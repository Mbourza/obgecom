<?php 
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("./config/init.php");
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

if (isset($_GET['logout'])) {
    logout();
}

$user_id   = (int)$user[0]['id'];
$userName  = $user[0]['name'];
$userRole  = $user[0]['role'];

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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centre d'Aide - Support Client</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/common.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        .help-container {
            display: flex;
            min-height: 100vh;
        }

        /* Help Sidebar */
        .help-sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e2e8f0;
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

        .help-sidebar-header {
            padding: 0 24px 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .help-sidebar-header h2 {
            color: #1e293b;
            font-size: 1.125rem;
            font-weight: 600;
        }

        .help-sidebar-nav {
            padding: 24px 0;
        }

        .help-nav-item {
            margin-bottom: 4px;
            padding: 0 24px;
        }

        .help-nav-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            font-weight: 500;
        }

        .help-nav-link:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .help-nav-link.active {
            background: #e1d7f5;
            color: #5f34d9;
        }

        .help-nav-link i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .help-main-content {
            flex: 1;
            margin-left: 285px;
            padding: 32px;
        }

        .help-header {
            margin-bottom: 32px;
        }

        .help-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .help-header p {
            color: #64748b;
            font-size: 1.125rem;
        }

        /* FAQ Section */
        .help-faq-section {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 24px;
            display: none;
        }

        .help-faq-section.active {
            display: block;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .help-faq-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        .help-faq-header h2 {
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .help-faq-header p {
            color: #64748b;
            font-size: 1rem;
        }

        .help-faq-item {
            border-bottom: 1px solid #e2e8f0;
        }

        .help-faq-item:last-child {
            border-bottom: none;
        }

        .help-faq-question {
            width: 100%;
            padding: 20px 24px;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: #1e293b;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
        }

        .help-faq-question:hover {
            background: #f8fafc;
        }

        .help-faq-answer {
            padding: 0 24px 24px;
            color: #475569;
            display: none;
        }

        .help-faq-answer.active {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; max-height: 0; }
            to { opacity: 1; max-height: 1000px; }
        }

        .help-faq-answer p {
            margin-bottom: 16px;
        }

        .help-faq-answer ol, .help-faq-answer ul {
            margin-left: 20px;
            margin-bottom: 16px;
        }

        .help-faq-answer li {
            margin-bottom: 8px;
        }

        .help-faq-answer strong {
            color: #1e293b;
        }

        .help-chevron {
            transition: transform 0.2s;
            color: #64748b;
        }

        .help-chevron.rotated {
            transform: rotate(90deg);
        }

        /* Video Container */
        .help-video-container {
            margin: 20px 0;
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }

        .video-wrapper {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            margin-bottom: 16px;
        }

        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Contact Section */
        .help-contact-section {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 32px;
            text-align: center;
        }

        .help-contact-section h3 {
            color: #1e293b;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .help-contact-section p {
            color: #64748b;
            margin-bottom: 32px;
        }

        .help-contact-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }

        .help-contact-method {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px;
            border-radius: 12px;
            background: #f8fafc;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }

        .help-contact-method:hover {
            background: #f1f5f9;
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .help-contact-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #5f34d9, #8b5cf6);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.5rem;
        }

        .help-contact-method strong {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .help-contact-method span {
            color: #64748b;
            font-size: 0.9rem;
        }

        /* Enhanced Sync Section */
        .sync-process {
            margin: 24px 0;
        }

        .sync-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 24px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid #5f34d9;
        }

        .sync-step-number {
            width: 40px;
            height: 40px;
            background: #5f34d9;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 16px;
            flex-shrink: 0;
        }

        .sync-step-content {
            flex: 1;
        }

        .sync-step-content h4 {
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .sync-step-content p {
            color: #64748b;
            margin: 0;
        }

        .sync-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }

        .sync-feature {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .sync-feature:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .sync-feature-icon {
            width: 50px;
            height: 50px;
            background: #e1d7f5;
            color: #5f34d9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.25rem;
        }

        .sync-feature h5 {
            color: #1e293b;
            margin-bottom: 8px;
        }

        .sync-feature p {
            color: #64748b;
            font-size: 0.9rem;
            margin: 0;
        }

        .troubleshooting-section {
            background: #fff8e6;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
            border-left: 4px solid #ffc107;
        }

        .troubleshooting-section h4 {
            color: #856404;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
        }

        .troubleshooting-section h4 i {
            margin-right: 8px;
        }

        .troubleshooting-steps {
            display: grid;
            gap: 12px;
        }

        .troubleshooting-step {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
        }

        .troubleshooting-step-number {
            width: 24px;
            height: 24px;
            background: #ffc107;
            color: #856404;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            margin-right: 12px;
            flex-shrink: 0;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .help-sidebar {
                margin-left: 0;
                position: relative;
                width: 100%;
                height: auto;
            }
            
            .help-main-content {
                margin-left: 0;
            }
            
            .help-container {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            .help-main-content {
                padding: 20px;
            }

            .help-contact-methods {
                grid-template-columns: 1fr;
            }

            .sync-features {
                grid-template-columns: 1fr;
            }
        }

        /* Styles pour les alertes d'abonnement */
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
</head>
<body>
    <?php $currentPage = 'support'; 
    require_once('../assets/sidebar.php'); ?>
    
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

        <?php endif; ?>

        <nav class="top-navbar">

            <div class="search-bar">
                <input type="text" id="search-input" placeholder="Rechercher un agent...">
                <button class="search-btn" onclick="searchAgents()">
                    <i class="bi bi-search"></i>
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

        <!-- Centre d'Aide Container -->
        <div class="help-container">
            <!-- Help Sidebar -->
            <div class="help-sidebar">
                <div class="help-sidebar-header">
                    <h2>Centre d'Aide OBGECOM</h2>
                </div>
                <nav class="help-sidebar-nav">
                    <div class="help-nav-item">
                        <a href="#connect-stores" class="help-nav-link active" data-tab="connect-stores">
                            <i class="fas fa-store"></i>
                            Connecter des boutiques
                        </a>
                    </div>
                    <div class="help-nav-item">
                        <a href="#shipping-companies" class="help-nav-link" data-tab="shipping-companies">
                            <i class="fas fa-truck"></i>
                            Transporteurs
                        </a>
                    </div>
                    <div class="help-nav-item">
                        <a href="#confirmation-agents" class="help-nav-link" data-tab="confirmation-agents">
                            <i class="fas fa-user-check"></i>
                            Agents de confirmation
                        </a>
                    </div>
                    <div class="help-nav-item">
                        <a href="#sync-orders" class="help-nav-link" data-tab="sync-orders">
                            <i class="fas fa-sync"></i>
                            Synchroniser les commandes
                        </a>
                    </div>
                    <div class="help-nav-item">
                        <a href="#contact-support" class="help-nav-link" data-tab="contact-support">
                            <i class="fas fa-headset"></i>
                            Support technique
                        </a>
                    </div>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="help-main-content">
                <div class="help-header">
                    <h1>Centre d'Aide OBGECOM</h1>
                    <p>Découvrez comment utiliser notre plateforme avec nos tutoriels vidéo</p>
                </div>

                <!-- Connecter des boutiques -->
                <div class="help-faq-section active" id="connect-stores">
                    <div class="help-faq-header">
                        <h2>Connecter des boutiques</h2>
                        <p>Apprenez à connecter vos boutiques en ligne à la plateforme OBGECOM</p>
                    </div>
                    <div class="help-faq-item">
                        <button class="help-faq-question" onclick="toggleHelpFAQ(this)">
                            Comment connecter vos boutiques à OBGECOM
                            <i class="fas fa-chevron-right help-chevron"></i>
                        </button>
                        <div class="help-faq-answer">
                            <p>Dans cette vidéo, nous vous expliquons étape par étape comment connecter vos différentes boutiques en ligne (Shopify, WooCommerce, PrestaShop, etc.) à la plateforme OBGECOM pour centraliser la gestion de vos commandes.</p>
                            
                            <div class="help-video-container">
                                <div class="video-wrapper">
                                    <iframe src="https://www.youtube.com/embed/ZEBev3IE0WQ" 
                                            title="Tutoriel : Connecter vos boutiques à OBGECOM" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                            allowfullscreen>
                                    </iframe>
                                </div>
                            </div>
                            
                            <h4>Résumé des étapes :</h4>
                            <ol>
                                <li>Accédez à la section "Boutiques" dans votre tableau de bord</li>
                                <li>Cliquez sur "Ajouter une boutique"</li>
                                <li>Sélectionnez votre plateforme e-commerce</li>
                                <li>Suivez les instructions pour autoriser la connexion</li>
                                <li>Configurez les paramètres d'importation</li>
                                <li>Testez la connexion avec une commande d'essai</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Transporteurs -->
                <div class="help-faq-section" id="shipping-companies">
                    <div class="help-faq-header">
                        <h2>Transporteurs</h2>
                        <p>Configurez vos transporteurs et définissez un transporteur par défaut</p>
                    </div>
                    <div class="help-faq-item">
                        <button class="help-faq-question" onclick="toggleHelpFAQ(this)">
                            Comment connecter des transporteurs et définir un transporteur par défaut
                            <i class="fas fa-chevron-right help-chevron"></i>
                        </button>
                        <div class="help-faq-answer">
                            <p>Cette vidéo vous montre comment intégrer vos transporteurs à OBGECOM et comment configurer un transporteur par défaut pour automatiser vos expéditions.</p>
                            
                            <div class="help-video-container">
                                <div class="video-wrapper">
                                    <iframe src="https://www.youtube.com/embed/Amq7F25gvFM" 
                                            title="Tutoriel : Configurer les transporteurs sur OBGECOM" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                            allowfullscreen>
                                    </iframe>
                                </div>
                            </div>
                            
                            <h4>Points clés abordés :</h4>
                            <ul>
                                <li>Ajout de nouveaux transporteurs</li>
                                <li>Configuration des tarifs et zones de livraison</li>
                                <li>Définition d'un transporteur par défaut</li>
                                <li>Gestion des exceptions par produit ou boutique</li>
                                <li>Test des paramètres de livraison</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Agents de confirmation -->
                <div class="help-faq-section" id="confirmation-agents">
                    <div class="help-faq-header">
                        <h2>Agents de confirmation</h2>
                        <p>Ajoutez et gérez vos agents de confirmation de commandes</p>
                    </div>
                    <div class="help-faq-item">
                        <button class="help-faq-question" onclick="toggleHelpFAQ(this)">
                            Comment ajouter et configurer des agents de confirmation
                            <i class="fas fa-chevron-right help-chevron"></i>
                        </button>
                        <div class="help-faq-answer">
                            <p>Découvrez dans ce tutoriel comment créer des comptes d'agents de confirmation et leur attribuer les permissions appropriées pour valider les commandes entrantes.</p>
                            
                            <div class="help-video-container">
                                <div class="video-wrapper">
                                    <iframe src="https://www.youtube.com/embed/KgsD7T0VET0" 
                                            title="Tutoriel : Gérer les agents de confirmation sur OBGECOM" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                            allowfullscreen>
                                    </iframe>
                                </div>
                            </div>
                            
                            <h4>Fonctionnalités couvertes :</h4>
                            <ol>
                                <li>Création d'un nouvel agent de confirmation</li>
                                <li>Attribution des permissions et accès</li>
                                <li>Configuration des notifications</li>
                                <li>Définition des plages horaires de travail</li>
                                <li>Suivi de l'activité des agents</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Synchroniser les commandes -->
                <div class="help-faq-section" id="sync-orders">
                    <div class="help-faq-header">
                        <h2>Synchroniser les commandes</h2>
                        <p>Automatisez la synchronisation de vos commandes entre vos boutiques et OBGECOM</p>
                    </div>
                    <div class="help-faq-item">
                        <button class="help-faq-question" onclick="toggleHelpFAQ(this)">
                            Processus de synchronisation des commandes
                            <i class="fas fa-chevron-right help-chevron"></i>
                        </button>
                        <div class="help-faq-answer">
                            <p>Cette section explique en détail le fonctionnement de la synchronisation des commandes sur OBGECOM. Découvrez comment automatiser l'importation de vos commandes depuis vos différentes plateformes e-commerce.</p>
                            
                            <div class="help-video-container">
                                <div class="video-wrapper">
                                    <iframe src="https://www.youtube.com/embed/kXUliWvxt8U" 
                                            title="Tutoriel : Synchronisation des commandes sur OBGECOM" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                            allowfullscreen>
                                    </iframe>
                                </div>
                            </div>
                            
                            <h4>Comment fonctionne la synchronisation ?</h4>
                            <p>OBGECOM utilise une technologie de synchronisation en temps réel qui se connecte aux APIs de vos plateformes e-commerce. Voici le processus détaillé :</p>
                            
                            <div class="sync-process">
                                <div class="sync-step">
                                    <div class="sync-step-number">1</div>
                                    <div class="sync-step-content">
                                        <h4>Connexion initiale</h4>
                                        <p>Lorsque vous connectez une boutique, OBGECOM établit une connexion sécurisée avec l'API de votre plateforme e-commerce.</p>
                                    </div>
                                </div>
                                <div class="sync-step">
                                    <div class="sync-step-number">2</div>
                                    <div class="sync-step-content">
                                        <h4>Récupération des commandes</h4>
                                        <p>Le système interroge régulièrement vos boutiques pour récupérer les nouvelles commandes selon la fréquence configurée.</p>
                                    </div>
                                </div>
                                <div class="sync-step">
                                    <div class="sync-step-number">3</div>
                                    <div class="sync-step-content">
                                        <h4>Traitement des données</h4>
                                        <p>Les commandes sont normalisées dans un format standard OBGECOM, quel que soit leur origine (Shopify, WooCommerce, etc.).</p>
                                    </div>
                                </div>
                                <div class="sync-step">
                                    <div class="sync-step-number">4</div>
                                    <div class="sync-step-content">
                                        <h4>Enrichissement</h4>
                                        <p>Les informations client et produit sont complétées automatiquement avec les données de votre catalogue.</p>
                                    </div>
                                </div>
                                <div class="sync-step">
                                    <div class="sync-step-number">5</div>
                                    <div class="sync-step-content">
                                        <h4>Notification</h4>
                                        <p>Les nouvelles commandes sont notifiées aux agents de confirmation désignés pour validation.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <h4>Fonctionnalités de synchronisation</h4>
                            <div class="sync-features">
                                <div class="sync-feature">
                                    <div class="sync-feature-icon">
                                        <i class="fas fa-bolt"></i>
                                    </div>
                                    <h5>Synchronisation rapide</h5>
                                    <p>Temps réel ou selon votre plan d'abonnement</p>
                                </div>
                                <div class="sync-feature">
                                    <div class="sync-feature-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <h5>Sécurisé</h5>
                                    <p>Connexions chiffrées et authentifiées</p>
                                </div>
                                <div class="sync-feature">
                                    <div class="sync-feature-icon">
                                        <i class="fas fa-redo"></i>
                                    </div>
                                    <h5>Réessai automatique</h5>
                                    <p>En cas d'échec, le système réessaie automatiquement</p>
                                </div>
                                <div class="sync-feature">
                                    <div class="sync-feature-icon">
                                        <i class="fas fa-chart-bar"></i>
                                    </div>
                                    <h5>Rapports détaillés</h5>
                                    <p>Suivez l'état de toutes vos synchronisations</p>
                                </div>
                            </div>
                            
                            <h4>Fréquence de synchronisation</h4>
                            <p>La synchronisation se fait à différentes fréquences selon votre abonnement :</p>
                            <ul>
                                <li><strong>Basique :</strong> Toutes les 30 minutes</li>
                                <li><strong>Professionnel :</strong> Toutes les 10 minutes</li>
                                <li><strong>Entreprise :</strong> En temps réel (moins de 2 minutes)</li>
                            </ul>
                            
                            <div class="troubleshooting-section">
                                <h4><i class="fas fa-tools"></i> Résolution des problèmes courants</h4>
                                <div class="troubleshooting-steps">
                                    <div class="troubleshooting-step">
                                        <div class="troubleshooting-step-number">1</div>
                                        <div>Vérifiez que la connexion à votre boutique est toujours active</div>
                                    </div>
                                    <div class="troubleshooting-step">
                                        <div class="troubleshooting-step-number">2</div>
                                        <div>Contrôlez les logs de synchronisation dans la section "Statut"</div>
                                    </div>
                                    <div class="troubleshooting-step">
                                        <div class="troubleshooting-step-number">3</div>
                                        <div>Assurez-vous que votre abonnement est à jour</div>
                                    </div>
                                    <div class="troubleshooting-step">
                                        <div class="troubleshooting-step-number">4</div>
                                        <div>Contactez notre support en cas de problème persistant</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support technique -->
                <div class="help-faq-section" id="contact-support">
                    <div class="help-contact-section">
                        <h3>Besoin d'aide supplémentaire ?</h3>
                        <p>Notre équipe de support est là pour vous aider avec tout problème technique ou question sur la plateforme OBGECOM.</p>
                        
                        <div class="help-contact-methods">
                            <div class="help-contact-method">
                                <div class="help-contact-icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <strong>Chat en direct</strong>
                                <span>Disponible 24/7 - Réponse immédiate</span>
                            </div>
                            
                            <div class="help-contact-method">
                                <div class="help-contact-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <strong>Email</strong>
                                <span>support@obgecom.com - Réponse sous 2h</span>
                            </div>
                            
                            <div class="help-contact-method">
                                <div class="help-contact-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <strong>Téléphone</strong>
                                <span>07.146.206.95 - Lun-Ven 9h-18h</span>
                            </div>
                            
                            <div class="help-contact-method">
                                <div class="help-contact-icon">
                                    <i class="fas fa-video"></i>
                                </div>
                                <strong>Visio-conférence</strong>
                                <span>Assistance en direct avec partage d'écran</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Fonction pour basculer l'affichage des réponses FAQ
        function toggleHelpFAQ(button) {
            const answer = button.nextElementSibling;
            const chevron = button.querySelector('.help-chevron');
            
            // Fermer tous les autres éléments FAQ
            document.querySelectorAll('.help-faq-answer').forEach(item => {
                if (item !== answer) {
                    item.classList.remove('active');
                }
            });
            
            document.querySelectorAll('.help-chevron').forEach(icon => {
                if (icon !== chevron) {
                    icon.classList.remove('rotated');
                }
            });
            
            // Basculer l'élément actuel
            answer.classList.toggle('active');
            chevron.classList.toggle('rotated');
        }

        // Gérer la navigation par onglets
        document.querySelectorAll('.help-nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Supprimer la classe active de tous les liens
                document.querySelectorAll('.help-nav-link').forEach(l => l.classList.remove('active'));
                
                // Ajouter la classe active au lien cliqué
                this.classList.add('active');
                
                // Masquer toutes les sections
                document.querySelectorAll('.help-faq-section').forEach(section => {
                    section.classList.remove('active');
                });
                
                // Afficher la section correspondante
                const tabId = this.getAttribute('data-tab');
                const targetSection = document.getElementById(tabId);
                if (targetSection) {
                    targetSection.classList.add('active');
                    
                    // Faire défiler jusqu'en haut de la section
                    window.scrollTo({
                        top: targetSection.offsetTop - 20,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Ouvrir automatiquement la première section au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const firstQuestion = document.querySelector('.help-faq-question');
            if (firstQuestion) {
                toggleHelpFAQ(firstQuestion);
            }
        });

        // Fonctions pour les alertes d'abonnement
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
</body>
</html>