<?php
// classes/PlanManager.php

class PlanManager {
    private $db;
    private $user_id;
    private $user_plan;
    private $cache = [];
    
    // Default free plan settings
    const FREE_PLAN_ORDERS_LIMIT = 100;
    const FREE_PLAN_TRIAL_DAYS = 30;
    
    public function __construct($db, $user_id = null) {
        $this->db = $db;
        $this->user_id = $user_id;
        if ($user_id) {
            $this->loadUserPlan();
        }
    }
    
    /**
     * Load current user plan
     */
    private function loadUserPlan() {
        // Get active plan for user
        $plan = $this->db->getThisQuery(
            "SELECT up.*, p.name as plan_name, p.price as plan_price, p.orders_limit as plan_orders_limit,
             p.is_free, p.trial_days, p.features, p.description as plan_description
             FROM user_plans up
             JOIN plans p ON up.plan_id = p.id
             WHERE up.user_id = ? 
             AND up.status IN ('active', 'trial')
             AND (up.end_date IS NULL OR up.end_date > NOW())
             ORDER BY up.created_at DESC 
             LIMIT 1",
            [$this->user_id]
        );
        
        if (!empty($plan)) {
            $this->user_plan = $plan[0];
        } else {
            // User has no active plan - assign free trial
            $this->assignFreeTrial();
        }
    }
    
    /**
     * Assign free trial to new user
     */
    public function assignFreeTrial() {
        // Get free plan
        $freePlan = $this->db->getThisQuery(
            "SELECT * FROM plans WHERE is_free = 1 AND is_active = 1 LIMIT 1",
            []
        );
        
        if (empty($freePlan)) {
            // Create default free plan if not exists
            $planId = $this->createDefaultFreePlan();
            $freePlan = $this->db->getThisQuery("SELECT * FROM plans WHERE id = ?", [$planId]);
        }
        
        $plan = $freePlan[0];
        
        // Check if user already has a plan
        $existing = $this->db->getThisQuery(
            "SELECT * FROM user_plans WHERE user_id = ? AND status IN ('pending', 'active', 'trial')",
            [$this->user_id]
        );
        
        if (!empty($existing)) {
            // Update existing plan
            $endDate = date('Y-m-d H:i:s', strtotime('+' . ($plan['trial_days'] ?? self::FREE_PLAN_TRIAL_DAYS) . ' days'));
            
            $this->db->update(
                'user_plans',
                [
                    'plan_id' => $plan['id'],
                    'status' => 'trial',
                    'start_date' => date('Y-m-d H:i:s'),
                    'end_date' => $endDate,
                    'trial_end_date' => $endDate,
                    'orders_limit' => $plan['orders_limit'] ?? self::FREE_PLAN_ORDERS_LIMIT,
                    'orders_used' => 0,
                    'orders_remaining' => $plan['orders_limit'] ?? self::FREE_PLAN_ORDERS_LIMIT,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                "user_id = ?",
                [$this->user_id]
            );
        } else {
            // Create new user plan
            $endDate = date('Y-m-d H:i:s', strtotime('+' . ($plan['trial_days'] ?? self::FREE_PLAN_TRIAL_DAYS) . ' days'));
            
            $planData = [
                'user_id' => $this->user_id,
                'plan_id' => $plan['id'],
                'status' => 'trial',
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => $endDate,
                'trial_end_date' => $endDate,
                'orders_limit' => $plan['orders_limit'] ?? self::FREE_PLAN_ORDERS_LIMIT,
                'orders_used' => 0,
                'orders_remaining' => $plan['orders_limit'] ?? self::FREE_PLAN_ORDERS_LIMIT,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('user_plans', $planData);
        }
        
        // Reload user plan
        $this->loadUserPlan();
        
        return true;
    }
    
    /**
     * Create default free plan
     */
    private function createDefaultFreePlan() {
        $planData = [
            'name' => 'Free Trial',
            'price' => 0,
            'orders_limit' => self::FREE_PLAN_ORDERS_LIMIT,
            'is_free' => 1,
            'trial_days' => self::FREE_PLAN_TRIAL_DAYS,
            'is_active' => 1,
            'description' => 'Free trial plan with ' . self::FREE_PLAN_ORDERS_LIMIT . ' orders',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('plans', $planData);
        return $this->db->lastInsertId();
    }
    
    /**
     * Check if user can perform an action
     */
    public function canPerformAction($actionType, $orderId = null) {
        if (!$this->user_plan) {
            return ['allowed' => false, 'message' => 'No active plan found'];
        }
        
        // Check if plan is expired
        if ($this->user_plan['status'] === 'expired' || 
            ($this->user_plan['end_date'] && strtotime($this->user_plan['end_date']) < time())) {
            return ['allowed' => false, 'message' => 'Plan has expired'];
        }
        
        // Check if user has orders remaining
        if ($actionType === 'order_sync' || $actionType === 'order_confirm') {
            if ($this->user_plan['orders_remaining'] <= 0) {
                return [
                    'allowed' => false, 
                    'message' => 'You have reached your order limit. Please upgrade your plan.'
                ];
            }
        }
        
        // Check specific action limits if needed
        $limits = $this->getActionLimits($actionType);
        if ($limits) {
            $usage = $this->getActionUsage($actionType);
            if ($usage >= $limits) {
                return [
                    'allowed' => false,
                    'message' => 'You have reached your ' . $actionType . ' limit'
                ];
            }
        }
        
        return ['allowed' => true, 'message' => 'Action allowed'];
    }
    
    /**
     * Get action limits from plan features
     */
    private function getActionLimits($actionType) {
        // You can define limits based on plan features
        $limitMap = [
            'order_sync' => $this->user_plan['orders_limit'] ?? self::FREE_PLAN_ORDERS_LIMIT,
            'order_confirm' => $this->user_plan['orders_limit'] ?? self::FREE_PLAN_ORDERS_LIMIT,
            'api_call' => 1000,
            'whatsapp_send' => 500,
            'agent_assign' => 100
        ];
        
        return $limitMap[$actionType] ?? null;
    }
    
    /**
     * Get current usage for action
     */
    private function getActionUsage($actionType) {
        $result = $this->db->getThisQuery(
            "SELECT SUM(action_count) as total 
             FROM user_usage_log 
             WHERE user_id = ? 
             AND action_type = ? 
             AND action_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            [$this->user_id, $actionType]
        );
        
        return $result[0]['total'] ?? 0;
    }
    
    /**
     * Log user action
     */
    public function logAction($actionType, $orderId = null, $count = 1) {
        $logData = [
            'user_id' => $this->user_id,
            'action_type' => $actionType,
            'action_count' => $count,
            'order_id' => $orderId,
            'action_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('user_usage_log', $logData);
        
        // Update orders used count
        if ($actionType === 'order_sync' || $actionType === 'order_confirm') {
            $this->updateOrderUsage($count);
        }
        
        return true;
    }
    
    /**
     * Update order usage for user
     */
    private function updateOrderUsage($count = 1) {
        $this->db->update_this(
            "UPDATE user_plans 
             SET orders_used = orders_used + ?,
                 orders_remaining = orders_remaining - ?
             WHERE user_id = ? AND status IN ('active', 'trial')",
            [$count, $count, $this->user_id]
        );
    }
    
    /**
     * Get user's plan information
     */
    public function getUserPlan() {
        return $this->user_plan;
    }
    
    /**
     * Get plan usage statistics
     */
    public function getPlanStats() {
        $stats = [
            'total_orders_limit' => $this->user_plan['orders_limit'] ?? self::FREE_PLAN_ORDERS_LIMIT,
            'orders_used' => $this->user_plan['orders_used'] ?? 0,
            'orders_remaining' => $this->user_plan['orders_remaining'] ?? self::FREE_PLAN_ORDERS_LIMIT,
            'plan_status' => $this->user_plan['status'] ?? 'no_plan',
            'plan_name' => $this->user_plan['plan_name'] ?? 'Free Trial',
            'expires_at' => $this->user_plan['end_date'] ?? null,
            'trial_ends_at' => $this->user_plan['trial_end_date'] ?? null,
            'is_trial' => $this->user_plan['status'] === 'trial'
        ];
        
        return $stats;
    }
    
    /**
     * Get available plans for upgrade
     */
    public function getAvailablePlans() {
        return $this->db->getThisQuery(
            "SELECT * FROM plans WHERE is_active = 1 ORDER BY price ASC",
            []
        );
    }
    
    /**
     * Upgrade user plan
     */
    public function upgradePlan($newPlanId, $paymentData = null) {
        // Get new plan details
        $newPlan = $this->db->getThisQuery(
            "SELECT * FROM plans WHERE id = ? AND is_active = 1",
            [$newPlanId]
        );
        
        if (empty($newPlan)) {
            return ['success' => false, 'message' => 'Plan not found'];
        }
        
        $plan = $newPlan[0];
        
        // Calculate end date
        $endDate = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Update user plan
        $this->db->update(
            'user_plans',
            [
                'plan_id' => $plan['id'],
                'status' => 'active',
                'end_date' => $endDate,
                'orders_limit' => $plan['orders_limit'],
                'orders_used' => 0,
                'orders_remaining' => $plan['orders_limit'],
                'monthly_price' => $plan['price'],
                'auto_renew' => 1,
                'renewal_date' => $endDate,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            "user_id = ? AND status IN ('active', 'trial')",
            [$this->user_id]
        );
        
        // Log transaction if payment data provided
        if ($paymentData) {
            $this->logTransaction($plan['price'], $plan['id'], $paymentData);
        }
        
        // Reload plan
        $this->loadUserPlan();
        
        return ['success' => true, 'message' => 'Plan upgraded successfully'];
    }
    
    /**
     * Log subscription transaction
     */
    private function logTransaction($amount, $planId, $paymentData) {
        $transactionData = [
            'user_id' => $this->user_id,
            'user_plan_id' => $this->user_plan['id'] ?? null,
            'transaction_id' => $paymentData['transaction_id'] ?? 'TXN_' . uniqid(),
            'amount' => $amount,
            'currency' => $paymentData['currency'] ?? 'MAD',
            'payment_method' => $paymentData['method'] ?? 'card',
            'status' => 'completed',
            'description' => 'Subscription payment for plan ID: ' . $planId,
            'gateway_response' => json_encode($paymentData),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('subscription_transactions', $transactionData);
    }
    
    /**
     * Check and handle expired plans
     */
    public function checkExpiredPlans() {
        $expiredPlans = $this->db->getThisQuery(
            "SELECT up.*, u.email, u.name 
             FROM user_plans up
             JOIN users u ON up.user_id = u.id
             WHERE up.status IN ('active', 'trial')
             AND up.end_date < NOW()",
            []
        );
        
        foreach ($expiredPlans as $plan) {
            // Update plan status to expired
            $this->db->update(
                'user_plans',
                ['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')],
                "id = ?",
                [$plan['id']]
            );
            
            // Send notification to user
            $this->sendPlanExpiredNotification($plan);
        }
        
        return count($expiredPlans);
    }
    
    /**
     * Send plan expired notification
     */
    private function sendPlanExpiredNotification($plan) {
        // You can implement email/SMS notification here
        error_log("Plan expired for user: " . $plan['email']);
    }
    
    /**
     * Reset trial usage for expired trials
     */
    public function resetTrialUsage() {
        // You might want to implement this for when users come back
    }
}