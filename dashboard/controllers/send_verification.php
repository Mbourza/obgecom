<?php
// dashboard/controllers/send_verification.php

session_start();
header('Content-Type: application/json');

// Load WhatsApp configuration
require_once('../config/whatsapp.php');

function generateVerificationCode() {
    return sprintf("%06d", mt_rand(1, 999999));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $phone = $data['phone'] ?? '';
    $name = $data['name'] ?? '';
    $method = $data['method'] ?? 'whatsapp'; // whatsapp or sms
    $isResend = $data['resend'] ?? false;

    // Validate phone number format
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Phone number is required']);
        exit;
    }

    // Format phone number for Morocco (add +212 if not present)
    if (!preg_match('/^\+212/', $phone)) {
        $phone = preg_replace('/^0/', '', $phone);
        $phone = '+212' . $phone;
    }

    // Validate Moroccan phone number format
    if (!preg_match('/^\+212[567]\d{8}$/', $phone)) {
        echo json_encode(['success' => false, 'message' => 'Invalid Moroccan phone number. Must start with +2125, +2126, or +2127']);
        exit;
    }

    // Send verification via selected method
    // FIX: arguments now match the function signature (to, name, isResend)
    if ($method === 'whatsapp') {
        $result = sendWhatsAppVerification($phone, $name, $isResend);
    } else {
        $result = sendSMSVerification($phone, $name, $isResend);
    }

    $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);

    if ($result['success']) {
        $response = [
            'success' => true,
            'message' => $isResend ? 'Verification code resent!' : 'Verification code sent!',
            'method' => $method,
            'verification_code' => $result['verification_code'] ?? ''
        ];

        // For debugging on localhost, show the code
        if ($isLocal) {
            $response['debug_code'] = $result['verification_code'] ?? '';
        }

        echo json_encode($response);
    } else {
        // The message that was masking real failures is now clearly separated
        // from an actual send failure, and the real error is surfaced (at least
        // on localhost) instead of being silently discarded.
        $response = [
            'success' => false,
            'message' => 'Failed to send verification message via WhatsApp. Please try again or use SMS.',
            'error' => $result['error'] ?? 'Unknown error'
        ];

        if ($isLocal) {
            $response['debug'] = $result; // full diagnostic payload, localhost only
        }

        echo json_encode($response);
    }
}

function sendWhatsAppVerification($to, $name = '', $isResend = false) {
    // Generate verification code
    $verificationCode = generateVerificationCode();

    // Store code in session for verification
    $_SESSION['whatsapp_verification_code'] = $verificationCode;
    $_SESSION['verification_phone'] = $to;
    $_SESSION['verification_expires'] = time() + 600; // 10 minutes
    $_SESSION['verification_method'] = 'whatsapp';

    // Prepare template payload for 360dialog
    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'template',
        'template' => [
            'name' => WHATSAPP_TEMPLATE_NAME,
            'language' => [
                'code' => 'en'
            ],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $verificationCode
                        ]
                    ]
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => '0',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $verificationCode
                        ]
                    ]
                ]
            ]
        ]
    ];

    $result = ['success' => false, 'error' => 'Request was never sent'];

    try {
        $result = send360DialogRequest($payload);

        if ($result['success']) {
            return [
                'success' => true,
                'verification_code' => $verificationCode,
                'method' => 'whatsapp_360dialog',
                'message_id' => $result['message_id'] ?? null
            ];
        } else {
            error_log("360dialog WhatsApp send failed: " . json_encode($result));
        }
    } catch (Exception $e) {
        error_log("360dialog WhatsApp exception: " . $e->getMessage());
        $result = ['success' => false, 'error' => $e->getMessage()];
    }

    return [
        'success' => false,
        'verification_code' => $verificationCode,
        'error' => $result['error'] ?? 'Unknown WhatsApp API error',
        'http_code' => $result['http_code'] ?? null,
        'raw_response' => $result['response'] ?? null
    ];
}

function sendSMSVerification($to, $name = '', $isResend = false) {
    // Generate verification code
    $verificationCode = generateVerificationCode();

    // Store code in session for verification
    $_SESSION['sms_verification_code'] = $verificationCode;
    $_SESSION['verification_phone'] = $to;
    $_SESSION['verification_expires'] = time() + 600;
    $_SESSION['verification_method'] = 'sms';

    // Simulate successful SMS sending
    return [
        'success' => true,
        'verification_code' => $verificationCode,
        'method' => 'sms_simulated'
    ];
}

function send360DialogRequest($payload) {
    // Check required constants are actually configured (not just non-empty)
    if (!defined('WHATSAPP_API_KEY') || empty(WHATSAPP_API_KEY)) {
        error_log("360dialog API Key is not configured");
        return [
            'success' => false,
            'error' => 'API Key not configured'
        ];
    }
    if (!defined('WHATSAPP_API_URL') || empty(WHATSAPP_API_URL)) {
        error_log("360dialog API URL is not configured");
        return [
            'success' => false,
            'error' => 'API URL not configured'
        ];
    }
    if (!defined('WHATSAPP_TEMPLATE_NAME') || empty(WHATSAPP_TEMPLATE_NAME)) {
        error_log("360dialog template name is not configured");
        return [
            'success' => false,
            'error' => 'Template name not configured'
        ];
    }

    $url = WHATSAPP_API_URL;

    $ch = curl_init();

    $headers = [
        'Content-Type: application/json',
        'D360-API-KEY: ' . WHATSAPP_API_KEY
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    // Log the request and response for debugging
    error_log("360dialog Request to: $url");
    error_log("Payload: " . json_encode($payload, JSON_UNESCAPED_UNICODE));
    error_log("Response HTTP Code: $httpCode");
    error_log("Response Body: " . $response);
    if ($curlError) {
        error_log("cURL Error: $curlError");
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);

        if (isset($responseData['messages'][0]['id'])) {
            return [
                'success' => true,
                'message_id' => $responseData['messages'][0]['id'],
                'response' => $responseData
            ];
        }

        return [
            'success' => true,
            'response' => $responseData
        ];
    }

    $errorMessage = "HTTP $httpCode";

    if ($response) {
        $responseData = json_decode($response, true);
        if (isset($responseData['error']['message'])) {
            $errorMessage .= " - " . $responseData['error']['message'];
        } elseif (isset($responseData['errors'][0]['title'])) {
            // 360dialog / Meta Cloud API sometimes nests errors this way
            $errorMessage .= " - " . $responseData['errors'][0]['title'];
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

// ============================================
// Verification check endpoint
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $code = $_POST['code'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $method = $_POST['method'] ?? 'whatsapp';

    if (empty($code) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Code and phone are required']);
        exit;
    }

    $verificationCheck = verifyCode($code, $phone, $method);

    if ($verificationCheck['success']) {
        echo json_encode(['success' => true, 'message' => 'Phone number verified successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => $verificationCheck['error'] ?? 'Invalid verification code']);
    }
}

function verifyCode($code, $phone, $method = 'whatsapp') {
    // Format phone for comparison
    if (!preg_match('/^\+212/', $phone)) {
        $phone = preg_replace('/^0/', '', $phone);
        $phone = '+212' . $phone;
    }

    $sessionKey = $method . '_verification_code';

    if (!isset($_SESSION[$sessionKey]) ||
        !isset($_SESSION['verification_expires']) ||
        !isset($_SESSION['verification_phone']) ||
        time() > $_SESSION['verification_expires']) {
        return ['success' => false, 'error' => 'Verification code expired or invalid'];
    }

    // Compare phone numbers
    $sessionPhone = $_SESSION['verification_phone'];
    if ($sessionPhone !== $phone &&
        str_replace('+212', '0', $sessionPhone) !== str_replace('+212', '0', $phone)) {
        return ['success' => false, 'error' => 'Phone number mismatch'];
    }

    if ($_SESSION[$sessionKey] === $code) {
        // Clear the code after successful verification
        unset($_SESSION[$sessionKey]);
        unset($_SESSION['verification_expires']);
        $_SESSION['phone_verified'] = true;
        $_SESSION['verified_phone'] = $phone;
        $_SESSION['verified_method'] = $method;
        $_SESSION['verified_at'] = time();
        return ['success' => true];
    }

    return ['success' => false, 'error' => 'Invalid verification code'];
}

// Test endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test_whatsapp'])) {
    $testPhone = '+212612345678'; // Replace with your test number

    $result = testWhatsAppConnection($testPhone);
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

function testWhatsAppConnection($phone) {
    $testCode = '123456';

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $phone,
        'type' => 'template',
        'template' => [
            'name' => WHATSAPP_TEMPLATE_NAME,
            'language' => [
                'code' => 'en'
            ],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $testCode
                        ]
                    ]
                ]
            ]
        ]
    ];

    if (defined('WHATSAPP_PHONE_NUMBER_ID') && !empty(WHATSAPP_PHONE_NUMBER_ID)) {
        $payload['from'] = WHATSAPP_PHONE_NUMBER_ID;
    }

    return send360DialogRequest($payload);
}

// Debug endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['debug_session'])) {
    echo json_encode([
        'session_data' => [
            'whatsapp_verification_code' => $_SESSION['whatsapp_verification_code'] ?? 'not set',
            'sms_verification_code' => $_SESSION['sms_verification_code'] ?? 'not set',
            'verification_phone' => $_SESSION['verification_phone'] ?? 'not set',
            'verification_expires' => $_SESSION['verification_expires'] ?? 'not set',
            'verification_method' => $_SESSION['verification_method'] ?? 'not set',
            'phone_verified' => $_SESSION['phone_verified'] ?? false,
            'verified_phone' => $_SESSION['verified_phone'] ?? 'not set',
            'verified_method' => $_SESSION['verified_method'] ?? 'not set'
        ],
        'current_time' => time(),
        'expires_in' => isset($_SESSION['verification_expires']) ?
            ($_SESSION['verification_expires'] - time()) . ' seconds' : 'unknown'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Clear session endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['clear_session'])) {
    unset($_SESSION['whatsapp_verification_code']);
    unset($_SESSION['sms_verification_code']);
    unset($_SESSION['verification_phone']);
    unset($_SESSION['verification_expires']);
    unset($_SESSION['verification_method']);
    unset($_SESSION['phone_verified']);
    unset($_SESSION['verified_phone']);
    unset($_SESSION['verified_at']);

    echo json_encode([
        'success' => true,
        'message' => 'Session cleared successfully'
    ]);
    exit;
}