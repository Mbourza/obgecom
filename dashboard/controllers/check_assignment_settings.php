<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

if(isset($_POST['action']) && $_POST['action'] == 'check_manual_assignment') {
    header('Content-Type: application/json');
    
    try {
        $user_id = getCurrentUserId($db);
        
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non authentifié']);
            exit;
        }
        
        // Get assignment settings from system_settings
        $settings = $db->getThisQuery("
            SELECT setting_key, setting_value 
            FROM system_settings 
            WHERE user_id = ? 
            AND setting_key IN ('assignment_method', 'assignment_allow_manual')
        ", [$user_id]);
        
        // Convert to key-value pairs
        $settings_map = [];
        foreach ($settings as $setting) {
            $settings_map[$setting['setting_key']] = $setting['setting_value'];
        }
        
        $assignment_method = $settings_map['assignment_method'] ?? 'performance';
        $allow_manual = isset($settings_map['assignment_allow_manual']) 
            ? (int) $settings_map['assignment_allow_manual'] 
            : 0;
        
        // Check if user has any active agents
        $agent_count = $db->getThisQuery("
            SELECT COUNT(*) as count 
            FROM agents 
            WHERE user_id = ? AND is_active = 1
        ", [$user_id]);
        
        $available_agents = $agent_count[0]['count'] ?? 0;
        
        if ($available_agents == 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Aucun agent actif disponible pour l\'assignation'
            ]);
            exit;
        }
        
        // Determine if manual assignment is allowed
        $is_allowed = false;
        $reason = '';
        
        if ($allow_manual == 1) {
            $is_allowed = true;
            $reason = 'Manual assignment explicitly enabled';
        } elseif ($assignment_method === 'region') {
            $is_allowed = true;
            $reason = 'Region-based assignment method requires manual assignment';
        } else {
            $reason = 'Manual assignment not allowed for current configuration';
        }
        
        if ($is_allowed) {
            echo json_encode([
                'success' => true,
                'allow_manual' => true,
                'assignment_method' => $assignment_method,
                'available_agents' => $available_agents,
                'reason' => $reason
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $reason,
                'assignment_method' => $assignment_method
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Assignment settings check error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la vérification des paramètres'
        ]);
    }
    exit;
}

// Helper function to get current user ID
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    $identifier = $_SESSION['user']['username'];

    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

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