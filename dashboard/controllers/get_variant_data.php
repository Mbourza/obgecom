<?php
// get_variant_data.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

// Get the variant ID from request
$variant_id = isset($_GET['variant_id']) ? intval($_GET['variant_id']) : 0;
$view_type = isset($_GET['view']) ? trim($_GET['view']) : 'details';

if ($variant_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de variante invalide',
        'code' => 'INVALID_ID'
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
    // Query to get variant data with product information
    $sql = "
        SELECT 
            pv.*,
            p.name as product_name,
            p.id as product_id,
            p.images as product_image,
            p.description as product_description
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        WHERE pv.id = ? AND p.user_id = ?
    ";
    
    $result = $db->getThisQuery($sql, [$variant_id, $user_id]);

    
    if (empty($result)) {
        echo json_encode([
            'success' => false,
            'message' => 'Variante non trouvée ou non autorisée',
            'code' => 'VARIANT_NOT_FOUND'
        ]);
        exit;
    }
    
    $variant = $result[0];
    
    // Decode attributes if they exist
    if (!empty($variant['attributes'])) {
        $attributes = json_decode($variant['attributes'], true);
        $variant['attributes'] = is_array($attributes) ? $attributes : [];
    } else {
        $variant['attributes'] = [];
    }
    
    // Get additional data based on view type
    $additional_data = [];
    
    if ($view_type === 'full' || $view_type === 'details') {
        // Get sales data for this variant
        $sales_sql = "
            SELECT 
                COUNT(*) as total_sales,
                SUM(quantity) as total_units_sold,
                SUM(total_amount) as total_revenue
            FROM order_items
            WHERE variant_id = ?
        ";
        
        $sales_data = $db->getThisQuery($sales_sql, [$variant_id]);
        $additional_data['sales'] = $sales_data[0] ?? [
            'total_sales' => 0,
            'total_units_sold' => 0,
            'total_revenue' => 0
        ];
        
        // Get stock alerts if any
        if ($variant['stock_quantity'] <= $variant['min_stock_threshold']) {
            $additional_data['stock_alert'] = [
                'type' => 'low_stock',
                'message' => 'Stock faible - ' . $variant['stock_quantity'] . ' unités restantes',
                'severity' => $variant['stock_quantity'] == 0 ? 'critical' : 'warning'
            ];
        } elseif ($variant['stock_quantity'] >= $variant['max_stock_threshold']) {
            $additional_data['stock_alert'] = [
                'type' => 'over_stock',
                'message' => 'Stock élevé - ' . $variant['stock_quantity'] . ' unités en stock',
                'severity' => 'info'
            ];
        }
    }
    
    if ($view_type === 'full') {
        // Get related variants from same product
        $related_variants_sql = "
            SELECT 
                id, 
                sku, 
                variant_name,
                price,
                stock_quantity,
                status
            FROM product_variants
            WHERE product_id = ? AND id != ?
            ORDER BY created_at DESC
            LIMIT 5
        ";
        
        $related_variants = $db->getThisQuery($related_variants_sql, [$variant['product_id'], $variant_id]);
        $additional_data['related_variants'] = $related_variants;
        
        // Get price history if available (you might need a separate table for this)
        $price_history_sql = "
            SELECT 
                price,
                compare_price,
                cost_price,
                updated_at as date
            FROM price_history
            WHERE variant_id = ?
            ORDER BY updated_at DESC
            LIMIT 10
        ";
        
        // Check if price_history table exists
        $table_exists = $db->getThisQuery("SHOW TABLES LIKE 'price_history'");
        if (!empty($table_exists)) {
            $price_history = $db->getThisQuery($price_history_sql, [$variant_id]);
            $additional_data['price_history'] = $price_history;
        }
        
        // Get stock history if available
        $stock_history_sql = "
            SELECT 
                stock_quantity,
                change_type,
                change_reason,
                changed_at as date
            FROM stock_history
            WHERE variant_id = ?
            ORDER BY changed_at DESC
            LIMIT 10
        ";
        
        $table_exists = $db->getThisQuery("SHOW TABLES LIKE 'stock_history'");
        if (!empty($table_exists)) {
            $stock_history = $db->getThisQuery($stock_history_sql, [$variant_id]);
            $additional_data['stock_history'] = $stock_history;
        }
    }
    
    // Format dates for display
    $variant['created_at_formatted'] = date('d/m/Y H:i', strtotime($variant['created_at']));
    $variant['updated_at_formatted'] = date('d/m/Y H:i', strtotime($variant['updated_at']));
    
    // Calculate profit margin
    if ($variant['cost_price'] > 0 && $variant['price'] > 0) {
        $profit = $variant['price'] - $variant['cost_price'];
        $margin = ($profit / $variant['price']) * 100;
        $variant['profit_margin'] = round($margin, 2);
        $variant['profit_amount'] = $profit;
    } else {
        $variant['profit_margin'] = null;
        $variant['profit_amount'] = null;
    }
    
    // Prepare response data
    $response_data = [
        'success' => true,
        'data' => array_merge($variant, $additional_data)
    ];
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    error_log("Error in get_variant_data.php: " . $e->getMessage());
    
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