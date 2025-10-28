<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $phone = $data['phone'] ?? '';
    $enteredCode = $data['code'] ?? '';
    
    // Check if verification session exists
    if (!isset($_SESSION['phone_verification'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'No verification session found. Please request a new code.'
        ]);
        exit;
    }
    
    $verificationData = $_SESSION['phone_verification'];
    
    // Check if the phone number matches
    if ($verificationData['phone'] !== $phone) {
        echo json_encode([
            'success' => false, 
            'message' => 'Phone number mismatch. Please request a new code.'
        ]);
        exit;
    }
    
    // Check expiration (10 minutes)
    if (time() > $verificationData['expires']) {
        unset($_SESSION['phone_verification']);
        echo json_encode([
            'success' => false, 
            'message' => 'Verification code expired. Please request a new one.'
        ]);
        exit;
    }
    
    // Check attempts (prevent brute force - max 5 attempts)
    if ($verificationData['attempts'] >= 5) {
        unset($_SESSION['phone_verification']);
        echo json_encode([
            'success' => false, 
            'message' => 'Too many failed attempts. Please request a new code.'
        ]);
        exit;
    }
    
    // Increment attempts
    $_SESSION['phone_verification']['attempts']++;
    
    // Check if the code matches
    if ($verificationData['code'] === $enteredCode) {
        // Mark phone as verified
        $_SESSION['phone_verified'] = true;
        $_SESSION['verified_phone'] = $phone;
        $_SESSION['verified_at'] = time();
        
        // Clear verification session
        unset($_SESSION['phone_verification']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Phone number verified successfully!'
        ]);
    } else {
        $remainingAttempts = 5 - $_SESSION['phone_verification']['attempts'];
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid verification code. ' . $remainingAttempts . ' attempts remaining.',
            'remaining_attempts' => $remainingAttempts
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]);
}
?>