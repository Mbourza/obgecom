<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $phone = $data['phone'] ?? '';
    $enteredCode = $data['code'] ?? '';
    
    // Format phone number for comparison (same as in sending script)
    if (!empty($phone) && !preg_match('/^\+212/', $phone)) {
        $phone = preg_replace('/^0/', '', $phone);
        $phone = '+212' . $phone;
    }
    
    // Check if Infobip verification session exists
    // Note: Infobip doesn't use verification_sid like Twilio
    if (!isset($_SESSION['sms_verification_code']) && 
        !isset($_SESSION['whatsapp_verification_code'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'No verification session found. Please request a new code.'
        ]);
        exit;
    }
    
    // Determine which verification method was used
    $storedCode = null;
    $verificationMethod = null;
    
    if (isset($_SESSION['sms_verification_code'])) {
        $storedCode = $_SESSION['sms_verification_code'];
        $verificationMethod = 'sms';
    } elseif (isset($_SESSION['whatsapp_verification_code'])) {
        $storedCode = $_SESSION['whatsapp_verification_code'];
        $verificationMethod = 'whatsapp';
    }
    
    // Check if we have a stored code
    if (!$storedCode) {
        echo json_encode([
            'success' => false, 
            'message' => 'No verification code found. Please request a new code.'
        ]);
        exit;
    }
    
    // Check if verification phone is set
    if (!isset($_SESSION['verification_phone'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Verification session incomplete. Please request a new code.'
        ]);
        exit;
    }
    
    // Get stored phone and format it for comparison
    $storedPhone = $_SESSION['verification_phone'];
    
    // Format both phones for comparison (remove any formatting differences)
    $normalizePhone = function($phone) {
        // Remove all non-digit characters except leading +
        $phone = preg_replace('/[^\d\+]/', '', $phone);
        
        // If it starts with +212, ensure proper format
        if (strpos($phone, '+212') === 0) {
            return $phone;
        }
        
        // If it starts with 212 (without +), add it
        if (strpos($phone, '212') === 0) {
            return '+' . $phone;
        }
        
        // If it starts with 0, convert to +212
        if (strpos($phone, '0') === 0) {
            return '+212' . substr($phone, 1);
        }
        
        return $phone;
    };
    
    $normalizedStoredPhone = $normalizePhone($storedPhone);
    $normalizedInputPhone = $normalizePhone($phone);
    
    // Check if the phone number matches
    if ($normalizedStoredPhone !== $normalizedInputPhone) {
        echo json_encode([
            'success' => false, 
            'message' => 'Phone number mismatch. Please use the same phone number you requested the code for.',
            'debug' => [
                'stored' => $normalizedStoredPhone,
                'input' => $normalizedInputPhone
            ]
        ]);
        exit;
    }
    
    // Check expiration (10 minutes)
    if (!isset($_SESSION['verification_expires'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'Verification session expired. Please request a new code.'
        ]);
        exit;
    }
    
    if (time() > $_SESSION['verification_expires']) {
        // Clear all verification session data
        unset($_SESSION['sms_verification_code']);
        unset($_SESSION['whatsapp_verification_code']);
        unset($_SESSION['verification_phone']);
        unset($_SESSION['verification_expires']);
        unset($_SESSION['verification_method']);
        
        echo json_encode([
            'success' => false, 
            'message' => 'Verification code expired. Please request a new one.'
        ]);
        exit;
    }
    
    // Verify the code
    if ($storedCode === $enteredCode) {
        // Mark phone as verified
        $_SESSION['phone_verified'] = true;
        $_SESSION['verified_phone'] = $normalizedInputPhone;
        $_SESSION['verified_at'] = time();
        $_SESSION['verified_method'] = $verificationMethod;
        
        // Clear verification session data
        unset($_SESSION['sms_verification_code']);
        unset($_SESSION['whatsapp_verification_code']);
        unset($_SESSION['verification_phone']);
        unset($_SESSION['verification_expires']);
        unset($_SESSION['verification_method']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Phone number verified successfully!',
            'method' => $verificationMethod
        ]);
    } else {
        // Code doesn't match
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid verification code. Please try again.',
            'debug' => [
                'entered' => $enteredCode,
                'expected' => $storedCode,
                'method' => $verificationMethod
            ]
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]);
}

// Debug endpoint to check session state
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['debug_session'])) {
    echo json_encode([
        'session_data' => [
            'sms_verification_code' => $_SESSION['sms_verification_code'] ?? 'not set',
            'whatsapp_verification_code' => $_SESSION['whatsapp_verification_code'] ?? 'not set',
            'verification_phone' => $_SESSION['verification_phone'] ?? 'not set',
            'verification_expires' => $_SESSION['verification_expires'] ?? 'not set',
            'verification_method' => $_SESSION['verification_method'] ?? 'not set',
            'phone_verified' => $_SESSION['phone_verified'] ?? false,
            'verified_phone' => $_SESSION['verified_phone'] ?? 'not set',
            'verified_at' => $_SESSION['verified_at'] ?? 'not set',
            'verified_method' => $_SESSION['verified_method'] ?? 'not set'
        ],
        'current_time' => time(),
        'session_expired' => isset($_SESSION['verification_expires']) ? 
            (time() > $_SESSION['verification_expires'] ? 'YES' : 'NO') : 'unknown',
        'expires_in' => isset($_SESSION['verification_expires']) ? 
            ($_SESSION['verification_expires'] - time()) . ' seconds' : 'unknown'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Clear session endpoint for testing
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['clear_session'])) {
    unset($_SESSION['sms_verification_code']);
    unset($_SESSION['whatsapp_verification_code']);
    unset($_SESSION['verification_phone']);
    unset($_SESSION['verification_expires']);
    unset($_SESSION['verification_method']);
    unset($_SESSION['phone_verified']);
    unset($_SESSION['verified_phone']);
    unset($_SESSION['verified_at']);
    unset($_SESSION['verified_method']);
    
    session_regenerate_id(true);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Session cleared successfully'
    ]);
    exit;
}
?>