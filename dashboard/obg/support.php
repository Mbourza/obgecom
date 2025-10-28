<?php
// Database connection and session check for super admin
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if user is logged in and is super admin
if(!Session::exists(Config::get('session/session_name'))){
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

// Include email manager
require_once '../controllers/EmailManager.php';
$emailManager = new EmailManager();

// Handle actions
if ($_POST) {
    if (isset($_POST['send_email'])) {
        $to = $_POST['to'];
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        $cc = isset($_POST['cc']) ? array_filter(explode(',', $_POST['cc'])) : [];
        $bcc = isset($_POST['bcc']) ? array_filter(explode(',', $_POST['bcc'])) : [];
        
        $result = $emailManager->sendEmail($to, $subject, $message, $cc, $bcc);
        
        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        Redirect::to('./email_support');
    }
    
    if (isset($_POST['search_emails'])) {
        $query = $_POST['search_query'];
        $field = $_POST['search_field'];
        $search_results = $emailManager->searchEmails($query, $field);
        Session::flash('search_results', $search_results);
        Session::flash('search_query', $query);
        Redirect::to('./email_support');
    }
    
    // Handle delete email
    if (isset($_POST['delete_email'])) {
        $emailId = $_POST['email_id'];
        $result = $emailManager->deleteEmail($emailId);
        
        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        Redirect::to('./email_support');
    }
}

// Handle GET actions
if ($_GET) {
    // View email
    if (isset($_GET['view_email'])) {
        $emailId = $_GET['email_id'];
        $email = $emailManager->getEmailById($emailId);
        
        if ($email) {
            // Mark as read
            if (!$email['read']) {
                $emailManager->markAsRead($emailId);
            }
            
            header('Content-Type: application/json');
            echo json_encode($email);
            exit;
        }
    }
    
    // Delete email via GET
    if (isset($_GET['delete_email'])) {
        $emailId = $_GET['email_id'];
        $result = $emailManager->deleteEmail($emailId);
        
        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        Redirect::to('./email_support');
    }
}

// Get email statistics
$email_stats = $emailManager->getEmailStats();

// Get recent emails
$recent_emails = $emailManager->getEmails(20, 1);

// Get all users for email composition
$all_users = $db->getThisQuery("SELECT id, name, email, created_at FROM users ORDER BY created_at DESC LIMIT 1000");

// Get search results if any
$search_results = Session::exists('search_results') ? Session::flash('search_results') : [];
$search_query = Session::exists('search_query') ? Session::flash('search_query') : '';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Email | Super Admin OBG</title>
    <link rel="stylesheet" href="../assets/css/common.css" />
    <link rel="stylesheet" href="../assets/css/dashboard.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a73e8;
            --primary-light: #4285f4;
            --secondary-color: #34a853;
            --accent-color: #fbbc04;
            --warning-color: #f29900;
            --danger-color: #ea4335;
            --dark-color: #202124;
            --light-color: #f8f9fa;
            --border-color: #dadce0;
            --text-primary: #202124;
            --text-secondary: #5f6368;
            --text-light: #9aa0a6;
            
            --primary-gradient: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
            --primary-gradient-hover: linear-gradient(135deg, #1669d9 0%, #3b78e7 100%);
            --success-gradient: linear-gradient(135deg, #34a853 0%, #2e8b47 100%);
            --warning-gradient: linear-gradient(135deg, #fbbc04 0%, #f29900 100%);
            --danger-gradient: linear-gradient(135deg, #ea4335 0%, #d93025 100%);
            
            --card-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
            --card-shadow-hover: 0 2px 6px 2px rgba(60, 64, 67, 0.15), 0 1px 2px 0 rgba(60, 64, 67, 0.3);
            --modal-shadow: 0 24px 38px 3px rgba(0, 0, 0, 0.14), 0 9px 46px 8px rgba(0, 0, 0, 0.12), 0 11px 15px -7px rgba(0, 0, 0, 0.2);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Google Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f8f9fa;
            color: var(--text-primary);
            margin: 0;
            padding: 0;
        }
        
        .email-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1.5rem;
            padding: 0 1rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 500;
            color: var(--dark-color);
            margin: 0;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-title i {
            color: var(--primary-color);
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border-top: 4px solid var(--primary-color);
        }
        
        .stat-card:nth-child(2) { border-top-color: var(--secondary-color); }
        .stat-card:nth-child(3) { border-top-color: var(--warning-color); }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
            background: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .stat-card:nth-child(2) .stat-icon { background: var(--secondary-color); }
        .stat-card:nth-child(3) .stat-icon { background: var(--warning-color); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .search-box {
            margin-bottom: 2rem;
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin: 0 1rem 2rem;
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }
        
        .search-select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
            min-width: 150px;
        }
        
        .search-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }
        
        .email-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 1rem;
            min-height: 600px;
            padding: 0 1rem;
        }
        
        .email-sidebar {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            display: flex;
            flex-direction: column;
            height: fit-content;
        }
        
        .email-content {
            background: white;
            border-radius: 8px;
            padding: 0;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .email-content-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: white;
        }
        
        .email-content-title {
            font-size: 1.25rem;
            font-weight: 500;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .email-list {
            flex: 1;
            overflow-y: auto;
            max-height: 500px;
        }
        
        .email-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            position: relative;
        }
        
        .email-item:hover {
            background: #f8f9fa;
        }
        
        .email-item.unread {
            background: #f0f6ff;
        }
        
        .email-item.selected {
            background: #e8f0fe;
        }
        
        .email-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 500;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .email-content-main {
            flex: 1;
            min-width: 0;
        }
        
        .email-header-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .email-sender {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }
        
        .email-subject {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .email-preview {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .email-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-light);
        }
        
        .email-date {
            font-size: 0.875rem;
            color: var(--text-light);
            flex-shrink: 0;
        }
        
        .email-actions {
            display: flex;
            gap: 0.5rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .email-item:hover .email-actions {
            opacity: 1;
        }
        
        .compose-btn {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 24px;
            font-weight: 500;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
        }
        
        .compose-btn:hover {
            background: var(--primary-light);
            box-shadow: 0 2px 6px 2px rgba(60, 64, 67, 0.15), 0 1px 2px 0 rgba(60, 64, 67, 0.3);
        }
        
        .user-section {
            margin-top: 1rem;
        }
        
        .user-section-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .user-item {
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .user-item:hover {
            background: #f8f9fa;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 500;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .user-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-start !important;
            padding: 0 0.5rem !important;
        }
        
        .user-name {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        
        .user-email {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: -webkit-box;
            line-clamp: 1;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
        }
        
        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--border-color);
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: #d93025;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            justify-content: center;
            border-radius: 50%;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            backdrop-filter: blur(4px);
            animation: modalFadeIn 0.3s ease;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 0;
            box-shadow: var(--modal-shadow);
            width: 100%;
            max-width: 800px;
            max-height: 90vh;
            overflow: hidden;
            animation: modalSlideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes modalSlideIn {
            from { transform: translateY(-20px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: white;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 500;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-light);
            transition: color 0.3s ease;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-modal:hover {
            color: var(--danger-color);
            background: rgba(234, 67, 53, 0.1);
        }
        
        .modal-body {
            padding: 1.5rem;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }
        
        .email-body {
            min-height: 200px;
            resize: vertical;
            line-height: 1.5;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            background: white;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        .email-detail {
            background: white;
        }
        
        .email-detail-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
            background: white;
        }
        
        .email-detail-subject {
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .email-detail-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .email-detail-body {
            padding: 1.5rem;
            line-height: 1.6;
            color: var(--text-primary);
            font-size: 1rem;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .email-detail-body img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }
        
        .email-detail-actions {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: var(--primary-color);
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            color: var(--text-primary);
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-unread {
            background: var(--primary-color);
            color: white;
        }
        
        .badge-important {
            background: var(--warning-color);
            color: white;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn-loading .loading-spinner {
            display: inline-block;
        }
        
        .btn-loading .btn-text {
            display: none;
        }
        
        .delete-confirm-modal .modal-content {
            max-width: 500px;
        }
        
        .delete-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            margin-top: 1.5rem;
        }
        
        @media (max-width: 1200px) {
            .email-layout {
                grid-template-columns: 250px 1fr;
            }
        }
        
        @media (max-width: 1024px) {
            .email-layout {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .email-sidebar {
                order: 2;
            }
            
            .email-content {
                order: 1;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .email-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-title {
                font-size: 1.75rem;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-select {
                width: 100%;
            }
            
            .modal-content {
                margin: 0.5rem;
                padding: 0;
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .email-detail-actions {
                flex-direction: column;
                gap: 0.75rem;
                align-items: stretch;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-light);
        }
        
        /* Gmail-like email detail styling */
        .email-detail-container {
            max-width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .email-detail-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: white;
        }

        .email-detail-subject {
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .email-detail-meta {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .meta-label {
            font-weight: 500;
            color: var(--text-primary);
            min-width: 30px;
        }

        .meta-value {
            color: var(--text-primary);
        }

        .sender-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 500;
            font-size: 1rem;
            margin-right: 0.75rem;
        }

        .sender-info {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .sender-main {
            display: flex;
            flex-direction: column;
        }

        .sender-name {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .sender-email {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .email-date {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-left: auto;
        }

        .email-detail-body {
            padding: 1.5rem;
            line-height: 1.6;
            color: var(--text-primary);
            font-size: 1rem;
            background: white;
            min-height: 200px;
        }

        .email-body-content {
            max-width: 100%;
            overflow-wrap: break-word;
            white-space: pre-wrap;
        }

        .email-body-content p {
            margin-bottom: 1rem;
        }

        .email-body-content a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .email-body-content a:hover {
            text-decoration: underline;
        }

        .email-attachments {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .attachments-title {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .attachment-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .attachment-item:hover {
            background: #e8f0fe;
            border-color: var(--primary-color);
        }

        .attachment-icon {
            width: 36px;
            height: 36px;
            background: var(--primary-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .attachment-size {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .download-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .download-btn:hover {
            background: var(--primary-light);
        }

        .email-tags {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
            flex-wrap: wrap;
        }

        .email-tag {
            padding: 0.25rem 0.5rem;
            background: #e8f0fe;
            color: var(--primary-color);
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .email-tag.unread {
            background: #fce8e6;
            color: var(--danger-color);
        }

        .email-tag.important {
            background: #fef7e0;
            color: var(--warning-color);
        }

        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
            color: var(--text-secondary);
        }

        .loading-spinner-large {
            width: 40px;
            height: 40px;
            border: 3px solid #f3f4f6;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        .error-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--danger-color);
        }

        .error-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .email-actions-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            background: white;
            border-top: 1px solid var(--border-color);
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            color: var(--text-primary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background: #f8f9fa;
            border-color: #bdc1c6;
        }

        .action-btn.danger {
            color: var(--danger-color);
            border-color: #fad2cf;
        }

        .action-btn.danger:hover {
            background: #fce8e6;
            border-color: #f28b82;
        }

        /* Modal adjustments for email view */
        #viewEmailModal .modal-content {
            max-width: 800px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }

        #viewEmailModal .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 0;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .email-detail-header {
                padding: 1rem;
            }
            
            .email-detail-body {
                padding: 1rem;
            }
            
            .meta-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .email-date {
                margin-left: 0;
            }
            
            .email-actions-bar {
                padding: 0.75rem 1rem;
                flex-wrap: wrap;
            }
            
            .action-btn {
                flex: 1;
                min-width: 120px;
                justify-content: center;
            }
        }

        /* Enhanced Gmail-like styles */
        .email-toolbar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: white;
        }

        .toolbar-btn {
            background: none;
            border: none;
            padding: 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s ease;
        }

        .toolbar-btn:hover {
            background: #f1f3f4;
            color: var(--text-primary);
        }

        .email-checkbox {
            margin-right: 0.5rem;
        }

        .email-star {
            color: var(--text-light);
            transition: color 0.2s ease;
        }

        .email-star.starred {
            color: var(--accent-color);
        }

        .email-star:hover {
            color: var(--accent-color);
        }

        .email-tags-container {
            display: flex;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }

        .email-tag {
            padding: 0.125rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .email-tag.work {
            background: #e8f0fe;
            color: var(--primary-color);
        }

        .email-tag.personal {
            background: #e6f4ea;
            color: var(--secondary-color);
        }

        .email-tag.important {
            background: #fef7e0;
            color: var(--warning-color);
        }

        .email-importance {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .email-importance.high {
            background: var(--danger-color);
        }

        .email-importance.medium {
            background: var(--warning-color);
        }

        .email-importance.low {
            background: var(--secondary-color);
        }

        .recipient-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .recipient-chip {
            display: flex;
            align-items: center;
            background: #e8f0fe;
            border-radius: 16px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            color: var(--primary-color);
        }

        .recipient-chip .remove {
            margin-left: 0.5rem;
            cursor: pointer;
            font-weight: bold;
        }

        .recipient-chip .remove:hover {
            color: var(--danger-color);
        }

        .user-search-container {
            position: relative;
        }

        .user-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-shadow: var(--card-shadow);
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .user-search-result {
            padding: 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-search-result:hover {
            background: #f8f9fa;
        }

        .user-search-result:last-child {
            border-bottom: none;
        }

        .email-priority-selector {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .priority-option {
            padding: 0.25rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .priority-option:hover {
            background: #f8f9fa;
        }

        .priority-option.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .email-format-selector {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .format-option {
            padding: 0.25rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .format-option:hover {
            background: #f8f9fa;
        }

        .format-option.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .email-signature {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid var(--primary-color);
        }

        .email-templates {
            margin-top: 1rem;
        }

        .template-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .email-scheduling {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .schedule-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .schedule-option input {
            margin: 0;
        }

        .schedule-datetime {
            margin-top: 0.5rem;
            display: none;
        }

        .email-tracking {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .tracking-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .tracking-option input {
            margin: 0;
        }

        .rich-text-editor {
            border: 1px solid var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }

        .editor-toolbar {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            background: #f8f9fa;
        }

        .editor-btn {
            background: none;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 2px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s ease;
        }

        .editor-btn:hover {
            background: #e8eaed;
            color: var(--text-primary);
        }

        .editor-content {
            min-height: 200px;
            padding: 1rem;
            outline: none;
        }

        .email-folder {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .email-folder:hover {
            background: #f8f9fa;
        }

        .email-folder.active {
            background: #e8f0fe;
            color: var(--primary-color);
        }

        .folder-count {
            margin-left: auto;
            background: var(--primary-color);
            color: white;
            border-radius: 12px;
            padding: 0.125rem 0.5rem;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .email-filter {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .filter-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .filter-option input {
            margin: 0;
        }

        .email-bulk-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid var(--border-color);
        }

        .bulk-action-btn {
            background: none;
            border: 1px solid var(--border-color);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s ease;
            font-size: 0.8rem;
        }

        .bulk-action-btn:hover {
            background: white;
            color: var(--text-primary);
        }

        .email-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            background: white;
        }

        .pagination-info {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pagination-btn {
            background: none;
            border: 1px solid var(--border-color);
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s ease;
            font-size: 0.8rem;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #f8f9fa;
            color: var(--text-primary);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .email-quick-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .quick-action-btn {
            background: none;
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.2s ease;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-action-btn:hover {
            background: #f8f9fa;
            color: var(--text-primary);
        }

        .email-notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            padding: 1rem 1.5rem;
            background: white;
            border-radius: 8px;
            box-shadow: var(--modal-shadow);
            border-left: 4px solid var(--primary-color);
            z-index: 1060;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateX(120%);
            transition: transform 0.3s ease;
        }

        .email-notification.show {
            transform: translateX(0);
        }

        .email-notification.success {
            border-left-color: var(--secondary-color);
        }

        .email-notification.error {
            border-left-color: var(--danger-color);
        }

        .email-notification.warning {
            border-left-color: var(--warning-color);
        }

        .email-notification .close {
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: var(--text-light);
            margin-left: auto;
        }

        .email-notification .close:hover {
            color: var(--danger-color);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php $currentPage = 'email_support';
    require_once('../../assets/sidebarSuper.php'); ?>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="top-navbar">
            <div class="navbar-title">
                <h1>
                    <i class="bi bi-envelope-check"></i>
                    Support Email
                </h1>
            </div>
        </nav>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Header -->
            <div class="email-header">
                <h2 class="page-title">
                    <i class="bi bi-envelope-paper"></i>
                    Centre de Messagerie Support
                </h2>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openComposeModal()">
                        <i class="bi bi-pencil-square"></i>
                        <span>Nouvel Email</span>
                    </button>
                </div>
            </div>
            
            <!-- Flash Messages -->
            <?php if (Session::exists('success')): ?>
                <div class="alert alert-success d-flex align-items-center">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo Session::flash('success'); ?>
                </div>
            <?php endif; ?>
            
            <?php if (Session::exists('error')): ?>
                <div class="alert alert-error d-flex align-items-center">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?php echo Session::flash('error'); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-envelope"></i>
                    </div>
                    <div class="stat-value"><?php echo $email_stats['total']; ?></div>
                    <div class="stat-label">Total des Emails</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-envelope-open"></i>
                    </div>
                    <div class="stat-value"><?php echo $email_stats['read']; ?></div>
                    <div class="stat-label">Emails Lus</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-envelope-exclamation"></i>
                    </div>
                    <div class="stat-value"><?php echo $email_stats['unread']; ?></div>
                    <div class="stat-label">Non Lus</div>
                </div>
            </div>
            
            <!-- Search Box -->
            <div class="search-box">
                <form method="POST" class="search-form">
                    <input type="text" name="search_query" class="search-input" 
                           placeholder="🔍 Rechercher dans les emails..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    <select name="search_field" class="search-select">
                        <option value="all">Tout</option>
                        <option value="subject">Sujet</option>
                        <option value="from">Expéditeur</option>
                        <option value="body">Contenu</option>
                    </select>
                    <button type="submit" name="search_emails" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                        Rechercher
                    </button>
                </form>
            </div>
            
            <!-- Email Layout -->
            <div class="email-layout">
                <!-- Sidebar -->
                <div class="email-sidebar">
                    <button class="compose-btn" onclick="openComposeModal()">
                        <i class="bi bi-pencil-square"></i>
                        <span>Composer un Email</span>
                    </button>
                    
                    <!-- Email Folders -->
                    <div class="email-folders">
                        <div class="email-folder active" onclick="setActiveFolder(this)">
                            <i class="bi bi-inbox"></i>
                            <span>Boîte de réception</span>
                            <span class="folder-count"><?php echo $email_stats['total']; ?></span>
                        </div>
                        <div class="email-folder" onclick="setActiveFolder(this)">
                            <i class="bi bi-envelope"></i>
                            <span>Non lus</span>
                            <span class="folder-count"><?php echo $email_stats['unread']; ?></span>
                        </div>
                        <div class="email-folder" onclick="setActiveFolder(this)">
                            <i class="bi bi-star"></i>
                            <span>Importants</span>
                            <span class="folder-count">0</span>
                        </div>
                        <div class="email-folder" onclick="setActiveFolder(this)">
                            <i class="bi bi-send"></i>
                            <span>Envoyés</span>
                            <span class="folder-count">0</span>
                        </div>
                        <div class="email-folder" onclick="setActiveFolder(this)">
                            <i class="bi bi-trash"></i>
                            <span>Corbeille</span>
                            <span class="folder-count">0</span>
                        </div>
                    </div>
                    
                    <!-- User Section -->
                    <div class="user-section">
                        <h3 class="user-section-title">
                            <i class="bi bi-people"></i>
                            Utilisateurs Récents
                        </h3>
                        <div class="user-list">
                            <?php foreach ($all_users as $user): ?>
                                <div class="user-item" onclick="selectUserForEmail('<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['name']); ?>')">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                        <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Email Content -->
                <div class="email-content">
                    <!-- Email Toolbar -->
                    <div class="email-toolbar">
                        <input type="checkbox" class="email-checkbox" onchange="toggleSelectAll(this)">
                        <button class="toolbar-btn" onclick="refreshEmails()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button class="toolbar-btn" onclick="archiveSelected()">
                            <i class="bi bi-archive"></i>
                        </button>
                        <button class="toolbar-btn" onclick="markAsSpam()">
                            <i class="bi bi-exclamation-circle"></i>
                        </button>
                        <button class="toolbar-btn" onclick="deleteSelected()">
                            <i class="bi bi-trash"></i>
                        </button>
                        <div style="flex: 1;"></div>
                        <button class="toolbar-btn" onclick="toggleEmailView()">
                            <i class="bi bi-list"></i>
                        </button>
                    </div>
                    
                    <div class="email-content-header">
                        <h3 class="email-content-title">
                            <i class="bi bi-inbox"></i>
                            <?php echo !empty($search_results) ? 'Résultats de Recherche' : 'Boîte de Réception'; ?>
                            <?php if (!empty($search_results)): ?>
                                <span style="font-size: 0.9rem; color: var(--text-secondary); margin-left: 0.75rem;">
                                    (<?php echo count($search_results); ?> résultat(s))
                                </span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    
                    <div class="email-list">
                        <?php if (!empty($search_results)): ?>
                            <?php foreach ($search_results as $email): ?>
                                <div class="email-item <?php echo !$email['read'] ? 'unread' : ''; ?>" onclick="viewEmail('<?php echo $email['id']; ?>')">
                                    <div class="email-checkbox">
                                        <input type="checkbox" onchange="event.stopPropagation()">
                                    </div>
                                    <button class="email-star <?php echo isset($email['starred']) && $email['starred'] ? 'starred' : ''; ?>" onclick="event.stopPropagation(); toggleStar('<?php echo $email['id']; ?>', this)">
                                        <i class="bi bi-star<?php echo isset($email['starred']) && $email['starred'] ? '-fill' : ''; ?>"></i>
                                    </button>
                                    <div class="email-avatar">
                                        <?php echo strtoupper(substr($email['from_name'] ?: $email['from'], 0, 2)); ?>
                                    </div>
                                    <div class="email-content-main">
                                        <div class="email-header-info">
                                            <div>
                                                <div class="email-sender"><?php echo htmlspecialchars($email['from_name'] ?: $email['from']); ?></div>
                                                <div class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                                            </div>
                                            <div class="email-date"><?php echo date('H:i', strtotime($email['date'])); ?></div>
                                        </div>
                                        <div class="email-preview"><?php echo htmlspecialchars($email['body_preview']); ?></div>
                                        <div class="email-meta">
                                            <?php if (!$email['read']): ?>
                                                <span class="badge badge-unread">Nouveau</span>
                                            <?php endif; ?>
                                            <?php if (isset($email['attachments']) && !empty($email['attachments'])): ?>
                                                <i class="bi bi-paperclip"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="email-actions">
                                        <button class="btn btn-icon btn-outline" onclick="event.stopPropagation(); archiveEmail('<?php echo $email['id']; ?>')">
                                            <i class="bi bi-archive"></i>
                                        </button>
                                        <button class="btn btn-icon btn-danger" onclick="event.stopPropagation(); confirmDelete('<?php echo $email['id']; ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!empty($recent_emails)): ?>
                            <?php foreach ($recent_emails as $email): ?>
                                <div class="email-item <?php echo !$email['read'] ? 'unread' : ''; ?>" onclick="viewEmail('<?php echo $email['id']; ?>')">
                                    <div class="email-checkbox">
                                        <input type="checkbox" onchange="event.stopPropagation()">
                                    </div>
                                    <button class="email-star <?php echo isset($email['starred']) && $email['starred'] ? 'starred' : ''; ?>" onclick="event.stopPropagation(); toggleStar('<?php echo $email['id']; ?>', this)">
                                        <i class="bi bi-star<?php echo isset($email['starred']) && $email['starred'] ? '-fill' : ''; ?>"></i>
                                    </button>
                                    <div class="email-avatar">
                                        <?php echo strtoupper(substr($email['from_name'] ?: $email['from'], 0, 2)); ?>
                                    </div>
                                    <div class="email-content-main">
                                        <div class="email-header-info">
                                            <div>
                                                <div class="email-sender"><?php echo htmlspecialchars($email['from_name'] ?: $email['from']); ?></div>
                                                <div class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                                            </div>
                                            <div class="email-date"><?php echo date('H:i', strtotime($email['date'])); ?></div>
                                        </div>
                                        <div class="email-preview"><?php echo htmlspecialchars($email['body_preview']); ?></div>
                                        <div class="email-meta">
                                            <?php if (!$email['read']): ?>
                                                <span class="badge badge-unread">Nouveau</span>
                                            <?php endif; ?>
                                            <?php if (isset($email['attachments']) && !empty($email['attachments'])): ?>
                                                <i class="bi bi-paperclip"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="email-actions">
                                        <button class="btn btn-icon btn-outline" onclick="event.stopPropagation(); archiveEmail('<?php echo $email['id']; ?>')">
                                            <i class="bi bi-archive"></i>
                                        </button>
                                        <button class="btn btn-icon btn-danger" onclick="event.stopPropagation(); confirmDelete('<?php echo $email['id']; ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-envelope-open"></i>
                                <p>Aucun email trouvé</p>
                                <button class="btn btn-primary" onclick="openComposeModal()">
                                    <i class="bi bi-pencil-square"></i>
                                    Écrire votre premier email
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="email-pagination">
                        <div class="pagination-info">
                            Affichage de 1 à <?php echo min(20, count($recent_emails)); ?> sur <?php echo $email_stats['total']; ?> emails
                        </div>
                        <div class="pagination-controls">
                            <button class="pagination-btn" disabled>
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button class="pagination-btn">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Compose Email Modal -->
    <div id="composeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="bi bi-pencil-square"></i>
                    Nouveau Message
                </h3>
                <button class="close-modal" onclick="closeModal('composeModal')">&times;</button>
            </div>
            <form id="composeForm" method="POST" onsubmit="return handleEmailSubmit(this)">
                <div class="modal-body">
                    <!-- Recipients -->
                    <div class="form-group">
                        <label class="form-label" for="emailTo">À</label>
                        <div class="user-search-container">
                            <input type="text" id="emailTo" name="to" class="form-control" 
                                   placeholder="Destinataire(s)" 
                                   oninput="searchUsers(this.value)"
                                   onfocus="showUserSearch()">
                            <div class="user-search-results" id="userSearchResults"></div>
                        </div>
                        <div class="recipient-chips" id="recipientChips"></div>
                    </div>
                    
                    <!-- CC and BCC -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="emailCc">Cc</label>
                            <input type="text" id="emailCc" name="cc" class="form-control" placeholder="Copie carbone">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="emailBcc">Cci</label>
                            <input type="text" id="emailBcc" name="bcc" class="form-control" placeholder="Copie carbone invisible">
                        </div>
                    </div>
                    
                    <!-- Subject -->
                    <div class="form-group">
                        <label class="form-label" for="emailSubject">Objet</label>
                        <input type="text" id="emailSubject" name="subject" class="form-control" required placeholder="Objet de l'email">
                    </div>
                    
                    <!-- Email Options -->
                    <div class="email-options">
                        <div class="email-priority-selector">
                            <div class="priority-option selected" data-priority="normal" onclick="setPriority(this, 'normal')">Normal</div>
                            <div class="priority-option" data-priority="high" onclick="setPriority(this, 'high')">Élevée</div>
                            <div class="priority-option" data-priority="low" onclick="setPriority(this, 'low')">Basse</div>
                        </div>
                        
                        <div class="email-format-selector">
                            <div class="format-option selected" data-format="plain" onclick="setFormat(this, 'plain')">Texte</div>
                            <div class="format-option" data-format="html" onclick="setFormat(this, 'html')">HTML</div>
                        </div>
                    </div>
                    
                    <!-- Message Body -->
                    <div class="form-group">
                        <label class="form-label" for="emailMessage">Message</label>
                        <div class="rich-text-editor" id="emailEditor" style="display: none;">
                            <div class="editor-toolbar">
                                <button type="button" class="editor-btn" onclick="formatText('bold')"><i class="bi bi-type-bold"></i></button>
                                <button type="button" class="editor-btn" onclick="formatText('italic')"><i class="bi bi-type-italic"></i></button>
                                <button type="button" class="editor-btn" onclick="formatText('underline')"><i class="bi bi-type-underline"></i></button>
                                <button type="button" class="editor-btn" onclick="formatText('strike')"><i class="bi bi-type-strikethrough"></i></button>
                                <div style="width: 1px; height: 20px; background: var(--border-color); margin: 0 0.5rem;"></div>
                                <button type="button" class="editor-btn" onclick="formatText('insertUnorderedList')"><i class="bi bi-list-ul"></i></button>
                                <button type="button" class="editor-btn" onclick="formatText('insertOrderedList')"><i class="bi bi-list-ol"></i></button>
                                <button type="button" class="editor-btn" onclick="formatText('outdent')"><i class="bi bi-text-indent-left"></i></button>
                                <button type="button" class="editor-btn" onclick="formatText('indent')"><i class="bi bi-text-indent-right"></i></button>
                                <div style="width: 1px; height: 20px; background: var(--border-color); margin: 0 0.5rem;"></div>
                                <button type="button" class="editor-btn" onclick="insertLink()"><i class="bi bi-link"></i></button>
                                <button type="button" class="editor-btn" onclick="insertImage()"><i class="bi bi-image"></i></button>
                            </div>
                            <div class="editor-content" contenteditable="true" id="htmlEditor"></div>
                        </div>
                        <textarea id="emailMessage" name="message" class="form-control email-body" required placeholder="Votre message..."></textarea>
                    </div>
                    
                    <!-- Email Templates -->
                    <div class="email-templates">
                        <label class="form-label">Modèles</label>
                        <select class="template-select" onchange="loadTemplate(this.value)">
                            <option value="">Sélectionner un modèle</option>
                            <option value="welcome">Email de bienvenue</option>
                            <option value="support">Réponse support</option>
                            <option value="notification">Notification</option>
                            <option value="marketing">Marketing</option>
                        </select>
                    </div>
                    
                    <!-- Signature -->
                    <div class="email-signature">
                        <strong>Signature</strong>
                        <p>Cordialement,<br>Équipe de support OBG</p>
                    </div>
                    
                    <!-- Attachments -->
                    <div class="form-group">
                        <label class="form-label">Pièces jointes</label>
                        <input type="file" id="emailAttachments" multiple class="form-control">
                        <div id="attachmentList" class="attachment-list" style="margin-top: 0.5rem;"></div>
                    </div>
                    
                    <!-- Advanced Options -->
                    <div class="email-scheduling">
                        <label class="form-label">Planification</label>
                        <div class="schedule-option">
                            <input type="radio" id="scheduleNow" name="schedule" value="now" checked onchange="toggleScheduleOptions()">
                            <label for="scheduleNow">Envoyer maintenant</label>
                        </div>
                        <div class="schedule-option">
                            <input type="radio" id="scheduleLater" name="schedule" value="later" onchange="toggleScheduleOptions()">
                            <label for="scheduleLater">Planifier l'envoi</label>
                        </div>
                        <div class="schedule-datetime" id="scheduleDateTime">
                            <input type="datetime-local" class="form-control" style="margin-top: 0.5rem;">
                        </div>
                    </div>
                    
                    <div class="email-tracking">
                        <label class="form-label">Suivi</label>
                        <div class="tracking-option">
                            <input type="checkbox" id="trackOpens" name="track_opens" checked>
                            <label for="trackOpens">Suivre les ouvertures</label>
                        </div>
                        <div class="tracking-option">
                            <input type="checkbox" id="trackClicks" name="track_clicks" checked>
                            <label for="trackClicks">Suivre les clics</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="saveDraft()">
                        <i class="bi bi-file-earmark"></i>
                        Brouillon
                    </button>
                    <button type="button" class="btn btn-outline" onclick="closeModal('composeModal')">
                        <i class="bi bi-x-circle"></i>
                        Annuler
                    </button>
                    <button type="submit" name="send_email" class="btn btn-primary" id="sendEmailBtn">
                        <span class="btn-text">
                            <i class="bi bi-send"></i>
                            Envoyer
                        </span>
                        <div class="loading-spinner" style="display: none;"></div>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Email Modal -->
    <div id="viewEmailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="bi bi-envelope-open"></i>
                    Détails de l'Email
                </h3>
                <button class="close-modal" onclick="closeModal('viewEmailModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="emailDetailContent">
                    <div class="email-detail-container">
                        <div class="loading-state">
                            <div class="loading-spinner-large"></div>
                            <p>Chargement du contenu de l'email...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="email-actions-bar">
                <button class="action-btn" onclick="replyToEmail()">
                    <i class="bi bi-reply"></i>
                    Répondre
                </button>
                <button class="action-btn" onclick="replyAllToEmail()">
                    <i class="bi bi-reply-all"></i>
                    Répondre à tous
                </button>
                <button class="action-btn" onclick="forwardEmail()">
                    <i class="bi bi-forward"></i>
                    Transférer
                </button>
                <button class="action-btn" onclick="markAsUnread()">
                    <i class="bi bi-envelope"></i>
                    Marquer non lu
                </button>
                <button class="action-btn" onclick="archiveCurrentEmail()">
                    <i class="bi bi-archive"></i>
                    Archiver
                </button>
                <button class="action-btn danger" onclick="deleteCurrentEmail()">
                    <i class="bi bi-trash"></i>
                    Supprimer
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal delete-confirm-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="bi bi-exclamation-triangle"></i>
                    Confirmer la suppression
                </h3>
                <button class="close-modal" onclick="closeModal('deleteConfirmModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="text-align: center; font-size: 1rem; margin-bottom: 0;">
                    Êtes-vous sûr de vouloir supprimer cet email ? Cette action est irréversible.
                </p>
                <div class="delete-actions">
                    <button class="btn btn-outline" onclick="closeModal('deleteConfirmModal')">
                        <i class="bi bi-x-circle"></i>
                        Annuler
                    </button>
                    <button class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash"></i>
                        Supprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification -->
    <div id="emailNotification" class="email-notification">
        <i class="bi bi-info-circle"></i>
        <span id="notificationMessage">Notification message</span>
        <button class="close" onclick="hideNotification()">&times;</button>
    </div>

    <script>
        // Global variables
        let currentEmailId = null;
        let selectedUsers = [];
        let currentFormat = 'plain';
        let currentPriority = 'normal';
        let selectedEmails = new Set();
        
        // User data for search
        const users = [
            <?php foreach ($all_users as $user): ?>
                {
                    name: '<?php echo htmlspecialchars($user['name']); ?>',
                    email: '<?php echo htmlspecialchars($user['email']); ?>',
                    initials: '<?php echo strtoupper(substr($user['name'], 0, 2)); ?>'
                },
            <?php endforeach; ?>
        ];

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        function openComposeModal() {
            // Reset form
            document.getElementById('composeForm').reset();
            document.getElementById('recipientChips').innerHTML = '';
            selectedUsers = [];
            currentFormat = 'plain';
            currentPriority = 'normal';
            
            // Show plain text editor by default
            document.getElementById('emailMessage').style.display = 'block';
            document.getElementById('emailEditor').style.display = 'none';
            
            openModal('composeModal');
            
            // Focus on subject field
            setTimeout(() => {
                document.querySelector('input[name="subject"]').focus();
            }, 300);
        }
        
        function selectUserForEmail(email, name) {
            if (!selectedUsers.some(user => user.email === email)) {
                selectedUsers.push({ email, name });
                updateRecipientChips();
            }
            closeModal('composeModal');
            openComposeModal();
        }
        
        function updateRecipientChips() {
            const chipsContainer = document.getElementById('recipientChips');
            chipsContainer.innerHTML = '';
            
            selectedUsers.forEach((user, index) => {
                const chip = document.createElement('div');
                chip.className = 'recipient-chip';
                chip.innerHTML = `
                    ${user.name} <${user.email}>
                    <span class="remove" onclick="removeRecipient(${index})">&times;</span>
                `;
                chipsContainer.appendChild(chip);
            });
            
            // Update the hidden input with all emails
            document.getElementById('emailTo').value = selectedUsers.map(user => user.email).join(', ');
        }
        
        function removeRecipient(index) {
            selectedUsers.splice(index, 1);
            updateRecipientChips();
        }
        
        function searchUsers(query) {
            const resultsContainer = document.getElementById('userSearchResults');
            
            if (query.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }
            
            const filteredUsers = users.filter(user => 
                user.name.toLowerCase().includes(query.toLowerCase()) || 
                user.email.toLowerCase().includes(query.toLowerCase())
            );
            
            resultsContainer.innerHTML = '';
            filteredUsers.forEach(user => {
                const result = document.createElement('div');
                result.className = 'user-search-result';
                result.innerHTML = `
                    <div class="user-avatar">${user.initials}</div>
                    <div>
                        <div class="user-name">${user.name}</div>
                        <div class="user-email">${user.email}</div>
                    </div>
                `;
                result.onclick = () => {
                    selectUserForEmail(user.email, user.name);
                    resultsContainer.style.display = 'none';
                };
                resultsContainer.appendChild(result);
            });
            
            resultsContainer.style.display = filteredUsers.length > 0 ? 'block' : 'none';
        }
        
        function showUserSearch() {
            const resultsContainer = document.getElementById('userSearchResults');
            const query = document.getElementById('emailTo').value;
            
            if (query.length >= 2) {
                searchUsers(query);
            }
        }
        
        function setPriority(element, priority) {
            document.querySelectorAll('.priority-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            currentPriority = priority;
        }
        
        function setFormat(element, format) {
            document.querySelectorAll('.format-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            currentFormat = format;
            
            if (format === 'html') {
                document.getElementById('emailMessage').style.display = 'none';
                document.getElementById('emailEditor').style.display = 'block';
            } else {
                document.getElementById('emailMessage').style.display = 'block';
                document.getElementById('emailEditor').style.display = 'none';
            }
        }
        
        function formatText(command, value = null) {
            document.execCommand(command, false, value);
            document.getElementById('htmlEditor').focus();
        }
        
        function insertLink() {
            const url = prompt('Entrez l\'URL:');
            if (url) {
                formatText('createLink', url);
            }
        }
        
        function insertImage() {
            const url = prompt('Entrez l\'URL de l\'image:');
            if (url) {
                formatText('insertImage', url);
            }
        }
        
        function loadTemplate(template) {
            const templates = {
                welcome: 'Bonjour,\n\nNous vous souhaitons la bienvenue dans notre plateforme.\n\nCordialement,\nÉquipe de support OBG',
                support: 'Bonjour,\n\nNous avons bien reçu votre demande de support et nous la traitons dans les plus brefs délais.\n\nCordialement,\nÉquipe de support OBG',
                notification: 'Notification importante\n\nVeuillez prendre connaissance des informations suivantes.\n\nCordialement,\nÉquipe de support OBG',
                marketing: 'Découvrez nos nouvelles fonctionnalités!\n\nNous sommes ravis de vous présenter nos dernières améliorations.\n\nCordialement,\nÉquipe de support OBG'
            };
            
            if (templates[template]) {
                if (currentFormat === 'html') {
                    document.getElementById('htmlEditor').innerHTML = templates[template].replace(/\n/g, '<br>');
                } else {
                    document.getElementById('emailMessage').value = templates[template];
                }
            }
        }
        
        function toggleScheduleOptions() {
            const scheduleLater = document.getElementById('scheduleLater').checked;
            document.getElementById('scheduleDateTime').style.display = scheduleLater ? 'block' : 'none';
        }
        
        function saveDraft() {
            showNotification('Brouillon sauvegardé avec succès', 'success');
            // In a real implementation, this would save to the database
        }
        
        function viewEmail(emailId) {
            currentEmailId = emailId;
            openModal('viewEmailModal');
            
            // Show loading state
            document.getElementById('emailDetailContent').innerHTML = `
                <div class="email-detail-container">
                    <div class="loading-state">
                        <div class="loading-spinner-large"></div>
                        <p>Chargement du contenu de l'email...</p>
                    </div>
                </div>
            `;
            
            // Load email details via AJAX
            fetch(`?view_email=true&email_id=${emailId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(email => {
                    const attachmentsHtml = formatAttachments(email.attachments);
                    const tagsHtml = email.read ? '' : '<span class="email-tag unread">Non lu</span>';
                    
                    // Utiliser html_body si disponible, sinon body
                    const emailBody = formatEmailBody(email.body, email.html_body);
                    
                    document.getElementById('emailDetailContent').innerHTML = `
                        <div class="email-detail-container">
                            <div class="email-detail-header">
                                <div class="email-detail-subject">${escapeHtml(email.subject)}</div>
                                <div class="email-detail-meta">
                                    <div class="sender-info">
                                        <div class="sender-avatar">
                                            ${getInitials(email.from_name || email.from)}
                                        </div>
                                        <div class="sender-main">
                                            <div class="sender-name">${escapeHtml(email.from_name || email.from)}</div>
                                            <div class="sender-email">${escapeHtml(email.from)}</div>
                                        </div>
                                        <div class="email-date">
                                            ${new Date(email.date).toLocaleString('fr-FR', {
                                                weekday: 'long',
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}
                                        </div>
                                    </div>
                                    <div class="meta-row">
                                        <div class="meta-item">
                                            <span class="meta-label">À:</span>
                                            <span class="meta-value">${formatRecipients(email.to)}</span>
                                        </div>
                                        ${email.cc && email.cc.length > 0 ? `
                                            <div class="meta-item">
                                                <span class="meta-label">Cc:</span>
                                                <span class="meta-value">${formatRecipients(email.cc)}</span>
                                            </div>
                                        ` : ''}
                                    </div>
                                    <div class="email-tags">
                                        ${tagsHtml}
                                        ${email.priority && email.priority !== 'normal' ? 
                                            `<span class="email-tag important">${escapeHtml(email.priority)}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="email-detail-body">
                                ${emailBody}
                                ${attachmentsHtml}
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    console.error('Error loading email:', error);
                    document.getElementById('emailDetailContent').innerHTML = `
                        <div class="email-detail-container">
                            <div class="error-state">
                                <i class="bi bi-exclamation-triangle"></i>
                                <h3>Erreur de chargement</h3>
                                <p>Une erreur s'est produite lors du chargement de l'email.</p>
                                <button class="btn btn-outline" onclick="viewEmail('${emailId}')" style="margin-top: 1rem;">
                                    <i class="bi bi-arrow-clockwise"></i>
                                    Réessayer
                                </button>
                            </div>
                        </div>
                    `;
                });
        }

        function getInitials(name) {
            if (!name) return '?';
            return name.split(' ')
                .map(part => part.charAt(0))
                .join('')
                .toUpperCase()
                .substring(0, 2);
        }

        function formatAttachments(attachments) {
            if (!attachments || attachments.length === 0) return '';
            
            const attachmentItems = attachments.map(attachment => `
                <div class="attachment-item">
                    <div class="attachment-icon">
                        <i class="bi bi-paperclip"></i>
                    </div>
                    <div class="attachment-info">
                        <div class="attachment-name">${escapeHtml(attachment.filename)}</div>
                        <div class="attachment-size">${formatFileSize(attachment.size)}</div>
                    </div>
                    <button class="download-btn" onclick="downloadAttachment(${attachment.part})">
                        <i class="bi bi-download"></i>
                        Télécharger
                    </button>
                </div>
            `).join('');
            
            return `
                <div class="email-attachments">
                    <div class="attachments-title">
                        <i class="bi bi-paperclip"></i>
                        Pièces jointes (${attachments.length})
                    </div>
                    <div class="attachment-list">
                        ${attachmentItems}
                    </div>
                </div>
            `;
        }
        
        function formatEmailBody(body, htmlBody) {
            // Priorité au HTML s'il existe
            if (htmlBody && htmlBody.trim().length > 0) {
                return `<div class="email-body-content html-content">${htmlBody}</div>`;
            }
            
            // Sinon formater le texte brut
            if (!body) return '<p style="color: #5f6368; font-style: italic;">Aucun contenu</p>';
            
            // Nettoyer le texte brut des en-têtes techniques
            let cleanBody = cleanRawEmailBody(body);
            
            let formatted = escapeHtml(cleanBody)
                .replace(/\n/g, '<br>')
                .replace(/\r/g, '')
                .replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')
                .replace(/(\b[\w\.-]+@[\w\.-]+\.\w{2,}\b)/g, '<a href="mailto:$1">$1</a>')
                .replace(/(\b\d{10}\b)/g, '<a href="tel:$1">$1</a>');
            
            return `<div class="email-body-content text-content">${formatted}</div>`;
        }

        function formatRecipients(recipients) {
            if (!recipients) return '';
            
            if (Array.isArray(recipients)) {
                return recipients.map(recipient => {
                    if (typeof recipient === 'object') {
                        return recipient.name ? `${escapeHtml(recipient.name)} <${escapeHtml(recipient.email)}>` : escapeHtml(recipient.email);
                    }
                    return escapeHtml(recipient);
                }).join(', ');
            }
            
            return escapeHtml(recipients);
        }

        function cleanRawEmailBody(body) {
            if (!body) return '';
            
            // Supprimer les en-têtes techniques et les boundaries
            let cleaned = body
                .replace(/--b1=[^\n]+\n/g, '') // Supprimer les boundaries
                .replace(/Content-Type:[^\n]+\n/g, '') // Supprimer les Content-Type
                .replace(/Content-Transfer-Encoding:[^\n]+\n/g, '') // Supprimer les encodings
                .replace(/charset=[^\n]+\n/g, '') // Supprimer les charsets
                .replace(/=[?][^?]+[?][QB][?][^?]+[?]=/g, '') // Décoder les encoded words
                .replace(/^\s*[-=_]+\s*$/gm, '') // Supprimer les lignes de séparation
                .replace(/\n{3,}/g, '\n\n'); // Réduire les multiples sauts de ligne
            
            // Extraire seulement le contenu après le dernier header
            const parts = cleaned.split(/\n\s*\n/);
            if (parts.length > 1) {
                // Prendre la dernière partie qui contient généralement le message
                return parts[parts.length - 1].trim();
            }
            
            return cleaned.trim();
        }
        
        function escapeHtml(unsafe) {
            if (unsafe == null) {
                return '';
            }
            
            switch (typeof unsafe) {
                case 'boolean':
                case 'number':
                    return String(unsafe);
                case 'string':
                    return unsafe
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");
                default:
                    return '';
            }
        }
        
        function formatFileSize(bytes) {
            if (!bytes) return '0 B';
            
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        function downloadAttachment(partId) {
            // In a real implementation, this would download the attachment
            alert('Téléchargement de la pièce jointe ' + partId);
        }
        
        function confirmDelete(emailId) {
            currentEmailId = emailId;
            openModal('deleteConfirmModal');
            
            // Set up delete confirmation
            document.getElementById('confirmDeleteBtn').onclick = function() {
                deleteEmail(emailId);
            };
        }
        
        function deleteEmail(emailId) {
            // Submit delete form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const emailIdInput = document.createElement('input');
            emailIdInput.type = 'hidden';
            emailIdInput.name = 'email_id';
            emailIdInput.value = emailId;
            
            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete_email';
            deleteInput.value = '1';
            
            form.appendChild(emailIdInput);
            form.appendChild(deleteInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteCurrentEmail() {
            if (currentEmailId) {
                closeModal('viewEmailModal');
                confirmDelete(currentEmailId);
            }
        }
        
        function archiveCurrentEmail() {
            if (currentEmailId) {
                // In a real implementation, this would archive the email
                showNotification('Email archivé avec succès', 'success');
                closeModal('viewEmailModal');
            }
        }
        
        function replyToEmail() {
            if (currentEmailId) {
                closeModal('viewEmailModal');
                openComposeModal();
                
                // Pre-fill subject with "Re: "
                const subjectInput = document.querySelector('input[name="subject"]');
                if (subjectInput && !subjectInput.value.startsWith('Re: ')) {
                    subjectInput.value = 'Re: ' + (subjectInput.value || '');
                }
            }
        }
        
        function replyAllToEmail() {
            if (currentEmailId) {
                closeModal('viewEmailModal');
                openComposeModal();
                
                // Pre-fill subject with "Re: "
                const subjectInput = document.querySelector('input[name="subject"]');
                if (subjectInput && !subjectInput.value.startsWith('Re: ')) {
                    subjectInput.value = 'Re: ' + (subjectInput.value || '');
                }
                
                // In a real implementation, this would also pre-fill CC with all recipients
            }
        }
        
        function forwardEmail() {
            if (currentEmailId) {
                closeModal('viewEmailModal');
                openComposeModal();
                
                // Pre-fill subject with "Fwd: "
                const subjectInput = document.querySelector('input[name="subject"]');
                if (subjectInput && !subjectInput.value.startsWith('Fwd: ')) {
                    subjectInput.value = 'Fwd: ' + (subjectInput.value || '');
                }
            }
        }
        
        function markAsUnread() {
            if (currentEmailId) {
                // In a real implementation, this would mark the email as unread via AJAX
                showNotification('Email marqué comme non lu', 'success');
                closeModal('viewEmailModal');
            }
        }
        
        function handleEmailSubmit(form) {
            const sendBtn = document.getElementById('sendEmailBtn');
            const btnText = sendBtn.querySelector('.btn-text');
            const spinner = sendBtn.querySelector('.loading-spinner');
            
            // Show loading state
            btnText.style.display = 'none';
            spinner.style.display = 'inline-block';
            sendBtn.disabled = true;
            sendBtn.classList.add('btn-loading');
            
            // Get HTML content if using HTML editor
            if (currentFormat === 'html') {
                const htmlContent = document.getElementById('htmlEditor').innerHTML;
                // In a real implementation, you would send this as HTML content
                console.log('HTML Content:', htmlContent);
            }
            
            // In real implementation, this would be the actual form submission
            // For now, we'll simulate a delay and then submit
            setTimeout(() => {
                form.submit();
            }, 1500);
            
            return true;
        }
        
        function toggleStar(emailId, element) {
            const icon = element.querySelector('i');
            const isStarred = icon.classList.contains('bi-star-fill');
            
            if (isStarred) {
                icon.classList.remove('bi-star-fill');
                icon.classList.add('bi-star');
                element.classList.remove('starred');
            } else {
                icon.classList.remove('bi-star');
                icon.classList.add('bi-star-fill');
                element.classList.add('starred');
            }
            
            // In a real implementation, this would update the database via AJAX
            showNotification(isStarred ? 'Email retiré des favoris' : 'Email ajouté aux favoris', 'success');
        }
        
        function archiveEmail(emailId) {
            // In a real implementation, this would archive the email via AJAX
            showNotification('Email archivé avec succès', 'success');
        }
        
        function setActiveFolder(element) {
            document.querySelectorAll('.email-folder').forEach(folder => folder.classList.remove('active'));
            element.classList.add('active');
            // In a real implementation, this would load emails for the selected folder
        }
        
        function toggleSelectAll(checkbox) {
            const emailCheckboxes = document.querySelectorAll('.email-checkbox input[type="checkbox"]');
            emailCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                if (checkbox.checked) {
                    selectedEmails.add(cb.closest('.email-item').dataset.emailId);
                } else {
                    selectedEmails.clear();
                }
            });
        }
        
        function refreshEmails() {
            // In a real implementation, this would refresh the email list
            showNotification('Emails actualisés', 'success');
        }
        
        function archiveSelected() {
            if (selectedEmails.size > 0) {
                // In a real implementation, this would archive selected emails
                showNotification(`${selectedEmails.size} email(s) archivé(s)`, 'success');
                selectedEmails.clear();
                document.querySelectorAll('.email-checkbox input[type="checkbox"]').forEach(cb => cb.checked = false);
            } else {
                showNotification('Veuillez sélectionner au moins un email', 'warning');
            }
        }
        
        function markAsSpam() {
            if (selectedEmails.size > 0) {
                // In a real implementation, this would mark selected emails as spam
                showNotification(`${selectedEmails.size} email(s) marqué(s) comme spam`, 'success');
                selectedEmails.clear();
                document.querySelectorAll('.email-checkbox input[type="checkbox"]').forEach(cb => cb.checked = false);
            } else {
                showNotification('Veuillez sélectionner au moins un email', 'warning');
            }
        }
        
        function deleteSelected() {
            if (selectedEmails.size > 0) {
                if (confirm(`Êtes-vous sûr de vouloir supprimer ${selectedEmails.size} email(s) ?`)) {
                    // In a real implementation, this would delete selected emails
                    showNotification(`${selectedEmails.size} email(s) supprimé(s)`, 'success');
                    selectedEmails.clear();
                    document.querySelectorAll('.email-checkbox input[type="checkbox"]').forEach(cb => cb.checked = false);
                }
            } else {
                showNotification('Veuillez sélectionner au moins un email', 'warning');
            }
        }
        
        function toggleEmailView() {
            // In a real implementation, this would toggle between list and card view
            showNotification('Affichage modifié', 'success');
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.getElementById('emailNotification');
            const messageElement = document.getElementById('notificationMessage');
            const icon = notification.querySelector('i');
            
            messageElement.textContent = message;
            notification.className = `email-notification ${type}`;
            
            // Update icon based on type
            icon.className = 'bi ' + (
                type === 'success' ? 'bi-check-circle' :
                type === 'error' ? 'bi-exclamation-circle' :
                type === 'warning' ? 'bi-exclamation-triangle' :
                'bi-info-circle'
            );
            
            notification.classList.add('show');
            
            setTimeout(() => {
                hideNotification();
            }, 5000);
        }
        
        function hideNotification() {
            document.getElementById('emailNotification').classList.remove('show');
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
            
            // Hide user search results when clicking outside
            if (!event.target.closest('.user-search-container')) {
                document.getElementById('userSearchResults').style.display = 'none';
            }
        });
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
                document.body.style.overflow = 'auto';
                document.getElementById('userSearchResults').style.display = 'none';
            }
        });
        
        // Auto-resize textarea
        document.addEventListener('input', function(event) {
            if (event.target.classList.contains('email-body')) {
                event.target.style.height = 'auto';
                event.target.style.height = (event.target.scrollHeight) + 'px';
            }
        });
        
        // Handle file attachments
        document.getElementById('emailAttachments').addEventListener('change', function(event) {
            const files = event.target.files;
            const attachmentList = document.getElementById('attachmentList');
            attachmentList.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const attachmentItem = document.createElement('div');
                attachmentItem.className = 'attachment-item';
                attachmentItem.innerHTML = `
                    <div class="attachment-icon">
                        <i class="bi bi-paperclip"></i>
                    </div>
                    <div class="attachment-info">
                        <div class="attachment-name">${file.name}</div>
                        <div class="attachment-size">${formatFileSize(file.size)}</div>
                    </div>
                    <button class="download-btn" onclick="removeAttachment(this)">
                        <i class="bi bi-x"></i>
                        Supprimer
                    </button>
                `;
                attachmentList.appendChild(attachmentItem);
            }
        });
        
        function removeAttachment(button) {
            button.closest('.attachment-item').remove();
        }
        
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to email items
            const emailItems = document.querySelectorAll('.email-item');
            emailItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(4px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
            
            // Add typing effect to search placeholder
            const searchInput = document.querySelector('input[name="search_query"]');
            if (searchInput) {
                const placeholders = [
                    "🔍 Rechercher dans les emails...",
                    "🔍 Rechercher par sujet...", 
                    "🔍 Rechercher par expéditeur...",
                    "🔍 Rechercher dans le contenu..."
                ];
                let currentIndex = 0;
                
                setInterval(() => {
                    searchInput.placeholder = placeholders[currentIndex];
                    currentIndex = (currentIndex + 1) % placeholders.length;
                }, 3000);
            }
            
            // Initialize email checkboxes
            document.querySelectorAll('.email-checkbox input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const emailItem = this.closest('.email-item');
                    const emailId = emailItem.dataset.emailId;
                    
                    if (this.checked) {
                        selectedEmails.add(emailId);
                    } else {
                        selectedEmails.delete(emailId);
                    }
                });
            });
        });
    </script>
</body>
</html>