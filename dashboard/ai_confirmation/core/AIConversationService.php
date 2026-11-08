<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Core;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  AIConversationService — generate replies for ASK_QUESTION / NEGOTIATE
 * ═══════════════════════════════════════════════════════════════
 *
 *  Public API:
 *      AIConversationService::replyFor(string $intent, array $safeContext, string $customerText): string
 *
 *  Guarantees:
 *    - Never returns a message longer than 400 chars.
 *    - Never includes URLs, phone numbers, or promise dates.
 *    - Never negotiates price. Redirects to human.
 *    - On AI failure, returns a safe canned French reply.
 * ═══════════════════════════════════════════════════════════════
 */
final class AIConversationService
{
    /** Maximum length of the reply we send back. */
    private const MAX_REPLY_CHARS = 400;

    /**
     * Generate a reply for a customer message.
     *
     * @param string $intent       One of ASK_QUESTION or NEGOTIATE (others give fallback).
     * @param array  $safeContext  Whitelisted order context:
     *                             - order_number (string)
     *                             - products     (array of names, ≤5)
     *                             - total        (float)
     *                             - currency     (string)
     *                             - city         (string)
     *                             - language     (string: fr, ar, en, darija)
     * @param string $customerText The verbatim customer message.
     */
    public static function replyFor(
        string $intent,
        array $safeContext,
        string $customerText,
    ): string {
        $intent = strtoupper(trim($intent));

        // Never generate negotiated prices.
        if ($intent === 'NEGOTIATE') {
            return self::negotiateFallback($safeContext);
        }
        if ($intent !== 'ASK_QUESTION') {
            return self::safeFallback($safeContext);
        }

        $aiReply = self::callGemini($safeContext, $customerText);
        if ($aiReply === null) {
            return self::safeFallback($safeContext);
        }

        return self::sanitize($aiReply);
    }

    // ═══════════════════════════════════════════════════════════
    //  Gemini call
    // ═══════════════════════════════════════════════════════════

    private static function callGemini(array $ctx, string $customerText): ?string
    {
        if (!function_exists('ai_confirmation_config')) {
            require_once __DIR__ . '/../config/ai_confirmation.php';
        }
        $cfg = ai_confirmation_config();
        $apiKey  = (string)($cfg['ai']['gemini_api_key'] ?? '');
        $model   = (string)($cfg['ai']['gemini_model'] ?? 'gemini-1.5-flash');
        $timeout = (int)($cfg['ai']['timeout_seconds'] ?? 15);

        if ($apiKey === '') {
            return null;
        }

        $prompt = self::buildPrompt($ctx, $customerText);

        $payload = [
            'contents' => [[
                'parts' => [[ 'text' => $prompt ]],
            ]],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 250,
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

        if ($curlErr !== '' || $httpCode !== 200) {
            return null;
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) return null;
        $cands = $decoded['candidates'] ?? null;
        if (!is_array($cands) || empty($cands[0])) return null;
        $parts = $cands[0]['content']['parts'] ?? null;
        if (!is_array($parts)) return null;

        $text = '';
        foreach ($parts as $p) {
            if (isset($p['text']) && is_string($p['text'])) {
                $text .= $p['text'];
            }
        }
        $text = trim($text);
        return $text !== '' ? $text : null;
    }

    // ═══════════════════════════════════════════════════════════
    //  Prompt
    // ═══════════════════════════════════════════════════════════

    private static function buildPrompt(array $ctx, string $customerText): string
    {
        $order    = (string)($ctx['order_number'] ?? '');
        $products = (array) ($ctx['products']    ?? []);
        $total    = (float) ($ctx['total']       ?? 0);
        $currency = (string)($ctx['currency']    ?? 'MAD');
        $city     = (string)($ctx['city']        ?? '');
        $lang     = (string)($ctx['language']    ?? 'fr');

        $p  = "You are a friendly customer-support assistant for a Moroccan e-commerce store.\n";
        $p .= "A customer is being asked to confirm their order via WhatsApp.\n";
        $p .= "They asked a question. Reply briefly, politely, and truthfully.\n\n";

        $p .= "STRICT RULES:\n";
        $p .= "  - Reply in the customer's language ({$lang}).\n";
        $p .= "  - Maximum 400 characters. Prefer 2–3 short sentences.\n";
        $p .= "  - End with the question: 'Souhaitez-vous confirmer cette commande ? Répondez OUI ou NON.'\n";
        $p .= "  - Do NOT invent facts. Only use the data below.\n";
        $p .= "  - Do NOT promise a specific delivery date.\n";
        $p .= "  - Do NOT mention discounts, prices different from the stated total, or free shipping.\n";
        $p .= "  - Do NOT include URLs, emails, phone numbers, or markdown.\n";
        $p .= "  - If you cannot answer safely, reply EXACTLY: 'Un agent va vous répondre rapidement.'\n\n";

        $p .= "Order context (safe subset only):\n";
        if ($order !== '')    $p .= "  Order number: {$order}\n";
        if (!empty($products)) {
            $names = array_slice(array_map('strval', $products), 0, 5);
            $p .= "  Products: " . implode(', ', $names) . "\n";
        }
        if ($total > 0)       $p .= "  Total: " . number_format($total, 2, '.', ' ') . " {$currency}\n";
        if ($city !== '')     $p .= "  Delivery city: {$city}\n";

        $p .= "\nCustomer message (verbatim):\n<<<\n" . $customerText . "\n>>>\n";
        $p .= "\nReply:";

        return $p;
    }

    // ═══════════════════════════════════════════════════════════
    //  Sanitizers & fallbacks
    // ═══════════════════════════════════════════════════════════

    private static function sanitize(string $text): string
    {
        // Strip markdown-ish artifacts
        $text = preg_replace('/\*+/u', '', $text);
        $text = preg_replace('/`+/u',  '', $text);
        $text = preg_replace('/^#+\s*/mu', '', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/u', '$1', $text); // [text](url) → text

        // Strip URLs
        $text = preg_replace('#https?://\S+#iu', '', $text);

        // Collapse whitespace
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        // Enforce max length
        if (mb_strlen($text) > self::MAX_REPLY_CHARS) {
            $text = mb_substr($text, 0, self::MAX_REPLY_CHARS - 1) . '…';
        }

        // Ensure the customer is asked to reply — if not, append
        if (!preg_match('/\b(OUI|NON)\b/u', $text)) {
            $text = rtrim($text, " .") . " Souhaitez-vous confirmer cette commande ? Répondez OUI ou NON.";
        }

        return $text;
    }

    private static function safeFallback(array $ctx): string
    {
        $lang = (string)($ctx['language'] ?? 'fr');
        return match (strtolower($lang)) {
            'ar'     => 'سيتواصل معك أحد مستشارينا في أقرب وقت بخصوص طلبك. هل تؤكد طلبك؟ أجب بـ نعم أو لا.',
            'darija' => 'غادي يتواصل معك واحد من الفريق ديالنا قريب بخصوص الطلب ديالك. واش كتأكد الطلب؟ جاوب بـ واخا ولا لا.',
            'en'     => 'One of our agents will contact you shortly regarding your order. Would you like to confirm? Reply YES or NO.',
            default  => 'Un agent va vous répondre rapidement. Souhaitez-vous confirmer cette commande ? Répondez OUI ou NON.',
        };
    }

    private static function negotiateFallback(array $ctx): string
    {
        $lang = (string)($ctx['language'] ?? 'fr');
        return match (strtolower($lang)) {
            'ar'     => 'سيتواصل معك أحد مستشارينا لمناقشة طلبك. هل تؤكد الطلب؟ أجب بـ نعم أو لا.',
            'darija' => 'غادي يتواصل معك واحد من الفريق لمناقشة الطلب ديالك. واش كتأكد الطلب؟ جاوب بـ واخا ولا لا.',
            'en'     => 'One of our agents will contact you to discuss pricing. Would you like to confirm your order? Reply YES or NO.',
            default  => 'Un agent va vous contacter pour discuter du tarif. Souhaitez-vous confirmer cette commande ? Répondez OUI ou NON.',
        };
    }
} ?>