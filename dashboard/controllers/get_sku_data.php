<?php
// get_sku_data.php
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
    // Get SKU ID from request
    $sku_id = isset($_GET['sku_id']) ? intval($_GET['sku_id']) : 0;
    
    if ($sku_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID SKU invalide',
            'code' => 'INVALID_SKU_ID'
        ]);
        exit;
    }
    
    // Query SKU data with product info
    $sku_data = $db->getThisQuery("
        SELECT 
            pv.id,
            pv.product_id,
            pv.sku,
            pv.variant_name,
            pv.attributes,
            pv.price,
            pv.compare_price,
            pv.cost_price,
            pv.stock_quantity,
            pv.min_stock_threshold,
            pv.max_stock_threshold,
            pv.status,
            pv.created_at,
            pv.updated_at,
            p.name as product_name,
            p.sku as parent_sku
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        WHERE pv.id = ? AND p.user_id = ?
    ", [$sku_id, $user_id]);
    
    if (empty($sku_data)) {
        echo json_encode([
            'success' => false,
            'message' => 'SKU non trouvé ou non autorisé',
            'code' => 'SKU_NOT_FOUND'
        ]);
        exit;
    }
    
    $sku = $sku_data[0];
    
    // Parse attributes
    $attributes = [];
    if ($sku['attributes'] && is_string($sku['attributes'])) {
        try {
            $attributes = json_decode($sku['attributes'], true);
        } catch (Exception $e) {
            $attributes = [];
        }
    }
    $sku['attributes'] = $attributes;
    
    // Format dates
    $sku['created_at_formatted'] = date('d/m/Y H:i', strtotime($sku['created_at']));
    $sku['updated_at_formatted'] = date('d/m/Y H:i', strtotime($sku['updated_at']));
    
    // Calculate stock level
    $stock_quantity = $sku['stock_quantity'] ?? 0;
    $max_threshold = $sku['max_stock_threshold'] ?? 100;
    
    if ($stock_quantity === 0) {
        $stock_level = 'out';
    } else {
        $percentage = ($stock_quantity / $max_threshold) * 100;
        if ($percentage <= 25) $stock_level = 'low';
        elseif ($percentage <= 50) $stock_level = 'medium';
        elseif ($percentage <= 75) $stock_level = 'high';
        else $stock_level = 'full';
    }
    
    $sku['stock_level'] = $stock_level;
    
    // Calculate margin if cost price exists
    if ($sku['cost_price'] && $sku['cost_price'] > 0) {
        $sku['margin_amount'] = $sku['price'] - $sku['cost_price'];
        $sku['margin_percentage'] = $sku['margin_amount'] / $sku['price'] * 100;
    } else {
        $sku['margin_amount'] = 0;
        $sku['margin_percentage'] = 0;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Données SKU récupérées avec succès',
        'data' => $sku
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_sku_data.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la récupération des données',
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
} ?>