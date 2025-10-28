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

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

try {
    $order_id = intval($_GET['id']);
    
    // Validate order ID
    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }
    
    // Get order details
    $order_query = "
        SELECT 
            o.id,
            o.store_id,
            o.user_id,
            o.external_order_id,
            o.order_number,
            o.customer_name,
            o.customer_email,
            o.customer_phone,
            o.customer_ville,
            o.shipping_address,
            o.billing_address,
            o.shipping_cost,
            o.total_amount,
            o.discount_amount,
            o.currency,
            o.status,
            o.shipping_status,
            o.payment_status,
            o.shipping_method,
            o.tracking_number,
            sc.name as carrier_name,
            sp.dispatched_at as shipped_date,
            o.weight,
            o.length,
            o.width,
            o.height,
            o.order_date,
            o.order_note,
            o.platform,
            o.shipping_company_id,
            o.confirmed_by_agent,
            o.handled_at,
            o.updated_at,
            o.created_at
        FROM orders o
        LEFT JOIN shipping_process sp ON sp.order_id = o.id
        LEFT JOIN shipping_companies sc ON sc.id = o.shipping_company_id
        WHERE o.id = ?
    ";

    
    $order_result = $db->getThisQuery($order_query, [$order_id]);
    
    if (empty($order_result)) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    $order = $order_result[0];
    
    // Format order data
    $formatted_order = [
        'id' => intval($order['id']),
        'store_id' => intval($order['store_id']),
        'user_id' => intval($order['user_id']),
        'external_order_id' => $order['external_order_id'],
        'order_number' => $order['order_number'],
        'customer_name' => $order['customer_name'],
        'customer_email' => $order['customer_email'],
        'customer_phone' => $order['customer_phone'],
        'customer_ville' => $order['customer_ville'],
        'shipping_address' => $order['shipping_address'],
        'billing_address' => $order['billing_address'],
        'shipping_cost' => floatval($order['shipping_cost']),
        'total_amount' => floatval($order['total_amount']),
        'discount_amount' => floatval($order['discount_amount']),
        'currency' => $order['currency'],
        'status' => $order['status'],
        'shipping_status' => $order['shipping_status'],
        'payment_status' => $order['payment_status'],
        'shipping_method' => $order['shipping_method'],
        'tracking_number' => $order['tracking_number'],
        'carrier_name' => $order['carrier_name'],
        'shipped_date' => $order['shipped_date'],
        'weight' => floatval($order['weight']),
        'length' => floatval($order['length']),
        'width' => floatval($order['width']),
        'height' => floatval($order['height']),
        'order_date' => $order['order_date'],
        'order_note' => $order['order_note'],
        'platform' => $order['platform'],
        'shipping_company_id' => $order['shipping_company_id'] ? intval($order['shipping_company_id']) : null,
        'confirmed_by_agent' => $order['confirmed_by_agent'] ? intval($order['confirmed_by_agent']) : null,
        'handled_at' => $order['handled_at'],
        'created_at' => $order['created_at'],
        'updated_at' => $order['updated_at']
    ];
    
    // Optionally get order items if you have an order_items table
    $items_query = "
        SELECT 
            oi.id,
            oi.product_name,
            oi.product_sku,
            oi.quantity,
            oi.unit_price,
            oi.total_price
        FROM order_items oi 
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ";
    
    $items_result = $db->getThisQuery($items_query, [$order_id]);
    
    // Format items data
    $items = array_map(function($item) {
        return [
            'id' => intval($item['id']),
            'product_name' => $item['product_name'],
            'product_sku' => $item['product_sku'],
            'quantity' => intval($item['quantity']),
            'unit_price' => floatval($item['unit_price']),
            'total_price' => floatval($item['total_price'])
        ];
    }, $items_result);
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_order,
        'items' => $items
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