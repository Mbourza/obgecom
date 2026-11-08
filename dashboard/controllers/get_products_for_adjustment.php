<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$products = $db->getThisQuery("
    SELECT id, name, sku, stock_quantity 
    FROM products 
    WHERE user_id = ? 
    AND status = 'active'
    ORDER BY name
", [$user_id]);

echo json_encode([
    'success' => true,
    'data' => $products
]); ?>