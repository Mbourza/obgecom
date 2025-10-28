<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBG - Built for Sellers</title>
    <!-- Font Awesome 6 (latest) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @font-face {
            font-family: 'Montserrat';
            src: url('./assets/fonts/mont.ttf') format('truetype');
            font-style: normal;
            font-weight: 400; /* optional but recommended */
        }


        :root {
            --primary: #5f34d9;
            --primary-dark: #4f2db0;
            --secondary: #8b5cf6;
            --accent: #a855f7;
            --text-primary: #2f2264;
            --text-secondary: #6b7280;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        body {
            font-family: 'monterat', -apple-system, BlinkMacSystemFont, sans-serif;
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

</head>
<body>
    <div class="bg-decoration"></div>
    <div class="bg-decoration"></div>
    
    <div class="container">
        <style>

            /* Navbar Styles */
            .navbar {
                position: fixed;
                top: 0;
                width: 100%;
                background: rgba(255, 255, 255, 0.08);
                backdrop-filter: blur(20px);
                border-bottom: 1px solid var(--glass-border);
                z-index: 1000;
                transition: all 0.3s ease;
                padding: 0;
            }

            .navbar.scrolled {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(25px);
            }

            .nav-container {
                max-width: 1400px;
                margin: 0 auto;
                padding: 0 2rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .logo {
                font-size: 1.8rem;
                font-weight: 900;
                color: white;
                text-decoration: none;
                background: linear-gradient(135deg, #5f34d9, #8b5cf6);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                width: 20%;
            }

            .logo img {

                width: 60%;
            }

            .navbar.scrolled .logo {
                color: var(--primary);
            }

            .nav-menu {
                display: flex;
                list-style: none;
                gap: 2rem;
                align-items: center;
            }

            .nav-link {
                color: rgba(255, 255, 255, 0.9);
                text-decoration: none;
                font-weight: 500;
                transition: all 0.3s ease;
                position: relative;
                padding: 0.5rem 0;
            }

            .nav-link::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 0;
                height: 2px;
                background: var(--secondary);
                transition: width 0.3s ease;
            }

            .nav-link:hover::after {
                width: 100%;
            }

            .navbar.scrolled .nav-link {
                color: var(--text-primary);
            }

            .nav-cta {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 50px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(95, 52, 217, 0.3);
            }

            .nav-cta:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(95, 52, 217, 0.4);
            }

            .mobile-toggle {
                display: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
            }

            /* Main Content */
            .main-hero {
                display: flex;
                gap: 80px;
                align-items: center;
                padding: 160px 60px 60px;
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
                margin-bottom: 40px;
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
                border-radius: 50px;
                padding: 8px;
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
                border-radius: 40px;
                padding: 16px 24px;
                color: var(--text-primary);
                margin-right: .8em;
                font-size: 15px;
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
                flex: 1.2;
                max-width: 700px;
                position: relative;
                animation: slideInRight 1s ease-out;
            }

            .french-dashboard {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(25px);
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
                border: 1px solid rgba(255, 255, 255, 0.3);
                margin-bottom: 30px;
                transition: all 0.3s ease;
            }

            .french-dashboard:hover {
                transform: translateY(-5px);
                box-shadow: 0 35px 70px rgba(0, 0, 0, 0.2);
            }

            .dashboard-header {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                padding: 16px 20px;
                display: grid;
                grid-template-columns: 30px 80px 120px 30px 80px 50px 80px 90px 90px 80px 80px;
                gap: 12px;
                font-size: 11px;
                font-weight: 600;
                text-align: center;
                align-items: center;
            }

            .order-entry {
                display: grid;
                grid-template-columns: 30px 80px 120px 30px 80px 50px 80px 90px 90px 80px 80px;
                gap: 12px;
                padding: 16px 20px;
                border-bottom: 1px solid #f3f4f6;
                align-items: center;
                font-size: 12px;
                color: #374151;
                transition: all 0.3s ease;
            }

            .order-entry:hover {
                background: rgba(95, 52, 217, 0.05);
                transform: translateX(5px);
            }

            .order-entry:nth-child(even) {
                background: #fafafa;
            }

            .order-entry:last-child {
                border-bottom: none;
            }

            .order-checkbox {
                width: 16px;
                height: 16px;
                accent-color: var(--primary);
            }

            .barcode {
                width: 30px;
                height: 20px;
                background: linear-gradient(90deg, #000 1px, transparent 1px, transparent 2px, #000 2px, #000 3px, transparent 3px, transparent 4px, #000 4px);
                background-size: 6px 100%;
                border-radius: 2px;
            }

            .client-info {
                text-align: left;
            }

            .client-name {
                font-weight: 600;
                color: #111827;
                font-size: 13px;
                margin-bottom: 2px;
            }

            .client-email {
                color: #6b7280;
                font-size: 11px;
            }

            .whatsapp-icon {
                width: 24px;
                height: 24px;
                background: #25d366;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 12px;
                margin: 0 auto;
                transition: all 0.3s ease;
            }

            .whatsapp-icon:hover {
                transform: scale(1.1);
            }

            .city-badge {
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: white;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 500;
                text-align: center;
                min-width: 20px;
            }

            .amount {
                font-weight: 600;
                color: #111827;
                text-align: right;
            }

            .status-badge {
                padding: 6px 10px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 600;
                text-align: center;
                text-transform: uppercase;
            }

            .status-en-attente {
                background: linear-gradient(135deg, #fef3c7, #fde68a);
                color: #d97706;
            }

            .status-non-soumis {
                background: linear-gradient(135deg, #fee2e2, #fecaca);
                color: #dc2626;
            }

            .action-button {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 8px;
                font-size: 11px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
            }

            .action-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(95, 52, 217, 0.3);
            }

            .action-select {
                background: white;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 6px 10px;
                font-size: 11px;
                color: #374151;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .action-select:hover {
                border-color: var(--primary);
            }

            /* Delivery Partners */
            .delivery-partners {
                display: flex;
                justify-content: space-between;
                align-items: center;
                position: relative;
                margin-top: 30px;
                padding: 0 10px;
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
                z-index: 3;
                cursor: pointer;
            }

            .partner-circle:hover {
                transform: translateY(-10px) scale(1.05);
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
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
                font-size: 28px;
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

            .partner-text {
                font-size: 10px;
                margin-top: 5px;
            }

            /* Connection Line */
            .connection-line {
                position: absolute;
                top: 90%;
                left: 10%;
                right: 10%;
                height: 3px;
                background: linear-gradient(90deg, #FFF, #FFF, var(--primary));
                border-radius: 2px;
                z-index: 1;
                animation: pulse 2s infinite;
            }

            /* Integration Message */
            .integration-message {
                text-align: center;
                margin: 50px 0;
                padding: 30px;
                background: var(--glass-bg);
                backdrop-filter: blur(20px);
                border-radius: 20px;
                border: 1px solid var(--glass-border);
                color: white;
            }

            .integration-message h3 {
                margin-bottom: 15px;
                font-size: 1.5rem;
                background: linear-gradient(135deg, #ffffff, #e0e7ff);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .integration-message p {
                color: rgba(255, 255, 255, 0.9);
                max-width: 800px;
                margin: 0 auto;
                line-height: 1.6;
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
                
                .dashboard-header,
                .order-entry {
                    grid-template-columns: 25px 70px 100px 25px 70px 40px 70px 80px 80px 70px 70px;
                    gap: 8px;
                    font-size: 10px;
                }

                .content-left h1 {
                    font-size: 3rem;
                }
            }

            @media (max-width: 968px) {
                .nav-menu {
                    position: fixed;
                    top: 80px;
                    left: -100%;
                    width: 100%;
                    height: calc(100vh - 80px);
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(25px);
                    flex-direction: column;
                    justify-content: flex-start;
                    padding-top: 2rem;
                    transition: left 0.3s ease;
                }

                .nav-menu.active {
                    left: 0;
                }

                .nav-link {
                    color: var(--text-primary);
                    font-size: 1.2rem;
                    margin: 1rem 0;
                }

                .mobile-toggle {
                    display: block;
                }

                .navbar.scrolled .mobile-toggle {
                    color: var(--text-primary);
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

                .dashboard-container {
                    max-width: 100%;
                }

                .french-dashboard {
                    overflow-x: auto;
                }

                .delivery-partners {
                    flex-wrap: wrap;
                    justify-content: center;
                    gap: 20px;
                }

                .partner-circle {
                    width: 70px;
                    height: 70px;
                    font-size: 12px;
                }

                .connection-line {
                    display: none;
                }

                .feature-badges {
                    justify-content: center;
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
                    padding: 100px 15px 30px;
                }

                .dashboard-header,
                .order-entry {
                    font-size: 9px;
                    padding: 12px 15px;
                }
            }
        </style>

        <nav class="navbar" id="navbar">
            <div class="nav-container">
                <a href="#" class="logo"><img src="./assets/img/logoIndex.png" /></a>
                <ul class="nav-menu" id="nav-menu">
                    <li><a href="#features" class="nav-link">Features</a></li>
                    <li><a href="#integrations" class="nav-link">Integrations</a></li>
                    <li><a href="#pricing" class="nav-link">Pricing</a></li>
                    <li><a href="#support" class="nav-link">Support</a></li>
                    <li><a href="#" class="nav-cta">Start Free Trial</a></li>
                </ul>
                <div class="mobile-toggle" id="mobile-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </nav>

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
                    <input type="text" class="search-input" placeholder="Enter your email to start your journey">
                    <button class="search-button">
                        <i class="fas fa-rocket" style="margin-right: 8px;"></i>
                        Start Free Trial
                    </button>
                </div>
            </div>

            <div class="dashboard-container">
                <div class="french-dashboard">
                    <div class="dashboard-header">
                        <div></div>
                        <div>CODE D'ENVOI</div>
                        <div>CLIENT</div>
                        <div>TEL</div>
                        <div>VILLE</div>
                        <div>ARTICLES</div>
                        <div>MONTANT</div>
                        <div>STATUT</div>
                        <div>CONFIRMATION</div>
                        <div>AGENT</div>
                        <div>MAGASIN</div>
                    </div>

                    <div class="order-entry">
                        <input type="checkbox" class="order-checkbox">
                        <div class="barcode"></div>
                        <div class="client-info">
                            <div class="client-name">Mohamed</div>
                            <div class="client-email">alienboughaza@gmail.com</div>
                        </div>
                        <div class="whatsapp-icon">W</div>
                        <div>Ouarzazate</div>
                        <div class="city-badge">1</div>
                        <div class="amount">35.00<br>MAD</div>
                        <div class="status-badge status-en-attente">En Attente</div>
                        <button class="action-button">Expédier</button>
                        <div>Non assigné</div>
                        <div>joudbee</div>
                    </div>

                    <div class="order-entry">
                        <input type="checkbox" class="order-checkbox">
                        <div class="barcode"></div>
                        <div class="client-info">
                            <div class="client-name">Mohamed</div>
                            <div class="client-email">alienboughaza@gmail.com</div>
                        </div>
                        <div class="whatsapp-icon">W</div>
                        <div>Ouarzazate</div>
                        <div class="city-badge">1</div>
                        <div class="amount">60.00<br>MAD</div>
                        <div class="status-badge status-en-attente">En Attente</div>
                        <select class="action-select">
                            <option>PAS DE RÉPONSE</option>
                        </select>
                        <div>Non assigné</div>
                        <div>joudbee</div>
                    </div>

                    <div class="order-entry">
                        <input type="checkbox" class="order-checkbox">
                        <div class="barcode"></div>
                        <div class="client-info">
                            <div class="client-name">Ali Bennani</div>
                            <div class="client-email">alibennani@me.com</div>
                        </div>
                        <div class="whatsapp-icon">W</div>
                        <div>Casablanca</div>
                        <div class="city-badge">1</div>
                        <div class="amount">35.00<br>MAD</div>
                        <div class="status-badge status-en-attente">En Attente</div>
                        <button class="action-button">Expédier</button>
                        <div>Non assigné</div>
                        <div>joudbee</div>
                    </div>

                    <div class="order-entry">
                        <input type="checkbox" class="order-checkbox">
                        <div class="barcode"></div>
                        <div class="client-info">
                            <div class="client-name">Ali Bennani</div>
                            <div class="client-email">alibennani@me.com</div>
                        </div>
                        <div class="whatsapp-icon">W</div>
                        <div>Casablanca</div>
                        <div class="city-badge">2</div>
                        <div class="amount">150.00<br>MAD</div>
                        <div class="status-badge status-en-attente">En Attente</div>
                        <button class="action-button">Expédier</button>
                        <div>Non assigné</div>
                        <div>joudbee</div>
                    </div>

                    <div class="order-entry">
                        <input type="checkbox" class="order-checkbox">
                        <div class="barcode"></div>
                        <div class="client-info">
                            <div class="client-name">Ali Bennani</div>
                            <div class="client-email">alibennani@me.com</div>
                        </div>
                        <div class="whatsapp-icon">W</div>
                        <div>Casablanca</div>
                        <div class="city-badge">6</div>
                        <div class="amount">450.00<br>MAD</div>
                        <div class="status-badge status-non-soumis">Non Soumis</div>
                        <select class="action-select">
                            <option>ANNULÉE</option>
                        </select>
                        <div>Allen Boughaza pending</div>
                        <div>joudbee</div>
                    </div>
                </div>

                <div class="connection-line"></div>

                <div class="delivery-partners">
                    <div class="partner-circle partner-cathedis">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">Cathedis</div>
                        <div class="discount-badge">50% OFF</div>
                    </div>
                    <div class="partner-circle partner-dzon">
                        <i class="fas fa-shipping-fast"></i>
                        <div class="partner-text">DZON Express</div>
                        <div class="discount-badge">50% OFF</div>
                    </div>
                    <div class="partner-circle partner-sendit">
                        <i class="fas fa-box"></i>
                        <div class="partner-text">SendIt</div>
                        <div class="discount-badge">50% OFF</div>
                    </div>
                    <div class="partner-circle partner-power">
                        <i class="fas fa-bolt"></i>
                        <div class="partner-text">Power</div>
                        <div class="discount-badge">50% OFF</div>
                    </div>
                    <div class="partner-circle partner-delivery">
                        <i class="fas fa-globe"></i>
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
                    padding: 40px 24px;
                }

                .feature-title {
                    font-size: 24px;
                }

                .feature-description {
                    font-size: 16px;
                }

                .process-flow {
                    flex-wrap: wrap;
                    justify-content: center;
                    gap: 12px;
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
                                    flex-direction: column;
                                    gap: 30px;
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

            .how-section {
                padding: 80px 100px;
                max-width: 1400px;
                margin: 0 auto;
            }

            .section-header {
                text-align: center;
                margin-bottom: 60px;
            }

            .section-title3 {
                font-size: 32px;
                font-weight: 800;
                color: #2f2264;
                margin-bottom: 12px;
                letter-spacing: -0.5px;
            }

            .section-subtitle {
                font-size: 18px;
                color: #64748b;
            }

            .process-flow2 {
                display: grid;
                gap: 0px;
            }

            .process-step2 {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 60px;
                align-items: center;
                opacity: 0;
                transform: translateY(30px);
                animation: fadeInUp 0.8s ease-out forwards;
            }

            .process-step2:nth-child(1) { animation-delay: 0.1s; }
            .process-step2:nth-child(2) { animation-delay: 0.2s; }
            .process-step2:nth-child(3) { animation-delay: 0.3s; }
            .process-step2:nth-child(4) { animation-delay: 0.4s; }

            .process-step2:nth-child(even) {
                direction: rtl;
            }

            .process-step2:nth-child(even) .step-content2,
            .process-step2:nth-child(even) .step-visual {
                direction: ltr;
            }

            .step-content2 {
                background: linear-gradient(135deg, #e1d7f5, #e1d7f5);
                color: white;
                padding: 40px;
                border-radius: 24px;
                box-shadow: 0 20px 40px rgba(139, 92, 246, 0.2);
                position: relative;
                overflow: hidden;
            }

            .step-content2::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1" fill="white" opacity="0.1"/><circle cx="80" cy="40" r="0.5" fill="white" opacity="0.1"/><circle cx="40" cy="80" r="0.8" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
                pointer-events: none;
            }

            .step-number {
                font-size: 30px;
                font-weight: 600;
                opacity: 0.9;
                position: absolute;
                color: #2f2264;
                top: 20px;
                right: 30px;
                line-height: 1;
            }

            .step-title {
                font-size: 38px;
                font-weight: 700;
                margin-bottom: 16px;
                position: relative;
                color: #2f2264;
                z-index: 1;
            }

            .step-description {
                font-size: 20px;
                line-height: 1.6;
                opacity: 0.95;
                position: relative;
                color: #2f2264;
                z-index: 1;
            }

            .step-features {
                list-style: none;
                margin-top: 20px;
                position: relative;
                z-index: 1;
                padding: 0;
            }

            .step-features li {
                margin: 8px 0;
                padding-left: 20px;
                position: relative;
                color: #2f2264;
                font-size: 20px;
            }

            .step-features li::before {
                content: '•';
                position: absolute;
                left: 0;
                color: #2f2264;
                font-weight: bold;
            }

            .step-visual {
                background: none;
                border-radius: 20px;
                padding: 20px;
                border: 1px solid #e2e8f0;
                position: relative;
                overflow: hidden;
            }

            /* Screenshot styling */
            .screenshot-container {
                width: 100%;
                height: 400px;
                background: none;
                border-radius: 0px;
                display: flex;
                align-items: center;
                justify-content: center;
                border: none;
                position: relative;
                overflow: hidden;
            }

            .screenshot-placeholder {
                text-align: center;
                color: #64748b;
                font-size: 16px;
                font-weight: 500;
            }

            .screenshot-placeholder i {
                font-size: 48px;
                margin-bottom: 16px;
                display: block;
                color: #94a3b8;
            }

            .screenshot-placeholder p {
                margin: 8px 0;
                font-size: 14px;
            }

            .screenshot-placeholder .recommended-size {
                font-size: 12px;
                color: #94a3b8;
                margin-top: 12px;
            }

            /* Actual screenshot styling when images are added */
            .screenshot-container img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                border-radius: 8px;
                transition: transform 0.3s ease;
            }

            .screenshot-container:hover img {
                transform: scale(1.02);
            }

            /* Step-specific placeholders */
            .step-1-screenshot .screenshot-placeholder i::before { content: '\f0c1'; }
            .step-2-screenshot .screenshot-placeholder i::before { content: '\f013'; }
            .step-3-screenshot .screenshot-placeholder i::before { content: '\f080'; }
            .step-4-screenshot .screenshot-placeholder i::before { content: '\f201'; }

            /* Animations */
            @keyframes fadeInUp {
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Responsive Design */
            @media (max-width: 1200px) {
                .how-section {
                    padding: 60px 50px;
                }
            }

            @media (max-width: 768px) {
                .how-section {
                    padding: 40px 20px;
                }

                .process-step2 {
                    grid-template-columns: 1fr;
                    gap: 30px;
                }

                .process-step2:nth-child(even) {
                    direction: ltr;
                }

                .step-content2 {
                    padding: 30px;
                }

                .step-title {
                    font-size: 28px;
                }

                .step-description {
                    font-size: 18px;
                }

                .step-features li {
                    font-size: 18px;
                }

                .screenshot-container {
                    height: 300px;
                }
            }

            @media (max-width: 480px) {
                .section-title3 {
                    font-size: 28px;
                }

                .step-title {
                    font-size: 24px;
                }

                .step-description {
                    font-size: 16px;
                }

                .step-features li {
                    font-size: 16px;
                }
            }
        </style>

        <div class="how-section">
            <div class="section-header" style="margin-top: 3em;">
                <h2 class="section-title3">HOW IT WORKS</h2>
                <p class="section-subtitle">An order comes in. OBGecom does the rest.</p>
            </div>

            <div class="process-flow2">
                <!-- Step 1: Connect -->
                <div class="process-step2">
                    <div class="step-content2">
                        <div class="step-number">1</div>
                        <h3 class="step-title">Connect your store & carriers</h3>
                        <p class="step-description">Integrate OBGecom with your e-commerce platform in a few clicks.</p>
                    </div>
                    <div class="step-visual">
                        <div class="screenshot-container step-1-screenshot">
                            <img src="./assets/img/image5.jpg" alt="Integration Dashboard">
                        </div>
                    </div>
                </div>

                <!-- Step 2: Automation -->
                <div class="process-step2">
                    <div class="step-content2">
                        <div class="step-number">2</div>
                        <h3 class="step-title">Automation kicks in</h3>
                        <ul class="step-features">
                            <li>Order data captured automatically.</li>
                            <li>Best carrier assigned instantly.</li>
                            <li>Customer notified immediately.</li>
                            <li>Tracking starts in real time.</li>
                        </ul>
                    </div>
                    <div class="step-visual">
                        <div class="screenshot-container step-2-screenshot">
                            <!-- Replace with actual screenshot: -->
                            <img src="./assets/img/image6.jpg" alt="Automation Dashboard">
                        </div>
                    </div>
                </div>

                <!-- Step 3: Monitor -->
                <div class="process-step2">
                    <div class="step-content2">
                        <div class="step-number">3</div>
                        <h3 class="step-title">Monitor from one dashboard</h3>
                        <p class="step-description">Stay in control with live updates and instant alerts</p>
                    </div>
                    <div class="step-visual">
                        <div class="screenshot-container step-3-screenshot">
                            <!-- Replace with actual screenshot: -->
                            <img src="./assets/img/image7.jpg" alt="Analytics Dashboard">
                        </div>
                    </div>
                </div>

                <!-- Step 4: Growth -->
                <div class="process-step2">
                    <div class="step-content2">
                        <div class="step-number">4</div>
                        <h3 class="step-title">Focus on growth</h3>
                        <p class="step-description">Stop firefighting logistics and spend time scaling your business.</p>
                    </div>
                    <div class="step-visual">
                        <div class="screenshot-container step-4-screenshot">
                            <!-- Replace with actual screenshot: -->
                            <img src="./assets/img/image8.jpg" alt="Growth Analytics">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>

            /* Testimonials Section */
            .testimonials-section {
                background-color: #e8eaf6;
                padding: 80px 40px;
                text-align: center;
            }

            .testimonials-title {
                font-size: 3rem;
                font-weight: 800;
                color: #3f51b5;
                margin-bottom: 8px;
                letter-spacing: 4px;
            }

            .testimonials-subtitle {
                font-size: 1.2rem;
                color: #666;
                margin-bottom: 60px;
                font-weight: 400;
            }

            .testimonials-container {
                max-width: 800px;
                margin: 0 auto;
            }

            .testimonial-item {
                display: flex;
                align-items: flex-start;
                gap: 25px;
                margin-bottom: 50px;
                text-align: left;
            }

            .testimonial-avatar {
                width: 70px;
                height: 70px;
                background-color: #2c2c2c;
                border-radius: 50%;
                flex-shrink: 0;
                position: relative;
            }

            .testimonial-avatar::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 35px;
                height: 35px;
                background-color: #666;
                border-radius: 50%;
            }

            .testimonial-content {
                flex: 1;
            }

            .testimonial-name {
                font-size: 1.1rem;
                font-weight: 700;
                color: #333;
                margin-bottom: 2px;
            }

            .testimonial-company {
                font-size: 1rem;
                color: #818397;
                font-weight: 500;
                margin-bottom: 12px;
            }

            .testimonial-rating {
                margin-bottom: 15px;
            }

            .star {
                color: #ffa726;
                font-size: 18px;
                margin-right: 2px;
            }

            .testimonial-text {
                font-size: 1rem;
                color: #444;
                line-height: 1.6;
                font-weight: 400;
            }


            @media (max-width: 1200px) {
                .pricing-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px;
                }
            }

            @media (max-width: 768px) {
                .pricing-grid {
                    grid-template-columns: 1fr;
                }
                
                .testimonials-title,
                .pricing-title {
                    font-size: 2.2rem;
                }
                
                .testimonial-item {
                    gap: 20px;
                }
                
                .testimonial-avatar {
                    width: 60px;
                    height: 60px;
                }
                
                .testimonials-section,
                .pricing-section {
                    padding: 60px 20px;
                }
            }
        </style>

        <!-- Testimonials Section -->
        <section class="testimonials-section">
            <h2 class="testimonials-title">TESTIMONIALS</h2>
            <p class="testimonials-subtitle">Trusted by Moroccan merchants</p>
            
            <div class="testimonials-container">
                <div class="testimonial-item">
                    <div class="testimonial-avatar"></div>
                    <div class="testimonial-content">
                        <div class="testimonial-name">Mouna, Aghebala -</div>
                        <div class="testimonial-company">TIMICHA Cooperative</div>
                        <div class="testimonial-rating">
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                        </div>
                        <p class="testimonial-text">
                            Before OBGecom, my team spent hours every night fixing addresses and chasing couriers. 
                            Now everything runs on autopilot. We save 3+ hours a day and can finally focus on growth.
                        </p>
                    </div>
                </div>
                
                <div class="testimonial-item">
                    <div class="testimonial-avatar"></div>
                    <div class="testimonial-content">
                        <div class="testimonial-name">Salma, Rabat -</div>
                        <div class="testimonial-company">Natural Cosmetics Brand</div>
                        <div class="testimonial-rating">
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                            <span class="star">★</span>
                        </div>
                        <p class="testimonial-text">
                            I thought automation would be expensive or complex. OBGecom connected to 
                            my store in minutes, and deliveries are smooth. More repeat sales, happier customers.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <style>
            /* Global Styles */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            body {
                background-color: #f8f9fa;
            }
            
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
                color: #999;
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

            .pricing-card.professional .plan-header {
                color: #6a1b9a;
            }

            .pricing-card.professional .plan-price {
                color: #6a1b9a;
            }

            .pricing-card.professional .plan-features li::before {
                color: #6a1b9a;
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

            .pricing-card.business .plan-header {
                color: #4a148c;
            }

            .pricing-card.business .plan-price {
                color: #4a148c;
            }

            .pricing-card.business .plan-features li::before {
                color: #4a148c;
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
        <section class="pricing-section">
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
                        <li>Up to 1,000 orders/month</li>
                        <li>2 store integrations</li>
                        <li>2 carrier integrations</li>
                        <li>Priority email support</li>
                    </ul>
                    
                    <div class="plan-description">(Perfect for growing shops)</div>
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
                        <li>Up to 4,000 orders/month</li>
                        <li>4 store integrations</li>
                        <li>5 carrier integrations</li>
                        <li>Up to 6 team members</li>
                        <li>Premium WhatsApp & Email support</li>
                    </ul>
                    
                    <div class="plan-description">(For established sellers scaling fast)</div>
                </div>
                
                <!-- Business Plan -->
                <div class="pricing-card business">
                    <div class="plan-header">BUSINESS</div>
                    <div class="pricing-display">
                        <div class="plan-price">
                            <span class="currency">MAD</span>
                            <span class="amount">450</span>
                            <span class="period">/month</span>
                        </div>
                        <div class="original-price">750 MAD flexible</div>
                    </div>
                    
                    <ul class="plan-features">
                        <li>Up to 30,000 orders/month</li>
                        <li>Unlimited stores, carriers & users</li>
                        <li>Dedicated Account Manager</li>
                        <li>Advanced support via WhatsApp, Email & Live Chat</li>
                    </ul>
                    
                    <div class="plan-description">(Complete solution for high-volume merchants)</div>
                </div>
            </div>
        </section>

        <style>

            /* Footer Section */
            .footer-section {
                background-color: #d1c4e9;
                padding: 40px 40px 30px 40px;
                border-top: 6px solid #5f34d9;
            }

            .footer-container {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .footer-logo {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .logo-icon {

                width: 55px;
                height: 55px;
                background: linear-gradient(135deg, #9c27b0, #673ab7);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 24px;
                font-weight: bold;
            }

            .logo-text {
                color: #673ab7;
            }

            .logo-text h3 {
                font-size: 2.2rem;
                font-weight: 800;
                margin: 0;
                letter-spacing: 1px;
            }

            .logo-text p {
                font-size: 0.9rem;
                color: #666;
                margin: 0;
                font-weight: 500;
            }

            .footer-right {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 15px;
            }

            .social-icons {
                display: flex;
                gap: 15px;
            }

            .social-icon {
                width: 45px;
                height: 45px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 20px;
                text-decoration: none;
                transition: transform 0.3s ease;
            }

            .social-icon:hover {
                transform: scale(1.1);
            }

            .social-icon.instagram {
                background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
            }

            .social-icon.linkedin {
                background: #0077b5;
            }

            .social-icon.tiktok {
                background: #000;
            }

            .footer-email {
                font-size: 0.95rem;
                color: #666;
                font-weight: 500;
            }

            .footer-links {
                text-align: center;
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid rgba(103, 58, 183, 0.2);
            }

            .footer-links a {
                color: #673ab7;
                text-decoration: none;
                font-size: 1rem;
                font-weight: 500;
                margin: 0 15px;
                transition: color 0.3s ease;
            }

            .footer-links a:hover {
                color: #9c27b0;
            }

            .footer-separator {
                color: #999;
                margin: 0 5px;
            }

            /* Footer Mobile Responsive */
            @media (max-width: 768px) {
                .footer-container {
                    flex-direction: column;
                    gap: 20px;
                    text-align: center;
                }

                .footer-right {
                    align-items: center;
                }

                .footer-links a {
                    display: block;
                    margin: 10px 0;
                }

                .footer-separator {
                    display: none;
                }
            }
        </style>

        <!-- Footer Section -->
        <footer class="footer-section">
            <div class="footer-container">
                <div class="footer-logo">
                    <img src="./assets/img/dark_logo.png" style="width:25%" alt="OBG Logo">
                </div>
                
                <div class="footer-right">
                    <div class="social-icons">
                        <a href="#" class="social-icon instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-icon linkedin">
                            <i class="fab fa-linkedin"></i>
                        </a>
                        <a href="#" class="social-icon tiktok">
                            <i class="fab fa-tiktok"></i>
                        </a>
                    </div>

                    <div class="footer-email">contact.be.obg@gmail.com</div>
                </div>
            </div>
            
            <div class="footer-links">
                <a href="#">Mentions légales</a>
                <span class="footer-separator">|</span>
                <a href="#">Politique de confidentialité</a>
                <span class="footer-separator">|</span>
                <a href="#">Contact</a>
            </div>
        </footer>

    </div>
</body>
</html>