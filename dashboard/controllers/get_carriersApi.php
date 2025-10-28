<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    
    // Get optional filters from query parameters with proper defaults
    $status_filter = null;
    if (isset($_GET['status']) && $_GET['status'] !== '') {
        if (in_array($_GET['status'], ['0', '1'])) {
            $status_filter = (int)$_GET['status'];
        } else {
            throw new Exception('Invalid status filter value. Must be 0 or 1.');
        }
    }
    
    $supports_tracking_filter = null;
    if (isset($_GET['supports_tracking']) && $_GET['supports_tracking'] !== '') {
        if (in_array($_GET['supports_tracking'], ['0', '1'])) {
            $supports_tracking_filter = (int)$_GET['supports_tracking'];
        } else {
            throw new Exception('Invalid supports_tracking filter value. Must be 0 or 1.');
        }
    }
    
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

    // First, let's do a simple query to see if we have data
    $simple_query = "SELECT COUNT(*) as total FROM shipping_companies";
    $simple_result = $db->getThisQuery($simple_query, []);
    $total_companies = $simple_result ? $simple_result[0]['total'] : 0;

    // Debug: Test basic query first
    $debug_query = "SELECT sc.id, sc.name, sc.is_active FROM shipping_companies sc WHERE 1=1";
    $debug_params = [];
    
    if ($status_filter !== null) {
        $debug_query .= " AND sc.is_active = ?";
        $debug_params[] = $status_filter;
    }
    
    $debug_query .= " LIMIT 5";
    $debug_result = $db->getThisQuery($debug_query, $debug_params);

    // Build the main query - simplified first
    $query = "SELECT 
                sc.id,
                sc.name,
                sc.api_url,
                sc.api_key,
                sc.api_secret,
                sc.supports_tracking,
                sc.tracking_url,
                sc.logo_url,
                sc.contact_email,
                sc.phone,
                sc.website,
                sc.is_active,
                sc.created_at,
                sc.updated_at
              FROM shipping_companies sc
              WHERE 1=1";

    $params = [];

    // Add filters to query
    if ($status_filter !== null) {
        $query .= " AND sc.is_active = ?";
        $params[] = $status_filter;
    }

    if ($supports_tracking_filter !== null) {
        $query .= " AND sc.supports_tracking = ?";
        $params[] = $supports_tracking_filter;
    }

    if ($search_term !== '') {
        $query .= " AND (sc.name LIKE ? OR sc.contact_email LIKE ? OR sc.website LIKE ?)";
        $search_param = '%' . $search_term . '%';
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }

    // Default ordering
    $query .= " ORDER BY sc.is_active DESC, sc.name ASC";

    // Add limit and offset
    $query .= " LIMIT ?";
    $params[] = $limit;
    
    if ($offset > 0) {
        $query .= " OFFSET ?";
        $params[] = $offset;
    }

    // Execute main query
    $shipping_companies = $db->getThisQuery($query, $params);

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total 
                   FROM shipping_companies sc 
                   WHERE 1=1";
    
    $count_params = [];
    
    // Apply same filters to count query
    if ($status_filter !== null) {
        $count_query .= " AND sc.is_active = ?";
        $count_params[] = $status_filter;
    }

    if ($supports_tracking_filter !== null) {
        $count_query .= " AND sc.supports_tracking = ?";
        $count_params[] = $supports_tracking_filter;
    }

    if ($search_term !== '') {
        $count_query .= " AND (sc.name LIKE ? OR sc.contact_email LIKE ? OR sc.website LIKE ?)";
        $search_param = '%' . $search_term . '%';
        $count_params[] = $search_param;
        $count_params[] = $search_param;
        $count_params[] = $search_param;
    }

    $count_result = $db->getThisQuery($count_query, $count_params);
    $total_count = $count_result ? (int)$count_result[0]['total'] : 0;

    // Process shipping companies data
    $processed_companies = [];
    if ($shipping_companies && is_array($shipping_companies)) {
        foreach ($shipping_companies as $company) {
            $processed_company = [
                'id' => (int)$company['id'],
                'name' => $company['name'] ?? '',
                'api_url' => $company['api_url'] ?? '',
                'api_key' => !empty($company['api_key']) ? substr($company['api_key'], 0, 8) . '...' : null,
                'has_api_secret' => !empty($company['api_secret']),
                'supports_tracking' => (int)($company['supports_tracking'] ?? 0),
                'tracking_url' => $company['tracking_url'] ?? '',
                'logo_url' => $company['logo_url'] ?? '',
                'contact_email' => $company['contact_email'] ?? '',
                'phone' => $company['phone'] ?? '',
                'website' => $company['website'] ?? '',
                'is_active' => (int)($company['is_active'] ?? 0),
                'auto_track_settings' => 0, // Simplified for now
                'total_orders' => 0, // Simplified for now
                'delivered_orders' => 0, // Simplified for now
                'recent_orders' => 0, // Simplified for now
                'avg_rating' => 0, // Simplified for now
                'created_at' => $company['created_at'] ?? null,
                'updated_at' => $company['updated_at'] ?? null
            ];

            // Add status display names
            $processed_company['status_name'] = ($company['is_active'] ?? 0) ? 'Actif' : 'Inactif';
            
            // Add tracking support display names
            $processed_company['tracking_support_name'] = ($company['supports_tracking'] ?? 0) ? 'Supporté' : 'Non supporté';

            $processed_companies[] = $processed_company;
        }
    }

    // Prepare response with debug info
    $response = [
        'success' => true,
        'data' => $processed_companies,
        'total_count' => $total_count,
        'debug_info' => [
            'total_companies_in_db' => $total_companies,
            'query_executed' => $query,
            'params_used' => $params,
            'debug_sample' => $debug_result,
            'raw_result_count' => $shipping_companies ? count($shipping_companies) : 0
        ],
        'filters_applied' => [
            'status' => $status_filter,
            'supports_tracking' => $supports_tracking_filter,
            'search' => $search_term,
            'limit' => $limit,
            'offset' => $offset
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("Carriers API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des transporteurs: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>