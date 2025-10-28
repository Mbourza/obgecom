<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
$db = DB::getInstance();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate required fields
if (empty($_POST['id']) || empty($_POST['name']) || !isset($_POST['price'])) {
    echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
    exit;
}

$productId = (int) $_POST['id'];

try {

    // Prepare update data
    $updateData = [

        'name' => $_POST['name'],
        'sku' => $_POST['sku'] ?? null,
        'price' => (float)$_POST['price'],
        'compare_price' => isset($_POST['compare_price']) ? (float)$_POST['compare_price'] : null,
        'cost_price' => isset($_POST['cost_price']) ? (float)$_POST['cost_price'] : null,
        'stock_quantity' => isset($_POST['quantity']) ? (int)$_POST['quantity'] : null,
        'status' => $_POST['status'] ?? 'active',
        'featured' => isset($_POST['featured']) ? 1 : 0,
        'description' => $_POST['description'] ?? null,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Update product in database
    $result = $db->update('products', $productId, $updateData);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or product not found']);
    }
    
} catch (Exception $e) {

    error_log("Error updating product: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the product']);
}
?>