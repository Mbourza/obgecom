<?php if(file_exists(stream_resolve_include_path("../config/only.php"))) {
    require_once("../config/only.php");
}

require_once ('../classes/Config.php'); 
require_once ('../classes/DB.php'); 
require_once ('../vendor/autoload.php'); 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = DB::getInstance();

// Check if user is logged in (adjust according to your auth system)
$user_id = getCurrentUserId($db);

if (!$user_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error'   => 'Non autorisé',
        'code'    => 'UNAUTHORIZED'
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'error' => 'Méthode non autorisée',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'resend_verification':
            echo json_encode(handleResendVerification());
            break;
            
        case 'verify_token':
            $token = $input['token'] ?? '';
            echo json_encode(verifyEmailToken($token));
            break;
            
        case 'check_verification_status':
            echo json_encode(checkVerificationStatus());
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Action non valide',
                'code' => 'INVALID_ACTION'
            ]);
    }
} catch (Exception $e) {
    error_log("Email API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur interne du serveur',
        'code' => 'INTERNAL_ERROR'
    ]);
}

function handleResendVerification() {
    global $db, $user_id;
    
    $userEmail = $_SESSION['user_email'] ?? '';
    
    // Rate limiting check
    $lastSent = $_SESSION['last_verification_sent'] ?? 0;
    $currentTime = time();
    $cooldownTime = 60; // 1 minute
    
    if ($currentTime - $lastSent < $cooldownTime) {
        $remainingTime = $cooldownTime - ($currentTime - $lastSent);
        return [
            'success' => false,
            'error' => "Veuillez attendre {$remainingTime} secondes avant de renvoyer un email.",
            'code' => 'RATE_LIMITED',
            'remainingTime' => $remainingTime
        ];
    }
    
    // Check if user is already verified
    $user = $db->getThisQuery("SELECT is_verified, email FROM users WHERE id = ?", [$user_id]);
    if (empty($user)) {
        return [
            'success' => false,
            'error' => 'Utilisateur non trouvé',
            'code' => 'USER_NOT_FOUND'
        ];
    }
    
    $userData = $user[0];
    if ($userData['is_verified']) {
        return [
            'success' => false,
            'error' => 'Votre email est déjà vérifié',
            'code' => 'ALREADY_VERIFIED'
        ];
    }
    
    // Use email from database if not in session
    $emailToUse = $userEmail ?: $userData['email'];
    
    // Send verification email
    $result = sendVerificationEmail($emailToUse, $user_id);
    
    if ($result['success']) {
        $_SESSION['last_verification_sent'] = $currentTime;
        return [
            'success' => true,
            'message' => 'Un nouveau lien de vérification a été envoyé à votre adresse email.',
            'email' => $emailToUse
        ];
    } else {
        error_log("Email verification failed for user {$user_id}: " . print_r($result, true));
        return [
            'success' => false,
            'error' => $result['error'],
            'code' => 'EMAIL_SEND_FAILED'
        ];
    }
}

function checkVerificationStatus() {
    global $db, $user_id;
    
    $user = $db->getThisQuery("SELECT is_verified, email FROM users WHERE id = ?", [$user_id]);
    
    if (empty($user)) {
        return [
            'success' => false,
            'error' => 'Utilisateur non trouvé',
            'code' => 'USER_NOT_FOUND'
        ];
    }
    
    return [
        'success' => true,
        'is_verified' => (bool)$user[0]['is_verified'],
        'email' => $user[0]['email']
    ];
}

function generateVerificationToken($userId) {
    global $db;
    
    try {
        // Delete any existing verification tokens for this user
        $db->delete('verification_tokens', [
            'user_id' => $userId, 
            'token_type' => 'email_verification'
        ]);
        
        // Generate new token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Insert new token
        $result = $db->insert('verification_tokens', [
            'user_id' => $userId,
            'token' => $token,
            'token_type' => 'email_verification',
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$result) {
            throw new Exception('Échec de l\'insertion du token');
        }
        
        return $token;
    } catch (Exception $e) {
        error_log("Token generation failed: " . $e->getMessage());
        throw $e;
    }
}

function sendVerificationEmail($email, $userId) {
    global $db;

    try {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Adresse email invalide'];
        }

        // Generate verification token
        $token = generateVerificationToken($userId);

        // Get user name
        $user = $db->getThisQuery("SELECT name FROM users WHERE id = ?", [$userId]);
        $userName = isset($user[0]['name']) ? $user[0]['name'] : '';

        $verificationLink = "https://obgecom.com/verify.php?token=" . urlencode($token);

        $subject = "Vérification de votre email - OBG";
        $message = buildEmailTemplate($userName, $verificationLink);

        // Create PHPMailer instance
        $mail = new PHPMailer(true);

        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com'; // Hostinger SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'support@obgecom.com'; // 
        $mail->Password   = 'Obg@123456';  // 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // 
        $mail->Port       = 587;

        // Sender info
        $mail->setFrom('support@obgecom.com', 'OBG Platform');
        $mail->addReplyTo('support@obgecom.com', 'Support OBG');

        // Recipient
        $mail->addAddress($email, $userName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();

        return ['success' => true];

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Erreur d\'envoi d\'email : ' . $e->getMessage()
        ];
    }
}


function buildEmailTemplate($userName, $verificationLink) {
    $escapedUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
    $escapedLink = htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8');
    
    return "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Vérification d'email</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4; 
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 10px; 
                overflow: hidden; 
                box-shadow: 0 0 20px rgba(0,0,0,0.1); 
            }
            .header { 
                background: #007bff; 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 24px; 
            }
            .content { 
                padding: 30px 20px; 
                background: white; 
            }
            .content h2 { 
                color: #333; 
                margin-top: 0; 
            }
            .button { 
                display: inline-block; 
                padding: 15px 30px; 
                background: #007bff; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 20px 0; 
                font-weight: bold;
            }
            .footer { 
                text-align: center; 
                padding: 20px; 
                background: #f8f9fa; 
                font-size: 12px; 
                color: #666; 
                border-top: 1px solid #eee; 
            }
            .warning { 
                background: #fff3cd; 
                border: 1px solid #ffeaa7; 
                color: #856404; 
                padding: 15px; 
                border-radius: 5px; 
                margin: 15px 0; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>OBG Platform</h1>
            </div>
            <div class='content'>
                <h2>Bonjour " . ($escapedUserName ?: 'Utilisateur') . " !</h2>
                <p>Merci de vous être inscrit sur notre plateforme. Pour activer votre compte, veuillez vérifier votre adresse email en cliquant sur le bouton ci-dessous :</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$escapedLink' class='button' style='color: #ffffff;'>Vérifier mon email</a>
                </div>
                
                <p>Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :</p>
                <p style='word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace;'>
                    <a href='$escapedLink' style='color: #007bff;'>$escapedLink</a>
                </p>
                
                <div class='warning'>
                    <strong>Important :</strong> Ce lien expirera dans 24 heures pour des raisons de sécurité.
                </div>
                
                <p><small>Si vous n'avez pas créé de compte sur notre plateforme, veuillez ignorer cet email.</small></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " OBG Platform. Tous droits réservés.</p>
                <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            </div>
        </div>
    </body>
    </html>";
}

function verifyEmailToken($token) {
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
            return [
                'success' => false, 
                'error' => 'Erreur lors de la vérification du compte',
                'code' => 'USER_UPDATE_FAILED'
            ];
        }
        
        return [
            'success' => true, 
            'user_id' => $tokenRecord['user_id'],
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
?>