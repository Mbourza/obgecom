<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  agent_dispatch_worker.php — route escalations to human agents
 * ═══════════════════════════════════════════════════════════════
 *
 *  Consumes the `agent_dispatch` queue and handles `assign_agent` jobs.
 *
 *  This worker is a THIN WRAPPER around the existing OrderDistributor
 *  class in controllers/distribute_orders_function.php. We do not
 *  reimplement agent assignment. We only:
 *
 *    1. Ensure the order is in `manual_required_unassigned`.
 *    2. Call OrderDistributor::distributeOrders() for the tenant.
 *    3. Read back the assignment row.
 *    4. If an agent was assigned → transition to `awaiting_agent_confirmation`.
 *    5. If no agents are available → transition to `manual_required` (terminal).
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Run:
 *      php ai_confirmation/workers/agent_dispatch_worker.php
 *      php ai_confirmation/workers/agent_dispatch_worker.php --once
 * ═══════════════════════════════════════════════════════════════
 */

use OBGecom\AiConfirmation\Core\ConfirmationStateMachine as SM;

$ctx = require __DIR__ . '/_bootstrap.php';

$db        = $ctx['db'];
$queue     = $ctx['queue'];
$cfg       = $ctx['cfg'];
$workerId  = $ctx['worker_id'];
$args      = $ctx['args'];
$queueName = $args['queue'] ?? 'agent_dispatch';

require_once __DIR__ . '/../core/ConfirmationStateMachine.php';

// The OrderDistributor class lives in the existing project and
// expects a \DB instance. We already have one from the bootstrap.
require_once __DIR__ . '/../../controllers/distribute_orders_function.php';

worker_log('info', 'agent_dispatch_worker.start', [
    'queue' => $queueName,
    'once'  => $args['once'],
]);

$processed  = 0;
$maxJobs    = (int)$cfg['worker']['max_jobs'];
$pollMs     = (int)$cfg['queue']['poll_interval_ms'];
$idleLogged = false;

while (!worker_should_stop()) {

    if ($maxJobs > 0 && $processed >= $maxJobs) {
        worker_log('info', 'agent_dispatch_worker.max_jobs_reached', ['processed' => $processed]);
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
            worker_log('debug', 'agent_dispatch_worker.idle', ['queue' => $queueName]);
            $idleLogged = true;
        }
        worker_sleep_ms($pollMs);
        continue;
    }
    $idleLogged = false;

    if ($job->jobType !== 'assign_agent') {
        worker_log('warning', 'agent_dispatch_worker.unknown_job_type', [
            'job_id'   => $job->id,
            'job_type' => $job->jobType,
        ]);
        $queue->ack($job, $workerId);
        continue;
    }

    try {
        $result = handleAssignAgent($db, $queue, $job, $cfg, $workerId);

        if ($result === true || $result === 'skip') {
            $queue->ack($job, $workerId);
            $processed++;
            worker_log('info', 'agent_dispatch_worker.job_done', [
                'job_id'    => $job->id,
                'order_id'  => $job->orderId,
                'result'    => $result === true ? 'assigned' : 'skip',
                'processed' => $processed,
            ]);
        } else {
            $queue->fail($job, 'unexpected handler return', 60, $workerId);
        }

    } catch (\Throwable $e) {
        worker_log('error', 'agent_dispatch_worker.job_failed', [
            'job_id' => $job->id,
            'error'  => $e->getMessage(),
        ]);
        $backoff = 120 * (int)max(1, $job->attempts + 1);
        $queue->fail($job, $e->getMessage(), $backoff, $workerId);
    }

    if ($args['once']) break;
}

worker_log('info', 'agent_dispatch_worker.exit', [
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
function handleAssignAgent(
    \DB $db,
    \OBGecom\AiConfirmation\Queue\Queue $queue,
    \OBGecom\AiConfirmation\Queue\Job $job,
    array $cfg,
    string $workerId,
): bool|string {

    $orderId = (int)($job->payload['order_id'] ?? $job->orderId ?? 0);
    $reason  = (string)($job->payload['reason'] ?? 'no reason given');
    if ($orderId <= 0) {
        worker_log('warning', 'assign_agent.bad_payload', ['job_id' => $job->id]);
        return 'skip';
    }

    // ── Load order ────────────────────────────────────────────
    $order = loadOrderForAgentDispatch($db, $orderId);
    if ($order === null) {
        worker_log('warning', 'assign_agent.order_missing', ['order_id' => $orderId]);
        return 'skip';
    }

    $userId  = (int)$order['user_id'];
    $storeId = (int)$order['store_id'];
    $status  = (string)$order['confirmation_status'];

    // ── Idempotent guards ─────────────────────────────────────
    // If the order has moved past manual_required_unassigned
    // already (assigned, confirmed, cancelled), do nothing.
    if ($status === 'awaiting_agent_confirmation'
        || $status === 'confirmed_by_agent'
        || $status === 'cancelled_by_agent'
        || $status === 'confirmed_by_bot'
        || $status === 'cancelled_by_bot'
        || $status === 'manual_required'
    ) {
        worker_log('debug', 'assign_agent.already_handled', [
            'order_id' => $orderId,
            'status'   => $status,
        ]);
        return 'skip';
    }

    // If the order is not yet in manual_required_unassigned, we
    // transition it here. This is the only state we can dispatch from.
    if ($status !== 'manual_required_unassigned') {
        // The only legal path from bot_first/second_message_sent
        // is via manual_required_unassigned. If we're not there,
        // either the caller forgot the intermediate step or the
        // order was reset. We force the intermediate transition.
        $ok = SM::transition(
            db:      $db,
            orderId: $orderId,
            from:    $status,
            to:      'manual_required_unassigned',
            event:   'AGENT_REQUIRED',
            actor:   'system',
            message: $reason,
        );
        if (!$ok) {
            worker_log('warning', 'assign_agent.cannot_move_to_unassigned', [
                'order_id' => $orderId,
                'status'   => $status,
            ]);
            return 'skip';
        }
    }

    // ── Check existing assignment ─────────────────────────────
    // If an agent assignment row already exists with a non-expired
    // status, we only need to advance the state machine.
    $existing = loadExistingAssignment($db, $orderId);
    if ($existing !== null && in_array($existing['status'], ['pending', 'confirmed'], true)) {
        worker_log('info', 'assign_agent.already_assigned', [
            'order_id' => $orderId,
            'agent_id' => $existing['agent_id'],
        ]);

        advanceToAwaitingConfirmation($db, $orderId, (int)$existing['agent_id'], $reason);
        return true;
    }

    // ── Call OrderDistributor ─────────────────────────────────
    // This is the same class the sync file calls. We invoke it for
    // the tenant and the specific store. It will look for
    // unassigned orders and assign them to agents.
    //
    // Note: OrderDistributor assigns ALL unassigned orders for the
    // tenant, not just ours. That is by design — it matches the
    // existing behaviour and avoids conflicts with the sync pipeline.
    $distributor = new \OrderDistributor($db, $userId, $storeId);

    try {
        $result = $distributor->distributeOrders();
    } catch (\Throwable $e) {
        // Transient error — let the queue retry with backoff.
        throw new \RuntimeException(
            'OrderDistributor threw: ' . $e->getMessage(), 0, $e
        );
    }

    $distributed = (int)($result['distributed'] ?? 0);
    $success     = (bool)($result['success'] ?? false);
    $message     = (string)($result['message'] ?? '');

    worker_log('info', 'assign_agent.distributor_result', [
        'order_id'    => $orderId,
        'success'     => $success,
        'distributed' => $distributed,
        'message'     => $message,
    ]);

    // ── Verify our specific order got an agent ────────────────
    $newAssignment = loadExistingAssignment($db, $orderId);

    if ($newAssignment !== null && in_array($newAssignment['status'], ['pending', 'confirmed'], true)) {
        // Assigned successfully
        advanceToAwaitingConfirmation($db, $orderId, (int)$newAssignment['agent_id'], $reason);
        return true;
    }

    // ── Not assigned ──────────────────────────────────────────
    // Either no agents are available, or the distributor decided
    // not to assign this particular order. The right move is to
    // mark the order manual_required (terminal) so a merchant sees it.
    if (!$success || $distributed === 0) {
        SM::transition(
            db:      $db,
            orderId: $orderId,
            from:    'manual_required_unassigned',
            to:      'manual_required',
            event:   'NO_AGENTS_AVAILABLE',
            actor:   'system',
            message: $message !== '' ? $message : 'No agents available for assignment',
        );

        worker_log('warning', 'assign_agent.no_agents', [
            'order_id' => $orderId,
            'message'  => $message,
        ]);
        return 'skip';
    }

    // Distributor reported success but our order was not assigned
    // — could be a race with another worker, or a filter in the
    // distributor. Log and skip; do not retry forever.
    worker_log('warning', 'assign_agent.unassigned_after_distribute', [
        'order_id'    => $orderId,
        'distributed' => $distributed,
        'message'     => $message,
    ]);
    return 'skip';
}

// ═════════════════════════════════════════════════════════════════
//  HELPERS
// ═════════════════════════════════════════════════════════════════

function loadOrderForAgentDispatch(\DB $db, int $orderId): ?array
{
    $rows = $db->getThisQuery(
        "SELECT id, user_id, store_id, order_number, customer_name,
                customer_phone, confirmation_status
           FROM orders
          WHERE id = ?
          LIMIT 1",
        [$orderId]
    );

    return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
}

/**
 * @return array{agent_id:int, status:string}|null
 */
function loadExistingAssignment(\DB $db, int $orderId): ?array
{
    $rows = $db->getThisQuery(
        "SELECT agent_id, status
           FROM agent_order_assignments
          WHERE order_id = ?
          ORDER BY assigned_at DESC
          LIMIT 1",
        [$orderId]
    );

    if (!is_array($rows) || !isset($rows[0])) {
        return null;
    }

    return [
        'agent_id' => (int)$rows[0]['agent_id'],
        'status'   => (string)$rows[0]['status'],
    ];
}

function advanceToAwaitingConfirmation(\DB $db, int $orderId, int $agentId, string $reason): void
{
    SM::transition(
        db:      $db,
        orderId: $orderId,
        from:    'manual_required_unassigned',
        to:      'awaiting_agent_confirmation',
        event:   'AGENT_ASSIGNED',
        actor:   'system',
        actorId: $agentId,
        message: 'Order assigned to agent: ' . $reason,
        metadata: [
            'agent_id' => $agentId,
            'reason'   => $reason,
        ],
    );

    // Stamp the agent on the order for dashboards that read it directly.
    try {
        $db->update_this(
            "UPDATE orders
                SET assigned_agent_id = ?,
                    assigned_at       = NOW(),
                    confirmation_channel = 'agent',
                    updated_at        = NOW()
              WHERE id = ?",
            [$agentId, $orderId]
        );
    } catch (\Throwable $e) {
        worker_log('warning', 'assign_agent.stamp_agent_failed', ['order_id' => $orderId]);
    }
} ?>