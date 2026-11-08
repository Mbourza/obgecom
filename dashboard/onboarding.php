<?php 
if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

$currentPage = '';

$db = DB::getInstance();
$user = $db->getThisQuery("SELECT id, `name`, `role`, is_verified, email FROM users WHERE email = ?", [$_SESSION['user']['username']]);

if (!$user || empty($user[0]['id'])) {
    logout(); 
}

$currentUserId = $user[0]['id'];
$onboarding = new Onboarding($currentUserId);
if ($onboarding->isOnboardingCompleted() && $currentPage === 'onboarding') {
    Redirect::to('./settings');
}

if (isset($_GET['logout'])) {
    logout();
}

function logout() {
    $user = new User();
    $user->logout();
    Redirect::to('../lg');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration initiale | OBG</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }
        
        .onboarding-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background particles */
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0) rotate(0deg); }
            33% { transform: translateY(-100px) translateX(50px) rotate(120deg); }
            66% { transform: translateY(-50px) translateX(-50px) rotate(240deg); }
        }
        
        .onboarding-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
        }
        
        .onboarding-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 0px;
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 1200px;
            overflow: hidden;
            animation: slideUp 0.8s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .onboarding-header {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
            padding: 50px 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .onboarding-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb, #667eea);
            background-size: 200% 100%;
            animation: gradientMove 3s linear infinite;
        }
        
        @keyframes gradientMove {
            0% { background-position: 0% 0%; }
            100% { background-position: 200% 0%; }
        }
        
        .header-content {
            width: 100%;
            max-width: 800px;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .user-details {
            text-align: left;
        }
        
        .user-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .user-email {
            font-size: 12px;
            color: #6c757d;
        }
        
        .logout-btn {
            background: rgba(108, 117, 125, 0.1);
            border: 1px solid rgba(108, 117, 125, 0.2);
            color: #6c757d;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .logout-btn:hover {
            background: rgba(108, 117, 125, 0.2);
            color: #495057;
            transform: translateY(-2px);
        }
        
        .onboarding-header h1 {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
            animation: fadeIn 0.8s ease 0.2s both;
        }
        
        .onboarding-header p {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 30px;
            animation: fadeIn 0.8s ease 0.4s both;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .onboarding-progress {
            max-width: 600px;
            margin: 0 auto;
            animation: fadeIn 0.8s ease 0.6s both;
        }
        
        .progress-bar {
            height: 12px;
            background: rgba(233, 236, 239, 0.6);
            border-radius: 20px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            border-radius: 20px;
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            font-size: 14px;
            font-weight: 500;
            color: #6c757d;
        }
        
        .onboarding-content {
            padding: 60px 40px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 50px;
            gap: 20px;
            position: relative;
        }
        
        .step-connector {
            flex: 1;
            max-width: 150px;
            height: 3px;
            background: #e9ecef;
            position: relative;
            overflow: hidden;
        }
        
        .step-connector.active::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            animation: slideIn 0.8s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        
        .step-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 28px;
            background: white;
            border: 4px solid #e9ecef;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
        }
        
        .step-circle::before {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            opacity: 0;
            transition: opacity 0.5s ease;
            z-index: -1;
        }
        
        .step.active .step-circle {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4); }
            50% { box-shadow: 0 10px 40px rgba(102, 126, 234, 0.6); }
        }
        
        .step.active .step-circle::before {
            opacity: 0.2;
            animation: ripple 1.5s infinite;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(1);
                opacity: 0.2;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }
        
        .step.completed .step-circle {
            border-color: #28a745;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            animation: checkmark 0.6s ease;
        }
        
        @keyframes checkmark {
            0%, 50% { transform: scale(0.8) rotate(-5deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        
        .step-label {
            font-size: 16px;
            font-weight: 600;
            color: #6c757d;
            transition: all 0.3s ease;
        }
        
        .step.active .step-label {
            color: #667eea;
            transform: translateY(-2px);
        }
        
        .step-content {
            text-align: center;
            animation: contentFade 0.8s ease;
        }
        
        @keyframes contentFade {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .step-icon {
            font-size: 80px;
            margin-bottom: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: iconBounce 0.8s ease;
        }
        
        @keyframes iconBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .step-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #2c3e50;
        }
        
        .step-description {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 40px;
            line-height: 1.7;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .platform-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin: 40px 0;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .platform-card {
            background: white;
            border: 3px solid #e9ecef;
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .platform-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .platform-card:hover {
            border-color: #667eea;
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
        }
        
        .platform-card:hover::before {
            opacity: 1;
        }
        
        .platform-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2);
        }
        
        .platform-card.selected::after {
            content: '✓';
            position: absolute;
            top: 16px;
            right: 16px;
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            animation: checkPop 0.4s ease;
        }
        
        @keyframes checkPop {
            0% { transform: scale(0) rotate(-180deg); }
            50% { transform: scale(1.2) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        
        .platform-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-weight: 700;
            font-size: 28px;
            color: white;
            transition: transform 0.4s ease;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .platform-card:hover .platform-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .platform-icon.shopify { 
            background: linear-gradient(135deg, #95bf47 0%, #7ea63f 100%);
        }

        .platform-icon.woocommerce { 
            background: linear-gradient(135deg, #96588a 0%, #7e4a74 100%);
        }

        .platform-icon.youcan { 
            background: linear-gradient(135deg, #1a73e8 0%, #1557b0 100%);
        }

        .platform-icon.add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .platform-name {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .platform-description {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.5;
        }
        
        .connection-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 0px;
            padding: 40px;
            margin-top: 30px;
            max-width: 900px;
            margin-left: auto;
            margin-right: auto;
            display: none;
            border: 2px solid #e9ecef;
        }
        
        .connection-form.active {
            display: block;
            animation: formSlide 0.5s ease;
        }
        
        @keyframes formSlide {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .connection-form h4 {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 24px;
            text-align: left;
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
            display: block;
            font-size: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .btn-primary, .oauth-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before, .oauth-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn-primary:hover::before, .oauth-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .btn-primary:hover, .oauth-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:active, .oauth-btn:active {
            transform: translateY(-1px);
        }
        
        .btn-skip {
            background: #6c757d;
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-skip:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(108, 117, 125, 0.3);
        }
        
        .onboarding-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .stores-list {
            margin: 40px 0;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .stores-list h4 {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .store-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            animation: storeSlide 0.5s ease;
        }
        
        @keyframes storeSlide {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .store-item:hover {
            border-color: #667eea;
            transform: translateX(4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }
        
        .store-info h4 {
            margin: 0 0 6px 0;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
            text-align: left;
        }
        
        .store-info p {
            margin: 0;
            color: #6c757d;
            font-size: 14px;
        }
        
        .store-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .status-connected {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .status-disconnected {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .completed-message {
            text-align: center;
            padding: 80px 40px;
        }
        
        .success-icon {
            font-size: 120px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 30px;
            animation: successPop 0.8s ease;
        }
        
        @keyframes successPop {
            0% { transform: scale(0) rotate(-180deg); }
            60% { transform: scale(1.2) rotate(10deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        
        .modal-overlay.active .modal {
            transform: scale(1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c757d;
            transition: color 0.3s ease;
        }
        
        .modal-close:hover {
            color: #495057;
        }
        
        /* Shipping Company Modal Styles */
        #shippingModal .modal {
            max-width: 600px;
        }
        
        .supported-carriers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        
        .supported-carrier {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .supported-carrier:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        
        .supported-carrier.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
        }
        
        .supported-carrier-logo {
            width: 40px;
            height: 40px;
            margin: 0 auto 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .supported-carrier-logo svg {
            width: 24px;
            height: 24px;
        }
        
        .supported-carrier-name {
            font-size: 12px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .required-asterisk {
            color: #dc3545;
        }
        
        .text-muted {
            color: #6c757d;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .onboarding-header h1 {
                font-size: 32px;
            }
            
            .onboarding-header p {
                font-size: 16px;
            }
            
            .step-circle {
                width: 60px;
                height: 60px;
                font-size: 20px;
            }
            
            .step-connector {
                max-width: 50px;
            }
            
            .step-title {
                font-size: 28px;
            }
            
            .step-description {
                font-size: 16px;
            }
            
            .platform-grid {
                grid-template-columns: 1fr;
            }
            
            .onboarding-content {
                padding: 40px 20px;
            }
            
            .connection-form {
                padding: 30px 20px;
            }
            
            .header-top {
                flex-direction: column;
                gap: 15px;
                align-items: center;
            }
            
            .user-info {
                width: 100%;
                justify-content: center;
            }
            
            .supported-carriers-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .store-item {
                flex-direction: column !important;
                align-items: flex-start;
                overflow: hidden;
            }
        }
    </style>

</head>
<body>

    <div class="onboarding-container" id="onboardingContainer">
        <div class="onboarding-wrapper">
            <div class="onboarding-card">
                <div class="onboarding-header">
                    <div class="header-content">
                        <div class="header-top">
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user[0]['name'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <div class="user-name"><?php echo htmlspecialchars($user[0]['name']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($user[0]['email']); ?></div>
                                </div>
                            </div>
                            <button class="logout-btn" id="logoutBtn">
                                <i class="bi bi-box-arrow-right"></i>
                                Déconnexion
                            </button>
                        </div>
                        
                        <h1>Bienvenue sur OBGECOM !</h1>
                        <p>Connectez vos boutiques et transporteurs pour commencer</p>
                        
                        <div class="onboarding-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                            </div>
                            <div class="progress-text">
                                <span>Progression</span>
                                <span id="progressText">0 sur 2 terminés</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="onboarding-content">
                    <div class="step-indicator">
                        <div class="step active" id="step1">
                            <div class="step-circle">1</div>
                            <span class="step-label">Boutiques</span>
                        </div>
                        
                        <div class="step-connector" id="connector1"></div>
                        
                        <div class="step" id="step2">
                            <div class="step-circle">2</div>
                            <span class="step-label">Transporteurs</span>
                        </div>
                    </div>
                    
                    <!-- Store Connection Step -->
                    <div class="step-content" id="storeStep">
                        <div class="step-icon">
                            <i class="bi bi-shop"></i>
                        </div>
                        <h2 class="step-title">Connectez vos boutiques</h2>
                        <p class="step-description">
                            Synchronisez vos commandes en connectant vos boutiques e-commerce. 
                            Nous supportons les principales plateformes du marché.
                        </p>
                        
                        <div class="platform-grid">
                            <div class="platform-card" data-platform="shopify">
                                <div class="platform-icon shopify">S</div>
                                <div class="platform-name">Shopify</div>
                                <div class="platform-description">
                                    Synchronisation automatique via OAuth
                                </div>
                            </div>
                            
                            <div class="platform-card" data-platform="woocommerce">
                                <div class="platform-icon woocommerce">WC</div>
                                <div class="platform-name">WooCommerce</div>
                                <div class="platform-description">
                                    Connexion via clés API
                                </div>
                            </div>
                            
                            <div class="platform-card" data-platform="youcan">
                                <div class="platform-icon youcan">YC</div>
                                <div class="platform-name">YouCan</div>
                                <div class="platform-description">
                                    Synchronisation automatique via OAuth
                                </div>
                            </div>
                        </div>
                        
                        <!-- Connection Forms -->
                        <div id="shopify-form" class="connection-form">
                            <h4>Connexion à Shopify</h4>
                            <div class="form-group">
                                <label class="form-label">Nom de la boutique</label>
                                <input type="text" class="form-control" placeholder="Ma Boutique Shopify" id="shopify-name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Domaine Shopify</label>
                                <input type="text" class="form-control" placeholder="ex: ma-boutique.myshopify.com" id="shopify-domain">
                            </div>
                            <button class="oauth-btn" onclick="connectStore('shopify')">
                                <i class="bi bi-link"></i>
                                Se connecter à Shopify
                            </button>
                        </div>
                        
                        <div id="woocommerce-form" class="connection-form">
                            <h4>Connexion à WooCommerce</h4>
                            <div class="form-group">
                                <label class="form-label">Nom de la boutique</label>
                                <input type="text" class="form-control" placeholder="Ma Boutique WooCommerce" id="woocommerce-name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">URL de la boutique</label>
                                <input type="url" class="form-control" placeholder="https://monsite.com" id="woocommerce-url">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Consumer Key</label>
                                <input type="text" class="form-control" placeholder="ck_xxxxxxxxxxxxxx" id="woocommerce-key">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Consumer Secret</label>
                                <input type="password" class="form-control" placeholder="cs_xxxxxxxxxxxxxx" id="woocommerce-secret">
                            </div>
                            <button class="btn-primary" onclick="connectStore('woocommerce')">
                                <i class="bi bi-plug"></i>
                                Connecter la boutique
                            </button>
                        </div>
                        
                        <div id="youcan-form" class="connection-form">
                            <h4>Connexion à YouCan</h4>
                            <div class="form-group">
                                <label class="form-label">Nom de la boutique</label>
                                <input type="text" class="form-control" placeholder="Ma Boutique YouCan" id="youcan-name">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Domaine YouCan</label>
                                <input type="text" class="form-control" placeholder="monsite.youcanshop.com" id="youcan-domain">
                            </div>
                            <button class="oauth-btn" onclick="connectStore('youcan')">
                                <i class="bi bi-link"></i>
                                Se connecter à YouCan
                            </button>
                        </div>
                        
                        <div class="stores-list" id="storesList" style="display: none;">
                            <h4>Boutiques connectées</h4>
                            <div id="connectedStores"></div>
                        </div>
                        
                        <div class="onboarding-actions" id="storeActions" style="display: none;">
                            <button class="btn-primary" onclick="goToShipping()">
                                Continuer vers les transporteurs
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Shipping Setup Step -->
                    <div class="step-content" id="shippingStep" style="display: none;">
                        <div class="step-icon">
                            <i class="bi bi-truck"></i>
                        </div>
                        <h2 class="step-title">Configurez vos transporteurs</h2>
                        <p class="step-description">
                            Ajoutez vos transporteurs pour expédier vos commandes automatiquement.
                        </p>
                        
                        <div class="platform-grid" style="max-width: 600px; margin: 40px auto;">
                            <div class="platform-card" onclick="openShippingModal()">
                                <div class="platform-icon add">
                                    <i class="bi bi-plus-lg"></i>
                                </div>
                                <div class="platform-name">Ajouter un transporteur</div>
                                <div class="platform-description">
                                    Configurez un nouveau transporteur
                                </div>
                            </div>
                        </div>
                        
                        <div class="stores-list" id="shippersList" style="display: none;">
                            <h4>Transporteurs configurés</h4>
                            <div id="connectedShippers"></div>
                        </div>
                        
                        <div class="onboarding-actions">
                            <button class="btn-skip" onclick="skipShipping()">
                                Configurer plus tard
                            </button>
                            <button class="btn-primary" id="completeShippingBtn" style="display: none;" onclick="completeOnboarding()">
                                Terminer la configuration
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Onboarding Completed -->
                    <div class="completed-message" id="completedStep" style="display: none;">
                        <div class="success-icon">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <h2 class="step-title">Configuration terminée !</h2>
                        <p class="step-description">
                            Félicitations ! Votre compte est maintenant configuré et prêt à synchroniser vos commandes.
                        </p>
                        <div class="onboarding-actions">
                            <button class="btn-primary" onclick="goToDashboard()">
                                Accéder au tableau de bord
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Confirmation de déconnexion</h3>
                <button class="modal-close" id="closeLogoutModal">&times;</button>
            </div>
            <p>Êtes-vous sûr de vouloir vous déconnecter ?</p>
            <div class="onboarding-actions" style="margin-top: 30px;">
                <button class="btn-skip" id="cancelLogout">Annuler</button>
                <button class="btn-primary" id="confirmLogout">Se déconnecter</button>
            </div>
        </div>
    </div>

    <style>
        /* Auth mode toggle */
        .auth-mode-toggle {
            display: flex;
            gap: 8px;
            background: #f1f3f9;
            padding: 6px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .auth-mode-toggle button {
            flex: 1;
            padding: 12px 16px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .auth-mode-toggle button.active {
            background: white;
            color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }

        .auth-mode-toggle button:hover:not(.active) {
            color: #495057;
        }

        /* Hide API-only / Auth-only groups */
        .auth-section { display: none; }
        .auth-section.active { display: block; }
    </style>

    <!-- Shipping Company Modal -->
    <div class="modal-overlay" id="shippingModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter un transporteur</h3>
                <button class="modal-close" onclick="closeShippingModal()">&times;</button>
            </div>

            <div class="modal-body">
                <form id="company-form">

                    <!-- Auth mode toggle -->
                    <div class="auth-mode-toggle">
                        <button type="button" id="mode-api" class="active" onclick="setAuthMode('api')">
                            <i class="bi bi-key"></i> Clé API
                        </button>
                        <button type="button" id="mode-auth" onclick="setAuthMode('auth')">
                            <i class="bi bi-person-lock"></i> Identifiants (Email/Mot de passe)
                        </button>
                    </div>

                    <!-- Hidden field storing the selected mode -->
                    <input type="hidden" id="auth-mode" name="auth_mode" value="api">

                    <div class="form-group">
                        <label for="company-select">Transporteur <span class="required-asterisk">*</span></label>
                        <select id="company-select" name="company-select" required onchange="handleCarrierChange()" class="form-control">
                            <option value="">Sélectionnez un transporteur</option>
                            <option value="ozonexpress">OzonExpress</option>
                            <option value="sendit">Sendit</option>
                            <option value="chronopost">Chronopost</option>
                            <option value="forcelog">Forcelog</option>
                            <option value="cathedis">Cathedis</option>
                            <option value="power">Power Delivery</option>
                            <option value="ortalog">Ortalog</option>
                            <option value="custom">Autre transporteur</option>
                        </select>
                    </div>

                    <div class="form-group" id="custom-name-group" style="display: none;">
                        <label for="custom-company-name">Nom personnalisé <span class="required-asterisk">*</span></label>
                        <input type="text" id="custom-company-name" name="custom-company-name" placeholder="Entrez le nom du transporteur" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="display-name">Nom d'affichage (optionnel)</label>
                        <input type="text" id="display-name" name="display-name" placeholder="Laissez vide pour utiliser le nom par défaut" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Transporteurs supportés</label>
                        <div class="supported-carriers-grid">
                            <div class="supported-carrier" data-carrier-id="ozonexpress">
                                <div class="supported-carrier-logo">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/><path d="M12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/></svg>
                                </div>
                                <div class="supported-carrier-name">OzonExpress</div>
                            </div>
                            <div class="supported-carrier" data-carrier-id="sendit">
                                <div class="supported-carrier-logo">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm13.5-9l1.96 2.5H17V9h2.5zm-1.5 9c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/></svg>
                                </div>
                                <div class="supported-carrier-name">Sendit</div>
                            </div>
                            <div class="supported-carrier" data-carrier-id="chronopost">
                                <div class="supported-carrier-logo">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/><path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                                </div>
                                <div class="supported-carrier-name">Chronopost</div>
                            </div>
                            <div class="supported-carrier" data-carrier-id="forcelog">
                                <div class="supported-carrier-logo">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M13 10h-2V8h2v2zm0-4h-2V1h2v5zM7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2zm-8.9-5h7.45c.75 0 1.41-.41 1.75-1.03l3.86-7.01L19.42 4l-3.87 7H8.53L4.27 2H1v2h2l3.6 7.59-1.35 2.44C4.52 15.37 5.48 17 7 17h12v-2H7l1.1-2z"/></svg>
                                </div>
                                <div class="supported-carrier-name">Forcelog</div>
                            </div>
                            <div class="supported-carrier" data-carrier-id="cathedis">
                                <div class="supported-carrier-logo">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19.5 20.5v-17A1.5 1.5 0 0018 2H6a1.5 1.5 0 00-1.5 1.5v17A1.5 1.5 0 006 22h12a1.5 1.5 0 001.5-1.5zM18 20H6V4h12v16z"/><path d="M8 7h8v2H8V7zm0 4h8v2H8v-2zm0 4h5v2H8v-2z"/></svg>
                                </div>
                                <div class="supported-carrier-name">Cathedis</div>
                            </div>
                            <div class="supported-carrier" data-carrier-id="power">
                                <div class="supported-carrier-logo">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M7 2v11h3v9l7-12h-4l4-8z"/></svg>
                                </div>
                                <div class="supported-carrier-name">Power Delivery</div>
                            </div>
                            <div class="supported-carrier" data-carrier-id="ortalog">
                                <div class="supported-carrier-logo">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2 4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm0 2.18 6 2.25v4.66c0 3.98-2.55 7.7-6 8.83-3.45-1.13-6-4.85-6-8.83V6.43l6-2.25z"/><path d="M11 7h2v5h-2zm0 6h2v2h-2z"/></svg>
                                </div>
                                <div class="supported-carrier-name">Ortalog</div>
                            </div>
                        </div>
                    </div>

                    <!-- ============ API MODE FIELDS ============ -->
                    <div id="api-fields" class="auth-section active">
                        <div class="form-group">
                            <label for="company-api-url">URL de l'API <span class="required-asterisk">*</span></label>
                            <input type="url" id="company-api-url" name="api-url" placeholder="https://api.transporteur.com/v1" class="form-control">
                            <small class="text-muted">URL de base de l'API du transporteur</small>
                        </div>

                        <div class="form-group">
                            <label for="company-api-key">Clé API</label>
                            <input type="password" id="company-api-key" name="api-key" placeholder="Votre clé API" class="form-control">
                            <small class="text-muted">Optionnel - peut être configuré plus tard</small>
                        </div>

                        <div class="form-group">
                            <label for="api-secret">Secret API</label>
                            <input type="password" id="api-secret" name="api-secret" placeholder="Votre secret API" class="form-control">
                        </div>
                    </div>

                    <!-- ============ AUTH MODE FIELDS ============ -->
                    <div id="auth-fields" class="auth-section">
                        <div class="form-group">
                            <label for="company-auth-url">URL du portail <span class="required-asterisk">*</span></label>
                            <input type="url" id="company-auth-url" name="auth-url" placeholder="https://ortalog.ma/login" class="form-control">
                            <small class="text-muted">URL de connexion du transporteur</small>
                        </div>

                        <div class="form-group">
                            <label for="company-auth-username">Email ou nom d'utilisateur <span class="required-asterisk">*</span></label>
                            <input type="text" id="company-auth-username" name="auth-username" placeholder="ex: contact@maboutique.com" class="form-control" autocomplete="username">
                        </div>

                        <div class="form-group">
                            <label for="company-auth-password">Mot de passe <span class="required-asterisk">*</span></label>
                            <input type="password" id="company-auth-password" name="auth-password" placeholder="Votre mot de passe" class="form-control" autocomplete="current-password">
                        </div>

                        <div class="form-group">
                            <label for="company-auth-notes">Notes (optionnel)</label>
                            <input type="text" id="company-auth-notes" name="auth-notes" placeholder="ex: compte principal" class="form-control">
                        </div>
                    </div>

                </form>
            </div>

            <div class="modal-footer" style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px;">
                <button type="button" class="btn-skip" onclick="closeShippingModal()">Annuler</button>
                <button type="button" class="btn-primary" id="btn_complivraison" onclick="saveShippingCompany()">Enregistrer</button>
            </div>
        </div>
    </div>

    <script>
        // State management
        let currentStep = 1;
        let connectedStores = [];
        let connectedShippers = [];
        let selectedPlatform = null;
        let userLimits = { current: 0, max: 5 };

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            loadUserLimits();
            updateProgress();
            
            // Check if user already has stores
            checkExistingStores();
            
            // Setup logout functionality
            setupLogout();
            
            // Setup shipping modal functionality
            setupShippingModal();
        });

        // Setup logout functionality
        function setupLogout() {
            const logoutBtn = document.getElementById('logoutBtn');
            const logoutModal = document.getElementById('logoutModal');
            const closeLogoutModal = document.getElementById('closeLogoutModal');
            const cancelLogout = document.getElementById('cancelLogout');
            const confirmLogout = document.getElementById('confirmLogout');
            
            logoutBtn.addEventListener('click', function() {
                logoutModal.classList.add('active');
            });
            
            closeLogoutModal.addEventListener('click', function() {
                logoutModal.classList.remove('active');
            });
            
            cancelLogout.addEventListener('click', function() {
                logoutModal.classList.remove('active');
            });
            
            confirmLogout.addEventListener('click', function() {
                // Call PHP logout function
                window.location.href = '?logout';
            });
            
            // Close modal when clicking outside
            logoutModal.addEventListener('click', function(e) {
                if (e.target === logoutModal) {
                    logoutModal.classList.remove('active');
                }
            });
        }

        // Setup shipping modal functionality
        function setupShippingModal() {
            const shippingModal = document.getElementById('shippingModal');
            const companyForm = document.getElementById('company-form');
            const supportedCarriers = document.querySelectorAll('.supported-carrier');
            
            // Handle supported carrier selection
            supportedCarriers.forEach(carrier => {
                carrier.addEventListener('click', function() {
                    // Remove selection from all carriers
                    supportedCarriers.forEach(c => c.classList.remove('selected'));
                    
                    // Add selection to clicked carrier
                    this.classList.add('selected');
                    
                    // Update the select dropdown
                    const carrierId = this.dataset.carrierId;
                    document.getElementById('company-select').value = carrierId;
                    handleCarrierChange();
                    
                    // Auto-fill API URL for known carriers
                    autoFillApiUrl(carrierId);
                });
            });
            
            // Handle form submission
            companyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                saveShippingCompany();
            });
            
            // Close modal when clicking outside
            shippingModal.addEventListener('click', function(e) {
                if (e.target === shippingModal) {
                    closeShippingModal();
                }
            });
        }

        // Open shipping modal
        function openShippingModal() {
            const shippingModal = document.getElementById('shippingModal');
            shippingModal.classList.add('active');

            document.getElementById('company-form').reset();
            document.querySelectorAll('.supported-carrier').forEach(c => c.classList.remove('selected'));
            document.getElementById('custom-name-group').style.display = 'none';

            // Reset to API mode by default
            setAuthMode('api');
        }

        // Close shipping modal
        function closeShippingModal() {
            const shippingModal = document.getElementById('shippingModal');
            shippingModal.classList.remove('active');
        }

        // Handle carrier selection change
        function handleCarrierChange() {
            const selectedCarrier = document.getElementById('company-select').value;
            const customNameGroup = document.getElementById('custom-name-group');

            // Show/hide custom name
            customNameGroup.style.display = selectedCarrier === 'custom' ? 'block' : 'none';

            // Highlight the matching supported-carrier card
            document.querySelectorAll('.supported-carrier').forEach(carrier => {
                carrier.classList.toggle('selected', carrier.dataset.carrierId === selectedCarrier);
            });

            // Auto-switch mode for known auth-only carriers
            const authOnlyCarriers = ['ortalog'];
            if (authOnlyCarriers.includes(selectedCarrier)) {
                setAuthMode('auth');
            }

            // Auto-fill the URL for the current mode
            autoFillApiUrl(selectedCarrier);
        }

        // Auto-fill API URL for known carriers
        function autoFillApiUrl(carrierId) {
            const apiUrls = {
                'ozonexpress': 'https://api.ozonexpress.com/v1',
                'sendit': 'https://api.sendit.com/v1',
                'chronopost': 'https://api.chronopost.com/v1',
                'forcelog': 'https://api.forcelog.com/v1',
                'cathedis': 'https://api.cathedis.com/v1',
                'power': 'https://api.powerdelivery.com/v1'
            };
            
            if (apiUrls[carrierId]) {
                document.getElementById('company-api-url').value = apiUrls[carrierId];
            }
        }

        // Save shipping company
        async function saveShippingCompany() {
            const form = document.getElementById('company-form');
            const submitBtn = document.getElementById('btn_complivraison');

            if (!submitBtn) {
                console.error('Submit button not found');
                return;
            }

            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Enregistrement...';
            submitBtn.disabled = true;

            try {
                const mode           = document.getElementById('auth-mode').value; // 'api' | 'auth'
                const selectedCarrier = document.getElementById('company-select')?.value?.trim() || '';
                const customName      = document.getElementById('custom-company-name')?.value?.trim() || '';
                const displayName     = document.getElementById('display-name')?.value?.trim() || '';

                // --- Common validation ---
                if (!selectedCarrier) throw new Error('Veuillez sélectionner un transporteur');
                if (selectedCarrier === 'custom' && !customName) {
                    throw new Error('Veuillez saisir un nom pour le transporteur personnalisé');
                }

                // --- Mode-specific values + validation ---
                let apiUrl = '', apiKey = '', apiSecret = '';
                let authUrl = '', authUsername = '', authPassword = '', authNotes = '';

                if (mode === 'auth') {
                    authUrl      = document.getElementById('company-auth-url')?.value?.trim() || '';
                    authUsername = document.getElementById('company-auth-username')?.value?.trim() || '';
                    authPassword = document.getElementById('company-auth-password')?.value || '';
                    authNotes    = document.getElementById('company-auth-notes')?.value?.trim() || '';

                    if (!authUrl)      throw new Error('Veuillez saisir l\'URL du portail');
                    if (!authUsername) throw new Error('Veuillez saisir l\'email / nom d\'utilisateur');
                    if (!authPassword) throw new Error('Veuillez saisir le mot de passe');
                } else {
                    apiUrl    = document.getElementById('company-api-url')?.value?.trim() || '';
                    apiKey    = document.getElementById('company-api-key')?.value?.trim() || '';
                    apiSecret = document.getElementById('api-secret')?.value?.trim() || '';

                    if (!apiUrl) throw new Error('Veuillez saisir l\'URL de l\'API');
                }

                // --- Build FormData for PHP ($_POST) ---
                const formData = new FormData();
                formData.append('name',         selectedCarrier === 'custom' ? customName : selectedCarrier);
                formData.append('slug',         selectedCarrier);
                formData.append('display_name', displayName || (selectedCarrier === 'custom' ? customName : selectedCarrier));
                formData.append('auth_mode',    mode);
                formData.append('is_active',    '1');
                formData.append('supports_tracking', '0');

                if (mode === 'auth') {
                    formData.append('api_url',       authUrl);       // <-- shared column
                    formData.append('auth_email',    authUsername);  // <-- renamed
                    formData.append('auth_password', authPassword);
                    formData.append('auth_notes',    authNotes);
                } else {
                    formData.append('api_url',    apiUrl);
                    formData.append('api_key',    apiKey);
                    formData.append('api_secret', apiSecret);
                }

                const response = await fetch('./controllers/save_shipping_company.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const responseText = await response.text();
                if (!response.ok) {
                    throw new Error(`Erreur HTTP! Status: ${response.status} - ${responseText}`);
                }

                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    throw new Error('Réponse invalide du serveur: ' + responseText.substring(0, 100));
                }

                if (!result.success) {
                    throw new Error(result.message || result.error || 'Erreur lors de l\'ajout du transporteur');
                }

                // --- Update UI state ---
                const newShipper = {
                    id: result.id || result.company_id,
                    name: displayName || (selectedCarrier === 'custom' ? customName : selectedCarrier),
                    auth_mode: mode,
                    // Show the right URL depending on mode
                    api_url: mode === 'auth' ? authUrl : apiUrl,
                    is_active: true
                };

                if (typeof connectedShippers !== 'undefined') {
                    connectedShippers.push(newShipper);
                    if (typeof renderConnectedShippers === 'function') renderConnectedShippers();
                }

                const completeBtn = document.getElementById('completeShippingBtn');
                if (completeBtn) completeBtn.style.display = 'inline-flex';

                if (typeof updateProgress === 'function') updateProgress();
                if (typeof closeShippingModal === 'function') closeShippingModal();
                if (typeof showNotification === 'function') {
                    showNotification('success', 'Transporteur ajouté avec succès!');
                } else {
                    alert('Transporteur ajouté avec succès!');
                }

                if (form) form.reset();
                setAuthMode('api'); // reset to default mode for next open

            } catch (error) {
                console.error('Error adding shipping company:', error);
                if (typeof showNotification === 'function') {
                    showNotification('error', error.message || 'Erreur lors de l\'ajout du transporteur');
                } else {
                    alert('Erreur: ' + (error.message || 'Erreur lors de l\'ajout du transporteur'));
                }
            } finally {
                if (submitBtn && document.body.contains(submitBtn)) {
                    submitBtn.innerHTML = originalText || 'Enregistrer';
                    submitBtn.disabled = false;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('company-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    saveShippingCompany();
                });
            }
        });

        // Generate animated background particles
        function createParticles() {
            const container = document.getElementById('onboardingContainer');
            const particleCount = 15;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 100 + 50;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
                
                container.appendChild(particle);
            }
        }
        
        // Load user limits from your PHP API
        async function loadUserLimits() {
            try {
                const response = await fetch('./controllers/get_user_limits.php');
                const data = await response.json();
                
                if (data.success) {
                    userLimits = data.limits;
                }
            } catch (error) {
                console.error('Error loading user limits:', error);
                // Fallback to default limits
                userLimits = { current: 0, max: 5 };
            }
        }

        // Check if user already has connected stores
        async function checkExistingStores() {
            try {
                const response = await fetch('./controllers/get_storesFrom_db.php');
                const data = await response.json();
                
                if (data.success && data.stores && data.stores.length > 0) {
                    connectedStores = data.stores;
                    renderConnectedStores();
                    
                    // Show continue button if stores exist
                    document.getElementById('storeActions').style.display = 'flex';
                    updateProgress();
                }
            } catch (error) {
                console.error('Error checking existing stores:', error);
            }
        }

        // Platform card selection
        document.querySelectorAll('.platform-card').forEach(card => {
            card.addEventListener('click', function() {
                if (this.onclick) return; 
                
                selectedPlatform = this.dataset.platform;
                
                // Remove selection from all cards in same grid
                const grid = this.parentElement;
                grid.querySelectorAll('.platform-card').forEach(c => {
                    c.classList.remove('selected');
                });
                
                // Add selection to clicked card
                this.classList.add('selected');
                
                // Hide all connection forms
                document.querySelectorAll('.connection-form').forEach(form => {
                    form.classList.remove('active');
                });
                
                // Show selected platform's connection form
                const form = document.getElementById(selectedPlatform + '-form');
                if (form) {
                    setTimeout(() => {
                        form.classList.add('active');
                        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 100);
                }
            });
        });
        
        // Connect store function with real API integration
        async function connectStore(platform) {
            const form = document.getElementById(platform + '-form');
            const formData = new FormData();
            
            try {
                // Show loading state
                const button = form.querySelector('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Connexion en cours...';
                button.disabled = true;
                
                // Prepare store data based on platform
                let storeData = {};
                
                if (platform === 'woocommerce') {
                    const storeName = document.getElementById('woocommerce-name').value;
                    const storeUrl = document.getElementById('woocommerce-url').value;
                    const consumerKey = document.getElementById('woocommerce-key').value;
                    const consumerSecret = document.getElementById('woocommerce-secret').value;
                    
                    if (!storeName || !storeUrl || !consumerKey || !consumerSecret) {
                        throw new Error('Veuillez remplir tous les champs');
                    }
                    
                    storeData = {
                        platform: 'woocommerce',
                        store_name: storeName,
                        domain: storeUrl.replace(/^https?:\/\//, ''),
                        consumer_key: consumerKey,
                        consumer_secret: consumerSecret,
                        api_url: `${storeUrl}/wp-json/wc/v3/`
                    };
                    
                    // Validate WooCommerce connection first
                    const isValid = await validateWooCommerceConnection(storeData);
                    if (!isValid) {
                        throw new Error('Impossible de se connecter à la boutique WooCommerce. Vérifiez vos identifiants.');
                    }
                    
                    // Add store to database
                    const response = await fetch('./controllers/add_storeTo_db.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(storeData)
                    });
                    
                    const result = await response.json();
                    
                    if (!result.success) {
                        throw new Error(result.message || 'Erreur lors de l\'ajout de la boutique');
                    }
                    
                    // Add to local state
                    connectedStores.push({
                        id: result.store_id,
                        name: storeData.store_name,
                        domain: storeData.domain,
                        platform: platform,
                        is_connected: true
                    });
                    
                } else if (platform === 'shopify' || platform === 'youcan') {
                    const storeName = document.getElementById(`${platform}-name`).value;
                    const storeDomain = document.getElementById(`${platform}-domain`).value;
                    
                    if (!storeName || !storeDomain) {
                        throw new Error('Veuillez remplir tous les champs');
                    }
                    
                    // Normalize domain
                    const normalizedDomain = normalizeDomain(storeDomain, platform);
                    
                    // Initiate OAuth flow
                    const oauthResponse = await fetch(`./controllers/${platform}_oauth_redirect.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            store_name: storeName,
                            domain: normalizedDomain
                        })
                    });
                    
                    const oauthData = await oauthResponse.json();
                    
                    if (!oauthData.success) {
                        throw new Error(oauthData.message || 'Erreur lors de l\'initialisation OAuth');
                    }
                    
                    // Redirect to OAuth URL
                    window.location.href = oauthData.install_url || oauthData.auth_url;
                    return; // Stop execution here since we're redirecting
                }
                
                // Update UI for successful connection
                renderConnectedStores();
                
                // Hide form
                form.classList.remove('active');
                
                // Show continue button
                document.getElementById('storeActions').style.display = 'flex';
                
                // Update progress
                updateProgress();
                
                // Show success message
                showNotification('success', 'Boutique connectée avec succès!');
                
                // Scroll to stores list
                setTimeout(() => {
                    document.getElementById('storesList').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 300);
                
            } catch (error) {
                console.error('Error connecting store:', error);
                showNotification('error', error.message || 'Erreur lors de la connexion');
            } finally {
                // Reset button state
                const button = form.querySelector('button');
                if (button) {
                    button.innerHTML = platform === 'woocommerce' ? 
                        '<i class="bi bi-plug"></i> Connecter la boutique' : 
                        '<i class="bi bi-link"></i> Se connecter à ' + platform.charAt(0).toUpperCase() + platform.slice(1);
                    button.disabled = false;
                }
            }
        }
        
        // Normalize domain for Shopify/YouCan
        function normalizeDomain(domain, platform) {

            domain = domain.trim().toLowerCase();

            // remove protocol
            domain = domain.replace(/^https?:\/\//, '');

            // remove paths
            domain = domain.split('/')[0];

            // remove trailing slash
            domain = domain.replace(/\/+$/, '');

            if (platform === 'shopify') {

                // Shopify validation
                if (!domain.endsWith('.myshopify.com')) {
                    throw new Error(
                        "Veuillez entrer votre domaine Shopify (ex: nom-boutique.myshopify.com) et non votre domaine personnalisé."
                    );
                }

                const regex = /^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/;
                if (!regex.test(domain)) {
                    throw new Error("Domaine Shopify invalide");
                }

            } else if (platform === 'youcan') {

                // Accept YouCan domains
                const validYouCanDomains = [
                    '.youcan.store',
                    '.youcan.shop'
                ];

                const isValid = validYouCanDomains.some(ext => domain.endsWith(ext));

                if (!isValid) {
                    throw new Error(
                        "Veuillez entrer votre domaine YouCan (ex: nom-boutique.youcan.store)"
                    );
                }

                const regex = /^[a-z0-9][a-z0-9\-]*\.(youcan\.store|youcan\.shop)$/;
                if (!regex.test(domain)) {
                    throw new Error("Domaine YouCan invalide");
                }
            }

            return domain;
        }
        
        // Validate WooCommerce connection
        async function validateWooCommerceConnection(storeData) {
            try {
                const response = await fetch('./controllers/woocommerce_proxy.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(storeData)
                });
                
                const result = await response.json();
                return result.success;
            } catch (error) {
                console.error("WooCommerce connection error:", error);
                return false;
            }
        }
        
        // Render connected stores list
        function renderConnectedStores() {
            const storesList = document.getElementById('storesList');
            const connectedStoresDiv = document.getElementById('connectedStores');
            
            if (connectedStores.length === 0) {
                storesList.style.display = 'none';
                return;
            }
            
            storesList.style.display = 'block';
            connectedStoresDiv.innerHTML = '';
            
            connectedStores.forEach(store => {
                const storeItem = document.createElement('div');
                storeItem.className = 'store-item';
                storeItem.innerHTML = `
                    <div class="store-info">
                        <h4>${store.storeName}</h4>
                        <p>${store.domain} • ${store.platform.charAt(0).toUpperCase() + store.platform.slice(1)}</p>
                    </div>
                    <div class="store-status ${store.is_connected ? 'status-connected' : 'status-disconnected'}">
                        ${store.is_connected ? 'Connecté' : 'Déconnecté'}
                    </div>
                `;
                
                connectedStoresDiv.appendChild(storeItem);
            });
        }
        
        // Go to shipping step
        function goToShipping() {
            currentStep = 2;
            
            // Hide store step
            document.getElementById('storeStep').style.display = 'none';
            
            // Show shipping step
            document.getElementById('shippingStep').style.display = 'block';
            
            // Update step indicators
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step1').classList.add('completed');
            document.getElementById('step1').querySelector('.step-circle').innerHTML = '<i class="bi bi-check"></i>';
            
            document.getElementById('connector1').classList.add('active');
            
            document.getElementById('step2').classList.add('active');
            
            // Load existing shipping companies
            loadShippingCompanies();
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            updateProgress();
        }
        
        // Load shipping companies from your PHP API
        async function loadShippingCompanies() {
            try {
                const response = await fetch('./controllers/get_shipping_data.php');
                const data = await response.json();
                
                if (data.success && data.data.shipping_companies && data.data.shipping_companies.length > 0) {
                    connectedShippers = data.data.shipping_companies;
                    renderConnectedShippers();
                    
                    // Show complete button if shippers exist
                    document.getElementById('completeShippingBtn').style.display = 'inline-flex';
                    updateProgress();
                }
            } catch (error) {
                console.error('Error loading shipping companies:', error);
            }
        }
        
        // Render connected shippers list
        function renderConnectedShippers() {
            const shippersList = document.getElementById('shippersList');
            const connectedShippersDiv = document.getElementById('connectedShippers');

            if (connectedShippers.length === 0) {
                shippersList.style.display = 'none';
                return;
            }

            shippersList.style.display = 'block';
            connectedShippersDiv.innerHTML = '';

            connectedShippers.forEach(shipper => {
                const modeLabel = shipper.auth_mode === 'auth'
                    ? '<span style="font-size:11px;background:#eef;color:#667eea;padding:2px 8px;border-radius:8px;margin-left:6px;">Auth</span>'
                    : '<span style="font-size:11px;background:#e6f7ee;color:#28a745;padding:2px 8px;border-radius:8px;margin-left:6px;">API</span>';

                const shipperItem = document.createElement('div');
                shipperItem.className = 'store-item';
                shipperItem.innerHTML = `
                    <div class="store-info">
                        <h4>${shipper.name}${modeLabel}</h4>
                        <p>${shipper.api_url || ''}</p>
                    </div>
                    <div class="store-status ${shipper.is_active ? 'status-connected' : 'status-disconnected'}">
                        ${shipper.is_active ? 'Actif' : 'Inactif'}
                    </div>
                `;
                connectedShippersDiv.appendChild(shipperItem);
            });
        }
        
        // Skip shipping
        function skipShipping() {
            completeOnboarding();
        }
        
        // Complete onboarding - Mark steps as completed in database
        async function completeOnboarding() {
            try {
                // Mark onboarding as completed in database
                const response = await fetch('./controllers/complete_onboarding.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        step_store: true,
                        step_shipping: connectedShippers.length > 0
                    })
                });
                
                const result = await response.json();
                
                if (!result.success) {
                    console.warn('Could not mark onboarding as completed:', result.message);
                }
                
                // Update UI
                showCompletionStep();
                
            } catch (error) {
                console.error('Error completing onboarding:', error);
                // Still show completion step even if API fails
                showCompletionStep();
            }
        }
        
        // Show completion step
        function showCompletionStep() {
            // Hide shipping step
            document.getElementById('shippingStep').style.display = 'none';
            
            // Show completed step
            document.getElementById('completedStep').style.display = 'block';
            
            // Update step indicators
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step2').classList.add('completed');
            document.getElementById('step2').querySelector('.step-circle').innerHTML = '<i class="bi bi-check"></i>';
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Update progress to 100%
            setTimeout(() => {
                document.getElementById('progressFill').style.width = '100%';
                document.getElementById('progressText').textContent = '2 sur 2 terminés';
            }, 300);
            
            // Create celebration effect
            createConfetti();
        }
        
        // Go to dashboard
        function goToDashboard() {
            window.location.href = './home';
        }
        
        // Update progress bar
        function updateProgress() {
            let completed = 0;
            const total = 2;
            
            if (connectedStores.length > 0) completed++;
            if (connectedShippers.length > 0) completed++;
            
            const percentage = (completed / total) * 100;
            
            document.getElementById('progressFill').style.width = percentage + '%';
            document.getElementById('progressText').textContent = `${completed} sur ${total} terminés`;
        }
        
        // Notification system
        function showNotification(type, message) {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.custom-notification');
            existingNotifications.forEach(notification => notification.remove());
            
            const notification = document.createElement('div');
            notification.className = `custom-notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()">&times;</button>
                </div>
            `;
            
            // Add styles if not already added
            if (!document.querySelector('#notification-styles')) {
                const styles = document.createElement('style');
                styles.id = 'notification-styles';
                styles.textContent = `
                    .custom-notification {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        padding: 16px 20px;
                        border-radius: 12px;
                        color: white;
                        font-weight: 500;
                        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                        z-index: 10000;
                        animation: slideInRight 0.3s ease-out;
                        max-width: 400px;
                    }
                    .notification-success {
                        background: linear-gradient(135deg, #28a745, #20c997);
                        border-left: 4px solid #1e7e34;
                    }
                    .notification-error {
                        background: linear-gradient(135deg, #dc3545, #e83e8c);
                        border-left: 4px solid #bd2130;
                    }
                    .notification-content {
                        display: flex;
                        align-items: center;
                        gap: 10px;
                    }
                    .notification-content button {
                        background: none;
                        border: none;
                        color: white;
                        font-size: 18px;
                        cursor: pointer;
                        margin-left: auto;
                    }
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    .bi-arrow-repeat.spin {
                        animation: spin 1s linear infinite;
                    }
                    @keyframes spin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                `;
                document.head.appendChild(styles);
            }
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Confetti effect
        function createConfetti() {
            const colors = ['#667eea', '#764ba2', '#f093fb', '#28a745', '#ffc107'];
            const confettiCount = 50;
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.top = '-10px';
                confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                confetti.style.opacity = '1';
                confetti.style.zIndex = '9999';
                confetti.style.pointerEvents = 'none';
                
                document.body.appendChild(confetti);
                
                const duration = Math.random() * 3 + 2;
                const xMovement = (Math.random() - 0.5) * 200;
                
                confetti.animate([
                    { transform: 'translateY(0) translateX(0) rotate(0deg)', opacity: 1 },
                    { transform: `translateY(100vh) translateX(${xMovement}px) rotate(${Math.random() * 720}deg)`, opacity: 0 }
                ], {
                    duration: duration * 1000,
                    easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
                });
                
                setTimeout(() => {
                    confetti.remove();
                }, duration * 1000);
            }
        }
        
        // Add hover effects to form inputs
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
                this.parentElement.style.transition = 'transform 0.3s ease';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });
        
        // Add ripple effect to buttons
        document.querySelectorAll('.btn-primary, .oauth-btn, .btn-skip').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.position = 'absolute';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.6)';
                ripple.style.transform = 'scale(0)';
                ripple.style.animation = 'ripple-animation 0.6s ease-out';
                ripple.style.pointerEvents = 'none';
                
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 600);
            });
        });
        
        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>

    <script>
        // Switch between API-key mode and Auth (credentials) mode
        function setAuthMode(mode) {
            document.getElementById('auth-mode').value = mode;

            const apiBtn  = document.getElementById('mode-api');
            const authBtn = document.getElementById('mode-auth');
            const apiFields  = document.getElementById('api-fields');
            const authFields = document.getElementById('auth-fields');

            if (mode === 'auth') {
                authBtn.classList.add('active');
                apiBtn.classList.remove('active');
                authFields.classList.add('active');
                apiFields.classList.remove('active');

                // Required attributes
                document.getElementById('company-auth-url').required = true;
                document.getElementById('company-auth-username').required = true;
                document.getElementById('company-auth-password').required = true;

                document.getElementById('company-api-url').required = false;
            } else {
                apiBtn.classList.add('active');
                authBtn.classList.remove('active');
                apiFields.classList.add('active');
                authFields.classList.remove('active');

                // Required attributes
                document.getElementById('company-api-url').required = true;

                document.getElementById('company-auth-url').required = false;
                document.getElementById('company-auth-username').required = false;
                document.getElementById('company-auth-password').required = false;
            }
        }

        // Auto-fill for known carriers (API + Auth)
        function autoFillApiUrl(carrierId) {
            const apiUrls = {
                'ozonexpress': 'https://api.ozonexpress.com/v1',
                'sendit':      'https://api.sendit.com/v1',
                'chronopost':  'https://api.chronopost.com/v1',
                'forcelog':    'https://api.forcelog.com/v1',
                'cathedis':    'https://api.cathedis.com/v1',
                'power':       'https://api.powerdelivery.com/v1',
                'ortalog':     'https://api.ortalog.ma/v1'
            };

            // Auth-mode portals for carriers that don't use APIs
            const authUrls = {
                'ortalog': 'https://ortalog.ma/login'
            };

            const currentMode = document.getElementById('auth-mode').value;

            if (currentMode === 'auth') {
                if (authUrls[carrierId]) {
                    document.getElementById('company-auth-url').value = authUrls[carrierId];
                }
            } else {
                if (apiUrls[carrierId]) {
                    document.getElementById('company-api-url').value = apiUrls[carrierId];
                }
            }
        }
    </script>

</body>
</html>