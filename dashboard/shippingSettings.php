<style>

    .settings-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        padding: 0;
        border: 1px solid rgba(255, 255, 255, 0.2);
        overflow: hidden;
    }

    .card-content {
        padding: 5px;
    }

    .form-section {
        background: #f8fafc;
        border-radius: 0px;
        padding: 24px;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: #1e293b;
        margin: 0 0 20px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-title::before {
        content: '';
        width: 4px;
        height: 20px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-radius: 2px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
        font-size: 14px;
    }

    .form-group input,
    .form-group select {
        padding: 14px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 15px;
        background: white;
        transition: all 0.2s ease;
        color: #374151;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        transform: translateY(-2px);
    }

    .form-group small {
        color: #6b7280;
        font-size: 12px;
        margin-top: 6px;
    }

    /* Toggle Switch */
    .toggle-switch {
        position: relative;
        width: 54px;
        height: 30px;
        margin-top: 4px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #cbd5e1;
        border-radius: 30px;
        transition: all 0.3s ease;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 4px;
        bottom: 4px;
        background: white;
        border-radius: 50%;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    input:checked + .slider {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
    }

    input:checked + .slider:before {
        transform: translateX(24px);
    }

    /* Button Styles */
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .btn:active {
        transform: translateY(0);
    }

    .btn {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 2px solid #e2e8f0;
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    /* Companies Section */
    .shipping-companies-section {
        margin-top: 32px;
    }

    .companies-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding: 24px;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border-radius: 0px;
        border: 1px solid #e2e8f0;
    }

    .companies-header h4 {
        color: #1e293b;
        margin: 0;
        font-size: 20px;
        font-weight: 700;
    }

    .company-item {
        background: white;
        border: 2px solid #f1f5f9;
        margin-bottom: 16px;
        overflow: hidden;
        transition: all 0.2s ease;
    }

    .company-item:hover {
        border-color: #e2e8f0;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        transform: translateY(-2px);
    }

    .company-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 24px;
        background: #fafbfc;
        border-bottom: 1px solid #f1f5f9;
    }

    .company-name {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 8px;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-active {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .status-inactive {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .company-actions {
        display: flex;
        gap: 8px;
    }

    .api-info {
        padding: 24px;
        color: #64748b;
        font-size: 14px;
        line-height: 1.6;
    }

    .api-url {
        font-family: 'Monaco', 'Menlo', monospace;
        background: #f8fafc;
        padding: 8px 12px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        font-size: 13px;
        margin-top: 4px;
        color: #475569;
    }

    .no-companies {
        text-align: center;
        padding: 48px 24px;
        color: #64748b;
        font-size: 16px;
        background: #f8fafc;
        border-radius: 16px;
        border: 2px dashed #cbd5e1;
    }

    /* Modal Styles */
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.8);
        backdrop-filter: blur(8px);
    }

    .modal-content {
        background: white;
        margin: 3% auto;
        padding: 0;
        border-radius: 0px;
        width: 90%;
        max-width: 600px;
        position: relative;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        max-height: 90vh;
        overflow-y: auto !important;
        animation: modalSlideIn 0.3s ease;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none;
    }

    .modal-content::-webkit-scrollbar {
        display: none; /* Chrome, Safari, Opera */
    }

    body.modal-open {
        overflow: hidden;
    }

    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: white;
        padding: 5px 32px;
        position: relative;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        padding: .4em;
    }

    .close {
        position: absolute;
        right: 24px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        color: rgba(255, 255, 255, 0.8);
        transition: all 0.2s ease;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .close:hover {
        color: white;
        background: rgba(255, 255, 255, 0.1);
    }

    .modal-body {
        padding: 32px;
    }

    .modal-footer {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        padding: 24px 32px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .companies-header {
            flex-direction: column;
            gap: 16px;
            align-items: stretch;
        }

        .company-header {
            flex-direction: column;
            gap: 16px;
            align-items: stretch;
        }

        .company-actions {
            align-self: stretch;
        }

        .company-actions .btn {
            flex: 1;
            justify-content: center;
        }
    }

    /* Notification Styles */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 500;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 300px; 
        animation: slideInRight 0.3s ease-out;
    }

    .notification-success {
        background-color: #10b981;
        border-left: 4px solid #059669;
    }

    .notification-error {
        background-color: #ef4444;
        border-left: 4px solid #dc2626;
    }

    .notification-info {
        background-color: #3b82f6;
        border-left: 4px solid #2563eb;
    }

    .notification button {
        background: none;
        border: none;
        color: white;
        font-size: 18px;
        cursor: pointer;
        margin-left: 10px;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification button:hover {
        opacity: 0.8;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    /* Loading Overlay */
    #loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .loading-content {
        background: white;
        padding: 30px;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    }

    .spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3498db;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        animation: spin 1s linear infinite;
        margin: 0 auto 15px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    #loading-message {
        color: #333;
        font-weight: 500;
    }

    /* Enhanced Modal Styles */
    .modal {
        z-index: 1000;
    }

    .modal-open {
        overflow: hidden;
    }

    /* Enhanced Company Item Styles */
    .company-item {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 16px;
        background: white;
        transition: all 0.2s ease;
    }

    .company-item:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-color: #d1d5db;
    }

    .company-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .company-name {
        font-size: 18px;
        font-weight: 600;
        color: #111827;
        margin-bottom: 8px;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-active {
        background-color: #d1fae5;
        color: #FFF;
    }

    .status-inactive {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .company-actions {
        display: flex;
        gap: 8px;
    }

    .company-actions .btn {
        padding: 6px 12px;
        font-size: 14px;
    }

    .api-info {
        padding-top: 16px;
        border-top: 1px solid #f3f4f6;
        color: #6b7280;
        font-size: 14px;
    }

    .api-url {
        font-family: 'Courier New', monospace;
        background-color: #f9fafb;
        padding: 8px 12px;
        border-radius: 4px;
        margin-top: 4px;
        word-break: break-all;
        border: 1px solid #e5e7eb;
    }

    .no-companies {
        text-align: center;
        padding: 40px 20px;
        color: #6b7280;
        background-color: #f9fafb;
        border-radius: 8px;
        border: 2px dashed #d1d5db;
    }

    /* Form Enhancements */
    .form-group small {
        display: block;
        margin-top: 4px;
        color: #6b7280;
        font-size: 12px;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .notification {
            right: 10px;
            left: 10px;
            min-width: auto;
        }
        
        .company-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .company-actions {
            margin-top: 12px;
            justify-content: flex-end;
        }
        
        .loading-content {
            margin: 20px;
            padding: 20px;
        }
    }
</style>

<div class="settings-section" id="shipping">
    <div class="settings-card">
        <div class="card-header">
            <h3>Paramètres d'expédition</h3>
        </div>
        
        <div class="card-content">
            <form id="shipping-form">
                <div class="form-section">
                    <div class="section-title">Configuration générale</div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="default-shipping-method">Méthode d'expédition par défaut</label>
                            <select id="default-shipping-method" name="default-shipping-method">
                                <option value="0">Sélectionner une méthode...</option>
                            </select>
                            <small>Choisissez parmi vos transporteurs configurés</small>
                        </div>
                        <div class="form-group">
                            <label for="auto-tracking">Suivi automatique</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="auto-tracking" name="auto-tracking" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tracking-update-interval">Intervalle de mise à jour du suivi (minutes)</label>
                            <input type="number" id="tracking-update-interval" name="tracking-update-interval" value="30" min="5" max="1440">
                            <small>Entre 5 minutes et 24 heures</small>
                        </div>
                        <div class="form-group">
                            <label for="auto-label-generation">Génération automatique d'étiquettes</label>
                            <label class="toggle-switch">
                                <input type="checkbox" id="auto-label-generation" name="auto-label-generation" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">Dimensions par défaut des colis</div>
                    
                    <div class="form-group">
                        <label for="default-package-weight">Poids par défaut du colis (kg)</label>
                        <input type="number" id="default-package-weight" name="default-package-weight" value="1.0" step="0.1" min="0.1">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="default-package-length">Longueur par défaut (cm)</label>
                            <input type="number" id="default-package-length" name="default-package-length" value="20" min="1">
                        </div>
                        <div class="form-group">
                            <label for="default-package-width">Largeur par défaut (cm)</label>
                            <input type="number" id="default-package-width" name="default-package-width" value="15" min="1">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="default-package-height">Hauteur par défaut (cm)</label>
                        <input type="number" id="default-package-height" name="default-package-height" value="10" min="1">
                    </div>
                </div>

                <style>
                    /* Carrier Priority */
                    .carrier-priority-list {
                        border: 1px solid #e5e7eb;
                        border-radius: 12px;
                        overflow: hidden;
                        background: #ffffff;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
                    }
                    
                    .carrier-priority-list > p {
                        padding: 1.5rem;
                        text-align: center;
                        color: #6b7280;
                        font-size: 0.95rem;
                    }
                    
                    .carrier-priority-item {
                        display: flex;
                        align-items: center;
                        gap: 1.25rem;
                        padding: 1.25rem 1.5rem;
                        background: white;
                        border-bottom: 1px solid #f3f4f6;
                        cursor: move;
                        transition: all 0.2s ease;
                        position: relative;
                    }
                    
                    .carrier-priority-item:last-child {
                        border-bottom: none;
                    }
                    
                    .carrier-priority-item:hover {
                        background: #fafbfc;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
                        transform: translateY(-1px);
                    }
                    
                    .carrier-priority-item.dragging {
                        opacity: 0.5;
                        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
                    }
                    
                    .drag-handle {
                        color: #9ca3af;
                        font-size: 1.2rem;
                        cursor: grab;
                        padding: 0.5rem;
                        border-radius: 6px;
                        transition: all 0.2s ease;
                        display: flex;
                        align-items: center;
                    }
                    
                    .drag-handle:hover {
                        background: #f3f4f6;
                        color: #6b7280;
                    }
                    
                    .drag-handle:active {
                        cursor: grabbing;
                    }
                    
                    .carrier-info {
                        flex: 1;
                        display: flex;
                        align-items: center;
                        gap: 1rem;
                        flex-wrap: wrap;
                    }
                    
                    .company-logo {
                        width: 48px;
                        height: 48px;
                        border-radius: 8px;
                        object-fit: contain;
                        border: 1px solid #e5e7eb;
                        padding: 4px;
                        background: white;
                    }
                    
                    .carrier-info strong {
                        font-size: 1rem;
                        color: #111827;
                        font-weight: 600;
                    }
                    
                    .carrier-info p {
                        width: 100%;
                        margin: 0.25rem 0 0 0;
                    }
                    
                    .carrier-info small {
                        color: #6b7280;
                        font-size: 0.875rem;
                    }
                    
                    .carrier-badge {
                        display: inline-flex;
                        align-items: center;
                        padding: 0.35rem 0.85rem;
                        border-radius: 20px;
                        font-size: 0.75rem;
                        font-weight: 600;
                        letter-spacing: 0.3px;
                        text-transform: uppercase;
                        transition: all 0.2s ease;
                    }
                    
                    .carrier-badge-primary {
                        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                        color: #1e40af;
                        box-shadow: 0 1px 3px rgba(30, 64, 175, 0.1);
                    }
                    
                    .carrier-badge-secondary {
                        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                        color: #92400e;
                        box-shadow: 0 1px 3px rgba(146, 64, 14, 0.1);
                    }
                    
                    .carrier-badge-tertiary {
                        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
                        color: #166534;
                        box-shadow: 0 1px 3px rgba(22, 101, 52, 0.1);
                    }
                    
                    .carrier-settings {
                        margin-left: auto;
                    }
                    
                    .carrier-settings label {
                        display: flex;
                        align-items: center;
                        gap: 0.6rem;
                        font-size: 0.875rem;
                        cursor: pointer;
                        color: #4b5563;
                        font-weight: 500;
                        padding: 0.5rem 0.75rem;
                        border-radius: 6px;
                        transition: all 0.2s ease;
                        user-select: none;
                    }
                    
                    .carrier-settings label:hover {
                        background: #f9fafb;
                        color: #111827;
                    }
                    
                    .carrier-settings input[type="checkbox"] {
                        width: 18px;
                        height: 18px;
                        cursor: pointer;
                        accent-color: #3b82f6;
                        border-radius: 4px;
                    }
                    
                    .settings-card h3 {
                        display: flex;
                        align-items: center;
                        gap: 0.75rem;
                        margin-bottom: 1.5rem;
                        font-size: 1.25rem;
                        color: #111827;
                        font-weight: 600;
                    }
                    
                    .settings-card h3 i {
                        color: #3b82f6;
                        font-size: 1.3rem;
                    }
                    
                    .form-group label {
                        display: block;
                        font-weight: 500;
                        color: #374151;
                        margin-bottom: 0.5rem;
                        font-size: 0.95rem;
                    }
                    
                    .form-control {
                        width: 100%;
                        padding: 0.75rem 1rem;
                        border: 1px solid #d1d5db;
                        border-radius: 8px;
                        font-size: 0.95rem;
                        color: #111827;
                        background: white;
                        transition: all 0.2s ease;
                        outline: none;
                    }
                    
                    .form-control:hover {
                        border-color: #9ca3af;
                    }
                    
                    .form-control:focus {
                        border-color: #3b82f6;
                        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                    }
                    
                    /* Responsive adjustments */
                    @media (max-width: 768px) {
                        .carrier-priority-item {
                            flex-direction: column;
                            align-items: flex-start;
                            gap: 1rem;
                        }
                        
                        .carrier-info {
                            width: 100%;
                        }
                        
                        .carrier-settings {
                            width: 100%;
                            margin-left: 0;
                        }
                    }
                </style>

                <div class="settings-card">
                    <h3><i class="fas fa-truck"></i> Priorité des Transporteurs</h3>

                    <div id="carrier-priority" class="carrier-priority-list">
                        <!-- Shipping companies will load here -->
                        <p id="loading-msg">Chargement des transporteurs...</p>
                    </div>

                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label>Logique de sélection automatique</label>
                        <select class="form-control" id="auto-selection-logic">
                            <option>Plus rapide disponible</option>
                            <option>Moins cher disponible</option>
                            <option>Plus fiable (taux de succès)</option>
                            <option selected>Par ordre de priorité</option>
                        </select>
                    </div>
                </div>
            </form>

            <!-- Shipping Companies Management Section -->
            <div class="shipping-companies-section">
                <div class="companies-header">
                    <h4>Transporteurs configurés</h4>
                    <button type="button" class="btn" onclick="openAddCompanyModal()">
                        + Ajouter un transporteur
                    </button>
                </div>

                <div id="companies-list">
                    <div class="no-companies">
                        Aucun transporteur configuré. Cliquez sur "Ajouter un transporteur" pour commencer.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .modal-header {
        padding: 24px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        font-size: 20px;
        font-weight: 600;
        color: #111827;
    }

    .close {
        font-size: 28px;
        color: #6b7280;
        cursor: pointer;
        line-height: 1;
        transition: color 0.2s;
        background: none;
        border: none;
        padding: 0;
    }

    .close:hover {
        color: #111827;
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 24px;
        border-top: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .required-asterisk {
        color: #ef4444;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 500;
        color: #374151;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.2s;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .form-group small {
        display: block;
        margin-top: 6px;
        font-size: 12px;
        color: #6b7280;
    }

    .text-muted {
        color: #6b7280;
    }

    .supported-carriers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 12px;
        padding: 16px;
        background: #f9fafb;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .supported-carrier {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 12px;
        background: white;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s;
    }

    .supported-carrier:hover {
        border-color: #2563eb;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .supported-carrier-logo {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 8px;
    }

    .supported-carrier-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .supported-carrier-logo svg {
        width: 32px;
        height: 32px;
        color: #9ca3af;
    }

    .supported-carrier-name {
        font-size: 12px;
        font-weight: 500;
        color: #374151;
        text-align: center;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 24px;
        border-top: 1px solid #e5e7eb;
        background: #f9fafb;
    }

    .btn-secondary,
    .btn-primary,
    .btn {
        padding: 10px 20px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }

    .btn-secondary {
        background: white;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
        background: #f9fafb;
    }

    .btn-primary,
    .btn {
        background: #2563eb;
        color: white;
    }

    .btn-primary:hover,
    .btn:hover {
        background: #1d4ed8;
    }

</style>

<!-- Add/Edit Company Modal -->
<div id="companyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Ajouter un transporteur</h3>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <form id="company-form">
                <div class="form-group">
                    <label for="company-select">Transporteur <span class="required-asterisk">*</span></label>
                    <select id="company-select" name="company-select" required onchange="handleCarrierChange()">
                        <option value="">Sélectionnez un transporteur</option>
                        <option value="ozonexpress">OzonExpress</option>
                        <option value="sendit">Sendit</option>
                        <option value="chronopost">Chronopost</option>
                        <option value="forcelog">Forcelog</option>
                        <option value="cathedis">Cathedis</option>
                        <option value="power">Power Delivery</option>
                        <option value="custom">Autre transporteur</option>
                    </select>
                </div>

                <div class="form-group" id="custom-name-group" style="display: none;">
                    <label for="custom-company-name">Nom personnalisé <span class="required-asterisk">*</span></label>
                    <input type="text" id="custom-company-name" name="custom-company-name" placeholder="Entrez le nom du transporteur">
                </div>

                <div class="form-group">
                    <label for="display-name">Nom d'affichage (optionnel)</label>
                    <input type="text" id="display-name" name="display-name" placeholder="Laissez vide pour utiliser le nom par défaut">
                </div>
            
            <div class="form-group">
                <label>Transporteurs supportés</label>
                <div class="supported-carriers-grid">
                    <div class="supported-carrier" data-carrier-id="ozonexpress">
                        <div class="supported-carrier-logo">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/>
                                <path d="M12 6c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4z"/>
                            </svg>
                        </div>
                        <div class="supported-carrier-name">OzonExpress</div>
                    </div>
                    <div class="supported-carrier" data-carrier-id="sendit">
                        <div class="supported-carrier-logo">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm13.5-9l1.96 2.5H17V9h2.5zm-1.5 9c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/>
                            </svg>
                        </div>
                        <div class="supported-carrier-name">Sendit</div>
                    </div>
                    <div class="supported-carrier" data-carrier-id="chronopost">
                        <div class="supported-carrier-logo">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z"/>
                                <path d="M12.5 7H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                            </svg>
                        </div>
                        <div class="supported-carrier-name">Chronopost</div>
                    </div>
                    <div class="supported-carrier" data-carrier-id="forcelog">
                        <div class="supported-carrier-logo">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M13 10h-2V8h2v2zm0-4h-2V1h2v5zM7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2zm-8.9-5h7.45c.75 0 1.41-.41 1.75-1.03l3.86-7.01L19.42 4l-3.87 7H8.53L4.27 2H1v2h2l3.6 7.59-1.35 2.44C4.52 15.37 5.48 17 7 17h12v-2H7l1.1-2z"/>
                            </svg>
                        </div>
                        <div class="supported-carrier-name">Forcelog</div>
                    </div>
                    <div class="supported-carrier" data-carrier-id="cathedis">
                        <div class="supported-carrier-logo">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19.5 20.5v-17A1.5 1.5 0 0018 2H6a1.5 1.5 0 00-1.5 1.5v17A1.5 1.5 0 006 22h12a1.5 1.5 0 001.5-1.5zM18 20H6V4h12v16z"/>
                                <path d="M8 7h8v2H8V7zm0 4h8v2H8v-2zm0 4h5v2H8v-2z"/>
                            </svg>
                        </div>
                        <div class="supported-carrier-name">Cathedis</div>
                    </div>
                    <div class="supported-carrier" data-carrier-id="power">
                        <div class="supported-carrier-logo">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M7 2v11h3v9l7-12h-4l4-8z"/>
                            </svg>
                        </div>
                        <div class="supported-carrier-name">Power Delivery</div>
                    </div>
                </div>
            </div>

                <div class="form-group">
                    <label for="company-api-url">URL de l'API <span class="required-asterisk">*</span></label>
                    <input type="url" id="company-api-url" name="api-url" placeholder="https://api.transporteur.com/v1" required>
                    <small class="text-muted">URL de base de l'API du transporteur</small>
                </div>

                <div class="form-group">
                    <label for="company-api-key">Clé API</label>
                    <input type="password" id="company-api-key" name="api-key" placeholder="Votre clé API">
                    <small class="text-muted">Optionnel - peut être configuré plus tard</small>
                </div>
                
                <div class="form-group">
                    <label for="api-secret">Secret API</label>
                    <input type="password" id="api-secret" name="api-secret" placeholder="Votre secret API">
                </div>
            </form>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
            <button type="submit" class="btn" form="company-form">Enregistrer</button>
        </div>
    </div>
</div>

<script>

    let shippingCompanies = [];
    let userSettings = [];
    let shippingConfig = {};
    let editingCompanyId = null;

    // Initialize the application
    document.addEventListener('DOMContentLoaded', function() {
        loadShippingData();
        initializeEventListeners();
    });

    // Load shipping data from backend
    async function loadShippingData() {
        try {
            showLoading('Chargement des données d\'expédition...');
            
            const response = await fetch('./controllers/get_shipping_data.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            });

            const result = await response.json();
            
            if (result.success) {
                shippingCompanies = result.data.shipping_companies || [];
                userSettings = result.data.user_settings || [];
                shippingConfig = result.data.shipping_config || {};
                
                // Get user-specific shipping settings
                userShippingSettings = (userSettings.length > 0) ? userSettings[0] : null;
                
                // First render the companies list and populate the dropdown
                renderCompaniesList();
                updateDefaultShippingSelect();
                
                // Then populate the form with saved values
                populateShippingConfig();
                
                showSuccess('Données d\'expédition chargées avec succès');
            } else {
                showError('Échec du chargement des données d\'expédition: ' + result.message);
            }
        } catch (error) {
            console.error('Error loading shipping data:', error);
            showError('Erreur réseau lors du chargement des données d\'expédition');
        } finally {
            hideLoading();
        }
    }

    function populateShippingConfig() {
        
        // Parse other_settings JSON if it exists
        let parsedOtherSettings = {};
        if (userShippingSettings && userShippingSettings.other_settings) {
            try {
                parsedOtherSettings = JSON.parse(userShippingSettings.other_settings);
            } catch (e) {
                console.warn('Failed to parse other_settings JSON:', e);
            }
        }
        
        // Build configuration values with proper priority
        const configValues = {
            'default-shipping-method': '',
            'auto-tracking': true,
            'tracking-update-interval': 30,
            'auto-label-generation': true,
            'default-package-weight': 1.0,
            'default-package-length': 20,
            'default-package-width': 15,
            'default-package-height': 10
        };
        
        // Apply user settings if available
        if (userShippingSettings) {
            // Handle shipping company selection
            if (userShippingSettings.shipping_company_id) {
                configValues['default-shipping-method'] = userShippingSettings.shipping_company_id;
            }
            
            // Handle boolean settings (convert string to boolean)
            configValues['auto-tracking'] = userShippingSettings.auto_track === '1' || userShippingSettings.auto_track === 1;
            configValues['auto-label-generation'] = userShippingSettings.SmartLabel === '1' || userShippingSettings.SmartLabel === 1;
            
            // Handle settings from other_settings JSON
            if (parsedOtherSettings.tracking_update_interval) {
                configValues['tracking-update-interval'] = parsedOtherSettings.tracking_update_interval;
            }
            
            if (parsedOtherSettings.default_package_weight) {
                configValues['default-package-weight'] = parsedOtherSettings.default_package_weight;
            }
            
            if (parsedOtherSettings.default_package_dimensions) {
                const dimensions = parsedOtherSettings.default_package_dimensions;
                configValues['default-package-length'] = dimensions.length || 20;
                configValues['default-package-width'] = dimensions.width || 15;
                configValues['default-package-height'] = dimensions.height || 10;
            }
        }
        
        // Populate form fields
        Object.entries(configValues).forEach(([fieldId, value]) => {
            const element = document.getElementById(fieldId);
            
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = Boolean(value);
                } else if (element.tagName === 'SELECT') {
                    setSelectValue(element, value.toString());
                } else {
                    element.value = value;
                }
            } else {
                console.warn(`Element with ID '${fieldId}' not found in DOM`);
            }
        });
    }

    function updateDefaultShippingSelect() {
        const selectElement = document.getElementById('default-shipping-method');
        if (!selectElement) {
            console.warn('Default shipping method select element not found');
            return;
        }
        
        // Clear existing options (except the first placeholder)
        while (selectElement.options.length > 1) {
            selectElement.removeChild(selectElement.lastChild);
        }
        
        // Add options for each active shipping company
        shippingCompanies.forEach(company => {
            if (company.is_active === '1' || company.is_active === 1) {
                const option = document.createElement('option');
                option.value = company.id;
                option.textContent = company.name;
                selectElement.appendChild(option);
            }
        });
    }

    function setSelectValue(selectElement, value) {
        if (!selectElement || selectElement.tagName !== 'SELECT') {
            console.warn('Invalid select element provided');
            return false;
        }
        
        // Convert value to string for comparison
        const stringValue = value.toString();
        
        // Find and select the matching option
        let optionFound = false;
        Array.from(selectElement.options).forEach(option => {
            if (option.value === stringValue) {
                option.selected = true;
                optionFound = true;
            } else {
                option.selected = false;
            }
        });
        
        if (!optionFound) {
            console.warn(`Option with value '${stringValue}' not found in select element`);
            // Set to first option (placeholder) if no match found
            if (selectElement.options.length > 0) {
                selectElement.selectedIndex = 0;
            }
        }
        
        return optionFound;
    }

    // Render companies list
    function renderCompaniesList() {
        const companiesList = document.getElementById('companies-list');
        
        if (!companiesList) {
            console.error('Companies list element not found');
            return;
        }
        
        if (shippingCompanies.length === 0) {
            companiesList.innerHTML = `
                <div class="no-companies">
                    Aucun transporteur configuré. Cliquez sur "Ajouter un transporteur" pour commencer.
                </div>
            `;
            updateDefaultShippingSelect();
            return;
        }

        companiesList.innerHTML = shippingCompanies.map(company => {
            // Find user settings for this company
            const userSetting = userSettings.find(setting => setting.shipping_company_id == company.id);
            const otherSettings = userSetting && userSetting.other_settings ? 
                JSON.parse(userSetting.other_settings) : {};
            
            return `
                <div class="company-item">
                    <div class="company-header">
                        <div>
                            <div class="company-name">${escapeHtml(company.name)}</div>
                            <span class="status-badge ${company.is_active == 1 ? 'status-active' : 'status-inactive'}">
                                ${company.is_active == 1 ? 'Actif' : 'Inactif'}
                            </span>
                        </div>
                        <div class="company-actions">
                            <button type="button" class="btn btn-secondary" onclick="editCompany(${company.id})">
                                Modifier
                            </button>
                            <button type="button" class="btn btn-danger" onclick="deleteCompany(${company.id})">
                                Supprimer
                            </button>
                        </div>
                    </div>
                    <div class="api-info">
                        <div><strong>URL API:</strong></div>
                        <div class="api-url">${escapeHtml(company.api_url)}</div>
                        <div style="margin-top: 16px;">
                            <strong>Suivi:</strong> ${otherSettings.supports_tracking ? 'Supporté' : 'Non supporté'}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        updateDefaultShippingSelect();
    }

    // Initialize event listeners
    function initializeEventListeners() {

        // Company form submission
        const companyForm = document.getElementById('company-form');
        if (companyForm) {
            companyForm.addEventListener('submit', handleCompanyFormSubmit);
        }

        // Modal close events
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('companyModal');
            if (event.target === modal) {
                closeModal();
            }
        });

        // Add keyboard event for modal close
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    }

    // Handle company form submission
    async function handleCompanyFormSubmit(e) {
        e.preventDefault();
        
        try {
            showLoading('Enregistrement du transporteur...');
            
            const formData = new FormData();
            
            // Get form values
            const companySelect = document.getElementById('company-select').value;
            const customName = document.getElementById('custom-company-name').value;
            const displayName = document.getElementById('display-name').value;
            const apiUrl = document.getElementById('company-api-url').value;
            const apiKey = document.getElementById('company-api-key').value;
            const apiSecret = document.getElementById('api-secret').value;
            
            // Determine company name and slug
            let companyName, companySlug;
            if (companySelect === 'custom') {
                companyName = customName;
                companySlug = customName.toLowerCase().replace(/\s+/g, '-');
            } else {
                const selectedOption = document.getElementById('company-select').options[document.getElementById('company-select').selectedIndex];
                companyName = selectedOption.text;
                companySlug = companySelect;
            }
            
            // Add form data
            formData.append('name', companyName);
            formData.append('slug', companySlug);
            formData.append('display_name', displayName || companyName);
            formData.append('api_url', apiUrl);
            formData.append('api_key', apiKey);
            formData.append('api_secret', apiSecret);
            
            // Add company ID if editing
            if (editingCompanyId) {
                formData.append('company-id', editingCompanyId);
            }

            const response = await fetch('./controllers/save_shipping_company.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                showSuccess(result.message);
                closeModal();
                // Reload data to get updated information
                await loadShippingData();
            } else {
                showError('Échec de l\'enregistrement: ' + result.message);
            }
        } catch (error) {
            console.error('Error saving company:', error);
            showError('Erreur réseau lors de l\'enregistrement');
        } finally {
            hideLoading();
        }
    }

    // Open add company modal
    function initializeCarrierSelection() {
        // Add click handlers for supported carriers grid
        const supportedCarriers = document.querySelectorAll('.supported-carrier');
        
        supportedCarriers.forEach(carrier => {
            carrier.addEventListener('click', function() {
                const carrierId = this.getAttribute('data-carrier-id');
                const carrierName = this.querySelector('.supported-carrier-name').textContent;
                handleCarrierSelection(carrierId, carrierName);
            });
        });
        
        // Add change handler for dropdown
        const companySelect = document.getElementById('company-select');
        companySelect.addEventListener('change', function() {
            handleCarrierChange();
        });
    }

    // Update your existing functions to use the new selection system
    function openAddCompanyModal() {
        editingCompanyId = null;
        document.getElementById('modal-title').textContent = 'Ajouter un transporteur';
        
        // Reset the form
        document.getElementById('company-form').reset();
        
        // Reset specific elements
        document.getElementById('company-select').value = '';
        document.getElementById('custom-company-name').value = '';
        document.getElementById('custom-name-group').style.display = 'none';
        
        // Clear any selection highlights
        const allCarriers = document.querySelectorAll('.supported-carrier');
        allCarriers.forEach(carrier => {
            carrier.classList.remove('selected');
        });
        
        // Show the modal
        document.getElementById('companyModal').style.display = 'block';
        document.body.classList.add('modal-open');
    }

    // Update editCompany function to use selection highlighting
    function editCompany(id) {
        const company = shippingCompanies.find(c => c.id == id);
        const userSetting = userSettings.find(setting => setting.shipping_company_id == id);

        if (!company) {
            showError('Transporteur non trouvé');
            return;
        }

        const otherSettings = userSetting && userSetting.other_settings ? 
            JSON.parse(userSetting.other_settings) : {};

        editingCompanyId = id;
        document.getElementById('modal-title').textContent = 'Modifier le transporteur';
        
        // Handle the company select dropdown and selection
        const companySelect = document.getElementById('company-select');
        if (companySelect) {
            // Check if company exists in predefined options
            const optionExists = Array.from(companySelect.options).some(
                option => option.value === company.slug || option.value === company.name.toLowerCase()
            );
            
            if (optionExists) {
                const carrierValue = company.slug || company.name.toLowerCase();
                companySelect.value = carrierValue;
                handleCarrierSelection(carrierValue, company.display_name || company.name);
            } else {
                // For custom carriers
                companySelect.value = 'custom';
                document.getElementById('custom-name-group').style.display = 'block';
                document.getElementById('custom-company-name').value = company.name;
                document.getElementById('display-name').value = company.display_name || '';
            }
        }
        
        // Set other field values
        document.getElementById('company-api-url').value = company.api_url || '';
        document.getElementById('company-api-key').value = company.api_key || '';
        document.getElementById('api-secret').value = otherSettings.api_secret || '';
        
        // Show the modal
        document.getElementById('companyModal').style.display = 'block';
        document.body.classList.add('modal-open');
    }

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeCarrierSelection();
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('companyModal');
            if (event.target === modal) {
                closeModal();
            }
        });
    });

    // Handle carrier selection change
    function handleCarrierChange() {
        const companySelect = document.getElementById('company-select');
        const customNameGroup = document.getElementById('custom-name-group');
        const customNameInput = document.getElementById('custom-company-name');
        const selectedValue = companySelect.value;
        
        if (selectedValue === 'custom') {
            customNameGroup.style.display = 'block';
            customNameInput.required = true;
            // Clear display name for custom carriers
            document.getElementById('display-name').value = '';
        } else {
            customNameGroup.style.display = 'none';
            customNameInput.required = false;
            customNameInput.value = '';
            
            // Set display name to carrier name when not custom
            if (selectedValue) {
                const selectedOption = companySelect.options[companySelect.selectedIndex];
                const carrierName = selectedOption.text;
                document.getElementById('display-name').value = carrierName;
            }
        }
        
        // Update visual selection
        updateCarrierSelectionHighlight(selectedValue);
    }

    // Update visual selection highlighting
    function updateCarrierSelectionHighlight(selectedCarrierId) {
        // Remove highlight from all carriers
        const allCarriers = document.querySelectorAll('.supported-carrier');
        allCarriers.forEach(carrier => {
            carrier.classList.remove('selected');
        });
        
        // Add highlight to selected carrier
        const selectedCarrier = document.querySelector(`.supported-carrier[data-carrier-id="${selectedCarrierId}"]`);
        if (selectedCarrier) {
            selectedCarrier.classList.add('selected');
        }
    }

    // Function to handle carrier selection from grid or dropdown
    function handleCarrierSelection(carrierId, carrierName = null) {
        const companySelect = document.getElementById('company-select');
        const displayNameInput = document.getElementById('display-name');
        
        // Set the select value
        companySelect.value = carrierId;
        
        // Update display name if not custom carrier
        if (carrierId !== 'custom' && carrierName) {
            displayNameInput.value = carrierName;
        } else if (carrierId === 'custom') {
            displayNameInput.value = '';
        }
        
        // Handle custom name group visibility
        handleCarrierChange();
        
        // Update visual selection
        updateCarrierSelectionHighlight(carrierId);
    }

    // Delete company
    async function deleteCompany(id) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce transporteur ?')) {
            return;
        }

        try {
            showLoading('Deleting company...');
            
            const formData = new FormData();
            formData.append('company-id', id);
            formData.append('action', 'delete');

            const response = await fetch('./controllers/delete_shipping_company.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                showSuccess('Company deleted successfully');
                await loadShippingData();
            } else {
                showError('Failed to delete company: ' + result.message);
            }
        } catch (error) {
            console.error('Error deleting company:', error);
            showError('Network error while deleting company');
        } finally {
            hideLoading();
        }
    }

    // Close modal
    function closeModal() {
        document.getElementById('companyModal').style.display = 'none';
        document.body.classList.remove('modal-open');
        editingCompanyId = null;
    }

    // Utility functions
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Notification functions
    function showSuccess(message) {
        showNotification('success', message);
    }

    function showError(message) {
        showNotification('error', message);
    }

    function showLoading(message = 'Loading...') {
        let loader = document.getElementById('loading-overlay');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'loading-overlay';
            loader.innerHTML = `
                <div class="loading-content">
                    <div class="spinner"></div>
                    <span id="loading-message">${escapeHtml(message)}</span>
                </div>
            `;
            document.body.appendChild(loader);
        } else {
            document.getElementById('loading-message').textContent = message;
        }
        loader.style.display = 'flex';
    }

    function hideLoading() {
        const loader = document.getElementById('loading-overlay');
        if (loader) {
            loader.style.display = 'none';
        }
    }

    async function loadShippingCompanies() {
        const userId = '<?php echo $user_id ?>'; 
        const container = document.getElementById('carrier-priority');
        const loadingMsg = document.getElementById('loading-msg');


        try {
            const response = await fetch(`./controllers/getShippingCompaniesApi.php?user_id=${userId}`);
            const data = await response.json();


            if (!data.success) throw new Error(data.message);

            container.innerHTML = ''; // clear loader

            if (data.companies.length === 0) {
                container.innerHTML = '<p>Aucun transporteur ajouté.</p>';
                return;
            }

            data.companies.forEach((company, index) => {
                const badgeClass = company.is_default
                    ? 'carrier-badge-primary'
                    : company.is_active
                        ? 'carrier-badge-secondary'
                        : 'carrier-badge-tertiary';

                const badgeText = company.is_default
                    ? 'Par défaut'
                    : company.is_active
                        ? 'Actif'
                        : 'Inactif';

                const item = document.createElement('div');
                item.className = 'carrier-priority-item';
                item.setAttribute('draggable', 'true');
                item.innerHTML = `
                    <span class="drag-handle"><i class="fas fa-grip-lines"></i></span>
                    <div class="carrier-info">
                        <img src="${company.logo_url || 'https://cdn-icons-png.flaticon.com/512/3211/3211362.png'}" 
                            alt="Logo" class="company-logo">
                        <strong>${index + 1}. ${company.name}</strong>
                        <span class="carrier-badge ${badgeClass}">${badgeText}</span>
                        <p><small>${company.phone ? `☎ ${company.phone}` : ''}</small></p>
                    </div>
                    <div class="carrier-settings">
                        <label>
                            <input type="checkbox" ${company.is_active ? 'checked' : ''}> 
                            Secours disponible
                        </label>
                    </div>
                `;
                container.appendChild(item);
            });

        } catch (error) {
            console.error('Erreur:', error);
            container.innerHTML = `<p style="color:red;">${error.message}</p>`;
        }
    }

    document.addEventListener('DOMContentLoaded', loadShippingCompanies);
    // Export functions for global access
    window.openAddCompanyModal = openAddCompanyModal;
    window.editCompany = editCompany;
    window.deleteCompany = deleteCompany;
    window.closeModal = closeModal;
</script>
