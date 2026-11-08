<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  _bootstrap.php — shared bootstrap for all workers
 * ═══════════════════════════════════════════════════════════════
 *
 *  Usage from a worker:
 *
 *      <?php
 *      declare(strict_types=1);
 *      $ctx = require __DIR__ . '/_bootstrap.php';
 *      // $ctx = ['db'=>\DB, 'queue'=>Queue, 'cfg'=>array,
 *      //         'worker_id'=>string, 'args'=>array]
 *
 *      while (!worker_should_stop()) {
 *          $job = $ctx['queue']->reserve('confirmation', $ctx['worker_id']);
 *          if ($job === null) {
 *              worker_sleep_ms($ctx['cfg']['queue']['poll_interval_ms']);
 *              continue;
 *          }
 *          // ... handle $job ...
 *      }
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  CLI flags supported out of the box:
 *      --once              process one job and exit
 *      --verbose           force debug-level logging
 *      --poll=N            override poll interval (ms)
 *      --queue=NAME        override queue name
 *
 *  Environment:
 *      - Must be run from a shell, not from a web request.
 *      - Must be run with the same PHP user that owns the project,
 *        so it can write to ai_confirmation/logs/.
 * ═══════════════════════════════════════════════════════════════
 */

if (!defined('AI_CONFIRMATION_WORKER_BOOTSTRAPPED')) {
    define('AI_CONFIRMATION_WORKER_BOOTSTRAPPED', 1);
}

// ───────────────────────────────────────────────────────────────
//  1. CLI-only guard
// ───────────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("ai_confirmation workers must be run from the CLI.\n");
}

// ───────────────────────────────────────────────────────────────
//  2. Hard UTC — workers never inherit a server timezone
// ───────────────────────────────────────────────────────────────
date_default_timezone_set('UTC');

// ───────────────────────────────────────────────────────────────
//  3. Locate project root (two levels up from this file)
// ───────────────────────────────────────────────────────────────
$__aiWorkersDir  = __DIR__;                              // <root>/ai_confirmation/workers
$__aiConfDir     = dirname($__aiWorkersDir);             // <root>/ai_confirmation
$__projectRoot   = dirname($__aiConfDir);                // <root>

// ───────────────────────────────────────────────────────────────
//  4. Include the project's core bootstrap (defines DB, etc.)
// ───────────────────────────────────────────────────────────────
$coreInit = $__projectRoot . '/core/init.php';
if (!is_file($coreInit)) {
    fwrite(STDERR, "[ai_confirmation] core/init.php not found at {$coreInit}\n");
    exit(1);
}
require_once $coreInit;

// ───────────────────────────────────────────────────────────────
//  5. Include our own config + queue factory
// ───────────────────────────────────────────────────────────────
require_once $__aiConfDir . '/config/ai_confirmation.php';
require_once $__aiConfDir . '/queue/Queue.php';
require_once $__aiConfDir . '/queue/Job.php';
require_once $__aiConfDir . '/queue/DbQueue.php';
require_once $__aiConfDir . '/queue/queue_factory.php';

$cfg = ai_confirmation_config();

// ───────────────────────────────────────────────────────────────
//  6. Refuse to start if the subsystem is disabled
// ───────────────────────────────────────────────────────────────
if (!$cfg['enabled']) {
    fwrite(STDERR,
        "[ai_confirmation] AI_CONFIRMATION_ENABLED is 0. "
        . "Set it to 1 in .env before starting workers.\n"
    );
    exit(2);
}

// ───────────────────────────────────────────────────────────────
//  7. Simple CLI argument parser
// ───────────────────────────────────────────────────────────────
$__argv = $_SERVER['argv'] ?? [];
$args = [
    'once'    => false,
    'verbose' => false,
    'poll'    => null,
    'queue'   => null,
];
foreach (array_slice($__argv, 1) as $arg) {
    if ($arg === '--once')    { $args['once'] = true;    continue; }
    if ($arg === '--verbose') { $args['verbose'] = true; continue; }
    if (preg_match('/^--poll=(\d+)$/', $arg, $m)) {
        $args['poll'] = max(100, (int)$m[1]);
        continue;
    }
    if (preg_match('/^--queue=([a-z0-9_]+)$/i', $arg, $m)) {
        $args['queue'] = $m[1];
        continue;
    }
    if (in_array($arg, ['-h', '--help'], true)) {
        fwrite(STDOUT, "Usage: php worker.php [--once] [--verbose] [--poll=MS] [--queue=NAME]\n");
        exit(0);
    }
}

// If --verbose, force debug-level logging regardless of config.
if ($args['verbose']) {
    $cfg['log']['level'] = 'debug';
}

// Allow --poll to override the configured interval.
if ($args['poll'] !== null) {
    $cfg['queue']['poll_interval_ms'] = $args['poll'];
}

// ───────────────────────────────────────────────────────────────
//  8. Ensure the log directory exists and is writable
// ───────────────────────────────────────────────────────────────
$logDir = dirname($cfg['log_file']);
if (!is_dir($logDir)) {
    if (!@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
        fwrite(STDERR, "[ai_confirmation] cannot create log dir: {$logDir}\n");
        exit(3);
    }
}
if (!is_writable($logDir)) {
    fwrite(STDERR, "[ai_confirmation] log dir not writable: {$logDir}\n");
    exit(3);
}

// ───────────────────────────────────────────────────────────────
//  9. Worker identity
// ───────────────────────────────────────────────────────────────
$__prefix = trim((string)($cfg['worker']['id_prefix'] ?? ''));
if ($__prefix === '') {
    $__prefix = gethostname() ?: 'host';
}
$workerId = $__prefix . ':' . getmypid();

// Try to set the process title so `ps aux` is readable.
if (function_exists('cli_set_process_title')) {
    @cli_set_process_title('obg-worker:' . basename($__argv[0] ?? 'worker'));
}

// ───────────────────────────────────────────────────────────────
// 10. Resolve DB and Queue — shared by all workers
// ───────────────────────────────────────────────────────────────
try {
    $db    = \OBGecom\AiConfirmation\Queue\queue_resolve_db();
    $queue = \OBGecom\AiConfirmation\Queue\queue_factory();
} catch (\Throwable $e) {
    fwrite(STDERR, "[ai_confirmation] bootstrap failed: " . $e->getMessage() . "\n");
    exit(4);
}

// ───────────────────────────────────────────────────────────────
// 11. Signal handling — graceful shutdown
// ───────────────────────────────────────────────────────────────
if (!function_exists('pcntl_signal')) {
    fwrite(STDERR, "[ai_confirmation] pcntl extension not loaded — cannot handle signals.\n"
                 . "                Install php-pcntl (Debian/Ubuntu: apt install php-pcntl) "
                 . "or run with `php -d extension=pcntl.so`.\n");
    // We can still run without signal handling — just no graceful shutdown.
}

$GLOBALS['__ai_worker_should_stop'] = false;
$GLOBALS['__ai_worker_last_signal'] = null;

if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);

    $__signalHandler = function (int $signo) {
        $GLOBALS['__ai_worker_should_stop'] = true;
        $GLOBALS['__ai_worker_last_signal'] = $signo;
    };

    pcntl_signal(SIGTERM, $__signalHandler);
    pcntl_signal(SIGINT,  $__signalHandler);
    pcntl_signal(SIGHUP,  $__signalHandler); // treat as graceful stop
}

// ───────────────────────────────────────────────────────────────
// 12. Shared helper functions
// ───────────────────────────────────────────────────────────────

if (!function_exists('worker_should_stop')) {
    /**
     * Has a shutdown signal been received?
     */
    function worker_should_stop(): bool
    {
        return $GLOBALS['__ai_worker_should_stop'] === true;
    }
}

if (!function_exists('worker_last_signal')) {
    /**
     * @return int|null
     */
    function worker_last_signal(): ?int
    {
        return $GLOBALS['__ai_worker_last_signal'] ?? null;
    }
}

if (!function_exists('worker_sleep_ms')) {
    /**
     * Sleep for the given milliseconds, but remain responsive to signals.
     *
     * On systems with pcntl_async_signals(true), a plain usleep() is
     * already interruptible. On others, we slice the sleep into small
     * chunks so the loop notices a shutdown flag promptly.
     */
    function worker_sleep_ms(int $ms): void
    {
        $ms = max(0, $ms);
        if ($ms === 0) {
            return;
        }

        // Fast path: async signals enabled — plain usleep is fine.
        if (function_exists('pcntl_async_signals')) {
            usleep($ms * 1000);
            return;
        }

        // Slice into 200ms chunks.
        $remaining = $ms;
        while ($remaining > 0 && !worker_should_stop()) {
            $slice = min(200, $remaining);
            usleep($slice * 1000);
            $remaining -= $slice;
        }
    }
}

if (!function_exists('worker_log')) {
    /**
     * Structured JSON logger.
     *
     * @param array<string,mixed> $ctx
     */
    function worker_log(string $level, string $event, array $ctx = []): void
    {
        static $minLevel = null;

        if ($minLevel === null) {
            $cfg = ai_confirmation_config();
            $levels = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40];
            $minLevel = $levels[strtolower($cfg['log']['level'] ?? 'info')] ?? 20;
        }

        $levels = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40];
        if (($levels[$level] ?? 0) < $minLevel) {
            return;
        }

        $cfg = ai_confirmation_config();
        $line = json_encode([
            'ts'      => gmdate('c'),
            'src'     => basename($_SERVER['argv'][0] ?? 'worker'),
            'level'   => $level,
            'event'   => $event,
            'worker'  => $GLOBALS['__ai_worker_id'] ?? null,
            'ctx'     => $ctx,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $logFile = $cfg['log_file'] ?? '';
        if ($logFile !== '') {
            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }

        // Mirror to error_log for warning/error (never for debug/info).
        if (!empty($cfg['log']['to_error_log']) && in_array($level, ['warning', 'error'], true)) {
            error_log('[ai_confirmation:' . $level . '] ' . $event . ' ' . json_encode($ctx));
        }
    }
}

if (!function_exists('worker_fatal')) {
    /**
     * Log an error and exit with a non-zero status.
     */
    function worker_fatal(string $event, array $ctx = []): void
    {
        worker_log('error', $event, $ctx);
        exit(1);
    }
}

// Expose the worker id for the logging helper
$GLOBALS['__ai_worker_id'] = $workerId;

// ───────────────────────────────────────────────────────────────
// 13. Startup banner
// ───────────────────────────────────────────────────────────────
worker_log('info', 'worker.boot', [
    'worker_id'    => $workerId,
    'php_version'  => PHP_VERSION,
    'pid'          => getmypid(),
    'argv'         => $__argv,
    'poll_ms'      => $cfg['queue']['poll_interval_ms'],
    'once'         => $args['once'],
]);

// ───────────────────────────────────────────────────────────────
// 14. Return the context
// ───────────────────────────────────────────────────────────────
return [
    'db'        => $db,
    'queue'     => $queue,
    'cfg'       => $cfg,
    'worker_id' => $workerId,
    'args'      => $args,
]; ?>