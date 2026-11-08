<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  bin/recover_stale_jobs.php — release stuck jobs
 * ═══════════════════════════════════════════════════════════════
 *
 *  Run this from cron every 5 minutes.
 *
 *  Two jobs:
 *    1. Release any job stuck in 'processing' beyond the
 *       configured stale threshold (default 10 minutes).
 *    2. Prune completed/failed jobs older than 7 days.
 *
 *  Idempotent. Safe to run concurrently (the queue's UPDATE
 *  uses a timestamp predicate so only one process wins each row).
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  CLI flags:
 *      --dry-run       Report what would change; make no changes.
 *      --verbose       Print per-job detail.
 *
 *  Crontab line:
 *      *\/5 * * * * /usr/bin/php /www/wwwroot/obgecom/dashboard/ai_confirmation/bin/recover_stale_jobs.php >/dev/null 2>&1
 * ═══════════════════════════════════════════════════════════════
 */

date_default_timezone_set('UTC');

// ─────────────────────────────────────────────────────────────
//  Bootstrap
// ─────────────────────────────────────────────────────────────
//  Path calculation:
//      this file  →  <root>/ai_confirmation/bin/recover_stale_jobs.php
//      __DIR__    →  <root>/ai_confirmation/bin
//      ..         →  <root>/ai_confirmation
//      ../..      →  <root>
$projectRoot = dirname(__DIR__, 2);

$coreInit = $projectRoot . '/core/init.php';
if (!is_file($coreInit)) {
    fwrite(STDERR, "[recover_stale_jobs] core/init.php not found at {$coreInit}\n");
    exit(1);
}
require_once $coreInit;
require_once __DIR__ . '/../config/ai_confirmation.php';
require_once __DIR__ . '/../queue/Queue.php';
require_once __DIR__ . '/../queue/Job.php';
require_once __DIR__ . '/../queue/DbQueue.php';
require_once __DIR__ . '/../queue/queue_factory.php';

// ─────────────────────────────────────────────────────────────
//  CLI parsing
// ─────────────────────────────────────────────────────────────
$argv    = $_SERVER['argv'] ?? [];
$dryRun  = in_array('--dry-run', $argv, true);
$verbose = in_array('--verbose', $argv, true);

// ─────────────────────────────────────────────────────────────
//  Config
// ─────────────────────────────────────────────────────────────
$cfg = ai_confirmation_config();

if (empty($cfg['enabled'])) {
    // Nothing to do when the subsystem is disabled.
    // Exit silently so cron does not email.
    exit(0);
}

$staleMinutes = (int)($cfg['queue']['stale_claim_minutes'] ?? 10);
if ($staleMinutes < 1) {
    $staleMinutes = 10;
}

$keepDoneDays = 7;

// ─────────────────────────────────────────────────────────────
//  Resolve DB + queue
// ─────────────────────────────────────────────────────────────
try {
    $db    = DB::getInstance();
    $queue = \OBGecom\AiConfirmation\Queue\queue_factory();
} catch (\Throwable $e) {
    fwrite(STDERR, "[recover_stale_jobs] bootstrap failed: {$e->getMessage()}\n");
    exit(1);
}

// ─────────────────────────────────────────────────────────────
//  Timer start
// ─────────────────────────────────────────────────────────────
$start = microtime(true);

// ─────────────────────────────────────────────────────────────
//  1. Count stale jobs (for the report)
// ─────────────────────────────────────────────────────────────
$staleBefore = 0;
try {
    $rows = $db->getThisQuery(
        "SELECT COUNT(*) AS c
           FROM job_queue
          WHERE status = 'processing'
            AND reserved_at IS NOT NULL
            AND reserved_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        [$staleMinutes]
    );
    if (is_array($rows) && isset($rows[0]['c'])) {
        $staleBefore = (int)$rows[0]['c'];
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "[recover_stale_jobs] stale count failed: {$e->getMessage()}\n");
    // Do not exit — proceed to recovery step anyway.
}

// ─────────────────────────────────────────────────────────────
//  2. Recover stale jobs
// ─────────────────────────────────────────────────────────────
$recovered = 0;
if ($staleBefore > 0 && !$dryRun) {
    try {
        $recovered = $queue->recoverStale($staleMinutes);
    } catch (\Throwable $e) {
        fwrite(STDERR, "[recover_stale_jobs] recoverStale failed: {$e->getMessage()}\n");
        exit(2);
    }
}

// ─────────────────────────────────────────────────────────────
//  3. Prune old done/failed jobs
// ─────────────────────────────────────────────────────────────
$pruned = 0;
if (!$dryRun) {
    try {
        $db->delete_this(
            "DELETE FROM job_queue
              WHERE status IN ('done','failed')
                AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
              LIMIT 5000",
            [$keepDoneDays]
        );
        $pruned = $db->affectedRows();
    } catch (\Throwable $e) {
        // Do not fail the whole run — prune is best-effort
        fwrite(STDERR, "[recover_stale_jobs] prune failed: {$e->getMessage()}\n");
    }
}

// ─────────────────────────────────────────────────────────────
//  4. Report
// ─────────────────────────────────────────────────────────────
$elapsedMs = (int)round((microtime(true) - $start) * 1000);

// Only speak when there is something meaningful to say.
$shouldReport =
    $staleBefore > 0
    || $recovered > 0
    || $pruned > 0
    || $verbose
    || $dryRun;

if ($shouldReport) {
    $line = json_encode([
        'ts'              => gmdate('c'),
        'src'             => 'recover_stale_jobs',
        'stale_threshold' => $staleMinutes,
        'stale_found'     => $staleBefore,
        'recovered'       => $recovered,
        'pruned'          => $pruned,
        'dry_run'         => $dryRun,
        'elapsed_ms'      => $elapsedMs,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $logFile = $cfg['log_file'] ?? '';
    if ($logFile !== '') {
        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    if ($verbose || $dryRun) {
        fwrite(STDOUT, $line . PHP_EOL);
    }
}

exit(0); ?>