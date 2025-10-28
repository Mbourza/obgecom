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

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$order_id = intval($_GET['order_id']);

try {
    // Get order details
    $order_query = "
        SELECT 
            id,
            store_id,
            user_id,
            external_order_id,
            order_number,
            customer_name,
            customer_email,
            customer_phone,
            shipping_address,
            billing_address,
            total_amount,
            currency,
            status,
            payment_status,
            shipping_method,
            tracking_number,
            weight,
            length,
            width,
            height,
            order_date,
            platform,
            created_at,
            updated_at
        FROM orders 
        WHERE id = ?
    ";
    
    $order_result = $db->getThisQuery($order_query, [$order_id]);
    
    if (empty($order_result)) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $order = $order_result[0];
    
    // Get order items
    $items_query = "
        SELECT 
            id,
            order_id,
            product_name,
            product_sku,
            quantity,
            unit_price,
            total_price,
            weight,
            created_at
        FROM order_items 
        WHERE order_id = ?
        ORDER BY id ASC
    ";
    
    $items_result = $db->getThisQuery($items_query, [$order_id]);
    
    // Calculate totals
    $total_items = count($items_result);
    $total_quantity = 0;
    $subtotal = 0;
    
    foreach ($items_result as $item) {
        $total_quantity += intval($item['quantity']);
        $subtotal += floatval($item['total_price']);
    }
    
    // Format the response
    $response_data = [
        'order' => [
            'id' => intval($order['id']),
            'store_id' => intval($order['store_id']),
            'user_id' => intval($order['user_id']),
            'external_order_id' => $order['external_order_id'],
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['customer_phone'],
            'shipping_address' => $order['shipping_address'],
            'billing_address' => $order['billing_address'],
            'total_amount' => floatval($order['total_amount']),
            'currency' => $order['currency'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'shipping_method' => $order['shipping_method'],
            'tracking_number' => $order['tracking_number'],
            'weight' => floatval($order['weight']),
            'length' => floatval($order['length']),
            'width' => floatval($order['width']),
            'height' => floatval($order['height']),
            'order_date' => $order['order_date'],
            'platform' => $order['platform'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at']
        ],
        'items' => array_map(function($item) {
            return [
                'id' => intval($item['id']),
                'order_id' => intval($item['order_id']),
                'product_name' => $item['product_name'],
                'product_sku' => $item['product_sku'],
                'quantity' => intval($item['quantity']),
                'unit_price' => floatval($item['unit_price']),
                'total_price' => floatval($item['total_price']),
                'weight' => floatval($item['weight']),
                'created_at' => $item['created_at']
            ];
        }, $items_result),
        'summary' => [
            'total_items' => $total_items,
            'total_quantity' => $total_quantity,
            'subtotal' => $subtotal
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $response_data
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in get_order_details.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching order details'
    ]);
}
?>