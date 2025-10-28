<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// Check if agent is logged in
if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../../login.php'); 
} 

if (isset($_GET['logout'])) {
    logout();
}

function getCurrentAgentInfo($db) {
    if (!isset($_SESSION['user'])) {
        return null;
    }

    $agent_email = $_SESSION['user']['username'];
    
    // Get agent from agents table
    $agent = $db->getThisQuery("
        SELECT id, user_id, name, email, is_active 
        FROM agents 
        WHERE email = ? AND is_active = 1
    ", [$agent_email]);

    if (empty($agent)) {
        return null;
    }

    return $agent[0]['id'];
}

$db = DB::getInstance();
$agent_id = getCurrentAgentInfo($db);

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../../login.php');
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $update_data = [
        'name' => $_POST['name'],
        'phone' => $_POST['phone'],
        'address' => $_POST['address'],
        'city' => $_POST['city'],
        'bank_name' => $_POST['bank_name'],
        'bank_account' => $_POST['bank_account']
    ];
    
    // Handle password change if provided
    if (!empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $update_data['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        } else {
            Redirect::to('agent_profile.php');
        }
    }
    
    // Handle file upload
    if (!empty($_FILES['profile_image']['name'])) {
        $target_dir = "../../uploads/agents/";
        $target_file = $target_dir . basename($_FILES["profile_image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
        if ($check !== false) {
            // Generate unique filename
            $new_filename = "agent_" . $agent_id . "_" . time() . "." . $imageFileType;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                $update_data['profile_image'] = $new_filename;
            } else {
                Session::flash('error', 'Erreur lors du téléchargement de l\'image');
            }
        } else {
            Session::flash('error', 'Le fichier n\'est pas une image valide');
        }
    }
    
    // Update agent data
    if ($db->update("agents", $agent_id, $update_data)) {
        Redirect::to('agent_profile.php');
    } else {
        //Session::flash('error', 'Erreur lors de la mise à jour du profil');
    }
}

// Get agent info
$agent_query = "SELECT * FROM agents WHERE id = ?";
$agent_info = $db->getThisQuery($agent_query, [$agent_id]);
$agent = $agent_info[0] ?? null;

if (!$agent) {
    session_destroy();
    header('Location: ../../login.php');
    exit;
}

// Get selected period from URL parameter
$selected_period = $_GET['period'] ?? 'current_month';

// Define period configurations
$periods = [
    'today' => [
        'label' => 'Aujourd\'hui',
        'where_clause' => 'DATE(updated_at) = CURDATE()',
        'group_by' => 'HOUR(updated_at)',
        'date_format' => '%H:00'
    ],
    'this_week' => [
        'label' => 'Cette semaine',
        'where_clause' => 'WEEK(updated_at) = WEEK(NOW()) AND YEAR(updated_at) = YEAR(NOW())',
        'group_by' => 'DATE(updated_at)',
        'date_format' => '%d/%m'
    ],
    'current_month' => [
        'label' => 'Ce mois',
        'where_clause' => 'DATE_FORMAT(updated_at, \'%Y-%m\') = DATE_FORMAT(NOW(), \'%Y-%m\')',
        'group_by' => 'DATE(updated_at)',
        'date_format' => '%d/%m'
    ],
    'last_month' => [
        'label' => 'Mois dernier',
        'where_clause' => 'DATE_FORMAT(updated_at, \'%Y-%m\') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m\')',
        'group_by' => 'DATE(updated_at)',
        'date_format' => '%d/%m'
    ],
    'last_3_months' => [
        'label' => '3 derniers mois',
        'where_clause' => 'updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)',
        'group_by' => 'DATE_FORMAT(updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ],
    'last_6_months' => [
        'label' => '6 derniers mois',
        'where_clause' => 'updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)',
        'group_by' => 'DATE_FORMAT(updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ],
    'this_year' => [
        'label' => 'Cette année',
        'where_clause' => 'YEAR(updated_at) = YEAR(NOW())',
        'group_by' => 'DATE_FORMAT(updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ],
    'last_year' => [
        'label' => 'Année dernière',
        'where_clause' => 'YEAR(updated_at) = YEAR(NOW()) - 1',
        'group_by' => 'DATE_FORMAT(updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ],
    'all_time' => [
        'label' => 'Tout le temps',
        'where_clause' => '1=1',
        'group_by' => 'DATE_FORMAT(updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ]
];

// Get agent statistics for selected period
$stats = getAgentDetailedStats($db, $agent_id, $selected_period, $periods);

function getAgentDetailedStats($db, $agent_id, $selected_period, $periods) {
    try {
        $period_config = $periods[$selected_period];
        $where_clause = $period_config['where_clause'];
        
        // Total orders handled in selected period
        $total_orders = $db->getThisQuery("
            SELECT COUNT(*) as total 
            FROM agent_order_assignments aoa
            JOIN orders o ON aoa.order_id = o.id
            WHERE aoa.agent_id = ? AND {$where_clause}
        ", [$agent_id])[0]['total'];
        
        // Confirmed orders in selected period
        $confirmed_orders = $db->getThisQuery("
            SELECT COUNT(*) as total 
            FROM orders 
            WHERE confirmed_by_agent = ? AND {$where_clause}
        ", [$agent_id])[0]['total'];
        
        // Factured orders in selected period
        $factured_orders = $db->getThisQuery("
            SELECT COUNT(*) as total 
            FROM orders 
            WHERE confirmed_by_agent = ? 
            AND shipping_status = 'delivered' 
            AND payment_status = 'paid'
            AND {$where_clause}
        ", [$agent_id])[0]['total'];
        
        // Cancelled orders in selected period
        $cancelled_orders = $db->getThisQuery("
            SELECT COUNT(*) as total 
            FROM orders 
            WHERE confirmed_by_agent = ? 
            AND status = 'cancelled'
            AND {$where_clause}
        ", [$agent_id])[0]['total'];
        
        // Amount earned in selected period
        $amount_earned = $db->getThisQuery("
            SELECT SUM(commission_amount) as total 
            FROM agent_commissions ac
            JOIN orders o ON ac.order_id = o.id
            WHERE ac.agent_id = ? 
            AND ac.status = 'paid'
            AND {$where_clause}
        ", [$agent_id])[0]['total'] ?? 0;
        
        // Pending amount in selected period
        $pending_amount = $db->getThisQuery("
            SELECT SUM(commission_amount) as total 
            FROM agent_commissions ac
            JOIN orders o ON ac.order_id = o.id
            WHERE ac.agent_id = ? 
            AND ac.status = 'pending'
            AND {$where_clause}
        ", [$agent_id])[0]['total'] ?? 0;
        
        // Average order value in selected period
        $avg_order_value = $db->getThisQuery("
            SELECT AVG(total_amount) as avg_value 
            FROM orders 
            WHERE confirmed_by_agent = ? AND {$where_clause}
        ", [$agent_id])[0]['avg_value'] ?? 0;
        
        // Performance metrics
        $performance_score = $db->getThisQuery("
            SELECT score 
            FROM agents 
            WHERE id = ?
        ", [$agent_id])[0]['score'] ?? 0;
        
        // Calculate confirmation rate for selected period
        $confirmation_rate = $total_orders > 0 ? round(($confirmed_orders / $total_orders) * 100, 2) : 0;
        
        // Recent activity for selected period
        $recent_activity = $db->getThisQuery("
            SELECT 
                o.order_number,
                o.status,
                o.shipping_status,
                o.updated_at,
                a.action_type,
                a.description,
                a.created_at
            FROM agent_activity_log a
            LEFT JOIN orders o ON a.order_id = o.id
            WHERE a.agent_id = ? AND {$where_clause}
            ORDER BY a.created_at DESC
            LIMIT 15
        ", [$agent_id]);
        
        return [
            'total_orders' => $total_orders,
            'confirmed_orders' => $confirmed_orders,
            'factured_orders' => $factured_orders,
            'cancelled_orders' => $cancelled_orders,
            'amount_earned' => $amount_earned,
            'pending_amount' => $pending_amount,
            'avg_order_value' => $avg_order_value,
            'performance_score' => $performance_score,
            'confirmation_rate' => $confirmation_rate,
            'recent_activity' => $recent_activity,
            'period_label' => $periods[$selected_period]['label']
        ];
        
    } catch (Exception $e) {
        return [
            'error' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
        ];
    }
}

// Get performance data for chart based on selected period
$period_config = $periods[$selected_period];
$group_by = $period_config['group_by'];
$date_format = $period_config['date_format'];
$where_clause = $period_config['where_clause'];

$performance_data = $db->getThisQuery("
    SELECT 
        {$group_by} as period_group,
        DATE_FORMAT(o.updated_at, '{$date_format}') as period_label,
        COUNT(*) as confirmed_count,
        SUM(CASE WHEN o.shipping_status = 'delivered' AND o.payment_status = 'paid' THEN 1 ELSE 0 END) as factured_count,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
        AVG(o.total_amount) as avg_amount
    FROM orders o
    WHERE o.confirmed_by_agent = ? AND {$where_clause}
    GROUP BY {$group_by}
    ORDER BY {$group_by} ASC
", [$agent_id]);

// Prepare data for charts
$chart_labels = [];
$confirmed_data = [];
$factured_data = [];
$cancelled_data = [];
$avg_amount_data = [];

foreach ($performance_data as $data) {
    $chart_labels[] = $data['period_label'];
    $confirmed_data[] = $data['confirmed_count'];
    $factured_data[] = $data['factured_count'];
    $cancelled_data[] = $data['cancelled_count'];
    $avg_amount_data[] = round($data['avg_amount'], 2);
}

// Handle AJAX requests for period changes
if (isset($_POST['ajax']) && $_POST['ajax'] == 'get_stats') {
    header('Content-Type: application/json');
    echo json_encode([
        'stats' => $stats,
        'chart_data' => [
            'labels' => $chart_labels,
            'confirmed' => $confirmed_data,
            'factured' => $factured_data,
            'cancelled' => $cancelled_data,
            'avg_amount' => $avg_amount_data
        ]
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Agent | <?php echo htmlspecialchars($agent['name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/rich.css" />
    <style>
        :root {
            --primary-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #8b5cf6;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f3f4f6;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            z-index: 0;
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: var(--box-shadow);
            position: relative;
            z-index: 1;
        }

        .profile-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 1rem;
            position: relative;
            z-index: 1;
        }

        .profile-email {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .profile-score {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: white;
            color: var(--dark-color);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            box-shadow: var(--box-shadow);
            z-index: 1;
        }

        .period-filter {
            background: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
        }

        .period-select {
            min-width: 200px;
        }

        .export-btn {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            color: white;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
        }

        .stat-card {
            text-align: center;
            padding: 1.5rem;
            border-left: 4px solid;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .stat-card.primary {
            border-left-color: var(--primary-color);
        }

        .stat-card.success {
            border-left-color: var(--success-color);
        }

        .stat-card.warning {
            border-left-color: var(--warning-color);
        }

        .stat-card.danger {
            border-left-color: var(--danger-color);
        }

        .stat-card.info {
            border-left-color: var(--info-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .stat-card.primary .stat-value {
            color: var(--primary-color);
        }

        .stat-card.success .stat-value {
            color: var(--success-color);
        }

        .stat-card.warning .stat-value {
            color: var(--warning-color);
        }

        .stat-card.danger .stat-value {
            color: var(--danger-color);
        }

        .stat-card.info .stat-value {
            color: var(--info-color);
        }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            transition: background-color 0.3s ease;
        }

        .activity-item:hover {
            background-color: #f9fafb;
            border-radius: 8px;
            margin: 0 -1rem;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .activity-icon.confirmed {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .activity-icon.shipped {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .activity-icon.cancelled {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .activity-icon.default {
            background-color: rgba(139, 92, 246, 0.1);
            color: var(--info-color);
        }

        .activity-content {
            flex-grow: 1;
        }

        .activity-order {
            font-weight: 600;
            color: var(--dark-color);
        }

        .activity-date {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
        }

        .nav-pills .nav-link {
            color: var(--dark-color);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .progress {
            height: 10px;
            border-radius: 5px;
        }

        .progress-bar {
            background-color: var(--primary-color);
        }

        .confirmation-rate {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: var(--border-radius);
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .period-info {
            background: linear-gradient(45deg, #e0e7ff, #f0f4ff);
            border: 1px solid #c7d2fe;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            color: #4338ca;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .profile-header {
                text-align: center;
            }
            
            .profile-image {
                margin: 0 auto;
            }
            
            .profile-score {
                position: relative;
                top: auto;
                right: auto;
                display: inline-block;
                margin-top: 1rem;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }

            .period-filter {
                text-align: center;
            }

            .period-select {
                min-width: 100%;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-speedometer2"></i> Agent Dashboard
            </a>
            
            <div class="navbar-nav ms-auto d-flex flex-row align-items-center">
                <span class="navbar-text me-3">
                    Bonjour, <strong><?php echo htmlspecialchars($agent['name']); ?></strong>
                </span>
               
                <a href="index.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-house"></i> Accueil
                </a>

                <a href="?logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Profile Header -->
        <div class="profile-header text-center">
            <div class="profile-score">
                <i class="bi bi-star-fill"></i> Score: <?php echo htmlspecialchars($agent['score'] ?? '0'); ?>
            </div>
            <img src="<?php echo !empty($agent['profile_image']) ? '../../uploads/agents/' . htmlspecialchars($agent['profile_image']) : '../../assets/images/default-avatar.jpg'; ?>" 
                 alt="Profile Image" class="profile-image">
            <h1 class="profile-name"><?php echo htmlspecialchars($agent['name']); ?></h1>
            <p class="profile-email"><?php echo htmlspecialchars($agent['email']); ?></p>
        </div>

        <!-- Period Filter -->
        <div class="period-filter d-flex justify-content-between align-items-center flex-wrap">
            <div class="d-flex align-items-center">
                <label for="periodSelect" class="form-label me-3 mb-0">
                    <i class="bi bi-calendar3"></i> Période:
                </label>
                <select class="form-select period-select" id="periodSelect">
                    <?php foreach ($periods as $key => $period): ?>
                        <option value="<?php echo $key; ?>" <?php echo $key === $selected_period ? 'selected' : ''; ?>>
                            <?php echo $period['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <button class="export-btn" onclick="exportStats()">
                    <i class="bi bi-download"></i> Exporter
                </button>
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimer
                </button>
            </div>
        </div>

        <!-- Period Info -->
        <div class="period-info">
            <i class="bi bi-info-circle"></i>
            Statistiques pour: <strong><?php echo $stats['period_label']; ?></strong>
        </div>

        <!-- Loading Overlay -->
        <div id="loadingOverlay" class="loading-overlay" style="display: none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <!-- Left Column - Profile Info -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-person-lines-fill"></i> Informations du profil
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nom complet</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($agent['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($agent['email']); ?>" disabled>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($agent['phone']); ?>">
                            </div>
                            
                            
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Photo de profil</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary w-100">
                                <i class="bi bi-save"></i> Mettre à jour le profil
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Confirmation Rate -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-check-circle"></i> Taux de confirmation
                    </div>
                    <div class="card-body text-center">
                        <div class="confirmation-rate mb-2" id="confirmationRate">
                            <?php echo htmlspecialchars($stats['confirmation_rate'] ?? '0'); ?>%
                        </div>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo htmlspecialchars($stats['confirmation_rate'] ?? '0'); ?>%" 
                                 aria-valuenow="<?php echo htmlspecialchars($stats['confirmation_rate'] ?? '0'); ?>" 
                                 aria-valuemin="0" aria-valuemax="100"
                                 id="confirmationProgressBar">
                            </div>
                        </div>
                        <p class="mt-2 mb-0 text-muted small">
                            Pourcentage de commandes confirmées avec succès
                        </p>
                    </div>
                </div>

                <!-- Quick Summary -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-speedometer2"></i> Résumé rapide
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Valeur moy. commande:</span>
                            <strong id="avgOrderValue"><?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?> MAD</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Taux d'annulation:</span>
                            <strong id="cancellationRate">
                                <?php 
                                $cancellation_rate = $stats['total_orders'] > 0 ? round(($stats['cancelled_orders'] / $stats['total_orders']) * 100, 2) : 0;
                                echo $cancellation_rate; 
                                ?>%
                            </strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Score performance:</span>
                            <strong class="text-primary"><?php echo htmlspecialchars($stats['performance_score'] ?? '0'); ?>/100</strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Stats and Activity -->
            <div class="col-lg-8">
                <!-- Stats Cards -->
                <div class="row" id="statsContainer">
                    <div class="col-md-6 col-xl-3">
                        <div class="card stat-card primary">
                            <div class="stat-value" id="totalOrders"><?php echo htmlspecialchars($stats['total_orders'] ?? '0'); ?></div>
                            <div class="stat-label">Commandes assignées</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card stat-card success">
                            <div class="stat-value" id="confirmedOrders"><?php echo htmlspecialchars($stats['confirmed_orders'] ?? '0'); ?></div>
                            <div class="stat-label">Commandes confirmées</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card stat-card info">
                            <div class="stat-value" id="facturedOrders"><?php echo htmlspecialchars($stats['factured_orders'] ?? '0'); ?></div>
                            <div class="stat-label">Commandes facturées</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card stat-card danger">
                            <div class="stat-value" id="cancelledOrders"><?php echo htmlspecialchars($stats['cancelled_orders'] ?? '0'); ?></div>
                            <div class="stat-label">Commandes annulées</div>
                        </div>
                    </div>
                </div>

                <!-- Financial Stats -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card stat-card warning">
                            <div class="stat-value" id="amountEarned"><?php echo number_format($stats['amount_earned'] ?? 0, 2); ?> MAD</div>
                            <div class="stat-label">Montant gagné</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card info">
                            <div class="stat-value" id="pendingAmount"><?php echo number_format($stats['pending_amount'] ?? 0, 2); ?> MAD</div>
                            <div class="stat-label">Montant en attente</div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Charts -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-bar-chart"></i> Performance détaillée
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="chartType" id="barChart" checked>
                                    <label class="btn btn-outline-primary" for="barChart">
                                        <i class="bi bi-bar-chart"></i>
                                    </label>
                                    <input type="radio" class="btn-check" name="chartType" id="lineChart">
                                    <label class="btn btn-outline-primary" for="lineChart">
                                        <i class="bi bi-graph-up"></i>
                                    </label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="bi bi-pie-chart"></i> Répartition
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 250px;">
                                    <canvas id="distributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comparative Stats -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-graph-up-arrow"></i> Comparaison avec la période précédente
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center p-3">
                                    <div class="h5 mb-1" id="ordersGrowth">+0%</div>
                                    <div class="text-muted small">Commandes</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3">
                                    <div class="h5 mb-1" id="confirmationGrowth">+0%</div>
                                    <div class="text-muted small">Confirmations</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3">
                                    <div class="h5 mb-1" id="revenueGrowth">+0%</div>
                                    <div class="text-muted small">Revenus</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center p-3">
                                    <div class="h5 mb-1" id="performanceGrowth">+0%</div>
                                    <div class="text-muted small">Performance</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-clock-history"></i> Activité récente
                        </div>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshActivity()">
                            <i class="bi bi-arrow-clockwise"></i> Actualiser
                        </button>
                    </div>
                    <div class="card-body" id="activityContainer">
                        <?php if (!empty($stats['recent_activity'])): ?>
                            <?php foreach ($stats['recent_activity'] as $activity): ?>
                                <div class="activity-item">
                                    <?php
                                    $icon_class = 'default';
                                    $icon = 'bi bi-activity';
                                    
                                    if (strpos($activity['action_type'] ?? '', 'confirm') !== false) {
                                        $icon_class = 'confirmed';
                                        $icon = 'bi bi-check-circle';
                                    } elseif (strpos($activity['action_type'] ?? '', 'ship') !== false) {
                                        $icon_class = 'shipped';
                                        $icon = 'bi bi-truck';
                                    } elseif (strpos($activity['action_type'] ?? '', 'cancel') !== false) {
                                        $icon_class = 'cancelled';
                                        $icon = 'bi bi-x-circle';
                                    }
                                    ?>
                                    <div class="activity-icon <?php echo $icon_class; ?>">
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-order">
                                            <?php echo htmlspecialchars($activity['description'] ?? 'Activité'); ?>
                                            <?php if (!empty($activity['order_number'])): ?>
                                                (<?php echo htmlspecialchars($activity['order_number']); ?>)
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-date">
                                            <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">Aucune activité récente pour cette période</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let performanceChart;
        let distributionChart;
        
        // Initialize charts
        function initializeCharts() {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const distributionCtx = document.getElementById('distributionChart').getContext('2d');
            
            // Performance Chart
            performanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [
                        {
                            label: 'Confirmées',
                            data: <?php echo json_encode($confirmed_data); ?>,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Facturées',
                            data: <?php echo json_encode($factured_data); ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Annulées',
                            data: <?php echo json_encode($cancelled_data); ?>,
                            backgroundColor: 'rgba(239, 68, 68, 0.7)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
            
            // Distribution Chart
            const totalOrders = <?php echo $stats['total_orders']; ?>;
            const confirmedOrders = <?php echo $stats['confirmed_orders']; ?>;
            const facturedOrders = <?php echo $stats['factured_orders']; ?>;
            const cancelledOrders = <?php echo $stats['cancelled_orders']; ?>;
            
            distributionChart = new Chart(distributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Confirmées', 'Facturées', 'Annulées', 'En attente'],
                    datasets: [{
                        data: [
                            confirmedOrders,
                            facturedOrders,
                            cancelledOrders,
                            totalOrders - confirmedOrders - cancelledOrders
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(156, 163, 175, 0.8)'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.raw + ' (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }
        
        // Handle period change
        $('#periodSelect').on('change', function() {
            const selectedPeriod = $(this).val();
            loadPeriodData(selectedPeriod);
        });
        
        // Chart type change
        $('input[name="chartType"]').on('change', function() {
            const chartType = $(this).attr('id') === 'barChart' ? 'bar' : 'line';
            performanceChart.config.type = chartType;
            performanceChart.update('active');
        });
        
        function loadPeriodData(period) {
            $('#loadingOverlay').show();
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    ajax: 'get_stats',
                    period: period
                },
                dataType: 'json',
                success: function(response) {
                    updateStats(response.stats);
                    updateCharts(response.chart_data);
                    
                    // Update URL without page refresh
                    const url = new URL(window.location);
                    url.searchParams.set('period', period);
                    window.history.pushState({}, '', url);
                    
                    $('#loadingOverlay').hide();
                },
                error: function() {
                    alert('Erreur lors du chargement des données');
                    $('#loadingOverlay').hide();
                }
            });
        }
        
        function updateStats(stats) {
            $('#totalOrders').text(stats.total_orders);
            $('#confirmedOrders').text(stats.confirmed_orders);
            $('#facturedOrders').text(stats.factured_orders);
            $('#cancelledOrders').text(stats.cancelled_orders);
            $('#amountEarned').text(parseFloat(stats.amount_earned).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' MAD');
            $('#pendingAmount').text(parseFloat(stats.pending_amount).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' MAD');
            $('#confirmationRate').text(stats.confirmation_rate + '%');
            $('#avgOrderValue').text(parseFloat(stats.avg_order_value).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' MAD');
            
            // Update progress bar
            $('#confirmationProgressBar').css('width', stats.confirmation_rate + '%').attr('aria-valuenow', stats.confirmation_rate);
            
            // Update cancellation rate
            const cancellationRate = stats.total_orders > 0 ? ((stats.cancelled_orders / stats.total_orders) * 100).toFixed(2) : 0;
            $('#cancellationRate').text(cancellationRate + '%');
        }
        
        function updateCharts(chartData) {
            // Update performance chart
            performanceChart.data.labels = chartData.labels;
            performanceChart.data.datasets[0].data = chartData.confirmed;
            performanceChart.data.datasets[1].data = chartData.factured;
            performanceChart.data.datasets[2].data = chartData.cancelled;
            performanceChart.update('active');
            
            // Update distribution chart
            const totalOrders = chartData.confirmed.reduce((a, b) => a + b, 0) + 
                              chartData.factured.reduce((a, b) => a + b, 0) + 
                              chartData.cancelled.reduce((a, b) => a + b, 0);
            
            distributionChart.data.datasets[0].data = [
                chartData.confirmed.reduce((a, b) => a + b, 0),
                chartData.factured.reduce((a, b) => a + b, 0),
                chartData.cancelled.reduce((a, b) => a + b, 0),
                Math.max(0, totalOrders - chartData.confirmed.reduce((a, b) => a + b, 0) - chartData.cancelled.reduce((a, b) => a + b, 0))
            ];
            distributionChart.update('active');
        }
        
        function exportStats() {
            const period = $('#periodSelect').val();
            const periodLabel = $('#periodSelect option:selected').text();
            
            // Create export data
            const exportData = {
                period: periodLabel,
                stats: {
                    total_orders: $('#totalOrders').text(),
                    confirmed_orders: $('#confirmedOrders').text(),
                    factured_orders: $('#facturedOrders').text(),
                    cancelled_orders: $('#cancelledOrders').text(),
                    amount_earned: $('#amountEarned').text(),
                    pending_amount: $('#pendingAmount').text(),
                    confirmation_rate: $('#confirmationRate').text(),
                    avg_order_value: $('#avgOrderValue').text()
                },
                export_date: new Date().toLocaleString('fr-FR')
            };
            
            // Create and download JSON file
            const dataStr = JSON.stringify(exportData, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `agent_stats_${period}_${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            URL.revokeObjectURL(url);
        }
        
        function refreshActivity() {
            const period = $('#periodSelect').val();
            loadPeriodData(period);
        }
        
        // Initialize charts when page loads
        $(document).ready(function() {
            initializeCharts();
        });
        
        // Show flash messages
        <?php if (Session::exists('success')): ?>
            alert('<?php echo Session::flash('success'); ?>');
        <?php elseif (Session::exists('error')): ?>
            alert('<?php echo Session::flash('error'); ?>');
        <?php endif; ?>
    </script>
</body>
</html>