<?php
require_once("../core/init.php");

$db = DB::getInstance();

if (isset($_GET['city'])) {
    $city = trim($_GET['city']);
    
    try {
        // Check if we have a shipping cost for this city
        $shipping = $db->get('shipping_costs', ['city', '=', $city])->first();
        
        if ($shipping) {
            echo json_encode(['success' => true, 'cost' => $shipping->cost]);
        } else {
            // Return default shipping cost or 0 if not found
            echo json_encode(['success' => true, 'cost' => 0]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'City parameter missing']);
}