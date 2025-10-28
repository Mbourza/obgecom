<?php 
// Database connection
if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

$db = DB::getInstance();

if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../lg'); 
} 
if (isset($_GET['logout'])) {
    logout();
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../lg');
}

// Security: Input validation and sanitization
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m', $date);
    return $d && $d->format('Y-m') === $date;
}

// Pagination configuration
$itemsPerPage = 20;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Filter parameters with validation
$dateFilter = isset($_GET['date_filter']) && validateDate($_GET['date_filter']) ? $_GET['date_filter'] : '';
$statusFilter = isset($_GET['status_filter']) && in_array($_GET['status_filter'], ['active', 'inactive']) ? $_GET['status_filter'] : '';
$cityFilter = isset($_GET['city_filter']) ? sanitizeInput($_GET['city_filter']) : '';
$searchQuery = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

$user = $db->getThisQuery("
    SELECT id, `name`, `role`
    FROM users
    WHERE username = ? OR email = ? OR phone = ?
    LIMIT 1
", [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]);

// Ensure we have a valid user
if (!$user || !isset($user[0]['id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Utilisateur non trouvé',
        'code'    => 'USER_NOT_FOUND'
    ]);
    exit;
}

$user_id = (int)$user[0]['id'];
$userName = $user[0]['name'];
$userRole = $user[0]['role'];

// Vérification de l'abonnement
$subscription = new Subscription($user_id);
$subscriptionStatus = $subscription->getSubscriptionStatus();
$subscriptionAlerts = $subscription->getSubscriptionAlerts();

// Vérifier l'accès aux fonctionnalités
$canAccessFeatures = $subscription->canAccessFeatures();

// Si l'abonnement est expiré ou inexistant, limiter l'accès
$limitedAccess = !$canAccessFeatures;

try {
    
    // Use COALESCE to handle potential NULL values and ROW_NUMBER for deduplication
    $baseQuery = "
        SELECT 
            ROW_NUMBER() OVER (ORDER BY order_count DESC, total_spent DESC) as row_num,
            customer_id,
            customer_name,
            customer_email,
            customer_phone,
            customer_city,
            first_order_date,
            last_order_date,
            order_count,
            total_spent,
            store_name,
            CASE 
                WHEN last_order_date >= DATE_SUB(NOW(), INTERVAL 5 MONTH) 
                THEN 'active' 
                ELSE 'inactive' 
            END as status
        FROM (
            SELECT 
                CONCAT(COALESCE(o.customer_phone, ''), '_', COALESCE(o.customer_email, '')) as customer_id,
                COALESCE(
                    NULLIF(MAX(o.customer_name), ''), 
                    CONCAT('Client ', SUBSTRING(COALESCE(MAX(o.customer_phone), MAX(o.customer_email)), -4))
                ) as customer_name,
                COALESCE(MAX(o.customer_email), 'N/A') as customer_email,
                COALESCE(MAX(o.customer_phone), 'N/A') as customer_phone,
                COALESCE(MAX(o.customer_ville), 'N/A') as customer_city,
                MIN(o.order_date) as first_order_date,
                MAX(o.order_date) as last_order_date,
                COUNT(DISTINCT o.id) as order_count,
                COALESCE(SUM(o.total_amount), 0) as total_spent,
                MAX(s.store_name) as store_name
            FROM orders o
            LEFT JOIN stores s ON o.store_id = s.id
            WHERE (o.customer_phone IS NOT NULL OR o.customer_email IS NOT NULL)
            AND o.user_id = ?  -- Only orders from the logged-in user
            GROUP BY COALESCE(o.customer_phone, COALESCE(o.customer_email, ''))
            HAVING order_count > 0
        ) as deduplicated_customers
    ";
    
    // Build WHERE clause for filters
    $whereConditions = [];
    $params = [$user_id]; // Start with user ID as first parameter
    
    if ($dateFilter) {
        $whereConditions[] = "DATE_FORMAT(last_order_date, '%Y-%m') = ?";
        $params[] = $dateFilter;
    }
    
    if ($statusFilter === 'active') {
        $whereConditions[] = "last_order_date >= DATE_SUB(NOW(), INTERVAL 5 MONTH)";
    } elseif ($statusFilter === 'inactive') {
        $whereConditions[] = "last_order_date < DATE_SUB(NOW(), INTERVAL 5 MONTH)";
    }
    
    if ($cityFilter) {
        $whereConditions[] = "customer_city LIKE ?";
        $params[] = '%' . $cityFilter . '%';
    }
    
    if ($searchQuery) {
        $whereConditions[] = "(customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ? OR customer_city LIKE ?)";
        $searchParam = '%' . $searchQuery . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    // Complete query for filtered results
    $filteredQuery = "SELECT * FROM (" . $baseQuery . ") as final_customers";
    if (!empty($whereConditions)) {
        $filteredQuery .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM (" . $filteredQuery . ") as count_query";
    $totalResult = $db->getThisQuery($countQuery, $params);
    $totalCustomers = $totalResult[0]['total'] ?? 0;
    $totalPages = ceil($totalCustomers / $itemsPerPage);
    
    // Get paginated results
    $paginatedQuery = $filteredQuery . " ORDER BY order_count DESC, total_spent DESC LIMIT ? OFFSET ?";
    $params[] = $itemsPerPage;
    $params[] = $offset;
    
    $customers = $db->getThisQuery($paginatedQuery, $params);
    
    // Get statistics (without pagination) - also filtered by user
    $statsQuery = "SELECT * FROM (" . $baseQuery . ") as stats_customers";
    $allCustomers = $db->getThisQuery($statsQuery, [$user_id]);
    
    $activeThreshold = date('Y-m-d', strtotime('-5 months'));
    $activeCustomers = array_filter($allCustomers, function($customer) use ($activeThreshold) {
        return $customer['last_order_date'] >= $activeThreshold;
    });
    
    // Calculate statistics
    $totalCustomersCount = count($allCustomers);
    $activeCustomersCount = count($activeCustomers);
    $avgOrderValue = $totalCustomersCount > 0 ? 
        array_sum(array_column($allCustomers, 'total_spent')) / array_sum(array_column($allCustomers, 'order_count')) : 0;
    
    $newThisMonth = array_filter($allCustomers, function($customer) {
        $firstOrder = new DateTime($customer['first_order_date']);
        $now = new DateTime();
        return $firstOrder->format('Y-m') === $now->format('Y-m');
    });
    $newThisMonthCount = count($newThisMonth);
    
    // Get top 4 customers
    $topCustomers = array_slice($allCustomers, 0, 4);
    
    // Get unique cities for filter dropdown - filtered by user
    $citiesQuery = "SELECT DISTINCT customer_ville FROM orders 
                    WHERE customer_ville IS NOT NULL AND customer_ville != '' 
                    AND user_id = ? 
                    ORDER BY customer_ville";
    $citiesResult = $db->getThisQuery($citiesQuery, [$user_id]);
    $cities = array_column($citiesResult, 'customer_ville');
    
} catch (Exception $e) {
    
    error_log("Database error in clients.php: " . $e->getMessage());
    $customers = [];
    $totalCustomers = 0;
    $totalPages = 0;
    $topCustomers = [];
    $totalCustomersCount = 0;
    $activeCustomersCount = 0;
    $avgOrderValue = 0;
    $newThisMonthCount = 0;
    $cities = [];
}

// Pagination helper function
function generatePagination($currentPage, $totalPages, $maxVisible = 7) {
    $pagination = [];
    
    if ($totalPages <= $maxVisible) {
        for ($i = 1; $i <= $totalPages; $i++) {
            $pagination[] = $i;
        }
    } else {
        $pagination[] = 1;
        
        if ($currentPage > 4) {
            $pagination[] = '...';
        }
        
        $start = max(2, $currentPage - 2);
        $end = min($totalPages - 1, $currentPage + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i != 1 && $i != $totalPages) {
                $pagination[] = $i;
            }
        }
        
        if ($currentPage < $totalPages - 3) {
            $pagination[] = '...';
        }
        
        if ($totalPages > 1) {
            $pagination[] = $totalPages;
        }
    }
    
    return $pagination;
}

$paginationItems = generatePagination($currentPage, $totalPages);

// Build query string for pagination links
function buildQueryString($excludeParams = []) {
    $params = $_GET;
    foreach ($excludeParams as $param) {
        unset($params[$param]);
    }
    return !empty($params) ? '&' . http_build_query($params) : '';
}

$baseQueryString = buildQueryString(['page']); ?>

<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestion des clients - OBG">
    <title>Clients | OBG</title>
    <link rel="stylesheet" href="../assets/css/clients.css" />
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .main-content {
            width: 80%;
        }

        .btn-filter-apply {
            padding: .1em;
            height: 2.6em;
            margin-top: 1.8em;
            background-color: #9c80fd !important;
        }
        
        .search-container {
            margin-bottom: 1rem;
        }
        
        .search-container input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 300px;
        }
        
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }
        
        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .pagination-btn:hover:not(.disabled) {
            background-color: #f8f9fa;
            border-color: #6c757d;
        }
        
        .pagination-btn.active {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-ellipsis {
            padding: 0.5rem;
            color: #6c757d;
        }
        
        .results-info {
            margin: 1rem 0;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .btn-filter-reset {
            background-color: transparent;
            color: var(--color-text);
            border: 1px solid var(--color-border);
            height: 2.8em !important;
            text-align: center;
            margin-top: 1.6em;
        }

        .whatsapp-link {
            color: #25D366;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .whatsapp-link:hover {
            color: #128C7E;
            text-decoration: underline;
        }

        .whatsapp-icon {
            width: 16px;
            height: 16px;
        }

        .top-navbar-right { justify-content: flex-end; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php 
    $currentPage = 'clients'; 
    require_once('../assets/sidebar.php'); 
    ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation Bar -->

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

        <nav class="top-navbar">
            
            <div class="search-bar">
                <form method="GET" style="display: flex; align-items: center; width: 100%">
                    <input type="hidden" name="date_filter" value="<?= htmlspecialchars($dateFilter) ?>">
                    <input type="hidden" name="status_filter" value="<?= htmlspecialchars($statusFilter) ?>">
                    <input type="hidden" name="city_filter" value="<?= htmlspecialchars($cityFilter) ?>">
                    <input type="text" name="search" placeholder="Rechercher par nom, tél, ville..." value="<?= htmlspecialchars($searchQuery) ?>">
                    <button type="submit" class="search-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                        </svg>
                    </button>
                </form>
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
        <!-- Clients Content -->
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h2>Gestion des clients</h2>
                <div class="action-buttons">
                    <button class="btn-secondary" onclick="exportClients()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                        </svg>
                        Exporter
                    </button>
                </div>
            </div>
            
            <!-- Client Filters -->
            <form method="GET" class="orders-filters">
                <input type="hidden" name="search" value="<?= htmlspecialchars($searchQuery) ?>">
                
                <div class="filter-group">
                    <label for="date_filter">Période:</label>
                    <input type="month" id="date_filter" name="date_filter" value="<?= htmlspecialchars($dateFilter) ?>">
                </div>
                
                <div class="filter-group">
                    <label for="city-filter">Ville:</label>
                    <select id="city-filter" name="city_filter">
                        <option value="">Toutes les villes</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= htmlspecialchars($city) ?>" <?= $cityFilter === $city ? 'selected' : '' ?>>
                                <?= htmlspecialchars($city) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status-filter">Statut:</label>
                    <select id="status-filter" name="status_filter">
                        <option value="">Tous les statuts</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Actif</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-filter-apply">Appliquer</button>
                <a href="?" class="btn-filter-reset">Réinitialiser</a>
            </form>
            
            <!-- Results Info -->
            <?php if ($searchQuery || $dateFilter || $statusFilter || $cityFilter): ?>
            <div class="results-info">
                Affichage de <?= count($customers) ?> client(s) sur <?= $totalCustomers ?> résultat(s) trouvé(s)
                <?php if ($searchQuery): ?>
                    pour la recherche "<?= htmlspecialchars($searchQuery) ?>"
                <?php endif; ?>
                <?php if ($cityFilter): ?>
                    dans la ville "<?= htmlspecialchars($cityFilter) ?>"
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Client Statistics -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total clients</h3>
                    </div>
                    <div class="stat-card-value"><?= number_format($totalCustomersCount) ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Clients actifs</h3>
                    </div>
                    <div class="stat-card-value"><?= number_format($activeCustomersCount) ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Panier moyen</h3>
                    </div>
                    <div class="stat-card-value"><?= number_format($avgOrderValue, 2) ?>D.H</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <h3>Nouveaux ce mois</h3>
                    </div>
                    <div class="stat-card-value"><?= number_format($newThisMonthCount) ?></div>
                </div>
            </div>
            
            <!-- Top Clients -->
            <?php if (!empty($topCustomers)): ?>
            <h3>Clients V.I.P</h3>
            <div class="client-grid">
                <?php foreach ($topCustomers as $index => $customer): 
                    $isActive = $customer['status'] === 'active';
                    $customerSince = date_diff(
                        new DateTime($customer['first_order_date']), 
                        new DateTime()
                    );
                    $customerSinceText = '';
                    if ($customerSince->y > 0) {
                        $customerSinceText .= $customerSince->y . ' an' . ($customerSince->y > 1 ? 's' : '');
                    }
                    if ($customerSince->m > 0) {
                        $customerSinceText .= ($customerSinceText ? ', ' : '') . $customerSince->m . ' mois';
                    }
                    if (empty($customerSinceText)) {
                        $customerSinceText = 'Moins d\'un mois';
                    }
                ?>
                <div class="client-card">
                    <div class="client-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="#9c80fd" viewBox="0 0 16 16">
                            <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                            <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                        </svg>
                    </div>
                    <div class="client-details">
                        <h3><?= htmlspecialchars($customer['customer_name']) ?></h3>
                        <p>Client depuis <?= $customerSinceText ?></p>
                        <span class="client-status <?= $isActive ? 'status-active' : 'status-inactive' ?>">
                            <?= $isActive ? 'Actif' : 'Inactif' ?>
                        </span>
                    </div>
                    <div class="client-stats">
                        <div class="client-stat">
                            <span class="stat-label">Commandes</span>
                            <span class="stat-value"><?= number_format($customer['order_count']) ?></span>
                        </div>
                        <div class="client-stat">
                            <span class="stat-label">Total</span>
                            <span class="stat-value"><?= number_format($customer['total_spent'], 2) ?>D.H</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <br />
            <?php endif; ?>
                
            <h3>Liste des clients</h3>
            
            <?php if (empty($customers)): ?>
                <div class="alert alert-info">
                    <?php if ($searchQuery || $dateFilter || $statusFilter || $cityFilter): ?>
                        Aucun client trouvé avec les critères de recherche actuels.
                    <?php else: ?>
                        Aucun client trouvé dans la base de données.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="clients-table">
                        <thead>
                            <tr>
                                <th style="display: none;">ID</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Tél</th>
                                <th>Date d'inscription</th>
                                <th>Dernière commande</th>
                                <th>Nombre commandes</th>
                                <th>Total dépensé</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $index => $customer): 
                                $isActive = $customer['status'] === 'active';
                                $customerSince = date('d/m/Y', strtotime($customer['first_order_date']));
                                $lastOrderDate = date('d/m/Y', strtotime($customer['last_order_date']));
                                $globalIndex = $offset + $index + 1;
                                
                                // Format phone number for WhatsApp (remove non-numeric characters and add Morocco country code if needed)
                                $whatsappNumber = preg_replace('/[^0-9]/', '', $customer['customer_phone']);
                                if ($whatsappNumber && !str_starts_with($whatsappNumber, '212')) {
                                    if (str_starts_with($whatsappNumber, '0')) {
                                        $whatsappNumber = '212' . substr($whatsappNumber, 1);
                                    } else {
                                        $whatsappNumber = '212' . $whatsappNumber;
                                    }
                                }
                            ?>
                            <tr>
                                <td style="display: none;">CL<?= str_pad($globalIndex, 4, '0', STR_PAD_LEFT) ?></td>
                                <td><?= htmlspecialchars($customer['customer_name']) ?></td>
                                <td><?= htmlspecialchars($customer['customer_email']) ?></td>
                                <td>
                                    <?php if ($customer['customer_phone'] !== 'N/A' && $whatsappNumber): ?>
                                        <a href="https://wa.me/<?= $whatsappNumber ?>" target="_blank" class="whatsapp-link">
                                            <svg class="whatsapp-icon" viewBox="0 0 24 24" fill="currentColor">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.520.149-.174.198-.298.298-.497.099-.198.050-.371-.025-.520-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.465 3.63z"/>
                                            </svg>
                                            <?= htmlspecialchars($customer['customer_phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($customer['customer_phone']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= $customerSince ?></td>
                                <td><?= $lastOrderDate ?></td>
                                <td><?= number_format($customer['order_count']) ?></td>
                                <td><?= number_format($customer['total_spent'], 2) ?>D.H</td>
                                <td>
                                    <span class="client-status <?= $isActive ? 'status-active' : 'status-inactive' ?>">
                                        <?= $isActive ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <!-- Previous button -->
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?= $currentPage - 1 ?><?= $baseQueryString ?>" class="pagination-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                    
                    <!-- Page numbers -->
                    <?php foreach ($paginationItems as $item): ?>
                        <?php if ($item === '...'): ?>
                            <span class="pagination-ellipsis">...</span>
                        <?php elseif ($item == $currentPage): ?>
                            <span class="pagination-btn active"><?= $item ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $item ?><?= $baseQueryString ?>" class="pagination-btn"><?= $item ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    <!-- Next button -->
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?= $currentPage + 1 ?><?= $baseQueryString ?>" class="pagination-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                            </svg>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Sidebar toggle functionality
        document.getElementById('open-sidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.add('active');
        });

        // Export clients function
        function exportClients() {
            // Get current filters
            const urlParams = new URLSearchParams(window.location.search);
            const exportParams = new URLSearchParams();
            
            // Add filters to export
            if (urlParams.get('date_filter')) exportParams.set('date_filter', urlParams.get('date_filter'));
            if (urlParams.get('status_filter')) exportParams.set('status_filter', urlParams.get('status_filter'));
            if (urlParams.get('city_filter')) exportParams.set('city_filter', urlParams.get('city_filter'));
            if (urlParams.get('search')) exportParams.set('search', urlParams.get('search'));
            
            // Add export flag
            exportParams.set('export', 'csv');
            
            // Create download link
            const exportUrl = window.location.pathname + '?' + exportParams.toString();
            window.location.href = exportUrl;
        }

        // Auto-submit form on filter change (optional enhancement)
        document.addEventListener('DOMContentLoaded', function() {
            const dateFilter = document.getElementById('date_filter');
            const statusFilter = document.getElementById('status-filter');
            const cityFilter = document.getElementById('city-filter');
            
            if (dateFilter) {
                dateFilter.addEventListener('change', function() {
                    // Optionally auto-submit on change
                    // this.form.submit();
                });
            }
            
            if (statusFilter) {
                statusFilter.addEventListener('change', function() {
                    // Optionally auto-submit on change
                    // this.form.submit();
                });
            }

            if (cityFilter) {
                cityFilter.addEventListener('change', function() {
                    // Optionally auto-submit on change
                    // this.form.submit();
                });
            }
        });

        // Search form enhancement
        document.querySelector('.search-bar form').addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="search"]');
            if (searchInput.value.trim() === '') {
                // If search is empty, remove the search parameter
                searchInput.name = '';
            }
        });

        // Client card click handler (optional enhancement)
        document.querySelectorAll('.client-card').forEach(card => {
            card.addEventListener('click', function() {
                // Add functionality to view client details
                console.log('Client card clicked');
            });
        });

        // Table row click handler (optional enhancement)
        document.querySelectorAll('.clients-table tbody tr').forEach(row => {
            row.addEventListener('click', function() {
                // Add functionality to view client details
                console.log('Client row clicked');
            });
        });

        // Notification functionality
        document.querySelector('.notification-btn')?.addEventListener('click', function() {
            // Add notification dropdown functionality
            console.log('Notifications clicked');
        });

        // Help functionality
        document.querySelector('.help-btn')?.addEventListener('click', function() {
            // Add help modal or tooltip functionality
            console.log('Help clicked');
        });
    </script>
</body>
</html>