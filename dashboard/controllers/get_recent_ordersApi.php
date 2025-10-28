<?php 
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Check if request is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get current user ID
$user_id = getCurrentUserId($db);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Get pagination parameters
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    // Validate pagination parameters
    if ($page < 1) $page = 1;
    if ($limit < 1 || $limit > 100) $limit = 10;
    
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination (only delivered orders with shipping company for current user)
    $count_query = "
        SELECT COUNT(*) as total 
        FROM orders o
        INNER JOIN shipping_companies sc ON o.shipping_company_id = sc.id
        WHERE o.shipping_status = ? AND o.user_id = ?
    ";
    $count_result = $db->getThisQuery($count_query, ['processing', $user_id]);
    
    if (!is_array($count_result) || empty($count_result)) {
        // No orders found - return empty result
        $response_data = [
            'orders' => [],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 0,
                'total_orders' => 0,
                'per_page' => $limit,
                'has_next' => false,
                'has_prev' => false
            ]
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $response_data
        ]);
        exit;
    }
    
    $total_orders = intval($count_result[0]['total']);
    
    if ($total_orders === 0) {
        // No orders found - return empty result
        $response_data = [
            'orders' => [],
            'pagination' => [
                'current_page' => 1,
                'total_pages' => 0,
                'total_orders' => 0,
                'per_page' => $limit,
                'has_next' => false,
                'has_prev' => false
            ]
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $response_data
        ]);
        exit;
    }
    
    $total_pages = ceil($total_orders / $limit);
    
    // Main query to get recent delivered orders for current user
    $orders_query = "
        SELECT 
            o.id,
            o.order_number,
            o.customer_name,
            o.status,
            o.order_date as shipped_date,
            o.tracking_number,
            o.shipping_company_id,
            sc.name as carrier_name,
            sc.tracking_url as tracking_url_template,
            o.created_at,
            o.updated_at
        FROM orders o
        INNER JOIN shipping_companies sc ON o.shipping_company_id = sc.id
        WHERE o.shipping_status = ? AND o.user_id = ?
        ORDER BY o.order_date DESC, o.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $orders_result = $db->getThisQuery($orders_query, ['processing', $user_id, $limit, $offset]);

    if (!is_array($orders_result)) {
        throw new Exception('Error retrieving orders from database.');
    }
    
    // Handle empty result
    if (empty($orders_result)) {
        $response_data = [
            'orders' => [],
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_orders' => $total_orders,
                'per_page' => $limit,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $response_data
        ]);
        exit;
    }

    $formatted_orders = array_map(function($order) {
        $tracking_url = null;
        if (!empty($order['tracking_number']) && !empty($order['tracking_url_template'])) {
            $tracking_url = str_replace('{tracking_number}', $order['tracking_number'], $order['tracking_url_template']);
        }

        return [
            'id' => intval($order['id']),
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'status' => $order['status'],
            'shipped_date' => $order['shipped_date'],
            'tracking_number' => $order['tracking_number'],
            'tracking_url' => $tracking_url,
            'carrier_name' => $order['carrier_name'],
            'shipping_company_id' => intval($order['shipping_company_id']),
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at']
        ];
    }, $orders_result);
    
    // Format the response
    $response_data = [
        'orders' => $formatted_orders,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_orders' => $total_orders,
            'per_page' => $limit,
            'has_next' => $page < $total_pages,
            'has_prev' => $page > 1
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $response_data
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Error in get_recent_ordersApi.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching recent orders'
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
}
?>