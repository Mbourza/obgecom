<?php

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

// api/activate_user.php - Activate user account
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$db = DB::getInstance();

$data = json_decode(file_get_contents('php://input'), true);

try {
    // Validate required fields
    $required_fields = ['user_id', 'plan'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception("Champs requis manquants: " . implode(', ', $missing_fields));
    }
    
    $user_id = $data['user_id'];
    $plan = $data['plan'];
    
    // Activate user account using DB class method
    $user_update_data = [
        'is_active' => 1,
        'active' => 1,
        'status' => 'active',
        'derniere_connexion' => date('Y-m-d H:i:s'),
        'evaluation_end_date' => date('Y-m-d H:i:s', strtotime('+30 days'))
    ];
    
    $activate_result = $db->update('users', $user_id, $user_update_data);
    
    if (!$activate_result) {
        throw new Exception('Erreur lors de l\'activation du compte utilisateur');
    }
    
    // Update plan status using DB class method
    $plan_update_data = [
        'status' => 'active',
        'activated_at' => date('Y-m-d H:i:s')
    ];
    
    // Get the user_plan record to update
    $user_plan = $db->query("SELECT id FROM user_plans WHERE user_id = ?", [$user_id]);
    
    if (!empty($user_plan)) {
        $plan_id = $user_plan[0]['id'];
        $plan_result = $db->update('user_plans', $plan_id, $plan_update_data);
        
        if (!$plan_result) {
            throw new Exception('Erreur lors de la mise à jour du plan utilisateur');
        }
    }
    
    // Send welcome email (optional)
    sendWelcomeEmail($user_id, $db);
    
    echo json_encode([
        'success' => true,
        'message' => 'Compte activé avec succès'
    ]);
    
} catch (Exception $e) {
    error_log("Error in activate_user.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function sendWelcomeEmail($user_id, $db) {
    // Get user details using DB class method
    $user_result = $db->query("SELECT name, email FROM users WHERE id = ?", [$user_id]);
    
    if (!empty($user_result)) {
        $user = $user_result[0];
        
        $subject = "Bienvenue sur PLatForme !";
        $message = "Bonjour " . $user['name'] . ",\n\n";
        $message .= "Votre compte a été créé avec succès. Vous pouvez maintenant accéder à votre tableau de bord.\n\n";
        $message .= "Cordialement,\nL'équipe PLatForme";
        
        // Send email (implement your email sending logic here)
        // mail($user['email'], $subject, $message);
    }
}

?>