<?php
if (file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

$db = DB::getInstance();

// ---------------------------------------------------------------------------
// Encryption helpers for auth passwords
// ---------------------------------------------------------------------------
// Define APP_KEY (32 bytes) in your core/init.php or env loader.
if (!defined('APP_KEY')) {
    define('APP_KEY', hash('sha256', 'CHANGE_ME_DEV_ONLY_KEY', true));
}

function encryptAuthPassword(string $plain): string {
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', APP_KEY, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new Exception('Erreur de chiffrement du mot de passe');
    }
    return base64_encode($iv . $cipher); // iv || cipher
}

function decryptAuthPassword(string $encoded): string {
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 17) {
        throw new Exception('Données chiffrées invalides');
    }
    $iv     = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain  = openssl_decrypt($cipher, 'AES-256-CBC', APP_KEY, OPENSSL_RAW_DATA, $iv);
    if ($plain === false) {
        throw new Exception('Erreur de déchiffrement');
    }
    return $plain;
}

// ---------------------------------------------------------------------------
// Method check
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    // -----------------------------------------------------------------------
    // Auth / user
    // -----------------------------------------------------------------------
    $user_id = getCurrentUserId($db);
    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Utilisateur non authentifié']);
        exit;
    }

    // -----------------------------------------------------------------------
    // Create vs Update
    // -----------------------------------------------------------------------
    $company_id = isset($_POST['company-id']) ? intval($_POST['company-id']) : null;
    $is_update  = !empty($company_id);

    // -----------------------------------------------------------------------
    // Subscription / carrier limit checks (only for NEW companies)
    // -----------------------------------------------------------------------
    $maxCarriers = PHP_INT_MAX;
    $currentCarrierCount = 0;

    if (!$is_update) {
        $subscription = new Subscription($user_id);
        if (!$subscription->canAccessFeatures()) {
            throw new Exception(
                'Votre abonnement a expiré ou n\'est pas valide. Veuillez renouveler votre abonnement pour continuer à ajouter des transporteurs.'
            );
        }

        $planDetails = getSubscriptionPlanDetails($db, $user_id);
        $maxCarriers = isset($planDetails['limits']['carriers'])
            ? (int)$planDetails['limits']['carriers']
            : PHP_INT_MAX;

        $currentCarrierCount = getCurrentCarrierCount($db, $user_id);

        if ($currentCarrierCount >= $maxCarriers) {
            throw new Exception(
                "Vous avez atteint votre limite de {$maxCarriers} transporteurs pour votre plan actuel. " .
                "Mettez à niveau votre plan pour ajouter plus de transporteurs."
            );
        }
    }

    // -----------------------------------------------------------------------
    // Auth mode
    // -----------------------------------------------------------------------
    $auth_mode = isset($_POST['auth_mode']) ? strtolower(trim($_POST['auth_mode'])) : 'api';
    if (!in_array($auth_mode, ['api', 'auth'], true)) {
        $auth_mode = 'api';
    }

    // -----------------------------------------------------------------------
    // Common required field: name
    // -----------------------------------------------------------------------
    if (!isset($_POST['name']) || trim($_POST['name']) === '') {
        echo json_encode(['success' => false, 'message' => 'Missing required field: name']);
        exit;
    }

    $company_name = trim($_POST['name']);
    $company_slug = isset($_POST['slug']) && trim($_POST['slug']) !== ''
        ? trim($_POST['slug'])
        : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $company_name));

    // -----------------------------------------------------------------------
    // Mode-specific fields (api_url is shared: API base URL OR login portal URL)
    // -----------------------------------------------------------------------
    $api_url       = '';
    $api_key       = '';
    $api_secret    = '';
    $auth_email    = '';
    $auth_password = '';
    $auth_notes    = '';

    if ($auth_mode === 'auth') {
        // In auth mode, api_url = the login portal URL
        $api_url       = isset($_POST['api_url']) ? trim($_POST['api_url']) : '';
        $auth_email    = isset($_POST['auth_email']) ? trim($_POST['auth_email']) : '';
        $auth_password = isset($_POST['auth_password']) ? (string)$_POST['auth_password'] : '';
        $auth_notes    = isset($_POST['auth_notes']) ? trim($_POST['auth_notes']) : '';

        if ($api_url === '' || $auth_email === '' || $auth_password === '') {
            echo json_encode([
                'success' => false,
                'message' => 'Champs requis manquants (URL du portail, email/utilisateur, mot de passe)'
            ]);
            exit;
        }

        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'URL du portail invalide']);
            exit;
        }

        // Accept email OR username
        if (strpos($auth_email, '@') !== false && !filter_var($auth_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Email invalide']);
            exit;
        }
        if (strlen($auth_email) < 3) {
            echo json_encode(['success' => false, 'message' => 'Nom d\'utilisateur trop court']);
            exit;
        }
    } else {
        // API mode
        $api_url    = isset($_POST['api_url']) ? trim($_POST['api_url']) : '';
        $api_key    = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';
        $api_secret = isset($_POST['api_secret']) ? trim($_POST['api_secret']) : '';

        if ($api_url === '') {
            echo json_encode(['success' => false, 'message' => 'Missing required field: api_url']);
            exit;
        }
        if (!filter_var($api_url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid API URL format']);
            exit;
        }
    }

    // -----------------------------------------------------------------------
    // Booleans
    // -----------------------------------------------------------------------
    $is_active = 1;
    if (isset($_POST['is_active'])) {
        $is_active = in_array($_POST['is_active'], ['on', '1', 'true', true, 1], true) ? 1 : 0;
    }

    $supports_tracking = 0;
    if (isset($_POST['supports_tracking'])) {
        $supports_tracking = in_array($_POST['supports_tracking'], ['on', '1', 'true', true, 1], true) ? 1 : 0;
    }

    // -----------------------------------------------------------------------
    // Build payload
    // -----------------------------------------------------------------------
    $base_data = [
        'name'              => $company_name,
        'api_url'           => $api_url,   // shared: API base OR login portal
        'auth_mode'         => $auth_mode,
        'supports_tracking' => $supports_tracking,
        'is_active'         => $is_active,
    ];

    if ($auth_mode === 'auth') {
        $base_data['auth_email']    = $auth_email;
        $base_data['auth_password'] = encryptAuthPassword($auth_password);
        $base_data['auth_notes']    = $auth_notes;

        // API-only columns stay empty in auth mode
        $base_data['api_key']    = null;
        $base_data['api_secret'] = null;
    } else {
        $base_data['api_key']    = $api_key;
        $base_data['api_secret'] = $api_secret;

        // Auth-only columns stay empty in api mode
        $base_data['auth_email']    = null;
        $base_data['auth_password'] = null;
        $base_data['auth_notes']    = null;
    }

    // -----------------------------------------------------------------------
    // UPDATE
    // -----------------------------------------------------------------------
    if ($is_update) {
        $existing_company = $db->getThisQuery(
            "SELECT id, auth_password FROM shipping_companies WHERE id = ? AND user_id = ?",
            [$company_id, $user_id]
        );

        if (empty($existing_company)) {
            echo json_encode([
                'success' => false,
                'message' => 'Transporteur non trouvé ou accès non autorisé'
            ]);
            exit;
        }

        // If auth mode and password left blank on edit -> keep the existing one
        if ($auth_mode === 'auth' && $auth_password === '') {
            unset($base_data['auth_password']);
        }

        $base_data['updated_at'] = date('Y-m-d H:i:s');

        $company_result = $db->update('shipping_companies', $company_id, $base_data);

        if ($company_result) {
            echo json_encode([
                'success'   => true,
                'message'   => 'Transporteur mis à jour avec succès',
                'action'    => 'updated',
                'id'        => $company_id,
                'auth_mode' => $auth_mode,
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Échec de la mise à jour du transporteur'
            ]);
        }
        exit;
    }

    // -----------------------------------------------------------------------
    // CREATE
    // -----------------------------------------------------------------------
    $duplicate_check = $db->getThisQuery(
        "SELECT id FROM shipping_companies WHERE name = ? AND user_id = ?",
        [$company_name, $user_id]
    );

    if (!empty($duplicate_check)) {
        echo json_encode([
            'success' => false,
            'message' => 'Un transporteur avec ce nom existe déjà'
        ]);
        exit;
    }

    $base_data['user_id']    = $user_id;
    $base_data['created_at'] = date('Y-m-d H:i:s');

    $company_result = $db->insert('shipping_companies', $base_data);

    if ($company_result) {
        $new_company_id = $db->getLastInsertId();

        if (!updateUserOnboarding($db, $user_id, 'step_shipping', true)) {
            error_log("Failed to update onboarding progress for user: $user_id");
        }

        $remainingCarriers = is_int($maxCarriers)
            ? max(0, $maxCarriers - ($currentCarrierCount + 1))
            : null;

        echo json_encode([
            'success'   => true,
            'message'   => 'Transporteur ajouté avec succès',
            'action'    => 'created',
            'id'        => $new_company_id,
            'auth_mode' => $auth_mode,
            'plan_info' => [
                'max_carriers'       => $maxCarriers,
                'current_carriers'   => $currentCarrierCount + 1,
                'remaining_carriers' => $remainingCarriers,
            ],
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Échec de la création du transporteur'
        ]);
    }

} catch (Exception $e) {
    error_log("Error in save_shipping_company.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ===========================================================================
// Helpers
// ===========================================================================

function updateUserOnboarding($db, $user_id, $step, $completed = true) {
    try {
        $current_time = date('Y-m-d H:i:s');

        $existing_onboarding = $db->getThisQuery(
            "SELECT id, step_store, step_shipping, step_products
             FROM user_onboarding WHERE user_id = ?",
            [$user_id]
        );

        if (!empty($existing_onboarding)) {
            $onboarding_id          = $existing_onboarding[0]['id'];
            $current_step_store     = $existing_onboarding[0]['step_store'];
            $current_step_shipping  = $existing_onboarding[0]['step_shipping'];
            $current_step_products  = $existing_onboarding[0]['step_products'];

            $update_data = ['updated_at' => $current_time];

            if ($step === 'step_store') {
                $update_data['step_store']    = $completed ? 1 : 0;
                $current_step_store           = $completed ? 1 : 0;
            } elseif ($step === 'step_shipping') {
                $update_data['step_shipping'] = $completed ? 1 : 0;
                $current_step_shipping        = $completed ? 1 : 0;
            } elseif ($step === 'step_products') {
                $update_data['step_products'] = $completed ? 1 : 0;
                $current_step_products        = $completed ? 1 : 0;
            }

            $required_steps_completed = ($current_step_store == 1 && $current_step_shipping == 1);
            $update_data['completed'] = $required_steps_completed ? 1 : 0;

            return (bool)$db->update('user_onboarding', $onboarding_id, $update_data);
        }

        $onboarding_data = [
            'user_id'       => $user_id,
            'step_store'    => 0,
            'step_shipping' => 0,
            'step_products' => 0,
            'completed'     => 0,
            'created_at'    => $current_time,
            'updated_at'    => $current_time,
        ];

        if ($step === 'step_store')         $onboarding_data['step_store']    = $completed ? 1 : 0;
        elseif ($step === 'step_shipping')  $onboarding_data['step_shipping'] = $completed ? 1 : 0;
        elseif ($step === 'step_products')  $onboarding_data['step_products'] = $completed ? 1 : 0;

        $required_steps_completed = (
            $onboarding_data['step_store'] == 1 && $onboarding_data['step_shipping'] == 1
        );
        $onboarding_data['completed'] = $required_steps_completed ? 1 : 0;

        return (bool)$db->insert('user_onboarding', $onboarding_data);

    } catch (Exception $e) {
        error_log("Exception in updateUserOnboarding: " . $e->getMessage());
        return false;
    }
}

function getSubscriptionPlanDetails($db, $user_id) {
    $query = "
        SELECT
            p.id AS plan_id, p.name AS plan_name, p.price AS plan_price, p.is_custom,
            up.status, up.activated_at AS subscription_start, up.expires_at,
            up.duration_months, up.monthly_price, up.total_amount,
            pl.limit_key, pl.limit_value
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
    if (empty($result)) return null;

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
        'limits'             => [],
    ];

    foreach ($result as $row) {
        if (!empty($row['limit_key'])) {
            $planDetails['limits'][$row['limit_key']] = $row['limit_value'];
        }
    }

    return $planDetails;
}

function getCurrentCarrierCount($db, $user_id) {
    $result = $db->getThisQuery(
        "SELECT COUNT(*) AS carrier_count FROM shipping_companies WHERE user_id = ? AND is_active = 1",
        [$user_id]
    );
    return empty($result) ? 0 : (int)$result[0]['carrier_count'];
}

function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    $identifier = $_SESSION['user']['username'];
    $user = $db->getThisQuery(
        "SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1",
        [$identifier, $identifier, $identifier]
    );

    return ($user && isset($user[0]['id'])) ? (int)$user[0]['id'] : null;
} ?>