<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// Check if agent is logged in
if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../../login.php'); 
}

// Handle logout
if (isset($_GET['logout'])) {
    $user = new User();
    $user->logout();
    Redirect::to('../../login.php');
}

// Get current agent info
function getCurrentAgentInfo($db) {
    if (!isset($_SESSION['user'])) {
        return null;
    }

    $agent_email = $_SESSION['user']['username'];
    
    // Get agent from agents table with responsible info
    $agent = $db->getThisQuery("
        SELECT a.*, r.name as responsable_name, r.email as responsable_email, r.phone as responsable_phone,
               t.name as team_name
        FROM agents a
        LEFT JOIN agents r ON a.responsable_id = r.id
        LEFT JOIN teams t ON a.team_id = t.id
        WHERE a.email = ? AND a.is_active = 1
    ", [$agent_email]);

    if (empty($agent)) {
        return null;
    }

    return $agent[0];
}

$db = DB::getInstance();
$agent_info = getCurrentAgentInfo($db);

// Redirect if agent not found
if (!$agent_info) {
    session_destroy();
    Redirect::to('../../login.php');
}

$agent_id = $agent_info['id'];

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $errors = [];
    $success = [];
    
    // Basic validation
    $update_data = [
        'name' => trim($_POST['name']),
        'phone' => trim($_POST['phone']),
        'address' => trim($_POST['address']),
        'city' => trim($_POST['city']),
        'bank_name' => trim($_POST['bank_name']),
        'bank_account' => trim($_POST['bank_account']),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Validate required fields
    if (empty($update_data['name'])) {
        $errors[] = 'Le nom est requis';
    }
    
    if (empty($update_data['phone'])) {
        $errors[] = 'Le téléphone est requis';
    }
    
    // Handle password change if provided
    if (!empty($_POST['new_password'])) {
        if (empty($_POST['confirm_password'])) {
            $errors[] = 'Veuillez confirmer le mot de passe';
        } elseif ($_POST['new_password'] !== $_POST['confirm_password']) {
            $errors[] = 'Les mots de passe ne correspondent pas';
        } elseif (strlen($_POST['new_password']) < 6) {
            $errors[] = 'Le mot de passe doit contenir au moins 6 caractères';
        } else {
            $update_data['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $success[] = 'Mot de passe mis à jour avec succès';
        }
    }
    
    // Handle file upload
    if (!empty($_FILES['profile_image']['name'])) {
        $target_dir = "../../uploads/agents/";
        
        // Create directory if it doesn't exist
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $imageFileType = strtolower(pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES["profile_image"]["tmp_name"]);
        if ($check === false) {
            $errors[] = 'Le fichier n\'est pas une image valide';
        } elseif (!in_array($imageFileType, $allowed_types)) {
            $errors[] = 'Seuls les fichiers JPG, JPEG, PNG et GIF sont autorisés';
        } elseif ($_FILES["profile_image"]["size"] > 5000000) { // 5MB limit
            $errors[] = 'L\'image est trop volumineuse (max 5MB)';
        } else {
            // Delete old profile image if exists
            if (!empty($agent_info['profile_image']) && file_exists($target_dir . $agent_info['profile_image'])) {
                unlink($target_dir . $agent_info['profile_image']);
            }
            
            // Generate unique filename
            $new_filename = "agent_" . $agent_id . "_" . time() . "." . $imageFileType;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                $update_data['profile_image'] = $new_filename;
                $success[] = 'Photo de profil mise à jour avec succès';
            } else {
                $errors[] = 'Erreur lors du téléchargement de l\'image';
            }
        }
    }
    
    // Update agent data if no errors
    if (empty($errors)) {
        if ($db->update("agents", $agent_id, $update_data)) {
            // Refresh agent info
            $agent_info = getCurrentAgentInfo($db);
            $success[] = 'Profil mis à jour avec succès';
            
            // Store success messages
            if (!empty($success)) {
                Session::flash('success', implode('<br>', $success));
            }
        } else {
            $errors[] = 'Erreur lors de la mise à jour du profil';
        }
    }
    
    // Store error messages
    if (!empty($errors)) {
        Session::flash('error', implode('<br>', $errors));
    }
    
    Redirect::to('agent_profile.php');
}

// Get selected period from URL parameter
$selected_period = $_GET['period'] ?? 'current_month';

// Define period configurations
$periods = [
    'today' => [
        'label' => 'Aujourd\'hui',
        'where_clause' => 'DATE(o.updated_at) = CURDATE()',
        'group_by' => 'HOUR(o.updated_at)',
        'date_format' => '%H:00'
    ],
    'this_week' => [
        'label' => 'Cette semaine',
        'where_clause' => 'WEEK(o.updated_at) = WEEK(NOW()) AND YEAR(o.updated_at) = YEAR(NOW())',
        'group_by' => 'DATE(o.updated_at)',
        'date_format' => '%d/%m'
    ],
    'current_month' => [
        'label' => 'Ce mois',
        'where_clause' => 'DATE_FORMAT(o.updated_at, \'%Y-%m\') = DATE_FORMAT(NOW(), \'%Y-%m\')',
        'group_by' => 'DATE(o.updated_at)',
        'date_format' => '%d/%m'
    ],
    'last_month' => [
        'label' => 'Mois dernier',
        'where_clause' => 'DATE_FORMAT(o.updated_at, \'%Y-%m\') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), \'%Y-%m\')',
        'group_by' => 'DATE(o.updated_at)',
        'date_format' => '%d/%m'
    ],
    'last_3_months' => [
        'label' => '3 derniers mois',
        'where_clause' => 'o.updated_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)',
        'group_by' => 'DATE_FORMAT(o.updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ],
    'last_6_months' => [
        'label' => '6 derniers mois',
        'where_clause' => 'o.updated_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)',
        'group_by' => 'DATE_FORMAT(o.updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ],
    'this_year' => [
        'label' => 'Cette année',
        'where_clause' => 'YEAR(o.updated_at) = YEAR(NOW())',
        'group_by' => 'DATE_FORMAT(o.updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ],
    'last_year' => [
        'label' => 'Année dernière',
        'where_clause' => 'YEAR(o.updated_at) = YEAR(NOW()) - 1',
        'group_by' => 'DATE_FORMAT(o.updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ],
    'all_time' => [
        'label' => 'Tout le temps',
        'where_clause' => '1=1',
        'group_by' => 'DATE_FORMAT(o.updated_at, \'%Y-%m\')',
        'date_format' => '%m/%Y'
    ]
];

// Get agent statistics for selected period
function getAgentDetailedStats($db, $agent_id, $selected_period, $periods) {
    try {
        $period_config = $periods[$selected_period];
        $where_clause = $period_config['where_clause'];
        
        // Total assigned orders in selected period
        $total_orders = $db->getThisQuery("
            SELECT COUNT(*) as total 
            FROM agent_order_assignments aoa
            JOIN orders o ON aoa.order_id = o.id
            WHERE aoa.agent_id = ? AND {$where_clause}
        ", [$agent_id])[0]['total'] ?? 0;
        
        // Confirmed orders in selected period
        $confirmed_orders = $db->getThisQuery("
            SELECT COUNT(*) as total 
            FROM orders 
            WHERE confirmed_by_agent = ? AND {$where_clause}
        ", [$agent_id])[0]['total'] ?? 0;
        
        // Delivered & paid orders in selected period
        $delivered_orders = $db->getThisQuery("
            SELECT COUNT(*) as total 
            FROM orders 
            WHERE confirmed_by_agent = ? 
            AND shipping_status = 'delivered' 
            AND payment_status = 'paid'
            AND {$where_clause}
        ", [$agent_id])[0]['total'] ?? 0;
        
        // Cancelled orders in selected period
        $cancelled_orders = $db->getThisQuery("
            SELECT COUNT(*) as total 
            FROM orders 
            WHERE confirmed_by_agent = ? 
            AND status = 'cancelled'
            AND {$where_clause}
        ", [$agent_id])[0]['total'] ?? 0;
        
        // Pending orders
        $pending_orders = $total_orders - $confirmed_orders - $cancelled_orders;
        if ($pending_orders < 0) $pending_orders = 0;
        
        // Amount earned in selected period
        $amount_earned = $db->getThisQuery("
            SELECT SUM(ac.commission_amount) as total 
            FROM agent_commissions ac
            JOIN orders o ON ac.order_id = o.id
            WHERE ac.agent_id = ? 
            AND ac.status = 'paid'
            AND {$where_clause}
        ", [$agent_id])[0]['total'] ?? 0;
        
        // Pending amount in selected period
        $pending_amount = $db->getThisQuery("
            SELECT SUM(ac.commission_amount) as total 
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
        
        // Calculate confirmation rate for selected period
        $confirmation_rate = $total_orders > 0 ? round(($confirmed_orders / $total_orders) * 100, 2) : 0;
        
        // Recent activity for selected period
        $recent_activity = $db->getThisQuery("
            SELECT 
                o.order_number,
                o.status,
                o.shipping_status,
                o.total_amount,
                aoa.assigned_at,
                aoa.confirmed_at,
                aoa.status as assignment_status
            FROM agent_order_assignments aoa
            JOIN orders o ON aoa.order_id = o.id
            WHERE aoa.agent_id = ? AND {$where_clause}
            ORDER BY aoa.assigned_at DESC
            LIMIT 10
        ", [$agent_id]);
        
        return [
            'total_orders' => $total_orders,
            'confirmed_orders' => $confirmed_orders,
            'delivered_orders' => $delivered_orders,
            'cancelled_orders' => $cancelled_orders,
            'pending_orders' => $pending_orders,
            'amount_earned' => $amount_earned,
            'pending_amount' => $pending_amount,
            'avg_order_value' => $avg_order_value,
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

$stats = getAgentDetailedStats($db, $agent_id, $selected_period, $periods);

// Get performance data for chart based on selected period
$period_config = $periods[$selected_period];
$group_by = $period_config['group_by'];
$date_format = $period_config['date_format'];
$where_clause = $period_config['where_clause'];

$performance_data = $db->getThisQuery("
    SELECT 
        {$group_by} as period_group,
        DATE_FORMAT(o.updated_at, '{$date_format}') as period_label,
        COUNT(*) as assigned_count,
        SUM(CASE WHEN o.confirmed_by_agent = ? THEN 1 ELSE 0 END) as confirmed_count,
        SUM(CASE WHEN o.shipping_status = 'delivered' AND o.payment_status = 'paid' THEN 1 ELSE 0 END) as delivered_count,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
    FROM agent_order_assignments aoa
    JOIN orders o ON aoa.order_id = o.id
    WHERE aoa.agent_id = ? AND {$where_clause}
    GROUP BY {$group_by}
    ORDER BY {$group_by} ASC
", [$agent_id, $agent_id]);

// Prepare data for charts
$chart_labels = [];
$assigned_data = [];
$confirmed_data = [];
$delivered_data = [];
$cancelled_data = [];

foreach ($performance_data as $data) {
    $chart_labels[] = $data['period_label'];
    $assigned_data[] = $data['assigned_count'];
    $confirmed_data[] = $data['confirmed_count'];
    $delivered_data[] = $data['delivered_count'];
    $cancelled_data[] = $data['cancelled_count'];
}

// Handle AJAX requests for period changes
if (isset($_POST['ajax']) && $_POST['ajax'] == 'get_stats') {
    header('Content-Type: application/json');
    echo json_encode([
        'stats' => $stats,
        'chart_data' => [
            'labels' => $chart_labels,
            'assigned' => $assigned_data,
            'confirmed' => $confirmed_data,
            'delivered' => $delivered_data,
            'cancelled' => $cancelled_data
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
    <title>Profil Agent | <?php echo htmlspecialchars($agent_info['name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <style>
        :root {
            --primary-color: #3b82f6;
            --primary-light: #dbeafe;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #8b5cf6;
            --dark-color: #1f2937;
            --gray-color: #6b7280;
            --light-color: #f9fafb;
            --border-radius: 10px;
            --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            --box-shadow-hover: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Segoe UI', 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f6f9fc 0%, #f1f5f9 100%);
            color: var(--dark-color);
            min-height: 100vh;
        }

        /* Header */
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--info-color) 100%);
            color: white;
            padding: 2.5rem 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            right: -50%;
            bottom: -50%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            animation: float 20s linear infinite;
            opacity: 0.1;
            z-index: 0;
        }

        @keyframes float {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-20px, -20px) rotate(360deg); }
        }

        .profile-image-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
            z-index: 1;
        }

        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .profile-image:hover {
            transform: scale(1.05);
            border-color: white;
        }

        .profile-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 1.5rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .profile-email {
            font-size: 1rem;
            opacity: 0.95;
            position: relative;
            z-index: 1;
            margin-bottom: 0.5rem;
        }

        .profile-score {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255, 255, 255, 0.95);
            color: var(--dark-color);
            padding: 0.5rem 1.2rem;
            border-radius: 25px;
            font-weight: 600;
            box-shadow: var(--box-shadow);
            z-index: 1;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Period Filter */
        .period-filter {
            background: white;
            padding: 1.2rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .period-select {
            min-width: 200px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }

        .period-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Buttons */
        .export-btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--info-color) 100%);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
            color: white;
        }

        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Cards */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            background: white;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 600;
            padding: 1.2rem 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-header i {
            color: var(--primary-color);
        }

        /* Stat Cards */
        .stat-card {
            padding: 1.5rem;
            border-radius: var(--border-radius);
            background: white;
            position: relative;
            overflow: hidden;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card.primary {
            border-left-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);
        }

        .stat-card.success {
            border-left-color: var(--success-color);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(16, 185, 129, 0.02) 100%);
        }

        .stat-card.warning {
            border-left-color: var(--warning-color);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(245, 158, 11, 0.02) 100%);
        }

        .stat-card.danger {
            border-left-color: var(--danger-color);
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(239, 68, 68, 0.02) 100%);
        }

        .stat-card.info {
            border-left-color: var(--info-color);
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, rgba(139, 92, 246, 0.02) 100%);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-color);
            font-weight: 500;
        }

        /* Activity Items */
        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background-color: #f8fafc;
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
            font-size: 1rem;
        }

        .activity-icon.assigned {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .activity-icon.confirmed {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .activity-icon.delivered {
            background-color: rgba(139, 92, 246, 0.1);
            color: var(--info-color);
        }

        .activity-icon.cancelled {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .activity-content {
            flex-grow: 1;
        }

        .activity-order {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .activity-amount {
            font-size: 0.9rem;
            color: var(--gray-color);
            margin-bottom: 0.25rem;
        }

        .activity-date {
            font-size: 0.8rem;
            color: var(--gray-color);
        }

        /* Form Elements */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 0.6rem 0.75rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        /* Progress */
        .progress {
            height: 10px;
            border-radius: 5px;
            background-color: #e5e7eb;
            overflow: hidden;
        }

        .progress-bar {
            background-color: var(--primary-color);
            transition: width 1s ease-in-out;
        }

        .confirmation-rate {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        /* Charts */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        /* Period Info */
        .period-info {
            background: linear-gradient(135deg, var(--primary-light) 0%, #eff6ff 100%);
            border: 1px solid #dbeafe;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: var(--primary-dark);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Badges */
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 500;
            border-radius: 6px;
        }

        .badge-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .badge-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .badge-info {
            background-color: rgba(139, 92, 246, 0.1);
            color: var(--info-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-header {
                padding: 1.5rem 1rem;
                text-align: center;
            }
            
            .profile-score {
                position: relative;
                top: auto;
                right: auto;
                display: inline-block;
                margin-top: 1rem;
            }
            
            .period-filter {
                flex-direction: column;
                gap: 1rem;
            }
            
            .period-select {
                width: 100%;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .activity-item {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .activity-icon {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-3 text-muted">Chargement des données...</p>
        </div>
    </div>

    <div class="container py-4">
        <!-- Profile Header -->
        <div class="profile-header text-center">
            <div class="profile-score">
                <i class="bi bi-star-fill text-warning"></i> 
                Score: <?php echo htmlspecialchars($agent_info['score'] ?? '0'); ?>
            </div>
            
            <h1 class="profile-name"><?php echo htmlspecialchars($agent_info['name']); ?></h1>
            <p class="profile-email">
                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($agent_info['email']); ?>
            </p>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <span class="badge badge-success" style="color: #FFF;">
                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($agent_info['phone']); ?>
                </span>
                <?php if (!empty($agent_info['city'])): ?>
                <span class="badge badge-info">
                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($agent_info['city']); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($agent_info['team_name'])): ?>
                <span class="badge badge-warning">
                    <i class="bi bi-people"></i> <?php echo htmlspecialchars($agent_info['team_name']); ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Period Filter -->
        <div class="period-filter d-flex justify-content-between align-items-center flex-wrap">
            <div class="d-flex align-items-center">
                <label for="periodSelect" class="form-label me-3 mb-0 fw-medium">
                    <i class="bi bi-calendar3 text-primary"></i> Période:
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
                <a href="./home" class="btn btn-outline-primary">
                    <i class="bi bi-house"></i> Accueil
                </a>
                <button class="export-btn" onclick="exportStats()">
                    <i class="bi bi-download"></i> Exporter
                </button>
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimer
                </button>
                <a href="?logout" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>

        <!-- Period Info -->
        <div class="period-info">
            <i class="bi bi-info-circle text-primary"></i>
            Statistiques pour: <strong><?php echo $stats['period_label']; ?></strong>
        </div>

        <!-- Main Content -->
        <div class="row">
            <!-- Left Column - Profile Info -->
            <div class="col-lg-4">
                <!-- Profile Information Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-person-lines-fill"></i> Informations du profil
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="profileForm">
                            <div class="mb-3">
                                <label for="name" class="form-label">Nom complet *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($agent_info['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" 
                                       value="<?php echo htmlspecialchars($agent_info['email']); ?>" disabled>
                                <small class="text-muted">Contactez l'administrateur pour modifier l'email</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Téléphone *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($agent_info['phone'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Adresse</label>
                                <input type="text" class="form-control" id="address" name="address" 
                                       value="<?php echo htmlspecialchars($agent_info['address'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="city" class="form-label">Ville</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo htmlspecialchars($agent_info['city'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="bank_name" class="form-label">Nom de la banque</label>
                                <input type="text" class="form-control" id="bank_name" name="bank_name" 
                                       value="<?php echo htmlspecialchars($agent_info['bank_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="bank_account" class="form-label">Numéro de compte</label>
                                <input type="text" class="form-control" id="bank_account" name="bank_account" 
                                       value="<?php echo htmlspecialchars($agent_info['bank_account'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="profile_image" class="form-label">Photo de profil</label>
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*">
                                <small class="text-muted">Formats acceptés: JPG, PNG, GIF (max 5MB)</small>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h6 class="mb-3">Changer le mot de passe</h6>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary w-100 py-2">
                                <i class="bi bi-save"></i> Mettre à jour le profil
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Team and Responsible Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-people"></i> Équipe et Responsable
                    </div>
                    <div class="card-body">
                        <?php if (!empty($agent_info['responsable_name'])): ?>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Responsable</label>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-person-badge text-primary"></i>
                                    <strong><?php echo htmlspecialchars($agent_info['responsable_name']); ?></strong>
                                </div>
                                <?php if (!empty($agent_info['responsable_email'])): ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="bi bi-envelope text-muted"></i>
                                        <small><?php echo htmlspecialchars($agent_info['responsable_email']); ?></small>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($agent_info['responsable_phone'])): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-telephone text-muted"></i>
                                        <small><?php echo htmlspecialchars($agent_info['responsable_phone']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($agent_info['team_name'])): ?>
                            <div>
                                <label class="form-label text-muted small">Équipe</label>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-people text-primary"></i>
                                    <strong><?php echo htmlspecialchars($agent_info['team_name']); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Performance Summary -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-graph-up"></i> Résumé de performance
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="confirmation-rate" id="confirmationRate">
                                <?php echo htmlspecialchars($stats['confirmation_rate'] ?? '0'); ?>%
                            </div>
                            <div class="text-muted small mb-2">Taux de confirmation</div>
                            <div class="progress mb-3">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo htmlspecialchars($stats['confirmation_rate'] ?? '0'); ?>%" 
                                     id="confirmationProgressBar">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="text-center">
                                    <div class="h4 mb-1" id="avgOrderValue">
                                        <?php echo number_format($stats['avg_order_value'] ?? 0, 2); ?> MAD
                                    </div>
                                    <div class="text-muted small">Valeur moyenne</div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="text-center">
                                    <div class="h4 mb-1" id="cancellationRate">
                                        <?php 
                                            $cancellation_rate = $stats['total_orders'] > 0 
                                                ? round(($stats['cancelled_orders'] / $stats['total_orders']) * 100, 2) 
                                                : 0;
                                            echo $cancellation_rate; 
                                        ?>%
                                    </div>
                                    <div class="text-muted small">Taux annulation</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                            <span class="text-muted">Score performance:</span>
                            <span class="h5 mb-0 text-primary">
                                <?php echo htmlspecialchars($agent_info['score'] ?? '0'); ?>/100
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Stats and Activity -->
            <div class="col-lg-8">
                <!-- Stats Cards -->
                <div class="row" id="statsContainer">
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="stat-card primary">
                            <div class="stat-value" id="totalOrders"><?php echo htmlspecialchars($stats['total_orders'] ?? '0'); ?></div>
                            <div class="stat-label">Commandes assignées</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="stat-card success">
                            <div class="stat-value" id="confirmedOrders"><?php echo htmlspecialchars($stats['confirmed_orders'] ?? '0'); ?></div>
                            <div class="stat-label">Confirmées</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="stat-card info">
                            <div class="stat-value" id="deliveredOrders"><?php echo htmlspecialchars($stats['delivered_orders'] ?? '0'); ?></div>
                            <div class="stat-label">Livrées & Payées</div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="stat-card danger">
                            <div class="stat-value" id="cancelledOrders"><?php echo htmlspecialchars($stats['cancelled_orders'] ?? '0'); ?></div>
                            <div class="stat-label">Annulées</div>
                        </div>
                    </div>
                </div>

                <!-- Financial Stats -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="stat-card warning">
                            <div class="stat-value" id="amountEarned"><?php echo number_format($stats['amount_earned'] ?? 0, 2); ?> MAD</div>
                            <div class="stat-label">Montant gagné</div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="stat-card info">
                            <div class="stat-value" id="pendingAmount"><?php echo number_format($stats['pending_amount'] ?? 0, 2); ?> MAD</div>
                            <div class="stat-label">Montant en attente</div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Charts -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-bar-chart"></i> Performance détaillée
                        </div>
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="chartType" id="barChart" checked>
                            <label class="btn btn-outline-primary" for="barChart" title="Graphique à barres">
                                <i class="bi bi-bar-chart"></i>
                            </label>
                            <input type="radio" class="btn-check" name="chartType" id="lineChart">
                            <label class="btn btn-outline-primary" for="lineChart" title="Graphique linéaire">
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

                <!-- Orders Distribution -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="bi bi-pie-chart"></i> Répartition des commandes
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height: 250px;">
                                    <canvas id="distributionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="bi bi-info-circle"></i> Détails
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>En attente:</span>
                                        <strong id="pendingOrders"><?php echo htmlspecialchars($stats['pending_orders'] ?? '0'); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Confirmées:</span>
                                        <strong class="text-success"><?php echo htmlspecialchars($stats['confirmed_orders'] ?? '0'); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Livrées:</span>
                                        <strong class="text-info"><?php echo htmlspecialchars($stats['delivered_orders'] ?? '0'); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Annulées:</span>
                                        <strong class="text-danger"><?php echo htmlspecialchars($stats['cancelled_orders'] ?? '0'); ?></strong>
                                    </div>
                                </div>
                                <hr>
                                <div class="text-center">
                                    <small class="text-muted">Total: <strong><?php echo htmlspecialchars($stats['total_orders'] ?? '0'); ?></strong> commandes</small>
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
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshActivity()" title="Actualiser">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="card-body" id="activityContainer">
                        <?php if (!empty($stats['recent_activity'])): ?>
                            <?php foreach ($stats['recent_activity'] as $activity): ?>
                                <div class="activity-item">
                                    <?php
                                    $icon_class = 'assigned';
                                    $icon = 'bi bi-inbox';
                                    $status_text = 'Assignée';
                                    
                                    if (!empty($activity['confirmed_at'])) {
                                        $icon_class = 'confirmed';
                                        $icon = 'bi bi-check-circle';
                                        $status_text = 'Confirmée';
                                    } elseif ($activity['status'] == 'cancelled' || $activity['assignment_status'] == 'cancelled') {
                                        $icon_class = 'cancelled';
                                        $icon = 'bi bi-x-circle';
                                        $status_text = 'Annulée';
                                    } elseif ($activity['shipping_status'] == 'delivered') {
                                        $icon_class = 'delivered';
                                        $icon = 'bi bi-truck';
                                        $status_text = 'Livrée';
                                    }
                                    ?>
                                    <div class="activity-icon <?php echo $icon_class; ?>">
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-order">
                                            Commande <?php echo htmlspecialchars($activity['order_number']); ?>
                                            <span class="badge badge-<?php echo $icon_class; ?> ms-2">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($activity['total_amount'])): ?>
                                            <div class="activity-amount">
                                                <?php echo number_format($activity['total_amount'], 2); ?> MAD
                                            </div>
                                        <?php endif; ?>
                                        <div class="activity-date">
                                            <?php 
                                                $date = !empty($activity['confirmed_at']) 
                                                    ? $activity['confirmed_at'] 
                                                    : $activity['assigned_at'];
                                                echo date('d/m/Y H:i', strtotime($date)); 
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-inbox display-6 text-muted mb-3"></i>
                                <p class="text-muted">Aucune activité récente pour cette période</p>
                            </div>
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
        let currentPeriod = '<?php echo $selected_period; ?>';
        
        // Initialize charts
        function initializeCharts() {
            const ctx = document.getElementById('performanceChart').getContext('2d');
            const distributionCtx = document.getElementById('distributionChart').getContext('2d');
            
            // Destroy existing charts if they exist
            if (performanceChart) {
                performanceChart.destroy();
            }
            if (distributionChart) {
                distributionChart.destroy();
            }
            
            // Performance Chart
            performanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [
                        {
                            label: 'Assignées',
                            data: <?php echo json_encode($assigned_data); ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Confirmées',
                            data: <?php echo json_encode($confirmed_data); ?>,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Livrées',
                            data: <?php echo json_encode($delivered_data); ?>,
                            backgroundColor: 'rgba(139, 92, 246, 0.7)',
                            borderColor: 'rgba(139, 92, 246, 1)',
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
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                usePointStyle: true,
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 10,
                            cornerRadius: 6,
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
            const deliveredOrders = <?php echo $stats['delivered_orders']; ?>;
            const cancelledOrders = <?php echo $stats['cancelled_orders']; ?>;
            const pendingOrders = <?php echo $stats['pending_orders']; ?>;
            
            distributionChart = new Chart(distributionCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Confirmées', 'Livrées', 'Annulées', 'En attente'],
                    datasets: [{
                        data: [
                            confirmedOrders - deliveredOrders,
                            deliveredOrders,
                            cancelledOrders,
                            pendingOrders
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(139, 92, 246, 0.8)',
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
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 10,
                                usePointStyle: true,
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 10,
                            cornerRadius: 6,
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
                        animateScale: true,
                        animateRotate: true,
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        }
        
        // Handle period change
        $('#periodSelect').on('change', function() {
            const selectedPeriod = $(this).val();
            currentPeriod = selectedPeriod;
            loadPeriodData(selectedPeriod);
        });
        
        // Chart type change
        $('input[name="chartType"]').on('change', function() {
            const chartType = $(this).attr('id') === 'barChart' ? 'bar' : 'line';
            performanceChart.config.type = chartType;
            performanceChart.update('active');
        });
        
        function loadPeriodData(period) {
            showLoading();
            
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    ajax: 'get_stats',
                    period: period
                },
                dataType: 'json',
                success: function(response) {
                    if (response.stats.error) {
                        showError(response.stats.error);
                    } else {
                        updateStats(response.stats);
                        updateCharts(response.chart_data);
                        
                        // Update URL without page refresh
                        const url = new URL(window.location);
                        url.searchParams.set('period', period);
                        window.history.pushState({}, '', url);
                    }
                    hideLoading();
                },
                error: function(xhr, status, error) {
                    showError('Erreur lors du chargement des données: ' + error);
                    hideLoading();
                }
            });
        }
        
        function updateStats(stats) {
            $('#totalOrders').text(stats.total_orders);
            $('#confirmedOrders').text(stats.confirmed_orders);
            $('#deliveredOrders').text(stats.delivered_orders);
            $('#cancelledOrders').text(stats.cancelled_orders);
            $('#pendingOrders').text(stats.pending_orders);
            $('#amountEarned').text(parseFloat(stats.amount_earned).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' MAD');
            $('#pendingAmount').text(parseFloat(stats.pending_amount).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' MAD');
            $('#confirmationRate').text(stats.confirmation_rate + '%');
            $('#avgOrderValue').text(parseFloat(stats.avg_order_value).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' MAD');
            
            // Update progress bar with animation
            const progressBar = $('#confirmationProgressBar');
            const currentWidth = parseInt(progressBar.css('width')) || 0;
            const newWidth = stats.confirmation_rate;
            
            progressBar.css('width', '0%').attr('aria-valuenow', '0');
            setTimeout(() => {
                progressBar.css('width', newWidth + '%').attr('aria-valuenow', newWidth);
            }, 100);
            
            // Update cancellation rate
            const cancellationRate = stats.total_orders > 0 
                ? ((stats.cancelled_orders / stats.total_orders) * 100).toFixed(2) 
                : 0;
            $('#cancellationRate').text(cancellationRate + '%');
            
            // Update period info
            $('.period-info strong').text(stats.period_label);
        }
        
        function updateCharts(chartData) {
            // Update performance chart
            performanceChart.data.labels = chartData.labels;
            performanceChart.data.datasets[0].data = chartData.assigned;
            performanceChart.data.datasets[1].data = chartData.confirmed;
            performanceChart.data.datasets[2].data = chartData.delivered;
            performanceChart.data.datasets[3].data = chartData.cancelled;
            performanceChart.update('active');
            
            // Update distribution chart
            const totalOrders = chartData.assigned.reduce((a, b) => a + b, 0);
            const confirmedOrders = chartData.confirmed.reduce((a, b) => a + b, 0);
            const deliveredOrders = chartData.delivered.reduce((a, b) => a + b, 0);
            const cancelledOrders = chartData.cancelled.reduce((a, b) => a + b, 0);
            const pendingOrders = totalOrders - confirmedOrders - cancelledOrders;
            
            distributionChart.data.datasets[0].data = [
                confirmedOrders - deliveredOrders,
                deliveredOrders,
                cancelledOrders,
                Math.max(0, pendingOrders)
            ];
            distributionChart.update('active');
        }
        
        function exportStats() {
            const period = $('#periodSelect option:selected').text();
            
            // Collect all stats
            const exportData = {
                period: period,
                date: new Date().toLocaleString('fr-FR'),
                agent: {
                    name: '<?php echo addslashes($agent_info["name"]); ?>',
                    email: '<?php echo addslashes($agent_info["email"]); ?>'
                },
                statistics: {
                    total_orders: $('#totalOrders').text(),
                    confirmed_orders: $('#confirmedOrders').text(),
                    delivered_orders: $('#deliveredOrders').text(),
                    cancelled_orders: $('#cancelledOrders').text(),
                    amount_earned: $('#amountEarned').text(),
                    pending_amount: $('#pendingAmount').text(),
                    confirmation_rate: $('#confirmationRate').text(),
                    avg_order_value: $('#avgOrderValue').text()
                }
            };
            
            // Convert to CSV
            let csvContent = "Statistiques Agent - " + period + "\n\n";
            csvContent += "Agent," + exportData.agent.name + "\n";
            csvContent += "Email," + exportData.agent.email + "\n";
            csvContent += "Date d'export," + exportData.date + "\n\n";
            csvContent += "Statistique,Valeur\n";
            
            for (const [key, value] of Object.entries(exportData.statistics)) {
                const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                csvContent += label + "," + value + "\n";
            }
            
            // Create and download CSV file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", `statistiques_agent_${period.toLowerCase().replace(/\s+/g, '_')}_${new Date().toISOString().slice(0,10)}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showSuccess('Export réussi! Le fichier est en cours de téléchargement.');
        }
        
        function refreshActivity() {
            loadPeriodData(currentPeriod);
        }
        
        function showLoading() {
            $('#loadingOverlay').fadeIn(200);
        }
        
        function hideLoading() {
            $('#loadingOverlay').fadeOut(200);
        }
        
        function showSuccess(message) {
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: "linear-gradient(to right, #10b981, #059669)",
                stopOnFocus: true
            }).showToast();
        }
        
        function showError(message) {
            Toastify({
                text: message,
                duration: 5000,
                gravity: "top",
                position: "right",
                backgroundColor: "linear-gradient(to right, #ef4444, #dc2626)",
                stopOnFocus: true
            }).showToast();
        }
        
        // Form validation
        $('#profileForm').on('submit', function(e) {
            const newPassword = $('#new_password').val();
            const confirmPassword = $('#confirm_password').val();
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showError('Les mots de passe ne correspondent pas');
                $('#confirm_password').focus();
                return false;
            }
            
            if (newPassword && newPassword.length < 6) {
                e.preventDefault();
                showError('Le mot de passe doit contenir au moins 6 caractères');
                $('#new_password').focus();
                return false;
            }
            
            return true;
        });
        
        // Show flash messages on page load
        <?php if (Session::exists('success')): ?>
            $(document).ready(function() {
                showSuccess('<?php echo addslashes(Session::flash('success')); ?>');
            });
        <?php elseif (Session::exists('error')): ?>
            $(document).ready(function() {
                showError('<?php echo addslashes(Session::flash('error')); ?>');
            });
        <?php endif; ?>
        
        // Initialize charts when page loads
        $(document).ready(function() {
            initializeCharts();
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const period = urlParams.get('period') || 'current_month';
            $('#periodSelect').val(period);
            loadPeriodData(period);
        });
    </script>
</body>
</html>