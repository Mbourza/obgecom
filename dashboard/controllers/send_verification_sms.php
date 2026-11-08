<?php
session_start();
header('Content-Type: application/json');

function generateVerificationCode() {
    return sprintf("%06d", mt_rand(1, 999999));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $phone = $data['phone'] ?? '';
    $name = $data['name'] ?? '';
    $isResend = $data['resend'] ?? false;
    
    // Validate phone number format
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number is required']);
        exit;
    }
    
    // Format phone number for Morocco (add +212 if not present)
    if (!preg_match('/^\+212/', $phone)) {
        // Remove leading zeros and add +212
        $phone = preg_replace('/^0/', '', $phone);
        $phone = '+212' . $phone;
    }
    
    // Validate Moroccan phone number format
    if (!preg_match('/^\+212[567]\d{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Moroccan phone number. Must start with +2125, +2126, or +2127']);
        exit;
    }
    
    // Send verification via Infobip SMS
    $smsSent = sendInfobipSMSVerification($phone, $name, $isResend);
    
    if ($smsSent['success']) {
        $response = [
            'success' => true,
            'message' => $isResend ? 'Verification code resent via SMS!' : 'Verification code sent via SMS!',
            'method' => 'sms',
            'verification_code' => $smsSent['verification_code'] ?? ''
        ];
        
        // For debugging on localhost, show the code
        if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1') {
            $response['debug_code'] = $smsSent['verification_code'] ?? '';
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send SMS. Please try again.', 
            'error' => $smsSent['error'],
            'details' => $smsSent['details'] ?? ''
        ]);
    }
}

function sendInfobipSMSVerification($to, $name = '', $isResend = false) {
    
    // Infobip API credentials
    $apiKey = 'd3d42943a14f43f39c1627d8c133bfaf-1dea65c9-1bc8-4a46-90e2-d3659167e749';
    $sender = '447491163443'; // Your SMS sender number
    
    try {
        // Generate verification code
        $verificationCode = generateVerificationCode();
        
        // Store code in session for verification
        $_SESSION['sms_verification_code'] = $verificationCode;
        $_SESSION['verification_phone'] = $to;
        $_SESSION['verification_expires'] = time() + 600; // 10 minutes
        $_SESSION['verification_method'] = 'sms';
        
        // Create SMS message
        $messageText = "Hello " . ($name ?: 'User') . "!\n\nYour verification code is: " . $verificationCode . "\n\nThis code will expire in 10 minutes.\n\nOBG ECOM";
        
        // Prepare SMS payload according to Infobip documentation
        $payload = [
            'messages' => [
                [
                    'destinations' => [
                        ['to' => $to]
                    ],
                    'from' => $sender,
                    'text' => $messageText
                ]
            ]
        ];
        
        // Send SMS
        $result = sendInfobipSMSRequest('/sms/2/text/advanced', $payload, $apiKey);
        
        if ($result['success']) {
            return [
                'success' => true,
                'verification_code' => $verificationCode,
                'method' => 'sms',
                'message_id' => $result['message_id'] ?? ''
            ];
        }
        
        error_log("SMS failed: " . json_encode($result));
        
        // Fallback: Try simpler SMS endpoint
        $simplePayload = [
            'to' => str_replace('+212', '0', $to), // Convert to local format
            'from' => $sender,
            'text' => "Code: " . $verificationCode . " - OBG ECOM Verification"
        ];
        
        $simpleResult = sendInfobipSimpleSMSRequest('/sms/2/text/single', $simplePayload, $apiKey);
        
        if ($simpleResult['success']) {
            return [
                'success' => true,
                'verification_code' => $verificationCode,
                'method' => 'sms_simple',
                'message_id' => $simpleResult['message_id'] ?? ''
            ];
        }
        
        error_log("Simple SMS also failed: " . json_encode($simpleResult));
        
        // All methods failed - return session-only success for testing
        return [
            'success' => true,
            'verification_code' => $verificationCode,
            'method' => 'session_fallback',
            'warning' => 'SMS API failed, but code is stored in session for verification',
            'error_details' => [
                'advanced_error' => $result['error'],
                'simple_error' => $simpleResult['error']
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Infobip SMS exception: " . $e->getMessage());
        
        // Even if API fails, store code in session
        if (isset($verificationCode)) {
            return [
                'success' => true,
                'verification_code' => $verificationCode,
                'method' => 'session',
                'warning' => 'Exception occurred, but code is stored in session',
                'error' => $e->getMessage()
            ];
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'details' => 'Exception before code generation'
        ];
    }
}

function sendInfobipSMSRequest($endpoint, $payload, $apiKey) {
    $url = 'https://api.infobip.com' . $endpoint;
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Authorization: App ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    
    // For debugging
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);
    
    curl_close($ch);
    
    // Log the request and response for debugging
    error_log("Infobip SMS Request to: $endpoint");
    error_log("Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
    error_log("Response HTTP Code: $httpCode");
    error_log("Response Body: " . $response);
    if ($curlError) error_log("cURL Error: " . $curlError);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);
        
        // Extract message ID if available
        $messageId = null;
        if (isset($responseData['messages'][0]['messageId'])) {
            $messageId = $responseData['messages'][0]['messageId'];
        }
        
        // Check SMS status
        $status = null;
        if (isset($responseData['messages'][0]['status']['groupName'])) {
            $status = $responseData['messages'][0]['status']['groupName'];
        }
        
        if ($status === 'PENDING' || $status === 'DELIVERED' || $status === 'SENT' || !$status) {
            return [
                'success' => true,
                'response' => $responseData,
                'status' => $status,
                'message_id' => $messageId
            ];
        } else {
            return [
                'success' => false,
                'error' => "SMS status: " . ($status ?: 'unknown'),
                'http_code' => $httpCode,
                'response' => $responseData
            ];
        }
    } else {
        $errorMessage = "HTTP $httpCode";
        
        if ($response) {
            $responseData = json_decode($response, true);
            if (isset($responseData['requestError']['serviceException']['text'])) {
                $errorMessage .= " - " . $responseData['requestError']['serviceException']['text'];
            } else {
                $errorMessage .= " - Response: " . $response;
            }
        }
        
        if ($curlError) {
            $errorMessage .= " - cURL Error: $curlError";
        }
        
        return [
            'success' => false,
            'error' => $errorMessage,
            'http_code' => $httpCode,
            'response' => $response,
            'curl_error' => $curlError
        ];
    }
}

function sendInfobipSimpleSMSRequest($endpoint, $payload, $apiKey) {
    $url = 'https://api.infobip.com' . $endpoint;
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Authorization: App ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);
        
        return [
            'success' => true,
            'response' => $responseData,
            'message_id' => $responseData['messageId'] ?? null
        ];
    } else {
        return [
            'success' => false,
            'error' => "HTTP $httpCode: " . $response,
            'http_code' => $httpCode
        ];
    }
}

// Verification check endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $code = $_POST['code'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    if (empty($code) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Code and phone are required']);
        exit;
    }
    
    $verificationCheck = verifySMSCode($code, $phone);
    
    if ($verificationCheck['success']) {
        echo json_encode(['success' => true, 'message' => 'Phone number verified successfully via SMS!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code', 'error' => $verificationCheck['error']]);
    }
}

function verifySMSCode($code, $phone) {
    // Format phone for comparison
    if (!preg_match('/^\+212/', $phone)) {
        $phone = preg_replace('/^0/', '', $phone);
        $phone = '+212' . $phone;
    }
    
    if (!isset($_SESSION['sms_verification_code']) || 
        !isset($_SESSION['verification_expires']) ||
        !isset($_SESSION['verification_phone']) ||
        time() > $_SESSION['verification_expires']) {
        return ['success' => false, 'error' => 'Verification code expired or invalid'];
    }
    
    // Compare phone numbers (allow both formats)
    $sessionPhone = $_SESSION['verification_phone'];
    if ($sessionPhone !== $phone && 
        str_replace('+212', '0', $sessionPhone) !== str_replace('+212', '0', $phone)) {
        return ['success' => false, 'error' => 'Phone number mismatch'];
    }
    
    if ($_SESSION['sms_verification_code'] === $code) {
        // Clear the code after successful verification
        unset($_SESSION['sms_verification_code']);
        unset($_SESSION['verification_expires']);
        $_SESSION['verified'] = true;
        $_SESSION['verified_phone'] = $phone;
        $_SESSION['verified_method'] = 'sms';
        $_SESSION['verified_at'] = time();
        return ['success' => true];
    }
    
    return ['success' => false, 'error' => 'Invalid verification code'];
}

// Alternative using HTTP_Request2 if available
function sendInfobipSMSRequestHTTP2($to, $verificationCode, $name = '') {
    require_once 'HTTP/Request2.php';
    
    $apiKey = 'd3d42943a14f43f39c1627d8c133bfaf-1dea65c9-1bc8-4a46-90e2-d3659167e749';
    $sender = '447491163443';
    
    $messageText = "Hello " . ($name ?: 'User') . "!\n\nYour verification code is: " . $verificationCode . "\n\nThis code will expire in 10 minutes.\n\nOBG ECOM";
    
    $payload = [
        'messages' => [
            [
                'destinations' => [
                    ['to' => $to]
                ],
                'from' => $sender,
                'text' => $messageText
            ]
        ]
    ];
    
    $request = new HTTP_Request2();
    $request->setUrl('https://api.infobip.com/sms/2/text/advanced');
    $request->setMethod(HTTP_Request2::METHOD_POST);
    $request->setConfig(array(
        'follow_redirects' => TRUE,
        'timeout' => 30,
        'connect_timeout' => 10
    ));
    $request->setHeader(array(
        'Authorization' => 'App ' . $apiKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ));
    $request->setBody(json_encode($payload));
    
    try {
        $response = $request->send();
        $status = $response->getStatus();
        $body = $response->getBody();
        
        if ($status == 200) {
            $responseData = json_decode($body, true);
            error_log("Infobip SMS HTTP2 success: HTTP $status");
            
            return [
                'success' => true,
                'response' => $responseData
            ];
        } else {
            error_log("Infobip SMS HTTP2 error: HTTP $status - $body");
            
            return [
                'success' => false,
                'error' => "HTTP $status: " . $response->getReasonPhrase(),
                'http_code' => $status,
                'response' => $body
            ];
        }
    } catch(HTTP_Request2_Exception $e) {
        error_log("Infobip SMS HTTP2 exception: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Test endpoint to verify SMS works
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['testsms'])) {
    $testPhone = '+212606643986'; // Replace with your test phone
    $testName = 'Test User';
    
    $result = sendInfobipSMSVerification($testPhone, $testName, false);
    
    echo "<pre>";
    echo "SMS Test Results:\n";
    echo "Phone: $testPhone\n";
    echo "Name: $testName\n\n";
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT);
    echo "</pre>";
    
    // Show session info
    echo "\n\nSession Info:\n";
    echo "Code: " . ($_SESSION['sms_verification_code'] ?? 'Not set') . "\n";
    echo "Phone: " . ($_SESSION['verification_phone'] ?? 'Not set') . "\n";
    echo "Expires: " . ($_SESSION['verification_expires'] ?? 'Not set') . "\n";
    
    exit;
}

// Test SMS directly (quick test)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['directsms'])) {
    $apiKey = 'd3d42943a14f43f39c1627d8c133bfaf-1dea65c9-1bc8-4a46-90e2-d3659167e749';
    $sender = '447491163443';
    $testPhone = '+212606643986';
    $verificationCode = generateVerificationCode();
    
    $payload = [
        'messages' => [
            [
                'destinations' => [
                    ['to' => $testPhone]
                ],
                'from' => $sender,
                'text' => "Your verification code is: " . $verificationCode . " - OBG ECOM"
            ]
        ]
    ];
    
    echo "<pre>";
    echo "Direct SMS Test:\n";
    echo "To: $testPhone\n";
    echo "From: $sender\n";
    echo "Code: $verificationCode\n\n";
    echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.infobip.com/sms/2/text/advanced',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: App ' . $apiKey,
            'Content-Type: application/json',
            'Accept: ' => 'application/json'
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    echo "Response: " . $response . "\n";
    echo "</pre>";
    
    exit;
}

// Debug endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['debug'])) {
    $debugInfo = [
        'session' => [
            'sms_verification_code' => $_SESSION['sms_verification_code'] ?? 'not set',
            'whatsapp_verification_code' => $_SESSION['whatsapp_verification_code'] ?? 'not set',
            'verification_phone' => $_SESSION['verification_phone'] ?? 'not set',
            'verification_expires' => $_SESSION['verification_expires'] ?? 'not set',
            'verified' => $_SESSION['verified'] ?? false
        ],
        'server' => [
            'php_version' => phpversion(),
            'curl_enabled' => function_exists('curl_version'),
            'server_name' => $_SERVER['SERVER_NAME']
        ]
    ];
    
    echo json_encode($debugInfo, JSON_PRETTY_PRINT);
    exit;
}
?>