<?php 
function getInitials($fullName) {
    if (empty($fullName)) {
        return "SA"; // Super Admin fallback
    }
    $parts = preg_split('/\s+/', trim($fullName));
    if (count($parts) > 1) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    } else {
        return strtoupper(mb_substr($parts[0], 0, 2));
    }
}

$name = !empty($user[0]['name']) ? $user[0]['name'] : "Super Admin";
$role = !empty($user[0]['role']) ? $user[0]['role'] : "Super Administrateur";
$initials = getInitials($name);

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

    /* Super Admin Specific Styles */
    .super-admin-badge {
        background: var(--admin-gradient);
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-left: 8px;
    }

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
</style>

<!-- Mobile Toggle Button -->
<button class="mobile-toggle" id="mobileToggle">
    <i class="bi bi-list"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h1>
            <img src="../../assets/img/logo_withe.png" style="height: 30px;"/> 
            <span class="super-admin-badge">SUPER ADMIN</span>
        </h1>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
    
    <div class="user-info">
        <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
        <div class="user-details">
            <div class="user-name" style="color: #fff !important;">
                <?php echo htmlspecialchars($name); ?>
                <span class="super-admin-badge" style="margin-left: 8px; font-size: 8px; padding: 2px 6px;">SA</span>
            </div>
            <div class="user-role"><?php echo htmlspecialchars($role); ?></div>
        </div>
    </div>
    
    <nav class="nav-menu">
        <!-- Section 1: Accueil/Dashboard -->
        <div class="nav-section">Tableau de Bord</div>
        
        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Tableau de Bord Principal">
                <a href="home" class="nav-link" data-page="super_admin">
                    <span class="nav-icon">
                        <i class="bi bi-speedometer2"></i>
                    </span>
                    <span class="nav-text">ACCUEIL</span>
                </a>
            </div>
        </div>

        <!-- Section 2: Utilisateurs -->
        <div class="nav-section">Gestion Utilisateurs</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Gestion des Utilisateurs">
                <a href="users" class="nav-link" data-page="users">
                    <span class="nav-icon">
                        <i class="bi bi-people"></i>
                    </span>
                    <span class="nav-text">UTILISATEURS</span>
                </a>
            </div>
        </div>

        <!-- Section 3: Abonnements -->
        <div class="nav-section">Abonnements</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Gestion des Abonnements">
                <a href="subscriptions" class="nav-link" data-page="subscriptions">
                    <span class="nav-icon">
                        <i class="bi bi-credit-card"></i>
                    </span>
                    <span class="nav-text">ABONNEMENTS</span>
                </a>
            </div>
        </div>

        <!-- Section 4: Finance -->
        <div class="nav-section">Finances</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltipTableau Financier">
                <a href="finances" class="nav-link" data-page="finances">
                    <span class="nav-icon">
                        <i class="bi bi-graph-up"></i>
                    </span>
                    <span class="nav-text">FINANCES</span>
                </a>
            </div>
        </div>

        <!-- Section 5: Stores -->
        <div class="nav-section">Boutiques</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Gestion des Boutiques">
                <a href="stores" class="nav-link" data-page="stores">
                    <span class="nav-icon">
                        <i class="bi bi-shop"></i>
                    </span>
                    <span class="nav-text">STORES CONNECTÉS</span>
                </a>
            </div>
        </div>

        <!-- Section 6: Support -->
        <div class="nav-section">Support</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Gestion du Support">
                <a href="support" class="nav-link" data-page="support">
                    <span class="nav-icon">
                        <i class="bi bi-headset"></i>
                    </span>
                    <span class="nav-text">SUPPORT</span>
                </a>
            </div>
        </div>

        <!-- Section 8: Paramètres -->
        <div class="nav-section">Administration</div>

        <div class="nav-item">
            <div class="tooltip-wrapper" data-tooltip="Paramètres Système">
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

<script>
    // Enhanced State Manager for Super Admin
    class SuperAdminSidebarStateManager {
        constructor() {
            this.storageKey = 'obg-super-admin-sidebar-state';
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
            
            // Map PHP files to page identifiers for super admin
            const pageMap = {
                'super_admin': 'super_admin',
                'users': 'users',
                'subscriptions': 'subscriptions',
                'finances': 'finances',
                'stores': 'stores',
                'support': 'support',
                'settings': 'settings'
            };
            
            return pageMap[filename] || 'super_admin';
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

    // Initialize the super admin application
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize state manager
        const stateManager = new SuperAdminSidebarStateManager();
        
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
            
            console.log('Super Admin Sidebar toggled:', newCollapsedState ? 'collapsed' : 'expanded');
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
                    applyActivePageState();
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
            
            console.log('Super Admin Application initialized with state:', stateManager.getState());
        }

        // Start the application
        initializeApp();

        // Expose utilities for debugging
        window.superAdminStateManager = stateManager;
        window.resetSuperAdminSidebarState = function() {
            stateManager.clearState();
            initializeApp();
            console.log('Super Admin Sidebar state reset to default');
        };

        console.log('Super Admin sidebar with enhanced management loaded!');
    });
</script>