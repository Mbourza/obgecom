<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    $agent_info = getCurrentAgentInfo($db);

    if (!$agent_info) {
        throw new Exception('Agent non authentifié');
    }

    $user_id = $agent_info['user_id'];
    $agent_id = $agent_info['agent_id'];

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

        // Record shipping submission by agent
        recordShippingSubmission($db, $order_id, $agent_id, $user_id, 'single', $shipping_result);

        $response = [
            'success' => true,
            'message' => 'Commande soumise au transporteur avec succès',
            'order_id' => $order_id,
            'submitted_by_agent' => $agent_info['name'],
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

                // Record shipping submission by agent
                recordShippingSubmission($db, $order_id, $agent_id, $user_id, 'bulk', $shipping_result);

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
                error_log("Error processing order {$order_id} in bulk shipping by agent: " . $error_message);
            }
        }

        $response = [
            'success' => $success_count > 0,
            'message' => "Traitement terminé: {$success_count} succès, {$failed_count} échecs",
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'submitted_by_agent' => $agent_info['name'],
            'tracking_numbers' => $tracking_numbers,
            'errors' => $errors
        ];

        echo json_encode($response);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action invalide']);
    }

} catch (Exception $e) {
    error_log("Error in agent_shipping_api.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get current agent info from session (email-based login)
 */
function getCurrentAgentInfo($db) {

    if (!isset($_SESSION['user'])) {
        return null;
    }

    $agent_email = $_SESSION['user']['username'];
    
    // Get agent from agents table
    $agent = $db->getThisQuery("
        SELECT id, user_id, name, email, is_active 
        FROM agents 
        WHERE email = ? AND is_active = 1
    ", [$agent_email]);

    if (empty($agent)) {
        return null;
    }

    return [
        'agent_id' => $agent[0]['id'],
        'user_id' => $agent[0]['user_id'],
        'name' => $agent[0]['name'],
        'email' => $agent[0]['email']
    ];
}

/**
 * Record shipping submission in new table
 */
function recordShippingSubmission($db, $order_id, $agent_id, $user_id, $submission_type, $shipping_result) {
    $data = [
        'order_id' => $order_id,
        'agent_id' => $agent_id,
        'user_id' => $user_id,
        'submission_type' => $submission_type, // 'single' or 'bulk'
        'shipping_company' => $shipping_result['company_name'] ?? '',
        'tracking_number' => $shipping_result['tracking_number'] ?? '',
        'status' => $shipping_result['notification_sent'] ? 'success' : 'failed',
        'response_message' => $shipping_result['message'] ?? '',
        'submitted_at' => date('Y-m-d H:i:s')
    ];

    try {
        $db->insert('shipping_submissions', $data);
        return true;
    } catch (Exception $e) {
        error_log("Failed to record shipping submission: " . $e->getMessage());
        return false;
    }
}

/**
 * Handle shipping company notification when order is confirmed
 * (Same as original function - no changes needed)
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
        
        // If we get parcel info back, it means the package exists
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
        if ($company_name === 'ozonexpress') {
            // First check if order already has a tracking number
            if (!empty($order['tracking_number'])) {
                // Check if this tracking number already exists in OzoneExpress
                $duplicate_check = checkOzoneExpressPackageExists(
                    $shipping_company['api_url'],
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
                'parcel-receiver' => $order['customer_name'] ?? '',
                'parcel-phone' => $order['customer_phone'] ?? '',
                'parcel-city' => getOzonCityId(getCityNameFromBillingAddress($order ?? '')),
                'parcel-address' => $order['shipping_address'] ?? '',
                'parcel-note' => $order['shipping_method'] ?? '',
                'parcel-price' => $order['total_amount'] ?? 0,
                'parcel-nature' => 'Commande client',
                'parcel-stock' => 0,
                'products' => json_encode($products)
            ];
            
            // Fix: Append /add-parcel to the API URL
            $url = rtrim($shipping_company['api_url'], '/') . '/add-parcel';

            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            
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
                $result['message'] = 'Erreur OzonExpress: ' . ($response_data['message'] ?? 'Réponse invalide');
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
 * Standalone function to check duplicates for any shipping company
 */
function checkShippingDuplicate($shippingCompany, $credentials, $trackingNumber) {
    switch (strtolower($shippingCompany)) {
        case 'ozonexpress':
            return checkOzoneExpressPackageExists(
                $credentials['api_url'],
                $trackingNumber
            );
            
        // Add other shipping companies here as needed
        case 'amana':
        case 'dhl':
        case 'fedex':
            // TODO: Implement other shipping companies
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
        // Check if NAME key exists before using it
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
} ?>