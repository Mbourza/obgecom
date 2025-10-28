<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// controllers/create_order.php - Create order in database
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
    $required_fields = ['client', 'products', 'total'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception("Champs requis manquants: " . implode(', ', $missing_fields));
    }
    
    // Validate client data
    if (empty($data['client']['name'])) {
        throw new Exception('Nom du client requis');
    }
    if (empty($data['client']['phone'])) {
        throw new Exception('Téléphone du client requis');
    }
    
    // Validate products array
    if (empty($data['products']) || !is_array($data['products'])) {
        throw new Exception('Produits requis');
    }
    
    // Validate each product
    foreach ($data['products'] as $index => $product) {
        if (empty($product['name'])) {
            throw new Exception("Nom produit manquant pour le produit à l'index $index");
        }
        if (!isset($product['quantity']) || $product['quantity'] <= 0) {
            throw new Exception("Quantité invalide pour le produit à l'index $index");
        }
        if (!isset($product['price']) || $product['price'] < 0) {
            throw new Exception("Prix invalide pour le produit à l'index $index");
        }
    }
    
    $user_id = getCurrentUserId($db);
    
    if (!$user_id) {
        throw new Exception('Utilisateur non authentifié');
    }
    
    // Get the first connected store for this user
    $store_id = getConnectedStoreId($db, $user_id);
    
    if (!$store_id) {
        throw new Exception('Aucun magasin connecté trouvé pour cet utilisateur');
    }
    
    // Check for duplicate phone number for this user's orders
    if (isPhoneNumberExists($db, $user_id, $data['client']['phone'])) {
        throw new Exception('Un client avec ce numéro de téléphone existe déjà: ' . $data['client']['phone']);
    }
    
    // Generate order number
    $order_number = generateOrderNumber($db, $user_id);
    
    // Start database transaction
    $db->beginTransaction();
    
    try {
        // Prepare order data
        $order_data = [
            'user_id' => $user_id,
            'store_id' => $store_id, // Now using dynamic store_id
            'external_order_id' => 1, // For external platform orders
            'order_number' => $order_number,
            'customer_name' => $data['client']['name'],
            'customer_email' => !empty($data['client']['email']) ? $data['client']['email'] : '',
            'customer_phone' => $data['client']['phone'],
            'customer_ville' => isset($data['client']['city']) ? $data['client']['city'] : '',
            'shipping_address' => isset($data['client']['address']) ? $data['client']['address'] : '',
            'billing_address' => isset($data['client']['address']) ? $data['client']['address'] : '',
            'shipping_cost' => $data['shipping_cost'] ?? 0,
            'total_amount' => $data['total'],
            'currency' => isset($data['currency']) ? $data['currency'] : 'MAD',
            'status' => 'new-order',
            'shipping_status' => 'pending',
            'payment_status' => 'pending',
            'shipping_method' => isset($data['shipping_method']) ? $data['shipping_method'] : '',
            'tracking_number' => '',
            'weight' => 0, // Will be calculated from products
            'length' => isset($data['dimensions']['length']) ? $data['dimensions']['length'] : 0,
            'width' => isset($data['dimensions']['width']) ? $data['dimensions']['width'] : 0,
            'height' => isset($data['dimensions']['height']) ? $data['dimensions']['height'] : 0,
            'order_date' => isset($data['created_at']) ? $data['created_at'] : date('Y-m-d H:i:s'),
            'platform' => 'manual', // Since this is manually created
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'shipping_company_id' => isset($data['shipping_company_id']) ? $data['shipping_company_id'] : 0,
            'confirmed_by_agent' => 0,
            'handled_at' => null
        ];
        
        // Add notes if provided (assuming you might want to add this field to orders table)
        if (isset($data['notes']) && !empty(trim($data['notes']))) {
            $order_data['order_note'] = trim($data['notes']);
        }
        
        // Insert order
        $order_result = $db->insert('orders', $order_data);
        
        if (!$order_result) {
            throw new Exception('Erreur lors de la création de la commande');
        }
        
        $order_id = $db->getLastInsertId();
        $total_weight = 0;
        
        // Insert order items
        foreach ($data['products'] as $product) {
            $product_weight = isset($product['weight']) ? $product['weight'] : 0;
            $item_quantity = $product['quantity'];
            $item_total_weight = $product_weight * $item_quantity;
            $total_weight += $item_total_weight;
            
            $order_item_data = [
                'order_id' => $order_id,
                'product_name' => $product['name'],
                'product_sku' => isset($product['sku']) ? $product['sku'] : '',
                'quantity' => $item_quantity,
                'unit_price' => $product['price'],
                'total_price' => ($product['price'] * $item_quantity) - (isset($product['discount']) ? $product['discount'] : 0),
                'weight' => $item_total_weight,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $item_result = $db->insert('order_items', $order_item_data);
            
            if (!$item_result) {
                throw new Exception('Erreur lors de l\'ajout des produits à la commande');
            }
        }
        
        // Update order total weight
        if ($total_weight > 0) {
            $db->update('orders', ['weight' => $total_weight], ['id' => $order_id]);
        }
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'order_number' => $order_number,
            'store_id' => $store_id,
            'message' => 'Commande créée avec succès'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in create_order.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Check if phone number already exists for this user's orders
 */
function isPhoneNumberExists($db, $user_id, $phone) {
    // Clean phone number (remove spaces, dashes, etc.)
    $cleaned_phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Check for exact match and cleaned match
    $query = "SELECT COUNT(*) as count FROM orders 
              WHERE user_id = ? 
              AND (customer_phone = ? OR REPLACE(REPLACE(REPLACE(customer_phone, ' ', ''), '-', ''), '(', '') LIKE ?)";
    
    $result = $db->getThisQuery($query, [$user_id, $phone, '%' . $cleaned_phone . '%']);
    
    return $result && $result[0]['count'] > 0;
}

/**
 * Get current user ID from session or JWT token
 * Implementation based on your authentication system
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
 * Get the first connected store ID for the user
 */
function getConnectedStoreId($db, $user_id) {
    $query = "SELECT id FROM stores WHERE user_id = ? AND is_connected = 1 ORDER BY connected_at ASC LIMIT 1";
    $result = $db->getThisQuery($query, [$user_id]);
    
    if ($result && count($result) > 0) {
        return $result[0]['id'];
    }
    
    return null;
}

/**
 * Generate unique order number
 */
function generateOrderNumber($db, $user_id) {
    $prefix = 'ORD';
    $date = date('Ymd');
    
    // Get the count of orders for today for this user
    $count = $db->getThisQuery("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND DATE(created_at) = CURDATE()", [$user_id]);
    $order_count = $count ? $count[0]['count'] + 1 : 1;
    
    return $prefix . '-' . $date . '-' . str_pad($order_count, 4, '0', STR_PAD_LEFT);
}
?>