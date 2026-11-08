<?php
function verify_shopify_webhook(): string {
    $secret = ''; // Partner Dashboard > App > Client secret (better: load from env/config)
    $hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
    $rawBody = file_get_contents('php://input'); // raw body, never json_encode($decoded)

    $calculated = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

    if ($hmacHeader === '' || !hash_equals($calculated, $hmacHeader)) {
        http_response_code(401);
        exit('Unauthorized');
    }
    
    return $rawBody;
}