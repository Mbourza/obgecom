<?php

if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../login'); 
} 

$db = DB::getInstance();

if (isset($_GET['logout'])) {
    logout();
}

// Get current user
$user = $db->getThisQuery(
    "SELECT id, `name`, `role` 
     FROM users 
     WHERE username = ? OR email = ? OR phone = ? 
     LIMIT 1", 
    [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]
);

$regions = $db->getThisQuery(
    "SELECT id, name, code FROM regions ORDER BY name"
);

if (!$user || !isset($user[0]['id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Utilisateur non trouvé',
        'code'    => 'USER_NOT_FOUND'
    ]);
    exit;
}

$user_id = (int)$user[0]['id'];
$userName = $user[0]['name'];
$userRole = $user[0]['role'];

// Check subscription
$subscription = new Subscription($user_id);
$subscriptionStatus = $subscription->getSubscriptionStatus();
$subscriptionAlerts = $subscription->getSubscriptionAlerts();

// Initialize onboarding
$onboarding = new Onboarding($user_id);

$currentPage = basename($_SERVER['PHP_SELF']);
if (!$onboarding->isOnboardingCompleted() && $currentPage !== 'onboarding') {
    Redirect::to('./onboarding');
}

// Check feature access
$canAccessFeatures = $subscription->canAccessFeatures();
$limitedAccess = !$canAccessFeatures;

// Get current month and year
$current_month = (int) date('m');
$current_year  = (int) date('Y');

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to'] ?? '';
$performance_filter = $_GET['performance'] ?? '';
$team_filter   = $_GET['team'] ?? '';
$responsable_filter = $_GET['responsable'] ?? '';
$region_filter = $_GET['region'] ?? '';

// Get all responsables for filter dropdown
$responsables = $db->getThisQuery(
    "SELECT id, name FROM responsables WHERE user_id = ? AND is_active = 1 ORDER BY name",
    [$user_id]
);

// Get all teams for filter dropdown
$teams = $db->getThisQuery(
    "SELECT id, name FROM teams WHERE user_id = ? ORDER BY name",
    [$user_id]
);

// DEBUG: Check if user has agents
$agent_check = $db->getThisQuery(
    "SELECT COUNT(*) as count FROM agents WHERE user_id = ?",
    [$user_id]
);

error_log("User ID: $user_id has " . ($agent_check[0]['count'] ?? 0) . " agents");

// Check if agent_confirmations table exists
$agent_confirmations_exists = $db->query("SHOW TABLES LIKE 'agent_confirmations'")->count() > 0;
error_log("Agent confirmations table exists: " . ($agent_confirmations_exists ? 'YES' : 'NO'));

// Build WHERE conditions
$where_conditions = ["a.user_id = ?"];
$params = [$user_id];

if ($status_filter === 'active') {
    $where_conditions[] = "a.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "a.is_active = 0";
}

if ($team_filter) {
    $where_conditions[] = "a.team_id = ?";
    $params[] = $team_filter;
}

if ($responsable_filter) {
    $where_conditions[] = "a.responsable_id = ?";
    $params[] = $responsable_filter;
}

// Add region filter to WHERE conditions
if ($region_filter) {
    $where_conditions[] = "a.region_id = ?";
    $params[] = $region_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// agent data
$base_query = "
    SELECT 
        a.id,
        a.name,
        a.email,
        a.phone,
        a.is_active,
        a.created_at,
        a.service_fee,
        a.confirmation_rate,
        a.score,
        a.total_payed,
        a.total_earned,
        a.team_id,
        a.responsable_id,
        a.region_id,  -- Add this
        t.name as team_name,
        r.name as responsable_name,
        r.phone as responsable_phone,
        reg.name as region_name,  -- Add this
        reg.code as region_code,  -- Add this
        -- Placeholders for confirmation data
        0 AS total_confirmations,
        0 AS total_amount,
        NULL AS last_confirmation,
        0 AS monthly_confirmations,
        0 AS monthly_amount,
        0 AS monthly_cost,
        0 AS monthly_performance,
        0 AS roi_percentage,
        a.confirmation_rate AS performance_percentage
    FROM agents a
    LEFT JOIN teams t ON a.team_id = t.id
    LEFT JOIN responsables r ON a.responsable_id = r.id
    LEFT JOIN regions reg ON a.region_id = reg.id  -- Add this join
    {$where_clause}
    ORDER BY a.name ASC
";

// First get all agents with basic info
$agents = $db->getThisQuery($base_query, $params);

// If we have agents and the confirmations table exists, enhance the data
if ($agent_confirmations_exists && !empty($agents)) {
    // Prepare date filter for confirmations
    $date_filter_where = "";
    $date_filter_params = [];
    
    if (!empty($date_from) && !empty($date_to)) {
        $date_filter_where = "AND created_at BETWEEN ? AND ?";
        $date_filter_params = [$date_from, $date_to . ' 23:59:59'];
    } elseif (!empty($date_from)) {
        $date_filter_where = "AND created_at >= ?";
        $date_filter_params = [$date_from];
    } elseif (!empty($date_to)) {
        $date_filter_where = "AND created_at <= ?";
        $date_filter_params = [$date_to . ' 23:59:59'];
    }
    
    // Get confirmation data for all agents at once (more efficient)
    $agent_ids = array_column($agents, 'id');
    $placeholders = implode(',', array_fill(0, count($agent_ids), '?'));
    
    $confirmation_query = "
        SELECT 
            agent_id,
            COUNT(*) as total_confirmations,
            SUM(amount) as total_amount,
            MAX(created_at) as last_confirmation,
            COUNT(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 END) as monthly_confirmations,
            SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN amount ELSE 0 END) as monthly_amount
        FROM agent_confirmations 
        WHERE agent_id IN ($placeholders) 
        AND status = 'confirmed'
        {$date_filter_where}
        GROUP BY agent_id
    ";
    
    $confirmation_params = array_merge(
        [$current_month, $current_year, $current_month, $current_year],
        $agent_ids,
        $date_filter_params
    );
    
    $confirmation_data = $db->getThisQuery($confirmation_query, $confirmation_params);
    
    // Create a lookup array for confirmation data
    $confirmation_lookup = [];
    foreach ($confirmation_data as $conf) {
        $confirmation_lookup[$conf['agent_id']] = $conf;
    }
    
    // Enhance agents with confirmation data
    foreach ($agents as $agent) {
        $agent_id = $agent['id'];
        
        if (isset($confirmation_lookup[$agent_id])) {
            $conf = $confirmation_lookup[$agent_id];
            
            $agent['total_confirmations'] = (int)$conf['total_confirmations'];
            $agent['total_amount'] = (float)$conf['total_amount'];
            $agent['last_confirmation'] = $conf['last_confirmation'];
            $agent['monthly_confirmations'] = (int)$conf['monthly_confirmations'];
            $agent['monthly_amount'] = (float)$conf['monthly_amount'];
            $agent['monthly_cost'] = $agent['monthly_amount'] * ($agent['service_fee'] / 100);
            
            // Calculate monthly performance
            if ($agent['monthly_confirmations'] > 0) {
                $agent['monthly_performance'] = round(
                    ($agent['monthly_confirmations'] * 100) / 
                    ($agent['monthly_confirmations'] + ($agent['monthly_confirmations'] * 0.2)),
                    1
                );
            } else {
                $agent['monthly_performance'] = 0;
            }
            
            // Calculate ROI
            if ($agent['monthly_cost'] > 0) {
                $agent['roi_percentage'] = round(
                    (($agent['monthly_amount'] - $agent['monthly_cost']) / $agent['monthly_cost']) * 100,
                    1
                );
            } else {
                $agent['roi_percentage'] = 0;
            }
            
            // Update performance percentage if we have confirmations
            if ($agent['total_confirmations'] > 0) {
                $agent['performance_percentage'] = round(
                    ($agent['total_confirmations'] * 100) / 
                    ($agent['total_confirmations'] + ($agent['total_confirmations'] * 0.2)),
                    1
                );
            }
        }
    }
}

// Apply performance filter if specified
if (!empty($performance_filter) && !empty($agents)) {
    $filtered_agents = [];
    foreach ($agents as $agent) {
        $performance = $agent['monthly_performance'];
        
        switch ($performance_filter) {
            case 'high':
                if ($performance >= 80) $filtered_agents[] = $agent;
                break;
            case 'medium':
                if ($performance >= 50 && $performance < 80) $filtered_agents[] = $agent;
                break;
            case 'low':
                if ($performance < 50) $filtered_agents[] = $agent;
                break;
        }
    }
    $agents = $filtered_agents;
}

// Get team statistics
$team_stats_query = "
    SELECT 
        t.id,
        t.name,
        t.description,
        COUNT(DISTINCT a.id) as total_agents,
        COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN a.id END) as active_agents,
        0 as monthly_amount,
        0 as monthly_cost
    FROM teams t
    LEFT JOIN agents a ON t.id = a.team_id
    WHERE t.user_id = ?
    GROUP BY t.id, t.name, t.description  -- Ensure all non-aggregated columns are here
    ORDER BY t.name ASC
";

$team_stats = $db->getThisQuery($team_stats_query, [$user_id]);

// Enhance team stats with actual data if confirmations exist
if ($agent_confirmations_exists && !empty($team_stats)) {
    foreach ($team_stats as &$team) {
        $team_id = $team['id'];
        
        // Get team's agents
        $team_agents = $db->getThisQuery(
            "SELECT id, service_fee FROM agents WHERE team_id = ? AND user_id = ?",
            [$team_id, $user_id]
        );
        
        if (!empty($team_agents)) {
            $team_agent_ids = array_column($team_agents, 'id');
            $placeholders = implode(',', array_fill(0, count($team_agent_ids), '?'));
            
            $team_confirmation_query = "
                SELECT 
                    SUM(amount) as monthly_amount
                FROM agent_confirmations 
                WHERE agent_id IN ($placeholders) 
                AND status = 'confirmed'
                AND MONTH(created_at) = ? 
                AND YEAR(created_at) = ?
            ";
            
            $team_confirmation_params = array_merge(
                $team_agent_ids,
                [$current_month, $current_year]
            );
            
            $team_confirmation_data = $db->getThisQuery($team_confirmation_query, $team_confirmation_params);
            
            if (!empty($team_confirmation_data)) {
                $team['monthly_amount'] = (float)$team_confirmation_data[0]['monthly_amount'] ?? 0;
                
                // Calculate monthly cost based on each agent's service fee
                $team_cost = 0;
                foreach ($team_agents as $agent) {
                    $agent_confirmation_query = "
                        SELECT SUM(amount) as agent_amount
                        FROM agent_confirmations 
                        WHERE agent_id = ?
                        AND status = 'confirmed'
                        AND MONTH(created_at) = ? 
                        AND YEAR(created_at) = ?
                    ";
                    
                    $agent_confirmation_data = $db->getThisQuery($agent_confirmation_query, [
                        $agent['id'], $current_month, $current_year
                    ]);
                    
                    if (!empty($agent_confirmation_data)) {
                        $agent_amount = (float)$agent_confirmation_data[0]['agent_amount'] ?? 0;
                        $team_cost += $agent_amount * ($agent['service_fee'] / 100);
                    }
                }
                
                $team['monthly_cost'] = $team_cost;
            }
        }
    }
}

// Get responsable statistics
$responsable_stats_query = "
    SELECT 
        r.id,
        r.name,
        r.email,
        r.phone,
        COUNT(DISTINCT a.id) as agents_assigned,
        0 as monthly_confirmations,
        0 as monthly_amount
    FROM responsables r
    LEFT JOIN agents a ON r.id = a.responsable_id
    WHERE r.user_id = ?
    GROUP BY r.id, r.name, r.email, r.phone
    ORDER BY r.name ASC
";

$responsable_stats = $db->getThisQuery($responsable_stats_query, [$user_id]);

// Enhance responsable stats if confirmations exist
if ($agent_confirmations_exists && !empty($responsable_stats)) {
    foreach ($responsable_stats as &$responsable) {
        $responsable_id = $responsable['id'];
        
        // Get responsable's agents
        $responsable_agents = $db->getThisQuery(
            "SELECT id FROM agents WHERE responsable_id = ? AND user_id = ?",
            [$responsable_id, $user_id]
        );
        
        if (!empty($responsable_agents)) {
            $responsable_agent_ids = array_column($responsable_agents, 'id');
            $placeholders = implode(',', array_fill(0, count($responsable_agent_ids), '?'));
            
            $responsable_confirmation_query = "
                SELECT 
                    COUNT(*) as monthly_confirmations,
                    SUM(amount) as monthly_amount
                FROM agent_confirmations 
                WHERE agent_id IN ($placeholders) 
                AND status = 'confirmed'
                AND MONTH(created_at) = ? 
                AND YEAR(created_at) = ?
            ";
            
            $responsable_confirmation_params = array_merge(
                $responsable_agent_ids,
                [$current_month, $current_year]
            );
            
            $responsable_confirmation_data = $db->getThisQuery($responsable_confirmation_query, $responsable_confirmation_params);
            
            if (!empty($responsable_confirmation_data)) {
                $responsable['monthly_confirmations'] = (int)$responsable_confirmation_data[0]['monthly_confirmations'] ?? 0;
                $responsable['monthly_amount'] = (float)$responsable_confirmation_data[0]['monthly_amount'] ?? 0;
            }
        }
    }
}

// Get overall statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_agents,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_agents,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_agents,
        COUNT(DISTINCT team_id) as total_teams,
        COUNT(DISTINCT responsable_id) as total_responsables,
        COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN 1 END) as new_this_month
    FROM agents
    WHERE user_id = ?
";

$stats = $db->getThisQuery($stats_query, [$user_id]);

// If no stats returned, create default
if (empty($stats)) {
    $stats = [[
        'total_agents' => 0,
        'active_agents' => 0,
        'inactive_agents' => 0,
        'total_teams' => 0,
        'total_responsables' => 0,
        'new_this_month' => 0
    ]];
}

$region_stats_query = "
    SELECT 
        reg.id,
        reg.name,
        reg.code,
        COUNT(DISTINCT a.id) as total_agents,
        COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN a.id END) as active_agents,
        AVG(a.confirmation_rate) as avg_performance,
        IFNULL(SUM(ac.monthly_amount), 0) as monthly_amount
    FROM regions reg
    LEFT JOIN agents a ON reg.id = a.region_id AND a.user_id = ?
    LEFT JOIN (
        SELECT agent_id, SUM(amount) as monthly_amount
        FROM agent_confirmations
        WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?
        AND status = 'confirmed'
        GROUP BY agent_id
    ) ac ON a.id = ac.agent_id
    WHERE reg.is_active = 1  -- Removed user_id condition
    GROUP BY reg.id, reg.name, reg.code
    ORDER BY monthly_amount DESC
";

$region_stats = $db->getThisQuery($region_stats_query, [
    $user_id, $current_month, $current_year
]);

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../login.php');
}?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestion des Équipes & Agents - Plateforme OBG ECOM">
    <title>Gestion d'Équipe | OBG ECOM</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/personnel.css" />
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="../assets/css/perso.css" />
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'teams'; 
    require_once('../assets/sidebar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>🎯 Gestion d'Équipe</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openModal('agent')">
                    <i class="bi bi-person-plus"></i> Nouvel Agent
                </button>
                <button class="btn btn-success" onclick="openModal('responsable')">
                    <i class="bi bi-person-badge"></i> Nouveau Responsable
                </button>
                <button class="btn btn-warning" onclick="openModal('team')">
                    <i class="bi bi-people"></i> Nouvelle Équipe
                </button>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="bi bi-people"></i>
                </div>
                <div class="stat-value"><?php echo $stats[0]['total_agents'] ?? 0; ?></div>
                <div class="stat-label">Agents Totaux</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="bi bi-person-check"></i>
                </div>
                <div class="stat-value"><?php echo $stats[0]['total_responsables'] ?? 0; ?></div>
                <div class="stat-label">Responsables</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="bi bi-diagram-3"></i>
                </div>
                <div class="stat-value"><?php echo $stats[0]['total_teams'] ?? 0; ?></div>
                <div class="stat-label">Équipes</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon danger">
                    <i class="bi bi-graph-up"></i>
                </div>
                <div class="stat-value">
                    <?php 
                    $total_performance = 0;
                    $agent_count = 0;
                    foreach ($agents as $agent) {
                        if (isset($agent['monthly_performance'])) {
                            $total_performance += $agent['monthly_performance'];
                            $agent_count++;
                        }
                    }
                    $avg_performance = $agent_count > 0 ? round($total_performance / $agent_count, 1) : 0;
                    echo $avg_performance; ?>%
                </div>
                <div class="stat-label">Performance Moyenne</div>
            </div>

            <style>
                .stats-section {
                    background: white;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                    margin-bottom: 30px;
                }

                .regions-stats {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                    gap: 15px;
                    margin-top: 15px;
                }

                .region-card {
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 12px;
                    padding: 15px;
                    transition: transform 0.2s, box-shadow 0.2s;
                }

                .region-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }

                .region-header {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    margin-bottom: 15px;
                }

                .region-code-badge {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 5px 12px;
                    border-radius: 20px;
                    font-weight: 700;
                    font-size: 14px;
                }

                .region-stats {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                }

                .stat-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    font-size: 13px;
                    color: #475569;
                }

                .stat-item i {
                    color: #667eea;
                }
            </style>

            <div class="stats-section" style="margin-top: 0px; margin-bottom: 0px;">
                <div class="regions-stats">
                    <?php if (!empty($region_stats)): 
                        foreach ($region_stats as $region): 
                            if ($region['total_agents'] > 0): ?>
                            <div class="region-card">
                                <div class="region-header">
                                    <span class="region-code-badge"><?php echo $region['code']; ?></span>
                                    <h4><?php echo $region['name']; ?></h4>
                                </div>
                                <div class="region-stats">
                                    <div class="stat-item">
                                        <i class="bi bi-people"></i>
                                        <span><?php echo $region['total_agents']; ?> agents</span>
                                    </div>
                                    <div class="stat-item">
                                        <i class="bi bi-graph-up"></i>
                                        <span><?php echo round($region['avg_performance'], 1); ?>% perf.</span>
                                    </div>
                                    <div class="stat-item">
                                        <i class="bi bi-cash"></i>
                                        <span><?php echo number_format($region['monthly_amount'], 0, ',', ' '); ?> MAD</span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; 
                        endforeach; else: ?>
                        <div style="text-align: center; padding: 20px; color: #64748b;">
                            <i class="bi bi-geo-alt" style="font-size: 36px; opacity: 0.5; margin-bottom: 10px; color: #667eea;"></i>
                            <p>Aucune donnée de région disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            /* Teams Section */
            .teams-section {
                background: white;
                border-radius: var(--border-radius);
                box-shadow: var(--shadow-sm);
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
                border-bottom: 2px solid var(--border-color);
            }

            .section-header h3 {
                font-size: 1.3rem;
                font-weight: 600;
                color: var(--dark-color);
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            /* Table Styles */
            .table-container {
                overflow-x: auto;
            }

            .teams-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.9rem;
            }

            .teams-table thead {
                background-color: #f8fafc;
            }

            .teams-table th {
                padding: 0.75rem 1rem;
                text-align: left;
                font-weight: 600;
                color: var(--text-muted);
                border-bottom: 2px solid var(--border-color);
                white-space: nowrap;
            }

            .teams-table td {
                padding: 1rem;
                border-bottom: 1px solid var(--border-color);
                vertical-align: middle;
            }

            .team-row {
                transition: var(--transition);
            }

            .team-row:hover {
                background-color: #f8fafc;
            }

            .team-info {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .team-avatar-small {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 0.9rem;
                color: white;
                flex-shrink: 0;
            }

            .team-name-text {
                font-weight: 600;
                color: var(--dark-color);
                margin-bottom: 2px;
            }

            .team-id-badge {
                font-size: 0.75rem;
                color: var(--text-muted);
            }

            .team-description {
                color: var(--text-muted);
                max-width: 300px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .text-center {
                text-align: center !important;
            }

            .text-right {
                text-align: right !important;
            }

            .badge {
                display: inline-block;
                padding: 0.25rem 0.75rem;
                border-radius: 12px;
                font-size: 0.8rem;
                font-weight: 500;
            }

            .badge-info {
                background-color: #dbeafe;
                color: #1e40af;
            }

            /* Action Buttons */
            .action-buttons-inline {
                display: flex;
                gap: 0.5rem;
                justify-content: center;
            }

            .btn-icon {
                background: none;
                border: 1px solid var(--border-color);
                color: var(--text-color);
                width: 32px;
                height: 32px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: var(--transition);
            }

            .btn-icon:hover {
                background-color: var(--primary-light);
                color: var(--primary-color);
                border-color: var(--primary-color);
            }

            .btn-icon.btn-danger:hover {
                background-color: #fee2e2;
                color: var(--danger-color);
                border-color: var(--danger-color);
            }

            /* Totals Row */
            .totals-row {
                background-color: #f8fafc;
                font-weight: 600;
            }

            .totals-row td {
                padding: 1rem;
                border-top: 2px solid var(--border-color);
            }

            /* Empty State */
            .empty-state {
                text-align: center;
                padding: 3rem 1rem;
            }

            .empty-state h4 {
                margin: 1rem 0 0.5rem;
                color: var(--dark-color);
            }

            .empty-state p {
                color: var(--text-muted);
                margin-bottom: 1.5rem;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .teams-table {
                    font-size: 0.85rem;
                }
                
                .teams-table th,
                .teams-table td {
                    padding: 0.5rem;
                }
                
                .team-description {
                    max-width: 150px;
                }
                
                .section-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 1rem;
                }
            }
        </style>
        
        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Teams Overview -->
            <div class="teams-section">
                <div class="section-header">
                    <h3>🏆 Équipes & Performance</h3>
                    <button class="btn-icon" onclick="openModal('team')">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                
                <!-- Teams Section -->
                <div class="teams-section">
                    <div class="section-header">
                        <h3>
                            <i class="bi bi-people"></i>
                            Équipes & Performance
                        </h3>
                        <button class="btn btn-primary" onclick="openModal('team')">
                            <i class="bi bi-plus-circle"></i>
                            Nouvelle Équipe
                        </button>
                    </div>

                    <?php if (!empty($team_stats)): ?>
                        <div class="table-container">
                            <table class="teams-table">
                                <thead>
                                    <tr>
                                        <th>Équipe</th>
                                        <th>Description</th>
                                        <th class="text-center">Agents</th>
                                        <th class="text-right">Chiffre</th>
                                        <th class="text-right">Coût</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($team_stats as $equipe): ?>

                                        <tr class="team-row" data-team-id="<?php echo $equipe['id']; ?>">
                                            <td>
                                                <div class="team-info">
                                                    <div class="team-avatar-small" style="background: #<?php echo str_pad(dechex(abs(crc32($equipe['name'])) % 0xFFFFFF), 6, '0', STR_PAD_LEFT); ?>">
                                                        <?php echo strtoupper(substr($equipe['name'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="team-name-text"><?php echo htmlspecialchars($equipe['name']); ?></div>
                                                        <div class="team-id-badge">ID: <?php echo $equipe['id']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="team-description">
                                                <?php echo htmlspecialchars($equipe['description'] ?? 'Sans description'); ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-info">
                                                    <?php echo $equipe['total_agents']; ?> 
                                                    <?php echo $equipe['total_agents'] > 1 ? 'agents' : 'agent'; ?>
                                                </span>
                                            </td>
                                            <td class="text-right">
                                                <strong><?php echo number_format($equipe['monthly_amount'], 0, ',', ' '); ?> MAD</strong>
                                            </td>
                                            <td class="text-right">
                                                <strong><?php echo number_format($equipe['monthly_cost'], 0, ',', ' '); ?> MAD</strong>
                                            </td>
                                            <td class="text-center">
                                                <div class="action-buttons-inline">
                                                    <button class="btn-icon" onclick="viewTeam(<?php echo $equipe['id']; ?>)" title="Voir détails">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn-icon btn-danger" onclick="deleteTeam(<?php echo $equipe['id']; ?>)" title="Supprimer">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="totals-row">
                                        <td colspan="2"><strong>TOTAL</strong></td>
                                        <td class="text-center">
                                            <strong><?php echo array_sum(array_column($team_stats, 'total_agents')); ?> agents</strong>
                                        </td>
                                        <td class="text-right">
                                            <strong><?php echo number_format(array_sum(array_column($team_stats, 'monthly_amount')), 0, ',', ' '); ?> MAD</strong>
                                        </td>
                                        <td class="text-right">
                                            <strong><?php echo number_format(array_sum(array_column($team_stats, 'monthly_cost')), 0, ',', ' '); ?> MAD</strong>
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-people" style="font-size: 64px; color: #cbd5e1;"></i>
                            <h4>Aucune équipe créée</h4>
                            <p>Commencez par créer votre première équipe</p>
                            <button class="btn btn-primary" onclick="openModal('team')">
                                <i class="bi bi-plus-circle"></i>
                                Créer une équipe
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Responsables Sidebar -->
            <div class="responsables-sidebar">
                <div class="section-header">
                    <h3>👨‍💼 Responsables</h3>
                    <button class="btn-icon" onclick="openModal('responsable')">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                
                <?php if (!empty($responsable_stats)): foreach ($responsable_stats as $responsable): ?>
                    <div class="responsable-card" onclick="viewResponsable(<?php echo $responsable['id']; ?>)">
                        <div class="responsable-avatar">
                            <?php echo substr($responsable['name'], 0, 2); ?>
                        </div>
                        <div class="responsable-info">
                            <h5><?php echo htmlspecialchars($responsable['name']); ?></h5>
                            <p><?php echo $responsable['agents_assigned']; ?> agents • <?php echo $responsable['monthly_confirmations']; ?> confirmations</p>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div style="text-align: center; padding: 20px; color: #64748b;">
                        <i class="bi bi-person-badge" style="font-size: 36px; opacity: 0.5; margin-bottom: 10px;"></i>
                        <p>Aucun responsable</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label for="status-filter">Statut</label>
                    <select id="status-filter" name="status" class="filter-control">
                        <option value="">Tous les statuts</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="team-filter">Équipe</label>
                    <select id="team-filter" name="team" class="filter-control">
                        <option value="">Toutes les équipes</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>" <?php echo $team_filter == $team['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="responsable-filter">Responsable</label>
                    <select id="responsable-filter" name="responsable" class="filter-control">
                        <option value="">Tous les responsables</option>
                        <?php foreach ($responsables as $responsable): ?>
                            <option value="<?php echo $responsable['id']; ?>" <?php echo $responsable_filter == $responsable['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($responsable['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="performance-filter">Performance</label>
                    <select id="performance-filter" name="performance" class="filter-control">
                        <option value="">Toutes</option>
                        <option value="high" <?php echo $performance_filter === 'high' ? 'selected' : ''; ?>>Haute (>80%)</option>
                        <option value="medium" <?php echo $performance_filter === 'medium' ? 'selected' : ''; ?>>Moyenne (50-80%)</option>
                        <option value="low" <?php echo $performance_filter === 'low' ? 'selected' : ''; ?>>Basse (<50%)</option>
                    </select>
                </div>

                <!-- regions -->
                <div class="filter-group">
                    <label for="region-filter">Région</label>
                    <select id="region-filter" name="region" class="filter-control">
                        <option value="">Toutes les régions</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo $region['id']; ?>" 
                                <?php echo (isset($_GET['region']) && $_GET['region'] == $region['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($region['name'] . ' (' . $region['code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Date de début</label>
                    <input type="date" name="date_from" class="filter-control" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label>Date de fin</label>
                    <input type="date" name="date_to" class="filter-control" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary" style="height: 46px;">
                        <i class="bi bi-funnel"></i> Appliquer
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Agents Table -->
        <div class="data-table">
            <div class="table-header">
                <h3>👥 Liste des Agents</h3>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-icon" onclick="exportData()" title="Exporter">
                        <i class="bi bi-download"></i>
                    </button>
                    <button class="btn-icon" onclick="refreshData()" title="Actualiser">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            <style>
                .region-badge {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                }

                .region-code {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 4px 10px;
                    border-radius: 16px;
                    font-size: 12px;
                    font-weight: 700;
                    letter-spacing: 0.5px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }

                .region-name {
                    font-size: 13px;
                    color: #475569;
                    margin-top: 4px;
                    font-weight: 500;
                }
            </style>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                        <th>Agent</th>
                        <th>Contact</th>
                        <th>Région</th>
                        <th>Équipe & Responsable</th>
                        <th>Score</th>
                        <th>Performance</th>
                        <th>Confirmations</th>
                        <th>Coût</th>
                        <th>ROI</th>
                        <th>Statut</th>
                        <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="agents-table-body">
                        <?php if (!empty($agents)): 

                        foreach ($agents as $agent): 
                            
                            $performance_class = 'performance-';
                            if ($agent['monthly_performance'] >= 80) $performance_class .= 'high';
                            elseif ($agent['monthly_performance'] >= 50) $performance_class .= 'medium';
                            else $performance_class .= 'low';
                            
                            $score_class = 'score-';
                            $score = $agent['score'] ?? 0;
                            if ($score >= 80) $score_class .= 'excellent';
                            elseif ($score >= 50) $score_class .= 'good';
                            else $score_class .= 'average';
                            
                            // Calculate cost per confirmation for display
                            $cost_per_confirmation = $agent['monthly_confirmations'] > 0 ? 
                                round($agent['monthly_cost'] / $agent['monthly_confirmations'], 2) : 0;
                        ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($agent['name']); ?></div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php echo date('d/m/Y', strtotime($agent['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($agent['email']); ?></div>
                                <div style="font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></div>
                            </td>

                            <td>
                                <div class="region-badge">
                                    <span class="region-code" 
                                        style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; 
                                                border-radius: 12px; font-size: 11px; font-weight: 600;">
                                        <?php echo htmlspecialchars($agent['region_code'] ?? 'N/A'); ?>
                                    </span>
                                    <div style="font-size: 13px; margin-top: 4px;">
                                        <?php echo htmlspecialchars($agent['region_name'] ?? 'Non assigné'); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500; color: #667eea;"><?php echo htmlspecialchars($agent['team_name'] ?? 'Non assigné'); ?></div>
                                <div style="font-size: 12px; color: #64748b;">
                                    <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($agent['responsable_name'] ?? 'Non assigné'); ?>
                                </div>
                            </td>
                            <td>
                                <div class="score-badge <?php echo $score_class; ?>">
                                    <?php echo $score; ?>
                                </div>
                            </td>
                            <td>
                                <span class="performance-indicator <?php echo $performance_class; ?>">
                                    <?php echo $agent['monthly_performance']; ?>%
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo $agent['monthly_confirmations']; ?></div>
                                <div style="font-size: 12px; color: #64748b;"><?php echo number_format($agent['monthly_amount'], 0, ',', ' '); ?> MAD</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #f59e0b;"><?php echo number_format($agent['monthly_cost'], 0, ',', ' '); ?> MAD</div>
                                <div style="font-size: 12px; color: #64748b;">
                                    <?php echo number_format($cost_per_confirmation, 2, ',', ' '); ?> MAD/conf
                                    <br>
                                    <small><?php echo $agent['service_fee'] ?? '0'; ?>% taux</small>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: <?php echo $agent['roi_percentage'] >= 0 ? '#10b981' : '#ef4444'; ?>;">
                                    <?php echo $agent['roi_percentage']; ?>%
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $agent['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $agent['is_active'] ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon" onclick="viewAgent(<?php echo $agent['id']; ?>)" title="Voir détails">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn-icon" onclick="editAgent(<?php echo $agent['id']; ?>)" title="Modifier">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-icon" onclick="deleteAgent(<?php echo $agent['id']; ?>)" title="Supprimer" style="color: #ef4444;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px;">
                                <i class="bi bi-people" style="font-size: 48px; color: #cbd5e1; margin-bottom: 15px;"></i>
                                <p style="color: #64748b;">Aucun agent trouvé</p>
                                <button class="btn btn-primary btn-sm" onclick="openModal('agent')">
                                    Ajouter un agent
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Agent Modal -->
    <div class="modal-overlay" id="agent-modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 id="agent-modal-title">Ajouter un Nouvel Agent</h3>
                <button class="modal-close" onclick="closeModal('agent')">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="agent-form">
                    <input type="hidden" id="agent-id" name="id">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="agent-name">Nom complet *</label>
                            <input type="text" id="agent-name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="agent-email">Email *</label>
                            <input type="email" id="agent-email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="agent-phone">Téléphone</label>
                            <input type="tel" id="agent-phone" name="phone" class="form-control">
                        </div>
                        
                        <div class="form-group" id="password-field">
                            <label class="form-label" for="agent-password">Mot de passe *</label>
                            <input type="password" id="agent-password" name="password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="agent-service_fee">Taux de service (%)</label>
                            <input type="number" id="agent-service_fee" name="service_fee" class="form-control" 
                                min="0" max="100" step="0.1" value="5">
                            <small style="color: #64748b; font-size: 12px;">Pourcentage appliqué sur chaque confirmation</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="agent-score">Score initial</label>
                            <input type="number" id="agent-score" name="score" class="form-control" 
                                min="0" max="100" step="1" value="50">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="agent-team">Équipe</label>
                            <select id="agent-team" name="team_id" class="form-control select2">
                                <option value="">Non assigné</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>"><?php echo htmlspecialchars($team['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="agent-responsable">Responsable</label>
                            <select id="agent-responsable" name="responsable_id" class="form-control select2">
                                <option value="">Non assigné</option>
                                <?php foreach ($responsables as $responsable): ?>
                                    <option value="<?php echo $responsable['id']; ?>"><?php echo htmlspecialchars($responsable['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="agent-region">Région *</label>
                        <select id="agent-region" name="region_id" class="form-control select2" required>
                            <option value="">Sélectionner une région</option>
                            <?php foreach ($regions as $region): ?>
                                <option value="<?php echo $region['id']; ?>">
                                    <?php echo htmlspecialchars($region['name'] . ' (' . $region['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="toggle-wrapper">
                        <div class="toggle">
                            <input type="checkbox" id="agent-status-toggle" name="is_active" value="1" checked>
                            <span class="toggle-slider"></span>
                        </div>
                        <label class="toggle-label" for="agent-status-toggle">Agent Actif</label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('agent')">Fermer</button>
                        <button type="submit" class="btn btn-primary" id="agent-submit-btn">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Responsable Modal -->
    <div class="modal-overlay" id="responsable-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter un Responsable</h3>
                <button class="modal-close" onclick="closeModal('responsable')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="responsable-form">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="responsable-name">Nom complet *</label>
                            <input type="text" id="responsable-name" name="name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="responsable-email">Email *</label>
                            <input type="email" id="responsable-email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="responsable-phone">Téléphone</label>
                            <input type="tel" id="responsable-phone" name="phone" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="responsable-password">Mot de passe *</label>
                            <input type="password" id="responsable-password" name="password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('responsable')">Annuler</button>
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Team Modal -->
    <div class="modal-overlay" id="team-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Créer une Équipe</h3>
                <button class="modal-close" onclick="closeModal('team')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="team-form">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="team-name">Nom de l'équipe *</label>
                        <input type="text" id="team-name" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="team-description">Description</label>
                        <textarea id="team-description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="team-responsable">Responsable principal</label>
                        <select id="team-responsable" name="responsable_id" class="form-control select2">
                            <option value="">Aucun</option>
                            <?php foreach ($responsables as $responsable): ?>
                                <option value="<?php echo $responsable['id']; ?>"><?php echo htmlspecialchars($responsable['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('team')">Annuler</button>
                        <button type="submit" class="btn btn-warning">Créer l'équipe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Agent Modal -->
    <div class="modal-overlay" id="view-agent-modal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>Détails de l'Agent</h3>
                <button class="modal-close" onclick="closeModal('view-agent')">&times;</button>
            </div>
            <div class="modal-body" id="agent-details">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize Select2 on page load
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap',
                width: '100%'
            });
        });
        
        // Modal functions
        function openModal(type) {
            const modal = document.getElementById(`${type}-modal`);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Initialize Select2 for modal
                setTimeout(() => {
                    $(`#${type}-modal .select2`).select2({
                        theme: 'bootstrap',
                        dropdownParent: $(`#${type}-modal`),
                        width: '100%'
                    });
                }, 100);
                
                // Reset form if opening new agent modal
                if (type === 'agent') {
                    document.getElementById('agent-form').reset();
                    document.getElementById('agent-id').value = '';
                    document.getElementById('agent-modal-title').textContent = 'Ajouter un Nouvel Agent';
                    const passwordField = document.getElementById('password-field');
                    if (passwordField) {
                        passwordField.innerHTML = `
                            <label class="form-label" for="agent-password">Mot de passe *</label>
                            <input type="password" id="agent-password" name="password" class="form-control" required minlength="6">
                        `;
                    }
                    if (document.getElementById('agent-status-toggle')) {
                        document.getElementById('agent-status-toggle').checked = true;
                    }
                }
            }
        }
        
        function closeModal(type) {
            const modal = document.getElementById(`${type}-modal`);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                const form = document.getElementById(`${type}-form`);
                if (form) {
                    form.reset();
                }
            }
        }
        
        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        });
        
        // Agent form submission
        document.getElementById('agent-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(this);
            const agentId = document.getElementById('agent-id').value;
            
            const url = agentId ? './controllers/update_agentApi.php' : './controllers/add_agentApi.php';
            
            const submitBtn = document.getElementById('agent-submit-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Enregistrement...';
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {

                    Swal.fire({
                        icon: 'success',
                        title: 'Succès!',
                        text: agentId ? 'Agent mis à jour avec succès' : 'Agent ajouté avec succès',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    throw new Error(data.message || data.error || 'Erreur lors de l\'enregistrement');
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
        
        // Responsable form submission
        document.getElementById('responsable-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Enregistrement...';
            
            try {
                const response = await fetch('./controllers/add_responsableApi.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès!',
                        text: 'Responsable ajouté avec succès',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    throw new Error(data.message || data.error);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
        
        // Team form submission
        document.getElementById('team-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Création...';
            
            try {
                const response = await fetch('./controllers/add_teamApi.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Succès!',
                        text: 'Équipe créée avec succès',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    throw new Error(data.message || data.error);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
        
        // Edit agent
        async function editAgent(id) {
            try {
                const response = await fetch(`./controllers/get_agentApi.php?id=${id}`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success && data.agent) {
                    const agent = data.agent;
                    
                    // Open modal first
                    openModal('agent');
                    
                    // Wait for modal to be fully rendered
                    setTimeout(() => {
                        // Populate form fields AFTER modal is opened
                        document.getElementById('agent-id').value = agent.id || '';
                        document.getElementById('agent-name').value = agent.name || '';
                        document.getElementById('agent-email').value = agent.email || '';
                        document.getElementById('agent-phone').value = agent.phone || '';
                        document.getElementById('agent-service_fee').value = agent.service_fee || '5';
                        document.getElementById('agent-score').value = agent.score || '50';

                        const regionSelect = $('#agent-region');
                        if (regionSelect.length && agent.region) {
                            regionSelect.val(agent.region).trigger('change');
                        } else if (regionSelect.length) {
                            regionSelect.val('').trigger('change');
                        }
                        
                        // Set status toggle
                        const statusToggle = document.getElementById('agent-status-toggle');
                        if (statusToggle) {
                            statusToggle.checked = agent.is_active == 1 || agent.is_active === true;
                        }
                        
                        // Update modal title
                        document.getElementById('agent-modal-title').textContent = 'Modifier l\'Agent';
                        
                        // Update password field for editing
                        const passwordField = document.getElementById('password-field');
                        if (passwordField) {
                            passwordField.innerHTML = `
                                <label class="form-label">Mot de passe</label>
                                <input type="password" name="password" class="form-control" 
                                    placeholder="Laisser vide pour ne pas modifier">
                                <small class="text-muted">Laisser vide pour conserver le mot de passe actuel</small>
                            `;
                        }
                        
                        // Set team select
                        const teamSelect = $('#agent-team');
                        if (teamSelect.length && agent.team_id) {
                            teamSelect.val(agent.team_id).trigger('change');
                        } else if (teamSelect.length) {
                            teamSelect.val('').trigger('change');
                        }
                        
                        // Set responsable select
                        const responsableSelect = $('#agent-responsable');
                        if (responsableSelect.length && agent.responsable_id) {
                            responsableSelect.val(agent.responsable_id).trigger('change');
                        } else if (responsableSelect.length) {
                            responsableSelect.val('').trigger('change');
                        }
                        
                        // Reinitialize Select2 ONLY if needed
                        if (!$('.select2-container').length) {
                            $('.select2').select2({
                                theme: 'bootstrap',
                                dropdownParent: $('#agent-modal')
                            });
                        }
                        
                    }, 300);
                    
                } else {
                    throw new Error(data.message || data.error || 'Erreur lors du chargement des données');
                }
            } catch (error) {
                console.error('Error in editAgent:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message
                });
            }
        }
        
        // View agent details
        async function viewAgent(id) {
            try {
                const response = await fetch(`./controllers/get_agent_detailsApi.php?id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    const agent = data.agent;
                    const stats = agent.statistics || {};
                    
                    let html = `
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                            <!-- Agent Info -->
                            <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                                    <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                        border-radius: 12px; display: flex; align-items: center; 
                                        justify-content: center; color: white; font-weight: 700; font-size: 24px;">
                                        ${agent.name.substring(0, 2).toUpperCase()}
                                    </div>
                                    <div>
                                        <h4 style="margin: 0 0 5px 0;">${agent.name}</h4>
                                        <p style="margin: 0; color: #64748b; font-size: 14px;">
                                            Agent depuis ${new Date(agent.created_at).toLocaleDateString('fr-FR')}
                                        </p>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                                    <div>
                                        <label style="display: block; font-size: 12px; color: #64748b;">Email</label>
                                        <strong>${agent.email}</strong>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 12px; color: #64748b;">Téléphone</label>
                                        <strong>${agent.phone || 'N/A'}</strong>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 12px; color: #64748b;">Taux de service</label>
                                        <strong>${agent.service_fee || 0}%</strong>
                                    </div>
                                    <div>
                                        <label style="display: block; font-size: 12px; color: #64748b;">Score</label>
                                        <div class="score-badge ${(agent.score || 0) >= 80 ? 'score-excellent' : (agent.score || 0) >= 50 ? 'score-good' : 'score-average'}" 
                                            style="display: inline-flex; width: 40px; height: 40px; align-items: center; justify-content: center;">
                                            ${agent.score || 0}
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                                    <h5 style="margin-bottom: 15px; font-size: 16px;">Assignation</h5>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div>
                                            <label style="display: block; font-size: 12px; color: #64748b;">Équipe</label>
                                            <strong style="color: #667eea;">${agent.team?.name || 'Non assigné'}</strong>
                                        </div>
                                        <div>
                                            <label style="display: block; font-size: 12px; color: #64748b;">Responsable</label>
                                            <strong>${agent.responsable?.name || 'Non assigné'}</strong>
                                            ${agent.responsable?.phone ? `<small style="display: block; color: #64748b;">${agent.responsable.phone}</small>` : ''}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Performance Stats -->
                            <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                                <h5 style="margin-bottom: 20px; font-size: 16px;">Statistiques de Performance</h5>
                                
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 25px;">
                                    <div style="text-align: center;">
                                        <div style="font-size: 28px; font-weight: 700; color: #667eea;">
                                            ${stats.monthly_performance || 0}%
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">Performance ce mois</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 28px; font-weight: 700; color: #10b981;">
                                            ${stats.monthly_orders || 0}
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">Commandes ce mois</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 28px; font-weight: 700; color: #f59e0b;">
                                            ${parseFloat(stats.monthly_amount || 0).toLocaleString('fr-FR', {style: 'currency', currency: 'MAD'})}
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">Montant confirmé</div>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="font-size: 28px; font-weight: 700; color: ${(stats.monthly_roi || 0) >= 0 ? '#10b981' : '#ef4444'};">
                                            ${stats.monthly_roi || 0}%
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">ROI mensuel</div>
                                    </div>
                                </div>
                                
                                <div style="padding: 15px; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
                                    <h6 style="margin-bottom: 10px; font-size: 14px;">Statistiques globales:</h6>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                                        <div>
                                            <span style="color: #64748b;">Taux de confirmation:</span>
                                            <strong style="color: #10b981; display: block;">
                                                ${stats.confirmation_rate || 0}%
                                            </strong>
                                        </div>
                                        <div>
                                            <span style="color: #64748b;">Commandes confirmées:</span>
                                            <strong style="color: #10b981; display: block;">
                                                ${stats.confirmed_orders || 0} / ${stats.total_orders || 0}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="padding: 15px; background: white; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 15px;">
                                    <h6 style="margin-bottom: 10px; font-size: 14px;">Statistiques financières:</h6>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                                        <div>
                                            <span style="color: #64748b;">Total gagné:</span>
                                            <strong style="color: #10b981; display: block;">
                                                ${parseFloat(agent.total_earned || 0).toLocaleString('fr-FR', {style: 'currency', currency: 'MAD'})}
                                            </strong>
                                        </div>
                                        <div>
                                            <span style="color: #64748b;">Total payé:</span>
                                            <strong style="color: #f59e0b; display: block;">
                                                ${parseFloat(agent.total_payed || 0).toLocaleString('fr-FR', {style: 'currency', currency: 'MAD'})}
                                            </strong>
                                        </div>
                                        <div>
                                            <span style="color: #64748b;">Coût mensuel:</span>
                                            <strong style="color: #f59e0b; display: block;">
                                                ${parseFloat(stats.monthly_cost || 0).toLocaleString('fr-FR', {style: 'currency', currency: 'MAD'})}
                                            </strong>
                                        </div>
                                        <div>
                                            <span style="color: #64748b;">Montant mensuel:</span>
                                            <strong style="color: #10b981; display: block;">
                                                ${parseFloat(stats.monthly_amount || 0).toLocaleString('fr-FR', {style: 'currency', currency: 'MAD'})}
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('agent-details').innerHTML = html;
                    openModal('view-agent');
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message
                });
            }
        }
        
        // Delete agent
        async function deleteAgent(id) {
            const result = await Swal.fire({
                title: 'Êtes-vous sûr?',
                text: "Cette action est irréversible!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Oui, supprimer!',
                cancelButtonText: 'Annuler'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('./controllers/delete_agentApi.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({id: id})
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Supprimé!',
                            text: 'Agent supprimé avec succès',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        throw new Error(data.message || data.error);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: error.message
                    });
                }
            }
        }
        
        // view team
        async function viewTeam(id) {
            try {
                const response = await fetch(`./controllers/get_team_detailsApi.php?team_id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    const team = data.data;
                    const agents = team.agents || [];
                    const stats = team.statistics || {};
                    
                    const activeAgents = agents.filter(agent => agent.is_active).length;
                    const avgConfirmation = stats.avg_confirmation_rate || 
                                        agents.reduce((sum, agent) => sum + (parseFloat(agent.confirmation_rate) || 0), 0) / (agents.length || 1);
                    const avgScore = stats.avg_score || 
                                agents.reduce((sum, agent) => sum + (parseFloat(agent.score) || 0), 0) / (agents.length || 1);
                    
                    Swal.fire({
                        title: `<div style="font-size: 22px; font-weight: 600; color: #2d3748;">
                                <i class="fas fa-users me-2" style="color: #667eea;"></i>
                                ${team.team_name || team.name}
                                </div>`,
                        html: `
                            <style>
                                .team-modal-content {
                                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                                    max-width: 600px;
                                }
                                .compact-card {
                                    background: #f8fafc;
                                    border-radius: 10px;
                                    padding: 20px;
                                    margin-bottom: 15px;
                                    border: 1px solid #e2e8f0;
                                }
                                .compact-grid {
                                    display: grid;
                                    grid-template-columns: repeat(2, 1fr);
                                    gap: 15px;
                                    margin-bottom: 20px;
                                }
                                .compact-stat {
                                    background: white;
                                    border-radius: 8px;
                                    padding: 15px;
                                    text-align: center;
                                    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                                    border: 1px solid #e2e8f0;
                                }
                                .stat-value {
                                    font-size: 18px;
                                    font-weight: 700;
                                    color: #2d3748;
                                    margin-bottom: 3px;
                                }
                                .stat-label {
                                    font-size: 12px;
                                    color: #718096;
                                    text-transform: uppercase;
                                    letter-spacing: 0.5px;
                                }
                                .compact-section {
                                    margin-top: 20px;
                                }
                                .section-title {
                                    font-size: 14px;
                                    font-weight: 600;
                                    color: #4a5568;
                                    margin-bottom: 10px;
                                    display: flex;
                                    align-items: center;
                                    gap: 8px;
                                }
                                .agents-list {
                                    max-height: 200px;
                                    overflow-y: auto;
                                    padding-right: 5px;
                                }
                                .agent-item {
                                    padding: 10px 12px;
                                    background: white;
                                    border-radius: 6px;
                                    margin-bottom: 8px;
                                    border-left: 3px solid #667eea;
                                    font-size: 13px;
                                }
                                .agent-header {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    margin-bottom: 4px;
                                }
                                .agent-name {
                                    font-weight: 600;
                                    color: #2d3748;
                                }
                                .agent-confirm {
                                    font-size: 12px;
                                    font-weight: 600;
                                    padding: 2px 8px;
                                    border-radius: 10px;
                                    background: ${getPerformanceColor(avgConfirmation)};
                                }
                                .agent-details {
                                    display: flex;
                                    gap: 10px;
                                    font-size: 11px;
                                    color: #718096;
                                }
                                .empty-agents {
                                    text-align: center;
                                    padding: 30px;
                                    color: #a0aec0;
                                }
                                .empty-agents i {
                                    font-size: 32px;
                                    margin-bottom: 10px;
                                    opacity: 0.5;
                                }
                                .financial-row {
                                    display: grid;
                                    grid-template-columns: repeat(3, 1fr);
                                    gap: 10px;
                                    margin-top: 10px;
                                }
                                .financial-item {
                                    background: white;
                                    border-radius: 6px;
                                    padding: 12px;
                                    text-align: center;
                                    border: 1px solid #e2e8f0;
                                }
                                .financial-value {
                                    font-size: 16px;
                                    font-weight: 700;
                                    color: #2d3748;
                                }
                                .financial-label {
                                    font-size: 11px;
                                    color: #718096;
                                    margin-top: 2px;
                                }
                                .team-meta {
                                    font-size: 12px;
                                    color: #718096;
                                    display: flex;
                                    justify-content: space-between;
                                    margin-top: 15px;
                                    padding-top: 15px;
                                    border-top: 1px solid #e2e8f0;
                                }
                            </style>
                            
                            <div class="team-modal-content">
                                <!-- Team Info -->
                                <div class="compact-card">
                                    <div style="margin-bottom: 10px; color: #4a5568; font-size: 14px;">
                                        ${team.description || 'Aucune description'}
                                    </div>
                                    <div style="display: flex; gap: 20px; font-size: 13px;">
                                        <div>
                                            <div style="color: #718096;">Responsable</div>
                                            <div style="font-weight: 500;">${team.responsable_name || 'Non assigné'}</div>
                                        </div>
                                        <div>
                                            <div style="color: #718096;">Créé par</div>
                                            <div style="font-weight: 500;">${team.created_by_name || 'Système'}</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stats Grid -->
                                <div class="compact-grid">
                                    <div class="compact-stat">
                                        <div class="stat-value">${agents.length}</div>
                                        <div class="stat-label">Agents Total</div>
                                    </div>
                                    <div class="compact-stat">
                                        <div class="stat-value">${activeAgents}</div>
                                        <div class="stat-label">Agents Actifs</div>
                                    </div>
                                    <div class="compact-stat">
                                        <div class="stat-value">${avgConfirmation.toFixed(0)}%</div>
                                        <div class="stat-label">Confirmation</div>
                                    </div>
                                    <div class="compact-stat">
                                        <div class="stat-value">${avgScore.toFixed(1)}</div>
                                        <div class="stat-label">Score Moyen</div>
                                    </div>
                                </div>

                                <!-- Financial -->
                                <div class="compact-section">
                                    <div class="section-title">
                                        <i class="fas fa-chart-line"></i>
                                        Statistiques Financières
                                    </div>
                                    <div class="financial-row">
                                        <div class="financial-item">
                                            <div class="financial-value">${formatCurrency(stats.total_earned || 0)}</div>
                                            <div class="financial-label">Total Gains</div>
                                        </div>
                                        <div class="financial-item">
                                            <div class="financial-value">${formatCurrency(stats.total_payed || 0)}</div>
                                            <div class="financial-label">Total Paiements</div>
                                        </div>
                                        <div class="financial-item">
                                            <div class="financial-value">${formatCurrency((stats.total_earned || 0) - (stats.total_payed || 0))}</div>
                                            <div class="financial-label">Gains Nets</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Agents -->
                                <div class="compact-section">
                                    <div class="section-title">
                                        <i class="fas fa-user-friends"></i>
                                        Agents (${agents.length})
                                    </div>
                                    <div class="agents-list">
                                        ${agents.length > 0 ? 
                                            agents.map(agent => {
                                                const confirmRate = parseFloat(agent.confirmation_rate) || 0;
                                                return `
                                                <div class="agent-item">
                                                    <div class="agent-header">
                                                        <div class="agent-name">${agent.name}</div>
                                                        <div class="agent-confirm" style="background: ${getPerformanceColor(confirmRate)}">
                                                            ${confirmRate.toFixed(0)}%
                                                        </div>
                                                    </div>
                                                    <div class="agent-details">
                                                        ${agent.email ? `<span>${agent.email}</span>` : ''}
                                                        <span>Score: ${agent.score || 0}</span>
                                                        ${agent.service_fee ? `<span>Fee: ${parseFloat(agent.service_fee).toFixed(1)}%</span>` : ''}
                                                    </div>
                                                </div>
                                            `}).join('') : 
                                            `<div class="empty-agents">
                                                <i class="fas fa-users-slash"></i>
                                                <div>Aucun agent dans cette équipe</div>
                                            </div>`
                                        }
                                    </div>
                                </div>

                                <!-- Metadata -->
                                <div class="team-meta">
                                    <div>
                                        <i class="fas fa-calendar me-1"></i>
                                        Créé le ${new Date(team.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })}
                                    </div>
                                    <div>
                                        NUMBER ${team.id}
                                    </div>
                                </div>
                            </div>
                        `,
                        width: '550px',
                        showConfirmButton: false,
                        showCloseButton: true,
                        customClass: {
                            popup: 'compact-team-modal'
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: data.message || 'Impossible de charger les détails',
                        width: '400px'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue: ' + error.message,
                    width: '400px'
                });
            }
        }

        // Helper function for performance color
        function getPerformanceColor(rate) {
            const r = parseFloat(rate) || 0;
            if (r >= 80) return '#d1fae5';
            if (r >= 60) return '#fef3c7';
            return '#fee2e2';
        }

        async function deleteTeam(teamId, teamName, callback) {
            try {
                const result = await Swal.fire({
                    title: 'Confirmer la suppression',
                    html: `
                        <div style="text-align: left; padding: 10px 0;">
                            <p style="color: #dc2626; font-weight: 500; margin-bottom: 10px;">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Cette action est irréversible !
                            </p>
                            <p>Êtes-vous sûr de vouloir supprimer l'équipe <strong>"${teamName}"</strong> ?</p>
                            <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 10px; margin-top: 15px; border-radius: 0 4px 4px 0;">
                                <p style="margin: 0; color: #7f1d1d; font-size: 13px;">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Cette action supprimera également toutes les données associées.
                                </p>
                            </div>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Oui, supprimer',
                    cancelButtonText: 'Annuler',
                    reverseButtons: true,
                    width: '500px',
                    customClass: {
                        confirmButton: 'delete-confirm-btn',
                        cancelButton: 'delete-cancel-btn'
                    }
                });

                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Suppression en cours...',
                        text: 'Veuillez patienter',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Make delete request
                    const response = await fetch('./controllers/delete_teamApi.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            team_id: teamId
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Supprimé !',
                            text: `L'équipe "${teamName}" a été supprimée avec succès.`,
                            timer: 2000,
                            showConfirmButton: false,
                            timerProgressBar: true
                        }).then(() => {
                            // Refresh the page or update UI
                            if (callback && typeof callback === 'function') {
                                callback();
                            } else {
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: data.message || 'Échec de la suppression',
                            confirmButtonColor: '#dc2626'
                        });
                    }
                }
            } catch (error) {
                console.error('Delete team error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur est survenue lors de la suppression: ' + error.message,
                    confirmButtonColor: '#dc2626'
                });
            }
        }

        // Helper function for currency formatting
        function formatCurrency(amount) {
            if (!amount) return '0 MAD';
            return new Intl.NumberFormat('fr-MA', {
                style: 'currency',
                currency: 'MAD',
                minimumFractionDigits: 0
            }).format(amount);
        }
        
        // View responsable
        async function viewResponsable(id) {
            try {
                const url = `./controllers/get_responsable_detailsApi.php?id=${id}&include_agents=true&include_stats=true`;
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success && data.data) {
                    const responsable = data.data;
                    const agents = responsable.agents || [];
                    const stats = responsable.statistics || {};
                    const teams = responsable.teams_managed || [];
                    
                    const formatDate = (dateString) => {
                        if (!dateString) return 'N/A';
                        const date = new Date(dateString);
                        return date.toLocaleDateString('fr-FR', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric'
                        });
                    };
                    
                    const formatCurrency = (amount) => {
                        if (!amount) return '0';
                        return new Intl.NumberFormat('fr-MA', {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        }).format(amount) + 'dh';
                    };
                    
                    const getStatusDot = (isActive) => {
                        return isActive ? 
                            '<span style="display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%; margin-right: 5px;"></span>' :
                            '<span style="display: inline-block; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; margin-right: 5px;"></span>';
                    };
                    
                    const getPerformanceBar = (score) => {
                        const value = parseFloat(score) || 0;
                        let color = '#ef4444';
                        if (value >= 80) color = '#10b981';
                        else if (value >= 60) color = '#f59e0b';
                        else if (value >= 40) color = '#3b82f6';
                        
                        return `
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div style="flex: 1; height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden;">
                                    <div style="width: ${value}%; height: 100%; background: ${color}; transition: width 0.3s;"></div>
                                </div>
                                <span style="font-size: 11px; font-weight: 600; color: ${color}; min-width: 35px;">${value}%</span>
                            </div>
                        `;
                    };
                    
                    Swal.fire({
                        title: '',
                        html: `
                            <div style="font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
                                
                                <!-- Header Card with Gradient -->
                                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                    padding: 15px; border-radius: 0px; margin-bottom: 16px; color: white; position: relative; overflow: hidden;">
                                    <div style="position: absolute; top: 0; right: 0; width: 150px; height: 150px; 
                                        background: rgba(255,255,255,0.1); border-radius: 50%; transform: translate(40%, -40%);"></div>
                                    <div style="position: relative; z-index: 1;">
                                        <div style="display: flex; align-items: center; gap: 16px;">
                                            <div style="width: 56px; height: 56px; background: rgba(255,255,255,0.2); 
                                                backdrop-filter: blur(10px); border-radius: 14px; display: flex; 
                                                align-items: center; justify-content: center; font-weight: 700; font-size: 22px; 
                                                border: 2px solid rgba(255,255,255,0.3);">
                                                ${responsable.name.substring(0, 2).toUpperCase()}
                                            </div>
                                            <div style="flex: 1; text-align: left;">
                                                <h3 style="margin: 0; font-size: 20px; font-weight: 700;">${responsable.name}</h3>
                                                <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px; opacity: 0.9;">
                                                    ${getStatusDot(responsable.is_active)}
                                                    <span style="font-size: 12px;">${responsable.is_active ? 'Actif' : 'Inactif'}</span>
                                                    <span style="opacity: 0.7;">•</span>
                                                    <span style="font-size: 12px;">ID ${responsable.id}</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Quick Stats in Header -->
                                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 20px;">
                                            <div style="text-align: center;">
                                                <div style="font-size: 26px; font-weight: 700; line-height: 1;">${stats.total_agents || 0}</div>
                                                <div style="font-size: 11px; opacity: 0.8; margin-top: 2px;">Agents</div>
                                            </div>
                                            <div style="text-align: center;">
                                                <div style="font-size: 26px; font-weight: 700; line-height: 1;">${stats.performance_score || 0}%</div>
                                                <div style="font-size: 11px; opacity: 0.8; margin-top: 2px;">Performance</div>
                                            </div>
                                            <div style="text-align: center;">
                                                <div style="font-size: 20px; font-weight: 700; line-height: 1;">${formatCurrency(stats.monthly_amount || 0)}</div>
                                                <div style="font-size: 11px; opacity: 0.8; margin-top: 2px;">Ce mois</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contact Info - Horizontal Pills -->
                                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px;">
                                    <div style="flex: 1; min-width: 200px; background: #f8fafc; padding: 10px 14px; border-radius: 8px; 
                                        display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0;">
                                        <span style="font-size: 16px;">📧</span>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Email</div>
                                            <div style="font-size: 12px; color: #1e293b; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                ${responsable.email}
                                            </div>
                                        </div>
                                    </div>
                                    <div style="flex: 1; min-width: 150px; background: #f8fafc; padding: 10px 14px; border-radius: 8px; 
                                        display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0;">
                                        <span style="font-size: 16px;">📱</span>
                                        <div style="flex: 1;">
                                            <div style="font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Téléphone</div>
                                            <div style="font-size: 12px; color: #1e293b; font-weight: 500;">${responsable.phone || 'N/A'}</div>
                                        </div>
                                    </div>
                                </div>
                                
                                ${teams.length > 0 ? `
                                    <!-- Teams Section -->
                                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                            <span style="font-weight: 600; color: #1e293b; font-size: 13px;">Équipes gérées</span>
                                            <span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600;">
                                                ${teams.length}
                                            </span>
                                        </div>
                                        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                            ${teams.map(team => `
                                                <div style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); 
                                                    padding: 6px 10px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;
                                                    border: 1px solid #cbd5e1;">
                                                    <span style="font-weight: 600; color: #1e293b; font-size: 11px;">${team.name}</span>
                                                    <span style="background: #667eea; color: white; padding: 1px 5px; border-radius: 6px; 
                                                        font-size: 9px; font-weight: 600;">${team.agent_count}</span>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <!-- Agents List -->
                                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                        <span style="font-weight: 600; color: #1e293b; font-size: 13px;">Agents assignés</span>
                                        <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600;">
                                            ${agents.length}
                                        </span>
                                    </div>
                                    
                                    ${agents.length > 0 ? `
                                        <div style="max-height: 200px; overflow-y: auto; margin: -4px;">
                                            ${agents.map((agent, index) => `
                                                <div style="background: ${index % 2 === 0 ? '#f8fafc' : 'white'}; 
                                                    padding: 10px 12px; border-radius: 8px; margin-bottom: 6px; 
                                                    border: 1px solid ${index % 2 === 0 ? '#e2e8f0' : 'transparent'};">
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                                        <div style="flex: 1; min-width: 0;">
                                                            <div style="font-weight: 600; color: #1e293b; font-size: 12px; 
                                                                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                                ${agent.name}
                                                            </div>
                                                            <div style="font-size: 10px; color: #64748b; margin-top: 1px;">
                                                                ${agent.team_name || 'Sans équipe'}
                                                            </div>
                                                        </div>
                                                        <div style="display: flex; align-items: center; gap: 6px;">
                                                            ${getStatusDot(agent.is_active)}
                                                        </div>
                                                    </div>
                                                    ${getPerformanceBar(agent.monthly_performance)}
                                                </div>
                                            `).join('')}
                                        </div>
                                    ` : `
                                        <div style="text-align: center; padding: 30px 20px; color: #94a3b8;">
                                            <div style="font-size: 32px; margin-bottom: 8px; opacity: 0.5;">👥</div>
                                            <div style="font-size: 12px;">Aucun agent assigné</div>
                                        </div>
                                    `}
                                </div>
                                
                                ${stats.total_confirmations > 0 ? `
                                    <!-- Additional Stats -->
                                    <div style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); 
                                        border: 1px solid #fcd34d; border-radius: 10px; padding: 12px 14px; margin-bottom: 16px;">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 11px;">
                                            <div>
                                                <div style="color: #92400e; font-weight: 400;">Total confirmations</div>
                                                <div style="font-size: 18px; font-weight: 700; color: #78350f; margin-top: 2px;">
                                                    ${stats.total_confirmations}
                                                </div>
                                            </div>
                                            <div>
                                                <div style="color: #92400e; font-weight: 400;">Montant total</div>
                                                <div style="font-size: 18px; font-weight: 700; color: #78350f; margin-top: 2px;">
                                                    ${formatCurrency(stats.total_amount)}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                                
                                <!-- Footer Info -->
                                <div style="display: flex; justify-content: space-between; align-items: center; 
                                    padding: 10px 14px; background: #f8fafc; border-radius: 8px; margin-bottom: 16px; 
                                    font-size: 10px; color: #64748b; border: 1px solid #e2e8f0;">
                                    <span>Créé le ${formatDate(responsable.created_at)}</span>
                                    <span>•</span>
                                    <span>Par ${responsable.created_by_name || 'Système'}</span>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div style="display: flex; gap: 10px;">
                                    <button type="button" 
                                        style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                            color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 13px; 
                                            cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;
                                            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3);"
                                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 12px rgba(102, 126, 234, 0.4)';"
                                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px rgba(102, 126, 234, 0.3)';"
                                        onclick="editResponsable(${responsable.id}); Swal.close();">
                                        ✏️ Modifier
                                    </button>
                                    <button type="button" 
                                        style="flex: 1; padding: 12px; background: white; color: #667eea; 
                                            border: 2px solid #667eea; border-radius: 8px; font-weight: 600; font-size: 13px; 
                                            cursor: pointer; transition: all 0.2s;"
                                        onmouseover="this.style.background='#667eea'; this.style.color='white';"
                                        onmouseout="this.style.background='white'; this.style.color='#667eea';"
                                        onclick="assignAgentsToResponsable(${responsable.id}); Swal.close();">
                                        ➕ Assigner agents
                                    </button>
                                </div>
                            </div>
                        `,
                        width: '520px',
                        showConfirmButton: false,
                        showCloseButton: true,
                        padding: '0px',
                        background: '#ffffff',
                        customClass: {
                            popup: 'responsable-modern-modal',
                            closeButton: 'modern-close-btn'
                        }
                    });
                } else {
                    throw new Error(data.message || 'Impossible de charger les détails du responsable');
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message || 'Une erreur est survenue',
                    width: '400px'
                });
            }
        }

        // Fonctions supplémentaires (à ajouter si nécessaire)
        async function editResponsable(id) {
            try {
                // Charger les données du responsable
                const response = await fetch(`./controllers/get_responsable_detailsApi.php?id=${id}&include_agents=false`);
                const data = await response.json();
                
                if (!data.success || !data.data) {
                    throw new Error('Impossible de charger les données du responsable');
                }
                
                const responsable = data.data;
                
                // Récupérer les équipes pour le dropdown
                const teamsResponse = await fetch(`./controllers/get_teams.php?user_id=${responsable.user_id}`);
                const teamsData = await teamsResponse.json();
                const teams = teamsData.success ? teamsData.data : [];
                
                Swal.fire({
                    title: `<div style="font-size: 22px; font-weight: 600; color: #1f2937;">
                            <i class="bi bi-pencil-square me-2" style="color: #667eea;"></i>
                            Modifier le Responsable
                            </div>`,
                    html: `
                        <div id="edit-responsable-form" style="text-align: left; max-width: 100%; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
                            
                            <!-- Informations de base -->
                            <div style="background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                                <h5 style="margin-bottom: 15px; font-size: 16px; color: #374151;">
                                    <i class="bi bi-info-circle me-2"></i>Informations personnelles
                                </h5>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <!-- Nom -->
                                    <div>
                                        <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px; font-weight: 500;">
                                            Nom complet *
                                        </label>
                                        <input type="text" 
                                            id="edit-responsable-name" 
                                            value="${responsable.name}" 
                                            style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;"
                                            required>
                                    </div>
                                    
                                    <!-- Email -->
                                    <div>
                                        <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px; font-weight: 500;">
                                            Email *
                                        </label>
                                        <input type="email" 
                                            id="edit-responsable-email" 
                                            value="${responsable.email}" 
                                            style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;"
                                            required>
                                    </div>
                                    
                                    <!-- Téléphone -->
                                    <div>
                                        <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px; font-weight: 500;">
                                            Téléphone
                                        </label>
                                        <input type="tel" 
                                            id="edit-responsable-phone" 
                                            value="${responsable.phone || ''}" 
                                            style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;"
                                            placeholder="0600000000">
                                    </div>
                                    
                                    <!-- Équipe principale -->
                                    <div>
                                        <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px; font-weight: 500;">
                                            Équipe principale
                                        </label>
                                        <select id="edit-responsable-team" 
                                            style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white;">
                                            <option value="">Non assigné</option>
                                            ${teams.map(team => `
                                                <option value="${team.id}" ${responsable.team_id == team.id ? 'selected' : ''}>
                                                    ${team.name}
                                                </option>
                                            `).join('')}
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gestion du mot de passe -->
                            <div style="background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                                <h5 style="margin-bottom: 15px; font-size: 16px; color: #374151;">
                                    <i class="bi bi-key me-2"></i>Gestion du mot de passe
                                </h5>
                                
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                                    <input type="checkbox" id="edit-password-checkbox" style="transform: scale(1.2);">
                                    <label for="edit-password-checkbox" style="font-size: 14px; color: #374151;">
                                        Modifier le mot de passe
                                    </label>
                                </div>
                                
                                <div id="password-fields" style="display: none;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div>
                                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px; font-weight: 500;">
                                                Nouveau mot de passe
                                            </label>
                                            <input type="password" 
                                                id="edit-responsable-password" 
                                                style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;"
                                                placeholder="Minimum 6 caractères">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px; font-weight: 500;">
                                                Confirmer le mot de passe
                                            </label>
                                            <input type="password" 
                                                id="edit-responsable-password-confirm" 
                                                style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px;"
                                                placeholder="Répéter le mot de passe">
                                        </div>
                                    </div>
                                    <div style="margin-top: 10px; font-size: 12px; color: #6b7280;">
                                        <i class="bi bi-info-circle me-1"></i> Laisser vide pour conserver le mot de passe actuel
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Statut et permissions -->
                            <div style="background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                                <h5 style="margin-bottom: 15px; font-size: 16px; color: #374151;">
                                    <i class="bi bi-gear me-2"></i>Statut et permissions
                                </h5>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                    <!-- Statut -->
                                    <div>
                                        <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px; font-weight: 500;">
                                            Statut du responsable
                                        </label>
                                        <select id="edit-responsable-status" 
                                            style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white;">
                                            <option value="1" ${responsable.is_active ? 'selected' : ''}>Actif</option>
                                            <option value="0" ${!responsable.is_active ? 'selected' : ''}>Inactif</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Niveau d'accès -->
                                    <div>
                                        <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 5px; font-weight: 500;">
                                            Niveau d'accès
                                        </label>
                                        <select id="edit-responsable-access" 
                                            style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; background: white;">
                                            <option value="basic" selected>Basique (Gestion agents)</option>
                                            <option value="advanced">Avancé (Gestion équipes)</option>
                                            <option value="full">Complet (Tous les accès)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Permissions supplémentaires -->
                                <div style="margin-top: 15px;">
                                    <label style="display: block; font-size: 13px; color: #6b7280; margin-bottom: 10px; font-weight: 500;">
                                        Permissions supplémentaires
                                    </label>
                                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                                        <div>
                                            <input type="checkbox" id="permission-view-reports" style="transform: scale(1.1); margin-right: 8px;">
                                            <label for="permission-view-reports" style="font-size: 13px; color: #374151;">
                                                Voir les rapports
                                            </label>
                                        </div>
                                        <div>
                                            <input type="checkbox" id="permission-manage-payments" style="transform: scale(1.1); margin-right: 8px;">
                                            <label for="permission-manage-payments" style="font-size: 13px; color: #374151;">
                                                Gérer les paiements
                                            </label>
                                        </div>
                                        <div>
                                            <input type="checkbox" id="permission-add-agents" style="transform: scale(1.1); margin-right: 8px;" checked>
                                            <label for="permission-add-agents" style="font-size: 13px; color: #374151;">
                                                Ajouter des agents
                                            </label>
                                        </div>
                                        <div>
                                            <input type="checkbox" id="permission-edit-agents" style="transform: scale(1.1); margin-right: 8px;" checked>
                                            <label for="permission-edit-agents" style="font-size: 13px; color: #374151;">
                                                Modifier les agents
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Zone de notification -->
                            <div id="edit-responsable-error" style="display: none; padding: 10px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; margin-bottom: 15px; color: #dc2626; font-size: 13px;"></div>
                            
                        </div>
                    `,
                    width: '700px',
                    showCancelButton: true,
                    confirmButtonText: 'Enregistrer',
                    cancelButtonText: 'Annuler',
                    confirmButtonColor: '#667eea',
                    cancelButtonColor: '#6b7280',
                    showLoaderOnConfirm: true,
                    preConfirm: async () => {
                        try {
                            // Récupérer les valeurs du formulaire
                            const name = document.getElementById('edit-responsable-name').value.trim();
                            const email = document.getElementById('edit-responsable-email').value.trim();
                            const phone = document.getElementById('edit-responsable-phone').value.trim();
                            const teamId = document.getElementById('edit-responsable-team').value;
                            const status = document.getElementById('edit-responsable-status').value;
                            const changePassword = document.getElementById('edit-password-checkbox').checked;
                            const password = document.getElementById('edit-responsable-password')?.value || '';
                            const passwordConfirm = document.getElementById('edit-responsable-password-confirm')?.value || '';
                            
                            // Validation
                            const errorDiv = document.getElementById('edit-responsable-error');
                            
                            if (!name) {
                                errorDiv.textContent = 'Le nom est requis';
                                errorDiv.style.display = 'block';
                                return false;
                            }
                            
                            if (!email) {
                                errorDiv.textContent = 'L\'email est requis';
                                errorDiv.style.display = 'block';
                                return false;
                            }
                            
                            if (!isValidEmail(email)) {
                                errorDiv.textContent = 'Format d\'email invalide';
                                errorDiv.style.display = 'block';
                                return false;
                            }
                            
                            if (phone && !isValidPhone(phone)) {
                                errorDiv.textContent = 'Format de téléphone invalide (ex: 0600000000)';
                                errorDiv.style.display = 'block';
                                return false;
                            }
                            
                            if (changePassword) {
                                if (password.length < 6) {
                                    errorDiv.textContent = 'Le mot de passe doit contenir au moins 6 caractères';
                                    errorDiv.style.display = 'block';
                                    return false;
                                }
                                
                                if (password !== passwordConfirm) {
                                    errorDiv.textContent = 'Les mots de passe ne correspondent pas';
                                    errorDiv.style.display = 'block';
                                    return false;
                                }
                            }
                            
                            // Préparer les données pour l'API
                            const formData = new FormData();
                            formData.append('id', responsable.id);
                            formData.append('name', name);
                            formData.append('email', email);
                            formData.append('phone', phone);
                            formData.append('team_id', teamId || '');
                            formData.append('is_active', status);
                            
                            if (changePassword && password) {
                                formData.append('password', password);
                            }
                            
                            // Ajouter les permissions
                            const permissions = [];
                            if (document.getElementById('permission-view-reports')?.checked) permissions.push('view_reports');
                            if (document.getElementById('permission-manage-payments')?.checked) permissions.push('manage_payments');
                            if (document.getElementById('permission-add-agents')?.checked) permissions.push('add_agents');
                            if (document.getElementById('permission-edit-agents')?.checked) permissions.push('edit_agents');
                            formData.append('permissions', JSON.stringify(permissions));
                            
                            // Envoyer la requête
                            const updateResponse = await fetch('./controllers/update_responsableApi.php', {
                                method: 'POST',
                                body: formData
                            });
                            
                            const updateData = await updateResponse.json();
                            
                            if (!updateData.success) {
                                throw new Error(updateData.message || updateData.error || 'Erreur lors de la mise à jour');
                            }
                            
                            return updateData;
                            
                        } catch (error) {
                            Swal.showValidationMessage(`Erreur: ${error.message}`);
                            return false;
                        }
                    },
                    didOpen: () => {
                        // Activer/désactiver les champs de mot de passe
                        const passwordCheckbox = document.getElementById('edit-password-checkbox');
                        const passwordFields = document.getElementById('password-fields');
                        
                        if (passwordCheckbox && passwordFields) {
                            passwordCheckbox.addEventListener('change', function() {
                                passwordFields.style.display = this.checked ? 'block' : 'none';
                            });
                        }
                        
                        // Initialiser les permissions (vous pouvez récupérer ces données depuis votre API si elles existent)
                        // Pour l'instant, nous utilisons des valeurs par défaut
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès!',
                            text: 'Responsable modifié avec succès',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            // Rafraîchir la page ou mettre à jour l'interface
                            location.reload();
                        });
                    }
                });
                
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message || 'Impossible de charger les données du responsable',
                    width: '400px'
                });
            }
        }

        // Fonctions d'aide
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function isValidPhone(phone) {
            const re = /^(06|07|05)\d{8}$/;
            return re.test(phone);
        }

        async function assignAgentsToResponsable(responsableId) {
            try {
                // Charger les données du responsable
                const responsableResponse = await fetch(`./controllers/get_responsable_detailsApi.php?id=${responsableId}&include_agents=true`);
                const responsableData = await responsableResponse.json();
                
                if (!responsableData.success || !responsableData.data) {
                    throw new Error('Impossible de charger les données du responsable');
                }
                
                const responsable = responsableData.data;
                const currentAgents = responsable.agents || [];
                const currentAgentIds = currentAgents.map(agent => agent.id);
                
                // Charger tous les agents de l'utilisateur
                const formData = new FormData();
                formData.append('action', 'get_agents_with_regions');
                
                const agentsResponse = await fetch('./controllers/get_agentsForAssign.php', {
                    method: 'POST',
                    body: formData
                });
                
                const agentsData = await agentsResponse.json();
                
                if (!agentsData.success) {
                    throw new Error('Impossible de charger la liste des agents');
                }
                
                const allAgents = agentsData.agents || [];
                
                // Formater les agents
                const formattedAgents = allAgents.map(agent => ({
                    id: agent.id,
                    name: agent.name,
                    email: agent.email,
                    region_name: agent.region_name || 'Non assigné',
                    is_assigned: currentAgentIds.includes(agent.id),
                    score: agent.score || 0,
                    confirmation: agent.confirmation_rate || 0
                }));
                
                Swal.fire({
                    title: `<div style="font-size: 20px; font-weight: 600; color: #1f2937;">
                            <i class="bi bi-person-plus me-2" style="color: #667eea;"></i>
                            Assigner des agents
                            </div>`,
                    html: `
                        <div id="assign-agents-form" style="text-align: left; max-width: 100%;">
                            
                            <!-- En-tête compact -->
                            <div style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 600; color: #374151; font-size: 15px;">${responsable.name}</div>
                                    <div style="font-size: 12px; color: #6b7280;">${responsable.email}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 14px; color: #6b7280;">Agents assignés: <strong style="color: #667eea;">${currentAgents.length}</strong></div>
                                    <div style="font-size: 12px; color: #6b7280;">Disponibles: <strong style="color: #10b981;">${allAgents.length - currentAgents.length}</strong></div>
                                </div>
                            </div>
                            
                            <!-- Contrôles rapides -->
                            <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px; margin-bottom: 12px;">
                                <input type="text" 
                                    id="agent-search" 
                                    placeholder="Rechercher..."
                                    style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px;"
                                    autocomplete="off">
                                <div style="display: flex; gap: 6px;">
                                    <button id="select-available" class="btn btn-success btn-xs" style="padding: 6px 10px; font-size: 11px;">
                                        <i class="bi bi-check-square me-1"></i>Disponibles
                                    </button>
                                    <button id="clear-search" class="btn btn-secondary btn-xs" style="padding: 6px 10px; font-size: 11px;">
                                        <i class="bi bi-eraser"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Liste compacte des agents -->
                            <div style="max-height: 250px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 6px; padding: 5px; background: white;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                                            <th style="padding: 8px; text-align: left; width: 30px;">
                                                <input type="checkbox" id="select-all-checkbox" style="transform: scale(1.1);">
                                            </th>
                                            <th style="padding: 8px; text-align: left;">Agent</th>
                                            <th style="padding: 8px; text-align: left;">Région</th>
                                            <th style="padding: 8px; text-align: center; width: 70px;">Score</th>
                                            <th style="padding: 8px; text-align: center; width: 60px;">Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody id="agents-table-body">
                                        ${formattedAgents.length > 0 ? formattedAgents.map(agent => `
                                            <tr class="agent-row" data-agent-id="${agent.id}" data-status="${agent.is_assigned ? 'assigned' : 'available'}" style="border-bottom: 1px solid #f3f4f6; cursor: pointer;">
                                                <td style="padding: 8px; vertical-align: middle;">
                                                    <input type="checkbox" 
                                                        class="agent-checkbox" 
                                                        data-agent-id="${agent.id}"
                                                        ${agent.is_assigned ? 'checked' : ''}
                                                        style="transform: scale(1.1);">
                                                </td>
                                                <td style="padding: 8px; vertical-align: middle;">
                                                    <div style="font-weight: 500; color: #374151;">${agent.name}</div>
                                                    <div style="font-size: 11px; color: #6b7280;">${agent.email}</div>
                                                </td>
                                                <td style="padding: 8px; vertical-align: middle; color: #6b7280; font-size: 11px;">
                                                    ${agent.region_name}
                                                </td>
                                                <td style="padding: 8px; vertical-align: middle; text-align: center;">
                                                    <span style="font-weight: 600; color: ${agent.score >= 70 ? '#10b981' : agent.score >= 50 ? '#f59e0b' : '#ef4444'};">
                                                        ${agent.score}
                                                    </span>
                                                </td>
                                                <td style="padding: 8px; vertical-align: middle; text-align: center;">
                                                    <span style="font-size: 11px; padding: 2px 6px; border-radius: 10px; font-weight: 500;
                                                        background: ${agent.is_assigned ? '#dbeafe' : '#dcfce7'}; 
                                                        color: ${agent.is_assigned ? '#1e40af' : '#166534'};">
                                                        ${agent.is_assigned ? 'Assigné' : 'Disponible'}
                                                    </span>
                                                </td>
                                            </tr>
                                        `).join('') : `
                                            <tr>
                                                <td colspan="5" style="padding: 30px; text-align: center; color: #9ca3af;">
                                                    <i class="bi bi-people" style="font-size: 24px; opacity: 0.5; margin-bottom: 8px; display: block;"></i>
                                                    Aucun agent disponible
                                                </td>
                                            </tr>
                                        `}
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Résumé et options -->
                            <div style="margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div style="background: #f0f9ff; padding: 8px; border-radius: 6px; border: 1px solid #bfdbfe;">
                                    <div style="font-size: 12px; color: #1e40af; font-weight: 500;">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Sélection: <span id="selected-count">0</span> agent(s)
                                    </div>
                                </div>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <input type="checkbox" id="remove-other-assignments" style="transform: scale(1.1);">
                                    <label for="remove-other-assignments" style="font-size: 12px; color: #6b7280;">
                                        Retirer assignations actuelles
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Zone d'erreur -->
                            <div id="assign-agents-error" style="display: none; margin-top: 10px; padding: 8px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; color: #dc2626; font-size: 12px;"></div>
                            
                        </div>
                    `,
                    width: '600px',
                    showCancelButton: true,
                    confirmButtonText: 'Assigner',
                    cancelButtonText: 'Annuler',
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#6b7280',
                    showLoaderOnConfirm: true,
                    preConfirm: async () => {
                        try {
                            // Récupérer les agents sélectionnés
                            const selectedAgents = [];
                            const agentCheckboxes = document.querySelectorAll('.agent-checkbox:checked');
                            
                            if (agentCheckboxes.length === 0) {
                                const errorDiv = document.getElementById('assign-agents-error');
                                errorDiv.textContent = 'Veuillez sélectionner au moins un agent';
                                errorDiv.style.display = 'block';
                                return false;
                            }
                            
                            agentCheckboxes.forEach(checkbox => {
                                selectedAgents.push(parseInt(checkbox.dataset.agentId));
                            });
                            
                            const removeOtherAssignments = document.getElementById('remove-other-assignments').checked;
                            
                            // Envoyer la requête
                            const assignFormData = new FormData();
                            assignFormData.append('responsable_id', responsableId);
                            assignFormData.append('agent_ids', JSON.stringify(selectedAgents));
                            assignFormData.append('remove_other_assignments', removeOtherAssignments ? '1' : '0');
                            
                            const response = await fetch('./controllers/assign_agents_to_responsableApi.php', {
                                method: 'POST',
                                body: assignFormData
                            });
                            
                            const data = await response.json();
                            
                            if (!data.success) {
                                throw new Error(data.message || 'Erreur lors de l\'assignation');
                            }
                            
                            return data;
                            
                        } catch (error) {
                            Swal.showValidationMessage(`Erreur: ${error.message}`);
                            return false;
                        }
                    },
                    didOpen: () => {
                        // Initialiser les fonctionnalités
                        initCompactAssignForm();
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Assignation réussie',
                            html: `<div style="text-align: center;">
                                <div style="font-size: 36px; color: #10b981; margin-bottom: 10px;">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div style="font-size: 16px; font-weight: 500; margin-bottom: 5px;">
                                    ${result.value.assigned_count} agent(s) assigné(s)
                                </div>
                                <div style="font-size: 13px; color: #6b7280;">
                                    à ${responsable.name}
                                </div>
                            </div>`,
                            timer: 2000,
                            showConfirmButton: false,
                            willClose: () => location.reload()
                        });
                    }
                });
                
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: error.message || 'Impossible de charger les données',
                    width: '400px'
                });
            }
        }

        // Fonction d'initialisation du formulaire compact
        function initCompactAssignForm() {
            const searchInput = document.getElementById('agent-search');
            const clearSearchBtn = document.getElementById('clear-search');
            const selectAvailableBtn = document.getElementById('select-available');
            const selectAllCheckbox = document.getElementById('select-all-checkbox');
            const removeOtherCheckbox = document.getElementById('remove-other-assignments');
            
            // Mettre à jour le compteur de sélection
            function updateSelectionCount() {
                const selectedCount = document.querySelectorAll('.agent-checkbox:checked').length;
                document.getElementById('selected-count').textContent = selectedCount;
            }
            
            // Fonction de recherche
            function filterAgents() {
                const searchTerm = searchInput.value.toLowerCase();
                const rows = document.querySelectorAll('.agent-row');
                
                rows.forEach(row => {
                    const name = row.querySelector('td:nth-child(2) div:first-child').textContent.toLowerCase();
                    const email = row.querySelector('td:nth-child(2) div:last-child').textContent.toLowerCase();
                    const region = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                    
                    const matches = name.includes(searchTerm) || email.includes(searchTerm) || region.includes(searchTerm);
                    row.style.display = matches ? '' : 'none';
                });
            }
            
            // Événements
            if (searchInput) {
                searchInput.addEventListener('input', filterAgents);
            }
            
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', () => {
                    searchInput.value = '';
                    filterAgents();
                });
            }
            
            if (selectAvailableBtn) {
                selectAvailableBtn.addEventListener('click', () => {
                    document.querySelectorAll('.agent-row[data-status="available"] .agent-checkbox').forEach(checkbox => {
                        checkbox.checked = true;
                    });
                    updateSelectionCount();
                });
            }
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const allCheckboxes = document.querySelectorAll('.agent-checkbox');
                    allCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateSelectionCount();
                });
            }
            
            // Mettre à jour le compteur lorsqu'un agent est sélectionné
            document.querySelectorAll('.agent-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectionCount);
            });
            
            // Permettre de cliquer sur toute la ligne pour sélectionner
            document.querySelectorAll('.agent-row').forEach(row => {
                row.addEventListener('click', (e) => {
                    if (e.target.type !== 'checkbox') {
                        const checkbox = row.querySelector('.agent-checkbox');
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });
            });
            
            // Initialiser le compteur
            updateSelectionCount();
        }

        // Fonction d'initialisation du formulaire
        function initAssignAgentsForm() {
            const searchInput = document.getElementById('agent-search');
            const clearSearchBtn = document.getElementById('clear-search');
            const toggleFiltersBtn = document.getElementById('toggle-filters');
            const filterStatus = document.getElementById('filter-status');
            const filterRegion = document.getElementById('filter-region');
            const filterSort = document.getElementById('filter-sort');
            const selectAllBtn = document.getElementById('select-all');
            const deselectAllBtn = document.getElementById('deselect-all');
            const selectAvailableBtn = document.getElementById('select-available');
            
            // Variables d'état
            let isAdvancedFiltersVisible = false;
            
            // Fonction pour mettre à jour le résumé de sélection
            function updateSelectionSummary() {
                const selectedCheckboxes = document.querySelectorAll('.agent-checkbox:checked');
                const selectedCount = selectedCheckboxes.length;
                
                if (selectedCount > 0) {
                    const summaryDiv = document.getElementById('selection-summary');
                    const selectedCountSpan = document.getElementById('selected-count');
                    const availableCountSpan = document.getElementById('available-count');
                    const assignedCountSpan = document.getElementById('assigned-count');
                    
                    let availableCount = 0;
                    let assignedCount = 0;
                    
                    selectedCheckboxes.forEach(checkbox => {
                        const agentItem = checkbox.closest('.agent-item');
                        if (agentItem) {
                            if (agentItem.dataset.status === 'available') {
                                availableCount++;
                            } else if (agentItem.dataset.status === 'assigned') {
                                assignedCount++;
                            }
                        }
                    });
                    
                    selectedCountSpan.textContent = selectedCount;
                    availableCountSpan.textContent = availableCount;
                    assignedCountSpan.textContent = assignedCount;
                    
                    summaryDiv.style.display = 'block';
                } else {
                    document.getElementById('selection-summary').style.display = 'none';
                }
            }
            
            // Fonction de filtrage
            function filterAgents() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusFilter = filterStatus.value;
                const regionFilter = filterRegion.value;
                const sortFilter = filterSort.value;
                
                const agentItems = document.querySelectorAll('.agent-item');
                let visibleItems = [];
                
                agentItems.forEach(item => {
                    const agentName = item.querySelector('div[style*="font-weight: 600"]').textContent.toLowerCase();
                    const agentEmail = item.querySelector('div[style*="font-size: 12px"]').textContent.toLowerCase();
                    const agentPhone = item.querySelector('div[style*="font-size: 12px"]').textContent.includes('phone') ? 
                        item.querySelector('div[style*="font-size: 12px"]').textContent.toLowerCase() : '';
                    const agentStatus = item.dataset.status;
                    const agentRegion = item.dataset.region;
                    const agentScore = parseFloat(item.dataset.score) || 0;
                    const agentConfirmation = parseFloat(item.dataset.confirmation) || 0;
                    
                    // Appliquer les filtres
                    const matchesSearch = agentName.includes(searchTerm) || 
                                        agentEmail.includes(searchTerm) || 
                                        agentPhone.includes(searchTerm);
                    
                    const matchesStatus = statusFilter === 'all' || 
                                        (statusFilter === 'assigned' && agentStatus === 'assigned') ||
                                        (statusFilter === 'not_assigned' && agentStatus === 'available');
                    
                    const matchesRegion = regionFilter === 'all' || 
                                        agentRegion === document.querySelector(`#filter-region option[value="${regionFilter}"]`)?.textContent;
                    
                    const shouldShow = matchesSearch && matchesStatus && matchesRegion;
                    
                    if (shouldShow) {
                        item.style.display = 'block';
                        visibleItems.push({
                            element: item,
                            name: agentName,
                            score: agentScore,
                            confirmation: agentConfirmation
                        });
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Trier les éléments visibles
                if (sortFilter === 'score') {
                    visibleItems.sort((a, b) => b.score - a.score);
                } else if (sortFilter === 'confirmation') {
                    visibleItems.sort((a, b) => b.confirmation - a.confirmation);
                } else {
                    visibleItems.sort((a, b) => a.name.localeCompare(b.name));
                }
                
                // Réorganiser les éléments dans le DOM
                const regionGroups = document.querySelectorAll('.region-group');
                regionGroups.forEach(group => {
                    const regionContainer = group.querySelector('div[style*="padding: 15px"] > div');
                    if (regionContainer) {
                        const sortedItems = visibleItems
                            .filter(item => item.element.closest('.region-group') === group)
                            .map(item => item.element);
                        
                        regionContainer.innerHTML = '';
                        sortedItems.forEach(item => {
                            regionContainer.appendChild(item);
                        });
                    }
                    
                    // Masquer les régions vides
                    const visibleAgents = group.querySelectorAll('.agent-item[style*="display: block"]');
                    if (visibleAgents.length === 0) {
                        group.style.display = 'none';
                    } else {
                        group.style.display = 'block';
                        // Mettre à jour le compteur
                        const headerSpan = group.querySelector('div[style*="background: linear-gradient"] span:last-child');
                        if (headerSpan) {
                            const assignedCount = Array.from(visibleAgents).filter(agent => 
                                agent.dataset.status === 'assigned'
                            ).length;
                            headerSpan.textContent = `${visibleAgents.length} agents (${assignedCount} assignés)`;
                        }
                    }
                });
                
                updateSelectionSummary();
            }
            
            // Événements de recherche
            if (searchInput) {
                searchInput.addEventListener('input', filterAgents);
            }
            
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', () => {
                    searchInput.value = '';
                    filterAgents();
                });
            }
            
            // Toggle des filtres avancés
            if (toggleFiltersBtn) {
                toggleFiltersBtn.addEventListener('click', () => {
                    const filtersDiv = document.getElementById('advanced-filters');
                    isAdvancedFiltersVisible = !isAdvancedFiltersVisible;
                    filtersDiv.style.display = isAdvancedFiltersVisible ? 'block' : 'none';
                    toggleFiltersBtn.innerHTML = isAdvancedFiltersVisible ? 
                        '<i class="bi bi-sliders me-1"></i>Masquer filtres' :
                        '<i class="bi bi-sliders me-1"></i>Filtres';
                });
            }
            
            // Événements des filtres
            if (filterStatus) filterStatus.addEventListener('change', filterAgents);
            if (filterRegion) filterRegion.addEventListener('change', filterAgents);
            if (filterSort) filterSort.addEventListener('change', filterAgents);
            
            // Sélection/désélection globale
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', () => {
                    document.querySelectorAll('.agent-checkbox').forEach(checkbox => {
                        checkbox.checked = true;
                    });
                    updateSelectionSummary();
                });
            }
            
            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', () => {
                    document.querySelectorAll('.agent-checkbox').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    updateSelectionSummary();
                });
            }
            
            if (selectAvailableBtn) {
                selectAvailableBtn.addEventListener('click', () => {
                    document.querySelectorAll('.agent-checkbox').forEach(checkbox => {
                        const agentItem = checkbox.closest('.agent-item');
                        checkbox.checked = agentItem && agentItem.dataset.status === 'available';
                    });
                    updateSelectionSummary();
                });
            }
            
            // Mettre à jour le résumé lors de la sélection d'un agent
            document.querySelectorAll('.agent-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectionSummary);
            });
            
            // Initialiser le résumé
            updateSelectionSummary();
        }

        // Fonction pour afficher la modal de succès
        function showAssignSuccessModal(resultData, responsableName) {
            Swal.fire({
                icon: 'success',
                title: 'Assignation réussie!',
                html: `
                    <div style="text-align: center; padding: 20px;">
                        <div style="font-size: 72px; color: #10b981; margin-bottom: 20px;">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div style="font-size: 24px; font-weight: 600; color: #374151; margin-bottom: 15px;">
                            ${resultData.assigned_count || 'Plusieurs'} agent(s) assigné(s)
                        </div>
                        <div style="color: #6b7280; font-size: 16px; margin-bottom: 25px;">
                            Les agents ont été assignés à <strong>${responsableName}</strong> avec succès
                        </div>
                        <div style="background: #f8fafc; padding: 15px; border-radius: 10px; margin-top: 20px; text-align: left;">
                            <div style="font-size: 14px; color: #374151; margin-bottom: 10px; font-weight: 500;">
                                <i class="bi bi-info-circle me-2"></i>Résumé de l'assignation
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 13px;">
                                <div style="color: #6b7280;">Nombre d'agents assignés:</div>
                                <div style="font-weight: 600; color: #10b981;">${resultData.assigned_count || 0}</div>
                                <div style="color: #6b7280;">Responsable:</div>
                                <div style="font-weight: 600;">${responsableName}</div>
                                <div style="color: #6b7280;">Heure de l'assignation:</div>
                                <div style="font-weight: 600;">${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</div>
                            </div>
                        </div>
                    </div>
                `,
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                willClose: () => {
                    // Rafraîchir la page pour voir les changements
                    location.reload();
                }
            });
        }
        
        // Export data
        function exportData() {
            const params = new URLSearchParams(window.location.search);
            window.open(`./controllers/export_agentsApi.php?${params.toString()}`, '_blank');
        }
        
        // Refresh data
        function refreshData() {
            location.reload();
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay').forEach(modal => {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                });
            }
            
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openModal('agent');
            }
            
            if (e.ctrlKey && e.key === 's' && e.shiftKey) {
                e.preventDefault();
                openModal('responsable');
            }
            
            if (e.ctrlKey && e.key === 't') {
                e.preventDefault();
                openModal('team');
            }
        });
    </script>

</body>
</html>