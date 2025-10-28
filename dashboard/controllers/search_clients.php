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

// Check if query is provided
if (!isset($_GET['query']) || empty(trim($_GET['query']))) {
    echo json_encode(['success' => false, 'message' => 'Search query is required']);
    exit;
}

try {
    $query = trim($_GET['query']);
    
    // Validate query length
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Query must be at least 2 characters long']);
        exit;
    }
    
    // Search for clients in orders table
    // Group by customer details to get unique clients
    $search_query = "
        SELECT 
            MIN(id) as order_id,
            customer_name,
            customer_email,
            customer_phone,
            customer_ville as city,
            shipping_address,
            billing_address,
            COUNT(*) as total_orders,
            MAX(order_date) as last_order_date,
            SUM(total_amount) as total_spent,
            currency
        FROM orders 
        WHERE 
            (customer_name LIKE ? OR 
             customer_email LIKE ? OR 
             customer_phone LIKE ? OR
             customer_ville LIKE ?)
            AND customer_name IS NOT NULL 
            AND customer_name != ''
        GROUP BY 
            LOWER(customer_name), 
            LOWER(customer_email), 
            customer_phone
        ORDER BY 
            last_order_date DESC,
            total_orders DESC
        LIMIT 20
    ";
    
    $search_param = '%' . $query . '%';
    $clients_result = $db->getThisQuery($search_query, [
        $search_param, 
        $search_param, 
        $search_param, 
        $search_param
    ]);
    
    if (empty($clients_result)) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'No clients found'
        ]);
        exit;
    }
    
    // Format client data
    $clients = array_map(function($client) {
        return [
            'id' => intval($client['order_id']), // Using first order ID as client identifier
            'name' => $client['customer_name'],
            'email' => $client['customer_email'],
            'phone' => $client['customer_phone'],
            'city' => $client['city'],
            'address' => $client['shipping_address'] ?: $client['billing_address'],
            'total_orders' => intval($client['total_orders']),
            'last_order_date' => $client['last_order_date'],
            'total_spent' => floatval($client['total_spent']),
            'currency' => $client['currency']
        ];
    }, $clients_result);
    
    echo json_encode([
        'success' => true,
        'data' => $clients,
        'count' => count($clients)
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in search_clients.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching for clients'
    ]);
}
?>