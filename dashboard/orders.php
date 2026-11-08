<?php
if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

$db = DB::getInstance();

if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../lg'); 
} 

$user_id = getCurrentUserId($db);

if (!$user_id) {
    Redirect::to('../lg');
}

$user = $db->getThisQuery("SELECT id, `name`, `role` FROM users WHERE id = ?", [$user_id]);

$aiService = new AIConfirmationService($db, $user_id);
$subscription = new Subscription($user_id);
$subscriptionStatus = $subscription->getSubscriptionStatus();
$subscriptionAlerts = $subscription->getSubscriptionAlerts();

$onboarding = new Onboarding($user_id);

$currentPage = basename($_SERVER['PHP_SELF']);
if (!$onboarding->isOnboardingCompleted() && $currentPage !== 'onboarding') {
    Redirect::to('./onboarding');
}

$canAccessFeatures = $subscription->canAccessFeatures();
$limitedAccess = !$canAccessFeatures;

// ✅ FIXED: Load stores for filter dropdown
$stores = $db->getThisQuery("SELECT id, storeName FROM stores WHERE user_id = ? ORDER BY storeName", [$user_id]);

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    switch ($_POST['action']) {
        case 'get_orders':
            echo getOrders($db, $user_id);
            exit;
    }
}

if (isset($_GET['logout'])) { logout(); }
if (isset($_GET['process_ai_confirmations'])) { $aiService->checkAndProcessPendingAttempts(); }

// ✅ FIXED: getOrders() — added city filter + search
function getOrders($db, $user_id) {
    global $aiService;
    if ($aiService === null) $aiService = new AIConfirmationService($db, $user_id);
    
    try {
        $filters = ["o.user_id = ?"];
        $params = [$user_id];
        
        if (!empty($_POST['ai_confirmation_status'])) {
            switch ($_POST['ai_confirmation_status']) {
                case 'pending_ai':
                    $filters[] = "EXISTS (SELECT 1 FROM ai_confirmation_attempts a WHERE a.order_id = o.id AND a.user_id = o.user_id AND a.status IN ('pending', 'sent') AND a.escalated_to_agent = 0)";
                    break;
                case 'escalated':
                    $filters[] = "EXISTS (SELECT 1 FROM ai_confirmation_attempts a WHERE a.order_id = o.id AND a.user_id = o.user_id AND a.escalated_to_agent = 1)";
                    break;
                case 'confirmed_by_ai':
                    $filters[] = "EXISTS (SELECT 1 FROM ai_confirmation_attempts a WHERE a.order_id = o.id AND a.user_id = o.user_id AND a.status = 'customer_confirmed')";
                    break;
                case 'rejected_by_ai':
                    $filters[] = "EXISTS (SELECT 1 FROM ai_confirmation_attempts a WHERE a.order_id = o.id AND a.user_id = o.user_id AND a.status = 'customer_rejected')";
                    break;
            }
        }
        
        if (!empty($_POST['confirmation_status'])) {
            $filters[] = "o.status = ?";
            $params[] = $_POST['confirmation_status'];
        }
        
        if (!empty($_POST['shipping_status'])) {
            $filters[] = "o.shipping_status = ?";
            $params[] = $_POST['shipping_status'];
        }
        
        if (!empty($_POST['store_id'])) {
            $filters[] = "o.store_id = ?";
            $params[] = $_POST['store_id'];
        }
        
        // ✅ NEW: City filter
        if (!empty($_POST['city'])) {
            $filters[] = "o.customer_ville LIKE ?";
            $params[] = '%' . trim($_POST['city']) . '%';
        }
        
        // ✅ NEW: Search filter
        if (!empty($_POST['search'])) {
            $filters[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ? OR o.customer_email LIKE ?)";
            $s = "%" . trim($_POST['search']) . "%";
            $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
        }
        
        if (!empty($_POST['agent_id'])) {
            if ($_POST['agent_id'] == -1) {
                $filters[] = "(aoa.agent_id IS NULL OR aoa.agent_id = 0)";
            } else {
                $filters[] = "aoa.agent_id = ?";
                $params[] = $_POST['agent_id'];
            }
        }
        
        if (!empty($_POST['assignment_status'])) {
            if ($_POST['assignment_status'] === 'unassigned') {
                $filters[] = "aoa.agent_id IS NULL";
            } else {
                $filters[] = "aoa.status = ?";
                $params[] = $_POST['assignment_status'];
            }
        }
        
        if (!empty($_POST['date_range'])) {
            switch ($_POST['date_range']) {
                case 'today': $filters[] = "DATE(o.created_at) = CURDATE()"; break;
                case 'yesterday': $filters[] = "DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"; break;
                case 'week': $filters[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; break;
                case 'month': $filters[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"; break;
                case '90days': $filters[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"; break;
                case 'year': $filters[] = "YEAR(o.created_at) = YEAR(CURDATE())"; break;
                case 'all': break;
                case 'custom':
                    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
                        $filters[] = "DATE(o.created_at) BETWEEN ? AND ?";
                        $params[] = $_POST['start_date']; $params[] = $_POST['end_date'];
                    } elseif (!empty($_POST['start_date'])) {
                        $filters[] = "DATE(o.created_at) >= ?"; $params[] = $_POST['start_date'];
                    } elseif (!empty($_POST['end_date'])) {
                        $filters[] = "DATE(o.created_at) <= ?"; $params[] = $_POST['end_date'];
                    }
                    break;
            }
        }
        
        if (!empty($_POST['min_amount'])) { $filters[] = "o.total_amount >= ?"; $params[] = floatval($_POST['min_amount']); }
        if (!empty($_POST['max_amount'])) { $filters[] = "o.total_amount <= ?"; $params[] = floatval($_POST['max_amount']); }

        $where_clause = "WHERE " . implode(" AND ", $filters);

        $count_query = "SELECT COUNT(DISTINCT o.id) as total_count FROM orders o 
                        LEFT JOIN order_items oi ON o.id = oi.order_id 
                        LEFT JOIN stores s ON o.store_id = s.id
                        LEFT JOIN agent_order_assignments aoa ON o.id = aoa.order_id AND aoa.user_id = o.user_id
                        LEFT JOIN agents a ON aoa.agent_id = a.id {$where_clause}";
        $count_result = $db->getThisQuery($count_query, $params);
        $total_count = !empty($count_result) ? (int)($count_result[0]['total_count'] ?? 0) : 0;
        
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 50;
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT o.*, COUNT(oi.id) as item_count, GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products,
                        s.storeName, a.name as agent_name, a.id as agent_id, aoa.status as assignment_status,
                        aoa.assigned_at, aoa.confirmed_at, aoa.notes as assignment_notes, aoa.priority_score
                FROM orders o 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                LEFT JOIN stores s ON o.store_id = s.id
                LEFT JOIN agent_order_assignments aoa ON o.id = aoa.order_id AND aoa.user_id = o.user_id
                LEFT JOIN agents a ON aoa.agent_id = a.id
                {$where_clause}
                GROUP BY o.id, a.id, aoa.id
                ORDER BY o.created_at DESC 
                LIMIT {$offset}, {$per_page}";

        $orders = $db->getThisQuery($query, $params);
        if (!$orders) $orders = [];

        $orderIds = array_column($orders, 'id');
        $aiStatusMap = [];
        if (!empty($orderIds)) {
            try { $aiStatusMap = $aiService->getAIConfirmationStatusForOrders($orderIds); }
            catch (Exception $e) { error_log("Error: " . $e->getMessage()); $aiStatusMap = []; }
        }
        
        foreach ($orders as &$order) {
            $aiStatus = $aiStatusMap[$order['id']] ?? null;
            if ($aiStatus) {
                $order['ai_confirmation_status'] = $aiStatus['status'] ?? 'not_started';
                $order['ai_attempt_number'] = (int)($aiStatus['attempt_number'] ?? 0);
                $order['ai_attempts_remaining'] = (int)($aiStatus['attempts_remaining'] ?? 0);
                $order['ai_next_attempt'] = $aiStatus['next_attempt_at'] ?? null;
                $order['ai_escalated'] = (int)($aiStatus['escalated_to_agent'] ?? 0);
                $order['ai_customer_response'] = $aiStatus['customer_response'] ?? null;
                $order['ai_created_at'] = $aiStatus['created_at'] ?? null;
            } else {
                $order['ai_confirmation_status'] = 'not_started';
                $order['ai_attempt_number'] = 0;
                $order['ai_attempts_remaining'] = 0;
                $order['ai_next_attempt'] = null;
                $order['ai_escalated'] = 0;
                $order['ai_customer_response'] = null;
                $order['ai_created_at'] = null;
            }
            $order['ai_enabled'] = $aiService->isAIConfirmationEnabled();
        }

        $stats_query = "SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'confirmed' THEN o.id END) as confirmed_by_us,
            COUNT(DISTINCT CASE WHEN o.shipping_status = 'delivered' THEN o.id END) as confirmed_by_shipping,
            COUNT(DISTINCT CASE WHEN o.status = 'cancelled' THEN o.id END) as cancelled_by_us,
            COUNT(DISTINCT CASE WHEN o.shipping_status IN ('returned_to_sender', 'cancelled') THEN o.id END) as cancelled_by_shipping,
            COALESCE(SUM(CASE WHEN o.shipping_status = 'delivered' THEN o.total_amount ELSE 0 END), 0) as total_revenue,
            (SELECT o2.currency FROM orders o2 WHERE o2.user_id = ? GROUP BY o2.currency ORDER BY COUNT(*) DESC LIMIT 1) as currency
            FROM orders o 
            LEFT JOIN agent_order_assignments aoa ON o.id = aoa.order_id AND aoa.user_id = o.user_id
            {$where_clause}";

        $stats_params = array_merge($params, [$user_id]);
        $stats_result = $db->getThisQuery($stats_query, $stats_params);
        
        if (empty($stats_result)) {
            $stats_result = [[
                'total_orders' => 0, 'confirmed_by_us' => 0, 'confirmed_by_shipping' => 0,
                'cancelled_by_us' => 0, 'cancelled_by_shipping' => 0, 'total_revenue' => 0, 'currency' => 'MAD'
            ]];
        }
        
        $stats = $stats_result[0];
        $confirmed_total = ($stats['confirmed_by_us'] ?? 0) + ($stats['confirmed_by_shipping'] ?? 0);
        $cancelled_total = ($stats['cancelled_by_us'] ?? 0) + ($stats['cancelled_by_shipping'] ?? 0);
        
        $dashboard_stats = [
            'total_orders' => (int)($stats['total_orders'] ?? 0),
            'confirmed' => [
                'total' => (int)$confirmed_total,
                'by_us' => (int)($stats['confirmed_by_us'] ?? 0),
                'by_shipping' => (int)($stats['confirmed_by_shipping'] ?? 0)
            ],
            'cancelled' => [
                'total' => (int)$cancelled_total,
                'by_us' => (int)($stats['cancelled_by_us'] ?? 0),
                'by_shipping' => (int)($stats['cancelled_by_shipping'] ?? 0)
            ],
            'revenue' => [
                'amount' => (float)($stats['total_revenue'] ?? 0),
                'currency' => $stats['currency'] ?? 'MAD'
            ]
        ];
        
        return json_encode([
            'success' => true,
            'orders' => $orders,
            'stats' => [
                'total_orders' => (int)($stats['total_orders'] ?? 0),
                'confirmed_by_us' => (int)($stats['confirmed_by_us'] ?? 0),
                'confirmed_by_shipping' => (int)($stats['confirmed_by_shipping'] ?? 0),
                'cancelled_by_us' => (int)($stats['cancelled_by_us'] ?? 0),
                'cancelled_by_shipping' => (int)($stats['cancelled_by_shipping'] ?? 0),
                'total_revenue' => (float)($stats['total_revenue'] ?? 0),
                'currency' => $stats['currency'] ?? 'MAD'
            ],
            'dashboard_stats' => $dashboard_stats,
            'pagination' => [
                'total' => (int)$total_count, 'page' => (int)$page,
                'per_page' => (int)$per_page, 'total_pages' => (int)ceil($total_count / $per_page)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error in getOrders: " . $e->getMessage());
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage(),
            'orders' => [],
            'stats' => ['total_orders'=>0,'confirmed_by_us'=>0,'confirmed_by_shipping'=>0,'cancelled_by_us'=>0,'cancelled_by_shipping'=>0,'total_revenue'=>0,'currency'=>'MAD'],
            'dashboard_stats' => [
                'total_orders' => 0,
                'confirmed' => ['total'=>0,'by_us'=>0,'by_shipping'=>0],
                'cancelled' => ['total'=>0,'by_us'=>0,'by_shipping'=>0],
                'revenue' => ['amount'=>0,'currency'=>'MAD']
            ],
            'pagination' => ['total'=>0,'page'=>1,'per_page'=>50,'total_pages'=>0]
        ]);
    }
}

$initial_orders = json_decode(getOrders($db, $user_id), true); 

function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) return null;
    $identifier = $_SESSION['user']['username'];
    if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    $user = $db->getThisQuery("SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1",
        [$identifier, $identifier, $identifier]);
    if ($user && isset($user[0]['id'])) return (int)$user[0]['id'];
    return null;
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../login.php');
} ?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestion des commandes - PLatForme">
    <title>Commandes | OBG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/orders.css" />
    <link rel="stylesheet" href="../assets/css/stats.css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .btn-bulk-confirm { padding: 5px 10px; border: 1px solid #ccc; }
        .btn-bulk-confirm:hover { background-color: #969696; color: #faf8ff; border-color: #faf8ff; }

        #import-sheets-btn {
            padding: 0 .8em; border: solid 1px #198754; background: none;
            color: #198754; border-radius: .2em; line-height: 3em;
        }
        #import-sheets-btn:hover { background-color: #198754; color: #FFF; }

        #createCommande-btn {
            padding: 0 .8em; border: solid 1px #444655; background: none;
            color: #444655; border-radius: .2em; line-height: 3em;
        }
        #createCommande-btn:hover { background-color: #444655; color: #FFF; }

        .stats-cards { grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .loading { display: none; text-align: center; padding: 20px; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 2px !important;
            font-size: 8px !important;
            font-weight: 300;
            color: #fff;
        }
        .status-badge.new { background: #9ca3af; }
        .status-badge.pickup_pending { background: #fff3cd; color: #856404; }
        .status-badge.collected { background: #cce5ff; color: #004085; }
        .status-badge.in_transit { background: #bee3f8; color: #0c5460; }
        .status-badge.arrived_at_agency { background: #d1ecf1; color: #0c5460; }
        .status-badge.out_for_delivery { background: #ffeeba; color: #856404; }
        .status-badge.delivered { background: #d4edda; color: #155724; }
        .status-badge.refused { background: #f8d7da; color: #721c24; }
        .status-badge.unreachable { background: #f5c6cb; color: #721c24; }
        .status-badge.rescheduled { background: #e2e3e5; color: #383d41; }
        .status-badge.returned_to_sender { background: #f1f1f1; color: #212529; }
        .status-badge.cancelled { background: #f8d7da; color: #721c24; }
        .status-badge.address_error { background: #f5c6cb; color: #721c24; }
        .status-badge.warehouse_waiting { background: #e2e3e5; color: #383d41; }
        .status-badge.delivery_failed { background: #f8d7da; color: #721c24; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.processing { background: #d1ecf1; color: #0c5460; }
        .status-badge.shipped { background: #bee3f8; color: #0c5460; }
        .status-badge.not_submitted { background: #e0e0e0; color: #555; }

        /* AI status badges */
        .ai-status-badge {
            display: inline-block; padding: 4px 8px; border-radius: 12px;
            font-size: 10px; font-weight: 500; text-align: center; white-space: nowrap;
        }
        .ai-status-badge.pending { background: #fff3cd; color: #856404; }
        .ai-status-badge.sent { background: #d1ecf1; color: #0c5460; }
        .ai-status-badge.delivered { background: #cce5ff; color: #004085; }
        .ai-status-badge.customer_confirmed { background: #d4edda; color: #155724; }
        .ai-status-badge.customer_rejected { background: #f8d7da; color: #721c24; }
        .ai-status-badge.no_response { background: #fff3cd; color: #856404; }
        .ai-status-badge.escalated { background: #f1f1f1; color: #212529; }
        .ai-status-badge.failed { background: #f8d7da; color: #721c24; }
        .ai-status-badge.not_started { background: #e2e3e5; color: #383d41; }
        .ai-status-badge.disabled { background: #e2e3e5; color: #6c757d; }

        /* ✅ FIXED: Filter bar */
        .orders-filters {
            background: white; padding: 20px; border-radius: 10px;
            margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .filters-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; flex: 1; min-width: 150px; }
        .filter-group label {
            font-size: 11px; font-weight: 600; color: #495057;
            margin-bottom: 6px; display: flex; align-items: center; gap: 4px;
        }
        .filter-group label i { color: #9c80fd; }
        .filter-group .form-control {
            padding: 8px 12px; border: 1.5px solid #e0e0e0; border-radius: 8px;
            font-size: 13px; background: #fafafa; transition: all 0.2s ease; height: 40px;
        }
        .filter-group .form-control:focus {
            border-color: #9c80fd; box-shadow: 0 0 0 3px rgba(156, 128, 253, 0.15);
            outline: none; background: #fff;
        }
        .filter-actions { display: flex; gap: 8px; align-items: center; }
        .btn-filter-apply {
            background: linear-gradient(135deg, #9c80fd 0%, #764ba2 100%);
            color: white; border: none; padding: 8px 20px; border-radius: 25px;
            font-weight: 600; font-size: 13px; cursor: pointer;
            display: flex; align-items: center; gap: 8px; transition: all 0.2s;
            height: 40px; white-space: nowrap;
        }
        .btn-filter-apply:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(156, 128, 253, 0.3); }
        .btn-filter-reset {
            background: #fff; color: #6c757d; border: 1px solid #e0e0e0;
            padding: 8px 16px; border-radius: 25px; font-weight: 500;
            font-size: 13px; cursor: pointer; display: flex; align-items: center;
            gap: 6px; height: 40px; transition: all 0.2s; white-space: nowrap;
        }
        .btn-filter-reset:hover { border-color: #9c80fd; color: #9c80fd; }
        .date-input-group { display: flex; gap: 8px; align-items: center; }
        .date-input-group .form-control { flex: 1; min-width: 0; }

        /* Table */
        .table-container, .orders-table-container {
            background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden; overflow-x: auto; position: relative;
        }
        .orders-table {
            width: 100%; border-collapse: collapse; min-width: 1200px; font-size: 13px;
        }
        .orders-table th {
            background: linear-gradient(135deg, #9c80fd 0%, #764ba2 100%);
            color: white; padding: 12px 8px; text-align: center; font-weight: 600;
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;
            position: sticky; top: 0; z-index: 10;
        }
        .orders-table td { padding: 8px; border-bottom: 1px solid #f1f3f4; vertical-align: middle; }
        .orders-table tbody tr:hover {
            background-color: #f8f9ff; transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.2s ease;
        }

        .checkbox-cell { width: 40px; text-align: center; }
        .tracking-cell { width: 120px; }
        .customer-cell { width: 140px; }
        .phone-cell { width: 50px; text-align: center; }
        .city-cell { width: 80px; }
        .products-cell { width: 80px; text-align: center; }
        .amount-cell { width: 90px; }
        .status-cell { width: 100px; text-align: center; }
        .ai-confirmation-cell { width: 100px; text-align: center; }
        .confirmation-cell { width: 130px; }
        .agent-cell { width: 100px; text-align: center; }
        .store-cell { width: 80px; }
        .date-cell { width: 90px; }
        .actions-cell { width: 60px; text-align: center; }

        .agent-name {
            font-size: 11px; color: #374151; font-weight: 500;
            margin: 0 auto; text-align: center;
        }

        .tracking-info { display: flex; flex-direction: column; gap: 2px; }
        .tracking-number { font-weight: 600; font-size: 12px; color: #2563eb; }
        .tracking-status { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; }

        .customer-info { display: flex; flex-direction: column; gap: 2px; }
        .customer-name { font-weight: 600; font-size: 12px; color: #111827; }
        .customer-email {
            font-size: 10px; color: #6b7280; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap; max-width: 130px;
        }

        .whatsapp-container { position: relative; display: inline-block; }
        .whatsapp-btn {
            background: #25d366; color: white; border: none; border-radius: 50%;
            width: 28px; height: 28px; display: flex; align-items: center;
            justify-content: center; cursor: pointer; transition: all 0.2s ease;
            text-decoration: none; font-size: 14px;
        }
        .whatsapp-btn:hover { background: #128c7e; transform: scale(1.1); }
        .phone-tooltip {
            position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
            background: #333; color: white; padding: 6px 10px; border-radius: 6px;
            font-size: 11px; white-space: nowrap; opacity: 0; visibility: hidden;
            transition: all 0.2s ease; z-index: 1000;
        }
        .phone-tooltip::after {
            content: ''; position: absolute; top: 100%; left: 50%;
            transform: translateX(-50%); border: 4px solid transparent; border-top-color: #333;
        }
        .whatsapp-container:hover .phone-tooltip { opacity: 1; visibility: visible; }

        .products-container { position: relative; display: inline-block; }
        .product-count {
            background-color: #9c80fd !important; color: #374151;
            padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;
            text-align: center; cursor: pointer; transition: all 0.2s ease;
        }
        .product-count:hover { background: #d1d5db; transform: scale(1.05); }
        .products-tooltip {
            position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
            background: #333; color: white; padding: 8px 12px; border-radius: 6px;
            font-size: 11px; white-space: nowrap; opacity: 0; visibility: hidden;
            transition: all 0.2s ease; z-index: 1000;
        }
        .products-tooltip::after {
            content: ''; position: absolute; top: 100%; left: 50%;
            transform: translateX(-50%); border: 4px solid transparent; border-top-color: #333;
        }
        .products-container:hover .products-tooltip { opacity: 1; visibility: visible; }

        .confirmation-select {
            padding: 4px 6px; border-radius: 12px; border: 1px solid #d1d5db;
            font-size: 10px; font-weight: 500; text-transform: uppercase;
            cursor: pointer; width: 100%; background: white;
        }
        .confirmation-select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2); }

        .amount { font-weight: 600; color: #059669; font-size: 12px; }
        .date-cell { font-size: 11px; color: #6b7280; }
        .store-name { font-size: 11px; color: #374151; font-weight: 500; }

        .actions-dropdown { position: relative; display: inline-block; }
        .actions-trigger {
            background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 6px;
            padding: 4px 8px; cursor: pointer; font-size: 16px; color: #6b7280;
            transition: all 0.2s ease; width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
        }
        .actions-trigger:hover { background: #e5e7eb; border-color: #9ca3af; transform: scale(1.05); }
        .actions-menu {
            position: absolute; top: 100%; right: 0; background: white;
            border: 1px solid #d1d5db; border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 140px;
            z-index: 1000; opacity: 0; visibility: hidden; transform: translateY(-10px);
            transition: all 0.2s ease;
        }
        .actions-dropdown.open .actions-menu { opacity: 1; visibility: visible; transform: translateY(0); }
        .actions-menu button {
            width: 100%; padding: 8px 12px; border: none; background: none;
            text-align: left; cursor: pointer; font-size: 12px; color: #374151;
            display: flex; align-items: center; gap: 8px; transition: background-color 0.2s ease;
        }
        .actions-menu button:hover { background-color: #f3f4f6; }
        .actions-menu button:first-child { border-radius: 8px 8px 0 0; }
        .actions-menu button:last-child { border-radius: 0 0 8px 8px; }
        .actions-menu button.delete-btn:hover { background-color: #fee2e2; color: #991b1b; }
        .actions-menu svg { flex-shrink: 0; }

        .no-data { text-align: center; padding: 40px; color: #6b7280; font-style: italic; }

        .checkbox-cell input[type="checkbox"] { width: 16px; height: 16px; accent-color: #2563eb; cursor: pointer; }

        .btn-bulk-ship {
            background-color: #28a745; color: white; border: none;
            padding: 8px 16px; border-radius: 4px; font-size: 14px;
            font-weight: 500; cursor: pointer; transition: all 0.2s ease;
            display: flex; align-items: center; gap: 6px;
        }
        .btn-bulk-ship:hover { background-color: #218838; transform: translateY(-1px); }
        .btn-bulk-ship:disabled { background-color: #6c757d; cursor: not-allowed; }

        /* Pagination */
        .pagination-container {
            background: white; padding: 15px; border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 10px;
        }
        .pagination-info { font-size: 14px; color: #6c757d; }
        .page-btn {
            background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px;
            padding: 6px 10px; cursor: pointer; font-size: 12px; transition: all 0.2s ease;
        }
        .page-btn:hover:not(:disabled) { background: #e9ecef; border-color: #ced4da; }
        .page-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .page-number {
            min-width: 32px; height: 32px; display: flex; align-items: center;
            justify-content: center; background: #f8f9fa; border: 1px solid #dee2e6;
            border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s ease;
        }
        .page-number:hover { background: #e9ecef; border-color: #ced4da; }
        .page-number.active { background: #007bff; color: white; border-color: #007bff; }
        .page-ellipsis { padding: 0 8px; display: flex; align-items: center; justify-content: center; }
        #per-page-select { padding: 6px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; }
        .pagination-controls { display: flex; align-items: center; gap: 10px; }
        .page-navigation { display: flex; gap: 5px; }
        #page-numbers { display: flex; gap: 5px; }

        /* Bulk Actions */
        .bulk-actions {
            display: flex; align-items: center; justify-content: space-between;
            background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px;
            padding: 12px 16px; margin: 16px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            animation: slideDown 0.3s ease-out;
        }
        .selected-count { font-weight: 600; color: #495057; font-size: 14px; }
        .bulk-buttons { display: flex; gap: 8px; }
        .btn-bulk-status, .btn-bulk-delete {
            padding: 8px 16px; border: none; border-radius: 4px; font-size: 14px;
            font-weight: 500; cursor: pointer; transition: all 0.2s ease;
            display: flex; align-items: center; gap: 6px;
        }
        .btn-bulk-status { background: #007bff; color: white; }
        .btn-bulk-status:hover { background: #0056b3; transform: translateY(-1px); }
        .btn-bulk-delete { background: #dc3545; color: white; }
        .btn-bulk-delete:hover { background: #c82333; transform: translateY(-1px); }
        .select-all-checkbox, .order-checkbox { width: 16px; height: 16px; cursor: pointer; accent-color: #007bff; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Modals */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0;
            width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe; margin: 15% auto; padding: 0; border: none;
            width: 400px; border-radius: 0px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .modal-large { width: 80%; max-width: 800px; }
        .modal-header {
            padding: 20px; border-bottom: 1px solid #eee; display: flex;
            justify-content: space-between; align-items: center;
        }
        .modal-header h3 { margin: 0; }
        .modal-body { padding: 20px; }
        .modal-footer {
            padding: 20px; border-top: 1px solid #eee; display: flex;
            justify-content: flex-end; gap: 10px;
        }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }

        /* Notifications */
        .notification {
            position: fixed; top: 20px; right: 20px; padding: 15px 20px;
            border-radius: 4px; color: white; font-weight: 500;
            transform: translateX(400px); transition: transform 0.3s ease; z-index: 1001;
        }
        .notification.show { transform: translateX(0); }
        .notification-success { background-color: #28a745; }
        .notification-error { background-color: #dc3545; }
        .notification-info { background-color: #17a2b8; }
        .notification-warning { background-color: #ffc107; color: #212529; }
        .spin { animation: spin 2s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Dashboard */
        .dashboard-content { padding: 20px; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .dashboard-header h2 { margin: 0; color: #2c3e50; }
        .action-buttons { display: flex; gap: 10px; }
        .btn-primary {
            background-color: #007bff; color: white; border: none;
            padding: 10px 16px; border-radius: 6px; cursor: pointer;
            font-size: 14px; font-weight: 500; display: flex; align-items: center;
            gap: 8px; transition: background-color 0.2s;
        }
        .btn-primary:hover { background-color: #0056b3; }
        .btn-primary { background: linear-gradient(135deg, #9c80fd 0%, #764ba2 100%) !important; }
        .btn-secondary {
            background-color: #6c757d; color: white; border: none; padding: 10px 16px;
            border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500;
            display: flex; align-items: center; gap: 8px;
        }
        .btn-secondary:hover { background-color: #545b62; }

        .stat-card {
            background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 20px; transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative; overflow: hidden;
        }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%; }
        .stat-card.total::before { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card.confirmed::before { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-card.cancelled::before { background: linear-gradient(135deg, #dc3545, #e83e8c); }
        .stat-card.revenue::before { background: linear-gradient(135deg, #fd7e14, #ffcc00); }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; margin-bottom: 15px; font-size: 20px;
        }
        .stat-card.total .stat-icon { background: rgba(102, 126, 234, 0.15); color: #667eea; }
        .stat-card.confirmed .stat-icon { background: rgba(40, 167, 69, 0.15); color: #28a745; }
        .stat-card.cancelled .stat-icon { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
        .stat-card.revenue .stat-icon { background: rgba(253, 126, 20, 0.15); color: #fd7e14; }
        .stat-value { font-size: 28px; font-weight: 700; margin-bottom: 5px; color: #2c3e50; }
        .stat-title { font-size: 14px; color: #6c757d; font-weight: 500; margin-bottom: 1px; }
        .stat-breakdown {
            border-top: 1px solid #e9ecef; padding-top: 15px; margin-top: 15px;
            display: flex; column-gap: 1em;
        }
        .breakdown-item { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
        .breakdown-label { color: #6c757d; }
        .breakdown-value {
            font-weight: 600; color: #f7ebff; background-color: #9c80fd;
            padding: 0 .4em; margin: 0 .2em; border-radius: 50%;
        }
        .currency { font-size: 16px; font-weight: 600; color: #6c757d; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stats-container { margin-bottom: 20px; }

        /* Notifications bell */
        .notifications { position: relative; display: inline-block; }
        .notification-btn {
            position: relative; background: none; border: none; cursor: pointer;
            padding: 8px; border-radius: 6px; transition: background-color 0.2s; color: #6b7280;
        }
        .notification-btn:hover { background-color: #f3f4f6; color: #374151; }
        .notification-badge {
            position: absolute; top: 2px; right: 2px; background-color: #ef4444;
            color: white; border-radius: 50%; width: 18px; height: 18px;
            font-size: 10px; font-weight: bold; display: flex; align-items: center;
            justify-content: center; min-width: 18px; padding: 0 2px;
            animation: pulse 2s infinite;
        }
        .notification-badge.hidden { display: none; }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        .notification-dropdown {
            position: absolute; top: 100%; right: 0; background: white;
            border: 1px solid #e5e7eb; border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            width: 320px; max-height: 400px; overflow-y: auto;
            z-index: 1000; display: none;
        }
        .notification-dropdown.show { display: block; }
        .notification-header {
            padding: 12px 16px; border-bottom: 1px solid #e5e7eb;
            font-weight: 600; color: #374151; display: flex;
            justify-content: space-between; align-items: center;
        }
        .mark-all-read { background: none; border: none; color: #3b82f6; font-size: 12px; cursor: pointer; text-decoration: underline; }
        .notification-list { max-height: 320px; overflow-y: auto; }
        .notification-item { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; cursor: pointer; transition: background-color 0.2s; }
        .notification-item:hover { background-color: #f9fafb; }
        .notification-item.unread { background-color: #eff6ff; border-left: 3px solid #3b82f6; }
        .notification-item:last-child { border-bottom: none; }

        /* Subscription alerts */
        .subscription-alerts-container { margin-bottom: 24px; }
        .subscription-alert {
            display: flex; align-items: center; padding: 16px; border-radius: 12px;
            margin-bottom: 12px; position: relative; gap: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .alert-danger { background: linear-gradient(135deg, #fee 0%, #fdd 100%); border: 1px solid #f5c6cb; color: #721c24; }
        .alert-warning { background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border: 1px solid #ffeaa7; color: #856404; }
        .alert-info { background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border: 1px solid #b8daff; color: #0c5460; }
        .alert-icon { flex-shrink: 0; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .alert-content { flex: 1; }
        .alert-content h4 { margin: 0 0 4px 0; font-weight: 600; }
        .alert-content p { margin: 0; font-size: 14px; }
        .alert-actions { display: flex; gap: 12px; align-items: center; }
        .alert-btn { background: #007bff; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .alert-btn:hover { background: #0056b3; }
        .alert-close { background: none; border: none; color: inherit; cursor: pointer; padding: 4px; opacity: 0.7; font-size: 18px; }
        .alert-close:hover { opacity: 1; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filters-row { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .filter-actions { width: 100%; }
            .btn-filter-apply, .btn-filter-reset { flex: 1; justify-content: center; }
            .pagination-container { flex-direction: column; }
            .bulk-actions { flex-direction: column; gap: 12px; }
            .action-buttons { flex-direction: column; }
            .table-container { margin: 0 -20px; border-radius: 0; }
            .orders-table { font-size: 11px; }
            .orders-table th, .orders-table td { padding: 6px 4px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .orders-table { font-size: 12px; }
        }
    </style>
</head>
<body>
    <?php $currentPage = 'orders'; require_once('../assets/sidebar.php'); ?>
    <main class="main-content" id="mainContent">

        <?php if (!empty($subscriptionAlerts)): ?>
            <div class="subscription-alerts-container">
                <?php foreach ($subscriptionAlerts as $alert): ?>
                    <div class="subscription-alert alert-<?php echo $alert['type']; ?>" data-priority="<?php echo $alert['priority']; ?>">
                        <div class="alert-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                            </svg>
                        </div>
                        <div class="alert-content">
                            <h4><?php echo htmlspecialchars($alert['title']); ?></h4>
                            <p><?php echo htmlspecialchars($alert['message']); ?></p>
                        </div>
                        <div class="alert-actions">
                            <a href="<?php echo htmlspecialchars($alert['action_url']); ?>" class="alert-btn">
                                <?php echo htmlspecialchars($alert['action_text']); ?>
                            </a>
                        </div>
                        <button class="alert-close" onclick="this.closest('.subscription-alert').remove()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Top Navigation Bar -->
        <nav class="top-navbar" style="display:flex;justify-content:flex-end;padding:10px 0;margin-bottom:10px;">
            <div class="search-bar"></div>
            <div class="top-navbar-right">
                <div class="notifications">
                    <button class="notification-btn" data-tooltip="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2z"/>
                            <path d="M8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.918z"/>
                        </svg>
                        <span class="notification-badge hidden">0</span>
                    </button>
                    <div class="notification-dropdown">
                        <div class="notification-header">
                            <span>Notifications</span>
                            <button class="mark-all-read">Marquer tout comme lu</button>
                        </div>
                        <div class="notification-list">
                            <div class="loading">Chargement des notifications...</div>
                        </div>
                    </div>
                </div>
                <script src="../assets/js/notifications.js"></script>
            </div>
        </nav>
        
        <!-- Orders Content -->
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h2>Gestion des commandes</h2>
                <div class="action-buttons">
                    <button class="btn-primary" id="sync-orders-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M2 8a6 6 0 1 1 11.472 2.093l.919.393a.5.5 0 0 1-.384.918l-2.1-.9a.5.5 0 0 1-.303-.457V6.5a.5.5 0 0 1 1 0v2.235A5 5 0 1 0 3 8a.5.5 0 0 1-1 0z"/>
                        </svg>
                        Synchroniser les commandes
                    </button>
                    <button class="btn-success" id="createCommande-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 4a.5.5 0 0 1 .5.5V7.5H11.5a.5.5 0 0 1 0 1H8.5v3a.5.5 0 0 1-1 0v-3H4.5a.5.5 0 0 1 0-1H7.5V4.5A.5.5 0 0 1 8 4z"/>
                        </svg>
                        Créer une commande
                    </button>
                    <a class="btn-success" href="Import_InterfaceApi.php" id="import-sheets-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 0a8 8 0 1 0 8 8A8.01 8.01 0 0 0 8 0zm3.354 6.354a.5.5 0 0 1-.708 0L8.5 4.207V11.5a.5.5 0 0 1-1 0V4.207L5.354 6.354a.5.5 0 1 1-.708-.708l3-3a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708z"/>
                        </svg>
                        Google Sheets
                    </a>
                </div>
            </div>
            
            <!-- ✅ FIXED: Filter Bar -->
            <div class="orders-filters">
                <form method="POST" action="" id="filterForm">
                    <input type="hidden" name="action" value="get_orders">
                    <div class="filters-row">
                        
                        <div class="filter-group">
                            <label for="confirmation-filter"><i class="fas fa-check-circle"></i> Confirmation</label>
                            <select id="confirmation-filter" name="confirmation_status" class="form-control">
                                <option value="">Tous</option>
                                <option value="new-order">Nouvelle commande</option>
                                <option value="confirmed">Confirmée</option>
                                <option value="no-answer">Pas de réponse</option>
                                <option value="busy">Occupé</option>
                                <option value="cancelled">Annulée</option>
                                <option value="double-order">Double commande</option>
                                <option value="unreachable">Injoignable</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="shipping-filter"><i class="fas fa-truck"></i> Livraison</label>
                            <select id="shipping-filter" name="shipping_status" class="form-control">
                                <option value="">Tous</option>
                                <option value="new">Nouveau colis</option>
                                <option value="pickup_pending">En cours de ramassage</option>
                                <option value="collected">Ramassé</option>
                                <option value="in_transit">En transit</option>
                                <option value="arrived_at_agency">Arrivé à l'agence</option>
                                <option value="out_for_delivery">En cours de livraison</option>
                                <option value="delivered">Livrée</option>
                                <option value="refused">Refusée</option>
                                <option value="unreachable">Client injoignable</option>
                                <option value="rescheduled">Reprogrammée</option>
                                <option value="returned_to_sender">Retour à l'expéditeur</option>
                                <option value="cancelled">Annulée</option>
                                <option value="address_error">Erreur d'adresse</option>
                                <option value="warehouse_waiting">En attente au dépôt</option>
                                <option value="delivery_failed">Livraison échouée</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="date-range"><i class="fas fa-calendar-alt"></i> Période</label>
                            <select id="date-range" name="date_range" class="form-control">
                                <option value="today">Aujourd'hui</option>
                                <option value="week" selected>7 derniers jours</option>
                                <option value="month">30 derniers jours</option>
                                <option value="90days">90 derniers jours</option>
                                <option value="year">Cette année</option>
                                <option value="all">Tout</option>
                                <option value="custom">Personnalisée</option>
                            </select>
                        </div>

                        <div id="custom-date-group" class="filter-group" style="display: none;">
                            <label><i class="fas fa-calendar-day"></i> Du / Au</label>
                            <div class="date-input-group">
                                <input type="date" id="start-date" name="start_date" class="form-control">
                                <span>à</span>
                                <input type="date" id="end-date" name="end_date" class="form-control">
                            </div>
                        </div>

                        <div class="filter-group">
                            <label for="store-filter"><i class="fas fa-store"></i> Magasin</label>
                            <select id="store-filter" name="store_id" class="form-control">
                                <option value="">Tous les magasins</option>
                                <?php foreach ($stores as $store): ?>
                                    <option value="<?= $store['id'] ?>"><?= htmlspecialchars($store['storeName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="city-filter"><i class="fas fa-map-marker-alt"></i> Ville</label>
                            <input type="text" id="city-filter" name="city" class="form-control" placeholder="Ex: Casablanca">
                        </div>

                        <div class="filter-group">
                            <label for="search-input"><i class="fas fa-search"></i> Rechercher</label>
                            <input type="text" id="search-input" name="search" class="form-control" placeholder="Commande, client...">
                        </div>

                        <div class="filter-group">
                            <label for="ai-confirmation-filter"><i class="fas fa-robot"></i> AI Status</label>
                            <select id="ai-confirmation-filter" name="ai_confirmation_status" class="form-control">
                                <option value="">Tous</option>
                                <option value="pending_ai">En attente AI</option>
                                <option value="escalated">Escaladé</option>
                                <option value="confirmed_by_ai">Confirmé par AI</option>
                                <option value="rejected_by_ai">Refusé par AI</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn-filter-apply" id="apply-filters">
                                <i class="fas fa-filter"></i> Appliquer
                            </button>
                            <button type="button" class="btn-filter-reset" id="reset-filters">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="loading" id="loading" style="display: none;">
                <p>Chargement...</p>
            </div>

            <!-- Stats -->
            <div class="stats-container">
                <div class="stats-grid">
                    <div class="stat-card total">
                        <div class="stat-icon"><i class="fas fa-shopping-cart" style="color: #9c80fd;"></i></div>
                        <div class="stat-value" id="total-orders-value"><?php echo number_format($initial_orders['dashboard_stats']['total_orders'] ?? 0); ?></div>
                        <div class="stat-title">Total des Commandes</div>
                    </div>
                    <div class="stat-card confirmed">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value" id="confirmed-orders-value"><?php echo number_format($initial_orders['dashboard_stats']['confirmed']['total'] ?? 0); ?></div>
                        <div class="stat-title">Commandes Confirmées</div>
                        <div class="stat-breakdown">
                            <div class="breakdown-item">
                                <span class="breakdown-label">Store</span>
                                <span class="breakdown-value" id="confirmed-by-us"><?php echo number_format($initial_orders['dashboard_stats']['confirmed']['by_us'] ?? 0); ?></span>
                            </div>
                            <div class="breakdown-item">
                                <span class="breakdown-label">Livraison</span>
                                <span class="breakdown-value" id="confirmed-by-shipping"><?php echo number_format($initial_orders['dashboard_stats']['confirmed']['by_shipping'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card cancelled">
                        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                        <div class="stat-value" id="cancelled-orders-value"><?php echo number_format($initial_orders['dashboard_stats']['cancelled']['total'] ?? 0); ?></div>
                        <div class="stat-title">Commandes Annulées</div>
                        <div class="stat-breakdown">
                            <div class="breakdown-item">
                                <span class="breakdown-label">Store</span>
                                <span class="breakdown-value" id="cancelled-by-us"><?php echo number_format($initial_orders['dashboard_stats']['cancelled']['by_us'] ?? 0); ?></span>
                            </div>
                            <div class="breakdown-item">
                                <span class="breakdown-label">Livraison</span>
                                <span class="breakdown-value" id="cancelled-by-shipping"><?php echo number_format($initial_orders['dashboard_stats']['cancelled']['by_shipping'] ?? 0); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="stat-card revenue">
                        <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="stat-value" id="revenue-value">
                            <?php echo number_format($initial_orders['dashboard_stats']['revenue']['amount'] ?? 0, 2); ?>
                            <span class="currency"><?php echo $initial_orders['dashboard_stats']['revenue']['currency'] ?? 'MAD'; ?></span>
                        </div>
                        <div class="stat-title">Chiffre d'Affaires</div>
                    </div>
                </div>
            </div>

            <style>
                /* ─── ACTION BUTTONS (inline icons) ─── */
                .actions-cell { width: 140px; text-align: center; }
                .action-buttons-visible {
                    display: inline-flex;
                    gap: 6px;
                    align-items: center;
                    justify-content: center;
                }

                .btn-action {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px;
                    height: 32px;
                    border: none;
                    border-radius: 8px;
                    background: #f3f4f6;
                    color: #4b5563;
                    cursor: pointer;
                    font-size: 14px;
                    line-height: 1;
                    transition: transform .15s ease, box-shadow .15s ease, background .15s ease, color .15s ease;
                    padding: 0;
                }

                .btn-action svg,
                .btn-action i { pointer-events: none; }

                .btn-action:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 10px rgba(0,0,0,0.12);
                }

                /* Individual colors */
                .btn-action.btn-view:hover {
                    background: #e7f1ff;
                    color: #0d6efd;
                }

                .btn-action.btn-edit:hover {
                    background: #fff8e1;
                    color: #f59e0b;
                }

                .btn-action.btn-assign {
                    background: #e8f5e9;
                    color: #2e7d32;
                }
                .btn-action.btn-assign:hover {
                    background: #2e7d32;
                    color: #fff;
                }

                .btn-action.btn-reassign {
                    background: #e0f2fe;
                    color: #0284c7;
                }
                .btn-action.btn-reassign:hover {
                    background: #0284c7;
                    color: #fff;
                }

                .btn-action.btn-shipping {
                    background: #e8f5e9;
                    color: #2e7d32;
                }
                .btn-action.btn-shipping:hover {
                    background: #2e7d32;
                    color: #fff;
                }

                .btn-action.btn-delete:hover {
                    background: #fdecea;
                    color: #dc3545;
                }

                .btn-action:active {
                    transform: translateY(0);
                    box-shadow: none;
                }
            </style>

            <!-- Orders Table -->
            <div class="orders-table-container">
                <table class="orders-table" id="orders-table">
                    <thead>
                        <tr>
                            <th class="checkbox-cell"><input type="checkbox" class="select-all-checkbox" id="select-all-checkbox" title="Sélectionner tout"></th>
                            <th style="display: none;">N° Commande</th>
                            <th class="tracking-cell">Code d'envoi</th>
                            <th class="customer-cell">Client</th>
                            <th class="phone-cell">Tel</th>
                            <th class="city-cell">Ville</th>
                            <th class="products-cell">Articles</th>
                            <th class="amount-cell">Montant</th>
                            <th class="status-cell">Statut</th>
                            <th class="ai-confirmation-cell">AI Status</th>
                            <th class="confirmation-cell">Confirmation</th>
                            <th class="agent-cell">Agent</th>
                            <th class="store-cell">Magasin</th>
                            <th class="date-cell">Date</th>
                            <th class="actions-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="orders-tbody">
                        <?php if ($initial_orders['success'] && !empty($initial_orders['orders'])): ?>
                            <?php foreach ($initial_orders['orders'] as $order): ?>
                                <tr data-order-id="<?php echo htmlspecialchars($order['id']); ?>">
                                    <td class="checkbox-cell">
                                        <input type="checkbox" class="order-checkbox" value="<?php echo htmlspecialchars($order['id']); ?>" onchange="handleOrderCheckboxChange(this)">
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
                                                            'new'=>'Nouveau colis','pickup_pending'=>'En cours de ramassage','collected'=>'Ramassé',
                                                            'in_transit'=>'En transit','arrived_at_agency'=>"Arrivé à l'agence",'out_for_delivery'=>'En cours de livraison',
                                                            'delivered'=>'Livrée','refused'=>'Refusée','unreachable'=>'Client injoignable',
                                                            'rescheduled'=>'Reprogrammée','returned_to_sender'=>"Retour à l'expéditeur",'cancelled'=>'Annulée',
                                                            'address_error'=>"Erreur d'adresse",'warehouse_waiting'=>'En attente au dépôt',
                                                            'delivery_failed'=>'Livraison échouée','pending'=>'En attente','processing'=>'En préparation',
                                                            'shipped'=>'Expédiée','not_submitted'=>'non soumis'
                                                        ];
                                                        echo $status_labels[$order['shipping_status']] ?? $order['shipping_status'];
                                                        ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="tracking-number">-</div>
                                                <div class="tracking-status">
                                                    <span class="status-badge <?php echo $order['shipping_status']; ?>">
                                                        <?php echo $status_labels[$order['shipping_status']] ?? $order['shipping_status']; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="customer-cell">
                                        <div class="customer-info">
                                            <div class="customer-name"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                            <div class="customer-email"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></div>
                                        </div>
                                    </td>
                                    <td class="phone-cell">
                                        <?php if (!empty($order['customer_phone'])): ?>
                                            <div class="whatsapp-container">
                                                <a href="https://wa.me/<?php echo htmlspecialchars($order['customer_phone']); ?>" class="whatsapp-btn" target="_blank" title="Contacter via WhatsApp">
                                                    <i class="fab fa-whatsapp" style="color: #FFF; font-size: 1rem;"></i>
                                                </a>
                                                <div class="phone-tooltip"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
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
                                            <div class="products-tooltip"><?php echo htmlspecialchars($order['products'] ?? ''); ?></div>
                                        </div>
                                    </td>
                                    <td class="amount-cell">
                                        <div class="amount"><?php echo number_format($order['total_amount'], 2); ?> <?php echo $order['currency']; ?></div>
                                    </td>
                                    <td class="status-cell">
                                        <span class="status-badge <?php echo $order['shipping_status']; ?>">
                                            <?php echo $status_labels[$order['shipping_status']] ?? $order['shipping_status']; ?>
                                        </span>
                                    </td>
                                    <td class="ai-confirmation-cell">
                                        <?php if ($aiService->isAIConfirmationEnabled()): ?>
                                            <span class="ai-status-badge <?php echo $order['ai_confirmation_status'] ?? 'not_started'; ?>">
                                                <?php
                                                $aiStatusLabels = [
                                                    'pending'=>'En attente AI','sent'=>'Message envoyé','delivered'=>'Message livré',
                                                    'customer_confirmed'=>'✅ Confirmé','customer_rejected'=>'❌ Refusé',
                                                    'no_response'=>'Sans réponse','escalated'=>'⬆️ Vers agent','failed'=>'Échec','not_started'=>'Non démarré'
                                                ];
                                                echo $aiStatusLabels[$order['ai_confirmation_status']] ?? ($order['ai_confirmation_status'] ?? 'Non démarré');
                                                ?>
                                                <?php if (isset($order['ai_attempt_number']) && $order['ai_attempt_number'] > 1): ?>
                                                    <small>(<?php echo $order['ai_attempt_number']; ?>e tentative)</small>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="ai-status-badge disabled">Désactivé</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="confirmation-cell">
                                        <select class="confirmation-select" id="confirmation_select_<?php echo $order['id']; ?>"
                                                data-order-id="<?php echo htmlspecialchars($order['id']); ?>"
                                                onchange="updateConfirmationStatus(this)">
                                            <?php
                                            $confirmation_options = [
                                                'new-order'=>'Nouvelle commande','confirmed'=>'Confirmée','no-answer'=>'Pas de réponse',
                                                'busy'=>'Occupé','cancelled'=>'Annulée','double-order'=>'Double commande','unreachable'=>'Injoignable'
                                            ];
                                            $current_status = $order['status'] ?? 'new-order';
                                            if ($current_status === 'new-order') {
                                                echo '<option value="new-order" selected disabled style="background-color: #cce5ff; color: #004085;">Nouvelle commande</option>';
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
                                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $selected; ?> style="<?php echo $style; ?>">
                                                <?php echo htmlspecialchars($label); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($current_status === 'confirmed' && (empty($order['tracking_number']) || $order['tracking_number'] === NULL)): ?>
                                            <div class="shipping-btn-container mt-2">
                                                <button class="btn btn-sm btn-success submit-shipping-btn" 
                                                        data-order-id="<?php echo htmlspecialchars($order['id']); ?>" 
                                                        onclick="submitToShipping(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-truck"></i> Expédier
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="agent-cell">
                                        <div class="agent-info" style="text-align: center;">
                                            <?php if (!empty($order['agent_name'])): ?>
                                                <div class="agent-name"><?php echo htmlspecialchars($order['agent_name']); ?></div>
                                                <?php if (!empty($order['assignment_status'])): ?>
                                                    <div class="assignment-status" style="font-size: 10px; color: #6b7280;">
                                                        <span class="assignment-badge <?php echo $order['assignment_status']; ?>">
                                                            <?php 
                                                            $assignment_labels = ['assigned'=>'Assigné','in_progress'=>'En cours','completed'=>'Terminé','cancelled'=>'Annulé'];
                                                            echo $assignment_labels[$order['assignment_status']] ?? $order['assignment_status'];
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div style="font-size: 11px; color: #9ca3af;">Non assigné</div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="store-cell">
                                        <div class="store-name"><?php echo !empty($order['storeName']) ? htmlspecialchars($order['storeName']) : 'N/A'; ?></div>
                                    </td>
                                    <td class="date-cell">
                                        <div><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></div>
                                        <div style="font-size: 10px; color: #9ca3af;"><?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="action-buttons-visible">
                                            <button class="btn-action btn-view" onclick="viewOrder(<?php echo $order['id']; ?>)" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-action btn-edit" onclick="editOrderData(<?php echo $order['id']; ?>)" title="Modifier">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <?php if (empty($order['agent_name'])): ?>
                                                <button class="btn-action btn-assign" onclick="assignAgent(<?php echo $order['id']; ?>)" title="Assigner un agent">
                                                    <i class="fas fa-user-plus"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-action btn-reassign" onclick="reassignAgent(<?php echo $order['id']; ?>)" title="Réassigner">
                                                    <i class="fas fa-rotate"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (($order['status'] ?? '') === 'confirmed' && empty($order['tracking_number'])): ?>
                                                <button class="btn-action btn-shipping submit-shipping-btn"
                                                        data-order-id="<?php echo $order['id']; ?>"
                                                        onclick="submitToShipping(<?php echo $order['id']; ?>)" title="Expédier">
                                                    <i class="fas fa-truck"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-delete" onclick="deleteOrder(<?php echo $order['id']; ?>)" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="15" class="no-data">Aucune commande trouvée</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div class="pagination-container" id="pagination-container">
                <div class="pagination-info" id="pagination-info">
                    Affichage de <span id="start-index">1</span> à <span id="end-index"><?= count($initial_orders['orders'] ?? []) ?></span> 
                    sur <span id="total-items"><?= $initial_orders['pagination']['total'] ?? 0 ?></span> commandes
                </div>
                <div class="pagination-controls">
                    <div class="per-page-selector" style="display: flex; align-items: center; gap: 5px;">
                        <label for="per-page-select" style="font-size: 12px;">Afficher:</label>
                        <select id="per-page-select">
                            <option value="10" <?= ($initial_orders['pagination']['per_page'] ?? 50) == 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= ($initial_orders['pagination']['per_page'] ?? 50) == 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= ($initial_orders['pagination']['per_page'] ?? 50) == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= ($initial_orders['pagination']['per_page'] ?? 50) == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>
                    <div class="page-navigation">
                        <button id="first-page" class="page-btn"><i class="fas fa-angle-double-left"></i></button>
                        <button id="prev-page" class="page-btn"><i class="fas fa-angle-left"></i></button>
                        <div id="page-numbers"></div>
                        <button id="next-page" class="page-btn"><i class="fas fa-angle-right"></i></button>
                        <button id="last-page" class="page-btn"><i class="fas fa-angle-double-right"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Status Modal -->
    <div id="status-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifier le statut de la commande</h3>
                <span class="close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="new-status">Nouveau statut:</label>
                    <select id="new-status">
                        <option value="confirmed">Confirmée</option>
                        <option value="no-answer">Pas de réponse</option>
                        <option value="busy">Occupé</option>
                        <option value="cancelled">Annulée</option>
                        <option value="double-order">Double commande</option>
                        <option value="unreachable">Injoignable</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeStatusModal()">Annuler</button>
                <button class="btn-primary" onclick="updateOrderStatus()">Mettre à jour</button>
            </div>
        </div>
    </div>

    <?php require_once('./view_orderDetailModal.html'); ?>
    <?php require_once('./orderModal.php'); ?>
    <?php require_once('./editOrderModal.php'); ?>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ═══════════════════════════════════════════════════════════
   STATE
   ═══════════════════════════════════════════════════════════ */
let currentOrderId = null;
let selectedOrders = new Set();
let currentPage = 1;
let totalPages = 1;
let perPage = 50;

/* ═══════════════════════════════════════════════════════════
   NOTIFICATIONS
   ═══════════════════════════════════════════════════════════ */
function showNotification(message, type = 'info') {
    const n = document.createElement('div');
    n.className = `notification notification-${type}`;
    n.textContent = message;
    document.body.appendChild(n);
    setTimeout(() => n.classList.add('show'), 10);
    setTimeout(() => {
        n.classList.remove('show');
        setTimeout(() => n.remove(), 300);
    }, 5000);
}

/* ═══════════════════════════════════════════════════════════
   INITIALIZATION
   ═══════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    initializeFilterListeners();
    initializePaginationListeners();
    initializeOrderCheckboxes();
});

function initializeEventListeners() {
    document.getElementById('sync-orders-btn')?.addEventListener('click', syncOrders);
    document.getElementById('select-all-checkbox')?.addEventListener('change', toggleSelectAll);
    
    document.getElementById('createCommande-btn')?.addEventListener('click', openCreateOrderModal);

    addBulkActionListeners();

    const bulkStatus = document.getElementById('bulk-status-btn');
    if (bulkStatus) bulkStatus.addEventListener('click', bulkChangeStatus);
    const bulkDelete = document.getElementById('bulk-delete-btn');
    if (bulkDelete) bulkDelete.addEventListener('click', bulkDeleteOrders);
    const bulkAssign = document.getElementById('bulk-assign-btn');
    if (bulkAssign) bulkAssign.addEventListener('click', bulkAssignAgents);
    const bulkShip = document.getElementById('bulk-ship-btn');
    if (bulkShip) bulkShip.addEventListener('click', bulkShipOrders);
}

function initializeFilterListeners() {
    const form = document.getElementById('filterForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            currentPage = 1;
            loadOrders();
        });
    }

    const resetBtn = document.getElementById('reset-filters');
    if (resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const f = document.getElementById('filterForm');
            if (f) {
                f.querySelectorAll('select').forEach(s => {
                    s.selectedIndex = 0;
                });
                f.querySelectorAll('input[type="text"], input[type="date"]').forEach(i => i.value = '');
            }
            const dr = document.getElementById('date-range');
            if (dr) dr.value = 'week';
            const cdg = document.getElementById('custom-date-group');
            if (cdg) cdg.style.display = 'none';
            currentPage = 1;
            loadOrders();
        });
    }

    const dr = document.getElementById('date-range');
    if (dr) {
        dr.addEventListener('change', function() {
            const cdg = document.getElementById('custom-date-group');
            if (cdg) cdg.style.display = this.value === 'custom' ? 'block' : 'none';
        });
    }
}

function initializePaginationListeners() {
    document.getElementById('first-page')?.addEventListener('click', () => goToPage(1));
    document.getElementById('prev-page')?.addEventListener('click', () => goToPage(currentPage - 1));
    document.getElementById('next-page')?.addEventListener('click', () => goToPage(currentPage + 1));
    document.getElementById('last-page')?.addEventListener('click', () => goToPage(totalPages));

    document.getElementById('per-page-select')?.addEventListener('change', function() {
        perPage = parseInt(this.value);
        currentPage = 1;
        loadOrders();
    });
}

document.addEventListener('click', () => {
    document.querySelectorAll('.actions-dropdown.open').forEach(el => el.classList.remove('open'));
});

function initializeOrderCheckboxes() {
    document.querySelectorAll('.order-checkbox').forEach(cb => {
        if (cb.dataset.listenerAttached) return;
        cb.dataset.listenerAttached = '1';
        cb.addEventListener('change', function() {
            handleOrderCheckboxChange(this);
        });
    });
}

/* ═══════════════════════════════════════════════════════════
   BULK ACTIONS BAR
   ═══════════════════════════════════════════════════════════ */
function addBulkActionListeners() {
    if (document.getElementById('bulk-actions')) return;
    const filtersContainer = document.querySelector('.orders-filters');
    if (!filtersContainer) return;

    const html = `
        <div id="bulk-actions" class="bulk-actions" style="display: none;">
            <span class="selected-count">0 commande(s) sélectionnée(s)</span>
            <div class="bulk-buttons">
                <button class="btn-bulk-status" id="bulk-status-btn">Changer le statut</button>
                <button class="btn-bulk-assign" id="bulk-assign-btn" style="background:#17a2b8;color:#fff;padding:8px 16px;border:none;border-radius:4px;cursor:pointer;">
                    <i class="fas fa-user-plus"></i> Assigner
                </button>
                <button class="btn-bulk-ship" id="bulk-ship-btn">
                    <i class="fas fa-truck"></i> Expédier la sélection
                </button>
                <button class="btn-bulk-delete" id="bulk-delete-btn">Supprimer sélection</button>
            </div>
        </div>
    `;
    filtersContainer.insertAdjacentHTML('afterend', html);

    document.getElementById('bulk-status-btn')?.addEventListener('click', bulkChangeStatus);
    document.getElementById('bulk-assign-btn')?.addEventListener('click', bulkAssignAgents);
    document.getElementById('bulk-ship-btn')?.addEventListener('click', bulkShipOrders);
    document.getElementById('bulk-delete-btn')?.addEventListener('click', bulkDeleteOrders);
}

function toggleSelectAll() {
    const sel = document.getElementById('select-all-checkbox').checked;
    document.querySelectorAll('.order-checkbox').forEach(cb => {
        cb.checked = sel;
        const id = parseInt(cb.value);
        if (sel) selectedOrders.add(id); else selectedOrders.delete(id);
    });
    updateBulkActionsVisibility();
    checkSelectedOrdersStatus();
}

function handleOrderCheckboxChange(cb) {
    const id = parseInt(cb.value);
    if (cb.checked) selectedOrders.add(id); else selectedOrders.delete(id);

    const all = document.querySelectorAll('.order-checkbox');
    const checked = document.querySelectorAll('.order-checkbox:checked');
    document.getElementById('select-all-checkbox').checked = all.length === checked.length && all.length > 0;

    updateBulkActionsVisibility();
    checkSelectedOrdersStatus();
}

function updateBulkActionsVisibility() {
    const bar = document.getElementById('bulk-actions');
    if (!bar) return;
    const cnt = document.querySelector('.selected-count');
    if (selectedOrders.size >= 2) {
        bar.style.display = 'flex';
        cnt.textContent = `${selectedOrders.size} commande${selectedOrders.size > 1 ? 's' : ''} sélectionnée${selectedOrders.size > 1 ? 's' : ''}`;
    } else {
        bar.style.display = 'none';
    }
}

function checkSelectedOrdersStatus() {
    const bulkShipBtn = document.getElementById('bulk-ship-btn');
    if (!bulkShipBtn) return;
    const selectedRows = document.querySelectorAll('.order-checkbox:checked');
    let allConfirmed = true;
    selectedRows.forEach(cb => {
        const row = cb.closest('tr');
        const statusSelect = row.querySelector('.confirmation-select');
        if (statusSelect && statusSelect.value !== 'confirmed') allConfirmed = false;
    });
    bulkShipBtn.style.display = allConfirmed && selectedRows.length >= 1 ? 'flex' : 'none';
}

/* ═══════════════════════════════════════════════════════════
   LOAD ORDERS
   ═══════════════════════════════════════════════════════════ */
function loadOrders() {
    const loading = document.getElementById('loading');
    if (loading) loading.style.display = 'block';

    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    formData.append('action', 'get_orders');
    formData.append('page', currentPage);
    formData.append('per_page', perPage);

    fetch('', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (loading) loading.style.display = 'none';
            if (data.success) {
                updateOrdersTable(data.orders);
                updateStats(data.dashboard_stats);
                updatePagination(data.pagination);
                selectedOrders.clear();
                updateBulkActionsVisibility();
            } else {
                showNotification(data.message || 'Erreur', 'error');
            }
        })
        .catch(err => {
            if (loading) loading.style.display = 'none';
            showNotification('Erreur de connexion', 'error');
            console.error(err);
        });
}

/* ═══════════════════════════════════════════════════════════
   UPDATE TABLE (with AI status column — fixes column shift)
   ═══════════════════════════════════════════════════════════ */
function updateOrdersTable(orders) {
    const tbody = document.getElementById('orders-tbody');
    if (!orders || orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="15" class="no-data">Aucune commande trouvée</td></tr>';
        return;
    }

    const statusLabels = {
        'new':'Nouveau colis','pickup_pending':'En cours de ramassage','collected':'Ramassé',
        'in_transit':'En transit','arrived_at_agency':"Arrivé à l'agence",'out_for_delivery':'En cours de livraison',
        'delivered':'Livrée','refused':'Refusée','unreachable':'Client injoignable','rescheduled':'Reprogrammée',
        'returned_to_sender':"Retour à l'expéditeur",'cancelled':'Annulée','address_error':"Erreur d'adresse",
        'warehouse_waiting':'En attente au dépôt','delivery_failed':'Livraison échouée','pending':'En attente',
        'processing':'En préparation','shipped':'Expédiée','not_submitted':'non soumis'
    };

    const confirmationOptions = {
        'confirmed':'Confirmée','no-answer':'Pas de réponse','busy':'Occupé','cancelled':'Annulée',
        'double-order':'Double commande','unreachable':'Injoignable'
    };

    const confirmationStyles = {
        'new-order':'background-color: #cce5ff; color: #004085;',
        'confirmed':'background-color: #d4edda; color: #155724;',
        'no-answer':'background-color: #fff3cd; color: #856404;',
        'busy':'background-color: #ffe8cc; color: #804d00;',
        'cancelled':'background-color: #f8d7da; color: #721c24;',
        'double-order':'background-color: #e2e3e5; color: #383d41;',
        'unreachable':'background-color: #d1ecf1; color: #0c5460;'
    };

    const assignmentLabels = {
        'assigned':'Assigné','in_progress':'En cours','completed':'Terminé','cancelled':'Annulé'
    };

    const aiLabels = {
        'pending':'En attente AI','sent':'Message envoyé','delivered':'Message livré',
        'customer_confirmed':'✅ Confirmé','customer_rejected':'❌ Refusé','no_response':'Sans réponse',
        'escalated':'⬆️ Vers agent','failed':'Échec','not_started':'Non démarré'
    };

    tbody.innerHTML = orders.map(order => {
        const cur = order.status || 'new-order';
        let statusOpts = '';
        if (cur === 'new-order') {
            statusOpts += `<option value="new-order" selected disabled style="${confirmationStyles['new-order']}">Nouvelle commande</option>`;
        }
        statusOpts += Object.entries(confirmationOptions).map(([v, l]) => {
            const sel = (cur === v) ? 'selected' : '';
            const st = confirmationStyles[v] || 'background-color: #f8f9fa; color: #212529;';
            return `<option value="${v}" ${sel} style="${st}">${l}</option>`;
        }).join('');

        const aiStatus = order.ai_confirmation_status || 'not_started';
        const aiAttempt = order.ai_attempt_number || 0;

        return `
            <tr data-order-id="${order.id}">
                <td class="checkbox-cell">
                    <input type="checkbox" class="order-checkbox" value="${order.id}" onchange="handleOrderCheckboxChange(this)">
                </td>
                <td style="display:none;"><strong>${order.order_number || ''}</strong></td>
                <td class="tracking-cell">
                    <div class="tracking-info">
                        <div class="tracking-number">${order.tracking_number || '-'}</div>
                        <div class="tracking-status">
                            <span class="status-badge ${order.shipping_status}">
                                ${statusLabels[order.shipping_status] || order.shipping_status || 'En attente'}
                            </span>
                        </div>
                    </div>
                </td>
                <td class="customer-cell">
                    <div class="customer-info">
                        <div class="customer-name">${order.customer_name || ''}</div>
                        <div class="customer-email">${order.customer_email || ''}</div>
                    </div>
                </td>
                <td class="phone-cell">
                    ${order.customer_phone
                        ? `<div class="whatsapp-container">
                               <a href="https://wa.me/${String(order.customer_phone).replace(/[^0-9]/g,'')}" class="whatsapp-btn" target="_blank">
                                   <i class="fab fa-whatsapp" style="color:#FFF;"></i>
                               </a>
                               <div class="phone-tooltip">${order.customer_phone}</div>
                           </div>`
                        : '<span style="color:#9ca3af;">-</span>'}
                </td>
                <td class="city-cell">
                    <div style="font-size:11px;color:#374151;">${order.customer_ville || '-'}</div>
                </td>
                <td class="products-cell">
                    <div class="products-container">
                        <div class="product-count">${order.item_count || 0}</div>
                        <div class="products-tooltip">${(order.products || '').replace(/"/g,'&quot;')}</div>
                    </div>
                </td>
                <td class="amount-cell">
                    <div class="amount">${parseFloat(order.total_amount || 0).toFixed(2)} ${order.currency || ''}</div>
                </td>
                <td class="status-cell">
                    <span class="status-badge ${order.shipping_status || 'pending'}">
                        ${statusLabels[order.shipping_status] || order.shipping_status || 'En attente'}
                    </span>
                </td>
                <td class="ai-confirmation-cell">
                    ${order.ai_enabled
                        ? `<span class="ai-status-badge ${aiStatus}">
                                ${aiLabels[aiStatus] || aiStatus}
                                ${aiAttempt > 1 ? `<small>(${aiAttempt}e)</small>` : ''}
                           </span>`
                        : `<span class="ai-status-badge disabled">Désactivé</span>`}
                </td>
                <td class="confirmation-cell">
                    <select class="confirmation-select" data-order-id="${order.id}" onchange="updateConfirmationStatus(this)">
                        ${statusOpts}
                    </select>
                    ${cur === 'confirmed' && (!order.tracking_number || String(order.tracking_number).trim() === '')
                        ? `<div class="shipping-btn-container mt-2">
                              <button class="btn btn-sm btn-success submit-shipping-btn" data-order-id="${order.id}" onclick="submitToShipping(${order.id})">
                                  <i class="fas fa-truck"></i> Expédier
                              </button>
                           </div>`
                        : ''}
                </td>
                <td class="agent-cell">
                    <div class="agent-info" style="text-align: center;">
                        ${order.agent_name
                            ? `<div class="agent-name">${order.agent_name}</div>
                               ${order.assignment_status
                                   ? `<div class="assignment-status" style="font-size:10px;color:#6b7280;">
                                          <span class="assignment-badge ${order.assignment_status}">
                                              ${assignmentLabels[order.assignment_status] || order.assignment_status}
                                          </span>
                                      </div>`
                                   : ''}`
                            : `<div style="font-size:11px;color:#9ca3af;">Non assigné</div>`}
                    </div>
                </td>
                <td class="store-cell">
                    <div class="store-name">${order.storeName || 'N/A'}</div>
                </td>
                <td class="date-cell">
                    <div>${formatDate(order.created_at)}</div>
                    <div style="font-size:10px;color:#9ca3af;">${formatTime(order.created_at)}</div>
                </td>
                <td class="actions-cell">
                    <div class="action-buttons-visible">
                        <button class="btn-action btn-view" onclick="viewOrder(${order.id})" title="Voir">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-action btn-edit" onclick="editOrderData(${order.id})" title="Modifier">
                            <i class="fas fa-pen"></i>
                        </button>
                        ${!order.agent_name
                            ? `<button class="btn-action btn-assign" onclick="assignAgent(${order.id})" title="Assigner">
                                <i class="fas fa-user-plus"></i>
                            </button>`
                            : `<button class="btn-action btn-reassign" onclick="reassignAgent(${order.id})" title="Réassigner">
                                <i class="fas fa-rotate"></i>
                            </button>`}
                        ${cur === 'confirmed' && (!order.tracking_number || String(order.tracking_number).trim() === '')
                            ? `<button class="btn-action btn-shipping submit-shipping-btn"
                                    data-order-id="${order.id}"
                                    onclick="submitToShipping(${order.id})" title="Expédier">
                                <i class="fas fa-truck"></i>
                            </button>`
                            : ''}
                        <button class="btn-action btn-delete" onclick="deleteOrder(${order.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    document.getElementById('select-all-checkbox').checked = false;
    selectedOrders.clear();
    initializeOrderCheckboxes();
}

function formatDate(d) { if (!d) return ''; const dt = new Date(d);
    return `${String(dt.getDate()).padStart(2,'0')}/${String(dt.getMonth()+1).padStart(2,'0')}/${dt.getFullYear()}`; }
function formatTime(d) { if (!d) return ''; const dt = new Date(d);
    return `${String(dt.getHours()).padStart(2,'0')}:${String(dt.getMinutes()).padStart(2,'0')}`; }

/* ═══════════════════════════════════════════════════════════
   STATS
   ═══════════════════════════════════════════════════════════ */
function updateStats(stats) {
    if (!stats) return;
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('total-orders-value', stats.total_orders || 0);
    set('confirmed-orders-value', stats.confirmed?.total || 0);
    set('confirmed-by-us', stats.confirmed?.by_us || 0);
    set('confirmed-by-shipping', stats.confirmed?.by_shipping || 0);
    set('cancelled-orders-value', stats.cancelled?.total || 0);
    set('cancelled-by-us', stats.cancelled?.by_us || 0);
    set('cancelled-by-shipping', stats.cancelled?.by_shipping || 0);
    const rev = document.getElementById('revenue-value');
    if (rev) {
        const amount = Number(stats.revenue?.amount || 0).toFixed(2);
        const cur = stats.revenue?.currency || 'MAD';
        rev.innerHTML = `${amount} <span class="currency">${cur}</span>`;
    }
}

/* ═══════════════════════════════════════════════════════════
   PAGINATION
   ═══════════════════════════════════════════════════════════ */
function updatePagination(p) {
    if (!p) return;
    totalPages = p.total_pages;
    currentPage = p.page;
    perPage = p.per_page;

    const startIndex = p.total === 0 ? 0 : (currentPage - 1) * perPage + 1;
    const endIndex = Math.min(currentPage * perPage, p.total);

    document.getElementById('start-index').textContent = startIndex;
    document.getElementById('end-index').textContent = endIndex;
    document.getElementById('total-items').textContent = p.total;
    document.getElementById('per-page-select').value = perPage;

    document.getElementById('first-page').disabled = currentPage === 1;
    document.getElementById('prev-page').disabled = currentPage === 1;
    document.getElementById('next-page').disabled = currentPage === totalPages;
    document.getElementById('last-page').disabled = currentPage === totalPages;

    const container = document.getElementById('page-numbers');
    container.innerHTML = '';
    if (totalPages <= 1) return;

    const addBtn = (n) => {
        const b = document.createElement('button');
        b.className = `page-number ${n === currentPage ? 'active' : ''}`;
        b.textContent = n;
        b.addEventListener('click', () => goToPage(n));
        container.appendChild(b);
    };
    const addEllipsis = () => {
        const s = document.createElement('span');
        s.className = 'page-ellipsis';
        s.textContent = '...';
        container.appendChild(s);
    };

    addBtn(1);
    let startP = Math.max(2, currentPage - 2);
    let endP = Math.min(totalPages - 1, currentPage + 2);
    if (currentPage <= 4) endP = Math.min(6, totalPages - 1);
    if (currentPage >= totalPages - 3) startP = Math.max(2, totalPages - 5);
    if (startP > 2) addEllipsis();
    for (let i = startP; i <= endP; i++) addBtn(i);
    if (endP < totalPages - 1) addEllipsis();
    if (totalPages > 1) addBtn(totalPages);
}

function goToPage(page) {
    if (page < 1 || page > totalPages || page === currentPage) return;
    currentPage = page;
    loadOrders();
    document.getElementById('orders-table')?.scrollIntoView({ behavior: 'smooth' });
}

/* ═══════════════════════════════════════════════════════════
   STATUS UPDATE (single)
   ═══════════════════════════════════════════════════════════ */
function updateConfirmationStatus(selectEl) {
    const orderId = selectEl.getAttribute('data-order-id');
    const newStatus = selectEl.value;
    const original = selectEl.selectedIndex;
    selectEl.disabled = true;

    const fd = new FormData();
    fd.append('action', 'update_order_status');
    fd.append('order_id', orderId);
    fd.append('status', newStatus);

    fetch('./controllers/update_or_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            selectEl.disabled = false;
            if (data.success) {
                updateSelectStyle(selectEl, newStatus);
                showNotification('Statut mis à jour', 'success');
                if (newStatus === 'confirmed') showShippingSubmissionOption(orderId);
                else removeShippingSubmissionOption(orderId);
            } else {
                selectEl.selectedIndex = original;
                showNotification(data.message || 'Erreur', 'error');
            }
        })
        .catch(() => {
            selectEl.disabled = false;
            selectEl.selectedIndex = original;
            showNotification('Erreur de connexion', 'error');
        });
}

function updateSelectStyle(selectEl, status) {
    for (let opt of selectEl.options) opt.style = '';
    switch(status) {
        case 'confirmed': selectEl.style.backgroundColor = '#d4edda'; selectEl.style.color = '#155724'; break;
        case 'no-answer': selectEl.style.backgroundColor = '#fff3cd'; selectEl.style.color = '#856404'; break;
        case 'busy': selectEl.style.backgroundColor = '#ffe8cc'; selectEl.style.color = '#804d00'; break;
        case 'cancelled': selectEl.style.backgroundColor = '#f8d7da'; selectEl.style.color = '#721c24'; break;
        case 'double-order': selectEl.style.backgroundColor = '#e2e3e5'; selectEl.style.color = '#383d41'; break;
        case 'unreachable': selectEl.style.backgroundColor = '#d1ecf1'; selectEl.style.color = '#0c5460'; break;
        default: selectEl.style.backgroundColor = '#f8f9fa'; selectEl.style.color = '#212529';
    }
}

function showShippingSubmissionOption(orderId) {
    const cell = document.querySelector(`select[data-order-id="${orderId}"]`)?.closest('td');
    if (!cell) return;
    let c = cell.querySelector('.shipping-btn-container');
    if (!c) {
        c = document.createElement('div');
        c.className = 'shipping-btn-container mt-2';
        cell.appendChild(c);
    }
    c.innerHTML = `<button class="btn btn-sm btn-success submit-shipping-btn" data-order-id="${orderId}" onclick="submitToShipping(${orderId})">
        <i class="fas fa-truck"></i> Expédier
    </button>`;
}

function removeShippingSubmissionOption(orderId) {
    document.querySelector(`select[data-order-id="${orderId}"]`)?.closest('td')?.querySelector('.shipping-btn-container')?.remove();
}

/* ═══════════════════════════════════════════════════════════
   STATUS MODAL (bulk)
   ═══════════════════════════════════════════════════════════ */
function bulkChangeStatus() {
    if (selectedOrders.size < 2) { showNotification('Sélectionnez au moins 2 commandes', 'warning'); return; }
    currentOrderId = 'bulk';
    document.getElementById('new-status').value = 'no-answer';
    document.getElementById('status-modal').style.display = 'block';
}

function closeStatusModal() {
    document.getElementById('status-modal').style.display = 'none';
    currentOrderId = null;
}

function updateOrderStatus() {
    if (!currentOrderId) return;
    const newStatus = document.getElementById('new-status').value;
    const fd = new FormData();
    if (currentOrderId === 'bulk') {
        fd.append('action', 'update_orders_status_bulk');
        fd.append('order_ids', JSON.stringify(Array.from(selectedOrders)));
        fd.append('status', newStatus);
    } else {
        fd.append('action', 'update_order_status');
        fd.append('order_id', currentOrderId);
        fd.append('status', newStatus);
    }
    fetch('./controllers/update_or_status.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'OK', 'success');
                closeStatusModal();
                selectedOrders.clear();
                updateBulkActionsVisibility();
                loadOrders();
            } else {
                showNotification(data.message || 'Erreur', 'error');
            }
        })
        .catch(() => showNotification('Erreur de connexion', 'error'));
}

/* ═══════════════════════════════════════════════════════════
   SUBMIT TO SHIPPING
   ═══════════════════════════════════════════════════════════ */
function submitToShipping(orderId) {
    const btn = document.querySelector(`button[data-order-id="${orderId}"].submit-shipping-btn`)
              || document.querySelector(`button[onclick*="submitToShipping(${orderId})"]`);
    const orig = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...'; }

    showNotification('Envoi de la commande au transporteur...', 'info');

    const fd = new FormData();
    fd.append('action', 'submit_to_shipping');
    fd.append('order_id', orderId);

    fetch('./controllers/upTo_shipping_api.php', { method: 'POST', body: fd })
        .then(async r => {
            const raw = await r.text();
            try { return JSON.parse(raw); }
            catch { throw new Error('Réponse invalide: ' + raw.substring(0, 300)); }
        })
        .then(data => {
            if (data.success) {
                let msg = 'Commande envoyée au transporteur';
                if (data.shipping_notification?.tracking_number) {
                    msg += `\nSuivi: ${data.shipping_notification.tracking_number}`;
                    updateTrackingNumberInUI(orderId, data.shipping_notification.tracking_number);
                }
                showNotification(msg, 'success');
                loadOrders();
            } else {
                showNotification(data.message || 'Erreur d\'envoi', 'error');
            }
        })
        .catch(err => showNotification(err.message || 'Erreur', 'error'))
        .finally(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = orig || '<i class="fas fa-truck"></i> Expédier'; }
        });
}

function updateTrackingNumberInUI(orderId, trackingNumber) {
    const tn = document.querySelector(`tr[data-order-id="${orderId}"] .tracking-number`);
    if (tn) tn.textContent = trackingNumber;
}

/* ═══════════════════════════════════════════════════════════
   SYNC
   ═══════════════════════════════════════════════════════════ */
function syncOrders() {
    const btn = document.getElementById('sync-orders-btn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="spin" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/><path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/></svg> Synchronisation...';
    fetch('./controllers/sync_orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=sync_orders'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { showNotification(data.message, 'success'); loadOrders(); }
        else showNotification(data.message, 'error');
    })
    .catch(() => showNotification('Erreur de synchronisation', 'error'))
    .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

/* ═══════════════════════════════════════════════════════════
   DELETE
   ═══════════════════════════════════════════════════════════ */
function deleteOrder(orderId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_order');
    fd.append('order_id', orderId);
    fetch('./controllers/delete_ordersApi.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) { showNotification(data.message, 'success'); loadOrders(); }
            else showNotification(data.message, 'error');
        })
        .catch(() => showNotification('Erreur lors de la suppression', 'error'));
}

function bulkDeleteOrders() {
    if (selectedOrders.size < 2) { showNotification('Sélectionnez au moins 2 commandes', 'warning'); return; }
    if (!confirm(`Êtes-vous sûr de vouloir supprimer ${selectedOrders.size} commandes ?`)) return;
    const fd = new FormData();
    fd.append('action', 'delete_orders');
    fd.append('order_ids', JSON.stringify(Array.from(selectedOrders)));
    fetch('./controllers/delete_ordersApi.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                selectedOrders.clear();
                updateBulkActionsVisibility();
                loadOrders();
            } else showNotification(data.message, 'error');
        })
        .catch(() => showNotification('Erreur lors de la suppression', 'error'));
}

/* ═══════════════════════════════════════════════════════════
   BULK SHIP
   ═══════════════════════════════════════════════════════════ */
function bulkShipOrders() {
    if (selectedOrders.size < 1) { showNotification('Sélectionnez au moins 1 commande', 'warning'); return; }
    if (!confirm(`Êtes-vous sûr de vouloir expédier ${selectedOrders.size} commande(s) ?`)) return;

    const bulkShipBtn = document.getElementById('bulk-ship-btn');
    if (bulkShipBtn) {
        bulkShipBtn.disabled = true;
        bulkShipBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
    }

    showNotification(`Envoi de ${selectedOrders.size} commande(s)...`, 'info');

    const fd = new FormData();
    fd.append('action', 'bulk_submit_to_shipping');
    fd.append('order_ids', JSON.stringify(Array.from(selectedOrders)));

    fetch('./controllers/upTo_shipping_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                let msg = `${data.success_count} commande(s) envoyée(s)`;
                if (data.failed_count > 0) msg += `, ${data.failed_count} échec(s)`;
                showNotification(msg, 'success');
                loadOrders();
            } else showNotification(data.message || 'Erreur', 'error');
        })
        .catch(() => showNotification('Erreur de connexion', 'error'))
        .finally(() => {
            if (bulkShipBtn) {
                bulkShipBtn.disabled = false;
                bulkShipBtn.innerHTML = '<i class="fas fa-truck"></i> Expédier la sélection';
            }
            selectedOrders.clear();
            updateBulkActionsVisibility();
        });
}

/* ═══════════════════════════════════════════════════════════
   BULK ASSIGN (stub — connect to your bulk assign API)
   ═══════════════════════════════════════════════════════════ */
function bulkAssignAgents() {
    if (selectedOrders.size < 1) { showNotification('Sélectionnez au moins 1 commande', 'warning'); return; }
    showNotification('Assignation en masse — connectez votre API bulk_assign_orders.php', 'info');
}

/* ═══════════════════════════════════════════════════════════
   AGENT ACTIONS
   ═══════════════════════════════════════════════════════════ */
function checkAIAssignmentStatus(orderId) {
    return new Promise((resolve, reject) => {
        const fd = new FormData();
        fd.append('action', 'check_ai_status');
        fd.append('order_id', orderId);
        fetch('./controllers/assign_agentApi.php', { method: 'POST', body: fd })
            .then(r => r.json()).then(resolve).catch(reject);
    });
}

function assignAgent(orderId) {
    currentOrderId = orderId;
    showNotification('Vérification du statut AI...', 'info');
    checkAIAssignmentStatus(orderId)
        .then(statusData => {
            if (!statusData.success) {
                showNotification(statusData.message || 'Erreur de vérification', 'error');
                return;
            }
            if (!statusData.can_assign) {
                let msg = 'Cette commande est en cours de confirmation par AI.';
                if (statusData.ai_status) {
                    msg += `\nStatut: ${statusData.ai_status.status}`;
                    if (statusData.ai_status.attempts_remaining) msg += `\nTentatives restantes: ${statusData.ai_status.attempts_remaining}`;
                }
                if (statusData.can_override && confirm(msg + '\n\nForcer l\'assignation ?')) {
                    forceAssignAgent(orderId);
                    return;
                }
                showNotification(msg, 'warning');
                return;
            }
            proceedWithAgentAssignment(orderId);
        })
        .catch(() => showNotification('Erreur de vérification AI', 'error'));
}

function forceAssignAgent(orderId) {
    const fd = new FormData();
    fd.append('action', 'force_assign_after_ai');
    fd.append('order_id', orderId);
    fetch('./controllers/assign_agentApi.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification('AI process escalated', 'success');
                proceedWithAgentAssignment(orderId);
            } else showNotification(data.message || 'Erreur', 'error');
        })
        .catch(() => showNotification('Erreur de connexion', 'error'));
}

function proceedWithAgentAssignment(orderId) {
    showNotification('Chargement des agents disponibles...', 'info');
    fetch('./controllers/get_agentsForAssign.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_available_agents'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) showAgentAssignmentModal(data.agents, false);
        else showNotification('Erreur lors du chargement des agents', 'error');
    })
    .catch(() => showNotification('Erreur de connexion', 'error'));
}

function reassignAgent(orderId) {
    currentOrderId = orderId;
    showNotification('Chargement des informations...', 'info');
    Promise.all([
        fetch('./controllers/re_agent_assignmentApi.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=get_current_assignment&order_id=${orderId}`
        }).then(r => r.json()),
        fetch('./controllers/get_agentsForAssign.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=get_available_agents'
        }).then(r => r.json())
    ])
    .then(([assignmentData, agentsData]) => {
        if (agentsData.success) showAgentAssignmentModal(agentsData.agents, true, assignmentData.current_assignment);
        else showNotification('Erreur lors du chargement des agents', 'error');
    })
    .catch(() => showNotification('Erreur de connexion', 'error'));
}

function showAgentAssignmentModal(agents, isReassignment = false, currentAssignment = null) {
    const html = `
        <div id="agent-assignment-modal" class="modal" style="display:block;">
            <div class="modal-content" style="max-width:500px;">
                <div class="modal-header">
                    <h3>${isReassignment ? 'Réassigner un agent' : 'Assigner un agent'}</h3>
                    <span class="close" onclick="closeAgentModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="agent-select">Sélectionner un agent:</label>
                        <select id="agent-select" class="form-control">
                            <option value="">-- Choisir un agent --</option>
                            ${agents.map(a => `
                                <option value="${a.id}" ${currentAssignment && currentAssignment.agent_id == a.id ? 'selected' : ''}
                                    data-phone="${a.phone || ''}" data-email="${a.email || ''}">
                                    ${a.name} - ${a.phone || 'N/A'}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    <div class="form-group" style="margin-top:15px;">
                        <label for="assignment-notes">Notes (optionnel):</label>
                        <textarea id="assignment-notes" class="form-control" rows="3" placeholder="Notes pour l'agent..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="assignment-priority">Priorité:</label>
                        <select id="assignment-priority" class="form-control">
                            <option value="0">Basse</option>
                            <option value="1" selected>Moyenne</option>
                            <option value="2">Haute</option>
                            <option value="3">Urgente</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary" onclick="closeAgentModal()">Annuler</button>
                    <button class="btn-primary" onclick="confirmAgentAssignment(${isReassignment})">
                        ${isReassignment ? 'Réassigner' : 'Assigner'}
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', html);
}

function closeAgentModal() {
    document.getElementById('agent-assignment-modal')?.remove();
    currentOrderId = null;
}

function confirmAgentAssignment(isReassignment = false) {
    const agentId = document.getElementById('agent-select').value;
    const notes = document.getElementById('assignment-notes').value;
    const priority = document.getElementById('assignment-priority').value;
    if (!agentId) { showNotification('Sélectionnez un agent', 'warning'); return; }
    if (!currentOrderId) { showNotification('Erreur: ID manquant', 'error'); return; }

    const btn = document.querySelector('#agent-assignment-modal .btn-primary');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';

    const fd = new FormData();
    fd.append('action', isReassignment ? 'reassign_agent' : 'assign_agent');
    fd.append('order_id', currentOrderId);
    fd.append('agent_id', agentId);
    fd.append('notes', notes);
    fd.append('priority', priority);

    fetch('./controllers/assign_agentApi.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showNotification(isReassignment ? 'Agent réassigné' : 'Agent assigné', 'success');
                closeAgentModal();
                loadOrders();
            } else {
                showNotification(data.message || 'Erreur', 'error');
                btn.disabled = false;
                btn.innerHTML = orig;
            }
        })
        .catch(() => {
            showNotification('Erreur de connexion', 'error');
            btn.disabled = false;
            btn.innerHTML = orig;
        });
}

/* ═══════════════════════════════════════════════════════════
   PLACEHOLDERS — connect to your existing modals
   ═══════════════════════════════════════════════════════════ */

function viewOrder(id) {
    if (typeof viewThisOrder === 'function') viewThisOrder(id);
}

function editOrderData(orderId) {
    
    if (typeof editOrder === 'function') {
        editOrder(orderId);
        return;
    }
}

/* ═══════════════════════════════════════════════════════════
   MODAL OUTSIDE-CLICK CLOSE
   ═══════════════════════════════════════════════════════════ */
window.addEventListener('click', function(e) {
    const sm = document.getElementById('status-modal');
    if (e.target === sm) closeStatusModal();
    const om = document.getElementById('order-modal');
    if (e.target === om) closeOrderModal?.();
});

function closeOrderModal() {
    const m = document.getElementById('orderModal');
    if (m) { m.classList.remove('show'); m.classList.add('hide'); }
}

/* ═══════════════════════════════════════════════════════════
   ALERT HELPERS
   ═══════════════════════════════════════════════════════════ */
function closeSubscriptionAlert(button) {
    const alert = button.closest('.subscription-alert');
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 300);
}
function dismissSubscriptionAlert(button, days) {
    const alert = button.closest('.subscription-alert');
    alert.style.opacity = '0';
    setTimeout(() => alert.remove(), 300);
}
</script>
</body>
</html>