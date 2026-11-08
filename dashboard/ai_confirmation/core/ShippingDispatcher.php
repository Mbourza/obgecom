<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Core;

use PDO;
use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  ShippingDispatcher — safe wrapper around upTo_shipping.php
 * ═══════════════════════════════════════════════════════════════
 *
 *  The existing shipping logic lives in:
 *      controllers/upTo_shipping.php
 *  as a web endpoint with a top-level `exit` statement that makes
 *  it unsafe to include from CLI workers.
 *
 *  We added a guard constant (SHIPPING_DISPATCHER_INCLUDED) to that
 *  file so that when it is defined, only the pure function
 *  definitions load and the endpoint code is skipped. This class
 *  defines that constant and then loads the functions.
 *
 *  Public API:
 *      ShippingDispatcher::submit($db, $orderRow, $userId): array
 *
 *  Returns:
 *      [
 *        'ok'              => bool,
 *        'notification_sent'=> bool,
 *        'company_name'    => ?string,
 *        'tracking_number' => ?string,
 *        'message'         => ?string,
 *        'raw'             => array (full result from the handler),
 *      ]
 *
 *  Never throws. Never calls exit.
 *  Never triggers the web-endpoint side effects of upTo_shipping.php.
 * ═══════════════════════════════════════════════════════════════
 */
final class ShippingDispatcher
{
    /** Whether the shipping library has already been loaded in this process. */
    private static bool $loaded = false;

    /**
     * Submit a confirmed order to the merchant's configured shipping company.
     *
     * @param  \DB  $db       The existing DB class instance (needed by
     *                        handleShippingCompanyNotification, which uses
     *                        the DB object, not raw PDO).
     * @param  array $order   A row from `orders`, joined with `stores`
     *                        (must contain: id, user_id, store_id,
     *                        customer_name, customer_phone,
     *                        shipping_address, total_amount, ...).
     * @param  int  $userId   Merchant id (matches order.user_id).
     *
     * @return array{ok:bool, notification_sent:bool, company_name:?string,
     *               tracking_number:?string, message:?string, raw:array}
     */
    public static function submit(\DB $db, array $order, int $userId): array
    {
        $empty = [
            'ok'                => false,
            'notification_sent' => false,
            'company_name'      => null,
            'tracking_number'   => null,
            'message'           => null,
            'raw'               => [],
        ];

        // ── Guard: sanity check ─────────────────────────────────
        if (empty($order['id']) || $userId <= 0) {
            $empty['message'] = 'invalid order or user';
            return $empty;
        }

        // ── Load the shipping function library once ─────────────
        if (!self::ensureLoaded()) {
            $empty['message'] = 'shipping library could not be loaded';
            return $empty;
        }

        // ── Call the existing handler ───────────────────────────
        // The handler expects $new_status = 'confirmed' to trigger
        // submission to the shipping company.
        try {
            /** @var array $result */
            $result = \handleShippingCompanyNotification(
                $db,
                $order,
                'confirmed',
                $userId
            );
        } catch (Throwable $e) {
            $empty['message'] = 'shipping handler threw: ' . $e->getMessage();
            return $empty;
        }

        if (!is_array($result)) {
            $empty['message'] = 'shipping handler returned a non-array value';
            return $empty;
        }

        // Normalize the handler's output. The handler returns keys:
        //   notification_sent  bool
        //   company_name       string
        //   message            string
        //   tracking_number    string  (on success)
        //   duplicate_found    bool    (on duplicate detection)
        $notificationSent = (bool)($result['notification_sent'] ?? false);

        return [
            'ok'                => $notificationSent,
            'notification_sent' => $notificationSent,
            'company_name'      => isset($result['company_name'])
                                        ? (string)$result['company_name']
                                        : null,
            'tracking_number'   => isset($result['tracking_number'])
                                        ? (string)$result['tracking_number']
                                        : null,
            'message'           => isset($result['message'])
                                        ? (string)$result['message']
                                        : null,
            'raw'               => $result,
        ];
    }

    /**
     * Load the shipping function library once. Returns true on success.
     *
     * Uses a define() guard so the top-level endpoint code inside
     * upTo_shipping.php is skipped when included from CLI.
     */
    private static function ensureLoaded(): bool
    {
        if (self::$loaded) {
            return true;
        }

        // Define the guard BEFORE including the file. This tells the
        // file to skip its web-endpoint logic and only define the
        // functions.
        if (!defined('SHIPPING_DISPATCHER_INCLUDED')) {
            define('SHIPPING_DISPATCHER_INCLUDED', 1);
        }

        // Locate the file. It's at <root>/controllers/upTo_shipping.php.
        // From this file (<root>/ai_confirmation/core/ShippingDispatcher.php),
        // that is ../../controllers/upTo_shipping.php.
        $path = __DIR__ . '/../../controllers/upTo_shipping_api.php';

        if (!is_file($path)) {
            error_log('[ShippingDispatcher] controllers/upTo_shipping.php not found at ' . $path);
            return false;
        }

        // Silence warnings from the include in case upTo_shipping.php
        // references $_SERVER or $_SESSION in ways that don't apply
        // in CLI. Errors would still be logged by error_log().
        $prevErr = error_reporting(0);
        try {
            require_once $path;
        } catch (Throwable $e) {
            error_log('[ShippingDispatcher] include failed: ' . $e->getMessage());
            error_reporting($prevErr);
            return false;
        } finally {
            error_reporting($prevErr);
        }

        // The function must now exist.
        if (!function_exists('handleShippingCompanyNotification')) {
            error_log('[ShippingDispatcher] handleShippingCompanyNotification() was not defined by upTo_shipping.php');
            return false;
        }

        self::$loaded = true;
        return true;
    }
} ?>