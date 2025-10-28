
class TransporteursManager {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 10;
        this.currentFilters = {};
        this.carriers = [];
        this.filteredCarriers = [];
        this.totalCount = 0;
        this.loading = false;
        this.apiEndpoint = './controllers/get_carriersApi.php';
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadCarriers(); // Load initial data
    }

    bindEvents() {
        // Filter apply button
        document.querySelector('.btn-filter-apply')?.addEventListener('click', () => {
            this.applyFilters();
        });

        // Filter reset button
        document.querySelector('.btn-filter-reset')?.addEventListener('click', () => {
            this.resetFilters();
        });

        // Search input with debounce
        const searchInput = document.getElementById('search-carrier');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.currentFilters.search = e.target.value.trim();
                    this.applyFilters();
                }, 500);
            });
        }

        // Pagination events
        this.bindPaginationEvents();
    }

    bindPaginationEvents() {
        // Previous page
        document.querySelector('.pagination-prev')?.addEventListener('click', () => {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadCarriers();
            }
        });

        // Next page
        document.querySelector('.pagination-next')?.addEventListener('click', () => {
            const totalPages = Math.ceil(this.totalCount / this.itemsPerPage);
            if (this.currentPage < totalPages) {
                this.currentPage++;
                this.loadCarriers();
            }
        });
    }
            // Reset filters
    applyFilters() {
        // Get filter values
        const statusFilter = document.getElementById('status-filter')?.value || '';
        const trackingFilter = document.getElementById('tracking-filter')?.value || '';
        const searchTerm = document.getElementById('search-carrier')?.value?.trim() || '';

        // Update current filters - only add non-empty values
        this.currentFilters = {};

        if (statusFilter) {
            this.currentFilters.status = statusFilter;
        }
        
        if (searchTerm) {
            this.currentFilters.search = searchTerm;
        }

        // Convert tracking filter to supports_tracking parameter
        if (trackingFilter === 'yes') {
            this.currentFilters.supports_tracking = 1;
        } else if (trackingFilter === 'no') {
            this.currentFilters.supports_tracking = 0;
        }

        // Reset to first page and load filtered data
        this.currentPage = 1;
        this.loadCarriers();
    }

    resetFilters() {
        // Reset form elements
        const elements = [
            'status-filter',
            'tracking-filter',
            'search-carrier'
        ];

        elements.forEach(id => {
            const element = document.getElementById(id);
            if (element) element.value = '';
        });

        // Reset filters object and reload
        this.currentFilters = {};
        this.currentPage = 1;
        this.loadCarriers();
    }

    async loadCarriers() {
        if (this.loading) return;
        
        this.loading = true;
        this.showLoading();

        try {
            // Build query parameters
            const params = new URLSearchParams();
            
            // Add pagination
            params.append('limit', this.itemsPerPage.toString());
            params.append('offset', ((this.currentPage - 1) * this.itemsPerPage).toString());

            // Add filters
            Object.entries(this.currentFilters).forEach(([key, value]) => {

                if (value !== '' && value !== null && value !== undefined) {
                    params.append(key, value);
                }
            });

            const response = await fetch(`${this.apiEndpoint}?${params.toString()}`);

            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                this.carriers = data.data || [];
                this.totalCount = data.total_count || 0;
                renderCarriers(this.carriers);
                this.updatePaginationInfo();
            } else {
                this.showError(data.message || 'Erreur lors du chargement des transporteurs');
            }

        } catch (error) {
            console.error('Error loading carriers:', error);
            this.showError('Erreur de connexion. Veuillez réessayer.');
        } finally {
            this.loading = false;
            this.hideLoading();
        }
    }

    updatePaginationInfo() {
        const totalPages = Math.ceil(this.totalCount / this.itemsPerPage);
        const startItem = ((this.currentPage - 1) * this.itemsPerPage) + 1;
        const endItem = Math.min(this.currentPage * this.itemsPerPage, this.totalCount);

        // Update pagination info
        const paginationInfo = document.querySelector('.pagination-info');
        if (paginationInfo) {
            paginationInfo.textContent = `${startItem}-${endItem} sur ${this.totalCount} transporteurs`;
        }

        // Update pagination controls
        const prevBtn = document.querySelector('.pagination-prev');
        const nextBtn = document.querySelector('.pagination-next');
        const pageInfo = document.querySelector('.page-info');

        if (prevBtn) prevBtn.disabled = this.currentPage <= 1;
        if (nextBtn) nextBtn.disabled = this.currentPage >= totalPages;
        if (pageInfo) pageInfo.textContent = `Page ${this.currentPage} sur ${totalPages}`;

        // Update results count
        const resultsCount = document.querySelector('.results-count');
        if (resultsCount) {
            resultsCount.textContent = `${this.totalCount} transporteur${this.totalCount > 1 ? 's' : ''} trouvé${this.totalCount > 1 ? 's' : ''}`;
        }
    }

    showLoading() {
        const container = document.getElementById('carriers-list');
        if (container) {
            container.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Chargement des transporteurs...</p>
                </div>
            `;
        }

        // Disable filter buttons during loading
        document.querySelector('.btn-filter-apply')?.setAttribute('disabled', 'true');
        document.querySelector('.btn-filter-reset')?.setAttribute('disabled', 'true');
    }

    hideLoading() {
        // Re-enable filter buttons
        document.querySelector('.btn-filter-apply')?.removeAttribute('disabled');
        document.querySelector('.btn-filter-reset')?.removeAttribute('disabled');
    }

    showError(message) {
        const container = document.getElementById('carriers-list');
        if (container) {
            container.innerHTML = `
                <div class="error-message">
                    <p>❌ ${message}</p>
                    <button onclick="window.transporteursManager.loadCarriers()" class="btn-retry">Réessayer</button>
                </div>
            `;
        }
    }

    // Utility methods for external use
    refreshData() {
        this.loadCarriers();
    }

    getSelectedCarrier(id) {
        return this.carriers.find(carrier => carrier.id === parseInt(id));
    }

    getCurrentFilters() {
        return { ...this.currentFilters };
    }
}