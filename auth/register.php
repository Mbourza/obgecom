<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Créez votre compte sur PLatForme">
    <title>Inscription | PLatForme</title>
    <style>
        :root {
            --primary-color: #4a6fdc;
            --primary-dark: #3a5fc2;
            --primary-light: rgba(74, 111, 220, 0.1);
            --error-color: #e74c3c;
            --success-color: #2ecc71;
            --text-color: #333;
            --text-light: #666;
            --light-bg: #f9f9f9;
            --border-color: #ddd;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
        }
        
        .register-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 8px 20px var(--shadow-color);
            width: 100%;
            max-width: 550px;
            padding: 2.5rem;
            transition: var(--transition);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo h1 {
            color: var(--primary-color);
            font-size: 2rem;
            position: relative;
            display: inline-block;
        }
        
        .logo h1::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
            border-radius: 3px;
        }
        
        h2 {
            color: var(--text-color);
            text-align: center;
            margin-bottom: 1.75rem;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .alert {
            padding: 0.85rem;
            border-radius: 6px;
            margin-bottom: 1.75rem;
            text-align: center;
            font-weight: 500;
        }
        
        .alert-error {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(46, 204, 113, 0.2);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group input {
            width: 100%;
            padding: 0.85rem 1rem;
            padding-left: 2.5rem;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .input-group .icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }
        
        .input-group .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
        }
        
        .input-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 111, 220, 0.15);
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        
        .strength-meter {
            height: 5px;
            width: 100%;
            background-color: var(--border-color);
            border-radius: 5px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .strength-meter-fill {
            height: 100%;
            width: 0;
            background-color: var(--error-color);
            transition: var(--transition);
        }
        
        .terms-checkbox {
            margin-bottom: 1.75rem;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
        }
        
        .checkbox-container input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .checkmark {
            position: relative;
            display: inline-block;
            height: 18px;
            width: 18px;
            background-color: white;
            border: 1.5px solid var(--border-color);
            border-radius: 4px;
            margin-right: 8px;
            transition: var(--transition);
            flex-shrink: 0;
        }
        
        .checkbox-container:hover input ~ .checkmark {
            border-color: var(--primary-color);
        }
        
        .checkbox-container input:checked ~ .checkmark {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }
        
        .checkbox-container input:checked ~ .checkmark:after {
            display: block;
        }
        
        .checkbox-container .checkmark:after {
            left: 5px;
            top: 2px;
            width: 4px;
            height: 8px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .checkbox-text {
            font-size: 0.95rem;
            line-height: 1.4;
        }
        
        .checkbox-text a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .checkbox-text a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.85rem;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            background-color: var(--border-color);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn .spinner {
            display: none;
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            border: 2px solid white;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }
        
        .btn.loading .spinner {
            display: block;
        }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.75rem 0;
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }
        
        .divider::before {
            margin-right: 0.75rem;
        }
        
        .divider::after {
            margin-left: 0.75rem;
        }
        
        .social-login {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }
        
        .social-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            background-color: white;
            border: 1.5px solid var(--border-color);
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .social-btn:hover {
            background-color: var(--light-bg);
            transform: translateY(-2px);
        }
        
        .social-btn svg {
            margin-right: 0.5rem;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.95rem;
            color: var(--text-light);
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .validation-feedback {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: none;
        }
        
        .validation-feedback.error {
            color: var(--error-color);
            display: block;
        }
        
        @media (max-width: 640px) {
            .register-container {
                padding: 2rem 1.5rem;
                border-radius: 8px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .social-login {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>PLatForme</h1>
        </div>
        
        <h2>Créer un compte</h2>
        
        <div class="alert alert-error" id="error-message" style="display: none;">
            <!-- Error messages will appear here -->
        </div>
        
        <form id="register-form" action="/register" method="POST" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label for="first-name">Prénom</label>
                    <div class="input-group">
                        <span class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                            </svg>
                        </span>
                        <input type="text" id="first-name" name="first_name" placeholder="Jean" required autocomplete="given-name" aria-describedby="first-name-error">
                    </div>
                    <div id="first-name-error" class="validation-feedback"></div>
                </div>
                
                <div class="form-group">
                    <label for="last-name">Nom</label>
                    <div class="input-group">
                        <span class="icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                            </svg>
                        </span>
                        <input type="text" id="last-name" name="last_name" placeholder="Dupont" required autocomplete="family-name" aria-describedby="last-name-error">
                    </div>
                    <div id="last-name-error" class="validation-feedback"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">Adresse email</label>
                <div class="input-group">
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2zm13 2.383-4.708 2.825L15 11.105V5.383zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741zM1 11.105l4.708-2.897L1 5.383v5.722z"/>
                        </svg>
                    </span>
                    <input type="email" id="email" name="email" placeholder="jean.dupont@exemple.com" required autocomplete="email" aria-describedby="email-error">
                </div>
                <div id="email-error" class="validation-feedback"></div>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <div class="input-group">
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                        </svg>
                    </span>
                    <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="new-password" aria-describedby="password-error">
                    <div class="toggle-password" id="toggle-password" role="button" aria-label="Afficher le mot de passe">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                            <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                        </svg>
                    </div>
                </div>
                <div id="password-error" class="validation-feedback"></div>
                <div class="password-strength" id="password-strength">
                    <span id="strength-text">Force du mot de passe: Faible</span>
                    <div class="strength-meter">
                        <div class="strength-meter-fill" id="strength-meter-fill"></div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm-password">Confirmez le mot de passe</label>
                <div class="input-group">
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                        </svg>
                    </span>
                    <input type="password" id="confirm-password" name="confirm_password" placeholder="••••••••" required autocomplete="new-password" aria-describedby="confirm-password-error">
                    <div class="toggle-password" id="toggle-confirm-password" role="button" aria-label="Afficher le mot de passe">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                            <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                        </svg>
                    </div>
                </div>
                <div id="confirm-password-error" class="validation-feedback"></div>
            </div>
            
            <div class="terms-checkbox">
                <label class="checkbox-container">
                    <input type="checkbox" id="terms" name="terms" required>
                    <span class="checkmark"></span>
                    <span class="checkbox-text">
                        J'accepte les <a href="/conditions-utilisation" target="_blank">Conditions d'utilisation</a> et la <a href="/politique-confidentialite" target="_blank">Politique de confidentialité</a>
                    </span>
                </label>
                <div id="terms-error" class="validation-feedback"></div>
            </div>
            
            <button type="submit" class="btn" id="register-button">
                Créer mon compte
                <span class="spinner"></span>
            </button>
        </form>
        
        <div class="divider">ou</div>
        
        <div class="social-login">
            <button type="button" class="social-btn" aria-label="S'inscrire avec Google">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#DB4437" viewBox="0 0 16 16">
                    <path d="M15.545 6.558a9.42 9.42 0 0 1 .139 1.626c0 2.434-.87 4.492-2.384 5.885h.002C11.978 15.292 10.158 16 8 16A8 8 0 1 1 8 0a7.689 7.689 0 0 1 5.352 2.082l-2.284 2.284A4.347 4.347 0 0 0 8 3.166c-2.087 0-3.86 1.408-4.492 3.304a4.792 4.792 0 0 0 0 3.063h.003c.635 1.893 2.405 3.301 4.492 3.301 1.078 0 2.004-.276 2.722-.764h-.003a3.702 3.702 0 0 0 1.599-2.431H8v-3.08h7.545z"/>
                </svg>
                Google
            </button>
            <button type="button" class="social-btn" aria-label="S'inscrire avec Facebook">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#4267B2" viewBox="0 0 16 16">
                    <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/>
                </svg>
                Facebook
            </button>
        </div>
        
        <div class="login-link">
            Vous avez déjà un compte ? <a href="/login">Se connecter</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check for URL params
            const urlParams = new URLSearchParams(window.location.search);
            const errorMessage = urlParams.get('error');
            
            if (errorMessage) {
                document.getElementById('error-message').textContent = errorMessage;
                document.getElementById('error-message').style.display = 'block';
            }
            
            // Form elements
            const form = document.getElementById('register-form');
            const firstNameInput = document.getElementById('first-name');
            const lastNameInput = document.getElementById('last-name');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm-password');
            const termsCheckbox = document.getElementById('terms');
            const registerButton = document.getElementById('register-button');
            
            // Error elements
            const firstNameError = document.getElementById('first-name-error');
            const lastNameError = document.getElementById('last-name-error');
            const emailError = document.getElementById('email-error');
            const passwordError = document.getElementById('password-error');
            const confirmPasswordError = document.getElementById('confirm-password-error');
            const termsError = document.getElementById('terms-error');
            
            // Password strength elements
            const strengthText = document.getElementById('strength-text');
            const strengthMeterFill = document.getElementById('strength-meter-fill');
            
            // Toggle password visibility
            const togglePassword = document.getElementById('toggle-password');
            togglePassword.addEventListener('click', function() {
                togglePasswordVisibility(passwordInput, this);
            });
            
            const toggleConfirmPassword = document.getElementById('toggle-confirm-password');
            toggleConfirmPassword.addEventListener('click', function() {
                togglePasswordVisibility(confirmPasswordInput, this);
            });
            
            function togglePasswordVisibility(inputField, toggleButton) {
                const type = inputField.getAttribute('type') === 'password' ? 'text' : 'password';
                inputField.setAttribute('type', type);
                
                // Change icon based on visibility
                if (type === 'text') {
                    toggleButton.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="m10.79 12.912-1.614-1.615a3.5 3.5 0 0 1-4.474-4.474l-2.06-2.06C.938 6.278 0 8 0 8s3 5.5 8 5.5a7.029 7.029 0 0 0 2.79-.588zM5.21 3.088A7.028 7.028 0 0 1 8 2.5c5 0 8 5.5 8 5.5s-.939 1.721-2.641 3.238l-2.062-2.062a3.5 3.5 0 0 0-4.474-4.474L5.21 3.089z"/>
                            <path d="M5.525 7.646a2.5 2.5 0 0 0 2.829 2.829l-2.83-2.829zm4.95.708-2.829-2.83a2.5 2.5 0 0 1 2.829 2.829zm3.171 6-12-12 .708-.708 12 12-.708.708z"/>
                        </svg>
                    `;
                    toggleButton.setAttribute('aria-label', 'Masquer le mot de passe');
                } else {
                    toggleButton.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                            <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                        </svg>
                    `;
                    toggleButton.setAttribute('aria-label', 'Afficher le mot de passe');
                }
            }
            
            // Validate email format
            function validateEmail(email) {
                const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(String(email).toLowerCase());
            }
            
            // Check password strength
            function checkPasswordStrength(password) {
                let strength = 0;
                
                // If password length is less than 6 - return as weak
                if (password.length < 6) {
                    strengthText.textContent = 'Force du mot de passe: Faible';
                    strengthText.style.color = 'var(--error-color)';
                    strengthMeterFill.style.width = '25%';
                    strengthMeterFill.style.backgroundColor = 'var(--error-color)';
                    return;
                }
                
                // Length check
                if (password.length >= 8) {
                    strength += 1;
                }
                
                // Contains lowercase and uppercase
                if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) {
                    strength += 1;
                }
                
                // Contains numbers
                if (password.match(/([0-9])/)) {
                    strength += 1;
                }
                
                // Contains special characters
                if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) {
                    strength += 1;
                }
                
                // Display feedback based on strength
                if (strength <= 1) {
                    strengthText.textContent = 'Force du mot de passe: Faible';
                    strengthText.style.color = 'var(--error-color)';
                    strengthMeterFill.style.width = '25%';
                    strengthMeterFill.style.backgroundColor = 'var(--error-color)';
                } else if (strength === 2) {
                    strengthText.textContent = 'Force du mot de passe: Moyenne';
                    strengthText.style.color = '#f39c12';
                    strengthMeterFill.style.width = '50%';
                    strengthMeterFill.style.backgroundColor = '#f39c12';
                } else if (strength === 3) {
                    strengthText.textContent = 'Force du mot de passe: Bonne';
                    strengthText.style.color = '#3498db';
                    strengthMeterFill.style.width = '75%';
                    strengthMeterFill.style.backgroundColor = '#3498db';
                } else {
                    strengthText.textContent = 'Force du mot de passe: Excellente';
                    strengthText.style.color = 'var(--success-color)';
                    strengthMeterFill.style.width = '100%';
                    strengthMeterFill.style.backgroundColor = 'var(--success-color)';
                }
            }
            
            // Initialize password strength indicator
            checkPasswordStrength(passwordInput.value);
            
            // Event listener for password strength
            passwordInput.addEventListener('input', function() {
                checkPasswordStrength(this.value);
            });
            
            // Reset all error messages
            function resetErrors() {
                const errorElements = document.querySelectorAll('.validation-feedback');
                errorElements.forEach(elem => {
                    elem.textContent = '';
                    elem.classList.remove('error');
                });
                document.getElementById('error-message').style.display = 'none';
            }
            
            // Validate form inputs
            function validateForm() {
                let isValid = true;
                resetErrors();
                
                // First name validation
                if (firstNameInput.value.trim() === '') {
                    firstNameError.textContent = 'Veuillez saisir votre prénom';
                    firstNameError.classList.add('error');
                    isValid = false;
                }
                
                // Last name validation
                if (lastNameInput.value.trim() === '') {
                    lastNameError.textContent = 'Veuillez saisir votre nom';
                    lastNameError.classList.add('error');
                    isValid = false;
                }
                
                // Email validation
                if (emailInput.value.trim() === '') {
                    emailError.textContent = 'Veuillez saisir votre adresse email';
                    emailError.classList.add('error');
                    isValid = false;
                } else if (!validateEmail(emailInput.value.trim())) {
                    emailError.textContent = 'Veuillez saisir une adresse email valide';
                    emailError.classList.add('error');
                    isValid = false;
                }
                
                // Password validation
                if (passwordInput.value === '') {
                    passwordError.textContent = 'Veuillez saisir un mot de passe';
                    passwordError.classList.add('error');
                    isValid = false;
                } else if (passwordInput.value.length < 8) {
                    passwordError.textContent = 'Le mot de passe doit contenir au moins 8 caractères';
                    passwordError.classList.add('error');
                    isValid = false;
                }
                
                // Confirm password validation
                if (confirmPasswordInput.value === '') {
                    confirmPasswordError.textContent = 'Veuillez confirmer votre mot de passe';
                    confirmPasswordError.classList.add('error');
                    isValid = false;
                } else if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordError.textContent = 'Les mots de passe ne correspondent pas';
                    confirmPasswordError.classList.add('error');
                    isValid = false;
                }
                
                // Terms checkbox validation
                if (!termsCheckbox.checked) {
                    termsError.textContent = 'Vous devez accepter les conditions d\'utilisation';
                    termsError.classList.add('error');
                    isValid = false;
                }
                
                return isValid;
            }
            
            // Add real-time validation for inputs
            firstNameInput.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    firstNameError.textContent = 'Veuillez saisir votre prénom';
                    firstNameError.classList.add('error');
                } else {
                    firstNameError.textContent = '';
                    firstNameError.classList.remove('error');
                }
            });
            
            lastNameInput.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    lastNameError.textContent = 'Veuillez saisir votre nom';
                    lastNameError.classList.add('error');
                } else {
                    lastNameError.textContent = '';
                    lastNameError.classList.remove('error');
                }
            });
            
            emailInput.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    emailError.textContent = 'Veuillez saisir votre adresse email';
                    emailError.classList.add('error');
                } else if (!validateEmail(this.value.trim())) {
                    emailError.textContent = 'Veuillez saisir une adresse email valide';
                    emailError.classList.add('error');
                } else {
                    emailError.textContent = '';
                    emailError.classList.remove('error');
                }
            });
            
            confirmPasswordInput.addEventListener('input', function() {
                if (passwordInput.value !== this.value) {
                    confirmPasswordError.textContent = 'Les mots de passe ne correspondent pas';
                    confirmPasswordError.classList.add('error');
                } else {
                    confirmPasswordError.textContent = '';
                    confirmPasswordError.classList.remove('error');
                }
            });
            
            // Handle form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!validateForm()) {
                    return;
                }
                
                // Show loading state
                registerButton.classList.add('loading');
                registerButton.disabled = true;
                
                // Simulate form submission
                setTimeout(function() {
                    // Replace this with your actual form submission logic
                    
                    // For demonstration, let's assume the registration is successful
                    // In a real app, you would handle the server response here
                    window.location.href = '/registration-success';
                    
                    // If there was an error, you could show it like this:
                    // document.getElementById('error-message').textContent = 'Email already in use';
                    // document.getElementById('error-message').style.display = 'block';
                    // registerButton.classList.remove('loading');
                    // registerButton.disabled = false;
                }, 1500);
            });
        });
    </script>
</body>
</html>