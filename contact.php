<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Obgecom est votre partenaire digital au Maroc : solutions e-commerce, gestion des commandes, sites web sur mesure et applications personnalisées pour booster votre business en ligne.">
    <meta name="keywords" content="Obgecom, e-commerce, gestion des commandes, site web sur mesure, application personnalisée, Maroc">
    <title>Contact - OBG ECOM</title>
    <meta name="description" content="Contactez l'équipe OBG ECOM pour toute question concernant notre plateforme de synchronisation de commandes e-commerce">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./assets/css/home.css">
    <style>
        /* Styles spécifiques à la page de contact */
        .contact-main {
            padding: 120px 0 60px;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .contact-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        
        .contact-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .contact-header h1 {
            font-size: 2.5rem;
            color: #2a1669;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .contact-header p {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        .contact-info {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .contact-info h2 {
            font-size: 1.8rem;
            color: #2a1669;
            margin-bottom: 1.5rem;
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
            background: #f8fafc;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .contact-method:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .contact-method i {
            font-size: 1.5rem;
            color: #6631e1;
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
        
        .contact-method-info a {
            color: #4a5568;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .contact-method-info a:hover {
            color: #6631e1;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: #f1f5f9;
            border-radius: 50%;
            color: #2a1669;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            background: #2a1669;
            color: white;
            transform: translateY(-3px);
        }
        
        .contact-form {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .contact-form h2 {
            font-size: 1.8rem;
            color: #2a1669;
            margin-bottom: 1.5rem;
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6631e1;
            box-shadow: 0 0 0 3px rgba(102, 49, 225, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        .submit-btn {
            width: 100%;
            background: #6631e1;
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
            box-shadow: 0 8px 25px rgba(102, 49, 225, 0.3);
        }
        
        .map-container {
            margin-top: 40px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .map-container iframe {
            width: 100%;
            height: 300px;
            border: none;
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
        
        @media (max-width: 968px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .contact-main {
                padding-top: 100px;
            }
            
            .contact-header h1 {
                font-size: 2rem;
            }
            
            .contact-info,
            .contact-form {
                padding: 1.5rem;
            }
        }

        /* Notification styles - Add this to your CSS */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            transform: translateX(400px);
            transition: all 0.3s ease;
            max-width: 400px;
            min-width: 300px;
        }

        .notification.notification-show {
            transform: translateX(0);
        }

        .notification-content {
            display: flex;
            align-items: flex-start;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            border-left: 4px solid #10b981;
            position: relative;
        }

        .notification-error .notification-content {
            border-left-color: #ef4444;
        }

        .notification-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
            margin-right: 12px;
            flex-shrink: 0;
            background: #10b981;
        }

        .notification-error .notification-icon {
            background: #ef4444;
        }

        .notification-text {
            flex: 1;
        }

        .notification-text strong {
            color: #1f2937;
            font-size: 16px;
            display: block;
            margin-bottom: 4px;
        }

        .notification-text p {
            color: #6b7280;
            font-size: 14px;
            margin: 0;
            line-height: 1.4;
        }

        .notification-close {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 20px;
            height: 20px;
            border: none;
            background: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .notification-close:hover {
            background: #f3f4f6;
            color: #374151;
        }

        /* Form validation styles */
        .field-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
        }

        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 6px;
            font-weight: 500;
        }

        /* Loading state for submit button */
        .submit-btn:disabled {
            cursor: not-allowed;
            transform: none !important;
        }

        /* Mobile responsive notifications */
        @media (max-width: 768px) {
            .notification {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
                min-width: auto;
                transform: translateY(-100px);
            }
            
            .notification.notification-show {
                transform: translateY(0);
            }
            
            .notification-content {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Header (identique à votre landing page) -->
    <?php require_once('./assets/navbar.php'); ?>

    <!-- Contenu principal -->
    <main class="contact-main">
        <div class="contact-container">
            <div class="contact-header">
                <h1>Contactez-nous</h1>
                <p>Une question, un projet ou besoin d'une démonstration ? Notre équipe est là pour vous aider.</p>
            </div>
            
            <div class="contact-grid">
                <div class="contact-info">
                    <h2>Nos coordonnées</h2>
                    <div class="contact-methods">
                        <div class="contact-method">
                            <i class="fas fa-envelope"></i>
                            <div class="contact-method-info">
                                <h4>Email</h4>
                                <p><a href="mailto:support@obgecom.com">support@obgecom.com</a></p>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <i class="fas fa-phone"></i>
                            <div class="contact-method-info">
                                <h4>Téléphone</h4>
                                <p><a href="tel:+212714620695">+212 714 620 695</a></p>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <i class="fab fa-whatsapp"></i>
                            <div class="contact-method-info">
                                <h4>WhatsApp</h4>
                                <p><a href="https://wa.me/212714620695" target="_blank">+212 714 620 695</a></p>
                            </div>
                        </div>
                        
                        <div class="contact-method">
                            <i class="fas fa-clock"></i>
                            <div class="contact-method-info">
                                <h4>Horaires</h4>
                                <p>Lun - Ven: 9h - 18h</p>
                                <p>Sam: 10h - 16h</p>
                            </div>
                        </div>
                    </div>
                    
                    <h3 style="margin-top: 30px;">Suivez-nous</h3>
                    <div class="social-links">
                        <a href="https://www.facebook.com/people/OBG-ecom/61579702710182/" class="social-link">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/obgecom/" class="social-link">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://www.linkedin.com/company/obgecom/" class="social-link">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
                
                <div class="contact-form">
                    <h2>Envoyez-nous un message</h2>
                    <form action="#" method="POST">
                        <div class="form-group">
                            <label for="name">Nom complet</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Adresse email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="tel" id="phone" name="phone">
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Sujet</label>
                            <select id="subject" name="subject" required>
                                <option value="">Sélectionnez un sujet</option>
                                <option value="demo">Demande de démonstration</option>
                                <option value="support">Support technique</option>
                                <option value="partnership">Partenariat</option>
                                <option value="billing">Question de facturation</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" required></textarea>
                        </div>
                        
                        <button type="submit" class="submit-btn">Envoyer le message</button>
                    </form>
                </div>
            </div>
            
            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3397.454263966667!2d-7.98127782417456!3d31.63689024219225!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xdafee8d96179e51%3A0x5950b6534f87adb8!2sMarrakech%2C%20Morocco!5e0!3m2!1sen!2sus!4v1700000000000!5m2!1sen!2sus" allowfullscreen="" loading="lazy"></iframe>
            </div>
            
            <div style="text-align: center; margin-top: 2rem;">
                <a href="./home" class="back-to-home">
                    <i class="fas fa-arrow-left"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </main>

    <!-- Footer (identique à votre landing page) -->
    <?php require_once('./assets/footer.php'); ?>
    <script>

        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const submitButton = form.querySelector('.submit-btn');
            const originalText = submitButton.textContent;
            
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.textContent = 'Envoi en cours...';
            submitButton.style.opacity = '0.7';
            
            // Create FormData object
            const formData = new FormData(form);
            
            // Send AJAX request
            fetch('./dashboard/controllers/contact_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success message
                    showNotification('Succès!', data.message, 'success');
                    form.reset();
                } else {
                    // Error message
                    showNotification('Erreur!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erreur!', 'Une erreur s\'est produite lors de l\'envoi. Veuillez réessayer.', 'error');
            })
            .finally(() => {
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.textContent = originalText;
                submitButton.style.opacity = '1';
            });
        });

        // Notification function
        function showNotification(title, message, type) {
            // Remove existing notification
            const existingNotification = document.querySelector('.notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <div class="notification-icon">
                        ${type === 'success' ? '✓' : '⚠'}
                    </div>
                    <div class="notification-text">
                        <strong>${title}</strong>
                        <p>${message}</p>
                    </div>
                    <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
            `;
            
            // Add notification to page
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
            
            // Animate in
            setTimeout(() => {
                notification.classList.add('notification-show');
            }, 100);
        }

        // Form validation improvements
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input, textarea, select');
            
            inputs.forEach(input => {
                // Real-time validation feedback
                input.addEventListener('blur', function() {
                    validateField(this);
                });
                
                input.addEventListener('input', function() {
                    // Remove error styling when user starts typing
                    this.classList.remove('field-error');
                    const errorMsg = this.parentElement.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                });
            });
        });

        function validateField(field) {
            const value = field.value.trim();
            let isValid = true;
            let errorMessage = '';
            
            // Remove existing error
            field.classList.remove('field-error');
            const existingError = field.parentElement.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }
            
            // Validation rules
            if (field.hasAttribute('required') && !value) {
                isValid = false;
                errorMessage = 'Ce champ est requis';
            } else if (field.type === 'email' && value && !isValidEmail(value)) {
                isValid = false;
                errorMessage = 'Veuillez entrer une adresse email valide';
            } else if (field.type === 'tel' && value && !isValidPhone(value)) {
                isValid = false;
                errorMessage = 'Veuillez entrer un numéro de téléphone valide';
            }
            
            if (!isValid) {
                field.classList.add('field-error');
                const errorElement = document.createElement('div');
                errorElement.className = 'error-message';
                errorElement.textContent = errorMessage;
                field.parentElement.appendChild(errorElement);
            }
            
            return isValid;
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function isValidPhone(phone) {
            const phoneRegex = /^[\+]?[0-9\s\-\(\)]{8,}$/;
            return phoneRegex.test(phone);
        }
        
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
        
    </script>
</body>
</html>