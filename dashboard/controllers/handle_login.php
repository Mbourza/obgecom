<?php 
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required'
    ]);
    exit;
}

$username = trim($input['username']);
$password = $input['password'];
$remember = isset($input['remember']) ? (bool)$input['remember'] : false;

// Validate empty fields
if (empty($username)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez entrer votre nom d\'utilisateur ou email',
        'field' => 'username'
    ]);
    exit;
}

if (empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez entrer votre mot de passe',
        'field' => 'password'
    ]);
    exit;
}

try {
    // Create User instance
    $user = new User();
    
    // First, check if user exists and validate password
    if ($user->find($username)) {
        $userData = $user->data();
        $userType = $user->getUserType();
        
        // Verify password first
        if (password_verify($password, $userData->password)) {
            // Password is correct, now check if account is active (payment/subscription status)
            $isActive = false;
            
            if ($userType === 'agent') {
                $isActive = $userData->is_active == 1;
            } else {
                // For users, check multiple possible active fields (payment/subscription status)
                if (isset($userData->is_active)) {
                    $isActive = $userData->is_active == 1;
                } elseif (isset($userData->active)) {
                    $isActive = $userData->active == 1;
                } else {
                    $isActive = true; // Default for users without active field
                }
            }
            
            if ($isActive) {
                // Account is active (payment made), proceed with login regardless of email verification
                $loginResult = $user->login($username, $password, $remember);
                
                if ($loginResult) {
                    // Check if email is verified
                    $emailVerified = isset($userData->is_verified) ? $userData->is_verified == 1 : true;
                    
                    // Login successful
                    if ($userType === 'user') {
                        $responseData = [
                            'success' => true,
                            'message' => 'Connexion réussie',
                            'user' => [
                                'id' => $userData->id,
                                'username' => $userData->username,
                                'email' => $userData->email ?? '',
                                'name' => $userData->name ?? $userData->username,
                                'role' => $userData->role ?? null,
                                'type' => 'user',
                                'phone' => $userData->phone ?? '',
                                'is_active' => $userData->is_active ?? $userData->active ?? 1,
                                'is_verified' => $emailVerified
                            ],
                            'redirect' => './dashboard/home.php'
                        ];
                        
                        // Add email verification reminder if needed
                        if (!$emailVerified) {
                            $responseData['email_verification_needed'] = true;
                            $responseData['verification_message'] = 'Veuillez vérifier votre adresse email pour accéder à toutes les fonctionnalités.';
                        }
                    } else {
                        $responseData = [
                            'success' => true,
                            'message' => 'Connexion réussie',
                            'user' => [
                                'id' => $userData->id,
                                'username' => $userData->email, // Agents use email as username
                                'email' => $userData->email,
                                'name' => $userData->name,
                                'role' => $userData->role ?? 'agent',
                                'type' => 'agent',
                                'phone' => $userData->phone ?? '',
                                'user_id' => $userData->user_id, // Parent user ID
                                'is_active' => $userData->is_active ?? 1,
                                'is_verified' => $emailVerified
                            ],
                            'redirect' => './dashboard/agents/'
                        ];
                    }
                    
                    echo json_encode($responseData);
                }
            } else {
                // Account exists and password is correct, but account is inactive due to payment/subscription issues
                http_response_code(403);
                
                $responseData = [
                    'success' => false,
                    'account_inactive' => true,
                    'user_type' => $userType,
                    'user_id' => $userData->id,
                    'email' => $userData->email ?? '',
                    'username' => $userType === 'user' ? $userData->username : $userData->email
                ];
                
                if ($userType === 'user') {
                    // Account inactive due to payment/subscription
                    $responseData['message'] = 'Votre compte est inactif. Veuillez effectuer le paiement ou contacter le support.';
                    $responseData['action_needed'] = 'payment_or_activation';
                    $responseData['redirect'] = './activate-account.php';
                } else {
                    // Agent account inactive
                    $responseData['message'] = 'Votre compte agent est inactif. Veuillez contacter l\'administrateur.';
                    $responseData['action_needed'] = 'contact_admin';
                    $responseData['redirect'] = './contact-admin.php';
                }
                
                echo json_encode($responseData);
            }
        } else {
            // Password is incorrect
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Nom d\'utilisateur/email ou mot de passe incorrect',
                'field' => 'password'
            ]);
        }
    } else {
        // User not found
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Nom d\'utilisateur/email ou mot de passe incorrect',
            'field' => 'username'
        ]);
    }

} catch (Exception $e) {
    // Handle database or other errors
    error_log('Login error: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion. Veuillez réessayer.'
    ]);
}
?>