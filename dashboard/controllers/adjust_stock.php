<?php
// adjust_stock.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Méthode non autorisée',
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
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['product_id', 'movement_type', 'quantity', 'reason'];
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
    
    // Extract data
    $product_id = intval($input['product_id']);
    $variation_id = isset($input['variation_id']) && !empty($input['variation_id']) 
        ? intval($input['variation_id']) 
        : null;
    $movement_type = $input['movement_type'];
    $quantity = intval($input['quantity']);
    $reason = trim($input['reason']);
    $reference = isset($input['reference']) ? trim($input['reference']) : null;
    $movement_date = isset($input['movement_date']) ? $input['movement_date'] : date('Y-m-d H:i:s');
    
    // Validate movement type
    if (!in_array($movement_type, ['in', 'out', 'adjustment'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Type de mouvement invalide',
            'code' => 'INVALID_MOVEMENT_TYPE'
        ]);
        exit;
    }
    
    // Validate quantity
    if ($quantity <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'La quantité doit être positive',
            'field' => 'quantity',
            'code' => 'INVALID_QUANTITY'
        ]);
        exit;
    }
    
    // Check product ownership
    $product_check = $db->getThisQuery("
        SELECT p.id, p.name, p.sku, p.stock_quantity as product_stock, 
               p.stock_status, p.user_id
        FROM products p
        WHERE p.id = ? AND p.user_id = ?
    ", [$product_id, $user_id]);
    
    if (empty($product_check)) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé ou non autorisé',
            'code' => 'PRODUCT_NOT_FOUND'
        ]);
        exit;
    }
    
    $product = $product_check[0];
    
    // Check variant ownership if applicable
    $variant = null;
    if ($variation_id) {
        $variant_check = $db->getThisQuery("
            SELECT pv.id, pv.sku, pv.stock_quantity, pv.attributes
            FROM product_variants pv
            WHERE pv.id = ? AND pv.product_id = ?
        ", [$variation_id, $product_id]);
        
        if (empty($variant_check)) {
            echo json_encode([
                'success' => false,
                'message' => 'Variant non trouvé',
                'code' => 'VARIANT_NOT_FOUND'
            ]);
            exit;
        }
        
        $variant = $variant_check[0];
    }
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Get current stock quantities
        $current_product_stock = intval($product['product_stock']);
        $current_variant_stock = $variant ? intval($variant['stock_quantity']) : null;
        
        // Calculate new quantities based on movement type
        if ($movement_type === 'adjustment') {
            // For adjustment, quantity is the new total
            $new_variant_stock = $quantity;
            $stock_change = $new_variant_stock - $current_variant_stock;
            $new_product_stock = $current_product_stock + $stock_change;
        } else {
            // For in/out movements
            $stock_change = $movement_type === 'in' ? $quantity : -$quantity;
            $new_variant_stock = $current_variant_stock + $stock_change;
            $new_product_stock = $current_product_stock + $stock_change;
        }
        
        // Ensure stocks don't go negative
        if ($new_variant_stock < 0) {
            $db->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'La quantité en stock ne peut pas être négative',
                'field' => 'quantity',
                'code' => 'NEGATIVE_STOCK'
            ]);
            exit;
        }
        
        if ($new_product_stock < 0) {
            $db->rollback();
            echo json_encode([
                'success' => false,
                'message' => 'Le stock total du produit ne peut pas être négatif',
                'code' => 'NEGATIVE_PRODUCT_STOCK'
            ]);
            exit;
        }
        
        // Update variant stock if applicable
        if ($variant) {
            $variant_update = $db->update('product_variants', $variation_id, [
                'stock_quantity' => $new_variant_stock,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$variant_update) {
                $db->rollback();
                throw new Exception('Échec de la mise à jour du variant');
            }
        }
        
        // Update product stock
        $product_stock_status = getStockStatus($new_product_stock);
        $product_update = $db->update('products', $product_id, [
            'stock_quantity' => $new_product_stock,
            'stock_status' => $product_stock_status,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if (!$product_update) {
            $db->rollback();
            throw new Exception('Échec de la mise à jour du produit');
        }
        
        // Record stock movement
        $movement_data = [
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'movement_type' => $movement_type,
            'quantity' => abs($stock_change),
            'previous_quantity' => $variant ? $current_variant_stock : $current_product_stock,
            'new_quantity' => $variant ? $new_variant_stock : $new_product_stock,
            'reason' => $reason,
            'user_id' => $user_id,
            'reference_type' => 'manual_adjustment',
            'reference_id' => null,
            'created_at' => $movement_date,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($reference) {
            $movement_data['reference'] = $reference;
        }
        
        $movement_result = $db->insert('stock_movements', $movement_data);
        
        if (!$movement_result) {
            $db->rollback();
            throw new Exception('Échec de l\'enregistrement du mouvement');
        }
        
        // Commit transaction
        $db->commit();
        
        // Prepare response
        $response = [
            'success' => true,
            'message' => 'Stock ajusté avec succès',
            'data' => [
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'product' => [
                    'name' => $product['name'],
                    'sku' => $product['sku'],
                    'old_stock' => $current_product_stock,
                    'new_stock' => $new_product_stock,
                    'status' => $product_stock_status
                ],
                'variant' => $variant ? [
                    'sku' => $variant['sku'],
                    'old_stock' => $current_variant_stock,
                    'new_stock' => $new_variant_stock
                ] : null,
                'movement' => [
                    'type' => $movement_type,
                    'quantity' => abs($stock_change),
                    'reason' => $reason,
                    'date' => $movement_date
                ]
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in adjust_stock.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'ajustement du stock',
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
    
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }
    
    $identifier = $_SESSION['user']['username'];
    
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);
    
    if ($user && isset($user[0]['id'])) {
        $_SESSION['user']['id'] = (int)$user[0]['id'];
        return (int)$user[0]['id'];
    }
    
    return null;
}

/**
 * Determine stock status based on quantity
 */
function getStockStatus($quantity) {
    if ($quantity <= 0) {
        return 'out_of_stock';
    } elseif ($quantity <= 10) {
        return 'low_stock';
    } else {
        return 'in_stock';
    }
} ?>