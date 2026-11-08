<?php
// get_cities_by_region.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if user is logged in
if(!Session::exists(Config::get('session/session_name'))) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Get current user ID
$user_id = getCurrentUserId($db);

if (!$user_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
    exit;
}

// Get region_id from POST request
$region_id = isset($_POST['region_id']) ? (int)$_POST['region_id'] : 0;

if ($region_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de région invalide']);
    exit;
}

try {
    // Query to get cities for the selected region with order counts
    $cities_query = "
        SELECT 
            c.id,
            c.name,
            c.region_id,
            COUNT(DISTINCT o.id) as order_count
        FROM cities c
        LEFT JOIN orders o ON o.customer_ville = c.name AND o.user_id = ?
        WHERE c.region_id = ?
        GROUP BY c.id, c.name
        ORDER BY c.name ASC
    ";
    
    $cities = $db->getThisQuery($cities_query, [$user_id, $region_id]);
    
    // Also get region name for reference
    $region_query = "SELECT name FROM regions WHERE id = ?";
    $region_result = $db->getThisQuery($region_query, [$region_id]);
    $region_name = $region_result[0]['name'] ?? 'Région';
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'region_id' => $region_id,
        'region_name' => $region_name,
        'cities' => $cities,
        'total_cities' => count($cities)
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des villes: ' . $e->getMessage()
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