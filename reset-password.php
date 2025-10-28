<?php 
if(file_exists(stream_resolve_include_path("./core/init.php"))) {
    require_once("./core/init.php");
}

// Check if user is logged in, redirect to dashboard if they are
if(Session::exists(Config::get('session/session_name'))){
    Redirect::to('./dashboard/home.php'); 
}

$token = isset($_GET['token']) ? $_GET['token'] : '';
$validToken = false;
$error = '';

if (!empty($token)) {
    try {
        $db = DB::getInstance();
        $tokenData = $db->query(
            "SELECT * FROM password_resets WHERE token = ? AND used_at = 0 AND expires_at > NOW()",
            [$token]
        );
        
        $validToken = $tokenData->count() > 0;
        
        if (!$validToken) {
            $error = 'Invalid or expired reset link. Please request a new password reset.';
        }
    } catch (Exception $e) {
        $error = 'An error occurred while validating the reset link.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - OBG ECOM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/login.css" />
    <style>
        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .reset-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        
        .reset-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .reset-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="bg-animation">
        <div class="floating-shape"></div>
        <div class="floating-shape"></div>
        <div class="floating-shape"></div>
    </div>

    <div class="reset-container">
        <div class="reset-card">
            <div class="logo" style="font-size: 2rem; margin-bottom: 20px;">OBG ECOM</div>
            
            <?php if (!empty($error)): ?>
                <div class="reset-error">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
                <p><a href="./login.php" class="form-button" style="display: inline-block; text-decoration: none;">Back to Login</a></p>
            <?php elseif (!$validToken && empty($token)): ?>
                <h1>Invalid Reset Link</h1>
                <p>The reset link is missing or invalid.</p>
                <p><a href="./login.php" class="form-button" style="display: inline-block; text-decoration: none;">Back to Login</a></p>
            <?php elseif ($validToken): ?>
                <h1>Create New Password</h1>
                <p>Please enter your new password below.</p>
                
                <form id="resetPasswordForm">
                    <input type="hidden" id="resetToken" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="newPassword" style="color: #787878; font-size: .8em">New Password</label>
                        <div style="position: relative;">
                            <i class="input-icon bi bi-lock"></i>
                            <input type="password" id="newPassword" class="form-input" placeholder="••••••••" required minlength="8">
                            <i class="password-toggle bi bi-eye" onclick="togglePassword('newPassword', this)"></i>
                        </div>
                        <div class="error-message" id="newPasswordError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirmNewPassword" style="color: #787878; font-size: .8em">Confirm New Password</label>
                        <div style="position: relative;">
                            <i class="input-icon bi bi-shield-lock"></i>
                            <input type="password" id="confirmNewPassword" class="form-input" placeholder="••••••••" required minlength="8">
                            <i class="password-toggle bi bi-eye" onclick="togglePassword('confirmNewPassword', this)"></i>
                        </div>
                        <div class="error-message" id="confirmNewPasswordError"></div>
                    </div>

                    <button type="submit" class="form-button" id="resetButton">
                        <span class="spinner"></span>
                        <span class="button-text">Reset Password</span>
                    </button>
                </form>
                
                <div style="margin-top: 20px;">
                    <a href="./login.php">← Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="notification" id="notification"></div>

    <script>
        let isLoading = false;

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';
            
            input.type = isPassword ? 'text' : 'password';
            icon.className = isPassword ? 'password-toggle bi bi-eye-slash' : 'password-toggle bi bi-eye';
        }

        function showError(inputId, message) {
            const input = document.getElementById(inputId);
            const errorElement = document.getElementById(inputId + 'Error');
            
            if (input && errorElement) {
                input.classList.add('error');
                errorElement.textContent = message;
                errorElement.classList.add('show');
            }
        }

        function clearErrors() {
            const errorMessages = document.querySelectorAll('.error-message');
            const inputs = document.querySelectorAll('.form-input');
            
            errorMessages.forEach(error => {
                error.classList.remove('show');
                error.textContent = '';
            });
            
            inputs.forEach(input => input.classList.remove('error'));
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.textContent = message;
                notification.className = `notification ${type}`;
                notification.classList.add('show');
                
                setTimeout(() => notification.classList.remove('show'), 5000);
            }
        }

        function setLoadingState(loading) {
            const button = document.getElementById('resetButton');
            const spinner = button.querySelector('.spinner');
            
            if (loading) {
                button.disabled = true;
                spinner.style.display = 'inline-block';
                button.classList.add('loading');
                isLoading = true;
            } else {
                button.disabled = false;
                spinner.style.display = 'none';
                button.classList.remove('loading');
                isLoading = false;
            }
        }

        async function handleResetPassword(event) {
            event.preventDefault();
            if (isLoading) return;
            
            clearErrors();
            setLoadingState(true);
            
            const token = document.getElementById('resetToken').value;
            const password = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmNewPassword').value;
            
            // Validation
            if (!password) {
                showError('newPassword', 'Please enter a new password');
                setLoadingState(false);
                return;
            }
            
            if (password.length < 8) {
                showError('newPassword', 'Password must be at least 8 characters');
                setLoadingState(false);
                return;
            }
            
            if (!confirmPassword) {
                showError('confirmNewPassword', 'Please confirm your password');
                setLoadingState(false);
                return;
            }
            
            if (password !== confirmPassword) {
                showError('confirmNewPassword', 'Passwords do not match');
                setLoadingState(false);
                return;
            }
            
            try {
                const response = await fetch('./dashboard/controllers/reset_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        token: token,
                        password: password,
                        confirmPassword: confirmPassword
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    document.getElementById('resetPasswordForm').reset();
                    
                    // Redirect to login after success
                    setTimeout(() => {
                        window.location.href = './login.php';
                    }, 3000);
                } else {
                    showNotification(data.message, 'error');
                }
                
            } catch (error) {
                console.error('Reset password error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            } finally {
                setLoadingState(false);
            }
        }

        // Initialize form
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            if (form) {
                form.addEventListener('submit', handleResetPassword);
            }
        });
    </script>
</body>
</html>