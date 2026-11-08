<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// Include AI confirmation service
if(file_exists(stream_resolve_include_path("../classes/AIConfirmationService.php"))) {
    require_once("../classes/AIConfirmationService.php");
}

if (in_array($_POST['action'], ['assign_agent', 'reassign_agent', 'check_ai_status', 'force_assign_after_ai'])) {
    $db = DB::getInstance();
    $user_id = getCurrentUserId($db);
    
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non authentifié'
        ]);
        exit;
    }
    
    $order_id = $_POST['order_id'] ?? null;
    
    if (!$order_id) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de commande manquant'
        ]);
        exit;
    }
    
    // Initialize AI service
    $aiService = new AIConfirmationService($db, $user_id);
    
    // Handle different actions
    switch ($_POST['action']) {
        case 'check_ai_status':
            // Check if we can assign this order
            $canAssign = !$aiService->shouldWaitForAI($order_id);
            $aiStatus = $aiService->getLatestAttempt($order_id);
            
            echo json_encode([
                'success' => true,
                'can_assign' => $canAssign,
                'ai_status' => $aiStatus,
                'ai_enabled' => $aiService->isAIConfirmationEnabled()
            ]);
            exit;
            
        case 'force_assign_after_ai':
            // Force assign even if AI is pending (admin override)
            $canForce = isset($_SESSION['user']['role']) && 
                        in_array($_SESSION['user']['role'], ['admin', 'super_admin']);
            
            if (!$canForce) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Vous n\'avez pas la permission de forcer l\'assignation'
                ]);
                exit;
            }
            
            // Escalate AI process
            $attempt = $aiService->getLatestAttempt($order_id);
            if ($attempt && $attempt['escalated_to_agent'] == 0) {
                $db->update(
                    'ai_confirmation_attempts',
                    [
                        'status' => 'escalated',
                        'escalated_to_agent' => 1,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    "order_id = ? AND user_id = ?",
                    [$order_id, $user_id]
                );
            }
            break;
    }
    
    // Check AI confirmation before assignment
    if ($_POST['action'] !== 'force_assign_after_ai') {
        $shouldWait = $aiService->shouldWaitForAI($order_id);
        
        if ($shouldWait) {
            $attempt = $aiService->getLatestAttempt($order_id);
            $status = $attempt ? $attempt['status'] : 'unknown';
            $attemptsLeft = $attempt ? $attempt['attempts_remaining'] : 0;
            $nextAttempt = $attempt ? $attempt['next_attempt_at'] : null;
            
            echo json_encode([
                'success' => false,
                'message' => 'Cette commande est en cours de confirmation par AI. Veuillez patienter.',
                'ai_status' => $status,
                'attempts_remaining' => $attemptsLeft,
                'next_attempt_at' => $nextAttempt,
                'can_override' => isset($_SESSION['user']['role']) && 
                                 in_array($_SESSION['user']['role'], ['admin', 'super_admin'])
            ]);
            exit;
        }
    }
    
    // Proceed with assignment if AI confirmation is not needed or already complete
    $agent_id = $_POST['agent_id'] ?? null;
    $notes = $_POST['notes'] ?? '';
    $priority = $_POST['priority'] ?? 0;
    $assignment_type = $_POST['assignment_type'] ?? 'normal'; // normal, auto_confirm
    
    if (!$agent_id && $_POST['action'] !== 'force_assign_after_ai') {
        echo json_encode([
            'success' => false,
            'message' => 'ID d\'agent manquant'
        ]);
        exit;
    }
    
    try {
        // First, get the store_id from the orders table
        $order_data = $db->getThisQuery("
            SELECT store_id, status, shipping_status, customer_id
            FROM orders 
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ", [$order_id, $user_id]);
        
        if (empty($order_data)) {
            echo json_encode([
                'success' => false,
                'message' => 'Commande non trouvée'
            ]);
            exit;
        }
        
        $store_id = $order_data[0]['store_id'];
        $order_status = $order_data[0]['status'];
        $shipping_status = $order_data[0]['shipping_status'];
        
        // Check if order is ready for assignment
        if ($order_status === 'cancelled') {
            echo json_encode([
                'success' => false,
                'message' => 'Cette commande est annulée et ne peut pas être assignée'
            ]);
            exit;
        }
        
        // Check if order already has shipping/tracking
        $shipping_check = $db->getThisQuery("
            SELECT tracking_number FROM orders 
            WHERE id = ? AND user_id = ? AND tracking_number IS NOT NULL
        ", [$order_id, $user_id]);
        
        if (!empty($shipping_check) && !empty($shipping_check[0]['tracking_number'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Cette commande a déjà été envoyée et ne peut pas être réassignée'
            ]);
            exit;
        }
        
        if ($_POST['action'] === 'assign_agent' || $_POST['action'] === 'force_assign_after_ai') {
            // Check if assignment already exists
            $existing = $db->getThisQuery("
                SELECT id, status FROM agent_order_assignments 
                WHERE order_id = ? AND user_id = ?
            ", [$order_id, $user_id]);
            
            if (!empty($existing)) {
                // If assignment exists but is completed or cancelled, allow reassignment
                $current_status = $existing[0]['status'];
                if ($current_status === 'completed') {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cette commande a déjà été complétée par un agent'
                    ]);
                    exit;
                } elseif ($current_status !== 'cancelled') {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cette commande a déjà un agent assigné. Utilisez réassigner pour changer.',
                        'existing_assignment' => $existing[0]
                    ]);
                    exit;
                }
            }
            
            // Create new assignment with store_id
            $insert_data = [
                'order_id' => $order_id,
                'user_id' => $user_id,
                'agent_id' => $agent_id,
                'store_id' => $store_id,
                'status' => $assignment_type === 'auto_confirm' ? 'assigned' : 'pending',
                'priority_score' => $priority,
                'notes' => $notes,
                'assigned_at' => date('Y-m-d H:i:s'),
                'assignment_type' => $assignment_type
            ];
            
            $db->insert('agent_order_assignments', $insert_data);
            
            // Update order status
            $db->update(
                'orders',
                [
                    'status' => 'assigned_to_agent',
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                "id = ? AND user_id = ?",
                [$order_id, $user_id]
            );
            
            // Log the assignment
            $log_data = [
                'order_id' => $order_id,
                'user_id' => $user_id,
                'action' => 'assign_agent',
                'details' => json_encode([
                    'agent_id' => $agent_id,
                    'assignment_type' => $assignment_type,
                    'priority' => $priority,
                    'notes' => $notes
                ]),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $db->insert('order_action_logs', $log_data);
            
            // If AI confirmation was pending and we're assigning, mark as escalated
            if ($aiService->isAIConfirmationEnabled()) {
                $attempt = $aiService->getLatestAttempt($order_id);
                if ($attempt && $attempt['escalated_to_agent'] == 0) {
                    $db->update(
                        'ai_confirmation_attempts',
                        [
                            'status' => 'escalated',
                            'escalated_to_agent' => 1,
                            'updated_at' => date('Y-m-d H:i:s')
                        ],
                        "order_id = ? AND user_id = ?",
                        [$order_id, $user_id]
                    );
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Agent assigné avec succès',
                'data' => [
                    'order_id' => $order_id,
                    'agent_id' => $agent_id,
                    'assignment_type' => $assignment_type,
                    'ai_escalated' => $aiService->isAIConfirmationEnabled()
                ]
            ]);
            
        } elseif ($_POST['action'] === 'reassign_agent') {
            // Check if assignment exists
            $existing = $db->getThisQuery("
                SELECT id, status FROM agent_order_assignments 
                WHERE order_id = ? AND user_id = ?
            ", [$order_id, $user_id]);
            
            if (empty($existing)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Aucun agent assigné à cette commande'
                ]);
                exit;
            }
            
            // Update existing assignment
            $sql = "UPDATE agent_order_assignments 
                    SET agent_id = ?, 
                        priority_score = ?, 
                        notes = ?, 
                        assigned_at = ?,
                        reassigned_at = ?,
                        reassignment_count = reassignment_count + 1
                    WHERE order_id = ? AND user_id = ?";
            
            $db->update_this($sql, [
                $agent_id, 
                $priority, 
                $notes, 
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
                $order_id, 
                $user_id
            ]);
            
            // Log the reassignment
            $log_data = [
                'order_id' => $order_id,
                'user_id' => $user_id,
                'action' => 'reassign_agent',
                'details' => json_encode([
                    'old_agent_id' => $existing[0]['agent_id'] ?? null,
                    'new_agent_id' => $agent_id,
                    'priority' => $priority,
                    'notes' => $notes
                ]),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $db->insert('order_action_logs', $log_data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Agent réassigné avec succès',
                'data' => [
                    'order_id' => $order_id,
                    'agent_id' => $agent_id,
                    'reassignment_count' => isset($existing[0]['reassignment_count']) ? $existing[0]['reassignment_count'] + 1 : 1
                ]
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    // This can be username, email, or phone
    $identifier = $_SESSION['user']['username'];

    // Prefer explicit ID if available
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Try to match identifier against username, email, or phone
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

    return null;
}
?>