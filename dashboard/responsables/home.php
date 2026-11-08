<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if responsable is logged in via session
if(!Session::exists(Config::get('session/session_name'))) {
    Redirect::to('../../login'); 
}

// Get responsable info from session
$responsable_id = isset($_SESSION['responsable_id']) ? $_SESSION['responsable_id'] : null;
$responsable_email = isset($_SESSION['responsable_email']) ? $_SESSION['responsable_email'] : null;

// If no session data, try to get it from username in session
if (!$responsable_id && isset($_SESSION['user']['username'])) {
    $responsable_email = $_SESSION['user']['username'];
    $responsable = $db->getThisQuery("SELECT * FROM responsables WHERE email = ?", [$responsable_email]);
    
    if ($responsable && !empty($responsable)) {
        $responsable = $responsable[0];
        $responsable_id = $responsable['id'];
        
        // Store in session for future use
        $_SESSION['responsable_id'] = $responsable_id;
        $_SESSION['responsable_email'] = $responsable_email;
        $_SESSION['responsable_name'] = $responsable['name'];
    }
}

// If still no responsable ID, redirect to login
if (!$responsable_id) {
    session_destroy();
    Redirect::to('../../login');
}

// Get responsable details
$responsable = $db->getThisQuery("SELECT * FROM responsables WHERE id = ?", [$responsable_id]);
if (empty($responsable)) {
    session_destroy();
    Redirect::to('../../login');
}

$responsable = $responsable[0];

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_my_agents':
            echo getMyAgents($db, $responsable_id, $responsable);
            exit;
        case 'get_agent_stats':
            echo getAgentStats($db, $_POST['agent_id'] ?? 0);
            exit;
        case 'get_agent_orders':
            echo getAgentOrders($db, $_POST['agent_id'] ?? 0);
            exit;
        case 'get_team_performance':
            echo getTeamPerformance($db, $responsable_id);
            exit;
        case 'update_agent_status':
            echo updateAgentStatus($db, $_POST['agent_id'] ?? 0, $_POST['status'] ?? 0);
            exit;
        case 'get_recent_activity':
            echo getRecentActivity($db, $responsable_id);
            exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    Redirect::to('../../login.php');
}

// Get initial data
$initial_agents = json_decode(getMyAgents($db, $responsable_id, $responsable), true);

function getMyAgents($db, $responsable_id, $responsable = null) {
    try {
        // Get agents managed by this responsable
        $query = "
            SELECT 
                a.id,
                a.name,
                a.email,
                a.phone,
                a.region_id,
                a.service_fee,
                a.confirmation_rate,
                a.score,
                a.total_payed,
                a.total_earned,
                a.is_active,
                a.created_at,
                a.updated_at,
                a.team_id,
                r.name as region_name,
                t.name as team_name,
                
                -- Performance stats
                COUNT(DISTINCT aoa.id) as total_assignments,
                COUNT(DISTINCT CASE WHEN aoa.status = 'completed' THEN aoa.id END) as completed_assignments,
                COUNT(DISTINCT CASE WHEN aoa.status = 'pending' THEN aoa.id END) as pending_assignments,
                
                -- Confirmation stats
                COUNT(DISTINCT ac.id) as total_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN 1 ELSE 0 END) as successful_confirmations,
                SUM(CASE WHEN ac.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_confirmations,
                
                -- Financial stats
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as total_confirmed_amount,
                SUM(CASE WHEN ac.status = 'cancelled' THEN ac.amount ELSE 0 END) as total_cancelled_amount,
                
                -- Today's performance
                COUNT(DISTINCT CASE WHEN DATE(aoa.assigned_at) = CURDATE() THEN aoa.id END) as today_assignments,
                COUNT(DISTINCT CASE WHEN DATE(ac.created_at) = CURDATE() THEN ac.id END) as today_confirmations,
                SUM(CASE WHEN DATE(ac.created_at) = CURDATE() AND ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as today_confirmed_amount
                
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id
            LEFT JOIN agent_confirmations ac ON a.id = ac.agent_id AND ac.order_id = aoa.order_id
            WHERE a.responsable_id = ?
            GROUP BY a.id, r.id, t.id
            ORDER BY a.is_active DESC, a.score DESC
        ";
        
        $agents = $db->getThisQuery($query, [$responsable_id]);
        
        // Calculate overall statistics
        $stats = [
            'total_agents' => count($agents ?? []),
            'active_agents' => count(array_filter($agents, fn($a) => $a['is_active'] == 1)),
            'total_assignments' => array_sum(array_column($agents, 'total_assignments')),
            'completed_assignments' => array_sum(array_column($agents, 'completed_assignments')),
            'total_confirmations' => array_sum(array_column($agents, 'total_confirmations')),
            'successful_confirmations' => array_sum(array_column($agents, 'successful_confirmations')),
            'total_confirmed_amount' => array_sum(array_column($agents, 'total_confirmed_amount')),
            'total_earned' => array_sum(array_column($agents, 'total_earned')),
            'today_confirmations' => array_sum(array_column($agents, 'today_confirmations')),
            'today_amount' => array_sum(array_column($agents, 'today_confirmed_amount'))
        ];
        
        // Calculate success rate
        if ($stats['total_assignments'] > 0) {
            $stats['success_rate'] = round(($stats['completed_assignments'] / $stats['total_assignments']) * 100, 2);
        } else {
            $stats['success_rate'] = 0;
        }
        
        // Calculate confirmation rate
        if ($stats['total_confirmations'] > 0) {
            $stats['confirmation_rate'] = round(($stats['successful_confirmations'] / $stats['total_confirmations']) * 100, 2);
        } else {
            $stats['confirmation_rate'] = 0;
        }
        
        return json_encode([
            'success' => true,
            'responsable_name' => $responsable['name'] ?? '',
            'agents' => $agents,
            'stats' => $stats,
            'time_period' => date('F Y')
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getAgentStats($db, $agent_id) {
    try {
        $query = "
            SELECT 
                a.*,
                r.name as region_name,
                t.name as team_name,
                COUNT(DISTINCT aoa.id) as total_assignments,
                COUNT(DISTINCT CASE WHEN aoa.status = 'completed' THEN aoa.id END) as completed_assignments,
                COUNT(DISTINCT ac.id) as total_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN 1 ELSE 0 END) as successful_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as total_confirmed_amount,
                COUNT(DISTINCT CASE WHEN DATE(ac.created_at) = CURDATE() AND ac.status = 'confirmed' THEN ac.id END) as today_successful_confirmations
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id
            LEFT JOIN agent_confirmations ac ON a.id = ac.agent_id
            WHERE a.id = ?
            GROUP BY a.id, r.id, t.id
        ";
        
        $agent = $db->getThisQuery($query, [$agent_id]);
        if (empty($agent)) {
            return json_encode([
                'success' => false,
                'message' => 'Agent non trouvé'
            ]);
        }
        
        $agent = $agent[0];
        
        // Get performance over time
        $performance_query = "
            SELECT 
                DATE(ac.created_at) as date,
                COUNT(DISTINCT ac.id) as daily_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as daily_amount
            FROM agent_confirmations ac
            WHERE ac.agent_id = ?
            AND ac.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(ac.created_at)
            ORDER BY date DESC
        ";
        
        $performance_data = $db->getThisQuery($performance_query, [$agent_id]);
        
        // Get recent activity
        $activity_query = "
            SELECT 
                ac.*,
                o.order_number,
                o.customer_name,
                DATE_FORMAT(ac.created_at, '%Y-%m-%d %H:%i') as formatted_date
            FROM agent_confirmations ac
            LEFT JOIN orders o ON ac.order_id = o.id
            WHERE ac.agent_id = ?
            ORDER BY ac.created_at DESC
            LIMIT 10
        ";
        
        $recent_activity = $db->getThisQuery($activity_query, [$agent_id]);
        
        return json_encode([
            'success' => true,
            'agent' => $agent,
            'performance_data' => $performance_data,
            'recent_activity' => $recent_activity
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getAgentOrders($db, $agent_id) {
    try {
        $query = "
            SELECT 
                o.*,
                aoa.assigned_at,
                aoa.status as assignment_status,
                ac.status as confirmation_status,
                ac.handled_at as confirmation_handled_at,
                ac.amount as confirmation_amount,
                s.storeName as store_name,
                DATE_FORMAT(ac.created_at, '%d/%m/%Y %H:%i') as formatted_date
            FROM orders o
            INNER JOIN agent_order_assignments aoa ON o.id = aoa.order_id
            LEFT JOIN agent_confirmations ac ON o.id = ac.order_id AND ac.agent_id = aoa.agent_id
            LEFT JOIN stores s ON o.store_id = s.id
            WHERE aoa.agent_id = ?
            ORDER BY aoa.assigned_at DESC
            LIMIT 50
        ";
        
        $orders = $db->getThisQuery($query, [$agent_id]);
        
        // Add status translations and colors
        foreach ($orders as &$order) {
            $order['status_badge'] = getOrderStatusBadge($order['confirmation_status']);
        }
        
        return json_encode([
            'success' => true,
            'orders' => $orders
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getTeamPerformance($db, $responsable_id) {
    try {
        // First, get the team ID for this responsable
        $team_query = "SELECT team_id FROM responsables WHERE id = ?";
        $team_result = $db->getThisQuery($team_query, [$responsable_id]);
        
        if (empty($team_result)) {
            return json_encode([
                'success' => true,
                'performance_data' => []
            ]);
        }
        
        $team_id = $team_result[0]['team_id'];
        
        // If team_id is null or 0, get performance for all agents under this responsable
        if (empty($team_id)) {
            $query = "
                SELECT 
                    DATE(ac.created_at) as date,
                    COUNT(DISTINCT ac.id) as total_confirmations,
                    SUM(CASE WHEN ac.status = 'confirmed' THEN 1 ELSE 0 END) as successful_confirmations,
                    SUM(CASE WHEN ac.status = 'confirmed' THEN COALESCE(ac.amount, 0) ELSE 0 END) as total_amount
                FROM agent_confirmations ac
                INNER JOIN agents a ON ac.agent_id = a.id
                WHERE a.responsable_id = ?
                AND ac.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(ac.created_at)
                ORDER BY date ASC
            ";
            
            $performance_data = $db->getThisQuery($query, [$responsable_id]);
        } else {
            // Get performance for agents in the same team
            $query = "
                SELECT 
                    DATE(ac.created_at) as date,
                    COUNT(DISTINCT ac.id) as total_confirmations,
                    SUM(CASE WHEN ac.status = 'confirmed' THEN 1 ELSE 0 END) as successful_confirmations,
                    SUM(CASE WHEN ac.status = 'confirmed' THEN COALESCE(ac.amount, 0) ELSE 0 END) as total_amount
                FROM agent_confirmations ac
                INNER JOIN agents a ON ac.agent_id = a.id
                WHERE a.team_id = ?
                AND ac.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(ac.created_at)
                ORDER BY date ASC
            ";
            
            $performance_data = $db->getThisQuery($query, [$team_id]);
        }
        
        // Ensure we always return an array
        if (empty($performance_data)) {
            $performance_data = [];
        }
        
        return json_encode([
            'success' => true,
            'performance_data' => $performance_data
        ]);
        
    } catch (Exception $e) {
        error_log("Team Performance Error: " . $e->getMessage());
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage(),
            'performance_data' => []
        ]);
    }
}

function getRecentActivity($db, $responsable_id) {
    try {
        $query = "
            SELECT 
                ac.*,
                a.name as agent_name,
                o.order_number,
                o.customer_name,
                DATE_FORMAT(ac.created_at, '%d/%m %H:%i') as formatted_date,
                CASE 
                    WHEN ac.status = 'confirmed' THEN 'success'
                    WHEN ac.status = 'cancelled' THEN 'danger'
                    ELSE 'warning'
                END as activity_type
            FROM agent_confirmations ac
            INNER JOIN agents a ON ac.agent_id = a.id
            LEFT JOIN orders o ON ac.order_id = o.id
            WHERE a.responsable_id = ?
            ORDER BY ac.created_at DESC
            LIMIT 10
        ";
        
        $activities = $db->getThisQuery($query, [$responsable_id]);
        
        return json_encode([
            'success' => true,
            'activities' => $activities
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function updateAgentStatus($db, $agent_id, $status) {
    try {
        $query = "UPDATE agents SET is_active = ?, updated_at = NOW() WHERE id = ?";
        $db->getThisQuery($query, [$status, $agent_id]);
        
        return json_encode([
            'success' => true,
            'message' => $status ? 'Agent activé avec succès' : 'Agent désactivé avec succès'
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getOrderStatusBadge($status) {
    $badges = [
        'confirmed' => ['bg-success', 'Confirmé'],
        'pending' => ['bg-warning', 'En attente'],
        'cancelled' => ['bg-danger', 'Annulé'],
        'processing' => ['bg-info', 'En traitement']
    ];
    
    return $badges[$status] ?? ['bg-secondary', $status];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Responsable | Supervision d'Agents</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --sidebar-width: 260px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--dark-color);
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: white;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        
        .logo-section {
            padding: 30px 25px;
            background: var(--primary-gradient);
            color: white;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo i {
            font-size: 28px;
        }
        
        .user-info {
            padding: 25px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 32px;
            font-weight: 600;
        }
        
        .user-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-role {
            color: #64748b;
            font-size: 14px;
        }
        
        .nav-menu {
            flex: 1;
            padding: 20px 0;
        }
        
        .nav-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #64748b;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .nav-item:hover {
            color: #667eea;
            background: #f1f5f9;
        }
        
        .nav-item.active {
            color: #667eea;
            background: #f1f5f9;
            border-left-color: #667eea;
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        
        .logout-btn {
            padding: 15px 25px;
            border-top: 1px solid #e2e8f0;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 8px;
        }
        
        .header-subtitle {
            color: #64748b;
            font-size: 14px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
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
        
        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.warning::before { background: var(--warning-gradient); }
        .stat-card.danger::before { background: var(--danger-gradient); }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 20px;
            color: white;
            background: var(--primary-gradient);
        }
        
        .stat-card.success .stat-icon { background: var(--success-gradient); }
        .stat-card.warning .stat-icon { background: var(--warning-gradient); }
        .stat-card.danger .stat-icon { background: var(--danger-gradient); }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--dark-color);
        }
        
        .stat-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }
        
        .stat-change {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            margin-top: 8px;
        }
        
        .stat-change.positive { color: #10b981; }
        .stat-change.negative { color: #ef4444; }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Agents Container */
        .agents-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        /* Filters */
        .filters-bar {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }
        
        .filter-select {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            color: var(--dark-color);
            transition: border-color 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Agents Table */
        .agents-table-container {
            overflow-x: auto;
        }
        
        .agents-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .agents-table th {
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .agents-table td {
            padding: 20px 12px;
            border-bottom: 1px solid #e2e8f0;
            transition: background-color 0.2s ease;
        }
        
        .agents-table tbody tr:hover td {
            background-color: #f8fafc;
        }
        
        .agent-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .agent-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .agent-info h5 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--dark-color);
        }
        
        .agent-info p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }
        
        .team-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            background: #f1f5f9;
            color: #64748b;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .status-badge::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.active::before {
            background: #10b981;
        }
        
        .status-badge.inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-badge.inactive::before {
            background: #ef4444;
        }
        
        .progress-bar {
            width: 120px;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--success-gradient);
            border-radius: 4px;
        }
        
        .score-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            color: white;
        }
        
        .score-high { background: var(--success-gradient); }
        .score-medium { background: var(--warning-gradient); }
        .score-low { background: var(--danger-gradient); }
        
        /* Actions */
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f1f5f9;
            color: #64748b;
        }
        
        .btn-icon:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-icon.success:hover { background: var(--success-gradient); }
        .btn-icon.danger:hover { background: var(--danger-gradient); }
        
        /* Sidebar Right */
        .activity-sidebar {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            height: fit-content;
        }
        
        .activity-list {
            margin-top: 20px;
        }
        
        .activity-item {
            display: flex;
            gap: 12px;
            padding: 16px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .activity-icon.success {
            background: #d1fae5;
            color: #10b981;
        }
        
        .activity-icon.danger {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .activity-content h6 {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--dark-color);
        }
        
        .activity-content p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }
        
        .activity-time {
            font-size: 11px;
            color: #94a3b8;
        }
        
        /* Chart */
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        
        /* Modal */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 25px 30px;
            border: none;
        }
        
        .modal-title {
            font-weight: 600;
            font-size: 20px;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .agent-details-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .agent-details-avatar {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 40px;
            font-weight: 600;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin: 30px 0;
        }
        
        .stat-mini-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-mini-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-mini-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-mini-label {
            font-size: 12px;
            color: #64748b;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Loading */
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-section">
            <div class="logo">
                <i class="fas fa-chart-network"></i>
                <span>TeamManager</span>
            </div>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($responsable['name'], 0, 1)); ?>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($responsable['name']); ?></div>
            <div class="user-role">Responsable d'Agents</div>
        </div>
        
        <div class="nav-menu">
            <a href="./home" class="nav-item active">
                <i class="fas fa-users"></i>
                <span>Mes Agents</span>
            </a>
            <a href="./performance" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Performance</span>
            </a>
            <a href="./planning" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Planning</span>
            </a>
            <a href="./settings" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
        </div>
        
        <div class="logout-btn">
            <a href="?logout" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <h1>Dashboard Responsable</h1>
                <div class="header-subtitle">
                    <i class="fas fa-calendar-alt me-2"></i>
                    <?php echo date('l, d F Y'); ?>
                </div>
            </div>
            <div>
                <button class="btn btn-primary" onclick="loadAgents()">
                    <i class="fas fa-sync-alt me-2"></i>Actualiser
                </button>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="stats-grid" id="stats-overview">
            <?php if ($initial_agents['success']): ?>
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $initial_agents['stats']['total_agents']; ?></div>
                <div class="stat-label">Agents Totaux</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <?php echo $initial_agents['stats']['active_agents']; ?> actifs
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-bullseye"></i>
                </div>
                <div class="stat-value"><?php echo $initial_agents['stats']['confirmation_rate']; ?>%</div>
                <div class="stat-label">Taux de Confirmation</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <?php echo $initial_agents['stats']['today_confirmations']; ?> aujourd'hui
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo number_format($initial_agents['stats']['total_confirmed_amount'], 0); ?> MAD</div>
                <div class="stat-label">Montant Confirmé</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <?php echo number_format($initial_agents['stats']['today_amount'], 0); ?> MAD aujourd'hui
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo $initial_agents['stats']['success_rate']; ?>%</div>
                <div class="stat-label">Taux de Réussite</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <?php echo $initial_agents['stats']['completed_assignments']; ?> terminées
                </div>
            </div>
            <?php else: ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-spinner"></i>
                </div>
                <div class="stat-value">0</div>
                <div class="stat-label">Chargement...</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Agents Section -->
            <div class="agents-container">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-users me-2"></i>Mes Agents
                    </div>
                    <div class="text-muted">
                        <?php echo $initial_agents['success'] ? count($initial_agents['agents']) : 0; ?> agents
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-bar">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <div class="filter-label">Recherche</div>
                            <input type="text" 
                                   id="search-input" 
                                   class="filter-select" 
                                   placeholder="Nom, email, région..."
                                   oninput="filterAgents()">
                        </div>
                        
                        <div class="filter-group">
                            <div class="filter-label">Statut</div>
                            <select id="status-filter" class="filter-select" onchange="filterAgents()">
                                <option value="all">Tous les statuts</option>
                                <option value="active">Actifs seulement</option>
                                <option value="inactive">Inactifs seulement</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <div class="filter-label">Équipe</div>
                            <select id="team-filter" class="filter-select" onchange="filterAgents()">
                                <option value="all">Toutes les équipes</option>
                                <?php if ($initial_agents['success']): ?>
                                    <?php 
                                    $teams = array_unique(array_filter(array_column($initial_agents['agents'], 'team_name')));
                                    foreach ($teams as $team): ?>
                                        <option value="<?php echo htmlspecialchars($team); ?>">
                                            <?php echo htmlspecialchars($team); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Agents Table -->
                <div class="agents-table-container">
                    <table class="agents-table">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Statut</th>
                                <th>Taux</th>
                                <th>Score</th>
                                <th>Confirmations</th>
                                <th>Montant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="agents-tbody">
                            <?php if ($initial_agents['success'] && !empty($initial_agents['agents'])): ?>
                                <?php foreach ($initial_agents['agents'] as $agent): ?>
                                <?php
                                $score = $agent['score'] ?? 0;
                                $score_class = 'score-medium';
                                if ($score >= 80) $score_class = 'score-high';
                                elseif ($score <= 40) $score_class = 'score-low';
                                ?>
                                <tr data-agent-id="<?php echo $agent['id']; ?>">
                                    <td>
                                        <div class="agent-cell">
                                            <div class="agent-avatar" style="background: #764ba2">
                                                <?php echo strtoupper(substr($agent['name'], 0, 1)); ?>
                                            </div>
                                            <div class="agent-info">
                                                <h5><?php echo htmlspecialchars($agent['name']); ?></h5>
                                                <p><?php echo htmlspecialchars($agent['email']); ?></p>
                                                <?php if ($agent['team_name']): ?>
                                                    <div class="team-badge">
                                                        <i class="fas fa-users"></i>
                                                        <?php echo htmlspecialchars($agent['team_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($agent['is_active'] == 1): ?>
                                            <span class="status-badge active">
                                                Actif
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge inactive">
                                                Inactif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min($agent['confirmation_rate'] ?? 0, 100); ?>%"></div>
                                        </div>
                                        <small style="font-size: 12px; margin-left: 8px;">
                                            <?php echo $agent['confirmation_rate'] ?? 0; ?>%
                                        </small>
                                    </td>
                                    <td>
                                        <div class="score-badge <?php echo $score_class; ?>">
                                            <?php echo $score; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo $agent['successful_confirmations'] ?? 0; ?></strong>
                                            <div class="text-muted" style="font-size: 12px;">
                                                sur <?php echo $agent['total_confirmations'] ?? 0; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($agent['total_confirmed_amount'] ?? 0, 0); ?> MAD</strong>
                                        <div class="text-muted" style="font-size: 12px;">
                                            <?php echo number_format($agent['today_confirmed_amount'] ?? 0, 0); ?> MAD aujourd'hui
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon" onclick="viewAgentDetails(<?php echo $agent['id']; ?>)" 
                                                    title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon success" onclick="toggleAgentStatus(<?php echo $agent['id']; ?>, <?php echo $agent['is_active'] == 1 ? 0 : 1; ?>)" 
                                                    title="<?php echo $agent['is_active'] == 1 ? 'Désactiver' : 'Activer'; ?>">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="loading">
                                            <div class="loading-spinner"></div>
                                            <p>Chargement des agents...</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Activity Sidebar -->
            <div class="activity-sidebar">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-history me-2"></i>Activité Récente
                    </div>
                </div>
                
                <div class="activity-list" id="activity-list">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="chart-container">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-chart-line me-2"></i>Performance de l'Équipe (30 jours)
                </div>
            </div>
            <canvas id="performanceChart" height="120"></canvas>
        </div>
    </div>

    <!-- Agent Details Modal -->
    <div class="modal fade" id="agentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de l'Agent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: brightness(0) invert(1);"></button>
                </div>
                <div class="modal-body" id="agentModalBody">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Agent Orders Modal -->
    <div class="modal fade" id="ordersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Commandes de l'Agent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: brightness(0) invert(1);"></button>
                </div>
                <div class="modal-body" id="ordersModalBody">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <style>

        /* Loading State */
        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
        }

        .loading-spinner {
            display: flex;
            gap: 4px;
            margin-bottom: 16px;
        }

        .spinner-dot {
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            animation: pulse 1.5s ease-in-out infinite;
        }

        .spinner-dot:nth-child(2) { animation-delay: 0.2s; }
        .spinner-dot:nth-child(3) { animation-delay: 0.4s; }

        .loading-text {
            color: #64748b;
            font-size: 14px;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        /* Agent Details */
        .agent-details {
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .agent-details.fade-in {
            opacity: 1;
            transform: translateY(0);
        }

        .agent-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 0 0 25px 0;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }

        .avatar-container {
            position: relative;
        }

        .agent-avatar {
            width: 80px;
            height: 80px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 600;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .status-indicator {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid white;
        }

        .status-indicator.active {
            background: #10b981;
        }

        .status-indicator.inactive {
            background: #94a3b8;
        }

        .agent-basic-info {
            flex: 1;
        }

        .agent-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .agent-email {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .agent-meta {
            display: flex;
            gap: 8px;
        }

        .badge-active, .badge-inactive {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background: #f1f5f9;
            color: #64748b;
        }

        .badge-team {
            background: #e0f2fe;
            color: #0369a1;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Key Metrics */
        .key-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }

        .metric-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .metric-content {
            flex: 1;
        }

        .metric-value {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 4px;
        }

        .metric-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        /* Performance Section */
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }

        .performance-stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .perf-stat {
            background: #f8fafc;
            border-radius: 10px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .perf-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .perf-content {
            flex: 1;
        }

        .perf-value {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .perf-label {
            font-size: 12px;
            color: #64748b;
        }

        /* Success Rate */
        .success-rate {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
            margin-top: 16px;
        }

        .success-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        .success-value {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .success-progress {
            height: 8px;
            border-radius: 4px;
            background: #f1f5f9;
        }

        .success-progress .progress-bar {
            border-radius: 4px;
        }

        .success-details {
            font-size: 12px;
        }

        /* Additional Info */
        .additional-info {
            margin: 25px 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
        }

        .info-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-view-orders {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-view-orders:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }

        /* Error State */
        .error-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            text-align: center;
            padding: 30px;
        }

        .error-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #fee2e2;
            color: #ef4444;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .agent-header {
                flex-direction: column;
                text-align: center;
            }
            
            .key-metrics {
                grid-template-columns: 1fr;
            }
            
            .performance-stats-grid {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>

    <script>
        let performanceChart = null;
        
        // Load recent activity
        function loadRecentActivity() {
            const formData = new FormData();
            formData.append('action', 'get_recent_activity');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.activities) {
                    updateActivityList(data.activities);
                }
            })
            .catch(error => console.error('Error loading activity:', error));
        }
        
        function updateActivityList(activities) {
            const activityList = document.getElementById('activity-list');
            
            if (!activities || activities.length === 0) {
                activityList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-2x text-muted mb-3"></i>
                        <p class="text-muted">Aucune activité récente</p>
                    </div>
                `;
                return;
            }
            
            activityList.innerHTML = activities.map(activity => `
                <div class="activity-item">
                    <div class="activity-icon ${activity.activity_type}">
                        <i class="fas fa-${activity.status === 'confirmed' ? 'check-circle' : 'times-circle'}"></i>
                    </div>
                    <div class="activity-content">
                        <h6>${escapeHtml(activity.agent_name)}</h6>
                        <p>${getActivityText(activity)}</p>
                        <div class="activity-time">
                            <i class="far fa-clock me-1"></i>${activity.formatted_date}
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        function getActivityText(activity) {
            if (activity.status === 'confirmed') {
                return `Confirmation réussie #${activity.order_number || activity.order_id}`;
            } else if (activity.status === 'cancelled') {
                return `Confirmation annulée #${activity.order_number || activity.order_id}`;
            }
            return `Nouvelle activité sur #${activity.order_number || activity.order_id}`;
        }
        
        function loadAgents() {
            const tbody = document.getElementById('agents-tbody');
            const statsOverview = document.getElementById('stats-overview');
            
            // Show loading
            tbody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="loading">
                            <div class="loading-spinner"></div>
                            <p>Actualisation des données...</p>
                        </div>
                    </td>
                </tr>
            `;
            
            const formData = new FormData();
            formData.append('action', 'get_my_agents');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateAgentsTable(data);
                    updateStatsOverview(data);
                    loadPerformanceChart();
                    loadRecentActivity();
                } else {
                    showToast(data.message || 'Erreur lors du chargement', 'danger');
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center py-4 text-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Erreur de chargement
                            </td>
                        </tr>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4 text-danger">
                            <i class="fas fa-wifi-slash me-2"></i>
                            Erreur de connexion
                        </td>
                    </tr>
                `;
            });
        }
        
        function filterAgents() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const teamFilter = document.getElementById('team-filter').value;
            
            const rows = document.querySelectorAll('#agents-tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const agentName = row.querySelector('.agent-info h5').textContent.toLowerCase();
                const agentEmail = row.querySelector('.agent-info p').textContent.toLowerCase();
                const isActive = row.querySelector('.status-badge').classList.contains('active');
                const teamName = row.querySelector('.team-badge') ? 
                    row.querySelector('.team-badge').textContent.toLowerCase() : '';
                
                let showRow = true;
                
                // Search filter
                if (searchTerm && !agentName.includes(searchTerm) && !agentEmail.includes(searchTerm)) {
                    showRow = false;
                }
                
                // Status filter
                if (statusFilter === 'active' && !isActive) {
                    showRow = false;
                } else if (statusFilter === 'inactive' && isActive) {
                    showRow = false;
                }
                
                // Team filter
                if (teamFilter !== 'all' && teamFilter !== '' && 
                    teamName !== teamFilter.toLowerCase().trim()) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
                if (showRow) visibleCount++;
            });
            
            // Update count
            const countElement = document.querySelector('.agents-container .text-muted');
            if (countElement) {
                countElement.textContent = `${visibleCount} agents`;
            }
        }
        
        function updateAgentsTable(data) {
            const tbody = document.getElementById('agents-tbody');
            
            if (!data.agents || data.agents.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-users-slash fa-2x text-muted mb-3"></i>
                            <p class="text-muted">Aucun agent trouvé</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = data.agents.map(agent => {
                const score = agent.score || 0;
                let scoreClass = 'score-medium';
                if (score >= 80) scoreClass = 'score-high';
                else if (score <= 40) scoreClass = 'score-low';
                
                const initials = agent.name ? agent.name.charAt(0).toUpperCase() : 'A';
                const teamColor = agent.team_color ? `style="background: ${escapeHtml(agent.team_color)}"` : '';
                
                return `
                    <tr data-agent-id="${agent.id}">
                        <td>
                            <div class="agent-cell">
                                <div class="agent-avatar" ${teamColor}>
                                    ${initials}
                                </div>
                                <div class="agent-info">
                                    <h5>${escapeHtml(agent.name)}</h5>
                                    <p>${escapeHtml(agent.email)}</p>
                                    ${agent.team_name ? `
                                        <div class="team-badge">
                                            <i class="fas fa-users"></i>
                                            ${escapeHtml(agent.team_name)}
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </td>
                        <td>
                            ${agent.is_active == 1 ? 
                                '<span class="status-badge active">Actif</span>' : 
                                '<span class="status-badge inactive">Inactif</span>'}
                        </td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${Math.min(agent.confirmation_rate || 0, 100)}%"></div>
                            </div>
                            <small style="font-size: 12px; margin-left: 8px;">
                                ${agent.confirmation_rate || 0}%
                            </small>
                        </td>
                        <td>
                            <div class="score-badge ${scoreClass}">
                                ${score}
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong>${agent.successful_confirmations || 0}</strong>
                                <div class="text-muted" style="font-size: 12px;">
                                    sur ${agent.total_confirmations || 0}
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong>${formatCurrency(agent.total_confirmed_amount || 0)}</strong>
                            <div class="text-muted" style="font-size: 12px;">
                                ${formatCurrency(agent.today_confirmed_amount || 0)} aujourd'hui
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="viewAgentDetails(${agent.id})" 
                                        title="Voir détails">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon ${agent.is_active == 1 ? 'danger' : 'success'}" 
                                        onclick="toggleAgentStatus(${agent.id}, ${agent.is_active == 1 ? 0 : 1})" 
                                        title="${agent.is_active == 1 ? 'Désactiver' : 'Activer'}">
                                    <i class="fas fa-power-off"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        function updateStatsOverview(data) {
            if (!data.stats) return;
            
            const stats = data.stats;
            const statsHtml = `
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value">${stats.total_agents || 0}</div>
                    <div class="stat-label">Agents Totaux</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        ${stats.active_agents || 0} actifs
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <div class="stat-value">${stats.confirmation_rate || 0}%</div>
                    <div class="stat-label">Taux de Confirmation</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        ${stats.today_confirmations || 0} aujourd'hui
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value">${formatCurrency(stats.total_confirmed_amount || 0)}</div>
                    <div class="stat-label">Montant Confirmé</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        ${formatCurrency(stats.today_amount || 0)} aujourd'hui
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value">${stats.success_rate || 0}%</div>
                    <div class="stat-label">Taux de Réussite</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        ${stats.completed_assignments || 0} terminées
                    </div>
                </div>
            `;
            
            document.getElementById('stats-overview').innerHTML = statsHtml;
        }
        
        function toggleAgentStatus(agentId, status) {
            const action = status ? 'activer' : 'désactiver';
            
            if (!confirm(`Voulez-vous ${action} cet agent?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_agent_status');
            formData.append('agent_id', agentId);
            formData.append('status', status);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    loadAgents();
                } else {
                    showToast(data.message || 'Erreur lors de la mise à jour', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function viewAgentDetails(agentId) {
            const modal = new bootstrap.Modal(document.getElementById('agentModal'));
            const modalBody = document.getElementById('agentModalBody');
            
            // Animated loading state
            modalBody.innerHTML = `
                <div class="loading-container">
                    <div class="loading-spinner">
                        <div class="spinner-dot"></div>
                        <div class="spinner-dot"></div>
                        <div class="spinner-dot"></div>
                    </div>
                    <p class="loading-text">Chargement des détails...</p>
                </div>
            `;
            
            modal.show();
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_agent_stats&agent_id=${agentId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const agent = data.agent;
                    const initials = agent.name?.charAt(0).toUpperCase() || 'A';
                    const successRate = agent.total_assignments ? Math.round((agent.completed_assignments / agent.total_assignments) * 100) : 0;
                    const isActive = agent.is_active == 1;
                    
                    modalBody.innerHTML = `
                        <div class="agent-details">
                            <!-- Header with avatar and basic info -->
                            <div class="agent-header" style="--team-color: ${agent.team_color || '#667eea'}">
                                <div class="avatar-container">
                                    <div class="agent-avatar" style="background: ${agent.team_color ? `linear-gradient(135deg, ${agent.team_color} 0%, ${adjustColor(agent.team_color, -20)} 100%)` : 'var(--primary-gradient)'}">
                                        ${initials}
                                    </div>
                                    <div class="status-indicator ${isActive ? 'active' : 'inactive'}"></div>
                                </div>
                                <div class="agent-basic-info">
                                    <h4 class="agent-name">${escapeHtml(agent.name)}</h4>
                                    <p class="agent-email">
                                        <i class="fas fa-envelope me-2"></i>${escapeHtml(agent.email)}
                                    </p>
                                    <div class="agent-meta">
                                        <span class="badge ${isActive ? 'badge-active' : 'badge-inactive'}">
                                            <i class="fas fa-circle ${isActive ? 'text-success' : 'text-secondary'} me-1"></i>
                                            ${isActive ? 'Actif' : 'Inactif'}
                                        </span>
                                        <span class="badge badge-team">
                                            <i class="fas fa-users me-1"></i>${escapeHtml(agent.team_name || 'Sans équipe')}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Key Metrics Cards -->
                            <div class="key-metrics">
                                <div class="metric-card">
                                    <div class="metric-icon" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));">
                                        <i class="fas fa-trophy" style="color: #667eea;"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value">${agent.score || 0}</div>
                                        <div class="metric-label">Score</div>
                                    </div>
                                </div>
                                
                                <div class="metric-card">
                                    <div class="metric-icon" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(59, 130, 246, 0.1));">
                                        <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value">${agent.confirmation_rate || 0}%</div>
                                        <div class="metric-label">Taux de Confirmation</div>
                                    </div>
                                </div>
                                
                                <div class="metric-card">
                                    <div class="metric-icon" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1));">
                                        <i class="fas fa-coins" style="color: #f59e0b;"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value">${formatCompactCurrency(agent.total_confirmed_amount || 0)}</div>
                                        <div class="metric-label">Montant Confirmé</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Performance Stats -->
                            <div class="performance-section">
                                <h6 class="section-title">
                                    <i class="fas fa-chart-line me-2"></i>Performance
                                </h6>
                                
                                <div class="performance-stats-grid">
                                    <div class="perf-stat">
                                        <div class="perf-icon">
                                            <i class="fas fa-tasks"></i>
                                        </div>
                                        <div class="perf-content">
                                            <div class="perf-value">${agent.total_assignments || 0}</div>
                                            <div class="perf-label">Assignations</div>
                                        </div>
                                    </div>
                                    
                                    <div class="perf-stat">
                                        <div class="perf-icon">
                                            <i class="fas fa-check-double"></i>
                                        </div>
                                        <div class="perf-content">
                                            <div class="perf-value">${agent.successful_confirmations || 0}</div>
                                            <div class="perf-label">Confirmations Réussies</div>
                                        </div>
                                    </div>
                                    
                                    <div class="perf-stat">
                                        <div class="perf-icon">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div class="perf-content">
                                            <div class="perf-value">${agent.today_successful_confirmations || 0}</div>
                                            <div class="perf-label">Aujourd'hui</div>
                                        </div>
                                    </div>
                                    
                                    <div class="perf-stat">
                                        <div class="perf-icon">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div class="perf-content">
                                            <div class="perf-value">${formatCompactCurrency(agent.total_earned || 0)}</div>
                                            <div class="perf-label">Total Gagné</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Success Rate Progress -->
                                <div class="success-rate">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="success-label">Taux de Réussite des Assignations</span>
                                        <span class="success-value">${successRate}%</span>
                                    </div>
                                    <div class="progress success-progress">
                                        <div class="progress-bar" role="progressbar" 
                                            style="width: ${successRate}%; background: linear-gradient(90deg, #667eea, #764ba2);" 
                                            aria-valuenow="${successRate}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="success-details mt-1">
                                        <small class="text-muted">${agent.completed_assignments || 0} terminées sur ${agent.total_assignments || 0}</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Additional Info -->
                            <div class="additional-info">
                                <h6 class="section-title">
                                    <i class="fas fa-info-circle me-2"></i>Informations
                                </h6>
                                
                                <div class="info-grid">
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-phone"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Téléphone</div>
                                            <div class="info-value">${escapeHtml(agent.phone || 'Non renseigné')}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Région</div>
                                            <div class="info-value">${escapeHtml(agent.region_name || 'Non assignée')}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-percentage"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Frais de Service</div>
                                            <div class="info-value">${agent.service_fee || 0}%</div>
                                        </div>
                                    </div>
                                    
                                    <div class="info-item">
                                        <div class="info-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="info-content">
                                            <div class="info-label">Membre depuis</div>
                                            <div class="info-value">${formatDate(agent.created_at)}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <button class="btn btn-view-orders" onclick="viewAgentOrders(${agentId})">
                                    <i class="fas fa-list me-2"></i>Voir les Commandes
                                </button>
                                <button class="btn btn-outline-secondary" onclick="toggleAgentStatus(${agentId}, ${isActive ? 0 : 1})">
                                    <i class="fas fa-power-off me-2"></i>
                                    ${isActive ? 'Désactiver' : 'Activer'}
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Add fade-in animation
                    setTimeout(() => {
                        modalBody.querySelector('.agent-details').classList.add('fade-in');
                    }, 10);
                    
                } else {
                    modalBody.innerHTML = `
                        <div class="error-state">
                            <div class="error-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <h6>Erreur de chargement</h6>
                            <p class="text-muted">${data.message || 'Impossible de charger les détails de l\'agent'}</p>
                            <button class="btn btn-primary mt-3" onclick="viewAgentDetails(${agentId})">
                                <i class="fas fa-redo me-2"></i>Réessayer
                            </button>
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error('Error:', err);
                modalBody.innerHTML = `
                    <div class="error-state">
                        <div class="error-icon">
                            <i class="fas fa-wifi-slash"></i>
                        </div>
                        <h6>Erreur de connexion</h6>
                        <p class="text-muted">Impossible de se connecter au serveur</p>
                        <button class="btn btn-primary mt-3" onclick="viewAgentDetails(${agentId})">
                            <i class="fas fa-redo me-2"></i>Réessayer
                        </button>
                    </div>
                `;
            });
        }

        // Helper functions
        function formatCompactCurrency(amount) {
            if (amount >= 1000000) {
                return (amount / 1000000).toFixed(1) + 'M MAD';
            } else if (amount >= 1000) {
                return (amount / 1000).toFixed(1) + 'k MAD';
            }
            return new Intl.NumberFormat('fr-FR').format(amount) + ' MAD';
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
            } catch (e) {
                return dateString;
            }
        }

        function adjustColor(color, amount) {
            if (!color.startsWith('#')) return color;
            
            let r = parseInt(color.slice(1, 3), 16);
            let g = parseInt(color.slice(3, 5), 16);
            let b = parseInt(color.slice(5, 7), 16);
            
            r = Math.max(0, Math.min(255, r + amount));
            g = Math.max(0, Math.min(255, g + amount));
            b = Math.max(0, Math.min(255, b + amount));
            
            return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`;
        }
        
        function viewAgentOrders(agentId) {
            const agentModal = bootstrap.Modal.getInstance(document.getElementById('agentModal'));
            if (agentModal) {
                agentModal.hide();
            }
            
            const ordersModal = new bootstrap.Modal(document.getElementById('ordersModal'));
            const ordersModalBody = document.getElementById('ordersModalBody');
            
            // Show loading
            ordersModalBody.innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>Chargement des commandes...</p>
                </div>
            `;
            
            ordersModal.show();
            
            const formData = new FormData();
            formData.append('action', 'get_agent_orders');
            formData.append('agent_id', agentId);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    ordersModalBody.innerHTML = `
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Commande</th>
                                        <th>Client</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.orders.map(order => {
                                        const status = getOrderStatusBadge(order.confirmation_status);
                                        return `
                                            <tr>
                                                <td>#${escapeHtml(order.order_number || order.id)}</td>
                                                <td>${escapeHtml(order.customer_name || order.store_name || 'N/A')}</td>
                                                <td>${formatCurrency(order.confirmation_amount || 0)}</td>
                                                <td>
                                                    <span class="badge ${status[0]}">
                                                        ${status[1]}
                                                    </span>
                                                </td>
                                                <td>${order.formatted_date || 'N/A'}</td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                } else {
                    ordersModalBody.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.message || 'Erreur lors du chargement des commandes'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading orders:', error);
                ordersModalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Erreur de connexion
                    </div>
                `;
            });
        }
        
        function loadPerformanceChart() {
            const formData = new FormData();
            formData.append('action', 'get_team_performance');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.performance_data && Array.isArray(data.performance_data) && data.performance_data.length > 0) {
                    renderPerformanceChart(data.performance_data);
                } else {
                    renderEmptyChart();
                }
            })
            .catch(error => {
                console.error('Error loading performance data:', error);
                renderEmptyChart();
            });
        }

        function renderPerformanceChart(performanceData) {
            const ctx = document.getElementById('performanceChart');
            if (!ctx) return;
            
            try {
                // Format dates
                const labels = performanceData.map(item => {
                    if (!item.date) return '';
                    try {
                        const date = new Date(item.date);
                        return isNaN(date.getTime()) ? item.date : date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
                    } catch (e) {
                        return item.date;
                    }
                });
                
                const confirmationsData = performanceData.map(item => parseInt(item.total_confirmations) || 0);
                const successfulData = performanceData.map(item => parseInt(item.successful_confirmations) || 0);
                
                if (performanceChart) {
                    performanceChart.destroy();
                }
                
                performanceChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Confirmations réussies',
                                data: successfulData,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 2,
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Confirmations totales',
                                data: confirmationsData,
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                borderWidth: 2,
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
                                    padding: 20,
                                    usePointStyle: true,
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(30, 41, 59, 0.9)',
                                titleColor: '#f1f5f9',
                                bodyColor: '#f1f5f9',
                                padding: 12,
                                cornerRadius: 8
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#64748b',
                                    maxRotation: 0
                                }
                            },
                            y: {
                                beginAtZero: true,
                                min: 0,
                                grid: {
                                    color: 'rgba(100, 116, 139, 0.1)'
                                },
                                ticks: {
                                    color: '#64748b',
                                    precision: 0,
                                    stepSize: 1
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            } catch (error) {
                console.error('Error rendering chart:', error);
                renderEmptyChart();
            }
        }

        function renderEmptyChart() {
            const ctx = document.getElementById('performanceChart');
            if (!ctx) return;
            
            try {
                // Destroy existing chart
                if (performanceChart) {
                    performanceChart.destroy();
                    performanceChart = null;
                }
                
                // Clear the canvas
                ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);
                
                // Show a simple "no data" message on the canvas itself
                const context = ctx.getContext('2d');
                const width = ctx.width;
                const height = ctx.height;
                
                // Clear and draw message
                context.clearRect(0, 0, width, height);
                context.fillStyle = '#64748b';
                context.font = '14px Arial';
                context.textAlign = 'center';
                context.textBaseline = 'middle';
                context.fillText('Aucune donnée disponible', width / 2, height / 2);
                
            } catch (error) {
                console.error('Error in renderEmptyChart:', error);
                // Just clear the canvas if there's an error
                if (ctx) {
                    ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);
                }
            }
        }
                
        function getOrderStatusBadge(status) {
            const badges = {
                'confirmed': ['bg-success', 'Confirmé'],
                'pending': ['bg-warning', 'En attente'],
                'cancelled': ['bg-danger', 'Annulé'],
                'processing': ['bg-info', 'En traitement']
            };
            
            return badges[status] || ['bg-secondary', status || 'N/A'];
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount) + ' MAD';
        }
        
        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                        ${escapeHtml(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            // Add to container
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            container.appendChild(toast);
            document.body.appendChild(container);
            
            // Show toast
            const bsToast = new bootstrap.Toast(toast, {
                autohide: true,
                delay: 3000
            });
            bsToast.show();
            
            // Remove after hiding
            toast.addEventListener('hidden.bs.toast', function() {
                container.remove();
            });
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPerformanceChart();
            loadRecentActivity();
            
            // Auto-refresh every 2 minutes
            setInterval(loadAgents, 120000);
            
            // Refresh chart every 5 minutes
            setInterval(loadPerformanceChart, 300000);
            
            // Refresh activity every minute
            setInterval(loadRecentActivity, 60000);
        });
        
        // Add search debounce
        let searchTimeout;
        document.getElementById('search-input').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterAgents, 300);
        });
    </script>
</body>
</html>