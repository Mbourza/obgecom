<?php
// dashboard/webhooks/compliance.php - Shopify mandatory compliance webhooks

require_once('./config_shopify.php');

// Raw body, before any other processing
$raw_body    = file_get_contents('php://input');
$hmac_header = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
$topic       = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '';
$shop_domain = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? '';

// Verify HMAC first
$calculated = base64_encode(hash_hmac('sha256', $raw_body, SHOPIFY_CLIENT_SECRET, true));
if ($hmac_header === '' || !hash_equals($calculated, $hmac_header)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid HMAC signature']);
    exit;
}

$payload = json_decode($raw_body, true) ?: [];

// Log without personal data
file_put_contents(
    __DIR__ . '/compliance.log',
    date('Y-m-d H:i:s') . " | Topic: $topic | Shop: $shop_domain\n",
    FILE_APPEND
);

switch ($topic) {
    case 'customers/data_request':
        handleCustomerDataRequest($payload);
        break;
    case 'customers/redact':
        handleCustomerRedact($payload);
        break;
    case 'shop/redact':
        handleShopRedact($payload);
        break;
    default:
        // Unknown topic: still acknowledge
        break;
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'received']);
exit;

function handleCustomerDataRequest(array $payload): void {
    $customer_id = $payload['customer']['id'] ?? null;
    $shop_id     = $payload['shop_id'] ?? null;
    // TODO: collect the customer's data from your DB and send it to the store owner
}

function handleCustomerRedact(array $payload): void {
    $customer_id = $payload['customer']['id'] ?? null;
    $shop_id     = $payload['shop_id'] ?? null;
    // TODO: delete the customer's personal data from your DB
}

function handleShopRedact(array $payload): void {
    $shop_id = $payload['shop_id'] ?? null;
    // TODO: delete all data for this shop from your DB
} ?>