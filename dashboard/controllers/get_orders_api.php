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

// Check if action is provided
if (!isset($_POST['action']) || $_POST['action'] !== 'get_orders') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    // Get logged in user id
    $user_id = getCurrentUserId($db);
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        exit;
    }

    // Get filter parameters
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';
    $date_range = isset($_POST['date_range']) ? trim($_POST['date_range']) : '';
    $min_amount = isset($_POST['min_amount']) ? floatval($_POST['min_amount']) : 0;
    $max_amount = isset($_POST['max_amount']) ? floatval($_POST['max_amount']) : 0;
    $search = isset($_POST['search']) ? trim($_POST['search']) : '';
    
    // Build the WHERE clause
    $where_conditions = ["user_id = ?"]; // restrict by user_id
    $params = [$user_id];
    
    // Status filter
    if (!empty($status) && $status !== 'all') {
        $where_conditions[] = "status = ?";
        $params[] = $status;
    }
    
    // Date range filter
    if (!empty($date_range)) {
        switch ($date_range) {
            case 'today':
                $where_conditions[] = "DATE(order_date) = CURDATE()";
                break;
            case 'yesterday':
                $where_conditions[] = "DATE(order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'this_week':
                $where_conditions[] = "YEARWEEK(order_date, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'last_week':
                $where_conditions[] = "YEARWEEK(order_date, 1) = YEARWEEK(CURDATE() - INTERVAL 1 WEEK, 1)";
                break;
            case 'this_month':
                $where_conditions[] = "YEAR(order_date) = YEAR(CURDATE()) AND MONTH(order_date) = MONTH(CURDATE())";
                break;
            case 'last_month':
                $where_conditions[] = "YEAR(order_date) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(order_date) = MONTH(CURDATE() - INTERVAL 1 MONTH)";
                break;
            case 'last_30_days':
                $where_conditions[] = "order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
    }
    
    // Amount range filter
    if ($min_amount > 0) {
        $where_conditions[] = "total_amount >= ?";
        $params[] = $min_amount;
    }
    
    if ($max_amount > 0) {
        $where_conditions[] = "total_amount <= ?";
        $params[] = $max_amount;
    }
    
    // Search filter
    if (!empty($search)) {
        $where_conditions[] = "(order_number LIKE ? OR customer_name LIKE ? OR customer_email LIKE ? OR external_order_id LIKE ?)";
        $search_param = '%' . $search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Build the complete WHERE clause
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Get orders with filters
    $orders_query = "
        SELECT 
            id,
            store_id,
            user_id,
            external_order_id,
            order_number,
            customer_name,
            customer_email,
            customer_phone,
            total_amount,
            currency,
            status,
            payment_status,
            shipping_method,
            tracking_number,
            order_date,
            platform,
            created_at,
            updated_at
        FROM orders 
        $where_clause
        ORDER BY order_date DESC, id DESC
        LIMIT 1000
    ";
    
    $orders_result = $db->getThisQuery($orders_query, $params);
    
    // Format orders data
    $orders = array_map(function($order) {
        return [
            'id' => intval($order['id']),
            'store_id' => intval($order['store_id']),
            'user_id' => intval($order['user_id']),
            'external_order_id' => $order['external_order_id'],
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['customer_phone'],
            'total_amount' => floatval($order['total_amount']),
            'currency' => $order['currency'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'shipping_method' => $order['shipping_method'],
            'tracking_number' => $order['tracking_number'],
            'order_date' => $order['order_date'],
            'platform' => $order['platform'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at']
        ];
    }, $orders_result);
    
    // Get statistics (also scoped by user_id)
    $stats_query = "
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(AVG(total_amount), 0) as average_order_value,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
            COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped_orders,
            COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders
        FROM orders 
        $where_clause
    ";
    
    $stats_result = $db->getThisQuery($stats_query, $params);
    $stats = $stats_result[0];
    
    // Format stats
    $formatted_stats = [
        'total_orders' => intval($stats['total_orders']),
        'total_revenue' => floatval($stats['total_revenue']),
        'average_order_value' => floatval($stats['average_order_value']),
        'pending_orders' => intval($stats['pending_orders']),
        'processing_orders' => intval($stats['processing_orders']),
        'shipped_orders' => intval($stats['shipped_orders']),
        'delivered_orders' => intval($stats['delivered_orders']),
        'cancelled_orders' => intval($stats['cancelled_orders'])
    ];
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $formatted_stats,
        'total_count' => count($orders)
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in get_orders_api.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching orders'
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
