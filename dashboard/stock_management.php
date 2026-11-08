<?php
// stock_management.php
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

$user_id = (int) $user[0]['id'];
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

// Handle actions
$action = $_GET['action'] ?? '';
$sku_id = $_GET['sku_id'] ?? 0;
$product_id = $_GET['product_id'] ?? 0;

// Get all products for dropdown
$products = $db->getThisQuery("
    SELECT id, name, sku, stock_quantity 
    FROM products 
    WHERE user_id = ? 
    ORDER BY name ASC
", [$user_id]);

// Get all SKUs
$skus = $db->getThisQuery("
    SELECT 
        pv.id,
        pv.product_id,
        pv.sku,
        pv.variant_name,
        pv.attributes,
        pv.price,
        pv.compare_price,
        pv.cost_price,
        pv.stock_quantity,
        pv.min_stock_threshold,
        pv.max_stock_threshold,
        pv.status,
        pv.created_at,
        p.name as product_name,
        p.sku as parent_sku
    FROM product_variants pv
    LEFT JOIN products p ON pv.product_id = p.id
    WHERE p.user_id = ?
    ORDER BY pv.created_at DESC
", [$user_id]);

// Get specific SKU if editing
$current_sku = null;
if ($sku_id) {
    $current_sku = $db->getThisQuery("
        SELECT pv.*, p.name as product_name 
        FROM product_variants pv
        LEFT JOIN products p ON pv.product_id = p.id
        WHERE pv.id = ? AND p.user_id = ?
    ", [$sku_id, $user_id]);
    
    if ($current_sku) {
        $current_sku = $current_sku[0];
    }
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
    <meta name="description" content="Gestion des Variantes de Produits - Plateforme">
    <title>Gestion des Variantes | OBG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
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
            padding: 0;
        }

        .tab-content.active {
            display: block;
        }

        /* Variant List */
        .variant-list-container {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .variant-list-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .variant-list-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Filters */
        .filters-section {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--background-color);
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
            position: relative;
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

        .filter-input,
        .filter-select {
            width: 100%;
            padding: 0.625rem 0.875rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: var(--surface-color);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .search-icon-filter {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .filter-input.with-icon {
            padding-left: 2.5rem;
        }

        /* Variant Table */
        .variant-table {
            width: 100%;
            border-collapse: collapse;
        }

        .variant-table th {
            text-align: left;
            padding: 1rem 1.5rem;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .variant-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .variant-table tr:hover {
            background: var(--background-color);
        }

        /* Variant Badges */
        .variant-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .variant-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .variant-badge.inactive {
            background: rgba(100, 116, 139, 0.1);
            color: var(--secondary-color);
            border: 1px solid rgba(100, 116, 139, 0.2);
        }

        .variant-badge.disabled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Stock Level Indicator */
        .stock-level {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stock-indicator {
            width: 80px;
            height: 6px;
            background: var(--border-color);
            border-radius: 3px;
            overflow: hidden;
            position: relative;
        }

        .stock-fill {
            height: 100%;
            position: absolute;
            left: 0;
            top: 0;
            border-radius: 3px;
        }

        .stock-fill.low {
            background: var(--warning-color);
            width: 25%;
        }

        .stock-fill.medium {
            background: var(--warning-color);
            width: 50%;
        }

        .stock-fill.high {
            background: var(--success-color);
            width: 75%;
        }

        .stock-fill.full {
            background: var(--success-color);
            width: 100%;
        }

        .stock-fill.out {
            background: var(--danger-color);
            width: 100%;
        }

        /* Variant Info */
        .variant-info {
            max-width: 200px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-color);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .action-btn.edit:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .action-btn.delete:hover {
            background: var(--danger-color);
            color: white;
            border-color: var(--danger-color);
        }

        .action-btn.view:hover {
            background: var(--info-color);
            color: white;
            border-color: var(--info-color);
        }

        /* Form Container */
        .form-container {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            padding: 2rem;
        }

        .form-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .form-header h3 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form Groups */
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

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: var(--surface-color);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-hint {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Variant Fields */
        .variant-fields {
            background: var(--background-color);
            border-radius: var(--border-radius-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .variant-fields-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        /* Price Row */
        .price-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .price-group {
            position: relative;
        }

        .price-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .price-input {
            padding-left: 2.5rem !important;
        }

        /* Stock Threshold */
        .threshold-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
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

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .pagination {
            display: flex;
            gap: 0.25rem;
            margin: 0;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            background: var(--surface-color);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .page-link:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-item.active .page-link {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .page-item.disabled .page-link {
            background: var(--background-color);
            color: var(--text-secondary);
            border-color: var(--border-color);
            cursor: not-allowed;
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

            .form-row {
                grid-template-columns: 1fr;
            }

            .price-row,
            .threshold-row {
                grid-template-columns: 1fr;
            }

            .variant-table {
                display: block;
                overflow-x: auto;
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
        }

        .select2-container {
            width: 100% !important;
        }

        .form-group {
            margin-bottom: 0rem; 
        }

        /* Select2 Customization */
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            min-height: 42px;
            background: var(--surface-color);
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
            padding-left: 1rem;
            color: var(--text-primary);
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px;
        }

        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'stock_management';
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
                <input type="text" placeholder="Rechercher variantes, produits..." id="global-search">
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="notifications">
                    <button class="notification-btn" data-tooltip="Notifications" style="background: none; border:none;">
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
                    <h2><i class="fas fa-layer-group text-primary me-2"></i> Gestion des Variantes</h2>
                    <p class="text-muted mb-0">Gérez les variantes et SKUs de vos produits</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="stock_overview.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Retour
                    </a>
                    <button class="btn btn-primary" onclick="showAddVariantForm()">
                        <i class="fas fa-plus"></i>
                        Nouvelle Variante
                    </button>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-nav">
                    <button class="tab-btn active" data-tab="all-variants">
                        <i class="fas fa-list"></i>
                        Toutes les Variantes
                    </button>
                    <button class="tab-btn" data-tab="by-product">
                        <i class="fas fa-cube"></i>
                        Par Produit
                    </button>
                </div>
                
                <!-- All Variants Tab -->
                <div id="all-variants" class="tab-content active">
                    <div class="variant-list-container">
                        <!-- Filters -->
                        <div class="filters-section">
                            <div class="filters-row">
                                <div class="filter-group">
                                    <label class="filter-label">Rechercher</label>
                                    <div class="position-relative">
                                        <i class="fas fa-search search-icon-filter"></i>
                                        <input type="text" 
                                            class="filter-input with-icon" 
                                            id="filter-search" 
                                            placeholder="Rechercher SKU, produit, variante...">
                                    </div>
                                </div>
                                <div class="filter-group">
                                    <label class="filter-label">Statut</label>
                                    <select class="filter-select" id="filter-status">
                                        <option value="">Tous</option>
                                        <option value="active">Actif</option>
                                        <option value="inactive">Inactif</option>
                                        <option value="disabled">Désactivé</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="filter-label">Niveau de stock</label>
                                    <select class="filter-select" id="filter-stock">
                                        <option value="">Tous</option>
                                        <option value="out">En rupture</option>
                                        <option value="low">Faible</option>
                                        <option value="medium">Moyen</option>
                                        <option value="high">Élevé</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label class="filter-label">&nbsp;</label>
                                    <button class="btn btn-secondary w-100" id="reset-filters">
                                        <i class="fas fa-redo"></i>
                                        Réinitialiser
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Variant Table -->
                        <div class="table-responsive">
                            <table class="variant-table">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Produit</th>
                                        <th>Nom Variante</th>
                                        <th>Prix</th>
                                        <th>Stock</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($skus)): ?>
                                        <?php foreach ($skus as $sku): ?>
                                            <?php 
                                            // Calculate stock level
                                            $stock_quantity = $sku['stock_quantity'] ?? 0;
                                            $min_threshold = $sku['min_stock_threshold'] ?? 10;
                                            $max_threshold = $sku['max_stock_threshold'] ?? 100;
                                            
                                            $stock_level = 'out';
                                            if ($stock_quantity > 0) {
                                                $percentage = ($stock_quantity / $max_threshold) * 100;
                                                if ($percentage <= 25) $stock_level = 'low';
                                                elseif ($percentage <= 50) $stock_level = 'medium';
                                                elseif ($percentage <= 75) $stock_level = 'high';
                                                else $stock_level = 'full';
                                            }
                                                            ?>
                                            <tr class="variant-row" 
                                                data-variant-id="<?php echo $sku['id']; ?>"
                                                data-product-id="<?php echo $sku['product_id']; ?>"
                                                data-product-name="<?php echo htmlspecialchars($sku['product_name']); ?>"
                                                data-sku="<?php echo htmlspecialchars($sku['sku']); ?>"
                                                data-variant-name="<?php echo htmlspecialchars($sku['variant_name'] ?: 'Variante principale'); ?>"
                                                data-price="<?php echo $sku['price']; ?>"
                                                data-stock-quantity="<?php echo $stock_quantity; ?>"
                                                data-min-threshold="<?php echo $min_threshold; ?>"
                                                data-max-threshold="<?php echo $max_threshold; ?>"
                                                data-stock-level="<?php echo $stock_level; ?>"
                                                data-status="<?php echo $sku['status']; ?>">
                                                <td>
                                                    <strong><?php echo htmlspecialchars($sku['sku']); ?></strong>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($sku['product_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($sku['parent_sku']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="variant-info">
                                                        <?php echo htmlspecialchars($sku['variant_name'] ?: 'Variante principale'); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold">
                                                        <?php 
                                                        echo number_format($sku['price'] ?? 0, 2, ',', ' ') . ' MAD';
                                                        ?>
                                                    </div>
                                                    <?php if ($sku['compare_price'] && $sku['compare_price'] > $sku['price']): ?>
                                                        <div class="text-muted text-decoration-line-through" style="font-size: 0.8rem;">
                                                            <?php echo number_format($sku['compare_price'], 2, ',', ' ') . ' MAD'; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="stock-level">
                                                        <span class="fw-bold me-2"><?php echo $stock_quantity; ?></span>
                                                        <div class="stock-indicator">
                                                            <div class="stock-fill <?php echo $stock_level; ?>"></div>
                                                        </div>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.8rem;">
                                                        Min: <?php echo $min_threshold; ?> | Max: <?php echo $max_threshold; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="variant-badge <?php echo $sku['status']; ?>">
                                                        <?php 
                                                        echo $sku['status'] === 'active' ? 'Actif' : 
                                                            ($sku['status'] === 'inactive' ? 'Inactif' : 'Désactivé');
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="action-btn view" title="Voir détails" onclick="viewVariant(<?php echo $sku['id']; ?>)">
                                                            <i class="fas fa-eye fa-sm"></i>
                                                        </button>
                                                        <button class="action-btn edit" title="Modifier" onclick="editVariant(<?php echo $sku['id']; ?>)">
                                                            <i class="fas fa-edit fa-sm"></i>
                                                        </button>
                                                        <button class="action-btn delete" title="Supprimer" onclick="deleteVariant(<?php echo $sku['id']; ?>, '<?php echo htmlspecialchars($sku['sku']); ?>')">
                                                            <i class="fas fa-trash fa-sm"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7">
                                                <div class="empty-state">
                                                    <i class="fas fa-layer-group text-muted"></i>
                                                    <h3>Aucune variante trouvée</h3>
                                                    <p>Commencez par créer votre première variante pour gérer les différentes versions de vos produits.</p>
                                                    <button class="btn btn-primary" onclick="showAddVariantForm()">
                                                        <i class="fas fa-plus"></i>
                                                        Créer une variante
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="pagination-container">
                            <nav>
                                <ul class="pagination" id="pagination">
                                    <!-- Pagination will be generated by JavaScript -->
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
                
                <!-- By Product Tab -->
                <div id="by-product" class="tab-content">
                    <div class="variant-list-container">
                        <div class="variant-list-header">
                            <h3 class="mb-0"><i class="fas fa-cube me-2"></i>Variantes par Produit</h3>
                            <div class="variant-list-actions">
                                <select class="form-select" id="group-by-product" style="width: 300px;">
                                    <option value="">Sélectionner un produit</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['id']; ?>">
                                            <?php echo htmlspecialchars($product['name']); ?> (<?php echo htmlspecialchars($product['sku']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div id="product-variants-list" class="p-4">
                            <div class="empty-state">
                                <i class="fas fa-cube text-muted"></i>
                                <h3>Sélectionnez un produit</h3>
                                <p>Sélectionnez un produit dans la liste ci-dessus pour voir ses variantes.</p>
                            </div>
                        </div>
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

    <!-- Add/Edit Variant Modal -->
    <div class="modal fade" id="variantModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="
                border: none;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                border-radius: 1px;
                overflow: hidden;
            ">
                <!-- Modal Header -->
                <div class="modal-header" style="
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 1.5rem 2rem;
                    border: none;
                    border-radius: 0;
                ">
                    <div class="d-flex align-items-center w-100">
                        <div class="modal-icon-wrapper me-3" style="
                            background: rgba(255,255,255,0.2);
                            width: 48px;
                            height: 48px;
                            border-radius: 12px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            backdrop-filter: blur(10px);
                        ">
                            <i class="fas fa-layer-group" style="font-size: 1.25rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-1" style="font-weight: 700; font-size: 1.25rem;" id="variantModalTitle">
                                Nouvelle Variante
                            </h5>
                            <p class="mb-0" style="opacity: 0.9; font-size: 0.875rem;">
                                Créer une nouvelle variante de produit
                            </p>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="
                        filter: brightness(0) invert(1);
                        opacity: 0.8;
                    "></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body" style="padding: 2rem; background: #f8fafc;">
                    <form id="variantForm">
                        <input type="hidden" id="variant_id" name="variant_id" value="">
                        
                        <!-- Basic Information -->
                        <div class="form-section mb-4" style="
                            background: white;
                            border-radius: 16px;
                            padding: 1.75rem;
                            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                            border: 1px solid #e2e8f0;
                        ">
                            <h6 class="section-title mb-3" style="
                                color: #2d3748;
                                font-weight: 700;
                                font-size: 1rem;
                                display: flex;
                                align-items: center;
                                gap: 0.5rem;
                            ">
                                <i class="fas fa-info-circle" style="color: #667eea;"></i>
                                Informations de base
                            </h6>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="product_id" class="form-label mb-2" style="color: #4a5568; font-weight: 600;">
                                            Produit *
                                        </label>
                                        <select id="product_id" name="product_id" class="form-select" required style="
                                            border: 2px solid #e2e8f0;
                                            border-radius: 10px;
                                            padding: 0.75rem 1rem;
                                            font-size: 0.95rem;
                                            transition: all 0.2s;
                                        ">
                                            <option value="">Sélectionner un produit</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?php echo $product['id']; ?>" 
                                                    <?php echo ($current_sku && $current_sku['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($product['name']); ?> (<?php echo htmlspecialchars($product['sku']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sku" class="form-label mb-2" style="color: #4a5568; font-weight: 600;">
                                            SKU de la variante *
                                        </label>
                                        <input type="text" id="sku" name="sku" class="form-control" required
                                            value="<?php echo $current_sku ? htmlspecialchars($current_sku['sku']) : ''; ?>"
                                            placeholder="ex: TSHIRT-BLUE-M"
                                            style="
                                                border: 2px solid #e2e8f0;
                                                border-radius: 10px;
                                                padding: 0.75rem 1rem;
                                                font-size: 0.95rem;
                                                transition: all 0.2s;
                                            ">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row g-3 mt-3">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label for="variant_name" class="form-label mb-2" style="color: #4a5568; font-weight: 600;">
                                            Nom de la variante *
                                        </label>
                                        <input type="text" id="variant_name" name="variant_name" class="form-control" required
                                            value="<?php echo $current_sku ? htmlspecialchars($current_sku['variant_name']) : ''; ?>"
                                            placeholder="ex: T-Shirt Bleu Taille M"
                                            style="
                                                border: 2px solid #e2e8f0;
                                                border-radius: 10px;
                                                padding: 0.75rem 1rem;
                                                font-size: 0.95rem;
                                                transition: all 0.2s;
                                            ">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="status" class="form-label mb-2" style="color: #4a5568; font-weight: 600;">
                                            Statut *
                                        </label>
                                        <select id="status" name="status" class="form-select" required style="
                                            border: 2px solid #e2e8f0;
                                            border-radius: 10px;
                                            padding: 0.75rem 1rem;
                                            font-size: 0.95rem;
                                            transition: all 0.2s;
                                        ">
                                            <option value="active" <?php echo ($current_sku && $current_sku['status'] == 'active') ? 'selected' : ''; ?>>Actif</option>
                                            <option value="inactive" <?php echo ($current_sku && $current_sku['status'] == 'inactive') ? 'selected' : ''; ?>>Inactif</option>
                                            <option value="disabled" <?php echo ($current_sku && $current_sku['status'] == 'disabled') ? 'selected' : ''; ?>>Désactivé</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing Section -->
                        <div class="form-section mb-4" style="
                            background: white;
                            border-radius: 16px;
                            padding: 1.75rem;
                            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                            border: 1px solid #f0f0f0;
                        ">
                            <h6 class="section-title mb-3" style="
                                color: #2d3748;
                                font-weight: 700;
                                font-size: 1rem;
                                display: flex;
                                align-items: center;
                                gap: 0.5rem;
                            ">
                                <i class="fas fa-money-bill-wave" style="color: #f59e0b;"></i>
                                Tarification
                            </h6>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="price-card" style="
                                        background: #fff9ed;
                                        border-radius: 1px;
                                        padding: 1.25rem;
                                        border: 2px solid #fbbf24;
                                    ">
                                        <label for="price" class="form-label mb-3" style="color: #92400e; font-weight: 600;">
                                            Prix de vente *
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="
                                                background: #fef3c7;
                                                border: 2px solid #fbbf24;
                                                color: #92400e;
                                                font-weight: 600;
                                            ">MAD</span>
                                            <input type="number" step="0.01" id="price" name="price" class="form-control" required
                                                value="<?php echo $current_sku ? number_format($current_sku['price'], 2, '.', '') : '0.00'; ?>"
                                                style="
                                                    border: 2px solid #fbbf24;
                                                    border-left: none;
                                                    font-weight: 600;
                                                ">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="price-card" style="
                                        background: #f0f4ff;
                                        border-radius: 1px;
                                        padding: 1.25rem;
                                        border: 2px solid #818cf8;
                                    ">
                                        <label for="compare_price" class="form-label mb-3" style="color: #3730a3; font-weight: 600;">
                                            Prix comparé
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="
                                                background: #e0e7ff;
                                                border: 2px solid #818cf8;
                                                color: #3730a3;
                                                font-weight: 600;
                                            ">MAD</span>
                                            <input type="number" step="0.01" id="compare_price" name="compare_price" class="form-control"
                                                value="<?php echo $current_sku ? number_format($current_sku['compare_price'], 2, '.', '') : ''; ?>"
                                                style="
                                                    border: 2px solid #818cf8;
                                                    border-left: none;
                                                ">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="price-card" style="
                                        background: #f0fdf4;
                                        border-radius: 1px;
                                        padding: 1.25rem;
                                        border: 2px solid #34d399;
                                    ">
                                        <label for="cost_price" class="form-label mb-3" style="color: #065f46; font-weight: 600;">
                                            Prix de revient
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text" style="
                                                background: #d1fae5;
                                                border: 2px solid #34d399;
                                                color: #065f46;
                                                font-weight: 600;
                                            ">MAD</span>
                                            <input type="number" step="0.01" id="cost_price" name="cost_price" class="form-control"
                                                value="<?php echo $current_sku ? number_format($current_sku['cost_price'], 2, '.', '') : ''; ?>"
                                                style="
                                                    border: 2px solid #34d399;
                                                    border-left: none;
                                                ">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Management Section -->
                        <div class="form-section" style="
                            background: white;
                            border-radius: 16px;
                            padding: 1.75rem;
                            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                            border: 1px solid #e0f2fe;
                        ">
                            <h6 class="section-title mb-3" style="
                                color: #2d3748;
                                font-weight: 700;
                                font-size: 1rem;
                                display: flex;
                                align-items: center;
                                gap: 0.5rem;
                            ">
                                <i class="fas fa-boxes" style="color: #0ea5e9;"></i>
                                Gestion des stocks
                            </h6>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="stock-card" style="
                                        background: #f0f9ff;
                                        border-radius: 1px;
                                        padding: 1.5rem;
                                        border: 2px solid #0ea5e9;
                                    ">
                                        <label for="stock_quantity" class="form-label mb-3" style="color: #0369a1; font-weight: 600;">
                                            Quantité en stock *
                                        </label>
                                        <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" required
                                            value="<?php echo $current_sku ? $current_sku['stock_quantity'] : '0'; ?>"
                                            min="0"
                                            style="
                                                border: 2px solid #0ea5e9;
                                                border-radius: 8px;
                                                padding: 0.75rem;
                                                font-size: 1.1rem;
                                                font-weight: 600;
                                                text-align: center;
                                            ">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="stock-card" style="
                                        background: #fffbeb;
                                        border-radius: 1px;
                                        padding: 1.5rem;
                                        border: 2px solid #f59e0b;
                                    ">
                                        <label for="min_stock_threshold" class="form-label mb-3" style="color: #92400e; font-weight: 600;">
                                            Seuil minimum
                                        </label>
                                        <input type="number" id="min_stock_threshold" name="min_stock_threshold" class="form-control"
                                            value="<?php echo $current_sku ? $current_sku['min_stock_threshold'] : '10'; ?>"
                                            min="0" placeholder="10"
                                            style="
                                                border: 2px solid #f59e0b;
                                                border-radius: 8px;
                                                padding: 0.75rem;
                                            ">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="stock-card" style="
                                        background: #f0fdf4;
                                        border-radius: 1px;
                                        padding: 1.5rem;
                                        border: 2px solid #10b981;
                                    ">
                                        <label for="max_stock_threshold" class="form-label mb-3" style="color: #065f46; font-weight: 600;">
                                            Seuil maximum
                                        </label>
                                        <input type="number" id="max_stock_threshold" name="max_stock_threshold" class="form-control"
                                            value="<?php echo $current_sku ? $current_sku['max_stock_threshold'] : '100'; ?>"
                                            min="0" placeholder="100"
                                            style="
                                                border: 2px solid #10b981;
                                                border-radius: 8px;
                                                padding: 0.75rem;
                                            ">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer" style="
                    background: #f8fafc;
                    padding: 1.5rem 2rem;
                    border-top: 1px solid #e2e8f0;
                ">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="
                        border: 2px solid #cbd5e0;
                        border-radius: 10px;
                        padding: 0.75rem 1.5rem;
                        font-weight: 600;
                        color: #4a5568;
                        transition: all 0.2s;
                    ">
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" id="saveVariant" style="
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        border: none;
                        border-radius: 10px;
                        padding: 0.75rem 2rem;
                        font-weight: 700;
                        transition: all 0.2s;
                        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
                    ">
                        Enregistrer la Variante
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Simple hover effects */
        #variantModal .form-select:focus,
        #variantModal .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        #variantModal .price-card:hover,
        #variantModal .stock-card:hover {
            transform: translateY(-2px);
            transition: transform 0.2s ease;
        }

        #variantModal .btn-outline-secondary:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }

        #variantModal .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
    </style>

    <!-- View Variant Modal -->
    <div class="modal fade" id="viewVariantModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>
                        Détails de la Variante
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="variantDetails">
                    <!-- Variant details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i>
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('#product_id').select2({
                placeholder: "Sélectionner un produit",
                allowClear: true,
                dropdownParent: $('#variantModal')
            });
            
            $('#group-by-product').select2({
                placeholder: "Sélectionner un produit",
                allowClear: true
            });
            
            // Initialize event listeners
            initEventListeners();
            
            // Check if we should open the modal
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');
            const variantId = urlParams.get('variant_id');
            
            if (action === 'add_variant' || action === 'edit_variant') {
                showAddVariantForm(variantId);
            }
            
            function initEventListeners() {
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
                
                // Filter functionality
                $('#filter-status, #filter-stock').change(applyFilters);
                $('#filter-search').on('input', debounce(applyFilters, 300));
                $('#reset-filters').click(resetFilters);
                $('#global-search').on('input', debounce(handleGlobalSearch, 300));
                
                // Save Variant
                $('#saveVariant').click(saveVariant);
                
                // Group by product
                $('#group-by-product').change(function() {
                    const productId = $(this).val();
                    if (productId) {
                        loadProductVariants(productId);
                    } else {
                        $('#product-variants-list').html(`
                            <div class="empty-state">
                                <i class="fas fa-cube text-muted"></i>
                                <h3>Sélectionnez un produit</h3>
                                <p>Sélectionnez un produit dans la liste ci-dessus pour voir ses variantes.</p>
                            </div>
                        `);
                    }
                });
                
                // Modal events
                $('#variantModal').on('hidden.bs.modal', function() {
                    resetVariantForm();
                });
            }
            
            function applyFilters() {
                const searchFilter = $('#filter-search').val().toLowerCase();
                const statusFilter = $('#filter-status').val();
                const stockFilter = $('#filter-stock').val();
                
                $('.variant-row').each(function() {
                    const $row = $(this);
                    const productName = $row.data('product-name').toLowerCase();
                    const variantName = $row.data('variant-name').toLowerCase();
                    const sku = $row.data('sku').toLowerCase();
                    const status = $row.data('status');
                    const stockLevel = $row.data('stock-level');
                    
                    let show = true;
                    
                    // Status filter
                    if (statusFilter && status !== statusFilter) {
                        show = false;
                    }
                    
                    // Stock level filter
                    if (stockFilter && stockLevel !== stockFilter) {
                        show = false;
                    }
                    
                    // Search filter (searches across multiple fields)
                    if (searchFilter) {
                        if (!productName.includes(searchFilter) && 
                            !variantName.includes(searchFilter) &&
                            !sku.includes(searchFilter)) {
                            show = false;
                        }
                    }
                    
                    if (show) {
                        $row.show();
                    } else {
                        $row.hide();
                    }
                });
                
                // Show/hide empty state if all rows are hidden
                const visibleRows = $('.variant-row:visible').length;
                const totalRows = $('.variant-row').length;
                
                if (visibleRows === 0 && totalRows > 0) {
                    if (!$('#no-results-row').length) {
                        $('.variant-table tbody').append(`
                            <tr id="no-results-row">
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-search text-muted"></i>
                                        <h3>Aucun résultat trouvé</h3>
                                        <p>Aucune variante ne correspond à vos critères de recherche.</p>
                                        <button class="btn btn-secondary" id="reset-filters-inline">
                                            <i class="fas fa-redo"></i>
                                            Réinitialiser les filtres
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `);
                        
                        $('#reset-filters-inline').click(resetFilters);
                    }
                } else {
                    $('#no-results-row').remove();
                }
            }

            function resetFilters() {
                $('#filter-search').val('');
                $('#filter-status').val('');
                $('#filter-stock').val('');
                
                $('.variant-row').show();
                $('#no-results-row').remove();
            }
            
            function handleGlobalSearch() {
                const searchTerm = $('#global-search').val().toLowerCase();
                
                if (searchTerm) {
                    $('.variant-row').each(function() {
                        const $row = $(this);
                        const productName = $row.data('product-name').toLowerCase();
                        const variantName = $row.data('variant-name').toLowerCase();
                        const sku = $row.data('sku').toLowerCase();
                        
                        if (productName.includes(searchTerm) || 
                            variantName.includes(searchTerm) || 
                            sku.includes(searchTerm)) {
                            $row.show();
                            $row.addClass('highlight');
                        } else {
                            $row.hide();
                            $row.removeClass('highlight');
                        }
                    });
                    
                    // Show/hide empty state for global search
                    const visibleRows = $('.variant-row:visible').length;
                    if (visibleRows === 0) {
                        if (!$('#no-search-results-row').length) {
                            $('.variant-table tbody').append(`
                                <tr id="no-search-results-row">
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-search text-muted"></i>
                                            <h3>Aucun résultat trouvé</h3>
                                            <p>Aucune variante ne correspond à votre recherche.</p>
                                        </div>
                                    </td>
                                </tr>
                            `);
                        }
                    } else {
                        $('#no-search-results-row').remove();
                    }
                } else {
                    $('.variant-row').show().removeClass('highlight');
                    $('#no-search-results-row').remove();
                }
            }
            
            function showAddVariantForm(variantId = null) {
                if (variantId) {
                    // Load variant data for editing
                    loadVariantData(variantId);
                    $('#variantModalTitle').text('Modifier Variante');
                } else {
                    $('#variantModalTitle').text('Nouvelle Variante');
                }
                
                const modal = new bootstrap.Modal(document.getElementById('variantModal'));
                modal.show();
            }
            
            function loadVariantData(variantId) {
                showLoading();
                
                $.ajax({
                    url: './controllers/get_variant_data.php',
                    method: 'GET',
                    data: { variant_id: variantId },
                    success: function(response) {
                        if (response.success) {
                            const variant = response.data;
                            
                            // Populate form fields
                            $('#variant_id').val(variant.id);
                            $('#product_id').val(variant.product_id).trigger('change');
                            $('#sku').val(variant.sku);
                            $('#variant_name').val(variant.variant_name);
                            $('#status').val(variant.status);
                            $('#price').val(variant.price);
                            $('#compare_price').val(variant.compare_price);
                            $('#cost_price').val(variant.cost_price);
                            $('#stock_quantity').val(variant.stock_quantity);
                            $('#min_stock_threshold').val(variant.min_stock_threshold);
                            $('#max_stock_threshold').val(variant.max_stock_threshold);
                            
                        } else {
                            showNotification(response.message || 'Erreur lors du chargement de la variante', 'error');
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
            
            function saveVariant() {
                // Validate form
                if (!$('#variantForm')[0].checkValidity()) {
                    $('#variantForm')[0].reportValidity();
                    return;
                }
                
                // Collect form data
                const formData = {
                    variant_id: $('#variant_id').val(),
                    product_id: $('#product_id').val(),
                    sku: $('#sku').val(),
                    variant_name: $('#variant_name').val(),
                    status: $('#status').val(),
                    price: parseFloat($('#price').val()),
                    compare_price: parseFloat($('#compare_price').val() || 0),
                    cost_price: parseFloat($('#cost_price').val() || 0),
                    stock_quantity: parseInt($('#stock_quantity').val()),
                    min_stock_threshold: parseInt($('#min_stock_threshold').val()),
                    max_stock_threshold: parseInt($('#max_stock_threshold').val())
                };
                
                showLoading();
                
                // Send data to server
                $.ajax({
                    url: './controllers/save_variantApi.php',
                    method: 'POST',
                    data: formData,
                    success: function(response) {
                        console.log(response)
                        if (response.success) {
                            showNotification(response.message || 'Variante enregistrée avec succès', 'success');
                            
                            // Close modal and refresh page
                            $('#variantModal').modal('hide');
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showNotification(response.message || 'Erreur lors de l\'enregistrement', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        showNotification('Erreur de connexion au serveur', 'error');
                    },
                    complete: function() {
                        hideLoading();
                    }
                });
            }
            
            function editVariant(variantId) {
                showAddVariantForm(variantId);
            }
            
            function viewVariant(variantId) {
                showLoading();
                
                $.ajax({
                    url: './controllers/get_variant_data.php',
                    method: 'GET',
                    data: { variant_id: variantId, view: 'details' },
                    success: function(response) {
                        if (response.success) {
                            const variant = response.data;
                            
                            // Format pricing
                            const priceFormatted = new Intl.NumberFormat('fr-MA', {
                                style: 'currency',
                                currency: 'MAD',
                                minimumFractionDigits: 2
                            }).format(variant.price);
                            
                            const comparePriceFormatted = variant.compare_price > 0 ? 
                                new Intl.NumberFormat('fr-MA', {
                                    style: 'currency',
                                    currency: 'MAD',
                                    minimumFractionDigits: 2
                                }).format(variant.compare_price) : '-';
                            
                            const costPriceFormatted = variant.cost_price > 0 ? 
                                new Intl.NumberFormat('fr-MA', {
                                    style: 'currency',
                                    currency: 'MAD',
                                    minimumFractionDigits: 2
                                }).format(variant.cost_price) : '-';
                            
                            const margin = variant.cost_price > 0 ? 
                                ((variant.price - variant.cost_price) / variant.price * 100).toFixed(2) : null;
                            
                            // Get stock level class and percentage
                            const stockLevel = getStockLevel(variant.stock_quantity, variant.max_stock_threshold);
                            const stockPercentage = Math.min((variant.stock_quantity / variant.max_stock_threshold) * 100, 100);
                            
                            // Create compact variant details HTML with better styling
                            const variantDetails = `
                                <div class="variant-view">
                                    <!-- Header -->
                                    <div class="variant-header mb-4">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0 text-primary">${variant.sku}</h5>
                                            <span class="variant-badge badge bg-${getStatusColor(variant.status)}">
                                                ${getStatusText(variant.status)}
                                            </span>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            ${variant.product_name} ${variant.variant_name ? `• ${variant.variant_name}` : ''}
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <!-- Left Column -->
                                        <div class="col-md-6">
                                            <!-- Stock Card -->
                                            <div class="info-card mb-3">
                                                <h6 class="info-card-title">
                                                    <i class="bi bi-box-seam me-2"></i>Stock
                                                </h6>
                                                <div class="info-card-content">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <span class="fw-bold fs-5 me-3">${variant.stock_quantity}</span>
                                                        <div class="stock-progress flex-grow-1">
                                                            <div class="progress" style="height: 8px;">
                                                                <div class="progress-bar bg-${getStockColor(stockLevel)}" 
                                                                    style="width: ${stockPercentage}%">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="stock-thresholds d-flex justify-content-between small text-muted">
                                                        <span>Min: ${variant.min_stock_threshold}</span>
                                                        <span>Max: ${variant.max_stock_threshold}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Dates Card -->
                                            <div class="info-card">
                                                <h6 class="info-card-title">
                                                    <i class="bi bi-calendar3 me-2"></i>Dates
                                                </h6>
                                                <div class="info-card-content">
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="date-item">
                                                                <div class="date-label small text-muted">Création</div>
                                                                <div class="date-value">
                                                                    ${formatDate(variant.created_at)}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="date-item">
                                                                <div class="date-label small text-muted">Modification</div>
                                                                <div class="date-value">
                                                                    ${formatDate(variant.updated_at)}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Right Column -->
                                        <div class="col-md-6">
                                            <!-- Pricing Card -->
                                            <div class="info-card mb-3">
                                                <h6 class="info-card-title">
                                                    <i class="bi bi-tag me-2"></i>Prix
                                                </h6>
                                                <div class="info-card-content">
                                                    <div class="price-grid">
                                                        <div class="price-item">
                                                            <div class="price-label">Prix de vente</div>
                                                            <div class="price-value fw-bold text-primary">
                                                                ${priceFormatted}
                                                            </div>
                                                        </div>
                                                        ${variant.compare_price > 0 ? `
                                                            <div class="price-item">
                                                                <div class="price-label">Prix comparé</div>
                                                                <div class="price-value text-decoration-line-through text-muted">
                                                                    ${comparePriceFormatted}
                                                                </div>
                                                            </div>
                                                        ` : ''}
                                                        <div class="price-item">
                                                            <div class="price-label">Prix de revient</div>
                                                            <div class="price-value">${costPriceFormatted}</div>
                                                        </div>
                                                        ${margin !== null ? `
                                                            <div class="price-item">
                                                                <div class="price-label">Marge</div>
                                                                <div class="price-value ${margin >= 30 ? 'text-success' : margin >= 20 ? 'text-warning' : 'text-danger'} fw-bold">
                                                                    ${margin}%
                                                                </div>
                                                            </div>
                                                        ` : ''}
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Attributes (if any) -->
                                            ${variant.attributes && Object.keys(variant.attributes).length > 0 ? `
                                                <div class="info-card">
                                                    <h6 class="info-card-title">
                                                        <i class="bi bi-list-check me-2"></i>Attributs
                                                    </h6>
                                                    <div class="info-card-content">
                                                        <div class="attributes-grid">
                                                            ${Object.entries(variant.attributes).map(([key, value]) => `
                                                                <div class="attribute-item">
                                                                    <span class="attribute-key">${key}:</span>
                                                                    <span class="attribute-value">${value}</span>
                                                                </div>
                                                            `).join('')}
                                                        </div>
                                                    </div>
                                                </div>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            $('#variantDetails').html(variantDetails);
                            const modal = new bootstrap.Modal(document.getElementById('viewVariantModal'));
                            modal.show();
                            
                            // Add modal title dynamically
                            $('#variantModalLabel').html(`<i class="bi bi-eye me-2"></i>Détails de la variante`);
                        } else {
                            showNotification(response.message || 'Erreur lors du chargement', 'error');
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

            // Helper functions for better styling
            function getStockLevel(stockQuantity, maxThreshold) {
                if (stockQuantity === 0) return 'out-of-stock';
                if (stockQuantity <= 10) return 'low-stock';
                if (stockQuantity >= maxThreshold * 0.9) return 'high-stock';
                return 'normal-stock';
            }

            function getStockColor(level) {
                const colors = {
                    'out-of-stock': 'danger',
                    'low-stock': 'warning',
                    'normal-stock': 'success',
                    'high-stock': 'info'
                };
                return colors[level] || 'secondary';
            }

            function getStatusColor(status) {
                const colors = {
                    'active': 'success',
                    'inactive': 'warning',
                    'disabled': 'danger'
                };
                return colors[status] || 'secondary';
            }

            function getStatusText(status) {
                const texts = {
                    'active': 'Actif',
                    'inactive': 'Inactif',
                    'disabled': 'Désactivé'
                };
                return texts[status] || status;
            }

            function formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('fr-FR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
            }

            // Add CSS for better styling
            const variantStyles = `
                .variant-view {
                    font-size: 0.9rem;
                }
                
                .variant-header {
                    padding-bottom: 1rem;
                    border-bottom: 1px solid #dee2e6;
                }
                
                .info-card {
                    background: #f8f9fa;
                    border-radius: 8px;
                    padding: 1rem;
                    margin-bottom: 1rem;
                    border: 1px solid #e9ecef;
                }
                
                .info-card-title {
                    font-size: 0.85rem;
                    color: #6c757d;
                    margin-bottom: 0.75rem;
                    display: flex;
                    align-items: center;
                }
                
                .info-card-content {
                    font-size: 0.9rem;
                }
                
                .price-grid {
                    display: grid;
                    gap: 0.5rem;
                }
                
                .price-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.25rem 0;
                }
                
                .price-label {
                    color: #6c757d;
                }
                
                .price-value {
                    font-weight: 500;
                }
                
                .date-item {
                    padding: 0.25rem 0;
                }
                
                .date-label {
                    margin-bottom: 0.1rem;
                }
                
                .date-value {
                    font-weight: 500;
                    color: #495057;
                }
                
                .attributes-grid {
                    display: grid;
                    gap: 0.4rem;
                }
                
                .attribute-item {
                    display: flex;
                    gap: 0.5rem;
                    padding: 0.3rem 0.5rem;
                    background: white;
                    border-radius: 4px;
                    border-left: 3px solid #0d6efd;
                }
                
                .attribute-key {
                    font-weight: 600;
                    color: #495057;
                    min-width: 80px;
                }
                
                .attribute-value {
                    color: #212529;
                }
                
                .stock-progress .progress {
                    border-radius: 4px;
                    overflow: hidden;
                }
                
                .stock-thresholds {
                    font-size: 0.8rem;
                }
                
                .variant-badge {
                    font-size: 0.75rem;
                    padding: 0.25rem 0.75rem;
                    border-radius: 12px;
                }
                
                /* Responsive adjustments */
                @media (max-width: 768px) {
                    .info-card {
                        padding: 0.75rem;
                    }
                    
                    .price-item, .date-item {
                        font-size: 0.85rem;
                    }
                }
            `;

            // Add styles to document
            if (!document.querySelector('#variant-styles')) {
                const styleSheet = document.createElement("style");
                styleSheet.id = "variant-styles";
                styleSheet.textContent = variantStyles;
                document.head.appendChild(styleSheet);
            }
            
            function deleteVariant(variantId, skuCode) {
                if (confirm(`Êtes-vous sûr de vouloir supprimer la variante "${skuCode}" ? Cette action est irréversible.`)) {
                    showLoading();
                    
                    $.ajax({
                        url: './controllers/delete_variant.php',
                        method: 'POST',
                        data: { variant_id: variantId },
                        success: function(response) {
                            if (response.success) {
                                showNotification(response.message || 'Variante supprimée avec succès', 'success');
                                // Remove the row from table
                                $(`tr[data-variant-id="${variantId}"]`).remove();
                            } else {
                                showNotification(response.message || 'Erreur lors de la suppression', 'error');
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
            
            function loadProductVariants(productId) {
                showLoading();
                
                $.ajax({
                    url: './controllers/get_product_variants.php',
                    method: 'GET',
                    data: { product_id: productId },
                    success: function(response) {
                        if (response.success) {
                            const variants = response.data;
                            const productName = response.product_name || 'Produit';
                            
                            if (variants.length > 0) {
                                let html = `
                                    <div class="card">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0"><i class="fas fa-cube me-2"></i>${productName} - Variantes</h5>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>SKU</th>
                                                            <th>Nom</th>
                                                            <th>Prix</th>
                                                            <th>Stock</th>
                                                            <th>Statut</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                `;
                                
                                variants.forEach(variant => {
                                    const stockLevel = getStockLevel(variant.stock_quantity, variant.max_stock_threshold);
                                    html += `
                                        <tr>
                                            <td><strong>${variant.sku}</strong></td>
                                            <td>${variant.variant_name || 'Variante principale'}</td>
                                            <td>${new Intl.NumberFormat('fr-MA', { style: 'currency', currency: 'MAD' }).format(variant.price)}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-2">${variant.stock_quantity}</span>
                                                    <div class="stock-indicator" style="width: 60px;">
                                                        <div class="stock-fill ${stockLevel}"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="variant-badge ${variant.status}">
                                                    ${variant.status === 'active' ? 'Actif' : variant.status === 'inactive' ? 'Inactif' : 'Désactivé'}
                                                </span>
                                            </td>
                                        </tr>
                                    `;
                                });
                                
                                html += `
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="card-footer text-muted">
                                            <small>Total: ${variants.length} variante(s)</small>
                                        </div>
                                    </div>
                                `;
                                
                                $('#product-variants-list').html(html);
                            } else {
                                $('#product-variants-list').html(`
                                    <div class="empty-state">
                                        <i class="fas fa-layer-group text-muted"></i>
                                        <h3>Aucune variante</h3>
                                        <p>Ce produit n'a pas encore de variantes.</p>
                                        <button class="btn btn-primary" onclick="showAddVariantForm()">
                                            <i class="fas fa-plus"></i>
                                            Ajouter une variante
                                        </button>
                                    </div>
                                `);
                            }
                        } else {
                            showNotification(response.message || 'Erreur lors du chargement des variantes', 'error');
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
            
            function resetVariantForm() {
                $('#variantForm')[0].reset();
                $('#variant_id').val('');
                $('#product_id').val('').trigger('change');
            }
            
            // Utility functions
            function getStockLevel(quantity, maxThreshold) {
                if (quantity === 0) return 'out';
                const percentage = (quantity / maxThreshold) * 100;
                if (percentage <= 25) return 'low';
                if (percentage <= 50) return 'medium';
                if (percentage <= 75) return 'high';
                return 'full';
            }
            
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
            
            // Expose functions to global scope for inline onclick handlers
            window.showAddVariantForm = showAddVariantForm;
            window.editVariant = editVariant;
            window.viewVariant = viewVariant;
            window.deleteVariant = deleteVariant;
            
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