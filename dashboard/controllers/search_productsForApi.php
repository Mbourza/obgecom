<?php
// search_products.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Get current user ID
$user_id = getCurrentUserId($db);

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non authentifié',
        'code' => 'UNAUTHENTICATED'
    ]);
    exit;
}

try {
    // Get search query
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $include_variants = isset($_GET['include_variants']) ? filter_var($_GET['include_variants'], FILTER_VALIDATE_BOOLEAN) : true;
    
    if (strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'products' => [],
            'message' => 'Tapez au moins 2 caractères'
        ]);
        exit;
    }
    
    if ($include_variants) {
        // Search both products and variants
        $search_sql = "
            SELECT 
                p.id,
                p.name,
                p.description,
                p.sku as parent_sku,
                p.price,
                p.cost_price,
                p.stock_quantity,
                p.stock_status,
                p.category,
                p.tags,
                p.images,
                p.type,
                NULL as variant_id,
                NULL as variant_name,
                NULL as variant_sku,
                NULL as variant_attributes,
                NULL as variant_price,
                NULL as variant_cost_price,
                NULL as variant_stock_quantity,
                'product' as item_type
            FROM products p
            WHERE p.user_id = ?
            AND p.status = 'active'
            AND (
                p.name LIKE ? 
                OR p.sku LIKE ? 
                OR p.tags LIKE ?
                OR p.description LIKE ?
                OR p.external_product_id LIKE ?
            )
            
            UNION ALL
            
            SELECT 
                p.id,
                p.name,
                p.description,
                p.sku as parent_sku,
                COALESCE(pv.price, p.price) as price,
                COALESCE(pv.cost_price, p.cost_price) as cost_price,
                pv.stock_quantity,
                CASE 
                    WHEN pv.stock_quantity <= 0 THEN 'out_of_stock'
                    WHEN pv.stock_quantity <= 10 THEN 'low_stock'
                    ELSE 'in_stock'
                END as stock_status,
                p.category,
                p.tags,
                p.images,
                p.type,
                pv.id as variant_id,
                pv.variant_name,
                pv.sku as variant_sku,
                pv.attributes as variant_attributes,
                pv.price as variant_price,
                pv.cost_price as variant_cost_price,
                pv.stock_quantity as variant_stock_quantity,
                'variant' as item_type
            FROM products p
            INNER JOIN product_variants pv ON p.id = pv.product_id
            WHERE p.user_id = ?
            AND p.status = 'active'
            AND (
                pv.sku LIKE ? 
                OR pv.variant_name LIKE ? 
                OR pv.attributes LIKE ?
                OR p.name LIKE ?
            )
            
            ORDER BY 
                CASE 
                    WHEN item_type = 'product' THEN 0 
                    ELSE 1 
                END,
                name ASC
            LIMIT ?
        ";
        
        $search_param = "%{$query}%";
        $params = [
            $user_id, $search_param, $search_param, $search_param, $search_param, $search_param,
            $user_id, $search_param, $search_param, $search_param, $search_param,
            $limit
        ];
    } else {
        // Search only products
        $search_sql = "
            SELECT 
                p.id,
                p.name,
                p.description,
                p.sku,
                p.price,
                p.cost_price,
                p.stock_quantity,
                p.stock_status,
                p.category,
                p.tags,
                p.images,
                p.type,
                'product' as item_type
            FROM products p
            WHERE p.user_id = ?
            AND p.status = 'active'
            AND (
                p.name LIKE ? 
                OR p.sku LIKE ? 
                OR p.tags LIKE ?
                OR p.description LIKE ?
                OR p.external_product_id LIKE ?
            )
            ORDER BY name ASC
            LIMIT ?
        ";
        
        $search_param = "%{$query}%";
        $params = [
            $user_id, $search_param, $search_param, $search_param, $search_param, $search_param,
            $limit
        ];
    }
    
    $results = $db->getThisQuery($search_sql, $params);
    
    // Format results for frontend
    $products = [];
    foreach ($results as $row) {
        $product = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'] ?? '',
            'sku' => $row['item_type'] === 'product' ? $row['parent_sku'] ?? $row['sku'] : $row['variant_sku'],
            'category' => $row['category'],
            'stock_quantity' => intval($row['stock_quantity'] ?? $row['variant_stock_quantity'] ?? 0),
            'price' => floatval($row['price']),
            'cost_price' => floatval($row['cost_price'] ?? $row['variant_cost_price'] ?? 0),
            'stock_status' => $row['stock_status'],
            'tags' => $row['tags'],
            'images' => $row['images'] ? json_decode($row['images'], true) : [],
            'type' => $row['type'],
            'item_type' => $row['item_type']
        ];
        
        // Add variant-specific information
        if ($row['item_type'] === 'variant') {
            $product['variant_id'] = $row['variant_id'];
            $product['variant_name'] = $row['variant_name'];
            $product['parent_sku'] = $row['parent_sku'];
            $product['variant_attributes'] = $row['variant_attributes'] ? json_decode($row['variant_attributes'], true) : [];
            $product['variant_price'] = floatval($row['variant_price'] ?? $row['price']);
            $product['variant_cost_price'] = floatval($row['variant_cost_price'] ?? $row['cost_price']);
        }
        
        // Determine stock display text
        $stock_quantity = $product['stock_quantity'];
        if ($stock_quantity <= 0) {
            $product['stock_display'] = 'Rupture';
            $product['stock_class'] = 'out-of-stock';
        } elseif ($stock_quantity <= 10) {
            $product['stock_display'] = 'Faible';
            $product['stock_class'] = 'low-stock';
        } else {
            $product['stock_display'] = 'En stock';
            $product['stock_class'] = 'in-stock';
        }
        
        $products[] = $product;
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products),
        'query' => $query
    ]);
    
} catch (Exception $e) {
    error_log("Error in search_products.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la recherche',
        'error' => $e->getMessage(),
        'code' => 'SEARCH_ERROR'
    ]);
}

/**
 * Get current user ID from session
 */
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }
    
    // Try to get ID from session first
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }
    
    // Otherwise, query the database
    $identifier = $_SESSION['user']['username'];
    
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);
    
    if ($user && isset($user[0]['id'])) {
        // Store in session for future use
        $_SESSION['user']['id'] = (int)$user[0]['id'];
        return (int)$user[0]['id'];
    }
    
    return null;
}