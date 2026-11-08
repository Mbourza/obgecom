<?php
// controllers/shopify_disconnect.php - Handle Shopify store disconnection

if (file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$db = DB::getInstance();

// Get current user
$user_id = getCurrentUserId($db);

if (!$user_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Non authentifié'
    ]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$store_id = $input['store_id'] ?? 0;

if (!$store_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'ID de boutique requis'
    ]);
    exit;
}

// Verify store belongs to user and is Shopify
$store = $db->getThisQuery(
    "SELECT id, storeName, domain, platform, is_connected FROM stores 
     WHERE id = ? AND user_id = ? AND platform = 'shopify'",
    [$store_id, $user_id]
);

if (empty($store)) {
    http_response_code(404);
    echo json_encode([
        'success' => false, 
        'message' => 'Boutique Shopify non trouvée'
    ]);
    exit;
}

$store = $store[0];

try {
    // Begin transaction
    $db->beginTransaction();
    
    // Update store to disconnected status
    $update_data = [
        'is_connected' => 0,
        'connection_error' => 'Déconnecté manuellement par l\'utilisateur',
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Keep the api_key and refresh_token for potential reconnection
    // but mark as disconnected
    $updated = $db->update('stores', $store_id, $update_data);
    
    if (!$updated) {
        throw new Exception('Erreur lors de la mise à jour du statut de la boutique');
    }
    
    // Optionally: Invalidate tokens in shopify_tokens table
    $token_table_exists = $db->getThisQuery("SHOW TABLES LIKE 'shopify_tokens'");
    if (!empty($token_table_exists)) {
        // Instead of deleting, mark as inactive or keep for reference
        // You could delete them or keep them for history
        $db->query(
            "UPDATE shopify_tokens SET 
             expires_at = NOW(),
             updated_at = NOW()
             WHERE store_id = ? AND user_id = ?",
            [$store_id, $user_id]
        );
    }
    
    // Log the disconnection
    error_log("Shopify store disconnected - User: $user_id, Store: {$store['storeName']} (ID: $store_id)");
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Boutique Shopify déconnectée avec succès',
        'store_name' => $store['storeName'],
        'store_id' => $store_id
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Error disconnecting Shopify store: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la déconnexion: ' . $e->getMessage()
    ]);
}

/**
 * Get current user ID from session
 */
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    $identifier = $_SESSION['user']['username'];

    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

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