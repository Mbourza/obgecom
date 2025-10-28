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

try {
    
    // Get user's shipping settings (assuming user_id is available in session)
    $user_id = getCurrentUserId($db); 


    $companies_query = "
        SELECT * 
        FROM shipping_companies 
        WHERE is_active = 1 
        AND user_id = ? 
        ORDER BY name ASC
    ";
    $shipping_companies = $db->getThisQuery($companies_query, [$user_id]);

    
    $settings_query = "
        SELECT ss.*, sc.name AS company_name
        FROM shipping_settings ss
        LEFT JOIN shipping_companies sc 
            ON ss.shipping_company_id = sc.id 
        AND sc.user_id = ss.user_id
        WHERE ss.user_id = ?
    ";

    $user_settings = $db->getThisQuery($settings_query, [$user_id]);
    
    // Get general shipping configuration
    $config_query = "SELECT * FROM shipping_config WHERE user_id = ? LIMIT 1";
    $shipping_config = $db->getThisQuery($config_query, [$user_id]);
    
    // Default configuration if none exists
    $default_config = [
        'default_shipping_method' => '2',
        'auto_tracking' => false,
        'tracking_update_interval' => 30,
        'auto_label_generation' => true,
        'default_package_weight' => 1.0,
        'default_package_length' => 20,
        'default_package_width' => 15,
        'default_package_height' => 10
    ];
    
    $current_config = !empty($shipping_config) ? array_merge($default_config, json_decode($shipping_config[0]['settings'], true)) : $default_config;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'shipping_companies' => $shipping_companies ?: [],
            'user_settings' => $user_settings ?: [],
            'shipping_config' => $current_config
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in get_shipping_data.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching shipping data'
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