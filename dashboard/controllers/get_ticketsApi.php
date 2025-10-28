<?php

if(file_exists("../core/init.php")) {
    require_once("../core/init.php");
}

// Set headers for JSON API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get request parameters
$method = $_SERVER['REQUEST_METHOD'];
$request = $_GET['request'] ?? '';
$ticketId = $_GET['id'] ?? null;

// Initialize database connection
try {
    $db = DB::getInstance();
} catch (Exception $e) {
    respondWithError('Database connection failed', 500);
}

// Main request router
try {
    switch($method) {
        case 'GET':
            handleGetRequest($db, $request, $ticketId);
            break;
            
        case 'POST':
            handlePostRequest($db, $request);
            break;
            
        case 'PUT':
            handlePutRequest($db, $request, $ticketId);
            break;
            
        case 'DELETE':
            handleDeleteRequest($db, $ticketId);
            break;
            
        default:
            respondWithError('Method not allowed', 405);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    respondWithError('Internal server error: ' . $e->getMessage(), 500);
}

// GET request handler
function handleGetRequest($db, $request, $ticketId) {
    switch($request) {
        case 'all':
            getAllTickets($db);
            break;
            
        case 'stats':
            getTicketStats($db);
            break;
            
        default:
            if ($ticketId) {
                getTicketById($db, $ticketId);
            } else {
                getAllTickets($db);
            }
            break;
    }
}

// POST request handler
function handlePostRequest($db, $request) {

    switch($request) {
        case 'create':
            createTicket($db);
            break;
            
        case 'reply':
            addReply($db);
            break;
            
        default:
            respondWithError('Invalid POST request', 400);
    }
}

// PUT request handler
function handlePutRequest($db, $request, $ticketId) {
    if ($ticketId) {
        updateTicket($db, $ticketId);
    } else {
        respondWithError('Ticket ID required for update', 400);
    }
}

// DELETE request handler
function handleDeleteRequest($db, $ticketId) {
    if ($ticketId) {
        deleteTicket($db, $ticketId);
    } else {
        respondWithError('Ticket ID required for deletion', 400);
    }
}

// Get all tickets with enhanced filtering and sorting
function getAllTickets($db) {

    $userId = getCurrentUserId($db);
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    $status = $_GET['status'] ?? '';
    $priority = $_GET['priority'] ?? '';
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build WHERE clause
    $whereConditions = ["t.user_id = ?"];
    $params = [$userId];
    
    if ($status) {
        $whereConditions[] = "t.status = ?";
        $params[] = $status;
    }
    
    if ($priority) {
        $whereConditions[] = "t.priority = ?";
        $params[] = $priority;
    }
    
    if ($category) {
        $whereConditions[] = "t.category = ?";
        $params[] = $category;
    }
    
    if ($search) {
        $whereConditions[] = "(t.title LIKE ? OR t.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get tickets with reply count and last update
    $query = "SELECT t.*, 
                     COUNT(r.id) as reply_count,
                     COALESCE(MAX(r.created_at), t.created_at) as last_update,
                     u.username as user_name,
                     u.email as user_email
              FROM tickets t 
              LEFT JOIN ticket_replies r ON t.id = r.ticket_id 
              LEFT JOIN users u ON t.user_id = u.id
              WHERE $whereClause
              GROUP BY t.id 
              ORDER BY last_update DESC, t.created_at DESC
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $tickets = $db->getThisQuery($query, $params);
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(DISTINCT t.id) as total 
    FROM tickets t 
    WHERE $whereClause";

    // Safely get the result
    $countResult = $db->getThisQuery($countQuery, array_slice($params, 0, -2));
    $totalCount = 0;

    if ($countResult && is_array($countResult) && isset($countResult[0]['total'])) {
        $totalCount = intval($countResult[0]['total']);
    }

    
    // Format the data for frontend
    $formattedTickets = array_map(function($ticket) {
        return formatTicketData($ticket);
    }, $tickets);
    
    respondWithSuccess([
        'tickets' => $formattedTickets,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$totalCount,
            'pages' => ceil($totalCount / $limit)
        ]
    ]);
}

// Get single ticket by ID with full details
function getTicketById($db, $ticketId) {
    $userId = getCurrentUserId($db);
    
    // Get ticket details
    $ticketQuery = "SELECT t.*, u.username as user_name, u.email as user_email
                    FROM tickets t 
                    LEFT JOIN users u ON t.user_id = u.id
                    WHERE t.id = ? AND t.user_id = ?";
    
    $ticket = $db->getThisQuery($ticketQuery, [$ticketId, $userId]);
    
    if (!$ticket) {
        respondWithError('Ticket not found or access denied', 404);
    }
    
    // Get replies with author information
    $repliesQuery = "SELECT r.*,
                     CASE 
                         WHEN r.author_type = 'user' THEN u.username
                        WHEN r.author_type = 'admin' THEN a.username
                        ELSE 'Support Agent'
                    END as author_name,
                    CASE 
                         WHEN r.author_type = 'user' THEN u.email
                        WHEN r.author_type = 'admin' THEN a.email
                        ELSE 'support@company.com'
                    END as author_email
                FROM ticket_replies r
                LEFT JOIN users u ON r.author_id = u.id AND r.author_type = 'user'
                LEFT JOIN admin_users a ON r.author_id = a.id AND r.author_type = 'admin'
                WHERE r.ticket_id = ? 
                ORDER BY r.created_at ASC";
    
    $replies = $db->getThisQuery($repliesQuery, [$ticketId]);
    
    // Alternative approach - you could also use:
    if (!is_array($replies)) {
        $replies = [];
    }
    
    $formattedReplies = array_map(function($reply) {
        return [
            'id' => (int)$reply['id'],
            'author' => $reply['author_type'] === 'user' ? 'Vous' : $reply['author_name'],
            'author_type' => $reply['author_type'],
            'date' => formatDate($reply['created_at']),
            'content' => $reply['content'],
            'created_at' => $reply['created_at']
        ];
    }, $replies);
    
    $formattedTicket = formatTicketData($ticket);
    $formattedTicket['replies'] = $formattedReplies;
    
    respondWithSuccess($formattedTicket);
}

// Create new ticket
function createTicket($db) {
    $input = getJsonInput();
    
    // Validate required fields
    $requiredFields = ['title', 'category', 'priority', 'description'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            respondWithError("Field '$field' is required", 400);
        }
    }
    
    // Validate field values
    if (!in_array($input['priority'], ['low', 'medium', 'high'])) {
        respondWithError('Invalid priority value', 400);
    }
    
    $allowedCategories = ['technical', 'billing', 'account', 'order', 'delivery', 'return', 'other'];
    if (!in_array($input['category'], $allowedCategories)) {
        respondWithError('Invalid category value', 400);
    }
    
    $userId = getCurrentUserId($db);
    
    try {
        $db->beginTransaction();
        
        $db->insert("tickets", [
            "title" => trim($input['title']),
            "category" => $input['category'],
            "priority" => $input['priority'],
            "description" => trim($input['description']),
            "user_id" => $userId,
            "created_at" => date('Y-m-d H:i:s'),
            "updated_at" => date('Y-m-d H:i:s')
        ]);
        
        $ticketId = $db->getLastInsertId();
        
        // Log ticket creation
        logTicketActivity($db, $ticketId, 'created', 'Ticket created by user');
        
        $db->commit();
        
        // Return the new ticket
        getTicketById($db, $ticketId);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

// Add reply to ticket
function addReply($db) {
    $input = getJsonInput();
    
    if (!isset($input['ticket_id']) || !isset($input['content'])) {
        respondWithError('Ticket ID and content are required', 400);
    }
    
    $ticketId = (int)$input['ticket_id'];
    $content = trim($input['content']);
    
    if (empty($content)) {
        respondWithError('Reply content cannot be empty', 400);
    }
    
    $userId = getCurrentUserId($db);
    
    // Verify ticket ownership
    $ticketQuery = "SELECT id FROM tickets WHERE id = ? AND user_id = ?";
    $ticket = $db->getThisQuery($ticketQuery, [$ticketId, $userId]);
    
    if (!$ticket) {
        respondWithError('Ticket not found or access denied', 404);
    }
    
    try {
        $db->beginTransaction();
        
        // Insert reply
        $replyQuery = "INSERT INTO ticket_replies (ticket_id, content, author_type, author_id, created_at) 
                       VALUES (?, ?, 'user', ?, NOW())";
        
        $db->query($replyQuery, [$ticketId, $content, $userId]);
        
        // Update ticket status and timestamp
        $updateQuery = "UPDATE tickets SET status = 'pending', updated_at = NOW() WHERE id = ?";
        $db->query($updateQuery, [$ticketId]);
        
        // Log activity
        logTicketActivity($db, $ticketId, 'reply_added', 'User added reply');
        
        $db->commit();
        
        respondWithSuccess(['message' => 'Reply added successfully']);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

// Update ticket
function updateTicket($db, $ticketId) {
    $input = getJsonInput();
    $userId = getCurrentUserId($db);
    
    // Verify ticket ownership
    $ticketQuery = "SELECT id FROM tickets WHERE id = ? AND user_id = ?";
    $ticket = $db->getThisQuery($ticketQuery, [$ticketId, $userId]);
    
    if (!$ticket) {
        respondWithError('Ticket not found or access denied', 404);
    }
    
    $fields = [];
    $params = [];
    
    // Only allow certain fields to be updated by users
    $allowedFields = ['priority'];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $fields[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($fields)) {
        respondWithError('No valid fields to update', 400);
    }
    
    $fields[] = 'updated_at = NOW()';
    $params[] = $ticketId;
    
    try {
        $db->beginTransaction();
        
        $query = "UPDATE tickets SET " . implode(', ', $fields) . " WHERE id = ?";
        $db->query($query, $params);
        
        // Log activity
        logTicketActivity($db, $ticketId, 'updated', 'Ticket updated by user');
        
        $db->commit();
        
        respondWithSuccess(['message' => 'Ticket updated successfully']);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

// Get ticket statistics
function getTicketStats($db) {
    $userId = getCurrentUserId($db);
    
    $queries = [
        'total' => "SELECT COUNT(*) as count FROM tickets WHERE user_id = ?",
        'open' => "SELECT COUNT(*) as count FROM tickets WHERE user_id = ? AND status IN ('open', 'pending')",
        'resolved' => "SELECT COUNT(*) as count FROM tickets WHERE user_id = ? AND status = 'resolved'",
        'closed' => "SELECT COUNT(*) as count FROM tickets WHERE user_id = ? AND status = 'closed'"
    ];
    
    $stats = [];
    foreach ($queries as $key => $query) {
        $result = $db->getThisQuery($query, [$userId]);
        $stats[$key] = (int) ($result[0]['count'] ?? 0);
    }
    
    // Get average response time (in hours)
    $avgResponseQuery = "SELECT AVG(TIMESTAMPDIFF(HOUR, t.created_at, r.created_at)) as avg_hours
                         FROM tickets t
                         JOIN ticket_replies r ON t.id = r.ticket_id
                         WHERE t.user_id = ? AND r.author_type != 'user'
                         AND r.created_at = (
                             SELECT MIN(created_at) 
                             FROM ticket_replies 
                             WHERE ticket_id = t.id )";
    
    $avgResult = $db->getThisQuery($avgResponseQuery, [$userId]);
    $avgResponseTime = isset($avgResult[0]['avg_hours']) ? round($avgResult[0]['avg_hours']) : 24;;
    
    $stats['avg_response_time'] = $avgResponseTime;
    
    respondWithSuccess($stats);
}

// Delete ticket (soft delete)
function deleteTicket($db, $ticketId) {
    $userId = getCurrentUserId($db);
    
    // Verify ticket ownership
    $ticketQuery = "SELECT id FROM tickets WHERE id = ? AND user_id = ?";
    $ticket = $db->getThisQuery($ticketQuery, [$ticketId, $userId]);
    
    if (!$ticket) {
        respondWithError('Ticket not found or access denied', 404);
    }
    
    try {
        $db->beginTransaction();
        
        // Soft delete - update status to deleted
        $query = "UPDATE tickets SET status = 'deleted', updated_at = NOW() WHERE id = ?";
        $db->query($query, [$ticketId]);
        
        // Log activity
        logTicketActivity($db, $ticketId, 'deleted', 'Ticket deleted by user');
        
        $db->commit();
        
        respondWithSuccess(['message' => 'Ticket deleted successfully']);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

// Helper functions
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
}

function getJsonInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respondWithError('Invalid JSON input', 400);
    }
    return $input;
}

function formatTicketData($ticket) {
    // If $ticket is an array of rows, get the first one
    if (isset($ticket[0]) && is_array($ticket[0])) {
        $ticket = $ticket[0];
    }

    if (!is_array($ticket)) {
        return [];
    }

    return [
        'id' => isset($ticket['id']) ? (int)$ticket['id'] : 0,
        'title' => $ticket['title'] ?? '',
        'category' => $ticket['category'] ?? '',
        'priority' => $ticket['priority'] ?? '',
        'status' => $ticket['status'] ?? '',
        'created' => isset($ticket['created_at']) ? formatDate($ticket['created_at']) : '',
        'lastUpdate' => isset($ticket['updated_at']) ? formatDate($ticket['updated_at']) : '',
        'description' => $ticket['description'] ?? '',
        'replyCount' => isset($ticket['reply_count']) ? (int)$ticket['reply_count'] : 0,
        'user_name' => $ticket['user_name'] ?? '',
        'user_email' => $ticket['user_email'] ?? ''
    ];
}


function formatDate($dateString) {
    return date('d/m/Y H:i', strtotime($dateString));
}

function logTicketActivity($db, $ticketId, $action, $description) {
    $userId = getCurrentUserId($db);
    $query = "INSERT INTO ticket_activity_log (ticket_id, user_id, action, description, created_at) 
              VALUES (?, ?, ?, ?, NOW())";
    
    try {
        $db->query($query, [$ticketId, $userId, $action, $description]);
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log ticket activity: " . $e->getMessage());
    }
}

function respondWithSuccess($data) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('c')
    ]);
    exit();
}

function respondWithError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'timestamp' => date('c')
    ]);
    exit();
} ?>