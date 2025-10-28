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
    $userId = isset($_POST['user_id']) ? $_POST['user_id'] : null;
    
    switch($action) {
        case 'toggle_status':
            if ($userId) {
                toggleUserStatus($userId, $db);
            }
            break;
        case 'update_subscription':
            if ($userId && isset($_POST['plan_id']) && isset($_POST['duration'])) {
                updateUserSubscription($userId, $_POST['plan_id'], $_POST['duration'], $db);
            }
            break;
        case 'send_email':
            if ($userId && isset($_POST['subject']) && isset($_POST['message'])) {
                sendEmailToUser($userId, $_POST['subject'], $_POST['message'], $db);
            }
            break;
        case 'export_csv':
            exportUsersToCSV($db, $_GET);
            break;
        case 'bulk_action':
            if (isset($_POST['selected_users']) && isset($_POST['bulk_action_type'])) {
                handleBulkAction($_POST['selected_users'], $_POST['bulk_action_type'], $db);
            }
            break;
    }
}

// Get filter parameters with sanitization
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$plan_filter = isset($_GET['plan_filter']) ? trim($_GET['plan_filter']) : '';
$status_filter = isset($_GET['status_filter']) ? trim($_GET['status_filter']) : '';
$date_range = isset($_GET['date_range']) ? trim($_GET['date_range']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'created_at';
$sort_order = isset($_GET['sort_order']) ? trim($_GET['sort_order']) : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;

// Validate page number
if ($page < 1) $page = 1;

// Build WHERE conditions
$where_conditions = ["u.role != 'super'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($plan_filter)) {
    if ($plan_filter === 'free') {
        $where_conditions[] = "(up.plan_id IS NULL OR up.status != 'active')";
    } else {
        $where_conditions[] = "p.name = ?";
        $params[] = $plan_filter;
    }
}

if (!empty($status_filter)) {
    switch ($status_filter) {
        case 'active':
            $where_conditions[] = "u.is_active = 1";
            break;
        case 'inactive':
            $where_conditions[] = "u.is_active = 0";
            break;
        case 'verified':
            $where_conditions[] = "u.is_verified = 1";
            break;
        case 'unverified':
            $where_conditions[] = "u.is_verified = 0";
            break;
    }
}

// Date range filter
if (!empty($date_range)) {
    switch ($date_range) {
        case 'today':
            $where_conditions[] = "DATE(u.created_at) = CURDATE()";
            break;
        case 'yesterday':
            $where_conditions[] = "DATE(u.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $where_conditions[] = "YEARWEEK(u.created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'last_week':
            $where_conditions[] = "YEARWEEK(u.created_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
            break;
        case 'this_month':
            $where_conditions[] = "MONTH(u.created_at) = MONTH(CURDATE()) AND YEAR(u.created_at) = YEAR(CURDATE())";
            break;
        case 'last_month':
            $where_conditions[] = "MONTH(u.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(u.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            break;
        case 'this_year':
            $where_conditions[] = "YEAR(u.created_at) = YEAR(CURDATE())";
            break;
        case 'last_year':
            $where_conditions[] = "YEAR(u.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))";
            break;
        case 'custom':
            if (!empty($start_date) && !empty($end_date)) {
                $where_conditions[] = "DATE(u.created_at) BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            }
            break;
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT u.id) as total 
               FROM users u
               LEFT JOIN user_plans up ON u.id = up.user_id AND up.status = 'active'
               LEFT JOIN plans p ON up.plan_id = p.id
               $where_clause";
$count_result = $db->getThisQuery($count_query, $params);
$total_users = $count_result[0]['total'] ?? 0;
$total_pages = ceil($total_users / $per_page);
$offset = ($page - 1) * $per_page;

// Validate page number against total pages
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Build main query with improved performance
$users_query = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.is_active,
        u.is_verified,
        u.created_at,
        u.derniere_connexion,
        p.name AS plan_name,
        p.id AS plan_id,
        up.expires_at,
        up.status AS subscription_status,
        up.total_amount AS subscription_amount,
        up.created_at AS subscription_start,
        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders o WHERE o.user_id = u.id AND o.status = 'confirmed') AS total_revenue,
        (SELECT COUNT(*) FROM stores s WHERE s.user_id = u.id) AS store_count,
        (SELECT COUNT(*) FROM shipping_companies sc WHERE sc.user_id = u.id) AS shipping_companies_count
    FROM users u
    LEFT JOIN user_plans up ON up.id = (
        SELECT up2.id 
        FROM user_plans up2 
        WHERE up2.user_id = u.id 
        AND up2.status = 'active' 
        ORDER BY up2.created_at DESC 
        LIMIT 1
    )
    LEFT JOIN plans p ON up.plan_id = p.id
    $where_clause
    ORDER BY $sort_by $sort_order
    LIMIT $offset, $per_page
";

$users = $db->getThisQuery($users_query, $params);

// Get all available plans for subscription management
$plans_query = "SELECT id, `name`, price FROM plans ORDER BY price ASC";
$available_plans = $db->getThisQuery($plans_query);

// Get plan statistics for filters
$plan_stats_query = "
    SELECT 
        COALESCE(p.name, 'Starter') as plan_name,
        COUNT(DISTINCT u.id) as user_count
    FROM users u
    LEFT JOIN user_plans up ON u.id = up.user_id AND up.status = 'active'
    LEFT JOIN plans p ON up.plan_id = p.id
    WHERE u.role != 'super'
    GROUP BY p.name
    ORDER BY user_count DESC
";

$plan_stats = $db->getThisQuery($plan_stats_query);

// Get status statistics
$status_stats_query = "
SELECT 
    COUNT(*) AS total_users,
    SUM(u.is_active) AS active_users,
    SUM(u.is_verified) AS verified_users,
    SUM(
      CASE 
        WHEN EXISTS (
          SELECT 1
          FROM user_plans up2
          JOIN plans p2 ON up2.plan_id = p2.id
          WHERE up2.user_id = u.id
            AND up2.status = 'active'
            AND p2.name != 'Starter'          -- exclude free Starter plan
        ) THEN 1
        ELSE 0
      END
    ) AS paid_users
FROM users u
WHERE u.role != 'super'
";

$status_stats = $db->getThisQuery($status_stats_query)[0];

// Function definitions
function toggleUserStatus($userId, $db) {
    // Get current status
    $current_user = $db->getThisQuery("SELECT is_active, name FROM users WHERE id = ?", [$userId]);
    if (!$current_user) {
        $_SESSION['error_message'] = "Utilisateur non trouvé";
        return;
    }
    
    $new_status = $current_user[0]['is_active'] ? 0 : 1;
    $user_name = $current_user[0]['name'];
    
    // Update status
    $update_result = $db->update('users', $userId, ['is_active' => $new_status]);
    
    if ($update_result) {
        // Log the action
        logAdminAction($db, "Changed user status to " . ($new_status ? 'active' : 'inactive'), $userId);
        $_SESSION['success_message'] = "Statut de $user_name mis à jour avec succès";
    } else {
        $_SESSION['error_message'] = "Erreur lors de la mise à jour du statut";
    }
    
    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit;
}

function updateUserSubscription($userId, $planId, $duration, $db) {
    // Validate inputs
    if (empty($planId)) {
        $_SESSION['error_message'] = "Veuillez sélectionner un plan";
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
        exit;
    }
    
    // Calculate expiration date based on months
    $expires_at = date('Y-m-d H:i:s', strtotime("+$duration months"));
    $now = date('Y-m-d H:i:s');
    $started_at = $now; // Subscription starts immediately
    
    // Get user info for logging
    $user_info = $db->getThisQuery("SELECT name FROM users WHERE id = ?", [$userId]);
    if (!$user_info) {
        $_SESSION['error_message'] = "Utilisateur non trouvé";
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
        exit;
    }
    
    $user_name = $user_info[0]['name'];
    
    // Get plan info
    $plan_info = $db->getThisQuery("SELECT `name`, price FROM plans WHERE id = ?", [$planId]);
    if (!$plan_info) {
        $_SESSION['error_message'] = "Plan non trouvé";
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
        exit;
    }
    
    $plan_name = $plan_info[0]['name'];
    $plan_price = $plan_info[0]['price'];
    $total_amount = $plan_price * $duration;
    
    // 1. Handle user_plans table
    $existing_user_plan = $db->getThisQuery("SELECT id FROM user_plans WHERE user_id = ? AND status = 'active'", [$userId]);
    
    if (!empty($existing_user_plan)) {
        // Update existing user plan
        $update_result = $db->update('user_plans', $existing_user_plan[0]['id'], [
            'plan_id' => $planId,
            'expires_at' => $expires_at,
            'total_amount' => $total_amount,
            'updated_at' => $now
        ]);
        
        if (!$update_result) {
            $_SESSION['error_message'] = "Erreur lors de la mise à jour de l'abonnement user_plans";
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
            exit;
        }
    } else {
        // Create new user plan
        $insert_id = $db->insert('user_plans', [
            'user_id' => $userId,
            'plan_id' => $planId,
            'status' => 'active',
            'total_amount' => $total_amount,
            'expires_at' => $expires_at,
            'created_at' => $now,
            'updated_at' => $now
        ]);
        
        if (!$insert_id) {
            $_SESSION['error_message'] = "Erreur lors de la création de l'abonnement user_plans";
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
            exit;
        }
    }
    
    // 2. Handle subscriptions table (the table your API is checking)
    $existing_subscription = $db->getThisQuery("SELECT id FROM subscriptions WHERE user_id = ? ", [$userId]);
    
    if (!empty($existing_subscription)) {
        // Update existing subscription
        $update_result = $db->update('subscriptions', $existing_subscription[0]['id'], [
            'plan_id' => $planId,
            'expires_at' => $expires_at,
            'status' => 'active',
            'updated_at' => $now
        ]);
        
        if (!$update_result) {
            $_SESSION['error_message'] = "Erreur lors de la mise à jour de l'abonnement subscriptions";
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
            exit;
        }
        
        $_SESSION['success_message'] = "Abonnement de $user_name mis à jour vers $plan_name pour $duration mois";
    } else {
        // Create new subscription
        $insert_id = $db->insert('subscriptions', [
            'user_id' => $userId,
            'plan_id' => $planId,
            'stripe_id' => null,
            'status' => 'active',
            'started_at' => $started_at,
            'expires_at' => $expires_at,
            'created_at' => $now,
            'updated_at' => $now
        ]);
        
        if (!$insert_id) {
            $_SESSION['error_message'] = "Erreur lors de la création de l'abonnement subscriptions";
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
            exit;
        }
        
        $_SESSION['success_message'] = "Nouvel abonnement $plan_name créé pour $user_name pour $duration mois";
    }
    
    // Log the action
    logAdminAction($db, "Updated subscription to $plan_name for $duration month(s)", $userId);

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit;
}

function sendEmailToUser($userId, $subject, $message, $db) {
    // Validate inputs
    if (empty($subject) || empty($message)) {
        $_SESSION['error_message'] = "Veuillez remplir le sujet et le message";
        return;
    }
    
    // Get user info
    $user_info = $db->getThisQuery("SELECT name, email FROM users WHERE id = ?", [$userId]);
    if (!$user_info) {
        $_SESSION['error_message'] = "Utilisateur non trouvé";
        return;
    }
    
    $user_name = $user_info[0]['name'];
    $user_email = $user_info[0]['email'];
    
    // Prepare API call
    $apiUrl = '../controllers/super_admin_email_api.php';
    
    $postData = [
        'action' => 'send_email',
        'user_id' => $userId,
        'subject' => $subject,
        'message' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($postData))
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result['success']) {
            $_SESSION['success_message'] = $result['message'];
            logAdminAction($db, "Sent email to user: $subject", $userId);
        } else {
            $_SESSION['error_message'] = $result['error'] ?? 'Erreur lors de l\'envoi de l\'email';
        }
    } else {
        $_SESSION['error_message'] = 'Erreur de connexion à l\'API: ' . $curl_error;
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit;
}

function exportUsersToCSV($db, $filters) {
    // Build the same query as for display but without pagination
    $where_conditions = ["u.role != 'super'"];
    $params = [];
    
    if (!empty($filters['search'])) {
        $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $search_term = "%{$filters['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $export_query = "
        SELECT 
            u.id,
            u.name,
            u.email,
            u.phone,
            u.is_active,
            u.is_verified,
            u.created_at,
            p.name as plan_name,
            up.expires_at,
            up.status as subscription_status,
            (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as order_count,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders o WHERE o.user_id = u.id AND o.status != 'cancelled') as total_revenue,
            (SELECT COUNT(*) FROM stores s WHERE s.user_id = u.id) as store_count
        FROM users u
        LEFT JOIN user_plans up ON u.id = up.user_id AND up.status = 'active'
        LEFT JOIN plans p ON up.plan_id = p.id
        $where_clause
        ORDER BY u.created_at DESC
    ";
    
    $users = $db->getThisQuery($export_query, $params);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=utilisateurs_obg_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // CSV header
    fputcsv($output, [
        'ID', 'Nom', 'Email', 'Téléphone', 'Statut', 'Email Vérifié', 
        'Date Inscription', 'Abonnement', 'Expiration Abonnement', 
        'Statut Abonnement', 'Nombre Commandes', 'Chiffre d\'Affaires', 'Nombre de Boutiques'
    ], ';');
    
    // Data rows
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['name'],
            $user['email'],
            $user['phone'] ?: 'N/A',
            $user['is_active'] ? 'Actif' : 'Inactif',
            $user['is_verified'] ? 'Oui' : 'Non',
            date('d/m/Y', strtotime($user['created_at'])),
            $user['plan_name'] ?: 'Gratuit',
            $user['expires_at'] ? date('d/m/Y', strtotime($user['expires_at'])) : 'N/A',
            $user['subscription_status'] ?: 'Aucun',
            $user['order_count'],
            $user['total_revenue'],
            $user['store_count']
        ], ';');
    }
    
    fclose($output);
    exit;
}

function handleBulkAction($userIds, $actionType, $db) {
    if (empty($userIds)) {
        $_SESSION['error_message'] = "Aucun utilisateur sélectionné";
        return;
    }
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($userIds as $userId) {
        switch ($actionType) {
            case 'activate':
                $result = $db->update('users', $userId, ['is_active' => 1]);
                break;
            case 'deactivate':
                $result = $db->update('users', $userId, ['is_active' => 0]);
                break;
            case 'delete':
                // Be careful with deletion - maybe just mark as inactive instead
                $result = $db->update('users', $userId, ['is_active' => 0, 'deleted_at' => date('Y-m-d H:i:s')]);
                break;
            default:
                $result = false;
                break;
        }
        
        if ($result) {
            $success_count++;
            logAdminAction($db, "Bulk action: $actionType", $userId);
        } else {
            $error_count++;
        }
    }
    
    if ($success_count > 0) {
        $_SESSION['success_message'] = "Action effectuée sur $success_count utilisateur(s)";
    }
    if ($error_count > 0) {
        $_SESSION['error_message'] = "Erreur sur $error_count utilisateur(s)";
    }
}

function logAdminAction($db, $action, $target_user_id = null) {
    $admin_user = $_SESSION['user']['username'];
    $db->insert('admin_logs', [
        'admin_email' => $admin_user,
        'action' => $action,
        'target_user_id' => $target_user_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'created_at' => date('Y-m-d H:i:s')
    ]);
}

function formatCurrency($amount) {
    $amount = is_null($amount) ? 0 : $amount;
    return number_format((float)$amount, 2, ',', ' ') . ' D.H';
}

function formatDate($date) {
    if (empty($date) || $date == '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date('d/m/Y', strtotime($date));
}

function formatDateTime($date) {
    if (empty($date) || $date == '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date('d/m/Y H:i', strtotime($date));
}

function getPlanBadge($plan_name) {
    if (empty($plan_name)) {
        return '<span class="badge free">Gratuit</span>';
    }
    
    $badges = [
        'Pack 1' => 'pack-1',
        'Pack 2' => 'pack-2', 
        'Pack 3' => 'pack-3',
        'Starter' => 'pack-1',
        'Professional' => 'pack-2',
        'Enterprise' => 'pack-3'
    ];
    
    $class = $badges[$plan_name] ?? 'pack-other';
    return '<span class="badge ' . $class . '">' . htmlspecialchars($plan_name) . '</span>';
}

function getStatusBadge($is_active, $is_verified) {
    $badges = [];
    
    if ($is_active) {
        $badges[] = '<span class="badge active">Actif</span>';
    } else {
        $badges[] = '<span class="badge inactive">Inactif</span>';
    }
    
    if ($is_verified) {
        $badges[] = '<span class="badge verified">Vérifié</span>';
    } else {
        $badges[] = '<span class="badge unverified">Non vérifié</span>';
    }
    
    return implode(' ', $badges);
}

function isSubscriptionExpired($expires_at) {
    if (empty($expires_at) || $expires_at == '0000-00-00 00:00:00') {
        return true;
    }
    return strtotime($expires_at) < time();
}

function getSortIcon($column, $current_sort, $current_order) {
    if ($column !== $current_sort) {
        return '<i class="bi bi-arrow-down-up text-muted"></i>';
    }
    
    if ($current_order === 'ASC') {
        return '<i class="bi bi-arrow-up text-primary"></i>';
    } else {
        return '<i class="bi bi-arrow-down text-primary"></i>';
    }
}

function getSortUrl($column, $current_sort, $current_order, $get_params) {
    $params = $get_params;
    $params['sort_by'] = $column;
    
    if ($column === $current_sort) {
        $params['sort_order'] = $current_order === 'ASC' ? 'DESC' : 'ASC';
    } else {
        $params['sort_order'] = 'DESC';
    }
    
    // Remove page parameter when changing sort
    unset($params['page']);
    
    return '?' . http_build_query($params);
}

// Calculate some additional statistics
$active_percentage = $status_stats['total_users'] > 0 ? 
    round(($status_stats['active_users'] / $status_stats['total_users']) * 100, 1) : 0;
$verified_percentage = $status_stats['total_users'] > 0 ? 
    round(($status_stats['verified_users'] / $status_stats['total_users']) * 100, 1) : 0;
$paid_percentage = $status_stats['total_users'] > 0 ? 
    round(($status_stats['paid_users'] / $status_stats['total_users']) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs | Super Admin OBG</title>
    <link rel="stylesheet" href="../../assets/css/supperDash.css" />
    <link rel="stylesheet" href="../../assets/css/super.css" />
    <link rel="stylesheet" href="../../assets/css/superUsers.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css" rel="stylesheet">
    <style>
        .users-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: var(--shadow-md);
            text-align: center;
            border-top: 4px solid var(--primary-color);
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
            background: var(--primary-color);
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
        
        .stat-percentage {
            font-size: 12px;
            color: var(--success-color);
            font-weight: 600;
        }
        
        .filters-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control, .form-select {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .custom-date-range {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .users-table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th {
            background: #f8fafc;
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        
        .users-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        
        .users-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .users-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .user-email {
            color: var(--secondary-color);
            font-size: 12px;
        }
        
        .user-phone {
            color: var(--secondary-color);
            font-size: 12px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 2px;
        }
        
        .badge.free {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .badge.pack-1 {
            background: #bee3f8;
            color: #2b6cb0;
        }
        
        .badge.pack-2 {
            background: #c6f6d5;
            color: #276749;
        }
        
        .badge.pack-3 {
            background: #faf089;
            color: #744210;
        }
        
        .badge.active {
            background: #c6f6d5;
            color: #276749;
        }
        
        .badge.inactive {
            background: #fed7d7;
            color: #c53030;
        }
        
        .badge.verified {
            background: #bee3f8;
            color: #2b6cb0;
        }
        
        .badge.unverified {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .badge.expired {
            background: #fed7d7;
            color: #c53030;
        }
        
        .badge.active-sub {
            background: #c6f6d5;
            color: #276749;
        }

        .pack-other {
            background-color: #2f2264;
            color: white;
        }
        
        .sortable {
            cursor: pointer;
            user-select: none;
            transition: var(--transition);
        }
        
        .sortable:hover {
            background: #edf2f7;
        }
        
        .actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .action-btn {
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            background: none;
            cursor: pointer;
            transition: var(--transition);
            color: var(--secondary-color);
        }
        
        .action-btn:hover {
            background: #f1f5f9;
            color: var(--primary-color);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            gap: 8px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            background: white;
            color: var(--dark-color);
            text-decoration: none;
            border-radius: 4px;
            transition: var(--transition);
        }
        
        .page-link:hover {
            background: #f1f5f9;
            border-color: #cbd5e0;
        }
        
        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .page-info {
            font-size: 14px;
            color: var(--secondary-color);
            margin: 0 16px;
        }
        
        .no-data {
            padding: 60px 20px;
            text-align: center;
            color: var(--secondary-color);
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .export-btn {
            background: var(--success-color);
            color: white;
        }
        
        .export-btn:hover {
            background: #38a169;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            outline: 0;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-dialog {
            position: relative;
            width: auto;
            margin: 0.5rem;
            pointer-events: none;
            margin: 1.75rem auto;
        }
        
        .modal-content {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0,0,0,.2);
            border-radius: 0.3rem;
            outline: 0;
        }
        
        .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 1rem 1rem;
            border-bottom: 1px solid #dee2e6;
            border-top-left-radius: calc(0.3rem - 1px);
            border-top-right-radius: calc(0.3rem - 1px);
        }
        
        .modal-title {
            margin-bottom: 0;
            line-height: 1.5;
            font-size: 1.25rem;
            font-weight: 500;
        }
        
        .modal-body {
            position: relative;
            flex: 1 1 auto;
            padding: 1rem;
        }
        
        .modal-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            padding: 0.75rem;
            border-top: 1px solid #dee2e6;
            border-bottom-right-radius: calc(0.3rem - 1px);
            border-bottom-left-radius: calc(0.3rem - 1px);
        }
        
        .close {
            padding: 1rem 1rem;
            margin: -1rem -1rem -1rem auto;
            background-color: transparent;
            border: 0;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            color: #000;
            text-shadow: 0 1px 0 #fff;
            opacity: .5;
            cursor: pointer;
        }
        
        .close:hover {
            opacity: .75;
        }
        
        .alert {
            position: relative;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .bulk-actions {
            display: none;
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        .bulk-actions.show {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .select-all-checkbox {
            margin-right: 10px;
        }
        
        @media (max-width: 768px) {
            .users-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .custom-date-range {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .users-table {
                font-size: 14px;
            }
            
            .users-table th,
            .users-table td {
                padding: 12px 8px;
            }
            
            .modal-dialog {
                max-width: 95%;
                margin: 1.75rem auto;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'users';
    require_once('../../assets/sidebarSuper.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <div class="navbar-title">
                <h1>
                    <i class="bi bi-people"></i>
                    Gestion des Utilisateurs
                </h1>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Success Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Header -->
            <div class="users-header">
                <h2 class="page-title">Utilisateurs de la Plateforme</h2>
                <div class="header-actions">
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="export_csv">
                        <button type="submit" class="btn export-btn">
                            <i class="bi bi-download"></i>
                            Exporter CSV
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $status_stats['total_users']; ?></div>
                    <div class="stat-label">Utilisateurs Totaux</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $status_stats['active_users']; ?></div>
                    <div class="stat-label">Utilisateurs Actifs</div>
                    <div class="stat-percentage"><?php echo $active_percentage; ?>%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $status_stats['verified_users']; ?></div>
                    <div class="stat-label">Emails Vérifiés</div>
                    <div class="stat-percentage"><?php echo $verified_percentage; ?>%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $status_stats['paid_users']; ?></div>
                    <div class="stat-label">Utilisateurs Payants</div>
                    <div class="stat-percentage"><?php echo $paid_percentage; ?>%</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-card">
                <form method="get" class="filter-form" id="filterForm">
                    <div class="filter-group">
                        <label for="search">Recherche</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               placeholder="Nom, email ou téléphone..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="plan_filter">Type d'Abonnement</label>
                        <select id="plan_filter" name="plan_filter" class="form-select">
                            <option value="">Tous les abonnements</option>
                            <option value="free" <?php echo $plan_filter === 'free' ? 'selected' : ''; ?>>Gratuit</option>
                            <?php foreach ($plan_stats as $plan): ?>
                                <?php if (!empty($plan['plan_name'])): ?>
                                    <option value="<?php echo htmlspecialchars($plan['plan_name']); ?>" 
                                            <?php echo $plan_filter === $plan['plan_name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($plan['plan_name']); ?> (<?php echo $plan['user_count']; ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status_filter">Statut</label>
                        <select id="status_filter" name="status_filter" class="form-select">
                            <option value="">Tous les statuts</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                            <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Vérifié</option>
                            <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>Non vérifié</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_range">Période d'inscription</label>
                        <select id="date_range" name="date_range" class="form-select">
                            <option value="">Toutes les périodes</option>
                            <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                            <option value="yesterday" <?php echo $date_range === 'yesterday' ? 'selected' : ''; ?>>Hier</option>
                            <option value="this_week" <?php echo $date_range === 'this_week' ? 'selected' : ''; ?>>Cette semaine</option>
                            <option value="last_week" <?php echo $date_range === 'last_week' ? 'selected' : ''; ?>>Semaine dernière</option>
                            <option value="this_month" <?php echo $date_range === 'this_month' ? 'selected' : ''; ?>>Ce mois</option>
                            <option value="last_month" <?php echo $date_range === 'last_month' ? 'selected' : ''; ?>>Mois dernier</option>
                            <option value="this_year" <?php echo $date_range === 'this_year' ? 'selected' : ''; ?>>Cette année</option>
                            <option value="last_year" <?php echo $date_range === 'last_year' ? 'selected' : ''; ?>>Année dernière</option>
                            <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Personnalisé</option>
                        </select>
                    </div>
                    
                    <div class="filter-group custom-date-range" id="custom-date-range" style="<?php echo $date_range === 'custom' ? 'display: grid;' : 'display: none;'; ?>">
                        <label for="start_date">Date de début</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
                        
                        <label for="end_date">Date de fin</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel"></i>
                                Filtrer
                            </button>
                            <a href="./users" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <div>
                    <input type="checkbox" id="selectAll" class="select-all-checkbox">
                    <span id="selectedCount">0 utilisateur(s) sélectionné(s)</span>
                </div>
                <select id="bulkActionType" class="form-select" style="width: auto;">
                    <option value="">Actions groupées</option>
                    <option value="activate">Activer</option>
                    <option value="deactivate">Désactiver</option>
                    <option value="send_email">Envoyer un email</option>
                </select>
                <button type="button" class="btn btn-primary" id="applyBulkAction">Appliquer</button>
                <button type="button" class="btn btn-secondary" id="cancelBulkAction">Annuler</button>
            </div>
            
            <!-- Users Table -->
            <div class="users-table-container">
                <div class="table-header">
                    <h3 class="table-title">Liste des Utilisateurs</h3>
                    <div class="table-actions">
                        <span class="page-info">
                            Affichage de <?php echo min(($page - 1) * $per_page + 1, $total_users); ?> 
                            à <?php echo min($page * $per_page, $total_users); ?> 
                            sur <?php echo $total_users; ?> utilisateurs
                        </span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th style="width: 30px;">
                                    <input type="checkbox" id="selectAllHeader">
                                </th>
                                <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('u.name', $sort_by, $sort_order, $_GET); ?>'">
                                    Utilisateur <?php echo getSortIcon('u.name', $sort_by, $sort_order); ?>
                                </th>
                                <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('p.name', $sort_by, $sort_order, $_GET); ?>'">
                                    Abonnement <?php echo getSortIcon('p.name', $sort_by, $sort_order); ?>
                                </th>
                                <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('u.created_at', $sort_by, $sort_order, $_GET); ?>'">
                                    Date Inscription <?php echo getSortIcon('u.created_at', $sort_by, $sort_order); ?>
                                </th>
                                <th>Expiration</th>
                                <th>Statut</th>
                                <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('order_count', $sort_by, $sort_order, $_GET); ?>'">
                                    Commandes <?php echo getSortIcon('order_count', $sort_by, $sort_order); ?>
                                </th>
                                <th class="sortable" onclick="window.location.href='<?php echo getSortUrl('total_revenue', $sort_by, $sort_order, $_GET); ?>'">
                                    Chiffre d'Affaires <?php echo getSortIcon('total_revenue', $sort_by, $sort_order); ?>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>">
                                        </td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-name" style="color:#000;"><?php echo htmlspecialchars($user['name'] ?: 'Non renseigné'); ?></div>
                                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                                <?php if (!empty($user['phone'])): ?>
                                                    <div class="user-phone"><?php echo htmlspecialchars($user['phone']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getPlanBadge($user['plan_name']); ?>
                                            <?php if (!empty($user['plan_name']) && !empty($user['expires_at'])): ?>
                                                <?php if (isSubscriptionExpired($user['expires_at'])): ?>
                                                    <span class="badge expired">Expiré</span>
                                                <?php else: ?>
                                                    <span class="badge active-sub">Actif</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><?php echo formatDate($user['created_at']); ?></div>
                                            <small style="color: #6b7280; font-size: 12px;">
                                                Dernière connexion: <?php echo formatDate($user['derniere_connexion']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['expires_at'])): ?>
                                                <?php echo formatDate($user['expires_at']); ?>
                                            <?php else: ?>
                                                <span style="color: #6b7280;">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo getStatusBadge($user['is_active'], $user['is_verified']); ?>
                                        </td>
                                        <td>
                                            <div style="text-align: center; font-weight: 600;">
                                                <?php echo $user['order_count']; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--success-color);">
                                                <?php echo formatCurrency($user['total_revenue']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <button class="action-btn view-user" title="Voir détails" data-user-id="<?php echo $user['id']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="action-btn edit-subscription" title="Modifier abonnement" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['name']); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="action-btn send-email" title="Contacter" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['name']); ?>" data-user-email="<?php echo htmlspecialchars($user['email']); ?>">
                                                    <i class="bi bi-envelope"></i>
                                                </button>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="action-btn" title="<?php echo $user['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                                        <i class="bi bi-<?php echo $user['is_active'] ? 'person-check' : 'person-x'; ?>"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="no-data">
                                            <i class="bi bi-people"></i>
                                            <p>Aucun utilisateur trouvé</p>
                                            <?php if (!empty($search) || !empty($plan_filter) || !empty($status_filter) || !empty($date_range)): ?>
                                                <a href="./users" class="btn btn-outline">
                                                    Réinitialiser les filtres
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-link">
                                <i class="bi bi-chevron-double-left"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-link">
                                <i class="bi bi-chevron-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- View User Modal -->
    <div id="viewUserModal" class="modal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Détails de l'utilisateur</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="userDetails">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="text-muted">Chargement des informations...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Subscription Modal -->
    <div id="editSubscriptionModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'Abonnement</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="post" id="subscriptionForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_subscription">
                        <input type="hidden" name="user_id" id="subscription_user_id">
                        
                        <!-- Select Plan -->
                        <div class="mb-3">
                            <label for="plan_id" class="form-label">Plan d'Abonnement</label>
                            <select class="form-select" id="plan_id" name="plan_id" required>
                                <option value="">Sélectionner un plan</option>
                                <?php foreach ($available_plans as $plan): ?>
                                    <option value="<?php echo $plan['id']; ?>">
                                        <?php echo htmlspecialchars($plan['name']); ?> - <?php echo formatCurrency($plan['price']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Duration in months -->
                        <div class="mb-3">
                            <label for="duration" class="form-label">Durée</label>
                            <select class="form-select" id="duration" name="duration" required>
                                <option value="1">1 mois</option>
                                <option value="3">3 mois</option>
                                <option value="12">12 mois</option>
                                <option value="24">24 mois</option>
                            </select>
                        </div>

                        <!-- Expiration Date -->
                        <div class="mb-3">
                            <label class="form-label">Date d'expiration</label>
                            <div id="expiration_date" class="form-control" style="background-color: #f8f9fa;">
                                Calculée automatiquement
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Send Email Modal -->
    <div id="sendEmailModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Envoyer un Email</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <form method="post" id="emailForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="send_email">
                        <input type="hidden" name="user_id" id="email_user_id">
                        
                        <div class="mb-3">
                            <label for="recipient" class="form-label">Destinataire</label>
                            <input type="text" class="form-control" id="recipient" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Sujet</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Envoyer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/l10n/fr.min.js"></script>
    <script>
        // Enhanced table functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Modal functionality
            const modals = {
                viewUser: document.getElementById('viewUserModal'),
                editSubscription: document.getElementById('editSubscriptionModal'),
                sendEmail: document.getElementById('sendEmailModal')
            };
            
            const closeButtons = document.querySelectorAll('.close, .btn-secondary[data-dismiss="modal"]');
            
            // Close modal function
            function closeModal(modal) {
                modal.classList.remove('show');
                restoreBodyScroll();
            }
            
            // Show modal function
            function showModal(modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            
            // Close modals when clicking close buttons
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    closeModal(modal);
                });
            });
            
            // Close modal when clicking outside
            Object.values(modals).forEach(modal => {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal(this);
                    }
                });
            });

            // Function to restore body scroll
            function restoreBodyScroll() {
                document.body.style.overflow = 'auto';
            }

            // Date range functionality
            const dateRangeSelect = document.getElementById('date_range');
            const customDateRange = document.getElementById('custom-date-range');
            
            if (dateRangeSelect) {
                dateRangeSelect.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        customDateRange.style.display = 'grid';
                    } else {
                        customDateRange.style.display = 'none';
                    }
                });
            }

            // Initialize flatpickr for date inputs
            flatpickr("#start_date", {
                dateFormat: "Y-m-d",
                locale: "fr",
                maxDate: new Date()
            });
            
            flatpickr("#end_date", {
                dateFormat: "Y-m-d",
                locale: "fr",
                minDate: document.getElementById('start_date')?.value,
                maxDate: new Date()
            });

            // Bulk actions functionality
            const bulkActions = document.getElementById('bulkActions');
            const selectAllHeader = document.getElementById('selectAllHeader');
            const selectAll = document.getElementById('selectAll');
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            const selectedCount = document.getElementById('selectedCount');
            const bulkActionType = document.getElementById('bulkActionType');
            const applyBulkAction = document.getElementById('applyBulkAction');
            const cancelBulkAction = document.getElementById('cancelBulkAction');

            function updateSelectedCount() {
                const selected = document.querySelectorAll('.user-checkbox:checked');
                selectedCount.textContent = `${selected.length} utilisateur(s) sélectionné(s)`;
                
                if (selected.length > 0) {
                    bulkActions.classList.add('show');
                } else {
                    bulkActions.classList.remove('show');
                }
            }

            // Select all functionality
            if (selectAllHeader) {
                selectAllHeader.addEventListener('change', function() {
                    userCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    selectAll.checked = this.checked;
                    updateSelectedCount();
                });
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    userCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    selectAllHeader.checked = this.checked;
                    updateSelectedCount();
                });
            }

            userCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Apply bulk action
            if (applyBulkAction) {
                applyBulkAction.addEventListener('click', function() {
                    const action = bulkActionType.value;
                    const selectedUsers = Array.from(document.querySelectorAll('.user-checkbox:checked'))
                        .map(checkbox => checkbox.value);
                    
                    if (!action) {
                        alert('Veuillez sélectionner une action');
                        return;
                    }
                    
                    if (selectedUsers.length === 0) {
                        alert('Veuillez sélectionner au moins un utilisateur');
                        return;
                    }
                    
                    if (action === 'send_email') {
                        // Handle bulk email sending
                        alert(`Envoi d'email à ${selectedUsers.length} utilisateur(s)`);
                        // Implement bulk email functionality
                    } else {
                        // Submit bulk action form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';
                        
                        const actionInput = document.createElement('input');
                        actionInput.name = 'action';
                        actionInput.value = 'bulk_action';
                        form.appendChild(actionInput);
                        
                        const actionTypeInput = document.createElement('input');
                        actionTypeInput.name = 'bulk_action_type';
                        actionTypeInput.value = action;
                        form.appendChild(actionTypeInput);
                        
                        selectedUsers.forEach(userId => {
                            const input = document.createElement('input');
                            input.name = 'selected_users[]';
                            input.value = userId;
                            form.appendChild(input);
                        });
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }

            // Cancel bulk action
            if (cancelBulkAction) {
                cancelBulkAction.addEventListener('click', function() {
                    userCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    selectAll.checked = false;
                    selectAllHeader.checked = false;
                    bulkActions.classList.remove('show');
                });
            }

            // User details modal handler
            document.querySelectorAll('.view-user').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const modal = document.getElementById('viewUserModal');
                    const detailsDiv = document.getElementById('userDetails');
                    
                    // Show elegant loading spinner
                    detailsDiv.innerHTML = `
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <p class="text-muted">Chargement des informations...</p>
                        </div>
                    `;
                    
                    showModal(modal);
                    
                    // Fetch user info via AJAX
                    fetch('../controllers/getUserDetailsApi.php?id=' + userId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            detailsDiv.innerHTML = `
                                <div class="alert alert-danger d-flex align-items-center" role="alert">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <div>${data.error}</div>
                                </div>
                            `;
                            return;
                        }
                        
                        // Get status badge class
                        const getStatusBadge = (status) => {
                            const statusMap = {
                                'active': 'success',
                                'actif': 'success',
                                'expired': 'danger',
                                'expiré': 'danger',
                                'pending': 'warning',
                                'en attente': 'warning'
                            };
                            const badgeClass = statusMap[status?.toLowerCase()] || 'secondary';
                            return `<span class="badge bg-${badgeClass}">${status}</span>`;
                        };
                        
                        detailsDiv.innerHTML = `
                            <!-- User Information Section -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-person me-2"></i>Informations de l'utilisateur
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                                <i class="bi bi-person-circle text-primary me-3 fs-4"></i>
                                                <div>
                                                    <small class="text-muted d-block">Nom</small>
                                                    <strong>${data.name || '—'}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                                <i class="bi bi-envelope text-info me-3 fs-4"></i>
                                                <div>
                                                    <small class="text-muted d-block">Email</small>
                                                    <strong>${data.email || '—'}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                                <i class="bi bi-telephone text-success me-3 fs-4"></i>
                                                <div>
                                                    <small class="text-muted d-block">Téléphone</small>
                                                    <strong>${data.phone || '—'}</strong>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center p-3 bg-light rounded">
                                                <i class="bi bi-calendar-plus text-warning me-3 fs-4"></i>
                                                <div>
                                                    <small class="text-muted d-block">Créé le</small>
                                                    <strong>${data.created_at || '—'}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Subscription Section -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-box me-2"></i>Abonnement Actuel
                                    </h6>
                                </div>
                                <div class="card-body">
                                    ${data.subscription ? `
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <small class="text-muted d-block mb-1">Plan</small>
                                                    <h6 class="mb-0 text-primary">
                                                        <i class="bi bi-star me-1"></i>${data.subscription.plan_name}
                                                    </h6>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <small class="text-muted d-block mb-1">Statut</small>
                                                    <div>${getStatusBadge(data.subscription.status)}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded text-center">
                                                    <small class="text-muted d-block mb-1">Montant</small>
                                                    <h5 class="mb-0 text-success">
                                                        ${data.subscription.amount} <small>MAD</small>
                                                    </h5>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded">
                                                    <small class="text-muted d-block mb-1">Début</small>
                                                    <strong>${data.subscription.start}</strong>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="p-3 border rounded">
                                                    <small class="text-muted d-block mb-1">Expiration</small>
                                                    <strong>${data.subscription.expires}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    ` : `
                                        <div class="text-center py-4">
                                            <i class="bi bi-box-open text-muted mb-3" style="font-size: 3rem;"></i>
                                            <p class="text-muted mb-0">Aucun abonnement actif</p>
                                        </div>
                                    `}
                                </div>
                            </div>
                            
                            <!-- Statistics Section -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-graph-up me-2"></i>Statistiques
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="p-4 bg-light rounded text-center">
                                                <i class="bi bi-cart text-primary mb-2" style="font-size: 2rem;"></i>
                                                <h3 class="mb-1">${data.stats?.order_count || 0}</h3>
                                                <small class="text-muted">Commandes Totales</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-4 bg-light rounded text-center">
                                                <i class="bi bi-currency-dollar text-success mb-2" style="font-size: 2rem;"></i>
                                                <h3 class="mb-1">${data.stats?.total_revenue || 0} <small>MAD</small></h3>
                                                <small class="text-muted">Chiffre d'Affaires</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    })
                    .catch(error => {
                        detailsDiv.innerHTML = `
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <div>Erreur lors du chargement des données. Veuillez réessayer.</div>
                            </div>
                        `;
                        console.error('Error fetching user details:', error);
                    });
                });
            });
            
            // Edit subscription
            document.querySelectorAll('.edit-subscription').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name');

                    // Update modal title and hidden input
                    document.getElementById('subscription_user_id').value = userId;
                    document.querySelector('#editSubscriptionModal .modal-title').textContent =
                        `Modifier l'abonnement - ${userName}`;

                    // Reset modal fields while loading
                    document.getElementById('plan_id').value = '';
                    document.getElementById('duration').value = '1';
                    document.getElementById('expiration_date').textContent = 'Chargement...';

                    // Fetch user's current subscription data
                    fetch(`../controllers/get_subscription.php?user_id=${userId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Prefill plan
                                document.getElementById('plan_id').value = data.plan_id;

                                // Prefill duration in months (default to 1 if null)
                                document.getElementById('duration').value = data.duration || '1';

                                // Prefill expiration date (formatted)
                                const expEl = document.getElementById('expiration_date');
                                if (data.expires_at) {
                                    const date = new Date(data.expires_at);
                                    expEl.textContent = date.toLocaleDateString('fr-FR', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric'
                                    });
                                } else {
                                    expEl.textContent = 'Calculée automatiquement';
                                }
                            } else {
                                alert("Impossible de charger les informations de l'abonnement.");
                                document.getElementById('expiration_date').textContent = 'Calculée automatiquement';
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert("Erreur de chargement des données.");
                        })
                        .finally(() => {
                            // Show modal
                            showModal(modals.editSubscription);
                        });
                });
            });
            
            // Calculate expiration date
            document.getElementById('duration').addEventListener('input', function() {
                const months = parseInt(this.value) || 0;
                const expirationEl = document.getElementById('expiration_date');

                if (months > 0) {
                    const expirationDate = new Date();
                    expirationDate.setMonth(expirationDate.getMonth() + months);

                    // Format date in French locale (e.g., "5 octobre 2025")
                    const formattedDate = expirationDate.toLocaleDateString('fr-FR', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });

                    expirationEl.textContent = formattedDate;
                } else {
                    expirationEl.textContent = 'Calculée automatiquement';
                }
            });
            
            // Send email
            document.querySelectorAll('.send-email').forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name');
                    const userEmail = this.getAttribute('data-user-email');
                    
                    document.getElementById('email_user_id').value = userId;
                    document.getElementById('recipient').value = `${userName} <${userEmail}>`;
                    
                    // Pre-fill subject with user name for personalization
                    document.getElementById('subject').value = `Message important pour ${userName}`;
                    
                    showModal(modals.sendEmail);
                });
            });

            // Update the email form submission to use AJAX
            document.getElementById('emailForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                
                // Show loading state
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Envoi en cours...';
                submitBtn.disabled = true;
                
                // Prepare API data
                const apiData = {
                    action: 'send_email',
                    user_id: formData.get('user_id'),
                    subject: formData.get('subject'),
                    message: formData.get('message')
                };
                
                // Send via API
                fetch('../controllers/super_admin_email_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(apiData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showNotification(data.message, 'success');
                        
                        // Close modal
                        closeModal(modals.sendEmail);
                        
                        // Reset form
                        this.reset();
                        
                        // Reload page to show success message
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showNotification(data.error || 'Erreur lors de l\'envoi', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Erreur de connexion', 'error');
                })
                .finally(() => {
                    // Restore button
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
            });

            // Add notification function
            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} alert-dismissible fade show`;
                notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1060; min-width: 300px;';
                notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(notification);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 5000);
            }

            // Auto-submit form on filter change
            const filterSelects = document.querySelectorAll('#plan_filter, #status_filter, #date_range');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });
            
            // Search debouncing
            let searchTimeout;
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.form.submit();
                    }, 500);
                });
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.getElementById('search');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
            
            // Escape to clear search and close modals
            if (e.key === 'Escape') {
                const searchInput = document.getElementById('search');
                if (searchInput && searchInput.value) {
                    searchInput.value = '';
                    searchInput.form.submit();
                }
                
                // Close any open modals
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                    document.body.style.overflow = 'auto';
                });
            }
        });
    </script>
</body>
</html>