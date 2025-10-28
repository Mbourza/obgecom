<?php header('Content-Type: application/json');
if(file_exists(stream_resolve_include_path("../config/only.php"))) {
    require_once("../config/only.php");
}

require_once ('../classes/Config.php'); 
require_once ('../classes/DB.php'); 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once ('../vendor/autoload.php'); 

$db = DB::getInstance();

function generateVerificationCode() {
    return sprintf("%06d", mt_rand(1, 999999));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? '';
    $isResend = $data['resend'] ?? false;
    
    // Validate email address
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address format']);
        exit;
    }
    
    // Generate verification code
    $verificationCode = generateVerificationCode();
    
    // Store in session
    $_SESSION['email_verification'] = [
        'email' => $email,
        'code' => $verificationCode,
        'attempts' => 0,
        'expires' => time() + 600 // 10 minutes
    ];
    
    // Send via Email
    $emailSent = sendVerificationEmail($email, $verificationCode, $name);
    
    if ($emailSent['success']) {
        $response = [
            'success' => true,
            'message' => $isResend ? 'Verification code resent via Email!' : 'Verification code sent via Email!',
            'method' => 'email'
        ];
        
        // Return code for testing
        if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
            $response['verification_code'] = $verificationCode;
            $response['test_mode'] = true;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send verification email. Please try again.',
            'error' => $emailSent['error'] ?? 'Unknown error'
        ]);
    }
}

function sendVerificationEmail($email, $code, $name = '') {
    try {
        // Include required files

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }

        // Create PHPMailer instance
        $mail = new PHPMailer(true);

        // SMTP configuration (using your existing email settings)
        $mail->isSMTP();
        $mail->Host       = 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'support@obgecom.com';
        $mail->Password   = 'Obg@123456';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender info
        $mail->setFrom('support@obgecom.com', 'OBG Platform');
        $mail->addReplyTo('support@obgecom.com', 'Support OBG');

        // Recipient
        $mail->addAddress($email, $name);

        // Email content
        $subject = "Your OBG ECOM Verification Code";
        $message = buildVerificationEmailTemplate($name, $code);

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
            'error' => 'Email sending error: ' . $e->getMessage()
        ];
    }
}

function buildVerificationEmailTemplate($userName, $verificationCode) {
    $escapedUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
    $escapedCode = htmlspecialchars($verificationCode, ENT_QUOTES, 'UTF-8');
    
    return "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Email Verification - OBG ECOM</title>
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
                background: linear-gradient(135deg, #8a2be2 0%, #4b0082 100%); 
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
            .verification-code { 
                display: inline-block; 
                padding: 20px 30px; 
                background: linear-gradient(135deg, #8a2be2 0%, #4b0082 100%); 
                color: white; 
                text-decoration: none; 
                border-radius: 10px; 
                margin: 20px 0; 
                font-weight: bold;
                font-size: 28px;
                letter-spacing: 5px;
                text-align: center;
                box-shadow: 0 4px 15px rgba(138, 43, 226, 0.3);
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
            .info-box {
                background: #d1ecf1;
                border: 1px solid #bee5eb;
                color: #0c5460;
                padding: 15px;
                border-radius: 5px;
                margin: 15px 0;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>OBG ECOM Platform</h1>
            </div>
            <div class='content'>
                <h2>Hello " . ($escapedUserName ?: 'there') . "! 👋</h2>
                <p>Thank you for signing up with OBG ECOM! To complete your registration and access your dashboard, please use the verification code below:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <div class='verification-code'>" . $escapedCode . "</div>
                </div>
                
                <div class='info-box'>
                    <strong>📱 How to use this code:</strong><br>
                    Enter this 6-digit code in the verification form on our website to verify your email address.
                </div>
                
                <div class='warning'>
                    <strong>⏰ Important:</strong> This verification code will expire in <strong>10 minutes</strong> for security reasons.
                </div>
                
                <p><strong>Didn't request this code?</strong><br>
                If you didn't create an account with OBG ECOM, please ignore this email or contact our support team immediately.</p>
                
                <p>Best regards,<br>
                <strong>The OBG ECOM Team</strong></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " OBG ECOM Platform. All rights reserved.</p>
                <p>This is an automated email, please do not reply directly to this message.</p>
                <p>If you need assistance, contact us at: support@obgecom.com</p>
            </div>
        </div>
    </body>
    </html>";
}

// Email verification code verification endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'verify_code') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $code = $data['code'] ?? '';
    
    // Validate input
    if (empty($email) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Email and code are required']);
        exit;
    }
    
    // Check if verification session exists
    if (!isset($_SESSION['email_verification']) || $_SESSION['email_verification']['email'] !== $email) {
        echo json_encode(['success' => false, 'message' => 'No verification session found for this email']);
        exit;
    }
    
    $verificationData = $_SESSION['email_verification'];
    
    // Check if code is expired
    if (time() > $verificationData['expires']) {
        unset($_SESSION['email_verification']);
        echo json_encode(['success' => false, 'message' => 'Verification code has expired']);
        exit;
    }
    
    // Check if code matches
    if ($verificationData['code'] === $code) {
        // Code is correct - mark as verified
        $verificationData['verified'] = true;
        $_SESSION['email_verification'] = $verificationData;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Email verified successfully!'
        ]);
    } else {
        // Increment attempts
        $_SESSION['email_verification']['attempts']++;
        
        $remainingAttempts = 5 - $_SESSION['email_verification']['attempts'];
        
        if ($remainingAttempts <= 0) {
            // Too many failed attempts - clear session
            unset($_SESSION['email_verification']);
            echo json_encode([
                'success' => false, 
                'message' => 'Too many failed attempts. Please request a new verification code.'
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid verification code. ' . $remainingAttempts . ' attempts remaining.'
            ]);
        }
    }
    exit;
}

// Resend verification code endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'resend_code') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $name = $data['name'] ?? '';
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    // Rate limiting - check if we recently sent a code
    $lastSent = $_SESSION['last_email_sent'] ?? 0;
    $currentTime = time();
    $cooldownTime = 60; // 1 minute cooldown
    
    if ($currentTime - $lastSent < $cooldownTime) {
        $remainingTime = $cooldownTime - ($currentTime - $lastSent);
        echo json_encode([
            'success' => false, 
            'message' => "Please wait {$remainingTime} seconds before requesting a new code."
        ]);
        exit;
    }
    
    // Generate new verification code
    $verificationCode = generateVerificationCode();
    
    // Store in session
    $_SESSION['email_verification'] = [
        'email' => $email,
        'code' => $verificationCode,
        'attempts' => 0,
        'expires' => time() + 600 // 10 minutes
    ];
    
    $_SESSION['last_email_sent'] = $currentTime;
    
    // Send via Email
    $emailSent = sendVerificationEmail($email, $verificationCode, $name);
    
    if ($emailSent['success']) {
        $response = [
            'success' => true,
            'message' => 'New verification code sent via Email!',
            'method' => 'email'
        ];
        
        // Return code for testing
        if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
            $response['verification_code'] = $verificationCode;
            $response['test_mode'] = true;
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send verification email. Please try again.',
            'error' => $emailSent['error'] ?? 'Unknown error'
        ]);
    }
    exit;
}
?>