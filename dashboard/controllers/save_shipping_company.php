<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
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

    // Check if this is an update (has company-id) or new creation
    $company_id = isset($_POST['company-id']) ? intval($_POST['company-id']) : null;
    $is_update = !empty($company_id);

    // Only check subscription limits for new companies, not updates
    if (!$is_update) {
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
    }

    // Validate required fields (updated field names)
    $required_fields = ['name', 'api_url'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
        ]);
        exit;
    }

    // Validate URL format
    if (!filter_var($_POST['api_url'], FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid API URL format']);
        exit;
    }

    // Sanitize and prepare data (updated field names)
    $company_name = trim($_POST['name']);
    $company_slug = isset($_POST['slug']) ? trim($_POST['slug']) : strtolower(str_replace(' ', '-', $company_name));
    $display_name = isset($_POST['display_name']) ? trim($_POST['display_name']) : $company_name;
    $api_url = trim($_POST['api_url']);
    $api_key = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
    $api_secret = isset($_POST['api_secret']) ? trim($_POST['api_secret']) : '';
    
    // Handle is_active and supports_tracking (could be boolean or 'on')
    $is_active = 1; // Default to active
    if (isset($_POST['is_active'])) {
        $is_active = ($_POST['is_active'] === 'on' || $_POST['is_active'] === '1' || $_POST['is_active'] === true) ? 1 : 0;
    }
    
    $supports_tracking = 0; // Default to not supporting tracking
    if (isset($_POST['supports_tracking'])) {
        $supports_tracking = ($_POST['supports_tracking'] === 'on' || $_POST['supports_tracking'] === '1' || $_POST['supports_tracking'] === true) ? 1 : 0;
    }

    if ($is_update) {
        // Verify the company belongs to the current user before updating
        $existing_company = $db->getThisQuery(
            "SELECT id FROM shipping_companies WHERE id = ? AND user_id = ?", 
            [$company_id, $user_id]
        );
        
        if (empty($existing_company)) {
            echo json_encode([
                'success' => false,
                'message' => 'Transporteur non trouvé ou accès non autorisé'
            ]);
            exit;
        }

        // Update existing company in shipping_companies table
        $company_data = [
            'name' => $company_name,
            'api_url' => $api_url,
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'supports_tracking' => $supports_tracking,
            'is_active' => $is_active,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $company_result = $db->update('shipping_companies', $company_id, $company_data);

        if ($company_result) {
            echo json_encode([
                'success' => true,
                'message' => 'Transporteur mis à jour avec succès',
                'action' => 'updated',
                'id' => $company_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Échec de la mise à jour du transporteur'
            ]);
        }
            
    } else {
        // Check for duplicate company for this user
        $duplicate_check = $db->getThisQuery(
            "SELECT id FROM shipping_companies WHERE (name = ? OR slug = ?) AND user_id = ?",
            [$company_name, $company_slug, $user_id]
        );
        
        if (!empty($duplicate_check)) {
            echo json_encode([
                'success' => false,
                'message' => 'Un transporteur avec ce nom existe déjà'
            ]);
            exit;
        }

        // Insert new company (with user_id)
        $company_data = [
            'user_id' => $user_id,
            'name' => $company_name,
            'api_url' => $api_url,
            'api_key' => $api_key,
            'api_secret' => $api_secret,
            'supports_tracking' => $supports_tracking,
            'is_active' => $is_active,
            'created_at' => date('Y-m-d H:i:s')
        ];
                
        $company_result = $db->insert('shipping_companies', $company_data);
                
        if ($company_result) {
            $new_company_id = $db->getLastInsertId();
            
            // Calculate remaining carrier slots for response
            $remainingCarriers = $maxCarriers - ($currentCarrierCount + 1);
                        
            echo json_encode([
                'success' => true,
                'message' => 'Transporteur ajouté avec succès',
                'action' => 'created',
                'id' => $new_company_id,
                'plan_info' => [
                    'max_carriers' => $maxCarriers,
                    'current_carriers' => $currentCarrierCount + 1,
                    'remaining_carriers' => $remainingCarriers
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Échec de la création du transporteur'
            ]);
        }
    }

} catch (Exception $e) {
    error_log("Error in save_shipping_company.php: " . $e->getMessage());
        
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
}
?>