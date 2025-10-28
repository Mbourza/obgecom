<?php
// controllers/import_google_sheets.php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("./config/init.php");
}

require_once('../vendor/autoload.php');
use Google\Client;
use Google\Service\Sheets;

$db = DB::getInstance();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'import_from_sheets':
            echo importFromGoogleSheets($db);
            exit;
        case 'validate_sheet':
            echo validateGoogleSheet($db);
            exit;
        case 'get_sheet_preview':
            echo getSheetPreview($db);
            exit;
    }
}

/**
 * Import orders from Google Sheets
*/

function importFromGoogleSheets($db) {
    
    try {
        $sheet_url = $_POST['sheet_url'] ?? '';
        $sheet_name = $_POST['sheet_name'] ?? 'Sheet1';
        $start_row = intval($_POST['start_row'] ?? 2); // Skip header row
        $mapping = json_decode($_POST['column_mapping'] ?? '{}', true);
        
        if (empty($sheet_url)) {
            throw new Exception('URL de la feuille Google Sheets requis');
        }
        
        // Extract spreadsheet ID from URL
        $sheet_id = extractSpreadsheetId($sheet_url);
        if (!$sheet_id) {
            throw new Exception('URL Google Sheets invalide');
        }
        
        // Initialize Google Sheets client
        $client = getGoogleSheetsClient();
        $service = new Sheets($client);
        
        // Get data from Google Sheets
        $range = $sheet_name . '!A' . $start_row . ':Z1000'; // Adjust range as needed
        $response = $service->spreadsheets_values->get($sheet_id, $range);
        $values = $response->getValues();
        
        if (empty($values)) {
            throw new Exception('Aucune donnée trouvée dans la feuille');
        }
        
        $orders_imported = 0;
        $errors = [];
        
        foreach ($values as $row_index => $row) {
            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                $order_data = mapRowToOrder($row, $mapping);
                
                // Validate required fields
                if (empty($order_data['order_number'])) {
                    $errors[] = "Ligne " . ($start_row + $row_index) . ": Numéro de commande manquant";
                    continue;
                }
                
                // Check if order already exists
                $existing_order = $db->getThisQuery(
                    "SELECT id FROM orders WHERE order_number = ?",
                    [$order_data['order_number']]
                );
                
                if (!empty($existing_order)) {
                    $errors[] = "Ligne " . ($start_row + $row_index) . ": Commande {$order_data['order_number']} existe déjà";
                    continue;
                }
                
                // Begin transaction
                $db->beginTransaction();
                
                try {
                    // Prepare order data for insertion
                    $db_order_data = [
                        'store_id' => null, // Google Sheets import
                        'user_id' => getCurrentUserId($db), // Get current user
                        'external_order_id' => null,
                        'order_number' => $order_data['order_number'],
                        'customer_name' => $order_data['customer_name'] ?? '',
                        'customer_email' => $order_data['customer_email'] ?? '',
                        'customer_phone' => $order_data['customer_phone'] ?? '',
                        'total_amount' => floatval($order_data['total_amount'] ?? 0),
                        'currency' => $order_data['currency'] ?? 'MAD',
                        'status' => mapImportStatus($order_data['status'] ?? 'pending'),
                        'payment_status' => $order_data['payment_status'] ?? 'pending',
                        'shipping_address' => json_encode([
                            'address' => $order_data['shipping_address'] ?? '',
                            'city' => $order_data['shipping_city'] ?? '',
                            'state' => $order_data['shipping_state'] ?? '',
                            'postcode' => $order_data['shipping_postcode'] ?? '',
                            'country' => $order_data['shipping_country'] ?? 'MA'
                        ]),
                        'billing_address' => json_encode([
                            'address' => $order_data['billing_address'] ?? $order_data['shipping_address'] ?? '',
                            'city' => $order_data['billing_city'] ?? $order_data['shipping_city'] ?? '',
                            'state' => $order_data['billing_state'] ?? $order_data['shipping_state'] ?? '',
                            'postcode' => $order_data['billing_postcode'] ?? $order_data['shipping_postcode'] ?? '',
                            'country' => $order_data['billing_country'] ?? 'MA'
                        ]),
                        'order_date' => parseDate($order_data['order_date'] ?? date('Y-m-d H:i:s')),
                        'platform' => 'google_sheets',
                        'notes' => $order_data['notes'] ?? '',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $result = $db->insert('orders', $db_order_data);
                    
                    if ($result) {
                        $order_id = $db->getLastInsertId();
                        
                        // Insert order items if provided
                        if (!empty($order_data['products'])) {
                            $products = parseProducts($order_data['products']);
                            
                            foreach ($products as $product) {
                                $item_data = [
                                    'order_id' => $order_id,
                                    'product_name' => $product['name'],
                                    'product_sku' => $product['sku'] ?? '',
                                    'quantity' => intval($product['quantity'] ?? 1),
                                    'unit_price' => floatval($product['price'] ?? 0),
                                    'total_price' => floatval($product['quantity'] ?? 1) * floatval($product['price'] ?? 0),
                                    'weight' => floatval($product['weight'] ?? 0),
                                    'created_at' => date('Y-m-d H:i:s')
                                ];
                                
                                $db->insert('order_items', $item_data);
                            }
                        }
                        
                        $db->commit();
                        $orders_imported++;
                    } else {
                        $db->rollback();
                        $errors[] = "Ligne " . ($start_row + $row_index) . ": Erreur lors de l'insertion";
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    $errors[] = "Ligne " . ($start_row + $row_index) . ": " . $e->getMessage();
                }
            } catch (Exception $e) {
                $errors[] = "Ligne " . ($start_row + $row_index) . ": " . $e->getMessage();
            }
        }
        
        return json_encode([
            'success' => true,
            'message' => "{$orders_imported} commandes importées avec succès",
            'orders_imported' => $orders_imported,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'importation: ' . $e->getMessage()
        ]);
    }
}

/**
 * Validate Google Sheet structure and get column headers
 */
function validateGoogleSheet($db) {
    try {
        $sheet_url = $_POST['sheet_url'] ?? '';
        $sheet_name = $_POST['sheet_name'] ?? 'Sheet1';
        
        if (empty($sheet_url)) {
            throw new Exception('URL de la feuille Google Sheets requis');
        }
        
        $sheet_id = extractSpreadsheetId($sheet_url);
        if (!$sheet_id) {
            throw new Exception('URL Google Sheets invalide');
        }
        
        $client = getGoogleSheetsClient();
        $service = new Sheets($client);
        
        // Get header row
        $range = $sheet_name . '!A1:Z1';
        $response = $service->spreadsheets_values->get($sheet_id, $range);
        $headers = $response->getValues()[0] ?? [];
        
        if (empty($headers)) {
            throw new Exception('Aucun en-tête trouvé dans la feuille');
        }
        
        // Get sample data (first 5 rows)
        $sample_range = $sheet_name . '!A2:Z6';
        $sample_response = $service->spreadsheets_values->get($sheet_id, $sample_range);
        $sample_data = $sample_response->getValues() ?? [];
        
        return json_encode([
            'success' => true,
            'headers' => $headers,
            'sample_data' => $sample_data,
            'suggested_mapping' => suggestColumnMapping($headers)
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur lors de la validation: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get preview of data to be imported
 */
function getSheetPreview($db) {

    try {
        $sheet_url = $_POST['sheet_url'] ?? '';
        $sheet_name = $_POST['sheet_name'] ?? 'Sheet1';
        $start_row = intval($_POST['start_row'] ?? 2);
        $mapping = json_decode($_POST['column_mapping'] ?? '{}', true);
        
        $sheet_id = extractSpreadsheetId($sheet_url);
        $client = getGoogleSheetsClient();
        $service = new Sheets($client);
        
        // Get preview data (first 10 rows)
        $range = $sheet_name . '!A' . $start_row . ':Z' . ($start_row + 9);
        $response = $service->spreadsheets_values->get($sheet_id, $range);
        $values = $response->getValues() ?? [];
        
        $preview_data = [];
        foreach ($values as $row) {
            if (!empty(array_filter($row))) {
                $order_data = mapRowToOrder($row, $mapping);
                $preview_data[] = $order_data;
            }
        }
        
        return json_encode([
            'success' => true,
            'preview_data' => $preview_data
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur lors de la prévisualisation: ' . $e->getMessage()
        ]);
    }
}

/**
 * Helper functions
 */

function getGoogleSheetsClient() {
    $client = new Client();
    
    // Method 1: Service Account (Recommended for server-to-server)
    if (file_exists('../config/google-credentials.json')) {
        $client->setAuthConfig('../config/google-credentials.json');
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        return $client;
    }
    
    // Method 2: API Key (for public sheets only)
    $api_key = getenv('GOOGLE_API_KEY') ?: 'YOUR_GOOGLE_API_KEY';
    if ($api_key && $api_key !== 'YOUR_GOOGLE_API_KEY') {
        $client->setDeveloperKey($api_key);
        return $client;
    }
    
    throw new Exception('Configuration Google API manquante');
}

function extractSpreadsheetId($url) {
    // Extract spreadsheet ID from various Google Sheets URL formats
    $patterns = [
        '/\/spreadsheets\/d\/([a-zA-Z0-9-_]+)/',
        '/spreadsheet\/ccc\?key=([a-zA-Z0-9-_]+)/',
        '/^([a-zA-Z0-9-_]+)$/' // Direct ID
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

function mapRowToOrder($row, $mapping) {
    $order_data = [];
    
    foreach ($mapping as $field => $column_index) {
        if (isset($row[$column_index])) {
            $order_data[$field] = trim($row[$column_index]);
        }
    }
    
    return $order_data;
}

function suggestColumnMapping($headers) {
    $suggestions = [];
    
    $field_patterns = [
        'order_number' => ['commande', 'order', 'numero', 'number', 'cmd'],
        'customer_name' => ['nom', 'name', 'client', 'customer'],
        'customer_email' => ['email', 'mail', 'e-mail'],
        'customer_phone' => ['telephone', 'phone', 'tel', 'mobile'],
        'total_amount' => ['total', 'montant', 'amount', 'prix', 'price'],
        'status' => ['statut', 'status', 'etat', 'state'],
        'order_date' => ['date', 'created', 'commande_date'],
        'products' => ['produit', 'product', 'article', 'items'],
        'shipping_address' => ['adresse', 'address', 'livraison', 'shipping'],
        'shipping_city' => ['ville', 'city', 'localite'],
        'notes' => ['note', 'comment', 'remarque', 'observation']
    ];
    
    foreach ($headers as $index => $header) {
        $header_lower = strtolower($header);
        
        foreach ($field_patterns as $field => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($header_lower, $pattern) !== false) {
                    $suggestions[$field] = $index;
                    break 2;
                }
            }
        }
    }
    
    return $suggestions;
}

function mapImportStatus($status) {
    $status_mapping = [
        'en attente' => 'pending',
        'pending' => 'pending',
        'en cours' => 'processing',
        'processing' => 'processing',
        'en preparation' => 'processing',
        'expediee' => 'shipped',
        'shipped' => 'shipped',
        'livree' => 'delivered',
        'delivered' => 'delivered',
        'annulee' => 'cancelled',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled'
    ];
    
    $status_lower = strtolower($status);
    return $status_mapping[$status_lower] ?? 'pending';
}

function parseDate($date_string) {
    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d',
        'd/m/Y',
        'd/m/Y H:i:s',
        'm/d/Y',
        'm/d/Y H:i:s'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $date_string);
        if ($date !== false) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    
    return date('Y-m-d H:i:s');
}

function parseProducts($products_string) {
    if (empty($products_string)) {
        return [];
    }
    
    $products = [];
    $items = explode(';', $products_string); // Assuming semicolon separator
    
    foreach ($items as $item) {
        $item = trim($item);
        if (empty($item)) continue;
        
        // Try to parse format: "Product Name (qty: 2, price: 100)"
        if (preg_match('/^(.+?)\s*\(.*qty[:\s]*(\d+).*price[:\s]*([\d.,]+).*\)$/i', $item, $matches)) {
            $products[] = [
                'name' => trim($matches[1]),
                'quantity' => intval($matches[2]),
                'price' => floatval(str_replace(',', '.', $matches[3]))
            ];
        } else {
            // Simple format: just product name
            $products[] = [
                'name' => $item,
                'quantity' => 1,
                'price' => 0
            ];
        }
    }
    
    return $products;
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