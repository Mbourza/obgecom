<?php
// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Basic validation
if (!isset($data['api_url'], $data['consumer_key'], $data['consumer_secret'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Build API request
$url = rtrim($data['api_url'], '/') . '/products?per_page=1';

// Setup cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $data['consumer_key'] . ":" . $data['consumer_secret']);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Handle result
if ($http_code === 200 && $response) {
    echo json_encode(['success' => true, 'data' => json_decode($response, true)]);
} else {
    http_response_code($http_code ?: 500);
    echo json_encode(['success' => false, 'message' => $error ?: 'Invalid WooCommerce API credentials']);
} ?>