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

    // Get logged in user id
    $user_id = getCurrentUserId($db);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non authentifié'
        ]);
        exit;
    }

    // Vérification de l'abonnement
    $subscription = new Subscription($user_id);
    $subscriptionStatus = $subscription->getSubscriptionStatus();
    $subscriptionAlerts = $subscription->getSubscriptionAlerts();

    // Vérifier l'accès aux fonctionnalités
    $canAccessFeatures = $subscription->canAccessFeatures();

    // Si l'abonnement est expiré ou inexistant, limiter l'accès
    if (!$canAccessFeatures) {
        throw new Exception('Votre abonnement a expiré ou n\'est pas valide. Veuillez renouveler votre abonnement pour continuer à ajouter des transporteurs.');
    }

    // Get subscription plan details and carrier limits
    $planDetails = getSubscriptionPlanDetails($db, $user_id);

    // Check carrier limits
    $maxCarriers = isset($planDetails['limits']['carriers']) 
        ? (int)$planDetails['limits']['carriers'] 
        : PHP_INT_MAX; // if unlimited

    // Get current carrier count for this user
    $currentCarrierCount = getCurrentCarrierCount($db, $user_id);

    if ($currentCarrierCount >= $maxCarriers) {
        throw new Exception(
            "Vous avez atteint votre limite de {$maxCarriers} transporteurs pour votre plan actuel. 
            Mettez à niveau votre plan pour ajouter plus de transporteurs."
        );
    }

    // Validate required fields
    $name = null;

    // If "custom" carrier, require custom_name
    if (isset($_POST['carrier_type']) && $_POST['carrier_type'] === 'custom') {
        if (!isset($_POST['custom_name']) || empty(trim($_POST['custom_name']))) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Le nom personnalisé du transporteur est requis'
            ]);
            exit;
        }
        $name = trim($_POST['custom_name']);
    } else {
        // Use display_name if provided, otherwise fallback to the carrier_type
        $name = !empty($_POST['display_name']) ? trim($_POST['display_name']) : trim($_POST['carrier_type']);
    }
    
    // Final check
    if (empty($name)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le nom du transporteur est requis'
        ]);
        exit;
    }

   // Sanitize and validate input data
    $carrierType = isset($_POST['carrier_type']) ? trim($_POST['carrier_type']) : '';
    $customName  = isset($_POST['custom_name']) ? trim($_POST['custom_name']) : '';
    $displayName = isset($_POST['display_name']) ? trim($_POST['display_name']) : '';
    $api_url      = isset($_POST['api_url']) ? trim($_POST['api_url']) : '';
    $apiKey      = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
    $apiSecret   = isset($_POST['api_secret']) ? trim($_POST['api_secret']) : '';
    $trackingUrl = isset($_POST['tracking_url']) ? trim($_POST['tracking_url']) : '';
    $isActive    = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

    // Determine carrier name (use custom if chosen)
    if ($carrierType === 'custom') {
        $name = $customName;
    } else {
        $name = $displayName ?: $carrierType;
    }

    if (empty($api_url)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "L'URL de l'API est requise"
        ]);
        exit;
    }

    // Validate name length
    if (strlen($name) > 255) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Le nom du transporteur est trop long (255 caractères maximum)'
        ]);
        exit;
    }

    // Validate URLs if provided
    if (!empty($trackingUrl) && !filter_var($trackingUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'URL de suivi invalide'
        ]);
        exit;
    }

    if (!empty($api_url) && !filter_var($api_url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'URL API invalide'
        ]);
        exit;
    }

    // Validate is_active value
    if (!in_array($isActive, [0, 1])) {
        $isActive = 1; // Default to active
    }

    // Check if carrier with the same name already exists for this user
    $duplicateQuery = "SELECT id FROM shipping_companies WHERE name = ? AND api_url= ? AND user_id = ?";
    $duplicateCarrier = $db->getThisQuery($duplicateQuery, [$name, $api_url, $user_id]);
    
    if (!empty($duplicateCarrier)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Une société de livraison avec ces mêmes informations existe déjà'
        ]);
        exit;
    }

    // Prepare insert query (with user_id)
    $insertQuery = "INSERT INTO shipping_companies (
                        user_id,
                        name,
                        api_url,
                        api_key,
                        api_secret,
                        tracking_url,
                        is_active,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $insertParams = [
        $user_id,
        $name,
        $api_url,
        $apiKey,
        $apiSecret,
        $trackingUrl,
        $isActive
    ];
    
    // Execute insert query
    $result = $db->insert_this($insertQuery, $insertParams);
    
    if ($result !== false) {
        $newCarrierId = $db->getLastInsertId();
        
        if ($newCarrierId) {
            // Fetch the newly created carrier (scoped by user_id)
            $getNewCarrierQuery = "SELECT 
                                    id,
                                    name,
                                    api_url,
                                    api_key,
                                    api_secret,
                                    supports_tracking,
                                    tracking_url,
                                    logo_url,
                                    contact_email,
                                    phone,
                                    website,
                                    is_active,
                                    created_at,
                                    updated_at
                                  FROM shipping_companies 
                                  WHERE id = ? AND user_id = ?";
            
            $newCarrier = $db->getThisQuery($getNewCarrierQuery, [$newCarrierId, $user_id]);
            
            if (!empty($newCarrier)) {
                $carrierData = $newCarrier[0];
                
                // Calculate remaining carrier slots
                $remainingCarriers = $maxCarriers - ($currentCarrierCount + 1);
                
                echo json_encode([
                    'success' => true,
                    'data' => $carrierData,
                    'message' => 'Transporteur ajouté avec succès',
                    'plan_info' => [
                        'max_carriers' => $maxCarriers,
                        'current_carriers' => $currentCarrierCount + 1,
                        'remaining_carriers' => $remainingCarriers
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Transporteur ajouté avec succès',
                    'carrier_id' => $newCarrierId
                ]);
            }
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Transporteur ajouté avec succès'
            ]);
        }
    } else {
        throw new Exception('Échec de l\'insertion en base de données');
    }

} catch (PDOException $e) {
    error_log("Database error in add_carrier_api.php: " . $e->getMessage());
    
    if ($e->getCode() == '23000') {
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
    error_log("Error in add_carrier_api.php: " . $e->getMessage());
    
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
 * Get current carrier count for a user
 */
function getCurrentCarrierCount($db, $user_id) {
    $query = "SELECT COUNT(*) as carrier_count FROM shipping_companies WHERE user_id = ? AND is_active = 1";
    $result = $db->getThisQuery($query, [$user_id]);
    
    if (empty($result)) {
        return 0;
    }
    
    return (int)$result[0]['carrier_count'];
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
} ?>