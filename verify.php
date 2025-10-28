<?php
if(file_exists(stream_resolve_include_path("./core/init.php"))) {
    require_once("./core/init.php");
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = DB::getInstance();

// Get token from URL parameter
$token = $_GET['token'] ?? '';

// Handle the verification
$result = handleEmailVerification($token);

function handleEmailVerification($token) {
    global $db;
    
    try {
        // Validate token format
        if (empty($token) || strlen($token) != 64 || !ctype_xdigit($token)) {
            return [
                'success' => false, 
                'error' => 'Format de token invalide',
                'code' => 'INVALID_TOKEN_FORMAT'
            ];
        }
        
        // Get token record
        $tokenRecord = $db->getThisQuery(
            "SELECT * FROM verification_tokens WHERE token = ? AND token_type = 'email_verification' AND used_at IS NULL", 
            [$token]
        );
        
        if (!$tokenRecord || empty($tokenRecord)) {
            return [
                'success' => false, 
                'error' => 'Token invalide ou déjà utilisé',
                'code' => 'TOKEN_INVALID'
            ];
        }
        
        $tokenRecord = $tokenRecord[0];

        
        // Check if token is expired
        if (strtotime($tokenRecord['expires_at']) < time()) {
            // Delete expired token
            $db->delete('verification_tokens', ['id' => $tokenRecord['id']]);
            return [
                'success' => false, 
                'error' => 'Token expiré, veuillez demander un nouveau lien',
                'code' => 'TOKEN_EXPIRED'
            ];
        }
        
        // Get user information before updating
        $user = $db->getThisQuery("SELECT `name`, email, is_verified FROM users WHERE id = ?", [$tokenRecord['user_id']]);
        
        if (!$user || empty($user)) {
            return [
                'success' => false, 
                'error' => 'Utilisateur non trouvé',
                'code' => 'USER_NOT_FOUND'
            ];
        }
        
        $userData = $user[0];
        
        // Check if already verified
        if ($userData['is_verified']) {
            return [
                'success' => false, 
                'error' => 'Ce compte est déjà vérifié',
                'code' => 'ALREADY_VERIFIED',
                'user_name' => $userData['name']
            ];
        }
        
        // Mark token as used
        $updateResult = $db->update('verification_tokens', $tokenRecord['id'], [
            'used_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$updateResult) {
            return [
                'success' => false, 
                'error' => 'Erreur lors de la mise à jour du token',
                'code' => 'TOKEN_UPDATE_FAILED'
            ];
        }
        
        // Mark user as verified
        $userUpdateResult = $db->update('users', $tokenRecord['user_id'], [
            'is_verified' => 1,
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$userUpdateResult) {
            // If user update fails, we should revert the token update
            $db->update('verification_tokens', $tokenRecord['id'], ['used_at' => null]);
            return [
                'success' => false, 
                'error' => 'Erreur lors de la vérification du compte',
                'code' => 'USER_UPDATE_FAILED'
            ];
        }
        
        return [
            'success' => true, 
            'user_id' => $tokenRecord['user_id'],
            'user_name' => $userData['name'],
            'user_email' => $userData['email'],
            'message' => 'Email vérifié avec succès!'
        ];
        
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        return [
            'success' => false, 
            'error' => 'Erreur système lors de la vérification',
            'code' => 'SYSTEM_ERROR'
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $result['success'] ? 'Vérification réussie' : 'Erreur de vérification'; ?> - OBG</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 100%;
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
        }

        .icon.success {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }

        .icon.error {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }

        .icon.warning {
            background: linear-gradient(135deg, #ff9800, #f57c00);
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 28px;
            font-weight: 600;
        }

        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .user-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }

        .user-info strong {
            color: #333;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,123,255,0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(108,117,125,0.3);
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 14px;
        }

        .auto-redirect {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #1976d2;
        }

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .icon {
                width: 60px;
                height: 60px;
                font-size: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($result['success']): ?>
            <!-- Success Case -->
            <div class="icon success">✓</div>
            <h1>Email vérifié avec succès !</h1>
            <p>Félicitations ! Votre adresse email a été vérifiée avec succès.</p>
            
            <?php if (isset($result['user_name']) || isset($result['user_email'])): ?>
                <div class="user-info">
                    <?php if (isset($result['user_name']) && !empty($result['user_name'])): ?>
                        <p><strong>Nom:</strong> <?php echo htmlspecialchars($result['user_name']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($result['user_email']) && !empty($result['user_email'])): ?>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($result['user_email']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="auto-redirect">
                <p><strong>Redirection automatique</strong> vers la page de connexion dans <span id="countdown">5</span> secondes...</p>
            </div>
            
            <a href="https://obgecom.com/lg" class="btn btn-primary">Se connecter maintenant</a>
            <a href="https://obgecom.com" class="btn btn-secondary">Retour à l'accueil</a>

        <?php elseif ($result['code'] === 'ALREADY_VERIFIED'): ?>
            <!-- Already Verified Case -->
            <div class="icon warning">⚠</div>
            <h1>Compte déjà vérifié</h1>
            <p>Ce compte a déjà été vérifié. Vous pouvez vous connecter directement.</p>
            
            <?php if (isset($result['user_name']) && !empty($result['user_name'])): ?>
                <div class="user-info">
                    <p><strong>Utilisateur:</strong> <?php echo htmlspecialchars($result['user_name']); ?></p>
                </div>
            <?php endif; ?>
            
            <a href="https://obgecom.com/lg" class="btn btn-primary">Se connecter</a>
            <a href="https://obgecom.com" class="btn btn-secondary">Retour à l'accueil</a>

        <?php else: ?>
            <!-- Error Cases -->
            <div class="icon error">✗</div>
            <h1>Erreur de vérification</h1>
            
            <?php
            switch ($result['code']) {
                case 'INVALID_TOKEN_FORMAT':
                    echo '<p>Le lien de vérification est invalide. Veuillez vérifier que vous avez copié le lien complet.</p>';
                    break;
                case 'TOKEN_INVALID':
                    echo '<p>Ce lien de vérification est invalide ou a déjà été utilisé.</p>';
                    break;
                case 'TOKEN_EXPIRED':
                    echo '<p>Ce lien de vérification a expiré. Les liens sont valides pendant 24 heures seulement.</p>';
                    break;
                case 'USER_NOT_FOUND':
                    echo '<p>Aucun utilisateur associé à ce lien de vérification.</p>';
                    break;
                default:
                    echo '<p>' . htmlspecialchars($result['error']) . '</p>';
            }
            ?>
            
            <?php if (in_array($result['code'], ['TOKEN_INVALID', 'TOKEN_EXPIRED'])): ?>
                <p>Vous pouvez demander un nouveau lien de vérification depuis votre tableau de bord.</p>
                <a href="https://obgecom.com/lg" class="btn btn-primary">Se connecter pour renvoyer</a>
            <?php endif; ?>
            
            <a href="https://obgecom.com" class="btn btn-secondary">Retour à l'accueil</a>
        <?php endif; ?>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> OBGECOM. Tous droits réservés.</p>
        </div>
    </div>

    <?php if ($result['success']): ?>
    <script>
        // Auto-redirect countdown for success case
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = 'https://obgecom.com/dashboard/home.php';
            }
        }, 1000);
    </script>
    <?php endif; ?>
</body>
</html>