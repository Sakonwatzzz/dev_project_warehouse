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
    header("Location: /login.php?timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Verify CSRF token
if (!isset($_SESSION['csrf_token']) || !isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Invalid CSRF token');
}

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

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
        WHERE s.check_status_id = 2";

    $params = [];
    if ($search || $start_date || $end_date) {
        $query .= " AND 1=1";
        if ($search) {
            $query .= " AND (sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $query .= " AND s.stock_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND s.stock_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }
    $query .= " ORDER BY s.stock_date DESC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column formats
    $sheet->getStyle('A')->getNumberFormat()->setFormatCode('@');
    $sheet->getStyle('D')->getNumberFormat()->setFormatCode('@');

    // Report title
    $sheet->setCellValue('A1', 'รายงานการเช็คสต๊อกสินค้า');
    $sheet->mergeCells('A1:J1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // Report date
    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:J2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // Date range
    $date_range = '';
    if ($start_date && $end_date) {
        $date_range = "ช่วงวันที่: $start_date ถึง $end_date";
    } elseif ($start_date) {
        $date_range = "ตั้งแต่วันที่: $start_date";
    } elseif ($end_date) {
        $date_range = "ถึงวันที่: $end_date";
    }
    $sheet->setCellValue('A3', $date_range ? $date_range : '');
    $sheet->mergeCells('A3:J3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(20);

    // Empty row
    $row = 4;
    $sheet->getRowDimension($row)->setRowHeight(15);

    // Table headers
    $row++;
    $sheet->setCellValueExplicit('A' . $row, 'รหัสการเช็คสต๊อก', DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, 'รหัสสต๊อก');
    $sheet->setCellValue('C' . $row, 'วันที่');
    $sheet->setCellValue('D' . $row, 'คลังสินค้า');
    $sheet->setCellValueExplicit('E' . $row, 'รหัสสินค้า', DataType::TYPE_STRING);
    $sheet->setCellValue('F' . $row, 'ชื่อสินค้า');
    $sheet->setCellValue('G' . $row, 'จำนวนจากระบบ');
    $sheet->setCellValue('H' . $row, 'จำนวนที่นับได้');
    $sheet->setCellValue('I' . $row, 'พนักงาน');
    $sheet->setCellValue('J' . $row, 'สถานะ');
    $sheet->setCellValue('K' . $row, 'หมายเหตุ');

    // Header styling
    $headerStyle = [
        'font' => ['bold' => true],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];
    $sheet->getStyle('A' . $row . ':K' . $row)->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // Data rows
    $dataStartRow = $row + 1;

    if (count($stock_checks) > 0) {
        foreach ($stock_checks as $stock) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, $stock['stock_check_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $stock['stock_id']);
            $sheet->setCellValue('C' . $row, $stock['stock_date']);
            $sheet->setCellValue('D' . $row, $stock['warehouse_name']);
            $sheet->setCellValueExplicit('E' . $row, $stock['product_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('F' . $row, $stock['product_name']);
            $sheet->setCellValue('G' . $row, $stock['stock_check_system']);
            $sheet->setCellValue('H' . $row, $stock['stock_check_actual']);
            $sheet->setCellValue('I' . $row, $stock['personnel_name']);
            $sheet->setCellValue('J' . $row, $stock['check_status_name']);
            $sheet->setCellValue('K' . $row, $stock['stock_check_remark']);
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    } else {
        $row++;
        $sheet->setCellValue('A' . $row, 'ไม่พบข้อมูลการเช็คสต๊อก');
        $sheet->mergeCells('A' . $row . ':K' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    // Summary row
    $row++;
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->mergeCells('A' . $row . ':J' . $row);
    $sheet->setCellValue('K' . $row, count($stock_checks) . ' รายการ');
    $sheet->getStyle('A' . $row . ':K' . $row)->getFont()->setBold(true);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // Apply borders to data and summary
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
    $sheet->getStyle('A' . $dataStartRow . ':K' . $row)->applyFromArray($styleArray);

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

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="StockCheckReport_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    // Create error spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage());
    $sheet->mergeCells('A1:K1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="ErrorReport_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>