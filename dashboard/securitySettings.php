<div class="settings-section" id="security">
    <div class="settings-card">
    <h3>Paramètres de sécurité</h3>
        <form id="security-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="two-factor-auth">Authentification à deux facteurs</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="two-factor-auth" name="two-factor-auth" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="form-group">
                    <label for="login-attempts">Tentatives de connexion max</label>
                    <input type="number" id="login-attempts" name="login-attempts" value="5" min="3" max="10">
                    <small>Nombre maximum de tentatives avant blocage</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="session-timeout">Timeout de session (minutes)</label>
                    <input type="number" id="session-timeout" name="session-timeout" value="60" min="15" max="480">
                </div>
                <div class="form-group">
                    <label for="password-policy">Politique de mot de passe stricte</label>
                    <label class="toggle-switch">
                        <input type="checkbox" id="password-policy" name="password-policy" checked>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="ip-whitelist">Liste blanche d'IP</label>
                <textarea id="ip-whitelist" name="ip-whitelist" rows="3" placeholder="192.168.1.1&#10;10.0.0.1&#10;203.0.113.1"></textarea>
                <small>Une IP par ligne. Laisser vide pour autoriser toutes les IP</small>
            </div>
            
            <div class="form-group">
                <label for="audit-log">Journal d'audit</label>
                <label class="toggle-switch">
                    <input type="checkbox" id="audit-log" name="audit-log" checked>
                    <span class="slider"></span>
                </label>
                <small>Enregistrer toutes les actions importantes</small>
            </div>
        </form>
    </div>
</div>