<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

try {

    // Get current user ID
    $user_id = getCurrentUserId($db);

    // Check if user is logged in
    if (!$user_id) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'message' => 'Authentication required'
        ]);
        exit;
    }
    // Check if ID parameter is provided
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du transporteur requis'
        ]);
        exit;
    }

    $carrierId = intval($_GET['id']);
    
    // Validate ID is a positive integer
    if ($carrierId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du transporteur invalide'
        ]);
        exit;
    }

    
    // Prepare and execute query
    $query = "SELECT 
                id,
                name,
                api_url,
                api_key,
                supports_tracking,
                tracking_url,
                logo_url,
                contact_email,
                phone,
                website,
                is_active,
                created_at,
                updated_at
              FROM shipping_companies 
              WHERE id = ? and user_id = ?";
    
    $result = $db->getThisQuery($query, [$carrierId, $user_id]);
    
    // Check if carrier exists
    if (empty($result)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Transporteur non trouvé'
        ]);
        exit;
    }
    
    $carrier = $result[0];
    
    // Format the response to match your frontend expectations
    $responseData = [
        'id' => $carrier['id'],
        'name' => $carrier['name'],
        'api_url' => $carrier['api_url'], // Mapping api_url to description based on your JS
        'api_key' => $carrier['api_key'],
        'tracking_url' => $carrier['tracking_url'],
        'is_active' => $carrier['is_active'],
        'logo_url' => $carrier['logo_url'],
        'contact_email' => $carrier['contact_email'],
        'phone' => $carrier['phone'],
        'website' => $carrier['website'],
        'supports_tracking' => $carrier['supports_tracking'],
        'created_at' => $carrier['created_at'],
        'updated_at' => $carrier['updated_at']
    ];
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $responseData,
        'message' => 'Données du transporteur récupérées avec succès'
    ]);

} catch (Exception $e) {
    // Log error for debugging
    error_log("Error in get_carrierById_Api.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur'
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
}

?>