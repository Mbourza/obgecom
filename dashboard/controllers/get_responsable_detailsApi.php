<?php
// get_responsable_detailsApi.php
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

// Check if user is authenticated
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Get query parameters
$responsable_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$include_agents = isset($_GET['include_agents']) ? filter_var($_GET['include_agents'], FILTER_VALIDATE_BOOLEAN) : true;
$include_stats = isset($_GET['include_stats']) ? filter_var($_GET['include_stats'], FILTER_VALIDATE_BOOLEAN) : true;
$include_performance = isset($_GET['include_performance']) ? filter_var($_GET['include_performance'], FILTER_VALIDATE_BOOLEAN) : false;

// Validate responsable ID
if ($responsable_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID du responsable invalide']);
    exit;
}

try {
    // Get current user ID
    $current_user_id = getCurrentUserId($db);
    if (!$current_user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit;
    }

    // Get responsable details
    $responsable_query = "
        SELECT 
            r.*,
            DATE_FORMAT(r.created_at, '%d/%m/%Y %H:%i') as created_at_formatted,
            u.name as created_by_name,
            u.email as created_by_email,
            (SELECT COUNT(*) FROM agents a WHERE a.responsable_id = r.id AND a.is_active = 1) as active_agents_count,
            (SELECT COUNT(*) FROM agents a WHERE a.responsable_id = r.id) as total_agents_count
        FROM responsables r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.id = ? AND r.user_id = ?
        LIMIT 1
    ";
    
    $responsable = $db->getThisQuery($responsable_query, [$responsable_id, $current_user_id]);
    
    if (!$responsable || empty($responsable)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Responsable non trouvé']);
        exit;
    }
    
    $responsable_data = $responsable[0];
    
    // Get agents assigned to this responsable
    $agents = [];
    if ($include_agents) {
        $agents_query = "
            SELECT 
                a.id,
                a.name,
                a.email,
                a.phone,
                a.is_active,
                a.confirmation_rate,
                a.score,
                a.service_fee,
                a.total_earned,
                a.total_payed,
                a.region_id,
                a.team_id,
                t.name as team_name,
                r.name as region_name,
                r.code as region_code,
                DATE_FORMAT(a.created_at, '%d/%m/%Y') as join_date,
                (SELECT COUNT(*) FROM agent_confirmations ac WHERE ac.agent_id = a.id AND ac.status = 'confirmed') as total_confirmations,
                (SELECT SUM(amount) FROM agent_confirmations ac WHERE ac.agent_id = a.id AND ac.status = 'confirmed') as total_amount,
                (SELECT COUNT(*) FROM agent_confirmations ac WHERE ac.agent_id = a.id AND ac.status = 'confirmed' 
                    AND MONTH(ac.created_at) = MONTH(CURDATE()) AND YEAR(ac.created_at) = YEAR(CURDATE())) as monthly_confirmations,
                (SELECT SUM(amount) FROM agent_confirmations ac WHERE ac.agent_id = a.id AND ac.status = 'confirmed' 
                    AND MONTH(ac.created_at) = MONTH(CURDATE()) AND YEAR(ac.created_at) = YEAR(CURDATE())) as monthly_amount
            FROM agents a
            LEFT JOIN teams t ON a.team_id = t.id
            LEFT JOIN regions r ON a.region_id = r.id
            WHERE a.responsable_id = ? AND a.user_id = ?
            ORDER BY a.name ASC
        ";
        
        $agents = $db->getThisQuery($agents_query, [$responsable_id, $current_user_id]);
        
        if ($agents) {
            foreach ($agents as &$agent) {
                // Calculate monthly performance
                $monthly_conf = intval($agent['monthly_confirmations'] ?: 0);
                if ($monthly_conf > 0) {
                    $agent['monthly_performance'] = round(
                        ($monthly_conf * 100) / ($monthly_conf + ($monthly_conf * 0.2)),
                        1
                    );
                } else {
                    $agent['monthly_performance'] = 0;
                }
                
                // Calculate ROI
                $monthly_amount = floatval($agent['monthly_amount'] ?: 0);
                $service_fee = floatval($agent['service_fee'] ?: 0);
                $monthly_cost = $monthly_amount * ($service_fee / 100);
                
                if ($monthly_cost > 0) {
                    $agent['monthly_roi'] = round((($monthly_amount - $monthly_cost) / $monthly_cost) * 100, 1);
                } else {
                    $agent['monthly_roi'] = 0;
                }
                
                $agent['performance_rating'] = calculatePerformanceRating($agent['monthly_performance']);
                $agent['status_badge'] = $agent['is_active'] ? 'Actif' : 'Inactif';
            }
            
            $responsable_data['agents'] = $agents;
        } else {
            $responsable_data['agents'] = [];
        }
    }
    
    // Get detailed statistics
    $stats = [];
    if ($include_stats) {
        $stats_query = "
            SELECT 
                COUNT(DISTINCT a.id) as total_agents,
                COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN a.id END) as active_agents,
                COUNT(DISTINCT a.team_id) as total_teams,
                COUNT(DISTINCT a.region_id) as total_regions,
                AVG(a.confirmation_rate) as avg_confirmation_rate,
                AVG(a.score) as avg_score,
                SUM(a.total_earned) as total_earned,
                SUM(a.total_payed) as total_payed,
                IFNULL(SUM(ac.total_confirmations), 0) as total_confirmations,
                IFNULL(SUM(ac.total_amount), 0) as total_amount,
                IFNULL(SUM(ac.monthly_confirmations), 0) as monthly_confirmations,
                IFNULL(SUM(ac.monthly_amount), 0) as monthly_amount
            FROM agents a
            LEFT JOIN (
                SELECT 
                    agent_id,
                    COUNT(*) as total_confirmations,
                    SUM(amount) as total_amount,
                    COUNT(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 END) as monthly_confirmations,
                    SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN amount ELSE 0 END) as monthly_amount
                FROM agent_confirmations
                WHERE status = 'confirmed'
                GROUP BY agent_id
            ) ac ON a.id = ac.agent_id
            WHERE a.responsable_id = ? AND a.user_id = ?
        ";
        
        $stats_result = $db->getThisQuery($stats_query, [$responsable_id, $current_user_id]);
        $stats = $stats_result ? $stats_result[0] : [];
        
        // Calculate performance metrics
        if (!empty($stats)) {
            $monthly_amount = floatval($stats['monthly_amount'] ?: 0);
            $total_amount = floatval($stats['total_amount'] ?: 0);
            
            // Calculate performance score
            $avg_confirmation = floatval($stats['avg_confirmation_rate'] ?: 0);
            $avg_score = floatval($stats['avg_score'] ?: 0);
            $performance_score = round(($avg_confirmation * 0.6) + ($avg_score * 0.4), 1);
            
            $stats['performance_score'] = $performance_score;
            $stats['performance_rating'] = calculatePerformanceRating($performance_score);
            
            // Calculate average monthly ROI
            if ($agents && !empty($agents)) {
                $total_roi = 0;
                $agents_with_roi = 0;
                foreach ($agents as $agent) {
                    if (isset($agent['monthly_roi'])) {
                        $total_roi += $agent['monthly_roi'];
                        $agents_with_roi++;
                    }
                }
                $stats['avg_monthly_roi'] = $agents_with_roi > 0 ? round($total_roi / $agents_with_roi, 1) : 0;
            }
        }
        
        $responsable_data['statistics'] = $stats;
    }
    
    // Get performance history
    $performance_history = [];
    if ($include_performance) {
        $performance_query = "
            SELECT 
                DATE_FORMAT(ac.created_at, '%Y-%m') as month,
                COUNT(DISTINCT ac.id) as confirmations,
                SUM(ac.amount) as amount,
                COUNT(DISTINCT a.id) as active_agents,
                AVG(a.confirmation_rate) as avg_confirmation_rate
            FROM agent_confirmations ac
            INNER JOIN agents a ON ac.agent_id = a.id
            WHERE a.responsable_id = ? 
                AND a.user_id = ? 
                AND ac.status = 'confirmed'
                AND ac.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(ac.created_at, '%Y-%m')
            ORDER BY month ASC
        ";
        
        $performance_history = $db->getThisQuery($performance_query, [$responsable_id, $current_user_id]);
        $responsable_data['performance_history'] = $performance_history ?: [];
        
        // Get daily performance for last 7 days
        $daily_performance_query = "
            SELECT 
                DATE(ac.created_at) as date,
                COUNT(DISTINCT ac.id) as confirmations,
                SUM(ac.amount) as amount,
                COUNT(DISTINCT a.id) as agents_active
            FROM agent_confirmations ac
            INNER JOIN agents a ON ac.agent_id = a.id
            WHERE a.responsable_id = ? 
                AND a.user_id = ? 
                AND ac.status = 'confirmed'
                AND ac.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(ac.created_at)
            ORDER BY date DESC
        ";
        
        $daily_performance = $db->getThisQuery($daily_performance_query, [$responsable_id, $current_user_id]);
        $responsable_data['daily_performance'] = $daily_performance ?: [];
    }
    
    // Get teams managed by this responsable
    $teams_query = "
        SELECT 
            t.id,
            t.name,
            t.description,
            COUNT(DISTINCT a.id) as agent_count,
            COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN a.id END) as active_agent_count,
            IFNULL(SUM(ac.monthly_amount), 0) as monthly_revenue
        FROM teams t
        LEFT JOIN agents a ON t.id = a.team_id
        LEFT JOIN (
            SELECT 
                agent_id,
                SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) 
                    THEN amount ELSE 0 END) as monthly_amount
            FROM agent_confirmations
            WHERE status = 'confirmed'
            GROUP BY agent_id
        ) ac ON a.id = ac.agent_id
        WHERE t.responsable_id = ? AND t.user_id = ?
        GROUP BY t.id, t.name, t.description
        ORDER BY t.name ASC
    ";
    
    $teams = $db->getThisQuery($teams_query, [$responsable_id, $current_user_id]);
    $responsable_data['teams_managed'] = $teams ?: [];
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => $responsable_data,
        'metadata' => [
            'agents_count' => $include_agents ? count($agents) : null,
            'teams_count' => count($teams ?: []),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $current_user_id
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error in get_responsable_detailsApi.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la récupération des données',
        'error' => $e->getMessage()
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
 * Calculate performance rating
 */
function calculatePerformanceRating($score) {
    $score = floatval($score);
    
    if ($score >= 80) return 'Excellent';
    if ($score >= 60) return 'Bon';
    if ($score >= 40) return 'Moyen';
    if ($score >= 20) return 'Faible';
    return 'Très faible';
}

/**
 * Check if user has permission to access responsable
 */
function hasResponsableAccess($db, $user_id, $responsable_id) {
    $query = "
        SELECT id 
        FROM responsables 
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ";
    
    $result = $db->getThisQuery($query, [$responsable_id, $user_id]);
    return !empty($result);
} ?>