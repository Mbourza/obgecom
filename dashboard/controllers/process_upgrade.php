<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

require_once('./cmi/cmi_config.php');

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
    // ============================================================================
    // VALIDATE REQUIRED FIELDS
    // ============================================================================
    $required_fields = ['user_id', 'plan_id', 'duration'];
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    if (!empty($missing_fields)) {
        throw new Exception("Champs requis manquants: " . implode(', ', $missing_fields));
    }

    $user_id = (int)$data['user_id'];
    $plan_id = (int)$data['plan_id'];
    $duration = (int)$data['duration'];
    
    // Validate duration
    if (!in_array($duration, [1, 3, 12])) {
        throw new Exception("Durée invalide. Choisissez 1, 3 ou 12 mois.");
    }

    // ============================================================================
    // GET USER INFORMATION AND CURRENT SUBSCRIPTION
    // ============================================================================
    $user_query = $db->query("
        SELECT u.*, 
               s.id as subscription_id, 
               s.plan_id as current_plan_id, 
               s.status as subscription_status,
               s.expires_at,
               cp.name as current_plan_name,
               cp.price as current_monthly_price
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
        LEFT JOIN plans cp ON s.plan_id = cp.id
        WHERE u.id = ?
        ORDER BY s.id DESC LIMIT 1
    ", [$user_id])->first();

    if (!$user_query) {
        throw new Exception("Utilisateur non trouvé.");
    }

    // ============================================================================
    // GET TARGET PLAN INFORMATION
    // ============================================================================
    $target_plan = $db->get('plans', array('id', '=', $plan_id))->first();
    if (!$target_plan) {
        throw new Exception("Plan sélectionné non trouvé.");
    }

    // Check if plan is active
    if (isset($target_plan->is_active) && !$target_plan->is_active) {
        throw new Exception("Ce plan n'est pas disponible actuellement.");
    }

    // ============================================================================
    // VALIDATE UPGRADE ELIGIBILITY
    // ============================================================================
    
    // 1. Check if trying to upgrade to same plan
    if ($user_query->subscription_id && $user_query->current_plan_id == $plan_id) {
        throw new Exception("Vous êtes déjà abonné à ce plan.");
    }
    
    // 2. Check if target plan is cheaper (downgrade)
    $target_price = (float)$target_plan->price;
    $current_price = (float)($user_query->current_monthly_price ?? 0);
    
    if ($user_query->subscription_id && $target_price < $current_price) {
        // For downgrades, show info message (you might want to handle differently)
        // throw new Exception("Pour passer à un plan inférieur, veuillez contacter le support.");
    }
    
    // 3. Check if user is on evaluation plan and trying to renew instead of upgrade
    $evaluation_plans = ['starter', 'free', 'evaluation', 'test', 'trial'];
    $current_plan_name = strtolower($user_query->current_plan_name ?? '');
    
    $is_evaluation_plan = in_array($current_plan_name, $evaluation_plans) || 
                         ($user_query->current_monthly_price ?? 0) == 0 || 
                         strpos($current_plan_name, 'eval') !== false ||
                         strpos($current_plan_name, 'test') !== false;

    // ============================================================================
    // CALCULATE PRORATED AMOUNT (if upgrading from paid plan)
    // ============================================================================
    $prorated_credit = 0;
    $upgrade_details = [];
    
    if ($user_query->subscription_id && 
        $user_query->subscription_status === 'active' && 
        $user_query->current_monthly_price > 0 &&
        !$is_evaluation_plan) {
        
        // Calculate unused time from current subscription
        if ($user_query->expires_at) {
            $current_expiry = new DateTime($user_query->expires_at);
            $today = new DateTime();
            
            if ($today < $current_expiry) {
                $days_remaining = $today->diff($current_expiry)->days;
                $total_days_in_period = 30; // Assuming monthly billing
                
                if ($days_remaining > 0 && $total_days_in_period > 0) {
                    // Calculate prorated credit
                    $prorated_credit = ($user_query->current_monthly_price / $total_days_in_period) * $days_remaining;
                    $prorated_credit = round($prorated_credit, 2);
                    
                    $upgrade_details['prorated_credit'] = $prorated_credit;
                    $upgrade_details['days_remaining'] = $days_remaining;
                }
            }
        }
    }

    // ============================================================================
    // CALCULATE FINAL AMOUNTS WITH DISCOUNTS
    // ============================================================================
    $discounts = [
        1 => 0,
        3 => 5,
        12 => 15
    ];
    
    $discount_percentage = $discounts[$duration] ?? 0;
    $discounted_monthly_price = $target_price * (1 - ($discount_percentage / 100));
    $total_amount_before_credit = $discounted_monthly_price * $duration;
    
    // Apply prorated credit if available
    $final_amount = $total_amount_before_credit - $prorated_credit;
    if ($final_amount < 0) {
        $final_amount = 0;
        $upgrade_details['credit_carried_over'] = abs($final_amount);
    }
    
    // Round amounts
    $final_amount = round($final_amount, 2);
    $discounted_monthly_price = round($discounted_monthly_price, 2);
    $total_amount_before_credit = round($total_amount_before_credit, 2);

    // ============================================================================
    // GENERATE TRANSACTION ID AND SAVE PAYMENT ATTEMPT
    // ============================================================================
    $transaction_id = 'UPG' . date('YmdHis') . rand(10000, 99999);
    
    $payment_data = [
        'user_id' => $user_id,
        'transaction_id' => $transaction_id,
        'amount' => $final_amount,
        'monthly_amount' => $discounted_monthly_price,
        'duration_months' => $duration,
        'currency' => '504',
        'plan_id' => $plan_id,
        'plan_name' => $target_plan->name,
        'payment_type' => 'upgrade',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'metadata' => json_encode([
            'current_plan_id' => $user_query->current_plan_id,
            'current_plan_name' => $user_query->current_plan_name,
            'current_monthly_price' => $user_query->current_monthly_price,
            'discount_percentage' => $discount_percentage,
            'prorated_credit' => $prorated_credit,
            'total_before_credit' => $total_amount_before_credit,
            'is_evaluation_upgrade' => $is_evaluation_plan,
            'upgrade_details' => $upgrade_details
        ])
    ];

    $db->insert('payment_attempts', $payment_data);

    // ============================================================================
    // CREATE UPGRADE RECORD (PENDING)
    // ============================================================================
    $upgrade_record = [
        'user_id' => $user_id,
        'current_subscription_id' => $user_query->subscription_id,
        'current_plan_id' => $user_query->current_plan_id,
        'new_plan_id' => $plan_id,
        'transaction_id' => $transaction_id,
        'duration_months' => $duration,
        'monthly_price' => $discounted_monthly_price,
        'total_amount' => $final_amount,
        'discount_percentage' => $discount_percentage,
        'prorated_credit' => $prorated_credit,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $db->insert('plan_upgrades', $upgrade_record);

    // ============================================================================
    // PREPARE CMI PAYMENT DATA
    // ============================================================================
    $amount_formatted = number_format($final_amount, 2, '.', '');
    $rnd = date('YmdHis') . rand(1000, 9999);

    // Build URLs with transaction id and type
    $okUrlFull = rtrim(OK_URL, '/') . "?transaction_id={$transaction_id}&type=upgrade";
    $failUrlFull = rtrim(FAIL_URL, '/') . "?transaction_id={$transaction_id}&type=upgrade";

    // Create description with upgrade info
    $description = "Mise à niveau vers {$target_plan->name} - {$duration} mois";
    if ($discount_percentage > 0) {
        $description .= " (Économisez {$discount_percentage}%)";
    }
    if ($prorated_credit > 0) {
        $description .= " - Crédit appliqué: {$prorated_credit} MAD";
    }
    if ($duration > 1) {
        $description .= " ({$discounted_monthly_price} MAD/mois)";
    }

    // Prepare user info for payment
    $user_name = !empty($user_query->username) ? $user_query->username : 
                (!empty($user_query->full_name) ? $user_query->full_name : '-');
    $user_email = $user_query->email;
    $user_phone = !empty($user_query->phone) ? $user_query->phone : '-';

    $cmi_data = array(
        "clientid"        => CMI_MERCHANT_ID,
        "amount"          => $amount_formatted,
        "oid"             => $transaction_id,
        "okUrl"           => $okUrlFull,
        "failUrl"         => $failUrlFull,
        "TranType"        => "PreAuth", 
        "currency"        => "504",
        "rnd"             => $rnd,
        "storetype"       => "3D_PAY_HOSTING",
        "hashAlgorithm"   => "ver3",
        "callbackUrl"     => CALLBACK_URL,
        "shopurl"         => SHOP_URL,
        "lang"            => "fr",
        "refreshtime"     => "5",
        "BillToName"      => cleanString($user_name),
        "email"           => $user_email,
        "tel"             => cleanString($user_phone),
        "description"     => $description
    );

    // ============================================================================
    // GENERATE HASH AND FINALIZE PAYMENT DATA
    // ============================================================================
    $hash = generateCMIHashFixed($cmi_data, CMI_SECRET_KEY);
    $cmi_data["HASH"] = $hash;
    $cmi_data["encoding"] = "UTF-8";

    // Validate parameters
    validateCMIParameters($cmi_data);

    // ============================================================================
    // GENERATE PAYMENT FORM HTML WITH UPGRADE INFO
    // ============================================================================
    $form_html = generateUpgradePaymentForm($cmi_data, [
        'current_plan' => $user_query->current_plan_name ?? 'Aucun',
        'new_plan' => $target_plan->name,
        'duration' => $duration,
        'monthly_price' => $discounted_monthly_price,
        'total_amount' => $final_amount,
        'discount_percentage' => $discount_percentage,
        'prorated_credit' => $prorated_credit,
        'is_evaluation_upgrade' => $is_evaluation_plan
    ]);

    // ============================================================================
    // RETURN SUCCESS RESPONSE
    // ============================================================================
    echo json_encode([
        'success' => true,
        'form_html' => $form_html,
        'transaction_id' => $transaction_id,
        'gateway_url' => CMI_GATEWAY_URL,
        'amount' => $amount_formatted,
        'monthly_amount' => $discounted_monthly_price,
        'duration' => $duration,
        'discount_percentage' => $discount_percentage,
        'prorated_credit' => $prorated_credit,
        'current_plan' => $user_query->current_plan_name ?? 'Aucun',
        'new_plan' => $target_plan->name,
        'is_evaluation_upgrade' => $is_evaluation_plan,
        'debug_info' => [
            'amount' => $amount_formatted,
            'rnd' => $rnd,
            'oid' => $transaction_id,
            'description' => $description,
            'user_id' => $user_id,
            'plan_id' => $plan_id
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in process_upgrade.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => 'UPGRADE_ERROR'
    ]);
    exit;
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function cleanString($str) {
    $str = trim($str);
    $str = str_replace(["\r", "\n", "\t"], " ", $str);
    $str = preg_replace('/[^\p{L}\p{N}\s\-\.@:+]/u', '', $str);
    return substr($str, 0, 100);
}

function generateCMIHashFixed($data, $storeKey) {
    $hashData = $data;
    unset($hashData['HASH']);
    unset($hashData['hash']);
    unset($hashData['encoding']);
    unset($hashData['ENCODING']);

    $paramNames = array_keys($hashData);
    natcasesort($paramNames);

    $hashString = "";
    foreach ($paramNames as $paramName) {
        $paramValue = (string)$hashData[$paramName];
        $paramValue = trim($paramValue);
        $paramValue = str_replace("\\", "\\\\", $paramValue);
        $paramValue = str_replace("|", "\\|", $paramValue);
        $hashString .= $paramValue . "|";
    }

    $storeKey = trim($storeKey);
    $storeKey = str_replace("\\", "\\\\", $storeKey);
    $storeKey = str_replace("|", "\\|", $storeKey);
    $hashString .= $storeKey;

    $sha512Hash = hash('sha512', $hashString);
    $hash = base64_encode(pack('H*', $sha512Hash));

    return $hash;
}

function validateCMIParameters($cmi_data) {
    $errors = [];
    $required = ['clientid', 'amount', 'oid', 'okUrl', 'failUrl', 'TranType', 'currency', 'rnd', 'storetype', 'hashAlgorithm'];
    foreach ($required as $field) {
        if (!isset($cmi_data[$field]) || trim((string)$cmi_data[$field]) === '') {
            $errors[] = "Missing required field: $field";
        }
    }
    if (!empty($cmi_data['amount']) && !preg_match('/^\d+\.\d{2}$/', $cmi_data['amount'])) {
        $errors[] = "Invalid amount format: must be X.XX";
    }
    if (!empty($errors)) {
        throw new Exception("Parameter validation failed: " . implode(', ', $errors));
    }
}

function generateUpgradePaymentForm($cmi_data, $upgrade_info) {
    $plan_change_text = '';
    if ($upgrade_info['is_evaluation_upgrade']) {
        $plan_change_text = "Transition depuis un plan d'évaluation";
    } else {
        $plan_change_text = "Mise à niveau depuis {$upgrade_info['current_plan']}";
    }
    
    $prorated_text = '';
    if ($upgrade_info['prorated_credit'] > 0) {
        $prorated_text = "<li>Crédit de report: <strong>{$upgrade_info['prorated_credit']} MAD</strong></li>";
    }
    
    $discount_text = '';
    if ($upgrade_info['discount_percentage'] > 0) {
        $discount_text = "<li>Réduction: <strong>{$upgrade_info['discount_percentage']}%</strong></li>";
    }
    
    $form_html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Mise à niveau - Paiement Sécurisé CMI</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    text-align: center; 
                    padding: 50px; 
                    background: #f5f5f5; 
                    margin: 0;
                }
                .container { 
                    background: white; 
                    padding: 30px; 
                    border-radius: 10px; 
                    max-width: 500px; 
                    margin: 0 auto; 
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                }
                .spinner { 
                    border: 4px solid #f3f3f3; 
                    border-top: 4px solid #8a2be2; 
                    border-radius: 50%; 
                    width: 40px; 
                    height: 40px; 
                    animation: spin 2s linear infinite; 
                    margin: 20px auto; 
                }
                @keyframes spin { 
                    0% { transform: rotate(0deg); } 
                    100% { transform: rotate(360deg); } 
                }
                .loading-text {
                    margin: 15px 0;
                    color: #333;
                    font-size: 16px;
                }
                .progress-bar {
                    width: 100%;
                    background-color: #f3f3f3;
                    border-radius: 5px;
                    margin: 20px 0;
                    overflow: hidden;
                }
                .progress {
                    width: 0%;
                    height: 8px;
                    background: linear-gradient(90deg, #8a2be2, #6a11cb);
                    border-radius: 5px;
                    transition: width 0.3s ease;
                }
                .btn { 
                    background: linear-gradient(135deg, #8a2be2, #6a11cb);
                    color: white; 
                    padding: 12px 24px; 
                    border: none; 
                    border-radius: 5px; 
                    cursor: pointer; 
                    font-size: 16px; 
                    margin-top: 20px; 
                    display: none;
                }
                .btn:hover { 
                    background: linear-gradient(135deg, #6a11cb, #8a2be2);
                    transform: translateY(-1px);
                }
                .countdown {
                    font-weight: bold;
                    color: #8a2be2;
                }
                .upgrade-info {
                    background: #f8f5ff;
                    border: 1px solid #e2d8ff;
                    padding: 20px;
                    margin: 20px 0;
                    text-align: left;
                    border-radius: 8px;
                }
                .upgrade-info h4 {
                    margin: 0 0 15px 0;
                    color: #5b21b6;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .upgrade-info ul {
                    margin: 0;
                    padding-left: 20px;
                    color: #475569;
                }
                .upgrade-info li {
                    margin-bottom: 8px;
                    padding: 5px 0;
                }
                .plan-change {
                    background: #e0f2fe;
                    border-left: 4px solid #0ea5e9;
                    padding: 15px;
                    margin: 15px 0;
                    border-radius: 5px;
                }
                .amount-breakdown {
                    background: #f0f9ff;
                    border: 1px solid #bae6fd;
                    padding: 15px;
                    margin: 15px 0;
                    border-radius: 5px;
                }
                .highlight {
                    color: #8a2be2;
                    font-weight: bold;
                }
                .bi {
                    font-size: 1.2em;
                }
            </style>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        </head>
        <body>
            <div class="container">
                <h3 style="color: #8a2be2; margin-bottom: 20px;">
                    <i class="bi bi-arrow-up-circle-fill"></i> Mise à niveau de plan
                </h3>
                
                <div class="plan-change">
                    <h4><i class="bi bi-arrow-up-right"></i> ' . $plan_change_text . '</h4>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px;">
                        <div style="text-align: center;">
                            <div style="font-size: 12px; color: #64748b;">Plan actuel</div>
                            <div style="font-weight: bold; color: #475569;">' . htmlspecialchars($upgrade_info['current_plan']) . '</div>
                        </div>
                        <div><i class="bi bi-arrow-right" style="color: #8a2be2;"></i></div>
                        <div style="text-align: center;">
                            <div style="font-size: 12px; color: #64748b;">Nouveau plan</div>
                            <div style="font-weight: bold; color: #8a2be2;">' . htmlspecialchars($upgrade_info['new_plan']) . '</div>
                        </div>
                    </div>
                </div>
                
                <div class="upgrade-info">
                    <h4><i class="bi bi-receipt"></i> Détails de la mise à niveau:</h4>
                    <ul>
                        <li>Nouveau plan: <strong>' . htmlspecialchars($upgrade_info['new_plan']) . '</strong></li>
                        <li>Durée: <strong>' . $upgrade_info['duration'] . ' mois</strong></li>
                        <li>Prix mensuel: <strong>' . number_format($upgrade_info['monthly_price'], 2) . ' MAD</strong></li>
                        ' . $discount_text . '
                        ' . $prorated_text . '
                        <li>Montant total: <strong class="highlight">' . number_format($upgrade_info['total_amount'], 2) . ' MAD</strong></li>
                    </ul>
                </div>
                
                <p class="loading-text">Redirection vers la passerelle de paiement...</p>
                <p class="loading-text">Redirection automatique dans <span class="countdown" id="countdown">3</span> secondes</p>
                
                <div class="progress-bar">
                    <div class="progress" id="progressBar"></div>
                </div>
                
                <div class="spinner"></div>
                
                <p style="font-size: 14px; color: #666; margin-top: 20px;">
                    <i class="bi bi-shield-check"></i> Paiement 100% sécurisé via CMI
                </p>
                
                <form id="cmi_form" method="POST" action="' . htmlspecialchars(CMI_GATEWAY_URL) . '">';
            
            foreach ($cmi_data as $key => $value) {
                $form_html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">' . "\n";
            }
            
            $form_html .= '
                </form>
                
                <button type="button" class="btn" id="manualSubmitBtn" onclick="submitPaymentForm()">
                    <i class="bi bi-credit-card"></i> Payer maintenant
                </button>
            </div>

            <script>
                let countdown = 3;
                let submitted = false;
                
                function startCountdown() {
                    const countdownElement = document.getElementById("countdown");
                    const progressBar = document.getElementById("progressBar");
                    
                    const countdownInterval = setInterval(() => {
                        countdownElement.textContent = countdown;
                        progressBar.style.width = ((3 - countdown) / 3 * 100) + "%";
                        
                        if (countdown <= 0) {
                            clearInterval(countdownInterval);
                            submitPaymentForm();
                        }
                        countdown--;
                    }, 1000);
                }
                
                function submitPaymentForm() {
                    if (submitted) return;
                    
                    submitted = true;
                    const form = document.getElementById("cmi_form");
                    
                    try {
                        console.log("Submitting upgrade payment form to:", form.action);
                        form.submit();
                    } catch (error) {
                        console.error("Form submission error:", error);
                        showManualButton();
                    }
                }
                
                function showManualButton() {
                    document.getElementById("manualSubmitBtn").style.display = "inline-block";
                    document.querySelector(".loading-text").textContent = "Cliquez sur le bouton ci-dessous pour continuer le paiement";
                    document.querySelector(".spinner").style.display = "none";
                }
                
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", function() {
                        setTimeout(startCountdown, 1000);
                        setTimeout(showManualButton, 8000);
                    });
                } else {
                    setTimeout(startCountdown, 1000);
                    setTimeout(showManualButton, 8000);
                }
                
                document.addEventListener("visibilitychange", function() {
                    if (!document.hidden && !submitted) {
                        setTimeout(submitPaymentForm, 1000);
                    }
                });
                
                // Handle back button/refresh
                window.addEventListener("beforeunload", function (e) {
                    if (!submitted) {
                        e.preventDefault();
                        e.returnValue = "Votre paiement est en cours. Êtes-vous sûr de vouloir quitter?";
                    }
                });
            </script>
        </body>
    </html>';
    
    return $form_html;
}
?>