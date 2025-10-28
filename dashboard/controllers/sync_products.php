<?php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// api/sync_products.php - Synchronize products from connected stores
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

// Check if action is sync_products
$action = $_POST['action'] ?? '';
if ($action !== 'sync_products') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    
    $user_id = getCurrentUserId($db);
    
    if (!$user_id) {
        throw new Exception('Utilisateur non authentifié');
    }
    
    // Get all connected stores for the user
    $stores = $db->getThisQuery("SELECT * FROM stores WHERE user_id = ?", [$user_id]);
    
    if (empty($stores)) {
        throw new Exception('Aucun magasin connecté trouvé');
    }
    
    $total_synced = 0;
    $total_updated = 0;
    $sync_results = [];
    
    foreach ($stores as $store) {
        try {
            $products_result = ['synced' => 0, 'updated' => 0];
            
            if ($store['platform'] === 'youcan') {
                $products_result = syncYouCanProducts($db, $store);
            } elseif ($store['platform'] === 'woocommerce') {
                $products_result = syncWooCommerceProducts($db, $store);
            } elseif ($store['platform'] === 'shopify') {
                $products_result = syncShopifyProducts($db, $store);
            }
            
            $total_synced += $products_result['synced'];
            $total_updated += $products_result['updated'];
            
            $sync_results[] = [
                'store' => $store['storeName'],
                'platform' => $store['platform'],
                'products_synced' => $products_result['synced'],
                'products_updated' => $products_result['updated']
            ];
            
        } catch (Exception $e) {
            error_log("Error syncing products from store {$store['storeName']}: " . $e->getMessage());
            $sync_results[] = [
                'store' => $store['storeName'],
                'platform' => $store['platform'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    $message = "Synchronisation terminée. {$total_synced} produits synchronisés, {$total_updated} produits mis à jour.";
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'details' => $sync_results,
        'total_synced' => $total_synced,
        'total_updated' => $total_updated
    ]);
    
} catch (Exception $e) {
    error_log("Error in sync_products.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Synchronize products from YouCan store
 */

 function syncYouCanProducts($db, $store) {
    // YouCan API uses fixed base URL, not store-specific
    $api_base = 'https://api.youcan.shop';
    $access_token = $store['api_key']; // This should be the OAuth access token
    
    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    
    // Get products with pagination
    $page = 1;
    $products_synced = 0;
    $products_updated = 0;
    $has_more = true;
    
    while ($has_more) {
        // YouCan products endpoint with pagination
        $url = $api_base . '/products?page=' . $page . '&per_page=50';
        
        error_log("Fetching YouCan products - Page: $page");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("CURL Error YouCan: " . $curl_error);
        }
        
        if ($http_code !== 200) {
            error_log("YouCan API Response: " . $response);
            throw new Exception("Erreur API YouCan: HTTP {$http_code} - " . substr($response, 0, 200));
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Erreur JSON YouCan: ' . json_last_error_msg());
        }
        
        // YouCan API response structure
        $products = [];
        if (isset($data['data']) && is_array($data['data'])) {
            $products = $data['data'];
        } elseif (is_array($data)) {
            $products = $data;
        }
        
        if (empty($products)) {
            $has_more = false;
            break;
        }
        
        error_log("Found " . count($products) . " YouCan products on page $page");
        
        foreach ($products as $product) {
            try {
                if (!isset($product['id'])) {
                    continue;
                }
                
                // Check if product already exists
                $existing_product = $db->getThisQuery(
                    "SELECT id FROM products WHERE store_id = ? AND external_product_id = ?",
                    [$store['id'], $product['id']]
                );
                
                // Extract images from YouCan format
                $images = [];
                if (isset($product['images']) && is_array($product['images'])) {
                    foreach ($product['images'] as $image) {
                        if (isset($image['url'])) {
                            $images[] = [
                                'id' => $image['id'] ?? null,
                                'src' => $image['url'],
                                'alt' => $image['alt'] ?? ''
                            ];
                        }
                    }
                }
                
                // Extract variants from YouCan format
                $variants = [];
                if (isset($product['variants']) && is_array($product['variants'])) {
                    $variants = $product['variants'];
                }
                
                // Handle inventory - YouCan might have different field names
                $inventory = 0;
                if (isset($product['inventory'])) {
                    $inventory = intval($product['inventory']);
                } elseif (isset($product['variants'][0]['inventory'])) {
                    // Sum inventory from variants
                    foreach ($product['variants'] as $variant) {
                        $inventory += intval($variant['inventory'] ?? 0);
                    }
                }
                
                $product_data = [
                    'store_id' => $store['id'],
                    'user_id' => $store['user_id'],
                    'external_product_id' => (string)$product['id'],
                    'name' => $product['name'] ?? '',
                    'description' => strip_tags($product['description'] ?? ''),
                    'sku' => $product['sku'] ?? '',
                    'price' => floatval($product['price'] ?? 0),
                    'compare_price' => floatval($product['compare_at_price'] ?? 0),
                    'cost_price' => floatval($product['cost_price'] ?? 0),
                    'stock_quantity' => $inventory,
                    'weight' => floatval($product['weight'] ?? 0),
                    'status' => (isset($product['visibility']) && $product['visibility'] == 1) ? 'active' : 'inactive',
                    'category' => $product['product_type'] ?? $product['category'] ?? null,
                    'tags' => is_array($product['tags'] ?? null) ? implode(',', $product['tags']) : ($product['tags'] ?? ''),
                    'images' => json_encode($images),
                    'variants' => json_encode($variants),
                    'platform' => 'youcan',
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if (!empty($existing_product)) {
                    // Update existing product
                    $result = $db->update('products', $existing_product[0]['id'], $product_data);
                    if ($result) {
                        $products_updated++;
                        error_log("Updated YouCan product: " . $product['name']);
                    }
                } else {
                    // Insert new product
                    $product_data['created_at'] = date('Y-m-d H:i:s');
                    $result = $db->insert('products', $product_data);
                    if ($result) {
                        $products_synced++;
                        error_log("Synced YouCan product: " . $product['name']);
                    }
                }
                
            } catch (Exception $e) {
                error_log("Error processing YouCan product {$product['id']}: " . $e->getMessage());
            }
        }
        
        // Check if there are more pages
        if (isset($data['meta']['pagination']['current_page']) && isset($data['meta']['pagination']['total_pages'])) {
            $has_more = $data['meta']['pagination']['current_page'] < $data['meta']['pagination']['total_pages'];
        } elseif (isset($data['links']['next'])) {
            $has_more = !empty($data['links']['next']);
        } elseif (count($products) < 50) {
            $has_more = false;
        } else {
            $has_more = false;
        }
        
        $page++;
        
        // Add small delay to avoid rate limiting
        usleep(500000); // 500ms
    }
    
    error_log("YouCan products sync completed: {$products_synced} synced, {$products_updated} updated");
    
    return [
        'synced' => $products_synced,
        'updated' => $products_updated
    ];
}

/**
 * Synchronize products from Shopify store
 */
function syncShopifyProducts($db, $store) {
    // Ensure domain is correct
    $api_url = $store['domain'];
    if (strpos($api_url, 'http') !== 0) {
        $api_url = 'https://' . $api_url;
    }
    $api_url = rtrim($api_url, '/');

    $access_token = $store['api_key'];

    // Shopify Admin API endpoint
    $products_endpoint = $api_url . '/admin/api/2024-01/products.json';

    $headers = [
        'X-Shopify-Access-Token: ' . $access_token,
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    $products_synced = 0;
    $products_updated = 0;
    $page_info = null;
    $has_more = true;

    while ($has_more) {
        $params = ['limit' => 50];

        if ($page_info) {
            $params['page_info'] = $page_info;
        }

        $url = $products_endpoint . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Important: follow redirects
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception("CURL Error Shopify: " . $curl_error);
        }

        if ($http_code !== 200) {
            $body = substr($response, $header_size);
            error_log("Shopify API Response: " . $body);
            throw new Exception("Shopify API Error: HTTP {$http_code}");
        }

        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Shopify JSON Error: ' . json_last_error_msg());
        }

        if (!isset($data['products']) || !is_array($data['products'])) {
            throw new Exception('Invalid Shopify API response');
        }

        $products = $data['products'];

        if (empty($products)) {
            break;
        }

        foreach ($products as $product) {
            try {
                if (!isset($product['id'])) {
                    continue;
                }

                $existing_product = $db->getThisQuery(
                    "SELECT id FROM products WHERE store_id = ? AND external_product_id = ?",
                    [$store['id'], (string)$product['id']]
                );

                $images = [];
                if (isset($product['images']) && is_array($product['images'])) {
                    foreach ($product['images'] as $image) {
                        $images[] = [
                            'id' => $image['id'] ?? null,
                            'src' => $image['src'] ?? '',
                            'alt' => $image['alt'] ?? ''
                        ];
                    }
                }

                $main_variant = $product['variants'][0] ?? [];

                // Handle inventory
                $inventory_quantity = 0;
                if (isset($main_variant['inventory_management']) && $main_variant['inventory_management']) {
                    $inventory_quantity = intval($main_variant['inventory_quantity'] ?? 0);
                }

                // Build product_data
                $product_data = [
                    'store_id' => $store['id'],
                    'user_id' => $store['user_id'],
                    'external_product_id' => (string)$product['id'],
                    'name' => $product['title'] ?? '',
                    'description' => strip_tags($product['body_html'] ?? ''),

                    // SKU fallback if empty
                    'sku' => $main_variant['sku'] 
                        ?: ($main_variant['barcode'] ?? $product['handle']),

                    'price' => floatval($main_variant['price'] ?? 0),
                    'compare_price' => !empty($main_variant['compare_at_price']) 
                        ? floatval($main_variant['compare_at_price']) 
                        : 0,
                    'cost_price' => 0, // Shopify Products API doesn’t return cost

                    'stock_quantity' => $inventory_quantity,

                    // Convert grams → kg
                    'weight' => isset($main_variant['grams']) 
                        ? floatval($main_variant['grams']) / 1000 
                        : 0,

                    'status' => ($product['status'] ?? '') === 'active' ? 'active' : 'inactive',
                    'category' => $product['product_type'] ?? null,
                    'tags' => $product['tags'] ?? '',
                    'images' => json_encode($images),
                    'variants' => json_encode($product['variants'] ?? []),
                    'platform' => 'shopify',
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if (!empty($existing_product)) {
                    $result = $db->update('products', $existing_product[0]['id'], $product_data);
                    if ($result) {
                        $products_updated++;
                    }
                } else {
                    $product_data['created_at'] = date('Y-m-d H:i:s');
                    $result = $db->insert('products', $product_data);
                    if ($result) {
                        $products_synced++;
                    }
                }

            } catch (Exception $e) {
                error_log("Error processing Shopify product {$product['id']}: " . $e->getMessage());
            }
        }

        $page_info = null;
        if (preg_match('/<([^>]+)>;\s*rel="next"/', $header, $matches)) {
            parse_str(parse_url($matches[1], PHP_URL_QUERY), $query);
            $page_info = $query['page_info'] ?? null;
        }

        $has_more = ($page_info !== null);
    }

    return [
        'synced' => $products_synced,
        'updated' => $products_updated
    ];
}

/**
 * Synchronize products from WooCommerce store
 */
function syncWooCommerceProducts($db, $store) {
    $api_url = rtrim($store['api_url'], '/');
    $consumer_key = $store['Consumer_key'];
    $consumer_secret = $store['Consumer_secret'];
    
    // WooCommerce REST API endpoint for products
    $products_endpoint = $api_url . "/products/";
    
    // Get products with pagination
    $params = [
        'per_page' => 100,
        'status' => 'publish',
        'consumer_key' => $consumer_key,
        'consumer_secret' => $consumer_secret
    ];
    
    $url = $products_endpoint . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception("CURL Error: " . $curl_error);
    }
    
    if ($http_code !== 200) {
        throw new Exception("Erreur API WooCommerce: HTTP {$http_code} - Response: " . $response);
    }
    
    $products = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erreur JSON: ' . json_last_error_msg());
    }
    
    if (!$products || !is_array($products)) {
        throw new Exception('Réponse API WooCommerce invalide');
    }
    
    $products_synced = 0;
    $products_updated = 0;
    
    foreach ($products as $product) {
        try {
            if (!isset($product['id'])) {
                continue;
            }
            
            // Check if product already exists
            $existing_product = $db->getThisQuery(
                "SELECT id FROM products WHERE store_id = ? AND external_product_id = ?",
                [$store['id'], $product['id']]
            );
            
            // Extract categories
            $categories = [];
            if (isset($product['categories']) && is_array($product['categories'])) {
                foreach ($product['categories'] as $category) {
                    $categories[] = $category['name'];
                }
            }
            
            // Extract tags
            $tags = [];
            if (isset($product['tags']) && is_array($product['tags'])) {
                foreach ($product['tags'] as $tag) {
                    $tags[] = $tag['name'];
                }
            }
            
            // Extract main image and gallery
            $images = [];
            if (isset($product['images']) && is_array($product['images'])) {
                foreach ($product['images'] as $image) {
                    $images[] = [
                        'id' => $image['id'] ?? null,
                        'src' => $image['src'] ?? '',
                        'alt' => $image['alt'] ?? ''
                    ];
                }
            }
            
            $product_data = [
                'store_id' => $store['id'],
                'user_id' => $store['user_id'],
                'external_product_id' => $product['id'],
                'name' => $product['name'] ?? '',
                'description' => strip_tags($product['description'] ?? ''),
                'short_description' => strip_tags($product['short_description'] ?? ''),
                'sku' => $product['sku'] ?? '',
                'price' => floatval($product['price'] ?? 0),
                'regular_price' => floatval($product['regular_price'] ?? 0),
                'sale_price' => floatval($product['sale_price'] ?? 0),
                'stock_quantity' => $product['manage_stock'] ? intval($product['stock_quantity'] ?? 0) : null,
                'stock_status' => $product['stock_status'] ?? 'instock',
                'weight' => floatval($product['weight'] ?? 0),
                'length' => floatval($product['dimensions']['length'] ?? 0),
                'width' => floatval($product['dimensions']['width'] ?? 0),
                'height' => floatval($product['dimensions']['height'] ?? 0),
                'status' => $product['status'] === 'publish' ? 'active' : 'inactive',
                'featured' => $product['featured'] ? 1 : 0,
                'category' => implode(',', $categories),
                'tags' => implode(',', $tags),
                'images' => json_encode($images),
                'type' => $product['type'] ?? 'simple',
                'platform' => 'woocommerce',
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (!empty($existing_product)) {
                // Update existing product
                $result = $db->update('products', $existing_product[0]['id'], $product_data);
                if ($result) {
                    $products_updated++;
                    
                    // Update product variations if it's a variable product
                    if ($product['type'] === 'variable' && isset($product['variations'])) {
                        syncProductVariations($db, $existing_product[0]['id'], $product['variations'], $store);
                    }
                }
            } else {
                // Insert new product
                $product_data['created_at'] = date('Y-m-d H:i:s');
                $result = $db->insert('products', $product_data);
                if ($result) {
                    $product_id = $db->getLastInsertId();
                    $products_synced++;
                    
                    // Insert product variations if it's a variable product
                    if ($product['type'] === 'variable' && isset($product['variations'])) {
                        syncProductVariations($db, $product_id, $product['variations'], $store);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error processing WooCommerce product {$product['id']}: " . $e->getMessage());
        }
    }
    
    return [
        'synced' => $products_synced,
        'updated' => $products_updated
    ];
}

/**
 * Synchronize product variations for variable products
 */
function syncProductVariations($db, $product_id, $variation_ids, $store) {
    if (!is_array($variation_ids) || empty($variation_ids)) {
        return;
    }
    
    $api_url = rtrim($store['api_url'], '/');
    $consumer_key = $store['Consumer_key'];
    $consumer_secret = $store['Consumer_secret'];
    
    foreach ($variation_ids as $variation_id) {
        try {
            // Fetch variation details
            $variation_endpoint = $api_url . "/products/{$product_id}/variations/{$variation_id}";
            $params = [
                'consumer_key' => $consumer_key,
                'consumer_secret' => $consumer_secret
            ];
            
            $url = $variation_endpoint . '?' . http_build_query($params);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                $variation = json_decode($response, true);
                
                if ($variation) {
                    // Check if variation already exists
                    $existing_variation = $db->getThisQuery(
                        "SELECT id FROM product_variations WHERE product_id = ? AND external_variation_id = ?",
                        [$product_id, $variation['id']]
                    );
                    
                    $variation_data = [
                        'product_id' => $product_id,
                        'external_variation_id' => $variation['id'],
                        'sku' => $variation['sku'] ?? '',
                        'price' => floatval($variation['price'] ?? 0),
                        'regular_price' => floatval($variation['regular_price'] ?? 0),
                        'sale_price' => floatval($variation['sale_price'] ?? 0),
                        'stock_quantity' => $variation['manage_stock'] ? intval($variation['stock_quantity'] ?? 0) : null,
                        'stock_status' => $variation['stock_status'] ?? 'instock',
                        'weight' => floatval($variation['weight'] ?? 0),
                        'attributes' => json_encode($variation['attributes'] ?? []),
                        'image' => isset($variation['image']['src']) ? $variation['image']['src'] : null,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    if (!empty($existing_variation)) {
                        $db->update('product_variations', $existing_variation[0]['id'], $variation_data);
                    } else {
                        $variation_data['created_at'] = date('Y-m-d H:i:s');
                        $db->insert('product_variations', $variation_data);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error syncing variation {$variation_id}: " . $e->getMessage());
        }
    }
}

/**
 * Get current user ID from session
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

?>