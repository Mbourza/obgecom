<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
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
    // Validate required fields
    $required_fields = ['user_id', 'plan', 'total_amount'];
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    if (!empty($missing_fields)) {
        throw new Exception("Champs requis manquants: " . implode(', ', $missing_fields));
    }

    // Get additional data for payment record
    $duration = isset($data['duration']) ? intval($data['duration']) : 1;
    $monthly_amount = isset($data['monthly_amount']) ? floatval($data['monthly_amount']) : 0;
    $plan_name = isset($data['plan_name']) ? $data['plan_name'] : $data['plan'];
    
    // Generate unique transaction ID - FIXED FORMAT
    $transaction_id = 'TXN' . date('YmdHis') . rand(10000, 99999);

    $plan_ids = [
        'starter'      => 1,
        'professional' => 2,
        'growth'       => 3,
        'business'     => 4
    ];
    
    // Make sure plan exists in map
    $plan_id = isset($plan_ids[$data['plan']]) ? $plan_ids[$data['plan']] : null;
    
    if (!$plan_id) {
        throw new Exception("Invalid plan selected: " . $data['plan']);
    }

    // Save payment attempt in database with duration information
    $payment_data = [
        'user_id' => $data['user_id'],
        'transaction_id' => $transaction_id,
        'amount' => $data['total_amount'],
        'monthly_amount' => $monthly_amount,
        'duration_months' => $duration,
        'currency' => '504',
        'plan_id' => $plan_id,
        'plan_name' => $plan_name,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $db->insert('payment_attempts', $payment_data);

    // Prepare basic values - use total_amount instead of monthly amount
    $amount_formatted = number_format(floatval($data['total_amount']), 2, '.', '');
    $rnd = date('YmdHis') . rand(1000, 9999); // safe, no spaces

    // Build ok/fail URLs including transaction id
    $okUrlFull = rtrim(OK_URL, '/') . "?transaction_id={$transaction_id}";
    $failUrlFull = rtrim(FAIL_URL, '/') . "?transaction_id={$transaction_id}";

    // Create description with duration information
    $description = "Abonnement {$plan_name} - {$duration} mois";
    if ($duration > 1) {
        $description .= " ({$monthly_amount} MAD/mois)";
    }

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
        "BillToName"      => !empty($data['name']) ? cleanString($data['name']) : "-",
        "email"           => !empty($data['email']) ? $data['email'] : '-',
        "tel"             => !empty($data['phone']) ? cleanString($data['phone']) : "-",
        "description"     => $description
    );

    // Generate the HASH using the exact algorithm 
    $hash = generateCMIHashFixed($cmi_data, CMI_SECRET_KEY);

    // Add HASH and encoding AFTER hash calculation 
    $cmi_data["HASH"] = $hash;
    $cmi_data["encoding"] = "UTF-8";

    // Validate minimal parameters
    validateCMIParameters($cmi_data);

    // Generate form HTML
    $form_html = generateCMIFormFixed($cmi_data);

    // Return JSON response
    echo json_encode([
        'success' => true,
        'form_html' => $form_html,
        'transaction_id' => $transaction_id,
        'gateway_url' => CMI_GATEWAY_URL,
        'amount' => $amount_formatted,
        'duration' => $duration,
        'monthly_amount' => $monthly_amount,
        'debug_info' => [
            'amount' => $amount_formatted,
            'rnd' => $rnd,
            'oid' => $transaction_id,
            'description' => $description
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in process_payment.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

/* ----------------- Helpers ----------------- */

function cleanString($str) {
    $str = trim($str);
    $str = str_replace(["\r", "\n", "\t"], " ", $str);
    $str = preg_replace('/[^\p{L}\p{N}\s\-\.@:+]/u', '', $str);
    return substr($str, 0, 100); // Limit length
}

function generateCMIHashFixed($data, $storeKey) {
    $hashData = $data;

    // Remove fields that shouldn't be in hash
    unset($hashData['HASH']);
    unset($hashData['hash']);
    unset($hashData['encoding']);
    unset($hashData['ENCODING']);

    // Sort keys (case-insensitive)
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

    // Add escaped store key
    $storeKey = trim($storeKey);
    $storeKey = str_replace("\\", "\\\\", $storeKey);
    $storeKey = str_replace("|", "\\|", $storeKey);
    $hashString .= $storeKey;

    // Generate SHA512 hash and base64 encode
    $sha512Hash = hash('sha512', $hashString);
    $hash = base64_encode(pack('H*', $sha512Hash));

    return $hash;
}

/**
 * Validate required cmi parameters
 */
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
            <title>Redirection vers CMI</title>
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
                    border-top: 4px solid #3498db; 
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
                    background-color: #3498db;
                    border-radius: 5px;
                    transition: width 0.3s ease;
                }
                .btn { 
                    background: #007bff; 
                    color: white; 
                    padding: 12px 24px; 
                    border: none; 
                    border-radius: 5px; 
                    cursor: pointer; 
                    font-size: 16px; 
                    margin-top: 20px; 
                    display: none;
                }
                .btn:hover { background: #0056b3; }
                .countdown {
                    font-weight: bold;
                    color: #007bff;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h3>Paiement Sécurisé CMI</h3>
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
            
            // Add all parameters as hidden fields
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
                
                // Countdown and progress animation
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
                
                // Submit form function
                function submitPaymentForm() {
                    if (submitted) return; // Prevent double submission
                    
                    submitted = true;
                    const form = document.getElementById("cmi_form");
                    
                    try {
                        console.log("Submitting payment form to:", form.action);
                        form.submit();
                    } catch (error) {
                        console.error("Form submission error:", error);
                        showManualButton();
                    }
                }
                
                // Show manual submit button
                function showManualButton() {
                    document.getElementById("manualSubmitBtn").style.display = "inline-block";
                    document.querySelector(".loading-text").textContent = "Cliquez sur le bouton ci-dessous pour continuer";
                    document.querySelector(".spinner").style.display = "none";
                }
                
                // Initialize when DOM is ready
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", function() {
                        setTimeout(startCountdown, 500); // Small delay to ensure everything is loaded
                        setTimeout(showManualButton, 10000); // Show manual button after 10 seconds as failsafe
                    });
                } else {
                    setTimeout(startCountdown, 500);
                    setTimeout(showManualButton, 10000);
                }
                
                // Handle page visibility changes (in case user switches tabs)
                document.addEventListener("visibilitychange", function() {
                    if (!document.hidden && !submitted) {
                        // Page became visible again, try to submit if not already done
                        setTimeout(submitPaymentForm, 1000);
                    }
                });
            </script>
        </body>
    </html>';
    
    return $form_html;
} ?>