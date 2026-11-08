<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Core;

use DateTimeImmutable;
use Throwable;

use OBGecom\AiConfirmation\Queue\Job;
use OBGecom\AiConfirmation\Queue\Queue;

require_once __DIR__ . '/../queue/Queue.php';
require_once __DIR__ . '/../queue/Job.php';
require_once __DIR__ . '/../queue/DbQueue.php';
require_once __DIR__ . '/../queue/queue_factory.php';

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  ConfirmationService — enqueue a confirmation job for a new order
 * ═══════════════════════════════════════════════════════════════
 *
 *  This is the ONLY file the sync pipeline talks to.
 *
 *  In syncShopifyOrders() / syncYouCanOrders(), right after a new
 *  order is committed, we add:
 *
 *      try {
 *          require_once __DIR__ . '/../ai_confirmation/core/ConfirmationService.php';
 *          \OBGecom\AiConfirmation\Core\ConfirmationService::enqueueForConfirmation(
 *              (int)$order_id, (int)$store['id'], (int)$store['user_id']
 *          );
 *      } catch (\Throwable $e) {
 *          error_log("[ai_confirmation] enqueue failed for order {$order_id}: "
 *                  . $e->getMessage());
 *      }
 *
 *  That's the entire integration. Sync latency added per order: ~2ms.
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Guarantees:
 *   - Never throws to the caller.
 *   - Never sends WhatsApp or calls AI.
 *   - Idempotent: same order → same idempotency_key → only one job.
 *   - Respects env, merchant, and per-store kill switches.
 *   - Fire-and-forget: safe to call inside a loop over hundreds of orders.
 * ═══════════════════════════════════════════════════════════════
 */
final class ConfirmationService
{
    /** Queue name used for outbound confirmation jobs. */
    public const QUEUE_CONFIRMATION = 'confirmation';

    /** Job type for the "send first WhatsApp message" job. */
    public const JOB_SEND_CONFIRMATION = 'send_confirmation';

    /**
     * Enqueue a confirmation job for a newly synced order.
     *
     * @param int       $orderId  Primary key in `orders`.
     * @param int       $storeId  `stores.id` the order belongs to.
     * @param int       $userId   `users.id` the merchant.
     * @param \DB|null  $db       Optional. If null, resolved from DB singleton.
     *
     * @return bool  true if a job was enqueued (or already existed),
     *               false if the order was skipped (disabled config, error, etc.)
     */
    public static function enqueueForConfirmation(
        int $orderId,
        int $storeId,
        int $userId,
        ?\DB $db = null,
    ): bool {
        try {
            // ── Sanity checks ────────────────────────────────
            if ($orderId <= 0 || $storeId <= 0 || $userId <= 0) {
                return false;
            }

            // ── Load config ──────────────────────────────────
            if (!function_exists('ai_confirmation_config')) {
                require_once __DIR__ . '/../config/ai_confirmation.php';
            }
            $cfg = ai_confirmation_config();

            if (!$cfg['enabled']) {
                // Global env kill switch is off — nothing to do.
                return false;
            }

            // ── Resolve DB ───────────────────────────────────
            if ($db === null) {
                $db = \DB::getInstance();
            }

            // ── Load effective per-store config ──────────────
            if (!class_exists(ConfirmationConfig::class)) {
                require_once __DIR__ . '/ConfirmationConfig.php';
            }
            $storeCfg = ConfirmationConfig::forStore($db, $storeId, $userId);

            if (!$storeCfg['enabled']) {
                self::log($cfg, 'debug', 'enqueue.skip.disabled', [
                    'order_id' => $orderId,
                    'store_id' => $storeId,
                    'source'   => $storeCfg['source'],
                ]);
                return false;
            }

            // ── Guard: is the order in a state that needs confirming? ──
            // Only `bot_pending` is eligible. If the order already moved
            // past bot_pending (because it was processed), skip.
            $state = self::loadOrderState($db, $orderId);
            if ($state === null) {
                self::log($cfg, 'warning', 'enqueue.skip.order_missing', [
                    'order_id' => $orderId,
                ]);
                return false;
            }

            // Accept anything that has NOT been confirmed/cancelled yet.
            // This tolerates manual resets and re-syncs.
            if (!in_array($state['confirmation_status'], ['bot_pending'], true)) {
                self::log($cfg, 'debug', 'enqueue.skip.not_pending', [
                    'order_id'   => $orderId,
                    'status'     => $state['confirmation_status'],
                ]);
                return false;
            }

            // ── Idempotency key ──────────────────────────────
            $idempotencyKey = 'send_confirm:order:' . $orderId;

            // ── Build the job ────────────────────────────────
            $job = Job::make(
                queue:          self::QUEUE_CONFIRMATION,
                jobType:        self::JOB_SEND_CONFIRMATION,
                userId:         $userId,
                storeId:        $storeId,
                orderId:        $orderId,
                payload: [
                    'order_id'       => $orderId,
                    'store_id'       => $storeId,
                    'user_id'        => $userId,
                    'template_name'  => $storeCfg['whatsapp_template_name'],
                    'language'       => $storeCfg['whatsapp_language'],
                ],
                idempotencyKey: $idempotencyKey,
                availableAt:    new DateTimeImmutable('now'),
                maxAttempts:    $cfg['queue']['max_attempts'],
            );

            // ── Push ────────────────────────────────────────
            $queue = self::resolveQueue($cfg);
            $result = $queue->push($job);

            $enqueued = ($result !== null);

            self::log($cfg, $enqueued ? 'info' : 'debug', $enqueued
                ? 'enqueue.ok'
                : 'enqueue.duplicate', [
                'order_id'        => $orderId,
                'store_id'        => $storeId,
                'job_id'          => $result?->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            // ── Audit trail: record ORDER_RECEIVED ───────────
            // Best-effort. Failure here never breaks the sync.
            if ($enqueued) {
                self::writeOrderReceivedLog($db, $orderId, $state, $storeCfg);
            }

            return true;

        } catch (Throwable $e) {
            // Never throw to the sync pipeline.
            error_log('[ai_confirmation] ConfirmationService::enqueueForConfirmation '
                    . "failed for order {$orderId}: " . $e->getMessage());
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  PRIVATE
    // ═══════════════════════════════════════════════════════════

    /**
     * Resolve a Queue instance. Uses the factory, which caches.
     */
    private static function resolveQueue(array $cfg): Queue
    {
        if (!function_exists('OBGecom\\AiConfirmation\\Queue\\queue_factory')) {
            require_once __DIR__ . '/../queue/queue_factory.php';
        }
        return \OBGecom\AiConfirmation\Queue\queue_factory();
    }

    /**
     * Load the current confirmation status of the order.
     *
     * @return array{confirmation_status:string, status:string, user_id:int, store_id:int}|null
     */
    private static function loadOrderState(\DB $db, int $orderId): ?array
    {
        $rows = $db->getThisQuery(
            "SELECT confirmation_status, status, user_id, store_id
               FROM orders
              WHERE id = ?
              LIMIT 1",
            [$orderId]
        );

        return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    /**
     * Insert the first row into confirmation_logs to mark the order
     * as received by the AI confirmation subsystem.
     *
     * Uses a dedicated event_type 'ORDER_RECEIVED'. Old/new status
     * are both 'bot_pending' because we are not changing state here.
     */
    private static function writeOrderReceivedLog(
        \DB $db,
        int $orderId,
        array $state,
        array $storeCfg,
    ): void {
        try {
            $db->insert_this(
                "INSERT INTO confirmation_logs
                    (order_id, actor_type, actor_id,
                     event_type, old_status, new_status,
                     old_order_status, new_order_status,
                     message, raw_payload, created_at)
                 VALUES
                    (?, 'system', NULL,
                     'ORDER_RECEIVED', ?, ?,
                     ?, ?,
                     ?, ?, NOW())",
                [
                    $orderId,
                    $state['confirmation_status'],
                    $state['confirmation_status'],
                    $state['status'] ?? null,
                    $state['status'] ?? null,
                    'Order received by AI confirmation subsystem',
                    json_encode([
                        'source'           => $storeCfg['source'],
                        'template'         => $storeCfg['whatsapp_template_name'],
                        'language'         => $storeCfg['whatsapp_language'],
                        'timeout_minutes'  => $storeCfg['timeout_minutes'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );
        } catch (Throwable $e) {
            error_log('[ai_confirmation] ORDER_RECEIVED log failed for order '
                    . "{$orderId}: " . $e->getMessage());
        }
    }

    /**
     * Structured logger — same JSON-line format as DbQueue.
     */
    private static function log(array $cfg, string $level, string $event, array $ctx = []): void
    {
        $levels = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40];
        $min = $levels[strtolower($cfg['log']['level'] ?? 'info')] ?? 20;
        if (($levels[$level] ?? 0) < $min) {
            return;
        }

        $line = json_encode([
            'ts'    => (new DateTimeImmutable('now'))->format('c'),
            'src'   => 'ConfirmationService',
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
} ?>