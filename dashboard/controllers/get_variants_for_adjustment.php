<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$variants = $db->getThisQuery("
    SELECT pv.id, pv.sku, pv.attributes, pv.stock_quantity, p.name as product_name
    FROM product_variations pv
    LEFT JOIN products p ON pv.product_id = p.id
    WHERE p.user_id = ?
    ORDER BY p.name, pv.sku
", [$user_id]);

echo json_encode([
    'success' => true,
    'data' => $variants
]); ?>