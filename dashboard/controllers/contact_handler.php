<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php'; // Chemin vers l'autoload de PHPMailer

// Response headers
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Sanitize and validate input
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Get form data
$name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
$email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
$phone = isset($_POST['phone']) ? sanitize_input($_POST['phone']) : '';
$subject = isset($_POST['subject']) ? sanitize_input($_POST['subject']) : '';
$message = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = "Le nom est requis";
}

if (empty($email)) {
    $errors[] = "L'email est requis";
} elseif (!validate_email($email)) {
    $errors[] = "L'email n'est pas valide";
}

if (empty($subject)) {
    $errors[] = "Le sujet est requis";
}

if (empty($message)) {
    $errors[] = "Le message est requis";
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Subject mapping
$subject_options = [
    'demo' => 'Demande de démonstration',
    'support' => 'Support technique',
    'partnership' => 'Partenariat',
    'billing' => 'Question de facturation',
    'other' => 'Autre'
];

$subject_text = isset($subject_options[$subject]) ? $subject_options[$subject] : 'Autre';

// Create HTML email template
$html_message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau message de contact - OBG ECOM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8fafc;
        }
        
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .email-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .email-header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .email-body {
            padding: 40px 30px;
        }
        
        .contact-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #2a1669;
            min-width: 100px;
            margin-right: 15px;
        }
        
        .info-value {
            color: #4a5568;
            flex: 1;
            word-break: break-word;
        }
        
        .message-content {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
        }
        
        .message-content h3 {
            color: #2a1669;
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .message-text {
            color: #4a5568;
            line-height: 1.7;
            font-size: 15px;
        }
        
        .email-footer {
            background: #f8fafc;
            padding: 25px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .email-footer p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .logo-text {
            font-weight: 700;
            font-size: 20px;
            color: #2a1669;
        }
        
        .priority-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .demo-badge {
            background: #f59e0b;
        }
        
        .support-badge {
            background: #ef4444;
        }
        
        .partnership-badge {
            background: #8b5cf6;
        }
        
        .billing-badge {
            background: #06b6d4;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Nouveau Message de Contact</h1>
            <p>Formulaire de contact OBG ECOM</p>
        </div>
        
        <div class="email-body">
            <div class="contact-info">
                <div class="info-row">
                    <span class="info-label">Nom:</span>
                    <span class="info-value"><strong>' . htmlspecialchars($name) . '</strong></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><a href="mailto:' . htmlspecialchars($email) . '" style="color: #6631e1; text-decoration: none;">' . htmlspecialchars($email) . '</a></span>
                </div>
                
                ' . (!empty($phone) ? '
                <div class="info-row">
                    <span class="info-label">Téléphone:</span>
                    <span class="info-value"><a href="tel:' . htmlspecialchars($phone) . '" style="color: #6631e1; text-decoration: none;">' . htmlspecialchars($phone) . '</a></span>
                </div>' : '') . '
                
                <div class="info-row">
                    <span class="info-label">Sujet:</span>
                    <span class="info-value">
                        <span class="priority-badge ' . $subject . '-badge">' . $subject_text . '</span>
                    </span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span class="info-value">' . date('d/m/Y à H:i:s') . '</span>
                </div>
            </div>
            
            <div class="message-content">
                <h3>Message:</h3>
                <div class="message-text">' . nl2br(htmlspecialchars($message)) . '</div>
            </div>
        </div>
        
        <div class="email-footer">
            <p>Ce message a été envoyé depuis le formulaire de contact de <strong class="logo-text">OBG ECOM</strong></p>
            <p>Répondez directement à cet email pour contacter le client.</p>
        </div>
    </div>
</body>
</html>';

// Create plain text version
$plain_message = "
Nouveau message de contact - OBG ECOM
=====================================

INFORMATIONS DU CONTACT:
------------------------
Nom: $name
Email: $email
" . (!empty($phone) ? "Téléphone: $phone\n" : "") . "
Sujet: $subject_text
Date: " . date('d/m/Y à H:i:s') . "

MESSAGE:
--------
$message

---
Ce message a été envoyé depuis le formulaire de contact d'OBG ECOM.
Répondez directement à cet email pour contacter le client.
";

// Email subject
$email_subject = "[OBG ECOM Contact] " . $subject_text . " - " . $name;

// Send email using PHPMailer with your SMTP configuration
try {
    // Create PHPMailer instance
    $mail = new PHPMailer(true);

    // SMTP configuration (using your existing settings)
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'support@obgecom.com';
    $mail->Password   = 'Obg@123456';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Sender info
    $mail->setFrom('support@obgecom.com', 'OBG ECOM Contact Form');
    $mail->addReplyTo($email, $name);

    // Recipient - change to your actual email
    $mail->addAddress('support@obgecom.com', 'Équipe OBG');

    // Content
    $mail->isHTML(true);
    $mail->Subject = $email_subject;
    $mail->Body    = $html_message;
    $mail->AltBody = $plain_message;

    // Send email
    $mail->send();
    
    // Log the contact (optional)
    $log_entry = date('Y-m-d H:i:s') . " - Contact from: $name ($email) - Subject: $subject_text\n";
    file_put_contents('contact_logs.txt', $log_entry, FILE_APPEND | LOCK_EX);
    
    echo json_encode([
        'success' => true,
        'message' => 'Merci pour votre message! Nous vous contacterons dans les plus brefs délais.'
    ]);
    
} catch (Exception $e) {
    error_log("Contact form PHPMailer Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur s\'est produite lors de l\'envoi de votre message. Veuillez réessayer plus tard.'
    ]);
}
?>