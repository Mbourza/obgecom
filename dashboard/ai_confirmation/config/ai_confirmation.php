<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  Configuration Loader
 * ═══════════════════════════════════════════════════════════════
 *
 *  This is the ONLY file in the AI confirmation subsystem that
 *  reads environment variables or includes external config.
 *  Everything else calls `ai_confirmation_config()`.
 *
 *  Usage:
 *      require_once __DIR__ . '/../config/ai_confirmation.php';
 *      $cfg = ai_confirmation_config();
 *      echo $cfg['gemini_model'];
 *
 *  The returned array is cached for the lifetime of the process.
 *
 *  This file is safe to include from CLI (workers) and from FPM
 *  (webhook endpoint). It has no side effects beyond reading files.
 * ═══════════════════════════════════════════════════════════════
*/

date_default_timezone_set('UTC');

if (!function_exists('ai_confirmation_config')) {

    /**
     * Load and return the effective AI confirmation configuration.
     *
     * @param  bool $reload  Force a fresh read (useful in tests).
     * @return array<string,mixed>
     */
    function ai_confirmation_config(bool $reload = false): array
    {
        static $config = null;

        if ($config !== null && !$reload) {
            return $config;
        }

        // ─────────────────────────────────────────────────────
        // 1. Resolve project root
        // ─────────────────────────────────────────────────────
        // This file lives at: <root>/ai_confirmation/config/ai_confirmation.php
        // So the project root is two levels up.
        $configDir   = __DIR__;                             // <root>/ai_confirmation/config
        $aiConfDir   = dirname($configDir);                 // <root>/ai_confirmation
        $projectRoot = dirname($aiConfDir);                 // <root>

        // ─────────────────────────────────────────────────────
        // 2. Load .env (project root) — no Composer needed
        // ─────────────────────────────────────────────────────
        $envVars = _ai_conf_load_env_file($projectRoot . '/.env');
        // Fallback: also support .env inside ai_confirmation/
        if (empty($envVars)) {
            $envVars = _ai_conf_load_env_file($aiConfDir . '/.env');
        }

        /**
         * Small helper: fetch env var with fallback chain.
         *   1. .env file value (highest priority)
         *   2. getenv() / $_ENV value
         *   3. $default
         */
        $env = static function (string $key, $default = null) use ($envVars) {
            if (array_key_exists($key, $envVars)) {
                return $envVars[$key];
            }
            $v = getenv($key);
            if ($v !== false && $v !== '') {
                return $v;
            }
            if (isset($_ENV[$key])) {
                return $_ENV[$key];
            }
            if (isset($_SERVER[$key])) {
                return $_SERVER[$key];
            }
            return $default;
        };

        /** Cast "1", "true", "yes", "on" → true. Everything else → false. */
        $envBool = static function (string $key, bool $default = false) use ($env): bool {
            $v = $env($key, $default ? '1' : '0');
            if (is_bool($v)) return $v;
            $v = strtolower(trim((string)$v));
            return in_array($v, ['1', 'true', 'yes', 'on'], true);
        };

        /** Cast to int with a default and clamp. */
        $envInt = static function (string $key, int $default, ?int $min = null, ?int $max = null) use ($env): int {
            $v = $env($key, $default);
            if (!is_numeric($v)) return $default;
            $i = (int)$v;
            if ($min !== null && $i < $min) $i = $min;
            if ($max !== null && $i > $max) $i = $max;
            return $i;
        };

        /** Cast to float with a default and clamp. */
        $envFloat = static function (string $key, float $default, ?float $min = null, ?float $max = null) use ($env): float {
            $v = $env($key, $default);
            if (!is_numeric($v)) return $default;
            $f = (float)$v;
            if ($min !== null && $f < $min) $f = $min;
            if ($max !== null && $f > $max) $f = $max;
            return $f;
        };

        // ─────────────────────────────────────────────────────
        // 3. Load existing WhatsApp credentials
        //    config/whatsapp.php defines $apiKey, $apiUrl, $phoneNumberId
        //    as globals. We capture them WITHOUT modifying that file.
        // ─────────────────────────────────────────────────────
        $waApiKey        = null;
        $waApiUrl        = null;
        $waPhoneNumberId = null;
        $waFromNumber    = null;

        // Path A — include the file if constants are not yet defined
        $whatsappConfigFile = $projectRoot . '/config/whatsapp.php';
        if (!defined('WHATSAPP_API_URL') && is_file($whatsappConfigFile)) {
            $prevErr = error_reporting(0);
            try {
                include $whatsappConfigFile;
            } catch (\Throwable $e) {
                error_log('[ai_confirmation] include config/whatsapp.php failed: '
                        . $e->getMessage());
            } finally {
                error_reporting($prevErr);
            }
        }

        // Path B — read constants if defined
        if (defined('WHATSAPP_API_URL')) {
            $waApiUrl = (string)constant('WHATSAPP_API_URL');
        }
        if (defined('WHATSAPP_API_KEY')) {
            $waApiKey = (string)constant('WHATSAPP_API_KEY');
        }
        if (defined('WHATSAPP_PHONE_NUMBER_ID')) {
            $waPhoneNumberId = (string)constant('WHATSAPP_PHONE_NUMBER_ID');
        }
        if (defined('WHATSAPP_FROM_NUMBER')) {
            $waFromNumber = (string)constant('WHATSAPP_FROM_NUMBER');
        }

        // Path C — fall back to globals (older style)
        if (($waApiKey === null || $waApiKey === '') && isset($GLOBALS['apiKey'])) {
            $waApiKey = (string)$GLOBALS['apiKey'];
        }
        if (($waApiUrl === null || $waApiUrl === '') && isset($GLOBALS['apiUrl'])) {
            $waApiUrl = (string)$GLOBALS['apiUrl'];
        }
        if (($waPhoneNumberId === null || $waPhoneNumberId === '')
            && isset($GLOBALS['phoneNumberId'])
        ) {
            $waPhoneNumberId = (string)$GLOBALS['phoneNumberId'];
        }

        // Path D — fall back to environment variables
        if ($waApiKey === null || $waApiKey === '') {
            $waApiKey = getenv('WHATSAPP_API_KEY') ?: null;
        }
        if ($waApiUrl === null || $waApiUrl === '') {
            $waApiUrl = getenv('WHATSAPP_API_URL') ?: null;
        }
        if ($waPhoneNumberId === null || $waPhoneNumberId === '') {
            $waPhoneNumberId = getenv('WHATSAPP_PHONE_NUMBER_ID') ?: null;
        }

        if ($waApiKey === null || $waApiKey === ''
            || $waApiUrl === null || $waApiUrl === ''
            || $waPhoneNumberId === null || $waPhoneNumberId === ''
        ) {
            error_log('[ai_confirmation] WhatsApp credentials incomplete — '
                    . 'check that config/whatsapp.php defines WHATSAPP_API_URL, '
                    . 'WHATSAPP_API_KEY, and WHATSAPP_PHONE_NUMBER_ID.');
        }

        // ─────────────────────────────────────────────────────
        // 4. Build the config array
        // ─────────────────────────────────────────────────────
        $config = [

            // ── Master switch ────────────────────────────────
            'enabled' => $envBool('AI_CONFIRMATION_ENABLED', false),

            // ── Paths ────────────────────────────────────────
            'project_root'    => $projectRoot,
            'ai_conf_root'    => $aiConfDir,
            'log_file'        => _ai_conf_resolve_path(
                                    (string)$env('AI_CONFIRMATION_LOG_FILE', 'ai_confirmation/logs/worker.log'),
                                    $projectRoot
                                 ),

            // ── AI (Gemini) ──────────────────────────────────
            'ai' => [
                'provider'         => (string)$env('AI_PROVIDER', 'gemini'),
                'gemini_api_key'   => (string)$env('GEMINI_API_KEY', ''),
                'gemini_model'     => (string)$env('GEMINI_MODEL', 'gemini-1.5-flash'),
                'timeout_seconds'  => $envInt('AI_REQUEST_TIMEOUT_SECONDS', 15, 1, 120),
                'max_input_chars'  => $envInt('AI_MAX_INPUT_CHARS', 1000, 50, 10000),
                'max_output_tokens'=> $envInt('AI_MAX_OUTPUT_TOKENS', 300, 50, 4000),
            ],

            // ── Queue ────────────────────────────────────────
            'queue' => [
                'driver'                 => (string)$env('AI_CONFIRMATION_QUEUE_DRIVER', 'db'),
                'poll_interval_ms'       => $envInt('AI_CONFIRMATION_POLL_INTERVAL_MS', 1000, 100, 30000),
                'stale_claim_minutes'    => $envInt('AI_CONFIRMATION_STALE_CLAIM_MINUTES', 10, 1, 1440),
                'max_attempts'           => $envInt('AI_CONFIRMATION_MAX_ATTEMPTS', 3, 1, 10),
            ],

            // ── Confirmation defaults ────────────────────────
            'defaults' => [
                'timeout_minutes'         => $envInt('AI_CONFIRMATION_DEFAULT_TIMEOUT_MINUTES', 120, 1, 10080),
                'second_timeout_minutes'  => $envInt('AI_CONFIRMATION_DEFAULT_SECOND_TIMEOUT_MINUTES', 120, 1, 10080),
                'max_ai_replies'          => $envInt('AI_CONFIRMATION_DEFAULT_MAX_AI_REPLIES', 3, 0, 20),
                'confirm_threshold'       => $envFloat('AI_CONFIRMATION_DEFAULT_CONFIRM_THRESHOLD', 0.85, 0.0, 1.0),
                'cancel_threshold'        => $envFloat('AI_CONFIRMATION_DEFAULT_CANCEL_THRESHOLD', 0.85, 0.0, 1.0),
                'agent_fallback_enabled'  => $envBool('AI_CONFIRMATION_DEFAULT_AGENT_FALLBACK_ENABLED', true),
                'auto_shipping_enabled'   => $envBool('AI_CONFIRMATION_DEFAULT_AUTO_SHIPPING_ENABLED', true),
            ],

            // ── WhatsApp (360dialog) ─────────────────────────
            'whatsapp' => [
                'provider'          => defined('WHATSAPP_PROVIDER')
                                            ? (string)constant('WHATSAPP_PROVIDER')
                                            : (string)$env('WHATSAPP_PROVIDER', '360dialog'),
                'webhook_secret'    => (string)$env('WHATSAPP_WEBHOOK_SECRET', ''),
                'webhook_mode'      => (string)$env('WHATSAPP_WEBHOOK_MODE', ''),
                'webhook_header'    => (string)$env('WHATSAPP_WEBHOOK_HEADER', 'X-Webhook-Secret'),
                'webhook_query_key' => (string)$env('WHATSAPP_WEBHOOK_QUERY_KEY', 'secret'),
                'default_template'  => defined('WHATSAPP_TEMPLATE_AI_CONFIRMATION')
                                            ? (string)constant('WHATSAPP_TEMPLATE_AI_CONFIRMATION')
                                            : (string)$env('WHATSAPP_DEFAULT_TEMPLATE_NAME', 'order_confirmation_ai'),
                'default_language'  => (string)$env('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE', 'fr'),
                // Captured from config/whatsapp.php — do not edit here.
                'api_key'           => $waApiKey,
                'api_url'           => $waApiUrl,
                'phone_number_id'   => $waPhoneNumberId,
                'from_number'       => $waFromNumber,
            ],

            // ── Logging ──────────────────────────────────────
            'log' => [
                'level'           => (string)$env('AI_CONFIRMATION_LOG_LEVEL', 'info'),
                'to_error_log'    => $envBool('AI_CONFIRMATION_LOG_TO_ERROR_LOG', true),
            ],

            // ── Worker identity ──────────────────────────────
            'worker' => [
                'id_prefix'          => (string)$env('AI_CONFIRMATION_WORKER_ID_PREFIX', ''),
                'max_jobs'           => $envInt('AI_CONFIRMATION_WORKER_MAX_JOBS', 1000, 0, 1000000),
                'shutdown_timeout'   => $envInt('AI_CONFIRMATION_WORKER_SHUTDOWN_TIMEOUT', 30, 1, 300),
            ],

            // ── Rate limiting ────────────────────────────────
            'ratelimit' => [
                'whatsapp_per_minute' => $envInt('AI_CONFIRMATION_MAX_WHATSAPP_PER_MINUTE', 60, 1, 100000),
                'ai_calls_per_minute' => $envInt('AI_CONFIRMATION_MAX_AI_CALLS_PER_MINUTE', 30, 1, 100000),
            ],

            // ── Testing ──────────────────────────────────────
            'use_mocks' => $envBool('AI_CONFIRMATION_USE_MOCKS', false),
        ];

        // ─────────────────────────────────────────────────────
        // 5. Startup sanity warnings (logged once, no secrets)
        // ─────────────────────────────────────────────────────
        if ($config['enabled']) {
            if ($config['ai']['gemini_api_key'] === '') {
                error_log('[ai_confirmation] ENABLED but GEMINI_API_KEY is empty — '
                        . 'deterministic classifier only, no AI fallback.');
            }
            if ($config['whatsapp']['api_key'] === null
                || $config['whatsapp']['api_key'] === ''
                || $config['whatsapp']['api_url'] === null
                || $config['whatsapp']['api_url'] === ''
                || $config['whatsapp']['phone_number_id'] === null
                || $config['whatsapp']['phone_number_id'] === ''
            ) {
                error_log('[ai_confirmation] ENABLED but WhatsApp credentials missing — '
                        . 'check that config/whatsapp.php defines WHATSAPP_API_URL, '
                        . 'WHATSAPP_API_KEY, and WHATSAPP_PHONE_NUMBER_ID.');
            }
            if (!is_dir(dirname($config['log_file']))) {
                @mkdir(dirname($config['log_file']), 0775, true);
            }
        }

        return $config;
    }
}

// ═══════════════════════════════════════════════════════════════
//  INTERNAL HELPERS (prefixed to avoid collisions)
// ═══════════════════════════════════════════════════════════════

if (!function_exists('_ai_conf_load_env_file')) {
    /**
     * Parse a .env file into an associative array.
     *
     * Supports:
     *   KEY=value
     *   KEY="quoted value with spaces"
     *   KEY='single quoted'
     *   # comments
     *   blank lines
     *   ${OTHER_KEY} interpolation (one level)
     *
     * Does NOT overwrite existing real environment variables.
     *
     * @return array<string,string>
     */
    function _ai_conf_load_env_file(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        // Strip UTF-8 BOM if present.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $out   = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Split on first '=' only.
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));

            if ($key === '') {
                continue;
            }

            // Strip surrounding quotes.
            if (strlen($val) >= 2) {
                $first = $val[0];
                $last  = $val[strlen($val) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                    // Unescape double-quoted values only.
                    if ($first === '"') {
                        $val = str_replace(['\\n', '\\r', '\\t', '\\"'], ["\n", "\r", "\t", '"'], $val);
                    }
                }
            }

            // ${VAR} interpolation using values already parsed in this file.
            if (str_contains($val, '${')) {
                $val = preg_replace_callback('/\$\{([A-Z0-9_]+)\}/i', function ($m) use ($out) {
                    return $out[$m[1]] ?? '';
                }, $val);
            }

            // Real environment variables win over file values.
            $real = getenv($key);
            if ($real !== false && $real !== '') {
                $out[$key] = $real;
            } else {
                $out[$key] = $val;
            }
        }

        return $out;
    }
}

if (!function_exists('_ai_conf_resolve_path')) {
    /**
     * If the given path is absolute, return as-is.
     * Otherwise resolve it relative to the project root.
     */
    function _ai_conf_resolve_path(string $path, string $projectRoot): string
    {
        if ($path === '') {
            return $projectRoot;
        }
        // Unix absolute or Windows drive letter.
        if ($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            return $path;
        }
        return rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('ai_confirmation_config_redacted')) {
    /**
     * Return a copy of the config with secrets masked.
     * Safe to log or dump in a debug endpoint.
     */
    function ai_confirmation_config_redacted(): array
    {
        $c = ai_confirmation_config();
        $c['ai']['gemini_api_key']       = _ai_conf_redact($c['ai']['gemini_api_key'] ?? '');
        $c['whatsapp']['api_key']        = _ai_conf_redact($c['whatsapp']['api_key'] ?? '');
        $c['whatsapp']['webhook_secret'] = _ai_conf_redact($c['whatsapp']['webhook_secret'] ?? '');
        return $c;
    }
}

if (!function_exists('_ai_conf_redact')) {
    function _ai_conf_redact(?string $secret): string
    {
        if ($secret === null || $secret === '') return '(empty)';
        $len = strlen($secret);
        if ($len <= 8) return str_repeat('*', $len);
        return substr($secret, 0, 4) . str_repeat('*', $len - 8) . substr($secret, -4);
    }
} ?>