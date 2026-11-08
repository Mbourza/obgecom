<?php
declare(strict_types=1);

namespace OBGecom\AiConfirmation\Core;

use Throwable;

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  ConfirmationConfig — resolve effective per-store settings
 * ═══════════════════════════════════════════════════════════════
 *
 *  Priority chain (highest wins):
 *
 *      1. store_confirmation_settings    (per-store explicit override)
 *      2. system_settings.ai_confirmation (existing merchant kill switch)
 *      3. AI_CONFIRMATION_DEFAULT_*       (from .env, via config loader)
 *
 *  The system_settings layer is a MASTER kill switch for the
 *  merchant (user_id). If it's '0', the whole subsystem is off
 *  for that merchant, even if a store row exists saying it's on.
 *
 *  Uses the project's existing DB class exclusively. No raw PDO.
 *
 *  Usage:
 *
 *      $cfg = ConfirmationConfig::forStore($db, $storeId, $userId);
 *      if (!$cfg['enabled']) {
 *          return; // skip entirely
 *      }
 *      $timeout = $cfg['timeout_minutes'];
 *
 *  The returned array is stable. Adding fields is safe; renaming
 *  or removing fields is a breaking change for callers.
 * ═══════════════════════════════════════════════════════════════
 */
final class ConfirmationConfig
{
    /**
     * Per-process cache, keyed by store_id.
     * A worker processing 100 orders for the same store hits the DB once.
     *
     * @var array<int,array<string,mixed>>
     */
    private static array $cache = [];

    /**
     * Resolve effective confirmation configuration for a store.
     *
     * @return array{
     *   store_id: int,
     *   user_id: int,
     *   enabled: bool,
     *   timeout_minutes: int,
     *   timeout_seconds: int,
     *   second_timeout_minutes: int,
     *   second_timeout_seconds: int,
     *   max_ai_replies: int,
     *   confirm_threshold: float,
     *   cancel_threshold: float,
     *   agent_fallback_enabled: bool,
     *   auto_shipping_enabled: bool,
     *   whatsapp_template_name: string,
     *   whatsapp_language: string,
     *   source: string
     * }
     */
    public static function forStore(\DB $db, int $storeId, int $userId): array
    {
        if (isset(self::$cache[$storeId])) {
            return self::$cache[$storeId];
        }

        // ── Layer 3: .env defaults ───────────────────────────
        if (!function_exists('ai_confirmation_config')) {
            require_once __DIR__ . '/../config/ai_confirmation.php';
        }
        $env = ai_confirmation_config();
        $defaults = $env['defaults'];

        // ── Layer 2: merchant-wide master switch ─────────────
        // Read the existing `system_settings.ai_confirmation` column.
        // If the merchant has explicitly turned this off, it wins.
        $merchantEnabled = self::readMerchantMasterSwitch($db, $userId);

        // ── Layer 1: per-store row (if any) ──────────────────
        $storeRow = self::readStoreRow($db, $storeId);

        // ── Merge ────────────────────────────────────────────
        $enabled = ($merchantEnabled === false)
            ? false
            : (bool)($storeRow['ai_confirmation_enabled'] ?? true);

        // Optional global kill switch (from .env)
        if (!$env['enabled']) {
            $enabled = false;
        }

        $timeoutMinutes       = (int)($storeRow['first_timeout_minutes']  ?? $defaults['timeout_minutes']);
        $secondTimeoutMinutes = (int)($storeRow['second_timeout_minutes'] ?? $defaults['second_timeout_minutes']);

        $cfg = [
            'store_id' => $storeId,
            'user_id'  => $userId,
            'enabled'  => $enabled,

            'timeout_minutes'        => $timeoutMinutes,
            'timeout_seconds'        => $timeoutMinutes * 60,
            'second_timeout_minutes' => $secondTimeoutMinutes,
            'second_timeout_seconds' => $secondTimeoutMinutes * 60,

            'max_ai_replies'    => (int)   ($storeRow['max_ai_replies']    ?? $defaults['max_ai_replies']),
            'confirm_threshold' => (float) ($storeRow['confirm_threshold'] ?? $defaults['confirm_threshold']),
            'cancel_threshold'  => (float) ($storeRow['cancel_threshold']  ?? $defaults['cancel_threshold']),

            'agent_fallback_enabled' => (bool)($storeRow['agent_fallback_enabled'] ?? $defaults['agent_fallback_enabled']),
            'auto_shipping_enabled'  => (bool)($storeRow['auto_shipping_enabled']  ?? $defaults['auto_shipping_enabled']),

            'whatsapp_template_name' => (string)($storeRow['whatsapp_template_name'] ?? $env['whatsapp']['default_template']),
            'whatsapp_language'      => (string)($storeRow['whatsapp_language']      ?? $env['whatsapp']['default_language']),

            'source' => self::describeSource($storeRow, $merchantEnabled, $env['enabled']),
        ];

        self::$cache[$storeId] = $cfg;
        return $cfg;
    }

    /**
     * Clear the per-process cache.
     * Useful in tests, and in long-running workers that need to
     * pick up an admin change without restarting.
     */
    public static function clearCache(?int $storeId = null): void
    {
        if ($storeId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$storeId]);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Private — layer readers
    // ═══════════════════════════════════════════════════════════

    /**
     * Read the merchant-wide master switch from system_settings.
     *
     * The existing schema has:
     *   system_settings.ai_confirmation ENUM('0','1') NOT NULL DEFAULT '0'
     *
     * We return:
     *   true  → merchant has enabled AI confirmation
     *   false → merchant has disabled it (kill switch)
     *   null  → no row found (fall back to defaults)
     *
     * @return bool|null
     */
    private static function readMerchantMasterSwitch(\DB $db, int $userId): ?bool
    {
        try {
            $rows = $db->getThisQuery(
                "SELECT setting_value, ai_confirmation
                   FROM system_settings
                  WHERE user_id = ?
                    AND setting_key = 'ai_confirmation'
                  LIMIT 1",
                [$userId]
            );

            if (empty($rows)) {
                return null; // no row → defaults apply
            }

            $row = $rows[0];

            // Prefer setting_value, fall back to the ai_confirmation column
            $val = (string)($row['setting_value'] ?? '');
            if ($val === '') {
                $val = (string)($row['ai_confirmation'] ?? '');
            }

            return $val === '1';
            
        } catch (Throwable $e) {
            // Table missing or column renamed — do not crash the worker.
            // Log once so we notice.
            error_log('[ConfirmationConfig] system_settings read failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Read the per-store row from store_confirmation_settings.
     *
     * Returns an empty array if there is no row. All keys in the
     * caller are then filled from defaults.
     *
     * @return array<string,mixed>
     */
    private static function readStoreRow(\DB $db, int $storeId): array
    {
        try {
            $rows = $db->getThisQuery(
                "SELECT
                    ai_confirmation_enabled,
                    first_timeout_minutes,
                    second_timeout_minutes,
                    max_ai_replies,
                    confirm_threshold,
                    cancel_threshold,
                    agent_fallback_enabled,
                    auto_shipping_enabled,
                    whatsapp_template_name,
                    whatsapp_language
                   FROM store_confirmation_settings
                  WHERE store_id = ?
                  LIMIT 1",
                [$storeId]
            );

            return (is_array($rows) && isset($rows[0])) ? $rows[0] : [];
        } catch (Throwable $e) {
            error_log('[ConfirmationConfig] store_confirmation_settings read failed: ' . $e->getMessage());
            return [];
        }
    }

    private static function describeSource(array $storeRow, ?bool $merchantEnabled, bool $envEnabled): string
    {
        if (!$envEnabled) {
            return 'disabled_by_env';
        }
        if ($merchantEnabled === false) {
            return 'disabled_by_merchant';
        }
        if (!empty($storeRow)) {
            return 'store_row';
        }
        return 'defaults';
    }
} ?>