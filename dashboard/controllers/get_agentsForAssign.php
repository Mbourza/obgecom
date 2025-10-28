<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

if ($_POST['action'] === 'get_available_agents') {

    $db = DB::getInstance();
    $user_id = getCurrentUserId($db); 
    
    try {
        // Get agents that are active and available
        $agents = $db->getThisQuery("
            SELECT id, name, phone, email, service_fee, is_active 
            FROM agents 
            WHERE user_id = ? AND is_active = 1
            ORDER BY name ASC
        ", [$user_id]);
        
        echo json_encode([
            'success' => true,
            'agents' => $agents
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
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