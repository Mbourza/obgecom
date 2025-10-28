<style>
    .last-updated {
        font-size: 0.8em;
        color: #666;
        margin-top: 15px;
        text-align: right;
    }
</style>

<div class="settings-section" id="notifications">
    <div class="settings-card">
        <h3>Notifications par email</h3>
        <form id="notifications-form">
            <div class="notification-item">
                <div>
                    <strong>Nouvelle commande</strong>
                    <br><small>Recevoir un email pour chaque nouvelle commande</small>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="notify-new-order" checked>
                    <span class="slider"></span>
                </label>
            </div>
            
            <div class="notification-item">
                <div>
                    <strong>Statut de livraison</strong>
                    <br><small>Notifications sur les changements de statut</small>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="notify-delivery-status" checked>
                    <span class="slider"></span>
                </label>
            </div>
            
            <div class="notification-item">
                <div>
                    <strong>Erreurs de synchronisation</strong>
                    <br><small>Alertes en cas d'erreur de synchronisation</small>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="notify-sync-errors" checked>
                    <span class="slider"></span>
                </label>
            </div>
            
            <div class="notification-item">
                <div>
                    <strong>Rapport quotidien</strong>
                    <br><small>Résumé quotidien des activités</small>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="notify-daily-report">
                    <span class="slider"></span>
                </label>
            </div>
            
            <div class="notification-item">
                <div>
                    <strong>Maintenance système</strong>
                    <br><small>Notifications de maintenance et mises à jour</small>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="notify-maintenance" checked>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="last-updated" id="last-updated"></div>
        </form>
    </div>
</div>

<script>
    class NotificationSettings {
        constructor() {
            this.form = document.getElementById('notifications-form');
            this.messageContainer = document.getElementById('message-container');
            this.lastUpdatedElement = document.getElementById('last-updated');
            this.debounceTimer = null;
            
            this.init();
        }
        
        init() {
            this.loadSettings();
            this.attachEventListeners();
        }
        
        attachEventListeners() {
            // Add change event listeners to all checkboxes
            const checkboxes = this.form.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    this.debouncedSave();
                });
            });
        }
        
        debouncedSave() {
            // Clear existing timer
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }
            
            // Set new timer to save after 500ms of inactivity
            this.debounceTimer = setTimeout(() => {
                this.saveSettings();
            }, 500);
        }
        
        async loadSettings() {
            try {
                this.setLoading(true);
                
                const response = await fetch('./controllers/load_notification_settings.php', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    this.populateForm(result.data);
                    this.updateLastUpdated(result.data.last_updated);
                } else {
                    showNotification('error', result.message || 'Erreur lors du chargement des paramètres');
                }
            } catch (error) {
                console.error('Error loading settings:', error);
                showNotification('error', 'Erreur de connexion lors du chargement des paramètres');
            } finally {
                this.setLoading(false);
            }
        }
        
        populateForm(data) {
            Object.keys(data).forEach(key => {
                if (key.startsWith('notify_')) {
                    const htmlKey = key.replaceAll('_', '-');  // 🔁 this is the fix
                    const checkbox = this.form.querySelector(`input[name="${htmlKey}"]`);
                    if (checkbox) {
                        checkbox.checked = !!data[key]; // ensure boolean
                    } else {
                        console.warn(`No checkbox found for name="${htmlKey}"`);
                    }
                }
            });
        }

        
        async saveSettings() {
            try {
                this.setLoading(true);
                
                const formData = new FormData(this.form);
                
                const response = await fetch('./controllers/save_notification_settings.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('success', result.message);
                    this.updateLastUpdated(new Date().toISOString());
                } else {
                    showNotification('error', result.message || 'Erreur lors de la sauvegarde');
                }
            } catch (error) {
                console.error('Error saving settings:', error);
                showNotification('error', 'Erreur de connexion lors de la sauvegarde');
            } finally {
                this.setLoading(false);
            }
        }
        
        setLoading(isLoading) {
            const settingsCard = document.querySelector('.settings-card');
            if (isLoading) {
                settingsCard.classList.add('loading');
            } else {
                settingsCard.classList.remove('loading');
            }
        }
        
        updateLastUpdated(timestamp) {
            if (timestamp) {
                const date = new Date(timestamp);
                const formattedDate = date.toLocaleString('fr-FR', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                this.lastUpdatedElement.textContent = `Dernière mise à jour: ${formattedDate}`;
            } else {
                this.lastUpdatedElement.textContent = '';
            }
        }
    }
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        new NotificationSettings();
    });
</script>