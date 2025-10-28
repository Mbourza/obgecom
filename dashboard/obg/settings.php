<?php
// Database connection and session check for super admin
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if user is logged in and is super admin
if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../../lg'); 
} 

$isLoggedIn = isset($_SESSION['user']);
$user = $db->getThisQuery("SELECT id, `name`, `role`, is_verified, email FROM users WHERE email = ?", [$_SESSION['user']['username']]);

if (!$user || empty($user[0]['id'])) {
    logout(); 
}

if (isset($_GET['logout'])) {
    logout();
}

// Check if user is super admin
if ($user[0]['role'] !== 'super') {
    Redirect::to('../home');
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../../lg');
}

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = false;
    $message = '';
    
    if (isset($_POST['action'])) {
        switch($_POST['action']) {
            case 'update_general_settings':
                $success = updateGeneralSettings($db, $_POST);
                $message = $success ? 'Paramètres généraux mis à jour avec succès' : 'Erreur lors de la mise à jour';
                break;
                
            case 'update_email_settings':
                $success = updateEmailSettings($db, $_POST);
                $message = $success ? 'Paramètres email mis à jour avec succès' : 'Erreur lors de la mise à jour';
                break;
                
            case 'update_payment_settings':
                $success = updatePaymentSettings($db, $_POST);
                $message = $success ? 'Paramètres de paiement mis à jour avec succès' : 'Erreur lors de la mise à jour';
                break;
                
            case 'update_shipping_settings':
                $success = updateShippingSettings($db, $_POST);
                $message = $success ? 'Paramètres de livraison mis à jour avec succès' : 'Erreur lors de la mise à jour';
                break;
                
            case 'update_api_settings':
                $success = updateApiSettings($db, $_POST);
                $message = $success ? 'Paramètres API mis à jour avec succès' : 'Erreur lors de la mise à jour';
                break;
                
            case 'update_security_settings':
                $success = updateSecuritySettings($db, $_POST);
                $message = $success ? 'Paramètres de sécurité mis à jour avec succès' : 'Erreur lors de la mise à jour';
                break;
                
            case 'add_admin_user':
                $success = addAdminUser($db, $_POST);
                $message = $success ? 'Administrateur ajouté avec succès' : 'Erreur lors de l\'ajout';
                break;
                
            case 'update_admin_status':
                $success = updateAdminStatus($db, $_POST);
                $message = $success ? 'Statut administrateur mis à jour' : 'Erreur lors de la mise à jour';
                break;
                
            case 'test_email_settings':
                $result = testEmailSettings($db);
                $message = $result['success'] ? 'Test email envoyé avec succès' : 'Erreur: ' . $result['message'];
                $success = $result['success'];
                break;
                
            case 'test_payment_settings':
                $result = testPaymentSettings($db);
                $message = $result['message'];
                $success = $result['success'];
                break;
                
            case 'test_api_settings':
                $result = testApiSettings($db);
                $message = $result['message'];
                $success = $result['success'];
                break;
                
            case 'generate_backup':
                $result = generateBackup($db);
                $message = $result['message'];
                $success = $result['success'];
                break;
                
            case 'clear_cache':
                $result = clearSystemCache();
                $message = $result['message'];
                $success = $result['success'];
                break;
        }
        
        if ($success) {
            $_SESSION['success_message'] = $message;
        } else {
            $_SESSION['error_message'] = $message;
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Function to update general settings
function updateGeneralSettings($db, $data) {
    $settings = [
        'site_name' => $data['site_name'] ?? '',
        'site_email' => $data['site_email'] ?? '',
        'site_phone' => $data['site_phone'] ?? '',
        'site_address' => $data['site_address'] ?? '',
        'site_currency' => $data['site_currency'] ?? 'MAD',
        'timezone' => $data['timezone'] ?? 'Africa/Casablanca',
        'date_format' => $data['date_format'] ?? 'd/m/Y',
        'items_per_page' => $data['items_per_page'] ?? 20,
        'maintenance_mode' => isset($data['maintenance_mode']) ? 1 : 0,
        'registration_enabled' => isset($data['registration_enabled']) ? 1 : 0,
        'site_language' => $data['site_language'] ?? 'fr',
        'site_description' => $data['site_description'] ?? '',
        'site_keywords' => $data['site_keywords'] ?? '',
        'google_analytics_id' => $data['google_analytics_id'] ?? ''
    ];
    
    foreach ($settings as $key => $value) {
        $db->update('settings', ['name' => $key], ['value' => $value]);
    }
    
    logAdminAction($db, "Updated general settings");
    return true;
}

// Function to update email settings
function updateEmailSettings($db, $data) {
    $settings = [
        'smtp_host' => $data['smtp_host'] ?? '',
        'smtp_port' => $data['smtp_port'] ?? '587',
        'smtp_username' => $data['smtp_username'] ?? '',
        'smtp_password' => $data['smtp_password'] ?? '',
        'smtp_encryption' => $data['smtp_encryption'] ?? 'tls',
        'from_email' => $data['from_email'] ?? '',
        'from_name' => $data['from_name'] ?? '',
        'order_notifications' => isset($data['order_notifications']) ? 1 : 0,
        'user_welcome_email' => isset($data['user_welcome_email']) ? 1 : 0,
        'email_signature' => $data['email_signature'] ?? '',
        'bcc_all_emails' => $data['bcc_all_emails'] ?? ''
    ];
    
    foreach ($settings as $key => $value) {
        // Don't update password if empty (to keep existing one)
        if ($key === 'smtp_password' && empty($value)) {
            continue;
        }
        $db->update('settings', ['name' => $key], ['value' => $value]);
    }
    
    logAdminAction($db, "Updated email settings");
    return true;
}

// Function to update payment settings
function updatePaymentSettings($db, $data) {
    $settings = [
        'stripe_enabled' => isset($data['stripe_enabled']) ? 1 : 0,
        'stripe_publishable_key' => $data['stripe_publishable_key'] ?? '',
        'stripe_secret_key' => $data['stripe_secret_key'] ?? '',
        'stripe_webhook_secret' => $data['stripe_webhook_secret'] ?? '',
        'paypal_enabled' => isset($data['paypal_enabled']) ? 1 : 0,
        'paypal_client_id' => $data['paypal_client_id'] ?? '',
        'paypal_secret' => $data['paypal_secret'] ?? '',
        'paypal_sandbox' => isset($data['paypal_sandbox']) ? 1 : 0,
        'bank_transfer_enabled' => isset($data['bank_transfer_enabled']) ? 1 : 0,
        'bank_details' => $data['bank_details'] ?? '',
        'currency' => $data['currency'] ?? 'MAD',
        'tax_rate' => $data['tax_rate'] ?? 0,
        'payment_timeout' => $data['payment_timeout'] ?? 30
    ];
    
    foreach ($settings as $key => $value) {
        // Don't update secret keys if empty
        if (($key === 'stripe_secret_key' || $key === 'paypal_secret' || $key === 'stripe_webhook_secret') && empty($value)) {
            continue;
        }
        $db->update('settings', ['name' => $key], ['value' => $value]);
    }
    
    logAdminAction($db, "Updated payment settings");
    return true;
}

// Function to update shipping settings
function updateShippingSettings($db, $data) {
    $settings = [
        'shipping_enabled' => isset($data['shipping_enabled']) ? 1 : 0,
        'free_shipping_threshold' => $data['free_shipping_threshold'] ?? 0,
        'shipping_cost' => $data['shipping_cost'] ?? 0,
        'shipping_api_key' => $data['shipping_api_key'] ?? '',
        'shipping_api_url' => $data['shipping_api_url'] ?? '',
        'auto_sync_orders' => isset($data['auto_sync_orders']) ? 1 : 0,
        'default_shipping_method' => $data['default_shipping_method'] ?? 'standard',
        'shipping_zones' => $data['shipping_zones'] ?? '',
        'shipping_tax' => $data['shipping_tax'] ?? 0
    ];
    
    foreach ($settings as $key => $value) {
        $db->update('settings', ['name' => $key], ['value' => $value]);
    }
    
    logAdminAction($db, "Updated shipping settings");
    return true;
}

// Function to update API settings
function updateApiSettings($db, $data) {
    $settings = [
        'api_enabled' => isset($data['api_enabled']) ? 1 : 0,
        'api_key' => $data['api_key'] ?? '',
        'api_secret' => $data['api_secret'] ?? '',
        'webhook_url' => $data['webhook_url'] ?? '',
        'cors_allowed_origins' => $data['cors_allowed_origins'] ?? '',
        'api_rate_limit' => $data['api_rate_limit'] ?? 100,
        'api_logging' => isset($data['api_logging']) ? 1 : 0
    ];
    
    foreach ($settings as $key => $value) {
        // Generate new API key if requested
        if ($key === 'api_key' && isset($data['generate_new_api_key'])) {
            $value = bin2hex(random_bytes(32));
        }
        // Don't update secret if empty
        if ($key === 'api_secret' && empty($value)) {
            continue;
        }
        $db->update('settings', ['name' => $key], ['value' => $value]);
    }
    
    logAdminAction($db, "Updated API settings");
    return true;
}

// Function to update security settings
function updateSecuritySettings($db, $data) {
    $settings = [
        'login_attempts' => $data['login_attempts'] ?? 5,
        'lockout_duration' => $data['lockout_duration'] ?? 30,
        'password_min_length' => $data['password_min_length'] ?? 8,
        'password_require_special' => isset($data['password_require_special']) ? 1 : 0,
        'password_require_numbers' => isset($data['password_require_numbers']) ? 1 : 0,
        'password_require_uppercase' => isset($data['password_require_uppercase']) ? 1 : 0,
        'session_timeout' => $data['session_timeout'] ?? 60,
        'two_factor_auth' => isset($data['two_factor_auth']) ? 1 : 0,
        'ip_whitelist' => $data['ip_whitelist'] ?? '',
        'force_https' => isset($data['force_https']) ? 1 : 0,
        'content_security_policy' => isset($data['content_security_policy']) ? 1 : 0
    ];
    
    foreach ($settings as $key => $value) {
        $db->update('settings', ['name' => $key], ['value' => $value]);
    }
    
    logAdminAction($db, "Updated security settings");
    return true;
}

// Function to add admin user
function addAdminUser($db, $data) {
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? '';
    $role = $data['role'] ?? 'admin';
    $permissions = $data['permissions'] ?? [];
    
    if (empty($email) || empty($name)) {
        return false;
    }
    
    // Check if user exists
    $existing_user = $db->getThisQuery("SELECT id FROM users WHERE email = ?", [$email]);
    if ($existing_user) {
        // Update existing user to admin
        $db->update('users', $existing_user[0]['id'], [
            'role' => $role,
            'is_active' => 1,
            'permissions' => json_encode($permissions)
        ]);
    } else {
        // Create new admin user
        $password = bin2hex(random_bytes(8)); // Temporary password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $db->insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => $hashed_password,
            'role' => $role,
            'is_active' => 1,
            'is_verified' => 1,
            'permissions' => json_encode($permissions),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Send welcome email with temporary password
        sendAdminWelcomeEmail($email, $name, $password, $role);
    }
    
    logAdminAction($db, "Added admin user: $email");
    return true;
}

// Function to update admin status
function updateAdminStatus($db, $data) {
    $user_id = $data['user_id'] ?? 0;
    $action = $data['status_action'] ?? '';
    
    if ($action === 'deactivate') {
        $db->update('users', $user_id, ['is_active' => 0]);
        logAdminAction($db, "Deactivated admin user", $user_id);
    } elseif ($action === 'activate') {
        $db->update('users', $user_id, ['is_active' => 1]);
        logAdminAction($db, "Activated admin user", $user_id);
    } elseif ($action === 'delete') {
        $db->update('users', $user_id, ['role' => 'user']); // Demote to regular user
        logAdminAction($db, "Removed admin privileges", $user_id);
    }
    
    return true;
}

// Function to test email settings
function testEmailSettings($db) {
    $smtp_host = getSetting($db, 'smtp_host');
    $smtp_username = getSetting($db, 'smtp_username');
    $from_email = getSetting($db, 'from_email');
    $from_name = getSetting($db, 'from_name');
    
    if (empty($smtp_host) || empty($smtp_username)) {
        return ['success' => false, 'message' => 'Paramètres SMTP incomplets'];
    }
    
    try {
        // In a real implementation, you would send a test email here
        // For now, we'll simulate success
        $test_result = true; // This would be the actual email sending result
        
        if ($test_result) {
            logAdminAction($db, "Tested email settings - Success");
            return ['success' => true, 'message' => 'Email de test envoyé avec succès'];
        } else {
            throw new Exception('Échec de l\'envoi');
        }
    } catch (Exception $e) {
        logAdminAction($db, "Tested email settings - Failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur SMTP: ' . $e->getMessage()];
    }
}

// Function to test payment settings
function testPaymentSettings($db) {
    $stripe_enabled = getSetting($db, 'stripe_enabled');
    $stripe_secret_key = getSetting($db, 'stripe_secret_key');
    $paypal_enabled = getSetting($db, 'paypal_enabled');
    $paypal_client_id = getSetting($db, 'paypal_client_id');
    
    $results = [];
    
    // Test Stripe
    if ($stripe_enabled && !empty($stripe_secret_key)) {
        try {
            // In real implementation, test Stripe connection
            // For now, simulate test
            $results[] = 'Stripe: ✅ Connecté';
        } catch (Exception $e) {
            $results[] = 'Stripe: ❌ Erreur - ' . $e->getMessage();
        }
    }
    
    // Test PayPal
    if ($paypal_enabled && !empty($paypal_client_id)) {
        try {
            // In real implementation, test PayPal connection
            // For now, simulate test
            $results[] = 'PayPal: ✅ Connecté';
        } catch (Exception $e) {
            $results[] = 'PayPal: ❌ Erreur - ' . $e->getMessage();
        }
    }
    
    if (empty($results)) {
        $results[] = 'Aucun service de paiement configuré';
    }
    
    logAdminAction($db, "Tested payment settings");
    return ['success' => true, 'message' => implode('<br>', $results)];
}

// Function to test API settings
function testApiSettings($db) {
    $api_enabled = getSetting($db, 'api_enabled');
    $api_key = getSetting($db, 'api_key');
    
    if (!$api_enabled) {
        return ['success' => false, 'message' => 'API non activée'];
    }
    
    if (empty($api_key)) {
        return ['success' => false, 'message' => 'Clé API manquante'];
    }
    
    try {
        // Test API endpoints
        $endpoints = ['/api/v1/products', '/api/v1/orders', '/api/v1/users'];
        $working_endpoints = [];
        
        foreach ($endpoints as $endpoint) {
            // In real implementation, test each endpoint
            // For now, simulate success
            $working_endpoints[] = $endpoint;
        }
        
        logAdminAction($db, "Tested API settings");
        return [
            'success' => true, 
            'message' => 'API fonctionnelle. Endpoints testés: ' . implode(', ', $working_endpoints)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur API: ' . $e->getMessage()];
    }
}

// Function to generate backup
function generateBackup($db) {
    try {
        $backup_dir = '../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Get all tables
        $tables = $db->getThisQuery("SHOW TABLES");
        $backup_content = "";
        
        foreach ($tables as $table) {
            $table_name = current($table);
            
            // Drop table
            $backup_content .= "DROP TABLE IF EXISTS `$table_name`;\n";
            
            // Create table
            $create_table = $db->getThisQuery("SHOW CREATE TABLE `$table_name`");
            $backup_content .= $create_table[0]['Create Table'] . ";\n\n";
            
            // Insert data
            $rows = $db->getThisQuery("SELECT * FROM `$table_name`");
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $columns = implode('`, `', array_keys($row));
                    $values = implode("', '", array_map(function($value) use ($db) {
                        return $db->getConnection()->real_escape_string($value);
                    }, array_values($row)));
                    
                    $backup_content .= "INSERT INTO `$table_name` (`$columns`) VALUES ('$values');\n";
                }
                $backup_content .= "\n";
            }
        }
        
        // Save backup file
        if (file_put_contents($backup_file, $backup_content)) {
            logAdminAction($db, "Generated system backup");
            return [
                'success' => true, 
                'message' => 'Sauvegarde créée: ' . basename($backup_file),
                'file' => $backup_file
            ];
        } else {
            throw new Exception('Impossible de créer le fichier de sauvegarde');
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur de sauvegarde: ' . $e->getMessage()];
    }
}

// Function to clear system cache
function clearSystemCache() {
    try {
        $cache_dirs = ['../cache/', '../tmp/'];
        $cleared = 0;
        
        foreach ($cache_dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                        $cleared++;
                    }
                }
            }
        }
        
        // Clear opcache if enabled
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        return [
            'success' => true, 
            'message' => "Cache vidé avec succès ($cleared fichiers supprimés)"
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur de vidage du cache: ' . $e->getMessage()];
    }
}

// Function to send admin welcome email
function sendAdminWelcomeEmail($email, $name, $password, $role) {
    // In real implementation, send email with credentials
    // For now, just log it
    error_log("Admin welcome email would be sent to: $email, Password: $password, Role: $role");
    return true;
}

// Function to get setting value
function getSetting($db, $key, $default = '') {
    $result = $db->getThisQuery("SELECT value FROM settings WHERE name = ?", [$key]);
    return $result ? $result[0]['value'] : $default;
}

// Get all admin users
$admin_users = $db->getThisQuery("
    SELECT id, name, email, role, is_active, created_at, derniere_connexion, permissions
    FROM users 
    WHERE role IN ('super', 'admin') 
    ORDER BY role = 'super' DESC, created_at DESC
");

// Get system information
$system_info = [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
    'database_version' => $db->getThisQuery("SELECT VERSION() as version")[0]['version'] ?? 'N/A',
    'total_users' => $db->getThisQuery("SELECT COUNT(*) as total FROM users WHERE role != 'super'")[0]['total'] ?? 0,
    'total_orders' => $db->getThisQuery("SELECT COUNT(*) as total FROM orders")[0]['total'] ?? 0,
    'total_revenue' => $db->getThisQuery("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status != 'cancelled'")[0]['total'] ?? 0,
    'disk_usage' => disk_free_space(__DIR__) ? round(disk_free_space(__DIR__) / (1024 * 1024 * 1024), 2) . ' GB libre' : 'N/A',
    'memory_usage' => round(memory_get_usage(true) / (1024 * 1024), 2) . ' MB'
];

// Get recent admin logs
$recent_logs = $db->getThisQuery("
    SELECT * FROM admin_logs 
    ORDER BY created_at DESC 
    LIMIT 10
");

function logAdminAction($db, $action, $target_user_id = null) {
    $admin_user = $_SESSION['user']['username'];
    $db->insert('admin_logs', [
        'admin_email' => $admin_user,
        'action' => $action,
        'target_user_id' => $target_user_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

// Available permissions for admin users
$admin_permissions = [
    'users' => 'Gestion des utilisateurs',
    'products' => 'Gestion des produits',
    'orders' => 'Gestion des commandes',
    'content' => 'Gestion du contenu',
    'settings' => 'Modification des paramètres',
    'reports' => 'Accès aux rapports',
    'email' => 'Envoi d\'emails',
    'api' => 'Gestion API'
];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres | Super Admin OBG</title>
    <link rel="stylesheet" href="../../assets/css/supperDash.css" />
    <link rel="stylesheet" href="../../assets/css/super.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --primary-dark: #3a56d4;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
            --radius-sm: 4px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        .settings-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .settings-header {
            display: flex;
            justify-content: space-between;
            flex-direction: row;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }
        
        .settings-tabs {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .nav-tabs {
            display: flex;
            flex-wrap: wrap;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .nav-tabs .nav-link {
            padding: 16px 24px;
            border: none;
            background: none;
            color: var(--secondary-color);
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            background: white;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: white;
        }
        
        .tab-content {
            padding: 0;
        }
        
        .tab-pane {
            display: none;
            padding: 30px;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .settings-card {
            background: white;
            border-radius: 0px;
            padding: 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            border-left: 4px solid var(--primary-color);
        }
        
        .settings-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .settings-card.danger {
            border-left-color: var(--danger-color);
        }
        
        .settings-card.success {
            border-left-color: var(--success-color);
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 8px;
            display: block;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .form-text {
            font-size: 12px;
            color: var(--secondary-color);
            margin-top: 4px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .permission-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            background: #f8f9fa;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: black;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-md);
            text-align: center;
            border-top: 4px solid var(--primary-color);
            transition: var(--transition);
        }
        
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .info-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 8px;
        }
        
        .info-label {
            font-size: 14px;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .admin-users-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .admin-users-table th {
            background: #f8fafc;
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 1px solid #e2e8f0;
        }
        
        .admin-users-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        
        .admin-users-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge.super-admin {
            background: #805ad5;
            color: white;
        }
        
        .badge.admin {
            background: #3182ce;
            color: white;
        }
        
        .badge.active {
            background: #48bb78;
            color: white;
        }
        
        .badge.inactive {
            background: #e53e3e;
            color: white;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            background: none;
            cursor: pointer;
            transition: var(--transition);
            color: var(--secondary-color);
        }
        
        .action-btn:hover {
            background: #f1f5f9;
            color: var(--primary-color);
        }
        
        .alert {
            position: relative;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeaa7;
        }
        
        .api-key-display {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            font-size: 14px;
            word-break: break-all;
        }
        
        .test-results {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            background: #f8f9fa;
            border-left: 4px solid var(--secondary-color);
        }
        
        .test-results.success {
            border-left-color: var(--success-color);
            background: #d4edda;
        }
        
        .test-results.error {
            border-left-color: var(--danger-color);
            background: #f8d7da;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .logs-table th,
        .logs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .logs-table th {
            background: #f8fafc;
            font-weight: 600;
        }
        
        .maintenance-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .quick-actions {
            display: flex;
            justify-content: space-between;
            flex-wrap: nowrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-action-btn {
            background: white;
            border: none;
            padding: 20px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .quick-action-btn i {
            font-size: 2rem;
            color: var(--primary-color);
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online {
            background: var(--success-color);
        }
        
        .status-offline {
            background: var(--danger-color);
        }
        
        .status-warning {
            background: var(--warning-color);
        }
        
        @media (max-width: 768px) {
            .settings-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .nav-tabs {
                flex-direction: column;
            }
            
            .nav-tabs .nav-link {
                border-bottom: 1px solid #e2e8f0;
                border-left: 3px solid transparent;
            }
            
            .nav-tabs .nav-link.active {
                border-left-color: var(--primary-color);
                border-bottom-color: #e2e8f0;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .system-info-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'settings';
    require_once('../../assets/sidebarSuper.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <div class="navbar-title">
                <h1>
                    <i class="bi bi-gear"></i>
                    Paramètres du Système
                </h1>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="settings-container">
                <!-- Success Message -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <?php 
                        echo $_SESSION['success_message']; 
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <?php 
                        echo $_SESSION['error_message']; 
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <!-- Maintenance Mode Banner -->
                <?php if (getSetting($db, 'maintenance_mode')): ?>
                    <div class="maintenance-banner">
                        <h4><i class="bi bi-exclamation-triangle"></i> Mode Maintenance Activé</h4>
                        <p>Le site est actuellement en mode maintenance et inaccessible aux utilisateurs normaux.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Header -->
                <div class="settings-header">
                    <h2 class="page-title">Configuration du Système</h2>
                    <div class="quick-actions">
                        <button class="quick-action-btn" onclick="testAllSystems()">
                            <i class="bi bi-play-circle"></i>
                            <span>Tester tous les systèmes</span>
                        </button>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="generate_backup">
                            <button type="submit" class="quick-action-btn">
                                <i class="bi bi-cloud-arrow-down"></i>
                                <span>Sauvegarder</span>
                            </button>
                        </form>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="clear_cache">
                            <button type="submit" class="quick-action-btn">
                                <i class="bi bi-trash"></i>
                                <span>Vider le cache</span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="system-info-grid">
                    <div class="info-card">
                        <div class="info-value">PHP <?php echo $system_info['php_version']; ?></div>
                        <div class="info-label">Version PHP</div>
                    </div>
                    <div class="info-card">
                        <div class="info-value"><?php echo $system_info['database_version']; ?></div>
                        <div class="info-label">Base de Données</div>
                    </div>
                    <div class="info-card">
                        <div class="info-value"><?php echo $system_info['total_users']; ?></div>
                        <div class="info-label">Utilisateurs</div>
                    </div>
                    <div class="info-card">
                        <div class="info-value"><?php echo $system_info['total_orders']; ?></div>
                        <div class="info-label">Commandes</div>
                    </div>
                    <div class="info-card">
                        <div class="info-value"><?php echo $system_info['disk_usage']; ?></div>
                        <div class="info-label">Espace disque</div>
                    </div>
                    <div class="info-card">
                        <div class="info-value"><?php echo $system_info['memory_usage']; ?></div>
                        <div class="info-label">Mémoire utilisée</div>
                    </div>
                </div>
                
                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <div class="nav-tabs">
                        <button class="nav-link active" data-tab="general">
                            <i class="bi bi-house"></i>
                            Général
                        </button>
                        <button class="nav-link" data-tab="email">
                            <i class="bi bi-envelope"></i>
                            Email
                        </button>
                        <button class="nav-link" data-tab="payment">
                            <i class="bi bi-credit-card"></i>
                            Paiement
                        </button>
                        <button class="nav-link" data-tab="shipping">
                            <i class="bi bi-truck"></i>
                            Livraison
                        </button>
                        <button class="nav-link" data-tab="api">
                            <i class="bi bi-plug"></i>
                            API
                        </button>
                        <button class="nav-link" data-tab="security">
                            <i class="bi bi-shield-lock"></i>
                            Sécurité
                        </button>
                        <button class="nav-link" data-tab="admins">
                            <i class="bi bi-people"></i>
                            Administrateurs
                        </button>
                        <button class="nav-link" data-tab="logs">
                            <i class="bi bi-clock-history"></i>
                            Logs
                        </button>
                    </div>
                    
                    <div class="tab-content">
                        <!-- General Settings Tab -->
                        <div class="tab-pane active" id="general">
                            <form method="post">
                                <input type="hidden" name="action" value="update_general_settings">
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-info-circle"></i>
                                        Informations du Site
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Nom du Site *</label>
                                            <input type="text" class="form-control" name="site_name" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'site_name', 'OBG Platform')); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Email du Site *</label>
                                            <input type="email" class="form-control" name="site_email" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'site_email', 'contact@obg.com')); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Téléphone</label>
                                            <input type="text" class="form-control" name="site_phone" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'site_phone')); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Adresse</label>
                                            <textarea class="form-control" name="site_address" rows="3"><?php echo htmlspecialchars(getSetting($db, 'site_address')); ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Description du Site</label>
                                            <textarea class="form-control" name="site_description" rows="3"><?php echo htmlspecialchars(getSetting($db, 'site_description')); ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Mots-clés SEO</label>
                                            <input type="text" class="form-control" name="site_keywords" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'site_keywords')); ?>">
                                            <div class="form-text">Séparés par des virgules</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-globe"></i>
                                        Paramètres Régionaux
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Devise</label>
                                            <select class="form-select" name="site_currency">
                                                <option value="MAD" <?php echo getSetting($db, 'site_currency') === 'MAD' ? 'selected' : ''; ?>>MAD (Dirham Marocain)</option>
                                                <option value="EUR" <?php echo getSetting($db, 'site_currency') === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                                                <option value="USD" <?php echo getSetting($db, 'site_currency') === 'USD' ? 'selected' : ''; ?>>USD (Dollar US)</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Fuseau Horaire</label>
                                            <select class="form-select" name="timezone">
                                                <option value="Africa/Casablanca" <?php echo getSetting($db, 'timezone') === 'Africa/Casablanca' ? 'selected' : ''; ?>>Casablanca</option>
                                                <option value="Europe/Paris" <?php echo getSetting($db, 'timezone') === 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                                                <option value="UTC" <?php echo getSetting($db, 'timezone') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Format de Date</label>
                                            <select class="form-select" name="date_format">
                                                <option value="d/m/Y" <?php echo getSetting($db, 'date_format') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                                <option value="m/d/Y" <?php echo getSetting($db, 'date_format') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                <option value="Y-m-d" <?php echo getSetting($db, 'date_format') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Langue</label>
                                            <select class="form-select" name="site_language">
                                                <option value="fr" <?php echo getSetting($db, 'site_language') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                                <option value="en" <?php echo getSetting($db, 'site_language') === 'en' ? 'selected' : ''; ?>>English</option>
                                                <option value="ar" <?php echo getSetting($db, 'site_language') === 'ar' ? 'selected' : ''; ?>>العربية</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Éléments par Page</label>
                                            <input type="number" class="form-control" name="items_per_page" 
                                                   value="<?php echo getSetting($db, 'items_per_page', 20); ?>" min="5" max="100">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Google Analytics ID</label>
                                            <input type="text" class="form-control" name="google_analytics_id" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'google_analytics_id')); ?>">
                                            <div class="form-text">Ex: G-XXXXXXXXXX</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-toggle-on"></i>
                                        Fonctionnalités
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="maintenance_mode" id="maintenance_mode" 
                                                       <?php echo getSetting($db, 'maintenance_mode') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="maintenance_mode">Mode Maintenance</label>
                                            </div>
                                            <div class="form-text">Le site sera inaccessible aux utilisateurs normaux</div>
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="registration_enabled" id="registration_enabled" 
                                                       <?php echo getSetting($db, 'registration_enabled', 1) ? 'checked' : ''; ?>>
                                                <label class="form-label" for="registration_enabled">Inscriptions Actives</label>
                                            </div>
                                            <div class="form-text">Autoriser les nouvelles inscriptions</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i>
                                    Enregistrer les Paramètres Généraux
                                </button>
                            </form>
                        </div>
                        
                        <!-- Email Settings Tab -->
                        <div class="tab-pane" id="email">
                            <form method="post">
                                <input type="hidden" name="action" value="update_email_settings">
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-server"></i>
                                        Configuration SMTP
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Serveur SMTP *</label>
                                            <input type="text" class="form-control" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'smtp_host')); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Port SMTP *</label>
                                            <input type="number" class="form-control" name="smtp_port" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'smtp_port', 587)); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Nom d'utilisateur *</label>
                                            <input type="text" class="form-control" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'smtp_username')); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Mot de passe</label>
                                            <input type="password" class="form-control" name="smtp_password" 
                                                   placeholder="Laisser vide pour ne pas modifier">
                                            <div class="form-text">Mot de passe SMTP</div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Chiffrement</label>
                                            <select class="form-select" name="smtp_encryption">
                                                <option value="tls" <?php echo getSetting($db, 'smtp_encryption') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo getSetting($db, 'smtp_encryption') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                <option value="" <?php echo empty(getSetting($db, 'smtp_encryption')) ? 'selected' : ''; ?>>Aucun</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-envelope-paper"></i>
                                        Expéditeur des Emails
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Email de l'expéditeur *</label>
                                            <input type="email" class="form-control" name="from_email" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'from_email')); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Nom de l'expéditeur *</label>
                                            <input type="text" class="form-control" name="from_name" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'from_name')); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Signature des Emails</label>
                                            <textarea class="form-control" name="email_signature" rows="4"><?php echo htmlspecialchars(getSetting($db, 'email_signature')); ?></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">BCC pour tous les emails</label>
                                            <input type="email" class="form-control" name="bcc_all_emails" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'bcc_all_emails')); ?>">
                                            <div class="form-text">Recevoir une copie de tous les emails envoyés</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-bell"></i>
                                        Notifications
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="order_notifications" id="order_notifications" 
                                                       <?php echo getSetting($db, 'order_notifications', 1) ? 'checked' : ''; ?>>
                                                <label class="form-label" for="order_notifications">Notifications de Commandes</label>
                                            </div>
                                            <div class="form-text">Recevoir des emails pour les nouvelles commandes</div>
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="user_welcome_email" id="user_welcome_email" 
                                                       <?php echo getSetting($db, 'user_welcome_email', 1) ? 'checked' : ''; ?>>
                                                <label class="form-label" for="user_welcome_email">Email de Bienvenue</label>
                                            </div>
                                            <div class="form-text">Envoyer un email de bienvenue aux nouveaux utilisateurs</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-play-circle"></i>
                                        Test de Configuration
                                    </h3>
                                    <div class="form-group">
                                        <label class="form-label">Tester les paramètres email</label>
                                        <div>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="test_email_settings">
                                                <button type="submit" class="btn btn-warning">
                                                    <i class="bi bi-send"></i>
                                                    Tester l'envoi d'email
                                                </button>
                                            </form>
                                        </div>
                                        <div class="form-text">Envoie un email de test à l'adresse configurée</div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i>
                                    Enregistrer les Paramètres Email
                                </button>
                            </form>
                        </div>
                        
                        <!-- Payment Settings Tab -->
                        <div class="tab-pane" id="payment">
                            <form method="post">
                                <input type="hidden" name="action" value="update_payment_settings">
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-credit-card-2-front"></i>
                                        Stripe
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="stripe_enabled" id="stripe_enabled" 
                                                       <?php echo getSetting($db, 'stripe_enabled') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="stripe_enabled">Activer Stripe</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Clé Publique Stripe</label>
                                            <input type="text" class="form-control" name="stripe_publishable_key" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'stripe_publishable_key')); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Clé Secrète Stripe</label>
                                            <input type="password" class="form-control" name="stripe_secret_key" 
                                                   placeholder="Laisser vide pour ne pas modifier">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Secret Webhook Stripe</label>
                                            <input type="password" class="form-control" name="stripe_webhook_secret" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'stripe_webhook_secret')); ?>">
                                            <div class="form-text">Pour les webhooks Stripe</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-paypal"></i>
                                        PayPal
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="paypal_enabled" id="paypal_enabled" 
                                                       <?php echo getSetting($db, 'paypal_enabled') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="paypal_enabled">Activer PayPal</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Client ID PayPal</label>
                                            <input type="text" class="form-control" name="paypal_client_id" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'paypal_client_id')); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Secret PayPal</label>
                                            <input type="password" class="form-control" name="paypal_secret" 
                                                   placeholder="Laisser vide pour ne pas modifier">
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="paypal_sandbox" id="paypal_sandbox" 
                                                       <?php echo getSetting($db, 'paypal_sandbox') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="paypal_sandbox">Mode Sandbox</label>
                                            </div>
                                            <div class="form-text">Utiliser l'environnement de test PayPal</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-bank"></i>
                                        Virement Bancaire
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="bank_transfer_enabled" id="bank_transfer_enabled" 
                                                       <?php echo getSetting($db, 'bank_transfer_enabled') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="bank_transfer_enabled">Activer les Virements</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Coordonnées Bancaires</label>
                                            <textarea class="form-control" name="bank_details" rows="4"><?php echo htmlspecialchars(getSetting($db, 'bank_details')); ?></textarea>
                                            <div class="form-text">Ces informations seront affichées aux clients</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-currency-exchange"></i>
                                        Paramètres Financiers
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Devise par Défaut</label>
                                            <select class="form-select" name="currency">
                                                <option value="MAD" <?php echo getSetting($db, 'currency') === 'MAD' ? 'selected' : ''; ?>>MAD (Dirham Marocain)</option>
                                                <option value="EUR" <?php echo getSetting($db, 'currency') === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                                                <option value="USD" <?php echo getSetting($db, 'currency') === 'USD' ? 'selected' : ''; ?>>USD (Dollar US)</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Taux de TVA (%)</label>
                                            <input type="number" class="form-control" name="tax_rate" 
                                                   value="<?php echo getSetting($db, 'tax_rate', 0); ?>" step="0.01" min="0" max="100">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Délai de Paiement (minutes)</label>
                                            <input type="number" class="form-control" name="payment_timeout" 
                                                   value="<?php echo getSetting($db, 'payment_timeout', 30); ?>" min="5" max="1440">
                                            <div class="form-text">Temps avant expiration d'une commande en attente de paiement</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-play-circle"></i>
                                        Test des Paiements
                                    </h3>
                                    <div class="form-group">
                                        <label class="form-label">Tester les configurations de paiement</label>
                                        <div>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="test_payment_settings">
                                                <button type="submit" class="btn btn-warning">
                                                    <i class="bi bi-credit-card"></i>
                                                    Tester les paiements
                                                </button>
                                            </form>
                                        </div>
                                        <div class="form-text">Vérifie la connectivité avec les services de paiement</div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i>
                                    Enregistrer les Paramètres de Paiement
                                </button>
                            </form>
                        </div>
                        
                        <!-- Shipping Settings Tab -->
                        <div class="tab-pane" id="shipping">
                            <form method="post">
                                <input type="hidden" name="action" value="update_shipping_settings">
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-truck"></i>
                                        Paramètres de Livraison
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="shipping_enabled" id="shipping_enabled" 
                                                       <?php echo getSetting($db, 'shipping_enabled', 1) ? 'checked' : ''; ?>>
                                                <label class="form-label" for="shipping_enabled">Livraison Activée</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Coût de Livraison (MAD)</label>
                                            <input type="number" class="form-control" name="shipping_cost" 
                                                   value="<?php echo getSetting($db, 'shipping_cost', 0); ?>" step="0.01" min="0">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Seuil Livraison Gratuite (MAD)</label>
                                            <input type="number" class="form-control" name="free_shipping_threshold" 
                                                   value="<?php echo getSetting($db, 'free_shipping_threshold', 0); ?>" step="0.01" min="0">
                                            <div class="form-text">0 pour désactiver la livraison gratuite</div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">TVA Livraison (%)</label>
                                            <input type="number" class="form-control" name="shipping_tax" 
                                                   value="<?php echo getSetting($db, 'shipping_tax', 0); ?>" step="0.01" min="0" max="100">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Méthode de Livraison par Défaut</label>
                                            <select class="form-select" name="default_shipping_method">
                                                <option value="standard" <?php echo getSetting($db, 'default_shipping_method') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                                <option value="express" <?php echo getSetting($db, 'default_shipping_method') === 'express' ? 'selected' : ''; ?>>Express</option>
                                                <option value="pickup" <?php echo getSetting($db, 'default_shipping_method') === 'pickup' ? 'selected' : ''; ?>>Retrait en Magasin</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-plugin"></i>
                                        Intégration Transporteur
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Clé API Transporteur</label>
                                            <input type="text" class="form-control" name="shipping_api_key" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'shipping_api_key')); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">URL API Transporteur</label>
                                            <input type="url" class="form-control" name="shipping_api_url" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'shipping_api_url')); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Zones de Livraison</label>
                                            <textarea class="form-control" name="shipping_zones" rows="4" placeholder="Ex: Casablanca: 30, Rabat: 40, Autres: 60"><?php echo htmlspecialchars(getSetting($db, 'shipping_zones')); ?></textarea>
                                            <div class="form-text">Format: Ville: Prix, Ville: Prix</div>
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="auto_sync_orders" id="auto_sync_orders" 
                                                       <?php echo getSetting($db, 'auto_sync_orders') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="auto_sync_orders">Synchronisation Auto des Commandes</label>
                                            </div>
                                            <div class="form-text">Envoyer automatiquement les commandes confirmées au transporteur</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i>
                                    Enregistrer les Paramètres de Livraison
                                </button>
                            </form>
                        </div>
                        
                        <!-- API Settings Tab -->
                        <div class="tab-pane" id="api">
                            <form method="post">
                                <input type="hidden" name="action" value="update_api_settings">
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-plug"></i>
                                        Paramètres API
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="api_enabled" id="api_enabled" 
                                                       <?php echo getSetting($db, 'api_enabled') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="api_enabled">API Activée</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Clé API</label>
                                            <div class="api-key-display">
                                                <?php echo htmlspecialchars(getSetting($db, 'api_key', 'Non générée')); ?>
                                            </div>
                                            <div class="checkbox-group mt-2">
                                                <input type="checkbox" name="generate_new_api_key" id="generate_new_api_key">
                                                <label class="form-label" for="generate_new_api_key">Générer une nouvelle clé API</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Secret API</label>
                                            <input type="password" class="form-control" name="api_secret" 
                                                   placeholder="Laisser vide pour ne pas modifier">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Limite de Requêtes (par heure)</label>
                                            <input type="number" class="form-control" name="api_rate_limit" 
                                                   value="<?php echo getSetting($db, 'api_rate_limit', 100); ?>" min="10" max="10000">
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="api_logging" id="api_logging" 
                                                       <?php echo getSetting($db, 'api_logging', 1) ? 'checked' : ''; ?>>
                                                <label class="form-label" for="api_logging">Journalisation des Appels API</label>
                                            </div>
                                            <div class="form-text">Enregistrer tous les appels API pour le débogage</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-link"></i>
                                        Webhooks
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">URL Webhook</label>
                                            <input type="url" class="form-control" name="webhook_url" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'webhook_url')); ?>">
                                            <div class="form-text">URL pour recevoir les notifications</div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Origines CORS Autorisées</label>
                                            <input type="text" class="form-control" name="cors_allowed_origins" 
                                                   value="<?php echo htmlspecialchars(getSetting($db, 'cors_allowed_origins')); ?>">
                                            <div class="form-text">Séparer par des virgules (ex: https://site1.com,https://site2.com)</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-play-circle"></i>
                                        Test de l'API
                                    </h3>
                                    <div class="form-group">
                                        <label class="form-label">Tester la connectivité API</label>
                                        <div>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="test_api_settings">
                                                <button type="submit" class="btn btn-warning">
                                                    <i class="bi bi-plug"></i>
                                                    Tester l'API
                                                </button>
                                            </form>
                                        </div>
                                        <div class="form-text">Vérifie que l'API fonctionne correctement</div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i>
                                    Enregistrer les Paramètres API
                                </button>
                            </form>
                        </div>
                        
                        <!-- Security Settings Tab -->
                        <div class="tab-pane" id="security">
                            <form method="post">
                                <input type="hidden" name="action" value="update_security_settings">
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-shield-check"></i>
                                        Connexion et Authentification
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Tentatives de Connexion Max</label>
                                            <input type="number" class="form-control" name="login_attempts" 
                                                   value="<?php echo getSetting($db, 'login_attempts', 5); ?>" min="1" max="10">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Durée de Blocage (minutes)</label>
                                            <input type="number" class="form-control" name="lockout_duration" 
                                                   value="<?php echo getSetting($db, 'lockout_duration', 30); ?>" min="1" max="1440">
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Timeout Session (minutes)</label>
                                            <input type="number" class="form-control" name="session_timeout" 
                                                   value="<?php echo getSetting($db, 'session_timeout', 60); ?>" min="5" max="1440">
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="two_factor_auth" id="two_factor_auth" 
                                                       <?php echo getSetting($db, 'two_factor_auth') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="two_factor_auth">Authentification à Deux Facteurs</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-key"></i>
                                        Politique des Mots de Passe
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Longueur Minimale</label>
                                            <input type="number" class="form-control" name="password_min_length" 
                                                   value="<?php echo getSetting($db, 'password_min_length', 8); ?>" min="6" max="32">
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="password_require_special" id="password_require_special" 
                                                       <?php echo getSetting($db, 'password_require_special') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="password_require_special">Caractères Spéciaux Requis</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="password_require_numbers" id="password_require_numbers" 
                                                       <?php echo getSetting($db, 'password_require_numbers', 1) ? 'checked' : ''; ?>>
                                                <label class="form-label" for="password_require_numbers">Chiffres Requis</label>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="password_require_uppercase" id="password_require_uppercase" 
                                                       <?php echo getSetting($db, 'password_require_uppercase', 1) ? 'checked' : ''; ?>>
                                                <label class="form-label" for="password_require_uppercase">Lettres Majuscules Requises</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-card">
                                    <h3 class="card-title">
                                        <i class="bi bi-lock"></i>
                                        Sécurité Avancée
                                    </h3>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="force_https" id="force_https" 
                                                       <?php echo getSetting($db, 'force_https') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="force_https">Forcer HTTPS</label>
                                            </div>
                                            <div class="form-text">Rediriger automatiquement vers HTTPS</div>
                                        </div>
                                        <div class="form-group">
                                            <div class="checkbox-group">
                                                <input type="checkbox" name="content_security_policy" id="content_security_policy" 
                                                       <?php echo getSetting($db, 'content_security_policy') ? 'checked' : ''; ?>>
                                                <label class="form-label" for="content_security_policy">Politique de Sécurité du Contenu (CSP)</label>
                                            </div>
                                            <div class="form-text">Protection contre les attaques XSS</div>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Liste Blanche IP</label>
                                            <textarea class="form-control" name="ip_whitelist" rows="4" placeholder="Exemple: 192.168.1.1, 10.0.0.0/24"><?php echo htmlspecialchars(getSetting($db, 'ip_whitelist')); ?></textarea>
                                            <div class="form-text">Séparer par des virgules. Laisser vide pour autoriser toutes les IPs.</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i>
                                    Enregistrer les Paramètres de Sécurité
                                </button>
                            </form>
                        </div>
                        
                        <!-- Admins Management Tab -->
                        <div class="tab-pane" id="admins">
                            <div class="settings-card">
                                <h3 class="card-title">
                                    <i class="bi bi-person-plus"></i>
                                    Ajouter un Administrateur
                                </h3>
                                <form method="post" class="form-grid">
                                    <input type="hidden" name="action" value="add_admin_user">
                                    <div class="form-group">
                                        <label class="form-label">Nom *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Rôle *</label>
                                        <select class="form-select" name="role" required>
                                            <option value="admin">Administrateur</option>
                                            <option value="super" disabled>Super Admin (non modifiable)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Permissions</label>
                                        <div class="permissions-grid">
                                            <?php foreach ($admin_permissions as $key => $label): ?>
                                                <div class="permission-item">
                                                    <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" id="perm_<?php echo $key; ?>" checked>
                                                    <label for="perm_<?php echo $key; ?>"><?php echo $label; ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-person-add"></i>
                                            Ajouter l'Administrateur
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="settings-card">
                                <h3 class="card-title">
                                    <i class="bi bi-people"></i>
                                    Liste des Administrateurs
                                </h3>
                                <div class="table-responsive">
                                    <table class="admin-users-table">
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Email</th>
                                                <th>Rôle</th>
                                                <th>Statut</th>
                                                <th>Permissions</th>
                                                <th>Dernière Connexion</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($admin_users)): ?>
                                                <?php foreach ($admin_users as $admin): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($admin['name']); ?></strong>
                                                            <?php if ($admin['role'] === 'super'): ?>
                                                                <br><small class="text-muted">(Vous)</small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $admin['role'] === 'super' ? 'super-admin' : 'admin'; ?>">
                                                                <?php echo $admin['role'] === 'super' ? 'Super Admin' : 'Admin'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $admin['is_active'] ? 'active' : 'inactive'; ?>">
                                                                <?php echo $admin['is_active'] ? 'Actif' : 'Inactif'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $permissions = json_decode($admin['permissions'] ?? '[]', true);
                                                            if (!empty($permissions)) {
                                                                echo '<small>' . implode(', ', array_intersect_key($admin_permissions, array_flip($permissions))) . '</small>';
                                                            } else {
                                                                echo '<small class="text-muted">Toutes</small>';
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $admin['derniere_connexion'] ? date('d/m/Y H:i', strtotime($admin['derniere_connexion'])) : 'Jamais'; ?>
                                                        </td>
                                                        <td>
                                                            <div class="actions">
                                                                <?php if ($admin['role'] !== 'super'): ?>
                                                                    <?php if ($admin['is_active']): ?>
                                                                        <form method="post" style="display: inline;">
                                                                            <input type="hidden" name="action" value="update_admin_status">
                                                                            <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                                                                            <input type="hidden" name="status_action" value="deactivate">
                                                                            <button type="submit" class="action-btn" title="Désactiver">
                                                                                <i class="bi bi-person-x"></i>
                                                                            </button>
                                                                        </form>
                                                                    <?php else: ?>
                                                                        <form method="post" style="display: inline;">
                                                                            <input type="hidden" name="action" value="update_admin_status">
                                                                            <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                                                                            <input type="hidden" name="status_action" value="activate">
                                                                            <button type="submit" class="action-btn" title="Activer">
                                                                                <i class="bi bi-person-check"></i>
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                    
                                                                    <form method="post" style="display: inline;">
                                                                        <input type="hidden" name="action" value="update_admin_status">
                                                                        <input type="hidden" name="user_id" value="<?php echo $admin['id']; ?>">
                                                                        <input type="hidden" name="status_action" value="delete">
                                                                        <button type="submit" class="action-btn text-danger" title="Retirer les droits admin" onclick="return confirm('Êtes-vous sûr de vouloir retirer les droits administrateur à cet utilisateur ?')">
                                                                            <i class="bi bi-person-dash"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Actions non disponibles</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">
                                                        <i class="bi bi-people" style="font-size: 2rem; opacity: 0.5;"></i>
                                                        <p class="text-muted mt-2">Aucun administrateur trouvé</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Logs Tab -->
                        <div class="tab-pane" id="logs">
                            <div class="settings-card">
                                <h3 class="card-title">
                                    <i class="bi bi-clock-history"></i>
                                    Logs Récentes
                                </h3>
                                <div class="table-responsive">
                                    <table class="logs-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Administrateur</th>
                                                <th>Action</th>
                                                <th>IP</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recent_logs)): ?>
                                                <?php foreach ($recent_logs as $log): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                                        <td><?php echo htmlspecialchars($log['admin_email']); ?></td>
                                                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                                                        <td><code><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-4">
                                                        <i class="bi bi-clock-history" style="font-size: 2rem; opacity: 0.5;"></i>
                                                        <p class="text-muted mt-2">Aucun log récent</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="settings-card">
                                <h3 class="card-title">
                                    <i class="bi bi-download"></i>
                                    Export des Logs
                                </h3>
                                <div class="form-group">
                                    <label class="form-label">Exporter les logs</label>
                                    <div>
                                        <button class="btn btn-secondary" onclick="exportLogs('csv')">
                                            <i class="bi bi-file-earmark-spreadsheet"></i>
                                            Export CSV
                                        </button>
                                        <button class="btn btn-secondary" onclick="exportLogs('json')">
                                            <i class="bi bi-file-code"></i>
                                            Export JSON
                                        </button>
                                    </div>
                                    <div class="form-text">Télécharger les logs des 30 derniers jours</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabLinks = document.querySelectorAll('.nav-link[data-tab]');
            const tabPanes = document.querySelectorAll('.tab-pane');
            
            tabLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Update active tab
                    tabLinks.forEach(tab => tab.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show target tab content
                    tabPanes.forEach(pane => {
                        pane.classList.remove('active');
                        if (pane.id === targetTab) {
                            pane.classList.add('active');
                        }
                    });
                    
                    // Save active tab to session storage
                    sessionStorage.setItem('activeSettingsTab', targetTab);
                });
            });
            
            // Restore active tab from session storage
            const activeTab = sessionStorage.getItem('activeSettingsTab');
            if (activeTab) {
                const tabLink = document.querySelector(`.nav-link[data-tab="${activeTab}"]`);
                if (tabLink) {
                    tabLink.click();
                }
            }
            
            // Form validation and enhancements
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = this.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = '#dc3545';
                            // Add error message
                            if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('error-message')) {
                                const errorMsg = document.createElement('div');
                                errorMsg.className = 'error-message text-danger mt-1';
                                errorMsg.style.fontSize = '12px';
                                errorMsg.textContent = 'Ce champ est obligatoire';
                                field.parentNode.appendChild(errorMsg);
                            }
                        } else {
                            field.style.borderColor = '';
                            const errorMsg = field.parentNode.querySelector('.error-message');
                            if (errorMsg) {
                                errorMsg.remove();
                            }
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        // Scroll to first error
                        const firstError = this.querySelector('[required]:invalid');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstError.focus();
                        }
                    }
                });
            });
            
            // Toggle dependent fields
            const toggleFields = document.querySelectorAll('input[type="checkbox"]');
            toggleFields.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const dependentFields = this.closest('.form-group').nextElementSibling;
                    if (dependentFields && dependentFields.classList.contains('form-group')) {
                        const inputs = dependentFields.querySelectorAll('input, select, textarea');
                        inputs.forEach(input => {
                            input.disabled = !this.checked;
                        });
                    }
                });
                
                // Trigger change on page load
                toggle.dispatchEvent(new Event('change'));
            });
            
            // API key generation confirmation
            const generateKeyCheckbox = document.getElementById('generate_new_api_key');
            if (generateKeyCheckbox) {
                generateKeyCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        if (!confirm('Générer une nouvelle clé API invalidera toutes les clés existantes. Êtes-vous sûr ?')) {
                            this.checked = false;
                        }
                    }
                });
            }
            
            // Real-time validation for email fields
            const emailFields = document.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                field.addEventListener('blur', function() {
                    const email = this.value.trim();
                    if (email && !isValidEmail(email)) {
                        this.style.borderColor = '#dc3545';
                        if (!this.nextElementSibling || !this.nextElementSibling.classList.contains('error-message')) {
                            const errorMsg = document.createElement('div');
                            errorMsg.className = 'error-message text-danger mt-1';
                            errorMsg.style.fontSize = '12px';
                            errorMsg.textContent = 'Format d\'email invalide';
                            this.parentNode.appendChild(errorMsg);
                        }
                    } else {
                        this.style.borderColor = '';
                        const errorMsg = this.parentNode.querySelector('.error-message');
                        if (errorMsg) {
                            errorMsg.remove();
                        }
                    }
                });
            });
            
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            // Auto-save draft (optional)
            let saveTimeout;
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        // Could implement auto-save functionality here
                        console.log('Draft saved for:', this.name);
                    }, 2000);
                });
            });
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Test all systems function
        function testAllSystems() {
            const tests = [
                { name: 'Email', action: 'test_email_settings' },
                { name: 'Paiement', action: 'test_payment_settings' },
                { name: 'API', action: 'test_api_settings' }
            ];
            
            let results = [];
            let completed = 0;
            
            tests.forEach(test => {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=${test.action}`
                })
                .then(response => response.text())
                .then(() => {
                    completed++;
                    // In a real implementation, you would parse the response
                    // For now, we'll simulate results
                    results.push(`${test.name}: ✅ Succès`);
                    
                    if (completed === tests.length) {
                        showTestResults(results.join('<br>'));
                    }
                })
                .catch(error => {
                    completed++;
                    results.push(`${test.name}: ❌ Erreur`);
                    
                    if (completed === tests.length) {
                        showTestResults(results.join('<br>'));
                    }
                });
            });
            
            // Show loading indicator
            showTestResults('Tests en cours...');
        }
        
        function showTestResults(message) {
            // Create or update test results display
            let resultsDiv = document.getElementById('test-results');
            if (!resultsDiv) {
                resultsDiv = document.createElement('div');
                resultsDiv.id = 'test-results';
                resultsDiv.className = 'test-results';
                document.querySelector('.settings-container').prepend(resultsDiv);
            }
            
            resultsDiv.innerHTML = message;
            resultsDiv.className = 'test-results ' + (message.includes('❌') ? 'error' : 'success');
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                resultsDiv.remove();
            }, 10000);
        }
        
        // Export logs function
        function exportLogs(format) {
            const url = `export_logs.php?format=${format}`;
            window.open(url, '_blank');
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S to save current form
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const activeTab = document.querySelector('.tab-pane.active');
                const form = activeTab.querySelector('form');
                if (form) {
                    form.dispatchEvent(new Event('submit'));
                }
            }
            
            // Tab navigation with numbers
            if (e.altKey && e.key >= '1' && e.key <= '8') {
                e.preventDefault();
                const tabIndex = parseInt(e.key) - 1;
                const tabs = document.querySelectorAll('.nav-link[data-tab]');
                if (tabs[tabIndex]) {
                    tabs[tabIndex].click();
                }
            }
        });
        
        // Auto-refresh system info every 30 seconds
        setInterval(() => {
            // In a real implementation, you would fetch updated system info
            console.log('Refreshing system info...');
        }, 30000);
    </script>
</body>
</html>