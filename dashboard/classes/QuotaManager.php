<?php
// classes/QuotaManager.php

class QuotaManager {
    private $db;
    private $user_id;
    private $currentQuota;
    private $cache = [];
    
    // Pricing configuration
    const PRICE_PER_ORDER = 0.50; // €0.50 per order
    const FREE_QUOTA_LIMIT = 100;
    
    public function __construct($db, $user_id = null) {
        $this->db = $db;
        $this->user_id = $user_id;
        if ($user_id) {
            $this->loadCurrentQuota();
        }
    }
    
    /**
     * Load current month's quota for user
     */
    private function loadCurrentQuota() {
        $currentMonth = date('Y-m-01');
        
        $quota = $this->db->getThisQuery(
            "SELECT * FROM user_quotas 
             WHERE user_id = ? AND quota_month = ? 
             LIMIT 1",
            [$this->user_id, $currentMonth]
        );
        
        if (!empty($quota)) {
            $this->currentQuota = $quota[0];
        } else {
            // No quota for this month - create one
            $this->createMonthlyQuota();
        }
    }
    
    /**
     * Create monthly quota for user
     */
    private function createMonthlyQuota() {
        $currentMonth = date('Y-m-01');
        
        // Check if user has rollover from previous month
        $rollover = $this->calculateRollover();
        
        // Check if this is a new user (first month)
        $isNewUser = $this->isNewUser();
        $freeQuota = $isNewUser ? self::FREE_QUOTA_LIMIT : 0;
        
        $totalQuota = $freeQuota + $rollover;
        
        $quotaData = [
            'user_id' => $this->user_id,
            'quota_month' => $currentMonth,
            'total_quota' => $totalQuota,
            'orders_used' => 0,
            'orders_remaining' => $totalQuota,
            'rollover_from_previous' => $rollover,
            'is_free_trial' => $isNewUser ? 1 : 0,
            'paid_amount' => 0,
            'payment_status' => 'paid',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('user_quotas', $quotaData);
        
        // Log the creation
        if ($isNewUser) {
            $this->logHistory('initial_quota', self::FREE_QUOTA_LIMIT, 0, $totalQuota, "Initial free quota for new user");
        }
        
        if ($rollover > 0) {
            $this->logHistory('monthly_rollover', $rollover, 0, $totalQuota, "Rollover from previous month: {$rollover} orders");
        }
        
        // Reload quota
        $this->loadCurrentQuota();
    }
    
    /**
     * Calculate rollover from previous month
     */
    private function calculateRollover() {
        $previousMonth = date('Y-m-01', strtotime('-1 month'));
        
        $previousQuota = $this->db->getThisQuery(
            "SELECT total_quota, orders_used FROM user_quotas 
             WHERE user_id = ? AND quota_month = ? 
             LIMIT 1",
            [$this->user_id, $previousMonth]
        );
        
        if (empty($previousQuota)) {
            return 0;
        }
        
        $quota = $previousQuota[0];
        $remaining = $quota['total_quota'] - $quota['orders_used'];
        
        // Max rollover is 100% of the quota (you can adjust this)
        return max(0, $remaining);
    }
    
    /**
     * Check if user is new (first month)
     */
    private function isNewUser() {
        $count = $this->db->getThisQuery(
            "SELECT COUNT(*) as total FROM user_quotas WHERE user_id = ?",
            [$this->user_id]
        );
        
        return $count[0]['total'] == 0;
    }
    
    /**
     * Check if user can process an order
     */
    public function canProcessOrder($orderId = null) {
        if (!$this->currentQuota) {
            return ['allowed' => false, 'message' => 'No active quota found'];
        }
        
        if ($this->currentQuota['orders_remaining'] <= 0) {
            return [
                'allowed' => false, 
                'message' => 'You have used all your orders for this month. Please purchase additional quota.',
                'remaining' => 0,
                'total_quota' => $this->currentQuota['total_quota']
            ];
        }
        
        return [
            'allowed' => true, 
            'message' => 'OK',
            'remaining' => $this->currentQuota['orders_remaining'],
            'total_quota' => $this->currentQuota['total_quota']
        ];
    }
    
    /**
     * Use an order from quota
     */
    public function useOrder($orderId = null) {
        $check = $this->canProcessOrder($orderId);
        if (!$check['allowed']) {
            return ['success' => false, 'message' => $check['message']];
        }
        
        // Update quota
        $this->db->update_this(
            "UPDATE user_quotas 
             SET orders_used = orders_used + 1,
                 orders_remaining = orders_remaining - 1,
                 updated_at = NOW()
             WHERE user_id = ? AND quota_month = ?",
            [$this->user_id, $this->currentQuota['quota_month']]
        );
        
        // Log usage
        $this->logHistory(
            'use_order', 
            1, 
            $this->currentQuota['orders_remaining'], 
            $this->currentQuota['orders_remaining'] - 1,
            "Order #{$orderId} processed"
        );
        
        // Reload quota
        $this->loadCurrentQuota();
        
        return [
            'success' => true,
            'remaining' => $this->currentQuota['orders_remaining'],
            'used' => $this->currentQuota['orders_used'],
            'total' => $this->currentQuota['total_quota']
        ];
    }
    
    /**
     * Purchase additional quota
     */
    public function purchaseQuota($orderCount) {
        if ($orderCount <= 0) {
            return ['success' => false, 'message' => 'Order count must be positive'];
        }
        
        $amount = $orderCount * self::PRICE_PER_ORDER;
        
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // Update quota
            $this->db->update_this(
                "UPDATE user_quotas 
                 SET total_quota = total_quota + ?,
                     orders_remaining = orders_remaining + ?,
                     paid_amount = paid_amount + ?,
                     updated_at = NOW()
                 WHERE user_id = ? AND quota_month = ?",
                [
                    $orderCount,
                    $orderCount,
                    $amount,
                    $this->user_id,
                    $this->currentQuota['quota_month']
                ]
            );
            
            // Create transaction record
            $transactionData = [
                'user_id' => $this->user_id,
                'quota_id' => $this->currentQuota['id'],
                'transaction_id' => 'QTA_' . uniqid() . '_' . time(),
                'order_count' => $orderCount,
                'amount' => $amount,
                'price_per_order' => self::PRICE_PER_ORDER,
                'currency' => 'MAD',
                'payment_method' => 'card', // This should come from payment gateway
                'status' => 'pending',
                'description' => "Purchase of {$orderCount} additional orders",
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('quota_transactions', $transactionData);
            $transactionId = $this->db->lastInsertId();
            
            // Log history
            $remaining = $this->currentQuota['orders_remaining'] + $orderCount;
            $this->logHistory(
                'purchase_quota',
                $orderCount,
                $this->currentQuota['orders_remaining'],
                $remaining,
                "Purchased {$orderCount} additional orders for {$amount} MAD"
            );
            
            $this->db->commit();
            
            // Reload quota
            $this->loadCurrentQuota();
            
            return [
                'success' => true,
                'message' => 'Quota purchased successfully',
                'transaction_id' => $transactionData['transaction_id'],
                'amount' => $amount,
                'new_remaining' => $this->currentQuota['orders_remaining'],
                'total_quota' => $this->currentQuota['total_quota']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => 'Failed to purchase quota: ' . $e->getMessage()];
        }
    }
    
    /**
     * Confirm payment and activate quota
     */
    public function confirmPayment($transactionId) {
        $transaction = $this->db->getThisQuery(
            "SELECT * FROM quota_transactions WHERE transaction_id = ? AND user_id = ?",
            [$transactionId, $this->user_id]
        );
        
        if (empty($transaction)) {
            return ['success' => false, 'message' => 'Transaction not found'];
        }
        
        $trans = $transaction[0];
        
        if ($trans['status'] === 'completed') {
            return ['success' => true, 'message' => 'Transaction already completed'];
        }
        
        // Update transaction status
        $this->db->update(
            'quota_transactions',
            [
                'status' => 'completed',
                'updated_at' => date('Y-m-d H:i:s')
            ],
            "transaction_id = ?",
            [$transactionId]
        );
        
        // Update quota payment status if not already
        if ($this->currentQuota && $this->currentQuota['payment_status'] !== 'paid') {
            $this->db->update(
                'user_quotas',
                ['payment_status' => 'paid', 'updated_at' => date('Y-m-d H:i:s')],
                "id = ?",
                [$this->currentQuota['id']]
            );
        }
        
        return ['success' => true, 'message' => 'Payment confirmed'];
    }
    
    /**
     * Get quota statistics
     */
    public function getQuotaStats() {
        if (!$this->currentQuota) {
            return [
                'total_quota' => 0,
                'orders_used' => 0,
                'orders_remaining' => 0,
                'rollover' => 0,
                'is_free_trial' => false,
                'quota_month' => date('Y-m-01')
            ];
        }
        
        return [
            'total_quota' => (int)$this->currentQuota['total_quota'],
            'orders_used' => (int)$this->currentQuota['orders_used'],
            'orders_remaining' => (int)$this->currentQuota['orders_remaining'],
            'rollover' => (int)$this->currentQuota['rollover_from_previous'],
            'is_free_trial' => (bool)$this->currentQuota['is_free_trial'],
            'quota_month' => $this->currentQuota['quota_month'],
            'paid_amount' => (float)$this->currentQuota['paid_amount'],
            'usage_percentage' => $this->currentQuota['total_quota'] > 0 
                ? round(($this->currentQuota['orders_used'] / $this->currentQuota['total_quota']) * 100, 2)
                : 0
        ];
    }
    
    /**
     * Get quota history
     */
    public function getQuotaHistory($limit = 30) {
        return $this->db->getThisQuery(
            "SELECT * FROM user_quota_history 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?",
            [$this->user_id, $limit]
        );
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($limit = 20) {
        return $this->db->getThisQuery(
            "SELECT * FROM quota_transactions 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?",
            [$this->user_id, $limit]
        );
    }
    
    /**
     * Log history entry
     */
    private function logHistory($actionType, $orderCount, $previousRemaining, $newRemaining, $description = null) {
        $historyData = [
            'user_id' => $this->user_id,
            'action_type' => $actionType,
            'order_count' => $orderCount,
            'previous_remaining' => $previousRemaining,
            'new_remaining' => $newRemaining,
            'reference_id' => $this->currentQuota['id'] ?? null,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->db->insert('user_quota_history', $historyData);
    }
    
    /**
     * Process monthly rollover (should be run by cron)
     */
    public static function processMonthlyRollover($db) {
        $currentMonth = date('Y-m-01');
        $previousMonth = date('Y-m-01', strtotime('-1 month'));
        
        // Get all users with quota from previous month
        $users = $db->getThisQuery(
            "SELECT DISTINCT user_id FROM user_quotas 
             WHERE quota_month = ? AND (total_quota - orders_used) > 0",
            [$previousMonth]
        );
        
        $processed = 0;
        
        foreach ($users as $user) {
            $userId = $user['user_id'];
            
            // Check if user already has quota for current month
            $exists = $db->getThisQuery(
                "SELECT COUNT(*) as total FROM user_quotas 
                 WHERE user_id = ? AND quota_month = ?",
                [$userId, $currentMonth]
            );
            
            if ($exists[0]['total'] > 0) {
                continue; // Already has current month quota
            }
            
            // Create new quota with rollover
            $manager = new self($db, $userId);
            // This will automatically calculate rollover
            $manager->createMonthlyQuota();
            $processed++;
        }
        
        return $processed;
    }
    
    /**
     * Get price per order
     */
    public static function getPricePerOrder() {
        return self::PRICE_PER_ORDER;
    }
    
    /**
     * Get free quota limit
     */
    public static function getFreeQuotaLimit() {
        return self::FREE_QUOTA_LIMIT;
    }
} ?>