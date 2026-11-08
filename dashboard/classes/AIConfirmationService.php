<?php
// classes/AIConfirmationService.php

class AIConfirmationService {
    private $db;
    private $user_id;
    private $order_id;
    private $settings;
    private $cache = [];
    private $whatsappService;
    
    // Default values as class constants
    const DEFAULT_MAX_ATTEMPTS = 3;
    const DEFAULT_MAX_WAIT_HOURS = 24;
    const DEFAULT_RETRY_INTERVAL_HOURS = 2;
    
    public function __construct($db, $user_id) {
        $this->db = $db;
        $this->user_id = $user_id;
        $this->loadSettings();
        $this->initWhatsAppService();
    }
    
    /**
     * Initialize WhatsApp service
     */
    private function initWhatsAppService() {
        // Check if WhatsAppService exists and load it
        $whatsappPath = __DIR__ . '/../services/WhatsAppService.php';
        if (file_exists($whatsappPath)) {
            require_once($whatsappPath);
            $this->whatsappService = new WhatsAppService($this->db, $this->user_id);
        } else {
            error_log("WhatsAppService.php not found at: " . $whatsappPath);
            $this->whatsappService = null;
        }
    }
    
    /**
     * Load AI confirmation settings from database
     * Using the existing security_settings table
    */
    private function loadSettings() {
        // Try to get settings from system_settings table
        $settings = $this->db->getThisQuery(
            "SELECT * FROM system_settings 
             WHERE user_id = ?",
            [$this->user_id]
        );
        
        $this->settings = [];
        $this->settings['ai_confirmation'] = 1; // Default enabled
        $this->settings['max_ai_attempts'] = self::DEFAULT_MAX_ATTEMPTS;
        $this->settings['ai_max_wait_hours'] = self::DEFAULT_MAX_WAIT_HOURS;
        $this->settings['ai_retry_interval_hours'] = self::DEFAULT_RETRY_INTERVAL_HOURS;
        
        if (!empty($settings)) {
            foreach ($settings as $setting) {
                $key = $setting['setting_key'];
                $value = $setting['setting_value'];
                
                // Convert value based on setting_type
                switch ($setting['setting_type']) {
                    case 'boolean':
                        $value = (int)$value;
                        break;
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'float':
                        $value = (float)$value;
                        break;
                    // Add more types as needed
                }
                
                if (isset($this->settings[$key])) {
                    $this->settings[$key] = $value;
                }
            }
            
            // Store the raw settings for reference
            $this->settings['system_settings'] = $settings;
        } else {
            // Create default system settings for this user if not exists
            $this->createDefaultSecuritySettings();
        }
    }
    
    /**
     * Create default security settings for a user
     */
    private function createDefaultSecuritySettings() {
        try {
            $this->db->insert('security_settings', [
                'user_id' => $this->user_id,
                'two_factor_auth' => 0,
                'max_login_attempts' => self::DEFAULT_MAX_ATTEMPTS,
                'session_timeout' => 3600,
                'password_policy_strict' => 0,
                'ip_whitelist' => null,
                'audit_log_enabled' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to create default security settings: " . $e->getMessage());
        }
    }
    
    /**
     * Check if AI confirmation is enabled for this user
     */
    public function isAIConfirmationEnabled() {
        // Check if AI confirmation is enabled
        // Default to true if security settings exist
        $enabled = isset($this->settings['ai_confirmation']) && 
                   $this->settings['ai_confirmation'] == 1;
        
        // Check if WhatsApp is available
        if ($enabled && $this->whatsappService) {
            $enabled = $this->whatsappService->isEnabled();
        }
        
        return $enabled;
    }
    
    /**
     * Get the latest AI confirmation attempt for an order
     */
    public function getLatestAttempt($order_id) {
        // Check cache first
        if (isset($this->cache['attempts'][$order_id])) {
            return $this->cache['attempts'][$order_id];
        }
        
        $result = $this->db->getThisQuery(
            "SELECT * FROM ai_confirmation_attempts 
             WHERE order_id = ? AND user_id = ? 
             ORDER BY attempt_number DESC LIMIT 1",
            [$order_id, $this->user_id]
        );
        
        $this->cache['attempts'][$order_id] = $result[0] ?? null;
        return $this->cache['attempts'][$order_id];
    }
    
    /**
     * Get AI confirmation status for multiple orders (bulk)
     */
    public function getAIConfirmationStatusForOrders($order_ids) {
        if (empty($order_ids)) {
            return [];
        }
        
        // Check cache for all orders first
        $cached = [];
        $missing = [];
        
        foreach ($order_ids as $id) {
            if (isset($this->cache['attempts'][$id])) {
                $cached[$id] = $this->cache['attempts'][$id];
            } else {
                $missing[] = $id;
            }
        }
        
        if (empty($missing)) {
            return $cached;
        }
        
        // Fetch missing orders from database
        $placeholders = implode(',', array_fill(0, count($missing), '?'));
        $params = array_merge([$this->user_id], $missing);
        
        $results = $this->db->getThisQuery(
            "SELECT * FROM ai_confirmation_attempts 
             WHERE user_id = ? AND order_id IN ({$placeholders})
             AND (order_id, attempt_number) IN (
                 SELECT order_id, MAX(attempt_number) 
                 FROM ai_confirmation_attempts 
                 WHERE user_id = ? AND order_id IN ({$placeholders})
                 GROUP BY order_id
             )",
            array_merge([$this->user_id], $missing, [$this->user_id], $missing)
        );
        
        $statusMap = $cached;
        foreach ($results as $result) {
            $statusMap[$result['order_id']] = $result;
            $this->cache['attempts'][$result['order_id']] = $result;
        }
        
        return $statusMap;
    }
    
    /**
     * Get complete AI confirmation status for an order
     */
    public function getOrderAIConfirmationStatus($order_id) {
        $attempt = $this->getLatestAttempt($order_id);
        
        if (!$attempt) {
            return [
                'status' => 'not_started',
                'ai_enabled' => $this->isAIConfirmationEnabled(),
                'message' => 'AI confirmation not started'
            ];
        }
        
        return [
            'status' => $attempt['status'],
            'attempt_number' => (int)$attempt['attempt_number'],
            'attempts_remaining' => (int)$attempt['attempts_remaining'],
            'next_attempt_at' => $attempt['next_attempt_at'],
            'escalated' => (bool)$attempt['escalated_to_agent'],
            'customer_response' => $attempt['customer_response'],
            'response_received_at' => $attempt['response_received_at'],
            'created_at' => $attempt['created_at'],
            'updated_at' => $attempt['updated_at'],
            'ai_enabled' => $this->isAIConfirmationEnabled(),
            'message_content' => $attempt['message_content']
        ];
    }
    
    /**
     * Check if we should wait for AI before assigning to agent
     */
    public function shouldWaitForAI($order_id) {
        if (!$this->isAIConfirmationEnabled()) {
            return false;
        }
        
        // Check if order has been processed by AI
        $attempt = $this->getLatestAttempt($order_id);
        
        if (!$attempt) {
            // No AI attempt yet, start AI process
            $this->startAIConfirmation($order_id);
            return true;
        }
        
        // Check if AI process is complete (escalated or customer responded)
        if ($attempt['status'] === 'escalated' || $attempt['escalated_to_agent'] == 1) {
            return false;
        }
        
        if ($attempt['status'] === 'customer_confirmed' || $attempt['status'] === 'customer_rejected') {
            return false;
        }
        
        // Check if waiting for AI response
        if ($attempt['status'] === 'pending' || $attempt['status'] === 'sent') {
            return $this->isWithinAITimeWindow($attempt);
        }
        
        return true;
    }
    
    /**
     * Process customer response to AI message
     */
    public function processAIResponse($order_id, $response, $channel = 'whatsapp') {
        $attempt = $this->getLatestAttempt($order_id);
        
        if (!$attempt) {
            return ['success' => false, 'message' => 'No AI attempt found for this order'];
        }
        
        $response_upper = strtoupper(trim($response));
        
        if ($response_upper === 'OUI' || $response_upper === 'YES' || $response_upper === 'Y') {
            // Customer confirmed
            $this->db->update(
                'ai_confirmation_attempts',
                [
                    'status' => 'customer_confirmed',
                    'customer_response' => $response,
                    'response_received_at' => date('Y-m-d H:i:s'),
                    'response_channel' => $channel,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                "order_id = ? AND user_id = ? AND id = ?",
                [$order_id, $this->user_id, $attempt['id']]
            );
            
            // Update order status
            $this->db->update_this(
                "UPDATE orders SET 
                    status = 'confirmed',
                    confirmation_method = 'ai',
                    confirmed_at = NOW(),
                    updated_at = NOW(),
                    ai_confirmed = 1
                WHERE id = ? AND user_id = ?",
                [$order_id, $this->user_id]
            );
            
            // Mark as ready for shipping
            $this->markOrderReadyForShipping($order_id);
            
            // Log the confirmation
            error_log("AI: Customer confirmed order #{$order_id} via {$channel}");
            
            return [
                'success' => true, 
                'status' => 'confirmed',
                'message' => 'Order confirmed by customer via AI'
            ];
            
        } elseif ($response_upper === 'NON' || $response_upper === 'NO' || $response_upper === 'N') {
            // Customer rejected
            $this->db->update(
                'ai_confirmation_attempts',
                [
                    'status' => 'customer_rejected',
                    'customer_response' => $response,
                    'response_received_at' => date('Y-m-d H:i:s'),
                    'response_channel' => $channel,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                "order_id = ? AND user_id = ? AND id = ?",
                [$order_id, $this->user_id, $attempt['id']]
            );
            
            // Update order status
            $this->db->update_this(
                "UPDATE orders SET 
                    status = 'cancelled',
                    cancellation_reason = 'Customer rejected via AI',
                    cancelled_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND user_id = ?",
                [$order_id, $this->user_id]
            );
            
            error_log("AI: Customer rejected order #{$order_id} via {$channel}");
            
            return [
                'success' => true,
                'status' => 'rejected',
                'message' => 'Order rejected by customer via AI'
            ];
            
        } else {
            // Invalid response, log it but don't change status
            $this->db->update(
                'ai_confirmation_attempts',
                [
                    'customer_response' => $response,
                    'response_channel' => $channel,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                "order_id = ? AND user_id = ? AND id = ?",
                [$order_id, $this->user_id, $attempt['id']]
            );
            
            return [
                'success' => false,
                'status' => 'invalid_response',
                'message' => 'Invalid response received. Expected OUI or NON.'
            ];
        }
    }
    
    /**
     * Get all pending AI confirmations
     */
    public function getPendingAIConfirmations() {
        if (!$this->isAIConfirmationEnabled()) {
            return [];
        }
        
        return $this->db->getThisQuery(
            "SELECT a.*, o.order_number, o.customer_name, o.total_amount, o.currency, o.customer_phone
             FROM ai_confirmation_attempts a
             JOIN orders o ON a.order_id = o.id
             WHERE a.user_id = ? 
             AND a.status IN ('pending', 'sent')
             AND a.escalated_to_agent = 0
             AND a.attempts_remaining > 0
             ORDER BY a.created_at ASC",
            [$this->user_id]
        );
    }
    
    /**
     * Check and process pending AI attempts
     */
    public function checkAndProcessPendingAttempts() {
        if (!$this->isAIConfirmationEnabled()) {
            return ['retried' => 0, 'escalated' => 0, 'failed' => 0];
        }
        
        $pendingAttempts = $this->getPendingAIConfirmations();
        $processed = ['retried' => 0, 'escalated' => 0, 'failed' => 0];
        
        foreach ($pendingAttempts as $attempt) {
            $timeSinceLastAttempt = time() - strtotime($attempt['updated_at']);
            $retryInterval = ($this->settings['ai_retry_interval_hours'] ?? self::DEFAULT_RETRY_INTERVAL_HOURS) * 3600;
            
            if ($timeSinceLastAttempt >= $retryInterval) {
                if ($attempt['attempts_remaining'] > 1) {
                    $this->retryAIConfirmation($attempt['order_id']);
                    $processed['retried']++;
                    error_log("AI: Retry attempt for order #{$attempt['order_id']}");
                } else {
                    $this->escalateToAgent($attempt['order_id']);
                    $processed['escalated']++;
                    error_log("AI: Escalated order #{$attempt['order_id']} to agents");
                }
            }
        }
        
        return $processed;
    }
    
    /**
     * Force escalate an order to agents
     */
    public function forceEscalateToAgent($order_id) {
        $attempt = $this->getLatestAttempt($order_id);
        
        if (!$attempt) {
            return ['success' => false, 'message' => 'No AI attempt found'];
        }
        
        if ($attempt['escalated_to_agent'] == 1) {
            return ['success' => false, 'message' => 'Already escalated'];
        }
        
        $this->escalateToAgent($order_id);
        
        return [
            'success' => true,
            'message' => 'Order escalated to agents'
        ];
    }
    
    /**
     * Start AI confirmation process for an order
     */
    public function startAIConfirmation($order_id) {
        $this->order_id = $order_id;
        
        // Get order details with customer info
        $order = $this->db->getThisQuery(
            "SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email
             FROM orders o
             JOIN customers c ON o.customer_id = c.id
             WHERE o.id = ? AND o.user_id = ?",
            [$order_id, $this->user_id]
        );
        
        if (!$order) {
            error_log("AI: Order #{$order_id} not found or no customer");
            return false;
        }
        
        $order = $order[0];
        
        // Check if customer has phone or email
        if (empty($order['customer_phone']) && empty($order['customer_email'])) {
            error_log("AI: Order #{$order_id} has no contact info, escalating to agent");
            $this->escalateToAgent($order_id);
            return false;
        }
        
        // Create AI confirmation attempt
        $attemptData = [
            'order_id' => $order_id,
            'user_id' => $this->user_id,
            'attempt_number' => 1,
            'status' => 'pending',
            'message_content' => $this->generateAIMessage($order),
            'attempts_remaining' => $this->settings['max_ai_attempts'] ?? self::DEFAULT_MAX_ATTEMPTS,
            'next_attempt_at' => date('Y-m-d H:i:s', strtotime('+' . ($this->settings['ai_retry_interval_hours'] ?? self::DEFAULT_RETRY_INTERVAL_HOURS) . ' hours'))
        ];
        
        $this->db->insert('ai_confirmation_attempts', $attemptData);
        
        // Send initial AI message via WhatsApp
        $sent = $this->sendAIMessage($order_id, $attemptData['message_content'], $order);
        
        // Update status based on send result
        $status = $sent ? 'sent' : 'failed';
        $this->db->update(
            'ai_confirmation_attempts',
            ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
            "order_id = ? AND user_id = ? AND attempt_number = 1",
            [$order_id, $this->user_id]
        );
        
        error_log("AI: Started confirmation for order #{$order_id} - Status: {$status}");
        
        return $sent;
    }
    
    /**
     * Generate AI message for customer
     */
    private function generateAIMessage($order) {
        $message = "Bonjour {$order['customer_name']},\n\n";
        $message .= "Nous avons bien reçu votre commande #{$order['order_number']} du " . 
                   date('d/m/Y', strtotime($order['created_at'])) . ".\n\n";
        $message .= "Détails de la commande:\n";
        $message .= "- Montant: {$order['total_amount']} {$order['currency']}\n";
        $message .= "- Articles: " . $this->getOrderItems($order['id']) . "\n\n";
        $message .= "Pourriez-vous confirmer cette commande en répondant 'OUI' à ce message?\n";
        $message .= "Si vous avez des questions, n'hésitez pas à nous contacter.\n\n";
        $message .= "Merci,\nL'équipe OBG";
        
        return $message;
    }
    
    /**
     * Get order items as string
     */
    private function getOrderItems($order_id) {
        $items = $this->db->getThisQuery(
            "SELECT product_name, quantity FROM order_items WHERE order_id = ?",
            [$order_id]
        );
        
        $itemList = [];
        foreach ($items as $item) {
            $itemList[] = "{$item['quantity']}x {$item['product_name']}";
        }
        
        return implode(', ', $itemList);
    }
    
    /**
     * Send AI message to customer via WhatsApp
     */
    private function sendAIMessage($order_id, $message, $order) {
        // Check if WhatsApp service is available
        if (!$this->whatsappService || !$this->whatsappService->isEnabled()) {
            error_log("AI: WhatsApp not configured for user {$this->user_id}");
            return false;
        }
        
        // Get customer phone
        $phone = $order['customer_phone'] ?? null;
        
        if (empty($phone)) {
            error_log("AI: No phone number for order #{$order_id}");
            return false;
        }
        
        // Format phone number
        $phone = $this->whatsappService->formatPhoneNumber($phone);
        
        // Send message using WhatsApp service
        $result = $this->whatsappService->sendMessage($phone, $message);
        
        // Log result
        if ($result['success']) {
            error_log("AI: Message sent to {$phone} for order #{$order_id}");
            return true;
        } else {
            error_log("AI: Failed to send message to {$phone} for order #{$order_id}: " . ($result['error'] ?? 'Unknown error'));
            return false;
        }
    }
    
    /**
     * Check if within AI time window
     */
    private function isWithinAITimeWindow($attempt) {
        $maxWaitHours = $this->settings['ai_max_wait_hours'] ?? self::DEFAULT_MAX_WAIT_HOURS;
        $createdAt = strtotime($attempt['created_at']);
        $currentTime = time();
        $hoursElapsed = ($currentTime - $createdAt) / 3600;
        
        // If within wait window, don't assign to agent
        if ($hoursElapsed < $maxWaitHours) {
            return true;
        }
        
        // Check if we should make another AI attempt
        if ($attempt['attempts_remaining'] > 0) {
            $nextAttemptTime = strtotime($attempt['next_attempt_at']);
            if ($currentTime >= $nextAttemptTime) {
                $this->retryAIConfirmation($attempt['order_id']);
                return true;
            }
            // Still within retry window
            return true;
        }
        
        // Time's up, escalate to agent
        $this->escalateToAgent($attempt['order_id']);
        return false;
    }
    
    /**
     * Retry AI confirmation
     */
    private function retryAIConfirmation($order_id) {
        $currentAttempt = $this->getLatestAttempt($order_id);
        
        if (!$currentAttempt) {
            return;
        }
        
        // Get order details
        $order = $this->db->getThisQuery(
            "SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email
             FROM orders o
             JOIN customers c ON o.customer_id = c.id
             WHERE o.id = ? AND o.user_id = ?",
            [$order_id, $this->user_id]
        );
        
        if (!$order) {
            return;
        }
        
        $order = $order[0];
        $newAttemptNumber = $currentAttempt['attempt_number'] + 1;
        
        // Create new attempt
        $attemptData = [
            'order_id' => $order_id,
            'user_id' => $this->user_id,
            'attempt_number' => $newAttemptNumber,
            'status' => 'pending',
            'message_content' => $this->generateRetryMessage($order, $newAttemptNumber),
            'attempts_remaining' => $currentAttempt['attempts_remaining'] - 1,
            'next_attempt_at' => date('Y-m-d H:i:s', strtotime('+' . ($this->settings['ai_retry_interval_hours'] ?? self::DEFAULT_RETRY_INTERVAL_HOURS) . ' hours'))
        ];
        
        $this->db->insert('ai_confirmation_attempts', $attemptData);
        
        // Send retry message
        $sent = $this->sendAIMessage($order_id, $attemptData['message_content'], $order);
        
        // Update status
        $status = $sent ? 'sent' : 'failed';
        $this->db->update(
            'ai_confirmation_attempts',
            ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
            "order_id = ? AND user_id = ? AND attempt_number = ?",
            [$order_id, $this->user_id, $newAttemptNumber]
        );
        
        error_log("AI: Retry {$newAttemptNumber} for order #{$order_id} - Status: {$status}");
    }
    
    /**
     * Generate retry message
     */
    private function generateRetryMessage($order, $attemptNumber) {
        $message = "Bonjour {$order['customer_name']},\n\n";
        $message .= "Suite à notre message précédent concernant votre commande #{$order['order_number']},\n";
        $message .= "nous n'avons pas encore reçu votre confirmation.\n\n";
        $message .= "Votre commande est en attente de confirmation.\n";
        $message .= "Nous vous prions de bien vouloir confirmer en répondant 'OUI'.\n\n";
        $message .= "Ceci est notre {$attemptNumber}ème tentative de contact.\n";
        $message .= "Si nous n'avons pas de réponse dans 2 heures, nous essaierons à nouveau.\n\n";
        $message .= "Merci,\nL'équipe OBG";
        
        return $message;
    }
    
    /**
     * Escalate order to agent
     */
    private function escalateToAgent($order_id) {
        // Update AI attempt status
        $this->db->update(
            'ai_confirmation_attempts',
            "order_id = ? AND user_id = ? AND status != 'escalated'",
            [
                'status' => 'escalated',
                'escalated_to_agent' => 1,
                'escalated_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [$order_id, $this->user_id]
        );
        
        // Update order status
        $this->db->update_this(
            "UPDATE orders SET 
                status = 'pending_assignment',
                ai_escalated = 1,
                escalated_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND user_id = ?",
            [$order_id, $this->user_id]
        );
        
        // Log the escalation
        error_log("AI: Confirmation escalated to agent for order #{$order_id}");
        
        // Create notification
        $this->notifyAgentEscalation($order_id);
    }
    
    /**
     * Notify about escalation
     */
    private function notifyAgentEscalation($order_id) {
        // Get order details for notification
        $order = $this->db->getThisQuery(
            "SELECT order_number, customer_name, total_amount 
             FROM orders 
             WHERE id = ? AND user_id = ?",
            [$order_id, $this->user_id]
        );
        
        if (!$order) {
            return;
        }
        
        $order = $order[0];
        
        // Create notification
        $notificationData = [
            'user_id' => $this->user_id,
            'order_id' => $order_id,
            'type' => 'ai_escalation',
            'title' => 'AI Confirmation Escalation',
            'message' => "Commande #{$order['order_number']} - Client: {$order['customer_name']} - Montant: {$order['total_amount']} - AI confirmation timeout, agent assignment needed.",
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('notifications', $notificationData);
    }
    
    /**
     * Mark order as ready for shipping
     */
    private function markOrderReadyForShipping($order_id) {
        $this->db->update_this(
            "UPDATE orders SET 
                ready_for_shipping = 1,
                status = 'confirmed',
                ai_confirmed = 1,
                confirmed_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND user_id = ?",
            [$order_id, $this->user_id]
        );
    }
    
    /**
     * Get AI statistics
     */
    public function getAIStatistics() {
        $stats = $this->db->getThisQuery(
            "SELECT 
                COUNT(*) as total_attempts,
                SUM(CASE WHEN status = 'customer_confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'customer_rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status IN ('pending', 'sent') THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN escalated_to_agent = 1 THEN 1 ELSE 0 END) as escalated,
                AVG(attempt_number) as avg_attempts
             FROM ai_confirmation_attempts 
             WHERE user_id = ?",
            [$this->user_id]
        );
        
        return $stats[0] ?? [
            'total_attempts' => 0,
            'confirmed' => 0,
            'rejected' => 0,
            'pending' => 0,
            'escalated' => 0,
            'avg_attempts' => 0
        ];
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
    }
} ?>