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
    
    switch ($_POST['action']) {
        case 'get_performance_summary':
            echo getPerformanceSummary($db, $responsable_id, $_POST['period'] ?? 'month');
            exit;
        case 'get_agent_performance_ranking':
            echo getAgentPerformanceRanking($db, $responsable_id, $_POST['period'] ?? 'month');
            exit;
        case 'get_team_performance_trends':
            echo getTeamPerformanceTrends($db, $responsable_id, $_POST['period'] ?? 'month');
            exit;
        case 'get_region_performance':
            echo getRegionPerformance($db, $responsable_id, $_POST['period'] ?? 'month');
            exit;
        case 'get_time_performance':
            echo getTimePerformance($db, $responsable_id, $_POST['period'] ?? 'month');
            exit;
        case 'get_detailed_report':
            echo getDetailedReport($db, $responsable_id, $_POST['start_date'] ?? '', $_POST['end_date'] ?? '');
            exit;
        case 'export_performance_report':
            echo exportPerformanceReport($db, $responsable_id, $_POST['period'] ?? 'month', $_POST['format'] ?? 'csv');
            exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    Redirect::to('../../login.php');
}

function getPerformanceSummary($db, $responsable_id, $period = 'month') {
    try {
        $date_filter = getDateFilter($period);
        
        $query = "
            SELECT 
                -- General stats
                COUNT(DISTINCT a.id) as total_agents,
                COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN a.id END) as active_agents,
                
                -- Assignment stats
                COUNT(DISTINCT aoa.id) as total_assignments,
                COUNT(DISTINCT CASE WHEN aoa.status = 'completed' THEN aoa.id END) as completed_assignments,
                COUNT(DISTINCT CASE WHEN aoa.status = 'pending' THEN aoa.id END) as pending_assignments,
                
                -- Confirmation stats
                COUNT(DISTINCT ac.id) as total_confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) as successful_confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'cancelled' THEN ac.id END) as cancelled_confirmations,
                
                -- Financial stats
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as total_confirmed_amount,
                SUM(CASE WHEN ac.status = 'cancelled' THEN ac.amount ELSE 0 END) as total_cancelled_amount,
                SUM(CASE WHEN ac.status = 'confirmed' THEN (ac.amount * a.service_fee / 100) ELSE 0 END) as total_agent_earnings,
                
                -- Performance metrics
                AVG(a.confirmation_rate) as avg_confirmation_rate,
                AVG(a.score) as avg_agent_score,
                
                -- Response time metrics
                AVG(TIMESTAMPDIFF(MINUTE, aoa.assigned_at, ac.handled_at)) as avg_response_time_minutes,
                
                -- Period comparison
                (
                    SELECT COUNT(DISTINCT ac2.id) 
                    FROM agent_confirmations ac2
                    INNER JOIN agents a2 ON ac2.agent_id = a2.id
                    WHERE a2.responsable_id = ?
                    AND ac2.created_at >= DATE_SUB(NOW(), INTERVAL 2 {$period}) 
                    AND ac2.created_at < DATE_SUB(NOW(), INTERVAL 1 {$period})
                ) as previous_period_confirmations,
                
                (
                    SELECT SUM(CASE WHEN ac2.status = 'confirmed' THEN ac2.amount ELSE 0 END)
                    FROM agent_confirmations ac2
                    INNER JOIN agents a2 ON ac2.agent_id = a2.id
                    WHERE a2.responsable_id = ?
                    AND ac2.created_at >= DATE_SUB(NOW(), INTERVAL 2 {$period}) 
                    AND ac2.created_at < DATE_SUB(NOW(), INTERVAL 1 {$period})
                ) as previous_period_amount
                
            FROM agents a
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id 
                AND aoa.assigned_at >= ?
            LEFT JOIN agent_confirmations ac ON a.id = ac.agent_id 
                AND ac.created_at >= ?
            WHERE a.responsable_id = ?
        ";
        
        $date_filter_value = $date_filter['value'];
        $params = [$responsable_id, $responsable_id, $date_filter_value, $date_filter_value, $responsable_id];
        
        $summary = $db->getThisQuery($query, $params);
        
        if (empty($summary)) {
            return json_encode(['success' => false, 'message' => 'Aucune donnée trouvée']);
        }
        
        $summary = $summary[0];
        
        // Calculate rates
        $summary['completion_rate'] = $summary['total_assignments'] > 0 
            ? round(($summary['completed_assignments'] / $summary['total_assignments']) * 100, 2) 
            : 0;
            
        $summary['confirmation_rate'] = $summary['total_confirmations'] > 0 
            ? round(($summary['successful_confirmations'] / $summary['total_confirmations']) * 100, 2) 
            : 0;
            
        $summary['cancellation_rate'] = $summary['total_confirmations'] > 0 
            ? round(($summary['cancelled_confirmations'] / $summary['total_confirmations']) * 100, 2) 
            : 0;
        
        // Calculate growth
        $summary['confirmation_growth'] = $summary['previous_period_confirmations'] > 0 
            ? round((($summary['successful_confirmations'] - $summary['previous_period_confirmations']) / $summary['previous_period_confirmations']) * 100, 2)
            : ($summary['successful_confirmations'] > 0 ? 100 : 0);
            
        $summary['amount_growth'] = $summary['previous_period_amount'] > 0 
            ? round((($summary['total_confirmed_amount'] - $summary['previous_period_amount']) / $summary['previous_period_amount']) * 100, 2)
            : ($summary['total_confirmed_amount'] > 0 ? 100 : 0);
        
        return json_encode([
            'success' => true,
            'summary' => $summary,
            'period' => $period,
            'period_label' => $date_filter['label']
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getAgentPerformanceRanking($db, $responsable_id, $period = 'month') {
    try {
        $date_filter = getDateFilter($period);
        
        $query = "
            SELECT 
                a.id,
                a.name,
                a.email,
                a.phone,
                r.name as region_name,
                t.name as team_name,
                a.confirmation_rate,
                a.score,
                
                -- Performance stats for period
                COUNT(DISTINCT aoa.id) as total_assignments,
                COUNT(DISTINCT CASE WHEN aoa.status = 'completed' THEN aoa.id END) as completed_assignments,
                
                -- Confirmation stats
                COUNT(DISTINCT ac.id) as total_confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) as successful_confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'cancelled' THEN ac.id END) as cancelled_confirmations,
                
                -- Financial stats
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as total_confirmed_amount,
                SUM(CASE WHEN ac.status = 'confirmed' THEN (ac.amount * a.service_fee / 100) ELSE 0 END) as agent_earnings,
                
                -- Performance metrics
                CASE 
                    WHEN COUNT(DISTINCT ac.id) > 0 
                    THEN ROUND((COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) / COUNT(DISTINCT ac.id)) * 100, 2)
                    ELSE 0 
                END as period_confirmation_rate,
                
                -- Response time
                AVG(TIMESTAMPDIFF(MINUTE, aoa.assigned_at, ac.handled_at)) as avg_response_time,
                
                -- Ranking metrics (weighted score)
                (
                    (CASE WHEN COUNT(DISTINCT ac.id) > 0 
                        THEN (COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) / COUNT(DISTINCT ac.id)) * 100 
                        ELSE 0 END) * 0.4 +
                    (a.score / 100) * 0.3 +
                    (CASE WHEN COUNT(DISTINCT aoa.id) > 0 
                        THEN (COUNT(DISTINCT CASE WHEN aoa.status = 'completed' THEN aoa.id END) / COUNT(DISTINCT aoa.id)) * 100 
                        ELSE 0 END) * 0.2 +
                    (CASE WHEN AVG(TIMESTAMPDIFF(MINUTE, aoa.assigned_at, ac.handled_at)) IS NOT NULL 
                        THEN GREATEST(0, 100 - (AVG(TIMESTAMPDIFF(MINUTE, aoa.assigned_at, ac.handled_at)) / 10)) 
                        ELSE 100 END) * 0.1
                ) as performance_score
                
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id 
                AND aoa.assigned_at >= ?
            LEFT JOIN agent_confirmations ac ON a.id = ac.agent_id 
                AND ac.order_id = aoa.order_id 
                AND ac.created_at >= ?
            WHERE a.responsable_id = ?
            GROUP BY a.id, r.id, t.id
            ORDER BY performance_score DESC, total_confirmed_amount DESC
        ";
        
        $ranking = $db->getThisQuery($query, [$date_filter['value'], $date_filter['value'], $responsable_id]);
        
        // Add rank position
        foreach ($ranking as $index => &$agent) {
            $agent['rank'] = $index + 1;
            
            // Determine performance level
            $score = $agent['performance_score'];
            if ($score >= 80) {
                $agent['performance_level'] = 'excellent';
                $agent['performance_color'] = '#10b981';
            } elseif ($score >= 60) {
                $agent['performance_level'] = 'good';
                $agent['performance_color'] = '#3b82f6';
            } elseif ($score >= 40) {
                $agent['performance_level'] = 'average';
                $agent['performance_color'] = '#f59e0b';
            } else {
                $agent['performance_level'] = 'needs_improvement';
                $agent['performance_color'] = '#ef4444';
            }
        }
        
        return json_encode([
            'success' => true,
            'ranking' => $ranking,
            'period' => $period
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getTeamPerformanceTrends($db, $responsable_id, $period = 'month') {
    try {
        $date_filter = getDateFilter('year'); // Always show yearly trends
        
        $query = "
            SELECT 
                DATE_FORMAT(ac.created_at, '%Y-%m') as month,
                COUNT(DISTINCT ac.id) as total_confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) as successful_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as total_amount,
                COUNT(DISTINCT a.id) as active_agents,
                AVG(a.confirmation_rate) as avg_confirmation_rate
            FROM agent_confirmations ac
            INNER JOIN agents a ON ac.agent_id = a.id
            WHERE a.responsable_id = ?
            AND ac.created_at >= ?
            GROUP BY DATE_FORMAT(ac.created_at, '%Y-%m')
            ORDER BY month ASC
        ";
        
        $trends = $db->getThisQuery($query, [$responsable_id, $date_filter['value']]);
        
        // Calculate month-over-month growth
        $previous_month = null;
        foreach ($trends as &$trend) {
            if ($previous_month) {
                $trend['confirmations_growth'] = $previous_month['successful_confirmations'] > 0 
                    ? round((($trend['successful_confirmations'] - $previous_month['successful_confirmations']) / $previous_month['successful_confirmations']) * 100, 2)
                    : ($trend['successful_confirmations'] > 0 ? 100 : 0);
                    
                $trend['amount_growth'] = $previous_month['total_amount'] > 0 
                    ? round((($trend['total_amount'] - $previous_month['total_amount']) / $previous_month['total_amount']) * 100, 2)
                    : ($trend['total_amount'] > 0 ? 100 : 0);
            } else {
                $trend['confirmations_growth'] = 0;
                $trend['amount_growth'] = 0;
            }
            $previous_month = $trend;
            
            // Format month label
            $trend['month_label'] = date('M Y', strtotime($trend['month'] . '-01'));
            
            // Calculate success rate
            $trend['success_rate'] = $trend['total_confirmations'] > 0 
                ? round(($trend['successful_confirmations'] / $trend['total_confirmations']) * 100, 2)
                : 0;
        }
        
        // Get daily trends for current month
        $daily_query = "
            SELECT 
                DATE(ac.created_at) as date,
                COUNT(DISTINCT ac.id) as daily_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as daily_amount
            FROM agent_confirmations ac
            INNER JOIN agents a ON ac.agent_id = a.id
            WHERE a.responsable_id = ?
            AND ac.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            GROUP BY DATE(ac.created_at)
            ORDER BY date ASC
        ";
        
        $daily_trends = $db->getThisQuery($daily_query, [$responsable_id]);
        
        return json_encode([
            'success' => true,
            'monthly_trends' => $trends,
            'daily_trends' => $daily_trends,
            'current_month' => date('F Y')
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getRegionPerformance($db, $responsable_id, $period = 'month') {
    try {
        $date_filter = getDateFilter($period);
        
        $query = "
            SELECT 
                r.id,
                r.name as region_name,
                COUNT(DISTINCT a.id) as total_agents,
                COUNT(DISTINCT CASE WHEN a.is_active = 1 THEN a.id END) as active_agents,
                
                -- Performance stats
                COUNT(DISTINCT ac.id) as total_confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) as successful_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as total_amount,
                
                -- Agent performance in region
                AVG(a.confirmation_rate) as avg_confirmation_rate,
                AVG(a.score) as avg_score
                
            FROM regions r
            INNER JOIN agents a ON r.id = a.region_id
            LEFT JOIN agent_confirmations ac ON a.id = ac.agent_id 
                AND ac.created_at >= ?
            WHERE a.responsable_id = ?
            GROUP BY r.id
            ORDER BY total_amount DESC
        ";
        
        $regions = $db->getThisQuery($query, [$date_filter['value'], $responsable_id]);
        
        // Calculate success rates and add color for chart
        $colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#10b981', '#3b82f6', '#f59e0b', '#ef4444'];
        
        foreach ($regions as $index => &$region) {
            $region['success_rate'] = $region['total_confirmations'] > 0 
                ? round(($region['successful_confirmations'] / $region['total_confirmations']) * 100, 2)
                : 0;
                
            $region['color'] = $colors[$index % count($colors)];
            
            // Calculate performance per agent
            $region['amount_per_agent'] = $region['active_agents'] > 0 
                ? round($region['total_amount'] / $region['active_agents'], 2)
                : 0;
        }
        
        return json_encode([
            'success' => true,
            'regions' => $regions,
            'period' => $period
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getTimePerformance($db, $responsable_id, $period = 'month') {
    try {
        $date_filter = getDateFilter($period);
        
        // Performance by hour of day
        $hourly_query = "
            SELECT 
                HOUR(ac.created_at) as hour,
                COUNT(DISTINCT ac.id) as confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) as successful_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as amount
            FROM agent_confirmations ac
            INNER JOIN agents a ON ac.agent_id = a.id
            WHERE a.responsable_id = ?
            AND ac.created_at >= ?
            GROUP BY HOUR(ac.created_at)
            ORDER BY hour ASC
        ";
        
        $hourly_data = $db->getThisQuery($hourly_query, [$responsable_id, $date_filter['value']]);
        
        // Performance by day of week
        $daily_query = "
            SELECT 
                DAYNAME(ac.created_at) as day_name,
                DAYOFWEEK(ac.created_at) as day_number,
                COUNT(DISTINCT ac.id) as confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) as successful_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as amount
            FROM agent_confirmations ac
            INNER JOIN agents a ON ac.agent_id = a.id
            WHERE a.responsable_id = ?
            AND ac.created_at >= ?
            GROUP BY DAYNAME(ac.created_at), DAYOFWEEK(ac.created_at)
            ORDER BY day_number ASC
        ";
        
        $daily_data = $db->getThisQuery($daily_query, [$responsable_id, $date_filter['value']]);
        
        // Performance by week
        $weekly_query = "
            SELECT 
                WEEK(ac.created_at, 3) as week_number,
                YEAR(ac.created_at) as year,
                MIN(DATE(ac.created_at)) as week_start,
                COUNT(DISTINCT ac.id) as confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) as successful_confirmations,
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as amount
            FROM agent_confirmations ac
            INNER JOIN agents a ON ac.agent_id = a.id
            WHERE a.responsable_id = ?
            AND ac.created_at >= ?
            GROUP BY YEAR(ac.created_at), WEEK(ac.created_at, 3)
            ORDER BY year, week_number ASC
        ";
        
        $weekly_data = $db->getThisQuery($weekly_query, [$responsable_id, $date_filter['value']]);
        
        // Calculate success rates
        foreach ($hourly_data as &$hour) {
            $hour['success_rate'] = $hour['confirmations'] > 0 
                ? round(($hour['successful_confirmations'] / $hour['confirmations']) * 100, 2)
                : 0;
        }
        
        foreach ($daily_data as &$day) {
            $day['success_rate'] = $day['confirmations'] > 0 
                ? round(($day['successful_confirmations'] / $day['confirmations']) * 100, 2)
                : 0;
        }
        
        foreach ($weekly_data as &$week) {
            $week['success_rate'] = $week['confirmations'] > 0 
                ? round(($week['successful_confirmations'] / $week['confirmations']) * 100, 2)
                : 0;
            $week['week_label'] = date('d M', strtotime($week['week_start']));
        }
        
        return json_encode([
            'success' => true,
            'hourly_performance' => $hourly_data,
            'daily_performance' => $daily_data,
            'weekly_performance' => $weekly_data
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getDetailedReport($db, $responsable_id, $start_date, $end_date) {
    try {
        if (empty($start_date) || empty($end_date)) {
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
        }
        
        $query = "
            SELECT 
                a.id as agent_id,
                a.name as agent_name,
                a.email as agent_email,
                r.name as region_name,
                t.name as team_name,
                
                -- Daily breakdown
                DATE(ac.created_at) as activity_date,
                DAYNAME(ac.created_at) as day_name,
                
                -- Activity stats
                COUNT(DISTINCT ac.id) as daily_confirmations,
                COUNT(DISTINCT CASE WHEN ac.status = 'confirmed' THEN ac.id END) as daily_successful,
                COUNT(DISTINCT CASE WHEN ac.status = 'cancelled' THEN ac.id END) as daily_cancelled,
                
                -- Financial stats
                SUM(CASE WHEN ac.status = 'confirmed' THEN ac.amount ELSE 0 END) as daily_amount,
                SUM(CASE WHEN ac.status = 'confirmed' THEN (ac.amount * a.service_fee / 100) ELSE 0 END) as daily_earnings,
                
                -- Response time
                AVG(TIMESTAMPDIFF(MINUTE, aoa.assigned_at, ac.handled_at)) as avg_response_time
                
            FROM agents a
            LEFT JOIN regions r ON a.region_id = r.id
            LEFT JOIN teams t ON a.team_id = t.id
            LEFT JOIN agent_confirmations ac ON a.id = ac.agent_id
            LEFT JOIN agent_order_assignments aoa ON a.id = aoa.agent_id AND ac.order_id = aoa.order_id
            WHERE a.responsable_id = ?
            AND DATE(ac.created_at) BETWEEN ? AND ?
            GROUP BY a.id, DATE(ac.created_at)
            ORDER BY activity_date DESC, daily_amount DESC
        ";
        
        $report = $db->getThisQuery($query, [$responsable_id, $start_date, $end_date]);
        
        // Calculate totals
        $totals = [
            'total_confirmations' => 0,
            'total_successful' => 0,
            'total_amount' => 0,
            'total_earnings' => 0
        ];
        
        foreach ($report as $row) {
            $totals['total_confirmations'] += $row['daily_confirmations'];
            $totals['total_successful'] += $row['daily_successful'];
            $totals['total_amount'] += $row['daily_amount'];
            $totals['total_earnings'] += $row['daily_earnings'];
        }
        
        $totals['success_rate'] = $totals['total_confirmations'] > 0 
            ? round(($totals['total_successful'] / $totals['total_confirmations']) * 100, 2)
            : 0;
        
        return json_encode([
            'success' => true,
            'report' => $report,
            'totals' => $totals,
            'period' => [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'formatted' => date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date))
            ]
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function exportPerformanceReport($db, $responsable_id, $period = 'month', $format = 'csv') {
    try {
        $data = getPerformanceSummary($db, $responsable_id, $period);
        $data = json_decode($data, true);
        
        if (!$data['success']) {
            return json_encode(['success' => false, 'message' => $data['message']]);
        }
        
        $filename = "performance_report_" . date('Y-m-d') . "." . $format;
        
        return json_encode([
            'success' => true,
            'filename' => $filename,
            'data' => $data['summary']
        ]);
        
    } catch (Exception $e) {
        return json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

function getDateFilter($period) {
    switch ($period) {
        case 'day':
            return ['value' => 'CURDATE()', 'label' => 'Aujourd\'hui'];
        case 'week':
            return ['value' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)', 'label' => 'Cette semaine'];
        case 'month':
            return ['value' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)', 'label' => 'Ce mois'];
        case 'quarter':
            return ['value' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)', 'label' => 'Ce trimestre'];
        case 'year':
            return ['value' => 'DATE_SUB(NOW(), INTERVAL 365 DAY)', 'label' => 'Cette année'];
        default:
            return ['value' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)', 'label' => 'Ce mois'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance d'Équipe | Dashboard Responsable</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --sidebar-width: 260px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--dark-color);
        }
        
        /* Sidebar (same as home page) */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: white;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        
        .logo-section {
            padding: 30px 25px;
            background: var(--primary-gradient);
            color: white;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo i {
            font-size: 28px;
        }
        
        .user-info {
            padding: 25px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 32px;
            font-weight: 600;
        }
        
        .user-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-role {
            color: #64748b;
            font-size: 14px;
        }
        
        .nav-menu {
            flex: 1;
            padding: 20px 0;
        }
        
        .nav-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #64748b;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .nav-item:hover {
            color: #667eea;
            background: #f1f5f9;
        }
        
        .nav-item.active {
            color: #667eea;
            background: #f1f5f9;
            border-left-color: #667eea;
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        
        .logout-btn {
            padding: 15px 25px;
            border-top: 1px solid #e2e8f0;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 8px;
        }
        
        .header-subtitle {
            color: #64748b;
            font-size: 14px;
        }
        
        /* Period Selector */
        .period-selector {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .period-buttons {
            display: flex;
            gap: 10px;
        }
        
        .period-btn {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .period-btn:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .period-btn.active {
            background: var(--primary-gradient);
            border-color: transparent;
            color: white;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }
        
        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.warning::before { background: var(--warning-gradient); }
        .stat-card.danger::before { background: var(--danger-gradient); }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 20px;
            color: white;
            background: var(--primary-gradient);
        }
        
        .stat-card.success .stat-icon { background: var(--success-gradient); }
        .stat-card.warning .stat-icon { background: var(--warning-gradient); }
        .stat-card.danger .stat-icon { background: var(--danger-gradient); }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--dark-color);
        }
        
        .stat-label {
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }
        
        .stat-change {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            margin-top: 8px;
        }
        
        .stat-change.positive { color: #10b981; }
        .stat-change.negative { color: #ef4444; }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Charts */
        .chart-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        /* Ranking Table */
        .ranking-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }
        
        .ranking-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .ranking-table th {
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .ranking-table td {
            padding: 20px 12px;
            border-bottom: 1px solid #e2e8f0;
            transition: background-color 0.2s ease;
        }
        
        .ranking-table tbody tr:hover td {
            background-color: #f8fafc;
        }
        
        .rank-cell {
            width: 60px;
            text-align: center;
            font-weight: 700;
            font-size: 20px;
        }
        
        .rank-1 { color: #f59e0b; }
        .rank-2 { color: #94a3b8; }
        .rank-3 { color: #cd7f32; }
        
        .agent-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .agent-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .agent-info h5 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--dark-color);
        }
        
        .agent-info p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }
        
        .performance-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .performance-excellent {
            background: #d1fae5;
            color: #065f46;
        }
        
        .performance-good {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .performance-average {
            background: #fef3c7;
            color: #92400e;
        }
        
        .performance-needs_improvement {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Region Performance */
        .regions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .region-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #667eea;
        }
        
        .region-name {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        
        .region-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .region-stat {
            text-align: center;
        }
        
        .region-stat-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        .region-stat-label {
            font-size: 12px;
            color: #64748b;
        }
        
        /* Custom Date Range */
        .date-range-selector {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .date-input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            color: var(--dark-color);
        }
        
        /* Loading */
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast */
        .toast-container {
            z-index: 9999;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-section">
            <div class="logo">
                <i class="fas fa-chart-network"></i>
                <span>TeamManager</span>
            </div>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($responsable['name'], 0, 1)); ?>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($responsable['name']); ?></div>
            <div class="user-role">Responsable d'Agents</div>
        </div>
        
        <div class="nav-menu">
            <a href="./home" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Mes Agents</span>
            </a>
            <a href="./performance" class="nav-item active">
                <i class="fas fa-chart-line"></i>
                <span>Performance</span>
            </a>
            <a href="./planning" class="nav-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Planning</span>
            </a>
            <a href="./settings" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
        </div>
        
        <div class="logout-btn">
            <a href="?logout" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Déconnexion</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <h1>Analyse de Performance</h1>
                <div class="header-subtitle">
                    <i class="fas fa-chart-line me-2"></i>
                    Suivi détaillé des performances de votre équipe
                </div>
            </div>
            <div class="export-buttons">
                <button class="btn btn-outline-primary" onclick="exportReport('pdf')">
                    <i class="fas fa-file-pdf me-2"></i>PDF
                </button>
                <button class="btn btn-outline-success" onclick="exportReport('excel')">
                    <i class="fas fa-file-excel me-2"></i>Excel
                </button>
            </div>
        </div>

        <!-- Period Selector -->
        <div class="period-selector">
            <div class="period-buttons">
                <button class="period-btn active" onclick="changePeriod('day')">Aujourd'hui</button>
                <button class="period-btn" onclick="changePeriod('week')">Cette semaine</button>
                <button class="period-btn" onclick="changePeriod('month')">Ce mois</button>
                <button class="period-btn" onclick="changePeriod('quarter')">Ce trimestre</button>
                <button class="period-btn" onclick="changePeriod('year')">Cette année</button>
            </div>
            
            <div class="date-range-selector">
                <input type="date" id="start-date" class="date-input">
                <span>à</span>
                <input type="date" id="end-date" class="date-input">
                <button class="btn btn-primary" onclick="loadCustomDateRange()">
                    <i class="fas fa-filter me-2"></i>Appliquer
                </button>
            </div>
        </div>

        <!-- Performance Overview -->
        <div class="stats-grid" id="performance-overview">
            <!-- Loading state -->
            <div class="stat-card">
                <div class="loading">
                    <div class="loading-spinner"></div>
                </div>
            </div>
        </div>

        <!-- Performance Trends -->
        <div class="dashboard-grid">
            <!-- Main Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Tendances de Performance</div>
                    <select id="trend-type" class="form-select form-select-sm" style="width: auto;" onchange="loadPerformanceTrends()">
                        <option value="monthly">Mensuelle</option>
                        <option value="weekly">Hebdomadaire</option>
                        <option value="daily">Quotidienne</option>
                    </select>
                </div>
                <canvas id="performanceTrendsChart" height="300"></canvas>
            </div>
            
            <!-- Agent Ranking -->
            <div class="ranking-container">
                <div class="chart-header">
                    <div class="chart-title">Classement des Agents</div>
                    <span class="text-muted" style="font-size: 12px;">Top 10</span>
                </div>
                <div id="agent-ranking">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Regional Performance -->
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Performance par Région</div>
            </div>
            <div id="regions-performance">
                <div class="loading">
                    <div class="loading-spinner"></div>
                </div>
            </div>
        </div>

        <!-- Time Analysis -->
        <div class="dashboard-grid">
            <!-- Hourly Performance -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Performance par Heure</div>
                </div>
                <canvas id="hourlyPerformanceChart" height="250"></canvas>
            </div>
            
            <!-- Daily Performance -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title">Performance par Jour</div>
                </div>
                <canvas id="dailyPerformanceChart" height="250"></canvas>
            </div>
        </div>

        <!-- Detailed Report -->
        <div class="chart-container">
            <div class="chart-header">
                <div class="chart-title">Rapport Détaillé</div>
                <button class="btn btn-sm btn-outline-primary" onclick="loadDetailedReport()">
                    <i class="fas fa-redo me-1"></i>Actualiser
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="detailed-report">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Agent</th>
                            <th>Confirmations</th>
                            <th>Réussites</th>
                            <th>Taux</th>
                            <th>Montant</th>
                            <th>Gains</th>
                            <th>Temps Réponse</th>
                        </tr>
                    </thead>
                    <tbody id="report-body">
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="loading">
                                    <div class="loading-spinner"></div>
                                    <p class="mt-2">Chargement du rapport...</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let currentPeriod = 'month';
        let performanceTrendsChart = null;
        let hourlyPerformanceChart = null;
        let dailyPerformanceChart = null;
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPerformanceOverview();
            loadPerformanceTrends();
            loadAgentRanking();
            loadRegionalPerformance();
            loadTimePerformance();
            loadDetailedReport();
            
            // Set default dates
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            document.getElementById('start-date').valueAsDate = firstDay;
            document.getElementById('end-date').valueAsDate = today;
        });
        
        function changePeriod(period) {
            currentPeriod = period;
            
            // Update active button
            document.querySelectorAll('.period-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.toLowerCase().includes(period)) {
                    btn.classList.add('active');
                }
            });
            
            // Reload all data
            loadPerformanceOverview();
            loadPerformanceTrends();
            loadAgentRanking();
            loadRegionalPerformance();
            loadTimePerformance();
        }
        
        function loadCustomDateRange() {
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            if (!startDate || !endDate) {
                showToast('Veuillez sélectionner une plage de dates', 'warning');
                return;
            }
            
            loadDetailedReport(startDate, endDate);
        }
        
        function loadPerformanceOverview() {
            const formData = new FormData();
            formData.append('action', 'get_performance_summary');
            formData.append('period', currentPeriod);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updatePerformanceOverview(data.summary);
                } else {
                    showToast(data.message || 'Erreur de chargement', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        function updatePerformanceOverview(summary) {
            const overviewHtml = `
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value">${summary.successful_confirmations || 0}</div>
                    <div class="stat-label">Confirmations réussies</div>
                    <div class="stat-change ${summary.confirmation_growth >= 0 ? 'positive' : 'negative'}">
                        <i class="fas fa-${summary.confirmation_growth >= 0 ? 'arrow-up' : 'arrow-down'}"></i>
                        ${Math.abs(summary.confirmation_growth || 0)}% vs. période précédente
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value">${formatCurrency(summary.total_confirmed_amount || 0)}</div>
                    <div class="stat-label">Montant confirmé</div>
                    <div class="stat-change ${summary.amount_growth >= 0 ? 'positive' : 'negative'}">
                        <i class="fas fa-${summary.amount_growth >= 0 ? 'arrow-up' : 'arrow-down'}"></i>
                        ${Math.abs(summary.amount_growth || 0)}% vs. période précédente
                    </div>
                </div>
                
                <div class="stat-card success">
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-value">${summary.confirmation_rate || 0}%</div>
                    <div class="stat-label">Taux de confirmation</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        ${summary.avg_confirmation_rate || 0}% moyenne
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-stopwatch"></i>
                    </div>
                    <div class="stat-value">${summary.avg_response_time_minutes ? Math.round(summary.avg_response_time_minutes) : 0} min</div>
                    <div class="stat-label">Temps de réponse moyen</div>
                    <div class="stat-change positive">
                        <i class="fas fa-user-clock"></i>
                        ${summary.active_agents || 0} agents actifs
                    </div>
                </div>
            `;
            
            document.getElementById('performance-overview').innerHTML = overviewHtml;
        }
        
        function loadPerformanceTrends() {
            const trendType = document.getElementById('trend-type').value;
            const formData = new FormData();
            formData.append('action', 'get_team_performance_trends');
            formData.append('period', currentPeriod);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (trendType === 'monthly') {
                        renderMonthlyTrendsChart(data.monthly_trends);
                    } else if (trendType === 'weekly') {
                        renderWeeklyTrendsChart(data.weekly_trends);
                    } else {
                        renderDailyTrendsChart(data.daily_trends);
                    }
                }
            })
            .catch(console.error);
        }
        
        function renderMonthlyTrendsChart(trends) {
            const ctx = document.getElementById('performanceTrendsChart').getContext('2d');
            
            if (performanceTrendsChart) {
                performanceTrendsChart.destroy();
            }
            
            const labels = trends.map(t => t.month_label);
            const confirmations = trends.map(t => t.successful_confirmations);
            const amounts = trends.map(t => t.total_amount / 1000); // Convert to thousands
            
            performanceTrendsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Confirmations réussies',
                            data: confirmations,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Montant (K MAD)',
                            data: amounts,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Confirmations'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Montant (K MAD)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        },
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.datasetIndex === 1) {
                                        label += formatCurrency(context.raw * 1000);
                                    } else {
                                        label += context.raw;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        function renderWeeklyTrendsChart(trends) {
            // Similar implementation for weekly trends
        }
        
        function renderDailyTrendsChart(trends) {
            // Similar implementation for daily trends
        }
        
        function loadAgentRanking() {
            const formData = new FormData();
            formData.append('action', 'get_agent_performance_ranking');
            formData.append('period', currentPeriod);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateAgentRanking(data.ranking);
                }
            })
            .catch(console.error);
        }
        
        function updateAgentRanking(ranking) {
            const top10 = ranking.slice(0, 10);
            
            const rankingHtml = `
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>Agent</th>
                            <th>Taux</th>
                            <th>Score</th>
                            <th>Montant</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${top10.map(agent => `
                            <tr>
                                <td class="rank-cell rank-${agent.rank}">${agent.rank}</td>
                                <td>
                                    <div class="agent-cell">
                                        <div class="agent-avatar" style="background: ${agent.performance_color || '#667eea'}">
                                            ${agent.name ? agent.name.charAt(0).toUpperCase() : 'A'}
                                        </div>
                                        <div class="agent-info">
                                            <h5>${escapeHtml(agent.name)}</h5>
                                            <p>${escapeHtml(agent.team_name || 'Sans équipe')}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>${agent.period_confirmation_rate || 0}%</td>
                                <td>${agent.score || 0}</td>
                                <td>${formatCurrency(agent.total_confirmed_amount || 0)}</td>
                                <td>
                                    <span class="performance-badge performance-${agent.performance_level}">
                                        ${getPerformanceLabel(agent.performance_level)}
                                    </span>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
            
            document.getElementById('agent-ranking').innerHTML = rankingHtml;
        }
        
        function getPerformanceLabel(level) {
            const labels = {
                'excellent': 'Excellent',
                'good': 'Bon',
                'average': 'Moyen',
                'needs_improvement': 'À améliorer'
            };
            return labels[level] || 'Inconnu';
        }
        
        function loadRegionalPerformance() {
            const formData = new FormData();
            formData.append('action', 'get_region_performance');
            formData.append('period', currentPeriod);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateRegionalPerformance(data.regions);
                }
            })
            .catch(console.error);
        }
        
        function updateRegionalPerformance(regions) {
            const regionsHtml = `
                <div class="regions-grid">
                    ${regions.map(region => `
                        <div class="region-card" style="border-left-color: ${region.color}">
                            <div class="region-name">
                                <i class="fas fa-map-marker-alt me-2"></i>${escapeHtml(region.region_name)}
                            </div>
                            <div class="progress mb-3" style="height: 8px;">
                                <div class="progress-bar" style="width: ${region.success_rate}%; background: ${region.color}"></div>
                            </div>
                            <div class="region-stats">
                                <div class="region-stat">
                                    <div class="region-stat-value">${region.active_agents || 0}</div>
                                    <div class="region-stat-label">Agents</div>
                                </div>
                                <div class="region-stat">
                                    <div class="region-stat-value">${region.successful_confirmations || 0}</div>
                                    <div class="region-stat-label">Confirmations</div>
                                </div>
                                <div class="region-stat">
                                    <div class="region-stat-value">${formatCurrency(region.total_amount || 0)}</div>
                                    <div class="region-stat-label">Montant</div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            document.getElementById('regions-performance').innerHTML = regionsHtml;
        }
        
        function loadTimePerformance() {
            const formData = new FormData();
            formData.append('action', 'get_time_performance');
            formData.append('period', currentPeriod);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderHourlyPerformanceChart(data.hourly_performance);
                    renderDailyPerformanceChart(data.daily_performance);
                }
            })
            .catch(console.error);
        }
        
        function renderHourlyPerformanceChart(data) {
            const ctx = document.getElementById('hourlyPerformanceChart').getContext('2d');
            
            if (hourlyPerformanceChart) {
                hourlyPerformanceChart.destroy();
            }
            
            // Prepare data
            const hours = Array.from({length: 24}, (_, i) => i);
            const confirmations = hours.map(hour => {
                const hourData = data.find(d => d.hour == hour);
                return hourData ? hourData.confirmations : 0;
            });
            
            hourlyPerformanceChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: hours.map(h => `${h}:00`),
                    datasets: [{
                        label: 'Confirmations par heure',
                        data: confirmations,
                        backgroundColor: 'rgba(102, 126, 234, 0.5)',
                        borderColor: '#667eea',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Nombre de confirmations'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Heure de la journée'
                            }
                        }
                    }
                }
            });
        }
        
        function renderDailyPerformanceChart(data) {
            const ctx = document.getElementById('dailyPerformanceChart').getContext('2d');
            
            if (dailyPerformanceChart) {
                dailyPerformanceChart.destroy();
            }
            
            const dayOrder = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
            const amounts = dayOrder.map(day => {
                const dayData = data.find(d => d.day_name.includes(day));
                return dayData ? dayData.amount : 0;
            });
            
            dailyPerformanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dayOrder,
                    datasets: [{
                        label: 'Montant confirmé',
                        data: amounts,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Montant (MAD)'
                            }
                        }
                    }
                }
            });
        }
        
        function loadDetailedReport(startDate = '', endDate = '') {
            const formData = new FormData();
            formData.append('action', 'get_detailed_report');
            if (startDate) formData.append('start_date', startDate);
            if (endDate) formData.append('end_date', endDate);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDetailedReport(data.report, data.totals, data.period);
                }
            })
            .catch(console.error);
        }
        
        function updateDetailedReport(report, totals, period) {
            const reportBody = document.getElementById('report-body');
            
            if (!report || report.length === 0) {
                reportBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-3"></i>
                            <p>Aucune donnée pour la période sélectionnée</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            reportBody.innerHTML = report.map(row => `
                <tr>
                    <td>${formatDate(row.activity_date)}</td>
                    <td>
                        <div class="agent-cell">
                            <div class="agent-avatar">
                                ${row.agent_name ? row.agent_name.charAt(0).toUpperCase() : 'A'}
                            </div>
                            <div class="agent-info">
                                <h6>${escapeHtml(row.agent_name)}</h6>
                                <small>${escapeHtml(row.region_name || 'N/A')}</small>
                            </div>
                        </div>
                    </td>
                    <td>${row.daily_confirmations || 0}</td>
                    <td>${row.daily_successful || 0}</td>
                    <td>
                        ${row.daily_confirmations > 0 
                            ? Math.round((row.daily_successful / row.daily_confirmations) * 100) 
                            : 0}%
                    </td>
                    <td>${formatCurrency(row.daily_amount || 0)}</td>
                    <td>${formatCurrency(row.daily_earnings || 0)}</td>
                    <td>${row.avg_response_time ? Math.round(row.avg_response_time) + ' min' : 'N/A'}</td>
                </tr>
            `).join('');
            
            // Add totals row
            reportBody.innerHTML += `
                <tr class="table-active fw-bold">
                    <td colspan="2">Totaux (${period.formatted})</td>
                    <td>${totals.total_confirmations}</td>
                    <td>${totals.total_successful}</td>
                    <td>${totals.success_rate}%</td>
                    <td>${formatCurrency(totals.total_amount)}</td>
                    <td>${formatCurrency(totals.total_earnings)}</td>
                    <td>-</td>
                </tr>
            `;
        }
        
        function exportReport(format) {
            const formData = new FormData();
            formData.append('action', 'export_performance_report');
            formData.append('period', currentPeriod);
            formData.append('format', format);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Rapport exporté avec succès', 'success');
                    // In a real application, this would trigger a file download
                } else {
                    showToast(data.message || 'Erreur lors de l\'export', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Erreur de connexion', 'danger');
            });
        }
        
        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount) + ' MAD';
        }
        
        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }
        
        function showToast(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                        ${escapeHtml(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            // Add to container
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            container.appendChild(toast);
            document.body.appendChild(container);
            
            // Show toast
            const bsToast = new bootstrap.Toast(toast, {
                autohide: true,
                delay: 3000
            });
            bsToast.show();
            
            // Remove after hiding
            toast.addEventListener('hidden.bs.toast', function() {
                container.remove();
            });
        }
        
        // Auto-refresh data every 5 minutes
        setInterval(() => {
            loadPerformanceOverview();
            loadPerformanceTrends();
            loadAgentRanking();
        }, 300000);
    </script>
</body>
</html>