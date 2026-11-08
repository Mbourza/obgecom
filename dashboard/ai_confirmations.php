<?php
/**
 * ai_confirmations.php — AI Confirmation History Dashboard
 *
 * Shows every order that passed through the AI confirmation system,
 * with full conversation, event log, and queue history.
 */

if (file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

$db = DB::getInstance();

if (!Session::exists(Config::get('session/session_name'))) {
    Redirect::to('../lg');
}

$user_id = getCurrentUserId($db);

if (!$user_id) {
    Redirect::to('../lg');
}

$user = $db->getThisQuery("SELECT id, `name`, `role` FROM users WHERE id = ?", [$user_id]);
// ═══════════════════════════════════════════════════════════════
//  AJAX HANDLERS
// ═══════════════════════════════════════════════════════════════

if (isset($_POST['action']) && $_POST['action'] === 'list_confirmations') {
    header('Content-Type: application/json');

    try {
        $page       = max(1, (int)($_POST['page'] ?? 1));
        $perPage    = max(1, min(100, (int)($_POST['per_page'] ?? 20)));
        $offset     = ($page - 1) * $perPage;
        $search     = trim($_POST['search'] ?? '');
        $statusF    = trim($_POST['status_filter'] ?? 'all');
        $dateFrom   = trim($_POST['date_from'] ?? '');
        $dateTo     = trim($_POST['date_to'] ?? '');

        $where  = ["o.user_id = ?"];
        $params = [$user_id];

        // Only orders that entered the AI flow
        $where[] = "o.confirmation_status IS NOT NULL";

        if ($search !== '') {
            $where[] = "(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($statusF !== 'all') {
            $where[] = "o.confirmation_status = ?";
            $params[] = $statusF;
        }
        if ($dateFrom !== '') {
            $where[] = "DATE(o.created_at) >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = "DATE(o.created_at) <= ?";
            $params[] = $dateTo;
        }

        $whereSql = implode(' AND ', $where);

        $countRows = $db->getThisQuery(
            "SELECT COUNT(*) AS total FROM orders o WHERE $whereSql",
            $params
        );
        $total = (int)($countRows[0]['total'] ?? 0);

        $rows = $db->getThisQuery(
            "SELECT
                o.id, o.order_number, o.customer_name, o.customer_phone,
                o.customer_ville, o.total_amount, o.currency,
                o.confirmation_status, o.status, o.bot_attempts,
                o.confirmation_deadline, o.confirmed_at, o.cancelled_at,
                o.assigned_agent_id, o.shipping_status, o.tracking_number,
                o.created_at, o.updated_at,
                o.bot_sent, o.bot_sent_at, o.last_bot_message_at,
                (SELECT COUNT(*) FROM whatsapp_messages w
                  WHERE w.order_id = o.id AND w.direction = 'OUTBOUND') AS outbound_count,
                (SELECT COUNT(*) FROM whatsapp_messages w
                  WHERE w.order_id = o.id AND w.direction = 'INBOUND')  AS inbound_count,
                (SELECT COUNT(*) FROM confirmation_logs l
                  WHERE l.order_id = o.id) AS event_count
             FROM orders o
             WHERE $whereSql
             ORDER BY o.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        // Summary stats
        $stats = $db->getThisQuery(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN confirmation_status IN ('confirmed_by_bot','confirmed_by_agent') THEN 1 ELSE 0 END) AS confirmed,
                SUM(CASE WHEN confirmation_status IN ('cancelled_by_bot','cancelled_by_agent') THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN confirmation_status IN ('bot_pending','bot_first_message_sent','bot_second_message_sent') THEN 1 ELSE 0 END) AS waiting,
                SUM(CASE WHEN confirmation_status IN ('manual_required','manual_required_unassigned','awaiting_agent_confirmation') THEN 1 ELSE 0 END) AS manual,
                AVG(NULLIF(bot_attempts, 0)) AS avg_attempts
             FROM orders o
             WHERE o.user_id = ? AND o.confirmation_status IS NOT NULL",
            [$user_id]
        );

        echo json_encode([
            'success'    => true,
            'data'       => $rows ?: [],
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
            ],
            'summary' => $stats[0] ?? [],
        ]);
        exit;
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'order_detail') {
    header('Content-Type: application/json');

    try {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId <= 0) {
            throw new \Exception('order_id required');
        }

        // Ownership check
        $own = $db->getThisQuery(
            "SELECT id FROM orders WHERE id = ? AND user_id = ? LIMIT 1",
            [$orderId, $user_id]
        );
        if (empty($own)) {
            throw new \Exception('Order not found or not owned by you');
        }

        // Order
        $orderRows = $db->getThisQuery(
            "SELECT o.*,
                    s.storeName, s.platform,
                    a.name AS agent_name
               FROM orders o
               LEFT JOIN stores s ON o.store_id = s.id
               LEFT JOIN agents a ON o.assigned_agent_id = a.id
              WHERE o.id = ? LIMIT 1",
            [$orderId]
        );
        $order = $orderRows[0] ?? null;

        // Items
        $items = $db->getThisQuery(
            "SELECT product_name, product_sku, quantity, unit_price, total_price
               FROM order_items WHERE order_id = ? ORDER BY id ASC",
            [$orderId]
        );

        // Conversation — every message in order
        $messages = $db->getThisQuery(
            "SELECT id, direction, message_type, message, provider,
                    provider_message_id, status, ai_intent, ai_confidence, created_at
               FROM whatsapp_messages
              WHERE order_id = ?
              ORDER BY created_at ASC, id ASC",
            [$orderId]
        );

        // Event log — every state transition and audit event
        $events = $db->getThisQuery(
            "SELECT id, actor_type, actor_id, event_type,
                    old_status, new_status, message, raw_payload, created_at
               FROM confirmation_logs
              WHERE order_id = ?
              ORDER BY created_at ASC, id ASC",
            [$orderId]
        );

        // Queue history — every job for this order
        $jobs = $db->getThisQuery(
            "SELECT id, queue, job_type, status, attempts, max_attempts,
                    available_at, reserved_by, last_error,
                    created_at, updated_at
               FROM job_queue
              WHERE order_id = ?
              ORDER BY created_at ASC, id ASC",
            [$orderId]
        );

        echo json_encode([
            'success'  => true,
            'order'    => $order,
            'items'    => $items ?: [],
            'messages' => $messages ?: [],
            'events'   => $events ?: [],
            'jobs'     => $jobs ?: [],
        ]);
        exit;
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ═══════════════════════════════════════════════════════════════
//  Helpers
// ═══════════════════════════════════════════════════════════════

function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }
    $identifier = $_SESSION['user']['username'];
    $user = $db->getThisQuery(
        "SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1",
        [$identifier, $identifier, $identifier]
    );
    return ($user && isset($user[0]['id'])) ? (int)$user[0]['id'] : null;
}

function confStatusMeta(string $status): array {
    $map = [
        'bot_pending'                 => ['label' => 'En attente',              'class' => 'pending'],
        'bot_first_message_sent'      => ['label' => '1er message envoyé',      'class' => 'sent'],
        'bot_second_message_sent'     => ['label' => 'Rappel envoyé',           'class' => 'sent-2'],
        'confirmed_by_bot'            => ['label' => '✅ Confirmé par IA',      'class' => 'confirmed'],
        'cancelled_by_bot'            => ['label' => '❌ Annulé par IA',        'class' => 'cancelled'],
        'manual_required_unassigned'  => ['label' => '⬆️ À assigner',           'class' => 'escalated'],
        'awaiting_agent_confirmation' => ['label' => '👤 Avec agent',           'class' => 'agent'],
        'confirmed_by_agent'          => ['label' => '✅ Confirmé par agent',   'class' => 'confirmed'],
        'cancelled_by_agent'          => ['label' => '❌ Annulé par agent',     'class' => 'cancelled'],
        'manual_required'             => ['label' => '🚨 Action requise',       'class' => 'manual'],
    ];
    return $map[$status] ?? ['label' => $status, 'class' => 'unknown'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Confirmations | OBG</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/common.css">
    <style>
        :root {
            --primary-color: #9c80fd;
            --success-color: #10b981;
            --danger-color:  #ef4444;
            --warning-color: #f59e0b;
            --info-color:    #3b82f6;
        }
        body { font-family: 'Inter', -apple-system, sans-serif; background: #f3f4f6; }

        .main-content { margin-left: 280px; padding: 30px; min-height: 100vh; transition: margin-left .3s; }
        .main-content.expanded { margin-left: 80px; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); text-align: center; }
        .stat-card .stat-number { font-size: 26px; font-weight: 700; color: #1f2937; }
        .stat-card .stat-label { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .stat-card .stat-icon { font-size: 20px; margin-bottom: 8px; }

        /* Filter bar */
        .filter-bar { background: #fff; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px;
                      display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
                      box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .filter-bar .form-control, .filter-bar .form-select {
            border-radius: 8px; border: 1px solid #e5e7eb; padding: 8px 12px; font-size: 14px;
        }
        .filter-bar .form-control:focus, .filter-bar .form-select:focus {
            border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(156,128,253,.2);
        }

        /* Table */
        .table-container { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); overflow: hidden; }
        .table-container thead th {
            background: #f8fafc; color: #1f2937; font-weight: 600; font-size: 12px;
            text-transform: uppercase; letter-spacing: .5px; padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb; white-space: nowrap;
        }
        .table-container tbody td { padding: 12px 16px; vertical-align: middle; border-bottom: 1px solid #f1f3f4; }
        .table-container tbody tr { cursor: pointer; }
        .table-container tbody tr:hover { background: #f8fafc; }

        /* Status badges */
        .conf-badge { display: inline-flex; align-items: center; gap: 4px;
                      padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
        .conf-badge.pending     { background: #fef3c7; color: #92400e; }
        .conf-badge.sent        { background: #dbeafe; color: #1e40af; }
        .conf-badge.sent-2      { background: #dbeafe; color: #1e3a8a; }
        .conf-badge.confirmed   { background: #d1fae5; color: #065f46; }
        .conf-badge.cancelled   { background: #fee2e2; color: #991b1b; }
        .conf-badge.escalated   { background: #ede9fe; color: #5b21b6; }
        .conf-badge.agent       { background: #c7d2fe; color: #3730a3; }
        .conf-badge.manual      { background: #fecaca; color: #7f1d1d; }
        .conf-badge.unknown     { background: #f3f4f6; color: #6b7280; }

        /* Pagination */
        .pagination-container { display: flex; justify-content: space-between; align-items: center;
                                padding: 16px 20px; border-top: 1px solid #e5e7eb; flex-wrap: wrap; gap: 12px; }
        .pagination-controls { display: flex; gap: 4px; align-items: center; }
        .pagination-controls button {
            padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; cursor: pointer;
        }
        .pagination-controls button:hover:not(:disabled) { background: #f3f4f6; }
        .pagination-controls button:disabled { opacity: .5; cursor: not-allowed; }
        .pagination-controls .page-number.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }

        /* Modal — full-screen-ish */
        .modal-xl-custom { max-width: 1200px; }
        .modal-content { border-radius: 16px; border: none; }
        .modal-header { border-bottom: 1px solid #e5e7eb; padding: 20px 24px; }
        .modal-body   { padding: 24px; }
        .modal-footer { border-top: 1px solid #e5e7eb; padding: 16px 24px; }

        /* Detail sections */
        .detail-section { margin-bottom: 24px; }
        .detail-section h6 {
            font-weight: 700; color: #374151; margin-bottom: 12px;
            font-size: 13px; text-transform: uppercase; letter-spacing: .5px;
        }
        .detail-row { display: flex; padding: 6px 0; border-bottom: 1px solid #f3f4f6; }
        .detail-row .label { width: 160px; font-weight: 500; color: #6b7280; font-size: 13px; flex-shrink: 0; }
        .detail-row .value { flex: 1; color: #1f2937; font-size: 13px; word-break: break-word; }

        /* Timeline (conversation) */
        .timeline { position: relative; padding-left: 20px; }
        .timeline::before {
            content: ''; position: absolute; left: 6px; top: 4px; bottom: 4px;
            width: 2px; background: #e5e7eb;
        }
        .timeline-item { position: relative; margin-bottom: 16px; padding-left: 20px; }
        .timeline-item::before {
            content: ''; position: absolute; left: -17px; top: 6px;
            width: 12px; height: 12px; border-radius: 50%;
            background: #d1d5db; border: 2px solid #fff;
        }
        .timeline-item.inbound::before  { background: var(--info-color); }
        .timeline-item.outbound::before { background: var(--success-color); }
        .timeline-item.system::before   { background: var(--warning-color); }

        .timeline-bubble {
            background: #f8fafc; padding: 10px 14px; border-radius: 8px; font-size: 13px;
            line-height: 1.5; white-space: pre-wrap; word-break: break-word;
        }
        .timeline-item.inbound .timeline-bubble  { background: #eff6ff; }
        .timeline-item.outbound .timeline-bubble { background: #f0fdf4; }

        .timeline-meta {
            font-size: 11px; color: #9ca3af; margin-bottom: 4px;
            display: flex; gap: 12px; flex-wrap: wrap;
        }

        /* Event log */
        .event-row {
            display: flex; gap: 12px; padding: 8px 0;
            border-bottom: 1px solid #f3f4f6; font-size: 13px;
        }
        .event-row .event-time { color: #9ca3af; min-width: 130px; font-family: monospace; font-size: 12px; }
        .event-row .event-type { font-weight: 600; color: #374151; min-width: 180px; }
        .event-row .event-detail { flex: 1; color: #4b5563; }

        /* Jobs table */
        .jobs-table { font-size: 12px; }

        /* Loading */
        #loadingSpinner { display: none; text-align: center; padding: 40px; }

        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-bar { flex-direction: column; align-items: stretch; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php
$currentPage = 'ai_confirmations';
require_once('../assets/sidebar.php');
?>

<main class="main-content" id="mainContent">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold" style="color:#1f2937; font-size:1.5rem;">
                <i class="bi bi-robot me-2" style="color:var(--primary-color);"></i>
                AI Confirmations
            </h2>
            <p class="text-muted small mt-1 mb-0">
                Suivez toutes les confirmations IA : commandes, conversations WhatsApp, événements et journal des jobs.
            </p>
        </div>
        <div>
            <button class="btn btn-outline-primary btn-sm" onclick="loadConfirmations()">
                <i class="bi bi-arrow-clockwise"></i> Actualiser
            </button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-robot" style="color:var(--primary-color);"></i></div>
            <div class="stat-number" id="stat-total">0</div>
            <div class="stat-label">Total commandes IA</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-check-circle" style="color:var(--success-color);"></i></div>
            <div class="stat-number" id="stat-confirmed">0</div>
            <div class="stat-label">Confirmées</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-x-circle" style="color:var(--danger-color);"></i></div>
            <div class="stat-number" id="stat-cancelled">0</div>
            <div class="stat-label">Annulées</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-hourglass-split" style="color:var(--warning-color);"></i></div>
            <div class="stat-number" id="stat-waiting">0</div>
            <div class="stat-label">En attente</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-person-up" style="color:var(--info-color);"></i></div>
            <div class="stat-number" id="stat-manual">0</div>
            <div class="stat-label">Traitées par agent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-bar-chart" style="color:#8b5cf6;"></i></div>
            <div class="stat-number" id="stat-avg">0.0</div>
            <div class="stat-label">Moy. tentatives IA</div>
        </div>
    </div>

    <div class="filter-bar">
        <div style="flex:1; min-width:180px;">
            <input type="text" class="form-control" id="searchInput" placeholder="Commande, client, téléphone..." onkeyup="debouncedLoad()">
        </div>
        <div style="min-width:200px;">
            <select class="form-select" id="statusFilter" onchange="applyFilters()">
                <option value="all">Tous les statuts</option>
                <option value="bot_pending">En attente</option>
                <option value="bot_first_message_sent">1er message envoyé</option>
                <option value="bot_second_message_sent">Rappel envoyé</option>
                <option value="confirmed_by_bot">Confirmé par IA</option>
                <option value="cancelled_by_bot">Annulé par IA</option>
                <option value="manual_required_unassigned">À assigner</option>
                <option value="awaiting_agent_confirmation">Avec agent</option>
                <option value="confirmed_by_agent">Confirmé par agent</option>
                <option value="cancelled_by_agent">Annulé par agent</option>
                <option value="manual_required">Action requise</option>
            </select>
        </div>
        <div style="min-width:140px;">
            <input type="date" class="form-control" id="dateFrom" onchange="applyFilters()">
        </div>
        <div style="min-width:140px;">
            <input type="date" class="form-control" id="dateTo" onchange="applyFilters()">
        </div>
        <button class="btn btn-secondary btn-sm" onclick="resetFilters()">
            <i class="bi bi-eraser"></i> Réinitialiser
        </button>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Commande</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Statut IA</th>
                        <th>Tentatives</th>
                        <th>Dernière activité</th>
                        <th>Deadline</th>
                        <th>Livraison</th>
                        <th>Messages</th>
                    </tr>
                </thead>
                <tbody id="confirmationsBody">
                    <tr><td colspan="9" class="text-center py-4 text-muted">Chargement...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="pagination-container">
            <div class="pagination-info" id="paginationInfo">
                Affichage de <span id="startIndex">0</span> à <span id="endIndex">0</span>
                sur <span id="totalItems">0</span> commandes
            </div>
            <div class="pagination-controls">
                <select class="form-select form-select-sm" style="width:auto;" id="perPageSelect" onchange="changePerPage()">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <button onclick="goToPage('first')" id="firstPageBtn" disabled><i class="bi bi-chevron-double-left"></i></button>
                <button onclick="goToPage('prev')"  id="prevPageBtn"  disabled><i class="bi bi-chevron-left"></i></button>
                <span id="pageNumbers"></span>
                <button onclick="goToPage('next')"  id="nextPageBtn"  disabled><i class="bi bi-chevron-right"></i></button>
                <button onclick="goToPage('last')"  id="lastPageBtn"  disabled><i class="bi bi-chevron-double-right"></i></button>
            </div>
        </div>
    </div>
</main>

<!-- Detail modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl-custom modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-robot me-2" style="color:var(--primary-color);"></i>
                    <span id="modalOrderTitle">Chargement...</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <a class="btn btn-primary" id="viewOrderBtn" href="#" style="background:var(--primary-color); border:none;">
                    <i class="bi bi-box-arrow-up-right"></i> Ouvrir la commande
                </a>
            </div>
        </div>
    </div>
</div>

<div id="loadingSpinner">
    <div class="spinner-border text-primary"></div>
    <p class="mt-2 text-muted">Chargement...</p>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentPage = 1;
let totalPages  = 1;
let perPage     = 20;
let searchTimer = null;

const CONFIRM_STATUS = {
    'bot_pending':                 { label: 'En attente',          cls: 'pending' },
    'bot_first_message_sent':      { label: '1er message envoyé',  cls: 'sent' },
    'bot_second_message_sent':     { label: 'Rappel envoyé',       cls: 'sent-2' },
    'confirmed_by_bot':            { label: '✅ Confirmé par IA',  cls: 'confirmed' },
    'cancelled_by_bot':            { label: '❌ Annulé par IA',    cls: 'cancelled' },
    'manual_required_unassigned':  { label: '⬆️ À assigner',       cls: 'escalated' },
    'awaiting_agent_confirmation': { label: '👤 Avec agent',       cls: 'agent' },
    'confirmed_by_agent':          { label: '✅ Confirmé par agent', cls: 'confirmed' },
    'cancelled_by_agent':          { label: '❌ Annulé par agent',   cls: 'cancelled' },
    'manual_required':             { label: '🚨 Action requise',   cls: 'manual' },
};

document.addEventListener('DOMContentLoaded', loadConfirmations);

function debouncedLoad() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 400);
}

function applyFilters() {
    currentPage = 1;
    loadConfirmations();
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = 'all';
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    applyFilters();
}

function changePerPage() {
    perPage = parseInt(document.getElementById('perPageSelect').value);
    currentPage = 1;
    loadConfirmations();
}

function loadConfirmations() {
    const form = new FormData();
    form.append('action',        'list_confirmations');
    form.append('page',          currentPage);
    form.append('per_page',      perPage);
    form.append('search',        document.getElementById('searchInput').value.trim());
    form.append('status_filter', document.getElementById('statusFilter').value);
    form.append('date_from',     document.getElementById('dateFrom').value);
    form.append('date_to',       document.getElementById('dateTo').value);

    fetch(window.location.href, { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('confirmationsBody').innerHTML =
                    `<tr><td colspan="9" class="text-center py-4 text-danger">${data.message || 'Erreur'}</td></tr>`;
                return;
            }
            renderRows(data.data);
            updatePagination(data.pagination);
            updateStats(data.summary);
        })
        .catch(err => {
            console.error(err);
            document.getElementById('confirmationsBody').innerHTML =
                `<tr><td colspan="9" class="text-center py-4 text-danger">Erreur de connexion</td></tr>`;
        });
}

function renderRows(rows) {
    const tbody = document.getElementById('confirmationsBody');

    if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-5 text-muted">
            <i class="bi bi-inbox" style="font-size:32px; display:block; margin-bottom:12px;"></i>
            Aucune commande IA trouvée
        </td></tr>`;
        return;
    }

    tbody.innerHTML = rows.map(r => {
        const cs    = CONFIRM_STATUS[r.confirmation_status] || { label: r.confirmation_status, cls: 'unknown' };
        const total = parseFloat(r.total_amount || 0).toFixed(2);
        const msgs  = `${r.outbound_count}↑ / ${r.inbound_count}↓`;
        const deadline = r.confirmation_deadline ? formatDate(r.confirmation_deadline) : '—';
        const lastActivity = r.last_bot_message_at || r.updated_at;

        return `
            <tr onclick="viewOrder(${r.id})">
                <td><strong>#${escapeHtml(r.order_number || r.id)}</strong></td>
                <td>
                    ${escapeHtml(r.customer_name || 'N/A')}
                    <div class="small text-muted">${escapeHtml(r.customer_phone || '')}</div>
                </td>
                <td><strong>${total}</strong> <span class="text-muted">${escapeHtml(r.currency || 'MAD')}</span></td>
                <td><span class="conf-badge ${cs.cls}">${cs.label}</span></td>
                <td class="text-center">${r.bot_attempts || 0}</td>
                <td class="small text-muted">${formatDate(lastActivity)}</td>
                <td class="small text-muted">${deadline}</td>
                <td class="small">${escapeHtml(r.shipping_status || '—')}${r.tracking_number ? '<br><code>' + escapeHtml(r.tracking_number) + '</code>' : ''}</td>
                <td class="small text-muted">${msgs}</td>
            </tr>
        `;
    }).join('');
}

function updatePagination(p) {
    if (!p) return;
    totalPages = p.total_pages;
    currentPage = p.page;

    const start = p.total > 0 ? (currentPage - 1) * perPage + 1 : 0;
    const end   = Math.min(currentPage * perPage, p.total);

    document.getElementById('startIndex').textContent = start;
    document.getElementById('endIndex').textContent   = end;
    document.getElementById('totalItems').textContent = p.total;

    document.getElementById('firstPageBtn').disabled = currentPage <= 1;
    document.getElementById('prevPageBtn').disabled  = currentPage <= 1;
    document.getElementById('nextPageBtn').disabled  = currentPage >= totalPages;
    document.getElementById('lastPageBtn').disabled  = currentPage >= totalPages;

    const pn = document.getElementById('pageNumbers');
    pn.innerHTML = '';
    let a = Math.max(1, currentPage - 2);
    let b = Math.min(totalPages, currentPage + 2);
    for (let i = a; i <= b; i++) {
        const btn = document.createElement('button');
        btn.className = 'page-number' + (i === currentPage ? ' active' : '');
        btn.textContent = i;
        btn.onclick = () => goToPage(i);
        pn.appendChild(btn);
    }
}

function updateStats(s) {
    if (!s) return;
    document.getElementById('stat-total').textContent     = s.total || 0;
    document.getElementById('stat-confirmed').textContent = s.confirmed || 0;
    document.getElementById('stat-cancelled').textContent = s.cancelled || 0;
    document.getElementById('stat-waiting').textContent   = s.waiting || 0;
    document.getElementById('stat-manual').textContent    = s.manual || 0;
    document.getElementById('stat-avg').textContent       = parseFloat(s.avg_attempts || 0).toFixed(1);
}

function goToPage(p) {
    if (p === 'first') p = 1;
    else if (p === 'prev') p = currentPage - 1;
    else if (p === 'next') p = currentPage + 1;
    else if (p === 'last') p = totalPages;
    if (p < 1 || p > totalPages || p === currentPage) return;
    currentPage = p;
    loadConfirmations();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ─── Detail view ─────────────────────────────────────────────
function viewOrder(orderId) {
    document.getElementById('modalOrderTitle').textContent = 'Chargement...';
    document.getElementById('detailBody').innerHTML =
        '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    document.getElementById('viewOrderBtn').href = `orders.php?view=details&order_id=${orderId}`;

    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();

    const form = new FormData();
    form.append('action',   'order_detail');
    form.append('order_id', orderId);

    fetch(window.location.href, { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('detailBody').innerHTML =
                    `<div class="alert alert-danger">${escapeHtml(data.message || 'Erreur')}</div>`;
                return;
            }
            renderDetail(data);
        })
        .catch(err => {
            console.error(err);
            document.getElementById('detailBody').innerHTML =
                '<div class="alert alert-danger">Erreur de connexion</div>';
        });
}

function renderDetail(d) {
    const o = d.order;
    document.getElementById('modalOrderTitle').textContent = `#${o.order_number || o.id} — ${o.customer_name || 'N/A'}`;

    const cs = CONFIRM_STATUS[o.confirmation_status] || { label: o.confirmation_status, cls: 'unknown' };

    // ── Order summary
    const orderHtml = `
        <div class="detail-section">
            <h6><i class="bi bi-info-circle"></i> Commande</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-row"><span class="label">Numéro</span><span class="value"><strong>#${escapeHtml(o.order_number || o.id)}</strong></span></div>
                    <div class="detail-row"><span class="label">Client</span><span class="value">${escapeHtml(o.customer_name || 'N/A')}</span></div>
                    <div class="detail-row"><span class="label">Téléphone</span><span class="value">${escapeHtml(o.customer_phone || 'N/A')}</span></div>
                    <div class="detail-row"><span class="label">Email</span><span class="value">${escapeHtml(o.customer_email || 'N/A')}</span></div>
                    <div class="detail-row"><span class="label">Ville</span><span class="value">${escapeHtml(o.customer_ville || 'N/A')}</span></div>
                </div>
                <div class="col-md-6">
                    <div class="detail-row"><span class="label">Montant</span><span class="value"><strong>${parseFloat(o.total_amount || 0).toFixed(2)} ${escapeHtml(o.currency || 'MAD')}</strong></span></div>
                    <div class="detail-row"><span class="label">Statut IA</span><span class="value"><span class="conf-badge ${cs.cls}">${cs.label}</span></span></div>
                    <div class="detail-row"><span class="label">Statut commande</span><span class="value">${escapeHtml(o.status || '')}</span></div>
                    <div class="detail-row"><span class="label">Tentatives IA</span><span class="value">${o.bot_attempts || 0}</span></div>
                    <div class="detail-row"><span class="label">Deadline</span><span class="value">${o.confirmation_deadline ? formatFull(o.confirmation_deadline) : '—'}</span></div>
                    <div class="detail-row"><span class="label">Confirmé le</span><span class="value">${o.confirmed_at ? formatFull(o.confirmed_at) : '—'}</span></div>
                    <div class="detail-row"><span class="label">Annulé le</span><span class="value">${o.cancelled_at ? formatFull(o.cancelled_at) : '—'}</span></div>
                    <div class="detail-row"><span class="label">Agent assigné</span><span class="value">${escapeHtml(o.agent_name || '—')}</span></div>
                    <div class="detail-row"><span class="label">Livraison</span><span class="value">${escapeHtml(o.shipping_status || '—')}${o.tracking_number ? ' — <code>' + escapeHtml(o.tracking_number) + '</code>' : ''}</span></div>
                </div>
            </div>
        </div>
    `;

    // ── Conversation timeline
    let convHtml = '<div class="detail-section"><h6><i class="bi bi-chat-dots"></i> Conversation WhatsApp</h6>';
    if (!d.messages.length) {
        convHtml += '<p class="text-muted small">Aucun message.</p>';
    } else {
        convHtml += '<div class="timeline">';
        d.messages.forEach(m => {
            const dir = (m.direction || '').toLowerCase();
            const cls = dir === 'inbound' ? 'inbound' : 'outbound';
            const typeLabel = escapeHtml(m.message_type || '');
            const aiBit = m.ai_intent
                ? ` · IA: <strong>${escapeHtml(m.ai_intent)}</strong>${m.ai_confidence ? ' (' + m.ai_confidence + ')' : ''}`
                : '';
            convHtml += `
                <div class="timeline-item ${cls}">
                    <div class="timeline-meta">
                        <span><i class="bi bi-${cls === 'inbound' ? 'arrow-down-left' : 'arrow-up-right'}"></i> ${cls.toUpperCase()}</span>
                        <span>${formatFull(m.created_at)}</span>
                        <span>${typeLabel}${aiBit}</span>
                        <span>${escapeHtml(m.status || '')}</span>
                    </div>
                    <div class="timeline-bubble">${escapeHtml(m.message || '')}</div>
                </div>`;
        });
        convHtml += '</div>';
    }
    convHtml += '</div>';

    // ── Event log
    let evHtml = '<div class="detail-section"><h6><i class="bi bi-list-check"></i> Journal des événements</h6>';
    if (!d.events.length) {
        evHtml += '<p class="text-muted small">Aucun événement.</p>';
    } else {
        evHtml += '<div>';
        d.events.forEach(e => {
            const detail = [
                e.old_status && e.new_status ? `→ ${escapeHtml(e.old_status)} → ${escapeHtml(e.new_status)}` : '',
                e.message ? escapeHtml(e.message) : '',
            ].filter(Boolean).join(' — ');
            evHtml += `
                <div class="event-row">
                    <div class="event-time">${formatFull(e.created_at)}</div>
                    <div class="event-type">${escapeHtml(e.event_type)}</div>
                    <div class="event-detail">${detail}</div>
                </div>`;
        });
        evHtml += '</div>';
    }
    evHtml += '</div>';

    // ── Job queue history
    let jobHtml = '<div class="detail-section"><h6><i class="bi bi-stack"></i> Journal des jobs (queue)</h6>';
    if (!d.jobs.length) {
        jobHtml += '<p class="text-muted small">Aucun job enregistré.</p>';
    } else {
        jobHtml += `<div class="table-responsive"><table class="table table-sm jobs-table">
            <thead><tr>
                <th>Queue</th><th>Type</th><th>Statut</th><th>Essais</th><th>Créé</th><th>Mis à jour</th><th>Erreur</th>
            </tr></thead><tbody>`;
        d.jobs.forEach(j => {
            jobHtml += `<tr>
                <td><code>${escapeHtml(j.queue)}</code></td>
                <td><code>${escapeHtml(j.job_type)}</code></td>
                <td>${escapeHtml(j.status)}</td>
                <td>${j.attempts}/${j.max_attempts}</td>
                <td class="small text-muted">${formatFull(j.created_at)}</td>
                <td class="small text-muted">${formatFull(j.updated_at)}</td>
                <td class="small text-danger">${escapeHtml((j.last_error || '').substring(0, 200))}</td>
            </tr>`;
        });
        jobHtml += '</tbody></table></div>';
    }
    jobHtml += '</div>';

    // ── Items
    let itemsHtml = '<div class="detail-section"><h6><i class="bi bi-box-seam"></i> Articles commandés</h6>';
    if (!d.items.length) {
        itemsHtml += '<p class="text-muted small">Aucun article.</p>';
    } else {
        itemsHtml += `<table class="table table-sm">
            <thead><tr><th>Produit</th><th>SKU</th><th>Qté</th><th>Prix unitaire</th><th>Total</th></tr></thead><tbody>`;
        d.items.forEach(it => {
            itemsHtml += `<tr>
                <td>${escapeHtml(it.product_name || '')}</td>
                <td><code>${escapeHtml(it.product_sku || '')}</code></td>
                <td>${it.quantity}</td>
                <td>${parseFloat(it.unit_price || 0).toFixed(2)}</td>
                <td>${parseFloat(it.total_price || 0).toFixed(2)}</td>
            </tr>`;
        });
        itemsHtml += '</tbody></table>';
    }
    itemsHtml += '</div>';

    document.getElementById('detailBody').innerHTML =
        orderHtml + convHtml + evHtml + jobHtml + itemsHtml;
}

// ── Helpers
function formatDate(s) {
    if (!s) return '—';
    const d = new Date(s.replace(' ', 'T'));
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) +
           ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}
function formatFull(s) {
    if (!s) return '—';
    const d = new Date(s.replace(' ', 'T'));
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
           ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}
function escapeHtml(t) {
    if (t === null || t === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(t);
    return div.innerHTML;
}
</script>

</body>
</html>