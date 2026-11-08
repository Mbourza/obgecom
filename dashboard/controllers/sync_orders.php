<?php //whatsapp_webhook.php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

require_once('./config/e_config.php');

// api/sync_orders.php - Synchronize orders from connected stores
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

// Check if action is sync_orders
$action = $_POST['action'] ?? '';
if ($action !== 'sync_orders') {

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {

    $user_id = getCurrentUserId($db);

    // Vérification de l'abonnement
    $subscription = new Subscription($user_id);
    $subscriptionStatus = $subscription->getSubscriptionStatus();
    $subscriptionAlerts = $subscription->getSubscriptionAlerts();

    // Vérifier l'accès aux fonctionnalités
    $canAccessFeatures = $subscription->canAccessFeatures();

    // Si l'abonnement est expiré ou inexistant, limiter l'accès
    $limitedAccess = !$canAccessFeatures;

    if (!$canAccessFeatures) {

        throw new Exception('Votre abonnement a expiré ou n\'est pas valide. Veuillez renouveler votre abonnement pour continuer à synchroniser vos commandes.');
    }
    
    if (!$user_id) {
        throw new Exception('Utilisateur non authentifié');
    }

    // Get subscription plan details and order limits
    $planDetails = getSubscriptionPlanDetails($db, $user_id);

    // make sure planDetails contains limits
    $maxOrders = isset($planDetails['limits']['monthly_orders']) 
        ? (int)$planDetails['limits']['monthly_orders'] 
        : PHP_INT_MAX; // if unlimited

    $monthlyUsage   = getMonthlyOrderUsage($db, $user_id);
    $ordersUsed     = (int)$monthlyUsage['orders_synced'];
    $remainingOrders = $maxOrders - $ordersUsed;

    if ($remainingOrders <= 0) {
        $nextResetDate = date('Y-m-d', strtotime('first day of next month'));
        throw new Exception(
            "Vous avez atteint votre limite mensuelle de {$maxOrders} commandes. 
            Votre quota sera réinitialisé le {$nextResetDate}. 
            Mettez à niveau votre plan pour obtenir plus de commandes."
        );
    }
        
    // Get all connected stores for the user
    $stores = $db->getThisQuery("SELECT * FROM stores WHERE user_id = ?", [$user_id]);
    
    if (empty($stores)) {
        throw new Exception('Aucun magasin connecté trouvé');
    }
    
    $total_synced = 0;
    $sync_results = [];
    
    foreach ($stores as $store) {

        try {

            $orders_synced = 0;
            
            if ($store['platform'] === 'youcan') {
                $orders_synced = syncYouCanOrders($db, $store);
            } elseif ($store['platform'] === 'woocommerce') {
                $orders_synced = syncWooCommerceOrders($db, $store);
            } elseif($store['platform'] === 'shopify') {
                $orders_synced = syncShopifyOrders($db, $store);
            }
            
            $total_synced += $orders_synced['synced'];
            // Update usage tracking
            if ($orders_synced['synced'] > 0) {
                
                updateMonthlyOrderUsage($db, $user_id, $orders_synced['synced']);
            }

            $sync_results[] = [
                'store' => $store['storeName'],
                'platform' => $store['platform'],
                'orders_synced' => $orders_synced
            ];
            
        } catch (Exception $e) {
            error_log("Error syncing store {$store['storeName']}: " . $e->getMessage());
            $sync_results[] = [
                'store' => $store['storeName'],
                'platform' => $store['platform'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Synchronisation terminée. {$total_synced} commandes synchronisées.",
        'details' => $sync_results,
        'total_synced' => $total_synced
    ]);
    
} catch (Exception $e) {
    error_log("Error in sync_orders.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * @param  object $db     Database instance (expects beginTransaction/commit/rollback/insert/getThisQuery/getLastInsertId)
 * @param  array  $store  Store row (must contain: id, user_id, api_key)
 * @return array          ['synced' => int, 'updated' => int, 'new_order_ids' => array, 'distribution' => array]
*/

function syncYouCanOrders($db, $store) {
    $api_base     = 'https://api.youcan.shop';
    $access_token = $store['api_key'];

    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    // ─────────────────────────────────────────────────────────────
    // Pagination config
    // ─────────────────────────────────────────────────────────────
    $per_page  = 50;   // how many orders to request per page
    $max_pages = 5;    // HARD CAP: 2 pages × 50 = max 100 orders per sync
                       // Set to 1 for max 50, 2 for max 100, etc.

    $orders_synced  = 0;
    $orders_updated = 0;
    $new_order_ids  = [];
    $page           = 1;
    $has_more       = true;
    $seen_order_ids = []; // dedupe within this run

    while ($has_more && $page <= $max_pages) {

        $url = $api_base . '/orders'
             . '?page='        . $page
             . '&limit='       . $per_page
             . '&include='     . urlencode('customer,variants,payment,shipping')
             . '&sort_field=created_at&sort_order=desc';

        // ─────────────────────────────────────────────────────────
        // HTTP request
        // ─────────────────────────────────────────────────────────
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response   = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new Exception("CURL Error: " . $curl_error);
        }

        if ($http_code !== 200) {
            throw new Exception("Erreur API YouCan: HTTP {$http_code} - Response: " . substr($response, 0, 500));
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['data']) || !is_array($data['data'])) {
            error_log("YouCan sync: invalid response — " . substr($response, 0, 500));
            throw new Exception('Réponse API YouCan invalide - data missing');
        }

        $orders = $data['data'];

        // ─────────────────────────────────────────────────────────
        // Empty page → done
        // ─────────────────────────────────────────────────────────
        if (empty($orders)) {
            error_log("YouCan sync: page {$page} returned 0 orders — stopping");
            $has_more = false;
            break;
        }

        // ─────────────────────────────────────────────────────────
        // Pagination: trust YouCan's own meta when available
        // ─────────────────────────────────────────────────────────
        $pagination = $data['meta']['pagination'] ?? null;

        if (is_array($pagination)) {
            $current_page = (int)($pagination['current_page'] ?? $page);
            $last_page    = (int)($pagination['last_page']    ?? $page);
            error_log("YouCan sync: pagination meta — page {$current_page}/{$last_page}");

            if ($current_page >= $last_page) {
                $has_more = false;
            }
        } else {
            // Fallback: if we got fewer than requested, we're on the last page
            if (count($orders) < $per_page) {
                $has_more = false;
            }
        }

        // ─────────────────────────────────────────────────────────
        // Duplicate page guard (in case YouCan ignores 'page')
        // ─────────────────────────────────────────────────────────
        $first_id = $orders[0]['id'] ?? null;
        if ($first_id !== null && isset($seen_order_ids[$first_id])) {
            error_log("YouCan sync: page {$page} repeats first order {$first_id} — stopping");
            $has_more = false;
            break;
        }

        // ─────────────────────────────────────────────────────────
        // Process each order on this page
        // ─────────────────────────────────────────────────────────
        foreach ($orders as $order) {

            $order_id_key = $order['id'] ?? null;
            if ($order_id_key === null) {
                error_log("YouCan sync: skipping order without id");
                continue;
            }

            // Dedupe within this run
            if (isset($seen_order_ids[$order_id_key])) {
                error_log("YouCan sync: skipping duplicate order {$order_id_key} (seen this run)");
                continue;
            }
            $seen_order_ids[$order_id_key] = true;

            try {
                // ─────────────────────────────────────────────────
                // Already in DB? Only refresh shipping status
                // ─────────────────────────────────────────────────
                $existing_order = $db->getThisQuery(
                    "SELECT id, shipping_status, tracking_number, shipping_company_id
                     FROM orders
                     WHERE store_id = ? AND external_order_id = ?
                     LIMIT 1",
                    [$store['id'], $order['id']]
                );

                if (!empty($existing_order)) {
                    error_log("YouCan sync: order {$order['id']} already exists — checking shipping update");
                    $updated = updateOrderShippingStatus($db, $existing_order[0]);
                    if ($updated) {
                        $orders_updated++;
                    }
                    continue;
                }

                // ─────────────────────────────────────────────────
                // New order — open transaction
                // ─────────────────────────────────────────────────
                $transaction_open = false;
                if (method_exists($db, 'beginTransaction')) {
                    $db->beginTransaction();
                    $transaction_open = true;
                }

                try {
                    // ═════════════════════════════════════════════
                    // CUSTOMER INFO — single source of truth
                    // ═════════════════════════════════════════════
                    $customer_info = getYouCanCustomerInfo($order);

                    $customer_name  = $customer_info['name'];
                    $customer_email = $customer_info['email'];
                    $customer_phone = $customer_info['phone'];
                    $customer_ville = $customer_info['city'];

                    $formatted_shipping_address = $customer_info['shipping_address_formatted'];
                    $formatted_billing_address  = $customer_info['billing_address_formatted'];

                    // ═════════════════════════════════════════════
                    // TRACKING NUMBER — try every known location
                    // ═════════════════════════════════════════════
                    $tracking_number = null;

                    if (!empty($order['shipping']['tracking_number'])) {
                        $tracking_number = $order['shipping']['tracking_number'];
                    } elseif (!empty($order['tracking_number'])) {
                        $tracking_number = $order['tracking_number'];
                    } elseif (!empty($order['fulfillments']) && is_array($order['fulfillments'])) {
                        foreach ($order['fulfillments'] as $f) {
                            if (!empty($f['tracking_number'])) {
                                $tracking_number = $f['tracking_number'];
                                break;
                            }
                        }
                    }

                    // ═════════════════════════════════════════════
                    // SHIPPING / ORDER / PAYMENT STATUS
                    // ═════════════════════════════════════════════
                    $shipping_status_code = $order['shipping']['status']
                        ?? $order['shipping_status']
                        ?? 'unfulfilled';

                    $order_status    = mapYouCanOrderStatus($order);
                    $shipping_status = mapYouCanShippingStatus($shipping_status_code);
                    $payment_status  = mapYouCanPaymentStatus(
                        $order['payment']['status']
                        ?? $order['payment_status']
                        ?? 'pending'
                    );

                    // ═════════════════════════════════════════════
                    // Build order row
                    // ═════════════════════════════════════════════
                    $order_data = [
                        'store_id'           => $store['id'],
                        'user_id'            => $store['user_id'],
                        'external_order_id'  => $order['id'],
                        'order_number'       => $order['ref'] ?? $order['id'],
                        'customer_name'      => $customer_name,
                        'customer_email'     => $customer_email,
                        'customer_phone'     => $customer_phone,
                        'customer_ville'     => $customer_ville,
                        'shipping_address'   => $formatted_shipping_address,
                        'billing_address'    => $formatted_billing_address,
                        'total_amount'       => floatval($order['total'] ?? 0),
                        'currency'           => $order['currency'] ?? 'MAD',
                        'status'             => $order_status,
                        'shipping_status'    => $shipping_status,
                        'payment_status'     => $payment_status,
                        'shipping_method'    => $order['shipping']['method'] ?? null,
                        'tracking_number'    => $tracking_number,
                        'order_date'         => convertYouCanDateTime($order['created_at'] ?? date('Y-m-d H:i:s')),
                        'platform'           => 'youcan',
                        'created_at'         => date('Y-m-d H:i:s'),
                        'updated_at'         => date('Y-m-d H:i:s'),
                        'confirmed_by_agent' => null,
                        'handled_at'         => null,
                    ];


                    // ═════════════════════════════════════════════
                    // Insert order (handle unique-constraint duplicates)
                    // ═════════════════════════════════════════════
                    try {
                        $result = $db->insert('orders', $order_data);
                    } catch (Exception $e) {
                        $is_duplicate = (strpos($e->getMessage(), '1062') !== false)
                            || (strpos($e->getMessage(), '23000') !== false)
                            || (stripos($e->getMessage(), 'duplicate') !== false);

                        if ($is_duplicate) {
                            error_log("YouCan sync: order {$order['id']} already exists (unique constraint) — skipping");
                            if ($transaction_open && method_exists($db, 'rollback')) {
                                $db->rollback();
                            }
                            continue;
                        }
                        throw $e;
                    }

                    if (!$result) {
                        if ($transaction_open && method_exists($db, 'rollback')) {
                            $db->rollback();
                        }
                        error_log("YouCan sync: failed to insert order {$order['id']}");
                        continue;
                    }

                    // ═════════════════════════════════════════════
                    // Order items
                    // ═════════════════════════════════════════════
                    $order_pk = $db->getLastInsertId();
                    $new_order_ids[] = $order_pk;

                    if (!empty($order['variants']) && is_array($order['variants'])) {
                        foreach ($order['variants'] as $variant_item) {

                            $product_name = $variant_item['product_name']
                                ?? $variant_item['product']['name']
                                ?? $variant_item['name']
                                ?? 'Unknown Product';

                            $product_sku = $variant_item['sku']
                                ?? $variant_item['product']['sku']
                                ?? '';

                            $unit_price = floatval($variant_item['price'] ?? $variant_item['unit_price'] ?? 0);
                            $quantity   = intval($variant_item['quantity'] ?? 1);

                            $item_data = [
                                'order_id'     => $order_pk,
                                'product_name' => $product_name,
                                'product_sku'  => $product_sku,
                                'quantity'     => $quantity,
                                'unit_price'   => $unit_price,
                                'total_price'  => $unit_price * $quantity,
                                'weight'       => $variant_item['weight'] ?? $variant_item['product']['weight'] ?? null,
                                'created_at'   => date('Y-m-d H:i:s'),
                            ];

                            $item_result = $db->insert('order_items', $item_data);

                            if (!$item_result) {
                                throw new Exception("Erreur lors de l'insertion de l'article: " . $product_name);
                            }
                        }
                    }

                    // ═════════════════════════════════════════════
                    // Commit
                    // ═════════════════════════════════════════════
                    if ($transaction_open && method_exists($db, 'commit')) {
                        $db->commit();
                    }

                    $orders_synced++;

                    // ── AI Confirmation hook (non-blocking) ─────
                    try {
                        require_once __DIR__ . '/../ai_confirmation/core/ConfirmationService.php';
                        \OBGecom\AiConfirmation\Core\ConfirmationService::enqueueForConfirmation(
                            (int)$order_pk,
                            (int)$store['id'],
                            (int)$store['user_id']
                        );
                    } catch (\Throwable $__ai_ex) {
                        error_log('[ai_confirmation] enqueue failed for YouCan order '
                                . $order_pk . ': ' . $__ai_ex->getMessage());
                    }
                    // ────────────────────────────────────────────


                } catch (Exception $e) {
                    if ($transaction_open && method_exists($db, 'rollback')) {
                        $db->rollback();
                    }
                    error_log("YouCan sync: transaction failed for order {$order['id']}: " . $e->getMessage());
                    throw $e;
                }

            } catch (Exception $e) {
                error_log("YouCan sync: error processing order {$order['id']}: " . $e->getMessage());
                // Keep going — one bad order shouldn't stop the sync
            }
        }

        $page++;
        usleep(300000); // 300ms politeness delay between pages
    }

    if ($page > $max_pages && $has_more) {
        error_log("YouCan sync: reached max_pages cap ({$max_pages}) — stopping early (more orders available)");
    }

    error_log("YouCan sync: done — {$orders_synced} synced, {$orders_updated} updated, " . count($new_order_ids) . " new ids");

    // ─────────────────────────────────────────────────────────────
    // Distribution (unchanged behaviour, wrapped in try/catch)
    // ─────────────────────────────────────────────────────────────
    $distribution_result = ['success' => false, 'distributed' => 0];

    if ($orders_synced > 0) {
        try {
            $distribution_method = getDistributionMethod($db, $store['user_id']);

            if ($distribution_method === 'performance') {

                $distribute_file = __DIR__ . '/distribute_orders_function.php';
                if (file_exists($distribute_file)) {
                    require_once($distribute_file);

                    $distributor = new OrderDistributor($db, $store['user_id'], $store['id']);
                    $distribution_result = $distributor->distributeOrders();

                    if (!empty($distribution_result['success'])) {
                        error_log("YouCan sync: distribution OK — " . ($distribution_result['message'] ?? ''));
                    } else {
                        error_log("YouCan sync: distribution failed — " . ($distribution_result['message'] ?? 'unknown'));
                    }
                } else {
                    error_log("YouCan sync: distribute_orders_function.php not found");
                }
            } else {
                error_log("YouCan sync: distribution method is 'manual' — skipping auto-distribution");
                $distribution_result = [
                    'success'     => true,
                    'message'     => 'YouCan orders synchronized. Manual distribution required.',
                    'distributed' => 0,
                    'method'      => 'manual',
                ];
            }

        } catch (Exception $e) {
            error_log("YouCan sync: distribution error — " . $e->getMessage());
            $distribution_result = [
                'success'     => false,
                'message'     => 'Distribution failed: ' . $e->getMessage(),
                'distributed' => 0,
            ];
        }
    }

    return [
        'synced'        => $orders_synced,
        'updated'       => $orders_updated,
        'new_order_ids' => $new_order_ids,
        'distribution'  => $distribution_result,
    ];
}

function getYouCanCustomerInfo(array $order): array {
    $customer         = $order['customer']         ?? null;
    $shipping_address = $order['shipping_address'] ?? ($order['shipping']['address'] ?? null);
    $billing_address  = $order['billing_address']  ?? ($order['billing']['address']  ?? null);

    // ─────────────────────────────────────────────────────────────
    // If no dedicated shipping_address exists, treat the customer
    // object itself as the address source (YouCan commonly stores
    // address fields directly on customer).
    // ─────────────────────────────────────────────────────────────
    $address_source = null;
    if (is_array($shipping_address)) {
        $address_source = $shipping_address;
    } elseif (is_array($billing_address)) {
        $address_source = $billing_address;
    } elseif (is_array($customer)) {
        $address_source = $customer;
    }

    // ---- Name ----
    $name = '';
    if (is_array($customer)) {
        if (!empty($customer['full_name'])) {
            $name = trim($customer['full_name']);
        } elseif (!empty($customer['first_name']) || !empty($customer['last_name'])) {
            $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        } elseif (!empty($customer['name'])) {
            $name = trim($customer['name']);
        }
    }
    if ($name === '' && is_array($address_source)) {
        if (!empty($address_source['full_name'])) {
            $name = trim($address_source['full_name']);
        } elseif (!empty($address_source['first_name']) || !empty($address_source['last_name'])) {
            $name = trim(($address_source['first_name'] ?? '') . ' ' . ($address_source['last_name'] ?? ''));
        }
    }
    if ($name === '') {
        $name = "Client " . ($order['ref'] ?? $order['id'] ?? 'unknown');
    }

    // ---- Email ----
    $email = '';
    if (is_array($customer) && !empty($customer['email'])) {
        $email = trim($customer['email']);
    } elseif (!empty($order['email'])) {
        $email = trim($order['email']);
    }

    // ---- Phone ----
    $phone = '';
    if (is_array($customer) && !empty($customer['phone'])) {
        $phone = $customer['phone'];
    } elseif (is_array($address_source) && !empty($address_source['phone'])) {
        $phone = $address_source['phone'];
    }

    // ---- City ----
    $city = 'Non renseigné';
    if (is_array($address_source) && !empty($address_source['city'])) {
        $city = $address_source['city'];
    } elseif (is_array($customer) && !empty($customer['city'])) {
        $city = $customer['city'];
    }

    // ---- Formatted addresses ----
    // Use whatever source we resolved; always an array now.
    $shipping_formatted = is_array($address_source)
        ? formatYouCanAddress($address_source)
        : "Commande YouCan #" . ($order['ref'] ?? $order['id'] ?? 'unknown');

    $billing_formatted = is_array($billing_address)
        ? formatYouCanAddress($billing_address)
        : $shipping_formatted;

    return [
        'name'                       => $name,
        'email'                      => $email,
        'phone'                      => $phone,
        'city'                       => $city,
        'shipping_address_formatted' => $shipping_formatted,
        'billing_address_formatted'  => $billing_formatted,
    ];
}

/**
 * Convert YouCan datetime format to MySQL datetime format
 * @param string $youcan_datetime Format: 2026-04-08T11:53:17+00:00
 * @return string Format: 2026-04-08 11:53:17
*/
function convertYouCanDateTime($youcan_datetime) {
    if (empty($youcan_datetime)) {
        return date('Y-m-d H:i:s');
    }
    
    // Remove the timezone part (+00:00 or Z) and replace T with space
    $datetime = preg_replace('/\+[0-9]{2}:[0-9]{2}$/', '', $youcan_datetime);
    $datetime = str_replace('T', ' ', $datetime);
    $datetime = str_replace('Z', '', $datetime);
    
    // Validate the datetime
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        error_log("Invalid datetime format: " . $youcan_datetime);
        return date('Y-m-d H:i:s');
    }
    
    return date('Y-m-d H:i:s', $timestamp);
}

function mapYouCanOrderStatus($order) {
    // Check shipping status first - if fulfilled, it's delivered/shipped
    $shipping_status = null;
    if (isset($order['shipping']['status'])) {
        $shipping_status = $order['shipping']['status'];
    } elseif (isset($order['shipping_status'])) {
        $shipping_status = $order['shipping_status'] === 'fulfilled' ? 1 : 2;
    }
    
    // If shipping status is fulfilled (1) or delivered, order is confirmed
    if ($shipping_status == 1) {
        return 'confirmed';
    }
    
    // Check order status (1 = open, 2 = closed)
    if (isset($order['status'])) {
        if ($order['status'] == 2) {
            // Closed order - could be cancelled or completed
            // Check if it was fulfilled
            if ($shipping_status == 1) {
                return 'confirmed';
            }
            return 'cancelled';
        }
        if ($order['status'] == 1) {
            return 'new-order';
        }
    }
    
    // Check confirmation_status if available
    if (isset($order['confirmation_status'])) {
        if ($order['confirmation_status'] === 'closed') {
            return 'cancelled';
        }
        if ($order['confirmation_status'] === 'open') {
            return 'new-order';
        }
    }
    
    // Default: treat as new order
    return 'new-order';
}

function mapYouCanShippingStatus($status_code) {
    $status_map = [
        0 => 'not_submitted',
        1 => 'delivered',
        2 => 'pending',      // <-- changed from 'delivered'
        3 => 'failed_delivery',
        4 => 'returned'
    ];
    return $status_map[$status_code] ?? 'not_submitted';
}

function mapYouCanPaymentStatus($status_code) {
    $status_map = [
        1 => 'paid',           // Paid
        2 => 'pending',        // Unpaid/Pending (this is the common case)
        3 => 'refunded',       // Refunded
        4 => 'failed',         // Failed
        5 => 'pending',        // Pending
        6 => 'authorized'      // Authorized
    ];
    
    return $status_map[$status_code] ?? 'pending';
}

function formatYouCanAddress($address_data) {
    if (!$address_data || !is_array($address_data)) {
        return '';
    }

    $parts = [];

    // ─────────────────────────────────────────────────────────
    // 1. Name line
    // ─────────────────────────────────────────────────────────
    if (!empty($address_data['full_name'])) {
        $parts[] = trim($address_data['full_name']);
    } elseif (!empty($address_data['first_name']) || !empty($address_data['last_name'])) {
        $name = trim(($address_data['first_name'] ?? '') . ' ' . ($address_data['last_name'] ?? ''));
        if ($name !== '') $parts[] = $name;
    }

    // ─────────────────────────────────────────────────────────
    // 2. Street address — check every known key
    // ─────────────────────────────────────────────────────────
    $street_line1 = '';
    foreach (['first_line', 'address', 'address_1', 'street'] as $key) {
        if (!empty($address_data[$key]) && is_string($address_data[$key])) {
            $street_line1 = trim($address_data[$key]);
            break;
        }
    }

    $street_line2 = '';
    foreach (['second_line', 'address_2', 'address_2_line'] as $key) {
        if (!empty($address_data[$key]) && is_string($address_data[$key])) {
            $street_line2 = trim($address_data[$key]);
            break;
        }
    }

    // ─────────────────────────────────────────────────────────
    // 3. Region — used as address fallback if no street exists
    // ─────────────────────────────────────────────────────────
    $region = '';
    foreach (['region', 'state', 'province', 'quartier'] as $key) {
        if (!empty($address_data[$key]) && is_string($address_data[$key])) {
            $region = trim($address_data[$key]);
            break;
        }
    }

    // ─────────────────────────────────────────────────────────
    // 4. Add the address block — street if we have it, else region
    // ─────────────────────────────────────────────────────────
    if ($street_line1 !== '') {
        $parts[] = $street_line1;
        if ($street_line2 !== '') {
            $parts[] = $street_line2;
        }
    } elseif ($region !== '') {
        // No street → use region (quartier) as the address
        $parts[] = $region;
    } else {
        // Last resort — YouCan's pre-built location string
        $location = trim((string)($address_data['location'] ?? ''), " ,\t\n\r\0\x0B");
        if ($location !== '') {
            $parts[] = $location;
        }
    }

    // ─────────────────────────────────────────────────────────
    // 5. City + zip
    // ─────────────────────────────────────────────────────────
    $city = '';
    foreach (['city', 'town'] as $key) {
        if (!empty($address_data[$key]) && is_string($address_data[$key])) {
            $city = trim($address_data[$key]);
            break;
        }
    }

    $zip = '';
    foreach (['zip_code', 'postal_code', 'zip'] as $key) {
        if (!empty($address_data[$key]) && is_string($address_data[$key])) {
            $zip = trim($address_data[$key]);
            break;
        }
    }

    if ($city !== '') {
        $city_line = $city;
        if ($zip !== '') $city_line .= ' ' . $zip;
        $parts[] = $city_line;
    } elseif ($zip !== '') {
        $parts[] = $zip;
    }

    // ─────────────────────────────────────────────────────────
    // 6. Country
    // ─────────────────────────────────────────────────────────
    $country = $address_data['country_name']
            ?? $address_data['country']
            ?? null;

    if (is_array($country) && !empty($country['name'])) {
        $country = $country['name'];
    }
    if (!empty($country) && is_string($country)) {
        $parts[] = trim($country);
    }

    // ─────────────────────────────────────────────────────────
    // 7. Clean up: trim, drop empties, de-dupe consecutive
    // ─────────────────────────────────────────────────────────
    $parts = array_values(array_filter(
        array_map('trim', $parts),
        fn($v) => $v !== ''
    ));

    $unique = [];
    foreach ($parts as $p) {
        if (empty($unique) || end($unique) !== $p) {
            $unique[] = $p;
        }
    }

    return implode(', ', $unique);
}

/**
 * Synchronize orders from WooCommerce store
*/

function getCityNameFromBillingAddress($billing_address_json) {
    // Step 1: Decode billing address
    $billing = json_decode($billing_address_json, true);
    if (!$billing || !is_array($billing)) return null;

    $city_input = trim($billing['city'] ?? '');
    $postcode_input = trim($billing['postcode'] ?? '');

    // Step 2: Determine what to search with
    if (empty($city_input) && !empty($postcode_input)) {
        if (!ctype_digit($postcode_input)) {
            return ucfirst(strtolower($postcode_input)); // Use postcode as city name if it's not digits
        }
    } elseif (!empty($city_input)) {
        return ucfirst(strtolower($city_input)); // Return formatted city name
    }

    return null; // Nothing found
}

function formatAddress($address_data) {
    if (!$address_data || !is_array($address_data)) {
        return '';
    }
    
    $address_parts = [];
    
    // Add name if available
    $name = trim(($address_data['first_name'] ?? '') . ' ' . ($address_data['last_name'] ?? ''));
    if (!empty($name)) {
        $address_parts[] = $name;
    }
    
    // Add company if available
    if (!empty($address_data['company'])) {
        $address_parts[] = trim($address_data['company']);
    }
    
    // Add address lines
    if (!empty($address_data['address_1'])) {
        $address_parts[] = trim($address_data['address_1']);
    }
    
    if (!empty($address_data['address_2'])) {
        $address_parts[] = trim($address_data['address_2']);
    }
    
    // Handle city/postcode logic
    $city = getCityFromAddress($address_data);
    $postcode = trim($address_data['postcode'] ?? '');
    
    // Create city/postcode line
    $city_line = '';
    if (!empty($city) && !empty($postcode)) {
        // If postcode is numeric, show "City PostCode"
        if (ctype_digit($postcode)) {
            $city_line = $city . ' ' . $postcode;
        } else {
            // If postcode is not numeric, it might be the city name, so just show city
            $city_line = $city;
        }
    } elseif (!empty($city)) {
        $city_line = $city;
    } elseif (!empty($postcode)) {
        $city_line = $postcode;
    }
    
    if (!empty($city_line)) {
        $address_parts[] = $city_line;
    }
    
    // Add state if available
    if (!empty($address_data['state'])) {
        $address_parts[] = trim($address_data['state']);
    }
    
    // Add country if available
    if (!empty($address_data['country'])) {
        $address_parts[] = trim($address_data['country']);
    }
    
    return implode(', ', array_filter($address_parts));
}

function getCityFromAddress($address_data) {
    if (!$address_data || !is_array($address_data)) {
        return null;
    }
    
    $city_input = trim($address_data['city'] ?? '');
    $postcode_input = trim($address_data['postcode'] ?? '');
    
    // Use the same logic as getCityNameFromBillingAddress
    if (empty($city_input) && !empty($postcode_input)) {
        if (!ctype_digit($postcode_input)) {
            return ucfirst(strtolower($postcode_input)); // Use postcode as city name if it's not digits
        }
    } elseif (!empty($city_input)) {
        return ucfirst(strtolower($city_input)); // Return formatted city name
    }
    
    return null;
}

function formatShippingAddress($shipping_data) {
    return formatAddress($shipping_data);
}

function formatBillingAddress($billing_data) {
    return formatAddress($billing_data);
}

// Updated syncWooCommerceOrders function with address formatting
function syncWooCommerceOrders($db, $store) {

    $api_url = rtrim($store['api_url'], '/');
    $consumer_key = $store['Consumer_key'];
    $consumer_secret = $store['Consumer_secret'];
    
    // WooCommerce REST API endpoint for orders
    $orders_endpoint = $api_url . "/orders/"; 
    
    // Get orders from the last 30 days
    $params = [
        'per_page' => 100,
        'after' => date('Y-m-d', strtotime('-30 days')) . 'T00:00:00',
        'consumer_key' => $consumer_key,
        'consumer_secret' => $consumer_secret
    ];
    
    $url = $orders_endpoint . '?' . http_build_query($params);
    
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
    
    $orders = json_decode($response, true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erreur JSON: ' . json_last_error_msg());
    }
    
    // Handle different response structures
    if (!$orders) {
        throw new Exception('Réponse API WooCommerce vide');
    }
    
    // If the response is not an array, make it one
    if (!is_array($orders)) {
        throw new Exception('Réponse API WooCommerce invalide - pas un tableau');
    }
    
    // Check if orders is wrapped in another structure
    if (isset($orders['orders'])) {
        $orders = $orders['orders'];
    }
    
    error_log("Number of orders found: " . count($orders));
    
    $orders_synced = 0;
    $orders_updated = 0;
    
    foreach ($orders as $order) {
        try {
            // Validate order structure
            if (!isset($order['id'])) {
                error_log("Order missing ID, skipping");
                continue;
            }
            
            error_log("Processing order ID: " . $order['id']);
            
            // Check if order already exists
            $existing_order = $db->getThisQuery(
                "SELECT id, shipping_status, tracking_number, shipping_company_id FROM orders WHERE store_id = ? AND external_order_id = ?",
                [$store['id'], $order['id']]
            );
            
            if (!empty($existing_order)) {
                error_log("Order {$order['id']} already exists, updating shipping status");
                
                // Update existing order's shipping status
                $updated = updateOrderShippingStatus($db, $existing_order[0]);
                if ($updated) {
                    $orders_updated++;
                }
                continue;
            }
            
            // Begin transaction for order and items
            $db->beginTransaction();
            
            try {
                // Extract customer info safely
                $customer_name = '';
                if (isset($order['billing']['first_name']) || isset($order['billing']['last_name'])) {
                    $customer_name = trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? ''));
                }
                
                // Extract shipping info from order meta or shipping lines
                $shipping_info = extractShippingInfo($order, $db);

                // Format addresses as readable strings instead of JSON
                $formatted_shipping_address = formatShippingAddress($order['shipping'] ?? []);
                $formatted_billing_address = formatBillingAddress($order['billing'] ?? []);
                
                $order_data = [
                    'store_id'             => $store['id'],
                    'user_id'              => $store['user_id'],
                    'external_order_id'    => $order['id'],
                    'order_number'         => $order['number'] ?? $order['id'],
                    'customer_name'        => $customer_name,
                    'customer_email'       => $order['billing']['email'] ?? '',
                    'customer_phone'       => $order['billing']['phone'] ?? '',
                    'customer_ville'       => getCityFromAddress($order['shipping']),
                    'shipping_address'     => $formatted_shipping_address, // Now formatted as string
                    'billing_address'      => $formatted_billing_address,  // Now formatted as string
                    'total_amount'         => floatval($order['total'] ?? 0),
                    'currency'             => $order['currency'] ?? 'MAD',
                    'status'               => mapWooCommerceStatus($order['status'] ?? ''),
                    'shipping_status'      => 'not_submitted',
                    'payment_status'       => ($order['status'] === 'completed') ? 'paid' : 'pending',
                    'shipping_method'      => $order['shipping_lines'][0]['method_title'] ?? null,
                    'tracking_number'      => $shipping_info['tracking_number'] ?? null,
                    'weight'               => $order['weight'] ?? null,
                    'length'               => $order['length'] ?? null,
                    'width'                => $order['width'] ?? null,
                    'height'               => $order['height'] ?? null,
                    'order_date'           => $order['date_created'] ?? date('Y-m-d H:i:s'),
                    'platform'             => 'woocommerce',
                    'created_at'           => date('Y-m-d H:i:s'),
                    'updated_at'           => date('Y-m-d H:i:s'),
                    'confirmed_by_agent'   => null,
                    'handled_at'         => null
                ];
                
                error_log("Inserting order data: " . json_encode($order_data));
                
                $result = $db->insert('orders', $order_data);
                
                if ($result) {
                    $order_id = $db->getLastInsertId();
                    error_log("Order inserted with ID: " . $order_id);
                    
                    // Insert order items
                    if (isset($order['line_items']) && is_array($order['line_items'])) {
                        foreach ($order['line_items'] as $item) {
                            $item_data = [
                                'order_id' => $order_id,
                                'product_name' => $item['name'] ?? '',
                                'product_sku' => $item['sku'] ?? '',
                                'quantity' => intval($item['quantity'] ?? 1),
                                'unit_price' => floatval($item['price'] ?? 0),
                                'total_price' => floatval($item['total'] ?? 0),
                                'weight' => isset($item['meta_data']) ? extractWeightFromMeta($item['meta_data']) : null,
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                            
                            $item_result = $db->insert('order_items', $item_data);
                            
                            if (!$item_result) {
                                throw new Exception("Erreur lors de l'insertion de l'article: " . ($item['name'] ?? 'Unknown'));
                            }
                        }
                    }
                    
                    $db->commit();
                    $orders_synced++;
                    error_log("Order {$order['id']} synchronized successfully");
                    
                    // Update shipping status for new order if tracking info exists
                    if ($shipping_info['tracking_number'] && $shipping_info['shipping_company']) {
                        $new_order_data = [
                            'id' => $order_id,
                            'tracking_number' => $shipping_info['tracking_number'],
                            'shipping_company_id' => $shipping_info['shipping_company'],
                            'shipping_status' => 'new'
                        ];
                        updateOrderShippingStatus($db, $new_order_data);
                    }
                } else {
                    $db->rollback();
                    error_log("Failed to insert order {$order['id']}");
                }
            } catch (Exception $e) {
                $db->rollback();
                error_log("Transaction failed for order {$order['id']}: " . $e->getMessage());
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Error processing WooCommerce order {$order['id']}: " . $e->getMessage());
        }
    }
    
    error_log("Total orders synchronized: " . $orders_synced);
    error_log("Total orders updated: " . $orders_updated);
    $distribution_result = ['success' => false, 'distributed' => 0];
    
    if ($orders_synced > 0) {
        try {
            $distribution_method = getDistributionMethod($db, $store['user_id']);
            
            if ($distribution_method === 'performance') {
                require_once ('./distribute_orders_function.php');
                
                $distributor = new OrderDistributor($db, $store['user_id'], $store['id']);
                $distribution_result = $distributor->distributeOrders();
                
                if ($distribution_result['success']) {
                    error_log("Orders distributed successfully: " . $distribution_result['message']);
                } else {
                    error_log("Order distribution failed: " . $distribution_result['message']);
                }
            } else {
                error_log("Distribution method is 'manual' - skipping automatic distribution for WooCommerce");
                $distribution_result = [
                    'success' => true,
                    'message' => 'WooCommerce orders synchronized. Manual distribution required.',
                    'distributed' => 0,
                    'method' => 'manual'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error during order distribution: " . $e->getMessage());
            $distribution_result = [
                'success' => false,
                'message' => 'Distribution failed: ' . $e->getMessage(),
                'distributed' => 0
            ];
        }
    }
    
    return [
        'synced' => $orders_synced,
        'updated' => $orders_updated,
        'distribution' => $distribution_result
    ];
}

function updateOrderShippingStatus($db, $order) {
    if (empty($order['tracking_number']) || empty($order['shipping_company_id'])) {
        error_log("Order {$order['id']} missing tracking number or shipping_company_id");
        return false;
    }

    try {
        // Fetch shipping company info from database
        $company = $db->get('shipping_companies', ['id', '=', $order['shipping_company_id']]);
        if (!$company->count()) {
            error_log("Shipping company ID {$order['shipping_company_id']} not found for order {$order['id']}");
            return false;
        }

        $company_data = $company->first();
        if (!$company_data->supports_tracking || empty($company_data->api_url)) {
            error_log("Shipping company {$company_data->name} does not support tracking or API URL missing.");
            return false;
        }

        // Call getShippingStatus with the correct company info
        $shipping_status = getShippingStatus(
            $order['tracking_number'],
            $company_data,
            $db
        );

        if ($shipping_status && $shipping_status !== $order['shipping_status']) {
            $update_data = [
                'shipping_status' => $shipping_status,
                'updated_at'      => date('Y-m-d H:i:s')
            ];

            $result = $db->update('orders', $order['id'], $update_data);

            if ($result) {
                error_log("Order {$order['id']} shipping status updated to: {$shipping_status}");
                return true;
            } else {
                error_log("Failed to update shipping status for order {$order['id']}");
            }
        }

    } catch (Exception $e) {
        error_log("Error updating shipping status for order {$order['id']}: " . $e->getMessage());
    }

    return false;
}

function getShippingStatus($tracking_number, $company_data, $db) {
    if (empty($tracking_number) || empty($company_data->name)) {
        error_log("Missing tracking number or shipping company name.");
        return null;
    }

    $company_name = strtolower($company_data->name);

    switch ($company_name) {
        case 'ozonexpress':
            return getOzonExpressStatus($tracking_number, $company_data);
        case 'dhl':
            return getDHLStatus($tracking_number, $company_data);
        case 'fedex':
            return getFedExStatus($tracking_number, $company_data);
        case 'ups':
            return getUPSStatus($tracking_number, $company_data);
        default:
            error_log("Unsupported shipping company: {$company_name}");
            return null;
    }
}

function getOzonExpressStatus($tracking_number, $company_data) {
    try {
        if (!$company_data->supports_tracking || empty($company_data->api_url)) {
            error_log("Tracking not supported or API URL missing for company: {$company_data->name}");
            return null;
        }

        // 1. Build the API URL (already contains customer_id and api_key as path parameters)
        $api_url = rtrim($company_data->api_url, '/') . '/tracking';

        // 2. Prepare the POST fields
        $post_fields = [
            'tracking-number' => $tracking_number
        ];

        // 3. Call the API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Consider true in production
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 4. Parse response
        if ($http_code === 200) {
            $data = json_decode($response, true);


            if (isset($data['TRACKING']['LAST_TRACKING']['STATUT'])) {
                return mapOzonExpressStatus($data['TRACKING']['LAST_TRACKING']['STATUT']);
            } else {
                return mapOzonExpressStatus('non soumis');
            }
        }

        error_log("OzonExpress tracking API error for {$company_data->name}: HTTP {$http_code} - {$response}");
        return null;

    } catch (Exception $e) {
        error_log("OzonExpress tracking exception for {$company_data->name}: " . $e->getMessage());
        return null;
    }
}

/**
 * Map OzonExpress status to our internal status
*/

function mapOzonExpressStatus($status) {
    $status_map = [
        'non soumis'             => 'not_submitted',
        'nouveau colis'          => 'pending',
        'En traitement'             => 'processing',
        'attente de ramassage'   => 'pickup_pending',
        'ramassé'                => 'collected',
        'en transit'             => 'in_transit',
        'en cours de livraison'  => 'out_for_delivery',
        'livré'                  => 'delivered',
        'échec de livraison'     => 'failed_delivery',
        'retourné'               => 'returned',
        'annulé'                 => 'cancelled'
    ];
    
    $status_lower = strtolower(trim($status));
    return $status_map[$status_lower] ?? 'unknown';
}

/**
 * Extract shipping information from WooCommerce order
*/

function extractShippingInfo($order, $db) {
    $shipping_info = [
        'tracking_number' => $order['tracking_number'] ?? null,
        'shipping_company' => null,
        'shipping_company_logo' => null,
        'shipping_company_website' => null
    ];

    if (!empty($order['shipping_company_id'])) {
        $company_id = (int)$order['shipping_company_id'];

        $company = $db->get('shipping_companies', ['id', '=', $company_id]);

        if ($company->count()) {
            $company_data = $company->first();
            $shipping_info['shipping_company'] = $company_data->name;
            $shipping_info['shipping_company_logo'] = $company_data->logo_url;
            $shipping_info['shipping_company_website'] = $company_data->website;
        }
    }

    return $shipping_info;
}

/**
 * Placeholder functions for other shipping companies
 */
function getDHLStatus($tracking_number, $company_data) {
    // Implement DHL API integration
    return null;
}

function getFedExStatus($tracking_number, $company_data) {
    // Implement FedEx API integration
    return null;
}

function getUPSStatus($tracking_number, $company_data) {
    // Implement UPS API integration
    return null;
}

/**
 * Map WooCommerce order status to standard status
 */

function mapWooCommerceStatus($status) {
    $status_map = [
        'pending'     => 'new-order', 
        'on-hold'     => 'new-order', 
        'processing'  => 'new-order', 
        'completed'   => 'confirmed',       
        'cancelled'   => 'cancelled',      
        'refunded'    => 'cancelled',      
        'failed'      => 'unreachable'  
    ];

    return $status_map[$status] ?? 'new-order'; 
}
/**
 * Extract weight from WooCommerce product meta data
 */
function extractWeightFromMeta($meta_data) {
    if (!is_array($meta_data)) {
        return null;
    }
    
    foreach ($meta_data as $meta) {
        if (isset($meta['key']) && $meta['key'] === '_weight' && !empty($meta['value'])) {
            return floatval($meta['value']);
        }
        
        // Also check for weight in display_key
        if (isset($meta['display_key']) && strtolower($meta['display_key']) === 'weight' && !empty($meta['value'])) {
            return floatval($meta['value']);
        }
    }
    
    return null;
}

function getSubscriptionPlanDetails($db, $user_id) {
    $query = "
        SELECT 
            p.id AS plan_id,
            p.name AS plan_name,
            p.price AS plan_price,
            p.is_custom,
            up.status,
            up.activated_at AS subscription_start,
            up.expires_at,
            up.duration_months,
            up.monthly_price,
            up.total_amount,
            pl.limit_key,
            pl.limit_value
        FROM user_plans up
        JOIN plans p ON up.plan_id = p.id
        LEFT JOIN plan_limits pl ON p.id = pl.plan_id
        WHERE up.user_id = ?
        AND up.status = 'active'
        AND (up.expires_at IS NULL OR up.expires_at > NOW())
        ORDER BY up.activated_at DESC
        LIMIT 1
    ";

    $result = $db->getThisQuery($query, [$user_id]);

    if (empty($result)) {
        return null;
    }

    // Structure the plan details
    $planDetails = [
        'plan_id'            => $result[0]['plan_id'],
        'plan_name'          => $result[0]['plan_name'],
        'plan_price'         => $result[0]['plan_price'],
        'is_custom'          => (bool)$result[0]['is_custom'],
        'status'             => $result[0]['status'],
        'subscription_start' => $result[0]['subscription_start'],
        'expires_at'         => $result[0]['expires_at'],
        'duration_months'    => $result[0]['duration_months'],
        'monthly_price'      => $result[0]['monthly_price'],
        'total_amount'       => $result[0]['total_amount'],
        'limits'             => []
    ];

    // Collect all limits for the plan
    foreach ($result as $row) {
        if (!empty($row['limit_key'])) {
            $planDetails['limits'][$row['limit_key']] = $row['limit_value'];
        }
    }

    return $planDetails;
}

function getMonthlyOrderUsage($db, $user_id) {
    $currentMonth = date('Y-m'); // e.g. "2025-09"

    $query = "
        SELECT orders_synced 
        FROM monthly_order_usage 
        WHERE user_id = ? AND usage_month = ?
        LIMIT 1
    ";
    $result = $db->getThisQuery($query, [$user_id, $currentMonth]);

    if (empty($result)) {
        return ['orders_synced' => 0]; // default if no record yet
    }

    return $result[0];
}

/**
 * Update monthly order usage
*/

function updateMonthlyOrderUsage($db, $user_id, $orders_count) {
    $current_month = date('Y-m');
    
    // Check if record exists
    $existing_usage = $db->getThisQuery(
        "SELECT id, orders_synced FROM monthly_order_usage WHERE user_id = ? AND usage_month = ?",
        [$user_id, $current_month]
    );
    
    if (!empty($existing_usage)) {
        // Update existing record
        $new_count = $existing_usage[0]['orders_synced'] + $orders_count;
        $update_data = [
            'orders_synced' => $new_count,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return $db->update('monthly_order_usage', $existing_usage[0]['id'], $update_data);
    } else {
        // Create new record
        $usage_data = [
            'user_id' => $user_id,
            'usage_month' => $current_month,
            'orders_synced' => $orders_count,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        return $db->insert('monthly_order_usage', $usage_data);
    }
}

/**
 * Synchronize orders from Shopify store
*/
function syncShopifyOrders($db, $store) {
    $shop_domain = rtrim($store['domain'], '/');
    $store['api_key'] = refreshShopifyToken($db, $store);
    $access_token = $store['api_key'];
    
    // Shopify Admin API endpoint for orders
    $orders_endpoint = "https://{$shop_domain}/admin/api/2024-01/orders.json";
    
    $headers = [
        'X-Shopify-Access-Token: ' . $access_token,
        'Content-Type: application/json'
    ];
    
    // Get orders from the last 30 days
    $params = [
        'limit' => 250,
        'status' => 'any',
        'created_at_min' => date('c', strtotime('-30 days'))
    ];
    
    $url = $orders_endpoint . '?' . http_build_query($params);
    
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
        throw new Exception("CURL Error: " . $curl_error);
    }
    
    if ($http_code !== 200) {
        throw new Exception("Erreur API Shopify: HTTP {$http_code} - Response: " . $response);
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['orders'])) {
        throw new Exception('Réponse API Shopify invalide');
    }
    
    $orders_synced = 0;
    $orders_updated = 0;
    
    foreach ($data['orders'] as $order) {
        try {
            // Check if order already exists
            $existing_order = $db->getThisQuery(
                "SELECT id, shipping_status, tracking_number, shipping_company_id FROM orders WHERE store_id = ? AND external_order_id = ?",
                [$store['id'], $order['id']]
            );
            
            if (!empty($existing_order)) {
                error_log("Shopify order {$order['id']} already exists, updating shipping status");
                
                // Update existing order's shipping status
                $updated = updateOrderShippingStatus($db, $existing_order[0]);
                if ($updated) {
                    $orders_updated++;
                }
                continue;
            }
            
            // Begin transaction for order and items
            $db->beginTransaction();
            
            try {
                // Extract customer info
                $customer_name = '';
                if (isset($order['customer'])) {
                    $customer_name = trim(($order['customer']['first_name'] ?? '') . ' ' . ($order['customer']['last_name'] ?? ''));
                } elseif (isset($order['shipping_address'])) {
                    $customer_name = trim(($order['shipping_address']['first_name'] ?? '') . ' ' . ($order['shipping_address']['last_name'] ?? ''));
                }
                
                // Extract shipping info
                $shipping_info = extractShopifyShippingInfo($order, $db);
                
                // Format addresses
                $formatted_shipping_address = formatShopifyAddress($order['shipping_address'] ?? []);
                $formatted_billing_address = formatShopifyAddress($order['billing_address'] ?? []);
                
                $order_data = [
                    'store_id'             => $store['id'],
                    'user_id'              => $store['user_id'],
                    'external_order_id'    => $order['id'],
                    'order_number'         => $order['order_number'] ?? $order['name'] ?? $order['id'],
                    'customer_name'        => $customer_name,
                    'customer_email'       => $order['email'] ?? $order['customer']['email'] ?? '',
                    'customer_phone'       => $order['phone'] ?? $order['customer']['phone'] ?? $order['shipping_address']['phone'] ?? '',
                    'customer_ville'       => $order['shipping_address']['city'] ?? null,
                    'shipping_address'     => $formatted_shipping_address,
                    'billing_address'      => $formatted_billing_address,
                    'total_amount'         => floatval($order['total_price'] ?? 0),
                    'currency'             => $order['currency'] ?? 'USD',
                    'status'               => mapShopifyStatus($order['financial_status'] ?? ''),
                    'shipping_status'      => 'not_submitted',
                    'payment_status'       => mapShopifyPaymentStatus($order['financial_status'] ?? ''),
                    'shipping_method'      => $order['shipping_lines'][0]['title'] ?? null,
                    'tracking_number'      => $shipping_info['tracking_number'] ?? null,
                    'weight'               => $order['total_weight'] ?? null,
                    'order_date'           => $order['created_at'] ?? date('Y-m-d H:i:s'),
                    'platform'             => 'shopify',
                    'created_at'           => date('Y-m-d H:i:s'),
                    'updated_at'           => date('Y-m-d H:i:s'),
                    'confirmed_by_agent'   => null,
                    'handled_at'           => null
                ];
                
                
                $result = $db->insert('orders', $order_data);
                
                if ($result) {
                    $order_id = $db->getLastInsertId();
                    
                    // Insert order items
                    if (isset($order['line_items']) && is_array($order['line_items'])) {
                        foreach ($order['line_items'] as $item) {
                            $item_data = [
                                'order_id' => $order_id,
                                'product_name' => $item['name'] ?? $item['title'] ?? '',
                                'product_sku' => $item['sku'] ?? '',
                                'quantity' => intval($item['quantity'] ?? 1),
                                'unit_price' => floatval($item['price'] ?? 0),
                                'total_price' => floatval($item['price'] ?? 0) * intval($item['quantity'] ?? 1),
                                'weight' => $item['grams'] ? ($item['grams'] / 1000) : null, // Convert grams to kg
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                            
                            $item_result = $db->insert('order_items', $item_data);
                            
                            if (!$item_result) {
                                throw new Exception("Erreur lors de l'insertion de l'article: " . ($item['name'] ?? 'Unknown'));
                            }
                        }
                    }
                    
                    $db->commit();
                    $orders_synced++;
                    
                    // Update shipping status for new order if tracking info exists
                    if ($shipping_info['tracking_number'] && $shipping_info['shipping_company']) {
                        $new_order_data = [
                            'id' => $order_id,
                            'tracking_number' => $shipping_info['tracking_number'],
                            'shipping_company_id' => $shipping_info['shipping_company'],
                            'shipping_status' => 'new'
                        ];
                        updateOrderShippingStatus($db, $new_order_data);
                    }

                    try {
                        require_once __DIR__ . '/../ai_confirmation/core/ConfirmationService.php';
                        \OBGecom\AiConfirmation\Core\ConfirmationService::enqueueForConfirmation(
                            (int)$order_id,
                            (int)$store['id'],
                            (int)$store['user_id']
                        );
                    } catch (\Throwable $__ai_ex) {
                        error_log('[ai_confirmation] enqueue failed for Shopify order '. $order_id . ': ' . $__ai_ex->getMessage());
                    }

                } else {
                    $db->rollback();
                }
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Error processing Shopify order {$order['id']}: " . $e->getMessage());
        }
    }
    
    error_log("Total Shopify orders synchronized: " . $orders_synced);
    error_log("Total Shopify orders updated: " . $orders_updated);
    
    $distribution_result = ['success' => false, 'distributed' => 0];
    
    if ($orders_synced > 0) {
        try {
            $distribution_method = getDistributionMethod($db, $store['user_id']);
            
            if ($distribution_method === 'performance') {
                require_once ('./distribute_orders_function.php');
                
                $distributor = new OrderDistributor($db, $store['user_id'], $store['id']);
                $distribution_result = $distributor->distributeOrders();
                
                if ($distribution_result['success']) {
                    error_log("Shopify orders distributed successfully: " . $distribution_result['message']);
                } else {
                    error_log("Shopify order distribution failed: " . $distribution_result['message']);
                }
            } else {
                error_log("Distribution method is 'manual' - skipping automatic distribution for Shopify");
                $distribution_result = [
                    'success' => true,
                    'message' => 'Shopify orders synchronized. Manual distribution required.',
                    'distributed' => 0,
                    'method' => 'manual'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error during Shopify order distribution: " . $e->getMessage());
            $distribution_result = [
                'success' => false,
                'message' => 'Distribution failed: ' . $e->getMessage(),
                'distributed' => 0
            ];
        }
    }
    
    return [
        'synced' => $orders_synced,
        'updated' => $orders_updated,
        'distribution' => $distribution_result
    ];
}

function refreshShopifyToken($db, $store) {
    // Only refresh if token is missing or close to expiry
    if (!empty($store['token_expires_at']) &&
        strtotime($store['token_expires_at']) > time() + 120) {
        return $store['api_key']; // still valid, nothing to do
    }

    if (empty($store['refresh_token'])) {
        throw new Exception('Aucun refresh token disponible — reconnexion requise');
    }

    $token_url = "https://{$store['domain']}/admin/oauth/access_token";
    $post_fields = [
        'client_id'     => SHOPIFY_CLIENT_ID,
        'client_secret' => SHOPIFY_CLIENT_SECRET,
        'grant_type'    => 'refresh_token',
        'refresh_token' => $store['refresh_token']
    ];

    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($http_code !== 200 || !isset($result['access_token'])) {
        error_log("Shopify token refresh failed for store {$store['id']}: " . $response);
        // Mark store as needing manual reconnection
        $db->update('stores', $store['id'], [
            'is_connected' => 0,
            'connection_error' => 'Token expiré, reconnexion requise'
        ]);
        throw new Exception('Impossible de rafraîchir le token Shopify — reconnexion requise');
    }

    $new_expires_at = date('Y-m-d H:i:s', time() + ($result['expires_in'] ?? 3600));

    $db->update('stores', $store['id'], [
        'api_key' => $result['access_token'],
        'refresh_token' => $result['refresh_token'] ?? $store['refresh_token'],
        'token_expires_at' => $new_expires_at,
        'updated_at' => date('Y-m-d H:i:s')
    ]);

    return $result['access_token'];
}

/**
 * Format Shopify address
 */
function formatShopifyAddress($address_data) {
    if (!$address_data || !is_array($address_data)) {
        return '';
    }
    
    $address_parts = [];
    
    // Add name
    $name = trim(($address_data['first_name'] ?? '') . ' ' . ($address_data['last_name'] ?? ''));
    if (!empty($name)) {
        $address_parts[] = $name;
    }
    
    // Add company
    if (!empty($address_data['company'])) {
        $address_parts[] = trim($address_data['company']);
    }
    
    // Add address lines
    if (!empty($address_data['address1'])) {
        $address_parts[] = trim($address_data['address1']);
    }
    
    if (!empty($address_data['address2'])) {
        $address_parts[] = trim($address_data['address2']);
    }
    
    // Add city and zip
    $city_line = '';
    if (!empty($address_data['city']) && !empty($address_data['zip'])) {
        $city_line = $address_data['city'] . ' ' . $address_data['zip'];
    } elseif (!empty($address_data['city'])) {
        $city_line = $address_data['city'];
    } elseif (!empty($address_data['zip'])) {
        $city_line = $address_data['zip'];
    }
    
    if (!empty($city_line)) {
        $address_parts[] = $city_line;
    }
    
    // Add province/state
    if (!empty($address_data['province'])) {
        $address_parts[] = trim($address_data['province']);
    }
    
    // Add country
    if (!empty($address_data['country'])) {
        $address_parts[] = trim($address_data['country']);
    }
    
    return implode(', ', array_filter($address_parts));
}

/**
 * Extract shipping information from Shopify order
 */
function extractShopifyShippingInfo($order, $db) {
    $shipping_info = [
        'tracking_number' => null,
        'shipping_company' => null,
        'shipping_company_logo' => null,
        'shipping_company_website' => null
    ];
    
    // Check fulfillments for tracking info
    if (isset($order['fulfillments']) && is_array($order['fulfillments'])) {
        foreach ($order['fulfillments'] as $fulfillment) {
            if (!empty($fulfillment['tracking_number'])) {
                $shipping_info['tracking_number'] = $fulfillment['tracking_number'];
                
                // Try to match tracking company
                if (!empty($fulfillment['tracking_company'])) {
                    $company_name = $fulfillment['tracking_company'];
                    
                    $company = $db->getThisQuery(
                        "SELECT id, name, logo_url, website FROM shipping_companies WHERE LOWER(name) = LOWER(?)",
                        [$company_name]
                    );
                    
                    if (!empty($company)) {
                        $shipping_info['shipping_company'] = $company[0]['id'];
                        $shipping_info['shipping_company_logo'] = $company[0]['logo_url'];
                        $shipping_info['shipping_company_website'] = $company[0]['website'];
                    }
                }
                break;
            }
        }
    }
    
    return $shipping_info;
}

/**
 * Map Shopify financial status to standard status
 */
function mapShopifyStatus($financial_status) {
    $status_map = [
        'pending'        => 'new-order',
        'authorized'     => 'new-order',
        'partially_paid' => 'new-order',
        'paid'           => 'confirmed',
        'partially_refunded' => 'confirmed',
        'refunded'       => 'cancelled',
        'voided'         => 'cancelled'
    ];
    
    return $status_map[$financial_status] ?? 'new-order';
}

/**
 * Map Shopify payment status
 */
function mapShopifyPaymentStatus($financial_status) {
    $status_map = [
        'pending'        => 'pending',
        'authorized'     => 'authorized',
        'partially_paid' => 'partially_paid',
        'paid'           => 'paid',
        'partially_refunded' => 'partially_refunded',
        'refunded'       => 'refunded',
        'voided'         => 'voided'
    ];
    
    return $status_map[$financial_status] ?? 'pending';
}

// function to get the distribution method
function getDistributionMethod($db, $user_id) {
    // First try to get user-specific setting
    $user_setting = $db->getThisQuery(
        "SELECT setting_value FROM system_settings 
         WHERE user_id = ? AND setting_key = 'distribution_method' 
         LIMIT 1",
        [$user_id]
    );
    
    if (!empty($user_setting)) {
        return $user_setting[0]['setting_value'];
    }
    
    // If no user-specific setting, check for global setting (user_id = 0 or NULL)
    $global_setting = $db->getThisQuery(
        "SELECT setting_value FROM system_settings 
         WHERE (user_id = 0 OR user_id IS NULL) AND setting_key = 'distribution_method' 
         LIMIT 1"
    );
    
    if (!empty($global_setting)) {
        return $global_setting[0]['setting_value'];
    }
    
    // Default to 'performance' if no setting found
    return 'performance';
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