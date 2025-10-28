<?php if(file_exists(stream_resolve_include_path("./core/init.php"))) {
    require_once("./core/init.php");
}
// Check if user is logged in
if(Session::exists(Config::get('session/session_name'))){
    Redirect::to('./dashboard/home.php'); 
} ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Obgecom est votre partenaire digital au Maroc : solutions e-commerce, gestion des commandes, sites web sur mesure et applications personnalisées pour booster votre business en ligne.">
    <meta name="keywords" content="Obgecom, e-commerce, gestion des commandes, site web sur mesure, application personnalisée, Maroc">
    <title>OBG ECOM - Authentication</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/login.css" />
    <!-- Meta Pixel Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        
        fbq('init', '1024222953100967');
        fbq('track', 'PageView');
    </script>
    <noscript>
        <img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=1024222953100967&ev=PageView&noscript=1"/>
    </noscript>

    <!-- End Meta Pixel Code -->
    <style>
        /* Phone verification styles */
        .verification-step {
            display: none;
        }
        
        .verification-step.active {
            display: block;
        }
        
        .phone-input-container {
            position: relative;
            margin-bottom: 20px;
        }
        
        .country-code {
            position: absolute;
            left: 45px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-weight: 500;
            z-index: 2;
        }
        
        .phone-input {
            padding-left: 80px !important;
        }
        
        .verification-code-container {
            display: none;
            margin-top: 20px;
        }
        
        .verification-code-container.active {
            display: block;
        }
        
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .code-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .code-input:focus {
            border-color: #8a2be2;
            box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.1);
            outline: none;
        }
        
        .code-input.filled {
            border-color: #10b981;
            background-color: #f0fdf4;
        }
        
        .resend-code {
            text-align: center;
            margin-top: 20px;
        }
        
        .resend-button {
            background: none;
            border: none;
            color: #8a2be2;
            cursor: pointer;
            font-size: 14px;
            text-decoration: underline;
        }
        
        .resend-button:disabled {
            color: #999;
            cursor: not-allowed;
        }
        
        .countdown {
            color: #666;
            font-size: 14px;
        }
        
        .verification-success {
            text-align: center;
            color: #10b981;
            margin: 10px 0;
        }
        
        .verification-error {
            text-align: center;
            color: #ef4444;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <?php require_once('./assets/navbar.php'); ?>
    <!-- Background animation -->
    <div class="bg-animation">
        <div class="floating-shape"></div>
        <div class="floating-shape"></div>
        <div class="floating-shape"></div>
    </div>

    <!-- Authentication Container -->
    <div class="auth-container" id="authContainer">
        <!-- Left side - Forms -->
        <div class="auth-forms">
            <!-- Login Form -->
            <div class="form-container active" id="loginForm">
                <h1 class="form-title">Login</h1>
                <p class="form-subtitle">Access your OBG dashboard</p>

                <form id="loginFormElement" onsubmit="handleLogin(event)">
                    <div class="form-group">
                        <label class="form-label" for="loginUsername">Username</label>
                        <div style="position: relative;">
                            <i class="input-icon bi bi-person"></i>
                            <input type="text" id="loginUsername" class="form-input" placeholder="Username / Email / Phone" required>
                        </div>
                        <div class="error-message" id="loginUsernameError"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="loginPassword">Password</label>
                        <div style="position: relative;">
                            <i class="input-icon bi bi-lock"></i>
                            <input type="password" id="loginPassword" class="form-input" placeholder="••••••••" required>
                            <i class="password-toggle bi bi-eye" onclick="togglePassword('loginPassword', this)"></i>
                        </div>
                        <div class="error-message" id="loginPasswordError"></div>
                        <div class="forgot-password">
                            <a href="#" onclick="showForgotPassword()">Forgot password?</a>
                        </div>
                    </div>

                    <button type="submit" class="form-button" id="loginButton">
                        <span class="spinner"></span>
                        <span class="button-text">Login</span>
                    </button>
                </form>

                <div class="form-switch">
                    Don't have an account? 
                    <button onclick="switchForm('signup')">Create account</button>
                </div>
            </div>

            <style>
                /* New styles for duration selector */
                
                .duration-selector {
                    margin: 30px 0;
                    text-align: center;
                    display: none;
                }

                .duration-options {
                    display: inline-flex;
                    background: rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    border-radius: 50px;
                    padding: 6px;
                    gap: 6px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                }

                .duration-option {
                    padding: 12px 24px;
                    border: none;
                    border-radius: 40px;
                    background: transparent;
                    color: rgba(255, 255, 255, 0.8);
                    cursor: pointer;
                    font-size: 0.9rem;
                    font-weight: 600;
                    transition: all 0.3s ease;
                    position: relative;
                    white-space: nowrap;
                }

                .duration-option:hover {
                    background: rgba(255, 255, 255, 0.15);
                    color: white;
                }

                .duration-option.selected {
                    background: white;
                    color: #8a2be2;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                }

                .duration-discount {
                    display: block;
                    font-size: 0.7rem;
                    font-weight: 700;
                    color: #fbbf24;
                    margin-top: 2px;
                }

                .duration-option.selected .duration-discount {
                    color: #f59e0b;
                }

                .plan-period {
                    color: #d3d3d3;
                    font-size: .9em;
                }

                .plan-saving {
                    color: #00d4ff;
                    font-style: italic;
                }

                @keyframes slideDown {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .plan-name {
                    font-size: 1.8rem;
                    font-weight: 700;
                    margin-bottom: 10px;
                    color: white;
                }

                .plan-price {
                    margin-bottom: 10px;
                }

                .price {
                    font-size: 2.5rem;
                    font-weight: 700;
                    color: white;
                }

                .period {
                    font-size: 1rem;
                    color: #d3d3d3;
                }

                .plan-features {
                    flex-grow: 1;
                    margin-bottom: 25px;
                }

                .plan-features ul {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                }

                .plan-features li {
                    padding: 12px 0;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                    display: flex;
                    align-items: center;
                    color: #e1e5e9;
                }

                .plan-features li:last-child {
                    border-bottom: none;
                }

                .plan-features i {
                    color: #10b981;
                    margin-right: 12px;
                    font-size: 1.2rem;
                }

                .plan-button {
                    width: 100%;
                    padding: 15px;
                    background: linear-gradient(135deg, #8a2be2, #6a11cb);
                    color: white;
                    border: none;
                    border-radius: 12px;
                    font-size: 1.1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    position: relative;
                    overflow: hidden;
                }

                .plan-button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 20px rgba(138, 43, 226, 0.4);
                }

                .plan-button:active {
                    transform: translateY(0);
                }

                /* Improved Carousel Navigation */
                .carousel-nav {
                    display: flex;
                    justify-content: space-between;
                    position: absolute;
                    top: 50%;
                    left: 0;
                    right: 0;
                    transform: translateY(-50%);
                    z-index: 10;
                    pointer-events: none;
                }

                .carousel-btn {
                    background: rgba(255, 255, 255, 0.9);
                    border: none;
                    width: 50px;
                    height: 50px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                    pointer-events: all;
                    font-size: 1.2rem;
                    color: #8a2be2;
                }

                .carousel-btn:hover {
                    background: white;
                    transform: scale(1.1);
                    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
                }

                .carousel-btn:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                    transform: none;
                }

                /* Improved Carousel Indicators */
                .carousel-indicators {
                    display: flex;
                    justify-content: center;
                    gap: 12px;
                    margin-top: 25px;
                }

                .carousel-dot {
                    width: 14px;
                    height: 14px;
                    border-radius: 50%;
                    border: none;
                    background: rgba(255, 255, 255, 0.3);
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .carousel-dot.active {
                    background: white;
                    transform: scale(1.2);
                }

                .carousel-dot:hover {
                    background: rgba(255, 255, 255, 0.7);
                    transform: scale(1.1);
                }

                /* Plan Selection Helper */
                .plan-selection-helper {
                    text-align: center;
                    margin: 15px 0;
                    color: rgba(255, 255, 255, 0.7);
                    font-size: 0.9rem;
                }

                .plan-selection-helper i {
                    margin-right: 5px;
                }

                /* Quick Plan Selector */
                .quick-plan-selector {
                    display: flex;
                    justify-content: center;
                    gap: 10px;
                    margin: 20px 0;
                    flex-wrap: wrap;
                }

                .quick-plan-btn {
                    padding: 10px 20px;
                    background: rgba(255, 255, 255, 0.1);
                    border: 2px solid transparent;
                    border-radius: 25px;
                    color: white;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    font-weight: 500;
                }

                .quick-plan-btn:hover {
                    background: rgba(255, 255, 255, 0.2);
                    transform: translateY(-2px);
                }

                .quick-plan-btn.active {
                    background: rgba(138, 43, 226, 0.3);
                    border-color: #9c80fd;
                    color: white;
                }

                /* Responsive Design */
                @media (max-width: 768px) {
                    .duration-options {
                        flex-wrap: wrap;
                        border-radius: 20px;
                    }
                    
                    .duration-option {
                        padding: 10px 18px;
                        font-size: 0.85rem;
                    }

                    .carousel-btn {
                        width: 40px;
                        height: 40px;
                    }

                    .plan-card {
                        padding: 20px;
                        min-height: 450px;
                    }

                    .price {
                        font-size: 2rem;
                    }

                    .quick-plan-selector {
                        gap: 8px;
                    }

                    .quick-plan-btn {
                        padding: 8px 16px;
                        font-size: 0.85rem;
                    }
                }

                @media (max-width: 480px) {
                    .plan-card {
                        padding: 15px;
                        min-height: 400px;
                    }

                    .price {
                        font-size: 1.8rem;
                    }

                    .quick-plan-selector {
                        flex-direction: column;
                        align-items: center;
                    }

                    .quick-plan-btn {
                        width: 100%;
                        max-width: 200px;
                    }
                }
            </style>

            <!-- Signup Step 1 - Plan Selection -->
            <div class="form-container" id="signupForm">
                <div class="signup-step active" id="signupStep1">
                    <h1 class="form-title">Choose Your Plan</h1>
                    <p class="form-subtitle">Select the plan that fits your needs</p>

                    <div class="quick-plan-selector">
                        <button class="quick-plan-btn active" data-plan="starter" onclick="goToPlanByType('starter')">Starter</button>
                        <button class="quick-plan-btn" data-plan="professional" onclick="goToPlanByType('professional')">Professional</button>
                        <button class="quick-plan-btn" data-plan="growth" onclick="goToPlanByType('growth')">Growth</button>
                        <button class="quick-plan-btn" data-plan="business" onclick="goToPlanByType('business')">Business</button>
                    </div>

                    <div class="duration-selector" id="durationSelector">
                        <div class="duration-options">
                            <div class="duration-option selected" data-duration="1" onclick="selectDuration(1)">
                                1 month
                            </div>
                            <div class="duration-option" data-duration="3" onclick="selectDuration(3)">
                                3 months
                                <div class="duration-discount">-5%</div>
                            </div>
                            <div class="duration-option" data-duration="12" onclick="selectDuration(12)">
                                12 months
                                <div class="duration-discount">-15%</div>
                            </div>
                            <div class="duration-option" data-duration="24" onclick="selectDuration(24)">
                                24 months
                                <div class="duration-discount">-25%</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Carousel Container -->
                    <div class="carousel-container">
                        <!-- Carousel Navigation -->
                        <div class="carousel-nav">
                            <button class="carousel-btn prev" id="prevBtn" onclick="previousPlan()">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button class="carousel-btn next" id="nextBtn" onclick="nextPlan()">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>

                        <!-- Carousel Track -->
                        <div class="carousel-track">
                            <div class="carousel-wrapper" id="plansCarousel">

                                <!-- Starter Plan - Free -->
                                <div class="plan-card" data-plan="starter">
                                    <div class="plan-header">
                                        <h3 class="plan-name">Starter</h3>
                                        <div class="plan-price">
                                            <span class="price">0 MAD</span>
                                            <span class="period">/month</span>
                                        </div>
                                        <span class="plan-period">Free - No commitment</span>
                                    </div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="bi bi-check"></i> Up to 100 orders per month</li>
                                            <li><i class="bi bi-check"></i> 1 store integration</li>
                                            <li><i class="bi bi-check"></i> 1 delivery company integration</li>
                                            <li><i class="bi bi-check"></i> Support via Email</li>
                                        </ul>
                                    </div>
                                    <button class="plan-button" onclick="selectPlan('starter')">Start for free</button>
                                </div>

                                <!-- Professional Plan -->
                                <div class="plan-card" data-plan="professional">
                                    <div class="plan-header">
                                        <h3 class="plan-name">Professional</h3>
                                        <div class="plan-price">
                                            <span class="price" data-base-price="149">149 MAD</span>
                                            <span class="period">/month</span>
                                        </div>
                                        <span class="plan-period" id="professional-period">Billed monthly</span>
                                    </div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="bi bi-check"></i> Up to 1,000 orders per month</li>
                                            <li><i class="bi bi-check"></i> Manage up to 5 stores</li>
                                            <li><i class="bi bi-check"></i> Multi-carrier automation</li>
                                            <li><i class="bi bi-check"></i> Priority chat & email support</li>
                                        </ul>
                                    </div>
                                    <button class="plan-button" onclick="selectPlan('professional')">Get Started</button>
                                </div>

                                <!-- Growth Plan - Featured -->
                                <div class="plan-card popular" data-plan="growth">
                                    <div class="plan-header">
                                        <h3 class="plan-name">Growth</h3>
                                        <div class="plan-price">
                                            <span class="price" data-base-price="199">199 MAD</span>
                                            <span class="period">/month</span>
                                        </div>
                                        <span class="plan-period" id="growth-period">Billed monthly</span>
                                    </div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="bi bi-check"></i> Up to 4,000 orders per month</li>
                                            <li><i class="bi bi-check"></i> Manage up to 10 stores</li>
                                            <li><i class="bi bi-check"></i> Multi-carrier automation & tracking</li>
                                            <li><i class="bi bi-check"></i> Priority chat & email support</li>
                                        </ul>
                                    </div>
                                    <button class="plan-button" onclick="selectPlan('growth')">Get Started</button>
                                </div>

                                <!-- Business Plan -->
                                <div class="plan-card" data-plan="business">
                                    <div class="plan-header">
                                        <h3 class="plan-name">Business</h3>
                                        <div class="plan-price">
                                            <span class="price" data-base-price="999">999 MAD</span>
                                            <span class="period">/month</span>
                                        </div>
                                        <span class="plan-period" id="business-period">Billed monthly</span>
                                    </div>
                                    <div class="plan-features">
                                        <ul>
                                            <li><i class="bi bi-check"></i> Unlimited orders & stores</li>
                                            <li><i class="bi bi-check"></i> Full multi-carrier automation</li>
                                            <li><i class="bi bi-check"></i> Advanced team & analytics dashboard</li>
                                            <li><i class="bi bi-check"></i> Dedicated account manager & 24/7 support</li>
                                        </ul>
                                    </div>
                                    <button class="plan-button" onclick="selectPlan('business')">Get Started</button>
                                </div>

                            </div>
                        </div>

                        <!-- Carousel Indicators -->
                        <div class="carousel-indicators">
                            <button class="carousel-dot active" onclick="goToPlan(0)"></button>
                            <button class="carousel-dot" onclick="goToPlan(1)"></button>
                            <button class="carousel-dot" onclick="goToPlan(2)"></button>
                            <button class="carousel-dot" onclick="goToPlan(3)"></button>
                        </div>
                    </div>
                </div>

                <!-- Signup Step 2 - User Details & Payment -->
                <div class="signup-step" id="signupStep2">
                    <h1 class="form-title">Complete Your Registration</h1>
                    <p class="form-subtitle">Create your account and proceed to payment</p>

                    <div class="selected-plan-info">
                        <div class="plan-summary">
                            <span class="plan-label">Selected plan:</span>
                            <span class="selected-plan-name" id="selectedPlanName" style="color: #e1d7f5;"></span>
                            <span class="selected-plan-price" id="selectedPlanPrice" style="color: #e1d7f5;"></span>
                        </div>
                    </div>

                    <form id="signupStep2Form">
                        <!-- Personal Information -->
                        <div class="verification-step active" id="personalInfoStep">
                            <div class="form-group">
                                <label class="form-label" for="signupName">Full Name</label>
                                <div style="position: relative;">
                                    <i class="input-icon bi bi-person"></i>
                                    <input type="text" id="signupName" class="form-input" placeholder="Your full name" required>
                                </div>
                                <div class="error-message" id="signupNameError"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="signupEmail">Email Address</label>
                                <div style="position: relative;">
                                    <i class="input-icon bi bi-envelope"></i>
                                    <input type="email" id="signupEmail" class="form-input" placeholder="your@email.com" required>
                                </div>
                                <div class="error-message" id="signupEmailError"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="signupPhone">Phone Number</label>
                                <div class="phone-input-container">
                                    <i class="input-icon bi bi-phone"></i>
                                    <span class="country-code">+212</span>
                                    <input type="tel" id="signupPhone" class="form-input phone-input" 
                                        placeholder="6XX-XXXXXX" pattern="[0-9]{9}" maxlength="9" required>
                                </div>
                                <div class="error-message" id="signupPhoneError"></div>
                                <small style="color: #ccc; font-size: 12px;">Enter your 9-digit Moroccan phone number (without +212)</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="signupPassword">Password</label>
                                <div style="position: relative;">
                                    <i class="input-icon bi bi-lock"></i>
                                    <input type="password" id="signupPassword" class="form-input" placeholder="••••••••" required>
                                    <i class="password-toggle bi bi-eye" onclick="togglePassword('signupPassword', this)"></i>
                                </div>
                                <div class="error-message" id="signupPasswordError"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="confirmPassword">Confirm Password</label>
                                <div style="position: relative;">
                                    <i class="input-icon bi bi-shield-lock"></i>
                                    <input type="password" id="confirmPassword" class="form-input" placeholder="••••••••" required>
                                    <i class="password-toggle bi bi-eye" onclick="togglePassword('confirmPassword', this)"></i>
                                </div>
                                <div class="error-message" id="confirmPasswordError"></div>
                            </div>

                            <div class="form-buttons">
                                <button type="button" class="form-button secondary" onclick="goToStep(1)">
                                    <i class="bi bi-arrow-left"></i>
                                    Back
                                </button>
                                <button type="button" class="form-button" onclick="verifyEmailAddress()" id="verifyEmailButton">
                                    <span class="spinner"></span>
                                    <span class="button-text">Verify Email Address</span>
                                </button>
                            </div>
                        </div>

                        <!-- Email Verification Step -->
                        <div class="verification-step" id="emailVerificationStep">
                            <div class="verification-header">
                                <h3 style="text-align: center; color: #333; margin-bottom: 10px;">Verify Your Email Address</h3>
                                <p style="text-align: center; color: #d7d7d7; margin-bottom: 20px;">
                                    We sent a verification code to <strong id="verificationEmailAddress">your@email.com</strong>
                                </p>
                            </div>

                            <div class="code-inputs">
                                <input type="text" class="code-input" maxlength="1" data-index="0" oninput="moveToNext(this)" onkeydown="handleCodeInputKeydown(event, this)">
                                <input type="text" class="code-input" maxlength="1" data-index="1" oninput="moveToNext(this)" onkeydown="handleCodeInputKeydown(event, this)">
                                <input type="text" class="code-input" maxlength="1" data-index="2" oninput="moveToNext(this)" onkeydown="handleCodeInputKeydown(event, this)">
                                <input type="text" class="code-input" maxlength="1" data-index="3" oninput="moveToNext(this)" onkeydown="handleCodeInputKeydown(event, this)">
                                <input type="text" class="code-input" maxlength="1" data-index="4" oninput="moveToNext(this)" onkeydown="handleCodeInputKeydown(event, this)">
                                <input type="text" class="code-input" maxlength="1" data-index="5" oninput="moveToNext(this)" onkeydown="handleCodeInputKeydown(event, this)">
                            </div>

                            <div id="verificationMessage" class="verification-success" style="display: none;">
                                ✓ Email address verified successfully!
                            </div>

                            <div id="verificationError" class="verification-error" style="display: none;">
                                Invalid verification code. Please try again.
                            </div>

                            <div class="resend-code">
                                <button type="button" class="resend-button" onclick="resendVerificationCode()" id="resendButton" style="color: #fff;">
                                    Resend Code
                                </button>
                                <div class="countdown" id="countdown" style="display: none;">
                                    Resend available in <span id="countdownTimer">60</span>s
                                </div>
                            </div>

                            <div class="form-buttons" style="margin-top: 30px;">
                                <button type="button" class="form-button secondary" onclick="backToPersonalInfo()">
                                    <i class="bi bi-arrow-left"></i>
                                    Back
                                </button>
                                <button type="button" class="form-button" onclick="completeSignup()" id="completeSignupButton" disabled>
                                    <span class="spinner"></span>
                                    <span class="button-text">Complete Signup</span>
                                </button>
                            </div>
                        </div>

                        <!-- Terms and Conditions (will be shown after email verification) -->
                        <div class="terms-acceptance" id="termsSection" style="display: none;">
                            <label class="checkbox-container">
                                <input type="checkbox" id="acceptTerms" name="acceptTerms" required>
                                <span class="checkmark"></span>
                                <span>
                                    I accept the 
                                    <span class="terms-link" onclick="showTermsModal()">Terms and Conditions of Sale</span> 
                                    and Privacy Policy
                                </span>
                            </label>
                            <div class="error-message" id="termsError"></div>
                        </div>
                    </form>
                </div>

                <div class="form-switch">
                    Already have an account? 
                    <button onclick="switchForm('login')">Login</button>
                </div>
            </div>

            <!-- Forgot Password Form -->
            <div class="form-container" id="forgotForm">
                <h1 class="form-title">Forgot Password</h1>
                <p class="form-subtitle">Enter your username to receive a reset link</p>

                <form id="forgotFormElement" onsubmit="handleForgotPassword(event)">
                    <div class="form-group">
                        <label class="form-label" for="forgotUsername">Username</label>
                        <div style="position: relative;">
                            <i class="input-icon bi bi-person"></i>
                            <input type="text" id="forgotUsername" class="form-input" placeholder="Your username" required>
                        </div>
                        <div class="error-message" id="forgotUsernameError"></div>
                    </div>

                    <button type="submit" class="form-button" id="forgotButton">
                        <span class="spinner"></span>
                        <span class="button-text">Send Reset Link</span>
                    </button>
                </form>

                <div class="form-switch">
                    <button onclick="switchForm('login')">← Back to login</button>
                </div>
            </div>
        </div>

        <style>
            .auth-branding {
                text-align: center;
                padding: 40px 30px;
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
                transition: all 0.3s ease;
            }

            .auth-branding:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            }

            .branding-logo {
                width: 100%;
                max-width: 300px;
                margin-bottom: 5px;
                filter: drop-shadow(0 3px 8px rgba(0, 0, 0, 0.1));
            }

            .features-list {
                list-style: none;
                padding: 0;
                margin: 0;
                text-align: left;
                display: inline-block;
                margin-top: -3em;
            }

            .features-list li {
                font-size: 16px;
                font-weight: 500;
                color: #FFF;
                margin: 12px 0;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .features-list i {
                color: #e1d7f5;
                font-size: 18px;
                background: rgba(46, 125, 255, 0.1);
                border-radius: 50%;
                padding: 4px;
            }
        </style>

        <!-- Right side - Branding -->
        <div class="auth-branding">
            <div class="logo">
                <img src="assets/img/logoIndex.png" alt="OBG ECOM" class="branding-logo">
            </div>

            <ul class="features-list">
                <li><i class="bi bi-check-circle-fill"></i> Automatic order synchronization<br>across all connected stores</li>
                <li><i class="bi bi-check-circle-fill"></i> Bulk order confirmation & processing<br>with one click</li>
                <li><i class="bi bi-check-circle-fill"></i> Seamless multi-carrier integration<br>with real-time tracking</li>
                <li><i class="bi bi-check-circle-fill"></i> Smart analytics dashboards<br>for sales, delivery & customer insights</li>
            </ul>

        </div>

    </div>

    <!-- Notification -->
    <div class="notification" id="notification"></div>

    <!-- Terms Modal -->
    <div class="modal-overlay" id="termsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Terms and Conditions of Sale – OBGecom</h3>
                <button class="modal-close" onclick="closeTermsModal()">
                    <i class="bi bi-x"></i>
                </button>
            </div>

            <style>
                /* Terms checkbox */
                .terms-acceptance {
                    margin-bottom: 25px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 10px;
                    border-left: 4px solid #3498db;
                    margin-top: 20px;
                }

                .checkbox-container {
                    display: flex;
                    align-items: flex-start;
                    cursor: pointer;
                    font-size: 0.9rem;
                    color: #2c3e50;
                    line-height: 1.4;
                }

                .checkbox-container input {
                    position: absolute;
                    opacity: 0;
                    cursor: pointer;
                }

                .checkmark {
                    height: 20px;
                    width: 20px;
                    background-color: #fff;
                    border: 2px solid #ddd;
                    border-radius: 4px;
                    margin-right: 12px;
                    margin-top: 2px;
                    flex-shrink: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.3s ease;
                }

                .checkbox-container input:checked ~ .checkmark {
                    background-color: #3498db;
                    border-color: #3498db;
                }

                .checkmark:after {
                    content: "";
                    display: none;
                }

                .checkbox-container input:checked ~ .checkmark:after {
                    display: block;
                }

                .checkbox-container .checkmark:after {
                    width: 5px;
                    height: 10px;
                    border: solid white;
                    border-width: 0 2px 2px 0;
                    transform: rotate(45deg);
                    margin-top: -2px;
                }
                .terms-link {
                    color: #3498db;
                    text-decoration: underline;
                    cursor: pointer;
                }

                .terms-link:hover {
                    color: #2980b9;
                }

                /* Modal styles */
                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
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

                .modal-content {
                    background: white;
                    border-radius: 20px;
                    max-width: 90%;
                    max-height: 90%;
                    width: 700px;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                    transform: scale(0.8);
                    transition: all 0.3s ease;
                }

                .modal-overlay.active .modal-content {
                    transform: scale(1);
                }

                .modal-header {
                    padding: 25px 30px 20px;
                    border-bottom: 1px solid #e9ecef;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .modal-header h3 {
                    margin: 0;
                    color: #2c3e50;
                    font-size: 1.5rem;
                }

                .modal-close {
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    color: #7f8c8d;
                    cursor: pointer;
                    padding: 5px;
                    border-radius: 50%;
                    transition: all 0.3s ease;
                }

                .modal-close:hover {
                    background: #f8f9fa;
                    color: #2c3e50;
                }

                .language-toggle {
                    display: flex;
                    justify-content: center;
                    margin-bottom: 20px;
                    gap: 10px;
                }

                .lang-btn {
                    padding: 8px 16px;
                    border: 2px solid #3498db;
                    background: white;
                    color: #3498db;
                    border-radius: 25px;
                    cursor: pointer;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    font-size: 0.9rem;
                }

                .lang-btn.active {
                    background: #3498db;
                    color: white;
                }

                .modal-body {
                    padding: 20px 30px;
                    overflow-y: auto;
                    flex: 1;
                }

                .terms-scrollable {
                    max-height: 400px;
                    overflow-y: auto;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 10px;
                    margin-bottom: 20px;
                    border: 1px solid #e9ecef;
                }

                .terms-scrollable h4 {
                    color: #3498db;
                    margin-top: 20px;
                    margin-bottom: 10px;
                    font-size: 1.1rem;
                }

                .terms-scrollable h4:first-child {
                    margin-top: 0;
                }

                .terms-scrollable p {
                    margin-bottom: 15px;
                    font-size: 0.95rem;
                    line-height: 1.6;
                    color: #2c3e50;
                }

                .modal-footer {
                    padding: 20px 30px;
                    border-top: 1px solid #e9ecef;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .scroll-indicator {
                    color: #7f8c8d;
                    font-size: 0.9rem;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }

                .scroll-indicator.hidden {
                    visibility: hidden;
                }
            </style>
            
            <div class="modal-body">
                <div class="language-toggle">
                    <button class="lang-btn active" onclick="switchLanguage('en')" id="enBtn">🇬🇧 English</button>
                    <button class="lang-btn" onclick="switchLanguage('fr')" id="frBtn">🇫🇷 Français</button>
                </div>

                <div class="terms-scrollable" id="termsScrollable">
                    <!-- English Content -->
                    <div id="enContent">
                        <h4>1. Purpose</h4>
                        <p>These Terms and Conditions ("T&C") govern the contractual relationship between OBGecom and any Client subscribing to its post-order automation SaaS services.</p>

                        <h4>2. Services Provided</h4>
                        <p>OBGecom provides a SaaS solution automating post-order steps: order validation, carrier assignment, customer notifications, and reporting.</p>

                        <h4>3. Pricing & Payment</h4>
                        <p>Prices are displayed in Moroccan Dirhams (MAD), Euros (EUR), or US Dollars (USD), excluding taxes. Payment is monthly or annual, via credit card or other secure methods. In case of non-payment, OBGecom reserves the right to suspend service access.</p>

                        <h4>4. Term & Termination</h4>
                        <p>The contract is effective for an indefinite term from subscription. The Client may terminate at any time via their account dashboard. OBGecom may terminate in case of breach of the T&Cs or non-payment.</p>

                        <h4>5. Right of Withdrawal</h4>
                        <p>Consumer Clients have a legal 14-day period from subscription to exercise their right of withdrawal, with no justification or fees. Requests must be sent to: support@obgecom.com, including subscription details. OBGecom will refund any amounts paid within 14 days using the same payment method.</p>
                        <p><strong>⚠️ Exception:</strong> if the Client has started using the service before the end of the withdrawal period, with prior consent, the right no longer applies to the consumed service.</p>

                        <h4>6. Client Obligations</h4>
                        <p>The Client agrees to provide accurate information, use the service lawfully, and not misuse the platform for fraudulent purposes.</p>

                        <h4>7. Liability</h4>
                        <p>OBGecom will take all necessary measures to ensure service continuity and security but cannot guarantee uninterrupted availability. OBGecom shall not be held liable for indirect damages.</p>

                        <h4>8. Data Protection</h4>
                        <p>OBGecom collects and processes personal data in compliance with Moroccan law 09-08 and GDPR. See the Privacy Policy for details.</p>

                        <h4>9. Intellectual Property</h4>
                        <p>All elements of the platform (software, graphics, logos, texts, etc.) are protected by copyright. Unauthorized reproduction or use is prohibited.</p>

                        <h4>10. Governing Law & Jurisdiction</h4>
                        <p>These T&Cs are governed by Moroccan law. Any dispute shall fall under the exclusive jurisdiction of the Casablanca courts.</p>
                    </div>

                    <!-- French Content -->
                    <div id="frContent" class="hidden">
                        <h4>1. Objet</h4>
                        <p>Les présentes Conditions Générales de Vente (« CGV ») régissent les relations contractuelles entre OBGecom et tout Client souscrivant à ses services SaaS d'automatisation post-commande e-commerce.</p>

                        <h4>2. Services proposés</h4>
                        <p>OBGecom propose une solution SaaS permettant d'automatiser les étapes post-commande : validation des commandes, affectation au transporteur, notifications clients et reporting.</p>

                        <h4>3. Tarifs et paiement</h4>
                        <p>Les prix sont exprimés en dirhams marocains (MAD), en euros (EUR) ou en dollars (USD), hors taxes. Le paiement est mensuel ou annuel, par carte bancaire ou tout moyen sécurisé. En cas de non-paiement, OBGecom se réserve le droit de suspendre l'accès au service.</p>

                        <h4>4. Durée et résiliation</h4>
                        <p>Le contrat est conclu pour une durée indéterminée à compter de la souscription. Le Client peut résilier son abonnement à tout moment via son espace personnel. OBGecom peut résilier en cas de manquement grave aux CGV ou défaut de paiement.</p>

                        <h4>5. Droit de rétractation</h4>
                        <p>Le Client consommateur dispose d'un délai légal de 14 jours à compter de la souscription pour exercer son droit de rétractation, sans motif ni frais. La demande doit être envoyée à : support@obgecom.com, avec les informations d'abonnement. OBGecom remboursera les sommes versées dans un délai de 14 jours via le même mode de paiement.</p>
                        <p><strong>⚠️ Exception :</strong> si le Client a commencé à utiliser le service avant la fin du délai, après accord exprès, le droit de rétractation ne s'applique plus pour la période déjà consommée.</p>

                        <h4>6. Obligations du Client</h4>
                        <p>Le Client s'engage à fournir des informations exactes, à utiliser le service conformément aux lois et à ne pas détourner la plateforme à des fins frauduleuses.</p>

                        <h4>7. Responsabilité</h4>
                        <p>OBGecom met en œuvre tous les moyens nécessaires pour assurer la continuité et la sécurité du service, mais ne garantit pas une disponibilité sans interruption. La responsabilité d'OBGecom ne couvre pas les dommages indirects.</p>

                        <h4>8. Données personnelles</h4>
                        <p>OBGecom collecte et traite les données personnelles conformément à la loi marocaine n°09-08 et au RGPD. Voir la Politique de Confidentialité pour plus de détails.</p>

                        <h4>9. Propriété intellectuelle</h4>
                        <p>Tous les éléments de la plateforme (logiciels, graphismes, logos, textes, etc.) sont protégés par le droit d'auteur. Toute reproduction ou utilisation non autorisée est interdite.</p>

                        <h4>10. Loi applicable et juridiction</h4>
                        <p>Les présentes CGV sont régies par la loi marocaine. Tout litige sera soumis à la compétence exclusive des tribunaux de Marrakech.</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="scroll-indicator" id="scrollIndicator">
                    <i class="bi bi-arrow-down"></i>
                    <span>Scroll to continue</span>
                </div>
                <button class="form-button" onclick="acceptTermsAndClose()" id="acceptTermsBtn" disabled>
                    Accept and close
                </button>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let currentUser = null;
        let isLoading = false;
        let currentStep = 1;
        let selectedPlan = null;
        let signupData = {};
        let currentPlanIndex = 0;
        const totalPlans = 4;
        let autoPlayInterval = null;
        let hasScrolledToBottom = false;
        let currentLanguage = 'en';

        let selectedDuration = 1; // Default to 1 month
        const durationDiscounts = {
            1: 0,    // 0% discount for 1 month
            3: 5,    // 5% discount for 3 months
            12: 15,  // 15% discount for 12 months
            24: 25   // 25% discount for 24 months
        };

        // Plan data
        const plans = {
            starter: {
                name: "Starter",
                price: "0",
                basePrice: 0,
                features: [
                    "Up to 100 orders/month", 
                    "1 store integration", 
                    "1 delivery company integration", 
                    "Support via Email", 
                    "Ideal for testing and launching your business"
                ]
            },
            professional: {
                name: "Professional",
                price: "149",
                basePrice: 149,
                features: [
                    "Up to 1,000 orders/month", 
                    "2 store integrations", 
                    "2 delivery company integrations", 
                    "Priority support via Email"
                ]
            },
            growth: {
                name: "Growth",
                price: "199",
                basePrice: 199,
                features: [
                    "Up to 4,000 orders/month", 
                    "4 store integrations", 
                    "5 delivery company integrations", 
                    "Up to 5 team members", 
                    "Premium support via WhatsApp & Email"
                ]
            },
            business: {
                name: "Business",
                price: "999",
                basePrice: 999,
                features: [
                    "Unlimited orders", 
                    "Unlimited integrations", 
                    "Unlimited team members", 
                    "Dedicated Account Manager", 
                    "Advanced support via WhatsApp, Email & Live Chat"
                ]
            }
        };

        let verificationCode = null;
        let isEmailVerified = false;
        let resendTimer = null;
        let countdownTime = 60;

        // Email verification functions
        async function verifyEmailAddress() {
            if (isLoading) return;
            
            // Validate personal information first
            const validationErrors = validatePersonalInfo();
            if (validationErrors.length > 0) {
                validationErrors.forEach(({ field, message }) => showError(field, message));
                return;
            }
            
            setLoadingState('verifyEmailButton', true);
            
            try {
                const emailAddress = document.getElementById('signupEmail').value;
                const userName = document.getElementById('signupName').value;
                
                // Show the email in verification step
                document.getElementById('verificationEmailAddress').textContent = emailAddress;
                
                // Call your backend to send email verification
                const response = await fetch('./dashboard/controllers/send_verification_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: emailAddress,
                        name: userName
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Store verification code for testing
                    if (data.verification_code) {
                        verificationCode = data.verification_code;
                        
                        showNotification(
                            `📧 Verification code sent to your email! ${data.test_mode ? 'Code: ' + verificationCode : ''}`, 
                            'success', 
                            8000
                        );
                    }
                    
                    // Switch to verification step
                    switchVerificationStep('emailVerificationStep');
                    
                    // Auto-fill code in test mode
                    if (data.verification_code) {
                        setTimeout(() => {
                            autoFillTestCode(data.verification_code);
                        }, 1000);
                    }
                    
                    // Start countdown for resend
                    startResendCountdown();
                    
                } else {
                    throw new Error(data.message || 'Failed to send verification code');
                }
                
            } catch (error) {
                console.error('Email verification error:', error);
                showNotification(error.message || 'Failed to send verification code. Please try again.', 'error');
            } finally {
                setLoadingState('verifyEmailButton', false);
            }
        }

        async function verifyCode() {
            const enteredCode = getVerificationCode();
            
            if (enteredCode.length !== 6) {
                showVerificationError('Please enter the 6-digit code');
                return;
            }
            
            setLoadingState('completeSignupButton', true);
            hideVerificationError();
            
            try {
                const emailAddress = document.getElementById('signupEmail').value;
                
                // Verify the code with your backend
                const response = await fetch('./dashboard/controllers/verify_email_code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: emailAddress,
                        code: enteredCode
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Email address verified successfully
                    isEmailVerified = true;
                    showVerificationSuccess();
                    
                    // Enable complete signup button
                    document.getElementById('completeSignupButton').disabled = false;
                    
                    // Show terms and conditions
                    document.getElementById('termsSection').style.display = 'block';
                    
                    showNotification('Email address verified successfully!', 'success');
                    
                } else {
                    throw new Error(data.message || 'Invalid verification code');
                }
                
            } catch (error) {
                console.error('Code verification error:', error);
                showVerificationError(error.message || 'Invalid verification code. Please try again.');
            } finally {
                setLoadingState('completeSignupButton', false);
            }
        }

        async function resendVerificationCode() {
            if (isLoading) return;
            
            setLoadingState('resendButton', true);
            
            try {
                const emailAddress = document.getElementById('signupEmail').value;
                
                // Resend verification code
                const response = await fetch('./dashboard/controllers/send_verification_email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: emailAddress,
                        name: document.getElementById('signupName').value,
                        resend: true
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update verification code (for testing)
                    if (data.verification_code) {
                        verificationCode = data.verification_code;
                        console.log('Test mode - New verification code:', verificationCode); // Remove in production
                    }
                    
                    // Clear previous code inputs
                    clearCodeInputs();
                    
                    // Restart countdown
                    startResendCountdown();
                    
                    showNotification('Verification code resent!', 'success');
                    
                } else {
                    throw new Error(data.message || 'Failed to resend code');
                }
                
            } catch (error) {
                console.error('Resend error:', error);
                showNotification(error.message || 'Failed to resend code. Please try again.', 'error');
            } finally {
                setLoadingState('resendButton', false);
            }
        }

        function startResendCountdown() {
            const resendButton = document.getElementById('resendButton');
            const countdownElement = document.getElementById('countdown');
            const countdownTimer = document.getElementById('countdownTimer');
            
            resendButton.disabled = true;
            countdownElement.style.display = 'block';
            countdownTime = 60;
            
            resendTimer = setInterval(() => {
                countdownTime--;
                countdownTimer.textContent = countdownTime;
                
                if (countdownTime <= 0) {
                    clearInterval(resendTimer);
                    resendButton.disabled = false;
                    countdownElement.style.display = 'none';
                }
            }, 1000);
        }

        function switchVerificationStep(step) {
            // Hide all verification steps
            document.querySelectorAll('.verification-step').forEach(step => {
                step.classList.remove('active');
            });
            
            // Show the target step
            document.getElementById(step).classList.add('active');
        }

        function backToPersonalInfo() {
            switchVerificationStep('personalInfoStep');
            hideVerificationError();
            hideVerificationSuccess();
            isEmailVerified = false;
            document.getElementById('completeSignupButton').disabled = true;
            document.getElementById('termsSection').style.display = 'none';
        }

        // Code input handling functions
        function moveToNext(input) {
            const value = input.value;
            const index = parseInt(input.getAttribute('data-index'));
            
            if (value && index < 5) {
                const nextInput = document.querySelector(`.code-input[data-index="${index + 1}"]`);
                if (nextInput) {
                    nextInput.focus();
                }
            }
            
            // Update input styling
            input.classList.toggle('filled', value !== '');
            
            // Auto-verify when all digits are entered
            if (index === 5 && value) {
                const code = getVerificationCode();
                if (code.length === 6) {
                    verifyCode();
                }
            }
        }

        function handleCodeInputKeydown(event, input) {
            const index = parseInt(input.getAttribute('data-index'));
            
            if (event.key === 'Backspace' && !input.value && index > 0) {
                const prevInput = document.querySelector(`.code-input[data-index="${index - 1}"]`);
                if (prevInput) {
                    prevInput.focus();
                    prevInput.value = ''; // Clear the previous input
                    prevInput.classList.remove('filled');
                }
            }
        }

        function getVerificationCode() {
            let code = '';
            for (let i = 0; i < 6; i++) {
                const input = document.querySelector(`.code-input[data-index="${i}"]`);
                if (input) {
                    code += input.value;
                }
            }
            return code;
        }

        function clearCodeInputs() {
            document.querySelectorAll('.code-input').forEach(input => {
                input.value = '';
                input.classList.remove('filled');
            });
            // Focus on first input
            const firstInput = document.querySelector('.code-input[data-index="0"]');
            if (firstInput) firstInput.focus();
        }

        function autoFillTestCode(code) {
            if (!code || code.length !== 6) return;
            
            for (let i = 0; i < 6; i++) {
                const input = document.querySelector(`.code-input[data-index="${i}"]`);
                if (input) {
                    input.value = code[i];
                    input.classList.add('filled');
                }
            }
            
            // Auto-verify after filling
            setTimeout(() => {
                verifyCode();
            }, 500);
        }

        function showVerificationSuccess() {
            const messageElement = document.getElementById('verificationMessage');
            messageElement.style.display = 'block';
            
            // Hide error if shown
            hideVerificationError();
        }

        function hideVerificationSuccess() {
            document.getElementById('verificationMessage').style.display = 'none';
        }

        function showVerificationError(message) {
            const errorElement = document.getElementById('verificationError');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
        }

        function hideVerificationError() {
            document.getElementById('verificationError').style.display = 'none';
        }

        async function completeSignup() {
            if (!isEmailVerified) {
                showNotification('Please verify your email address first', 'error');
                return;
            }
            
            // Check if terms are accepted
            const termsAccepted = document.getElementById('acceptTerms').checked;
            if (!termsAccepted) {
                showError('terms', 'You must accept the terms and conditions');
                return;
            }
            
            // Continue with the existing signup process
            await handleSignupStep2(new Event('submit'));
        }

        // Update validation to focus on email
        function validatePersonalInfo() {
            const errors = [];
            const email = document.getElementById('signupEmail').value;
            const phone = document.getElementById('signupPhone').value;
            
            // Existing validations...
            if (!document.getElementById('signupName').value.trim()) {
                errors.push({ field: 'signupName', message: 'Please enter your full name' });
            }
            
            if (!email.trim()) {
                errors.push({ field: 'signupEmail', message: 'Please enter your email address' });
            } else if (!validateEmail(email)) {
                errors.push({ field: 'signupEmail', message: 'Please enter a valid email address' });
            }
            
            // Phone validation (still required but not for verification)
            if (!phone.trim()) {
                errors.push({ field: 'signupPhone', message: 'Please enter your phone number' });
            } else if (!/^[0-9]{9}$/.test(phone)) {
                errors.push({ field: 'signupPhone', message: 'Please enter a valid 9-digit phone number' });
            } else if (!/^[65][0-9]{8}$/.test(phone)) {
                errors.push({ field: 'signupPhone', message: 'Please enter a valid Moroccan phone number starting with 6 or 5' });
            }
            
            // Password validations...
            const password = document.getElementById('signupPassword').value;
            if (!password) {
                errors.push({ field: 'signupPassword', message: 'Please enter a password' });
            } else if (!validatePassword(password)) {
                errors.push({ field: 'signupPassword', message: 'Password must be at least 8 characters' });
            }
            
            const confirmPassword = document.getElementById('confirmPassword').value;
            if (!confirmPassword) {
                errors.push({ field: 'confirmPassword', message: 'Please confirm your password' });
            } else if (password !== confirmPassword) {
                errors.push({ field: 'confirmPassword', message: 'Passwords do not match' });
            }
            
            return errors;
        }

        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function validatePhone(phone) {
            return /^[65][0-9]{8}$/.test(phone);
        }

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (resendTimer) {
                clearInterval(resendTimer);
            }
        });

        // Function to select duration
        function selectDuration(months) {
            selectedDuration = months;
            
            // Update UI for duration options
            document.querySelectorAll('.duration-option').forEach(option => {
                if (parseInt(option.getAttribute('data-duration')) === months) {
                    option.classList.add('selected');
                } else {
                    option.classList.remove('selected');
                }
            });
            
            // Update all plan prices
            updateAllPlanPrices();
        }

        function updateAllPlanPrices() {
            // For each paid plan, update the price display
            ['professional', 'growth', 'business'].forEach(planId => {
                updatePlanPrice(planId);
            });
        }

        function updatePlanPrice(planId) {
            if (planId === 'starter') return; // Starter is always free
            
            const plan = plans[planId];
            const discount = durationDiscounts[selectedDuration];
            const discountedPrice = plan.basePrice * (1 - discount/100);
            
            // Update the price display
            const priceElement = document.querySelector(`[data-plan="${planId}"] .price`);
            if (priceElement) {
                priceElement.textContent = `${Math.round(discountedPrice)} MAD`;
            }
            
            // Update saving information
            const savingElement = document.getElementById(`${planId}-saving`);
            if (savingElement && discount > 0) {
                const monthlySaving = plan.basePrice - discountedPrice;
                const totalSaving = monthlySaving * selectedDuration;
                const roundedSaving = Math.round(totalSaving); // ✅ round to nearest integer
                savingElement.textContent = `Save ${roundedSaving} MAD (${discount}%)`;
            } else if (savingElement) {
                savingElement.textContent = '';
            }
            
            // Update period text
            const periodElement = document.getElementById(`${planId}-period`);
            if (periodElement) {
                if (selectedDuration === 1) {
                    periodElement.textContent = 'Billed monthly';
                } else {
                    const totalAmount = discountedPrice * selectedDuration;
                    periodElement.textContent = `${selectedDuration} months for ${Math.round(totalAmount)} MAD`;
                }
            }
        }

        function toggleDurationSelector(planId) {
            const durationSelector = document.getElementById('durationSelector');
            
            if (planId === 'starter') {
                // Hide duration selector for free plan
                durationSelector.style.display = 'none';
                // Reset to 1 month duration
                selectDuration(1);
            } else {
                // Show duration selector for paid plans
                durationSelector.style.display = 'block';
            }
        }

        function switchLanguage(lang) {
            currentLanguage = lang;
            const frBtn = document.getElementById('frBtn');
            const enBtn = document.getElementById('enBtn');
            const frContent = document.getElementById('frContent');
            const enContent = document.getElementById('enContent');
            const modalTitle = document.getElementById('modalTitle');
            
            if (lang === 'en') {
                enBtn.classList.add('active');
                frBtn.classList.remove('active');
                enContent.classList.remove('hidden');
                frContent.classList.add('hidden');
                modalTitle.textContent = 'Terms and Conditions of Sale – OBGecom';
            } else {
                frBtn.classList.add('active');
                enBtn.classList.remove('active');
                frContent.classList.remove('hidden');
                enContent.classList.add('hidden');
                modalTitle.textContent = 'Conditions Générales de Vente – OBGecom';
            }
            
            // Reset scroll detection
            hasScrolledToBottom = false;
            updateAcceptButton();
        }

        function showTermsModal() {
            const modal = document.getElementById('termsModal');
            const termsScrollable = document.getElementById('termsScrollable');
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Reset scroll position and detection
            termsScrollable.scrollTop = 0;
            hasScrolledToBottom = false;
            updateAcceptButton();
            
            // Add scroll listener
            termsScrollable.addEventListener('scroll', handleTermsScroll);
        }

        function closeTermsModal() {
            const modal = document.getElementById('termsModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        function handleTermsScroll() {
            const termsScrollable = document.getElementById('termsScrollable');
            const scrollTop = termsScrollable.scrollTop;
            const scrollHeight = termsScrollable.scrollHeight;
            const clientHeight = termsScrollable.clientHeight;
            
            // Check if scrolled to bottom (with some tolerance)
            if (scrollTop + clientHeight >= scrollHeight - 10) {
                hasScrolledToBottom = true;
                updateAcceptButton();
            }
        }

        function updateAcceptButton() {
            const acceptBtn = document.getElementById('acceptTermsBtn');
            const scrollIndicator = document.getElementById('scrollIndicator');
            
            if (hasScrolledToBottom) {
                acceptBtn.disabled = false;
                scrollIndicator.classList.add('hidden');
                if (currentLanguage === 'en') {
                    acceptBtn.textContent = 'Accept and close';
                } else {
                    acceptBtn.textContent = 'Accepter et fermer';
                }
            } else {
                acceptBtn.disabled = true;
                scrollIndicator.classList.remove('hidden');
                if (currentLanguage === 'en') {
                    acceptBtn.textContent = 'Scroll to continue';
                    scrollIndicator.innerHTML = '<i class="bi bi-arrow-down"></i><span>Scroll to continue</span>';
                } else {
                    acceptBtn.textContent = 'Faites défiler pour continuer';
                    scrollIndicator.innerHTML = '<i class="bi bi-arrow-down"></i><span>Faites défiler pour continuer</span>';
                }
            }
        }

        function acceptTermsAndClose() {
            if (hasScrolledToBottom) {
                document.getElementById('acceptTerms').checked = true;
                closeTermsModal();
            }
        }

        // Initialize application
        document.addEventListener('DOMContentLoaded', function() {
            initializeFormListeners();
            initializeRealTimeValidation();
            initializeKeyboardShortcuts();
            initializeCarousel();
            initializePlanCardClicks();
            initializeQuickPlanSelector();
            
            // Get the plan from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const planFromUrl = urlParams.get('plan');
            
            if (planFromUrl && ['starter', 'professional', 'growth', 'business'].includes(planFromUrl)) {
                // Switch to signup form if not already there
                if (!document.getElementById('signupForm').classList.contains('active')) {
                    switchForm('signup');
                }
                
                // Select the plan
                selectPlan(planFromUrl);
                
                // Scroll to the signup form for better UX
                document.getElementById('signupForm').scrollIntoView({ behavior: 'smooth' });
            }
        });

        // Initialize form event listeners
        function initializeFormListeners() {
            const loginForm = document.querySelector('#loginForm');
            if (loginForm) loginForm.addEventListener('submit', handleLogin);
            
            const signupStep2Form = document.querySelector('#signupStep2Form');
            if (signupStep2Form) signupStep2Form.addEventListener('submit', handleSignupStep2);
            
            const forgotForm = document.querySelector('#forgotForm form');
            if (forgotForm) forgotForm.addEventListener('submit', handleForgotPassword);
        }

        // Initialize real-time validation
        function initializeRealTimeValidation() {
            const validators = [
                { id: 'signupEmail', validator: validateEmail, message: 'Please enter a valid email address' },
                { id: 'signupPassword', validator: validatePassword, message: 'Password must be at least 8 characters' }, 
                { id: 'signupPhone', validator: validatePhone, message: 'Please enter a valid 9-digit phone number' }
            ];

            validators.forEach(({ id, validator, message }) => {
                const input = document.getElementById(id);
                if (input) {
                    input.addEventListener('blur', function() {
                        if (this.value && !validator(this.value)) {
                            showError(id, message);
                        } else {
                            clearInputError(id);
                        }
                    });
                }
            });

            // Confirm password validation
            const confirmPasswordInput = document.getElementById('confirmPassword');
            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('blur', function() {
                    const password = document.getElementById('signupPassword').value;
                    if (this.value && this.value !== password) {
                        showError('confirmPassword', 'Passwords do not match');
                    } else {
                        clearInputError('confirmPassword');
                    }
                });
            }
        }

        // Initialize keyboard shortcuts
        function initializeKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    switchForm('login');
                }
                
                if (document.getElementById('signupStep1')?.classList.contains('active')) {
                    if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        previousPlan();
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        nextPlan();
                    }
                }
            });
        }

        // Initialize carousel
        function initializeCarousel() {
            updateCarouselControls();
            initializeTouchSupport();
        }

        // Initialize touch/swipe support for mobile
        function initializeTouchSupport() {
            let startX = 0;
            let currentX = 0;
            let isDragging = false;

            const carousel = document.getElementById('plansCarousel');
            if (!carousel) return;
            
            carousel.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                isDragging = true;
            });

            carousel.addEventListener('touchmove', function(e) {
                if (!isDragging) return;
                e.preventDefault();
                currentX = e.touches[0].clientX;
            });

            carousel.addEventListener('touchend', function(e) {
                if (!isDragging) return;
                isDragging = false;
                
                const diffX = startX - currentX;
                const threshold = 50;
                
                if (Math.abs(diffX) > threshold) {
                    if (diffX > 0) {
                        nextPlan();
                    } else {
                        previousPlan();
                    }
                }
            });

            // Add resize handler
            window.addEventListener('resize', updateCarousel);
        }

        // Initialize plan card click handlers
        function initializePlanCardClicks() {
            const planCards = document.querySelectorAll('.plan-card');
            planCards.forEach((card, index) => {
                card.addEventListener('click', function() {
                    if (index !== currentPlanIndex) {
                        goToPlan(index);
                    }
                });
            });
        }

        function switchForm(formType) {
            const forms = document.querySelectorAll('.form-container');
            forms.forEach(form => form.classList.remove('active'));
            
            const targetForm = document.getElementById(formType + 'Form');
            if (targetForm) {
                targetForm.classList.add('active');
            }
            
            if (formType === 'signup') {
                goToStep(1);
            }
            
            clearErrors();
        }

        // Plan selection
        function selectPlan(planId) {
            selectedPlan = planId;
            signupData.plan = planId;
            
            // Show/hide duration selector based on plan
            toggleDurationSelector(planId);
            
            // For free plan, set duration to 1 month
            if (planId === 'starter') {
                signupData.duration = 1;
                signupData.planPrice = 0;
            } else {
                // For paid plans, use selected duration
                signupData.duration = selectedDuration;
                
                // Calculate the price with discount
                const discount = durationDiscounts[selectedDuration];
                const basePrice = plans[planId].basePrice;
                const finalPrice = basePrice * (1 - discount/100);
                signupData.planPrice = Math.round(finalPrice);
            }
            
            // Add selection animation
            const selectedCard = document.querySelector(`[data-plan="${planId}"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
                setTimeout(() => selectedCard.classList.remove('selected'), 300);
            }
            
            // Update selected plan display
            const selectedPlanName = document.getElementById('selectedPlanName');
            const selectedPlanPrice = document.getElementById('selectedPlanPrice');
            
            if (selectedPlanName && selectedPlanPrice && plans[planId]) {
                selectedPlanName.textContent = plans[planId].name;
                
                if (planId === 'starter') {
                    selectedPlanPrice.textContent = 'Free';
                } else {
                    if (selectedDuration === 1) {
                        selectedPlanPrice.textContent = `${signupData.planPrice} MAD/month`;
                    } else {
                        const totalAmount = signupData.planPrice * selectedDuration;
                        selectedPlanPrice.textContent = `${signupData.planPrice} MAD/month (${selectedDuration} months)`;
                    }
                }
            }
            
            showNotification(`${plans[planId].name} plan selected!`, 'success');
            
            setTimeout(() => goToStep(2), 500);
        }

        // Step navigation
        function goToStep(step) {
            const steps = document.querySelectorAll('.signup-step');
            steps.forEach(s => s.classList.remove('active'));
            
            const targetStep = document.getElementById('signupStep' + step);
            if (targetStep) {
                targetStep.classList.add('active');
                currentStep = step;
            }
        }

        // Carousel navigation
        function nextPlan() {
            if (currentPlanIndex < totalPlans - 1) {
                currentPlanIndex++;
                updateCarousel();
            }
        }

        function previousPlan() {
            if (currentPlanIndex > 0) {
                currentPlanIndex--;
                updateCarousel();
            }
        }

        function goToPlan(index) {
            if (index >= 0 && index < totalPlans) {
                currentPlanIndex = index;
                toggleDurationSelector(currentPlanIndex)
                updateCarousel();
            }
        }

        function updateCarousel() {
            const carousel = document.getElementById('plansCarousel');
            if (!carousel) return;
            
            const translateX = -(currentPlanIndex * (100 / totalPlans));
            carousel.style.transform = `translateX(${translateX}%)`;
            carousel.style.transition = 'transform 0.3s ease-in-out';
            
            updateCarouselControls();
            highlightCurrentPlan();
            updateQuickPlanSelector();
            
        }

        function updateCarouselControls() {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            if (prevBtn && nextBtn) {
                prevBtn.disabled = currentPlanIndex === 0;
                nextBtn.disabled = currentPlanIndex === totalPlans - 1;
                prevBtn.style.opacity = currentPlanIndex === 0 ? '0.5' : '1';
                nextBtn.style.opacity = currentPlanIndex === totalPlans - 1 ? '0.5' : '1';
            }
            
            const dots = document.querySelectorAll('.carousel-dot');
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentPlanIndex);
            });
        }

        function highlightCurrentPlan() {
            const planCards = document.querySelectorAll('.plan-card');
            planCards.forEach((card, index) => {
                if (index === currentPlanIndex) {
                    card.style.transform = 'scale(1.05)';
                    card.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                } else {
                    card.style.transform = 'scale(1)';
                    card.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
                }
            });
        }

        function updateQuickPlanSelector() {
            const planKeys = Object.keys(plans);
            const currentPlanId = planKeys[currentPlanIndex];
            
            const quickPlanButtons = document.querySelectorAll('.quick-plan-btn');
            quickPlanButtons.forEach(button => {
                if (button.getAttribute('data-plan') === currentPlanId) {
                    button.classList.add('active');
                } else {
                    button.classList.remove('active');
                }
            });
        }

        // Auto-play functionality
        function startAutoPlay(interval = 5000) {
            return setInterval(() => {
                if (currentPlanIndex < totalPlans - 1) {
                    nextPlan();
                } else {
                    currentPlanIndex = 0;
                    updateCarousel();
                }
            }, interval);
        }

        function stopAutoPlay() {
            if (autoPlayInterval) {
                clearInterval(autoPlayInterval);
                autoPlayInterval = null;
            }
        }

        // Utility functions
        function showForgotPassword() {
            switchForm('forgot');
        }

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';
            
            input.type = isPassword ? 'text' : 'password';
            icon.className = isPassword ? 'password-toggle bi bi-eye-slash' : 'password-toggle bi bi-eye';
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

        function showError(inputId, message) {
            const input = document.getElementById(inputId);
            const errorElement = document.getElementById(inputId + 'Error');
            
            if (input && errorElement) {
                input.classList.add('error');
                errorElement.textContent = message;
                errorElement.classList.add('show');
            }
        }

        function clearInputError(inputId) {
            const input = document.getElementById(inputId);
            const errorElement = document.getElementById(inputId + 'Error');
            
            if (input && errorElement) {
                input.classList.remove('error');
                errorElement.classList.remove('show');
                errorElement.textContent = '';
            }
        }

        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.textContent = message;
                notification.className = `notification ${type}`;
                notification.classList.add('show');
                
                setTimeout(() => notification.classList.remove('show'), 4000);
            }
        }

        function setLoadingState(buttonId, loading) {
            const button = document.getElementById(buttonId);
            if (!button) return;
            
            const spinner = button.querySelector('.spinner');
            
            if (loading) {
                button.disabled = true;
                if (spinner) spinner.style.display = 'inline-block';
                button.classList.add('loading');
                isLoading = true;
            } else {
                button.disabled = false;
                if (spinner) spinner.style.display = 'none';
                button.classList.remove('loading');
                isLoading = false;
            }
        }

        // Validation functions
        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function validatePassword(password) {
            return password.length >= 8;
        }

        function getCurrentPlan() {
            const planKeys = Object.keys(plans);
            return plans[planKeys[currentPlanIndex]];
        }

        // Event handlers
        async function handleLogin(event) {
            event.preventDefault();
            if (isLoading) return;
            
            clearErrors();
            setLoadingState('loginButton', true);
            
            const username = document.getElementById('loginUsername')?.value;
            const password = document.getElementById('loginPassword')?.value;
            const remember = document.getElementById('rememberMe')?.checked || false;
            
            // Client-side validation
            if (!username) {
                showError('loginUsername', 'Please enter your username');
                setLoadingState('loginButton', false);
                return;
            }
            
            if (!password) {
                showError('loginPassword', 'Please enter your password');
                setLoadingState('loginButton', false);
                return;
            }
            
            try {
                // Make API call to PHP backend
                const response = await fetch('./dashboard/controllers/handle_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        },
                    body: JSON.stringify({
                        username: username,
                        password: password,
                        remember: remember
                    })
                });
                
                // Parse JSON response
                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Case 1: LOGIN SUCCESSFUL
                    currentUser = data.user;
                    
                    // Show different messages based on user type
                    let welcomeMessage = data.message;
                    if (data.user.type === 'agent') {
                        welcomeMessage += ` Welcome Agent ${data.user.name}`;
                    } else {
                        welcomeMessage += ` Welcome ${data.user.name}`;
                    }
                    
                    // Add email verification reminder if needed
                    if (data.email_verification_needed) {
                        welcomeMessage += '. ' + data.verification_message;
                        showNotification(welcomeMessage, 'info', 5000);
                    } else {
                        showNotification(welcomeMessage + ' Redirecting...', 'success');
                    }
                    
                    // Store user data in localStorage for client-side reference if needed
                    try {
                        localStorage.setItem('userType', data.user.type);
                        localStorage.setItem('userName', data.user.name);
                        localStorage.setItem('userData', JSON.stringify(data.user));
                    } catch(e) {
                        // localStorage might not be available
                        console.log('Could not store user info locally');
                    }
                    
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);
                    
                } else if (data.account_inactive) {
                    // Case 2: ACCOUNT EXISTS BUT INACTIVE - Payment/subscription issue
                    
                    showNotification(data.message, 'warning');
                    
                    // Store user info for payment page
                    try {
                        localStorage.setItem('pendingUserId', data.user_id);
                        localStorage.setItem('pendingUserEmail', data.email);
                        localStorage.setItem('pendingUserName', data.username);
                    } catch(e) {
                        console.log('Could not store pending user info');
                    }
                    
                    // Redirect to payment page after a short delay
                    setTimeout(() => {
                        window.location.href = `${data.redirect}?user_id=${data.user_id}&email=${encodeURIComponent(data.email)}`;
                    }, 1500);
                    
                } else {
                    // Case 3: LOGIN FAILED - Wrong credentials or other errors
                    
                    if (data.field) {
                        // Specific field error
                        const fieldId = 'login' + data.field.charAt(0).toUpperCase() + data.field.slice(1);
                        showError(fieldId, data.message);
                        
                        // Focus on the error field
                        const errorField = document.getElementById(fieldId);
                        if (errorField) {
                            errorField.focus();
                        }
                    } else {
                        // General error
                        showNotification(data.message, 'error');
                    }
                    
                    // Optional: Add login attempt tracking for security
                    trackFailedLoginAttempt(username);
                }
                
            } catch (error) {
                // Case 4: NETWORK OR SERVER ERROR
                console.error('Login error:', error);
                
                if (error.name === 'TypeError' && error.message.includes('fetch')) {
                    // Network error
                    showNotification('Network connection problem. Please check your internet connection.', 'error');
                } else if (error.name === 'SyntaxError') {
                    // JSON parsing error
                    showNotification('Server error. Please try again later.', 'error');
                } else {
                    // Other errors
                    showNotification('Connection error. Please try again.', 'error');
                }
                
            } finally {
                setLoadingState('loginButton', false);
            }
        }

        // Helper function to track failed login attempts (optional security feature)
        function trackFailedLoginAttempt(username) {
            try {
                let attempts = JSON.parse(localStorage.getItem('failedLogins') || '{}');
                const now = Date.now();
                const oneHour = 60 * 60 * 1000;
                
                // Clean old attempts
                Object.keys(attempts).forEach(key => {
                    if (now - attempts[key].lastAttempt > oneHour) {
                        delete attempts[key];
                    }
                });
                
                // Add current attempt
                if (attempts[username]) {
                    attempts[username].count++;
                    attempts[username].lastAttempt = now;
                } else {
                    attempts[username] = { count: 1, lastAttempt: now };
                }
                
                localStorage.setItem('failedLogins', JSON.stringify(attempts));
                
                // Show warning after multiple failed attempts
                if (attempts[username].count >= 3) {
                    showNotification('Multiple failed login attempts. Please make sure you are using the correct credentials.', 'warning');
                }
                
            } catch (e) {
                // localStorage not available
                console.log('Could not track failed login attempts');
            }
        }

        async function handleSignupStep2(event) {
            event.preventDefault();
            if (isLoading) return;

            clearErrors();
            setLoadingState('step2Button', true);

            // Get form data including duration
            const formData = {
                name: document.getElementById('signupName').value,
                email: document.getElementById('signupEmail').value,
                password: document.getElementById('signupPassword').value,
                confirmPassword: document.getElementById('confirmPassword').value,
                selectedPlan: selectedPlan,
                duration: selectedDuration,
                monthlyPrice: signupData.planPrice, 
                totalAmount: signupData.planPrice * selectedDuration,
                discount: durationDiscounts[selectedDuration] 
            };

            // Check if terms are accepted
            const termsAccepted = document.getElementById('acceptTerms').checked;
            if (!termsAccepted) {
                showError('terms', 'You must accept the terms and conditions');
                setLoadingState('step2Button', false);
                return;
            }

            const validationErrors = validateSignupStep2(formData);

            if (validationErrors.length > 0) {
                validationErrors.forEach(({ field, message }) => showError(field, message));
                setLoadingState('step2Button', false);
                return;
            }

            try {
                // Check if it's a free plan
                if (formData.selectedPlan === 'starter' || formData.planPrice === 0) {
                    await handleFreePlan(formData);
                    return;
                }

                // Calculate total amount based on duration
                const discount = durationDiscounts[formData.duration];
                const basePrice = plans[formData.selectedPlan].basePrice;
                const monthlyPrice = basePrice * (1 - discount/100);
                const totalAmount = Math.round(monthlyPrice * formData.duration);
                
                console.log('Processing payment for plan:', formData.selectedPlan, 'Amount:', totalAmount);
                
                // For paid plans, create user and proceed to payment
                const response = await fetch('./dashboard/controllers/create_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        ...formData,
                        monthlyPrice: Math.round(monthlyPrice),
                        totalAmount: totalAmount,
                        discount: discount
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    // Save user_id for payment processing
                    signupData = { 
                        ...formData, 
                        user_id: result.user_id,
                        monthlyPrice: Math.round(monthlyPrice),
                        totalAmount: totalAmount
                    };
                    
                    console.log('User created successfully, processing payment...');
                    
                    // Call backend to start payment
                    const paymentResponse = await fetch('./dashboard/controllers/process_payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            user_id: result.user_id,
                            plan: formData.selectedPlan,
                            plan_name: formData.planName || formData.selectedPlan,
                            duration: formData.duration,
                            monthly_amount: Math.round(monthlyPrice),
                            total_amount: totalAmount,
                            name: formData.name,
                            email: formData.email
                        })
                    });

                    if (!paymentResponse.ok) {
                        throw new Error(`Payment HTTP error! status: ${paymentResponse.status}`);
                    }

                    const paymentResult = await paymentResponse.json();
                    console.log('Payment response:', paymentResult);

                    if (paymentResult.success && paymentResult.form_html) {
                        // Show success message briefly
                        showNotification('Redirecting to secure payment...', 'info');
                        
                        // Wait a moment then create payment form
                        setTimeout(() => {
                            redirectToPayment(paymentResult.form_html, paymentResult.transaction_id);
                            
                            // Alternative: Use new window method (uncomment if preferred)
                            // createPaymentWindow(paymentResult.form_html, paymentResult.transaction_id);
                        }, 1000);
                        
                    } else {
                        throw new Error(paymentResult.message || 'Error generating payment form');
                    }
                } else {
                    throw new Error(result.message || 'Error creating user');
                }

            } catch (error) {
                console.error('Signup error:', error);
                
                let errorMessage = 'An unexpected error occurred.';
                
                if (error.message.includes('network') || error.message.includes('Network') || error.message.includes('fetch')) {
                    errorMessage = "Connection problem. Please check your internet connection.";
                } else if (error.message.includes('HTTP error')) {
                    errorMessage = "Server error. Please try again in a few moments.";
                } else if (error.message) {
                    errorMessage = error.message;
                }
                
                showNotification(errorMessage, 'error');
                setLoadingState('step2Button', false);
            }
        }


        async function handleFreePlan(formData) {
            try {
                // Create user for free plan
                const response = await fetch('./dashboard/controllers/create_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    // Activate free plan on backend
                    const activationResponse = await fetch('./dashboard/controllers/activate_free_plan.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            user_id: result.user_id,
                            plan: formData.selectedPlan
                        })
                    });

                    const activationResult = await activationResponse.json();

                    if (activationResult.success) {
                        // Show success message
                        showNotification('Congratulations! Your free plan has been successfully activated.', 'success');
                        
                        // Wait 2 seconds before redirecting to login
                        setTimeout(() => {
                            window.location.href = './lg'; // Adjust path as needed
                        }, 2000);
                    } else {
                        showNotification(activationResult.message || 'Error activating free plan', 'error');
                    }
                } else {
                    showNotification(result.message || 'Error creating account', 'error');
                }

            } catch (error) {
                console.error('Free plan activation error:', error);
                showNotification('Error activating free plan', 'error');
            }
        }

        /**
         * Create payment form and handle submission to CMI gateway
        */

        function redirectToPayment(formHtml, transactionId) {
            
            try {
                // Create a temporary container
                const tempContainer = document.createElement('div');
                tempContainer.innerHTML = formHtml;
                
                // Find the form in the HTML
                const form = tempContainer.querySelector('form');
                
                if (!form) {
                    throw new Error('Payment form not found in HTML');
                }
                
                // Get form action and method
                const actionUrl = form.getAttribute('action');
                const method = form.getAttribute('method') || 'POST';
                
                console.log('Form action URL:', actionUrl);
                console.log('Form method:', method);
                
                // Get all form data
                const formData = new FormData();
                const inputs = form.querySelectorAll('input[type="hidden"]');
                
                inputs.forEach(input => {
                    formData.append(input.name, input.value);
                    console.log('Form field:', input.name, '=', input.value);
                });
                
                // Show loading message
                showPaymentRedirectMessage(transactionId);
                
                // Method 1: Direct form submission (most reliable)
                submitDirectForm(actionUrl, formData, transactionId);
                
            } catch (error) {
                console.error('Error in payment redirect:', error);
                showNotification('Error redirecting to payment: ' + error.message, 'error');
                setLoadingState('step2Button', false);
            }
        }

        /**
         * Submit form directly by creating and submitting it
        */
        function submitDirectForm(actionUrl, formData, transactionId) {
            // Replace current page content with loading screen
            document.body.innerHTML = `
                <div style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 99999;
                    font-family: Arial, sans-serif;
                ">
                    <div style="
                        background: white;
                        padding: 40px;
                        border-radius: 15px;
                        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                        text-align: center;
                        max-width: 400px;
                        width: 90%;
                    ">
                        <div style="
                            width: 60px;
                            height: 60px;
                            border: 4px solid #f3f3f3;
                            border-top: 4px solid #007bff;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            margin: 0 auto 20px;
                        "></div>
                        
                        <h2 style="color: #333; margin-bottom: 15px; font-size: 24px;">
                            Redirecting to Payment
                        </h2>
                        
                        <p style="color: #666; font-size: 16px; margin-bottom: 10px;">
                            You are being redirected to the secure CMI payment gateway...
                        </p>
                        
                        <p style="color: #999; font-size: 12px; margin-bottom: 20px;">
                            Transaction: ${transactionId}
                        </p>
                        
                        <div style="
                            background: #f8f9fa;
                            padding: 15px;
                            border-radius: 8px;
                            font-size: 14px;
                            color: #666;
                        ">
                            <strong>🔒 100% Secure Payment</strong><br>
                            Your data is protected by SSL encryption
                        </div>
                    </div>
                </div>
                
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `;
            
            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = actionUrl;
            form.style.display = 'none';
            
            // Add all form fields
            for (let [key, value] of formData.entries()) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
            
            // Add form to body and submit
            document.body.appendChild(form);
            
            console.log('Submitting form to:', actionUrl);
            
            // Submit immediately
            setTimeout(() => {
                form.submit();
            }, 1000); // 1 second delay to show the loading screen
        }

        function showPaymentRedirectMessage(transactionId) {
            showNotification(`Redirecting to secure payment... (${transactionId})`, 'info');
        }

        /**
         * Cancel payment and clean up
        */
       
        function cancelPayment() {
            const overlay = document.getElementById('payment-overlay');
            if (overlay) {
                overlay.remove();
            }
            
            // Reset loading state
            setLoadingState('step2Button', false);
            
            // Optional: Call backend to mark transaction as cancelled
            if (window.currentTransactionId) {
                fetch('./dashboard/controllers/cancel_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        transaction_id: window.currentTransactionId
                    })
                }).catch(error => console.error('Error cancelling payment:', error));
            }
            
            showNotification('Payment cancelled', 'info');
        }

        async function handleForgotPassword(event) {
            event.preventDefault();
            if (isLoading) return;
            
            clearErrors();
            setLoadingState('forgotButton', true);
            
            const email = document.getElementById('forgotUsername').value;
            
            if (!email.trim()) {
                showError('forgotUsername', 'Please enter your email address');
                setLoadingState('forgotButton', false);
                return;
            }
            
            if (!validateEmail(email)) {
                showError('forgotUsername', 'Please enter a valid email address');
                setLoadingState('forgotButton', false);
                return;
            }
            
            try {
                const response = await fetch('./dashboard/controllers/forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        email: email
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Clear the form
                    document.getElementById('forgotUsername').value = '';
                    setTimeout(() => switchForm('login'), 3000);
                } else {
                    showNotification(data.message, 'error');
                }
                
            } catch (error) {
                console.error('Forgot password error:', error);
                showNotification('Network error. Please check your connection and try again.', 'error');
            } finally {
                setLoadingState('forgotButton', false);
            }
        }

        // Validation helper functions
        function validateSignupStep2(data) {
            const errors = [];
            
            if (!data.name.trim()) {
                errors.push({ field: 'signupName', message: 'Please enter your full name' });
            }
            
            if (!data.email.trim()) {
                errors.push({ field: 'signupEmail', message: 'Please enter your email address' });
            } else if (!validateEmail(data.email)) {
                errors.push({ field: 'signupEmail', message: 'Please enter a valid email address' });
            }
            
            if (!data.password) {
                errors.push({ field: 'signupPassword', message: 'Please enter a password' });
            } else if (!validatePassword(data.password)) {
                errors.push({ field: 'signupPassword', message: 'Password must be at least 8 characters' });
            }
            
            if (!data.confirmPassword) {
                errors.push({ field: 'confirmPassword', message: 'Please confirm your password' });
            } else if (data.password !== data.confirmPassword) {
                errors.push({ field: 'confirmPassword', message: 'Passwords do not match' });
            }
            
            return errors;
        }

        // Simulate API call
        function simulateApiCall(delay = 1500) {
            return new Promise((resolve) => {
                setTimeout(resolve, delay);
            });
        }

        // Initialize quick plan selector
        function initializeQuickPlanSelector() {
            const quickPlanButtons = document.querySelectorAll('.quick-plan-btn');
            quickPlanButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Update active state
                    quickPlanButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        }

        function goToPlanByType(planType) {
            const planIndexMap = {
                'starter': 0,
                'professional': 1,
                'growth': 2,
                'business': 3
            };
            
            if (planIndexMap.hasOwnProperty(planType)) {
                goToPlan(planIndexMap[planType]);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Your existing initialization code
            
            // Initialize plan prices
            setTimeout(() => {
                updateAllPlanPrices();
            }, 100);
            
            // When carousel changes, update duration selector visibility
            const originalUpdateCarousel = updateCarousel;
            updateCarousel = function() {
                originalUpdateCarousel();
                
                // Get the currently visible plan
                const planKeys = Object.keys(plans);
                const currentPlanId = planKeys[currentPlanIndex];
                
                // Update duration selector visibility
                toggleDurationSelector(currentPlanId);
            };
        });

    </script>
</body>
</html>