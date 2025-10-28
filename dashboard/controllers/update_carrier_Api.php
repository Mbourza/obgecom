<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Méthode non autorisée'
        ]);
        exit;
    }

    // Validate required fields
    if (empty($_POST['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du transporteur requis'
        ]);
        exit;
    }

    $carrierId   = intval($_POST['id']);
    $api_url     = isset($_POST['api_url']) ? trim($_POST['api_url']) : '';
    $trackingUrl = isset($_POST['tracking_url']) ? trim($_POST['tracking_url']) : '';
    $isActive    = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    // Validate ID
    if ($carrierId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du transporteur invalide'
        ]);
        exit;
    }

    // Validate URLs if provided
    if (!empty($trackingUrl) && !filter_var($trackingUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'URL de suivi invalide'
        ]);
        exit;
    }

    if (!empty($api_url) && !filter_var($api_url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'URL API invalide'
        ]);
        exit;
    }

    // Validate is_active
    if (!in_array($isActive, [0, 1])) {
        $isActive = 1;
    }

    // Check if carrier exists
    $checkQuery = "SELECT id FROM shipping_companies WHERE id = ?";
    $existingCarrier = $db->getThisQuery($checkQuery, [$carrierId]);

    if (empty($existingCarrier)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Société de livraison non trouvée'
        ]);
        exit;
    }

    // Update query
    $updateQuery = "UPDATE shipping_companies SET 
                        api_url = ?,
                        tracking_url = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?";

    $updateParams = [
        $api_url,
        $trackingUrl,
        $isActive,
        $carrierId
    ];

    $result = $db->getThisQuery($updateQuery, $updateParams);

    if ($result !== false) {
        // Fetch updated data
        $getUpdatedQuery = "SELECT 
                                id,
                                name,
                                api_url,
                                tracking_url,
                                logo_url,
                                contact_email,
                                phone,
                                website,
                                is_active,
                                supports_tracking,
                                created_at,
                                updated_at
                            FROM shipping_companies 
                            WHERE id = ?";
        $updatedCarrier = $db->getThisQuery($getUpdatedQuery, [$carrierId]);

        $responseData = !empty($updatedCarrier) ? $updatedCarrier[0] : [];

        echo json_encode([
            'success' => true,
            'data' => $responseData,
            'message' => 'Société de livraison mise à jour avec succès'
        ]);
    } else {
        throw new Exception('Échec de la mise à jour en base de données');
    }

} catch (PDOException $e) {
    error_log("Database error in update_carrier_Api.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données'
    ]);

} catch (Exception $e) {
    error_log("Error in update_carrier_Api.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur'
    ]);
}
?>