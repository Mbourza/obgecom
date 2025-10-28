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
$required_fields = ['shipping_company_id', 'company_name', 'api_url'];
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

// Validate URL format
if (!filter_var($_POST['api_url'], FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid API URL format']);
    exit;
}

$user_id = null; 

if (isset($_SESSION['user'])) {
    $username = $_SESSION['user']['username'];

    // Get user_id from the database
    $user = $db->getThisQuery("SELECT id FROM utilisateur WHERE username = ?", [$username]);

    if ($user && isset($user[0]['id'])) {
        $user_id = $user[0]['id'];
    }
}

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Sanitize and prepare data
$shipping_company_id = intval($_POST['shipping_company_id']);
$company_name = trim($_POST['company_name']);
$api_url = trim($_POST['api_url']);
$api_key = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
$support_tracking = isset($_POST['support_tracking']) && $_POST['support_tracking'] === '1' ? 1 : 0;
$auto_track = isset($_POST['auto_track']) && $_POST['auto_track'] === '1' ? 1 : 0;
$smart_label = isset($_POST['SmartLabel']) && $_POST['SmartLabel'] === '1' ? 1 : 0;
$priority = isset($_POST['priority']) ? intval($_POST['priority']) : 1;

// Handle additional settings
$additional_settings = '';
if (isset($_POST['additional_settings'])) {
    $additional_settings = $_POST['additional_settings'];
    // Validate JSON
    if (!json_decode($additional_settings)) {
        echo json_encode(['success' => false, 'message' => 'Invalid additional settings format']);
        exit;
    }
}

try {
    // Check if user already has settings for this shipping company
    $existing_setting = $db->query("SELECT id FROM shipping_settings WHERE user_id = ? AND shipping_company_id = ?", 
        [$user_id, $shipping_company_id])->first();
    
    if ($existing_setting) {
        // Update existing settings
        $settings_data = [
            'company_name' => $company_name,
            'api_key' => $api_key,
            'api_url' => $api_url,
            'support_tracking' => $support_tracking,
            'auto_track' => $auto_track,
            'SmartLabel' => $smart_label,
            'priority' => $priority,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Add additional settings if provided
        if (!empty($additional_settings)) {
            $settings_data['other_settings'] = $additional_settings;
        }
        
        $result = $db->update('shipping_settings', $existing_setting->id, $settings_data);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Shipping settings updated successfully',
                'action' => 'updated',
                'data' => array_merge($settings_data, ['id' => $existing_setting->id])
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update shipping settings'
            ]);
        }
        
    } else {

        if (!empty($additional_settings)) {

            $settings_data['other_settings'] = $additional_settings;
        }
        // Insert new settings
        $settings_data = [
            'user_id' => $user_id,
            'company_name' => $company_name,
            'support_tracking' => $support_tracking,
            'shipping_company_id' => $shipping_company_id,
            'auto_track' => $auto_track,
            'SmartLabel' => $smart_label,
            'other_settings' => $additional_settings,
            'priority' => $priority,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Add additional settings if provided        
        
        if ($db->insert('shipping_settings', $settings_data)) {

            $new_setting_id = $db->getLastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Shipping settings created successfully',
                'action' => 'created',
                'data' => array_merge($settings_data, ['id' => $new_setting_id])
            ]);

        } else {

            echo json_encode([
                'success' => false,
                'message' => 'Failed to create shipping settings'
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log("Error in save_shipping_settings.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving shipping settings'
    ]);
} ?>