<?php 
require_once("./core/init.php");

$db = DB::getInstance();

// Récupérer transaction_id depuis URL
$transaction_id = $_GET['transaction_id'] ?? null;

if (!$transaction_id) {
    Redirect::to('./home');
}

// Chercher la transaction
$payment = $db->get('payment_attempts', ['transaction_id', '=', $transaction_id]);

if (!$payment->count()) {
    Redirect::to('./home');
}

$paymentData = $payment->first();

// Récupérer les données utilisateur
$user = $db->get('users', ['id', '=', $paymentData->user_id]);
$userData = $user->count() ? $user->first() : null;

// Récupérer les données de subscription si disponibles
$subscription = null;
if ($userData && $userData->plan_id) {
    $sub = $db->get('subscriptions', ['user_id', '=', $userData->id]);
    $subscription = $sub->count() ? $sub->first() : null;
}

// Définir statut et informations - PENDING = FAILED
$statusText = '';
$statusClass = '';
$statusIcon = '';
$statusMessage = '';
$showRetryButton = false;

if ($paymentData->status === 'completed') {
    $statusText = "Paiement réussi";
    $statusClass = "success";
    $statusIcon = "fas fa-check-circle";
    $statusMessage = "Félicitations ! Votre paiement a été traité avec succès et votre compte a été mis à jour.";
} elseif ($paymentData->status === 'failed') {
    $statusText = "Paiement échoué";
    $statusClass = "failed";
    $statusIcon = "fas fa-times-circle";
    $statusMessage = "Nous sommes désolés, votre paiement n'a pas pu être traité. Veuillez vérifier vos informations de paiement et réessayer.";
    $showRetryButton = true;
} else {
    // Pending status means failed payment
    $statusText = "Paiement échoué";
    $statusClass = "failed";
    $statusIcon = "fas fa-times-circle";
    $statusMessage = "Votre tentative de paiement n'a pas abouti. La transaction n'a pas pu être finalisée avec succès.";
    $showRetryButton = true;
}

// Fonction pour formater les dates
function formatDate($date) {
    if (!$date) return 'N/A';
    $dateObj = new DateTime($date);
    return $dateObj->format('d/m/Y à H:i');
}

// Fonction pour obtenir le nom du plan
function getPlanName($plan_id) {
    $plans = [
        1 => 'Starter',
        2 => 'Professional',
        3 => 'Growth',
        4 => 'Business'
    ];
    return $plans[$plan_id] ?? 'Plan Inconnu';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat du Paiement - <?= $statusText ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --success-color: #10b981;
            --success-bg: #ecfdf5;
            --success-border: #6ee7b7;
            --error-color: #ef4444;
            --error-bg: #fef2f2;
            --error-border: #fca5a5;
            --primary-color: #3b82f6;
            --primary-dark: #1d4ed8;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --bg-secondary: #f8fafc;
            --border-color: #e5e7eb;
            --shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
            color: var(--text-primary);
        }

        .container {
            max-width: 85%;
            width: 100%;
        }

        .payment-card {
            background: white;
            border-radius: 0px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            animation: slideUp 0.8s ease-out;
            border: none;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .status-header {
            padding: 50px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .status-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            background: radial-gradient(circle at 30% 40%, rgba(255,255,255,0.5), transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255,255,255,0.3), transparent 50%);
        }

        .status-header.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .status-header.failed {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .status-icon {
            position: relative;
            z-index: 2;
            font-size: 5rem;
            margin-bottom: 20px;
            display: inline-block;
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .status-title {
            position: relative;
            z-index: 2;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            letter-spacing: -0.02em;
        }

        .status-message {
            position: relative;
            z-index: 2;
            font-size: 1.2rem;
            opacity: 0.95;
            line-height: 1.6;
            max-width: 600px;
            margin: 0 auto;
            font-weight: 400;
        }

        .card-content {
            padding: 40px;
        }

        .alert-box {
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 32px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            animation: slideIn 0.6s ease-out 0.3s both;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-box.error {
            background: var(--error-bg);
            border: 2px solid var(--error-border);
            color: var(--error-color);
        }

        .alert-box.success {
            background: var(--success-bg);
            border: 2px solid var(--success-border);
            color: var(--success-color);
        }

        .alert-icon {
            font-size: 1.5rem;
            margin-top: 2px;
        }

        .alert-content h3 {
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 8px;
        }

        .alert-content p {
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0;
        }

        .section {
            margin-bottom: 40px;
            animation: fadeInUp 0.6s ease-out 0.4s both;
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

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .section-icon {
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .info-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: var(--primary-color);
        }

        .info-card:hover::before {
            width: 8px;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 500;
        }

        .amount {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--success-color);
            background: linear-gradient(135deg, var(--success-color), #059669);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .transaction-id {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            word-break: break-all;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .badge.success {
            background: var(--success-bg);
            color: var(--success-color);
            border: 1px solid var(--success-border);
        }

        .badge.error {
            background: var(--error-bg);
            color: var(--error-color);
            border: 1px solid var(--error-border);
        }

        .badge.warning {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fcd34d;
        }

        .actions {
            text-align: center;
            margin-top: 40px;
            padding-top: 32px;
            border-top: 2px solid var(--border-color);
            animation: fadeIn 0.6s ease-out 0.6s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            margin: 8px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px 0 rgba(59, 130, 246, 0.6);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            box-shadow: 0 4px 14px 0 rgba(107, 114, 128, 0.3);
        }

        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-3px);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            box-shadow: 0 4px 14px 0 rgba(245, 158, 11, 0.4);
        }

        .btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px 0 rgba(245, 158, 11, 0.6);
        }

        .success-celebration {
            background: var(--success-bg);
            border: 2px solid var(--success-border);
            border-radius: 20px;
            padding: 32px;
            text-align: center;
            margin: 32px 0;
            position: relative;
            overflow: hidden;
        }

        .success-celebration::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1), transparent 70%);
            animation: rotate 10s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .celebration-icon {
            font-size: 3rem;
            color: var(--success-color);
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        .celebration-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--success-color);
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .celebration-text {
            color: #047857;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .status-header {
                padding: 40px 24px;
            }
            
            .status-icon {
                font-size: 4rem;
            }
            
            .status-title {
                font-size: 2rem;
            }
            
            .status-message {
                font-size: 1.1rem;
            }
            
            .card-content {
                padding: 24px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .btn {
                display: flex;
                width: 100%;
                justify-content: center;
                margin: 8px 0;
            }
            
            .actions {
                padding-top: 24px;
            }
        }

        @media (max-width: 480px) {
            .status-header {
                padding: 32px 16px;
            }
            
            .card-content {
                padding: 16px;
            }
            
            .info-card {
                padding: 16px;
            }
        }
    </style>
    <?php if ($paymentData->status === 'completed'): ?>
        <!-- Meta Pixel Code -->
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
            fbq('track', 'PageView');
            fbq('track', 'Purchase', {
                value: <?= $paymentData->amount ?>,
                currency: 'MAD',
                content_type: 'product',
                content_ids: ['<?= htmlspecialchars($paymentData->transaction_id) ?>']
            });
        </script>
        <noscript>
            <img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=1024222953100967&ev=PageView&noscript=1"/>
        </noscript>
        <!-- End Meta Pixel Code -->
    <?php endif; ?>
</head>
<body>
    <div class="container">
        <div class="payment-card">
            <div class="status-header <?= $statusClass ?>">
                <div class="status-icon">
                    <i class="<?= $statusIcon ?>"></i>
                </div>
                <h1 class="status-title"><?= $statusText ?></h1>
                <p class="status-message"><?= $statusMessage ?></p>
            </div>

            <div class="card-content">
                <?php if ($paymentData->status === 'pending' || $paymentData->status === 'failed'): ?>
                <div class="alert-box error">
                    <div class="alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="alert-content">
                        <h3>Transaction non aboutie</h3>
                        <p>Votre paiement n'a pas pu être finalisé. Les causes possibles incluent : fonds insuffisants, carte expirée, limite de crédit dépassée, ou problème technique. Nous vous invitons à vérifier vos informations de paiement et à réessayer.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Informations de transaction -->
                <div class="section">
                    <div class="section-header">
                        <i class="fas fa-receipt section-icon"></i>
                        <h3 class="section-title">Détails de la transaction</h3>
                    </div>
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-label">ID de transaction</div>
                            <div class="info-value transaction-id"><?= htmlspecialchars($paymentData->transaction_id) ?></div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Montant</div>
                            <div class="info-value amount"><?= number_format($paymentData->amount, 2, ',', ' ') ?> MAD</div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Date de transaction</div>
                            <div class="info-value">
                                <i class="fas fa-calendar-alt" style="margin-right: 8px; color: var(--primary-color);"></i>
                                <?= formatDate($paymentData->created_at) ?>
                            </div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Méthode de paiement</div>
                            <div class="info-value">
                                <i class="fas fa-credit-card" style="margin-right: 8px; color: var(--primary-color);"></i>
                                <?= ucfirst($paymentData->payment_method ?? 'Non spécifié') ?>
                            </div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Statut du paiement</div>
                            <div class="info-value">
                                <?php if ($paymentData->status === 'completed'): ?>
                                    <span class="badge success"><i class="fas fa-check"></i> Réussi</span>
                                <?php else: ?>
                                    <span class="badge error"><i class="fas fa-times"></i> Échoué</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($paymentData->proc_return_code): ?>
                        <div class="info-card">
                            <div class="info-label">Code de retour</div>
                            <div class="info-value"><?= htmlspecialchars($paymentData->proc_return_code) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($userData): ?>
                <!-- Informations utilisateur -->
                <div class="section">
                    <div class="section-header">
                        <i class="fas fa-user section-icon"></i>
                        <h3 class="section-title">Informations du compte</h3>
                    </div>
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-label">Nom complet</div>
                            <div class="info-value"><?= htmlspecialchars($userData->name) ?></div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Adresse email</div>
                            <div class="info-value">
                                <div><?= htmlspecialchars($userData->email) ?></div>
                                <div style="margin-top: 8px;">
                                    <span class="badge <?= $userData->is_verified ? 'success' : 'warning' ?>">
                                        <i class="fas fa-<?= $userData->is_verified ? 'check-circle' : 'clock' ?>"></i>
                                        <?= $userData->is_verified ? 'Vérifié' : 'Non vérifié' ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if ($userData->phone): ?>
                        <div class="info-card">
                            <div class="info-label">Numéro de téléphone</div>
                            <div class="info-value">
                                <i class="fas fa-phone" style="margin-right: 8px; color: var(--primary-color);"></i>
                                <?= htmlspecialchars($userData->phone) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="info-card">
                            <div class="info-label">Statut du compte</div>
                            <div class="info-value">
                                <span class="badge <?= $userData->is_active ? 'success' : 'error' ?>">
                                    <i class="fas fa-<?= $userData->is_active ? 'check' : 'times' ?>"></i>
                                    <?= $userData->is_active ? 'Actif' : 'Inactif' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($paymentData->status === 'completed' && $subscription): ?>
                <!-- Informations d'abonnement -->
                <div class="section">
                    <div class="section-header">
                        <i class="fas fa-crown section-icon"></i>
                        <h3 class="section-title">Détails de l'abonnement</h3>
                    </div>
                    <div class="info-grid">
                        <div class="info-card">
                            <div class="info-label">Plan souscrit</div>
                            <div class="info-value">
                                <i class="fas fa-star" style="margin-right: 8px; color: #fbbf24;"></i>
                                <?= getPlanName($subscription->plan_id) ?>
                            </div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Date de début</div>
                            <div class="info-value">
                                <i class="fas fa-play-circle" style="margin-right: 8px; color: var(--success-color);"></i>
                                <?= formatDate($subscription->started_at) ?>
                            </div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Date d'expiration</div>
                            <div class="info-value">
                                <i class="fas fa-calendar-times" style="margin-right: 8px; color: var(--text-secondary);"></i>
                                <?= formatDate($subscription->expires_at) ?>
                            </div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Statut de l'abonnement</div>
                            <div class="info-value">
                                <span class="badge success">
                                    <i class="fas fa-check-circle"></i>
                                    <?= ucfirst($subscription->status) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Message de succès -->
                <div class="success-celebration">
                    <div class="celebration-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h4 class="celebration-title">Félicitations !</h4>
                    <p class="celebration-text">
                        Votre abonnement <strong><?= getPlanName($subscription->plan_id) ?></strong> est maintenant actif. 
                        Profitez de toutes les fonctionnalités premium et explorez toutes les possibilités qui s'offrent à vous !
                    </p>
                </div>
                <?php endif; ?>

                <div class="actions">
                    <?php if ($paymentData->status === 'completed'): ?>
                        <a href="/dashboard/home.php" class="btn btn-primary">
                            <i class="fas fa-tachometer-alt"></i>
                            Accéder au tableau de bord
                        </a>
                        <a href="/dashboard/profile.php" class="btn btn-secondary">
                            <i class="fas fa-user-cog"></i>
                            Gérer mon profil
                        </a>
                    <?php else: ?>
                        <a href="/checkout?retry=<?= $transaction_id ?>" class="btn btn-warning">
                            <i class="fas fa-redo"></i>
                            Réessayer le paiement
                        </a>
                        <a href="/contact" class="btn btn-secondary">
                            <i class="fas fa-life-ring"></i>
                            Contacter le support
                        </a>
                    <?php endif; ?>
                    <a href="/home" class="btn btn-secondary">
                        <i class="fas fa-home"></i>
                        Retour à l'accueil
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>