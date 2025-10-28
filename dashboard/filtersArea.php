<?php
$user_id = getCurrentUserId($db);
// First, let's get all unique shipping statuses from orders
$shipping_statuses_query = "SELECT DISTINCT shipping_status FROM orders WHERE user_id = ? ORDER BY shipping_status";
$unique_shipping_statuses = $db->getThisQuery($shipping_statuses_query, [$user_id]);

// Get all stores for the current user
$stores_query = "SELECT id, storeName, platform FROM stores WHERE user_id = ? AND is_connected = 1 ORDER BY storeName";
$user_stores = $db->getThisQuery($stores_query, [$user_id]);

// Status labels mapping
$status_labels = [
    'new' => 'Nouveau colis',
    'pickup_pending' => 'En cours de ramassage',
    'collected' => 'Ramassé',
    'in_transit' => 'En transit',
    'arrived_at_agency' => "Arrivé à l'agence",
    'out_for_delivery' => 'En cours de livraison',
    'delivered' => 'Livrée',
    'refused' => 'Refusée',
    'unreachable' => 'Client injoignable',
    'rescheduled' => 'Reprogrammée',
    'returned_to_sender' => "Retour à l'expéditeur",
    'cancelled' => 'Annulée',
    'address_error' => "Erreur d'adresse",
    'warehouse_waiting' => 'En attente au dépôt',
    'delivery_failed' => 'Livraison échouée',
    'pending' => 'En attente',
    'processing' => 'En préparation',
    'shipped' => 'Expédiée',
    'not_submitted' => 'Non soumis'
];

// Confirmation status options
$confirmation_options = [
    'confirmed' => 'Confirmée',
    'no-answer' => 'Pas de réponse',
    'busy' => 'Occupé',
    'cancelled' => 'Annulée',
    'double-order' => 'Double commande',
    'unreachable' => 'Injoignable'
];
?>

<div class="orders-filters">
    <div class="filters-grid">
        <!-- Search Filter (Full width) -->
        <div class="filter-group search-group">
            <label for="search-filter">Rechercher:</label>
            <div class="search-container">
                <input type="text" id="search-filter" class="search-input" placeholder="Client, téléphone, ville, N° de commande...">
                <button class="search-btn2" id="search-btn2">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>

        <!-- Status Filters Row -->
        <div class="filters-row">
            <!-- Confirmation Status Filter -->
            <div class="filter-group">
                <label for="confirmation-filter">Statut de confirmation:</label>
                <select id="confirmation-filter" class="filter-select">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($confirmation_options as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>">
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Shipping Status Filter -->
            <div class="filter-group">
                <label for="shipping-filter">Statut d'expédition:</label>
                <select id="shipping-filter" class="filter-select">
                    <option value="">Tous les statuts</option>
                    <?php foreach ($unique_shipping_statuses as $status): ?>
                        <?php if (!empty($status)): ?>
                            <?php
                            $value = $status['shipping_status'] ?? ''; // use empty string if null
                            $value = is_array($value) ? '' : $value;   // avoid arrays just in case
                            ?>
                            <option value="<?php echo htmlspecialchars($value); ?>">
                                <?php echo htmlspecialchars($status_labels[$value] ?? $value); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Range Filter -->
            <div class="filter-group">
                <label for="date-range">Période:</label>
                <select id="date-range" class="filter-select">
                    <option value="today">Aujourd'hui</option>
                    <option value="yesterday">Hier</option>
                    <option value="week" selected>7 derniers jours</option>
                    <option value="month">30 derniers jours</option>
                    <option value="3months">3 derniers mois</option>
                    <option value="custom">Personnalisé</option>
                </select>
            </div>

            <!-- Custom Date Range (Hidden by default) -->
            <div class="filter-group custom-date-group" id="custom-date-group" style="display: none;">
                <label>Date personnalisée:</label>
                <div class="date-inputs">
                    <input type="date" id="start-date" class="filter-input" placeholder="Date de début">
                    <span class="date-separator">à</span>
                    <input type="date" id="end-date" class="filter-input" placeholder="Date de fin">
                </div>
            </div>

            <!-- Store Filter (only show if multiple stores exist) -->
            <?php if (count($user_stores) > 1): ?>
            <div class="filter-group">
                <label for="store-filter">Magasin:</label>
                <select id="store-filter" class="filter-select">
                    <option value="">Tous les magasins</option>
                    <?php foreach ($user_stores as $store): ?>
                        <option value="<?php echo htmlspecialchars($store['id']); ?>">
                            <?php echo htmlspecialchars($store['storeName']); ?>
                            <small>(<?php echo htmlspecialchars($store['platform']); ?>)</small>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="filters-actions">
        <button class="btn-filter-apply" id="apply-filters">
            <i class="fas fa-filter"></i> Appliquer les filtres
        </button>
        <button class="btn-filter-reset" id="reset-filters">
            <i class="fas fa-undo"></i> Réinitialiser
        </button>
        <button class="btn-export" id="export-filtered">
            <i class="fas fa-download"></i> Exporter
        </button>
    </div>
</div>

<style>
    .orders-filters {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .filters-grid {
        display: flex;
        width: 100%;
        gap: 20px;
    }

    .filters-row {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: flex-end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        flex: 1;
        min-width: 200px;
    }

    .filter-group.search-group {
        grid-column: 1 / -1;
    }

    .filter-group label {
        font-size: 13px;
        font-weight: 600;
        color: #4b5563;
        margin-bottom: 8px;
    }

    .filter-select, .filter-input {
        padding: 12px 16px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        transition: all 0.2s ease;
        width: 100%;
    }

    .filter-select:focus, .filter-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .search-container {
        display: flex;
        position: relative;
        width: 100%;
    }

    .search-input {
        padding: 12px 50px 12px 16px;
        border-radius: 8px;
        font-size: 15px;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
        width: 100%;
    }

    .search-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .search-btn2 {
        position: absolute;
        right: 0;
        top: 0;
        height: 100%;
        width: 50px;
        background: #9c80fd;
        color: white;
        border: none;
        border-radius: 0 8px 8px 0;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .search-btn2:hover {
        background: #5f34d9;
    }

    .custom-date-group .date-inputs {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .custom-date-group .date-separator {
        color: #6b7280;
        font-size: 14px;
    }

    .filters-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }

    .btn-filter-apply {
        background-color: #9c80fd !important;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-filter-apply:hover {
        background: #5f34d9 !important;
    }

    .btn-filter-reset, .btn-export {
        padding: 2px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-filter-reset {
        background: #f3f4f6;
        color: #374151;
    }

    .btn-filter-reset:hover {
        background: #e5e7eb;
    }

    .btn-export {
        background: #10b981;
        color: white;
    }

    .btn-export:hover {
        background: #059669;
    }

    /* Responsive styles */
    @media (max-width: 1200px) {
        .filter-group {
            min-width: 180px;
        }
    }

    @media (max-width: 992px) {
        .filters-row {
            gap: 15px;
        }
        
        .filter-group {
            min-width: 160px;
        }
    }

    @media (max-width: 768px) {
        .orders-filters {
            padding: 20px;
        }
        
        .filters-row {
            flex-direction: column;
            gap: 15px;
        }
        
        .filter-group {
            min-width: 100%;
        }
        
        .custom-date-group .date-inputs {
            flex-direction: column;
            align-items: stretch;
        }
        
        .custom-date-group .date-separator {
            display: none;
        }
        
        .filters-actions {
            flex-direction: column;
        }
        
        .btn-filter-apply, .btn-filter-reset, .btn-export {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .orders-filters {
            padding: 16px;
        }
        
        .filter-select, .filter-input, .search-input {
            padding: 14px 16px;
        }
        
        .search-btn2 {
            width: 60px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle date range selection
    const dateRangeSelect = document.getElementById('date-range');
    const customDateGroup = document.getElementById('custom-date-group');
    
    dateRangeSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customDateGroup.style.display = 'flex';
        } else {
            customDateGroup.style.display = 'none';
        }
    });
    
    // Apply filters when apply button is clicked
    document.getElementById('apply-filters').addEventListener('click', function() {
        applyFilters();
    });
    
    // Search only when search button is clicked
    document.getElementById('search-btn2').addEventListener('click', function() {
        searchOnly();
    });
    
    // Search when pressing Enter in search input
    document.getElementById('search-filter').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchOnly();
        }
    });
    
    // Reset filters
    document.getElementById('reset-filters').addEventListener('click', function() {
        resetFilters();
    });
    
    // Export filtered data
    document.getElementById('export-filtered').addEventListener('click', function() {
        exportFilteredData();
    });
    
    // Real-time search with debounce
    document.getElementById('search-filter').addEventListener('input', function() {
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(function() {
            searchOnly();
        }, 500);
    });
});

function applyFilters() {
    const filters = {
        confirmation_status: document.getElementById('confirmation-filter').value,
        shipping_status: document.getElementById('shipping-filter').value,
        date_range: document.getElementById('date-range').value,
        start_date: document.getElementById('start-date').value,
        end_date: document.getElementById('end-date').value,
        store_id: document.getElementById('store-filter') ? document.getElementById('store-filter').value : '',
        search: '' // Don't include search in filters
    };
    
    // Show loading state on apply button
    const applyBtn = document.getElementById('apply-filters');
    const originalText = applyBtn.innerHTML;
    applyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Application...';
    applyBtn.disabled = true;
    
    // AJAX request to filter orders
    fetch('./controllers/filter_orders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(filters)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the orders table
            document.getElementById('orders-tbody').innerHTML = data.html;
            
            // Update orders count
            updateOrdersCount(data.count);
        } else {
            console.error('Filter error:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    })
    .finally(() => {
        // Restore apply button
        applyBtn.innerHTML = originalText;
        applyBtn.disabled = false;
    });
}

function searchOnly() {
    const searchTerm = document.getElementById('search-filter').value;
    
    // Show loading state on search button
    const searchBtn = document.getElementById('search-btn2');
    const originalIcon = searchBtn.innerHTML;
    searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    const dateRange = document.getElementById('date-range').value;
    // Custom date range handling
    let startDate = '';
    let endDate = '';
    if (dateRange === 'custom') {
        startDate = document.getElementById('start-date').value;
        endDate = document.getElementById('end-date').value;
    }
    
    // AJAX request to search orders
    fetch('./controllers/filter_orders.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            search: searchTerm,
            // Reset other filters to default
            confirmation_status: '',
            shipping_status: '',
            date_range: dateRange,
            start_date: startDate,
            end_date: endDate,
            store_id: ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the orders table
            document.getElementById('orders-tbody').innerHTML = data.html;
            
            // Update orders count
            updateOrdersCount(data.count);
        } else {
            console.error('Search error:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    })
    .finally(() => {
        // Restore search button icon
        searchBtn.innerHTML = originalIcon;
    });
}

function resetFilters() {
    // Reset all filter inputs
    document.getElementById('confirmation-filter').value = '';
    document.getElementById('shipping-filter').value = '';
    document.getElementById('date-range').value = 'week';
    document.getElementById('start-date').value = '';
    document.getElementById('end-date').value = '';
    document.getElementById('search-filter').value = '';
    
    if (document.getElementById('store-filter')) {
        document.getElementById('store-filter').value = '';
    }
    
    // Hide custom date group
    document.getElementById('custom-date-group').style.display = 'none';
    
    // Apply filters to reload default data
    applyFilters();
}

function exportFilteredData() {
    const filters = {
        confirmation_status: document.getElementById('confirmation-filter').value,
        shipping_status: document.getElementById('shipping-filter').value,
        date_range: document.getElementById('date-range').value,
        start_date: document.getElementById('start-date').value,
        end_date: document.getElementById('end-date').value,
        store_id: document.getElementById('store-filter') ? document.getElementById('store-filter').value : '',
        search: document.getElementById('search-filter').value
    };

    const exportBtn = document.getElementById('export-filtered');
    const originalText = exportBtn.innerHTML;
    exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération Excel...';
    exportBtn.disabled = true;

    // Use fetch to check for errors first
    const formData = new FormData();
    for (const key in filters) {
        if (filters[key]) {
            formData.append(key, filters[key]);
        }
    }

    fetch('./controllers/export_orders.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Export failed');
        }
        // If successful, trigger download
        return response.blob();
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'Rapport_Commandes_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.xlsx';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
    })
    .catch(error => {
        console.error('Export error:', error);
        alert('Erreur lors de l\'exportation. Veuillez réessayer.');
    })
    .finally(() => {
        exportBtn.innerHTML = originalText;
        exportBtn.disabled = false;
    });
}

function updateOrdersCount(count) {
    const countElement = document.querySelector('.orders-count');
    if (countElement) {
        countElement.textContent = `${count} commande(s) trouvée(s)`;
    }
}
</script>