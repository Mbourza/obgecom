// Stock Report Variables and Functions
let reportChart = null;
let currentReportData = null;

// Initialize report event listeners
function initializeReportEventListeners() {
    // Report form submission
    $('#reportForm').off('submit').on('submit', function(e) {
        e.preventDefault();
        loadReportData();
    });
    
    // Export report button
    $('#exportReportBtn').off('click').on('click', exportReportToPDF);
    
    // Print report button
    $('#printReportBtn').off('click').on('click', printReport);
    
    // Reset report button
    $('#resetReportBtn').off('click').on('click', resetReportForm);
}

// Show stock report modal
function showStockReport() {
    const modal = new bootstrap.Modal(document.getElementById('stockReportModal'), {
        backdrop: 'static',
        keyboard: false,
        size: 'xl'
    });
    
    // Reset and initialize report
    resetReportForm();
    
    // Set default date range (last 30 days)
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    
    $('#reportStartDate').val(startDate.toISOString().split('T')[0]);
    $('#reportEndDate').val(endDate.toISOString().split('T')[0]);
    
    // Initialize event listeners for this modal
    initializeReportEventListeners();
    
    modal.show();
}

// Reset report form
function resetReportForm() {
    // Reset report form
    $('#reportForm')[0].reset();
    
    // Reset report sections
    $('#reportSummary').html(`
        <div class="text-center py-5">
            <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">Aucun rapport généré</h4>
            <p class="text-muted">Configurez les filtres et cliquez sur "Générer le rapport"</p>
        </div>
    `);
    
    $('#reportDetails').html(`
        <div class="text-center py-5">
            <i class="fas fa-table fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">Aucun détail disponible</h4>
            <p class="text-muted">Les détails apparaîtront ici après génération du rapport</p>
        </div>
    `);
    
    // Hide export button
    $('#exportReportBtn').hide();
}

// Load report data
async function loadReportData() {
    showLoading();
    
    try {
        // Get form data
        const startDate = $('#reportStartDate').val();
        const endDate = $('#reportEndDate').val();
        const category = $('#reportCategory').val();
        const stockStatus = $('#reportStockStatus').val();
        
        // Validate dates
        if (!startDate || !endDate) {
            throw new Error('Veuillez sélectionner une période');
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            throw new Error('La date de début doit être antérieure à la date de fin');
        }
        
        // Prepare request data
        const requestData = {
            start_date: startDate,
            end_date: endDate,
            category: category || null,
            stock_status: stockStatus || null,
            user_id: '<?php echo $user_id; ?>'
        };
        
        // Call API to get report data
        const response = await fetch('./controllers/generate_stock_report.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(requestData)
        });
        
        const data = await response.json();

        if (data.success) {
            currentReportData = data;
            
            // Display report data
            displayReportSummary(data.summary);
            displayReportDetails(data.details);
            
            // Show export button
            $('#exportReportBtn').show();
            
            showNotification('Rapport généré avec succès', 'success');
        } else {
            throw new Error(data.message || 'Erreur lors de la génération du rapport');
        }
        
    } catch (error) {
        console.error('Report error:', error);
        showNotification(error.message, 'error');
    } finally {
        hideLoading();
    }
}

// Display report summary
function displayReportSummary(summary) {
    const summaryHtml = `
        <div class="row">
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon bg-primary">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="summary-content">
                        <h4>${summary.total_products || 0}</h4>
                        <p>Produits</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon bg-success">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <div class="summary-content">
                        <h4>${summary.total_quantity || 0}</h4>
                        <p>Unités totales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon bg-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="summary-content">
                        <h4>${summary.low_stock || 0}</h4>
                        <p>Stock faible</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="summary-icon bg-info">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="summary-content">
                        <h4>${formatCurrency(summary.total_value || 0)}</h4>
                        <p>Valeur totale</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-chart-pie me-2"></i>
                            Répartition par statut
                        </h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>En stock</span>
                            <span class="fw-bold text-success">${summary.in_stock || 0}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Stock faible</span>
                            <span class="fw-bold text-warning">${summary.low_stock || 0}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>En rupture</span>
                            <span class="fw-bold text-danger">${summary.out_of_stock || 0}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-exchange-alt me-2"></i>
                            Mouvements
                        </h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Entrées</span>
                            <span class="fw-bold text-success">+${summary.total_incoming || 0}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Sorties</span>
                            <span class="fw-bold text-danger">-${summary.total_outgoing || 0}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Variation nette</span>
                            <span class="fw-bold ${(summary.net_change || 0) >= 0 ? 'text-success' : 'text-danger'}">
                                ${(summary.net_change || 0) >= 0 ? '+' : ''}${summary.net_change || 0}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#reportSummary').html(summaryHtml).addClass('fade-in');
}

// Display report details
function displayReportDetails(details) {
    if (!details || details.length === 0) {
        $('#reportDetails').html(`
            <div class="empty-state">
                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                <h4>Aucune donnée disponible</h4>
                <p class="text-muted">Aucune donnée trouvée pour les critères sélectionnés.</p>
            </div>
        `);
        return;
    }
    
    let detailsHtml = `
        <div class="table-responsive">
            <table class="table table-hover report-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>SKU</th>
                        <th>Catégorie</th>
                        <th>Stock initial</th>
                        <th>Entrées</th>
                        <th>Sorties</th>
                        <th>Stock final</th>
                        <th>Prix unitaire</th>
                        <th>Valeur totale</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    details.forEach(item => {
        const statusClass = getStockStatusClass(item.final_stock);
        const statusText = getStockStatusText(item.final_stock);
        
        detailsHtml += `
            <tr>
                <td>
                    <div class="fw-bold">${escapeHtml(item.product_name)}</div>
                    ${item.variant_name ? `<small class="text-muted">${escapeHtml(item.variant_name)}</small>` : ''}
                </td>
                <td><code>${escapeHtml(item.product_sku)}</code></td>
                <td>${escapeHtml(item.category || 'Non catégorisé')}</td>
                <td>${item.initial_stock || 0}</td>
                <td class="text-success">+${item.incoming_quantity || 0}</td>
                <td class="text-danger">-${item.outgoing_quantity || 0}</td>
                <td class="fw-bold">${item.final_stock || 0}</td>
                <td>${formatCurrency(item.unit_price || 0)}</td>
                <td class="fw-bold text-primary">${formatCurrency(item.total_value || 0)}</td>
                <td>
                    <span class="stock-status-badge ${statusClass}">${statusText}</span>
                </td>
            </tr>
        `;
    });
    
    detailsHtml += `
                </tbody>
            </table>
        </div>
        <div class="text-end mt-3">
            <small class="text-muted">
                ${details.length} produit(s) trouvé(s) | Valeur totale: ${formatCurrency(details.reduce((sum, item) => sum + (item.total_value || 0), 0))}
            </small>
        </div>
    `;
    
    $('#reportDetails').html(detailsHtml).addClass('fade-in');
}

// Export report to PDF
async function exportReportToPDF() {
    if (!currentReportData) {
        showNotification('Veuillez d\'abord générer un rapport', 'warning');
        return;
    }
    
    showLoading();
    
    try {
        const startDate = $('#reportStartDate').val();
        const endDate = $('#reportEndDate').val();
        
        // Prepare HTML for PDF
        const reportContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Rapport de Stock Détail</title>
                <style>
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        font-size: 12px;
                        color: #333;
                        margin: 0;
                        padding: 20px;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 30px;
                        border-bottom: 3px solid #4a5568;
                        padding-bottom: 20px;
                    }
                    .header h1 {
                        color: #2d3748;
                        margin: 0;
                        font-size: 24px;
                    }
                    .header .subtitle {
                        color: #718096;
                        font-size: 14px;
                        margin-top: 5px;
                    }
                    .company-info {
                        text-align: center;
                        margin-bottom: 20px;
                        color: #4a5568;
                        font-size: 11px;
                    }
                    .summary-grid {
                        display: grid;
                        grid-template-columns: repeat(4, 1fr);
                        gap: 15px;
                        margin: 25px 0;
                    }
                    .summary-item {
                        background: #f8fafc;
                        border: 1px solid #e2e8f0;
                        border-radius: 8px;
                        padding: 15px;
                        text-align: center;
                    }
                    .summary-value {
                        font-size: 20px;
                        font-weight: bold;
                        color: #4a5568;
                        margin: 5px 0;
                    }
                    .summary-label {
                        font-size: 11px;
                        color: #718096;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    }
                    .filters {
                        background: #edf2f7;
                        border: 1px solid #cbd5e0;
                        border-radius: 6px;
                        padding: 15px;
                        margin: 20px 0;
                        font-size: 11px;
                    }
                    .filters h3 {
                        color: #4a5568;
                        margin: 0 0 10px 0;
                        font-size: 14px;
                    }
                    .filter-item {
                        margin: 5px 0;
                    }
                    .filter-label {
                        font-weight: bold;
                        color: #4a5568;
                        min-width: 120px;
                        display: inline-block;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin: 25px 0;
                        font-size: 11px;
                    }
                    th {
                        background: #4a5568;
                        color: white;
                        padding: 12px 8px;
                        text-align: left;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                        border: 1px solid #2d3748;
                    }
                    td {
                        padding: 10px 8px;
                        border: 1px solid #e2e8f0;
                    }
                    tr:nth-child(even) {
                        background: #f8fafc;
                    }
                    .text-right {
                        text-align: right;
                    }
                    .text-center {
                        text-align: center;
                    }
                    .status-badge {
                        display: inline-block;
                        padding: 3px 8px;
                        border-radius: 12px;
                        font-size: 10px;
                        font-weight: 600;
                        text-transform: uppercase;
                    }
                    .in-stock { background: #c6f6d5; color: #22543d; }
                    .low-stock { background: #feebc8; color: #744210; }
                    .out-stock { background: #fed7d7; color: #742a2a; }
                    .footer {
                        margin-top: 40px;
                        padding-top: 20px;
                        border-top: 1px solid #e2e8f0;
                        text-align: center;
                        color: #718096;
                        font-size: 10px;
                    }
                    .page-break {
                        page-break-before: always;
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>Rapport de Stock Détail</h1>
                    <div class="subtitle">Analyse complète des mouvements de stock</div>
                </div>
                
                <div class="company-info">
                    Généré par: ${escapeHtml('<?php echo $userName; ?>')}<br>
                    Date de génération: ${new Date().toLocaleDateString('fr-FR', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })}
                </div>
                
                <div class="filters">
                    <h3>📋 Filtres appliqués</h3>
                    <div class="filter-item">
                        <span class="filter-label">Période:</span>
                        ${formatDate(startDate)} au ${formatDate(endDate)}
                    </div>
                    <div class="filter-item">
                        <span class="filter-label">Catégorie:</span>
                        ${$('#reportCategory').val() || 'Toutes'}
                    </div>
                    <div class="filter-item">
                        <span class="filter-label">Statut:</span>
                        ${$('#reportStockStatus').val() === 'all' ? 'Tous' : 
                          $('#reportStockStatus').val() === 'in_stock' ? 'En stock' :
                          $('#reportStockStatus').val() === 'low_stock' ? 'Stock faible' : 'En rupture'}
                    </div>
                </div>
                
                <h3>📊 Résumé du rapport</h3>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-value">${currentReportData.summary.total_products || 0}</div>
                        <div class="summary-label">Produits</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value">${currentReportData.summary.total_quantity || 0}</div>
                        <div class="summary-label">Unités totales</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value">${currentReportData.summary.low_stock || 0}</div>
                        <div class="summary-label">Stock faible</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value">${formatCurrency(currentReportData.summary.total_value || 0)}</div>
                        <div class="summary-label">Valeur totale</div>
                    </div>
                </div>
                
                <h3>📋 Détails des produits</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>SKU</th>
                            <th>Catégorie</th>
                            <th class="text-right">Stock initial</th>
                            <th class="text-right">Entrées</th>
                            <th class="text-right">Sorties</th>
                            <th class="text-right">Stock final</th>
                            <th class="text-right">Prix unitaire</th>
                            <th class="text-right">Valeur totale</th>
                            <th class="text-center">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        currentReportData.details.forEach(item => {
            const statusClass = getStockStatusClass(item.final_stock);
            const statusText = getStockStatusText(item.final_stock);
            
            reportContent += `
                <tr>
                    <td>${escapeHtml(item.product_name)}${item.variant_name ? `<br><small>${escapeHtml(item.variant_name)}</small>` : ''}</td>
                    <td>${escapeHtml(item.product_sku)}</td>
                    <td>${escapeHtml(item.category || 'N/A')}</td>
                    <td class="text-right">${item.initial_stock || 0}</td>
                    <td class="text-right" style="color: #10b981;">+${item.incoming_quantity || 0}</td>
                    <td class="text-right" style="color: #ef4444;">-${item.outgoing_quantity || 0}</td>
                    <td class="text-right"><strong>${item.final_stock || 0}</strong></td>
                    <td class="text-right">${formatCurrency(item.unit_price || 0)}</td>
                    <td class="text-right"><strong>${formatCurrency(item.total_value || 0)}</strong></td>
                    <td class="text-center">
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </td>
                </tr>
            `;
        });
        
        reportContent += `
                    </tbody>
                    <tfoot>
                        <tr style="background: #2d3748; color: white;">
                            <td colspan="8"><strong>TOTAL</strong></td>
                            <td class="text-right"><strong>${formatCurrency(currentReportData.summary.total_value || 0)}</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="footer">
                    Rapport généré par le système de gestion de stock<br>
                    Page 1 sur 1 • ${new Date().toLocaleDateString('fr-FR')}
                </div>
            </body>
            </html>
        `;
        
        // Open PDF in new window for printing
        const printWindow = window.open('', '_blank');
        printWindow.document.write(reportContent);
        printWindow.document.close();
        
        // Auto-print after a short delay
        setTimeout(() => {
            printWindow.print();
        }, 500);
        
        showNotification('PDF généré avec succès', 'success');
        
    } catch (error) {
        console.error('PDF export error:', error);
        showNotification('Erreur lors de la génération du PDF', 'error');
    } finally {
        hideLoading();
    }
}

// Print report
function printReport() {
    const printWindow = window.open('', '_blank');
    
    const reportContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Rapport de Stock</title>
            <style>
                /* Same styles as PDF export but adjusted for print */
                body { font-family: Arial, sans-serif; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; }
                @media print {
                    @page { margin: 0.5cm; }
                    body { margin: 0; }
                }
            </style>
        </head>
        <body>
            <h1>Rapport de Stock</h1>
            <!-- Include report data -->
        </body>
        </html>
    `;
    
    printWindow.document.write(reportContent);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => printWindow.print(), 500);
}

// Utility Functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: 'MAD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-FR');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getStockStatusClass(quantity) {
    if (quantity === 0) return 'out-stock';
    if (quantity <= 10) return 'low-stock';
    return 'in-stock';
}

function getStockStatusText(quantity) {
    if (quantity === 0) return 'Rupture';
    if (quantity <= 10) return 'Faible';
    return 'Normal';
}

// Initialize when document is ready
$(document).ready(function() {
    // Add keyboard shortcut for report (Ctrl/Cmd + R)
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            showStockReport();
        }
    });
});

// Expose functions to global scope
window.showStockReport = showStockReport;
window.resetReportForm = resetReportForm;
window.loadReportData = loadReportData;
window.exportReport = exportReportToPDF;
window.printReport = printReport;

// Add CSS for report styles if not already added
if (!$('#report-styles').length) {
    const reportStyles = document.createElement('style');
    reportStyles.id = 'report-styles';
    reportStyles.textContent = `
        /* Report Modal Styles */
        .report-modal .modal-content {
            border-radius: 0px !important;
            border: none;
            box-shadow: var(--shadow-xl);
        }
        
        .report-modal .modal-header {
            background: linear-gradient(135deg, #0ea5e9, #38bdf8);
            color: white;
            border-radius: 0px !important;
        }
        
        .report-modal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
            padding: 2rem;
        }
        
        /* Report Tabs */
        .report-modal .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }
        
        .report-modal .nav-tabs .nav-link {
            border: none;
            color: #64748b;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
            border-radius: 8px 8px 0 0;
        }
        
        .report-modal .nav-tabs .nav-link.active {
            color: #0ea5e9;
            border-bottom: 3px solid #0ea5e9;
            background: transparent;
        }
        
        .report-modal .nav-tabs .nav-link:hover {
            color: #0ea5e9;
            background: rgba(14, 165, 233, 0.05);
        }
        
        /* Report Filters */
        .report-filters {
            background: #f8fafc;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .report-filters .form-label {
            font-weight: 600;
            color: #475569;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        /* Summary Cards */
        .summary-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid #e2e8f0;
            transition: var(--transition-smooth);
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .summary-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .summary-content h4 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }
        
        .summary-content p {
            color: #64748b;
            margin: 0;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        /* Metric Items */
        .metric-item {
            text-align: center;
            padding: 1rem;
        }
        
        .metric-item small {
            font-size: 0.8rem;
            color: #64748b;
        }
        
        .metric-item h5 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-top: 0.25rem;
        }
        
        /* Chart Cards */
        .chart-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
            height: 100%;
        }
        
        .chart-card h6 {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }
        
        .chart-card .chart-container {
            height: 200px;
            position: relative;
        }
        
        /* Report Tables */
        .report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.9rem;
        }
        
        .report-table th {
            background: #f8fafc;
            padding: 0.875rem 1rem;
            font-weight: 700;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .report-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        
        .report-table tr:hover {
            background: #f8fafc;
        }
        
        /* Stock Status Badges */
        .stock-status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            display: inline-block;
        }
        
        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            color: #475569;
            margin-bottom: 0.5rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .report-modal .modal-body {
                padding: 1rem;
            }
            
            .summary-card {
                margin-bottom: 1rem;
            }
            
            .chart-card .chart-container {
                height: 150px;
            }
            
            .report-table {
                font-size: 0.8rem;
            }
            
            .report-table th,
            .report-table td {
                padding: 0.5rem;
            }
            
            .report-modal .nav-tabs .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }
        }
        
        /* Print Styles */
        @media print {
            .report-modal .modal-dialog {
                max-width: none;
                margin: 0;
            }
            
            .report-modal .modal-content {
                border: none;
                box-shadow: none;
            }
            
            .report-modal .modal-header,
            .report-modal .modal-footer,
            .report-filters,
            .nav-tabs {
                display: none !important;
            }
            
            .report-modal .modal-body {
                max-height: none;
                overflow: visible;
                padding: 0;
            }
            
            .tab-content > .tab-pane {
                display: block !important;
                opacity: 1 !important;
            }
        }
    `;
    document.head.appendChild(reportStyles);
}