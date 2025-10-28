<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once ('../vendor/autoload.php'); 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = DB::getInstance();

// Check if user is logged in and is super admin
if(!Session::exists(Config::get('session/session_name'))){
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error'   => 'Non autorisé',
        'code'    => 'UNAUTHORIZED'
    ]);
    exit;
} 

// Check if user is super admin
$user = $db->getThisQuery("SELECT id, `name`, `role`, is_verified, email FROM users WHERE email = ?", [$_SESSION['user']['username']]);
if (!$user || empty($user[0]['id']) || $user[0]['role'] !== 'super') {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error'   => 'Accès refusé - Privilèges insuffisants',
        'code'    => 'FORBIDDEN'
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
        case 'send_email':
            $userId = $input['user_id'] ?? '';
            $subject = $input['subject'] ?? '';
            $message = $input['message'] ?? '';
            echo json_encode(sendEmailToUser($userId, $subject, $message));
            break;
            
        case 'send_bulk_emails':
            $userIds = $input['user_ids'] ?? [];
            $subject = $input['subject'] ?? '';
            $message = $input['message'] ?? '';
            echo json_encode(sendBulkEmails($userIds, $subject, $message));
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
    error_log("Super Admin Email API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur interne du serveur',
        'code' => 'INTERNAL_ERROR'
    ]);
}

function sendEmailToUser($userId, $subject, $message) {
    global $db;
    
    try {
        // Validate inputs
        if (empty($userId) || empty($subject) || empty($message)) {
            return [
                'success' => false,
                'error' => 'Tous les champs sont obligatoires',
                'code' => 'MISSING_FIELDS'
            ];
        }
        
        // Get user details
        $user = $db->getThisQuery("SELECT email, name FROM users WHERE id = ?", [$userId]);
        if (empty($user)) {
            return [
                'success' => false,
                'error' => 'Utilisateur non trouvé',
                'code' => 'USER_NOT_FOUND'
            ];
        }
        
        $userData = $user[0];
        $user_email = $userData['email'];
        $user_name = $userData['name'];
        
        // Validate email
        if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'Adresse email de l\'utilisateur invalide',
                'code' => 'INVALID_USER_EMAIL'
            ];
        }
        
        // Build email content
        $email_body = buildAdminEmailTemplate($user_name, $subject, $message);
        
        // Send email using PHPMailer
        $mailResult = sendPHPMailerEmail($user_email, $user_name, $subject, $email_body);
        
        if ($mailResult['success']) {
            // Log the action
            logAdminAction($db, "Email envoyé: $subject", $userId);
            
            return [
                'success' => true,
                'message' => "Email envoyé avec succès à $user_name",
                'email' => $user_email
            ];
        } else {
            return [
                'success' => false,
                'error' => $mailResult['error'],
                'code' => 'EMAIL_SEND_FAILED'
            ];
        }
        
    } catch (Exception $e) {
        error_log("Send email error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage(),
            'code' => 'SEND_EMAIL_ERROR'
        ];
    }
}

function sendBulkEmails($userIds, $subject, $message) {
    global $db;
    
    try {
        // Validate inputs
        if (empty($userIds) || !is_array($userIds) || empty($subject) || empty($message)) {
            return [
                'success' => false,
                'error' => 'Données invalides pour l\'envoi en masse',
                'code' => 'INVALID_BULK_DATA'
            ];
        }
        
        $results = [
            'success' => true,
            'total' => count($userIds),
            'sent' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($userIds as $userId) {
            $result = sendEmailToUser($userId, $subject, $message);
            $results['details'][] = [
                'user_id' => $userId,
                'success' => $result['success'],
                'message' => $result['message'] ?? $result['error'] ?? 'Unknown error'
            ];
            
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['success'] = false;
            }
        }
        
        // Log bulk action
        logAdminAction($db, "Envoi en masse: $subject - {$results['sent']}/{$results['total']} envoyés");
        
        return $results;
        
    } catch (Exception $e) {
        error_log("Bulk email error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Erreur lors de l\'envoi en masse: ' . $e->getMessage(),
            'code' => 'BULK_EMAIL_ERROR'
        ];
    }
}

function sendPHPMailerEmail($toEmail, $toName, $subject, $message) {
    try {
        // Create PHPMailer instance
        $mail = new PHPMailer(true);

        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com'; // Your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'support@obgecom.com'; // Your email
        $mail->Password   = 'Obg@123456';  // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender info (super admin)
        $mail->setFrom('support@obgecom.com', 'OBG Super Admin');
        $mail->addReplyTo('support@obgecom.com', 'Support OBG');

        // Recipient
        $mail->addAddress($toEmail, $toName);

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

function buildAdminEmailTemplate($userName, $subject, $message) {
    $escapedUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
    $escapedSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $formattedMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    
    return "
    <!DOCTYPE html>
    <html lang='fr'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$escapedSubject</title>
        <style>
            body { 
                font-family: 'Segoe UI', Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f8f9fa; 
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white; 
                border-radius: 12px; 
                overflow: hidden; 
                box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 24px; 
                font-weight: 600;
            }
            .header .subtitle {
                margin: 5px 0 0 0;
                font-size: 14px;
                opacity: 0.9;
            }
            .content { 
                padding: 30px; 
                background: white; 
            }
            .content h2 { 
                color: #2d3748; 
                margin-top: 0;
                font-size: 20px;
                border-bottom: 2px solid #e2e8f0;
                padding-bottom: 10px;
            }
            .message-content {
                background: #f7fafc;
                border-left: 4px solid #4299e1;
                padding: 20px;
                border-radius: 0 8px 8px 0;
                margin: 20px 0;
            }
            .footer { 
                text-align: center; 
                padding: 25px; 
                background: #edf2f7; 
                font-size: 13px; 
                color: #4a5568; 
                border-top: 1px solid #e2e8f0; 
            }
            .info-box {
                background: #ebf8ff;
                border: 1px solid #bee3f8;
                color: #2b6cb0;
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                font-size: 14px;
            }
            .signature {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
                color: #718096;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>OBG Platform</h1>
                <div class='subtitle'>Communication de l'équipe</div>
            </div>
            <div class='content'>
                <h2>$escapedSubject</h2>
                
                <p><strong>Cher(e) $escapedUserName,</strong></p>
                
                <div class='message-content'>
                    $formattedMessage
                </div>
                
                <div class='info-box'>
                    <strong>ℹ Information:</strong> Ce message vous a été envoyé par l'équipe d'administration OBG. 
                    Veuillez ne pas répondre directement à cet email.
                </div>
                
                <div class='signature'>
                    <p><strong>Cordialement,</strong><br>
                    L'équipe d'administration OBG<br>
                    <em>Votre succès est notre priorité</em></p>
                </div>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " OBG Platform. Tous droits réservés.</p>
                <p>Cet email a été envoyé depuis le panneau d'administration.</p>
            </div>
        </div>
    </body>
    </html>";
}

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
?>