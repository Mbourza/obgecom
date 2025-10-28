<?php
// filter_orders.php - Fixed version with debugging
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type early
header('Content-Type: application/json');

$db = DB::getInstance();

// Check if user is logged in
$user_id = getCurrentUserId($db);
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'debug' => 'User not logged in']);
    exit;
}

// Get JSON input with error handling
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input', 'debug' => json_last_error_msg()]);
    exit;
}

// If no input, use empty array
if ($input === null) {
    $input = [];
}

// Build the main query with agent information
$query = "SELECT o.*, s.storeName as store_name, 
        a.name as agent_name,
        a.id as agent_id,
        aoa.status as assignment_status,
        aoa.assigned_at,
        aoa.confirmed_at,
        aoa.notes as assignment_notes,
        aoa.priority_score,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
        (SELECT GROUP_CONCAT(CONCAT(oi.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') 
        FROM order_items oi WHERE oi.order_id = o.id) as products
        FROM orders o 
        LEFT JOIN stores s ON o.store_id = s.id 
        LEFT JOIN agent_order_assignments aoa ON o.id = aoa.order_id AND aoa.user_id = o.user_id
        LEFT JOIN agents a ON aoa.agent_id = a.id
        WHERE o.user_id = ?";

$params = [$user_id];

// Apply filters
$conditions = [];

// Confirmation status filter
if (!empty($input['confirmation_status'])) {
    $conditions[] = "o.status = ?";
    $params[] = $input['confirmation_status'];
}

// Shipping status filter
if (!empty($input['shipping_status'])) {
    $conditions[] = "o.shipping_status = ?";
    $params[] = $input['shipping_status'];
}

// Agent filter
if (!empty($input['agent_id'])) {
    $conditions[] = "aoa.agent_id = ?";
    $params[] = $input['agent_id'];
}

// Assignment status filter
if (!empty($input['assignment_status'])) {
    if ($input['assignment_status'] === 'unassigned') {
        $conditions[] = "aoa.agent_id IS NULL";
    } else {
        $conditions[] = "aoa.status = ?";
        $params[] = $input['assignment_status'];
    }
}

// Date range filter
if (!empty($input['date_range'])) {
    switch ($input['date_range']) {
        case 'today':
            $conditions[] = "DATE(o.created_at) = CURDATE()";
            break;
        case 'yesterday':
            $conditions[] = "DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '3months':
            $conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case 'custom':
            if (!empty($input['start_date']) && !empty($input['end_date'])) {
                $conditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
                $params[] = $input['start_date'];
                $params[] = $input['end_date'];
            }
            break;
    }
}

// Store filter
if (!empty($input['store_id']) && is_numeric($input['store_id'])) {
    $conditions[] = "o.store_id = ?";
    $params[] = intval($input['store_id']);
}

// Search filter
if (!empty($input['search'])) {
    $search_term = '%' . strtolower($input['search']) . '%';
    $conditions[] = "(LOWER(o.customer_name) LIKE ? 
                      OR LOWER(o.customer_email) LIKE ? 
                      OR LOWER(o.customer_phone) LIKE ? 
                      OR LOWER(o.customer_ville) LIKE ? 
                      OR LOWER(o.order_number) LIKE ?)";

    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Add conditions to query
if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

// Add ordering
$query .= " ORDER BY o.created_at DESC";

// Add limit if needed (for performance)
$query .= " LIMIT 1000";

try {
    // Debug: Log the query and parameters
    error_log("Query: " . $query);
    error_log("Params: " . print_r($params, true));
    
    // Get orders using your DB class
    $orders = $db->getThisQuery($query, $params);
    
    // Check if orders is an array
    if (!is_array($orders)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Database query returned invalid result',
            'debug' => 'Expected array, got: ' . gettype($orders)
        ]);
        exit;
    }
    
    // Generate HTML for orders table
    $html = generateOrdersTableHTML($orders);
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($orders),
        'debug' => [
            'user_id' => $user_id,
            'input' => $input,
            'conditions_count' => count($conditions)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}

function generateOrdersTableHTML($orders) {
    if (empty($orders)) {
        return '<tr><td colspan="14" class="no-data">Aucune commande trouvée</td></tr>';
    }
    
    $html = '';
    
    // Status labels mapping - updated to match common e-commerce statuses
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
    
    $confirmation_options = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmée',
        'no-answer' => 'Pas de réponse',
        'busy' => 'Occupé',
        'cancelled' => 'Annulée',
        'double-order' => 'Double commande',
        'unreachable' => 'Injoignable'
    ];

    $assignment_labels = [
        'assigned' => 'Assigné',
        'in_progress' => 'En cours',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé'
    ];
    
    foreach ($orders as $order) {
        // Ensure $order is an array or object
        if (!is_array($order) && !is_object($order)) {
            continue;
        }
        
        // Convert object to array for consistent access
        if (is_object($order)) {
            $order = (array) $order;
        }
        
        // Check for required fields
        if (!isset($order['id'])) {
            continue;
        }
        
        $html .= '<tr data-order-id="' . htmlspecialchars($order['id']) . '">';
        
        // Checkbox
        $html .= '<td class="checkbox-cell">';
        $html .= '<input type="checkbox" class="order-checkbox" value="' . htmlspecialchars($order['id']) . '" onchange="handleOrderCheckboxChange(this)">';
        $html .= '</td>';
        
        // Hidden order number
        $html .= '<td style="display: none;"><strong>' . htmlspecialchars($order['order_number'] ?? '') . '</strong></td>';
        
        // Tracking info
        $html .= '<td class="tracking-cell">';
        $html .= '<div class="tracking-info">';
        if (!empty($order['tracking_number'])) {
            $html .= '<div class="tracking-number">' . htmlspecialchars($order['tracking_number']) . '</div>';
        } else {
            $html .= '<div class="tracking-number">-</div>';
        }
        $html .= '<div class="tracking-status">';
        $shipping_status = $order['shipping_status'] ?? 'pending';
        $html .= '<span class="status-badge ' . $shipping_status . '">';
        $html .= $status_labels[$shipping_status] ?? $shipping_status;
        $html .= '</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</td>';
        
        // Customer info
        $html .= '<td class="customer-cell">';
        $html .= '<div class="customer-info">';
        $html .= '<div class="customer-name">' . htmlspecialchars($order['customer_name'] ?? '') . '</div>';
        $html .= '<div class="customer-email">' . htmlspecialchars($order['customer_email'] ?? '') . '</div>';
        $html .= '</div>';
        $html .= '</td>';
        
        // Phone
        $html .= '<td class="phone-cell">';
        if (!empty($order['customer_phone'])) {
            $html .= '<div class="whatsapp-container">';
            $html .= '<a href="https://wa.me/' . htmlspecialchars($order['customer_phone']) . '" class="whatsapp-btn" target="_blank" title="Contacter via WhatsApp">';
            $html .= '<i class="fab fa-whatsapp" style="color: #FFF; font-size: 1rem;"></i>';
            $html .= '</a>';
            $html .= '<div class="phone-tooltip">' . htmlspecialchars($order['customer_phone']) . '</div>';
            $html .= '</div>';
        } else {
            $html .= '<span style="color: #9ca3af; font-size: 11px;">-</span>';
        }
        $html .= '</td>';
        
        // City - updated to use customer_ville
        $html .= '<td class="city-cell">';
        $html .= '<div style="font-size: 11px; color: #374151;">';
        $html .= !empty($order['customer_ville']) ? htmlspecialchars($order['customer_ville']) : '-';
        $html .= '</div>';
        $html .= '</td>';
        
        // Products - now using the concatenated product details from order_items
        $html .= '<td class="products-cell">';
        $html .= '<div class="products-container">';
        $html .= '<div class="product-count">' . ($order['item_count'] ?? 0) . '</div>';
        $html .= '<div class="products-tooltip">' . htmlspecialchars($order['products'] ?? 'Aucun produit') . '</div>';
        $html .= '</div>';
        $html .= '</td>';
        
        // Amount
        $html .= '<td class="amount-cell">';
        $amount = $order['total_amount'] ?? 0;
        $currency = $order['currency'] ?? 'DH';
        $html .= '<div class="amount">' . number_format($amount, 2) . ' ' . $currency . '</div>';
        $html .= '</td>';
        
        // Status
        $html .= '<td class="status-cell">';
        $html .= '<span class="status-badge ' . $shipping_status . '">';
        $html .= $status_labels[$shipping_status] ?? $shipping_status;
        $html .= '</span>';
        $html .= '</td>';
        
        // Confirmation
        $html .= '<td class="confirmation-cell">';
        $html .= '<select class="confirmation-select" data-order-id="' . htmlspecialchars($order['id']) . '" onchange="updateConfirmationStatus(this)">';
        
        $current_status = $order['status'] ?? 'pending';
        
        foreach ($confirmation_options as $value => $label) {
            $selected = ($current_status === $value) ? 'selected' : '';
            $style = "background-color: ";
            switch($value) {
                case 'pending': $style .= "#f8f9fa; color: #495057;"; break;
                case 'confirmed': $style .= "#d4edda; color: #155724;"; break;
                case 'no-answer': $style .= "#fff3cd; color: #856404;"; break;
                case 'busy': $style .= "#ffe8cc; color: #804d00;"; break;
                case 'cancelled': $style .= "#f8d7da; color: #721c24;"; break;
                case 'double-order': $style .= "#e2e3e5; color: #383d41;"; break;
                case 'unreachable': $style .= "#d1ecf1; color: #0c5460;"; break;
                default: $style .= "#f8f9fa; color: #212529;";
            }
            
            $html .= '<option value="' . htmlspecialchars($value) . '" ' . $selected . ' style="' . $style . '">';
            $html .= htmlspecialchars($label);
            $html .= '</option>';
        }
        
        $html .= '</select>';
        
        // Shipping button for confirmed orders without tracking
        if ($current_status == 'confirmed' && empty($order['tracking_number'])) {
            $html .= '<div class="shipping-btn-container mt-2">';
            $html .= '<button class="btn btn-sm btn-primary submit-shipping-btn" data-order-id="' . htmlspecialchars($order['id']) . '" onclick="submitToShipping(' . $order['id'] . ')">';
            $html .= '<i class="fas fa-truck"></i> Expédier';
            $html .= '</button>';
            $html .= '</div>';
        }
        
        $html .= '</td>';
        
        // Agent column
        $html .= '<td class="agent-cell">';
        $html .= '<div class="agent-info" style="text-align: center;">';
        if (!empty($order['agent_name'])) {
            $html .= '<div class="agent-name" style="font-size: 11px; color: #374151; font-weight: 500;">';
            $html .= htmlspecialchars($order['agent_name']);
            $html .= '</div>';
            if (!empty($order['assignment_status'])) {
                $html .= '<div class="assignment-status" style="font-size: 10px; color: #6b7280;">';
                $html .= '<span class="assignment-badge ' . $order['assignment_status'] . '">';
                $html .= $assignment_labels[$order['assignment_status']] ?? $order['assignment_status'];
                $html .= '</span>';
                $html .= '</div>';
            }
        } else {
            $html .= '<div style="font-size: 11px; color: #9ca3af;">';
            $html .= 'Non assigné';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</td>';
        
        // Store
        $html .= '<td class="store-cell">';
        $html .= '<div class="store-name">';
        $html .= !empty($order['store_name']) ? htmlspecialchars($order['store_name']) : 'N/A';
        $html .= '</div>';
        $html .= '</td>';
        
        // Date
        $html .= '<td class="date-cell">';
        $created_at = $order['created_at'] ?? date('Y-m-d H:i:s');
        $html .= '<div>' . date('d/m/Y', strtotime($created_at)) . '</div>';
        $html .= '<div style="font-size: 10px; color: #9ca3af;">' . date('H:i', strtotime($created_at)) . '</div>';
        $html .= '</td>';
        
        // Actions
        $html .= '<td class="actions-cell">';
        $html .= '<div class="actions-dropdown">';
        $html .= '<div class="actions-trigger">⋮</div>';
        $html .= '<div class="actions-menu">';
        $html .= '<button onclick="viewOrder(' . $order['id'] . ')" title="Voir détails">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">';
        $html .= '<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>';
        $html .= '<path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>';
        $html .= '</svg>';
        $html .= 'Voir';
        $html .= '</button>';
        $html .= '<button onclick="editOrderStatus(' . $order['id'] . ')" title="Modifier statut">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">';
        $html .= '<path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708L10.5 8.207l-3-3L12.146.146zM11.207 9.5L7 13.707V10.5a.5.5 0 0 1 .5-.5h3.707zM10.5 11.207L6.293 7 .854 12.439a.5.5 0 0 0-.146.353V15.5a.5.5 0 0 0 .5.5h2.707a.5.5 0 0 0 .354-.146L10.5 11.207z"/>';
        $html .= '</svg>';
        $html .= 'Modifier';
        $html .= '</button>';
        $html .= '<button class="delete-btn" onclick="deleteOrder(' . $order['id'] . ')" title="Supprimer">';
        $html .= '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">';
        $html .= '<path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>';
        $html .= '<path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>';
        $html .= '</svg>';
        $html .= 'Supprimer';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</td>';
        
        $html .= '</tr>';
    }
    
    return $html;
}

/**
 * Get current user ID from session
 */
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    // This can be username, email, or phone
    $identifier = $_SESSION['user']['username'];

    // Prefer explicit ID if available
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Try to match identifier against username, email, or phone
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

    return null;
}

/**
 * Alternative function to get detailed order items for a specific order
 */
function getOrderItemsDetails($db, $order_id) {
    $query = "SELECT * FROM order_items WHERE order_id = ? ORDER BY id";
    return $db->getThisQuery($query, [$order_id]);
}

/**
 * Get order summary with total weight and dimensions
 */
function getOrderSummary($db, $order_id) {
    $query = "SELECT 
                COUNT(*) as total_items,
                SUM(quantity) as total_quantity,
                SUM(total_price) as items_total,
                SUM(weight * quantity) as total_weight
              FROM order_items 
              WHERE order_id = ?";
    
    $result = $db->getThisQuery($query, [$order_id]);
    return $result ? $result[0] : null;
}
?>