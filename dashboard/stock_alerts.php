<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("./config/init.php");
}

$db = DB::getInstance();

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['username'])) {
    Redirect::to('../login.php');
    exit;
}

// Get user from session
$user = $db->getThisQuery(
    "SELECT id, username, `name`, `role` 
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

// Logout if requested
if (isset($_GET['logout'])) {
    $userModel = new User();
    $userModel->logout();
    Redirect::to('../login.php');
    exit;
}

$user_id = (int)$user[0]['id'];
$userName = $user[0]['name'] ?? $user[0]['username'];
$userRole = $user[0]['role'] ?? 'user';

// Check if Subscription class exists
if (class_exists('Subscription')) {
    $subscription = new Subscription($user_id);
    $subscriptionStatus = $subscription->getSubscriptionStatus();
    $subscriptionAlerts = $subscription->getSubscriptionAlerts();
} else {
    $subscriptionStatus = 'active';
    $subscriptionAlerts = [];
}

// Check if Onboarding class exists
if (class_exists('Onboarding')) {
    $onboarding = new Onboarding($user_id);
    
    $currentPage = basename($_SERVER['PHP_SELF']);
    if (!$onboarding->isOnboardingCompleted() && $currentPage !== 'onboarding.php') {
        Redirect::to('./onboarding.php');
        exit;
    }
}

// Check feature access
$canAccessFeatures = true;
if (isset($subscription) && method_exists($subscription, 'canAccessFeatures')) {
    $canAccessFeatures = $subscription->canAccessFeatures();
}

$limitedAccess = !$canAccessFeatures; 

// Récupérer les alertes de stock
$alerts = $db->getThisQuery("
    SELECT 
        sa.*,
        p.id as product_id,
        p.name as product_name,
        p.sku as product_sku,
        p.stock_quantity as product_stock,
        p.min_stock_threshold as product_min_threshold,
        pv.id as variant_id,
        pv.sku as variant_sku,
        pv.variant_name,
        pv.stock_quantity as variant_stock,
        pv.min_stock_threshold as variant_min_threshold,
        pv.max_stock_threshold as variant_max_threshold,
        u.username as created_by_name
    FROM stock_alerts sa
    LEFT JOIN products p ON sa.product_id = p.id
    LEFT JOIN product_variants pv ON sa.variant_id = pv.id
    LEFT JOIN users u ON sa.created_by = u.id
    WHERE p.user_id = ?
    AND sa.is_resolved = 0
    ORDER BY 
        sa.priority DESC,
        sa.created_at DESC
", [$user_id]);

// Récupérer les alertes résolues
$resolvedAlerts = $db->getThisQuery("
    SELECT 
        sa.*,
        p.name as product_name,
        p.sku as product_sku,
        pv.sku as variant_sku,
        pv.variant_name,
        u.username as resolved_by_name
    FROM stock_alerts sa
    LEFT JOIN products p ON sa.product_id = p.id
    LEFT JOIN product_variants pv ON sa.variant_id = pv.id
    LEFT JOIN users u ON sa.resolved_by = u.id
    WHERE p.user_id = ?
    AND sa.is_resolved = 1
    ORDER BY sa.resolved_at DESC
    LIMIT 20
", [$user_id]);

// Récupérer les statistiques d'alertes
$alertStats = $db->getThisQuery("
    SELECT 
        COUNT(*) as total_alerts,
        SUM(CASE WHEN is_resolved = 0 THEN 1 ELSE 0 END) as active_alerts,
        SUM(CASE WHEN is_resolved = 1 THEN 1 ELSE 0 END) as resolved_alerts,
        SUM(CASE WHEN priority = 'high' AND is_resolved = 0 THEN 1 ELSE 0 END) as high_priority,
        SUM(CASE WHEN priority = 'medium' AND is_resolved = 0 THEN 1 ELSE 0 END) as medium_priority,
        SUM(CASE WHEN priority = 'low' AND is_resolved = 0 THEN 1 ELSE 0 END) as low_priority,
        SUM(CASE WHEN alert_type = 'low_stock' AND is_resolved = 0 THEN 1 ELSE 0 END) as low_stock_alerts,
        SUM(CASE WHEN alert_type = 'out_of_stock' AND is_resolved = 0 THEN 1 ELSE 0 END) as out_of_stock_alerts,
        SUM(CASE WHEN alert_type = 'expiring' AND is_resolved = 0 THEN 1 ELSE 0 END) as expiring_alerts
    FROM stock_alerts sa
    LEFT JOIN products p ON sa.product_id = p.id
    WHERE p.user_id = ?
", [$user_id]);

$stats = $alertStats[0] ?? [
    'total_alerts' => 0,
    'active_alerts' => 0,
    'resolved_alerts' => 0,
    'high_priority' => 0,
    'medium_priority' => 0,
    'low_priority' => 0,
    'low_stock_alerts' => 0,
    'out_of_stock_alerts' => 0,
    'expiring_alerts' => 0
];

// Récupérer les paramètres de configuration des alertes
$alertSettings = $db->getThisQuery("
    SELECT * FROM alert_settings 
    WHERE user_id = ? 
    LIMIT 1
", [$user_id]);

$settings = $alertSettings[0] ?? [
    'low_stock_threshold' => 10,
    'send_email_alerts' => 1,
    'send_sms_alerts' => 0,
    'alert_frequency' => 'immediate',
    'working_hours_only' => 0,
    'auto_reorder_enabled' => 0,
    'reorder_quantity' => 20
]; ?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Alertes de Stock - Plateforme">
    <title>Alertes de Stock | OBG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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

        /* Stats Cards */
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
            box-shadow: var(--shadow-md);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.danger::before {
            background: linear-gradient(90deg, var(--danger-color), #dc2626);
        }

        .stat-card.warning::before {
            background: linear-gradient(90deg, var(--warning-color), #d97706);
        }

        .stat-card.info::before {
            background: linear-gradient(90deg, var(--info-color), #0284c7);
        }

        .stat-card.success::before {
            background: linear-gradient(90deg, var(--success-color), #059669);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card.danger .stat-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .stat-card.warning .stat-icon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stat-card.info .stat-icon {
            background: rgba(14, 165, 233, 0.1);
            color: var(--info-color);
        }

        .stat-card.success .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Alert Cards */
        .alert-card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .alert-card.high {
            border-left: 4px solid var(--danger-color);
        }

        .alert-card.medium {
            border-left: 4px solid var(--warning-color);
        }

        .alert-card.low {
            border-left: 4px solid var(--info-color);
        }

        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .alert-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .alert-card.high .alert-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .alert-card.medium .alert-icon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .alert-card.low .alert-icon {
            background: rgba(14, 165, 233, 0.1);
            color: var(--info-color);
        }

        .alert-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .alert-info p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin: 0;
        }

        .alert-priority {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert-card.high .alert-priority {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-card.medium .alert-priority {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .alert-card.low .alert-priority {
            background: rgba(14, 165, 233, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(14, 165, 233, 0.2);
        }

        .alert-content {
            margin-bottom: 1.5rem;
        }

        .alert-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 0.95rem;
            font-weight: 500;
        }

        .stock-indicator {
            width: 100%;
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.25rem;
        }

        .stock-fill {
            height: 100%;
            border-radius: 3px;
        }

        .stock-fill.low {
            background: var(--warning-color);
        }

        .stock-fill.out {
            background: var(--danger-color);
        }

        .stock-fill.expiring {
            background: var(--info-color);
        }

        .alert-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        /* Tabs */
        .tabs-container {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .tabs-nav {
            display: flex;
            background: var(--background-color);
            border-bottom: 1px solid var(--border-color);
        }

        .tab-btn {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            color: var(--primary-color);
            background: var(--surface-color);
        }

        .tab-btn.active {
            color: var(--primary-color);
            background: var(--surface-color);
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-color);
        }

        .tab-content {
            display: none;
            padding: 1.5rem;
        }

        .tab-content.active {
            display: block;
        }

        /* Empty State */
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

        /* Settings Panel */
        .settings-panel {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .settings-section {
            margin-bottom: 2rem;
        }

        .settings-section:last-child {
            margin-bottom: 0;
        }

        .settings-section h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: var(--surface-color);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-hint {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Switch Toggle */
        .switch-container {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-color);
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        .switch-label {
            font-weight: 500;
            color: var(--text-primary);
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-icon.primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-icon.success {
            background: var(--success-color);
            color: white;
        }

        .btn-icon.danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* Filters */
        .filters-section {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: block;
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .tabs-nav {
                flex-direction: column;
            }

            .tab-btn {
                width: 100%;
                justify-content: center;
            }

            .filters-row {
                flex-direction: column;
            }

            .filter-group {
                min-width: 100%;
            }

            .alert-details {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .top-navbar {
                padding: 1rem;
            }

            .search-bar {
                margin: 0;
                max-width: none;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Badge for Alert Count */
        .alert-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .tab-btn .alert-count-badge {
            background: var(--danger-color);
            color: white;
        }

        /* Time Indicator */
        .time-indicator {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Resolved Alerts */
        .resolved-alert {
            opacity: 0.8;
            background: var(--background-color);
        }

        .resolved-alert:hover {
            opacity: 1;
        }

        .resolved-badge {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'stock_alerts';
    require_once('../assets/sidebar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <button class="btn btn-link sidebar-toggle d-md-none">
                <i class="fas fa-bars" style="color: #5f34d9;"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Rechercher des alertes..." id="global-search">
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="notifications">
                    <button class="notification-btn" data-tooltip="Notifications" style="border: none; background: none">
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
                    <h2><i class="fas fa-bell text-warning me-2"></i> Alertes de Stock</h2>
                    <p class="text-muted mb-0">Surveillez et gérez vos alertes de stock en temps réel</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-warning" onclick="testAlerts()">
                        <i class="fas fa-bell"></i>
                        Tester les alertes
                    </button>
                    <button class="btn btn-primary" onclick="markAllAsRead()">
                        <i class="fas fa-check-double"></i>
                        Tout marquer comme lu
                    </button>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['active_alerts']; ?></h3>
                        <p>Alertes actives</p>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['high_priority']; ?></h3>
                        <p>Priorité haute</p>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['low_stock_alerts']; ?></h3>
                        <p>Stocks faibles</p>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['resolved_alerts']; ?></h3>
                        <p>Alertes résolues</p>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-nav">
                    <button class="tab-btn active" data-tab="active-alerts">
                        <i class="fas fa-bell"></i>
                        Alertes actives
                        <?php if ($stats['active_alerts'] > 0): ?>
                            <span class="alert-count-badge"><?php echo $stats['active_alerts']; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="tab-btn" data-tab="resolved-alerts">
                        <i class="fas fa-check-circle"></i>
                        Alertes résolues
                    </button>
                    <button class="tab-btn" data-tab="settings">
                        <i class="fas fa-cog"></i>
                        Paramètres
                    </button>
                </div>
                
                <!-- Active Alerts Tab -->
                <div id="active-alerts" class="tab-content active">
                    <?php if (!empty($alerts)): ?>
                        <div class="filters-section">
                            <div class="filters-row">
                                <div class="filter-group">
                                    <label class="filter-label">Type d'alerte</label>
                                    <select class="form-control" id="filter-type">
                                        <option value="">Tous les types</option>
                                        <option value="low_stock">Stock faible</option>
                                        <option value="out_of_stock">Rupture de stock</option>
                                        <option value="expiring">Produits expirés</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="filter-label">Priorité</label>
                                    <select class="form-control" id="filter-priority">
                                        <option value="">Toutes</option>
                                        <option value="high">Haute</option>
                                        <option value="medium">Moyenne</option>
                                        <option value="low">Basse</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="filter-label">Tri par</label>
                                    <select class="form-control" id="filter-sort">
                                        <option value="date_desc">Date (récent)</option>
                                        <option value="date_asc">Date (ancien)</option>
                                        <option value="priority">Priorité</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div id="alerts-container">
                            <?php foreach ($alerts as $alert): ?>
                                <?php
                                $stockQuantity = $alert['variant_stock'] ?? $alert['product_stock'];
                                $minThreshold = $alert['variant_min_threshold'] ?? $alert['product_min_threshold'] ?? 10;
                                $maxThreshold = $alert['variant_max_threshold'] ?? 100;
                                
                                // Determine alert type icon and color
                                $alertTypeIcon = '';
                                $alertTypeColor = '';
                                $stockPercentage = 0;
                                
                                switch ($alert['alert_type']) {
                                    case 'low_stock':
                                        $alertTypeIcon = 'fas fa-chart-line-down';
                                        $alertTypeColor = 'warning';
                                        $stockPercentage = ($stockQuantity / $minThreshold) * 100;
                                        break;
                                    case 'out_of_stock':
                                        $alertTypeIcon = 'fas fa-times-circle';
                                        $alertTypeColor = 'danger';
                                        $stockPercentage = 0;
                                        break;
                                    case 'expiring':
                                        $alertTypeIcon = 'fas fa-clock';
                                        $alertTypeColor = 'info';
                                        break;
                                }
                                ?>
                                <div class="alert-card <?php echo $alert['priority']; ?>" 
                                     data-alert-id="<?php echo $alert['id']; ?>"
                                     data-alert-type="<?php echo $alert['alert_type']; ?>"
                                     data-alert-priority="<?php echo $alert['priority']; ?>">
                                    <div class="alert-header">
                                        <div class="alert-title">
                                            <div class="alert-icon">
                                                <i class="<?php echo $alertTypeIcon; ?>"></i>
                                            </div>
                                            <div class="alert-info">
                                                <h4>
                                                    <?php echo htmlspecialchars($alert['product_name']); ?>
                                                    <?php if ($alert['variant_name']): ?>
                                                        <small class="text-muted">- <?php echo htmlspecialchars($alert['variant_name']); ?></small>
                                                    <?php endif; ?>
                                                </h4>
                                                <p>
                                                    <i class="fas fa-hashtag"></i>
                                                    <?php echo htmlspecialchars($alert['variant_sku'] ?? $alert['product_sku']); ?>
                                                    • 
                                                    <i class="fas fa-calendar"></i>
                                                    <?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <span class="alert-priority"><?php echo ucfirst($alert['priority']); ?></span>
                                    </div>
                                    
                                    <div class="alert-content">
                                        <div class="alert-details">
                                            <div class="detail-item">
                                                <span class="detail-label">Type d'alerte</span>
                                                <span class="detail-value">
                                                    <?php 
                                                    echo $alert['alert_type'] === 'low_stock' ? 'Stock faible' : 
                                                         ($alert['alert_type'] === 'out_of_stock' ? 'Rupture de stock' : 'Produit expirant');
                                                    ?>
                                                </span>
                                            </div>
                                            
                                            <div class="detail-item">
                                                <span class="detail-label">Stock actuel</span>
                                                <div>
                                                    <span class="detail-value"><?php echo $stockQuantity; ?> unités</span>
                                                    <?php if ($alert['alert_type'] !== 'expiring'): ?>
                                                        <div class="stock-indicator">
                                                            <div class="stock-fill <?php echo $alert['alert_type']; ?>" 
                                                                 style="width: <?php echo min($stockPercentage, 100); ?>%"></div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="detail-item">
                                                <span class="detail-label">Seuil minimum</span>
                                                <span class="detail-value"><?php echo $minThreshold; ?> unités</span>
                                            </div>
                                            
                                            <?php if ($alert['alert_type'] === 'expiring' && !empty($alert['expiry_date'])): ?>
                                            <div class="detail-item">
                                                <span class="detail-label">Date d'expiration</span>
                                                <span class="detail-value"><?php echo date('d/m/Y', strtotime($alert['expiry_date'])); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($alert['message'])): ?>
                                            <div class="alert-message mt-3 p-3 bg-<?php echo $alertTypeColor; ?>-light rounded">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <?php echo htmlspecialchars($alert['message']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="alert-actions">
                                        <div class="time-indicator">
                                            <i class="fas fa-clock"></i>
                                            Il y a <?php echo timeElapsed($alert['created_at']); ?>
                                        </div>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-success" 
                                                    onclick="resolveAlert(<?php echo $alert['id']; ?>, this)">
                                                <i class="fas fa-check"></i>
                                                Résoudre
                                            </button>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="viewProductDetails(<?php echo $alert['product_id']; ?>, <?php echo $alert['variant_id'] ?: 'null'; ?>)">
                                                <i class="fas fa-eye"></i>
                                                Voir
                                            </button>
                                            <button class="btn btn-sm btn-warning" 
                                                    onclick="adjustStockFromAlert(<?php echo $alert['product_id']; ?>, <?php echo $alert['variant_id'] ?: 'null'; ?>)">
                                                <i class="fas fa-edit"></i>
                                                Ajuster
                                            </button>
                                            <button class="btn btn-sm btn-info" 
                                                    onclick="createPurchaseOrder(<?php echo $alert['product_id']; ?>, <?php echo $alert['variant_id'] ?: 'null'; ?>)">
                                                <i class="fas fa-shopping-cart"></i>
                                                Commander
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash text-success"></i>
                            <h3>Aucune alerte active</h3>
                            <p>Tous vos stocks sont à des niveaux satisfaisants.</p>
                            <button class="btn btn-primary" onclick="checkStockLevels()">
                                <i class="fas fa-sync"></i>
                                Vérifier les niveaux
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Resolved Alerts Tab -->
                <div id="resolved-alerts" class="tab-content">
                    <?php if (!empty($resolvedAlerts)): ?>
                        <div class="filters-section">
                            <div class="filters-row">
                                <div class="filter-group">
                                    <label class="filter-label">Période</label>
                                    <select class="form-control" id="resolved-period">
                                        <option value="7">7 derniers jours</option>
                                        <option value="30" selected>30 derniers jours</option>
                                        <option value="90">90 derniers jours</option>
                                        <option value="all">Toutes</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div id="resolved-alerts-container">
                            <?php foreach ($resolvedAlerts as $alert): ?>
                                <div class="alert-card resolved-alert">
                                    <div class="alert-header">
                                        <div class="alert-title">
                                            <div class="alert-icon">
                                                <i class="fas fa-check-circle text-success"></i>
                                            </div>
                                            <div class="alert-info">
                                                <h4>
                                                    <?php echo htmlspecialchars($alert['product_name']); ?>
                                                    <?php if ($alert['variant_name']): ?>
                                                        <small class="text-muted">- <?php echo htmlspecialchars($alert['variant_name']); ?></small>
                                                    <?php endif; ?>
                                                </h4>
                                                <p>
                                                    <i class="fas fa-hashtag"></i>
                                                    <?php echo htmlspecialchars($alert['variant_sku'] ?? $alert['product_sku']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <span class="resolved-badge">
                                            <i class="fas fa-check"></i>
                                            Résolue
                                        </span>
                                    </div>
                                    
                                    <div class="alert-content">
                                        <div class="alert-details">
                                            <div class="detail-item">
                                                <span class="detail-label">Type d'alerte</span>
                                                <span class="detail-value">
                                                    <?php 
                                                    echo $alert['alert_type'] === 'low_stock' ? 'Stock faible' : 
                                                         ($alert['alert_type'] === 'out_of_stock' ? 'Rupture de stock' : 'Produit expirant');
                                                    ?>
                                                </span>
                                            </div>
                                            
                                            <div class="detail-item">
                                                <span class="detail-label">Résolue le</span>
                                                <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($alert['resolved_at'])); ?></span>
                                            </div>
                                            
                                            <div class="detail-item">
                                                <span class="detail-label">Résolue par</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($alert['resolved_by_name']); ?></span>
                                            </div>
                                            
                                            <?php if (!empty($alert['resolution_notes'])): ?>
                                            <div class="detail-item">
                                                <span class="detail-label">Notes</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($alert['resolution_notes']); ?></span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="alert-actions">
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                onclick="reopenAlert(<?php echo $alert['id']; ?>)">
                                            <i class="fas fa-redo"></i>
                                            Rouvrir
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history text-muted"></i>
                            <h3>Aucune alerte résolue</h3>
                            <p>Les alertes résolues apparaîtront ici.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Settings Tab -->
                <div id="settings" class="tab-content">
                    <div class="settings-panel">
                        <form id="alertSettingsForm">
                            <div class="settings-section">
                                <h4><i class="fas fa-sliders-h me-2"></i>Seuils d'alerte</h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="low_stock_threshold" class="form-label">Seuil de stock faible</label>
                                        <input type="number" id="low_stock_threshold" name="low_stock_threshold" 
                                               class="form-control" min="1" max="1000" 
                                               value="<?php echo $settings['low_stock_threshold']; ?>" required>
                                        <div class="form-hint">Nombre d'unités minimum avant alerte (par défaut: 10)</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="critical_stock_threshold" class="form-label">Seuil critique</label>
                                        <input type="number" id="critical_stock_threshold" name="critical_stock_threshold" 
                                               class="form-control" min="1" max="100" 
                                               value="<?php echo $settings['critical_stock_threshold'] ?? 3; ?>">
                                        <div class="form-hint">Nombre d'unités minimum avant alerte critique (par défaut: 3)</div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="expiry_days_threshold" class="form-label">Alerte d'expiration (jours)</label>
                                    <input type="number" id="expiry_days_threshold" name="expiry_days_threshold" 
                                           class="form-control" min="1" max="365" 
                                           value="<?php echo $settings['expiry_days_threshold'] ?? 30; ?>">
                                    <div class="form-hint">Alerter X jours avant l'expiration (par défaut: 30)</div>
                                </div>
                            </div>
                            
                            <div class="settings-section">
                                <h4><i class="fas fa-bell me-2"></i>Notifications</h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <div class="switch-container">
                                            <label class="switch">
                                                <input type="checkbox" id="send_email_alerts" name="send_email_alerts" 
                                                       <?php echo $settings['send_email_alerts'] ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <span class="switch-label">Alertes par email</span>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="switch-container">
                                            <label class="switch">
                                                <input type="checkbox" id="send_sms_alerts" name="send_sms_alerts" 
                                                       <?php echo $settings['send_sms_alerts'] ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <span class="switch-label">Alertes par SMS</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="alert_frequency" class="form-label">Fréquence des alertes</label>
                                        <select id="alert_frequency" name="alert_frequency" class="form-control">
                                            <option value="immediate" <?php echo $settings['alert_frequency'] === 'immediate' ? 'selected' : ''; ?>>Immédiate</option>
                                            <option value="hourly" <?php echo $settings['alert_frequency'] === 'hourly' ? 'selected' : ''; ?>>Horaire</option>
                                            <option value="daily" <?php echo $settings['alert_frequency'] === 'daily' ? 'selected' : ''; ?>>Quotidienne</option>
                                            <option value="weekly" <?php echo $settings['alert_frequency'] === 'weekly' ? 'selected' : ''; ?>>Hebdomadaire</option>
                                        </select>
                                        <div class="form-hint">Fréquence d'envoi des notifications</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="switch-container">
                                            <label class="switch">
                                                <input type="checkbox" id="working_hours_only" name="working_hours_only" 
                                                       <?php echo $settings['working_hours_only'] ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <span class="switch-label">Heures de travail uniquement</span>
                                        </div>
                                        <div class="form-hint">Envoyer les alertes seulement pendant les heures de travail (9h-18h)</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="settings-section">
                                <h4><i class="fas fa-robot me-2"></i>Automatisation</h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <div class="switch-container">
                                            <label class="switch">
                                                <input type="checkbox" id="auto_reorder_enabled" name="auto_reorder_enabled" 
                                                       <?php echo $settings['auto_reorder_enabled'] ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <span class="switch-label">Réapprovisionnement automatique</span>
                                        </div>
                                        <div class="form-hint">Créer automatiquement des commandes pour les stocks faibles</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="reorder_quantity" class="form-label">Quantité de réapprovisionnement</label>
                                        <input type="number" id="reorder_quantity" name="reorder_quantity" 
                                               class="form-control" min="1" max="10000" 
                                               value="<?php echo $settings['reorder_quantity']; ?>">
                                        <div class="form-hint">Quantité par défaut pour les commandes automatiques</div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="auto_resolve_days" class="form-label">Résolution automatique (jours)</label>
                                    <input type="number" id="auto_resolve_days" name="auto_resolve_days" 
                                           class="form-control" min="0" max="365" 
                                           value="<?php echo $settings['auto_resolve_days'] ?? 7; ?>">
                                    <div class="form-hint">Résoudre automatiquement les alertes après X jours (0 = désactivé)</div>
                                </div>
                            </div>
                            
                            <div class="settings-section">
                                <h4><i class="fas fa-user-plus me-2"></i>Destinataires</h4>
                                
                                <div class="form-group">
                                    <label for="alert_recipients" class="form-label">Emails des destinataires</label>
                                    <textarea id="alert_recipients" name="alert_recipients" 
                                              class="form-control" rows="3" 
                                              placeholder="email1@exemple.com, email2@exemple.com"><?php echo htmlspecialchars($settings['alert_recipients'] ?? ''); ?></textarea>
                                    <div class="form-hint">Séparez les emails par des virgules</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone_numbers" class="form-label">Numéros de téléphone (SMS)</label>
                                    <textarea id="phone_numbers" name="phone_numbers" 
                                              class="form-control" rows="3" 
                                              placeholder="+212612345678, +212698765432"><?php echo htmlspecialchars($settings['phone_numbers'] ?? ''); ?></textarea>
                                    <div class="form-hint">Format international (+212XXXXXXXXX), séparés par des virgules</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-secondary" onclick="resetSettings()">
                                    <i class="fas fa-undo"></i>
                                    Réinitialiser
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Enregistrer les paramètres
                                </button>
                            </div>
                        </form>
                    </div>
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

    <!-- Resolve Alert Modal -->
    <div class="modal fade" id="resolveAlertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2 text-success"></i>
                        Résoudre l'alerte
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="resolveAlertForm">
                        <input type="hidden" id="resolveAlertId" name="alert_id">
                        
                        <div class="form-group">
                            <label for="resolution_notes" class="form-label">Notes de résolution</label>
                            <textarea id="resolution_notes" name="resolution_notes" 
                                      class="form-control" rows="4" 
                                      placeholder="Décrivez comment cette alerte a été résolue..." required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="prevent_recurrence" name="prevent_recurrence">
                                <label class="form-check-label" for="prevent_recurrence">
                                    Ajuster le seuil pour éviter la récurrence
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-success" id="confirmResolve">Confirmer la résolution</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Alerts Modal -->
    <div class="modal fade" id="testAlertsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-bell me-2 text-warning"></i>
                        Tester les alertes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Cette fonction enverra des notifications de test selon vos paramètres actuels.
                    </div>
                    
                    <div class="form-group">
                        <label for="testAlertType" class="form-label">Type d'alerte de test</label>
                        <select id="testAlertType" class="form-control">
                            <option value="low_stock">Stock faible</option>
                            <option value="out_of_stock">Rupture de stock</option>
                            <option value="expiring">Produit expirant</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="testPriority" class="form-label">Priorité</label>
                        <select id="testPriority" class="form-control">
                            <option value="low">Basse</option>
                            <option value="medium">Moyenne</option>
                            <option value="high">Haute</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="testEmail" checked>
                            <label class="form-check-label" for="testEmail">
                                Envoyer un email de test
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="testSMS">
                            <label class="form-check-label" for="testSMS">
                                Envoyer un SMS de test
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-warning" id="sendTestAlert">
                        <i class="fas fa-paper-plane"></i>
                        Envoyer le test
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize
            initializeEventListeners();
            
            function initializeEventListeners() {
                // Tab switching
                $('.tab-btn').click(function() {
                    const tabId = $(this).data('tab');
                    
                    // Update active tab
                    $('.tab-btn').removeClass('active');
                    $(this).addClass('active');
                    
                    // Show corresponding content
                    $('.tab-content').removeClass('active');
                    $(`#${tabId}`).addClass('active');
                });
                
                // Filters
                $('#filter-type, #filter-priority, #filter-sort').change(applyFilters);
                $('#global-search').on('input', debounce(handleGlobalSearch, 300));
                
                // Settings form
                $('#alertSettingsForm').submit(saveAlertSettings);
                
                // Resolve alert modal
                $('#confirmResolve').click(confirmResolveAlert);
                
                // Test alerts
                $('#sendTestAlert').click(sendTestAlert);
                
                // Mark all as read
                $('#markAllReadBtn').click(markAllAsRead);
            }
            
            function applyFilters() {
                const typeFilter = $('#filter-type').val();
                const priorityFilter = $('#filter-priority').val();
                const sortFilter = $('#filter-sort').val();
                
                let $alerts = $('#alerts-container .alert-card');
                
                // Apply type filter
                if (typeFilter) {
                    $alerts = $alerts.filter(`[data-alert-type="${typeFilter}"]`);
                }
                
                // Apply priority filter
                if (priorityFilter) {
                    $alerts = $alerts.filter(`[data-alert-priority="${priorityFilter}"]`);
                }
                
                // Apply sorting
                const alertsArray = $alerts.toArray();
                
                switch (sortFilter) {
                    case 'date_desc':
                        alertsArray.sort((a, b) => {
                            const dateA = new Date($(a).data('created-at') || 0);
                            const dateB = new Date($(b).data('created-at') || 0);
                            return dateB - dateA;
                        });
                        break;
                    case 'date_asc':
                        alertsArray.sort((a, b) => {
                            const dateA = new Date($(a).data('created-at') || 0);
                            const dateB = new Date($(b).data('created-at') || 0);
                            return dateA - dateB;
                        });
                        break;
                    case 'priority':
                        const priorityOrder = { high: 3, medium: 2, low: 1 };
                        alertsArray.sort((a, b) => {
                            const priorityA = priorityOrder[$(a).data('alert-priority')] || 0;
                            const priorityB = priorityOrder[$(b).data('alert-priority')] || 0;
                            return priorityB - priorityA;
                        });
                        break;
                }
                
                // Reorder alerts
                const $container = $('#alerts-container');
                $container.empty();
                alertsArray.forEach(alert => $container.append(alert));
                
                // Show empty state if no alerts match
                if (alertsArray.length === 0) {
                    $container.html(`
                        <div class="empty-state">
                            <i class="fas fa-search text-muted"></i>
                            <h3>Aucune alerte trouvée</h3>
                            <p>Aucune alerte ne correspond à vos critères de filtrage.</p>
                            <button class="btn btn-secondary" onclick="resetFilters()">
                                <i class="fas fa-undo"></i>
                                Réinitialiser les filtres
                            </button>
                        </div>
                    `);
                }
            }
            
            function handleGlobalSearch() {
                const searchTerm = $('#global-search').val().toLowerCase();
                
                if (searchTerm) {
                    $('.alert-card').each(function() {
                        const $alert = $(this);
                        const alertText = $alert.text().toLowerCase();
                        
                        if (alertText.includes(searchTerm)) {
                            $alert.show();
                            $alert.addClass('highlight');
                        } else {
                            $alert.hide();
                            $alert.removeClass('highlight');
                        }
                    });
                } else {
                    $('.alert-card').show().removeClass('highlight');
                }
            }
            
            function resetFilters() {
                $('#filter-type').val('');
                $('#filter-priority').val('');
                $('#filter-sort').val('date_desc');
                $('#global-search').val('');
                applyFilters();
            }
            
            function resolveAlert(alertId, button) {
                // Store alert ID
                $('#resolveAlertId').val(alertId);
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('resolveAlertModal'));
                modal.show();
            }
            
            function confirmResolveAlert() {
                const alertId = $('#resolveAlertId').val();
                const notes = $('#resolution_notes').val();
                const preventRecurrence = $('#prevent_recurrence').is(':checked');
                
                if (!notes.trim()) {
                    showNotification('Veuillez ajouter des notes de résolution', 'warning');
                    return;
                }
                
                showLoading();
                
                $.ajax({
                    url: './controllers/resolve_alert.php',
                    method: 'POST',
                    data: {
                        alert_id: alertId,
                        resolution_notes: notes,
                        prevent_recurrence: preventRecurrence
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('Alerte résolue avec succès', 'success');
                            
                            // Remove alert from UI
                            $(`.alert-card[data-alert-id="${alertId}"]`).remove();
                            
                            // Update stats
                            updateAlertStats();
                            
                            // Close modal
                            $('#resolveAlertModal').modal('hide');
                            $('#resolveAlertForm')[0].reset();
                        } else {
                            showNotification(response.message || 'Erreur lors de la résolution', 'error');
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
            
            function viewProductDetails(productId, variantId) {
                // Navigate to product page or open product details
                if (variantId) {
                    window.location.href = `stock_management.php?action=view_sku&sku_id=${variantId}`;
                } else {
                    window.location.href = `items.php?view=product&id=${productId}`;
                }
            }
            
            function adjustStockFromAlert(productId, variantId) {
                // Open stock adjustment modal from inventory page
                if (typeof window.parent !== 'undefined' && window.parent.showAdjustStockModal) {
                    window.parent.showAdjustStockModal(variantId || productId, variantId ? 'variant' : 'product');
                } else {
                    // Fallback to new tab
                    window.open(`stock_inventory.php?adjust=${variantId || productId}&type=${variantId ? 'variant' : 'product'}`, '_blank');
                }
            }
            
            function createPurchaseOrder(productId, variantId) {
                // In a real implementation, this would create a purchase order
                // For now, we'll show a notification
                showNotification('Fonctionnalité de commande en développement', 'info');
            }
            
            function reopenAlert(alertId) {
                if (confirm('Êtes-vous sûr de vouloir rouvrir cette alerte ?')) {
                    showLoading();
                    
                    $.ajax({
                        url: './controllers/reopen_alert.php',
                        method: 'POST',
                        data: { alert_id: alertId },
                        success: function(response) {
                            if (response.success) {
                                showNotification('Alerte rouverte avec succès', 'success');
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            } else {
                                showNotification(response.message || 'Erreur lors de la réouverture', 'error');
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
            
            function saveAlertSettings(e) {
                e.preventDefault();
                
                const formData = {
                    low_stock_threshold: $('#low_stock_threshold').val(),
                    critical_stock_threshold: $('#critical_stock_threshold').val(),
                    expiry_days_threshold: $('#expiry_days_threshold').val(),
                    send_email_alerts: $('#send_email_alerts').is(':checked') ? 1 : 0,
                    send_sms_alerts: $('#send_sms_alerts').is(':checked') ? 1 : 0,
                    alert_frequency: $('#alert_frequency').val(),
                    working_hours_only: $('#working_hours_only').is(':checked') ? 1 : 0,
                    auto_reorder_enabled: $('#auto_reorder_enabled').is(':checked') ? 1 : 0,
                    reorder_quantity: $('#reorder_quantity').val(),
                    auto_resolve_days: $('#auto_resolve_days').val(),
                    alert_recipients: $('#alert_recipients').val(),
                    phone_numbers: $('#phone_numbers').val()
                };
                
                showLoading();
                
                $.ajax({
                    url: './controllers/save_alert_settings.php',
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            showNotification('Paramètres enregistrés avec succès', 'success');
                        } else {
                            showNotification(response.message || 'Erreur lors de l\'enregistrement', 'error');
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
            
            function resetSettings() {
                if (confirm('Êtes-vous sûr de vouloir réinitialiser tous les paramètres à leurs valeurs par défaut ?')) {
                    $('#alertSettingsForm')[0].reset();
                    showNotification('Paramètres réinitialisés', 'info');
                }
            }
            
            function testAlerts() {
                const modal = new bootstrap.Modal(document.getElementById('testAlertsModal'));
                modal.show();
            }
            
            function sendTestAlert() {
                const alertType = $('#testAlertType').val();
                const priority = $('#testPriority').val();
                const sendEmail = $('#testEmail').is(':checked');
                const sendSMS = $('#testSMS').is(':checked');
                
                if (!sendEmail && !sendSMS) {
                    showNotification('Veuillez sélectionner au moins un type de notification', 'warning');
                    return;
                }
                
                showLoading();
                
                $.ajax({
                    url: './controllers/test_alerts.php',
                    method: 'POST',
                    data: {
                        alert_type: alertType,
                        priority: priority,
                        send_email: sendEmail,
                        send_sms: sendSMS
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotification('Notification de test envoyée avec succès', 'success');
                            $('#testAlertsModal').modal('hide');
                        } else {
                            showNotification(response.message || 'Erreur lors de l\'envoi du test', 'error');
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
            
            function checkStockLevels() {
                showLoading();
                
                $.ajax({
                    url: './controllers/check_stock_levels.php',
                    method: 'POST',
                    success: function(response) {
                        if (response.success) {
                            showNotification(response.message || 'Vérification des niveaux de stock terminée', 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showNotification(response.message || 'Erreur lors de la vérification', 'error');
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
            
            function markAllAsRead() {
                if (confirm('Êtes-vous sûr de vouloir marquer toutes les alertes comme lues ?')) {
                    showLoading();
                    
                    $.ajax({
                        url: './controllers/mark_all_alerts_read.php',
                        method: 'POST',
                        success: function(response) {
                            if (response.success) {
                                showNotification('Toutes les alertes ont été marquées comme lues', 'success');
                                setTimeout(() => {
                                    location.reload();
                                }, 1000);
                            } else {
                                showNotification(response.message || 'Erreur lors de l\'opération', 'error');
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
            
            function updateAlertStats() {
                // In a real implementation, update stats via AJAX
                // For now, we'll just reload after a short delay
                setTimeout(() => {
                    $.ajax({
                        url: './controllers/get_alert_stats.php',
                        method: 'GET',
                        success: function(response) {
                            if (response.success) {
                                // Update stats in UI
                                $('.stat-card:nth-child(1) h3').text(response.data.active_alerts);
                                $('.stat-card:nth-child(2) h3').text(response.data.high_priority);
                                $('.stat-card:nth-child(3) h3').text(response.data.low_stock_alerts);
                                
                                // Update tab badge
                                const badge = $('.tab-btn.active .alert-count-badge');
                                if (response.data.active_alerts > 0) {
                                    badge.text(response.data.active_alerts).show();
                                } else {
                                    badge.hide();
                                }
                            }
                        }
                    });
                }, 500);
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
                
                setTimeout(() => {
                    $notification.addClass('show');
                }, 10);
                
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

<?php
// Helper function to display time elapsed
function timeElapsed($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'an',
        'm' => 'mois',
        'w' => 'semaine',
        'd' => 'jour',
        'h' => 'heure',
        'i' => 'minute',
        's' => 'seconde',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) : 'à l\'instant';
}
?>