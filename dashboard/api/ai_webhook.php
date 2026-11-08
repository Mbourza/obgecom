<?php //ai_webhook.php
if(file_exists(stream_resolve_include_path("../config/init.php"))) {
    require_once("../config/init.php");
}

if(file_exists(stream_resolve_include_path("../services/WhatsAppService.php"))) {
    require_once("../services/WhatsAppService.php");
}

header('Content-Type: application/json');

// 360dialog webhook - no verification token needed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw input
    $input = file_get_contents('php://input');
    $payload = json_decode($input, true);
    
    if (!$payload) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }
    
    // Log incoming webhook
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    file_put_contents($log_dir . '/whatsapp_webhook.log', 
        date('Y-m-d H:i:s') . " - Webhook received\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n", 
        FILE_APPEND
    );
    
    $db = DB::getInstance();
    
    // Process webhook
    $whatsapp = new WhatsAppService($db);
    $result = $whatsapp->processIncomingMessage($payload);
    
    // Always return 200 OK to acknowledge receipt
    echo json_encode(['success' => true, 'processed' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);