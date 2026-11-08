class SettingsService {
    constructor() {
        this.baseUrl = './controllers/adSettingsApi.php';
    }

    // Récupérer tous les paramètres
    async getAllSettings() {
        try {
            const response = await fetch(this.baseUrl);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Erreur lors de la récupération des paramètres');
            }
            
            return result.data || {};
        } catch (error) {
            console.error('Erreur lors de la récupération des paramètres:', error);
            throw error;
        }
    }

    // Récupérer plusieurs paramètres spécifiques
    async getSettings(keys) {
        try {
            const keysString = Array.isArray(keys) ? keys.join(',') : keys;
            const response = await fetch(`${this.baseUrl}?keys=${encodeURIComponent(keysString)}`);
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Erreur lors de la récupération des paramètres');
            }
            
            return result.data || {};
        } catch (error) {
            console.error('Erreur lors de la récupération des paramètres:', error);
            throw error;
        }
    }

    // Sauvegarder les paramètres d'assignation
    async saveAssignmentSettings(settings) {
        try {
            console.log('Envoi des paramètres d\'assignation:', settings);
            
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    assignment_settings: settings
                })
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Erreur lors de la sauvegarde');
            }
            
            console.log('Sauvegarde réussie:', result);
            return {
                success: true,
                message: result.message,
                results: result.results
            };
        } catch (error) {
            console.error('Erreur lors de la sauvegarde des paramètres d\'assignation:', error);
            return {
                success: false,
                message: error.message
            };
        }
    }

    // Sauvegarder les préférences de traitement
    async saveProcessingPreferences(preferences) {
        try {
            console.log('Envoi des préférences de traitement:', preferences);
            
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    processing_preferences: preferences
                })
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Erreur lors de la sauvegarde');
            }
            
            console.log('Sauvegarde réussie:', result);
            return {
                success: true,
                message: result.message,
                results: result.results
            };
        } catch (error) {
            console.error('Erreur lors de la sauvegarde des préférences de traitement:', error);
            return {
                success: false,
                message: error.message
            };
        }
    }
}