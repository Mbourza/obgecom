<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $enteredCode = $data['code'] ?? '';
    
    // Check if verification session exists
    if (!isset($_SESSION['email_verification'])) {
        echo json_encode([
            'success' => false, 
            'message' => 'No verification session found. Please request a new code.'
        ]);
        exit;
    }
    
    $verificationData = $_SESSION['email_verification'];
    
    // Check if the email matches
    if ($verificationData['email'] !== $email) {
        echo json_encode([
            'success' => false, 
            'message' => 'Email address mismatch. Please request a new code.'
        ]);
        exit;
    }
    
    // Check expiration (10 minutes)
    if (time() > $verificationData['expires']) {
        unset($_SESSION['email_verification']);
        echo json_encode([
            'success' => false, 
            'message' => 'Verification code expired. Please request a new one.'
        ]);
        exit;
    }
    
    // Check attempts (prevent brute force - max 5 attempts)
    if ($verificationData['attempts'] >= 5) {
        unset($_SESSION['email_verification']);
        echo json_encode([
            'success' => false, 
            'message' => 'Too many failed attempts. Please request a new code.'
        ]);
        exit;
    }
    
    // Increment attempts
    $_SESSION['email_verification']['attempts']++;
    
    // Check if the code matches
    if ($verificationData['code'] === $enteredCode) {
        // Mark email as verified
        $_SESSION['email_verified'] = true;
        $_SESSION['verified_email'] = $email;
        $_SESSION['email_verified_at'] = time();
        
        // Clear verification session
        unset($_SESSION['email_verification']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Email address verified successfully!'
        ]);
    } else {
        $remainingAttempts = 5 - $_SESSION['email_verification']['attempts'];
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