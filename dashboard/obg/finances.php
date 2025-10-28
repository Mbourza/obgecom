<?php
// Database connection and session check for super admin
if (file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if user is logged in and is super admin
if (!Session::exists(Config::get('session/session_name'))) {
    Redirect::to('../../lg'); 
} 

$isLoggedIn = isset($_SESSION['user']);
$user = $db->getThisQuery("SELECT id, `name`, `role`, is_verified, email FROM users WHERE email = ?", [$_SESSION['user']['username']]);

if (!$user || empty($user[0]['id'])) {
    logout(); 
}

if (isset($_GET['logout'])) {
    logout();
}

// Check if user is super admin
if ($user[0]['role'] !== 'super') {
    Redirect::to('../home');
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../../lg');
}

// Handle actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'add_partner':
            addCommercialPartner($_POST, $db);
            break;
        case 'update_partner':
            updateCommercialPartner($_POST, $db);
            break;
        case 'record_payment':
            recordPartnerPayment($_POST, $db);
            break;
        case 'export_commissions':
            exportCommissionsReport($_POST, $db);
            break;
    }
}

// Get filter parameters
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'this_month';
$partner_id = isset($_GET['partner_id']) ? (int)$_GET['partner_id'] : 0;
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Get commercial partners
$partners = $db->getThisQuery("SELECT * FROM commercial_partners ORDER BY created_at DESC");

// Get statistics
$stats = getFinancialStatistics($db, $date_range, $partner_id);

// Function definitions
function addCommercialPartner($data, $db) {
    $required = ['name', 'email', 'commission_rate'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $_SESSION['error_message'] = "Le champ $field est requis";
            header("Location: finances.php");
            exit;
        }
    }
    
    $unique_code = generateUniqueCode($db);
    
    $insert_id = $db->insert('commercial_partners', [
        'name' => trim($data['name']),
        'email' => trim($data['email']),
        'phone' => trim($data['phone'] ?? ''),
        'company' => trim($data['company'] ?? ''),
        'commission_rate' => (float)$data['commission_rate'],
        'unique_code' => $unique_code,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($insert_id) {
        $_SESSION['success_message'] = "Partenaire commercial ajouté avec succès";
        logAdminAction($db, "Added commercial partner: " . trim($data['name']));
    } else {
        $_SESSION['error_message'] = "Erreur lors de l'ajout du partenaire";
    }
    
    header("Location: finances.php");
    exit;
}

function updateCommercialPartner($data, $db) {
    $partner_id = (int)$data['partner_id'];
    
    $update_data = [
        'name' => trim($data['name']),
        'email' => trim($data['email']),
        'phone' => trim($data['phone'] ?? ''),
        'company' => trim($data['company'] ?? ''),
        'commission_rate' => (float)$data['commission_rate'],
        'status' => $data['status'] ?? 'active',
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $result = $db->update('commercial_partners', $partner_id, $update_data);
    
    if ($result) {
        $_SESSION['success_message'] = "Partenaire commercial mis à jour avec succès";
        logAdminAction($db, "Updated commercial partner: " . trim($data['name']));
    } else {
        $_SESSION['error_message'] = "Erreur lors de la mise à jour du partenaire";
    }
    
    header("Location: finances.php");
    exit;
}

function recordPartnerPayment($data, $db) {
    $partner_id = (int)$data['partner_id'];
    $amount = (float)$data['amount'];
    
    if ($amount <= 0) {
        $_SESSION['error_message'] = "Le montant doit être supérieur à 0";
        header("Location: finances.php");
        exit;
    }
    
    $insert_id = $db->insert('partner_payments', [
        'partner_id' => $partner_id,
        'amount' => $amount,
        'payment_method' => trim($data['payment_method']),
        'payment_date' => $data['payment_date'] ?? date('Y-m-d'),
        'status' => 'paid',
        'notes' => trim($data['notes'] ?? ''),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($insert_id) {
        // Mark related commissions as paid
        $db->query("UPDATE partner_conversions SET status = 'paid' 
                   WHERE partner_id = ? AND status = 'approved'", [$partner_id]);
        
        $_SESSION['success_message'] = "Paiement enregistré avec succès";
        logAdminAction($db, "Recorded payment for partner ID: $partner_id - Amount: $amount");
    } else {
        $_SESSION['error_message'] = "Erreur lors de l'enregistrement du paiement";
    }
    
    header("Location: finances.php");
    exit;
}

function getFinancialStatistics($db, $date_range = 'this_month', $partner_id = 0) {
    $where_conditions = [];
    $params = [];
    
    // Date range conditions
    switch ($date_range) {
        case 'today':
            $where_conditions[] = "DATE(pc.created_at) = CURDATE()";
            break;
        case 'yesterday':
            $where_conditions[] = "DATE(pc.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $where_conditions[] = "YEARWEEK(pc.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'last_week':
            $where_conditions[] = "YEARWEEK(pc.created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
            break;
        case 'this_month':
            $where_conditions[] = "MONTH(pc.created_at) = MONTH(CURDATE()) AND YEAR(pc.created_at) = YEAR(CURDATE())";
            break;
        case 'last_month':
            $where_conditions[] = "MONTH(pc.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(pc.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            break;
    }
    
    // Partner filter
    if ($partner_id > 0) {
        $where_conditions[] = "pc.partner_id = ?";
        $params[] = $partner_id;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stats_query = "
        SELECT 
            -- Traffic stats
            (SELECT COUNT(*) FROM partner_traffic pt $where_clause) as total_traffic,
            
            -- Conversion stats
            COUNT(pc.id) as total_conversions,
            SUM(CASE WHEN pc.conversion_type = 'registration' THEN 1 ELSE 0 END) as registrations,
            SUM(CASE WHEN pc.conversion_type = 'trial' THEN 1 ELSE 0 END) as trials,
            SUM(CASE WHEN pc.conversion_type = 'paid_subscription' THEN 1 ELSE 0 END) as paid_subscriptions,
            
            -- Revenue stats
            COALESCE(SUM(pc.conversion_value), 0) as total_revenue,
            COALESCE(SUM(pc.commission_amount), 0) as total_commissions,
            
            -- Commission status
            SUM(CASE WHEN pc.status = 'pending' THEN pc.commission_amount ELSE 0 END) as pending_commissions,
            SUM(CASE WHEN pc.status = 'approved' THEN pc.commission_amount ELSE 0 END) as approved_commissions,
            SUM(CASE WHEN pc.status = 'paid' THEN pc.commission_amount ELSE 0 END) as paid_commissions,
            
            -- Conversion rate
            ROUND((COUNT(pc.id) * 100.0 / NULLIF((SELECT COUNT(*) FROM partner_traffic pt $where_clause), 0)), 2) as conversion_rate
            
        FROM partner_conversions pc
        $where_clause
    ";
    
    $result = $db->getThisQuery($stats_query, $params);
    return $result[0] ?? [];
}

function generateUniqueCode($db) {
    do {
        $code = 'PART' . strtoupper(substr(md5(uniqid()), 0, 8));
        $existing = $db->getThisQuery("SELECT id FROM commercial_partners WHERE unique_code = ?", [$code]);
    } while (!empty($existing));
    
    return $code;
}

function formatCurrency($amount) {
    $amount = is_null($amount) ? 0 : $amount;
    return number_format((float)$amount, 2, ',', ' ') . ' D.H';
}

function formatPercentage($value) {
    return number_format((float)$value, 2) . '%';
}

// Get top performers
$top_performers = $db->getThisQuery("
    SELECT 
        cp.name,
        cp.company,
        COUNT(pc.id) as conversions,
        COALESCE(SUM(pc.commission_amount), 0) as total_commissions,
        COALESCE(SUM(pc.conversion_value), 0) as total_revenue
    FROM commercial_partners cp
    LEFT JOIN partner_conversions pc ON cp.id = pc.partner_id
    WHERE pc.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY cp.id, cp.name, cp.company
    ORDER BY total_commissions DESC
    LIMIT 5
");

// Get recent conversions
$recent_conversions = $db->getThisQuery("
    SELECT 
        pc.*,
        cp.name as partner_name,
        u.name as user_name,
        p.name as plan_name
    FROM partner_conversions pc
    JOIN commercial_partners cp ON pc.partner_id = cp.id
    LEFT JOIN users u ON pc.user_id = u.id
    LEFT JOIN plans p ON pc.plan_id = p.id
    ORDER BY pc.created_at DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finances & Partenaires | Super Admin OBG</title>
    <link rel="stylesheet" href="../../assets/css/supperDash.css" />
    <link rel="stylesheet" href="../../assets/css/super.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css" rel="stylesheet">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--secondary-color);
            font-weight: 500;
        }
        
        .stat-change {
            font-size: 12px;
            font-weight: 600;
        }
        
        .positive { color: var(--success-color); }
        .negative { color: var(--danger-color); }
        
        .section-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .partner-link {
            background: #f8f9fa;
            border: 1px dashed #dee2e6;
            border-radius: 8px;
            padding: 12px;
            font-family: monospace;
            font-size: 14px;
            margin-top: 8px;
        }
        
        .conversion-badge {
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
        }
        
        .badge-registration { background: #e3f2fd; color: #1976d2; }
        .badge-trial { background: #fff3e0; color: #f57c00; }
        .badge-paid { background: #e8f5e8; color: #388e3c; }
        
        .commission-status {
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3e0; color: #f57c00; }
        .status-approved { background: #e3f2fd; color: #1976d2; }
        .status-paid { background: #e8f5e8; color: #388e3c; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'finances';
    require_once('../../assets/sidebarSuper.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <div class="navbar-title">
                <h1>
                    <i class="bi bi-graph-up"></i>
                    Finances & Partenaires Commerciaux
                </h1>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="section-card">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Période</label>
                        <select name="date_range" class="form-select">
                            <option value="today" <?= $date_range == 'today' ? 'selected' : '' ?>>Aujourd'hui</option>
                            <option value="yesterday" <?= $date_range == 'yesterday' ? 'selected' : '' ?>>Hier</option>
                            <option value="this_week" <?= $date_range == 'this_week' ? 'selected' : '' ?>>Cette semaine</option>
                            <option value="last_week" <?= $date_range == 'last_week' ? 'selected' : '' ?>>Semaine dernière</option>
                            <option value="this_month" <?= $date_range == 'this_month' ? 'selected' : '' ?>>Ce mois</option>
                            <option value="last_month" <?= $date_range == 'last_month' ? 'selected' : '' ?>>Mois dernier</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Partenaire</label>
                        <select name="partner_id" class="form-select">
                            <option value="0">Tous les partenaires</option>
                            <?php foreach ($partners as $partner): ?>
                                <option value="<?= $partner['id'] ?>" <?= $partner_id == $partner['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($partner['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Filtrer
                        </button>
                    </div>
                    
                    <div class="col-md-2">
                        <a href="finances.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-arrow-clockwise"></i> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= formatCurrency($stats['total_revenue'] ?? 0) ?></div>
                    <div class="stat-label">Chiffre d'Affaires</div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up"></i>
                        Revenus générés
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?= formatCurrency($stats['total_commissions'] ?? 0) ?></div>
                    <div class="stat-label">Commissions Totales</div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up"></i>
                        À payer aux partenaires
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_conversions'] ?? 0 ?></div>
                    <div class="stat-label">Conversions</div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up"></i>
                        <?= $stats['conversion_rate'] ?? 0 ?>% taux de conversion
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_traffic'] ?? 0 ?></div>
                    <div class="stat-label">Visites</div>
                    <div class="stat-label">
                        <?= $stats['registrations'] ?? 0 ?> inscriptions,
                        <?= $stats['trials'] ?? 0 ?> essais,
                        <?= $stats['paid_subscriptions'] ?? 0 ?> abonnements
                    </div>
                </div>
            </div>
            
            <!-- Two Column Layout -->
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Top Performers -->
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">Top Performers (30 jours)</h3>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Partenaire</th>
                                        <th>Conversions</th>
                                        <th>Revenus</th>
                                        <th>Commissions</th>
                                        <th>Performance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($top_performers)): ?>
                                        <?php foreach ($top_performers as $performer): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($performer['name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($performer['company']) ?></small>
                                                </td>
                                                <td><?= $performer['conversions'] ?></td>
                                                <td class="fw-semibold text-success"><?= formatCurrency($performer['total_revenue']) ?></td>
                                                <td class="fw-bold"><?= formatCurrency($performer['total_commissions']) ?></td>
                                                <td>
                                                    <?php if ($performer['total_commissions'] > 1000): ?>
                                                        <span class="badge bg-success">Excellent</span>
                                                    <?php elseif ($performer['total_commissions'] > 500): ?>
                                                        <span class="badge bg-warning">Bon</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">Standard</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">
                                                <i class="bi bi-graph-up"></i>
                                                <p>Aucune donnée de performance disponible</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Recent Conversions -->
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">Conversions Récentes</h3>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Partenaire</th>
                                        <th>Utilisateur</th>
                                        <th>Type</th>
                                        <th>Valeur</th>
                                        <th>Commission</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($recent_conversions)): ?>
                                        <?php foreach ($recent_conversions as $conversion): ?>
                                            <tr>
                                                <td><?= date('d/m/Y H:i', strtotime($conversion['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($conversion['partner_name']) ?></td>
                                                <td><?= htmlspecialchars($conversion['user_name'] ?? 'N/A') ?></td>
                                                <td>
                                                    <span class="conversion-badge badge-<?= $conversion['conversion_type'] ?>">
                                                        <?= $conversion['conversion_type'] ?>
                                                    </span>
                                                </td>
                                                <td class="fw-semibold"><?= formatCurrency($conversion['conversion_value']) ?></td>
                                                <td class="fw-bold text-success"><?= formatCurrency($conversion['commission_amount']) ?></td>
                                                <td>
                                                    <span class="commission-status status-<?= $conversion['status'] ?>">
                                                        <?= $conversion['status'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="bi bi-people"></i>
                                                <p>Aucune conversion récente</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">Actions Rapides</h3>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
                                <i class="bi bi-person-plus"></i> Ajouter un Partenaire
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                                <i class="bi bi-cash-coin"></i> Enregistrer un Paiement
                            </button>
                            <a href="#" class="btn btn-outline-primary">
                                <i class="bi bi-download"></i> Exporter Rapport
                            </a>
                        </div>
                    </div>
                    
                    <!-- Commission Summary -->
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">Commissions</h3>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>En attente:</span>
                                <strong class="text-warning"><?= formatCurrency($stats['pending_commissions'] ?? 0) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Approuvées:</span>
                                <strong class="text-primary"><?= formatCurrency($stats['approved_commissions'] ?? 0) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Payées:</span>
                                <strong class="text-success"><?= formatCurrency($stats['paid_commissions'] ?? 0) ?></strong>
                            </div>
                        </div>
                        
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: 30%"></div>
                            <div class="progress-bar bg-primary" style="width: 40%"></div>
                            <div class="progress-bar bg-success" style="width: 30%"></div>
                        </div>
                    </div>
                    
                    <!-- Active Partners -->
                    <div class="section-card">
                        <div class="section-header">
                            <h3 class="section-title">Partenaires Actifs</h3>
                        </div>
                        
                        <div class="list-group">
                            <?php if (!empty($partners)): foreach ($partners as $partner): ?>
                                <?php if ($partner['status'] == 'active'): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?= htmlspecialchars($partner['name']) ?></h6>
                                            <small><?= $partner['commission_rate'] ?>%</small>
                                        </div>
                                        <p class="mb-1 text-muted"><?= htmlspecialchars($partner['email']) ?></p>
                                        <small class="text-muted">
                                            Code: <code><?= $partner['unique_code'] ?></code>
                                        </small>
                                        <div class="partner-link">
                                            https://obg.ma/register?ref=<?= $partner['unique_code'] ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Partner Modal -->
    <div class="modal fade" id="addPartnerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un Partenaire Commercial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_partner">
                        
                        <div class="mb-3">
                            <label class="form-label">Nom complet *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Téléphone</label>
                                    <input type="text" name="phone" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Entreprise</label>
                                    <input type="text" name="company" class="form-control">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Taux de Commission (%) *</label>
                            <input type="number" name="commission_rate" class="form-control" step="0.01" min="0" max="100" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div class="modal fade" id="recordPaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enregistrer un Paiement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="record_payment">
                        
                        <div class="mb-3">
                            <label class="form-label">Partenaire *</label>
                            <select name="partner_id" class="form-select" required>
                                <option value="">Sélectionner un partenaire</option>
                                <?php foreach ($partners as $partner): ?>
                                    <option value="<?= $partner['id'] ?>"><?= htmlspecialchars($partner['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Montant (D.H) *</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Méthode de Paiement</label>
                            <select name="payment_method" class="form-select">
                                <option value="bank_transfer">Virement Bancaire</option>
                                <option value="cash">Espèces</option>
                                <option value="check">Chèque</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Date du Paiement</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Copy referral link functionality
        document.querySelectorAll('.partner-link').forEach(function(element) {
            element.addEventListener('click', function() {
                const text = this.textContent;
                navigator.clipboard.writeText(text).then(function() {
                    // Show copied feedback
                    const original = element.textContent;
                    element.textContent = 'Lien copié!';
                    setTimeout(function() {
                        element.textContent = original;
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>