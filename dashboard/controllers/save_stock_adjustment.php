<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Get input data
$item_type = $_POST['item_type'] ?? '';
$product_id = (int)($_POST['product_id'] ?? 0);
$variation_id = (int)($_POST['variation_id'] ?? 0);
$adjustment_type = $_POST['adjustment_type'] ?? 'adjustment';
$quantity = (int)($_POST['quantity'] ?? 0);
$reason = $_POST['reason'] ?? '';
$user_id = (int)($_POST['user_id'] ?? 0);

// Validate
if (!$product_id || !$quantity || !$reason || !$user_id) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

// Get current stock
if ($item_type === 'product') {
    $current = $db->getThisQuery("SELECT stock_quantity FROM products WHERE id = ?", [$product_id]);
    $current_stock = $current[0]['stock_quantity'] ?? 0;
} else if ($item_type === 'variant') {
    $current = $db->getThisQuery("SELECT stock_quantity FROM product_variations WHERE id = ?", [$variation_id]);
    $current_stock = $current[0]['stock_quantity'] ?? 0;
} else {
    echo json_encode(['success' => false, 'message' => 'Type d\'élément invalide']);
    exit;
}

// Calculate new stock
$new_stock = $current_stock;
if ($adjustment_type === 'in') {
    $new_stock = $current_stock + $quantity;
} else if ($adjustment_type === 'out') {
    $new_stock = max(0, $current_stock - $quantity);
} else {
    // Adjustment - set to exact quantity
    $new_stock = max(0, $quantity);
}

// Update stock
if ($item_type === 'product') {
    $db->query("UPDATE products SET stock_quantity = ? WHERE id = ?", [$new_stock, $product_id]);
} else if ($item_type === 'variant') {
    $db->query("UPDATE product_variations SET stock_quantity = ? WHERE id = ?", [$new_stock, $variation_id]);
}

// Record movement
$db->query("
    INSERT INTO stock_movements 
    (product_id, variation_id, movement_type, quantity, previous_quantity, new_quantity, reason, user_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
", [
    $product_id,
    $variation_id > 0 ? $variation_id : null,
    $adjustment_type,
    $quantity,
    $current_stock,
    $new_stock,
    $reason,
    $user_id
]);

echo json_encode([
    'success' => true,
    'message' => 'Stock mis à jour avec succès'
]); ?>