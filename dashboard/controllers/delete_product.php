<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate product ID
if (!isset($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

$db = DB::getInstance();

$productId = (int) $_POST['product_id'];

try {
    
    // Start transaction
    $db->beginTransaction();
    
    // Then delete the product
    $result = $db->query("DELETE FROM products WHERE id = ?", [$productId]);
    
    if ($result) {

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Product not found or already deleted']);
    }
    
} catch (Exception $e) {

    $db->rollBack();
    error_log("Error deleting product: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting the product']);
}
?>