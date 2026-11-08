<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Queue;

use DateTimeImmutable;
use RuntimeException;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  DbQueue — MariaDB implementation of Queue
 * ═══════════════════════════════════════════════════════════════
 *
 *  Backing table: job_queue  (see migration 2026_09_19_001)
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Concurrency model (MariaDB 10.11 has no SKIP LOCKED):
 *
 *      1. Worker A issues: UPDATE job_queue
 *                             SET status='processing',
 *                                 reserved_by=?, reserved_at=NOW()
 *                           WHERE id = ?
 *                             AND status = 'pending'
 *                           LIMIT 1
 *         → affectedRows() = 1  → A owns the job
 *         → affectedRows() = 0  → someone else won; try next
 *
 *      The WHERE status='pending' clause is what makes the UPDATE
 *      atomic across concurrent workers. No SELECT FOR UPDATE
 *      needed.
 *
 *  Idempotent push:
 *      The migration defines UNIQUE(idempotency_key). We pre-check
 *      for an existing row with the same key and return null if
 *      found — matching the original PDO semantics.
 *
 *  Retry backoff schedule (computed here, not by callers):
 *      attempt 1 → +60s
 *      attempt 2 → +300s
 *      attempt 3+→ +900s
 * ═══════════════════════════════════════════════════════════════
 */
final class DbQueue implements Queue
{
    /** Backoff schedule in seconds, indexed by attempt number (0-based). */
    private const BACKOFF_SCHEDULE = [60, 300, 900];

    /** Upper bound on candidates scanned per reserve() call. */
    private const RESERVE_SCAN_LIMIT = 20;

    /** Max jobs recovered by recoverStale() in one call. */
    private const STALE_RECOVER_BATCH = 500;

    public function __construct(
        private \DB $db,
        private string $logFile = '',
        private string $logLevel = 'info',
    ) {
    }

    // ═══════════════════════════════════════════════════════════
    //  PUSH
    // ═══════════════════════════════════════════════════════════

    public function push(Job $job): ?Job
    {
        if ($job->isPersisted()) {
            throw new \InvalidArgumentException(
                'DbQueue::push received a Job that already has an id (' . $job->id . ')'
            );
        }

        // ── Idempotency pre-check ────────────────────────────────
        // The UNIQUE(idempotency_key) constraint would also catch
        // this, but insert_this() returns false on ANY failure —
        // including duplicate keys AND genuine DB errors — so we
        // cannot distinguish them from the return value alone.
        // We pre-check to preserve the original "duplicate → null,
        // error → throw" semantics.
        try {
            $existing = $this->db->getThisQuery(
                "SELECT id FROM job_queue WHERE idempotency_key = ? LIMIT 1",
                [$job->idempotencyKey]
            );
            if (!empty($existing)) {
                $this->log('debug', 'push.duplicate', [
                    'idempotency_key' => $job->idempotencyKey,
                    'queue'           => $job->queue,
                    'existing_id'     => (int)$existing[0]['id'],
                ]);
                return null;
            }
        } catch (\Throwable $e) {
            // Pre-check failure is not fatal; fall through to insert.
            $this->log('warning', 'push.precheck_failed', [
                'idempotency_key' => $job->idempotencyKey,
                'error'           => $e->getMessage(),
            ]);
        }

        // ── Insert ───────────────────────────────────────────────
        try {
            $insertId = $this->db->insert_this(
                "INSERT INTO job_queue
                    (queue, job_type, user_id, store_id, order_id,
                     payload, idempotency_key, status, attempts, max_attempts,
                     available_at, reserved_at, reserved_by, last_error,
                     created_at, updated_at)
                 VALUES
                    (?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?,
                     ?, NULL, NULL, NULL,
                     NOW(), NOW())",
                [
                    $job->queue,
                    $job->jobType,
                    $job->userId,
                    $job->storeId,
                    $job->orderId,
                    json_encode($job->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $job->idempotencyKey,
                    Job::STATUS_PENDING,
                    0,
                    $job->maxAttempts,
                    $job->availableAt->format('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable $e) {
            $this->log('error', 'push.failed', [
                'idempotency_key' => $job->idempotencyKey,
                'error'           => $e->getMessage(),
            ]);
            throw new RuntimeException('DbQueue::push failed: ' . $e->getMessage(), 0, $e);
        }

        if (!$insertId) {
            // insert_this() returned false. Either the pre-check missed
            // a concurrent insert of the same key, or the INSERT failed
            // silently. Re-check for the key to decide.
            try {
                $existing = $this->db->getThisQuery(
                    "SELECT id FROM job_queue WHERE idempotency_key = ? LIMIT 1",
                    [$job->idempotencyKey]
                );
                if (!empty($existing)) {
                    $this->log('debug', 'push.duplicate.race', [
                        'idempotency_key' => $job->idempotencyKey,
                        'existing_id'     => (int)$existing[0]['id'],
                    ]);
                    return null;
                }
            } catch (\Throwable $e) {
                // fall through
            }

            $this->log('error', 'push.insert_failed', [
                'idempotency_key' => $job->idempotencyKey,
                'db_error'        => $this->db->getError(),
            ]);
            throw new RuntimeException(
                'DbQueue::push failed: insert_this returned false ('
                . ($this->db->getError() ?? 'no error message')
                . ')'
            );
        }

        $id = (int)$insertId;

        $this->log('info', 'push.ok', [
            'id'              => $id,
            'queue'           => $job->queue,
            'job_type'        => $job->jobType,
            'idempotency_key' => $job->idempotencyKey,
            'available_at'    => $job->availableAt->format('Y-m-d H:i:s'),
        ]);

        return $job->withId($id);
    }

    // ═══════════════════════════════════════════════════════════
    //  RESERVE
    // ═══════════════════════════════════════════════════════════

    public function reserve(string $queue, string $workerId): ?Job
    {
        // Fetch a small batch of candidate ids (cheap: index-covered).
        try {
            $rows = $this->db->getThisQuery(
                "SELECT id
                   FROM job_queue
                  WHERE queue = ?
                    AND status = 'pending'
                    AND available_at <= NOW()
                  ORDER BY available_at ASC, id ASC
                  LIMIT " . self::RESERVE_SCAN_LIMIT,
                [$queue]
            );
        } catch (\Throwable $e) {
            $this->log('error', 'reserve.select_failed', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (empty($rows)) {
            return null;
        }

        // Try each candidate until one is successfully claimed.
        foreach ($rows as $row) {
            $id = (int)$row['id'];

            try {
                $this->db->update_this(
                    "UPDATE job_queue
                        SET status      = 'processing',
                            reserved_by = ?,
                            reserved_at = NOW(),
                            updated_at  = NOW()
                      WHERE id = ?
                        AND status = 'pending'
                      LIMIT 1",
                    [$workerId, $id]
                );
            } catch (\Throwable $e) {
                // A failure on one candidate should not stop us
                // from trying the others.
                $this->log('warning', 'reserve.claim_failed', [
                    'id'    => $id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($this->db->affectedRows() !== 1) {
                // Another worker grabbed it between our SELECT and UPDATE.
                continue;
            }

            // We own it now — load the full row.
            $job = $this->loadJobById($id);
            if ($job === null) {
                // Extremely unlikely (row deleted mid-flight); skip.
                continue;
            }

            $this->log('debug', 'reserve.ok', [
                'id'       => $job->id,
                'queue'    => $queue,
                'worker'   => $workerId,
                'job_type' => $job->jobType,
                'attempts' => $job->attempts,
            ]);

            return $job;
        }

        // Every candidate was taken by someone else; not an error.
        return null;
    }

    // ═══════════════════════════════════════════════════════════
    //  ACK
    // ═══════════════════════════════════════════════════════════

    public function ack(Job $job, string $workerId): bool
    {
        if (!$job->isPersisted()) {
            throw new \InvalidArgumentException('DbQueue::ack: job has no id');
        }

        try {
            $this->db->update_this(
                "UPDATE job_queue
                    SET status      = 'done',
                        reserved_at = NULL,
                        reserved_by = NULL,
                        updated_at  = NOW()
                  WHERE id = ?
                    AND status = 'processing'
                    AND reserved_by = ?
                  LIMIT 1",
                [$job->id, $workerId]
            );
        } catch (\Throwable $e) {
            $this->log('error', 'ack.exception', [
                'id'    => $job->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        $ok = $this->db->affectedRows() === 1;

        $this->log($ok ? 'info' : 'warning', 'ack', [
            'id'     => $job->id,
            'queue'  => $job->queue,
            'worker' => $workerId,
            'ok'     => $ok,
        ]);

        return $ok;
    }

    // ═══════════════════════════════════════════════════════════
    //  FAIL (with retry / terminal decision)
    // ═══════════════════════════════════════════════════════════

    public function fail(Job $job, string $error, int $retryInSeconds, string $workerId): bool
    {
        if (!$job->isPersisted()) {
            throw new \InvalidArgumentException('DbQueue::fail: job has no id');
        }

        $nextAttempts = $job->attempts + 1;
        $willRetry    = $nextAttempts < $job->maxAttempts;
        $errText      = $this->truncateError($error);

        if ($willRetry) {
            $delay = $retryInSeconds > 0
                ? $retryInSeconds
                : $this->backoffFor($nextAttempts);

            try {
                $this->db->update_this(
                    "UPDATE job_queue
                        SET status       = 'pending',
                            attempts     = ?,
                            available_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                            reserved_at  = NULL,
                            reserved_by  = NULL,
                            last_error   = ?,
                            updated_at   = NOW()
                      WHERE id = ?
                        AND status = 'processing'
                        AND reserved_by = ?
                      LIMIT 1",
                    [$nextAttempts, $delay, $errText, $job->id, $workerId]
                );
            } catch (\Throwable $e) {
                $this->log('error', 'fail.retry.exception', [
                    'id'    => $job->id,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            $ok = $this->db->affectedRows() === 1;

            $this->log('warning', 'fail.retry', [
                'id'           => $job->id,
                'queue'        => $job->queue,
                'worker'       => $workerId,
                'attempt'      => $nextAttempts,
                'max_attempts' => $job->maxAttempts,
                'retry_in'     => $delay,
                'error'        => $errText,
                'ok'           => $ok,
            ]);

            return $ok;
        }

        // Terminal failure.
        try {
            $this->db->update_this(
                "UPDATE job_queue
                    SET status      = 'failed',
                        attempts    = ?,
                        reserved_at = NULL,
                        reserved_by = NULL,
                        last_error  = ?,
                        updated_at  = NOW()
                  WHERE id = ?
                    AND status = 'processing'
                    AND reserved_by = ?
                  LIMIT 1",
                [$nextAttempts, $errText, $job->id, $workerId]
            );
        } catch (\Throwable $e) {
            $this->log('error', 'fail.terminal.exception', [
                'id'    => $job->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        $ok = $this->db->affectedRows() === 1;

        $this->log($ok ? 'error' : 'warning', 'fail.terminal', [
            'id'           => $job->id,
            'queue'        => $job->queue,
            'worker'       => $workerId,
            'attempts'     => $nextAttempts,
            'max_attempts' => $job->maxAttempts,
            'error'        => $errText,
            'ok'           => $ok,
        ]);

        return false;
    }

    // ═══════════════════════════════════════════════════════════
    //  SIZE
    // ═══════════════════════════════════════════════════════════

    public function size(string $queue): int
    {
        try {
            $rows = $this->db->getThisQuery(
                "SELECT COUNT(*) AS c
                   FROM job_queue
                  WHERE queue = ?
                    AND status IN ('pending','processing')",
                [$queue]
            );
        } catch (\Throwable $e) {
            return 0;
        }
        return (int)($rows[0]['c'] ?? 0);
    }

    // ═══════════════════════════════════════════════════════════
    //  RECOVER STALE
    // ═══════════════════════════════════════════════════════════

    public function recoverStale(int $staleAfterMinutes): int
    {
        $staleAfterMinutes = max(1, $staleAfterMinutes);

        try {
            $this->db->update_this(
                "UPDATE job_queue
                    SET status      = 'pending',
                        reserved_at = NULL,
                        reserved_by = NULL,
                        last_error  = CONCAT(
                            COALESCE(last_error, ''),
                            CASE WHEN last_error IS NULL OR last_error = '' THEN '' ELSE ' | ' END,
                            'recovered from stale claim'
                        ),
                        updated_at  = NOW()
                  WHERE status = 'processing'
                    AND reserved_at IS NOT NULL
                    AND reserved_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
                  LIMIT " . self::STALE_RECOVER_BATCH,
                [$staleAfterMinutes]
            );
        } catch (\Throwable $e) {
            $this->log('error', 'recover_stale.exception', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }

        $count = $this->db->affectedRows();

        if ($count > 0) {
            $this->log('warning', 'recover_stale', [
                'recovered'     => $count,
                'stale_after_m' => $staleAfterMinutes,
            ]);
        }

        return $count;
    }

    // ═══════════════════════════════════════════════════════════
    //  Internal helpers
    // ═══════════════════════════════════════════════════════════

    private function loadJobById(int $id): ?Job
    {
        try {
            $rows = $this->db->getThisQuery(
                "SELECT id, queue, job_type, user_id, store_id, order_id,
                        payload, idempotency_key, status, attempts, max_attempts,
                        available_at, reserved_at, reserved_by, last_error,
                        created_at, updated_at
                   FROM job_queue
                  WHERE id = ?
                  LIMIT 1",
                [$id]
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (empty($rows)) {
            return null;
        }

        return Job::fromRow($rows[0]);
    }

    private function backoffFor(int $attempt): int
    {
        $idx = max(0, $attempt - 1);
        return self::BACKOFF_SCHEDULE[$idx] ?? end(self::BACKOFF_SCHEDULE);
    }

    private function truncateError(string $error): string
    {
        // last_error is TEXT; still cap to keep logs and rows manageable.
        if (strlen($error) <= 4000) {
            return $error;
        }
        return substr($error, 0, 4000) . '…(truncated)';
    }

    /**
     * Structured logger. Writes JSON lines to $logFile, optionally
     * mirrors to error_log(). Respects $logLevel.
     *
     * @param array<string,mixed> $ctx
     */
    private function log(string $level, string $event, array $ctx = []): void
    {
        $levels = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40];
        $min = $levels[strtolower($this->logLevel)] ?? 20;
        if (($levels[$level] ?? 0) < $min) {
            return;
        }

        $line = json_encode([
            'ts'    => (new DateTimeImmutable('now'))->format('c'),
            'src'   => 'DbQueue',
            'level' => $level,
            'event' => $event,
            'ctx'   => $ctx,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($this->logFile !== '') {
            @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
} ?>