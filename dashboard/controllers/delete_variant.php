<?php
// delete_sku.php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false, 
        'message' => 'Méthode non autorisée',
        'code' => 'INVALID_METHOD'
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
    // Get SKU ID from request
    $sku_id = isset($_POST['variant_id']) ? intval($_POST['variant_id']) : 0;

    if ($sku_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'ID SKU invalide',
            'code' => 'INVALID_SKU_ID'
        ]);
        exit;
    }
    
    // Check if SKU exists and belongs to user
    $sku_check = $db->getThisQuery("
        SELECT pv.id, pv.sku, pv.product_id
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        WHERE pv.id = ? AND p.user_id = ?
    ", [$sku_id, $user_id]);
    
    if (empty($sku_check)) {

        echo json_encode([
            'success' => false,
            'message' => 'SKU non trouvé ou non autorisé',
            'code' => 'SKU_NOT_FOUND'
        ]);
        exit;
    }
    
    $sku_data = $sku_check[0];
    
    // Delete the SKU
    $delete_query = "DELETE FROM product_variants WHERE id = ?";
    $result = $db->query($delete_query, [$sku_id]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'SKU supprimé avec succès',
            'sku_id' => $sku_id,
            'sku' => $sku_data['sku'],
            'data' => [
                'deleted_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Échec de la suppression du SKU',
            'code' => 'DELETE_FAILED'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in delete_sku.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de la suppression',
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