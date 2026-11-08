<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  inbound_worker.php — classify and act on inbound WhatsApp replies
 * ═══════════════════════════════════════════════════════════════
 *
 *  Consumes the `inbound_ai` queue and handles `classify_reply` jobs.
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Run:
 *      php ai_confirmation/workers/inbound_worker.php
 *      php ai_confirmation/workers/inbound_worker.php --once
 * ═══════════════════════════════════════════════════════════════
 */

use OBGecom\AiConfirmation\Core\AIClassifier;
use OBGecom\AiConfirmation\Core\AIConversationService;
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
$queueName = $args['queue'] ?? 'inbound_ai';

require_once __DIR__ . '/../core/ConfirmationStateMachine.php';
require_once __DIR__ . '/../core/ConfirmationConfig.php';
require_once __DIR__ . '/../core/AIClassifier.php';
require_once __DIR__ . '/../core/AIConversationService.php';
require_once __DIR__ . '/../whatsapp/WhatsAppService.php';

worker_log('info', 'inbound_worker.start', [
    'queue' => $queueName,
    'once'  => $args['once'],
]);

$processed  = 0;
$maxJobs    = (int)$cfg['worker']['max_jobs'];
$pollMs     = (int)$cfg['queue']['poll_interval_ms'];
$idleLogged = false;

while (!worker_should_stop()) {

    if ($maxJobs > 0 && $processed >= $maxJobs) {
        worker_log('info', 'inbound_worker.max_jobs_reached', ['processed' => $processed]);
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
            worker_log('debug', 'inbound_worker.idle', ['queue' => $queueName]);
            $idleLogged = true;
        }
        worker_sleep_ms($pollMs);
        continue;
    }
    $idleLogged = false;

    if ($job->jobType !== 'classify_reply') {
        worker_log('warning', 'inbound_worker.unknown_job_type', [
            'job_id'   => $job->id,
            'job_type' => $job->jobType,
        ]);
        $queue->ack($job, $workerId);
        continue;
    }

    try {
        $result = handleClassifyReply($db, $queue, $job, $cfg, $workerId);

        if ($result === true || $result === 'skip') {
            $queue->ack($job, $workerId);
            $processed++;
            worker_log('info', 'inbound_worker.job_done', [
                'job_id'    => $job->id,
                'order_id'  => $job->orderId,
                'result'    => $result === true ? 'ok' : 'skip',
                'processed' => $processed,
            ]);
        } else {
            $queue->fail($job, 'unexpected handler return', 60, $workerId);
        }

    } catch (\Throwable $e) {
        worker_log('error', 'inbound_worker.job_failed', [
            'job_id' => $job->id,
            'error'  => $e->getMessage(),
        ]);
        $backoff = 60 * (int)max(1, $job->attempts + 1);
        $queue->fail($job, $e->getMessage(), $backoff, $workerId);
    }

    if ($args['once']) break;
}

worker_log('info', 'inbound_worker.exit', [
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
function handleClassifyReply(
    \DB $db,
    \OBGecom\AiConfirmation\Queue\Queue $queue,
    Job $job,
    array $cfg,
    string $workerId,
): bool|string {

    $waMsgId = (int)($job->payload['whatsapp_message_id'] ?? 0);
    if ($waMsgId <= 0) {
        worker_log('warning', 'classify_reply.missing_wa_msg_id', ['job_id' => $job->id]);
        return 'skip';
    }

    // ── 1. Load inbound message ────────────────────────────────
    $msg = loadInboundMessage($db, $waMsgId);
    if ($msg === null) {
        worker_log('warning', 'classify_reply.msg_missing', ['wa_msg_id' => $waMsgId]);
        return 'skip';
    }

    // ── 2. Idempotency: already classified? ────────────────────
    if (!empty($msg['ai_intent'])) {
        return 'skip';
    }

    // ── 3. Match to an order ───────────────────────────────────
    $order = findActiveOrderForPhone($db, (string)$msg['customer_phone']);
    if ($order === null) {
        worker_log('info', 'classify_reply.no_matching_order', [
            'phone_suffix' => substr((string)$msg['customer_phone'], -4),
        ]);
        markInboundProcessed($db, $waMsgId, null, 'AGENT_REQUIRED', 0.0);
        return 'skip';
    }

    $orderId = (int)$order['id'];
    $storeId = (int)$order['store_id'];
    $userId  = (int)$order['user_id'];

    // Attach the matched order and tenant to the message row.
    updateInboundOrderLink($db, $waMsgId, $orderId, $userId, $storeId);

    // ── 4. Load per-store config ───────────────────────────────
    ConfirmationConfig::clearCache($storeId);
    $storeCfg = ConfirmationConfig::forStore($db, $storeId, $userId);

    // ── 5. Classify ────────────────────────────────────────────
    $text = (string)($msg['message'] ?? '');
    $classification = AIClassifier::classify($text, [
        'order_number' => $order['order_number'] ?? null,
    ]);

    markInboundProcessed(
        $db, $waMsgId, $orderId,
        $classification['intent'],
        $classification['confidence']
    );

    worker_log('info', 'classify_reply.classified', [
        'order_id'   => $orderId,
        'intent'     => $classification['intent'],
        'confidence' => $classification['confidence'],
        'source'     => $classification['source'],
    ]);

    // ── 6. Act on intent ───────────────────────────────────────
    return actOnIntent($db, $queue, $order, $msg, $classification, $storeCfg, $cfg);
}

// ═════════════════════════════════════════════════════════════════
//  INTENT HANDLING
// ═════════════════════════════════════════════════════════════════

function actOnIntent(
    \DB $db,
    \OBGecom\AiConfirmation\Queue\Queue $queue,
    array $order,
    array $msg,
    array $classification,
    array $storeCfg,
    array $cfg,
): bool|string {

    $orderId = (int)$order['id'];
    $storeId = (int)$order['store_id'];
    $userId  = (int)$order['user_id'];
    $intent  = (string)$classification['intent'];
    $conf    = (float)$classification['confidence'];
    $from    = (string)$order['confirmation_status'];

    if (!in_array($from, ['bot_first_message_sent', 'bot_second_message_sent'], true)) {
        worker_log('info', 'actOnIntent.not_waiting', [
            'order_id' => $orderId,
            'status'   => $from,
        ]);
        return 'skip';
    }

    // ── CONFIRM ────────────────────────────────────────────────
    if ($intent === 'CONFIRM' && $conf >= $storeCfg['confirm_threshold']) {
        $ok = SM::transition(
            db: $db, orderId: $orderId,
            from: $from, to: 'confirmed_by_bot',
            event: 'CUSTOMER_CONFIRMED',
            actor: 'customer',
            message: 'Customer confirmed via WhatsApp',
            metadata: [
                'intent'     => $intent,
                'confidence' => $conf,
                'source'     => $classification['source'],
            ],
        );

        if ($ok) {

            try {
                $db->update_this(
                    "UPDATE orders
                        SET status = 'confirmed',
                            updated_at = NOW()
                      WHERE id = ?",
                    [$orderId]
                );
            } catch (\Throwable $e) {
                worker_log('warning', 'actOnIntent.status_update_failed', [
                    'order_id' => $orderId,
                    'error'    => $e->getMessage(),
                ]);
            }

            sendTextSafe(
                $db, $order,
                "Merci ! Votre commande est confirmée. Nous préparons l'expédition.",
                $storeCfg, $userId, 'CONFIRMATION'
            );

            if (!empty($storeCfg['auto_shipping_enabled'])) {
                enqueueShipping($queue, $order, $cfg);
            }
        }
        return true;
    }

    // ── CANCEL ─────────────────────────────────────────────────
    if ($intent === 'CANCEL' && $conf >= $storeCfg['cancel_threshold']) {
        $ok = SM::transition(
            db: $db, orderId: $orderId,
            from: $from, to: 'cancelled_by_bot',
            event: 'CUSTOMER_CANCELLED',
            actor: 'customer',
            message: 'Customer cancelled via WhatsApp',
            metadata: [
                'intent'     => $intent,
                'confidence' => $conf,
                'source'     => $classification['source'],
            ],
        );
        if ($ok) {

            try {
                $db->update_this(
                    "UPDATE orders
                        SET status = 'cancelled',
                            updated_at = NOW()
                      WHERE id = ?",
                    [$orderId]
                );
            } catch (\Throwable $e) {
                worker_log('warning', 'actOnIntent.status_update_failed', [
                    'order_id' => $orderId,
                    'error'    => $e->getMessage(),
                ]);
            }
            
            sendTextSafe(
                $db, $order,
                "Votre commande a bien été annulée. Merci de votre retour.",
                $storeCfg, $userId, 'CONFIRMATION'
            );
        }
        return true;
    }

    // ── ASK_QUESTION / NEGOTIATE → send an AI reply ────────────
    if (in_array($intent, ['ASK_QUESTION', 'NEGOTIATE'], true)) {
        $maxReplies  = (int)$storeCfg['max_ai_replies'];
        $botAttempts = (int)($order['bot_attempts'] ?? 0);
        if ($botAttempts >= $maxReplies) {
            worker_log('info', 'actOnIntent.max_ai_replies_reached', [
                'order_id' => $orderId,
                'attempts' => $botAttempts,
            ]);
            return escalateToAgent($db, $queue, $order, $classification, $cfg, 'Max AI replies reached');
        }

        $safeCtx = buildSafeContext($db, $order, $storeCfg);
        $reply   = AIConversationService::replyFor($intent, $safeCtx, (string)$msg['message']);

        sendTextSafe($db, $order, $reply, $storeCfg, $userId, 'AI_REPLY');

        try {
            $db->update_this(
                "UPDATE orders
                    SET bot_attempts = bot_attempts + 1,
                        last_bot_message_at = NOW(),
                        updated_at = NOW()
                  WHERE id = ?",
                [$orderId]
            );
        } catch (\Throwable $e) {
            worker_log('warning', 'actOnIntent.bot_attempts_update_failed', ['order_id' => $orderId]);
        }
        return true;
    }

    // ── MODIFY_ORDER / UNKNOWN / AGENT_REQUIRED / low confidence ─
    return escalateToAgent(
        $db, $queue, $order, $classification, $cfg,
        'Unhandled intent: ' . $intent . ' (conf=' . $conf . ')'
    );
}

// ═════════════════════════════════════════════════════════════════
//  ESCALATION
// ═════════════════════════════════════════════════════════════════

function escalateToAgent(
    \DB $db,
    \OBGecom\AiConfirmation\Queue\Queue $queue,
    array $order,
    array $classification,
    array $cfg,
    string $reason,
): bool {
    $orderId = (int)$order['id'];
    $from    = (string)$order['confirmation_status'];

    if (in_array($from, ['bot_first_message_sent', 'bot_second_message_sent'], true)) {
        SM::transition(
            db: $db, orderId: $orderId,
            from: $from, to: 'manual_required_unassigned',
            event: 'AGENT_REQUIRED',
            actor: 'system',
            message: $reason,
            metadata: [
                'intent'     => $classification['intent']     ?? null,
                'confidence' => $classification['confidence'] ?? null,
            ],
        );
    }

    // Holding message for the customer
    try {
        $order2 = loadOrderFull($db, $orderId);
        if ($order2) {
            $storeCfg = ConfirmationConfig::forStore(
                $db,
                (int)$order2['store_id'],
                (int)$order2['user_id']
            );
            sendTextSafe(
                $db, $order2,
                "Un agent va vous répondre très rapidement. Merci de patienter.",
                $storeCfg,
                (int)$order2['user_id'],
                'AGENT_REPLY'
            );
        }
    } catch (\Throwable $e) {
        worker_log('warning', 'escalate.holding_message_failed', ['order_id' => $orderId]);
    }

    // Enqueue agent assignment
    $job = Job::make(
        queue:          'agent_dispatch',
        jobType:        'assign_agent',
        userId:         (int)$order['user_id'],
        storeId:        (int)$order['store_id'],
        orderId:        $orderId,
        payload: [
            'order_id' => $orderId,
            'reason'   => $reason,
        ],
        idempotencyKey: 'assign_agent:order:' . $orderId,
        maxAttempts:    (int)$cfg['queue']['max_attempts'],
    );
    try {
        $queue->push($job);
    } catch (\Throwable $e) {
        worker_log('warning', 'escalate.enqueue_failed', [
            'order_id' => $orderId,
            'error'    => $e->getMessage(),
        ]);
    }

    worker_log('info', 'escalate.done', [
        'order_id' => $orderId,
        'reason'   => $reason,
    ]);

    return true;
}

// ═════════════════════════════════════════════════════════════════
//  SHIPPING ENQUEUE
// ═════════════════════════════════════════════════════════════════

function enqueueShipping(
    \OBGecom\AiConfirmation\Queue\Queue $queue,
    array $order,
    array $cfg,
): void {
    $orderId = (int)$order['id'];
    $job = Job::make(
        queue:          'shipping',
        jobType:        'submit_shipping',
        userId:         (int)$order['user_id'],
        storeId:        (int)$order['store_id'],
        orderId:        $orderId,
        payload: [
            'order_id' => $orderId,
            'source'   => 'bot_confirm',
        ],
        idempotencyKey: 'ship:order:' . $orderId,
        maxAttempts:    (int)$cfg['queue']['max_attempts'],
    );
    try {
        $queue->push($job);
    } catch (\Throwable $e) {
        worker_log('warning', 'enqueue_shipping.failed', [
            'order_id' => $orderId,
            'error'    => $e->getMessage(),
        ]);
    }
}

// ═════════════════════════════════════════════════════════════════
//  WHATSAPP SEND HELPER
// ═════════════════════════════════════════════════════════════════

function sendTextSafe(
    \DB $db,
    array $order,
    string $text,
    array $storeCfg,
    int $userId,
    string $messageType,
): void {
    try {
        $svc = new WhatsAppService($db);
        $result = $svc->sendText(
            (string)$order['customer_phone'],
            $text,
            [
                'order_id' => (int)$order['id'],
                'user_id'  => $userId,
                'store_id' => (int)$order['store_id'],
                'type'     => $messageType,
            ]
        );
        if (!$result['ok']) {
            worker_log('warning', 'send_text.failed', [
                'order_id' => $order['id'],
                'error'    => $result['error'] ?? null,
            ]);
        }
    } catch (\Throwable $e) {
        worker_log('warning', 'send_text.exception', [
            'order_id' => $order['id'],
            'error'    => $e->getMessage(),
        ]);
    }
}

// ═════════════════════════════════════════════════════════════════
//  DB HELPERS
// ═════════════════════════════════════════════════════════════════

function loadInboundMessage(\DB $db, int $id): ?array
{
    $rows = $db->getThisQuery(
        "SELECT id, user_id, store_id, order_id, customer_phone,
                direction, message_type, message, ai_intent, ai_confidence
           FROM whatsapp_messages
          WHERE id = ?
          LIMIT 1",
        [$id]
    );

    return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
}

function updateInboundOrderLink(\DB $db, int $waMsgId, int $orderId, int $userId, int $storeId): void
{
    try {
        $db->update_this(
            "UPDATE whatsapp_messages
                SET order_id = ?, user_id = ?, store_id = ?, updated_at = NOW()
              WHERE id = ?",
            [$orderId, $userId, $storeId, $waMsgId]
        );
    } catch (\Throwable $e) {
        worker_log('warning', 'update_inbound_link.failed', ['wa_msg_id' => $waMsgId]);
    }
}

/**
 * Mark the inbound message as classified.
 *
 * The old version used COALESCE(order_id, ?) to avoid overwriting
 * an existing order_id with a NULL/empty value. Because
 * DB::update_this() binds everything as PARAM_STR (turning PHP null
 * into ''), we split the logic: always update the classification
 * fields, and set order_id only when we actually have one.
 */
function markInboundProcessed(
    \DB $db,
    int $waMsgId,
    ?int $orderId,
    string $intent,
    float $confidence,
): void {
    try {
        $db->update_this(
            "UPDATE whatsapp_messages
                SET ai_intent = ?, ai_confidence = ?,
                    status = 'processed',
                    updated_at = NOW()
              WHERE id = ?",
            [$intent, $confidence, $waMsgId]
        );

        if ($orderId !== null && $orderId > 0) {
            $db->update_this(
                "UPDATE whatsapp_messages
                    SET order_id = ?
                  WHERE id = ?
                    AND (order_id IS NULL OR order_id = 0)",
                [$orderId, $waMsgId]
            );
        }
    } catch (\Throwable $e) {
        worker_log('warning', 'mark_inbound.failed', ['wa_msg_id' => $waMsgId]);
    }
}

/**
 * Find the most recent active confirmation for the phone.
 */
function findActiveOrderForPhone(\DB $db, string $phone): ?array
{
    $normalized = preg_replace('/\D+/', '', $phone);
    if ($normalized === '') return null;

    $suffix = '%' . substr($normalized, -9);

    $rows = $db->getThisQuery(
        "SELECT id, user_id, store_id, order_number, customer_name,
                customer_phone, customer_ville, total_amount, currency,
                confirmation_status, bot_attempts
           FROM orders
          WHERE confirmation_status IN ('bot_first_message_sent','bot_second_message_sent')
            AND (customer_phone = ? OR customer_phone LIKE ?)
          ORDER BY COALESCE(last_bot_message_at, created_at) DESC
          LIMIT 1",
        [$normalized, $suffix]
    );

    return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
}

function loadOrderFull(\DB $db, int $orderId): ?array
{
    $rows = $db->getThisQuery(
        "SELECT id, user_id, store_id, order_number, customer_name,
                customer_phone, customer_ville, total_amount, currency,
                confirmation_status, bot_attempts
           FROM orders
          WHERE id = ?
          LIMIT 1",
        [$orderId]
    );

    return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
}

/**
 * Safe, minimal order context for the AI conversation service.
 */
function buildSafeContext(\DB $db, array $order, array $storeCfg): array
{
    $products = [];
    try {
        $rows = $db->getThisQuery(
            "SELECT product_name FROM order_items WHERE order_id = ? LIMIT 5",
            [(int)$order['id']]
        );
        if (is_array($rows)) {
            foreach ($rows as $r) {
                if (isset($r['product_name'])) {
                    $products[] = (string)$r['product_name'];
                }
            }
        }
    } catch (\Throwable $e) {
        // ignore — no products means the AI prompt omits them
    }

    return [
        'order_number' => $order['order_number'] ?? null,
        'products'     => $products,
        'total'        => (float)($order['total_amount'] ?? 0),
        'currency'     => (string)($order['currency'] ?? 'MAD'),
        'city'         => (string)($order['customer_ville'] ?? ''),
        'language'     => (string)($storeCfg['whatsapp_language'] ?? 'fr'),
    ];
} ?>