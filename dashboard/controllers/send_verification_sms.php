<?php
session_start();
header('Content-Type: application/json');

function generateVerificationCode() {
    return sprintf("%06d", mt_rand(1, 999999));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $phone = $data['phone'] ?? '';
    $name = $data['name'] ?? '';
    $isResend = $data['resend'] ?? false;
    
    // Validate phone number
    if (empty($phone) || !preg_match('/^\+212[65]\d{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid phone number format']);
        exit;
    }
    
    // Generate verification code
    $verificationCode = generateVerificationCode();
    
    // Store in session
    $_SESSION['phone_verification'] = [
        'phone' => $phone,
        'code' => $verificationCode,
        'attempts' => 0,
        'expires' => time() + 600 // 10 minutes
    ];
    
    // Choose between WhatsApp or SMS
    $useWhatsApp = true; // Set to false for SMS
    
    if ($useWhatsApp) {
        // Send via WhatsApp
        $whatsappSent = sendWhatsAppWithTwilio($phone, $verificationCode, $name);
        
        if ($whatsappSent) {
            $response = [
                'success' => true,
                'message' => $isResend ? 'Verification code resent via WhatsApp!' : 'Verification code sent via WhatsApp!',
                'method' => 'whatsapp'
            ];
            
            // Return code for testing
            if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                $response['verification_code'] = $verificationCode;
            }
            
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send WhatsApp message. Please try SMS instead.']);
        }
    } else {
        // Send via SMS
        $smsSent = sendSMSWithTwilio($phone, $verificationCode, $name);
        
        if ($smsSent) {
            $response = [
                'success' => true,
                'message' => $isResend ? 'Verification code resent via SMS!' : 'Verification code sent via SMS!',
                'method' => 'sms'
            ];
            
            // Return code for testing
            if ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                $response['verification_code'] = $verificationCode;
            }
            
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send SMS. Please try again.']);
        }
    }
}

function sendWhatsAppWithTwilio($to, $code, $name = '') {
    $account_sid = 'AC9ed1298658f3ced50c6d88d1deee1275';
    $auth_token = 'bb0d7c1ad762489e6b3bc47db6c50ede';
    $twilio_whatsapp_number = 'whatsapp:+14155238886'; // Twilio's WhatsApp sandbox number
    
    // Format phone for WhatsApp
    $whatsapp_to = 'whatsapp:' . $to;
    
    $message = "Hello " . ($name ?: "there") . "! 👋\n\n";
    $message .= "Your *OBG ECOM* verification code is:\n";
    $message .= "🎯 *" . $code . "*\n\n";
    $message .= "This code will expire in 10 minutes.\n\n";
    $message .= "Thank you for choosing OBG ECOM! 🚀";
    
    try {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";
        
        $postData = http_build_query([
            'From' => $twilio_whatsapp_number,
            'To' => $whatsapp_to,
            'Body' => $message
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_USERPWD, "{$account_sid}:{$auth_token}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            error_log("WhatsApp message sent successfully to: $to");
            return true;
        } else {
            $responseData = json_decode($response, true);
            error_log("WhatsApp failed: HTTP $httpCode - " . ($responseData['message'] ?? $response));
            return false;
        }
    } catch (Exception $e) {
        error_log("WhatsApp exception: " . $e->getMessage());
        return false;
    }
}

function sendSMSWithTwilio($to, $code, $name = '') {
    $account_sid = 'AC9ed1298658f3ced50c6d88d1deee1275';
    $auth_token = 'bb0d7c1ad762489e6b3bc47db6c50ede';
    $twilio_number = '+14155238886';
    
    $message = "Hello " . ($name ?: "there") . "! Your OBG ECOM verification code is: " . $code . ". Valid for 10 minutes.";
    
    try {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";
        
        $postData = http_build_query([
            'From' => $twilio_number,
            'To' => $to,
            'Body' => $message
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_USERPWD, "{$account_sid}:{$auth_token}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            error_log("SMS sent successfully to: $to");
            return true;
        } else {
            $responseData = json_decode($response, true);
            error_log("SMS failed: HTTP $httpCode - " . ($responseData['message'] ?? $response));
            return false;
        }
    } catch (Exception $e) {
        error_log("SMS exception: " . $e->getMessage());
        return false;
    }
}
?>