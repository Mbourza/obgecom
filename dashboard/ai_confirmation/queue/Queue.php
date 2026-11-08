<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Queue;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  Queue — driver-agnostic contract
 * ═══════════════════════════════════════════════════════════════
 *
 *  Every queue backend (DbQueue today, RedisQueue later) must
 *  implement this interface. Nothing outside the queue package
 *  ever touches a driver directly — they only see Queue.
 *
 *  Usage from a worker:
 *
 *      $job = $queue->reserve('confirmation', $workerId);
 *      if ($job === null) {
 *          usleep($pollIntervalMs * 1000);
 *          continue;
 *      }
 *      try {
 *          handle($job);
 *          $queue->ack($job);
 *      } catch (\Throwable $e) {
 *          $queue->fail($job, $e->getMessage(), 60);
 *      }
 *
 *  Usage from a producer (ConfirmationService):
 *
 *      $job = Job::make(...);
 *      $result = $queue->push($job);
 *      if ($result === null) {
 *          // duplicate — already enqueued, that's fine
 *      }
 *
 *  Contract guarantees for every implementation:
 *
 *  1. push() is idempotent on idempotency_key.
 *  2. reserve() never returns the same job to two workers
 *     at the same time (safe under N concurrent workers).
 *  3. ack() and fail() only succeed if the job is currently
 *     in `processing` and was reserved by the calling worker.
 *  4. fail() retries up to max_attempts, then marks the job
 *     `failed` and returns false. Backoff is the driver's job.
 *  5. No method throws for a "normal" outcome. Exceptions mean
 *     infrastructure failure (DB down, etc.).
 * ═══════════════════════════════════════════════════════════════
 */
interface Queue
{
    /**
     * Enqueue a new job.
     *
     * If a job with the same `idempotencyKey` already exists,
     * return null and do not insert. This is the single source of
     * truth for "the same logical job must not be processed twice".
     *
     * @param  Job $job  Job to enqueue (id must be null)
     * @return Job|null  The persisted job with its assigned id,
     *                   or null if a duplicate already existed
     *
     * @throws \RuntimeException on infrastructure failure
     */
    public function push(Job $job): ?Job;

    /**
     * Attempt to claim the next due job from a queue.
     *
     * Only jobs with:
     *      queue = :queue
     *      status = 'pending'
     *      available_at <= NOW()
     * are considered. The driver atomically transitions the
     * chosen job to `processing`, stamps reserved_by/reserved_at,
     * and returns it.
     *
     * Safe under N concurrent workers. Two simultaneous calls
     * never receive the same job.
     *
     * @param  string     $queue     Queue name (e.g. 'confirmation')
     * @param  string     $workerId  Worker identity (hostname:pid)
     * @return Job|null              A reserved job, or null if none due
     *
     * @throws \RuntimeException on infrastructure failure
     */
    public function reserve(string $queue, string $workerId): ?Job;

    /**
     * Mark a job as successfully completed.
     *
     * Only succeeds if:
     *      - the job is currently `processing`
     *      - reserved_by matches the given worker
     *
     * @return bool true if the job was transitioned to `done`
     *
     * @throws \RuntimeException on infrastructure failure
     */
    public function ack(Job $job, string $workerId): bool;

    /**
     * Record a failure.
     *
     * Behaviour:
     *      - If attempts + 1 < max_attempts:
     *            status back to 'pending'
     *            attempts += 1
     *            available_at = NOW() + $retryInSeconds
     *            last_error = $error
     *        Returns true (job will be retried).
     *
     *      - Otherwise:
     *            status = 'failed'
     *            attempts += 1
     *            last_error = $error
     *        Returns false (terminal).
     *
     * Only succeeds if the job is currently `processing` and
     * reserved_by matches the given worker.
     *
     * @param int $retryInSeconds  Backoff before the next attempt
     *
     * @throws \RuntimeException on infrastructure failure
     */
    public function fail(Job $job, string $error, int $retryInSeconds, string $workerId): bool;

    /**
     * Approximate depth of a queue.
     *
     * Counts only jobs that are `pending` or `processing` —
     * the two states that represent outstanding work. Does not
     * include `done` or `failed`.
     *
     * Used for monitoring dashboards and health checks.
     */
    public function size(string $queue): int;

    /**
     * Release jobs whose worker has died.
     *
     * Called by the watchdog at intervals. Any job in `processing`
     * whose `reserved_at` is older than the given number of minutes
     * is returned to `pending` so another worker can pick it up.
     *
     * Idempotent and safe to call from multiple processes.
     *
     * @return int  Number of jobs recovered
     *
     * @throws \RuntimeException on infrastructure failure
     */
    public function recoverStale(int $staleAfterMinutes): int;
} ?>