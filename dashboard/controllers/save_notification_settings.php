<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get user ID from session
$user_id = getCurrentUserId($db);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Validate and sanitize notification settings
$notify_new_order = isset($_POST['notify-new-order']) && $_POST['notify-new-order'] === 'on' ? 1 : 0;
$notify_delivery_status = isset($_POST['notify-delivery-status']) && $_POST['notify-delivery-status'] === 'on' ? 1 : 0;
$notify_sync_errors = isset($_POST['notify-sync-errors']) && $_POST['notify-sync-errors'] === 'on' ? 1 : 0;
$notify_daily_report = isset($_POST['notify-daily-report']) && $_POST['notify-daily-report'] === 'on' ? 1 : 0;
$notify_maintenance = isset($_POST['notify-maintenance']) && $_POST['notify-maintenance'] === 'on' ? 1 : 0;

// Prepare data for database insertion/update
$data = [
    'user_id' => $user_id,
    'notify_new_order' => $notify_new_order,
    'notify_delivery_status' => $notify_delivery_status,
    'notify_sync_errors' => $notify_sync_errors,
    'notify_daily_report' => $notify_daily_report,
    'notify_maintenance' => $notify_maintenance,
    'updated_at' => date('Y-m-d H:i:s')
];

try {
    // Check if notification settings already exist for this user
    $existing_query = "SELECT id FROM notification_settings WHERE user_id = ? LIMIT 1";
    $existing_result = $db->getThisQuery($existing_query, [$user_id]);
    
    if (!empty($existing_result)) {
        // Update existing record
        $existing_id = $existing_result[0]['id'];
        unset($data['user_id']); // Don't update user_id
        $result = $db->update('notification_settings', $existing_id, $data);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Paramètres de notification mis à jour avec succès',
                'action' => 'updated',
                'id' => $existing_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Échec de la mise à jour des paramètres de notification'
            ]);
        }
    } else {
        // Insert new record
        $data['created_at'] = date('Y-m-d H:i:s');
        $result = $db->insert('notification_settings', $data);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Paramètres de notification sauvegardés avec succès',
                'action' => 'created',
                'id' => $db->getLastInsertId()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Échec de la sauvegarde des paramètres de notification'
            ]);
        }
    }

} catch (Exception $e) {
    error_log("Error in save_notification_settings.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur s\'est produite lors de la sauvegarde des paramètres de notification'
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