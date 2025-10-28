<?php  
if (file_exists(stream_resolve_include_path("../core/init.php"))) {     
    require_once("../core/init.php"); 
}  

header('Content-Type: application/json');  

$db = DB::getInstance();  

// Check if request is GET 
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {     
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);     
    exit; 
}  

try {
    // Get user_id from URL parameter or from session
    $user_id = null;
    
    if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
        $user_id = (int)$_GET['user_id'];
    } else {
        $user_id = getCurrentUserId($db);
    }
    
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID not provided or user not authenticated'
        ]);
        exit;
    }
    
    // Get basic user information
    $user_query = "
        SELECT 
            id,
            username,
            email,
            name,
            phone,
            evaluation_end_date,
            plan_id,
            created_at,
            updated_at
        FROM users 
        WHERE id = ?
    ";
    
    $user_result = $db->getThisQuery($user_query, [$user_id]);
    
    if (empty($user_result)) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    $user_data = $user_result[0];

    // Get user's current plan from users.plan_id
    $subscription_query = "
        SELECT 
            u.id AS user_id,
            u.plan_id,
            u.name AS user_name,
            u.username,
            u.email,
            u.phone,
            p.name AS plan_name,
            p.price AS plan_price,
            p.is_custom,
            p.created_at AS plan_created_at
        FROM users u
        INNER JOIN plans p ON u.plan_id = p.id
        WHERE u.id = ?
        LIMIT 1
    ";
    
    // Fallback: latest record from user_plans
    $user_plan_query = "
        SELECT 
            up.id AS user_plan_id,
            up.user_id,
            up.plan_id,
            up.status AS plan_status,
            up.activated_at,
            up.expires_at,
            p.name AS plan_name,
            p.price AS plan_price,
            p.is_custom,
            p.created_at AS plan_created_at
        FROM user_plans up
        INNER JOIN plans p ON up.plan_id = p.id
        WHERE up.user_id = ?
        ORDER BY up.activated_at DESC
        LIMIT 1
    ";
    
    $subscription_result = $db->getThisQuery($subscription_query, [$user_id]);
    $user_plan_result   = $db->getThisQuery($user_plan_query, [$user_id]);
    
    // Get latest payment attempts
    $payment_attempts_query = "
        SELECT 
            id,
            user_id,
            transaction_id,
            amount,
            currency,
            plan_id,
            payment_method,
            status,
            proc_return_code,
            gateway_response,
            created_at,
            updated_at
        FROM payment_attempts
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ";
    
    $payment_attempts_result = $db->getThisQuery($payment_attempts_query, [$user_id]);
    
    // Get plan limits
    $plan_limits = null;
    $current_plan_id = null;
    
    if (!empty($subscription_result)) {
        $current_plan_id = $subscription_result[0]['plan_id'];
    } elseif (!empty($user_plan_result)) {
        $current_plan_id = $user_plan_result[0]['plan_id'];
    }
    
    if ($current_plan_id) {
        $limits_query = "
            SELECT 
                limit_key,
                limit_value
            FROM plan_limits
            WHERE plan_id = ?
        ";
        
        $limits_result = $db->getThisQuery($limits_query, [$current_plan_id]);
        
        if (!empty($limits_result)) {
            $plan_limits = [];
            foreach ($limits_result as $limit) {
                $plan_limits[$limit['limit_key']] = $limit['limit_value'];
            }
        }
    }
    
    // Prepare response data
    $response_data = [
        'success' => true,
        'id' => (int)$user_data['id'],
        'username' => $user_data['username'],
        'email' => $user_data['email'],
        'name' => $user_data['name'] ?? $user_data['username'],
        'phone' => $user_data['phone'],
        'evaluation_end_date' => $user_data['evaluation_end_date'],
        'created_at' => $user_data['created_at'],
        'updated_at' => $user_data['updated_at']
    ];    
    
    // Add subscription/plan info
    $subscription_data = null;
    $plan_id_to_use = null;
    
    if (!empty($subscription_result)) {
        $sub = $subscription_result[0];
        
        $subscription_data = [
            'plan_id' => (int)$sub['plan_id'],
            'plan_name' => $sub['plan_name'],
            'plan_price' => $sub['plan_price'],
            'is_custom' => (bool)$sub['is_custom'],
            'plan_created_at' => $sub['plan_created_at']
        ];
        
        $plan_id_to_use = (int)$sub['plan_id'];
    } elseif (!empty($user_plan_result)) {
        $up = $user_plan_result[0];
        
        $subscription_data = [
            'user_plan_id' => (int)$up['user_plan_id'],
            'plan_id' => (int)$up['plan_id'],
            'status' => $up['plan_status'],
            'activated_at' => $up['activated_at'],
            'expires_at' => $up['expires_at'],
            'plan_name' => $up['plan_name'],
            'plan_price' => $up['plan_price'],
            'is_custom' => (bool)$up['is_custom'],
            'plan_created_at' => $up['plan_created_at']
        ];
        
        $plan_id_to_use = (int)$up['plan_id'];
    }
    
    if ($subscription_data) {
        $response_data['subscription'] = $subscription_data;
        
        // Add plan limits if available
        if ($plan_limits) {
            $response_data['subscription']['limits'] = $plan_limits;
        }
        
        // Set plan_id for backwards compatibility
        $response_data['plan_id'] = $plan_id_to_use;
    } else {
        $response_data['subscription'] = null;
        $response_data['plan_id'] = null;
    }
    
    // Add payment attempts
    if (!empty($payment_attempts_result)) {
        $response_data['payment_attempts'] = [];
        
        foreach ($payment_attempts_result as $attempt) {
            $response_data['payment_attempts'][] = [
                'id' => (int)$attempt['id'],
                'transaction_id' => $attempt['transaction_id'],
                'amount' => $attempt['amount'],
                'currency' => $attempt['currency'],
                'plan_id' => $attempt['plan_id'] ? (int)$attempt['plan_id'] : null,
                'payment_method' => $attempt['payment_method'],
                'status' => $attempt['status'],
                'proc_return_code' => $attempt['proc_return_code'],
                'gateway_response' => $attempt['gateway_response'],
                'created_at' => $attempt['created_at'],
                'updated_at' => $attempt['updated_at']
            ];
        }
        
        // Latest attempt shortcut
        $latest = $payment_attempts_result[0];
        $response_data['latest_payment_attempt'] = [
            'status' => $latest['status'],
            'transaction_id' => $latest['transaction_id'],
            'amount' => $latest['amount'],
            'created_at' => $latest['created_at']
        ];
    } else {
        $response_data['payment_attempts'] = [];
        $response_data['latest_payment_attempt'] = null;
    }
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    error_log("Error in get_user_dataApi.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching user data'
    ]);
}

/**
 * Get current user ID from session
 */
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    // This can be username, email, or phone
    $identifier = $_SESSION['user']['username'];

    // Prefer explicit ID if available
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Try to match identifier against username, email, or phone
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

    return null;
}

/**
 * Determine account status for activation page
 */
function determineAccountStatus($user_data, $subscription_result, $user_plan_result) {
    $current_date = new DateTime();
    
    // First check subscriptions table (active subscriptions)
    if (!empty($subscription_result)) {
        $subscription = $subscription_result[0];
        
        // Check subscription status
        if ($subscription['status'] === 'active') {
            // Check if subscription is expiring
            if ($subscription['expires_at']) {
                $expires_date = new DateTime($subscription['expires_at']);
                $days_until_expiry = $current_date->diff($expires_date)->days;
                
                if ($expires_date < $current_date) {
                    return 'expired';
                } else if ($days_until_expiry <= 7) {
                    return 'expiring_soon';
                } else {
                    return 'active';
                }
            } else {
                // No expiry date means lifetime or ongoing subscription
                return 'active';
            }
        } else if ($subscription['status'] === 'suspended') {
            return 'suspended';
        } else if ($subscription['status'] === 'cancelled' || $subscription['status'] === 'canceled') {
            return 'expired';
        } else {
            return 'inactive';
        }
    }
    
    // If no active subscription, check user_plans table
    if (!empty($user_plan_result)) {
        $user_plan = $user_plan_result[0];
        
        if ($user_plan['plan_status'] === 'active') {
            // Check if plan is expiring
            if ($user_plan['expires_at']) {
                $expires_date = new DateTime($user_plan['expires_at']);
                $days_until_expiry = $current_date->diff($expires_date)->days;
                
                if ($expires_date < $current_date) {
                    return 'expired';
                } else if ($days_until_expiry <= 7) {
                    return 'expiring_soon';
                } else {
                    return 'active';
                }
            } else {
                return 'active';
            }
        } else if ($user_plan['plan_status'] === 'suspended') {
            return 'suspended';
        } else if ($user_plan['plan_status'] === 'expired' || $user_plan['plan_status'] === 'cancelled') {
            return 'expired';
        } else if ($user_plan['plan_status'] === 'pending') {
            return 'inactive'; // Pending payment
        }
    }
    
    // No subscription or user plan found, check if trial period exists
    if ($user_data['evaluation_end_date']) {
        $trial_end_date = new DateTime($user_data['evaluation_end_date']);
        
        if ($current_date > $trial_end_date) {
            return 'trial_expired';
        } else {
            return 'trial_active';
        }
    }
    
    // Default to inactive if nothing else applies
    return 'inactive';
}

?>