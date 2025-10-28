<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

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

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required parameter
    if (!isset($input['id']) || empty($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'L\'ID de l\'agent est requis'
        ]);
        exit;
    }

    // Sanitize and validate input data
    $agentId = $input['id'];

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

    // Check if agent exists
    $checkQuery = "SELECT id, name FROM agents WHERE id = ?";
    $agentResult = $db->getThisQuery($checkQuery, [$agentId]);
    
    if (empty($agentResult)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Agent non trouvé'
        ]);
        exit;
    }

    $agentName = $agentResult[0]['name'];

    // Check if agent has associated confirmations
    $confirmationsQuery = "SELECT COUNT(*) as confirmation_count FROM agent_confirmations WHERE agent_id = ?";
    $confirmationsResult = $db->getThisQuery($confirmationsQuery, [$agentId]);
    $confirmationCount = $confirmationsResult[0]['confirmation_count'] ?? 0;

    // Begin transaction for data integrity
    $db->query("START TRANSACTION");

    try {
        // If agent has confirmations, you might want to:
        // Option 1: Prevent deletion if agent has confirmations
        // Option 2: Delete confirmations as well (cascade delete)
        // Option 3: Set agent_id to NULL in confirmations (soft reference)
        
        // For this example, I'll use Option 3 (soft reference) - safer approach
        if ($confirmationCount > 0) {
            $updateConfirmationsQuery = "UPDATE agent_confirmations SET agent_id = NULL WHERE agent_id = ?";
            $updateResult = $db->query($updateConfirmationsQuery, [$agentId]);
            
            if (!$updateResult) {
                throw new Exception("Erreur lors de la mise à jour des confirmations associées");
            }
        }

        // Delete the agent
        $deleteQuery = "DELETE FROM agents WHERE id = ?";
        $deleteResult = $db->query($deleteQuery, [$agentId]);

        if (!$deleteResult) {
            throw new Exception("Erreur lors de la suppression de l'agent");
        }

        // Check if any rows were affected

        // Commit transaction
        $db->query("COMMIT");

        // Log the deletion for audit purposes
        error_log("Agent deleted - ID: $agentId, Name: $agentName, Confirmations updated: $confirmationCount");

        echo json_encode([
            'success' => true,
            'message' => "Agent '$agentName' supprimé avec succès",
            'deleted_agent' => [
                'id' => $agentId,
                'name' => $agentName,
                'confirmations_updated' => intval($confirmationCount)
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->query("ROLLBACK");
        throw $e;
    }

} catch (PDOException $e) {
    // Handle database specific errors
    error_log("Database error in delete_agentApi.php: " . $e->getMessage());
    
    // Rollback if transaction is still active
    try {
        $db->query("ROLLBACK");
    } catch (Exception $rollbackError) {
        // Ignore rollback errors if transaction was already rolled back
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données'
    ]);
    
} catch (Exception $e) {
    // Handle general errors
    error_log("Error in delete_agentApi.php: " . $e->getMessage());
    
    // Rollback if transaction is still active
    try {
        $db->query("ROLLBACK");
    } catch (Exception $rollbackError) {
        // Ignore rollback errors if transaction was already rolled back
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>