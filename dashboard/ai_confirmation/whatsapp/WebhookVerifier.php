<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\WhatsApp;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  WebhookVerifier — authenticate inbound webhook requests
 * ═══════════════════════════════════════════════════════════════
 *
 *  Supports four modes, chosen by config:
 *
 *    - hmac    HMAC-SHA256 of raw body, sent in a header
 *              (e.g. X-Hub-Signature-256: sha256=abcdef...)
 *
 *    - header  Shared secret in a custom header
 *              (e.g. X-Webhook-Secret: <secret>)
 *
 *    - query   Shared secret in a query parameter
 *              (e.g. /webhook.php?secret=<secret>)
 *
 *    - none    No verification. Logs a warning per request.
 *
 *  Configuration (.env):
 *      WHATSAPP_WEBHOOK_MODE       hmac | header | query | none
 *      WHATSAPP_WEBHOOK_SECRET     the shared secret
 *      WHATSAPP_WEBHOOK_HEADER     (optional) header name, default X-Webhook-Secret
 *      WHATSAPP_WEBHOOK_QUERY_KEY  (optional) query param name, default secret
 *
 *  Usage:
 *      $ok = WebhookVerifier::verify($rawBody, $cfg);
 *      if (!$ok) { http_response_code(401); exit; }
 * ═══════════════════════════════════════════════════════════════
 */
final class WebhookVerifier
{
    /** Default header name when mode=header. */
    public const DEFAULT_HEADER = 'X-Webhook-Secret';

    /** Default query param name when mode=query. */
    public const DEFAULT_QUERY_KEY = 'secret';

    /** Header that carries HMAC signature (mode=hmac). */
    public const HMAC_HEADER = 'X-Hub-Signature-256';

    /**
     * Verify an incoming webhook request.
     *
     * @param string $rawBody  The raw request body (php://input).
     * @param array  $cfg      The full config array from ai_confirmation_config().
     * @param array  $headers  Optional. If null, read from $_SERVER.
     * @param array  $query    Optional. If null, read from $_GET.
     */
    public static function verify(
        string $rawBody,
        array $cfg,
        ?array $headers = null,
        ?array $query = null,
    ): bool {
        $mode   = strtolower((string)($cfg['whatsapp']['webhook_mode'] ?? ''));
        $secret = (string)($cfg['whatsapp']['webhook_secret'] ?? '');

        // If mode not set, auto-pick: header if secret exists, else none.
        if ($mode === '') {
            $mode = $secret !== '' ? 'header' : 'none';
        }

        $headers = $headers ?? self::collectHeaders();
        $query   = $query   ?? ($_GET ?? []);

        switch ($mode) {
            case 'hmac':
                return self::verifyHmac($rawBody, $secret, $headers, $cfg);
            case 'header':
                return self::verifyHeader($secret, $headers, $cfg);
            case 'query':
                return self::verifyQuery($secret, $query, $cfg);
            case 'none':
                self::log($cfg, 'warning', 'webhook.unverified', [
                    'note' => 'webhook mode is "none" — any caller is accepted',
                    'ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
                return true;
            default:
                self::log($cfg, 'error', 'webhook.invalid_mode', ['mode' => $mode]);
                return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Modes
    // ═══════════════════════════════════════════════════════════

    private static function verifyHmac(
        string $rawBody,
        string $secret,
        array $headers,
        array $cfg,
    ): bool {
        if ($secret === '') {
            self::log($cfg, 'error', 'webhook.hmac.no_secret', []);
            return false;
        }

        $provided = self::getHeader($headers, self::HMAC_HEADER);
        if ($provided === null || $provided === '') {
            self::log($cfg, 'warning', 'webhook.hmac.missing_header', [
                'header' => self::HMAC_HEADER,
            ]);
            return false;
        }

        // Some providers prefix with "sha256=". Strip it if present.
        if (stripos($provided, 'sha256=') === 0) {
            $provided = substr($provided, 7);
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        if (!hash_equals($expected, strtolower($provided))) {
            self::log($cfg, 'warning', 'webhook.hmac.mismatch', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            return false;
        }
        return true;
    }

    private static function verifyHeader(
        string $secret,
        array $headers,
        array $cfg,
    ): bool {
        if ($secret === '') {
            self::log($cfg, 'error', 'webhook.header.no_secret', []);
            return false;
        }

        $headerName = (string)($cfg['whatsapp']['webhook_header'] ?? self::DEFAULT_HEADER);
        $provided   = self::getHeader($headers, $headerName);

        if ($provided === null || $provided === '') {
            self::log($cfg, 'warning', 'webhook.header.missing', [
                'header' => $headerName,
            ]);
            return false;
        }

        if (!hash_equals($secret, $provided)) {
            self::log($cfg, 'warning', 'webhook.header.mismatch', [
                'header' => $headerName,
                'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            return false;
        }
        return true;
    }

    private static function verifyQuery(
        string $secret,
        array $query,
        array $cfg,
    ): bool {
        if ($secret === '') {
            self::log($cfg, 'error', 'webhook.query.no_secret', []);
            return false;
        }

        $key = (string)($cfg['whatsapp']['webhook_query_key'] ?? self::DEFAULT_QUERY_KEY);
        $provided = isset($query[$key]) ? (string)$query[$key] : '';

        if ($provided === '') {
            self::log($cfg, 'warning', 'webhook.query.missing', ['key' => $key]);
            return false;
        }

        if (!hash_equals($secret, $provided)) {
            self::log($cfg, 'warning', 'webhook.query.mismatch', [
                'key' => $key,
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
            return false;
        }
        return true;
    }

    // ═══════════════════════════════════════════════════════════
    //  Header utilities
    // ═══════════════════════════════════════════════════════════

    /**
     * Collect HTTP headers from $_SERVER. Handles both CGI and
     * FastCGI variations of header naming.
     *
     * @return array<string,string>  Lowercased keys.
     */
    private static function collectHeaders(): array
    {
        $out = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $out[$name] = (string)$v;
            }
        }
        // Content-Type / Content-Length live outside HTTP_ prefix
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $out['content-type'] = (string)$_SERVER['CONTENT_TYPE'];
        }
        return $out;
    }

    private static function getHeader(array $headers, string $name): ?string
    {
        $needle = strtolower($name);
        foreach ($headers as $k => $v) {
            if (strtolower($k) === $needle) {
                return (string)$v;
            }
        }
        return null;
    }

    private static function log(array $cfg, string $level, string $event, array $ctx = []): void
    {
        $levels = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40];
        $min = $levels[strtolower($cfg['log']['level'] ?? 'info')] ?? 20;
        if (($levels[$level] ?? 0) < $min) return;

        $line = json_encode([
            'ts'    => gmdate('c'),
            'src'   => 'WebhookVerifier',
            'level' => $level,
            'event' => $event,
            'ctx'   => $ctx,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $logFile = $cfg['log_file'] ?? '';
        if ($logFile !== '') {
            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        if (!empty($cfg['log']['to_error_log']) && $level !== 'debug') {
            error_log('[ai_confirmation:' . $level . '] ' . $event . ' ' . json_encode($ctx));
        }
    }
}