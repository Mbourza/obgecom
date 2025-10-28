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
    // Get general settings
    $settings_query = "
        SELECT 
            id,
            company_name,
            company_email,
            company_phone,
            company_address,
            timezone,
            default_currency,
            default_language,
            created_at,
            updated_at
        FROM general_settings 
        ORDER BY id DESC 
        LIMIT 1
    ";
    
    $settings_result = $db->getThisQuery($settings_query);
    
    if (!empty($settings_result)) {
        $settings = $settings_result[0];
        
        echo json_encode([
            'success' => true,
            'settings' => [
                'id' => (int)$settings['id'],
                'company_name' => $settings['company_name'],
                'company_email' => $settings['company_email'],
                'company_phone' => $settings['company_phone'],
                'company_address' => $settings['company_address'],
                'timezone' => $settings['timezone'],
                'default_currency' => $settings['default_currency'],
                'default_language' => $settings['default_language'],
                'created_at' => $settings['created_at'],
                'updated_at' => $settings['updated_at']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'settings' => null,
            'message' => 'No general settings found'
        ]);
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in get_general_settings.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching general settings'
    ]);
}

?>