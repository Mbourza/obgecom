<?php 
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

$db = DB::getInstance();

// Get current user ID
$user_id = getCurrentUserId($db);

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Utilisateur non authentifié'
    ]);
    exit;
}

try {
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['start_date']) || !isset($input['end_date'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Les dates sont requises'
        ]);
        exit;
    }
    
    // Extract data
    $start_date = $input['start_date'];
    $end_date = $input['end_date'];
    $category = isset($input['category']) && !empty($input['category']) ? $input['category'] : null;
    $stock_status = isset($input['stock_status']) && !empty($input['stock_status']) ? $input['stock_status'] : null;
    
    // Validate dates
    if (new DateTime($start_date) > new DateTime($end_date)) {
        echo json_encode([
            'success' => false,
            'message' => 'La date de début doit être antérieure à la date de fin'
        ]);
        exit;
    }
    
    // Fix 1: First get all products for the user
    $params = [$user_id];
    $where_conditions = ["p.user_id = ?"];
    
    if ($category) {
        $where_conditions[] = "p.category = ?";
        $params[] = $category;
    }
    
    // Get basic product information
    $query = "
        SELECT 
            p.id as product_id,
            p.name as product_name,
            p.sku as product_sku,
            p.category,
            p.sale_price,
            p.stock_quantity as product_stock,
            p.stock_status
        FROM products p
        WHERE " . implode(" AND ", $where_conditions) . "
        ORDER BY p.name
    ";
    
    $products = $db->getThisQuery($query, $params);
    
    // If no products found
    if (empty($products)) {
        echo json_encode([
            'success' => true,
            'summary' => [
                'total_products' => 0,
                'total_value' => 0,
                'in_stock' => 0,
                'low_stock' => 0,
                'out_of_stock' => 0,
                'total_incoming' => 0,
                'total_outgoing' => 0,
                'net_change' => 0,
                'total_quantity' => 0,
                'avg_unit_value' => 0,
                'out_of_stock_rate' => 0
            ],
            'details' => [],
            'charts' => [
                'categories' => [],
                'top_products' => [],
                'movements' => []
            ],
            'filters' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'category' => $category,
                'stock_status' => $stock_status
            ]
        ]);
        exit;
    }
    
    // Calculate summary statistics
    $summary = [
        'total_products' => 0,
        'total_value' => 0,
        'in_stock' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0,
        'total_incoming' => 0,
        'total_outgoing' => 0
    ];
    
    $details = [];
    $categories = [];
    $top_products = [];
    
    foreach ($products as $product) {
        // Get product variants
        $variants = $db->getThisQuery("
            SELECT 
                id as variant_id,
                sku as variant_sku,
                stock_quantity as variant_stock,
                attributes as variant_attributes,
                price as variant_price
            FROM product_variants 
            WHERE product_id = ? AND user_id = ?
        ", [$product['product_id'], $user_id]);
        
        // If no variants, treat product as single item
        if (empty($variants)) {
            // Get initial stock (stock before start date)
            $initial_stock_query = "
                SELECT new_quantity 
                FROM stock_movements 
                WHERE product_id = ? 
                    AND variation_id IS NULL
                    AND created_at < ?
                ORDER BY created_at DESC 
                LIMIT 1
            ";
            
            $initial_stock_result = $db->getThisQuery($initial_stock_query, [
                $product['product_id'], 
                $start_date . ' 00:00:00'
            ]);
            
            $initial_stock = $initial_stock_result ? (int)$initial_stock_result[0]['new_quantity'] : (int)$product['product_stock'];
            
            // Get incoming movements during period
            $incoming_query = "
                SELECT SUM(quantity) as total_incoming
                FROM stock_movements 
                WHERE product_id = ? 
                    AND variation_id IS NULL
                    AND movement_type IN ('in', 'adjustment')
                    AND created_at BETWEEN ? AND ?
            ";
            
            $incoming_result = $db->getThisQuery($incoming_query, [
                $product['product_id'],
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            ]);
            
            $incoming_quantity = $incoming_result ? (int)$incoming_result[0]['total_incoming'] : 0;
            
            // Get outgoing movements during period
            $outgoing_query = "
                SELECT SUM(quantity) as total_outgoing
                FROM stock_movements 
                WHERE product_id = ? 
                    AND variation_id IS NULL
                    AND movement_type = 'out'
                    AND created_at BETWEEN ? AND ?
            ";
            
            $outgoing_result = $db->getThisQuery($outgoing_query, [
                $product['product_id'],
                $start_date . ' 00:00:00',
                $end_date . ' 23:59:59'
            ]);
            
            $outgoing_quantity = $outgoing_result ? (int)$outgoing_result[0]['total_outgoing'] : 0;
            
            // Calculate final stock for period
            $final_stock = $initial_stock + $incoming_quantity - $outgoing_quantity;
            
            // Calculate product value (use sale_price, fallback to product price)
            $price = $product['sale_price'] ?: 0;
            $product_value = $final_stock * $price;
            
            // Add product to details
            $details[] = [
                'product_name' => $product['product_name'],
                'product_sku' => $product['product_sku'],
                'variant_name' => null,
                'variant_attributes' => null,
                'category' => $product['category'],
                'initial_stock' => $initial_stock,
                'incoming_quantity' => $incoming_quantity,
                'outgoing_quantity' => $outgoing_quantity,
                'final_stock' => $final_stock,
                'unit_price' => $price,
                'total_value' => $product_value,
                'stock_status' => getStockStatusFromQuantity($final_stock)
            ];
            
            // Update summary
            $summary['total_products']++;
            $summary['total_value'] += $product_value;
            $summary['total_incoming'] += $incoming_quantity;
            $summary['total_outgoing'] += $outgoing_quantity;
            
            // Update stock status counts
            if ($final_stock <= 0) {
                $summary['out_of_stock']++;
            } elseif ($final_stock <= 10) {
                $summary['low_stock']++;
            } else {
                $summary['in_stock']++;
            }
            
            // Build category data
            $cat = $product['category'] ?: 'Non catégorisé';
            if (!isset($categories[$cat])) {
                $categories[$cat] = 0;
            }
            $categories[$cat] += $product_value;
            
            // Add to top products
            $top_products[] = [
                'name' => $product['product_name'],
                'value' => $product_value
            ];
            
        } else {
            // Handle products with variants
            foreach ($variants as $variant) {
                // Get initial stock for variant
                $initial_stock_query = "
                    SELECT new_quantity 
                    FROM stock_movements 
                    WHERE product_id = ? 
                        AND variation_id = ?
                        AND created_at < ?
                    ORDER BY created_at DESC 
                    LIMIT 1
                ";
                
                $initial_stock_result = $db->getThisQuery($initial_stock_query, [
                    $product['product_id'], 
                    $variant['variant_id'],
                    $start_date . ' 00:00:00'
                ]);
                
                $initial_stock = $initial_stock_result ? (int)$initial_stock_result[0]['new_quantity'] : (int)$variant['variant_stock'];
                
                // Get incoming movements for variant
                $incoming_query = "
                    SELECT SUM(quantity) as total_incoming
                    FROM stock_movements 
                    WHERE product_id = ? 
                        AND variation_id = ?
                        AND movement_type IN ('in', 'adjustment')
                        AND created_at BETWEEN ? AND ?
                ";
                
                $incoming_result = $db->getThisQuery($incoming_query, [
                    $product['product_id'],
                    $variant['variant_id'],
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                ]);
                
                $incoming_quantity = $incoming_result ? (int)$incoming_result[0]['total_incoming'] : 0;
                
                // Get outgoing movements for variant
                $outgoing_query = "
                    SELECT SUM(quantity) as total_outgoing
                    FROM stock_movements 
                    WHERE product_id = ? 
                        AND variation_id = ?
                        AND movement_type = 'out'
                        AND created_at BETWEEN ? AND ?
                ";
                
                $outgoing_result = $db->getThisQuery($outgoing_query, [
                    $product['product_id'],
                    $variant['variant_id'],
                    $start_date . ' 00:00:00',
                    $end_date . ' 23:59:59'
                ]);
                
                $outgoing_quantity = $outgoing_result ? (int)$outgoing_result[0]['total_outgoing'] : 0;
                
                // Calculate final stock for variant
                $final_stock = $initial_stock + $incoming_quantity - $outgoing_quantity;
                
                // Calculate variant value
                $price = $variant['variant_price'] ?: $product['sale_price'] ?: 0;
                $variant_value = $final_stock * $price;
                
                // Parse variant attributes
                $attributes = '';
                if ($variant['variant_attributes']) {
                    $attrs = json_decode($variant['variant_attributes'], true);
                    if ($attrs) {
                        $attribute_parts = [];
                        foreach ($attrs as $key => $value) {
                            $attribute_parts[] = "$key: $value";
                        }
                        $attributes = implode(', ', $attribute_parts);
                    }
                }
                
                // Add variant to details
                $details[] = [
                    'product_name' => $product['product_name'],
                    'product_sku' => $product['product_sku'],
                    'variant_name' => $variant['variant_sku'] ?: "Variant",
                    'variant_attributes' => $attributes,
                    'category' => $product['category'],
                    'initial_stock' => $initial_stock,
                    'incoming_quantity' => $incoming_quantity,
                    'outgoing_quantity' => $outgoing_quantity,
                    'final_stock' => $final_stock,
                    'unit_price' => $price,
                    'total_value' => $variant_value,
                    'stock_status' => getStockStatusFromQuantity($final_stock)
                ];
                
                // Update summary
                $summary['total_products']++;
                $summary['total_value'] += $variant_value;
                $summary['total_incoming'] += $incoming_quantity;
                $summary['total_outgoing'] += $outgoing_quantity;
                
                // Update stock status counts
                if ($final_stock <= 0) {
                    $summary['out_of_stock']++;
                } elseif ($final_stock <= 10) {
                    $summary['low_stock']++;
                } else {
                    $summary['in_stock']++;
                }
                
                // Build category data
                $cat = $product['category'] ?: 'Non catégorisé';
                if (!isset($categories[$cat])) {
                    $categories[$cat] = 0;
                }
                $categories[$cat] += $variant_value;
                
                // Add to top products
                $top_products[] = [
                    'name' => $product['product_name'] . ($variant['variant_sku'] ? ' (' . $variant['variant_sku'] . ')' : ''),
                    'value' => $variant_value
                ];
            }
        }
    }
    
    // Apply stock status filter if specified
    if ($stock_status && $stock_status !== 'all') {
        $filtered_details = [];
        $filtered_top_products = [];
        $filtered_categories = [];
        
        // Reset summary for filtered data
        $filtered_summary = [
            'total_products' => 0,
            'total_value' => 0,
            'in_stock' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'total_incoming' => 0,
            'total_outgoing' => 0
        ];
        
        foreach ($details as $item) {
            $item_status = getStockStatusFromQuantity($item['final_stock']);
            
            if ($stock_status === $item_status) {
                $filtered_details[] = $item;
                
                $filtered_summary['total_products']++;
                $filtered_summary['total_value'] += $item['total_value'];
                $filtered_summary['total_incoming'] += $item['incoming_quantity'];
                $filtered_summary['total_outgoing'] += $item['outgoing_quantity'];
                
                // Update stock status counts
                if ($item['final_stock'] <= 0) {
                    $filtered_summary['out_of_stock']++;
                } elseif ($item['final_stock'] <= 10) {
                    $filtered_summary['low_stock']++;
                } else {
                    $filtered_summary['in_stock']++;
                }
                
                // Build category data
                $cat = $item['category'] ?: 'Non catégorisé';
                if (!isset($filtered_categories[$cat])) {
                    $filtered_categories[$cat] = 0;
                }
                $filtered_categories[$cat] += $item['total_value'];
                
                // Add to filtered top products
                $filtered_top_products[] = [
                    'name' => $item['product_name'] . ($item['variant_name'] ? ' (' . $item['variant_name'] . ')' : ''),
                    'value' => $item['total_value']
                ];
            }
        }
        
        // Replace with filtered data
        $details = $filtered_details;
        $summary = $filtered_summary;
        $categories = $filtered_categories;
        $top_products = $filtered_top_products;
    }
    
    // Sort top products by value
    usort($top_products, function($a, $b) {
        return $b['value'] - $a['value'];
    });
    
    // Prepare charts data
    $charts_categories = [];
    foreach ($categories as $name => $value) {
        $charts_categories[] = ['name' => $name, 'value' => $value];
    }
    
    // Get daily movements
    $movements = getDailyMovements($db, $user_id, $start_date, $end_date, $category);
    
    // Add calculated metrics
    $summary['net_change'] = $summary['total_incoming'] - $summary['total_outgoing'];
    $summary['total_quantity'] = array_sum(array_column($details, 'final_stock'));
    
    if ($summary['total_products'] > 0 && $summary['total_quantity'] > 0) {
        $summary['avg_unit_value'] = $summary['total_value'] / $summary['total_quantity'];
        $summary['out_of_stock_rate'] = $summary['out_of_stock'] / $summary['total_products'];
    } else {
        $summary['avg_unit_value'] = 0;
        $summary['out_of_stock_rate'] = 0;
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'summary' => $summary,
        'details' => $details,
        'charts' => [
            'categories' => $charts_categories,
            'top_products' => array_slice($top_products, 0, 10),
            'movements' => $movements
        ],
        'filters' => [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'category' => $category,
            'stock_status' => $stock_status
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in generate_stock_report.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la génération du rapport: ' . $e->getMessage()
    ]);
}

/**
 * Get daily movements for chart
 */
function getDailyMovements($db, $user_id, $start_date, $end_date, $category = null) {
    $params = [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    
    $category_join = "";
    $category_where = "";
    
    if ($category) {
        $category_join = "INNER JOIN products p ON p.id = sm.product_id AND p.user_id = ?";
        $category_where = "AND p.category = ?";
        $params = [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59', $user_id, $category];
    } else {
        $category_join = "INNER JOIN products p ON p.id = sm.product_id AND p.user_id = ?";
        $params = [$user_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59', $user_id];
    }
    
    $query = "
        SELECT 
            DATE(sm.created_at) as date,
            SUM(CASE WHEN sm.movement_type IN ('in', 'adjustment') THEN sm.quantity ELSE 0 END) as incoming,
            SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as outgoing
        FROM stock_movements sm
        $category_join
        WHERE sm.created_at BETWEEN ? AND ?
        $category_where
        GROUP BY DATE(sm.created_at)
        ORDER BY DATE(sm.created_at)
    ";
    
    $movements = $db->getThisQuery($query, $params);
    
    // Format dates for display
    $formatted_movements = [];
    if ($movements) {
        foreach ($movements as $movement) {
            if ($movement['date']) {
                $date = new DateTime($movement['date']);
                $formatted_movements[] = [
                    'date' => $date->format('d/m'),
                    'incoming' => (int)$movement['incoming'],
                    'outgoing' => (int)$movement['outgoing']
                ];
            }
        }
    }
    
    return $formatted_movements;
}

/**
 * Get current user ID
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
 * Determine stock status from quantity
 */
function getStockStatusFromQuantity($quantity) {
    if ($quantity <= 0) {
        return 'out_of_stock';
    } elseif ($quantity <= 10) {
        return 'low_stock';
    } else {
        return 'in_stock';
    }
}
?>