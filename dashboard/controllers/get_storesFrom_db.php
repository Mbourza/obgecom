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

// Helper function to get current user ID
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

try {
    // Get current user ID
    $current_user_id = getCurrentUserId($db);
    
    if ($current_user_id === null) {
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit;
    }

    // Get stores belonging to the logged-in user only
    $stores_query = "
        SELECT 
            id,
            user_id,
            storeName,
            logo_url,
            platform,
            store_name,
            domain,
            api_key,
            api_url,
            Consumer_key,
            Consumer_secret,
            is_connected,
            connected_at
        FROM stores
        WHERE user_id = ?
        ORDER BY id DESC
    ";
    
    $stores_result = $db->getThisQuery($stores_query, [$current_user_id]);
    
    if (!empty($stores_result)) {
        // Format the stores data
        $formatted_stores = [];
        
        foreach ($stores_result as $store) {
            $formatted_stores[] = [
                'id' => (int)$store['id'],
                'user_id' => (int)$store['user_id'],
                'storeName' => $store['storeName'],
                'logo_url' => $store['logo_url'],
                'platform' => $store['platform'],
                'store_name' => $store['store_name'],
                'domain' => $store['domain'],
                'api_key' => $store['api_key'],
                'api_url' => $store['api_url'],
                'Consumer_key' => $store['Consumer_key'],
                'Consumer_secret' => $store['Consumer_secret'],
                'is_connected' => (bool)$store['is_connected'],
                'connected_at' => $store['connected_at']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'stores' => $formatted_stores,
            'count' => count($formatted_stores)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'stores' => [],
            'count' => 0,
            'message' => 'No stores found'
        ]);
    }

} catch (Exception $e) {
    // Log the error
    error_log("Error in get_storesFrom_db.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching stores'
    ]);
}
?>