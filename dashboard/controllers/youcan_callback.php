<?php
require_once('../core/init.php');

$db = DB::getInstance();

// Handle OAuth errors from YouCan (user denial, app not approved, etc.)
$error = $_GET['error'] ?? null;
$error_description = $_GET['error_description'] ?? null;

if ($error) {
    error_log("YouCan OAuth error: $error - $error_description");
    
    $user_message = match($error) {
        'access_denied' => 'Vous avez refusé l\'accès à votre boutique YouCan',
        'invalid_client' => 'Votre application n\'est pas encore approuvée par YouCan Partner. Veuillez d\'abord installer l\'application depuis le marketplace YouCan',
        'unauthorized_client' => 'Application non autorisée. Veuillez installer l\'application depuis le marketplace YouCan de votre boutique',
        'invalid_scope' => 'Permissions invalides demandées',
        'invalid_request' => 'Requête invalide. Vérifiez vos paramètres OAuth',
        default => "Erreur OAuth: " . ($error_description ?? $error)
    };
    
    showErrorAndRedirect($user_message);
}

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;

// Debug: Log what we received
error_log("YouCan Callback received - Code: " . ($code ? 'present' : 'missing') . ", State: $state");

// Validate OAuth parameters
if (!$code) {
    error_log("YouCan OAuth failed - Missing authorization code");
    showErrorAndRedirect('Code d\'autorisation manquant. Veuillez réessayer la connexion');
}

if (!$state) {
    error_log("YouCan OAuth failed - Missing state parameter");
    showErrorAndRedirect('Paramètre state manquant');
}

// Validate state to prevent CSRF attacks
$session_state = $_SESSION['youcan_oauth_state'] ?? null;
if ($state !== $session_state) {
    error_log("YouCan OAuth validation failed - State mismatch. Expected: $session_state, Got: $state");
    showErrorAndRedirect('Validation OAuth échouée. Veuillez réessayer');
}

// Configuration YouCan
$client_id = "1757";
$client_secret = "POnXQ1efzp3Si0dyfCbdwQBaXRaXx6CRJNGNMep7";
$redirect_uri = "https://obgecom.com/dashboard/controllers/youcan_callback.php";

// Exchange authorization code for access token
$token_url = "https://api.youcan.shop/oauth/token";
$post_fields = [
    'grant_type' => 'authorization_code',
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code,
    'redirect_uri' => $redirect_uri
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Debug: Log the response
error_log("YouCan token response (HTTP $http_code): $response");
if ($curl_error) {
    error_log("YouCan CURL error: $curl_error");
    showErrorAndRedirect('Erreur de connexion au serveur YouCan. Veuillez réessayer');
}

// Handle different HTTP response codes
if ($http_code !== 200) {
    $error_details = '';
    $user_friendly_message = '';
    
    if ($response) {
        $result = json_decode($response, true);
        $error_details = $result['error'] ?? $result['message'] ?? $result['error_description'] ?? '';
    }
    
    switch ($http_code) {
        case 400:
            $user_friendly_message = 'Requête invalide. Le code d\'autorisation a peut-être expiré';
            break;
        case 401:
            if (stripos($error_details, 'client') !== false) {
                $user_friendly_message = 'Application non approuvée. Veuillez d\'abord installer l\'application depuis le marketplace YouCan de votre boutique';
            } else {
                $user_friendly_message = 'Identifiants OAuth invalides';
            }
            break;
        case 403:
            $user_friendly_message = 'Accès refusé. Votre application n\'a pas les permissions nécessaires';
            break;
        case 404:
            $user_friendly_message = 'Endpoint OAuth non trouvé. Contactez le support';
            break;
        case 429:
            $user_friendly_message = 'Trop de tentatives. Veuillez patienter quelques minutes';
            break;
        case 500:
        case 502:
        case 503:
            $user_friendly_message = 'Erreur serveur YouCan. Veuillez réessayer dans quelques instants';
            break;
        default:
            $user_friendly_message = "Erreur HTTP $http_code lors de l'obtention du token";
    }
    
    error_log("YouCan token exchange failed - HTTP $http_code: $error_details");
    showErrorAndRedirect($user_friendly_message . ($error_details ? " ($error_details)" : ''));
}

$result = json_decode($response, true);

if (!isset($result['access_token'])) {
    $error_msg = $result['error'] ?? $result['error_description'] ?? 'Token non reçu';
    error_log("YouCan token missing in response: " . json_encode($result));
    showErrorAndRedirect("Impossible d'obtenir le token d'accès: $error_msg");
}

$access_token = $result['access_token'];
$refresh_token = $result['refresh_token'] ?? null;
$expires_in = $result['expires_in'] ?? 3600;

// Try to get store information via YouCan API (optional)
$store_info = getYouCanStoreInfo($access_token);
if (!$store_info) {
    error_log("YouCan store info unavailable, using session data");
    // Use basic store info from session if API call fails
    $store_info = [
        'name' => $_SESSION['youcan_store_name'] ?? 'Boutique YouCan',
        'domain' => $_SESSION['youcan_store_domain'] ?? ''
    ];
}

// Get current user ID
$user_id = getCurrentUserId($db);
if (!$user_id) {
    showErrorAndRedirect('Utilisateur non authentifié. Veuillez vous reconnecter');
}

// Retrieve store details from session
$store_name = $_SESSION['youcan_store_name'] ?? $store_info['name'] ?? 'Boutique YouCan';
$store_domain = $_SESSION['youcan_store_domain'] ?? $store_info['domain'] ?? '';

// Check if store already exists
$existing_store = $db->getThisQuery(
    "SELECT id FROM stores WHERE user_id = ? AND domain = ?", 
    [$user_id, $store_domain]
);

if (!empty($existing_store)) {
    // Clean up session before showing warning
    unset($_SESSION['youcan_oauth_state']);
    unset($_SESSION['youcan_store_name']);
    unset($_SESSION['youcan_store_domain']);
    
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
    $inserted = $db->insert('stores', $store_data);
    
    if ($inserted) {
        // Clean up OAuth session data
        unset($_SESSION['youcan_oauth_state']);
        unset($_SESSION['youcan_store_name']);
        unset($_SESSION['youcan_store_domain']);
        
        error_log("YouCan store connected successfully - User: $user_id, Store: $store_name");
        header("Location: ../settings.php?success=1&platform=youcan");
        exit;
    } else {
        error_log("Database insert failed for YouCan store: " . json_encode($store_data));
        showErrorAndRedirect('Erreur lors de l\'enregistrement de la boutique. Veuillez réessayer');
    }
} catch (Exception $e) {
    error_log("Exception during YouCan store insert: " . $e->getMessage());
    showErrorAndRedirect('Erreur système lors de l\'ajout de la boutique');
}

function getYouCanStoreInfo($access_token) {
    $api_url = "https://api.youcan.shop/v2/store";
    
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("YouCan store info CURL error: $curl_error");
        return null;
    }
    
    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        return $data['data'] ?? $data;
    }
    
    error_log("YouCan store info failed - HTTP $http_code: $response");
    return null;
}

function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    // Prefer explicit ID if available
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Try to match identifier against username, email, or phone
    $identifier = $_SESSION['user']['username'];
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

function showErrorAndRedirect($message, $type = 'error') {
    $icon = $type === 'warning' ? '⚠️' : '❌';
    $bgColor = $type === 'warning' ? '#ff9800' : '#f44336';
    $title = $type === 'warning' ? 'Attention' : 'Erreur de connexion YouCan';
    
    echo "<!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$title</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
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
                animation: progress 5s linear;
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
    }
    
    echo "<div class='redirect-info'>Redirection vers les paramètres dans <span id='countdown'>5</span> secondes...</div>
            <div class='progress-bar'><div class='progress-fill'></div></div>
        </div>
        <script>
            let count = 5;
            const countdown = setInterval(() => {
                count--;
                document.getElementById('countdown').textContent = count;
                if (count <= 0) {
                    clearInterval(countdown);
                    window.location.href = '../settings.php';
                }
            }, 1000);
        </script>
    </body>
    </html>";
    exit;
}
?>