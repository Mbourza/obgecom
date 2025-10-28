<?php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// controllers/add_storeTo_db.php - Add store to database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$db = DB::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

try {
    // Validate required fields
    $required_fields = ['platform', 'store_name', 'domain'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception("Champs requis manquants: " . implode(', ', $missing_fields));
    }
    
    // For WooCommerce, consumer_key and consumer_secret are required
    if ($data['platform'] === 'woocommerce') {
        if (empty($data['consumer_key']) || empty($data['consumer_secret'])) {
            throw new Exception('Consumer Key et Consumer Secret sont requis pour WooCommerce');
        }
    }
    

    $user_id = getCurrentUserId($db); 
    
    if (!$user_id) {
        throw new Exception('Utilisateur non authentifié');
    }
    
    $existing_store = $db->getThisQuery("SELECT id FROM stores WHERE user_id = ? AND domain = ?", [
        $user_id, 
        $data['domain']
    ]);
    
    if (!empty($existing_store)) {
        throw new Exception('Cette boutique existe déjà pour cet utilisateur');
    }
    
    // Prepare store data
    $store_data = [

        'user_id' => $user_id,
        'storeName' => $data['store_name'],
        'logo_url' => isset($data['logo_url']) ? $data['logo_url'] : '',
        'platform' => $data['platform'],
        'store_name' => $data['store_name'],
        'domain' => $data['domain'],
        'api_key' => '', // Not used for WooCommerce
        'api_url' => $data['api_url'],
        'Consumer_key' => isset($data['consumer_key']) ? $data['consumer_key'] : '',
        'Consumer_secret' => isset($data['consumer_secret']) ? $data['consumer_secret'] : '',
        'is_connected' => 1, // Since validation passed in frontend
        'connected_at' => date('Y-m-d H:i:s')
    ];
    
    // Insert store using DB class method
    $store_result = $db->insert('stores', $store_data);
    
    if ($store_result) {
        $store_id = $db->getLastInsertId();
        
        echo json_encode([
            'success' => true,
            'store_id' => $store_id,
            'message' => 'Boutique ajoutée avec succès'
        ]);
    } else {
        throw new Exception('Erreur lors de l\'ajout de la boutique');
    }
    
} catch (Exception $e) {
    error_log("Error in add_storeTo_db.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
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