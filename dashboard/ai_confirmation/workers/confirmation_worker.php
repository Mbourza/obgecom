<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  confirmation_worker.php
 * ═══════════════════════════════════════════════════════════════
 *
 *  Consumes the `confirmation` queue and handles `send_confirmation`
 *  jobs. Sends the FIRST WhatsApp message, transitions the order to
 *  bot_first_message_sent, sets the deadline, and enqueues the
 *  delayed timeout job.
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Run:
 *      php ai_confirmation/workers/confirmation_worker.php
 *      php ai_confirmation/workers/confirmation_worker.php --once
 *      php ai_confirmation/workers/confirmation_worker.php --verbose
 *      php ai_confirmation/workers/confirmation_worker.php --poll=500
 *
 *  Under systemd (recommended) — see README for unit file.
 * ═══════════════════════════════════════════════════════════════
 */

use OBGecom\AiConfirmation\Core\ConfirmationConfig;
use OBGecom\AiConfirmation\Core\ConfirmationStateMachine as SM;
use OBGecom\AiConfirmation\Queue\Job;
use OBGecom\AiConfirmation\WhatsApp\WhatsAppService;

$ctx = require __DIR__ . '/_bootstrap.php';

$db        = $ctx['db'];
$queue     = $ctx['queue'];
$cfg       = $ctx['cfg'];
$workerId  = $ctx['worker_id'];
$args      = $ctx['args'];
$queueName = $args['queue'] ?? 'confirmation';

require_once __DIR__ . '/../core/ConfirmationStateMachine.php';
require_once __DIR__ . '/../core/ConfirmationConfig.php';
require_once __DIR__ . '/../whatsapp/WhatsAppService.php';

worker_log('info', 'confirmation_worker.start', [
    'queue' => $queueName,
    'once'  => $args['once'],
]);

// ─────────────────────────────────────────────────────────────
//  Main loop
// ─────────────────────────────────────────────────────────────
$processed  = 0;
$maxJobs    = (int)$cfg['worker']['max_jobs'];
$pollMs     = (int)$cfg['queue']['poll_interval_ms'];
$idleLogged = false;

while (!worker_should_stop()) {

    if ($maxJobs > 0 && $processed >= $maxJobs) {
        worker_log('info', 'confirmation_worker.max_jobs_reached', [
            'processed' => $processed,
        ]);
        break;
    }

    try {
        $job = $queue->reserve($queueName, $workerId);
    } catch (\Throwable $e) {
        worker_log('error', 'reserve.failed', [
            'error' => $e->getMessage(),
        ]);
        worker_sleep_ms(2000);
        continue;
    }

    if ($job === null) {
        if (!$idleLogged) {
            worker_log('debug', 'confirmation_worker.idle', [
                'queue' => $queueName,
            ]);
            $idleLogged = true;
        }
        worker_sleep_ms($pollMs);
        continue;
    }
    $idleLogged = false;

    if ($job->jobType !== 'send_confirmation') {
        worker_log('warning', 'confirmation_worker.unknown_job_type', [
            'job_id'   => $job->id,
            'job_type' => $job->jobType,
        ]);
        $queue->ack($job, $workerId);
        continue;
    }

    try {
        $result = handleSendConfirmation($db, $queue, $job, $cfg, $workerId);

        if ($result === true) {
            $queue->ack($job, $workerId);
            $processed++;
            worker_log('info', 'confirmation_worker.job_done', [
                'job_id'    => $job->id,
                'order_id'  => $job->orderId,
                'processed' => $processed,
            ]);
        } elseif ($result === 'skip') {
            $queue->ack($job, $workerId);
            $processed++;
            worker_log('debug', 'confirmation_worker.job_skipped', [
                'job_id'   => $job->id,
                'order_id' => $job->orderId,
            ]);
        } else {
            $queue->fail($job, 'unexpected handler return', 60, $workerId);
        }

    } catch (\Throwable $e) {
        worker_log('error', 'confirmation_worker.job_failed', [
            'job_id'   => $job->id,
            'order_id' => $job->orderId,
            'error'    => $e->getMessage(),
            'class'    => get_class($e),
        ]);

        $backoffSeconds = 60 * (int)max(1, $job->attempts + 1);
        $queue->fail($job, $e->getMessage(), $backoffSeconds, $workerId);
    }

    if ($args['once']) {
        worker_log('info', 'confirmation_worker.once_done', [
            'processed' => $processed,
        ]);
        break;
    }
}

worker_log('info', 'confirmation_worker.exit', [
    'processed' => $processed,
    'signal'    => worker_last_signal(),
]);
exit(0);


// ═════════════════════════════════════════════════════════════════
//  HANDLER
// ═════════════════════════════════════════════════════════════════

/**
 * Handle a single `send_confirmation` job.
 *
 * @return true   — job completed successfully
 * @return 'skip' — job should be acked without action (order moved)
 * @throws \Throwable on genuine failure (triggers retry)
 */
function handleSendConfirmation(
    \DB $db,
    \OBGecom\AiConfirmation\Queue\Queue $queue,
    Job $job,
    array $cfg,
    string $workerId,
): bool|string {

    $orderId = (int)($job->payload['order_id'] ?? $job->orderId ?? 0);
    $storeId = (int)($job->payload['store_id'] ?? $job->storeId ?? 0);
    $userId  = (int)($job->payload['user_id']  ?? $job->userId  ?? 0);

    if ($orderId <= 0 || $storeId <= 0 || $userId <= 0) {
        throw new RuntimeException(
            'send_confirmation job missing order_id/store_id/user_id in payload'
        );
    }

    // ── 1. Load order ──────────────────────────────────────────
    $order = loadOrder($db, $orderId, $userId);
    if ($order === null) {
        worker_log('warning', 'send_confirmation.order_missing', [
            'order_id' => $orderId,
        ]);
        return 'skip';
    }

    // ── 2. Guard: only proceed if order is still bot_pending ───
    if ($order['confirmation_status'] !== 'bot_pending') {
        worker_log('debug', 'send_confirmation.not_eligible', [
            'order_id' => $orderId,
            'status'   => $order['confirmation_status'],
        ]);
        return 'skip';
    }

    // ── 3. Guard: customer phone must exist ────────────────────
    if (empty(trim((string)$order['customer_phone']))) {
        worker_log('warning', 'send_confirmation.no_phone', [
            'order_id' => $orderId,
        ]);

        // Escalate directly to manual_required_unassigned — a human
        // needs to reach this customer.
        SM::transition(
            db:      $db,
            orderId: $orderId,
            from:    'bot_pending',
            to:      'manual_required_unassigned',
            event:   'NO_PHONE_NUMBER',
            actor:   'system',
            message: 'Order has no customer phone number',
        );
        return 'skip';
    }

    // ── 4. Load effective store config ─────────────────────────
    ConfirmationConfig::clearCache($storeId);
    $storeCfg = ConfirmationConfig::forStore($db, $storeId, $userId);

    // ── 5. Build the message text (for logging / text mode) ────
    $messageText = buildConfirmationMessage($order, $storeCfg);

    // ── 6. Send ────────────────────────────────────────────────
    $sendResult = sendConfirmationMessage(
        $db,
        $order,
        $storeCfg,
        $messageText,
        $userId,
    );

    if (!$sendResult['ok']) {
        throw new RuntimeException(
            'WhatsApp send failed: ' . ($sendResult['error'] ?? 'unknown')
        );
    }

    // ── 7. Transition state (atomic; may fail if raced) ────────
    $deadline = (new DateTimeImmutable('now'))
        ->modify('+' . $storeCfg['timeout_minutes'] . ' minutes');

    $moved = SM::transition(
        db:      $db,
        orderId: $orderId,
        from:    'bot_pending',
        to:      'bot_first_message_sent',
        event:   'WHATSAPP_CONFIRMATION_SENT',
        actor:   'system',
        message: 'Initial WhatsApp confirmation message sent',
        metadata: [
            'provider'            => '360dialog',
            'provider_message_id' => $sendResult['provider_message_id'] ?? null,
            'template'            => $storeCfg['whatsapp_template_name'],
            'language'            => $storeCfg['whatsapp_language'],
            'phone'               => maskPhone($order['customer_phone']),
            'deadline'            => $deadline->format('Y-m-d H:i:s'),
        ],
    );

    if (!$moved) {
        worker_log('warning', 'send_confirmation.transition_raced', [
            'order_id' => $orderId,
        ]);
        return 'skip';
    }

    // ── 8. Persist deadline on the order ───────────────────────
    try {
        $db->update_this(
            "UPDATE orders
                SET confirmation_deadline = ?,
                    bot_sent              = 1,
                    bot_sent_at           = NOW(),
                    last_bot_message_at   = NOW(),
                    bot_attempts          = bot_attempts + 1,
                    updated_at            = NOW()
              WHERE id = ?",
            [$deadline->format('Y-m-d H:i:s'), $orderId]
        );
    } catch (\Throwable $e) {
        worker_log('warning', 'send_confirmation.deadline_update_failed', [
            'order_id' => $orderId,
            'error'    => $e->getMessage(),
        ]);
        // Non-fatal. The state is already correct.
    }

    // ── 9. Enqueue delayed timeout job ─────────────────────────
    $timeoutJob = Job::make(
        queue:          'timeout',
        jobType:        'check_timeout',
        userId:         $userId,
        storeId:        $storeId,
        orderId:        $orderId,
        payload: [
            'order_id'        => $orderId,
            'stage'           => 'first',
            'expected_status' => 'bot_first_message_sent',
        ],
        idempotencyKey: 'timeout:first:order:' . $orderId,
        availableAt:    $deadline,
        maxAttempts:    (int)$cfg['queue']['max_attempts'],
    );

    try {
        $queue->push($timeoutJob);
    } catch (\Throwable $e) {
        worker_log('warning', 'send_confirmation.timeout_enqueue_failed', [
            'order_id' => $orderId,
            'error'    => $e->getMessage(),
        ]);
    }

    worker_log('info', 'send_confirmation.complete', [
        'order_id'        => $orderId,
        'deadline'        => $deadline->format('c'),
        'message_preview' => mb_substr($messageText, 0, 80),
    ]);

    return true;
}

// ═════════════════════════════════════════════════════════════════
//  HELPERS
// ═════════════════════════════════════════════════════════════════

/**
 * Load order row (tenant-scoped).
 *
 * @return array<string,mixed>|null
 */
function loadOrder(\DB $db, int $orderId, int $userId): ?array
{
    $rows = $db->getThisQuery(
        "SELECT id, store_id, user_id, order_number,
                customer_name, customer_phone, customer_email,
                customer_ville, shipping_address, total_amount, currency,
                status, confirmation_status, bot_attempts,
                confirmation_deadline
           FROM orders
          WHERE id = ? AND user_id = ?
          LIMIT 1",
        [$orderId, $userId]
    );

    return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
}

/**
 * Compose the initial confirmation message.
 *
 * Used for text-mode sends and for logging.
 */
function buildConfirmationMessage(array $order, array $storeCfg): string
{
    $name     = trim((string)($order['customer_name'] ?? '')) ?: 'cher client';
    $number   = $order['order_number'] ?? ('#' . $order['id']);
    $total    = number_format((float)$order['total_amount'], 2, '.', ' ');
    $currency = $order['currency'] ?? 'MAD';
    $city     = $order['customer_ville'] ?? '';

    $msg  = "Bonjour {$name},\n\n";
    $msg .= "Votre commande {$number} d'un montant de {$total} {$currency}";
    if ($city) {
        $msg .= " pour livraison à {$city}";
    }
    $msg .= " est en attente de confirmation.\n\n";
    $msg .= "Répondez *OUI* pour confirmer la livraison, ou *NON* pour annuler.";

    return $msg;
}

/**
 * Send the confirmation message via WhatsAppService.
 *
 * @return array{ok:bool, provider_message_id?:?string, error?:?string}
 */
function sendConfirmationMessage(
    \DB $db,
    array $order,
    array $storeCfg,
    string $messageText,
    int $userId,
): array {
    $phone = (string)$order['customer_phone'];

    try {
        $svc = new WhatsAppService($db);

        // Try the message-based API. If the store is configured for
        // templates, WhatsAppService::sendConfirmation() picks template
        // mode automatically.
        $context = [
            'order_id' => $order['id']       ?? null,
            'user_id'  => $userId,
            'store_id' => $order['store_id'] ?? null,
            'type'     => 'CONFIRMATION',
        ];

        $res = $svc->sendConfirmation($phone, $order, $storeCfg + $context);

        return [
            'ok'                  => (bool)($res['ok']                  ?? false),
            'provider_message_id' => $res['provider_message_id']        ?? null,
            'error'               => $res['error']                      ?? null,
        ];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Mask a phone number for logs: keep first 4 and last 2.
 */
function maskPhone(string $phone): string
{
    $phone = preg_replace('/\s+/', '', $phone);
    $len = strlen($phone);
    if ($len <= 6) {
        return str_repeat('*', max(0, $len - 2)) . substr($phone, -2);
    }
    return substr($phone, 0, 4) . str_repeat('*', $len - 6) . substr($phone, -2);
} ?>