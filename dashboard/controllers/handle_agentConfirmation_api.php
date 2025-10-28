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
    $agent_id = getCurrentAgentId($db);
    if (!$agent_id) {
        throw new Exception('Agent non authentifié');
    }

    // Get agent information
    $agent = $db->get('agents', ['id', '=', $agent_id])->first();
    if (!$agent || !$agent->is_active) {
        throw new Exception('Agent non trouvé ou inactif');
    }

    $action = $_POST['action'];

    if ($action === 'update_order_status') {
        handleSingleOrderUpdate($db, $agent_id, $agent);
    } elseif ($action === 'update_orders_status_bulk') {
        handleBulkOrderUpdate($db, $agent_id, $agent);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

} catch (Exception $e) {
    error_log("Error in update_order_status.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle single order status update
 */
function handleSingleOrderUpdate($db, $agent_id, $agent) {
    $order_id = $_POST['order_id'] ?? '';
    $new_status = $_POST['status'] ?? '';
    
    if (empty($order_id) || empty($new_status)) {
        throw new Exception('ID de commande et statut requis');
    }

    // Get current order
    $order = $db->get('orders', ['id', '=', $order_id])->first();
    if (!$order) {
        throw new Exception('Commande non trouvée');
    }

    $old_status = $order->status;
    $current_time = date('Y-m-d H:i:s');

    // Prepare order update data
    $order_update_data = [
        'status' => $new_status,
        'updated_at' => $current_time
    ];

    // Add agent confirmation info to orders table if confirming
    $confirmation_statuses = ['confirmed', 'delivered', 'processing'];
    if (in_array($new_status, $confirmation_statuses)) {
        $order_update_data['confirmed_by_agent'] = $agent_id;
        $order_update_data['handled_at'] = $current_time;
    }

    // Update order in database
    $update_result = $db->update('orders', $order_id, $order_update_data);
    if (!$update_result) {
        throw new Exception('Erreur lors de la mise à jour de la commande');
    }

    // Handle agent confirmation record
    $agent_confirmation_result = handleAgentConfirmation($db, $order_id, $agent_id, $agent, $new_status, $old_status);

    $response = [
        'success' => true,
        'message' => 'Statut de commande mis à jour avec succès',
        'order_id' => $order_id,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'confirmed_by' => $agent->name,
        'confirmed_at' => $current_time,
        'agent_confirmation' => $agent_confirmation_result
    ];

    echo json_encode($response);
}

/**
 * Handle bulk order status update
 */
function handleBulkOrderUpdate($db, $agent_id, $agent) {
    $order_ids_json = $_POST['order_ids'] ?? '';
    $new_status = $_POST['status'] ?? '';
    
    if (empty($order_ids_json) || empty($new_status)) {
        throw new Exception('Liste des commandes et statut requis');
    }

    $order_ids = json_decode($order_ids_json, true);
    if (!is_array($order_ids) || empty($order_ids)) {
        throw new Exception('Format de liste des commandes invalide');
    }

    if (count($order_ids) < 2) {
        throw new Exception('Au moins 2 commandes sont requises pour une mise à jour en lot');
    }

    $successful_count = 0;
    $failed_count = 0;
    $failed_orders = [];
    $current_time = date('Y-m-d H:i:s');
    
    $confirmation_statuses = ['confirmed', 'delivered', 'processing'];

    // Process each order
    foreach ($order_ids as $order_id) {
        try {
            // Get current order
            $order = $db->get('orders', ['id', '=', $order_id])->first();
            if (!$order) {
                $failed_count++;
                $failed_orders[] = ['order_id' => $order_id, 'error' => 'Commande non trouvée'];
                continue;
            }

            $old_status = $order->status;

            // Prepare order update data
            $order_update_data = [
                'status' => $new_status,
                'updated_at' => $current_time
            ];

            // Add agent confirmation info if confirming
            if (in_array($new_status, $confirmation_statuses)) {
                $order_update_data['confirmed_by_agent'] = $agent_id;
                $order_update_data['handled_at'] = $current_time;
            }

            // Update order
            $update_result = $db->update('orders', $order_id, $order_update_data);
            if (!$update_result) {
                $failed_count++;
                $failed_orders[] = ['order_id' => $order_id, 'error' => 'Erreur de mise à jour'];
                continue;
            }

            // Handle agent confirmation record
            handleAgentConfirmation($db, $order_id, $agent_id, $agent, $new_status, $old_status);
            
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
        'new_status' => $new_status,
        'confirmed_by' => $agent->name,
        'confirmed_at' => $current_time
    ];

    if (!empty($failed_orders)) {
        $response['failed_orders'] = $failed_orders;
    }

    echo json_encode($response);
}

/**
 * Handle agent confirmation records based on status changes
 */
function handleAgentConfirmation($db, $order_id, $agent_id, $agent, $new_status, $old_status) {

    $result = ['action' => 'none', 'message' => ''];
    $current_time = date('Y-m-d H:i:s');
    
    // Get agent's user_id and shipping company
    $user_id = $agent->user_id;
    
    // Define statuses that should have agent confirmations
    $confirmation_statuses = ['confirmed', 'delivered'];
    
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
                'user_id' => $user_id,
                'amount' => 0,
                'created_at' => $current_time,
                'status' => $new_status,
                'handled_at' => $current_time
            ];
            
            $insert_result = $db->insert('agent_confirmations', $confirmation_data);
            
            if ($insert_result) {
                $result['action'] = 'created';
                $result['message'] = 'Nouvelle confirmation d\'agent créée';
                $result['confirmation_id'] = $insert_result;
            }

        } else {
            // Update existing confirmation
            $update_data = [
                'status' => $new_status,
                'handled_at' => $current_time,
                'amount' => 0
            ];
            
            $update_result = $db->update('agent_confirmations', $existing_confirmation[0]['id'], $update_data);
            
            if ($update_result) {
                $result['action'] = 'updated';
                $result['message'] = 'Confirmation d\'agent mise à jour';
                $result['confirmation_id'] = $existing_confirmation[0]['id'];
            }
        }
    } 
    // Handle status changes FROM confirmation statuses to non-confirmation statuses
    elseif (in_array($old_status, $confirmation_statuses) && !in_array($new_status, $confirmation_statuses)) {
        $delete_result = $db->query("
            DELETE FROM agent_confirmations 
            WHERE agent_id = ? AND order_id = ?
        ", [$agent_id, $order_id]);
        
        if ($delete_result) {
            $result['action'] = 'removed';
            $result['message'] = 'Confirmation d\'agent supprimée (statut non valide pour commission)';
        }
    }
    
    return $result;
}

/**
 * Get current agent ID from session
 */
function getCurrentAgentId($db) {
    if (!isset($_SESSION['user'])) {
        return null;
    }

    $username = $_SESSION['user']['username'];
    $agent_id = null;

    // First try to get from agents table using email
    $agent = $db->getIOrN("agents", "email", $username);
    if ($agent && isset($agent->id)) {
        return $agent->id;
    }

    // If not found by email, try by name (fallback)
    $agent = $db->getIOrN("agents", "name", $username);
    if ($agent && isset($agent->id)) {
        return $agent->id;
    }

    // Last fallback: check if user exists and has associated agent
    $user = $db->getIOrN("users", "username", $username);
    if ($user && isset($user->id)) {
        $agent = $db->get('agents', ['user_id', '=', $user->id])->first();
        if ($agent) {
            return $agent->id;
        }
    }

    return null;
}

?>