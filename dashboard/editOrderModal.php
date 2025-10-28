<!-- Enhanced Edit Order Modal -->
<div id="edit-order-modal" class="modal">
    <div class="modal-content modal-large" style="border-radius: 0px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Modifier la commande #<span id="edit-order-number"></span></h3>
            <span class="close" onclick="closeEditOrderModal()">&times;</span>
        </div>
        
        <div class="modal-body" id="edit-modal-body">
            <div id="loading-container" style="display: none;">
                <div class="loading-spinner"></div>
                <p class="loading-text">Chargement des données...</p>
            </div>
            
            <div id="edit-form-container">
                <form id="edit-order-form">
                    <!-- Client Information Card -->
                    <div class="info-card">
                        <div class="card-header">
                            <h4><i class="fas fa-user-circle"></i> Informations Client</h4>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="edit-client-name">
                                    <i class="fas fa-user"></i> Nom du client *
                                </label>
                                <input type="text" id="edit-client-name" class="form-control" required>
                                <div class="field-validation"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit-client-phone">
                                    <i class="fas fa-phone"></i> Téléphone *
                                </label>
                                <input type="tel" id="edit-client-phone" class="form-control" required>
                                <div class="field-validation"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit-client-email">
                                    <i class="fas fa-envelope"></i> Email
                                </label>
                                <input type="email" id="edit-client-email" class="form-control">
                                <div class="field-validation"></div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="edit-client-address">
                                    <i class="fas fa-map-marker-alt"></i> Adresse de livraison *
                                </label>
                                <input type="text" id="edit-client-address" class="form-control" required>
                                <div class="field-validation"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit-client-city">
                                    <i class="fas fa-city"></i> Ville *
                                </label>
                                <input type="text" id="edit-client-city" class="form-control" required>
                                <div class="field-validation"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Details Card -->
                    <div class="info-card">
                        <div class="card-header">
                            <h4><i class="fas fa-clipboard-list"></i> Détails de la Commande</h4>
                        </div>
                        <div class="form-grid">

                            <div class="form-group">
                                <label for="edit-order-subtotal">
                                    <i class="fas fa-calculator"></i> Sous-total (MAD)
                                </label>
                                <input type="number" id="edit-order-subtotal" class="form-control money-input" step="0.01" min="0" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit-order-discount">
                                    <i class="fas fa-tag"></i> Remise (MAD)
                                </label>
                                <input type="number" id="edit-order-discount" class="form-control money-input" step="0.01" min="0" onchange="recalculateOrderTotal()">
                            </div>
                            
                            <div class="form-group">
                                <label for="edit-order-total">
                                    <i class="fas fa-money-bill-wave"></i> Total (MAD)
                                </label>
                                <input type="number" id="edit-order-total" class="form-control money-input total-field" step="0.01" min="0" readonly>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="edit-order-notes">
                                    <i class="fas fa-sticky-note"></i> Notes
                                </label>
                                <textarea id="edit-order-notes" class="form-control" rows="3" placeholder="Ajouter des notes ou commentaires..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Products Section -->
                    <div class="products-section">
                        <div class="products-header">
                            <h4><i class="fas fa-shopping-cart"></i> Produits de la Commande</h4>
                            <div class="products-summary" id="products-summary">
                                <span class="item-count">0 article(s)</span>
                            </div>
                        </div>
                        
                        <div class="products-table">
                            <div class="table-header-row">
                                <div class="col-product">Produit</div>
                                <div class="col-price">Prix Unitaire</div>
                                <div class="col-quantity">Quantité</div>
                                <div class="col-total">Total</div>
                            </div>
                            <div id="edit-products-body" class="table-body">
                                <!-- Products will be loaded here -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeEditOrderModal()">
                <i class="fas fa-times"></i> Annuler
            </button>
            <button class="btn btn-primary" onclick="saveEditedOrder()" id="save-btn">
                <i class="fas fa-save"></i> Enregistrer les modifications
            </button>
        </div>
    </div>
</div>

<style>

    .modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(10px);
        transition: opacity 0.3s ease;
    }

    .modal.show {
        display: flex;
        opacity: 1;
    }

    .modal-content {
        background: #ffffff;
        margin: 2% auto;
        padding: 0;
        border: none;
        width: 95%;
        max-width: 1200px;
        border-radius: 20px;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
        transform: scale(0.8);
        transition: transform 0.3s ease;
        max-height: 95vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .modal.show .modal-content {
        transform: scale(1);
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
    }

    .modal-header h3 {
        font-size: 24px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 15px;
        margin: 0;
    }

    .modal-header h3 i {
        font-size: 28px;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
    }

    .close {
        color: white;
        font-size: 32px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        background: none;
        border: none;
        padding: 5px;
        border-radius: 50%;
    }

    .close:hover {
        transform: rotate(90deg) scale(1.1);
        background: rgba(255, 255, 255, 0.1);
    }

    .modal-body {
        padding: 30px;
        overflow-y: auto;
        flex: 1;
        background: linear-gradient(145deg, #f8f9ff 0%, #ffffff 100%);
    }

    /* Loading Animation */
    #loading-container {
        text-align: center;
        padding: 60px;
    }

    .loading-spinner {
        width: 60px;
        height: 60px;
        border: 4px solid #e3e3e3;
        border-top: 4px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 20px;
    }

    .loading-text {
        font-size: 16px;
        color: #666;
        font-weight: 500;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Info Cards */
    .info-card {
        background: white;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .info-card:hover {
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
        transform: translateY(-2px);
    }

    .card-header {
        background: linear-gradient(135deg, #f8f9ff 0%, #e6e9ff 100%);
        padding: 20px 25px;
        border-bottom: 2px solid #e2e8f0;
    }

    .card-header h4 {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .card-header h4 i {
        color: #667eea;
        font-size: 20px;
    }

    /* Form Styles */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        padding: 25px;
    }

    .form-group {
        position: relative;
        margin-bottom: 0;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #4a5568;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-group label i {
        color: #667eea;
        width: 16px;
    }

    .form-control {
        width: 100%;
        padding: 15px 20px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 16px;
        transition: all 0.3s ease;
        background: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        font-family: inherit;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        transform: translateY(-1px);
    }

    .form-control:invalid {
        border-color: #e53e3e;
    }

    .form-control.is-valid {
        border-color: #38a169;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2338a169' d='m2.3 6.73.8-.77-.8-.77-.8.77.8.77.8.77.8-.77-.8-.77-.8-.77-.8.77.8.77z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 16px;
    }

    .money-input {
        position: relative;
    }

    .money-input::after {
        content: "MAD";
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #666;
        font-size: 14px;
        pointer-events: none;
    }

    .total-field {
        background: linear-gradient(135deg, #f0fff4 0%, #e6fffa 100%);
        font-weight: 700;
        color: #2d7738;
        font-size: 18px;
    }

    select.form-control {
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'%3e%3cpath fill='%23667eea' d='M2 0L0 2h4zm0 5L0 3h4z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 15px center;
        background-size: 12px;
        padding-right: 40px;
    }

    textarea.form-control {
        resize: vertical;
        min-height: 100px;
        font-family: inherit;
    }

    /* Field Validation */
    .field-validation {
        font-size: 12px;
        margin-top: 5px;
        min-height: 16px;
        color: #e53e3e;
        font-weight: 500;
    }

    .field-validation.success {
        color: #38a169;
    }

    /* Status Preview */
    .status-preview {
        margin-top: 10px;
    }

    .status-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
    }

    .status-pending { background: linear-gradient(135deg, #fef3cd, #fde68a); color: #92400e; }
    .status-processing { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; }
    .status-confirmed { background: linear-gradient(135deg, #d1fae5, #a7f3d0); color: #065f46; }
    .status-shipped { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); color: #3730a3; }
    .status-delivered { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #14532d; }
    .status-cancelled { background: linear-gradient(135deg, #fecaca, #fca5a5); color: #991b1b; }

    /* Products Section */
    .products-section {
        background: white;
        border-radius: 16px;
        padding: 0;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .products-header {
        background: linear-gradient(135deg, #f8f9ff 0%, #e6e9ff 100%);
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #e2e8f0;
    }

    .products-header h4 {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .products-header h4 i {
        color: #667eea;
        font-size: 20px;
    }

    .products-summary {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .item-count {
        background: #667eea;
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .products-table {
        border-radius: 0;
        overflow: hidden;
    }

    .table-header-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 20px;
        padding: 20px 25px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 700;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table-body {
        min-height: 100px;
        max-height: 400px;
        overflow-y: auto;
    }

    .product-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 20px;
        padding: 20px 25px;
        border-bottom: 1px solid #f1f3f4;
        align-items: center;
        transition: all 0.3s ease;
        background: white;
    }

    .product-row:hover {
        background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
        transform: translateX(5px);
        border-left: 4px solid #667eea;
    }

    .product-row:last-child {
        border-bottom: none;
    }

    .product-info2 {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .product-details {
        flex: 1;
    }

    .product-name {
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 4px;
        font-size: 16px;
    }

    .product-sku {
        font-size: 12px;
        color: #718096;
        background: #f7fafc;
        padding: 2px 8px;
        border-radius: 12px;
        display: inline-block;
    }

    .quantity-input, .price-input {
        width: 80px;
        padding: 10px 12px;
        text-align: center;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .quantity-input:focus, .price-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        transform: scale(1.05);
    }

    .product-total {
        font-weight: 700;
        color: #2d7738;
        font-size: 16px;
        text-align: right;
    }

    /* Modal Footer */
    .modal-footer {
        padding: 25px 30px;
        background: #f8f9ff;
        border-top: 2px solid #e2e8f0;
        display: flex;
        justify-content: flex-end;
        gap: 15px;
    }

    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 120px;
        justify-content: center;
    }

    .btn-secondary {
        background: #e2e8f0;
        color: #4a5568;
    }

    .btn-secondary:hover {
        background: #cbd5e0;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .modal-content {
            width: 98%;
            margin: 1% auto;
            border-radius: 16px;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
            gap: 20px;
            padding: 20px;
        }
        
        .table-header-row, .product-row {
            grid-template-columns: 1.5fr 0.8fr 0.8fr 0.9fr;
            gap: 10px;
            padding: 15px;
        }
        
        .product-info2 {
            gap: 10px;
        }
        
        
        .quantity-input, .price-input {
            width: 60px;
            font-size: 12px;
        }
        
        .modal-footer {
            padding: 20px;
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
    }

    /* Animation Classes */
    .fade-in {
        animation: fadeIn 0.3s ease;
    }

    .slide-up {
        animation: slideUp 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    /* Success animations */
    .success-pulse {
        animation: successPulse 0.6s ease;
    }

    @keyframes successPulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); background: #f0fff4; }
        100% { transform: scale(1); }
    }
</style>

<script>

    // Enhanced Edit Order Data Function
    function editOrderData(orderId) {
        currentOrderId = orderId;
        
        // Show modal with animation
        const modal = document.getElementById('edit-order-modal');
        const loadingContainer = document.getElementById('loading-container');
        const formContainer = document.getElementById('edit-form-container');
        
        modal.style.display = 'block';
        modal.classList.add('show');
        loadingContainer.style.display = 'block';
        formContainer.style.display = 'none';
        
        // Add animation classes
        modal.classList.add('fade-in');
        
        // Fetch order data
        fetch(`./controllers/get_order_detailsApi.php?order_id=${orderId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide loading with animation
                    setTimeout(() => {
                        loadingContainer.style.display = 'none';
                        formContainer.style.display = 'block';
                        formContainer.classList.add('slide-up');
                        
                        // Populate form with data
                        populateEditForm(data.order);
                    }, 800); // Small delay for better UX
                } else {
                    showNotification(data.message || 'Erreur lors du chargement', 'error');
                    closeEditOrderModal();
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
                closeEditOrderModal();
                console.error('Error:', error);
            });
    }

    function populateEditForm(order) {
        // Enhanced form population with validation
        const setFieldValue = (id, value, validate = false) => {
            const element = document.getElementById(id);
            if (element) {
                element.value = value || '';
                if (validate && value) {
                    element.classList.add('is-valid');
                    const validation = element.parentNode.querySelector('.field-validation');
                    if (validation) {
                        validation.textContent = '✓ Valide';
                        validation.classList.add('success');
                    }
                }
            }
        };

        // Populate client information
        setFieldValue('edit-client-name', order.customer_name, true);
        setFieldValue('edit-client-phone', order.customer_phone, true);
        setFieldValue('edit-client-email', order.customer_email);
        setFieldValue('edit-client-address', order.shipping_address, true);
        setFieldValue('edit-client-city', order.shipping_city, true);
        
        // Populate order details
        setFieldValue('edit-order-discount', parseFloat(order.discount_amount || 0).toFixed(2));
        setFieldValue('edit-order-total', parseFloat(order.total_amount || 0).toFixed(2));
        setFieldValue('edit-order-notes', order.notes);
        
        // Update status badge
        updateStatusBadge(order.status);
        
        // Set order number in header with animation
        const orderNumberSpan = document.getElementById('edit-order-number');
        if (orderNumberSpan) {
            orderNumberSpan.textContent = order.order_number || '';
            orderNumberSpan.classList.add('success-pulse');
        }
        
        // Populate products with enhanced display
        const productsBody = document.getElementById('edit-products-body');
        if (productsBody && order.items) {
            console.log(order.items)
            productsBody.innerHTML = order.items.map((item, index) => `
                <div class="product-row fade-in" data-product-id="${item.product_id}" style="animation-delay: ${index * 0.1}s">
                    <div class="product-info2">
                        <div class="product-details">
                            <div class="product-name">${item.name || 'Produit sans nom'}</div>
                            <div class="product-sku">SKU: ${item.sku || 'N/A'}</div>
                        </div>
                    </div>
                    <div>
                        <input type="number" 
                            class="price-input" 
                            value="${parseFloat(item.price || 0).toFixed(2)}" 
                            step="0.01" 
                            min="0" 
                            title="Prix unitaire"
                            onchange="updateProductPrice(this, ${item.product_id})"
                            onfocus="this.select()">
                    </div>
                    <div>
                        <input type="number" 
                            class="quantity-input" 
                            value="${item.quantity || 1}" 
                            min="1" 
                            title="Quantité"
                            onchange="updateProductQuantity(this, ${item.product_id})"
                            onfocus="this.select()">
                    </div>
                    <div class="product-total">
                        ${(parseFloat(item.price || 0) * parseFloat(item.quantity || 1)).toFixed(2)} MAD
                    </div>
                </div>
            `).join('');
            
            // Update products summary
            updateProductsSummary(order.items.length);
        }
        
        // Calculate initial totals
        recalculateOrderTotal();
        
        // Add input event listeners for real-time validation
        addFormValidation();
    }

    function updateStatusBadge(status) {
        const statusPreview = document.getElementById('status-preview');
        const statusLabels = {
            'pending': 'En attente',
            'processing': 'En préparation',
            'confirmed': 'Confirmée',
            'shipped': 'Expédiée',
            'delivered': 'Livrée',
            'cancelled': 'Annulée'
        };
        
        if (statusPreview) {
            statusPreview.innerHTML = `<div class="status-badge status-${status}">${statusLabels[status] || status}</div>`;
        }
    }

    function updateProductsSummary(itemCount) {
        const summaryElement = document.getElementById('products-summary');
        if (summaryElement) {
            const countElement = summaryElement.querySelector('.item-count');
            if (countElement) {
                countElement.textContent = `${itemCount} article${itemCount > 1 ? 's' : ''}`;
            }
        }
    }

    function addFormValidation() {
        const requiredFields = ['edit-client-name', 'edit-client-phone', 'edit-client-address', 'edit-client-city'];
        
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', function() {
                    validateField(this);
                });
                
                field.addEventListener('blur', function() {
                    validateField(this);
                });
            }
        });
        
        // Email validation
        const emailField = document.getElementById('edit-client-email');
        if (emailField) {
            emailField.addEventListener('input', function() {
                validateEmailField(this);
            });
        }
    }

    function validateField(field) {
        const validation = field.parentNode.querySelector('.field-validation');
        const value = field.value.trim();
        
        if (field.hasAttribute('required')) {
            if (value === '') {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
                if (validation) {
                    validation.textContent = 'Ce champ est obligatoire';
                    validation.classList.remove('success');
                }
                return false;
            }
        }
        
        // Phone validation
        if (field.type === 'tel' && value !== '') {
            const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
            if (!phoneRegex.test(value)) {
                field.classList.remove('is-valid');
                field.classList.add('is-invalid');
                if (validation) {
                    validation.textContent = 'Format de téléphone invalide';
                    validation.classList.remove('success');
                }
                return false;
            }
        }
        
        // Success state
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        if (validation) {
            validation.textContent = '✓ Valide';
            validation.classList.add('success');
        }
        return true;
    }

    function validateEmailField(field) {
        const validation = field.parentNode.querySelector('.field-validation');
        const value = field.value.trim();
        
        if (value === '') {
            field.classList.remove('is-valid', 'is-invalid');
            if (validation) {
                validation.textContent = '';
                validation.classList.remove('success');
            }
            return true;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            if (validation) {
                validation.textContent = 'Format d\'email invalide';
                validation.classList.remove('success');
            }
            return false;
        }
        
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        if (validation) {
            validation.textContent = '✓ Email valide';
            validation.classList.add('success');
        }
        return true;
    }

    function updateProductPrice(input, productId) {
        const row = input.closest('.product-row');
        const quantityInput = row.querySelector('.quantity-input');
        const totalDiv = row.querySelector('.product-total');
        
        // Add visual feedback
        input.classList.add('success-pulse');
        setTimeout(() => input.classList.remove('success-pulse'), 600);
        
        if (quantityInput && totalDiv) {
            const quantity = parseFloat(quantityInput.value) || 0;
            const price = parseFloat(input.value) || 0;
            const total = (price * quantity).toFixed(2);
            
            // Animate total change
            totalDiv.style.transform = 'scale(1.1)';
            totalDiv.style.color = '#667eea';
            totalDiv.textContent = total + ' MAD';
            
            setTimeout(() => {
                totalDiv.style.transform = 'scale(1)';
                totalDiv.style.color = '#2d7738';
            }, 300);
            
            recalculateOrderTotal();
        }
    }

    function updateProductQuantity(input, productId) {
        const row = input.closest('.product-row');
        const priceInput = row.querySelector('.price-input');
        const totalDiv = row.querySelector('.product-total');
        
        // Add visual feedback
        input.classList.add('success-pulse');
        setTimeout(() => input.classList.remove('success-pulse'), 600);
        
        if (priceInput && totalDiv) {
            const price = parseFloat(priceInput.value) || 0;
            const quantity = parseFloat(input.value) || 0;
            const total = (price * quantity).toFixed(2);
            
            // Animate total change
            totalDiv.style.transform = 'scale(1.1)';
            totalDiv.style.color = '#667eea';
            totalDiv.textContent = total + ' MAD';
            
            setTimeout(() => {
                totalDiv.style.transform = 'scale(1)';
                totalDiv.style.color = '#2d7738';
            }, 300);
            
            recalculateOrderTotal();
        }
    }

    function recalculateOrderTotal() {
        let subtotal = 0;
        let itemCount = 0;
        
        document.querySelectorAll('.product-row').forEach(row => {
            const priceInput = row.querySelector('.price-input');
            const quantityInput = row.querySelector('.quantity-input');
            
            if (priceInput && quantityInput) {
                const price = parseFloat(priceInput.value) || 0;
                const quantity = parseFloat(quantityInput.value) || 0;
                subtotal += price * quantity;
                itemCount++;
            }
        });
        
        const discountInput = document.getElementById('edit-order-discount');
        const subtotalInput = document.getElementById('edit-order-subtotal');
        const totalInput = document.getElementById('edit-order-total');
        
        const discount = parseFloat(discountInput ? discountInput.value : 0) || 0;
        const total = Math.max(0, subtotal - discount);
        
        // Animate changes
        if (subtotalInput) {
            subtotalInput.classList.add('success-pulse');
            subtotalInput.value = subtotal.toFixed(2);
            setTimeout(() => subtotalInput.classList.remove('success-pulse'), 600);
        }
        
        if (totalInput) {
            totalInput.classList.add('success-pulse');
            totalInput.value = total.toFixed(2);
            setTimeout(() => totalInput.classList.remove('success-pulse'), 600);
        }
        
        // Update products summary
        updateProductsSummary(itemCount);
    }

    function closeEditOrderModal() {
        const modal = document.getElementById('edit-order-modal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                modal.classList.remove('fade-in');
                // Reset form
                const form = document.getElementById('edit-order-form');
                if (form) {
                    form.reset();
                    // Remove validation classes
                    form.querySelectorAll('.form-control').forEach(input => {
                        input.classList.remove('is-valid', 'is-invalid');
                    });
                    form.querySelectorAll('.field-validation').forEach(validation => {
                        validation.textContent = '';
                        validation.classList.remove('success');
                    });
                }
            }, 300);
        }
        currentOrderId = null;
    }

    function saveEditedOrder() {
        if (!currentOrderId) {
            showNotification('Aucune commande sélectionnée', 'error');
            return;
        }
        
        // Validate form before saving
        const isValid = validateAllFields();
        if (!isValid) {
            showNotification('Veuillez corriger les erreurs dans le formulaire', 'error');
            return;
        }
        
        // Collect form data
        const formData = {
            order_id: currentOrderId,
            customer_name: document.getElementById('edit-client-name').value.trim(),
            customer_phone: document.getElementById('edit-client-phone').value.trim(),
            customer_email: document.getElementById('edit-client-email').value.trim(),
            shipping_address: document.getElementById('edit-client-address').value.trim(),
            shipping_city: document.getElementById('edit-client-city').value.trim(),
            subtotal: document.getElementById('edit-order-subtotal').value,
            discount_amount: document.getElementById('edit-order-discount').value,
            total_amount: document.getElementById('edit-order-total').value,
            notes: document.getElementById('edit-order-notes').value.trim(),
            items: []
        };
        
        // Collect product changes
        document.querySelectorAll('.product-row').forEach(row => {
            const productId = row.getAttribute('data-product-id');
            const priceInput = row.querySelector('.price-input');
            const quantityInput = row.querySelector('.quantity-input');
            
            if (productId && priceInput && quantityInput) {
                formData.items.push({
                    product_id: productId,
                    price: priceInput.value,
                    quantity: quantityInput.value
                });
            }
        });
        
        // Update save button state
        const saveBtn = document.getElementById('save-btn');
        if (!saveBtn) return;
        
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.7';
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
        
        // Send update request
        fetch('./controllers/update_orderApi.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Success animation
                saveBtn.innerHTML = '<i class="fas fa-check"></i> Enregistré!';
                saveBtn.style.background = 'linear-gradient(135deg, #38a169 0%, #2d7738 100%)';
                
                setTimeout(() => {
                    showNotification('Commande mise à jour avec succès', 'success');
                    closeEditOrderModal();
                    
                    // Refresh orders table if function exists
                    if (typeof loadOrders === 'function') {
                        loadOrders();
                    }
                }, 1000);
            } else {
                showNotification(data.message || 'Erreur lors de la mise à jour', 'error');
            }
        })
        .catch(error => {
            showNotification('Erreur de connexion', 'error');
            console.error('Error:', error);
        })
        .finally(() => {
            setTimeout(() => {
                saveBtn.disabled = false;
                saveBtn.style.opacity = '1';
                saveBtn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                saveBtn.innerHTML = originalText;
            }, 2000);
        });
    }

    function validateAllFields() {
        let isValid = true;
        
        // Validate required fields
        const requiredFields = [
            { id: 'edit-client-name', name: 'Nom du client' },
            { id: 'edit-client-phone', name: 'Téléphone' },
            { id: 'edit-client-address', name: 'Adresse' },
            { id: 'edit-client-city', name: 'Ville' }
        ];
        
        requiredFields.forEach(field => {
            const element = document.getElementById(field.id);
            if (!validateField(element)) {
                isValid = false;
            }
        });
        
        // Validate email if provided
        const emailField = document.getElementById('edit-client-email');
        if (emailField && !validateEmailField(emailField)) {
            isValid = false;
        }
        
        return isValid;
    }

    // Close modal on outside click
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('edit-order-modal');
        if (event.target === modal) {
            closeEditOrderModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const modal = document.getElementById('edit-order-modal');
            if (modal && modal.classList.contains('show')) {
                closeEditOrderModal();
            }
        }
    });
</script>