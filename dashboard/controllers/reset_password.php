<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
} else {
    require_once("./core/init.php");
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$token = isset($input['token']) ? trim($input['token']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';
$confirmPassword = isset($input['confirmPassword']) ? trim($input['confirmPassword']) : '';

if (empty($token) || empty($password) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if ($password !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit;
}

try {
    $db = DB::getInstance();
    
    // Find valid token
    $tokenData = $db->query(

        "SELECT * FROM password_resets WHERE token = ? AND used_at = 0 AND expires_at > NOW()",
        [$token]
    );
    
    if ($tokenData->count() === 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid or expired reset link. Please request a new password reset.'
        ]);
        exit;
    }
    
    $tokenInfo = $tokenData->first();
    $email = $tokenInfo->email;
    
    // Find user
    $user = $db->query("SELECT id FROM users WHERE email = ?", [$email]);
    
    if ($user->count() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $userInfo = $user->first();
    
    // Hash new password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Update user password
    $db->update('users', $userInfo->id, [
        'password' => $hashedPassword,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // Mark token as used
    $db->update('password_resets', $tokenInfo->id, [
        'used_at' => 1
    ]);
    
    // Delete all expired tokens for this email
    $db->query("DELETE FROM password_resets WHERE email = ? AND (expires_at < NOW() OR used_at = 1)", [$email]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully! You can now login with your new password.'
    ]);
    
} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
} ?>