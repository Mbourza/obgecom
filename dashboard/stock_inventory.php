<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
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

// Récupérer les statistiques d'inventaire - UPDATED QUERY
$inventoryStats = $db->getThisQuery("
    SELECT 
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT pv.id) as total_skus,
        SUM(
            CASE 
                WHEN pv.id IS NOT NULL THEN COALESCE(pv.stock_quantity, 0)
                ELSE COALESCE(p.stock_quantity, 0)
            END
        ) as total_quantity,
        SUM(
            CASE 
                WHEN pv.id IS NOT NULL AND pv.stock_quantity <= 10 THEN 1
                WHEN pv.id IS NULL AND p.stock_quantity <= 10 THEN 1
                ELSE 0 
            END
        ) as low_stock_count,
        SUM(
            CASE 
                WHEN pv.id IS NOT NULL AND pv.stock_quantity = 0 THEN 1
                WHEN pv.id IS NULL AND p.stock_quantity = 0 THEN 1
                ELSE 0 
            END
        ) as out_of_stock_count,
        SUM(
            CASE 
                WHEN pv.id IS NOT NULL THEN COALESCE(pv.price * pv.stock_quantity, p.cost_price * pv.stock_quantity, 0)
                ELSE COALESCE(p.cost_price * p.stock_quantity, 0)
            END
        ) as total_inventory_value
    FROM products p
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    WHERE p.user_id = ?
", [$user_id]);

$stats = $inventoryStats[0] ?? [
    'total_products' => 0,
    'total_skus' => 0,
    'total_quantity' => 0,
    'low_stock_count' => 0,
    'out_of_stock_count' => 0,
    'total_inventory_value' => 0
];

// Récupérer les produits et SKUs en stock faible - UPDATED QUERY
$lowStockItems = $db->getThisQuery("
    SELECT 
        p.id as product_id,
        p.name as product_name,
        p.sku as parent_sku,
        p.stock_quantity as product_quantity,
        pv.id as sku_id,
        pv.sku as sku_code,
        pv.attributes as variant_name,
        pv.stock_quantity as sku_quantity,
        CASE 
            WHEN pv.id IS NOT NULL THEN 'variant'
            ELSE 'product'
        END as item_type,
        CASE 
            WHEN pv.id IS NOT NULL THEN COALESCE(pv.price, p.cost_price)
            ELSE p.cost_price
        END as cost_price,
        CASE 
            WHEN pv.id IS NOT NULL THEN COALESCE(pv.price, p.price)
            ELSE p.price
        END as price
    FROM products p
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    WHERE p.user_id = ?
    AND (
        (pv.id IS NOT NULL AND pv.stock_quantity <= 10)
        OR 
        (pv.id IS NULL AND p.stock_quantity <= 10)
    )
    ORDER BY 
        CASE 
            WHEN pv.id IS NOT NULL THEN pv.stock_quantity
            ELSE p.stock_quantity
        END ASC
    LIMIT 10
", [$user_id]);

// Check if stock_movements table exists, if not create it
$tableExists = $db->getThisQuery("
    SHOW TABLES LIKE 'stock_movements'
");

if (empty($tableExists)) {
    // Create stock_movements table
    $db->query("
        CREATE TABLE IF NOT EXISTS stock_movements (
            id INT PRIMARY KEY AUTO_INCREMENT,
            product_id INT NOT NULL,
            variation_id INT NULL,
            movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
            quantity INT NOT NULL,
            previous_quantity INT NULL,
            new_quantity INT NULL,
            reason VARCHAR(500) NULL,
            user_id INT NOT NULL,
            reference_type VARCHAR(50) NULL,
            reference_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_product_id (product_id),
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_movement_type (movement_type),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (variation_id) REFERENCES product_variants(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

// Récupérer les mouvements de stock récents - UPDATED QUERY
$recentMovements = $db->getThisQuery("
    SELECT 
        sm.*,
        p.name as product_name,
        p.sku as product_sku,
        pv.sku as variant_sku,
        pv.attributes as variant_name,
        u.name as user_name
    FROM stock_movements sm
    LEFT JOIN products p ON sm.product_id = p.id
    LEFT JOIN product_variants pv ON sm.variation_id = pv.id
    LEFT JOIN users u ON sm.user_id = u.id
    WHERE p.user_id = ?
    ORDER BY sm.created_at DESC
    LIMIT 10
", [$user_id]);

// Récupérer les catégories avec stock - UPDATED QUERY
$categoriesWithStock = $db->getThisQuery("
    SELECT 
        p.category as category_name,
        COUNT(DISTINCT p.id) as product_count,
        SUM(
            CASE 
                WHEN pv.id IS NOT NULL THEN COALESCE(pv.stock_quantity, 0)
                ELSE COALESCE(p.stock_quantity, 0)
            END
        ) as total_quantity,
        SUM(
            CASE 
                WHEN pv.id IS NOT NULL THEN COALESCE(pv.price * pv.stock_quantity, p.cost_price * pv.stock_quantity, 0)
                ELSE COALESCE(p.cost_price * p.stock_quantity, 0)
            END
        ) as total_value
    FROM products p
    LEFT JOIN product_variants pv ON p.id = pv.product_id
    WHERE p.user_id = ?
    AND p.category IS NOT NULL
    AND p.category != ''
    GROUP BY p.category
    ORDER BY total_value DESC
    LIMIT 5
", [$user_id]);


function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../login');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inventaire et Gestion des Stocks - Plateforme">
    <title>Inventaire des Stocks | OBG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #7c3aed;
            --primary-light: #8b5cf6;
            --primary-dark: #6d28d9;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --success-light: #34d399;
            --warning-color: #f59e0b;
            --warning-light: #fbbf24;
            --danger-color: #ef4444;
            --danger-light: #f87171;
            --info-color: #0ea5e9;
            --info-light: #38bdf8;
            --background-color: #f8fafc;
            --surface-color: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 10px 10px -5px rgb(0 0 0 / 0.04);
            --border-radius: 16px;
            --border-radius-sm: 10px;
            --border-radius-lg: 20px;
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Segoe UI Emoji', sans-serif;
            background: linear-gradient(135deg, #f6f9ff 0%, #f0f4ff 100%);
            color: var(--text-primary);
            line-height: 1.7;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--background-color);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-light), var(--primary-dark));
            border-radius: 10px;
            border: 2px solid var(--background-color);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary-dark), #5b21b6);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background: transparent;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Top Navigation */
        .top-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 1.25rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 20px rgba(124, 58, 237, 0.08);
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .search-bar {
            position: relative;
            max-width: 450px;
            flex: 1;
            margin: 0 2rem;
        }

        .search-bar input {
            width: 100%;
            padding: 0.875rem 1.25rem 0.875rem 3.5rem;
            border: 2px solid transparent;
            border-radius: 50px;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(139, 92, 246, 0.05));
            font-size: 0.95rem;
            transition: var(--transition-smooth);
            box-shadow: 0 2px 10px rgba(124, 58, 237, 0.05);
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.15);
            background: white;
            transform: translateY(-1px);
        }

        .search-bar .search-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            font-size: 1.1rem;
            transition: var(--transition-smooth);
        }

        .search-bar input:focus + .search-icon {
            color: var(--primary-dark);
            transform: translateY(-50%) scale(1.1);
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 2.5rem;
            animation: contentAppear 0.6s ease-out 0.2s both;
        }

        @keyframes contentAppear {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2.5rem;
            animation: slideUp 0.6s ease-out 0.3s both;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-header h2 {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            display: inline-block;
        }

        .dashboard-header h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            border-radius: 2px;
            animation: expandWidth 0.8s ease-out 0.8s both;
        }

        @keyframes expandWidth {
            from { width: 0; }
            to { width: 60px; }
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.75rem;
            margin-bottom: 2.5rem;
            animation: cardsAppear 0.6s ease-out 0.4s both;
        }

        @keyframes cardsAppear {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(124, 58, 237, 0.1);
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            backdrop-filter: blur(10px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(139, 92, 246, 0.03));
            opacity: 0;
            transition: var(--transition-smooth);
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-xl);
            border-color: rgba(124, 58, 237, 0.2);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover::after {
            transform: scaleX(1);
        }

        .stat-card.primary::after {
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }

        .stat-card.success::after {
            background: linear-gradient(90deg, var(--success-color), var(--success-light));
        }

        .stat-card.warning::after {
            background: linear-gradient(90deg, var(--warning-color), var(--warning-light));
        }

        .stat-card.danger::after {
            background: linear-gradient(90deg, var(--danger-color), var(--danger-light));
        }

        .stat-card.info::after {
            background: linear-gradient(90deg, var(--info-color), var(--info-light));
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
            position: relative;
            transition: var(--transition-smooth);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-card.primary .stat-icon {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(139, 92, 246, 0.1));
            color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
        }

        .stat-card.success .stat-icon {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(52, 211, 153, 0.1));
            color: var(--success-color);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .stat-card.warning .stat-icon {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1));
            color: var(--warning-color);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
        }

        .stat-card.danger .stat-icon {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(248, 113, 113, 0.1));
            color: var(--danger-color);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.15);
        }

        .stat-card.info .stat-icon {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.1), rgba(56, 189, 248, 0.1));
            color: var(--info-color);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.15);
        }

        .stat-content h3 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card:hover .stat-content h3 {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-content p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin: 0;
            font-weight: 500;
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2.5rem;
            animation: gridAppear 0.6s ease-out 0.5s both;
        }

        @keyframes gridAppear {
            from {
                opacity: 0;
                transform: translateY(60px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Chart Container */
        .chart-container {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(124, 58, 237, 0.1);
            height: 100%;
            transition: var(--transition-smooth);
        }

        .chart-container:hover {
            box-shadow: var(--shadow-lg);
            border-color: rgba(124, 58, 237, 0.2);
            transform: translateY(-2px);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .chart-header h4 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .chart-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Table Styles */
        .table-container {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(124, 58, 237, 0.1);
            overflow: hidden;
            height: 100%;
            transition: var(--transition-smooth);
        }

        .table-container:hover {
            box-shadow: var(--shadow-lg);
            border-color: rgba(124, 58, 237, 0.2);
        }

        .table-header {
            padding: 1.75rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.02), rgba(139, 92, 246, 0.02));
        }

        .table-header h4 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .table-content {
            padding: 1.75rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .inventory-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .inventory-table th {
            text-align: left;
            padding: 1.25rem;
            font-weight: 700;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.02), rgba(139, 92, 246, 0.02));
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .inventory-table td {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(124, 58, 237, 0.05);
            vertical-align: middle;
            font-size: 0.95rem;
            transition: var(--transition-smooth);
        }

        .inventory-table tr {
            transition: var(--transition-smooth);
        }

        .inventory-table tr:hover {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.03), rgba(139, 92, 246, 0.03));
            transform: translateX(4px);
        }

        .inventory-table tr:hover td {
            border-bottom-color: rgba(124, 58, 237, 0.1);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
            animation: quickActionsAppear 0.6s ease-out 0.6s both;
        }

        @keyframes quickActionsAppear {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .quick-action-card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid rgba(124, 58, 237, 0.1);
            transition: var(--transition-bounce);
            cursor: pointer;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .quick-action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(139, 92, 246, 0.05));
            opacity: 0;
            transition: var(--transition-smooth);
        }

        .quick-action-card:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: var(--shadow-xl);
            border-color: rgba(124, 58, 237, 0.3);
        }

        .quick-action-card:hover::before {
            opacity: 1;
        }

        .quick-action-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: white;
            margin: 0 auto 1.5rem;
            transition: var(--transition-bounce);
            box-shadow: 0 8px 20px rgba(124, 58, 237, 0.3);
            position: relative;
            z-index: 1;
        }

        .quick-action-card:hover .quick-action-icon {
            transform: scale(1.15) rotate(360deg);
            box-shadow: 0 12px 30px rgba(124, 58, 237, 0.4);
        }

        .quick-action-card h5 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
            position: relative;
            z-index: 1;
        }

        .quick-action-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        /* Stock Badges */
        .stock-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition-smooth);
        }

        .stock-badge i {
            font-size: 0.7rem;
        }

        .stock-badge.low {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(251, 191, 36, 0.15));
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.1);
        }

        .stock-badge.out {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(248, 113, 113, 0.15));
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
        }

        .stock-badge.normal {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(52, 211, 153, 0.15));
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
        }

        /* Movement Type */
        .movement-type {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition-smooth);
        }

        .movement-type i {
            font-size: 0.7rem;
        }

        .movement-type.in {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(52, 211, 153, 0.15));
            color: var(--success-color);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
        }

        .movement-type.out {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(248, 113, 113, 0.15));
            color: var(--danger-color);
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.1);
        }

        .movement-type.adjustment {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.15), rgba(139, 92, 246, 0.15));
            color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.1);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-color);
            background: var(--surface-color);
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-bounce);
            position: relative;
            overflow: hidden;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            opacity: 0;
            transition: var(--transition-smooth);
        }

        .action-btn:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: var(--shadow-md);
        }

        .action-btn:hover::before {
            opacity: 1;
        }

        .action-btn i {
            position: relative;
            z-index: 1;
            transition: var(--transition-smooth);
        }

        .action-btn:hover i {
            color: white;
            transform: scale(1.1);
        }

        .action-btn.primary:hover {
            border-color: var(--primary-color);
        }

        .action-btn.success:hover {
            border-color: var(--success-color);
        }

        .action-btn.danger:hover {
            border-color: var(--danger-color);
        }

        /* Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition-smooth);
            backdrop-filter: blur(10px);
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            background: var(--surface-color);
            padding: 3rem;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow-xl);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite;
            margin: 0 auto 1.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(720deg); }
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: var(--surface-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-xl);
            z-index: 10000;
            transform: translateX(400px);
            transition: var(--transition-bounce);
            border-left: 6px solid var(--primary-color);
            min-width: 320px;
            max-width: 400px;
            backdrop-filter: blur(10px);
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.success {
            border-left-color: var(--success-color);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.2);
        }

        .notification.error {
            border-left-color: var(--danger-color);
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.2);
        }

        .notification.warning {
            border-left-color: var(--warning-color);
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.2);
        }

        .notification.info {
            border-left-color: var(--info-color);
            box-shadow: 0 10px 30px rgba(14, 165, 233, 0.2);
        }

        /* Modal Styles */
        .modal-content {
            border-radius: var(--border-radius-lg);
            border: none;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            animation: modalAppear 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: scale(0.8) translateY(-50px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
            padding: 2rem;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .modal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,100 M100,0 L0,100" stroke="white" stroke-width="0.5" stroke-opacity="0.1"/></svg>');
        }

        .modal-title {
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            position: relative;
            z-index: 1;
        }

        .modal-body {
            padding: 2rem;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.02), rgba(139, 92, 246, 0.02));
        }

        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.95rem;
            transition: var(--transition-smooth);
            background: var(--surface-color);
            box-shadow: 0 2px 8px rgba(124, 58, 237, 0.05);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.15);
            transform: translateY(-1px);
        }

        .form-control:focus::placeholder {
            color: var(--primary-color);
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }

            .dashboard-content {
                padding: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.5rem;
            }

            .dashboard-header h2 {
                font-size: 1.75rem;
            }

            .top-navbar {
                padding: 1rem;
            }

            .search-bar {
                margin: 0;
                max-width: none;
            }

            .chart-header, .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .chart-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            color: var(--text-secondary);
            animation: fadeIn 0.8s ease-out;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-block;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .empty-state p {
            margin-bottom: 2rem;
            font-size: 1rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* View More Link */
        .view-more {
            text-align: center;
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.02), rgba(139, 92, 246, 0.02));
        }

        .view-more a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: var(--transition-smooth);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(139, 92, 246, 0.1));
            border: 1px solid rgba(124, 58, 237, 0.2);
        }

        .view-more a:hover {
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-color: var(--primary-color);
            gap: 1rem;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
        }

        /* Progress Bars */
        .progress {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(139, 92, 246, 0.1));
            border-radius: 10px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            border-radius: 10px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-bar.bg-warning {
            background: linear-gradient(90deg, var(--warning-color), var(--warning-light));
        }

        .progress-bar.bg-success {
            background: linear-gradient(90deg, var(--success-color), var(--success-light));
        }

        .progress-bar.bg-danger {
            background: linear-gradient(90deg, var(--danger-color), var(--danger-light));
        }

        .progress-bar.bg-info {
            background: linear-gradient(90deg, var(--info-color), var(--info-light));
        }

        /* Glow Effects */
        .glow {
            animation: glow 2s ease-in-out infinite alternate;
        }

        @keyframes glow {
            from {
                box-shadow: 0 0 10px rgba(124, 58, 237, 0.3);
            }
            to {
                box-shadow: 0 0 20px rgba(124, 58, 237, 0.5), 0 0 30px rgba(139, 92, 246, 0.2);
            }
        }

        /* Hover Effects */
        .hover-lift {
            transition: var(--transition-smooth);
        }

        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        /* Custom Select2 */
        .select2-container--default .select2-selection--single {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            height: 48px;
            padding: 0.5rem;
            transition: var(--transition-smooth);
        }

        .select2-container--default .select2-selection--single:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.15);
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 34px;
            color: var(--text-primary);
        }

        /* Print Styles */
        @media print {
            .top-navbar, .quick-actions, .action-buttons, .view-more {
                display: none !important;
            }
            
            .stat-card, .table-container, .chart-container {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'stock_inventory';
    require_once('../assets/sidebar.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <button class="btn btn-link sidebar-toggle d-md-none">
                <i class="fas fa-bars" style="color: var(--primary-color);"></i>
            </button>
            
            <div class="search-bar">
                <i class="fas fa-search search-icon"></i>
                <input type="text" placeholder="Rechercher produits, SKUs, catégories..." id="global-search">
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <div class="notifications">
                    <button class="notification-btn btn-icon" data-tooltip="Notifications" style="background: none; border: none">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle"
                        style="top: auto !important; left: auto !important;">0</span>
                    </button>
                </div>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <div class="dashboard-header">
                <div>
                    <h2><i class="fas fa-boxes-stacked me-2"></i> Inventaire des Stocks</h2>
                    <p class="text-muted mb-0">Vue d'ensemble de votre inventaire et gestion des stocks</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary hover-lift" onclick="showAdjustStockModal()">
                        <i class="fas fa-edit me-2"></i>
                        Ajuster le stock
                    </button>
                    <button class="btn btn-info hover-lift" onclick="exportInventory()">
                        <i class="fas fa-file-export me-2"></i>
                        Exporter
                    </button>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="quick-action-card" onclick="navigateTo('./stock_management')">
                    <div class="quick-action-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <h5>Gérer les SKUs</h5>
                    <p>Créer et gérer les variantes de produits</p>
                </div>
                <div class="quick-action-card" onclick="navigateTo('./items')">
                    <div class="quick-action-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <h5>Voir les produits</h5>
                    <p>Consulter tous vos produits</p>
                </div>
                <div class="quick-action-card" onclick="showAdjustStockModal()">
                    <div class="quick-action-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h5>Ajuster le stock</h5>
                    <p>Corriger les quantités en stock</p>
                </div>
                <div class="quick-action-card" onclick="showStockReport()">
                    <div class="quick-action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h5>Rapports</h5>
                    <p>Analyser les performances du stock</p>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_products']); ?></h3>
                        <p>Produits en stock</p>
                    </div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_skus']); ?></h3>
                        <p>SKUs/Variantes</p>
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_quantity']); ?></h3>
                        <p>Unités totales</p>
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['low_stock_count']); ?></h3>
                        <p>Stock faible</p>
                    </div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['out_of_stock_count']); ?></h3>
                        <p>En rupture</p>
                    </div>
                </div>
                
                <div class="stat-card primary">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_inventory_value'], 2, ',', ' '); ?> MAD</h3>
                        <p>Valeur totale de l'inventaire</p>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Left Column: Charts -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h4><i class="fas fa-chart-line"></i> Évolution du stock</h4>
                        <div class="chart-actions">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary active" data-period="7">
                                    7j
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-period="30">
                                    30j
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-period="90">
                                    90j
                                </button>
                            </div>
                            <button class="btn btn-sm btn-light" onclick="downloadChart()" title="Télécharger">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Canvas avec hauteur fixe -->
                    <div class="chart-wrapper" style="height: 220px; position: relative;">
                        <canvas id="stockChart" height="220"></canvas>
                        
                        <!-- Overlay pour indiquer le chargement -->
                        <div class="chart-overlay" id="chartLoading" style="display: none;">
                            <div class="spinner spinner-sm"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Low Stock -->
                <div class="table-container">
                    <div class="table-header">
                        <h4><i class="fas fa-exclamation-triangle text-warning me-2"></i>Stock faible</h4>
                        <span class="badge bg-warning">
                            <?php echo is_array($lowStockItems) ? count($lowStockItems) : 0; ?> items
                        </span>
                    </div>
                    <div class="table-content">
                        <?php if (!empty($lowStockItems)): ?>
                            <table class="inventory-table">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>SKU</th>
                                        <th>Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lowStockItems as $item): ?>
                                        <?php
                                        $sku = $item['sku_code'] ?: $item['parent_sku'];
                                        $quantity = $item['sku_quantity'] ?: $item['product_quantity'];
                                        $percentage = ($quantity / 10) * 100; // Using 10 as default threshold
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                <?php 
                                                if ($item['variant_name']):
                                                    $attributes = json_decode($item['variant_name'], true);
                                                    if ($attributes && is_array($attributes)):
                                                        $variantText = implode(', ', array_map(function($key, $value) {
                                                            return "$key: $value";
                                                        }, array_keys($attributes), $attributes));
                                                ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($variantText); ?></small>
                                                <?php 
                                                    endif;
                                                endif; 
                                                ?>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($sku); ?></code>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-bold"><?php echo $quantity; ?></span>
                                                    <div class="progress" style="width: 80px; height: 6px;">
                                                        <div class="progress-bar bg-warning" role="progressbar" 
                                                             style="width: <?php echo min($percentage, 100); ?>%">
                                                        </div>
                                                    </div>
                                                    <span class="stock-badge low">Faible</span>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if (count($lowStockItems) >= 10): ?>
                                <div class="view-more">
                                    <a href="stock_management.php?filter=low_stock">
                                        Voir tous les stocks faibles
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle text-success"></i>
                                <h3>Aucun stock faible</h3>
                                <p>Tous vos produits ont un niveau de stock suffisant.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Movements -->
            <div class="table-container" style="margin-top: 2rem;">
                <div class="table-header">
                    <h4><i class="fas fa-history me-2"></i>Mouvements récents</h4>
                    <button class="btn btn-sm btn-outline-primary" onclick="showAllMovements()">
                        Voir tout
                    </button>
                </div>
                <div class="table-content">
                    <?php if (!empty($recentMovements)): ?>
                        <table class="inventory-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Produit</th>
                                    <th>Type</th>
                                    <th>Quantité</th>
                                    <th>Utilisateur</th>
                                    <th>Raison</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentMovements as $movement): ?>
                                    <?php 
                                    // Parse variant attributes if exists
                                    $variantName = '';
                                    if ($movement['variant_name']) {
                                        $attributes = json_decode($movement['variant_name'], true);
                                        if ($attributes && is_array($attributes)) {
                                            $variantName = implode(', ', array_map(function($key, $value) {
                                                return "$key: $value";
                                            }, array_keys($attributes), $attributes));
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($movement['product_name']); ?></div>
                                            <?php if ($movement['variant_sku'] || $variantName): ?>
                                                <small class="text-muted">
                                                    <?php 
                                                    if ($movement['variant_sku']) {
                                                        echo htmlspecialchars($movement['variant_sku']);
                                                        if ($variantName) echo " - ";
                                                    }
                                                    echo htmlspecialchars($variantName);
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="movement-type <?php echo $movement['movement_type']; ?>">
                                                <?php 
                                                echo $movement['movement_type'] === 'in' ? 'Entrée' : 
                                                     ($movement['movement_type'] === 'out' ? 'Sortie' : 'Ajustement');
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold <?php echo $movement['movement_type'] === 'in' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $movement['movement_type'] === 'in' ? '+' : '-'; ?>
                                                <?php echo $movement['quantity']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($movement['user_name']); ?>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($movement['reason'] ?: '-'); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history text-muted"></i>
                            <h3>Aucun mouvement récent</h3>
                            <p>Les mouvements de stock apparaîtront ici.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Categories Summary -->
            <?php if (!empty($categoriesWithStock)): ?>
            <div class="table-container" style="margin-top: 2rem;">
                <div class="table-header">
                    <h4><i class="fas fa-folder me-2"></i>Stock par catégorie</h4>
                </div>
                <div class="table-content">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Catégorie</th>
                                <th>Produits</th>
                                <th>Quantité</th>
                                <th>Valeur</th>
                                <th>% du total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalValue = $stats['total_inventory_value'];
                            foreach ($categoriesWithStock as $category): 
                                $percentage = $totalValue > 0 ? ($category['total_value'] / $totalValue * 100) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($category['category_name']); ?></div>
                                    </td>
                                    <td>
                                        <?php echo $category['product_count']; ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($category['total_quantity']); ?>
                                    </td>
                                    <td>
                                        <?php echo number_format($category['total_value'], 2, ',', ' '); ?> MAD
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress" style="width: 100px; height: 6px;">
                                                <div class="progress-bar bg-primary" role="progressbar" 
                                                     style="width: <?php echo min($percentage, 100); ?>%">
                                                </div>
                                            </div>
                                            <span><?php echo number_format($percentage, 1); ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
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

    <!-- Adjust Stock Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Ajuster le stock
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Search Section -->
                    <div class="search-section mb-4">
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="stockSearch" 
                                placeholder="Rechercher produit par nom, SKU, code-barres...">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            Tapez pour rechercher un produit. Les résultats s'afficheront ci-dessous.
                        </small>
                    </div>

                    <!-- Results Grid -->
                    <div class="results-grid" id="searchResults" style="display: none;">
                        <h6 class="mb-3">Résultats de la recherche</h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>SKU</th>
                                        <th>Stock actuel</th>
                                        <th>Prix</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="resultsBody">
                                    <!-- Results will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Adjustment Form -->
                    <div class="adjustment-form" id="adjustmentForm" style="display: none;">
                        <div class="row">
                            <div class="col-md-8">
                                <!-- Product Info -->
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-box me-2"></i>
                                            Informations du produit
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Nom:</strong> <span id="selectedProductName"></span></p>
                                                <p class="mb-1"><strong>SKU:</strong> <span id="selectedProductSku"></span></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-1"><strong>Catégorie:</strong> <span id="selectedProductCategory"></span></p>
                                                <p class="mb-1"><strong>Type:</strong> <span id="selectedProductType"></span></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Adjustment Details -->
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-sliders-h me-2"></i>
                                            Détails de l'ajustement
                                        </h6>
                                        <form id="adjustStockForm">
                                            <input type="hidden" id="adjustProductId" name="product_id">
                                            <input type="hidden" id="adjustVariantId" name="variation_id">
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-4">
                                                    <label class="form-label">Type d'ajustement *</label>
                                                    <select class="form-select" id="adjustmentType" name="movement_type" required>
                                                        <option value="">Sélectionner</option>
                                                        <option value="in">Entrée de stock</option>
                                                        <option value="out">Sortie de stock</option>
                                                        <option value="adjustment">Correction</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Quantité *</label>
                                                    <input type="number" class="form-control" id="adjustQuantity" 
                                                        name="quantity" min="1" value="1" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Date</label>
                                                    <input type="datetime-local" class="form-control" 
                                                        name="movement_date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Raison *</label>
                                                <textarea class="form-control" id="adjustReason" 
                                                        name="reason" rows="3" required
                                                        placeholder="Ex: Réapprovisionnement, Vente, Inventaire..."></textarea>
                                                <div class="form-text">
                                                    Décrivez la raison de l'ajustement pour le suivi.
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Référence (optionnel)</label>
                                                <input type="text" class="form-control" name="reference" 
                                                    placeholder="Numéro de commande, facture, etc.">
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <!-- Stock Summary -->
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-chart-line me-2"></i>
                                            Résumé du stock
                                        </h6>
                                        <div class="stock-summary">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Stock actuel:</span>
                                                <strong id="currentStock">0</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Nouveau stock:</span>
                                                <strong id="newStock" class="text-success">0</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Variation:</span>
                                                <strong id="stockChange" class="text-primary">+0</strong>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between">
                                                <span>Statut:</span>
                                                <span id="stockStatus" class="badge bg-secondary">N/A</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quick Actions -->
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-bolt me-2"></i>
                                            Actions rapides
                                        </h6>
                                        <div class="d-grid gap-2">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="applyQuickQuantity(10)">
                                                +10 unités
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="applyQuickQuantity(-5)">
                                                -5 unités
                                            </button>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="setToZero()">
                                                Mettre à zéro
                                            </button>
                                            <button type="button" class="btn btn-outline-info" 
                                                    onclick="suggestReplenishment()">
                                                Suggérer réappro
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" id="saveAdjustment" disabled>
                        <i class="fas fa-save me-2"></i>
                        Enregistrer l'ajustement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentProducts = [];
        let selectedProduct = null;

        $(document).ready(function() {
            // Initialize search functionality
            initializeStockSearch();
            
            // Initialize adjustment form events
            initializeAdjustmentForm();
        });

        function initializeStockSearch() {
            const searchInput = $('#stockSearch');
            let searchTimeout;
            
            // Search on input
            searchInput.on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val().trim();
                
                if (query.length < 2) {
                    $('#searchResults').hide();
                    $('#resultsBody').empty();
                    return;
                }
                
                // Show loading
                searchInput.parent().append('<span class="search-loading"></span>');
                
                searchTimeout = setTimeout(() => {
                    searchProducts(query);
                }, 500);
            });
            
            // Clear search
            $('#clearSearch').click(function() {
                searchInput.val('');
                $('#searchResults').hide();
                $('#resultsBody').empty();
                resetAdjustmentForm();
            });
            
            // Enter key to search
            searchInput.on('keypress', function(e) {
                if (e.which === 13) {
                    const query = $(this).val().trim();
                    if (query.length >= 2) {
                        searchProducts(query);
                    }
                }
            });
        }

        async function searchProducts(query) {
            try {
                showLoading();
                
                // Fetch products from API
                const response = await fetch(`./controllers/search_productsForApi.php?q=${encodeURIComponent(query)}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                if (data.success) {
                    currentProducts = data.products || [];
                    displaySearchResults(currentProducts);
                } else {
                    showNotification(data.message || 'Erreur de recherche', 'error');
                    $('#searchResults').hide();
                }
            } catch (error) {
                console.error('Search error:', error);
                showNotification('Erreur lors de la recherche', 'error');
            } finally {
                hideLoading();
                $('.search-loading').remove();
            }
        }

        function displaySearchResults(products) {
            const resultsBody = $('#resultsBody');
            resultsBody.empty();
            
            if (products.length === 0) {
                resultsBody.html(`
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <i class="fas fa-search fa-2x text-muted mb-3"></i>
                            <p class="text-muted">Aucun produit trouvé</p>
                        </td>
                    </tr>
                `);
            } else {
                products.forEach((product, index) => {
                    const stockClass = getStockStatusClass(product.stock_quantity);
                    const stockText = getStockStatusText(product.stock_quantity);
                    
                    const row = `
                        <tr data-index="${index}" onclick="selectProduct(${index})">
                            <td>
                                <div class="fw-bold">${product.name}</div>
                                ${product.variant_name ? 
                                    `<small class="text-muted">${product.variant_name}</small>` : ''}
                            </td>
                            <td>
                                <code>${product.sku}</code>
                                ${product.barcode ? 
                                    `<br><small class="text-muted">${product.barcode}</small>` : ''}
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold">${product.stock_quantity || 0}</span>
                                    <span class="stock-status ${stockClass}">${stockText}</span>
                                </div>
                            </td>
                            <td>
                                ${product.price ? `${parseFloat(product.price).toFixed(2)} MAD` : '-'}
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="event.stopPropagation(); selectProduct(${index})">
                                    Sélectionner
                                </button>
                            </td>
                        </tr>
                    `;
                    resultsBody.append(row);
                });
            }
            
            $('#searchResults').show();
        }

        function selectProduct(index) {
            const product = currentProducts[index];
            if (!product) return;
            
            // Update UI
            $('#resultsBody tr').removeClass('selected');
            $(`#resultsBody tr[data-index="${index}"]`).addClass('selected');
            
            // Store selected product
            selectedProduct = product;
            
            // Populate form
            populateAdjustmentForm(product);
            
            // Show adjustment form
            $('#adjustmentForm').show();
            $('#saveAdjustment').prop('disabled', false);
            
            // Scroll to form
            $('#adjustmentForm')[0].scrollIntoView({ behavior: 'smooth' });
        }

        function populateAdjustmentForm(product) {
            // Product info
            $('#selectedProductName').text(product.name);
            $('#selectedProductSku').text(product.sku);
            $('#selectedProductCategory').text(product.category || 'Non catégorisé');
            $('#selectedProductType').text(product.variant_name ? 'Variant' : 'Produit simple');
            
            // Form fields
            $('#adjustProductId').val(product.id);
            $('#adjustVariantId').val(product.variant_id || '');
            
            // Current stock
            const currentStock = product.stock_quantity || 0;
            $('#currentStock').text(currentStock);
            
            // Update stock summary
            updateStockSummary();
        }

        function initializeAdjustmentForm() {
            // Update stock summary on changes
            $('#adjustmentType, #adjustQuantity').on('change input', updateStockSummary);
            
            // Save adjustment
            $('#saveAdjustment').click(saveStockAdjustment);
        }

        function updateStockSummary() {
            if (!selectedProduct) return;
            
            const currentStock = parseInt($('#currentStock').text()) || 0;
            const adjustmentType = $('#adjustmentType').val();
            const quantity = parseInt($('#adjustQuantity').val()) || 0;
            
            let newStock = currentStock;
            let stockChange = 0;
            
            switch (adjustmentType) {
                case 'in':
                    stockChange = quantity;
                    newStock = currentStock + quantity;
                    break;
                case 'out':
                    stockChange = -quantity;
                    newStock = Math.max(0, currentStock - quantity);
                    break;
                case 'adjustment':
                    stockChange = quantity - currentStock;
                    newStock = Math.max(0, quantity);
                    break;
                default:
                    return;
            }
            
            // Update display
            $('#newStock').text(newStock);
            $('#stockChange').text(stockChange >= 0 ? `+${stockChange}` : stockChange);
            $('#stockChange').toggleClass('text-success', stockChange >= 0)
                            .toggleClass('text-danger', stockChange < 0);
            
            // Update status
            const statusClass = getStockStatusClass(newStock);
            const statusText = getStockStatusText(newStock);
            $('#stockStatus').removeClass().addClass(`badge ${statusClass}`).text(statusText);
        }

        function getStockStatusClass(quantity) {
            if (quantity === 0) return 'bg-danger';
            if (quantity <= 10) return 'bg-warning';
            return 'bg-success';
        }

        function getStockStatusText(quantity) {
            if (quantity === 0) return 'En rupture';
            if (quantity <= 10) return 'Stock faible';
            return 'En stock';
        }

        function applyQuickQuantity(amount) {
            const current = parseInt($('#adjustQuantity').val()) || 0;
            const newValue = Math.max(0, current + amount);
            $('#adjustQuantity').val(newValue).trigger('input');
        }

        function setToZero() {
            $('#adjustQuantity').val(0).trigger('input');
            $('#adjustmentType').val('adjustment').trigger('change');
        }

        function suggestReplenishment() {
            const currentStock = parseInt($('#currentStock').text()) || 0;
            const suggested = Math.max(20, Math.round(currentStock * 0.5));
            $('#adjustQuantity').val(suggested);
            $('#adjustmentType').val('in').trigger('change');
            $('#adjustReason').val('Réapprovisionnement suggéré par le système');
        }

        async function saveStockAdjustment() {
            if (!selectedProduct) {
                showNotification('Veuillez sélectionner un produit', 'warning');
                return;
            }
            
            // Validate form
            const formData = {
                product_id: $('#adjustProductId').val(),
                variation_id: $('#adjustVariantId').val(),
                movement_type: $('#adjustmentType').val(),
                quantity: $('#adjustQuantity').val(),
                reason: $('#adjustReason').val(),
                reference: $('input[name="reference"]').val(),
                movement_date: $('input[name="movement_date"]').val()
            };
            
            if (!formData.movement_type || !formData.quantity || !formData.reason) {

                showNotification('Veuillez remplir tous les champs obligatoires', 'warning');
                return;
            }
            
            try {
                showLoading();
                
                // Save adjustment via API
                const response = await fetch('./controllers/adjust_stock.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                if (data.success) {
                    // Show success message
                    showNotification('Stock ajusté avec succès!', 'success');
                    
                    // Close modal after delay
                    setTimeout(() => {
                        $('#adjustStockModal').modal('hide');
                        
                        // Reload page to show updated data
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    }, 1500);
                } else {
                    showNotification(data.message || 'Erreur lors de l\'ajustement', 'error');
                }
            } catch (error) {
                console.error('Save error:', error);
                showNotification('Erreur de connexion', 'error');
            } finally {
                hideLoading();
            }
        }

        function resetAdjustmentForm() {
            selectedProduct = null;
            $('#adjustmentForm').hide();
            $('#adjustStockForm')[0].reset();
            $('#saveAdjustment').prop('disabled', true);
        }

        // Expose function to global scope
        window.showAdjustStockModal = function(productId = null, variantId = null) {
            const modal = new bootstrap.Modal(document.getElementById('adjustStockModal'), {
                backdrop: 'static',
                keyboard: false
            });
            
            modal.show();
            
            // If product ID is provided, load that product
            if (productId) {
                setTimeout(() => {
                    loadSpecificProduct(productId, variantId);
                }, 500);
            }
        };

        async function loadSpecificProduct(productId, variantId = null) {
            try {
                showLoading();
                
                const url = variantId 
                    ? `./controllers/get_variant.php?id=${variantId}&product_id=${productId}`
                    : `./controllers/get_product.php?id=${productId}`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    const product = data.product || data.variant;
                    currentProducts = [product];
                    selectProduct(0);
                } else {
                    showNotification('Produit non trouvé', 'error');
                }
            } catch (error) {
                console.error('Load product error:', error);
                showNotification('Erreur de chargement', 'error');
            } finally {
                hideLoading();
            }
        }
    </script>

    <style>
        /* Search Results Styling */
        #searchResults {
            max-height: 400px;
            overflow-y: auto;
        }

        #resultsBody tr {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        #resultsBody tr:hover {
            background-color: rgba(124, 58, 237, 0.05);
            transform: translateX(5px);
        }

        #resultsBody tr.selected {
            background-color: rgba(124, 58, 237, 0.1);
            border-left: 4px solid var(--primary-color);
        }

        /* Stock Status Badges */
        .stock-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stock-status.in-stock {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .stock-status.low-stock {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .stock-status.out-of-stock {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Quick Actions Animation */
        @keyframes quickAction {
            0% { transform: scale(1); }
            50% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }

        .btn:active {
            animation: quickAction 0.2s ease;
        }

        /* Loading Animation */
        .search-loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>

    <!-- Stock Report Modal -->
    <div class="modal fade report-modal" id="stockReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" style="border-radius: 0;">
                <div class="modal-header" style="border-radius: 0;">
                    <h5 class="modal-title">
                        <i class="fas fa-chart-bar me-2"></i>
                        Rapport de Stock Détail
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Report Filters -->
                    <div class="report-filters">
                        <form id="reportForm">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Date début</label>
                                    <input type="date" class="form-control" id="reportStartDate" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date fin</label>
                                    <input type="date" class="form-control" id="reportEndDate" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Catégorie</label>
                                    <select class="form-select" id="reportCategory">
                                        <option value="">Toutes les catégories</option>
                                        <?php 
                                        $categories = $db->getThisQuery("
                                            SELECT DISTINCT category 
                                            FROM products 
                                            WHERE user_id = ? AND category IS NOT NULL AND category != ''
                                            ORDER BY category
                                        ", [$user_id]);
                                        
                                        foreach ($categories as $cat):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                                                <?php echo htmlspecialchars($cat['category']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Statut stock</label>
                                    <select class="form-select" id="reportStockStatus">
                                        <option value="all">Tous</option>
                                        <option value="in_stock">En stock</option>
                                        <option value="low_stock">Stock faible</option>
                                        <option value="out_of_stock">En rupture</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary" id="generateReportBtn">
                                        <i class="fas fa-chart-line me-2"></i>
                                        Générer le rapport
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetReportForm()">
                                        <i class="fas fa-redo me-2"></i>
                                        Réinitialiser
                                    </button>
                                    <button type="button" class="btn btn-success ms-2" id="exportReportBtn" style="display: none;">
                                        <i class="fas fa-file-pdf me-2"></i>
                                        Exporter PDF
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Report Tabs -->
                    <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button">
                                <i class="fas fa-chart-pie me-2"></i>Résumé
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button">
                                <i class="fas fa-table me-2"></i>Détails
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="reportTabContent">
                        <div class="tab-pane fade show active" id="summary" role="tabpanel">
                            <div id="reportSummary">
                                <div class="text-center py-5">
                                    <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                                    <h4 class="text-muted">Aucun rapport généré</h4>
                                    <p class="text-muted">Configurez les filtres et cliquez sur "Générer le rapport"</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="details" role="tabpanel">
                            <div id="reportDetails">
                                <div class="text-center py-5">
                                    <i class="fas fa-table fa-3x text-muted mb-3"></i>
                                    <h4 class="text-muted">Aucun détail disponible</h4>
                                    <p class="text-muted">Les détails apparaîtront ici après génération du rapport</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Fermer
                    </button>
                    <button type="button" class="btn btn-primary" onclick="printReport()">
                        <i class="fas fa-print me-2"></i>
                        Imprimer
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* Chart Container - Compact and Beautiful */
        .chart-container {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
            border-radius: var(--border-radius);
            padding: 1.25rem 1.5rem;
            box-shadow: 
                0 4px 12px rgba(124, 58, 237, 0.08),
                0 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(124, 58, 237, 0.15);
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            height: 360px; /* Hauteur fixe pour une apparence compacte */
        }

        .chart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, 
                transparent, 
                var(--primary-color), 
                transparent
            );
            opacity: 0.3;
        }

        .chart-container:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 8px 25px rgba(124, 58, 237, 0.12),
                0 2px 4px rgba(0, 0, 0, 0.05);
            border-color: rgba(124, 58, 237, 0.25);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(124, 58, 237, 0.1);
        }

        .chart-header h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-header h4 i {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.2rem;
        }

        .chart-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-actions select {
            border: 1px solid rgba(124, 58, 237, 0.2);
            border-radius: 8px;
            padding: 0.375rem 0.75rem;
            font-size: 0.85rem;
            background: rgba(255, 255, 255, 0.8);
            color: var(--text-primary);
            transition: all 0.2s ease;
            min-width: 100px;
        }

        .chart-actions select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }

        .chart-actions .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 8px;
        }
    </style>

    <script>
        $(document).ready(function() {
            // Initialize charts with improved animations
            initializeCharts();
            
            // Initialize event listeners
            initializeEventListeners();
            
            // Load initial data
            loadChartData(30);
            
            // Add initial animations
            animateElements();
            
            function initializeEventListeners() {
                // Global search with improved UX
                $('#global-search').on('input', debounce(function(e) {
                    handleGlobalSearch(e.target.value);
                }, 300));
                
                // Chart period change
                $('#chart-period').change(function() {
                    const days = $(this).val();
                    animateChartChange(days);
                });
                
                // Adjust stock modal
                $('#adjustItemType').change(function() {
                    const type = $(this).val();
                    animateFormGroup(type);
                });
                
                $('#adjustProductId, #adjustSkuId').change(updateStockInfo);
                $('#adjustType, #adjustQuantity').on('input', updateStockInfo);
                
                
                // Quick action buttons
                $('.quick-action-card').click(function() {
                    animateQuickAction(this);
                });
                
                // Table row hover effects
                $('.inventory-table tbody tr').hover(
                    function() {
                        $(this).addClass('hover-lift');
                    },
                    function() {
                        $(this).removeClass('hover-lift');
                    }
                );
                
                // Stats cards hover
                $('.stat-card').hover(
                    function() {
                        $(this).addClass('glow');
                    },
                    function() {
                        $(this).removeClass('glow');
                    }
                );
            }
            
            function animateElements() {
                // Stagger animation for stats cards
                $('.stat-card').each(function(index) {
                    $(this).css({
                        'animation-delay': (index * 0.1) + 's',
                        'opacity': 0
                    }).animate({
                        opacity: 1
                    }, 600);
                });
                
                // Animate quick actions
                $('.quick-action-card').each(function(index) {
                    $(this).css({
                        'animation-delay': (index * 0.15) + 's',
                        'transform': 'scale(0.9)',
                        'opacity': 0
                    }).animate({
                        opacity: 1
                    }, 800, function() {
                        $(this).css('transform', 'scale(1)');
                    });
                });
            }
            
            function initializeCharts() {
                // Initialize Chart.js with beautiful theme
                Chart.defaults.font.family = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
                Chart.defaults.color = 'var(--text-secondary)';
                Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(255, 255, 255, 0.95)';
                Chart.defaults.plugins.tooltip.titleColor = 'var(--text-primary)';
                Chart.defaults.plugins.tooltip.bodyColor = 'var(--text-primary)';
                Chart.defaults.plugins.tooltip.borderColor = 'var(--border-color)';
                Chart.defaults.plugins.tooltip.borderWidth = 1;
                Chart.defaults.plugins.tooltip.cornerRadius = 12;
                Chart.defaults.plugins.tooltip.padding = 16;
                Chart.defaults.plugins.tooltip.boxPadding = 8;
                
                // Destroy existing chart if it exists
                if (window.stockChart && typeof window.stockChart.destroy === 'function') {
                    window.stockChart.destroy();
                }
                
                // Create gradient for chart
                const ctx = document.getElementById('stockChart').getContext('2d');
                const gradientIncoming = ctx.createLinearGradient(0, 0, 0, 400);
                gradientIncoming.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
                gradientIncoming.addColorStop(1, 'rgba(16, 185, 129, 0.05)');
                
                const gradientOutgoing = ctx.createLinearGradient(0, 0, 0, 400);
                gradientOutgoing.addColorStop(0, 'rgba(239, 68, 68, 0.3)');
                gradientOutgoing.addColorStop(1, 'rgba(239, 68, 68, 0.05)');
                
                const gradientCurrent = ctx.createLinearGradient(0, 0, 0, 400);
                gradientCurrent.addColorStop(0, 'rgba(124, 58, 237, 0.3)');
                gradientCurrent.addColorStop(1, 'rgba(124, 58, 237, 0.05)');
                
                window.stockChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: 'Entrées',
                                data: [],
                                borderColor: 'var(--success-color)',
                                backgroundColor: gradientIncoming,
                                tension: 0.4,
                                fill: true,
                                pointRadius: 0,
                                pointHoverRadius: 6,
                                pointBackgroundColor: 'var(--success-color)',
                                borderWidth: 3,
                                pointHoverBorderWidth: 2,
                                pointHoverBorderColor: '#fff'
                            },
                            {
                                label: 'Sorties',
                                data: [],
                                borderColor: 'var(--danger-color)',
                                backgroundColor: gradientOutgoing,
                                tension: 0.4,
                                fill: true,
                                pointRadius: 0,
                                pointHoverRadius: 6,
                                pointBackgroundColor: 'var(--danger-color)',
                                borderWidth: 3,
                                pointHoverBorderWidth: 2,
                                pointHoverBorderColor: '#fff'
                            },
                            {
                                label: 'Stock actuel',
                                data: [],
                                borderColor: 'var(--primary-color)',
                                backgroundColor: gradientCurrent,
                                tension: 0.4,
                                fill: true,
                                borderWidth: 4,
                                pointRadius: 0,
                                pointHoverRadius: 8,
                                pointBackgroundColor: 'var(--primary-color)',
                                pointHoverBorderWidth: 3,
                                pointHoverBorderColor: '#fff'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animations: {
                            tension: {
                                duration: 1000,
                                easing: 'easeInOutCubic'
                            },
                            colors: {
                                duration: 1000,
                                easing: 'easeInOutCubic'
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    padding: 24,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    },
                                    color: 'var(--text-secondary)'
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(255, 255, 255, 0.98)',
                                titleColor: 'var(--text-primary)',
                                bodyColor: 'var(--text-primary)',
                                borderColor: 'rgba(124, 58, 237, 0.2)',
                                borderWidth: 2,
                                cornerRadius: 12,
                                padding: 16,
                                boxPadding: 8,
                                displayColors: true,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.parsed.y.toLocaleString('fr');
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false,
                                    drawBorder: false
                                },
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: {
                                        size: 11
                                    },
                                    color: 'var(--text-secondary)'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    borderDash: [2, 2],
                                    drawBorder: false,
                                    color: 'rgba(124, 58, 237, 0.05)'
                                },
                                ticks: {
                                    callback: function(value) {
                                        return value.toLocaleString('fr');
                                    },
                                    padding: 16,
                                    font: {
                                        size: 11
                                    },
                                    color: 'var(--text-secondary)'
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'nearest'
                        },
                        elements: {
                            line: {
                                borderWidth: 3,
                                tension: 0.4
                            },
                            point: {
                                radius: 0,
                                hoverRadius: 6,
                                hoverBorderWidth: 2
                            }
                        }
                    }
                });
            }
            
            function animateChartChange(days) {
                $('.chart-container').addClass('loading');
                loadChartData(days);
                setTimeout(() => {
                    $('.chart-container').removeClass('loading');
                }, 1000);
            }
            
            function loadChartData(days) {
                showLoading();
                
                // Animate chart update
                setTimeout(() => {
                    useSampleChartData(days);
                    hideLoading();
                    
                    // Add success animation
                    $('.chart-container').addClass('success-animation');
                    setTimeout(() => {
                        $('.chart-container').removeClass('success-animation');
                    }, 1000);
                }, 800);
            }
            
            function useSampleChartData(days) {
                const labels = [];
                const incoming = [];
                const outgoing = [];
                const current = [];
                
                const baseStock = <?php echo $stats['total_quantity']; ?>;
                const today = new Date();
                
                let cumulativeIncoming = 0;
                let cumulativeOutgoing = 0;
                
                for (let i = days - 1; i >= 0; i--) {
                    const date = new Date();
                    date.setDate(today.getDate() - i);
                    
                    // Format date based on period
                    let dateFormat;
                    if (days <= 7) {
                        dateFormat = date.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit' });
                    } else if (days <= 30) {
                        dateFormat = date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
                    } else {
                        dateFormat = date.toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
                    }
                    
                    labels.push(dateFormat);
                    
                    // Generate realistic data with smooth transitions
                    const dailyIncoming = Math.floor(Math.random() * 50) + 10;
                    const dailyOutgoing = Math.floor(Math.random() * 40) + 5;
                    
                    incoming.push(dailyIncoming);
                    outgoing.push(dailyOutgoing);
                    
                    cumulativeIncoming += dailyIncoming;
                    cumulativeOutgoing += dailyOutgoing;
                    
                    const randomVariation = (Math.random() * 20 - 10);
                    const dailyStock = Math.max(0, Math.round(baseStock + cumulativeIncoming - cumulativeOutgoing + randomVariation));
                    current.push(dailyStock);
                }
                
                updateStockChart({
                    labels: labels,
                    incoming: incoming,
                    outgoing: outgoing,
                    current: current
                });
            }
            
            function updateStockChart(data) {
                if (!window.stockChart) {
                    initializeCharts();
                }
                
                // Animate chart update
                window.stockChart.data.labels = data.labels;
                window.stockChart.data.datasets[0].data = data.incoming;
                window.stockChart.data.datasets[1].data = data.outgoing;
                window.stockChart.data.datasets[2].data = data.current;
                
                window.stockChart.update('normal');
            }
            
            function handleGlobalSearch(searchTerm) {
                if (!searchTerm) {
                    $('.inventory-table tbody tr').show().removeClass('highlight');
                    return;
                }
                
                const term = searchTerm.toLowerCase();
                let hasMatches = false;
                
                $('.inventory-table tbody tr').each(function() {
                    const $row = $(this);
                    const rowText = $row.text().toLowerCase();
                    
                    if (rowText.includes(term)) {
                        $row.show();
                        if (!$row.hasClass('highlight')) {
                            $row.addClass('highlight').css('opacity', 0);
                            $row.animate({ opacity: 1 }, 300);
                        }
                        hasMatches = true;
                    } else {
                        $row.hide().removeClass('highlight');
                    }
                });
                
                // Show notification if no matches
                if (!hasMatches && searchTerm.length > 2) {
                    showNotification(`Aucun résultat pour "${searchTerm}"`, 'info', 2000);
                }
            }
            
            function animateFormGroup(type) {
                if (type === 'product') {
                    $('#productGroup').slideDown(300, function() {
                        loadProductsForAdjustment();
                    });
                    $('#variantGroup').slideUp(300);
                } else if (type === 'variant') {
                    $('#variantGroup').slideDown(300, function() {
                        loadVariantsForAdjustment();
                    });
                    $('#productGroup').slideUp(300);
                } else {
                    $('#productGroup').slideUp(300);
                    $('#variantGroup').slideUp(300);
                }
            }
            
            function showAdjustStockModal(itemId = null, itemType = null) {
                $('#adjustStockForm')[0].reset();
                $('#productGroup').hide();
                $('#variantGroup').hide();
                $('#currentStockInfo').text('Sélectionnez un élément pour voir les informations de stock actuelles.');
                
                if (itemId && itemType) {
                    $('#adjustItemType').val(itemType);
                    $('#adjustItemType').trigger('change');
                    
                    setTimeout(() => {
                        if (itemType === 'product') {
                            loadProductsForAdjustment().then(() => {
                                $('#adjustProductId').val(itemId).trigger('change');
                            });
                        } else if (itemType === 'variant') {
                            loadVariantsForAdjustment().then(() => {
                                $('#adjustSkuId').val(itemId).trigger('change');
                            });
                        }
                    }, 500);
                }
                
                const modal = new bootstrap.Modal(document.getElementById('adjustStockModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                modal.show();
            }
            
            function loadProductsForAdjustment() {
                return new Promise((resolve) => {
                    showLoading();
                    
                    // Simulate API call with smooth animation
                    setTimeout(() => {
                        const $select = $('#adjustProductId');
                        $select.empty().append('<option value="">Sélectionner un produit</option>');
                        
                        // Sample data
                        const sampleProducts = [
                            { id: 1, name: 'Produit Premium A', sku: 'PROD-001', stock_quantity: 125 },
                            { id: 2, name: 'Produit Standard B', sku: 'PROD-002', stock_quantity: 68 },
                            { id: 3, name: 'Produit Économique C', sku: 'PROD-003', stock_quantity: 42 },
                            { id: 4, name: 'Produit Deluxe D', sku: 'PROD-004', stock_quantity: 89 },
                            { id: 5, name: 'Produit Basique E', sku: 'PROD-005', stock_quantity: 156 }
                        ];
                        
                        sampleProducts.forEach((product, index) => {
                            setTimeout(() => {
                                $select.append(new Option(`${product.name} (${product.sku}) - Stock: ${product.stock_quantity}`, product.id));
                                if (index === sampleProducts.length - 1) {
                                    hideLoading();
                                    resolve();
                                }
                            }, index * 100);
                        });
                    }, 800);
                });
            }
            
            function loadVariantsForAdjustment() {
                return new Promise((resolve) => {
                    showLoading();
                    
                    setTimeout(() => {
                        const $select = $('#adjustSkuId');
                        $select.empty().append('<option value="">Sélectionner un variant</option>');
                        
                        const sampleVariants = [
                            { id: 1, sku: 'SKU-001', attributes: '{"Taille": "M", "Couleur": "Noir"}', stock_quantity: 25 },
                            { id: 2, sku: 'SKU-002', attributes: '{"Taille": "L", "Couleur": "Bleu"}', stock_quantity: 18 },
                            { id: 3, sku: 'SKU-003', attributes: '{"Taille": "S", "Couleur": "Rouge"}', stock_quantity: 32 },
                            { id: 4, sku: 'SKU-004', attributes: '{"Taille": "XL", "Couleur": "Vert"}', stock_quantity: 12 },
                            { id: 5, sku: 'SKU-005', attributes: '{"Taille": "M", "Couleur": "Blanc"}', stock_quantity: 45 }
                        ];
                        
                        sampleVariants.forEach((variant, index) => {
                            setTimeout(() => {
                                const attrs = JSON.parse(variant.attributes);
                                const attrText = Object.entries(attrs).map(([k, v]) => `${k}: ${v}`).join(', ');
                                $select.append(new Option(`${variant.sku} (${attrText}) - Stock: ${variant.stock_quantity}`, variant.id));
                                if (index === sampleVariants.length - 1) {
                                    hideLoading();
                                    resolve();
                                }
                            }, index * 100);
                        });
                    }, 800);
                });
            }
            
            function updateStockInfo() {
                const itemType = $('#adjustItemType').val();
                const itemId = itemType === 'product' ? $('#adjustProductId').val() : $('#adjustSkuId').val();
                const adjustmentType = $('#adjustType').val();
                const quantity = parseInt($('#adjustQuantity').val()) || 0;
                
                if (!itemId) {
                    $('#currentStockInfo').text('Sélectionnez un élément pour voir les informations de stock actuelles.');
                    return;
                }
                
                // Calculate stock with animation
                const currentStock = itemId === '1' ? 125 : itemId === '2' ? 68 : itemId === '3' ? 42 : itemId === '4' ? 89 : 156;
                let newStock = currentStock;
                
                switch (adjustmentType) {
                    case 'in':
                        newStock = currentStock + quantity;
                        break;
                    case 'out':
                        newStock = Math.max(0, currentStock - quantity);
                        break;
                    case 'adjustment':
                        newStock = Math.max(0, quantity);
                        break;
                }
                
                $('#currentStockInfo').html(`
                    <i class="fas fa-info-circle me-2"></i>
                    Stock actuel: <strong class="text-primary">${currentStock}</strong><br>
                    Quantité d'ajustement: <strong class="${adjustmentType === 'in' ? 'text-success' : adjustmentType === 'out' ? 'text-danger' : 'text-warning'}">${adjustmentType === 'in' ? '+' : adjustmentType === 'out' ? '-' : '='}${quantity}</strong><br>
                    Nouveau stock: <strong class="text-success">${newStock}</strong>
                `);
            }
            
            function animateQuickAction(element) {
                const $card = $(element);
                $card.addClass('active');
                
                // Add ripple effect
                const $ripple = $('<div class="ripple"></div>');
                $card.append($ripple);
                
                setTimeout(() => {
                    $card.removeClass('active');
                    $ripple.remove();
                }, 600);
            }
            
            function adjustItemStock(itemId, itemType) {
                showAdjustStockModal(itemId, itemType);
            }
            
            function orderMoreStock(itemId) {
                Swal.fire({
                    title: 'Commander du stock',
                    text: 'Voulez-vous créer une commande pour ce produit ?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--primary-color)',
                    cancelButtonColor: 'var(--secondary-color)',
                    confirmButtonText: 'Oui, commander',
                    cancelButtonText: 'Annuler',
                    background: 'var(--surface-color)',
                    color: 'var(--text-primary)',
                    backdrop: 'rgba(124, 58, 237, 0.1)'
                }).then((result) => {
                    if (result.isConfirmed) {
                        showNotification('Commande créée avec succès!', 'success');
                    }
                });
            }
            
            function showTransferModal() {
                Swal.fire({
                    title: 'Transfert de stock',
                    html: `
                        <div class="text-start">
                            <div class="mb-3">
                                <label class="form-label">Depuis l'emplacement</label>
                                <select class="form-control">
                                    <option>Entrepôt Principal</option>
                                    <option>Boutique A</option>
                                    <option>Boutique B</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Vers l'emplacement</label>
                                <select class="form-control">
                                    <option>Boutique A</option>
                                    <option>Boutique B</option>
                                    <option>Entrepôt Principal</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantité</label>
                                <input type="number" class="form-control" min="1" value="1">
                            </div>
                        </div>
                    `,
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: 'var(--primary-color)',
                    cancelButtonColor: 'var(--secondary-color)',
                    confirmButtonText: 'Transférer',
                    cancelButtonText: 'Annuler',
                    background: 'var(--surface-color)',
                    color: 'var(--text-primary)',
                    backdrop: 'rgba(124, 58, 237, 0.1)'
                });
            }
            
            function exportInventory() {
                showLoading();
                
                Swal.fire({
                    title: 'Exportation en cours',
                    html: 'Préparation du fichier...',
                    timer: 2000,
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                }).then(() => {
                    hideLoading();
                    Swal.fire({
                        title: 'Exportation terminée!',
                        text: 'Votre fichier a été généré avec succès.',
                        icon: 'success',
                        confirmButtonColor: 'var(--primary-color)',
                        background: 'var(--surface-color)',
                        color: 'var(--text-primary)'
                    });
                });
            }
            
            function showStockReport() {
                Swal.fire({
                    title: 'Rapports détaillés',
                    text: 'Cette fonctionnalité est en cours de développement.',
                    icon: 'info',
                    confirmButtonColor: 'var(--primary-color)',
                    background: 'var(--surface-color)',
                    color: 'var(--text-primary)'
                });
            }
            
            function showAllMovements() {
                navigateTo('./stock_movements');
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
                $(this).find('i').toggleClass('fa-bars fa-times');
            });
            
            // Close sidebar when clicking outside on mobile
            $(document).on('click', function(e) {
                if ($(window).width() <= 768) {
                    if (!$(e.target).closest('.sidebar').length && 
                        !$(e.target).closest('.sidebar-toggle').length &&
                        $('.sidebar').hasClass('show')) {
                        $('.sidebar').removeClass('show');
                        $('.sidebar-toggle i').removeClass('fa-times').addClass('fa-bars');
                    }
                }
            });
            
            // Add keyboard shortcuts
            $(document).on('keydown', function(e) {
                // Ctrl/Cmd + F for search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    $('#global-search').focus().select();
                }
                
                // Ctrl/Cmd + S for save
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    showAdjustStockModal();
                }
                
                // Escape to close modals
                if (e.key === 'Escape') {
                    $('.modal.show').modal('hide');
                }
            });
            
            // Add touch device support
            if ('ontouchstart' in window) {
                $('.action-btn, .quick-action-card, .stat-card').addClass('touch-device');
            }
            
            // Initialize tooltips
            $('[data-tooltip]').tooltip({
                trigger: 'hover',
                placement: 'top',
                animation: true
            });
            
            // Add print styles
            $('.btn-print').click(function() {
                window.print();
            });
        });

        function navigateTo(url) {
            showLoading();
            setTimeout(() => {
                window.location.href = url;
            }, 800);
        }

        function showLoading() {
            $('#loading-overlay').addClass('show');
        }

        function hideLoading() {
            $('#loading-overlay').removeClass('show');
        }

        function showNotification(message, type = 'info', duration = 5000) {
            // Remove existing notifications
            $('.notification').remove();
            
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            
            const titles = {
                success: 'Succès',
                error: 'Erreur',
                warning: 'Attention',
                info: 'Information'
            };
            
            const $notification = $(`
                <div class="notification ${type}">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas ${icons[type]} fa-2x"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold fs-5 mb-1">${titles[type]}</div>
                            <div class="text-muted">${message}</div>
                        </div>
                        <button class="btn-close btn-close-dark ms-3" onclick="$(this).closest('.notification').remove()"></button>
                    </div>
                </div>
            `);
            
            $('#notification-container').append($notification);
            
            // Animate in
            setTimeout(() => {
                $notification.addClass('show');
            }, 10);
            
            // Auto remove
            setTimeout(() => {
                $notification.removeClass('show');
                setTimeout(() => {
                    $notification.remove();
                }, 300);
            }, duration);
        }
        
        // Add CSS for ripple effect
        const style = document.createElement('style');
        style.textContent = `
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(124, 58, 237, 0.3);
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .highlight {
                background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(139, 92, 246, 0.1)) !important;
                border-left: 4px solid var(--primary-color) !important;
            }
            
            .success-animation {
                animation: successPulse 1s ease-in-out;
            }
            
            @keyframes successPulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.02); }
            }
            
            .touch-device {
                cursor: pointer;
            }
            
            .touch-device:active {
                transform: scale(0.98);
            }
            
            .modal-content.success {
                animation: successGlow 1s ease-in-out;
            }
            
            @keyframes successGlow {
                0%, 100% { box-shadow: var(--shadow-xl); }
                50% { box-shadow: 0 0 30px rgba(16, 185, 129, 0.5); }
            }
        `;
        document.head.appendChild(style);
    </script>

    <script src="../assets/js/repport.js"></script>

</body>
</html>