<?php
// export_clients.php
if(file_exists(stream_resolve_include_path("./config/init.php"))) {
    require_once("./config/init.php");
}

// Security: Input validation and sanitization
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m', $date);
    return $d && $d->format('Y-m') === $date;
}

// Get filter parameters
$dateFilter = isset($_POST['date_filter']) && validateDate($_POST['date_filter']) ? $_POST['date_filter'] : '';
$statusFilter = isset($_POST['status_filter']) && in_array($_POST['status_filter'], ['active', 'inactive']) ? $_POST['status_filter'] : '';
$searchQuery = isset($_POST['search']) ? sanitizeInput($_POST['search']) : '';

try {
    $db = DB::getInstance();
    
    // Same query as in the main file but without pagination
    $baseQuery = "
        SELECT 
            ROW_NUMBER() OVER (ORDER BY order_count DESC, total_spent DESC) as client_id,
            customer_name,
            customer_email,
            customer_phone,
            first_order_date,
            last_order_date,
            order_count,
            total_spent,
            CASE 
                WHEN last_order_date >= DATE_SUB(NOW(), INTERVAL 5 MONTH) 
                THEN 'Actif' 
                ELSE 'Inactif' 
            END as status
        FROM (
            SELECT 
                COALESCE(
                    NULLIF(customer_name, ''), 
                    CONCAT('Client ', SUBSTRING(COALESCE(customer_phone, customer_email), -4))
                ) as customer_name,
                COALESCE(customer_email, 'N/A') as customer_email,
                COALESCE(customer_phone, 'N/A') as customer_phone,
                MIN(order_date) as first_order_date,
                MAX(order_date) as last_order_date,
                COUNT(DISTINCT id) as order_count,
                COALESCE(SUM(total_amount), 0) as total_spent
            FROM orders 
            WHERE (customer_phone IS NOT NULL OR customer_email IS NOT NULL)
            GROUP BY 
                COALESCE(customer_phone, ''), 
                COALESCE(customer_email, ''),
                CASE 
                    WHEN customer_name IS NULL OR customer_name = '' 
                    THEN CONCAT('Client ', SUBSTRING(COALESCE(customer_phone, customer_email), -4))
                    ELSE customer_name 
                END
            HAVING order_count > 0
        ) as deduplicated_customers
    ";
    
    // Build WHERE clause for filters
    $whereConditions = [];
    $params = [];
    
    if ($dateFilter) {
        $whereConditions[] = "DATE_FORMAT(last_order_date, '%Y-%m') = ?";
        $params[] = $dateFilter;
    }
    
    if ($statusFilter === 'active') {
        $whereConditions[] = "last_order_date >= DATE_SUB(NOW(), INTERVAL 5 MONTH)";
    } elseif ($statusFilter === 'inactive') {
        $whereConditions[] = "last_order_date < DATE_SUB(NOW(), INTERVAL 5 MONTH)";
    }
    
    if ($searchQuery) {
        $whereConditions[] = "(customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ?)";
        $searchParam = '%' . $searchQuery . '%';
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    // Complete query for export
    $exportQuery = "SELECT * FROM (" . $baseQuery . ") as export_customers";
    if (!empty($whereConditions)) {
        $exportQuery .= " WHERE " . implode(" AND ", $whereConditions);
    }
    $exportQuery .= " ORDER BY order_count DESC, total_spent DESC";
    
    $customers = $db->getThisQuery($exportQuery, $params);
    
    // Generate filename with timestamp and filters
    $filename = 'clients_export_' . date('Y-m-d_H-i-s');
    if ($dateFilter) {
        $filename .= '_' . str_replace('-', '', $dateFilter);
    }
    if ($statusFilter) {
        $filename .= '_' . $statusFilter;
    }
    if ($searchQuery) {
        $filename .= '_' . preg_replace('/[^a-zA-Z0-9]/', '', $searchQuery);
    }
    $filename .= '.csv';
    
    // Set headers for file download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Create file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Column headers
    $headers = [
        'ID Client',
        'Nom',
        'Email',
        'Téléphone',
        'Date d\'inscription',
        'Dernière commande',
        'Nombre de commandes',
        'Total dépensé (€)',
        'Statut',
        'Jours depuis dernière commande',
        'Valeur moyenne par commande (€)'
    ];
    
    fputcsv($output, $headers, ';');
    
    // Data rows
    foreach ($customers as $customer) {
        $daysSinceLastOrder = (new DateTime())->diff(new DateTime($customer['last_order_date']))->days;
        $avgOrderValue = $customer['order_count'] > 0 ? $customer['total_spent'] / $customer['order_count'] : 0;
        
        $row = [
            'CL' . str_pad($customer['client_id'], 4, '0', STR_PAD_LEFT),
            $customer['customer_name'],
            $customer['customer_email'],
            $customer['customer_phone'],
            date('d/m/Y', strtotime($customer['first_order_date'])),
            date('d/m/Y', strtotime($customer['last_order_date'])),
            $customer['order_count'],
            number_format($customer['total_spent'], 2, ',', ''),
            $customer['status'],
            $daysSinceLastOrder,
            number_format($avgOrderValue, 2, ',', '')
        ];
        
        fputcsv($output, $row, ';');
    }
    
    // Add summary row
    if (!empty($customers)) {
        fputcsv($output, [], ';'); // Empty row
        fputcsv($output, ['RÉSUMÉ'], ';');
        fputcsv($output, ['Total clients', count($customers)], ';');
        fputcsv($output, ['Clients actifs', count(array_filter($customers, function($c) { return $c['status'] === 'Actif'; }))], ';');
        fputcsv($output, ['Clients inactifs', count(array_filter($customers, function($c) { return $c['status'] === 'Inactif'; }))], ';');
        fputcsv($output, ['Total des ventes', number_format(array_sum(array_column($customers, 'total_spent')), 2, ',', '') . ' €'], ';');
        fputcsv($output, ['Total des commandes', array_sum(array_column($customers, 'order_count'))], ';');
        fputcsv($output, ['Panier moyen', number_format(array_sum(array_column($customers, 'total_spent')) / array_sum(array_column($customers, 'order_count')), 2, ',', '') . ' €'], ';');
        fputcsv($output, ['Date d\'export', date('d/m/Y H:i:s')], ';');
        
        // Add filters used
        if ($dateFilter || $statusFilter || $searchQuery) {
            fputcsv($output, [], ';');
            fputcsv($output, ['FILTRES APPLIQUÉS'], ';');
            if ($dateFilter) {
                fputcsv($output, ['Période', $dateFilter], ';');
            }
            if ($statusFilter) {
                fputcsv($output, ['Statut', ucfirst($statusFilter)], ';');
            }
            if ($searchQuery) {
                fputcsv($output, ['Recherche', $searchQuery], ';');
            }
        }
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    http_response_code(500);
    echo "Erreur lors de l'export: " . $e->getMessage();
    exit;
}
?>