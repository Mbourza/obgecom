<?php
if(file_exists(stream_resolve_include_path("../core/init.php"))) {
    require_once("../core/init.php");
}

$db = DB::getInstance();

// Check if responsable is logged in via session
if(!Session::exists(Config::get('session/session_name'))) {
    Redirect::to('../../login'); 
}

// Get responsable info from session
$responsable_id = isset($_SESSION['responsable_id']) ? $_SESSION['responsable_id'] : null;
$responsable_email = isset($_SESSION['responsable_email']) ? $_SESSION['responsable_email'] : null;

// If no session data, try to get it from username in session
if (!$responsable_id && isset($_SESSION['user']['username'])) {
    $responsable_email = $_SESSION['user']['username'];
    $responsable = $db->getThisQuery("SELECT * FROM responsables WHERE email = ?", [$responsable_email]);
    
    if ($responsable && !empty($responsable)) {
        $responsable = $responsable[0];
        $responsable_id = $responsable['id'];
        
        // Store in session for future use
        $_SESSION['responsable_id'] = $responsable_id;
        $_SESSION['responsable_email'] = $responsable_email;
        $_SESSION['responsable_name'] = $responsable['name'];
    }
}

// If still no responsable ID, redirect to login
if (!$responsable_id) {
    session_destroy();
    Redirect::to('../../login');
}

// Get responsable details
$responsable = $db->getThisQuery("SELECT * FROM responsables WHERE id = ?", [$responsable_id]);
if (empty($responsable)) {
    session_destroy();
    Redirect::to('../../login');
}

$responsable = $responsable[0];

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = handleAjaxRequest($db, $responsable_id, $_POST);
    echo json_encode($response);
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    Redirect::to('../../login.php');
}

/**
 * Main AJAX request handler
 */
function handleAjaxRequest($db, $responsable_id, $data) {
    try {
        $action = $data['action'] ?? '';
        
        switch ($action) {
            case 'get_planning_overview':
                return getPlanningOverview($db, $responsable_id, $data['date'] ?? date('Y-m-d'));
                
            case 'get_agent_schedule':
                return getAgentSchedule($db, $data['agent_id'] ?? 0, $data['start_date'] ?? '', $data['end_date'] ?? '');
                
            case 'get_available_agents':
                return getAvailableAgents($db, $responsable_id, $data['date'] ?? '', $data['time_slot'] ?? '');
                
            case 'get_orders_to_assign':
                return getOrdersToAssign($db, $responsable_id, $data['date'] ?? '', $data['status'] ?? 'pending');
                
            case 'create_schedule_slot':
                return createScheduleSlot($db, $data);
                
            case 'update_schedule_slot':
                return updateScheduleSlot($db, $data);
                
            case 'delete_schedule_slot':
                return deleteScheduleSlot($db, $data['slot_id'] ?? 0);
                
            case 'assign_order_to_agent':
                return assignOrderToAgent($db, $data);
                
            case 'reassign_order':
                return reassignOrder($db, $data);
                
            case 'get_schedule_stats':
                return getScheduleStats($db, $responsable_id, $data['date'] ?? date('Y-m-d'));
                
            case 'bulk_assign_orders':
                return bulkAssignOrders($db, $data);
                
            case 'generate_weekly_schedule':
                return generateWeeklySchedule($db, $responsable_id, $data);
                
            case 'export_schedule':
                return exportSchedule($db, $responsable_id, $data);
                
            case 'get_agent_details':
                return getAgentDetails($db, $data['agent_id'] ?? 0);
                
            case 'get_order_details':
                return getOrderDetails($db, $data['order_id'] ?? 0);
                
            default:
                return ['success' => false, 'message' => 'Action non reconnue'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Get planning overview for a specific date
 */
function getPlanningOverview($db, $responsable_id, $date = null) {
    if (!$date) $date = date('Y-m-d');
    
    try {
        // Get schedule for the day
        $schedule = $db->getThisQuery("
            SELECT 
                s.*,
                a.id as agent_id,
                a.name as agent_name,
                a.email as agent_email,
                a.phone as agent_phone,
                r.name as region_name,
                t.name as team_name,
                t.color as team_color,
                t.icon as team_icon,
                COUNT(aoa.id) as assigned_orders,
                COUNT(CASE WHEN aoa.status = 'completed' THEN aoa.id END) as completed_orders,
                CONCAT(s.start_time, ' - ', s.end_time) as time_range
            FROM schedule_slots s
            INNER JOIN agents a ON s.agent_id = a.id
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id 
                AND DATE(aoa.assigned_at) = ?
                AND aoa.status IN ('pending', 'in_progress', 'completed')
            WHERE a.responsable_id = ?
            AND s.slot_date = ?
            GROUP BY s.id, a.id, r.id, t.id
            ORDER BY s.start_time ASC, a.name ASC
        ", [$date, $responsable_id, $date]);
        
        // Get agents with availability status
        $agents = $db->getThisQuery("
            SELECT 
                a.*,
                r.name as region_name,
                t.name as team_name,
                t.color as team_color,
                t.icon as team_icon,
                COUNT(DISTINCT s.id) as scheduled_slots_today,
                GROUP_CONCAT(DISTINCT CONCAT(s.start_time, '-', s.end_time) ORDER BY s.start_time) as schedule_times,
                a.availability_status
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            LEFT JOIN schedule_slots s ON a.id = s.agent_id AND s.slot_date = ?
            WHERE a.responsable_id = ?
            AND a.is_active = 1
            GROUP BY a.id, r.id, t.id
            ORDER BY 
                FIELD(a.availability_status, 'available', 'busy', 'offline'),
                a.name ASC
        ", [$date, $responsable_id]);
        
        // Get orders to assign
        $orders = $db->getThisQuery("
            SELECT 
                o.*,
                s.storeName as store_name,
                s.address as store_address,
                s.phone as store_phone,
                r.name as region_name,
                c.name as customer_name,
                c.phone as customer_phone,
                TIME(o.created_at) as order_time,
                o.pickup_time,
                o.pickup_date,
                o.total_amount,
                o.priority,
                o.special_instructions,
                (
                    SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name, ' (', p.category, ')'))
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = o.id
                ) as items_summary,
                (
                    SELECT SUM(oi.quantity * p.estimated_time)
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = o.id
                ) as estimated_time_minutes
            FROM orders o
            LEFT JOIN stores s ON o.store_id = s.id
            LEFT JOIN regions r ON s.region_id = r.id
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.status IN ('pending', 'confirmed')
            AND o.id NOT IN (
                SELECT order_id 
                FROM agent_order_assignments 
                WHERE DATE(assigned_at) = ?
                AND status NOT IN ('cancelled', 'rejected')
            )
            AND (
                o.pickup_date = ? 
                OR (o.pickup_date IS NULL AND DATE(o.created_at) = ?)
            )
            ORDER BY 
                o.priority DESC,
                o.created_at ASC
            LIMIT 50
        ", [$date, $date, $date]);
        
        // Get schedule statistics
        $stats = $db->getThisQuery("
            SELECT 
                COUNT(DISTINCT s.id) as total_slots,
                COUNT(DISTINCT a.id) as agents_scheduled,
                COUNT(DISTINCT o.id) as orders_to_assign,
                COUNT(DISTINCT aoa.id) as orders_assigned,
                COUNT(DISTINCT CASE WHEN aoa.status = 'completed' THEN aoa.id END) as orders_completed,
                SUM(TIME_TO_SEC(TIMEDIFF(s.end_time, s.start_time))) / 3600 as total_hours_scheduled
            FROM schedule_slots s
            INNER JOIN agents a ON s.agent_id = a.id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id AND DATE(aoa.assigned_at) = ?
            LEFT JOIN orders o ON o.pickup_date = ? 
                AND o.status IN ('pending', 'confirmed')
                AND o.id NOT IN (
                    SELECT order_id 
                    FROM agent_order_assignments 
                    WHERE DATE(assigned_at) = ?
                    AND status NOT IN ('cancelled', 'rejected')
                )
            WHERE a.responsable_id = ?
            AND s.slot_date = ?
        ", [$date, $date, $date, $responsable_id, $date]);
        
        return [
            'success' => true,
            'date' => $date,
            'formatted_date' => date('d/m/Y', strtotime($date)),
            'day_name' => date('l', strtotime($date)),
            'schedule' => $schedule ?? [],
            'agents' => $agents ?? [],
            'orders' => $orders ?? [],
            'stats' => $stats[0] ?? [],
            'time_slots' => generateTimeSlots()
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Get agent schedule for a date range
 */
function getAgentSchedule($db, $agent_id, $start_date, $end_date) {
    if (!$start_date) $start_date = date('Y-m-d');
    if (!$end_date) $end_date = date('Y-m-d', strtotime('+7 days'));
    
    try {
        $schedule = $db->getThisQuery("
            SELECT 
                s.*,
                a.name as agent_name,
                COUNT(aoa.id) as assigned_orders,
                COUNT(CASE WHEN aoa.status = 'completed' THEN aoa.id END) as completed_orders,
                GROUP_CONCAT(DISTINCT o.order_number) as order_numbers,
                DAYNAME(s.slot_date) as day_name
            FROM schedule_slots s
            INNER JOIN agents a ON s.agent_id = a.id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id 
                AND DATE(aoa.assigned_at) = s.slot_date
                AND TIME(aoa.assigned_at) BETWEEN s.start_time AND s.end_time
            LEFT JOIN orders o ON aoa.order_id = o.id
            WHERE s.agent_id = ?
            AND s.slot_date BETWEEN ? AND ?
            GROUP BY s.id, a.id
            ORDER BY s.slot_date ASC, s.start_time ASC
        ", [$agent_id, $start_date, $end_date]);
        
        $agent = $db->getThisQuery("
            SELECT a.*, r.name as region_name, t.name as team_name, t.color as team_color
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            WHERE a.id = ?
        ", [$agent_id]);
        
        $formatted_schedule = [];
        foreach ($schedule as $slot) {
            $date = $slot['slot_date'];
            if (!isset($formatted_schedule[$date])) {
                $formatted_schedule[$date] = [
                    'date' => $date,
                    'day_name' => $slot['day_name'],
                    'slots' => []
                ];
            }
            $formatted_schedule[$date]['slots'][] = $slot;
        }
        
        return [
            'success' => true,
            'agent' => $agent[0] ?? null,
            'schedule' => $formatted_schedule,
            'date_range' => [
                'start' => $start_date,
                'end' => $end_date,
                'formatted' => date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date))
            ],
            'calendar_dates' => generateDateRange($start_date, $end_date)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Get available agents for a specific time slot
 */
function getAvailableAgents($db, $responsable_id, $date, $time_slot) {
    if (!$date) $date = date('Y-m-d');
    
    try {
        $time_parts = explode('-', $time_slot);
        $start_time = isset($time_parts[0]) ? trim($time_parts[0]) . ':00' : '09:00:00';
        $end_time = isset($time_parts[1]) ? trim($time_parts[1]) . ':00' : '10:00:00';
        
        $agents = $db->getThisQuery("
            SELECT 
                a.*,
                r.name as region_name,
                t.name as team_name,
                t.color as team_color,
                t.icon as team_icon,
                COUNT(DISTINCT aoa.id) as current_assignments,
                (
                    SELECT COUNT(*) 
                    FROM schedule_slots s2 
                    WHERE s2.agent_id = a.id 
                    AND s2.slot_date = ?
                    AND s2.start_time < ? 
                    AND s2.end_time > ?
                ) as is_busy,
                a.confirmation_rate,
                a.score,
                a.performance_level
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id 
                AND DATE(aoa.assigned_at) = ?
                AND aoa.status IN ('pending', 'in_progress')
            WHERE a.responsable_id = ?
            AND a.is_active = 1
            AND a.availability_status != 'offline'
            GROUP BY a.id, r.id, t.id
            ORDER BY a.performance_level DESC, a.score DESC
        ", [$date, $end_time, $start_time, $date, $responsable_id]);
        
        // Enhance agent data
        foreach ($agents as &$agent) {
            $agent['is_available'] = $agent['is_busy'] == 0 && $agent['current_assignments'] < 5;
            $agent['workload_percentage'] = min(100, ($agent['current_assignments'] / 5) * 100);
            $agent['performance_color'] = getPerformanceColor($agent['performance_level']);
            $agent['availability_icon'] = getAvailabilityIcon($agent['availability_status']);
        }
        
        return [
            'success' => true,
            'date' => $date,
            'time_slot' => $time_slot,
            'agents' => $agents,
            'total_available' => count(array_filter($agents, fn($a) => $a['is_available'])),
            'total_agents' => count($agents)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Get orders to assign for a specific date
 */
function getOrdersToAssign($db, $responsable_id, $date, $status = 'pending') {
    if (!$date) $date = date('Y-m-d');
    
    try {
        $orders = $db->getThisQuery("
            SELECT 
                o.*,
                s.storeName as store_name,
                s.address as store_address,
                s.phone as store_phone,
                s.latitude as store_lat,
                s.longitude as store_lng,
                r.name as region_name,
                c.name as customer_name,
                c.phone as customer_phone,
                c.address as customer_address,
                TIME(o.created_at) as order_time,
                o.pickup_time,
                o.pickup_date,
                o.total_amount,
                o.priority,
                o.special_instructions,
                o.delivery_type,
                (
                    SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.name, ' (', p.category, ')'))
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = o.id
                ) as items_summary,
                (
                    SELECT SUM(oi.quantity * p.estimated_time)
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = o.id
                ) as estimated_time_minutes
            FROM orders o
            LEFT JOIN stores s ON o.store_id = s.id
            LEFT JOIN regions r ON s.region_id = r.id
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.status IN ('pending', 'confirmed')
            AND o.id NOT IN (
                SELECT order_id 
                FROM agent_order_assignments 
                WHERE DATE(assigned_at) = ?
                AND status NOT IN ('cancelled', 'rejected')
            )
            AND (
                o.pickup_date = ? 
                OR (o.pickup_date IS NULL AND DATE(o.created_at) = ?)
            )
            ORDER BY 
                FIELD(o.priority, 'high', 'medium', 'low', 'normal'),
                o.created_at ASC
            LIMIT 100
        ", [$date, $date, $date]);
        
        // Enhance order data
        foreach ($orders as &$order) {
            $order['estimated_time'] = formatEstimatedTime($order['estimated_time_minutes'] ?? 30);
            $order['assignment_status'] = 'unassigned';
            $order['time_slot'] = $order['pickup_time'] ?: 'Flexible';
            $order['priority_color'] = getPriorityColor($order['priority']);
            $order['priority_icon'] = getPriorityIcon($order['priority']);
            $order['delivery_icon'] = getDeliveryIcon($order['delivery_type']);
            $order['urgency_level'] = calculateUrgency($order['created_at'], $order['priority']);
        }
        
        return [
            'success' => true,
            'date' => $date,
            'orders' => $orders,
            'total_orders' => count($orders),
            'categories' => [
                'high' => count(array_filter($orders, fn($o) => $o['priority'] === 'high')),
                'medium' => count(array_filter($orders, fn($o) => $o['priority'] === 'medium')),
                'low' => count(array_filter($orders, fn($o) => $o['priority'] === 'low'))
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Create a new schedule slot
 */
function createScheduleSlot($db, $data) {
    try {
        $agent_id = $data['agent_id'] ?? 0;
        $slot_date = $data['slot_date'] ?? date('Y-m-d');
        $start_time = $data['start_time'] ?? '09:00:00';
        $end_time = $data['end_time'] ?? '10:00:00';
        $slot_type = $data['slot_type'] ?? 'work';
        $notes = $data['notes'] ?? '';
        $recurring = $data['recurring'] ?? 'none';
        $recurring_end = $data['recurring_end'] ?? null;
        
        // Validate time
        if (strtotime($end_time) <= strtotime($start_time)) {
            return ['success' => false, 'message' => 'L\'heure de fin doit être après l\'heure de début'];
        }
        
        // Check if agent already has a schedule at this time
        $existing = $db->getThisQuery("
            SELECT id FROM schedule_slots 
            WHERE agent_id = ? 
            AND slot_date = ? 
            AND (
                (start_time < ? AND end_time > ?) OR
                (start_time >= ? AND start_time < ?)
            )
        ", [$agent_id, $slot_date, $end_time, $start_time, $start_time, $end_time]);
        
        if (!empty($existing)) {
            return ['success' => false, 'message' => 'L\'agent a déjà un créneau à cette période'];
        }
        
        // Handle recurring schedule
        $created_slots = [];
        $dates = [$slot_date];
        
        if ($recurring !== 'none' && $recurring_end) {
            $dates = generateRecurringDates($slot_date, $recurring_end, $recurring);
        }
        
        foreach ($dates as $date) {
            $db->getThisQuery("
                INSERT INTO schedule_slots 
                (agent_id, slot_date, start_time, end_time, slot_type, notes, recurring_pattern, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [$agent_id, $date, $start_time, $end_time, $slot_type, $notes, $recurring]);
            
            $slot_id = $db->lastInsertId();
            $created_slots[] = $slot_id;
        }
        
        return [
            'success' => true,
            'message' => count($created_slots) > 1 ? 
                count($created_slots) . ' créneaux ajoutés avec succès' : 
                'Créneau ajouté avec succès',
            'slot_ids' => $created_slots
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Update an existing schedule slot
 */
function updateScheduleSlot($db, $data) {
    try {
        $slot_id = $data['slot_id'] ?? 0;
        $updates = [];
        $params = [];
        
        $fields = ['agent_id', 'start_time', 'end_time', 'slot_type', 'notes'];
        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updates)) {
            return ['success' => false, 'message' => 'Aucune donnée à mettre à jour'];
        }
        
        $updates[] = 'updated_at = NOW()';
        $params[] = $slot_id;
        
        $query = "UPDATE schedule_slots SET " . implode(', ', $updates) . " WHERE id = ?";
        $db->getThisQuery($query, $params);
        
        return [
            'success' => true,
            'message' => 'Créneau mis à jour avec succès'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Delete a schedule slot
 */
function deleteScheduleSlot($db, $slot_id) {
    try {
        // Check if slot has assigned orders
        $check = $db->getThisQuery("
            SELECT COUNT(*) as assigned_orders
            FROM agent_order_assignments aoa
            INNER JOIN schedule_slots s ON aoa.agent_id = s.agent_id 
                AND DATE(aoa.assigned_at) = s.slot_date
                AND TIME(aoa.assigned_at) BETWEEN s.start_time AND s.end_time
            WHERE s.id = ?
            AND aoa.status IN ('pending', 'in_progress')
        ", [$slot_id]);
        
        if (!empty($check) && $check[0]['assigned_orders'] > 0) {
            return [
                'success' => false,
                'message' => 'Impossible de supprimer ce créneau car il contient des commandes assignées'
            ];
        }
        
        $db->getThisQuery("DELETE FROM schedule_slots WHERE id = ?", [$slot_id]);
        
        return [
            'success' => true,
            'message' => 'Créneau supprimé avec succès'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Assign order to agent
 */
function assignOrderToAgent($db, $data) {
    try {
        $order_id = $data['order_id'] ?? 0;
        $agent_id = $data['agent_id'] ?? 0;
        $assigned_at = $data['assigned_at'] ?? date('Y-m-d H:i:s');
        $notes = $data['notes'] ?? '';
        $estimated_duration = $data['estimated_duration'] ?? 30;
        
        // Check if order is already assigned for today
        $existing = $db->getThisQuery("
            SELECT id FROM agent_order_assignments 
            WHERE order_id = ? 
            AND DATE(assigned_at) = DATE(?)
            AND status NOT IN ('cancelled', 'rejected')
        ", [$order_id, $assigned_at]);
        
        if (!empty($existing)) {
            return ['success' => false, 'message' => 'Cette commande est déjà assignée aujourd\'hui'];
        }
        
        // Check if agent is available
        $agent_schedule = $db->getThisQuery("
            SELECT s.id 
            FROM schedule_slots s
            WHERE s.agent_id = ?
            AND s.slot_date = DATE(?)
            AND s.start_time <= TIME(?)
            AND s.end_time >= TIME(?)
        ", [$agent_id, $assigned_at, $assigned_at, $assigned_at]);
        
        if (empty($agent_schedule)) {
            return ['success' => false, 'message' => 'L\'agent n\'est pas disponible à cette heure'];
        }
        
        // Check agent's workload
        $workload = $db->getThisQuery("
            SELECT COUNT(*) as current_assignments
            FROM agent_order_assignments 
            WHERE agent_id = ?
            AND DATE(assigned_at) = DATE(?)
            AND status IN ('pending', 'in_progress')
        ", [$agent_id, $assigned_at]);
        
        $current_assignments = $workload[0]['current_assignments'] ?? 0;
        if ($current_assignments >= 5) {
            return ['success' => false, 'message' => 'L\'agent a atteint sa limite de commandes simultanées'];
        }
        
        // Assign order
        $db->getThisQuery("
            INSERT INTO agent_order_assignments 
            (order_id, agent_id, assigned_at, status, notes, estimated_duration, created_at, updated_at)
            VALUES (?, ?, ?, 'pending', ?, ?, NOW(), NOW())
        ", [$order_id, $agent_id, $assigned_at, $notes, $estimated_duration]);
        
        $assignment_id = $db->lastInsertId();
        
        // Update order status
        $db->getThisQuery("UPDATE orders SET status = 'assigned', updated_at = NOW() WHERE id = ?", [$order_id]);
        
        // Update agent workload
        $db->getThisQuery("
            UPDATE agents 
            SET current_workload = current_workload + 1,
                last_assignment = NOW()
            WHERE id = ?
        ", [$agent_id]);
        
        return [
            'success' => true,
            'message' => 'Commande assignée avec succès',
            'assignment_id' => $assignment_id
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Get schedule statistics
 */
function getScheduleStats($db, $responsable_id, $date) {
    if (!$date) $date = date('Y-m-d');
    
    try {
        $stats = $db->getThisQuery("
            SELECT 
                -- Schedule metrics
                COUNT(DISTINCT s.id) as total_slots,
                COUNT(DISTINCT a.id) as agents_scheduled,
                ROUND(COUNT(DISTINCT a.id) * 100.0 / (
                    SELECT COUNT(*) 
                    FROM agents 
                    WHERE responsable_id = ? 
                    AND is_active = 1
                ), 1) as schedule_coverage_percent,
                
                -- Time metrics
                ROUND(SUM(TIME_TO_SEC(TIMEDIFF(s.end_time, s.start_time))) / 3600, 1) as total_scheduled_hours,
                ROUND(AVG(TIME_TO_SEC(TIMEDIFF(s.end_time, s.start_time))) / 3600, 2) as avg_slot_hours,
                
                -- Assignment metrics
                COUNT(DISTINCT aoa.id) as total_assignments,
                COUNT(DISTINCT CASE WHEN aoa.status = 'pending' THEN aoa.id END) as pending_assignments,
                COUNT(DISTINCT CASE WHEN aoa.status = 'in_progress' THEN aoa.id END) as in_progress_assignments,
                COUNT(DISTINCT CASE WHEN aoa.status = 'completed' THEN aoa.id END) as completed_assignments,
                
                -- Efficiency metrics
                ROUND(
                    COUNT(DISTINCT aoa.id) * 100.0 / 
                    GREATEST(COUNT(DISTINCT s.id), 1), 
                    1
                ) as utilization_rate,
                
                -- Unassigned orders
                (
                    SELECT COUNT(*)
                    FROM orders o
                    WHERE o.pickup_date = ? 
                    AND o.status IN ('pending', 'confirmed')
                    AND o.id NOT IN (
                        SELECT order_id 
                        FROM agent_order_assignments 
                        WHERE DATE(assigned_at) = ?
                        AND status NOT IN ('cancelled', 'rejected')
                    )
                ) as unassigned_orders,
                
                -- Agent availability
                (
                    SELECT COUNT(*)
                    FROM agents
                    WHERE responsable_id = ?
                    AND is_active = 1
                    AND availability_status = 'available'
                ) as available_agents
                
            FROM schedule_slots s
            INNER JOIN agents a ON s.agent_id = a.id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id 
                AND DATE(aoa.assigned_at) = ?
            WHERE a.responsable_id = ?
            AND s.slot_date = ?
        ", [$responsable_id, $date, $date, $responsable_id, $date, $responsable_id, $date]);
        
        return [
            'success' => true,
            'date' => $date,
            'stats' => $stats[0] ?? []
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Bulk assign multiple orders
 */
function bulkAssignOrders($db, $data) {
    try {
        $orders = json_decode($data['orders'] ?? '[]', true);
        $agent_id = $data['agent_id'] ?? 0;
        $time_slot = $data['time_slot'] ?? '';
        $date = $data['date'] ?? date('Y-m-d');
        $distribution = $data['distribution'] ?? 'sequential';
        
        if (empty($orders)) {
            return ['success' => false, 'message' => 'Aucune commande sélectionnée'];
        }
        
        if (!$agent_id) {
            return ['success' => false, 'message' => 'Agent non spécifié'];
        }
        
        $time_parts = explode('-', $time_slot);
        $base_time = isset($time_parts[0]) ? trim($time_parts[0]) : '09:00';
        
        $success_count = 0;
        $failed_orders = [];
        
        foreach ($orders as $index => $order_id) {
            // Calculate assignment time based on distribution
            if ($distribution === 'staggered') {
                $minutes_offset = $index * 15; // 15 minutes apart
                $assigned_at = $date . ' ' . date('H:i:s', strtotime($base_time) + ($minutes_offset * 60));
            } else {
                $assigned_at = $date . ' ' . $base_time . ':00';
            }
            
            $assign_data = [
                'order_id' => $order_id,
                'agent_id' => $agent_id,
                'assigned_at' => $assigned_at
            ];
            
            $result = assignOrderToAgent($db, $assign_data);
            
            if ($result['success']) {
                $success_count++;
            } else {
                $failed_orders[] = [
                    'order_id' => $order_id,
                    'reason' => $result['message']
                ];
            }
        }
        
        return [
            'success' => true,
            'message' => "Assignation en masse terminée: {$success_count} réussies, " . count($failed_orders) . " échouées",
            'results' => [
                'success_count' => $success_count,
                'failed_count' => count($failed_orders),
                'failed_orders' => $failed_orders
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Generate weekly schedule
 */
function generateWeeklySchedule($db, $responsable_id, $data) {
    try {
        $start_date = $data['start_date'] ?? date('Y-m-d');
        $end_date = $data['end_date'] ?? date('Y-m-d', strtotime('+6 days'));
        $template = $data['template'] ?? 'standard';
        $agent_ids = json_decode($data['agent_ids'] ?? '[]', true);
        
        // Get agents
        $where_clause = "WHERE responsable_id = ? AND is_active = 1";
        $params = [$responsable_id];
        
        if (!empty($agent_ids)) {
            $placeholders = implode(',', array_fill(0, count($agent_ids), '?'));
            $where_clause .= " AND id IN ($placeholders)";
            $params = array_merge($params, $agent_ids);
        }
        
        $agents_query = "SELECT * FROM agents $where_clause ORDER BY region_id, team_id";
        $agents = $db->getThisQuery($agents_query, $params);
        
        // Define templates
        $templates = [
            'standard' => [
                'monday' => ['09:00-12:00', '14:00-18:00'],
                'tuesday' => ['09:00-12:00', '14:00-18:00'],
                'wednesday' => ['09:00-12:00', '14:00-18:00'],
                'thursday' => ['09:00-12:00', '14:00-18:00'],
                'friday' => ['09:00-12:00', '14:00-18:00'],
                'saturday' => ['10:00-13:00'],
                'sunday' => []
            ],
            'extended' => [
                'monday' => ['08:00-12:00', '13:00-19:00'],
                'tuesday' => ['08:00-12:00', '13:00-19:00'],
                'wednesday' => ['08:00-12:00', '13:00-19:00'],
                'thursday' => ['08:00-12:00', '13:00-19:00'],
                'friday' => ['08:00-12:00', '13:00-19:00'],
                'saturday' => ['09:00-14:00'],
                'sunday' => ['10:00-13:00']
            ],
            'flexible' => [
                'monday' => ['10:00-16:00'],
                'tuesday' => ['10:00-16:00'],
                'wednesday' => ['10:00-16:00'],
                'thursday' => ['10:00-16:00'],
                'friday' => ['10:00-16:00'],
                'saturday' => [],
                'sunday' => []
            ]
        ];
        
        $selected_template = $templates[$template] ?? $templates['standard'];
        $generated_slots = [];
        $dates = generateDateRange($start_date, $end_date);
        
        foreach ($dates as $date) {
            $day_name = strtolower(date('l', strtotime($date)));
            $time_slots = $selected_template[$day_name] ?? [];
            
            foreach ($agents as $agent) {
                foreach ($time_slots as $time_slot) {
                    $time_parts = explode('-', $time_slot);
                    $start_time = $time_parts[0] . ':00';
                    $end_time = $time_parts[1] . ':00';
                    
                    // Skip if slot exists
                    $existing = $db->getThisQuery("
                        SELECT id FROM schedule_slots 
                        WHERE agent_id = ? 
                        AND slot_date = ? 
                        AND start_time = ? 
                        AND end_time = ?
                    ", [$agent['id'], $date, $start_time, $end_time]);
                    
                    if (empty($existing)) {
                        $db->getThisQuery("
                            INSERT INTO schedule_slots 
                            (agent_id, slot_date, start_time, end_time, slot_type, created_at, updated_at)
                            VALUES (?, ?, ?, ?, 'work', NOW(), NOW())
                        ", [$agent['id'], $date, $start_time, $end_time]);
                        
                        $generated_slots[] = [
                            'agent_id' => $agent['id'],
                            'agent_name' => $agent['name'],
                            'date' => $date,
                            'time_slot' => $time_slot
                        ];
                    }
                }
            }
        }
        
        return [
            'success' => true,
            'message' => "Planning généré: " . count($generated_slots) . " créneaux créés",
            'generated_slots' => $generated_slots,
            'period' => [
                'start' => $start_date,
                'end' => $end_date,
                'template' => $template,
                'agents_count' => count($agents)
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Get agent details
 */
function getAgentDetails($db, $agent_id) {
    try {
        $agent = $db->getThisQuery("
            SELECT 
                a.*,
                r.name as region_name,
                t.name as team_name,
                t.color as team_color,
                t.icon as team_icon,
                (
                    SELECT COUNT(*)
                    FROM agent_order_assignments
                    WHERE agent_id = a.id
                    AND DATE(assigned_at) = CURDATE()
                ) as today_assignments,
                (
                    SELECT COUNT(*)
                    FROM agent_order_assignments
                    WHERE agent_id = a.id
                    AND status = 'completed'
                    AND DATE(assigned_at) = CURDATE()
                ) as today_completed,
                (
                    SELECT AVG(rating)
                    FROM order_feedback
                    WHERE order_id IN (
                        SELECT order_id 
                        FROM agent_order_assignments 
                        WHERE agent_id = a.id
                        AND status = 'completed'
                    )
                ) as avg_rating
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            WHERE a.id = ?
        ", [$agent_id]);
        
        if (empty($agent)) {
            return ['success' => false, 'message' => 'Agent non trouvé'];
        }
        
        return [
            'success' => true,
            'agent' => $agent[0]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Get order details
 */
function getOrderDetails($db, $order_id) {
    try {
        $order = $db->getThisQuery("
            SELECT 
                o.*,
                s.storeName as store_name,
                s.address as store_address,
                s.phone as store_phone,
                s.latitude as store_lat,
                s.longitude as store_lng,
                r.name as region_name,
                c.name as customer_name,
                c.phone as customer_phone,
                c.address as customer_address,
                c.latitude as customer_lat,
                c.longitude as customer_lng,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(oi.quantity, 'x ', p.name, 
                               ' (', p.category, ') - ', 
                               FORMAT(p.price, 2), ' MAD') 
                        SEPARATOR '\\n'
                    )
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = o.id
                ) as items_details,
                (
                    SELECT SUM(oi.quantity * p.price)
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = o.id
                ) as calculated_total,
                (
                    SELECT SUM(oi.quantity * p.estimated_time)
                    FROM order_items oi
                    LEFT JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = o.id
                ) as total_estimated_time
            FROM orders o
            LEFT JOIN stores s ON o.store_id = s.id
            LEFT JOIN regions r ON s.region_id = r.id
            LEFT JOIN customers c ON o.customer_id = c.id
            WHERE o.id = ?
        ", [$order_id]);
        
        if (empty($order)) {
            return ['success' => false, 'message' => 'Commande non trouvée'];
        }
        
        return [
            'success' => true,
            'order' => $order[0]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Export schedule data
 */
function exportSchedule($db, $responsable_id, $data) {
    try {
        $start_date = $data['start_date'] ?? date('Y-m-d');
        $end_date = $data['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $format = $data['format'] ?? 'csv';
        
        $schedule = $db->getThisQuery("
            SELECT 
                s.slot_date,
                s.start_time,
                s.end_time,
                a.name as agent_name,
                a.email as agent_email,
                a.phone as agent_phone,
                r.name as region_name,
                t.name as team_name,
                s.slot_type,
                s.notes,
                COUNT(aoa.id) as assigned_orders,
                GROUP_CONCAT(DISTINCT o.order_number) as order_numbers
            FROM schedule_slots s
            INNER JOIN agents a ON s.agent_id = a.id
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id 
                AND DATE(aoa.assigned_at) = s.slot_date
                AND TIME(aoa.assigned_at) BETWEEN s.start_time AND s.end_time
            LEFT JOIN orders o ON aoa.order_id = o.id
            WHERE a.responsable_id = ?
            AND s.slot_date BETWEEN ? AND ?
            GROUP BY s.id, a.id, r.id, t.id
            ORDER BY s.slot_date ASC, s.start_time ASC, a.name ASC
        ", [$responsable_id, $start_date, $end_date]);
        
        return [
            'success' => true,
            'filename' => "planning_" . date('Y-m-d') . "_" . $start_date . "_to_" . $end_date . "." . $format,
            'schedule' => $schedule,
            'total_slots' => count($schedule),
            'period' => [
                'start' => $start_date,
                'end' => $end_date,
                'formatted' => date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date))
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
    }
}

/**
 * Helper function: Generate time slots
 */
function generateTimeSlots() {
    $slots = [];
    $start = strtotime('08:00');
    $end = strtotime('20:00');
    
    for ($time = $start; $time < $end; $time += 1800) {
        $start_time = date('H:i', $time);
        $end_time = date('H:i', $time + 1800);
        $slots[] = [
            'start' => $start_time,
            'end' => $end_time,
            'label' => "$start_time - $end_time",
            'hour' => date('H', $time)
        ];
    }
    
    return $slots;
}

/**
 * Helper function: Generate date range
 */
function generateDateRange($start_date, $end_date) {
    $dates = [];
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while ($current <= $end) {
        $dates[] = date('Y-m-d', $current);
        $current = strtotime('+1 day', $current);
    }
    
    return $dates;
}

/**
 * Helper function: Generate recurring dates
 */
function generateRecurringDates($start_date, $end_date, $pattern) {
    $dates = [];
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while ($current <= $end) {
        $dates[] = date('Y-m-d', $current);
        
        switch ($pattern) {
            case 'daily':
                $current = strtotime('+1 day', $current);
                break;
            case 'weekly':
                $current = strtotime('+1 week', $current);
                break;
            case 'weekdays':
                $current = strtotime('+1 day', $current);
                // Skip weekends
                while (in_array(date('N', $current), [6, 7])) {
                    $current = strtotime('+1 day', $current);
                }
                break;
            default:
                $current = $end + 1; // Exit loop
        }
    }
    
    return $dates;
}

/**
 * Helper function: Get performance color
 */
function getPerformanceColor($level) {
    return match($level) {
        'excellent' => '#10b981',
        'good' => '#3b82f6',
        'average' => '#f59e0b',
        'needs_improvement' => '#ef4444',
        default => '#64748b'
    };
}

/**
 * Helper function: Get availability icon
 */
function getAvailabilityIcon($status) {
    return match($status) {
        'available' => 'fas fa-circle-check',
        'busy' => 'fas fa-clock',
        'offline' => 'fas fa-circle',
        default => 'fas fa-question-circle'
    };
}

/**
 * Helper function: Get priority color
 */
function getPriorityColor($priority) {
    return match($priority) {
        'high' => '#ef4444',
        'medium' => '#f59e0b',
        'low' => '#10b981',
        default => '#64748b'
    };
}

/**
 * Helper function: Get priority icon
 */
function getPriorityIcon($priority) {
    return match($priority) {
        'high' => 'fas fa-exclamation-triangle',
        'medium' => 'fas fa-exclamation-circle',
        'low' => 'fas fa-info-circle',
        default => 'fas fa-flag'
    };
}

/**
 * Helper function: Get delivery icon
 */
function getDeliveryIcon($type) {
    return match($type) {
        'express' => 'fas fa-bolt',
        'standard' => 'fas fa-truck',
        'pickup' => 'fas fa-store',
        default => 'fas fa-box'
    };
}

/**
 * Helper function: Format estimated time
 */
function formatEstimatedTime($minutes) {
    if ($minutes < 60) {
        return "{$minutes} min";
    }
    
    $hours = floor($minutes / 60);
    $remaining = $minutes % 60;
    
    if ($remaining === 0) {
        return "{$hours}h";
    }
    
    return "{$hours}h {$remaining}min";
}

/**
 * Helper function: Calculate urgency
 */
function calculateUrgency($created_at, $priority) {
    $now = time();
    $created = strtotime($created_at);
    $hours_passed = ($now - $created) / 3600;
    
    $urgency_scores = [
        'high' => 100 - ($hours_passed * 20),
        'medium' => 80 - ($hours_passed * 15),
        'low' => 60 - ($hours_passed * 10)
    ];
    
    $score = $urgency_scores[$priority] ?? 50;
    return max(0, min(100, $score));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning & Assignation | Dashboard Responsable</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --secondary: #10b981;
            --secondary-dark: #059669;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }

        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--dark) 0%, #0f172a 100%);
            color: white;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 700;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .user-profile {
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 50%;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 600;
            color: white;
            border: 4px solid rgba(255, 255, 255, 0.2);
        }

        .user-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .user-role {
            font-size: 14px;
            color: var(--gray-400);
        }

        .nav-menu {
            flex: 1;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 24px;
            color: var(--gray-400);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }

        .nav-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-item.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--primary);
        }

        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar-footer {
            padding: 20px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            overflow-y: auto;
            background: var(--light);
        }

        /* Header */
        .main-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid var(--gray-200);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-subtitle {
            color: var(--gray-500);
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            padding: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 24px;
        }

        .stat-icon.primary { background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%); color: white; }
        .stat-icon.success { background: linear-gradient(135deg, #34d399 0%, var(--secondary) 100%); color: white; }
        .stat-icon.warning { background: linear-gradient(135deg, #fbbf24 0%, var(--warning) 100%); color: white; }
        .stat-icon.danger { background: linear-gradient(135deg, #f87171 0%, var(--danger) 100%); color: white; }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            margin-top: 8px;
        }

        .trend-up { color: var(--secondary); }
        .trend-down { color: var(--danger); }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            padding: 0 30px 30px;
        }

        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Schedule Timeline */
        .schedule-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
        }

        .section-header {
            padding: 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Timeline */
        .timeline {
            padding: 24px;
        }

        .timeline-hour {
            display: grid;
            grid-template-columns: 80px 1fr;
            gap: 20px;
            margin-bottom: 20px;
            align-items: start;
        }

        .hour-label {
            text-align: right;
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
            padding-top: 8px;
        }

        .time-slots {
            position: relative;
            height: 60px;
            background: var(--gray-100);
            border-radius: 8px;
            overflow: hidden;
        }

        .schedule-slot {
            position: absolute;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 6px;
            padding: 8px 12px;
            color: white;
            font-size: 12px;
            font-weight: 500;
            overflow: hidden;
            cursor: pointer;
            transition: var(--transition);
            border-left: 4px solid rgba(255, 255, 255, 0.3);
        }

        .schedule-slot:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .slot-agent {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .slot-info {
            font-size: 10px;
            opacity: 0.9;
        }

        /* Orders Panel */
        .orders-panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            height: fit-content;
        }

        .orders-list {
            max-height: 600px;
            overflow-y: auto;
            padding: 0 24px 24px;
        }

        .order-item {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 16px;
            margin-bottom: 12px;
            transition: var(--transition);
            cursor: pointer;
        }

        .order-item:hover {
            border-color: var(--primary);
            transform: translateX(4px);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .order-number {
            font-weight: 600;
            color: var(--dark);
        }

        .order-priority {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-high { background: #fee2e2; color: var(--danger); }
        .priority-medium { background: #fef3c7; color: var(--warning); }
        .priority-low { background: #d1fae5; color: var(--secondary); }

        .order-details {
            font-size: 13px;
            color: var(--gray-600);
            margin-bottom: 12px;
        }

        .order-actions {
            display: flex;
            gap: 8px;
        }

        /* Agent Cards */
        .agents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            padding: 24px;
        }

        .agent-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            cursor: pointer;
            border: 2px solid transparent;
        }

        .agent-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
        }

        .agent-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
        }

        .agent-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .agent-team {
            font-size: 12px;
            color: var(--gray-500);
            margin-bottom: 8px;
        }

        .agent-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-available { background: #d1fae5; color: var(--secondary); }
        .status-busy { background: #fef3c7; color: var(--warning); }
        .status-offline { background: #f1f5f9; color: var(--gray-500); }

        /* Calendar */
        .calendar-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin: 0 30px 30px;
            padding: 24px;
        }

        #calendar {
            height: 600px;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--secondary) 0%, var(--secondary-dark) 100%);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            padding: 0;
            justify-content: center;
            border-radius: 10px;
        }

        /* Date Navigation */
        .date-navigation {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 0 30px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--box-shadow);
        }

        .date-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .date-display {
            font-size: 18px;
            font-weight: 600;
            min-width: 200px;
            text-align: center;
        }

        .view-switcher {
            display: flex;
            background: var(--gray-100);
            border-radius: 8px;
            padding: 4px;
        }

        .view-btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--gray-600);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .view-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Modals */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            padding: 24px;
            border: none;
        }

        .modal-title {
            font-weight: 600;
            font-size: 20px;
        }

        /* Loading */
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 9999;
        }

        .toast {
            background: white;
            border-radius: var(--border-radius);
            padding: 16px 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .app-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }

            .date-navigation {
                flex-direction: column;
                gap: 20px;
                margin: 20px;
            }

            .header-actions {
                flex-direction: column;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <span>Planify</span>
                </div>
            </div>

            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($responsable['name'], 0, 1)); ?>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($responsable['name']); ?></div>
                <div class="user-role">Responsable d'Agents</div>
            </div>

            <nav class="nav-menu">
                <a href="./home" class="nav-item">
                    <i class="nav-icon fas fa-users"></i>
                    <span>Mes Agents</span>
                </a>
                <a href="./performance" class="nav-item">
                    <i class="nav-icon fas fa-chart-line"></i>
                    <span>Performance</span>
                </a>
                <a href="./planning" class="nav-item active">
                    <i class="nav-icon fas fa-calendar-alt"></i>
                    <span>Planning</span>
                </a>
                
                <a href="./settings" class="nav-item">
                    <i class="nav-icon fas fa-cog"></i>
                    <span>Paramètres</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <a href="?logout" class="nav-item">
                    <i class="nav-icon fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="header-title">
                                <h1>Planning & Assignation</h1>
                                <div class="header-subtitle">
                                    <i class="fas fa-calendar-alt me-2"></i>
                                    Gestion intelligente des plannings et assignation des commandes
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="header-actions text-end">
                                <button class="btn btn-primary" onclick="showGenerateScheduleModal()">
                                    <i class="fas fa-magic me-2"></i>Générer Planning
                                </button>
                                <button class="btn btn-success" onclick="showAddSlotModal()">
                                    <i class="fas fa-plus me-2"></i>Nouveau Créneau
                                </button>
                                <button class="btn btn-outline" onclick="exportSchedule()">
                                    <i class="fas fa-download me-2"></i>Exporter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Date Navigation -->
            <div class="date-navigation">
                <div class="date-controls">
                    <button class="btn btn-icon" onclick="previousDay()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    
                    <div class="date-display" id="current-date">
                        <?php echo date('l, d F Y'); ?>
                    </div>
                    
                    <button class="btn btn-icon" onclick="nextDay()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <button class="btn btn-primary" onclick="goToToday()">
                        <i class="fas fa-calendar-day me-2"></i>Aujourd'hui
                    </button>
                    
                    <input type="date" id="date-picker" class="form-control" 
                           style="width: 180px;" onchange="changeDate(this.value)">
                </div>
                
                <div class="view-switcher">
                    <button class="view-btn active" onclick="changeView('day')">
                        Jour
                    </button>
                    <button class="view-btn" onclick="changeView('week')">
                        Semaine
                    </button>
                    <button class="view-btn" onclick="changeView('month')">
                        Mois
                    </button>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid" id="planning-stats">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value" id="stats-agents">0</div>
                    <div class="stat-label">Agents Programmés</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span>12% vs hier</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value" id="stats-slots">0</div>
                    <div class="stat-label">Créneaux Planifiés</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span>8% vs hier</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-value" id="stats-orders">0</div>
                    <div class="stat-label">Commandes à Assigner</div>
                    <div class="stat-trend trend-down">
                        <i class="fas fa-arrow-down"></i>
                        <span>15% vs hier</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value" id="stats-assigned">0</div>
                    <div class="stat-label">Commandes Assignées</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span>22% vs hier</span>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard -->
            <div class="dashboard-grid">
                <!-- Schedule Timeline -->
                <div class="schedule-container">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-timeline"></i>
                            Planning du Jour
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-outline" onclick="loadPlanningData()">
                                <i class="fas fa-redo"></i>
                            </button>
                            <button class="btn btn-primary" onclick="showAddSlotModal()">
                                <i class="fas fa-plus me-2"></i>Ajouter Créneau
                            </button>
                        </div>
                    </div>
                    
                    <div class="timeline" id="timeline-container">
                        <!-- Timeline will be populated by JavaScript -->
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Chargement du planning...</p>
                        </div>
                    </div>
                </div>

                <!-- Orders & Agents -->
                <div class="orders-panel">
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-clipboard-list"></i>
                            Commandes à Assigner
                            <span class="badge bg-primary ms-2" id="orders-count">0</span>
                        </div>
                        <button class="btn btn-sm btn-outline" onclick="loadOrders()">
                            <i class="fas fa-redo"></i>
                        </button>
                    </div>
                    
                    <div class="orders-list" id="orders-list">
                        <!-- Orders will be populated by JavaScript -->
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Chargement des commandes...</p>
                        </div>
                    </div>
                    
                    <div class="section-header">
                        <div class="section-title">
                            <i class="fas fa-user-group"></i>
                            Agents Disponibles
                        </div>
                    </div>
                    
                    <div class="agents-grid" id="agents-grid">
                        <!-- Agents will be populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Calendar View -->
            <div class="calendar-container">
                <div class="section-header">
                    <div class="section-title">
                        <i class="fas fa-calendar"></i>
                        Vue Calendrier
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-outline" onclick="calendar.prev()">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="btn btn-outline" onclick="calendar.today()">
                            Aujourd'hui
                        </button>
                        <button class="btn btn-outline" onclick="calendar.next()">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div id="calendar"></div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="addSlotModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <!-- Header with gradient -->
                <div class="modal-header bg-gradient-primary text-white border-0 position-relative overflow-hidden">
                    <div class="position-absolute top-0 start-0 w-100 h-100 opacity-10">
                        <div class="animated-bg"></div>
                    </div>
                    <div class="position-relative">
                        <h5 class="modal-title d-flex align-items-center mb-0">
                            <span class="icon-circle me-3">
                                <i class="fas fa-calendar-plus"></i>
                            </span>
                            <span>Nouveau Créneau de Planning</span>
                        </h5>
                        <p class="text-white-50 mb-0 mt-1 small">Planifiez un nouveau créneau horaire</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white position-relative" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>

                <!-- Body with enhanced form -->
                <div class="modal-body p-4">
                    <form id="slotForm">
                        <div class="row g-4">
                            <!-- Agent Selection -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark mb-2">
                                    <i class="fas fa-user-tie text-primary me-2"></i>Agent
                                </label>
                                <div class="input-group-custom">
                                    <select class="form-control form-control-lg border-2 rounded-3" id="slot-agent" required>
                                        <option value="">Sélectionner un agent</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Date Selection -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark mb-2">
                                    <i class="fas fa-calendar text-primary me-2"></i>Date
                                </label>
                                <div class="input-group-custom">
                                    <input type="date" class="form-control form-control-lg border-2 rounded-3" id="slot-date" required>
                                </div>
                            </div>

                            <!-- Time Range -->
                            <div class="col-12">
                                <div class="time-range-container p-3 bg-light rounded-3 border">
                                    <label class="form-label fw-semibold text-dark mb-3 d-block">
                                        <i class="fas fa-clock text-primary me-2"></i>Horaires
                                    </label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small mb-2">Heure de début</label>
                                            <input type="time" class="form-control form-control-lg border-2 rounded-3" id="slot-start-time" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted small mb-2">Heure de fin</label>
                                            <input type="time" class="form-control form-control-lg border-2 rounded-3" id="slot-end-time" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Type Selection with Icons -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark mb-3">
                                    <i class="fas fa-tag text-primary me-2"></i>Type de créneau
                                </label>
                                <div class="row g-3">
                                    <div class="col-6 col-md-3">
                                        <input type="radio" class="btn-check" name="slot-type" id="type-work" value="work" checked>
                                        <label class="btn btn-outline-primary w-100 py-3 rounded-3" for="type-work">
                                            <i class="fas fa-briefcase d-block mb-2 fs-4"></i>
                                            <span class="d-block small">Travail</span>
                                        </label>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <input type="radio" class="btn-check" name="slot-type" id="type-break" value="break">
                                        <label class="btn btn-outline-success w-100 py-3 rounded-3" for="type-break">
                                            <i class="fas fa-coffee d-block mb-2 fs-4"></i>
                                            <span class="d-block small">Pause</span>
                                        </label>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <input type="radio" class="btn-check" name="slot-type" id="type-meeting" value="meeting">
                                        <label class="btn btn-outline-info w-100 py-3 rounded-3" for="type-meeting">
                                            <i class="fas fa-users d-block mb-2 fs-4"></i>
                                            <span class="d-block small">Réunion</span>
                                        </label>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <input type="radio" class="btn-check" name="slot-type" id="type-training" value="training">
                                        <label class="btn btn-outline-warning w-100 py-3 rounded-3" for="type-training">
                                            <i class="fas fa-graduation-cap d-block mb-2 fs-4"></i>
                                            <span class="d-block small">Formation</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Recurrence Settings -->
                            <div class="col-12">
                                <div class="recurring-container p-3 bg-light rounded-3 border">
                                    <label class="form-label fw-semibold text-dark mb-3 d-block">
                                        <i class="fas fa-redo-alt text-primary me-2"></i>Récurrence
                                    </label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <select class="form-control form-control-lg border-2 rounded-3" id="slot-recurring">
                                                <option value="none">Aucune récurrence</option>
                                                <option value="daily">Tous les jours</option>
                                                <option value="weekdays">Jours de semaine (Lun-Ven)</option>
                                                <option value="weekly">Toutes les semaines</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <input type="date" class="form-control form-control-lg border-2 rounded-3" id="slot-recurring-end" placeholder="Date de fin">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notes -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark mb-2">
                                    <i class="fas fa-sticky-note text-primary me-2"></i>Notes
                                    <span class="text-muted small ms-2">(optionnel)</span>
                                </label>
                                <textarea class="form-control border-2 rounded-3" id="slot-notes" rows="3" placeholder="Ajoutez des notes ou des détails supplémentaires..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Footer with enhanced buttons -->
                <div class="modal-footer border-0 bg-light p-4">
                    <button type="button" class="btn btn-lg btn-light px-4 rounded-pill" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="button" class="btn btn-lg btn-primary px-4 rounded-pill shadow-sm" onclick="saveScheduleSlot()">
                        <i class="fas fa-save me-2"></i>Enregistrer le créneau
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Modal Enhancements */
        .modal-content {
            border-radius: 20px !important;
            overflow: hidden;
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }

        .icon-circle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            backdrop-filter: blur(10px);
        }

        .icon-circle i {
            font-size: 1.2rem;
        }

        /* Animated Background */
        .animated-bg {
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255, 255, 255, 0.05) 10px,
                rgba(255, 255, 255, 0.05) 20px
            );
            animation: slide 20s linear infinite;
        }

        @keyframes slide {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(50px) translateY(50px); }
        }

        /* Form Controls */
        .form-control {
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.15) !important;
            transform: translateY(-1px);
        }

        .form-control-lg {
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }

        /* Type Selection Buttons */
        .btn-check:checked + .btn-outline-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-check:checked + .btn-outline-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border-color: #11998e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(17, 153, 142, 0.3);
        }

        .btn-check:checked + .btn-outline-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-color: #4facfe;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 172, 254, 0.3);
        }

        .btn-check:checked + .btn-outline-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-color: #f093fb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(240, 147, 251, 0.3);
        }

        .btn-outline-primary,
        .btn-outline-success,
        .btn-outline-info,
        .btn-outline-warning {
            transition: all 0.3s ease;
            border-width: 2px;
        }

        .btn-outline-primary:hover,
        .btn-outline-success:hover,
        .btn-outline-info:hover,
        .btn-outline-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Container Backgrounds */
        .time-range-container,
        .recurring-container {
            transition: all 0.3s ease;
        }

        .time-range-container:hover,
        .recurring-container:hover {
            background: #f8f9fa !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        /* Textarea */
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4) !important;
        }

        .btn-light {
            transition: all 0.3s ease;
        }

        .btn-light:hover {
            background: #e2e6ea;
            transform: translateY(-1px);
        }

        .rounded-pill {
            border-radius: 50px !important;
        }

        /* Modal Animation */
        .modal.fade .modal-dialog {
            transition: transform 0.4s ease-out, opacity 0.4s ease-out;
        }

        .modal.show .modal-dialog {
            animation: modalSlideIn 0.4s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px) scale(0.95);
                opacity: 0;
            }
            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        /* Select Dropdown Enhancement */
        select.form-control {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23667eea' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 12px;
            padding-right: 2.5rem;
        }

        /* Label Icons */
        .form-label i {
            opacity: 0.8;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .icon-circle {
                width: 35px;
                height: 35px;
            }
            
            .icon-circle i {
                font-size: 1rem;
            }
            
            .btn-outline-primary i,
            .btn-outline-success i,
            .btn-outline-info i,
            .btn-outline-warning i {
                font-size: 1.2rem !important;
            }
        }

        /* Focus States */
        input[type="date"]:focus,
        input[type="time"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
        }

        /* Placeholder Styling */
        ::placeholder {
            color: #adb5bd;
            opacity: 1;
        }

        :-ms-input-placeholder {
            color: #adb5bd;
        }

        ::-ms-input-placeholder {
            color: #adb5bd;
        }
    </style>

    <!-- Assign Order Modal -->
    <div class="modal fade" id="assignOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assigner une Commande</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="assign-order-content">
                        <!-- Dynamic content -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="confirmAssignment()">
                        <i class="fas fa-user-check me-2"></i>Assigner
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Assign Modal -->
    <div class="modal fade" id="bulkAssignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assignation en Masse</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="bulk-assign-info"></p>
                    <div class="mb-3">
                        <label class="form-label">Agent</label>
                        <select class="form-control" id="bulk-agent-select">
                            <option value="">Sélectionner un agent</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Créneau horaire</label>
                        <select class="form-control" id="bulk-time-slot">
                            <option value="09:00-10:00">09:00 - 10:00</option>
                            <option value="10:00-11:00">10:00 - 11:00</option>
                            <option value="11:00-12:00">11:00 - 12:00</option>
                            <option value="14:00-15:00">14:00 - 15:00</option>
                            <option value="15:00-16:00">15:00 - 16:00</option>
                            <option value="16:00-17:00">16:00 - 17:00</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="confirmBulkAssignment()">
                        <i class="fas fa-users me-2"></i>Assigner en Masse
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Schedule Modal -->
    <div class="modal fade" id="generateScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Générer Planning Hebdomadaire</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date de début</label>
                            <input type="date" class="form-control" id="schedule-start-date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date de fin</label>
                            <input type="date" class="form-control" id="schedule-end-date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Modèle</label>
                            <select class="form-control" id="schedule-template">
                                <option value="standard">Standard (Lun-Ven: 9h-12h & 14h-18h)</option>
                                <option value="extended">Étendu (Lun-Ven: 8h-12h & 13h-19h)</option>
                                <option value="flexible">Flexible (Lun-Ven: 10h-16h)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Agents</label>
                            <select class="form-control" id="schedule-agents" multiple>
                                <!-- Agents loaded dynamically -->
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Cette action générera automatiquement des créneaux pour les agents sélectionnés.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" onclick="confirmGenerateSchedule()">
                        <i class="fas fa-magic me-2"></i>Générer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/fr.js"></script>
    
    <script>
        // Global variables
        let currentDate = new Date();
        let calendar;
        let selectedOrders = new Set();
        let selectedAgent = null;
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            initDatePicker();
            loadPlanningData();
            initCalendar();
            initSelect2();
            
            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('slot-date').value = today;
            document.getElementById('slot-start-time').value = '09:00';
            document.getElementById('slot-end-time').value = '10:00';
            document.getElementById('schedule-start-date').value = today;
            document.getElementById('schedule-end-date').value = 
                new Date(Date.now() + 6 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        });
        
        function initDatePicker() {
            const datePicker = document.getElementById('date-picker');
            datePicker.valueAsDate = currentDate;
            updateDateDisplay();
        }
        
        function updateDateDisplay() {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const formattedDate = currentDate.toLocaleDateString('fr-FR', options);
            document.getElementById('current-date').textContent = formattedDate;
            document.getElementById('date-picker').valueAsDate = currentDate;
        }
        
        function previousDay() {
            currentDate.setDate(currentDate.getDate() - 1);
            updateDateDisplay();
            loadPlanningData();
            if (calendar) calendar.prev();
        }
        
        function nextDay() {
            currentDate.setDate(currentDate.getDate() + 1);
            updateDateDisplay();
            loadPlanningData();
            if (calendar) calendar.next();
        }
        
        function goToToday() {
            currentDate = new Date();
            updateDateDisplay();
            loadPlanningData();
            if (calendar) calendar.today();
        }
        
        function changeDate(dateString) {
            currentDate = new Date(dateString);
            updateDateDisplay();
            loadPlanningData();
            if (calendar) calendar.gotoDate(currentDate);
        }
        
        function changeView(view) {
            const btns = document.querySelectorAll('.view-btn');
            btns.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            if (calendar) {
                const viewMap = {
                    'day': 'timeGridDay',
                    'week': 'timeGridWeek',
                    'month': 'dayGridMonth'
                };
                calendar.changeView(viewMap[view]);
            }
        }
        
        function initSelect2() {
            $('#bulk-agent-select, #slot-agent, #schedule-agents').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }
        
        function loadPlanningData() {
            const dateStr = currentDate.toISOString().split('T')[0];
            
            showLoading('#timeline-container');
            showLoading('#orders-list');
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_planning_overview&date=' + dateStr
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updatePlanningOverview(data);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function updatePlanningOverview(data) {
            // Update stats
            const stats = data.stats || {};
            document.getElementById('stats-agents').textContent = stats.agents_scheduled || 0;
            document.getElementById('stats-slots').textContent = stats.total_slots || 0;
            document.getElementById('stats-orders').textContent = stats.orders_to_assign || 0;
            document.getElementById('stats-assigned').textContent = stats.orders_assigned || 0;
            
            // Update timeline
            updateTimeline(data.schedule, data.time_slots);
            
            // Update orders
            updateOrdersList(data.orders);
            
            // Update agents
            updateAgentsGrid(data.agents);
            
            // Update agent selects
            updateAgentSelects(data.agents);
        }
        
        function updateTimeline(schedule, timeSlots) {
            const container = document.getElementById('timeline-container');
            
            if (!schedule || schedule.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun créneau programmé pour aujourd'hui</p>
                        <button class="btn btn-primary mt-2" onclick="showAddSlotModal()">
                            <i class="fas fa-plus me-2"></i>Ajouter un créneau
                        </button>
                    </div>
                `;
                return;
            }
            
            // Group slots by hour
            const slotsByHour = {};
            schedule.forEach(slot => {
                const hour = parseInt(slot.start_time.split(':')[0]);
                if (!slotsByHour[hour]) slotsByHour[hour] = [];
                slotsByHour[hour].push(slot);
            });
            
            let timelineHTML = '';
            
            // Generate timeline for business hours (8:00 to 20:00)
            for (let hour = 8; hour <= 20; hour++) {
                const slots = slotsByHour[hour] || [];
                
                timelineHTML += `
                    <div class="timeline-hour">
                        <div class="hour-label">${hour.toString().padStart(2, '0')}:00</div>
                        <div class="time-slots">
                `;
                
                if (slots.length > 0) {
                    slots.forEach(slot => {
                        const startHour = parseInt(slot.start_time.split(':')[0]);
                        const startMin = parseInt(slot.start_time.split(':')[1]);
                        const endHour = parseInt(slot.end_time.split(':')[0]);
                        const endMin = parseInt(slot.end_time.split(':')[1]);
                        
                        const duration = (endHour - startHour) * 60 + (endMin - startMin);
                        const offset = startMin;
                        const widthPercent = (duration / 60) * 100;
                        const leftPercent = (offset / 60) * 100;
                        
                        timelineHTML += `
                            <div class="schedule-slot" 
                                 style="left: ${leftPercent}%; width: ${widthPercent}%;"
                                 onclick="viewScheduleSlot(${slot.id})"
                                 title="${slot.agent_name} - ${slot.time_range}">
                                <div class="slot-agent">${escapeHtml(slot.agent_name)}</div>
                                <div class="slot-info">
                                    <i class="fas fa-tasks me-1"></i>${slot.assigned_orders} cmd
                                </div>
                            </div>
                        `;
                    });
                }
                
                timelineHTML += '</div></div>';
            }
            
            container.innerHTML = timelineHTML;
        }
        
        function updateOrdersList(orders) {
            const container = document.getElementById('orders-list');
            const countElement = document.getElementById('orders-count');
            
            if (!orders || orders.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-clipboard-check fa-2x text-success mb-3"></i>
                        <p class="text-muted">Toutes les commandes sont assignées</p>
                    </div>
                `;
                countElement.textContent = '0';
                return;
            }
            
            countElement.textContent = orders.length;
            
            let ordersHTML = '';
            
            orders.forEach(order => {
                const priorityClass = `priority-${order.priority || 'medium'}`;
                const estimatedTime = order.estimated_time || '30 min';
                
                ordersHTML += `
                    <div class="order-item" data-order-id="${order.id}">
                        <div class="order-header">
                            <div class="order-number">#${escapeHtml(order.order_number || order.id)}</div>
                            <span class="order-priority ${priorityClass}">
                                ${order.priority || 'normal'}
                            </span>
                        </div>
                        <div class="order-details">
                            <div class="mb-2">
                                <strong>${escapeHtml(order.store_name || 'Boutique')}</strong><br>
                                <small>${escapeHtml(order.customer_name || 'Client')}</small>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-clock me-1"></i>${estimatedTime} •
                                <i class="fas fa-money-bill me-1 ms-2"></i>${formatCurrency(order.total_amount || 0)}
                            </div>
                        </div>
                        <div class="order-actions">
                            <button class="btn btn-sm btn-primary" onclick="assignOrder(${order.id})">
                                <i class="fas fa-user-check me-1"></i>Assigner
                            </button>
                            <button class="btn btn-sm btn-outline" onclick="viewOrderDetails(${order.id})">
                                <i class="fas fa-eye me-1"></i>Voir
                            </button>
                            <div class="form-check form-check-inline ms-auto">
                                <input class="form-check-input" type="checkbox" 
                                       value="${order.id}"
                                       onchange="toggleOrderSelection(${order.id}, this.checked)">
                            </div>
                        </div>
                    </div>
                `;
            });
            
            // Add bulk assign button if orders selected
            if (selectedOrders.size > 0) {
                ordersHTML += `
                    <div class="text-center mt-3">
                        <button class="btn btn-success" onclick="showBulkAssignModal()">
                            <i class="fas fa-users me-2"></i>
                            Assigner ${selectedOrders.size} commandes en masse
                        </button>
                    </div>
                `;
            }
            
            container.innerHTML = ordersHTML;
        }
        
        function updateAgentsGrid(agents) {
            const container = document.getElementById('agents-grid');
            
            if (!agents || agents.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-user-slash fa-2x text-muted mb-3"></i>
                        <p class="text-muted">Aucun agent disponible</p>
                    </div>
                `;
                return;
            }
            
            let agentsHTML = '';
            
            agents.forEach(agent => {
                const statusClass = `status-${agent.availability_status || 'offline'}`;
                const statusIcon = agent.is_available ? 'fa-check' : 'fa-clock';
                
                agentsHTML += `
                    <div class="agent-card" onclick="viewAgentSchedule(${agent.id})">
                        <div class="agent-avatar">
                            ${agent.name ? agent.name.charAt(0).toUpperCase() : 'A'}
                        </div>
                        <div class="agent-name">${escapeHtml(agent.name)}</div>
                        <div class="agent-team">${escapeHtml(agent.team_name || 'Sans équipe')}</div>
                        <div class="agent-status ${statusClass}">
                            <i class="fas ${statusIcon}"></i>
                            ${agent.availability_status === 'available' ? 'Disponible' : 
                              agent.availability_status === 'busy' ? 'Occupé' : 'Hors ligne'}
                        </div>
                        <div class="small text-muted mt-2">
                            ${agent.scheduled_slots_today || 0} créneau(s) aujourd'hui
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = agentsHTML;
        }
        
        function updateAgentSelects(agents) {
            const select = document.getElementById('slot-agent');
            const bulkSelect = document.getElementById('bulk-agent-select');
            const scheduleSelect = document.getElementById('schedule-agents');
            
            let options = '<option value="">Sélectionner un agent</option>';
            let scheduleOptions = '';
            
            agents.forEach(agent => {
                options += `<option value="${agent.id}">${escapeHtml(agent.name)} - ${escapeHtml(agent.team_name || 'Sans équipe')}</option>`;
                scheduleOptions += `<option value="${agent.id}" selected>${escapeHtml(agent.name)}</option>`;
            });
            
            select.innerHTML = options;
            bulkSelect.innerHTML = options;
            scheduleSelect.innerHTML = scheduleOptions;
            
            // Reinitialize Select2
            $(scheduleSelect).select2();
        }
        
        function toggleOrderSelection(orderId, isSelected) {
            if (isSelected) {
                selectedOrders.add(orderId);
            } else {
                selectedOrders.delete(orderId);
            }
            loadOrders();
        }
        
        function loadOrders() {
            const dateStr = currentDate.toISOString().split('T')[0];
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_orders_to_assign&date=' + dateStr
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateOrdersList(data.orders);
                }
            })
            .catch(console.error);
        }
        
        function showAddSlotModal() {
            const modal = new bootstrap.Modal(document.getElementById('addSlotModal'));
            modal.show();
        }
        
        function saveScheduleSlot() {
            const formData = new FormData();
            formData.append('action', 'create_schedule_slot');
            formData.append('agent_id', document.getElementById('slot-agent').value);
            formData.append('slot_date', document.getElementById('slot-date').value);
            formData.append('start_time', document.getElementById('slot-start-time').value + ':00');
            formData.append('end_time', document.getElementById('slot-end-time').value + ':00');
            formData.append('slot_type', document.getElementById('slot-type').value);
            formData.append('notes', document.getElementById('slot-notes').value);
            formData.append('recurring', document.getElementById('slot-recurring').value);
            formData.append('recurring_end', document.getElementById('slot-recurring-end').value);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('addSlotModal')).hide();
                    loadPlanningData();
                    if (calendar) calendar.refetchEvents();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function assignOrder(orderId) {
            selectedAgent = null;
            const dateStr = currentDate.toISOString().split('T')[0];
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_available_agents&date=' + dateStr + '&time_slot=09:00-10:00'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAssignOrderModal(orderId, data.agents);
                }
            })
            .catch(console.error);
        }
        
        function showAssignOrderModal(orderId, agents) {
            const modal = new bootstrap.Modal(document.getElementById('assignOrderModal'));
            const content = document.getElementById('assign-order-content');
            
            let agentsHTML = '<div class="agent-selector mb-4">';
            
            agents.forEach(agent => {
                agentsHTML += `
                    <div class="agent-option ${agent.is_available ? '' : 'unavailable'}" 
                         onclick="${agent.is_available ? `selectAgentForAssignment(${agent.id})` : ''}">
                        <div class="agent-avatar" style="background: ${agent.team_color || '#6366f1'}">
                            ${agent.name ? agent.name.charAt(0).toUpperCase() : 'A'}
                        </div>
                        <h6>${escapeHtml(agent.name)}</h6>
                        <p class="text-muted mb-1">${escapeHtml(agent.team_name || 'Sans équipe')}</p>
                        <div class="small">
                            <span class="badge bg-${agent.is_available ? 'success' : 'danger'}">
                                ${agent.is_available ? 'Disponible' : 'Indisponible'}
                            </span>
                            <span class="badge bg-info ms-1">${agent.current_assignments || 0}/5</span>
                        </div>
                    </div>
                `;
            });
            
            agentsHTML += '</div>';
            agentsHTML += `
                <div class="mb-3">
                    <label class="form-label">Créneau horaire</label>
                    <select class="form-control" id="assignment-time-slot">
                        <option value="09:00:00">09:00 - 10:00</option>
                        <option value="10:00:00">10:00 - 11:00</option>
                        <option value="11:00:00">11:00 - 12:00</option>
                        <option value="14:00:00">14:00 - 15:00</option>
                        <option value="15:00:00">15:00 - 16:00</option>
                        <option value="16:00:00">16:00 - 17:00</option>
                    </select>
                </div>
                <input type="hidden" id="selected-order-id" value="${orderId}">
            `;
            
            content.innerHTML = agentsHTML;
            modal.show();
        }
        
        function selectAgentForAssignment(agentId) {
            selectedAgent = agentId;
            document.querySelectorAll('.agent-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            event.target.closest('.agent-option').classList.add('selected');
        }
        
        function confirmAssignment() {
            if (!selectedAgent) {
                showToast('Veuillez sélectionner un agent', 'warning');
                return;
            }
            
            const orderId = document.getElementById('selected-order-id').value;
            const timeSlot = document.getElementById('assignment-time-slot').value;
            const dateStr = currentDate.toISOString().split('T')[0];
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=assign_order_to_agent&order_id=${orderId}&agent_id=${selectedAgent}&assigned_at=${dateStr} ${timeSlot}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('assignOrderModal')).hide();
                    loadPlanningData();
                    loadOrders();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function showBulkAssignModal() {
            if (selectedOrders.size === 0) {
                showToast('Veuillez sélectionner des commandes', 'warning');
                return;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('bulkAssignModal'));
            document.getElementById('bulk-assign-info').textContent = 
                `Assigner ${selectedOrders.size} commandes sélectionnées`;
            modal.show();
        }
        
        function confirmBulkAssignment() {
            const agentId = document.getElementById('bulk-agent-select').value;
            const timeSlot = document.getElementById('bulk-time-slot').value;
            const dateStr = currentDate.toISOString().split('T')[0];
            
            if (!agentId) {
                showToast('Veuillez sélectionner un agent', 'warning');
                return;
            }
            
            const orders = Array.from(selectedOrders);
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=bulk_assign_orders&agent_id=${agentId}&time_slot=${timeSlot}&date=${dateStr}&orders=${JSON.stringify(orders)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('bulkAssignModal')).hide();
                    selectedOrders.clear();
                    loadPlanningData();
                    loadOrders();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function showGenerateScheduleModal() {
            const modal = new bootstrap.Modal(document.getElementById('generateScheduleModal'));
            modal.show();
        }
        
        function confirmGenerateSchedule() {
            const startDate = document.getElementById('schedule-start-date').value;
            const endDate = document.getElementById('schedule-end-date').value;
            const template = document.getElementById('schedule-template').value;
            const agentSelect = document.getElementById('schedule-agents');
            const agentIds = Array.from(agentSelect.selectedOptions).map(opt => opt.value);
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=generate_weekly_schedule&start_date=${startDate}&end_date=${endDate}&template=${template}&agent_ids=${JSON.stringify(agentIds)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('generateScheduleModal')).hide();
                    loadPlanningData();
                    if (calendar) calendar.refetchEvents();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function exportSchedule() {
            const dateStr = currentDate.toISOString().split('T')[0];
            const endDate = new Date(currentDate.getTime() + 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=export_schedule&start_date=${dateStr}&end_date=${endDate}&format=csv`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Planning exporté avec succès', 'success');
                    // In production, trigger file download
                    console.log('Export data:', data);
                }
            })
            .catch(console.error);
        }
        
        function initCalendar() {
            const calendarEl = document.getElementById('calendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridDay',
                locale: 'fr',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridDay,timeGridWeek,dayGridMonth'
                },
                events: function(info, successCallback, failureCallback) {
                    const startDate = info.startStr.split('T')[0];
                    const endDate = info.endStr.split('T')[0];
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=get_planning_overview&date=${startDate}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const events = data.schedule.map(slot => ({
                                title: `${slot.agent_name} (${slot.assigned_orders} cmd)`,
                                start: `${slot.slot_date}T${slot.start_time}`,
                                end: `${slot.slot_date}T${slot.end_time}`,
                                backgroundColor: getSlotColor(slot.slot_type),
                                borderColor: getSlotColor(slot.slot_type),
                                extendedProps: {
                                    slotId: slot.id,
                                    agentName: slot.agent_name
                                }
                            }));
                            successCallback(events);
                        } else {
                            failureCallback(data.message);
                        }
                    })
                    .catch(failureCallback);
                },
                eventClick: function(info) {
                    const slotId = info.event.extendedProps.slotId;
                    viewScheduleSlot(slotId);
                },
                dateClick: function(info) {
                    changeDate(info.dateStr);
                }
            });
            
            calendar.render();
        }
        
        function getSlotColor(slotType) {
            switch(slotType) {
                case 'work': return '#6366f1';
                case 'break': return '#f59e0b';
                case 'meeting': return '#8b5cf6';
                case 'training': return '#10b981';
                default: return '#64748b';
            }
        }
        
        function viewScheduleSlot(slotId) {
            // Implementation for viewing/editing slot details
            showToast(`Visualisation du créneau #${slotId}`, 'info');
        }
        
        function viewAgentSchedule(agentId) {
            // Implementation for viewing agent schedule
            showToast(`Planning de l'agent #${agentId}`, 'info');
        }
        
        function viewOrderDetails(orderId) {
            // Implementation for viewing order details
            showToast(`Détails de la commande #${orderId}`, 'info');
        }
        
        // Utility functions
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'MAD',
                minimumFractionDigits: 0
            }).format(amount);
        }
        
        function showToast(message, type = 'info') {
            const container = document.querySelector('.toast-container') || createToastContainer();
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'danger' ? 'fa-exclamation-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.style.borderLeftColor = `var(--${type})`;
            
            toast.innerHTML = `
                <i class="fas ${icon} text-${type}"></i>
                <span>${escapeHtml(message)}</span>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
            return container;
        }
        
        function showLoading(selector) {
            const element = document.querySelector(selector);
            if (element) {
                element.innerHTML = `
                    <div class="loading">
                        <div class="spinner"></div>
                        <p class="mt-3">Chargement...</p>
                    </div>
                `;
            }
        }
        
        // Auto-refresh every 30 seconds
        setInterval(loadPlanningData, 30000);
    </script>
</body>
</html>