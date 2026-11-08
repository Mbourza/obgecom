<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Queue;

use RuntimeException;

require_once __DIR__ . '/Queue.php';
require_once __DIR__ . '/Job.php';
require_once __DIR__ . '/DbQueue.php';

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  queue_factory — build the configured Queue implementation
 * ═══════════════════════════════════════════════════════════════
 *
 *  Usage:
 *
 *      require_once __DIR__ . '/../queue/queue_factory.php';
 *      $queue = queue_factory();
 *      $queue->push(Job::make(...));
 *
 *  The factory reads config once and caches the resulting Queue
 *  instance for the lifetime of the process. Multiple calls return
 *  the same object — safe to call from anywhere.
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  To add a new driver (Redis, SQS, etc.):
 *      1. Implement OBGecom\AiConfirmation\Queue\Queue
 *      2. Add a `case` below
 *      3. Set AI_CONFIRMATION_QUEUE_DRIVER in .env
 *  No other file needs to change.
 * ═══════════════════════════════════════════════════════════════
 */

if (!function_exists('OBGecom\\AiConfirmation\\Queue\\queue_factory')) {

    /**
     * Return a ready-to-use Queue instance.
     *
     * @param bool $reload  Force a fresh instance (useful in tests).
     */
    function queue_factory(bool $reload = false): Queue
    {
        static $instance = null;

        if ($instance !== null && !$reload) {
            return $instance;
        }

        // ── 1. Load config ─────────────────────────────────
        if (!function_exists('ai_confirmation_config')) {
            require_once __DIR__ . '/../config/ai_confirmation.php';
        }
        $cfg = ai_confirmation_config();

        $driver   = strtolower($cfg['queue']['driver'] ?? 'db');
        $logFile  = (string)($cfg['log_file'] ?? '');
        $logLevel = (string)($cfg['log']['level'] ?? 'info');

        // ── 2. Dispatch on driver ──────────────────────────
        switch ($driver) {
            case 'db':
                $instance = new DbQueue(
                    db:       queue_resolve_db(),
                    logFile:  $logFile,
                    logLevel: $logLevel,
                );
                break;

            // case 'redis':
            //     $instance = new RedisQueue(...);
            //     break;

            default:
                throw new RuntimeException(
                    "queue_factory: unsupported driver '{$driver}'. "
                    . "Set AI_CONFIRMATION_QUEUE_DRIVER to one of: db"
                );
        }

        return $instance;
    }

    /**
     * Resolve the shared DB instance.
     *
     * The project's DB class is a singleton and defines a
     * DB::getInstance() accessor. core/init.php must be included
     * before this factory is called — that is the caller's
     * responsibility (see workers/_bootstrap.php and each web
     * endpoint's bootstrap block).
     *
     * @return \DB
     */
    function queue_resolve_db(): \DB
    {
        if (!class_exists('DB')) {
            throw new RuntimeException(
                'queue_resolve_db: class DB is not loaded. '
                . 'Make sure core/init.php is included before calling the queue factory.'
            );
        }

        $db = \DB::getInstance();
        if (!$db) {
            throw new RuntimeException('queue_resolve_db: DB::getInstance() returned null');
        }

        return $db;
    }
} ?>