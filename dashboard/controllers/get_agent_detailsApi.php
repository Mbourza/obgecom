<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

try {
    // Check if request method is GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Méthode non autorisée'
        ]);
        exit;
    }

    // Validate required parameter
    if (!isset($_GET['id']) || empty(trim($_GET['id']))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'L\'ID de l\'agent est requis'
        ]);
        exit;
    }

    // Check if password should be included (only for admin/editing purposes)
    $includePassword = isset($_GET['include_password']) && $_GET['include_password'] === 'true';

    // Sanitize and validate input data
    $agentId = trim($_GET['id']);

    // Validate agent ID is numeric
    if (!is_numeric($agentId) || intval($agentId) <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID d\'agent invalide'
        ]);
        exit;
    }

    $agentId = intval($agentId);

    // Build the base query
    $agentQuery = "SELECT 
                        id,
                        name,
                        email,
                        phone,
                        is_active,
                        created_at,
                        updated_at";
    
    // Include password only if explicitly requested
    if ($includePassword) {
        $agentQuery .= ", password";
    }
    
    $agentQuery .= " FROM agents WHERE id = ?";
    
    $agentResult = $db->getThisQuery($agentQuery, [$agentId]);
    
    // Check if agent exists
    if (empty($agentResult)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Agent non trouvé'
        ]);
        exit;
    }

    $agentData = $agentResult[0];

    // Get performance statistics from agent_confirmations
    $statsQuery = "SELECT 
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as confirmed_orders,
                        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as pending_orders,
                        SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as canceled_orders,
                        COALESCE(SUM(CASE WHEN status = 1 THEN amount ELSE 0 END), 0) as confirmed_amount,
                        COALESCE(SUM(amount), 0) as total_amount
                    FROM agent_confirmations 
                    WHERE agent_id = ?";
    
    $statsResult = $db->getThisQuery($statsQuery, [$agentId]);
    $stats = $statsResult[0] ?? [
        'total_orders' => 0, 
        'confirmed_orders' => 0, 
        'pending_orders' => 0, 
        'canceled_orders' => 0,
        'confirmed_amount' => 0,
        'total_amount' => 0
    ];

    // Calculate performance metrics
    $confirmationRate = $stats['total_orders'] > 0 ? 
        round(($stats['confirmed_orders'] / $stats['total_orders']) * 100, 2) : 0;
    
    $cancellationRate = $stats['total_orders'] > 0 ? 
        round(($stats['canceled_orders'] / $stats['total_orders']) * 100, 2) : 0;

    // Get average performance for comparison
    $avgQuery = "SELECT 
                    COALESCE(AVG(agent_stats.confirmation_rate), 0) as avg_confirmation_rate
                 FROM (
                    SELECT 
                        agent_id,
                        CASE 
                            WHEN COUNT(*) > 0 THEN (SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100
                            ELSE 0 
                        END as confirmation_rate
                    FROM agent_confirmations 
                    GROUP BY agent_id
                 ) as agent_stats";
    
    $avgResult = $db->getThisQuery($avgQuery, []);
    $avgConfirmationRate = $avgResult[0]['avg_confirmation_rate'] ?? 0;

    // Calculate performance percentage based on comparison to average
    $performancePercentage = $avgConfirmationRate > 0 ? 
        min(100, round(($confirmationRate / $avgConfirmationRate) * 100)) : $confirmationRate;

    // Get recent confirmations (orders from WooCommerce/YouCan)
    $confirmationsQuery = "SELECT 
                                id,
                                order_id,
                                amount,
                                status,
                                customer_name,
                                customer_phone,
                                created_at
                            FROM agent_confirmations
                            WHERE agent_id = ?
                            ORDER BY created_at DESC
                            LIMIT 10";
    
    $confirmationsResult = $db->getThisQuery($confirmationsQuery, [$agentId]);
    $confirmations = $confirmationsResult ?? [];

    // Format agent data with statistics
    $responseData = [
        'id' => $agentData['id'],
        'name' => $agentData['name'],
        'email' => $agentData['email'],
        'phone' => $agentData['phone'],
        'is_active' => $agentData['is_active'],
        'created_at' => $agentData['created_at'],
        'updated_at' => $agentData['updated_at'],
        'statistics' => [
            'total_orders' => intval($stats['total_orders']),
            'confirmed_orders' => intval($stats['confirmed_orders']),
            'pending_orders' => intval($stats['pending_orders']),
            'canceled_orders' => intval($stats['canceled_orders']),
            'confirmed_amount' => floatval($stats['confirmed_amount']),
            'total_amount' => floatval($stats['total_amount']),
            'confirmation_rate' => $confirmationRate,
            'cancellation_rate' => $cancellationRate,
            'performance_percentage' => $performancePercentage
        ]
    ];

    // Include password only if requested (typically for admin/editing purposes)
    if ($includePassword && isset($agentData['password'])) {
        $responseData['password'] = $agentData['password'];
    }

    // Format confirmations data with status descriptions
    $formattedConfirmations = [];
    
    $statusLabels = [
        0 => 'En attente',
        1 => 'Confirmé et expédié',
        2 => 'Annulé'
    ];

    if(!empty($confirmations)) : foreach ($confirmations as $confirmation) {
        $formattedConfirmations[] = [
            'id' => $confirmation['id'],
            'order_id' => $confirmation['order_id'],
            'amount' => floatval($confirmation['amount']),
            'status' => intval($confirmation['status']),
            'status_label' => $statusLabels[$confirmation['status']] ?? 'Inconnu',
            'customer_name' => $confirmation['customer_name'],
            'customer_phone' => $confirmation['customer_phone'],
            'created_at' => $confirmation['created_at']
        ];
    } endif;
    
    echo json_encode([
        'success' => true,
        'agent' => $responseData,
        'confirmations' => $formattedConfirmations,
        'message' => 'Détails de l\'agent récupérés avec succès'
    ]);

} catch (PDOException $e) {
    // Handle database specific errors
    error_log("Database error in get_agent_detailsApi.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données'
    ]);
    
} catch (Exception $e) {
    // Handle general errors
    error_log("Error in get_agent_detailsApi.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur'
    ]);
}
?>