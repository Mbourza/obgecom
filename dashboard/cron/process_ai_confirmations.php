<?php
// /www/wwwroot/obgecom/dashboard/cron/process_ai_confirmations.php
// Run this every 15 minutes via cron

// Define BASE_PATH before loading init
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/www/wwwroot/obgecom/dashboard/');
}

// Set working directory to dashboard root
chdir(BASE_PATH);

// Load dependencies
require_once(BASE_PATH . 'core/init.php');

$db = DB::getInstance();

// Define log file
$logFile = '/www/wwwroot/obgecom/logs/ai_cron.log';

// Ensure log directory exists
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log function
function writeAILog($message) {
    global $logFile;
    $timestamp = date('[Y-m-d H:i:s] ');
    file_put_contents($logFile, $timestamp . $message . PHP_EOL, FILE_APPEND);
}

try {
    writeAILog("===== Starting AI confirmation processing =====");
    
    // Load AI service
    require_once(BASE_PATH . 'classes/AIConfirmationService.php');
    
    // STEP 1: Get all users with AI confirmation enabled
    // CORRECTED QUERY: ai_confirmation is a column, not a setting_key value
    $users = $db->getThisQuery(
        "SELECT DISTINCT user_id 
         FROM system_settings 
         WHERE ai_confirmation = 1"
    );
    
    if (empty($users)) {
        writeAILog("No users with AI confirmation enabled");
        
        // Debug: Check what's in the table
        $debugUsers = $db->getThisQuery("SELECT id, user_id, ai_confirmation FROM system_settings LIMIT 10");
        if (!empty($debugUsers)) {
            writeAILog("DEBUG: Found " . count($debugUsers) . " records in system_settings:");
            foreach ($debugUsers as $u) {
                writeAILog("  ID: {$u['id']}, User: {$u['user_id']}, ai_confirmation: {$u['ai_confirmation']}");
            }
        } else {
            writeAILog("DEBUG: No records found in system_settings table");
        }
        
        exit(0);
    }
    
    writeAILog("Found " . count($users) . " users with AI confirmation enabled");
    
    $totalRetried = 0;
    $totalEscalated = 0;
    $totalFailed = 0;
    
    foreach ($users as $user) {
        $userId = $user['user_id'];
        
        try {
            writeAILog("Processing AI confirmations for user: {$userId}");
            
            $aiService = new AIConfirmationService($db, $userId);
            
            if (!$aiService->isAIConfirmationEnabled()) {
                writeAILog("AI confirmation disabled for user: {$userId}");
                continue;
            }
            
            // Only process pending attempts for this specific user
            $result = $aiService->checkAndProcessPendingAttempts();
            
            $totalRetried += $result['retried'];
            $totalEscalated += $result['escalated'];
            $totalFailed += $result['failed'];
            
            writeAILog("User {$userId}: Retried: {$result['retried']}, Escalated: {$result['escalated']}, Failed: {$result['failed']}");
            
            // Create notifications for escalated orders
            if ($result['escalated'] > 0) {
                $escalatedOrders = $db->getThisQuery(
                    "SELECT order_id FROM ai_confirmation_attempts 
                     WHERE user_id = ? 
                     AND escalated_to_agent = 1 
                     AND escalated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                     AND status = 'escalated'",
                    [$userId]
                );
                
                foreach ($escalatedOrders as $order) {
                    $orderDetails = $db->getThisQuery(
                        "SELECT order_number, customer_name, total_amount 
                         FROM orders 
                         WHERE id = ? AND user_id = ?",
                        [$order['order_id'], $userId]
                    );
                    
                    if (!empty($orderDetails)) {
                        $orderData = $orderDetails[0];
                        
                        $notificationData = [
                            'user_id' => $userId,
                            'order_id' => $order['order_id'],
                            'type' => 'ai_escalation',
                            'title' => 'AI Confirmation Escalated',
                            'message' => "Commande #{$orderData['order_number']} - Client: {$orderData['customer_name']} - Montant: {$orderData['total_amount']} - AI confirmation timeout, agent assignment needed.",
                            'is_read' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $db->insert('notifications', $notificationData);
                    }
                }
            }
            
        } catch (Exception $e) {
            writeAILog("Error processing user {$userId}: " . $e->getMessage());
        }
    }
    
    writeAILog("AI confirmation processing completed. Total - Retried: {$totalRetried}, Escalated: {$totalEscalated}, Failed: {$totalFailed}");
    writeAILog("===== AI confirmation processing finished =====\n");
    
} catch (Exception $e) {
    writeAILog("CRITICAL ERROR: " . $e->getMessage());
    error_log("AI Confirmation cron error: " . $e->getMessage());
}?>