<?php
// update_responsableApi.php
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

    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['username'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Non authentifié'
        ]);
        exit;
    }

    // Validate required fields
    if (empty($_POST['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du responsable requis'
        ]);
        exit;
    }

    // Get current user ID
    $currentUser = $db->getThisQuery(
        "SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1",
        [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]
    );

    if (!$currentUser || !isset($currentUser[0]['id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non trouvé'
        ]);
        exit;
    }

    $current_user_id = (int)$currentUser[0]['id'];
    $responsable_id = intval($_POST['id']);

    // Validate ID
    if ($responsable_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du responsable invalide'
        ]);
        exit;
    }

    // Check if responsable exists and belongs to current user
    $checkQuery = "SELECT id, user_id, name, email FROM responsables WHERE id = ?";
    $existingResponsable = $db->getThisQuery($checkQuery, [$responsable_id]);

    if (empty($existingResponsable)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Responsable non trouvé'
        ]);
        exit;
    }

    $current_responsable = $existingResponsable[0];

    // Check permissions - user can only update their own responsables
    if ($current_responsable['user_id'] !== $current_user_id) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Accès non autorisé'
        ]);
        exit;
    }

    // Get and validate form data
    $name = isset($_POST['name']) ? trim($_POST['name']) : $current_responsable['name'];
    $email = isset($_POST['email']) ? trim($_POST['email']) : $current_responsable['email'];
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validate required fields
    if (empty($name)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le nom est requis'
        ]);
        exit;
    }

    if (empty($email)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'L\'email est requis'
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

    // Validate phone format (Moroccan format)
    if (!empty($phone) && !preg_match('/^(06|07|05)\d{8}$/', $phone)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Format de téléphone invalide. Utilisez 06, 07 ou 05 suivi de 8 chiffres'
        ]);
        exit;
    }

    // Validate is_active
    if (!in_array($is_active, [0, 1])) {
        $is_active = 1;
    }

    // Check if email is already used by another responsable (except current)
    $emailCheckQuery = "SELECT id FROM responsables WHERE email = ? AND id != ? AND user_id = ?";
    $emailCheck = $db->getThisQuery($emailCheckQuery, [$email, $responsable_id, $current_user_id]);
    
    if (!empty($emailCheck)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cet email est déjà utilisé par un autre responsable'
        ]);
        exit;
    }

    // Check if phone is already used by another responsable (except current)
    if (!empty($phone)) {
        $phoneCheckQuery = "SELECT id FROM responsables WHERE phone = ? AND id != ? AND user_id = ?";
        $phoneCheck = $db->getThisQuery($phoneCheckQuery, [$phone, $responsable_id, $current_user_id]);
        
        if (!empty($phoneCheck)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ce numéro de téléphone est déjà utilisé par un autre responsable'
            ]);
            exit;
        }
    }

    // Update password if provided
    $updatePassword = !empty($password);
    if ($updatePassword) {
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 6 caractères'
            ]);
            exit;
        }
    }

    // Handle team assignment if provided
    $team_id = isset($_POST['team_id']) ? intval($_POST['team_id']) : null;
    if ($team_id !== null && $team_id > 0) {
        $teamCheck = $db->getThisQuery(
            "SELECT id, responsable_id FROM teams WHERE id = ? AND user_id = ?",
            [$team_id, $current_user_id]
        );
        
        if (empty($teamCheck)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Équipe non trouvée ou non autorisée'
            ]);
            exit;
        }
        
        $team = $teamCheck[0];
        
        // Check if this team is already assigned to another responsable
        if ($team['responsable_id'] && $team['responsable_id'] != $responsable_id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Cette équipe est déjà assignée à un autre responsable'
            ]);
            exit;
        }
    }

    // Begin transaction
    $db->query("START TRANSACTION");

    try {
        // Update responsable information
        $updateFields = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'is_active' => $is_active,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Update password if provided
        if ($updatePassword) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateFields['password'] = $hashedPassword;
        }
        
        // Build update query dynamically
        $setParts = [];
        $params = [];
        
        foreach ($updateFields as $field => $value) {
            $setParts[] = "{$field} = ?";
            $params[] = $value;
        }
        
        $params[] = $responsable_id;
        
        // Build the final query
        $updateQuery = "UPDATE responsables SET " . implode(', ', $setParts) . " WHERE id = ?";
        
        // Execute update
        $updateResult = $db->getThisQuery($updateQuery, $params);
        
        if ($updateResult === false) {
            throw new Exception('Échec de la mise à jour du responsable');
        }
        
        // Handle team assignment
        if ($team_id !== null) {
            // First, remove this responsable from any other teams
            $clearOldTeamsQuery = "UPDATE teams SET responsable_id = NULL WHERE responsable_id = ? AND user_id = ?";
            $db->getThisQuery($clearOldTeamsQuery, [$responsable_id, $current_user_id]);
            
            // If team_id is 0 or empty, we just removed the assignment
            if ($team_id > 0) {
                // Assign this responsable to the new team
                $assignTeamQuery = "UPDATE teams SET responsable_id = ? WHERE id = ? AND user_id = ?";
                $assignResult = $db->getThisQuery($assignTeamQuery, [$responsable_id, $team_id, $current_user_id]);
                
                if ($assignResult === false) {
                    throw new Exception('Échec de l\'assignation de l\'équipe');
                }
            }
        }
        
        // Commit transaction
        $db->query("COMMIT");
        
        // Fetch updated responsable data with team information
        $getUpdatedQuery = "
            SELECT 
                r.id,
                r.name,
                r.email,
                r.phone,
                r.is_active,
                r.user_id,
                r.created_at,
                r.updated_at,
                t.id as team_id,
                t.name as team_name,
                t.description as team_description,
                u.name as created_by_name,
                u.email as created_by_email,
                (SELECT COUNT(*) FROM agents a WHERE a.responsable_id = r.id AND a.is_active = 1) as active_agents_count,
                (SELECT COUNT(*) FROM agents a WHERE a.responsable_id = r.id) as total_agents_count,
                (SELECT COUNT(*) FROM teams t2 WHERE t2.responsable_id = r.id AND t2.user_id = r.user_id) as teams_managed_count
            FROM responsables r
            LEFT JOIN teams t ON t.responsable_id = r.id
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
            GROUP BY r.id
        ";
        
        $updatedResponsable = $db->getThisQuery($getUpdatedQuery, [$responsable_id]);
        
        if (!empty($updatedResponsable)) {
            // Format the response
            $responseData = $updatedResponsable[0];
            $responseData['is_active'] = (bool)$responseData['is_active'];
            $responseData['team_id'] = $responseData['team_id'] ? (int)$responseData['team_id'] : null;
            $responseData['teams_managed_count'] = (int)$responseData['teams_managed_count'];
            
            echo json_encode([
                'success' => true,
                'data' => $responseData,
                'message' => 'Responsable mis à jour avec succès'
            ]);
        } else {
            // Fallback response
            echo json_encode([
                'success' => true,
                'message' => 'Responsable mis à jour avec succès',
                'data' => [
                    'id' => $responsable_id,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'is_active' => (bool)$is_active
                ]
            ]);
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->query("ROLLBACK");
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Database error in update_responsableApi.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données',
        'error' => $e->getMessage()
    ]);

} catch (Exception $e) {
    error_log("Error in update_responsableApi.php: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>