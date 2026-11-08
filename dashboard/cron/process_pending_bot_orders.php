<?php
// /www/wwwroot/obgecom/dashboard/cron/process_pending_bot_orders.php
// Run this every minute via cron

// Define BASE_PATH before loading init
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/www/wwwroot/obgecom/dashboard/');
}

// Set working directory to dashboard root
chdir(BASE_PATH);

// Load core init
require_once(BASE_PATH . 'core/init.php');

$db = DB::getInstance();

// Define log file
$logFile = '/www/wwwroot/obgecom/logs/bot_cron.log';

// Ensure log directory exists
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log function
function writeBotLog($message) {
    global $logFile;
    $timestamp = date('[Y-m-d H:i:s] ');
    file_put_contents($logFile, $timestamp . $message . PHP_EOL, FILE_APPEND);
}

try {
    writeBotLog("===== Starting bot order processing =====");
    
    // STEP 1: Get all users who have AI confirmation enabled
    // CORRECTED QUERY: ai_confirmation is a column, not a setting_key value
    $aiUsers = $db->getThisQuery(
        "SELECT DISTINCT user_id 
         FROM system_settings 
         WHERE ai_confirmation = 1"
    );
    
    if (empty($aiUsers)) {
        writeBotLog("No users with AI confirmation enabled");
        writeBotLog("DEBUG: Check system_settings table - ai_confirmation should be 1");
        
        // Debug: Check what's in the table
        $debugUsers = $db->getThisQuery("SELECT id, user_id, ai_confirmation FROM system_settings LIMIT 10");
        if (!empty($debugUsers)) {
            writeBotLog("DEBUG: Found " . count($debugUsers) . " records in system_settings:");
            foreach ($debugUsers as $u) {
                writeBotLog("  ID: {$u['id']}, User: {$u['user_id']}, ai_confirmation: {$u['ai_confirmation']}");
            }
        } else {
            writeBotLog("DEBUG: No records found in system_settings table");
        }
        
        exit(0);
    }
    
    // Extract user IDs
    $userIds = array_column($aiUsers, 'user_id');
    $userIdsPlaceholder = implode(',', array_fill(0, count($userIds), '?'));
    
    writeBotLog("Found " . count($userIds) . " users with AI confirmation enabled: " . implode(', ', $userIds));
    
    // STEP 2: Find orders that need bot processing - ONLY for users with AI enabled
    $pending_orders = $db->getThisQuery("
        SELECT id, order_number, customer_phone, customer_name, 
               total_amount, currency, customer_ville, created_at,
               platform, user_id
        FROM orders 
        WHERE user_id IN ({$userIdsPlaceholder})
        AND status = 'new-order' 
        AND (confirmation_status = 'bot_pending' OR confirmation_status IS NULL)
        AND (assigned_agent_id IS NULL OR assigned_agent_id = 0)
        AND confirmation_status != 'confirmed'
        AND bot_sent = 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at ASC
        LIMIT 50
    ", $userIds);
    
    if ($pending_orders === false) {
        writeBotLog("ERROR: Query failed");
        exit(1);
    }
    
    writeBotLog("Found " . count($pending_orders) . " orders to process for AI users");
    
    // Load WhatsApp service
    $whatsappPath = BASE_PATH . 'services/WhatsAppService.php';
    if (!file_exists($whatsappPath)) {
        writeBotLog("ERROR: WhatsAppService.php not found at: " . $whatsappPath);
        exit(1);
    }
    require_once($whatsappPath);
    
    $processedCount = 0;
    $skippedCount = 0;
    $failedCount = 0;
    
    foreach ($pending_orders as $order) {
        writeBotLog("Processing order #{$order['order_number']} (User: {$order['user_id']}) from {$order['platform']}");
        
        try {
            // Check if customer has phone number
            if (empty($order['customer_phone'])) {
                writeBotLog("⚠️ No phone number for order #{$order['order_number']}, skipping");
                
                $db->update_this("
                    UPDATE orders 
                    SET confirmation_status = 'no_phone',
                        bot_failed_reason = 'No phone number available'
                    WHERE id = ?
                ", [$order['id']]);
                $skippedCount++;
                continue;
            }
            
            // Create WhatsApp service instance for this user
            $whatsapp = new WhatsAppService($db, $order['user_id']);
            
            if (!$whatsapp->isEnabled()) {
                writeBotLog("⚠️ WhatsApp not enabled for user {$order['user_id']}, skipping order #{$order['order_number']}");
                
                $db->update_this("
                    UPDATE orders 
                    SET confirmation_status = 'whatsapp_disabled'
                    WHERE id = ?
                ", [$order['id']]);
                $skippedCount++;
                continue;
            }
            
            // Format phone number
            $phone = $whatsapp->formatPhoneNumber($order['customer_phone']);
            
            // Generate confirmation message
            $message = "Bonjour {$order['customer_name']},\n\n";
            $message .= "Nous avons bien reçu votre commande #{$order['order_number']}.\n";
            $message .= "Montant: {$order['total_amount']} {$order['currency']}\n";
            $message .= "Ville: {$order['customer_ville']}\n\n";
            $message .= "Pour confirmer cette commande, veuillez répondre 'OUI' à ce message.\n";
            $message .= "Si vous avez des questions, n'hésitez pas à nous contacter.\n\n";
            $message .= "Merci,\nL'équipe OBG";
            
            // Send message
            $result = $whatsapp->sendMessage($phone, $message);
            
            if ($result['success']) {
                writeBotLog("✅ WhatsApp sent for order #{$order['order_number']} to {$phone}");
                
                // Update order status
                $db->update_this("
                    UPDATE orders 
                    SET confirmation_status = 'sent',
                        confirmation_sent_at = NOW(),
                        bot_sent = 1,
                        bot_sent_at = NOW()
                    WHERE id = ?
                ", [$order['id']]);
                
                // Log the confirmation
                $db->insert('confirmation_logs', [
                    'order_id' => $order['id'],
                    'actor_type' => 'system',
                    'event_type' => 'bot_triggered_by_cron',
                    'message' => 'Bot triggered successfully',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $processedCount++;
            } else {
                $errorMsg = $result['error'] ?? 'Unknown error';
                writeBotLog("❌ Failed for order #{$order['order_number']}: {$errorMsg}");
                
                $db->update_this("
                    UPDATE orders 
                    SET confirmation_status = 'failed',
                        bot_failed_reason = ?,
                        bot_failed_at = NOW()
                    WHERE id = ?
                ", [$errorMsg, $order['id']]);
                $failedCount++;
            }
            
        } catch (Exception $e) {
            writeBotLog("❌ Exception for order #{$order['order_number']}: " . $e->getMessage());
            
            $db->update_this("
                UPDATE orders 
                SET confirmation_status = 'error',
                    bot_failed_reason = ?
                WHERE id = ?
            ", [$e->getMessage(), $order['id']]);
            $failedCount++;
        }
        
        // Small delay to avoid rate limiting
        usleep(500000); // 0.5 second delay
    }
    
    writeBotLog("===== Bot order processing finished =====");
    writeBotLog("Processed: {$processedCount}, Skipped: {$skippedCount}, Failed: {$failedCount}\n");
    
} catch (Exception $e) {
    writeBotLog("CRITICAL ERROR: " . $e->getMessage());
    error_log("Bot cron error: " . $e->getMessage());
} ?>