<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  bin/worker_watchdog.php — keep workers alive
 * ═══════════════════════════════════════════════════════════════
 *
 *  Called by cron every minute. Ensures every worker is running.
 *
 *  Supports two supervision modes:
 *
 *    MODE A (recommended) — systemd
 *        The systemd unit files handle restart on failure.
 *        This watchdog ONLY reports state to the watchdog log.
 *        No process management. Safer, industry-standard.
 *
 *    MODE B — PID files
 *        If systemd is not available, this watchdog starts and
 *        stops workers itself. It writes a pid file per worker
 *        and refuses to start a worker that is already running.
 *        Also refuses to restart a worker that crashed in the
 *        last 60 seconds (backoff).
 *
 *  Mode is auto-detected: if the systemd units are installed,
 *  MODE A is used; otherwise MODE B.
 *
 *  Crontab line:
 *      * * * * * /usr/bin/php /path/to/ai_confirmation/bin/worker_watchdog.php >/dev/null 2>&1
 * ═══════════════════════════════════════════════════════════════
 */

// ─────────────────────────────────────────────────────────────
//  Setup
// ─────────────────────────────────────────────────────────────
date_default_timezone_set('UTC');

$projectRoot = dirname(dirname(__DIR__));   // <root>/ai_confirmation/bin → <root>
$workersDir  = __DIR__ . '/../workers';
$logsDir     = __DIR__ . '/../logs';
$pidsDir     = __DIR__ . '/../run';
$watchLog    = $logsDir . '/watchdog.log';

@mkdir($logsDir, 0775, true);
@mkdir($pidsDir, 0775, true);

// Rotate watchdog log if > 5MB
if (is_file($watchLog) && filesize($watchLog) > 5 * 1024 * 1024) {
    @rename($watchLog, $watchLog . '.1');
}

function wlog(string $msg, array $ctx = []): void
{
    global $watchLog;
    $line = json_encode([
        'ts'  => gmdate('c'),
        'src' => 'watchdog',
        'msg' => $msg,
        'ctx' => $ctx,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($watchLog, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// ─────────────────────────────────────────────────────────────
//  Discover configured workers
// ─────────────────────────────────────────────────────────────
// We treat a fixed, curated list as the workers we want running.
// This is safer than scanning the directory (which might pick up
// _bootstrap.php or future helper scripts).
$workers = [
    // name              => [worker file,          queue name,     instances]
    'confirmation'   => ['confirmation_worker.php',   'confirmation',   1],
    'inbound'        => ['inbound_worker.php',        'inbound_ai',     1],
    'timeout'        => ['timeout_worker.php',        'timeout',        1],
    'agent_dispatch' => ['agent_dispatch_worker.php', 'agent_dispatch', 1],
    'shipping'       => ['shipping_worker.php',       'shipping',       1],
];

// Confirm every worker file exists
$missing = [];
foreach ($workers as $name => [$file, $_queue, $_n]) {
    if (!is_file($workersDir . '/' . $file)) {
        $missing[] = $file;
    }
}
if (!empty($missing)) {
    wlog('missing_worker_files', ['missing' => $missing]);
    // Still continue — we'll report but not attempt to start missing files.
}

// ─────────────────────────────────────────────────────────────
//  Determine supervision mode
// ─────────────────────────────────────────────────────────────
$systemdMode = false;
if (function_exists('shell_exec')) {
    // Look for one of our known unit names
    $out = @shell_exec('systemctl list-units --type=service --all 2>/dev/null | grep -c "obg-worker"');
    if (is_string($out) && (int)trim($out) > 0) {
        $systemdMode = true;
    }
}

wlog('cycle_start', [
    'mode'          => $systemdMode ? 'systemd' : 'pidfile',
    'project_root'  => $projectRoot,
]);

// ─────────────────────────────────────────────────────────────
//  MODE A — systemd
// ─────────────────────────────────────────────────────────────
if ($systemdMode) {
    foreach ($workers as $name => [$file, $queue, $_n]) {
        $unit = 'obg-worker-' . str_replace('_', '-', $name) . '.service';

        // Check if the unit is active
        $state = trim((string)@shell_exec(
            'systemctl is-active ' . escapeshellarg($unit) . ' 2>/dev/null'
        ));

        wlog('worker_check', [
            'name'  => $name,
            'unit'  => $unit,
            'state' => $state,
        ]);

        if ($state !== 'active') {
            // Try a restart. systemd will rate-limit if we spam it.
            @shell_exec(
                'systemctl restart ' . escapeshellarg($unit) . ' 2>&1'
            );
            wlog('worker_restart_issued', [
                'name' => $name,
                'unit' => $unit,
            ]);
        }
    }
    exit(0);
}

// ─────────────────────────────────────────────────────────────
//  MODE B — PID files
// ─────────────────────────────────────────────────────────────

$phpBin = PHP_BINARY;
if ($phpBin === '' || !is_file($phpBin)) {
    $phpBin = '/usr/bin/php';
}

foreach ($workers as $name => [$file, $queue, $instances]) {

    $workerPath = $workersDir . '/' . $file;
    if (!is_file($workerPath)) {
        wlog('pidfile.worker_file_missing', ['name' => $name, 'path' => $workerPath]);
        continue;
    }

    $pidFile = $pidsDir . '/' . $name . '.pid';
    $pid     = null;

    if (is_file($pidFile)) {
        $raw = trim((string)@file_get_contents($pidFile));
        if (ctype_digit($raw) && (int)$raw > 0) {
            $pid = (int)$raw;
        }
    }

    // Check the process is actually alive
    if ($pid !== null) {
        $alive = isProcessAlive($pid);
        if (!$alive) {
            // Stale pid file. Remove and continue to restart.
            @unlink($pidFile);
            $pid = null;
            wlog('pidfile.stale_removed', ['name' => $name]);
        }
    }

    // If we still have a live pid, ensure it is our worker (not a reused pid)
    if ($pid !== null) {
        $cmdline = readProcessCmdline($pid);
        if ($cmdline === '' || strpos($cmdline, $file) === false) {
            wlog('pidfile.pid_reused', [
                'name'    => $name,
                'pid'     => $pid,
                'cmdline' => $cmdline,
            ]);
            @unlink($pidFile);
            $pid = null;
        }
    }

    // Worker is running. Nothing to do.
    if ($pid !== null) {
        wlog('worker_alive', ['name' => $name, 'pid' => $pid]);
        continue;
    }

    // ── Crash-loop backoff ────────────────────────────────────
    // If a marker file exists and is younger than 60s, skip restart.
    $markerFile = $pidsDir . '/' . $name . '.laststart';
    if (is_file($markerFile) && (time() - filemtime($markerFile)) < 60) {
        wlog('worker_backoff', [
            'name' => $name,
            'age'  => time() - filemtime($markerFile),
        ]);
        continue;
    }

    // ── Start the worker ──────────────────────────────────────
    $cmd = escapeshellcmd($phpBin)
         . ' ' . escapeshellarg($workerPath)
         . ' --queue=' . escapeshellarg($queue);

    // Redirect stdout/stderr to per-worker logs, detach, no shell
    $stdoutLog = $logsDir . '/' . $name . '.stdout.log';
    $stderrLog = $logsDir . '/' . $name . '.stderr.log';
    $fullCmd = $cmd
             . ' >> ' . escapeshellarg($stdoutLog)
             . ' 2>> ' . escapeshellarg($stderrLog)
             . ' & echo $!';

    $newPid = trim((string)@shell_exec($fullCmd));
    if (ctype_digit($newPid) && (int)$newPid > 0) {
        @file_put_contents($pidFile, (string)(int)$newPid);
        @touch($markerFile);
        wlog('worker_started', [
            'name' => $name,
            'pid'  => (int)$newPid,
            'cmd'  => $cmd,
        ]);
    } else {
        wlog('worker_start_failed', [
            'name' => $name,
            'cmd'  => $cmd,
        ]);
    }
}

wlog('cycle_end');
exit(0);

// ═════════════════════════════════════════════════════════════════
//  Helpers
// ═════════════════════════════════════════════════════════════════

function isProcessAlive(int $pid): bool
{
    if ($pid <= 0) return false;
    if (function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }
    // Fallback: check /proc on Linux
    if (is_dir('/proc/' . $pid)) return true;
    // Fallback: shell test
    $out = @shell_exec('kill -0 ' . (int)$pid . ' 2>/dev/null && echo ok');
    return trim((string)$out) === 'ok';
}

function readProcessCmdline(int $pid): string
{
    $path = '/proc/' . $pid . '/cmdline';
    if (is_file($path)) {
        $raw = (string)@file_get_contents($path);
        return str_replace("\0", ' ', $raw);
    }
    // macOS / BSD fallback
    $out = @shell_exec('ps -p ' . (int)$pid . ' -o command= 2>/dev/null');
    return trim((string)$out);
} ?>