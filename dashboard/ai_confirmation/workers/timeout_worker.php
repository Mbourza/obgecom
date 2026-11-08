<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  timeout_worker.php — handle delayed confirmation timeouts
 * ═══════════════════════════════════════════════════════════════
 *
 *  Consumes the `timeout` queue and handles `check_timeout` jobs.
 *
 *  Two stages (from the flow chart):
 *    stage=first   after 2h without reply → send reminder
 *    stage=second  after 4h without reply → escalate to human agent
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Run:
 *      php ai_confirmation/workers/timeout_worker.php
 *      php ai_confirmation/workers/timeout_worker.php --once
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
$queueName = $args['queue'] ?? 'timeout';

require_once __DIR__ . '/../core/ConfirmationStateMachine.php';
require_once __DIR__ . '/../core/ConfirmationConfig.php';
require_once __DIR__ . '/../whatsapp/WhatsAppService.php';

worker_log('info', 'timeout_worker.start', [
    'queue' => $queueName,
    'once'  => $args['once'],
]);

$processed  = 0;
$maxJobs    = (int)$cfg['worker']['max_jobs'];
$pollMs     = (int)$cfg['queue']['poll_interval_ms'];
$idleLogged = false;

while (!worker_should_stop()) {

    if ($maxJobs > 0 && $processed >= $maxJobs) {
        worker_log('info', 'timeout_worker.max_jobs_reached', ['processed' => $processed]);
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
            worker_log('debug', 'timeout_worker.idle', ['queue' => $queueName]);
            $idleLogged = true;
        }
        worker_sleep_ms($pollMs);
        continue;
    }
    $idleLogged = false;

    if ($job->jobType !== 'check_timeout') {
        worker_log('warning', 'timeout_worker.unknown_job_type', [
            'job_id'   => $job->id,
            'job_type' => $job->jobType,
        ]);
        $queue->ack($job, $workerId);
        continue;
    }

    try {
        $result = handleCheckTimeout($db, $queue, $job, $cfg, $workerId);

        if ($result === true || $result === 'skip') {
            $queue->ack($job, $workerId);
            $processed++;
            worker_log('info', 'timeout_worker.job_done', [
                'job_id'    => $job->id,
                'order_id'  => $job->orderId,
                'stage'     => $job->payload['stage'] ?? '?',
                'result'    => $result === true ? 'acted' : 'skip',
                'processed' => $processed,
            ]);
        } else {
            $queue->fail($job, 'unexpected handler return', 60, $workerId);
        }

    } catch (\Throwable $e) {
        worker_log('error', 'timeout_worker.job_failed', [
            'job_id' => $job->id,
            'error'  => $e->getMessage(),
        ]);
        $backoff = 60 * (int)max(1, $job->attempts + 1);
        $queue->fail($job, $e->getMessage(), $backoff, $workerId);
    }

    if ($args['once']) break;
}

worker_log('info', 'timeout_worker.exit', [
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
function handleCheckTimeout(
    \DB $db,
    \OBGecom\AiConfirmation\Queue\Queue $queue,
    Job $job,
    array $cfg,
    string $workerId,
): bool|string {

    $orderId = (int)($job->payload['order_id'] ?? $job->orderId ?? 0);
    $stage   = (string)($job->payload['stage'] ?? '');
    if ($orderId <= 0 || !in_array($stage, ['first', 'second'], true)) {
        worker_log('warning', 'check_timeout.bad_payload', [
            'job_id' => $job->id,
            'stage'  => $stage,
        ]);
        return 'skip';
    }

    // ── Load order ────────────────────────────────────────────
    $order = loadOrderForTimeout($db, $orderId);
    if ($order === null) {
        worker_log('warning', 'check_timeout.order_missing', ['order_id' => $orderId]);
        return 'skip';
    }

    $userId  = (int)$order['user_id'];
    $storeId = (int)$order['store_id'];
    $status  = (string)$order['confirmation_status'];

    // ── Load per-store config (no cache — admin may have changed) ─
    ConfirmationConfig::clearCache($storeId);
    $storeCfg = ConfirmationConfig::forStore($db, $storeId, $userId);

    // ─────────────────────────────────────────────────────────
    //  STAGE = FIRST
    //  Expected current status: bot_first_message_sent
    //  Action: send reminder, transition, enqueue 2nd timeout
    // ─────────────────────────────────────────────────────────
    if ($stage === 'first') {

        // If the order already moved (customer replied, agent handled,
        // or a race happened) → nothing to do. Idempotent.
        if ($status !== 'bot_first_message_sent') {
            worker_log('debug', 'check_timeout.stage_first.skip', [
                'order_id' => $orderId,
                'status'   => $status,
            ]);
            return 'skip';
        }

        // Send reminder
        $sendOk = sendReminderSafe($db, $order, $storeCfg, $userId);

        if (!$sendOk) {
            // If the reminder could not be sent, do NOT advance stage.
            // The job will retry via the queue's backoff.
            throw new RuntimeException('reminder send failed');
        }

        // Compute new deadline = now + second_timeout_minutes
        $secondDeadline = (new DateTimeImmutable('now'))
            ->modify('+' . (int)$storeCfg['second_timeout_minutes'] . ' minutes');

        // Advance the state machine
        $moved = SM::transition(
            db:      $db,
            orderId: $orderId,
            from:    'bot_first_message_sent',
            to:      'bot_second_message_sent',
            event:   'WHATSAPP_REMINDER_SENT',
            actor:   'system',
            message: 'Reminder sent after first timeout',
            metadata: [
                'stage'        => 'first',
                'new_deadline' => $secondDeadline->format('Y-m-d H:i:s'),
                'template'     => $storeCfg['whatsapp_template_name'] ?? null,
            ],
        );

        if (!$moved) {
            worker_log('warning', 'check_timeout.stage_first.transition_raced', [
                'order_id' => $orderId,
            ]);
            return 'skip';
        }

        // Update deadline on the order row
        try {
            $db->update_this(
                "UPDATE orders
                    SET confirmation_deadline = ?,
                        last_bot_message_at   = NOW(),
                        bot_attempts          = bot_attempts + 1,
                        updated_at            = NOW()
                  WHERE id = ?",
                [$secondDeadline->format('Y-m-d H:i:s'), $orderId]
            );
        } catch (\Throwable $e) {
            worker_log('warning', 'check_timeout.stage_first.deadline_update_failed', [
                'order_id' => $orderId,
            ]);
        }

        // Enqueue stage-two timeout
        $job2 = Job::make(
            queue:          'timeout',
            jobType:        'check_timeout',
            userId:         $userId,
            storeId:        $storeId,
            orderId:        $orderId,
            payload: [
                'order_id'        => $orderId,
                'stage'           => 'second',
                'expected_status' => 'bot_second_message_sent',
            ],
            idempotencyKey: 'timeout:second:order:' . $orderId,
            availableAt:    $secondDeadline,
            maxAttempts:    (int)$cfg['queue']['max_attempts'],
        );
        try {
            $queue->push($job2);
        } catch (\Throwable $e) {
            worker_log('warning', 'check_timeout.stage_first.enqueue_failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        worker_log('info', 'check_timeout.stage_first.done', [
            'order_id'        => $orderId,
            'second_deadline' => $secondDeadline->format('c'),
        ]);
        return true;
    }

    // ─────────────────────────────────────────────────────────
    //  STAGE = SECOND
    //  Expected current status: bot_second_message_sent
    //  Action: transition to manual_required_unassigned, enqueue agent
    // ─────────────────────────────────────────────────────────
    if ($stage === 'second') {

        if ($status !== 'bot_second_message_sent') {
            worker_log('debug', 'check_timeout.stage_second.skip', [
                'order_id' => $orderId,
                'status'   => $status,
            ]);
            return 'skip';
        }

        // Advance to manual_required_unassigned
        $moved = SM::transition(
            db:      $db,
            orderId: $orderId,
            from:    'bot_second_message_sent',
            to:      'manual_required_unassigned',
            event:   'TIMEOUT_NO_RESPONSE',
            actor:   'system',
            message: 'No customer response after reminder — escalating to agent',
            metadata: [
                'stage'   => 'second',
                'timeout' => $storeCfg['second_timeout_minutes'] ?? null,
            ],
        );

        if (!$moved) {
            worker_log('warning', 'check_timeout.stage_second.transition_raced', [
                'order_id' => $orderId,
            ]);
            return 'skip';
        }

        // Enqueue agent assignment
        $agentJob = Job::make(
            queue:          'agent_dispatch',
            jobType:        'assign_agent',
            userId:         $userId,
            storeId:        $storeId,
            orderId:        $orderId,
            payload: [
                'order_id' => $orderId,
                'reason'   => 'Customer did not respond after reminder',
            ],
            idempotencyKey: 'assign_agent:order:' . $orderId,
            maxAttempts:    (int)$cfg['queue']['max_attempts'],
        );
        try {
            $queue->push($agentJob);
        } catch (\Throwable $e) {
            worker_log('warning', 'check_timeout.stage_second.enqueue_failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }

        worker_log('info', 'check_timeout.stage_second.done', [
            'order_id' => $orderId,
        ]);
        return true;
    }

    return 'skip';
}

// ═════════════════════════════════════════════════════════════════
//  HELPERS
// ═════════════════════════════════════════════════════════════════

function loadOrderForTimeout(\DB $db, int $orderId): ?array
{
    $rows = $db->getThisQuery(
        "SELECT id, user_id, store_id, order_number, customer_name,
                customer_phone, customer_ville, total_amount, currency,
                confirmation_status, bot_attempts, confirmation_deadline
           FROM orders
          WHERE id = ?
          LIMIT 1",
        [$orderId]
    );

    return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
}

function sendReminderSafe(\DB $db, array $order, array $storeCfg, int $userId): bool
{
    try {
        $svc = new WhatsAppService($db);
        $result = $svc->sendReminder(
            (string)$order['customer_phone'],
            $order,
            $storeCfg + [
                'store_id' => (int)$order['store_id'],
                'user_id'  => $userId,
            ]
        );
        if (!$result['ok']) {
            worker_log('warning', 'send_reminder.failed', [
                'order_id' => $order['id'],
                'error'    => $result['error'] ?? null,
            ]);
            return false;
        }
        return true;
    } catch (\Throwable $e) {
        worker_log('warning', 'send_reminder.exception', [
            'order_id' => $order['id'],
            'error'    => $e->getMessage(),
        ]);
        return false;
    }
} ?>