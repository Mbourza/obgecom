<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Obgecom est votre partenaire digital au Maroc : solutions e-commerce, gestion des commandes, sites web sur mesure et applications personnalisées pour booster votre business en ligne.">
    <meta name="keywords" content="Obgecom, e-commerce, gestion des commandes, site web sur mesure, application personnalisée, Maroc">
    <title>Conditions Générales de Vente - OBG ECOM</title>
    <meta name="description" content="Conditions Générales de Vente d'OBG ECOM - Plateforme de synchronisation de commandes e-commerce">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/home.css">
    <style>
        /* Styles spécifiques à la page CGV */
        .terms-container {
            max-width: 1000px;
            margin: 120px auto 60px;
            padding: 0 1.5rem;
            color: #333;
        }
        
        .terms-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .terms-header h1 {
            font-size: 2.5rem;
            color: #2a1669;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .terms-header p {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .terms-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            padding: 2.5rem;
        }
        
        .terms-section {
            margin-bottom: 2.5rem;
        }
        
        .terms-section h2 {
            font-size: 1.5rem;
            color: #2a1669;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .terms-section p, .terms-section ul {
            line-height: 1.7;
            margin-bottom: 1rem;
            color: #475569;
        }
        
        .terms-section ul {
            padding-left: 1.5rem;
        }
        
        .terms-section li {
            margin-bottom: 0.5rem;
        }
        
        .language-toggle {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .language-btn {
            background: #f1f5f9;
            border: none;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .language-btn:first-child {
            border-radius: 6px 0 0 6px;
        }
        
        .language-btn:last-child {
            border-radius: 0 6px 6px 0;
        }
        
        .language-btn.active {
            background: #2a1669;
            color: white;
        }
        
        .language-content {
            display: none;
        }
        
        .language-content.active {
            display: block;
        }
        
        .back-to-home {
            display: inline-flex;
            align-items: center;
            color: #2a1669;
            text-decoration: none;
            font-weight: 500;
            margin-top: 2rem;
            transition: all 0.3s ease;
        }
        
        .back-to-home:hover {
            color: #6631e1;
        }
        
        .back-to-home i {
            margin-right: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .terms-container {
                margin-top: 100px;
            }
            
            .terms-header h1 {
                font-size: 2rem;
            }
            
            .terms-content {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php require_once('./assets/navbar.php'); ?>

    <!-- Contenu principal -->
    <main class="terms-container">
        <div class="terms-header">
            <h1>Conditions Générales de Vente</h1>
            <p>Dernière mise à jour: 15 Mars 2024</p>
        </div>
        
        <div class="language-toggle">
            <button class="language-btn active" data-lang="fr">Français</button>
            <button class="language-btn" data-lang="en">English</button>
        </div>
        
        <div class="terms-content">
            <div class="language-content active" id="fr-content">
                <div class="terms-section">
                    <h2>1. Objet</h2>
                    <p>Les présentes Conditions Générales de Vente (« CGV ») régissent les relations contractuelles entre OBGecom et tout Client souscrivant à ses services SaaS d'automatisation post-commande e-commerce.</p>
                </div>
                
                <div class="terms-section">
                    <h2>2. Services proposés</h2>
                    <p>OBGecom propose une solution SaaS permettant d'automatiser les étapes post-commande : validation des commandes, affectation au transporteur, notifications clients et reporting.</p>
                </div>
                
                <div class="terms-section">
                    <h2>3. Tarifs et paiement</h2>
                    <ul>
                        <li>Les prix sont exprimés en dirhams marocains (MAD), en euros (EUR) ou en dollars (USD), hors taxes.</li>
                        <li>Le paiement est mensuel ou annuel, par carte bancaire ou tout moyen sécurisé.</li>
                        <li>En cas de non-paiement, OBGecom se réserve le droit de suspendre l'accès au service.</li>
                    </ul>
                </div>
                
                <div class="terms-section">
                    <h2>4. Durée et résiliation</h2>
                    <ul>
                        <li>Le contrat est conclu pour une durée indéterminée à compter de la souscription.</li>
                        <li>Le Client peut résilier son abonnement à tout moment via son espace personnel.</li>
                        <li>OBGecom peut résilier en cas de manquement grave aux CGV ou défaut de paiement.</li>
                    </ul>
                </div>
                
                <div class="terms-section">
                    <h2>5. Droit de rétractation</h2>
                    <p>Le Client consommateur dispose d'un délai légal de 14 jours à compter de la souscription pour exercer son droit de rétractation, sans motif ni frais.</p>
                    <p>La demande doit être envoyée à : <a href="mailto:support@obgecom.com">support@obgecom.com</a>, avec les informations d'abonnement.</p>
                    <p>OBGecom remboursera les sommes versées dans un délai de 14 jours via le même mode de paiement.</p>
                    <p>⚠️ Exception : si le Client a commencé à utiliser le service avant la fin du délai, après accord exprès, le droit de rétractation ne s'applique plus pour la période déjà consommée.</p>
                </div>
                
                <div class="terms-section">
                    <h2>6. Obligations du Client</h2>
                    <p>Le Client s'engage à fournir des informations exactes, à utiliser le service conformément aux lois et à ne pas détourner la plateforme à des fins frauduleuses.</p>
                </div>
                
                <div class="terms-section">
                    <h2>7. Responsabilité</h2>
                    <p>OBGecom met en œuvre tous les moyens nécessaires pour assurer la continuité et la sécurité du service, mais ne garantit pas une disponibilité sans interruption. La responsabilité d'OBGecom ne couvre pas les dommages indirects.</p>
                </div>
                
                <div class="terms-section">
                    <h2>8. Données personnelles</h2>
                    <p>OBGecom collecte et traite les données personnelles conformément à la loi marocaine n°09-08 et au RGPD. Voir la <a href="privacy.html">Politique de Confidentialité</a> pour plus de détails.</p>
                </div>
                
                <div class="terms-section">
                    <h2>9. Propriété intellectuelle</h2>
                    <p>Tous les éléments de la plateforme (logiciels, graphismes, logos, textes, etc.) sont protégés par le droit d'auteur. Toute reproduction ou utilisation non autorisée est interdite.</p>
                </div>
                
                <div class="terms-section">
                    <h2>10. Loi applicable et juridiction</h2>
                    <p>Les présentes CGV sont régies par la loi marocaine. Tout litige sera soumis à la compétence exclusive des tribunaux de Marrakech.</p>
                </div>
            </div>
            
            <div class="language-content" id="en-content">
                <div class="terms-section">
                    <h2>1. Purpose</h2>
                    <p>These Terms and Conditions ("CGV") govern the contractual relationship between OBGecom and any Client subscribing to its post-order automation SaaS services.</p>
                </div>
                
                <div class="terms-section">
                    <h2>2. Services Provided</h2>
                    <p>OBGecom provides a SaaS solution automating post-order steps: order validation, carrier assignment, customer notifications, and reporting.</p>
                </div>
                
                <div class="terms-section">
                    <h2>3. Pricing & Payment</h2>
                    <ul>
                        <li>Prices are displayed in MAD or EUR, excluding taxes.</li>
                        <li>Payment is monthly or annual, via credit card or other secure methods.</li>
                        <li>In case of non-payment, OBGecom reserves the right to suspend service access.</li>
                    </ul>
                </div>
                
                <div class="terms-section">
                    <h2>4. Term & Termination</h2>
                    <ul>
                        <li>The contract is effective for an indefinite term from subscription.</li>
                        <li>The Client may terminate at any time via their account dashboard.</li>
                        <li>OBGecom may terminate in case of breach of the T&Cs or non-payment.</li>
                    </ul>
                </div>
                
                <div class="terms-section">
                    <h2>5. Right of Withdrawal</h2>
                    <p>Consumer Clients have a legal 14-day period from subscription to exercise their right of withdrawal, with no justification or fees.</p>
                    <p>Requests must be sent to: <a href="mailto:contact.be.Obg@gmail.com">contact.be.Obg@gmail.com</a>, including subscription details.</p>
                    <p>OBGecom will refund any amounts paid within 14 days using the same payment method.</p>
                    <p>⚠️ Exception: if the Client has started using the service before the end of the withdrawal period, with prior consent, the right no longer applies to the consumed service.</p>
                </div>
                
                <div class="terms-section">
                    <h2>6. Client Obligations</h2>
                    <p>The Client agrees to provide accurate information, use the service lawfully, and not misuse the platform for fraudulent purposes.</p>
                </div>
                
                <div class="terms-section">
                    <h2>7. Liability</h2>
                    <p>OBGecom will take all necessary measures to ensure service continuity and security but cannot guarantee uninterrupted availability. OBGecom shall not be held liable for indirect damages.</p>
                </div>
                
                <div class="terms-section">
                    <h2>8. Data Protection</h2>
                    <p>OBGecom collects and processes personal data in compliance with Moroccan law 09-08 and GDPR. See the <a href="privacy.html">Privacy Policy</a> for details.</p>
                </div>
                
                <div class="terms-section">
                    <h2>9. Intellectual Property</h2>
                    <p>All elements of the platform (software, graphics, logos, texts, etc.) are protected by copyright. Unauthorized reproduction or use is prohibited.</p>
                </div>
                
                <div class="terms-section">
                    <h2>10. Governing Law & Jurisdiction</h2>
                    <p>These T&Cs are governed by Moroccan law. Any dispute shall fall under the exclusive jurisdiction of the Casablanca courts.</p>
                </div>
            </div>
        </div>
        
        <a href="./home" class="back-to-home">
            <i class="fas fa-arrow-left"></i> Retour à l'accueil
        </a>
    </main>

    <!-- Footer (identique à votre landing page) -->
    <?php require_once('./assets/footer.php'); ?>

    <script>
        // Gestion du changement de langue
        document.querySelectorAll('.language-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Désactiver tous les boutons
                document.querySelectorAll('.language-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Activer le bouton cliqué
                this.classList.add('active');
                
                // Afficher le contenu correspondant
                const lang = this.getAttribute('data-lang');
                document.querySelectorAll('.language-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(`${lang}-content`).classList.add('active');
            });
        });
        
        // Smooth scrolling pour les ancres
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
    </script>
</body>
</html>