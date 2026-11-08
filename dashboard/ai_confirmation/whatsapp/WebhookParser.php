<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\WhatsApp;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  WebhookParser — normalize 360dialog webhook payloads
 * ═══════════════════════════════════════════════════════════════
 *
 *  Input:  raw JSON string from php://input
 *  Output: a normalized event struct, or null on parse failure
 *
 *  Normalized shape:
 *      [
 *        'kind'              => 'message' | 'status' | 'unknown',
 *
 *        // for kind=message:
 *        'provider_message_id'=> 'wamid.XXX',
 *        'from_phone'        => '212612345678',       // E.164 no +
 *        'contact_name'      => 'Ahmed' | null,
 *        'timestamp'         => 1700000000,
 *        'message_type'      => 'text' | 'image' | 'audio' | 'video'
 *                               | 'document' | 'location' | 'button'
 *                               | 'interactive' | 'sticker' | 'reaction'
 *                               | 'unknown',
 *        'text'              => 'oui je confirme' | null,  // for text/button
 *        'raw_message'       => [...],                 // full message object
 *        'phone_number_id'   => '1207455752442734',    // our WA number
 *        'display_phone'     => '212669335322',
 *
 *        // for kind=status:
 *        'provider_message_id'=> 'wamid.XXX',
 *        'status'            => 'sent' | 'delivered' | 'read' | 'failed',
 *        'recipient_id'      => '212612345678',
 *        'timestamp'         => 1700000001,
 *        'errors'            => [...],                 // if status=failed
 *      ]
 *
 *  Handles 360dialog's quirk of batching multiple events in
 *  `entry[].changes[].value`. Always returns the FIRST message
 *  event if any exist; otherwise the first status event.
 * ═══════════════════════════════════════════════════════════════
 */
final class WebhookParser
{
    /**
     * @return array<string,mixed>|null  Normalized event, or null.
     */
    public static function parse(string $rawBody): ?array
    {
        if ($rawBody === '') {
            return null;
        }

        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return null;
        }

        // Walk every entry → change → value looking for messages or statuses.
        $entries = $data['entry'] ?? null;
        if (!is_array($entries)) {
            return null;
        }

        // Priority 1 — find a message
        foreach ($entries as $entry) {
            if (!is_array($entry) || empty($entry['changes']) || !is_array($entry['changes'])) {
                continue;
            }
            foreach ($entry['changes'] as $change) {
                $value = $change['value'] ?? null;
                if (!is_array($value)) continue;

                if (!empty($value['messages']) && is_array($value['messages'])) {
                    $message = $value['messages'][0] ?? null;
                    if (is_array($message)) {
                        return self::normalizeMessage($message, $value);
                    }
                }
            }
        }

        // Priority 2 — find a status update
        foreach ($entries as $entry) {
            if (!is_array($entry) || empty($entry['changes']) || !is_array($entry['changes'])) {
                continue;
            }
            foreach ($entry['changes'] as $change) {
                $value = $change['value'] ?? null;
                if (!is_array($value)) continue;

                if (!empty($value['statuses']) && is_array($value['statuses'])) {
                    $status = $value['statuses'][0] ?? null;
                    if (is_array($status)) {
                        return self::normalizeStatus($status, $value);
                    }
                }
            }
        }

        // Unknown event shape
        return ['kind' => 'unknown'];
    }

    // ═══════════════════════════════════════════════════════════
    //  Message normalization
    // ═══════════════════════════════════════════════════════════

    private static function normalizeMessage(array $message, array $value): array
    {
        $type = strtolower((string)($message['type'] ?? 'unknown'));
        $from = (string)($message['from'] ?? '');
        $id   = (string)($message['id'] ?? '');

        $text = null;

        switch ($type) {
            case 'text':
                $text = isset($message['text']['body'])
                    ? (string)$message['text']['body']
                    : null;
                break;

            case 'button':
                // Quick-reply button tap
                $text = isset($message['button']['text'])
                    ? (string)$message['button']['text']
                    : (isset($message['button']['payload'])
                        ? (string)$message['button']['payload']
                        : null);
                break;

            case 'interactive':
                // List reply or button reply
                if (isset($message['interactive']['button_reply']['title'])) {
                    $text = (string)$message['interactive']['button_reply']['title'];
                } elseif (isset($message['interactive']['list_reply']['title'])) {
                    $text = (string)$message['interactive']['list_reply']['title'];
                }
                break;

            case 'image':
            case 'audio':
            case 'video':
            case 'document':
            case 'sticker':
            case 'location':
            case 'contacts':
            case 'reaction':
            case 'order':
                // Non-text; leave $text=null; caller decides how to handle.
                $text = null;
                break;

            default:
                $text = null;
        }

        // Extract contact profile name (if provided)
        $contactName = null;
        if (!empty($value['contacts']) && is_array($value['contacts'])) {
            foreach ($value['contacts'] as $c) {
                if (($c['wa_id'] ?? null) === $from) {
                    $contactName = isset($c['profile']['name'])
                        ? (string)$c['profile']['name']
                        : null;
                    break;
                }
            }
        }

        // Metadata
        $phoneNumberId   = null;
        $displayPhone    = null;
        if (!empty($value['metadata']) && is_array($value['metadata'])) {
            $phoneNumberId = isset($value['metadata']['phone_number_id'])
                ? (string)$value['metadata']['phone_number_id'] : null;
            $displayPhone = isset($value['metadata']['display_phone_number'])
                ? (string)$value['metadata']['display_phone_number'] : null;
        }

        return [
            'kind'                => 'message',
            'provider_message_id' => $id,
            'from_phone'          => self::normalizePhone($from),
            'contact_name'        => $contactName,
            'timestamp'           => isset($message['timestamp'])
                ? (int)$message['timestamp'] : time(),
            'message_type'        => $type,
            'text'                => $text,
            'raw_message'         => $message,
            'phone_number_id'     => $phoneNumberId,
            'display_phone'       => $displayPhone,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  Status normalization
    // ═══════════════════════════════════════════════════════════

    private static function normalizeStatus(array $status, array $value): array
    {
        return [
            'kind'                => 'status',
            'provider_message_id' => (string)($status['id'] ?? ''),
            'status'              => strtolower((string)($status['status'] ?? 'unknown')),
            'recipient_id'        => self::normalizePhone((string)($status['recipient_id'] ?? '')),
            'timestamp'           => isset($status['timestamp'])
                ? (int)$status['timestamp'] : time(),
            'errors'              => $status['errors'] ?? [],
            'raw_status'          => $status,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  Utilities
    // ═══════════════════════════════════════════════════════════

    /**
     * Normalize to E.164 without leading +, matching WhatsApp's
     * "wa_id" convention.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 10 && $digits[0] === '0') {
            return '212' . substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '212' . $digits;
        }
        return $digits;
    }
}