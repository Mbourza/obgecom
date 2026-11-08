<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = DB::getInstance();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Utilisez GET']);
    exit;
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

try {
    // Récupérer l'ID de l'utilisateur courant
    $current_user_id = getCurrentUserId($db);
    if (!$current_user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit;
    }
    
    // Récupérer les paramètres
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $current_user_id;
    $responsable_id = isset($_GET['responsable_id']) ? intval($_GET['responsable_id']) : 0;
    $active_only = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : false;
    $include_stats = isset($_GET['include_stats']) ? filter_var($_GET['include_stats'], FILTER_VALIDATE_BOOLEAN) : false;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Vérifier les permissions (l'utilisateur ne peut voir que ses propres équipes ou celles de ses responsables)
    if ($user_id !== $current_user_id && !isAdmin($db, $current_user_id)) {
        // Vérifier si l'utilisateur est responsable de certaines équipes
        if (!hasAccessToUserTeams($db, $current_user_id, $user_id)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
            exit;
        }
    }
    
    // Construire la requête SQL
    $where_conditions = ["t.user_id = ?"];
    $params = [$user_id];
    
    if ($responsable_id > 0) {
        $where_conditions[] = "t.responsable_id = ?";
        $params[] = $responsable_id;
    }
    
    if ($active_only) {
        $where_conditions[] = "t.is_active = 1";
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(t.name LIKE ? OR t.description LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Base query pour les informations de l'équipe
    $teams_query = "
        SELECT 
            t.id,
            t.name,
            t.description,
            t.responsable_id,
            t.user_id,
            t.is_active,
            t.created_at,
            t.updated_at,
            r.name as responsable_name,
            r.email as responsable_email,
            r.phone as responsable_phone,
            u.name as created_by_name,
            u.email as created_by_email
    ";
    
    // Ajouter les statistiques si demandé
    if ($include_stats) {
        $teams_query .= ",
            (SELECT COUNT(*) FROM agents a WHERE a.team_id = t.id AND a.is_active = 1) as active_agents,
            (SELECT COUNT(*) FROM agents a WHERE a.team_id = t.id) as total_agents,
            (SELECT AVG(a.confirmation_rate) FROM agents a WHERE a.team_id = t.id) as avg_confirmation_rate,
            (SELECT AVG(a.score) FROM agents a WHERE a.team_id = t.id) as avg_score,
            (SELECT SUM(a.total_earned) FROM agents a WHERE a.team_id = t.id) as total_earned,
            (SELECT SUM(a.total_payed) FROM agents a WHERE a.team_id = t.id) as total_payed
        ";
    }
    
    $teams_query .= "
        FROM teams t
        LEFT JOIN responsables r ON t.responsable_id = r.id
        LEFT JOIN users u ON t.user_id = u.id
        {$where_clause}
        ORDER BY t.name ASC
    ";
    
    // Exécuter la requête
    $teams = $db->getThisQuery($teams_query, $params);
    
    if (!$teams) {
        $teams = [];
    }
    
    // Si les statistiques sont demandées, calculer les performances mensuelles
    if ($include_stats && !empty($teams)) {
        foreach ($teams as &$team) {
            $team_id = $team['id'];
            
            // Récupérer les statistiques mensuelles
            $monthly_stats_query = "
                SELECT 
                    COUNT(DISTINCT ac.id) as monthly_confirmations,
                    SUM(ac.amount) as monthly_amount
                FROM agent_confirmations ac
                INNER JOIN agents a ON ac.agent_id = a.id
                WHERE a.team_id = ?
                    AND ac.status = 'confirmed'
                    AND MONTH(ac.created_at) = MONTH(CURDATE())
                    AND YEAR(ac.created_at) = YEAR(CURDATE())
            ";
            
            $monthly_stats = $db->getThisQuery($monthly_stats_query, [$team_id]);
            
            if ($monthly_stats && isset($monthly_stats[0])) {
                $team['monthly_confirmations'] = intval($monthly_stats[0]['monthly_confirmations'] ?? 0);
                $team['monthly_amount'] = floatval($monthly_stats[0]['monthly_amount'] ?? 0);
                
                // Calculer le coût mensuel (basé sur les frais de service des agents)
                $team_cost_query = "
                    SELECT 
                        SUM(ac.monthly_amount * (a.service_fee / 100)) as monthly_cost
                    FROM (
                        SELECT 
                            agent_id,
                            SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) 
                                THEN amount ELSE 0 END) as monthly_amount
                        FROM agent_confirmations
                        WHERE status = 'confirmed'
                        GROUP BY agent_id
                    ) ac
                    INNER JOIN agents a ON ac.agent_id = a.id
                    WHERE a.team_id = ?
                ";
                
                $cost_result = $db->getThisQuery($team_cost_query, [$team_id]);
                $team['monthly_cost'] = $cost_result ? floatval($cost_result[0]['monthly_cost'] ?? 0) : 0;
                
                // Calculer le ROI mensuel
                if ($team['monthly_cost'] > 0) {
                    $team['monthly_roi'] = round((($team['monthly_amount'] - $team['monthly_cost']) / $team['monthly_cost']) * 100, 1);
                } else {
                    $team['monthly_roi'] = 0;
                }
            } else {
                $team['monthly_confirmations'] = 0;
                $team['monthly_amount'] = 0;
                $team['monthly_cost'] = 0;
                $team['monthly_roi'] = 0;
            }
            
            // Calculer la note de performance
            $avg_confirmation = floatval($team['avg_confirmation_rate'] ?? 0);
            $avg_score = floatval($team['avg_score'] ?? 0);
            $team['performance_score'] = round(($avg_confirmation * 0.6) + ($avg_score * 0.4), 1);
            
            // Déterminer la note de performance
            $team['performance_rating'] = calculatePerformanceRating($team['performance_score']);
            
            // Formatage des nombres
            $team['avg_confirmation_rate'] = round(floatval($team['avg_confirmation_rate'] ?? 0), 1);
            $team['avg_score'] = round(floatval($team['avg_score'] ?? 0), 1);
        }
    }
    
    // Préparer la réponse
    $response = [
        'success' => true,
        'data' => $teams,
        'count' => count($teams),
        'metadata' => [
            'user_id' => $user_id,
            'current_user_id' => $current_user_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'filters' => [
                'responsable_id' => $responsable_id,
                'active_only' => $active_only,
                'include_stats' => $include_stats,
                'search' => $search
            ]
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Error in get_teams.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la récupération des équipes',
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
 * Check if user is admin
 */
function isAdmin($db, $user_id) {
    $query = "
        SELECT role 
        FROM users 
        WHERE id = ? 
        AND role IN ('admin', 'super_admin')
        LIMIT 1
    ";
    
    $result = $db->getThisQuery($query, [$user_id]);
    return !empty($result);
}

/**
 * Check if user has access to another user's teams
 */
function hasAccessToUserTeams($db, $current_user_id, $target_user_id) {
    // Vérifier si l'utilisateur est responsable pour cet utilisateur
    $query = "
        SELECT id 
        FROM responsables 
        WHERE user_id = ? AND assigned_to_user_id = ?
        LIMIT 1
    ";
    
    $result = $db->getThisQuery($query, [$target_user_id, $current_user_id]);
    return !empty($result);
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
 * Alternative version - simple team list for dropdowns
 */
function getTeamsForDropdown($db, $user_id, $active_only = true) {
    $where_conditions = ["user_id = ?"];
    $params = [$user_id];
    
    if ($active_only) {
        $where_conditions[] = "is_active = 1";
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $query = "
        SELECT id, name, description
        FROM teams
        {$where_clause}
        ORDER BY name ASC
    ";
    
    return $db->getThisQuery($query, $params);
}
?>