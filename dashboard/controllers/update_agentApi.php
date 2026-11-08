<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$db = DB::getInstance();

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Méthode non autorisée'
        ]);
        exit;
    }

    // Validate required fields
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'L\'ID de l\'agent est requis'
        ]);
        exit;
    }

    if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le nom de l\'agent est requis'
        ]);
        exit;
    }

    if (!isset($_POST['email']) || empty(trim($_POST['email']))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'L\'email de l\'agent est requis'
        ]);
        exit;
    }

    // Sanitize and validate input data
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $region = isset($_POST['region_id']) ? trim($_POST['region_id']) : '';
    $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    
    // Get team_id and responsable_id from form
    $teamId = isset($_POST['team_id']) && $_POST['team_id'] !== '' ? intval($_POST['team_id']) : null;
    $responsableId = isset($_POST['responsable_id']) && $_POST['responsable_id'] !== '' ? intval($_POST['responsable_id']) : null;
    
    // Handle service_fee (should come from service_fee field, not price)
    $serviceFee = null;
    if (isset($_POST['service_fee']) && $_POST['service_fee'] !== '' && $_POST['service_fee'] !== null) {
        $serviceFee = floatval($_POST['service_fee']);
        
        // Validate service_fee is between 0 and 100
        if ($serviceFee < 0 || $serviceFee > 100) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Le taux de service doit être entre 0 et 100%'
            ]);
            exit;
        }
    }
    
    // Handle score
    $score = isset($_POST['score']) && $_POST['score'] !== '' ? intval($_POST['score']) : 50;
    if ($score < 0 || $score > 100) {
        $score = 50; // Default value
    }

    // Validate ID
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID d\'agent invalide'
        ]);
        exit;
    }

    // Validate name length
    if (strlen($name) > 255) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le nom de l\'agent est trop long (255 caractères maximum)'
        ]);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Format d\'email invalide'
        ]);
        exit;
    }

    // Validate email length
    if (strlen($email) > 255) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'L\'email est trop long (255 caractères maximum)'
        ]);
        exit;
    }

    // Validate phone length if provided
    if (!empty($phone) && strlen($phone) > 20) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le numéro de téléphone est trop long (20 caractères maximum)'
        ]);
        exit;
    }

    // Validate is_active value
    if (!in_array($isActive, [0, 1])) {
        $isActive = 1; // Default to active
    }

    // Validate team_id if provided
    if ($teamId !== null && $teamId <= 0) {
        $teamId = null; // Reset to null if invalid
    }
    
    // Validate responsable_id if provided
    if ($responsableId !== null && $responsableId <= 0) {
        $responsableId = null; // Reset to null if invalid
    }

    // Check if agent exists
    $existingAgentQuery = "SELECT id, user_id FROM agents WHERE id = ?";
    $existingAgent = $db->getThisQuery($existingAgentQuery, [$id]);

    if (empty($existingAgent)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Agent non trouvé'
        ]);
        exit;
    }

    // Get user_id from existing agent (for validation)
    $userId = $existingAgent[0]['user_id'];

    // Validate team_id belongs to the same user
    if ($teamId !== null) {
        $teamCheckQuery = "SELECT id FROM teams WHERE id = ? AND user_id = ?";
        $teamCheck = $db->getThisQuery($teamCheckQuery, [$teamId, $userId]);
        
        if (empty($teamCheck)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Équipe invalide ou non autorisée'
            ]);
            exit;
        }
    }

    // Validate responsable_id belongs to the same user
    if ($responsableId !== null) {
        $responsableCheckQuery = "SELECT id FROM responsables WHERE id = ? AND user_id = ?";
        $responsableCheck = $db->getThisQuery($responsableCheckQuery, [$responsableId, $userId]);
        
        if (empty($responsableCheck)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Responsable invalide ou non autorisé'
            ]);
            exit;
        }
    }

    // Check if another agent with the same email already exists (excluding current agent)
    $duplicateQuery = "SELECT id FROM agents WHERE email = ? AND id != ?";
    $duplicateAgent = $db->getThisQuery($duplicateQuery, [$email, $id]);
    
    if (!empty($duplicateAgent)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Un autre agent avec cet email existe déjà'
        ]);
        exit;
    }
    
    // Prepare update parameters with ALL fields including team_id and responsable_id
    $updateParams = [
        "name" => $name,
        "email" => $email,
        "phone" => $phone,
        "region_id" => $region,
        "service_fee" => $serviceFee,
        "score" => $score,
        "is_active" => $isActive,
        "team_id" => $teamId,
        "responsable_id" => $responsableId,
        "updated_at" => date('Y-m-d H:i:s')
    ];

    // Handle password update if provided
    if (isset($_POST['password']) && !empty(trim($_POST['password']))) {
        $password = trim($_POST['password']);
        
        // Validate password length
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 6 caractères'
            ]);
            exit;
        }
        
        // Hash the new password
        $updateParams['password'] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    // Execute update query
    $result = $db->update("agents", $id, $updateParams);
    
    // Check if update was successful
    if ($result !== false) {
        // Get the updated agent data with team and responsable details
        $getUpdatedAgentQuery = "SELECT 
                                    a.id,
                                    a.name,
                                    a.email,
                                    a.phone,
                                    a.region_id,
                                    a.service_fee,
                                    a.score,
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
                                    t.name as team_name
                                  FROM agents a
                                  LEFT JOIN responsables r ON a.responsable_id = r.id
                                  LEFT JOIN teams t ON a.team_id = t.id
                                  WHERE a.id = ?";
        
        $updatedAgent = $db->getThisQuery($getUpdatedAgentQuery, [$id]);
        
        if (!empty($updatedAgent)) {
            $agentData = $updatedAgent[0];
            
            // Format response data with all fields including team and responsable
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
            
            echo json_encode([
                'success' => true,
                'data' => $responseData,
                'message' => 'Agent mis à jour avec succès'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Agent mis à jour avec succès'
            ]);
        }
    } else {
        // Log detailed error information
        error_log("Update failed for agent ID: $id");
        error_log("Update params: " . print_r($updateParams, true));
        
        throw new Exception('Échec de la mise à jour en base de données');
    }

} catch (PDOException $e) {
    // Handle database specific errors
    error_log("Database error in update_agentApi.php: " . $e->getMessage());
    error_log("PDO Error Code: " . $e->getCode());
    error_log("PDO Error Info: " . $e->getMessage());
    
    // Check for specific database errors
    if ($e->getCode() == '23000') {
        // Integrity constraint violation (duplicate entry, etc.)
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Conflit de données - vérifiez l\'unicité des informations'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de base de données: ' . $e->getMessage()
        ]);
    }
    
} catch (Exception $e) {
    // Handle general errors
    error_log("Error in update_agentApi.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur: ' . $e->getMessage()
    ]);
}
?>