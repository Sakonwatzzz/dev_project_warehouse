<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../vendor/autoload.php';
session_start();
date_default_timezone_set('Asia/Bangkok');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: /login.php?timeout=1&page=stock_list");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Verify CSRF token
if (!isset($_SESSION['csrf_token']) || !isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Invalid CSRF token');
}

try {
    // Handle search parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Build the query to fetch stock check data
    $query = "
        SELECT 
            sc.stock_check_id,
            s.stock_id,
            s.stock_date,
            w.warehouse_name,
            p.product_id,
            p.product_name,
            sc.stock_check_system,
            sc.stock_check_actual,
            per.personnel_name,
            scs.check_status_name,
            sc.stock_check_remark
        FROM stock_check sc
        LEFT JOIN product p ON sc.product_id = p.product_id
        LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
        LEFT JOIN stock s ON sc.stock_id = s.stock_id
        LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
        LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
        WHERE s.check_status_id = 2
        AND s.stock_date >= :today_start 
        AND s.stock_date <= :today_end";

    $params = [
        ':today_start' => date('Y-m-d 00:00:00'),
        ':today_end' => date('Y-m-d 23:59:59')
    ];

    if ($search) {
        $query .= " AND (sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    $query .= " ORDER BY s.stock_date DESC";

    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Stock Check Report');

    // Set headers
    $headers = [
        'รหัส',
        'รหัสสต๊อก',
        'วันที่',
        'คลังสินค้า',
        'รหัสสินค้า',
        'ชื่อสินค้า',
        'จำนวนจากระบบ',
        'จำนวนที่นับได้',
        'พนักงาน',
        'สถานะ',
        'หมายเหตุ'
    ];
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:K1')->getFont()->setBold(true);
    $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Populate data
    $row = 2;
    foreach ($stock_checks as $stock) {
        $sheet->setCellValue('A' . $row, $stock['stock_check_id']);
        $sheet->setCellValue('B' . $row, $stock['stock_id']);
        $sheet->setCellValue('C' . $row, $stock['stock_date']);
        $sheet->setCellValue('D' . $row, $stock['warehouse_name']);
        $sheet->setCellValue('E' . $row, $stock['product_id']);
        $sheet->setCellValue('F' . $row, $stock['product_name']);
        $sheet->setCellValue('G' . $row, $stock['stock_check_system']);
        $sheet->setCellValue('H' . $row, $stock['stock_check_actual']);
        $sheet->setCellValue('I' . $row, $stock['personnel_name']);
        $sheet->setCellValue('J' . $row, $stock['check_status_name']);
        $sheet->setCellValue('K' . $row, $stock['stock_check_remark']);
        $row++;
    }

    // Add total row
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->mergeCells('A' . $row . ':J' . $row);
    $sheet->setCellValue('K' . $row, count($stock_checks) . ' รายการ');
    $sheet->getStyle('A' . $row . ':K' . $row)->getFont()->setBold(true);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // Apply borders
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
    $sheet->getStyle('A1:K' . $row)->applyFromArray($styleArray);

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(30);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(15);
    $sheet->getColumnDimension('I')->setWidth(20);
    $sheet->getColumnDimension('J')->setWidth(15);
    $sheet->getColumnDimension('K')->setWidth(30);

    // Send headers to download the file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="StockCheckReport_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Save file to PHP output
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage());
    exit;
}
?>