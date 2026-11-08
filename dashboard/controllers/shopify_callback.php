<?php

require_once('../core/init.php');
require_once('./config/e_config.php');

$db = DB::getInstance();

$code = $_GET['code'] ?? null;
$shop = $_GET['shop'] ?? null;
$state = $_GET['state'] ?? null;

// Debug: Log what we received
error_log("Callback received - Shop: $shop, Code: " . ($code ? 'present' : 'missing') . ", State: $state");

if (!$code || !$shop || !$state) {
    error_log("OAuth validation failed - Missing parameters");
    showErrorAndRedirect('Paramètres OAuth invalides');
}

// Check if this is a reconnection (state contains store_id) or new connection
$is_reconnection = is_numeric($state);
$store_id = $is_reconnection ? (int)$state : null;

// For new connections, verify state matches session
if (!$is_reconnection && $state !== ($_SESSION['oauth_state'] ?? null)) {
    error_log("OAuth validation failed - Session state: " . ($_SESSION['oauth_state'] ?? 'not set'));
    showErrorAndRedirect('Paramètres OAuth invalides');
}

// Get Shopify app credentials
$client_id = SHOPIFY_CLIENT_ID;
$client_secret = SHOPIFY_CLIENT_SECRET;

// Exchange code for access token - USE JSON NOT HTTP_BUILD_QUERY
$token_url = "https://$shop/admin/oauth/access_token";
$post_fields = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code, 
    'expiring' => 1  
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields)); // USE JSON
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json' // JSON header
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Debug: Log the response
error_log("Shopify response (HTTP $http_code): " . substr($response, 0, 500));

// Check for CURL error
if ($curl_error) {
    error_log("CURL Error: $curl_error");
    showErrorAndRedirect("Erreur de connexion: $curl_error");
}

// Parse response
$result = json_decode($response, true);

// Check if we got an access token
if (!isset($result['access_token'])) {
    $error_msg = $result['error'] ?? $result['error_description'] ?? 'Unknown error';
    error_log("Token exchange failed: $error_msg - Full response: $response");
    showErrorAndRedirect("Impossible d'obtenir le token: $error_msg");
}

$access_token = $result['access_token'];
$refresh_token = $result['refresh_token'] ?? null;
$expires_in = $result['expires_in'] ?? 86400;
$expires_at = date('Y-m-d H:i:s', time() + $expires_in);

// Get current user
$user_id = getCurrentUserId($db) ?? null;

if (!$user_id) {
    showErrorAndRedirect('Utilisateur non authentifié');
}

try {
    // Handle reconnection or new connection
    if ($is_reconnection) {
        // ---- RECONNECTION FLOW ----
        error_log("Processing Shopify reconnection for store_id: $store_id, user: $user_id");
        
        // Verify store belongs to user
        $existing_store = $db->getThisQuery(
            "SELECT id, storeName, domain FROM stores WHERE id = ? AND user_id = ? AND platform = 'shopify'",
            [$store_id, $user_id]
        );
        
        if (empty($existing_store)) {
            error_log("Store not found or doesn't belong to user - Store ID: $store_id, User: $user_id");
            showErrorAndRedirect('Boutique non trouvée ou vous n\'avez pas les droits');
        }
        
        $store = $existing_store[0];
        
        // Begin transaction
        $db->beginTransaction();
        
        // Update store with new tokens
        $update_data = [
            'api_key' => $access_token,
            'refresh_token' => $refresh_token,
            'token_expires_at' => $expires_at,
            'is_connected' => 1,
            'connection_error' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $updated = $db->update('stores', $store_id, $update_data);
        
        if ($updated) {
            // Update or insert token in shopify_tokens table
            $token_table_exists = $db->getThisQuery("SHOW TABLES LIKE 'shopify_tokens'");
            if (!empty($token_table_exists)) {
                // Check if token exists
                $existing_token = $db->getThisQuery(
                    "SELECT id FROM shopify_tokens WHERE store_id = ? AND user_id = ?",
                    [$store_id, $user_id]
                );
                
                if (!empty($existing_token)) {
                    // Update existing token
                    $db->update('shopify_tokens', $existing_token[0]['id'], [
                        'access_token' => $access_token,
                        'refresh_token' => $refresh_token,
                        'expires_at' => $expires_at,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    // Insert new token
                    $db->insert('shopify_tokens', [
                        'store_id' => $store_id,
                        'user_id' => $user_id,
                        'access_token' => $access_token,
                        'refresh_token' => $refresh_token,
                        'expires_at' => $expires_at,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            $db->commit();
            
            error_log("Shopify store reconnected successfully - User: $user_id, Store: {$store['storeName']}");
            
            // Clear OAuth session data
            unset($_SESSION['oauth_state']);
            unset($_SESSION['store_name']);
            unset($_SESSION['store_domain']);
            
            // Redirect to settings page with success message
            header("Location: ../settings/stores?success=reconnected");
            exit;
        } else {
            $db->rollBack();
            error_log("Failed to update store during reconnection - Store ID: $store_id");
            showErrorAndRedirect('Erreur lors de la reconnexion de la boutique');
        }
        
    } else {
        // ---- NEW CONNECTION FLOW ----
        error_log("Processing new Shopify connection for user: $user_id");
        
        $store_name = $_SESSION['store_name'] ?? null;
        $store_domain = $_SESSION['store_domain'] ?? null;
        
        if (!$store_name || !$store_domain) {
            showErrorAndRedirect('Informations de boutique manquantes');
        }
        
        // Check if store already exists
        $existing_store = $db->getThisQuery(
            "SELECT id FROM stores WHERE user_id = ? AND domain = ? AND platform = 'shopify'",
            [$user_id, $store_domain]
        );
        
        if (!empty($existing_store)) {
            showErrorAndRedirect('Cette boutique existe déjà pour cet utilisateur', 'warning');
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        // Insert new store
        $store_data = [
            'user_id' => $user_id,
            'storeName' => $store_name,
            'store_name' => $store_name,
            'platform' => 'shopify',
            'domain' => $store_domain,
            'api_key' => $access_token,
            'refresh_token' => $refresh_token,
            'token_expires_at' => $expires_at,
            'Consumer_key' => $client_id,
            'Consumer_secret' => $client_secret,
            'is_connected' => 1,
            'connected_at' => date('Y-m-d H:i:s')
        ];

        $inserted = $db->insert('stores', $store_data);

        if ($inserted) {
            $store_id = $db->getLastInsertId();
            
            // Store token in shopify_tokens table
            $token_table_exists = $db->getThisQuery("SHOW TABLES LIKE 'shopify_tokens'");
            if (!empty($token_table_exists)) {
                $db->insert('shopify_tokens', [
                    'store_id' => $store_id,
                    'user_id' => $user_id,
                    'access_token' => $access_token,
                    'refresh_token' => $refresh_token,
                    'expires_at' => $expires_at,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Update onboarding progress
            $onboarding_updated = updateUserOnboarding($db, $user_id, 'step_store', true);
            
            if (!$onboarding_updated) {
                error_log("Failed to update onboarding progress for user: $user_id");
            }
            
            $db->commit();
            
            error_log("Shopify store connected successfully - User: $user_id, Store: $store_name");
            
            // Clear OAuth session data
            unset($_SESSION['oauth_state']);
            unset($_SESSION['store_name']);
            unset($_SESSION['store_domain']);
            
            header("Location: ../onboarding?success=1&platform=shopify");
            exit;
        } else {
            $db->rollBack();
            showErrorAndRedirect('Erreur lors de l\'ajout de la boutique');
        }
    }
    
} catch (Exception $e) {
    if (isset($db) && method_exists($db, 'rollBack')) {
        $db->rollBack();
    }
    error_log("Exception during Shopify OAuth: " . $e->getMessage());
    showErrorAndRedirect('Erreur système: ' . $e->getMessage());
}

// Helper functions
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

function updateUserOnboarding($db, $user_id, $step, $completed = true) {
    try {
        $existing_onboarding = $db->getThisQuery(
            "SELECT id, step_store, step_shipping, step_products, completed 
             FROM user_onboarding 
             WHERE user_id = ?",
            [$user_id]
        );
        
        $current_time = date('Y-m-d H:i:s');
        
        if (!empty($existing_onboarding)) {
            $onboarding_id = $existing_onboarding[0]['id'];
            $current_data = $existing_onboarding[0];
            
            $update_data = ['updated_at' => $current_time];
            
            switch ($step) {
                case 'step_store':
                    $update_data['step_store'] = $completed ? 1 : 0;
                    break;
                case 'step_shipping':
                    $update_data['step_shipping'] = $completed ? 1 : 0;
                    break;
                case 'step_products':
                    $update_data['step_products'] = $completed ? 1 : 0;
                    break;
            }
            
            $step_store = $step === 'step_store' ? ($completed ? 1 : 0) : ($current_data['step_store'] ?? 0);
            $step_shipping = $step === 'step_shipping' ? ($completed ? 1 : 0) : ($current_data['step_shipping'] ?? 0);
            $step_products = $step === 'step_products' ? ($completed ? 1 : 0) : ($current_data['step_products'] ?? 0);
            
            $all_completed = ($step_store && $step_shipping && $step_products) ? 1 : 0;
            $update_data['completed'] = $all_completed;
            
            $updated = $db->update('user_onboarding', $update_data, ['id' => $onboarding_id]);
            
            if ($updated) {
                error_log("Onboarding updated for user $user_id - Step: $step");
                return true;
            }
            return false;
        } else {
            $onboarding_data = [
                'user_id' => $user_id,
                'step_store' => 0,
                'step_shipping' => 0,
                'step_products' => 0,
                'completed' => 0,
                'created_at' => $current_time,
                'updated_at' => $current_time
            ];
            
            switch ($step) {
                case 'step_store':
                    $onboarding_data['step_store'] = $completed ? 1 : 0;
                    break;
                case 'step_shipping':
                    $onboarding_data['step_shipping'] = $completed ? 1 : 0;
                    break;
                case 'step_products':
                    $onboarding_data['step_products'] = $completed ? 1 : 0;
                    break;
            }
            
            $onboarding_data['completed'] = ($onboarding_data['step_store'] && $onboarding_data['step_shipping'] && $onboarding_data['step_products']) ? 1 : 0;
            
            $inserted = $db->insert('user_onboarding', $onboarding_data);
            
            if ($inserted) {
                error_log("New onboarding record created for user $user_id - Step: $step");
                return true;
            }
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception in updateUserOnboarding: " . $e->getMessage());
        return false;
    }
}

function showErrorAndRedirect($message, $type = 'error') {
    $icon = $type === 'warning' ? '⚠️' : '❌';
    $bgColor = $type === 'warning' ? '#ff9800' : '#f44336';
    
    echo "<!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Erreur OAuth</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .message-container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 500px;
                width: 100%;
                padding: 40px;
                text-align: center;
                animation: slideIn 0.4s ease-out;
            }
            @keyframes slideIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .icon {
                font-size: 64px;
                margin-bottom: 20px;
                animation: bounce 0.6s ease-in-out;
            }
            @keyframes bounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }
            h1 {
                color: #333;
                font-size: 24px;
                margin-bottom: 16px;
            }
            p {
                color: #666;
                font-size: 16px;
                line-height: 1.6;
                margin-bottom: 30px;
            }
            .redirect-info {
                color: #999;
                font-size: 14px;
                margin-top: 20px;
            }
            .progress-bar {
                width: 100%;
                height: 4px;
                background: #f0f0f0;
                border-radius: 2px;
                overflow: hidden;
                margin-top: 20px;
            }
            .progress-fill {
                height: 100%;
                background: $bgColor;
                animation: progress 3s linear;
            }
            @keyframes progress {
                from { width: 0%; }
                to { width: 100%; }
            }
        </style>
    </head>
    <body>
        <div class='message-container'>
            <div class='icon'>$icon</div>
            <h1>" . ($type === 'warning' ? 'Attention' : 'Erreur') . "</h1>
            <p>" . htmlspecialchars($message) . "</p>
            <div class='redirect-info'>Redirection en cours...</div>
            <div class='progress-bar'><div class='progress-fill'></div></div>
        </div>
        <script>
            setTimeout(() => {
                window.location.href = '../settings/stores';
            }, 3000);
        </script>
    </body>
    </html>";
    exit;
}
?>