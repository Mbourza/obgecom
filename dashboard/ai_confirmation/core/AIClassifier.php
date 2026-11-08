<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Core;

use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  AIClassifier — deterministic rules + Gemini fallback
 * ═══════════════════════════════════════════════════════════════
 *
 *  Public API:
 *      AIClassifier::classify(string $text, array $context = []): array
 *
 *  Stage 1 — rules (regex). Free. Covers:
 *      French:   oui, non, ok, d'accord, je confirme, confirmer,
 *                j'annule, annuler, non merci
 *      English:  yes, no, ok, confirm, cancel
 *      Arabic:   نعم, لا, ملغي
 *      Darija:   واخا, واه, لا, ماشي, صافي
 *
 *  Stage 2 — Gemini. Only when rules cannot decide.
 *      Model default: gemini-1.5-flash
 *      Timeout: 15s
 *      Output: strict JSON
 *
 *  Never throws. Never blocks longer than the configured timeout.
 * ═══════════════════════════════════════════════════════════════
 */
final class AIClassifier
{
    /** Allowed intent values. Anything else is coerced to AGENT_REQUIRED. */
    public const ALLOWED_INTENTS = [
        'CONFIRM',
        'CANCEL',
        'MODIFY_ORDER',
        'ASK_QUESTION',
        'NEGOTIATE',
        'UNKNOWN',
        'AGENT_REQUIRED',
    ];

    /** Max characters we send to Gemini. Longer inputs are truncated. */
    private const MAX_INPUT_CHARS = 1000;

    /**
     * Classify an inbound message.
     *
     * @param array{
     *   last_intent?:?string,
     *   order_number?:?string,
     *   customer_language?:?string
     * } $context
     *
     * @return array{
     *   intent:string, confidence:float, reason:string,
     *   source:string, requires_agent:bool, raw:array
     * }
     */
    public static function classify(string $text, array $context = []): array
    {
        $text = self::cleanText($text);

        // ── Stage 1 — deterministic rules ─────────────────────
        $ruleResult = self::classifyByRules($text, $context);
        if ($ruleResult !== null) {
            return $ruleResult;
        }

        // ── Stage 2 — Gemini fallback ─────────────────────────
        $aiResult = self::classifyWithGemini($text, $context);
        if ($aiResult !== null) {
            return $aiResult;
        }

        // ── Fallback ──────────────────────────────────────────
        return self::fallback(
            'AI call failed or returned invalid output',
            ['text_length' => strlen($text)]
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  Stage 1 — Deterministic rules
    // ═══════════════════════════════════════════════════════════

    private static function classifyByRules(string $text, array $context): ?array
    {
        $norm = self::normalize($text);

        // Very short and clearly affirmative → confirm
        $confirmPatterns = [
            '/^(oui|ouais|ouaip|yep|yes|yeah|ok|okay|d\'?accord|daccord)[!\.\s]*$/iu',
            '/^(je\s+confirme|confirme|confirmer|je\s+valide|valider|validé|validé[.!]*)[!\.\s]*$/iu',
            '/^(نعم|أكد|موافق|تمام)[!\.\s،]*$/u',
            '/^(واخا|واه|واخّا|صافي|مزيان)[!\.\s،]*$/u',
        ];
        foreach ($confirmPatterns as $rx) {
            if (preg_match($rx, $norm)) {
                return self::makeResult(
                    'CONFIRM', 0.98, 'Customer clearly confirmed (rule)',
                    'rule', false
                );
            }
        }

        // Very short and clearly negative → cancel
        $cancelPatterns = [
            '/^(non|no|nope|nah|nan)[!\.\s]*$/iu',
            '/^(j\'?annule|annule|annuler|je\s+refuse|refuse|refuser)[!\.\s]*$/iu',
            '/^(non\s+merci|pas\s+intéressé[e]?|pas\s+interesse[e]?)[!\.\s]*$/iu',
            '/^(لا|ملغي|ألغ|رفض)[!\.\s،]*$/u',
            '/^(لا\s+شكرا|ماشي|ما\s+بغيتش)[!\.\s،]*$/u',
        ];
        foreach ($cancelPatterns as $rx) {
            if (preg_match($rx, $norm)) {
                return self::makeResult(
                    'CANCEL', 0.98, 'Customer clearly cancelled (rule)',
                    'rule', false
                );
            }
        }

        // Messages that are clearly questions (start with question words or end with ?)
        if (preg_match('/\?\s*$/u', $norm)) {
            // Not enough to decide intent, but we know it's a question.
            // Let AI handle, unless there's no AI configured.
        }

        // Rule: no match.
        return null;
    }

    // ═══════════════════════════════════════════════════════════
    //  Stage 2 — Gemini
    // ═══════════════════════════════════════════════════════════

    private static function classifyWithGemini(string $text, array $context): ?array
    {
        if (!function_exists('ai_confirmation_config')) {
            require_once __DIR__ . '/../config/ai_confirmation.php';
        }
        $cfg = ai_confirmation_config();
        $apiKey = (string)($cfg['ai']['gemini_api_key'] ?? '');
        $model  = (string)($cfg['ai']['gemini_model'] ?? 'gemini-1.5-flash');
        $timeout = (int)($cfg['ai']['timeout_seconds'] ?? 15);

        if ($apiKey === '') {
            return self::fallback('Gemini API key not configured');
        }

        // Truncate for safety
        if (mb_strlen($text) > self::MAX_INPUT_CHARS) {
            $text = mb_substr($text, 0, self::MAX_INPUT_CHARS) . '…';
        }

        $prompt = self::buildPrompt($text, $context);

        $payload = [
            'contents' => [[
                'parts' => [[ 'text' => $prompt ]],
            ]],
            'generationConfig' => [
                'temperature'      => 0.0,
                'maxOutputTokens'  => 300,
                'responseMimeType' => 'application/json',
                'responseSchema'   => [
                    'type'       => 'OBJECT',
                    'properties' => [
                        'intent' => [
                            'type' => 'STRING',
                            'enum' => self::ALLOWED_INTENTS,
                        ],
                        'confidence' => [
                            'type'             => 'NUMBER',
                            'minimum'          => 0.0,
                            'maximum'          => 1.0,
                        ],
                        'reason' => ['type' => 'STRING'],
                    ],
                    'required' => ['intent', 'confidence', 'reason'],
                ],
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT',         'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH',        'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',  'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',  'threshold' => 'BLOCK_ONLY_HIGH'],
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr !== '') {
            return self::fallback('Gemini curl error: ' . $curlErr);
        }
        if ($httpCode !== 200) {
            return self::fallback('Gemini HTTP ' . $httpCode, [
                'body_excerpt' => is_string($response) ? substr($response, 0, 300) : null,
            ]);
        }

        $decoded = json_decode((string)$response, true);
        $aiJson = self::extractJsonFromGeminiResponse($decoded);
        if (!is_array($aiJson)) {
            return self::fallback('Gemini returned unparseable JSON', [
                'body_excerpt' => substr((string)$response, 0, 300),
            ]);
        }

        return self::validateAndCoerce($aiJson, 'ai', $decoded);
    }

    /**
     * Gemini's response shape:
     *   { candidates: [ { content: { parts: [ { text: "..." } ] } } ] }
     * The `text` field is a JSON string (because we asked for JSON mode).
     */
    private static function extractJsonFromGeminiResponse(?array $decoded): ?array
    {
        if (!is_array($decoded)) return null;
        $cands = $decoded['candidates'] ?? null;
        if (!is_array($cands) || empty($cands[0])) return null;
        $parts = $cands[0]['content']['parts'] ?? null;
        if (!is_array($parts)) return null;

        // Concatenate all text parts (usually just one)
        $text = '';
        foreach ($parts as $p) {
            if (isset($p['text']) && is_string($p['text'])) {
                $text .= $p['text'];
            }
        }
        $text = trim($text);
        if ($text === '') return null;

        // Strip accidental markdown fences if present
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $json = json_decode($text, true);
        return is_array($json) ? $json : null;
    }

    // ═══════════════════════════════════════════════════════════
    //  Prompt
    // ═══════════════════════════════════════════════════════════

    private static function buildPrompt(string $text, array $context): string
    {
        $lastIntent = (string)($context['last_intent'] ?? '');
        $lang       = (string)($context['customer_language'] ?? '');

        $prompt  = "You are an intent classifier for a WhatsApp order-confirmation bot.\n";
        $prompt .= "A customer received a message asking them to confirm or cancel their order.\n";
        $prompt .= "Classify the customer's reply into EXACTLY ONE of these intents:\n";
        $prompt .= "  - CONFIRM        The customer confirms the order\n";
        $prompt .= "  - CANCEL         The customer cancels the order\n";
        $prompt .= "  - MODIFY_ORDER   The customer wants to change something (address, quantity, product)\n";
        $prompt .= "  - ASK_QUESTION   The customer asks a question (delivery time, price, etc.)\n";
        $prompt .= "  - NEGOTIATE      The customer asks for a discount, better price, free shipping\n";
        $prompt .= "  - UNKNOWN        You cannot understand the message\n";
        $prompt .= "  - AGENT_REQUIRED The message is ambiguous, contains multiple intents, or\n";
        $prompt .= "                   clearly needs a human (complaint, legal issue, etc.)\n\n";

        if ($lang !== '') {
            $prompt .= "Likely customer language: {$lang}.\n";
        }
        if ($lastIntent !== '') {
            $prompt .= "Note: the customer's previous intent in this conversation was '{$lastIntent}'.\n";
        }

        $prompt .= "\nRules:\n";
        $prompt .= "  - Confidence is a number between 0 and 1.\n";
        $prompt .= "  - Use HIGH confidence (>= 0.9) only if you are sure.\n";
        $prompt .= "  - If the message is short but ambiguous, use AGENT_REQUIRED.\n";
        $prompt .= "  - Never invent intent that isn't in the list.\n";
        $prompt .= "  - Never include explanations outside the JSON response.\n\n";

        $prompt .= "Customer message (verbatim, between <<< >>>):\n";
        $prompt .= "<<<\n" . $text . "\n>>>\n";

        return $prompt;
    }

    // ═══════════════════════════════════════════════════════════
    //  Validation & results
    // ═══════════════════════════════════════════════════════════

    private static function validateAndCoerce(array $raw, string $source, ?array $full = null): array
    {
        $intent = strtoupper(trim((string)($raw['intent'] ?? '')));
        $conf   = $raw['confidence'] ?? null;
        $reason = trim((string)($raw['reason'] ?? ''));

        if (!in_array($intent, self::ALLOWED_INTENTS, true)) {
            return self::fallback(
                'AI returned unknown intent: ' . ($intent ?: '(empty)'),
                ['raw' => $raw]
            );
        }
        if (!is_numeric($conf)) {
            return self::fallback('AI returned non-numeric confidence', ['raw' => $raw]);
        }
        $conf = (float)$conf;
        if ($conf < 0.0) $conf = 0.0;
        if ($conf > 1.0) $conf = 1.0;

        $requiresAgent = in_array($intent, ['UNKNOWN', 'AGENT_REQUIRED'], true);

        return self::makeResult($intent, $conf, $reason, $source, $requiresAgent, $full ?? []);
    }

    private static function makeResult(
        string $intent,
        float $confidence,
        string $reason,
        string $source,
        bool $requiresAgent,
        array $raw = [],
    ): array {
        return [
            'intent'         => $intent,
            'confidence'     => round($confidence, 3),
            'reason'         => $reason,
            'source'         => $source,
            'requires_agent' => $requiresAgent,
            'raw'            => $raw,
        ];
    }

    private static function fallback(string $why, array $extra = []): array
    {
        return [
            'intent'         => 'AGENT_REQUIRED',
            'confidence'     => 0.0,
            'reason'         => 'classifier fallback: ' . $why,
            'source'         => 'fallback',
            'requires_agent' => true,
            'raw'            => $extra,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  Text utilities
    // ═══════════════════════════════════════════════════════════

    private static function cleanText(string $text): string
    {
        // Strip control chars, normalize whitespace, trim
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private static function normalize(string $text): string
    {
        // Lowercase + strip diacritics (é → e) so patterns match consistently
        $text = mb_strtolower($text, 'UTF-8');
        if (function_exists('iconv')) {
            $clean = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if (is_string($clean) && $clean !== '') {
                $text = $clean;
            }
        }
        return $text;
    }
} ?>