<?php

require_once('../core/init.php');

$db = DB::getInstance();

$code = $_GET['code'] ?? null;
$shop = $_GET['shop'] ?? null;
$state = $_GET['state'] ?? null;

// Debug: Log what we received
error_log("Callback received - Shop: $shop, Code: " . ($code ? 'present' : 'missing') . ", State: $state");

if (!$code || !$shop || !$state || $state !== $_SESSION['oauth_state']) {
    error_log("OAuth validation failed - Session state: " . ($_SESSION['oauth_state'] ?? 'not set'));
    showErrorAndRedirect('Paramètres OAuth invalides');
}

// ⚠️ CRITICAL: Use your ACTUAL client_secret from Shopify Partner Dashboard
// These should NOT be the same!
$client_id = "ce2fe15164103b7bcf3e7c4e5770feab";
$client_secret = "9cf49aaa08d587f434522111266c3320"; // ← Get this from Shopify Partner Dashboard

// Exchange code for access token
$token_url = "https://$shop/admin/oauth/access_token";
$post_fields = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Debug: Log the response
error_log("Shopify response (HTTP $http_code): $response");

$result = json_decode($response, true);

if (!isset($result['access_token'])) {
    // Log the actual error from Shopify
    $error_msg = $result['error'] ?? $result['error_description'] ?? 'Unknown error';
    error_log("Token exchange failed: $error_msg");
    showErrorAndRedirect("Impossible d'obtenir le token: $error_msg");
}

$access_token = $result['access_token'];

// Save store to DB
$user_id = getCurrentUserId($db) ?? null;
$store_name = $_SESSION['store_name'];
$store_domain = $_SESSION['store_domain'];

if (!$user_id) showErrorAndRedirect('Utilisateur non authentifié');

$existing_store = $db->getThisQuery("SELECT id FROM stores WHERE user_id = ? AND domain = ?", [$user_id, $store_domain]);

if (!empty($existing_store)) {
    showErrorAndRedirect('Cette boutique existe déjà pour cet utilisateur', 'warning');
}

// Insert store
$store_data = [
    'user_id' => $user_id,
    'storeName' => $store_name,
    'store_name' => $store_name,
    'platform' => 'shopify',
    'domain' => $store_domain,
    'api_key' => $access_token,
    'Consumer_key' => $client_id,
    'Consumer_secret' => $client_secret,
    'is_connected' => 1,
    'connected_at' => date('Y-m-d H:i:s')
];

$inserted = $db->insert('stores', $store_data);

if ($inserted) {
    // Clear OAuth session data
    unset($_SESSION['oauth_state']);
    unset($_SESSION['store_name']);
    unset($_SESSION['store_domain']);
    
    header("Location: ../settings.php?success=1");
    exit;
} else {
    showErrorAndRedirect('Erreur lors de l\'ajout de la boutique');
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
            <div class='redirect-info'>Redirection vers les paramètres dans <span id='countdown'>3</span> secondes...</div>
            <div class='progress-bar'><div class='progress-fill'></div></div>
        </div>
        <script>
            let count = 3;
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