<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

header('Content-Type: application/json');

// Check if anyone is logged in
if(!Session::exists(Config::get('session/session_name'))){
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$db = DB::getInstance();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_GET['query']) || empty(trim($_GET['query']))) {
    echo json_encode(['success' => false, 'message' => 'Search query is required']);
    exit;
}

/* ─────────────────────────────────────────────────────────────
   RESOLVE CALLER IDENTITY
   Returns:
     [
       'type'    => 'user' | 'agent',
       'user_id' => <store owner user_id>   (always set when resolvable),
       'agent_id'=> <agent_id | null>
     ]
   ───────────────────────────────────────────────────────────── */
function resolveCaller($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    $identifier = $_SESSION['user']['username'];

    // ── 1) If session explicitly says this is a user (store owner/admin)
    if (!empty($_SESSION['user']['id']) && (($_SESSION['user']['role'] ?? '') !== 'agent')) {
        return [
            'type'     => 'user',
            'user_id'  => (int)$_SESSION['user']['id'],
            'agent_id' => null
        ];
    }

    // ── 2) Try to resolve as a user (store owner/admin) in users table
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return [
            'type'     => 'user',
            'user_id'  => (int)$user[0]['id'],
            'agent_id' => null
        ];
    }

    // ── 3) Try to resolve as an agent
    $agent = $db->getIOrN("agents", "email", $identifier);
    if (!$agent) {
        $agent = $db->getIOrN("agents", "name", $identifier);
    }

    if ($agent && isset($agent->id)) {
        // The store owner for this agent
        $ownerUserId = $agent->user_id ?? null;

        // Fallback: look up via user_id relation
        if (!$ownerUserId && !empty($agent->user_id)) {
            $ownerUserId = (int)$agent->user_id;
        }

        return [
            'type'     => 'agent',
            'user_id'  => $ownerUserId ? (int)$ownerUserId : null,
            'agent_id' => (int)$agent->id
        ];
    }

    return null;
}

try {
    $caller = resolveCaller($db);

    if (!$caller) {
        echo json_encode(['success' => false, 'message' => 'Could not resolve caller identity']);
        exit;
    }

    $user_id  = $caller['user_id'];   // store owner id (may be null for orphan agent)
    $agent_id = $caller['agent_id'];  // null when caller is a user/admin
    $callerType = $caller['type'];

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Store owner not found for this account']);
        exit;
    }

    $query = trim($_GET['query']);
    if (strlen($query) < 2) {
        echo json_encode(['success' => false, 'message' => 'Query must be at least 2 characters long']);
        exit;
    }

    /* ─────────────────────────────────────────────────────────
       SEARCH CLIENTS (orders table)
       - Admins:      see all orders of their store
       - Agents:      see all orders of their store too
                      (the "assigned_to_current_agent" flag tells us
                       which clients are assigned to THIS agent)
       ───────────────────────────────────────────────────────── */
    $search_query = "
        SELECT 
            MIN(o.id) as order_id,
            o.customer_name,
            o.customer_email,
            o.customer_phone,
            o.customer_ville as city,
            o.shipping_address,
            o.billing_address,
            COUNT(*) as total_orders,
            MAX(o.order_date) as last_order_date,
            SUM(o.total_amount) as total_spent,
            o.currency
        FROM orders o
        WHERE 
            (
                o.customer_name  LIKE ? OR 
                o.customer_email LIKE ? OR 
                o.customer_phone LIKE ? OR
                o.customer_ville LIKE ?
            )
            AND o.customer_name IS NOT NULL 
            AND o.customer_name != ''
            AND o.user_id = ?
        GROUP BY 
            LOWER(o.customer_name), 
            LOWER(o.customer_email), 
            o.customer_phone
        ORDER BY 
            last_order_date DESC,
            total_orders DESC
        LIMIT 20
    ";

    $search_param = '%' . $query . '%';
    $clients_result = $db->getThisQuery($search_query, [
        $search_param, 
        $search_param, 
        $search_param, 
        $search_param,
        $user_id
    ]);

    if (empty($clients_result)) {
        echo json_encode([
            'success'   => true,
            'data'      => [],
            'message'   => 'No clients found',
            'user_id'   => $user_id,
            'agent_id'  => $agent_id,
            'caller'    => $callerType
        ]);
        exit;
    }

    $clients = array_map(function($client) use ($db, $agent_id, $user_id) {
        // Most recent order for this client
        $recent_order = $db->getThisQuery("
            SELECT o.id, o.order_number, o.status, o.shipping_status, o.payment_status
            FROM orders o
            WHERE o.customer_name  = ? 
                AND o.customer_email = ? 
                AND o.customer_phone = ?
                AND o.user_id = ?
            ORDER BY o.order_date DESC
            LIMIT 1
        ", [
            $client['customer_name'],
            $client['customer_email'],
            $client['customer_phone'],
            $user_id
        ]);

        // Only compute assignment flag when caller is an agent
        $assigned_to_current_agent = false;
        if ($agent_id) {
            $agent_assignment = $db->getThisQuery("
                SELECT COUNT(*) as assigned_count
                FROM orders o
                JOIN agent_order_assignments aoa ON o.id = aoa.order_id
                WHERE o.customer_name  = ? 
                    AND o.customer_email = ? 
                    AND o.customer_phone = ?
                    AND o.user_id = ?
                    AND aoa.agent_id = ?
            ", [
                $client['customer_name'],
                $client['customer_email'],
                $client['customer_phone'],
                $user_id,
                $agent_id
            ]);
            $assigned_to_current_agent =
                isset($agent_assignment[0]['assigned_count']) &&
                $agent_assignment[0]['assigned_count'] > 0;
        }

        return [
            'id'                        => intval($client['order_id']),
            'name'                      => $client['customer_name'],
            'email'                     => $client['customer_email'],
            'phone'                     => $client['customer_phone'],
            'city'                      => $client['city'],
            'address'                   => $client['shipping_address'] ?: $client['billing_address'],
            'total_orders'              => intval($client['total_orders']),
            'last_order_date'           => $client['last_order_date'],
            'total_spent'               => floatval($client['total_spent']),
            'currency'                  => $client['currency'],
            'recent_order'              => !empty($recent_order) ? [
                'id'              => $recent_order[0]['id'],
                'order_number'    => $recent_order[0]['order_number'],
                'status'          => $recent_order[0]['status'],
                'shipping_status' => $recent_order[0]['shipping_status'],
                'payment_status'  => $recent_order[0]['payment_status']
            ] : null,
            'assigned_to_current_agent' => $assigned_to_current_agent,
            'user_id'                   => $user_id
        ];
    }, $clients_result);

    echo json_encode([
        'success'  => true,
        'data'     => $clients,
        'count'    => count($clients),
        'user_id'  => $user_id,
        'agent_id' => $agent_id,
        'caller'   => $callerType
    ]);

} catch (Exception $e) {
    error_log("Error in search_clients.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while searching for clients',
        'error'   => $e->getMessage()
    ]);
}
?>