<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get notifications for a user
        handleGetNotifications($db);
    } elseif ($method === 'POST') {
        // Handle POST requests (mark as read, etc.)
        handlePostRequest($db);
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Méthode non autorisée'
        ]);
    }

} catch (PDOException $e) {
    error_log("Database error in notifications_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données'
    ]);
} catch (Exception $e) {
    error_log("Error in notifications_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur'
    ]);
}

function handleGetNotifications($db) {
    // Validate required parameter
    if (!isset($_GET['user_id']) || empty(trim($_GET['user_id']))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'L\'ID utilisateur est requis'
        ]);
        return;
    }

    $userId = trim($_GET['user_id']);
    
    // Validate user ID is numeric
    if (!is_numeric($userId) || intval($userId) <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID utilisateur invalide'
        ]);
        return;
    }

    $userId = intval($userId);
    
    // Get limit parameter (default: 20, max: 100)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $limit = max(1, min(100, $limit));
    
    // Get only unread notifications or all
    $onlyUnread = isset($_GET['only_unread']) && $_GET['only_unread'] === 'true';
    
    // Build query
    $query = "SELECT 
                id,
                user_id,
                title,
                message,
                type,
                is_read,
                created_at,
                updated_at
              FROM user_notification 
              WHERE user_id = ?";
    
    $params = [$userId];
    
    // Add unread filter if requested
    if ($onlyUnread) {
        $query .= " AND is_read = 0";
    }
    
    // Order by creation date (newest first) and limit
    $query .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $result = $db->getThisQuery($query, $params);
    $notifications = $result ?? [];
    
    // Get unread count
    $unreadCountQuery = "SELECT COUNT(*) as unread_count FROM user_notification WHERE user_id = ? AND is_read = 0";
    $unreadResult = $db->getThisQuery($unreadCountQuery, [$userId]);
    $unreadCount = $unreadResult[0]['unread_count'] ?? 0;
    
    // Format notifications
    $formattedNotifications = [];
    foreach ($notifications as $notification) {
        $formattedNotifications[] = [
            'id' => intval($notification['id']),
            'user_id' => intval($notification['user_id']),
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'is_read' => intval($notification['is_read']),
            'created_at' => $notification['created_at'],
            'updated_at' => $notification['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $formattedNotifications,
        'unread_count' => intval($unreadCount),
        'total_count' => count($formattedNotifications),
        'message' => 'Notifications récupérées avec succès'
    ]);
}

function handlePostRequest($db) {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Action requise'
        ]);
        return;
    }
    
    $action = $input['action'];
    
    switch ($action) {
        case 'mark_read':
            markNotificationAsRead($db, $input);
            break;
        case 'mark_all_read':
            markAllNotificationsAsRead($db, $input);
            break;
        case 'delete':
            deleteNotification($db, $input);
            break;
        case 'create':
            createNotification($db, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue'
            ]);
    }
}

function markNotificationAsRead($db, $input) {
    if (!isset($input['notification_id']) || !isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID de notification et ID utilisateur requis'
        ]);
        return;
    }
    
    $notificationId = intval($input['notification_id']);
    $userId = intval($input['user_id']);
    
    if ($notificationId <= 0 || $userId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'IDs invalides'
        ]);
        return;
    }
    
    // Update notification
    $query = "UPDATE user_notification 
              SET is_read = 1, updated_at = NOW() 
              WHERE id = ? AND user_id = ?";
    
    $result = $db->executeThisQuery($query, [$notificationId, $userId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de marquer la notification comme lue'
        ]);
    }
}

function markAllNotificationsAsRead($db, $input) {
    if (!isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID utilisateur requis'
        ]);
        return;
    }
    
    $userId = intval($input['user_id']);
    
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID utilisateur invalide'
        ]);
        return;
    }
    
    // Update all unread notifications for the user
    $query = "UPDATE user_notification 
              SET is_read = 1, updated_at = NOW() 
              WHERE user_id = ? AND is_read = 0";
    
    $result = $db->executeThisQuery($query, [$userId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Toutes les notifications marquées comme lues'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de marquer les notifications comme lues'
        ]);
    }
}

function deleteNotification($db, $input) {
    if (!isset($input['notification_id']) || !isset($input['user_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID de notification et ID utilisateur requis'
        ]);
        return;
    }
    
    $notificationId = intval($input['notification_id']);
    $userId = intval($input['user_id']);
    
    if ($notificationId <= 0 || $userId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'IDs invalides'
        ]);
        return;
    }
    
    // Delete notification
    $query = "DELETE FROM user_notification WHERE id = ? AND user_id = ?";
    $result = $db->executeThisQuery($query, [$notificationId, $userId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification supprimée'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de supprimer la notification'
        ]);
    }
}

function createNotification($db, $input) {
    $requiredFields = ['user_id', 'title', 'message'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Le champ {$field} est requis"
            ]);
            return;
        }
    }
    
    $userId = intval($input['user_id']);
    $title = trim($input['title']);
    $message = trim($input['message']);
    $type = isset($input['type']) ? trim($input['type']) : 'info';
    
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID utilisateur invalide'
        ]);
        return;
    }
    
    // Validate type
    $allowedTypes = ['order', 'system', 'message', 'alert', 'success', 'warning', 'info', 'agent'];
    if (!in_array($type, $allowedTypes)) {
        $type = 'info';
    }
    
    // Insert notification
    $query = "INSERT INTO user_notification (user_id, title, message, type, is_read, created_at) 
              VALUES (?, ?, ?, ?, 0, NOW())";
    
    $result = $db->executeThisQuery($query, [$userId, $title, $message, $type]);
    
    if ($result) {
        // Get the inserted notification ID
        $insertId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'notification_id' => $insertId,
            'message' => 'Notification créée avec succès'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de créer la notification'
        ]);
    }
}

// Helper function to create a notification (can be called from other parts of your app)
function createUserNotification($db, $userId, $title, $message, $type = 'info') {
    try {
        $allowedTypes = ['order', 'system', 'message', 'alert', 'success', 'warning', 'info', 'agent'];
        if (!in_array($type, $allowedTypes)) {
            $type = 'info';
        }
        
        $query = "INSERT INTO user_notification (user_id, title, message, type, is_read, created_at) 
                  VALUES (?, ?, ?, ?, 0, NOW())";
        
        return $db->executeThisQuery($query, [$userId, $title, $message, $type]);
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}
?>