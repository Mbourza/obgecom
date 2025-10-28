<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

try {
    // Check if request method is GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Méthode non autorisée'
        ]);
        exit;
    }

    // Validate required parameter
    if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'L\'ID de l\'agent est requis'
        ]);
        exit;
    }

    // Check if password should be included (only for admin/editing purposes)
    $includePassword = isset($_GET['include_password']) && $_GET['include_password'] === 'true';

    // Sanitize and validate input data
    $agentId = trim($_GET['id']);

    // Validate agent ID is numeric
    if (!is_numeric($agentId) || intval($agentId) <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID d\'agent invalide'
        ]);
        exit;
    }

    $agentId = intval($agentId);

    // Build the base query
    $selectQuery = "SELECT 
                        id,
                        name,
                        email,
                        phone,
                        service_fee,
                        is_active,
                        created_at,
                        updated_at";

    // Include password only if explicitly requested
    if ($includePassword) {
        $selectQuery .= ", password";
    }

    $selectQuery .= " FROM agents WHERE id = ?";

    // Execute select query
    $result = $db->getThisQuery($selectQuery, [$agentId]);

    // Check if agent exists
    if (empty($result)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Agent non trouvé'
        ]);
        exit;
    }

    // Get agent data
    $agentData = $result[0];

    // Format response data
    $responseData = [
        'id' => $agentData['id'],
        'name' => $agentData['name'],
        'email' => $agentData['email'],
        'phone' => $agentData['phone'],
        'service_fee' => $agentData['service_fee'],
        'is_active' => $agentData['is_active'],
        'created_at' => $agentData['created_at'],
        'updated_at' => $agentData['updated_at']
    ];

    // Include password only if requested (typically for admin/editing purposes)
    if ($includePassword && isset($agentData['password'])) {
        $responseData['password'] = $agentData['password'];
    }

    echo json_encode([
        'success' => true,
        'agent' => $responseData,
        'message' => 'Agent récupéré avec succès'
    ]);

} catch (PDOException $e) {
    // Handle database specific errors
    error_log("Database error in get_agentApi.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données'
    ]);

} catch (Exception $e) {
    // Handle general errors
    error_log("Error in get_agentApi.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur'
    ]);
}
?>