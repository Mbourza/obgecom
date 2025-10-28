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
    $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    // Handle service_fee (price field from form)
    $serviceFee = null;
    if (isset($_POST['price']) && $_POST['price'] !== '' && $_POST['price'] !== null) {
        $serviceFee = floatval($_POST['price']);
        
        // Validate service_fee is not negative
        if ($serviceFee < 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Le prix par confirmation ne peut pas être négatif'
            ]);
            exit;
        }
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

    // Check if agent exists
    $existingAgentQuery = "SELECT id FROM agents WHERE id = ?";
    $existingAgent = $db->getThisQuery($existingAgentQuery, [$id]);

    if (empty($existingAgent)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Agent non trouvé'
        ]);
        exit;
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
    
    // Prepare update parameters
    $updateParams = [
        "name" => $name,
        "email" => $email,
        "phone" => $phone,
        "service_fee" => $serviceFee,
        "is_active" => $isActive
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
        // Get the updated agent data to return
        $getUpdatedAgentQuery = "SELECT 
                                    id,
                                    name,
                                    email,
                                    phone,
                                    service_fee,
                                    is_active,
                                    created_at,
                                    updated_at
                                  FROM agents 
                                  WHERE id = ?";
        
        $updatedAgent = $db->getThisQuery($getUpdatedAgentQuery, [$id]);
        
        if (!empty($updatedAgent)) {
            $agentData = $updatedAgent[0];
            
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
        throw new Exception('Échec de la mise à jour en base de données');
    }

} catch (PDOException $e) {
    // Handle database specific errors
    error_log("Database error in update_agentApi.php: " . $e->getMessage());
    
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
            'message' => 'Erreur de base de données'
        ]);
    }
    
} catch (Exception $e) {
    // Handle general errors
    error_log("Error in update_agentApi.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur'
    ]);
}
?>