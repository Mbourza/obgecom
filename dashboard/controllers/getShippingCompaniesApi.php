<?php
require_once '../core/init.php';
$db = DB::getInstance();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    // Check if user_id is provided
    if (!isset($_GET['user_id']) || empty(trim($_GET['user_id']))) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'L\'ID utilisateur est requis']);
        exit;
    }

    $userId = intval($_GET['user_id']);
    // Get default company from shipping_settings
    $defaultQuery = "SELECT shipping_company_id FROM shipping_settings WHERE user_id = ? LIMIT 1";
    $defaultResult = $db->getThisQuery($defaultQuery, [$userId]);
    $defaultCompanyId = $defaultResult && count($defaultResult) > 0 ? $defaultResult[0]['shipping_company_id'] : null;

    // Get all shipping companies for user
    $query = "SELECT id, name, logo_url, phone, supports_tracking, is_active 
              FROM shipping_companies WHERE user_id = ?";
    $companies = $db->getThisQuery($query, [$userId]);

    // Mark default company
    foreach ($companies as &$company) {
        $company['is_default'] = ($company['id'] == $defaultCompanyId);
    }

    echo json_encode([
        'success' => true,
        'companies' => $companies
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur : ' . $e->getMessage()
    ]);
}
