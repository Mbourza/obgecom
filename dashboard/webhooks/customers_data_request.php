<?php
require_once('./config_shopify.php');

$body        = file_get_contents('php://input');
$shopifyHmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

$calculatedHmac = base64_encode(
    hash_hmac('sha256', $body, SHOPIFY_CLIENT_SECRET, true)
);

if ($shopifyHmac === '' || !hash_equals($calculatedHmac, $shopifyHmac)) {
    http_response_code(401);
    exit('Unauthorized');
}

// Webhook is valid
// TODO: handle the request here

http_response_code(200);
echo 'OK';