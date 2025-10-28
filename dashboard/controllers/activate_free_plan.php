<?php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// api/activate_free_plan.php - Activate free plan for user
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    $required_fields = ['user_id', 'plan'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception("Champs requis manquants: " . implode(', ', $missing_fields));
    }
    
    $user_id = $data['user_id'];
    $plan_name = $data['plan'];
    
    // Verify user exists
    $user = $db->getThisQuery("SELECT id, email FROM users WHERE id = ?", [$user_id]);
    
    if (empty($user)) {
        throw new Exception('Utilisateur introuvable');
    }
    
    // Check if plan is actually free (gratuit)
    $plan_id = getPlanId($db, $plan_name);

    
    if (!$plan_id) {
        throw new Exception('Plan invalide');
    }
    
    // Verify it's a free plan
    $plan_info = $db->getThisQuery("SELECT id, name, price FROM plans WHERE id = ?", [$plan_id]);
    
    if (empty($plan_info) || $plan_info[0]['price'] > 0) {
        throw new Exception('Ce plan n\'est pas gratuit');
    }
    
    // Update user status to active
    $user_update_data = [
        'is_active' => 1,
        'active' => 1,
        'status' => 'active',
        'evaluation_end_date' => date('Y-m-d', strtotime('+30 days')),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    
    $user_update_result = $db->update('users', $user_id, $user_update_data);

    
    if (!$user_update_result) {
        throw new Exception('Erreur lors de la mise à jour de l\'utilisateur');
    }
    
    // Update user_plans status to active
    $plan_update_data = [
        'status' => 'active',
        'activated_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $plan_update_result = $db->updateByFields(
        'user_plans',
        [
            'user_id' => $user_id,
            'plan_id' => $plan_id
        ],
        $plan_update_data
    );
    
    if (!$plan_update_result) {
        throw new Exception('Erreur lors de l\'activation du plan');
    }
    
    // Optional: Create subscription record for free plan
    $subscription_data = [
        'user_id' => $user_id,
        'plan_id' => $plan_id,
        'status' => 'active',
        'started_at' => date('Y-m-d'),
        'expires_at' => date('Y-m-d', strtotime('+30 days')), 
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Check if subscriptions table exists, if so insert record
    $table_exists = $db->getThisQuery("SHOW TABLES LIKE 'subscriptions'");
    if (!empty($table_exists)) {
        $db->insert('subscriptions', $subscription_data);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Plan gratuit activé avec succès',
        'user_id' => $user_id,
        'plan_name' => $plan_info[0]['name']
    ]);
    
} catch (Exception $e) {

    error_log("Error in activate_free_plan.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function getPlanId($db, $plan_key) {
    // Handle both direct plan name and plan key
    $plan_key = strtolower($plan_key);

    // First try direct lookup
    $result = $db->getThisQuery("SELECT id FROM plans WHERE LOWER(name) = ?", [$plan_key]);
    
    if (!empty($result)) {
        return $result[0]['id'];
    }
    
    // If not found, try with plan mappings
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