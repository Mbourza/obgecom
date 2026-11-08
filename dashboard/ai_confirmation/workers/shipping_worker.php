<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  shipping_worker.php — submit confirmed orders to shipping
 * ═══════════════════════════════════════════════════════════════
 *
 *  Consumes the `shipping` queue and handles `submit_shipping` jobs.
 *
 *  Every job is one order. The worker:
 *    1. Claims the order via orders.shipping_request_id (idempotent).
 *    2. Loads the full order + store row.
 *    3. Calls ShippingDispatcher::submit() — which reuses the
 *       existing handleShippingCompanyNotification() in
 *       controllers/upTo_shipping.php.
 *    4. Updates orders.shipping_status / tracking_number on success.
 *    5. On business failure → manual_required (merchant handles).
 *    6. On transient failure → throws → retried by the queue.
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Run:
 *      php ai_confirmation/workers/shipping_worker.php
 *      php ai_confirmation/workers/shipping_worker.php --once
 * ═══════════════════════════════════════════════════════════════
 */

use OBGecom\AiConfirmation\Core\ConfirmationStateMachine as SM;
use OBGecom\AiConfirmation\Core\ShippingDispatcher;

$ctx = require __DIR__ . '/_bootstrap.php';

$db        = $ctx['db'];
$queue     = $ctx['queue'];
$cfg       = $ctx['cfg'];
$workerId  = $ctx['worker_id'];
$args      = $ctx['args'];
$queueName = $args['queue'] ?? 'shipping';

require_once __DIR__ . '/../core/ConfirmationStateMachine.php';
require_once __DIR__ . '/../core/ShippingDispatcher.php';

worker_log('info', 'shipping_worker.start', [
    'queue' => $queueName,
    'once'  => $args['once'],
]);

$processed  = 0;
$maxJobs    = (int)$cfg['worker']['max_jobs'];
$pollMs     = (int)$cfg['queue']['poll_interval_ms'];
$idleLogged = false;

while (!worker_should_stop()) {

    if ($maxJobs > 0 && $processed >= $maxJobs) {
        worker_log('info', 'shipping_worker.max_jobs_reached', ['processed' => $processed]);
        break;
    }

    try {
        $job = $queue->reserve($queueName, $workerId);
    } catch (\Throwable $e) {
        worker_log('error', 'reserve.failed', ['error' => $e->getMessage()]);
        worker_sleep_ms(2000);
        continue;
    }

    if ($job === null) {
        if (!$idleLogged) {
            worker_log('debug', 'shipping_worker.idle', ['queue' => $queueName]);
            $idleLogged = true;
        }
        worker_sleep_ms($pollMs);
        continue;
    }
    $idleLogged = false;

    if ($job->jobType !== 'submit_shipping') {
        worker_log('warning', 'shipping_worker.unknown_job_type', [
            'job_id'   => $job->id,
            'job_type' => $job->jobType,
        ]);
        $queue->ack($job, $workerId);
        continue;
    }

    try {
        $result = handleSubmitShipping($db, $job, $cfg, $workerId);

        if ($result === true || $result === 'skip') {
            $queue->ack($job, $workerId);
            $processed++;
            worker_log('info', 'shipping_worker.job_done', [
                'job_id'    => $job->id,
                'order_id'  => $job->orderId,
                'result'    => $result === true ? 'submitted' : 'skip',
                'processed' => $processed,
            ]);
        } else {
            $queue->fail($job, 'unexpected handler return', 60, $workerId);
        }

    } catch (\Throwable $e) {
        worker_log('error', 'shipping_worker.job_failed', [
            'job_id' => $job->id,
            'error'  => $e->getMessage(),
        ]);
        // Shipping APIs are slow and rate-limited; use a longer backoff.
        $backoff = 180 * (int)max(1, $job->attempts + 1);
        $queue->fail($job, $e->getMessage(), $backoff, $workerId);
    }

    if ($args['once']) break;
}

worker_log('info', 'shipping_worker.exit', [
    'processed' => $processed,
    'signal'    => worker_last_signal(),
]);
exit(0);


// ═════════════════════════════════════════════════════════════════
//  HANDLER
// ═════════════════════════════════════════════════════════════════

/**
 * @return true|'skip'
 */
function handleSubmitShipping(
    \DB $db,
    \OBGecom\AiConfirmation\Queue\Job $job,
    array $cfg,
    string $workerId,
): bool|string {

    $orderId = (int)($job->payload['order_id'] ?? $job->orderId ?? 0);
    if ($orderId <= 0) {
        worker_log('warning', 'submit_shipping.bad_payload', ['job_id' => $job->id]);
        return 'skip';
    }

    // ── 1. Load order ─────────────────────────────────────────
    $order = loadOrderForShipping($db, $orderId);
    if ($order === null) {
        worker_log('warning', 'submit_shipping.order_missing', ['order_id' => $orderId]);
        return 'skip';
    }

    $userId  = (int)$order['user_id'];
    $storeId = (int)$order['store_id'];

    // ── 2. Guard: must be confirmed ───────────────────────────
    if (!in_array((string)$order['confirmation_status'], ['confirmed_by_bot', 'confirmed_by_agent'], true)
        && (string)$order['status'] !== 'confirmed'
    ) {
        worker_log('info', 'submit_shipping.not_confirmed', [
            'order_id'     => $orderId,
            'conf_status'  => $order['confirmation_status'],
            'order_status' => $order['status'],
        ]);
        return 'skip';
    }

    // ── 3. Guard: already shipped? ────────────────────────────
    $alreadyShippedStatuses = ['processing', 'collected', 'in_transit', 'out_for_delivery', 'delivered'];
    if (in_array((string)$order['shipping_status'], $alreadyShippedStatuses, true)) {
        worker_log('info', 'submit_shipping.already_shipped', [
            'order_id'        => $orderId,
            'shipping_status' => $order['shipping_status'],
        ]);
        return 'skip';
    }

    // ── 4. Claim via shipping_request_id (idempotency) ────────
    // Deterministic key based on order + store.
    $idem = 'ship:' . $orderId . ':' . $storeId;

    try {
        $db->update_this(
            "UPDATE orders
                SET shipping_request_id = ?,
                    updated_at          = NOW()
              WHERE id = ?
                AND shipping_request_id IS NULL
              LIMIT 1",
            [$idem, $orderId]
        );
    } catch (\Throwable $e) {
        worker_log('warning', 'submit_shipping.claim_failed', [
            'order_id' => $orderId,
            'error'    => $e->getMessage(),
        ]);
        return 'skip';
    }

    if ($db->affectedRows() !== 1) {
        worker_log('info', 'submit_shipping.already_claimed', [
            'order_id' => $orderId,
            'idem'     => $idem,
        ]);
        return 'skip';
    }

    // ── 5. Build the order row for the shipping handler ───────
    // The existing handler expects an associative array with the
    // same shape as `SELECT o.*, s.platform, s.api_url, ... FROM
    // orders o JOIN stores s ON o.store_id = s.id`.
    $orderForShipping = loadOrderWithStore($db, $orderId, $userId);
    if ($orderForShipping === null) {
        // Release the claim so a retry can succeed
        releaseClaim($db, $orderId);
        throw new \RuntimeException('order lost between claim and load');
    }

    // ── 6. Dispatch to shipping ───────────────────────────────
    $result = ShippingDispatcher::submit($db, $orderForShipping, $userId);

    $ok               = (bool)($result['ok']                ?? false);
    $notificationSent = (bool)($result['notification_sent'] ?? false);
    $companyName      = $result['company_name']    ?? null;
    $trackingNumber   = $result['tracking_number'] ?? null;
    $message          = $result['message']         ?? null;
    $duplicateFound   = (bool)($result['raw']['duplicate_found'] ?? false);

    // ── 7. Handle outcomes ────────────────────────────────────

    // ── 7a. Success ───────────────────────────────────────────
    if ($ok && $notificationSent) {
        updateOrderShipped($db, $orderId, $companyName, $trackingNumber, 'processing');

        writeShippingLog($db, $orderId, 'SHIPPING_SUBMITTED', [
            'company'         => $companyName,
            'tracking_number' => $trackingNumber,
            'message'         => $message,
        ]);

        worker_log('info', 'submit_shipping.ok', [
            'order_id'        => $orderId,
            'company'         => $companyName,
            'tracking_number' => $trackingNumber,
        ]);
        return true;
    }

    // ── 7b. Duplicate detected (upstream already has this parcel) ──
    if ($duplicateFound && $trackingNumber) {
        updateOrderShipped($db, $orderId, $companyName, $trackingNumber, 'processing');

        writeShippingLog($db, $orderId, 'SHIPPING_DUPLICATE_DETECTED', [
            'company'         => $companyName,
            'tracking_number' => $trackingNumber,
            'message'         => $message,
        ]);

        worker_log('info', 'submit_shipping.duplicate', [
            'order_id'        => $orderId,
            'tracking_number' => $trackingNumber,
        ]);
        return true;
    }

    // ── 7c. Business failure ──────────────────────────────────
    $isTransient = isTransientShippingError($result, $message);

    if ($isTransient) {
        // Release the claim so the retry can run.
        releaseClaim($db, $orderId);
        throw new \RuntimeException(
            'transient shipping error: ' . ($message ?? 'unknown')
        );
    }

    // Terminal business failure → manual_required
    SM::transition(
        db:      $db,
        orderId: $orderId,
        from:    (string)$order['confirmation_status'],
        to:      'manual_required',
        event:   'SHIPPING_FAILED',
        actor:   'system',
        message: 'Shipping submission failed: ' . ($message ?? 'unknown'),
        metadata: [
            'company'  => $companyName,
            'message'  => $message,
            'raw_keys' => array_keys($result['raw'] ?? []),
        ],
    );

    writeShippingLog($db, $orderId, 'SHIPPING_FAILED', [
        'company' => $companyName,
        'message' => $message,
    ]);

    worker_log('warning', 'submit_shipping.business_failure', [
        'order_id' => $orderId,
        'company'  => $companyName,
        'message'  => $message,
    ]);

    return 'skip';
}

// ═════════════════════════════════════════════════════════════════
//  HELPERS
// ═════════════════════════════════════════════════════════════════

function loadOrderForShipping(\DB $db, int $orderId): ?array
{
    $rows = $db->getThisQuery(
        "SELECT id, user_id, store_id, order_number, customer_name,
                customer_phone, confirmation_status, status,
                shipping_status, tracking_number, shipping_request_id
           FROM orders
          WHERE id = ?
          LIMIT 1",
        [$orderId]
    );

    return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
}

/**
 * Load the full order + store row in the shape the shipping handler expects.
 */
function loadOrderWithStore(\DB $db, int $orderId, int $userId): ?array
{
    $rows = $db->getThisQuery(
        "SELECT o.*, s.platform, s.api_url, s.api_key,
                s.Consumer_key, s.Consumer_secret, s.domain,
                s.refresh_token, s.token_expires_at
           FROM orders o
           JOIN stores s ON o.store_id = s.id
          WHERE o.id = ? AND o.user_id = ?
          LIMIT 1",
        [$orderId, $userId]
    );

    return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
}

/**
 * Persist the shipping result on the order.
 *
 * Split into multiple statements because DB::update_this() binds
 * PHP null as '', which would overwrite a valid tracking_number
 * or shipping_company_id with an empty value. We update those
 * fields only when we have a value for them.
 */
function updateOrderShipped(
    \DB $db,
    int $orderId,
    ?string $companyName,
    ?string $trackingNumber,
    string $shippingStatus = 'processing',
): void {
    try {
        // Always update the shipping status.
        $db->update_this(
            "UPDATE orders
                SET shipping_status = ?,
                    updated_at      = NOW()
              WHERE id = ?",
            [$shippingStatus, $orderId]
        );

        // Tracking number, only when we have one.
        if ($trackingNumber !== null && $trackingNumber !== '') {
            $db->update_this(
                "UPDATE orders
                    SET tracking_number = ?
                  WHERE id = ?",
                [$trackingNumber, $orderId]
            );
        }

        // Shipping company id, only when we know the company name.
        if ($companyName !== null && $companyName !== '') {
            // Look up the id first (safe read), then update only
            // if a matching company exists.
            $companyRows = $db->getThisQuery(
                "SELECT id FROM shipping_companies WHERE name = ? LIMIT 1",
                [$companyName]
            );
            if (is_array($companyRows) && isset($companyRows[0]['id'])) {
                $db->update_this(
                    "UPDATE orders
                        SET shipping_company_id = ?
                      WHERE id = ?",
                    [(int)$companyRows[0]['id'], $orderId]
                );
            }
        }
    } catch (\Throwable $e) {
        worker_log('warning', 'submit_shipping.update_order_failed', [
            'order_id' => $orderId,
            'error'    => $e->getMessage(),
        ]);
    }
}

/**
 * Release the shipping_request_id claim so a retry can succeed.
 */
function releaseClaim(\DB $db, int $orderId): void
{
    try {
        $db->update_this(
            "UPDATE orders
                SET shipping_request_id = NULL,
                    updated_at = NOW()
              WHERE id = ?",
            [$orderId]
        );
    } catch (\Throwable $e) {
        worker_log('warning', 'submit_shipping.release_claim_failed', [
            'order_id' => $orderId,
        ]);
    }
}

/**
 * Append a shipping-related row to confirmation_logs.
 * Uses the same table as the state machine so the audit trail is unified.
 */
function writeShippingLog(\DB $db, int $orderId, string $event, array $metadata): void
{
    try {
        // actor_id is written as SQL literal NULL (not a bound null)
        // because DB::insert_this() binds PHP null as ''.
        $db->insert_this(
            "INSERT INTO confirmation_logs
                (order_id, actor_type, actor_id,
                 event_type, message, raw_payload, created_at)
             VALUES (?, 'system', NULL, ?, ?, ?, NOW())",
            [
                $orderId,
                $event,
                $metadata['message'] ?? null,
                json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
    } catch (\Throwable $e) {
        worker_log('warning', 'submit_shipping.log_failed', [
            'order_id' => $orderId,
            'event'    => $event,
        ]);
    }
}

/**
 * Heuristic: classify a shipping failure as transient or terminal.
 * Terminal failures (bad city, missing field, invalid phone) should
 * not be retried — they need human intervention.
 */
function isTransientShippingError(array $result, ?string $message): bool
{
    if ($message === null) return false;

    $needles = [
        'timeout', 'timed out', 'temporarily unavailable',
        'bad gateway', '502', '503', '504',
        'connection reset', 'connection refused', 'network',
        'curl error', 'ssl', 'dns', 'temporary',
    ];
    $m = strtolower($message);
    foreach ($needles as $n) {
        if (strpos($m, $n) !== false) return true;
    }
    return false;
} ?>