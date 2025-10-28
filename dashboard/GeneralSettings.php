<style>
    /* Notification Styles */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        padding: 12px 20px;
        border-radius: 4px;
        color: white;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideIn 0.3s ease-out;
    }

    .notification-success {
        background-color: #10b981;
        border-left: 4px solid #059669;
    }

    .notification-error {
        background-color: #ef4444;
        border-left: 4px solid #dc2626;
    }

    .notification-warning {
        background-color: #f59e0b;
        border-left: 4px solid #d97706;
    }

    .notification-info {
        background-color: #3b82f6;
        border-left: 4px solid #2563eb;
    }

    .notification-close {
        background: none;
        border: none;
        color: white;
        font-size: 18px;
        cursor: pointer;
        margin-left: 10px;
        padding: 0;
        line-height: 1;
    }

    .notification-close:hover {
        opacity: 0.8;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Form submit button loading state */
    .form-group button[type="submit"]:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Add submit button to your form if you don't have one */
    .settings-form-actions {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }

    .btn-primary {
        background-color: #3b82f6;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        transition: background-color 0.2s;
    }

    .btn-primary:hover {
        background-color: #2563eb;
    }

    .btn-primary:disabled {
        background-color: #9ca3af;
        cursor: not-allowed;
    }
</style>

<div class="settings-section active" id="general">
    <div class="settings-card">
        <h3>Informations générales</h3>
        <form id="general-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="company-name">Nom de l'entreprise</label>
                    <input type="text" id="company-name" name="company-name" value="Platform">
                </div>
                <div class="form-group">
                    <label for="company-email">Email de contact</label>
                    <input type="email" id="company-email" name="company-email" value="contact@platform.com">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="company-phone">Téléphone</label>
                    <input type="tel" id="company-phone" name="company-phone" placeholder="06 ...">
                </div>
                <div class="form-group">
                    <label for="timezone">Fuseau horaire</label>
                    <select id="timezone" name="timezone">
                        <option value="Europe/Paris" selected>Europe/Paris (GMT+1)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="company-address">Adresse de l'entreprise</label>
                <textarea id="company-address" name="company-address" rows="3">123 Rue de la Paix, 75001 Paris, France</textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="default-currency">Devise par défaut</label>
                    <select id="default-currency" name="default-currency">
                        <option value="MAD" selected>MAD (DH)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="default-language">Langue par défaut</label>
                    <select id="default-language" name="default-language">
                        <option value="fr" selected>Français</option>
                        <option value="en">English</option>
                    </select>
                </div>
            </div>
        </form>
    </div>
</div>