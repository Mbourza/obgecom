<style>

    /* Settings Section */
    .settings-section {
        max-width: 1200px;
        margin: 0 auto;
    }

    .settings-card {
        background: white;
        border-radius: 12px;
        padding: 1.75rem;
        margin-bottom: 1.75rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.1);
        border: 1px solid #e2e8f0;
        transition: box-shadow 0.2s ease, transform 0.1s ease;
    }

    .settings-card:hover {
        box-shadow: 0 4px 6px rgba(0,0,0,0.05), 0 4px 6px rgba(0,0,0,0.1);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.75rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #f1f5f9;
    }

    .card-header h3 {
        margin: 0;
        font-size: 1.35rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .card-header h3 i {
        color: #3b82f6;
        font-size: 1.25rem;
    }

    /* Automation Rules */
    .automation-rule {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
        transition: all 0.2s ease;
    }

    .automation-rule:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
    }

    .rule-content {
        flex: 1;
    }

    .rule-conditions, .rule-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .rule-label {
        font-weight: 600;
        color: #475569;
        min-width: 50px;
    }

    .rule-select, .rule-operator {
        padding: 0.6rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: white;
        font-size: 0.9rem;
        transition: border-color 0.2s ease;
    }

    .rule-select:focus, .rule-operator:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .rule-operator {
        width: 60px;
        text-align: center;
    }

    .rule-input {
        padding: 0.6rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        flex: 1;
        transition: border-color 0.2s ease;
    }

    .rule-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .rule-actions-btns {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .status-badge {
        padding: 0.35rem 0.85rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .status-active {
        background: #d1fae5;
        color: #065f46;
    }

    .status-inactive {
        background: #fef3c7;
        color: #92400e;
    }

    /* Webhooks */
    .webhook-item {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        gap: 1.25rem;
        background: #f8fafc;
    }

    .webhook-info {
        flex: 1;
    }

    .webhook-events {
        display: flex;
        gap: 1.25rem;
        margin-top: 0.85rem;
        flex-wrap: wrap;
    }

    .webhook-events label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        cursor: pointer;
    }

    .webhook-actions {
        display: flex;
        gap: 0.5rem;
        align-items: flex-start;
    }

    /* API Tokens */
    .api-tokens-list {
        margin-bottom: 1.25rem;
    }

    .api-token-item {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8fafc;
    }

    .token-info {
        flex: 1;
    }

    .token-info strong {
        display: block;
        margin-bottom: 0.5rem;
        color: #1e293b;
    }

    .token-value {
        background: #f1f5f9;
        padding: 0.6rem 1rem;
        border-radius: 8px;
        font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
        font-size: 0.85rem;
        display: inline-block;
        margin-bottom: 0.5rem;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .token-meta {
        font-size: 0.85rem;
        color: #64748b;
    }

    .token-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Carrier Priority */
    .carrier-priority-list {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        overflow: hidden;
    }

    .carrier-priority-list > p {

        padding: .4em;
    }

    .carrier-priority-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem;
        background: white;
        border-bottom: 1px solid #e2e8f0;
        cursor: move;
        transition: background 0.2s ease;
    }

    .carrier-priority-item:last-child {
        border-bottom: none;
    }

    .carrier-priority-item:hover {
        background: #f8fafc;
    }

    .drag-handle {
        color: #94a3b8;
        font-size: 1.1rem;
        cursor: grab;
        padding: 0.5rem;
        border-radius: 4px;
        transition: background 0.2s ease;
    }

    .drag-handle:hover {
        background: #e2e8f0;
    }

    .carrier-info {
        flex: 1;
    }

    .carrier-badge {
        display: inline-block;
        padding: 0.3rem 0.8rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 0.5rem;
    }

    .carrier-badge-primary {
        background: #dbeafe;
        color: #1e40af;
    }

    .carrier-badge-secondary {
        background: #fef3c7;
        color: #92400e;
    }

    .carrier-badge-tertiary {
        background: #dcfce7;
        color: #166534;
    }

    .carrier-settings label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        cursor: pointer;
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #374151;
    }

    .form-control {
        width: 100%;
        padding: 0.85rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.2s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .input-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .input-group .form-control {
        width: auto;
    }

    .input-suffix {
        color: #64748b;
        font-size: 0.875rem;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: normal;
        cursor: pointer;
    }

    .checkbox-label input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .radio-group {
        display: flex;
        gap: 1.5rem;
    }

    .radio-group label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: normal;
        cursor: pointer;
    }

    /* Buttons */
    .btn-primary {
        background: #3b82f6;
        color: white;
        padding: 0.85rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
        padding: 0.85rem 1.5rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
        transform: translateY(-1px);
    }

    .btn-danger {
        background: #ef4444;
        color: white;
        padding: 0.85rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-danger:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(220, 38, 38, 0.2);
    }

    .btn-icon {
        background: transparent;
        border: none;
        padding: 0.6rem;
        cursor: pointer;
        font-size: 1.1rem;
        transition: all 0.2s ease;
        border-radius: 6px;
        color: #64748b;
    }

    .btn-icon:hover {
        background: #f1f5f9;
        color: #334155;
    }

    .button-group {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* Sync Integrations */
    .sync-integrations {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-top: 0.75rem;
    }

    .sync-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
    }

    .sync-item img {
        flex-shrink: 0;
        border-radius: 6px;
    }

    .sync-item span {
        flex: 1;
        font-weight: 500;
    }

    /* Beta Features */
    .beta-features-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .beta-feature-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #f8fafc;
    }

    .feature-info {
        flex: 1;
    }

    .feature-info strong {
        display: block;
        margin-bottom: 0.25rem;
        color: #1e293b;
    }

    .feature-info p {
        margin: 0.5rem 0 0 0;
        font-size: 0.875rem;
        color: #64748b;
    }

    .beta-badge {
        display: inline-block;
        padding: 0.2rem 0.6rem;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        background: #fef3c7;
        color: #92400e;
        margin-left: 0.5rem;
    }

    /* Toggle Switch */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 28px;
    }

    .toggle-switch input[type="checkbox"] {
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
        background-color: #cbd5e1;
        transition: 0.3s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    .toggle-switch input:checked + .slider {
        background-color: #3b82f6;
    }

    .toggle-switch input:checked + .slider:before {
        transform: translateX(24px);
    }

    /* Info/Help Text */
    .info-box {
        background: #eff6ff;
        border-left: 4px solid #3b82f6;
        padding: 1.25rem;
        border-radius: 8px;
        margin-top: 1.25rem;
    }

    .info-box strong {
        display: block;
        margin-bottom: 0.5rem;
        color: #1e40af;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .help-text {
        display: block;
        font-size: 0.875rem;
        color: #64748b;
        margin-top: 0.5rem;
        line-height: 1.5;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .automation-rule,
        .webhook-item,
        .api-token-item,
        .beta-feature-item {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .rule-actions-btns,
        .webhook-actions,
        .token-actions {
            width: 100%;
            justify-content: flex-end;
            margin-top: 1rem;
        }
        
        .radio-group {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .button-group {
            flex-direction: column;
        }
        
        .button-group button {
            width: 100%;
        }
        
        .card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .card-header h3 {
            font-size: 1.2rem;
        }
    }
</style>

<!-- Advanced Settings Section -->
<div class="settings-section" id="advanced">
    
    <!-- 1. Order Processing Preferences -->
    <div class="settings-card">
        <h3><i class="fas fa-cogs"></i> Préférences de Traitement</h3>
        
        <form>
            <div class="form-group">
                <label>Délai de confirmation automatique</label>
                <div class="input-group">
                    <input type="number" class="form-control" value="15" min="0" max="1440">
                    <span class="input-suffix">minutes</span>
                </div>
                <small class="help-text">Attendre X minutes avant de confirmer une commande automatiquement</small>
            </div>
            
            <div class="form-group">
                <label>Auto-annulation des commandes non confirmées</label>
                <div class="input-group">
                    <input type="number" class="form-control" value="48" min="1" max="168">
                    <span class="input-suffix">heures</span>
                </div>
                <small class="help-text">Annuler automatiquement les commandes non confirmées après ce délai</small>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox">
                    Fusionner automatiquement les commandes en double du même client
                </label>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" checked>
                    Envoyer notification au client après confirmation
                </label>
            </div>
        </form>
    </div>

    <!-- 2. Webhooks & Callbacks -->
    <div class="settings-card">
        <div class="card-header">
            <h3><i class="fas fa-link"></i> Webhooks & Notifications</h3>
            <button class="btn-primary" onclick="addWebhook()">
                <i class="fas fa-plus"></i> Ajouter un webhook
            </button>
        </div>
        
        <div id="webhooks-list">
            <div class="webhook-item">
                <div class="webhook-info">
                    <input type="url" class="form-control" placeholder="https://votresite.com/webhook" 
                        value="https://monapp.com/api/orders">

                    <div class="webhook-events">
                        <label><input type="checkbox" checked> Commande créée</label>
                        <label><input type="checkbox" checked> Statut changé</label>
                        <label><input type="checkbox"> Livraison échouée</label>
                        <label><input type="checkbox"> Remboursement demandé</label>
                    </div>
                </div>
                <div class="webhook-actions">
                    <button class="btn-secondary" onclick="testWebhook(this)">
                        <i class="fas fa-vial"></i> Tester
                    </button>
                    <button class="btn-icon" onclick="deleteWebhook(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <small class="help-text">Les webhooks envoient des notifications HTTP POST à vos systèmes externes lors d'événements spécifiques.</small>
    </div>

    <!-- 3. Beta Features -->
    <div class="settings-card">
        <h3><i class="fas fa-flask"></i> Fonctionnalités Expérimentales</h3>
        <p class="help-text">Testez les nouvelles fonctionnalités avant leur sortie officielle</p>
        
        <div class="beta-features-list">
            
            <div class="beta-feature-item">
                <div class="feature-info">
                    <strong>Page de suivi améliorée</strong>
                    <span class="beta-badge">BETA</span>
                    <p>Nouvelle mise en page avec carte interactive</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox">
                    <span class="slider"></span>
                </label>
            </div>
            
            <div class="beta-feature-item">
                <div class="feature-info">
                    <strong>Optimisation Intelligente de Livraison</strong>
                    <span class="beta-badge">BETA</span>
                    <p>IA qui sélectionne automatiquement le meilleur transporteur</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox">
                    <span class="slider"></span>
                </label>
            </div>
            
            <div class="beta-feature-item">
                <div class="feature-info">
                    <strong>Prédiction des délais de livraison</strong>
                    <span class="beta-badge">BETA</span>
                    <p>Estimation précise basée sur l'historique et les données en temps réel</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="slider"></span>
                </label>
            </div>
        </div>
    </div>
</div>

<script>

    function addWebhook() {
        alert("Ajouter un nouveau webhook");
    }
    
    function testWebhook(button) {
        alert("Tester le webhook");
    }
    
    function deleteWebhook(button) {
        alert("Supprimer le webhook");
    }
    
</script>
