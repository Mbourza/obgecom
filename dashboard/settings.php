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
    <meta name="description" content="Paramètres système - OBG">
    <title>Paramètres | OBG</title>
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Settings specific styles */
        .settings-nav {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 2rem;
            overflow-x: auto;
        }
        
        .settings-nav-item {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: #6b7280;
            white-space: nowrap;
            transition: all 0.3s ease;
            border-bottom: 2px solid transparent;
        }
        
        .settings-nav-item:hover {
            color: #3b82f6;
            background-color: #f8fafc;
        }
        
        .settings-nav-item.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
            background-color: #f8fafc;
        }
        
        .settings-section {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }
        
        .settings-section.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .settings-card h3 {
            margin: 0 0 1.5rem 0;
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 0.5rem;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #3b82f6;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .settings-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #9c80fd 0%, #5f34d9 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .btn-secondary {
            background: #f8fafc;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }
        
        .api-key-display {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            word-break: break-all;
            margin-top: 1rem;
        }
        
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .notification-item:last-child {
            margin-bottom: 0;
        }
        
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .backup-item:last-child {
            margin-bottom: 0;
        }
        
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }
        
        .btn-icon:hover {
            background-color: #f3f4f6;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            background-color: #f8fafc;
            min-height: 100vh;
        }

        .top-navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .search-bar {
            position: relative;
            flex: 1;
            max-width: 400px;
            margin: 0 2rem;
        }

        .search-bar input {
            width: 100%;
            padding: 0.75rem 3rem 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
        }

        .search-btn {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
        }

        .top-navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notifications {
            position: relative;
        }

        .notification-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc2626;
            color: white;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .help-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
        }

        .dashboard-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-header h2 {
            margin: 0;
            color: #1f2937;
            font-size: 1.75rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .settings-actions {
                flex-direction: column;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .search-bar {
                margin: 0 1rem;
            }

            .dashboard-content {
                border-radius: 1px;
                padding: .9rem;
            }

            .search-bar {

                padding: 5px 1px;
                background: none;
            }
        }

        input[type="text"], input[type="url"], input[type="password"], input[type="number"], select {
            border-radius: 0 !important;
        }
    </style>
</head>
<body>
    <?php $currentPage = 'settings'; 
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
                <input type="text" placeholder="Rechercher dans les paramètres...">
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
        
        <!-- Settings Content -->
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h2>Paramètres système</h2>
            </div>
            
            <!-- Success/Error Messages -->
            <div id="success-message" class="success-message">
                Paramètres sauvegardés avec succès !
            </div>
            <div id="error-message" class="error-message">
                Erreur lors de la sauvegarde des paramètres.
            </div>
            
            <!-- Settings Navigation -->
            <nav class="settings-nav">
                <button class="settings-nav-item active" data-section="general">Général</button>
                <button class="settings-nav-item" data-section="shipping">Expédition</button>
                <button class="settings-nav-item" data-section="api">Gestion des Boutiques</button>
                <button class="settings-nav-item" data-section="notifications">Notifications</button>
                <!--<button class="settings-nav-item" data-section="security">Sécurité</button>-->
                <!--<button class="settings-nav-item" data-section="backup">Sauvegarde</button>-->
                <button class="settings-nav-item" data-section="advanced">Avancé</button>
            </nav>
            
            <!-- General Settings -->
            <?php require_once('./GeneralSettings.php'); ?>
            <!-- Shipping Settings -->
            <?php require_once('./shippingSettings.php'); ?>
            <!-- API & Integrations Settings -->
            <?php require_once('./apiSettings.php'); ?>
            <!-- Notifications Settings -->
            <?php require_once('./notificationsSettings.php'); ?>
            <!-- Security Settings -->
            <?php //require_once('./securitySettings.php'); ?>
            <!-- Backup Settings -->
            <?php //require_once('./backupSettings.php'); ?>
            <!-- Advanced Settings -->
            <?php require_once('./advancedSettings.php'); ?>

            <!-- Action Buttons -->
            <div class="settings-actions">
                <button type="button" class="btn-secondary" onclick="resetForm()">Annuler</button>
                <button type="button" class="btn-primary save-btn" onclick="saveSettings()">Sauvegarder les paramètres</button>
            </div>
        </div>
    </main>
    
    <script> 
        // Settings navigation
        document.addEventListener('DOMContentLoaded', function() {
            const navItems = document.querySelectorAll('.settings-nav-item');
            const sections = document.querySelectorAll('.settings-section');
            
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    const targetSection = this.dataset.section;
                    
                    // Remove active class from all nav items and sections
                    navItems.forEach(nav => nav.classList.remove('active'));
                    sections.forEach(section => section.classList.remove('active'));
                    
                    // Add active class to clicked nav item and corresponding section
                    this.classList.add('active');
                    document.getElementById(targetSection).classList.add('active');
                });
            });

            const generalForm = document.getElementById('general-form');
    
            if (generalForm) {
                generalForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    saveGeneralSettings();
                });
            }
            
            // Load existing settings on page load
            loadGeneralSettings();

        });

        // Get currently active section
        function getActiveSection() {
            const activeNavItem = document.querySelector('.settings-nav-item.active');
            return activeNavItem ? activeNavItem.dataset.section : 'general';
        }

        // Main save settings function - calls appropriate sub-function based on active tab
        function saveSettings() {
            const activeSection = getActiveSection();
            const successMessage = document.getElementById('success-message');
            const errorMessage = document.getElementById('error-message');
            
            // Hide any existing messages
            if (successMessage) successMessage.style.display = 'none';
            if (errorMessage) errorMessage.style.display = 'none';
            
            // Call the appropriate save function based on active section
            switch(activeSection) {
                case 'general':
                    saveGeneralSettings();
                    break;
                case 'shipping':
                    saveShippingSettings();
                    break;
                case 'api':
                    saveApiSettings();
                    break;
                case 'notifications':
                    saveNotificationSettings(); // Changed this line
                    break;
                case 'security':
                    saveSecuritySettings();
                    break;
                case 'backup':
                    saveBackupSettings();
                    break;
                case 'advanced':
                    saveAdvancedSettings();
                    break;
                default:
                    saveGeneralSettings();
            }
        }

        function saveGeneralSettings() {
            const form = document.getElementById('general-form');
            const formData = new FormData(form);
            
            // Show loading state
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton ? submitButton.textContent : '';
            if (submitButton) {
                submitButton.textContent = 'Enregistrement...';
                submitButton.disabled = true;
            }
            
            fetch('./controllers/up_generalSettings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message);
                } else {
                    showNotification('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'Une erreur est survenue lors de l\'enregistrement');
            })
            .finally(() => {
                // Reset button state
                if (submitButton) {
                    submitButton.textContent = originalText || 'Enregistrer';
                    submitButton.disabled = false;
                }
            });
        }

        function loadGeneralSettings() {

            fetch('./controllers/get_generalSettings.php', {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.settings) {
                    populateForm(data.settings);
                }
            })
            .catch(error => {
                console.error('Error loading settings:', error);
            });
        }

        function populateForm(settings) {

            const form = document.getElementById('general-form');
            
            // Populate form fields
            const fields = {
                'company-name': settings.company_name,
                'company-email': settings.company_email,
                'company-phone': settings.company_phone,
                'company-address': settings.company_address,
                'timezone': settings.timezone,
                'default-currency': settings.default_currency,
                'default-language': settings.default_language
            };
            
            Object.keys(fields).forEach(fieldName => {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (field && fields[fieldName]) {
                    field.value = fields[fieldName];
                }
            });
        }
        
        // Save shipping settings function
        async function saveShippingSettings() {
            // Show loading state
            const saveButton = document.querySelector('#save-shipping-config-btn');
            const originalButtonText = saveButton ? saveButton.textContent : '';
            
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.textContent = 'Saving...';
            }

            try {
                const formData = new FormData();
                
                // Collect all form fields including the new ones to match database schema
                const fields = [
                    'default-shipping-method',
                    'tracking-update-interval',
                    'default-package-weight',
                    'default-package-length',
                    'default-package-width',
                    'default-package-height',
                    'priority'
                ];

                // Get selected company name from the select dropdown
                const defaultShippingSelect = document.getElementById('default-shipping-method');
                if (defaultShippingSelect) {
                    const selectedOption = defaultShippingSelect.options[defaultShippingSelect.selectedIndex];
                    const companyName = selectedOption.text.trim();
                    if (companyName) {
                        formData.append('company-name', companyName);
                    }
                }

                // Add regular form fields
                fields.forEach(fieldId => {
                    const element = document.getElementById(fieldId);
                    if (element) {
                        const value = element.value.trim();
                        if (value !== '') {
                            formData.append(fieldId, value);
                        }
                    }
                });


                // Handle checkboxes
                const checkboxes = [
                    'support-tracking',
                    'auto-tracking',
                    'auto-label-generation'
                ];

                checkboxes.forEach(fieldId => {
                    const element = document.getElementById(fieldId);
                    if (element) {
                        if (element.checked) {
                            formData.append(fieldId, 'on');
                        } else {
                            formData.append(fieldId, 'off');
                        }
                    }
                });

                // Validate required fields before sending
                const requiredFields = ['company-name'];
                const missingFields = [];
                
                requiredFields.forEach(fieldId => {
                    const element = document.getElementById(fieldId);
                    if (!element || !element.value.trim()) {
                        missingFields.push(fieldId.replace('-', ' '));
                    }
                });

                if (missingFields.length > 0) {
                    showError(`Please fill in the following required fields: ${missingFields.join(', ')}`);
                    return;
                }

                // Client-side validation for numeric fields
                const numericValidation = [
                    { id: 'tracking-update-interval', min: 5, max: 1440, name: 'Tracking update interval', unit: 'minutes' },
                    { id: 'default-package-weight', min: 0.1, max: 1000, name: 'Package weight', unit: 'kg' },
                    { id: 'default-package-length', min: 1, max: 300, name: 'Package length', unit: 'cm' },
                    { id: 'default-package-width', min: 1, max: 300, name: 'Package width', unit: 'cm' },
                    { id: 'default-package-height', min: 1, max: 300, name: 'Package height', unit: 'cm' }
                ];

                for (const validation of numericValidation) {
                    const element = document.getElementById(validation.id);
                    if (element && element.value) {
                        const value = parseFloat(element.value);
                        if (isNaN(value) || value < validation.min || value > validation.max) {
                            showError(`${validation.name} must be between ${validation.min} and ${validation.max} ${validation.unit}`);
                            return;
                        }
                    }
                }

                // Send request
                const response = await fetch('./controllers/save_shipping_config.php', {
                    method: 'POST',
                    body: formData
                });

                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response');
                }

                const result = await response.json();
                
                if (result.success) {
                    showSuccess(result.message || 'Configuration saved successfully');
                    
                    // Update local config if function exists
                    if (typeof loadShippingData === 'function') {
                        await loadShippingData();
                    }
                    
                    // Optionally close modal or update UI state
                    if (typeof updateUIAfterSave === 'function') {
                        updateUIAfterSave(result);
                    }
                    
                } else {
                    showError(result.message || 'Failed to save configuration');
                }

            } catch (error) {
                console.error('Error saving shipping config:', error);
                
                // More specific error messages
                if (error.name === 'TypeError' && error.message.includes('fetch')) {
                    showError('Network error: Unable to connect to server. Please check your internet connection.');
                } else if (error.message.includes('JSON')) {
                    showError('Server error: Invalid response format. Please try again.');
                } else {
                    showError(`Error saving configuration: ${error.message}`);
                }
            } finally {
                // Restore button state
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.textContent = originalButtonText;
                }
            }
        }

        // Helper function to update UI after saving settings
        function updateShippingSettingsUI(savedSettings) {
            // You can add visual feedback here to show which company is currently selected
            // For example, highlight the selected company in the list
            
            const companiesList = document.getElementById('companies-list');
            if (companiesList) {
                // Remove any existing selection indicators
                const existingIndicators = companiesList.querySelectorAll('.company-selected');
                existingIndicators.forEach(indicator => indicator.remove());
                
                // Add selection indicator to the current company
                const companyItems = companiesList.querySelectorAll('.company-item');
                companyItems.forEach(item => {
                    const companyId = item.dataset.companyId || 
                                    item.querySelector('[onclick*="editCompany"]')?.getAttribute('onclick')?.match(/\d+/)?.[0];
                    
                    if (companyId == savedSettings.shipping_company_id) {
                        const indicator = document.createElement('div');
                        indicator.className = 'company-selected';
                        indicator.innerHTML = '<span style="color: #10b981; font-weight: bold;">✓ Currently Selected</span>';
                        item.querySelector('.company-header').appendChild(indicator);
                    }
                });
            }
        }

        // Function to get current shipping settings for a specific company
        function getCurrentShippingSettings(companyId) {

            const company = shippingCompanies.find(c => c.id == companyId);
            const userSetting = userSettings.find(setting => setting.shipping_company_id == companyId);
            
            if (!company) return null;
            
            const otherSettings = userSetting && userSetting.other_settings ? 
                JSON.parse(userSetting.other_settings) : {};
            
            return {
                shipping_company_id: companyId,
                company_name: company.name,
                api_url: company.api_url,
                api_key: company.api_key || '',
                support_tracking: company.supports_tracking == 1 || otherSettings.supports_tracking,
                auto_track: document.getElementById('auto-tracking')?.checked || false,
                SmartLabel: document.getElementById('auto-label-generation')?.checked || false,
                priority: 1, // Default priority
                additional_settings: {
                    tracking_update_interval: document.getElementById('tracking-update-interval')?.value || 30,
                    default_package_weight: document.getElementById('default-package-weight')?.value || 1.0,
                    default_package_length: document.getElementById('default-package-length')?.value || 20,
                    default_package_width: document.getElementById('default-package-width')?.value || 15,
                    default_package_height: document.getElementById('default-package-height')?.value || 10
                }
            };
        }

        // Function to validate shipping settings before saving
        function validateShippingSettings() {
            const selectedCompanyId = document.getElementById('default-shipping-method').value;
            
            if (!selectedCompanyId) {
                showError('Please select a shipping company');
                return false;
            }
            
            const trackingInterval = document.getElementById('tracking-update-interval').value;
            if (trackingInterval < 5 || trackingInterval > 1440) {
                showError('Tracking update interval must be between 5 and 1440 minutes');
                return false;
            }
            
            const packageWeight = document.getElementById('default-package-weight').value;
            if (packageWeight <= 0) {
                showError('Package weight must be greater than 0');
                return false;
            }
            
            const packageDimensions = [
                document.getElementById('default-package-length').value,
                document.getElementById('default-package-width').value,
                document.getElementById('default-package-height').value
            ];
            
            if (packageDimensions.some(dim => dim <= 0)) {
                showError('All package dimensions must be greater than 0');
                return false;
            }
            
            return true;
        }

        // Enhanced save function with validation
        async function saveShippingSettingsWithValidation() {
            if (validateShippingSettings()) {
                await saveShippingSettings();
            }
        }

        // Auto-save functionality (optional)
        function enableAutoSaveShippingSettings() {
            const formElements = [
                'default-shipping-method',
                'auto-tracking',
                'tracking-update-interval',
                'auto-label-generation',
                'default-package-weight',
                'default-package-length',
                'default-package-width',
                'default-package-height'
            ];
            
            formElements.forEach(elementId => {
                const element = document.getElementById(elementId);
                if (element) {
                    element.addEventListener('change', debounce(saveShippingSettings, 2000));
                }
            });
        }

        // Export functions for global access
        window.saveShippingSettings = saveShippingSettings;
        window.saveShippingSettingsWithValidation = saveShippingSettingsWithValidation;
        window.getCurrentShippingSettings = getCurrentShippingSettings;
        window.validateShippingSettings = validateShippingSettings;
        window.enableAutoSaveShippingSettings = enableAutoSaveShippingSettings;

        function showNotification(type, message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <span>${message}</span>
                <button class="notification-close">&times;</button>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
            
            // Close button functionality
            notification.querySelector('.notification-close').addEventListener('click', function() {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            });
        }
        
        // Reset form function
        function resetForm() {
            if (confirm('Êtes-vous sûr de vouloir annuler les modifications ?')) {
                location.reload();
            }
        }

        function initializeNotificationForm(formId = 'notifications-form') {
            const notificationForm = document.getElementById(formId);
            
            if (!notificationForm) {
                console.warn(`Form with ID '${formId}' not found.`);
                return;
            }

            // Add form submit event listener
            notificationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitNotificationForm(this);
            });

            // Optional: Auto-save on toggle change
            const toggles = notificationForm.querySelectorAll('input[type="checkbox"]');
            toggles.forEach(toggle => {
                toggle.addEventListener('change', function() {
                    // Uncomment to enable auto-save:
                    // submitNotificationForm(notificationForm);
                });
            });
        }

        function submitNotificationForm(form) {
            const formData = new FormData(form);

            
            // Get save button (assuming it exists in your UI)
            const saveButton = document.querySelector('[data-action="save"]') || document.querySelector('.save-btn');
            
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.textContent = 'Sauvegarde...';
            }

            fetch('./controllers/save_notification_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                console.log(data)
                if (data.success) {
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message || 'Erreur lors de la sauvegarde', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erreur de connexion', 'error');
            })
            .finally(() => {
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.textContent = 'Sauvegarder les paramètres';
                }
            });
        }

        function saveNotificationSettings() {
            const notificationForm = document.getElementById('notifications-form');
            
            if (notificationForm) {
                // Trigger form submission
                submitNotificationForm(notificationForm)
            } else {
                console.error('Notification form not found');
                showNotification('Formulaire de notifications introuvable', 'error');
            }
        } 
        // Advanced actions
        function clearCache() {
            if (confirm('Êtes-vous sûr de vouloir vider le cache ?')) {
                alert('Cache vidé avec succès !');
            }
        }
        
        function syncData() {
            alert('Synchronisation des données en cours...');
        }
        
        function exportSettings() {
            const settings = {
                general: {
                    companyName: document.getElementById('company-name').value,
                    companyEmail: document.getElementById('company-email').value,
                    companyPhone: document.getElementById('company-phone').value,
                    timezone: document.getElementById('timezone').value,
                    companyAddress: document.getElementById('company-address').value,
                    defaultCurrency: document.getElementById('default-currency').value,
                    defaultLanguage: document.getElementById('default-language').value
                },
                // Add other sections as needed
            };
            
            const dataStr = JSON.stringify(settings, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const exportFileDefaultName = 'egrow-settings.json';
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', exportFileDefaultName);
            linkElement.click();
        }
        
        function importSettings() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.onchange = function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const settings = JSON.parse(e.target.result);
                            // Apply settings to form fields
                            if (settings.general) {
                                document.getElementById('company-name').value = settings.general.companyName || '';
                                document.getElementById('company-email').value = settings.general.companyEmail || '';
                                // Add other fields as needed
                            }
                            alert('Paramètres importés avec succès !');
                        } catch (error) {
                            alert('Erreur lors de l\'importation des paramètres.');
                        }
                    };
                    reader.readAsText(file);
                }
            };
            input.click();
        }
        
        function resetSettings() {
            if (confirm('Êtes-vous sûr de vouloir réinitialiser tous les paramètres ? Cette action est irréversible.')) {
                if (confirm('Dernière confirmation : tous les paramètres seront perdus !')) {
                    alert('Paramètres réinitialisés. La page va se recharger.');
                    setTimeout(() => location.reload(), 1000);
                }
            }
        }
        
        // Search functionality
        const searchInput = document.querySelector('.search-bar input');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const allCards = document.querySelectorAll('.settings-card');
            
            allCards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                if (cardText.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = searchTerm ? 'none' : 'block';
                }
            });
        });
        
        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('open-sidebar');
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                // This would toggle sidebar visibility on mobile
                console.log('Toggle sidebar');
            });
        }
    </script>
</body>
</html>