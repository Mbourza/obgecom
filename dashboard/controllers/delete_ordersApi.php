<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    
    // Check if required POST data exists
    if (!isset($_POST['action'])) {
        echo json_encode(['success' => false, 'message' => 'Missing action parameter']);
        exit;
    }

    $action = $_POST['action'];
    $order_ids = [];

    // Handle different actions
    if ($action === 'delete_order') {
        // Single order deletion
        if (!isset($_POST['order_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing order ID']);
            exit;
        }
        $order_ids = [intval($_POST['order_id'])];
        
    } elseif ($action === 'delete_orders') {
        // Bulk order deletion
        if (!isset($_POST['order_ids'])) {
            echo json_encode(['success' => false, 'message' => 'Missing order IDs']);
            exit;
        }
        
        // Decode the order IDs from JSON
        $order_ids = json_decode($_POST['order_ids'], true);
        
        if (!is_array($order_ids) || empty($order_ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid order IDs']);
            exit;
        }

        // Validate that we have at least 2 orders for bulk deletion
        if (count($order_ids) < 2) {
            echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner au moins 2 commandes']);
            exit;
        }
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }

    // Get the current user ID
    $user_id = getCurrentUserId($db);
    
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit;
    }

    // Sanitize order IDs (ensure they are integers)
    $sanitized_order_ids = array_map('intval', $order_ids);
    $sanitized_order_ids = array_filter($sanitized_order_ids, function($id) {
        return $id > 0;
    });

    if (empty($sanitized_order_ids)) {
        echo json_encode(['success' => false, 'message' => 'Invalid order IDs provided']);
        exit;
    }

    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($sanitized_order_ids) - 1) . '?';
    
    // First, verify that all orders belong to the current user
    $verify_query = "SELECT id FROM orders WHERE id IN ($placeholders) AND user_id = ?";
    $verify_params = array_merge($sanitized_order_ids, [$user_id]);
    $verified_orders = $db->getThisQuery($verify_query, $verify_params);
    
    if (count($verified_orders) !== count($sanitized_order_ids)) {
        echo json_encode([
            'success' => false,
            'message' => count($sanitized_order_ids) === 1 ? 'Order not found or access denied' : 'Some orders not found or access denied'
        ]);
        exit;
    }

    // Start transaction to ensure both deletions succeed or fail together
    $db->beginTransaction();
    
    try {
        // First, delete order items for these orders
        $delete_items_query = "DELETE FROM order_items WHERE order_id IN ($placeholders)";
        $items_result = $db->query($delete_items_query, $sanitized_order_ids);
        
        // Then delete the orders
        $delete_orders_query = "DELETE FROM orders WHERE id IN ($placeholders) AND user_id = ?";
        $delete_orders_params = array_merge($sanitized_order_ids, [$user_id]);
        $orders_result = $db->query($delete_orders_query, $delete_orders_params);
        
        if ($orders_result) {
            // Get the number of affected rows - this might vary depending on your DB class implementation
            // You may need to adjust this based on what your query() method returns
            $deleted_count = count($sanitized_order_ids); // Fallback count
            
            // Commit the transaction
            $db->commit();
            
            // Get updated order count for the user
            $count_query = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?";
            $count_result = $db->getThisQuery($count_query, [$user_id]);
            $remaining_orders = $count_result ? (int)$count_result[0]['order_count'] : 0;
            
            // Customize success message based on count
            $message = $deleted_count === 1 
                ? "Successfully deleted 1 order and its items"
                : "Successfully deleted {$deleted_count} orders and their items";
            
            echo json_encode([
                'success' => true,
                'message' => $message,
                'deleted_count' => $deleted_count,
                'remaining_orders' => $remaining_orders
            ]);
        } else {
            // Rollback transaction on failure
            $db->rollback();
            echo json_encode([
                'success' => false,
                'message' => count($sanitized_order_ids) === 1 ? 'Failed to delete order' : 'Failed to delete orders'
            ]);
        }
    } catch (Exception $trans_e) {
        // Rollback transaction on any error
        $db->rollback();
        throw $trans_e; // Re-throw to be caught by outer try-catch
    }

} catch (Exception $e) {
    // Log the error
    error_log("Error in delete_ordersApi.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the order(s)'
    ]);
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
} ?>