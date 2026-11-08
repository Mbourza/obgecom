<?php
// Database connection and session check
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("./config/init.php");
}

$db = DB::getInstance();

// Check if user is logged in
if(!Session::exists(Config::get('session/session_name'))) {
    Redirect::to('../lg'); 
} 

$isLoggedIn = isset($_SESSION['user']);
$user = $db->getThisQuery("SELECT id, `name`, `role`, is_verified, email FROM users WHERE email = ?", [$_SESSION['user']['username']]);

if (!$user || empty($user[0]['id'])) {
    logout(); 
}

if (isset($_GET['logout'])) {
    logout();
}

$currentUserId = $user[0]['id'];
$isVerified = $user[0]['is_verified'];
$userEmail = $user[0]['email'];

// Ensure we have a valid user
if (!$user || !isset($user[0]['id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Utilisateur non trouvé',
        'code'    => 'USER_NOT_FOUND'
    ]);
    exit;
}
$user_id = $currentUserId;
// Initialize onboarding
$onboarding = new Onboarding($currentUserId);

// Redirect to onboarding 
$currentPage = basename($_SERVER['PHP_SELF']);
if (!$onboarding->isOnboardingCompleted() && $currentPage !== 'onboarding') {
    Redirect::to('./onboarding');
}

// Get filter parameters
$storeFilter = isset($_GET['store']) ? $_GET['store'] : null;
$dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'this_month';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Vérification de l'abonnement
$subscription = new Subscription($currentUserId);
$subscriptionStatus = $subscription->getSubscriptionStatus();
$subscriptionAlerts = $subscription->getSubscriptionAlerts();

// Vérifier l'accès aux fonctionnalités
$canAccessFeatures = $subscription->canAccessFeatures();

// Si l'abonnement est expiré ou inexistant, limiter l'accès
if (!$canAccessFeatures) {
    // Limiter l'accès aux fonctionnalités premium
    $limitedAccess = true;
} else {
    $limitedAccess = false;
}

// Calculate date range based on filter
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
            $dateCondition = "DATE(created_at) BETWEEN '$startDate' AND '$endDate'";
        } else {
            $dateCondition = "1=1";
        }
        break;
    default:
        $dateCondition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
}

// Add store filter if selected
$storeCondition = $storeFilter ? "AND store_id = $storeFilter" : "";

// Get dashboard data if user is logged in
$dashboardData = [];
$chartData = [];
$stores = [];

if ($isLoggedIn) {
    try {
        // Get stores for filter dropdown
        $storesQuery = "SELECT id, storeName FROM stores WHERE user_id = ?";
        $stores = $db->getThisQuery($storesQuery, [$currentUserId]);
        
        // Get total orders count
        $totalOrdersQuery = "SELECT COUNT(*) as total_orders FROM orders WHERE user_id = ? $storeCondition";
        $totalOrders = $db->getThisQuery($totalOrdersQuery, [$currentUserId])[0]['total_orders'];
        
        // Get confirmed orders count (factured/paid)
        $confirmedOrdersQuery = "SELECT COUNT(*) as confirmed_orders 
                               FROM orders 
                               WHERE user_id = ? 
                               AND status = 'complete' 
                               $storeCondition";
        $confirmedOrders = $db->getThisQuery($confirmedOrdersQuery, [$currentUserId])[0]['confirmed_orders'];
        
        // Get delivered orders count
        $deliveredOrdersQuery = "SELECT COUNT(*) as delivered_orders 
                               FROM orders 
                               WHERE user_id = ? 
                               AND shipping_status = 'delivered' 
                               $storeCondition";
        $deliveredOrders = $db->getThisQuery($deliveredOrdersQuery, [$currentUserId])[0]['delivered_orders'];
        
        // Get returned orders count
        $returnedOrdersQuery = "SELECT COUNT(*) as returned_orders 
                              FROM orders 
                              WHERE user_id = ? 
                              AND shipping_status = 'returned' 
                              $storeCondition";
        $returnedOrders = $db->getThisQuery($returnedOrdersQuery, [$currentUserId])[0]['returned_orders'];
        
        // Calculate rates
        $confirmationRate = $totalOrders > 0 ? round(($confirmedOrders / $totalOrders) * 100, 2) : 0;
        $deliveryRate = $confirmedOrders > 0 ? round(($deliveredOrders / $confirmedOrders) * 100, 2) : 0;
        $returnRate = $deliveredOrders > 0 ? round(($returnedOrders / $deliveredOrders) * 100, 2) : 0;
        
        // Get filtered orders count
        $filteredOrdersQuery = "SELECT COUNT(*) as filtered_orders 
                              FROM orders 
                              WHERE user_id = ? 
                              AND $dateCondition 
                              $storeCondition";
        $filteredOrders = $db->getThisQuery($filteredOrdersQuery, [$currentUserId])[0]['filtered_orders'];
        
        // Get filtered revenue
        $filteredRevenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as filtered_revenue 
                               FROM orders 
                               WHERE user_id = ? 
                               AND $dateCondition 
                               AND status != 'cancelled' 
                               $storeCondition";
        $filteredRevenue = $db->getThisQuery($filteredRevenueQuery, [$currentUserId])[0]['filtered_revenue'];
        
        // Get user orders count for current period
        $ordersQuery = "SELECT COUNT(*) as total_orders, 
                       SUM(CASE WHEN $dateCondition THEN 1 ELSE 0 END) as period_orders,
                       SUM(CASE WHEN status = 'complete' AND $dateCondition THEN 1 ELSE 0 END) as confirmed_orders,
                       SUM(CASE WHEN shipping_status = 'delivered' AND $dateCondition THEN 1 ELSE 0 END) as delivered_orders,
                       SUM(CASE WHEN shipping_status = 'returned' AND $dateCondition THEN 1 ELSE 0 END) as returned_orders
                       FROM orders WHERE user_id = ? $storeCondition";
        $orderStats = $db->getThisQuery($ordersQuery, [$currentUserId]);
        
        // Get revenue data for current period
        $revenueQuery = "SELECT 
                        SUM(CASE WHEN $dateCondition THEN total_amount ELSE 0 END) as period_revenue,
                        AVG(CASE WHEN $dateCondition THEN total_amount ELSE NULL END) as avg_order_value
                        FROM orders WHERE user_id = ? AND status != 'cancelled' $storeCondition";
        $revenueStats = $db->getThisQuery($revenueQuery, [$currentUserId]);
        
        // Get customer count for current period
        $customersQuery = "SELECT COUNT(DISTINCT customer_id) as total_customers,
                          COUNT(DISTINCT CASE WHEN $dateCondition THEN customer_id END) as period_customers
                          FROM orders WHERE user_id = ? $storeCondition";
        $customerStats = $db->getThisQuery($customersQuery, [$currentUserId]);
        
        // Get recent orders
        $recentOrdersQuery = "SELECT * 
                      FROM orders 
                      WHERE user_id = ? 
                      $storeCondition
                      ORDER BY created_at DESC 
                      LIMIT 5";
        $recentOrders = $db->getThisQuery($recentOrdersQuery, [$currentUserId]);
        
        // Get support tickets
        $ticketsQuery = "SELECT * FROM tickets 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC LIMIT 3";
        $supportTickets = $db->getThisQuery($ticketsQuery, [$currentUserId]);
        
        // CHART DATA QUERIES
        
        // 1. Daily sales for the last 30 days
        $dailySalesQuery = "SELECT 
                           DATE(created_at) as date,
                           COUNT(*) as orders_count,
                           COALESCE(SUM(total_amount), 0) as revenue
                           FROM orders 
                           WHERE user_id = ? 
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                           AND status != 'cancelled'
                           $storeCondition
                           GROUP BY DATE(created_at)
                           ORDER BY date ASC";
        $dailySales = $db->getThisQuery($dailySalesQuery, [$currentUserId]);
        
        // 2. Weekly sales for the last 12 weeks
        $weeklySalesQuery = "SELECT 
                            YEARWEEK(created_at, 1) as week,
                            CONCAT(YEAR(created_at), '-W', LPAD(WEEK(created_at, 1), 2, '0')) as week_label,
                            COUNT(*) as orders_count,
                            COALESCE(SUM(total_amount), 0) as revenue
                            FROM orders 
                            WHERE user_id = ? 
                            AND created_at >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                            AND status != 'cancelled'
                            $storeCondition
                            GROUP BY YEARWEEK(created_at, 1)
                            ORDER BY week ASC";

        $weeklySales = $db->getThisQuery($weeklySalesQuery, [$currentUserId]);
        
        // 3. Monthly sales for the last 12 months
        $monthlySalesQuery = "SELECT 
                             DATE_FORMAT(created_at, '%Y-%m') as month,
                             COUNT(*) as orders_count,
                             COALESCE(SUM(total_amount), 0) as revenue
                             FROM orders 
                             WHERE user_id = ? 
                             AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                             AND status != 'cancelled'
                             $storeCondition
                             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                             ORDER BY month ASC";
        $monthlySales = $db->getThisQuery($monthlySalesQuery, [$currentUserId]);
        
        // 4. Top products by quantity
        $topProductsQuery = "SELECT 
                            oi.product_name,
                            SUM(oi.quantity) as total_quantity,
                            SUM(oi.quantity * oi.unit_price) as total_revenue,
                            COUNT(DISTINCT oi.order_id) as order_count
                            FROM order_items oi
                            JOIN orders o ON oi.order_id = o.id
                            WHERE o.user_id = ?
                            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                            AND o.status != 'cancelled'
                            $storeCondition
                            GROUP BY oi.product_name
                            ORDER BY total_quantity DESC
                            LIMIT 10";
        $topProducts = $db->getThisQuery($topProductsQuery, [$currentUserId]);
        
        // 5. Order status distribution
        $orderStatusQuery = "SELECT 
                            status,
                            COUNT(*) as count,
                            (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders WHERE user_id = ? AND $dateCondition $storeCondition)) as percentage
                            FROM orders 
                            WHERE user_id = ?
                            AND $dateCondition
                            $storeCondition
                            GROUP BY status
                            ORDER BY count DESC";
        $orderStatusStats = $db->getThisQuery($orderStatusQuery, [$currentUserId, $currentUserId]);
        
        // 6. Shipping status distribution
        $shippingStatusQuery = "SELECT 
                               shipping_status,
                               COUNT(*) as count,
                               (COUNT(*) * 100.0 / (SELECT COUNT(*) FROM orders WHERE user_id = ? AND $dateCondition $storeCondition)) as percentage
                               FROM orders 
                               WHERE user_id = ?
                               AND $dateCondition
                               $storeCondition
                               GROUP BY shipping_status
                               ORDER BY count DESC";
        $shippingStatusStats = $db->getThisQuery($shippingStatusQuery, [$currentUserId, $currentUserId]);

        //
        $topClientsQuery = "
            SELECT 
                o.customer_name AS name,
                o.customer_email AS email,
                COUNT(o.id) AS order_count,
                COALESCE(SUM(o.total_amount), 0) AS total_spent
            FROM orders o
            WHERE o.user_id = ?
            AND o.status != 'cancelled'
            $storeCondition
            GROUP BY o.customer_name, o.customer_email
            ORDER BY order_count DESC
            LIMIT 8
        ";

        $topClients = $db->getThisQuery($topClientsQuery, [$currentUserId]);

        
        $dashboardData = [
            'total_orders' => $totalOrders,
            'confirmed_orders' => $confirmedOrders,
            'delivered_orders' => $deliveredOrders,
            'returned_orders' => $returnedOrders,
            'confirmation_rate' => $confirmationRate,
            'delivery_rate' => $deliveryRate,
            'return_rate' => $returnRate,
            'filtered_orders' => $filteredOrders,
            'filtered_revenue' => $filteredRevenue,
            'orders' => $orderStats[0] ?? ['total_orders' => 0, 'period_orders' => 0, 'confirmed_orders' => 0, 'delivered_orders' => 0, 'returned_orders' => 0],
            'revenue' => $revenueStats[0] ?? ['period_revenue' => 0, 'avg_order_value' => 0],
            'customers' => $customerStats[0] ?? ['total_customers' => 0, 'period_customers' => 0],
            'recent_orders' => $recentOrders ?? [],
            'support_tickets' => $supportTickets ?? []
        ];
        
        $chartData = [
            'daily_sales' => $dailySales ?? [],
            'weekly_sales' => $weeklySales ?? [],
            'monthly_sales' => $monthlySales ?? [],
            'top_products' => $topProducts ?? [],
            'order_status' => $orderStatusStats ?? [],
            'shipping_status' => $shippingStatusStats ?? [],
            'customer_acquisition' => $customerAcquisition ?? [],
            'top_clients' => $topClients ?? []
        ];
        
    } catch (Exception $e) {

        error_log("Dashboard data error: " . $e->getMessage());
        $dashboardData = [
            'total_orders' => 0,
            'confirmed_orders' => 0,
            'delivered_orders' => 0,
            'returned_orders' => 0,
            'confirmation_rate' => 0,
            'delivery_rate' => 0,
            'return_rate' => 0,
            'filtered_orders' => 0,
            'filtered_revenue' => 0,
            'orders' => ['total_orders' => 0, 'period_orders' => 0, 'confirmed_orders' => 0, 'delivered_orders' => 0, 'returned_orders' => 0],
            'revenue' => ['period_revenue' => 0, 'avg_order_value' => 0],
            'customers' => ['total_customers' => 0, 'period_customers' => 0],
            'recent_orders' => [],
            'support_tickets' => []
        ];
        $chartData = [
            'daily_sales' => [],
            'weekly_sales' => [],
            'monthly_sales' => [],
            'top_products' => [],
            'order_status' => [],
            'shipping_status' => [],
            'customer_acquisition' => []
        ];
    }
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../lg');
}

// Calculate percentage changes
function calculatePercentageChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

// Format currency
function formatCurrency($amount) {
    $amount = is_null($amount) ? 0 : $amount;
    return number_format((float)$amount, 2, ',', ' ') . ' D.H';
}

// Format percentage
function formatPercentage($value) {
    return round((float)$value, 2) . '%';
}

// Format status badge
function getStatusBadge($status) {
    $badges = [
        'complete' => 'Livrée',
        'pending' => 'En cours',
        'processing' => 'Préparation',
        'cancelled' => 'Annulée',
        'shipped' => 'Expédiée',
        'delivered' => 'Livrée',
        'returned' => 'Retournée'
    ];
    return $badges[$status] ?? ucfirst($status);
}

function getStatusClass($status) {
    $classes = [
        'complete' => 'complete',
        'pending' => 'pending',
        'processing' => 'processing',
        'cancelled' => 'cancelled',
        'shipped' => 'processing',
        'delivered' => 'complete',
        'returned' => 'cancelled'
    ];
    return $classes[$status] ?? 'pending';
}

// Get shipping status class
function getShippingStatusClass($status) {
    $classes = [
        'delivered' => 'complete',
        'shipped' => 'processing',
        'pending' => 'pending',
        'returned' => 'cancelled',
        'lost' => 'cancelled'
    ];
    return $classes[$status] ?? 'pending';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tableau de bord OBG - Gestion des commandes e-commerce">
    <title>Tableau de bord | OBG</title>
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        
        fbq('init', '1024222953100967');
        fbq('track', 'ViewContent');
    </script>
    <noscript>
        <img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=1024222953100967&ev=PageView&noscript=1"/>
    </noscript>

    <!-- End Meta Pixel Code -->
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'dashboard';
    require_once('../assets/sidebar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">

        <!-- ==================== RESTYLED DASHBOARD ==================== -->
        <div class="dashboard-container">

            <!-- Verification Banner (unchanged logic, restyled) -->
            <?php if (!$isVerified): ?>
            <div class="verification-banner-modern">
                <div class="verification-banner-inner">
                    <div class="banner-icon-wrapper">
                        <span class="banner-icon">📬</span>
                    </div>
                    <div class="banner-content">
                        <h4>Vérification d'email requise</h4>
                        <p>Veuillez vérifier votre adresse email <strong><?php echo htmlspecialchars($userEmail); ?></strong> pour accéder à toutes les fonctionnalités.</p>
                        <div id="verificationMessage" class="verification-alert" style="display: none;"></div>
                    </div>
                    <div class="banner-actions">
                        <button type="button" id="resendVerificationBtn" class="btn-verify">
                            <i class="bi bi-envelope-paper"></i>
                            <span id="resendBtnText">Renvoyer l'email</span>
                        </button>
                        <button type="button" class="btn-close-banner" onclick="closeVerificationBanner()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Subscription Alerts (restyled) -->
            <?php if (!empty($subscriptionAlerts)): ?>
            <div class="subscription-alerts-modern">
                <?php foreach ($subscriptionAlerts as $alert): ?>
                    <div class="alert-card alert-<?php echo $alert['type']; ?>">
                        <div class="alert-card-icon">
                            <?php if ($alert['type'] === 'danger'): ?>
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            <?php elseif ($alert['type'] === 'warning'): ?>
                                <i class="bi bi-clock-fill"></i>
                            <?php else: ?>
                                <i class="bi bi-info-circle-fill"></i>
                            <?php endif; ?>
                        </div>
                        <div class="alert-card-content">
                            <h4><?php echo htmlspecialchars($alert['title']); ?></h4>
                            <p><?php echo htmlspecialchars($alert['message']); ?></p>
                        </div>
                        <div class="alert-card-actions">
                            <a href="<?php echo htmlspecialchars($alert['action_url']); ?>" class="alert-action-btn primary">
                                <?php echo htmlspecialchars($alert['action_text']); ?>
                            </a>
                            <?php if ($alert['priority'] === 'medium'): ?>
                                <button class="alert-action-btn ghost" onclick="dismissSubscriptionAlert(this, 7)">Ignorer 7 jours</button>
                            <?php endif; ?>
                        </div>
                        <button class="alert-close-btn" onclick="closeSubscriptionAlert(this)">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Top Navigation Bar (restyled) -->
            <nav class="top-navbar-modern">
                <div class="nav-left">
                    <div class="search-bar-modern">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="Rechercher...">
                    </div>
                </div>
                <div class="nav-right">
                    <div class="notifications-modern">
                        <button class="notif-btn" data-tooltip="Notifications">
                            <i class="bi bi-bell"></i>
                            <span class="notif-badge hidden">0</span>
                        </button>
                        <div class="notif-dropdown">
                            <div class="notif-header">
                                <span>Notifications</span>
                                <button class="mark-all-read">Marquer tout comme lu</button>
                            </div>
                            <div class="notif-list">
                                <div class="loading">Chargement...</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isLoggedIn): ?>
                    <div class="user-menu-modern">
                        <button class="user-btn-modern" onclick="toggleUserMenu()">
                            <span class="user-avatar"><?php echo strtoupper(substr($user[0]['name'], 0, 1)); ?></span>
                            <span class="user-name"><?php echo htmlspecialchars($user[0]['name']); ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="user-dropdown-modern" id="userDropdown">
                            <a href="./profile.php"><i class="bi bi-person"></i> Mon Profil</a>
                            <a href="./settings.php"><i class="bi bi-gear"></i> Paramètres</a>
                            <hr>
                            <a href="?logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="auth-links-modern">
                        <a href="../login.php" class="auth-link">Connexion</a>
                        <a href="../register.php" class="auth-link register">S'inscrire</a>
                    </div>
                    <?php endif; ?>
                </div>
            </nav>

            <!-- Dashboard Content -->
            <div class="dashboard-content-modern">
                <?php if (!$isLoggedIn): ?>
                    <!-- Not logged in view -->
                    <div class="auth-notice-modern">
                        <h2>Bienvenue sur OBGECOM</h2>
                        <p>Connectez-vous pour accéder à votre tableau de bord et gérer vos commandes e-commerce</p>
                        <div class="auth-buttons-modern">
                            <a href="../login.php" class="btn-auth primary">Se connecter</a>
                            <a href="../register.php" class="btn-auth secondary">Créer un compte</a>
                            <a href="../plans.php" class="btn-auth secondary">Voir nos plans</a>
                            <a href="../contact.php" class="btn-auth secondary">Nous contacter</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Logged in user dashboard -->
                    <div class="welcome-section">
                        <div class="welcome-greeting">
                            <h2>Bonjour <?php echo htmlspecialchars($user[0]['name']); ?> 👋</h2>
                            <p>Voici un aperçu de votre activité récente. Vous avez <strong><?php echo $dashboardData['filtered_orders']; ?></strong> commandes cette période.</p>
                        </div>
                        <div class="welcome-date">
                            <span><?php echo date('d/m/Y'); ?></span>
                        </div>
                    </div>

                    <!-- Filters (restyled) -->
                    <div class="filters-modern">
                        <form method="get" class="filter-form-modern">
                            <div class="filter-group-modern">
                                <label for="store">Boutique</label>
                                <select id="store" name="store" class="filter-select">
                                    <option value="">Toutes les boutiques</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?php echo $store['id']; ?>" <?php echo isset($_GET['store']) && $_GET['store'] == $store['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($store['storeName']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group-modern">
                                <label for="date-range">Période</label>
                                <select id="date-range" name="date_range" class="filter-select">
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
                            
                            <div class="filter-group-modern custom-date-range" id="custom-date-range" style="<?php echo $dateRange == 'custom' ? '' : 'display: none;'; ?>">
                                <label for="start-date">Du</label>
                                <input type="date" id="start-date" name="start_date" class="filter-input" value="<?php echo $startDate; ?>">
                                
                                <label for="end-date">Au</label>
                                <input type="date" id="end-date" name="end_date" class="filter-input" value="<?php echo $endDate; ?>">
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="filter-btn primary">Appliquer</button>
                                <button type="button" class="filter-btn ghost" onclick="window.location.href='home.php'">Réinitialiser</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Stats Cards (restyled) -->
                    <div class="stats-grid">
                        <div class="stat-card-modern">
                            <div class="stat-card-header">
                                <span class="stat-icon" style="background: #e8f0fe; color: #1a73e8;">
                                    <i class="bi bi-box-seam"></i>
                                </span>
                                <span class="stat-change positive">+12%</span>
                            </div>
                            <div class="stat-value"><?php echo $dashboardData['total_orders']; ?></div>
                            <div class="stat-label">Commandes totales</div>
                            <div class="stat-sub"><?php echo $dashboardData['filtered_orders']; ?> cette période</div>
                        </div>
                        
                        <div class="stat-card-modern">
                            <div class="stat-card-header">
                                <span class="stat-icon" style="background: #e6f4ea; color: #137333;">
                                    <i class="bi bi-currency-dollar"></i>
                                </span>
                                <span class="stat-change positive">+8%</span>
                            </div>
                            <div class="stat-value"><?php echo formatCurrency($dashboardData['filtered_revenue']); ?></div>
                            <div class="stat-label">Chiffre d'affaires</div>
                            <div class="stat-sub">Panier moyen: <?php echo formatCurrency($dashboardData['revenue']['avg_order_value']); ?></div>
                        </div>
                        
                        <div class="stat-card-modern">
                            <div class="stat-card-header">
                                <span class="stat-icon" style="background: #fef3e2; color: #b65a00;">
                                    <i class="bi bi-check-circle"></i>
                                </span>
                                <span class="stat-change <?php echo $dashboardData['confirmation_rate'] >= 80 ? 'positive' : 'negative'; ?>">
                                    <?php echo $dashboardData['confirmation_rate'] >= 80 ? '+' : ''; ?><?php echo formatPercentage($dashboardData['confirmation_rate']); ?>
                                </span>
                            </div>
                            <div class="stat-value"><?php echo formatPercentage($dashboardData['confirmation_rate']); ?></div>
                            <div class="stat-label">Taux de confirmation</div>
                            <div class="stat-sub"><?php echo $dashboardData['confirmed_orders']; ?> facturées</div>
                        </div>
                        
                        <div class="stat-card-modern">
                            <div class="stat-card-header">
                                <span class="stat-icon" style="background: #fce8e6; color: #c62828;">
                                    <i class="bi bi-truck"></i>
                                </span>
                                <span class="stat-change <?php echo $dashboardData['delivery_rate'] >= 90 ? 'positive' : 'negative'; ?>">
                                    <?php echo $dashboardData['delivery_rate'] >= 90 ? '+' : ''; ?><?php echo formatPercentage($dashboardData['delivery_rate']); ?>
                                </span>
                            </div>
                            <div class="stat-value"><?php echo formatPercentage($dashboardData['delivery_rate']); ?></div>
                            <div class="stat-label">Taux de livraison</div>
                            <div class="stat-sub"><?php echo $dashboardData['delivered_orders']; ?> livrées</div>
                        </div>
                        
                        <div class="stat-card-modern">
                            <div class="stat-card-header">
                                <span class="stat-icon" style="background: #f3e8fd; color: #7b1fa2;">
                                    <i class="bi bi-people"></i>
                                </span>
                                <span class="stat-change positive">+5%</span>
                            </div>
                            <div class="stat-value"><?php echo $dashboardData['customers']['total_customers']; ?></div>
                            <div class="stat-label">Clients</div>
                            <div class="stat-sub"><?php echo $dashboardData['customers']['period_customers']; ?> cette période</div>
                        </div>
                    </div>
                    
                    <!-- Charts Section (restyled) -->
                    <div class="charts-grid">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h3>Évolution des ventes</h3>
                                <div class="chart-periods">
                                    <button class="period-btn active" onclick="switchChart('daily')">Journalier</button>
                                    <button class="period-btn" onclick="switchChart('weekly')">Hebdomadaire</button>
                                    <button class="period-btn" onclick="switchChart('monthly')">Mensuel</button>
                                </div>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h3>Statut des commandes</h3>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Secondary Charts -->
                    <div class="charts-grid-secondary">
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h3>Statut de livraison</h3>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="shippingChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="chart-card">
                            <div class="chart-card-header">
                                <h3>Top produits</h3>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="productsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Top Clients Table (restyled) -->
                    <div class="table-card">
                        <div class="table-card-header">
                            <h3><i class="bi bi-star"></i> Top Clients</h3>
                            <a href="./customers.php" class="table-view-all">
                                Voir tout <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                        
                        <div class="table-search">
                            <i class="bi bi-search"></i>
                            <input type="text" id="client-search" placeholder="Rechercher un client...">
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th class="sortable" data-sort="name">Client</th>
                                        <th class="sortable" data-sort="phone">Téléphone</th>
                                        <th class="sortable" data-sort="city">Ville</th>
                                        <th class="sortable" data-sort="orders">Commandes</th>
                                        <th class="sortable" data-sort="revenue">Chiffre d'Affaires</th>
                                    </tr>
                                </thead>
                                <tbody id="clients-tbody">
                                    <?php if (!empty($chartData['top_clients'])): ?>
                                        <?php foreach ($chartData['top_clients'] as $client): ?>
                                            <tr>
                                                <td>
                                                    <div class="client-info">
                                                        <span class="client-name"><?php echo htmlspecialchars($client['name'] ?: 'Client sans nom'); ?></span>
                                                        <span class="client-email"><?php echo htmlspecialchars($client['email'] ?: 'Aucun email'); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="client-phone">
                                                        <?php 
                                                        $phoneQuery = "SELECT customer_phone FROM orders 
                                                                    WHERE customer_email = ? AND user_id = ? 
                                                                    AND customer_phone IS NOT NULL 
                                                                    LIMIT 1";
                                                        $phoneResult = $db->getThisQuery($phoneQuery, [$client['email'], $currentUserId]);
                                                        echo $phoneResult ? htmlspecialchars($phoneResult[0]['customer_phone']) : 'N/A';
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="client-city">
                                                        <?php 
                                                        $cityQuery = "SELECT customer_ville FROM orders 
                                                                    WHERE customer_email = ? AND user_id = ? 
                                                                    AND customer_ville IS NOT NULL 
                                                                    LIMIT 1";
                                                        $cityResult = $db->getThisQuery($cityQuery, [$client['email'], $currentUserId]);
                                                        echo $cityResult ? htmlspecialchars($cityResult[0]['customer_ville']) : 'N/A';
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><span class="order-badge"><?php echo $client['order_count']; ?></span></td>
                                                <td class="revenue-cell"><?php echo formatCurrency($client['total_spent']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="empty-state">
                                                <div class="empty-state-inner">
                                                    <i class="bi bi-people"></i>
                                                    <p>Aucun client trouvé</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="table-footer">
                            <div class="entries-info">
                                Affichage de <?php echo min(1, count($chartData['top_clients'])); ?> à <?php echo count($chartData['top_clients']); ?> sur <?php echo count($chartData['top_clients']); ?> clients
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <style>
        /* ============================================================
           RESTYLED DASHBOARD — MODERN, CLEAN, PROFESSIONAL
           ============================================================ */
        
        /* --- Reset / Base --- */
        .dashboard-container {
            padding: 24px 32px 40px;
            max-width: 1440px;
            margin: 0 auto;
            font-family: 'Inter', sans-serif;
        }

        /* --- Verification Banner Modern --- */
        .verification-banner-modern {
            background: #fef9e7;
            border: 1px solid #f9e79f;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            position: relative;
        }
        .verification-banner-inner {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .banner-icon-wrapper {
            flex-shrink: 0;
        }
        .banner-icon {
            font-size: 24px;
        }
        .banner-content {
            flex: 1;
        }
        .banner-content h4 {
            margin: 0 0 4px 0;
            font-size: 15px;
            font-weight: 600;
            color: #7d6608;
        }
        .banner-content p {
            margin: 0;
            font-size: 13px;
            color: #7d6608;
        }
        .banner-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .btn-verify {
            background: #f1c40f;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #7d6608;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-verify:hover {
            background: #d4ac0d;
        }
        .btn-close-banner {
            background: none;
            border: none;
            color: #7d6608;
            cursor: pointer;
            font-size: 18px;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .btn-close-banner:hover {
            background: rgba(0,0,0,0.05);
        }

        /* --- Subscription Alerts Modern --- */
        .subscription-alerts-modern {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }
        .alert-card {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 20px;
            border-radius: 12px;
            position: relative;
            flex-wrap: wrap;
        }
        .alert-card.alert-danger {
            background: #fdecea;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-card.alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .alert-card.alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .alert-card-icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        .alert-card-content {
            flex: 1;
        }
        .alert-card-content h4 {
            margin: 0 0 2px 0;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-card-content p {
            margin: 0;
            font-size: 13px;
            opacity: 0.85;
        }
        .alert-card-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .alert-action-btn {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .alert-action-btn.primary {
            background: #007bff;
            color: #fff;
        }
        .alert-action-btn.primary:hover {
            background: #0056b3;
        }
        .alert-action-btn.ghost {
            background: transparent;
            border: 1px solid currentColor;
            color: inherit;
        }
        .alert-action-btn.ghost:hover {
            background: rgba(0,0,0,0.05);
        }
        .alert-close-btn {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 16px;
            opacity: 0.6;
            padding: 4px;
        }
        .alert-close-btn:hover {
            opacity: 1;
        }

        /* --- Top Navbar Modern --- */
        .top-navbar-modern {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0 20px 0;
            border-bottom: 1px solid #eef0f2;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .nav-left {
            display: flex;
            align-items: center;
            flex: 1;
        }
        .search-bar-modern {
            display: flex;
            align-items: center;
            background: #f5f7fa;
            border-radius: 10px;
            padding: 0 14px;
            border: 1px solid #eef0f2;
            transition: border 0.2s;
            max-width: 360px;
            width: 100%;
        }
        .search-bar-modern:focus-within {
            border-color: #4a6cf7;
            background: #fff;
        }
        .search-bar-modern i {
            color: #9aa0a9;
            font-size: 16px;
        }
        .search-bar-modern input {
            border: none;
            background: transparent;
            padding: 10px 12px;
            font-size: 14px;
            width: 100%;
            outline: none;
            font-family: 'Inter', sans-serif;
        }
        .search-bar-modern input::placeholder {
            color: #b0b6bf;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .notifications-modern {
            position: relative;
        }
        .notif-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: #5b677a;
            cursor: pointer;
            padding: 6px;
            position: relative;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .notif-btn:hover {
            background: #f5f7fa;
        }
        .notif-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #e74c3c;
            color: #fff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notif-badge.hidden {
            display: none;
        }
        .notif-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            width: 320px;
            max-height: 400px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .notif-dropdown.show {
            display: block;
            animation: slideDown 0.2s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notif-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #eef0f2;
            font-weight: 600;
            font-size: 14px;
        }
        .notif-header .mark-all-read {
            background: none;
            border: none;
            color: #4a6cf7;
            font-size: 12px;
            cursor: pointer;
            font-weight: 500;
        }
        .notif-list {
            padding: 8px 0;
        }
        .notif-list .loading {
            padding: 24px;
            text-align: center;
            color: #9aa0a9;
            font-size: 14px;
        }

        .user-menu-modern {
            position: relative;
        }
        .user-btn-modern {
            display: flex;
            align-items: center;
            gap: 10px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 8px 4px 4px;
            border-radius: 10px;
            transition: background 0.2s;
        }
        .user-btn-modern:hover {
            background: #f5f7fa;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #4a6cf7;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
        }
        .user-name {
            font-size: 14px;
            font-weight: 500;
            color: #1a2332;
        }
        .user-btn-modern i {
            color: #9aa0a9;
            font-size: 14px;
        }
        .user-dropdown-modern {
            position: absolute;
            top: 100%;
            right: 0;
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            min-width: 200px;
            padding: 8px 0;
            z-index: 1000;
            display: none;
        }
        .user-dropdown-modern.show {
            display: block;
            animation: slideDown 0.2s ease;
        }
        .user-dropdown-modern a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            color: #1a2332;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        .user-dropdown-modern a:hover {
            background: #f5f7fa;
        }
        .user-dropdown-modern hr {
            margin: 6px 0;
            border: none;
            border-top: 1px solid #eef0f2;
        }
        .user-dropdown-modern a i {
            width: 18px;
            color: #5b677a;
        }

        .auth-links-modern {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .auth-links-modern .auth-link {
            font-size: 14px;
            color: #5b677a;
            text-decoration: none;
            font-weight: 500;
        }
        .auth-links-modern .auth-link.register {
            background: #4a6cf7;
            color: #fff;
            padding: 8px 20px;
            border-radius: 8px;
        }
        .auth-links-modern .auth-link.register:hover {
            background: #3a56d4;
        }

        /* --- Welcome Section --- */
        .welcome-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .welcome-greeting h2 {
            margin: 0 0 4px 0;
            font-size: 24px;
            font-weight: 700;
            color: #1a2332;
        }
        .welcome-greeting p {
            margin: 0;
            font-size: 15px;
            color: #5b677a;
        }
        .welcome-greeting p strong {
            color: #1a2332;
        }
        .welcome-date {
            font-size: 14px;
            color: #5b677a;
            background: #f5f7fa;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
        }

        /* --- Filters Modern --- */
        .filters-modern {
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .filter-form-modern {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 16px;
        }
        .filter-group-modern {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .filter-group-modern label {
            font-size: 12px;
            font-weight: 600;
            color: #5b677a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .filter-select, .filter-input {
            padding: 8px 12px;
            border: 1px solid #eef0f2;
            border-radius: 8px;
            font-size: 13px;
            background: #fafbfc;
            color: #1a2332;
            font-family: 'Inter', sans-serif;
            min-width: 140px;
            transition: border 0.2s;
        }
        .filter-select:focus, .filter-input:focus {
            border-color: #4a6cf7;
            outline: none;
            background: #fff;
        }
        .custom-date-range {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-left: auto;
            align-items: center;
        }
        .filter-btn {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .filter-btn.primary {
            background: #4a6cf7;
            color: #fff;
        }
        .filter-btn.primary:hover {
            background: #3a56d4;
        }
        .filter-btn.ghost {
            background: transparent;
            border: 1px solid #eef0f2;
            color: #5b677a;
        }
        .filter-btn.ghost:hover {
            background: #f5f7fa;
        }

        /* --- Stats Grid --- */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card-modern {
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 12px;
            padding: 18px 20px;
            transition: box-shadow 0.2s;
        }
        .stat-card-modern:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .stat-change {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            background: #e6f4ea;
            color: #137333;
        }
        .stat-change.negative {
            background: #fdecea;
            color: #c62828;
        }
        .stat-value {
            font-size: 26px;
            font-weight: 700;
            color: #1a2332;
            line-height: 1.2;
        }
        .stat-label {
            font-size: 13px;
            color: #5b677a;
            margin-top: 4px;
        }
        .stat-sub {
            font-size: 12px;
            color: #9aa0a9;
            margin-top: 4px;
        }

        /* --- Charts --- */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .charts-grid-secondary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 32px;
        }
        .chart-card {
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 12px;
            padding: 20px;
            overflow: hidden;
        }
        .chart-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 8px;
        }
        .chart-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #1a2332;
        }
        .chart-periods {
            display: flex;
            gap: 4px;
        }
        .period-btn {
            background: none;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #5b677a;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }
        .period-btn:hover {
            background: #f5f7fa;
        }
        .period-btn.active {
            background: #4a6cf7;
            color: #fff;
        }
        .chart-wrapper {
            position: relative;
            height: 280px;
        }
        .chart-wrapper canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* --- Table Card --- */
        .table-card {
            background: #fff;
            border: 1px solid #eef0f2;
            border-radius: 12px;
            overflow: hidden;
        }
        .table-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #eef0f2;
        }
        .table-card-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #1a2332;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-card-header h3 i {
            color: #f1c40f;
        }
        .table-view-all {
            font-size: 13px;
            font-weight: 500;
            color: #4a6cf7;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: gap 0.2s;
        }
        .table-view-all:hover {
            gap: 8px;
            color: #3a56d4;
        }
        .table-search {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #f5f7fa;
        }
        .table-search i {
            color: #9aa0a9;
            font-size: 16px;
            margin-right: 12px;
        }
        .table-search input {
            border: none;
            background: transparent;
            padding: 6px 0;
            font-size: 14px;
            width: 100%;
            outline: none;
            font-family: 'Inter', sans-serif;
        }
        .table-search input::placeholder {
            color: #b0b6bf;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table-modern {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .table-modern thead th {
            text-align: left;
            padding: 12px 16px;
            font-weight: 600;
            color: #5b677a;
            border-bottom: 1px solid #eef0f2;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .table-modern tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #f5f7fa;
            color: #1a2332;
        }
        .table-modern tbody tr:hover {
            background: #fafbfc;
        }
        .client-info {
            display: flex;
            flex-direction: column;
        }
        .client-name {
            font-weight: 500;
        }
        .client-email {
            font-size: 12px;
            color: #9aa0a9;
        }
        .client-phone, .client-city {
            font-size: 13px;
            color: #5b677a;
        }
        .order-badge {
            background: #f5f7fa;
            padding: 4px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
        .revenue-cell {
            font-weight: 600;
            color: #137333;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        .empty-state-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: #9aa0a9;
        }
        .empty-state-inner i {
            font-size: 36px;
        }
        .table-footer {
            padding: 12px 20px;
            border-top: 1px solid #eef0f2;
            display: flex;
            justify-content: flex-end;
        }
        .entries-info {
            font-size: 13px;
            color: #9aa0a9;
        }

        /* --- Auth Notice --- */
        .auth-notice-modern {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 12px;
            border: 1px solid #eef0f2;
        }
        .auth-notice-modern h2 {
            font-size: 28px;
            font-weight: 700;
            color: #1a2332;
            margin-bottom: 12px;
        }
        .auth-notice-modern p {
            font-size: 16px;
            color: #5b677a;
            margin-bottom: 24px;
        }
        .auth-buttons-modern {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-auth {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-auth.primary {
            background: #4a6cf7;
            color: #fff;
        }
        .btn-auth.primary:hover {
            background: #3a56d4;
        }
        .btn-auth.secondary {
            background: #f5f7fa;
            color: #1a2332;
            border: 1px solid #eef0f2;
        }
        .btn-auth.secondary:hover {
            background: #eef0f2;
        }

        /* --- Responsive --- */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            .charts-grid-secondary {
                grid-template-columns: 1fr;
            }
            .filter-actions {
                margin-left: 0;
                width: 100%;
                justify-content: flex-start;
            }
        }
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 16px;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            .top-navbar-modern {
                flex-direction: column;
                align-items: stretch;
            }
            .search-bar-modern {
                max-width: 100%;
            }
            .nav-right {
                justify-content: space-between;
            }
            .filter-form-modern {
                flex-direction: column;
                align-items: stretch;
            }
            .custom-date-range {
                flex-direction: column;
                align-items: stretch;
            }
            .banner-actions {
                width: 100%;
                justify-content: flex-start;
            }
            .alert-card {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        @media (max-width: 480px) {
            .stat-card-modern {
                padding: 14px 16px;
            }
            .stat-value {
                font-size: 20px;
            }
            .welcome-greeting h2 {
                font-size: 20px;
            }
        }
    </style>

    <!-- Flatpickr for date range -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/fr.min.js"></script>
    
    <script>
        // ==================== CHART DATA & INIT ====================
        const chartData = <?php echo json_encode($chartData); ?>;
        let salesChart, statusChart, shippingChart, productsChart;
        let currentPeriod = 'daily';
        
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.color = '#5b677a';
        Chart.defaults.borderColor = '#eef0f2';
        
        document.addEventListener('DOMContentLoaded', function() {
            const dateRangeSelect = document.getElementById('date-range');
            const customDateRange = document.getElementById('custom-date-range');
            
            if (dateRangeSelect) {
                dateRangeSelect.addEventListener('change', function() {
                    customDateRange.style.display = this.value === 'custom' ? 'flex' : 'none';
                });
            }
            
            flatpickr("#start-date", { dateFormat: "Y-m-d", locale: "fr", maxDate: new Date() });
            flatpickr("#end-date", { dateFormat: "Y-m-d", locale: "fr", minDate: document.getElementById('start-date').value, maxDate: new Date() });
            
            document.getElementById('start-date')?.addEventListener('change', function() {
                document.getElementById('end-date')._flatpickr.set('minDate', this.value);
            });
            
            initializeCharts();
            
            const sidebarToggle = document.getElementById('open-sidebar');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
        });

        function showNoDataMessage(canvasId, message = 'Aucune donnée disponible') {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width || 400;
            canvas.height = rect.height || 300;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#9aa0a9';
            ctx.font = '16px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('📊', canvas.width / 2, canvas.height / 2 - 20);
            ctx.fillText(message, canvas.width / 2, canvas.height / 2 + 20);
        }

        function getStatusBadge(status) {
            const badges = {
                'complete': 'Livrée', 'pending': 'En cours', 'processing': 'Préparation',
                'cancelled': 'Annulée', 'shipped': 'Expédiée', 'delivered': 'Livrée', 'returned': 'Retournée'
            };
            return badges[status] || status;
        }

        function formatCurrency(amount, short = false) {
            if (short && amount >= 1000) return (amount / 1000).toFixed(1) + 'K';
            return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'MAD' }).format(amount);
        }

        function getSalesChartData(period) {
            let data, labels;
            switch(period) {
                case 'daily':
                    data = chartData.daily_sales || [];
                    labels = data.map(item => new Date(item.date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }));
                    break;
                case 'weekly':
                    data = chartData.weekly_sales || [];
                    labels = data.map(item => item.week_label);
                    break;
                case 'monthly':
                    data = chartData.monthly_sales || [];
                    labels = data.map(item => new Date(item.month + '-01').toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' }));
                    break;
                default:
                    data = chartData.daily_sales || [];
                    labels = data.map(item => new Date(item.date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }));
            }
            return {
                labels: labels,
                datasets: [
                    {
                        label: 'Commandes',
                        data: data.map(item => item.orders_count),
                        borderColor: '#4a6cf7',
                        backgroundColor: 'rgba(74, 108, 247, 0.08)',
                        yAxisID: 'y',
                        pointBackgroundColor: '#4a6cf7',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Revenus (DH)',
                        data: data.map(item => item.revenue),
                        borderColor: '#137333',
                        backgroundColor: 'rgba(19, 115, 51, 0.08)',
                        yAxisID: 'y1',
                        pointBackgroundColor: '#137333',
                        tension: 0.3,
                        fill: true
                    }
                ]
            };
        }

        function initializeCharts() {
            // Sales Chart
            const salesCtx = document.getElementById('salesChart');
            if (salesCtx) {
                const salesData = getSalesChartData('daily');
                const hasData = salesData.datasets.some(d => d.data && d.data.length > 0 && d.data.some(v => v > 0));
                if (!hasData) {
                    showNoDataMessage('salesChart', 'Aucune donnée de ventes');
                } else {
                    salesChart = new Chart(salesCtx, {
                        type: 'line',
                        data: salesData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: true, position: 'top', labels: { usePointStyle: true, padding: 20 } },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            return label + ': ' + (context.datasetIndex === 1 ? formatCurrency(context.raw) : context.raw);
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: { beginAtZero: true, grid: { drawBorder: false }, title: { display: true, text: 'Commandes' } },
                                y1: { beginAtZero: true, grid: { drawOnChartArea: false }, position: 'right', title: { display: true, text: 'Revenus (DH)' }, ticks: { callback: v => formatCurrency(v, true) } },
                                x: { grid: { display: false } }
                            },
                            elements: { line: { tension: 0.3, borderWidth: 2 }, point: { radius: 3, hoverRadius: 5 } },
                            interaction: { mode: 'index', intersect: false }
                        }
                    });
                }
            }

            // Status Chart
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx && chartData.order_status && chartData.order_status.length > 0) {
                statusChart = new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: chartData.order_status.map(item => getStatusBadge(item.status)),
                        datasets: [{
                            data: chartData.order_status.map(item => item.count),
                            backgroundColor: ['#2ecc71', '#f39c12', '#3498db', '#e74c3c', '#9b59b6'],
                            borderWidth: 0,
                            spacing: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '70%',
                        plugins: {
                            legend: { position: 'right', labels: { usePointStyle: true, padding: 16, boxWidth: 10 } },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const pct = chartData.order_status[context.dataIndex].percentage.toFixed(1);
                                        return `${context.label}: ${context.raw} (${pct}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            } else if (statusCtx) {
                showNoDataMessage('statusChart', 'Aucun statut de commande');
            }

            // Shipping Chart
            const shippingCtx = document.getElementById('shippingChart');
            if (shippingCtx && chartData.shipping_status && chartData.shipping_status.length > 0) {
                const colors = ['#2ecc71', '#3498db', '#f39c12', '#e74c3c', '#9b59b6', '#1abc9c'];
                shippingChart = new Chart(shippingCtx, {
                    type: 'pie',
                    data: {
                        labels: chartData.shipping_status.map(item => item.shipping_status),
                        datasets: [{
                            data: chartData.shipping_status.map(item => item.count),
                            backgroundColor: colors.slice(0, chartData.shipping_status.length),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { usePointStyle: true, padding: 16 } },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = total > 0 ? (context.raw / total * 100).toFixed(1) : 0;
                                        return `${context.label}: ${context.raw} (${pct}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            } else if (shippingCtx) {
                showNoDataMessage('shippingChart', 'Aucun statut de livraison');
            }

            // Products Chart
            const productsCtx = document.getElementById('productsChart');
            if (productsCtx && chartData.top_products && chartData.top_products.length > 0) {
                productsChart = new Chart(productsCtx, {
                    type: 'bar',
                    data: {
                        labels: chartData.top_products.slice(0, 10).map(item => item.product_name),
                        datasets: [{
                            label: 'Quantité vendue',
                            data: chartData.top_products.slice(0, 10).map(item => item.total_quantity),
                            backgroundColor: '#4a6cf7',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const item = chartData.top_products[context.dataIndex];
                                        return `Quantité: ${item.total_quantity}\nRevenu: ${formatCurrency(item.total_revenue)}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { drawBorder: false } },
                            x: { display: false }
                        }
                    }
                });
            } else if (productsCtx) {
                showNoDataMessage('productsChart', 'Aucun produit vendu');
            }
        }

        function switchChart(period) {
            currentPeriod = period;
            document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            if (event && event.target) event.target.classList.add('active');
            if (salesChart) {
                salesChart.data = getSalesChartData(period);
                salesChart.update();
            }
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.main-content').classList.toggle('expanded');
        }

        function toggleUserMenu() {
            document.getElementById('userDropdown').classList.toggle('show');
        }
        document.addEventListener('click', function(e) {
            const menu = document.querySelector('.user-menu-modern');
            if (menu && !menu.contains(e.target)) {
                document.getElementById('userDropdown').classList.remove('show');
            }
        });

        // Notification dropdown toggle
        document.querySelector('.notif-btn')?.addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelector('.notif-dropdown').classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            const dropdown = document.querySelector('.notif-dropdown');
            if (dropdown && !dropdown.closest('.notifications-modern').contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // ==================== SORTING & SEARCH FOR TABLE ====================
        (function() {
            const headers = document.querySelectorAll('.sortable');
            const tbody = document.getElementById('clients-tbody');
            if (!tbody) return;
            
            function getCellValue(row, column) {
                switch(column) {
                    case 'name': return row.querySelector('.client-name')?.textContent.trim() || '';
                    case 'phone': return row.querySelector('.client-phone')?.textContent.trim() || '';
                    case 'city': return row.querySelector('.client-city')?.textContent.trim() || '';
                    case 'orders': return parseInt(row.querySelector('.order-badge')?.textContent.trim() || '0');
                    case 'revenue': {
                        const val = row.querySelector('.revenue-cell')?.textContent.trim() || '0 D.H';
                        return parseFloat(val.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
                    }
                    default: return '';
                }
            }
            
            headers.forEach(header => {
                header.addEventListener('click', function() {
                    const column = this.dataset.sort;
                    const isAsc = this.classList.contains('asc');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    if (rows.length === 0 || rows[0].classList.contains('empty-state')) return;
                    
                    headers.forEach(h => h.classList.remove('asc', 'desc'));
                    
                    rows.sort((a, b) => {
                        let aVal = getCellValue(a, column);
                        let bVal = getCellValue(b, column);
                        if (typeof aVal === 'string') {
                            return isAsc ? bVal.localeCompare(aVal) : aVal.localeCompare(bVal);
                        } else {
                            return isAsc ? bVal - aVal : aVal - bVal;
                        }
                    });
                    rows.forEach(row => tbody.appendChild(row));
                    this.classList.toggle('asc', !isAsc);
                    this.classList.toggle('desc', isAsc);
                });
            });
            
            const searchInput = document.getElementById('client-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const term = this.value.toLowerCase();
                    document.querySelectorAll('#clients-tbody tr').forEach(row => {
                        if (row.querySelector('.empty-state')) return;
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(term) ? '' : 'none';
                    });
                });
            }
        })();

        // ==================== VERIFICATION BANNER ====================
        window.closeVerificationBanner = function() {
            document.querySelector('.verification-banner-modern')?.remove();
        };
        window.dismissSubscriptionAlert = function(btn, days) {
            const card = btn.closest('.alert-card');
            card.style.opacity = '0';
            setTimeout(() => card.remove(), 300);
        };
        window.closeSubscriptionAlert = function(btn) {
            const card = btn.closest('.alert-card');
            card.style.opacity = '0';
            setTimeout(() => card.remove(), 300);
        };
    </script>

    <div id="obgecom-assistant-container">
        <?php require_once('./ai_assistant/chat.html') ?>
    </div>
</body>
</html>