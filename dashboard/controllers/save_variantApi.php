<?php
// save_skuApi.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Méthode non autorisée. Utilisez POST.',
        'code' => 'INVALID_METHOD'
    ]);
    exit;
}

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
    // Get and validate input data
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    // Required fields validation
    $required_fields = ['product_id', 'sku', 'price', 'stock_quantity'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            echo json_encode([
                'success' => false,
                'message' => "Le champ '$field' est requis",
                'field' => $field,
                'code' => 'VALIDATION_ERROR'
            ]);
            exit;
        }
    }
    
    // Extract and sanitize data
    $sku_id = isset($input['sku_id']) ? intval($input['sku_id']) : 0;
    $product_id = intval($input['product_id']);
    $sku = trim($input['sku']);
    $variant_name = isset($input['variant_name']) ? trim($input['variant_name']) : null;
    $status = in_array($input['status'] ?? 'active', ['active', 'inactive', 'disabled']) 
        ? $input['status'] 
        : 'active';
    
    // Validate numeric fields
    $price = filter_var($input['price'], FILTER_VALIDATE_FLOAT);
    if ($price === false || $price < 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Prix invalide',
            'field' => 'price',
            'code' => 'VALIDATION_ERROR'
        ]);
        exit;
    }
    
    $compare_price = isset($input['compare_price']) && $input['compare_price'] !== '' 
        ? filter_var($input['compare_price'], FILTER_VALIDATE_FLOAT) 
        : null;
    
    $cost_price = isset($input['cost_price']) && $input['cost_price'] !== '' 
        ? filter_var($input['cost_price'], FILTER_VALIDATE_FLOAT) 
        : null;
    
    $new_stock_quantity = intval($input['stock_quantity']);
    $min_stock_threshold = isset($input['min_stock_threshold']) 
        ? intval($input['min_stock_threshold']) 
        : 10;
    $max_stock_threshold = isset($input['max_stock_threshold']) 
        ? intval($input['max_stock_threshold']) 
        : 100;
    
    // Validate stock thresholds
    if ($min_stock_threshold < 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Le seuil minimum doit être positif',
            'field' => 'min_stock_threshold',
            'code' => 'VALIDATION_ERROR'
        ]);
        exit;
    }
    
    if ($max_stock_threshold <= $min_stock_threshold) {
        echo json_encode([
            'success' => false,
            'message' => 'Le seuil maximum doit être supérieur au seuil minimum',
            'field' => 'max_stock_threshold',
            'code' => 'VALIDATION_ERROR'
        ]);
        exit;
    }
    
    // Process attributes
    $attributes = [];
    if (isset($input['attributes']) && is_array($input['attributes'])) {
        foreach ($input['attributes'] as $key => $value) {
            if (!empty(trim($key)) && !empty(trim($value))) {
                $attributes[trim($key)] = trim($value);
            }
        }
    }
    
    // Check if product belongs to user
    $product_check = $db->getThisQuery("
        SELECT id, name, stock_quantity 
        FROM products 
        WHERE id = ? AND user_id = ?
    ", [$product_id, $user_id]);
    
    if (empty($product_check)) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé ou non autorisé',
            'code' => 'PRODUCT_NOT_FOUND'
        ]);
        exit;
    }
    
    $current_product_stock = intval($product_check[0]['stock_quantity']);
    
    // Check SKU uniqueness (excluding current SKU if editing)
    $sku_check_sql = "
        SELECT pv.id, pv.sku, pv.stock_quantity as current_sku_quantity
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        WHERE pv.sku = ? AND p.user_id = ?
    ";
    
    $sku_check_params = [$sku, $user_id];
    
    if ($sku_id > 0) {
        $sku_check_sql .= " AND pv.id != ?";
        $sku_check_params[] = $sku_id;
    }
    
    $existing_sku = $db->getThisQuery($sku_check_sql, $sku_check_params);
    
    if (!empty($existing_sku)) {
        echo json_encode([
            'success' => false,
            'message' => 'Ce SKU existe déjà. Veuillez en choisir un autre.',
            'field' => 'sku',
            'code' => 'SKU_EXISTS'
        ]);
        exit;
    }
    
    // Prepare data for database
    $sku_data = [
        'product_id' => $product_id,
        'sku' => $sku,
        'variant_name' => $variant_name,
        'attributes' => !empty($attributes) ? json_encode($attributes, JSON_UNESCAPED_UNICODE) : null,
        'price' => $price,
        'compare_price' => $compare_price,
        'cost_price' => $cost_price,
        'stock_quantity' => $new_stock_quantity,
        'min_stock_threshold' => $min_stock_threshold,
        'max_stock_threshold' => $max_stock_threshold,
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Start transaction for data consistency
    $db->beginTransaction();
    
    try {
        $old_stock_quantity = 0;
        $is_new_sku = ($sku_id == 0);
        
        if ($sku_id > 0) {
            // Get current SKU quantity before update
            $current_sku = $db->getThisQuery("
                SELECT stock_quantity 
                FROM product_variants 
                WHERE id = ? AND product_id = ?
            ", [$sku_id, $product_id]);
            
            if (!empty($current_sku)) {
                $old_stock_quantity = intval($current_sku[0]['stock_quantity']);
            }
            
            // Check SKU ownership
            $sku_ownership_check = $db->getThisQuery("
                SELECT pv.id 
                FROM product_variants pv
                JOIN products p ON pv.product_id = p.id
                WHERE pv.id = ? AND p.user_id = ?
            ", [$sku_id, $user_id]);
            
            if (empty($sku_ownership_check)) {
                $db->rollback();
                echo json_encode([
                    'success' => false,
                    'message' => 'SKU non trouvé ou non autorisé',
                    'code' => 'SKU_NOT_FOUND'
                ]);
                exit;
            }
            
            // Update SKU
            $result = $db->update('product_variants', $sku_id, $sku_data);
            $action = 'updated';
        } else {
            // Create new SKU
            $sku_data['created_at'] = date('Y-m-d H:i:s');
            $result = $db->insert('product_variants', $sku_data);
            $sku_id = $db->getLastInsertId();
            $action = 'created';
        }
        
        if (!$result) {
            $db->rollback();
            echo json_encode([
                'success' => false,
                'message' => $action === 'created' ? 'Échec de la création du SKU' : 'Échec de la mise à jour du SKU',
                'code' => $action === 'created' ? 'CREATE_FAILED' : 'UPDATE_FAILED'
            ]);
            exit;
        }
        
        // Calculate product stock changes
        if ($is_new_sku) {
            // For new SKU: add its quantity to product total
            $product_stock_change = $new_stock_quantity;
        } else {
            // For update: calculate difference
            $product_stock_change = $new_stock_quantity - $old_stock_quantity;
        }
        
        // Update product total stock quantity
        $new_product_stock_quantity = $current_product_stock + $product_stock_change;
        
        // Ensure product stock doesn't go negative
        if ($new_product_stock_quantity < 0) {
            $db->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'La quantité en stock ne peut pas être négative',
                'code' => 'NEGATIVE_STOCK'
            ]);
            exit;
        }
        
        // Update product stock quantity and status
        $stock_status = ($new_product_stock_quantity > 0) ? 'in_stock' : 'out_of_stock';
        
        $product_update = $db->update('products', $product_id, [
            'stock_quantity' => $new_product_stock_quantity,
            'stock_status' => $stock_status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$product_update) {
            $db->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du stock du produit',
                'code' => 'PRODUCT_UPDATE_FAILED'
            ]);
            exit;
        }
        
        // Record stock movement
        $movement_data = [
            'product_id' => $product_id,
            'variation_id' => $sku_id,
            'movement_type' => $is_new_sku ? 'in' : 'adjustment',
            'quantity' => $product_stock_change,
            'previous_quantity' => $is_new_sku ? 0 : $old_stock_quantity,
            'new_quantity' => $new_stock_quantity,
            'reason' => $is_new_sku ? 'Création de Variant' : 'Mise à jour de Variant',
            'user_id' => $user_id,
            'reference_type' => 'sku_' . $action,
            'reference_id' => $sku_id,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $stock_movement_result = $db->insert('stock_movements', $movement_data);
        
        if (!$stock_movement_result) {
            $db->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du mouvement de stock',
                'code' => 'STOCK_MOVEMENT_FAILED'
            ]);
            exit;
        }
        
        // Commit all changes
        $db->commit();
        
        // Prepare response
        $response_data = [
            'success' => true,
            'message' => $action === 'created' ? 'SKU créé avec succès' : 'SKU mis à jour avec succès',
            'action' => $action,
            'sku_id' => $sku_id,
            'sku' => $sku,
            'stock_info' => [
                'sku_quantity' => $new_stock_quantity,
                'product_total_quantity' => $new_product_stock_quantity,
                'stock_change' => $product_stock_change
            ],
            'data' => [
                'sku' => $sku,
                'variant_name' => $variant_name,
                'price' => $price,
                'stock_quantity' => $new_stock_quantity,
                'status' => $status
            ]
        ];
        
        echo json_encode($response_data);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in save_skuApi.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors du traitement de la requête',
        'error' => $e->getMessage(),
        'code' => 'INTERNAL_ERROR'
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

/**
 * Helper function to validate SKU format
*/
function validateSKU($sku) {
    // Basic validation - can be customized based on your requirements
    if (empty($sku)) {
        return false;
    }
    
    // Check length (example: 3-50 characters)
    if (strlen($sku) < 3 || strlen($sku) > 50) {
        return false;
    }
    
    // Check for invalid characters (only alphanumeric, dash, underscore)
    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $sku)) {
        return false;
    }
    
    return true;
}
?>