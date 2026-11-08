<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Core;

use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  ConfirmationStateMachine — the single writer of orders.confirmation_status
 * ═══════════════════════════════════════════════════════════════
 *
 *  Every state transition in the confirmation flow goes through
 *  ConfirmationStateMachine::transition(). Nothing else writes
 *  orders.confirmation_status. That is what makes the system
 *  auditable and idempotent.
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Usage:
 *
 *      $ok = ConfirmationStateMachine::transition(
 *          db:       $db,
 *          orderId:  123,
 *          from:     'bot_pending',
 *          to:       'bot_first_message_sent',
 *          event:    'WHATSAPP_CONFIRMATION_SENT',
 *          actor:    'system',
 *          actorId:  null,
 *          message:  'Confirmation message sent to +2126...',
 *          metadata: ['provider' => '360dialog', 'provider_message_id' => 'wamid.XX'],
 *      );
 *
 *      if (!$ok) {
 *          // The transition was already applied by someone else,
 *          // or the order was not in the expected `from` state.
 *      }
 *
 *  The `actor` must be one of the existing confirmation_logs.actor_type
 *  enum values: system | bot | agent | merchant | customer.
 * ═══════════════════════════════════════════════════════════════
 */
final class ConfirmationStateMachine
{
    /**
     * The full set of statuses the orders.confirmation_status enum
     * can hold. Must stay in sync with the DB schema.
     */
    public const STATUSES = [
        'bot_pending',
        'bot_first_message_sent',
        'bot_second_message_sent',
        'confirmed_by_bot',
        'cancelled_by_bot',
        'manual_required',
        'manual_required_unassigned',
        'awaiting_agent_confirmation',
        'confirmed_by_agent',
        'cancelled_by_agent',
    ];

    /** Terminal statuses — nothing can transition out of these. */
    public const TERMINAL_STATUSES = [
        'confirmed_by_bot',
        'cancelled_by_bot',
        'confirmed_by_agent',
        'cancelled_by_agent',
        'manual_required',
    ];

    /**
     * Legal transitions: from → [allowed targets].
     *
     * Any transition not listed here is rejected and logged as warning.
     */
    public const TRANSITIONS = [
        'bot_pending' => [
            'bot_first_message_sent',
            'cancelled_by_bot',
            'confirmed_by_bot',
        ],
        'bot_first_message_sent' => [
            'confirmed_by_bot',
            'cancelled_by_bot',
            'bot_second_message_sent',
            'manual_required_unassigned',
            'bot_first_message_sent', // no-op for "AI replied, still waiting"
        ],
        'bot_second_message_sent' => [
            'confirmed_by_bot',
            'cancelled_by_bot',
            'manual_required_unassigned',
            'bot_second_message_sent', // no-op for "AI replied, still waiting"
        ],
        'manual_required_unassigned' => [
            'awaiting_agent_confirmation',
            'manual_required',
        ],
        'awaiting_agent_confirmation' => [
            'confirmed_by_agent',
            'cancelled_by_agent',
            'manual_required',
        ],
        // Terminal — no outbound
        'confirmed_by_bot'  => [],
        'cancelled_by_bot'  => [],
        'confirmed_by_agent'=> [],
        'cancelled_by_agent'=> [],
        'manual_required'   => [],
    ];

    /** Valid values for confirmation_logs.actor_type. */
    public const ACTOR_TYPES = ['system', 'bot', 'agent', 'merchant', 'customer'];

    // ═══════════════════════════════════════════════════════════
    //  PUBLIC API
    // ═══════════════════════════════════════════════════════════

    /**
     * Perform an atomic, idempotent state transition.
     *
     * @param string               $from     The expected current status
     * @param string               $to       The desired next status
     * @param string               $event    Human-readable event name (stored in logs)
     * @param string               $actor    'system'|'bot'|'agent'|'merchant'|'customer'
     * @param int|null             $actorId  Agent id, customer id, etc. (nullable)
     * @param string|null          $message  Optional note for the audit log
     * @param array<string,mixed>  $metadata Optional JSON payload for the audit log
     *
     * @return bool  true if the transition was applied or was a same-status no-op;
     *               false if the order was not in the expected state (already moved)
     */
    public static function transition(
        \DB $db,
        int $orderId,
        string $from,
        string $to,
        string $event,
        string $actor = 'system',
        ?int $actorId = null,
        ?string $message = null,
        array $metadata = [],
    ): bool {
        // ── Validate inputs against the map ──────────────────
        if (!in_array($from, self::STATUSES, true)) {
            throw new \InvalidArgumentException("ConfirmationStateMachine: unknown 'from' status '{$from}'");
        }
        if (!in_array($to, self::STATUSES, true)) {
            throw new \InvalidArgumentException("ConfirmationStateMachine: unknown 'to' status '{$to}'");
        }
        if (!in_array($actor, self::ACTOR_TYPES, true)) {
            throw new \InvalidArgumentException("ConfirmationStateMachine: unknown actor '{$actor}'");
        }

        // ── Same-status no-op ────────────────────────────────
        if ($from === $to) {
            return true;
        }

        // ── Legal transition check ───────────────────────────
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            error_log("[ConfirmationStateMachine] illegal transition "
                    . "order={$orderId} {$from} → {$to} (event={$event})");
            return false;
        }

        // ── Fetch current row (for logging + safety) ─────────
        $current = self::loadOrderSnapshot($db, $orderId);
        if ($current === null) {
            error_log("[ConfirmationStateMachine] order {$orderId} not found");
            return false;
        }

        // ── Atomic UPDATE with the expected `from` in WHERE ──
        // Race-safe: two processes try the same transition, only
        // one UPDATE changes a row.
        $db->update_this(
            "UPDATE orders
                SET confirmation_status = ?,
                    updated_at          = NOW()
              WHERE id = ?
                AND confirmation_status = ?
              LIMIT 1",
            [$to, $orderId, $from]
        );

        if ($db->affectedRows() !== 1) {
            // Someone else moved the order first, or it was never
            // in the expected state. Idempotent: return false, no log.
            return false;
        }

        // ── Side-effect fields on orders ─────────────────────
        self::applySideEffectTimestamps($db, $orderId, $to);

        // ── Audit log ────────────────────────────────────────
        // Best-effort. Failure here does not roll back the state
        // change — the state is the source of truth.
        try {
            $db->insert_this(
                "INSERT INTO confirmation_logs
                    (order_id, actor_type, actor_id,
                     event_type, old_status, new_status,
                     old_order_status, new_order_status,
                     message, raw_payload, created_at)
                 VALUES
                    (?, ?, ?,
                     ?, ?, ?,
                     ?, ?,
                     ?, ?, NOW())",
                [
                    $orderId,
                    $actor,
                    $actorId,
                    $event,
                    $from,
                    $to,
                    $current['status'] ?? null,
                    $current['status'] ?? null, // order.status is not changed here
                    $message,
                    empty($metadata)
                        ? null
                        : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );
        } catch (Throwable $e) {
            error_log("[ConfirmationStateMachine] audit log failed for order {$orderId}: "
                    . $e->getMessage());
        }

        return true;
    }

    /**
     * Convenience: check whether a transition would be legal,
     * without touching the DB.
     */
    public static function canTransition(string $from, string $to): bool
    {
        if (!in_array($from, self::STATUSES, true)) return false;
        if (!in_array($to, self::STATUSES, true))   return false;
        if ($from === $to) return true;
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Is the given status terminal?
     */
    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES, true);
    }

    // ═══════════════════════════════════════════════════════════
    //  PRIVATE
    // ═══════════════════════════════════════════════════════════

    /**
     * Load the minimum fields we need from orders for logging.
     *
     * @return array{status:string, user_id:int, store_id:int}|null
     */
    private static function loadOrderSnapshot(\DB $db, int $orderId): ?array
    {
        $rows = $db->getThisQuery(
            "SELECT status, user_id, store_id
               FROM orders
              WHERE id = ?
              LIMIT 1",
            [$orderId]
        );

        return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    /**
     * Update confirmed_at / cancelled_at when the terminal state is reached.
     * Never overwrite an existing non-null value (idempotent).
     */
    private static function applySideEffectTimestamps(\DB $db, int $orderId, string $to): void
    {
        $setConfirmed = in_array($to, ['confirmed_by_bot', 'confirmed_by_agent'], true);
        $setCancelled = in_array($to, ['cancelled_by_bot', 'cancelled_by_agent'], true);

        if (!$setConfirmed && !$setCancelled) {
            return;
        }

        try {
            if ($setConfirmed) {
                $db->update_this(
                    "UPDATE orders
                        SET confirmed_at = COALESCE(confirmed_at, NOW())
                      WHERE id = ?",
                    [$orderId]
                );
            }
            if ($setCancelled) {
                $db->update_this(
                    "UPDATE orders
                        SET cancelled_at = COALESCE(cancelled_at, NOW())
                      WHERE id = ?",
                    [$orderId]
                );
            }
        } catch (Throwable $e) {
            error_log("[ConfirmationStateMachine] side-effect timestamp failed for order {$orderId}: "
                    . $e->getMessage());
        }
    }
} ?>