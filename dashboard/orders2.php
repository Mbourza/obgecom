<?php
if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

$db = DB::getInstance();

if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../lg'); 
} 

// Get current user ID
$user_id = getCurrentUserId($db);

if (!$user_id) {
    Redirect::to('../lg');
}

$user = $db->getThisQuery("SELECT id, `name`, `role` FROM users WHERE id = ?", [$user_id]);

$aiService = new AIConfirmationService($db, $user_id);
// Vérification de l'abonnement
$subscription = new Subscription($user_id);
$subscriptionStatus = $subscription->getSubscriptionStatus();
$subscriptionAlerts = $subscription->getSubscriptionAlerts();

// Initialize onboarding
$onboarding = new Onboarding($user_id);

$currentPage = basename($_SERVER['PHP_SELF']);
if (!$onboarding->isOnboardingCompleted() && $currentPage !== 'onboarding') {
    Redirect::to('./onboarding');
}

// Vérifier l'accès aux fonctionnalités
$canAccessFeatures = $subscription->canAccessFeatures();

// Si l'abonnement est expiré ou inexistant, limiter l'accès
$limitedAccess = !$canAccessFeatures;

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_orders':
            echo getOrders($db, $user_id);
            exit;
    }
}

if (isset($_GET['logout'])) {
    logout();
}

if (isset($_GET['process_ai_confirmations'])) {
    $aiService->checkAndProcessPendingAttempts();
}
// Get orders with filters
function getOrders($db, $user_id) {
    // Use global $aiService or create new instance
    global $aiService;
    
    if ($aiService === null) {
        $aiService = new AIConfirmationService($db, $user_id);
    }
    
    try {
        $filters = ["o.user_id = ?"];
        $params = [$user_id];
        
        // AI Confirmation Status Filter
        if (!empty($_POST['ai_confirmation_status'])) {
            switch ($_POST['ai_confirmation_status']) {
                case 'pending_ai':
                    $filters[] = "EXISTS (
                        SELECT 1 FROM ai_confirmation_attempts a 
                        WHERE a.order_id = o.id 
                        AND a.user_id = o.user_id 
                        AND a.status IN ('pending', 'sent')
                        AND a.escalated_to_agent = 0
                    )";
                    break;
                case 'escalated':
                    $filters[] = "EXISTS (
                        SELECT 1 FROM ai_confirmation_attempts a 
                        WHERE a.order_id = o.id 
                        AND a.user_id = o.user_id 
                        AND a.escalated_to_agent = 1
                    )";
                    break;
                case 'confirmed_by_ai':
                    $filters[] = "EXISTS (
                        SELECT 1 FROM ai_confirmation_attempts a 
                        WHERE a.order_id = o.id 
                        AND a.user_id = o.user_id 
                        AND a.status = 'customer_confirmed'
                    )";
                    break;
                case 'rejected_by_ai':
                    $filters[] = "EXISTS (
                        SELECT 1 FROM ai_confirmation_attempts a 
                        WHERE a.order_id = o.id 
                        AND a.user_id = o.user_id 
                        AND a.status = 'customer_rejected'
                    )";
                    break;
            }
        }
        
        // Confirmation status filter (the main status field)
        if (!empty($_POST['confirmation_status'])) {
            $filters[] = "o.status = ?";
            $params[] = $_POST['confirmation_status'];
        }
        
        // Shipping status filter (separate field)
        if (!empty($_POST['shipping_status'])) {
            $filters[] = "o.shipping_status = ?";
            $params[] = $_POST['shipping_status'];
        }
        
        // Store filter
        if (!empty($_POST['store_id'])) {
            $filters[] = "o.store_id = ?";
            $params[] = $_POST['store_id'];
        }
        
        // Agent filter
        if (!empty($_POST['agent_id'])) {
            $filters[] = "aoa.agent_id = ?";
            $params[] = $_POST['agent_id'];
        }
        
        // Assignment status filter
        if (!empty($_POST['assignment_status'])) {
            if ($_POST['assignment_status'] === 'unassigned') {
                $filters[] = "aoa.agent_id IS NULL";
            } else {
                $filters[] = "aoa.status = ?";
                $params[] = $_POST['assignment_status'];
            }
        }
        
        // Date range filter
        if (!empty($_POST['date_range'])) {
            switch ($_POST['date_range']) {
                case 'today':
                    $filters[] = "DATE(o.created_at) = CURDATE()";
                    break;
                case 'yesterday':
                    $filters[] = "DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                    break;
                case 'week':
                    $filters[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $filters[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'custom':
                    // Handle custom date range
                    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
                        $filters[] = "DATE(o.created_at) BETWEEN ? AND ?";
                        $params[] = $_POST['start_date'];
                        $params[] = $_POST['end_date'];
                    } elseif (!empty($_POST['start_date'])) {
                        $filters[] = "DATE(o.created_at) >= ?";
                        $params[] = $_POST['start_date'];
                    } elseif (!empty($_POST['end_date'])) {
                        $filters[] = "DATE(o.created_at) <= ?";
                        $params[] = $_POST['end_date'];
                    }
                    break;
            }
        }
        
        // Amount filters
        if (!empty($_POST['min_amount'])) {
            $filters[] = "o.total_amount >= ?";
            $params[] = floatval($_POST['min_amount']);
        }
        
        if (!empty($_POST['max_amount'])) {
            $filters[] = "o.total_amount <= ?";
            $params[] = floatval($_POST['max_amount']);
        }

        $where_clause = "WHERE " . implode(" AND ", $filters);

        // Get total count
        $count_query = "SELECT COUNT(DISTINCT o.id) as total_count
                FROM orders o 
                LEFT JOIN order_items oi ON o.id = oi.order_id 
                LEFT JOIN stores s ON o.store_id = s.id
                LEFT JOIN agent_order_assignments aoa ON o.id = aoa.order_id AND aoa.user_id = o.user_id
                LEFT JOIN agents a ON aoa.agent_id = a.id
                {$where_clause}";
                
        $count_result = $db->getThisQuery($count_query, $params);
        $total_count = !empty($count_result) ? (int)($count_result[0]['total_count'] ?? 0) : 0;
        
        // Calculate pagination
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 50;
        $offset = ($page - 1) * $per_page;
        
        // Get orders with pagination
        $query = "SELECT o.*, 
                        COUNT(oi.id) as item_count,
                        GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products,
                        s.storeName,
                        a.name as agent_name,
                        a.id as agent_id,
                        aoa.status as assignment_status,
                        aoa.assigned_at,
                        aoa.confirmed_at,
                        aoa.notes as assignment_notes,
                        aoa.priority_score
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
        
        // Ensure orders is always an array
        if (!$orders) {
            $orders = [];
        }

        // Get AI status for all orders
        $orderIds = array_column($orders, 'id');
        $aiStatusMap = [];
        
        if (!empty($orderIds)) {
            try {
                $aiStatusMap = $aiService->getAIConfirmationStatusForOrders($orderIds);
            } catch (Exception $e) {
                error_log("Error getting AI status: " . $e->getMessage());
                $aiStatusMap = [];
            }
        }
        
        // Add AI info to each order
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

        // Get stats
        $stats_query = "SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            
            -- Count orders confirmed by us (store status)
            COUNT(DISTINCT CASE WHEN o.status = 'confirmed' THEN o.id END) as confirmed_by_us,
            
            -- Count orders confirmed by shipping company
            COUNT(DISTINCT CASE WHEN o.shipping_status = 'delivered' THEN o.id END) as confirmed_by_shipping,
            
            -- Count orders cancelled by us (store status)
            COUNT(DISTINCT CASE WHEN o.status = 'cancelled' THEN o.id END) as cancelled_by_us,
            
            -- Count orders cancelled by shipping company
            COUNT(DISTINCT CASE WHEN o.shipping_status IN ('returned_to_sender', 'cancelled') THEN o.id END) as cancelled_by_shipping,
            
            -- Total revenue from delivered orders
            COALESCE(SUM(CASE WHEN o.shipping_status = 'delivered' THEN o.total_amount ELSE 0 END), 0) as total_revenue,
            
            -- Currency
            (SELECT o2.currency FROM orders o2 WHERE o2.user_id = ? GROUP BY o2.currency ORDER BY COUNT(*) DESC LIMIT 1) as currency
            
            FROM orders o 
            LEFT JOIN agent_order_assignments aoa ON o.id = aoa.order_id AND aoa.user_id = o.user_id
            {$where_clause}";

        // Add user_id parameter for currency subquery
        $stats_params = array_merge($params, [$user_id]);
        $stats_result = $db->getThisQuery($stats_query, $stats_params);
        
        // Default stats if empty
        if (empty($stats_result)) {
            $stats_result = [[
                'total_orders' => 0,
                'confirmed_by_us' => 0,
                'confirmed_by_shipping' => 0,
                'cancelled_by_us' => 0,
                'cancelled_by_shipping' => 0,
                'total_revenue' => 0,
                'currency' => 'MAD'
            ]];
        }
        
        $stats = $stats_result[0];
        
        // Calculate dashboard stats
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
        
        // Prepare final response
        $response = [
            'success' => true,
            'orders' => $orders, // Always an array
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
                'total' => (int)$total_count,
                'page' => (int)$page,
                'per_page' => (int)$per_page,
                'total_pages' => (int)ceil($total_count / $per_page)
            ]
        ];
        
        // Debug log for empty orders
        if (empty($orders)) {
            error_log("No orders found for user: $user_id, filters applied: " . json_encode($_POST));
        }
        
        return json_encode($response);
        
    } catch (Exception $e) {
        error_log("Error in getOrders: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        return json_encode([
            'success' => false,
            'message' => 'Erreur lors de la récupération des commandes: ' . $e->getMessage(),
            'orders' => [], // Always include empty orders array
            'stats' => [
                'total_orders' => 0,
                'confirmed_by_us' => 0,
                'confirmed_by_shipping' => 0,
                'cancelled_by_us' => 0,
                'cancelled_by_shipping' => 0,
                'total_revenue' => 0,
                'currency' => 'MAD'
            ],
            'dashboard_stats' => [
                'total_orders' => 0,
                'confirmed' => ['total' => 0, 'by_us' => 0, 'by_shipping' => 0],
                'cancelled' => ['total' => 0, 'by_us' => 0, 'by_shipping' => 0],
                'revenue' => ['amount' => 0, 'currency' => 'MAD']
            ],
            'pagination' => [
                'total' => 0,
                'page' => 1,
                'per_page' => 50,
                'total_pages' => 0
            ]
        ]);
    }
}

// Get initial data for page load
$initial_orders = json_decode(getOrders($db, $user_id), true); 

function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    // This can be username, email, or phone
    $identifier = $_SESSION['user']['username'];

    // Prefer explicit ID if available
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Try to match identifier against username, email, or phone
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

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
    <!--scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>

        .btn-bulk-confirm {

            padding: 5px 10px; 
            border: 1px solid #ccc;
        }

        .btn-bulk-confirm:hover {
            background-color: #969696;
            color: #faf8ff;
            border-color: #faf8ff;
        }

        #import-sheets-btn {
            padding: 0 .8em;
            padding: 0 .8em;
            border: solid 1px #198754;
            background: none;
            color: #198754;
            border-radius: .2em;
            line-height: 3em;
        }

        #import-sheets-btn:hover {
            background-color: #198754;
            color: #FFF;
        }

        #createCommande-btn {
            padding: 0 .8em;
            padding: 0 .8em;
            border: solid 1px #444655;
            background: none;
            color: #444655;
            border-radius: .2em;
            line-height: 3em;
        }

        #createCommande-btn:hover {
            background-color: #444655;
            color: #FFF;
        }

        .stats-cards {
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 2px !important;
            font-size: 8px !important;
            font-weight: 300;
            color: #fff;
        }

        /* Main status colors */
        .status-badge.new { background: #9ca3af; } /* Gris */
        .status-badge.pickup_pending { background: #fff3cd; color: #856404; } /* Jaune clair */
        .status-badge.collected { background: #cce5ff; color: #004085; } /* Bleu clair */
        .status-badge.in_transit { background: #bee3f8; color: #0c5460; } /* Bleu ciel */
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

        /* Internal platform statuses */
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.processing { background: #d1ecf1; color: #0c5460; }
        .status-badge.shipped { background: #bee3f8; color: #0c5460; }
        .status-badge.not_submitted { background: #e0e0e0; color: #555; } /* For not yet submitted */

    </style>

</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'orders';
    require_once('../assets/sidebar.php'); ?>
    <!-- Main Content -->
    <main class="main-content" id="mainContent">

        <?php if (!empty($subscriptionAlerts)): ?>
            
            <div class="subscription-alerts-container">
                <?php foreach ($subscriptionAlerts as $alert): ?>
                    <div class="subscription-alert alert-<?php echo $alert['type']; ?>" data-priority="<?php echo $alert['priority']; ?>">
                        <div class="alert-icon">
                            <?php if ($alert['type'] === 'danger'): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                                </svg>
                            <?php elseif ($alert['type'] === 'warning'): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                </svg>
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="alert-content">
                            <h4><?php echo htmlspecialchars($alert['title']); ?></h4>
                            <p><?php echo htmlspecialchars($alert['message']); ?></p>
                        </div>
                        <div class="alert-actions">
                            <a href="<?php echo htmlspecialchars($alert['action_url']); ?>" class="alert-btn">
                                <?php echo htmlspecialchars($alert['action_text']); ?>
                            </a>
                            <?php if ($alert['priority'] === 'medium'): ?>
                                <button class="alert-dismiss" onclick="dismissSubscriptionAlert(this, 7)">Ignorer 7 jours</button>
                            <?php endif; ?>
                        </div>
                        <button class="alert-close" onclick="closeSubscriptionAlert(this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <style>
                .subscription-alerts-container {
                    margin-bottom: 24px;
                }
                
                .subscription-alert {
                    display: flex;
                    align-items: center;
                    padding: 16px;
                    border-radius: 12px;
                    margin-bottom: 12px;
                    position: relative;
                    gap: 16px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .alert-danger {
                    background: linear-gradient(135deg, #fee 0%, #fdd 100%);
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                }
                
                .alert-warning {
                    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
                    border: 1px solid #ffeaa7;
                    color: #856404;
                }
                
                .alert-info {
                    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
                    border: 1px solid #b8daff;
                    color: #0c5460;
                }
                
                .alert-icon {
                    flex-shrink: 0;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .alert-danger .alert-icon {
                    background: #f8d7da;
                }
                
                .alert-warning .alert-icon {
                    background: #fff3cd;
                }
                
                .alert-info .alert-icon {
                    background: #d1ecf1;
                }
                
                .alert-content {
                    flex: 1;
                }
                
                .alert-content h4 {
                    margin: 0 0 4px 0;
                    font-weight: 600;
                }
                
                .alert-content p {
                    margin: 0;
                    font-size: 14px;
                }
                
                .alert-actions {
                    display: flex;
                    gap: 12px;
                    align-items: center;
                }
                
                .alert-btn {
                    background: #007bff;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-size: 14px;
                    font-weight: 500;
                    transition: all 0.3s ease;
                }
                
                .alert-btn:hover {
                    background: #0056b3;
                    transform: translateY(-1px);
                }
                
                .alert-dismiss {
                    background: transparent;
                    border: 1px solid currentColor;
                    color: inherit;
                    padding: 8px 12px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 12px;
                    transition: all 0.3s ease;
                }
                
                .alert-dismiss:hover {
                    background: rgba(255,255,255,0.2);
                }
                
                .alert-close {
                    background: none;
                    border: none;
                    color: inherit;
                    cursor: pointer;
                    padding: 4px;
                    border-radius: 4px;
                    opacity: 0.7;
                }
                
                .alert-close:hover {
                    opacity: 1;
                    background: rgba(0,0,0,0.1);
                }
                
                @media (max-width: 768px) {
                    .subscription-alert {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 12px;
                    }
                    
                    .alert-actions {
                        width: 100%;
                        justify-content: space-between;
                    }
                }
            </style>

            <script>
                function closeSubscriptionAlert(button) {
                    const alert = button.closest('.subscription-alert');
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                    
                    // Stocker en localStorage pour ne pas réafficher pendant 24h
                    const alertType = alert.classList.contains('alert-danger') ? 'danger' : 
                                    alert.classList.contains('alert-warning') ? 'warning' : 'info';
                    localStorage.setItem(`subscriptionAlert_${alertType}_dismissed`, Date.now());
                }
                
                function dismissSubscriptionAlert(button, days) {
                    const alert = button.closest('.subscription-alert');
                    const alertType = alert.classList.contains('alert-warning') ? 'warning' : 'info';
                    
                    // Stocker en localStorage pour ne pas réafficher pendant X jours
                    const dismissUntil = Date.now() + (days * 24 * 60 * 60 * 1000);
                    localStorage.setItem(`subscriptionAlert_${alertType}_dismissed`, dismissUntil);
                    
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }
                
                // Vérifier si les alertes ont été ignorées
                document.addEventListener('DOMContentLoaded', function() {
                    const alerts = document.querySelectorAll('.subscription-alert');
                    alerts.forEach(alert => {
                        const alertType = alert.classList.contains('alert-danger') ? 'danger' : 
                                        alert.classList.contains('alert-warning') ? 'warning' : 'info';
                        
                        const dismissedUntil = localStorage.getItem(`subscriptionAlert_${alertType}_dismissed`);
                        if (dismissedUntil && Date.now() < parseInt(dismissedUntil)) {
                            alert.remove();
                        }
                    });
                });
            </script>

        <?php endif; ?>

        <!-- Top Navigation Bar -->
        <nav class="top-navbar">
            
            <div class="search-bar">

            </div>
            
            <div class="top-navbar-right">
                <style>
                    /* Base styles for the notification system */
                    .notifications {
                        position: relative;
                        display: inline-block;
                    }

                    .notification-btn {
                        position: relative;
                        background: none;
                        border: none;
                        cursor: pointer;
                        padding: 8px;
                        border-radius: 6px;
                        transition: background-color 0.2s;
                        color: #6b7280;
                    }

                    .notification-btn:hover {
                        background-color: #f3f4f6;
                        color: #374151;
                    }

                    .notification-badge {
                        position: absolute;
                        top: 2px;
                        right: 2px;
                        background-color: #ef4444;
                        color: white;
                        border-radius: 50%;
                        width: 18px;
                        height: 18px;
                        font-size: 10px;
                        font-weight: bold;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-width: 18px;
                        padding: 0 2px;
                        animation: pulse 2s infinite;
                    }

                    .notification-badge.hidden {
                        display: none;
                    }

                    @keyframes pulse {
                        0% {
                            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
                        }
                        70% {
                            box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
                        }
                        100% {
                            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
                        }
                    }

                    .notification-dropdown {
                        position: absolute;
                        top: 100%;
                        right: 0;
                        background: white;
                        border: 1px solid #e5e7eb;
                        border-radius: 8px;
                        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                        width: 320px;
                        max-height: 400px;
                        overflow-y: auto;
                        z-index: 1000;
                        display: none;
                    }

                    .notification-dropdown.show {
                        display: block;
                        animation: slideIn 0.2s ease-out;
                    }

                    @keyframes slideIn {
                        from {
                            opacity: 0;
                            transform: translateY(-10px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    .notification-header {
                        padding: 12px 16px;
                        border-bottom: 1px solid #e5e7eb;
                        font-weight: 600;
                        color: #374151;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }

                    .mark-all-read {
                        background: none;
                        border: none;
                        color: #3b82f6;
                        font-size: 12px;
                        cursor: pointer;
                        text-decoration: underline;
                    }

                    .notification-list {
                        max-height: 320px;
                        overflow-y: auto;
                    }

                    .notification-item {
                        padding: 12px 16px;
                        border-bottom: 1px solid #f3f4f6;
                        cursor: pointer;
                        transition: background-color 0.2s;
                    }

                    .notification-item:hover {
                        background-color: #f9fafb;
                    }

                    .notification-item.unread {
                        background-color: #eff6ff;
                        border-left: 3px solid #3b82f6;
                    }

                    .notification-item:last-child {
                        border-bottom: none;
                    }

                    .notification-content {
                        display: flex;
                        align-items: flex-start;
                        gap: 12px;
                    }

                    .notification-icon {
                        width: 32px;
                        height: 32px;
                        border-radius: 50%;
                        background-color: #3b82f6;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        font-size: 14px;
                        flex-shrink: 0;
                    }

                    .notification-details {
                        flex: 1;
                    }

                    .notification-title {
                        font-weight: 600;
                        color: #374151;
                        margin: 0 0 4px 0;
                        font-size: 14px;
                    }

                    .notification-message {
                        color: #6b7280;
                        font-size: 13px;
                        line-height: 1.4;
                        margin: 0 0 4px 0;
                    }

                    .notification-time {
                        color: #9ca3af;
                        font-size: 11px;
                    }

                    .no-notifications {
                        padding: 40px 16px;
                        text-align: center;
                        color: #9ca3af;
                    }

                    .loading {
                        padding: 20px;
                        text-align: center;
                        color: #6b7280;
                    }
                </style>
                
                <div class="notifications">
                    <button class="notification-btn" data-tooltip="Notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2z"/>
                            <path d="M8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4.002 4.002 0 0 0-3.203-3.92L8 1.918zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5.002 5.002 0 0 1 13 6c0 .88.32 4.2 1.22 6z"/>
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

                <script src="../assets/js/notifications.js" ></script>
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
            
            <!-- Orders Filters -->
            <?php require_once('filtersArea.php'); ?>
            
            <!-- Loading indicator -->
            <div class="loading" id="loading" style="display: none;">
                <p>Chargement...</p>
            </div>

            <style>
                @media (max-width: 768px) {
                    .stats-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }

                @media (max-width: 480px) {
                    .stats-grid {
                        grid-template-columns: 1fr;
                    }
                }

                .stats-container {
                    margin-bottom: 20px;
                }

                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(15%, 1fr));
                    gap: 10px;
                    margin-bottom: 15px;
                }

                .stats-header {
                    margin-bottom: 25px;
                }
                
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 20px;
                    margin-bottom: 30px;
                }
                
                .stat-card {
                    background: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                    padding: 20px;
                    transition: transform 0.3s ease, box-shadow 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }
                
                .stat-card:hover {
                    transform: translateY(-5px);
                    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
                }
                
                .stat-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 5px;
                    height: 100%;
                }
                
                .stat-card.total::before {
                    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                }
                
                .stat-card.confirmed::before {
                    background: linear-gradient(135deg, var(--success-color), #20c997);
                }
                
                .stat-card.cancelled::before {
                    background: linear-gradient(135deg, var(--danger-color), #e83e8c);
                }
                
                .stat-card.revenue::before {
                    background: linear-gradient(135deg, #fd7e14, #ffcc00);
                }
                
                .stat-icon {
                    width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 15px;
                    font-size: 20px;
                }
                
                .stat-card.total .stat-icon {
                    background-color: rgba(102, 126, 234, 0.15);
                    color: var(--primary-color);
                }
                
                .stat-card.confirmed .stat-icon {
                    background-color: rgba(40, 167, 69, 0.15);
                    color: var(--success-color);
                }
                
                .stat-card.cancelled .stat-icon {
                    background-color: rgba(220, 53, 69, 0.15);
                    color: var(--danger-color);
                }
                
                .stat-card.revenue .stat-icon {
                    background-color: rgba(253, 126, 20, 0.15);
                    color: #fd7e14;
                }
                
                .stat-value {
                    font-size: 28px;
                    font-weight: 700;
                    margin-bottom: 5px;
                    color: #2c3e50;
                }
                
                .stat-title {
                    font-size: 14px;
                    color: #6c757d;
                    font-weight: 500;
                    margin-bottom: 1px;
                }
                
                .stat-breakdown {
                    border-top: 1px solid #e9ecef;
                    padding-top: 15px;
                    margin-top: 15px;
                    display: flex;
                    column-gap: 1em;
                }
                
                .breakdown-item {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                    font-size: 13px;
                }
                
                .breakdown-label {
                    color: #6c757d;
                }
                
                .breakdown-value {
                    font-weight: 600;
                    color: #f7ebff;
                    background-color: #9c80fd;
                    padding: 0 .4em;
                    margin: 0 .2em;
                    border-radius: 50%;
                }
                
                .breakdown-agent {
                    color: var(--info-color);
                }
                
                .breakdown-shipping {
                    color: var(--success-color);
                }
                
                .breakdown-store {
                    color: var(--warning-color);
                }
                
                .currency {
                    font-size: 16px;
                    font-weight: 600;
                    color: #6c757d;
                }
                
                /* Responsive adjustments */
                @media (max-width: 1200px) {
                    .stats-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }
                
                @media (max-width: 768px) {
                    .stats-grid {
                        grid-template-columns: 1fr;
                    }
                    
                    .stat-value {
                        font-size: 24px;
                    }
                }
                
                /* Loading animation */
                .loading-pulse {
                    display: inline-block;
                    width: 80%;
                    height: 24px;
                    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
                    background-size: 200% 100%;
                    animation: loading 1.5s infinite;
                    border-radius: 4px;
                }
                
                @keyframes loading {
                    0% {
                        background-position: 200% 0;
                    }
                    100% {
                        background-position: -200% 0;
                    }
                }
            </style>
            
            <!-- Orders Statistics -->
            <div class="stats-container">
                <div class="stats-grid">
                    <!-- Total Orders Card -->
                    <div class="stat-card total">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart" style="color: #9c80fd;"></i>
                        </div>
                        <div class="stat-value" id="total-orders-value">
                            <?php echo number_format($initial_orders['dashboard_stats']['total_orders'] ?? 0); ?>
                        </div>
                        <div class="stat-title">Total des Commandes</div>
                    </div>
                    
                    <!-- Confirmed Orders Card -->
                    <div class="stat-card confirmed">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value" id="confirmed-orders-value">
                            <?php echo number_format($initial_orders['dashboard_stats']['confirmed']['total'] ?? 0); ?>
                        </div>
                        <div class="stat-title">Commandes Confirmées</div>
                        
                        <div class="stat-breakdown">
                            <div class="breakdown-item">
                                <span class="breakdown-label breakdown-agent">Store </span>
                                <span class="breakdown-value" id="confirmed-by-us">
                                    <?php echo number_format($initial_orders['dashboard_stats']['confirmed']['by_us'] ?? 0); ?>
                                </span>
                            </div>
                            <div class="breakdown-item">
                                <span class="breakdown-label breakdown-shipping">Livraison </span>
                                <span class="breakdown-value" id="confirmed-by-shipping">
                                    <?php echo number_format($initial_orders['dashboard_stats']['confirmed']['by_shipping'] ?? 0); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cancelled Orders Card -->
                    <div class="stat-card cancelled">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-value" id="cancelled-orders-value">
                            <?php echo number_format($initial_orders['dashboard_stats']['cancelled']['total'] ?? 0); ?>
                        </div>
                        <div class="stat-title">Commandes Annulées</div>
                        
                        <div class="stat-breakdown">
                            <div class="breakdown-item">
                                <span class="breakdown-label breakdown-store">Store </span>
                                <span class="breakdown-value" id="cancelled-by-us">
                                    <?php echo number_format($initial_orders['dashboard_stats']['cancelled']['by_us'] ?? 0); ?>
                                </span>
                            </div>
                            <div class="breakdown-item">
                                <span class="breakdown-label breakdown-shipping">Livraison </span>
                                <span class="breakdown-value" id="cancelled-by-shipping">
                                    <?php echo number_format($initial_orders['dashboard_stats']['cancelled']['by_shipping'] ?? 0); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Revenue Card -->
                    <div class="stat-card revenue">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value" id="revenue-value">
                            <?php echo number_format($initial_orders['dashboard_stats']['revenue']['amount'] ?? 0, 2); ?> 
                            <span class="currency"><?php echo $initial_orders['dashboard_stats']['revenue']['currency'] ?? 'MAD'; ?></span>
                        </div>
                        <div class="stat-title">Chiffre d'Affaires</div>
                    </div>
                </div>
            </div>
            <!-- Orders Table -->
            <div class="orders-table-container">

                <style>

                    .btn-primary
                    {
                        background: linear-gradient(135deg, #9c80fd 0%, #764ba2 100%) !important;
                    }

                    .table-container {
                        background: white;
                        border-radius: 12px;
                        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                        overflow: hidden;
                        overflow-x: auto;
                        position: relative;
                    }

                    .orders-table {
                        width: 100%;
                        border-collapse: collapse;
                        min-width: 1000px;
                        font-size: 13px;
                    }

                    .orders-table th {
                        background: linear-gradient(135deg, #9c80fd 0%, #764ba2 100%);
                        color: white;
                        padding: 12px 8px;
                        text-align: left;
                        font-weight: 600;
                        font-size: 11px;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        position: sticky;
                        top: 0;
                        z-index: 10;
                        text-align: center;
                    }

                    .orders-table td {
                        padding: 8px;
                        border-bottom: 1px solid #f1f3f4;
                        vertical-align: middle;
                    }

                    .orders-table tbody tr:hover {
                        background-color: #f8f9ff;
                        transform: translateY(-1px);
                        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                        transition: all 0.2s ease;
                    }

                    /* Compact column widths */
                    .checkbox-cell { width: 40px; }
                    .tracking-cell { width: 120px; }
                    .customer-cell { width: 140px; }
                    .phone-cell { width: 50px; }
                    .city-cell { width: 80px; }
                    .products-cell { width: 80px; }
                    .amount-cell { width: 90px; }
                    .status-cell { width: 100px; }
                    .confirmation-cell { width: 120px; }
                    .agent-cell {width: 100px}
                    .store-cell { width: 80px; }
                    .date-cell { width: 90px; }
                    .actions-cell { width: 60px; }

                    .agent-name{
                        
                        font-size: 11px; 
                        color: #374151; 
                        font-weight: 500;
                        margin: 0 auto;
                        text-align: center
                    }

                    /* Tracking cell styles */
                    .tracking-info {
                        display: flex;
                        flex-direction: column;
                        gap: 2px;
                    }

                    .tracking-number {
                        font-weight: 600;
                        font-size: 12px;
                        color: #2563eb;
                    }

                    .tracking-status {
                        font-size: 10px;
                        color: #6b7280;
                        text-transform: uppercase;
                        letter-spacing: 0.3px;
                    }

                    /* Customer info styles */
                    .customer-info {
                        display: flex;
                        flex-direction: column;
                        gap: 2px;
                    }

                    .customer-name {
                        font-weight: 600;
                        font-size: 12px;
                        color: #111827;
                    }

                    .customer-email {
                        font-size: 10px;
                        color: #6b7280;
                        overflow: hidden;
                        text-overflow: ellipsis;
                        white-space: nowrap;
                        max-width: 130px;
                    }

                    /* WhatsApp phone styles */
                    .whatsapp-container {
                        position: relative;
                        display: inline-block;
                    }

                    .whatsapp-btn {
                        background: #25d366;
                        color: white;
                        border: none;
                        border-radius: 50%;
                        width: 28px;
                        height: 28px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                        transition: all 0.2s ease;
                        text-decoration: none;
                        font-size: 14px;
                    }

                    .whatsapp-btn:hover {
                        background: #128c7e;
                        transform: scale(1.1);
                    }

                    .phone-tooltip {
                        position: absolute;
                        bottom: 100%;
                        left: 50%;
                        transform: translateX(-50%);
                        background: #333;
                        color: white;
                        padding: 6px 10px;
                        border-radius: 6px;
                        font-size: 11px;
                        white-space: nowrap;
                        opacity: 0;
                        visibility: hidden;
                        transition: all 0.2s ease;
                        z-index: 1000;
                    }

                    .phone-tooltip::after {
                        content: '';
                        position: absolute;
                        top: 100%;
                        left: 50%;
                        transform: translateX(-50%);
                        border: 4px solid transparent;
                        border-top-color: #333;
                    }

                    .whatsapp-container:hover .phone-tooltip {
                        opacity: 1;
                        visibility: visible;
                    }

                    /* Products info styles */
                    .products-container {
                        position: relative;
                        display: inline-block;
                    }

                    .product-count {
                        background-color: #9c80fd !important;
                        color: #374151;
                        padding: 4px 8px;
                        border-radius: 12px;
                        font-size: 11px;
                        font-weight: 600;
                        text-align: center;
                        cursor: pointer;
                        transition: all 0.2s ease;
                    }

                    .product-count:hover {
                        background: #d1d5db;
                        transform: scale(1.05);
                    }

                    .products-tooltip {
                        position: absolute;
                        bottom: 100%;
                        left: 50%;
                        transform: translateX(-50%);
                        background: #333;
                        color: white;
                        padding: 8px 12px;
                        border-radius: 6px;
                        font-size: 11px;
                        white-space: nowrap;
                        opacity: 0;
                        visibility: hidden;
                        transition: all 0.2s ease;
                        z-index: 1000;
                    }

                    .products-tooltip::after {
                        content: '';
                        position: absolute;
                        top: 100%;
                        left: 50%;
                        transform: translateX(-50%);
                        border: 4px solid transparent;
                        border-top-color: #333;
                    }

                    .products-container:hover .products-tooltip {
                        opacity: 1;
                        visibility: visible;
                    }

                    /* Status badge styles */
                    .status-badge {
                        padding: 4px 8px;
                        border-radius: 12px;
                        font-size: 8px;
                        font-weight: 300;
                        text-transform: uppercase;
                        letter-spacing: 0.3px;
                    }

                    .status-badge.pending {
                        background: #fef3c7;
                        color: #92400e;
                    }

                    .status-badge.processing {
                        background: #dbeafe;
                        color: #1e40af;
                    }

                    .status-badge.shipped {
                        background: #d1fae5;
                        color: #065f46;
                    }

                    .status-badge.delivered {
                        background: #dcfce7;
                        color: #166534;
                    }

                    .status-badge.cancelled {
                        background: #fee2e2;
                        color: #991b1b;
                    }

                    /* Confirmation select styles */
                    .confirmation-select {
                        padding: 4px 6px;
                        border-radius: 12px;
                        border: 1px solid #d1d5db;
                        font-size: 10px;
                        font-weight: 500;
                        text-transform: uppercase;
                        cursor: pointer;
                        width: 100%;
                        background: white;
                    }

                    .confirmation-select:focus {
                        outline: none;
                        border-color: #2563eb;
                        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
                    }

                    /* Amount styles */
                    .amount {
                        font-weight: 600;
                        color: #059669;
                        font-size: 12px;
                    }

                    /* Date styles */
                    .date-cell {
                        font-size: 11px;
                        color: #6b7280;
                    }

                    /* Store name styles */
                    .store-name {
                        font-size: 11px;
                        color: #374151;
                        font-weight: 500;
                    }

                    /* Actions dropdown */
                    .actions-dropdown {
                        position: relative;
                        display: inline-block;
                    }

                    .actions-trigger {
                        background: #f3f4f6;
                        border: 1px solid #d1d5db;
                        border-radius: 6px;
                        padding: 4px 8px;
                        cursor: pointer;
                        font-size: 16px;
                        color: #6b7280;
                        transition: all 0.2s ease;
                        width: 32px;
                        height: 32px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }

                    .actions-trigger:hover {
                        background: #e5e7eb;
                        border-color: #9ca3af;
                        transform: scale(1.05);
                        z-index: 10;
                    }

                    .actions-menu {
                        position: absolute;
                        top: 100%;
                        right: 0;
                        background: white;
                        border: 1px solid #d1d5db;
                        border-radius: 8px;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        min-width: 140px;
                        z-index: 1000;
                        opacity: 0;
                        visibility: hidden;
                        transform: translateY(-10px);
                        transition: all 0.2s ease;
                    }

                    .actions-dropdown.open .actions-menu {
                        opacity: 1;
                        visibility: visible;
                        transform: translateY(0);
                    }

                    .actions-menu button {
                        width: 100%;
                        padding: 8px 12px;
                        border: none;
                        background: none;
                        text-align: left;
                        cursor: pointer;
                        font-size: 12px;
                        color: #374151;
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        transition: background-color 0.2s ease;
                    }

                    .actions-menu button:hover {
                        background-color: #f3f4f6;
                    }

                    .actions-menu button:first-child {
                        border-radius: 8px 8px 0 0;
                    }

                    .actions-menu button:last-child {
                        border-radius: 0 0 8px 8px;
                    }

                    .actions-menu button.delete-btn:hover {
                        background-color: #fee2e2;
                        color: #991b1b;
                    }

                    .actions-menu svg {
                        flex-shrink: 0;
                    }

                    .no-data {
                        text-align: center;
                        padding: 40px;
                        color: #6b7280;
                        font-style: italic;
                    }

                    /* Checkbox styles */
                    .checkbox-cell input[type="checkbox"] {
                        width: 16px;
                        height: 16px;
                        accent-color: #2563eb;
                        cursor: pointer;
                    }

                    .btn-bulk-ship {
                        background-color: #28a745;
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 4px;
                        font-size: 14px;
                        font-weight: 500;
                        cursor: pointer;
                        transition: all 0.2s ease;
                        display: flex;
                        align-items: center;
                        gap: 6px;
                    }

                    .btn-bulk-ship:hover {
                        background-color: #218838;
                        transform: translateY(-1px);
                    }

                    .btn-bulk-ship:disabled {
                        background-color: #6c757d;
                        cursor: not-allowed;
                    }

                    /* Responsive */
                    @media (max-width: 768px) {
                        .table-container {
                            margin: 0 -20px;
                            border-radius: 0;
                        }
                        
                        .orders-table {
                            font-size: 11px;
                        }
                        
                        .orders-table th,
                        .orders-table td {
                            padding: 6px 4px;
                        }
                    }

                    /* Pagination Styles */
                    .pagination-container {
                        background: white;
                        padding: 15px;
                        border-radius: 8px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        margin-top: 20px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }

                    .pagination-info {
                        font-size: 14px;
                        color: #6c757d;
                    }

                    .page-btn {
                        background: #f8f9fa;
                        border: 1px solid #dee2e6;
                        border-radius: 4px;
                        padding: 6px 10px;
                        cursor: pointer;
                        font-size: 12px;
                        transition: all 0.2s ease;
                    }

                    .page-btn:hover:not(:disabled) {
                        background: #e9ecef;
                        border-color: #ced4da;
                    }

                    .page-btn:disabled {
                        opacity: 0.5;
                        cursor: not-allowed;
                    }

                    .page-number {
                        min-width: 32px;
                        height: 32px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        background: #f8f9fa;
                        border: 1px solid #dee2e6;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 14px;
                        transition: all 0.2s ease;
                    }

                    .page-number:hover {
                        background: #e9ecef;
                        border-color: #ced4da;
                    }

                    .page-number.active {
                        background: #007bff;
                        color: white;
                        border-color: #007bff;
                    }

                    .page-ellipsis {
                        padding: 0 8px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }

                    #per-page-select {
                        padding: 6px 10px;
                        border: 1px solid #ced4da;
                        border-radius: 4px;
                        font-size: 14px;
                    }

                    @media (max-width: 768px) {
                        .pagination-container {
                            flex-direction: column;
                            gap: 15px;
                        }
                        
                        .pagination-controls {
                            flex-direction: column;
                            gap: 10px;
                        }
                    }
                </style>

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
                            <th class="ai-confirmation-cell">AI Status</th>
                            <th class="confirmation-cell">Confirmation</th>
                            <th class="agent-cell" style="text-align: center">Agent</th>
                            <th class="store-cell">Magasin</th>
                            <th class="date-cell">Date</th>
                            <th class="actions-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="orders-tbody" style="position: relative; z-index: 2">
                        <?php if ($initial_orders['success'] && !empty($initial_orders['orders'])): ?>
                            <?php foreach ($initial_orders['orders'] as $order): ?>
                                <tr data-order-id="<?php echo htmlspecialchars($order['id']); ?>">
                                    <td class="checkbox-cell">
                                        <input type="checkbox" class="order-checkbox" 
                                        value="<?php echo htmlspecialchars($order['id']); ?>" onchange="handleOrderCheckboxChange(this)">
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
                                                        
                                                            // Internal/Platform Specific
                                                            'pending'              => 'En attente',
                                                            'processing'           => 'En préparation',
                                                            'shipped'              => 'Expédiée', // Alias for in_transit
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
                                                        
                                                            // Internal/Platform Specific
                                                            'pending'              => 'En attente',
                                                            'processing'           => 'En préparation',
                                                            'shipped'              => 'Expédiée', // Alias for in_transit
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
                                    <td class="phone-cell">
                                        <?php if (!empty($order['customer_phone'])): ?>
                                            <div class="whatsapp-container">
                                                <a href="https://wa.me/<?php echo htmlspecialchars($order['customer_phone']); ?>" 
                                                class="whatsapp-btn" target="_blank" title="Contacter via WhatsApp">
                                                    <i class="fab fa-whatsapp" style="color: #FFF; font-size: 1rem;"></i>
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
                                                "new"                => "Nouveau colis",
                                                "pickup_pending"     => "En cours de ramassage",
                                                "collected"          => "Ramassé",
                                                "in_transit"         => "En transit",
                                                "arrived_at_agency"  => "Arrivé à l'agence",
                                                "out_for_delivery"   => "En cours de livraison",
                                                "delivered"          => "Livrée",
                                                "refused"            => "Refusée",
                                                "unreachable"        => "Client injoignable",
                                                "rescheduled"        => "Reprogrammée",
                                                "returned_to_sender" => "Retour à l'expéditeur",
                                                "cancelled"          => "Annulée",
                                                "address_error"      => "Erreur d'adresse",
                                                "warehouse_waiting"  => "En attente au dépôt",
                                                "delivery_failed"    => "Livraison échouée",
                                            
                                                // Internal/Platform Specific
                                                "pending"            => "En attente",
                                                "processing"         => "En préparation",
                                                "shipped"            => "Expédiée", // Alias for in_transit
                                                "not_submitted"      => "non soumis"
                                            ];
                                            
                                            echo $status_labels[$order['shipping_status']] ?? $order['shipping_status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td class="ai-confirmation-cell">
                                        <?php if ($aiService->isAIConfirmationEnabled()): ?>
                                            <?php if (isset($order['ai_confirmation_status'])): ?>
                                                <span class="ai-status-badge <?php echo $order['ai_confirmation_status']; ?>">
                                                    <?php
                                                    $aiStatusLabels = [
                                                        'pending' => 'En attente AI',
                                                        'sent' => 'Message envoyé',
                                                        'delivered' => 'Message livré',
                                                        'customer_confirmed' => '✅ Confirmé',
                                                        'customer_rejected' => '❌ Refusé',
                                                        'no_response' => 'Sans réponse',
                                                        'escalated' => '⬆️ Vers agent',
                                                        'failed' => 'Échec'
                                                    ];
                                                    echo $aiStatusLabels[$order['ai_confirmation_status']] ?? $order['ai_confirmation_status'];
                                                    ?>
                                                    <?php if (isset($order['ai_attempt_number']) && $order['ai_attempt_number'] > 1): ?>
                                                        <small>(<?php echo $order['ai_attempt_number']; ?>e tentative)</small>
                                                    <?php endif; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="ai-status-badge not_started">Non démarré</span>
                                            <?php endif; ?>
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
                                                'new-order' => 'Nouvelle commande',
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
                                                <div class="agent-name">
                                                    <?php echo htmlspecialchars($order['agent_name']); ?>
                                                </div>
                                                <?php if (!empty($order['assignment_status'])): ?>
                                                    <div class="assignment-status" style="font-size: 10px; color: #6b7280;">
                                                        <span class="assignment-badge <?php echo $order['assignment_status']; ?>">
                                                            <?php 
                                                            $assignment_labels = [
                                                                'assigned' => 'Assigné',
                                                                'in_progress' => 'En cours',
                                                                'completed' => 'Terminé',
                                                                'cancelled' => 'Annulé'
                                                            ];
                                                            echo $assignment_labels[$order['assignment_status']] ?? $order['assignment_status'];
                                                            ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div style="font-size: 11px; color: #9ca3af;">
                                                    Non assigné
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="store-cell">
                                        <div class="store-name">
                                            <?php echo !empty($order['storeName']) ? htmlspecialchars($order['storeName']) : 'N/A'; ?>
                                        </div>
                                    </td>
                                    <td class="date-cell">
                                        <div><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></div>
                                        <div style="font-size: 10px; color: #9ca3af;"><?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                                    </td>
                                    <td class="actions-cell">
                                        <div class="actions-dropdown">
                                            <div class="actions-trigger">⋮</div>
                                            <div class="actions-menu">
                                                <button onclick="viewOrder(<?php echo $order['id']; ?>)" title="Voir détails">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                                    </svg>
                                                    Voir
                                                </button>
                                                <button onclick="editOrderData(<?php echo $order['id']; ?>)" title="Modifier statut">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L10.5 8.207l-3-3L12.146.146zM11.207 9.5L7 13.707V10.5a.5.5 0 0 1 .5-.5h3.707zM10.5 11.207L6.293 7 .854 12.439a.5.5 0 0 0-.146.353V15.5a.5.5 0 0 0 .5.5h2.707a.5.5 0 0 0 .354-.146L10.5 11.207z"/>
                                                    </svg>
                                                    Modifier
                                                </button>
                                                <?php if (empty($order['agent_name'])): ?>
                                                    <button onclick="assignAgent(<?php echo $order['id']; ?>)" title="Assigner un agent">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                                            <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                                                        </svg>
                                                        Assigner
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="reassignAgent(<?php echo $order['id']; ?>)" title="Réassigner">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                                            <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                                            <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                                        </svg>
                                                        Réassigner
                                                    </button>
                                                <?php endif; ?>
                                                <button class="delete-btn" onclick="deleteOrder(<?php echo $order['id']; ?>)" title="Supprimer">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                                    </svg>
                                                    Supprimer
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="15" class="no-data">Aucune commande trouvée</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <script>
                    document.querySelectorAll('.actions-trigger').forEach(trigger => {
                        trigger.addEventListener('click', function (e) {
                            e.stopPropagation(); // Prevent bubbling
                            const dropdown = this.closest('.actions-dropdown');

                            // Close others
                            document.querySelectorAll('.actions-dropdown.open').forEach(el => {
                                if (el !== dropdown) el.classList.remove('open');
                            });

                            dropdown.classList.toggle('open');
                        });
                    });

                    // Close on outside click
                    document.addEventListener('click', () => {
                        document.querySelectorAll('.actions-dropdown.open').forEach(el => {
                            el.classList.remove('open');
                        });
                    });
                </script>

            </div>
            <!-- Pagination Controls -->
            <div class="pagination-container" id="pagination-container" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding: 10px; background: white; border-radius: 8px;">
                <?php if ($initial_orders['success'] && !empty($initial_orders['orders'])): ?>
                <div class="pagination-info" id="pagination-info">
                    Affichage de <span id="start-index">1</span> à <span id="end-index"><?= count($initial_orders['orders']) ?></span> 
                    sur <span id="total-items"><?= $initial_orders['pagination']['total'] ?? 0 ?></span> commandes
                </div>

                <div class="pagination-controls" style="display: flex; align-items: center; gap: 10px;">
                    <div class="per-page-selector" style="display: flex; align-items: center; gap: 5px;">
                        <label for="per-page-select" style="font-size: 12px;">Afficher:</label>
                        <select id="per-page-select" style="padding: 5px; border-radius: 4px; border: 1px solid #ddd;">
                            <option value="10" <?= ($initial_orders['pagination']['per_page'] == 10) ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= ($initial_orders['pagination']['per_page'] == 25) ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= ($initial_orders['pagination']['per_page'] == 50) ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= ($initial_orders['pagination']['per_page'] == 100) ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>

                    <div class="page-navigation" style="display: flex; gap: 5px;">
                        <button id="first-page" class="page-btn" <?= ($initial_orders['pagination']['page'] <= 1) ? 'disabled' : '' ?>>
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                        <button id="prev-page" class="page-btn" <?= ($initial_orders['pagination']['page'] <= 1) ? 'disabled' : '' ?>>
                            <i class="fas fa-angle-left"></i>
                        </button>

                        <div class="page-numbers" id="page-numbers" style="display: flex; gap: 5px;">
                            <!-- JS will insert page numbers -->
                        </div>

                        <button id="next-page" class="page-btn" <?= ($initial_orders['pagination']['page'] >= $initial_orders['pagination']['total_pages']) ? 'disabled' : '' ?>>
                            <i class="fas fa-angle-right"></i>
                        </button>
                        <button id="last-page" class="page-btn" <?= ($initial_orders['pagination']['page'] >= $initial_orders['pagination']['total_pages']) ? 'disabled' : '' ?>>
                            <i class="fas fa-angle-double-right"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Order Status Modal -->
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

<style>
    /* AI Status Badge Styles */
    .ai-status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 500;
        text-align: center;
    }

    .ai-status-badge.pending {
        background: #fff3cd;
        color: #856404;
    }

    .ai-status-badge.sent {
        background: #d1ecf1;
        color: #0c5460;
    }

    .ai-status-badge.delivered {
        background: #cce5ff;
        color: #004085;
    }

    .ai-status-badge.customer_confirmed {
        background: #d4edda;
        color: #155724;
    }

    .ai-status-badge.customer_rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .ai-status-badge.no_response {
        background: #fff3cd;
        color: #856404;
    }

    .ai-status-badge.escalated {
        background: #f1f1f1;
        color: #212529;
    }

    .ai-status-badge.failed {
        background: #f8d7da;
        color: #721c24;
    }

    .ai-status-badge.not_started {
        background: #e2e3e5;
        color: #383d41;
    }

    .ai-status-badge.disabled {
        background: #e2e3e5;
        color: #6c757d;
    }
</style>
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let currentOrderId = null;
    let selectedOrders = new Set();

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        initializeEventListeners();
        initializeFilterListeners()

    });

    function initializeEventListeners() {
        // Sync orders button
        document.getElementById('sync-orders-btn').addEventListener('click', syncOrders);
        
        // Select all checkbox
        document.getElementById('select-all-checkbox').addEventListener('change', toggleSelectAll);
        
        // Add bulk action buttons event listeners
        addBulkActionListeners();
    }

    function addBulkActionListeners() {
        if (!document.getElementById('bulk-actions')) {
            const filtersContainer = document.querySelector('.orders-filters');
            const bulkActionsHTML = `
                <div id="bulk-actions" class="bulk-actions" style="display: none;">
                    <span class="selected-count">0 commande(s) sélectionnée(s)</span>
                    <div class="bulk-buttons">
                        <button class="btn-bulk-status" id="bulk-status-btn">
                            Changer le statut
                        </button>
                        <button class="btn-bulk-assign" id="bulk-assign-btn">
                            <i class="fas fa-user-plus"></i> Assigner
                        </button>
                        <button class="btn-bulk-ship" id="bulk-ship-btn">
                            <i class="fas fa-truck"></i> Expédier la sélection
                        </button>
                        <button class="btn-bulk-delete" id="bulk-delete-btn">
                            Supprimer sélection
                        </button>
                    </div>
                </div>
            `;
            filtersContainer.insertAdjacentHTML('afterend', bulkActionsHTML);
            
            document.getElementById('bulk-status-btn').addEventListener('click', bulkChangeStatus);
            document.getElementById('bulk-assign-btn').addEventListener('click', bulkAssignAgents);
            document.getElementById('bulk-ship-btn').addEventListener('click', bulkShipOrders);
            document.getElementById('bulk-delete-btn').addEventListener('click', bulkDeleteOrders);
        }
    }

    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('select-all-checkbox');
        const orderCheckboxes = document.querySelectorAll('.order-checkbox');
        
        if (selectAllCheckbox.checked) {
            orderCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
                selectedOrders.add(parseInt(checkbox.value));
            });
            selectAllCheckbox.dataset.selected = 'true';
        } else {
            orderCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectedOrders.clear();
            selectAllCheckbox.dataset.selected = 'false';
        }
        
        updateBulkActionsVisibility();
        checkSelectedOrdersStatus();
    }

    function checkSelectedOrdersStatus() {
        const bulkShipBtn = document.getElementById('bulk-ship-btn');
        if (!bulkShipBtn) return;
        
        // Get all selected order rows
        const selectedRows = document.querySelectorAll('.order-checkbox:checked');
        
        // Check if all selected orders are confirmed
        let allConfirmed = true;
        selectedRows.forEach(checkbox => {
            const row = checkbox.closest('tr');
            const statusSelect = row.querySelector('.confirmation-select');
            if (statusSelect && statusSelect.value !== 'confirmed') {
                allConfirmed = false;
            }
        });
        
        // Show/hide bulk ship button
        bulkShipBtn.style.display = allConfirmed && selectedRows.length >= 1 ? 'block' : 'none';
    }

    // Modify the handleOrderCheckboxChange function to check status
    function handleOrderCheckboxChange(checkbox) {
        const orderId = parseInt(checkbox.value);
        
        if (checkbox.checked) {
            selectedOrders.add(orderId);
        } else {
            selectedOrders.delete(orderId);
            document.getElementById('select-all-checkbox').checked = false;
        }
        
        const visibleCheckboxes = document.querySelectorAll('.order-checkbox');
        const allSelected = Array.from(visibleCheckboxes).every(cb => cb.checked);
        document.getElementById('select-all-checkbox').checked = allSelected;
        
        updateBulkActionsVisibility();
        checkSelectedOrdersStatus(); // Add this line
    }

    // Add this new function to handle bulk shipping
    function bulkShipOrders() {
        if (selectedOrders.size < 1) {
            showNotification('Veuillez sélectionner au moins une commande', 'warning');
            return;
        }
        
        // Get all selected confirmed orders
        const orderIds = Array.from(selectedOrders);
        
        if (!confirm(`Êtes-vous sûr de vouloir expédier ${orderIds.length} commande(s) confirmée(s) ?`)) {
            return;
        }
        
        const bulkShipBtn = document.getElementById('bulk-ship-btn');
        if (bulkShipBtn) {
            bulkShipBtn.disabled = true;
            bulkShipBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';
        }
        
        showNotification(`Envoi de ${orderIds.length} commande(s) au transporteur...`, 'info');
        
        const formData = new FormData();
        formData.append('action', 'bulk_submit_to_shipping');
        formData.append('order_ids', JSON.stringify(orderIds));
        
        fetch('./controllers/upTo_shipping_api.php', {
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
                showNotification(successMessage, 'success');
                
                // Update tracking numbers in UI for successful shipments
                if (data.tracking_numbers) {
                    data.tracking_numbers.forEach(trackingInfo => {
                        if (trackingInfo.success && trackingInfo.tracking_number) {
                            updateTrackingNumberInUI(trackingInfo.order_id, trackingInfo.tracking_number);
                        }
                    });
                }
                
                loadOrders();
            } else {
                showNotification(data.message || 'Erreur lors de l\'envoi au transporteur', 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur de connexion avec le serveur', 'error');
            console.error('Error:', error);
        })
        .finally(() => {
            if (bulkShipBtn) {
                bulkShipBtn.disabled = false;
                bulkShipBtn.innerHTML = '<i class="fas fa-truck"></i> Expédier la sélection';
            }
            selectedOrders.clear();
            updateBulkActionsVisibility();
        });
    }

    function handleOrderCheckboxChange(checkbox) {
        const orderId = parseInt(checkbox.value);
        
        if (checkbox.checked) {
            selectedOrders.add(orderId);
        } else {
            selectedOrders.delete(orderId);
            // Uncheck select all if not all are selected
            document.getElementById('select-all-checkbox').checked = false;
        }
        
        // Check if all visible orders are selected
        const visibleCheckboxes = document.querySelectorAll('.order-checkbox');
        const allSelected = Array.from(visibleCheckboxes).every(cb => cb.checked);
        document.getElementById('select-all-checkbox').checked = allSelected;
        
        updateBulkActionsVisibility();
    }

    function updateBulkActionsVisibility() {
        const bulkActions = document.getElementById('bulk-actions');
        const selectedCount = document.querySelector('.selected-count');
        
        // Show bulk actions when 2 OR MORE orders are selected
        if (selectedOrders.size >= 2) {
            bulkActions.style.display = 'flex';
            selectedCount.textContent = `${selectedOrders.size} commande${selectedOrders.size > 1 ? 's' : ''} sélectionnée${selectedOrders.size > 1 ? 's' : ''}`;
        } else {
            bulkActions.style.display = 'none';
        }
    }

    function bulkChangeStatus() {
        console.log('bulkChangeStatus called'); // Debug log
        console.log('Selected orders count:', selectedOrders.size); // Debug log
        
        if (selectedOrders.size < 2) {
            console.log('Not enough orders selected'); // Debug log
            showNotification('Veuillez sélectionner au moins 2 commandes', 'warning');
            return;
        }
        
        // Set current order ID to indicate bulk operation
        currentOrderId = 'bulk';
        console.log('Current order ID set to:', currentOrderId); // Debug log
        
        document.getElementById('new-status').value = 'no-answer'; // Default value
        
        const modal = document.getElementById('status-modal');
        console.log('Modal element:', modal); // Debug log
        
        if (modal) {
            console.log('Showing modal'); // Debug log
            modal.style.display = 'block';
        } else {
            console.error('Modal element not found!'); // Debug log
        }
    }

    function bulkDeleteOrders() {
        if (selectedOrders.size < 2) {
            showNotification('Veuillez sélectionner au moins 2 commandes', 'warning');
            return;
        }
        
        if (!confirm(`Êtes-vous sûr de vouloir supprimer ${selectedOrders.size} commandes ?`)) {
            return;
        }
        
        const orderIds = Array.from(selectedOrders);
        
        const formData = new FormData();
        formData.append('action', 'delete_orders');
        formData.append('order_ids', JSON.stringify(orderIds));
        
        fetch('./controllers/delete_ordersApi.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                selectedOrders.clear();
                updateBulkActionsVisibility();
                loadOrders();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur lors de la suppression', 'error');
            console.error('Error:', error);
        });
    }

    function syncOrders() {
        const syncBtn = document.getElementById('sync-orders-btn');
        const originalText = syncBtn.innerHTML;
        
        syncBtn.disabled = true;
        syncBtn.innerHTML = '<svg class="spin" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/><path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/></svg> Synchronisation...';
        
        fetch('./controllers/sync_orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=sync_orders'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                loadOrders();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur lors de la synchronisation', 'error');
            console.error('Error:', error);
        })
        .finally(() => {
            syncBtn.disabled = false;
            syncBtn.innerHTML = originalText;
        });
    }

    let currentPage = 1;
    let totalPages = 1;
    let perPage = 50;

    function loadOrders() {
        const loading = document.getElementById('loading');
        loading.style.display = 'block';
        
        const formData = new FormData();
        formData.append('action', 'get_orders');
        formData.append('page', currentPage);
        formData.append('per_page', perPage);
        
        // Add filters
        const confirmationStatus = document.getElementById('confirmation-filter').value;
        const shippingStatus = document.getElementById('shipping-filter').value;
        const dateRange = document.getElementById('date-range').value;
        const storeFilter = document.getElementById('store-filter') ? document.getElementById('store-filter').value : '';
        const aiStatus = document.getElementById('ai-confirmation-filter')?.value || '';
        
        // Custom date range handling
        let startDate = '';
        let endDate = '';
        if (dateRange === 'custom') {
            startDate = document.getElementById('start-date').value;
            endDate = document.getElementById('end-date').value;
        }

        if (confirmationStatus) formData.append('confirmation_status', confirmationStatus);
        if (shippingStatus) formData.append('shipping_status', shippingStatus);
        if (dateRange) formData.append('date_range', dateRange);
        if (storeFilter) formData.append('store_id', storeFilter);
        if (aiStatus) formData.append('ai_confirmation_status', aiStatus);
        if (startDate) formData.append('start_date', startDate);
        if (endDate) formData.append('end_date', endDate);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            loading.style.display = 'none';
            if (data.success) {
                updateOrdersTable(data.orders);
                updateStats(data.dashboard_stats);
                updatePagination(data.pagination);
                
                // Add AI status indicators for each order
                if (data.orders) {
                    data.orders.forEach(order => {
                        if (order.ai_confirmation_status) {
                            addAIStatusIndicator(order.id, {
                                status: order.ai_confirmation_status,
                                attempt_number: order.ai_attempt_number,
                                attempts_remaining: order.ai_attempts_remaining,
                                next_attempt_at: order.ai_next_attempt,
                                escalated: order.ai_escalated
                            });
                        }
                    });
                }
                
                selectedOrders.clear();
                updateBulkActionsVisibility();
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

    function updatePagination(pagination) {
        if (!pagination) return;
        
        totalPages = pagination.total_pages;
        currentPage = pagination.page;
        perPage = pagination.per_page;
        
        // Update pagination info
        const startIndex = (currentPage - 1) * perPage + 1;
        const endIndex = Math.min(currentPage * perPage, pagination.total);
        
        document.getElementById('start-index').textContent = startIndex;
        document.getElementById('end-index').textContent = endIndex;
        document.getElementById('total-items').textContent = pagination.total;
        
        // Update per page selector
        document.getElementById('per-page-select').value = perPage;
        
        // Update page navigation buttons
        document.getElementById('first-page').disabled = currentPage === 1;
        document.getElementById('prev-page').disabled = currentPage === 1;
        document.getElementById('next-page').disabled = currentPage === totalPages;
        document.getElementById('last-page').disabled = currentPage === totalPages;
        
        // Generate page numbers
        const pageNumbersContainer = document.getElementById('page-numbers');
        pageNumbersContainer.innerHTML = '';
        
        // Always show first page
        addPageNumber(1, pageNumbersContainer);
        
        // Calculate range of pages to show
        let startPage = Math.max(2, currentPage - 2);
        let endPage = Math.min(totalPages - 1, currentPage + 2);
        
        // Adjust if we're near the beginning
        if (currentPage <= 4) {
            endPage = Math.min(6, totalPages - 1);
        }
        
        // Adjust if we're near the end
        if (currentPage >= totalPages - 3) {
            startPage = Math.max(2, totalPages - 5);
        }
        
        // Add ellipsis after first page if needed
        if (startPage > 2) {
            addEllipsis(pageNumbersContainer);
        }
        
        // Add middle pages
        for (let i = startPage; i <= endPage; i++) {
            addPageNumber(i, pageNumbersContainer);
        }
        
        // Add ellipsis before last page if needed
        if (endPage < totalPages - 1) {
            addEllipsis(pageNumbersContainer);
        }
        
        // Always show last page if there is more than one page
        if (totalPages > 1) {
            addPageNumber(totalPages, pageNumbersContainer);
        }
    }

    function addPageNumber(page, container) {
        const pageElement = document.createElement('button');
        pageElement.className = `page-number ${page === currentPage ? 'active' : ''}`;
        pageElement.textContent = page;
        pageElement.addEventListener('click', () => goToPage(page));
        container.appendChild(pageElement);
    }

    function addEllipsis(container) {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'page-ellipsis';
        ellipsis.textContent = '...';
        container.appendChild(ellipsis);
    }

    function goToPage(page) {
        if (page < 1 || page > totalPages || page === currentPage) return;
        
        currentPage = page;
        loadOrders();
        
        // Scroll to top of table
        document.getElementById('orders-table').scrollIntoView({ behavior: 'smooth' });
    }

    // Add event listeners for pagination controls
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize event listeners
        initializeEventListeners();
        
        // Add pagination event listeners ONLY if the elements exist
        const firstPageBtn = document.getElementById('first-page');
        const prevPageBtn = document.getElementById('prev-page');
        const nextPageBtn = document.getElementById('next-page');
        const lastPageBtn = document.getElementById('last-page');
        const perPageSelect = document.getElementById('per-page-select');
        
        if (firstPageBtn) {
            firstPageBtn.addEventListener('click', () => goToPage(1));
        }
        
        if (prevPageBtn) {
            prevPageBtn.addEventListener('click', () => goToPage(currentPage - 1));
        }
        
        if (nextPageBtn) {
            nextPageBtn.addEventListener('click', () => goToPage(currentPage + 1));
        }
        
        if (lastPageBtn) {
            lastPageBtn.addEventListener('click', () => goToPage(totalPages));
        }
        
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function() {
                perPage = parseInt(this.value);
                currentPage = 1; 
                loadOrders();
            });
        }
    });
    
    // Add event listeners for the new filter elements
    document.getElementById('apply-filters').addEventListener('click', loadOrders);
   
    document.getElementById('reset-filters').addEventListener('click', function() {
        // Reset all filter inputs
        document.getElementById('confirmation-filter').value = '';
        document.getElementById('shipping-filter').value = '';
        document.getElementById('date-range').value = 'week';
        if (document.getElementById('store-filter')) {
            document.getElementById('store-filter').value = '';
        }
        document.getElementById('custom-date-group').style.display = 'none';
        
        // Reload orders
        loadOrders();
    });

    // Handle custom date range visibility
    document.getElementById('date-range').addEventListener('change', function() {
        const customDateGroup = document.getElementById('custom-date-group');
        customDateGroup.style.display = this.value === 'custom' ? 'block' : 'none';
    });

    function updateOrdersTable(orders) {
        const tbody = document.getElementById('orders-tbody');
        
        if (!orders || orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="15" class="no-data">Aucune commande trouvée</td></tr>';
            return;
        }
        
        const statusLabels = {
            'new': 'Nouveau colis',
            'pickup_pending': 'En cours de ramassage',
            'collected': 'Ramassé',
            'in_transit': 'En transit',
            'arrived_at_agency': 'Arrivé à l\'agence',
            'out_for_delivery': 'En cours de livraison',
            'delivered': 'Livrée',
            'refused': 'Refusée',
            'unreachable': 'Client injoignable',
            'rescheduled': 'Reprogrammée',
            'returned_to_sender': 'Retour à l\'expéditeur',
            'cancelled': 'Annulée',
            'address_error': 'Erreur d\'adresse',
            'warehouse_waiting': 'En attente au dépôt',
            'delivery_failed': 'Livraison échouée',
            'pending': 'En attente',
            'processing': 'En préparation',
            'shipped': 'Expédiée',
            'not_submitted': 'non soumis'
        };

        const confirmationOptions = {
            'confirmed': 'Confirmée',
            'no-answer': 'Pas de réponse',
            'busy': 'Occupé',
            'cancelled': 'Annulée',
            'double-order': 'Double commande',
            'unreachable': 'Injoignable'
        };

        const confirmationStyles = {
            'new-order': 'background-color: #cce5ff; color: #004085;',
            'confirmed': 'background-color: #d4edda; color: #155724;',
            'no-answer': 'background-color: #fff3cd; color: #856404;',
            'busy': 'background-color: #ffe8cc; color: #804d00;',
            'cancelled': 'background-color: #f8d7da; color: #721c24;',
            'double-order': 'background-color: #e2e3e5; color: #383d41;',
            'unreachable': 'background-color: #d1ecf1; color: #0c5460;'
        };

        const assignmentLabels = {
            'assigned': 'Assigné',
            'in_progress': 'En cours',
            'completed': 'Terminé',
            'cancelled': 'Annulé'
        };
        
        tbody.innerHTML = orders.map(order => {
            const currentStatus = order.status || 'new-order';
            let statusSelectOptions = '';
            
            // Add "Nouvelle commande" as disabled option only if it's current status
            if (currentStatus === 'new-order') {
                statusSelectOptions += `
                    <option value="new-order" selected disabled style="${confirmationStyles['new-order']}">
                        Nouvelle commande
                    </option>
                `;
            }
            
            // Add all other options
            statusSelectOptions += Object.entries(confirmationOptions).map(([value, label]) => {
                const selected = (currentStatus === value) ? 'selected' : '';
                const style = confirmationStyles[value] || 'background-color: #f8f9fa; color: #212529;';
                return `
                    <option value="${value}" ${selected} style="${style}">
                        ${label}
                    </option>
                `;
            }).join('');
            
            return `
                <tr data-order-id="${order.id}">
                    <td class="checkbox-cell">
                        <input type="checkbox" class="order-checkbox" value="${order.id}" onchange="handleOrderCheckboxChange(this)">
                    </td>
                    <td style="display: none;"><strong>${order.order_number || ''}</strong></td>
                    <td class="tracking-cell">
                        <div class="tracking-info">
                            ${order.tracking_number ? `
                                <div class="tracking-number">${order.tracking_number}</div>
                                <div class="tracking-status">
                                    <span class="status-badge ${order.shipping_status}">
                                        ${statusLabels[order.shipping_status] || order.shipping_status || 'En attente'}
                                    </span>
                                </div>
                            ` : `
                                <div class="tracking-number">-</div>
                                <div class="tracking-status">
                                    <span class="status-badge ${order.shipping_status}">
                                        ${statusLabels[order.shipping_status] || order.shipping_status || 'En attente'}
                                    </span>
                                </div>
                            `}
                        </div>
                    </td>
                    <td class="customer-cell">
                        <div class="customer-info">
                            <div class="customer-name">${order.customer_name || ''}</div>
                            <div class="customer-email">${order.customer_email || ''}</div>
                        </div>
                    </td>
                    <td class="phone-cell">
                        ${order.customer_phone ? `
                            <div class="whatsapp-container">
                                <a href="https://wa.me/${order.customer_phone}" 
                                class="whatsapp-btn" target="_blank" title="Contacter via WhatsApp">
                                    <i class="fab fa-whatsapp" style="color: #FFF; font-size: 1rem;"></i>
                                </a>
                                <div class="phone-tooltip">
                                    ${order.customer_phone}
                                </div>
                            </div>
                        ` : '<span style="color: #9ca3af; font-size: 11px;">-</span>'}
                    </td>
                    <td class="city-cell">
                        <div style="font-size: 11px; color: #374151;">
                            ${order.customer_ville || '-'}
                        </div>
                    </td>
                    <td class="products-cell">
                        <div class="products-container">
                            <div class="product-count">${order.item_count || 0}</div>
                            <div class="products-tooltip">
                                ${order.products || ''}
                            </div>
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
                        ${order.ai_enabled ? `
                            <span class="ai-status-badge ${order.ai_confirmation_status || 'not_started'}">
                                ${
                                    {
                                        'pending': 'En attente AI',
                                        'sent': 'Message envoyé',
                                        'delivered': 'Message livré',
                                        'customer_confirmed': '✅ Confirmé',
                                        'customer_rejected': '❌ Refusé',
                                        'no_response': 'Sans réponse',
                                        'escalated': '⬆️ Vers agent',
                                        'failed': 'Échec',
                                        'not_started': 'Non démarré'
                                    }[order.ai_confirmation_status] || order.ai_confirmation_status || 'Non démarré'
                                }
                                ${order.ai_attempt_number > 1 ? `<small>(${order.ai_attempt_number}e tentative)</small>` : ''}
                            </span>
                        ` : `<span class="ai-status-badge disabled">Désactivé</span>`}
                    </td>
                    <td class="confirmation-cell">
                        <select class="confirmation-select" 
                            data-order-id="${order.id}"
                            onchange="updateConfirmationStatus(this)">
                            ${statusSelectOptions}
                        </select>

                        ${(currentStatus === 'confirmed' && (!order.tracking_number || order.tracking_number.trim() === '')) ? `
                            <div class="shipping-btn-container mt-2">
                                <button class="btn btn-sm btn-primary submit-shipping-btn" data-order-id="${order.id}" onclick="submitToShipping(${order.id})">
                                    <i class="fas fa-truck"></i> Expédier
                                </button>
                            </div>
                        ` : ''}
                    </td>
                    
                    <td class="agent-cell">
                        <div class="agent-info" style="text-align: center;">
                            ${order.agent_name ? `
                                <div class="agent-name" style="font-size: 11px; color: #374151; font-weight: 500;">
                                    ${order.agent_name}
                                </div>
                                ${order.assignment_status ? `
                                    <div class="assignment-status" style="font-size: 10px; color: #6b7280;">
                                        <span class="assignment-badge ${order.assignment_status}">
                                            ${assignmentLabels[order.assignment_status] || order.assignment_status}
                                        </span>
                                    </div>
                                ` : ""}
                            ` : `
                                <div style="font-size: 11px; color: #9ca3af;">
                                    Non assigné
                                </div>
                            `}
                        </div>
                    </td>

                    <td class="store-cell">
                        <div class="store-name">
                            ${order.storeName || 'N/A'}
                        </div>
                    </td>
                    <td class="date-cell">
                        <div>${formatDate(order.created_at)}</div>
                        <div style="font-size: 10px; color: #9ca3af;">${formatTime(order.created_at)}</div>
                    </td>
                    <td class="actions-cell">
                        <div class="actions-dropdown">
                            <div class="actions-trigger">⋮</div>
                            <div class="actions-menu">
                                <button onclick="viewOrder(${order.id})" title="Voir détails">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                    </svg>
                                    Voir
                                </button>
                                <button onclick="editOrderData(${order.id})" title="Modifier statut">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L10.5 8.207l-3-3L12.146.146zM11.207 9.5L7 13.707V10.5a.5.5 0 0 1 .5-.5h3.707zM10.5 11.207L6.293 7 .854 12.439a.5.5 0 0 0-.146.353V15.5a.5.5 0 0 0 .5.5h2.707a.5.5 0 0 0 .354-.146L10.5 11.207z"/>
                                    </svg>
                                    Modifier
                                </button>
                                ${!order.agent_name ? `
                                    <button onclick="assignAgent(${order.id})" title="Assigner un agent">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                                        </svg>
                                        Assigner
                                    </button>
                                ` : `
                                    <button onclick="reassignAgent(${order.id})" title="Réassigner">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                            <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                            <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                        </svg>
                                        Réassigner
                                    </button>
                                `}
                                <button class="delete-btn" onclick="deleteOrder(${order.id})" title="Supprimer">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                        <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                    </svg>
                                    Supprimer
                                    </button>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
        
        // Reset select all checkbox
        document.getElementById('select-all-checkbox').checked = false;
        document.getElementById('select-all-checkbox').dataset.selected = 'false';
        
        // Reinitialize action dropdowns
        initializeActionDropdowns();
    }

    // Add this function to reinitialize the dropdowns after table update
    function initializeActionDropdowns() {
        document.querySelectorAll('.actions-trigger').forEach(trigger => {
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                const dropdown = this.closest('.actions-dropdown');
                
                // Close others
                document.querySelectorAll('.actions-dropdown.open').forEach(el => {
                    if (el !== dropdown) el.classList.remove('open');
                });
                
                dropdown.classList.toggle('open');
            });
        });
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function formatTime(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${hours}:${minutes}`;
    }

    function updateStats(stats) {
        // Total orders
        const totalElement = document.getElementById('total-orders-value');
        if (totalElement) {
            totalElement.textContent = stats.total_orders || 0;
        }
        
        // Confirmed orders breakdown
        const confirmedTotalElement = document.getElementById('confirmed-orders-value');
        if (confirmedTotalElement) {
            confirmedTotalElement.textContent = stats.confirmed?.total || 0;
        }
        
        const confirmedByUsElement = document.getElementById('confirmed-by-us');
        if (confirmedByUsElement) {
            confirmedByUsElement.textContent = stats.confirmed?.by_us || 0;
        }
        
        const confirmedByShippingElement = document.getElementById('confirmed-by-shipping');
        if (confirmedByShippingElement) {
            confirmedByShippingElement.textContent = stats.confirmed?.by_shipping || 0;
        }
        
        // Cancelled orders breakdown
        const cancelledTotalElement = document.getElementById('cancelled-orders-value');
        if (cancelledTotalElement) {
            cancelledTotalElement.textContent = stats.cancelled?.total || 0;
        }
        
        const cancelledByUsElement = document.getElementById('cancelled-by-us');
        if (cancelledByUsElement) {
            cancelledByUsElement.textContent = stats.cancelled?.by_us || 0;
        }
        
        const cancelledByShippingElement = document.getElementById('cancelled-by-shipping');
        if (cancelledByShippingElement) {
            cancelledByShippingElement.textContent = stats.cancelled?.by_shipping || 0;
        }
        
        // Revenue
        const revenueElement = document.getElementById('revenue-value');
        if (revenueElement) {
            const amount = stats.revenue?.amount ? Number(stats.revenue.amount).toFixed(2) : '0.00';
            const currency = stats.revenue?.currency || 'MAD';
            revenueElement.innerHTML = `${amount} <span class="currency">${currency}</span>`;
        }
    }

    // Helper function to update progress bars
    function updateProgressBar(elementId, currentValue, totalValue) {
        const element = document.getElementById(elementId);
        if (element) {
            const percentage = Math.min(100, (currentValue || 0) / Math.max(1, totalValue || 1) * 100);
            element.style.width = percentage + '%';
        }
    }

    function editOrderStatus(orderId, currentStatus) {
        currentOrderId = orderId;
        document.getElementById('new-status').value = currentStatus;
        document.getElementById('status-modal').style.display = 'block';
    }

    function closeStatusModal() {
        document.getElementById('status-modal').style.display = 'none';
        currentOrderId = null;
    }

    function updateOrderStatus() {
        if (!currentOrderId) return;
        
        const newStatus = document.getElementById('new-status').value;
        
        if (currentOrderId === 'bulk') {
            if (selectedOrders.size === 0) {
                showNotification('Aucune commande sélectionnée', 'warning');
                return;
            }
            
            const orderIds = Array.from(selectedOrders);
            const formData = new FormData();
            formData.append('action', 'update_orders_status_bulk'); // Updated action name
            formData.append('order_ids', JSON.stringify(orderIds));
            formData.append('status', newStatus);
            
            fetch('./controllers/update_or_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = data.message;
                    if (data.successful_count !== undefined) {
                        message += ` - ${data.successful_count} mise(s) à jour réussie(s)`;
                        if (data.failed_count > 0) {
                            message += `, ${data.failed_count} échec(s)`;
                        }
                    }
                    showNotification(message, 'success');
                    closeStatusModal();
                    selectedOrders.clear();
                    updateBulkActionsVisibility();
                    loadOrders();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur lors de la mise à jour', 'error');
                console.error('Error:', error);
            });
        } else {
            const formData = new FormData();
            formData.append('action', 'update_order_status');
            formData.append('order_id', currentOrderId);
            formData.append('status', newStatus);
            
            fetch('./controllers/update_or_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    closeStatusModal();
                    loadOrders();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur lors de la mise à jour', 'error');
                console.error('Error:', error);
            });
        }
    }

    function deleteOrder(orderId) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette commande ?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'delete_order');
        formData.append('order_id', orderId);
        
        fetch('./controllers/delete_ordersApi.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                loadOrders();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur lors de la suppression', 'error');
            console.error('Error:', error);
        });
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 5000);
    }

    window.onclick = function(event) {
        const statusModal = document.getElementById('status-modal');
        const orderModal = document.getElementById('order-modal');
        
        if (event.target === statusModal) {
            closeStatusModal();
        }
        if (event.target === orderModal) {
            closeOrderModal();
        }
    }

    function closeOrderModal() {
        const modal = document.getElementById('orderModal');
        if (modal) {
            modal.classList.remove('show');
            modal.classList.add('hide');
        }
    }

    function updateConfirmationStatus(selectElement) {
        const orderId = selectElement.getAttribute('data-order-id');
        const newStatus = selectElement.value;
        const originalSelectedIndex = selectElement.selectedIndex;
        
        selectElement.disabled = true;
        
        const formData = new FormData();
        formData.append('action', 'update_order_status');
        formData.append('order_id', orderId);
        formData.append('status', newStatus);
        
        fetch('./controllers/update_or_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            selectElement.disabled = false;
            
            if (data.success) {
                updateSelectStyle(selectElement, newStatus);
                showNotification('Statut de confirmation mis à jour', 'success');
                
                if (newStatus === 'confirmed') {
                    showShippingSubmissionOption(orderId);
                } else {
                    removeShippingSubmissionOption(orderId);
                }
            } else {
                selectElement.selectedIndex = originalSelectedIndex;
                showNotification(data.message || 'Erreur lors de la mise à jour', 'error');
            }
        })
        .catch(error => {
            selectElement.disabled = false;
            selectElement.selectedIndex = originalSelectedIndex;
            showNotification('Erreur de connexion', 'error');
            console.error('Error:', error);
        });
    }

    function updateSelectStyle(selectElement, status) {
        for (let option of selectElement.options) {
            option.style = '';
        }
        
        switch(status) {
            case 'confirmed':
                selectElement.style.backgroundColor = '#d4edda';
                selectElement.style.color = '#155724';
                break;
            case 'no-answer':
                selectElement.style.backgroundColor = '#fff3cd';
                selectElement.style.color = '#856404';
                break;
            case 'busy':
                selectElement.style.backgroundColor = '#ffe8cc';
                selectElement.style.color = '#804d00';
                break;
            case 'cancelled':
                selectElement.style.backgroundColor = '#f8d7da';
                selectElement.style.color = '#721c24';
                break;
            case 'double-order':
                selectElement.style.backgroundColor = '#e2e3e5';
                selectElement.style.color = '#383d41';
                break;
            case 'unreachable':
                selectElement.style.backgroundColor = '#d1ecf1';
                selectElement.style.color = '#0c5460';
                break;
            default:
                selectElement.style.backgroundColor = '#f8f9fa';
                selectElement.style.color = '#212529';
        }
    }

    function showShippingSubmissionOption(orderId) {
        const confirmationCell = document.querySelector(`select[data-order-id="${orderId}"]`).closest('td');
        
        let btnContainer = confirmationCell.querySelector('.shipping-btn-container');
        if (!btnContainer) {
            btnContainer = document.createElement('div');
            btnContainer.className = 'shipping-btn-container mt-2';
            confirmationCell.appendChild(btnContainer);
        }
        
        btnContainer.innerHTML = `
            <button class="btn btn-sm btn-primary submit-shipping-btn" data-order-id="${orderId}" onclick="submitToShipping(${orderId})">
                <i class="fas fa-truck"></i> Expédier
            </button>
        `;
    }

    function removeShippingSubmissionOption(orderId) {
        const confirmationCell = document.querySelector(`select[data-order-id="${orderId}"]`).closest('td');
        const btnContainer = confirmationCell.querySelector('.shipping-btn-container');
        if (btnContainer) {
            btnContainer.remove();
        }
    }

    function submitToShipping(orderId) {
        const btn = document.querySelector(`button[data-order-id="${orderId}"].submit-shipping-btn`);
        const originalBtnHtml = btn ? btn.innerHTML : '';
        
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
        }
        
        showNotification('Envoi de la commande au transporteur...', 'info');
        
        const formData = new FormData();
        formData.append('action', 'submit_to_shipping');
        formData.append('order_id', orderId);
        
        fetch('./controllers/upTo_shipping_api.php', {
            method: 'POST',
            body: formData
        })
        .then(async response => {
            // First get the raw response text to see what's coming back
            const rawResponse = await response.text();
            console.log('RAW RESPONSE:', rawResponse);
            console.log('HTTP STATUS:', response.status);
            
            let data;
            try {
                data = JSON.parse(rawResponse);
            } catch(e) {
                // If JSON parsing fails, show the raw response
                console.error('JSON Parse Error:', e);
                throw new Error(`Réponse invalide du serveur: ${rawResponse.substring(0, 300)}`);
            }
            
            if (!response.ok) {
                throw new Error(data.message || `Erreur HTTP ${response.status}`);
            }
            
            return data;
        })
        .then(data => {
            
            if (data.success) {
                let successMsg = 'Commande envoyée au transporteur avec succès';
                if (data.shipping_notification?.tracking_number) {
                    successMsg += `\nNuméro de suivi: ${data.shipping_notification.tracking_number}`;
                    showNotification(successMsg, 'success', 8000);
                    updateTrackingNumberInUI(orderId, data.shipping_notification.tracking_number);
                } else {
                    showNotification(successMsg + (data.message ? `\n${data.message}` : ''), 'success');
                }
                
                if (typeof loadOrders === 'function') {
                    loadOrders();
                } else if (typeof refreshOrders === 'function') {
                    refreshOrders();
                } else {
                    setTimeout(() => location.reload(), 2000);
                }
            } else {
                let errorMsg = data.message || 'Erreur lors de l\'envoi au transporteur';
                
                if (data.missing_fields && data.missing_fields.length > 0) {
                    errorMsg = `Champs requis manquants:\n${data.missing_fields.join('\n')}`;
                } else if (data.validation_errors && data.validation_errors.length > 0) {
                    errorMsg = `Erreurs de validation:\n${data.validation_errors.join('\n')}`;
                } else if (data.http_code === 401) {
                    errorMsg = 'Clé API invalide. Veuillez vérifier la configuration du transporteur.';
                } else if (data.http_code === 404) {
                    errorMsg = 'Service de transporteur non trouvé. Vérifiez l\'URL de l\'API.';
                }
                
                showNotification(errorMsg, 'error', 10000);
                console.error('Shipping error details:', data);
            }
        })
        .catch(error => {
            console.error('FETCH ERROR:', error);
            showNotification(error.message || 'Erreur de connexion avec le serveur', 'error');
        })
        .finally(() => {
            if (btn && typeof loadOrders !== 'function' && typeof refreshOrders !== 'function') {
                setTimeout(() => {
                    if (btn.parentElement) {
                        btn.disabled = false;
                        btn.innerHTML = originalBtnHtml || '<i class="fas fa-truck"></i> Expédier';
                    }
                }, 3000);
            } else if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml || '<i class="fas fa-truck"></i> Expédier';
            }
        });
    }

    function updateTrackingNumberInUI(orderId, trackingNumber) {
        const trackingCell = document.querySelector(`tr[data-order-id="${orderId}"] .tracking-number`);
        if (trackingCell) {
            trackingCell.textContent = trackingNumber;
        }
    }

    function checkAIAssignmentStatus(orderId) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('action', 'check_ai_status');
            formData.append('order_id', orderId);
            
            fetch('./controllers/assign_agentApi.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                resolve(data);
            })
            .catch(error => {
                reject(error);
            });
        });
    }

    // assignAgent function
    function assignAgent(orderId) {
        currentOrderId = orderId;
        
        // First check AI status
        showNotification('Vérification du statut AI...', 'info');
        
        checkAIAssignmentStatus(orderId)
            .then(statusData => {
                if (!statusData.success) {
                    showNotification(statusData.message || 'Erreur de vérification', 'error');
                    return;
                }
                
                if (!statusData.can_assign) {
                    // Show AI status message
                    let message = 'Cette commande est en cours de confirmation par AI.';
                    if (statusData.ai_status) {
                        message += `\nStatut: ${statusData.ai_status.status}`;
                        if (statusData.ai_status.attempts_remaining) {
                            message += `\nTentatives restantes: ${statusData.ai_status.attempts_remaining}`;
                        }
                        if (statusData.ai_status.next_attempt_at) {
                            const nextAttempt = new Date(statusData.ai_status.next_attempt_at);
                            message += `\nProchaine tentative: ${nextAttempt.toLocaleString()}`;
                        }
                    }
                    
                    if (statusData.can_override) {
                        message += '\n\nVoulez-vous forcer l\'assignation ? (Admin only)';
                    }
                    
                    if (statusData.can_override && confirm(message + '\n\nForcer l\'assignation ?')) {
                        // Force assignment
                        forceAssignAgent(orderId);
                        return;
                    }
                    
                    showNotification(message, 'warning', 10000);
                    return;
                }
                
                // AI status is okay, proceed with agent assignment
                proceedWithAgentAssignment(orderId);
            })
            .catch(error => {
                showNotification('Erreur de vérification AI', 'error');
                console.error('Error:', error);
            });
    }

    function forceAssignAgent(orderId) {
        const formData = new FormData();
        formData.append('action', 'force_assign_after_ai');
        formData.append('order_id', orderId);
        
        fetch('./controllers/assign_agentApi.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('AI process escalated, vous pouvez maintenant assigner un agent', 'success');
                // Proceed with normal assignment
                proceedWithAgentAssignment(orderId);
            } else {
                showNotification(data.message || 'Erreur de forçage', 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur de connexion', 'error');
            console.error('Error:', error);
        });
    }

    function proceedWithAgentAssignment(orderId) {
        // Show loading state
        showNotification('Chargement des agents disponibles...', 'info');
        
        // Fetch available agents
        fetch('./controllers/get_agentsForAssign.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_available_agents'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAgentAssignmentModal(data.agents, false);
            } else {
                showNotification('Erreur lors du chargement des agents', 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur de connexion', 'error');
            console.error('Error:', error);
        });
    }

    function reassignAgent(orderId) {
        currentOrderId = orderId;
        
        // Show loading state
        showNotification('Chargement des informations...', 'info');
        
        // Fetch current assignment and available agents
        Promise.all([
            // Get current assignment
            fetch('./controllers/re_agent_assignmentApi.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_current_assignment&order_id=${orderId}`
            }).then(r => r.json()),
            
            // Get available agents
            fetch('./controllers/get_agentsForAssign.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_available_agents'
            }).then(r => r.json())
        ])
        .then(([assignmentData, agentsData]) => {
            if (agentsData.success) {
                showAgentAssignmentModal(agentsData.agents, true, assignmentData.current_assignment);
            } else {
                showNotification('Erreur lors du chargement des agents', 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur de connexion', 'error');
            console.error('Error:', error);
        });
    }

    function showAgentAssignmentModal(agents, isReassignment = false, currentAssignment = null) {
        // Create modal HTML
        const modalHTML = `
            <div id="agent-assignment-modal" class="modal" style="display: block;">
                <div class="modal-content" style="max-width: 500px;">
                    <div class="modal-header">
                        <h3>${isReassignment ? 'Réassigner un agent' : 'Assigner un agent'}</h3>
                        <span class="close" onclick="closeAgentModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="agent-select">Sélectionner un agent:</label>
                            <select id="agent-select" class="form-control">
                                <option value="">-- Choisir un agent --</option>
                                ${agents.map(agent => `
                                    <option value="${agent.id}" 
                                        ${currentAssignment && currentAssignment.agent_id == agent.id ? 'selected' : ''}
                                        data-phone="${agent.phone || ''}"
                                        data-email="${agent.email || ''}">
                                        ${agent.name} - ${agent.phone || 'N/A'}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                        
                        <div class="agent-info" id="agent-info" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; display: none;">
                            <div><strong>Téléphone:</strong> <span id="agent-phone"></span></div>
                            <div><strong>Email:</strong> <span id="agent-email"></span></div>
                            <div><strong>Frais de service:</strong> <span id="agent-zone"></span></div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 15px;">
                            <label for="assignment-notes">Notes (optionnel):</label>
                            <textarea id="assignment-notes" class="form-control" rows="3" 
                                    placeholder="Notes pour l'agent..."></textarea>
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
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Add event listener for agent selection change
        document.getElementById('agent-select').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const infoDiv = document.getElementById('agent-info');
            
            if (this.value && selectedOption.dataset.phone) {
                document.getElementById('agent-phone').textContent = selectedOption.dataset.phone || 'N/A';
                document.getElementById('agent-email').textContent = selectedOption.dataset.email || 'N/A';
                // You might want to add zone information to your agent data
                document.getElementById('agent-zone').textContent = selectedOption.dataset.zone || 'N/A';
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        });
        
        // Trigger change event if there's a preselected agent
        if (currentAssignment) {
            document.getElementById('agent-select').dispatchEvent(new Event('change'));
        }
    }

    function closeAgentModal() {
        const modal = document.getElementById('agent-assignment-modal');
        if (modal) {
            modal.remove();
        }
        currentOrderId = null;
    }

    function confirmAgentAssignment(isReassignment = false) {
        const agentSelect = document.getElementById('agent-select');
        const agentId = agentSelect.value;
        const notes = document.getElementById('assignment-notes').value;
        const priority = document.getElementById('assignment-priority').value;
        const assignmentType = document.getElementById('assignment-type')?.value || 'normal';
        
        if (!agentId) {
            showNotification('Veuillez sélectionner un agent', 'warning');
            return;
        }
        
        if (!currentOrderId) {
            showNotification('Erreur: ID de commande manquant', 'error');
            return;
        }
        
        const submitBtn = document.querySelector('#agent-assignment-modal .btn-primary');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
        
        const formData = new FormData();
        formData.append('action', isReassignment ? 'reassign_agent' : 'assign_agent');
        formData.append('order_id', currentOrderId);
        formData.append('agent_id', agentId);
        formData.append('notes', notes);
        formData.append('priority', priority);
        formData.append('assignment_type', assignmentType);
        
        fetch('./controllers/assign_agentApi.php', {
            method: 'POST', 
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Check if AI was escalated
                let message = isReassignment ? 'Agent réassigné avec succès' : 'Agent assigné avec succès';
                if (data.data?.ai_escalated) {
                    message += ' (AI process terminé)';
                }
                showNotification(message, 'success');
                closeAgentModal();
                loadOrders(); // Refresh the orders table
            } else {
                // Check if this is an AI waiting error
                if (data.ai_status) {
                    let errorMsg = data.message;
                    if (data.attempts_remaining) {
                        errorMsg += `\nTentatives restantes: ${data.attempts_remaining}`;
                    }
                    if (data.next_attempt_at) {
                        const nextAttempt = new Date(data.next_attempt_at);
                        errorMsg += `\nProchaine tentative: ${nextAttempt.toLocaleString()}`;
                    }
                    if (data.can_override) {
                        errorMsg += '\n\nUtilisez "Forcer l\'assignation" si nécessaire.';
                    }
                    showNotification(errorMsg, 'warning', 10000);
                } else {
                    showNotification(data.message || 'Erreur lors de l\'assignation', 'error');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            showNotification('Erreur de connexion', 'error');
            console.error('Error:', error);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    // Add AI status display in the order table
    function addAIStatusIndicator(orderId, aiStatus) {
        const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
        if (!row) return;
        
        // Find or create AI status cell
        let aiCell = row.querySelector('.ai-status-cell');
        if (!aiCell) {
            const confirmationCell = row.querySelector('.confirmation-cell');
            if (confirmationCell) {
                aiCell = document.createElement('td');
                aiCell.className = 'ai-status-cell';
                confirmationCell.parentNode.insertBefore(aiCell, confirmationCell.nextSibling);
            }
        }
        
        if (aiCell) {
            let statusHtml = '';
            if (aiStatus) {
                const statusLabels = {
                    'pending': '⏳ En attente AI',
                    'sent': '✉️ Message envoyé',
                    'delivered': '✅ Message livré',
                    'customer_confirmed': '✅ Confirmé par client',
                    'customer_rejected': '❌ Refusé par client',
                    'no_response': '⏰ Sans réponse',
                    'escalated': '⬆️ Vers agent',
                    'failed': '❌ Échec'
                };
                
                const label = statusLabels[aiStatus.status] || aiStatus.status;
                const attemptInfo = aiStatus.attempt_number ? ` (${aiStatus.attempt_number}e tentative)` : '';
                
                statusHtml = `
                    <span class="ai-status-badge ${aiStatus.status}">
                        ${label}
                        ${attemptInfo}
                    </span>
                    ${aiStatus.next_attempt_at && aiStatus.status !== 'escalated' ? `
                        <div style="font-size: 10px; color: #6b7280; margin-top: 2px;">
                            Prochaine: ${new Date(aiStatus.next_attempt_at).toLocaleString()}
                        </div>
                    ` : ''}
                `;
            } else {
                statusHtml = `<span class="ai-status-badge not_started">⏸️ Non démarré</span>`;
            }
            aiCell.innerHTML = statusHtml;
        }
    }

    // Add this CSS for the agent assignment modal
    const agentModalStyles = `
        <style>
        .agent-info {
            font-size: 14px;
        }
        .agent-info div {
            margin-bottom: 5px;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        </style>
    `;

    // Add styles to document head
    document.head.insertAdjacentHTML('beforeend', agentModalStyles);

    function bulkAssignAgents() {
        if (selectedOrders.size < 1) {
            showNotification('Veuillez sélectionner au moins une commande', 'warning');
            return;
        }
        
        // Check if manual assignment is allowed
        checkManualAssignmentPermission();
    }

    function checkManualAssignmentPermission() {
        // First check if manual assignment is allowed
        fetch('./controllers/check_assignment_settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=check_manual_assignment'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.allow_manual) {
                showBulkAssignmentModal();
            } else {
                showNotification(
                    data.message || "L'assignation manuelle n'est pas autorisée pour votre configuration", 
                    'error'
                );
            }
        })
        .catch(error => {
            showNotification('Erreur de vérification des permissions', 'error');
            console.error('Error:', error);
        });
    }

    function showBulkAssignmentModal() {
        // Show loading
        showNotification('Chargement des agents disponibles...', 'info');
        
        // Fetch available agents with region information
        fetch('./controllers/get_agentsForAssign.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_agents_with_regions'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createBulkAssignmentModal(data.agents, data.regions);
            } else {
                showNotification('Erreur lors du chargement des agents', 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur de connexion', 'error');
            console.error('Error:', error);
        });
    }

    function createBulkAssignmentModal(agents, regions) {
        const orderIds = Array.from(selectedOrders);
        console.log(regions)
        // Create modal HTML
        const modalHTML = `
            <div id="bulk-assignment-modal" class="modal" style="display: block;">
                <div class="modal-content" style="max-width: 800px; max-height: 80vh; overflow-y: auto;">
                    <div class="modal-header">
                        <h3>Assigner ${orderIds.length} commande(s) à un agent</h3>
                        <span class="close" onclick="closeBulkAssignmentModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info" style="margin-bottom: 20px;">
                            <i class="fas fa-info-circle"></i>
                            ${orderIds.length} commande(s) sélectionnée(s) pour l'assignation.
                        </div>
                        
                        <div class="row" style="display: flex; gap: 20px;">
                            <div class="col-md-6" style="flex: 1;">
                                <div class="form-group">
                                    <label for="region-filter"><i class="fas fa-map-marker-alt"></i> Filtrer par région:</label>
                                    <select id="region-filter" class="form-control" onchange="filterAgentsByRegion()">
                                        <option value="">Toutes les régions</option>
                                        ${regions.map(region => `
                                            <option value="${region.id}">${region.name}</option>
                                        `).join('')}
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="agent-search"><i class="fas fa-search"></i> Rechercher un agent:</label>
                                    <input type="text" id="agent-search" class="form-control" 
                                        placeholder="Nom ou téléphone..." 
                                        onkeyup="filterAgentsBySearch()">
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-sort"></i> Trier par:</label>
                                    <div class="sort-options" style="display: flex; gap: 10px;">
                                        <button class="btn btn-sm btn-outline-primary" onclick="sortAgents('name')">
                                            Nom A-Z
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" onclick="sortAgents('performance')">
                                            Performance
                                        </button>
                                        <button class="btn btn-sm btn-outline-primary" onclick="sortAgents('workload')">
                                            Charge de travail
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6" style="flex: 1;">
                                <div class="form-group">
                                    <label for="assignment-type"><i class="fas fa-exchange-alt"></i> Type d'assignation:</label>
                                    <select id="assignment-type" class="form-control">
                                        <option value="assign">Assigner (nouvelle)</option>
                                        <option value="reassign">Rassigner (remplacer)</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="assignment-priority"><i class="fas fa-flag"></i> Priorité:</label>
                                    <select id="assignment-priority" class="form-control">
                                        <option value="low">Basse</option>
                                        <option value="medium" selected>Moyenne</option>
                                        <option value="high">Haute</option>
                                        <option value="urgent">Urgente</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="assignment-notes"><i class="fas fa-sticky-note"></i> Notes (optionnel):</label>
                                    <textarea id="assignment-notes" class="form-control" rows="3" 
                                            placeholder="Notes pour l'agent..."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="agents-list" id="agents-list" 
                            style="max-height: 300px; overflow-y: auto; margin-top: 20px; border: 1px solid #ddd; border-radius: 4px; padding: 10px;">
                            ${agents.map(agent => `
                                <div class="agent-card" data-agent-id="${agent.id}" 
                                    data-region-id="${agent.region_id || ''}"
                                    data-agent-name="${agent.name.toLowerCase()}"
                                    data-agent-phone="${agent.phone || ''}"
                                    onclick="selectAgent(this, ${agent.id})"
                                    style="padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong>${agent.name}</strong>
                                            <div style="font-size: 12px; color: #666;">
                                                <i class="fas fa-phone"></i> ${agent.phone || 'N/A'}
                                                ${agent.region_name ? `<br><i class="fas fa-map-marker-alt"></i> ${agent.region_name}` : ''}
                                            </div>
                                        </div>
                                        <div style="text-align: right; font-size: 12px;">
                                            <div>Confirmations: ${agent.confirmation_rate || 0}%</div>
                                            <div>Charge: ${agent.current_assignments || 0} commandes</div>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        
                        <div class="selected-agent-info" id="selected-agent-info" 
                            style="margin-top: 20px; padding: 15px; background: #e8f4fd; border-radius: 4px; display: none;">
                            <h5><i class="fas fa-user-check"></i> Agent sélectionné:</h5>
                            <div id="agent-details"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-secondary" onclick="closeBulkAssignmentModal()">Annuler</button>
                        <button class="btn-primary" id="confirm-bulk-assign" onclick="confirmBulkAssignment()" disabled>
                            <i class="fas fa-user-plus"></i> Assigner les commandes
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    function selectAgent(cardElement, agentId) {
        // Remove selected class from all cards
        document.querySelectorAll('.agent-card').forEach(card => {
            card.classList.remove('selected');
            card.style.backgroundColor = '';
            card.style.borderColor = '#e0e0e0';
        });
        
        // Add selected class to clicked card
        cardElement.classList.add('selected');
        cardElement.style.backgroundColor = '#e8f4fd';
        cardElement.style.borderColor = '#007bff';
        
        // Update selected agent info
        const agentName = cardElement.querySelector('strong').textContent;
        const agentPhone = cardElement.dataset.agentPhone;
        const agentRegion = cardElement.querySelector('.fa-map-marker-alt')?.parentElement?.textContent?.replace('📍 ', '') || 'N/A';
        
        document.getElementById('selected-agent-info').style.display = 'block';
        document.getElementById('agent-details').innerHTML = `
            <div><strong>Nom:</strong> ${agentName}</div>
            <div><strong>Téléphone:</strong> ${agentPhone}</div>
            <div><strong>Région:</strong> ${agentRegion}</div>
        `;
        
        // Enable confirm button
        document.getElementById('confirm-bulk-assign').disabled = false;
        document.getElementById('confirm-bulk-assign').dataset.agentId = agentId;
    }

    function filterAgentsByRegion() {
        const regionId = document.getElementById('region-filter').value;
        const searchTerm = document.getElementById('agent-search').value.toLowerCase();
        filterAgents(regionId, searchTerm);
    }

    function filterAgentsBySearch() {
        const regionId = document.getElementById('region-filter').value;
        const searchTerm = document.getElementById('agent-search').value.toLowerCase();
        filterAgents(regionId, searchTerm);
    }

    function filterAgents(regionId, searchTerm) {
        const agentCards = document.querySelectorAll('.agent-card');
        
        agentCards.forEach(card => {
            const matchesRegion = !regionId || card.dataset.regionId === regionId;
            const matchesSearch = !searchTerm || 
                                card.dataset.agentName.includes(searchTerm) ||
                                card.dataset.agentPhone.includes(searchTerm);
            
            card.style.display = (matchesRegion && matchesSearch) ? 'block' : 'none';
        });
    }

    function sortAgents(sortBy) {
        const container = document.getElementById('agents-list');
        const cards = Array.from(container.querySelectorAll('.agent-card'));
        
        cards.sort((a, b) => {
            switch(sortBy) {
                case 'name':
                    return a.dataset.agentName.localeCompare(b.dataset.agentName);
                case 'performance':
                    const perfA = parseFloat(a.querySelector('div > div:nth-child(2) > div').textContent.replace('Confirmations: ', '').replace('%', ''));
                    const perfB = parseFloat(b.querySelector('div > div:nth-child(2) > div').textContent.replace('Confirmations: ', '').replace('%', ''));
                    return perfB - perfA; // Descending
                case 'workload':
                    const loadA = parseFloat(a.querySelector('div > div:nth-child(2) > div:nth-child(2)').textContent.replace('Charge: ', '').replace(' commandes', ''));
                    const loadB = parseFloat(b.querySelector('div > div:nth-child(2) > div:nth-child(2)').textContent.replace('Charge: ', '').replace(' commandes', ''));
                    return loadA - loadB; // Ascending (less workload first)
                default:
                    return 0;
            }
        });
        
        // Reorder cards
        cards.forEach(card => container.appendChild(card));
    }

    function confirmBulkAssignment() {
        const orderIds = Array.from(selectedOrders);
        const agentId = document.getElementById('confirm-bulk-assign').dataset.agentId;
        const assignmentType = document.getElementById('assignment-type').value;
        const priority = document.getElementById('assignment-priority').value;
        const notes = document.getElementById('assignment-notes').value;
        const usePerformance = document.getElementById('use-performance-assignment')?.checked || false;
        
        if (!usePerformance && !agentId) {
            showNotification('Veuillez sélectionner un agent ou utiliser l\'assignation par performance', 'warning');
            return;
        }
        
        if (orderIds.length === 0) {
            showNotification('Veuillez sélectionner des commandes', 'warning');
            return;
        }
        
        // First, close the bulk assignment modal
        closeBulkAssignmentModal();
        
        // Then show processing modal
        Swal.fire({
            title: usePerformance ? 'Assignation par performance en cours' : 'Assignation en cours',
            html: `
                <div style="text-align: center;">
                    <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-3">Traitement de ${orderIds.length} commande(s)...</p>
                    <div style="font-size: 14px; color: #6b7280; margin-top: 10px;">
                        ${usePerformance ? 
                            'Optimisation des agents selon leur performance' : 
                            `Assignation à l'agent sélectionné`}
                    </div>
                </div>
            `,
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        const formData = new FormData();
        formData.append('action', 'bulk_assign_orders');
        formData.append('order_ids', JSON.stringify(orderIds));
        
        if (!usePerformance && agentId) {
            formData.append('agent_id', agentId);
        }
        
        formData.append('assignment_type', assignmentType);
        formData.append('priority', priority);
        formData.append('notes', notes);
        formData.append('use_performance', usePerformance);
        
        fetch('./controllers/bulk_assign_orders.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            Swal.close(); // Close the loading modal
            
            if (data.success) {
                let message = `
                    <div style="text-align: left;">
                        <h5 style="color: #10b981; margin-bottom: 15px;">
                            <i class="fas fa-check-circle me-2"></i>
                            ${usePerformance ? 'Assignation optimisée terminée' : 'Assignation terminée'}
                        </h5>
                        
                        ${!usePerformance && data.agent ? `
                            <div style="background: #f0fdf4; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <p><strong>Agent:</strong> ${data.agent.name}</p>
                                <p><strong>Type:</strong> ${assignmentType === 'auto_confirm' ? 'Auto-confirmation' : 'Normale'}</p>
                                <p><strong>Priorité:</strong> ${priority}</p>
                            </div>
                        ` : ''}
                        
                        ${usePerformance && data.agents_used ? `
                            <div style="background: #eff6ff; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <h6 style="margin-top: 0; color: #1e40af;">Agents utilisés:</h6>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">
                                    ${Object.entries(data.agents_used).map(([agentId, agentData]) => `
                                        <div style="text-align: center; padding: 10px; background: white; border-radius: 6px; border: 1px solid #dbeafe;">
                                            <div style="font-weight: bold; color: #1e40af;">${agentData.agent}</div>
                                            <div style="font-size: 24px; font-weight: bold; color: #3b82f6;">${agentData.count}</div>
                                            <div style="font-size: 12px; color: #6b7280;">commandes</div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 15px;">
                            <div style="text-align: center; padding: 10px; background: #dcfce7; border-radius: 6px;">
                                <div style="font-size: 24px; font-weight: bold; color: #059669;">${data.statistics.assigned_count}</div>
                                <div style="font-size: 12px; color: #047857;">Assignées</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #fef3c7; border-radius: 6px;">
                                <div style="font-size: 24px; font-weight: bold; color: #d97706;">${data.statistics.reassigned_count}</div>
                                <div style="font-size: 12px; color: #b45309;">Réassignées</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #f3f4f6; border-radius: 6px;">
                                <div style="font-size: 24px; font-weight: bold; color: #6b7280;">${data.statistics.skipped_count}</div>
                                <div style="font-size: 12px; color: #6b7280;">Ignorées</div>
                            </div>
                            <div style="text-align: center; padding: 10px; background: #fee2e2; border-radius: 6px;">
                                <div style="font-size: 24px; font-weight: bold; color: #dc2626;">${data.statistics.failed_count}</div>
                                <div style="font-size: 12px; color: #b91c1c;">Échouées</div>
                            </div>
                        </div>
                    </div>
                `;
                
                if (data.statistics.failed_count > 0) {
                    message += `<p style="color: #dc2626; font-size: 14px;">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Certaines commandes n'ont pas pu être assignées
                    </p>`;
                }
                
                Swal.fire({
                    title: 'Assignation réussie',
                    html: message,
                    icon: 'success',
                    confirmButtonText: 'Fermer',
                    confirmButtonColor: '#10b981',
                    width: '700px',
                    showCancelButton: data.processed_orders && data.processed_orders.length > 0,
                    cancelButtonText: 'Voir les détails',
                    cancelButtonColor: '#3b82f6',
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isDismissed && result.dismiss === Swal.DismissReason.cancel) {
                        // Show detailed view
                        showAssignmentDetails(data.processed_orders, usePerformance);
                    }
                    
                    // Clear selections and refresh orders
                    selectedOrders.clear();
                    updateBulkActionsVisibility();
                    loadOrders();
                });
                
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur d\'assignation',
                    text: data.message || 'Une erreur est survenue',
                    confirmButtonColor: '#dc2626'
                });
            }
        })
        .catch(error => {
            Swal.close();
            Swal.fire({
                icon: 'error',
                title: 'Erreur de connexion',
                text: 'Impossible de se connecter au serveur',
                confirmButtonColor: '#dc2626'
            });
            console.error('Error:', error);
        });
    }

    // Function to show detailed assignment results
    function showAssignmentDetails(processedOrders, usePerformance = false) {
        let detailsHtml = `
            <div style="text-align: left; max-height: 400px; overflow-y: auto;">
                <h6 style="color: #374151; margin-bottom: 15px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">
                    <i class="fas fa-list-alt me-2"></i>Détails des assignations
                </h6>
        `;
        
        processedOrders.forEach((order, index) => {
            const statusColor = {
                'assigned': '#10b981',
                'reassigned': '#f59e0b',
                'skipped': '#6b7280',
                'failed': '#dc2626'
            }[order.status] || '#6b7280';
            
            detailsHtml += `
                <div style="padding: 12px; background: white; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid ${statusColor};">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #1f2937;">Commande #${order.order_id}</strong>
                            <div style="font-size: 12px; color: ${statusColor}; font-weight: 500;">
                                ${order.status === 'assigned' ? 'Assignée' : 
                                order.status === 'reassigned' ? 'Réassignée' : 
                                order.status === 'skipped' ? 'Ignorée' : 'Échouée'}
                            </div>
                        </div>
                        ${order.agent_name ? `
                            <div style="text-align: right;">
                                <div style="font-size: 12px; color: #6b7280;">Agent</div>
                                <div style="font-weight: 500;">${order.agent_name}</div>
                            </div>
                        ` : ''}
                    </div>
                    ${order.reason ? `
                        <div style="font-size: 12px; color: #6b7280; margin-top: 5px;">
                            <i class="fas fa-info-circle me-1"></i>${order.reason}
                        </div>
                    ` : ''}
                    ${usePerformance && order.score ? `
                        <div style="font-size: 11px; color: #9ca3af; margin-top: 5px;">
                            Score: ${order.score} | Confirmation: ${order.confirmation_rate}%
                        </div>
                    ` : ''}
                </div>
            `;
        });
        
        detailsHtml += `</div>`;
        
        Swal.fire({
            title: 'Détails des opérations',
            html: detailsHtml,
            width: '600px',
            confirmButtonText: 'Fermer',
            confirmButtonColor: '#3b82f6'
        });
    }

    function closeBulkAssignmentModal() {
        const modal = document.getElementById('bulk-assignment-modal');
        if (modal) {
            modal.remove();
        }
    }

    // Add CSS styles for the modal
    const bulkAssignmentStyles = `
        <style>
        .agent-card:hover {
            background-color: #f5f5f5;
            transform: translateY(-1px);
        }
        
        .agent-card.selected {
            background-color: #e8f4fd !important;
            border-color: #007bff !important;
        }
        
        .btn-bulk-assign {
            background: #17a2b8;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-bulk-assign:hover {
            background: #138496;
            transform: translateY(-1px);
        }
        
        .sort-options .btn {
            flex: 1;
        }
        
        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        </style>
    `;

    // Add styles to document head
    document.head.insertAdjacentHTML('beforeend', bulkAssignmentStyles);

    function initializeFilterListeners() {
        const applyBtn = document.getElementById('apply-filters');
        const resetBtn = document.getElementById('reset-filters');
        const dateRange = document.getElementById('date-range');
        
        // Remove old listeners by cloning (clean slate)
        if (applyBtn) {
            const newApply = applyBtn.cloneNode(true);
            applyBtn.parentNode.replaceChild(newApply, applyBtn);
            newApply.addEventListener('click', function(e) {
                e.preventDefault();
                currentPage = 1; // reset to page 1 when filters change
                loadOrders();
            });
        }
        
        if (resetBtn) {
            const newReset = resetBtn.cloneNode(true);
            resetBtn.parentNode.replaceChild(newReset, resetBtn);
            newReset.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('confirmation-filter').value = '';
                document.getElementById('shipping-filter').value = '';
                document.getElementById('date-range').value = 'week';
                if (document.getElementById('store-filter')) {
                    document.getElementById('store-filter').value = '';
                }
                if (document.getElementById('city-filter')) {
                    document.getElementById('city-filter').value = '';
                }
                const customDateGroup = document.getElementById('custom-date-group');
                if (customDateGroup) customDateGroup.style.display = 'none';
                
                currentPage = 1;
                loadOrders();
            });
        }
        
        if (dateRange) {
            const newDateRange = dateRange.cloneNode(true);
            dateRange.parentNode.replaceChild(newDateRange, dateRange);
            newDateRange.addEventListener('change', function() {
                const customDateGroup = document.getElementById('custom-date-group');
                if (customDateGroup) {
                    customDateGroup.style.display = this.value === 'custom' ? 'block' : 'none';
                }
            });
        }
    }
</script>

<style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 0;
        border: none;
        width: 400px;
        border-radius: 0px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    
    .modal-large {
        width: 80%;
        max-width: 800px;
    }
    
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        margin: 0;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .modal-footer {
        padding: 20px;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .close {
        color: #aaa;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover {
        color: #000;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 500;
    }
    
    .form-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 4px;
        color: white;
        font-weight: 500;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        z-index: 1001;
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-success {
        background-color: #28a745;
    }
    
    .notification-error {
        background-color: #dc3545;
    }
    
    .notification-info {
        background-color: #17a2b8;
    }
    
    .spin {
        animation: spin 2s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .no-data {
        text-align: center;
        padding: 40px;
        color: #666;
    }
    
    .customer-info {
        font-size: 13px;
    }
    
    .customer-name {
        font-weight: 500;
        margin-bottom: 2px;
    }
    
    .customer-email {
        color: #666;
    }
    
    .products-info {
        font-size: 13px;
    }
    
    .product-count {
        font-weight: 500;
        margin-bottom: 2px;
    }
    
    .product-list {
        color: #666;
        font-size: 12px;
    }
    
    .amount {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .actions {
        display: flex;
        gap: 5px;
        justify-content: center;
    }
    
    .btn-action {
        padding: 6px 8px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-view {
        background-color: #17a2b8;
        color: white;
    }
    
    .btn-view:hover {
        background-color: #138496;
    }
    
    .btn-edit {
        background-color: #ffc107;
        color: #212529;
    }
    
    .btn-edit:hover {
        background-color: #e0a800;
    }
    
    .btn-delete {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-delete:hover {
        background-color: #c82333;
    }
    
    .orders-filters {
        background: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        flex-wrap: nowrap;
        align-items: flex-start;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        flex-direction: column;
        align-content: flex-start;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        min-width: 120px;
    }
    
    .filter-group label {
        font-size: 12px;
        font-weight: 500;
        color: #495057;
        margin-bottom: 5px;
    }
    
    .filter-group select,
    .filter-group input {
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .btn-filter-apply {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
    }
    
    .btn-filter-apply:hover {
        background-color: #0056b3;
    }
    
    .btn-filter-reset {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
    }
    
    .btn-filter-reset:hover {
        background-color: #545b62;
    }
    
    .dashboard-content {
        padding: 20px;
    }
    
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .dashboard-header h2 {
        margin: 0;
        color: #2c3e50;
    }
    
    .action-buttons {
        display: flex;
        gap: 10px;
    }
    
    .btn-primary {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.2s;
    }
    
    .btn-primary:hover {
        background-color: #0056b3;
    }
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
        border: none;
        padding: 10px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background-color 0.2s;
    }
    
    .btn-secondary:hover {
        background-color: #545b62;
    }
    
    .stats-cards {
        display: grid;
        margin-bottom: 20px;
        gap: 15px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
    }
    
    .stat-card-header h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #6c757d;
        font-weight: 500;
    }
    
    .stat-card-value {
        font-size: 24px;
        font-weight: bold;
        color: #2c3e50;
    }
    
    @media (max-width: 768px) {
        .stats-cards {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .orders-filters {
            flex-direction: column;
            align-items: stretch;
        }
        
        .filter-group {
            min-width: auto;
        }
        
        .dashboard-header {
            flex-direction: column;
            gap: 15px;
            align-items: stretch;
        }
        
        .action-buttons {
            flex-direction: column;
            justify-content: center;
        }

        #import-sheets-btn {
            text-align: center;
        }

        .filters-grid {
            flex-direction: column;
        }

        .stats-container {
            padding: 0;
        }

        .orders-table-container {
            width: 100%;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
        }
    }
    
    @media (max-width: 480px) {
        .stats-cards {
            grid-template-columns: 1fr;
        }
        
        .orders-table {
            font-size: 12px;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 8px;
        }
    }

    /* Bulk Actions Styles */
    .bulk-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 12px 16px;
        margin: 16px 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    .selected-count {
        font-weight: 600;
        color: #495057;
        font-size: 14px;
    }

    .bulk-buttons {
        display: flex;
        gap: 8px;
    }

    .btn-bulk-status,
    .btn-bulk-delete {
        padding: 8px 16px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-bulk-status {
        background: #007bff;
        color: white;
    }

    .btn-bulk-status:hover {
        background: #0056b3;
        transform: translateY(-1px);
    }

    .btn-bulk-delete {
        background: #dc3545;
        color: white;
    }

    .btn-bulk-delete:hover {
        background: #c82333;
        transform: translateY(-1px);
    }

    /* Checkbox styling */
    .checkbox-cell {
        width: 40px;
        text-align: center;
    }

    .select-all-checkbox,
    .order-checkbox {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #007bff;
    }

    /* Animation for bulk actions */
    .bulk-actions {
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .bulk-actions {
            flex-direction: column;
            gap: 12px;
            align-items: stretch;
        }
        
        .bulk-buttons {
            justify-content: center;
        }
        
        .selected-count {
            text-align: center;
        }
    }
</style>

<?php require_once('./orderModal.php'); ?>
<?php require_once('./editOrderModal.php'); ?>

</body>
</html>