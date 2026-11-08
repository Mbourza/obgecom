<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  api/whatsapp/webhook.php — public webhook endpoint
 * ═══════════════════════════════════════════════════════════════
 *
 *  Receives all webhook POSTs from 360dialog:
 *    - inbound customer messages
 *    - message status updates (sent/delivered/read/failed)
 *
 *  Flow (must complete in < 200ms):
 *    1. Read raw body.
 *    2. Verify signature (WebhookVerifier).
 *    3. Parse payload    (WebhookParser).
 *    4. If status update → update whatsapp_messages.status → 200 OK.
 *    5. If message       → insert whatsapp_messages (INBOUND)
 *                          → enqueue classify_reply job
 *                          → 200 OK.
 *    6. Anything else    → 200 OK (always, to stop 360dialog retries).
 *
 *  No AI, no network calls, no long operations here.
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  URL to configure in 360dialog dashboard:
 *      https://yourdomain.com/ai_confirmation/api/whatsapp/webhook.php
 * ═══════════════════════════════════════════════════════════════
 */

use OBGecom\AiConfirmation\Queue\Job;
use OBGecom\AiConfirmation\WhatsApp\WebhookParser;
use OBGecom\AiConfirmation\WhatsApp\WebhookVerifier;

// ─────────────────────────────────────────────────────────────
//  Bootstrap
// ─────────────────────────────────────────────────────────────
//  Path calculation:
//      this file  →  <root>/ai_confirmation/api/whatsapp/webhook.php
//      __DIR__    →  <root>/ai_confirmation/api/whatsapp
//      ..         →  <root>/ai_confirmation/api
//      ../..      →  <root>/ai_confirmation
//      ../../..   →  <root>
$projectRoot = dirname(__DIR__, 3);

$coreInit = $projectRoot . '/core/init.php';
if (!is_file($coreInit)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'core init missing']);
    exit;
}
require_once $coreInit;

require_once __DIR__ . '/../../config/ai_confirmation.php';
require_once __DIR__ . '/../../queue/queue_factory.php';
require_once __DIR__ . '/../../whatsapp/WebhookVerifier.php';
require_once __DIR__ . '/../../whatsapp/WebhookParser.php';

require_once __DIR__ . '/../../queue/Queue.php';
require_once __DIR__ . '/../../queue/Job.php';
require_once __DIR__ . '/../../queue/DbQueue.php';
require_once __DIR__ . '/../../queue/queue_factory.php';

// ─────────────────────────────────────────────────────────────
//  Read raw body FIRST (before any header manipulation)
// ─────────────────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$rawBody = is_string($rawBody) ? $rawBody : '';

// Read raw input headers before any preprocessing.
// Some SAPIs return title-case names; we lowercase keys here so the
// verifier's lookup is reliable regardless of the caller.
$rawHeaders = function_exists('getallheaders') ? getallheaders() : null;
if (is_array($rawHeaders)) {
    $normalized = [];
    foreach ($rawHeaders as $k => $v) {
        $normalized[strtolower((string)$k)] = $v;
    }
    $rawHeaders = $normalized;
}

// We always answer 200 eventually, even on errors, so 360dialog
// does not retry forever.
$respondOk = static function (string $note = ''): void {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'note' => $note]);
    exit;
};
$respondErr = static function (int $code, string $error): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
};

// ─────────────────────────────────────────────────────────────
//  Load config
// ─────────────────────────────────────────────────────────────
try {
    $cfg = ai_confirmation_config();
} catch (\Throwable $e) {
    error_log('[ai_confirmation][webhook] config load failed: ' . $e->getMessage());
    $respondOk('config-error');
}

if (empty($cfg['enabled'])) {
    $respondOk('disabled');
}

// ─────────────────────────────────────────────────────────────
//  HTTP method handling
// ─────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'GET') {
    // Health / readiness probe.
    $respondOk('ready');
}
if ($method !== 'POST') {
    $respondErr(405, 'method not allowed');
}

// ─────────────────────────────────────────────────────────────
//  1. Verify signature
// ─────────────────────────────────────────────────────────────
try {
    $verified = WebhookVerifier::verify($rawBody, $cfg, $rawHeaders);
} catch (\Throwable $e) {
    error_log('[ai_confirmation][webhook] verifier threw: ' . $e->getMessage());
    $verified = false;
}
if (!$verified) {
    $respondErr(401, 'signature verification failed');
}

// ─────────────────────────────────────────────────────────────
//  2. Parse payload
// ─────────────────────────────────────────────────────────────
try {
    $event = WebhookParser::parse($rawBody);
} catch (\Throwable $e) {
    error_log('[ai_confirmation][webhook] parser threw: ' . $e->getMessage());
    $respondOk('parse-error');
}
if (!is_array($event)) {
    $respondOk('unparseable');
}

// ─────────────────────────────────────────────────────────────
//  3. Resolve DB + queue once, reuse for both paths
// ─────────────────────────────────────────────────────────────
try {
    $db = DB::getInstance();
} catch (\Throwable $e) {
    error_log('[ai_confirmation][webhook] db resolve failed: ' . $e->getMessage());
    $respondOk('db-unavailable');
}

// ─────────────────────────────────────────────────────────────
//  4. STATUS UPDATE branch
// ─────────────────────────────────────────────────────────────
if (($event['kind'] ?? '') === 'status') {
    handleStatusUpdate($db, $cfg, $event);
    $respondOk('status-updated');
}

// ─────────────────────────────────────────────────────────────
//  5. INBOUND MESSAGE branch
// ─────────────────────────────────────────────────────────────
if (($event['kind'] ?? '') === 'message') {
    try {
        handleInboundMessage($db, $cfg, $event);
    } catch (\Throwable $e) {
        // Never 500 — 360dialog would retry. Log and return 200.
        error_log('[ai_confirmation][webhook] inbound handling failed: '
                . $e->getMessage());
    }
    $respondOk('received');
}

// ─────────────────────────────────────────────────────────────
//  6. Unknown event kind
// ─────────────────────────────────────────────────────────────
$respondOk('unknown-kind');

// ═════════════════════════════════════════════════════════════════
//  HANDLERS
// ═════════════════════════════════════════════════════════════════

/**
 * Update the delivery/read status of a message we previously sent.
 *
 * @param array<string,mixed> $event
 */
function handleStatusUpdate(\DB $db, array $cfg, array $event): void
{
    $pmid   = (string)($event['provider_message_id'] ?? '');
    $status = strtolower((string)($event['status'] ?? ''));
    if ($pmid === '' || $status === '') {
        return;
    }

    // Only track known transitions
    $allowed = ['sent', 'delivered', 'read', 'failed'];
    if (!in_array($status, $allowed, true)) {
        return;
    }

    try {
        $db->update_this(
            "UPDATE whatsapp_messages
                SET status = ?, updated_at = NOW()
              WHERE provider = '360dialog'
                AND provider_message_id = ?
              LIMIT 1",
            [$status, $pmid]
        );
    } catch (\Throwable $e) {
        error_log('[ai_confirmation][webhook] status update failed: ' . $e->getMessage());
    }
}

/**
 * Log an inbound message and enqueue the AI classification job.
 *
 * @param array<string,mixed> $event
 */
function handleInboundMessage(\DB $db, array $cfg, array $event): void
{
    $pmid = (string)($event['provider_message_id'] ?? '');
    $from = (string)($event['from_phone'] ?? '');
    $type = (string)($event['message_type'] ?? 'unknown');
    $text = $event['text'] ?? null;                 // null for non-text
    $name = $event['contact_name'] ?? null;

    if ($pmid === '' || $from === '') {
        return;
    }

    // ── Idempotency: have we already seen this message? ──────
    try {
        $existing = $db->getThisQuery(
            "SELECT id FROM whatsapp_messages
              WHERE provider = '360dialog'
                AND provider_message_id = ?
              LIMIT 1",
            [$pmid]
        );
        if (!empty($existing)) {
            return; // already processed
        }
    } catch (\Throwable $e) {
        error_log('[ai_confirmation][webhook] dedup check failed: ' . $e->getMessage());
    }

    // ── Build the message body we log ────────────────────────
    $body = '';
    if ($type === 'text' && is_string($text)) {
        $body = $text;
    } else {
        $body = '[non-text:' . $type . ']';
    }

    // ── Insert inbound row (unmatched sentinel = user_id 0) ──
    $insertId = null;
    try {
        $insertId = $db->insert_this(
            "INSERT INTO whatsapp_messages
                (user_id, store_id, order_id,
                 customer_phone, direction, message_type,
                 message, provider, provider_message_id,
                 status, created_at, updated_at)
             VALUES
                (0, 0, NULL,
                 ?, 'INBOUND', 'CUSTOMER_REPLY',
                 ?, '360dialog', ?,
                 'received', NOW(), NOW())",
            [$from, $body, $pmid]
        );
    } catch (\Throwable $e) {
        error_log('[ai_confirmation][webhook] inbound insert failed: ' . $e->getMessage());
        return;
    }
    if (!$insertId) {
        // insert_this returned false — treat as duplicate-or-failure.
        // Either way, do not enqueue.
        return;
    }

    // ── Enqueue classify_reply job ───────────────────────────
    $idempotencyKey = 'wa_in:360dialog:' . $pmid;

    try {
        $job = Job::make(
            queue:          'inbound_ai',
            jobType:        'classify_reply',
            userId:         0,                    // sentinel — resolved by worker
            storeId:        null,
            orderId:        null,
            payload: [
                'whatsapp_message_id' => (int)$insertId,
                'provider_message_id' => $pmid,
                'from_phone'          => $from,
                'message_type'        => $type,
                'text'                => $text,
                'contact_name'        => $name,
            ],
            idempotencyKey: $idempotencyKey,
            availableAt:    new DateTimeImmutable('now'),
            maxAttempts:    (int)$cfg['queue']['max_attempts'],
        );

        $queue = \OBGecom\AiConfirmation\Queue\queue_factory();
        $queue->push($job);

    } catch (\Throwable $e) {
        // Insert succeeded but enqueue failed — the message is
        // logged. A recovery script can pick it up later.
        error_log('[ai_confirmation][webhook] enqueue failed: ' . $e->getMessage());
    }
} ?>