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
        
        .data-row:hover {
            background-color: #f8f9fa;
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
                                <li>Assurez-vous que votre feuille Google Sheets est publique ou partagée en lecture</li>
                                <li>La première ligne doit contenir les en-têtes des colonnes</li>
                                <li>Les données doivent commencer à partir de la deuxième ligne</li>
                                <li>Utilisez l'URL de publication : Fichier → Partager → Publier sur le web</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sheet-url" class="form-label">URL de la feuille Google Sheets *</label>
                            <input type="url" class="form-control" id="sheet-url" 
                                   placeholder="https://docs.google.com/spreadsheets/d/your-sheet-id/edit?usp=sharing">
                            <div class="form-text">Collez l'URL complète de votre feuille Google Sheets</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="sheet-name" class="form-label">Nom de l'onglet</label>
                                <input type="text" class="form-control" id="sheet-name" value="Sheet1">
                                <div class="form-text">Généralement "Sheet1", "Feuille 1", etc.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="start-row" class="form-label">Ligne de début des données</label>
                                <input type="number" class="form-control" id="start-row" value="2" min="2">
                                <div class="form-text">Généralement 2 (ligne 1 = en-têtes)</div>
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
                            <h5>Aperçu des données (premières 5 lignes)</h5>
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
                        <button class="btn btn-primary btn-nav" onclick="validateMapping()">
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
        let sheetData = [];
        let suggestedMapping = {};
        
        // Field definitions with labels and requirements
        const fieldDefinitions = {
            
            'order_number': { label: 'Numéro de commande *', required: true },
            'customer_name': { label: 'Nom du client *', required: true },
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
            'notes': { label: 'Notes', required: false }
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
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
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
            
            showLoading('Connexion à Google Sheets...');
            
            const sheetId = extractSheetId(sheetUrl);
            if (!sheetId) {
                hideLoading();
                alert('Impossible d\'extraire l\'ID de la feuille depuis l\'URL');
                return;
            }
            
            // Load real data from Google Sheets
            loadSheetData(sheetId, sheetName, parseInt(startRow));
        }
        
        function extractSheetId(url) {
            const match = url.match(/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/);
            return match ? match[1] : null;
        }
        
        async function loadSheetData(sheetId, sheetName, startRow) {
            try {
                // Convert Google Sheets to CSV format
                const csvUrl = `https://docs.google.com/spreadsheets/d/${sheetId}/gviz/tq?tqx=out:csv&sheet=${encodeURIComponent(sheetName)}`;
                
                const response = await fetch(csvUrl);
                if (!response.ok) {
                    throw new Error('Impossible d\'accéder à la feuille. Vérifiez que la feuille est publique.');
                }
                
                const csvText = await response.text();
                const rows = parseCSV(csvText);
                
                if (rows.length === 0) {
                    throw new Error('Aucune donnée trouvée dans la feuille');
                }
                
                // Extract headers (first row)
                sheetHeaders = rows[0];
                
                // Extract data starting from specified row
                sheetData = rows.slice(startRow - 1).filter(row => row.some(cell => cell.trim() !== ''));
                
                if (sheetData.length === 0) {
                    throw new Error('Aucune donnée trouvée à partir de la ligne spécifiée');
                }
                
                generateSuggestedMapping();
                createColumnMapping();
                showSampleData();
                
                hideLoading();
                nextStep();
                
            } catch (error) {
                hideLoading();
                alert('Erreur: ' + error.message);
                console.error('Error loading sheet:', error);
            }
        }
        
        function parseCSV(csvText) {
            const rows = [];
            let currentRow = [];
            let inQuotes = false;
            let currentCell = '';
            
            for (let i = 0; i < csvText.length; i++) {
                const char = csvText[i];
                const nextChar = csvText[i + 1];
                
                if (char === '"') {
                    if (inQuotes && nextChar === '"') {
                        currentCell += '"';
                        i++; // Skip next quote
                    } else {
                        inQuotes = !inQuotes;
                    }
                } else if (char === ',' && !inQuotes) {
                    currentRow.push(currentCell);
                    currentCell = '';
                } else if (char === '\n' && !inQuotes) {
                    currentRow.push(currentCell);
                    rows.push(currentRow);
                    currentRow = [];
                    currentCell = '';
                } else {
                    currentCell += char;
                }
            }
            
            // Add last row
            if (currentCell !== '' || currentRow.length > 0) {
                currentRow.push(currentCell);
                rows.push(currentRow);
            }
            
            return rows;
        }
        
        function generateSuggestedMapping() {
            suggestedMapping = {};
            
            const mappingRules = {
                'order_number': ['numéro', 'commande', 'order', 'number', 'ref', 'référence', 'id'],
                'customer_name': ['nom', 'client', 'name', 'customer', 'prénom', 'fullname'],
                'customer_email': ['email', 'mail', 'e-mail', 'courriel'],
                'customer_phone': ['téléphone', 'phone', 'tel', 'mobile', 'portable'],
                'total_amount': ['montant', 'total', 'amount', 'prix', 'price', 'cost'],
                'order_date': ['date', 'commande', 'order', 'created', 'creation'],
                'status': ['statut', 'status', 'état', 'state'],
                'products': ['produit', 'product', 'article', 'item', 'description'],
                'shipping_address': ['adresse', 'livraison', 'shipping', 'address', 'delivery']
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
                    label.innerHTML = fieldDef.label + ' <span class="text-danger">*</span>';
                }
                
                const select = document.createElement('select');
                select.className = 'form-select';
                select.id = `mapping-${field}`;
                
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = '-- Ignorer cette colonne --';
                select.appendChild(emptyOption);
                
                sheetHeaders.forEach(header => {
                    const option = document.createElement('option');
                    option.value = header;
                    option.textContent = header;
                    
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
            
            // Show first 5 rows of actual data
            const displayData = sheetData.slice(0, 5);
            displayData.forEach(row => {
                const tr = document.createElement('tr');
                row.forEach(cell => {
                    const td = document.createElement('td');
                    td.textContent = cell || '-';
                    td.style.maxWidth = '200px';
                    td.style.overflow = 'hidden';
                    td.style.textOverflow = 'ellipsis';
                    td.style.whiteSpace = 'nowrap';
                    tr.appendChild(td);
                });
                table.appendChild(tr);
            });
            
            if (sheetData.length > 5) {
                const infoRow = document.createElement('tr');
                const infoCell = document.createElement('td');
                infoCell.colSpan = sheetHeaders.length;
                infoCell.className = 'text-center text-muted';
                infoCell.textContent = `... et ${sheetData.length - 5} lignes supplémentaires`;
                infoRow.appendChild(infoCell);
                table.appendChild(infoRow);
            }
        }
        
        function validateMapping() {
            showLoading('Validation du mapping...');
            
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
            
            generatePreview(mapping);
            hideLoading();
            nextStep();
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
            const mappedFields = Object.keys(mapping).filter(field => mapping[field]);
            
            // Transform sheet data using mapping
            const previewData = sheetData.map(row => {
                const mappedRow = {};
                Object.keys(mapping).forEach(field => {
                    if (mapping[field]) {
                        const headerIndex = sheetHeaders.indexOf(mapping[field]);
                        mappedRow[field] = headerIndex !== -1 ? (row[headerIndex] || '') : '';
                    }
                });
                return mappedRow;
            });
            
            let html = `
                <div class="alert alert-info">
                    <strong>Aperçu de ${previewData.length} commandes à importer</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead class="table-dark">
                            <tr>
            `;
            
            // Add headers for mapped fields
            mappedFields.forEach(field => {
                html += `<th>${fieldDefinitions[field].label}</th>`;
            });
            
            html += `</tr></thead><tbody>`;
            
            // Add preview rows (show first 10)
            const displayData = previewData.slice(0, 10);
            displayData.forEach(row => {
                html += '<tr>';
                mappedFields.forEach(field => {
                    html += `<td>${row[field] || '-'}</td>`;
                });
                html += '</tr>';
            });
            
            if (previewData.length > 10) {
                html += `<tr><td colspan="${mappedFields.length}" class="text-center text-muted">... et ${previewData.length - 10} lignes supplémentaires</td></tr>`;
            }
            
            html += '</tbody></table></div>';
            
            container.innerHTML = html;
        }
        
        async function startImport() {
            showLoading('Importation en cours...');
            
            const mapping = getCurrentMapping();
            const transformedData = sheetData.map(row => {
                const mappedRow = {};
                Object.keys(mapping).forEach(field => {
                    if (mapping[field]) {
                        const headerIndex = sheetHeaders.indexOf(mapping[field]);
                        mappedRow[field] = headerIndex !== -1 ? (row[headerIndex] || '') : '';
                    }
                });
                return mappedRow;
            });
            
            try {
                const response = await fetch('./controllers/import_orders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        orders: transformedData,
                        mapping: mapping
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showImportResults(result.data);
                } else {
                    throw new Error(result.message || 'Erreur lors de l\'importation');
                }
                
            } catch (error) {
                hideLoading();
                alert('Erreur: ' + error.message);
                return;
            }
            
            hideLoading();
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
            
            if (results.errors > 0 && results.errorDetails) {
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
            sheetData = [];
            suggestedMapping = {};
            
            document.getElementById('sheet-url').value = '';
            document.getElementById('sheet-name').value = 'Sheet1';
            document.getElementById('start-row').value = '2';
            
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