<?php
require_once("../core/init.php");

$db = DB::getInstance();

if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    
    try {
        // Get order details
        $order = $db->get('orders', ['id', '=', $order_id])->first();
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Commande non trouvée']);
            exit;
        }
        
        // Get order items
        $items = $db->getThisQuery("
            SELECT * 
            FROM order_items
            WHERE order_id = ?
        ", [$order_id]);

        // Format response
        $response = [
            'success' => true,
            'order' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'customer_email' => $order->customer_email,
                'shipping_address' => $order->shipping_address,
                'shipping_city' => $order->customer_ville,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'discount_amount' => $order->discount_amount,
                'notes' => $order->order_note,
                'items' => array_map(function($item) {
                    return [
                        'product_id' => $item['id'],
                        'name' => $item['product_name'],
                        'sku' => $item['product_sku'],
                        'price' => $item['unit_price'],
                        'quantity' => $item['quantity']
                    ];
                }, $items)
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID de commande manquant']);
} ?>