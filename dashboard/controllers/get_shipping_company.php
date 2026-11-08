<?php
// Database connection and session check for super admin
if (file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Check if user is logged in and is super admin
if (!Session::exists(Config::get('session/session_name'))) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = $db->getThisQuery("SELECT id, `name`, `role` FROM users WHERE email = ?", [$_SESSION['user']['username']]);

if (!$user || empty($user[0]['id']) || $user[0]['role'] !== 'super') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $query = "SELECT id, name, slug, logo_url, phone, is_active FROM shipping_options WHERE id = ?";
    $result = $db->getThisQuery($query, [$id]);
    
    if ($result && !empty($result)) {
        echo json_encode(['success' => true, 'company' => $result[0]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Company not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
}
?>