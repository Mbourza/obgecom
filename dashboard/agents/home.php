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

$db = DB::getInstance();
$agent_email = $_SESSION['user']['username'];
$agent_id = getAgentByEmail($db, $agent_email);

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../../login.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_agent_orders':
            echo getAgentOrders($db, $agent_id);
            exit;
        case 'get_agent_stats':
            echo getAgentStats($db, $agent_id);
            exit;
    }
}

function getAgentByEmail($db, $email) {
    $agent = $db->getIOrN("agents", "email", $email);
    return ($agent && isset($agent->id)) ? $agent->id : null;
}

// Get ONLY orders assigned to this agent
function getAgentOrders($db, $agent_id) {
    try {
        $filters = ["aoa.agent_id = ?"];
        $params = [$agent_id];
        
        // Shipping status filter
        if (!empty($_POST['shipping_status']) && $_POST['shipping_status'] !== 'all') {
            $filters[] = "o.shipping_status = ?";
            $params[] = $_POST['shipping_status'];
        }
        
        // Store filter
        if (!empty($_POST['store_id']) && $_POST['store_id'] !== 'all') {
            $filters[] = "o.store_id = ?";
            $params[] = $_POST['store_id'];
        }
        
        // Shipping company filter
        if (!empty($_POST['shipping_company']) && $_POST['shipping_company'] !== 'all') {
            $filters[] = "o.shipping_company = ?";
            $params[] = $_POST['shipping_company'];
        }
        
        // Confirmation status filter
        if (!empty($_POST['confirmation_status'])) {
            $filters[] = "o.status = ?";
            $params[]  = $_POST['confirmation_status'];
        }
        
        // Date range filter
        if (!empty($_POST['date_range'])) {
            switch ($_POST['date_range']) {
                case 'today':
                    $filters[] = "DATE(aoa.assigned_at) = CURDATE()";
                    break;
                case 'yesterday':
                    $filters[] = "DATE(aoa.assigned_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'week':
                    $filters[] = "aoa.assigned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $filters[] = "aoa.assigned_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                    break;
                case 'custom':
                    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
                        $filters[] = "DATE(aoa.assigned_at) BETWEEN ? AND ?";
                        array_push($params, $_POST['start_date'], $_POST['end_date']);
                    }
                    break;
            }
        }
        
        // Search filter
        if (!empty($_POST['search'])) {

            $filters[] = "(o.tracking_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ? OR o.customer_ville LIKE ?)";
            $search = '%' . $_POST['search'] . '%';
            array_push($params, $search, $search, $search, $search, $search);
        }        
        
        $where_clause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";
        
        $query = "SELECT 
            o.*,
            aoa.id as assignment_id,
            aoa.assigned_at,
            aoa.status as assignment_status,
            aoa.priority_score,
            aoa.notes,
            a.name as agent_name,
            a.email as agent_email,
            a.phone as agent_phone,
            a.confirmation_rate,
            a.score as agent_score,
            s.storeName as store_name,
            COUNT(oi.id) as item_count,
            GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.total_price) as items_total,
            ac.status as confirmation_status,
            ac.handled_at as confirmation_handled_at
            FROM orders o
            INNER JOIN agent_order_assignments aoa ON o.id = aoa.order_id
            INNER JOIN agents a ON aoa.agent_id = a.id
            LEFT JOIN stores s ON o.store_id = s.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN agent_confirmations ac ON o.id = ac.order_id AND ac.agent_id = a.id
            {$where_clause}
            GROUP BY o.id, aoa.id, a.id, s.id
            ORDER BY 
            CASE WHEN aoa.status = 'pending' THEN 0 ELSE 1 END,
            aoa.priority_score DESC,
            aoa.assigned_at ASC
            LIMIT 50";
        
        $orders = $db->getThisQuery($query, $params);
        
        // Get distinct shipping statuses
        $shipping_statuses = $db->getThisQuery("
            SELECT DISTINCT o.shipping_status 
            FROM orders o 
            INNER JOIN agent_order_assignments aoa ON o.id = aoa.order_id
            WHERE o.shipping_status IS NOT NULL 
            AND o.shipping_status != '' 
            AND aoa.agent_id = ?
            ORDER BY o.shipping_status ASC
        ", [$agent_id]);
        
        // Get distinct stores for this agent - FIXED THIS PART
        $stores = $db->getThisQuery("
            SELECT DISTINCT s.id, s.storeName 
            FROM stores s
            INNER JOIN orders o ON s.id = o.store_id
            INNER JOIN agent_order_assignments aoa ON o.id = aoa.order_id
            WHERE aoa.agent_id = ?
            AND s.storeName IS NOT NULL 
            AND s.storeName != ''
            ORDER BY s.storeName ASC
        ", [$agent_id]);
        
        return json_encode([
            'success' => true,
            'orders' => $orders,
            'shipping_statuses' => $shipping_statuses,
            'stores' => $stores, // Now properly populated
            'stats' => [
                'total' => count($orders),
                'pending' => count(array_filter($orders, fn($o) => $o['assignment_status'] === 'pending')),
                'confirmed' => count(array_filter($orders, fn($o) => !empty($o['confirmed_by_agent']))),
                'shipped' => count(array_filter($orders, fn($o) => !empty($o['shipping_status']) && !empty($o['tracking_number']))),
                'cancelled' => count(array_filter($orders, fn($o) => stripos($o['shipping_status'] ?? '', 'cancel') !== false || stripos($o['shipping_status'] ?? '', 'annul') !== false))
            ]
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

// Get agent statistics
function getAgentStats($db, $agent_id) {
    try {
        // Map shipping_status from French to normalized values
        $status_map = [
            'non soumis'             => 'not_submitted',
            'nouveau colis'          => 'pending',
            'attente de ramassage'   => 'pickup_pending',
            'ramassé'                => 'collected',
            'en transit'             => 'in_transit',
            'en cours de livraison'  => 'out_for_delivery',
            'livré'                  => 'delivered',
            'échec de livraison'     => 'failed_delivery',
            'retourné'               => 'returned',
            'annulé'                 => 'cancelled'
        ];

        // Get all orders confirmed by the agent
        $stats_query = "
            SELECT 
                o.id,
                o.status,
                o.shipping_status,
                o.confirmed_by_agent,
                o.tracking_number,
                o.handled_at,
                o.updated_at,
                ac.amount
            FROM orders o
            LEFT JOIN agent_confirmations ac ON o.id = ac.order_id AND ac.agent_id = ?
            WHERE o.confirmed_by_agent = ?
        ";

        $rows = $db->getThisQuery($stats_query, [$agent_id, $agent_id]);

        // Initialize counters
        $stats = [
            'total_handled'          => 0,
            'in_progress'            => 0,
            'shipped'                => 0,
            'delivered'              => 0,
            'cancelled'              => 0,
            'agent_cancelled'        => 0,
            'today_confirmed'        => 0,
            'today_shipped'          => 0,
            'today_cancelled'        => 0,
            'today_agent_cancelled'  => 0,
            'total_amount_handled'   => 0
        ];

        $today = date('Y-m-d');

        foreach ($rows as $row) {
            $stats['total_handled']++;

            // Normalize shipping status
            $normalized_shipping = $status_map[strtolower($row['shipping_status'])] ?? null;

            // Count shipped orders: confirmed by this agent, has shipping_status (not empty), and has tracking_number
            if ($row['confirmed_by_agent'] == $agent_id && 
                !empty($row['shipping_status']) && 
                !empty($row['tracking_number'])) {
                $stats['shipped']++;
            }

            // Count in_progress orders
            if (in_array($normalized_shipping, ['pending', 'pickup_pending', 'collected', 'in_transit', 'out_for_delivery'])) {
                $stats['in_progress']++;
            }

            // Count delivered orders
            if ($normalized_shipping === 'delivered') {
                $stats['delivered']++;
            }

            // Count cancelled orders by shipping company (when shipping company cancelled due to unreachable client)
            if ($normalized_shipping === 'cancelled' || 
                $row['shipping_status'] === 'cancelled' || 
                $row['shipping_status'] === 'annulé') {
                $stats['cancelled']++;
            }

            // Count cancelled orders by agent (when order status is cancelled)
            if ($row['status'] === 'cancelled') {
                $stats['agent_cancelled']++;
            }

            // Today confirmed: status = confirmed and handled_at = today
            if ($row['status'] === 'confirmed' && 
                !empty($row['handled_at']) && 
                date('Y-m-d', strtotime($row['handled_at'])) === $today) {
                $stats['today_confirmed']++;
            }

            // Today shipped: same logic as shipped but only for today
            if ($row['confirmed_by_agent'] == $agent_id && 
                !empty($row['shipping_status']) && 
                !empty($row['tracking_number']) &&
                !empty($row['updated_at']) &&
                date('Y-m-d', strtotime($row['updated_at'])) === $today) {
                $stats['today_shipped']++;
            }

            // Today cancelled by shipping: shipping_status is cancelled and updated_at = today
            if (($normalized_shipping === 'cancelled' || 
                 $row['shipping_status'] === 'cancelled' || 
                 $row['shipping_status'] === 'annulé') &&
                !empty($row['updated_at']) &&
                date('Y-m-d', strtotime($row['updated_at'])) === $today) {
                $stats['today_cancelled']++;
            }

            // Today cancelled by agent: order status is cancelled and updated_at = today
            if ($row['status'] === 'cancelled' &&
                !empty($row['updated_at']) &&
                date('Y-m-d', strtotime($row['updated_at'])) === $today) {
                $stats['today_agent_cancelled']++;
            }

            // Add to total amount if amount exists
            if (!empty($row['amount'])) {
                $stats['total_amount_handled'] += (float)$row['amount'];
            }
        }

        return json_encode([
            'success' => true,
            'stats'   => $stats
        ]);

    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
        ]);
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

// Get initial data
$initial_orders = json_decode(getAgentOrders($db, $agent_id), true);
$initial_stats = json_decode(getAgentStats($db, $agent_id), true); 

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tableau de bord Agent - Gestion des commandes">
    <title>Tableau de bord Agent | <?php echo htmlspecialchars($agent['name']); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/rich.css" />

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-soft: 0 8px 32px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 16px 64px rgba(0, 0, 0, 0.15);
            --border-radius: 16px;
            --filter-bg: linear-gradient(145deg, #f8f9ff 0%, #e8f2ff 100%);
        }

        body {
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Enhanced Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin: 32px 0;
            padding: 0 16px;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--border-radius);
            padding: 32px 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-soft);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-hover);
        }

        .stat-card.primary::before {
            background: var(--primary-gradient);
        }

        .stat-card.success::before {
            background: var(--success-gradient);
        }

        .stat-card.warning::before {
            background: var(--warning-gradient);
        }

        .stat-card.danger::before {
            background: var(--danger-gradient);
        }

        .stat-card.info::before {
            background: var(--info-gradient);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(45deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card p {
            color: #64748b;
            font-weight: 500;
            margin: 0;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Enhanced Filters Container */
        .filters-container {
            background: var(--filter-bg);
            border-radius: 20px;
            padding: 32px;
            margin: 24px 0;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .filters-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-gradient);
        }

        .filter-row {
            margin-bottom: 24px;
            display: flex;
            gap: 10px;
            align-items: end;
            flex-wrap: wrap;
            width: 100%;
        }

        .filter-row:last-child {
            margin-bottom: 0;
        }

        /* Enhanced Search Box */
        .search-box {
            flex: 2;
            min-width: 300px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 16px 24px 16px 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 2px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1), inset 0 2px 8px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
        }

        .search-box::before {
            font-family: 'bootstrap-icons';
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 16px;
            z-index: 1;
        }

        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-gradient);
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
        }

        /* Enhanced Filter Groups */
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0px;
            min-width: 30%;
            flex-direction: row;
        }

        .filter-group label

        {
            margin-right: 0px;
            font-weight: 500;
            font-size: 12px !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: .4em;
            text-align: center;
            width: 60%;
            color: #fff;
        }

        .filter-select {
            padding: 12px 16px;
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            transition: all 0.3s ease;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .filter-select:hover {
            border-color: #667eea;
            background: white;
        }

        /* Enhanced Buttons */
        .btn {
            border-radius: 2px;
            padding: 12px 20px;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transition: all 0.4s ease;
            transform: translate(-50%, -50%);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-success {
            background: var(--success-gradient);
            border: none;
            box-shadow: 0 4px 16px rgba(79, 172, 254, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(79, 172, 254, 0.4);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }

        /* Custom Date Filter Enhancement */
        .custom-date-filter {
            display: none;
            background: rgba(255, 255, 255, 0.7);
            padding: 20px;
            border-radius: 12px;
            margin-top: 16px;
            border: 1px solid rgba(102, 126, 234, 0.2);
            gap: 20px;
        }

        .custom-date-filter .filter-group {
            flex: 1;
        }

        /* Enhanced Section Header */
        .section-header {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--dark-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 16px;
        }

        /* Enhanced Table Styling */
        .orders-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 32px;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .orders-table {
            width: 100%;
            margin-top: 24px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
        }

        .orders-table thead th {
            background: var(--primary-gradient);
            color: white;
            padding: 16px 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 12px;
            border: none;
        }

        .orders-table tbody tr {
            background: white;
            transition: all 0.3s ease;
            border-bottom: 1px solid #f1f5f9;
        }

        .orders-table tbody tr:hover {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            transform: scale(1.01);
        }

        /* Enhanced WhatsApp Button */
        .whatsapp-btn {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 11px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(37, 211, 102, 0.3);
        }

        .whatsapp-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 211, 102, 0.4);
            color: white;
        }

        /* Enhanced Status Badges */
        .status-badge, .confirmation-status, .shipping-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .confirmation-status.confirmed {
            background: linear-gradient(135deg, #d4edda 0%, #a3d977 100%);
            color: #155724;
        }

        .confirmation-status.pending {
            background: linear-gradient(135deg, #fff3cd 0%, #ffd60a 100%);
            color: #856404;
        }

        .confirmation-status.not-confirmed {
            background: linear-gradient(135deg, #f8d7da 0%, #e74c3c 100%);
            color: #721c24;
        }

        /* Enhanced Action Buttons */
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .btn-confirm {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 2px 8px rgba(79, 172, 254, 0.3);
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(79, 172, 254, 0.4);
        }

        .btn-ship {
            background: var(--warning-gradient);
            color: white;
            box-shadow: 0 2px 8px rgba(250, 112, 154, 0.3);
        }

        .btn-ship:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(250, 112, 154, 0.4);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
                gap: 16px;
            }

            .search-box {
                min-width: auto;
            }

            .filters-container {
                padding: 20px;
            }

            .orders-section {
                padding: 20px;
            }
        }

        /* Loading Animation */
        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
            font-weight: 600;
        }

        .loading i {
            animation: spin 2s linear infinite;
            font-size: 1.5rem;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* No Orders State */
        .no-orders {
            padding: 60px 20px;
            color: #94a3b8;
        }

        .no-orders i {
            display: block;
            margin-bottom: 16px;
        }
    </style>

</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-speedometer2"></i> Agent Dashboard
            </a>
            
            <div class="navbar-nav ms-auto d-flex flex-row align-items-center">
                <span class="navbar-text me-3">
                    Bonjour, <strong><?php echo htmlspecialchars($agent['name']); ?></strong>
                </span>
                <a href="agent_profile.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-person"></i> Profil
                </a>
                <a href="?logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Welcome Section -->
        <style>
            .welcome-section {
                display: flex;
                justify-content: space-between; /* Text left, button right */
                align-items: center;
                background: #fff;
                padding: 20px 25px;
                border-radius: 10px;
                box-shadow: 0px 3px 8px rgba(0, 0, 0, 0.1);
            }

            .we-text h1 {
                margin: 0;
                font-size: 22px;
                color: #333;
                font-weight: 600;
            }

            .we-text p {
                margin: 5px 0 0;
                color: #666;
            }

            #createCommande-btn {
                display: flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, #28a745, #218838);
                color: white;
                border: none;
                border-radius: 8px;
                padding: 10px 16px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease-in-out;
            }

            #createCommande-btn svg {
                flex-shrink: 0;
            }

            #createCommande-btn:hover {
                background: linear-gradient(135deg, #218838, #1e7e34);
                transform: translateY(-1px);
                box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3);
            }
        </style>

        <div class="welcome-section">
            <div class="we-text">
                <h1>Tableau de bord Agent</h1>
                <p class="mb-0">Gérez vos commandes et suivez vos performances quotidiennes.</p>
            </div>

            <button id="createCommande-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 4a.5.5 0 0 1 .5.5V7.5H11.5a.5.5 0 0 1 0 1H8.5v3a.5.5 0 0 1-1 0v-3H4.5a.5.5 0 0 1 0-1H7.5V4.5A.5.5 0 0 1 8 4z"/>
                </svg>
                Créer une commande
            </button>
        </div>

        
        <!-- Statistics -->
        <style>
            .stats-container {
                max-width: 1200px;
                margin: 0 auto;
            }

            .stats-section {
                margin-bottom: 16px;
            }

            .section-title {
                color: #333;
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 8px;
                padding-left: 4px;
            }

            .stats-grid {
                display: grid;
                gap: 8px;
                margin-bottom: 16px;
            }

            .main-stats {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            }

            .today-stats {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }

            .stat-card {
                background: white;
                border-radius: 8px;
                padding: 12px 16px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                border-left: 4px solid;
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }

            .stat-card::before {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                width: 40px;
                height: 40px;
                opacity: 0.1;
                border-radius: 50%;
                transform: translate(15px, -15px);
            }

            .stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }

            .stat-card h3 {
                font-size: 20px;
                font-weight: 700;
                margin: 0 0 4px 0;
                line-height: 1;
            }

            .stat-card p {
                font-size: 12px;
                font-weight: 500;
                margin: 0;
                opacity: 0.8;
                line-height: 1.2;
            }

            /* Main Stats Colors */
            .stat-card.primary {
                border-left-color: #3b82f6;
            }
            .stat-card.primary h3 {
                color: #3b82f6;
            }
            .stat-card.primary::before {
                background: #3b82f6;
            }

            .stat-card.warning {
                border-left-color: #f59e0b;
            }
            .stat-card.warning h3 {
                color: #f59e0b;
            }
            .stat-card.warning::before {
                background: #f59e0b;
            }

            .stat-card.success {
                border-left-color: #10b981;
            }
            .stat-card.success h3 {
                color: #10b981;
            }
            .stat-card.success::before {
                background: #10b981;
            }

            .stat-card.danger {
                border-left-color: #ef4444;
            }
            .stat-card.danger h3 {
                color: #ef4444;
            }
            .stat-card.danger::before {
                background: #ef4444;
            }

            .stat-card.info {
                border-left-color: #8b5cf6;
            }
            .stat-card.info h3 {
                color: #8b5cf6;
            }
            .stat-card.info::before {
                background: #8b5cf6;
            }

            /* Compact today cards */
            .today-stats .stat-card {
                padding: 8px 12px;
            }

            .today-stats .stat-card h3 {
                font-size: 18px;
            }

            .today-stats .stat-card p {
                font-size: 11px;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .main-stats {
                    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                }
                
                .today-stats {
                    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                }
                
                .stat-card {
                    padding: 10px 12px;
                }
                
                .stat-card h3 {
                    font-size: 18px;
                }
                
                .today-stats .stat-card h3 {
                    font-size: 16px;
                }
            }
        </style>

        <div class="stats-container">
            <!-- Main Statistics -->
            <div class="stats-section">
                <h4 class="section-title">Statistiques Générales</h4>
                <div class="stats-grid main-stats">
                    <div class="stat-card primary">
                        <h3 id="stat-total"><?php echo $initial_stats['success'] ? $initial_stats['stats']['total_handled'] : 0; ?></h3>
                        <p>Total traitées</p>
                    </div>
                    <div class="stat-card success">
                        <h3 id="stat-shipped"><?php echo $initial_stats['success'] ? $initial_stats['stats']['shipped'] : 0; ?></h3>
                        <p>Expédiées</p>
                    </div>
                    <div class="stat-card info">
                        <h3 id="stat-delivered"><?php echo $initial_stats['success'] ? $initial_stats['stats']['delivered'] : 0; ?></h3>
                        <p>Livrées</p>
                    </div>
                    <div class="stat-card danger">
                        <h3 id="stat-cancelled"><?php echo $initial_stats['success'] ? $initial_stats['stats']['cancelled'] : 0; ?></h3>
                        <p>Annulées (Livraison)</p>
                    </div>
                    <div class="stat-card danger">
                        <h3 id="stat-agent-cancelled"><?php echo $initial_stats['success'] ? $initial_stats['stats']['agent_cancelled'] : 0; ?></h3>
                        <p>Annulées (Agent)</p>
                    </div>
                </div>
            </div>

            <!-- Today Statistics -->
            <div class="stats-section">
                <h4 class="section-title">Statistiques d'Aujourd'hui</h4>
                <div class="stats-grid today-stats">
                    <div class="stat-card info">
                        <h3 id="stat-today-confirmed"><?php echo $initial_stats['success'] ? $initial_stats['stats']['today_confirmed'] : 0; ?></h3>
                        <p>Confirmées</p>
                    </div>
                    <div class="stat-card success">
                        <h3 id="stat-today-shipped"><?php echo $initial_stats['success'] ? $initial_stats['stats']['today_shipped'] : 0; ?></h3>
                        <p>Expédiées</p>
                    </div>
                    <div class="stat-card danger">
                        <h3 id="stat-today-cancelled"><?php echo $initial_stats['success'] ? $initial_stats['stats']['today_cancelled'] : 0; ?></h3>
                        <p>Annulées</p>
                    </div>
                    <div class="stat-card danger">
                        <h3 id="stat-today-agent-cancelled"><?php echo $initial_stats['success'] ? $initial_stats['stats']['today_agent_cancelled'] : 0; ?></h3>
                        <p>Annulées (Agent)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Section -->
        <div class="orders-section">
            <div class="section-header">
                <h2 class="section-title">Commandes disponibles</h2>
                <div class="filters-container">
                    <div class="filter-row">
                        <div class="search-box">
                            <input type="text" id="search-input" placeholder="Rechercher par numéro, nom, email ou téléphone...">
                            <button class="search-btn" onclick="loadOrders()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>

                        <button id="bulk-confirm-btn" class="btn btn-success btn-sm" onclick="bulkConfirmOrders()" disabled>
                            <i class="bi bi-check-circle"></i> Confirmer sélection
                        </button>

                        <button id="bulk-expedite-btn" class="btn btn-warning btn-sm" onclick="bulkExpediteOrders()" disabled>
                            <i class="bi bi-truck"></i> Expédier sélection
                        </button>

                        <button class="btn btn-primary btn-sm" onclick="loadOrders()">
                            <i class="bi bi-arrow-clockwise"></i> Actualiser
                        </button>
                    </div>

                    <div class="filter-row">

                        <div class="filter-group">
                            <label for="confirmation-filter">Confirmation</label>
                            <select id="confirmation-filter" class="filter-select" onchange="loadOrders()">
                                <option value="all">Tous</option>
                                <?php 
                                $confirmation_options = [
                                    'confirmed' => 'Confirmée',
                                    'no-answer' => 'Pas de réponse',
                                    'busy' => 'Occupé',
                                    'cancelled' => 'Annulée',
                                    'double-order' => 'Double commande',
                                    'unreachable' => 'Injoignable'
                                ];
                                foreach ($confirmation_options as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                                            
                        <?php 
                        $status_map = [
                            'not_submitted'   => 'non soumis',
                            'pending'         => 'nouveau colis',
                            'pickup_pending'  => 'attente de ramassage',
                            'collected'       => 'ramassé',
                            'in_transit'      => 'en transit',
                            'out_for_delivery'=> 'en cours de livraison',
                            'delivered'       => 'livré',
                            'failed_delivery' => 'échec de livraison',
                            'returned'        => 'retourné',
                            'cancelled'       => 'annulé'
                        ]; ?>

                        <div class="filter-group">
                            <label for="shipping-status-filter">Statut livraison</label>
                            <select id="shipping-status-filter" class="filter-select" onchange="loadOrders()">
                                <option value="all">Tous</option>
                                <?php if ($initial_orders['success'] && !empty($initial_orders['shipping_statuses'])): ?>
                                    <?php foreach ($initial_orders['shipping_statuses'] as $status): 
                                        $raw_status = strtolower(trim($status['shipping_status']));
                                        // Map the raw status to the friendly label, or fallback to raw if not found
                                        $friendly_label = $status_map[$raw_status] ?? $status['shipping_status'];
                                    ?>
                                        <option value="<?php echo htmlspecialchars($status['shipping_status']); ?>">
                                            <?php echo htmlspecialchars($friendly_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                                            
                        <div class="filter-group">
                            <label for="store-filter">Boutique</label>
                            <select id="store-filter" class="filter-select" onchange="loadOrders()">
                                <option value="all">Toutes</option>
                                <?php if ($initial_orders['success'] && !empty($initial_orders['stores'])): ?>
                                    <?php foreach ($initial_orders['stores'] as $store): ?>
                                        <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['storeName']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                                            
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="date-filter">Période</label>
                            <select id="date-filter" class="filter-select" onchange="toggleCustomDateFilter()">
                                <option value="week" selected>Cette semaine</option>
                                <option value="today">Aujourd'hui</option>
                                <option value="yesterday">Hier</option>
                                <option value="month">Ce mois</option>
                                <option value="custom">Personnalisée</option>
                            </select>
                        </div>
                        
                        <div id="custom-date-filter" class="custom-date-filter">
                            <div class="filter-group">
                                <label for="start-date">De:</label>
                                <input type="date" id="start-date" class="filter-select" onchange="loadOrders()">
                            </div>
                            <div class="filter-group">
                                <label for="end-date">À:</label>
                                <input type="date" id="end-date" class="filter-select" onchange="loadOrders()">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="loading" class="loading" style="display: none;">
                <i class="bi bi-hourglass-split"></i> Chargement des commandes...
            </div>

            <style>
                .table-responsive {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                    overflow: hidden;
                }

                .orders-table {
                    width: 100%;
                    border-collapse: collapse;
                    position: relative;
                    z-index: 0;
                }

                .orders-table thead {
                    position: relative;
                    z-index: 1;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }

                .orders-table th {
                    padding: 16px 12px;
                    font-weight: 600;
                    font-size: 13px;
                    text-align: left;
                    border: none;
                }

                .orders-table tbody {
                    position: relative;
                    z-index: 2;
                }

                .orders-table tbody tr {
                    border-bottom: 1px solid #e5e7eb;
                    transition: all 0.2s ease;
                }

                .orders-table tbody tr:hover {
                    background-color: #f9fafb;
                    transform: translateY(-1px);
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }

                .orders-table td {
                    padding: 12px;
                    vertical-align: middle;
                    font-size: 13px;
                    color: #374151;
                }

                /* Column specific styling */
                .checkbox-cell {
                    width: 40px;
                    text-align: center;
                }

                .select-all-checkbox, .order-checkbox {
                    width: 16px;
                    height: 16px;
                    cursor: pointer;
                    accent-color: #667eea;
                }

                .tracking-cell {
                    width: 140px;
                }

                .tracking-info {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }

                .tracking-number {
                    font-weight: 600;
                    font-size: 12px;
                    color: #1f2937;
                }

                .customer-cell {
                    width: 180px;
                }

                .customer-info {
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                }

                .customer-name {
                    font-weight: 600;
                    color: #1f2937;
                    font-size: 13px;
                }

                .customer-email {
                    font-size: 11px;
                    color: #6b7280;
                }

                .phone-cell {
                    width: 60px;
                    text-align: center;
                }

                .whatsapp-container {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 4px;
                    position: relative;
                }

                .whatsapp-btn {
                    background: #25d366;
                    border: none;
                    border-radius: 50%;
                    width: 32px;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-decoration: none;
                    transition: all 0.2s ease;
                    cursor: pointer;
                }

                .whatsapp-btn:hover {
                    background: #128c7e;
                    transform: scale(1.1);
                }

                .whatsapp-btn i {
                    color: white;
                    font-size: 16px;
                }

                .phone-tooltip {
                    position: absolute;
                    top: 100%;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #1f2937;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 10px;
                    white-space: nowrap;
                    opacity: 0;
                    pointer-events: none;
                    transition: opacity 0.2s ease;
                    z-index: 10;
                }

                .whatsapp-container:hover .phone-tooltip {
                    opacity: 1;
                }

                .city-cell {
                    width: 100px;
                    font-size: 11px;
                    color: #374151;
                }

                .products-cell {
                    width: 80px;
                    text-align: center;
                }

                .products-container {
                    position: relative;
                    display: inline-block;
                }

                .product-count {
                    background: #f3f4f6;
                    border: 1px solid #d1d5db;
                    border-radius: 20px;
                    padding: 4px 12px;
                    font-weight: 600;
                    font-size: 12px;
                    color: #374151;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }

                .product-count:hover {
                    background: #e5e7eb;
                    transform: scale(1.05);
                }

                .products-tooltip {
                    position: absolute;
                    bottom: 100%;
                    left: 50%;
                    transform: translateX(-50%);
                    background: #1f2937;
                    color: white;
                    padding: 8px 12px;
                    border-radius: 6px;
                    font-size: 11px;
                    white-space: nowrap;
                    opacity: 0;
                    pointer-events: none;
                    transition: opacity 0.2s ease;
                    z-index: 10;
                    margin-bottom: 5px;
                    max-width: 200px;
                    white-space: normal;
                    text-align: left;
                }

                .products-container:hover .products-tooltip {
                    opacity: 1;
                }

                .amount-cell {
                    width: 100px;
                    text-align: right;
                }

                .amount {
                    font-weight: 700;
                    font-size: 14px;
                    color: #059669;
                }

                .status-cell {
                    width: 140px;
                }

                .confirmation-cell {
                    width: 160px;
                }

                .confirmation-select {
                    width: 100%;
                    padding: 6px 8px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    font-size: 12px;
                    background: white;
                    cursor: pointer;
                    transition: border-color 0.2s ease;
                }

                .confirmation-select:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }

                .store-cell {
                    width: 120px;
                }

                .store-name {
                    font-size: 12px;
                    color: #374151;
                }

                .date-cell {
                    width: 100px;
                    font-size: 12px;
                    color: #6b7280;
                }

                .actions-cell {
                    width: 60px;
                    text-align: center;
                }

                .actions-dropdown {
                    position: relative;
                    display: inline-block;
                }

                .actions-trigger {
                    background: none;
                    border: none;
                    font-size: 18px;
                    font-weight: bold;
                    color: #6b7280;
                    cursor: pointer;
                    padding: 4px 8px;
                    border-radius: 4px;
                    transition: all 0.2s ease;
                }

                .actions-trigger:hover {
                    background: #f3f4f6;
                    color: #374151;
                }

                .actions-menu {
                    position: absolute;
                    top: 100%;
                    right: 0;
                    background: white;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                    padding: 8px 0;
                    min-width: 120px;
                    opacity: 0;
                    visibility: hidden;
                    transform: translateY(-10px);
                    transition: all 0.2s ease;
                    z-index: 100;
                }

                .actions-dropdown:hover .actions-menu {
                    opacity: 1;
                    visibility: visible;
                    transform: translateY(0);
                }

                .actions-menu button {
                    width: 100%;
                    padding: 8px 16px;
                    border: none;
                    background: none;
                    text-align: left;
                    font-size: 12px;
                    color: #374151;
                    cursor: pointer;
                    transition: background-color 0.2s ease;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .actions-menu button:hover {
                    background: #f9fafb;
                }

                .actions-menu button.delete-btn:hover {
                    background: #fee2e2;
                    color: #dc2626;
                }

                /* Status badges */
                .status-badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 12px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .status-badge.new {
                    background: #dbeafe;
                    color: #1e40af;
                }

                .status-badge.pickup_pending {
                    background: #fef3c7;
                    color: #d97706;
                }

                .status-badge.collected {
                    background: #d1fae5;
                    color: #059669;
                }

                .status-badge.in_transit, .status-badge.shipped {
                    background: #e0e7ff;
                    color: #5b21b6;
                }

                .status-badge.arrived_at_agency {
                    background: #fce7f3;
                    color: #be185d;
                }

                .status-badge.out_for_delivery {
                    background: #ecfdf5;
                    color: #047857;
                }

                .status-badge.delivered {
                    background: #dcfce7;
                    color: #166534;
                }

                .status-badge.refused, .status-badge.cancelled {
                    background: #fee2e2;
                    color: #dc2626;
                }

                .status-badge.unreachable {
                    background: #f3f4f6;
                    color: #6b7280;
                }

                .status-badge.rescheduled {
                    background: #fef3c7;
                    color: #d97706;
                }

                .status-badge.returned_to_sender {
                    background: #fde68a;
                    color: #92400e;
                }

                .status-badge.address_error {
                    background: #fed7d7;
                    color: #c53030;
                }

                .status-badge.warehouse_waiting {
                    background: #e2e8f0;
                    color: #4a5568;
                }

                .status-badge.delivery_failed {
                    background: #fed7d7;
                    color: #c53030;
                }

                .status-badge.pending {
                    background: #fef3c7;
                    color: #d97706;
                }

                .status-badge.processing {
                    background: #dbeafe;
                    color: #1e40af;
                }

                .status-badge.not_submitted {
                    background: #f1f5f9;
                    color: #64748b;
                }

                /* Shipping button for confirmed orders */
                .shipping-btn-container {
                    margin-top: 8px;
                }

                .submit-shipping-btn {
                    background: #3b82f6;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    padding: 4px 8px;
                    font-size: 11px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    display: flex;
                    align-items: center;
                    gap: 4px;
                }

                .submit-shipping-btn:hover {
                    background: #2563eb;
                    transform: translateY(-1px);
                }

                /* No data state */
                .no-data {
                    text-align: center !important;
                    padding: 40px 20px;
                    color: #6b7280;
                    font-style: italic;
                }

                /* Responsive design */
                @media (max-width: 1200px) {
                    .orders-table th,
                    .orders-table td {
                        padding: 8px 6px;
                        font-size: 12px;
                    }
                    
                    .customer-cell {
                        width: 150px;
                    }
                    
                    .tracking-cell {
                        width: 120px;
                    }
                }

                @media (max-width: 768px) {
                    .table-responsive {
                        overflow-x: auto;
                    }
                    
                    .orders-table {
                        min-width: 800px;
                    }
                }

                .edit-btn-compact {
                    background: none;
                    border: none;
                    color: #6c757d;
                    font-size: 14px;
                    cursor: pointer;
                    padding: 4px;
                    border-radius: 3px;
                    transition: all 0.2s;
                }

                .edit-btn-compact:hover {
                    background-color: #e9ecef;
                    color: #007bff;
                }

                .actions-cell {
                    color: #fff !important;
                    padding: 16px 12px !important;
                    background: var(--primary-gradient);
                    font-size: 12px;
                    font-weight: 600;
                }
            </style>

            <div class="table-responsive">
                <table class="orders-table" id="orders-table" style="position: relative; z-index: 0">
                    <thead style="position: relative; z-index: 1;">
                        <tr>
                            <th class="checkbox-cell">
                                <input type="checkbox" class="select-all-checkbox" id="select-all-checkbox" title="Sélectionner tout">
                            </th>
                            <th style="display: none;">N° Commande</th>
                            <th class="tracking-cell">Code d'envoi</th>
                            <th class="customer-cell">Client</th>
                            <th class="phone-cell">Tel</th>
                            <th class="city-cell">Ville</th>
                            <th class="products-cell">Articles</th>
                            <th class="amount-cell">Montant</th>
                            <th class="status-cell">Statut</th>
                            <th class="confirmation-cell">Confirmation</th>
                            <th class="store-cell">Magasin</th>
                            <th class="date-cell">Date</th>
                            <td class="actions-cell">Actions</td>
                        </tr>
                    </thead>
                    <tbody id="orders-tbody" style="position: relative; z-index: 2">
                        <?php if ($initial_orders['success'] && !empty($initial_orders['orders'])): ?>
                            <?php foreach ($initial_orders['orders'] as $order): ?>
                                <tr class="order-row" data-order-id="<?php echo htmlspecialchars($order['id']); ?>" style="cursor: pointer;">
                                    <td class="checkbox-cell" onclick="event.stopPropagation();">
                                        <?php if ($order['assignment_status'] == 'pending'): ?>
                                            <input type="checkbox" class="order-checkbox" 
                                            value="<?php echo htmlspecialchars($order['id']); ?>">
                                        <?php else: ?>
                                            <input type="checkbox" disabled>
                                        <?php endif; ?>
                                    </td>
                                    <td style="display: none;"><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td class="tracking-cell">
                                        <div class="tracking-info">
                                            <?php if (!empty($order['tracking_number'])): ?>
                                                <div class="tracking-number"><?php echo htmlspecialchars($order['tracking_number']); ?></div>
                                                <div class="tracking-status">
                                                    <span class="status-badge <?php echo $order['shipping_status']; ?>">
                                                        <?php 
                                                        $status_labels = [
                                                            'new'                  => 'Nouveau colis',
                                                            'pickup_pending'       => 'En cours de ramassage',
                                                            'collected'            => 'Ramassé',
                                                            'in_transit'           => 'En transit',
                                                            'arrived_at_agency'    => "Arrivé à l'agence",
                                                            'out_for_delivery'     => 'En cours de livraison',
                                                            'delivered'            => 'Livrée',
                                                            'refused'              => 'Refusée',
                                                            'unreachable'          => 'Client injoignable',
                                                            'rescheduled'          => 'Reprogrammée',
                                                            'returned_to_sender'   => "Retour à l'expéditeur",
                                                            'cancelled'            => 'Annulée',
                                                            'address_error'        => "Erreur d'adresse",
                                                            'warehouse_waiting'    => 'En attente au dépôt',
                                                            'delivery_failed'      => 'Livraison échouée',
                                                            'pending'              => 'En attente',
                                                            'processing'           => 'En préparation',
                                                            'shipped'              => 'Expédiée',
                                                            'not_submitted'        => 'non soumis'
                                                        ];
                                                        echo $status_labels[$order['shipping_status']] ?? $order['shipping_status'];
                                                        ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="tracking-number">-</div>
                                                <div class="tracking-status">
                                                    <span class="status-badge <?php echo $order['shipping_status']; ?>">
                                                        <?php 
                                                        $status_labels = [
                                                            'new'                  => 'Nouveau colis',
                                                            'pickup_pending'       => 'En cours de ramassage',
                                                            'collected'            => 'Ramassé',
                                                            'in_transit'           => 'En transit',
                                                            'arrived_at_agency'    => "Arrivé à l'agence",
                                                            'out_for_delivery'     => 'En cours de livraison',
                                                            'delivered'            => 'Livrée',
                                                            'refused'              => 'Refusée',
                                                            'unreachable'          => 'Client injoignable',
                                                            'rescheduled'          => 'Reprogrammée',
                                                            'returned_to_sender'   => "Retour à l'expéditeur",
                                                            'cancelled'            => 'Annulée',
                                                            'address_error'        => "Erreur d'adresse",
                                                            'warehouse_waiting'    => 'En attente au dépôt',
                                                            'delivery_failed'      => 'Livraison échouée',
                                                            'pending'              => 'En attente',
                                                            'processing'           => 'En préparation',
                                                            'shipped'              => 'Expédiée',
                                                            'not_submitted'        => 'non soumis'
                                                        ];
                                                        echo $status_labels[$order['shipping_status']] ?? $order['shipping_status'];
                                                        ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="customer-cell">
                                        <div class="customer-info">
                                            <div class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <div class="customer-email"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                        </div>
                                    </td>
                                    <td class="phone-cell" onclick="event.stopPropagation();">
                                        <?php if (!empty($order['customer_phone'])): ?>
                                            <?php
                                            $rawPhone = preg_replace('/[^0-9]/', '', $order['customer_phone']);
                                            if (strpos($rawPhone, '0') === 0) {
                                                $rawPhone = substr($rawPhone, 1);
                                            }
                                            $waPhone = (strpos($rawPhone, '212') === 0) ? $rawPhone : '212' . $rawPhone;
                                            ?>
                                            <div class="whatsapp-container">
                                                <a href="https://wa.me/<?php echo $waPhone; ?>" 
                                                class="whatsapp-btn" target="_blank" title="Contacter via WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                                <div class="phone-tooltip">
                                                    <?php echo htmlspecialchars($order['customer_phone']); ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #9ca3af; font-size: 11px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="city-cell">
                                        <div style="font-size: 11px; color: #374151;">
                                            <?php echo !empty($order['customer_ville']) ? htmlspecialchars($order['customer_ville']) : '-'; ?>
                                        </div>
                                    </td>
                                    <td class="products-cell">
                                        <div class="products-container">
                                            <div class="product-count"><?php echo $order['item_count']; ?></div>
                                            <div class="products-tooltip">
                                                <?php echo htmlspecialchars($order['products']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="amount-cell">
                                        <div class="amount"><?php echo number_format($order['total_amount'], 2); ?> <?php echo $order['currency']; ?></div>
                                    </td>
                                    <td class="status-cell">
                                        <span class="status-badge <?php echo $order['shipping_status']; ?>">
                                            <?php 
                                            $status_labels = [
                                                'new'                  => 'Nouveau colis',
                                                'pickup_pending'       => 'En cours de ramassage',
                                                'collected'            => 'Ramassé',
                                                'in_transit'           => 'En transit',
                                                'arrived_at_agency'    => "Arrivé à l'agence",
                                                'out_for_delivery'     => 'En cours de livraison',
                                                'delivered'            => 'Livrée',
                                                'refused'              => 'Refusée',
                                                'unreachable'          => 'Client injoignable',
                                                'rescheduled'          => 'Reprogrammée',
                                                'returned_to_sender'   => "Retour à l'expéditeur",
                                                'cancelled'            => 'Annulée',
                                                'address_error'        => "Erreur d'adresse",
                                                'warehouse_waiting'    => 'En attente au dépôt',
                                                'delivery_failed'      => 'Livraison échouée',
                                                'pending'              => 'En attente',
                                                'processing'           => 'En préparation',
                                                'shipped'              => 'Expédiée',
                                                'not_submitted'        => 'non soumis'
                                            ];
                                            echo $status_labels[$order['shipping_status']] ?? $order['shipping_status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td class="confirmation-cell" onclick="event.stopPropagation();">
                                        <select class="confirmation-select" id="confirmation_select_<?php echo $order['id']; ?>"
                                                data-order-id="<?php echo htmlspecialchars($order['id']); ?>"
                                                onchange="confirmOrder(this)">
                                            <?php
                                            $confirmation_options = [
                                                // 'new-order' => 'Nouvelle commande', // Excluded from options
                                                'confirmed' => 'Confirmée',
                                                'no-answer' => 'Pas de réponse',
                                                'busy' => 'Occupé',
                                                'cancelled' => 'Annulée',
                                                'double-order' => 'Double commande',
                                                'unreachable' => 'Injoignable'
                                            ];

                                            $current_status = $order['status'] ?? 'new-order';

                                            // Add "Nouvelle commande" visually ONLY if it's current status
                                            if ($current_status === 'new-order') {
                                                echo '<option value="new-order" selected disabled style="background-color: #cce5ff; color: #004085;">
                                                        Nouvelle commande
                                                    </option>';
                                            }

                                            foreach ($confirmation_options as $value => $label): 
                                                $selected = ($current_status === $value) ? 'selected' : '';
                                                $style = "background-color: ";
                                                switch($value) {
                                                    case 'confirmed': $style .= "#d4edda; color: #155724;"; break;
                                                    case 'no-answer': $style .= "#fff3cd; color: #856404;"; break;
                                                    case 'busy': $style .= "#ffe8cc; color: #804d00;"; break;
                                                    case 'cancelled': $style .= "#f8d7da; color: #721c24;"; break;
                                                    case 'double-order': $style .= "#e2e3e5; color: #383d41;"; break;
                                                    case 'unreachable': $style .= "#d1ecf1; color: #0c5460;"; break;
                                                    default: $style .= "#f8f9fa; color: #212529;";
                                                }
                                            ?>
                                            <option value="<?php echo htmlspecialchars($value); ?>" 
                                                    <?php echo $selected; ?> 
                                                    style="<?php echo $style; ?>">
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <?php if ($current_status == 'confirmed' && empty($order['tracking_number'])): ?>
                                            <div class="shipping-btn-container mt-2">
                                                <button class="btn btn-sm btn-primary submit-shipping-btn" 
                                                        data-order-id="<?php echo htmlspecialchars($order['id']); ?>" 
                                                        onclick="sendToShipping(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-truck"></i> Expédier
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="store-cell">
                                        <div class="store-name">
                                            <?php echo !empty($order['store_name']) ? htmlspecialchars($order['store_name']) : 'N/A'; ?>
                                        </div>
                                    </td>
                                    <td class="date-cell">
                                        <div><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></div>
                                        <div style="font-size: 10px; color: #9ca3af;"><?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                                    </td>
                                    <td style="background-color: none !important;">
                                        <button class="edit-btn-compact" onclick="event.stopPropagation(); editOrderData(<?php echo htmlspecialchars($order['id']); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="13" class="no-data">Aucune commande trouvée</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php require_once('./view_orderDetailModal.html'); ?>
    <?php require_once('./orderModal.php'); ?>
    <?php require_once('./editOrderModal.php'); ?>

    <script>

        let selectedOrders = new Set();

        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh every 80 seconds
            setInterval(loadOrders, 800000);
            
            // Search on Enter key
            document.getElementById('search-input').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loadOrders();
                }
            });
            
            // Initialize row click events and bulk actions
            initializeRowClickEvents();
            initializeBulkActions();
        });

        // Function to initialize row click events
        function initializeRowClickEvents() {
            // Remove existing event listeners to prevent duplicates
            document.querySelectorAll('.order-row').forEach(row => {
                const newRow = row.cloneNode(true);
                row.parentNode.replaceChild(newRow, row);
            });
            
            // Add click event to order rows
            document.querySelectorAll('.order-row').forEach(row => {
                row.addEventListener('click', function(e) {
                    // Don't trigger if clicking on interactive elements
                    if (e.target.closest('.checkbox-cell') ||
                        e.target.closest('.confirmation-cell') ||
                        e.target.closest('.phone-cell') ||
                        e.target.classList.contains('order-checkbox') ||
                        e.target.classList.contains('confirmation-select') ||
                        e.target.classList.contains('submit-shipping-btn') ||
                        e.target.classList.contains('whatsapp-btn') ||
                        e.target.closest('.whatsapp-btn') ||
                        e.target.closest('.btn-action') ||
                        e.target.tagName === 'SELECT' ||
                        e.target.tagName === 'BUTTON' ||
                        e.target.tagName === 'INPUT') {
                        return;
                    }
                    
                    const orderId = this.getAttribute('data-order-id');
                    if (orderId) {
                        viewOrder(orderId);
                    }
                });
                
                // Add hover effect
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = '#f8f9fa';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        }

        // Function to initialize bulk actions
        function initializeBulkActions() {
            // Initialize select-all checkbox
            const selectAllCheckbox = document.getElementById('select-all-checkbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    toggleSelectAll(this);
                });
            }
            
            // Initialize individual checkboxes
            document.querySelectorAll('.order-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    handleOrderCheckboxChange(this);
                });
            });
            
            // Update bulk actions visibility on initialization
            updateBulkActionsVisibility();
        }

        // Function to toggle select all checkboxes
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.order-checkbox:not(:disabled)');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                if (checkbox.checked) {
                    selectedOrders.add(parseInt(cb.value));
                } else {
                    selectedOrders.delete(parseInt(cb.value));
                }
            });
            
            updateBulkActionsVisibility();
        }

        // Function to handle individual checkbox changes
        function handleOrderCheckboxChange(checkbox) {
            const orderId = parseInt(checkbox.value);
            
            if (checkbox.checked) {
                selectedOrders.add(orderId);
            } else {
                selectedOrders.delete(orderId);
                const selectAllCheckbox = document.getElementById('select-all-checkbox');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
            }
            
            updateBulkActionsVisibility();
        }

        // Function to update bulk actions visibility
        function updateBulkActionsVisibility() {
            const bulkActions = document.getElementById('bulk-actions');
            const selectedCount = document.querySelector('.selected-count');
            const bulkConfirmBtn = document.getElementById('bulk-confirm-btn');
            
            if (selectedOrders.size > 0) {
                if (bulkActions) {
                    bulkActions.style.display = 'flex';
                }
                if (selectedCount) {
                    selectedCount.textContent = `${selectedOrders.size} commande${selectedOrders.size > 1 ? 's' : ''} sélectionnée${selectedOrders.size > 1 ? 's' : ''}`;
                }
                
                // Enable bulk confirm button when orders are selected
                if (bulkConfirmBtn) {
                    bulkConfirmBtn.disabled = false;
                }
            } else {
                if (bulkActions) {
                    bulkActions.style.display = 'none';
                }
                
                // Disable bulk confirm button when no orders are selected
                if (bulkConfirmBtn) {
                    bulkConfirmBtn.disabled = true;
                }
            }
        }

        function toggleCustomDateFilter() {
            const dateFilter = document.getElementById('date-filter');
            const customDateFilter = document.getElementById('custom-date-filter');
            
            if (dateFilter.value === 'custom') {
                customDateFilter.style.display = 'flex';
            } else {
                customDateFilter.style.display = 'none';
                loadOrders();
            }
        }

        function loadOrders() {
            const loading = document.getElementById('loading');
            loading.style.display = 'block';
                        
            const formData = new FormData();
            formData.append('action', 'get_agent_orders');
                        
            // Add filters
            const search = document.getElementById('search-input').value;
            
            const shippingStatusFilter = document.getElementById('shipping-status-filter').value;
            const storeFilter = document.getElementById('store-filter').value;
            const confirmationFilter = document.getElementById('confirmation-filter').value;
            const dateRange = document.getElementById('date-filter').value;
            const startDate = document.getElementById('start-date')?.value;
            const endDate = document.getElementById('end-date')?.value;
                        
            if (search) formData.append('search', search);
            
            if (shippingStatusFilter && shippingStatusFilter !== 'all') formData.append('shipping_status', shippingStatusFilter);
            if (storeFilter && storeFilter !== 'all') formData.append('store_id', storeFilter);
            if (confirmationFilter && confirmationFilter !== 'all') formData.append('confirmation_status', confirmationFilter);
                        
            if (dateRange) {
                formData.append('date_range', dateRange);
                                
                if (dateRange === 'custom' && startDate && endDate) {
                    formData.append('start_date', startDate);
                    formData.append('end_date', endDate);
                }
            }
                        
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none';
                                
                if (data.success) {
                    updateOrdersTable(data.orders);
                    loadStats(); // Refresh stats
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                showNotification('Erreur lors du chargement', 'error');
                console.error('Error:', error);
            });
        }

        function loadStats() {
            const formData = new FormData();
            formData.append('action', 'get_agent_stats');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStats(data.stats);
                }
            })
            .catch(error => {
                console.error('Error loading stats:', error);
            });
        }

        function bulkConfirmOrders() {
            const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
            const orderIds = Array.from(selectedCheckboxes).map(cb => cb.value);
            
            if (orderIds.length === 0) {
                showNotification('Aucune commande sélectionnée', 'warning');
                return;
            }
            
            if (!confirm(`Voulez-vous vraiment confirmer ${orderIds.length} commande(s)?`)) {
                return;
            }
            
            // Get bulk confirm button to show loading state
            const bulkBtn = document.querySelector('.bulk-confirm-btn');
            if (bulkBtn) {
                bulkBtn.disabled = true;
                bulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirmation en cours...';
            }
            
            showNotification(`Confirmation de ${orderIds.length} commande(s) en cours...`, 'info');
            
            const formData = new FormData();
            formData.append('action', 'update_orders_status_bulk'); // Use same action as admin bulk update
            formData.append('order_ids', JSON.stringify(orderIds));
            formData.append('status', 'confirmed'); // Set status to confirmed
            
            fetch('../controllers/handle_agentConfirmation_api.php', { 
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = data.message || 'Commandes confirmées avec succès';
                    if (data.successful_count !== undefined) {
                        message += ` - ${data.successful_count} confirmation(s) réussie(s)`;
                        if (data.failed_count > 0) {
                            message += `, ${data.failed_count} échec(s)`;
                        }
                    }
                    showNotification(message, 'success');
                    
                    loadOrders(); // Refresh orders table
                    
                    // Clear selections
                    const selectAllCheckbox = document.getElementById('select-all');
                    if (selectAllCheckbox) selectAllCheckbox.checked = false;
                    document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = false);
                    
                    // Update bulk button visibility
                    if (typeof updateBulkConfirmButton === 'function') {
                        updateBulkConfirmButton();
                    }
                } else {
                    showNotification(data.message || 'Erreur lors de la confirmation en masse', 'error');
                }
                
                // Re-enable bulk button
                if (bulkBtn) {
                    bulkBtn.disabled = false;
                    bulkBtn.innerHTML = '<i class="fas fa-check-double"></i> Confirmer sélection';
                }
            })
            .catch(error => {
                showNotification('Erreur lors de la confirmation en masse', 'error');
                console.error('Error:', error);
                
                // Re-enable bulk button on error
                if (bulkBtn) {
                    bulkBtn.disabled = false;
                    bulkBtn.innerHTML = '<i class="fas fa-check-double"></i> Confirmer sélection';
                }
            });
        }

        function bulkExpediteOrders() {
            const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
            
            if (selectedCheckboxes.length === 0) {
                showNotification('Veuillez sélectionner au moins une commande', 'warning');
                return;
            }

            // Get only confirmed orders that don't have tracking numbers yet
            const confirmedOrders = [];
            selectedCheckboxes.forEach(checkbox => {
                const orderId = checkbox.value;
                const orderRow = checkbox.closest('tr');
                const confirmationSelect = orderRow.querySelector('.confirmation-select');
                const trackingCell = orderRow.querySelector('.tracking-number');
                
                // Check if order is confirmed and doesn't have tracking number
                if (confirmationSelect && confirmationSelect.value === 'confirmed') {
                    const hasTracking = trackingCell && trackingCell.textContent.trim() !== '-';
                    if (!hasTracking) {
                        confirmedOrders.push(orderId);
                    }
                }
            });

            if (confirmedOrders.length === 0) {
                showNotification('Aucune commande confirmée sans numéro de suivi sélectionnée', 'warning');
                return;
            }

            if (!confirm(`Êtes-vous sûr de vouloir expédier ${confirmedOrders.length} commande(s) confirmée(s) ?`)) {
                return;
            }

            const bulkExpediteBtn = document.getElementById('bulk-expedite-btn');
            if (bulkExpediteBtn) {
                bulkExpediteBtn.disabled = true;
                bulkExpediteBtn.innerHTML = '<i class="bi bi-spinner bi-spin"></i> Envoi en cours...';
            }

            showNotification(`Envoi de ${confirmedOrders.length} commande(s) au transporteur...`, 'info');

            const formData = new FormData();
            formData.append('action', 'bulk_submit_to_shipping');
            formData.append('order_ids', JSON.stringify(confirmedOrders));

            fetch('../controllers/agent_shipping_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let successMessage = `${data.success_count} commande(s) envoyée(s) avec succès`;
                    if (data.failed_count > 0) {
                        successMessage += `, ${data.failed_count} échec(s)`;
                    }
                    
                    // Add agent info to success message
                    if (data.submitted_by_agent) {
                        successMessage += ` par ${data.submitted_by_agent}`;
                    }
                    
                    showNotification(successMessage, 'success');

                    // Show detailed tracking information
                    if (data.tracking_numbers && data.tracking_numbers.length > 0) {
                        let trackingMessage = 'Numéros de suivi générés:\n';
                        data.tracking_numbers.forEach(trackingInfo => {
                            if (trackingInfo.success && trackingInfo.tracking_number) {
                                trackingMessage += `Commande ${trackingInfo.order_id}: ${trackingInfo.tracking_number}\n`;
                                // Update tracking number in UI for successful shipments
                                updateTrackingNumberInUI(trackingInfo.order_id, trackingInfo.tracking_number);
                            }
                        });
                        
                        if (trackingMessage !== 'Numéros de suivi générés:\n') {
                            showNotification(trackingMessage, 'info');
                        }
                    }

                    // Show errors if any
                    if (data.errors && data.errors.length > 0) {
                        let errorMessage = 'Erreurs rencontrées:\n';
                        data.errors.forEach(error => {
                            errorMessage += `• ${error}\n`;
                        });
                        showNotification(errorMessage, 'warning');
                    }

                    loadOrders(); // Refresh orders table
                } else {
                    showNotification(data.message || 'Erreur lors de l\'envoi au transporteur', 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion avec le serveur', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                // Re-enable the bulk expedite button
                if (bulkExpediteBtn) {
                    bulkExpediteBtn.disabled = true; // Keep disabled until new selection
                    bulkExpediteBtn.innerHTML = '<i class="bi bi-truck"></i> Expédier sélection';
                }
                
                // Clear all selections
                const allCheckboxes = document.querySelectorAll('.order-checkbox:checked');
                allCheckboxes.forEach(checkbox => checkbox.checked = false);
                
                // Uncheck master checkbox
                const selectAllCheckbox = document.getElementById('select-all-checkbox');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
                
                // Update bulk button states
                updateBulkButtonStates();
            });
        }

        // Helper function to update tracking number in UI
        function updateTrackingNumberInUI(orderId, trackingNumber) {
            const orderRow = document.querySelector(`tr[data-order-id="${orderId}"]`);
            if (!orderRow) return;
            
            // Update tracking number in tracking cell
            const trackingNumberDiv = orderRow.querySelector('.tracking-number');
            if (trackingNumberDiv) {
                trackingNumberDiv.textContent = trackingNumber;
            }
            
            // Update shipping status to processing
            const statusBadges = orderRow.querySelectorAll('.status-badge');
            statusBadges.forEach(badge => {
                badge.textContent = 'En préparation';
                badge.className = 'status-badge processing';
            });
            
            // Remove the individual expedite button since order is now shipped
            const shippingBtnContainer = orderRow.querySelector('.shipping-btn-container');
            if (shippingBtnContainer) {
                shippingBtnContainer.innerHTML = `
                    <button class="btn btn-sm btn-success" disabled>
                        <i class="fas fa-check"></i> Expédié
                    </button>
                `;
            }
        }

        // Helper function to manage bulk button states (matches your existing pattern)
        function updateBulkButtonStates() {
            const selectedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
            const bulkConfirmBtn = document.getElementById('bulk-confirm-btn');
            const bulkExpediteBtn = document.getElementById('bulk-expedite-btn');
            
            const hasSelection = selectedCheckboxes.length > 0;
            
            if (bulkConfirmBtn) {
                bulkConfirmBtn.disabled = !hasSelection;
            }
            
            if (bulkExpediteBtn) {
                // Enable only if there are confirmed orders without tracking numbers
                let hasConfirmedOrders = false;
                selectedCheckboxes.forEach(checkbox => {
                    const orderRow = checkbox.closest('tr');
                    const confirmationSelect = orderRow.querySelector('.confirmation-select');
                    const trackingCell = orderRow.querySelector('.tracking-number');
                    
                    if (confirmationSelect && confirmationSelect.value === 'confirmed') {
                        const hasTracking = trackingCell && trackingCell.textContent.trim() !== '-';
                        if (!hasTracking) {
                            hasConfirmedOrders = true;
                        }
                    }
                });
                
                bulkExpediteBtn.disabled = !hasConfirmedOrders;
            }
        }

        // Function to handle master checkbox (select all)
        function handleSelectAll() {
            const selectAllCheckbox = document.getElementById('select-all-checkbox');
            const orderCheckboxes = document.querySelectorAll('.order-checkbox');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    orderCheckboxes.forEach(checkbox => {
                        if (!checkbox.disabled) {
                            checkbox.checked = this.checked;
                        }
                    });
                    updateBulkButtonStates();
                });
            }
        }

        // Function to handle individual checkbox changes
        function handleIndividualCheckboxes() {
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('order-checkbox')) {
                    updateBulkButtonStates();
                    
                    // Update master checkbox state
                    const selectAllCheckbox = document.getElementById('select-all-checkbox');
                    const orderCheckboxes = document.querySelectorAll('.order-checkbox:not([disabled])');
                    const checkedCheckboxes = document.querySelectorAll('.order-checkbox:checked');
                    
                    if (selectAllCheckbox) {
                        if (checkedCheckboxes.length === 0) {
                            selectAllCheckbox.indeterminate = false;
                            selectAllCheckbox.checked = false;
                        } else if (checkedCheckboxes.length === orderCheckboxes.length) {
                            selectAllCheckbox.indeterminate = false;
                            selectAllCheckbox.checked = true;
                        } else {
                            selectAllCheckbox.indeterminate = true;
                            selectAllCheckbox.checked = false;
                        }
                    }
                }
            });
        }

        // Initialize the bulk functionality when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            handleSelectAll();
            handleIndividualCheckboxes();
            updateBulkButtonStates();
        });

        function confirmOrder(selectElement) {
            if (!confirm('Voulez-vous vraiment confirmer cette commande?')) {
                return;
            }
            
            const orderId = selectElement.getAttribute('data-order-id');
            const newStatus = selectElement.value;
            const originalSelectedIndex = selectElement.selectedIndex;
            
            selectElement.disabled = true;

            // Get the button to show loading state (optional)
            const btn = document.querySelector(`button[data-order-id="${orderId}"].confirm-btn`);
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirmation...';
            }
            
            showNotification('Confirmation de la commande en cours...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'update_order_status');
            formData.append('order_id', orderId);
            formData.append('status', newStatus); // Set status to confirmed
            
            fetch('../controllers/handle_agentConfirmation_api.php', { // Use same API endpoint as admin
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Commande confirmée avec succès', 'success');
                    loadOrders(); // Refresh orders table
                } else {
                    showNotification(data.message || 'Erreur lors de la confirmation', 'error');
                    
                    // Re-enable button on error
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check"></i> Confirmer';
                    }
                }
            })
            .catch(error => {
                showNotification('Erreur lors de la confirmation', 'error');
                console.error('Error:', error);
                
                // Re-enable button on error
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Confirmer';
                }
            });
        }

        function sendToShipping(orderId) {
            if (!confirm('Voulez-vous vraiment envoyer cette commande au transport?')) {
                return;
            }
            
            // Get the button to show loading state (optional)
            const btn = document.querySelector(`button[data-order-id="${orderId}"].send-shipping-btn`);
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
            }
            
            showNotification('Envoi de la commande au transporteur...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'submit_to_shipping'); // Changed from 'send_to_shipping' to match admin
            formData.append('order_id', orderId);
            
            fetch('../controllers/agent_shipping_api.php', { // Added proper API endpoint
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Commande envoyée au transporteur avec succès', 'success');
                    
                    // Handle tracking number if provided
                    if (data.shipping_notification && data.shipping_notification.tracking_number) {
                        showNotification(`Numéro de suivi: ${data.shipping_notification.tracking_number}`, 'info');
                        // Update tracking number in UI if function exists
                        if (typeof updateTrackingNumberInUI === 'function') {
                            updateTrackingNumberInUI(orderId, data.shipping_notification.tracking_number);
                        }
                    }
                    
                    loadOrders(); // Refresh orders table
                } else {
                    showNotification(data.message || 'Erreur lors de l\'envoi au transporteur', 'error');
                    
                    // Re-enable button on error
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-truck"></i> Envoyer au transport';
                    }
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion avec le serveur', 'error');
                console.error('Error:', error);
                
                // Re-enable button on error
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-truck"></i> Envoyer au transport';
                }
            });
        }

        function updateOrdersTable(orders) {
            const tbody = document.getElementById('orders-tbody');
            
            if (!orders || orders.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="13" class="no-data">Aucune commande trouvée</td>
                    </tr>
                `;
                return;
            }
            
            // Status labels mapping (same as in PHP)
            const statusLabels = {
                'new': 'Nouveau colis',
                'pickup_pending': 'En cours de ramassage',
                'collected': 'Ramassé',
                'in_transit': 'En transit',
                'arrived_at_agency': "Arrivé à l'agence",
                'out_for_delivery': 'En cours de livraison',
                'delivered': 'Livrée',
                'refused': 'Refusée',
                'unreachable': 'Client injoignable',
                'rescheduled': 'Reprogrammée',
                'returned_to_sender': "Retour à l'expéditeur",
                'cancelled': 'Annulée',
                'address_error': "Erreur d'adresse",
                'warehouse_waiting': 'En attente au dépôt',
                'delivery_failed': 'Livraison échouée',
                'pending': 'En attente',
                'processing': 'En préparation',
                'shipped': 'Expédiée',
                'not_submitted': 'non soumis'
            };

            // Confirmation options
            const confirmationOptions = {
                'confirmed': 'Confirmée',
                'no-answer': 'Pas de réponse',
                'busy': 'Occupé',
                'cancelled': 'Annulée',
                'double-order': 'Double commande',
                'unreachable': 'Injoignable'
            };

            tbody.innerHTML = orders.map(order => {
                const shippingStatus = order.shipping_status || 'processing';
                const shippingStatusText = statusLabels[shippingStatus] || shippingStatus;
                const currentStatus = order.status || 'new-order';
                
                // Format phone for WhatsApp
                const formatPhoneForWhatsApp = (phone) => {
                    if (!phone) return null;
                    let rawPhone = phone.replace(/[^0-9]/g, '');
                    if (rawPhone.startsWith('0')) {
                        rawPhone = rawPhone.substring(1);
                    }
                    return rawPhone.startsWith('212') ? rawPhone : '212' + rawPhone;
                };

                const waPhone = formatPhoneForWhatsApp(order.customer_phone);
                
                // Build confirmation select options
                const buildConfirmationSelect = (orderId, currentStatus) => {
                    let options = '';
                    
                    // Add "Nouvelle commande" only if it's current status
                    if (currentStatus === 'new-order') {
                        options += `<option value="new-order" selected disabled style="background-color: #cce5ff; color: #004085;">Nouvelle commande</option>`;
                    }
                    
                    // Add other options
                    Object.entries(confirmationOptions).forEach(([value, label]) => {
                        const selected = (currentStatus === value) ? 'selected' : '';
                        let style = "background-color: ";
                        switch(value) {
                            case 'confirmed': style += "#d4edda; color: #155724;"; break;
                            case 'no-answer': style += "#fff3cd; color: #856404;"; break;
                            case 'busy': style += "#ffe8cc; color: #804d00;"; break;
                            case 'cancelled': style += "#f8d7da; color: #721c24;"; break;
                            case 'double-order': style += "#e2e3e5; color: #383d41;"; break;
                            case 'unreachable': style += "#d1ecf1; color: #0c5460;"; break;
                            default: style += "#f8f9fa; color: #212529;";
                        }
                        options += `<option value="${value}" ${selected} style="${style}">${label}</option>`;
                    });
                    
                    return `<select class="confirmation-select" id="confirmation_select_${orderId}" data-order-id="${orderId}" onchange="confirmOrder(this)">${options}</select>`;
                };

                // Show shipping button if confirmed and no tracking number
                const shippingButton = (currentStatus === 'confirmed' && !order.tracking_number) ? 
                    `<div class="shipping-btn-container mt-2">
                        <button class="btn btn-sm btn-primary submit-shipping-btn" 
                                data-order-id="${order.id}" 
                                onclick="sendToShipping(${order.id})">
                            <i class="fas fa-truck"></i> Expédier
                        </button>
                    </div>` : '';

                const formatDate = (dateString) => {
                    const date = new Date(dateString);
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear();
                    const hours = String(date.getHours()).padStart(2, '0');
                    const minutes = String(date.getMinutes()).padStart(2, '0');
                    return `<div>${day}/${month}/${year}</div><div style="font-size: 10px; color: #9ca3af;">${hours}:${minutes}</div>`;
                };

                return `
                    <tr class="order-row" data-order-id="${order.id}" style="cursor: pointer;">
                        <td class="checkbox-cell" onclick="event.stopPropagation();">
                            ${order.assignment_status === 'pending' ? 
                                `<input type="checkbox" class="order-checkbox" value="${order.id}" onchange="handleOrderCheckboxChange(this)">` : 
                                '<input type="checkbox" disabled>'
                            }
                        </td>
                        <td style="display: none;"><strong>${escapeHtml(order.order_number || '')}</strong></td>
                        <td class="tracking-cell">
                            <div class="tracking-info">
                                ${order.tracking_number ? 
                                    `<div class="tracking-number">${escapeHtml(order.tracking_number)}</div>` : 
                                    '<div class="tracking-number">-</div>'
                                }
                                <div class="tracking-status">
                                    <span class="status-badge ${shippingStatus}">
                                        ${shippingStatusText}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="customer-cell">
                            <div class="customer-info">
                                <div class="customer-name">${escapeHtml(order.customer_name || '')}</div>
                                <div class="customer-email">${escapeHtml(order.customer_email || '')}</div>
                            </div>
                        </td>
                        <td class="phone-cell" onclick="event.stopPropagation();">
                            ${waPhone ? 
                                `<div class="whatsapp-container">
                                    <a href="https://wa.me/${waPhone}" class="whatsapp-btn" target="_blank" title="Contacter via WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </a>
                                    <div class="phone-tooltip">
                                        ${escapeHtml(order.customer_phone)}
                                    </div>
                                </div>` : 
                                '<span style="color: #9ca3af; font-size: 11px;">-</span>'
                            }
                        </td>
                        <td class="city-cell">
                            <div style="font-size: 11px; color: #374151;">
                                ${order.customer_ville ? escapeHtml(order.customer_ville) : '-'}
                            </div>
                        </td>
                        <td class="products-cell">
                            <div class="products-container">
                                <div class="product-count">${order.item_count || 0}</div>
                                <div class="products-tooltip">
                                    ${escapeHtml(order.products || '')}
                                </div>
                            </div>
                        </td>
                        <td class="amount-cell">
                            <div class="amount">${(Number(order.total_amount) || 0).toFixed(2)} ${escapeHtml(order.currency || '')}</div>
                        </td>
                        <td class="status-cell">
                            <span class="status-badge ${shippingStatus}">
                                ${shippingStatusText}
                            </span>
                        </td>
                        <td class="confirmation-cell" onclick="event.stopPropagation();">
                            ${buildConfirmationSelect(order.id, currentStatus)}
                            ${shippingButton}
                        </td>
                        <td class="store-cell">
                            <div class="store-name">
                                ${order.store_name ? escapeHtml(order.store_name) : 'N/A'}
                            </div>
                        </td>
                        <td class="date-cell">
                            ${formatDate(order.created_at)}
                        </td>
                        <td style="background-color: none !important;">
                            <button class="edit-btn-compact" onclick="event.stopPropagation(); editOrderData(${order.id});">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'En attente',
                'processing': 'En préparation',
                'shipped': 'Expédié',
                'delivered': 'Livré'
            };
            return statusMap[status] || status;
        }

        function getShippingStatusText(status) {
            const statusMap = {
                'processing': 'En cours',
                'shipped': 'Expédié',
                'delivered': 'Livré',
                'cancelled': 'Annulé'
            };
            return statusMap[status] || status;
        }

        function getOrderActions(order) {
            let actions = '';
            
            if (order.assignment_status === 'available') {
                actions += `
                    <button class="btn-action btn-confirm" onclick="event.stopPropagation(); confirmOrder(${order.id})">
                        <i class="bi bi-check-circle"></i> Confirmer
                    </button>
                `;
            } else if (order.assignment_status === 'assigned') {
                actions += `
                    <button class="btn-action btn-ship" onclick="event.stopPropagation(); sendToShipping(${order.id})">
                        <i class="bi bi-truck"></i> Expédier
                    </button>
                `;
            }
                       
            return actions;
        }

        function updateStats(stats) {
            document.getElementById('stat-total').textContent = stats.total_handled || 0;
            document.getElementById('stat-progress').textContent = stats.in_progress || 0;
            document.getElementById('stat-shipped').textContent = stats.shipped || 0;
            document.getElementById('stat-cancelled').textContent = stats.cancelled || 0;
            document.getElementById('stat-today-confirmed').textContent = stats.today_confirmed || 0;
            document.getElementById('stat-today-shipped').textContent = stats.today_shipped || 0;
            document.getElementById('stat-today-cancelled').textContent = stats.today_cancelled || 0;
        }

        let activeNotifications = new Set();
        let notificationCounter = 0;

        function showNotification(message, type, duration = 5000) {
            // Create unique notification ID
            const notificationId = `notification_${++notificationCounter}`;
            
            // Create notification element
            const notification = document.createElement('div');
            notification.id = notificationId;
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="bi ${getNotificationIcon(type)}"></i>
                <span>${message}</span>
                <button class="notification-close" onclick="removeNotification('${notificationId}')">
                    <i class="bi bi-x"></i>
                </button>
            `;
            
            // Position notification (stack them)
            const existingNotifications = document.querySelectorAll('.notification');
            const topOffset = 20 + (existingNotifications.length * 70); // Stack notifications
            notification.style.top = `${topOffset}px`;
            
            // Add to page and track it
            document.body.appendChild(notification);
            activeNotifications.add(notificationId);
            
            // Auto-remove after specified duration
            const timeoutId = setTimeout(() => {
                removeNotification(notificationId);
            }, duration);
            
            // Store timeout ID for potential cancellation
            notification.dataset.timeoutId = timeoutId;
            
            return notificationId; // Return ID for manual control
        }

        function removeNotification(notificationId) {
            const notification = document.getElementById(notificationId);
            if (notification) {
                // Clear timeout to prevent double removal
                if (notification.dataset.timeoutId) {
                    clearTimeout(parseInt(notification.dataset.timeoutId));
                }
                
                // Add fade-out animation
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                
                // Remove after animation
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                        activeNotifications.delete(notificationId);
                        repositionNotifications();
                    }
                }, 300);
            }
        }

        function getNotificationIcon(type) {
            const icons = {
                'success': 'bi-check-circle',
                'error': 'bi-exclamation-triangle',
                'warning': 'bi-exclamation-circle',
                'info': 'bi-info-circle'
            };
            return icons[type] || 'bi-info-circle';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        function formatPrice(amount) {
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }

        // Add CSS for notifications
        const notificationStyles = `
            .notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 500;
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 10px;
                min-width: 300px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideIn 0.3s ease-out;
            }
            
            .notification.success {
                background-color: #28a745;
            }
            
            .notification.error {
                background-color: #dc3545;
            }
            
            .notification.warning {
                background-color: #ffc107;
                color: #212529;
            }
            
            .notification.info {
                background-color: #17a2b8;
            }
            
            .notification-close {
                background: none;
                border: none;
                color: inherit;
                font-size: 18px;
                cursor: pointer;
                margin-left: auto;
                opacity: 0.7;
            }
            
            .notification-close:hover {
                opacity: 1;
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            .btn-action {
                background: none;
                border: 1px solid #dee2e6;
                padding: 4px 8px;
                margin: 2px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                transition: all 0.2s;
            }
            
            .btn-action:hover {
                background-color: #f8f9fa;
            }
            
            .btn-confirm {
                color: #28a745;
                border-color: #28a745;
            }
            
            .btn-confirm:hover {
                background-color: #28a745;
                color: white;
            }
            
            .btn-ship {
                color: #007bff;
                border-color: #007bff;
            }
            
            .btn-ship:hover {
                background-color: #007bff;
                color: white;
            }
            
            .btn-view {
                color: #6c757d;
                border-color: #6c757d;
            }
            
            .btn-view:hover {
                background-color: #6c757d;
                color: white;
            }
            
            .no-orders {
                text-align: center;
                padding: 40px;
                color: #6c757d;
            }
            
            .status-badge.pending {
                background-color: #fff3cd;
                color: #856404;
            }
            
            .status-badge.processing {
                background-color: #cce5ff;
                color: #004085;
            }
            
            .status-badge.shipped {
                background-color: #d4edda;
                color: #155724;
            }
            
            .status-badge.delivered {
                background-color: #d1ecf1;
                color: #0c5460;
            }
            
            .filter-group {
                display: flex;
                align-items: center;
                margin-right: 15px;
            }
            
            .filter-group label {
                margin-right: 5px;
                font-weight: 500;
                font-size: 14px;
            }
            
            .filter-select {
                padding: 5px 10px;
                border-radius: 4px;
                border: 1px solid #ced4da;
                font-size: 14px;
            }
            
            .stat-card.danger {
                color: white;
            }
        `;
        
        // Add styles to head
        const styleElement = document.createElement('style');
        styleElement.textContent = notificationStyles;
        document.head.appendChild(styleElement);

        function closeOrderModal() {
            const modal = document.getElementById('orderModal');
            if (modal) {
                modal.classList.remove('show');
                modal.classList.add('hide'); // Optional: for fade-out animation
            }
        }
    
    </script>
</body>
</html>