<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBG ECOM - Synchronisez vos commandes en un clic</title>
    <meta name="description" content="Centralisez et gérez toutes vos commandes Youcana et WooCommerce en une seule plateforme. Confirmez en masse et expédiez plus rapidement.">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet" href="../assets/css/home.css" />
</head>
<body>

    <style>
        header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: var(--bg-white);
            border-bottom: 1px solid var(--border-light);
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            width: 30%;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .logo-icon::before {
            content: '';
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .logo-icon::after {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--primary-color);
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            letter-spacing: -0.025em;
        }

        .nav-section {
            display: flex;
            align-items: center;
            width: 100%;
            gap: 3rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
        }

        .nav-item a {
            text-decoration: none;
            color: var(--text-light);
            font-weight: 500;
            font-size: 15px;
            padding: 8px 0;
            transition: color 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .nav-item a:hover {
            color: var(--text-dark);
        }

        .dropdown-icon {
            width: 16px;
            height: 16px;
            opacity: 0.6;
            transition: transform 0.2s ease;
        }

        .nav-item:hover .dropdown-icon {
            transform: rotate(180deg);
        }

        .auth-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .language-selector {
            display: flex;
            align-items: center;
            gap: 4px;
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            padding: 6px 8px;
            border-radius: 6px;
            transition: background-color 0.2s ease;
        }

        .language-selector:hover {
            background-color: #f7fafc;
        }

        .flag-icon {
            width: 20px;
            height: 15px;
            border-radius: 2px;
            background: linear-gradient(to bottom, #012169 33%, white 33%, white 66%, #C8102E 66%);
            position: relative;
        }

        .flag-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                linear-gradient(45deg, transparent 45%, white 45%, white 55%, transparent 55%),
                linear-gradient(-45deg, transparent 45%, white 45%, white 55%, transparent 55%),
                linear-gradient(45deg, transparent 47%, #C8102E 47%, #C8102E 53%, transparent 53%),
                linear-gradient(-45deg, transparent 47%, #C8102E 47%, #C8102E 53%, transparent 53%);
        }

        .login-btn {
            background: none;
            border: none;
            color: var(--text-light);
            font-weight: 500;
            font-size: 15px;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .login-btn:hover {
            color: var(--text-dark);
        }

        .cta-button {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .cta-button:hover {
            transform: translateY(-1px);
        }

        /* WhatsApp Float Button */
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            background: #25D366;
            color: white;
            border-radius: 50px;
            padding: 15px 20px;
            text-decoration: none;
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        .whatsapp-float:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(37, 211, 102, 0.6);
            color: white;
            text-decoration: none;
        }

        .whatsapp-float i {
            font-size: 20px;
        }

        @keyframes pulse {
            0% { box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4); }
            50% { box-shadow: 0 4px 30px rgba(37, 211, 102, 0.7); }
            100% { box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4); }
        }

        /* About Section */
        .about-section {
            padding: 100px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .about-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .about-content {
            position: relative;
            z-index: 2;
        }

        .about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .about-text h2 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .about-text p {
            font-size: 1.2rem;
            line-height: 1.8;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .about-features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 40px;
        }

        .about-feature {
            background: rgba(255, 255, 255, 0.1);
            padding: 25px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .about-feature:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }

        .about-feature i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #e0f7f7;
        }

        .about-feature h4 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .about-feature p {
            font-size: 1rem;
            opacity: 0.8;
            margin: 0;
        }

        /* Contact Section */
        .contact-section {
            padding: 100px 0;
            background: #f8fafc;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .contact-info h2 {
            font-size: 2.5rem;
            color: #1a202c;
            margin-bottom: 1.5rem;
            font-weight: 700;
        }

        .contact-info p {
            font-size: 1.1rem;
            color: #4a5568;
            margin-bottom: 2rem;
            line-height: 1.7;
        }

        .contact-methods {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .contact-method {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .contact-method:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .contact-method i {
            font-size: 1.5rem;
            color: var(--primary-color);
            width: 30px;
            text-align: center;
        }

        .contact-method-info h4 {
            margin: 0 0 5px 0;
            font-weight: 600;
            color: #1a202c;
        }

        .contact-method-info p {
            margin: 0;
            color: #4a5568;
            font-size: 0.95rem;
        }

        .contact-form {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1a202c;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .submit-btn {
            width: 100%;
            background: var(--primary-color);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }

        /* How it works section */
        .how-it-works {
            padding: 100px 0;
            background: white;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }

        .step-card {
            text-align: center;
            padding: 40px 30px;
            background: #f8fafc;
            border-radius: 20px;
            position: relative;
            transition: all 0.3s ease;
        }

        .step-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .step-number {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .step-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin: 20px 0 25px 0;
        }

        .step-card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #1a202c;
            font-weight: 600;
        }

        .step-card p {
            color: #4a5568;
            line-height: 1.6;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .header-content {
                padding: 0 1rem;
            }
            
            .nav-section {
                gap: 1rem;
            }

            .about-grid,
            .contact-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .about-text h2 {
                font-size: 2rem;
            }

            .about-features {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .whatsapp-float {
                bottom: 20px;
                right: 20px;
                padding: 12px 15px;
            }

            .whatsapp-float span {
                display: none;
            }
        }

    </style>

    <header>
        <div class="container">
            <div class="header-content">
                <a href="#" class="logo">
                    <span class="logo-text"><img src="./assets/img/logo.png" style="width: 20%;"/></span>
                </a>
                
                <div class="nav-section">
                    <nav class="nav-links">
                        <div class="nav-item">
                            <a href="#features">
                                Features
                                <svg class="dropdown-icon" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 10l-4-4h8l-4 4z"/>
                                </svg>
                            </a>
                        </div>

                        <div class="nav-item">
                            <a href="#pricing">Tarification</a>
                        </div>

                        <div class="nav-item">
                            <a href="#about">
                                À propos
                                <svg class="dropdown-icon" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 10l-4-4h8l-4 4z"/>
                                </svg>
                            </a>
                        </div>
                        <div class="nav-item">
                            <a href="#how-it-works">
                                Comment ça marche
                                <svg class="dropdown-icon" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 10l-4-4h8l-4 4z"/>
                                </svg>
                            </a>
                        </div>
                        
                    </nav>
                    
                    <div class="auth-section">
                        <button class="language-selector">
                            <div class="flag-icon"></div>
                            FR
                            <svg class="dropdown-icon" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M8 10l-4-4h8l-4 4z"/>
                            </svg>
                        </button>
                        
                        <a href="login.php" class="login-btn">Se connecter</a>
                        
                        <a href="#pricing" class="cta-button">Commencer</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- WhatsApp Float Button -->
    <a href="https://wa.me/212600000000?text=Bonjour%20OBG%20ECOM%2C%20j'aimerais%20en%20savoir%20plus%20sur%20vos%20services" class="whatsapp-float" target="_blank">
        <i class="fab fa-whatsapp"></i>
        <span>Contactez-nous</span>
    </a>

    <section class="hero" style="color: #979797;">

        <div class="floating-element"><i class="fas fa-box"></i></div>
        <div class="floating-element"><i class="fas fa-rocket"></i></div>
        <div class="floating-element"><i class="fas fa-bolt"></i></div>

        <div class="container">
            <div class="hero-content">
                <div class="section-badge">Plateforme de gestion e-commerce intelligente</div>
                <h1>Simplifiez, automatisez et expédiez – le futur de votre e-commerce commence ici</h1>
                <p>OBG ECOM connecte Youcan et WooCommerce, synchronise vos commandes en temps réel, et les transmet automatiquement aux transporteurs. Gagnez du temps, réduisez les erreurs et faites passer votre business au niveau supérieur grâce à notre plateforme tout-en-un.</p>
                <div class="hero-buttons">
                    <a href="#pricing" class="btn-primary" style="background-color: #6631e1;">Démarrer gratuitement <i>→</i></a>
                    <a href="#contact" class="btn-secondary">Planifier une démo</a>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="features">
        <div class="container" style="max-width: 90%;">
            <div class="section-header">
                <div class="section-badge">Plateforme tout-en-un</div>
                <h2 class="section-title" style="font-size: 3em;">Gérez et automatisez tout votre e-commerce depuis une seule interface</h2>
                <p class="section-subtitle">OBG ECOM synchronise vos commandes, centralise vos équipes et connecte automatiquement vos ventes aux transporteurs.</p>
            </div>

            <div class="features-grid">

                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-sync-alt"></i></div>
                    <h3 class="feature-title">Synchronisation automatique</h3>
                    <p class="feature-description">Récupérez automatiquement vos commandes depuis YouCan et WooCommerce en temps réel.</p>
                    <ul class="checkmark-list">
                        <li>Connexion API directe</li>
                        <li>Mise à jour des statuts</li>
                        <li>Filtrage intelligent des commandes</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-users-cog"></i></div>
                    <h3 class="feature-title">Gestion d’équipe</h3>
                    <p class="feature-description">Ajoutez vos agents et suivez leur performance dans le traitement des commandes.</p>
                    <ul class="checkmark-list">
                        <li>Rôles et permissions</li>
                        <li>Suivi des performances par agent</li>
                        <li>Historique des actions</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shipping-fast"></i></div>
                    <h3 class="feature-title">Expédition simplifiée</h3>
                    <p class="feature-description">Envoyez vos commandes aux transporteurs automatiquement ou en un clic.</p>
                    <ul class="checkmark-list">
                        <li>Génération de bordereaux</li>
                        <li>Envoi via API ou email</li>
                        <li>Numéros de suivi générés</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    <h3 class="feature-title">Statistiques & Suivi</h3>
                    <p class="feature-description">Analysez les performances de votre boutique et de vos équipes.</p>
                    <ul class="checkmark-list">
                        <li>Commandes livrées/annulées</li>
                        <li>Tableau de bord interactif</li>
                        <li>Export en Excel ou PDF</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-robot"></i></div>
                    <h3 class="feature-title">Automatisations intelligentes</h3>
                    <p class="feature-description">Réduisez le travail manuel grâce à des règles d’automatisation personnalisables.</p>
                    <ul class="checkmark-list">
                        <li>Sans code</li>
                        <li>Automatisation du dispatching</li>
                        <li>Actions conditionnelles</li>
                    </ul>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="fab fa-whatsapp"></i></div>
                    <h3 class="feature-title">Intégration WhatsApp</h3>
                    <p class="feature-description">Gardez vos clients informés de l’état de leurs commandes via WhatsApp.</p>
                    <ul class="checkmark-list">
                        <li>Notifications automatiques</li>
                        <li>Historique des messages</li>
                        <li>Réponses rapides</li>
                    </ul>
                </div>

            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="about-content">
                <div class="about-grid">
                    <div class="about-text">
                        <div class="section-badge" style="background-color: #1f1926;">Moins de clics, plus de résultats</div>
                        <h2>Pourquoi OBG ECOM ?</h2>
                        <p>Parce que gérer un business en ligne ne devrait pas être un casse-tête. Chez OBG ECOM, on simplifie votre quotidien de e-commerçant marocain.</p>
                        <p>Notre plateforme connecte vos boutiques YouCan et WooCommerce, automatise les tâches répétitives, et envoie vos commandes directement aux transporteurs. Moins de clics, plus de ventes.</p>
                        <p>Conçue par des experts du digital et du terrain marocain, OBG ECOM est là pour faire décoller votre boutique – sans courbe d’apprentissage, sans stress.</p>
                    </div>

                    <div class="about-features">
                        <div class="about-feature">
                            <i class="fas fa-plug"></i>
                            <h4>Connexion rapide à vos boutiques</h4>
                            <p>Connectez YouCan et WooCommerce en quelques clics et centralisez tout au même endroit.</p>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-people-carry"></i>
                            <h4>Gestion d'équipe intégrée</h4>
                            <p>Ajoutez vos agents de confirmation, attribuez les commandes et suivez leur performance en temps réel.</p>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-shipping-fast"></i>
                            <h4>Envoi intelligent aux transporteurs</h4>
                            <p>Amana, Chrono Diali, Jibli, Ozon... choisissez, générez, c’est expédié !</p>
                        </div>
                        <div class="about-feature">
                            <i class="fas fa-chart-pie"></i>
                            <h4>Suivi et statistiques clairs</h4>
                            <p>Visualisez vos ventes, agents, livraisons, et exportez tout en PDF ou Excel.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section id="how-it-works" class="how-it-works">
        <div class="container">
            <div class="section-header">
                <div class="section-badge">Gestion intelligente, résultats concrets</div>
                <h2 class="section-title" style="font-size: 2.5em;">Comment OBG ECOM simplifie votre quotidien</h2>
                <p class="section-subtitle">3 étapes pour passer de la prise de tête à une gestion e-commerce efficace et automatisée</p>
            </div>

            <div class="steps-grid">

                <div class="step-card">
                    <div class="step-number">1</div>
                    <div class="step-icon">
                        <i class="fas fa-plug"></i>
                    </div>
                    <h3>Connectez vos boutiques</h3>
                    <p>Synchronisez vos comptes YouCan et WooCommerce en toute sécurité. Les commandes sont récupérées automatiquement, sans aucune configuration technique.</p>
                </div>

                <div class="step-card">
                    <div class="step-number">2</div>
                    <div class="step-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <h3>Attribuez et expédiez</h3>
                    <p>Affectez les commandes à vos agents, générez les bordereaux, et envoyez les colis aux transporteurs comme Amana, Chrono Diali ou Ozon en un seul clic.</p>
                </div>

                <div class="step-card">
                    <div class="step-number">3</div>
                    <div class="step-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3>Suivez, analysez, optimisez</h3>
                    <p>Accédez à des tableaux de bord en temps réel, suivez les performances de vos agents et exportez vos données pour piloter votre business avec clarté.</p>
                </div>

            </div>
        </div>
    </section>

    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3>500 +</h3>
                    <p>Commerçants actifs</p>
                </div>
                <div class="stat-item">
                    <h3>1M+</h3>
                    <p>Commandes traitées</p>
                </div>
                <div class="stat-item">
                    <h3>95%</h3>
                    <p>Temps gagné</p>
                </div>
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>Support disponible</p>
                </div>
            </div>
        </div>
    </section>

    <section id="demo" class="demo">
        <div class="container">
            <div class="demo-content">
                <div class="demo-text">
                    <h2>Interface intuitive, résultats immédiats</h2>
                    <p>Notre interface a été conçue pour vous faire gagner du temps dès le premier jour. Visualisez toutes vos commandes, filtrez par critères, et confirmez en masse en quelques clics.</p>
                    <p>Fini les allers-retours entre plateformes. Tout est centralisé dans un seul endroit, avec des statistiques en temps réel pour suivre vos performances.</p>
                    <a href="#pricing" class="cta-button" style="background-color: #6631e1">Commencer maintenant</a>
                </div>
                <div class="demo-image">
                    <div class="demo-mockup">
                        <div style="text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 1rem;">📱</div>
                            <div>Interface de gestion des commandes</div>
                            <div style="font-size: 0.9rem; color: #718096; margin-top: 0.5rem;">Tableau de bord en temps réel</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <style>
        .container2 {
            width: 90%;
            margin: 0 auto;
        }
    </style>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, padding 0.3s ease-out;
        }
        
        .faq-answer.expanded {
            max-height: 200px;
            padding-top: 0.5rem;
        }
        
        .chevron {
            transition: transform 0.3s ease;
        }
        
        .chevron.rotated {
            transform: rotate(180deg);
        }
        
        .faq-item:hover {
            background-color: #f8fafc;
        }
        
        .bg-secondary-50 {
            background-color: #f8fafc;
        }
    </style>

    <section class="py-16">
        <div class="max-w-4xl mx-auto px-6" style="max-width: 100%; padding: 0">
            <!-- Header -->
            <div class="text-center mb-12">
                <div class="section-badge">Guide rapide</div>
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Questions Fréquemment Posées</h2>
                <p class="text-gray-600">Tout ce que vous devez savoir sur OBG</p>
            </div>

            <style>

                .rp-1 {

                    padding: 0 6em;
                }

                @media only screen and (max-width: 767px) {

                    .rp-1 {
                        padding: 0 1em !important;
                    }
                }

                @media only screen and (min-width: 768px) and (max-width: 1024px) {
                    /* Styles for tablets */
                    .rp-1 {
                        padding: 0 2.5em !important;
                    }
                }


            </style>
            
            <!-- FAQ Items -->
            <div class="space-y-4 rp-1">

                <!-- FAQ 1 -->
                <div class="bg-white border border-gray-200">
                    <button class="flex justify-between items-center w-full text-left px-6 py-5 focus:outline-none" onclick="toggleFaq(this)">
                        <h3 class="text-lg font-medium text-gray-900">Qu'est-ce qu'OBG ECOM ?</h3>
                        <svg class="chevron w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="faq-answer px-6">
                        <p class="text-gray-600 pb-5">OBG ECOM est une plateforme centralisée qui permet aux boutiques en ligne sur YouCan et WooCommerce de gérer facilement leurs commandes, de la récupération automatique jusqu’à l’expédition via les transporteurs partenaires.</p>
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="bg-white border border-gray-200">
                    <button class="flex justify-between items-center w-full text-left px-6 py-5 focus:outline-none" onclick="toggleFaq(this)">
                        <h3 class="text-lg font-medium text-gray-900">Comment connecter ma boutique à OBG ECOM ?</h3>
                        <svg class="chevron w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="faq-answer px-6">
                        <p class="text-gray-600 pb-5">Connectez votre boutique YouCan ou WooCommerce grâce à notre intégration API sécurisée. Aucune compétence technique n’est requise. Une fois connecté, vos commandes sont automatiquement synchronisées.</p>
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="bg-white border border-gray-200">
                    <button class="flex justify-between items-center w-full text-left px-6 py-5 focus:outline-none" onclick="toggleFaq(this)">
                        <h3 class="text-lg font-medium text-gray-900">Quelles tâches puis-je automatiser ?</h3>
                        <svg class="chevron w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="faq-answer px-6">
                        <p class="text-gray-600 pb-5">Avec OBG ECOM, vous pouvez automatiser : la récupération des commandes, la génération des bordereaux d'expédition, l'envoi aux transporteurs, les notifications aux clients, et le suivi des livraisons en temps réel.</p>
                    </div>
                </div>

                <!-- FAQ 4 -->
                <div class="bg-white border border-gray-200">
                    <button class="flex justify-between items-center w-full text-left px-6 py-5 focus:outline-none" onclick="toggleFaq(this)">
                        <h3 class="text-lg font-medium text-gray-900">Ai-je besoin de compétences techniques ?</h3>
                        <svg class="chevron w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="faq-answer px-6">
                        <p class="text-gray-600 pb-5">Pas du tout. La plateforme a été conçue pour être intuitive, même pour les non-techniciens. Le processus d’intégration est guidé pas à pas.</p>
                    </div>
                </div>

                <!-- FAQ 5 -->
                <div class="bg-white border border-gray-200">
                    <button class="flex justify-between items-center w-full text-left px-6 py-5 focus:outline-none" onclick="toggleFaq(this)">
                        <h3 class="text-lg font-medium text-gray-900">Quels transporteurs sont intégrés ?</h3>
                        <svg class="chevron w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="faq-answer px-6">
                        <p class="text-gray-600 pb-5">OBG ECOM vous permet de choisir parmi plusieurs transporteurs populaires au Maroc tels que Amana, Jibli, Chrono Diali, Ozonexpress, Lux, etc., avec envoi automatique des bordereaux d'expédition.</p>
                    </div>
                </div>

                <!-- FAQ 6 -->
                <div class="bg-white border border-gray-200">
                    <button class="flex justify-between items-center w-full text-left px-6 py-5 focus:outline-none" onclick="toggleFaq(this)">
                        <h3 class="text-lg font-medium text-gray-900">Combien coûte OBG ECOM ?</h3>
                        <svg class="chevron w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="faq-answer px-6">
                        <p class="text-gray-600 pb-5">Nous proposons des formules d’abonnement flexibles, sans engagement, pour s’adapter aux besoins de chaque boutique. Accédez aux fonctions avancées dès le premier palier.</p>
                    </div>
                </div>
            </div>

            <!-- CTA Section -->
            <div class="mt-16 text-center bg-white p-8">
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Prêt à Transformer Votre Business E-commerce ?</h3>
                <p class="text-gray-600 mb-6">Rejoignez des milliers de boutiques e-commerce qui utilisent OBG pour rationaliser leurs opérations, augmenter les ventes et réduire le travail manuel.</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#pricing" class="bg-teal-600 text-white px-8 py-3 rounded-lg hover:bg-teal-700 transition-colors font-medium"
                    style="background-color: #6631e1">
                        Commencer Maintenant
                    </a>
                    <a href="contact.php" class="border border-teal-600 text-teal-600 px-8 py-3 rounded-lg hover:bg-teal-50 transition-colors font-medium">
                        Prendre Contact
                    </a>
                </div>
            </div>
        </div>
    </section>

    <script>
        function toggleFaq(button) {
            const answer = button.nextElementSibling;
            const chevron = button.querySelector('.chevron');
            const isExpanded = answer.classList.contains('expanded');
            
            // Close all other FAQ items
            document.querySelectorAll('.faq-answer.expanded').forEach(item => {
                if (item !== answer) {
                    item.classList.remove('expanded');
                    item.previousElementSibling.querySelector('.chevron').classList.remove('rotated');
                }
            });
            
            // Toggle current FAQ item
            if (isExpanded) {
                answer.classList.remove('expanded');
                chevron.classList.remove('rotated');
            } else {
                answer.classList.add('expanded');
                chevron.classList.add('rotated');
            }
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const platformItems = document.querySelectorAll('.platform-item');
            
            platformItems.forEach((item, index) => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-12px) scale(1.05)';
                    
                    const ripple = document.createElement('div');
                    ripple.style.cssText = `
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        width: 0;
                        height: 0;
                        border-radius: 50%;
                        background: rgba(59, 130, 246, 0.1);
                        transform: translate(-50%, -50%);
                        pointer-events: none;
                        animation: ripple 0.6s ease-out;
                    `;
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        if (ripple.parentNode) {
                            ripple.parentNode.removeChild(ripple);
                        }
                    }, 600);
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
                
                item.addEventListener('click', function() {
                    this.style.transform = 'translateY(-8px) scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'translateY(-12px) scale(1.05)';
                    }, 150);
                });
            });
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.platform-item, .section-title, .meta-partners').forEach(el => {
            observer.observe(el);
        });
    </script>

    <!-- Footer -->
    <style>

        .footer {
            display: flex;
            background-color: #0f0f23;
        }
        .footer__nav {
            flex: 1;
            background-color: #f9f9f9;
            padding: 20px;
        }
        .footer__nav-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .footer__nav-item {
            margin-bottom: 10px;
            padding: 8px 0;
            color: var(--dark);
            border-bottom: 2px solid #000;
        }

        .footer__nav-item:hover {
            background-color: #2a1669;
        }

        .footer__nav-item:hover .footer__nav-link {
            color: #fff;
            padding-left: .5em;
        }  

        .footer__nav-link {
            text-decoration: none;
            color: #2a1669;
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .footer__signup {
            flex: 1;
            background: #2a1669;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .footer__signup-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
            color: #fff;
        }
        .footer__signup-text {
            font-size: 16px;
            margin-bottom: 20px;
            text-align: center;
            padding: 5px 20px;
            color: #fff;
        }
        .footer__form {
            display: flex;
            width: 100%;
            max-width: 400px;
        }
        .footer__input {
            flex-grow: 1;
            padding: 15px 10px;
            border: 1px solid #000;
            color: #000;
        }
        .footer__submit {
            padding: 10px 20px;
            cursor: pointer;
            background: none !important;
            border: 1px solid #ffffff !important;
            color: #fff;
            margin: 0 auto;
        }

        .footer__social {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .footer__social-link {
            margin: 0 10px;
        }
        .footer__social-icon {
            width: 25px;
            height: 25px;
        }
        .footer__bottom {
            background-color: #f0f0f0;
            padding: 10px;
            text-align: center;
            font-size: 14px;
        }

        .container2 {
            text-align: center;
            position: relative;
        }
        .container2 h1 {
            font-size: 10vw;
            font-weight: bold;
            line-height: 1;
            margin: 0;
            color: #2a1669;
        }
        .cube {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 30vw;
            height: 10vw;
            border: 2px solid white;
            opacity: 0.2;
        }

        @media (max-width: 768px) {
            .footer {
                flex-direction: column-reverse;
            }

            .container2 h1 {
                font-size: 18vw;
            }

            .footer__submit {
                width: 80%;
            }

            .footer__input {
                border: 1px solid var(--dark);
                width: 90%;
                margin: 0 auto;
            }
        }
    </style>

    <footer class="footer">
        <nav class="footer__nav">
        <ul class="footer__nav-list">
            <li class="footer__nav-item"><a href="#features" class="footer__nav-link">Fonctionnalités</a></li>
            <li class="footer__nav-item"><a href="conditions.html" class="footer__nav-link">Conditions</a></li>
            <li class="footer__nav-item"><a href="#automation" class="footer__nav-link">Automatisation WhatsApp</a></li>
            <li class="footer__nav-item"><a href="#pricing" class="footer__nav-link">Tarifs</a></li>
            <li class="footer__nav-item"><a href="contact.php" class="footer__nav-link">Contact</a></li>
        </ul>

        <div class="container2">
            <h1>OBGECOM</h1>
            <div class="cube"></div>
        </div>

        </nav>
        <div class="footer__signup">
            <h2 class="footer__signup-title">UNIFIEZ VOTRE FLUX DE TRAVAIL</h2>
            <p class="footer__signup-text">
                Découvrez OBG : une solution tout-en-un qui combine la collaboration en équipe, l’automatisation WhatsApp 
                et des workflows puissants. Gagnez du temps, automatisez vos processus et communiquez efficacement.
            </p>
            <form class="footer__form">
                <input type="email" placeholder="Entrez votre e-mail" class="footer__input">
                <button type="submit" class="footer__submit">S'inscrire</button>
            </form>

            <style>

                .integration-section {
                    max-width: 600px;
                    margin: 0 auto;
                    text-align: center;
                }

                .section-title {
                    font-size: 16px;
                    color: #64748b;
                    margin-bottom: 24px;
                    font-weight: 500;
                }

                .platforms-grid {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 16px;
                    flex-wrap: wrap;
                    margin-bottom: 20px;
                    margin-top: 4em;
                }

                .platform-item {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    transition: opacity 0.2s ease;
                }

                .platform-item:hover {
                    opacity: 0.7;
                }

                .platform-logo {
                    width: 40px;
                    height: 40px;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 6px;
                    transition: transform 0.2s ease;
                }

                .platform-logo:hover {
                    transform: scale(1.05);
                }

                .platform-logo svg {
                    width: 20px;
                    height: 20px;
                }

                .platform-name {
                    font-size: 10px;
                    color: #e1e1e1;
                    font-weight: 500;
                }

                /* Platform colors */
                .shopify { background: #96bf48; }
                .woocommerce { background: #96588a; }
                .youcan { background: #2c3e50; }

                .meta-partners {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    margin-top: 16px;
                }

                .meta-logo {
                    font-size: 14px;
                    font-weight: 700;
                    color: #1877f2;
                }

                .partners-text {
                    color: #6c6c6c;
                    font-size: 14px;
                    font-weight: 500;
                }

                @media (max-width: 480px) {
                    .platforms-grid {
                        gap: 12px;
                    }
                    
                    .platform-logo {
                        width: 36px;
                        height: 36px;
                    }

                    .platform-logo svg {
                        width: 18px;
                        height: 18px;
                    }
                    
                    .platform-name {
                        font-size: 10px;
                    }

                    .meta-logo, .partners-text {
                        font-size: 13px;
                    }
                }
            </style>

            <div class="integration-section">
                <div class="meta-partners">
                    <span class="partners-text">Développé et créé par </span><span class="meta-logo">BRZ</span>
                </div>
            </div>

            
        </div>
    </footer>

    <script>
        // Smooth scrolling for navigation links
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

        // Header background change on scroll
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
                header.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.boxShadow = 'none';
            }
        });

        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

    </script>
</body>
</html>