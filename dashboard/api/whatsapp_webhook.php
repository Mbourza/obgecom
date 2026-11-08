<?php //whatsapp_webhook.php
if(file_exists(stream_resolve_include_path("../config/init.php"))) {
    require_once("../config/init.php");
}

if(file_exists(stream_resolve_include_path("../services/WhatsAppService.php"))) {
    require_once("../services/WhatsAppService.php");
}

header('Content-Type: application/json');

// Verification endpoint for Meta/WhatsApp
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verify_token = defined('WHATSAPP_VERIFY_TOKEN') ? WHATSAPP_VERIFY_TOKEN : 'your_verify_token_here';
    
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    if ($mode === 'subscribe' && $token === $verify_token) {
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo json_encode(['error' => 'Verification failed']);
    exit;
}

// Handle incoming messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }
    
    $db = DB::getInstance();
    
    // Process webhook
    $whatsapp = new WhatsAppService($db);
    $result = $whatsapp->processIncomingMessage($input);
    
    if ($result && isset($result['success']) && $result['success']) {
        // Log successful processing
        error_log("WhatsApp webhook processed: Order #{$result['order_id']} - Response: {$result['response']}");
        echo json_encode(['success' => true, 'processed' => true]);
    } else {
        // No matching order found
        echo json_encode(['success' => true, 'processed' => false, 'message' => 'No matching order found']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);