<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Queue;

use DateTimeImmutable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  Job — immutable value object for one queue entry
 * ═══════════════════════════════════════════════════════════════
 *
 *  Every queue operation works with Job instances:
 *
 *      $job = Job::make(
 *          queue: 'confirmation',
 *          jobType: 'send_confirmation',
 *          userId: 42,
 *          storeId: 7,
 *          orderId: 123,
 *          payload: ['order_id' => 123],
 *          idempotencyKey: 'send_confirm:order:123',
 *          availableAt: new DateTimeImmutable('now'),
 *      );
 *      $queue->push($job);
 *
 *  Or, after loading from the DB:
 *
 *      $job = Job::fromRow($dbRow);
 *
 *  Jobs are immutable. To "modify" one, create a new one.
 *  Only `withId()` and `withStatus()` produce new instances, and
 *  they are used internally by the queue implementation.
 * ═══════════════════════════════════════════════════════════════
 */
final class Job
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE       = 'done';
    public const STATUS_FAILED     = 'failed';

    /** All statuses allowed in job_queue.status. */
    public const ALL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_DONE,
        self::STATUS_FAILED,
    ];

    /**
     * @param int|null             $id              DB-assigned id (null before insert)
     * @param string               $queue           e.g. 'confirmation', 'timeout', 'shipping'
     * @param string               $jobType         e.g. 'send_confirmation', 'check_timeout'
     * @param int                  $userId          Tenant: merchant user id
     * @param int|null             $storeId         Tenant: store id (nullable)
     * @param int|null             $orderId         Related order id (nullable)
     * @param array<string,mixed>  $payload         Arbitrary JSON payload
     * @param string               $idempotencyKey  Unique per logical job
     * @param string               $status          One of ALL_STATUSES
     * @param int                  $attempts        How many times this job has been attempted
     * @param int                  $maxAttempts     Terminal failure after this many
     * @param DateTimeImmutable    $availableAt     Do not process before this time
     * @param DateTimeImmutable|null $reservedAt    When it was claimed
     * @param string|null          $reservedBy      Worker identity
     * @param string|null          $lastError       Last failure message
     * @param DateTimeImmutable|null $createdAt
     * @param DateTimeImmutable|null $updatedAt
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $queue,
        public readonly string $jobType,
        public readonly int $userId,
        public readonly ?int $storeId,
        public readonly ?int $orderId,
        public readonly array $payload,
        public readonly string $idempotencyKey,
        public readonly string $status,
        public readonly int $attempts,
        public readonly int $maxAttempts,
        public readonly DateTimeImmutable $availableAt,
        public readonly ?DateTimeImmutable $reservedAt,
        public readonly ?string $reservedBy,
        public readonly ?string $lastError,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt,
    ) {
        // ── Invariant checks (throw on programming errors) ───
        if ($this->queue === '') {
            throw new \InvalidArgumentException('Job: queue must not be empty');
        }
        if ($this->jobType === '') {
            throw new \InvalidArgumentException('Job: jobType must not be empty');
        }
        if ($this->idempotencyKey === '') {
            throw new \InvalidArgumentException('Job: idempotencyKey must not be empty');
        }
        if (!in_array($this->status, self::ALL_STATUSES, true)) {
            throw new \InvalidArgumentException("Job: invalid status '{$this->status}'");
        }
        if ($this->attempts < 0) {
            throw new \InvalidArgumentException('Job: attempts must be >= 0');
        }
        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('Job: maxAttempts must be >= 1');
        }

        if ($this->userId < 0) {
            throw new \InvalidArgumentException('Job: userId must be >= 0');
        }
    }

    /**
     * Factory for creating a new job to enqueue.
     * The `id` is null (assigned by the DB on insert).
     * `status` defaults to 'pending'.
     * `attempts` defaults to 0.
     */
    public static function make(
        string $queue,
        string $jobType,
        int $userId,
        ?int $storeId,
        ?int $orderId,
        array $payload,
        string $idempotencyKey,
        ?DateTimeImmutable $availableAt = null,
        int $maxAttempts = 3,
    ): self {
        return new self(
            id: null,
            queue: $queue,
            jobType: $jobType,
            userId: $userId,
            storeId: $storeId,
            orderId: $orderId,
            payload: $payload,
            idempotencyKey: $idempotencyKey,
            status: self::STATUS_PENDING,
            attempts: 0,
            maxAttempts: $maxAttempts,
            availableAt: $availableAt ?? new DateTimeImmutable('now'),
            reservedAt: null,
            reservedBy: null,
            lastError: null,
            createdAt: null,
            updatedAt: null,
        );
    }

    /**
     * Hydrate a Job from a DB row (associative array).
     * Column names must match the job_queue schema exactly.
     *
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:            isset($row['id']) ? (int)$row['id'] : null,
            queue:         (string)$row['queue'],
            jobType:       (string)$row['job_type'],
            userId:        (int)$row['user_id'],
            storeId:       isset($row['store_id']) && $row['store_id'] !== null
                                ? (int)$row['store_id'] : null,
            orderId:       isset($row['order_id']) && $row['order_id'] !== null
                                ? (int)$row['order_id'] : null,
            payload:       self::decodePayload($row['payload'] ?? '{}'),
            idempotencyKey:(string)$row['idempotency_key'],
            status:        (string)$row['status'],
            attempts:      (int)($row['attempts'] ?? 0),
            maxAttempts:   (int)($row['max_attempts'] ?? 3),
            availableAt:   self::parseDate($row['available_at'] ?? null) ?? new DateTimeImmutable('now'),
            reservedAt:    self::parseDate($row['reserved_at'] ?? null),
            reservedBy:    isset($row['reserved_by']) && $row['reserved_by'] !== ''
                                ? (string)$row['reserved_by'] : null,
            lastError:     isset($row['last_error']) && $row['last_error'] !== ''
                                ? (string)$row['last_error'] : null,
            createdAt:     self::parseDate($row['created_at'] ?? null),
            updatedAt:     self::parseDate($row['updated_at'] ?? null),
        );
    }

    /**
     * Return a copy with the DB-assigned id set.
     * Used internally by DbQueue::push() after the INSERT.
     */
    public function withId(int $id): self
    {
        return new self(
            id: $id,
            queue: $this->queue,
            jobType: $this->jobType,
            userId: $this->userId,
            storeId: $this->storeId,
            orderId: $this->orderId,
            payload: $this->payload,
            idempotencyKey: $this->idempotencyKey,
            status: $this->status,
            attempts: $this->attempts,
            maxAttempts: $this->maxAttempts,
            availableAt: $this->availableAt,
            reservedAt: $this->reservedAt,
            reservedBy: $this->reservedBy,
            lastError: $this->lastError,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    /**
     * Return a copy with the status changed.
     * Does not touch attempts, reservedAt, etc.
     */
    public function withStatus(string $status): self
    {
        if (!in_array($status, self::ALL_STATUSES, true)) {
            throw new \InvalidArgumentException("Job::withStatus: invalid status '{$status}'");
        }
        return new self(
            id: $this->id,
            queue: $this->queue,
            jobType: $this->jobType,
            userId: $this->userId,
            storeId: $this->storeId,
            orderId: $this->orderId,
            payload: $this->payload,
            idempotencyKey: $this->idempotencyKey,
            status: $status,
            attempts: $this->attempts,
            maxAttempts: $this->maxAttempts,
            availableAt: $this->availableAt,
            reservedAt: $this->reservedAt,
            reservedBy: $this->reservedBy,
            lastError: $this->lastError,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
        );
    }

    /**
     * Has the job exhausted its retries?
     */
    public function isExhausted(): bool
    {
        return $this->attempts >= $this->maxAttempts;
    }

    /**
     * Has the job been assigned an id (i.e. persisted)?
     */
    public function isPersisted(): bool
    {
        return $this->id !== null;
    }

    /**
     * Is this job due for processing right now?
     */
    public function isDue(?DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new DateTimeImmutable('now');
        return $this->availableAt <= $now;
    }

    /**
     * Safe array representation for logging.
     * Payload is included but shortened if huge.
     *
     * @return array<string,mixed>
     */
    public function toLogArray(): array
    {
        return [
            'id'               => $this->id,
            'queue'            => $this->queue,
            'job_type'         => $this->jobType,
            'user_id'          => $this->userId,
            'store_id'         => $this->storeId,
            'order_id'         => $this->orderId,
            'status'           => $this->status,
            'attempts'         => $this->attempts,
            'max_attempts'     => $this->maxAttempts,
            'available_at'     => $this->availableAt->format('c'),
            'reserved_by'      => $this->reservedBy,
            'idempotency_key'  => $this->idempotencyKey,
            'payload_preview'  => self::previewPayload($this->payload),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    private static function decodePayload(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        try {
            return new DateTimeImmutable((string)$value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Truncate payload for logging so we never dump megabytes.
     */
    private static function previewPayload(array $payload): array|string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '(unencodable)';
        }
        if (strlen($json) <= 500) {
            return $payload;
        }
        return substr($json, 0, 500) . '…(' . strlen($json) . ' bytes total)';
    }
} ?>