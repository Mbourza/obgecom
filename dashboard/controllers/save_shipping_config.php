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

// Get user ID from session
$user_id = getCurrentUserId($db); 


// Validate and sanitize input data - mapping to database columns
$company_name = isset($_POST['company-name']) ? trim($_POST['company-name']) : null;
$support_tracking = isset($_POST['support-tracking']) && $_POST['support-tracking'] === 'on' ? 1 : 0;
$shipping_company_id = isset($_POST['default-shipping-method']) ? intval($_POST['default-shipping-method']) : null;
$auto_track = isset($_POST['auto-tracking']) && $_POST['auto-tracking'] === 'on' ? 1 : 0;
$smart_label = isset($_POST['auto-label-generation']) && $_POST['auto-label-generation'] === 'on' ? 1 : 0;
$priority = isset($_POST['priority']) ? intval($_POST['priority']) : 1;

// Handle other settings that don't have direct column mapping
$other_settings = [];
if (isset($_POST['tracking-update-interval'])) {
    $tracking_update_interval = intval($_POST['tracking-update-interval']);
    if ($tracking_update_interval >= 5 && $tracking_update_interval <= 1440) {
        $other_settings['tracking_update_interval'] = $tracking_update_interval;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tracking update interval must be between 5 and 1440 minutes'
        ]);
        exit;
    }
} else {
    $other_settings['tracking_update_interval'] = 30; // default
}

// Package dimensions and weight
if (isset($_POST['default-package-weight'])) {
    $default_package_weight = floatval($_POST['default-package-weight']);
    if ($default_package_weight <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Package weight must be greater than 0'
        ]);
        exit;
    }
    $other_settings['default_package_weight'] = $default_package_weight;
} else {
    $other_settings['default_package_weight'] = 1.0; // default
}

if (isset($_POST['default-package-length']) && isset($_POST['default-package-width']) && isset($_POST['default-package-height'])) {
    $default_package_length = intval($_POST['default-package-length']);
    $default_package_width = intval($_POST['default-package-width']);
    $default_package_height = intval($_POST['default-package-height']);
    
    if ($default_package_length <= 0 || $default_package_width <= 0 || $default_package_height <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Package dimensions must be greater than 0'
        ]);
        exit;
    }
    
    $other_settings['default_package_dimensions'] = [
        'length' => $default_package_length,
        'width' => $default_package_width,
        'height' => $default_package_height
    ];
} else {
    $other_settings['default_package_dimensions'] = [
        'length' => 20,
        'width' => 15,
        'height' => 10
    ]; // defaults
}

// Prepare data for database insertion/update
$data = [
    'user_id' => $user_id,
    'company_name' => $company_name,
    'support_tracking' => $support_tracking,
    'shipping_company_id' => $shipping_company_id,
    'auto_track' => $auto_track,
    'SmartLabel' => $smart_label,
    'other_settings' => json_encode($other_settings),
    'priority' => $priority,
    'updated_at' => date('Y-m-d H:i:s')
];

try {
    // Check if shipping config already exists for this user
    $existing_query = "SELECT id FROM shipping_settings WHERE user_id = ? LIMIT 1";
    $existing_result = $db->getThisQuery($existing_query, [$user_id]);
    
    if (!empty($existing_result)) {
        // Update existing record
        $existing_id = $existing_result[0]['id'];
        unset($data['user_id']); // Don't update user_id
        $result = $db->update('shipping_settings', $existing_id, $data);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Shipping configuration updated successfully',
                'action' => 'updated',
                'id' => $existing_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update shipping configuration'
            ]);
        }
    } else {
        // Insert new record
        $data['created_at'] = date('Y-m-d H:i:s');
        $result = $db->insert('shipping_settings', $data);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Shipping configuration saved successfully',
                'action' => 'created',
                'id' => $db->getLastInsertId()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to save shipping configuration'
            ]);
        }
    }

} catch (Exception $e) {
    error_log("Error in save_shipping_config.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving shipping configuration'
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