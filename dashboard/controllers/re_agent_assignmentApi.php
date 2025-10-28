<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

if ($_POST['action'] === 'get_current_assignment') {
    $db = DB::getInstance();
    $order_id = $_POST['order_id'];
    
    try {
        $assignment = $db->getThisQuery("
            SELECT aoa.agent_id, a.name, a.phone, a.email, aoa.notes, aoa.priority
            FROM agent_order_assignments aoa
            LEFT JOIN agents a ON aoa.agent_id = a.id
            WHERE aoa.order_id = ?
            LIMIT 1
        ", [$order_id]);
        
        echo json_encode([
            'success' => true,
            'current_assignment' => !empty($assignment) ? $assignment[0] : null
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
}
?>