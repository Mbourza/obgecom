<?php
require_once('../core/init.php');

$db = DB::getInstance();

// Comprehensive error handling for OAuth errors from YouCan
$error = $_GET['error'] ?? null;
$error_description = $_GET['error_description'] ?? null;

if ($error) {
    error_log("YouCan OAuth error: $error - $error_description");
    
    $user_message = match($error) {
        'access_denied' => 'Vous avez refusé l\'accès à votre boutique YouCan',
        'invalid_client' => 'Application non reconnue. Veuillez vérifier que l\'application est installée depuis le marketplace YouCan',
        'unauthorized_client' => 'Application non autorisée. Veuillez installer l\'application depuis le marketplace YouCan',
        'invalid_scope' => 'Permissions insuffisantes. Veuillez réinstaller l\'application',
        'invalid_request' => 'Requête invalide. Veuillez réessayer',
        default => "Erreur d'authentification: " . ($error_description ?? $error)
    };
    
    showErrorAndRedirect($user_message);
}

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

// Debug logging
error_log("YouCan Callback received - Code: " . ($code ? 'present' : 'missing') . ", State: $state");

// Validate required parameters
if (!$code) {
    error_log("YouCan OAuth failed - Missing authorization code");
    showErrorAndRedirect('Code d\'autorisation manquant. Veuillez réessayer la connexion');
}

if (!$state) {
    error_log("YouCan OAuth failed - Missing state parameter");
    showErrorAndRedirect('Paramètre de sécurité manquant');
}

// CSRF protection - Validate state
$session_state = $_SESSION['youcan_oauth_state'] ?? null;
if (!$session_state || !hash_equals($session_state, $state)) {
    error_log("YouCan OAuth validation failed - State mismatch. Expected: $session_state, Got: $state");
    showErrorAndRedirect('Validation OAuth échouée. Veuillez réessayer');
}

// YouCan Configuration
$client_id = "2310";
$client_secret = "m7gzBVHkdZEuBRI9TPC8pI4sVFdbIHFZMhvc7xQ8";
$redirect_uri = "https://obgecom.com/dashboard/controllers/youcan_callback.php";

// Exchange authorization code for access token
$token_response = exchangeToken($code, $client_id, $client_secret, $redirect_uri);

if (!$token_response['success']) {
    error_log("Token exchange failed: " . $token_response['message']);
    showErrorAndRedirect($token_response['message']);
}

$access_token = $token_response['access_token'];
$refresh_token = $token_response['refresh_token'] ?? null;
$expires_in = $token_response['expires_in'] ?? 3600;

// Get store information
$store_info = getYouCanStoreInfo($access_token);
if (!$store_info) {
    error_log("Warning: Could not fetch store info from API");
    // Fall back to session data
    $store_info = [
        'name' => $_SESSION['youcan_store_name'] ?? 'Boutique YouCan',
        'domain' => $_SESSION['youcan_store_domain'] ?? '',
        'email' => null,
        'currency' => 'MAD',
        'country' => 'MA'
    ];
}

// Get current user ID
$user_id = getCurrentUserId($db);
if (!$user_id) {
    showErrorAndRedirect('Utilisateur non authentifié. Veuillez vous reconnecter');
}

// Prepare store data
$store_name = $store_info['name'] ?? $_SESSION['youcan_store_name'] ?? 'Boutique YouCan';
$store_domain = $store_info['domain'] ?? $_SESSION['youcan_store_domain'] ?? '';

// Check if store already exists
$existing_store = $db->getThisQuery(
    "SELECT id FROM stores WHERE user_id = ? AND domain = ?", 
    [$user_id, $store_domain]
);

if (!empty($existing_store)) {
    // Clean up session
    cleanOAuthSession();
    showErrorAndRedirect('Cette boutique YouCan est déjà connectée à votre compte', 'warning');
}

// Insert the store into database
$store_data = [
    'user_id' => $user_id,
    'storeName' => $store_name,
    'store_name' => $store_name,
    'platform' => 'youcan',
    'domain' => $store_domain,
    'api_key' => $access_token,
    'Consumer_key' => $client_id,
    'Consumer_secret' => $client_secret,
    'is_connected' => 1,
    'connected_at' => date('Y-m-d H:i:s'),
    'store_data' => json_encode($store_info)
];

try {
    // Start transaction
    $db->beginTransaction();
    
    $inserted = $db->insert('stores', $store_data);
    
    if ($inserted) {
        // Update onboarding progress
        updateUserOnboarding($db, $user_id, 'step_store', true);
        
        // Commit transaction
        $db->commit();
        
        // Clean up OAuth session
        cleanOAuthSession();
        
        // Log success
        error_log("YouCan store connected successfully - User: $user_id, Store: $store_name");
        
        // Redirect to success page
        header("Location: ../onboarding?success=1&platform=youcan&store=" . urlencode($store_name));
        exit;
    } else {
        $db->rollBack();
        error_log("Database insert failed for YouCan store");
        showErrorAndRedirect('Erreur lors de l\'enregistrement de la boutique. Veuillez réessayer');
    }
} catch (Exception $e) {
    $db->rollBack();
    error_log("Exception during YouCan store insert: " . $e->getMessage());
    showErrorAndRedirect('Erreur système lors de l\'ajout de la boutique');
}

/**
 * Exchange authorization code for access token
 */
function exchangeToken($code, $client_id, $client_secret, $redirect_uri) {
    // Try multiple authentication methods
    $methods = [
        'body' => [
            'url' => 'https://api.youcan.shop/oauth/token',
            'use_basic_auth' => false
        ],
        'basic_auth' => [
            'url' => 'https://api.youcan.shop/oauth/token',
            'use_basic_auth' => true
        ],
        'alt_endpoint' => [
            'url' => 'https://api.youcan.shop/token',
            'use_basic_auth' => false
        ]
    ];
    
    $last_error = '';
    
    foreach ($methods as $method_name => $config) {
        error_log("Trying token exchange method: $method_name");
        
        $post_fields = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirect_uri
        ];
        
        // Add credentials to body if not using Basic Auth
        if (!$config['use_basic_auth']) {
            $post_fields['client_id'] = $client_id;
            $post_fields['client_secret'] = $client_secret;
        }
        
        $ch = curl_init($config['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'OBGEcom/1.0');
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ];
        
        // Add Basic Auth header if needed
        if ($config['use_basic_auth']) {
            $credentials = base64_encode($client_id . ':' . $client_secret);
            $headers[] = 'Authorization: Basic ' . $credentials;
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Enable verbose output for debugging
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        
        // Log verbose information
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("Method $method_name - CURL verbose: $verboseLog");
        fclose($verbose);
        
        curl_close($ch);
        
        // Log response
        error_log("Method $method_name - HTTP $http_code: " . substr($response, 0, 500));
        
        if ($curl_error) {
            $last_error = "CURL error: $curl_error";
            continue;
        }
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            if (isset($result['access_token'])) {
                error_log("Token exchange successful with method: $method_name");
                return [
                    'success' => true,
                    'access_token' => $result['access_token'],
                    'refresh_token' => $result['refresh_token'] ?? null,
                    'expires_in' => $result['expires_in'] ?? 3600,
                    'method' => $method_name
                ];
            }
        }
        
        $last_error = "HTTP $http_code: " . $response;
    }
    
    // All methods failed
    error_log("All token exchange methods failed. Last error: $last_error");
    return [
        'success' => false,
        'message' => 'Impossible d\'obtenir le token d\'accès. Veuillez réessayer ou contacter le support.'
    ];
}

/**
 * Get store information from YouCan API
 */
function getYouCanStoreInfo($access_token) {
    $endpoints = [
        'https://api.youcan.shop/store',
        'https://api.youcan.shop/v1/store',
        'https://api.youcan.shop/merchant/store'
    ];
    
    foreach ($endpoints as $endpoint) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            $store_data = $data['data'] ?? $data;
            
            // Normalize store data
            return [
                'name' => $store_data['name'] ?? $store_data['store_name'] ?? null,
                'domain' => $store_data['domain'] ?? $store_data['subdomain'] ?? null,
                'email' => $store_data['email'] ?? $store_data['contact_email'] ?? null,
                'currency' => $store_data['currency'] ?? $store_data['default_currency'] ?? 'MAD',
                'country' => $store_data['country'] ?? $store_data['country_code'] ?? 'MA',
                'phone' => $store_data['phone'] ?? null,
                'logo' => $store_data['logo'] ?? $store_data['store_logo'] ?? null
            ];
        }
    }
    
    return null;
}

/**
 * Get current user ID from session
 */
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        return null;
    }

    // Return ID if directly available
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Try to find user by username/email/phone
    $identifier = $_SESSION['user']['username'] ?? $_SESSION['user']['email'] ?? null;
    if (!$identifier) {
        return null;
    }

    $user = $db->getThisQuery(
        "SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1",
        [$identifier, $identifier, $identifier]
    );

    return $user[0]['id'] ?? null;
}

/**
 * Update user onboarding progress
 */
function updateUserOnboarding($db, $user_id, $step, $completed = true) {
    if ($step !== 'step_store') {
        return true;
    }
    
    try {
        $current_time = date('Y-m-d H:i:s');
        
        // Check if onboarding record exists
        $existing = $db->getThisQuery(
            "SELECT id FROM user_onboarding WHERE user_id = ?",
            [$user_id]
        );
        
        if (!empty($existing)) {
            return $db->update('user_onboarding', $existing[0]['id'], [
                'step_store' => $completed ? 1 : 0,
                'updated_at' => $current_time
            ]);
        } else {
            return $db->insert('user_onboarding', [
                'user_id' => $user_id,
                'step_store' => $completed ? 1 : 0,
                'completed' => 0,
                'created_at' => $current_time,
                'updated_at' => $current_time
            ]);
        }
    } catch (Exception $e) {
        error_log("Onboarding update failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Clean up OAuth session data
 */
function cleanOAuthSession() {
    unset($_SESSION['youcan_oauth_state']);
    unset($_SESSION['youcan_store_name']);
    unset($_SESSION['youcan_store_domain']);
}

/**
 * Display error message and redirect
 */
function showErrorAndRedirect($message, $type = 'error') {
    $icon = $type === 'warning' ? '⚠️' : '❌';
    $bgColor = $type === 'warning' ? '#ff9800' : '#f44336';
    $title = $type === 'warning' ? 'Attention' : 'Erreur de connexion YouCan';
    
    // Store message in session for retrieval after redirect
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
    
    // HTML error page with auto-redirect
    echo "<!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$title</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
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
                max-width: 550px;
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
            .help-text {
                background: #f5f5f5;
                border-left: 4px solid $bgColor;
                padding: 15px;
                margin: 20px 0;
                text-align: left;
                border-radius: 4px;
                font-size: 14px;
                color: #555;
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
                animation: progress 5s linear forwards;
            }
            @keyframes progress {
                from { width: 0%; }
                to { width: 100%; }
            }
            .btn {
                display: inline-block;
                background: $bgColor;
                color: white;
                text-decoration: none;
                padding: 12px 30px;
                border-radius: 8px;
                margin-top: 20px;
                font-weight: 500;
                transition: opacity 0.3s;
            }
            .btn:hover {
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <div class='message-container'>
            <div class='icon'>$icon</div>
            <h1>$title</h1>
            <p>" . htmlspecialchars($message) . "</p>";
    
    // Add helpful instructions for common errors
    if (stripos($message, 'approuv') !== false || stripos($message, 'install') !== false) {
        echo "<div class='help-text'>
            <strong>💡 Comment résoudre ce problème:</strong><br>
            1. Connectez-vous à votre tableau de bord YouCan<br>
            2. Allez dans Apps & Intégrations<br>
            3. Installez/Approuvez l'application OBGEcom<br>
            4. Réessayez la connexion
        </div>";
    } elseif (stripos($message, 'token') !== false) {
        echo "<div class='help-text'>
            <strong>💡 Problème d'authentification:</strong><br>
            • Vérifiez que votre boutique est active<br>
            • Réessayez dans quelques instants<br>
            • Contactez le support si le problème persiste
        </div>";
    }
    
    echo "<div class='redirect-info'>Redirection vers les paramètres dans <span id='countdown'>5</span> secondes...</div>
            <div class='progress-bar'><div class='progress-fill'></div></div>
            <a href='../onboarding' class='btn'>Retourner aux paramètres</a>
        </div>
        <script>
            let count = 5;
            const countdown = setInterval(() => {
                count--;
                document.getElementById('countdown').textContent = count;
                if (count <= 0) {
                    clearInterval(countdown);
                    window.location.href = '../onboarding';
                }
            }, 1000);
        </script>
    </body>
    </html>";
    exit;
} ?>