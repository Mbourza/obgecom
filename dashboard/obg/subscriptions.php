<?php
// Database connection and session check for super admin
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if user is logged in and is super admin
if(!Session::exists(Config::get('session/session_name'))){
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

// Handle form submissions for plan management
if ($_POST) {
    if (isset($_POST['add_plan'])) {
        $name = $_POST['name'];
        $price = $_POST['price'];
        $is_custom = isset($_POST['is_custom']) ? 1 : 0;
        
        // Insert the basic plan
        $insert_query = "INSERT INTO plans (name, price, is_custom, created_at) VALUES (?, ?, ?, NOW())";
        $plan_id = $db->insert($insert_query, [$name, $price, $is_custom]);
        
        // Insert plan limits
        if ($plan_id) {
            $limits = [
                'orders_per_month' => $_POST['orders_per_month'] ?? 0,
                'stores' => $_POST['stores'] ?? 0,
                'carriers' => $_POST['carriers'] ?? 0,
                'team_members' => $_POST['team_members'] ?? 0,
                'support' => $_POST['support'] ?? 0,
                'account_manager' => $_POST['account_manager'] ?? 0
            ];
            
            foreach ($limits as $limit_key => $limit_value) {
                if ($limit_value !== '') {
                    $db->insert("INSERT INTO plan_limits (plan_id, limit_key, limit_value) VALUES (?, ?, ?)", 
                               [$plan_id, $limit_key, $limit_value]);
                }
            }
        }
        
        Session::flash('success', 'Plan ajouté avec succès!');
        Redirect::to('./subscriptions');
    }
    
    if (isset($_POST['update_plan'])) {

        $plan_id = intval($_POST['plan_id']);
        $name = escape($_POST['name']);
        $price = floatval($_POST['price']);
        $is_custom = isset($_POST['is_custom']) ? 1 : 0;
        
        try {
            // Start transaction if your DB class supports it
            // $db->beginTransaction();
            
            // Update the basic plan
            $updateData = [
                'name' => $_POST['name'],
                'price' => (float)$_POST['price'],
                'is_custom' => isset($_POST['is_custom']) ? (int)$_POST['is_custom'] : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $plan_id = $_POST['plan_id'];
            
            $planUpdated = $db->update('plans', $plan_id, $updateData);           
            
            if (!$planUpdated) {
                throw new Exception('Erreur lors de la mise à jour du plan');
            }
            
            // Update plan limits
            $limits = [
                'orders_per_month' => intval($_POST['orders_per_month'] ?? 0),
                'stores' => intval($_POST['stores'] ?? 0),
                'carriers' => intval($_POST['carriers'] ?? 0),
                'team_members' => intval($_POST['team_members'] ?? 0),
                'support' => intval($_POST['support'] ?? 0),
                'account_manager' => intval($_POST['account_manager'] ?? 0)
            ];
            
            foreach ($limits as $limit_key => $limit_value) {
                // Check if limit exists
                $existing_limit = $db->getThisQuery(
                    "SELECT id FROM plan_limits WHERE plan_id = ? AND limit_key = ?",
                    [$plan_id, $limit_key]
                );
                
                if (!empty($existing_limit)) {
                    // Update existing limit
                    $updated = $db->query(
                        "UPDATE plan_limits SET limit_value = ? WHERE plan_id = ? AND limit_key = ?",
                        [$limit_value, $plan_id, $limit_key]
                    );
                    
                    if (!$updated) {
                        throw new Exception("Erreur lors de la mise à jour de la limite: {$limit_key}");
                    }
                } else {
                    // Insert new limit
                    $inserted = $db->query(
                        "INSERT INTO plan_limits (plan_id, limit_key, limit_value) VALUES (?, ?, ?)",
                        [$plan_id, $limit_key, $limit_value]
                    );
                    
                    if (!$inserted) {
                        throw new Exception("Erreur lors de l'insertion de la limite: {$limit_key}");
                    }
                }
            }
            
            // Commit transaction if using transactions
            // $db->commit();
            
            Session::flash('success', 'Plan mis à jour avec succès!');
            Redirect::to('subscriptions');
            
        } catch (Exception $e) {
            // Rollback transaction if using transactions
            // $db->rollback();
            
            Session::flash('error', $e->getMessage());
            Redirect::to('subscriptions');
        }
    }
    
    if (isset($_POST['delete_plan'])) {
        $plan_id = $_POST['plan_id'];
        
        // Check if plan has active subscriptions
        $check_subscriptions = $db->getThisQuery(
            "SELECT COUNT(*) as subscription_count FROM subscriptions WHERE plan_id = ? AND status = 'active'", 
            [$plan_id]
        )[0]['subscription_count'];
        
        $check_user_plans = $db->getThisQuery(
            "SELECT COUNT(*) as user_plan_count FROM user_plans WHERE plan_id = ? AND status = 'active'", 
            [$plan_id]
        )[0]['user_plan_count'];
        
        if ($check_subscriptions > 0 || $check_user_plans > 0) {
            Session::flash('error', 'Impossible de supprimer ce plan car il a des abonnements actifs!');
        } else {
            // Delete plan limits first
            $db->delete("DELETE FROM plan_limits WHERE plan_id = ?", [$plan_id]);
            // Delete the plan
            $db->delete("DELETE FROM plans WHERE id = ?", [$plan_id]);
            Session::flash('success', 'Plan supprimé avec succès!');
        }
        Redirect::to('./subscriptions');
    }
}

// Get filter parameters
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'this_month';
$plan_filter = isset($_GET['plan_filter']) ? $_GET['plan_filter'] : '';

// Calculate date range for analytics
switch ($date_range) {
    case 'today':
        $date_condition = "DATE(s.created_at) = CURDATE()";
        break;
    case 'this_week':
        $date_condition = "YEARWEEK(s.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'this_month':
        $date_condition = "MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
        break;
    case 'last_month':
        $date_condition = "MONTH(s.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(s.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
    case 'this_year':
        $date_condition = "YEAR(s.created_at) = YEAR(CURDATE())";
        break;
    default:
        $date_condition = "1=1";
}

// Subscription Distribution Analytics
$distribution_query = "
    SELECT 
        CASE 
            WHEN s.plan_id IS NULL OR s.status != 'active' THEN 'Non activé'
            ELSE COALESCE(p.name, 'Inconnu')
        END as plan_type,
        COUNT(DISTINCT u.id) as user_count,
        COUNT(s.id) as subscription_count,
        COALESCE(SUM(up.total_amount), 0) as total_revenue,
        (COUNT(DISTINCT u.id) * 100.0 / (SELECT COUNT(*) FROM users WHERE role != 'super_admin')) as user_percentage,
        (COUNT(s.id) * 100.0 / (SELECT COUNT(*) FROM subscriptions WHERE status = 'active')) as subscription_percentage
    FROM users u
    LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
    LEFT JOIN user_plans up ON u.id = up.user_id AND up.status = 'active'
    LEFT JOIN plans p ON s.plan_id = p.id
    WHERE u.role != 'super_admin'
    GROUP BY plan_type
    ORDER BY user_count DESC
";

$distribution_stats = $db->getThisQuery($distribution_query);

// Monthly Subscription Growth
$monthly_growth_query = "
    SELECT 
        DATE_FORMAT(s.created_at, '%Y-%m') as month,
        COUNT(*) as new_subscriptions,
        COALESCE(SUM(up.total_amount), 0) as monthly_revenue,
        COUNT(DISTINCT s.user_id) as new_customers
    FROM subscriptions s
    LEFT JOIN user_plans up ON s.user_id = up.user_id AND up.status = 'active'
    WHERE s.status = 'active'
    AND s.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(s.created_at, '%Y-%m')
    ORDER BY month ASC
";

$monthly_growth = $db->getThisQuery($monthly_growth_query);

// Renewal Analytics
$renewal_query = "
    SELECT 
        COUNT(*) as total_active,
        SUM(CASE WHEN s.expires_at > NOW() THEN 1 ELSE 0 END) as active_subscriptions,
        SUM(CASE WHEN s.expires_at <= NOW() THEN 1 ELSE 0 END) as expired_subscriptions,
        COUNT(DISTINCT s.user_id) as unique_customers,
        AVG(up.total_amount) as avg_revenue_per_subscription
    FROM subscriptions s
    LEFT JOIN user_plans up ON s.user_id = up.user_id AND up.status = 'active'
    WHERE s.status = 'active'
";

$renewal_stats = $db->getThisQuery($renewal_query)[0];

// Non-renewed subscriptions
$non_renewed_query = "
    SELECT 
        COUNT(DISTINCT u.id) as non_renewed_count
    FROM users u
    INNER JOIN subscriptions s ON u.id = s.user_id
    WHERE s.status = 'active' 
    AND s.expires_at < NOW()
    AND NOT EXISTS (
        SELECT 1 FROM subscriptions s2 
        WHERE s2.user_id = u.id 
        AND s2.status = 'active' 
        AND s2.expires_at > NOW()
    )
";

$non_renewed_count = $db->getThisQuery($non_renewed_query)[0]['non_renewed_count'];

// Get all plans with their limits
$plans_query = "
    SELECT p.*, 
           GROUP_CONCAT(CONCAT(pl.limit_key, ':', pl.limit_value)) as limits
    FROM plans p
    LEFT JOIN plan_limits pl ON p.id = pl.plan_id
    GROUP BY p.id
    ORDER BY p.price ASC
";

$plans_result = $db->getThisQuery($plans_query);
$plans = [];

foreach ($plans_result as $plan) {
    $limits = [];
    if (!empty($plan['limits'])) {
        $limit_pairs = explode(',', $plan['limits']);
        foreach ($limit_pairs as $pair) {
            list($key, $value) = explode(':', $pair);
            $limits[$key] = $value;
        }
    }
    $plan['limits'] = $limits;
    $plans[] = $plan;
}

// Recent subscriptions with actual revenue data
$recent_subscriptions_query = "
    SELECT 
        s.*,
        u.name as user_name,
        u.email,
        p.name as plan_name,
        up.total_amount,
        up.monthly_price,
        up.duration_months
    FROM subscriptions s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN plans p ON s.plan_id = p.id
    LEFT JOIN user_plans up ON s.user_id = up.user_id AND up.status = 'active'
    WHERE s.status = 'active'
    ORDER BY s.created_at DESC
    LIMIT 10
";

$recent_subscriptions = $db->getThisQuery($recent_subscriptions_query);

function formatCurrency($amount) {
    $amount = is_null($amount) ? 0 : $amount;
    return number_format((float)$amount, 2, ',', ' ') . ' D.H';
}

function formatPercentage($value) {
    return round((float)$value, 1) . '%';
}

function getPlanColor($plan_type) {
    $colors = [
        'Gratuit' => '#6b7280',
        'Starter' => '#3b82f6',
        'Professional' => '#10b981',
        'Growth' => '#f59e0b',
        'Business' => '#8b5cf6',
        'Inconnu' => '#6b7280'
    ];
    return $colors[$plan_type] ?? '#6b7280';
}

function getPlanGradient($plan_type) {
    $gradients = [
        'Gratuit' => 'linear-gradient(135deg, #6b7280 0%, #9ca3af 100%)',
        'Starter' => 'linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%)',
        'Professional' => 'linear-gradient(135deg, #10b981 0%, #34d399 100%)',
        'Growth' => 'linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%)',
        'Business' => 'linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%)',
        'Inconnu' => 'linear-gradient(135deg, #6b7280 0%, #9ca3af 100%)'
    ];
    return $gradients[$plan_type] ?? 'linear-gradient(135deg, #6b7280 0%, #9ca3af 100%)';
}

function isSubscriptionActive($expires_at) {
    if (empty($expires_at) || $expires_at == '0000-00-00 00:00:00') {
        return false;
    }
    return strtotime($expires_at) > time();
}

function getLimitDisplayName($limit_key) {
    $names = [
        'orders_per_month' => 'Commandes/mois',
        'stores' => 'Boutiques',
        'carriers' => 'Transporteurs',
        'team_members' => 'Membres d\'équipe',
        'support' => 'Support prioritaire',
        'account_manager' => 'Gestionnaire de compte'
    ];
    return $names[$limit_key] ?? $limit_key;
}

function getLimitDisplayValue($limit_key, $value) {
    if ($value == 0) {
        return 'Non';
    } elseif ($value == 1 && in_array($limit_key, ['support', 'account_manager'])) {
        return 'Oui';
    } elseif ($value >= 10000) {
        return 'Illimité';
    } else {
        return $value;
    }
}

function getPlanIcon($plan_name) {
    $icons = [
        'Gratuit' => 'bi-arrow-through-heart',
        'Starter' => 'bi-rocket',
        'Professional' => 'bi-star',
        'Growth' => 'bi-graph-up-arrow',
        'Business' => 'bi-award',
        'default' => 'bi-box'
    ];
    
    $plan_lower = strtolower($plan_name);
    foreach ($icons as $key => $icon) {
        if (strpos($plan_lower, strtolower($key)) !== false) {
            return $icon;
        }
    }
    return $icons['default'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Abonnements | Super Admin OBG</title>
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        
        
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --card-shadow-hover: 0 20px 45px -5px rgba(0, 0, 0, 0.15), 0 10px 18px -5px rgba(0, 0, 0, 0.1);
        }
        
        .subscriptions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        
        .page-title {
            font-size: 2.25rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .stat-card:nth-child(2)::before { background: var(--success-gradient); }
        .stat-card:nth-child(3)::before { background: var(--warning-gradient); }
        .stat-card:nth-child(4)::before { background: var(--info-gradient); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
            background: var(--primary-gradient);
        }
        
        .stat-card:nth-child(2) .stat-icon { background: var(--success-gradient); }
        .stat-card:nth-child(3) .stat-icon { background: var(--warning-gradient); }
        .stat-card:nth-child(4) .stat-icon { background: var(--info-gradient); }
        
        .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .chart-container {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: var(--card-shadow);
            border: none;
            transition: all 0.3s ease;
        }
        
        .chart-container:hover {
            box-shadow: var(--card-shadow-hover);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .chart-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        
        .chart-canvas {
            height: 320px;
            position: relative;
        }
        
        .distribution-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        
        .distribution-card {
            background: white;
            border-radius: .5rem;
            padding: 1.75rem;
            box-shadow: var(--card-shadow);
            border-left: 4px solid;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .distribution-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .plan-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .plan-name {
            font-weight: 700;
            font-size: 1.125rem;
            color: #1f2937;
        }
        
        .plan-users {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1f2937;
        }
        
        .plan-percentage {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .progress {
            height: 8px;
            background: #f3f4f6;
            border-radius: 4px;
            overflow: hidden;
            margin: 0.75rem 0;
        }
        
        .progress-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 0.8s ease;
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .distribution-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        
        .tabs-container {
            background: white;
            border-radius: 0rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2.5rem;
            overflow: hidden;
        }
        
        .tabs-header {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        
        .tab {
            padding: 1.25rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #6b7280;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab:hover {
            color: #374151;
            background: #f1f5f9;
        }
        
        .tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
            background: white;
        }
        
        .tab-content {
            padding: 0;
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* exactly 4 columns */
            gap: .4rem;
            padding: 1rem;
        }

        .plan-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 1.25rem;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .plan-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--primary-gradient);
        }
        
        .plan-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .plan-card-header {
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            column-gap: .4em;
        }
        
        .plan-name {
            font-size: .9rem;
            font-weight: 800;
            color: #1f2937;
            margin: 0;
        }
        
        .plan-price {
            font-size: 2rem;
            font-weight: 800;
            color: #3b82f6;
            line-height: 1;
        }
        
        .plan-price-period {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }
        
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem 0;
        }
        
        .plan-features li {
            padding: 0.75rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s ease;
        }
        
        .plan-features li:hover {
            background: #f8fafc;
            margin: 0 -1rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
        }
        
        .plan-features li:last-child {
            border-bottom: none;
        }
        
        .plan-features li i {
            color: #10b981;
            flex-shrink: 0;
            font-size: 1.125rem;
        }
        
        .feature-name {
            flex: 1;
            font-size: 0.95rem;
            color: #4b5563;
        }
        
        .feature-value {
            font-weight: 700;
            color: #1f2937;
            background: #f3f4f6;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
        }
        
        .plan-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.4);
        }
        
        .btn-success {
            background: var(--success-gradient);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4);
        }
        
        .btn-danger {
            background: var(--warning-gradient);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.4);
        }
        
        .btn-outline {
            background: white;
            color: #3b82f6;
            border: 2px solid #3b82f6;
        }
        
        .btn-outline:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-2px);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1050;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            backdrop-filter: blur(4px);
        }
        
        .modal.active {
            display: flex;
            animation: modalFadeIn 0.3s ease;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            border-radius: 1.25rem;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            border: none;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
            transition: color 0.2s ease;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-modal:hover {
            color: #374151;
            background: #f3f4f6;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .form-check-input {
            margin: 0;
            width: 1.25rem;
            height: 1.25rem;
        }
        
        .limits-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .limit-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .limit-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #4b5563;
        }
        
        .recent-subscriptions {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--card-shadow);
        }
        
        .subscription-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.2s ease;
        }
        
        .subscription-item:hover {
            background: #f8fafc;
            margin: 0 -1rem;
            padding: 1.25rem 1rem;
            border-radius: 0.75rem;
        }
        
        .subscription-item:last-child {
            border-bottom: none;
        }
        
        .subscription-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .subscription-user {
            font-weight: 700;
            color: #1f2937;
            font-size: 1.05rem;
        }
        
        .subscription-plan {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .subscription-amount {
            font-weight: 800;
            color: #10b981;
            font-size: 1.25rem;
        }
        
        .subscription-date {
            font-size: 0.8rem;
            color: #9ca3af;
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border: none;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state p {
            font-size: 1.125rem;
            margin-bottom: 1.5rem;
        }
        
        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        @media (max-width: 768px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .distribution-cards {
                grid-template-columns: 1fr;
            }
            
            .plans-grid {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
            
            .limits-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs-header {
                flex-direction: column;
            }
            
            .tab {
                text-align: left;
                border-bottom: 1px solid #e5e7eb;
                border-left: 3px solid transparent;
                justify-content: flex-start;
            }
            
            .tab.active {
                border-left-color: #3b82f6;
                border-bottom-color: #e5e7eb;
            }
            
            .modal-content {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'subscriptions';
    require_once('../../assets/sidebarSuper.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <div class="navbar-title">
                <h1>
                    <i class="bi bi-credit-card"></i>
                    Gestion des Abonnements
                </h1>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Header -->
            <div class="subscriptions-header">
                <h2 class="page-title">Analytics & Gestion des Abonnements</h2>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openModal('addPlanModal')">
                        <i class="bi bi-plus-circle"></i>
                        Nouveau Plan
                    </button>
                </div>
            </div>
            
            <!-- Flash Messages -->
            <?php if (Session::exists('success')): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i>
                    <?php echo Session::flash('success'); ?>
                </div>
            <?php endif; ?>
            
            <?php if (Session::exists('error')): ?>
                <div class="alert alert-error">
                    <i class="bi bi-exclamation-circle"></i>
                    <?php echo Session::flash('error'); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-value"><?php echo $renewal_stats['total_active']; ?></div>
                    <div class="stat-label">Abonnements Actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-person-check"></i>
                    </div>
                    <div class="stat-value"><?php echo $renewal_stats['unique_customers']; ?></div>
                    <div class="stat-label">Clients Abonnés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-currency-exchange"></i>
                    </div>
                    <div class="stat-value"><?php echo formatCurrency($renewal_stats['avg_revenue_per_subscription']); ?></div>
                    <div class="stat-label">Panier Moyen</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-arrow-clockwise"></i>
                    </div>
                    <div class="stat-value"><?php echo $non_renewed_count; ?></div>
                    <div class="stat-label">Non Renouvelés</div>
                </div>
            </div>
            
            <!-- Distribution Cards -->
            <div class="distribution-cards">
                <?php foreach ($distribution_stats as $stat): ?>
                    <div class="distribution-card" style="border-left-color: <?php echo getPlanColor($stat['plan_type']); ?>">
                        <div class="plan-stats">
                            <div class="plan-name"><?php echo htmlspecialchars($stat['plan_type']); ?></div>
                            <div class="plan-users"><?php echo $stat['user_count']; ?></div>
                        </div>
                        <div class="plan-percentage">
                            <?php echo formatPercentage($stat['user_percentage']); ?> des utilisateurs
                        </div>
                        <div class="progress">
                            <div class="progress-bar" 
                                 style="width: <?php echo $stat['user_percentage']; ?>%; background: <?php echo getPlanColor($stat['plan_type']); ?>">
                            </div>
                        </div>
                        <div class="distribution-meta">
                            <span><?php echo $stat['subscription_count']; ?> abonnements</span>
                            <span><?php echo formatCurrency($stat['total_revenue']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Évolution des Abonnements</h3>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="subscriptionsChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title">Répartition par Plan</h3>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Tabs Container -->
            <div class="tabs-container">

                <div class="tabs-header">
                    <button class="tab active" onclick="switchTab('plans')">
                        <i class="bi bi-layers"></i>
                        Gestion des Plans
                    </button>
                    <button class="tab" onclick="switchTab('recent')">
                        <i class="bi bi-clock-history"></i>
                        Abonnements Récents
                    </button>
                    <button class="tab" onclick="switchTab('analytics')">
                        <i class="bi bi-graph-up"></i>
                        Analytics Détaillés
                    </button>
                </div>
                
                <!-- Plans Management Tab -->
                <div id="plans-tab" class="tab-content active">
                    <div class="plans-grid">
                        <?php foreach ($plans as $plan): ?>
                            <div class="plan-card">
                                <div class="plan-card-header">
                                    <h4 class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></h4>
                                    <div>
                                        <div class="plan-price"><?php echo formatCurrency($plan['price']); ?></div>
                                        <div class="plan-price-period">/ mois</div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($plan['limits'])): ?>
                                    <ul class="plan-features">
                                        <?php foreach ($plan['limits'] as $limit_key => $limit_value): ?>
                                            <li>
                                                <i class="bi bi-check-lg"></i>
                                                <span class="feature-name"><?php echo getLimitDisplayName($limit_key); ?></span>
                                                <span class="feature-value"><?php echo getLimitDisplayValue($limit_key, $limit_value); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                
                                <div class="plan-actions">
                                    <button class="btn btn-outline" onclick="editPlan(<?php echo $plan['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                        Modifier
                                    </button>
                                    <button class="btn btn-danger" onclick="deletePlan(<?php echo $plan['id']; ?>, '<?php echo htmlspecialchars($plan['name']); ?>')">
                                        <i class="bi bi-trash"></i>
                                        Supprimer
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Recent Subscriptions Tab -->
                <div id="recent-tab" class="tab-content">
                    <div class="recent-subscriptions">
                        <?php if (!empty($recent_subscriptions)): ?>
                            <?php foreach ($recent_subscriptions as $subscription): ?>
                                <div class="subscription-item">
                                    <div class="subscription-info">
                                        <div class="subscription-user">
                                            <?php echo htmlspecialchars($subscription['user_name']); ?>
                                        </div>
                                        <div class="subscription-plan">
                                            <?php echo htmlspecialchars($subscription['plan_name'] ?: 'Gratuit'); ?>
                                            <?php if (isSubscriptionActive($subscription['expires_at'])): ?>
                                                <span class="badge badge-success">● Actif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">● Expiré</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="subscription-date">
                                            <i class="bi bi-calendar"></i>
                                            Début: <?php echo date('d/m/Y', strtotime($subscription['created_at'])); ?>
                                            <?php if (!empty($subscription['expires_at'])): ?>
                                                • Expire: <?php echo date('d/m/Y', strtotime($subscription['expires_at'])); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="subscription-amount">
                                        <?php echo formatCurrency($subscription['total_amount'] ?: $subscription['monthly_price']); ?>
                                        <?php if ($subscription['duration_months'] > 1): ?>
                                            <small style="display: block; font-size: 0.75rem; color: #6b7280; text-align: right;">
                                                <?php echo $subscription['duration_months']; ?> mois
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-credit-card"></i>
                                <p>Aucun abonnement récent</p>
                                <button class="btn btn-outline" onclick="switchTab('plans')">
                                    <i class="bi bi-plus-circle"></i>
                                    Créer un nouveau plan
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Analytics Tab -->
                <div id="analytics-tab" class="tab-content">
                    <div style="padding: 2rem;">
                        <h4 style="margin-bottom: 1.5rem; color: #1f2937; font-weight: 700;">Analytics Détaillés des Abonnements</h4>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $renewal_stats['active_subscriptions']; ?></div>
                                <div class="stat-label">Abonnements Valides</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $renewal_stats['expired_subscriptions']; ?></div>
                                <div class="stat-label">Abonnements Expirés</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo formatPercentage(($renewal_stats['active_subscriptions'] / max(1, $renewal_stats['total_active'])) * 100); ?></div>
                                <div class="stat-label">Taux de Renouvellement</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo formatPercentage(($non_renewed_count / max(1, $renewal_stats['unique_customers'])) * 100); ?></div>
                                <div class="stat-label">Taux de Désabonnement</div>
                            </div>
                        </div>
                        
                        <h5 style="margin-bottom: 1rem; color: #374151; font-weight: 600;">Performance Mensuelle</h5>
                        <div class="chart-container">
                            <canvas id="monthlyAnalyticsChart" height="250"></canvas>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Add Plan Modal -->
    <div id="addPlanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter un Nouveau Plan</h3>
                <button class="close-modal" onclick="closeModal('addPlanModal')">&times;</button>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Nom du Plan</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex: Plan Entreprise" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Prix Mensuel (D.H)</label>
                    <input type="number" name="price" class="form-control" step="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_custom">
                        Plan Personnalisé
                    </label>
                </div>
                
                <h5 style="margin: 1.5rem 0 1rem 0; color: #374151; font-weight: 600;">Limites du Plan</h5>

                <div class="limits-grid">
                    <div class="limit-group">
                        <label class="limit-label">Commandes par mois</label>
                        <input type="number" name="orders_per_month" class="form-control" value="0" min="0">
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Nombre de boutiques</label>
                        <input type="number" name="stores" class="form-control" value="0" min="0">
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Transporteurs</label>
                        <input type="number" name="carriers" class="form-control" value="0" min="0">
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Membres d'équipe</label>
                        <input type="number" name="team_members" class="form-control" value="0" min="0">
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Support prioritaire</label>
                        <select name="support" class="form-control">
                            <option value="0">Non</option>
                            <option value="1">Oui</option>
                        </select>
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Gestionnaire de compte</label>
                        <select name="account_manager" class="form-control">
                            <option value="0">Non</option>
                            <option value="1">Oui</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addPlanModal')">Annuler</button>
                    <button type="submit" name="add_plan" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i>
                        Créer le Plan
                    </button>
                </div>

            </form>

        </div>
    </div>

    <!-- Edit Plan Modal -->
    <div id="editPlanModal" class="modal">
        <div class="modal-content">

            <div class="modal-header">
                <h3 class="modal-title">Modifier le Plan</h3>
                <button class="close-modal" onclick="closeModal('editPlanModal')">&times;</button>
            </div>

            <form method="POST">
                <input type="hidden" name="plan_id" id="edit_plan_id">
                <div class="form-group">
                    <label class="form-label">Nom du Plan</label>
                    <input type="text" name="name" id="edit_plan_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Prix Mensuel (D.H)</label>
                    <input type="number" name="price" id="edit_plan_price" class="form-control" step="0.01" required>
                </div>

                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" name="is_custom" id="edit_plan_custom">
                        Plan Personnalisé
                    </label>
                </div>
                
                <h5 style="margin: 1.5rem 0 1rem 0; color: #374151; font-weight: 600;">Limites du Plan</h5>
                <div class="limits-grid">
                    <div class="limit-group">
                        <label class="limit-label">Commandes par mois</label>
                        <input type="number" name="orders_per_month" id="edit_orders_per_month" class="form-control" min="0">
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Nombre de boutiques</label>
                        <input type="number" name="stores" id="edit_stores" class="form-control" min="0">
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Transporteurs</label>
                        <input type="number" name="carriers" id="edit_carriers" class="form-control" min="0">
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Membres d'équipe</label>
                        <input type="number" name="team_members" id="edit_team_members" class="form-control" min="0">
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Support prioritaire</label>
                        <select name="support" id="edit_support" class="form-control">
                            <option value="0">Non</option>
                            <option value="1">Oui</option>
                        </select>
                    </div>
                    <div class="limit-group">
                        <label class="limit-label">Gestionnaire de compte</label>
                        <select name="account_manager" id="edit_account_manager" class="form-control">
                            <option value="0">Non</option>
                            <option value="1">Oui</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editPlanModal')">Annuler</button>
                    <button type="submit" name="update_plan" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i>
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Plan Modal -->
    <div id="deletePlanModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Supprimer le Plan</h3>
                <button class="close-modal" onclick="closeModal('deletePlanModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="plan_id" id="delete_plan_id">
                <div style="text-align: center; padding: 1rem 0 2rem;">
                    <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"></i>
                    <p style="font-size: 1.125rem; color: #374151; margin-bottom: 0.5rem;">
                        Êtes-vous sûr de vouloir supprimer le plan 
                        <strong id="delete_plan_name" style="color: #1f2937;"></strong> ?
                    </p>
                    <p style="color: #6b7280; font-size: 0.95rem;">
                        <i class="bi bi-info-circle"></i>
                        Cette action est irréversible et supprimera toutes les limites associées.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('deletePlanModal')">Annuler</button>
                    <button type="submit" name="delete_plan" class="btn btn-danger">
                        <i class="bi bi-trash"></i>
                        Supprimer définitivement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Chart data from PHP
        const distributionData = <?php echo json_encode($distribution_stats); ?>;
        const monthlyGrowthData = <?php echo json_encode($monthly_growth); ?>;
        
        // Initialize charts
        let subscriptionsChart, distributionChart, monthlyAnalyticsChart;
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
        });
        
        function initializeCharts() {
            // Subscriptions Growth Chart
            const subscriptionsCtx = document.getElementById('subscriptionsChart');
            if (subscriptionsCtx) {
                subscriptionsChart = new Chart(subscriptionsCtx, {
                    type: 'line',
                    data: {
                        labels: monthlyGrowthData.map(item => {
                            const [year, month] = item.month.split('-');
                            return new Date(year, month - 1).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                        }),
                        datasets: [
                            {
                                label: 'Nouveaux Abonnements',
                                data: monthlyGrowthData.map(item => item.new_subscriptions),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                borderWidth: 3,
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Nouveaux Clients',
                                data: monthlyGrowthData.map(item => item.new_customers),
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 3,
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                                titleColor: '#1f2937',
                                bodyColor: '#6b7280',
                                borderColor: '#e5e7eb',
                                borderWidth: 1,
                                cornerRadius: 8,
                                displayColors: true,
                                callbacks: {
                                    label: function(context) {
                                        return `${context.dataset.label}: ${context.parsed.y}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        animations: {
                            tension: {
                                duration: 1000,
                                easing: 'linear'
                            }
                        }
                    }
                });
            }
            
            // Distribution Chart
            const distributionCtx = document.getElementById('distributionChart');
            if (distributionCtx) {
                distributionChart = new Chart(distributionCtx, {
                    type: 'doughnut',
                    data: {
                        labels: distributionData.map(item => item.plan_type),
                        datasets: [{
                            data: distributionData.map(item => item.user_count),
                            backgroundColor: distributionData.map(item => getPlanColor(item.plan_type)),
                            borderWidth: 3,
                            borderColor: '#ffffff',
                            hoverOffset: 15
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '65%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    },
                                    color: '#6b7280'
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                                titleColor: '#1f2937',
                                bodyColor: '#6b7280',
                                borderColor: '#e5e7eb',
                                borderWidth: 1,
                                cornerRadius: 8,
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                                        return `${context.label}: ${context.parsed} utilisateurs (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        animation: {
                            animateScale: true,
                            animateRotate: true
                        }
                    }
                });
            }
            
            // Monthly Analytics Chart
            const monthlyCtx = document.getElementById('monthlyAnalyticsChart');
            if (monthlyCtx) {
                monthlyAnalyticsChart = new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyGrowthData.map(item => {
                            const [year, month] = item.month.split('-');
                            return new Date(year, month - 1).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                        }),
                        datasets: [
                            {
                                label: 'Revenus (D.H)',
                                data: monthlyGrowthData.map(item => item.monthly_revenue),
                                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                                borderRadius: 6,
                                borderWidth: 0,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Nouveaux Clients',
                                data: monthlyGrowthData.map(item => item.new_customers),
                                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                                borderRadius: 6,
                                borderWidth: 0,
                                yAxisID: 'y1',
                                type: 'line',
                                tension: 0.4,
                                fill: false
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                position: 'left',
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y1: {
                                beginAtZero: true,
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
        
        function getPlanColor(planType) {
            const colors = {
                'Gratuit': '#6b7280',
                'Starter': '#3b82f6',
                'Professional': '#10b981',
                'Growth': '#f59e0b',
                'Business': '#8b5cf6',
                'Inconnu': '#6b7280'
            };
            return colors[planType] || '#6b7280';
        }
        
        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        // Plan management functions
        function editPlan(planId) {
            // Fetch plan data via AJAX
            fetch('../controllers/get_superPlanApi.php?id=' + planId)
                .then(response => response.json())
                .then(plan => {
                    document.getElementById('edit_plan_id').value = plan.id;
                    document.getElementById('edit_plan_name').value = plan.name;
                    document.getElementById('edit_plan_price').value = plan.price;
                    document.getElementById('edit_plan_custom').checked = plan.is_custom;
                    
                    // Set limit values
                    document.getElementById('edit_orders_per_month').value = plan.limits.orders_per_month || 0;
                    document.getElementById('edit_stores').value = plan.limits.stores || 0;
                    document.getElementById('edit_carriers').value = plan.limits.carriers || 0;
                    document.getElementById('edit_team_members').value = plan.limits.team_members || 0;
                    document.getElementById('edit_support').value = plan.limits.support || 0;
                    document.getElementById('edit_account_manager').value = plan.limits.account_manager || 0;
                    
                    openModal('editPlanModal');
                })
                .catch(error => {
                    console.error('Error fetching plan data:', error);
                    alert('Erreur lors du chargement des données du plan');
                });
        }
        
        function deletePlan(planId, planName) {
            document.getElementById('delete_plan_id').value = planId;
            document.getElementById('delete_plan_name').textContent = planName;
            openModal('deletePlanModal');
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = 'auto';
            }
        });
        
        // Add smooth scrolling for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>