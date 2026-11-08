<?php 
if(file_exists(stream_resolve_include_path("./config/init.php"))) {
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
    <meta name="description" content="Gestion des produits - Plateforme">
    <title>Produits | OBG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
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

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
        }

        .nav-link.active {
            background: var(--primary-color);
            color: white;
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

        /* Sync Button Styles */
        .sync-button {
            background: linear-gradient(135deg, #9c80fd, #5f34d9);
            border: none;
            border-radius: var(--border-radius);
            padding: 0.875rem 1.5rem;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .sync-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .sync-button:active {
            transform: translateY(0);
        }

        .sync-button.syncing {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            cursor: not-allowed;
        }

        .sync-button.syncing::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shine 1.5s infinite;
        }

        @keyframes shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .sync-icon {
            transition: transform 0.3s ease;
        }

        .sync-button.syncing .sync-icon {
            animation: rotate 1s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Filters Section */
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
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input {
            padding: 0.625rem 0.875rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        #reset-filters {
            color: #9c80fd;
            border-color: #9c80fd;
        }

        #reset-filters:hover {
            color: white;
            border-color: #5f34d9;
            background-color: #5f34d9;
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .product-card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
        }

        .product-header {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .product-image-container {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: var(--border-radius-sm);
            overflow: hidden;
            background: var(--background-color);
            border: 2px solid var(--border-color);
            flex-shrink: 0;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-image:hover {
            transform: scale(1.05);
        }

        .image-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .product-info {
            flex: 1;
            min-width: 0;
        }

        .product-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-sku {
            font-size: 0.8rem;
            color: var(--text-secondary);
            background: var(--background-color);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .product-description {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .product-pricing {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .price-item {
            text-align: center;
            padding: 0.75rem;
            background: var(--background-color);
            border-radius: var(--border-radius-sm);
            transition: all 0.2s ease;
        }

        .price-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .price-label {
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .price-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .price-value.highlight {
            color: var(--success-color);
        }

        .price-value.warning {
            color: var(--warning-color);
        }

        .price-value.danger {
            color: var(--danger-color);
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .quantity-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .quantity-badge.low-stock {
            background: var(--warning-color);
            animation: pulse 2s infinite;
        }

        .quantity-badge.out-of-stock {
            background: var(--danger-color);
        }

        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-badge.draft {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-badge.archived {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .product-actions {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }

        .product-card:hover .product-actions {
            opacity: 1;
            transform: translateY(0);
        }

        .product-actions .btn {
            flex: 1;
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            border-radius: var(--border-radius-sm);
            transition: all 0.2s ease;
        }

        .product-actions .btn:hover {
            transform: translateY(-1px);
        }

        /* Pagination Styles */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            padding: 1rem;
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin: 0;
        }

        .page-item {
            list-style: none;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            background: var(--surface-color);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            transition: all 0.2s ease;
            font-weight: 500;
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

        .pagination-info {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-left: 1rem;
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
            text-align: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            width: 100%;
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

        /* Sync Progress */
        .sync-progress {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            display: none;
        }

        .sync-progress.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .progress-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .progress-icon {
            width: 40px;
            height: 40px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .progress-bar {
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), #059669);
            border-radius: 4px;
            transition: width 0.5s ease;
            position: relative;
        }

        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: progressShine 2s infinite;
        }

        @keyframes progressShine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Notifications */
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

        /* Modal Styles */
        .modern-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 20px;
        }
        
        .modern-modal-overlay.show {
            opacity: 1;
        }
        
        .modern-modal {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .modern-modal-overlay.show .modern-modal {
            transform: scale(1) translateY(0);
        }
        
        .modern-modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .modern-modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 100%);
            pointer-events: none;
        }
        
        .modern-modal-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 1;
        }
        
        .modern-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            z-index: 2;
        }
        
        .modern-modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg) scale(1.1);
        }
        
        .modern-modal-body {
            padding: 40px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modern-form-group {
            margin-bottom: 25px;
        }
        
        .modern-form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .modern-form-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            background: white;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        .modern-form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .modern-form-select {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            background: white;
            transition: all 0.3s ease;
            box-sizing: border-box;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 50px;
        }
        
        .modern-form-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .modern-form-textarea {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 16px;
            background: white;
            transition: all 0.3s ease;
            box-sizing: border-box;
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }
        
        .modern-form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .modern-form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .modern-switch-container {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 0;
        }
        
        .modern-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #e5e7eb;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .modern-switch.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .modern-switch::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .modern-switch.active::after {
            transform: translateX(30px);
        }
        
        .modern-switch-label {
            font-weight: 500;
            color: #374151;
            cursor: pointer;
        }
        
        .modern-modal-footer {
            padding: 30px 40px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        
        .modern-btn {
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 120px;
        }
        
        .modern-btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 2px solid #e5e7eb;
        }
        
        .modern-btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .modern-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .modern-btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .modern-btn-primary:hover::before {
            left: 100%;
        }
        
        .modern-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .modern-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }
        
        .modern-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }

        /* View Modal Styles */
        .view-modal-body {
            padding: 30px;
        }

        .product-detail-section {
            margin-bottom: 30px;
        }

        .product-detail-section h4 {
            color: #374151;
            margin-bottom: 15px;
            font-weight: 600;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 8px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .detail-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            color: #374151;
            font-weight: 500;
        }

        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .gallery-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .gallery-image:hover {
            transform: scale(1.05);
        }

        /* Animations */
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

            .products-grid {
                grid-template-columns: 1fr;
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .product-pricing {
                grid-template-columns: 1fr;
            }

            .dashboard-header  {
                flex-direction: column;
                gap: 1em;
            }

            .pagination-container {
                flex-direction: column;
                gap: 1rem;
            }

            .product-header {
                flex-direction: column;
                text-align: center;
            }

            .product-image-container {
                align-self: center;
            }

            .modern-modal {
                margin: 10px;
                max-height: 95vh;
            }
            
            .modern-modal-header,
            .modern-modal-body,
            .modern-modal-footer {
                padding: 20px;
            }
            
            .modern-form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .modern-modal-footer {
                flex-direction: column;
            }
            
            .modern-btn {
                width: 100%;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: var(--surface-color);
            border-radius: var(--border-radius);
            border: 2px dashed var(--border-color);
            animation: fadeInUp 0.6s ease-out;
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: var(--background-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--text-secondary);
            font-size: 2rem;
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Platform Badge */
        .platform-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: var(--primary-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Featured Badge */
        .featured-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--warning-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'items';
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

        <!-- Top Navigation -->
        <nav class="top-navbar">
            <button class="btn btn-link sidebar-toggle d-md-none">
                <i class="fas fa-bars" style="color: #5f34d9;"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Rechercher des produits..." id="global-search">
            </div>
            
            <div class="d-flex align-items-center gap-3">
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
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h2>Gestion des produits</h2>
                <button class="sync-button" id="sync-products-btn">
                    <i class="fas fa-sync-alt sync-icon"></i>
                    <span class="sync-text">Synchroniser</span>
                </button>
            </div>
            
            <!-- Sync Progress -->
            <div class="sync-progress" id="sync-progress">
                <div class="progress-header">
                    <div class="progress-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Synchronisation en cours...</h5>
                        <p class="mb-0 text-muted">Récupération des produits depuis votre boutique</p>
                    </div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                </div>
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Étape: <span id="current-step">Connexion à l'API</span></small>
                    <small class="text-muted"><span id="progress-percent">0</span>%</small>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">Recherche</label>
                        <input type="text" class="filter-input" placeholder="Nom, SKU ou code-barres..." id="product-search" style="width: 250px;">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Statut</label>
                        <select class="filter-input" id="status-filter" style="width: 150px;">
                            <option value="">Tous</option>
                            <option value="active">Actif</option>
                            <option value="draft">Brouillon</option>
                            <option value="archived">Archivé</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Par page</label>
                        <select class="filter-input" id="per-page-filter" style="width: 100px;">
                            <option value="12">12</option>
                            <option value="24">24</option>
                            <option value="48">48</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button class="btn btn-outline-primary" id="reset-filters">
                            <i class="fas fa-undo"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="products-grid" id="products-grid">
                <!-- Products will be loaded here -->
            </div>
            
            <!-- Pagination -->
            <div class="pagination-container" id="pagination-container" style="display: none;">
                <nav>
                    <ul class="pagination" id="pagination">
                        <!-- Pagination links will be generated here -->
                    </ul>
                </nav>
                <div class="pagination-info" id="pagination-info">
                    <!-- Pagination info will be displayed here -->
                </div>
            </div>
            
            <!-- Empty State -->
            <div class="empty-state" id="empty-state" style="display: none;">
                <div class="empty-state-icon">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3>Aucun produit trouvé</h3>
                <p>Commencez par synchroniser vos produits depuis votre boutique en ligne.</p>
                <button class="btn btn-primary" onclick="$('#sync-products-btn').click()">
                    <i class="fas fa-sync-alt"></i>
                    Synchroniser maintenant
                </button>
            </div>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Chargement en cours...</p>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            let currentPage = 1;
            let perPage = 12;
            let searchTerm = '';
            let statusFilter = '';
            let totalProducts = 0;
            let totalPages = 0;
            let products = [];
            
            // Initialize
            loadProducts();
            initializeEventListeners();
            
            function initializeEventListeners() {
                $('#sync-products-btn').click(handleSync);
                $('#product-search').on('input', debounce(handleSearch, 300));
                $('#status-filter').change(handleFilterChange);
                $('#per-page-filter').change(handlePerPageChange);
                $('#reset-filters').click(resetFilters);
                $('#global-search').on('input', debounce(handleGlobalSearch, 300));
            }

            /**
             * Main function to sync products with real API call
             */
            function handleSync() {
                // Prevent multiple sync requests
                if ($('#sync-products-btn').hasClass('syncing')) {
                    return;
                }

                // Show loading state
                $('#sync-products-btn').addClass('syncing');
                $('#sync-products-btn').find('.sync-text').text('Synchronisation...');
                
                // Show progress modal
                $('#sync-progress').addClass('show');
                $('#progress-fill').css('width', '0%');
                $('#progress-percent').text('0');
                $('#current-step').text('Initialisation...');

                // Start progress simulation
                simulateProductSyncProgress();

                // Make actual API call
                $.ajax({
                    url: './controllers/sync_products.php',
                    method: 'POST',
                    data: {
                        action: 'sync_products'
                    },
                    timeout: 120000, // 2 minutes timeout
                    success: function(response) {
                        console.log('Raw response (success):', response);

                        // Try parsing in case PHP returns JSON
                        try {
                            let jsonResponse = (typeof response === 'string') ? JSON.parse(response) : response;
                            if (jsonResponse.success) {
                                completeProductSync(jsonResponse);
                            } else {
                                handleProductSyncError(jsonResponse.message || 'Erreur de synchronisation');
                            }
                        } catch (e) {
                            console.error('Invalid JSON response:', response);
                            handleProductSyncError('Réponse invalide du serveur');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Sync error:', error);
                        console.log('Raw response (error):', xhr.responseText);

                        let errorMessage = 'Erreur de connexion';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (status === 'timeout') {
                            errorMessage = 'Timeout - La synchronisation prend trop de temps';
                        }
                        
                        handleProductSyncError(errorMessage);
                    }
                });
            }

            /**
             * Simulate sync progress with realistic steps
             */
            function simulateProductSyncProgress() {
                const steps = [
                    { text: 'Connexion aux APIs des magasins', progress: 15 },
                    { text: 'Récupération des produits', progress: 35 },
                    { text: 'Validation des données produits', progress: 55 },
                    { text: 'Mise à jour de la base de données', progress: 75 },
                    { text: 'Synchronisation des variations', progress: 90 },
                    { text: 'Finalisation', progress: 100 }
                ];

                let currentStep = 0;
                const interval = setInterval(() => {
                    if (currentStep >= steps.length) {
                        clearInterval(interval);
                        return;
                    }

                    const step = steps[currentStep];
                    $('#current-step').text(step.text);
                    $('#progress-percent').text(step.progress);
                    $('#progress-fill').css('width', step.progress + '%');

                    currentStep++;
                }, 1200);

                // Store interval ID to clear it if needed
                window.productSyncInterval = interval;
            }

            /**
            * Complete product sync successfully
            */
            function completeProductSync(response) {
                // Clear any running interval
                if (window.productSyncInterval) {
                    clearInterval(window.productSyncInterval);
                }

                // Ensure progress shows 100%
                $('#progress-fill').css('width', '100%');
                $('#progress-percent').text('100');
                $('#current-step').text('Synchronisation terminée');

                setTimeout(() => {
                    // Hide progress modal
                    $('#sync-progress').removeClass('show');
                    
                    // Reset button state
                    $('#sync-products-btn').removeClass('syncing');
                    $('#sync-products-btn').find('.sync-text').text('Synchroniser');

                    // Show success notification with details
                    const totalProducts = (response.total_synced || 0) + (response.total_updated || 0);
                    let message = `Synchronisation terminée! ${totalProducts} produits traités.`;
                    
                    if (response.total_synced > 0) {
                        message += ` ${response.total_synced} nouveaux,`;
                    }
                    if (response.total_updated > 0) {
                        message += ` ${response.total_updated} mis à jour.`;
                    }

                    showNotification(message, 'success');

                    // Reload products list
                    loadProducts();

                }, 500);
            }

            /**
            * Handle product sync errors
            */
            function handleProductSyncError(errorMessage) {
                // Clear any running interval
                if (window.productSyncInterval) {
                    clearInterval(window.productSyncInterval);
                }

                setTimeout(() => {
                    // Hide progress modal
                    $('#sync-progress').removeClass('show');
                    
                    // Reset button state
                    $('#sync-products-btn').removeClass('syncing');
                    $('#sync-products-btn').find('.sync-text').text('Synchroniser');

                    // Show error notification
                    showNotification(`Erreur de synchronisation: ${errorMessage}`, 'error');

                }, 500);
            }
                
            function handleSearch() {
                searchTerm = $('#product-search').val();
                currentPage = 1;
                loadProducts();
            }

            function handleGlobalSearch() {
                searchTerm = $('#global-search').val();
                currentPage = 1;
                loadProducts();
            }
            
            function handleFilterChange() {
                statusFilter = $('#status-filter').val();
                currentPage = 1;
                loadProducts();
            }
            
            function handlePerPageChange() {
                perPage = parseInt($('#per-page-filter').val());
                currentPage = 1;
                loadProducts();
            }
            
            function resetFilters() {
                $('#product-search').val('');
                $('#global-search').val('');
                $('#status-filter').val('');
                $('#per-page-filter').val('12');
                searchTerm = '';
                statusFilter = '';
                perPage = 12;
                currentPage = 1;
                loadProducts();
            }
            
            async function loadProducts() {
                try {
                    // Show loading state
                    showLoadingState();
                    
                    // Build query parameters
                    const urlParams = new URLSearchParams({
                        page: currentPage,
                        per_page: perPage
                    });
                    
                    // Add filters if they exist
                    if (statusFilter) {
                        urlParams.append('status', statusFilter);
                    }
                    
                    if (searchTerm) {
                        urlParams.append('search', searchTerm);
                    }
                    
                    // Make request to PHP script
                    const response = await fetch(`./controllers/load_productsApi.php?${urlParams.toString()}`, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update global variables
                        products = data.products || [];
                        totalProducts = data.pagination.total_products || 0;
                        totalPages = data.pagination.total_pages || 1;
                        
                        // Render products
                        renderProducts(products);
                        
                        // Render pagination
                        renderPagination();
                        
                    } else {
                        console.error('Failed to load products:', data.message);
                        
                        // Show error state
                        showErrorState(data.message);
                        
                        // Fallback to empty products array
                        renderProducts([]);
                    }
                    
                } catch (error) {
                    console.error('Error loading products:', error);
                    
                    // Show error state
                    showErrorState('Erreur de chargement des produits. Veuillez réessayer.');
                    
                    // Fallback to empty products array
                    renderProducts([]);
                    
                } finally {
                    // Hide loading state
                    hideLoadingState();
                }
            }

            // Helper functions for UI states
            function showLoadingState() {
                const $grid = $('#products-grid');
                const $emptyState = $('#empty-state');
                const $pagination = $('#pagination-container');
                
                $emptyState.hide();
                $pagination.hide();
                $grid.html(`
                    <div style="grid-column: 1 / -1; display: flex; justify-content: center; align-items: center; min-height: 250px;">
                        <div class="text-center">
                            <div class="spinner"></div>
                            <p class="mt-3">Chargement des produits...</p>
                        </div>
                    </div>
                `);

            }

            function hideLoadingState() {
                // Loading state is replaced by actual content
            }

            function showErrorState(message) {
                const $grid = $('#products-grid');
                const $emptyState = $('#empty-state');
                const $pagination = $('#pagination-container');
                
                $grid.hide();
                $pagination.hide();
                $emptyState.html(`
                    <div class="error-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3>Erreur de chargement</h3>
                        <p>${message}</p>
                        <button onclick="loadProducts()" class="btn btn-primary">Réessayer</button>
                    </div>
                `).show();
            }

            // Updated renderProducts function
            function renderProducts(products) {
              
                const $grid = $('#products-grid');
                const $emptyState = $('#empty-state');
                const $pagination = $('#pagination-container');
                
                if (products.length === 0) {
                    $grid.hide();
                    $pagination.hide();
                    $emptyState.show();
                    return;
                }
                
                $emptyState.hide();
                $pagination.show();
                $grid.show().empty();
                
                products.forEach(product => {
                    const productCard = createProductCard(product);
                    $grid.append(productCard);
                });
                
                // Add entrance animation
                $grid.find('.product-card').each((index, card) => {
                    $(card).css({
                        opacity: 0,
                        transform: 'translateY(20px)'
                    }).delay(index * 100).animate({
                        opacity: 1
                    }, 300).css('transform', 'translateY(0)');
                });
            }

            // Function to render pagination
            function renderPagination() {
                const $pagination = $('#pagination');
                const $paginationInfo = $('#pagination-info');
                
                // Clear existing pagination
                $pagination.empty();
                
                // Calculate pagination info
                const startItem = (currentPage - 1) * perPage + 1;
                const endItem = Math.min(currentPage * perPage, totalProducts);
                
                // Update pagination info
                $paginationInfo.text(`Affichage de ${startItem} à ${endItem} sur ${totalProducts} produits`);
                
                // Don't show pagination if only one page
                if (totalPages <= 1) {
                    $('#pagination-container').hide();
                    return;
                }
                
                // Previous button
                const prevDisabled = currentPage === 1 ? 'disabled' : '';
                $pagination.append(`
                    <li class="page-item ${prevDisabled}">
                        <a class="page-link" href="#" data-page="${currentPage - 1}">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                `);
                
                // Page numbers
                const maxVisiblePages = 5;
                let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
                let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
                
                // Adjust start page if we're near the end
                if (endPage - startPage + 1 < maxVisiblePages) {
                    startPage = Math.max(1, endPage - maxVisiblePages + 1);
                }
                
                // First page and ellipsis
                if (startPage > 1) {
                    $pagination.append(`
                        <li class="page-item">
                            <a class="page-link" href="#" data-page="1">1</a>
                        </li>
                    `);
                    if (startPage > 2) {
                        $pagination.append(`
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        `);
                    }
                }
                
                // Page numbers
                for (let i = startPage; i <= endPage; i++) {
                    const active = i === currentPage ? 'active' : '';
                    $pagination.append(`
                        <li class="page-item ${active}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>
                    `);
                }
                
                // Last page and ellipsis
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        $pagination.append(`
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        `);
                    }
                    $pagination.append(`
                        <li class="page-item">
                            <a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a>
                        </li>
                    `);
                }
                
                // Next button
                const nextDisabled = currentPage === totalPages ? 'disabled' : '';
                $pagination.append(`
                    <li class="page-item ${nextDisabled}">
                        <a class="page-link" href="#" data-page="${currentPage + 1}">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                `);
                
                // Add click handlers for pagination links
                $pagination.off('click').on('click', '.page-link', function(e) {
                    e.preventDefault();
                    if ($(this).parent().hasClass('disabled')) return;
                    
                    const page = $(this).data('page');
                    if (page && page !== currentPage) {
                        currentPage = page;
                        loadProducts();
                        
                        // Scroll to top of products grid
                        $('html, body').animate({
                            scrollTop: $('.products-grid').offset().top - 100
                        }, 300);
                    }
                });
            }
            
            function createProductCard(product) {
                // Parse images JSON
                let productImages = [];
                try {
                    if (product.images && typeof product.images === 'string') {
                        productImages = JSON.parse(product.images);
                    } else if (Array.isArray(product.images)) {
                        productImages = product.images;
                    }
                } catch (e) {
                    console.error('Error parsing product images:', e);
                }
                
                // Get first image or placeholder
                const firstImage = productImages.length > 0 ? productImages[0].src : 'https://via.placeholder.com/100x100/f0f0f0/999?text=IMG';
                const imageCount = productImages.length;
                
                // Calculate stock status
                const quantity = product.stock_quantity || product.quantity || 0;
                let quantityClass = 'bg-primary';
                if (quantity === 0) {
                    quantityClass = 'out-of-stock';
                } else if (quantity < 10) {
                    quantityClass = 'low-stock';
                }
                
                // Format prices
                const priceFormatted = formatPrice(product.price);
                const comparePriceFormatted = formatPrice(product.compare_price);
                const costPriceFormatted = formatPrice(product.cost_price);
                const margin = calculateMargin(product.price, product.cost_price);
                
                // Get price value classes
                const priceClass = product.price > product.compare_price ? 'highlight' : '';
                const costClass = product.cost_price > 0 ? 'warning' : '';
                const marginClass = margin > 30 ? 'highlight' : margin > 0 ? 'warning' : 'danger';
                
                return $(`
                    <div class="product-card" data-product-id="${product.id}">
                        <div class="product-header">
                            <div class="product-image-container">
                                <img src="${firstImage}" alt="${product.name}" class="product-image">
                                ${product.platform ? `<span class="platform-badge">${product.platform}</span>` : ''}
                                ${product.featured ? '<span class="featured-badge"><i class="fas fa-star"></i></span>' : ''}
                                ${imageCount > 1 ? `<span class="image-badge">+${imageCount - 1}</span>` : ''}
                            </div>
                            <div class="product-info">
                                <h3 class="product-name">${escapeHtml(product.name)}</h3>
                                <span class="product-sku">${escapeHtml(product.sku || 'N/A')}</span>
                                ${product.description ? `<div class="product-description">${escapeHtml(product.description.replace(/<[^>]*>/g, ''))}</div>` : ''}
                            </div>
                        </div>
                        
                        <div class="product-pricing">
                            <div class="price-item">
                                <div class="price-label">Prix de vente</div>
                                <div class="price-value ${priceClass}">${priceFormatted}</div>
                            </div>
                            <div class="price-item">
                                <div class="price-label">Prix comparé</div>
                                <div class="price-value">${comparePriceFormatted}</div>
                            </div>
                            <div class="price-item">
                                <div class="price-label">Coût</div>
                                <div class="price-value ${costClass}">${costPriceFormatted}</div>
                            </div>
                            <div class="price-item">
                                <div class="price-label">Marge</div>
                                <div class="price-value ${marginClass}">${margin}%</div>
                            </div>
                        </div>
                        
                        <div class="product-meta">
                            <span class="quantity-badge ${quantityClass}">
                                <i class="fas fa-${quantity === 0 ? 'times' : 'box'}"></i>
                                ${quantity} en stock
                            </span>
                            <span class="status-badge ${product.status}">
                                ${getStatusLabel(product.status)}
                            </span>
                        </div>
                        
                        <div class="product-actions">
                            <button class="btn btn-sm btn-outline-primary edit-product" data-product-id="${product.id}">
                                <i class="fas fa-edit"></i> Modifier
                            </button>
                            <button class="btn btn-sm btn-outline-info view-product" data-product-id="${product.id}">
                                <i class="fas fa-eye"></i> Voir
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item text-danger delete-product" href="#" data-product-id="${product.id}">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                `);
            }
            
            function formatPrice(price) {
                if (!price || price === 0 || price === "0.00") return '-';
                return new Intl.NumberFormat('fr-MA', {
                    style: 'currency',
                    currency: 'MAD',
                    minimumFractionDigits: 0
                }).format(parseFloat(price));
            }
            
            function calculateMargin(sellPrice, costPrice) {
                if (!sellPrice || !costPrice || sellPrice === 0 || costPrice === 0) return '-';
                const sell = parseFloat(sellPrice);
                const cost = parseFloat(costPrice);
                if (sell === 0 || cost === 0) return '-';
                const margin = ((sell - cost) / sell) * 100;
                return Math.round(margin);
            }
            
            function getStatusLabel(status) {
                const labels = {
                    'active': 'Actif',
                    'draft': 'Brouillon',
                    'archived': 'Archivé'
                };
                return labels[status] || status;
            }
            
            function showNotification(message, type = 'info') {
                // Remove existing notifications
                $('.notification').remove();
                
                const notification = $(`
                    <div class="notification ${type}">
                        <div class="d-flex align-items-center">
                            <div class="me-2">
                                ${getNotificationIcon(type)}
                            </div>
                            <div>
                                <strong>${getNotificationTitle(type)}</strong>
                                <div>${message}</div>
                            </div>
                            <button class="btn-close ms-auto" onclick="$(this).closest('.notification').remove()"></button>
                        </div>
                    </div>
                `);
                
                $('body').append(notification);
                
                setTimeout(() => {
                    notification.addClass('show');
                }, 100);
                
                setTimeout(() => {
                    notification.removeClass('show');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                }, 5000);
            }
            
            function getNotificationIcon(type) {
                const icons = {
                    'success': '<i class="fas fa-check-circle text-success"></i>',
                    'error': '<i class="fas fa-exclamation-circle text-danger"></i>',
                    'warning': '<i class="fas fa-exclamation-triangle text-warning"></i>',
                    'info': '<i class="fas fa-info-circle text-primary"></i>'
                };
                return icons[type] || icons.info;
            }
            
            function getNotificationTitle(type) {
                const titles = {
                    'success': 'Succès',
                    'error': 'Erreur',
                    'warning': 'Attention',
                    'info': 'Information'
                };
                return titles[type] || titles.info;
            }
            
            function escapeHtml(unsafe) {
                if (!unsafe) return '';
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
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
            
            // Product action handlers
            $(document).on('click', '.edit-product', function() {
                const productId = $(this).data('product-id');
                const product = products.find(p => p.id == productId);
                
                if (!product) {
                    showNotification('Produit non trouvé', 'error');
                    return;
                }

                // Create beautiful modern modal with CSS
                const modalStyles = `
                    <style id="modern-modal-styles">
                        .modern-modal-overlay {
                            position: fixed;
                            top: 0;
                            left: 0;
                            right: 0;
                            bottom: 0;
                            background: rgba(0, 0, 0, 0.6);
                            backdrop-filter: blur(8px);
                            z-index: 10000;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            opacity: 0;
                            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                            padding: 20px;
                        }
                        
                        .modern-modal-overlay.show {
                            opacity: 1;
                        }
                        
                        .modern-modal {
                            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
                            border-radius: 20px;
                            max-width: 800px;
                            width: 100%;
                            max-height: 90vh;
                            overflow: hidden;
                            transform: scale(0.9) translateY(20px);
                            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                            position: relative;
                        }
                        
                        .modern-modal-overlay.show .modern-modal {
                            transform: scale(1) translateY(0);
                        }
                    </style>
                `;

                // Create the modal HTML
                const $editModal = $(`
                    ${modalStyles}
                    <div class="modern-modal-overlay" id="editProductModal">
                        <div class="modern-modal">
                            <div class="modern-modal-header">
                                <h2 class="modern-modal-title">Éditer le produit: ${escapeHtml(product.name)}</h2>
                                <button type="button" class="modern-modal-close" id="closeModal">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="modern-modal-body">
                                <form id="editProductForm">
                                    <input type="hidden" name="id" value="${product.id}">
                                    
                                    <div class="modern-form-row">
                                        <div class="modern-form-group">
                                            <label for="editName" class="modern-form-label">Nom du produit</label>
                                            <input type="text" class="modern-form-input" id="editName" name="name" value="${escapeHtml(product.name)}" required>
                                        </div>
                                        <div class="modern-form-group">
                                            <label for="editSku" class="modern-form-label">SKU</label>
                                            <input type="text" class="modern-form-input" id="editSku" name="sku" value="${escapeHtml(product.sku || '')}">
                                        </div>
                                    </div>
                                    
                                    <div class="modern-form-row">
                                        <div class="modern-form-group">
                                            <label for="editPrice" class="modern-form-label">Prix</label>
                                            <input type="number" step="0.01" class="modern-form-input" id="editPrice" name="price" value="${product.price || 0}" required>
                                        </div>
                                        <div class="modern-form-group">
                                            <label for="editComparePrice" class="modern-form-label">Prix comparé</label>
                                            <input type="number" step="0.01" class="modern-form-input" id="editComparePrice" name="compare_price" value="${product.compare_price || 0}">
                                        </div>
                                        <div class="modern-form-group">
                                            <label for="editCostPrice" class="modern-form-label">Prix de revient</label>
                                            <input type="number" step="0.01" class="modern-form-input" id="editCostPrice" name="cost_price" value="${product.cost_price || 0}">
                                        </div>
                                    </div>
                                    
                                    <div class="modern-form-row">
                                        <div class="modern-form-group">
                                            <label for="editQuantity" class="modern-form-label">Quantité</label>
                                            <input type="number" class="modern-form-input" id="editQuantity" name="quantity" value="${product.stock_quantity || product.quantity || 0}">
                                        </div>
                                        <div class="modern-form-group">
                                            <label for="editStatus" class="modern-form-label">Statut</label>
                                            <select class="modern-form-select" id="editStatus" name="status">
                                                <option value="active" ${product.status === 'active' ? 'selected' : ''}>Actif</option>
                                                <option value="draft" ${product.status === 'draft' ? 'selected' : ''}>Brouillon</option>
                                                <option value="archived" ${product.status === 'archived' ? 'selected' : ''}>Archivé</option>
                                            </select>
                                        </div>
                                        <div class="modern-form-group">
                                            <label class="modern-form-label">En vedette</label>
                                            <div class="modern-switch-container">
                                                <div class="modern-switch ${product.featured ? 'active' : ''}" id="editFeaturedSwitch">
                                                    <input type="checkbox" id="editFeatured" name="featured" ${product.featured ? 'checked' : ''} style="display: none;">
                                                </div>
                                                <label class="modern-switch-label" for="editFeatured">Produit en vedette</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="modern-form-group">
                                        <label for="editDescription" class="modern-form-label">Description</label>
                                        <textarea class="modern-form-textarea" id="editDescription" name="description" placeholder="Entrez la description du produit...">${escapeHtml(product.description || '')}</textarea>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="modern-modal-footer">
                                <button type="button" class="modern-btn modern-btn-secondary" id="cancelBtn">Annuler</button>
                                <button type="button" class="modern-btn modern-btn-primary" id="saveProductChanges">Enregistrer</button>
                            </div>
                        </div>
                    </div>
                `);
                
                // Append modal to body
                $('body').append($editModal);
                
                // Show modal with animation
                setTimeout(() => {
                    $editModal.addClass('show');
                }, 10);
                
                // Handle switch toggle
                $editModal.on('click', '#editFeaturedSwitch', function() {
                    const $switch = $(this);
                    const $checkbox = $('#editFeatured');
                    const isActive = $switch.hasClass('active');
                    
                    $switch.toggleClass('active');
                    $checkbox.prop('checked', !isActive);
                });
                
                // Handle close button
                $editModal.on('click', '#closeModal, #cancelBtn', function() {
                    closeModal();
                });
                
                // Handle overlay click
                $editModal.on('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
                
                // Close modal function
                function closeModal() {
                    $editModal.removeClass('show');
                    setTimeout(() => {
                        $editModal.remove();
                        $('#modern-modal-styles').remove();
                    }, 300);
                }
                
                // Handle save button click
                $editModal.on('click', '#saveProductChanges', function() {
                    const $form = $('#editProductForm');
                    const $saveBtn = $(this);
                    const formData = $form.serializeArray();
                    const productData = {};
                    
                    // Convert form data to object
                    formData.forEach(item => {
                        productData[item.name] = item.value;
                    });
                    
                    // Add checkbox value
                    productData.featured = $('#editFeatured').is(':checked');
                    
                    // Show loading state
                    $saveBtn.prop('disabled', true).html('<div class="modern-spinner"></div> Enregistrement...');
                    
                    // Send update request
                    $.ajax({
                        url: './controllers/update_product.php',
                        method: 'POST',
                        dataType: 'json',
                        data: productData,
                        success: function(response) {
                            if (response.success) {
                                showNotification('Produit mis à jour avec succès', 'success');
                                
                                // Update the product in our local array
                                const index = products.findIndex(p => p.id == productId);
                                if (index !== -1) {
                                    products[index] = {...products[index], ...productData};
                                    renderProducts(products);
                                }
                                
                                closeModal();
                            } else {
                                showNotification(response.message || 'Erreur lors de la mise à jour', 'error');
                                $saveBtn.prop('disabled', false).html('Enregistrer');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error updating product:', error);
                            showNotification('Erreur de connexion', 'error');
                            $saveBtn.prop('disabled', false).html('Enregistrer');
                        }
                    });
                });
                
                // Handle escape key
                $(document).on('keydown.modal', function(e) {
                    if (e.key === 'Escape') {
                        closeModal();
                        $(document).off('keydown.modal');
                    }
                });
            });
            
            $(document).on('click', '.view-product', function() {
                const productId = $(this).data('product-id');
                const product = products.find(p => p.id == productId);
                
                if (!product) {
                    showNotification('Produit non trouvé', 'error');
                    return;
                }

                // Parse images JSON
                let productImages = [];
                try {
                    if (product.images && typeof product.images === 'string') {
                        productImages = JSON.parse(product.images);
                    } else if (Array.isArray(product.images)) {
                        productImages = product.images;
                    }
                } catch (e) {
                    console.error('Error parsing product images:', e);
                }

                // Create view modal
                const $viewModal = $(`
                    <div class="modern-modal-overlay" id="viewProductModal">
                        <div class="modern-modal">
                            <div class="modern-modal-header">
                                <h2 class="modern-modal-title">Détails du produit: ${escapeHtml(product.name)}</h2>
                                <button type="button" class="modern-modal-close" id="closeViewModal">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </div>
                            
                            <div class="view-modal-body">
                                <div class="product-detail-section">
                                    <h4>Informations générales</h4>
                                    <div class="detail-grid">
                                        <div class="detail-item">
                                            <div class="detail-label">Nom</div>
                                            <div class="detail-value">${escapeHtml(product.name)}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">SKU</div>
                                            <div class="detail-value">${escapeHtml(product.sku || 'N/A')}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Statut</div>
                                            <div class="detail-value">${getStatusLabel(product.status)}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Plateforme</div>
                                            <div class="detail-value">${product.platform || 'N/A'}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">En vedette</div>
                                            <div class="detail-value">${product.featured ? 'Oui' : 'Non'}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Quantité</div>
                                            <div class="detail-value">${product.stock_quantity || product.quantity || 0}</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="product-detail-section">
                                    <h4>Prix et coûts</h4>
                                    <div class="detail-grid">
                                        <div class="detail-item">
                                            <div class="detail-label">Prix de vente</div>
                                            <div class="detail-value">${formatPrice(product.price)}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Prix comparé</div>
                                            <div class="detail-value">${formatPrice(product.compare_price)}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Coût</div>
                                            <div class="detail-value">${formatPrice(product.cost_price)}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Marge</div>
                                            <div class="detail-value">${calculateMargin(product.price, product.cost_price)}%</div>
                                        </div>
                                    </div>
                                </div>
                                
                                ${product.description ? `
                                <div class="product-detail-section">
                                    <h4>Description</h4>
                                    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea;">
                                        <div style="color: #374151; line-height: 1.6;">${escapeHtml(product.description.replace(/<[^>]*>/g, ''))}</div>
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${productImages.length > 0 ? `
                                <div class="product-detail-section">
                                    <h4>Images (${productImages.length})</h4>
                                    <div class="image-gallery">
                                        ${productImages.map(img => `
                                            <img src="${img.src}" alt="${img.alt || 'Product Image'}" class="gallery-image" onclick="window.open('${img.src}', '_blank')">
                                        `).join('')}
                                    </div>
                                </div>
                                ` : ''}
                                
                                <div class="product-detail-section">
                                    <h4>Informations techniques</h4>
                                    <div class="detail-grid">
                                        <div class="detail-item">
                                            <div class="detail-label">ID Produit</div>
                                            <div class="detail-value">${product.id}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">ID Externe</div>
                                            <div class="detail-value">${product.external_product_id || 'N/A'}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Type</div>
                                            <div class="detail-value">${product.type || 'Simple'}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Date de création</div>
                                            <div class="detail-value">${product.created_at || 'N/A'}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Dernière mise à jour</div>
                                            <div class="detail-value">${product.updated_at || 'N/A'}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="modern-modal-footer">
                                <button type="button" class="modern-btn modern-btn-secondary" id="closeViewBtn">Fermer</button>
                                <button type="button" class="modern-btn modern-btn-primary" onclick="$('.edit-product[data-product-id=&quot;${product.id}&quot;]').click()">
                                    <i class="fas fa-edit"></i> Modifier
                                </button>
                            </div>
                        </div>
                    </div>
                `);
                
                // Append modal to body
                $('body').append($viewModal);
                
                // Show modal with animation
                setTimeout(() => {
                    $viewModal.addClass('show');
                }, 10);
                
                // Handle close button
                $viewModal.on('click', '#closeViewModal, #closeViewBtn', function() {
                    closeViewModal();
                });
                
                // Handle overlay click
                $viewModal.on('click', function(e) {
                    if (e.target === this) {
                        closeViewModal();
                    }
                });
                
                // Close modal function
                function closeViewModal() {
                    $viewModal.removeClass('show');
                    setTimeout(() => {
                        $viewModal.remove();
                    }, 300);
                }
                
                // Handle escape key
                $(document).on('keydown.viewModal', function(e) {
                    if (e.key === 'Escape') {
                        closeViewModal();
                        $(document).off('keydown.viewModal');
                    }
                });
            });
            
            $(document).on('click', '.delete-product', function(e) {
                e.preventDefault();
                const productId = $(this).data('product-id');
                const $productCard = $(this).closest('.product-card');
                const productName = $productCard.find('.product-name').text();
                
                if (confirm(`Êtes-vous sûr de vouloir supprimer le produit "${productName}" (ID: ${productId}) ? Cette action est irréversible.`)) {
                    // Show loading state
                    $productCard.addClass('deleting');
                    $(this).prop('disabled', true);
                    
                    $.ajax({
                        url: './controllers/delete_product.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            product_id: productId
                        },
                        success: function(response) {
                            if (response.success) {
                                // Show success notification
                                showNotification(`Produit "${productName}" supprimé avec succès`, 'success');
                                
                                // Reload products to update pagination
                                loadProducts();
                            } else {
                                // Show error message
                                showNotification(response.message || 'Erreur lors de la suppression du produit', 'error');
                                $productCard.removeClass('deleting');
                                $productCard.find('.delete-product').prop('disabled', false);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error deleting product:', error);
                            showNotification('Erreur de connexion lors de la suppression', 'error');
                            $productCard.removeClass('deleting');
                            $productCard.find('.delete-product').prop('disabled', false);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>