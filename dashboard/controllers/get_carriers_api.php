<?php
// get_carriers.php - API to fetch all shipping companies
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connection
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Get current user ID
$user_id = getCurrentUserId($db);

// Check if user is logged in
if (!$user_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Authentication required'
    ]);
    exit;
}

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
    // Get optional filters from query parameters
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $supports_tracking_filter = isset($_GET['supports_tracking']) ? $_GET['supports_tracking'] : '';
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // Base query (no JOIN, only filter by user_id)
    $query = "SELECT 
                sc.id,
                sc.user_id,
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
              WHERE sc.user_id = ?";

    $params = [$user_id];

    // Add filters
    if ($status_filter !== '') {
        $query .= " AND sc.is_active = ?";
        $params[] = $status_filter;
    }

    if ($supports_tracking_filter !== '') {
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

    // Ordering
    $query .= " ORDER BY sc.name ASC";

    // Limit & offset
    if ($limit > 0) {
        $query .= " LIMIT ?";
        $params[] = $limit;
        
        if ($offset > 0) {
            $query .= " OFFSET ?";
            $params[] = $offset;
        }
    }

    // Execute query
    $shipping_companies = $db->getThisQuery($query, $params);

    // Count for pagination
    $total_count = 0;
    if ($limit > 0) {
        $count_query = "SELECT COUNT(*) as total 
                        FROM shipping_companies sc 
                        WHERE sc.user_id = ?";
        $count_params = [$user_id];

        if ($status_filter !== '') {
            $count_query .= " AND sc.is_active = ?";
            $count_params[] = $status_filter;
        }

        if ($supports_tracking_filter !== '') {
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
    }

    // Process companies (hide full api_key/api_secret for security)
    $processed_companies = [];
    foreach ($shipping_companies as $company) {
        $processed_companies[] = [
            'id' => (int)$company['id'],
            'user_id' => (int)$company['user_id'],
            'name' => $company['name'],
            'api_url' => $company['api_url'],
            'api_key' => $company['api_key'] ? substr($company['api_key'], 0, 10) . '...' : null,
            'api_secret' => $company['api_secret'] ? substr($company['api_secret'], 0, 10) . '...' : null,
            'supports_tracking' => (int)$company['supports_tracking'],
            'tracking_url' => $company['tracking_url'],
            'logo_url' => $company['logo_url'],
            'contact_email' => $company['contact_email'],
            'phone' => $company['phone'],
            'website' => $company['website'],
            'is_active' => (int)$company['is_active'],
            'created_at' => $company['created_at'],
            'updated_at' => $company['updated_at'],
            'status_name' => $company['is_active'] ? 'Active' : 'Inactive',
            'tracking_support_name' => $company['supports_tracking'] ? 'Supported' : 'Not Supported'
        ];
    }
 
    // Build response
    $response = [
        'success' => true,
        'data' => $processed_companies,
        'user_id' => $user_id
    ];

    if ($limit > 0) {
        $response['pagination'] = [
            'total' => $total_count,
            'limit' => $limit,
            'offset' => $offset,
            'total_pages' => $limit > 0 ? ceil($total_count / $limit) : 1,
            'current_page' => $limit > 0 ? floor($offset / $limit) + 1 : 1
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in get_carriers.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching shipping companies data'
    ]);
} 


function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    // This can be username, email, or phone
    $identifier = $_SESSION['user']['username'];

    // Prefer explicit ID if available
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Try to match identifier against username, email, or phone
    $user = $db->getThisQuery("
        SELECT id 
        FROM users 
        WHERE username = ? OR email = ? OR phone = ?
        LIMIT 1
    ", [$identifier, $identifier, $identifier]);

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

    return null;
} ?>