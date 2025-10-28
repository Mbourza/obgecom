<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

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

if (!isset($_POST['action']) || $_POST['action'] !== 'check_shipping_status') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $user_id = getCurrentUserId($db);
    if (!$user_id) {
        throw new Exception('Utilisateur non authentifié');
    }

    $order_number = $_POST['order_number'] ?? '';
    if (empty($order_number)) {
        throw new Exception('Numéro de commande requis');
    }

    // First check if we already have a tracking number in our database
    $order = $db->getThisQuery("
        SELECT tracking_number, shipping_company_id 
        FROM orders 
        WHERE order_number = ? AND user_id = ?
    ", [$order_number, $user_id]);

    if (!empty($order) && !empty($order[0]['tracking_number'])) {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'tracking_number' => $order[0]['tracking_number']
        ]);
        exit;
    }

    // If no tracking number in our DB, check with shipping company API
    $shipping_company = $db->getThisQuery("
        SELECT sc.name, sc.api_url, sc.api_key
        FROM shipping_settings ss
        JOIN shipping_companies sc ON ss.shipping_company_id = sc.id
        WHERE ss.user_id = ? AND sc.is_active = 1
        ORDER BY ss.priority ASC
        LIMIT 1
    ", [$user_id]);

    if (empty($shipping_company)) {
        throw new Exception('Aucune compagnie de livraison configurée');
    }

    $shipping_company = $shipping_company[0];
    $company_name = strtolower($shipping_company['name']);

    if ($company_name === 'ozonexpress') {
        // Check with OzonExpress API if order exists
        $url = rtrim($shipping_company['api_url'], '/') . '/check-parcel';
        $payload = [
            'order-number' => $order_number,
            'api-key' => $shipping_company['api_key']
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response_data = json_decode($response, true);

        if ($http_code === 200 && isset($response_data['PARCEL']['TRACKING-NUMBER'])) {
            $tracking_number = $response_data['PARCEL']['TRACKING-NUMBER'];
            
            // Update our database with the tracking number
            $db->updateWhere('orders', [
                'tracking_number' => $tracking_number,
                'shipping_company_id' => $shipping_company['id'],
                'updated_at' => date('Y-m-d H:i:s')
            ], ['order_number' => $order_number, 'user_id' => $user_id]);

            echo json_encode([
                'success' => true,
                'exists' => true,
                'tracking_number' => $tracking_number
            ]);
            exit;
        } else {
            // Order doesn't exist in shipping system
            echo json_encode([
                'success' => true,
                'exists' => false
            ]);
            exit;
        }
    } else {
        throw new Exception("Support pour la compagnie {$shipping_company['name']} non encore implémenté.");
    }

} catch (Exception $e) {
    error_log("Error in check_shipping_status.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
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