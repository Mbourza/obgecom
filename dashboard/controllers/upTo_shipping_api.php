<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

if (!defined('SHIPPING_DISPATCHER_INCLUDED')):

$db = DB::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action required']);
    exit;
}

try {

    $user_id = getCurrentUserId($db);

    if (!$user_id) {
        throw new Exception('Utilisateur non authentifié');
    }

    // Handle single order shipping
    if ($_POST['action'] === 'submit_to_shipping') {
        $order_id = $_POST['order_id'] ?? '';
        if (empty($order_id)) {
            throw new Exception('ID de commande requis');
        }

        // Get order details with store information
        $order = $db->getThisQuery("
            SELECT o.*, s.platform, s.api_url, s.api_key, s.Consumer_key, s.Consumer_secret
            FROM orders o 
            JOIN stores s ON o.store_id = s.id 
            WHERE o.id = ? AND o.user_id = ?
        ", [$order_id, $user_id]);

        if (empty($order)) {
            throw new Exception('Commande non trouvée');
        }

        $order = $order[0]; // Get first result
        // Verify order is confirmed before shipping
        if ($order['status'] !== 'confirmed') {
            throw new Exception('La commande doit être confirmée avant l\'expédition');
        }

        // Handle shipping company notification
        $shipping_result = handleShippingCompanyNotification($db, $order, 'confirmed', $user_id);

        if (!$shipping_result['notification_sent']) {
            throw new Exception($shipping_result['message'] ?? 'Échec de l\'envoi au transporteur');
        }

        // Update order status to processing after successful shipping submission
        $db->update('orders', $order_id, [
            'shipping_status' => 'processing',
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $response = [
            'success' => true,
            'message' => 'Commande soumise au transporteur avec succès',
            'order_id' => $order_id,
            'shipping_notification' => [
                'company' => $shipping_result['company_name'],
                'tracking_number' => $shipping_result['tracking_number'] ?? '',
                'message' => $shipping_result['message']
            ]
        ];

        echo json_encode($response);

    } 
    // Handle bulk order shipping
    elseif ($_POST['action'] === 'bulk_submit_to_shipping') {
        $order_ids_json = $_POST['order_ids'] ?? '';
        if (empty($order_ids_json)) {
            throw new Exception('IDs de commandes requis');
        }

        $order_ids = json_decode($order_ids_json, true);
        if (!is_array($order_ids) || empty($order_ids)) {
            throw new Exception('Format des IDs de commandes invalide');
        }

        $success_count = 0;
        $failed_count = 0;
        $tracking_numbers = [];
        $errors = [];

        foreach ($order_ids as $order_id) {
            try {
                // Get order details with store information
                $order = $db->getThisQuery("
                    SELECT o.*, s.platform, s.api_url, s.api_key, s.Consumer_key, s.Consumer_secret
                    FROM orders o 
                    JOIN stores s ON o.store_id = s.id 
                    WHERE o.id = ? AND o.user_id = ?
                ", [$order_id, $user_id]);

                if (empty($order)) {
                    $failed_count++;
                    $errors[] = "Commande {$order_id} non trouvée";
                    $tracking_numbers[] = [
                        'order_id' => $order_id,
                        'success' => false,
                        'error' => 'Commande non trouvée'
                    ];
                    continue;
                }

                $order = $order[0]; // Get first result

                // Verify order is confirmed before shipping
                if ($order['status'] !== 'confirmed') {
                    $failed_count++;
                    $errors[] = "Commande {$order_id} doit être confirmée avant l'expédition";
                    $tracking_numbers[] = [
                        'order_id' => $order_id,
                        'success' => false,
                        'error' => 'Commande non confirmée'
                    ];
                    continue;
                }

                // Handle shipping company notification
                $shipping_result = handleShippingCompanyNotification($db, $order, 'confirmed', $user_id);

                if (!$shipping_result['notification_sent']) {
                    $failed_count++;
                    $error_message = $shipping_result['message'] ?? 'Échec de l\'envoi au transporteur';
                    $errors[] = "Commande {$order_id}: {$error_message}";
                    $tracking_numbers[] = [
                        'order_id' => $order_id,
                        'success' => false,
                        'error' => $error_message
                    ];
                    continue;
                }

                // Update order status to processing after successful shipping submission
                $db->update('orders', $order_id, [
                    'shipping_status' => 'processing',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                $success_count++;
                $tracking_numbers[] = [
                    'order_id' => $order_id,
                    'success' => true,
                    'tracking_number' => $shipping_result['tracking_number'] ?? '',
                    'company' => $shipping_result['company_name'],
                    'message' => $shipping_result['message']
                ];

            } catch (Exception $e) {
                $failed_count++;
                $error_message = $e->getMessage();
                $errors[] = "Commande {$order_id}: {$error_message}";
                $tracking_numbers[] = [
                    'order_id' => $order_id,
                    'success' => false,
                    'error' => $error_message
                ];
                error_log("Error processing order {$order_id} in bulk shipping: " . $error_message);
            }
        }

        $response = [
            'success' => $success_count > 0,
            'message' => "Traitement terminé: {$success_count} succès, {$failed_count} échecs",
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'tracking_numbers' => $tracking_numbers,
            'errors' => $errors
        ];

        echo json_encode($response);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action invalide']);
    }

} catch (Throwable $e) {
    error_log("Error in upTo_shipping_api.php: " . $e->getMessage() 
        . " | File: " . $e->getFile() 
        . " | Line: " . $e->getLine());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() !== '' 
            ? $e->getMessage() 
            : 'Erreur inconnue (' . get_class($e) . ' à la ligne ' . $e->getLine() . ')'
    ]);
}

endif; // !defined('SHIPPING_DISPATCHER_INCLUDED')
/**
 * Check if package exists in OzoneExpress
*/
function checkOzoneExpressPackageExists($apiUrl, $trackingNumber) {
    $url = $apiUrl.'/parcel-info';
    
    $postData = [
        'tracking-number' => $trackingNumber
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        
        if (isset($response_data['PARCEL-INFO']['INFOS']['TRACKING-NUMBER']) && !empty($response_data['PARCEL-INFO']['INFOS']['TRACKING-NUMBER'])) {
            return [
                'exists' => true,
                'package_info' => $response_data
            ];
        }
    }
    
    return [
        'exists' => false,
        'error' => $response ?? null
    ];
}

/**
 * Check if package exists in ForceLog
 */
function checkForceLogPackageExists($apiKey, $trackingNumber) {
    $url = 'https://api.forcelog.ma/customer/Parcels/GetParcel';
    
    $postData = [
        'Code' => $trackingNumber
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        
        if (isset($response_data['RESULT']) && $response_data['RESULT'] === 'SUCCESS' && isset($response_data['PARCEL'])) {
            return [
                'exists' => true,
                'package_info' => $response_data
            ];
        }
    }
    
    return [
        'exists' => false,
        'error' => $response ?? null
    ];
}

/**
 * Get ForceLog cities from API
 */
/**
 * Get ForceLog cities from API with better error handling
 */
function getForceLogCities($apiKey) {
    $url = 'https://api.forcelog.ma/customer/Cities';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        
        if (is_array($response_data) && !empty($response_data)) {
            return $response_data;
        }
    }
    
    error_log("ForceLog Cities API Error - HTTP Code: $http_code, Response: " . $response);
    
    // Return some default cities as fallback
    return [];
}

/**
 * Get ForceLog city ID by name
 */
function getForceLogCityId($apiKey, $city_name) {
    $cities = getForceLogCities($apiKey);
    
    if (empty($cities)) {
        return null;
    }
    
    $city_name = strtolower(trim($city_name));
    $best_match_id = null;
    $best_similarity = 0;
    
    foreach ($cities['Cities'] as $city) {
 
        if (!isset($city['NAME'])) {
            continue;
        }
        
        $city_ref = strtolower(trim($city['NAME']));
        
        // 1. Exact match
        if ($city_ref === $city_name) {
            return $city['CODE']; // Return the numeric city ID
        }
        
        // 2. Contains match (handles partial matches)
        if (strpos($city_ref, $city_name) !== false || strpos($city_name, $city_ref) !== false) {
            if (strlen($city_name) >= 3) {
                return $city['CODE'];
            }
        }
        
        // 3. Levenshtein distance (handles typos better)
        $distance = levenshtein($city_name, $city_ref);
        $max_length = max(strlen($city_name), strlen($city_ref));
        $similarity = (($max_length - $distance) / $max_length) * 100;
        
        if ($similarity > $best_similarity && $similarity >= 40) {
            $best_similarity = $similarity;
            $best_match_id = $city['CODE'];
        }
        
        // 4. Similar text as fallback
        similar_text($city_name, $city_ref, $percent);
        if ($percent > $best_similarity && $percent >= 35) {
            $best_similarity = $percent;
            $best_match_id = $city['CODE'];
        }
    }

    return $best_match_id;
}

/**
 * Get ForceLog city CODE by name
*/
function getForceLogCityCode($apiKey, $city_name) {
    $cities = getForceLogCities($apiKey);
    
    if (empty($cities)) {
        return null;
    }
    
    $city_name = strtolower(trim($city_name));
    
    foreach ($cities['Cities'] as $city) {
        if (!isset($city['NAME']) || !isset($city['CODE'])) {
            continue;
        }
        
        $city_ref = strtolower(trim($city['NAME']));
        $city_code = trim($city['CODE']);
        
        // Exact match with city name
        if ($city_ref === $city_name) {
            return $city_code;
        }
        
        // Remove accents and special characters for better matching
        $clean_city_name = removeAccents($city_name);
        $clean_city_ref = removeAccents($city_ref);
        
        if ($clean_city_ref === $clean_city_name) {
            return $city_code;
        }
        
        // Contains match
        if (strpos($clean_city_ref, $clean_city_name) !== false || 
            strpos($clean_city_name, $clean_city_ref) !== false) {
            return $city_code;
        }
        
        // Try matching with common city variations
        $city_variations = [
            'ouarzazate' => ['ouarzazate', 'warzazate', 'orzazate'],
            'casablanca' => ['casablanca', 'casa', 'dar el beida'],
            'marrakech' => ['marrakech', 'marrakesh'],
            // Add more variations as needed
        ];
        
        if (isset($city_variations[$clean_city_name])) {
            foreach ($city_variations[$clean_city_name] as $variation) {
                if ($clean_city_ref === $variation) {
                    return $city_code;
                }
            }
        }
    }

    return null;
}

/**
 * Check ForceLog API health
*/
function checkForceLogHealth($apiKey) {
    $url = 'https://api.forcelog.ma/health/';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return ['healthy' => true, 'message' => 'API is healthy'];
    } else {
        return ['healthy' => false, 'message' => "Health check failed with HTTP $http_code"];
    }
}

function handleShippingCompanyNotification($db, $order, $new_status, $user_id) {
    $result = ['notification_sent' => false, 'company_name' => '', 'message' => ''];
    
    // Only notify shipping company for confirmed/processing orders
    if (!in_array($new_status, ['confirmed', 'delivered'])) {
        return $result;
    }
    
    // Fetch shipping settings and company info
    $shipping_info = $db->getThisQuery("
        SELECT 
            ss.shipping_company_id,
            ss.company_name,
            sc.name as company_name,
            sc.api_url,
            sc.api_key,
            sc.api_secret,
            sc.auth_email,
            sc.auth_password,
            sc.supports_tracking,
            sc.is_active
        FROM shipping_settings ss
        JOIN shipping_companies sc ON ss.shipping_company_id = sc.id
        WHERE ss.user_id = ? AND sc.is_active = 1
        ORDER BY ss.priority ASC
        LIMIT 1
    ", [$user_id]);

    if (empty($shipping_info)) {
        return $result;
    }
    
    $shipping_company = $shipping_info[0];
    $company_name = strtolower($shipping_company['company_name']);
    
    try {
        // OzoneExpress handling
        if ($company_name === 'ozonexpress') {
            // [Keep existing OzoneExpress code unchanged...]
            // First check if order already has a tracking number
            if (!empty($order['tracking_number'])) {
                $base_url = cleanApiBaseUrl($shipping_company['api_url']);
                $url = $base_url . '/add-parcel';

                $duplicate_check = checkOzoneExpressPackageExists(
                    $base_url,
                    $order['tracking_number']
                );
                
                if ($duplicate_check['exists']) {
                    $result['notification_sent'] = false;
                    $result['company_name'] = $shipping_company['company_name'];
                    $result['message'] = 'Colis déjà envoyé à OzonExpress avec le numéro: ' . $order['tracking_number'];
                    $result['tracking_number'] = $order['tracking_number'];
                    $result['duplicate_found'] = true;
                    return $result;
                }
            }
            
            // Validate required fields before preparing payload
            $missing_fields = [];
            $field_labels = [
                'customer_name' => 'Nom du destinataire (parcel-receiver)',
                'customer_phone' => 'Téléphone du destinataire (parcel-phone)',
                'shipping_address' => 'Adresse complète (parcel-address)',
                'total_amount' => 'Prix du colis (parcel-price)'
            ];
            
            foreach ($field_labels as $field => $label) {
                if (empty($order[$field]) || (is_string($order[$field]) && trim($order[$field]) === '')) {
                    $missing_fields[] = $label;
                }
            }
            
            // Validate city
            $city_name = getCityNameFromBillingAddress($order ?? '');
            $city_id = getOzonCityId($city_name);
            if (empty($city_id)) {
                $missing_fields[] = 'ID de la ville (parcel-city) - Ville non trouvée ou invalide';
            }
            
            // If there are missing required fields, return error
            if (!empty($missing_fields)) {
                $result['notification_sent'] = false;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Champs requis manquants pour OzonExpress: ' . implode(', ', $missing_fields);
                $result['missing_fields'] = $missing_fields;
                return $result;
            }
            
            // Prepare data for OzonExpress
            $items = getOrderItems($db, $order['id']);
            $products = [];
            
            foreach ($items as $item) {
                $products[] = [
                    'ref' => $item['sku'] ?? $item['name'],
                    'qnty' => $item['quantity']
                ];
            }
            
            $payload = [
                'tracking-number' => '', // Let system auto-generate
                'parcel-receiver' => trim($order['customer_name']),
                'parcel-phone' => trim($order['customer_phone']),
                'parcel-city' => $city_id,
                'parcel-address' => trim($order['shipping_address']),
                'parcel-note' => $order['shipping_method'] ?? '',
                'parcel-price' => $order['total_amount'],
                'parcel-nature' => 'Commande client',
                'parcel-stock' => 0,
                'products' => json_encode($products)
            ];
            
            // Fix: Append /add-parcel to the API URL
            $baseUrl = rtrim($shipping_company['api_url'], '/');
            if (substr($baseUrl, -10) !== 'add-parcel') { 
                $url = $baseUrl . '/add-parcel';
            } else {
                $url = $baseUrl;
            }
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            // Handle cURL errors
            if ($response === false) {
                $result['message'] = 'Erreur de connexion à OzonExpress: ' . $curl_error;
                return $result;
            }
            
            $response_data = json_decode($response, true);
            
            if ($http_code === 200 && isset($response_data['ADD-PARCEL']['NEW-PARCEL']['TRACKING-NUMBER'])) {
                $tracking_number = $response_data['ADD-PARCEL']['NEW-PARCEL']['TRACKING-NUMBER'];
                
                // Update the order with the new tracking number
                $db->update('orders', $order['id'], [
                    'tracking_number' => $tracking_number,
                    'shipping_company_id' => $shipping_company['shipping_company_id']
                ]);
                
                $result['notification_sent'] = true;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Commande envoyée à OzonExpress avec succès';
                $result['tracking_number'] = $tracking_number;
                
            } else {
                if (isset($response_data['error'])) {
                    $error_msg = $response_data['error'];
                } elseif (isset($response_data['message'])) {
                    $error_msg = $response_data['message'];
                } else {
                    $error_msg = 'Réponse invalide de l\'API (Code HTTP: ' . $http_code . ')';
                    error_log("OzonExpress API Response: " . $response);
                }
                
                $result['message'] = 'Erreur OzonExpress: ' . $error_msg;
                $result['api_response'] = $response_data;
            }
            
        } 

        // ForceLog handling
        elseif ($company_name === 'forcelog') {
            // First check if order already has a tracking number
            if (!empty($order['tracking_number'])) {
                $duplicate_check = checkForceLogPackageExists(
                    $shipping_company['api_key'],
                    $order['tracking_number']
                );
                
                if ($duplicate_check['exists']) {
                    $result['notification_sent'] = false;
                    $result['company_name'] = $shipping_company['company_name'];
                    $result['message'] = 'Colis déjà envoyé à ForceLog avec le numéro: ' . $order['tracking_number'];
                    $result['tracking_number'] = $order['tracking_number'];
                    $result['duplicate_found'] = true;
                    return $result;
                }
            }
            
            // Validate required fields for ForceLog
            $missing_fields = [];
            $validation_errors = [];
            
            // Check each required field individually with strict validation
            if (empty(trim($order['customer_name'] ?? ''))) {
                $missing_fields[] = 'Nom du destinataire (RECEIVER)';
            }
            
            if (empty(trim($order['customer_phone'] ?? ''))) {
                $missing_fields[] = 'Téléphone du destinataire (PHONE)';
            } else {
                $phone = formatPhoneNumber($order['customer_phone']);
                if (strlen($phone) < 10) {
                    $validation_errors[] = 'Numéro de téléphone invalide';
                }
            }
            
            if (empty(trim($order['shipping_address'] ?? ''))) {
                $missing_fields[] = 'Adresse complète (ADDRESS)';
            }
            
            // Validate city
            $city_name = getCityNameFromBillingAddress($order ?? '');
            $city_code = getForceLogCityCode($shipping_company['api_key'], $city_name);
            
            if (empty($city_code)) {
                $missing_fields[] = 'Ville de destination (CITY) - Ville non trouvée ou non desservie par ForceLog';
                // Debug: log available cities
                $available_cities = getForceLogCities($shipping_company['api_key']);
                error_log("Available ForceLog Cities: " . json_encode($available_cities));
                error_log("Looking for city: " . $city_name);
            }
            
            // If there are missing required fields, return error
            if (!empty($missing_fields) || !empty($validation_errors)) {
                $result['notification_sent'] = false;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Erreurs de validation: ' . 
                    implode(', ', $missing_fields) . 
                    (!empty($validation_errors) ? ' | ' . implode(', ', $validation_errors) : '');
                $result['missing_fields'] = $missing_fields;
                $result['validation_errors'] = $validation_errors;
                return $result;
            }
            
            // Prepare data for ForceLog
            $items = getOrderItems($db, $order['id']);
            $stock_items = [];
            
            foreach ($items as $item) {
                $sku = $item['sku'] ?? $item['name'];
                $quantity = $item['quantity'];
                // Clean SKU and ensure it's not empty
                $sku = trim($sku);
                if (!empty($sku)) {
                    $stock_items[] = "{$sku}:{$quantity}";
                }
            }
            
            // If no valid stock items, use a default
            if (empty($stock_items)) {
                $stock_items[] = "PRODUIT:1";
            }
            
            // Format phone number
            $phone = formatPhoneNumber($order['customer_phone']);
            
            // Prepare payload - using city CODE not ID
            $payload = [
                'ORDER_NUM' => substr($order['order_number'] ?? 'CMD_' . $order['id'], 0, 20),
                'RECEIVER' => substr(trim($order['customer_name']), 0, 50),
                'PHONE' => substr($phone, 0, 14),
                'CITY' => $city_code, // Use city CODE (like "ORZ")
                'ADDRESS' => substr(trim($order['shipping_address']), 0, 100),
                'COMMENT' => substr($order['shipping_method'] ?? '', 0, 100),
                'PRODUCT_NATURE' => substr('Commande client', 0, 100),
                'COD' => floatval($order['total_amount'] ?? 0),
                'CAN_OPEN' => 1,
                'STOCK' => substr(implode(',', $stock_items), 0, 100)
            ];
            
            // Log the payload for debugging
            error_log("ForceLog Final Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
            
            $url = 'https://api.forcelog.ma/customer/Parcels/AddParcel';
            
            // Try different request formats
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            
            // Try sending as JSON first (some APIs prefer this)
            $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-API-Key: ' . $shipping_company['api_key'],
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Shipping-Integration/1.0'
            ]);
            
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            // Enable verbose logging for debugging
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            // Get verbose output
            rewind($verbose);
            $verbose_log = stream_get_contents($verbose);
            error_log("cURL Verbose: " . $verbose_log);
            fclose($verbose);
            
            curl_close($ch);
            
            // If JSON fails, try form-data
            if ($http_code === 400 || $http_code === 415) {
                error_log("JSON request failed, trying form-data...");
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                
                // Try multipart form-data
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-API-Key: ' . $shipping_company['api_key'],
                    'Content-Type: multipart/form-data',
                    'User-Agent: Shipping-Integration/1.0'
                ]);
                
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
            }
            
            // If form-data fails, try URL encoded
            if ($http_code === 400 || $http_code === 415) {
                error_log("Form-data failed, trying URL encoded...");
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                
                // Try URL encoded
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-API-Key: ' . $shipping_company['api_key'],
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: Shipping-Integration/1.0'
                ]);
                
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
            }
            
            // Log the full response for debugging
            error_log("ForceLog Final Response - HTTP Code: $http_code");
            error_log("ForceLog Response Body: " . $response);
            
            // Handle cURL errors
            if ($response === false) {
                $result['message'] = 'Erreur de connexion à ForceLog: ' . $curl_error;
                return $result;
            }
            
            $response_data = json_decode($response, true);
            
            if ($http_code === 200 && isset($response_data['ADD-PARCEL']['RESULT']) && $response_data['ADD-PARCEL']['RESULT'] === 'SUCCESS') {
                $tracking_number = $response_data['ADD-PARCEL']['NEW-PARCEL']['TRACKING_NUMBER'];
                
                // Update the order with the new tracking number
                $db->update('orders', $order['id'], [
                    'tracking_number' => $tracking_number,
                    'shipping_company_id' => $shipping_company['shipping_company_id']
                ]);
                
                $result['notification_sent'] = true;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Commande envoyée à ForceLog avec succès';
                $result['tracking_number'] = $tracking_number;
                
            } else {
                // Better error handling
                $error_msg = 'Erreur inconnue';
                
                if (isset($response_data['ADD-PARCEL']['MESSAGE'])) {
                    $error_msg = $response_data['ADD-PARCEL']['MESSAGE'];
                } elseif (isset($response_data['message'])) {
                    $error_msg = $response_data['message'];
                } elseif ($http_code === 400) {
                    $error_msg = 'Requête invalide (Bad Request) - Vérifiez le format des données';
                } elseif ($http_code === 401) {
                    $error_msg = 'Clé API invalide ou non autorisée';
                } elseif ($http_code === 415) {
                    $error_msg = 'Type de contenu non supporté';
                } else {
                    $error_msg = 'Erreur HTTP: ' . $http_code;
                }
                
                $result['message'] = 'Erreur ForceLog: ' . $error_msg;
                $result['api_response'] = $response_data;
                $result['http_code'] = $http_code;
            }
        } 

        // power delivery handling
        elseif ($company_name === 'power delivery' || $company_name === 'power_delivery') {
            // First check if order already has a tracking number
            if (!empty($order['tracking_number'])) {
                $duplicate_check = checkPowerDeliveryPackageExists(
                    $shipping_company['api_key'],
                    $order['tracking_number']
                );
                
                if ($duplicate_check['exists']) {
                    $result['notification_sent'] = false;
                    $result['company_name'] = $shipping_company['company_name'];
                    $result['message'] = 'Colis déjà envoyé à Power Delivery avec le numéro: ' . $order['tracking_number'];
                    $result['tracking_number'] = $order['tracking_number'];
                    $result['duplicate_found'] = true;
                    return $result;
                }
            }
            
            // Validate required fields for Power Delivery
            $missing_fields = [];
            $validation_errors = [];
            
            // Check each required field individually with strict validation
            if (empty(trim($order['customer_name'] ?? ''))) {
                $missing_fields[] = 'Nom du destinataire (parcel_receiver)';
            }
            
            if (empty(trim($order['customer_phone'] ?? ''))) {
                $missing_fields[] = 'Téléphone du destinataire (parcel_phone)';
            } else {
                $phone = formatPhoneNumber($order['customer_phone']);
                if (strlen($phone) < 10) {
                    $validation_errors[] = 'Numéro de téléphone invalide';
                }
            }
            
            if (empty(trim($order['shipping_address'] ?? ''))) {
                $missing_fields[] = 'Adresse complète (parcel_address)';
            }
            
            // Validate city - try both ID and name matching
            $city_name = getCityNameFromBillingAddress($order ?? '');
            $city_id = getPowerDeliveryCityId($shipping_company['api_key'], $city_name);
            
            if (empty($city_id)) {
                // Try to get cities list to see what's available
                $available_cities = getPowerDeliveryCities($shipping_company['api_key']);
                $city_names = [];
                foreach ($available_cities as $city) {
                    if (isset($city['name'])) {
                        $city_names[] = $city['name'];
                    }
                }
                
                $missing_fields[] = 'Ville de destination (parcel_city) - Ville "' . $city_name . '" non trouvée. Villes disponibles: ' . implode(', ', array_slice($city_names, 0, 10));
                error_log("Power Delivery - Ville non trouvée: " . $city_name);
                error_log("Villes disponibles: " . json_encode($city_names));
            }
            
            // Validate total amount
            if (empty($order['total_amount']) || !is_numeric($order['total_amount']) || floatval($order['total_amount']) < 0) {
                $validation_errors[] = 'Montant total invalide';
            }
            
            // If there are missing required fields, return error
            if (!empty($missing_fields) || !empty($validation_errors)) {
                $result['notification_sent'] = false;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Erreurs de validation: ' . 
                    implode(', ', $missing_fields) . 
                    (!empty($validation_errors) ? ' | ' . implode(', ', $validation_errors) : '');
                $result['missing_fields'] = $missing_fields;
                $result['validation_errors'] = $validation_errors;
                return $result;
            }
            
            // Prepare data for Power Delivery
            $items = getOrderItems($db, $order['id']);
            
            // Generate unique parcel code - use order number if available, otherwise generate
            if (!empty($order['order_number'])) {
                $parcel_code = 'PD' . preg_replace('/[^a-zA-Z0-9]/', '', $order['order_number']);
            } else {
                $parcel_code = generateUniqueParcelCode($order);
            }
            
            // Ensure parcel code is not too long
            $parcel_code = substr($parcel_code, 0, 50);
            
            // Format phone number
            $phone = formatPhoneNumber($order['customer_phone']);
            
            // Prepare payload for Power Delivery according to API documentation
            $payload = [
                'parcel_code' => $parcel_code,
                'parcel_receiver' => substr(trim($order['customer_name']), 0, 100),
                'parcel_phone' => substr($phone, 0, 20),
                'parcel_city' => $city_id, // Use city ID (numeric)
                'parcel_price' => floatval($order['total_amount']),
                'parcel_address' => substr(trim($order['shipping_address']), 0, 255),
                'parcel_open' => 1, // Allow package opening by default (1 = yes, 0 = no)
                'parcel_note' => substr($order['shipping_method'] ?? 'Commande e-commerce', 0, 255)
            ];
            
            // Add optional fields if available
            if (!empty($order['customer_email'])) {
                $payload['parcel_email'] = substr(trim($order['customer_email']), 0, 100);
            }
            
            // Log the payload for debugging (without sensitive data)
            $debug_payload = $payload;
            unset($debug_payload['parcel_phone']);
            error_log("Power Delivery Payload: " . json_encode($debug_payload, JSON_UNESCAPED_UNICODE));
            
            $url = 'https://elog.ma/apiclient/addparcelsnew';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            
            // Send as JSON
            $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $shipping_company['api_key'],
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            // Enable verbose for debugging
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            // Get verbose output
            rewind($verbose);
            $verbose_log = stream_get_contents($verbose);
            fclose($verbose);
            
            curl_close($ch);
            
            // Log the full request/response for debugging
            error_log("Power Delivery Request: " . $json_payload);
            error_log("Power Delivery Response - HTTP Code: $http_code");
            error_log("Power Delivery Response Body: " . $response);
            
            // Handle cURL errors
            if ($response === false) {
                $result['message'] = 'Erreur de connexion à Power Delivery: ' . $curl_error;
                error_log("Power Delivery cURL Error: " . $curl_error);
                return $result;
            }
            
            $response_data = json_decode($response, true);
            
            if ($http_code === 200 && isset($response_data['success']) && $response_data['success'] === true) {
                $tracking_number = $parcel_code; // Use the parcel code as tracking number
                
                // Update the order with the new tracking number
                $db->update('orders', $order['id'], [
                    'tracking_number' => $tracking_number,
                    'shipping_company_id' => $shipping_company['shipping_company_id'],
                    'shipping_status' => 'processing',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                $result['notification_sent'] = true;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Commande envoyée à Power Delivery avec succès';
                $result['tracking_number'] = $tracking_number;
                $result['parcel_code'] = $parcel_code;
                
            } else {
                // Enhanced error handling for Power Delivery
                $error_msg = 'Erreur inconnue de l\'API';
                
                if (isset($response_data['message'])) {
                    $error_msg = $response_data['message'];
                } elseif (isset($response_data['error'])) {
                    $error_msg = $response_data['error'];
                } elseif (isset($response_data['errors']) && is_array($response_data['errors'])) {
                    $error_msg = implode(', ', $response_data['errors']);
                }
                
                // HTTP status specific messages
                if ($http_code === 400) {
                    $error_msg = 'Requête invalide - Vérifiez le format des données: ' . $error_msg;
                } elseif ($http_code === 401) {
                    $error_msg = 'Clé API invalide ou non autorisée';
                } elseif ($http_code === 403) {
                    $error_msg = 'Accès refusé - Vérifiez vos permissions API';
                } elseif ($http_code === 404) {
                    $error_msg = 'Endpoint non trouvé';
                } elseif ($http_code === 422) {
                    $error_msg = 'Données de validation échouées: ' . $error_msg;
                } elseif ($http_code === 500) {
                    $error_msg = 'Erreur interne du serveur Power Delivery';
                } elseif ($http_code === 502) {
                    $error_msg = 'Bad Gateway - Serveur Power Delivery indisponible';
                } elseif ($http_code === 503) {
                    $error_msg = 'Service Power Delivery indisponible';
                } else {
                    $error_msg = 'Erreur HTTP ' . $http_code . ': ' . $error_msg;
                }
                
                $result['message'] = 'Erreur Power Delivery: ' . $error_msg;
                $result['api_response'] = $response_data;
                $result['http_code'] = $http_code;
                
                // Log detailed error information
                error_log("Power Delivery API Error - Code: $http_code, Message: " . $error_msg);
                error_log("Full Response: " . $response);
            }

        } elseif ($company_name === 'quicklivraison' || $company_name === 'quick_livraison' || $company_name === 'quick') {
       
            // First check if order already has a tracking number
            if (!empty($order['tracking_number'])) {
                $duplicate_check = checkQuickLivraisonPackageExists(
                    $shipping_company['api_key'],
                    $order['tracking_number']
                );
                
                if ($duplicate_check['exists']) {
                    $result['notification_sent'] = false;
                    $result['company_name'] = $shipping_company['company_name'];
                    $result['message'] = 'Colis déjà envoyé à QuickLivraison avec le numéro: ' . $order['tracking_number'];
                    $result['tracking_number'] = $order['tracking_number'];
                    $result['duplicate_found'] = true;
                    return $result;
                }
            }
            
            // Validate required fields for QuickLivraison
            $missing_fields = [];
            $validation_errors = [];
            
            // Check required fields
            if (empty(trim($order['customer_name'] ?? ''))) {
                $missing_fields[] = 'Nom du destinataire (name)';
            }
            
            if (empty(trim($order['customer_phone'] ?? ''))) {
                $missing_fields[] = 'Téléphone du destinataire (phone)';
            } else {
                $phone = formatQuickLivraisonPhone($order['customer_phone']);
                if (strlen($phone) !== 9) {
                    $validation_errors[] = 'Numéro de téléphone invalide (doit être 9 chiffres)';
                }
            }
            
            if (empty(trim($order['shipping_address'] ?? ''))) {
                $missing_fields[] = 'Adresse complète (address)';
            }
            
            // Validate total amount
            if (empty($order['total_amount']) || !is_numeric($order['total_amount']) || floatval($order['total_amount']) < 0) {
                $validation_errors[] = 'Montant total invalide';
            }
            
            // Get district ID from city name
            $city_name = getCityNameFromBillingAddress($order ?? '');
            $district_id = getQuickLivraisonDistrictId($city_name);
            
            if (empty($district_id)) {
                $missing_fields[] = 'ID du district (district_id) - Ville "' . $city_name . '" non trouvée';
                error_log("QuickLivraison - District ID not found for city: " . $city_name);
            }
            
            // If there are missing required fields, return error
            if (!empty($missing_fields) || !empty($validation_errors)) {
                $result['notification_sent'] = false;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Erreurs de validation: ' . 
                    implode(', ', $missing_fields) . 
                    (!empty($validation_errors) ? ' | ' . implode(', ', $validation_errors) : '');
                $result['missing_fields'] = $missing_fields;
                $result['validation_errors'] = $validation_errors;
                return $result;
            }
            
            // Format phone number
            $phone = formatQuickLivraisonPhone($order['customer_phone']);
            
            // Get order items
            $items = getOrderItems($db, $order['id']);
            
            // Generate unique code if needed (QuickLivraison will generate if omitted)
            $custom_code = null;
            if (!empty($order['order_number'])) {
                $custom_code = 'OBG' . preg_replace('/[^a-zA-Z0-9]/', '', $order['order_number']);
                $custom_code = substr($custom_code, 0, 50);
            }
            
            // Prepare payload for QuickLivraison
            $payload = [
                'api_key' => $shipping_company['api_key'],
                'district_id' => $district_id,
                'name' => substr(trim($order['customer_name']), 0, 100),
                'amount' => floatval($order['total_amount']),
                'phone' => $phone,
                'address' => substr(trim($order['shipping_address']), 0, 255),
                'note' => substr($order['shipping_method'] ?? 'Commande e-commerce', 0, 255),
                'open' => true,  // Allow opening by default
                'try' => true    // Allow multiple delivery attempts
            ];
            
            // Add custom code if provided
            if (!empty($custom_code)) {
                $payload['code'] = $custom_code;
            }
            
            // Add product details if items exist (non-stock mode)
            if (!empty($items)) {
                $first_item = $items[0];
                $payload['prd_name'] = substr($first_item['name'] ?? 'Produit', 0, 100);
                $payload['qte_prd'] = $first_item['quantity'] ?? 1;
            } else {
                $payload['prd_name'] = 'Commande client';
                $payload['qte_prd'] = 1;
            }
            
            // Log the payload for debugging (without API key)
            $debug_payload = $payload;
            unset($debug_payload['api_key']);
            error_log("QuickLivraison Payload: " . json_encode($debug_payload, JSON_UNESCAPED_UNICODE));
            
            $url = 'https://clients.quicklivraison.ma/api/deliveries/store';
            
            // Build query string (since API expects form data, not JSON)
            $postData = http_build_query($payload);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            // Enable verbose for debugging
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            
            // Get verbose output
            rewind($verbose);
            $verbose_log = stream_get_contents($verbose);
            fclose($verbose);
            
            curl_close($ch);
            
            // Log the response
            error_log("QuickLivraison Request URL: $url");
            error_log("QuickLivraison Response - HTTP Code: $http_code");
            error_log("QuickLivraison Response Body: " . $response);
            
            // Handle cURL errors
            if ($response === false) {
                $result['message'] = 'Erreur de connexion à QuickLivraison: ' . $curl_error;
                error_log("QuickLivraison cURL Error: " . $curl_error);
                return $result;
            }
            
            $response_data = json_decode($response, true);
            
            // Check for success response
            if ($http_code === 200 && isset($response_data['success']) && isset($response_data['tracking_number'])) {
                $tracking_number = $response_data['tracking_number'];
                
                // Update the order with the new tracking number
                $db->update('orders', $order['id'], [
                    'tracking_number' => $tracking_number,
                    'shipping_company_id' => $shipping_company['shipping_company_id'],
                    'shipping_status' => 'processing',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                $result['notification_sent'] = true;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = $response_data['success'] ?? 'Commande envoyée à QuickLivraison avec succès';
                $result['tracking_number'] = $tracking_number;
                
            } else {
                // Error handling
                $error_msg = 'Erreur inconnue de l\'API';
                
                if (isset($response_data['error'])) {
                    $error_msg = $response_data['error'];
                } elseif (isset($response_data['message'])) {
                    $error_msg = $response_data['message'];
                }
                
                // HTTP status specific messages
                if ($http_code === 400) {
                    $error_msg = 'Requête invalide - Vérifiez les paramètres: ' . $error_msg;
                } elseif ($http_code === 401) {
                    $error_msg = 'Clé API invalide ou non autorisée';
                } elseif ($http_code === 403) {
                    $error_msg = 'Accès refusé - Vérifiez vos permissions API';
                } elseif ($http_code === 404) {
                    $error_msg = 'Endpoint non trouvé';
                } elseif ($http_code === 422) {
                    $error_msg = 'Données de validation échouées: ' . $error_msg;
                } elseif ($http_code === 500) {
                    $error_msg = 'Erreur interne du serveur QuickLivraison';
                }
                
                $result['message'] = 'Erreur QuickLivraison: ' . $error_msg;
                $result['api_response'] = $response_data;
                $result['http_code'] = $http_code;
                
                error_log("QuickLivraison API Error - Code: $http_code, Message: " . $error_msg);
            }
        
        } elseif ($company_name === 'infinidis' || $company_name === 'infinidis.ma') {
    
            // Check if order already has a tracking number
            if (!empty($order['tracking_number'])) {
                $duplicate_check = checkInfinidisPackageExists(
                    $shipping_company['api_key'],
                    $order['tracking_number']
                );
                
                if ($duplicate_check['exists']) {
                    $result['notification_sent'] = false;
                    $result['company_name'] = $shipping_company['company_name'];
                    $result['message'] = 'Colis déjà envoyé à INFINIDIS avec le numéro: ' . $order['tracking_number'];
                    $result['tracking_number'] = $order['tracking_number'];
                    $result['duplicate_found'] = true;
                    return $result;
                }
            }
            
            // Validate required fields for INFINIDIS
            $missing_fields = [];
            $validation_errors = [];
            
            // Check required fields based on your documentation
            if (empty(trim($order['customer_name'] ?? ''))) {
                $missing_fields[] = 'Nom complet du client (fullname)';
            }
            
            if (empty(trim($order['customer_email'] ?? ''))) {
                $missing_fields[] = 'Email du client (email)';
            } elseif (!filter_var($order['customer_email'], FILTER_VALIDATE_EMAIL)) {
                $validation_errors[] = 'Email invalide';
            }
            
            if (empty(trim($order['customer_phone'] ?? ''))) {
                $missing_fields[] = 'Téléphone (phone)';
            }
            
            if (empty(trim($order['shipping_address'] ?? ''))) {
                $missing_fields[] = 'Adresse (address)';
            }
            
            if (empty(trim($order['customer_ville'] ?? ''))) {
                $missing_fields[] = 'Ville (city)';
            }
            
            if (empty($order['total_amount']) || !is_numeric($order['total_amount'])) {
                $validation_errors[] = 'Prix invalide (price)';
            }
            
            // If there are missing required fields, return error
            if (!empty($missing_fields) || !empty($validation_errors)) {
                $result['notification_sent'] = false;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Erreurs de validation: ' . 
                    implode(', ', $missing_fields) . 
                    (!empty($validation_errors) ? ' | ' . implode(', ', $validation_errors) : '');
                $result['missing_fields'] = $missing_fields;
                $result['validation_errors'] = $validation_errors;
                return $result;
            }
            
            // Get order items for product info
            $items = getOrderItems($db, $order['id']);
            
            // Generate a unique order code if needed
            $order_code = !empty($order['order_number']) ? $order['order_number'] : 'CMD_' . $order['id'];
            
            // Prepare payload for INFINIDIS
            $payload = [
                'fullname' => substr(trim($order['customer_name']), 0, 100),
                'email' => 'OBGecom@infinidis.ma',
                'code' => $order_code,
                'product' => !empty($items) ? substr($items[0]['name'] ?? 'Produit', 0, 100) : 'Commande',
                'qty' => !empty($items) ? array_sum(array_column($items, 'quantity')) : 1,
                'phone' => formatInfinidisPhone($order['customer_phone']),
                'address' => substr(trim($order['shipping_address']), 0, 255),
                'city' => substr(trim($order['customer_ville']), 0, 100),
                'price' => floatval($order['total_amount']),
                'note' => substr($order['shipping_method'] ?? 'Commande e-commerce', 0, 255)
            ];
            
            // Log the payload for debugging
            error_log("INFINIDIS Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
            
            // Determine the correct endpoint (try both formats)
            $base_url = rtrim($shipping_company['api_url'], '/');
            $endpoints_to_try = [
                $base_url . '/sendtoinfinidis.php',
                $base_url . '/api/sendtoinfinidis.php',
                $base_url
            ];
            
            $response_data = null;
            $http_code = null;
            $success = false;
            
            foreach ($endpoints_to_try as $url) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $shipping_company['api_key'],
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json'
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
                
                error_log("INFINIDIS Attempt URL: $url - HTTP Code: $http_code");
                error_log("INFINIDIS Response: " . $response);
                
                if ($http_code === 200) {
                    $response_data = json_decode($response, true);
                    if (isset($response_data['success']) && $response_data['success'] === true) {
                        $success = true;
                        break;
                    }
                }
            }
            
            if ($success && $response_data) {
                // Extract tracking number from response
                $tracking_number = $response_data['code'] ?? $order_code;
                
                // Update the order with tracking number
                $db->update('orders', $order['id'], [
                    'tracking_number' => $tracking_number,
                    'shipping_company_id' => $shipping_company['shipping_company_id'],
                    'shipping_status' => 'processing',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
                $result['notification_sent'] = true;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = $response_data['message'] ?? 'Commande envoyée à INFINIDIS avec succès';
                $result['tracking_number'] = $tracking_number;
                
            } else {
                $error_msg = 'Erreur inconnue de l\'API';
                if ($response_data && isset($response_data['message'])) {
                    $error_msg = $response_data['message'];
                } elseif ($http_code === 400) {
                    $error_msg = 'Requête invalide - Vérifiez les paramètres';
                } elseif ($http_code === 401) {
                    $error_msg = 'Clé API invalide ou non autorisée';
                } elseif ($http_code === 405) {
                    $error_msg = 'Méthode non autorisée - Utilisez POST ou GET';
                }
                
                $result['message'] = 'Erreur INFINIDIS: ' . $error_msg;
                $result['api_response'] = $response_data;
                $result['http_code'] = $http_code;
            }
        } elseif ($company_name === 'ortalog') {

            // Ortalog uses JWT auth (email + password), not a static API key
            if (empty($shipping_company['auth_email']) || empty($shipping_company['auth_password'])) {
                $result['notification_sent'] = false;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Les identifiants Ortalog (email/mot de passe) ne sont pas configurés.';
                return $result;
            }
        
            // Duplicate check: Ortalog doesn't expose a lookup-by-code endpoint,
            // but if the order already has a tracking number we consider it sent.
            if (!empty($order['tracking_number'])) {
                $result['notification_sent'] = false;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Colis déjà envoyé à Ortalog avec le numéro: ' . $order['tracking_number'];
                $result['tracking_number'] = $order['tracking_number'];
                $result['duplicate_found'] = true;
                return $result;
            }
        
            // ---- Validate required fields ----
            $missing_fields = [];
            $validation_errors = [];
        
            if (empty(trim($order['customer_name'] ?? ''))) {
                $missing_fields[] = 'Nom du destinataire (receiver)';
            }
        
            if (empty(trim($order['customer_phone'] ?? ''))) {
                $missing_fields[] = 'Téléphone du destinataire (phone)';
            }
        
            if (empty(trim($order['shipping_address'] ?? ''))) {
                $missing_fields[] = 'Adresse complète (address)';
            }
        
            if (empty($order['total_amount']) || !is_numeric($order['total_amount'])) {
                $validation_errors[] = 'Prix invalide (price)';
            }
        
            if (!empty($missing_fields) || !empty($validation_errors)) {
                $result['notification_sent'] = false;
                $result['company_name'] = $shipping_company['company_name'];
                $result['message'] = 'Erreurs de validation: ' .
                    implode(', ', $missing_fields) .
                    (!empty($validation_errors) ? ' | ' . implode(', ', $validation_errors) : '');
                $result['missing_fields'] = $missing_fields;
                $result['validation_errors'] = $validation_errors;
                return $result;
            }
        
            // ---- Build Ortalog base URL ----
            $base_url = rtrim($shipping_company['api_url'], '/');
            if (strpos($base_url, '/api') === false) {
                $base_url = $base_url . '/api';
            }
        
            $email    = $shipping_company['auth_email'];
            $password = $shipping_company['auth_password'];
        
            try {
                // 1. Login → JWT
                $login_result = loginToOrtalog($base_url, $email, $password);
                if (!$login_result['success']) {
                    $result['message'] = 'Erreur de connexion à Ortalog: ' . ($login_result['message'] ?? 'Échec auth');
                    return $result;
                }
                $jwt_token = $login_result['token'];

                // 2. Resolve city ID (fallback to Casablanca if not found)
                $city_name = getCityNameFromBillingAddress($order ?? '');
                $city_id   = getOrtalogCityId($base_url, $jwt_token, $city_name ?: 'Casablanca');
        
                if (empty($city_id)) {
                    $city_id = getOrtalogCityId($base_url, $jwt_token, 'Casablanca');
                    if (empty($city_id)) {
                        $result['message'] = 'Ville non trouvée dans Ortalog: ' . ($city_name ?: 'inconnue');
                        return $result;
                    }
                }
        
                // 3. Create parcel
                $parcel_result = createOrtalogParcel($db, $base_url, $jwt_token, $order, $city_id);
        
                if (!$parcel_result['success']) {
                    $result['message'] = 'Erreur de création du colis Ortalog: ' . ($parcel_result['message'] ?? 'Échec');
                    return $result;
                }
        
                // 4. Persist tracking number on the order
                $tracking_number = $parcel_result['parcel_code'] ?? '';
                if (!empty($tracking_number)) {
                    $db->update('orders', $order['id'], [
                        'tracking_number'     => $tracking_number,
                        'shipping_company_id' => $shipping_company['shipping_company_id'],
                    ]);
                }
        
                $result['notification_sent'] = true;
                $result['company_name']      = $shipping_company['company_name'];
                $result['message']           = $parcel_result['message'] ?? 'Commande envoyée à Ortalog avec succès';
                $result['tracking_number']   = $tracking_number;
        
            } catch (Exception $e) {
                error_log("Ortalog shipping error: " . $e->getMessage());
                $result['message'] = 'Erreur Ortalog: ' . $e->getMessage();
            }
        } else {
            // Future support for other companies
            $result['message'] = "Support pour la compagnie {$shipping_company['company_name']} non encore implémenté.";
        }
        
    } catch (Exception $e) {
        error_log("Shipping company notification failed: " . $e->getMessage());
        $result['message'] = 'Erreur lors de l\'envoi à la compagnie de livraison: ' . $e->getMessage();
    }
    
    return $result;
}

/**
 * Check if package exists in INFINIDIS
*/
function checkInfinidisPackageExists($apiKey, $trackingNumber) {
    // INFINIDIS might not have a tracking endpoint yet
    // For now, return false to allow sending
    return ['exists' => false, 'message' => 'Duplicate check not available for INFINIDIS'];
}

/**
 * Format phone number for INFINIDIS
*/
function formatInfinidisPhone($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Moroccan numbers: if it starts with 212, convert to 0
    if (substr($phone, 0, 3) === '212') {
        $phone = '0' . substr($phone, 3);
    }
    
    // Ensure it starts with 0 for Moroccan numbers
    if (strlen($phone) === 9 && substr($phone, 0, 1) !== '0') {
        $phone = '0' . $phone;
    }
    
    return $phone;
}

/**
 * Get Power Delivery city ID by name */

function getPowerDeliveryCityId($apiKey, $city_name) {
    $cities = getPowerDeliveryCities($apiKey);
    
    if (empty($cities)) {
        return null;
    }
    
    $city_name = strtolower(trim($city_name));
    $best_match_id = null;
    $best_similarity = 0;
    
    foreach ($cities as $city) {
        // Fixed: changed 'name' to 'city'
        if (!isset($city['id']) || !isset($city['city'])) {
            continue;
        }
        
        $city_ref = strtolower(trim($city['city']));
        
        // 1. Exact match
        if ($city_ref === $city_name) {
            return $city['id'];
        }
        
        // 2. Contains match (handles partial matches)
        if (strpos($city_ref, $city_name) !== false || strpos($city_name, $city_ref) !== false) {
            if (strlen($city_name) >= 3) {
                return $city['id'];
            }
        }
        
        // 3. Levenshtein distance (handles typos better)
        $distance = levenshtein($city_name, $city_ref);
        $max_length = max(strlen($city_name), strlen($city_ref));
        $similarity = (($max_length - $distance) / $max_length) * 100;
        
        if ($similarity > $best_similarity && $similarity >= 40) {
            $best_similarity = $similarity;
            $best_match_id = $city['id'];
        }
        
        // 4. Similar text as fallback
        similar_text($city_name, $city_ref, $percent);
        if ($percent > $best_similarity && $percent >= 35) {
            $best_similarity = $percent;
            $best_match_id = $city['id'];
        }
    }

    return $best_match_id;
}

/**
 * Generate unique parcel code for Power Delivery
*/
function generateUniqueParcelCode($order) {
    $timestamp = time();
    $order_id = $order['id'] ?? '000';
    $random = rand(1000, 9999);
    
    return "PD{$timestamp}{$order_id}{$random}";
}
/**
 * Get Power Delivery cities from API
 */
function getPowerDeliveryCities($apiKey) {
    $url = 'https://elog.ma/apiclient/listcities';
    
    // Vérifier que la clé API n'est pas vide
    if (empty($apiKey)) {
        error_log("Power Delivery Cities API Error: API Key is empty");
        return [];
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $apiKey,
        'Content-Type: ' . 'application/json',
        'User-Agent: ' . 'OBGECOM-Shipment/1.0'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    // Activer le verbose pour le debugging
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    
    // Récupérer les logs verbose
    rewind($verbose);
    $verbose_log = stream_get_contents($verbose);
    fclose($verbose);
    
    curl_close($ch);
    
    // Log détaillé pour le debugging
    error_log("=== Power Delivery Cities API Debug ===");
    error_log("URL: " . $url);
    error_log("HTTP Code: " . $http_code);
    error_log("cURL Error: " . $curl_error);
    error_log("cURL Error No: " . $curl_errno);
    error_log("Response Length: " . strlen($response));
    error_log("Response: " . $response);
    error_log("Verbose Log: " . $verbose_log);
    error_log("=== End Debug ===");
    
    // Gestion des erreurs cURL
    if ($curl_errno > 0) {
        return [];
    }
    
    if ($http_code !== 200) {
        return [];
    }
    
    // Vérifier que la réponse n'est pas vide
    if (empty($response)) {
        return [];
    }
    
    $response_data = json_decode($response, true);
    
    // Vérifier les erreurs JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    
    // Plusieurs formats de réponse possibles
    if (isset($response_data['success']) && $response_data['success'] === true) {
        if (isset($response_data['cities']) && is_array($response_data['cities'])) {
            return $response_data['cities'];
        } elseif (isset($response_data['data']) && is_array($response_data['data'])) {
            return $response_data['data'];
        } elseif (isset($response_data['villes']) && is_array($response_data['villes'])) {
            return $response_data['villes'];
        }
    }
    
    // Vérifier d'autres formats de réponse
    if (is_array($response_data) && !empty($response_data)) {
        // Si c'est directement un tableau de villes
        $first_item = reset($response_data);
        if (isset($first_item['id']) && isset($first_item['name'])) {
            return $response_data;
        }
        
        // Chercher un tableau de villes dans n'importe quelle clé
        foreach ($response_data as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $first_city = reset($value);
                if (isset($first_city['id']) || isset($first_city['name'])) {
                    return $value;
                }
            }
        }
    }

    return [];
}
/**
 * Check if package exists in Power Delivery
 */
function checkPowerDeliveryPackageExists($apiKey, $trackingNumber) {
    $url = 'https://elog.ma/apiclient/trackparcel?parcel_code=' . urlencode($trackingNumber);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        
        if (isset($response_data['success']) && $response_data['success'] === true && isset($response_data['parcel'])) {
            return [
                'exists' => true,
                'package_info' => $response_data
            ];
        }
    }
    
    return [
        'exists' => false,
        'error' => $response ?? null
    ];
}

/**
 * Get ForceLog city data by name
 */
function getForceLogCityData($apiKey, $city_name) {
    $cities = getForceLogCities($apiKey);
    
    if (empty($cities)) {
        return null;
    }
    
    $city_name = strtolower(trim($city_name));
    
    foreach ($cities['Cities'] as $city_id => $city) {
        if (!isset($city['NAME'])) {
            continue;
        }
        
        $city_ref = strtolower(trim($city['NAME']));
        
        // Exact match
        if ($city_ref === $city_name) {
            return [
                'id' => $city_id,
                'name' => $city['NAME'],
                'code' => $city['CODE'] ?? ''
            ];
        }
        
        // Remove accents and special characters for better matching
        $clean_city_name = removeAccents($city_name);
        $clean_city_ref = removeAccents($city_ref);
        
        if ($clean_city_ref === $clean_city_name) {
            return [
                'id' => $city_id,
                'name' => $city['NAME'],
                'code' => $city['CODE'] ?? ''
            ];
        }
        
        // Contains match
        if (strpos($clean_city_ref, $clean_city_name) !== false || 
            strpos($clean_city_name, $clean_city_ref) !== false) {
            return [
                'id' => $city_id,
                'name' => $city['NAME'],
                'code' => $city['CODE'] ?? ''
            ];
        }
    }

    return null;
}

/**
 * Format phone number for ForceLog
 */
function formatPhoneNumber($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Ensure it starts with 0 for Moroccan numbers
    if (strlen($phone) === 9 && substr($phone, 0, 1) !== '0') {
        $phone = '0' . $phone;
    }
    
    // If it's 10 digits and starts with 0, it's good
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        return $phone;
    }
    
    // Default fallback
    return $phone;
}

/**
 * Remove accents from string
*/
function removeAccents($string) {
    $string = htmlentities($string, ENT_NOQUOTES, 'UTF-8');
    $string = preg_replace('#&([A-za-z])(?:acute|cedil|caron|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $string);
    $string = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $string);
    $string = preg_replace('#&[^;]+;#', '', $string);
    return $string;
}

function cleanApiBaseUrl($url) {
    $patterns = [
        '/add[-_]parcel$/i',
        '/create[-_]parcel$/i',
        '/parcel[-_]add$/i',
        '/parcel[-_]create$/i',
        '/send[-_]parcel$/i'
    ];
    
    foreach ($patterns as $pattern) {
        $url = preg_replace($pattern, '', $url);
    }

    return rtrim($url, '/');
}

/**
 * Standalone function to check duplicates for any shipping company
*/
function checkShippingDuplicate($shippingCompany, $credentials, $trackingNumber) {
    switch (strtolower($shippingCompany)) {
        case 'ozonexpress':
            return checkOzoneExpressPackageExists(
                $credentials['api_url'],
                $trackingNumber
            );
            
        case 'forcelog':
            return checkForceLogPackageExists(
                $credentials['api_key'],
                $trackingNumber
            );
            
        // Add other shipping companies here as needed
        case 'amana':
        case 'dhl':
        case 'fedex':
            return ['exists' => false, 'message' => 'Duplicate check not implemented for this company'];
            
        default:
            return ['exists' => false, 'message' => 'Unknown shipping company'];
    }
}

function getOzonCityId($city_name) {
    $city_api_url = "https://api.ozonexpress.ma/cities";
    $ch = curl_init($city_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['CITIES'])) return null;
    
    $cities = $data['CITIES'];
    if (!is_array($cities)) return null;
    
    $city_name = strtolower(trim($city_name));
    $best_match_id = null;
    $best_similarity = 0;
    
    foreach ($cities as $city) {
        if (!isset($city['NAME']) || !isset($city['ID'])) {
            continue;
        }
        
        $city_ref = strtolower(trim($city['NAME']));
        
        // 1. Exact match
        if ($city_ref === $city_name) {
            return $city['ID'];
        }
        
        // 2. Contains match (handles partial matches)
        if (strpos($city_ref, $city_name) !== false || strpos($city_name, $city_ref) !== false) {
            if (strlen($city_name) >= 3) {
                return $city['ID'];
            }
        }
        
        // 3. Levenshtein distance (handles typos better)
        $distance = levenshtein($city_name, $city_ref);
        $max_length = max(strlen($city_name), strlen($city_ref));
        $similarity = (($max_length - $distance) / $max_length) * 100;
        
        if ($similarity > $best_similarity && $similarity >= 40) {
            $best_similarity = $similarity;
            $best_match_id = $city['ID'];
        }
        
        // 4. Similar text as fallback
        similar_text($city_name, $city_ref, $percent);
        if ($percent > $best_similarity && $percent >= 35) {
            $best_similarity = $percent;
            $best_match_id = $city['ID'];
        }
    }

    return $best_match_id;
}

function getCityNameFromBillingAddress($order) {
    // 1. First, check customer_ville (direct city name)
    if (!empty($order['customer_ville'])) {
        return ucfirst(strtolower(trim($order['customer_ville'])));
    }

    // 2. Fallback: Check billing_address (formatted as "Name, City, Country")
    if (!empty($order['billing_address'])) {
        $parts = explode(',', $order['billing_address']);
        if (count($parts) >= 2) {
            return ucfirst(strtolower(trim($parts[1]))); // Return the city (2nd part)
        }
        // If no commas, return the full string as fallback
        return ucfirst(strtolower(trim($order['billing_address'])));
    }

    // 3. Legacy fallback (if billing/city fields exist separately)
    if (!empty($order['city'])) {
        return ucfirst(strtolower(trim($order['city'])));
    }

    return null; // No city found
}

/**
 * QuickLivraison Integration Functions
*/

/**
 * Get QuickLivraison cities list
*/
function getQuickLivraisonCities() {
    $url = 'https://clients.quicklivraison.ma/api/cities/frais-delay';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error || $http_code !== 200) {
        error_log("QuickLivraison Cities API Error: $curl_error (HTTP: $http_code)");
        return [];
    }
    
    $data = json_decode($response, true);
    
    if (!is_array($data)) {
        error_log("QuickLivraison Cities API: Invalid response format - not an array");
        return [];
    }
    
    // The cities are inside a "cities" key
    if (isset($data['cities']) && is_array($data['cities'])) {
        error_log("QuickLivraison Cities API: Retrieved " . count($data['cities']) . " cities");
        return $data['cities'];
    }
    
    // Fallback: if no 'cities' key, return the data as-is
    error_log("QuickLivraison Cities API: No 'cities' key found, returning raw data");
    return $data;
}


/**
 * Get QuickLivraison district ID by city name
*/
function getQuickLivraisonDistrictId($city_name) {
    $cities = getQuickLivraisonCities();
    
    if (empty($cities)) {
        error_log("QuickLivraison - No cities returned from API");
        return null;
    }
    
    $city_name = strtolower(trim($city_name));
    error_log("QuickLivraison - Looking for city: '$city_name'");
    
    // Direct mapping for common Moroccan cities (fallback if API fails)
    $direct_mappings = [
        'ouarzazate' => 1316,
        'warzazate' => 1316,
        'ourzazate' => 1316,
        'casablanca' => 43,
        'marrakech' => 49,
        'rabat' => 55,
        'tanger' => 103,
        'fes' => 577,
        'agadir' => 37,
        'meknes' => 547,
        'kenitra' => 1604,
        'tetouan' => 133,
        'safi' => 427,
        'el jadida' => 469,
        'nador' => 97,
        'settat' => 680,
        'khouribga' => 1094,
        'beni mellal' => 313,
        'laayoune' => 307,
        'dakhla' => 716,
    ];
    
    // First check direct mapping
    $search_name = strtolower(trim($city_name));
    if (isset($direct_mappings[$search_name])) {
        error_log("QuickLivraison - Direct mapping found for: $city_name -> ID: " . $direct_mappings[$search_name]);
        return $direct_mappings[$search_name];
    }
    
    // Search through API cities
    foreach ($cities as $city) {
        if (!is_array($city)) {
            continue;
        }
        
        // Get city name and ID from the correct keys
        $city_ref = '';
        $city_id = null;
        
        // The API uses 'city_name' and 'city_id'
        if (isset($city['city_name'])) {
            $city_ref = strtolower(trim($city['city_name']));
        } elseif (isset($city['name'])) {
            $city_ref = strtolower(trim($city['name']));
        } else {
            continue;
        }
        
        if (isset($city['city_id'])) {
            $city_id = $city['city_id'];
        } elseif (isset($city['id'])) {
            $city_id = $city['id'];
        } else {
            continue;
        }
        
        // Exact match
        if ($city_ref === $search_name) {
            error_log("QuickLivraison - Exact match found: '$city_ref' (ID: $city_id)");
            return $city_id;
        }
        
        // Case-insensitive exact match
        if ($city_ref === $search_name) {
            error_log("QuickLivraison - Case-insensitive match found: '$city_ref' (ID: $city_id)");
            return $city_id;
        }
    }
    
    // If still not found, try partial matching
    foreach ($cities as $city) {
        if (!is_array($city)) {
            continue;
        }
        
        $city_ref = '';
        $city_id = null;
        
        if (isset($city['city_name'])) {
            $city_ref = strtolower(trim($city['city_name']));
        } elseif (isset($city['name'])) {
            $city_ref = strtolower(trim($city['name']));
        } else {
            continue;
        }
        
        if (isset($city['city_id'])) {
            $city_id = $city['city_id'];
        } elseif (isset($city['id'])) {
            $city_id = $city['id'];
        } else {
            continue;
        }
        
        // Contains match
        if (strpos($city_ref, $search_name) !== false || strpos($search_name, $city_ref) !== false) {
            if (strlen($search_name) >= 3) {
                error_log("QuickLivraison - Contains match: '$search_name' in '$city_ref' (ID: $city_id)");
                return $city_id;
            }
        }
        
        // Remove accents for better matching
        $clean_search = removeAccents($search_name);
        $clean_city = removeAccents($city_ref);
        
        if ($clean_city === $clean_search) {
            error_log("QuickLivraison - Accent-free match: '$clean_search' (ID: $city_id)");
            return $city_id;
        }
    }
    
    error_log("QuickLivraison - No match found for city: '$city_name'");
    
    // Log first 20 cities for debugging
    $sample_cities = [];
    foreach (array_slice($cities, 0, 20) as $city) {
        if (is_array($city)) {
            $name = $city['city_name'] ?? $city['name'] ?? 'unknown';
            $id = $city['city_id'] ?? $city['id'] ?? '?';
            $sample_cities[] = "$name (ID: $id)";
        }
    }
    error_log("QuickLivraison - Available cities sample: " . implode(', ', $sample_cities));
    
    return null;
}
/**
 * Check if package exists in QuickLivraison
*/
function checkQuickLivraisonPackageExists($apiKey, $trackingNumber) {
    $url = 'https://clients.quicklivraison.ma/api/getParcelDetails/' . urlencode($trackingNumber);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        
        if (isset($response_data['tracking_number']) && !empty($response_data['tracking_number'])) {
            return [
                'exists' => true,
                'package_info' => $response_data
            ];
        }
    }
    
    return [
        'exists' => false,
        'error' => $response ?? null
    ];
}

/**
 * Format phone number for QuickLivraison (9 digits, no spaces)
*/

function formatQuickLivraisonPhone($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Moroccan numbers: if it starts with 212, convert to 0
    if (substr($phone, 0, 3) === '212') {
        $phone = '0' . substr($phone, 3);
    }
    
    // If it's 10 digits starting with 0, take last 9 digits
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        $phone = substr($phone, 1);
    }
    
    // If it's 9 digits, it's good
    if (strlen($phone) === 9) {
        return $phone;
    }
    
    // If it's 8 digits, add leading 0? Actually QuickLivraison wants 9 digits without leading 0
    if (strlen($phone) === 8) {
        return '6' . $phone;
    }
    
    // Default fallback - take last 9 digits
    return substr($phone, -9);
}

/**
 * Login to Ortalog and return a JWT token
*/
function loginToOrtalog($base_url, $email, $password) {
    $url = $base_url . '/auth/login/client';

    $postData = [
        'identifiant' => $email,
        'password'    => $password,
    ];
    $jsonData = json_encode($postData);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($jsonData),
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    error_log("Ortalog login - HTTP: $http_code | Error: " . ($curl_err ?: 'none'));

    if ($response === false) {
        return ['success' => false, 'message' => 'Erreur de connexion: ' . $curl_err];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Réponse invalide: ' . substr((string)$response, 0, 200)];
    }

    if ($http_code !== 200) {
        return ['success' => false, 'message' => $data['message'] ?? ('Erreur auth (HTTP ' . $http_code . ')')];
    }

    if (!isset($data['token'])) {
        return ['success' => false, 'message' => 'Token JWT non reçu'];
    }

    return ['success' => true, 'token' => $data['token'], 'user' => $data['user'] ?? null];
}

/**
 * Resolve an Ortalog city ID from a city name
*/
function getOrtalogCityId($base_url, $jwt_token, $city_name) {
    $url = $base_url . '/parcels/client/new';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $jwt_token,
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($response)) {
        error_log("Ortalog cities - HTTP: $http_code");
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['cities']) || !is_array($data['cities'])) {
        return null;
    }

    $city_name      = strtolower(trim($city_name));
    $best_match_id  = null;
    $best_similarity = 0;

    foreach ($data['cities'] as $city) {
        if (!isset($city['label'], $city['id'])) continue;

        $city_ref = strtolower(trim($city['label']));

        // Exact match
        if ($city_ref === $city_name) return $city['id'];

        // Contains match
        if (strpos($city_ref, $city_name) !== false || strpos($city_name, $city_ref) !== false) {
            if (strlen($city_name) >= 3) return $city['id'];
        }

        // Levenshtein fallback
        $distance   = levenshtein($city_name, $city_ref);
        $max_length = max(strlen($city_name), strlen($city_ref));
        if ($max_length > 0) {
            $similarity = (($max_length - $distance) / $max_length) * 100;
            if ($similarity > $best_similarity && $similarity >= 40) {
                $best_similarity = $similarity;
                $best_match_id   = $city['id'];
            }
        }
    }

    return $best_match_id;
}

/**
 * Fetch the list of products available in Ortalog's client stock
*/
function getOrtalogStockProducts($base_url, $jwt_token) {
    $url = $base_url . '/stock/client/produits';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $jwt_token,
        'Accept: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) return [];
    return json_decode($response, true) ?: [];
}

/**
 * Create a parcel in Ortalog (from stock) and return its code
*/
function createOrtalogParcel($db, $base_url, $jwt_token, $order, $city_id) {
    $url = $base_url . '/parcels/client';

    $items = getOrderItems($db, $order['id']);

    // ---- Normalize phone → 10 digits starting with 0 ----
    $phone = preg_replace('/[^0-9]/', '', $order['customer_phone'] ?? '');
    if (strpos($phone, '00212') === 0) {
        $phone = substr($phone, 5);
    } elseif (strpos($phone, '212') === 0) {
        $phone = substr($phone, 3);
    }
    if (strlen($phone) === 9 && $phone[0] !== '0') {
        $phone = '0' . $phone;
    } elseif (strlen($phone) === 8) {
        $phone = '0' . $phone;
    } elseif (strlen($phone) > 10) {
        $phone = '0' . substr($phone, -9);
    } elseif (strlen($phone) < 10) {
        $phone = '0' . substr($phone, -9);
    }

    // ---- Match order items against Ortalog stock products ----
    $stock_products = getOrtalogStockProducts($base_url, $jwt_token);
    $products       = [];

    foreach ($items as $item) {
        $name  = trim((string)$item['name']);
        $match = null;
        $best_similarity = 0;

        foreach ($stock_products as $sp) {
            if ((int)$sp['active'] !== 1) continue;

            $sp_name = trim((string)$sp['nom']);
            if (strcasecmp($sp_name, $name) === 0) {
                $match = $sp;
                break;
            }
            similar_text(mb_strtolower($sp_name), mb_strtolower($name), $percent);
            if ($percent > $best_similarity && $percent >= 70) {
                $best_similarity = $percent;
                $match = $sp;
            }
        }

        if (!$match) {
            return ['success' => false, 'message' => "Produit introuvable en stock Ortalog: " . $name];
        }
        if ((int)$match['stock'] < (int)$item['quantity']) {
            return ['success' => false, 'message' => "Stock insuffisant pour: " . $item['name']];
        }

        $products[] = ['id' => $match['id'], 'qty' => (int)$item['quantity']];
    }

    // ---- Build unique parcel code ----
    $parcel_code = 'TM' . strtoupper(uniqid()) . str_pad($order['id'], 5, '0', STR_PAD_LEFT);

    $payload = [
        'receiver'  => substr(trim($order['customer_name']), 0, 250),
        'phone'     => $phone,
        'city'      => $city_id,
        'address'   => substr(trim($order['shipping_address']), 0, 2000),
        'price'     => floatval($order['total_amount']),
        'code'      => $parcel_code,
        'note'      => substr($order['shipping_method'] ?? $order['order_note'] ?? 'Commande e-commerce', 0, 2000),
        'open'      => 1,
        'essayage'  => 0,
        'replace'   => 0,
        'fromStock' => 1,
        'products'  => $products,
    ];

    $jsonData = json_encode($payload);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $jwt_token,
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($jsonData),
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    error_log("Ortalog parcel - HTTP: $http_code | Response: " . substr((string)$response, 0, 500));

    if ($response === false) {
        return ['success' => false, 'message' => 'Erreur de connexion: ' . $curl_err];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'message' => 'Réponse invalide du serveur Ortalog'];
    }

    if ($http_code === 201 || $http_code === 200) {
        return [
            'success'     => true,
            'message'     => 'Colis créé avec succès',
            'parcel_id'   => $data['parcel_id'] ?? null,
            'parcel_code' => $data['parcel_code'] ?? $parcel_code,
        ];
    }

    $error_msg = $data['message'] ?? ('Erreur de création du colis (HTTP ' . $http_code . ')');
    return ['success' => false, 'message' => $error_msg];
}

/**
 * Get order items for shipping
*/
function getOrderItems($db, $order_id) {
    $items = $db->getThisQuery("
        SELECT 
            product_name as name,
            quantity,
            unit_price as price,
            product_sku as sku
        FROM order_items 
        WHERE order_id = ?
    ", [$order_id]);
    
    return $items ?? [];
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