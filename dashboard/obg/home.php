<?php
// Database connection and session check for super admin
if (file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if user is logged in and is super admin
if (!Session::exists(Config::get('session/session_name'))) {
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

// Get filter parameters with proper sanitization
$dateRange = isset($_GET['date_range']) ? trim($_GET['date_range']) : 'this_month';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : null;
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;

// Validate and sanitize date parameters
if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = null;
}
if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = null;
}

// Calculate date range based on filter with proper SQL injection protection
$dateCondition = "1=1"; // Default condition
$queryParams = [];

switch ($dateRange) {
    case 'today':
        $dateCondition = "DATE(created_at) = CURDATE()";
        break;
    case 'yesterday':
        $dateCondition = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
        break;
    case 'this_week':
        $dateCondition = "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'last_week':
        $dateCondition = "YEARWEEK(created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
        break;
    case 'this_month':
        $dateCondition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
        break;
    case 'last_month':
        $dateCondition = "MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
    case 'this_year':
        $dateCondition = "YEAR(created_at) = YEAR(CURDATE())";
        break;
    case 'last_year':
        $dateCondition = "YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))";
        break;
    case 'custom':
        if ($startDate && $endDate) {
            // Use prepared statements for custom date range
            $dateCondition = "DATE(created_at) BETWEEN ? AND ?";
            $queryParams = [$startDate, $endDate];
        }
        break;
    default:
        $dateCondition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
}

// Get super admin dashboard data
$dashboardData = [];
$chartData = [];

try {
    // USER STATISTICS with date filter
    // Total users
    $totalUsersQuery = "SELECT COUNT(*) as total_users FROM users WHERE role != 'super_admin' AND $dateCondition";
    $totalUsersResult = $db->getThisQuery($totalUsersQuery, $queryParams);
    $totalUsers = $totalUsersResult[0]['total_users'] ?? 0;
    
    // Active users
    $activeUsersQuery = "SELECT COUNT(*) as active_users FROM users WHERE is_active = 1 AND role != 'super_admin' AND $dateCondition";
    $activeUsersResult = $db->getThisQuery($activeUsersQuery, $queryParams);
    $activeUsers = $activeUsersResult[0]['active_users'] ?? 0;
    
    // Verified users
    $verifiedUsersQuery = "SELECT COUNT(*) as verified_users FROM users WHERE is_verified = 1 AND role != 'super_admin' AND $dateCondition";
    $verifiedUsersResult = $db->getThisQuery($verifiedUsersQuery, $queryParams);
    $verifiedUsers = $verifiedUsersResult[0]['verified_users'] ?? 0;
    
    // Users with paid plans
    $paidUsersQuery = "SELECT COUNT(DISTINCT u.id) as paid_users 
                      FROM users u 
                      LEFT JOIN user_plans up ON u.id = up.user_id 
                      WHERE u.role != 'super_admin' 
                      AND up.plan_id IS NOT NULL 
                      AND up.status = 'active'
                      AND u.$dateCondition";
    $paidUsersResult = $db->getThisQuery($paidUsersQuery, $queryParams);
    $paidUsers = $paidUsersResult[0]['paid_users'] ?? 0;
    
    // Free plan users
    $freeUsers = $totalUsers - $paidUsers;
    
    // SUBSCRIPTION STATISTICS with date filter
    // Active subscriptions
    $activeSubsQuery = "SELECT COUNT(*) as active_subscriptions 
                       FROM user_plans 
                       WHERE status = 'active' 
                       AND expires_at > NOW()
                       AND $dateCondition";
    $activeSubsResult = $db->getThisQuery($activeSubsQuery, $queryParams);
    $activeSubscriptions = $activeSubsResult[0]['active_subscriptions'] ?? 0;
    
    // Expired subscriptions
    $expiredSubsQuery = "SELECT COUNT(*) as expired_subscriptions 
                        FROM user_plans 
                        WHERE status = 'active' 
                        AND expires_at <= NOW()
                        AND $dateCondition";
    $expiredSubsResult = $db->getThisQuery($expiredSubsQuery, $queryParams);
    $expiredSubscriptions = $expiredSubsResult[0]['expired_subscriptions'] ?? 0;
    
    // Total revenue (based on date filter)
    $revenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as monthly_revenue 
                    FROM user_plans 
                    WHERE status = 'active' 
                    AND $dateCondition";
    $revenueResult = $db->getThisQuery($revenueQuery, $queryParams);
    $monthlyRevenue = $revenueResult[0]['monthly_revenue'] ?? 0;
    
    // Total lifetime revenue (always total, not filtered by date)
    $lifetimeRevenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as lifetime_revenue 
                            FROM user_plans 
                            WHERE status = 'active'";
    $lifetimeRevenueResult = $db->getThisQuery($lifetimeRevenueQuery);
    $lifetimeRevenue = $lifetimeRevenueResult[0]['lifetime_revenue'] ?? 0;
    
    // STORE STATISTICS with date filter
    // Total stores
    $totalStoresQuery = "SELECT COUNT(*) as total_stores FROM stores WHERE $dateCondition";
    $totalStoresResult = $db->getThisQuery($totalStoresQuery, $queryParams);
    $totalStores = $totalStoresResult[0]['total_stores'] ?? 0;
    
    // Connected stores
    $connectedStoresQuery = "SELECT COUNT(*) as connected_stores FROM stores WHERE is_connected = 1 AND $dateCondition";
    $connectedStoresResult = $db->getThisQuery($connectedStoresQuery, $queryParams);
    $connectedStores = $connectedStoresResult[0]['connected_stores'] ?? 0;
    
    // Stores by platform
    $platformStatsQuery = "SELECT platform, COUNT(*) as store_count 
                          FROM stores 
                          WHERE $dateCondition
                          GROUP BY platform";
    $platformStats = $db->getThisQuery($platformStatsQuery, $queryParams);
    
    // Platform distribution
    $woocommerceStores = 0;
    $youcanStores = 0;
    $shopifyStores = 0;
    $otherStores = 0;
    
    foreach ($platformStats as $platform) {
        switch (strtolower($platform['platform'])) {
            case 'woocommerce':
                $woocommerceStores = $platform['store_count'];
                break;
            case 'youcan':
                $youcanStores = $platform['store_count'];
                break;
            case 'shopify':
                $shopifyStores = $platform['store_count'];
                break;
            default:
                $otherStores += $platform['store_count'];
                break;
        }
    }
    
    // SHIPPING COMPANIES STATISTICS
    // Total shipping companies
    $totalShippingCompaniesQuery = "SELECT COUNT(*) as total_companies FROM shipping_companies WHERE $dateCondition";
    $totalShippingCompaniesResult = $db->getThisQuery($totalShippingCompaniesQuery, $queryParams);
    $totalShippingCompanies = $totalShippingCompaniesResult[0]['total_companies'] ?? 0;
    
    // Active shipping companies
    $activeShippingCompaniesQuery = "SELECT COUNT(*) as active_companies FROM shipping_companies WHERE is_active = 1 AND $dateCondition";
    $activeShippingCompaniesResult = $db->getThisQuery($activeShippingCompaniesQuery, $queryParams);
    $activeShippingCompanies = $activeShippingCompaniesResult[0]['active_companies'] ?? 0;
    
    // Shipping companies with tracking support
    $trackingShippingCompaniesQuery = "SELECT COUNT(*) as tracking_companies FROM shipping_companies WHERE supports_tracking = 1 AND $dateCondition";
    $trackingShippingCompaniesResult = $db->getThisQuery($trackingShippingCompaniesQuery, $queryParams);
    $trackingShippingCompanies = $trackingShippingCompaniesResult[0]['tracking_companies'] ?? 0;
    
    // Top shipping companies by usage
    $shippingCompaniesDistributionQuery = "SELECT 
                                          name, 
                                          COUNT(*) as usage_count,
                                          SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
                                          FROM shipping_companies 
                                          WHERE $dateCondition
                                          GROUP BY name 
                                          ORDER BY usage_count DESC 
                                          LIMIT 10";
    $shippingCompaniesDistribution = $db->getThisQuery($shippingCompaniesDistributionQuery, $queryParams);
    
    // CHART DATA QUERIES with date filter
    
    // 1. User growth over time (respects date filter)
    $userGrowthQuery = "SELECT 
                       DATE_FORMAT(created_at, '%Y-%m') as month,
                       COUNT(*) as new_users,
                       (SELECT COUNT(*) FROM users u2 WHERE DATE_FORMAT(u2.created_at, '%Y-%m') <= month AND u2.role != 'super_admin') as total_users
                       FROM users 
                       WHERE $dateCondition
                       AND role != 'super_admin'
                       GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                       ORDER BY month ASC";
    $userGrowth = $db->getThisQuery($userGrowthQuery, $queryParams);
    
    // 2. Subscription growth over time (respects date filter)
    $subscriptionGrowthQuery = "SELECT 
                               DATE_FORMAT(created_at, '%Y-%m') as month,
                               COUNT(*) as new_subscriptions,
                               SUM(total_amount) as monthly_revenue,
                               (SELECT COUNT(*) FROM user_plans up2 WHERE DATE_FORMAT(up2.created_at, '%Y-%m') <= month AND up2.status = 'active') as total_subscriptions
                               FROM user_plans 
                               WHERE $dateCondition
                               AND status = 'active'
                               GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                               ORDER BY month ASC";
    $subscriptionGrowth = $db->getThisQuery($subscriptionGrowthQuery, $queryParams);
    
    // 3. Platform growth over time (respects date filter)
    $platformGrowthQuery = "SELECT 
                           DATE_FORMAT(connected_at, '%Y-%m') as month,
                           platform,
                           COUNT(*) as new_stores
                           FROM stores 
                           WHERE $dateCondition
                           AND is_connected = 1
                           GROUP BY DATE_FORMAT(connected_at, '%Y-%m'), platform
                           ORDER BY month ASC, platform ASC";
    $platformGrowth = $db->getThisQuery($platformGrowthQuery, $queryParams);
    
    // 4. Recent users (respects date filter)
    $recentUsersQuery = "SELECT id, name, email, role, created_at, is_verified, is_active 
                        FROM users 
                        WHERE role != 'super_admin'
                        AND $dateCondition
                        ORDER BY created_at DESC 
                        LIMIT 5";
    $recentUsers = $db->getThisQuery($recentUsersQuery, $queryParams);
    
    // 5. Recent subscriptions (respects date filter)
    $recentSubscriptionsQuery = "SELECT up.*, u.name, u.email 
                                FROM user_plans up 
                                JOIN users u ON up.user_id = u.id 
                                WHERE $dateCondition
                                ORDER BY up.created_at DESC 
                                LIMIT 5";
    $recentSubscriptions = $db->getThisQuery($recentSubscriptionsQuery, $queryParams);
    
    // 6. Plan distribution (respects date filter)
    $planDistributionQuery = "SELECT 
                             p.name as plan_name,
                             COUNT(up.id) as user_count,
                             COALESCE(SUM(up.total_amount), 0) as total_revenue
                             FROM user_plans up
                             JOIN plans p ON up.plan_id = p.id
                             WHERE up.status = 'active'
                             AND up.$dateCondition
                             GROUP BY p.name
                             ORDER BY user_count DESC";
    $planDistribution = $db->getThisQuery($planDistributionQuery, $queryParams);
    
    // 7. Shipping companies growth over time
    $shippingCompaniesGrowthQuery = "SELECT 
                                    DATE_FORMAT(created_at, '%Y-%m') as month,
                                    COUNT(*) as new_companies,
                                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_companies
                                    FROM shipping_companies 
                                    WHERE $dateCondition
                                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                                    ORDER BY month ASC";
    $shippingCompaniesGrowth = $db->getThisQuery($shippingCompaniesGrowthQuery, $queryParams);
    
    $dashboardData = [
        'users' => [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'verified' => $verifiedUsers,
            'paid' => $paidUsers,
            'free' => $freeUsers
        ],
        'subscriptions' => [
            'active' => $activeSubscriptions,
            'expired' => $expiredSubscriptions,
            'monthly_revenue' => $monthlyRevenue,
            'lifetime_revenue' => $lifetimeRevenue
        ],
        'stores' => [
            'total' => $totalStores,
            'connected' => $connectedStores,
            'woocommerce' => $woocommerceStores,
            'youcan' => $youcanStores,
            'shopify' => $shopifyStores,
            'other' => $otherStores
        ],
        'shipping_companies' => [
            'total' => $totalShippingCompanies,
            'active' => $activeShippingCompanies,
            'with_tracking' => $trackingShippingCompanies,
            'distribution' => $shippingCompaniesDistribution
        ],
        'recent_users' => $recentUsers,
        'recent_subscriptions' => $recentSubscriptions,
        'plan_distribution' => $planDistribution
    ];
    
    $chartData = [
        'user_growth' => $userGrowth,
        'subscription_growth' => $subscriptionGrowth,
        'platform_growth' => $platformGrowth,
        'plan_distribution' => $planDistribution,
        'shipping_companies_growth' => $shippingCompaniesGrowth,
        'shipping_companies_distribution' => $shippingCompaniesDistribution
    ];
    
} catch (Exception $e) {
    error_log("Super Admin Dashboard error: " . $e->getMessage());
    $dashboardData = [
        'users' => ['total' => 0, 'active' => 0, 'verified' => 0, 'paid' => 0, 'free' => 0],
        'subscriptions' => ['active' => 0, 'expired' => 0, 'monthly_revenue' => 0, 'lifetime_revenue' => 0],
        'stores' => ['total' => 0, 'connected' => 0, 'woocommerce' => 0, 'youcan' => 0, 'shopify' => 0, 'other' => 0],
        'shipping_companies' => ['total' => 0, 'active' => 0, 'with_tracking' => 0, 'distribution' => []],
        'recent_users' => [],
        'recent_subscriptions' => [],
        'plan_distribution' => []
    ];
    $chartData = [
        'user_growth' => [],
        'subscription_growth' => [],
        'platform_growth' => [],
        'plan_distribution' => [],
        'shipping_companies_growth' => [],
        'shipping_companies_distribution' => []
    ];
}

function formatCurrency($amount) {
    $amount = is_null($amount) ? 0 : $amount;
    return number_format((float)$amount, 2, ',', ' ') . ' D.H';
}

function formatPercentage($value, $total) {
    if ($total == 0) return '0%';
    return round(($value / $total) * 100, 1) . '%';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tableau de bord Super Admin - OBG">
    <title>Super Admin | OBG</title>
    <link rel="stylesheet" href="../../assets/css/supperDash.css" />
    <link rel="stylesheet" href="../../assets/css/super.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'super_admin';
    require_once('../../assets/sidebarSuper.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation Bar -->
        <nav class="top-navbar">
            <div class="navbar-title">
                <h1>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                    </svg>
                    Tableau de bord Super Admin
                </h1>
            </div>
            
            <div class="top-navbar-right">
                <div class="user-menu">
                    <button class="user-btn" onclick="toggleUserMenu()">
                        <?php echo strtoupper(substr($user[0]['name'], 0, 1)); ?>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="./profile.php">
                            <i class="bi bi-person"></i>
                            Mon Profil
                        </a>
                        <a href="./settings.php">
                            <i class="bi bi-gear"></i>
                            Paramètres
                        </a>
                        <hr>
                        <a href="?logout">
                            <i class="bi bi-box-arrow-right"></i>
                            Déconnexion
                        </a>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Message -->
            <div class="welcome-message">
                <div class="welcome-icon admin">
                    <i class="bi bi-shield-check"></i>
                </div>
                <div class="welcome-content">
                    <h3>Super Admin Dashboard</h3>
                    <p>Vue d'ensemble complète de la plateforme OBG</p>
                    <?php if ($dateRange === 'custom' && $startDate && $endDate): ?>
                        <p class="date-filter-info">
                            Filtre appliqué : Du <?php echo date('d/m/Y', strtotime($startDate)); ?> au <?php echo date('d/m/Y', strtotime($endDate)); ?>
                        </p>
                    <?php elseif ($dateRange !== 'custom'): ?>
                        <p class="date-filter-info">
                            Période : <?php echo ucfirst(str_replace('_', ' ', $dateRange)); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="dashboard-filters">
                <form method="get" class="filter-form" id="filterForm">
                    <div class="filter-group">
                        <label for="date-range">Période</label>
                        <select id="date-range" name="date_range" class="form-select">
                            <option value="today" <?php echo $dateRange == 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                            <option value="yesterday" <?php echo $dateRange == 'yesterday' ? 'selected' : ''; ?>>Hier</option>
                            <option value="this_week" <?php echo $dateRange == 'this_week' ? 'selected' : ''; ?>>Cette semaine</option>
                            <option value="last_week" <?php echo $dateRange == 'last_week' ? 'selected' : ''; ?>>Semaine dernière</option>
                            <option value="this_month" <?php echo $dateRange == 'this_month' ? 'selected' : ''; ?>>Ce mois</option>
                            <option value="last_month" <?php echo $dateRange == 'last_month' ? 'selected' : ''; ?>>Mois dernier</option>
                            <option value="this_year" <?php echo $dateRange == 'this_year' ? 'selected' : ''; ?>>Cette année</option>
                            <option value="last_year" <?php echo $dateRange == 'last_year' ? 'selected' : ''; ?>>Année dernière</option>
                            <option value="custom" <?php echo $dateRange == 'custom' ? 'selected' : ''; ?>>Personnalisé</option>
                        </select>
                    </div>
                    
                    <div class="filter-group custom-date-range" id="custom-date-range" style="<?php echo $dateRange == 'custom' ? '' : 'display: none;'; ?>">
                        <label for="start-date">Du</label>
                        <input type="date" id="start-date" name="start_date" class="form-control" value="<?php echo $startDate; ?>" required>
                        
                        <label for="end-date">Au</label>
                        <input type="date" id="end-date" name="end_date" class="form-control" value="<?php echo $endDate; ?>" required>
                    </div>
                    
                    <button type="submit" class="filter-btn">Appliquer</button>
                    <button type="button" class="filter-btn reset" onclick="resetFilters()">Réinitialiser</button>
                </form>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-cards">
                <!-- Total Users -->
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Utilisateurs Totaux</h3>
                        <span class="stat-card-icon users">
                            <i class="bi bi-people"></i>
                        </span>
                    </div>
                    <div class="stat-card-value"><?php echo $dashboardData['users']['total']; ?></div>
                    <div class="stat-card-description">
                        <span><?php echo $dashboardData['users']['active']; ?> actifs</span>
                        <span class="stat-card-change positive"><?php echo $dashboardData['users']['verified']; ?> vérifiés</span>
                    </div>
                </div>
                
                <!-- Paid vs Free Users -->
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Utilisateurs Payants</h3>
                        <span class="stat-card-icon revenue">
                            <i class="bi bi-currency-dollar"></i>
                        </span>
                    </div>
                    <div class="stat-card-value"><?php echo formatPercentage($dashboardData['users']['paid'], $dashboardData['users']['total']); ?></div>
                    <div class="stat-card-description">
                        <span><?php echo $dashboardData['users']['paid']; ?> payants</span>
                        <span><?php echo $dashboardData['users']['free']; ?> gratuits</span>
                    </div>
                </div>
                
                <!-- Active Subscriptions -->
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Abonnements Actifs</h3>
                        <span class="stat-card-icon subscriptions">
                            <i class="bi bi-credit-card"></i>
                        </span>
                    </div>
                    <div class="stat-card-value"><?php echo $dashboardData['subscriptions']['active']; ?></div>
                    <div class="stat-card-description">
                        <span><?php echo $dashboardData['subscriptions']['expired']; ?> expirés</span>
                        <span class="stat-card-change positive"><?php echo formatCurrency($dashboardData['subscriptions']['monthly_revenue']); ?> cette période</span>
                    </div>
                </div>
                
                <!-- Revenue -->
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Revenu Total</h3>
                        <span class="stat-card-icon money">
                            <i class="bi bi-graph-up"></i>
                        </span>
                    </div>
                    <div class="stat-card-value"><?php echo formatCurrency($dashboardData['subscriptions']['lifetime_revenue']); ?></div>
                    <div class="stat-card-description">
                        <span><?php echo formatCurrency($dashboardData['subscriptions']['monthly_revenue']); ?> cette période</span>
                    </div>
                </div>
                
                <!-- Total Stores -->
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Boutiques</h3>
                        <span class="stat-card-icon stores">
                            <i class="bi bi-shop"></i>
                        </span>
                    </div>
                    <div class="stat-card-value"><?php echo $dashboardData['stores']['total']; ?></div>
                    <div class="stat-card-description">
                        <span><?php echo $dashboardData['stores']['connected']; ?> connectées</span>
                    </div>
                </div>

                <!-- Shipping Companies -->
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Transporteurs</h3>
                        <span class="stat-card-icon shipping">
                            <i class="bi bi-truck"></i>
                        </span>
                    </div>
                    <div class="stat-card-value"><?php echo $dashboardData['shipping_companies']['total']; ?></div>
                    <div class="stat-card-description">
                        <span><?php echo $dashboardData['shipping_companies']['active']; ?> actifs</span>
                        <span class="stat-card-change positive"><?php echo $dashboardData['shipping_companies']['with_tracking']; ?> avec tracking</span>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container large">
                    <div class="chart-header">
                        <h3>Croissance des Utilisateurs & Abonnements</h3>
                        <div class="chart-controls">
                            <button class="chart-control active" onclick="switchGrowthChart('users')">Utilisateurs</button>
                            <button class="chart-control" onclick="switchGrowthChart('subscriptions')">Abonnements</button>
                            <button class="chart-control" onclick="switchGrowthChart('revenue')">Revenus</button>
                            <button class="chart-control" onclick="switchGrowthChart('shipping')">Transporteurs</button>
                        </div>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="growthChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>Répartition des Plateformes</h3>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="platformChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Secondary Charts -->
            <div class="secondary-charts">
                <div class="secondary-chart">
                    <div class="chart-header">
                        <h3>Distribution des Plans</h3>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="planChart"></canvas>
                    </div>
                </div>
                
                <div class="secondary-chart">
                    <div class="chart-header">
                        <h3>Transporteurs les plus utilisés</h3>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="shippingCompaniesChart"></canvas>
                    </div>
                </div>
                
                <div class="secondary-chart">
                    <div class="chart-header">
                        <h3>Statut des Boutiques</h3>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="storeStatusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="activity-column">
                    <div class="card">
                        <div class="card-header">
                            <h3>Utilisateurs Récents</h3>
                            <a href="./users_management.php" class="view-all">
                                Voir tout
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div class="card-content">
                            <?php if (!empty($dashboardData['recent_users'])): ?>
                                <?php foreach ($dashboardData['recent_users'] as $recentUser): ?>
                                    <div class="activity-item">
                                        <div class="activity-avatar">
                                            <?php echo strtoupper(substr($recentUser['name'], 0, 1)); ?>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title"><?php echo htmlspecialchars($recentUser['name']); ?></div>
                                            <div class="activity-meta"><?php echo htmlspecialchars($recentUser['email']); ?></div>
                                            <div class="activity-time">
                                                Inscrit le <?php echo date('d/m/Y', strtotime($recentUser['created_at'])); ?>
                                                <?php if ($recentUser['is_verified']): ?>
                                                    <span class="badge verified">Vérifié</span>
                                                <?php endif; ?>
                                                <?php if (!$recentUser['is_active']): ?>
                                                    <span class="badge inactive">Inactif</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="bi bi-people"></i>
                                    <p>Aucun utilisateur récent</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="activity-column">
                    <div class="card">
                        <div class="card-header">
                            <h3>Abonnements Récents</h3>
                            <a href="./subscriptions_management.php" class="view-all">
                                Voir tout
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div class="card-content">
                            <?php if (!empty($dashboardData['recent_subscriptions'])): ?>
                                <?php foreach ($dashboardData['recent_subscriptions'] as $subscription): ?>
                                    <div class="activity-item">
                                        <div class="activity-avatar plan">
                                            <i class="bi bi-credit-card"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title"><?php echo htmlspecialchars($subscription['name']); ?></div>
                                            <div class="activity-meta"><?php echo htmlspecialchars($subscription['email']); ?></div>
                                            <div class="activity-time">
                                                <?php echo formatCurrency($subscription['total_amount']); ?> • 
                                                <?php echo date('d/m/Y', strtotime($subscription['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="bi bi-credit-card"></i>
                                    <p>Aucun abonnement récent</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="activity-column">
                    <div class="card">
                        <div class="card-header">
                            <h3>Transporteurs Récents</h3>
                            <a href="./shipping_management.php" class="view-all">
                                Voir tout
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        <div class="card-content">
                            <?php if (!empty($dashboardData['shipping_companies']['distribution'])): ?>
                                <?php foreach (array_slice($dashboardData['shipping_companies']['distribution'], 0, 5) as $company): ?>
                                    <div class="activity-item">
                                        <div class="activity-avatar shipping">
                                            <i class="bi bi-truck"></i>
                                        </div>
                                        <div class="activity-details">
                                            <div class="activity-title"><?php echo htmlspecialchars($company['name']); ?></div>
                                            <div class="activity-meta"><?php echo $company['usage_count']; ?> utilisations</div>
                                            <div class="activity-time">
                                                <?php echo $company['active_count']; ?> actifs
                                                <?php if ($company['active_count'] > 0): ?>
                                                    <span class="badge verified">Actif</span>
                                                <?php else: ?>
                                                    <span class="badge inactive">Inactif</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="bi bi-truck"></i>
                                    <p>Aucun transporteur récent</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/fr.min.js"></script>
    
    <script>
        // Chart data from PHP
        const chartData = <?php echo json_encode($chartData); ?>;
        const dashboardData = <?php echo json_encode($dashboardData); ?>;
        
        let growthChart, platformChart, planChart, shippingCompaniesChart, storeStatusChart;
        let currentGrowthChart = 'users';
        
        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Date range functionality
            const dateRangeSelect = document.getElementById('date-range');
            const customDateRange = document.getElementById('custom-date-range');
            const startDateInput = document.getElementById('start-date');
            const endDateInput = document.getElementById('end-date');
            
            dateRangeSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customDateRange.style.display = 'flex';
                    // Set default dates if empty
                    if (!startDateInput.value) {
                        const thirtyDaysAgo = new Date();
                        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                        startDateInput.value = thirtyDaysAgo.toISOString().split('T')[0];
                    }
                    if (!endDateInput.value) {
                        endDateInput.value = new Date().toISOString().split('T')[0];
                    }
                } else {
                    customDateRange.style.display = 'none';
                }
            });
            
            // Initialize flatpickr
            flatpickr("#start-date", {
                dateFormat: "Y-m-d",
                locale: "fr",
                maxDate: new Date()
            });
            
            flatpickr("#end-date", {
                dateFormat: "Y-m-d",
                locale: "fr",
                minDate: document.getElementById('start-date').value,
                maxDate: new Date()
            });
            
            document.getElementById('start-date').addEventListener('change', function() {
                document.getElementById('end-date')._flatpickr.set('minDate', this.value);
            });
            
            // Form validation for custom date range
            document.getElementById('filterForm').addEventListener('submit', function(e) {
                if (dateRangeSelect.value === 'custom') {
                    if (!startDateInput.value || !endDateInput.value) {
                        e.preventDefault();
                        alert('Veuillez sélectionner les dates de début et de fin pour la période personnalisée.');
                        return false;
                    }
                    if (new Date(startDateInput.value) > new Date(endDateInput.value)) {
                        e.preventDefault();
                        alert('La date de début ne peut pas être après la date de fin.');
                        return false;
                    }
                }
            });
            
            // Initialize all charts
            initializeCharts();
        });
        
        function initializeCharts() {
            // Growth Chart
            const growthCtx = document.getElementById('growthChart');
            if (growthCtx) {
                growthChart = new Chart(growthCtx, {
                    type: 'line',
                    data: getGrowthChartData('users'),
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        if (value >= 1000) {
                                            return (value / 1000) + 'k';
                                        }
                                        return value;
                                    }
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });
            }
            
            // Platform Chart
            const platformCtx = document.getElementById('platformChart');
            if (platformCtx) {
                platformChart = new Chart(platformCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['WooCommerce', 'YouCan', 'Shopify', 'Autres'],
                        datasets: [{
                            data: [
                                dashboardData.stores.woocommerce,
                                dashboardData.stores.youcan,
                                dashboardData.stores.shopify,
                                dashboardData.stores.other
                            ],
                            backgroundColor: [
                                '#3498db',
                                '#9b59b6',
                                '#2ecc71',
                                '#95a5a6'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            }
            
            // Plan Distribution Chart
            const planCtx = document.getElementById('planChart');
            if (planCtx && chartData.plan_distribution && chartData.plan_distribution.length > 0) {
                planChart = new Chart(planCtx, {
                    type: 'bar',
                    data: {
                        labels: chartData.plan_distribution.map(plan => plan.plan_name),
                        datasets: [{
                            label: 'Nombre d\'utilisateurs',
                            data: chartData.plan_distribution.map(plan => plan.user_count),
                            backgroundColor: '#4361ee',
                            borderColor: '#4361ee',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } else {
                // Hide plan chart if no data
                document.querySelector('.secondary-chart:first-child').style.display = 'none';
            }
            
            // Shipping Companies Chart
            const shippingCtx = document.getElementById('shippingCompaniesChart');
            if (shippingCtx && chartData.shipping_companies_distribution && chartData.shipping_companies_distribution.length > 0) {
                shippingCompaniesChart = new Chart(shippingCtx, {
                    type: 'bar',
                    data: {
                        labels: chartData.shipping_companies_distribution.map(company => company.name),
                        datasets: [{
                            label: "Nombre d'utilisations",
                            data: chartData.shipping_companies_distribution.map(company => company.usage_count),
                            backgroundColor: '#e74c3c',
                            borderColor: '#e74c3c',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
            
            // Store Status Chart
            const storeStatusCtx = document.getElementById('storeStatusChart');
            if (storeStatusCtx) {
                storeStatusChart = new Chart(storeStatusCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Connectées', 'Non connectées'],
                        datasets: [{
                            data: [
                                dashboardData.stores.connected,
                                Math.max(0, dashboardData.stores.total - dashboardData.stores.connected)
                            ],
                            backgroundColor: ['#2ecc71', '#e74c3c'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }
        
        function getGrowthChartData(type) {
            let datasets = [];
            let labels = [];
            
            // Handle empty data cases
            const userGrowthData = chartData.user_growth || [];
            const subscriptionGrowthData = chartData.subscription_growth || [];
            const shippingGrowthData = chartData.shipping_companies_growth || [];
            
            switch(type) {
                case 'users':
                    labels = userGrowthData.map(item => item.month);
                    datasets = [
                        {
                            label: 'Nouveaux utilisateurs',
                            data: userGrowthData.map(item => item.new_users || 0),
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Utilisateurs totaux',
                            data: userGrowthData.map(item => item.total_users || 0),
                            borderColor: '#2ecc71',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ];
                    break;
                    
                case 'subscriptions':
                    labels = subscriptionGrowthData.map(item => item.month);
                    datasets = [
                        {
                            label: 'Nouveaux abonnements',
                            data: subscriptionGrowthData.map(item => item.new_subscriptions || 0),
                            borderColor: '#9b59b6',
                            backgroundColor: 'rgba(155, 89, 182, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Abonnements totaux',
                            data: subscriptionGrowthData.map(item => item.total_subscriptions || 0),
                            borderColor: '#34495e',
                            backgroundColor: 'rgba(52, 73, 94, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ];
                    break;
                    
                case 'revenue':
                    labels = subscriptionGrowthData.map(item => item.month);
                    datasets = [
                        {
                            label: 'Revenus mensuels',
                            data: subscriptionGrowthData.map(item => item.monthly_revenue || 0),
                            borderColor: '#f39c12',
                            backgroundColor: 'rgba(243, 156, 18, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ];
                    break;
                    
                case 'shipping':
                    labels = shippingGrowthData.map(item => item.month);
                    datasets = [
                        {
                            label: 'Nouveaux transporteurs',
                            data: shippingGrowthData.map(item => item.new_companies || 0),
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Transporteurs actifs',
                            data: shippingGrowthData.map(item => item.active_companies || 0),
                            borderColor: '#e67e22',
                            backgroundColor: 'rgba(230, 126, 34, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ];
                    break;
            }
            
            return { labels, datasets };
        }
        
        function switchGrowthChart(type) {
            currentGrowthChart = type;
            
            // Update active button
            document.querySelectorAll('.chart-control').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update chart data
            if (growthChart) {
                growthChart.data = getGrowthChartData(type);
                growthChart.update();
            }
        }
        
        function resetFilters() {
            window.location.href = 'super_admin.php';
        }
        
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }
        
        // Close user dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            const dropdown = document.getElementById('userDropdown');
            
            if (!userMenu.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>