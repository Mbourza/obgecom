<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importer depuis Google Sheets</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .import-wizard {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .step {
            display: none;
        }
        
        .step.active {
            display: block;
        }
        
        .step-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .step-indicator {
            display: flex;
            gap: 10px;
        }
        
        .step-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #6c757d;
        }
        
        .step-dot.active {
            background: #0d6efd;
            color: white;
        }
        
        .step-dot.completed {
            background: #198754;
            color: white;
        }
        
        .column-mapping {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .mapping-field {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .mapping-field label {
            flex: 1;
            font-weight: 500;
        }
        
        .mapping-field select {
            flex: 2;
        }
        
        .preview-table {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        
        .import-results {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .error-list {
            max-height: 200px;
            overflow-y: auto;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .sample-data-table {
            font-size: 0.875rem;
        }
        
        .btn-nav {
            min-width: 100px;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0d6efd;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    
</head>
<body>
    <div class="container mt-4">
        <div class="import-wizard">
            <div class="text-center mb-4">
                <h2><i class="bi bi-file-earmark-spreadsheet"></i> Importer des commandes depuis Google Sheets</h2>
                <p class="text-muted">Suivez les étapes pour importer vos commandes depuis une feuille Google Sheets</p>
            </div>
            
            <div class="step-nav">
                <div class="step-indicator">
                    <div class="step-dot active" id="step-dot-1">1</div>
                    <div class="step-dot" id="step-dot-2">2</div>
                    <div class="step-dot" id="step-dot-3">3</div>
                    <div class="step-dot" id="step-dot-4">4</div>
                </div>
            </div>
            
            <!-- Step 1: Sheet URL -->
            <div class="step active" id="step-1">
                <div class="card">
                    <div class="card-header">
                        <h4>Étape 1: Configuration de la feuille</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Instructions:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Assurez-vous que votre feuille Google Sheets est publique ou partagée</li>
                                <li>La première ligne doit contenir les en-têtes des colonnes</li>
                                <li>Les données doivent commencer à partir de la deuxième ligne</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sheet-url" class="form-label">URL de la feuille Google Sheets *</label>
                            <input type="url" class="form-control" id="sheet-url" 
                                   placeholder="https://docs.google.com/spreadsheets/d/your-sheet-id/edit">
                            <div class="form-text">Collez l'URL complète de votre feuille Google Sheets</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="sheet-name" class="form-label">Nom de l'onglet</label>
                                <input type="text" class="form-control" id="sheet-name" value="Sheet1">
                            </div>
                            <div class="col-md-6">
                                <label for="start-row" class="form-label">Ligne de début des données</label>
                                <input type="number" class="form-control" id="start-row" value="2" min="2">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary btn-nav" onclick="validateSheet()">
                            Suivant <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Step 2: Column Mapping -->
            <div class="step" id="step-2">
                <div class="card">
                    <div class="card-header">
                        <h4>Étape 2: Correspondance des colonnes</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            Faites correspondre les colonnes de votre feuille avec les champs de commande. 
                            Les champs marqués d'un astérisque (*) sont obligatoires.
                        </div>
                        
                        <div id="column-mapping-container">
                            <!-- Column mapping will be generated here -->
                        </div>
                        
                        <div class="mt-4">
                            <h5>Aperçu des données</h5>
                            <div class="preview-table">
                                <table class="table table-sm sample-data-table" id="sample-data-table">
                                    <!-- Sample data will be shown here -->
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button class="btn btn-secondary btn-nav" onclick="previousStep()">
                            <i class="bi bi-arrow-left"></i> Précédent
                        </button>
                        <button class="btn btn-primary btn-nav" onclick="getPreview()">
                            Suivant <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Step 3: Preview -->
            <div class="step" id="step-3">
                <div class="card">
                    <div class="card-header">
                        <h4>Étape 3: Aperçu de l'importation</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i>
                            Vérifiez que les données sont correctement mappées avant de procéder à l'importation.
                        </div>
                        
                        <div id="preview-container">
                            <!-- Preview data will be shown here -->
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button class="btn btn-secondary btn-nav" onclick="previousStep()">
                            <i class="bi bi-arrow-left"></i> Précédent
                        </button>
                        <button class="btn btn-success btn-nav" onclick="startImport()">
                            <i class="bi bi-upload"></i> Importer
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Step 4: Results -->
            <div class="step" id="step-4">
                <div class="card">
                    <div class="card-header">
                        <h4>Étape 4: Résultats de l'importation</h4>
                    </div>
                    <div class="card-body">
                        <div id="import-results">
                            <!-- Import results will be shown here -->
                        </div>
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" onclick="window.location.href='orders.php'">
                            <i class="bi bi-list-ul"></i> Voir les commandes
                        </button>
                        <button class="btn btn-secondary ms-2" onclick="resetWizard()">
                            <i class="bi bi-arrow-repeat"></i> Nouvelle importation
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h5>Traitement en cours...</h5>
            <p id="loading-message">Veuillez patienter</p>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentStep = 1;
        let sheetHeaders = [];
        let suggestedMapping = {};
        
        // Field definitions with labels and requirements
        const fieldDefinitions = {
            'order_number': { label: 'Numéro de commande *', required: true },
            'customer_name': { label: 'Nom du client', required: false },
            'customer_email': { label: 'Email du client', required: false },
            'customer_phone': { label: 'Téléphone du client', required: false },
            'total_amount': { label: 'Montant total', required: false },
            'currency': { label: 'Devise', required: false },
            'status': { label: 'Statut', required: false },
            'payment_status': { label: 'Statut de paiement', required: false },
            'order_date': { label: 'Date de commande', required: false },
            'products': { label: 'Produits', required: false },
            'shipping_address': { label: 'Adresse de livraison', required: false },
            'billing_address': { label: 'Adresse de facturation', required: false },
            'notes': { label: 'Notes', required: false },
            'created_at': { label: 'Date de création', required: false },
            'updated_at': { label: 'Date de mise à jour', required: false }
        };
        
        function showLoading(message = 'Veuillez patienter') {
            document.getElementById('loading-message').textContent = message;
            document.getElementById('loading-overlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }
        
        function updateStepIndicator() {
            for (let i = 1; i <= 4; i++) {
                const dot = document.getElementById(`step-dot-${i}`);
                dot.classList.remove('active', 'completed');
                
                if (i < currentStep) {
                    dot.classList.add('completed');
                } else if (i === currentStep) {
                    dot.classList.add('active');
                }
            }
        }
        
        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
            
            // Show current step
            document.getElementById(`step-${step}`).classList.add('active');
            
            currentStep = step;
            updateStepIndicator();
        }
        
        function nextStep() {
            if (currentStep < 4) {
                showStep(currentStep + 1);
            }
        }
        
        function previousStep() {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        }
        
        function validateSheet() {
            const sheetUrl = document.getElementById('sheet-url').value;
            const sheetName = document.getElementById('sheet-name').value;
            const startRow = document.getElementById('start-row').value;
            
            if (!sheetUrl) {
                alert('Veuillez saisir l\'URL de la feuille Google Sheets');
                return;
            }
            
            if (!sheetUrl.includes('docs.google.com/spreadsheets')) {
                alert('L\'URL ne semble pas être une feuille Google Sheets valide');
                return;
            }
            
            showLoading('Chargement de la feuille...');
            
            // Extract sheet ID from URL
            const sheetId = extractSheetId(sheetUrl);
            if (!sheetId) {
                hideLoading();
                alert('Impossible d\'extraire l\'ID de la feuille depuis l\'URL');
                return;
            }
            
            // Simulate API call to get sheet data
            setTimeout(() => {
                loadSheetHeaders(sheetId, sheetName, startRow);
            }, 1500);
        }
        
        function extractSheetId(url) {
            const match = url.match(/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/);
            return match ? match[1] : null;
        }
        
        function loadSheetHeaders(sheetId, sheetName, startRow) {
            // Simulate loading headers from Google Sheets
            // In real implementation, this would make an API call
            
            // Mock headers for demonstration
            sheetHeaders = [
                'Numéro commande',
                'Nom client',
                'Email',
                'Téléphone',
                'Montant',
                'Date commande',
                'Statut',
                'Produits',
                'Adresse livraison'
            ];
            
            // Generate suggested mapping
            generateSuggestedMapping();
            
            // Create column mapping interface
            createColumnMapping();
            
            // Show sample data
            showSampleData();
            
            hideLoading();
            nextStep();
        }
        
        function generateSuggestedMapping() {
            suggestedMapping = {};
            
            const mappingRules = {
                'order_number': ['numéro', 'commande', 'order', 'number', 'ref'],
                'customer_name': ['nom', 'client', 'name', 'customer'],
                'customer_email': ['email', 'mail', 'e-mail'],
                'customer_phone': ['téléphone', 'phone', 'tel'],
                'total_amount': ['montant', 'total', 'amount', 'prix'],
                'order_date': ['date', 'commande', 'order'],
                'status': ['statut', 'status', 'état'],
                'products': ['produit', 'product', 'article'],
                'shipping_address': ['adresse', 'livraison', 'shipping', 'address']
            };
            
            Object.keys(mappingRules).forEach(field => {
                const keywords = mappingRules[field];
                const matchingHeader = sheetHeaders.find(header => 
                    keywords.some(keyword => 
                        header.toLowerCase().includes(keyword.toLowerCase())
                    )
                );
                
                if (matchingHeader) {
                    suggestedMapping[field] = matchingHeader;
                }
            });
        }
        
        function createColumnMapping() {
            const container = document.getElementById('column-mapping-container');
            container.innerHTML = '';
            
            const mappingDiv = document.createElement('div');
            mappingDiv.className = 'column-mapping';
            
            Object.keys(fieldDefinitions).forEach(field => {
                const fieldDef = fieldDefinitions[field];
                
                const mappingField = document.createElement('div');
                mappingField.className = 'mapping-field';
                
                const label = document.createElement('label');
                label.textContent = fieldDef.label;
                if (fieldDef.required) {
                    label.style.color = '#dc3545';
                }
                
                const select = document.createElement('select');
                select.className = 'form-select';
                select.id = `mapping-${field}`;
                
                // Add empty option
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = '-- Sélectionner --';
                select.appendChild(emptyOption);
                
                // Add header options
                sheetHeaders.forEach(header => {
                    const option = document.createElement('option');
                    option.value = header;
                    option.textContent = header;
                    
                    // Pre-select if suggested
                    if (suggestedMapping[field] === header) {
                        option.selected = true;
                    }
                    
                    select.appendChild(option);
                });
                
                mappingField.appendChild(label);
                mappingField.appendChild(select);
                mappingDiv.appendChild(mappingField);
            });
            
            container.appendChild(mappingDiv);
        }
        
        function showSampleData() {
            // Mock sample data
            const sampleData = [
                ['CMD001', 'Jean Dupont', 'jean@example.com', '0123456789', '159.99', '2024-01-15', 'En cours', 'Produit A, Produit B', '123 Rue de la Paix'],
                ['CMD002', 'Marie Martin', 'marie@example.com', '0987654321', '89.50', '2024-01-16', 'Livré', 'Produit C', '456 Avenue des Champs'],
                ['CMD003', 'Pierre Bernard', 'pierre@example.com', '0147852369', '245.00', '2024-01-17', 'En attente', 'Produit A, Produit D', '789 Boulevard Saint-Michel']
            ];
            
            const table = document.getElementById('sample-data-table');
            table.innerHTML = '';
            
            // Create header row
            const headerRow = document.createElement('tr');
            sheetHeaders.forEach(header => {
                const th = document.createElement('th');
                th.textContent = header;
                th.className = 'bg-light';
                headerRow.appendChild(th);
            });
            table.appendChild(headerRow);
            
            // Create data rows
            sampleData.forEach(row => {
                const tr = document.createElement('tr');
                row.forEach(cell => {
                    const td = document.createElement('td');
                    td.textContent = cell;
                    tr.appendChild(td);
                });
                table.appendChild(tr);
            });
        }
        
        function getPreview() {
            showLoading('Génération de l\'aperçu...');
            
            // Validate required fields
            const mapping = getCurrentMapping();
            const missingRequired = [];
            
            Object.keys(fieldDefinitions).forEach(field => {
                if (fieldDefinitions[field].required && !mapping[field]) {
                    missingRequired.push(fieldDefinitions[field].label);
                }
            });
            
            if (missingRequired.length > 0) {
                hideLoading();
                alert(`Les champs obligatoires suivants ne sont pas mappés:\n${missingRequired.join('\n')}`);
                return;
            }
            
            setTimeout(() => {
                generatePreview(mapping);
                hideLoading();
                nextStep();
            }, 1000);
        }
        
        function getCurrentMapping() {
            const mapping = {};
            Object.keys(fieldDefinitions).forEach(field => {
                const select = document.getElementById(`mapping-${field}`);
                if (select && select.value) {
                    mapping[field] = select.value;
                }
            });
            return mapping;
        }
        
        function generatePreview(mapping) {
            const container = document.getElementById('preview-container');
            
            // Mock preview data based on mapping
            const previewData = [

                { 'order_number': 'CMD001', 'customer_name': 'Jean Dupont', 'customer_email': 'jean@example.com', 'total_amount': '159.99' },
                { 'order_number': 'CMD002', 'customer_name': 'Marie Martin', 'customer_email': 'marie@example.com', 'total_amount': '89.50' },
                { 'order_number': 'CMD003', 'customer_name': 'Pierre Bernard', 'customer_email': 'pierre@example.com', 'total_amount': '245.00' }
            ];
            
            let html = `
                <div class="alert alert-info">
                    <strong>Aperçu de ${previewData.length} lignes à importer</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead class="table-dark">
                            <tr>
            `;
            
            // Add headers for mapped fields
            Object.keys(mapping).forEach(field => {
                html += `<th>${fieldDefinitions[field].label}</th>`;
            });
            
            html += `</tr></thead><tbody>`;
            
            // Add preview rows
            previewData.forEach(row => {
                html += '<tr>';
                Object.keys(mapping).forEach(field => {
                    html += `<td>${row[field] || '-'}</td>`;
                });
                html += '</tr>';
            });
            
            html += '</tbody></table></div>';
            
            container.innerHTML = html;
        }
        
        function startImport() {
            showLoading('Importation en cours...');
            
            // Simulate import process
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress >= 100) {
                    progress = 100;
                    clearInterval(interval);
                    
                    setTimeout(() => {
                        completeImport();
                    }, 500);
                }
                
                document.getElementById('loading-message').textContent = 
                    `Importation en cours... ${Math.round(progress)}%`;
            }, 300);
        }
        
        function completeImport() {
            hideLoading();
            
            // Mock import results
            const results = {
                total: 150,
                imported: 147,
                errors: 3,
                errorDetails: [
                    'Ligne 45: Email invalide pour la commande CMD045',
                    'Ligne 78: Montant manquant pour la commande CMD078',
                    'Ligne 132: Numéro de commande dupliqué CMD089'
                ]
            };
            
            showImportResults(results);
            nextStep();
        }
        
        function showImportResults(results) {
            const container = document.getElementById('import-results');
            
            let html = `
                <div class="row">
                    <div class="col-md-3">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h3 class="text-primary">${results.total}</h3>
                                <p class="card-text">Lignes traitées</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h3 class="text-success">${results.imported}</h3>
                                <p class="card-text">Importées avec succès</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-warning">
                            <div class="card-body">
                                <h3 class="text-warning">${results.errors}</h3>
                                <p class="card-text">Erreurs</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center border-info">
                            <div class="card-body">
                                <h3 class="text-info">${Math.round((results.imported / results.total) * 100)}%</h3>
                                <p class="card-text">Taux de succès</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (results.errors > 0) {
                html += `
                    <div class="mt-4">
                        <h5>Détail des erreurs</h5>
                        <div class="error-list">
                            <ul class="mb-0">
                `;
                
                results.errorDetails.forEach(error => {
                    html += `<li>${error}</li>`;
                });
                
                html += `
                            </ul>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        function resetWizard() {
            currentStep = 1;
            sheetHeaders = [];
            suggestedMapping = {};
            
            // Reset form
            document.getElementById('sheet-url').value = '';
            document.getElementById('sheet-name').value = 'Sheet1';
            document.getElementById('start-row').value = '2';
            
            // Clear containers
            document.getElementById('column-mapping-container').innerHTML = '';
            document.getElementById('sample-data-table').innerHTML = '';
            document.getElementById('preview-container').innerHTML = '';
            document.getElementById('import-results').innerHTML = '';
            
            showStep(1);
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateStepIndicator();
        });
    </script>
</body>
</html>