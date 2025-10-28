<?php
// Database connection and session check for super admin
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if user is logged in and is super admin
if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../../lg'); 
} 

$isLoggedIn = isset($_SESSION['user']);
$user = $db->getThisQuery("SELECT id, `name`, `role`, is_verified, email FROM users WHERE email = ?", [$_SESSION['user']['username']]);

if (!$user || empty($user[0]['id'])) {
    logout(); 
}

if (isset($_GET['logout'])) {
    logout();
}

// Check if user is super admin
if ($user[0]['role'] !== 'super') {
    Redirect::to('../home');
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../../lg');
}

// Get filter parameters
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'this_month';
$platform_filter = isset($_GET['platform_filter']) ? $_GET['platform_filter'] : '';

// Calculate date range for analytics
switch ($date_range) {
    case 'today':
        $date_condition = "DATE(s.created_at) = CURDATE()";
        $order_date_condition = "DATE(o.created_at) = CURDATE()";
        break;
    case 'this_week':
        $date_condition = "YEARWEEK(s.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $order_date_condition = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'this_month':
        $date_condition = "MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
        $order_date_condition = "MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
        break;
    case 'last_month':
        $date_condition = "MONTH(s.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(s.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        $order_date_condition = "MONTH(o.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(o.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
    case 'this_year':
        $date_condition = "YEAR(s.created_at) = YEAR(CURDATE())";
        $order_date_condition = "YEAR(o.created_at) = YEAR(CURDATE())";
        break;
    default:
        $date_condition = "1=1";
        $order_date_condition = "1=1";
}

// Platform filter condition
$platform_condition = "";
if (!empty($platform_filter)) {
    $platform_condition = "AND s.platform = '$platform_filter'";
}

// Overall Store Statistics
$store_stats_query = "
    SELECT 
        COUNT(*) as total_stores,
        SUM(CASE WHEN s.is_connected = 1 THEN 1 ELSE 0 END) as active_stores,
        SUM(CASE WHEN s.is_connected = 0 THEN 1 ELSE 0 END) as inactive_stores,
        COUNT(DISTINCT s.user_id) as unique_users,
        COALESCE(
            (SELECT COUNT(*) FROM orders o WHERE o.store_id = s.id AND $order_date_condition),
            0
        ) as recent_orders
    FROM stores s
    WHERE $date_condition $platform_condition
";

$store_stats_result = $db->getThisQuery($store_stats_query);
$store_stats = !empty($store_stats_result) ? $store_stats_result[0] : [
    'total_stores' => 0,
    'active_stores' => 0,
    'inactive_stores' => 0,
    'unique_users' => 0,
    'recent_orders' => 0
];

// Platform Distribution
$platform_distribution_query = "
    SELECT 
        COALESCE(s.platform, 'Unknown') as platform,
        COUNT(*) as store_count,
        SUM(CASE WHEN s.is_connected = 1 THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN s.is_connected = 0 THEN 1 ELSE 0 END) as inactive_count,
        COUNT(DISTINCT s.user_id) as user_count,
        (SELECT COUNT(*) FROM orders o WHERE o.platform = s.platform AND $order_date_condition) as recent_orders,
        (SELECT COUNT(*) FROM orders o WHERE o.platform = s.platform) as total_orders
    FROM stores s
    WHERE 1=1 $platform_condition
    GROUP BY s.platform
    ORDER BY store_count DESC
";

$platform_distribution = $db->getThisQuery($platform_distribution_query);

// Monthly Store Growth
$monthly_growth_query = "
    SELECT 
        DATE_FORMAT(s.created_at, '%Y-%m') as month,
        COUNT(*) as new_stores,
        COUNT(DISTINCT s.user_id) as new_users
    FROM stores s
    WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(s.created_at, '%Y-%m')
    ORDER BY month ASC
";

$monthly_growth = $db->getThisQuery($monthly_growth_query);

// Monthly Order Growth
$monthly_orders_query = "
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        COUNT(*) as order_count,
        COALESCE(SUM(o.total_amount), 0) as order_value
    FROM orders o
    WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month ASC
";

$monthly_orders = $db->getThisQuery($monthly_orders_query);

// Most Active Stores (by order count)
$active_stores_query = "
    SELECT 
        s.id,
        s.storeName,
        s.platform,
        s.is_connected,
        s.connected_at,
        u.name as user_name,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total_amount), 0) as total_revenue
    FROM stores s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN orders o ON s.id = o.store_id
    WHERE 1=1 $platform_condition
    GROUP BY s.id, s.storeName, s.platform, s.is_connected, s.connected_at, u.name
    ORDER BY order_count DESC
    LIMIT 10
";

$active_stores = $db->getThisQuery($active_stores_query);

// Least Active Stores (by order count)
$inactive_stores_query = "
    SELECT 
        s.id,
        s.storeName,
        s.platform,
        s.is_connected,
        s.connected_at,
        u.name as user_name,
        COUNT(o.id) as order_count,
        COALESCE(SUM(o.total_amount), 0) as total_revenue
    FROM stores s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN orders o ON s.id = o.store_id
    WHERE 1=1 $platform_condition
    GROUP BY s.id, s.storeName, s.platform, s.is_connected, s.connected_at, u.name
    HAVING order_count <= 5 OR order_count IS NULL
    ORDER BY order_count ASC
    LIMIT 10
";

$inactive_stores = $db->getThisQuery($inactive_stores_query);

// Recent Store Connections
$recent_connections_query = "
    SELECT 
        s.*,
        u.name as user_name,
        u.email
    FROM stores s
    JOIN users u ON s.user_id = u.id
    WHERE s.is_connected = 1
    ORDER BY s.connected_at DESC
    LIMIT 10
";

$recent_connections = $db->getThisQuery($recent_connections_query);

function formatCurrency($amount) {
    $amount = is_null($amount) ? 0 : $amount;
    return number_format((float)$amount, 2, ',', ' ') . ' D.H';
}

function formatPercentage($value) {
    return round((float)$value, 1) . '%';
}

function getPlatformColor($platform) {
    $colors = [
        'youcan' => '#3b82f6',
        'shopify' => '#96bf48',
        'woocommerce' => '#96588a'
    ];
    return $colors[strtolower($platform)] ?? '#6b7280';
}

function getPlatformIcon($platform) {
    $icons = [
        'youcan' => 'bi-shop',
        'shopify' => 'bi-cart',
        'woocommerce' => 'bi-basket'
    ];
    return $icons[strtolower($platform)] ?? 'bi-question-circle';
}

function getStatusBadge($is_connected) {
    if ($is_connected) {
        return '<span class="badge badge-success">● Connecté</span>';
    } else {
        return '<span class="badge badge-danger">● Déconnecté</span>';
    }
}

function getActivityLevel($order_count) {
    if ($order_count >= 100) {
        return '<span class="badge badge-success">Très Actif</span>';
    } elseif ($order_count >= 50) {
        return '<span class="badge badge-primary">Actif</span>';
    } elseif ($order_count >= 10) {
        return '<span class="badge badge-warning">Modéré</span>';
    } else {
        return '<span class="badge badge-danger">Peu Actif</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics des Stores | Super Admin OBG</title>
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --card-shadow-hover: 0 20px 45px -5px rgba(0, 0, 0, 0.15), 0 10px 18px -5px rgba(0, 0, 0, 0.1);
        }
        
        .stores-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        
        .page-title {
            font-size: 2.25rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .filters-container {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #4b5563;
        }
        
        .form-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            background: #fafafa;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .stat-card:nth-child(2)::before { background: var(--success-gradient); }
        .stat-card:nth-child(3)::before { background: var(--warning-gradient); }
        .stat-card:nth-child(4)::before { background: var(--info-gradient); }
        .stat-card:nth-child(5)::before { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
            background: var(--primary-gradient);
        }
        
        .stat-card:nth-child(2) .stat-icon { background: var(--success-gradient); }
        .stat-card:nth-child(3) .stat-icon { background: var(--warning-gradient); }
        .stat-card:nth-child(4) .stat-icon { background: var(--info-gradient); }
        .stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        
        .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .chart-container {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: var(--card-shadow);
            border: none;
            transition: all 0.3s ease;
        }
        
        .chart-container:hover {
            box-shadow: var(--card-shadow-hover);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .chart-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        
        .chart-canvas {
            height: 320px;
            position: relative;
        }
        
        .platform-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .platform-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: var(--card-shadow);
            border-left: 4px solid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .platform-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .platform-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .platform-name {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.125rem;
            color: #1f2937;
        }
        
        .platform-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        
        .platform-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .platform-stat {
            text-align: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 0.75rem;
        }
        
        .platform-stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1f2937;
            line-height: 1;
        }
        
        .platform-stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 0.25rem;
        }
        
        .progress {
            height: 8px;
            background: #f3f4f6;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.75rem 0;
        }
        
        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.8s ease;
        }
        
        .platform-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        
        .tabs-container {
            background: white;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2.5rem;
            overflow: hidden;
        }
        
        .tabs-header {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        
        .tab {
            padding: 1.25rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab:hover {
            color: #374151;
            background: #f1f5f9;
        }
        
        .tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
            background: white;
        }
        
        .tab-content {
            padding: 0;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .stores-list {
            padding: 1.5rem;
        }
        
        .store-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s ease;
        }
        
        .store-item:hover {
            background: #f8fafc;
            border-radius: 0.75rem;
        }
        
        .store-item:last-child {
            border-bottom: none;
        }
        
        .store-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex: 1;
        }
        
        .store-name {
            font-weight: 700;
            color: #1f2937;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .store-platform {
            color: #6b7280;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .store-user {
            font-size: 0.9rem;
            color: #4b5563;
        }
        
        .store-stats {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }
        
        .store-orders {
            font-weight: 800;
            color: #10b981;
            font-size: 1.25rem;
        }
        
        .store-revenue {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .store-date {
            font-size: 0.8rem;
            color: #9ca3af;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state p {
            font-size: 1.125rem;
            margin-bottom: 1.5rem;
        }
        
        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.4);
        }
        
        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .platform-cards {
                grid-template-columns: 1fr;
            }
            
            .platform-stats {
                grid-template-columns: 1fr;
            }
            
            .tabs-header {
                flex-direction: column;
            }
            
            .tab {
                text-align: left;
                border-bottom: 1px solid #e5e7eb;
                border-left: 3px solid transparent;
                justify-content: flex-start;
            }
            
            .tab.active {
                border-left-color: #3b82f6;
                border-bottom-color: #e5e7eb;
            }
            
            .store-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .store-stats {
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'stores';
    require_once('../../assets/sidebarSuper.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <div class="navbar-title">
                <h1>
                    <i class="bi bi-shop"></i>
                    Analytics des Stores
                </h1>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Header -->
            <div class="stores-header">
                <h2 class="page-title">Performance des Stores E-commerce</h2>
                <div class="filters-container">
                    <div class="filter-group">
                        <label class="filter-label">Période</label>
                        <select class="form-select" id="dateRangeFilter" onchange="applyFilters()">
                            <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                            <option value="this_week" <?php echo $date_range == 'this_week' ? 'selected' : ''; ?>>Cette semaine</option>
                            <option value="this_month" <?php echo $date_range == 'this_month' ? 'selected' : ''; ?>>Ce mois</option>
                            <option value="last_month" <?php echo $date_range == 'last_month' ? 'selected' : ''; ?>>Le mois dernier</option>
                            <option value="this_year" <?php echo $date_range == 'this_year' ? 'selected' : ''; ?>>Cette année</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Plateforme</label>
                        <select class="form-select" id="platformFilter" onchange="applyFilters()">
                            <option value="">Toutes les plateformes</option>
                            <option value="youcan" <?php echo $platform_filter == 'youcan' ? 'selected' : ''; ?>>YouCan</option>
                            <option value="shopify" <?php echo $platform_filter == 'shopify' ? 'selected' : ''; ?>>Shopify</option>
                            <option value="woocommerce" <?php echo $platform_filter == 'woocommerce' ? 'selected' : ''; ?>>WooCommerce</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-shop"></i>
                    </div>
                    <div class="stat-value"><?php echo $store_stats['total_stores']; ?></div>
                    <div class="stat-label">Total Stores</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $store_stats['active_stores']; ?></div>
                    <div class="stat-label">Stores Actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $store_stats['inactive_stores']; ?></div>
                    <div class="stat-label">Stores Inactifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-value"><?php echo $store_stats['unique_users']; ?></div>
                    <div class="stat-label">Utilisateurs Uniques</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-cart"></i>
                    </div>
                    <div class="stat-value"><?php echo $store_stats['recent_orders']; ?></div>
                    <div class="stat-label">Commandes Récentes</div>
                </div>
            </div>
            
            <!-- Platform Distribution Cards -->
            <div class="platform-cards">
                <?php foreach ($platform_distribution as $platform): ?>
                    <div class="platform-card" style="border-left-color: <?php echo getPlatformColor($platform['platform']); ?>">
                        <div class="platform-header">
                            <div class="platform-name">
                                <div class="platform-icon" style="background: <?php echo getPlatformColor($platform['platform']); ?>">
                                    <i class="bi <?php echo getPlatformIcon($platform['platform']); ?>"></i>
                                </div>
                                <?php echo ucfirst($platform['platform']); ?>
                            </div>
                            <div class="platform-users"><?php echo $platform['user_count']; ?> utilisateurs</div>
                        </div>
                        
                        <div class="platform-stats">
                            <div class="platform-stat">
                                <div class="platform-stat-value"><?php echo $platform['store_count']; ?></div>
                                <div class="platform-stat-label">Stores</div>
                            </div>
                            <div class="platform-stat">
                                <div class="platform-stat-value"><?php echo $platform['active_count']; ?></div>
                                <div class="platform-stat-label">Actifs</div>
                            </div>
                            <div class="platform-stat">
                                <div class="platform-stat-value"><?php echo $platform['inactive_count']; ?></div>
                                <div class="platform-stat-label">Inactifs</div>
                            </div>
                            <div class="platform-stat">
                                <div class="platform-stat-value"><?php echo $platform['total_orders']; ?></div>
                                <div class="platform-stat-label">Commandes</div>
                            </div>
                        </div>
                        
                        <div class="progress">
                            <div class="progress-bar" 
                                 style="width: <?php echo ($platform['store_count'] / max(1, $store_stats['total_stores'])) * 100; ?>%; 
                                        background: <?php echo getPlatformColor($platform['platform']); ?>">
                            </div>
                        </div>
                        
                        <div class="platform-meta">
                            <span><?php echo formatPercentage(($platform['store_count'] / max(1, $store_stats['total_stores'])) * 100); ?> des stores</span>
                            <span><?php echo $platform['recent_orders']; ?> commandes récentes</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Évolution des Stores</h3>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="storesChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Évolution des Commandes</h3>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tabs Container -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab active" onclick="switchTab('active')">
                        <i class="bi bi-graph-up-arrow"></i>
                        Stores les Plus Actifs
                    </button>
                    <button class="tab" onclick="switchTab('inactive')">
                        <i class="bi bi-graph-down-arrow"></i>
                        Stores les Moins Actifs
                    </button>
                    <button class="tab" onclick="switchTab('recent')">
                        <i class="bi bi-clock-history"></i>
                        Connexions Récentes
                    </button>
                </div>
                
                <!-- Active Stores Tab -->
                <div id="active-tab" class="tab-content active">
                    <div class="stores-list">
                        <?php if (!empty($active_stores)): ?>
                            <?php foreach ($active_stores as $store): ?>
                                <div class="store-item">
                                    <div class="store-info">
                                        <div class="store-name">
                                            <?php echo htmlspecialchars($store['storeName']); ?>
                                            <?php echo getStatusBadge($store['is_connected']); ?>
                                            <?php echo getActivityLevel($store['order_count']); ?>
                                        </div>
                                        <div class="store-platform">
                                            <i class="bi <?php echo getPlatformIcon($store['platform']); ?>" 
                                               style="color: <?php echo getPlatformColor($store['platform']); ?>"></i>
                                            <?php echo ucfirst($store['platform']); ?>
                                        </div>
                                        <div class="store-user">
                                            <i class="bi bi-person"></i>
                                            <?php echo htmlspecialchars($store['user_name']); ?>
                                        </div>
                                        <div class="store-date">
                                            <i class="bi bi-calendar"></i>
                                            Connecté: <?php echo $store['connected_at'] ? date('d/m/Y', strtotime($store['connected_at'])) : 'Non connecté'; ?>
                                        </div>
                                    </div>
                                    <div class="store-stats">
                                        <div class="store-orders">
                                            <?php echo $store['order_count']; ?> commandes
                                        </div>
                                        <div class="store-revenue">
                                            <?php echo formatCurrency($store['total_revenue']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-shop"></i>
                                <p>Aucun store actif trouvé</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Inactive Stores Tab -->
                <div id="inactive-tab" class="tab-content">
                    <div class="stores-list">
                        <?php if (!empty($inactive_stores)): ?>
                            <?php foreach ($inactive_stores as $store): ?>
                                <div class="store-item">
                                    <div class="store-info">
                                        <div class="store-name">
                                            <?php echo htmlspecialchars($store['storeName']); ?>
                                            <?php echo getStatusBadge($store['is_connected']); ?>
                                            <?php echo getActivityLevel($store['order_count']); ?>
                                        </div>
                                        <div class="store-platform">
                                            <i class="bi <?php echo getPlatformIcon($store['platform']); ?>" 
                                               style="color: <?php echo getPlatformColor($store['platform']); ?>"></i>
                                            <?php echo ucfirst($store['platform']); ?>
                                        </div>
                                        <div class="store-user">
                                            <i class="bi bi-person"></i>
                                            <?php echo htmlspecialchars($store['user_name']); ?>
                                        </div>
                                        <div class="store-date">
                                            <i class="bi bi-calendar"></i>
                                            Connecté: <?php echo $store['connected_at'] ? date('d/m/Y', strtotime($store['connected_at'])) : 'Non connecté'; ?>
                                        </div>
                                    </div>
                                    <div class="store-stats">
                                        <div class="store-orders">
                                            <?php echo $store['order_count'] ?? 0; ?> commandes
                                        </div>
                                        <div class="store-revenue">
                                            <?php echo formatCurrency($store['total_revenue']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-shop"></i>
                                <p>Aucun store inactif trouvé</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Connections Tab -->
                <div id="recent-tab" class="tab-content">
                    <div class="stores-list">
                        <?php if (!empty($recent_connections)): ?>
                            <?php foreach ($recent_connections as $store): ?>
                                <div class="store-item">
                                    <div class="store-info">
                                        <div class="store-name">
                                            <?php echo htmlspecialchars($store['storeName']); ?>
                                            <?php echo getStatusBadge($store['is_connected']); ?>
                                        </div>
                                        <div class="store-platform">
                                            <i class="bi <?php echo getPlatformIcon($store['platform']); ?>" 
                                               style="color: <?php echo getPlatformColor($store['platform']); ?>"></i>
                                            <?php echo ucfirst($store['platform']); ?>
                                        </div>
                                        <div class="store-user">
                                            <i class="bi bi-person"></i>
                                            <?php echo htmlspecialchars($store['user_name']); ?>
                                        </div>
                                        <div class="store-date">
                                            <i class="bi bi-calendar"></i>
                                            Connecté: <?php echo $store['connected_at'] ? date('d/m/Y H:i', strtotime($store['connected_at'])) : 'Non connecté'; ?>
                                        </div>
                                    </div>
                                    <div class="store-stats">
                                        <div class="store-orders">
                                            <i class="bi bi-check-lg" style="color: #10b981;"></i>
                                            Connecté
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-wifi"></i>
                                <p>Aucune connexion récente</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Chart data from PHP
        const monthlyGrowthData = <?php echo json_encode($monthly_growth); ?>;
        const monthlyOrdersData = <?php echo json_encode($monthly_orders); ?>;
        const platformDistributionData = <?php echo json_encode($platform_distribution); ?>;
        
        // Initialize charts
        let storesChart, ordersChart;
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });
        
        function initializeCharts() {
            // Stores Growth Chart
            const storesCtx = document.getElementById('storesChart');
            if (storesCtx) {
                storesChart = new Chart(storesCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyGrowthData.map(item => {
                            const [year, month] = item.month.split('-');
                            return new Date(year, month - 1).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                        }),
                        datasets: [
                            {
                                label: 'Nouveaux Stores',
                                data: monthlyGrowthData.map(item => item.new_stores),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                borderWidth: 3,
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Nouveaux Utilisateurs',
                                data: monthlyGrowthData.map(item => item.new_users),
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 3,
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                                titleColor: '#1f2937',
                                bodyColor: '#6b7280',
                                borderColor: '#e5e7eb',
                                borderWidth: 1,
                                cornerRadius: 8,
                                displayColors: true,
                                callbacks: {
                                    label: function(context) {
                                        return `${context.dataset.label}: ${context.parsed.y}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        animations: {
                            tension: {
                                duration: 1000,
                                easing: 'linear'
                            }
                        }
                    }
                });
            }
            
            // Orders Growth Chart
            const ordersCtx = document.getElementById('ordersChart');
            if (ordersCtx) {
                ordersChart = new Chart(ordersCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyOrdersData.map(item => {
                            const [year, month] = item.month.split('-');
                            return new Date(year, month - 1).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                        }),
                        datasets: [
                            {
                                label: 'Nombre de Commandes',
                                data: monthlyOrdersData.map(item => item.order_count),
                                backgroundColor: 'rgba(139, 92, 246, 0.8)',
                                borderRadius: 6,
                                borderWidth: 0
                            },
                            {
                                label: 'Valeur des Commandes (D.H)',
                                data: monthlyOrdersData.map(item => item.order_value),
                                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                                borderRadius: 6,
                                borderWidth: 0,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Nombre de Commandes'
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Valeur (D.H)'
                                },
                                grid: {
                                    drawOnChartArea: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    },
                                    callback: function(value) {
                                        return value.toLocaleString('fr-FR') + ' D.H';
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // Apply filters
        function applyFilters() {
            const dateRange = document.getElementById('dateRangeFilter').value;
            const platform = document.getElementById('platformFilter').value;
            
            let url = 'stores.php?';
            if (dateRange) url += 'date_range=' + dateRange + '&';
            if (platform) url += 'platform_filter=' + platform;
            
            window.location.href = url;
        }
    </script>
</body>
</html>