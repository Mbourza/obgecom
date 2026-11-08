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

    // Build the enhanced query with LEFT JOINs for responsable and team
    $agentQuery = "SELECT 
                        a.id,
                        a.name,
                        a.email,
                        a.phone,
                        a.is_active,
                        a.created_at,
                        a.updated_at,
                        a.service_fee,
                        a.confirmation_rate,
                        a.score,
                        a.total_payed,
                        a.total_earned,
                        a.team_id,
                        a.responsable_id,
                        -- Get responsable details
                        r.name as responsable_name,
                        r.phone as responsable_phone,
                        r.email as responsable_email,
                        -- Get team details
                        t.name as team_name,
                        t.description as team_description";
    
    // Include password only if explicitly requested
    if ($includePassword) {
        $agentQuery .= ", a.password";
    }
    
    $agentQuery .= " FROM agents a
                    LEFT JOIN responsables r ON a.responsable_id = r.id
                    LEFT JOIN teams t ON a.team_id = t.id
                    WHERE a.id = ?";
    
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
                        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_orders,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as canceled_orders,
                        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END), 0) as confirmed_amount,
                        COALESCE(SUM(amount), 0) as total_amount,
                        -- Monthly statistics
                        COUNT(CASE WHEN MONTH(created_at) = MONTH(CURRENT_DATE()) 
                                   AND YEAR(created_at) = YEAR(CURRENT_DATE()) 
                                   THEN 1 END) as monthly_orders,
                        COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(CURRENT_DATE()) 
                                          AND YEAR(created_at) = YEAR(CURRENT_DATE()) 
                                          AND status = 'confirmed' THEN amount ELSE 0 END), 0) as monthly_amount,
                        -- Last 30 days statistics
                        COUNT(CASE WHEN created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY) 
                                   THEN 1 END) as last_30_days_orders,
                        COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY) 
                                          AND status = 'confirmed' THEN amount ELSE 0 END), 0) as last_30_days_amount
                    FROM agent_confirmations 
                    WHERE agent_id = ?";
    
    $statsResult = $db->getThisQuery($statsQuery, [$agentId]);
    $stats = $statsResult[0] ?? [
        'total_orders' => 0, 
        'confirmed_orders' => 0, 
        'pending_orders' => 0, 
        'canceled_orders' => 0,
        'confirmed_amount' => 0,
        'total_amount' => 0,
        'monthly_orders' => 0,
        'monthly_amount' => 0,
        'last_30_days_orders' => 0,
        'last_30_days_amount' => 0
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
                            WHEN COUNT(*) > 0 THEN (SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) / COUNT(*)) * 100
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

    // Calculate monthly cost and ROI
    $monthlyCost = $stats['monthly_amount'] * ($agentData['service_fee'] / 100);
    $monthlyROI = $monthlyCost > 0 ? 
        round((($stats['monthly_amount'] - $monthlyCost) / $monthlyCost) * 100, 2) : 0;

    // Calculate monthly performance
    $monthlyPerformance = $stats['monthly_orders'] > 0 ? 
        round(($stats['monthly_orders'] * 100) / ($stats['monthly_orders'] + ($stats['monthly_orders'] * 0.2)), 1) : 0;

    // Get recent confirmations
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
        'is_active' => (bool)$agentData['is_active'],
        'created_at' => $agentData['created_at'],
        'updated_at' => $agentData['updated_at'],
        'service_fee' => floatval($agentData['service_fee']),
        'confirmation_rate' => floatval($agentData['confirmation_rate']),
        'score' => intval($agentData['score']),
        'total_payed' => floatval($agentData['total_payed']),
        'total_earned' => floatval($agentData['total_earned']),
        // Team information
        'team' => [
            'id' => $agentData['team_id'],
            'name' => $agentData['team_name'],
            'description' => $agentData['team_description']
        ],
        // Responsable information
        'responsable' => [
            'id' => $agentData['responsable_id'],
            'name' => $agentData['responsable_name'],
            'email' => $agentData['responsable_email'],
            'phone' => $agentData['responsable_phone']
        ],
        'statistics' => [
            // Order statistics
            'total_orders' => intval($stats['total_orders']),
            'confirmed_orders' => intval($stats['confirmed_orders']),
            'pending_orders' => intval($stats['pending_orders']),
            'canceled_orders' => intval($stats['canceled_orders']),
            'confirmed_amount' => floatval($stats['confirmed_amount']),
            'total_amount' => floatval($stats['total_amount']),
            
            // Monthly statistics
            'monthly_orders' => intval($stats['monthly_orders']),
            'monthly_amount' => floatval($stats['monthly_amount']),
            'monthly_cost' => floatval($monthlyCost),
            'monthly_roi' => floatval($monthlyROI),
            'monthly_performance' => floatval($monthlyPerformance),
            
            // Last 30 days statistics
            'last_30_days_orders' => intval($stats['last_30_days_orders']),
            'last_30_days_amount' => floatval($stats['last_30_days_amount']),
            
            // Performance metrics
            'confirmation_rate' => floatval($confirmationRate),
            'cancellation_rate' => floatval($cancellationRate),
            'performance_percentage' => floatval($performancePercentage)
        ]
    ];

    // Include password only if requested (typically for admin/editing purposes)
    if ($includePassword && isset($agentData['password'])) {
        $responseData['password'] = $agentData['password'];
    }

    // Format confirmations data with status descriptions
    $formattedConfirmations = [];
    
    $statusLabels = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmé',
        'cancelled' => 'Annulé',
        'refunded' => 'Remboursé',
        'failed' => 'Échoué'
    ];

    if(!empty($confirmations)) {
        foreach ($confirmations as $confirmation) {
            $formattedConfirmations[] = [
                'id' => $confirmation['id'],
                'order_id' => $confirmation['order_id'],
                'amount' => floatval($confirmation['amount']),
                'status' => $confirmation['status'],
                'status_label' => $statusLabels[$confirmation['status']] ?? 'Inconnu',
                'customer_name' => $confirmation['customer_name'],
                'customer_phone' => $confirmation['customer_phone'],
                'created_at' => $confirmation['created_at']
            ];
        }
    }
    
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
        'message' => 'Erreur de base de données: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // Handle general errors
    error_log("Error in get_agent_detailsApi.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur interne du serveur: ' . $e->getMessage()
    ]);
}
?>