<?php

// Set content type to JSON for AJAX responses
header('Content-Type: application/json');

// Include your core initialization file
if(file_exists(stream_resolve_include_path("core/init.php"))){
    require_once("core/init.php");
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Send JSON response and exit
 */
function sendResponse($success, $message, $data = []) {
    $response = array_merge([
        'success' => $success,
        'message' => $message
    ], $data);
    
    echo json_encode($response);
    exit;
}

/**
 * Validate CSRF token
 */
function validateToken($token) {
    return !empty($token) && strlen($token) >= 10;
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Rate limiting check
 */
function checkRateLimit($action, $identifier, $maxAttempts = 5, $timeWindow = 900) {
    $key = $action . '_attempts_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'time' => time()];
    }
    
    // Reset counter if time window has passed
    if (time() - $_SESSION[$key]['time'] > $timeWindow) {
        $_SESSION[$key] = ['count' => 0, 'time' => time()];
    }
    
    return $_SESSION[$key]['count'] < $maxAttempts;
}

/**
 * Increment rate limit counter
 */
function incrementRateLimit($action, $identifier) {
    $key = $action . '_attempts_' . md5($identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'time' => time()];
    }
    
    $_SESSION[$key]['count']++;
    $_SESSION[$key]['time'] = time();
}

/**
 * Reset rate limit counter
 */
function resetRateLimit($action, $identifier) {
    $key = $action . '_attempts_' . md5($identifier);
    unset($_SESSION[$key]);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $resetToken) {
    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $resetToken;
    
    $subject = "Demande de réinitialisation de mot de passe";
    $message = "
    <html>
    <head>
        <title>Réinitialisation du mot de passe</title>
    </head>
    <body>
        <h2>Demande de réinitialisation de mot de passe</h2>
        <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le lien ci-dessous pour le réinitialiser :</p>
        <p><a href='{$resetLink}'>Réinitialiser le mot de passe</a></p>
        <p>Si vous n'avez pas fait cette demande, veuillez ignorer cet e-mail.</p>
        <p>Ce lien expirera dans 1 heure.</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: noreply@' . $_SERVER['HTTP_HOST'] . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

/**
 * Send welcome email after registration
 */
function sendWelcomeEmail($email, $fullname, $verificationToken = null) {
    $subject = "Bienvenue sur notre plateforme !";
    $verificationLink = $verificationToken ? "http://" . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $verificationToken : null;
    
    $message = "
    <html>
    <head>
        <title>Bienvenue !</title>
    </head>
    <body>
        <h2>Bienvenue {$fullname} !</h2>
        <p>Merci de rejoindre notre plateform. Votre compte a été créé avec succès.</p>";
    
    if ($verificationLink) {
        $message .= "<p>Veuillez vérifier votre adresse e-mail en cliquant sur le lien ci-dessous :</p>
                    <p><a href='{$verificationLink}'>Vérifier l'adresse e-mail</a></p>";
    }
    
    $message .= "
        <p>Si vous avez des questions, n'hésitez pas à contacter notre équipe de support.</p>
        <p>Cordialement,<br>L'équipe</p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: welcome@' . $_SERVER['HTTP_HOST'] . "\r\n";
    
    return mail($email, $subject, $message, $headers);
}

/**
 * Verify Google JWT token
 */
function verifyGoogleToken($credential) {
    $client_id = 'YOUR_GOOGLE_CLIENT_ID'; // Replace with your Google Client ID
    
    // Simple JWT verification (in production, use a proper JWT library)
    $parts = explode('.', $credential);
    if (count($parts) !== 3) {
        return false;
    }
    
    $header = json_decode(base64_decode($parts[0]), true);
    $payload = json_decode(base64_decode($parts[1]), true);
    
    // Verify basic claims
    if (!$payload || !isset($payload['aud']) || $payload['aud'] !== $client_id) {
        return false;
    }
    
    if (!isset($payload['exp']) || $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Méthode de requête invalide');
}

// Check if action is specified
if (!isset($_POST['action'])) {
    sendResponse(false, 'Aucune action spécifiée');
}

$action = sanitizeInput($_POST['action']);

try {
    switch ($action) {
        case 'check_session':
            handleCheckSession();
            break;
        case 'login':
            handleLogin();
            break;
        case 'signup':
            handleSignup();
            break;
        case 'forgot_password':
            handleForgotPassword();
            break;
        case 'social_login':
            handleSocialLogin();
            break;
        case 'logout':
            handleLogout();
            break;
        default:
            sendResponse(false, 'Action invalide');
    }
} catch (Exception $e) {
    error_log("Erreur du gestionnaire d'authentification : " . $e->getMessage());
    sendResponse(false, 'Une erreur est survenue. Veuillez réessayer.');
}

/**
 * Check session status
 */
function handleCheckSession() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_email'])) {
        $userData = [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'fullname' => $_SESSION['user_fullname'] ?? '',
            'username' => $_SESSION['user_username'] ?? ''
        ];
        
        sendResponse(true, 'Session active', ['user' => $userData]);
    } else {
        sendResponse(false, 'Aucune session active');
    }
}

/**
 * Handle user login
 */
function handleLogin() {
    // Validate CSRF token
    if (!isset($_POST['token']) || !validateToken($_POST['token'])) {
        sendResponse(false, 'Token de sécurité invalide');
    }
    
    // Get and sanitize input
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = ($_POST['remember_me'] ?? 'false') === 'true';
    
    // Validate input
    if (empty($username) || empty($password)) {
        sendResponse(false, 'Nom d\'utilisateur et mot de passe requis');
    }
    
    if (!isValidEmail($username)) {
        sendResponse(false, 'Format d\'email invalide');
    }
    
    // Check rate limiting
    $identifier = $_SERVER['REMOTE_ADDR'] . '_' . $username;
    if (!checkRateLimit('login', $identifier)) {
        sendResponse(false, 'Trop de tentatives de connexion. Veuillez attendre avant de réessayer.');
    }
    
    global $db; // Assuming you have a database connection
    
    try {
        // Query user from database
        $stmt = $db->prepare("SELECT id, username, email, fullname, password_hash, is_active, email_verified FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            incrementRateLimit('login', $identifier);
            sendResponse(false, 'Email ou mot de passe incorrect');
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            incrementRateLimit('login', $identifier);
            sendResponse(false, 'Email ou mot de passe incorrect');
        }
        
        // Check if account is active
        if (!$user['is_active']) {
            sendResponse(false, 'Compte désactivé. Contactez l\'administrateur.');
        }
        
        // Optional: Check if email is verified
        if (!$user['email_verified']) {
            sendResponse(false, 'Veuillez vérifier votre adresse email avant de vous connecter.');
        }
        
        // Reset rate limit on successful login
        resetRateLimit('login', $identifier);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_fullname'] = $user['fullname'];
        $_SESSION['login_time'] = time();
        
        // Handle remember me
        if ($rememberMe) {
            $rememberToken = generateSecureToken();
            
            // Store remember token in database
            $stmt = $db->prepare("UPDATE users SET remember_token = ?, remember_token_expires = DATE_ADD(NOW(), INTERVAL 30 DAY) WHERE id = ?");
            $stmt->execute([$rememberToken, $user['id']]);
            
            // Set remember me cookie (30 days)
            setcookie('remember_token', $rememberToken, time() + (30 * 24 * 60 * 60), '/', '', true, true);
        }
        
        // Update last login
        $stmt = $db->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        sendResponse(true, 'Connexion réussie', [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'],
                'fullname' => $user['fullname']
            ],
            'redirect' => './dashboard'
        ]);
        
    } catch (PDOException $e) {
        error_log("Database error during login: " . $e->getMessage());
        sendResponse(false, 'Erreur de base de données');
    }
}

/**
 * Handle user registration
 */

 function handleSignup() {
    // Validate CSRF token
    if (!isset($_POST['token']) || !validateToken($_POST['token'])) {
        sendResponse(false, 'Token de sécurité invalide');
    }
    
    // Get and sanitize input
    $fullname = sanitizeInput($_POST['fullname'] ?? '');
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($fullname) || strlen($fullname) < 2) {
        sendResponse(false, 'Le nom complet doit contenir au moins 2 caractères', ['field' => 'signupName']);
    }
    
    if (empty($username) || strlen($username) < 3) {
        sendResponse(false, 'Le nom d\'utilisateur doit contenir au moins 3 caractères', ['field' => 'signupUsername']);
    }
    
    if (!isValidEmail($email)) {
        sendResponse(false, 'Format d\'email invalide', ['field' => 'signupEmail']);
    }
    
    if (strlen($password) < 8) {
        sendResponse(false, 'Le mot de passe doit contenir au moins 8 caractères', ['field' => 'signupPassword']);
    }
    
    // Check password strength
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        sendResponse(false, 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre', ['field' => 'signupPassword']);
    }
    
    // Check rate limiting
    $identifier = $_SERVER['REMOTE_ADDR'];
    if (!checkRateLimit('signup', $identifier, 3, 3600)) { // 3 attempts per hour
        sendResponse(false, 'Trop de tentatives d\'inscription. Veuillez attendre avant de réessayer.');
    }
    
    try {
        // Create User instance
        $user = new User();
        
        // Check if email already exists
        if ($user->emailExists($email)) {

            incrementRateLimit('signup', $identifier);
            sendResponse(false, 'Cette adresse email est déjà utilisée', ['field' => 'signupEmail']);
        }
        
        // Check if username already exists
        if ($user->usernameExists($username)) {

            incrementRateLimit('signup', $identifier);
            sendResponse(false, 'Ce nom d\'utilisateur est déjà pris', ['field' => 'signupUsername']);
        }
        
        // Hash password using SHA-256 (to match your User class login method)
        $passwordHash = hash('sha256', $password);
        
        // Generate email verification token
        $verificationToken = generateSecureToken();
        
        // Get database instance
        $db = DB::getInstance();
        
        // Insert new user using your database structure
        $fields = array(
            'fullname' => $fullname,
            'username' => $username,
            'email' => $email,
            'password' => $passwordHash,
            'verification_token' => $verificationToken,
            'is_verified' => 0, // User needs to verify email
            'role' => 'admin', // Default role (adjust as needed)
            'created_at' => date('Y-m-d H:i:s')
        );
        
        if ($db->insert('users', $fields)) {
            // Reset rate limit on successful signup
            resetRateLimit('signup', $identifier);
            
            // Send welcome email with verification
            if (sendWelcomeEmail($email, $fullname, $verificationToken)) {
                sendResponse(true, 'Compte créé avec succès ! Vérifiez votre email pour activer votre compte.');
            } else {
                sendResponse(true, 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.');
            }
        } else {
            sendResponse(false, 'Erreur lors de la création du compte');
        }
        
    } catch (Exception $e) {

        error_log("Error during signup: " . $e->getMessage());
        sendResponse(false, 'Erreur lors de la création du compte');
    }
}

/**
 * Handle forgot password
 */
function handleForgotPassword() {
    // Validate CSRF token
    if (!isset($_POST['token']) || !validateToken($_POST['token'])) {
        sendResponse(false, 'Token de sécurité invalide');
    }
    
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (!isValidEmail($email)) {
        sendResponse(false, 'Format d\'email invalide');
    }
    
    // Check rate limiting
    $identifier = $_SERVER['REMOTE_ADDR'] . '_' . $email;
    if (!checkRateLimit('forgot_password', $identifier, 3, 3600)) {
        sendResponse(false, 'Trop de demandes de réinitialisation. Veuillez attendre avant de réessayer.');
    }
    
    global $db;
    
    try {
        // Check if email exists
        $stmt = $db->prepare("SELECT id, fullname FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            incrementRateLimit('forgot_password', $identifier);
            // Don't reveal if email exists or not for security
            sendResponse(true, 'Si cette adresse email existe, vous recevrez un lien de réinitialisation.');
        }
        
        // Generate reset token
        $resetToken = generateSecureToken();
        $resetExpires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        // Store reset token
        $stmt = $db->prepare("
            UPDATE users 
            SET password_reset_token = ?, password_reset_expires = ? 
            WHERE id = ?
        ");
        $stmt->execute([$resetToken, $resetExpires, $user['id']]);
        
        // Send reset email
        if (sendPasswordResetEmail($email, $resetToken)) {
            resetRateLimit('forgot_password', $identifier);
            sendResponse(true, 'Lien de réinitialisation envoyé à votre adresse email.');
        } else {
            sendResponse(false, 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.');
        }
        
    } catch (PDOException $e) {
        error_log("Database error during forgot password: " . $e->getMessage());
        sendResponse(false, 'Erreur lors du traitement de la demande');
    }
}

/**
 * Handle social login
 */
function handleSocialLogin() {
    // Validate CSRF token
    if (!isset($_POST['token']) || !validateToken($_POST['token'])) {
        sendResponse(false, 'Token de sécurité invalide');
    }
    
    $provider = sanitizeInput($_POST['provider'] ?? '');
    
    if ($provider === 'google') {
        $credential = $_POST['credential'] ?? '';
        
        if (empty($credential)) {
            sendResponse(false, 'Credential Google manquant');
        }
        
        // Verify Google token
        $googleUser = verifyGoogleToken($credential);
        if (!$googleUser) {
            sendResponse(false, 'Token Google invalide');
        }
        
        $email = $googleUser['email'] ?? '';
        $name = $googleUser['name'] ?? '';
        $googleId = $googleUser['sub'] ?? '';
        
        if (empty($email) || empty($googleId)) {
            sendResponse(false, 'Données Google incomplètes');
        }
        
        global $db;
        
        try {
            // Check if user exists with this email
            $stmt = $db->prepare("SELECT id, fullname, username, email FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingUser) {
                // User exists, update Google ID and login
                $stmt = $db->prepare("UPDATE users SET google_id = ?, last_login = NOW() WHERE id = ?");
                $stmt->execute([$googleId, $existingUser['id']]);
                
                // Set session
                $_SESSION['user_id'] = $existingUser['id'];
                $_SESSION['user_email'] = $existingUser['email'];
                $_SESSION['user_username'] = $existingUser['username'];
                $_SESSION['user_fullname'] = $existingUser['fullname'];
                $_SESSION['login_time'] = time();
                
                sendResponse(true, 'Connexion Google réussie', [
                    'user' => [
                        'id' => $existingUser['id'],
                        'email' => $existingUser['email'],
                        'username' => $existingUser['username'],
                        'fullname' => $existingUser['fullname']
                    ],
                    'redirect' => './dashboard'
                ]);
            } else {
                // Create new user
                $username = strtolower(str_replace(' ', '_', $name)) . '_' . substr(md5($email), 0, 4);
                
                $stmt = $db->prepare("
                    INSERT INTO users (fullname, username, email, google_id, email_verified, is_active, created_at) 
                    VALUES (?, ?, ?, ?, 1, 1, NOW())
                ");
                
                $stmt->execute([$name, $username, $email, $googleId]);
                $userId = $db->lastInsertId();
                
                // Set session
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_username'] = $username;
                $_SESSION['user_fullname'] = $name;
                $_SESSION['login_time'] = time();
                
                // Send welcome email
                sendWelcomeEmail($email, $name);
                
                sendResponse(true, 'Compte créé et connexion réussie via Google', [
                    'user' => [
                        'id' => $userId,
                        'email' => $email,
                        'username' => $username,
                        'fullname' => $name
                    ],
                    'redirect' => './dashboard'
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("Database error during Google login: " . $e->getMessage());
            sendResponse(false, 'Erreur lors de la connexion Google');
        }
        
    } else {
        sendResponse(false, 'Fournisseur de connexion non supporté');
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    global $db;
    
    // Clear remember token if exists
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $db->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            error_log("Error clearing remember token: " . $e->getMessage());
        }
    }
    
    // Clear remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    // Destroy session
    session_destroy();
    
    // Start new session
    session_start();
    
    sendResponse(true, 'Déconnexion réussie', ['redirect' => 'login.php']);

}?>