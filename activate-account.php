<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBG ECOM - Activation de Compte</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* Base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333;
        }

        /* Container */
        .activation-container {
            background: white;
            border-radius: 0px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 85%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .activation-header {
            background: linear-gradient(to right, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .activation-header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .activation-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Status Alert */
        .status-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px 30px;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .status-alert.error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .status-alert i {
            font-size: 1.5rem;
        }

        .status-alert .alert-content h3 {
            margin-bottom: 5px;
            font-size: 1.2rem;
        }

        .status-alert .alert-content p {
            margin: 0;
            font-size: 0.95rem;
            line-height: 1.4;
        }

        /* Content */
        .activation-content {
            display: flex;
            flex-wrap: wrap;
        }

        /* Left side - Account info */
        .account-info {
            flex: 1;
            min-width: 300px;
            padding: 30px;
            background: #f8f9fa;
            border-right: 1px solid #eee;
        }

        .user-card {
            text-align: center;
            margin-bottom: 30px;
        }

        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(to right, #6a11cb 0%, #2575fc 100%);
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
        }

        .user-name {
            font-size: 1.4rem;
            margin-bottom: 5px;
        }

        .user-email {
            color: #666;
            margin-bottom: 15px;
        }

        .account-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .account-status.inactive {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .account-status.expired {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .plan-details {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .plan-name {
            font-size: 1.3rem;
            color: #6a11cb;
            margin-bottom: 10px;
            text-align: center;
        }

        .plan-price {
            font-size: 2rem;
            font-weight: bold;
            color: #2575fc;
            text-align: center;
            margin-bottom: 15px;
        }

        .plan-features {
            list-style: none;
            margin-top: 15px;
        }

        .plan-features li {
            padding: 8px 0;
            border-bottom: 1px solid #f1f1f1;
            display: flex;
            align-items: center;
        }

        .plan-features li:last-child {
            border-bottom: none;
        }

        .plan-features i {
            color: #28a745;
            margin-right: 10px;
        }

        /* Right side - Payment section */
        .payment-section {
            flex: 1;
            min-width: 300px;
            padding: 30px;
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: #333;
            position: relative;
            padding-bottom: 10px;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: #6a11cb;
            border-radius: 3px;
        }

        .payment-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #6a11cb;
        }

        .payment-info h4 {
            color: #6a11cb;
            margin-bottom: 10px;
        }

        .payment-info ul {
            list-style: none;
            margin: 0;
        }

        .payment-info li {
            padding: 5px 0;
            display: flex;
            align-items: center;
        }

        .payment-info i {
            color: #28a745;
            margin-right: 8px;
            font-size: 0.9rem;
        }

        .payment-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(to right, #6a11cb 0%, #2575fc 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .payment-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }

        .payment-button:active {
            transform: translateY(0);
        }

        .payment-button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading .spinner {
            display: block;
        }

        .loading .button-text {
            display: none;
        }

        .terms {
            margin: 25px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 0.9rem;
            color: #666;
        }

        .terms a {
            color: #6a11cb;
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        /* Footer */
        .activation-footer {
            padding: 20px 30px;
            background: #f8f9fa;
            text-align: center;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 0.9rem;
        }

        .activation-footer a {
            color: #6a11cb;
            text-decoration: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .activation-content {
                flex-direction: column;
            }
            
            .account-info {
                border-right: none;
                border-bottom: 1px solid #eee;
            }
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 10px;
            color: white;
            z-index: 1000;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            max-width: 400px;
        }

        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }

        .notification.success {
            background: #28a745;
        }

        .notification.error {
            background: #dc3545;
        }

        .notification.info {
            background: #17a2b8;
        }

        .notification.warning {
            background: #ffc107;
            color: #333;
        }
    </style>
</head>
<body>
    <!-- Activation Container -->
    <div class="activation-container">
        <!-- Header -->
        <div class="activation-header">
            <h1>Activation de Compte</h1>
            <p id="headerSubtext">Votre compte nécessite un paiement pour être activé</p>
        </div>

        <!-- Status Alert -->
        <div class="status-alert" id="statusAlert">
            <i class="bi bi-exclamation-triangle"></i>
            <div class="alert-content">
                <h3 id="alertTitle">Compte Non Activé</h3>
                <p id="alertMessage">Votre compte est actuellement inactif. Procédez au paiement ci-dessous pour l'activer et accéder à toutes les fonctionnalités.</p>
            </div>
        </div>

        <!-- Content -->
        <div class="activation-content">
            <!-- Left side - Account info -->
            <div class="account-info">
                <div class="user-card">
                    <div class="user-avatar">
                        <i class="bi bi-person"></i>
                    </div>
                    <h2 class="user-name" id="userName">Nom Utilisateur</h2>
                    <p class="user-email" id="userEmail">utilisateur@example.com</p>
                    <div class="account-status inactive" id="accountStatus">
                        Compte Inactif
                    </div>
                </div>

                <div class="plan-details">
                    <h3 class="plan-name" id="planName">Plan Professionnel</h3>
                    <div class="plan-price" id="planPrice">149 MAD/mois</div>
                    
                    <ul class="plan-features" id="planFeatures">
                        <li><i class="bi bi-check"></i> Jusqu'à 1 000 commandes/mois</li>
                        <li><i class="bi bi-check"></i> 2 intégrations boutiques</li>
                        <li><i class="bi bi-check"></i> 2 intégrations sociétés de livraison</li>
                        <li><i class="bi bi-check"></i> Support prioritaire via Email</li>
                    </ul>
                </div>
            </div>

            <!-- Right side - Payment section -->
            <div class="payment-section">
                <h3 class="section-title">Paiement Sécurisé</h3>
                
                <div class="payment-info">
                    <h4><i class="bi bi-shield-check"></i> Paiement sécurisé par CMI</h4>
                    <ul>
                        <li><i class="bi bi-check-circle"></i> Transactions protégées par SSL</li>
                        <li><i class="bi bi-check-circle"></i> Cartes Visa, MasterCard acceptées</li>
                        <li><i class="bi bi-check-circle"></i> Paiement via banques marocaines</li>
                        <li><i class="bi bi-check-circle"></i> Activation immédiate après paiement</li>
                    </ul>
                </div>

                <div class="terms">
                    <p>En procédant au paiement, vous acceptez nos <a href="#">Conditions Générales de Vente</a> et notre <a href="#">Politique de Confidentialité</a>. Votre abonnement sera renouvelé automatiquement chaque mois jusqu'à annulation.</p>
                </div>

                <button class="payment-button" id="paymentButton">
                    <span class="spinner"></span>
                    <i class="bi bi-credit-card"></i>
                    <span class="button-text">Payer via CMI - <span id="paymentAmount">149 MAD</span></span>
                </button>
            </div>
        </div>

        <!-- Footer -->
        <div class="activation-footer">
            <p>Vous rencontrez des problèmes? <a href="#">Contactez notre support</a></p>
        </div>
    </div>

    <!-- Notification -->
    <div class="notification" id="notification"></div>

    <!-- CMI Form Container (hidden) -->
    <div id="cmiFormContainer" style="display: none;"></div>

    <script>
        // Global variables
        let currentUser = null;
        let selectedPlan = null;
        let isLoading = false;

        // Plan data
        const plans = {
            starter: {
                name: "Starter",
                price: "0",
                features: [
                    "Jusqu'à 100 commandes/mois", 
                    "1 intégration boutique", 
                    "1 intégration société de livraison", 
                    "Support via Email", 
                    "Idéal pour tester et lancer son activité"
                ]
            },
            professional: {
                name: "Professional",
                price: "149",
                features: [
                    "Jusqu'à 1 000 commandes/mois", 
                    "2 intégrations boutiques", 
                    "2 intégrations sociétés de livraison", 
                    "Support prioritaire via Email"
                ]
            },
            growth: {
                name: "Growth",
                price: "199",
                features: [
                    "Jusqu'à 4 000 commandes/mois", 
                    "4 intégrations boutiques", 
                    "5 intégrations sociétés de livraison", 
                    "Jusqu'à 5 membres d'équipe", 
                    "Support premium via WhatsApp & Email"
                ]
            },
            business: {
                name: "Business",
                price: "450",
                features: [
                    "Commandes illimitées", 
                    "Intégrations illimitées", 
                    "Équipe illimitée", 
                    "Account Manager dédié", 
                    "Support avancé via WhatsApp, Email & Live Chat"
                ]
            }
        };

        // Account statuses configuration
        const accountStatuses = {
            inactive: {
                title: "Compte Non Activé",
                message: "Votre compte est actuellement inactif. Procédez au paiement ci-dessous pour l'activer et accéder à toutes les fonctionnalités.",
                headerText: "Votre compte nécessite un paiement pour être activé",
                statusClass: "inactive",
                statusText: "Compte Inactif",
                alertClass: ""
            },
            expired: {
                title: "Abonnement Expiré",
                message: "Votre abonnement a expiré. Renouvelez votre plan pour continuer à utiliser nos services sans interruption.",
                headerText: "Votre abonnement a expiré - Renouvellement requis",
                statusClass: "expired",
                statusText: "Abonnement Expiré",
                alertClass: "error"
            },
            suspended: {
                title: "Compte Suspendu",
                message: "Votre compte a été temporairement suspendu. Effectuez le paiement pour réactiver immédiatement votre accès.",
                headerText: "Compte suspendu - Paiement requis pour réactivation",
                statusClass: "expired",
                statusText: "Compte Suspendu",
                alertClass: "error"
            },
            trial_expired: {
                title: "Période d'Essai Terminée",
                message: "Votre période d'essai gratuite est terminée. Choisissez un plan payant pour continuer à utiliser nos services.",
                headerText: "Période d'essai terminée - Choisissez votre plan",
                statusClass: "inactive",
                statusText: "Essai Terminé",
                alertClass: ""
            }
        };

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
            initializeEventListeners();
        });

        // Initialize page with user data
        async function initializePage() {
            // Get user ID from URL parameters or localStorage
            const urlParams = new URLSearchParams(window.location.search);
            const userId = urlParams.get('user_id') || localStorage.getItem('pendingUserId');
            
            if (!userId) {
                showNotification('ID utilisateur manquant', 'error');
                return;
            }
            
            try {
                // Fetch user data from your backend
                const response = await fetch(`./dashboard/controllers/get_user_dataApi.php?user_id=${userId}`);
                const userData = await response.json();
                
                if (!userData.success) {
                    throw new Error(userData.message || 'Erreur lors de la récupération des données utilisateur');
                }

                // Set user data
                document.getElementById('userName').textContent = userData.name || userData.username;
                document.getElementById('userEmail').textContent = userData.email;
                
                // Use account status from API instead of calculating it here
                let accountStatus = userData.account_status || 'inactive';
                
                // Special handling: if user has active subscription, redirect to dashboard
                if (accountStatus === 'active') {
                    window.location.href = './dashboard/';
                    return;
                }
                
                // If user is in trial period and it's still active, redirect to dashboard
                if (accountStatus === 'trial_active') {
                    window.location.href = './dashboard/';
                    return;
                }
                
                // Set account status display
                updateAccountStatus(accountStatus);
                console.log(userData)
                // Set plan data
                if (userData.plan_id) {
                    // Map your plan IDs to the plan keys used in this page
                    const planMapping = {
                        1: 'starter',
                        2: 'professional', 
                        3: 'growth',
                        4: 'business'
                    };
                    
                    selectedPlan = planMapping[userData.plan_id] || 'professional';
                    updatePlanDetails(selectedPlan);
                } else {
                    // Default to professional plan if no plan specified
                    selectedPlan = 'professional';
                    updatePlanDetails(selectedPlan);
                }
                
                // Store data for later use
                currentUser = {
                    id: userId,
                    email: userData.email,
                    name: userData.name || userData.username,
                    phone: userData.phone || null,
                    plan: selectedPlan,
                    status: accountStatus,
                    is_first_subscription: !userData.subscription, // First subscription if no subscription exists
                    subscription_id: userData.subscription ? userData.subscription.id : null,
                    latest_payment_attempt: userData.latest_payment_attempt || null
                };
                
                // Save to localStorage
                localStorage.setItem('pendingUserId', userId);
                localStorage.setItem('pendingUserEmail', userData.email);
                localStorage.setItem('pendingUserName', userData.name || userData.username);
                localStorage.setItem('pendingUserPlan', selectedPlan);
                
                // Log payment attempts for debugging (optional)
                if (userData.payment_attempts && userData.payment_attempts.length > 0) {
                    console.log('User payment attempts:', userData.payment_attempts);
                }
                
                // Show previous payment attempt status if exists
                if (userData.latest_payment_attempt) {
                    const lastAttempt = userData.latest_payment_attempt;
                    if (lastAttempt.status === 'failed' || lastAttempt.status === 'error') {
                        showNotification(`Dernière tentative de paiement: ${lastAttempt.status}. Transaction: ${lastAttempt.transaction_id}`, 'warning');
                    } else if (lastAttempt.status === 'pending') {
                        showNotification(`Paiement en cours de traitement. Transaction: ${lastAttempt.transaction_id}`, 'info');
                    }
                }
                
            } catch (error) {
                console.error('Error initializing page:', error);
                showNotification('Erreur lors du chargement des données utilisateur', 'error');
            }
        }

        // Update account status display
        function updateAccountStatus(status) {
            const statusConfig = accountStatuses[status] || accountStatuses.inactive;
            
            // Update header
            document.getElementById('headerSubtext').textContent = statusConfig.headerText;
            
            // Update alert
            const alertElement = document.getElementById('statusAlert');
            const alertTitle = document.getElementById('alertTitle');
            const alertMessage = document.getElementById('alertMessage');
            
            alertTitle.textContent = statusConfig.title;
            alertMessage.textContent = statusConfig.message;
            
            // Update alert styling
            alertElement.className = 'status-alert' + (statusConfig.alertClass ? ' ' + statusConfig.alertClass : '');
            
            // Update account status badge
            const statusBadge = document.getElementById('accountStatus');
            statusBadge.textContent = statusConfig.statusText;
            statusBadge.className = 'account-status ' + statusConfig.statusClass;
        }

        // Update plan details in the UI
        function updatePlanDetails(planName) {
            const plan = plans[planName];
            if (!plan) return;

            document.getElementById('planName').textContent = plan.name;
            document.getElementById('planPrice').textContent = plan.price + ' MAD/mois';
            document.getElementById('paymentAmount').textContent = plan.price + ' MAD';

            const featuresList = document.getElementById('planFeatures');
            featuresList.innerHTML = '';

            plan.features.forEach(feature => {
                const li = document.createElement('li');
                li.innerHTML = `<i class="bi bi-check"></i> ${feature}`;
                featuresList.appendChild(li);
            });
        }

        // Initialize event listeners
        function initializeEventListeners() {
            const paymentButton = document.getElementById('paymentButton');
            if (paymentButton) {
                paymentButton.addEventListener('click', handleCMIPayment);
            }
        }

        // Handle CMI payment process
        async function handleCMIPayment() {
            if (isLoading || !currentUser) return;

            // Validate required data
            if (!currentUser.id || !selectedPlan) {
                showNotification('Données utilisateur manquantes. Veuillez rafraîchir la page.', 'error');
                return;
            }

            const plan = plans[selectedPlan];
            if (!plan || plan.price === "0") {
                showNotification('Plan invalide ou gratuit sélectionné.', 'error');
                return;
            }

            setLoadingState(true);
            showNotification('Préparation du paiement sécurisé...', 'info');

            try {
                // Prepare payment data
                const paymentData = {
                    user_id: currentUser.id,
                    plan: selectedPlan,
                    amount: parseFloat(plan.price),
                    name: currentUser.name || 'OBGECOM',
                    email: currentUser.email,
                    phone: currentUser.phone || '-'
                };

                console.log('Sending payment data:', paymentData);

                // Call your CMI payment processing endpoint
                const response = await fetch('./dashboard/controllers/process_payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(paymentData)
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('Payment processing result:', result);

                if (result.success && result.form_html) {
                    showNotification('Redirection vers CMI...', 'info');
                    
                    // Create and display the payment form modal
                    createAndSubmitPaymentForm(result.form_html, result.transaction_id);
                    
                } else {
                    throw new Error(result.message || 'Erreur lors de la préparation du paiement');
                }

            } catch (error) {
                console.error('Payment preparation error:', error);
                showNotification(
                    error.message || 'Erreur lors de la préparation du paiement. Veuillez réessayer.', 
                    'error'
                );
            } finally {
                setLoadingState(false);
            }
        }

        /**
         * Create payment form modal and handle submission to CMI gateway
         */
        function createAndSubmitPaymentForm(formHtml, transactionId) {
            // Create overlay for payment form
            const overlay = document.createElement('div');
            overlay.id = 'payment-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                z-index: 9999;
                display: flex;
                justify-content: center;
                align-items: center;
            `;

            // Create payment container
            const container = document.createElement('div');
            container.style.cssText = `
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                max-width: 500px;
                width: 90%;
                text-align: center;
                max-height: 90vh;
                overflow-y: auto;
            `;

            // Add payment content
            container.innerHTML = `
                <div style="margin-bottom: 20px;">
                    <h3 style="color: #333; margin-bottom: 10px;">
                        <i class="bi bi-shield-check" style="color: #28a745; margin-right: 8px;"></i>
                        Paiement Sécurisé CMI
                    </h3>
                    <p style="color: #666; font-size: 14px;">Transaction ID: ${transactionId}</p>
                    <p style="color: #666; font-size: 12px; margin-top: 5px;">
                        Vous allez être redirigé vers la passerelle de paiement sécurisée
                    </p>
                </div>
                ${formHtml}
                <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button onclick="cancelPayment()" style="
                        background: #dc3545;
                        color: white;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 5px;
                        cursor: pointer;
                        font-size: 14px;
                        transition: background-color 0.3s;
                    " onmouseover="this.style.backgroundColor='#c82333'" 
                       onmouseout="this.style.backgroundColor='#dc3545'">
                        Annuler le Paiement
                    </button>
                </div>
            `;

            overlay.appendChild(container);
            document.body.appendChild(overlay);

            // Store transaction ID for potential cleanup
            window.currentTransactionId = transactionId;
            
            // Add click outside to close (optional)
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    cancelPayment();
                }
            });
        }

        /**
         * Cancel payment and clean up
         */
        function cancelPayment() {
            const overlay = document.getElementById('payment-overlay');
            if (overlay) {
                overlay.remove();
            }
            
            // Reset loading state
            setLoadingState(false);
            
            // Optional: Call backend to mark transaction as cancelled
            if (window.currentTransactionId) {
                fetch('./dashboard/controllers/cancel_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transaction_id: window.currentTransactionId
                    })
                }).catch(error => console.error('Error cancelling payment:', error));
                
                // Clear the transaction ID
                window.currentTransactionId = null;
            }
            
            showNotification('Paiement annulé', 'info');
        }

        // Set loading state
        function setLoadingState(loading) {
            isLoading = loading;
            const button = document.getElementById('paymentButton');
            
            if (loading) {
                button.classList.add('loading');
                button.disabled = true;
            } else {
                button.classList.remove('loading');
                button.disabled = false;
            }
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type}`;
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, type === 'error' ? 6000 : 4000);
        }
    </script>
</body>
</html>