<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="OBGecom est votre partenaire digital au Maroc : solutions e-commerce, gestion des commandes, sites web sur mesure et applications personnalisées pour booster votre business en ligne.">
    <meta name="keywords" content="OBGecom, e-commerce, gestion des commandes, site web sur mesure, application personnalisée, Maroc">
    <title>OBGecom - Conçu pour les Vendeurs</title>
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

        /* ============================================
        RESPONSIVE TABLETTE & MOBILE
        ============================================ */

        @media (max-width: 1024px) {
            .main-hero {
                padding: 120px 30px 50px;
                gap: 40px;
            }
            .content-left h1 {
                font-size: 2.8rem;
            }
            .dashboard-container {
                max-width: 55%;
            }
            .dashboard-table th,
            .dashboard-table td {
                padding: 12px 8px;
                font-size: 11px;
            }
            .col-client { min-width: 100px; }
            .col-action { min-width: 100px; }
            .action-select {
                font-size: 10px;
                padding: 6px 8px;
            }
        }

        @media (max-width: 768px) {
            .main-hero {
                flex-direction: column;
                padding: 100px 20px 40px;
                text-align: center;
            }
            .content-left {
                max-width: 100%;
                text-align: center;
            }
            .content-left h1 {
                font-size: 2.2rem;
            }
            .content-left p {
                font-size: 1rem;
            }
            .feature-badges {
                justify-content: center;
            }
            .badge {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
            }
            .search-container {
                flex-direction: column;
                background: transparent;
                backdrop-filter: none;
                padding: 0;
                gap: 12px;
            }
            .search-input {
                width: 100%;
                padding: 12px 16px;
                border-radius: 50px;
                margin-right: 0;
            }
            .search-button {
                width: 100%;
                padding: 12px 20px;
                text-align: center;
                font-size: 14px;
            }
            .dashboard-container {
                max-width: 100%;
                overflow-x: auto;
                border-radius: 16px;
            }
            .french-dashboard,
            .english-dashboard {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .dashboard-table {
                min-width: 700px;
            }
            .dashboard-table th,
            .dashboard-table td {
                padding: 10px 8px;
                font-size: 10px;
                white-space: nowrap;
            }
            .action-select {
                font-size: 10px;
                padding: 5px 6px;
                min-width: 90px;
            }
            .delivery-partners {
                flex-wrap: wrap;
                justify-content: center;
                gap: 15px;
                padding: 20px 10px;
            }
            .partner-circle {
                width: 70px;
                height: 70px;
            }
            .partner-circle i {
                font-size: 20px;
            }
            .partner-text {
                font-size: 8px;
            }
            .discount-badge {
                font-size: 7px;
                padding: 2px 6px;
                bottom: -6px;
            }
            .wo-hdr {
                padding: 50px 20px 40px;
            }
            .wo-h1 {
                font-size: 2.2rem;
            }
            .wo-sub {
                font-size: 0.85rem;
                padding: 0 10px;
            }
            .wo-card {
                grid-template-columns: 1fr;
                min-height: auto;
            }
            .wo-info {
                padding: 35px 22px;
            }
            .wo-ctitle {
                font-size: 1.8rem;
            }
            .wo-cdesc {
                font-size: 0.85rem;
                max-width: 100%;
            }
            .wo-vis {
                padding: 30px 20px;
                border-left: none;
                border-top: 1px solid var(--border);
            }
            .wo-vis.left-side {
                border-right: none;
            }
            .kpi-row {
                flex-wrap: wrap;
                gap: 15px;
                justify-content: space-between;
            }
            .kpi-item {
                flex: 1;
                min-width: 80px;
            }
            .flow-track {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            .farr {
                transform: rotate(90deg);
                margin: 0;
            }
            .loyalty-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .lg-card {
                padding: 10px 8px;
            }
            .lg-val {
                font-size: 18px;
            }
            .llbl {
                font-size: 8px;
            }
            .vip-row {
                padding: 8px 10px;
                gap: 8px;
            }
            .vip-avatar {
                width: 28px;
                height: 28px;
                font-size: 9px;
            }
            .vip-name {
                font-size: 10px;
            }
            .vip-sub {
                font-size: 8px;
            }
            .vip-badge {
                font-size: 7px;
                padding: 2px 6px;
            }
            .team-bars {
                gap: 8px;
            }
            .tb-name {
                font-size: 10px;
                min-width: 45px;
            }
            .tb-pct {
                font-size: 12px;
                min-width: 28px;
            }
            .team-stat {
                flex-wrap: wrap;
                gap: 8px;
            }
            .ts-card {
                flex: 1;
                min-width: 80px;
                padding: 8px 4px;
            }
            .ts-val {
                font-size: 16px;
            }
            .ts-lbl {
                font-size: 8px;
            }
            .highlight-box {
                flex-wrap: wrap;
                gap: 10px;
            }
            .hb-card {
                flex: 1;
                min-width: 80px;
                padding: 10px 5px;
            }
            .hb-val {
                font-size: 18px;
            }
            .integrations-section {
                grid-template-columns: 1fr;
                padding: 40px 20px;
                gap: 40px;
            }
            .content-left2 {
                text-align: center;
                padding-right: 0;
            }
            .main-heading {
                font-size: 1.8rem;
            }
            .description {
                font-size: 0.9rem;
            }
            .api-illustration {
                justify-content: center;
                margin-top: 20px;
            }
            .management-interface {
                padding: 20px;
            }
            .interface-header {
                flex-wrap: wrap;
                justify-content: center;
                text-align: center;
                gap: 10px;
            }
            .interface-title {
                font-size: 16px;
            }
            .sync-button {
                padding: 8px 16px;
                font-size: 12px;
            }
            .integration-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .integration-card {
                padding: 20px;
            }
            .wrap {
                padding: 50px 16px 60px;
            }
            .headline {
                font-size: 1.8rem;
            }
            .sub {
                font-size: 0.85rem;
                padding: 0 10px;
            }
            .card {
                grid-template-columns: 1fr;
                border-radius: 20px;
                margin-bottom: 20px;
            }
            .info {
                padding: 30px 20px;
            }
            .panel {
                padding: 25px 20px;
                border-left: none;
                border-top: 1px solid var(--border);
                flex-direction: column !important;
            }
            .card:nth-child(4n-1) .panel {
                border-right: none;
                border-top: 1px solid var(--border);
            }
            .step-num {
                margin-bottom: 15px;
            }
            .card-title {
                font-size: 1.5rem;
            }
            .connector {
                display: none;
            }
            .net-wrap {
                flex-direction: column;
                gap: 15px;
            }
            .net-mid {
                flex-direction: row;
                justify-content: center;
            }
            .prc-wrap {
                padding: 50px 16px;
            }
            .prc-heading {
                font-size: 1.8rem;
            }
            .prc-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .prc-card {
                max-width: 100%;
            }
            .prc-amount {
                font-size: 2.5rem;
            }
            .partners-section {
                padding: 50px 16px;
            }
            .section-title {
                font-size: 1.8rem;
            }
            .section-subtitle {
                font-size: 0.9rem;
                padding: 0 10px;
            }
            .trust-metrics {
                flex-direction: row;
                flex-wrap: wrap;
                justify-content: center;
                gap: 25px;
            }
            .metric-number {
                font-size: 1.8rem;
            }
            .metric-label {
                font-size: 0.75rem;
            }
            .slider-track {
                width: calc(160px * 12);
            }
            .logo-slide {
                min-width: 160px;
                height: 90px;
            }
            .logo-container {
                width: 130px;
                height: 65px;
            }
            .cta-section {
                margin-top: 30px;
                padding: 25px 15px;
            }
            .cta-text {
                font-size: 0.9rem;
            }
            .nav-container {
                padding: 0 15px;
            }
            body {
                overflow-x: hidden;
            }
            .container {
                overflow-x: hidden;
            }
            button,
            .action-button,
            .search-button,
            .cta-button,
            .prc-cta,
            .sync-button {
                cursor: pointer;
                min-height: 44px;
            }
            .action-select {
                min-height: 36px;
            }
            .sm-row,
            .orow {
                padding: 10px 12px;
            }
            .ship-row {
                flex-wrap: wrap;
                gap: 8px;
            }
            .ship-id {
                min-width: 55px;
            }
        }

        @media (max-width: 480px) {
            .main-hero {
                padding: 80px 15px 30px;
            }
            .content-left h1 {
                font-size: 1.8rem;
            }
            .content-left p {
                font-size: 0.9rem;
            }
            .wo-h1 {
                font-size: 1.8rem;
            }
            .wo-ctitle {
                font-size: 1.5rem;
            }
            .wo-info {
                padding: 25px 16px;
            }
            .wo-vis {
                padding: 20px 16px;
            }
            .kpi-row {
                gap: 10px;
            }
            .kpi-val {
                font-size: 20px;
            }
            .kpi-lbl {
                font-size: 8px;
            }
            .loyalty-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .lg-card {
                padding: 8px 5px;
            }
            .lg-icon {
                width: 28px;
                height: 28px;
            }
            .lg-icon svg {
                width: 12px;
                height: 12px;
            }
            .lg-val {
                font-size: 16px;
            }
            .team-bars {
                gap: 12px;
            }
            .tb-row {
                gap: 8px;
            }
            .tb-name {
                font-size: 11px;
                min-width: 55px;
                text-align: left;
            }
            .tb-pct {
                font-size: 11px;
            }
            .stats-row {
                flex-wrap: wrap;
            }
            .stat-pill {
                min-width: 80px;
            }
            .prc-header {
                padding: 1.2rem 1rem 0.8rem;
            }
            .prc-feats {
                padding: 1rem;
            }
            .prc-feat {
                font-size: 12px;
                gap: 8px;
            }
            .prc-cta {
                margin: 1rem 1rem 1.2rem;
                padding: 12px;
                font-size: 13px;
            }
            .partner-circle {
                width: 60px;
                height: 60px;
            }
            .partner-circle i {
                font-size: 18px;
            }
            .slider-container::before,
            .slider-container::after {
                width: 30px;
            }
        }

        @media (max-width: 900px) and (orientation: landscape) {
            .main-hero {
                padding: 90px 20px 30px;
            }
            .dashboard-container {
                max-height: 450px;
            }
            .french-dashboard {
                max-height: 400px;
            }
            .wo-card {
                min-height: auto;
            }
        }

        @supports (padding: max(0px)) {
            .main-hero {
                padding-top: max(100px, env(safe-area-inset-top));
                padding-left: max(20px, env(safe-area-inset-left));
                padding-right: max(20px, env(safe-area-inset-right));
            }
            .wo-hdr {
                padding-top: max(50px, env(safe-area-inset-top));
            }
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

            .status-confirmee {
                background: linear-gradient(135deg, #d1fae5, #a7f3d0);
                color: #059669;
                border: 1px solid #10b981;
            }

            .status-annulee {
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
                .search-button {
                    font-size: 12px;
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
                const header = document.getElementById('navbar');
                if (header) {
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
                        Automatisation
                    </div>
                    <div class="badge">
                        <i class="fas fa-handshake"></i>
                        Partenaires de confiance
                    </div>
                    <div class="badge">
                        <i class="fas fa-bolt"></i>
                        Installation rapide
                    </div>
                </div>

                <h1>CONÇU POUR LES VENDEURS QUI RÊVENT PLUS GRAND</h1>
                <p>De la commande à la livraison, OBGecom automatise chaque étape avec une IA de pointe, des analyses en temps réel et des intégrations fluides.</p>
                
                <div class="search-container">
                    <input type="text" id="emailInput" class="search-input" placeholder="Entrez votre email pour commencer">
                    <button class="search-button" onclick="startTrial(event)">
                        <i class="fas fa-rocket" style="margin-right: 8px;"></i>
                        Essai gratuit
                    </button>
                </div>

                <script>
                function startTrial(event) {
                    event.preventDefault();
                    const email = document.getElementById('emailInput').value.trim();

                    if (email === "") {
                        alert("Veuillez entrer votre email avant de continuer.");
                        return;
                    }

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
                <div class="french-dashboard">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th class="col-checkbox"></th>
                                <th class="col-client">CLIENT</th>
                                <th class="col-whatsapp">TÉLÉPHONE</th>
                                <th class="col-city">VILLE</th>
                                <th class="col-articles">ARTICLES</th>
                                <th class="col-amount">MONTANT</th>
                                <th class="col-status">STATUT</th>
                                <th class="col-action">ACTION</th>
                                <th class="col-user">BOUTIQUE</th>
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
                                <td><div class="status-badge status-en-attente">En attente</div></td>
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
                                <td><div class="status-badge status-confirmee">Confirmée</div></td>
                                <td><button class="action-button">Expédier</button></td>
                                <td>Rabat Deals</td>
                            </tr>

                            <tr>
                                <td><input type="checkbox" class="order-checkbox"></td>
                                <td class="col-client"><div class="client-info"><div class="client-name">Hicham</div></div></td>
                                <td><div class="whatsapp-icon" title="+212 6XX XXX XXX"><i class="fab fa-whatsapp"></i></div></td>
                                <td>Marrakech</td>
                                <td><div class="city-badge">3</div></td>
                                <td><div class="amount">210.00<br>MAD</div></td>
                                <td><div class="status-badge status-annulee">Annulée</div></td>
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
                                <td><div class="status-badge status-en-attente">En attente</div></td>
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
                                <td><div class="status-badge status-confirmee">Confirmée</div></td>
                                <td><button class="action-button">Expédier</button></td>
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
                        <div class="discount-badge">-50%</div>
                    </div>
                    <div class="partner-circle partner-dzon">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">DZON Express</div>
                        <div class="discount-badge">-50%</div>
                    </div>
                    <div class="partner-circle partner-sendit">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">SendIt</div>
                        <div class="discount-badge">-50%</div>
                    </div>
                    <div class="partner-circle partner-power">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">Power</div>
                        <div class="discount-badge">-50%</div>
                    </div>
                    <div class="partner-circle partner-delivery">
                        <i class="fas fa-truck"></i>
                        <div class="partner-text">Global+</div>
                    </div>
                </div>

            </div>
        </main>

        <style>
            *{box-sizing:border-box;margin:0;padding:0;}

            :root{
                --pu:#7C3AED;
                --pu2:#6D28D9;
                --pu-light:#A78BFA;
                --pu-xlight:#EDE9FE;
                --pu-xxlight:#F5F3FF;
                --pu-dim:rgba(124,58,237,0.08);
                --pu-dim2:rgba(124,58,237,0.15);
                --pu-dim3:rgba(124,58,237,0.25);
                --border:rgba(124,58,237,0.12);
                --border2:rgba(124,58,237,0.28);
                --bg3:#F9F7FF;
                --txt:#1A1530;
                --txt2:#4B5268;
                --txt3:#9094A8;
                --green:#16A34A;
                --green-bg:#F0FDF4;
                --green-border:rgba(22,163,74,0.22);
                --blue:#1D4ED8;
                --blue-bg:#EFF6FF;
                --amber:#B45309;
                --amber-bg:#FFFBEB;
            }

            html, body {
                font-family: 'DM Sans', sans-serif;
                color: var(--txt);
                background: #fff;
                overflow-x: hidden;
            }

            .wo { position: relative; }

            .wo-hdr {
                text-align: center;
                padding: 96px 40px 80px;
                position: relative;
                overflow: hidden;
                background: #fff;
            }
            .hdr-orb {
                position: absolute;
                top: -120px; left: 50%;
                transform: translateX(-50%);
                width: 700px; height: 500px;
                background: radial-gradient(ellipse at 50% 40%, rgba(124,58,237,0.07) 0%, transparent 65%);
                pointer-events: none;
            }
            .hdr-dots {
                position: absolute; inset: 0;
                background-image: radial-gradient(rgba(124,58,237,0.09) 1px, transparent 1px);
                background-size: 28px 28px;
                pointer-events: none;
            }
            .hdr-rule {
                position: absolute; bottom: 0; left: 50%;
                transform: translateX(-50%);
                width: 64px; height: 2px;
                background: linear-gradient(to right, transparent, var(--pu), transparent);
            }

            .wo-eye {
                display: inline-flex; align-items: center; gap: 9px;
                font-size: 10px; font-weight: 600; letter-spacing: .26em; text-transform: uppercase;
                color: var(--pu); background: var(--pu-dim);
                border: 1px solid var(--pu-dim3); border-radius: 3px;
                padding: 7px 18px; margin-bottom: 32px;
                position: relative; z-index: 1;
            }
            .wo-dot {
                width: 5px; height: 5px; border-radius: 50%; background: var(--pu);
                animation: pulse 2s ease-in-out infinite;
            }
            @keyframes pulse {
                0%,100%{ opacity:1; box-shadow:0 0 0 0 rgba(124,58,237,.35) }
                50%{ opacity:.5; box-shadow:0 0 0 7px rgba(124,58,237,0) }
            }

            .wo-h1 {
                font-family: 'Cormorant Garamond', serif;
                font-size: clamp(3rem, 7.5vw, 5.8rem);
                font-weight: 300; color: var(--txt);
                line-height: .95; letter-spacing: -.02em;
                margin-bottom: 18px;
                position: relative; z-index: 1;
            }
            .wo-h1 em { font-style: italic; font-weight: 700; color: var(--pu); }

            .wo-sub {
                font-size: .95rem; color: var(--txt2);
                max-width: 450px; margin: 0 auto;
                line-height: 1.8; font-weight: 300;
                position: relative; z-index: 1;
            }

            .wo-cards { display: flex; flex-direction: column; border-top: 1px solid var(--border); }

            .wo-card {
                display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr);
                min-height: 510px;
                border-bottom: 1px solid var(--border);
                position: relative; overflow: hidden;
                background: #fff; transition: background .4s;
            }
            .wo-card:nth-child(even) { background: var(--pu-xxlight); }
            .wo-card:hover { background: var(--bg3); }

            .card-accent {
                position: absolute; top: 0; left: 0; right: 0; height: 2px;
                background: linear-gradient(to right, transparent, var(--pu-light), transparent);
                opacity: 0; transition: opacity .4s;
            }
            .wo-card:hover .card-accent { opacity: 1; }

            .wo-info { padding: 68px 60px; display: flex; flex-direction: column; justify-content: center; }

            .wo-num {
                font-size: 9px; font-weight: 600; letter-spacing: .28em;
                color: var(--pu); text-transform: uppercase;
                margin-bottom: 26px;
                display: flex; align-items: center; gap: 12px;
            }
            .wo-num::after {
                content: ''; flex: 0 0 36px; height: 1px;
                background: linear-gradient(to right, var(--pu), transparent);
            }

            .wo-ctitle {
                font-family: 'Cormorant Garamond', serif;
                font-size: clamp(1.9rem, 3.2vw, 2.9rem);
                font-weight: 400; color: var(--txt);
                line-height: 1.1; letter-spacing: -.01em; margin-bottom: 18px;
            }
            .wo-ctitle em { font-style: italic; font-weight: 700; color: var(--pu); }

            .wo-cdesc { font-size: .875rem; color: var(--txt2); line-height: 1.85; font-weight: 300; max-width: 360px; }

            .wo-vis {
                display: flex; align-items: center; justify-content: center;
                padding: 52px 44px;
                border-left: 1px solid var(--border);
                position: relative;
            }
            .wo-vis.left-side { border-left: none; border-right: 1px solid var(--border); }

            .panel { width: 100%; max-width: 320px; }

            .p-label {
                font-size: 9px; font-weight: 600; letter-spacing: .22em; text-transform: uppercase;
                color: var(--pu); margin-bottom: 14px;
                display: flex; align-items: center; gap: 8px;
            }
            .p-label::before { content: ''; width: 18px; height: 1px; background: var(--pu); }

            .flow-track {
                display: flex; align-items: center;
                background: var(--pu-xxlight); border: 1px solid var(--border2);
                border-radius: 12px; padding: 20px 10px;
            }
            .flow-step { display: flex; flex-direction: column; align-items: center; flex: 1; gap: 9px; cursor: default; }
            .flow-step:hover .flow-icon { border-color: var(--pu); background: var(--pu-dim2); transform: translateY(-3px); }

            .flow-icon {
                width: 46px; height: 46px; border-radius: 10px;
                background: #fff; border: 1px solid var(--border2);
                display: flex; align-items: center; justify-content: center;
                position: relative; transition: all .3s; flex-shrink: 0;
            }
            .flow-icon svg { width: 19px; height: 19px; fill: none; stroke: var(--pu); stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
            .flow-icon.pending { background: var(--pu-xxlight); border-style: dashed; border-color: var(--border2); }
            .flow-icon.pending svg { stroke: var(--pu-light); }

            .chk {
                position: absolute; top: -7px; right: -7px;
                width: 17px; height: 17px; border-radius: 50%;
                background: var(--green); border: 2px solid #fff;
                display: flex; align-items: center; justify-content: center;
            }
            .chk svg { width: 8px; height: 8px; stroke: #fff; stroke-width: 2.5; fill: none; }

            .flow-lbl { font-size: 9px; color: var(--txt3); letter-spacing: .04em; text-align: center; }

            .flow-arr { flex: 0 0 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 18px; }
            .flow-arr svg { width: 10px; height: 10px; stroke: var(--pu-light); fill: none; stroke-width: 1.8; }

            .ship-mini { margin-top: 10px; display: flex; flex-direction: column; gap: 5px; }

            .sm-row {
                display: flex; align-items: center; gap: 8px;
                background: #fff; border: 1px solid var(--border);
                border-radius: 7px; padding: 8px 11px;
                cursor: default; transition: border-color .2s, background .2s;
            }
            .sm-row:hover { border-color: var(--border2); background: var(--pu-xxlight); }
            .sm-num { font-size: 10px; font-weight: 600; color: var(--pu); min-width: 38px; letter-spacing: .04em; flex-shrink: 0; }
            .sm-name { flex: 1; font-size: 11px; font-weight: 300; color: var(--txt2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .sm-status { font-size: 8px; font-weight: 600; padding: 2px 8px; border-radius: 3px; white-space: nowrap; flex-shrink: 0; border: 1px solid transparent; }

            .kpi-row { margin-top: 28px; display: flex; gap: 20px; }
            .kpi-item { display: flex; flex-direction: column; gap: 5px; }
            .kpi-val { font-family: 'Cormorant Garamond', serif; font-size: 26px; font-weight: 600; color: var(--txt); line-height: 1; }
            .kpi-lbl { font-size: 9px; color: var(--txt3); font-weight: 300; letter-spacing: .04em; }
            .kpi-div { width: 1px; background: var(--border2); align-self: stretch; }

            .loyalty-grid { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 8px; }

            .lg-card {
                background: #fff; border: 1px solid var(--border);
                border-radius: 8px; padding: 13px 10px;
                display: flex; flex-direction: column; align-items: center; gap: 6px;
                cursor: default; position: relative; overflow: hidden; transition: all .25s;
            }
            .lg-card::after {
                content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
                background: linear-gradient(to right, transparent, var(--pu), transparent);
                opacity: 0; transition: opacity .3s;
            }
            .lg-card:hover { border-color: var(--border2); background: var(--pu-xxlight); }
            .lg-card:hover::after { opacity: 1; }

            .lg-icon {
                width: 34px; height: 34px; border-radius: 7px;
                background: var(--pu-dim); border: 1px solid var(--pu-dim2);
                display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            }
            .lg-icon svg { width: 15px; height: 15px; fill: none; stroke: var(--pu); stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
            .lg-val { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 600; color: var(--txt); line-height: 1; }
            .lg-lbl { font-size: 9px; color: var(--txt3); text-align: center; font-weight: 300; line-height: 1.3; }

            .vip-row {
                margin-top: 8px; background: #fff;
                border: 1px solid var(--border); border-radius: 8px;
                padding: 10px 12px; display: flex; align-items: center; gap: 10px;
                cursor: default; transition: all .25s;
            }
            .vip-row:hover { border-color: var(--border2); background: var(--pu-xxlight); }

            .vip-avatar {
                width: 32px; height: 32px; border-radius: 7px;
                background: var(--pu-dim); border: 1px solid var(--pu-dim2);
                display: flex; align-items: center; justify-content: center;
                font-size: 10px; font-weight: 600; color: var(--pu); flex-shrink: 0;
            }
            .vip-info { flex: 1; min-width: 0; }
            .vip-name { font-size: 11px; font-weight: 500; color: var(--txt); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .vip-sub { font-size: 9px; color: var(--txt3); margin-top: 2px; font-weight: 300; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .vip-badge {
                font-size: 8px; font-weight: 600;
                background: var(--pu-dim); color: var(--pu);
                border: 1px solid var(--pu-dim2); padding: 3px 8px;
                border-radius: 3px; letter-spacing: .08em; text-transform: uppercase; white-space: nowrap; flex-shrink: 0;
            }

            .feat-list { margin-top: 24px; display: flex; flex-direction: column; gap: 8px; }
            .feat-item {
                display: flex; align-items: center; gap: 12px;
                padding: 11px 13px; background: var(--pu-xxlight);
                border: 1px solid var(--border); border-radius: 7px;
                cursor: default; transition: border-color .2s;
            }
            .feat-item:hover { border-color: var(--border2); }
            .feat-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--pu); flex-shrink: 0; }
            .feat-txt { font-size: 11px; color: var(--txt2); font-weight: 300; line-height: 1.4; }

            .team-bars { display: flex; flex-direction: column; gap: 11px; }
            .tb-row { display: flex; align-items: center; gap: 10px; cursor: default; }
            .tb-name { font-size: 11px; font-weight: 400; color: var(--txt2); min-width: 52px; text-align: right; flex-shrink: 0; }
            .tb-track { flex: 1; height: 4px; background: var(--pu-dim); border-radius: 2px; overflow: hidden; }
            .tb-fill {
                height: 100%; border-radius: 2px;
                background: linear-gradient(90deg, var(--pu2), var(--pu-light));
                transform-origin: left;
                animation: barIn 1.4s cubic-bezier(.22,1,.36,1) forwards;
                transform: scaleX(0);
            }
            @keyframes barIn { to { transform: scaleX(1); } }
            .tb-row:hover .tb-fill { filter: brightness(1.12); }
            .tb-pct { font-family: 'Cormorant Garamond', serif; font-size: 14px; font-weight: 600; color: var(--pu); min-width: 32px; flex-shrink: 0; }

            .team-stat { display: flex; gap: 7px; margin-top: 14px; }
            .ts-card {
                flex: 1; background: #fff; border: 1px solid var(--border);
                border-radius: 7px; padding: 11px 8px; text-align: center;
                cursor: default; transition: all .25s;
            }
            .ts-card:hover { border-color: var(--border2); background: var(--pu-xxlight); }
            .ts-val { font-family: 'Cormorant Garamond', serif; font-size: 19px; font-weight: 600; color: var(--txt); line-height: 1; }
            .ts-lbl { font-size: 9px; color: var(--txt3); margin-top: 3px; font-weight: 300; }

            .improve-note {
                margin-top: 10px; font-size: 9px; color: var(--txt3); text-align: center;
                padding-top: 10px; border-top: 1px solid var(--border); letter-spacing: .02em;
            }
            .improve-note span { color: var(--pu); font-weight: 600; }

            .highlight-box { margin-top: 22px; display: flex; gap: 8px; }
            .hb-card {
                flex: 1; padding: 13px 8px; background: var(--pu-xxlight);
                border: 1px solid var(--border); border-radius: 7px;
                text-align: center; cursor: default; transition: border-color .2s;
            }
            .hb-card:hover { border-color: var(--border2); }
            .hb-val { font-family: 'Cormorant Garamond', serif; font-size: 20px; font-weight: 600; color: var(--pu); line-height: 1; }
            .hb-lbl { font-size: 9px; color: var(--txt3); margin-top: 4px; font-weight: 300; }

            .fade-up { opacity: 0; transform: translateY(20px); transition: opacity .75s ease, transform .75s ease; }
            .fade-up.visible { opacity: 1; transform: translateY(0); }

            @media(max-width:768px){
                .wo-hdr { padding: 64px 24px 56px; }
                .wo-card { grid-template-columns: 1fr; min-height: auto; }
                .wo-vis, .wo-vis.left-side { border-left: none; border-right: none; border-top: 1px solid var(--border); }
                .wo-info { padding: 44px 26px; }
                .wo-vis { padding: 32px 22px; }
                .panel { max-width: 100%; }
            }
        </style>

        <div class="wo">
            <div class="wo-hdr">
                <div class="hdr-orb"></div>
                <div class="hdr-dots"></div>
                <div class="hdr-rule"></div>
                <div class="wo-eye fade-up"><span class="wo-dot"></span>Pourquoi OBG ?</div>
                <h1 class="wo-h1 fade-up">Vous gérez une entreprise,<br><em>pas un entrepôt.</em></h1>
                <p class="wo-sub fade-up">Trois piliers qui séparent les équipes qui gagnent en logistique de celles qui s'y noient.</p>
            </div>

            <div class="wo-cards">
                <!-- CARTE 1 -->
                <div class="wo-card">
                    <div class="card-accent"></div>
                    <div class="wo-info fade-up">
                        <div class="wo-num">01</div>
                        <h2 class="wo-ctitle">Gestion automatique des<br>&amp; <em>commandes et expéditions</em></h2>
                        <p class="wo-cdesc">Chaque commande est instantanément confirmée et acheminée vers le bon transporteur — zéro saisie manuelle, zéro expédition manquée.</p>
                        <div class="kpi-row">
                            <div class="kpi-item"><div class="kpi-val">99.8%</div><div class="kpi-lbl">précision d'acheminement</div></div>
                            <div class="kpi-div"></div>
                            <div class="kpi-item"><div class="kpi-val">3.2s</div><div class="kpi-lbl">traitement moyen</div></div>
                            <div class="kpi-div"></div>
                            <div class="kpi-item"><div class="kpi-val">24/7</div><div class="kpi-lbl">opérations automatisées</div></div>
                        </div>
                    </div>
                    <div class="wo-vis fade-up">
                        <div class="panel">
                            <div class="p-label">Pipeline commande</div>
                            <div class="flow-track">
                                <div class="flow-step">
                                    <div class="flow-icon"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><path d="M9 12l2 2 4-4"/></svg><div class="chk"><svg viewBox="0 0 10 10"><path d="M2 5l2.5 2.5L8 3"/></svg></div></div>
                                    <div class="flow-lbl">Commande reçue</div>
                                </div>
                                <div class="flow-arr"><svg viewBox="0 0 14 14"><path d="M3 7h8M8 4l3 3-3 3"/></svg></div>
                                <div class="flow-step">
                                    <div class="flow-icon"><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg><div class="chk"><svg viewBox="0 0 10 10"><path d="M2 5l2.5 2.5L8 3"/></svg></div></div>
                                    <div class="flow-lbl">Emballée</div>
                                </div>
                                <div class="flow-arr"><svg viewBox="0 0 14 14"><path d="M3 7h8M8 4l3 3-3 3"/></svg></div>
                                <div class="flow-step">
                                    <div class="flow-icon"><svg viewBox="0 0 24 24"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg><div class="chk"><svg viewBox="0 0 10 10"><path d="M2 5l2.5 2.5L8 3"/></svg></div></div>
                                    <div class="flow-lbl">Expédiée</div>
                                </div>
                                <div class="flow-arr"><svg viewBox="0 0 14 14"><path d="M3 7h8M8 4l3 3-3 3"/></svg></div>
                                <div class="flow-step">
                                    <div class="flow-icon pending"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
                                    <div class="flow-lbl">Livrée</div>
                                </div>
                            </div>
                            <div class="ship-mini">
                                <div class="sm-row"><span class="sm-num">#4821</span><span class="sm-name">Baskets Bleues · EU</span><span class="sm-status" style="background:#F0FDF4;color:#16A34A;border-color:rgba(22,163,74,0.22)">livrée</span></div>
                                <div class="sm-row"><span class="sm-num">#4822</span><span class="sm-name">Sac en Cuir · US</span><span class="sm-status" style="background:#EFF6FF;color:#1D4ED8;border-color:rgba(29,78,216,0.2)">en transit</span></div>
                                <div class="sm-row"><span class="sm-num">#4823</span><span class="sm-name">Foulard en Soie · UK</span><span class="sm-status" style="background:#FFFBEB;color:#B45309;border-color:rgba(180,83,9,0.2)">en traitement</span></div>
                                <div class="sm-row"><span class="sm-num">#4824</span><span class="sm-name">Manteau en Laine · DE</span><span class="sm-status" style="background:#F0FDF4;color:#16A34A;border-color:rgba(22,163,74,0.22)">livrée</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CARTE 2 -->
                <div class="wo-card">
                    <div class="card-accent"></div>
                    <div class="wo-vis left-side fade-up">
                        <div class="panel">
                            <div class="p-label">Intelligence client</div>
                            <div class="loyalty-grid" style="display: flex; flex-direction: column;">
                                <div class="lg-card"><div class="lg-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div><div class="lg-val">2 483</div><div class="lg-lbl">clients totaux</div></div>
                                <div class="lg-card"><div class="lg-icon"><svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div><div class="lg-val">284</div><div class="lg-lbl">VIP (3+ commandes)</div></div>
                                <div class="lg-card"><div class="lg-icon"><svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div><div class="lg-val">12,4k MAD</div><div class="lg-lbl">LTV moyenne VIP</div></div>
                                <div class="lg-card"><div class="lg-icon"><svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div><div class="lg-val">67%</div><div class="lg-lbl">taux de réachat</div></div>
                            </div>
                            <div class="vip-row"><div class="vip-avatar">EM</div><div class="vip-info"><div class="vip-name">Emma Reynolds</div><div class="vip-sub">12 commandes · 3 240 MAD valeur à vie</div></div><div class="vip-badge">VIP OR</div></div>
                            <div class="vip-row"><div class="vip-avatar">JC</div><div class="vip-info"><div class="vip-name">James Chen</div><div class="vip-sub">8 commandes · 2 180 MAD valeur à vie</div></div><div class="vip-badge">VIP ARGENT</div></div>
                        </div>
                    </div>
                    <div class="wo-info fade-up">
                        <div class="wo-num">02</div>
                        <h2 class="wo-ctitle">Analyse de la<br><em>fidélité client</em></h2>
                        <p class="wo-cdesc">Identifiez les VIP et les acheteurs réguliers, suivez leurs habitudes et construisez une valeur client à long terme avec des données réelles.</p>
                        <div class="feat-list">
                            <div class="feat-item"><div class="feat-dot"></div><div class="feat-txt">Détection automatique des VIP après 3+ achats</div></div>
                            <div class="feat-item"><div class="feat-dot"></div><div class="feat-txt">Valeur à vie suivie par segment client</div></div>
                            <div class="feat-item"><div class="feat-dot"></div><div class="feat-txt">Alertes de réengagement pour comptes à risque</div></div>
                        </div>
                    </div>
                </div>

                <!-- CARTE 3 -->
                <div class="wo-card">
                    <div class="card-accent"></div>
                    <div class="wo-info fade-up">
                        <div class="wo-num">03</div>
                        <h2 class="wo-ctitle">Suivi des performances<br><em>d'équipe</em></h2>
                        <p class="wo-cdesc">Visualisez la précision de confirmation par agent et optimisez vos opérations avec des insights basés sur les données.</p>
                        <div class="highlight-box">
                            <div class="hb-card"><div class="hb-val">+12%</div><div class="hb-lbl">gain de précision</div></div>
                            <div class="hb-card"><div class="hb-val">4</div><div class="hb-lbl">agents actifs</div></div>
                            <div class="hb-card"><div class="hb-val">0.3%</div><div class="hb-lbl">taux d'erreur</div></div>
                        </div>
                    </div>
                    <div class="wo-vis fade-up">
                        <div class="panel">
                            <div class="p-label">Précision par agent</div>
                            <div class="team-bars">
                                <div class="tb-row"><div class="tb-name">Olivia</div><div class="tb-track"><div class="tb-fill" style="width:98%;animation-delay:.1s"></div></div><div class="tb-pct">98%</div></div>
                                <div class="tb-row"><div class="tb-name">Marcus</div><div class="tb-track"><div class="tb-fill" style="width:94%;animation-delay:.2s"></div></div><div class="tb-pct">94%</div></div>
                                <div class="tb-row"><div class="tb-name">Sofia</div><div class="tb-track"><div class="tb-fill" style="width:91%;animation-delay:.3s"></div></div><div class="tb-pct">91%</div></div>
                                <div class="tb-row"><div class="tb-name">Daniel</div><div class="tb-track"><div class="tb-fill" style="width:87%;animation-delay:.4s"></div></div><div class="tb-pct">87%</div></div>
                            </div>
                            <div class="team-stat">
                                <div class="ts-card"><div class="ts-val">2 184</div><div class="ts-lbl">commandes traitées</div></div>
                                <div class="ts-card"><div class="ts-val">0.3%</div><div class="ts-lbl">taux d'erreur</div></div>
                                <div class="ts-card"><div class="ts-val">2.4m</div><div class="ts-lbl">confirmation moyenne</div></div>
                            </div>
                            <div class="improve-note"><span>↑ 12%</span> d'amélioration de la précision ce mois-ci</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            const obsr = new IntersectionObserver(entries => {
                entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
            }, { threshold: 0.1 });
            document.querySelectorAll('.fade-up').forEach(el => obsr.observe(el));

            let i = 0;
            const newOrders = [
                { num: '#4825', name: 'Sac en toile · FR', st: 'en traitement', bg: '#FFFBEB', tx: '#B45309', bd: 'rgba(180,83,9,0.2)' },
                { num: '#4826', name: 'Chemise en lin · IT', st: 'en transit', bg: '#EFF6FF', tx: '#1D4ED8', bd: 'rgba(29,78,216,0.2)' },
                { num: '#4827', name: 'Veste en jean · AU', st: 'livrée', bg: '#F0FDF4', tx: '#16A34A', bd: 'rgba(22,163,74,0.22)' },
            ];
            setInterval(() => {
                const rows = document.querySelectorAll('.sm-row');
                if (!rows.length) return;
                const o = newOrders[i % newOrders.length];
                const row = rows[rows.length - 1];
                row.style.transition = 'opacity .28s, transform .28s';
                row.style.opacity = '0';
                row.style.transform = 'translateX(8px)';
                setTimeout(() => {
                    row.querySelector('.sm-num').textContent = o.num;
                    row.querySelector('.sm-name').textContent = o.name;
                    const s = row.querySelector('.sm-status');
                    s.textContent = o.st;
                    s.style.background = o.bg;
                    s.style.color = o.tx;
                    s.style.borderColor = o.bd;
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, 300);
                i++;
            }, 3200);
        </script>

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

            .line:nth-child(2) { animation-delay: 0.4s; }
            .line:nth-child(3) { animation-delay: 0.8s; }

            @keyframes dataFlow {
                0%, 100% { opacity: 0.4; transform: scaleX(0.7); background: linear-gradient(90deg, #6B46C1, #8B5CF6); }
                50% { opacity: 1; transform: scaleX(1.3); background: linear-gradient(90deg, #8B5CF6, #A78BFA); }
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
                <div class="section-title2">Intégrations API fluides</div>
                <h2 class="main-heading">Connectez vos boutiques et transporteurs en quelques minutes.</h2>
                <p class="description">Commandes, clients et logistique synchronisés automatiquement.</p>
                
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
                        <div class="api-label">Hub d'intégration</div>
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

            .circuit-pattern {
                position: absolute;
                width: 100%;
                height: 100%;
                background-image: 
                    linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px),
                    linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px);
                background-size: 50px 50px;
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

            @keyframes nodePulse {
                0%, 100% { transform: scale(1); opacity: 0.8; box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7); }
                50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 0 10px rgba(255, 255, 255, 0); }
            }

            @keyframes syncPulse {
                0%, 100% { transform: scale(1); opacity: 0.6; }
                50% { transform: scale(1.8); opacity: 1; }
            }

            .data-node:nth-child(1) { top: 15%; left: 10%; animation-delay: 0s; }
            .data-node:nth-child(2) { top: 25%; left: 75%; animation-delay: 1s; }
            .data-node:nth-child(3) { top: 45%; left: 20%; animation-delay: 2s; }
            .data-node:nth-child(4) { top: 35%; right: 15%; animation-delay: 0.5s; }
            .data-node:nth-child(5) { top: 65%; left: 60%; animation-delay: 1.5s; }
            .data-node:nth-child(6) { top: 75%; left: 30%; animation-delay: 2.5s; }
            .data-node:nth-child(7) { top: 55%; right: 25%; animation-delay: 0.8s; }
            .data-node:nth-child(8) { top: 85%; right: 40%; animation-delay: 1.8s; }

            @media (max-width: 768px) {
                .parallax-break { height: 40vh; }
                .section-title3 { font-size: 2.2rem; }
                .section-subtitle2 { font-size: 1.1rem; padding: 0 20px; }
            }

            @media (max-width: 480px) {
                .parallax-break { height: 35vh; }
                .section-title3 { font-size: 1.8rem; }
                .section-subtitle2 { font-size: 1rem; padding: 0 15px; }
            }
        </style>

        <section class="parallax-break">
            <div class="circuit-pattern"></div>
            <div class="data-network">
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
                <div class="data-node"></div>
            </div>
            <div class="break-content">
                <div class="section-header">
                    <h2 class="section-title3">COMMENT ÇA FONCTIONNE</h2>
                    <p class="section-subtitle2">De la commande à la livraison, OBGecom synchronise tout automatiquement</p>
                </div>
            </div>
            <div class="sync-indicators">
                <div class="sync-dot"></div>
                <div class="sync-dot"></div>
                <div class="sync-dot"></div>
                <div class="sync-dot"></div>
            </div>
        </section>

        <style>
            @import url('https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Mono:wght@400;500&family=Outfit:wght@300;400;500;600;700&display=swap');

            :root {
                --pu: #4338CA;
                --pu-light: #EEF2FF;
                --pu-mid: #818CF8;
                --pu-faint: #F5F3FF;
                --ink: #0F0E1A;
                --muted: #6B7280;
                --surface: #FFFFFF;
                --surface2: #F9F8FF;
                --border: rgba(99,91,199,0.15);
                --border-strong: rgba(99,91,199,0.35);
                --radius: 28px;
                --rad-sm: 14px;
                --rad-xs: 8px;
            }

            .wrap {
                font-family: 'Outfit', sans-serif;
                background: #F7F6FF;
                padding: 80px 32px 100px;
                position: relative;
                overflow: hidden;
            }

            .bg-orb {
                position: absolute;
                border-radius: 50%;
                filter: blur(80px);
                pointer-events: none;
            }
            .bg-orb1 { width: 500px; height: 500px; background: rgba(99,91,199,0.06); top: -120px; left: -100px; }
            .bg-orb2 { width: 400px; height: 400px; background: rgba(129,140,248,0.05); bottom: 0; right: -80px; }

            .hdr {
                text-align: center;
                margin-bottom: 72px;
                position: relative;
                z-index: 2;
            }

            .eyebrow {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-family: 'DM Mono', monospace;
                font-size: 10px;
                font-weight: 500;
                letter-spacing: 0.18em;
                text-transform: uppercase;
                color: var(--pu);
                background: var(--pu-light);
                border: 1px solid rgba(99,91,199,0.25);
                border-radius: 100px;
                padding: 6px 16px;
                margin-bottom: 28px;
            }

            .eye-dot {
                width: 5px; height: 5px;
                border-radius: 50%;
                background: var(--pu);
                animation: blink 1.6s ease-in-out infinite;
            }
            @keyframes blink { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.85)} }

            .headline {
                font-family: 'Instrument Serif', serif;
                font-size: clamp(2.4rem, 5.5vw, 4rem);
                font-weight: 400;
                color: var(--ink);
                line-height: 1.08;
                letter-spacing: -0.02em;
                margin-bottom: 18px;
            }
            .headline em { font-style: italic; color: var(--pu); }

            .sub {
                font-size: 1rem;
                font-weight: 400;
                color: var(--muted);
                max-width: 480px;
                margin: 0 auto;
                line-height: 1.65;
            }

            .steps { max-width: 1060px; margin: 0 auto; display: flex; flex-direction: column; gap: 0; position: relative; z-index: 2; }

            .card {
                display: grid;
                grid-template-columns: 1fr 1fr;
                border-radius: var(--radius);
                overflow: hidden;
                border: 1px solid var(--border);
                background: var(--surface);
                box-shadow: 0 4px 32px -8px rgba(0,0,0,0.06), 0 1px 4px rgba(0,0,0,0.03);
                opacity: 0;
                transform: translateY(36px);
                transition: opacity .55s ease, transform .55s ease, box-shadow .3s ease, border-color .3s ease;
            }
            .card.vis { opacity: 1; transform: translateY(0); }
            .card:hover { box-shadow: 0 16px 48px -16px rgba(67,56,202,0.22); border-color: var(--border-strong); }

            .info {
                padding: 52px 48px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                background: #FFFFFF;
            }

            .step-num {
                font-family: 'DM Mono', monospace;
                font-size: 10px;
                letter-spacing: .16em;
                color: var(--pu-mid);
                margin-bottom: 22px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .step-num::after {
                content: '';
                display: block;
                flex: 0 0 28px;
                height: 1px;
                background: var(--pu-mid);
                opacity: .4;
            }

            .card-title {
                font-family: 'Instrument Serif', serif;
                font-size: clamp(1.6rem, 2.8vw, 2.1rem);
                font-weight: 400;
                color: var(--ink);
                line-height: 1.15;
                letter-spacing: -.01em;
                margin-bottom: 14px;
            }

            .card-desc {
                font-size: .88rem;
                color: var(--muted);
                line-height: 1.7;
                margin-bottom: 26px;
                font-weight: 400;
            }

            .feats { list-style: none; display: flex; flex-direction: column; gap: 10px; }
            .fi {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: .82rem;
                color: #374151;
                font-weight: 400;
            }
            .fck {
                flex-shrink: 0;
                width: 17px; height: 17px;
                border-radius: 50%;
                background: var(--pu-light);
                border: 1px solid rgba(99,91,199,0.3);
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .fck svg { width: 8px; height: 8px; }

            .panel {
                background: var(--surface2);
                border-left: 1px solid var(--border);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 36px 28px;
            }
            .card:nth-child(4n+1) .panel { order: 1; }
            .card:nth-child(4n-1) .info { order: 2; }
            .card:nth-child(4n-1) .panel { order: 1; border-left: none; border-right: 1px solid var(--border); }

            .pr { width: 100%; max-width: 320px; font-family: 'Outfit', sans-serif; }

            .p-label { font-family:'DM Mono',monospace; font-size:9px; letter-spacing:.15em; color:var(--pu); text-transform:uppercase; margin-bottom:12px; display:flex; align-items:center; justify-content:space-between; }
            .live-badge { background:rgba(67,56,202,.1); color:var(--pu); font-size:9px; padding:2px 8px; border-radius:40px; font-weight:500; }

            .net-wrap { background:#FBF9FF; border-radius:18px; border:1px solid rgba(99,91,199,.12); padding:14px 10px; display:flex; justify-content:space-between; gap:10px; align-items:stretch; }
            .net-col { display:flex; flex-direction:column; gap:8px; flex:1; }
            .net-row { background:#fff; border-radius:40px; padding:5px 10px; display:flex; align-items:center; gap:7px; font-size:11px; font-weight:500; border:1px solid rgba(99,91,199,.08); }
            .net-dot { width:7px; height:7px; border-radius:50%; }
            .net-mid { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; padding: 0 4px; }
            .flow-bead { width:5px; height:5px; border-radius:50%; background:var(--pu-mid); animation:flowdot 1.6s infinite; }

            .sync-bar { margin-top:12px; background:var(--pu-light); border-radius:60px; padding:6px 14px; font-size:10px; font-weight:500; color:var(--pu); text-align:center; }

            .ord-card { background:#fff; border:1px solid rgba(99,91,199,.1); border-radius:16px; padding:10px 12px; margin-bottom:8px; cursor:pointer; transition:border-color .2s, background .2s; }
            .ord-card:hover { border-color:rgba(99,91,199,.4); background:#FDFCFF; }
            .ord-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:7px; }
            .ord-name { font-size:12px; font-weight:600; color:var(--ink); }
            .ord-sub { font-size:9px; color:var(--muted); margin-top:1px; }
            .stage-badge { font-size:9px; font-weight:600; padding:3px 8px; border-radius:40px; }
            .prog-track { height:4px; background:#EEF2FF; border-radius:4px; overflow:hidden; }
            .prog-fill { height:100%; background:linear-gradient(90deg,var(--pu),var(--pu-mid)); border-radius:4px; transition:width .4s cubic-bezier(.4,0,.2,1); }
            .auto-count { text-align:right; font-size:10px; font-weight:500; color:var(--pu); margin-top:8px; }

            .stats-row { display:flex; gap:8px; margin-bottom:14px; }
            .stat-pill { flex:1; background:#fff; border:1px solid rgba(99,91,199,.1); border-radius:14px; padding:8px 4px; text-align:center; }
            .stat-val { font-size:17px; font-weight:700; color:var(--ink); }
            .stat-lbl { font-size:9px; color:var(--muted); }
            .ship-row { display:flex; align-items:center; gap:8px; padding:7px 10px; background:#fff; border:1px solid rgba(99,91,199,.08); border-radius:12px; margin-bottom:6px; cursor:default; transition:background .15s; position:relative; }
            .ship-row:hover { background:var(--pu-faint); }
            .ship-id { font-family:'DM Mono',monospace; font-size:10px; font-weight:500; color:var(--pu); min-width:42px; }
            .ship-dest { flex:1; font-size:11px; font-weight:500; }
            .ship-car { font-size:10px; color:var(--muted); min-width:36px; }
            .status-chip { font-size:9px; font-weight:600; padding:2px 7px; border-radius:40px; }

            .scale-slider-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
            .scale-slider-row label { font-size:11px; font-weight:500; color:var(--muted); white-space:nowrap; }
            .scale-val { font-size:13px; font-weight:700; color:var(--pu); min-width:24px; text-align:right; }
            .met-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px; }
            .met-cell { background:#fff; border:1px solid rgba(99,91,199,.1); border-radius:12px; padding:10px 12px; }
            .met-v { font-size:15px; font-weight:700; color:var(--ink); }
            .met-l { font-size:9px; color:var(--muted); margin-top:1px; }
            .carrier-chips { display:flex; flex-wrap:wrap; gap:6px; }
            .c-chip { font-size:10px; font-weight:500; background:var(--pu-light); color:var(--pu); border:1px solid rgba(99,91,199,.2); padding:3px 9px; border-radius:40px; animation:pop .2s ease both; }
            @keyframes pop { from{transform:scale(.8);opacity:0} to{transform:scale(1);opacity:1} }
            .ts { font-size:9px; color:var(--muted); text-align:right; margin-top:8px; }
            input[type=range] { accent-color: var(--pu); width:100%; }

            @media(max-width:640px) {
                .card { grid-template-columns:1fr; }
                .card:nth-child(4n-1) .info { order:1; }
                .card:nth-child(4n-1) .panel { order:2; border-right:none; border-top:1px solid var(--border); }
                .panel { border-left:none; border-top:1px solid var(--border); }
                .info { padding:36px 28px; }
            }
        </style>

        <div class="wrap">
            <div class="bg-orb bg-orb1"></div>
            <div class="bg-orb bg-orb2"></div>

            <div class="hdr">
                <div class="eyebrow"><span class="eye-dot"></span>Logistique orchestrée<span class="eye-dot" style="background:var(--pu-mid)"></span></div>
                <h1 class="headline">Quatre étapes pour <em>synchroniser plus intelligemment</em></h1>
                <p class="sub">Automatisez toute votre stack logistique — regardez-la fonctionner en direct ci-dessous.</p>
            </div>

            <div class="steps">
                <div class="card" data-card style="border-radius: 0px;">
                    <div class="info">
                        <div class="step-num">ÉTAPE 01</div>
                        <h2 class="card-title">Connecter &amp; Synchroniser</h2>
                        <p class="card-desc">Liez vos boutiques et transporteurs en quelques minutes. Observez les données circuler automatiquement entre les plateformes.</p>
                        <ul class="feats">
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Intégrations en un clic</li>
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Mappage automatique des champs</li>
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Statut de synchronisation en direct</li>
                        </ul>
                    </div>
                    <div class="panel"><div class="pr" id="p1"></div></div>
                </div>
                <div class="connector"><div class="conn-line"></div><div class="conn-dot"></div><div class="conn-line"></div></div>

                <div class="card" data-card style="transition-delay:.1s; border-radius: 0px;">
                    <div class="panel" style="border-left:none;border-right:1px solid var(--border)"><div class="pr" id="p2"></div></div>
                    <div class="info">
                        <div class="step-num">ÉTAPE 02</div>
                        <h2 class="card-title">Automatisation intelligente</h2>
                        <p class="card-desc">Les règles se déclenchent instantanément. Cliquez sur une commande ci-dessous pour la voir traitée automatiquement.</p>
                        <ul class="feats">
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Sélection intelligente du transporteur</li>
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Génération automatique d'étiquettes</li>
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Mise à jour des stocks en temps réel</li>
                        </ul>
                    </div>
                </div>
                <div class="connector"><div class="conn-line"></div><div class="conn-dot" style="animation-delay:.4s"></div><div class="conn-line"></div></div>

                <div class="card" data-card style="transition-delay:.2s; border-radius: 0px;">
                    <div class="info">
                        <div class="step-num">ÉTAPE 03</div>
                        <h2 class="card-title">Supervision unifiée</h2>
                        <p class="card-desc">Chaque expédition et événement de synchronisation dans un seul tableau de bord.</p>
                        <ul class="feats">
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Indicateurs de santé en direct</li>
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Alertes instantanées pour exceptions</li>
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Traçabilité complète par commande</li>
                        </ul>
                    </div>
                    <div class="panel"><div class="pr" id="p3"></div></div>
                </div>
                <div class="connector"><div class="conn-line"></div><div class="conn-dot" style="animation-delay:.8s"></div><div class="conn-line"></div></div>

                <div class="card" data-card style="transition-delay:.3s; border-radius: 0px;">
                    <div class="panel" style="border-left:none;border-right:1px solid var(--border)"><div class="pr" id="p4"></div></div>
                    <div class="info">
                        <div class="step-num">ÉTAPE 04</div>
                        <h2 class="card-title">Passez à l'échelle</h2>
                        <p class="card-desc">Ajoutez des boutiques et transporteurs sans aucune interruption.</p>
                        <ul class="feats">
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Ajout de boutiques sans temps d'arrêt</li>
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Expansion illimitée des transporteurs</li>
                            <li class="fi"><span class="fck"><svg viewBox="0 0 8 7" fill="none"><path d="M1 3.5l2 2L7 1" stroke="#4338CA" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span>Tarification basée sur le volume</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
            const obs = new IntersectionObserver(entries => entries.forEach(e => { if(e.isIntersecting) e.target.classList.add('vis'); }), { threshold: 0.15 });
            document.querySelectorAll('[data-card]').forEach(el => obs.observe(el));

            (function(){
                const el = document.getElementById('p1');
                el.innerHTML = `<div class="p-label">Sync active <span class="live-badge">&#9679; live</span></div>
                <div class="net-wrap"><div class="net-col" id="stores"></div><div class="net-mid" id="beads"></div><div class="net-col" id="carriers"></div></div>
                <div class="sync-bar" id="sbar">&#8635; 243 enregistrements synchronisés</div>`;
                const stores = [['Shopify','#22C55E'],['WooCommerce','#22C55E'],['Magento','#F59E0B']];
                stores.forEach(([n,c]) => {
                    const r = document.createElement('div'); r.className='net-row';
                    r.innerHTML = `<span class="net-dot" style="background:${c};animation:blink 1.3s infinite;box-shadow:0 0 0 2px ${c}30"></span><span style="flex:1;font-size:11px">${n}</span><span style="font-size:9px;color:#6B7280">synchro</span>`;
                    document.getElementById('stores').appendChild(r);
                });
                for(let i=0;i<4;i++){const b=document.createElement('div');b.className='flow-bead';b.style.animationDelay=(i*.25)+'s';document.getElementById('beads').appendChild(b);}
                const carriers = ['DHL','FedEx','UPS'];
                carriers.forEach(c => {
                    const r = document.createElement('div'); r.className='net-row';
                    r.innerHTML = `<span style="flex:1;font-size:11px">${c}</span><span style="font-size:9px;color:#22C55E;font-weight:600">actif</span>`;
                    document.getElementById('carriers').appendChild(r);
                });
                let n=243;
                setInterval(()=>{ n+=Math.floor(Math.random()*7+1); document.getElementById('sbar').textContent=`↻ ${n} enregistrements synchronisés`; },1900);
            })();

            (function(){
                const el = document.getElementById('p2');
                el.innerHTML = `<div class="p-label">Cliquez pour automatiser</div><div id="ord-list"></div><div class="auto-count" id="ac">0 automatisés aujourd'hui</div>`;
                const orders = [{id:'#4821',item:'Baskets Bleues',zone:'EU',carrier:'DHL'},{id:'#4822',item:'Sac en Cuir',zone:'US',carrier:'FedEx'},{id:'#4823',item:'Foulard Soie',zone:'UK',carrier:'UPS'}];
                const stages = ['Reçue','Traitement','Étiquetée','Réservée'];
                const colors = ['#9CA3AF','#D97706','#2563EB','#059669'];
                const bgs = ['#F3F4F6','#FEF3C7','#DBEAFE','#D1FAE5'];
                let autoTotal=0;
                orders.forEach(o => {
                    o.state=0;
                    const c=document.createElement('div'); c.className='ord-card';
                    c.innerHTML=`<div class="ord-top"><div><div class="ord-name">${o.id} · ${o.item}</div><div class="ord-sub">${o.zone} · ${o.carrier}</div></div><span class="stage-badge" style="background:${bgs[0]};color:${colors[0]}">${stages[0]}</span></div><div class="prog-track"><div class="prog-fill" style="width:0%"></div></div>`;
                    c.onclick=()=>{
                        if(o.state<3){
                            o.state++;
                            const b=c.querySelector('.stage-badge'),f=c.querySelector('.prog-fill');
                            b.textContent=stages[o.state]; b.style.background=bgs[o.state]; b.style.color=colors[o.state];
                            f.style.width=`${Math.round((o.state/3)*100)}%`;
                            if(o.state===3){autoTotal++;document.getElementById('ac').textContent=`${autoTotal} automatisés aujourd'hui`;c.style.background='#F0FDF4';}
                        }
                    };
                    document.getElementById('ord-list').appendChild(c);
                });
            })();

            (function(){
                const el = document.getElementById('p3');
                el.innerHTML=`<div class="stats-row"><div class="stat-pill"><div class="stat-val">99.8%</div><div class="stat-lbl">Disponibilité</div></div><div class="stat-pill"><div class="stat-val">14</div><div class="stat-lbl">Actifs</div></div><div class="stat-pill"><div class="stat-val">2</div><div class="stat-lbl">Alertes</div></div></div><div id="shlist"></div><div class="ts" id="ts">Mis à jour à l'instant</div>`;
                const ships=[
                    {id:'#4819',dest:'Paris',car:'DHL',status:'livrée',tip:'Livrée à temps'},
                    {id:'#4820',dest:'New York',car:'FedEx',status:'en transit',tip:'En route — arrivée demain'},
                    {id:'#4821',dest:'Londres',car:'UPS',status:'en traitement',tip:'Étiquette générée, en attente pickup'},
                    {id:'#4822',dest:'Dubai',car:'DHL',status:'alerte',tip:'⚠ Retard douanier — action requise'}
                ];
                const sc={livrée:['#059669','#D1FAE5'],'en transit':['#2563EB','#DBEAFE'],'en traitement':['#D97706','#FEF3C7'],alerte:['#DC2626','#FEE2E2']};
                const list=document.getElementById('shlist');
                ships.forEach(s=>{
                    const r=document.createElement('div'); r.className='ship-row';
                    r.innerHTML=`<span class="ship-id">${s.id}</span><span class="ship-dest">${s.dest}</span><span class="ship-car">${s.car}</span><span class="status-chip" style="color:${sc[s.status][0]};background:${sc[s.status][1]}">${s.status}</span>`;
                    r.title=s.tip;
                    list.appendChild(r);
                });
                let t=0; setInterval(()=>{t++;document.getElementById('ts').textContent=`Mis à jour il y a ${t}s`;},1000);
            })();

            (function(){
                const el=document.getElementById('p4');
                el.innerHTML=`<div class="p-label">Dynamique d'échelle</div>
                <div class="scale-slider-row"><label>Boutiques</label><input type="range" min="1" max="22" value="3" step="1" id="sc-range"><span class="scale-val" id="sc-val">3</span></div>
                <div class="met-grid"><div class="met-cell"><div class="met-v" id="m-ord">1 020</div><div class="met-l">Commandes/jour</div></div><div class="met-cell"><div class="met-v" id="m-car">5</div><div class="met-l">Transporteurs</div></div><div class="met-cell"><div class="met-v" id="m-rev">25 800 MAD</div><div class="met-l">Revenu/mois</div></div><div class="met-cell"><div class="met-v" id="m-up">99.9%</div><div class="met-l">SLA disponibilité</div></div></div>
                <div class="carrier-chips" id="chips"></div>`;
                const clist=['DHL','FedEx','UPS','TNT','GLS','DPD','USPS','Aramex','Evri','PostNL','Sendle','Yodel'];
                function update(v){
                    document.getElementById('sc-val').textContent=v;
                    document.getElementById('m-ord').textContent=(v*340).toLocaleString();
                    document.getElementById('m-car').textContent=Math.min(v+2,12);
                    document.getElementById('m-rev').textContent=(v*8600).toLocaleString()+' MAD';
                    const chips=document.getElementById('chips'); chips.innerHTML='';
                    const cnt=Math.min(v+2,clist.length);
                    for(let i=0;i<cnt;i++){const c=document.createElement('span');c.className='c-chip';c.style.animationDelay=(i*.04)+'s';c.textContent=clist[i];chips.appendChild(c);}
                }
                document.getElementById('sc-range').addEventListener('input',e=>update(parseInt(e.target.value)));
                update(3);
            })();
        </script>
        
        <style>
            *{box-sizing:border-box;margin:0;padding:0}
            .prc-wrap{padding:4rem 2rem 5rem;font-family:system-ui,-apple-system,sans-serif}
            .prc-eyebrow{text-align:center;font-size:11px;font-weight:600;letter-spacing:4px;color:#9c84d4;text-transform:uppercase;margin-bottom:.6rem}
            .prc-heading{text-align:center;font-size:2.4rem;font-weight:700;color:#1a0533;margin-bottom:.5rem;letter-spacing:-0.5px}
            .prc-sub{text-align:center;font-size:1rem;color:#7a6a90;margin-bottom:3rem}
            .prc-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;max-width:1200px;margin:0 auto;}
            @media(max-width:1024px){.prc-grid{grid-template-columns:repeat(2,1fr)}}
            @media(max-width:600px){.prc-grid{grid-template-columns:1fr}}
            .prc-card{background:#fff;border:1px solid #e8e0f5;border-radius:24px;overflow:visible;position:relative;transition:transform .22s ease,box-shadow .22s ease}
            .prc-card:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(90,50,160,.1)}
            .prc-card--hot{border:2px solid #7F77DD;box-shadow:0 8px 30px rgba(127,119,221,.18)}
            .prc-popular{position:absolute;top:-14px;left:50%;transform:translateX(-50%);background:linear-gradient(90deg,#7b5ce7,#9b7fe8);color:#fff;font-size:10px;font-weight:700;letter-spacing:2.5px;padding:5px 18px;border-radius:20px;white-space:nowrap;text-transform:uppercase;z-index:2}
            .prc-header{padding:1.6rem 1.6rem 1rem}
            .prc-dot{width:8px;height:8px;border-radius:50%;display:inline-block;margin-bottom:1rem}
            .prc-tier{font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:#b0a0cc;margin-bottom:.8rem}
            .prc-price-row{display:flex;align-items:baseline;gap:2px;margin-bottom:.3rem}
            .prc-currency{font-size:14px;font-weight:600;color:#9c84d4;align-self:flex-start;padding-top:6px}
            .prc-amount{font-size:3rem;font-weight:800;line-height:1;color:#1a0533;letter-spacing:-2px}
            .prc-period{font-size:13px;color:#b0a0cc;margin-left:4px;font-weight:400}
            .prc-was{font-size:12px;color:#c0b0d8;text-decoration:line-through;margin-top:3px}
            .prc-free-pill{display:inline-block;background:#edf7ed;color:#2e7d32;font-size:11px;font-weight:600;padding:4px 12px;border-radius:20px;margin-bottom:.8rem;border:1px solid #c8e6c9}
            .prc-rule{height:1px;background:#f0eafa;margin:0 1.6rem}
            .prc-feats{padding:1.2rem 1.6rem;list-style:none}
            .prc-feat{display:flex;align-items:flex-start;gap:10px;font-size:13.5px;color:#4a3a62;line-height:1.5;margin-bottom:11px}
            .prc-chk{width:18px;height:18px;min-width:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-top:1px;flex-shrink:0}
            .prc-chk svg{width:9px;height:9px;display:block}
            .prc-foot{padding:.8rem 1.6rem;background:#faf8ff;border-top:1px solid #f0eafa;font-size:12px;color:#b0a0cc;text-align:center;font-style:italic}
            .prc-cta{display:block;width:calc(100% - 3.2rem);margin:1.2rem 1.6rem 1.6rem;padding:14px 20px;border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer;letter-spacing:.3px;text-decoration:none;text-align:center;transition:opacity .18s,transform .12s}
            .prc-cta:hover{opacity:.84}
            .prc-s1 .prc-dot{background:#a855f7}
            .prc-s1 .prc-chk{background:#f5eeff}
            .prc-s1 .prc-cta{background:#7b1fa2;color:#fff}
            .prc-s2 .prc-dot{background:#7F77DD}
            .prc-s2 .prc-chk{background:#EEEDFE}
            .prc-s2 .prc-cta{background:#6d5fd4;color:#fff}
            .prc-s3 .prc-dot{background:#5e35b1}
            .prc-s3 .prc-chk{background:#ede7f6}
            .prc-s3 .prc-cta{background:#5e35b1;color:#fff}
            .prc-s4 .prc-dot{background:#3a0b6e}
            .prc-s4 .prc-chk{background:#ede7f6}
            .prc-s4 .prc-cta{background:#3a0b6e;color:#fff}
        </style>

        <div class="prc-wrap" id="pricing">
            <p class="prc-eyebrow">Tarifs</p>
            <p class="prc-heading">Formules & Tarifs</p>
            <p class="prc-sub">Choisissez la formule qui évolue avec votre entreprise</p>

            <div class="prc-grid">
                <div class="prc-card prc-s1">
                    <div class="prc-header"><div class="prc-dot"></div><p class="prc-tier">Starter</p><div class="prc-free-pill">Gratuit à vie</div><div class="prc-price-row"><span class="prc-currency">MAD</span><span class="prc-amount">0</span><span class="prc-period">/mois</span></div></div>
                    <div class="prc-rule"></div>
                    <ul class="prc-feats"><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Jusqu'à 100 commandes/mois</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>1 boutique intégrée</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>1 transporteur intégré</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Support email</span></li></ul>
                    <div class="prc-foot">Idéal pour tester & lancer</div>
                    <a href="./lg?plan=starter" class="prc-cta">Commencer</a>
                </div>

                <div class="prc-card prc-s2">
                    <div class="prc-header"><div class="prc-dot"></div><p class="prc-tier">Professionnel</p><div class="prc-price-row"><span class="prc-currency">MAD</span><span class="prc-amount">149</span><span class="prc-period">/mois</span></div><p class="prc-was">199 MAD / mois</p></div>
                    <div class="prc-rule"></div>
                    <ul class="prc-feats"><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Jusqu'à 1 000 commandes/mois</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Jusqu'à 5 boutiques</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Multi-transporteurs et assignation auto</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Notifications clients automatiques</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Tableau de bord clients</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Support prioritaire chat & email</span></li></ul>
                    <div class="prc-foot">Parfait pour les entreprises en croissance</div>
                    <a href="./lg?plan=professional" class="prc-cta">Commencer</a>
                </div>

                <div class="prc-card prc-s3 prc-card--hot">
                    <span class="prc-popular">Le plus populaire</span>
                    <div class="prc-header"><div class="prc-dot"></div><p class="prc-tier">Growth</p><div class="prc-price-row"><span class="prc-currency">MAD</span><span class="prc-amount">199</span><span class="prc-period">/mois</span></div><p class="prc-was">299 MAD / mois</p></div>
                    <div class="prc-rule"></div>
                    <ul class="prc-feats"><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Jusqu'à 4 000 commandes/mois</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Jusqu'à 10 boutiques</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Multi-transporteurs et suivi auto</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Suivi de livraison en temps réel</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Tableau de bord clients avancé</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Support prioritaire</span></li></ul>
                    <div class="prc-foot">Idéal pour les entreprises à forte croissance</div>
                    <a href="./lg?plan=growth" class="prc-cta">Commencer</a>
                </div>

                <div class="prc-card prc-s4">
                    <div class="prc-header"><div class="prc-dot"></div><p class="prc-tier">Business</p><div class="prc-price-row"><span class="prc-currency">MAD</span><span class="prc-amount">999</span><span class="prc-period">/mois</span></div><p class="prc-was">1 999 MAD / mois</p></div>
                    <div class="prc-rule"></div>
                    <ul class="prc-feats"><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Commandes illimitées</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Boutiques illimitées</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Automatisation multi-transporteurs complète</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Gestion d'équipe avancée</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>CRM et tableau de bord client avancé</span></li><li class="prc-feat"><div class="prc-chk"><svg viewBox="0 0 10 10" fill="none"><path class="prc-tick" d="M2 5l2 2 4-4" stroke-width="1.6"/></svg></div><span>Gestionnaire dédié & support 24/7</span></li></ul>
                    <div class="prc-foot">Solution complète pour grands volumes</div>
                    <a href="./lg?plan=business" class="prc-cta">Commencer</a>
                </div>
            </div>
        </div>

        <style>
            .partners-section {
                max-width: 100%;
                margin: 0 auto;
                text-align: center;
                padding: 60px 20px;
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
                0% { transform: translateX(0); }
                100% { transform: translateX(-50%); }
            }

            .slider-container:hover .slider-track {
                animation-play-state: paused;
            }

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
                margin-top: 3rem;
                padding: 2rem;
                background: linear-gradient(135deg, #f8fafc, #f1f5f9);
                border-radius: 16px;
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

            @media (max-width: 768px) {
                .section-title { font-size: 2.2rem; }
                .section-subtitle { font-size: 1.1rem; margin-bottom: 1.5rem; }
                .trust-metrics { gap: 2rem; margin-bottom: 3rem; }
                .metric-number { font-size: 2rem; }
                .slider-track { width: calc(180px * 12); }
                .logo-slide { min-width: 180px; height: 100px; padding: 15px; }
                .logo-container { width: 140px; height: 70px; }
                .logo-placeholder { max-width: 100px; max-height: 50px; width: 100px; height: 50px; }
            }

            @media (max-width: 480px) {
                .section-title { font-size: 1.8rem; }
                .trust-metrics { flex-direction: column; gap: 1.5rem; }
                .slider-track { width: calc(150px * 12); }
                .logo-slide { min-width: 150px; height: 90px; padding: 10px; }
                .logo-container { width: 120px; height: 60px; }
                .logo-placeholder { max-width: 80px; max-height: 40px; width: 80px; height: 40px; }
            }
        </style>

        <div class="partners-section">
            <h2 class="section-title">Nos partenaires de confiance</h2>
            <p class="section-subtitle">Connectez vos boutiques aux transporteurs au Maroc et gérez les livraisons sans effort.</p>

            <div class="trust-metrics">
                <div class="metric"><div class="metric-number">500+</div><div class="metric-label">Boutiques connectées</div></div>
                <div class="metric"><div class="metric-number">10+</div><div class="metric-label">Transporteurs partenaires</div></div>
                <div class="metric"><div class="metric-number">100K+</div><div class="metric-label">Commandes synchronisées</div></div>
            </div>

            <div class="slider-container">
                <div class="slider-track">
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">Cathedis</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">Chronopost</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">F Delivery</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">Ozone</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">Power</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">SendIt</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">Cathedis</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">Chronopost</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">F Delivery</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">Ozone</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">Power</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">SendIt</div></div></div>
                    <div class="logo-slide"><div class="logo-container"><div class="logo-placeholder">QuickLivraison</div></div></div>
                </div>
            </div>

            <div class="cta-section">
                <p class="cta-text">Prêt à simplifier vos expéditions ? Synchronisez votre boutique avec nos transporteurs dès aujourd'hui.</p>
                <a class="cta-button" href="#pricing">Commencer <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></a>
            </div>
        </div>

        <?php require_once('./assets/footer.php'); ?>
    </div>

    <style>
        #obgecom-chat-widget {
            bottom: 90px !important;
            right: 25px !important;
        }
        @media (max-width: 768px) {
            #obgecom-chat-widget {
                bottom: 80px !important;
                right: 15px !important;
            }
        }
    </style>

    <div id="obgecom-assistant-container">
        <?php require_once('./dashboard/ai_assistant/chat_a.html'); ?>
    </div>
</body>
</html>