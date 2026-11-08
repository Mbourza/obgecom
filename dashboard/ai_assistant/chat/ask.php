<?php
// ============================================
// OBGEcom AI Bot - Context-Aware Chatbot
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Start session for conversation context
session_start();

// API Configuration
$API_KEY = "AIzaSyAEkhAORAoVt8UYNMwkr9IkaqRUhOQnpuI";

// File paths
$storageFile = "./storage.txt";
$logFile = "./ai_logs.txt";
$cacheDir = "./cache/";
$cacheFile = $cacheDir . "ai_cache.json";

// Rate limiting
$RATE_LIMIT_PUBLIC = 20;
$RATE_LIMIT_AUTH = 50;

// Conversation memory (last 3 messages)
$MAX_CONVERSATION_HISTORY = 3;

function getConversationHistory() {
    $history = $_SESSION['chat_history'] ?? [];
    
    // Format history for prompt
    $formatted = "";
    foreach ($history as $item) {
        if (isset($item['user'])) {
            $formatted .= "User: " . $item['user'] . "\n";
        }
        if (isset($item['assistant'])) {
            $formatted .= "Assistant: " . $item['assistant'] . "\n";
        }
    }
    
    return $formatted;
}

function addToConversationHistory($userMessage, $assistantMessage) {
    if (!isset($_SESSION['chat_history'])) {
        $_SESSION['chat_history'] = [];
    }
    
    global $MAX_CONVERSATION_HISTORY;
    
    // Add new messages
    $_SESSION['chat_history'][] = [
        'user' => $userMessage,
        'assistant' => $assistantMessage,
        'timestamp' => time()
    ];
    
    // Keep only last N conversations
    if (count($_SESSION['chat_history']) > $MAX_CONVERSATION_HISTORY) {
        $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -$MAX_CONVERSATION_HISTORY);
    }
}

function clearConversationHistory() {
    unset($_SESSION['chat_history']);
}

function applyRateLimit($userId = null, $limit = 20, $timeWindow = 60) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = $userId ? "rate_limit_user_{$userId}" : "rate_limit_ip_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 1,
            'first_request' => time()
        ];
        return null;
    }
    
    $rateData = $_SESSION[$key];
    
    if (time() - $rateData['first_request'] > $timeWindow) {
        $_SESSION[$key] = [
            'count' => 1,
            'first_request' => time()
        ];
        return null;
    }
    
    if ($rateData['count'] >= $limit) {
        $waitTime = $timeWindow - (time() - $rateData['first_request']);
        return [
            'success' => false,
            'error' => "Rate limit exceeded. Please wait {$waitTime} seconds.",
            'retry_after' => $waitTime
        ];
    }
    
    $rateData['count']++;
    $_SESSION[$key] = $rateData;
    
    return null;
}

function validateInput($input) {
    if (empty($input)) {
        return ['valid' => false, 'error' => 'Message cannot be empty'];
    }
    
    $sanitized = trim($input);
    
    if (strlen($sanitized) > 2000) {
        return ['valid' => false, 'error' => 'Message is too long (max 2000 characters)'];
    }
    
    return [
        'valid' => true,
        'data' => htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8')
    ];
}

function ensureDirectory($dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

function getCachedResponse($question, $cacheDuration = 300) {
    global $cacheFile;
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    $content = @file_get_contents($cacheFile);
    if (!$content) return null;
    
    $cache = @json_decode($content, true);
    if (!$cache) return null;
    
    $hash = md5(strtolower(trim($question)));
    
    if (isset($cache[$hash])) {
        $item = $cache[$hash];
        if (time() - $item['timestamp'] < $cacheDuration) {
            return $item['response'];
        }
    }
    
    return null;
}

function cacheResponse($question, $response) {
    global $cacheFile;
    
    ensureDirectory(dirname($cacheFile));
    
    $cache = [];
    if (file_exists($cacheFile)) {
        $content = @file_get_contents($cacheFile);
        if ($content) {
            $cache = @json_decode($content, true) ?: [];
        }
    }
    
    $hash = md5(strtolower(trim($question)));
    $cache[$hash] = [
        'response' => $response,
        'timestamp' => time()
    ];
    
    if (count($cache) > 500) {
        $cache = array_slice($cache, -500, null, true);
    }
    
    @file_put_contents($cacheFile, json_encode($cache));
}

function logInteraction($question, $response, $userId = null, $error = null) {
    global $logFile;
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $userId,
        'question' => substr($question, 0, 200),
        'response_length' => strlen($response),
        'error' => $error
    ];
    
    @file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND);
}

function loadContext() {
    global $storageFile;
    
    if (!file_exists($storageFile)) {
        return null;
    }
    
    $context = @file_get_contents($storageFile);
    if (!$context || trim($context) === '') {
        return null;
    }
    
    $maxLength = 25000;
    if (strlen($context) > $maxLength) {
        // Keep the most important sections (look for headers)
        $lines = explode("\n", $context);
        $important = [];
        
        foreach ($lines as $line) {
            if (strpos($line, '##') === 0 || strpos($line, '# ') === 0) {
                $important[] = $line;
            } elseif (strpos($line, '**Q:') !== false || strpos($line, '**A:') !== false) {
                $important[] = $line;
            } elseif (count($important) < 100) { // Limit lines
                $important[] = $line;
            }
        }
        
        $context = implode("\n", array_slice($important, 0, 100));
    }
    
    return $context;
}

function generatePrompt($question, $context, $conversationHistory = "") {
    $hasContext = !empty($context);
    
    if ($hasContext) {
        return <<<PROMPT
You are the OBGecom chatbot assistant - a helpful, knowledgeable expert in e-commerce automation.

CRITICAL RULES:
1. You ARE the chatbot - use "I" and "we" naturally
2. MAINTAIN CONTEXT from previous messages in the conversation
3. FOLLOW UP on what the user just said
4. Ask RELEVANT FOLLOW-UP QUESTIONS when appropriate
5. Keep responses CONCISE but COMPLETE (2-5 sentences)
6. Use natural emojis (👋 ✅ 🎁 👉 📦 📱 📩 💰 ⚡)
7. NEVER say "according to documentation" - just answer naturally
8. If user gives numbers (like order volume), use that information to provide specific recommendations

CONVERSATION HISTORY (last few messages):
{$conversationHistory}

CURRENT USER MESSAGE:
{$question}

YOUR KNOWLEDGE BASE (use this info):
{$context}

EXAMPLES OF GOOD CONTEXT-AWARE RESPONSES:
Example 1 (follow-up):
User: "I have 800 orders per month"
Assistant: "Perfect! With 800 orders/month, our Professional plan would be ideal. It supports 501-2000 orders. Would you like me to share the pricing details?"

Example 2 (asking for clarification):
User: "Tell me about plans"
Assistant: "Sure! We have the free Starter Pack (100 orders total), then Basic, Professional, and Enterprise plans. What's your approximate monthly order volume so I can recommend the best fit?"

Example 3 (continuing conversation):
User: "Yes, show me pricing"
Assistant: "Great! Here's a quick comparison: Professional plan is $X/month for up to 2000 orders, includes multi-carrier support and advanced analytics. Would you like the detailed pricing page link?"

NOW RESPOND naturally to the current message, considering the conversation history:
PROMPT;
    } else {
        return <<<PROMPT
You are the OBGecom chatbot assistant.

Unfortunately, no documentation has been uploaded yet. Be helpful but honest about the limitations.

Respond naturally and offer to connect with human support.

Current message: {$question}
PROMPT;
    }
}

function callGeminiAPI($prompt, $apiKey) {
    if (empty($apiKey) || strpos($apiKey, 'AIza') !== 0) {
        throw new Exception("Invalid API key");
    }
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7, // Higher for more conversational
            "topP" => 0.95,
            "topK" => 40,
            "maxOutputTokens" => 1024,
            "candidateCount" => 1
        ],
        "safetySettings" => [
            ["category" => "HARM_CATEGORY_HARASSMENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"],
            ["category" => "HARM_CATEGORY_HATE_SPEECH", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"],
            ["category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"],
            ["category" => "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold" => "BLOCK_MEDIUM_AND_ABOVE"]
        ]
    ];
    
    $ch = curl_init();
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-001:generateContent?key=" . urlencode($apiKey);
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Connection error: " . $error);
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $errorData = @json_decode($response, true);
        if (isset($errorData['error']['message'])) {
            throw new Exception("API Error: " . $errorData['error']['message']);
        }
        throw new Exception("API returned HTTP {$httpCode}");
    }
    
    return $response;
}

function extractAnswer($apiResponse) {
    $data = json_decode($apiResponse, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid API response");
    }
    
    if (isset($data['error'])) {
        throw new Exception("API Error: " . ($data['error']['message'] ?? 'Unknown error'));
    }
    
    if (isset($data['promptFeedback']['blockReason'])) {
        return "I cannot answer this question due to content safety policies. Please rephrase your question.";
    }
    
    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("No answer in API response");
    }
    
    $answer = trim($data['candidates'][0]['content']['parts'][0]['text']);
    
    // Clean up excessive newlines
    $answer = preg_replace('/\n{3,}/', "\n\n", $answer);
    
    return $answer;
}

// ============================================
// MAIN EXECUTION
// ============================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No data received']);
    exit;
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Check for reset command
if (isset($data['reset']) && $data['reset'] === true) {
    clearConversationHistory();
    echo json_encode(['success' => true, 'message' => 'Conversation reset']);
    exit;
}

if (!isset($data['question'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Question is required']);
    exit;
}

$userId = $_SESSION['user']['id'] ?? null;

try {
    ensureDirectory($cacheDir);
    
    // Rate limiting
    $limit = $userId ? $RATE_LIMIT_AUTH : $RATE_LIMIT_PUBLIC;
    $rateLimit = applyRateLimit($userId, $limit, 60);
    if ($rateLimit) {
        http_response_code(429);
        echo json_encode($rateLimit);
        exit;
    }
    
    // Validate
    $validation = validateInput($data['question']);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $validation['error']]);
        exit;
    }
    
    $question = $validation['data'];
    
    // Get conversation history BEFORE generating response
    $conversationHistory = getConversationHistory();
    
    // Check cache (with context consideration)
    $cacheKey = $question . "|" . substr(md5($conversationHistory), 0, 8);
    $cached = getCachedResponse($cacheKey);
    
    if ($cached !== null) {
        // Still add to conversation history even for cached responses
        addToConversationHistory($question, $cached);
        logInteraction($question, $cached, $userId);
        
        echo json_encode([
            'success' => true,
            'answer' => $cached,
            'cached' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'history_count' => count($_SESSION['chat_history'] ?? [])
        ]);
        exit;
    }
    
    // Load context
    $context = loadContext();
    
    // Generate prompt WITH conversation history
    $prompt = generatePrompt($question, $context, $conversationHistory);
    
    // Call API
    $apiResponse = callGeminiAPI($prompt, $API_KEY);
    $answer = extractAnswer($apiResponse);
    
    // Add to conversation history
    addToConversationHistory($question, $answer);
    
    // Cache with context key
    cacheResponse($cacheKey, $answer);
    logInteraction($question, $answer, $userId);
    
    // Success
    echo json_encode([
        'success' => true,
        'answer' => $answer,
        'cached' => false,
        'timestamp' => date('Y-m-d H:i:s'),
        'history_count' => count($_SESSION['chat_history'] ?? [])
    ]);
    
} catch (Exception $e) {
    error_log("AI Error: " . $e->getMessage());
    logInteraction($question ?? 'unknown', '', $userId, $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Sorry, I encountered an error. Please try again.',
        'debug' => [
            'message' => $e->getMessage()
        ]
    ]);
}
?>