<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$storeName = $input['store_name'] ?? null;
$domain = $input['domain'] ?? null;

if (!$storeName || !$domain) {
    echo json_encode([
        'success' => false, 
        'message' => 'Nom et domaine requis'
    ]);
    exit;
}

// Normalize YouCan domain
$domain = normalizeYouCanDomain($domain);

// Generate state token for CSRF protection
$state = bin2hex(random_bytes(16));
$_SESSION['youcan_oauth_state'] = $state;
$_SESSION['youcan_store_name'] = $storeName;
$_SESSION['youcan_store_domain'] = $domain;

// Log OAuth initiation
error_log("YouCan OAuth initiated - Store: $storeName, Domain: $domain, State: $state");

// YouCan OAuth configuration
$client_id = "2310";
$redirect_uri = "https://obgecom.com/dashboard/controllers/youcan_callback.php";

// Build authorization URL with correct YouCan format
$scopes = "read-orders edit-orders delete-orders read-products edit-products delete-products read-customers edit-customers delete-customers read-categories edit-categories";

$auth_url = "https://seller-area.youcan.shop/admin/oauth/authorize?"
    . "client_id=" . urlencode($client_id)
    . "&response_type=code"
    . "&scope=" . urlencode($scopes)
    . "&redirect_uri=" . urlencode($redirect_uri)
    . "&state=" . urlencode($state);

echo json_encode([
    'success' => true, 
    'auth_url' => $auth_url
]);

function normalizeYouCanDomain($domain) {

    $domain = trim($domain);

    // remove http / https
    $domain = preg_replace('/^https?:\/\//', '', $domain);

    // remove trailing slash
    $domain = rtrim($domain, '/');

    // If user entered only store name
    if (!str_contains($domain, '.')) {
        $domain = $domain . '.youcan.store';
    }

    return strtolower($domain);
}
?>