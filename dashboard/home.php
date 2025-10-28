<?php
// Database connection and session check
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("./config/init.php");
}

$db = DB::getInstance();

// Check if user is logged in
if(!Session::exists(Config::get('session/session_name'))){
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

        <style>

            .verification-banner {
                background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                border: 1px solid #ffecb5;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 24px;
                position: relative;
                display: flex;
                align-items: flex-start;
                gap: 16px;
                box-shadow: 0 2px 10px rgba(255, 193, 7, 0.1);
                flex-direction: column;
                align-content: flex-start;
                flex-wrap: wrap;
            }

            .verification-content {

                display: flex;
                column-gap: .9em;
            }

            .verification-icon {
                flex-shrink: 0;
                width: 48px;
                height: 48px;
                background: #ffc107;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
            }

            .verification-icon svg {
                width: 24px;
                height: 24px;
            }

            .verification-content {
                flex: 1;
            }

            .verification-text h4 {
                margin: 0 0 8px 0;
                color: #856404;
                font-weight: 600;
            }

            .verification-text p {
                margin: 0;
                color: #856404;
                font-size: 14px;
                line-height: 1.9em;
            }

            .verification-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .resend-form {
                margin: 0;
            }

            .resend-btn {
                background: #ffc107;
                border: none;
                color: #856404;
                padding: 10px 16px;
                border-radius: 6px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.3s ease;
            }

            .resend-btn:hover {
                background: #e0a800;
                transform: translateY(-1px);
            }

            .change-email-btn {
                background: transparent;
                border: 1px solid #ffc107;
                color: #856404;
                padding: 10px 16px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.3s ease;
            }

            .change-email-btn:hover {
                background: #ffc107;
            }

            .close-banner {
                position: absolute;
                top: 12px;
                right: 12px;
                background: none;
                border: none;
                color: #856404;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
            }

            .close-banner:hover {
                background: rgba(255, 193, 7, 0.2);
            }

            .verification-alert {
                padding: 8px 12px;
                border-radius: 4px;
                margin-top: 8px;
                font-size: 13px;
            }

            .verification-alert.success {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .verification-alert.error {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .email-change-form {
                background: white;
                border: 1px solid #e0e0e0;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 24px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }

            .btn-primary {
                background: #007bff;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
            }

            .btn-secondary {
                background: #6c757d;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
            }

            @media (max-width: 768px) {
                .verification-banner {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 12px;
                }
                
                .verification-actions {
                    flex-direction: column;
                    width: 100%;
                }
                
                .resend-btn, .change-email-btn {
                    width: 100%;
                    justify-content: center;
                }
            }
            .verification-info {
                font-size: 13px;
                color: #856404;
                margin: 5px 0;
                font-style: italic;
            }
        </style>

        <?php if (!$isVerified): ?>
            <div class="verification-banner" id="verificationBanner">
                <div class="verification-content">
                    <div class="verification-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                        </svg>
                    </div>
                    <div class="verification-text">
                        <h4>Vérification d'email requise</h4>
                        <p>Veuillez vérifier votre adresse email <strong><?php echo htmlspecialchars($userEmail); ?></strong> pour accéder à toutes les fonctionnalités.</p>
                        <p class="verification-info">Un email de vérification a été envoyé lors de votre inscription. Si vous ne l'avez pas reçu, vous pouvez en demander un nouveau.</p>
                        
                        <!-- Message area for AJAX responses -->
                        <div id="verificationMessage" class="verification-alert" style="display: none;"></div>
                    </div>
                </div>
                <div class="verification-actions">
                    <button type="button" id="resendVerificationBtn" class="resend-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                            <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                        </svg>
                        <span id="resendBtnText">Renvoyer l'email de vérification</span>
                    </button>
                </div>
                
                <button type="button" class="close-banner" onclick="closeVerificationBanner()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
            </div>

            <style>
                .verification-alert {
                    margin-top: 15px;
                    padding: 12px;
                    border-radius: 5px;
                    font-size: 14px;
                }

                .verification-alert.success {
                    background-color: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }

                .verification-alert.error {
                    background-color: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }

                .resend-btn:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }

                .loading {
                    opacity: 0.7;
                    pointer-events: none;
                }
            </style>

            <script>
                class EmailVerification {
                    constructor() {
                        this.resendBtn = document.getElementById('resendVerificationBtn');
                        this.btnText = document.getElementById('resendBtnText');
                        this.messageArea = document.getElementById('verificationMessage');
                        this.banner = document.getElementById('verificationBanner');
                        
                        this.init();
                    }
                    
                    init() {
                        if (this.resendBtn) {
                            this.resendBtn.addEventListener('click', () => this.resendVerification());
                        }
                        
                        // Auto-check verification status every 30 seconds
                        this.checkInterval = setInterval(() => this.checkVerificationStatus(), 30000);
                    }
                    
                    async resendVerification() {
                        if (this.resendBtn.disabled) return;
                        
                        this.setLoading(true);
                        this.hideMessage();
                        
                        try {
                            const response = await fetch('./controllers/dealWithEmailsApi.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    action: 'resend_verification'
                                })
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                this.showMessage(data.message, 'success');
                            } else {
                                this.handleError(data);
                            }
                            
                        } catch (error) {
                            console.error('Resend verification error:', error);
                            this.showMessage('Erreur de connexion. Veuillez réessayer.', 'error');
                        } finally {
                            this.setLoading(false);
                        }
                    }
                    
                    async checkVerificationStatus() {
                        try {
                            const response = await fetch('./controllers/dealWithEmailsApi.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({
                                    action: 'check_verification_status'
                                })
                            });
                            
                            const data = await response.json();
                            
                            if (data.success && data.is_verified) {
                                // User is now verified, hide banner and reload page
                                this.showMessage('Email vérifié avec succès!', 'success');
                                setTimeout(() => {
                                    window.location.reload();
                                }, 2000);
                                clearInterval(this.checkInterval);
                            }
                            
                        } catch (error) {
                            console.error('Verification status check error:', error);
                        }
                    }
                    
                    handleError(data) {
                        switch (data.code) {
                            case 'RATE_LIMITED':
                                this.showMessage(data.error, 'error');
                                this.startCountdown(data.remainingTime);
                                break;
                                
                            case 'ALREADY_VERIFIED':
                                this.showMessage('Votre email est déjà vérifié!', 'success');
                                setTimeout(() => window.location.reload(), 2000);
                                break;
                                
                            case 'EMAIL_SEND_FAILED':
                                this.showMessage('Erreur lors de l\'envoi de l\'email. Veuillez contacter le support si le problème persiste.', 'error');
                                break;
                                
                            default:
                                this.showMessage(data.error || 'Une erreur est survenue.', 'error');
                        }
                    }
                    
                    startCountdown(seconds) {
                        this.resendBtn.disabled = true;
                        let remaining = seconds;
                        
                        const updateCountdown = () => {
                            this.btnText.textContent = `Attendre ${remaining}s avant de renvoyer`;
                            remaining--;
                            
                            if (remaining < 0) {
                                clearInterval(countdownInterval);
                                this.resendBtn.disabled = false;
                                this.btnText.textContent = 'Renvoyer l\'email de vérification';
                            }
                        };
                        
                        updateCountdown();
                        const countdownInterval = setInterval(updateCountdown, 1000);
                    }
                    
                    setLoading(isLoading) {
                        if (isLoading) {
                            this.resendBtn.disabled = true;
                            this.resendBtn.classList.add('loading');
                            this.btnText.textContent = 'Envoi en cours...';
                        } else {
                            this.resendBtn.disabled = false;
                            this.resendBtn.classList.remove('loading');
                            this.btnText.textContent = 'Renvoyer l\'email de vérification';
                        }
                    }
                    
                    showMessage(message, type) {
                        this.messageArea.textContent = message;
                        this.messageArea.className = `verification-alert ${type}`;
                        this.messageArea.style.display = 'block';
                        
                        // Auto-hide success messages after 5 seconds
                        if (type === 'success') {
                            setTimeout(() => this.hideMessage(), 5000);
                        }
                    }
                    
                    hideMessage() {
                        this.messageArea.style.display = 'none';
                    }
                    
                    destroy() {
                        if (this.checkInterval) {
                            clearInterval(this.checkInterval);
                        }
                    }
                }

                // Initialize when DOM is ready
                document.addEventListener('DOMContentLoaded', function() {
                    window.emailVerification = new EmailVerification();
                });

                // Clean up on page unload
                window.addEventListener('beforeunload', function() {
                    if (window.emailVerification) {
                        window.emailVerification.destroy();
                    }
                });

                function closeVerificationBanner() {
                    document.getElementById('verificationBanner').style.display = 'none';
                    
                    // Stop checking verification status if banner is closed
                    if (window.emailVerification) {
                        window.emailVerification.destroy();
                    }
                }
            </script>
            
        <?php endif; ?>

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
                <input type="text" placeholder="Rechercher...">
                <button class="search-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M11.742 10.344a6.5 6.5 0 1 1-4.546 2.914.5.5 0 0 0-.908-.417A6 6 0 1 0 8 2v1z"/>
                        <path d="M8 4.466V.534a.25.25 0 0 0-.41-.192L5.23 2.308a.25.25 0 0 0 0 .384l2.36 1.966A.25.25 0 0 0 8 4.466z"/>
                    </svg>
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
                
                <?php if ($isLoggedIn): ?>
                <div class="user-menu">
                    <button class="user-btn" onclick="toggleUserMenu()">
                        <?php echo strtoupper(substr($user[0]['name'], 0, 1)); ?>
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="./profile.php">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                                <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                            </svg>
                            Mon Profil
                        </a>
                        <a href="./settings.php">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872l-.1-.34zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/>
                            </svg>
                            Paramètres
                        </a>
                        <hr>
                        <a href="?logout">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                                <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                            </svg>
                            Déconnexion
                        </a>
                    </div>
                </div>
                <?php else: ?>
                <div class="auth-links">
                    <a href="../login.php" class="auth-link">Connexion</a>
                    <a href="../register.php" class="auth-link register">S'inscrire</a>
                </div>
                <?php endif; ?>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <?php if (!$isLoggedIn): ?>
                <!-- Not logged in view -->
                <div class="auth-notice">
                    <h2>Bienvenue sur OBGECOM</h2>
                    <p>Connectez-vous pour accéder à votre tableau de bord et gérer vos commandes e-commerce</p>
                    <div class="auth-buttons">
                        <a href="../login.php" class="auth-btn primary">Se connecter</a>
                        <a href="../register.php" class="auth-btn secondary">Créer un compte</a>
                        <a href="../plans.php" class="auth-btn secondary">Voir nos plans</a>
                        <a href="../contact.php" class="auth-btn secondary">Nous contacter</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Logged in user dashboard -->
                <div class="welcome-message">
                    <div class="welcome-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                        </svg>
                    </div>
                    <div class="welcome-content">
                        <h3>Bonjour <?php echo htmlspecialchars($user[0]['name']); ?> !</h3>
                        <p>Voici un aperçu de votre activité récente. Vous avez <?php echo $dashboardData['filtered_orders']; ?> commandes cette période.</p>
                    </div>
                </div>
                
                <div class="dashboard-header">
                    <h2>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M5.5 2A3.5 3.5 0 0 0 2 5.5v5A3.5 3.5 0 0 0 5.5 14h5a3.5 3.5 0 0 0 3.5-3.5V8a.5.5 0 0 1 1 0v2.5a4.5 4.5 0 0 1-4.5 4.5h-5A4.5 4.5 0 0 1 1 10.5v-5A4.5 4.5 0 0 1 5.5 1H8a.5.5 0 0 1 0 1H5.5z"/>
                            <path d="M16 3a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                        </svg>
                        Tableau de bord
                    </h2>
                </div>
                
                <!-- Filters -->
                <div class="dashboard-filters">
                    <form method="get" class="filter-form">
                        <div class="filter-group">
                            <label for="store">Boutique</label>
                            <select id="store" name="store" class="form-select">
                                <option value="">Toutes les boutiques</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?php echo $store['id']; ?>" <?php echo isset($_GET['store']) && $_GET['store'] == $store['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($store['storeName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
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
                            <input type="date" id="start-date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                            
                            <label for="end-date">Au</label>
                            <input type="date" id="end-date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                        </div>
                        
                        <button type="submit" class="filter-btn">Appliquer</button>
                        <button type="button" class="filter-btn reset" onclick="window.location.href='dashboard.php'">Réinitialiser</button>
                    </form>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-cards">
                    <!-- Total Orders -->
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Commandes</h3>
                            <span class="stat-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                                </svg>
                            </span>
                        </div>
                        <div class="stat-card-value"><?php echo $dashboardData['total_orders']; ?></div>
                        <div class="stat-card-description">
                            <span><?php echo $dashboardData['filtered_orders']; ?> cette période</span>
                        </div>
                    </div>
                    
                    <!-- Revenue -->
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Chiffre d'affaires</h3>
                            <span class="stat-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M11 15a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm5-4a5 5 0 1 1-10 0 5 5 0 0 1 10 0z"/>
                                    <path d="M9.438 11.944c.047.596.518 1.06 1.363 1.116v.44h.375v-.443c.875-.061 1.386-.529 1.386-1.207 0-.618-.39-.936-1.09-1.1l-.296-.07v-1.2c.376.043.614.248.671.532h.658c-.047-.575-.54-1.024-1.329-1.073V8.5h-.375v.45c-.747.073-1.255.522-1.255 1.158 0 .562.378.92 1.007 1.066l.248.061v1.272c-.384-.058-.639-.27-.696-.563h-.668zm1.36-1.354c-.369-.085-.569-.26-.569-.522 0-.294.216-.514.572-.578v1.1h-.003zm.432.746c.449.104.655.272.655.569 0 .339-.257.571-.709.614v-1.195l.054.012z"/>
                                </svg>
                            </span>
                        </div>
                        <div class="stat-card-value"><?php echo formatCurrency($dashboardData['filtered_revenue']); ?></div>
                        <div class="stat-card-description">
                            <span>Panier moyen: <?php echo formatCurrency($dashboardData['revenue']['avg_order_value']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Confirmation Rate -->
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Taux de confirmation</h3>
                            <span class="stat-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z"/>
                                </svg>
                            </span>
                        </div>
                        <div class="stat-card-value"><?php echo formatPercentage($dashboardData['confirmation_rate']); ?></div>
                        <div class="stat-card-change <?php echo $dashboardData['confirmation_rate'] >= 80 ? 'positive' : ($dashboardData['confirmation_rate'] >= 50 ? '' : 'negative'); ?>">
                            <?php echo $dashboardData['confirmed_orders']; ?> facturées
                        </div>
                    </div>
                    
                    <!-- Delivery Rate -->
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Taux de livraison</h3>
                            <span class="stat-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5v-7zm1.294 7.456A1.999 1.999 0 0 1 4.732 11h5.536a2.01 2.01 0 0 1 .732-.732V3.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .294.456zM12 10a2 2 0 0 1 1.732 1h.768a.5.5 0 0 0 .5-.5V8.35a.5.5 0 0 0-.11-.312l-1.48-1.85A.5.5 0 0 0 13.02 6H12v4zm-9 1a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm9 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
                                </svg>
                            </span>
                        </div>
                        <div class="stat-card-value"><?php echo formatPercentage($dashboardData['delivery_rate']); ?></div>
                        <div class="stat-card-change <?php echo $dashboardData['delivery_rate'] >= 90 ? 'positive' : ($dashboardData['delivery_rate'] >= 70 ? '' : 'negative'); ?>">
                            <?php echo $dashboardData['delivered_orders']; ?> livrées
                        </div>
                    </div>
                    
                    <!-- Customers -->
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <h3>Clients</h3>
                            <span class="stat-card-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1h8zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002a.274.274 0 0 1-.014.002H7.022zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM6.936 9.28a5.88 5.88 0 0 0-1.23-.247A7.35 7.35 0 0 0 5 9c-4 0-5 3-5 4 0 .667.333 1 1 1h4.216A2.238 2.238 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816zM4.92 10A5.493 5.493 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275zM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0zm3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
                                </svg>
                            </span>
                        </div>
                        <div class="stat-card-value"><?php echo $dashboardData['customers']['total_customers']; ?></div>
                        <div class="stat-card-description">
                            <span><?php echo $dashboardData['customers']['period_customers']; ?> cette période</span>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Section -->
                <div class="charts-section">
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Évolution des ventes</h3>
                            <div class="chart-controls">
                                <button class="chart-control active" onclick="switchChart('daily')">Journalier</button>
                                <button class="chart-control" onclick="switchChart('weekly')">Hebdomadaire</button>
                                <button class="chart-control" onclick="switchChart('monthly')">Mensuel</button>
                            </div>
                        </div>
                        <div class="chart-canvas">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3>Statut des commandes</h3>
                        </div>
                        <div class="chart-canvas">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Secondary Charts -->
                <div class="secondary-charts">
                    <div class="secondary-chart">
                        <div class="chart-header">
                            <h3>Statut de livraison</h3>
                        </div>
                        <div class="chart-canvas">
                            <canvas id="shippingChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="secondary-chart">
                        <div class="chart-header">
                            <h3>Top produits</h3>
                        </div>
                        <div class="chart-canvas">
                            <canvas id="productsChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Client Chart Section -->
                <div class="dashboard-grid" style="display: flex;">
                    <div class="card" style="width: 100%;">
                        <div class="card-header">
                            <h3>Top Clients</h3>
                            <a href="./customers.php" class="view-all">
                                Voir tout
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M4 8a.5.5 0 0 1 .5-.5h5.793L8.146 5.354a.5.5 0 1 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708-.708L10.293 8.5H4.5A.5.5 0 0 1 4 8z"/>
                                </svg>
                            </a>
                        </div>
                        
                        <div class="clients-table-container">
                            
                            <table class="clients-table" id="clients-table">
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
                                                    <div class="client-name"><?php echo htmlspecialchars($client['name'] ?: 'Client sans nom'); ?></div>
                                                    <div class="client-email"><?php echo htmlspecialchars($client['email'] ?: 'Aucun email'); ?></div>
                                                </td>
                                                <td>
                                                    <div class="client-contact">
                                                        <span class="client-phone">
                                                            <?php 
                                                            // Get phone number from orders for this client
                                                            $phoneQuery = "SELECT customer_phone FROM orders 
                                                                        WHERE customer_email = ? AND user_id = ? 
                                                                        AND customer_phone IS NOT NULL 
                                                                        LIMIT 1";
                                                            $phoneResult = $db->getThisQuery($phoneQuery, [$client['email'], $currentUserId]);
                                                            echo $phoneResult ? htmlspecialchars($phoneResult[0]['customer_phone']) : 'N/A';
                                                            ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="client-city">
                                                    <?php 
                                                    // Get city from orders for this client
                                                    $cityQuery = "SELECT customer_ville FROM orders 
                                                                WHERE customer_email = ? AND user_id = ? 
                                                                AND customer_ville IS NOT NULL 
                                                                LIMIT 1";
                                                    $cityResult = $db->getThisQuery($cityQuery, [$client['email'], $currentUserId]);
                                                    echo $cityResult ? htmlspecialchars($cityResult[0]['customer_ville']) : 'N/A';
                                                    ?>
                                                </td>
                                                <td class="order-count"><?php echo $client['order_count']; ?></td>
                                                <td class="revenue-amount"><?php echo formatCurrency($client['total_spent']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="no-data">
                                                <div class="empty-state">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" viewBox="0 0 16 16">
                                                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zM7 6.5C7 7.328 6.552 8 6 8s-1-.672-1-1.5S5.448 5 6 5s1 .672 1 1.5zm-2.715 5.933a.5.5 0 0 1-.183-.683A4.498 4.498 0 0 1 8 9.5a4.5 4.5 0 0 1 3.898 2.25.5.5 0 0 1-.866.5A3.498 3.498 0 0 0 8 10.5a3.498 3.498 0 0 0-3.032 1.75.5.5 0 0 1-.683.183zM10 8c-.552 0-1-.672-1-1.5S9.448 5 10 5s1 .672 1 1.5S10.552 8 10 8z"/>
                                                    </svg>
                                                    <p>Aucun client trouvé</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            
                            <div class="table-actions">
                                <div class="entries-info">
                                    Affichage de <?php echo min(1, count($chartData['top_clients'])); ?> à <?php echo count($chartData['top_clients']); ?> sur <?php echo count($chartData['top_clients']); ?> clients
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                    .clients-table-container {
                        overflow-x: auto;
                        padding: 0 20px 20px;
                    }

                    .clients-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }

                    .clients-table th {
                        background-color: #f8f9fa;
                        text-align: left;
                        padding: 12px 15px;
                        font-weight: 600;
                        color: #495057;
                        border-bottom: 2px solid #e9ecef;
                    }

                    .clients-table td {
                        padding: 12px 15px;
                        border-bottom: 1px solid #e9ecef;
                    }

                    .clients-table tbody tr:hover {
                        background-color: #f8f9ff;
                    }

                    .client-name {
                        font-weight: 600;
                        color: #2c3e50;
                    }

                    .client-email {
                        color: #6c757d;
                        font-size: 13px;
                    }

                    .client-phone {
                        color: #667eea;
                        font-size: 14px;
                    }

                    .client-city {
                        color: #495057;
                    }

                    .order-count {
                        text-align: center;
                        font-weight: 600;
                        color: #2c3e50;
                    }

                    .revenue-amount {
                        font-weight: 700;
                        color: #28a745;
                        text-align: center;
                    }

                    .action-btn {
                        background: none;
                        border: none;
                        color: #667eea;
                        cursor: pointer;
                        font-size: 14px;
                        padding: 5px;
                    }

                    .action-btn:hover {
                        color: #5a67d8;
                    }

                    .table-actions {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 15px 0;
                        background-color: #f8f9fa;
                        border-top: 1px solid #e9ecef;
                    }

                    .entries-info {
                        font-size: 14px;
                        color: #6c757d;
                    }

                    .search-box {
                        display: flex;
                        align-items: center;
                        background: white;
                        border: 1px solid #ced4da;
                        border-radius: 4px;
                        padding: 6px 12px;
                        margin-bottom: 15px;
                        width: 300px;
                    }

                    .search-box input {
                        border: none;
                        outline: none;
                        flex: 1;
                        padding: 5px;
                    }

                    .sortable {
                        cursor: pointer;
                        user-select: none;
                    }

                    .sortable:hover {
                        background-color: #e9ecef;
                    }

                    .sortable::after {
                        content: '↕';
                        margin-left: 5px;
                        font-size: 12px;
                        opacity: 0.5;
                    }

                    .sortable.asc::after {
                        content: '↑';
                        opacity: 1;
                    }

                    .sortable.desc::after {
                        content: '↓';
                        opacity: 1;
                    }

                    .no-data {
                        text-align: center;
                        padding: 40px;
                    }

                    .empty-state {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                        color: #6c757d;
                    }

                    .empty-state svg {
                        margin-bottom: 10px;
                        opacity: 0.5;
                    }

                    @media (max-width: 768px) {
                        .clients-table {
                            font-size: 14px;
                        }
                        
                        .clients-table th,
                        .clients-table td {
                            padding: 8px 10px;
                        }
                        
                        .search-box {
                            width: 100%;
                        }
                    }
                </style>

                <script>
                // Client search functionality
                document.getElementById('client-search').addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('#clients-tbody tr');
                    
                    rows.forEach(row => {
                        const clientName = row.querySelector('.client-name').textContent.toLowerCase();
                        const clientEmail = row.querySelector('.client-email').textContent.toLowerCase();
                        const clientPhone = row.querySelector('.client-phone').textContent.toLowerCase();
                        const clientCity = row.querySelector('.client-city').textContent.toLowerCase();
                        
                        if (clientName.includes(searchTerm) || clientEmail.includes(searchTerm) || 
                            clientPhone.includes(searchTerm) || clientCity.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });

                // Sorting functionality
                document.querySelectorAll('.sortable').forEach(header => {
                    header.addEventListener('click', function() {
                        const column = this.dataset.sort;
                        const isAsc = this.classList.contains('asc');
                        const tbody = document.getElementById('clients-tbody');
                        const rows = Array.from(tbody.querySelectorAll('tr'));
                        
                        // Remove sort classes from all headers
                        document.querySelectorAll('.sortable').forEach(h => {
                            h.classList.remove('asc', 'desc');
                        });
                        
                        // Sort rows
                        rows.sort((a, b) => {
                            let aValue, bValue;
                            
                            switch(column) {
                                case 'name':
                                    aValue = a.querySelector('.client-name').textContent;
                                    bValue = b.querySelector('.client-name').textContent;
                                    break;
                                case 'phone':
                                    aValue = a.querySelector('.client-phone').textContent;
                                    bValue = b.querySelector('.client-phone').textContent;
                                    break;
                                case 'city':
                                    aValue = a.querySelector('.client-city').textContent;
                                    bValue = b.querySelector('.client-city').textContent;
                                    break;
                                case 'orders':
                                    aValue = parseInt(a.querySelector('.order-count').textContent);
                                    bValue = parseInt(b.querySelector('.order-count').textContent);
                                    break;
                                case 'revenue':
                                    aValue = parseFloat(a.querySelector('.revenue-amount').textContent.replace(/[^\d,]/g, '').replace(',', '.'));
                                    bValue = parseFloat(b.querySelector('.revenue-amount').textContent.replace(/[^\d,]/g, '').replace(',', '.'));
                                    break;
                                default:
                                    return 0;
                            }
                            
                            if (typeof aValue === 'string') {
                                return isAsc ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
                            } else {
                                return isAsc ? aValue - bValue : bValue - aValue;
                            }
                        });
                        
                        // Remove existing rows
                        while (tbody.firstChild) {
                            tbody.removeChild(tbody.firstChild);
                        }
                        
                        // Add sorted rows
                        rows.forEach(row => tbody.appendChild(row));
                        
                        // Update sort indicator
                        this.classList.toggle('asc', !isAsc);
                        this.classList.toggle('desc', isAsc);
                    });
                });

                function viewClientDetails(email) {
                    // Implement view client details functionality
                    console.log('View client details:', email);
                    // This would typically open a modal or navigate to a client details page
                    alert('Détails du client: ' + email);
                }

                function contactClient(email) {
                    // Implement contact client functionality
                    console.log('Contact client:', email);
                    // This would typically open a contact form or initiate a call
                    alert('Contacter le client: ' + email);
                }
                </script>

            <?php endif; ?>
        </div>
    </main>
    
    <!-- Flatpickr for date range -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/fr.min.js"></script>
    
    <script>
        // Chart data from PHP
        const chartData = <?php echo json_encode($chartData); ?>;
        
        // Initialize charts
        let salesChart, statusChart, shippingChart, productsChart;
        let currentPeriod = 'daily';
        
        // Chart.js configuration
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.color = '#64748b';
        Chart.defaults.borderColor = '#e2e8f0';
        
        // Initialize date range picker
        document.addEventListener('DOMContentLoaded', function() {
            // Show/hide custom date range based on selection
            const dateRangeSelect = document.getElementById('date-range');
            const customDateRange = document.getElementById('custom-date-range');
            
            dateRangeSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customDateRange.style.display = 'flex';
                } else {
                    customDateRange.style.display = 'none';
                }
            });
            
            // Initialize flatpickr for date inputs
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
            
            // Update end date min date when start date changes
            document.getElementById('start-date').addEventListener('change', function() {
                document.getElementById('end-date')._flatpickr.set('minDate', this.value);
            });
            
            initializeCharts();
            
            // Sidebar toggle
            const sidebarToggle = document.getElementById('open-sidebar');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
        });

        const statusMap = {
            'not_submitted': 'Non soumis',
            'pending': 'Nouveau colis',
            'processing': 'En traitement',
            'pickup_pending': 'Attente de ramassage',
            'collected': 'Ramassé',
            'in_transit': 'En transit',
            'out_for_delivery': 'En cours de livraison',
            'delivered': 'Livré',
            'failed_delivery': 'Échec de livraison',
            'returned': 'Retourné',
            'cancelled': 'Annulé'
        };

        // Function to get French status label
        function getFrenchStatusLabel(status) {
            return statusMap[status] || status;
        }

        function showShippingDetails(details) {
            // Implement this to show a modal or update a details panel
            console.log('Shipping details:', details);
            // Example: Update a details panel in your HTML
            const detailsPanel = document.getElementById('shippingDetailsPanel');
            if (detailsPanel) {
                detailsPanel.innerHTML = `
                    <h3>${details.status}</h3>
                    <p><strong>Nombre:</strong> ${details.count}</p>
                    <p><strong>Pourcentage:</strong> ${details.percentage}%</p>
                    <p>${details.description}</p>
                `;
                detailsPanel.style.display = 'block';
            }
        }

        function getStatusDescription(status) {
            // Return descriptive text for each status
            const descriptions = {
                'pending': 'Commandes en attente de traitement',
                'shipped': 'Commandes expédiées et en transit',
                'delivered': 'Commandes livrées avec succès',
                'cancelled': 'Commandes annulées',
                'returned': 'Commandes retournées'
            };
            return descriptions[status] || '';
        }

        // Function to get color based on status
        function getStatusColor(status) {
            const colorMap = {
                'not_submitted': '#95a5a6',    // Gray
                'pending': '#3498db',  
                'processing': '#6d3b00',        // Blue
                'pickup_pending': '#f39c12',   // Orange
                'collected': '#9b59b6',        // Purple
                'in_transit': '#e67e22',       // Dark Orange
                'out_for_delivery': '#f1c40f', // Yellow
                'delivered': '#2ecc71',        // Green
                'failed_delivery': '#e74c3c',  // Red
                'returned': '#34495e',         // Dark Gray
                'cancelled': '#c0392b'         // Dark Red
            };
            return colorMap[status] || '#95a5a6';
        }
        
        function initializeCharts() {
            // Helper function to show "No data" message
            function showNoDataMessage(canvasId, message = 'Aucune donnée disponible pour cette période') {
                const canvas = document.getElementById(canvasId);
                if (canvas) {
                    const ctx = canvas.getContext('2d');
                    const rect = canvas.getBoundingClientRect();
                    
                    // Clear canvas
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    // Set canvas size if not set
                    canvas.width = rect.width || 400;
                    canvas.height = rect.height || 300;
                    
                    // Draw "No data" message
                    ctx.fillStyle = '#999';
                    ctx.font = '16px Arial, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(message, canvas.width / 2, canvas.height / 2);
                    
                    // Draw icon (optional)
                    ctx.fillStyle = '#ccc';
                    ctx.font = '24px Arial, sans-serif';
                    ctx.fillText('📊', canvas.width / 2, canvas.height / 2 - 30);
                }
            }

            // Sales Chart
            const salesCtx = document.getElementById('salesChart');
            if (salesCtx) {
                const salesData = getSalesChartData('daily');
                
                // Check if sales data is empty or has no meaningful data
                const hasData = salesData.datasets && 
                            salesData.datasets.length > 0 && 
                            salesData.datasets.some(dataset => 
                                dataset.data && dataset.data.length > 0 && 
                                dataset.data.some(value => value > 0)
                            );
                
                if (!hasData) {
                    showNoDataMessage('salesChart', 'Aucune donnée de ventes pour cette période');
                } else {
                    salesChart = new Chart(salesCtx, {
                        type: 'line',
                        data: salesData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20,
                                    }
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            if (context.datasetIndex === 1) {
                                                label += formatCurrency(context.raw);
                                            } else {
                                                label += context.raw;
                                            }
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        drawBorder: false
                                    },
                                    title: {
                                        display: true,
                                        text: 'Commandes'
                                    }
                                },
                                y1: {
                                    beginAtZero: true,
                                    grid: {
                                        drawOnChartArea: false,
                                        drawBorder: false
                                    },
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Revenus (DH)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return formatCurrency(value, true);
                                        }
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false,
                                        drawBorder: false
                                    }
                                }
                            },
                            elements: {
                                line: {
                                    tension: 0.4,
                                    borderWidth: 2
                                },
                                point: {
                                    radius: 4,
                                    hoverRadius: 6,
                                    borderWidth: 2
                                }
                            },
                            interaction: {
                                mode: 'index',
                                intersect: false
                            }
                        }
                    });
                }
            }
            
            // Status Chart
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx) {
                if (!chartData.order_status || chartData.order_status.length === 0) {
                    showNoDataMessage('statusChart', 'Aucune donnée de statut de commande');
                } else {
                    statusChart = new Chart(statusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: chartData.order_status.map(item => getStatusBadge(item.status)),
                            datasets: [{
                                data: chartData.order_status.map(item => item.count),
                                backgroundColor: [
                                    '#2ecc71',
                                    '#f39c12',
                                    '#3498db',
                                    '#e74c3c',
                                    '#9b59b6'
                                ],
                                borderWidth: 0,
                                spacing: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20,
                                        boxWidth: 10
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const percentage = chartData.order_status[context.dataIndex].percentage.toFixed(2);
                                            return `${label}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }
            
            // Shipping Status Chart
            const shippingCtx = document.getElementById('shippingChart');
            if (shippingCtx) {
                if (!chartData.shipping_status || chartData.shipping_status.length === 0) {
                    showNoDataMessage('shippingChart', 'Aucune donnée de statut de livraison');
                } else {
                    // First calculate percentages if they don't exist
                    const total = chartData.shipping_status.reduce((sum, item) => sum + item.count, 0);
                    chartData.shipping_status.forEach(item => {
                        item.percentage = total > 0 ? (item.count / total) * 100 : 0;
                    });

                    shippingChart = new Chart(shippingCtx, {
                        type: 'pie',
                        data: {
                            labels: chartData.shipping_status.map(item => getFrenchStatusLabel(item.shipping_status)),
                            datasets: [{
                                data: chartData.shipping_status.map(item => item.count),
                                backgroundColor: chartData.shipping_status.map(item => getStatusColor(item.shipping_status)),
                                borderWidth: 2,
                                borderColor: '#ffffff',
                                spacing: 2,
                                hoverBorderWidth: 3,
                                hoverOffset: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: '',
                                    font: {
                                        size: 18,
                                        weight: 'bold'
                                    },
                                    padding: {
                                        top: 10,
                                        bottom: 20
                                    }
                                },
                                legend: {
                                    position: 'right',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 15,
                                        boxWidth: 12,
                                        boxHeight: 12,
                                        font: {
                                            size: 13,
                                            family: 'Arial, sans-serif'
                                        },
                                        color: '#333',
                                        generateLabels: function(chart) {
                                            const data = chart.data;
                                            return data.labels.map((label, i) => {
                                                const value = data.datasets[0].data[i];
                                                const percentage = chartData.shipping_status[i].percentage.toFixed(1);
                                                return {
                                                    text: `${label}: ${value} (${percentage}%)`,
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    hidden: false,
                                                    index: i
                                                };
                                            });
                                        }
                                    },
                                    onClick: function(e, legendItem, legend) {
                                        const index = legendItem.index;
                                        const chart = legend.chart;
                                        const meta = chart.getDatasetMeta(0);

                                        // Toggle visibility
                                        meta.data[index].hidden = !meta.data[index].hidden;

                                        // Update chart
                                        chart.update();
                                    }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: '#fff',
                                    bodyColor: '#fff',
                                    borderColor: '#4361ee',
                                    borderWidth: 1,
                                    cornerRadius: 6,
                                    displayColors: true,
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.label || '';
                                            const value = context.raw || 0;
                                            const percentage = context.dataset.data.length > 0 ? 
                                                (value / context.dataset.data.reduce((a, b) => a + b, 0) * 100).toFixed(1) : 0;
                                            return `${label}: ${value} commandes (${percentage}%)`;
                                        },
                                        afterLabel: function(context) {
                                            const status = chartData.shipping_status[context.dataIndex].shipping_status;
                                            return getStatusDescription(status);
                                        }
                                    }
                                }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'nearest'
                            },
                            onHover: function(event, elements) {
                                event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                            },
                            onClick: function(event, elements) {
                                if (elements.length > 0) {
                                    const index = elements[0].index;
                                    const status = chartData.shipping_status[index].shipping_status;
                                    const count = chartData.shipping_status[index].count;
                                    const total = chartData.shipping_status.reduce((sum, item) => sum + item.count, 0);
                                    const percentage = total > 0 ? (count / total * 100).toFixed(1) : 0;
                                    
                                    showShippingDetails({
                                        status: getFrenchStatusLabel(status),
                                        count: count,
                                        percentage: percentage,
                                        description: getStatusDescription(status)
                                    });
                                }
                            }
                        },
                        plugins: [{
                            id: 'customCenterText',
                            afterDraw: function(chart) {
                                const ctx = chart.ctx;
                                const width = chart.width;
                                const height = chart.height;
                                
                                ctx.restore();
                                const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const fontSize = (height / 8).toFixed(2);
                                ctx.font = `bold ${fontSize}px Arial, sans-serif`;
                                ctx.textBaseline = 'middle';
                                ctx.fillStyle = '#333';
                                
                                const text = ``;
                                const textX = Math.round((width - ctx.measureText(text).width) / 2);
                                const textY = height / 2;
                                
                                ctx.fillText(text, textX, textY);
                                ctx.save();
                            }
                        },
                        {
                            id: 'percentageLabels',
                            afterDatasetsDraw(chart, args, options) {
                                const {ctx, data, chartArea: {top, bottom, left, right, width, height}} = chart;
                                const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                
                                ctx.font = 'bold 12px Arial, sans-serif';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'middle';
                                
                                chart.getDatasetMeta(0).data.forEach((datapoint, index) => {
                                    if (!datapoint.hidden) {
                                        const x = datapoint.x;
                                        const y = datapoint.y;
                                        const value = data.datasets[0].data[index];
                                        const percentage = total > 0 ? (value / total * 100).toFixed(1) + '%' : '0%';
                                        
                                        ctx.fillStyle = data.datasets[0].backgroundColor[index] === '#ffffff' ? 
                                                    '#333' : '#ffffff';
                                        ctx.fillText(percentage, x, y);
                                    }
                                });
                            }
                        }]
                    });
                }
            }
            
            // Products Chart
            const productsCtx = document.getElementById('productsChart');
            if (productsCtx) {
                if (!chartData.top_products || chartData.top_products.length === 0) {
                    showNoDataMessage('productsChart', 'Aucun produit vendu pour cette période');
                } else {
                    productsChart = new Chart(productsCtx, {
                        type: 'bar',
                        data: {
                            labels: chartData.top_products.slice(0, 10).map(item => item.product_name),
                            datasets: [{
                                label: 'Quantité vendue',
                                data: chartData.top_products.slice(0, 10).map(item => item.total_quantity),
                                backgroundColor: '#9c80fd',
                                hoverBackgroundColor: '#5f34d9',
                                borderRadius: 4,
                                borderWidth: 0,
                                borderSkipped: false,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    enabled: true,
                                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                    titleColor: '#fff',
                                    bodyColor: '#fff',
                                    borderColor: '#4361ee',
                                    borderWidth: 1,
                                    cornerRadius: 6,
                                    displayColors: false,
                                    callbacks: {
                                        title: function(context) {
                                            const data = chartData.top_products[context[0].dataIndex];
                                            return data.product_name;
                                        },
                                        label: function(context) {
                                            return `Quantité: ${context.parsed.y}`;
                                        },
                                        afterLabel: function(context) {
                                            const data = chartData.top_products[context.dataIndex];
                                            return `Revenu: ${formatCurrency(data.total_revenue)}\nCommandes: ${data.order_count}`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        drawBorder: false,
                                        color: '#f0f0f0'
                                    },
                                    ticks: {
                                        color: '#666',
                                        font: {
                                            size: 12
                                        }
                                    }
                                },
                                x: {
                                    display: false, // This hides the x-axis labels completely
                                    grid: {
                                        display: false,
                                        drawBorder: false
                                    }
                                }
                            },
                            onHover: function(event, elements) {
                                event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                            }
                        }
                    });
                }
            }

            // Optional: Add a function to destroy existing charts before reinitializing
            function destroyExistingCharts() {
                if (window.salesChart) {
                    window.salesChart.destroy();
                    window.salesChart = null;
                }
                if (window.statusChart) {
                    window.statusChart.destroy();
                    window.statusChart = null;
                }
                if (window.shippingChart) {
                    window.shippingChart.destroy();
                    window.shippingChart = null;
                }
                if (window.productsChart) {
                    window.productsChart.destroy();
                    window.productsChart = null;
                }
            }

            // Call this before initializing new charts
            destroyExistingCharts();
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
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        yAxisID: 'y',
                        pointBackgroundColor: '#4361ee'
                    },
                    {
                        label: 'Revenus (DH)',
                        data: data.map(item => item.revenue),
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        yAxisID: 'y1',
                        pointBackgroundColor: '#2ecc71'
                    }
                ]
            };
        }
        
        function switchChart(period) {
            currentPeriod = period;
            
            // Update active button
            document.querySelectorAll('.chart-control').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update chart data
            if (salesChart) {
                salesChart.data = getSalesChartData(period);
                salesChart.update();
            }
        }
        
        function formatCurrency(amount, short = false) {
            if (short) {
                if (amount >= 1000000) {
                    return (amount / 1000000).toFixed(1) + 'M';
                }
                if (amount >= 1000) {
                    return (amount / 1000).toFixed(1) + 'K';
                }
            }
            return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'MAD' }).format(amount);
        }
        
        function getStatusBadge(status) {
            const badges = {
                'complete': 'Livrée',
                'pending': 'En cours',
                'processing': 'Préparation',
                'cancelled': 'Annulée',
                'shipped': 'Expédiée',
                'delivered': 'Livrée',
                'returned': 'Retournée'
            };
            return badges[status] || status.charAt(0).toUpperCase() + status.slice(1);
        }
        
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
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