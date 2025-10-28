<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id']);
    exit;
}

$userId = intval($_GET['user_id']);
$db = DB::getInstance();

// Fetch the latest active subscription
$subscription = $db->getThisQuery("
    SELECT s.plan_id, s.expires_at,
           TIMESTAMPDIFF(MONTH, s.started_at, s.expires_at) AS duration
    FROM subscriptions s
    WHERE s.user_id = ?
    ORDER BY s.id DESC LIMIT 1
", [$userId]);

if (!empty($subscription)) {
    echo json_encode([
        'success' => true,
        'plan_id' => $subscription[0]['plan_id'],
        'duration' => $subscription[0]['duration'],
        'expires_at' => $subscription[0]['expires_at']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Aucune souscription active trouvée.']);
}
?>
