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

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['store_id'])) {
        echo json_encode(['success' => false, 'message' => 'Store ID is required']);
        exit;
    }
    
    $store_id = (int)$input['store_id'];
    
    // You'll need to get the current user ID - adjust based on your auth system
    $user_id = getCurrentUserId($db);
    
    if (!$user_id) {
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit;
    }
    
    // Verify the store belongs to the current user
    $verify_query = "SELECT id FROM stores WHERE id = ? AND user_id = ?";
    $store_exists = $db->getThisQuery($verify_query, [$store_id, $user_id]);
    
    if (empty($store_exists)) {
        echo json_encode([
            'success' => false,
            'message' => 'Store not found or access denied'
        ]);
        exit;
    }
    
    // Update store to disconnected status
    $data = [
        'is_connected' => 0,
        'connected_at' => null
    ];
    
    $result = $db->update("stores", $store_id, $data);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Store disconnected successfully',
            'store_id' => $store_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to disconnect store'
        ]);
    }

} catch (Exception $e) {
    // Log the error
    error_log("Error in disconnect_store.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while disconnecting the store'
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