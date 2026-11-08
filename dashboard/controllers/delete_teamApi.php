<?php
// delete_teamApi.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get current user ID
$current_user_id = getCurrentUserId($db);
if (!$current_user_id) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $team_id = isset($data['team_id']) ? intval($data['team_id']) : 0;
    $force_delete = isset($data['force_delete']) ? filter_var($data['force_delete'], FILTER_VALIDATE_BOOLEAN) : false;
    $action = isset($data['action']) ? $data['action'] : 'delete';
    
    if ($team_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID d\'équipe invalide']);
        exit;
    }
    
    // Check if team exists and user has permission
    $team_query = "SELECT t.*, r.user_id as responsable_user_id 
                   FROM teams t 
                   LEFT JOIN responsables r ON t.responsable_id = r.id 
                   WHERE t.id = ?";
    $team = $db->getThisQuery($team_query, [$team_id]);
    
    if (empty($team)) {
        echo json_encode(['success' => false, 'message' => 'Équipe non trouvée']);
        exit;
    }
    
    $team_data = $team[0];
    
    // Check permissions: user must be team creator, responsable, or admin
    $is_creator = $team_data['user_id'] == $current_user_id;
    $is_responsable = $team_data['responsable_user_id'] == $current_user_id;
    
    // Check if user is admin (you might have a different way to check admin status)
    $admin_check = $db->getThisQuery("SELECT id FROM users WHERE id = ? AND role = 'admin'", [$current_user_id]);
    $is_admin = !empty($admin_check);
    
    if (!$is_creator && !$is_responsable && !$is_admin) {
        echo json_encode(['success' => false, 'message' => 'Vous n\'avez pas la permission de supprimer cette équipe']);
        exit;
    }
    
    // Check if team has active agents
    $agents_check = $db->getThisQuery("SELECT COUNT(*) as count FROM agents WHERE team_id = ? AND is_active = 1", [$team_id]);
    $has_active_agents = $agents_check && $agents_check[0]['count'] > 0;
    
    if ($has_active_agents && !$force_delete && $action === 'delete') {
        echo json_encode([
            'success' => false,
            'message' => 'Cette équipe contient des agents actifs. Voulez-vous forcer la suppression ?',
            'has_active_agents' => true,
            'agent_count' => $agents_check[0]['count']
        ]);
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    if ($action === 'archive') {
        // Archive the team (soft delete)
        $archive_query = "UPDATE teams SET is_archived = 1, archived_at = NOW(), archived_by = ? WHERE id = ?";
        $result = $db->getThisQuery($archive_query, [$current_user_id, $team_id]);
        
        // Also archive agents
        $archive_agents = "UPDATE agents SET is_archived = 1, team_id = NULL WHERE team_id = ?";
        $db->getThisQuery($archive_agents, [$team_id]);
        
        $message = 'Équipe archivée avec succès';
        
    } else {
        
        // First, remove agents from team (set team_id to NULL)
        $remove_agents = "UPDATE agents SET team_id = NULL WHERE team_id = ?";
        $db->query($remove_agents, [$team_id]);
        
        // Delete the team
        $delete_query = "DELETE FROM teams WHERE id = ?";
        $result = $db->query($delete_query, [$team_id]);
        
        // Log the deletion
        $log_query = "INSERT INTO deleted_teams_log (team_id, team_name, deleted_by, deleted_at, agents_count) 
                      VALUES (?, ?, ?, NOW(), ?)";
        $db->query($log_query, [
            $team_id, 
            $team_data['name'], 
            $current_user_id,
            $agents_check[0]['count'] ?? 0
        ]);
        
        $message = 'Équipe supprimée avec succès';
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'team_id' => $team_id
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    error_log("Error in delete_teamApi.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue: ' . $e->getMessage()
    ]);
}

// Helper function to get current user ID
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    $identifier = $_SESSION['user']['username'];
    $user = $db->getThisQuery("SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1", 
                              [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

    return null;
}
?>