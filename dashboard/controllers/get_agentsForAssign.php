<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $user_id = getCurrentUserId($db);
        
        if (!$user_id) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non authentifié']);
            exit;
        }
        
        switch ($_POST['action']) {
            case 'get_agents_with_regions':
                // Get all regions first
                $regions = $db->getThisQuery("
                    SELECT id, name 
                    FROM regions 
                    WHERE is_global = 1
                    ORDER BY name
                ");
                
                // Get agents with region information and performance metrics
                $agents = $db->getThisQuery("
                    SELECT 
                        a.id, a.name, a.phone, a.email,
                        a.region_id, r.name as region_name,
                        a.confirmation_rate,
                        a.score,
                        (
                            SELECT COUNT(*) 
                            FROM agent_order_assignments aoa 
                            WHERE aoa.agent_id = a.id 
                            AND aoa.status = 'pending'
                        ) as current_assignments
                    FROM agents a
                    LEFT JOIN regions r ON a.region_id = r.id
                    WHERE a.user_id = ? 
                    AND a.is_active = 1
                    ORDER BY a.name
                ", [$user_id]);
                
                echo json_encode([
                    'success' => true,
                    'agents' => $agents,
                    'regions' => $regions
                ]);
                break;
                
            case 'get_available_agents':
                // Simple agent list (for individual assignment modal)
                $agents = $db->getThisQuery("
                    SELECT 
                        a.id, a.name, a.phone, a.email,
                        a.region_id, r.name as region_name
                    FROM agents a
                    LEFT JOIN regions r ON a.region_id = r.id
                    WHERE a.user_id = ? 
                    AND a.is_active = 1
                    ORDER BY a.name
                ", [$user_id]);
                
                echo json_encode([
                    'success' => true,
                    'agents' => $agents
                ]);
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'message' => 'Action non reconnue'
                ]);
        }
        
    } catch (Exception $e) {
        error_log("Get agents error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du chargement des agents'
        ]);
    }
    exit;
}

// Same getCurrentUserId function as above
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