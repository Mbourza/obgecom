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

// Validate required fields
$required_fields = ['company-name', 'company-email', 'company-phone', 'timezone', 'default-currency', 'default-language'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
    ]);
    exit;
}

// Validate email format
if (!filter_var($_POST['company-email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Sanitize and prepare data
$company_name = trim($_POST['company-name']);
$company_email = trim($_POST['company-email']);
$company_phone = trim($_POST['company-phone']);
$company_address = isset($_POST['company-address']) ? trim($_POST['company-address']) : '';
$timezone = trim($_POST['timezone']);
$default_currency = trim($_POST['default-currency']);
$default_language = trim($_POST['default-language']);

try {
    // Check if general settings already exist
    $existing_query = "SELECT id FROM general_settings LIMIT 1";
    $existing_result = $db->getThisQuery($existing_query);
    
    $data = [
        'company_name' => $company_name,
        'company_email' => $company_email,
        'company_phone' => $company_phone,
        'company_address' => $company_address,
        'timezone' => $timezone,
        'default_currency' => $default_currency,
        'default_language' => $default_language,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($existing_result)) {
        // Update existing record
        $existing_id = $existing_result[0]['id'];
        $result = $db->update('general_settings', $existing_id, $data);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'General settings updated successfully',
                'action' => 'updated',
                'id' => $existing_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update general settings'
            ]);
        }
    } else {
        // Insert new record
        $data['created_at'] = date('Y-m-d H:i:s');
        $result = $db->insert('general_settings', $data);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'General settings saved successfully',
                'action' => 'created',
                'id' => $db->getLastInsertId()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save general settings'
            ]);
        }
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in save_general_settings.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving general settings'
    ]);
}

?>