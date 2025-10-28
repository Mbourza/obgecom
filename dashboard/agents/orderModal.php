<!-- Create Order Modal -->
<div id="create-order-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <div class="header-content">
                <h3><i class="fas fa-plus-circle"></i> Créer une nouvelle commande</h3>
                <div class="progress-indicator">
                    <div class="step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-label">Client</span>
                    </div>
                    <div class="step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-label">Produits</span>
                    </div>
                    <div class="step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-label">Finaliser</span>
                    </div>
                </div>
            </div>
            <button class="close" onclick="closeCreateOrderModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <form id="create-order-form">
                <!-- Step 1: Client Information -->
                <div class="form-step active" id="step-1">
                    <div class="step-header">
                        <h4><i class="fas fa-user"></i> Informations client</h4>
                        <p>Recherchez un client existant ou créez-en un nouveau</p>
                    </div>
                    
                    <div class="client-search-section">
                        <div class="search-toggle">
                            <button type="button" class="toggle-btn active" id="existing-client-btn" onclick="toggleClientMode('existing')">
                                <i class="fas fa-search"></i> Client existant
                            </button>
                            <button type="button" class="toggle-btn" id="new-client-btn" onclick="toggleClientMode('new')">
                                <i class="fas fa-user-plus"></i> Nouveau client
                            </button>
                        </div>
                        
                        <div id="existing-client-section" class="client-section active">
                            <div class="search-input-group">
                                <div class="input-with-icon">
                                    <i class="fas fa-search input-icon"></i>
                                    <input type="text" id="client-search" class="form-control" placeholder="Nom, email ou téléphone du client" autocomplete="off">
                                </div>
                                <button type="button" class="btn btn-primary" onclick="searchClients()">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <div id="client-results" class="search-results"></div>
                        </div>
                        
                        <div id="new-client-section" class="client-section">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="client-name">
                                        <i class="fas fa-user"></i> Nom complet *
                                    </label>
                                    <input type="text" id="client-name" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="client-phone">
                                        <i class="fas fa-phone"></i> Téléphone *
                                    </label>
                                    <input type="tel" id="client-phone" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="client-email">
                                        <i class="fas fa-envelope"></i> Email
                                    </label>
                                    <input type="email" id="client-email" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="client-address">
                                        <i class="fas fa-map-marker-alt"></i> Adresse
                                    </label>
                                    <input type="text" id="client-address" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="client-city">
                                        <i class="fas fa-city"></i> Ville
                                    </label>
                                    <input type="text" id="client-city" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="selected-client-card" class="client-card" style="display: none;">
                        <div class="client-info">
                            <div class="client-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="client-details">
                                <h5 id="selected-client-name"></h5>
                                <p id="selected-client-contact"></p>
                            </div>
                        </div>
                        <button type="button" class="btn-change" onclick="changeClient()">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: Products Selection -->
                <div class="form-step" id="step-2">
                    <div class="step-header">
                        <h4><i class="fas fa-shopping-cart"></i> Sélection des produits</h4>
                        <p>Recherchez et ajoutez les produits à la commande</p>
                    </div>
                    
                    <div class="product-search-section">
                        <div class="search-input-group">
                            <div class="input-with-icon">
                                <i class="fas fa-search input-icon"></i>
                                <input type="text" id="product-search" class="form-control" placeholder="Nom, référence ou code-barres du produit" autocomplete="off">
                            </div>
                            <button type="button" class="btn btn-primary" onclick="searchProducts()">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div id="product-results" class="search-results"></div>
                    </div>
                    
                    <div class="products-table-container">
                        <div class="table-header">
                            <h5><i class="fas fa-list"></i> Produits sélectionnés</h5>
                            <span class="product-count">0 produit(s)</span>
                        </div>
                        
                        <div class="products-table" id="selected-products-table">
                            <div class="table-header-row">
                                <div class="col-product">Produit</div>
                                <div class="col-price">Prix unitaire</div>
                                <div class="col-quantity">Quantité</div>
                                <div class="col-discount">Remise</div>
                                <div class="col-total">Total</div>
                                <div class="col-action">Action</div>
                            </div>
                            <div id="selected-products-body" class="table-body">
                                <div class="empty-state">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h5>Aucun produit sélectionné</h5>
                                    <p>Recherchez et ajoutez des produits à votre commande</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-summary">
                            <div class="summary-row">
                                <span>Sous-total:</span>
                                <span id="order-subtotal">0.00 MAD</span>
                            </div>
                            <div class="summary-row">
                                <span>Remise totale:</span>
                                <span id="order-discount">0.00 MAD</span>
                            </div>
                            <div class="summary-row total">
                                <span>Total:</span>
                                <span id="order-total">0.00 MAD</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Finalize Order -->
                <div class="form-step" id="step-3">
                    <div class="step-header">
                        <h4><i class="fas fa-check-circle"></i> Finaliser la commande</h4>
                        <p>Ajoutez les détails finaux de la commande</p>
                    </div>

                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Sous-total:</span>
                            <span id="order-subtotal">0.00 MAD</span>
                        </div>
                        <div class="summary-row">
                            <span>Remise totale:</span>
                            <span id="order-discount">0.00 MAD</span>
                        </div>
                        <div class="summary-row">
                            <span>Frais de livraison:</span>
                            <div class="shipping-cost-control">
                                <input type="number" id="shipping-cost" class="form-control" 
                                    min="0" step="0.01" value="0" onchange="updateOrderSummary()">
                                <span>MAD</span>
                            </div>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span id="order-total">0.00 MAD</span>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        
                        <div class="form-group full-width">
                            <label for="order-notes">
                                <i class="fas fa-sticky-note"></i> Notes de commande
                            </label>
                            <textarea id="order-notes" class="form-control" rows="4" placeholder="Instructions spéciales, remarques..."></textarea>
                        </div>

                    </div>
                    
                    <div class="order-preview">
                        <h5><i class="fas fa-eye"></i> Récapitulatif de la commande</h5>
                        <div class="preview-content">
                            <div class="preview-client">
                                <strong>Client:</strong> <span id="preview-client-name">-</span>
                            </div>
                            <div class="preview-products">
                                <strong>Produits:</strong> <span id="preview-product-count">0</span>
                            </div>
                            <div class="preview-total">
                                <strong>Total:</strong> <span id="preview-total">0.00 MAD</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="modal-footer">
            <div class="footer-buttons">
                <button class="btn btn-secondary" onclick="closeCreateOrderModal()">
                    <i class="fas fa-times"></i> Annuler
                </button>
                
                <div class="navigation-buttons">
                    <button class="btn btn-outline-primary" id="prev-btn" onclick="previousStep()" style="display: none;">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </button>
                    <button class="btn btn-primary" id="next-btn" onclick="nextStep()">
                        Suivant <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="btn btn-success" id="submit-btn" onclick="submitOrder()" style="display: none;">
                        <i class="fas fa-check"></i> Créer la commande
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Enhanced Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background-color: white;
        margin: 2% auto;
        width: 95%;
        max-width: 1200px;
        height: 90vh;
        border-radius: 0px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 24px 32px;
        position: relative;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .header-content h3 {
        font-size: 24px;
        font-weight: 600;
        margin: 0 0 16px 0;
    }

    .progress-indicator {
        display: flex;
        gap: 24px;
        margin-top: 8px;
    }

    .step {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 25px;
        background: rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .step.active {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }

    .step-number {
        background: rgba(255, 255, 255, 0.3);
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 600;
    }

    .step.active .step-number {
        background: white;
        color: #667eea;
    }

    .step-label {
        font-size: 14px;
        font-weight: 500;
    }

    .close {
        position: absolute;
        right: 24px;
        top: 24px;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        color: white;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 32px;
        background: #fafbfc;
    }

    /* Form Steps */
    .form-step {
        display: none;
        animation: stepSlideIn 0.4s ease;
    }

    .form-step.active {
        display: block;
    }

    @keyframes stepSlideIn {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .step-header {
        text-align: center;
        margin-bottom: 32px;
        padding: 24px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .step-header h4 {
        color: #333;
        font-size: 20px;
        margin-bottom: 8px;
    }

    .step-header p {
        color: #666;
        margin: 0;
    }

    /* Client Section */
    .client-search-section {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        margin-bottom: 24px;
    }

    .search-toggle {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        background: #f8f9fa;
        padding: 4px;
        border-radius: 8px;
    }

    .toggle-btn {
        flex: 1;
        padding: 12px 16px;
        border: none;
        background: transparent;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        color: #666;
    }

    .toggle-btn.active {
        background: white;
        color: #667eea;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .client-section {
        display: none;
    }

    .client-section.active {
        display: block;
    }

    .search-input-group {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
    }

    .input-with-icon {
        position: relative;
        flex: 1;
    }

    .input-icon {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #999;
        font-size: 14px;
    }

    .form-control {
        width: 100%;
        padding: 14px 16px 14px 44px;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus {
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .btn {
        padding: 14px 20px;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .btn-primary {
        background: #667eea;
        color: white;
    }

    .btn-primary:hover {
        background: #5a6fd8;
        transform: translateY(-1px);
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-outline-secondary {
        background: transparent;
        color: #6c757d;
        border: 2px solid #6c757d;
    }

    .btn-success {
        background: #28a745;
        color: white;
    }

    .btn-success:hover {
        background: #218838;
    }

    /* Form Grid */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
        font-size: 14px;
    }

    /* Search Results */
    .search-results {
        background: white;
        border-radius: 8px;
        max-height: 300px;
        overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border: 1px solid #e9ecef;
        margin-top: 8px;
        display: none;
    }

    .search-result-item {
        padding: 16px;
        cursor: pointer;
        border-bottom: 1px solid #f1f3f4;
        transition: background-color 0.2s ease;
    }

    .search-result-item:hover {
        background-color: #f8f9fa;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    /* Client Card */
    .client-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        border-left: 4px solid #28a745;
    }

    .client-info {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .client-avatar {
        width: 50px;
        height: 50px;
        background: #667eea;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }

    .client-details h5 {
        margin: 0 0 4px 0;
        color: #333;
    }

    .client-details p {
        margin: 0;
        color: #666;
        font-size: 14px;
    }

    .btn-change {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 8px 16px;
        border-radius: 6px;
        color: #666;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-change:hover {
        background: #e9ecef;
    }

    /* Products Table */
    .products-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .table-header {
        padding: 20px 24px;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-header h5 {
        margin: 0;
        color: #333;
    }

    .product-count {
        background: #667eea;
        color: white;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .products-table {
        min-height: 300px;
    }

    .table-header-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr 80px;
        gap: 16px;
        padding: 16px 24px;
        background: #f8f9fa;
        font-weight: 600;
        color: #333;
        border-bottom: 1px solid #e9ecef;
        font-size: 14px;
    }

    .table-body {
        min-height: 250px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 16px;
        color: #ddd;
    }

    .empty-state h5 {
        margin-bottom: 8px;
        color: #666;
    }

    .product-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1fr 80px;
        gap: 16px;
        padding: 16px 24px;
        border-bottom: 1px solid #f1f3f4;
        align-items: center;
        transition: background-color 0.2s ease;
    }

    .product-row:hover {
        background-color: #f8f9fa;
    }

    .product-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .product-name {
        font-weight: 500;
        color: #333;
    }

    .product-sku {
        font-size: 12px;
        color: #999;
    }

    .quantity-controls {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .quantity-btn {
        width: 30px;
        height: 30px;
        border: 1px solid #dee2e6;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .quantity-btn:hover {
        background: #f8f9fa;
    }

    .quantity-input {
        width: 60px;
        text-align: center;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 6px;
    }

    .discount-input {
        width: 80px;
        text-align: center;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        padding: 6px;
    }

    .remove-product {
        color: #dc3545;
        cursor: pointer;
        width: 30px;
        height: 30px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .remove-product:hover {
        background: #fee;
    }

    /* Order Summary */
    .order-summary {
        background: #f8f9fa;
        padding: 20px 24px;
        border-top: 1px solid #e9ecef;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 14px;
    }

    .summary-row.total {
        font-size: 18px;
        font-weight: 600;
        color: #28a745;
        padding-top: 8px;
        border-top: 1px solid #dee2e6;
        margin-top: 8px;
    }

    /* Order Preview */
    .order-preview {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        margin-top: 24px;
    }

    .order-preview h5 {
        margin-bottom: 16px;
        color: #333;
    }

    .preview-content {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .preview-content > div {
        padding: 12px;
        background: #f8f9fa;
        border-radius: 6px;
        display: flex;
        justify-content: space-between;
    }

    /* Modal Footer */
    .modal-footer {
        padding: 24px 32px;
        background: white;
        border-top: 1px solid #e9ecef;
        box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.05);
    }

    .footer-buttons {
        display: flex;
        justify-content: space-between;
        align-items: center;
        column-gap: .5em;
    }

    .navigation-buttons {
        display: flex;
        gap: 12px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .modal-content {
            width: 98%;
            height: 95vh;
            margin: 1% auto;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .table-header-row,
        .product-row {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        
        .progress-indicator {
            flex-direction: column;
            gap: 8px;
        }
        
        .footer-buttons {
            flex-direction: column;
            gap: 16px;
        }
        
        .navigation-buttons {
            width: 100%;
            justify-content: center;
        }
    }

    /* Animation for product addition */
    @keyframes productAdd {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .product-row {
        animation: productAdd 0.3s ease;
    }

    /* Loading States */
    .loading {
        position: relative;
        pointer-events: none;
        opacity: 0.7;
    }

    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #667eea;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Notification Styles */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        animation: notificationSlide 0.3s ease;
    }

    @keyframes notificationSlide {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .notification.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .notification.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .notification.warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .shipping-cost-control {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .shipping-cost-control input {
        width: 80px;
        padding: 6px;
        text-align: right;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
</style>

<script>
    // Enhanced Order Modal Functions
    let currentStep = 1;
    let selectedProducts = [];
    let selectedClient = null;
    let clientMode = 'existing'; // 'existing' or 'new'

    function openCreateOrderModal() {
        document.getElementById('create-order-modal').style.display = 'block';
        resetCreateOrderForm();
        goToStep(1);
    }

    function closeCreateOrderModal() {
        document.getElementById('create-order-modal').style.display = 'none';
    }

    function resetCreateOrderForm() {
        document.getElementById('create-order-form').reset();
        document.getElementById('shipping-cost').value = '0';
        selectedProducts = [];
        selectedClient = null;
        currentStep = 1;
        updateProductsDisplay();
        updateOrderSummary();
        hideClientCard();
        clearSearchResults();
        updateStepIndicator();
        updateNavigationButtons();
    }

    // Step Navigation
    function goToStep(step) {
        // Hide all steps
        document.querySelectorAll('.form-step').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        
        // Show current step
        document.getElementById(`step-${step}`).classList.add('active');
        document.querySelector(`[data-step="${step}"]`).classList.add('active');
        
        currentStep = step;
        updateNavigationButtons();
        updatePreview();
    }

    function nextStep() {
        if (validateCurrentStep()) {
            if (currentStep < 3) {
                goToStep(currentStep + 1);
            }
        }
    }

    function previousStep() {
        if (currentStep > 1) {
            goToStep(currentStep - 1);
        }
    }

    function validateCurrentStep() {
        switch(currentStep) {
            case 1:
                if (clientMode === 'new') {
                    const name = document.getElementById('client-name').value.trim();
                    const phone = document.getElementById('client-phone').value.trim();
                    if (!name || !phone) {
                        showNotification('Veuillez remplir les champs obligatoires du client', 'error');
                        return false;
                    }
                    selectedClient = {
                        name: name,
                        phone: phone,
                        email: document.getElementById('client-email').value.trim(),
                        address: document.getElementById('client-address').value.trim(),
                        city: document.getElementById('client-city').value.trim(),
                        isNew: true
                    };
                } else if (!selectedClient) {
                    showNotification('Veuillez sélectionner un client', 'error');
                    return false;
                }
                return true;
            case 2:
                if (selectedProducts.length === 0) {
                    showNotification('Veuillez ajouter au moins un produit', 'error');
                    return false;
                }
                return true;
            case 3:
                return true;
            default:
                return true;
        }
    }

    function updateNavigationButtons() {
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const submitBtn = document.getElementById('submit-btn');
        
        prevBtn.style.display = currentStep > 1 ? 'flex' : 'none';
        nextBtn.style.display = currentStep < 3 ? 'flex' : 'none';
        submitBtn.style.display = currentStep === 3 ? 'flex' : 'none';
    }

    function updateStepIndicator() {
        document.querySelectorAll('.step').forEach((step, index) => {
            if (index + 1 <= currentStep) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
    }

    // Client Management
    function toggleClientMode(mode) {
        clientMode = mode;
        
        // Update toggle buttons
        document.querySelectorAll('.toggle-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById(`${mode}-client-btn`).classList.add('active');
        
        // Show/hide sections
        document.querySelectorAll('.client-section').forEach(section => section.classList.remove('active'));
        document.getElementById(`${mode}-client-section`).classList.add('active');
        
        // Clear previous selections
        selectedClient = null;
        hideClientCard();
        clearSearchResults();
        
        if (mode === 'new') {
            document.getElementById('create-order-form').reset();
        }
    }

    function searchClients() {
        const query = document.getElementById('client-search').value.trim();
        if (query.length < 2) {
            showNotification('Veuillez entrer au moins 2 caractères', 'warning');
            return;
        }

        const resultsContainer = document.getElementById('client-results');
        resultsContainer.innerHTML = '<div class="search-result-item"><i class="fas fa-spinner fa-spin"></i> Recherche en cours...</div>';
        resultsContainer.style.display = 'block';

        // Make AJAX call to PHP endpoint
        fetch(`../controllers/search_clients.php?query=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    resultsContainer.innerHTML = `<div class="search-result-item"><i class="fas fa-exclamation-circle"></i> ${data.message}</div>`;
                    return;
                }

                if (data.data.length === 0) {
                    resultsContainer.innerHTML = '<div class="search-result-item"><i class="fas fa-exclamation-circle"></i> Aucun client trouvé</div>';
                    return;
                }

                resultsContainer.innerHTML = data.data.map(client => `
                    <div class="search-result-item" onclick="selectClient(${client.id}, '${escapeHtml(client.name)}', '${escapeHtml(client.email)}', '${escapeHtml(client.phone)}', '${escapeHtml(client.address)}', '${escapeHtml(client.city)}')">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 40px; height: 40px; background: #667eea; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: between; align-items: start;">
                                    <div>
                                        <strong>${escapeHtml(client.name)}</strong><br>
                                        <small style="color: #666;">${escapeHtml(client.email)} | ${escapeHtml(client.phone)}</small><br>
                                        <small style="color: #999;">${escapeHtml(client.address)}, ${escapeHtml(client.city)}</small>
                                    </div>
                                    <div style="text-align: right; font-size: 11px; color: #999;">
                                        ${client.total_orders} commande(s)<br>
                                        ${client.total_spent} ${client.currency}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error searching clients:', error);
                resultsContainer.innerHTML = '<div class="search-result-item"><i class="fas fa-exclamation-triangle"></i> Erreur lors de la recherche</div>';
            });
    }

    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function selectClient(id, name, email, phone, address, city) {
        selectedClient = {
            id: id,
            name: name,
            email: email,
            phone: phone,
            address: address,
            city: city,
            isNew: false
        };
        
        showClientCard(selectedClient);
        clearSearchResults();
        fetchShippingCost(city);
    }

    function fetchShippingCost(city) {
        fetch(`../controllers/get_shipping_cost.php?city=${encodeURIComponent(city)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.cost) {
                    document.getElementById('shipping-cost').value = data.cost;
                    updateOrderSummary();
                }
            })
            .catch(error => {
                console.error('Error fetching shipping cost:', error);
            });
    }

    function showClientCard(client) {
        document.getElementById('selected-client-name').textContent = client.name;
        document.getElementById('selected-client-contact').textContent = `${client.phone} | ${client.email}`;
        document.getElementById('selected-client-card').style.display = 'flex';
    }

    function hideClientCard() {
        document.getElementById('selected-client-card').style.display = 'none';
    }

    function changeClient() {
        selectedClient = null;
        hideClientCard();
        document.getElementById('client-search').value = '';
        document.getElementById('client-search').focus();
    }

    function clearSearchResults() {
        document.getElementById('client-results').innerHTML = '';
        document.getElementById('client-results').style.display = 'none';
        document.getElementById('product-results').innerHTML = '';
        document.getElementById('product-results').style.display = 'none';
    }

    // Product Management
    function searchProducts() {
        const query = document.getElementById('product-search').value.trim();
        
        if (query.length < 2) {
            showNotification('Veuillez entrer au moins 2 caractères', 'warning');
            return;
        }

        const resultsContainer = document.getElementById('product-results');
        resultsContainer.innerHTML = '<div class="search-result-item"><i class="fas fa-spinner fa-spin"></i> Recherche en cours...</div>';
        resultsContainer.style.display = 'block';

        // Make AJAX call to search endpoint
        fetch(`../controllers/search_products.php?query=${encodeURIComponent(query)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (data.products.length === 0) {
                        resultsContainer.innerHTML = '<div class="search-result-item"><i class="fas fa-exclamation-circle"></i> Aucun produit trouvé</div>';
                        return;
                    }

                    resultsContainer.innerHTML = data.products.map(product => {
                        // Get first image or use default
                        let imageUrl = product.image_url || 'https://via.placeholder.com/50';
                        if (product.images && product.images.length > 0) {
                            try {
                                const images = JSON.parse(product.images);
                                if (images.length > 0) {
                                    imageUrl = images[0].src || imageUrl;
                                }
                            } catch (e) {
                                console.error('Error parsing product images:', e);
                            }
                        }

                        // Convert price to number to ensure toFixed() works
                        const price = parseFloat(product.price) || 0;

                        return `
                            <div class="search-result-item" onclick="addProductToOrder(
                                ${product.id}, 
                                '${escapeHtml(product.name)}', 
                                ${price}, 
                                '${escapeHtml(product.sku || '')}', 
                                ${product.stock_quantity || product.quantity || 0}, 
                                '${imageUrl}'
                            )">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <img src="${imageUrl}" alt="${escapeHtml(product.name)}" 
                                        style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                        onerror="this.src='https://via.placeholder.com/50'">
                                    <div style="flex: 1;">
                                        <strong>${escapeHtml(product.name)}</strong><br>
                                        <small style="color: #666;">SKU: ${escapeHtml(product.sku || 'N/A')}</small><br>
                                        <small style="color: #28a745; font-weight: 600;">${price.toFixed(2)} MAD</small>
                                        <small style="color: #999; margin-left: 12px;">
                                            Stock: ${product.stock_quantity || product.quantity || 0}
                                        </small>
                                        ${product.status === 'outofstock' ? '<span class="badge bg-danger ms-2">Rupture</span>' : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    resultsContainer.innerHTML = `<div class="search-result-item"><i class="fas fa-exclamation-circle"></i> ${data.message || 'Erreur de recherche'}</div>`;
                }
            })
            .catch(error => {
                console.error('Error searching products:', error);
                resultsContainer.innerHTML = '<div class="search-result-item"><i class="fas fa-exclamation-circle"></i> Erreur de connexion au serveur</div>';
            });
    }

    // Helper function to escape HTML
    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    function addProductToOrder(id, name, price, sku, stock, image) {
        // Check if product already exists
        const existingIndex = selectedProducts.findIndex(p => p.id === id);
        
        if (existingIndex >= 0) {
            if (selectedProducts[existingIndex].quantity < stock) {
                selectedProducts[existingIndex].quantity++;
                updateProductsDisplay();
                updateOrderSummary();
            } else {
                showNotification('Stock insuffisant', 'warning');
            }
        } else {
            selectedProducts.push({
                id: id,
                name: name,
                price: price,
                sku: sku,
                stock: stock,
                image: image,
                quantity: 1,
                discount: 0
            });
            updateProductsDisplay();
            updateOrderSummary();
        }
        
        clearSearchResults();
        document.getElementById('product-search').value = '';
    }

    function updateProductsDisplay() {
        const tbody = document.getElementById('selected-products-body');
        const productCount = document.querySelector('.product-count');
        
        productCount.textContent = `${selectedProducts.length} produit(s)`;
        
        if (selectedProducts.length === 0) {
            tbody.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-shopping-cart"></i>
                    <h5>Aucun produit sélectionné</h5>
                    <p>Recherchez et ajoutez des produits à votre commande</p>
                </div>
            `;
            return;
        }
        
        tbody.innerHTML = selectedProducts.map((product, index) => `
            <div class="product-row">
                <div class="product-info">
                    <div class="product-name">
                        <img src="${product.image}" alt="${product.name}" class="product-image" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 8px;">
                        ${product.name}
                    </div>
                    <div class="product-sku">SKU: ${product.sku}</div>
                </div>
                <div>${product.price.toFixed(2)} MAD</div>
                <div class="quantity-controls">
                    <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <input type="number" class="quantity-input" value="${product.quantity}"
                        min="1" max="${product.stock}" onchange="setQuantity(${index}, this.value)">
                    <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, 1)">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div>
                    <input type="number" class="discount-input" value="${product.discount}"
                        min="0" max="${product.price * product.quantity}" step="0.01"
                        onchange="setDiscount(${index}, this.value)"> MAD
                </div>
                <div style="font-weight: 600; color: #28a745;">
                    ${((product.price * product.quantity) - product.discount).toFixed(2)} MAD
                </div>
                <div>
                    <button type="button" class="remove-product" onclick="removeProduct(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    function updateQuantity(index, change) {
        const product = selectedProducts[index];
        const newQuantity = product.quantity + change;
        
        if (newQuantity >= 1 && newQuantity <= product.stock) {
            product.quantity = newQuantity;
            updateProductsDisplay();
            updateOrderSummary();
        } else if (newQuantity > product.stock) {
            showNotification('Stock insuffisant', 'warning');
        }
    }

    function setQuantity(index, value) {
        const product = selectedProducts[index];
        const quantity = parseInt(value);
        
        if (quantity >= 1 && quantity <= product.stock) {
            product.quantity = quantity;
            updateOrderSummary();
        } else {
            showNotification('Quantité invalide', 'warning');
            updateProductsDisplay();
        }
    }

    function setDiscount(index, value) {
        const product = selectedProducts[index];
        const discount = parseFloat(value) || 0;
        const maxDiscount = product.price * product.quantity;
        
        if (discount >= 0 && discount <= maxDiscount) {
            
            product.discount = discount;
            updateProductsDisplay();
            updateOrderSummary();

        } else {
            showNotification('Remise invalide', 'warning');
            updateProductsDisplay();
        }
    }

    function removeProduct(index) {
        selectedProducts.splice(index, 1);
        updateProductsDisplay();
        updateOrderSummary();
        showNotification('Produit supprimé', 'success');
    }

    function updateOrderSummary() {
        const subtotal = selectedProducts.reduce((sum, product) => sum + (product.price * product.quantity), 0);
        const totalDiscount = selectedProducts.reduce((sum, product) => sum + product.discount, 0);
        const shippingCost = parseFloat(document.getElementById('shipping-cost').value) || 0;
        const total = subtotal - totalDiscount + shippingCost;
        
        document.getElementById('order-subtotal').textContent = subtotal.toFixed(2) + ' MAD';
        document.getElementById('order-discount').textContent = totalDiscount.toFixed(2) + ' MAD';
        document.getElementById('order-total').textContent = total.toFixed(2) + ' MAD';
    }

    function updatePreview() {
        if (currentStep >= 1 && selectedClient) {
            document.getElementById('preview-client-name').textContent = selectedClient.name;
        }
        
        if (currentStep >= 2) {
            document.getElementById('preview-product-count').textContent = `${selectedProducts.length} produit(s)`;
            const total = selectedProducts.reduce((sum, product) => 
                sum + (product.price * product.quantity) - product.discount, 0);
            document.getElementById('preview-total').textContent = total.toFixed(2) + ' MAD';
        }
    }

    // Form Submission
    function submitOrder() {

        if (!validateCurrentStep()) return;
    
        const shippingCost = parseFloat(document.getElementById('shipping-cost').value) || 0;
        
        const orderData = {
            client: selectedClient,
            products: selectedProducts,
            subtotal: selectedProducts.reduce((sum, product) => sum + (product.price * product.quantity), 0),
            discount: selectedProducts.reduce((sum, product) => sum + product.discount, 0),
            shipping_cost: shippingCost,
            total: selectedProducts.reduce((sum, product) => 
                sum + (product.price * product.quantity) - product.discount, 0) + shippingCost,
            notes: document.getElementById('order-notes').value.trim(),
            created_at: new Date().toISOString()
        };
            
        const submitBtn = document.getElementById('submit-btn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création en cours...';
        
        // Simulate API call
        setTimeout(() => {
            fetch('../controllers/create_orderByAgent.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(orderData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Commande créée avec succès!', 'success');
                    closeCreateOrderModal();
                    // loadOrders(); // Refresh orders list
                } else {
                    showNotification(data.message || 'Erreur lors de la création', 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur de connexion', 'error');
                console.error('Error:', error);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
            
            closeCreateOrderModal();
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }, 2000);
    }

    // Auto-search on input
    document.addEventListener('DOMContentLoaded', function() {
        let searchTimeout;
        
        document.getElementById('client-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    searchClients();
                }, 500);
            } else {
                clearSearchResults();
            }
        });
        
        document.getElementById('product-search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    searchProducts();
                }, 500);
            } else {
                clearSearchResults();
            }
        });
        
        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-input-group') && !e.target.closest('.search-results')) {
                clearSearchResults();
            }
        });
        
        // Add event listener to create order button (adjust selector as needed)
        const createBtn = document.getElementById('createCommande-btn');
        if (createBtn) {
            createBtn.addEventListener('click', openCreateOrderModal);
        }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('create-order-modal').style.display === 'block') {
            if (e.key === 'Escape') {
                closeCreateOrderModal();
            } else if (e.key === 'Enter' && e.ctrlKey) {
                if (currentStep < 3) {
                    nextStep();
                } else {
                    submitOrder();
                }
            }
        }
    });
</script>