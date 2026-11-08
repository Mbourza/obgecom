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

    .settings-card h3 {
        margin: 0 0 1.75rem 0;
        font-size: 1.35rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .settings-card h3 i {
        color: #3b82f6;
        font-size: 1.25rem;
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

    .button-group {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* Order Assignment Section Styles */
    .assignment-method {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin: 1.5rem 0;
    }

    .assignment-option {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
        background: white;
    }

    .assignment-option:hover {
        border-color: #cbd5e1;
        transform: translateY(-2px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    .assignment-option.selected {
        border-color: #3b82f6;
        background: #eff6ff;
    }

    .option-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 0.75rem;
    }

    .option-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
    }

    .option-icon.region {
        background: #10b981;
    }

    .option-icon.performance {
        background: #f59e0b;
    }

    .option-header h4 {
        margin: 0;
        font-size: 1.1rem;
        color: #1e293b;
    }

    .option-description {
        color: #64748b;
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 1rem;
    }

    .assignment-settings {
        display: none;
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
    }

    .assignment-settings.active {
        display: block;
    }

    .region-settings {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .performance-metrics {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .metric-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: white;
    }

    .metric-checkbox {
        width: 20px;
        height: 20px;
    }

    .metric-info {
        flex: 1;
    }

    .metric-info strong {
        display: block;
        margin-bottom: 0.25rem;
        color: #1e293b;
    }

    .metric-info small {
        color: #64748b;
        font-size: 0.85rem;
    }

    .metric-weight {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .metric-weight input {
        width: 80px;
        padding: 0.5rem;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        text-align: center;
    }

    .metric-weight span {
        color: #64748b;
        font-size: 0.9rem;
    }

    /* Toggle Switch */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 52px;
        height: 28px;
    }

    .toggle-switch input[type="radio"] {
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
    .help-text {
        display: block;
        font-size: 0.875rem;
        color: #64748b;
        margin-top: 0.5rem;
        line-height: 1.5;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
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
        
        .card-header h3 {
            font-size: 1.2rem;
        }
        
        .assignment-option {
            padding: 1rem;
        }
        
        .option-header {
            flex-wrap: wrap;
        }
        
        .region-settings {
            grid-template-columns: 1fr;
        }
        
        .metric-item {
            flex-wrap: wrap;
        }
    }
</style>

<!-- Advanced Settings Section -->
<div class="settings-section" id="advanced">
    
    <!-- 1. Order Assignment Configuration -->
    <div class="settings-card">
        <h3><i class="fas fa-users-cog"></i> Configuration de l'Assignation des Commandes</h3>
        
        <div class="assignment-method">
            <div class="assignment-option selected" onclick="selectAssignmentMethod(this, 'region')">
                <div class="option-header">
                    <div class="option-icon region">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <h4>Assignation par Région/Ville</h4>
                        <div class="option-description">
                            Les commandes sont assignées aux agents basés sur leur zone géographique assignée
                        </div>
                    </div>
                    <label class="toggle-switch">
                        <input type="radio" name="assignmentMethod" value="region" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="assignment-settings active" id="region-settings">
                    <div class="region-settings">
                        <div class="form-group">
                            <label>Niveau de granularité</label>
                            <select class="form-control">
                                <option value="city">Par ville</option>
                                <option value="region" selected>Par région</option>
                                <option value="postal">Par code postal</option>
                                <option value="country">Par pays</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Réassignation automatique</label>
                            <select class="form-control">
                                <option value="none">Ne pas réassigner</option>
                                <option value="same-day" selected>Dans la même journée</option>
                                <option value="next-day">Le jour suivant</option>
                                <option value="immediate">Immédiatement</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" checked>
                                Permettre la réassignation manuelle
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox">
                                Inclure les agents hors-ligne
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="assignment-option" onclick="selectAssignmentMethod(this, 'performance')">
                <div class="option-header">
                    <div class="option-icon performance">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h4>Assignation par Performance</h4>
                        <div class="option-description">
                            Les commandes sont assignées aux agents basés sur leur performance et disponibilité
                        </div>
                    </div>
                    <label class="toggle-switch">
                        <input type="radio" name="assignmentMethod" value="performance">
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="assignment-settings" id="performance-settings">
                    <div class="performance-metrics">
                        <h4>Métriques de Performance</h4>
                        <p class="help-text">Sélectionnez et pondérez les métriques utilisées pour l'assignation</p>
                        
                        <div class="metric-item">
                            <input type="checkbox" class="metric-checkbox" checked>
                            <div class="metric-info">
                                <strong>Taux de confirmation</strong>
                                <small>Pourcentage de commandes confirmées avec succès</small>
                            </div>
                            <div class="metric-weight">
                                <input type="number" value="40" min="0" max="100">
                                <span>%</span>
                            </div>
                        </div>
                        
                        <div class="metric-item">
                            <input type="checkbox" class="metric-checkbox" checked>
                            <div class="metric-info">
                                <strong>Temps moyen de traitement</strong>
                                <small>Temps moyen pour confirmer une commande</small>
                            </div>
                            <div class="metric-weight">
                                <input type="number" value="30" min="0" max="100">
                                <span>%</span>
                            </div>
                        </div>
                        
                        <div class="metric-item">
                            <input type="checkbox" class="metric-checkbox">
                            <div class="metric-info">
                                <strong>Taux de satisfaction client</strong>
                                <small>Notes de satisfaction des clients</small>
                            </div>
                            <div class="metric-weight">
                                <input type="number" value="20" min="0" max="100">
                                <span>%</span>
                            </div>
                        </div>
                        
                        <div class="metric-item">
                            <input type="checkbox" class="metric-checkbox" checked>
                            <div class="metric-info">
                                <strong>Nombre de commandes actives</strong>
                                <small>Charge de travail actuelle de l'agent</small>
                            </div>
                            <div class="metric-weight">
                                <input type="number" value="10" min="0" max="100">
                                <span>%</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Seuil minimum de performance</label>
                            <div class="input-group">
                                <input type="number" class="form-control" value="70" min="0" max="100">
                                <span class="input-suffix">%</span>
                            </div>
                            <small class="help-text">Agents en dessous de ce score ne recevront pas de nouvelles commandes</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="button-group" style="margin-top: 1.5rem;">
            <button class="btn-primary" onclick="saveAssignmentSettings()">
                <i class="fas fa-save"></i> Sauvegarder la configuration
            </button>
            <button class="btn-secondary" onclick="resetAssignmentSettings()">
                <i class="fas fa-undo"></i> Réinitialiser
            </button>
        </div>
    </div>

    <!-- 2. Order Processing Preferences -->
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
        
        <div class="button-group" style="margin-top: 1.5rem;">
            <button class="btn-primary" onclick="saveProcessingPreferences()">
                <i class="fas fa-save"></i> Sauvegarder les préférences
            </button>
        </div>
    </div>
</div>

<script src="../assets/js/settings-service.js" ></script>

<script>
    function selectAssignmentMethod(element, method) {
        // Remove selected class from all options
        document.querySelectorAll('.assignment-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        // Add selected class to clicked option
        element.classList.add('selected');
        
        // Hide all settings
        document.querySelectorAll('.assignment-settings').forEach(setting => {
            setting.classList.remove('active');
        });
        
        // Show selected method's settings
        const settingsId = `${method}-settings`;
        const settingsElement = document.getElementById(settingsId);
        if (settingsElement) {
            settingsElement.classList.add('active');
        }
        
        // Update radio button
        const radioButton = element.querySelector('input[type="radio"]');
        if (radioButton) {
            radioButton.checked = true;
        }
    }

    const settingsService = new SettingsService();

    const ASSIGNMENT_KEYS = [
        'assignment_method',
        'assignment_granularity',
        'assignment_auto_reassign',
        'assignment_allow_manual',
        'assignment_include_offline',
        'performance_metrics',
        'performance_min_threshold'
    ];

    // Liste des clés de préférences de traitement
    const PROCESSING_KEYS = [
        'processing_auto_confirm_delay',
        'processing_auto_cancel_hours',
        'processing_merge_duplicates',
        'processing_send_notification'
    ];

    // Fonction pour charger les données depuis la base de données
    async function loadSettingsFromDatabase() {
        try {
            console.log('Chargement des paramètres depuis la base de données...');
            
            // Récupérer tous les paramètres d'un coup
            const allKeys = [...ASSIGNMENT_KEYS, ...PROCESSING_KEYS];
            const settings = await settingsService.getSettings(allKeys);
            
            console.log('Paramètres chargés:', settings);
            
            // Appliquer les paramètres d'assignation
            applyAssignmentSettings(settings);
            
            // Appliquer les préférences de traitement
            applyProcessingPreferences(settings);
            
            return {
                success: true,
                message: 'Paramètres chargés avec succès'
            };
        } catch (error) {
            console.error('Erreur lors du chargement des paramètres:', error);
            return {
                success: false,
                message: error.message
            };
        }
    }

    // les paramètres d'assignation depuis la base de données
    function applyAssignmentSettings(settings) {
        if (!settings || Object.keys(settings).length === 0) {
            console.log('Aucun paramètre d\'assignation trouvé, utilisation des valeurs par défaut');
            return;
        }
        
        const method = settings.assignment_method || 'region';
        console.log('Méthode d\'assignation détectée:', method);
        
        // Sélectionner la méthode dans l'interface
        if (method === 'region') {
            const regionOption = document.querySelector('.assignment-option');
            if (regionOption) {
                selectAssignmentMethod(regionOption, 'region');
            }
            
            // Appliquer les paramètres région
            if (settings.assignment_granularity) {
                const granularitySelect = document.querySelector('#region-settings select');
                if (granularitySelect) {
                    granularitySelect.value = settings.assignment_granularity;
                }
            }
            
            if (settings.assignment_auto_reassign) {
                const autoReassignSelect = document.querySelectorAll('#region-settings select')[1];
                if (autoReassignSelect) {
                    autoReassignSelect.value = settings.assignment_auto_reassign;
                }
            }
            
            if (settings.assignment_allow_manual !== undefined) {
                const allowManualCheckbox = document.querySelector('#region-settings input[type="checkbox"]');
                if (allowManualCheckbox) {
                    allowManualCheckbox.checked = settings.assignment_allow_manual;
                }
            }
            
            if (settings.assignment_include_offline !== undefined) {
                const includeOfflineCheckbox = document.querySelectorAll('#region-settings input[type="checkbox"]')[1];
                if (includeOfflineCheckbox) {
                    includeOfflineCheckbox.checked = settings.assignment_include_offline;
                }
            }
        } else if (method === 'performance') {
            const performanceOption = document.querySelectorAll('.assignment-option')[1];
            if (performanceOption) {
                selectAssignmentMethod(performanceOption, 'performance');
            }
            
            // Réinitialiser toutes les métriques
            document.querySelectorAll('.metric-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            document.querySelectorAll('.metric-item input[type="number"]').forEach(input => {
                input.value = 0;
            });
            
            // Appliquer les métriques de performance
            if (settings.performance_metrics && Array.isArray(settings.performance_metrics)) {
                const metricItems = document.querySelectorAll('.metric-item');
                settings.performance_metrics.forEach((metric, index) => {
                    if (metricItems[index]) {
                        const checkbox = metricItems[index].querySelector('.metric-checkbox');
                        const weightInput = metricItems[index].querySelector('input[type="number"]');
                        
                        if (checkbox && weightInput) {
                            checkbox.checked = true;
                            weightInput.value = metric.weight || 0;
                        }
                    }
                });
            }
            
            // Appliquer le seuil minimum
            if (settings.performance_min_threshold !== undefined) {
                const thresholdInput = document.querySelector('#performance-settings input[type="number"]');
                if (thresholdInput) {
                    thresholdInput.value = settings.performance_min_threshold;
                }
            }
            
            updateMetricWeights();
        }
    }

    // les préférences de traitement depuis la base de données
    function applyProcessingPreferences(settings) {
        if (!settings || Object.keys(settings).length === 0) {
            console.log('Aucune préférence de traitement trouvée, utilisation des valeurs par défaut');
            return;
        }
        
        // Délai de confirmation automatique
        if (settings.processing_auto_confirm_delay !== undefined) {
            const autoConfirmInput = document.querySelector('input[value="15"]');
            if (autoConfirmInput) {
                autoConfirmInput.value = settings.processing_auto_confirm_delay;
            }
        }
        
        // Auto-annulation
        if (settings.processing_auto_cancel_hours !== undefined) {
            const autoCancelInput = document.querySelector('input[value="48"]');
            if (autoCancelInput) {
                autoCancelInput.value = settings.processing_auto_cancel_hours;
            }
        }
        
        // Fusion des doublons
        if (settings.processing_merge_duplicates !== undefined) {
            const mergeCheckbox = document.querySelectorAll('input[type="checkbox"]')[0];
            if (mergeCheckbox) {
                mergeCheckbox.checked = settings.processing_merge_duplicates;
            }
        }
        
        // Notification client
        if (settings.processing_send_notification !== undefined) {
            const notificationCheckbox = document.querySelectorAll('input[type="checkbox"]')[1];
            if (notificationCheckbox) {
                notificationCheckbox.checked = settings.processing_send_notification;
            }
        }
    }

    // Mettre à jour les fonctions de sauvegarde pour utiliser l'API
    async function saveAssignmentSettings() {
        const selectedMethod = document.querySelector('input[name="assignmentMethod"]:checked').value;
        const settings = {};
        
        if (selectedMethod === 'region') {
            settings.method = 'region';
            settings.granularity = document.querySelector('#region-settings select').value;
            settings.autoReassign = document.querySelectorAll('#region-settings select')[1].value;
            settings.allowManual = document.querySelector('#region-settings input[type="checkbox"]').checked;
            settings.includeOffline = document.querySelectorAll('#region-settings input[type="checkbox"]')[1].checked;
        } else {
            settings.method = 'performance';
            settings.metrics = [];
            
            document.querySelectorAll('.metric-item').forEach(item => {
                const checkbox = item.querySelector('.metric-checkbox');
                const weight = item.querySelector('input[type="number"]').value;
                const metricName = item.querySelector('.metric-info strong').textContent;
                
                if (checkbox && checkbox.checked) {
                    settings.metrics.push({
                        name: metricName,
                        weight: parseInt(weight) || 0
                    });
                }
            });
            
            const thresholdInput = document.querySelector('#performance-settings input[type="number"]');
            settings.minThreshold = thresholdInput ? thresholdInput.value : '70';
        }
        
        console.log('Paramètres d\'assignation à sauvegarder:', settings);
        
        // Afficher un indicateur de chargement
        const saveButton = document.querySelector('#save-assignment-btn');
        const originalText = saveButton.innerHTML;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde en cours...';
        saveButton.disabled = true;
        
        try {
            const result = await settingsService.saveAssignmentSettings(settings);
            
            if (result.success) {
                alert('✓ ' + result.message);
            } else {
                alert('✗ ' + (result.message || 'Erreur lors de la sauvegarde'));
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('✗ Erreur: ' + error.message);
        } finally {
            // Restaurer le bouton
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
        }
    }

    async function saveProcessingPreferences() {
        const preferences = {
            autoConfirmDelay: parseInt(document.querySelector('input[value="15"]').value),
            autoCancelHours: parseInt(document.querySelector('input[value="48"]').value),
            mergeDuplicates: document.querySelectorAll('input[type="checkbox"]')[0].checked,
            sendNotification: document.querySelectorAll('input[type="checkbox"]')[1].checked
        };
        
        // Afficher un indicateur de chargement
        const saveButton = document.querySelector('#save-processing-btn');
        const originalText = saveButton.innerHTML;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sauvegarde en cours...';
        saveButton.disabled = true;
        
        try {
            const success = await settingsService.saveProcessingPreferences(preferences);
            
            if (success) {
                alert('Préférences de traitement sauvegardées avec succès!');
            } else {
                alert('Erreur lors de la sauvegarde. Veuillez réessayer.');
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur lors de la sauvegarde. Veuillez réessayer.');
        } finally {
            // Restaurer le bouton
            saveButton.innerHTML = originalText;
            saveButton.disabled = false;
        }
    }

    // Initialisation
    document.addEventListener('DOMContentLoaded', async function() {
        console.log('Initialisation des paramètres...');
        
        // Ajouter des IDs aux boutons de sauvegarde
        document.querySelectorAll('#advanced .btn-primary').forEach((btn, index) => {
            if (index === 0) btn.id = 'save-assignment-btn';
            if (index === 1) btn.id = 'save-processing-btn';
        });
        
        // Charger les paramètres au démarrage
        try {
            const result = await loadSettingsFromDatabase();
            
            if (!result.success) {
                console.warn('Avertissement lors du chargement des paramètres:', result.message);
            }
        } catch (error) {
            console.error('Erreur lors de l\'initialisation:', error);
        }
        
        // Initialiser les poids des métriques
        document.querySelectorAll('.metric-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateMetricWeights);
        });
        
        // Initialiser les poids au chargement
        updateMetricWeights();
    });

    // Initialize metric weights calculation
    function updateMetricWeights() {
        const checkboxes = document.querySelectorAll('.metric-checkbox');
        const weightInputs = document.querySelectorAll('.metric-item input[type="number"]');
        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        
        if (checkedCount > 0) {
            const baseWeight = Math.floor(100 / checkedCount);
            let remaining = 100;
            
            checkboxes.forEach((checkbox, index) => {
                if (checkbox.checked) {
                    weightInputs[index].value = baseWeight;
                    remaining -= baseWeight;
                }
            });
            
            // Distribute remainder to first checked metric
            if (remaining > 0) {
                const firstCheckedIndex = Array.from(checkboxes).findIndex(cb => cb.checked);
                if (firstCheckedIndex !== -1) {
                    weightInputs[firstCheckedIndex].value = parseInt(weightInputs[firstCheckedIndex].value) + remaining;
                }
            }
        }
    }

</script>