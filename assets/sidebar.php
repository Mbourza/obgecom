<?php 
function getInitials($fullName) {
    if (empty($fullName)) {
        return "NA"; // fallback if no name available
    }
    $parts = preg_split('/\s+/', trim($fullName));
    if (count($parts) > 1) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    } else {
        return strtoupper(mb_substr($parts[0], 0, 2));
    }
}

$name = !empty($user[0]['name']) ? $user[0]['name'] : "Utilisateur";
$role = !empty($user[0]['role']) ? $user[0]['role'] : "Administrateur";
$initials = getInitials($name);

// Check if AI Confirmation is enabled for this user
$ai_confirmation_enabled = false;
$ai_check = $db->getThisQuery(
    "SELECT ai_confirmation FROM system_settings WHERE user_id = ? AND setting_key = 'ai_confirmation'",
    [$user_id]
);

if (!empty($ai_check) && $ai_check[0]['ai_confirmation'] == 1) {
    $ai_confirmation_enabled = true;
}

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

?>

<style>
    :root {
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 80px;
        --primary-color: #9c80fd;
        --primary-gradient: linear-gradient(135deg, #5f34d9 0%, #9c80fd 100%);
        --admin-gradient: linear-gradient(135deg, #5f34d9 0%, #9c80fd 100%);
        --secondary-color: #1a202c;
        --success-color: #48bb78;
        --danger-color: #f56565;
        --warning-color: #ed8936;
        --info-color: #38b2ac;
        --white: #ffffff;
        --gray-50: #f7fafc;
        --gray-100: #edf2f7;
        --gray-200: #e2e8f0;
        --gray-300: #cbd5e0;
        --gray-700: #4a5568;
        --gray-800: #2d3748;
        --gray-900: #1f1926;
        --text-primary: #2d3748;
        --text-secondary: #718096;
        --border-color: #e2e8f0;
        --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        --radius-sm: 0.375rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
    }

    /* Subscription badge */
    .subscription-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-left: 8px;
    }

    .subscription-badge.active {
        background: rgba(72, 187, 120, 0.2);
        color: #48bb78;
        border: 1px solid rgba(72, 187, 120, 0.3);
    }

    .subscription-badge.expired {
        background: rgba(245, 101, 101, 0.2);
        color: #f56565;
        border: 1px solid rgba(245, 101, 101, 0.3);
    }

    .subscription-badge.pending {
        background: rgba(237, 137, 54, 0.2);
        color: #ed8936;
        border: 1px solid rgba(237, 137, 54, 0.3);
    }

    .subscription-badge.free {
        background: rgba(74, 85, 104, 0.2);
        color: #a0aec0;
        border: 1px solid rgba(74, 85, 104, 0.3);
    }

    /* AI Confirmation Badge */
    .ai-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-left: 8px;
        background: rgba(156, 128, 253, 0.2);
        color: #9c80fd;
        border: 1px solid rgba(156, 128, 253, 0.3);
    }

    /* Stock Badge Styles */
    .stock-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-left: 8px;
    }

    .stock-badge.in-stock {
        background: rgba(72, 187, 120, 0.2);
        color: #48bb78;
        border: 1px solid rgba(72, 187, 120, 0.3);
    }

    .stock-badge.low-stock {
        background: rgba(237, 137, 54, 0.2);
        color: #ed8936;
        border: 1px solid rgba(237, 137, 54, 0.3);
    }

    .stock-badge.out-of-stock {
        background: rgba(245, 101, 101, 0.2);
        color: #f56565;
        border: 1px solid rgba(245, 101, 101, 0.3);
    }

    /* Nav Sections */
    .nav-section {
        padding: 16px 0 8px 20px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: rgba(255, 255, 255, 0.5);
        font-weight: 600;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin: 0 12px 8px;
    }

    .sidebar.collapsed .nav-section {
        display: none;
    }

    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--gray-900);
        color: white;
        transition: var(--transition);
        z-index: 1000;
        overflow-x: hidden;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        box-shadow: var(--shadow-xl);
        backdrop-filter: blur(10px);
    }

    .sidebar.collapsed {
        width: var(--sidebar-collapsed-width);
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        min-height: 80px;
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        position: relative;
    }

    .sidebar-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--admin-gradient);
    }

    .sidebar-header h1 {
        font-size: 18px;
        font-weight: 700;
        color: white;
        white-space: nowrap;
        overflow: hidden;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sidebar.collapsed .sidebar-header h1 {
        opacity: 0;
        width: 0;
    }

    .sidebar-toggle {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: white;
        cursor: pointer;
        padding: 8px;
        border-radius: var(--radius-md);
        transition: var(--transition);
        backdrop-filter: blur(10px);
    }

    .sidebar-toggle:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }

    .sidebar-toggle i {
        font-size: 16px;
        transition: var(--transition);
    }

    .sidebar.collapsed .sidebar-toggle i {
        transform: rotate(180deg);
    }

    .user-info {
        display: flex;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.02);
    }

    .user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--admin-gradient);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        margin-right: 12px;
        flex-shrink: 0;
        box-shadow: var(--shadow-md);
        transition: var(--transition);
        font-size: 14px;
    }

    .user-avatar:hover {
        transform: scale(1.05);
        box-shadow: var(--shadow-lg);
    }

    .user-details {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: var(--transition);
    }

    .sidebar.collapsed .user-details {
        opacity: 0;
        width: 0;
    }

    .user-name {
        font-weight: 600;
        font-size: 14px;
        color: white;
        white-space: nowrap;
        margin-bottom: 2px;
        display: flex;
        align-items: center;
    }

    .user-role {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.7);
        white-space: nowrap;
    }

    .nav-menu {
        flex: 1;
        padding: 16px 0;
    }

    .nav-item {
        margin: 2px 0;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: var(--transition);
        position: relative;
        white-space: nowrap;
        border-radius: 0;
        margin: 0 12px;
        border-radius: var(--radius-md);
        font-size: 14px;
    }

    .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        transform: translateX(4px);
        box-shadow: var(--shadow-md);
    }

    .nav-link.active {
        background: var(--admin-gradient);
        color: white;
        box-shadow: var(--shadow-lg);
    }

    .nav-link.active::before {
        content: '';
        position: absolute;
        left: -12px;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 80%;
        background: white;
        border-radius: 2px;
    }

    .nav-icon {
        margin-right: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        flex-shrink: 0;
        font-size: 16px;
    }

    .nav-text {
        overflow: hidden;
        transition: var(--transition);
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }

    .sidebar.collapsed .nav-text {
        opacity: 0;
        width: 0;
    }

    .sidebar-footer {
        padding: 16px 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: auto;
        background: rgba(255, 255, 255, 0.02);
    }

    .logout-btn {
        display: flex;
        align-items: center;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        padding: 10px 0;
        transition: var(--transition);
        white-space: nowrap;
        border-radius: var(--radius-md);
        font-weight: 500;
        font-size: 14px;
    }

    .logout-btn:hover {
        color: var(--danger-color);
        transform: translateX(4px);
    }

    .logout-btn i {
        margin-right: 16px;
        width: 20px;
        flex-shrink: 0;
        font-size: 16px;
    }

    .logout-text {
        overflow: hidden;
        transition: var(--transition);
    }

    .sidebar.collapsed .logout-text {
        opacity: 0;
        width: 0;
    }

    /* Main Content */
    .main-content {
        margin-left: var(--sidebar-width);
        transition: var(--transition);
        min-height: 100vh;
        padding: 40px;
    }

    .main-content.expanded {
        margin-left: var(--sidebar-collapsed-width);
    }

    /* Tooltip for collapsed sidebar */
    .tooltip-wrapper {
        position: relative;
    }

    .sidebar.collapsed .tooltip-wrapper:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 80px;
        top: 50%;
        transform: translateY(-50%);
        background: var(--gray-800);
        color: white;
        padding: 8px 12px;
        border-radius: var(--radius-md);
        font-size: 12px;
        white-space: nowrap;
        z-index: 1001;
        box-shadow: var(--shadow-lg);
        font-weight: 500;
    }

    .sidebar.collapsed .tooltip-wrapper:hover::before {
        content: '';
        position: absolute;
        left: 75px;
        top: 50%;
        transform: translateY(-50%);
        border: 6px solid transparent;
        border-right-color: var(--gray-800);
        z-index: 1001;
    }

    /* Mobile Button */
    .mobile-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1002;
        background: var(--admin-gradient);
        color: white;
        border: none;
        padding: 10px;
        border-radius: var(--radius-md);
        cursor: pointer;
        box-shadow: var(--shadow-lg);
        align-items: center;
        justify-content: center;
        transition: var(--transition);
    }

    .mobile-toggle:hover {
        transform: scale(1.05);
    }

    .mobile-toggle i {
        font-size: 18px;
    }

    /* Sidebar Overlay */
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.6);
        z-index: 999;
        display: none;
        backdrop-filter: blur(2px);
    }

    .sidebar-overlay.active {
        display: block;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .mobile-toggle {
            display: flex !important;
        }
        
        .sidebar.mobile-active + .mobile-toggle {
            opacity: 0;
            pointer-events: none;
        }

        .sidebar {
            width: 280px;
            transform: translateX(-100%);
        }

        .sidebar.mobile-active {
            transform: translateX(0);
        }

        .sidebar.collapsed.mobile-active {
            width: 280px;
        }

        .sidebar.collapsed.mobile-active .sidebar-header h1,
        .sidebar.collapsed.mobile-active .user-details,
        .sidebar.collapsed.mobile-active .nav-text,
        .sidebar.collapsed.mobile-active .logout-text,
        .sidebar.collapsed.mobile-active .nav-section {
            opacity: 1;
            width: auto;
            display: block;
        }

        .main-content {
            margin-left: 0;
            padding: 20px;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .sidebar-toggle {
            display: none;
        }

        .sidebar.collapsed .tooltip-wrapper:hover::after,
        .sidebar.collapsed .tooltip-wrapper:hover::before {
            display: none;
        }
    }

    /* Custom scrollbar for sidebar */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }

    /* Content styling */
    .content-card {
        background: white;
        border-radius: var(--radius-xl);
        padding: 40px;
        box-shadow: var(--shadow-lg);
        margin-bottom: 24px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .page-title {
        font-size: 32px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 24px;
        background: var(--primary-gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .stat-card {
        background: white;
        border-radius: var(--radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-md);
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: var(--transition);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
    }

    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .stat-label {
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--success-color);
        margin-left: 8px;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.5; }
        100% { opacity: 1; }
    }
</style>

<!-- Mobile Toggle Button -->
<button class="mobile-toggle" id="mobileToggle">
    <i class="bi bi-list"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h1><img src="../assets/img/logo_withe.png" style="width: 90%;"/> </h1>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
    
    <div class="user-info">
        <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div class="user-details">
            <div class="user-name" style="color: #fff !important;"><?php echo htmlspecialchars($name); ?></div>
            <div class="user-role"><?php echo htmlspecialchars($role); ?></div>
        </div>
    </div>
    
    <nav class="nav-menu">
        <!-- Section 1: Tableau de Bord -->
        <div class="nav-section">Tableau de Bord</div>
        
        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Tableau de bord">
                <a href="home" class="nav-link" data-page="dashboard">
                    <span class="nav-icon">
                        <i class="bi bi-speedometer2"></i>
                    </span>
                    <span class="nav-text">TABLEAU DE BORD</span>
                </a>
            </div>
        </div>

        <!-- Section 2: Gestion des Ventes -->
        <div class="nav-section">Gestion des Ventes</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Commandes">
                <a href="orders" class="nav-link" data-page="orders">
                    <span class="nav-icon">
                        <i class="bi bi-receipt"></i>
                    </span>
                    <span class="nav-text">COMMANDES</span>
                </a>
            </div>
        </div>

        <!-- Section 3: Gestion de Stock -->
        <div class="nav-section">Gestion de Stock</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Vue d'ensemble du stock">
                <a href="stock_overview" class="nav-link" data-page="stock_overview">
                    <span class="nav-icon">
                        <i class="bi bi-bar-chart"></i>
                    </span>
                    <span class="nav-text">
                        VUE STOCK
                        <?php
                        // Default values
                        $finalStatus = 'in-stock';
                        $badgeText   = 'NORMAL';

                        if (!empty($stock_status) && is_array($stock_status)) {

                            if (in_array('out-of-stock', $stock_status, true)) {
                                $finalStatus = 'out-of-stock';
                                $badgeText   = 'RUPTURE';

                            } elseif (in_array('low-stock', $stock_status, true)) {
                                $finalStatus = 'low-stock';
                                $badgeText   = 'FAIBLE';

                            } elseif (in_array('in-stock', $stock_status, true)) {
                                $finalStatus = 'in-stock';
                                $badgeText   = 'NORMAL';
                            }

                        } elseif (is_string($stock_status)) {
                            // If it's a single value (fallback safety)
                            $finalStatus = $stock_status;

                            switch ($stock_status) {
                                case 'out-of-stock':
                                    $badgeText = 'RUPTURE';
                                    break;
                                case 'low-stock':
                                    $badgeText = 'FAIBLE';
                                    break;
                                default:
                                    $badgeText = 'NORMAL';
                            }
                        }

                        echo '<span class="stock-badge ' . $finalStatus . '">' . $badgeText . '</span>';
                        ?>
                    </span>

                </a>
            </div>
        </div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Gestion des SKUs et Variantes">
                <a href="stock_management" class="nav-link" data-page="stock_management">
                    <span class="nav-icon">
                        <i class="bi bi-tags"></i>
                    </span>
                    <span class="nav-text">VARIANTES</span>
                </a>
            </div>
        </div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Inventaire et Niveaux de Stock">
                <a href="stock_inventory" class="nav-link" data-page="stock_inventory">
                    <span class="nav-icon">
                        <i class="bi bi-boxes"></i>
                    </span>
                    <span class="nav-text">INVENTAIRE</span>
                </a>
            </div>
        </div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Alertes et Rapports Stock">
                <a href="stock_alerts" class="nav-link" data-page="stock_alerts">
                    <span class="nav-icon">
                        <i class="bi bi-bell"></i>
                    </span>
                    <span class="nav-text">ALERTES STOCK</span>
                </a>
            </div>
        </div>

        <!-- Section 4: Gestion des Produits -->
        <div class="nav-section">Produits</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Liste et synchronisation des produits">
                <a href="items" class="nav-link" data-page="items">
                    <span class="nav-icon">
                        <i class="bi bi-box-seam"></i>
                    </span>
                    <span class="nav-text">PRODUITS</span>
                </a>
            </div>
        </div>

        <!-- Section 5: Gestion de Livraison -->
        <div class="nav-section">Livraison</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Transporteurs">
                <a href="transporters" class="nav-link" data-page="transporters">
                    <span class="nav-icon">
                        <i class="bi bi-truck"></i>
                    </span>
                    <span class="nav-text">SOCIÉTÉS DE LIVRAISON</span>
                </a>
            </div>
        </div>

        <!-- Section 6: Gestion des Clients -->
        <div class="nav-section">Clients</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Clients">
                <a href="clients" class="nav-link" data-page="clients">
                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>
                    <span class="nav-text">CLIENTS</span>
                </a>
            </div>
        </div>

        <!-- Section 7: AI Confirmations (Only if enabled) -->
        <?php if ($ai_confirmation_enabled): ?>
        <div class="nav-section">Intelligence Artificielle</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Suivi des confirmations AI">
                <a href="ai_confirmations" class="nav-link" data-page="ai_confirmations">
                    <span class="nav-icon">
                        <i class="bi bi-robot"></i>
                    </span>
                    <span class="nav-text">
                        AI CONFIRMATIONS
                        <span class="ai-badge"><i class="bi bi-check-circle-fill" style="font-size: 8px; margin-right: 3px;"></i> ACTIF</span>
                    </span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Section 8: Personnel -->
        <div class="nav-section">Personnel</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Personnel">
                <a href="personnel" class="nav-link" data-page="personnel">
                    <span class="nav-icon">
                        <i class="bi bi-person-badge"></i>
                    </span>
                    <span class="nav-text">AGENTS DE CONFIRMATION</span>
                </a>
            </div>
        </div>

        <!-- Section 9: Abonnement -->
        <div class="nav-section">Abonnement</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Gérer votre abonnement">
                <a href="./subscription" class="nav-link" data-page="subscription">
                    <span class="nav-icon">
                        <i class="bi bi-credit-card"></i>
                    </span>
                    <span class="nav-text">
                        ABONNEMENT
                        <?php
                        // Display subscription status badge
                        if (isset($user[0]['subscription_status'])) {
                            $status = $user[0]['subscription_status'];
                            $badgeClass = 'subscription-badge ' . $status;
                            $badgeText = '';
                            
                            switch($status) {
                                case 'active':
                                    $badgeText = 'ACTIF';
                                    break;
                                case 'expired':
                                    $badgeText = 'EXPIRÉ';
                                    break;
                                case 'pending':
                                    $badgeText = 'EN ATTENTE';
                                    break;
                                case 'free':
                                    $badgeText = 'GRATUIT';
                                    break;
                                default:
                                    $badgeText = strtoupper($status);
                            }
                            
                            echo '<span class="' . $badgeClass . '">' . $badgeText . '</span>';
                        } else {
                            // Default badge if status not set
                            echo '<span class="subscription-badge free">NON DÉFINI</span>';
                        }
                        ?>
                    </span>
                </a>
            </div>
        </div>

        <!-- Section 10: Support -->
        <div class="nav-section">Support</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Support">
                <a href="support" class="nav-link" data-page="support">
                    <span class="nav-icon">
                        <i class="bi bi-question-circle"></i>
                    </span>
                    <span class="nav-text">SUPPORT</span>
                </a>
            </div>
        </div>

        <!-- Section 11: Paramètres -->
        <div class="nav-section">Paramètres</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Paramètres">
                <a href="settings" class="nav-link" data-page="settings">
                    <span class="nav-icon">
                        <i class="bi bi-gear"></i>
                    </span>
                    <span class="nav-text">PARAMÈTRES</span>
                </a>
            </div>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <div class="tooltip-wrapper" data-tooltip="Déconnexion">
            <a href="?logout" class="logout-btn" id="logoutBtn">
                <i class="bi bi-box-arrow-right"></i>
                <span class="logout-text">DÉCONNEXION</span>
            </a>
        </div>
    </div>
</aside>

<?php // Check if user is super admin
if ($user[0]['role'] == 'super') {
    Redirect::to('./obg/home');
} ?>

<script>
    // Enhanced State Manager with Cookie Support and Mobile Handling
    class SidebarStateManager {
        constructor() {
            this.storageKey = 'obg-ecom-sidebar-state';
            this.state = {
                collapsed: false,
                activePage: this.getCurrentPageFromURL()
            };
            this.loadState();
        }

        // Get current page from URL
        getCurrentPageFromURL() {
            const path = window.location.pathname;
            const filename = path.split('/').pop().split('.')[0];
            
            // Map PHP files to page identifiers - INCLUDING AI CONFIRMATIONS
            const pageMap = {
                'home': 'dashboard',
                'dashboard': 'dashboard',
                'orders': 'orders',
                'stock_overview': 'stock_overview',
                'stock_management': 'stock_management',
                'stock_inventory': 'stock_inventory',
                'stock_alerts': 'stock_alerts',
                'items': 'items',
                'transporters': 'transporters',
                'clients': 'clients',
                'personnel': 'personnel',
                'subscription': 'subscription',
                'support': 'support',
                'settings': 'settings',
                'ai_confirmations': 'ai_confirmations'
            };
            
            return pageMap[filename] || 'dashboard';
        }

        // Check if device is mobile
        isMobile() {
            return window.innerWidth <= 768;
        }

        // Cookie-based storage methods
        setCookie(name, value, days = 30) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Strict`;
        }

        getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) == ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        // Load state from cookies (only for desktop)
        loadState() {
            if (!this.isMobile()) {
                const savedState = this.getCookie(this.storageKey);
                if (savedState) {
                    try {
                        const parsedState = JSON.parse(savedState);
                        this.state = { ...this.state, ...parsedState };
                    } catch (e) {
                        console.warn('Failed to parse saved sidebar state:', e);
                    }
                }
            }
            
            // Always update active page based on current URL
            this.state.activePage = this.getCurrentPageFromURL();
        }

        // Save state to cookies (only for desktop)
        saveState() {
            if (!this.isMobile()) {
                this.setCookie(this.storageKey, JSON.stringify(this.state));
            }
        }

        // Update collapsed state (only affects desktop)
        setCollapsed(collapsed) {
            if (!this.isMobile()) {
                this.state.collapsed = collapsed;
                this.saveState();
            }
        }

        // Update active page
        setActivePage(page) {
            this.state.activePage = page;
            this.saveState();
        }

        // Get current state
        getState() {
            return { ...this.state };
        }

        // Clear saved state
        clearState() {
            this.setCookie(this.storageKey, '', -1);
            this.state = {
                collapsed: false,
                activePage: this.getCurrentPageFromURL()
            };
        }
    }

    // Initialize the application
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize state manager
        const stateManager = new SidebarStateManager();
        
        // Get DOM elements
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const navLinks = document.querySelectorAll('.nav-link');

        // State management functions
        function applySidebarState() {
            const state = stateManager.getState();
            
            // Only apply collapsed state on desktop
            if (!stateManager.isMobile() && state.collapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            } else if (!stateManager.isMobile()) {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        }

        function applyActivePageState() {
            const state = stateManager.getState();
            
            // Update active nav link based on current page
            navLinks.forEach(link => {
                const pageId = link.getAttribute('data-page');
                if (pageId === state.activePage) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }

        // Toggle sidebar collapse (desktop only)
        function toggleSidebar() {
            if (stateManager.isMobile()) return; // Don't toggle on mobile
            
            const currentState = stateManager.getState();
            const newCollapsedState = !currentState.collapsed;
            
            stateManager.setCollapsed(newCollapsedState);
            applySidebarState();
            
            // Update toggle button icon
            const toggleIcon = sidebarToggle.querySelector('i');
            toggleIcon.style.transform = newCollapsedState ? 'rotate(180deg)' : 'rotate(0deg)';
            
            console.log('Sidebar toggled:', newCollapsedState ? 'collapsed' : 'expanded');
        }

        // Mobile sidebar functions
        function showMobileSidebar() {
            sidebar.classList.add('mobile-active');
            sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function hideMobileSidebar() {
            sidebar.classList.remove('mobile-active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Event listeners
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }

        if (mobileToggle) {
            mobileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                showMobileSidebar();
            });
        }

        // Close mobile sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', hideMobileSidebar);
        }

        // Close mobile sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (stateManager.isMobile() && 
                sidebar.classList.contains('mobile-active') &&
                !sidebar.contains(e.target) &&
                e.target !== mobileToggle) {
                hideMobileSidebar();
            }
        });

        // Prevent clicks inside sidebar from closing it
        sidebar.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Navigation handling
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Update the active page state
                const pageId = this.getAttribute('data-page');
                if (pageId) {
                    stateManager.setActivePage(pageId);
                }
                
                // Close mobile sidebar when navigating
                if (stateManager.isMobile()) {
                    hideMobileSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            // If switching from mobile to desktop, ensure mobile sidebar is closed
            if (window.innerWidth > 768) {
                hideMobileSidebar();
                // Reapply desktop state
                applySidebarState();
            } else {
                // On mobile, remove collapsed state and main content expansion
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        });

        // Handle escape key to close mobile sidebar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && stateManager.isMobile() && sidebar.classList.contains('mobile-active')) {
                hideMobileSidebar();
            }
        });

        // Initialize the application
        function initializeApp() {
            // Only apply sidebar state on desktop
            if (!stateManager.isMobile()) {
                applySidebarState();
                
                // Sync toggle button state with actual sidebar state
                const state = stateManager.getState();
                const toggleIcon = sidebarToggle.querySelector('i');
                if (state.collapsed) {
                    toggleIcon.style.transform = 'rotate(180deg)';
                } else {
                    toggleIcon.style.transform = 'rotate(0deg)';
                }
            }
            
            applyActivePageState();
            
            console.log('Application initialized with state:', stateManager.getState());
        }

        // Start the application
        initializeApp();

        // Expose utilities for debugging
        window.sidebarStateManager = stateManager;
        window.resetSidebarState = function() {
            stateManager.clearState();
            initializeApp();
            console.log('Sidebar state reset to default');
        };

        console.log('Enhanced sidebar with mobile support loaded!');
        console.log('Available debug commands:');
        console.log('- window.sidebarStateManager.getState() - Show current state');
        console.log('- window.resetSidebarState() - Reset to default state');
    });
</script>