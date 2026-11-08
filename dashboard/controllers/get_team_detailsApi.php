<?php
// get_team_detailsApi.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = DB::getInstance();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Use GET']);
    exit;
}

// Get current user ID from session
$current_user_id = getCurrentUserId($db);

try {
    // Get query parameters
    $team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
    $responsable_id = isset($_GET['responsable_id']) ? intval($_GET['responsable_id']) : 0;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $include_agents = isset($_GET['include_agents']) ? filter_var($_GET['include_agents'], FILTER_VALIDATE_BOOLEAN) : true;
    $include_performance = isset($_GET['include_performance']) ? filter_var($_GET['include_performance'], FILTER_VALIDATE_BOOLEAN) : false;
    
    if ($team_id > 0) {
        // Get single team details
        $team_query = "
            SELECT 
                t.*,
                r.name as responsable_name,
                r.email as responsable_email,
                r.phone as responsable_phone,
                u.name as created_by_name,
                u.email as created_by_email
            FROM teams t
            LEFT JOIN responsables r ON t.responsable_id = r.id
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
            LIMIT 1
        ";
        
        $team = $db->getThisQuery($team_query, [$team_id]);
        
        if (!$team || empty($team)) {
            echo json_encode([
                'success' => false,
                'message' => 'Team not found or you do not have permission to access it'
            ]);
            exit;
        }
        
        $team_data = $team[0];
        
        if ($include_agents) {
            // Get agents for this team
            $agents_query = "
                SELECT 
                    a.*,
                    u.username,
                    u.avatar,
                    u.city,
                    u.status,
                    u.derniere_connexion as last_login,
                    u.phone as user_phone,
                    u.role as user_role
                FROM agents a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.team_id = ? AND a.is_active = 1
                ORDER BY a.score DESC, a.name ASC
            ";
            
            $agents = $db->getThisQuery($agents_query, [$team_id]);
            $team_data['agents'] = $agents ?: [];
            $team_data['agent_count'] = count($team_data['agents']);
            
            // Calculate team performance if requested
            if ($include_performance && !empty($agents)) {
                $total_score = 0;
                $total_confirmation = 0;
                $total_earned = 0;
                $total_payed = 0;
                $active_agents = 0;
                
                foreach ($agents as &$agent) {
                    $agent_score = floatval($agent['score'] ?: 0);
                    $agent_confirmation = floatval($agent['confirmation_rate'] ?: 0);
                    $agent_earned = floatval($agent['total_earned'] ?: 0);
                    $agent_payed = floatval($agent['total_payed'] ?: 0);
                    
                    $total_score += $agent_score;
                    $total_confirmation += $agent_confirmation;
                    $total_earned += $agent_earned;
                    $total_payed += $agent_payed;
                    
                    if ($agent['is_active']) {
                        $active_agents++;
                    }
                    
                    // Add performance rating to each agent
                    $agent['performance_rating'] = calculatePerformanceRating($agent_confirmation);
                    $agent['net_earnings'] = $agent_earned - $agent_payed;
                }
                
                $team_data['performance_metrics'] = [
                    'total_agents' => count($agents),
                    'active_agents' => $active_agents,
                    'average_score' => count($agents) > 0 ? round($total_score / count($agents), 2) : 0,
                    'average_confirmation_rate' => count($agents) > 0 ? round($total_confirmation / count($agents), 2) : 0,
                    'total_earnings' => $total_earned,
                    'total_payments' => $total_payed,
                    'net_earnings' => $total_earned - $total_payed
                ];
                
                $team_data['agents'] = $agents;
            }
        }
        
        // Get team statistics
        $stats_query = "
            SELECT 
                COUNT(*) as total_agents,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_agents,
                AVG(score) as avg_score,
                AVG(confirmation_rate) as avg_confirmation,
                SUM(total_earned) as total_earned,
                SUM(total_payed) as total_payed
            FROM agents
            WHERE team_id = ?
        ";
        
        $stats = $db->getThisQuery($stats_query, [$team_id]);
        $team_data['statistics'] = $stats ? $stats[0] : [];
        
        echo json_encode([
            'success' => true,
            'data' => $team_data,
            'message' => 'Team details retrieved successfully'
        ]);
        
    } elseif ($responsable_id > 0) {
        // Get all teams managed by this responsable
        $teams_query = "
            SELECT 
                t.*,
                r.name as responsable_name,
                r.email as responsable_email,
                (SELECT COUNT(*) FROM agents a WHERE a.team_id = t.id AND a.is_active = 1) as agent_count,
                (SELECT SUM(total_earned) FROM agents a WHERE a.team_id = t.id) as total_revenue
            FROM teams t
            LEFT JOIN responsables r ON t.responsable_id = r.id
            WHERE t.responsable_id = ?
            ORDER BY t.created_at DESC
        ";
        
        $teams = $db->getThisQuery($teams_query, [$responsable_id]);
        
        // Get responsable details
        $responsable_query = "SELECT * FROM responsables WHERE id = ? LIMIT 1";
        $responsable = $db->getThisQuery($responsable_query, [$responsable_id]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'responsable' => $responsable ? $responsable[0] : null,
                'teams' => $teams ?: [],
                'team_count' => count($teams ?: [])
            ],
            'message' => 'Responsable teams retrieved successfully'
        ]);
        
    } elseif ($user_id > 0) {
        // Get teams created by this user
        $teams_query = "
            SELECT 
                t.*,
                r.name as responsable_name,
                r.email as responsable_email,
                (SELECT COUNT(*) FROM agents a WHERE a.team_id = t.id AND a.is_active = 1) as agent_count
            FROM teams t
            LEFT JOIN responsables r ON t.responsable_id = r.id
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
        ";
        
        $teams = $db->getThisQuery($teams_query, [$user_id]);
        
        echo json_encode([
            'success' => true,
            'data' => $teams ?: [],
            'count' => count($teams ?: []),
            'message' => 'User teams retrieved successfully'
        ]);
        
    } else {
        // Get all teams with basic info
        // If current_user_id is available, you might want to filter by permissions
        $where_clause = "";
        $params = [];
        
        // Example: If you want to show only teams where current user is responsable or created them
        if ($current_user_id) {
            // You can add permission-based filtering here
            // For example, show teams where user is responsable or created the team
            // $where_clause = "WHERE t.responsable_id = ? OR t.user_id = ?";
            // $params = [$current_user_id, $current_user_id];
        }
        
        $teams_query = "
            SELECT 
                t.id,
                t.name as team_name,
                t.description,
                t.created_at,
                t.updated_at,
                r.name as responsable_name,
                r.email as responsable_email,
                u.name as created_by_name,
                (SELECT COUNT(*) FROM agents a WHERE a.team_id = t.id AND a.is_active = 1) as agent_count,
                (SELECT SUM(total_earned) FROM agents a WHERE a.team_id = t.id) as total_revenue
            FROM teams t
            LEFT JOIN responsables r ON t.responsable_id = r.id
            LEFT JOIN users u ON t.user_id = u.id
            $where_clause
            ORDER BY t.created_at DESC
        ";
        
        $teams = $db->getThisQuery($teams_query, $params);
        
        echo json_encode([
            'success' => true,
            'data' => $teams ?: [],
            'count' => count($teams ?: []),
            'message' => 'All teams retrieved successfully'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get_team_detailsApi.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching team data: ' . $e->getMessage()
    ]);
}

/**
 * Get current user ID from session
 */
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    // Check if user ID is directly available in session
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    $identifier = $_SESSION['user']['username'];

    // Try to get user ID from database
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

/**
 * Calculate performance rating based on confirmation rate
 */
function calculatePerformanceRating($confirmation_rate) {
    $rate = floatval($confirmation_rate);
    
    if ($rate >= 90) return 'Excellent';
    if ($rate >= 75) return 'Good';
    if ($rate >= 60) return 'Fair';
    return 'Poor';
}

/**
 * Function to check if user has permission to access team
 * You can customize this based on your permission system
 */
function hasTeamAccess($db, $user_id, $team_id) {
    // Check if user is team creator, responsable, or admin
    $query = "
        SELECT t.id 
        FROM teams t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.id = ? 
        AND (t.user_id = ? OR t.responsable_id = ? OR ? IN (
            SELECT user_id FROM user_roles WHERE role = 'admin'
        ))
        LIMIT 1
    ";
    
    $result = $db->getThisQuery($query, [$team_id, $user_id, $user_id, $user_id]);
    return !empty($result);
}
?>