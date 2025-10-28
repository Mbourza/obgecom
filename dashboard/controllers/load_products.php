<?php

if(file_exists("../core/init.php")) {
    require_once("../core/init.php");
}
// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$db = DB::getInstance();

try {
    // Get user ID from session (if products are user-specific)
    $user_id = getCurrentUserId($db);
    
    // Base query to get products from database
    $query = "SELECT 
                id, store_id, user_id, external_product_id, 
                name, description, short_description, sku, 
                price, regular_price, sale_price, compare_price, cost_price, 
                stock_quantity, stock_status, weight, length, width, height, 
                status, featured, category, tags, images, variants, type, 
                platform, created_at, updated_at
              FROM products
              WHERE 1=1";
    $params = [];
    
    // Add user filter if products are user-specific
    if ($user_id) {
        $query .= " AND user_id = ?";
        $params[] = $user_id;
    }
    
    // Handle optional filters from GET parameters
    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $allowed_statuses = ['active', 'draft', 'archived'];
        $status = trim($_GET['status']);
        if (in_array($status, $allowed_statuses)) {
            $query .= " AND status = ?";
            $params[] = $status;
        }
    }
    
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = trim($_GET['search']);
        $query .= " AND (name LIKE ? OR sku LIKE ? OR description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    // Add ordering
    $query .= " ORDER BY name ASC";
    
    // Execute query
    $products = $db->getThisQuery($query, $params);
    
    // Format products data to match your JavaScript structure
    $formatted_products = [];
    
    if (!empty($products) && is_array($products)) {
        foreach ($products as $product) {
            // Handle images - assuming 'images' is a JSON string in the database
            $images = [];

            if (!empty($product['images'])) {
                try {
                    $images = json_decode($product['images'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $images = [];
                    }
                } catch (Exception $e) {
                    $images = [];
                }
            }
            
            // Get first image URL or use default
            $image_url = 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=150&h=150&fit=crop';
            if (!empty($images) && isset($images[0]['src'])) {
                $image_url = $images[0]['src'];
            }
            
            $formatted_products[] = [
                'id' => (int)($product['id'] ?? 0),
                'store_id' => (int)($product['store_id'] ?? 0),
                'user_id' => (int)($product['user_id'] ?? 0),
                'external_product_id' => $product['external_product_id'] ?? '',
                'name' => $product['name'] ?? '',
                'description' => $product['description'] ?? '',
                'short_description' => $product['short_description'] ?? '',
                'sku' => $product['sku'] ?? '',
                'price' => isset($product['price']) ? (float)$product['price'] : 0,
                'regular_price' => isset($product['regular_price']) ? (float)$product['regular_price'] : 0,
                'sale_price' => isset($product['sale_price']) ? (float)$product['sale_price'] : 0,
                'compare_price' => isset($product['compare_price']) ? (float)$product['compare_price'] : 0,
                'cost_price' => isset($product['cost_price']) ? (float)$product['cost_price'] : 0,
                'quantity' => isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : 0,
                'stock_status' => $product['stock_status'] ?? 'unknown',
                'weight' => isset($product['weight']) ? (float)$product['weight'] : 0,
                'dimensions' => [
                    'length' => isset($product['length']) ? (float)$product['length'] : 0,
                    'width' => isset($product['width']) ? (float)$product['width'] : 0,
                    'height' => isset($product['height']) ? (float)$product['height'] : 0,
                ],
                'status' => $product['status'] ?? 'unknown',
                'featured' => (bool)($product['featured'] ?? false),
                'category' => $product['category'] ?? '',
                'tags' => $product['tags'] ?? '',
                'images' => $images,
                'variants' => !empty($product['variants']) ? json_decode($product['variants'], true) : [],
                'type' => $product['type'] ?? 'simple',
                'platform' => $product['platform'] ?? '',
                'created_at' => $product['created_at'] ?? '',
                'updated_at' => $product['updated_at'] ?? '',
                'image_url' => $image_url
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'products' => $formatted_products,
        'total' => count($formatted_products)
    ]);
    
} catch (Exception $e) {
    error_log("Error in load_products.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading products',
        'products' => []
    ]);
}

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
} ?>