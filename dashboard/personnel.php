<?php

if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../login.php'); 
} 

$db = DB::getInstance();

if (isset($_GET['logout'])) {
    logout();
}

$user = $db->getThisQuery(
    "SELECT id, `name`, `role` 
     FROM users 
     WHERE username = ? OR email = ? OR phone = ? 
     LIMIT 1", 
    [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]
);

// Get current user ID
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

// Vérification de l'abonnement
$subscription = new Subscription($user_id);
$subscriptionStatus = $subscription->getSubscriptionStatus();
$subscriptionAlerts = $subscription->getSubscriptionAlerts();

// Vérifier l'accès aux fonctionnalités
$canAccessFeatures = $subscription->canAccessFeatures();

// Si l'abonnement est expiré ou inexistant, limiter l'accès
$limitedAccess = !$canAccessFeatures;

// Get current month and year for performance calculation (as integers)
$current_month = (int) date('m');
$current_year  = (int) date('Y');

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from     = $_GET['date_from'] ?? '';
$date_to       = $_GET['date_to'] ?? '';
$performance_filter = $_GET['performance'] ?? '';

// Build WHERE (agent-level) conditions (placeholders will be added later)
$where_conditions = [];
$where_conditions[] = "a.user_id = ?"; // keep as placeholder, will bind after month params
if ($status_filter === 'active') {
    $where_conditions[] = "a.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "a.is_active = 0";
}
$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Build LEFT JOIN conditions (use real timestamp column: handled_at)
$join_conditions = "a.id = ac.agent_id";
$dateParams = [];
if (!empty($date_from) && !empty($date_to)) {
    $join_conditions .= " AND ac.handled_at BETWEEN ? AND ?";
    $dateParams[] = $date_from;
    $dateParams[] = $date_to;
} elseif (!empty($date_from)) {
    $join_conditions .= " AND ac.handled_at >= ?";
    $dateParams[] = $date_from;
} elseif (!empty($date_to)) {
    $join_conditions .= " AND ac.handled_at <= ?";
    $dateParams[] = $date_to;
}

// Prepare month/year params in the same order as the SELECT's '?' placeholders (12 values)
$monthParams = [];
for ($i = 0; $i < 6; $i++) {
    $monthParams[] = $current_month;
    $monthParams[] = $current_year;
}

// Final $params: months/year (12) first, then date range params (0 or 2), then user_id (1)
$params = array_merge($monthParams, $dateParams, [$user_id]);

// Final query (use handled_at instead of confirmed_at)
$query = "
SELECT 
    a.id,
    a.name,
    a.email,
    a.phone,
    a.is_active,
    a.created_at,
    
    -- Total performance (safe for NULLs)
    COUNT(ac.id) AS total_confirmations,
    COALESCE(SUM(ac.amount), 0) AS total_amount,
    MAX(ac.handled_at) AS last_confirmation,
    
    -- Monthly performance (safe for NULLs)
    COALESCE(SUM(CASE WHEN ac.handled_at IS NOT NULL 
                      AND MONTH(ac.handled_at) = ? 
                      AND YEAR(ac.handled_at) = ? 
                 THEN 1 ELSE 0 END), 0) AS monthly_confirmations,
    
    COALESCE(SUM(CASE WHEN ac.handled_at IS NOT NULL 
                      AND MONTH(ac.handled_at) = ? 
                      AND YEAR(ac.handled_at) = ? 
                 THEN ac.amount ELSE 0 END), 0) AS monthly_amount,
    
    -- Overall performance % (safe for division by zero)
    CASE 
        WHEN COUNT(ac.id) > 0 
        THEN ROUND(
            (COUNT(ac.id) * 100.0) / 
            (COUNT(ac.id) + GREATEST(1, COUNT(ac.id) * 0.05)), 
            1
        )
        ELSE 0 
    END AS performance_percentage,
    
    -- Monthly performance % (safe for division by zero)
    CASE 
        WHEN COALESCE(SUM(CASE WHEN ac.handled_at IS NOT NULL 
                               AND MONTH(ac.handled_at) = ? 
                               AND YEAR(ac.handled_at) = ? 
                          THEN 1 ELSE 0 END), 0) > 0 
        THEN ROUND(
            (
                COALESCE(SUM(CASE WHEN ac.handled_at IS NOT NULL 
                                  AND MONTH(ac.handled_at) = ? 
                                  AND YEAR(ac.handled_at) = ? 
                             THEN 1 ELSE 0 END), 0) * 100.0
            ) /
            (
                COALESCE(SUM(CASE WHEN ac.handled_at IS NOT NULL 
                                  AND MONTH(ac.handled_at) = ? 
                                  AND YEAR(ac.handled_at) = ? 
                             THEN 1 ELSE 0 END), 0)
                + GREATEST(1, COALESCE(SUM(CASE WHEN ac.handled_at IS NOT NULL 
                                                AND MONTH(ac.handled_at) = ? 
                                                AND YEAR(ac.handled_at) = ? 
                                           THEN 1 ELSE 0 END), 0) * 0.05)
            ),
            1
        )
        ELSE 0 
    END AS monthly_performance

FROM agents a
LEFT JOIN agent_confirmations ac 
    ON {$join_conditions}

{$where_clause}

GROUP BY 
    a.id, a.name, a.email, a.phone, a.is_active, a.created_at

ORDER BY 
    monthly_confirmations DESC, 
    total_confirmations DESC, 
    a.name ASC;
";

// Execute the query
$agents = $db->getThisQuery($query, $params);

if ($agents === false) {
    error_log("Failed to load agents from database");
    $agents = [];
}

// Get statistics - UPDATED to only show current user's data
$stats_query = "
    SELECT 
        COUNT(*) as total_agents,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_agents,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_agents,
        COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH) THEN 1 END) as new_this_month
    FROM agents
    WHERE user_id = ?
";

$stats = $db->getThisQuery($stats_query, [$user_id]);

// Get total confirmations - UPDATED to only show current user's data
$confirmations_query = "
    SELECT COUNT(*) as total_confirmations 
    FROM agent_confirmations ac
    JOIN agents a ON a.id = ac.agent_id
    WHERE a.user_id = ?
";
$confirmations_stats = $db->getThisQuery($confirmations_query, [$user_id]);

// Calculate average performance
$avg_performance = 0;
$avg_monthly_performance = 0;
if (!empty($agents)) {
    $total_performance = array_sum(array_column($agents, 'performance_percentage'));
    $avg_performance = round($total_performance / count($agents), 1);
    
    $total_monthly_performance = array_sum(array_column($agents, 'monthly_performance'));
    $avg_monthly_performance = round($total_monthly_performance / count($agents), 1);
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../login.php');
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestion des agents - Plateforme OBG ECOM">
    <title>Agents | OBG ECOM</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../assets/css/personnel.css" />
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link rel="stylesheet" href="../assets/css/agentModal.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    
    <style>

        :root {

            --primary-color: #9c80fd;
            --secondary-color: #5f34d9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;

        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .main-content {
            width: 80%;
            padding: 2rem;
            margin-left: 20%;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .dashboard-header h2 {
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: #9c80fd !important;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #d1145a;
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }
        
        .btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: transparent;
            color: var(--dark-color);
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .btn-icon:hover {
            background-color: rgba(0, 0, 0, 0.1);
            transform: scale(1.1);
        }
        
        .btn-icon.btn-danger:hover {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        /* Filter section */
        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #495057;
        }
        
        .filter-group select, 
        .filter-group input {
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: var(--border-radius);
            background-color: white;
            transition: var(--transition);
        }
        
        .filter-group select:focus, 
        .filter-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        /* Stats cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stat-card-header h3 {
            font-size: 1rem;
            font-weight: 500;
            color: #6c757d;
            margin: 0;
        }
        
        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0.5rem 0;
        }
        
        .stat-card-change {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: #28a745;
        }
        
        .stat-card-change.negative {
            color: #dc3545;
        }
        
        /* Top performers */
        .top-performers {
            margin-bottom: 2rem;
        }
        
        .top-performers h3 {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }
        
        .performers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }
        
        .performer-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }
        
        .performer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .performer-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .performer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.25rem;
        }
        
        .performer-info h4 {
            margin: 0;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .performer-info p {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .performer-stats {
            margin-top: auto;
        }
        
        .performer-stat {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .stat-value {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .performance-bar {
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .performance-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
            border-radius: 3px;
        }
        
        /* Agents table */
        .table-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }
        
        .clients-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .clients-table th {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #dee2e6;
        }
        
        .clients-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .clients-table tr:last-child td {
            border-bottom: none;
        }
        
        .clients-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .status-inactive {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Modals */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            width: 50%;
            max-width: 600px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease;
            overflow: hidden;
        }
        
        .modal-header {
            padding: 1.5rem;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            transition: var(--transition);
        }
        
        .close-modal:hover {
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        /* View agent modal */
        .agent-info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        
        .info-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
        }
        
        .info-section h4 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e9ecef;
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-weight: 500;
            color: #6c757d;
        }
        
        .info-value {
            text-align: right;
            color: var(--dark-color);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .confirmation-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .confirmation-item:last-child {
            border-bottom: none;
        }
        
        .confirmation-customer {
            font-weight: 500;
        }
        
        .confirmation-amount {
            font-weight: 600;
            color: var(--success-color);
        }
        
        .confirmation-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        /* Performance chart */
        .performance-chart {
            margin-top: 1.5rem;
            height: 200px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Responsive design */
        @media (max-width: 1200px) {
            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 90%;
                margin: 10% auto;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .agent-info-grid {
                grid-template-columns: 1fr;
            }
            
            .performers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'personnel'; 
    require_once('../assets/sidebar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">

        <?php if (!empty($subscriptionAlerts)): ?>
            
            <div class="subscription-alerts-container">
                <?php foreach ($subscriptionAlerts as $alert): ?>
                    <div class="subscription-alert alert-<?php echo $alert['type']; ?>" data-priority="<?php echo $alert['priority']; ?>">
                        <div class="alert-icon">
                            <?php if ($alert['type'] === 'danger'): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                </svg>
                            <?php elseif ($alert['type'] === 'warning'): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                </svg>
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="alert-content">
                            <h4><?php echo htmlspecialchars($alert['title']); ?></h4>
                            <p><?php echo htmlspecialchars($alert['message']); ?></p>
                        </div>
                        <div class="alert-actions">
                            <a href="<?php echo htmlspecialchars($alert['action_url']); ?>" class="alert-btn">
                                <?php echo htmlspecialchars($alert['action_text']); ?>
                            </a>
                            <?php if ($alert['priority'] === 'medium'): ?>
                                <button class="alert-dismiss" onclick="dismissSubscriptionAlert(this, 7)">Ignorer 7 jours</button>
                            <?php endif; ?>
                        </div>
                        <button class="alert-close" onclick="closeSubscriptionAlert(this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <style>
                .subscription-alerts-container {
                    margin-bottom: 24px;
                }
                
                .subscription-alert {
                    display: flex;
                    align-items: center;
                    padding: 16px;
                    border-radius: 12px;
                    margin-bottom: 12px;
                    position: relative;
                    gap: 16px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .alert-danger {
                    background: linear-gradient(135deg, #fee 0%, #fdd 100%);
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                }
                
                .alert-warning {
                    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                    border: 1px solid #ffeaa7;
                    color: #856404;
                }
                
                .alert-info {
                    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
                    border: 1px solid #b8daff;
                    color: #0c5460;
                }
                
                .alert-icon {
                    flex-shrink: 0;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .alert-danger .alert-icon {
                    background: #f8d7da;
                }
                
                .alert-warning .alert-icon {
                    background: #fff3cd;
                }
                
                .alert-info .alert-icon {
                    background: #d1ecf1;
                }
                
                .alert-content {
                    flex: 1;
                }
                
                .alert-content h4 {
                    margin: 0 0 4px 0;
                    font-weight: 600;
                }
                
                .alert-content p {
                    margin: 0;
                    font-size: 14px;
                }
                
                .alert-actions {
                    display: flex;
                    gap: 12px;
                    align-items: center;
                }
                
                .alert-btn {
                    background: #007bff;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                }
                
                .alert-btn:hover {
                    background: #0056b3;
                    transform: translateY(-1px);
                }
                
                .alert-dismiss {
                    background: transparent;
                    border: 1px solid currentColor;
                    color: inherit;
                    padding: 8px 12px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 12px;
                    transition: all 0.3s ease;
                }
                
                .alert-dismiss:hover {
                    background: rgba(255,255,255,0.2);
                }
                
                .alert-close {
                    background: none;
                    border: none;
                    color: inherit;
                    cursor: pointer;
                    padding: 4px;
                    border-radius: 4px;
                    opacity: 0.7;
                }
                
                .alert-close:hover {
                    opacity: 1;
                    background: rgba(0,0,0,0.1);
                }
                
                @media (max-width: 768px) {
                    .subscription-alert {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 12px;
                    }
                    
                    .alert-actions {
                        width: 100%;
                        justify-content: space-between;
                    }
                }
            </style>

            <script>
                function closeSubscriptionAlert(button) {
                    const alert = button.closest('.subscription-alert');
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                    
                    // Stocker en localStorage pour ne pas réafficher pendant 24h
                    const alertType = alert.classList.contains('alert-danger') ? 'danger' : 
                                    alert.classList.contains('alert-warning') ? 'warning' : 'info';
                    localStorage.setItem(`subscriptionAlert_${alertType}_dismissed`, Date.now());
                }
                
                function dismissSubscriptionAlert(button, days) {
                    const alert = button.closest('.subscription-alert');
                    const alertType = alert.classList.contains('alert-warning') ? 'warning' : 'info';
                    
                    // Stocker en localStorage pour ne pas réafficher pendant X jours
                    const dismissUntil = Date.now() + (days * 24 * 60 * 60 * 1000);
                    localStorage.setItem(`subscriptionAlert_${alertType}_dismissed`, dismissUntil);
                    
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }
                
                // Vérifier si les alertes ont été ignorées
                document.addEventListener('DOMContentLoaded', function() {
                    const alerts = document.querySelectorAll('.subscription-alert');
                    alerts.forEach(alert => {
                        const alertType = alert.classList.contains('alert-danger') ? 'danger' : 
                                        alert.classList.contains('alert-warning') ? 'warning' : 'info';
                        
                        const dismissedUntil = localStorage.getItem(`subscriptionAlert_${alertType}_dismissed`);
                        if (dismissedUntil && Date.now() < parseInt(dismissedUntil)) {
                            alert.remove();
                        }
                    });
                });
            </script>

        <?php endif; ?>
        <!-- Top Navigation Bar -->
        <nav class="top-navbar">

            <div class="search-bar">
                <input type="text" id="search-input" placeholder="Rechercher un agent...">
                <button class="search-btn" onclick="searchAgents()">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            
            <div class="top-navbar-right">
                <style>
                    /* Base styles for the notification system */
                    .notifications {
                        position: relative;
                        display: inline-block;
                    }

                    .notification-btn {
                        position: relative;
                        background: none;
                        border: none;
                        cursor: pointer;
                        padding: 8px;
                        border-radius: 6px;
                        transition: background-color 0.2s;
                        color: #6b7280;
                    }

                    .notification-btn:hover {
                        background-color: #f3f4f6;
                        color: #374151;
                    }

                    .notification-badge {
                        position: absolute;
                        top: 2px;
                        right: 2px;
                        background-color: #ef4444;
                        color: white;
                        border-radius: 50%;
                        width: 18px;
                        height: 18px;
                        font-size: 10px;
                        font-weight: bold;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-width: 18px;
                        padding: 0 2px;
                        animation: pulse 2s infinite;
                    }

                    .notification-badge.hidden {
                        display: none;
                    }

                    @keyframes pulse {
                        0% {
                            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
                        }
                        70% {
                            box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
                        }
                        100% {
                            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
                        }
                    }

                    .notification-dropdown {
                        position: absolute;
                        top: 100%;
                        right: 0;
                        background: white;
                        border: 1px solid #e5e7eb;
                        border-radius: 8px;
                        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                        width: 320px;
                        max-height: 400px;
                        overflow-y: auto;
                        z-index: 1000;
                        display: none;
                    }

                    .notification-dropdown.show {
                        display: block;
                        animation: slideIn 0.2s ease-out;
                    }

                    @keyframes slideIn {
                        from {
                            opacity: 0;
                            transform: translateY(-10px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    .notification-header {
                        padding: 12px 16px;
                        border-bottom: 1px solid #e5e7eb;
                        font-weight: 600;
                        color: #374151;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }

                    .mark-all-read {
                        background: none;
                        border: none;
                        color: #3b82f6;
                        font-size: 12px;
                        cursor: pointer;
                        text-decoration: underline;
                    }

                    .notification-list {
                        max-height: 320px;
                        overflow-y: auto;
                    }

                    .notification-item {
                        padding: 12px 16px;
                        border-bottom: 1px solid #f3f4f6;
                        cursor: pointer;
                        transition: background-color 0.2s;
                    }

                    .notification-item:hover {
                        background-color: #f9fafb;
                    }

                    .notification-item.unread {
                        background-color: #eff6ff;
                        border-left: 3px solid #3b82f6;
                    }

                    .notification-item:last-child {
                        border-bottom: none;
                    }

                    .notification-content {
                        display: flex;
                        align-items: flex-start;
                        gap: 12px;
                    }

                    .notification-icon {
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        background-color: #3b82f6;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-size: 14px;
                        flex-shrink: 0;
                    }

                    .notification-details {
                        flex: 1;
                    }

                    .notification-title {
                        font-weight: 600;
                        color: #374151;
                        margin: 0 0 4px 0;
                        font-size: 14px;
                    }

                    .notification-message {
                        color: #6b7280;
                        font-size: 13px;
                        line-height: 1.4;
                        margin: 0 0 4px 0;
                    }

                    .notification-time {
                        color: #9ca3af;
                        font-size: 11px;
                    }

                    .no-notifications {
                        padding: 40px 16px;
                        text-align: center;
                        color: #9ca3af;
                    }

                    .loading {
                        padding: 20px;
                        text-align: center;
                        color: #6b7280;
                    }
                </style>
                
                <div class="notifications">
                    <button class="notification-btn" data-tooltip="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2z"/>
                            <path d="M8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.918zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6z"/>
                        </svg>
                        <span class="notification-badge hidden">0</span>
                    </button>
                    <div class="notification-dropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <button class="mark-all-read">Marquer tout comme lu</button>
                        </div>
                        <div class="notification-list">
                            <div class="loading">Chargement des notifications...</div>
                        </div>
                    </div>
                </div>

                <script src="../assets/js/notifications.js" ></script>

            </div>
        </nav>
        
        <!-- Personnel Content -->
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h2>Gestion des Agents</h2>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openAddAgentModal()">
                        <i class="bi bi-plus-lg"></i> Ajouter un agent
                    </button>
                    <button class="btn btn-secondary" onclick="exportData()">
                        <i class="bi bi-download"></i> Exporter
                    </button>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success mb-4">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger mb-4">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Personnel Filters -->
            <form method="GET" class="filter-row">
                <div class="filter-group">
                    <label for="status-filter">Statut</label>
                    <select id="status-filter" name="status" class="form-select">
                        <option value="">Tous les statuts</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="performance-filter">Performance</label>
                    <select id="performance-filter" name="performance" class="form-select">
                        <option value="">Tous</option>
                        <option value="high" <?php echo $performance_filter === 'high' ? 'selected' : ''; ?>>Haute (>80%)</option>
                        <option value="medium" <?php echo $performance_filter === 'medium' ? 'selected' : ''; ?>>Moyenne (50-80%)</option>
                        <option value="low" <?php echo $performance_filter === 'low' ? 'selected' : ''; ?>>Basse (<50%)</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="date-from">Date de début</label>
                    <input type="date" id="date-from" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date-to">Date de fin</label>
                    <input type="date" id="date-to" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Appliquer
                </button>
                <a href="personnel.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                </a>
            </form>
            
            <!-- Personnel Statistics -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Agents Totaux</h3>
                        <i class="bi bi-people-fill text-primary"></i>
                    </div>
                    <div class="stat-card-value"><?php echo $stats[0]['total_agents']; ?></div>
                    <div class="stat-card-change">
                        <i class="bi bi-arrow-up"></i> <?php echo round(($stats[0]['new_this_month'] / max(1, $stats[0]['total_agents'] - $stats[0]['new_this_month'])) * 100, 1); ?>% ce mois
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Confirmations Totales</h3>
                        <i class="bi bi-check-circle-fill text-success"></i>
                    </div>
                    <div class="stat-card-value"><?php echo $confirmations_stats[0]['total_confirmations'] ?? 0; ?></div>
                    <div class="stat-card-change">
                        <?php 
                        $monthly_confirmations = array_sum(array_column($agents, 'monthly_confirmations'));
                        $prev_month_confirmations = max(1, ($confirmations_stats[0]['total_confirmations'] ?? 1) - $monthly_confirmations);
                        $change = (($monthly_confirmations - $prev_month_confirmations) / $prev_month_confirmations) * 100;
                        ?>
                        <i class="bi bi-arrow-<?php echo $change >= 0 ? 'up' : 'down'; ?>"></i> 
                        <?php echo abs(round($change, 1)); ?>% vs mois dernier
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Performance Moyenne</h3>
                        <i class="bi bi-graph-up-arrow text-info"></i>
                    </div>
                    <div class="stat-card-value"><?php echo $avg_performance; ?>%</div>
                    <div class="stat-card-change <?php echo ($avg_monthly_performance - $avg_performance) >= 0 ? '' : 'negative'; ?>">
                        <i class="bi bi-arrow-<?php echo ($avg_monthly_performance - $avg_performance) >= 0 ? 'up' : 'down'; ?>"></i> 
                        <?php echo abs(round($avg_monthly_performance - $avg_performance, 1)); ?>% ce mois
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Nouveaux Agents</h3>
                        <i class="bi bi-person-plus-fill text-warning"></i>
                    </div>
                    <div class="stat-card-value"><?php echo $stats[0]['new_this_month']; ?></div>
                    <div class="stat-card-change">
                        <?php 
                        $new_last_month = $db->getThisQuery("SELECT COUNT(*) as count FROM agents WHERE DATE(created_at) BETWEEN DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 1 MONTH) AND DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")[0]['count'] ?? 0;
                        $change = (($stats[0]['new_this_month'] - $new_last_month) / max(1, $new_last_month)) * 100;
                        ?>
                        <i class="bi bi-arrow-<?php echo $change >= 0 ? 'up' : 'down'; ?>"></i> 
                        <?php echo abs(round($change, 1)); ?>% vs mois dernier
                    </div>
                </div>
            </div>

            <style>
                .top-performers {
                    margin: 0 auto;
                    padding: 20px 0px;
                    text-align: left;
                    margin-bottom: 20px;
                }

                .top-performers h3 {
                    font-size: 1.5rem;
                    color: #2c3e50;
                    margin-bottom: 20px;
                    font-weight: 600;
                }

                .performers-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 10px;
                    max-width: fit-content;
                }

                /* When there's only one card, limit its width */
                .performers-grid:has(.performer-card:only-child) {
                    max-width: 320px;
                }

                .performer-card {
                    background: #fff;
                    border-radius: 12px;
                    padding: 20px;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    cursor: pointer;
                    transition: all 0.3s ease;
                    border: 1px solid #e1e8ed;
                    width: 300px;
                }

                .performer-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
                }

                .performer-header {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 16px;
                }

                .performer-avatar {
                    width: 45px;
                    height: 45px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: 600;
                    font-size: 0.9rem;
                    flex-shrink: 0;
                }

                .performer-info h4 {
                    margin: 0;
                    font-size: 1rem;
                    color: #2c3e50;
                    font-weight: 600;
                    line-height: 1.2;
                }

                .performer-info p {
                    margin: 4px 0 0 0;
                    font-size: 0.75rem;
                    color: #7f8c8d;
                    font-weight: 400;
                }

                .performer-stats {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 12px;
                    margin-bottom: 12px;
                }

                .performer-stat {
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                }

                .stat-label {
                    font-size: 0.7rem;
                    color: #95a5a6;
                    font-weight: 500;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .stat-value {
                    font-size: 0.85rem;
                    color: #2c3e50;
                    font-weight: 600;
                }

                .performance-bar {
                    grid-column: 1 / -1;
                    height: 6px;
                    background-color: #ecf0f1;
                    border-radius: 3px;
                    overflow: hidden;
                    margin-top: 8px;
                }

                .performance-fill {
                    height: 100%;
                    background: linear-gradient(90deg, #2ecc71, #27ae60);
                    border-radius: 3px;
                    transition: width 0.3s ease;
                }

                /* Responsive adjustments */
                @media (max-width: 768px) {
                    .top-performers {
                        padding: 15px;
                    }
                    
                    .performers-grid {
                        grid-template-columns: 1fr;
                        max-width: 320px;
                    }
                    
                    .top-performers h3 {
                        font-size: 1.3rem;
                    }
                    
                    .performer-card {
                        padding: 16px;
                    }
                }

                @media (max-width: 480px) {
                    .performer-card {
                        max-width: 100%;
                    }
                    
                    .performer-avatar {
                        width: 40px;
                        height: 40px;
                        font-size: 0.8rem;
                    }
                    
                    .performer-info h4 {
                        font-size: 0.9rem;
                    }
                    
                    .performer-info p {
                        font-size: 0.7rem;
                    }
                    
                    .stat-value {
                        font-size: 0.8rem;
                    }
                }
            </style>
            
            <!-- Top Performers -->
            <div class="top-performers">
                <h3>Top Performers du Mois</h3>
                <div class="performers-grid">
                    <?php 
                    $top_performers = array_slice($agents, 0, 4);
                    foreach ($top_performers as $agent): 
                        $initials = implode('', array_map(function($name) { 
                            return strtoupper(substr(trim($name), 0, 1)); 
                        }, explode(' ', $agent['name'])));
                    ?>
                    <div class="performer-card" onclick="viewAgent(<?php echo $agent['id']; ?>)">
                        <div class="performer-header">
                            <div class="performer-avatar"><?php echo $initials; ?></div>
                            <div class="performer-info">
                                <h4><?php echo htmlspecialchars($agent['name']); ?></h4>
                                <p>Agent depuis <?php echo date('Y', strtotime($agent['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="performer-stats">
                            <div class="performer-stat">
                                <span class="stat-label">Confirmations</span>
                                <span class="stat-value"><?php echo $agent['monthly_confirmations']; ?></span>
                            </div>
                            <div class="performer-stat">
                                <span class="stat-label">Montant</span>
                                <span class="stat-value"><?php echo number_format($agent['monthly_amount'], 2, ',', ' '); ?> MAD</span>
                            </div>
                            <div class="performer-stat">
                                <span class="stat-label">Performance</span>
                                <span class="stat-value"><?php echo $agent['monthly_performance']; ?>%</span>
                            </div>
                            <div class="performance-bar">
                                <div class="performance-fill" style="width: <?php echo $agent['monthly_performance']; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- All Agents Table -->
            <h3 style="font-size: 1.4em; margin-top: 1.8em">Tous les Agents</h3>
            <div class="table-container">
                <table class="clients-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Contact</th>
                            <th>Confirmations</th>
                            <th>Performance</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="agents-table-body">
                        <?php foreach ($agents as $agent): 
                            $initials = implode('', array_map(function($name) { 
                                return strtoupper(substr(trim($name), 0, 1)); 
                            }, explode(' ', $agent['name'])));
                        ?>
                        <tr>
                            <td><?php echo str_pad($agent['id'], 3, '0', STR_PAD_LEFT); ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div class="performer-avatar" style="width: 36px; height: 36px; font-size: 0.875rem;"><?php echo $initials; ?></div>
                                    <div>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($agent['name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #6c757d;"><?php echo date('d/m/Y', strtotime($agent['created_at'])); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($agent['email']); ?></div>
                                <div style="font-size: 0.75rem; color: #6c757d;"><?php echo htmlspecialchars($agent['phone'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo $agent['monthly_confirmations']; ?> <span style="font-size: 0.75rem; color: #6c757d;">(<?php echo $agent['total_confirmations']; ?> total)</span></div>
                                <div style="font-size: 0.75rem; color: #6c757d;"><?php echo number_format($agent['monthly_amount'], 2, ',', ' '); ?> MAD</div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-weight: 500;"><?php echo $agent['monthly_performance']; ?>%</span>
                                    <div class="performance-bar" style="flex-grow: 1; height: 4px;">
                                        <div class="performance-fill" style="width: <?php echo $agent['monthly_performance']; ?>%"></div>
                                    </div>
                                </div>
                                <div style="font-size: 0.75rem; color: #6c757d;"><?php echo $agent['performance_percentage']; ?>% globale</div>
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
                                    <button class="btn-icon btn-danger" onclick="deleteAgent(<?php echo $agent['id']; ?>)" title="Supprimer">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Add/Edit Agent Modal -->
    <div class="modal" id="agent-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Ajouter un nouvel agent</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="agent-form">
                    <input type="hidden" id="agent-id" name="id">
                    
                    <div class="form-group">
                        <label for="agent-name">Nom complet *</label>
                        <input type="text" id="agent-name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="agent-email">Email *</label>
                        <input type="email" id="agent-email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="agent-password">Mot de passe *</label>
                        <input type="password" id="agent-password" name="password" required minlength="6">
                        <small class="form-text text-muted" id="password_text">Le mot de passe doit contenir au moins 6 caractères</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="agent-phone">Téléphone</label>
                        <input type="tel" id="agent-phone" name="phone">
                    </div>

                    <div class="form-group">
                        <label for="agent-price">Prix par confirmation</label>
                        <input type="number" id="agent-price" name="price" step="0.01" min="0" placeholder="0.00">
                        <small class="form-text text-muted">Montant que l'agent reçoit pour chaque confirmation (optionnel)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="agent-status">Statut *</label>
                        <select id="agent-status" name="is_active" required>
                            <option value="1">Actif</option>
                            <option value="0">Inactif</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Agent Details Modal -->
    <div class="modal" id="view-agent-modal">
        <div class="modal-content modal-scroll" style="width: 70%; max-width: 800px;">
            <div class="modal-header">
                <h3>Détails de l'agent</h3>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="agent-details">
                <!-- Agent details will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript -->
    <script>
        // Search functionality
        function searchAgents() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const tableBody = document.getElementById('agents-table-body');
            const rows = tableBody.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const name = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                
                if (name.includes(searchTerm) || email.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        }

        // Real-time search
        document.getElementById('search-input').addEventListener('input', searchAgents);

        // Modal functions
        function openAddAgentModal() {
            document.getElementById('modal-title').textContent = 'Ajouter un nouvel agent';
            document.getElementById('password_text').textContent = 'Le mot de passe doit contenir au moins 6 caractères';
            document.getElementById('agent-form').reset();
            document.getElementById('agent-id').value = '';
            document.getElementById('agent-password').setAttribute('required', 'required');
            document.getElementById('agent-password').placeholder = '';
            document.getElementById('agent-price').value = '';
            document.getElementById('agent-modal').style.display = 'block';
        }

        function editAgent(id) {
            // Fetch agent data via AJAX
            fetch(`./controllers/get_agentApi.php?id=${id}&include_password=true`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const agent = data.agent;
                        
                        // Update modal title and form for editing
                        document.getElementById('modal-title').textContent = 'Modifier l\'agent';
                        document.getElementById('agent-id').value = agent.id;
                        document.getElementById('agent-name').value = agent.name;
                        document.getElementById('agent-email').value = agent.email;
                        document.getElementById('agent-phone').value = agent.phone || '';
                                            document.getElementById('agent-price').value = agent.service_fee || '';
                        document.getElementById('agent-status').value = agent.is_active;
                        
                        // Update password field for editing mode
                        const passwordField = document.getElementById('agent-password');
                        const passwordText = document.getElementById('password_text');
                        
                        if (passwordField && passwordText) {
                            // Make password not required for editing
                            passwordField.removeAttribute('required');
                            passwordField.value = ''; // Clear any existing value
                            passwordField.placeholder = 'Laisser vide pour ne pas modifier';
                            
                            // Update help text
                            passwordText.textContent = 'Laisser vide pour ne pas modifier le mot de passe';
                        }
                        
                        document.getElementById('agent-modal').style.display = 'block';
                    } else {
                        alert('Erreur lors du chargement des données de l\'agent');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors du chargement des données de l\'agent');
                });
        }

        function viewAgent(id) {
            // Fetch detailed agent data via AJAX
            fetch(`./controllers/get_agent_detailsApi.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const agent = data.agent;
                        const confirmations = data.confirmations || [];
                        const performanceData = data.performance_data || [];
                        
                        // Generate initials for avatar
                        const initials = agent.name.split(' ').map(name => name[0]).join('').toUpperCase();
                        
                        // Prepare performance chart data
                        const months = [];
                        const performance = [];
                        
                        performanceData.forEach(item => {
                            months.push(new Date(item.year, item.month - 1).toLocaleDateString('fr-FR', { month: 'short' }));
                            performance.push(item.performance);
                        });
                        
                        let detailsHtml = `
                            <div class="agent-info-grid">
                                <div class="info-section">
                                    <h4>Informations Personnelles</h4>
                                    <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem;">
                                        <div class="performer-avatar" style="width: 60px; height: 60px; font-size: 1.5rem;">${initials}</div>
                                        <div>
                                            <h3 style="margin: 0 0 0.25rem 0;">${agent.name}</h3>
                                            <p style="margin: 0; color: #6c757d;">Agent depuis ${new Date(agent.created_at).toLocaleDateString('fr-FR')}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="info-row">
                                        <span class="info-label">Adresse email</span>
                                        <span class="info-value">${agent.email}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Téléphone</span>
                                        <span class="info-value">${agent.phone || 'N/A'}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Statut</span>
                                        <span class="info-value">
                                            <span class="status-badge ${agent.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                                ${agent.is_active == 1 ? 'Actif' : 'Inactif'}
                                            </span>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="info-section">
                                    <h4>Statistiques de Performance</h4>
                                    <div class="stats-grid">
                                        <div class="stat-card">
                                            <div class="stat-card-value">${agent.statistics.total_orders}</div>
                                            <div class="stat-label">Total Commandes</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-card-value">${agent.statistics.confirmed_orders}</div>
                                            <div class="stat-label">Confirmées</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-card-value">${agent.statistics.canceled_orders}</div>
                                            <div class="stat-label">Annulées</div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-row" style="margin-top: 1.5rem;">
                                        <span class="info-label">Performance Ce Mois</span>
                                        <span class="info-value">${agent.statistics.monthly_performance}%</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Performance Globale</span>
                                        <span class="info-value">${agent.statistics.performance_percentage}%</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Montant Confirmé</span>
                                        <span class="info-value">${parseFloat(agent.statistics.confirmed_amount || 0).toLocaleString('fr-FR', {style: 'currency', currency: 'MAD'})}</span>
                                    </div>
                                    
                                    <div class="performance-chart">
                                        <canvas id="performance-chart"></canvas>
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        if (confirmations.length > 0) {
                            detailsHtml += `
                                <div class="info-section">
                                    <h4>Dernières Confirmations</h4>
                                    <div class="confirmations-list">
                                        ${confirmations.slice(0, 5).map(conf => `
                                            <div class="confirmation-item">
                                                <div class="confirmation-header">
                                                    <span class="confirmation-customer">${conf.customer_name || 'N/A'}</span>
                                                    <span class="confirmation-amount">${parseFloat(conf.amount).toLocaleString('fr-FR', {style: 'currency', currency: 'MAD'})}</span>
                                                </div>
                                                <div class="confirmation-meta">
                                                    <span>${conf.status_label}</span>
                                                    <span>${new Date(conf.created_at).toLocaleDateString('fr-FR')}</span>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            `;
                        }
                        
                        document.getElementById('agent-details').innerHTML = detailsHtml;
                        
                        // Initialize performance chart
                        if (performanceData.length > 0) {
                            const ctx = document.getElementById('performance-chart').getContext('2d');
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: months,
                                    datasets: [{
                                        label: 'Performance (%)',
                                        data: performance,
                                        borderColor: '#4361ee',
                                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                                        tension: 0.3,
                                        fill: true
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return context.parsed.y + '%';
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: 100,
                                            ticks: {
                                                callback: function(value) {
                                                    return value + '%';
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }
                        
                        document.getElementById('view-agent-modal').style.display = 'block';
                    } else {
                        alert('Erreur lors du chargement des détails de l\'agent');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors du chargement des détails de l\'agent');
                });
        }

        function deleteAgent(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet agent? Cette action est irréversible.')) {
                fetch('./controllers/delete_agentApi.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({id: id})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors de la suppression de l\'agent');
                });
            }
        }

        function closeModal() {
            document.getElementById('agent-modal').style.display = 'none';
        }

        function closeViewModal() {
            document.getElementById('view-agent-modal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('agent-modal');
            const viewModal = document.getElementById('view-agent-modal');
            if (event.target === modal) {
                closeModal();
            }
            if (event.target === viewModal) {
                closeViewModal();
            }
        }

        // Form submission
        document.getElementById('agent-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            const agentId = document.getElementById('agent-id').value;
            const url = agentId ? './controllers/update_agentApi.php' : './controllers/add_agentApi.php';
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enregistrement...';
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(data);
                    closeModal();
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Enregistrer';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors de l\'enregistrement');
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Enregistrer';
            });
        });

        // Export functionality
        function exportData() {
            const filters = new URLSearchParams(window.location.search);
            window.open(`../controllers/export_agentsApi.php?${filters.toString()}`, '_blank');
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modals
            if (e.key === 'Escape') {
                closeModal();
                closeViewModal();
            }
            
            // Ctrl+N to add new agent
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddAgentModal();
            }
            
            // Ctrl+F to focus search
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.getElementById('search-input').focus();
            }
        });

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            // Only refresh if no modal is open
            if (document.getElementById('agent-modal').style.display === 'none' && 
                document.getElementById('view-agent-modal').style.display === 'none') {
                location.reload();
            }
        }, 300000); // 5 minutes

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Add hover effects to cards
            const cards = document.querySelectorAll('.stat-card, .performer-card');
            cards.forEach(card => {
                card.style.transition = 'all 0.3s ease';
            });
        });
    </script>
</body>
</html>