<?php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get search query
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit;
}

try {
    
    // Prepare search query
    $searchTerm = '%' . $query . '%';
    $sql = "SELECT 
                id, name, sku, price, stock_quantity, status, images, regular_price, sale_price, compare_price
            FROM products
            WHERE 
                (name LIKE ? OR sku LIKE ? OR description LIKE ?)
                AND status != 'draft'
            ORDER BY 
                CASE 
                    WHEN name LIKE ? THEN 0
                    WHEN sku LIKE ? THEN 1
                    ELSE 2
                END,
                name ASC
            LIMIT 20";
    
    $params = [
        $searchTerm, $searchTerm, $searchTerm,
        $searchTerm, $searchTerm
    ];
    
    // Execute query
    $products = $db->getThisQuery($sql, $params);
    
    echo json_encode([
        'success' => true,
        'products' => $products ?: []
    ]);
    
} catch (Exception $e) {
    error_log("Error in search_products.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching'
    ]);
}
?>