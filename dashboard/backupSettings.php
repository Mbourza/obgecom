<div class="settings-section" id="backup">
    <div class="settings-card">
        <h3>Sauvegarde automatique</h3>
        <form id="backup-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="auto-backup">Sauvegarde automatique</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="auto-backup" name="auto-backup" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="form-group">
                    <label for="backup-frequency">Fréquence</label>
                    <select id="backup-frequency" name="backup-frequency">
                        <option value="daily" selected>Quotidienne</option>
                        <option value="weekly">Hebdomadaire</option>
                        <option value="monthly">Mensuelle</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="backup-time">Heure de sauvegarde</label>
                    <input type="time" id="backup-time" name="backup-time" value="02:00">
                </div>
                <div class="form-group">
                    <label for="backup-retention">Rétention (jours)</label>
                    <input type="number" id="backup-retention" name="backup-retention" value="30" min="7" max="365">
                </div>
            </div>
            
            <div class="form-group">
                <label for="backup-location">Emplacement de sauvegarde</label>
                <select id="backup-location" name="backup-location">
                    <option value="local" selected>Stockage local</option>
                    <option value="aws-s3">Amazon S3</option>
                    <option value="google-drive">Google Drive</option>
                    <option value="dropbox">Dropbox</option>
                </select>
            </div>
        </form>
    </div>
    
    <div class="settings-card">
        <h3>Sauvegardes récentes</h3>
        <div class="backup-item">
            <div>
                <strong>Sauvegarde complète</strong>
                <br><small>07/06/2024 - 02:00</small>
            </div>
            <div>
                <button class="btn-icon" title="Télécharger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 0a.5.5 0 0 1 .5.5v5.793l2.146-2.147a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 1 1 .708-.708L7.5 6.293V.5A.5.5 0 0 1 8 0z"/>
                        <path d="M3 15h10v-2a.5.5 0 0 1 1 0v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2a.5.5 0 0 1 1 0v2z"/>
                    </svg>
                </button>
                <button class="btn-icon" title="Restaurer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M1 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V4zM1 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V7zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V7zM1 10a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-1zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-1zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1v-1z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="backup-item">
            <div>
                <strong>Sauvegarde complète</strong>
                <br><small>06/06/2024 - 02:00</small>
            </div>
            <div>
                <button class="btn-icon" title="Télécharger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 0a.5.5 0 0 1 .5.5v5.793l2.146-2.147a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 1 1 .708-.708L7.5 6.293V.5A.5.5 0 0 1 8 0z"/>
                        <path d="M3 15h10v-2a.5.5 0 0 1 1 0v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2a.5.5 0 0 1 1 0v2z"/>
                    </svg>
                </button>
                <button class="btn-icon" title="Restaurer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M1 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V4zM1 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V7zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V7zM1 10a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-1zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-1zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1v-1z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="backup-item">
            <div>
                <strong>Sauvegarde complète</strong>
                <br><small>05/06/2024 - 02:00</small>
            </div>
            <div>
                <button class="btn-icon" title="Télécharger">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 0a.5.5 0 0 1 .5.5v5.793l2.146-2.147a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 1 1 .708-.708L7.5 6.293V.5A.5.5 0 0 1 8 0z"/>
                        <path d="M3 15h10v-2a.5.5 0 0 1 1 0v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2a.5.5 0 0 1 1 0v2z"/>
                    </svg>
                </button>
                <button class="btn-icon" title="Restaurer">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M1 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V4zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V4zM1 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V7zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V7zM1 10a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-1zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-1zm5 0a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1v-1z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>