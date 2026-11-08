<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\WhatsApp;

use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  WhatsAppService — 360dialog WABA v2 sender
 * ═══════════════════════════════════════════════════════════════
 *
 *  Sends text and template messages via 360dialog WABA v2.
 *  Logs every outbound to whatsapp_messages.
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Reads credentials from constants defined in config/whatsapp.php:
 *      WHATSAPP_API_URL          https://waba-v2.360dialog.io/messages
 *      WHATSAPP_API_KEY          (bearer key, sent as D360-API-KEY header)
 *      WHATSAPP_PHONE_NUMBER_ID  (Meta phone number id — included in payload)
 *      WHATSAPP_FROM_NUMBER      (E.164 without +, e.g. 212669335322)
 *
 *  Public API:
 *      sendText($to, $text, $context)                          → array
 *      sendTemplate($to, $template, $params, $lang, $context)  → array
 *      sendConfirmation($to, $order, $storeCfg)                → array
 *      sendReminder($to, $order, $storeCfg)                    → array
 *      sendReply($to, $text, $context)                         → array
 *
 *  Every method returns:
 *      [
 *        'ok'                   => bool,
 *        'provider_message_id'  => ?string,
 *        'http_code'            => int,
 *        'error'                => ?string,
 *        'raw'                  => array (parsed provider response),
 *      ]
 *
 *  Timeouts: 15s connect + transfer.
 *  Retries:  one automatic retry on network errors and 5xx.
 *            Never retried on 4xx (bad request / auth / template).
 * ═══════════════════════════════════════════════════════════════
 */
final class WhatsAppService
{
    private \DB $db;
    private string $apiUrl;
    private string $apiKey;
    private string $phoneNumberId;
    private string $fromNumber;
    private string $logFile;
    private string $logLevel;

    /** Max curl timeout in seconds. */
    private const HTTP_TIMEOUT = 15;

    /** Max total attempts (1 initial + 1 retry). */
    private const MAX_ATTEMPTS = 2;

    public function __construct(\DB $db)
    {
        $this->db = $db;

        if (!function_exists('ai_confirmation_config')) {
            require_once __DIR__ . '/../config/ai_confirmation.php';
        }
        $cfg = ai_confirmation_config();

        // Prefer explicit constants from config/whatsapp.php
        $this->apiUrl        = defined('WHATSAPP_API_URL')
            ? (string)constant('WHATSAPP_API_URL')
            : (string)($cfg['whatsapp']['api_url'] ?? '');

        $this->apiKey        = defined('WHATSAPP_API_KEY')
            ? (string)constant('WHATSAPP_API_KEY')
            : (string)($cfg['whatsapp']['api_key'] ?? '');

        $this->phoneNumberId = defined('WHATSAPP_PHONE_NUMBER_ID')
            ? (string)constant('WHATSAPP_PHONE_NUMBER_ID')
            : (string)($cfg['whatsapp']['phone_number_id'] ?? '');

        $this->fromNumber    = defined('WHATSAPP_FROM_NUMBER')
            ? (string)constant('WHATSAPP_FROM_NUMBER')
            : '';

        $this->logFile  = (string)($cfg['log_file'] ?? '');
        $this->logLevel = (string)($cfg['log']['level'] ?? 'info');

        if ($this->apiUrl === '' || $this->apiKey === '' || $this->phoneNumberId === '') {
            throw new \RuntimeException(
                'WhatsAppService: missing credentials. '
                . 'Ensure config/whatsapp.php defines WHATSAPP_API_URL, '
                . 'WHATSAPP_API_KEY, and WHATSAPP_PHONE_NUMBER_ID.'
            );
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  PUBLIC SEND METHODS
    // ═══════════════════════════════════════════════════════════

    /**
     * Send a free-form text message.
     *
     * Works only if the customer messaged you within the last 24h
     * (Meta's session rule). Otherwise use sendTemplate().
     *
     * @param array<string,mixed> $context Optional keys: order_id, user_id, store_id, type
     * @return array{ok:bool, provider_message_id:?string, http_code:int, error:?string, raw:array}
     */
    public function sendText(string $to, string $text, array $context = []): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->normalizePhone($to),
            'type'              => 'text',
            'text' => [
                'preview_url' => false,
                'body'        => $text,
            ],
        ];

        return $this->dispatch($payload, [
            'type'    => $context['type'] ?? 'CONFIRMATION',
            'message' => $text,
            'to'      => $to,
        ] + $context);
    }

    /**
     * Send a template message.
     *
     * @param array<int,array{type:string,text?:string,image?:array,parameters?:array}> $params
     * @return array{ok:bool, provider_message_id:?string, http_code:int, error:?string, raw:array}
     */
    public function sendTemplate(
        string $to,
        string $template,
        array $params = [],
        string $lang = 'fr',
        array $context = [],
    ): array {
        // Meta expects components: [{type:'body', parameters:[...]}]
        $components = [];
        if (!empty($params)) {
            $components[] = [
                'type'       => 'body',
                'parameters' => $params,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->normalizePhone($to),
            'type'              => 'template',
            'template' => [
                'name'     => $template,
                'language' => ['code' => $lang],
                'components' => $components,
            ],
        ];

        return $this->dispatch($payload, [
            'type'     => $context['type'] ?? 'CONFIRMATION',
            'message'  => '[template:' . $template . ']',
            'to'       => $to,
            'template' => $template,
            'lang'     => $lang,
            'params'   => $params,
        ] + $context);
    }

    /**
     * Convenience for the initial confirmation message.
     *
     * Chooses text or template based on config.
     * Default = text (per your choice).
     */
    public function sendConfirmation(string $to, array $order, array $storeCfg): array
    {
        $templateName = $storeCfg['whatsapp_template_name']
            ?? (defined('WHATSAPP_TEMPLATE_AI_CONFIRMATION')
                ? (string)constant('WHATSAPP_TEMPLATE_AI_CONFIRMATION')
                : null);

        $useTemplate = !empty($storeCfg['use_templates']);

        if ($useTemplate && $templateName) {
            $params = [
                ['type' => 'text', 'text' => (string)($order['customer_name'] ?? '')],
                ['type' => 'text', 'text' => (string)($order['order_number'] ?? '')],
                ['type' => 'text', 'text' => $this->formatAmount(
                    (float)($order['total_amount'] ?? 0),
                    (string)($order['currency'] ?? 'MAD')
                )],
            ];

            return $this->sendTemplate(
                $to,
                (string)$templateName,
                $params,
                (string)($storeCfg['whatsapp_language'] ?? 'fr'),
                [
                    'order_id' => $order['id']       ?? null,
                    'user_id'  => $order['user_id']  ?? null,
                    'store_id' => $order['store_id'] ?? null,
                    'type'     => 'CONFIRMATION',
                ]
            );
        }

        // Free-text fallback
        $text = $this->buildTextConfirmation($order);
        return $this->sendText($to, $text, [
            'order_id' => $order['id']       ?? null,
            'user_id'  => $order['user_id']  ?? null,
            'store_id' => $order['store_id'] ?? null,
            'type'     => 'CONFIRMATION',
        ]);
    }

    /**
     * Send a reminder / second-chance message.
     */
    public function sendReminder(string $to, array $order, array $storeCfg): array
    {
        $templateName = defined('WHATSAPP_TEMPLATE_AI_RETRY')
            ? (string)constant('WHATSAPP_TEMPLATE_AI_RETRY')
            : null;

        $useTemplate = !empty($storeCfg['use_templates']);

        if ($useTemplate && $templateName) {
            $params = [
                ['type' => 'text', 'text' => (string)($order['customer_name'] ?? '')],
                ['type' => 'text', 'text' => (string)($order['order_number'] ?? '')],
            ];
            return $this->sendTemplate(
                $to,
                (string)$templateName,
                $params,
                (string)($storeCfg['whatsapp_language'] ?? 'fr'),
                [
                    'order_id' => $order['id']       ?? null,
                    'user_id'  => $order['user_id']  ?? null,
                    'store_id' => $order['store_id'] ?? null,
                    'type'     => 'REMINDER',
                ]
            );
        }

        $name   = trim((string)($order['customer_name'] ?? '')) ?: 'cher client';
        $number = (string)($order['order_number'] ?? ('#' . ($order['id'] ?? '')));

        $text = "Bonjour {$name},\n\n"
              . "Nous n'avons pas encore reçu votre confirmation pour la commande {$number}.\n\n"
              . "Merci de répondre *OUI* pour confirmer, ou *NON* pour annuler.";

        return $this->sendText($to, $text, [
            'order_id' => $order['id']       ?? null,
            'user_id'  => $order['user_id']  ?? null,
            'store_id' => $order['store_id'] ?? null,
            'type'     => 'REMINDER',
        ]);
    }

    /**
     * Send an agent-style free-text reply (used for AI conversation).
     */
    public function sendReply(string $to, string $text, array $context = []): array
    {
        $context['type'] = $context['type'] ?? 'AI_REPLY';
        return $this->sendText($to, $text, $context);
    }

    // ═══════════════════════════════════════════════════════════
    //  INTERNALS
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool, provider_message_id:?string, http_code:int, error:?string, raw:array}
     */
    private function dispatch(array $payload, array $context): array
    {
        $attempt = 0;
        $lastResult = [
            'ok'                  => false,
            'provider_message_id' => null,
            'http_code'           => 0,
            'error'               => 'not attempted',
            'raw'                 => [],
        ];

        while ($attempt < self::MAX_ATTEMPTS) {
            $attempt++;
            $result = $this->httpPost($payload);
            $lastResult = $result;

            if ($result['ok']) {
                break;
            }

            $httpCode = $result['http_code'];

            // Retry only on network errors or 5xx
            $isRetryable = ($httpCode === 0) || ($httpCode >= 500 && $httpCode < 600);
            if (!$isRetryable) {
                break;
            }

            usleep(500000); // 500ms backoff
        }

        // Log outbound message
        $this->logOutbound($payload, $context, $lastResult);

        return $lastResult;
    }

    /**
     * @return array{ok:bool, provider_message_id:?string, http_code:int, error:?string, raw:array}
     */
    private function httpPost(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [
                'ok' => false, 'provider_message_id' => null, 'http_code' => 0,
                'error' => 'json_encode failed', 'raw' => [],
            ];
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'D360-API-KEY: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlErrNo !== 0) {
            return [
                'ok' => false, 'provider_message_id' => null, 'http_code' => $httpCode,
                'error' => 'curl: ' . $curlError, 'raw' => [],
            ];
        }

        $decoded = is_string($response) ? json_decode($response, true) : null;
        $raw = is_array($decoded) ? $decoded : [];

        // 360dialog success response shape:
        // { "messages": [ { "id": "wamid.XXXX" } ], "contacts": [...] }
        if ($httpCode === 200 && isset($raw['messages'][0]['id'])) {
            return [
                'ok' => true,
                'provider_message_id' => (string)$raw['messages'][0]['id'],
                'http_code' => 200,
                'error' => null,
                'raw' => $raw,
            ];
        }

        // Error path
        $errMsg = 'HTTP ' . $httpCode;
        if (is_array($raw)) {
            if (isset($raw['error']['message'])) {
                $errMsg .= ' — ' . $raw['error']['message'];
            } elseif (isset($raw['message'])) {
                $errMsg .= ' — ' . $raw['message'];
            }
        } elseif (is_string($response) && $response !== '') {
            $errMsg .= ' — ' . substr($response, 0, 300);
        }

        return [
            'ok' => false,
            'provider_message_id' => null,
            'http_code' => $httpCode,
            'error' => $errMsg,
            'raw' => $raw,
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Logging to whatsapp_messages
    // ─────────────────────────────────────────────────────────

    private function logOutbound(array $payload, array $context, array $result): void
    {
        try {
            $userId  = (int)($context['user_id']  ?? 0);
            $storeId = (int)($context['store_id'] ?? 0);
            $orderId = isset($context['order_id']) && $context['order_id'] !== null
                ? (int)$context['order_id']
                : 0;

            // Skip insert if we lack tenant info — nothing to attach to
            if ($userId <= 0 || $storeId <= 0) {
                return;
            }

            $to = (string)($context['to'] ?? $payload['to'] ?? '');
            $messageText = (string)($context['message'] ?? '');
            $messageType = (string)($context['type'] ?? 'CONFIRMATION');
            $status      = $result['ok'] ? 'sent' : 'failed';

            // NULLIF(?, 0) converts our sentinel 0 back to SQL NULL for
            // order_id, because DB::insert_this() binds everything as
            // PARAM_STR and would turn PHP null into an empty string.
            $this->db->insert_this(
                "INSERT INTO whatsapp_messages
                    (user_id, store_id, order_id,
                     customer_phone, direction, message_type,
                     message, provider, provider_message_id,
                     status, created_at, updated_at)
                 VALUES
                    (?, ?, NULLIF(?, 0),
                     ?, 'OUTBOUND', ?,
                     ?, '360dialog', ?,
                     ?, NOW(), NOW())",
                [
                    $userId,
                    $storeId,
                    $orderId,
                    $this->normalizePhone($to),
                    $this->mapToEnum($messageType),
                    $messageText,
                    $result['provider_message_id'],
                    $status,
                ]
            );
        } catch (Throwable $e) {
            // Never fail the send because logging failed.
            $this->log('warning', 'log_outbound.failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map our internal type to the enum defined in whatsapp_messages.
     */
    private function mapToEnum(string $type): string
    {
        $allowed = ['CONFIRMATION','REMINDER','CUSTOMER_REPLY','AI_REPLY','AGENT_REPLY','SYSTEM'];
        return in_array($type, $allowed, true) ? $type : 'SYSTEM';
    }

    // ─────────────────────────────────────────────────────────
    //  Phone & format helpers
    // ─────────────────────────────────────────────────────────

    /**
     * Normalize to E.164 without leading +.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        // Morocco: 0XXXXXXXXX → 212XXXXXXXXX
        if (strlen($digits) === 10 && $digits[0] === '0') {
            $digits = '212' . substr($digits, 1);
        }
        // 9-digit Moroccan mobile → 212 + 9 digits
        elseif (strlen($digits) === 9) {
            $digits = '212' . $digits;
        }

        return $digits;
    }

    private function formatAmount(float $amount, string $currency): string
    {
        return number_format($amount, 2, '.', ' ') . ' ' . $currency;
    }

    private function buildTextConfirmation(array $order): string
    {
        $name    = trim((string)($order['customer_name'] ?? '')) ?: 'cher client';
        $number  = (string)($order['order_number'] ?? ('#' . ($order['id'] ?? '')));
        $total   = $this->formatAmount(
            (float)($order['total_amount'] ?? 0),
            (string)($order['currency'] ?? 'MAD')
        );
        $city    = trim((string)($order['customer_ville'] ?? ''));

        $msg  = "Bonjour {$name},\n\n";
        $msg .= "Votre commande {$number} d'un montant de {$total}";
        if ($city !== '') {
            $msg .= " pour livraison à {$city}";
        }
        $msg .= " est en attente de confirmation.\n\n";
        $msg .= "Répondez *OUI* pour confirmer la livraison, ou *NON* pour annuler.";

        return $msg;
    }

    // ─────────────────────────────────────────────────────────
    //  Structured logger
    // ─────────────────────────────────────────────────────────

    private function log(string $level, string $event, array $ctx = []): void
    {
        $levels = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40];
        $min = $levels[strtolower($this->logLevel)] ?? 20;
        if (($levels[$level] ?? 0) < $min) {
            return;
        }
        $line = json_encode([
            'ts'    => gmdate('c'),
            'src'   => 'WhatsAppService',
            'level' => $level,
            'event' => $event,
            'ctx'   => $ctx,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($this->logFile !== '') {
            @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
} ?>