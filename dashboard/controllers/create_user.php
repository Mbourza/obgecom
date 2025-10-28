<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ========== EMAIL VERIFICATION CHECK ==========
if (!isset($_SESSION['email_verified']) || !$_SESSION['email_verified']) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Email verification required. Please verify your email address first.'
    ]);
    exit;
}

// Check if verification is not expired (24 hours)
if (!isset($_SESSION['email_verified_at']) || (time() - $_SESSION['email_verified_at']) > 86400) {
    unset($_SESSION['email_verified']);
    unset($_SESSION['verified_email']);
    unset($_SESSION['email_verified_at']);
    
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Email verification expired. Please verify your email address again.'
    ]);
    exit;
}

// Get verified email from session
$verified_email = $_SESSION['verified_email'] ?? '';
// ========== END EMAIL VERIFICATION CHECK ==========

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
    $required_fields = ['name', 'password', 'selectedPlan'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception("Champs requis manquants: " . implode(', ', $missing_fields));
    }
    
    // Check if email already exists (using verified email from session)
    $existing_user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE email = ?
    ", [$verified_email]);
    
    if (!empty($existing_user)) {
        throw new Exception('Email déjà utilisé');
    }
    
    // Get plan ID
    $plan_id = getPlanId($db, $data['selectedPlan']);
    
    if (!$plan_id) {
        throw new Exception('Plan sélectionné invalide');
    }
    
    // Handle duration (default to 1 month if not provided)
    $duration = isset($data['duration']) ? intval($data['duration']) : 1;
    $monthly_price = isset($data['monthlyPrice']) ? floatval($data['monthlyPrice']) : 0;
    $total_amount = isset($data['totalAmount']) ? floatval($data['totalAmount']) : 0;
    $discount = isset($data['discount']) ? floatval($data['discount']) : 0;
    
    // Calculate subscription end date based on duration
    $subscription_end_date = date('Y-m-d H:i:s', strtotime("+$duration months"));
    
    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Prepare user data - USE VERIFIED EMAIL FROM SESSION
    $user_data = [
        'name' => $data['name'],
        'email' => $verified_email, // Use verified email from session instead of request data
        'password' => $hashed_password,
        'role' => isset($data['role']) ? $data['role'] : 'user',
        'phone' => $data['phone'] ?? '', // Phone is optional now
        'plan_id' => $plan_id,
        'is_active' => 0,
        'active' => 0,
        'is_verified' => 1, // Mark as verified since we already verified via email
        'email_verified_at' => date('Y-m-d H:i:s'),
        'activation_key' => isset($data['activation_key']) ? $data['activation_key'] : '',
        'status' => 'pending_payment',
        'subscription_duration' => $duration,
        'monthly_price' => $monthly_price,
        'total_amount' => $total_amount,
        'discount_applied' => $discount,
        'subscription_end_date' => $subscription_end_date,
        'created_at' => date('Y-m-d H:i:s'),
        'added' => date('Y-m-d H:i:s')
    ];
    
    // Insert user using DB class method
    $user_result = $db->insert('users', $user_data);
    
    if ($user_result) {
        $user_id = $db->getLastInsertId();
        
        // Store selected plan in user_plans table with duration information
        $plan_data = [
            'user_id' => $user_id,
            'plan_id' => $plan_id,
            'duration_months' => $duration,
            'monthly_price' => $monthly_price,
            'total_amount' => $total_amount,
            'discount' => $discount,
            'status' => 'pending',
            'activated_at' => date('Y-m-d H:i:s'),
            'expires_at' => $subscription_end_date,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $plan_result = $db->insert('user_plans', $plan_data);
        
        if ($plan_result) {
            // ========== CLEAR VERIFICATION SESSION AFTER SUCCESSFUL USER CREATION ==========
            unset($_SESSION['email_verified']);
            unset($_SESSION['verified_email']);
            unset($_SESSION['email_verified_at']);
            // ========== END CLEAR VERIFICATION SESSION ==========
            
            echo json_encode([
                'success' => true,
                'user_id' => $user_id,
                'duration' => $duration,
                'monthly_price' => $monthly_price,
                'total_amount' => $total_amount,
                'discount' => $discount,
                'subscription_end_date' => $subscription_end_date,
                'message' => 'Utilisateur créé avec succès'
            ]);
        } else {
            throw new Exception('Erreur lors de l\'association du plan à l\'utilisateur');
        }
        
    } else {
        throw new Exception('Erreur lors de la création de l\'utilisateur');
    }
    
} catch (Exception $e) {
    error_log("Error in create_user.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getPlanId($db, $plan_key) {
    // Convert plan_key to lowercase
    $plan_key = strtolower($plan_key);

    $plan_names = [
        'starter' => 'Starter',
        'professional' => 'Professional',
        'growth' => 'Growth',
        'business' => 'Business'
    ];
    
    if (!isset($plan_names[$plan_key])) {
        return false;
    }
    
    $result = $db->getThisQuery("SELECT id FROM plans WHERE name = ?", [$plan_names[$plan_key]]);
    
    return !empty($result) ? $result[0]['id'] : false;
}
?>