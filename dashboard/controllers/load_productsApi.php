<?php
require_once '../core/init.php';

$db = DB::getInstance();

// Get user from session
$user = $db->getThisQuery(
    "SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1", 
    [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]
);

if (!$user || !isset($user[0]['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Utilisateur non trouvé']);
    exit;
}

$user_id = (int)$user[0]['id'];

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 12;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Calculate offset
$offset = ($page - 1) * $per_page;

// Build WHERE clause - ADD USER ID FILTER
$where_conditions = ["user_id = ?"];
$params = [$user_id];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR sku LIKE ? OR barcode LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status)) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM products $where_clause";
$total_result = $db->getThisQuery($count_sql, $params);
$total_products = $total_result[0]['total'] ?? 0;
$total_pages = ceil($total_products / $per_page);

// Get products with pagination
$products_sql = "SELECT * FROM products $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$products = $db->getThisQuery($products_sql, $params);

// Format response
$response = [
    'success' => true,
    'products' => $products,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $per_page,
        'total_products' => $total_products,
        'total_pages' => $total_pages
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
?>