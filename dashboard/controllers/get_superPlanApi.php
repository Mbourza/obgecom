<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing plan ID']);
    exit;
}

$planId = intval($_GET['id']);
$db = DB::getInstance();

// Fetch the plan data
$plan = $db->getThisQuery("
    SELECT p.id, p.name, p.price, p.is_custom, p.created_at
    FROM plans p
    WHERE p.id = ?
    LIMIT 1
", [$planId]);

if (!empty($plan)) {
    $planData = $plan[0];
    
    // Fetch plan limits (key-value pairs)
    $limitsResult = $db->getThisQuery("
        SELECT limit_key, limit_value
        FROM plan_limits
        WHERE plan_id = ?
    ", [$planId]);
    
    // Convert key-value pairs to associative array
    $limits = [
        'orders_per_month' => 0,
        'stores' => 0,
        'carriers' => 0,
        'team_members' => 0,
        'support' => 0,
        'account_manager' => 0
    ];
    
    if (!empty($limitsResult)) {
        foreach ($limitsResult as $limit) {
            $limits[$limit['limit_key']] = (int)$limit['limit_value'];
        }
    }
    
    // Structure the response to match your JavaScript expectations
    echo json_encode([
        'success' => true,
        'id' => $planData['id'],
        'name' => $planData['name'],
        'price' => $planData['price'],
        'is_custom' => (bool)$planData['is_custom'],
        'created_at' => $planData['created_at'],
        'limits' => $limits
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Plan non trouvé']);
}
?>