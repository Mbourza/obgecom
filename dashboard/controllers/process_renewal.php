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
    $required_fields = ['user_id', 'duration'];
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
    $duration = (int)$data['duration'];
    
    // Validate duration
    if (!in_array($duration, [1, 3, 12])) {
        throw new Exception("Durée invalide. Choisissez 1, 3 ou 12 mois.");
    }

    // ============================================================================
    // GET USER'S CURRENT SUBSCRIPTION AND PLAN
    // ============================================================================
    $user_query = $db->query("
        SELECT u.*, s.id as subscription_id, s.plan_id, s.status as subscription_status, 
               s.expires_at, p.name as plan_name, p.price as monthly_price, p.is_custom
        FROM users u
        LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
        LEFT JOIN plans p ON s.plan_id = p.id
        WHERE u.id = ?
        ORDER BY s.id DESC LIMIT 1
    ", [$user_id])->first();

    if (!$user_query) {
        throw new Exception("Utilisateur non trouvé.");
    }

    if (!$user_query->subscription_id) {
        throw new Exception("Aucun abonnement actif trouvé.");
    }

    if ($user_query->subscription_status !== 'active') {
        throw new Exception("Votre abonnement n'est pas actif.");
    }

    // ============================================================================
    // CHECK IF PLAN CAN BE RENEWED (NOT EVALUATION/FREE)
    // ============================================================================
    $evaluation_plans = ['starter', 'free', 'evaluation', 'test', 'trial'];
    $current_plan_name = strtolower($user_query->plan_name);
    
    $is_evaluation_plan = in_array($current_plan_name, $evaluation_plans) || 
                         $user_query->monthly_price == 0 || 
                         strpos($current_plan_name, 'eval') !== false ||
                         strpos($current_plan_name, 'test') !== false;

    if ($is_evaluation_plan) {
        throw new Exception("Les plans d'évaluation ne peuvent pas être renouvelés. Veuillez mettre à niveau vers un plan payant.");
    }

    // ============================================================================
    // CALCULATE RENEWAL AMOUNTS WITH DISCOUNTS
    // ============================================================================
    $monthly_price = (float)$user_query->monthly_price;
    
    // Apply duration discounts
    $discounts = [
        1 => 0,
        3 => 5,
        12 => 15
    ];
    
    $discount_percentage = $discounts[$duration] ?? 0;
    $discounted_monthly_price = $monthly_price * (1 - ($discount_percentage / 100));
    $total_amount = $discounted_monthly_price * $duration;

    // Round to 2 decimal places
    $total_amount = round($total_amount, 2);
    $discounted_monthly_price = round($discounted_monthly_price, 2);

    // ============================================================================
    // CHECK IF SUBSCRIPTION IS NEAR EXPIRATION
    // ============================================================================
    $is_near_expiration = false;
    if ($user_query->expires_at) {
        $expires_date = new DateTime($user_query->expires_at);
        $current_date = new DateTime();
        $days_remaining = $current_date->diff($expires_date)->days;
        
        $is_near_expiration = $days_remaining <= 30;
    }

    // ============================================================================
    // GENERATE TRANSACTION ID AND SAVE PAYMENT ATTEMPT
    // ============================================================================
    $transaction_id = 'REN' . date('YmdHis') . rand(10000, 99999);
    
    $payment_data = [
        'user_id' => $user_id,
        'transaction_id' => $transaction_id,
        'amount' => $total_amount,
        'monthly_amount' => $discounted_monthly_price,
        'duration_months' => $duration,
        'currency' => '504',
        'plan_id' => $user_query->plan_id,
        'plan_name' => $user_query->plan_name,
        'payment_type' => 'renewal',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'metadata' => json_encode([
            'original_monthly_price' => $monthly_price,
            'discount_percentage' => $discount_percentage,
            'is_near_expiration' => $is_near_expiration,
            'current_expiry' => $user_query->expires_at
        ])
    ];

    $db->insert('payment_attempts', $payment_data);

    // ============================================================================
    // PREPARE CMI PAYMENT DATA
    // ============================================================================
    $amount_formatted = number_format($total_amount, 2, '.', '');
    $rnd = date('YmdHis') . rand(1000, 9999);

    // Build URLs with transaction id
    $okUrlFull = rtrim(OK_URL, '/') . "?transaction_id={$transaction_id}&type=renewal";
    $failUrlFull = rtrim(FAIL_URL, '/') . "?transaction_id={$transaction_id}&type=renewal";

    // Create description with renewal info
    $description = "Renouvellement {$user_query->plan_name} - {$duration} mois";
    if ($discount_percentage > 0) {
        $description .= " (Économisez {$discount_percentage}%)";
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
    // CREATE RENEWAL RECORD (PENDING)
    // ============================================================================
    $renewal_data = [
        'user_id' => $user_id,
        'subscription_id' => $user_query->subscription_id,
        'transaction_id' => $transaction_id,
        'plan_id' => $user_query->plan_id,
        'duration_months' => $duration,
        'monthly_price' => $discounted_monthly_price,
        'total_amount' => $total_amount,
        'discount_percentage' => $discount_percentage,
        'status' => 'pending',
        'current_expires_at' => $user_query->expires_at,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $db->insert('subscription_renewals', $renewal_data);

    // ============================================================================
    // GENERATE PAYMENT FORM HTML
    // ============================================================================
    $form_html = generateCMIFormFixed($cmi_data);

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
        'plan_name' => $user_query->plan_name,
        'debug_info' => [
            'amount' => $amount_formatted,
            'rnd' => $rnd,
            'oid' => $transaction_id,
            'description' => $description,
            'user_id' => $user_id
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in process_renewal.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => 'RENEWAL_ERROR'
    ]);
    exit;
}

// ============================================================================
// HELPER FUNCTIONS (Same as in your signup processor)
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

function generateCMIFormFixed($cmi_data) {
    $form_html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Renouvellement - Paiement Sécurisé CMI</title>
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
                    max-width: 400px; 
                    margin: 0 auto; 
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
                }
                .spinner { 
                    border: 4px solid #f3f3f3; 
                    border-top: 4px solid #10b981; 
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
                    background-color: #10b981;
                    border-radius: 5px;
                    transition: width 0.3s ease;
                }
                .btn { 
                    background: #10b981; 
                    color: white; 
                    padding: 12px 24px; 
                    border: none; 
                    border-radius: 5px; 
                    cursor: pointer; 
                    font-size: 16px; 
                    margin-top: 20px; 
                    display: none;
                }
                .btn:hover { background: #0d9c7a; }
                .countdown {
                    font-weight: bold;
                    color: #10b981;
                }
                .renewal-info {
                    background: #f0f9ff;
                    border-left: 4px solid #0ea5e9;
                    padding: 15px;
                    margin: 20px 0;
                    text-align: left;
                    border-radius: 5px;
                }
                .renewal-info h4 {
                    margin: 0 0 10px 0;
                    color: #0f172a;
                }
                .renewal-info ul {
                    margin: 0;
                    padding-left: 20px;
                    color: #475569;
                }
                .renewal-info li {
                    margin-bottom: 5px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h3 style="color: #10b981;">Renouvellement d\'abonnement</h3>
                
                <div class="renewal-info">
                    <h4><i class="bi bi-arrow-clockwise"></i> Détails du renouvellement:</h4>
                    <ul>
                        <li>Plan: ' . htmlspecialchars($cmi_data["description"]) . '</li>
                        <li>Montant total: ' . htmlspecialchars($cmi_data["amount"]) . ' MAD</li>
                        <li>Paiement sécurisé via CMI</li>
                    </ul>
                </div>
                
                <p class="loading-text">Redirection vers la passerelle de paiement...</p>
                <p class="loading-text">Redirection automatique dans <span class="countdown" id="countdown">3</span> secondes</p>
                
                <div class="progress-bar">
                    <div class="progress" id="progressBar"></div>
                </div>
                
                <div class="spinner"></div>
                
                <p style="font-size: 14px; color: #666; margin-top: 20px;">
                    Veuillez patienter, vous serez redirigé automatiquement.
                </p>
                
                <form id="cmi_form" method="POST" action="' . htmlspecialchars(CMI_GATEWAY_URL) . '">';
            
            foreach ($cmi_data as $key => $value) {
                $form_html .= '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">' . "\n";
            }
            
            $form_html .= '
                </form>
                
                <button type="button" class="btn" id="manualSubmitBtn" onclick="submitPaymentForm()">
                    Cliquez ici si la redirection ne fonctionne pas
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
                        console.log("Submitting renewal payment form to:", form.action);
                        form.submit();
                    } catch (error) {
                        console.error("Form submission error:", error);
                        showManualButton();
                    }
                }
                
                function showManualButton() {
                    document.getElementById("manualSubmitBtn").style.display = "inline-block";
                    document.querySelector(".loading-text").textContent = "Cliquez sur le bouton ci-dessous pour continuer";
                    document.querySelector(".spinner").style.display = "none";
                }
                
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", function() {
                        setTimeout(startCountdown, 500);
                        setTimeout(showManualButton, 10000);
                    });
                } else {
                    setTimeout(startCountdown, 500);
                    setTimeout(showManualButton, 10000);
                }
                
                document.addEventListener("visibilitychange", function() {
                    if (!document.hidden && !submitted) {
                        setTimeout(submitPaymentForm, 1000);
                    }
                });
            </script>
        </body>
    </html>';
    
    return $form_html;
}
?>