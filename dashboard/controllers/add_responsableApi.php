<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

if (!Session::exists(Config::get('session/session_name'))) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$db = DB::getInstance();

// Get current user ID
$user_id = getCurrentUserId($db); 
    
if (!$user_id) {
    throw new Exception('Utilisateur non authentifié');
}

// Get POST data
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$password = $_POST['password'] ?? '';

// Validation
if (empty($name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Tous les champs requis doivent être remplis']);
    exit;
}

// Check if email already exists
$existing = $db->getThisQuery(
    "SELECT id FROM responsables WHERE email = ? AND user_id = ?",
    [$email, $user_id]
);

if ($existing) {
    echo json_encode(['success' => false, 'error' => 'Cet email est déjà utilisé']);
    exit;
}

try {
    // Insert new responsable
    $responsableData = [
        'user_id' => $user_id,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $db->insert('responsables', $responsableData);
    
    echo json_encode(['success' => true, 'message' => 'Responsable ajouté avec succès']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $e->getMessage()]);
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