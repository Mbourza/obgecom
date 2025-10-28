<?php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$db = DB::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action not specified']);
    exit;
}

try {
    $user_id = getCurrentUserId($db);
    if (!$user_id) {
        throw new Exception('Utilisateur non authentifié');
    }

    $action = $_POST['action'];

    if ($action === 'update_order_status') {
        handleSingleOrderUpdate($db, $user_id);
    } elseif ($action === 'update_orders_status_bulk') {
        handleBulkOrderUpdate($db, $user_id);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

} catch (Exception $e) {
    error_log("Error in update_or_status.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle single order status update
 */
function handleSingleOrderUpdate($db, $user_id) {
    $order_id = $_POST['order_id'] ?? '';
    $new_status = $_POST['status'] ?? '';
    
    if (empty($order_id) || empty($new_status)) {
        throw new Exception('ID de commande et statut requis');
    }

    // Get current order status
    $order = $db->get('orders', ['id', '=', $order_id])->first();
    if (!$order) {
        throw new Exception('Commande non trouvée');
    }

    // Update confirmation status in database
    $update_result = $db->update('orders', $order_id, [
        'status' => $new_status,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    if (!$update_result) {
        throw new Exception('Erreur lors de la mise à jour de la base de données');
    }

    // Handle agent confirmations
    $agent_confirmation_result = handleAgentConfirmation($db, $order_id, $user_id, $new_status, $order->status);

    $response = [
        'success' => true,
        'message' => 'Statut de confirmation mis à jour',
        'order_id' => $order_id,
        'new_status' => $new_status,
        'agent_confirmation' => $agent_confirmation_result
    ];

    echo json_encode($response);
}

/**
 * Handle bulk order status update
 */
function handleBulkOrderUpdate($db, $user_id) {
    $order_ids_json = $_POST['order_ids'] ?? '';
    $new_status = $_POST['status'] ?? '';
    
    if (empty($order_ids_json) || empty($new_status)) {
        throw new Exception('Liste des commandes et statut requis');
    }

    $order_ids = json_decode($order_ids_json, true);
    if (!is_array($order_ids) || empty($order_ids)) {
        throw new Exception('Format de liste des commandes invalide');
    }

    // Validate minimum order count
    if (count($order_ids) < 2) {
        throw new Exception('Au moins 2 commandes sont requises pour une mise à jour en lot');
    }

    $successful_count = 0;
    $failed_count = 0;
    $failed_orders = [];

    // Process each order
    foreach ($order_ids as $order_id) {
        try {
            // Get current order status
            $order = $db->get('orders', ['id', '=', $order_id])->first();
            if (!$order) {
                $failed_count++;
                $failed_orders[] = ['order_id' => $order_id, 'error' => 'Commande non trouvée'];
                continue;
            }

            // Update order status
            $update_result = $db->update('orders', $order_id, [
                'status' => $new_status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            if (!$update_result) {
                $failed_count++;
                $failed_orders[] = ['order_id' => $order_id, 'error' => 'Erreur de mise à jour'];
                continue;
            }

            // Handle agent confirmations
            handleAgentConfirmation($db, $order_id, $user_id, $new_status, $order->status);
            $successful_count++;

        } catch (Exception $e) {
            $failed_count++;
            $failed_orders[] = ['order_id' => $order_id, 'error' => $e->getMessage()];
            error_log("Error updating order {$order_id}: " . $e->getMessage());
        }
    }

    $response = [
        'success' => true,
        'message' => "Mise à jour en lot terminée",
        'successful_count' => $successful_count,
        'failed_count' => $failed_count,
        'total_count' => count($order_ids),
        'new_status' => $new_status
    ];

    // Add failed orders details if any
    if (!empty($failed_orders)) {
        $response['failed_orders'] = $failed_orders;
    }

    echo json_encode($response);
}

/**
 * Handle agent confirmation records based on status changes
 */
function handleAgentConfirmation($db, $order_id, $agent_id, $new_status, $current_status) {
    $result = ['action' => 'none', 'message' => ''];
    
    // Get shipping company ID for this user
    $shipping_settings = $db->getThisQuery("
        SELECT shipping_company_id 
        FROM shipping_settings 
        WHERE user_id = ? 
        ORDER BY priority ASC 
        LIMIT 1
    ", [$agent_id]);
    
    $shipping_company_id = $shipping_settings ? $shipping_settings[0]['shipping_company_id'] : null;
    
    // Define statuses that should have agent confirmations
    $confirmation_statuses = ['confirmed', 'delivered', 'processing'];
    
    // Handle status changes to confirmation statuses
    if (in_array($new_status, $confirmation_statuses)) {
        // Check if confirmation already exists
        $existing_confirmation = $db->getThisQuery("
            SELECT id, status 
            FROM agent_confirmations 
            WHERE agent_id = ? AND order_id = ?
        ", [$agent_id, $order_id]);
        
        if (empty($existing_confirmation)) {
            // Create new confirmation record
            $confirmation_data = [
                'agent_id' => $agent_id,
                'order_id' => $order_id,
                'shipping_company_id' => $shipping_company_id,
                'amount' => getOrderCommissionAmount($db, $order_id),
                'created_at' => date('Y-m-d H:i:s'),
                'status' => $new_status,
                'confirmed_at' => date('Y-m-d H:i:s')
            ];
            
            $insert_result = $db->insert('agent_confirmations', $confirmation_data);
            
            if ($insert_result) {
                $result['action'] = 'created';
                $result['message'] = 'Confirmation d\'agent créée';
            }
        } else {
            // Update existing confirmation
            $update_data = [
                'status' => $new_status,
                'confirmed_at' => date('Y-m-d H:i:s')
            ];
            
            if ($shipping_company_id) {
                $update_data['shipping_company_id'] = $shipping_company_id;
            }
            
            $update_result = $db->update('agent_confirmations', $existing_confirmation[0]['id'], $update_data);
            
            if ($update_result) {
                $result['action'] = 'updated';
                $result['message'] = 'Confirmation d\'agent mise à jour';
            }
        }
    } 
    // Handle status changes FROM confirmation statuses to non-confirmation statuses
    elseif (in_array($current_status, $confirmation_statuses) && !in_array($new_status, $confirmation_statuses)) {
        $delete_result = $db->query("
            DELETE FROM agent_confirmations 
            WHERE agent_id = ? AND order_id = ?
        ", [$agent_id, $order_id]);
        
        if ($delete_result) {
            $result['action'] = 'removed';
            $result['message'] = 'Confirmation d\'agent supprimée car le statut n\'est plus valide pour la commission';
        }
    }
    // Special case: Handle status changes FROM 'delivered' to any other status
    elseif ($current_status === 'delivered' && $new_status !== 'delivered') {
        // Check if there's an existing confirmation to avoid duplicates
        $existing_confirmation = $db->getThisQuery("
            SELECT id 
            FROM agent_confirmations 
            WHERE agent_id = ? AND order_id = ?
        ", [$agent_id, $order_id]);
        
        if (!empty($existing_confirmation)) {

            if (in_array($new_status, $confirmation_statuses)) {
                $update_data = [
                    'status' => $new_status,
                    'confirmed_at' => date('Y-m-d H:i:s')
                ];
                
                if ($shipping_company_id) {
                    $update_data['shipping_company_id'] = $shipping_company_id;
                }
                
                $update_result = $db->update('agent_confirmations', $existing_confirmation[0]['id'], $update_data);
                
                if ($update_result) {
                    $result['action'] = 'updated';
                    $result['message'] = 'Confirmation d\'agent mise à jour depuis delivered';
                }
            } else {
                // Delete if changing to non-confirmation status
                $delete_result = $db->query("
                    DELETE FROM agent_confirmations 
                    WHERE agent_id = ? AND order_id = ?
                ", [$agent_id, $order_id]);
                
                if ($delete_result) {
                    $result['action'] = 'removed';
                    $result['message'] = 'Confirmation d\'agent supprimée (changement depuis delivered)';
                }
            }
        }
    }
    
    return $result;
}

/**
 * Get order commission amount for agent
 */
function getOrderCommissionAmount($db, $order_id) {
    $order = $db->getThisQuery("
        SELECT total_amount, commission_rate 
        FROM orders 
        WHERE id = ?
    ", [$order_id]);
    
    if (empty($order)) {
        return 0;
    }
    
    $total_amount = $order[0]['total_amount'] ?? 0;
    $commission_rate = $order[0]['commission_rate'] ?? 0.05; // Default 5% commission
    
    return $total_amount * $commission_rate;
}

/**
 * Get current user ID from session
 */
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
} ?>