<?php
// bulk_assign_orders.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'bulk_assign_orders') {
    echo json_encode(['success' => false, 'message' => 'Action non valide']);
    exit;
}

$db = DB::getInstance();
$user_id = getCurrentUserId($db);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé. Veuillez vous connecter.']);
    exit;
}

try {
    $order_ids = isset($_POST['order_ids']) ? json_decode($_POST['order_ids'], true) : [];
    $agent_id = isset($_POST['agent_id']) ? intval($_POST['agent_id']) : 0;
    $assignment_type = $_POST['assignment_type'] ?? 'normal';
    $priority = $_POST['priority'] ?? 0;
    $notes = trim($_POST['notes'] ?? '');
    $use_performance = isset($_POST['use_performance']) ? filter_var($_POST['use_performance'], FILTER_VALIDATE_BOOLEAN) : false;
    
    // Validation
    if (empty($order_ids) || !is_array($order_ids)) {
        echo json_encode(['success' => false, 'message' => 'Aucune commande sélectionnée']);
        exit;
    }
    
    if (!$use_performance && $agent_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Agent non valide']);
        exit;
    }
    
    // If using performance-based assignment, find best agent for each order
    if ($use_performance) {
        $assigned_orders = assignByPerformance($db, $user_id, $order_ids, $assignment_type, $priority, $notes);
        echo json_encode($assigned_orders);
        exit;
    }
    
    // Manual assignment - validate agent
    $agent_check = $db->getThisQuery("SELECT id, name, is_active FROM agents WHERE id = ?", [$agent_id]);
    if (empty($agent_check)) {
        echo json_encode(['success' => false, 'message' => 'Agent non trouvé']);
        exit;
    }
    
    $agent_data = $agent_check[0];
    if (!$agent_data['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Agent non actif']);
        exit;
    }
    
    // Get store_id from agent or first order
    $agent_store_id = null;
    if (!empty($order_ids)) {
        $first_order = $db->getThisQuery("SELECT store_id FROM orders WHERE id = ? LIMIT 1", [intval($order_ids[0])]);
        $agent_store_id = $first_order ? $first_order[0]['store_id'] : null;
    }
    
    // Initialize counters
    $assigned_count = 0;
    $reassigned_count = 0;
    $skipped_count = 0;
    $failed_count = 0;
    $processed_orders = [];
    
    // Process each order
    foreach ($order_ids as $order_id) {
        $order_id = intval($order_id);
        if ($order_id <= 0) {
            $failed_count++;
            continue;
        }
        
        try {
            // Check if order exists and belongs to user
            $order_check = $db->getThisQuery("
                SELECT store_id 
                FROM orders 
                WHERE id = ? AND user_id = ?
                LIMIT 1
            ", [$order_id, $user_id]);
            
            if (empty($order_check)) {
                $failed_count++;
                $processed_orders[] = [
                    'order_id' => $order_id,
                    'status' => 'failed',
                    'reason' => 'Commande non trouvée ou non autorisée'
                ];
                continue;
            }
            
            $store_id = $order_check[0]['store_id'];
            
            // Check if already assigned to this agent
            $existing = $db->getThisQuery("
                SELECT id, agent_id 
                FROM agent_order_assignments 
                WHERE order_id = ? AND user_id = ?
                LIMIT 1
            ", [$order_id, $user_id]);
            
            if (!empty($existing)) {
                $existing_agent = $existing[0]['agent_id'];
                
                if ($existing_agent == $agent_id) {
                    $skipped_count++;
                    $processed_orders[] = [
                        'order_id' => $order_id,
                        'status' => 'skipped',
                        'reason' => 'Déjà assigné à cet agent'
                    ];
                    continue;
                }
                
                // Different agent - this is a reassignment
                $reassigned_count++;
                
                // Update existing assignment
                $update_sql = "UPDATE agent_order_assignments 
                              SET agent_id = ?, priority_score = ?, notes = ?, assigned_at = ?
                              WHERE order_id = ? AND user_id = ?";
                
                $db->update_this($update_sql, [
                    $agent_id,
                    getPriorityScore($priority),
                    $notes,
                    date('Y-m-d H:i:s'),
                    $order_id,
                    $user_id
                ]);
                
                $processed_orders[] = [
                    'order_id' => $order_id,
                    'status' => 'reassigned',
                    'previous_agent' => $existing_agent,
                    'new_agent' => $agent_id
                ];
                
            } else {
                // New assignment
                $db->insert('agent_order_assignments', [
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                    'agent_id' => $agent_id,
                    'store_id' => $store_id,
                    'status' => $assignment_type === 'auto_confirm' ? 'confirmed' : 'pending',
                    'priority_score' => getPriorityScore($priority),
                    'notes' => $notes,
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'confirmed_at' => $assignment_type === 'auto_confirm' ? date('Y-m-d H:i:s') : null
                ]);
                
                $assigned_count++;
                $processed_orders[] = [
                    'order_id' => $order_id,
                    'status' => 'assigned',
                    'agent' => $agent_id
                ];
                
                // If auto-confirmed, update order status
                if ($assignment_type === 'auto_confirm') {
                    $db->update_this(
                        "UPDATE orders SET confirmed_by_agent = 1, handled_at = NOW() WHERE id = ?",
                        [$order_id]
                    );
                }
            }
            
        } catch (Exception $e) {
            $failed_count++;
            $processed_orders[] = [
                'order_id' => $order_id,
                'status' => 'failed',
                'reason' => $e->getMessage()
            ];
            error_log("Error processing order $order_id: " . $e->getMessage());
        }
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Assignation terminée',
        'statistics' => [
            'total_processed' => count($order_ids),
            'assigned_count' => $assigned_count,
            'reassigned_count' => $reassigned_count,
            'skipped_count' => $skipped_count,
            'failed_count' => $failed_count
        ],
        'processed_orders' => $processed_orders,
        'agent' => [
            'id' => $agent_id,
            'name' => $agent_data['name'] ?? 'N/A'
        ],
        'assignment_type' => $assignment_type
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue: ' . $e->getMessage()
    ]);
}

// Performance-based assignment function
function assignByPerformance($db, $user_id, $order_ids, $assignment_type, $priority, $notes) {
    $assigned_by_agent = [];
    $processed_orders = [];
    $failed_count = 0;
    
    // Get all active agents with their performance metrics
    $agents = $db->getThisQuery("
        SELECT 
            a.id,
            a.name,
            a.score,
            a.confirmation_rate,
            a.total_earned,
            a.is_active,
            COUNT(aoa.id) as current_assignments
        FROM agents a
        LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id 
            AND aoa.status IN ('pending', 'confirmed')
            AND DATE(aoa.assigned_at) = CURDATE()
        WHERE a.is_active = 1
        GROUP BY a.id
        ORDER BY a.score DESC, a.confirmation_rate DESC
    ");
    
    if (empty($agents)) {
        return [
            'success' => false,
            'message' => 'Aucun agent actif disponible'
        ];
    }
    
    foreach ($order_ids as $order_id) {
        $order_id = intval($order_id);
        if ($order_id <= 0) {
            $failed_count++;
            continue;
        }
        
        try {
            // Get order details and store
            $order = $db->getThisQuery("
                SELECT store_id, customer_ville, total_amount 
                FROM orders 
                WHERE id = ? AND user_id = ?
                LIMIT 1
            ", [$order_id, $user_id]);
            
            if (empty($order)) {
                $failed_count++;
                $processed_orders[] = [
                    'order_id' => $order_id,
                    'status' => 'failed',
                    'reason' => 'Commande non trouvée'
                ];
                continue;
            }
            
            $order_data = $order[0];
            $selected_agent = null;
            
            // Strategy 1: Check if any agent is specialized for this store
            foreach ($agents as $agent) {
                // Check agent capacity (max 20 assignments per day)
                if ($agent['current_assignments'] >= 20) {
                    continue;
                }
                
                // Check if agent has handled this store before (you might have this data)
                $store_experience = $db->getThisQuery("
                    SELECT COUNT(*) as count 
                    FROM agent_order_assignments 
                    WHERE agent_id = ? AND store_id = ?
                    LIMIT 1
                ", [$agent['id'], $order_data['store_id']]);
                
                if ($store_experience && $store_experience[0]['count'] > 0) {
                    $selected_agent = $agent;
                    break;
                }
            }
            
            // Strategy 2: If no store specialist, pick best available agent
            if (!$selected_agent) {
                foreach ($agents as $agent) {
                    if ($agent['current_assignments'] < 20) {
                        $selected_agent = $agent;
                        break;
                    }
                }
            }
            
            // Strategy 3: If all agents at capacity, pick least loaded
            if (!$selected_agent) {
                usort($agents, function($a, $b) {
                    return $a['current_assignments'] - $b['current_assignments'];
                });
                $selected_agent = $agents[0];
            }
            
            if (!$selected_agent) {
                $failed_count++;
                $processed_orders[] = [
                    'order_id' => $order_id,
                    'status' => 'failed',
                    'reason' => 'Aucun agent disponible'
                ];
                continue;
            }
            
            // Check if already assigned
            $existing = $db->getThisQuery("
                SELECT id, agent_id 
                FROM agent_order_assignments 
                WHERE order_id = ? AND user_id = ?
                LIMIT 1
            ", [$order_id, $user_id]);
            
            if (!empty($existing)) {
                $existing_agent = $existing[0]['agent_id'];
                
                if ($existing_agent == $selected_agent['id']) {
                    $processed_orders[] = [
                        'order_id' => $order_id,
                        'status' => 'skipped',
                        'reason' => 'Déjà assigné à cet agent'
                    ];
                    continue;
                }
                
                // Reassign to better agent
                $update_sql = "UPDATE agent_order_assignments 
                              SET agent_id = ?, priority_score = ?, notes = ?, assigned_at = ?
                              WHERE order_id = ? AND user_id = ?";
                
                $db->update_this($update_sql, [
                    $selected_agent['id'],
                    getPriorityScore($priority),
                    $notes . " (Auto-assigné par performance)",
                    date('Y-m-d H:i:s'),
                    $order_id,
                    $user_id
                ]);
                
                $processed_orders[] = [
                    'order_id' => $order_id,
                    'status' => 'reassigned',
                    'agent' => $selected_agent['id'],
                    'agent_name' => $selected_agent['name'],
                    'reason' => 'Réassigné pour meilleure performance'
                ];
                
            } else {
                // New assignment
                $db->insert('agent_order_assignments', [
                    'order_id' => $order_id,
                    'user_id' => $user_id,
                    'agent_id' => $selected_agent['id'],
                    'store_id' => $order_data['store_id'],
                    'status' => $assignment_type === 'auto_confirm' ? 'confirmed' : 'pending',
                    'priority_score' => getPriorityScore($priority),
                    'notes' => $notes . " (Auto-assigné par performance)",
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'confirmed_at' => $assignment_type === 'auto_confirm' ? date('Y-m-d H:i:s') : null
                ]);
                
                $processed_orders[] = [
                    'order_id' => $order_id,
                    'status' => 'assigned',
                    'agent' => $selected_agent['id'],
                    'agent_name' => $selected_agent['name'],
                    'score' => $selected_agent['score'],
                    'confirmation_rate' => $selected_agent['confirmation_rate']
                ];
                
                // Track assignments per agent
                if (!isset($assigned_by_agent[$selected_agent['id']])) {
                    $assigned_by_agent[$selected_agent['id']] = [
                        'agent' => $selected_agent['name'],
                        'count' => 0
                    ];
                }
                $assigned_by_agent[$selected_agent['id']]['count']++;
                
                // If auto-confirmed, update order
                if ($assignment_type === 'auto_confirm') {
                    $db->update_this(
                        "UPDATE orders SET confirmed_by_agent = 1, handled_at = NOW() WHERE id = ?",
                        [$order_id]
                    );
                }
            }
            
        } catch (Exception $e) {
            $failed_count++;
            $processed_orders[] = [
                'order_id' => $order_id,
                'status' => 'failed',
                'reason' => $e->getMessage()
            ];
        }
    }
    
    // Calculate statistics
    $total_assigned = count($processed_orders) - $failed_count;
    $assigned_count = 0;
    $reassigned_count = 0;
    $skipped_count = 0;
    
    foreach ($processed_orders as $order) {
        if ($order['status'] === 'assigned') $assigned_count++;
        if ($order['status'] === 'reassigned') $reassigned_count++;
        if ($order['status'] === 'skipped') $skipped_count++;
    }
    
    return [
        'success' => true,
        'message' => 'Assignation par performance terminée',
        'statistics' => [
            'total_processed' => count($order_ids),
            'assigned_count' => $assigned_count,
            'reassigned_count' => $reassigned_count,
            'skipped_count' => $skipped_count,
            'failed_count' => $failed_count
        ],
        'agents_used' => $assigned_by_agent,
        'processed_orders' => $processed_orders,
        'assignment_type' => $assignment_type . ' (auto-performance)'
    ];
}

// Helper function to get priority score
function getPriorityScore($priority) {
    $scores = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'urgent' => 4
    ];
    return $scores[$priority] ?? 2;
}

// Existing function from your code
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    $identifier = $_SESSION['user']['username'];

    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

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