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
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['username'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }

    // Validation des champs requis
    if (empty($_POST['responsable_id']) || empty($_POST['agent_ids'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        exit;
    }

    $responsable_id = intval($_POST['responsable_id']);
    $agent_ids = json_decode($_POST['agent_ids'], true);
    $remove_other_assignments = isset($_POST['remove_other_assignments']) && $_POST['remove_other_assignments'] === '1';

    // Valider les IDs
    if ($responsable_id <= 0 || !is_array($agent_ids) || empty($agent_ids)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        exit;
    }

    // Récupérer l'ID de l'utilisateur courant
    $currentUser = $db->getThisQuery(
        "SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1",
        [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]
    );

    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        exit;
    }

    $current_user_id = (int)$currentUser[0]['id'];

    // Vérifier que le responsable appartient à l'utilisateur
    $responsable_check = $db->getThisQuery(
        "SELECT id FROM responsables WHERE id = ? AND user_id = ?",
        [$responsable_id, $current_user_id]
    );

    if (empty($responsable_check)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Responsable non trouvé ou non autorisé']);
        exit;
    }

    // Vérifier que tous les agents appartiennent à l'utilisateur
    $placeholders = implode(',', array_fill(0, count($agent_ids), '?'));
    $agent_check_query = "SELECT COUNT(*) as count FROM agents WHERE id IN ($placeholders) AND user_id = ?";
    $agent_check_params = array_merge($agent_ids, [$current_user_id]);
    
    $agent_check = $db->getThisQuery($agent_check_query, $agent_check_params);
    
    if (empty($agent_check) || $agent_check[0]['count'] != count($agent_ids)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Certains agents ne sont pas autorisés']);
        exit;
    }

    // Démarrer la transaction
    $db->query("START TRANSACTION");

    try {
        $assigned_count = 0;
        
        // Si on doit retirer les autres assignations
        if ($remove_other_assignments) {
            // Retirer tous les agents de leurs responsables actuels
            $clear_query = "UPDATE agents SET responsable_id = NULL WHERE id IN ($placeholders)";
            $db->getThisQuery($clear_query, $agent_ids);
        }

        // Assigner les agents au nouveau responsable
        foreach ($agent_ids as $agent_id) {
            $update_query = "UPDATE agents SET responsable_id = ? WHERE id = ?";
            $result = $db->getThisQuery($update_query, [$responsable_id, $agent_id]);
            
            if ($result !== false) {
                $assigned_count++;
            }
        }

        // Valider la transaction
        $db->query("COMMIT");

        // Récupérer le nom du responsable pour la réponse
        $responsable_info = $db->getThisQuery(
            "SELECT name FROM responsables WHERE id = ?",
            [$responsable_id]
        );

        $responsable_name = $responsable_info[0]['name'] ?? '';

        echo json_encode([
            'success' => true,
            'assigned_count' => $assigned_count,
            'responsable_name' => $responsable_name,
            'message' => "$assigned_count agent(s) assigné(s) avec succès"
        ]);

    } catch (Exception $e) {
        // Annuler la transaction en cas d'erreur
        $db->query("ROLLBACK");
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in assign_agents_to_responsableApi.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'assignation',
        'error' => $e->getMessage()
    ]);
}
?>