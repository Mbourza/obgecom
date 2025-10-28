<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f5f5;
    }

    /* Footer Section */
    .footer-section {
        background: linear-gradient(135deg, #e8dff5 0%, #f3e5f5 100%);
        padding: 60px 40px 20px 40px;
        box-shadow: 0 -4px 20px rgba(124, 77, 255, 0.1);
        position: relative;
        overflow: hidden;
    }

    .footer-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #9c27b0, #673ab7, #7c4dff, #9c27b0);
        background-size: 200% 100%;
        animation: gradientShift 3s ease infinite;
    }

    @keyframes gradientShift {
        0%, 100% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 40px;
    }

    .footer-logo {
        display: flex;
        align-items: center;
        gap: 20px;
        flex: 1;
    }

    .logo-wrapper {
        position: relative;
        transition: transform 0.3s ease;
    }

    .logo-wrapper:hover {
        transform: scale(1.05);
    }

    .logo-wrapper img {
        max-width: 180px;
        height: auto;
        filter: drop-shadow(0 4px 8px rgba(124, 77, 255, 0.2));
    }

    .footer-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 20px;
    }

    .social-icons {
        display: flex;
        gap: 12px;
    }

    .social-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 22px;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        position: relative;
        overflow: hidden;
    }

    .social-icon::before {
        content: '';
        position: absolute;
        inset: 0;
        background: white;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .social-icon:hover {
        transform: translateY(-4px) scale(1.05);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
    }

    .social-icon:hover::before {
        opacity: 0.15;
    }

    .social-icon.instagram {
        background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
    }

    .social-icon.linkedin {
        background: linear-gradient(135deg, #0077b5 0%, #005885 100%);
    }

    .social-icon.tiktok {
        background: linear-gradient(135deg, #000000 0%, #333333 100%);
    }

    .footer-email {
        font-size: 1rem;
        color: #5e35b1;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: white;
        border-radius: 25px;
        box-shadow: 0 2px 8px rgba(124, 77, 255, 0.15);
        transition: all 0.3s ease;
    }

    .footer-email:hover {
        box-shadow: 0 4px 16px rgba(124, 77, 255, 0.25);
        transform: translateY(-2px);
    }

    .footer-email i {
        color: #7c4dff;
    }

    .footer-divider {
        max-width: 1200px;
        margin: 40px auto 30px;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(124, 77, 255, 0.3), transparent);
    }

    .footer-links {
        text-align: center;
        padding: 20px 0;
    }

    .footer-links a {
        color: #5e35b1;
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 600;
        margin: 0 20px;
        transition: all 0.3s ease;
        position: relative;
        padding: 5px 0;
    }

    .footer-links a::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        width: 0;
        height: 2px;
        background: linear-gradient(90deg, #9c27b0, #7c4dff);
        transform: translateX(-50%);
        transition: width 0.3s ease;
    }

    .footer-links a:hover {
        color: #7c4dff;
    }

    .footer-links a:hover::after {
        width: 100%;
    }

    .footer-separator {
        color: #b39ddb;
        margin: 0 5px;
    }

    /* Payment Section */
    .footer-payment {
        max-width: 1200px;
        margin: 30px auto 0;
        padding: 25px 30px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 4px 16px rgba(124, 77, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 30px;
    }

    .payment-section {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .payment-label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #5e35b1;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .payment-icons {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .payment-icon {
        display: inline-block;
        transition: all 0.3s ease;
        filter: grayscale(0.3);
    }

    .payment-icon:hover {
        transform: translateY(-3px);
        filter: grayscale(0);
    }

    .payment-icon img {
        height: 32px;
        width: auto;
    }

    .secure-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #2e7d32;
    }

    .secure-badge i {
        font-size: 1rem;
    }

    .footer-copyright {
        text-align: center;
        padding: 20px 0;
        font-size: 0.85rem;
        color: #7e57c2;
        font-weight: 500;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .footer-section {
            padding: 40px 20px 20px;
        }

        .footer-container {
            flex-direction: column;
            text-align: center;
        }

        .footer-logo {
            justify-content: center;
        }

        .footer-right {
            align-items: center;
        }

        .footer-links a {
            display: block;
            margin: 12px 0;
        }

        .footer-separator {
            display: none;
        }

        .footer-payment {
            flex-direction: column;
            gap: 20px;
            padding: 20px;
        }

        .payment-section {
            flex-direction: column;
            text-align: center;
        }
    }
</style>

<style>

     /* WhatsApp Floating Button */
     .whatsapp-float {
        position: fixed;
        width: 60px;
        height: 60px;
        bottom: 20px;
        right: 20px;
        background: linear-gradient(135deg, #25d366 0%, #20b955 100%);
        color: white;
        border-radius: 50%;
        text-align: center;
        font-size: 32px;
        box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
        z-index: 999;
        transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        animation: pulse 2s infinite;
    }

    .whatsapp-float:hover {
        transform: scale(1.15) rotate(5deg);
        box-shadow: 0 6px 30px rgba(37, 211, 102, 0.6);
    }

    .whatsapp-float:active {
        transform: scale(0.95);
    }

    .whatsapp-float i {
        line-height: 1;
    }

    /* Tooltip */
    .whatsapp-float::before {
        content: attr(data-tooltip);
        position: absolute;
        right: 70px;
        background-color: #333;
        color: white;
        padding: 8px 15px;
        border-radius: 8px;
        font-size: 14px;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }

    .whatsapp-float::after {
        content: '';
        position: absolute;
        right: 60px;
        border: 6px solid transparent;
        border-left-color: #333;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .whatsapp-float:hover::before,
    .whatsapp-float:hover::after {
        opacity: 1;
        visibility: visible;
    }

    /* Pulse Animation */
    @keyframes pulse {
        0% {
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
        }
        50% {
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.7), 0 0 0 10px rgba(37, 211, 102, 0.1);
        }
        100% {
            box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
        }
    }

    /* Ripple effect on click */
    .whatsapp-float::after {
        animation: ripple 0.6s ease-out;
    }

    @keyframes ripple {
        0% {
            box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7);
        }
        100% {
            box-shadow: 0 0 0 20px rgba(37, 211, 102, 0);
        }
    }

    /* Mobile responsiveness */
    @media (max-width: 768px) {
        .whatsapp-float {
            width: 55px;
            height: 55px;
            font-size: 28px;
            bottom: 15px;
            right: 15px;
        }

        .whatsapp-float::before {
            display: none;
        }

        .whatsapp-float::after {
            display: none;
        }
    }

</style>

<a href="https://wa.me/+212714620695" 
    class="whatsapp-float" 
    target="_blank" 
    rel="noopener noreferrer"
    data-tooltip="Chat with us!"
    aria-label="Chat with us on WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- Footer Section -->
<footer class="footer-section">
    <div class="footer-container">
        <div class="footer-logo">
            <div class="logo-wrapper">
                <img src="./assets/img/dark_logo.png" alt="OBG Logo">
            </div>
        </div>
        
        <div class="footer-right">
            <div class="social-icons">
                <a href="https://www.instagram.com/obgecom/" class="social-icon instagram" aria-label="Instagram">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="https://www.linkedin.com/company/obgecom/" class="social-icon linkedin" aria-label="LinkedIn">
                    <i class="fab fa-linkedin"></i>
                </a>
                <a href="https://www.facebook.com/share/1C7eoqSMqt/" class="social-icon tiktok" aria-label="facebook">
                    <i class="fab fa-facebook"></i>
                </a>
            </div>

            <div class="footer-email">
                <i class="fas fa-envelope"></i>
                support@obgecom.com
            </div>
        </div>
    </div>
    
    <div class="footer-divider"></div>
    
    <div class="footer-links">
        <a href="#">Mentions légales</a>
        <span class="footer-separator">|</span>
        <a href="conditions">Politique de confidentialité</a>
        <span class="footer-separator">|</span>
        <a href="./contact">Contact</a>
    </div>

    <!-- Secure Payment Section -->
    <div class="footer-payment">
        <div class="payment-section">
            <span class="payment-label">Paiement sécurisé</span>
            <div class="payment-icons">
                <a href="https://www.mastercard.com" target="_blank" class="payment-icon">
                    <img src="./assets/img/mastercard.png" alt="Mastercard">
                </a>
                <a href="https://www.visa.com" target="_blank" class="payment-icon">
                    <img src="./assets/img/visa.png" alt="Visa" style="height: 45px;">
                </a>
                <a href="http://www.cmi.co.ma" target="_blank" class="payment-icon">
                    <img src="./assets/img/cmicard.png" alt="CMI">
                </a>
            </div>
        </div>
        
        <div class="secure-badge">
            <i class="fas fa-shield-alt"></i>
            <span>100% Sécurisé</span>
        </div>
    </div>

    <div class="footer-copyright">
        © 2025 OBG. Tous droits réservés.
    </div>
</footer>