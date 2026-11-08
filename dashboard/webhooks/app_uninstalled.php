<?php
// dashboard/webhooks/app_uninstalled.php
require_once('./config_shopify.php');
// 1. Raw body (needed for the HMAC — never re-encode parsed JSON)
$body = file_get_contents('php://input');

// 2. Headers
$hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
$shop        = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? '';
$topic       = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '';

// 3. Verify HMAC FIRST, before anything else
$calculated_hmac = base64_encode(hash_hmac('sha256', $body, SHOPIFY_CLIENT_SECRET, true));

if ($hmac_header === '' || !hash_equals($calculated_hmac, $hmac_header)) {
    http_response_code(401);
    exit('Unauthorized');
}

// 4. Only verified requests reach this point
$data = json_decode($body, true);

// Log (sanitized: don't dump the full body in production)
file_put_contents(
    __DIR__ . '/webhook_log.txt',
    date('Y-m-d H:i:s') . " - shop: $shop - topic: $topic" . PHP_EOL,
    FILE_APPEND
);

// TODO: Handle uninstall logic here, e.g. remove the shop's access token / mark the shop as uninstalled

http_response_code(200);
echo 'OK'; ?>