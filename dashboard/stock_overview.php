<?php
// stock_overview.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("./config/init.php");
}

$db = DB::getInstance();
// Get user from session
$user = $db->getThisQuery(
    "SELECT id, `name`, `role` 
     FROM users 
     WHERE username = ? OR email = ? OR phone = ? 
     LIMIT 1", 
    [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]
);

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

if (isset($_GET['logout'])) {
    logout();
}

$user_id = (int)$user[0]['id'];
$userName = $user[0]['name'];
$userRole = $user[0]['role'];

// Vérification de l'abonnement
$subscription = new Subscription($user_id);
$subscriptionStatus = $subscription->getSubscriptionStatus();
$subscriptionAlerts = $subscription->getSubscriptionAlerts();

// Initialize onboarding
$onboarding = new Onboarding($user_id);
$currentPage = basename($_SERVER['PHP_SELF']);
if (!$onboarding->isOnboardingCompleted() && $currentPage !== 'onboarding') {
    Redirect::to('./onboarding');
}

// Vérifier l'accès aux fonctionnalités
$canAccessFeatures = $subscription->canAccessFeatures();

// Si l'abonnement est expiré ou inexistant, limiter l'accès
$limitedAccess = !$canAccessFeatures; 

// Get stock statistics
$stock_stats = [
    'total_products' => 0,
    'total_skus' => 0,
    'in_stock' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'total_value' => 0,
    'monthly_movements' => 0
];

// Get recent stock alerts
$recent_alerts = [];
// Get low stock items
$low_stock_items = [];
// Get top selling products
$top_selling = [];
// Get stock movements
$recent_movements = [];

// Query for stock statistics
try {
    // Total products
    $total_products = $db->getThisQuery("SELECT COUNT(*) as total FROM products WHERE user_id = ?", [$user_id]);
    $stock_stats['total_products'] = $total_products[0]['total'] ?? 0;
    
    // Total SKUs
    $total_skus = $db->getThisQuery("
        SELECT COUNT(DISTINCT sku) as total 
        FROM product_variants 
        WHERE product_id IN (SELECT id FROM products WHERE user_id = ?)
    ", [$user_id]);
    $stock_stats['total_skus'] = $total_skus[0]['total'] ?? 0;
    
    // Stock status counts
    $stock_status = $db->getThisQuery("
        SELECT 
            SUM(CASE WHEN stock_quantity > 10 THEN 1 ELSE 0 END) as in_stock,
            SUM(CASE WHEN stock_quantity BETWEEN 1 AND 10 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
        FROM products 
        WHERE user_id = ?
    ", [$user_id]);
    
    if ($stock_status && isset($stock_status[0])) {
        $stock_stats['in_stock'] = $stock_status[0]['in_stock'] ?? 0;
        $stock_stats['low_stock'] = $stock_status[0]['low_stock'] ?? 0;
        $stock_stats['out_of_stock'] = $stock_status[0]['out_of_stock'] ?? 0;
    }
    
    // Total inventory value
    $total_value = $db->getThisQuery("
        SELECT SUM(p.cost_price * p.stock_quantity) as total_value
        FROM products p
        WHERE p.user_id = ? AND p.cost_price > 0 AND p.stock_quantity > 0
    ", [$user_id]);
    $stock_stats['total_value'] = $total_value[0]['total_value'] ?? 0;
    
    // Monthly movements
    $monthly_movements = $db->getThisQuery("
        SELECT COUNT(*) as total
        FROM stock_movements 
        WHERE user_id = ? 
        AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ", [$user_id]);
    $stock_stats['monthly_movements'] = $monthly_movements[0]['total'] ?? 0;
    
    // Get low stock items
    $low_stock_items = $db->getThisQuery("
        SELECT 
            p.id,
            p.name,
            p.sku,
            p.stock_quantity,
            p.sale_price,
            p.regular_price,
            p.cost_price,
            p.images,
            p.stock_status,
            10 as threshold -- Default threshold of 10
        FROM products p
        WHERE p.user_id = ? 
        AND p.stock_status = 'low_stock'
        ORDER BY p.stock_quantity ASC
        LIMIT 10
    ", [$user_id]);
    
    // Get recent stock movements
    $recent_movements = $db->getThisQuery("
        SELECT 
            sm.id,
            sm.product_id,
            sm.variation_id,
            sm.movement_type,
            sm.quantity,
            sm.reason,
            sm.created_at,
            sm.user_id,
            p.name as product_name,
            p.sku as sku,
            COALESCE(pv.sku, p.sku) as display_sku,
            COALESCE(pv.variant_name, '') as variant_name,
            u.username as user_name
        FROM stock_movements sm
        INNER JOIN products p ON sm.product_id = p.id AND p.user_id = ?
        LEFT JOIN product_variants pv ON sm.variation_id = pv.id
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE sm.user_id = ?
        ORDER BY sm.created_at DESC
        LIMIT 10
    ", [$user_id, $user_id]);
    
} catch (Exception $e) {
    error_log("Error fetching stock stats: " . $e->getMessage());
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
    <meta name="description" content="Vue d'ensemble du stock - Plateforme">
    <title>Vue Stock | OBG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #0ea5e9;
            --background-color: #f8fafc;
            --surface-color: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --border-radius: 12px;
            --border-radius-sm: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background: var(--background-color);
        }

        /* Top Navigation */
        .top-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .search-bar {
            position: relative;
            max-width: 400px;
            flex: 1;
            margin: 0 2rem;
        }

        .search-bar input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid var(--border-color);
            border-radius: 50px;
            background: var(--surface-color);
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .search-bar .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 2rem;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .dashboard-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .action-card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--border-radius-sm);
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .action-content {
            flex: 1;
        }

        .action-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }

        .action-description {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.in-stock::before {
            background: var(--success-color);
        }

        .stat-card.low-stock::before {
            background: var(--warning-color);
        }

        .stat-card.out-of-stock::before {
            background: var(--danger-color);
        }

        .stat-card.info::before {
            background: var(--info-color);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.in-stock {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stat-icon.low-stock {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stat-icon.out-of-stock {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .stat-icon.info {
            background: rgba(14, 165, 233, 0.1);
            color: var(--info-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .stat-trend.positive {
            color: var(--success-color);
        }

        .stat-trend.negative {
            color: var(--danger-color);
        }

        /* Stock Overview Grid */
        .overview-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1024px) {
            .overview-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            height: 100%;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title i {
            color: var(--primary-color);
        }

        .view-all {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: var(--transition);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .view-all:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-dark);
        }

        /* Low Stock Items */
        .stock-items {
            max-height: 400px;
            overflow-y: auto;
        }

        .stock-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .stock-item:hover {
            background: var(--background-color);
            transform: translateX(4px);
        }

        .stock-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 60px;
            height: 60px;
            border-radius: var(--border-radius-sm);
            background: var(--background-color);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
            overflow: hidden;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-image i {
            color: var(--text-secondary);
            font-size: 1.5rem;
        }

        .item-details {
            flex: 1;
            min-width: 0;
        }

        .item-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-sku {
            font-size: 0.8rem;
            color: var(--text-secondary);
            background: var(--background-color);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .item-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .stock-level {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .stock-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .stock-badge.in-stock {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .stock-badge.low-stock {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
            animation: pulse 2s infinite;
        }

        .stock-badge.out-of-stock {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .reorder-btn {
            background: var(--warning-color);
            color: white;
            border: none;
            padding: 0.375rem 0.75rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .reorder-btn:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        /* Stock Chart */
        .chart-container {
            height: 300px;
            margin-top: 1rem;
            position: relative;
        }

        /* Recent Movements */
        .movements-table {
            width: 100%;
            border-collapse: collapse;
        }

        .movements-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .movements-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .movements-table tr:hover {
            background: var(--background-color);
        }

        .movement-type {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .movement-type.incoming {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .movement-type.outgoing {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .movement-type.adjustment {
            background: rgba(14, 165, 233, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(14, 165, 233, 0.2);
        }

        .quantity-change {
            font-weight: 600;
            font-size: 1rem;
        }

        .quantity-change.positive {
            color: var(--success-color);
        }

        .quantity-change.negative {
            color: var(--danger-color);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-color);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--surface-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--background-color);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        /* Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            background: var(--surface-color);
            padding: 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow-lg);
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Refresh Button */
        .refresh-btn {
            background: var(--surface-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .refresh-btn:hover {
            background: var(--background-color);
            transform: rotate(180deg);
        }

        .refresh-btn.refreshing {
            animation: rotate 1s linear infinite;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1rem;
            }

            .overview-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .stock-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .item-image {
                align-self: flex-start;
            }

            .stock-level {
                width: 100%;
                justify-content: space-between;
            }

            .movements-table {
                display: block;
                overflow-x: auto;
            }

            .top-navbar {
                padding: 1rem;
            }

            .search-bar {
                margin: 0;
                max-width: none;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            transform: translateX(400px);
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
            min-width: 300px;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            border-left-color: var(--success-color);
        }

        .notification.error {
            border-left-color: var(--danger-color);
        }

        .notification.warning {
            border-left-color: var(--warning-color);
        }

        /* Subscription Alerts */
        .subscription-alerts-container {
            margin-bottom: 1.5rem;
        }
        
        .subscription-alert {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 0.75rem;
            position: relative;
            gap: 1rem;
            box-shadow: var(--shadow-sm);
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'stock_overview';
    require_once('../assets/sidebar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <?php if (!empty($subscriptionAlerts)): ?>
            <div class="subscription-alerts-container">
                <?php foreach ($subscriptionAlerts as $alert): ?>
                    <div class="subscription-alert alert-<?php echo $alert['type']; ?>" data-priority="<?php echo $alert['priority']; ?>">
                        <div class="alert-icon">
                            <?php if ($alert['type'] === 'danger'): ?>
                                <i class="fas fa-exclamation-triangle"></i>
                            <?php elseif ($alert['type'] === 'warning'): ?>
                                <i class="fas fa-exclamation-circle"></i>
                            <?php else: ?>
                                <i class="fas fa-info-circle"></i>
                            <?php endif; ?>
                        </div>
                        <div class="alert-content">
                            <h4><?php echo htmlspecialchars($alert['title']); ?></h4>
                            <p><?php echo htmlspecialchars($alert['message']); ?></p>
                        </div>
                        <div class="alert-actions">
                            <a href="<?php echo htmlspecialchars($alert['action_url']); ?>" class="btn btn-sm btn-primary">
                                <?php echo htmlspecialchars($alert['action_text']); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Top Navigation -->
        <nav class="top-navbar">
            <button class="btn btn-link sidebar-toggle d-md-none">
                <i class="fas fa-bars" style="color: #5f34d9;"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Rechercher produits, SKUs..." id="global-search">
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="notifications">
                    <button class="notification-btn" data-tooltip="Notifications" style="background: none; border: none">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge hidden">0</span>
                    </button>
                </div>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="dashboard-header">
                <div>
                    <h2><i class="fas fa-chart-bar text-primary me-2"></i> Vue Stock - Tableau de Bord</h2>
                    <p class="text-muted mb-0">Vue d'ensemble de votre inventaire et gestion de stock</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" id="refresh-stock">
                        <i class="fas fa-sync-alt"></i>
                        Actualiser
                    </button>
                    <a href="stock_management.php" class="btn btn-success">
                        <i class="fas fa-tags"></i>
                        Gérer les SKUs
                    </a>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="stock_management.php?action=add_sku" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="action-content">
                        <div class="action-title">Nouvelle variante</div>
                        <div class="action-description">Créer un nouveau SKU avec variants</div>
                    </div>
                </a>

                <a href="stock_inventory.php?action=adjust" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-arrow-down-up"></i>
                    </div>
                    <div class="action-content">
                        <div class="action-title">Ajuster l'Inventaire</div>
                        <div class="action-description">Ajuster les niveaux de stock</div>
                    </div>
                </a>

                <a href="stock_alerts.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="action-content">
                        <div class="action-title">Alertes Stock</div>
                        <div class="action-description">Voir et gérer les alertes</div>
                    </div>
                </a>

                <a href="items.php?sync=true" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="action-content">
                        <div class="action-title">Synchroniser Produits</div>
                        <div class="action-description">Sync avec les stores connectés</div>
                    </div>
                </a>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card in-stock">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($stock_stats['in_stock']); ?></div>
                            <div class="stat-label">Produits en Stock</div>
                        </div>
                        <div class="stat-icon in-stock">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-trend positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>Bonne santé</span>
                    </div>
                </div>

                <div class="stat-card low-stock">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($stock_stats['low_stock']); ?></div>
                            <div class="stat-label">Stock Faible</div>
                        </div>
                        <div class="stat-icon low-stock">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-trend negative">
                        <i class="fas fa-arrow-up"></i>
                        <span>Attention requise</span>
                    </div>
                </div>

                <div class="stat-card out-of-stock">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($stock_stats['out_of_stock']); ?></div>
                            <div class="stat-label">En Rupture</div>
                        </div>
                        <div class="stat-icon out-of-stock">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-trend negative">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Réapprovisionnement urgent</span>
                    </div>
                </div>

                <div class="stat-card info">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo number_format($stock_stats['total_value'], 0, ',', ' '); ?> MAD</div>
                            <div class="stat-label">Valeur Totale</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="stat-trend positive">
                        <i class="fas fa-chart-line"></i>
                        <span>Inventaire valorisé</span>
                    </div>
                </div>
            </div>
            
            <!-- Overview Grid -->
            <div class="overview-grid">
                <!-- Low Stock Items -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            Stock Faible - À Réapprovisionner
                        </h3>
                        <a href="stock_alerts.php" class="view-all">
                            Voir tout <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="stock-items">
                        <?php if (!empty($low_stock_items)): ?>
                            <?php foreach ($low_stock_items as $item): ?>
                                <?php 
                                // Parse images
                                $images = [];
                                try {
                                    if ($item['images'] && is_string($item['images'])) {
                                        $images = json_decode($item['images'], true);
                                    } elseif (is_array($item['images'])) {
                                        $images = $item['images'];
                                    }
                                } catch (Exception $e) {
                                    $images = [];
                                }
                                $firstImage = !empty($images[0]['src']) ? $images[0]['src'] : '';
                                ?>
                                <div class="stock-item">
                                    <div class="item-image">
                                        <?php if ($firstImage): ?>
                                            <img src="<?php echo htmlspecialchars($firstImage); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-box"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="item-details">
                                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="item-sku">SKU: <?php echo htmlspecialchars($item['sku']); ?></div>
                                        <div class="item-meta">
                                            <span>Stock: <?php echo number_format($item['stock_quantity']); ?></span>
                                            <span>Seuil: <?php echo number_format($item['threshold']); ?></span>
                                        </div>
                                    </div>
                                    <div class="stock-level">
                                        <span class="stock-badge low-stock">FAIBLE</span>
                                        <button class="reorder-btn" onclick="reorderProduct(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['sku']); ?>')">
                                            Réappro.
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle text-success"></i>
                                <h3>Aucun produit en stock faible</h3>
                                <p>Tous vos produits ont des niveaux de stock suffisants.</p>
                                <a href="items.php" class="btn btn-primary">
                                    <i class="fas fa-eye"></i>
                                    Voir tous les produits
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Stock Status Chart -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-pie text-primary"></i>
                            Statut du Stock
                        </h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="stockChart"></canvas>
                    </div>
                    <div class="d-flex justify-content-around mt-3">
                        <div class="text-center">
                            <div class="text-success fw-bold fs-5"><?php echo $stock_stats['total_products'] > 0 ? round(($stock_stats['in_stock'] / $stock_stats['total_products']) * 100) : 0; ?>%</div>
                            <div class="text-muted small">En Stock</div>
                        </div>
                        <div class="text-center">
                            <div class="text-warning fw-bold fs-5"><?php echo $stock_stats['total_products'] > 0 ? round(($stock_stats['low_stock'] / $stock_stats['total_products']) * 100) : 0; ?>%</div>
                            <div class="text-muted small">Stock Faible</div>
                        </div>
                        <div class="text-center">
                            <div class="text-danger fw-bold fs-5"><?php echo $stock_stats['total_products'] > 0 ? round(($stock_stats['out_of_stock'] / $stock_stats['total_products']) * 100) : 0; ?>%</div>
                            <div class="text-muted small">Rupture</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Stock Movements -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history text-info"></i>
                        Mouvements Récents
                    </h3>
                    <a href="stock_inventory.php?view=movements" class="view-all">
                        Voir l'historique <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="stock-items">
                    <table class="movements-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>SKU</th>
                                <th>Produit</th>
                                <th>Type</th>
                                <th>Quantité</th>
                                <th>Utilisateur</th>
                                <th>Raison</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_movements)): ?>
                                <?php foreach ($recent_movements as $movement): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($movement['sku']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($movement['product_name']); ?></td>
                                        <td>
                                            <?php 
                                            $typeClass = '';
                                            $typeText = '';
                                            switch($movement['movement_type']) {
                                                case 'incoming':
                                                    $typeClass = 'incoming';
                                                    $typeText = 'ENTRÉE';
                                                    break;
                                                case 'outgoing':
                                                    $typeClass = 'outgoing';
                                                    $typeText = 'SORTIE';
                                                    break;
                                                case 'adjustment':
                                                    $typeClass = 'adjustment';
                                                    $typeText = 'AJUSTEMENT';
                                                    break;
                                                default:
                                                    $typeClass = 'adjustment';
                                                    $typeText = strtoupper($movement['movement_type']);
                                            }
                                            ?>
                                            <span class="movement-type <?php echo $typeClass; ?>"><?php echo $typeText; ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $quantityClass = $movement['movement_type'] === 'incoming' ? 'positive' : 'negative';
                                            $sign = $movement['movement_type'] === 'incoming' ? '+' : '-';
                                            ?>
                                            <span class="quantity-change <?php echo $quantityClass; ?>">
                                                <?php echo $sign . number_format($movement['quantity']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($movement['user_name'] ?: 'System'); ?></td>
                                        <td><?php echo htmlspecialchars($movement['reason'] ?: 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-history text-muted"></i>
                                            <h3>Aucun mouvement récent</h3>
                                            <p>Les mouvements de stock apparaîtront ici.</p>
                                            <a href="stock_inventory.php?action=adjust" class="btn btn-primary">
                                                <i class="fas fa-plus"></i>
                                                Créer un mouvement
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p class="mt-2">Chargement en cours...</p>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notification-container"></div>

    <script>
        $(document).ready(function() {
            // Initialize Chart.js
            initStockChart();
            
            // Initialize event listeners
            initEventListeners();
            
            // Load notifications
            loadNotifications();
            
            function initEventListeners() {
                // Refresh button
                $('#refresh-stock').click(refreshStockData);
                
                // Global search
                $('#global-search').on('input', debounce(handleGlobalSearch, 300));
                
                // Reorder buttons
                $(document).on('click', '.reorder-btn', function() {
                    const productId = $(this).data('product-id');
                    const sku = $(this).data('sku');
                    reorderProduct(productId, sku);
                });
            }
            
            function initStockChart() {
                const ctx = document.getElementById('stockChart').getContext('2d');
                
                const inStock = <?php echo $stock_stats['in_stock']; ?>;
                const lowStock = <?php echo $stock_stats['low_stock']; ?>;
                const outOfStock = <?php echo $stock_stats['out_of_stock']; ?>;
                
                const chart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['En Stock', 'Stock Faible', 'Rupture'],
                        datasets: [{
                            data: [inStock, lowStock, outOfStock],
                            backgroundColor: [
                                '#10b981', // Success green
                                '#f59e0b', // Warning orange
                                '#ef4444'  // Danger red
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverBorderWidth: 3,
                            hoverBorderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '70%',
                        animation: {
                            animateScale: true,
                            animateRotate: true
                        }
                    }
                });
                
                // Store chart reference for updates
                window.stockChart = chart;
            }
            
            function refreshStockData() {
                const $btn = $('#refresh-stock');
                const originalHtml = $btn.html();
                
                // Show loading state
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Actualisation...');
                showLoading();
                
                // Simulate API call
                setTimeout(() => {
                    // In real implementation, make AJAX call to refresh data
                    $.ajax({
                        url: './controllers/refresh_stock_data.php',
                        method: 'GET',
                        success: function(response) {
                            if (response.success) {
                                // Update chart data
                                if (window.stockChart) {
                                    window.stockChart.data.datasets[0].data = [
                                        response.data.in_stock || 0,
                                        response.data.low_stock || 0,
                                        response.data.out_of_stock || 0
                                    ];
                                    window.stockChart.update();
                                }
                                
                                // Update stats
                                if (response.data.in_stock !== undefined) {
                                    $('.stat-card.in-stock .stat-value').text(response.data.in_stock);
                                }
                                if (response.data.low_stock !== undefined) {
                                    $('.stat-card.low-stock .stat-value').text(response.data.low_stock);
                                }
                                if (response.data.out_of_stock !== undefined) {
                                    $('.stat-card.out-of-stock .stat-value').text(response.data.out_of_stock);
                                }
                                if (response.data.total_value !== undefined) {
                                    $('.stat-card.info .stat-value').text(
                                        new Intl.NumberFormat('fr-MA', {
                                            style: 'currency',
                                            currency: 'MAD',
                                            minimumFractionDigits: 0
                                        }).format(response.data.total_value)
                                    );
                                }
                                
                                showNotification('Données de stock actualisées avec succès!', 'success');
                            } else {
                                showNotification(response.message || 'Erreur lors de l\'actualisation', 'error');
                            }
                        },
                        error: function() {
                            showNotification('Erreur de connexion', 'error');
                        },
                        complete: function() {
                            hideLoading();
                            $btn.prop('disabled', false);
                            $btn.html(originalHtml);
                        }
                    });
                }, 500);
            }
            
            function handleGlobalSearch() {
                const searchTerm = $('#global-search').val().toLowerCase();
                
                if (searchTerm) {
                    // Filter low stock items
                    $('.stock-item').each(function() {
                        const $item = $(this);
                        const itemName = $item.find('.item-name').text().toLowerCase();
                        const itemSku = $item.find('.item-sku').text().toLowerCase();
                        
                        if (itemName.includes(searchTerm) || itemSku.includes(searchTerm)) {
                            $item.show();
                            $item.addClass('highlight');
                        } else {
                            $item.hide();
                            $item.removeClass('highlight');
                        }
                    });
                    
                    // Filter movements
                    $('.movements-table tbody tr').each(function() {
                        const $row = $(this);
                        const rowText = $row.text().toLowerCase();
                        
                        if (rowText.includes(searchTerm)) {
                            $row.show();
                            $row.addClass('highlight');
                        } else {
                            $row.hide();
                            $row.removeClass('highlight');
                        }
                    });
                } else {
                    // Reset filters
                    $('.stock-item').show().removeClass('highlight');
                    $('.movements-table tbody tr').show().removeClass('highlight');
                }
            }
            
            function reorderProduct(productId, sku) {
                if (confirm(`Voulez-vous réapprovisionner le produit SKU: ${sku} ?`)) {
                    showLoading();
                    
                    $.ajax({
                        url: './controllers/reorder_product.php',
                        method: 'POST',
                        data: {
                            product_id: productId,
                            sku: sku,
                            quantity: 50 // Default reorder quantity
                        },
                        success: function(response) {
                            if (response.success) {
                                showNotification(`Commande de réapprovisionnement créée pour ${sku}`, 'success');
                                refreshStockData(); // Refresh the data
                            } else {
                                showNotification(response.message || 'Erreur lors de la création de la commande', 'error');
                            }
                        },
                        error: function() {
                            showNotification('Erreur de connexion', 'error');
                        },
                        complete: function() {
                            hideLoading();
                        }
                    });
                }
            }
            
            function loadNotifications() {
                // This would typically load notifications from your backend
                // For now, we'll just initialize the notification system
                console.log('Notification system initialized');
            }
            
            // Utility functions
            function showLoading() {
                $('#loading-overlay').addClass('show');
            }
            
            function hideLoading() {
                $('#loading-overlay').removeClass('show');
            }
            
            function showNotification(message, type = 'info') {
                // Remove existing notifications
                $('.notification').remove();
                
                const icon = type === 'success' ? 'fa-check-circle' :
                            type === 'error' ? 'fa-exclamation-circle' :
                            type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
                
                const $notification = $(`
                    <div class="notification ${type}">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="fas ${icon} fa-lg"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold">${getNotificationTitle(type)}</div>
                                <div>${message}</div>
                            </div>
                            <button class="btn-close ms-3" onclick="$(this).closest('.notification').remove()"></button>
                        </div>
                    </div>
                `);
                
                $('#notification-container').append($notification);
                
                // Show animation
                setTimeout(() => {
                    $notification.addClass('show');
                }, 10);
                
                // Auto-remove after 5 seconds
                setTimeout(() => {
                    $notification.removeClass('show');
                    setTimeout(() => {
                        $notification.remove();
                    }, 300);
                }, 5000);
            }
            
            function getNotificationTitle(type) {
                const titles = {
                    'success': 'Succès',
                    'error': 'Erreur',
                    'warning': 'Attention',
                    'info': 'Information'
                };
                return titles[type] || 'Notification';
            }
            
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
            
            // Handle sidebar toggle on mobile
            $('.sidebar-toggle').click(function() {
                $('.sidebar').toggleClass('show');
            });
            
            // Close sidebar when clicking outside on mobile
            $(document).on('click', function(e) {
                if ($(window).width() <= 768) {
                    if (!$(e.target).closest('.sidebar').length && 
                        !$(e.target).closest('.sidebar-toggle').length &&
                        $('.sidebar').hasClass('show')) {
                        $('.sidebar').removeClass('show');
                    }
                }
            });
        });
    </script>
</body>
</html>