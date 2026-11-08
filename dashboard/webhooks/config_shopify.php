<?php
// dashboard/webhooks/config_shopify.php
// Shared Shopify config for all webhook endpoints.
// Outputs nothing if opened directly in a browser.

// Client secret: Partner Dashboard > Your app > Client credentials > Client secret
// (NOT the API key / Client ID, NOT an access token)
if (!defined('SHOPIFY_CLIENT_SECRET')) {
    define('SHOPIFY_CLIENT_SECRET', getenv('SHOPIFY_CLIENT_SECRET') ?: '');
}

// Shared helper: verifies the HMAC and returns the raw body, or exits with 401.
if (!function_exists('verify_shopify_webhook')) {
    function verify_shopify_webhook(): string {
        $rawBody     = file_get_contents('php://input'); // raw body, never re-encoded JSON
        $shopifyHmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

        $calculated = base64_encode(
            hash_hmac('sha256', $rawBody, SHOPIFY_CLIENT_SECRET, true)
        );

        if ($shopifyHmac === '' || !hash_equals($calculated, $shopifyHmac)) {
            http_response_code(401);
            exit('Unauthorized');
        }

        return $rawBody;
    }
} ?>