<?php
// webhook/whatsapp_webhook.php

require_once(__DIR__ . '/../core/init.php');
require_once(__DIR__ . '/../classes/AIConfirmationService.php');

$db = DB::getInstance();

// Verify webhook signature (if needed)
// $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
// verifySignature($signature);

// Get incoming webhook data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    exit;
}

// Handle WhatsApp message
if (isset($data['messages'])) {
    foreach ($data['messages'] as $message) {
        // Check if it's a text message
        if (isset($message['text']) && isset($message['from'])) {
            $phoneNumber = $message['from'];
            $responseText = $message['text']['body'];
            
            // Find order associated with this phone number
            $order = $db->getThisQuery(
                "SELECT o.id, o.user_id 
                 FROM orders o 
                 JOIN customers c ON o.customer_id = c.id 
                 WHERE c.phone LIKE ? 
                 AND o.status IN ('new-order', 'pending')
                 AND o.ai_confirmed = 0
                 ORDER BY o.created_at DESC 
                 LIMIT 1",
                ['%' . $phoneNumber]
            );
            
            if (!empty($order)) {
                $orderId = $order[0]['id'];
                $userId = $order[0]['user_id'];
                
                // Process AI response
                $aiService = new AIConfirmationService($db, $userId);
                $result = $aiService->processAIResponse($orderId, $responseText, 'whatsapp');
                
                // Log webhook response
                error_log("WhatsApp Webhook: Order #{$orderId} - Response: {$responseText} - Result: " . json_encode($result));
            }
        }
    }
}

// Always respond with 200 OK for webhook verification
http_response_code(200);
echo "OK";