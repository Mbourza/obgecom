<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Page de connexion à votre compte PLatForme">
    <title>Connexion à votre compte | PLatForme</title>
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
        
        .login-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 8px 20px var(--shadow-color);
            width: 100%;
            max-width: 450px;
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
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
            font-size: 0.95rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
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
        
        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            padding: 0.25rem 0;
        }
        
        .forgot-password:hover {
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
        
        .register-link {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.95rem;
            color: var(--text-light);
        }
        
        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .register-link a:hover {
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
        
        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
                border-radius: 8px;
            }
            
            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .social-login {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>PLatForme</h1>
        </div>
        
        <h2>Connexion à votre compte</h2>
        
        <div class="alert alert-error" id="error-message" style="display: none;">
            <!-- Error messages will appear here -->
        </div>
        
        <form id="login-form" action="/login" method="POST" novalidate>
            <div class="form-group">
                <label for="email">Adresse email</label>
                <div class="input-group">
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2zm13 2.383-4.708 2.825L15 11.105V5.383zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741zM1 11.105l4.708-2.897L1 5.383v5.722z"/>
                        </svg>
                    </span>
                    <input type="email" id="email" name="email" placeholder="email@exemple.com" required autocomplete="email" aria-describedby="email-error">
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
                    <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password" aria-describedby="password-error">
                    <div class="toggle-password" id="toggle-password" role="button" aria-label="Afficher le mot de passe">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                            <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                        </svg>
                    </div>
                </div>
                <div id="password-error" class="validation-feedback"></div>
            </div>
            
            <div class="remember-forgot">
                <label class="checkbox-container remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <span class="checkmark"></span>
                    Se souvenir de moi
                </label>
                <a href="/mot-de-passe-oublie" class="forgot-password">Mot de passe oublié ?</a>
            </div>
            
            <button type="submit" class="btn" id="login-button">
                Se connecter
                <span class="spinner"></span>
            </button>
        </form>
        
        <div class="divider">ou</div>
        
        <div class="social-login">
            <button type="button" class="social-btn" aria-label="Se connecter avec Google">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#DB4437" viewBox="0 0 16 16">
                    <path d="M15.545 6.558a9.42 9.42 0 0 1 .139 1.626c0 2.434-.87 4.492-2.384 5.885h.002C11.978 15.292 10.158 16 8 16A8 8 0 1 1 8 0a7.689 7.689 0 0 1 5.352 2.082l-2.284 2.284A4.347 4.347 0 0 0 8 3.166c-2.087 0-3.86 1.408-4.492 3.304a4.792 4.792 0 0 0 0 3.063h.003c.635 1.893 2.405 3.301 4.492 3.301 1.078 0 2.004-.276 2.722-.764h-.003a3.702 3.702 0 0 0 1.599-2.431H8v-3.08h7.545z"/>
                </svg>
                Google
            </button>
            <button type="button" class="social-btn" aria-label="Se connecter avec Facebook">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#4267B2" viewBox="0 0 16 16">
                    <path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z"/>
                </svg>
                Facebook
            </button>
        </div>
        
        <div class="register-link">
            Vous n'avez pas de compte ? <a href="/inscription">S'inscrire</a>
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
            
            // Form validation
            const form = document.getElementById('login-form');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const emailError = document.getElementById('email-error');
            const passwordError = document.getElementById('password-error');
            const loginButton = document.getElementById('login-button');
            
            // Toggle password visibility
            const togglePassword = document.getElementById('toggle-password');
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                // Change icon based on visibility
                if (type === 'text') {
                    this.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="m10.79 12.912-1.614-1.615a3.5 3.5 0 0 1-4.474-4.474l-2.06-2.06C.938 6.278 0 8 0 8s3 5.5 8 5.5a7.029 7.029 0 0 0 2.79-.588zM5.21 3.088A7.028 7.028 0 0 1 8 2.5c5 0 8 5.5 8 5.5s-.939 1.721-2.641 3.238l-2.062-2.062a3.5 3.5 0 0 0-4.474-4.474L5.21 3.089z"/>
                            <path d="M5.525 7.646a2.5 2.5 0 0 0 2.829 2.829l-2.83-2.829zm4.95.708-2.829-2.83a2.5 2.5 0 0 1 2.829 2.829zm3.171 6-12-12 .708-.708 12 12-.708.708z"/>
                        </svg>
                    `;
                    this.setAttribute('aria-label', 'Masquer le mot de passe');
                } else {
                    this.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                            <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8zm8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z"/>
                        </svg>
                    `;
                    this.setAttribute('aria-label', 'Afficher le mot de passe');
                }
            });
            
            // Validate email format
            function validateEmail(email) {
                const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(String(email).toLowerCase());
            }
            
            // Live email validation
            emailInput.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    emailError.textContent = 'L\'adresse email est requise';
                    emailError.className = 'validation-feedback error';
                } else if (!validateEmail(this.value)) {
                    emailError.textContent = 'Veuillez entrer une adresse email valide';
                    emailError.className = 'validation-feedback error';
                } else {
                    emailError.textContent = '';
                    emailError.className = 'validation-feedback';
                }
            });
            
            // Live password validation
            passwordInput.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    passwordError.textContent = 'Le mot de passe est requis';
                    passwordError.className = 'validation-feedback error';
                } else if (this.value.length < 6) {
                    passwordError.textContent = 'Le mot de passe doit contenir au moins 6 caractères';
                    passwordError.className = 'validation-feedback error';
                } else {
                    passwordError.textContent = '';
                    passwordError.className = 'validation-feedback';
                }
            });
            
            // Form submission with validation
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                // Clear previous errors
                emailError.textContent = '';
                emailError.className = 'validation-feedback';
                passwordError.textContent = '';
                passwordError.className = 'validation-feedback';
                
                // Validate email
                if (emailInput.value.trim() === '') {
                    emailError.textContent = 'L\'adresse email est requise';
                    emailError.className = 'validation-feedback error';
                    isValid = false;
                } else if (!validateEmail(emailInput.value)) {
                    emailError.textContent = 'Veuillez entrer une adresse email valide';
                    emailError.className = 'validation-feedback error';
                    isValid = false;
                }
                
                // Validate password
                if (passwordInput.value.trim() === '') {
                    passwordError.textContent = 'Le mot de passe est requis';
                    passwordError.className = 'validation-feedback error';
                    isValid = false;
                } else if (passwordInput.value.length < 6) {
                    passwordError.textContent = 'Le mot de passe doit contenir au moins 6 caractères';
                    passwordError.className = 'validation-feedback error';
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    return false;
                }
                
                // Show loading state
                loginButton.classList.add('loading');
                loginButton.disabled = true;
                
                // Simulate CSRF token check and form submission
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                if (!csrfToken) {
                    // In a real application, you would check for CSRF token
                    console.warn('CSRF token not found');
                }
                
                // In a real application, this would be handled by the server
                return true;
            });
            
            // Add focus states for accessibility
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.borderColor = 'var(--primary-color)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.borderColor = '';
                });
            });
            
            // Prevent multiple form submissions
            let formSubmitted = false;
            form.addEventListener('submit', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }
                formSubmitted = true;
                return true;
            });
        });
    </script>
</body>
</html>