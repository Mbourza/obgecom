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

// Get user ID from session
$user_id = getCurrentUserId($db);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Get notification settings for this user
    $query = "SELECT * FROM notification_settings WHERE user_id = ? LIMIT 1";
    $result = $db->getThisQuery($query, [$user_id]);
    
    if (!empty($result)) {
        $settings = $result[0];
        
        echo json_encode([
            'success' => true,
            'data' => [
                'notify_new_order' => (bool)$settings['notify_new_order'],
                'notify_delivery_status' => (bool)$settings['notify_delivery_status'],
                'notify_sync_errors' => (bool)$settings['notify_sync_errors'],
                'notify_daily_report' => (bool)$settings['notify_daily_report'],
                'notify_maintenance' => (bool)$settings['notify_maintenance'],
                'last_updated' => $settings['updated_at'] ?? $settings['created_at']
            ]
        ]);
        
    } else {
        // Return default settings if no settings exist
        echo json_encode([
            'success' => true,
            'data' => [
                'notify_new_order' => true,
                'notify_delivery_status' => true,
                'notify_sync_errors' => true,
                'notify_daily_report' => false,
                'notify_maintenance' => true,
                'last_updated' => null
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("Error in load_notification_settings.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur s\'est produite lors du chargement des paramètres de notification'
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