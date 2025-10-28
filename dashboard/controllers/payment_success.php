<?php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// api/payment_success.php - Handle CMI payment success callback
header('Content-Type: application/json');

$db = DB::getInstance();

try {
    // Get parameters from either GET or POST
    $user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
    $transaction_id = $_GET['transaction_id'] ?? $_POST['transaction_id'] ?? null;
    
    // Validate required parameters
    $missing_params = [];
    if (!$user_id) $missing_params[] = 'user_id';
    if (!$transaction_id) $missing_params[] = 'transaction_id';
    
    if (!empty($missing_params)) {
        throw new Exception('Paramètres manquants: ' . implode(', ', $missing_params));
    }
    
    // Update payment status
    updatePaymentStatus($db, $transaction_id, 'completed');
    
    // Activate user
    activateUser($db, $user_id);
    
    // Redirect to success page or return JSON for AJAX
    if (isset($_GET['redirect'])) {
        header('Location: ./dashboard/?payment=success');
        exit;
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Paiement confirmé avec succès'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in payment_success.php: " . $e->getMessage());
    
    if (isset($_GET['redirect'])) {
        header('Location: /?payment=error');
        exit;
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function updatePaymentStatus($db, $transaction_id, $status) {
    // Find payment record by transaction ID
    $payment = $db->query("SELECT id FROM payments WHERE transaction_id = ?", [$transaction_id]);
    
    if (empty($payment)) {
        throw new Exception('Transaction non trouvée');
    }
    
    $payment_id = $payment[0]['id'];
    
    // Update payment status using DB class method
    $payment_data = [
        'status' => $status,
        'completed_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $result = $db->update('payments', $payment_id, $payment_data);
    
    if (!$result) {
        throw new Exception('Erreur lors de la mise à jour du statut de paiement');
    }
    
    return true;
}

function activateUser($db, $user_id) {
    // Activate user account using DB class method
    $user_update_data = [
        'is_active' => 1,
        'active' => 1,
        'status' => 'active',
        'derniere_connexion' => date('Y-m-d H:i:s'),
        'evaluation_end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $activate_result = $db->update('users', $user_id, $user_update_data);
    
    if (!$activate_result) {
        throw new Exception('Erreur lors de l\'activation du compte utilisateur');
    }
    
    // Update user plan status
    $user_plan = $db->query("SELECT id FROM user_plans WHERE user_id = ?", [$user_id]);
    
    if (!empty($user_plan)) {
        $plan_id = $user_plan[0]['id'];
        $plan_update_data = [
            'status' => 'active',
            'activated_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $plan_result = $db->update('user_plans', $plan_id, $plan_update_data);
        
        if (!$plan_result) {
            throw new Exception('Erreur lors de la mise à jour du plan utilisateur');
        }
    }
    
    // Send welcome email (optional)
    sendWelcomeEmail($user_id, $db);
    
    return true;
}

function sendWelcomeEmail($user_id, $db) {
    // Get user details using DB class method
    $user_result = $db->query("SELECT name, email FROM users WHERE id = ?", [$user_id]);
    
    if (!empty($user_result)) {
        $user = $user_result[0];
        
        $subject = "Bienvenue sur PLatForme !";
        $message = "Bonjour " . $user['name'] . ",\n\n";
        $message .= "Votre compte a été activé avec succès suite à votre paiement. Vous pouvez maintenant accéder à votre tableau de bord.\n\n";
        $message .= "Cordialement,\nL'équipe PLatForme";
        
        // Send email (implement your email sending logic here)
        // mail($user['email'], $subject, $message);
    }
}

?>