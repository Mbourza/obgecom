<?php 
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
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

    $user_id = getCurrentUserId($db); // Adjust based on your auth system
    
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit;
    }
    
    // Get user's active plan with plan details and limits
    $user_plan_query = "
        SELECT 
            s.id AS subscription_id,
            s.user_id,
            s.plan_id,
            s.status,
            s.started_at,
            s.expires_at,
            p.name AS plan_name,
            p.price AS plan_price,
            p.is_custom,
            pl.limit_key,
            pl.limit_value
        FROM subscriptions s
        INNER JOIN plans p ON s.plan_id = p.id
        LEFT JOIN plan_limits pl ON p.id = pl.plan_id AND pl.limit_key = 'stores'
        WHERE s.user_id = ?
        ORDER BY s.started_at DESC
        LIMIT 1
    ";

    $user_plan_result = $db->getThisQuery($user_plan_query, [$user_id]);
    
    // Get current number of stores for this user
    $current_stores_query = "
        SELECT COUNT(*) as current_stores
        FROM stores
        WHERE user_id = ?
    ";
    
    $current_stores_result = $db->getThisQuery($current_stores_query, [$user_id]);
    $current_stores = $current_stores_result ? (int)$current_stores_result[0]['current_stores'] : 0;
    
    if (!empty($user_plan_result)) {
        $plan_data = $user_plan_result[0];
        
        // Get the store limit from plan_limits
        $max_stores = $plan_data['limit_value'] ? (int)$plan_data['limit_value'] : 5; // Default to 5 if no limit set
        
        echo json_encode([
            'success' => true,
            'limits' => [
                'current' => $current_stores,
                'max' => $max_stores
            ],
            'plan_info' => [
                'plan_id' => (int)$plan_data['plan_id'],
                'plan_name' => $plan_data['plan_name'],
                'plan_price' => $plan_data['plan_price'],
                'is_custom' => (bool)$plan_data['is_custom'],
                'status' => $plan_data['status'],
                'expires_at' => $plan_data['expires_at']
            ]
        ]);
    } else {
        // No active plan found - return default limits
        echo json_encode([
            'success' => true,
            'limits' => [
                'current' => $current_stores,
                'max' => 5 // Default limit for users without active plans
            ],
            'plan_info' => null,
            'message' => 'No active plan found, using default limits'
        ]);
    }

} catch (Exception $e) {
    // Log the error
    error_log("Error in get_user_limits.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching user limits'
    ]);
}

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
} ?>