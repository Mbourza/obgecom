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

// Get POST data
$name = $_POST['name'] ?? '';
$description = $_POST['description'] ?? '';
$responsable_id = $_POST['responsable_id'] ?? null;

// Validation
if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Le nom de l\'équipe est requis']);
    exit;
}

// Check if team name already exists for this user
$existing = $db->getThisQuery(
    "SELECT id FROM teams WHERE name = ? AND user_id = ?",
    [$name, $user_id]
);

if ($existing) {
    echo json_encode(['success' => false, 'error' => 'Cette équipe existe déjà']);
    exit;
}

try {
    // Insert new team
    $teamData = [
        'user_id' => $user_id,
        'name' => $name,
        'responsable_id' => $responsable_id ?: null,
        'description' => $description,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $db->insert('teams', $teamData);
    
    echo json_encode(['success' => true, 'message' => 'Équipe créée avec succès']);
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
}
?>