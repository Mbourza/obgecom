<?php

if(file_exists(stream_resolve_include_path("../config/only.php"))) {
    require_once("../config/only.php");
}

require_once '../vendor/autoload.php';
require_once '../classes/Config.php';
require_once '../classes/DB.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

$db = DB::getInstance();
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    die('Unauthorized - Please log in');
}

$user_id = getCurrentUserId($db);

if (!$user_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    
    if (!$user_id) {
        throw new Exception("Unable to determine user ID");
    }

    $filters = $_POST;

    // Build query
    $query = "SELECT o.*, s.storeName as store_name 
              FROM orders o 
              LEFT JOIN stores s ON o.store_id = s.id 
              WHERE o.user_id = ?";
    
    $params = [$user_id];
    $conditions = [];

    // Apply filters
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

    $orders = $db->getThisQuery($query, $params);

    if (empty($orders)) {
        throw new Exception("No orders found with the selected filters");
    }

    // Create spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Orders Export');

    // Add title row
    $sheet->mergeCells('A1:O1');
    $sheet->setCellValue('A1', 'ORDERS EXPORT REPORT');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 16,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2C3E50']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Add export info row
    $sheet->mergeCells('A2:O2');
    $exportInfo = 'Generated: ' . date('F d, Y H:i:s') . ' | Total Orders: ' . count($orders);
    $sheet->setCellValue('A2', $exportInfo);
    $sheet->getStyle('A2')->applyFromArray([
        'font' => [
            'italic' => true,
            'size' => 10,
            'color' => ['rgb' => '7F8C8D']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ]
    ]);

    // Headers
    $headers = [
        'Order Number', 'Tracking', 'Customer', 'Email', 'Phone',
        'City', 'Address', 'Products', 'Quantity', 'Amount',
        'Currency', 'Shipping Status', 'Confirmation Status', 'Store', 'Order Date'
    ];

    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '3', $header);
        $col++;
    }

    // Style header row
    $sheet->getStyle('A3:O3')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 11,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '3498DB']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '2C3E50']
            ]
        ]
    ]);
    $sheet->getRowDimension(3)->setRowHeight(25);

    // Add data with alternating row colors
    $row = 4;
    foreach ($orders as $order) {
        $sheet->setCellValue('A' . $row, $order['order_number'] ?? '');
        $sheet->setCellValue('B' . $row, $order['tracking_number'] ?? '');
        $sheet->setCellValue('C' . $row, $order['customer_name'] ?? '');
        $sheet->setCellValue('D' . $row, $order['customer_email'] ?? '');
        $sheet->setCellValue('E' . $row, $order['customer_phone'] ?? '');
        $sheet->setCellValue('F' . $row, $order['customer_ville'] ?? '');
        $sheet->setCellValue('G' . $row, $order['shipping_address'] ?? '');
        $sheet->setCellValue('H' . $row, $order['products'] ?? '');
        $sheet->setCellValue('I' . $row, $order['item_count'] ?? 0);
        $sheet->setCellValue('J' . $row, $order['total_amount'] ?? 0);
        $sheet->setCellValue('K' . $row, $order['currency'] ?? '');
        $sheet->setCellValue('L' . $row, $order['shipping_status'] ?? '');
        $sheet->setCellValue('M' . $row, $order['status'] ?? '');
        $sheet->setCellValue('N' . $row, $order['store_name'] ?? '');
        $sheet->setCellValue('O' . $row, $order['order_date'] ?? '');

        // Alternating row colors
        $fillColor = ($row % 2 == 0) ? 'ECF0F1' : 'FFFFFF';
        $sheet->getStyle('A' . $row . ':O' . $row)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $fillColor]
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'BDC3C7']
                ]
            ]
        ]);

        // Center align quantity and amount columns
        $sheet->getStyle('I' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Format amount as number with 2 decimals
        $sheet->getStyle('J' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        // Color code shipping status
        $shippingStatus = strtolower($order['shipping_status'] ?? '');
        if (in_array($shippingStatus, ['delivered', 'shipped'])) {
            $sheet->getStyle('L' . $row)->getFont()->getColor()->setRGB('27AE60');
        } elseif (in_array($shippingStatus, ['pending', 'processing'])) {
            $sheet->getStyle('L' . $row)->getFont()->getColor()->setRGB('F39C12');
        } elseif (in_array($shippingStatus, ['cancelled', 'returned'])) {
            $sheet->getStyle('L' . $row)->getFont()->getColor()->setRGB('E74C3C');
        }

        // Color code confirmation status
        $confirmStatus = strtolower($order['status'] ?? '');
        if ($confirmStatus === 'confirmed') {
            $sheet->getStyle('M' . $row)->getFont()->getColor()->setRGB('27AE60');
        } elseif ($confirmStatus === 'pending') {
            $sheet->getStyle('M' . $row)->getFont()->getColor()->setRGB('F39C12');
        } elseif (in_array($confirmStatus, ['cancelled', 'rejected'])) {
            $sheet->getStyle('M' . $row)->getFont()->getColor()->setRGB('E74C3C');
        }

        $row++;
    }

    // Add summary row
    $lastRow = $row;
    $sheet->mergeCells('A' . $lastRow . ':H' . $lastRow);
    $sheet->setCellValue('A' . $lastRow, 'TOTAL:');
    $sheet->setCellValue('I' . $lastRow, '=SUM(I4:I' . ($lastRow - 1) . ')');
    $sheet->setCellValue('J' . $lastRow, '=SUM(J4:J' . ($lastRow - 1) . ')');
    
    $sheet->getStyle('A' . $lastRow . ':O' . $lastRow)->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 11
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'D5DBDB']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_RIGHT
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_MEDIUM,
                'color' => ['rgb' => '2C3E50']
            ]
        ]
    ]);
    $sheet->getStyle('I' . $lastRow . ':J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('J' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');

    // Set column widths
    $columnWidths = [
        'A' => 15, 'B' => 15, 'C' => 20, 'D' => 25, 'E' => 15,
        'F' => 15, 'G' => 30, 'H' => 35, 'I' => 10, 'J' => 12,
        'K' => 10, 'L' => 15, 'M' => 18, 'N' => 20, 'O' => 18
    ];
    
    foreach ($columnWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    // Enable text wrapping for long content
    $sheet->getStyle('G4:H' . ($lastRow - 1))->getAlignment()->setWrapText(true);

    // Freeze header rows
    $sheet->freezePane('A4');

    // Set print settings
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
    $sheet->getPageSetup()->setFitToWidth(1);
    $sheet->getPageSetup()->setFitToHeight(0);

    // Set headers BEFORE any output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="orders_export_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Save to output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo "Export failed: " . $e->getMessage();
    exit;
}

/**
 * Get current user ID from session
*/
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
?>