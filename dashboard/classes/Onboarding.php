<?php
class Onboarding {
    private $db;
    private $user_id;
    private $user_role;
    
    public function __construct($user_id) {
        $this->db = DB::getInstance();
        $this->user_id = $user_id;
        $this->initializeOnboarding();
        $this->user_role = $this->getUserRole();
    }
    
    private function getUserRole() {
        $user = $this->db->getThisQuery(
            "SELECT role FROM users WHERE id = ?",
            [$this->user_id]
        );
        
        return $user[0]['role'] ?? null;
    }
    
    private function initializeOnboarding() {
        // Check if onboarding record exists
        $check = $this->db->getThisQuery(
            "SELECT id FROM user_onboarding WHERE user_id = ?",
            [$this->user_id]
        );
        
        if (empty($check)) {
            // Create onboarding record
            $this->db->insert('user_onboarding', [
                'user_id' => $this->user_id,
                'step_store' => false,
                'step_shipping' => false,
                'completed' => false
            ]);
        }
    }
    
    public function getOnboardingStatus() {
        // If user is super admin, return completed status
        if ($this->user_role === 'super') {
            return [
                'step_store' => true,
                'step_shipping' => true,
                'completed' => true
            ];
        }
        
        return $this->db->getThisQuery(
            "SELECT * FROM user_onboarding WHERE user_id = ?",
            [$this->user_id]
        )[0] ?? null;
    }
    
    public function completeStep($step) {
        // Skip for super admins
        if ($this->user_role === 'super') {
            return true;
        }
        
        $valid_steps = ['step_store', 'step_shipping'];
        
        if (in_array($step, $valid_steps)) {
            $this->db->update('user_onboarding', ['user_id' => $this->user_id], [
                $step => true,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Check if all steps are completed
            $this->checkCompletion();
            
            return true;
        }
        
        return false;
    }
    
    private function checkCompletion() {
        // Skip for super admins
        if ($this->user_role === 'super') {
            return;
        }
        
        $status = $this->getOnboardingStatus();
        
        if ($status && $status['step_store'] && $status['step_shipping']) {
            $this->db->update('user_onboarding', ['user_id' => $this->user_id], [
                'completed' => true,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    public function isOnboardingCompleted() {
        // Bypass for super admins
        if ($this->user_role === 'super') {
            return true;
        }
        
        $status = $this->getOnboardingStatus();
        return $status && $status['completed'];
    }
    
    public function getNextStep() {
        // Bypass for super admins
        if ($this->user_role === 'super') {
            return 'completed';
        }

        $status = $this->getOnboardingStatus();
        
        if (!$status['step_store']) {
            return 'store';
        } elseif (!$status['step_shipping']) {
            return 'shipping';
        } else {
            return 'completed';
        }
    }
    
    public function getProgress() {
        // Bypass for super admins - show 100% completion
        if ($this->user_role === 'super') {
            return [
                'completed' => 2,
                'total' => 2,
                'percentage' => 100
            ];
        }

        $status = $this->getOnboardingStatus();
        $completed = 0;
        $total = 2; 
        
        if ($status['step_store']) $completed++;
        if ($status['step_shipping']) $completed++;
        
        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => round(($completed / $total) * 100)
        ];
    }
    
    // Check if user has at least one connected store
    public function hasConnectedStore() {
        $stores = $this->db->getThisQuery(
            "SELECT COUNT(*) as store_count FROM stores WHERE user_id = ? AND is_connected = 1",
            [$this->user_id]
        );
        
        return $stores[0]['store_count'] > 0;
    }
    
    // Check if user has at least one shipping company
    public function hasShippingCompany() {
        $shipping = $this->db->getThisQuery(
            "SELECT COUNT(*) as shipping_count FROM user_shipping_settings WHERE user_id = ?",
            [$this->user_id]
        );
        
        return $shipping[0]['shipping_count'] > 0;
    }
}
?>