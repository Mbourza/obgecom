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

    // Build the enhanced query with LEFT JOINs for responsable and team
    $selectQuery = "SELECT 
                        a.id,
                        a.name,
                        a.email,
                        a.phone,
                        a.service_fee,
                        a.score,
                        a.region_id,
                        a.confirmation_rate,
                        a.total_payed,
                        a.total_earned,
                        a.is_active,
                        a.created_at,
                        a.updated_at,
                        a.team_id,
                        a.responsable_id,
                        -- Get responsable details
                        r.name as responsable_name,
                        -- Get team details
                        t.name as team_name";

    // Include password only if explicitly requested
    if ($includePassword) {
        $selectQuery .= ", a.password";
    }

    $selectQuery .= " FROM agents a
                    LEFT JOIN responsables r ON a.responsable_id = r.id
                    LEFT JOIN teams t ON a.team_id = t.id
                    WHERE a.id = ?";

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

    // Format response data (flat structure for compatibility with edit form)
    $responseData = [
        'id' => $agentData['id'],
        'name' => $agentData['name'],
        'email' => $agentData['email'],
        'phone' => $agentData['phone'],
        'region' => $agentData['region_id'],
        'service_fee' => floatval($agentData['service_fee']),
        'score' => intval($agentData['score']),
        'confirmation_rate' => floatval($agentData['confirmation_rate']),
        'total_payed' => floatval($agentData['total_payed']),
        'total_earned' => floatval($agentData['total_earned']),
        'is_active' => (bool)$agentData['is_active'],
        'created_at' => $agentData['created_at'],
        'updated_at' => $agentData['updated_at'],
        // Team information
        'team_id' => $agentData['team_id'],
        'team_name' => $agentData['team_name'],
        // Responsable information
        'responsable_id' => $agentData['responsable_id'],
        'responsable_name' => $agentData['responsable_name']
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
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);

} catch (Exception $e) {
    // Handle general errors
    error_log("Error in get_agentApi.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur: ' . $e->getMessage()
    ]);
}
?>