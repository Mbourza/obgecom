<?php 
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    $identifier = $_SESSION['user']['username'];

    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

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

function getUserStoreId($db, $user_id) {
    // Get the user's default store
    $store = $db->getThisQuery("
        SELECT id 
        FROM stores 
        WHERE user_id = ? 
        ORDER BY is_connected DESC, id ASC 
        LIMIT 1
    ", [$user_id]);
    
    if ($store && isset($store[0]['id'])) {
        return (int)$store[0]['id'];
    }
    
    return null;
}

function parseProducts($productsString) {
    $items = [];
    
    if (empty($productsString)) {
        return $items;
    }
    
    // Try different delimiters for product parsing
    $productLines = preg_split('/\r\n|\r|\n/', $productsString);
    
    foreach ($productLines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Try to parse product information
        // Format examples: 
        // "Product Name x 2 - 150.00 MAD"
        // "Product Name (SKU123) x 3 @ 50.00"
        // "Product Name | 2 | 75.00"
        
        $product = [
            'name' => $line,
            'sku' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'total_price' => 0,
            'weight' => 0
        ];
        
        // Extract quantity (look for patterns like "x 2", "× 3", "qty: 1")
        if (preg_match('/(?:x|×|qty|quantity)[:\s]*(\d+)/i', $line, $matches)) {
            $product['quantity'] = intval($matches[1]);
        }
        
        // Extract price (look for currency symbols or numbers with decimals)
        if (preg_match('/(\d+[.,]\d+)\s*(?:MAD|DH|€|USD|€)?/i', $line, $matches)) {
            $price = floatval(str_replace(',', '.', $matches[1]));
            $product['unit_price'] = $price / max(1, $product['quantity']);
            $product['total_price'] = $price;
        }
        
        // Extract SKU (look for patterns in parentheses or brackets)
        if (preg_match('/\[([^\]]+)\]/', $line, $matches) || preg_match('/\(([^)]+)\)/', $line, $matches)) {
            $product['sku'] = trim($matches[1]);
            // Remove SKU from product name for cleaner display
            $product['name'] = trim(preg_replace('/\[[^\]]+\]|\([^)]+\)/', '', $product['name']));
        }
        
        $items[] = $product;
    }
    
    return $items;
}

try {
    $db = DB::getInstance();
    $user_id = getCurrentUserId($db);
    
    if (!$user_id) {
        throw new Exception("Unauthorized - Please log in");
    }
    
    // Get user's default store
    $store_id = getUserStoreId($db, $user_id);
    if (!$store_id) {
        throw new Exception("No store found for this user. Please create a store first.");
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['orders']) || !is_array($input['orders'])) {
        throw new Exception("No orders data provided");
    }
    
    $results = [
        'total' => count($input['orders']),
        'imported' => 0,
        'errors' => 0,
        'errorDetails' => []
    ];
    
    foreach ($input['orders'] as $index => $orderData) {
        try {
            // Validate required fields
            if (empty($orderData['order_number']) || empty($orderData['customer_name'])) {
                throw new Exception("Numéro de commande et nom du client sont obligatoires");
            }
            
            // Check if order already exists
            $existing = $db->getThisQuery(
                "SELECT id FROM orders WHERE order_number = ? AND user_id = ?",
                [$orderData['order_number'], $user_id]
            );
            
            if ($existing) {
                throw new Exception("Numéro de commande dupliqué: " . $orderData['order_number']);
            }
            
            // Parse products if provided
            $orderItems = [];
            $productsText = $orderData['products'] ?? '';
            if (!empty($productsText)) {
                $orderItems = parseProducts($productsText);
            }
            
            // Calculate total from items if not provided
            $total_amount = floatval($orderData['total_amount'] ?? 0);
            if ($total_amount == 0 && !empty($orderItems)) {
                foreach ($orderItems as $item) {
                    $total_amount += $item['total_price'];
                }
            }
            
            // Insert order
            $insertData = [
                'store_id' => $store_id,
                'user_id' => $user_id,
                'order_number' => $orderData['order_number'],
                'customer_name' => $orderData['customer_name'],
                'customer_email' => $orderData['customer_email'] ?? '',
                'customer_phone' => $orderData['customer_phone'] ?? '',
                'customer_ville' => $orderData['customer_ville'] ?? $orderData['shipping_city'] ?? '',
                'shipping_address' => $orderData['shipping_address'] ?? '',
                'billing_address' => $orderData['billing_address'] ?? $orderData['shipping_address'] ?? '',
                'total_amount' => $total_amount,
                'currency' => $orderData['currency'] ?? 'MAD',
                'status' => $orderData['status'] ?? 'new-order',
                'shipping_status' => $orderData['shipping_status'] ?? 'pending',
                'payment_status' => $orderData['payment_status'] ?? 'pending',
                'order_date' => !empty($orderData['order_date']) ? date('Y-m-d H:i:s', strtotime($orderData['order_date'])) : date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Insert the order
            $order_id = $db->insert('orders', $insertData);
            
            if (!$order_id) {
                throw new Exception("Erreur lors de la création de la commande");
            }
            
            // Insert order items if products are provided
            if (!empty($orderItems)) {
                foreach ($orderItems as $item) {
                    $itemData = [
                        'order_id' => $order_id,
                        'product_name' => $item['name'],
                        'product_sku' => $item['sku'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['total_price'],
                        'weight' => $item['weight'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->insert('order_items', $itemData);
                }
            }
            
            $results['imported']++;
            
        } catch (Exception $e) {
            $results['errors']++;
            $results['errorDetails'][] = "Ligne " . ($index + 1) . ": " . $e->getMessage();
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'store_id' => $store_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>