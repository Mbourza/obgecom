<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// controllers/update_order.php - Update order in database
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$db = DB::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

try {
    // Validate required fields
    $required_fields = ['order_id', 'customer_name', 'customer_phone', 'customer_email'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception("Champs requis manquants: " . implode(', ', $missing_fields));
    }
    
    // Validate email format
    if (!filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Format d\'email invalide');
    }
    
    // Validate order ID
    $order_id = intval($data['order_id']);
    if ($order_id <= 0) {
        throw new Exception('ID de commande invalide');
    }
    
    // Get current user ID
    $user_id = getCurrentUserId($db);
    if (!$user_id) {
        throw new Exception('Utilisateur non authentifié');
    }
    
    // Verify order exists and belongs to user
    $existing_order = getOrderById($db, $order_id, $user_id);
    if (!$existing_order) {
        throw new Exception('Commande non trouvée ou accès non autorisé');
    }
    
    // Start database transaction
    $db->beginTransaction();
    
    try {
        // Calculate final total amount (subtotal - discount)
        $subtotal = isset($data['subtotal']) ? floatval($data['subtotal']) : 0;
        $discount_amount = isset($data['discount_amount']) ? floatval($data['discount_amount']) : 0;
        $final_total = $subtotal - $discount_amount;
        
        // Ensure total is not negative
        if ($final_total < 0) {
            $final_total = 0;
        }
        
        // Prepare order update data
        $order_update_data = [
            'customer_name' => trim($data['customer_name']),
            'customer_phone' => trim($data['customer_phone']),
            'customer_email' => trim($data['customer_email']),
            'shipping_address' => isset($data['shipping_address']) ? trim($data['shipping_address']) : '',
            'customer_ville' => isset($data['shipping_city']) ? trim($data['shipping_city']) : '',
            'total_amount' => $final_total,
            'discount_amount'=>$discount_amount,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Add notes if provided
        if (isset($data['notes'])) {
            $order_update_data['order_note'] = trim($data['notes']);
        }
        
        // Update order
        $order_result = $db->update('orders', $order_id, $order_update_data);
        
        if (!$order_result) {
            throw new Exception('Erreur lors de la mise à jour de la commande');
        }
        
        // Update order items if provided
        if (isset($data['items']) && is_array($data['items']) && !empty($data['items'])) {
            $total_weight = 0;
            
            foreach ($data['items'] as $item) {
                if (!isset($item['product_id']) || !isset($item['price']) || !isset($item['quantity'])) {
                    continue; // Skip invalid items
                }
                
                $item_id = intval($item['product_id']);
                $price = floatval($item['price']);
                $quantity = intval($item['quantity']);
                
                if ($item_id <= 0 || $price < 0 || $quantity <= 0) {
                    continue; // Skip invalid values
                }
                
                // Check if order item exists
                $existing_item = getOrderItem($db, $item_id);
                
                if ($existing_item) {
                    // Update existing item
                    $item_update_data = [
                        'unit_price' => $price,
                        'quantity' => $quantity,
                        'total_price' => $price * $quantity,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Update product_name if provided
                    if (isset($item['product_name']) && !empty(trim($item['product_name']))) {
                        $item_update_data['product_name'] = trim($item['product_name']);
                    }
                    
                    // Update product_sku if provided
                    if (isset($item['product_sku']) && !empty(trim($item['product_sku']))) {
                        $item_update_data['product_sku'] = trim($item['product_sku']);
                    }
                    
                    // Update weight if provided in the item data
                    if (isset($item['weight']) && $item['weight'] > 0) {
                        $total_item_weight = floatval($item['weight']) * $quantity;
                        $item_update_data['weight'] = $total_item_weight;
                        $total_weight += $total_item_weight;
                    } else {
                        // Calculate weight if original item had weight
                        if (isset($existing_item['weight']) && $existing_item['weight'] > 0) {
                            $unit_weight = $existing_item['weight'] / $existing_item['quantity'];
                            $new_total_weight = $unit_weight * $quantity;
                            $item_update_data['weight'] = $new_total_weight;
                            $total_weight += $new_total_weight;
                        }
                    }
                    
                    $item_result = $db->update('order_items', $existing_item['id'], $item_update_data);
                    
                    if (!$item_result) {
                        throw new Exception('Erreur lors de la mise à jour des produits');
                    }
                } else {
                    // Create new order item if it doesn't exist
                    $new_item_data = [
                        'order_id' => $order_id,
                        'product_name' => isset($item['product_name']) ? trim($item['product_name']) : '',
                        'product_sku' => isset($item['product_sku']) ? trim($item['product_sku']) : '',
                        'quantity' => $quantity,
                        'unit_price' => $price,
                        'total_price' => $price * $quantity,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $item_result = $db->insert('order_items', $new_item_data);
                    
                    if (!$item_result) {
                        throw new Exception('Erreur lors de la création du nouvel article');
                    }
                    
                    // Add weight to total
                    if ($new_item_data['weight'] > 0) {
                        $total_weight += $new_item_data['weight'];
                    }
                }
            }
            
            // Update order total weight if calculated
            if ($total_weight > 0) {
                $db->update('orders', $order_id, ['weight' => $total_weight]);
            }
        }
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'message' => 'Commande mise à jour avec succès',
            'final_total' => $final_total
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in update_order.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get current user ID from session
 * Reusing the same function from create_order.php
 */
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']['username']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    $identifier = $_SESSION['user']['username'];

    // Search in users table
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

    // Search in agents table
    $agent = $db->getThisQuery("
        SELECT user_id 
        FROM agents 
        WHERE email = ? OR name = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($agent && isset($agent[0]['user_id']) && $agent[0]['user_id'] !== null) {
        return (int)$agent[0]['user_id'];
    }

    return null;
}

/**
 * Get order by ID and verify it belongs to the user
 */
function getOrderById($db, $order_id, $user_id) {
    $query = "SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1";
    $result = $db->getThisQuery($query, [$order_id, $user_id]);
    
    if ($result && count($result) > 0) {
        return $result[0];
    }
    
    return null;
}

/**
 * Get order item by order ID and product ID
 */
function getOrderItem($db, $item_id) {
    // Use product_id column for matching
    $query = "SELECT * FROM order_items WHERE id = ? LIMIT 1";
    $result = $db->getThisQuery($query, [$item_id, ]);
    
    if ($result && count($result) > 0) {
        return $result[0];
    }
    
    return null;
} ?>