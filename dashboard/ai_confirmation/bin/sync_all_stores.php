<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════
 *  OBGecom — AI Confirmation System
 *  bin/sync_all_stores.php
 *
 *  Runs on a cron schedule (every 5 minutes).
 *  Syncs every connected store on the platform, sequentially.
 *
 *  Reuses the existing functions from controllers/sync_orders.php:
 *    - syncYouCanOrders()
 *    - syncWooCommerceOrders()
 *    - syncShopifyOrders()
 *
 *  Each store is synced in a try/catch so one broken store
 *  does not stop the rest.
 *
 *  CLI flags:
 *      --store=ID      sync only store ID
 *      --user=ID       sync only stores belonging to user ID
 *      --verbose       print per-store progress
 *
 *  Cron entry (every 5 minutes):
 *      *\/5 * * * * /usr/bin/php /www/wwwroot/obgecom/dashboard/ai_confirmation/bin/sync_all_stores.php >> /www/wwwroot/obgecom/dashboard/ai_confirmation/logs/sync.log 2>&1
 * ═══════════════════════════════════════════════════════════════
 */

date_default_timezone_set('UTC');

// Locate project root: this file is at <root>/ai_confirmation/bin/sync_all_stores.php
$projectRoot = dirname(__DIR__, 2);   // → /www/wwwroot/obgecom/dashboard

// Change to controllers directory BEFORE any require. This matches
// the working directory Apache uses when it runs sync_orders.php,
// so all relative '../core/...' paths resolve correctly.
chdir($projectRoot . '/controllers');

// ─── Load the project bootstrap ─────────────────────────────────
require_once '../core/init.php';

// ─── Load sync functions from the existing sync_orders.php ──────
// The file executes HTTP request handling at the top — we must
// skip that by faking a real POST so its endpoint logic runs
// without exiting and its function definitions get loaded.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'sync_orders';
$_SESSION['user'] = ['id' => 0, 'username' => '__cron__'];

// Buffer any output that sync_orders.php produces (JSON response
// we don't care about) so the cron output stays clean.
ob_start();
require_once $projectRoot . '/controllers/sync_orders.php';
$unused = ob_get_clean();

// ─── CLI argument parsing ───────────────────────────────────────
$argv    = $_SERVER['argv'] ?? [];
$verbose = in_array('--verbose', $argv, true);
$onlyStore = null;
$onlyUser  = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--store=(\d+)$/', $arg, $m)) $onlyStore = (int)$m[1];
    if (preg_match('/^--user=(\d+)$/',  $arg, $m)) $onlyUser  = (int)$m[1];
}

$db = DB::getInstance();

// ─── Fetch stores to sync ───────────────────────────────────────
$sql = "SELECT * FROM stores WHERE is_connected = 1";

$params = [];
if ($onlyStore !== null) {
    $sql .= " AND id = ?";
    $params[] = $onlyStore;
}
if ($onlyUser !== null) {
    $sql .= " AND user_id = ?";
    $params[] = $onlyUser;
}
$sql .= " ORDER BY id ASC";

$stores = $db->getThisQuery($sql, $params);

$startedAt = microtime(true);
$totalStores  = count($stores);
$totalSynced  = 0;
$errors       = 0;

fwrite(STDOUT, sprintf(
    "[%s] sync_all_stores: starting — %d stores to process\n",
    gmdate('Y-m-d H:i:s'),
    $totalStores
));

foreach ($stores as $store) {
    $storeStart = microtime(true);

    try {
        $result = null;

        switch ($store['platform']) {
            case 'youcan':
                $result = syncYouCanOrders($db, $store);
                break;
            case 'woocommerce':
                $result = syncWooCommerceOrders($db, $store);
                break;
            case 'shopify':
                $result = syncShopifyOrders($db, $store);
                break;
            default:
                fwrite(STDERR, sprintf(
                    "[%s] store %d (%s) — unknown platform '%s'\n",
                    gmdate('Y-m-d H:i:s'),
                    $store['id'],
                    $store['storeName'],
                    $store['platform']
                ));
                continue 2;
        }

        $synced  = (int)($result['synced']  ?? 0);
        $updated = (int)($result['updated'] ?? 0);

        $totalSynced += $synced;

        if ($verbose || $synced > 0 || $updated > 0) {
            fwrite(STDOUT, sprintf(
                "[%s] store %d (%s / %s) — synced=%d updated=%d in %.2fs\n",
                gmdate('Y-m-d H:i:s'),
                $store['id'],
                $store['storeName'],
                $store['platform'],
                $synced,
                $updated,
                microtime(true) - $storeStart
            ));
        }

    } catch (\Throwable $e) {
        $errors++;
        fwrite(STDERR, sprintf(
            "[%s] store %d (%s) FAILED: %s\n",
            gmdate('Y-m-d H:i:s'),
            $store['id'],
            $store['storeName'],
            $e->getMessage()
        ));
    }

    // Politeness delay between stores to avoid hammering the platform APIs
    usleep(200000); // 200ms
}

$elapsed = microtime(true) - $startedAt;

fwrite(STDOUT, sprintf(
    "[%s] sync_all_stores: done — %d stores, %d orders synced, %d errors, %.2fs total\n",
    gmdate('Y-m-d H:i:s'),
    $totalStores,
    $totalSynced,
    $errors,
    $elapsed
));

exit(0); ?>