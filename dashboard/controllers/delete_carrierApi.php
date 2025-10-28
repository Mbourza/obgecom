<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        echo json_encode(['success' => false, 'message' => 'Carrier ID is required']);
        exit;
    }
    
    $carrier_id = (int)$input['id'];
    
    // Get the current user ID - adjust based on your auth system
    $user_id = getCurrentUserId($db);
    
    if (!$user_id) {

        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit;
    }
    
    // Verify the shipping company exists and belongs to the current user
    $verify_query = "SELECT id, name FROM shipping_companies WHERE id = ?";
    $carrier_data = $db->getThisQuery($verify_query, [$carrier_id]);
    
    if (empty($carrier_data)) {
        echo json_encode([
            'success' => false,
            'message' => 'Transporteur non trouvé ou accès refusé'
        ]);
        exit;
    }
    
    $carrier_name = $carrier_data[0]['name'];
    
    // Check for ongoing shipments in shipping_process table
    // Look for orders that have this shipping company and are not finished
    $ongoing_shipments_query = "
        SELECT COUNT(*) as ongoing_count 
        FROM orders o 
        INNER JOIN shipping_process sp ON o.id = sp.order_id 
        WHERE o.shipping_company_id = ? 
        AND sp.status NOT IN ('delivered', 'cancelled', 'failed')
    ";
    
    $ongoing_result = $db->getThisQuery($ongoing_shipments_query, [$carrier_id]);
    $ongoing_count = $ongoing_result ? (int)$ongoing_result[0]['ongoing_count'] : 0;
    
    // If there are ongoing shipments, prevent deletion
    if ($ongoing_count > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Impossible de supprimer ce transporteur. Il y a {$ongoing_count} expédition(s) en cours."
        ]);
        exit;
    }
    
    // Also check if there are any orders assigned to this shipping company without shipping process entries
    $assigned_orders_query = "
        SELECT COUNT(*) as assigned_count 
        FROM orders 
        WHERE shipping_company_id = ? 
        AND id NOT IN (
            SELECT DISTINCT order_id 
            FROM shipping_process 
            WHERE order_id IS NOT NULL
        )
    ";
    
    $assigned_result = $db->getThisQuery($assigned_orders_query, [$carrier_id]);
    $assigned_count = $assigned_result ? (int)$assigned_result[0]['assigned_count'] : 0;
    
    if ($assigned_count > 0) {
        echo json_encode([
            'success' => false,
            'message' => "Impossible de supprimer ce transporteur. Il y a {$assigned_count} commande(s) assignée(s) à ce transporteur."
        ]);
        exit;
    }
    
    // If no ongoing shipments, proceed with deletion
    $result = $db->delete("shipping_companies", ["id", "=", $carrier_id]);
    
    if ($result) {
        // Get updated carrier count for the user
        $count_query = "SELECT COUNT(*) as carrier_count FROM shipping_companies WHERE user_id = ?";
        $count_result = $db->getThisQuery($count_query, [$user_id]);
        $current_carriers = $count_result ? (int)$count_result[0]['carrier_count'] : 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Transporteur supprimé avec succès',
            'carrier_id' => $carrier_id,
            'carrier_name' => $carrier_name,
            'current_carriers' => $current_carriers
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Échec de la suppression du transporteur'
        ]);
    }

} catch (Exception $e) {
    // Log the error
    error_log("Error in delete_carrier.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur s\'est produite lors de la suppression du transporteur'
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