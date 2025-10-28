<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_id = getCurrentUserId($db);

// Validate required fields
$required_fields = ['name', 'email'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Champs obligatoires manquants: ' . implode(', ', $missing_fields)
    ]);
    exit;
}

// Validate email format
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Format d\'email invalide']);
    exit;
}

// Sanitize and prepare data
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
$current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';

try {
    // Check if email already exists for another user
    $email_check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
    $email_exists = $db->getThisQuery($email_check_query, [$email, $user_id]);
    
    if (!empty($email_exists)) {
        echo json_encode([
            'success' => false,
            'message' => 'Cet email est déjà utilisé par un autre utilisateur'
        ]);
        exit;
    }
    
    // Get current user data
    $user_query = "SELECT * FROM users WHERE id = ?";
    $current_user = $db->getThisQuery($user_query, [$user_id]);
    
    if (empty($current_user)) {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit;
    }
    
    $current_user = $current_user[0];
    
    // Prepare update data
    $update_data = [
        'name' => $name,
        'email' => $email,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Handle password change if requested
    if (!empty($new_password)) {
        // Verify current password
        if (!password_verify($current_password, $current_user['password'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect'
            ]);
            exit;
        }
        
        // Validate new password
        if ($new_password !== $confirm_password) {
            echo json_encode([
                'success' => false,
                'message' => 'Les nouveaux mots de passe ne correspondent pas'
            ]);
            exit;
        }
        
        if (strlen($new_password) < 6) {
            echo json_encode([
                'success' => false,
                'message' => 'Le nouveau mot de passe doit contenir au moins 6 caractères'
            ]);
            exit;
        }
        
        // Hash new password
        $update_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
    }
    
    // Add any other profile fields you want to update
    $optional_fields = ['phone'];
    foreach ($optional_fields as $field) {
        if (isset($_POST[$field])) {
            $update_data[$field] = trim($_POST[$field]);
        }
    }
    
    // Update user profile
    $result = $db->update('users', $user_id, $update_data);
    
    if ($result) {
        
        echo json_encode([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'user' => [
                'id' => $user_id,
                'name' => $name,
                'email' => $email
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Échec de la mise à jour du profil'
        ]);
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in update_profile.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la mise à jour du profil'
    ]);
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
} ?>