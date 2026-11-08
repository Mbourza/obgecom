<?php
// services/BotConfirmationService.php
//define('BASE_PATH', '/www/wwwroot/obgecom/dashboard/');

require_once (BASE_PATH.'services/WhatsAppService.php');

class BotConfirmationService {
    private $db;
    private $whatsapp;
    
    // Order status constants
    const ORDER_STATUS_NEW = 'new-order';
    const ORDER_STATUS_CONFIRMED = 'confirmed';
    const ORDER_STATUS_CANCELLED = 'cancelled';
    const ORDER_STATUS_NO_ANSWER = 'no-answer';
    const ORDER_STATUS_BUSY = 'busy';
    const ORDER_STATUS_DOUBLE_ORDER = 'double-order';
    const ORDER_STATUS_UNREACHABLE = 'unreachable';
    
    public function __construct($db) {
        $this->db = $db;
        $this->whatsapp = new WhatsAppService($db);
    }
    
    /**
     * Process new order - send first message within 60 seconds
     */
    public function processNewOrder($order_id) {
        $order = $this->getOrder($order_id);
        
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        // Only process new orders
        if ($order['status'] !== self::ORDER_STATUS_NEW) {
            return ['success' => false, 'message' => 'Order is not in new status'];
        }
        
        // Check if customer has phone number
        if (empty($order['customer_phone'])) {
            return $this->moveToManualConfirmation($order_id, 'No phone number available');
        }
        
        // Send WhatsApp message
        $result = $this->whatsapp->sendConfirmationMessage($order);
        
        if ($result['success']) {
            // Update order status
            $this->updateOrderConfirmationStatus($order_id, 'bot_first_message_sent', [
                'last_bot_message_at' => date('Y-m-d H:i:s'),
                'bot_attempts' => 1
            ]);
            // Keep order status as 'new-order' until confirmed
            
            $this->logEvent($order_id, 'bot', 'first_message_sent', 
                           'bot_pending', 'bot_first_message_sent',
                           self::ORDER_STATUS_NEW, self::ORDER_STATUS_NEW);
            
            return ['success' => true, 'message' => 'WhatsApp message sent'];
        } else {
            // If WhatsApp fails, move to manual
            return $this->moveToManualConfirmation($order_id, 'Failed to send WhatsApp message');
        }
    }
    
    /**
     * Process customer reply
     */
    public function processCustomerReply($order_id, $response) {
        $order = $this->getOrder($order_id);
        
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        if ($response === 'yes') {
            return $this->confirmOrder($order_id);
        } elseif ($response === 'no') {
            return $this->cancelOrder($order_id);
        } else {
            return $this->handleInvalidResponse($order_id);
        }
    }
    
    /**
     * Confirm order by bot
     */
    private function confirmOrder($order_id) {
        $old_status = $this->getOrderStatus($order_id);
        
        $this->updateOrderConfirmationStatus($order_id, 'confirmed_by_bot', [
            'confirmation_channel' => 'bot'
        ]);
        
        // Update order status to 'confirmed'
        $this->db->update_this("
            UPDATE orders 
            SET status = ?, confirmed_by_agent = 0, handled_at = NOW()
            WHERE id = ?
        ", [self::ORDER_STATUS_CONFIRMED, $order_id]);
        
        $this->logEvent($order_id, 'customer', 'confirmed_by_customer',
                       'bot_first_message_sent', 'confirmed_by_bot',
                       $old_status, self::ORDER_STATUS_CONFIRMED);
        
        // Order is ready for delivery - trigger your delivery workflow
        $this->triggerOrderReady($order_id);
        
        return ['success' => true, 'message' => 'Order confirmed by customer'];
    }
    
    /**
     * Cancel order by bot
     */
    private function cancelOrder($order_id) {
        $old_status = $this->getOrderStatus($order_id);
        
        $this->updateOrderConfirmationStatus($order_id, 'cancelled_by_bot', []);
        
        // Update order status to 'cancelled'
        $this->db->update_this("
            UPDATE orders 
            SET status = ?, handled_at = NOW()
            WHERE id = ?
        ", [self::ORDER_STATUS_CANCELLED, $order_id]);
        
        $this->logEvent($order_id, 'customer', 'cancelled_by_customer',
                       'bot_first_message_sent', 'cancelled_by_bot',
                       $old_status, self::ORDER_STATUS_CANCELLED);
        
        // Notify merchant
        $this->notifyMerchant($order_id, 'Order cancelled by customer via WhatsApp');
        
        return ['success' => true, 'message' => 'Order cancelled by customer'];
    }
    
    /**
     * Handle invalid response
     */
    private function handleInvalidResponse($order_id) {
        $order = $this->getOrder($order_id);
        
        // If it's still within 2 hours and first message, wait for reminder
        if ($order['confirmation_status'] === 'bot_first_message_sent') {
            // Don't do anything, wait for reminder
            return ['success' => false, 'message' => 'Invalid response, waiting for proper response'];
        }
        
        // Otherwise move to manual
        return $this->moveToManualConfirmation($order_id, 'Invalid customer response');
    }
    
    /**
     * Send second reminder (called by cron after 2 hours)
     */
    public function sendSecondReminder($order_id) {
        $order = $this->getOrder($order_id);
        
        if (!$order || $order['confirmation_status'] !== 'bot_first_message_sent') {
            return false;
        }
        
        // Don't send reminder if order is already cancelled or confirmed
        if (in_array($order['status'], [self::ORDER_STATUS_CONFIRMED, self::ORDER_STATUS_CANCELLED])) {
            return false;
        }
        
        $result = $this->whatsapp->sendReminderMessage($order);
        
        if ($result['success']) {
            $this->updateOrderConfirmationStatus($order_id, 'bot_second_message_sent', [
                'last_bot_message_at' => date('Y-m-d H:i:s'),
                'bot_attempts' => $order['bot_attempts'] + 1
            ]);
            
            $this->logEvent($order_id, 'bot', 'second_reminder_sent',
                           'bot_first_message_sent', 'bot_second_message_sent',
                           $order['status'], $order['status']);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check for expired orders (4 hours with no response)
     */
    public function checkExpiredOrders() {
        $expired_orders = $this->db->getThisQuery("
            SELECT id 
            FROM orders 
            WHERE confirmation_status IN ('bot_first_message_sent', 'bot_second_message_sent')
            AND status = 'new-order'
            AND last_bot_message_at < DATE_SUB(NOW(), INTERVAL 4 HOUR)
        ");
        
        foreach ($expired_orders as $order) {
            $old_status = $this->getOrderStatus($order['id']);
            
            // Update order status to 'no-answer'
            $this->db->update_this("
                UPDATE orders 
                SET status = ?
                WHERE id = ?
            ", [self::ORDER_STATUS_NO_ANSWER, $order['id']]);
            
            $this->moveToManualConfirmation($order['id'], 'No customer response after 4 hours');
            
            $this->logEvent($order['id'], 'system', 'expired_no_response',
                           null, 'manual_required',
                           $old_status, self::ORDER_STATUS_NO_ANSWER);
        }
        
        return count($expired_orders);
    }
    
    /**
     * Move order to manual confirmation and assign to agent
     */
    private function moveToManualConfirmation($order_id, $reason) {
        $old_status = $this->getOrderStatus($order_id);
        
        $this->updateOrderConfirmationStatus($order_id, 'manual_required', [
            'bot_failed_reason' => $reason
        ]);
        
        $this->logEvent($order_id, 'system', 'moved_to_manual',
                       null, 'manual_required',
                       $old_status, $old_status, $reason);
        
        // Assign to an agent using your existing assignment logic
        return $this->assignToAgent($order_id, $reason);
    }
    
    /**
     * Assign order to agent using your existing agent_order_assignments table
     */
    private function assignToAgent($order_id, $reason) {
        // Find best agent
        $agent = $this->findBestAgent($order_id);
        
        if (!$agent) {
            $this->updateOrderConfirmationStatus($order_id, 'manual_required_unassigned', [
                'bot_failed_reason' => $reason . ' - No agent available'
            ]);
            return ['success' => false, 'message' => 'No agent available'];
        }
        
        // Check if assignment already exists
        $existing = $this->db->getThisQuery("
            SELECT id FROM agent_order_assignments 
            WHERE order_id = ? AND user_id = ?
        ", [$order_id, $this->getUserIdFromOrder($order_id)]);
        
        $assignment_data = [
            'order_id' => $order_id,
            'agent_id' => $agent['id'],
            'user_id' => $this->getUserIdFromOrder($order_id),
            'store_id' => $this->getStoreIdFromOrder($order_id),
            'status' => 'pending',
            'priority_score' => 0,
            'notes' => $reason,
            'assigned_at' => date('Y-m-d H:i:s')
        ];
        
        if (empty($existing)) {
            // Create new assignment
            $this->db->insert('agent_order_assignments', $assignment_data);
        } else {
            // Update existing
            $this->db->update_this("
                UPDATE agent_order_assignments 
                SET agent_id = ?, notes = ?, assigned_at = ?
                WHERE order_id = ? AND user_id = ?
            ", [$agent['id'], $reason, date('Y-m-d H:i:s'), $order_id, 
                $this->getUserIdFromOrder($order_id)]);
        }
        
        // Update order with assigned agent
        $this->updateOrderConfirmationStatus($order_id, 'awaiting_agent_confirmation', [
            'assigned_agent_id' => $agent['id'],
            'assigned_at' => date('Y-m-d H:i:s')
        ]);
        
        return ['success' => true, 'message' => 'Order assigned to agent', 'agent_id' => $agent['id']];
    }
    
    /**
     * Find best agent for assignment
     */
    private function findBestAgent($order_id) {
        $order = $this->getOrder($order_id);
        
        // Query to find active agent with least pending assignments
        $query = "
            SELECT a.*, COUNT(aoa.id) as pending_count
            FROM agents a
            LEFT JOIN agent_order_assignments aoa 
                ON a.id = aoa.agent_id 
                AND aoa.status = 'pending'
            WHERE a.is_active = 1
            GROUP BY a.id
            ORDER BY pending_count ASC
            LIMIT 1
        ";
        
        // If you have zone-based assignment and order has city
        if (!empty($order['customer_ville'])) {
            $zone_query = "
                SELECT a.*, COUNT(aoa.id) as pending_count
                FROM agents a
                LEFT JOIN agent_order_assignments aoa 
                    ON a.id = aoa.agent_id 
                    AND aoa.status = 'pending'
                WHERE a.is_active = 1 
                AND (a.zone = ? OR a.zone IS NULL)
                GROUP BY a.id
                ORDER BY 
                    CASE WHEN a.zone = ? THEN 0 ELSE 1 END,
                    pending_count ASC
                LIMIT 1
            ";
            $result = $this->db->getThisQuery($zone_query, [
                $order['customer_ville'], 
                $order['customer_ville']
            ]);
            
            if (!empty($result)) {
                return $result[0];
            }
        }
        
        $result = $this->db->getThisQuery($query);
        return $result[0] ?? null;
    }
    
    /**
     * Agent actions
     */
    public function agentConfirmOrder($order_id, $agent_id) {
        $old_status = $this->getOrderStatus($order_id);
        
        $this->updateOrderConfirmationStatus($order_id, 'confirmed_by_agent', [
            'confirmation_channel' => 'agent'
        ]);
        
        // Update order status to 'confirmed'
        $this->db->update_this("
            UPDATE orders 
            SET status = ?, confirmed_by_agent = 1, handled_at = NOW()
            WHERE id = ?
        ", [self::ORDER_STATUS_CONFIRMED, $order_id]);
        
        // Update assignment status
        $this->db->update_this("
            UPDATE agent_order_assignments 
            SET status = 'completed', confirmed_at = NOW()
            WHERE order_id = ? AND agent_id = ?
        ", [$order_id, $agent_id]);
        
        $this->logEvent($order_id, 'agent', 'confirmed_by_agent',
                       'awaiting_agent_confirmation', 'confirmed_by_agent',
                       $old_status, self::ORDER_STATUS_CONFIRMED);
        
        return ['success' => true, 'message' => 'Order confirmed by agent'];
    }
    
    public function agentCancelOrder($order_id, $agent_id, $reason = null) {
        $old_status = $this->getOrderStatus($order_id);
        
        $this->updateOrderConfirmationStatus($order_id, 'cancelled_by_agent', [
            'bot_failed_reason' => $reason
        ]);
        
        // Update order status to 'cancelled'
        $this->db->update_this("
            UPDATE orders 
            SET status = ?, handled_at = NOW()
            WHERE id = ?
        ", [self::ORDER_STATUS_CANCELLED, $order_id]);
        
        // Update assignment status
        $this->db->update_this("
            UPDATE agent_order_assignments 
            SET status = 'completed', confirmed_at = NOW()
            WHERE order_id = ? AND agent_id = ?
        ", [$order_id, $agent_id]);
        
        $this->logEvent($order_id, 'agent', 'cancelled_by_agent',
                       'awaiting_agent_confirmation', 'cancelled_by_agent',
                       $old_status, self::ORDER_STATUS_CANCELLED, $reason);
        
        // Notify merchant
        $this->notifyMerchant($order_id, "Order cancelled by agent: $reason");
        
        return ['success' => true, 'message' => 'Order cancelled by agent'];
    }
    
    /**
     * Helper methods
     */
    private function getOrder($order_id) {
        $result = $this->db->getThisQuery("
            SELECT * FROM orders WHERE id = ? LIMIT 1
        ", [$order_id]);
        
        return $result[0] ?? null;
    }
    
    private function getOrderStatus($order_id) {
        $result = $this->db->getThisQuery("
            SELECT status FROM orders WHERE id = ? LIMIT 1
        ", [$order_id]);
        
        return $result[0]['status'] ?? null;
    }
    
    private function getUserIdFromOrder($order_id) {
        $result = $this->db->getThisQuery("
            SELECT user_id FROM orders WHERE id = ? LIMIT 1
        ", [$order_id]);
        
        return $result[0]['user_id'] ?? null;
    }
    
    private function getStoreIdFromOrder($order_id) {
        $result = $this->db->getThisQuery("
            SELECT store_id FROM orders WHERE id = ? LIMIT 1
        ", [$order_id]);
        
        return $result[0]['store_id'] ?? null;
    }
    
    private function updateOrderConfirmationStatus($order_id, $confirmation_status, $additional = []) {
        $updates = ['confirmation_status' => $confirmation_status];
        
        if (isset($additional['last_bot_message_at'])) {
            $updates['last_bot_message_at'] = $additional['last_bot_message_at'];
        }
        
        if (isset($additional['bot_attempts'])) {
            $updates['bot_attempts'] = $additional['bot_attempts'];
        }
        
        if (isset($additional['confirmation_channel'])) {
            $updates['confirmation_channel'] = $additional['confirmation_channel'];
        }
        
        if (isset($additional['assigned_agent_id'])) {
            $updates['assigned_agent_id'] = $additional['assigned_agent_id'];
        }
        
        if (isset($additional['assigned_at'])) {
            $updates['assigned_at'] = $additional['assigned_at'];
        }
        
        if (isset($additional['bot_failed_reason'])) {
            $updates['bot_failed_reason'] = $additional['bot_failed_reason'];
        }
        
        // Build SET clause
        $set_parts = [];
        $values = [];
        foreach ($updates as $key => $value) {
            $set_parts[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $order_id;
        
        $sql = "UPDATE orders SET " . implode(', ', $set_parts) . " WHERE id = ?";
        $this->db->update_this($sql, $values);
    }
    
    private function logEvent($order_id, $actor_type, $event_type, 
                              $old_confirmation_status, $new_confirmation_status,
                              $old_order_status = null, $new_order_status = null, 
                              $message = null) {
        $this->db->insert('confirmation_logs', [
            'order_id' => $order_id,
            'actor_type' => $actor_type,
            'actor_id' => null,
            'event_type' => $event_type,
            'old_status' => $old_confirmation_status,
            'new_status' => $new_confirmation_status,
            'old_order_status' => $old_order_status,
            'new_order_status' => $new_order_status,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    private function triggerOrderReady($order_id) {
        // Trigger your existing delivery workflow
        // You can call your API endpoint or webhook
        $order = $this->getOrder($order_id);
        
        // Example: Call your delivery assignment system
        // $this->callDeliveryWebhook($order);
        
        error_log("Order $order_id is confirmed and ready for delivery");
    }
    
    private function notifyMerchant($order_id, $message) {
        // Implement merchant notification (email, SMS, etc.)
        // You can use your existing notification system
        error_log("Merchant notification for order $order_id: $message");
    }
}
?>