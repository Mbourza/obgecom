<?php

if (ob_get_level()) {
    ob_end_clean();
}

if(file_exists(stream_resolve_include_path("../config/only.php"))) {
    require_once("../config/only.php");
}

// Include PhpSpreadsheet library (you'll need to install it via composer)
require_once '../vendor/autoload.php';
require_once '../classes/Config.php';
require_once '../classes/DB.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

$db = DB::getInstance();

// Check if user is logged in
$user_id = getCurrentUserId($db);
if (!$user_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data (filters)
$filters = $_POST;

// Build the query (same as before)
$query = "SELECT o.*, s.storeName as store_name 
          FROM orders o 
          LEFT JOIN stores s ON o.store_id = s.id 
          WHERE o.user_id = ?";

$params = [$user_id];

// Apply filters (same logic as before)
$conditions = [];

if (!empty($filters['confirmation_status'])) {
    $conditions[] = "o.status = ?";
    $params[] = $filters['confirmation_status'];
}

if (!empty($filters['shipping_status'])) {
    $conditions[] = "o.shipping_status = ?";
    $params[] = $filters['shipping_status'];
}

if (!empty($filters['date_range'])) {
    switch ($filters['date_range']) {
        case 'today':
            $conditions[] = "DATE(o.created_at) = CURDATE()";
            break;
        case 'yesterday':
            $conditions[] = "DATE(o.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '3months':
            $conditions[] = "o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case 'custom':
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                $conditions[] = "DATE(o.created_at) BETWEEN ? AND ?";
                $params[] = $filters['start_date'];
                $params[] = $filters['end_date'];
            }
            break;
    }
}

if (!empty($filters['store_id']) && is_numeric($filters['store_id'])) {
    $conditions[] = "o.store_id = ?";
    $params[] = intval($filters['store_id']);
}

if (!empty($filters['search'])) {
    $search_term = '%' . $filters['search'] . '%';
    $conditions[] = "(o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ? OR o.shipping_city LIKE ? OR o.products LIKE ?)";
    $params = array_merge($params, array_fill(0, 5, $search_term));
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$query .= " ORDER BY o.created_at DESC";

try {
    // Get orders
    $orders = $db->getThisQuery($query, $params);

    // Status labels mapping
    $status_labels = [
        'new' => 'Nouveau colis',
        'pickup_pending' => 'En cours de ramassage',
        'collected' => 'Ramassé',
        'in_transit' => 'En transit',
        'arrived_at_agency' => "Arrivé à l'agence",
        'out_for_delivery' => 'En cours de livraison',
        'delivered' => 'Livrée',
        'refused' => 'Refusée',
        'unreachable' => 'Client injoignable',
        'rescheduled' => 'Reprogrammée',
        'returned_to_sender' => "Retour à l'expéditeur",
        'cancelled' => 'Annulée',
        'address_error' => "Erreur d'adresse",
        'warehouse_waiting' => 'En attente au dépôt',
        'delivery_failed' => 'Livraison échouée',
        'pending' => 'En attente',
        'processing' => 'En préparation',
        'shipped' => 'Expédiée',
        'not_submitted' => 'Non soumis'
    ];
    
    $confirmation_options = [
        'confirmed' => 'Confirmée',
        'no-answer' => 'Pas de réponse',
        'busy' => 'Occupé',
        'cancelled' => 'Annulée',
        'double-order' => 'Double commande',
        'unreachable' => 'Injoignable'
    ];

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator("Votre Système")
        ->setLastModifiedBy("Système d'Export")
        ->setTitle("Rapport des Commandes")
        ->setSubject("Export des commandes filtrées")
        ->setDescription("Rapport détaillé des commandes avec filtres appliqués")
        ->setKeywords("commandes export rapport")
        ->setCategory("Rapports");

    // Add title and metadata
    $sheet->setCellValue('A1', 'RAPPORT DES COMMANDES');
    $sheet->setCellValue('A2', 'Date d\'export: ' . date('d/m/Y H:i:s'));
    $sheet->setCellValue('A3', 'Nombre total de commandes: ' . count($orders));
    
    // Calculate totals
    $total_amount = 0;
    $total_items = 0;
    foreach ($orders as $order) {
        $total_amount += $order['total_amount'] ?? 0;
        $total_items += $order['item_count'] ?? 0;
    }
    
    $sheet->setCellValue('A4', 'Montant total: ' . number_format($total_amount, 2, ',', ' ') . ' DH');
    $sheet->setCellValue('A5', 'Articles totaux: ' . $total_items);

    // Style the title section
    $sheet->mergeCells('A1:P1');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 18,
            'color' => ['rgb' => '1F4E79']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E7F3FF']
        ]
    ]);

    // Style metadata
    $sheet->getStyle('A2:A5')->applyFromArray([
        'font' => ['bold' => true, 'size' => 11],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F8F9FA']
        ]
    ]);

    // Headers row (starting at row 7)
    $headers = [
        'N° Commande',
        'Code d\'envoi',
        'Client',
        'Email',
        'Téléphone',
        'Ville',
        'Adresse',
        'Articles',
        'Quantité',
        'Montant',
        'Devise',
        'Statut d\'expédition',
        'Statut de confirmation',
        'Magasin',
        'Date de commande',
        'Date de création'
    ];

    $header_row = 7;
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $header_row, $header);
        $col++;
    }

    // Style headers
    $sheet->getStyle('A7:P7')->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 12
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2E75B6']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);

    // Add data rows
    $row = 8;
    foreach ($orders as $order) {
        $data = [
            $order['order_number'] ?? '',
            $order['tracking_number'] ?? '-',
            $order['customer_name'] ?? '',
            $order['customer_email'] ?? '',
            $order['customer_phone'] ?? '-',
            $order['customer_ville'] ?? '-',
            $order['shipping_address'] ?? '-',
            $order['products'] ?? '',
            $order['item_count'] ?? 0,
            number_format($order['total_amount'] ?? 0, 2, ',', ' '),
            $order['currency'] ?? '',
            $status_labels[$order['shipping_status'] ?? ''] ?? ($order['shipping_status'] ?? ''),
            $confirmation_options[$order['status'] ?? ''] ?? ($order['status'] ?? ''),
            $order['store_name'] ?? 'N/A',
            isset($order['order_date']) ? date('d/m/Y H:i', strtotime($order['order_date'])) : '',
            isset($order['created_at']) ? date('d/m/Y H:i', strtotime($order['created_at'])) : ''
        ];

        $col = 'A';
        foreach ($data as $value) {
            $sheet->setCellValue($col . $row, $value);
            $col++;
        }

        // Apply alternating row colors
        if ($row % 2 == 0) {
            $sheet->getStyle('A' . $row . ':P' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8F9FA']
                ]
            ]);
        }

        // Color code shipping status
        $shipping_status = $order['shipping_status'] ?? '';
        $status_color = '';
        switch ($shipping_status) {
            case 'delivered':
                $status_color = '90EE90'; // Light green
                break;
            case 'in_transit':
            case 'out_for_delivery':
                $status_color = 'FFE066'; // Light yellow
                break;
            case 'cancelled':
            case 'refused':
            case 'delivery_failed':
                $status_color = 'FFB6C1'; // Light red
                break;
            case 'new':
            case 'pending':
                $status_color = 'ADD8E6'; // Light blue
                break;
        }
        
        if ($status_color) {
            $sheet->getStyle('L' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $status_color]
                ]
            ]);
        }

        $row++;
    }

    // Apply borders to all data
    $last_row = $row - 1;
    $sheet->getStyle('A7:P' . $last_row)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'CCCCCC']
            ]
        ]
    ]);

    // Auto-size columns
    foreach (range('A', 'P') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Set minimum column widths
    $sheet->getColumnDimension('A')->setWidth(15); // Order number
    $sheet->getColumnDimension('B')->setWidth(15); // Tracking
    $sheet->getColumnDimension('C')->setWidth(20); // Customer name
    $sheet->getColumnDimension('D')->setWidth(25); // Email
    $sheet->getColumnDimension('G')->setWidth(30); // Address
    $sheet->getColumnDimension('H')->setWidth(25); // Products

    // Add totals row
    $totals_row = $last_row + 2;
    $sheet->setCellValue('A' . $totals_row, 'TOTAUX');
    $sheet->setCellValue('I' . $totals_row, $total_items);
    $sheet->setCellValue('J' . $totals_row, number_format($total_amount, 2, ',', ' '));

    // Style totals row
    $sheet->getStyle('A' . $totals_row . ':P' . $totals_row)->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D9EDF7']
        ],
        'borders' => [
            'top' => ['borderStyle' => Border::BORDER_THICK]
        ]
    ]);

    // Add legend
    $legend_row = $totals_row + 3;
    $sheet->setCellValue('A' . $legend_row, 'LÉGENDE DES COULEURS:');
    $sheet->getStyle('A' . $legend_row)->applyFromArray(['font' => ['bold' => true]]);

    $legend_items = [
        ['Livré', '90EE90'],
        ['En transit / En livraison', 'FFE066'],
        ['Annulé / Refusé / Échec', 'FFB6C1'],
        ['Nouveau / En attente', 'ADD8E6']
    ];

    $legend_start = $legend_row + 1;
    foreach ($legend_items as $i => $item) {
        $current_row = $legend_start + $i;
        $sheet->setCellValue('A' . $current_row, $item[0]);
        $sheet->getStyle('A' . $current_row)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $item[1]]
            ]
        ]);
    }

    // Generate filename
    $filename = 'Rapport_Commandes_' . date('Y-m-d_H-i-s') . '.xlsx';

    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    // Save and output
    $writer = new Xlsx($spreadsheet);
    
    // Ensure proper output
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    // Clear any output buffers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log the error for debugging
    error_log("Excel export error: " . $e->getMessage());
    
    header('Content-Type: text/html; charset=utf-8');
    echo "Erreur lors de l'exportation Excel : " . $e->getMessage();
    exit;
}

/**
 * Get current user ID from session
 */
function getCurrentUserId($db) {
    if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
        return null;
    }

    $identifier = null;

    // Prefer explicit ID if already stored in session
    if (!empty($_SESSION['user']['id'])) {
        return (int)$_SESSION['user']['id'];
    }

    // Otherwise fallback to whatever is available
    if (!empty($_SESSION['user']['email'])) {
        $identifier = $_SESSION['user']['email'];
        $user = $db->getThisQuery("SELECT * FROM users WHERE email = ? LIMIT 1", [$identifier]);
    } elseif (!empty($_SESSION['user']['username'])) {
        $identifier = $_SESSION['user']['username'];
        $user = $db->getThisQuery("SELECT * FROM users WHERE username = ? LIMIT 1", [$identifier]);
    } elseif (!empty($_SESSION['user']['phone'])) {
        $identifier = $_SESSION['user']['phone'];
        $user = $db->getThisQuery("SELECT * FROM users WHERE phone = ? LIMIT 1", [$identifier]);
    } else {
        return null;
    }

    if ($user && isset($user[0]['id'])) {
        return (int)$user[0]['id'];
    }

    return null;
} ?>