<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

$item_type = $_GET['item_type'] ?? '';
$item_id = (int)($_GET['item_id'] ?? 0);

if ($item_type === 'product') {
    $result = $db->getThisQuery("SELECT stock_quantity FROM products WHERE id = ?", [$item_id]);
} else if ($item_type === 'variant') {
    $result = $db->getThisQuery("SELECT stock_quantity FROM product_variations WHERE id = ?", [$item_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid item type']);
    exit;
}

if (!empty($result)) {
    echo json_encode([
        'success' => true,
        'data' => [
            'current_stock' => (int)($result[0]['stock_quantity'] ?? 0)
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
} ?>