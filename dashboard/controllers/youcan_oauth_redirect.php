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
$client_id = "1757";
$redirect_uri = "https://obgecom.com/dashboard/controllers/youcan_callback.php";

// Build authorization URL with correct YouCan format
// Use scope[]=* to request all permissions (YouCan standard)
$auth_url = "https://seller-area.youcan.shop/admin/oauth/authorize?"
    . "client_id=" . urlencode($client_id)
    . "&response_type=code"
    . "&scope[]=*"
    . "&redirect_uri=" . urlencode($redirect_uri)
    . "&state=" . urlencode($state);

echo json_encode([
    'success' => true, 
    'auth_url' => $auth_url
]);

function normalizeYouCanDomain($domain) {
    $domain = trim($domain);
    
    // Remove protocol if present
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    
    // Remove trailing slashes
    $domain = rtrim($domain, '/');
    
    // For YouCan, domain should be like "store-name.youcan.shop"
    if (!preg_match('/\.youcan\.shop$/i', $domain)) {
        // If not a complete YouCan domain, assume it's the subdomain
        $domain = $domain . '.youcan.shop';
    }
    
    return strtolower($domain);
}
?>