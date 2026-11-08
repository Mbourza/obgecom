<?php 
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Phone verification check
if (!isset($_SESSION['phone_verified']) || !$_SESSION['phone_verified']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Phone verification required']);
    exit;
}

if (!isset($_SESSION['verified_at']) || (time() - $_SESSION['verified_at']) > 86400) {
    unset($_SESSION['phone_verified'], $_SESSION['verified_phone'], $_SESSION['verified_at']);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Phone verification expired']);
    exit;
}

$verified_phone = $_SESSION['verified_phone'] ?? '';
if (empty($verified_phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Verified phone not found']);
    exit;
}

$verified_phone_local = str_replace('+212', '0', $verified_phone);
$db = DB::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

try {
    // Validate required fields
    $required = ['name', 'email', 'password', 'orders'];
    $missing = array_diff($required, array_keys($data));
    if (!empty($missing)) {
        throw new Exception("Required fields missing: " . implode(', ', $missing));
    }
    
    $email = trim($data['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address");
    }
    
    // Check existing user
    $existing = $db->getThisQuery("SELECT id FROM users WHERE email = ?", [$email]);
    if (!empty($existing)) {
        throw new Exception('Email already registered');
    }
    
    $existingPhone = $db->getThisQuery("SELECT id FROM users WHERE phone = ?", [$verified_phone_local]);
    if (!empty($existingPhone)) {
        throw new Exception('Phone number already registered');
    }
    
    // Get quota details
    $orders = (int)$data['orders'];
    $isFree = $orders <= 100;
    
    // Get plan ID
    $planKey = $isFree ? 'starter' : 'payg';
    $planResult = $db->getThisQuery("SELECT id FROM plans WHERE LOWER(name) = ?", [strtolower($planKey)]);
    if (empty($planResult)) {
        // Create default plan if not exists
        $planId = $db->insert('plans', [
            'name' => $planKey === 'starter' ? 'Starter' : 'Pay as you go',
            'price' => $isFree ? 0 : 0.80,
            'orders_limit' => $orders,
            'is_free' => $isFree ? 1 : 0,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $planId = $planResult[0]['id'];
    }
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Calculate subscription
    $duration = 1;
    $monthly_price = $isFree ? 0 : ($orders - 100) * 0.80;
    $total_amount = $monthly_price * $duration;
    
    $subscription_end_date = date('Y-m-d H:i:s', strtotime("+$duration months"));
    
    // Prepare user data
    $user_data = [
        'name' => $data['name'],
        'email' => $email,
        'password' => $hashed_password,
        'phone' => $verified_phone_local,
        'role' => 'admin',
        'plan_id' => $planId,
        'is_active' => $isFree ? 1 : 0,
        'active' => $isFree ? 1 : 0,
        'status' => $isFree ? 'active' : 'pending_payment',
        'subscription_duration' => $duration,
        'monthly_price' => $monthly_price,
        'total_amount' => $total_amount,
        'subscription_end_date' => $subscription_end_date,
        'phone_verified' => 1,
        'phone_verified_at' => date('Y-m-d H:i:s'),
        'verified_method' => $_SESSION['verified_method'] ?? 'sms',
        'created_at' => date('Y-m-d H:i:s'),
        'added' => date('Y-m-d H:i:s')
    ];
    
    $user_result = $db->insert('users', $user_data);
   
    if (!$user_result) {
        throw new Exception('Error creating user');
    }
    
    $user_id = $db->getLastInsertId();
    
    // Create quota for user
    $currentMonth = date('Y-m-01');
    $quotaData = [
        'user_id' => $user_id,
        'quota_month' => $currentMonth,
        'total_quota' => $orders,
        'orders_used' => 0,
        'orders_remaining' => $orders,
        'rollover_from_previous' => 0,
        'is_free_trial' => $isFree ? 1 : 0,
        'paid_amount' => $total_amount,
        'payment_status' => $isFree ? 'paid' : 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $db->insert('user_quotas', $quotaData);
    $quotaId = $db->getLastInsertId();
    
    // Create user plan record
    $plan_data = [
        'user_id' => $user_id,
        'plan_id' => $planId,
        'duration_months' => $duration,
        'monthly_price' => $monthly_price,
        'total_amount' => $total_amount,
        'status' => $isFree ? 'active' : 'pending',
        'activated_at' => $isFree ? date('Y-m-d H:i:s') : null,
        'expires_at' => $subscription_end_date,
        'created_at' => date('Y-m-d H:i:s')
    ];
    $db->insert('user_plans', $plan_data);
    
    // If not free, create transaction
    $transactionId = null;
    if (!$isFree && $total_amount > 0) {
        $txnId = 'QTA_' . uniqid() . '_' . time();
        $transactionData = [
            'user_id' => $user_id,
            'quota_id' => $quotaId,
            'transaction_id' => $txnId,
            'order_count' => $orders - 100,
            'amount' => $total_amount,
            'price_per_order' => 0.80,
            'currency' => 'MAD',
            'payment_method' => 'card',
            'status' => 'pending',
            'description' => "Quota purchase: {$orders} orders",
            'created_at' => date('Y-m-d H:i:s')
        ];
        $db->insert('quota_transactions', $transactionData);
        $transactionId = $txnId;
    }
    
    // Clear verification session
    unset($_SESSION['phone_verified'], $_SESSION['verified_phone'], $_SESSION['verified_at']);
    
    // Log success
    error_log("User created: ID $user_id, Email: $email, Orders: $orders, Free: " . ($isFree ? 'Yes' : 'No'));
    
    echo json_encode([
        'success' => true,
        'user_id' => $user_id,
        'email' => $email,
        'phone' => $verified_phone_local,
        'orders' => $orders,
        'is_free' => $isFree,
        'total_amount' => $total_amount,
        'transaction_id' => $transactionId,
        'message' => $isFree ? 'Free account created successfully!' : 'Account created. Please complete payment.'
    ]);
    
} catch (Exception $e) {
    error_log("Error in create_user.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>