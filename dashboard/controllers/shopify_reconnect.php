<?php
// controllers/shopify_reconnect.php - Handle Shopify reconnection

if (file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once('./config/e_config.php');

$db = DB::getInstance();

// Get current user
$user_id = getCurrentUserId($db);

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$store_id = $input['store_id'] ?? 0;
$domain = $input['domain'] ?? '';

if (!$store_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de boutique requis']);
    exit;
}

// Verify store belongs to user and is Shopify
$store = $db->getThisQuery(
    "SELECT * FROM stores WHERE id = ? AND user_id = ? AND platform = 'shopify'",
    [$store_id, $user_id]
);

if (empty($store)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Boutique Shopify non trouvée']);
    exit;
}

$store = $store[0];
$domain = $domain ?: $store['domain'];
$domain = preg_replace('#^https?://#', '', rtrim($domain, '/'));

// Get Shopify app credentials from database
$client_id = getShopifyClientId();
$client_secret = getShopifyClientSecret();

if (empty($client_id) || empty($client_secret)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Configuration Shopify manquante. Veuillez contacter l\'administrateur.'
    ]);
    exit;
}

// Build OAuth URL
$scopes = 'read_orders,read_products,read_inventory,write_products,write_inventory';
$redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . '/shopify/callback.php';

$install_url = "https://{$domain}/admin/oauth/authorize?" .
    "client_id={$client_id}" .
    "&scope=" . urlencode($scopes) .
    "&redirect_uri=" . urlencode($redirect_uri) .
    "&state={$store_id}" .
    "&grant_options[]=offline";

// Mark store as disconnected and clear old tokens
$db->update('stores', $store_id, [
    'is_connected' => 0,
    'connection_error' => 'En attente de reconnexion...',
    'updated_at' => date('Y-m-d H:i:s')
]);

// Delete old tokens
$db->query(
    "DELETE FROM shopify_tokens WHERE store_id = ? AND user_id = ?",
    [$store_id, $user_id]
);

echo json_encode([
    'success' => true,
    'install_url' => $install_url,
    'message' => 'Redirection vers Shopify pour reconnexion...'
]);

/**
 * Get Shopify Client ID from configuration
 */
function getShopifyClientId() {
    global $db;
    
    // Check if defined as constant
    if (defined('SHOPIFY_CLIENT_ID')) {
        return SHOPIFY_CLIENT_ID;
    }
    
    // Load from database settings
    $setting = $db->getThisQuery(
        "SELECT setting_value FROM system_settings 
         WHERE setting_key = 'shopify_client_id' LIMIT 1"
    );
    
    return !empty($setting) ? $setting[0]['setting_value'] : '';
}

/**
 * Get Shopify Client Secret from configuration
 */
function getShopifyClientSecret() {
    global $db;
    
    // Check if defined as constant
    if (defined('SHOPIFY_CLIENT_SECRET')) {
        return SHOPIFY_CLIENT_SECRET;
    }
    
    // Load from database settings
    $setting = $db->getThisQuery(
        "SELECT setting_value FROM system_settings 
         WHERE setting_key = 'shopify_client_secret' LIMIT 1"
    );
    
    return !empty($setting) ? $setting[0]['setting_value'] : '';
}
?>