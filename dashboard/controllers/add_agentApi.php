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

    $user_id = getCurrentUserId($db);

    if (!$user_id) {
        throw new Exception('Utilisateur non authentifié');
    }

    // Vérification de l'abonnement
    $subscription = new Subscription($user_id);
    $subscriptionStatus = $subscription->getSubscriptionStatus();
    $subscriptionAlerts = $subscription->getSubscriptionAlerts();

    // Vérifier l'accès aux fonctionnalités
    $canAccessFeatures = $subscription->canAccessFeatures();

    // Si l'abonnement est expiré ou inexistant, limiter l'accès
    if (!$canAccessFeatures) {
        throw new Exception('Votre abonnement a expiré ou n\'est pas valide. Veuillez renouveler votre abonnement pour continuer à ajouter des agents.');
    }

    // Get subscription plan details and agent limits
    $planDetails = getSubscriptionPlanDetails($db, $user_id);

    // Check agent limits
    $maxAgents = isset($planDetails['limits']['team_members']) 
        ? (int)$planDetails['limits']['team_members'] 
        : PHP_INT_MAX; // if unlimited

    // Get current agent count for this user
    $currentAgentCount = getCurrentAgentCount($db, $user_id);

    if ($currentAgentCount >= $maxAgents) {
        throw new Exception(
            "Vous avez atteint votre limite de {$maxAgents} agents pour votre plan actuel. 
            Mettez à niveau votre plan pour ajouter plus d'agents."
        );
    }

    // Validate required fields
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

    if (!isset($_POST['password']) || empty(trim($_POST['password']))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le mot de passe est requis'
        ]);
        exit;
    }

    // Validate password length
    if (strlen(trim($_POST['password'])) < 6) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le mot de passe doit contenir au moins 6 caractères'
        ]);
        exit;
    }

    // Sanitize and validate input data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
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
        $isActive = 1;
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if agent with the same email already exists
    $duplicateQuery = "SELECT id FROM agents WHERE email = ?";
    $duplicateAgent = $db->getThisQuery($duplicateQuery, [$email]);
    
    if (!empty($duplicateAgent)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Un agent avec cet email existe déjà'
        ]);
        exit;
    }

    // Prepare insert query
    $insertQuery = "INSERT INTO agents ( 
                        user_id,
                        name,
                        email,
                        password,
                        phone,
                        service_fee,
                        is_active,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $insertParams = [
        $user_id,
        $name,
        $email,
        $hashed_password,
        $phone,
        $serviceFee,
        $isActive
    ];
    
    // Execute insert query
    $result = $db->getThisQuery($insertQuery, $insertParams);
    
    // Check if insert was successful
    if ($result !== false) {
        // Get the ID of the newly inserted agent
        $newAgentId = $db->getLastInsertId();
        
        if ($newAgentId) {
            // Get the newly created agent data to return (without password)
            $getNewAgentQuery = "SELECT 
                                    id,
                                    user_id,
                                    name,
                                    email,
                                    phone,
                                    service_fee,
                                    is_active,
                                    created_at,
                                    updated_at
                                  FROM agents 
                                  WHERE id = ?";
            
            $newAgent = $db->getThisQuery($getNewAgentQuery, [$newAgentId]);
            
            if (!empty($newAgent)) {
                $agentData = $newAgent[0];
                
                // Calculate remaining agent slots
                $remainingAgents = $maxAgents - ($currentAgentCount + 1);
                
                // Format response data
                $responseData = [
                    'id' => $agentData['id'],
                    'user_id' => $agentData['user_id'],
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
                    'message' => 'Agent ajouté avec succès',
                    'plan_info' => [
                        'max_agents' => $maxAgents,
                        'current_agents' => $currentAgentCount + 1,
                        'remaining_agents' => $remainingAgents
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Agent ajouté avec succès',
                    'agent_id' => $newAgentId
                ]);
            }
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Agent ajouté avec succès'
            ]);
        }
    } else {
        throw new Exception('Échec de l\'insertion en base de données');
    }

} catch (PDOException $e) {
    // Handle database specific errors
    error_log("Database error in add_agentApi.php: " . $e->getMessage());
    
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
    error_log("Error in add_agentApi.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get subscription plan details for a user
 */
function getSubscriptionPlanDetails($db, $user_id) {
    $query = "
        SELECT 
            p.id AS plan_id,
            p.name AS plan_name,
            p.price AS plan_price,
            p.is_custom,
            up.status,
            up.activated_at AS subscription_start,
            up.expires_at,
            up.duration_months,
            up.monthly_price,
            up.total_amount,
            pl.limit_key,
            pl.limit_value
        FROM user_plans up
        JOIN plans p ON up.plan_id = p.id
        LEFT JOIN plan_limits pl ON p.id = pl.plan_id
        WHERE up.user_id = ?
        AND up.status = 'active'
        AND (up.expires_at IS NULL OR up.expires_at > NOW())
        ORDER BY up.activated_at DESC
        LIMIT 1
    ";

    $result = $db->getThisQuery($query, [$user_id]);

    if (empty($result)) {
        return null;
    }

    // Structure the plan details
    $planDetails = [
        'plan_id'            => $result[0]['plan_id'],
        'plan_name'          => $result[0]['plan_name'],
        'plan_price'         => $result[0]['plan_price'],
        'is_custom'          => (bool)$result[0]['is_custom'],
        'status'             => $result[0]['status'],
        'subscription_start' => $result[0]['subscription_start'],
        'expires_at'         => $result[0]['expires_at'],
        'duration_months'    => $result[0]['duration_months'],
        'monthly_price'      => $result[0]['monthly_price'],
        'total_amount'       => $result[0]['total_amount'],
        'limits'             => []
    ];

    // Collect all limits for the plan
    foreach ($result as $row) {
        if (!empty($row['limit_key'])) {
            $planDetails['limits'][$row['limit_key']] = $row['limit_value'];
        }
    }

    return $planDetails;
}

/**
 * Get current agent count for a user
 */
function getCurrentAgentCount($db, $user_id) {
    $query = "SELECT COUNT(*) as agent_count FROM agents WHERE user_id = ? AND is_active = 1";
    $result = $db->getThisQuery($query, [$user_id]);
    
    if (empty($result)) {
        return 0;
    }
    
    return (int)$result[0]['agent_count'];
}

/**
 * Get current user ID from session
*/

function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    // This can be username, email, or phone
    $identifier = $_SESSION['user']['username'];

    // Prefer explicit ID if available
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Try to match identifier against username, email, or phone
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

    return null;
} 

?>