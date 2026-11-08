<?php
session_start();
header('Content-Type: application/json');
require_once('./config/e_config.php');

$input = json_decode(file_get_contents('php://input'), true);
$storeName = $input['store_name'] ?? null;
$domain = $input['domain'] ?? null;

if (!$storeName || !$domain) {
    echo json_encode(['success'=>false, 'message'=>'Nom et domaine requis']);
    exit;
}

// Validate Shopify domain strictly
if (!preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/i', $domain)) {
    echo json_encode([
        'success' => false,
        'message' => 'Domaine Shopify invalide. Utilisez nom-boutique.myshopify.com'
    ]);
    exit;
}

// Generate a state token for CSRF protection
$state = bin2hex(random_bytes(12));
$_SESSION['oauth_state'] = $state;
$_SESSION['store_name'] = $storeName;
$_SESSION['store_domain'] = $domain;

// Shopify OAuth URL
$client_id = SHOPIFY_CLIENT_ID;
$scopes = 'read_products,read_orders';
$redirect_uri = "https://obgecom.com/dashboard/controllers/shopify_callback.php";

$install_url = "https://$domain/admin/oauth/authorize?"
    . "client_id=" . $client_id
    . "&scope=" . urlencode($scopes)
    . "&redirect_uri=" . urlencode($redirect_uri)
    . "&state=" . $state;

echo json_encode(['success'=>true, 'install_url'=>$install_url]);
?>