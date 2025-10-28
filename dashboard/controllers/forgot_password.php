<?php if(file_exists(stream_resolve_include_path("../config/only.php"))) {
    require_once("../config/only.php");
}

require_once ('../classes/Config.php'); 
require_once ('../classes/DB.php'); 

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer autoloader
require_once ('../vendor/autoload.php'); 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

try {

    $db = DB::getInstance();
    
    // Check if user exists
    $user = $db->query("SELECT id, username, email FROM users WHERE email = ?", [$email]);
    
    if ($user->count() === 0) {
        // For security, don't reveal if email exists or not
        echo json_encode([
            'success' => true, 
            'message' => 'If the email exists in our system, a password reset link has been sent.'
        ]);
        exit;
    }
    
    $userData = $user->first();
    
    // Generate unique token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Delete any existing tokens for this email
    $db->query("DELETE FROM password_resets WHERE email = ? OR expires_at < NOW()", [$email]);
    
    // Insert new token
    $db->insert('password_resets', [
        'user_id' => $userData->id,
        'email' => $email,
        'token' => $token,
        'expires_at' => $expiresAt,
        'used_at' => 0
    ]);
    
    // Create reset link
    $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
    
    // Send email using PHPMailer
    $emailSent = sendPasswordResetEmail($email, $userData->username, $resetLink);
    
    if ($emailSent) {
        echo json_encode([
            'success' => true,
            'message' => 'If the email exists in our system, a password reset link has been sent.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send reset email. Please try again later.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}

function sendPasswordResetEmail($email, $username, $resetLink) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'support@obgecom.com'; 
        $mail->Password   = 'Obg@123456'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587; // ✅ Port for TLS
        $mail->CharSet    = 'UTF-8';
        
        // Recipients
        $mail->setFrom('support@obgecom.com', 'OBG ECOM');
        $mail->addAddress($email, $username);
        $mail->addReplyTo('support@obgecom.com', 'OBG ECOM Support');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - OBG ECOM';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>OBG ECOM</h1>
                    <h2>Password Reset Request</h2>
                </div>
                <div class='content'>
                    <p>Hello " . htmlspecialchars($username) . ",</p>
                    <p>You requested to reset your password. Click the button below to create a new password:</p>
                    <p style='text-align: center;'>
                        <a href='" . $resetLink . "' class='button'>Reset Your Password</a>
                    </p>
                    <p>Or copy and paste this link in your browser:</p>
                    <p style='word-break: break-all; background: #eee; padding: 10px; border-radius: 5px;'>" . $resetLink . "</p>
                    <p><strong>This link will expire in 1 hour.</strong></p>
                    <p>If you didn't request this reset, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " OBG ECOM. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello $username,\n\n"
                       . "You requested to reset your password. Click the link below to create a new password:\n\n"
                       . "$resetLink\n\n"
                       . "This link will expire in 1 hour.\n\n"
                       . "If you didn't request this reset, please ignore this email.\n\n"
                       . "© " . date('Y') . " OBG ECOM. All rights reserved.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>