<?php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// api/cleanup_failed_registration.php - Clean up failed registrations
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$db = DB::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

try {
    // Validate required fields
    if (empty($data['user_id'])) {
        throw new Exception('user_id est requis');
    }
    
    $user_id = $data['user_id'];
    
    // Verify user exists and has pending_payment status
    $user = $db->query("SELECT id, status FROM users WHERE id = ?", [$user_id]);
    
    if (empty($user)) {
        throw new Exception('Utilisateur non trouvé');
    }
    
    if ($user[0]['status'] !== 'pending_payment') {
        throw new Exception('Impossible de supprimer cet utilisateur - statut invalide');
    }
    
    // Begin transaction for cleanup
    $db->beginTransaction();
    
    try {
        // Get user_plans records to delete
        $user_plans = $db->query("SELECT id FROM user_plans WHERE user_id = ?", [$user_id]);
        
        // Delete from user_plans using DB class method
        foreach ($user_plans as $plan) {

            $delete_plan_result = $db->delete('user_plans', $plan['id']);
            
            if (!$delete_plan_result) {
                throw new Exception('Erreur lors de la suppression des plans utilisateur');
            }
        }
        
        // Get payment_attempts records to delete
        $payment_attempts = $db->query("SELECT id FROM payment_attempts WHERE user_id = ?", [$user_id]);
        
        // Delete from payment_attempts using DB class method
        foreach ($payment_attempts as $payment) {
            $delete_payment_result = $db->delete('payment_attempts', $payment['id']);
            if (!$delete_payment_result) {
                throw new Exception('Erreur lors de la suppression des tentatives de paiement');
            }
        }
        
        // Delete user using DB class method
        $delete_user_result = $db->delete('users', $user_id);
        
        if (!$delete_user_result) {
            throw new Exception('Erreur lors de la suppression de l\'utilisateur');
        }
        
        // Commit transaction
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Nettoyage effectué avec succès'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Error in cleanup_failed_registration.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

?>