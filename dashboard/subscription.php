<?php 

// Include configuration file if exists
if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

// Check if user is logged in
if(!isset($_SESSION['user']) || !isset($_SESSION['user']['username'])) {
    header('Location: ./home');
    exit();
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

$db = DB::getInstance();
if(!$db) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database connection failed',
        'code'    => 'DB_CONNECTION_FAILED'
    ]);
    exit;
}

// ============================================================================
// USER AUTHENTICATION
// ============================================================================

$useremail = $_SESSION['user']['username'];
$user = $db->get('users', array('email', '=', $useremail))->first();

if (!$user || !isset($user->id)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Utilisateur non trouvé',
        'code'    => 'USER_NOT_FOUND'
    ]);
    exit;
}

$user_id = (int)$user->id;

// ============================================================================
// GET USER'S CURRENT ORDER QUOTA
// ============================================================================

$current_quota = $db->query("
    SELECT * FROM user_order_quotas 
    WHERE user_id = ? AND status = 'active'
    ORDER BY id DESC LIMIT 1
", [$user_id])->first();

// If no quota exists, create a free one (100 free orders)
if (!$current_quota) {
    $db->query("
        INSERT INTO user_order_quotas (user_id, order_quota, free_orders_used, paid_orders_remaining, status, activated_at, expires_at)
        VALUES (?, 100, 0, 0, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))
    ", [$user_id]);
    
    $current_quota = $db->query("
        SELECT * FROM user_order_quotas 
        WHERE user_id = ? AND status = 'active'
        ORDER BY id DESC LIMIT 1
    ", [$user_id])->first();
}

// ============================================================================
// GET USAGE STATISTICS (LAST 30 DAYS)
// ============================================================================

$orders_used = $db->query("
    SELECT COUNT(*) as count FROM orders 
    WHERE user_id = ? 
    AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
", [$user_id])->first();

$orders_count = $orders_used ? (int)$orders_used->count : 0;

// Calculate remaining orders
$free_orders_remaining = max(0, 100 - ($current_quota->free_orders_used ?? 0));
$total_remaining = $free_orders_remaining + ($current_quota->paid_orders_remaining ?? 0);

// ============================================================================
// GET PURCHASE HISTORY
// ============================================================================

$purchases = $db->query("
    SELECT * FROM quota_purchases 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
", [$user_id])->results();

// ============================================================================
// CALCULATE PRICING FOR DISPLAY
// ============================================================================

$price_per_order = 0.80; // MAD
$free_quota = 100;

// Preset quotas for the UI
$preset_quotas = [100, 500, 1000, 2500, 5000, 10000];

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBG ECOM - Gestion du quota de commandes</title>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg: #fafaf8;
            --surface: #ffffff;
            --surface2: #f4f3f0;
            --border: #e8e6e0;
            --border2: #d4d1c9;
            --text: #1a1814;
            --text2: #5c5a55;
            --text3: #9b9890;
            --accent: #5c45e8;
            --accent2: #7c6af0;
            --accent-light: #eeecfd;
            --accent-mid: #c5bef9;
            --success: #1a7a5e;
            --success-bg: #edf7f3;
            --error: #c0392b;
            --error-bg: #fdf0ef;
            --info: #3a5bbf;
            --info-bg: #eef2fc;
            --warning: #d97706;
            --warning-bg: #fef3c7;
            --r: 12px;
            --r-sm: 8px;
            --r-lg: 16px;
            --r-pill: 100px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body {
            background: var(--bg);
            font-family: 'DM Sans', system-ui, sans-serif;
            color: var(--text);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .logo {
            font-family: 'Sora', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent);
            text-decoration: none;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-pill);
            color: var(--text2);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        
        /* Cards */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            margin-bottom: 1.5rem;
        }
        
        .card-title {
            font-family: 'Sora', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.25rem;
        }
        
        .card-subtitle {
            font-size: 0.85rem;
            color: var(--text3);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 1.25rem;
            text-align: center;
        }
        
        .stat-value {
            font-family: 'Sora', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Quota Selector */
        .quota-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
        }
        
        .quota-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .quota-header .q-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text3);
        }
        
        .quota-header .q-value {
            font-family: 'Sora', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent);
            background: var(--accent-light);
            padding: 0.25rem 1rem;
            border-radius: var(--r-pill);
        }
        
        .quota-slider {
            width: 100%;
            -webkit-appearance: none;
            appearance: none;
            height: 4px;
            background: var(--border2);
            border-radius: 4px;
            outline: none;
            margin: 1rem 0 0.5rem;
        }
        
        .quota-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--accent);
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(92, 69, 232, 0.3);
        }
        
        .quota-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--accent);
            cursor: pointer;
            border: none;
        }
        
        .quota-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.65rem;
            color: var(--text3);
        }
        
        .quota-presets {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .qp-btn {
            padding: 6px 14px;
            border-radius: var(--r-pill);
            font-size: 0.7rem;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text2);
            cursor: pointer;
            transition: all 0.18s;
        }
        
        .qp-btn:hover {
            border-color: var(--accent-mid);
            color: var(--accent);
        }
        
        .qp-btn.on {
            background: var(--accent-light);
            border-color: var(--accent);
            color: var(--accent);
        }
        
        /* Price Summary */
        .price-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--accent-light);
            border: 1px solid var(--accent-mid);
            border-radius: var(--r);
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .price-summary .label {
            font-size: 0.7rem;
            color: var(--accent2);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        
        .price-summary .value {
            font-family: 'Sora', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--accent);
            line-height: 1;
        }
        
        .price-summary .detail {
            font-size: 0.75rem;
            color: var(--text3);
            margin-top: 0.25rem;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--r-pill);
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 2px 8px rgba(92, 69, 232, 0.25);
        }
        
        .btn-primary:hover {
            background: #4e39d4;
            box-shadow: 0 4px 16px rgba(92, 69, 232, 0.35);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text2);
        }
        
        .btn-secondary:hover {
            border-color: var(--border2);
            background: var(--surface2);
        }
        
        .btn-success {
            background: var(--success);
            color: #fff;
        }
        
        .btn-success:hover {
            background: #15614a;
        }
        
        .btn-lg {
            padding: 16px 32px;
            font-size: 1rem;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Progress Bar */
        .progress-container {
            margin-bottom: 1.5rem;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .progress-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text2);
        }
        
        .progress-percentage {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--accent);
        }
        
        .progress-bar {
            height: 8px;
            background: var(--surface2);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        
        .progress-fill.warning {
            background: var(--warning);
        }
        
        .progress-fill.danger {
            background: var(--error);
        }
        
        .progress-text {
            font-size: 0.7rem;
            color: var(--text3);
            margin-top: 0.5rem;
        }
        
        /* History Table */
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th {
            text-align: left;
            padding: 12px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text3);
            border-bottom: 1px solid var(--border);
        }
        
        .history-table td {
            padding: 12px;
            font-size: 0.85rem;
            color: var(--text2);
            border-bottom: 1px solid var(--border);
        }
        
        .history-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: var(--r-pill);
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-badge.paid {
            background: var(--success-bg);
            color: var(--success);
        }
        
        .status-badge.pending {
            background: var(--warning-bg);
            color: var(--warning);
        }
        
        .status-badge.failed {
            background: var(--error-bg);
            color: var(--error);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text3);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--border2);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            font-family: 'Sora', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text2);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            font-size: 0.85rem;
            max-width: 400px;
            margin: 0 auto 1.5rem;
        }
        
        /* Alert */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 1rem 1.25rem;
            border-radius: var(--r);
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-info {
            background: var(--info-bg);
            border: 1px solid #bfcfef;
            color: var(--info);
        }
        
        .alert-warning {
            background: var(--warning-bg);
            border: 1px solid #fcd34d;
            color: var(--warning);
        }
        
        .alert-success {
            background: var(--success-bg);
            border: 1px solid #86efac;
            color: var(--success);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 1rem; }
            .card { padding: 1.5rem; }
            .header { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .price-summary { flex-direction: column; align-items: flex-start; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .quota-presets { justify-content: center; }
        }
        
        /* Loading spinner */
        .spin {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 14px 20px;
            border-radius: var(--r);
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 9999;
            transform: translateX(calc(100% + 2rem));
            transition: transform 0.3s ease;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast.success {
            background: var(--success-bg);
            border-left: 4px solid var(--success);
            color: var(--success);
        }
        
        .toast.error {
            background: var(--error-bg);
            border-left: 4px solid var(--error);
            color: var(--error);
        }
        
        .toast.info {
            background: var(--info-bg);
            border-left: 4px solid var(--info);
            color: var(--info);
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <a href="./home" class="logo">OBGECOM</a>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <span style="font-size: 0.85rem; color: var(--text2);">
                <strong><?php echo htmlspecialchars($user->name ?? $user->email); ?></strong>
            </span>
            <a href="./home" class="back-btn">
                <i class="bi bi-arrow-left"></i>
                Retour au tableau de bord
            </a>
        </div>
    </div>

    <!-- Current Quota Stats -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Votre quota de commandes</h2>
            <p class="card-subtitle">Gérez votre quota mensuel de commandes</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($orders_count); ?></div>
                <div class="stat-label">Commandes utilisées (30j)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($current_quota->order_quota ?? 100); ?></div>
                <div class="stat-label">Quota total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success);">
                    <?php echo number_format($free_orders_remaining); ?>
                </div>
                <div class="stat-label">Gratuites restantes</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--accent);">
                    <?php echo number_format($current_quota->paid_orders_remaining ?? 0); ?>
                </div>
                <div class="stat-label">Payantes restantes</div>
            </div>
        </div>
        
        <!-- Progress bar for free orders -->
        <?php 
        $free_used = $current_quota->free_orders_used ?? 0;
        $free_percentage = min(100, round(($free_used / 100) * 100));
        ?>
        <div class="progress-container">
            <div class="progress-header">
                <span class="progress-label">Commandes gratuites utilisées</span>
                <span class="progress-percentage"><?php echo $free_used; ?> / 100</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill <?php echo $free_percentage >= 90 ? 'danger' : ($free_percentage >= 70 ? 'warning' : ''); ?>" 
                     style="width: <?php echo $free_percentage; ?>%"></div>
            </div>
            <div class="progress-text">
                <?php if ($free_orders_remaining > 0): ?>
                    Il vous reste <?php echo $free_orders_remaining; ?> commandes gratuites ce mois-ci.
                <?php else: ?>
                    <span style="color: var(--warning);">
                        <i class="bi bi-exclamation-triangle"></i>
                        Toutes vos commandes gratuites ont été utilisées. Achetez un quota pour continuer.
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($current_quota->expires_at): ?>
        <div class="alert alert-info">
            <i class="bi bi-calendar-check"></i>
            <div>
                <strong>Prochaine réinitialisation :</strong> 
                <?php echo date('d F Y', strtotime($current_quota->expires_at)); ?>
                (<?php 
                    $days_left = max(0, floor((strtotime($current_quota->expires_at) - time()) / 86400));
                    echo $days_left . ' jour' . ($days_left > 1 ? 's' : '') . ' restant' . ($days_left > 1 ? 's' : '');
                ?>)
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Buy More Orders -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Acheter des commandes supplémentaires</h2>
            <p class="card-subtitle">Payez uniquement ce dont vous avez besoin — 0,80 MAD par commande</p>
        </div>
        
        <div class="quota-wrap">
            <div class="quota-header">
                <span class="q-label">Commandes supplémentaires</span>
                <span class="q-value" id="orderDisplay">500</span>
            </div>
            <input type="range" class="quota-slider" id="orderSlider" min="0" max="10000" step="50" value="500">
            <div class="quota-labels">
                <span>0</span>
                <span>5 000</span>
                <span>10 000+</span>
            </div>
            <div class="quota-presets">
                <button class="qp-btn" data-q="100">100</button>
                <button class="qp-btn on" data-q="500">500</button>
                <button class="qp-btn" data-q="1000">1 000</button>
                <button class="qp-btn" data-q="2500">2 500</button>
                <button class="qp-btn" data-q="5000">5 000</button>
                <button class="qp-btn" data-q="10000">10 000</button>
            </div>
        </div>
        
        <div class="price-summary">
            <div>
                <div class="label">Total à payer</div>
                <div class="value" id="totalPrice">400,00 MAD</div>
                <div class="detail" id="priceDetail">500 commandes × 0,80 MAD</div>
            </div>
            <button class="btn btn-primary btn-lg" id="buyBtn">
                <i class="bi bi-credit-card"></i>
                Acheter maintenant
            </button>
        </div>
        
        <div class="alert alert-success">
            <i class="bi bi-gift"></i>
            <div>
                <strong>Rappel :</strong> Vos 100 premières commandes sont toujours gratuites chaque mois. 
                Les commandes achetées sont reportées au mois suivant si non utilisées.
            </div>
        </div>
    </div>

    <!-- Purchase History -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Historique des achats</h2>
            <p class="card-subtitle">Vos transactions récentes</p>
        </div>
        
        <?php if (!empty($purchases)): ?>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Commandes</th>
                        <th>Montant</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $purchase): ?>
                        <tr>
                            <td>
                                <i class="bi bi-calendar3"></i>
                                <?php echo date('d M Y', strtotime($purchase->created_at)); ?>
                            </td>
                            <td>
                                <strong><?php echo number_format($purchase->order_quota); ?></strong> commandes
                            </td>
                            <td>
                                <strong><?php echo number_format($purchase->amount, 2); ?> MAD</strong>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $purchase->payment_status; ?>">
                                    <i class="bi bi-<?php 
                                        echo $purchase->payment_status == 'paid' ? 'check-circle' : 
                                             ($purchase->payment_status == 'pending' ? 'clock' : 'x-circle');
                                    ?>"></i>
                                    <?php 
                                        echo $purchase->payment_status == 'paid' ? 'Payé' : 
                                             ($purchase->payment_status == 'pending' ? 'En attente' : 'Échoué');
                                    ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-receipt-cutoff"></i>
                <h3>Aucun achat pour le moment</h3>
                <p>Vos achats de quota apparaîtront ici.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast notification -->
<div class="toast" id="toast">
    <span id="toastIcon">✓</span>
    <span id="toastMessage">Message</span>
</div>

<script>
// ============================================================================
// CONFIGURATION
// ============================================================================
const PRICE_PER_ORDER = 0.80;
const FREE_QUOTA = 100;
const USER_ID = <?php echo $user_id; ?>;

// ============================================================================
// QUOTA CALCULATOR
// ============================================================================
let selectedOrders = 500;

function calculateTotal(orders) {
    return {
        total: orders * PRICE_PER_ORDER,
        orders: orders
    };
}

function updateQuotaDisplay() {
    const orders = parseInt(document.getElementById('orderSlider').value);
    selectedOrders = orders;
    
    const result = calculateTotal(orders);
    
    document.getElementById('orderDisplay').textContent = orders.toLocaleString('fr-FR');
    document.getElementById('totalPrice').textContent = result.total.toFixed(2).replace('.', ',') + ' MAD';
    document.getElementById('priceDetail').textContent = 
        orders.toLocaleString('fr-FR') + ' commandes × ' + PRICE_PER_ORDER.toFixed(2).replace('.', ',') + ' MAD';
    
    // Update preset buttons
    document.querySelectorAll('.qp-btn').forEach(btn => {
        btn.classList.toggle('on', parseInt(btn.dataset.q) === orders);
    });
}

document.getElementById('orderSlider').addEventListener('input', updateQuotaDisplay);

document.querySelectorAll('.qp-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const val = parseInt(this.dataset.q);
        document.getElementById('orderSlider').value = val;
        updateQuotaDisplay();
    });
});

// Initialize
updateQuotaDisplay();

// ============================================================================
// PURCHASE HANDLER
// ============================================================================
document.getElementById('buyBtn').addEventListener('click', async function() {
    const btn = this;
    const orders = selectedOrders;
    const total = orders * PRICE_PER_ORDER;
    
    if (orders <= 0) {
        showToast('Veuillez sélectionner un nombre de commandes', 'error');
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spin"></span> Traitement...';
    
    try {
        const response = await fetch('./controllers/purchase_quota.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: USER_ID,
                order_quota: orders,
                amount: total
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Redirection vers le paiement...', 'success');
            
            // Create payment window
            const paymentWindow = window.open('', '_blank', 'width=600,height=800');
            if (paymentWindow) {
                paymentWindow.document.write(data.form_html);
                paymentWindow.document.close();
                paymentWindow.focus();
                
                // Listen for window close
                const checkPayment = setInterval(() => {
                    if (paymentWindow.closed) {
                        clearInterval(checkPayment);
                        setTimeout(() => window.location.reload(), 1000);
                    }
                }, 1000);
            } else {
                throw new Error('Impossible d\'ouvrir la fenêtre de paiement. Veuillez autoriser les popups.');
            }
        } else {
            throw new Error(data.message || 'Erreur lors de la création de la commande');
        }
    } catch (error) {
        console.error('Purchase error:', error);
        showToast(error.message || 'Une erreur est survenue', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-credit-card"></i> Acheter maintenant';
    }
});

// ============================================================================
// TOAST NOTIFICATION
// ============================================================================
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const icon = document.getElementById('toastIcon');
    const msg = document.getElementById('toastMessage');
    
    const icons = { success: '✓', error: '✕', info: 'ℹ' };
    
    toast.className = 'toast ' + type;
    icon.textContent = icons[type] || '✓';
    msg.textContent = message;
    
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 4000);
}
</script>

</body>
</html>