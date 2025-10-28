<?php ?>

<div class="settings-section" id="api">
    <div class="settings-card">
        <style>
            .settings-section2 {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 0px;
                padding: 30px;
                margin-bottom: 30px;
            }

            .section-header {
                display: flex;
                align-items: center;
                justify-content: between;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f0f0;
            }

            .section-title {
                font-size: 1.8rem;
                font-weight: 700;
                color: #2c3e50;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .api-icon {
                width: 32px;
                height: 32px;
                background: linear-gradient(45deg, #667eea, #764ba2);
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }

            label {
                display: block;
                font-weight: 600;
                color: #34495e;
                margin-bottom: 8px;
                font-size: 0.95rem;
            }

            input[type="text"], input[type="url"], input[type="password"], input[type="number"], select {
                width: 100%;
                padding: 12px 16px;
                border: 2px solid #e1e8ed;
                border-radius: 10px;
                font-size: 1rem;
                transition: all 0.3s ease;
                background: white;
            }

            input:focus, select:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .api-key-display {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 0px;
                margin-top: 10px;
                border-left: 4px solid #667eea;
                font-family: 'Courier New', monospace;
                font-size: 0.9rem;
            }

            small {
                color: #7f8c8d;
                font-size: 0.85rem;
                display: block;
                margin-top: 5px;
            }

            /* Store Management Styles */
            .store-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }

            .store-card {
                background: white;
                border-radius: 0px;
                padding: 20px;
                border: 1px solid #e1e8ed;
                transition: all 0.3s ease;
                position: relative;
            }

            .store-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            }

            .store-card.active {
                border-color: #fbfbfb;
                background: linear-gradient(135deg, #ffffff 0%, #ffffff 100%);
            }

            .store-card.inactive {
                border-color: #e74c3c;
                background: linear-gradient(135deg, #ffffff 0%, #fff 100%);
            }

            .store-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
            }

            .store-platform {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                text-transform: uppercase;
            }

            .store-platform.shopify { background: #95bf47; color: white; }
            .store-platform.woocommerce { background: #96588a; color: white; }
            .store-platform.youcan { background: #1a73e8; color: white; }

            .store-name {
                font-size: 1.2rem;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 5px;
            }

            .store-domain {
                color: #7f8c8d;
                font-size: 0.9rem;
                margin-bottom: 10px;
            }

            .store-actions {
                display: flex;
                gap: 10px;
            }

            .btn {
                padding: 8px 16px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                font-size: 0.9rem;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }

            .btn-primary {
                background: linear-gradient(45deg, #3f51b5, #3f51b5);
                color: white;
            }

            .btn-success {
                background: #27ae60;
                color: white;
            }

            .btn-danger {
                background: #e74c3c;
                color: white;
            }

            .btn-secondary {
                background: #95a5a6;
                color: white;
            }

            .btn-warning {
                background: #f39c12;
                color: white;
            }

            .btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            }

            .btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }

            .add-store-section {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 15px;
                padding: 25px;
                margin-bottom: 25px;
                border: 2px dashed #dee2e6;
            }

            .platform-selector {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }

            .platform-option {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 15px;
                border: 2px solid #e1e8ed;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.3s ease;
                background: white;
            }

            .platform-option:hover {
                border-color: #667eea;
                transform: translateY(-2px);
            }

            .platform-option.selected {
                border-color: #667eea;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .platform-logo {
                width: 40px;
                height: 40px;
                border-radius: 8px;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 0.8rem;
            }

            .toggle-switch {
                position: relative;
                display: inline-block;
                width: 50px;
                height: 24px;
            }

            .toggle-switch input {
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
                background-color: #ccc;
                transition: .4s;
                border-radius: 24px;
            }

            .slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }

            input:checked + .slider {
                background-color: #27ae60;
            }

            input:checked + .slider:before {
                transform: translateX(26px);
            }

            .status-indicator {
                position: absolute;
                top: 15px;
                right: 15px;
                width: 12px;
                height: 12px;
                border-radius: 50%;
            }

            .status-active { background: #27ae60; }
            .status-inactive { background: #e74c3c; }

            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(5px);
            }

            .modal-content {
                background-color: white;
                margin: 5% auto;
                border-radius: 0px;
                width: 90%;
                max-width: 600px;
                max-height: 80vh;
                overflow-y: auto;
            }

            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }

            .close:hover {
                color: #000;
            }

            .plan-limit-info {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 20px;
                border-left: 4px solid #667eea;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .limit-text {
                font-size: 0.95rem;
                color: #2c3e50;
            }

            .limit-count {
                font-weight: 700;
                color: #2c3e50;
            }

            .limit-reached {
                background: #ffeaa7;
                border-left-color: #fdcb6e;
            }

            @media (max-width: 768px) {
                .form-row {
                    grid-template-columns: 1fr;
                    gap: 15px;
                }
                
                .store-grid {
                    grid-template-columns: 1fr;
                }
                
                .platform-selector {
                    grid-template-columns: repeat(2, 1fr);
                }
                
                .plan-limit-info {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 10px;
                }

                .section-header {
                    flex-direction: column;
                }
            }
        </style>

        <!-- Store Management Section -->
        <div class="settings-section2">
            <div class="section-header">
                <h2 class="section-title">
                    <div class="api-icon">🏪</div>
                    Gestion des Boutiques
                </h2>
                <button class="btn btn-primary" id="add-store-btn" onclick="openAddStoreModal()">
                    + Ajouter une boutique
                </button>
            </div>

            <!-- Plan Limit Information -->
            <div class="plan-limit-info" id="plan-limit-info">
                <div class="limit-text">
                    Votre plan actuel vous permet de connecter jusqu'à <span class="limit-count" id="max-stores">5</span> boutiques.
                </div>
                <div>
                    Boutiques connectées: <span class="limit-count" id="current-stores">0</span>/<span class="limit-count" id="max-stores-count">5</span>
                </div>
            </div>

            <!-- Add Store Section -->
            <div class="add-store-section" id="add-store-section" style="display: none;">
                <h3>Ajouter une nouvelle boutique</h3>
                <form id="add-store-form">
                    <div class="platform-selector">
                        <div class="platform-option" data-platform="shopify">
                            <div class="platform-logo" style="background: #95bf47;">S</div>
                            <span>Shopify</span>
                        </div>
                        <div class="platform-option" data-platform="woocommerce">
                            <div class="platform-logo" style="background: #96588a;">WC</div>
                            <span>WooCommerce</span>
                        </div>
                        <div class="platform-option" data-platform="youcan">
                            <div class="platform-logo" style="background: #1a73e8;">YC</div>
                            <span>YouCan</span>
                        </div>
                    </div>

                    <div id="store-credentials-form" style="display: none;">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="store-name">Nom de la boutique</label>
                                <input type="text" id="store-name" name="store-name" placeholder="Ma Boutique">
                            </div>
                            <div class="form-group">
                                <label for="store-domain">Domaine</label>
                                <input type="text" id="store-domain" name="store-domain" placeholder="monsite.com">
                            </div>
                        </div>

                        <div id="manual-credentials" style="display: none;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="consumer-key">Consumer Key</label>
                                    <input type="text" id="consumer-key" name="consumer-key" placeholder="ck_xxxxxxxxxxxxxx">
                                </div>
                                <div class="form-group">
                                    <label for="consumer-secret">Consumer Secret</label>
                                    <input type="password" id="consumer-secret" name="consumer-secret" placeholder="cs_xxxxxxxxxxxxxx">
                                </div>
                            </div>
                        </div>

                        <!-- Platform-specific OAuth sections -->
                        <div id="shopify-oauth-section" style="display: none;">
                            <div class="form-group">
                                <button type="button" class="btn btn-primary" id="shopify-oauth-btn">
                                    🔗 Se connecter à Shopify
                                </button>
                                <small>Vous serez redirigé vers Shopify pour autoriser l'accès</small>
                            </div>
                        </div>

                        <div id="youcan-oauth-section" style="display: none;">
                            <div class="form-group">
                                <button type="button" class="btn btn-primary" id="youcan-oauth-btn">
                                    🔗 Se connecter à YouCan
                                </button>
                                <small>Vous serez redirigé vers YouCan pour autoriser l'accès</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <button type="button" class="btn btn-secondary" onclick="cancelAddStore()">Annuler</button>
                            <button type="submit" class="btn btn-success">Ajouter la boutique</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Active Stores Grid -->
            <div class="store-grid" id="stores-grid">
                <!-- Example stores will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            margin: 5% auto;
            padding: 0;
            border: none;
            border-radius: 0px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899);
        }

        .modal-content > h2 {
            padding: .5em;
        }

        .modal-header {
            padding: 28px 32px 20px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            position: relative;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -0.5px;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 300;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: 8px;
            background: transparent;
        }

        .close:hover {
            background: #f1f5f9;
            color: #1e293b;
            transform: rotate(90deg);
        }

        #modal-content {
            background: white;
            max-height: 70vh;
            overflow-y: auto;
        }

        #modal-content::-webkit-scrollbar {
            width: 8px;
        }

        #modal-content::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        #modal-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        #modal-content::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>

    <!-- Modal for store details -->
    <div id="storeModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Détails de la boutique</h2>
            <div id="modal-content"></div>
        </div>
    </div>

</div>

<script>
    // Global variables
    let stores = [];
    let selectedPlatform = null;
    let userLimits = { current: 0, max: 5 };
    let userPlanDetails = {};

    let userPlanLimits = {
        maxStores: 0,
        currentStores: 0,
        planName: '',
        isUnlimited: false
    };

    // Initialize the application when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeApp();
    });

    async function initializeApp() {
        try {
            await loadUserLimits();
            await loadStores();
            setupEventListeners();
            renderStores();
        } catch (error) {
            console.error('Error initializing app:', error);
            showAlert('Erreur lors de l\'initialisation de l\'application', 'danger');
        }
    }

    function setupEventListeners() {
        // Platform selection listeners
        document.querySelectorAll('.platform-option').forEach(option => {
            option.addEventListener('click', function() {
                selectPlatform(this.dataset.platform);
            });
        });

        // Form submission listener
        const addStoreForm = document.getElementById('add-store-form');
        if (addStoreForm) {
            addStoreForm.addEventListener('submit', function(e) {
                e.preventDefault();
                handleFormSubmit();
            });
        }

        // Shopify OAuth button listener
        const shopifyOAuthBtn = document.getElementById('shopify-oauth-btn');
        if (shopifyOAuthBtn) {
            shopifyOAuthBtn.addEventListener('click', initiateShopifyOAuth);
        }

        // YouCan OAuth button listener
        const youcanOAuthBtn = document.getElementById('youcan-oauth-btn');
        if (youcanOAuthBtn) {
            youcanOAuthBtn.addEventListener('click', initiateYouCanOAuth);
        }

        // Modal close listeners
        const modal = document.getElementById('storeModal');
        const closeBtn = modal?.querySelector('.close');
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    // User limits management
    async function loadUserLimits() {
        try {
            const response = await fetch('./controllers/get_user_limits.php');
            const data = await response.json();
            
            if (data.success) {
                userLimits = data.limits;
                userPlanDetails = data.plan_details || {};
                updateLimitDisplay();
            } else {
                // Fallback to default limits if API fails
                userLimits = { current: 0, max: 5 };
                userPlanDetails = {};
            }
        } catch (error) {
            console.error('Error loading user limits:', error);
            // Use default limits on error
            userLimits = { current: 0, max: 5 };
            userPlanDetails = {};
        }
    }

    function updateLimitDisplay() {
        const currentStoresElement = document.getElementById('current-stores');
        const maxStoresElement = document.getElementById('max-stores');
        const maxStoresCountElement = document.getElementById('max-stores-count');
        const planLimitInfo = document.getElementById('plan-limit-info');
        
        if (currentStoresElement) currentStoresElement.textContent = userLimits.current;
        if (maxStoresElement) maxStoresElement.textContent = userLimits.max;
        if (maxStoresCountElement) maxStoresCountElement.textContent = userLimits.max;
        
        // Update plan info styling if limit reached
        if (planLimitInfo) {
            if (userLimits.current >= userLimits.max) {
                planLimitInfo.classList.add('limit-reached');
            } else {
                planLimitInfo.classList.remove('limit-reached');
            }
        }
        
        // Update add button state
        const addBtn = document.getElementById('add-store-btn');
        if (addBtn) {
            if (userLimits.current >= userLimits.max) {
                addBtn.disabled = true;
                addBtn.innerHTML = '⚠️ Limite atteinte';
                addBtn.classList.add('btn-secondary');
                addBtn.classList.remove('btn-primary');
            } else {
                addBtn.disabled = false;
                addBtn.innerHTML = '+ Ajouter une boutique';
                addBtn.classList.add('btn-primary');
                addBtn.classList.remove('btn-secondary');
            }
        }
    }

    // Store loading and management
    async function loadStores() {
        try {
            const response = await fetch('./controllers/get_storesFrom_db.php');
            const data = await response.json();
            
            if (data.success) {
                stores = data.stores || [];
                userLimits.current = stores.length;
                updateLimitDisplay();
                renderStores();
            } else {
                throw new Error(data.message || 'Failed to load stores');
            }
        } catch (error) {
            console.error('Error loading stores:', error);
            showAlert('Erreur lors du chargement des boutiques', 'danger');
            stores = []; // Fallback to empty array
            renderStores();
        }
    }

    // Platform selection
    function selectPlatform(platform) {
        // Check if user has reached limit
        if (userLimits.current >= userLimits.max) {
            showAlert('Vous avez atteint la limite de votre plan. Mettez à niveau pour ajouter plus de boutiques.', 'warning');
            return;
        }

        selectedPlatform = platform;

        // Update UI - remove selection from all platforms
        document.querySelectorAll('.platform-option').forEach(option => {
            option.classList.remove('selected');
        });

        // Add selection to clicked platform
        const selectedOption = document.querySelector(`[data-platform="${platform}"]`);
        if (selectedOption) {
            selectedOption.classList.add('selected');
        }

        // Show credentials form
        const credentialsForm = document.getElementById('store-credentials-form');
        if (credentialsForm) {
            credentialsForm.style.display = 'block';
        }

        // Configure form based on platform
        const manualCredentials = document.getElementById('manual-credentials');
        const shopifyOAuthSection = document.getElementById('shopify-oauth-section');
        const youcanOAuthSection = document.getElementById('youcan-oauth-section');

        // Hide all sections first
        if (manualCredentials) manualCredentials.style.display = 'none';
        if (shopifyOAuthSection) shopifyOAuthSection.style.display = 'none';
        if (youcanOAuthSection) youcanOAuthSection.style.display = 'none';

        // Show appropriate section based on platform
        if (platform === 'woocommerce') {
            if (manualCredentials) manualCredentials.style.display = 'block';
        } else if (platform === 'shopify') {
            if (shopifyOAuthSection) shopifyOAuthSection.style.display = 'block';
        } else if (platform === 'youcan') {
            if (youcanOAuthSection) youcanOAuthSection.style.display = 'block';
        }
    }

    // Modal management
    function openAddStoreModal() {
        // Check if user has reached limit
        if (userLimits.current >= userLimits.max) {
            showAlert('Vous avez atteint la limite de votre plan. Mettez à niveau pour ajouter plus de boutiques.', 'warning');
            return;
        }
        
        const section = document.getElementById('add-store-section');
        if (section) {
            section.style.display = 'block';
            section.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function closeAddStoreModal() {
        const section = document.getElementById('add-store-section');
        if (section) {
            section.style.display = 'none';
        }
        resetForm();
    }

    function cancelAddStore() {
        closeAddStoreModal();
    }

    function resetForm() {
        const form = document.getElementById('add-store-form');
        if (form) {
            form.reset();
        }
        
        const credentialsForm = document.getElementById('store-credentials-form');
        if (credentialsForm) {
            credentialsForm.style.display = 'none';
        }
        
        // Reset platform selection
        document.querySelectorAll('.platform-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        selectedPlatform = null;
    }

    // Form submission handling
    async function handleFormSubmit() {
        if (!selectedPlatform) {
            showAlert('Veuillez sélectionner une plateforme', 'warning');
            return;
        }

        const submitBtn = document.querySelector('button[type="submit"]');
        const originalText = submitBtn?.innerHTML;
        
        // Show loading state
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Traitement...';
        }

        try {
            if (selectedPlatform === 'woocommerce') {
                await addWooCommerceStore();
            } else {
                // For Shopify and YouCan, the OAuth process is already handled separately
                showAlert('Veuillez utiliser le bouton de connexion OAuth pour ' + selectedPlatform, 'info');
            }
        } catch (error) {
            console.error('Error adding store:', error);
            showAlert('Erreur lors de l\'ajout de la boutique', 'danger');
        } finally {
            // Reset button state
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText || 'Ajouter la boutique';
            }
        }
    }

    // WooCommerce store addition
    async function addWooCommerceStore() {
        const form = document.getElementById('add-store-form');
        const formData = new FormData(form);
        
        const storeData = {
            platform: 'woocommerce',
            store_name: formData.get('store-name'),
            domain: formData.get('store-domain'),
            consumer_key: formData.get('consumer-key'),
            consumer_secret: formData.get('consumer-secret'),
            api_url: formData.get('api-url') || `https://${formData.get('store-domain')}/wp-json/wc/v3/`
        };

        // Validate required fields
        if (!storeData.store_name || !storeData.domain || !storeData.consumer_key || !storeData.consumer_secret) {
            showAlert('Veuillez remplir tous les champs requis', 'warning');
            return;
        }

        // Validate WooCommerce connection
        const isValid = await validateWooCommerceConnection(storeData);
        if (!isValid) {
            showAlert('Impossible de se connecter à la boutique WooCommerce. Vérifiez vos identifiants.', 'danger');
            return;
        }

        try {
            const response = await fetch('./controllers/add_storeTo_db.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(storeData)
            });

            const result = await response.json();
            
            if (result.success) {
                showAlert('Boutique WooCommerce ajoutée avec succès!', 'success');
                closeAddStoreModal();
                await loadStores(); // Reload stores
            } else {
                showAlert(result.message || 'Erreur lors de l\'ajout de la boutique', 'danger');
            }
        } catch (error) {
            console.error('Error adding WooCommerce store:', error);
            showAlert('Erreur de communication avec le serveur', 'danger');
        }
    }

    function normalizeDomain(domain) {
        domain = domain.trim();
        
        // Remove http:// or https:// protocol if present
        domain = domain.replace(/^https?:\/\//, '');
        
        // Remove any trailing slashes
        domain = domain.replace(/\/+$/, '');
        
        // Remove any path segments (e.g., /admin)
        domain = domain.split('/')[0];
        
        // Add .myshopify.com if no domain extension present
        if (!domain.includes('.')) {
            domain += '.myshopify.com';
        }
        
        return domain;
    }

    // Shopify store auth
    async function initiateShopifyOAuth() {
        const storeName = document.getElementById('store-name').value.trim();
        const rawDomain = document.getElementById('store-domain').value.trim();
        const domain = normalizeDomain(rawDomain);

        if (!storeName || !rawDomain) {
            alert('Veuillez entrer le nom et le domaine de la boutique.');
            return;
        }

        try {
            const res = await fetch('./controllers/shopify_oauth_redirect.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({store_name: storeName, domain})
            });
            const data = await res.json();

            if (data.success && data.install_url) {
                window.location.href = data.install_url; // redirect user to Shopify OAuth
            } else {
                alert(data.message || 'Erreur lors de la génération du lien OAuth.');
            }
        } catch (err) {
            console.error(err);
            alert('Erreur réseau lors de la tentative OAuth.');
        }
    }

    // YouCan store auth
    async function initiateYouCanOAuth() {
        const storeName = document.getElementById('store-name').value.trim();
        const rawDomain = document.getElementById('store-domain').value.trim();
        const domain = rawDomain.trim();

        if (!storeName || !domain) {
            alert('Veuillez entrer le nom et le domaine de la boutique YouCan.');
            return;
        }

        try {
            showAlert('Initialisation de la connexion YouCan...', 'info');
            
            // Call YouCan OAuth initiation endpoint
            const res = await fetch('./controllers/youcan_oauth_redirect.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    store_name: storeName,
                    domain: domain
                })
            });
            
            const data = await res.json();

            if (data.success && data.auth_url) {
                // Redirect to YouCan OAuth
                window.location.href = data.auth_url;
            } else {
                showAlert(data.message || 'Erreur lors de la connexion à YouCan', 'danger');
            }
        } catch (error) {
            console.error('YouCan OAuth error:', error);
            showAlert('Erreur réseau lors de la connexion à YouCan', 'danger');
        }
    }

    // WooCommerce connection validation
    async function validateWooCommerceConnection(storeData) {
        try {
            const response = await fetch('./controllers/woocommerce_proxy.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(storeData)
            });

            const result = await response.json();
            return result.success;
        } catch (error) {
            console.error("WooCommerce connection error:", error);
            return false;
        }
    }

    // Store rendering
    function renderStores() {
        const grid = document.getElementById('stores-grid');
        if (!grid) return;

        grid.innerHTML = '';

        if (stores.length === 0) {
            grid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #7f8c8d;">
                    <h3>Aucune boutique configurée</h3>
                    <p>Ajoutez votre première boutique pour commencer</p>
                </div>
            `;
            return;
        }

        stores.forEach(store => {
            const storeCard = createStoreCard(store);
            grid.appendChild(storeCard);
        });
    }

    function createStoreCard(store) {
        const storeCard = document.createElement('div');
        const isConnected = store.is_connected || store.status === 'active';
        const connectionStatus = isConnected ? 'Connecté' : 'Déconnecté';
        const statusClass = isConnected ? 'active' : 'inactive';
        const lastConnection = store.connected_at ? 
            new Date(store.connected_at).toLocaleString('fr-FR') : 
            (store.lastSync ? store.lastSync : 'Jamais');
        
        storeCard.className = `store-card ${statusClass}`;
        
        storeCard.innerHTML = `
            <div class="status-indicator status-${statusClass}"></div>
            <div class="store-header">
                <span class="store-platform ${store.platform}">${store.platform}</span>
                ${store.logo_url ? `<img src="${store.logo_url}" alt="Logo" style="width: 30px; height: 30px; border-radius: 5px;">` : ''}
            </div>
            <div class="store-name">${store.store_name || store.name}</div>
            <div class="store-domain">${store.domain}</div>
            <div class="connection-status">
                <span style="color: ${isConnected ? '#27ae60' : '#e74c3c'}">
                    ● ${connectionStatus}
                </span>
            </div>
            <div style="margin: 10px 0; font-size: 0.85rem; color: #7f8c8d;">
                Dernière connexion: ${lastConnection}
            </div>
            <div class="store-actions">
                ${!isConnected ? 
                    `<button class="btn btn-success" onclick="connectStore(${store.id})">
                        🔗 Connecter
                    </button>` : 
                    `<button class="btn btn-warning" onclick="disconnectStore(${store.id})">
                        ⚠️ Déconnecter
                    </button>`
                }
                <button class="btn btn-secondary" onclick="viewStoreDetails(${store.id})">
                    📊 Détails
                </button>
                <button class="btn btn-danger" onclick="deleteStore(${store.id})">
                    🗑️ Supprimer
                </button>
            </div>
        `;
        
        return storeCard;
    }

    // Store actions
    async function connectStore(storeId) {
        const store = stores.find(s => s.id === storeId);
        if (!store) return;

        try {
            showAlert('Connexion à la boutique...', 'info');
            
            // Platform-specific connection logic
            if (store.platform === 'shopify') {
                await reconnectShopifyStore(store);
            } else if (store.platform === 'youcan') {
                await reconnectYouCanStore(store);
            } else if (store.platform === 'woocommerce') {
                await reconnectWooCommerceStore(store);
            } else {
                // Generic connection for other platforms
                await new Promise(resolve => setTimeout(resolve, 1500));
                store.is_connected = true;
                store.connected_at = new Date().toISOString();
            }
            
            showAlert('Boutique connectée avec succès!', 'success');
            renderStores();

        } catch (error) {
            console.error('Error connecting store:', error);
            showAlert('Erreur lors de la connexion', 'danger');
        }
    }

    async function reconnectShopifyStore(store) {
        // Implement Shopify reconnection logic
        // This would typically involve re-initiating OAuth
        const res = await fetch('./controllers/shopify_reconnect.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ store_id: store.id, domain: store.domain })
        });
        
        const data = await res.json();
        
        if (data.success) {
            store.is_connected = true;
            store.connected_at = new Date().toISOString();
        } else {
            throw new Error(data.message || 'Shopify reconnection failed');
        }
    }

    async function reconnectYouCanStore(store) {
        // Implement YouCan reconnection logic
        const res = await fetch('./controllers/youcan_reconnect.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ store_id: store.id, domain: store.domain })
        });
        
        const data = await res.json();
        
        if (data.success) {
            store.is_connected = true;
            store.connected_at = new Date().toISOString();
        } else {
            throw new Error(data.message || 'YouCan reconnection failed');
        }
    }

    async function reconnectWooCommerceStore(store) {
        // Implement WooCommerce reconnection logic
        const res = await fetch('./controllers/woocommerce_reconnect.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ store_id: store.id })
        });
        
        const data = await res.json();
        
        if (data.success) {
            store.is_connected = true;
            store.connected_at = new Date().toISOString();
        } else {
            throw new Error(data.message || 'WooCommerce reconnection failed');
        }
    }

    async function disconnectStore(storeId) {
        const store = stores.find(s => s.id === storeId);
        if (!store) return;

        if (!confirm('Êtes-vous sûr de vouloir déconnecter cette boutique?')) {
            return;
        }

        try {
            // Platform-specific disconnect logic
            const endpoint = `./controllers/${store.platform}_disconnect.php`;
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    store_id: storeId
                })
            });

            const data = await response.json();

            if (data.success) {
                // Update the local store object
                store.is_connected = false;
                store.connected_at = null;
                
                showAlert('Boutique déconnectée avec succès', 'success');
                renderStores();
            } else {
                throw new Error(data.message || 'Failed to disconnect store');
            }
        } catch (error) {
            console.error('Error disconnecting store:', error);
            showAlert('Erreur lors de la déconnexion: ' + error.message, 'danger');
        }
    }

    function viewStoreDetails(storeId) {
        const store = stores.find(s => s.id === storeId);
        if (!store) return;

        const modal = document.getElementById('storeModal');
        const modalContent = document.getElementById('modal-content');
        
        if (!modal || !modalContent) return;

        const isConnected = store.is_connected || store.status === 'active';
        const connectionStatus = isConnected ? 'Connecté' : 'Déconnecté';
        const lastSync = store.lastSync || store.connected_at || 'Jamais';

        modalContent.innerHTML = `
            <div style="padding: 24px;">
                <!-- Header Section -->
                <div style="margin-bottom: 32px; padding-bottom: 24px; border-bottom: 2px solid #f0f0f0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <h3 style="margin: 0; font-size: 24px; font-weight: 600; color: #2c3e50;">
                            ${store.store_name || store.name}
                        </h3>
                        <span class="store-platform ${store.platform}" style="padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; text-transform: uppercase;">
                            ${store.platform}
                        </span>
                    </div>
                    <p style="margin: 0; color: #7f8c8d; font-size: 14px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="vertical-align: middle; margin-right: 6px;">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke-width="2" stroke-linecap="round"/>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        ${store.domain}
                    </p>
                </div>

                <!-- Details Grid -->
                <div style="display: grid; gap: 20px; margin-bottom: 32px;">
                    <!-- Connection Status -->
                    <div style="background: ${isConnected ? '#ecfdf5' : '#fef2f2'}; padding: 16px; border-radius: 12px; border-left: 4px solid ${isConnected ? '#10b981' : '#ef4444'};">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 13px; font-weight: 500; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">
                                Statut de connexion
                            </span>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: ${isConnected ? '#10b981' : '#ef4444'}; display: inline-block; animation: pulse 2s infinite;"></span>
                                <span style="font-weight: 600; font-size: 15px; color: ${isConnected ? '#059669' : '#dc2626'};">
                                    ${connectionStatus}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Platform Info -->
                    <div style="background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 13px; font-weight: 500; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                            Plateforme E-commerce
                        </div>
                        <div style="font-weight: 600; font-size: 15px; color: #334155;">
                            ${store.platform}
                        </div>
                    </div>

                    <!-- Last Sync -->
                    <div style="background: #f8fafc; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 13px; font-weight: 500; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                            Dernière synchronisation
                        </div>
                        <div style="font-weight: 600; font-size: 15px; color: #334155;">
                            ${lastSync}
                        </div>
                    </div>

                    ${store.consumer_key ? `
                        <!-- API Key -->
                        <div style="background: #fefce8; padding: 16px; border-radius: 12px; border: 1px solid #fde047;">
                            <div style="font-size: 13px; font-weight: 500; color: #854d0e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                                Clé API (Consumer Key)
                            </div>
                            <div style="font-family: 'Courier New', monospace; font-size: 14px; color: #713f12; word-break: break-all;">
                                ${store.consumer_key.substring(0, 20)}...
                            </div>
                        </div>
                    ` : ''}
                </div>

                <!-- Action Buttons -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding-top: 24px; border-top: 2px solid #f0f0f0;">
                    <button class="btn btn-secondary" onclick="testConnection(${store.id})"
                        style="padding: 14px 20px; border-radius: 10px; font-weight: 600; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; cursor: pointer;">
                        <span style="font-size: 18px;">🔍</span>
                        Tester
                    </button>
                </div>
            </div>

            <style>
                @keyframes pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.5; }
                }
                
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }
                
                .btn:active {
                    transform: translateY(0);
                }
            </style>
        `;
        
        modal.style.display = 'block';
    }

    function viewStore(storeId) {
        viewStoreDetails(storeId);
    }

    async function deleteStore(storeId) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette boutique? Cette action est irréversible.')) {
            return;
        }

        try {
            const response = await fetch(`./controllers/delete_store.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    store_id: storeId 
                })
            });

            const data = await response.json();

            if (data.success) {
                // Remove from local array
                stores = stores.filter(s => s.id !== storeId);
                
                // Update user limits with the count from server (more reliable)
                if (data.current_stores !== undefined) {
                    userLimits.current = data.current_stores;
                } else {
                    userLimits.current = stores.length;
                }
                
                showAlert(`Boutique "${data.store_name || 'inconnue'}" supprimée avec succès`, 'success');
                updateLimitDisplay();
                renderStores();

            } else {
                throw new Error(data.message || 'Failed to delete store');
            }
        } catch (error) {
            console.error('Error deleting store:', error);
            showAlert('Erreur lors de la suppression: ' + error.message, 'danger');
        }
    }

    async function testConnection(storeId) {
        const store = stores.find(s => s.id === storeId);
        if (!store) return;

        try {
            showAlert('Test de connexion...', 'info');
            
            // Platform-specific connection test
            const endpoint = `./controllers/test_${store.platform}_connection.php`;
            
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    store_id: storeId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                showAlert('Connexion réussie!', 'success');
                store.is_connected = true;
                store.connected_at = new Date().toISOString();
            } else {
                showAlert('Échec de la connexion: ' + (data.message || 'Vérifiez vos identifiants.'), 'danger');
                store.is_connected = false;
            }
            
            renderStores();
        } catch (error) {
            console.error('Error testing connection:', error);
            showAlert('Erreur lors du test de connexion', 'danger');
        }
    }

    function toggleStore(storeId) {
        const store = stores.find(s => s.id === storeId);
        if (!store) return;

        store.status = store.status === 'active' ? 'inactive' : 'active';
        store.is_connected = store.status === 'active';
        
        renderStores();
        showAlert(`Boutique ${store.status === 'active' ? 'activée' : 'désactivée'}`, 'info');
    }

    // Utility functions
    function showAlert(message, type = 'info') {
        // Create alert element if it doesn't exist
        let alertContainer = document.getElementById('alert-container');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'alert-container';
            alertContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 400px;
            `;
            document.body.appendChild(alertContainer);
        }

        const alertElement = document.createElement('div');
        const alertColors = {
            success: '#27ae60',
            danger: '#e74c3c',
            warning: '#f39c12',
            info: '#3498db'
        };

        alertElement.style.cssText = `
            background: ${alertColors[type] || alertColors.info};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;
        
        alertElement.textContent = message;
        alertContainer.appendChild(alertElement);

        // Animate in
        setTimeout(() => {
            alertElement.style.opacity = '1';
            alertElement.style.transform = 'translateX(0)';
        }, 100);

        // Auto remove after 5 seconds
        setTimeout(() => {
            alertElement.style.opacity = '0';
            alertElement.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (alertElement.parentNode) {
                    alertElement.parentNode.removeChild(alertElement);
                }
            }, 300);
        }, 5000);
    }

    // Export functions for global access (if using modules)
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            initializeApp,
            openAddStoreModal,
            closeAddStoreModal,
            selectPlatform,
            handleFormSubmit,
            connectStore,
            disconnectStore,
            viewStoreDetails,
            deleteStore,
            testConnection,
            toggleStore,
            showAlert
        };
    }
</script>