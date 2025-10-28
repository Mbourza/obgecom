<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

if (!isset($_GET['id'])) {
  echo json_encode(['error' => 'ID utilisateur manquant']);
  exit;
}

$userId = (int)$_GET['id'];

$user = $db->getThisQuery("
  SELECT id, name, email, phone, city, created_at, derniere_connexion
  FROM users WHERE id = ?
", [$userId]);

if (empty($user)) {
  echo json_encode(['error' => 'Utilisateur introuvable']);
  exit;
}
$user = $user[0];

// Fetch subscription
$subscription = $db->getThisQuery("
  SELECT p.name AS plan_name, up.status, up.total_amount AS amount, up.created_at AS start, up.expires_at AS expires
  FROM user_plans up
  JOIN plans p ON up.plan_id = p.id
  WHERE up.user_id = ? AND up.status = 'active'
  ORDER BY up.created_at DESC
  LIMIT 1
", [$userId]);

// Fetch stats
$stats = $db->getThisQuery("
  SELECT 
    COUNT(*) AS order_count,
    COALESCE(SUM(total_amount), 0) AS total_revenue
  FROM orders WHERE user_id = ? AND status != 'cancelled'
", [$userId])[0];

echo json_encode([
  'name' => $user['name'],
  'email' => $user['email'],
  'phone' => $user['phone'],
  'city' => $user['city'],
  'created_at' => $user['created_at'],
  'derniere_connexion' => $user['derniere_connexion'],
  'subscription' => !empty($subscription) ? $subscription[0] : null,
  'stats' => $stats
]); ?>
