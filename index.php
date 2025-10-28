<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Obgecom est votre partenaire digital au Maroc : solutions e-commerce, gestion des commandes, sites web sur mesure et applications personnalisées pour booster votre business en ligne.">
    <meta name="keywords" content="Obgecom, e-commerce, gestion des commandes, site web sur mesure, application personnalisée, Maroc">
    <title>OBG - Built for Sellers</title>
    <!-- Font Awesome 6 (latest) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #5f34d9;
            --primary-dark: #4f2db0;
            --secondary: #8b5cf6;
            --accent: #a855f7;
            --text-primary: #2f2264;
            --text-secondary: #6b7280;
            --bg-gradient: linear-gradient(135deg, #5f34d9 0%, #e1d7f5 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
    </style>

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

</head>
<body>
    <div class="bg-decoration"></div>
    <div class="bg-decoration"></div>
    
    <div class="container">
        <style>

            /* Main Content */
            .main-hero {
                display: flex;
                gap: 60px;
                align-items: center;
                padding: 140px 60px 60px;
                min-height: 100vh;
                max-width: 100%;
                margin: 0 auto;
                background: var(--bg-gradient);
            }

            .content-left {
                flex: 1;
                max-width: 50%;
                animation: slideInLeft 1s ease-out;
            }

            .content-left h1 {
                font-size: 3.5rem;
                font-weight: 900;
                line-height: 1.1;
                margin-bottom: 30px;
                color: white;
                letter-spacing: -1px;
                background: linear-gradient(135deg, #ffffff, #e0e7ff);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .content-left p {
                font-size: 1.3rem;
                line-height: 1.6;
                margin-bottom: 40px;
                color: rgba(255, 255, 255, 0.9);
            }

            .feature-badges {
                display: flex;
                gap: 1rem;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }

            .badge {
                background: var(--glass-bg);
                backdrop-filter: blur(10px);
                border: 1px solid var(--glass-border);
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 25px;
                font-size: 0.9rem;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                animation: fadeInUp 1s ease-out;
            }

            .search-container {
                display: flex;
                background: var(--glass-bg);
                backdrop-filter: blur(20px);
                border: 1px solid var(--glass-border);
                border-radius: 5px;
                padding: 4px;
                max-width: 100%;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                margin-bottom: 40px;
                transition: all 0.3s ease;
            }

            .search-container:hover {
                transform: translateY(-2px);
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            }

            .search-input {
                flex: 1;
                background: rgba(255, 255, 255, 0.9);
                border: none;
                border-radius: 4px;
                padding: 8px 6px;
                color: var(--text-primary);
                margin-right: .8em;
                font-size: 16px;
                outline: none;
            }

            .search-button {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                border: none;
                padding: 16px 32px;
                border-radius: 40px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 15px;
                box-shadow: 0 4px 15px rgba(95, 52, 217, 0.3);
            }

            .search-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(95, 52, 217, 0.4);
            }

            /* Dashboard Container */
            .dashboard-container {
                max-width: 60%;
                width: 100%;
                max-height: 600px;
                overflow: hidden;
                border-radius: 0px;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
                background: white;
            }

            .french-dashboard {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(25px);
                overflow-x: auto;
                max-height: 500px;
                overflow-y: auto;
            }

            /* Table Styles */
            .dashboard-table {
                width: 100%;
                border-collapse: collapse;
            }

            .dashboard-table thead {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .dashboard-table th {
                padding: 16px 12px;
                text-align: center;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                border-right: 1px solid rgba(255, 255, 255, 0.2);
            }

            .dashboard-table th:last-child {
                border-right: none;
            }

            .dashboard-table tbody tr {
                transition: all 0.3s ease;
                border-bottom: 1px solid #f3f4f6;
            }

            .dashboard-table tbody tr:nth-child(even) {
                background: #fafafa;
            }

            .dashboard-table tbody tr:hover {
                background: rgba(95, 52, 217, 0.05);
                transform: translateX(5px);
            }

            .dashboard-table td {
                padding: 16px 12px;
                text-align: center;
                font-size: 13px;
                color: #374151;
                vertical-align: middle;
            }

            /* Column Specific Widths */
            .col-checkbox { width: 50px; }
            .col-client { width: 180px; text-align: left; }
            .col-whatsapp { width: 60px; }
            .col-city { width: 120px; }
            .col-articles { width: 80px; }
            .col-amount { width: 100px; }
            .col-status { width: 120px; }
            .col-action { width: 120px; }
            .col-assigned { width: 120px; }
            .col-user { width: 100px; }

            /* Checkbox Styling */
            .order-checkbox {
                width: 18px;
                height: 18px;
                accent-color: var(--primary);
                cursor: pointer;
            }

            /* Client Info Styling */
            .client-info {
                text-align: center;
            }

            .client-name {
                font-weight: 600;
                color: #111827;
                font-size: 14px;
                margin-bottom: 2px;
            }

            /* WhatsApp Icon */
            .whatsapp-icon {
                width: 30px;
                height: 30px;
                background: #25d366;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 14px;
                margin: 0 auto;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .whatsapp-icon:hover {
                transform: scale(1.1);
                box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
            }

            /* City Badge */
            .city-badge {
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: white;
                padding: 6px 12px;
                border-radius: 15px;
                font-size: 12px;
                font-weight: 600;
                text-align: center;
                min-width: 30px;
                display: inline-block;
            }

            /* Amount Styling */
            .amount {
                font-weight: 700;
                color: #9c80fd;
                text-align: center;
                font-size: 14px;
                line-height: 1.3;
            }

            /* Status Badges */
            .status-badge {
                padding: 8px 12px;
                border-radius: 15px;
                font-size: 11px;
                font-weight: 600;
                text-align: center;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: inline-block;
            }

            .status-en-attente {
                background: linear-gradient(135deg, #fef3c7, #fde68a);
                color: #d97706;
                border: 1px solid #f59e0b;
            }

            .status-non-soumis {
                background: linear-gradient(135deg, #fee2e2, #fecaca);
                color: #dc2626;
                border: 1px solid #ef4444;
            }

            /* Action Button */
            .action-button {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                border: none;
                padding: 10px 16px;
                border-radius: 10px;
                font-size: 12px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .action-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(95, 52, 217, 0.4);
            }

            /* Action Select */
            .action-select {
                background: white;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                padding: 8px 12px;
                font-size: 12px;
                color: #374151;
                cursor: pointer;
                transition: all 0.3s ease;
                font-weight: 500;
                width: 100%;
                max-width: 150px;
            }

            .action-select:hover {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(95, 52, 217, 0.1);
            }

            .action-select:focus {
                outline: none;
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(95, 52, 217, 0.2);
            }

            /* Connection Line */
            .connection-line {
                height: 4px;
                background: linear-gradient(90deg, transparent, var(--primary), var(--secondary), transparent);
                margin: 0;
                animation: pulse 2s infinite;
            }

            /* Delivery Partners */
            .delivery-partners {
                display: flex;
                justify-content: space-around;
                align-items: center;
                padding: 30px 20px;
                background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            }

            .partner-circle {
                width: 90px;
                height: 90px;
                border-radius: 50%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                color: white;
                position: relative;
                text-align: center;
                line-height: 1.2;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                cursor: pointer;
            }

            .partner-circle:hover {
                transform: translateY(-10px) scale(1.05);
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            }

            .partner-circle i {
                font-size: 24px;
                margin-bottom: 5px;
            }

            .partner-cathedis {
                background: linear-gradient(135deg, #dc2626, #ef4444);
            }

            .partner-dzon {
                background: linear-gradient(135deg, #ea580c, #f97316);
            }

            .partner-sendit {
                background: linear-gradient(135deg, #1e40af, #3b82f6);
            }

            .partner-power {
                background: linear-gradient(135deg, #10b981, #34d399);
            }

            .partner-delivery {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
            }

            .partner-text {
                font-size: 10px;
                margin-top: 2px;
                font-weight: 600;
            }

            .discount-badge {
                position: absolute;
                bottom: -8px;
                left: 50%;
                transform: translateX(-50%);
                background: linear-gradient(135deg, #ffffff, #f8fafc);
                color: #dc2626;
                font-size: 9px;
                font-weight: 800;
                padding: 3px 8px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                border: 1px solid #fecaca;
            }

            .english-dashboard {

                scroll-behavior: smooth;
                overflow-x: auto;
                scrollbar-width: thin;
            }

            /* Animations */
            @keyframes slideInLeft {
                from {
                    opacity: 0;
                    transform: translateX(-50px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }

            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(50px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.7;
                }
            }

            /* Responsive Design */
            @media (max-width: 1200px) {
                .main-hero {
                    gap: 40px;
                    padding: 140px 40px 40px;
                }

                .content-left h1 {
                    font-size: 3rem;
                }
            }

            @media (max-width: 768px) {
                .main-hero {
                    flex-direction: column;
                    gap: 40px;
                    padding: 120px 20px 40px;
                    text-align: center;
                }

                .content-left {
                    max-width: 100%;
                }

                .content-left h1 {
                    font-size: 2.5rem;
                }

                .delivery-partners {
                    flex-wrap: wrap;
                    gap: 20px;
                    padding: 20px 10px;
                }

                .partner-circle {
                    width: 70px;
                    height: 70px;
                }

                .partner-circle i {
                    font-size: 18px;
                }

                .partner-text {
                    font-size: 9px;
                }
            }

            @media (max-width: 480px) {
                .nav-container {
                    padding: 0 1rem;
                }

                .content-left h1 {
                    font-size: 2rem;
                }

                .content-left p {
                    font-size: 1.1rem;
                }

                .main-hero {
                    padding: 100px 10px 30px;
                }

                .search-container {
                    padding: 0px;
                }

                .dashboard-container {

                    max-width: 100% !important;
                }
            }
        </style>

        <?php require_once('./assets/navbar.php'); ?>

        <script>
            // Header scroll effect
            window.addEventListener('scroll', function() {
                const header = document.getElementById('navbar'); // Changed from 'main-header' to 'navbar'
                const scrollPosition = window.scrollY;
                
                if (scrollPosition > 400) {
                    header.style.backgroundColor = '#FFF';
                } else {
                    header.style.backgroundColor = '#FFF';
                }
            });

            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Intersection Observer for animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observe all sections
            document.addEventListener('DOMContentLoaded', () => {
                const sections = document.querySelectorAll('section');
                sections.forEach(section => {
                    observer.observe(section);
                });
            });
        </script>

        <main class="main-hero">
            <div class="content-left">
                <div class="feature-badges">
                    <div class="badge">
                        <i class="fas fa-magic"></i>
                        Automation
                    </div>
                    <div class="badge">
                        <i class="fas fa-handshake"></i>
                        Trusted Partners
                    </div>
                    <div class="badge">
                        <i class="fas fa-bolt"></i>
                        Fast Setup
                    </div>
                </div>

                
                <h1>BUILT FOR SELLERS WHO DREAM BIGGER</h1>
                <p>From checkout to delivery, OBGecom streamlines every post-order step with cutting-edge AI, real-time analytics, and seamless integrations—freeing your time and fueling exponential growth in 2025.</p>
                
                <div class="search-container">
                    <input type="text" id="emailInput" class="search-input" placeholder="Enter your email to start your journey">
                    <a class="search-button" href="#" onclick="startTrial(event)">
                        <i class="fas fa-rocket" style="margin-right: 8px;"></i>
                        Start Free Trial
                    </a>
                </div>

                <script>
                function startTrial(event) {
                    event.preventDefault(); // prevent default link action
                    const email = document.getElementById('emailInput').value.trim();

                    if (email === "") {
                        alert("Please enter your email before continuing.");
                        return;
                    }

                    // redirect with email included
                    window.location.href = "./lg?plan=starter&email=" + encodeURIComponent(email);
                }
                </script>

            </div>

            <style>
                .dashboard-table td {
                    text-align: center;
                }
            </style>

            <div class="dashboard-container">
                
                <div class="english-dashboard">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th class="col-checkbox"></th>
                                <th class="col-client">CLIENT</th>
                                <th class="col-whatsapp">PHONE</th>
                                <th class="col-city">CITY</th>
                                <th class="col-articles">ITEMS</th>
                                <th class="col-amount">AMOUNT</th>
                                <th class="col-status">STATUS</th>
                                <th class="col-action">ACTION</th>
                                <th class="col-user">STORE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="checkbox" class="order-checkbox"></td>
                                <td class="col-client"><div class="client-info"><div class="client-name">Youssef</div></div></td>
                                <td><div class="whatsapp-icon" title="+212 6XX XXX XXX"><i class="fab fa-whatsapp"></i></div></td>
                                <td>Casablanca</td>
                                <td><div class="city-badge">2</div></td>
                                <td><div class="amount">120.00<br>MAD</div></td>
                                <td><div class="status-badge status-en-attente">Pending</div></td>
                                <td>
                                    <select class="action-select">
                                        <option value="confirmed">Confirmée</option>
                                        <option value="no-answer">Pas de réponse</option>
                                        <option value="busy">Occupé</option>
                                        <option value="cancelled">Annulée</option>
                                        <option value="double-order">Double commande</option>
                                        <option value="unreachable">Injoignable</option>
                                    </select>
                                </td>
                                <td>Atlas Shop</td>
                            </tr>

                            <tr>
                                <td><input type="checkbox" class="order-checkbox"></td>
                                <td class="col-client"><div class="client-info"><div class="client-name">Fatima</div></div></td>
                                <td><div class="whatsapp-icon" title="+212 6XX XXX XXX"><i class="fab fa-whatsapp"></i></div></td>
                                <td>Rabat</td>
                                <td><div class="city-badge">1</div></td>
                                <td><div class="amount">75.00<br>MAD</div></td>
                                <td><div class="status-badge status-confirmee">Confirmed</div></td>
                                <td><button class="action-button">Ship</button></td>
                                <td>Rabat Deals</td>
                            </tr>

                            <tr>
                                <td><input type="checkbox" class="order-checkbox"></td>
                                <td class="col-client"><div class="client-info"><div class="client-name">Hicham</div></div></td>
                                <td><div class="whatsapp-icon" title="+212 6XX XXX XXX"><i class="fab fa-whatsapp"></i></div></td>
                                <td>Marrakech</td>
                                <td><div class="city-badge">3</div></td>
                                <td><div class="amount">210.00<br>MAD</div></td>
                                <td><div class="status-badge status-annulee">Cancelled</div></td>
                                <td>
                                    <select class="action-select">
                                        <option value="confirmed">Confirmée</option>
                                        <option value="no-answer">Pas de réponse</option>
                                        <option value="busy">Occupé</option>
                                        <option value="cancelled" selected>Annulée</option>
                                        <option value="double-order">Double commande</option>
                                        <option value="unreachable">Injoignable</option>
                                    </select>
                                </td>
                                <td>Bazaar</td>
                            </tr>

                            <tr>
                                <td><input type="checkbox" class="order-checkbox"></td>
                                <td class="col-client"><div class="client-info"><div class="client-name">Salma</div></div></td>
                                <td><div class="whatsapp-icon" title="+212 6XX XXX XXX"><i class="fab fa-whatsapp"></i></div></td>
                                <td>Agadir</td>
                                <td><div class="city-badge">1</div></td>
                                <td><div class="amount">50.00<br>MAD</div></td>
                                <td><div class="status-badge status-en-attente">Pending</div></td>
                                <td>
                                    <select class="action-select">
                                        <option value="confirmed">Confirmée</option>
                                        <option value="no-answer">Pas de réponse</option>
                                        <option value="busy">Occupé</option>
                                        <option value="cancelled">Annulée</option>
                                        <option value="double-order">Double commande</option>
                                        <option value="unreachable">Injoignable</option>
                                    </select>
                                </td>
                                <td>TechSouk</td>
                            </tr>

                            <tr>
                                <td><input type="checkbox" class="order-checkbox"></td>
                                <td class="col-client"><div class="client-info"><div class="client-name">Omar</div></div></td>
                                <td><div class="whatsapp-icon" title="+212 6XX XXX XXX"><i class="fab fa-whatsapp"></i></div></td>
                                <td>Tanger</td>
                                <td><div class="city-badge">4</div></td>
                                <td><div class="amount">300.00<br>MAD</div></td>
                                <td><div class="status-badge status-confirmee">Confirmed</div></td>
                                <td><button class="action-button">Ship</button></td>
                                <td>Joudbee</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="connection-line"></div>

                <div class="delivery-partners">
                    <div class="partner-circle partner-cathedis">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">Cathedis</div>
                        <div class="discount-badge">50% OFF</div>
                    </div>
                    <div class="partner-circle partner-dzon">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">DZON Express</div>
                        <div class="discount-badge">50% OFF</div>
                    </div>
                    <div class="partner-circle partner-sendit">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">SendIt</div>
                        <div class="discount-badge">50% OFF</div>
                    </div>
                    <div class="partner-circle partner-power">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">Power</div>
                        <div class="discount-badge">50% OFF</div>
                    </div>
                    <div class="partner-circle partner-delivery">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">Global+</div>
                    </div>
                </div>

            </div>

        </main>

        <style>

            .why-obg-section {
                padding: 0px 0px;
                max-width: 100%;
                margin: 0 auto;
            }

            .section-header {
                text-align: center;
                margin-bottom: 60px;
            }

            .section-title {
                font-size: 48px;
                font-weight: 700;
                color: #4a3c8a;
                margin-bottom: 16px;
                letter-spacing: 2px;
                margin-top: 1em;
            }

            .section-subtitle {
                font-size: 20px;
                color: #6b7280;
                font-weight: 400;
            }

            .features-container {
                display: flex;
                flex-direction: column;
                gap: 0;
            }

            .feature-card {
                background: white;
                display: grid;
                grid-template-columns: 1fr 1fr;
                min-height: 400px;
                align-items: center;
            }

            .feature-card:nth-child(odd) {
                background: linear-gradient(135deg, #e9d5ff 0%, #ddd6fe 50%, #c4b5fd 100%);
            }

            .feature-card:nth-child(even) .feature-content {
                order: 2;
            }

            .feature-content {
                padding: 60px;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .feature-title {
                font-size: 36px;
                font-weight: 800;
                color: #4a3c8a;
                margin-bottom: 24px;
                text-transform: uppercase;
                letter-spacing: 1px;
                line-height: 1.2;
            }

            .feature-description {
                font-size: 26px;
                color: #4a3c8a;
                line-height: 1.6;
                margin-bottom: 32px;
            }

            .feature-visual {
                padding: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }

            /* Process Flow for First Feature */
            .process-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 32px;
            }

            .process-flow {
                display: flex;
                align-items: center;
                gap: 16px;
                margin-bottom: 24px;
            }

            .process-step {
                display: flex;
                flex-direction: column;
                align-items: center;
                position: relative;
            }

            .process-icon {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: #4a3c8a;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 32px;
                position: relative;
                box-shadow: 0 4px 12px rgba(74, 60, 138, 0.3);
            }

            .process-icon.completed::after {
                content: '✓';
                position: absolute;
                top: -8px;
                right: -8px;
                background: #22c55e;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                font-size: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                border: 3px solid white;
                font-weight: bold;
            }

            .process-arrow {
                font-size: 24px;
                color: #9ca3af;
                margin: 0 12px;
            }

            .process-final {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #6b7280;
                font-size: 32px;
            }

            /* Dashboard Visual */
            .dashboard-mock {
                background: white;
                border-radius: 12px;
                box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
                overflow: hidden;
                width: 500px;
                border: 1px solid #e5e7eb;
            }

            .dashboard-header {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
                height: 60px;
                display: flex;
                align-items: center;
                padding: 0 24px;
                position: relative;
                justify-content: space-between;
            }

            .window-controls {
                display: flex;
                gap: 8px;
            }

            .window-dot {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.4);
            }

            .dashboard-nav {
                background: #6366f1;
                padding: 16px 24px;
                display: flex;
                gap: 32px;
                font-size: 14px;
                color: white;
                font-weight: 500;
            }

            .nav-item {
                opacity: 0.7;
                cursor: pointer;
                transition: opacity 0.2s;
            }

            .nav-item.active {
                opacity: 1;
                font-weight: 600;
            }

            .dashboard-content {
                padding: 24px;
                background: white;
            }

            .order-row {
                display: grid;
                grid-template-columns: 60px 2fr 1fr 1fr 1fr 100px;
                align-items: center;
                padding: 16px 0;
                border-bottom: 1px solid #f3f4f6;
                gap: 16px;
            }

            .order-row:last-child {
                border-bottom: none;
            }

            .customer-avatar {
                width: 48px;
                height: 48px;
                border-radius: 8px;
                background: linear-gradient(45deg, #f59e0b, #f97316);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 700;
                font-size: 16px;
            }

            .order-info h4 {
                font-size: 15px;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 4px;
            }

            .order-info span {
                font-size: 13px;
                color: #6b7280;
            }

            .status-badge {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
            }

            .status-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
            }

            .status-delivered .status-dot { background: #10b981; }
            .status-processing .status-dot { background: #3b82f6; }
            .status-pending .status-dot { background: #f59e0b; }

            .price {
                font-weight: 700;
                color: #1f2937;
                font-size: 15px;
            }

            .date {
                color: #6b7280;
                font-size: 13px;
            }

            .action-btn {
                background: #4f46e5;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                transition: background 0.2s;
            }

            .action-btn:hover {
                background: #2f2264;
            }

            .carrier-badges {
                display: flex;
                gap: 12px;
                justify-content: center;
                margin-top: 24px;
            }

            .carrier-badge {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 10px;
                font-weight: bold;
                position: relative;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            }

            .carrier-badge:nth-child(1) { background: #dc2626; }
            .carrier-badge:nth-child(2) { background: #d97706; }
            .carrier-badge:nth-child(3) { background: #059669; }
            .carrier-badge:nth-child(4) { background: #7c2d12; }
            .carrier-badge:nth-child(5) { background: #1d4ed8; }

            /* Analytics Feature */
            .analytics-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 32px;
            }

            .analytics-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                margin-bottom: 24px;
            }

            .analytics-card {
                background: rgba(255, 255, 255, 0.9);
                padding: 24px 16px;
                border-radius: 12px;
                text-align: center;
                border: 1px solid rgba(255, 255, 255, 0.5);
                backdrop-filter: blur(10px);
            }

            .analytics-icon {
                font-size: 36px;
                color: #4f46e5;
                margin-bottom: 12px;
            }

            .analytics-label {
                font-size: 12px;
                color: #6b7280;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .metrics-dashboard {
                background: white;
                border-radius: 12px;
                padding: 32px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
                width: 480px;
                border: 1px solid #e5e7eb;
            }

            .metrics-header {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 12px;
                margin-bottom: 24px;
                font-size: 11px;
                color: #6b7280;
                text-align: center;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .vip-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 24px;
                margin-bottom: 24px;
                text-align: center;
            }

            .vip-card {
                padding: 20px;
            }

            .vip-icon {
                font-size: 32px;
                margin-bottom: 12px;
            }

            .vip-label {
                font-size: 12px;
                color: #6b7280;
                font-weight: 600;
            }

            .revenue-section {
                border-top: 1px solid #f3f4f6;
                padding-top: 20px;
            }

            .revenue-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
            }

            .revenue-label {
                font-size: 14px;
                color: #6b7280;
                font-weight: 500;
            }

            .revenue-amount {
                font-size: 16px;
                font-weight: 700;
                color: #1f2937;
            }

            /* Team Performance Feature */
            .team-container {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 40px;
                padding: 60px 40px;
            }

            .main-user {
                width: 100px;
                height: 100px;
                border-radius: 50%;
                background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                color: #6b7280;
                border: 4px solid white;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            }

            .team-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .team-member {
                width: 70px;
                height: 70px;
                border-radius: 12px;
                background: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 28px;
                color: #6b7280;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                border: 1px solid #e5e7eb;
            }

            .performance-chart {
                width: 100px;
                height: 100px;
                background: white;
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                color: #22c55e;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
                border: 1px solid #e5e7eb;
            }

            /* Responsive Design */
            @media (max-width: 1024px) {
                .feature-card {
                    grid-template-columns: 1fr;
                    min-height: auto;
                }

                .feature-card:nth-child(even) .feature-content {
                    order: 0;
                }

                .dashboard-mock {
                    width: 100%;
                    max-width: 450px;
                }

                .metrics-dashboard {
                    width: 100%;
                    max-width: 400px;
                }

                .team-container {
                    flex-direction: column;
                    gap: 24px;
                }
            }

            @media (max-width: 768px) {
                .section-title {
                    font-size: 36px;
                }

                .feature-content {
                    padding: 40px 10px;
                }

                .feature-title {
                    font-size: 24px;
                }

                .feature-description {
                    font-size: 16px;
                    padding: 0 .5em;
                }

                .feature-visual {
                    padding: 20px;
                    padding-top: 0px;
                }

                .process-flow {
                    flex-wrap: wrap;
                    justify-content: center;
                    gap: 0px;
                }

                .process-icon, .process-final {
                    width: 60px;
                    height: 60px;
                    font-size: 24px;
                }

                .analytics-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
        </style>

        <style>

            /* Base improvements for better scaling */
            .section-title {
                font-size: clamp(32px, 6vw, 48px);
                margin-bottom: clamp(12px, 2vw, 16px);
            }

            .section-subtitle {
                font-size: clamp(16px, 3vw, 20px);
            }

            .feature-title {
                font-size: clamp(24px, 5vw, 36px);
                margin-bottom: clamp(16px, 3vw, 24px);
            }

            .feature-description {
                font-size: clamp(18px, 3.5vw, 26px);
                margin-bottom: clamp(24px, 4vw, 32px);
            }

            .feature-content {
                padding: clamp(30px, 6vw, 60px);
            }

            .feature-visual {
                padding: clamp(20px, 5vw, 40px);
            }

            /* Tablet Improvements (1024px and below) */
            @media (max-width: 1024px) {
                .feature-card {
                    grid-template-columns: 1fr;
                    min-height: auto;
                    margin-bottom: 2rem;
                }

                .feature-card:nth-child(even) .feature-content {
                    order: 0;
                }

                .dashboard-mock {
                    width: 100%;
                    max-width: 500px;
                }

                .metrics-dashboard {
                    width: 100%;
                    max-width: 480px;
                }

                .team-container {
                    flex-direction: column;
                    gap: 24px;
                    padding: 40px 20px;
                }

                .feature-visual {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }

                .feature-visual img {
                    max-width: 90%;
                    height: auto;
                }
            }

            /* Large Mobile (768px and below) */
            @media (max-width: 768px) {
                .why-obg-section {
                    padding: 0 15px;
                }

                .section-header {
                    margin-bottom: clamp(40px, 8vw, 60px);
                }

                .feature-content {
                    padding: clamp(25px, 5vw, 40px) clamp(15px, 4vw, 25px);
                    text-align: center;
                }

                .feature-visual {
                    padding: clamp(15px, 4vw, 25px);
                    padding-top: 0;
                }

                /* Process Flow Mobile Optimization */
                .process-flow {
                    flex-direction: row;
                    align-items: center;
                    gap: 15px;
                }

                .process-arrow {
                    transform: rotate(90deg);
                    margin: 5px 0;
                    font-size: 18px;
                }

                .process-icon, .process-final {
                    width: clamp(50px, 12vw, 70px);
                    height: clamp(50px, 12vw, 70px);
                    font-size: clamp(20px, 5vw, 28px);
                }

                .process-icon.completed::after {
                    width: clamp(20px, 5vw, 28px);
                    height: clamp(20px, 5vw, 28px);
                    font-size: clamp(12px, 3vw, 16px);
                    top: clamp(-6px, -1.5vw, -8px);
                    right: clamp(-6px, -1.5vw, -8px);
                }

                /* Customer Loyalty Process Flow Mobile */
                .process-flow3 {
                    flex-direction: column;
                    gap: 20px;
                }

                .process-flow3 .arrow {
                    transform: rotate(90deg);
                    margin: 0;
                    font-size: 16px;
                }

                .step {
                    max-width: 150px;
                    width: 100%;
                }

                .icon-container {
                    width: clamp(80px, 15vw, 100px);
                    height: clamp(80px, 15vw, 100px);
                    margin-bottom: 15px;
                }

                /* Analytics Grid Mobile */
                .analytics-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 15px;
                    margin-bottom: 20px;
                }

                .analytics-card {
                    padding: 18px 12px;
                }

                .analytics-icon {
                    font-size: 28px;
                    margin-bottom: 8px;
                }

                /* Dashboard Content Mobile */
                .dashboard-content {
                    padding: 16px;
                }

                .order-row {
                    grid-template-columns: 40px 2fr 80px;
                    gap: 10px;
                    padding: 12px 0;
                    font-size: 12px;
                }

                .customer-avatar {
                    width: 36px;
                    height: 36px;
                    font-size: 14px;
                }

                .order-info h4 {
                    font-size: 13px;
                    margin-bottom: 2px;
                }

                .order-info span {
                    font-size: 11px;
                }

                .action-btn {
                    padding: 6px 10px;
                    font-size: 10px;
                }

                /* Hide some columns on mobile for better readability */
                .order-row > *:nth-child(4),
                .order-row > *:nth-child(5) {
                    display: none;
                }

                /* Team Visual Mobile */
                .team-visual-img {
                    width: 90% !important;
                    max-width: 350px;
                }
            }

            /* Small Mobile (480px and below) */
            @media (max-width: 480px) {
                .why-obg-section {
                    padding: 0px;
                }

                .feature-content {
                    padding: 20px 15px;
                }

                .feature-visual {
                    padding: 15px 10px;
                }

                .section-title {
                    letter-spacing: 1px;
                }

                .feature-title {
                    letter-spacing: 0.5px;
                    line-height: 1.1;
                }

                /* Process Flow Very Small Mobile */
                .process-icon, .process-final {
                    width: 45px;
                    height: 45px;
                    font-size: 18px;
                }

                .process-icon.completed::after {
                    width: 18px;
                    height: 18px;
                    font-size: 10px;
                    top: -5px;
                    right: -5px;
                }

                /* Icon Container Small Mobile */
                .icon-container {
                    width: 70px;
                    height: 70px;
                }

                /* Dashboard Mock Small Mobile */
                .dashboard-header {
                    height: 45px;
                    padding: 0 16px;
                }

                .window-dot {
                    width: 8px;
                    height: 8px;
                }

                .dashboard-nav {
                    padding: 12px 16px;
                    font-size: 12px;
                    gap: 20px;
                }

                .dashboard-content {
                    padding: 12px;
                }

                /* Metrics Dashboard Small Mobile */
                .metrics-dashboard {
                    padding: 20px;
                }

                .metrics-header {
                    font-size: 9px;
                    gap: 8px;
                    margin-bottom: 16px;
                }

                .vip-section {
                    gap: 16px;
                    margin-bottom: 16px;
                }

                .vip-card {
                    padding: 15px;
                }

                .vip-icon {
                    font-size: 24px;
                    margin-bottom: 8px;
                }

                .vip-label {
                    font-size: 10px;
                }

                .revenue-item {
                    padding: 6px 0;
                }

                .revenue-label {
                    font-size: 12px;
                }

                .revenue-amount {
                    font-size: 14px;
                }
            }

            /* Very Small Mobile (360px and below) */
            @media (max-width: 360px) {
                .feature-content {
                    padding: 15px 10px;
                }

                .process-flow {
                    gap: 10px;
                }

                .process-icon, .process-final {
                    width: 40px;
                    height: 40px;
                    font-size: 16px;
                }

                .icon-container {
                    width: 60px;
                    height: 60px;
                }

                .step {
                    max-width: 120px;
                }
            }

            /* Landscape Mobile Orientation */
            @media (max-width: 768px) and (orientation: landscape) {
                .feature-card {
                    grid-template-columns: 1fr 1fr;
                    min-height: 400px;
                }

                .feature-card:nth-child(even) .feature-content {
                    order: 2;
                }

                .process-flow {
                    flex-direction: row;
                    flex-wrap: wrap;
                    justify-content: center;
                }

                .process-arrow {
                    transform: rotate(0deg);
                    margin: 0 8px;
                }

                .process-flow3 {
                    flex-direction: row;
                    flex-wrap: wrap;
                    justify-content: center;
                }

                .process-flow3 .arrow {
                    transform: rotate(0deg);
                    margin: 0 10px;
                }
            }

            /* Performance optimizations for mobile */
            @media (max-width: 768px) {
                .feature-card {
                    will-change: auto;
                }

                .feature-card:hover {
                    transform: none;
                }

                * {
                    -webkit-tap-highlight-color: transparent;
                }
            }
        </style>

        <section class="why-obg-section">
            <div class="section-header">
                <h2 class="section-title">WHY OBG?</h2>
                <p class="section-subtitle">You run a business, not a warehouse.</p>
            </div>

            <div class="features-container">
                <!-- Feature 1: Automatic Order & Shipping Management -->
                <div class="feature-card">
                    <div class="feature-content">
                        <h3 class="feature-title">AUTOMATIC ORDER<br>& SHIPPING MANAGEMENT</h3>
                        <p class="feature-description">Every order is instantly confirmed and sent to the right carrier, no manual input.</p>
                        
                        <div class="process-flow">
                            <div class="process-step">
                                <div class="process-icon completed">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                            </div>
                            <div class="process-arrow">
                                <i class="fas fa-long-arrow-alt-right"></i>
                            </div>
                            <div class="process-step">
                                <div class="process-icon completed">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                            <div class="process-arrow">
                                <i class="fas fa-long-arrow-alt-right"></i>
                            </div>
                            <div class="process-step">
                                <div class="process-icon completed">
                                    <i class="fas fa-truck"></i>
                                </div>
                            </div>
                            <div class="process-arrow">
                                <i class="fas fa-ellipsis-h"></i>
                            </div>
                            <div class="process-step">
                                <div class="process-final">
                                    <i class="fas fa-home"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="feature-visual">
                        <img src="./assets/img/img2.png" style="width: 100%;"
                            alt="OBGecom Order Management Screenshot" 
                            class="feature-screenshot">
                    </div>
                </div>

                <!-- Feature 2: Customer Loyalty Insights -->
                <div class="feature-card">
                    <div class="feature-content">
                        <h3 class="feature-title">CUSTOMER LOYALTY INSIGHTS</h3>
                        <p class="feature-description">
                            Spot VIPs and repeat buyers, track their habits, and build long-term customer value.
                        </p>

                        <style>
                            .process-container {
                                max-width: 1000px;
                                width: 100%;
                            }

                            .process-flow3 {
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                gap: 0px;
                                flex-wrap: wrap;
                            }

                            .step {
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                text-align: center;
                                flex: 1;
                                min-width: 40px;
                                max-width: 160px;
                            }

                            .icon-container {
                                width: 120px;
                                height: 120px;
                                background: white;
                                border-radius: 20px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                margin-bottom: 20px;
                                position: relative;
                            }

                            /* Step 1 - Analytics Dashboard */
                            .step-1 .main-icon {
                                width: 60px;
                                height: 60px;
                                background: #2f2264;
                                border-radius: 8px;
                                position: relative;
                                display: flex;
                                flex-direction: column;
                                padding: 8px;
                                box-sizing: border-box;
                            }

                            .step-1 .chart-bars {
                                display: flex;
                                align-items: flex-end;
                                justify-content: space-between;
                                height: 20px;
                                margin-bottom: 4px;
                            }

                            .step-1 .bar {
                                width: 4px;
                                background: white;
                                border-radius: 1px;
                            }

                            .step-1 .bar:nth-child(1) { height: 60%; }
                            .step-1 .bar:nth-child(2) { height: 80%; }
                            .step-1 .bar:nth-child(3) { height: 40%; }
                            .step-1 .bar:nth-child(4) { height: 100%; }
                            .step-1 .bar:nth-child(5) { height: 70%; }

                            .step-1 .people-icons {
                                display: flex;
                                gap: 2px;
                                justify-content: center;
                                margin-top: 2px;
                            }

                            .step-1 .person {
                                width: 8px;
                                height: 8px;
                                background: white;
                                border-radius: 50%;
                                position: relative;
                            }

                            .step-1 .person::after {
                                content: '';
                                width: 6px;
                                height: 4px;
                                background: white;
                                border-radius: 0 0 3px 3px;
                                position: absolute;
                                top: 6px;
                                left: 1px;
                            }

                            /* Step 2 - Document Analysis */
                            .step-2 .main-icon {
                                width: 50px;
                                height: 60px;
                                background: #2f2264;
                                border-radius: 4px;
                                position: relative;
                                display: flex;
                                flex-direction: column;
                                align-items: center;
                                justify-content: flex-start;
                                padding: 8px 6px;
                                box-sizing: border-box;
                            }

                            .step-2 .doc-lines {
                                width: 100%;
                                height: 100%;
                                display: flex;
                                flex-direction: column;
                                gap: 3px;
                                margin-top: 4px;
                            }

                            .step-2 .line {
                                height: 2px;
                                background: white;
                                border-radius: 1px;
                            }

                            .step-2 .line:nth-child(1) { width: 80%; }
                            .step-2 .line:nth-child(2) { width: 100%; }
                            .step-2 .line:nth-child(3) { width: 60%; }
                            .step-2 .line:nth-child(4) { width: 90%; }

                            .step-2 .magnifying-glass {
                                position: absolute;
                                bottom: -8px;
                                right: -8px;
                                width: 24px;
                                height: 24px;
                                background: #2f2264;
                                border-radius: 50%;
                                border: 3px solid white;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            }

                            .step-2 .magnifying-glass::before {
                                content: '';
                                width: 8px;
                                height: 8px;
                                border: 2px solid white;
                                border-radius: 50%;
                            }

                            .step-2 .magnifying-glass::after {
                                content: '';
                                width: 6px;
                                height: 2px;
                                background: white;
                                position: absolute;
                                bottom: 2px;
                                right: 2px;
                                transform: rotate(45deg);
                                border-radius: 1px;
                            }

                            /* Step 3 - Target User */
                            .step-3 .main-icon {
                                position: relative;
                            }

                            .step-3 .target-circles {
                                width: 60px;
                                height: 60px;
                                position: relative;
                            }

                            .step-3 .circle {
                                position: absolute;
                                border: 4px solid #2f2264;
                                border-radius: 50%;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -50%);
                            }

                            .step-3 .circle:nth-child(1) {
                                width: 60px;
                                height: 60px;
                            }

                            .step-3 .circle:nth-child(2) {
                                width: 40px;
                                height: 40px;
                            }

                            .step-3 .circle:nth-child(3) {
                                width: 20px;
                                height: 20px;
                            }

                            .step-3 .center-dot {
                                width: 6px;
                                height: 6px;
                                background: #2f2264;
                                border-radius: 50%;
                                position: absolute;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -50%);
                            }

                            .step-3 .user-icon {
                                position: absolute;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -50%);
                                color: #2f2264;
                                font-size: 16px;
                                z-index: 1;
                            }

                            .step-3 .user-head {
                                width: 10px;
                                height: 10px;
                                background: #2f2264;
                                border-radius: 50%;
                                margin: 0 auto 2px;
                            }

                            .step-3 .user-body {
                                width: 16px;
                                height: 12px;
                                background: #2f2264;
                                border-radius: 8px 8px 0 0;
                            }

                            /* Step 4 - Success User */
                            .step-4 .icon-container {
                                position: relative;
                            }

                            .step-4 .main-icon {
                                position: relative;
                            }

                            .step-4 .user-head {
                                width: 20px;
                                height: 20px;
                                background: #2f2264;
                                border-radius: 50%;
                                margin: 0 auto 4px;
                            }

                            .step-4 .user-body {
                                width: 32px;
                                height: 24px;
                                background: #2f2264;
                                border-radius: 16px 16px 0 0;
                            }

                            .step-4 .award {
                                position: absolute;
                                right: -6px;
                                width: 28px;
                                height: 28px;
                                background: #fbbf24;
                                border-radius: 50%;
                                border: 3px solid white;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 14px;
                                color: white;
                            }

                            .step-4 .award::before {
                                content: '★';
                            }

                            /* Sparkles */
                            .sparkle {
                                position: absolute;
                                color: #fbbf24;
                                font-size: 14px;
                                animation: sparkle 2s ease-in-out infinite;
                            }

                            .sparkle-1 {
                                top: 10px;
                                right: 10px;
                                animation-delay: 0s;
                            }

                            .sparkle-2 {
                                top: 30px;
                                right: 5px;
                                font-size: 10px;
                                animation-delay: 0.5s;
                            }

                            .sparkle-3 {
                                bottom: 10px;
                                right: 15px;
                                font-size: 12px;
                                animation-delay: 1s;
                            }

                            @keyframes sparkle {
                                0%, 100% { opacity: 0.3; transform: scale(1); }
                                50% { opacity: 1; transform: scale(1.2); }
                            }

                            /* Arrows */
                            .arrow {
                                color: #000;
                                font-size: 20px;
                                margin: 0 10px;
                                flex-shrink: 0;
                            }

                            .arrow::before {
                                content: '→';
                            }

                            /* Responsive */
                            @media (max-width: 768px) {
                                .process-flow3 {
                                    flex-direction: row;
                                    gap: 35px;
                                }

                                .arrow {
                                    transform: rotate(90deg);
                                    margin: 0;
                                }

                                .step {
                                    max-width: 200px;
                                }
                            }
                        </style>

                        <div class="process-flow3">
                            <!-- Step 1: Analytics Dashboard -->
                            <div class="step step-1">
                                <div class="icon-container">
                                    <div class="main-icon">
                                        <div class="chart-bars">
                                            <div class="bar"></div>
                                            <div class="bar"></div>
                                            <div class="bar"></div>
                                            <div class="bar"></div>
                                            <div class="bar"></div>
                                        </div>
                                        <div class="people-icons">
                                            <div class="person"></div>
                                            <div class="person"></div>
                                            <div class="person"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="arrow"></div>

                            <!-- Step 2: Document Analysis -->
                            <div class="step step-2">
                                <div class="icon-container">
                                    <div class="main-icon">
                                        <div class="doc-lines">
                                            <div class="line"></div>
                                            <div class="line"></div>
                                            <div class="line"></div>
                                            <div class="line"></div>
                                        </div>
                                        <div class="magnifying-glass"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="arrow"></div>

                            <!-- Step 3: Target User -->
                            <div class="step step-3">
                                <div class="icon-container">
                                    <div class="main-icon">
                                        <div class="target-circles">
                                            <div class="circle"></div>
                                            <div class="circle"></div>
                                            <div class="circle"></div>
                                            <div class="center-dot"></div>
                                        </div>
                                        <div class="user-icon">
                                            <div class="user-head"></div>
                                            <div class="user-body"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="arrow"></div>

                            <!-- Step 4: Success User -->
                            <div class="step step-4">
                                <div class="icon-container">
                                    <div class="main-icon">
                                        <div class="user-head"></div>
                                        <div class="user-body"></div>
                                    </div>
                                    <div class="award"></div>
                                    <div class="sparkle sparkle-1">✨</div>
                                    <div class="sparkle sparkle-2">★</div>
                                    <div class="sparkle sparkle-3">✨</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Feature Visual with Screenshot -->
                    <div class="feature-visual">
                        <img src="./assets/img/image3.jpg" width="85%"
                            alt="OBGecom Customer Loyalty Insights Screenshot" 
                            class="feature-screenshot">
                    </div>
                </div>

                <!-- Feature 3: Team Performance Tracking -->
                <div class="feature-card" style="background: #e2daff;">
                    <div class="feature-content">
                        <h3 class="feature-title">TEAM PERFORMANCE TRACKING</h3>
                        <p class="feature-description">See confirmation accuracy per agent and optimize your operations with data.</p>
                    </div>
                    
                    <div class="feature-visual">
                        <!-- Single responsive image instead of multiple icons -->
                        <img src="./assets/img/img4.png" alt="Team performance visual" class="img-fluid team-visual-img" width="75%">
                    </div>
                </div>
            </div>
        </section>

        <style>

            .integrations-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0px;
                align-items: center;
                max-width: 100%;
                margin: 0 auto;
                padding: 60px;
                background: white;
                border-radius: 0;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.05);
            }

            /* Left Content */
            .content-left2 {
                padding-right: 40px;
            }

            .section-title2 {
                font-size: 14px;
                font-weight: 500;
                color: #6B46C1;
                letter-spacing: 1px;
                text-transform: uppercase;
                margin-bottom: 20px;
            }

            .main-heading {
                font-size: 36px;
                font-weight: 700;
                color: #1e293b;
                line-height: 1.2;
                margin-bottom: 20px;
            }

            .description {
                font-size: 18px;
                color: #64748b;
                line-height: 1.6;
                margin-bottom: 40px;
            }

            /* API Illustration */
            .api-illustration {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 30px;
                margin-top: 40px;
            }

            .server-group {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .server {
                width: 50px;
                height: 35px;
                background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
                border-radius: 6px;
                position: relative;
                border: 2px solid #cbd5e1;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .server::after {
                content: '';
                width: 24px;
                height: 3px;
                background: #64748b;
                border-radius: 2px;
            }

            .connection-lines {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin: 0 15px;
            }

            .line {
                width: 40px;
                height: 3px;
                background: linear-gradient(90deg, #6B46C1, #8B5CF6);
                border-radius: 2px;
                animation: dataFlow 2s ease-in-out infinite;
            }

            .line:nth-child(2) {
                animation-delay: 0.4s;
            }

            .line:nth-child(3) {
                animation-delay: 0.8s;
            }

            @keyframes dataFlow {
                0%, 100% { 
                    opacity: 0.4; 
                    transform: scaleX(0.7);
                    background: linear-gradient(90deg, #6B46C1, #8B5CF6);
                }
                50% { 
                    opacity: 1; 
                    transform: scaleX(1.3);
                    background: linear-gradient(90deg, #8B5CF6, #A78BFA);
                }
            }

            .api-box {
                background: white;
                border: 3px solid #6B46C1;
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 15px 35px rgba(107, 70, 193, 0.15);
                position: relative;
                min-width: 140px;
                text-align: center;
            }

            .api-icon {
                width: 50px;
                height: 50px;
                background: linear-gradient(135deg, #6B46C1, #8B5CF6);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 14px;
                margin: 0 auto 12px;
            }

            .api-label {
                font-size: 14px;
                font-weight: 600;
                color: #1e293b;
            }

            .check-mark {
                position: absolute;
                top: -12px;
                right: -12px;
                width: 28px;
                height: 28px;
                background: #10b981;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 14px;
                font-weight: bold;
                border: 4px solid white;
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            }

            /* Right Side - Management Interface */
            .management-interface {
                background: white;
                border-radius: 20px;
                padding: 28px;
                box-shadow: 0 30px 60px rgba(0, 0, 0, 0.1);
                border: 1px solid #e2e8f0;
            }

            .interface-header {
                display: flex;
                align-items: center;
                gap: 16px;
                margin-bottom: 28px;
                padding-bottom: 20px;
                border-bottom: 2px solid #f1f5f9;
            }

            .interface-icon {
                width: 36px;
                height: 36px;
                background: linear-gradient(135deg, #6B46C1, #8B5CF6);
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
            }

            .interface-title {
                font-size: 18px;
                font-weight: 600;
                color: #1e293b;
                flex: 1;
            }

            .sync-button {
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
                box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            }

            .sync-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
            }

            .section-label {
                font-size: 14px;
                color: #64748b;
                margin-bottom: 20px;
            }

            .section-label.bold {
                font-weight: 600;
                color: #1e293b;
                margin-top: 8px;
            }

            .integration-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-bottom: 28px;
            }

            .integration-card {
                background: #f8fafc;
                border: 2px solid #e2e8f0;
                border-radius: 16px;
                padding: 24px;
                text-align: center;
                transition: all 0.3s ease;
                cursor: pointer;
                position: relative;
            }

            .integration-card:hover {
                border-color: #6B46C1;
                transform: translateY(-4px);
                box-shadow: 0 12px 30px rgba(107, 70, 193, 0.15);
            }

            .integration-card.active {
                border-color: #10b981;
                background: #f0fdf4;
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(16, 185, 129, 0.15);
            }

            .integration-card.active::after {
                content: '✓';
                position: absolute;
                top: -10px;
                right: -10px;
                width: 24px;
                height: 24px;
                background: #10b981;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 12px;
                font-weight: bold;
                border: 3px solid white;
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            }

            .platform-icon {
                width: 50px;
                height: 50px;
                border-radius: 12px;
                margin: 0 auto 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                color: white;
                font-size: 18px;
            }

            .shopify { background: linear-gradient(135deg, #96bf48, #7ab800); }
            .woocommerce { background: linear-gradient(135deg, #96588a, #7c4a7c); }
            .youcan { background: linear-gradient(135deg, #ee672f, #f26822); }

            .platform-name {
                font-size: 15px;
                font-weight: 600;
                color: #1e293b;
            }

            .status-section {
                background: #f1f5f9;
                border-radius: 12px;
                padding: 20px;
                text-align: center;
            }

            .status-text {
                font-size: 14px;
                color: #64748b;
                margin-bottom: 8px;
            }

            .status-value {
                font-size: 24px;
                font-weight: 700;
                color: #10b981;
            }

            /* Responsive Design */
            @media (max-width: 968px) {
                .integrations-section {
                    grid-template-columns: 1fr;
                    gap: 60px;
                    padding: 40px 30px;
                }

                .content-left2 {
                    padding-right: 0;
                    text-align: center;
                }

                .main-heading {
                    font-size: 32px;
                }

                .api-illustration {
                    justify-content: center;
                    margin: 40px 0;
                }
            }

            @media (max-width: 768px) {
                .integration-grid {
                    grid-template-columns: 1fr;
                }

                .main-heading {
                    font-size: 28px;
                }

                .integrations-section {
                    padding: 30px 20px;
                }
            }

            /* Animation for the entire section */
            .integrations-section {
                animation: fadeInUp 1s ease-out;
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(40px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>

        <section class="integrations-section">
            <div class="content-left2">
                <div class="section-title2">Seamless API Integrations</div>
                <h2 class="main-heading">Connect your stores and carriers in minutes.</h2>
                <p class="description">Orders, customers, and logistics sync automatically.</p>
                
                <div class="api-illustration">
                    <div class="server-group">
                        <div class="server"></div>
                        <div class="server"></div>
                        <div class="server"></div>
                    </div>
                    
                    <div class="connection-lines">
                        <div class="line"></div>
                        <div class="line"></div>
                        <div class="line"></div>
                    </div>
                    
                    <div class="api-box">
                        <div class="api-icon">API</div>
                        <div class="api-label">Integration Hub</div>
                        <div class="check-mark">✓</div>
                    </div>
                </div>
            </div>

            <div class="management-interface">
                <div class="interface-header">
                    <div class="interface-icon">🏪</div>
                    <div class="interface-title">Gestion des Boutiques</div>
                    <button class="sync-button">+ Ajouter une boutique</button>
                </div>
                
                <div class="section-label">Votre plan actuel vous permet de connecter jusqu'à 5 boutiques.</div>
                <div class="section-label" style="text-align: right; font-weight: 600; color: #1e293b;">Boutiques connectées: 1/5</div>
                
                <div class="section-label bold">Ajouter une nouvelle boutique</div>
                
                <div class="integration-grid">
                    <div class="integration-card active">
                        <div class="platform-icon shopify">🛒</div>
                        <div class="platform-name">Shopify</div>
                    </div>
                    
                    <div class="integration-card">
                        <div class="platform-icon woocommerce">W</div>
                        <div class="platform-name">WooCommerce</div>
                    </div>
                    
                    <div class="integration-card">
                        <div class="platform-icon youcan">Y</div>
                        <div class="platform-name">YouCan</div>
                    </div>
                </div>
            </div>
        </section>

        <style>

            /* Delivery Theme Gradient */
            .parallax-break {
                height: 50vh;
                position: relative;
                background: var(--bg-gradient);
                overflow: hidden;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .data-network {
                position: absolute;
                width: 100%;
                height: 100%;
                top: 0;
                left: 0;
                opacity: 0.6;
            }

            .data-node {
                position: absolute;
                width: 12px;
                height: 12px;
                background: rgba(255, 255, 255, 0.8);
                border-radius: 50%;
                animation: nodePulse 3s ease-in-out infinite;
            }

            .connection-line {
                position: absolute;
                height: 1px;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
                animation: dataFlow 4s linear infinite;
                transform-origin: left center;
            }

            /* Remove unused floating data animations */

            .circuit-pattern {
                position: absolute;
                width: 100%;
                height: 100%;
                background-image: 
                    linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px),
                    linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
                background-size: 50px 50px;
                animation: circuitMove 20s linear infinite;
            }

            .section-header {
                text-align: center;
                margin-bottom: 0px;
                position: relative;
                z-index: 10;
            }

            .section-title3 {
                font-size: 3rem;
                font-weight: 800;
                color: white;
                margin-bottom: 20px;
                letter-spacing: 2px;
                text-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            }

            .section-subtitle2 {
                font-size: 1.3rem;
                color: #FFF;
                max-width: 700px;
                margin: 0 auto;
                line-height: 1.6;
                text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            }

            .sync-indicators {
                position: absolute;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%);
                display: flex;
                gap: 8px;
            }

            .sync-dot {
                width: 8px;
                height: 8px;
                background: rgba(255, 255, 255, 0.6);
                border-radius: 50%;
                animation: syncPulse 2s ease-in-out infinite;
            }

            .sync-dot:nth-child(2) { animation-delay: 0.3s; }
            .sync-dot:nth-child(3) { animation-delay: 0.6s; }
            .sync-dot:nth-child(4) { animation-delay: 0.9s; }

            /* Animations */
            @keyframes nodePulse {
                0%, 100% { 
                    transform: scale(1); 
                    opacity: 0.8; 
                    box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7);
                }
                50% { 
                    transform: scale(1.3); 
                    opacity: 1; 
                    box-shadow: 0 0 0 10px rgba(255, 255, 255, 0);
                }
            }

            @keyframes dataFlow {
                0% { 
                    opacity: 0; 
                    transform: scaleX(0); 
                }
                20% { 
                    opacity: 1; 
                    transform: scaleX(0.3); 
                }
                80% { 
                    opacity: 1; 
                    transform: scaleX(1); 
                }
                100% { 
                    opacity: 0; 
                    transform: scaleX(1); 
                }
            }

            @keyframes syncPulse {
                0%, 100% { 
                    transform: scale(1); 
                    opacity: 0.6; 
                }
                50% { 
                    transform: scale(1.8); 
                    opacity: 1; 
                }
            }

            /* Dynamic Nodes Positioning */
            .data-node:nth-child(1) { top: 15%; left: 10%; animation-delay: 0s; }
            .data-node:nth-child(2) { top: 25%; left: 75%; animation-delay: 1s; }
            .data-node:nth-child(3) { top: 45%; left: 20%; animation-delay: 2s; }
            .data-node:nth-child(4) { top: 35%; right: 15%; animation-delay: 0.5s; }
            .data-node:nth-child(5) { top: 65%; left: 60%; animation-delay: 1.5s; }
            .data-node:nth-child(6) { top: 75%; left: 30%; animation-delay: 2.5s; }
            .data-node:nth-child(7) { top: 55%; right: 25%; animation-delay: 0.8s; }
            .data-node:nth-child(8) { top: 85%; right: 40%; animation-delay: 1.8s; }

            /* Connection Lines Positioning */
            .connection-line:nth-child(1) { top: 20%; left: 15%; width: 200px; transform: rotate(25deg); animation-delay: 0s; }
            .connection-line:nth-child(2) { top: 40%; left: 25%; width: 180px; transform: rotate(-15deg); animation-delay: 1s; }
            .connection-line:nth-child(3) { top: 60%; right: 20%; width: 150px; transform: rotate(45deg); animation-delay: 2s; }
            .connection-line:nth-child(4) { top: 30%; right: 30%; width: 120px; transform: rotate(-35deg); animation-delay: 0.5s; }
            .connection-line:nth-child(5) { top: 70%; left: 40%; width: 160px; transform: rotate(10deg); animation-delay: 1.5s; }

            /* Responsive Design */
            @media (max-width: 768px) {
                .parallax-break { height: 40vh; }
                .section-title3 { font-size: 2.2rem; }
                .section-subtitle2 { font-size: 1.1rem; padding: 0 20px; }
            }

            @media (max-width: 480px) {
                .parallax-break { height: 35vh; }
                .section-title3 { font-size: 1.8rem; }
                .section-subtitle2 { font-size: 1rem; padding: 0 15px; }
                .circuit-pattern { background-size: 30px 30px; }
            }
        </style>

        <section class="parallax-break">
            <div class="circuit-pattern"></div>
            
            <div class="data-network">
                <!-- Data Nodes -->
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                
                <!-- Connection Lines -->
                <div class="connection-line"></div>
                <div class="connection-line"></div>
                <div class="connection-line"></div>
                <div class="connection-line"></div>
                <div class="connection-line"></div>
            </div>
            
            <div class="break-content">
                <div class="section-header">
                    <h2 class="section-title3">HOW IT WORKS</h2>
                    <p class="section-subtitle2">From order placement to delivery tracking, OBGecom synchronizes everything automatically</p>
                </div>
            </div>
            
            <div class="sync-indicators">
                <div class="sync-dot"></div>
                <div class="sync-dot"></div>
                <div class="sync-dot"></div>
                <div class="sync-dot"></div>
            </div>
        </section>

        <script>
            // Parallax scrolling effect
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const dataNetwork = document.querySelector('.data-network');
                const circuitPattern = document.querySelector('.circuit-pattern');
                const nodes = document.querySelectorAll('.data-node');
                
                if (dataNetwork) {
                    const speed = scrolled * 0.2;
                    dataNetwork.style.transform = `translateY(${speed}px)`;
                }
                
                if (circuitPattern) {
                    const speed = scrolled * 0.1;
                    circuitPattern.style.transform = `translateX(${50 + speed * 0.1}px) translateY(${50 + speed * 0.1}px)`;
                }
                
                nodes.forEach((node, index) => {
                    const speed = (index + 1) * 0.1;
                    const offsetX = Math.sin(scrolled * 0.001 + index) * 10;
                    const offsetY = scrolled * speed;
                    node.style.transform = `translate(${offsetX}px, ${offsetY}px)`;
                });
            });

            // Enhanced interactivity
            document.addEventListener('mousemove', (e) => {
                const nodes = document.querySelectorAll('.data-node');
                const mouseX = e.clientX / window.innerWidth;
                const mouseY = e.clientY / window.innerHeight;
                
                nodes.forEach((node, index) => {
                    const speed = (index + 1) * 0.02;
                    const offsetX = (mouseX - 0.5) * 20 * speed;
                    const offsetY = (mouseY - 0.5) * 20 * speed;
                    node.style.transform += ` translate(${offsetX}px, ${offsetY}px)`;
                });
            });

            // Performance optimization - pause animations when not visible
            document.addEventListener('visibilitychange', () => {
                const animatedElements = document.querySelectorAll('.data-node, .connection-line, .floating-data, .break-icon, .sync-dot, .circuit-pattern');
                
                if (document.hidden) {
                    animatedElements.forEach(el => el.style.animationPlayState = 'paused');
                } else {
                    animatedElements.forEach(el => el.style.animationPlayState = 'running');
                }
            });

            // Add dynamic glow effect to nodes on intersection
            observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -100px 0px'
            };

            observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const nodes = entry.target.querySelectorAll('.data-node');
                        nodes.forEach((node, index) => {
                            setTimeout(() => {
                                node.style.filter = 'brightness(1.5) drop-shadow(0 0 20px rgba(255,255,255,0.8))';
                                setTimeout(() => {
                                    node.style.filter = '';
                                }, 1000);
                            }, index * 200);
                        });
                    }
                });
            }, observerOptions);

            observer.observe(document.querySelector('.parallax-break'));
        </script>

        <style>

            .hero-content {
                text-align: center;
                z-index: 10;
                color: white;
                max-width: 800px;
                padding: 0 20px;
            }

            .hero-badge {
                display: inline-block;
                background: rgba(255, 255, 255, 0.2);
                backdrop-filter: blur(10px);
                padding: 12px 24px;
                border-radius: 50px;
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 30px;
                border: 1px solid rgba(255, 255, 255, 0.3);
                animation: fadeInUp 0.8s ease-out;
            }

            .hero-title {
                font-size: clamp(3rem, 8vw, 6rem);
                font-weight: 800;
                margin-bottom: 20px;
                line-height: 1.1;
                animation: fadeInUp 0.8s ease-out 0.2s both;
                text-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            }

            .hero-subtitle {
                font-size: clamp(1.2rem, 3vw, 1.8rem);
                margin-bottom: 40px;
                opacity: 0.9;
                line-height: 1.4;
                animation: fadeInUp 0.8s ease-out 0.4s both;
            }

            .sync-stats {
                display: flex;
                justify-content: center;
                gap: 60px;
                margin-top: 60px;
                animation: fadeInUp 0.8s ease-out 0.6s both;
            }

            .stat-item {
                text-align: center;
            }

            .stat-number {
                font-size: 2.5rem;
                font-weight: 800;
                display: block;
                margin-bottom: 8px;
            }

            .stat-label {
                font-size: 0.9rem;
                opacity: 0.8;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .scroll-indicator {
                position: absolute;
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%);
                color: white;
                animation: bounce 2s infinite;
            }

            .scroll-indicator::before {
                content: '↓';
                font-size: 2rem;
                display: block;
            }

            /* How It Works Section */
            .how-section {
                padding: 100px 100px 80px;
                max-width: 1400px;
                margin: 0 auto;
                background: #fafbfc;
            }

            .section-header {
                text-align: center;
                margin-bottom: 0px;
            }

            .section-title3 {
                font-size: 3rem;
                font-weight: 800;
                color: #FFF;
                margin-bottom: 16px;
                letter-spacing: -1px;
            }

            .section-subtitle {
                font-size: 1.3rem;
                color: #FFF;
                max-width: 600px;
                margin: 0 auto;
            }

            .process-flow2 {
                display: grid;
                gap: 60px;
            }

            .process-step2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 80px;
                align-items: center;
                opacity: 0;
                transform: translateY(50px);
                transition: all 0.8s ease-out;
            }

            .process-step2.visible {
                opacity: 1;
                transform: translateY(0);
            }

            .process-step2:nth-child(even) {
                direction: rtl;
            }

            .process-step2:nth-child(even) .step-content2,
            .process-step2:nth-child(even) .step-visual {
                direction: ltr;
            }

            .step-content2 {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                padding: 50px;
                border-radius: 24px;
                box-shadow: 0 25px 50px rgba(102, 126, 234, 0.3);
                position: relative;
                overflow: hidden;
                transform: translateZ(0);
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }

            .step-content2:hover {
                transform: translateY(-5px);
                box-shadow: 0 35px 70px rgba(102, 126, 234, 0.4);
            }

            .step-content2::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: 
                    radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                    radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
                pointer-events: none;
            }

            .step-number {
                position: absolute;
                top: 25px;
                right: 30px;
                width: 50px;
                height: 50px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                font-weight: 700;
                backdrop-filter: blur(10px);
                border: 2px solid rgba(255, 255, 255, 0.3);
            }

            .step-title {
                font-size: 2.2rem;
                font-weight: 700;
                margin-bottom: 20px;
                position: relative;
                z-index: 1;
                line-height: 1.2;
            }

            .step-description {
                font-size: 1.1rem;
                line-height: 1.7;
                opacity: 0.95;
                position: relative;
                z-index: 1;
                margin-bottom: 20px;
            }

            .step-features {
                list-style: none;
                position: relative;
                z-index: 1;
                padding: 0;
            }

            .step-features li {
                margin: 12px 0;
                padding-left: 25px;
                position: relative;
                font-size: 1rem;
                line-height: 1.6;
            }

            .step-features li::before {
                content: '✓';
                position: absolute;
                left: 0;
                color: rgba(255, 255, 255, 0.8);
                font-weight: bold;
                font-size: 1.1rem;
            }

            .step-visual {
                background: white;
                border-radius: 20px;
                padding: 30px;
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
                position: relative;
                overflow: hidden;
                transition: transform 0.3s ease;
            }

            .step-visual:hover {
                transform: translateY(-8px);
            }

            .screenshot-container {
                width: 100%;
                height: 350px;
                background: #f8fafc;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow: hidden;
                border: 2px solid #e2e8f0;
            }

            .screenshot-container img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 10px;
                transition: transform 0.4s ease;
            }

            .screenshot-container:hover img {
                transform: scale(1.05);
            }

            /* Animations */
            @keyframes float {
                0%, 100% { transform: translate(0, 0) rotate(0deg); }
                33% { transform: translate(30px, -30px) rotate(120deg); }
                66% { transform: translate(-20px, 20px) rotate(240deg); }
            }

            @keyframes syncFlow {
                0% { opacity: 0; transform: translateY(-20px); }
                50% { opacity: 1; }
                100% { opacity: 0; transform: translateY(20px); }
            }

            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.2); opacity: 0.8; }
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            @keyframes bounce {
                0%, 20%, 50%, 80%, 100% { transform: translateX(-50%) translateY(0); }
                40% { transform: translateX(-50%) translateY(-10px); }
                60% { transform: translateX(-50%) translateY(-5px); }
            }

            /* Responsive Design */
            @media (max-width: 1200px) {
                .how-section { padding: 80px 50px; }
                .sync-stats { gap: 40px; }
            }

            @media (max-width: 768px) {
                .parallax-hero { height: 80vh; }
                .how-section { padding: 60px 20px; }
                .process-step2 {
                    grid-template-columns: 1fr;
                    gap: 40px;
                }
                .process-step2:nth-child(even) { direction: ltr; }
                .step-content2 { padding: 40px; }
                .sync-stats {
                    flex-direction: column;
                    gap: 30px;
                    align-items: center;
                }
                .screenshot-container { height: 250px; }
            }

            @media (max-width: 480px) {
                .step-content2 { padding: 30px; }
                .step-title { font-size: 1.8rem; }
                .section-title3 { font-size: 2.2rem; }
            }
        </style>

        <!-- How It Works Section -->
        <section class="how-section">

            <div class="process-flow2">
                <!-- Step 1: Connect -->
                <div class="process-step2" data-step="1">
                    <div class="step-content2">
                        <div class="step-number">1</div>
                        <h3 class="step-title">Connect & Sync</h3>
                        <p class="step-description">Link your e-commerce platform with multiple shipping carriers in one unified system. Our smart sync engine maps all data fields automatically.</p>
                    </div>
                    <div class="step-visual">
                        <div class="screenshot-container">
                            <img src="./assets/img/image5.jpg" alt="Integration Dashboard showing store and carrier connections">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Automation -->
                <div class="process-step2" data-step="2">
                    <div class="step-content2">
                        <div class="step-number">2</div>
                        <h3 class="step-title">Intelligent Automation</h3>
                        <ul class="step-features">
                            <li>Orders sync instantly across all platforms</li>
                            <li>Smart carrier selection based on location & preferences</li>
                            <li>Automated label generation and booking</li>
                            <li>Real-time inventory updates everywhere</li>
                        </ul>
                    </div>
                    <div class="step-visual">
                        <div class="screenshot-container">
                            <img src="./assets/img/image6.jpg" alt="Automation engine processing orders in real-time">
                        </div>
                    </div>
                </div>

                <!-- Step 3: Monitor -->
                <div class="process-step2" data-step="3">
                    <div class="step-content2">
                        <div class="step-number">3</div>
                        <h3 class="step-title">Unified Monitoring</h3>
                        <p class="step-description">Track every order, shipment, and sync status from one powerful dashboard. Get instant alerts when manual intervention is needed.</p>
                    </div>
                    <div class="step-visual">
                        <div class="screenshot-container">
                            <img src="./assets/img/image7.jpg" alt="Comprehensive monitoring dashboard with live sync status">
                        </div>
                    </div>
                </div>

                <!-- Step 4: Growth -->
                <div class="process-step2" data-step="4">
                    <div class="step-content2">
                        <div class="step-number">4</div>
                        <h3 class="step-title">Scale Seamlessly</h3>
                        <p class="step-description">Focus on growing your business while OBGecom handles the complex synchronization behind the scenes. Add new stores or carriers without disruption.</p>
                    </div>
                    <div class="step-visual">
                        <div class="screenshot-container">
                            <img src="./assets/img/image8.jpg" alt="Business growth analytics and scaling metrics">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <script>
            // Parallax scroll effect
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const parallaxBg = document.querySelector('.parallax-bg');
                const heroContent = document.querySelector('.hero-content');
                
                if (parallaxBg && scrolled < window.innerHeight) {
                    parallaxBg.style.transform = `translate3d(0, ${scrolled * 0.5}px, 0)`;
                    heroContent.style.transform = `translate3d(0, ${scrolled * 0.3}px, 0)`;
                    heroContent.style.opacity = Math.max(0, 1 - scrolled / (window.innerHeight * 0.8));
                }
            });


            document.querySelectorAll('.process-step2').forEach(step => {
                observer.observe(step);
            });

            // Add some dynamic sync animations
            const syncLines = document.querySelectorAll('.sync-line');
            setInterval(() => {
                syncLines.forEach((line, index) => {
                    line.style.animationDelay = `${Math.random() * 3}s`;
                });
            }, 5000);
        </script>

        <style>
            
            /* Pricing Section */
            .pricing-section {
                background: linear-gradient(135deg, #e1d7f5 0%, #c9b8eb 50%, #2f2264 100%);
                padding: 80px 20px;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .pricing-title {
                font-size: 2.8rem;
                font-weight: 800;
                text-align: center;
                margin-bottom: 60px;
                letter-spacing: 2px;
                color: #2f2264;
                text-transform: uppercase;
            }

            .pricing-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 25px;
                max-width: 100%;
                width: 100%;
                margin: 0 auto;
            }

            .pricing-card {
                background: white;
                border-radius: 15px;
                padding: 0;
                text-align: left;
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                position: relative;
                overflow: hidden;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                display: flex;
                flex-direction: column;
                height: 100%;
            }

            .pricing-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            }

            .plan-header {
                color: #4a148c;
                padding: 25px 25px 15px;
                font-size: 1.4rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 1px;
                border-bottom: 1px solid #f0f0f0;
            }

            .pricing-display {
                padding: 20px 25px;
                border-bottom: 1px solid #f0f0f0;
            }

            .starter-free {
                background: #e8f5e8;
                color: #2e7d32;
                padding: 8px 15px;
                border-radius: 20px;
                font-size: 0.9rem;
                font-weight: 600;
                display: inline-block;
                margin-bottom: 15px;
            }

            .plan-price {
                font-size: 2.5rem;
                font-weight: 800;
                color: #4a148c;
                margin-bottom: 5px;
                display: flex;
                align-items: flex-start;
            }

            .plan-price .currency {
                font-size: 1.5rem;
                margin-right: 2px;
                margin-top: 5px;
            }

            .plan-price .amount {
                font-size: 2.5rem;
                line-height: 1;
            }

            .plan-price .period {
                font-size: 0.9rem;
                font-weight: normal;
                color: #666;
                align-self: flex-end;
                margin-bottom: 5px;
                margin-left: 5px;
            }

            .original-price {
                font-size: 1rem;
                color: #1f1e1e;
                text-decoration: line-through;
                margin-top: 5px;
            }

            .price-note {
                font-size: 0.85rem;
                color: #666;
                margin-top: 8px;
            }

            .plan-features {
                list-style: none;
                margin: 0;
                padding: 25px;
                flex-grow: 1;
            }

            .plan-features li {
                margin-bottom: 15px;
                padding-left: 30px;
                position: relative;
                font-size: 1rem;
                color: #555;
                line-height: 1.4;
            }

            .plan-features li::before {
                content: "✓";
                position: absolute;
                left: 0;
                color: #4a148c;
                font-weight: 700;
                font-size: 1.1rem;
            }

            .plan-description {
                background: #f8f9fa;
                padding: 20px 25px;
                font-size: 0.9rem;
                color: #666;
                font-style: italic;
                text-align: center;
                border-top: 1px solid #f0f0f0;
            }

            /* Button Styles */
            .plan-button {
                margin: 20px 25px 25px;
                padding: 15px 25px;
                border: none;
                border-radius: 8px;
                font-size: 1.1rem;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 1px;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: block;
                text-align: center;
                position: relative;
                overflow: hidden;
            }

            .plan-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }

            .plan-button:active {
                transform: translateY(0);
            }

            .plan-button::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                transition: left 0.5s;
            }

            .plan-button:hover::before {
                left: 100%;
            }

            /* Card specific colors */
            .pricing-card.starter .plan-header {
                color: #7b1fa2;
            }

            .pricing-card.starter .plan-price {
                color: #7b1fa2;
            }

            .pricing-card.starter .plan-features li::before {
                color: #7b1fa2;
            }

            .pricing-card.starter .plan-button {
                background: #7b1fa2;
                color: white;
            }

            .pricing-card.starter .plan-button:hover {
                background: #6a1489;
            }

            .pricing-card.professional .plan-header {
                color: #6a1b9a;
            }

            .pricing-card.professional .plan-price {
                color: #6a1b9a;
            }

            .pricing-card.professional .plan-features li::before {
                color: #6a1b9a;
            }

            .pricing-card.professional .plan-button {
                background: #6a1b9a;
                color: white;
            }

            .pricing-card.professional .plan-button:hover {
                background: #5d1787;
            }

            .pricing-card.growth .plan-header {
                color: #5e35b1;
            }

            .pricing-card.growth .plan-price {
                color: #5e35b1;
            }

            .pricing-card.growth .plan-features li::before {
                color: #5e35b1;
            }

            .pricing-card.growth .plan-button {
                background: #5e35b1;
                color: white;
            }

            .pricing-card.growth .plan-button:hover {
                background: #512e9e;
            }

            .pricing-card.business .plan-header {
                color: #4a148c;
            }

            .pricing-card.business .plan-price {
                color: #4a148c;
            }

            .pricing-card.business .plan-features li::before {
                color: #4a148c;
            }

            .pricing-card.business .plan-button {
                background: #4a148c;
                color: white;
            }

            .pricing-card.business .plan-button:hover {
                background: #3d1073;
            }

            @media (max-width: 1024px) {
                .pricing-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px;
                }
                
                .pricing-title {
                    font-size: 2.5rem;
                }
            }

            @media (max-width: 768px) {
                .pricing-grid {
                    grid-template-columns: 1fr;
                    max-width: 500px;
                    gap: 25px;
                }
                
                .pricing-title {
                    font-size: 2.2rem;
                    margin-bottom: 40px;
                }
                
                .pricing-section {
                    padding: 60px 15px;
                }
            }
        </style>

        <!-- Pricing Section -->
        <section class="pricing-section" id="pricing">
            <h2 class="pricing-title">PLANS & PRICING</h2>
            
            <div class="pricing-grid">
                <!-- Starter Plan -->
                <div class="pricing-card starter">
                    <div class="plan-header">STARTER</div>
                    <div class="pricing-display">
                        <div class="starter-free">Free forever</div>
                        <div class="plan-price">
                            <span class="currency">MAD</span>
                            <span class="amount">0</span>
                            <span class="period">/month</span>
                        </div>
                    </div>
                    
                    <ul class="plan-features">
                        <li>Up to 100 orders/month</li>
                        <li>1 store integration</li>
                        <li>1 carrier integration</li>
                        <li>Email support</li>
                    </ul>
                    
                    <div class="plan-description">(Best for testing & launching)</div>
                    <a href="#signup" class="plan-button" onclick="selectPlan('starter')">Get Started</a>
                </div>
                
                <!-- Professional Plan -->
                <div class="pricing-card professional">
                    <div class="plan-header">PROFESSIONAL</div>
                    <div class="pricing-display">
                        <div class="plan-price">
                            <span class="currency">MAD</span>
                            <span class="amount">149</span>
                            <span class="period">/month</span>
                        </div>
                        <div class="original-price">199 MAD flexible</div>
                    </div>
                    
                    <ul class="plan-features">
                        <li>Up to 1,000 orders per month</li>
                        <li>Connect and manage up to 5 stores (Shopify, YouCan, WooCommerce)</li>
                        <li>Multi-carrier integration for automated order assignment and tracking</li>
                        <li>Automatic customer notifications (confirmation, shipment, delivery)</li>
                        <li>Customer management dashboard to view, segment, and analyze your clients</li>
                        <li>Priority chat & email support</li>
                    </ul>
                    
                    <div class="plan-description">(Perfect for growing e-commerce businesses)</div>
                    <a href="#signup" class="plan-button" onclick="selectPlan('professional')">Get Started</a>
                </div>


                <!-- Growth Plan -->
                <div class="pricing-card growth">
                    <div class="plan-header">GROWTH</div>
                    <div class="pricing-display">
                        <div class="plan-price">
                            <span class="currency">MAD</span>
                            <span class="amount">199</span>
                            <span class="period">/month</span>
                        </div>
                        <div class="original-price">299 MAD flexible</div>
                    </div>
                    
                    <ul class="plan-features">
                        <li>Up to 4,000 orders per month</li>
                        <li>Connect and manage up to 10 stores (Shopify, YouCan, WooCommerce)</li>
                        <li>Multi-carrier integration for automated order assignment and tracking</li>
                        <li>Real-time delivery tracking</li>
                        <li>Automatic customer notifications (confirmation, shipment, delivery)</li>
                        <li>Customer management dashboard to view, segment, and analyze your clients</li>
                        <li>Priority chat & email support</li>
                    </ul>
                    
                    <div class="plan-description">(Ideal for fast-growing businesses)</div>
                    <a href="#signup" class="plan-button" onclick="selectPlan('growth')">Get Started</a>
                </div>
                
                <!-- Business Plan -->
                <div class="pricing-card business">
                    <div class="plan-header">BUSINESS</div>
                    <div class="pricing-display">
                        <div class="plan-price">
                            <span class="currency">MAD</span>
                            <span class="amount">999</span>
                            <span class="period">/month</span>
                        </div>
                        <div class="original-price">1999 MAD flexible</div>
                    </div>
                    
                    <ul class="plan-features">
                        <li>Unlimited orders per month</li>
                        <li>Unlimited store connections across all major platforms (Shopify, YouCan, WooCommerce)</li>
                        <li>Full multi-carrier automation</li>
                        <li>Integrated order confirmation service</li>
                        <li>Unlimited automation rules (by region, carrier, value, or delivery speed)</li>
                        <li>Advanced team management (multi-user access, roles, and permissions)</li>
                        <li>Comprehensive analytics suite (orders, carriers, clients)</li>
                        <li>Real-time delivery tracking</li>
                        <li>CRM & advanced customer dashboard</li>
                        <li>Dedicated account manager & 24/7 premium support</li>
                        <li>Personalized onboarding & training for teams</li>
                    </ul>
                                    
                    <div class="plan-description">(Complete solution for high-volume businesses)</div>
                    <a href="#signup" class="plan-button" onclick="selectPlan('business')">Get Started</a>
                </div>


            </div>
        </section>

        <script>
            function selectPlan(planName) {
                // Store the selected plan in memory
                sessionStorage.setItem('selectedPlan', planName);
                
                // Navigate to signup page with plan parameter
                window.location.href = `./lg?plan=${planName}`;
                
                // Alternative: If you want to handle it differently, you can use:
                // alert(`Selected plan: ${planName.toUpperCase()}`);
                
                console.log(`User selected: ${planName} plan`);
            }
        </script>

        <style>

            .partners-section {
                max-width: 100%;
                margin: 0 auto;
                text-align: center;
            }

            .section-title {
                font-size: 3rem;
                font-weight: 800;
                color: #1a202c;
                margin-bottom: 1rem;
                letter-spacing: -0.025em;
                position: relative;
            }

            .section-title::after {
                content: '';
                position: absolute;
                bottom: -8px;
                left: 50%;
                transform: translateX(-50%);
                width: 80px;
                height: 4px;
                background: linear-gradient(90deg, #6366f1, #8b5cf6);
                border-radius: 2px;
            }

            .section-subtitle {
                font-size: 1.2rem;
                color: #6b7280;
                margin-bottom: 2rem;
                max-width: 650px;
                margin-left: auto;
                margin-right: auto;
                line-height: 1.7;
                font-weight: 400;
            }

            .trust-metrics {
                display: flex;
                justify-content: center;
                gap: 3rem;
                margin-bottom: 4rem;
                flex-wrap: wrap;
            }

            .metric {
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .metric-number {
                font-size: 2.5rem;
                font-weight: 900;
                color: #6366f1;
                line-height: 1;
            }

            .metric-label {
                font-size: 0.95rem;
                color: #6b7280;
                font-weight: 500;
                margin-top: 0.5rem;
            }

            .slider-container {
                width: 100%;
                overflow: hidden;
                background: #ffffff;
                position: relative;
                border: 1px solid #e5e7eb;
                padding: 20px 0;
            }

            .slider-track {
                display: flex;
                animation: infiniteSlide 30s linear infinite;
                width: calc(220px * 12);
                gap: 0;
            }

            .logo-slide {
                min-width: 220px;
                height: 120px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                position: relative;
            }

            .logo-container {
                width: 160px;
                height: 80px;
                background: none;
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
            }

            .logo-container::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(99, 102, 241, 0.1), transparent);
                transition: left 0.5s;
            }

            .logo-slide:hover .logo-container {
                transform: translateY(-4px);
                box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                border-color: #c7d2fe;
                background: #ffffff;
            }

            .logo-slide:hover .logo-container::before {
                left: 100%;
            }

            .logo-slide img {
                max-width: 120px;
                max-height: 60px;
                object-fit: contain;
                transition: all 0.3s ease;
                filter: grayscale(0.3) opacity(0.8);
            }

            .logo-slide:hover img {
                filter: grayscale(0) opacity(1);
                transform: scale(1.05);
            }

            /* Placeholder logos for missing images */
            .logo-placeholder {
                width: 120px;
                height: 60px;
                background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                color: #64748b;
                font-size: 0.9rem;
                text-align: center;
                border: 2px dashed #cbd5e1;
            }

            @keyframes infiniteSlide {
                0% {
                    transform: translateX(0);
                }
                100% {
                    transform: translateX(-50%);
                }
            }

            .slider-container:hover .slider-track {
                animation-play-state: paused;
            }

            /* Gradient fade effects */
            .slider-container::before,
            .slider-container::after {
                content: '';
                position: absolute;
                top: 0;
                width: 60px;
                height: 100%;
                z-index: 2;
                pointer-events: none;
            }

            .slider-container::before {
                left: 0;
                background: linear-gradient(to right, #ffffff, transparent);
            }

            .slider-container::after {
                right: 0;
                background: linear-gradient(to left, #ffffff, transparent);
            }

            .cta-section {
                margin-top: 0rem;
                padding: 2rem;
                background: linear-gradient(135deg, #f8fafc, #f1f5f9);
                border-radius: 0px;
                border: 1px solid #e2e8f0;
            }

            .cta-text {
                font-size: 1.1rem;
                color: #475569;
                margin-bottom: 1.5rem;
            }

            .cta-button {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background: #9c80fd;
                color: white;
                padding: 12px 24px;
                border-radius: 12px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
            }

            .cta-button:hover {
                background: #4f46e5;
                transform: translateY(-2px);
                box-shadow: 0 10px 25px -3px rgba(99, 102, 241, 0.3);
            }

            /* Responsive Design */
            @media (max-width: 768px) {
                .section-title {
                    font-size: 2.2rem;
                }
                
                .section-subtitle {
                    font-size: 1.1rem;
                    margin-bottom: 1.5rem;
                }

                .trust-metrics {
                    gap: 2rem;
                    margin-bottom: 3rem;
                }

                .metric-number {
                    font-size: 2rem;
                }

                .slider-track {
                    width: calc(180px * 12);
                }

                .logo-slide {
                    min-width: 180px;
                    height: 100px;
                    padding: 15px;
                }

                .logo-container {
                    width: 140px;
                    height: 70px;
                }

                .logo-slide img,
                .logo-placeholder {
                    max-width: 100px;
                    max-height: 50px;
                    width: 100px;
                    height: 50px;
                }

                .slider-container {
                    padding: 30px 0;
                    border-radius: 20px;
                }
            }

            @media (max-width: 480px) {
                body {
                    padding: 0px;
                }

                .section-title {
                    font-size: 1.8rem;
                }

                .trust-metrics {
                    flex-direction: column;
                    gap: 1.5rem;
                }

                .slider-track {
                    width: calc(150px * 12);
                }

                .logo-slide {
                    min-width: 150px;
                    height: 90px;
                    padding: 10px;
                }

                .logo-container {
                    width: 120px;
                    height: 60px;
                }

                .logo-slide img,
                .logo-placeholder {
                    max-width: 80px;
                    max-height: 40px;
                    width: 80px;
                    height: 40px;
                }
            }

        </style>

        <div class="partners-section">
            <h2 class="section-title">Our Trusted Partners</h2>
            <p class="section-subtitle">
                Connect your stores with carriers across Morocco and manage deliveries effortlessly—no manual work needed.
            </p>

            <div class="trust-metrics">
                <div class="metric">
                    <div class="metric-number">500+</div>
                    <div class="metric-label">Stores Connectés</div>
                </div>
                <div class="metric">
                    <div class="metric-number">10+</div>
                    <div class="metric-label">Partenaires Livraison</div>
                </div>
                <div class="metric">
                    <div class="metric-number">100K+</div>
                    <div class="metric-label">Commandes Synchronisées</div>
                </div>
            </div>


            <div class="slider-container">
                <div class="slider-track">
                    <!-- First set of logos -->
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/cathedis.jpeg" alt="Cathedis" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">Cathedis</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/chronopost.jpeg" alt="Chronopost" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">ChronoPost</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/f.png" alt="F Delivery" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">F Delivery</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/ozone.png" alt="Ozone" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">Ozone</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/power.png" alt="Power" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">Power</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/sendit.jpeg" alt="SendIt" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">SendIt</div>
                        </div>
                    </div>

                    <!-- Duplicate set for seamless loop -->
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/cathedis.jpeg" alt="Cathedis" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">Cathedis</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/chronopost.jpeg" alt="Chronopost" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">ChronoPost</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/f.png" alt="F Delivery" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">F Delivery</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/ozone.png" alt="Ozone" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">Ozone</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/power.png" alt="Power" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">Power</div>
                        </div>
                    </div>
                    <div class="logo-slide">
                        <div class="logo-container">
                            <img src="./assets/img/carriers/sendit.jpeg" alt="SendIt" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="logo-placeholder" style="display: none;">SendIt</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cta-section">
                <p class="cta-text">Ready to simplify your shipping? Sync your store with trusted carriers today.</p>

                <a class="cta-button" href="#pricing">
                    Start Now
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m9 18 6-6-6-6"/>
                    </svg>
                </a>
            </div>
        </div>

        <?php require_once('./assets/footer.php') ?>

    </div>
</body>
</html>