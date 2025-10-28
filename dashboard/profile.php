<?php
// Database connection and security functions remain the same
if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

$db = DB::getInstance();

if(!Session::exists(Config::get('session/session_name'))){
    Redirect::to('../login.php'); 
} 

if (isset($_GET['logout'])) {
    logout();
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../login.php');
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    return preg_match('/^[0-9+\-\s\(\)]{10,20}$/', $phone);
}

// Get current user data
$currentUser = $db->getThisQuery("SELECT * FROM users WHERE email = ?", [$_SESSION['user']['username']]);
if (empty($currentUser)) {
    Redirect::to('../login.php');
}

$currentUser = $currentUser[0];

$user = [$currentUser];

// Handle form submission
$message = '';
$messageType = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        // Validate and sanitize inputs
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($name)) {
            $errors[] = "Le nom est obligatoire";
        }
        
        if (empty($email)) {
            $errors[] = "L'email est obligatoire";
        } elseif (!validateEmail($email)) {
            $errors[] = "Format d'email invalide";
        }
        
        if (!empty($phone) && !validatePhone($phone)) {
            $errors[] = "Format de téléphone invalide";
        }
        
        // Check if email already exists (for other users)
        if (empty($errors)) {
            $emailExists = $db->getThisQuery("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $currentUser['id']]);
            if (!empty($emailExists)) {
                $errors[] = "Cet email est déjà utilisé par un autre utilisateur";
            }
        }
        
        // Password validation if changing password
        $updatePassword = false;
        if (!empty($newPassword)) {
            if (empty($currentPassword)) {
                $errors[] = "Le mot de passe actuel est requis pour changer le mot de passe";
            } elseif (!password_verify($currentPassword, $currentUser['password'])) {
                $errors[] = "Mot de passe actuel incorrect";
            } elseif (strlen($newPassword) < 6) {
                $errors[] = "Le nouveau mot de passe doit contenir au moins 6 caractères";
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = "Les mots de passe ne correspondent pas";
            } else {
                $updatePassword = true;
            }
        }
        
        // Update user information if no errors
        if (empty($errors)) {
            if ($updatePassword) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateResult = $db->query("UPDATE users SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?", 
                    [$name, $email, $phone, $hashedPassword, $currentUser['id']]);
            } else {
                $updateResult = $db->query("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?", 
                    [$name, $email, $phone, $currentUser['id']]);
            }
            
            if ($updateResult) {
                // Update session data
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                
                // Refresh current user data
                $currentUser = $db->getThisQuery("SELECT * FROM users WHERE id = ?", [$currentUser['id']])[0];
                
                $message = "Profil mis à jour avec succès";
                $messageType = 'success';
                
                if ($updatePassword) {
                    $message .= " et mot de passe modifié";
                }
            } else {
                $errors[] = "Erreur lors de la mise à jour du profil";
            }
        }
        
        if (!empty($errors)) {
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        $errors[] = "Une erreur inattendue s'est produite";
        $messageType = 'error';
    }
}

// Format dates for display
$createdAt = !empty($currentUser['created_at']) ? date('d/m/Y H:i', strtotime($currentUser['created_at'])) : 'N/A';
$lastConnection = !empty($currentUser['derniere_connexion']) ? date('d/m/Y H:i', strtotime($currentUser['derniere_connexion'])) : 'N/A';
$evaluationEndDate = !empty($currentUser['evaluation_end_date']) ? date('d/m/Y', strtotime($currentUser['evaluation_end_date'])) : 'N/A';

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Profil utilisateur - PLatForme">
    <title>Mon Profil | OBG</title>
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2563eb;
            --primary-light: #dbeafe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success-color: #059669;
            --danger-color: #dc2626;
            --border-radius: 8px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .main-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 0;
            background-color: var(--gray-50);
        }
        
        /* Top Navigation */
        .top-navbar {
            background-color: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }
        
        .top-navbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .nav-btn {
            background: none;
            border: none;
            color: var(--gray-600);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: var(--border-radius);
            transition: all 0.2s ease;
        }
        
        .nav-btn:hover {
            background-color: var(--gray-100);
            color: var(--gray-800);
        }
        
        /* Content Area */
        .dashboard-content {
            padding: 2rem;
            max-width: 100%;
            margin: 0 auto;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .breadcrumb a {
            color: var(--gray-500);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            color: var(--primary-color);
        }
        
        .breadcrumb .separator {
            color: var(--gray-400);
        }
        
        /* Profile Header */
        .profile-header {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }
        
        .profile-info h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }
        
        .profile-info .email {
            color: var(--gray-600);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .profile-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .meta-item i {
            color: var(--primary-color);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            background-color: var(--primary-light);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }
        
        .stat-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-600);
            margin: 0;
        }
        
        .stat-value2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #ecfdf5;
            color: var(--success-color);
        }
        
        .status-inactive {
            background-color: #fef2f2;
            color: var(--danger-color);
        }
        
        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: #ecfdf5;
            border-color: var(--success-color);
            color: var(--success-color);
        }
        
        .alert-error {
            background-color: #fef2f2;
            border-color: var(--danger-color);
            color: var(--danger-color);
        }
        
        .alert ul {
            margin-top: 0.5rem;
            padding-left: 1.5rem;
        }
        
        /* Form */
        .profile-form {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow);
        }
        
        .form-section {
            margin-bottom: 2.5rem;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .form-section h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.875rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .form-group input:disabled {
            background-color: var(--gray-100);
            color: var(--gray-500);
        }
        
        .toggle-password {
            position: relative;
        }
        
        .toggle-password-btn {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
        }
        
        .password-hint {
            background-color: var(--gray-50);
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
            border-left: 3px solid var(--primary-color);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
        }
        
        .btn-secondary {
            background-color: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background-color: var(--gray-300);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-content {
                padding: 1rem;
            }
            
            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

</head>
<body>
    <div class="main-container">
        <!-- Sidebar -->
        <?php 
        $currentPage = 'profile'; 
        require_once('../assets/sidebar.php'); 
        ?>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Top Navigation -->
            <nav class="top-navbar">
                
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
            
            <!-- Content -->
            <div class="dashboard-content">
                <!-- Breadcrumb -->
                <div class="breadcrumb">
                    <a href="../dashboard.php">
                        <i class="bi bi-house-door"></i> Tableau de bord
                    </a>
                    <span class="separator">/</span>
                    <span>Mon Profil</span>
                </div>
                
                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="profile-info">
                        <h1><?= htmlspecialchars($currentUser['name']) ?></h1>
                        <p class="email">
                            <i class="bi bi-envelope"></i>
                            <?= htmlspecialchars($currentUser['email']) ?>
                        </p>
                        <div class="profile-meta">
                            <div class="meta-item">
                                <i class="bi bi-shield-check"></i>
                                <span>Rôle: Administrateur</span>
                            </div>
                            <div class="meta-item">
                                <i class="bi bi-calendar-plus"></i>
                                <span>Membre depuis: <?= $createdAt ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="bi bi-clock-history"></i>
                                <span>Dernière connexion: <?= $lastConnection ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="bi bi-activity"></i>
                            </div>
                            <h3 class="stat-title">Statut du compte</h3>
                        </div>
                        <p class="stat-value2">
                            <span class="status-badge <?= $currentUser['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <i class="bi bi-<?= $currentUser['is_active'] ? 'check-circle' : 'x-circle' ?>"></i>
                                <?= $currentUser['is_active'] ? 'Actif' : 'Inactif' ?>
                            </span>
                        </p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <h3 class="stat-title">Date de création</h3>
                        </div>
                        <p class="stat-value2"><?= $createdAt ?></p>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="bi bi-clock"></i>
                            </div>
                            <h3 class="stat-title">Dernière activité</h3>
                        </div>
                        <p class="stat-value2"><?= $lastConnection ?></p>
                    </div>
                    
                    <?php if (!empty($currentUser['evaluation_end_date'])): ?>
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <h3 class="stat-title">Fin d'évaluation</h3>
                        </div>
                        <p class="stat-value2"><?= $evaluationEndDate ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $messageType ?>">
                        <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Erreurs détectées :</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Form -->
                <form method="POST" class="profile-form" id="profileForm">
                    <div class="form-section">
                        <h2>
                            <i class="bi bi-person-lines-fill"></i>
                            Informations personnelles
                        </h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Nom complet *</label>
                                <input 
                                    type="text" 
                                    id="name" 
                                    name="name" 
                                    value="<?= htmlspecialchars($currentUser['name']) ?>" 
                                    required
                                    placeholder="Entrez votre nom complet"
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="username">Nom d'utilisateur</label>
                                <input 
                                    type="text" 
                                    id="username" 
                                    value="<?= htmlspecialchars($currentUser['username']) ?>" 
                                    disabled
                                    placeholder="Nom d'utilisateur"
                                >
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Adresse email *</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    value="<?= htmlspecialchars($currentUser['email']) ?>" 
                                    required
                                    placeholder="votre@email.com"
                                >
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Numéro de téléphone</label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>"
                                    placeholder="06 ..."
                                >
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="role">Rôle</label>
                                <input 
                                    type="text" 
                                    id="role" 
                                    value="Compte Administrateur" 
                                    disabled
                                >
                            </div>
                            
                            <?php if (!empty($currentUser['plan_id'])): 
                                $plan = null;
                                if (!empty($currentUser['plan_id'])) {
                                    $planData = $db->getThisQuery("SELECT name, price FROM plans WHERE id = ?", [$currentUser['plan_id']]);
                                    if ($planData && !empty($planData[0]['name'])) {
                                        $plan = $planData[0];
                                    }
                                } endif;
                                if ($plan): ?>
                            <div class="form-group">
                                <label for="plan_name">Plan souscrit</label>
                                <input 
                                    type="text" 
                                    id="plan_name" 
                                    value="<?= htmlspecialchars($plan['name']) ?><?= isset($plan['price']) ? ' - ' . htmlspecialchars($plan['price']) . ' MAD' : '' ?>" 
                                    disabled
                                >
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h2>
                            <i class="bi bi-shield-lock"></i>
                            Sécurité du compte
                        </h2>
                        
                        <div class="password-hint">
                            <p>
                                <strong>Laissez ces champs vides</strong> si vous ne souhaitez pas modifier votre mot de passe. 
                                Pour des raisons de sécurité, vous devez saisir votre mot de passe actuel pour en définir un nouveau.
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label for="current_password">Mot de passe actuel</label>
                            <div class="toggle-password">
                                <input 
                                    type="password" 
                                    id="current_password" 
                                    name="current_password"
                                    placeholder="Entrez votre mot de passe actuel"
                                >
                                <button type="button" class="toggle-password-btn" onclick="togglePassword('current_password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">Nouveau mot de passe</label>
                                <div class="toggle-password">
                                    <input 
                                        type="password" 
                                        id="new_password" 
                                        name="new_password"
                                        placeholder="Minimum 6 caractères"
                                    >
                                    <button type="button" class="toggle-password-btn" onclick="togglePassword('new_password')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirmer le mot de passe</label>
                                <div class="toggle-password">
                                    <input 
                                        type="password" 
                                        id="confirm_password" 
                                        name="confirm_password"
                                        placeholder="Répétez le nouveau mot de passe"
                                    >
                                    <button type="button" class="toggle-password-btn" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-check-circle"></i>
                            Mettre à jour le profil
                        </button>
                        <a href="../dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i>
                            Retour au tableau de bord
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>

        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Always prevent default form submission
            
            const submitBtn = document.getElementById('submitBtn');
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            // Basic validation
            if (!name) {
                alert('Le nom est obligatoire');
                return false;
            }
            
            if (!email) {
                alert('L\'email est obligatoire');
                return false;
            }
            
            // Email format validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Format d\'email invalide');
                return false;
            }
            
            // Password validation if changing password
            if (newPassword || confirmPassword) {
                if (!currentPassword) {
                    alert('Le mot de passe actuel est requis pour changer le mot de passe');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    alert('Les mots de passe ne correspondent pas');
                    return false;
                }
                
                if (newPassword.length < 6) {
                    alert('Le nouveau mot de passe doit contenir au moins 6 caractères');
                    return false;
                }
            }
            
            // Show loading state
            const originalBtnContent = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass"></i> Mise à jour en cours...';
            
            // Prepare form data
            const formData = new FormData();
            
            // Add all form fields to FormData
            const formElements = this.elements;
            for (let i = 0; i < formElements.length; i++) {
                const element = formElements[i];
                if (element.name && element.type !== 'submit') {
                    if (element.type === 'checkbox') {
                        formData.append(element.name, element.checked ? '1' : '0');
                    } else if (element.type === 'radio') {
                        if (element.checked) {
                            formData.append(element.name, element.value);
                        }
                    } else {
                        formData.append(element.name, element.value);
                    }
                }
            }
            
            // Send AJAX request
            fetch('./controllers/update_profileApi.php', {

                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnContent;
                
                if (data.success) {
                    // Success handling
                    alert(data.message || 'Profil mis à jour avec succès');
                    
                    // Clear password fields on success
                    if (newPassword) {
                        document.getElementById('new_password').value = '';
                        document.getElementById('confirm_password').value = '';
                        document.getElementById('current_password').value = '';
                    }
                    
                    // Optional: Redirect or update UI
                    // window.location.reload(); // Uncomment if you want to reload the page
                    
                } else {
                    // Error handling
                    alert(data.message || 'Erreur lors de la mise à jour du profil');
                }
            })
            .catch(error => {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnContent;
                
                console.error('Error:', error);
                alert('Une erreur est survenue lors de la mise à jour. Veuillez réessayer.');
            });
        });

        // Optional: Add real-time validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.classList.add('is-invalid');
                // You can add visual feedback here
            } else {
                this.classList.remove('is-invalid');
            }
        });

        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });

        // Sidebar toggle
        const openSidebarBtn = document.getElementById('open-sidebar');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');

        if (openSidebarBtn && sidebar && mainContent) {
            openSidebarBtn.addEventListener('click', function (e) {
                e.stopPropagation(); // prevent body click from firing immediately
                sidebar.classList.toggle('active');
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', function (e) {
                if (
                    sidebar.classList.contains('active') &&
                    !sidebar.contains(e.target) &&
                    e.target !== openSidebarBtn
                ) {
                    sidebar.classList.remove('active');
                    sidebar.classList.add('collapsed');
                    mainContent.classList.remove('expanded');
                }
            });
        }

    </script>
</body>
</html>