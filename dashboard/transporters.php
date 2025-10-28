<?php if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../login.php'); 
} 
if (isset($_GET['logout'])) {
    logout();
}


$db = DB::getInstance();
$user = $db->getThisQuery(
    "SELECT id, `name`, `role` 
     FROM users 
     WHERE username = ? OR email = ? OR phone = ? 
     LIMIT 1", 
    [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]
);

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

function logout() {

    $user = new User();
    $user->logout();
    Redirect::to('../login.php');
}

$supportedCarriers = [
    ['id' => 'ozonexpress', 'name' => 'OzonExpress', 'logo' => 'ozone.jpeg'],
    ['id' => 'sendit', 'name' => 'Sendit', 'logo' => 'sendit.jpeg'],
    ['id' => 'chronopost', 'name' => 'Chronopost', 'logo' => 'chronopost.jpeg'],
    ['id' => 'forcelog', 'name' => 'Forcelog', 'logo' => 'f.jpeg'],
    ['id' => 'cathedis', 'name' => 'Cathedis', 'logo' => 'cathedis.jpeg'],
    ['id' => 'power', 'name' => 'Power Delivery', 'logo' => 'power.jpeg']
];

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestion des sociétés de livraison - OBG">
    <title>sociétés de livraison | OBG</title>
    <link rel="stylesheet" href="../assets/css/transporteurs.css" />
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #9c80fd;
            --secondary-color: #5f34d9;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        /* Main layout improvements */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: var(--transition);
        }

        .dashboard-content {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-top: 20px;
        }

        /* Top navbar */
        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 15px 25px;
            margin-bottom: 20px;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: #f5f7fa;
            border-radius: 50px;
            padding: 8px 15px;
            width: 400px;
            transition: var(--transition);
        }

        .search-bar:focus-within {
            box-shadow: 0 0 0 2px rgba(74, 107, 223, 0.2);
        }

        .search-bar input {
            border: none;
            background: transparent;
            padding: 5px 10px;
            width: 100%;
            outline: none;
            font-size: 14px;
        }

        .search-btn {
            background: transparent;
            border: none;
            color: var(--secondary-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .top-navbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-btn, .help-btn {
            background: transparent;
            border: none;
            color: var(--secondary-color);
            cursor: pointer;
            position: relative;
            padding: 5px;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .notification-btn:hover, .help-btn:hover {
            background: #f0f2f5;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Dashboard header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-content h2 {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            color: var(--dark-color);
        }

        .header-subtitle {
            color: var(--secondary-color);
            font-size: 14px;
            margin: 5px 0 0;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        /* Buttons */
        .btn-primary, .btn-secondary, .btn-refresh {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: #f0f4ff;
            transform: translateY(-2px);
        }

        .btn-refresh {
            background: var(--info-color);
            color: white;
        }

        .btn-refresh:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        /* Filters */
        .orders-filters {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            padding: 15px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            color: var(--secondary-color);
        }

        .filter-group select {
            padding: 8px 12px;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            background: white;
            font-size: 14px;
            min-width: 150px;
        }

        .btn-filter-apply, .btn-filter-reset {
            padding: 8px 16px;
            border-radius: var(--border-radius);
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-filter-apply {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-filter-apply:hover {
            background: #3a5bd0;
        }

        .btn-filter-reset {
            background: white;
            color: var(--secondary-color);
            border: 1px solid #ddd;
        }

        .btn-filter-reset:hover {
            background: #f8f9fa;
        }

        /* Stats cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .stat-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .stat-card-icon svg {
            width: 24px;
            height: 24px;
        }

        .stat-card-icon.active {
            background: var(--success-color);
        }

        .stat-card-icon.inactive {
            background: var(--danger-color);
        }

        .stat-card-icon.transit {
            background: var(--warning-color);
        }

        .stat-card-icon.default {
            background: var(--primary-color);
        }

        .stat-card-content {
            flex: 1;
        }

        .stat-card-header h3 {
            font-size: 14px;
            color: var(--secondary-color);
            margin: 0;
            font-weight: 500;
        }

        .stat-card-value {
            font-size: 24px;
            font-weight: 600;
            margin-top: 5px;
            color: var(--dark-color);
        }

        /* Section headers */
        .section-header {
            margin: 30px 0 15px;
        }

        .section-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        /* Carrier grid */
        .carrier-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: #666;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .carrier-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
        }

        .carrier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .carrier-card.inactive {
            opacity: 0.8;
            background: #f9f9f9;
        }

        .carrier-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 16px;
            background: #f5f7fa;
            border-bottom: 1px solid #eee;
        }

        .carrier-logo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .carrier-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .carrier-logo svg {
            width: 40px;
            height: 40px;
            color: var(--primary-color);
        }

        .carrier-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #e3f7e8;
            color: var(--success-color);
        }

        .status-inactive {
            background: #ffebee;
            color: var(--danger-color);
        }

        .carrier-details {
            padding: 16px;
            flex-grow: 1;
        }

        .carrier-details h3 {
            margin: 0 0 8px;
            color: var(--dark-color);
            font-size: 18px;
        }

        .carrier-description {
            margin: 0 0 16px;
            color: var(--secondary-color);
            font-size: 14px;
            line-height: 1.5;
        }

        .carrier-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 16px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
            font-size: 13px;
        }

        .meta-item svg {
            color: #777;
        }

        .carrier-actions {
            display: flex;
            border-top: 1px solid #eee;
            padding: 8px;
        }

        .btn-action {
            flex: 1;
            background: none;
            border: none;
            padding: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            color: #666;
        }

        .btn-action:hover {
            background: #f5f7fa;
            color: var(--primary-color);
        }

        .btn-action svg {
            transition: transform 0.2s;
        }

        .btn-action:hover svg {
            transform: scale(1.1);
        }

        .btn-toggle.active {
            color: var(--success-color);
        }

        .btn-toggle.inactive {
            color: var(--danger-color);
        }

        .btn-edit {
            color: var(--primary-color);
        }

        .btn-delete {
            color: var(--danger-color);
        }

        /* Table styles */
        .table-container {
            overflow-x: auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }

        .tracking-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tracking-table th, .tracking-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .tracking-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 14px;
        }

        .tracking-table tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-delivered {
            background: #e3f7e8;
            color: var(--success-color);
        }

        .status-shipped {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-in_transit {
            background: #fff8e1;
            color: #ff8f00;
        }

        .status-pending {
            background: #f5f5f5;
            color: #757575;
        }

        .status-cancelled {
            background: #ffebee;
            color: var(--danger-color);
        }

        .tracking-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .tracking-link:hover {
            text-decoration: underline;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            margin-top: 20px;
        }

        .pagination-btn, .pagination-page {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            border: 1px solid #ddd;
            background: white;
            color: var(--dark-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .pagination-btn:hover, .pagination-page:hover {
            background: #f5f5f5;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-page.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination-ellipsis {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary-color);
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            max-width: 800px;
            margin: 50px auto;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            font-size: 20px;
            color: var(--dark-color);
        }

        .modal-close {
            font-size: 24px;
            color: var(--secondary-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--danger-color);
        }

        .modal-body {
            padding: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(74, 107, 223, 0.2);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        /* Supported carriers grid in modal */
        .supported-carriers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .supported-carrier {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .supported-carrier:hover {
            border-color: var(--primary-color);
            background: #f5f7fa;
        }

        .supported-carrier.selected {
            border-color: var(--primary-color);
            background: #f0f4ff;
        }

        .supported-carrier-logo {
            width: 50px;
            height: 50px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .supported-carrier-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .supported-carrier-name {
            font-size: 13px;
            text-align: center;
            color: var(--dark-color);
            font-weight: 500;
        }

        /* Loading and error states */
        .loading {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 2s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error-message, .success-message {
            padding: 15px;
            border-radius: var(--border-radius);
            margin: 20px 0;
            border: 1px solid transparent;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding-top: 70px;
            }
        }

        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-bar {
                width: 100%;
                margin-bottom: 0px;
            }

            .action-buttons {

                flex-direction: column;
            }
            
            .orders-filters {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-group select {
                width: 100%;
            }
            
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            .carrier-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                width: 100%;
            }
            
            .btn-primary, .btn-secondary, .btn-refresh {
                width: 100%;
                justify-content: center;
            }
            
            .modal-content {
                margin: 20px auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'transporteurs'; 
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
                <input type="text" placeholder="Rechercher un transporteur..." id="search-carrier">
                <button class="search-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                    </svg>
                </button>
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
        
        <!-- Transporteurs Content -->
        <div class="dashboard-content">
            <div class="dashboard-header">
                <div class="header-content">
                    <h2>Gestion des sociétés de livraison</h2>
                    <p class="header-subtitle">Gérez vos partenaires de livraison et suivez leurs performances</p>
                </div>
                <div class="action-buttons">
                    <button class="btn-primary" id="add-carrier-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 2a.5.5 0 0 1 .5.5v5h5a.5.5 0 0 1 0 1h-5v5a.5.5 0 0 1-1 0v-5h-5a.5.5 0 0 1 0-1h5v-5A.5.5 0 0 1 8 2Z"/>
                        </svg>
                        Ajouter un transporteur
                    </button>

                    <button class="btn-secondary" id="export-carriers">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                        </svg>
                        Exporter
                    </button>

                    <button class="btn-refresh" id="refresh-data">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                            <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                        </svg>
                        Actualiser
                    </button>
                </div>
            </div>
            
            <!-- Transporteur Filters -->
            <div class="orders-filters">
                <!-- Statut -->
                <div class="filter-group">
                    <label for="status-filter">Statut</label>
                    <select id="status-filter">
                        <option value="">Tous les statuts</option>
                        <option value="1">Actif</option>
                        <option value="0">Inactif</option>
                    </select>
                </div>

                <!-- Apply / Reset Buttons -->
                <button class="btn-filter-apply">Appliquer</button>
                <button class="btn-filter-reset">Réinitialiser</button>
            </div>

            <!-- Loading indicator -->
            <div id="loading" class="loading" style="display: none;">
                <div class="loader"></div>
                <p>Chargement des données...</p>
            </div>
            
            <!-- Error message -->
            <div id="error-message" class="error-message" style="display: none;">
                <p>Erreur lors du chargement des données. Veuillez réessayer.</p>
            </div>
            
            <!-- Success message -->
            <div id="success-message" class="success-message" style="display: none;">
                <p></p>
            </div>
            
            <!-- Transporteur Statistics -->
            <div class="stats-cards" id="stats-cards">
                <div class="stat-card">
                    <div class="stat-card-icon default">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5v-7z"/>
                        </svg>
                    </div>
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <h3>Total Sociétés de Livraison</h3>
                        </div>
                        <div class="stat-card-value" id="total-carriers">0</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon active">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.97 4.97a.235.235 0 0 0-.02.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.061L6.97 11.03a.75.75 0 0 0 1.079-.02L11.031 6.75a.75.75 0 0 0-1.061-1.06z"/>
                        </svg>
                    </div>
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <h3>Actifs</h3>
                        </div>
                        <div class="stat-card-value" id="active-carriers">0</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon inactive">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                        </svg>
                    </div>
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <h3>Inactifs</h3>
                        </div>
                        <div class="stat-card-value" id="inactive-carriers">0</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon transit">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                            <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                        </svg>
                    </div>
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <h3>Commandes en cours</h3>
                        </div>
                        <div class="stat-card-value" id="orders-in-transit">0</div>
                    </div>
                </div>
            </div>
            
            <!-- Transporteurs List -->
            <div class="section-header">
                <h3>Sociétés de Livraison Disponibles</h3>
            </div>
            <div class="carrier-grid" id="carrier-grid">
                <!-- Carriers will be dynamically loaded here -->
            </div>
            
            <!-- Recent Orders Table -->
            <div class="section-header">
                <h3 style="margin-top: 1.5em;">Dernières commandes expédiées</h3>
            </div>
            <div class="table-container">
                <table class="tracking-table">
                    <thead>
                        <tr>
                            <th>N° Commande</th>
                            <th>Sociétés de Livraison</th>
                            <th>N° de suivi</th>
                            <th>Client</th>
                            <th>Date d'expédition</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="orders-table-body">
                        <!-- Orders will be dynamically loaded here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination for Orders -->
            <div class="pagination" id="orders-pagination">
                <button class="pagination-btn prev" id="prev-orders" disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                    </svg>
                </button>
                <div class="pagination-pages" id="orders-pages">
                    <button class="pagination-page active">1</button>
                </div>
                <button class="pagination-btn next" id="next-orders">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
            </div>
        </div>
    </main>
    
    <!-- Add Carrier Modal -->
    <div id="add-carrier-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter un transporteur</h3>
                <span class="modal-close">&times;</span>
            </div>
            
            <form id="add-carrier-form">
                <div class="form-group">
                    <label for="carrier-type">Transporteur*</label>
                    <select id="carrier-type" name="carrier_type" required>
                        <option value="">Sélectionnez un transporteur</option>
                        <?php foreach ($supportedCarriers as $carrier): ?>
                            <option value="<?php echo htmlspecialchars($carrier['id']); ?>">
                                <?php echo htmlspecialchars($carrier['name']); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="custom">Autre transporteur</option>
                    </select>
                </div>

                <div class="form-group" id="custom-name-group" style="display: none;">
                    <label for="custom-carrier-name">Nom personnalisé*</label>
                    <input type="text" id="custom-carrier-name" name="custom_name" placeholder="Entrez le nom du transporteur">
                </div>

                <div class="form-group">
                    <label for="carrier-name">Nom d'affichage (optionnel)</label>
                    <input type="text" id="carrier-name" name="display_name" placeholder="Laissez vide pour utiliser le nom par défaut">
                </div>
                
                <div class="form-group">
                    <label>Transporteurs supportés</label>
                    <div class="supported-carriers-grid">
                        <?php foreach ($supportedCarriers as $carrier): ?>
                            <div class="supported-carrier" data-carrier-id="<?php echo htmlspecialchars($carrier['id']); ?>">
                                <div class="supported-carrier-logo">
                                    <?php if (isset($carrier['logo']) && file_exists("../assets/img/carriers/" . $carrier['logo'])): ?>
                                        <img src="../assets/img/carriers/<?php echo htmlspecialchars($carrier['logo']); ?>" alt="<?php echo htmlspecialchars($carrier['name']); ?>">
                                    <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M3 13.5L2.25 12H7.5l.75-1.5H2L1 9h14l-1 1.5h-.5L13 9h9l1 1.5H15l.75 1.5H21.75L21 13.5h-6.75l-.75 1.5h8.5L23 15H9l1 1.5h.5l.75 1.5H3.75L3 16.5h5.25l-.75 1.5H1.5L1 18l.75 1.5h12L13 18h9.25l.75 1.5H22l-.75-1.5H19.5l.75-1.5H16.5l-.75-1.5H20l1-1.5h-4.5l-.75-1.5H19l1-1.5h-5.25l-.75-1.5H18L19.5 9H21l-1.5 3H22l.75-1.5H24L22.5 9H23l-1.5 3h.75L24 10.5H0"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <div class="supported-carrier-name"><?php echo htmlspecialchars($carrier['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="api-url">URL de l'API*</label>
                    <input type="url" id="api-url" name="api_url" placeholder="https://api.transporteur.com/v1" required>
                    <small class="text-muted">URL de base de l'API du transporteur</small>
                </div>

                <div class="form-group">
                    <label for="carrier-api-key">Clé API</label>
                    <input type="password" id="carrier-api-key" name="api_key" placeholder="Votre clé API">
                    <small class="text-muted">Optionnel - peut être configuré plus tard</small>
                </div>
                
                <div class="form-group">
                    <label for="carrier-api-secret">Secret API</label>
                    <input type="password" id="carrier-api-secret" name="api_secret" placeholder="Votre secret API">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary cancel-btn">Annuler</button>
                    <button type="submit" class="btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Carrier Modal -->
    <div id="edit-carrier-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifier le transporteur</h3>
                <span class="modal-close">&times;</span>
            </div>
            <form id="edit-carrier-form">
                <input type="hidden" id="edit-carrier-id" name="id">
                
                <!-- Nom du transporteur affiché mais non modifiable -->
                <div class="form-group">
                    <label for="edit-carrier-name">Nom du transporteur</label>
                    <input type="text" id="edit-carrier-name" name="name" disabled>
                </div>

                <div class="form-group">
                    <label for="edit-carrier-api-url">API URL*</label>
                    <input type="text" id="edit-carrier-api-url" name="api_url" 
                        placeholder="https://api.exemple.com/v1/endpoint" required>
                </div>

                <div class="form-group">
                    <label for="edit-carrier-tracking-url">URL de suivi </label>
                    <input type="text" id="edit-carrier-tracking-url" name="tracking_url" 
                        placeholder="https://exemple.com/track/{tracking_number}">
                </div>
                
                <div class="form-group">
                    <label for="edit-carrier-status">Statut*</label>
                    <select id="edit-carrier-status" name="is_active" required>
                        <option value="1">Actif</option>
                        <option value="0">Inactif</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary cancel-btn">Annuler</button>
                    <button type="submit" class="btn-primary">Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Order Details Modal -->
    <div id="order-details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Détails de la commande</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div id="order-details-content">
                <!-- Order details will be loaded here -->
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary cancel-btn">Fermer</button>
            </div>
        </div>
    </div>

    <style>

        .orders-container {
            margin: 20px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            background: white;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: white;
        }

        .orders-table thead {
            background: linear-gradient(135deg, #9c80fd 0%, #5f34d9 100%);
            color: white;
        }

        .orders-table th {
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border: none;
        }

        .orders-table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid #f1f5f9;
        }

        .orders-table tbody tr:hover {
            background: linear-gradient(90deg, #f8fafc 0%, #e2e8f0 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .orders-table td {
            padding: 16px 12px;
            font-size: 14px;
            color: #475569;
            vertical-align: middle;
            border: none;
        }

        /* Order Number Styling */
        .orders-table td:first-child strong {
            color: #1e293b;
            font-size: 15px;
            font-weight: 700;
        }

        /* Tracking Link Styling */
        .tracking-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: inline-block;
        }

        .tracking-link:hover {
            background: #dbeafe;
            color: #1d4ed8;
            text-decoration: none;
        }

        /* Status Badge Styling */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-shipped {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .status-delivered {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
        }

        .status-pending {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .status-cancelled {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .status-unknown {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        /* Action Buttons */
        .btn-action {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border: none;
            padding: 8px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            margin-right: 6px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
        }

        .btn-action:active {
            transform: translateY(0);
        }

        .btn-action svg {
            width: 16px;
            height: 16px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .orders-container {
                margin: 10px;
                border-radius: 8px;
            }

            .orders-table th,
            .orders-table td {
                padding: 12px 8px;
                font-size: 13px;
            }

            .btn-action {
                padding: 6px;
                margin-right: 4px;
            }

            .status-badge {
                padding: 4px 8px;
                font-size: 11px;
            }
        }

        /* Loading Animation */
        .orders-loading {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }

        .orders-loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Empty State */
        .orders-empty {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .orders-empty svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Order Details Modal Styling */
        .order-details-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }

        /* Header Section */
        .order-header {
            background: linear-gradient(135deg, #9c80fd 0%, #5f34d9 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            color: white;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
        }

        .order-header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .order-title {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .order-title i {
            font-size: 24px;
            opacity: 0.9;
        }

        .order-meta {
            display: flex;
            gap: 24px;
            opacity: 0.9;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        .status-shipped { background: #d1fae5; color: #065f46; }
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fef3c7; color: #92400e; }
        .status-refunded { background: #f3e8ff; color: #7c3aed; }

        /* Content Grid */
        .order-content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid #e5e7eb;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #374151;
        }

        .card-header i {
            font-size: 18px;
            color: #6b7280;
        }

        .card-body {
            padding: 20px;
        }

        /* Detail Rows */
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .detail-row .label {
            font-weight: 500;
            color: #6b7280;
            font-size: 14px;
        }

        .detail-row .value {
            font-weight: 600;
            color: #1f2937;
            text-align: right;
            max-width: 60%;
            word-break: break-word;
        }

        /* Special Value Styling */
        .amount-total {
            font-size: 18px;
            color: #059669 !important;
            font-weight: 700;
        }

        .discount {
            color: #dc2626 !important;
        }

        .badge-count {
            background: #3b82f6;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        /* Tracking Number */
        .tracking-number {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tracking-code {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 13px;
            color: #374151;
        }

        .btn-copy {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.2s;
        }

        .btn-copy:hover {
            background: #2563eb;
        }

        /* Address Grid */
        .address-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
        }

        .address-block h5 {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .address-text {
            margin: 0;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            color: #6b7280;
            line-height: 1.5;
            border-left: 3px solid #3b82f6;
        }

        /* Order Items Section */
        .order-items-section,
        .order-notes {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 24px;
            border: 1px solid #e5e7eb;
        }

        .section-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #374151;
        }

        .section-header i {
            font-size: 18px;
            color: #6b7280;
        }

        /* Items Grid */
        .items-grid {
            padding: 20px;
            display: grid;
            gap: 12px;
        }

        .item-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            transition: background 0.2s;
        }

        .item-card:hover {
            background: #f3f4f6;
        }

        .item-info h5 {
            margin: 0 0 4px 0;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }

        .item-sku {
            margin: 0;
            font-size: 12px;
            color: #6b7280;
            font-family: 'Courier New', monospace;
        }

        .item-details {
            display: flex;
            gap: 16px;
            align-items: center;
            font-size: 14px;
        }

        .item-quantity {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        .item-price {
            color: #6b7280;
        }

        .item-total {
            font-weight: 700;
            color: #059669;
        }

        /* Order Notes */
        .notes-content {
            padding: 20px;
        }

        .notes-content p {
            margin: 0;
            line-height: 1.6;
            color: #374151;
            background: #f9fafb;
            padding: 16px;
            border-radius: 8px;
            border-left: 3px solid #f59e0b;
        }

        /* System Information */
        .system-info {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 12px;
            color: #6b7280;
        }

        .info-row:not(:last-child) {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        /* Links */
        a {
            color: #3b82f6;
            text-decoration: none;
            transition: color 0.2s;
        }

        a:hover {
            color: #2563eb;
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .order-details-container {
                padding: 16px;
            }
            
            .order-content-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .order-header-main {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
            
            .order-meta {
                flex-direction: column;
                gap: 8px;
            }
            
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            
            .detail-row .value {
                max-width: 100%;
                text-align: left;
            }
            
            .address-grid {
                grid-template-columns: 1fr;
            }
            
            .item-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .item-details {
                justify-content: space-between;
                width: 100%;
            }
        }

        /* Print Styles */
        @media print {
            .order-details-container {
                background: white;
                box-shadow: none;
            }
            
            .info-card {
                box-shadow: none;
                border: 1px solid #ccc;
                break-inside: avoid;
            }
            
            .btn-copy {
                display: none;
            }
        }

        /* Animation for status changes */
        .status-badge, .shipping-status, .payment-status {
            transition: all 0.3s ease;
        }

        /* Hover effects for interactive elements */
        .info-card:hover .card-header {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
        }

        /* Smooth scrolling for long content */
        .order-details-container {
            scroll-behavior: smooth;
        }
    </style>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentPage = 1;
        let carriersData = [];
        let ordersData = [];
        let totalPages = 1;
        let currentFilters = {};
        const supportedCarriers = <?php echo json_encode($supportedCarriers); ?>;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadData();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            // Modal handlers
            const addCarrierBtn = document.getElementById('add-carrier-btn');
            const modals = ['add-carrier-modal', 'edit-carrier-modal', 'order-details-modal'];
            
            addCarrierBtn?.addEventListener('click', () => {
                document.getElementById('add-carrier-modal').style.display = 'block';
            });

            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                modals.forEach(modalId => {
                    const modal = document.getElementById(modalId);
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            });

            // Close modals with close buttons
            document.querySelectorAll('.modal-close, .cancel-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.closest('.modal').style.display = 'none';
                });
            });

            // Form submissions
            document.getElementById('add-carrier-form')?.addEventListener('submit', handleAddCarrier);
            document.getElementById('edit-carrier-form')?.addEventListener('submit', handleEditCarrier);

            // Refresh button
            document.getElementById('refresh-data')?.addEventListener('click', loadData);

            // Search
            document.getElementById('search-carrier')?.addEventListener('input', debounce(handleSearch, 300));

            // Export
            document.getElementById('export-carriers')?.addEventListener('click', exportCarriers);

            // Supported carrier selection
            document.querySelectorAll('.supported-carrier').forEach(carrier => {
                carrier.addEventListener('click', function() {
                    const carrierId = this.getAttribute('data-carrier-id');
                    document.getElementById('carrier-type').value = carrierId;
                    
                    // Update selected state
                    document.querySelectorAll('.supported-carrier').forEach(c => {
                        c.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    
                    // Auto-fill tracking URL if available in supported carriers
                    const selectedCarrier = supportedCarriers.find(c => c.id === carrierId);
                    if (selectedCarrier && selectedCarrier.default_tracking_url) {
                        document.getElementById('carrier-tracking-url').value = selectedCarrier.default_tracking_url;
                    }
                });
            });

            // Apply filters
            document.querySelector('.btn-filter-apply')?.addEventListener('click', applyFilters);
            document.querySelector('.btn-filter-reset')?.addEventListener('click', resetFilters);
        }

        // Load all data
        async function loadData() {
            showLoading(true);
            hideMessages();
            
            try {
                // Load carriers
                await loadCarriers();
                
                // Load recent orders
                await loadRecentOrders();
                
                // Update statistics
                updateStatistics();
                
            } catch (error) {
                console.error('Error loading data:', error);
                
                if (error.message.includes('Failed to fetch') || error.message.includes('HTTP error')) {
                    showErrorMessage('Erreur de connexion. Veuillez vérifier votre connexion internet.');
                } else {
                    showErrorMessage('Erreur lors du chargement des données');
                }
            } finally {
                showLoading(false);
            }
        }

        // Load carriers from database
        async function loadCarriers() {
            try {
                const response = await fetch('./controllers/get_carriers_api.php');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log(data)
                
                if (data.success) {
                    carriersData = data.data;
                    renderCarriers(carriersData);
                } else {
                    throw new Error(data.message || 'Failed to load carriers');
                }
            } catch (error) {
                console.error('Error loading carriers:', error);
                throw error;
            }
        }

        // Load recent orders
        async function loadRecentOrders(page = 1) {
            try {
                const response = await fetch(`./controllers/get_recent_ordersApi.php?page=${page}&limit=10`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    ordersData = data.data.orders || [];
                    totalPages = data.data.pagination.total_pages || 0;
                    currentPage = page;
                    
                    if (ordersData.length === 0) {
                        renderEmptyOrders();
                    } else {
                        renderOrders(ordersData);
                    }
                    
                    updatePagination();
                } else {
                    throw new Error(data.message || 'Failed to load orders');
                }
            } catch (error) {
                console.error('Error loading orders:', error);
                throw error;
            }
        }

        // Render empty orders state
        function renderEmptyOrders() {
            const tbody = document.getElementById('orders-table-body');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="d-flex flex-column align-items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#6c757d" viewBox="0 0 16 16">
                                    <path d="M8 1a2 2 0 0 0-2 2v2H5V3a3 3 0 0 1 6 0v2h-1V3a2 2 0 0 0-2-2zM5 5H3.36a1.5 1.5 0 0 0-1.483 1.277L.85 13.13A2.5 2.5 0 0 0 3.322 16h9.356a2.5 2.5 0 0 0 2.472-2.87l-1.028-6.853A1.5 1.5 0 0 0 12.64 5H11v1.5a.5.5 0 0 1-1 0V5H6v1.5a.5.5 0 0 1-1 0V5z"/>
                                </svg>
                                <p class="mt-3 mb-0">Aucune commande trouvée</p>
                            </div>
                        </td>
                    </tr>
                `;
            }
        }

        // Render carriers grid
        function renderCarriers(carriers) {
            const grid = document.getElementById('carrier-grid');
            if (!grid) return;

            if (!carriers || !carriers.length) {
                grid.innerHTML = `
                    <div class="no-results">
                        <div class="d-flex flex-column align-items-center py-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#6c757d" viewBox="0 0 16 16">
                                <path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5v-7z"/>
                            </svg>
                            <p class="mt-3 mb-0">Aucun transporteur trouvé</p>
                        </div>
                    </div>
                `;
                return;
            }

            grid.innerHTML = carriers.map(carrier => {
                // Find supported carrier data
                const supportedCarrier = supportedCarriers.find(
                c => c.id.toLowerCase() === carrier.name.toLowerCase()
                ) || {};
                
                return `
                <div class="carrier-card ${carrier.is_active == 0 ? 'inactive' : ''}">
                    <div class="carrier-header">
                        <div class="carrier-logo">
                            ${supportedCarrier.logo ? 
                                `<img src="../assets/img/carriers/${escapeHtml(supportedCarrier.logo)}" alt="${escapeHtml(supportedCarrier.name || carrier.name)}">` : 
                                `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M3 13.5L2.25 12H7.5l.75-1.5H2L1 9h14l-1 1.5h-.5L13 9h9l1 1.5H15l.75 1.5H21.75L21 13.5h-6.75l-.75 1.5h8.5L23 15H9l1 1.5h.5l.75 1.5H3.75L3 16.5h5.25l-.75 1.5H1.5L1 18l.75 1.5h12L13 18h9.25l.75 1.5H22l-.75-1.5H19.5l.75-1.5H16.5l-.75-1.5H20l1-1.5h-4.5l-.75-1.5H19l1-1.5h-5.25l-.75-1.5H18L19.5 9H21l-1.5 3H22l.75-1.5H24L22.5 9H23l-1.5 3h.75L24 10.5H0"/>
                                </svg>`
                            }
                        </div>
                        <div class="carrier-status status-${carrier.is_active == 1 ? 'active' : 'inactive'}">
                            ${carrier.is_active == 1 ? 'Actif' : 'Inactif'}
                        </div>
                    </div>
                    
                    <div class="carrier-details">
                        <h3>${escapeHtml(carrier.name || supportedCarrier.name || 'Transporteur')}</h3>
                        <p class="carrier-description">${escapeHtml(carrier.description || supportedCarrier.description || 'Aucune description disponible')}</p>
                        
                        <div class="carrier-meta">
                            <span class="meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                                ${carrier.phone || 'Non spécifié'}
                            </span>
                            <span class="meta-item">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                                ${carrier.email || 'Non spécifié'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="carrier-actions">
                        <button class="btn-action btn-toggle ${carrier.is_active == 1 ? 'active' : 'inactive'}" 
                                onclick="toggleCarrierStatus(${carrier.id})"
                                title="${carrier.is_active == 1 ? 'Désactiver' : 'Activer'}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                ${carrier.is_active == 1 ? 
                                    '<path d="M8 12l3 3 5-6"/>' : 
                                    '<path d="M16 12H8"/>'}
                            </svg>
                        </button>
                        
                        <button class="btn-action btn-edit" onclick="editCarrier(${carrier.id})" title="Modifier">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        
                        <button class="btn-action btn-delete" onclick="deleteCarrier(${carrier.id})" title="Supprimer">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </div>
                `;
            }).join('');
        }

        // Render orders table
        function renderOrders(orders) {
            const tbody = document.getElementById('orders-table-body');
            if (!tbody) return;

            tbody.innerHTML = orders.map(order => `
                <tr>
                    <td><strong>#${escapeHtml(order.order_number)}</strong></td>
                    <td>${escapeHtml(order.carrier_name || 'N/A')}</td>
                    <td>
                        ${order.tracking_number ? `
                            <a href="${order.tracking_url || '#'}" target="_blank" class="tracking-link">
                                ${escapeHtml(order.tracking_number)}
                            </a>
                        ` : 'N/A'}
                    </td>
                    <td>${escapeHtml(order.customer_name || 'N/A')}</td>
                    <td>${formatDate(order.shipped_date)}</td>
                    <td>
                        <span class="status-badge status-${order.status?.toLowerCase() || 'unknown'}">
                            ${getStatusText(order.status)}
                        </span>
                    </td>
                    <td>
                        <button class="btn-action" onclick="viewOrderDetails(${order.id})" title="Voir détails">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                            </svg>
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Get status text
        function getStatusText(status) {
            const statusMap = {
                'pending': 'En attente',
                'processing': 'En traitement',
                'shipped': 'Expédié',
                'in_transit': 'En transit',
                'delivered': 'Livré',
                'cancelled': 'Annulé'
            };
            
            return statusMap[status?.toLowerCase()] || status || 'Inconnu';
        }

        // Update statistics
        function updateStatistics() {
            const totalCarriers = carriersData.length;
            const activeCarriers = carriersData.filter(c => c.is_active == 1).length;
            const inactiveCarriers = totalCarriers - activeCarriers;
            const ordersInTransit = ordersData.filter(o => o.status === 'in_transit' || o.status === 'shipped').length;

            document.getElementById('total-carriers').textContent = totalCarriers;
            document.getElementById('active-carriers').textContent = activeCarriers;
            document.getElementById('inactive-carriers').textContent = inactiveCarriers;
            document.getElementById('orders-in-transit').textContent = ordersInTransit;
        }

        // Apply filters
        function applyFilters() {
            const statusFilter = document.getElementById('status-filter').value;
            
            currentFilters = {
                status: statusFilter
            };
            
            // Reload data with filters
            loadData();
        }

        // Reset filters
        function resetFilters() {
            document.getElementById('status-filter').value = '';
            currentFilters = {};
            loadData();
        }

        // Toggle carrier status
        async function toggleCarrierStatus(carrierId) {
            if (!confirm('Êtes-vous sûr de vouloir changer le statut de ce transporteur ?')) {
                return;
            }

            try {
                showLoading(true);
                
                const response = await fetch('./controllers/toggle_carrier_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: carrierId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update local data
                    const carrier = carriersData.find(c => c.id == carrierId);
                    if (carrier) {
                        carrier.is_active = carrier.is_active == 1 ? 0 : 1;
                    }
                    
                    // Re-render and update stats
                    renderCarriers(carriersData);
                    updateStatistics();
                    
                    showSuccessMessage('Statut du transporteur mis à jour avec succès');
                } else {
                    throw new Error(data.message || 'Failed to toggle carrier status');
                }
            } catch (error) {
                console.error('Error toggling carrier status:', error);
                showErrorMessage('Erreur lors de la mise à jour du statut');
            } finally {
                showLoading(false);
            }
        }

        // Add new carrier
        async function handleAddCarrier(e) {
            e.preventDefault();
            
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            
            try {
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.textContent = 'Ajout en cours...';
                
                // Get form data
                const formData = new FormData(form);
                
                // Validate required fields
                if (!formData.get('carrier_type') || !formData.get('api_url')) {

                    throw new Error('Veuillez remplir tous les champs obligatoires');
                }
                
                // Send request
                const response = await fetch('./controllers/add_carrier_Api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Close modal
                    document.getElementById('add-carrier-modal').style.display = 'none';
                    form.reset();
                    
                    // Reload carriers
                    await loadCarriers();
                    updateStatistics();
                    
                    showSuccessMessage('Transporteur ajouté avec succès');
                } else {
                    closeAddModal();
                    throw new Error(data.message || 'Erreur lors de l\'ajout du transporteur');
                }
            } catch (error) {
                console.error('Error adding carrier:', error);
                showErrorMessage(error.message || 'Erreur lors de l\'ajout du transporteur');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        }

        function closeAddModal() {
            document.getElementById('add-carrier-modal').style.display = 'none';
        }

        // Edit carrier
        async function editCarrier(carrierId) {
            try {
                showLoading(true);

                const response = await fetch(`./controllers/get_carrierById_Api.php?id=${carrierId}`);
                const data = await response.json();

                if (data.success) {
                    const carrier = data.data;

                    // Populate edit form
                    document.getElementById('edit-carrier-id').value = carrier.id;
                    document.getElementById('edit-carrier-name').value = carrier.name || '';
                    document.getElementById('edit-carrier-api-url').value = carrier.api_url || '';
                    document.getElementById('edit-carrier-tracking-url').value = carrier.tracking_url || '';
                    document.getElementById('edit-carrier-status').value = carrier.is_active;

                    // Show modal
                    document.getElementById('edit-carrier-modal').style.display = 'block';
                } else {
                    throw new Error(data.message || 'Échec du chargement des données du transporteur');
                }
            } catch (error) {
                console.error('Error loading carrier for edit:', error);
                showErrorMessage('Erreur lors du chargement des données du transporteur');
            } finally {
                showLoading(false);
            }
        }

        // Handle edit carrier form submission
        async function handleEditCarrier(e) {
            e.preventDefault();
            
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            
            try {
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.textContent = 'Enregistrement...';
                
                // Get form data
                const formData = new FormData(form);
                
                // Validate required fields
                if (!formData.get('api_url')) {
                    throw new Error('Veuillez remplir tous les champs obligatoires');
                }
                
                // Send request
                const response = await fetch('./controllers/update_carrier_Api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Close modal
                    document.getElementById('edit-carrier-modal').style.display = 'none';
                    
                    // Reload carriers
                    await loadCarriers();
                    updateStatistics();
                    
                    showSuccessMessage('Transporteur modifié avec succès');
                } else {
                    throw new Error(data.message || 'Erreur lors de la modification du transporteur');
                }
            } catch (error) {
                console.error('Error updating carrier:', error);
                showErrorMessage(error.message || 'Erreur lors de la modification du transporteur');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        }

        // Delete carrier
        async function deleteCarrier(carrierId) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer ce transporteur ? Cette action est irréversible.')) {
                return;
            }

            try {
                showLoading(true);
                
                const response = await fetch('./controllers/delete_carrierApi.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: carrierId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove from local data
                    carriersData = carriersData.filter(c => c.id != carrierId);
                    
                    // Re-render and update stats
                    renderCarriers(carriersData);
                    updateStatistics();
                    
                    showSuccessMessage('Transporteur supprimé avec succès');
                } else {
                    throw new Error(data.message || 'Failed to delete carrier');
                }
            } catch (error) {
                console.error('Error deleting carrier:', error);
                showErrorMessage('Erreur lors de la suppression du transporteur');
            } finally {
                showLoading(false);
            }
        }

        // View order details
        async function viewOrderDetails(orderId) {
            try {
                showLoading(true);
                
                const response = await fetch(`./controllers/get_ThisOrderDetail.php?id=${orderId}`);
                const data = await response.json();
                
                if (data.success) {
                    const order = data.data;
                    const items = data.items || [];
                    const content = document.getElementById('order-details-content');
                    
                    // Format dimensions
                    const dimensions = (order.length && order.width && order.height) 
                        ? `${order.length} × ${order.width} × ${order.height} cm`
                        : 'N/A';
                    
                    // Calculate total items
                    const totalItems = items.reduce((sum, item) => sum + item.quantity, 0);
                    
                    content.innerHTML = `
                        <div class="order-details-container">
                            <!-- Header Section -->
                            <div class="order-header">
                                <div class="order-header-main">
                                    <h3 class="order-title">
                                        <i class="fas fa-package"></i>
                                        Commande #${escapeHtml(order.order_number)}
                                    </h3>
                                    <span class="status-badge status-${(order.status || 'unknown').toLowerCase()}">
                                        <i class="fas fa-circle"></i>
                                        ${getStatusText(order.status)}
                                    </span>
                                </div>
                                <div class="order-meta">
                                    <span class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        ${formatDate(order.order_date)}
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-store"></i>
                                        ${escapeHtml(order.platform || 'N/A')}
                                    </span>
                                </div>
                            </div>

                            <!-- Main Content Grid -->
                            <div class="order-content-grid">
                                <!-- Customer Information -->
                                <div class="info-card">
                                    <div class="card-header">
                                        <i class="fas fa-user"></i>
                                        <h4>Informations Client</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="detail-row">
                                            <span class="label">Nom:</span>
                                            <span class="value">${escapeHtml(order.customer_name || 'N/A')}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Email:</span>
                                            <span class="value">
                                                ${order.customer_email ? `<a href="mailto:${order.customer_email}">${escapeHtml(order.customer_email)}</a>` : 'N/A'}
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Téléphone:</span>
                                            <span class="value">
                                                ${order.customer_phone ? `<a href="tel:${order.customer_phone}">${escapeHtml(order.customer_phone)}</a>` : 'N/A'}
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Ville:</span>
                                            <span class="value">${escapeHtml(order.customer_ville || 'N/A')}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Shipping Information -->
                                <div class="info-card">
                                    <div class="card-header">
                                        <i class="fas fa-shipping-fast"></i>
                                        <h4>Expédition</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="detail-row">
                                            <span class="label">Transporteur:</span>
                                            <span class="value">${escapeHtml(order.carrier_name || 'N/A')}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Méthode:</span>
                                            <span class="value">${escapeHtml(order.shipping_method || 'N/A')}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Numéro de suivi:</span>
                                            <span class="value tracking-number">
                                                ${order.tracking_number ? `
                                                    <span class="tracking-code">${escapeHtml(order.tracking_number)}</span>
                                                    <button class="btn-copy" onclick="copyToClipboard('${order.tracking_number}')">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                ` : 'N/A'}
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Statut expédition:</span>
                                            <span class="shipping-status status-${(order.shipping_status || 'unknown').toLowerCase()}">
                                                ${getStatusText(order.shipping_status)}
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Date d'expédition:</span>
                                            <span class="value">${formatDate(order.shipped_date)}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Information -->
                                <div class="info-card full-width">
                                    <div class="card-header">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <h4>Adresses</h4>
                                    </div>
                                    <div class="card-body address-grid">
                                        <div class="address-block">
                                            <h5><i class="fas fa-truck"></i> Adresse de livraison</h5>
                                            <p class="address-text">${escapeHtml(order.shipping_address || 'N/A')}</p>
                                        </div>
                                        <div class="address-block">
                                            <h5><i class="fas fa-file-invoice"></i> Adresse de facturation</h5>
                                            <p class="address-text">${escapeHtml(order.billing_address || 'N/A')}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Package Details -->
                                <div class="info-card">
                                    <div class="card-header">
                                        <i class="fas fa-box"></i>
                                        <h4>Détails du colis</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="detail-row">
                                            <span class="label">Poids:</span>
                                            <span class="value">${order.weight ? order.weight + ' kg' : 'N/A'}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Dimensions:</span>
                                            <span class="value">${dimensions}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Articles totaux:</span>
                                            <span class="value badge-count">${totalItems}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Financial Information -->
                                <div class="info-card">
                                    <div class="card-header">
                                        <i class="fas fa-euro-sign"></i>
                                        <h4>Informations financières</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="detail-row">
                                            <span class="label">Montant total:</span>
                                            <span class="value amount-total">${formatAmount(order.total_amount, order.currency)}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Frais de livraison:</span>
                                            <span class="value">${formatAmount(order.shipping_cost, order.currency)}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Réduction:</span>
                                            <span class="value discount">${formatAmount(order.discount_amount, order.currency)}</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="label">Statut paiement:</span>
                                            <span class="payment-status status-${(order.payment_status || 'unknown').toLowerCase()}">
                                                <i class="fas fa-credit-card"></i>
                                                ${getStatusText(order.payment_status)}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Order Items -->
                            ${items.length > 0 ? `
                                <div class="order-items-section">
                                    <div class="section-header">
                                        <i class="fas fa-list"></i>
                                        <h4>Articles commandés (${items.length})</h4>
                                    </div>
                                    <div class="items-grid">
                                        ${items.map(item => `
                                            <div class="item-card">
                                                <div class="item-info">
                                                    <h5 class="item-name">${escapeHtml(item.product_name)}</h5>
                                                    <p class="item-sku">SKU: ${escapeHtml(item.product_sku)}</p>
                                                </div>
                                                <div class="item-details">
                                                    <span class="item-quantity">Qté: ${item.quantity}</span>
                                                    <span class="item-price">${formatAmount(item.unit_price, order.currency)}</span>
                                                    <span class="item-total">${formatAmount(item.total_price, order.currency)}</span>
                                                </div>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}

                            <!-- Order Notes -->
                            ${order.order_note ? `
                                <div class="order-notes">
                                    <div class="section-header">
                                        <i class="fas fa-sticky-note"></i>
                                        <h4>Notes de commande</h4>
                                    </div>
                                    <div class="notes-content">
                                        <p>${escapeHtml(order.order_note)}</p>
                                    </div>
                                </div>
                            ` : ''}

                            <!-- System Information -->
                            <div class="system-info">
                                <div class="info-row">
                                    <span>ID commande: ${order.id}</span>
                                    <span>Créé: ${formatDateTime(order.created_at)}</span>
                                </div>
                                <div class="info-row">
                                    <span>ID externe: ${order.external_order_id || 'N/A'}</span>
                                    <span>Modifié: ${formatDateTime(order.updated_at)}</span>
                                </div>
                                ${order.confirmed_by_agent ? `
                                    <div class="info-row">
                                        <span>Confirmé par agent: ${order.confirmed_by_agent}</span>
                                        <span>Traité: ${formatDateTime(order.handled_at)}</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('order-details-modal').style.display = 'block';
                } else {
                    throw new Error(data.message || 'Failed to load order details');
                }
            } catch (error) {
                console.error('Error loading order details:', error);
                showErrorMessage('Erreur lors du chargement des détails de la commande');
            } finally {
                showLoading(false);
            }
        }

        // Helper function to copy tracking number to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showSuccessMessage('Numéro de suivi copié!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        // Helper function to format amounts
        function formatAmount(amount, currency = 'MAD') {
            if (!amount || amount === 0) return '0,00 D.H';
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: currency
            }).format(amount);
        }

        // Helper function to format date and time
        function formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleString('fr-FR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Track order
        function trackOrder(trackingNumber, carrierId = null) {
            if (!trackingNumber) return;
            
            // Find carrier tracking URL
            let trackingUrl = '#';
            if (carrierId) {
                const carrier = carriersData.find(c => c.id == carrierId);
                if (carrier && carrier.tracking_url) {
                    trackingUrl = carrier.tracking_url.replace('{tracking_number}', trackingNumber);
                }
            }
            
            window.open(trackingUrl, '_blank');
        }

        // Handle search
        function handleSearch(e) {
            const searchTerm = e.target.value.toLowerCase();
            
            if (!searchTerm) {
                renderCarriers(carriersData);
                return;
            }
            
            const filteredCarriers = carriersData.filter(carrier => {
                const carrierName = carrier.name?.toLowerCase() || '';
                const carrierType = supportedCarriers.find(c => c.id === carrier.carrier_type)?.name?.toLowerCase() || '';
                return carrierName.includes(searchTerm) || carrierType.includes(searchTerm);
            });
            
            renderCarriers(filteredCarriers);
        }

        // Export carriers
        function exportCarriers() {
            const csvContent = "data:text/csv;charset=utf-8," + 
                "ID,Nom,Type,Clé API,URL de suivi,Statut\n" +
                carriersData.map(carrier => {
                    const carrierType = supportedCarriers.find(c => c.id === carrier.carrier_type)?.name || carrier.carrier_type;
                    return `"${carrier.id}","${escapeCsv(carrier.name)}","${escapeCsv(carrierType)}","${escapeCsv(carrier.api_key)}","${escapeCsv(carrier.tracking_url)}","${carrier.is_active == 1 ? 'Actif' : 'Inactif'}"`;
                }).join("\n");
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `transporteurs_${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Update pagination
        function updatePagination() {
            const paginationPages = document.getElementById('orders-pages');
            const prevBtn = document.getElementById('prev-orders');
            const nextBtn = document.getElementById('next-orders');
            
            if (!paginationPages) return;
            
            // Update buttons state
            prevBtn.disabled = currentPage <= 1;
            nextBtn.disabled = currentPage >= totalPages;
            
            // Generate page numbers
            let pagesHtml = '';
            for (let i = 1; i <= totalPages; i++) {
                if (i === currentPage || 
                    i === 1 || 
                    i === totalPages || 
                    (i >= currentPage - 1 && i <= currentPage + 1)) {
                    pagesHtml += `<button class="pagination-page ${i === currentPage ? 'active' : ''}" onclick="loadRecentOrders(${i})">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    pagesHtml += '<span class="pagination-ellipsis">...</span>';
                }
            }
            
            paginationPages.innerHTML = pagesHtml;
            
            // Add event listeners for prev/next
            prevBtn.onclick = () => {
                if (currentPage > 1) {
                    loadRecentOrders(currentPage - 1);
                }
            };
            
            nextBtn.onclick = () => {
                if (currentPage < totalPages) {
                    loadRecentOrders(currentPage + 1);
                }
            };
        }

        // Utility functions
        function showLoading(show) {
            const loading = document.getElementById('loading');
            if (loading) {
                loading.style.display = show ? 'block' : 'none';
            }
        }

        function hideMessages() {
            document.getElementById('error-message').style.display = 'none';
            document.getElementById('success-message').style.display = 'none';
        }

        function showErrorMessage(message) {
            const errorMessage = document.getElementById('error-message');
            errorMessage.querySelector('p').textContent = message;
            errorMessage.style.display = 'block';
            
            // Hide after 5 seconds
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 5000);
        }

        function showSuccessMessage(message) {
            const successMessage = document.getElementById('success-message');
            successMessage.querySelector('p').textContent = message;
            successMessage.style.display = 'block';
            
            // Hide after 5 seconds
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 5000);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function escapeCsv(text) {
            if (!text) return '';
            return `"${text.toString().replace(/"/g, '""')}"`;
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    </script>
</body>
</html>