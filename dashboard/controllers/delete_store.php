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
    
    // Verify the store belongs to the current user and get store details
    $verify_query = "SELECT id, storeName FROM stores WHERE id = ? AND user_id = ?";
    $store_data = $db->getThisQuery($verify_query, [$store_id, $user_id]);
    
    if (empty($store_data)) {
        echo json_encode([
            'success' => false,
            'message' => 'Store not found or access denied'
        ]);
        exit;
    }
    
    $store_name = $store_data[0]['storeName'];
    
    // Delete the store from database
    $delete_query = "DELETE * FROM stores WHERE id = ?";
    $result = $db->delete("stores", ["id", "=", $store_id]);
    
    if ($result) {
        // Get updated store count for the user
        $count_query = "SELECT COUNT(*) as store_count FROM stores WHERE user_id = ?";
        $count_result = $db->getThisQuery($count_query, [$user_id]);
        $current_stores = $count_result ? (int)$count_result[0]['store_count'] : 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Store deleted successfully',
            'store_id' => $store_id,
            'store_name' => $store_name,
            'current_stores' => $current_stores
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete store'
        ]);
    }

} catch (Exception $e) {
    // Log the error
    error_log("Error in delete_store.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the store'
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