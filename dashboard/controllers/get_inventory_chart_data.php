<?php if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Get user from session
$user = $db->getThisQuery(
    "SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1", 
    [$_SESSION['user']['username'], $_SESSION['user']['username'], $_SESSION['user']['username']]
);

if (!$user || !isset($user[0]['id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_id = (int)$user[0]['id'];
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

// Prepare data arrays
$labels = [];
$incomingData = [];
$outgoingData = [];
$currentData = [];

// Generate dates for the selected period
$endDate = new DateTime();
$startDate = new DateTime();
$startDate->modify("-{$days} days");

// Initialize all dates with 0 values
$currentDate = clone $startDate;
while ($currentDate <= $endDate) {
    $dateKey = $currentDate->format('Y-m-d');
    $labels[] = $currentDate->format('d/m');
    $incomingData[$dateKey] = 0;
    $outgoingData[$dateKey] = 0;
    
    // Get stock movements for this date
    $movements = $db->getThisQuery("
        SELECT 
            DATE(sm.created_at) as movement_date,
            sm.movement_type,
            SUM(sm.quantity) as total_quantity
        FROM stock_movements sm
        LEFT JOIN products p ON sm.product_id = p.id
        WHERE p.user_id = ?
        AND DATE(sm.created_at) = ?
        GROUP BY DATE(sm.created_at), sm.movement_type
    ", [$user_id, $dateKey]);
    
    foreach ($movements as $movement) {
        if ($movement['movement_type'] === 'in') {
            $incomingData[$dateKey] = (int)$movement['total_quantity'];
        } else if ($movement['movement_type'] === 'out') {
            $outgoingData[$dateKey] = (int)$movement['total_quantity'];
        }
    }
    
    $currentDate->modify('+1 day');
}

// Calculate cumulative current stock
$currentStock = 0;
$currentDate = clone $startDate;
while ($currentDate <= $endDate) {
    $dateKey = $currentDate->format('Y-m-d');
    
    // Get total stock at this date
    $stockStats = $db->getThisQuery("
        SELECT 
            SUM(
                CASE 
                    WHEN pv.id IS NOT NULL THEN COALESCE(pv.stock_quantity, 0)
                    ELSE COALESCE(p.stock_quantity, 0)
                END
            ) as total_quantity
        FROM products p
        LEFT JOIN product_variations pv ON p.id = pv.product_id
        WHERE p.user_id = ?
        AND DATE(p.created_at) <= ?
    ", [$user_id, $dateKey]);
    
    if (!empty($stockStats)) {
        $currentStock = (int)($stockStats[0]['total_quantity'] ?? 0);
    }
    
    // Adjust with incoming/outgoing for the day
    $currentStock += $incomingData[$dateKey] - $outgoingData[$dateKey];
    $currentData[] = max(0, $currentStock);
    
    $currentDate->modify('+1 day');
}

// Convert associative arrays to indexed arrays
$incoming = array_values($incomingData);
$outgoing = array_values($outgoingData);

// Return the data
echo json_encode([
    'success' => true,
    'data' => [
        'labels' => $labels,
        'incoming' => $incoming,
        'outgoing' => $outgoing,
        'current' => $currentData
    ]
]); ?>