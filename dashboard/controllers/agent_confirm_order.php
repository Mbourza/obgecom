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

$action = $_POST['action'] ?? '';
if ($action !== 'confirm_order' && $action !== 'bulk_confirm_orders') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {

    $agent_id = getCurrentUserId($db);
    
    if (!$agent_id) {
        throw new Exception('Agent non authentifié');
    }
    
    // Check if user is an agent
    if (!isAgent($db, $agent_id)) {
        throw new Exception('Accès non autorisé - Agent requis');
    }
    
    if ($action === 'bulk_confirm_orders') {
        handleBulkAgentConfirmation($db, $agent_id);
    } else {
        handleSingleAgentConfirmation($db, $agent_id);
    }
    
} catch (Exception $e) {
    error_log("Error in agent_confirm_order.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle single order confirmation by agent
*/

function handleSingleAgentConfirmation($db, $agent_id) {
    $order_id = $_POST['order_id'] ?? '';
    $confirm_action = $_POST['confirm_action'] ?? 'confirm'; //
    
    if (empty($order_id)) {
        throw new Exception('ID de commande requis');
    }
    
    // Get order details - agents can confirm orders assigned to their region/zone
    $order = $db->getThisQuery("
        SELECT o.*, s.platform, s.api_url, s.api_key, s.Consumer_key, s.Consumer_secret,
               ac.id as confirmation_id, ac.status as confirmation_status
        FROM orders o 
        JOIN stores s ON o.store_id = s.id 
        LEFT JOIN agent_confirmations ac ON o.id = ac.order_id AND ac.agent_id = ?
        WHERE o.id = ? AND o.user_id IN (
            SELECT user_id FROM agent_assignments aa 
            WHERE aa.agent_id = ? OR o.user_id = ?
        )
    ", [$agent_id, $order_id, $agent_id, $agent_id]);
    
    if (empty($order)) {
        throw new Exception('Commande non trouvée ou non accessible');
    }
    
    $order = $order[0];
    
    if ($confirm_action === 'confirm') {
        // Confirm the order
        $result = confirmOrderByAgent($db, $order, $agent_id);
    } else {
        // Unconfirm the order
        $result = unconfirmOrderByAgent($db, $order, $agent_id);
    }
    
    echo json_encode($result);
}

/**
 * Handle bulk order confirmation by agent
 */
function handleBulkAgentConfirmation($db, $agent_id) {
    $order_ids_json = $_POST['order_ids'] ?? '';
    $confirm_action = $_POST['confirm_action'] ?? 'confirm';
    
    if (empty($order_ids_json)) {
        throw new Exception('IDs de commandes requis');
    }
    
    $order_ids = json_decode($order_ids_json, true);
    
    if (!is_array($order_ids) || empty($order_ids)) {
        throw new Exception('Format des IDs de commandes invalide');
    }
    
    $successful_confirmations = [];
    $failed_confirmations = [];
    
    foreach ($order_ids as $order_id) {
        try {
            // Get order details
            $order = $db->getThisQuery("
                SELECT o.*, s.platform, s.api_url, s.api_key, s.Consumer_key, s.Consumer_secret,
                       ac.id as confirmation_id, ac.status as confirmation_status
                FROM orders o 
                JOIN stores s ON o.store_id = s.id 
                LEFT JOIN agent_confirmations ac ON o.id = ac.order_id AND ac.agent_id = ?
                WHERE o.id = ? AND o.user_id IN (
                    SELECT user_id FROM agent_assignments aa 
                    WHERE aa.agent_id = ? OR o.user_id = ?
                )
            ", [$agent_id, $order_id, $agent_id, $agent_id]);
            
            if (empty($order)) {
                $failed_confirmations[] = ['order_id' => $order_id, 'error' => 'Commande non trouvée'];
                continue;
            }
            
            $order = $order[0];
            
            if ($confirm_action === 'confirm') {
                $result = confirmOrderByAgent($db, $order, $agent_id);
            } else {
                $result = unconfirmOrderByAgent($db, $order, $agent_id);
            }
            
            if ($result['success']) {
                $successful_confirmations[] = $order_id;
            } else {
                $failed_confirmations[] = ['order_id' => $order_id, 'error' => $result['message']];
            }
            
        } catch (Exception $e) {
            $failed_confirmations[] = ['order_id' => $order_id, 'error' => $e->getMessage()];
        }
    }
    
    $total_orders = count($order_ids);
    $successful_count = count($successful_confirmations);
    $failed_count = count($failed_confirmations);
    
    $response = [
        'success' => $successful_count > 0,
        'message' => "Confirmation terminée: {$successful_count}/{$total_orders} commandes confirmées",
        'total_orders' => $total_orders,
        'successful_count' => $successful_count,
        'failed_count' => $failed_count,
        'action' => $confirm_action
    ];
    
    if (!empty($failed_confirmations)) {
        $response['failed_confirmations'] = $failed_confirmations;
    }
    
    echo json_encode($response);
}

/**
 * Confirm order by agent
 */
function confirmOrderByAgent($db, $order, $agent_id) {
    $order_id = $order['id'];
    
    // Check if already confirmed by this agent
    if (!empty($order['confirmation_id'])) {
        return [
            'success' => false,
            'message' => 'Commande déjà confirmée par cet agent',
            'order_id' => $order_id
        ];
    }
    
    // Get agent's shipping company
    $shipping_settings = $db->getThisQuery("
        SELECT shipping_company_id 
        FROM shipping_settings 
        WHERE user_id = ? 
        ORDER BY priority ASC 
        LIMIT 1
    ", [$agent_id]);
    
    $shipping_company_id = $shipping_settings ? $shipping_settings[0]['shipping_company_id'] : null;
    
    // Create agent confirmation record
    $confirmation_data = [
        'agent_id' => $agent_id,
        'order_id' => $order_id,
        'shipping_company_id' => $shipping_company_id,
        'amount' => getOrderCommissionAmount($db, $order_id),
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'confirmed',
        'confirmed_at' => date('Y-m-d H:i:s')
    ];
    
    $insert_result = $db->insert('agent_confirmations', $confirmation_data);
    
    if (!$insert_result) {
        throw new Exception('Erreur lors de la création de la confirmation');
    }
    
    // Update order status to confirmed and set confirmed_by_agent
    $update_result = $db->update('orders', $order_id, [
        'status' => 'confirmed',
        'confirmed_by_agent' => $agent_id,
        'confirmed_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    if (!$update_result) {
        // Rollback the confirmation if order update fails
        $db->query("DELETE FROM agent_confirmations WHERE agent_id = ? AND order_id = ?", [$agent_id, $order_id]);
        throw new Exception('Erreur lors de la mise à jour de la commande');
    }
    
    // Try to update status in the connected store
    $api_update_success = false;
    $api_error_message = '';
    
    try {
        if ($order['platform'] === 'youcan') {
            $api_update_success = updateYouCanOrderStatus($order, 'confirmed');
        } elseif ($order['platform'] === 'woocommerce') {
            $api_update_success = updateWooCommerceOrderStatus($order, 'confirmed');
        }
    } catch (Exception $e) {
        $api_error_message = $e->getMessage();
        error_log("API update failed for order {$order_id}: " . $e->getMessage());
    }
    
    // Handle shipping company notification
    $shipping_result = handleShippingCompanyNotification($db, $order, 'confirmed', $agent_id);
    
    $response = [
        'success' => true,
        'message' => 'Commande confirmée avec succès',
        'order_id' => $order_id,
        'confirmed_by_agent' => $agent_id,
        'api_update_success' => $api_update_success
    ];
    
    if ($shipping_result['notification_sent']) {
        $response['shipping_notification'] = [
            'company' => $shipping_result['company_name'],
            'message' => $shipping_result['message']
        ];
    }
    
    if (!$api_update_success && !empty($api_error_message)) {
        $response['api_warning'] = 'Confirmation locale réussie, mais erreur API: ' . $api_error_message;
    }
    
    return $response;
}

/**
 * Unconfirm order by agent
 */
function unconfirmOrderByAgent($db, $order, $agent_id) {
    $order_id = $order['id'];
    
    // Check if confirmed by this agent
    if (empty($order['confirmation_id'])) {
        return [
            'success' => false,
            'message' => 'Cette commande n\'est pas confirmée par cet agent',
            'order_id' => $order_id
        ];
    }
    
    // Remove agent confirmation
    $delete_result = $db->query("
        DELETE FROM agent_confirmations 
        WHERE agent_id = ? AND order_id = ?
    ", [$agent_id, $order_id]);
    
    if (!$delete_result) {
        throw new Exception('Erreur lors de la suppression de la confirmation');
    }
    
    // Update order status back to pending and remove confirmed_by_agent
    $update_result = $db->update('orders', $order_id, [
        'status' => 'pending',
        'confirmed_by_agent' => null,
        'confirmed_at' => null,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    if (!$update_result) {
        throw new Exception('Erreur lors de la mise à jour de la commande');
    }
    
    // Try to update status in the connected store
    $api_update_success = false;
    $api_error_message = '';
    
    try {
        if ($order['platform'] === 'youcan') {
            $api_update_success = updateYouCanOrderStatus($order, 'pending');
        } elseif ($order['platform'] === 'woocommerce') {
            $api_update_success = updateWooCommerceOrderStatus($order, 'pending');
        }
    } catch (Exception $e) {
        $api_error_message = $e->getMessage();
        error_log("API update failed for order {$order_id}: " . $e->getMessage());
    }
    
    $response = [
        'success' => true,
        'message' => 'Confirmation de commande annulée avec succès',
        'order_id' => $order_id,
        'api_update_success' => $api_update_success
    ];
    
    if (!$api_update_success && !empty($api_error_message)) {
        $response['api_warning'] = 'Annulation locale réussie, mais erreur API: ' . $api_error_message;
    }
    
    return $response;
}

/**
 * Check if user is an agent
 */
function isAgent($db, $user_id) {
    $user = $db->getThisQuery("
        SELECT role 
        FROM users 
        WHERE id = ?
    ", [$user_id]);
    
    return !empty($user) && $user[0]['role'] === 'agent';
}

/**
 * Get current user ID from session
 */
function getCurrentUserId($db) {
    if (!isset($_SESSION['user'])) {
        return null;
    }

    $username = $_SESSION['user']['username'];
    $user_id = null;

    $user = $db->getIOrN("users", "username", $username);

    if ($user && isset($user->id)) {
        $user_id = $user->id;
    }

    return $user_id;
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
    $commission_rate = $order[0]['commission_rate'] ?? 0.05;
    
    return $total_amount * $commission_rate;
}

// Include the existing functions from your original file
function handleShippingCompanyNotification($db, $order, $new_status, $user_id) {
    // Copy the function from your original file
    $result = ['notification_sent' => false, 'company_name' => '', 'message' => ''];

    if (!in_array($new_status, ['confirmed', 'delivered'])) {
        return $result;
    }

    $shipping_info = $db->getThisQuery("
        SELECT 
            ss.shipping_company_id,
            ss.company_name,
            ss.auto_track,
            sc.name as company_name,
            sc.api_url,
            sc.api_key,
            sc.api_secret,
            sc.supports_tracking,
            sc.is_active
        FROM shipping_settings ss
        JOIN shipping_companies sc ON ss.shipping_company_id = sc.id
        WHERE ss.user_id = ? AND sc.is_active = 1
        ORDER BY ss.priority ASC
        LIMIT 1
    ", [$user_id]);

    if (empty($shipping_info)) {
        return $result;
    }

    $shipping_company = $shipping_info[0];
    $company_name = strtolower($shipping_company['company_name']);

    try {
        if ($company_name === 'ozonexpress') {
            // Implementation for OzonExpress - copy from your original file
            // ... (implementation details)
            $result['notification_sent'] = true;
            $result['company_name'] = $shipping_company['company_name'];
            $result['message'] = 'Commande envoyée à OzonExpress avec succès';
        }
    } catch (Exception $e) {
        error_log("Shipping company notification failed: " . $e->getMessage());
        $result['message'] = 'Erreur lors de l\'envoi à la compagnie de livraison: ' . $e->getMessage();
    }

    return $result;
}

function updateYouCanOrderStatus($order, $new_status) {
    // Copy from your original file
    // ... implementation
    return true;
}

function updateWooCommerceOrderStatus($order, $new_status) {
    // Copy from your original file
    // ... implementation  
    return true;
}

?>