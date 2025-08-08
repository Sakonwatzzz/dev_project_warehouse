<?php
require_once __DIR__ . '/../../../../../config/database_server.php';
require_once __DIR__ . '/../../../../../vendor/autoload.php';
ob_start();
session_start();
date_default_timezone_set('Asia/Bangkok');

$pdo = Database::getInstance()->getConnection(); 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// Create CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'supplier_lot', 'supplier_id' => $_GET['supplier_id'] ?? 0]);
    header("Location: /page/login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        throw new Exception("Database error: " . htmlspecialchars($e->getMessage()));
    }
}

try {
    // Validate CSRF token
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }

    // Get supplier ID and validate
    $supplier_id = isset($_GET['supplier_id']) ? filter_var($_GET['supplier_id'], FILTER_VALIDATE_INT) : 0;
    if (!$supplier_id) {
        throw new Exception('กรุณาระบุรหัสซัพพลายเออร์');
    }

    // Fetch supplier information
    $stmt = fetchData($pdo, "SELECT supplier_name FROM supplier WHERE supplier_id = :supplier_id", [':supplier_id' => $supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        throw new Exception('ไม่พบข้อมูลซัพพลายเออร์');
    }

    // Handle search and date parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    // Build query
    $query = "
        SELECT
            l.lot_id,
            l.lot_detail,
            l.lot_date,
            ls.lot_status_name,
            w.warehouse_name
        FROM lot l
        LEFT JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
        LEFT JOIN warehouse w ON l.warehouse_id = w.warehouse_id
        WHERE l.supplier_id = :supplier_id";
    $params = [':supplier_id' => $supplier_id];

    if (!empty($search)) {
        $query .= " AND (l.lot_id LIKE :search OR l.lot_detail LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date) {
        $query .= " AND l.lot_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $query .= " AND l.lot_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }

    $query .= " ORDER BY l.lot_date DESC";

    $stmt = fetchData($pdo, $query, $params);
    $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column A as text
    $sheet->getStyle('A')->getNumberFormat()->setFormatCode('@');

    // Report title
    $sheet->setCellValue('A1', 'รายงานล็อตสินค้าจาก: ' . htmlspecialchars($supplier['supplier_name']));
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // Report date
    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:E2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // Date range and search
    $filters = [];
    if ($search) $filters[] = "ค้นหา: $search";
    if ($start_date && $end_date) {
        $filters[] = "ช่วงวันที่: $start_date ถึง $end_date";
    } elseif ($start_date) {
        $filters[] = "ตั้งแต่วันที่: $start_date";
    } elseif ($end_date) {
        $filters[] = "ถึงวันที่: $end_date";
    }
    $filter_text = !empty($filters) ? implode(', ', $filters) : 'ทั้งหมด';
    $sheet->setCellValue('A3', $filter_text);
    $sheet->mergeCells('A3:E3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(20);

    // Empty row
    $row = 4;
    $sheet->getRowDimension($row)->setRowHeight(15);

    // Table headers
    $row++;
    $sheet->setCellValueExplicit('A' . $row, 'รหัสล็อต', DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, 'วันที่และเวลา');
    $sheet->setCellValue('C' . $row, 'คลังสินค้า');
    $sheet->setCellValue('D' . $row, 'รายละเอียด');
    $sheet->setCellValue('E' . $row, 'สถานะ');

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
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // Data rows
    $dataStartRow = $row + 1;

    if (count($lots) > 0) {
        foreach ($lots as $lot) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, $lot['lot_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $lot['lot_date']);
            $sheet->setCellValue('C' . $row, $lot['warehouse_name']);
            $sheet->setCellValue('D' . $row, $lot['lot_detail']);
            $sheet->setCellValue('E' . $row, $lot['lot_status_name']);
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    } else {
        $row++;
        $sheet->setCellValue('A' . $row, 'ไม่พบข้อมูลล็อต');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    // Summary row
    $row++;
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->mergeCells('A' . $row . ':D' . $row);
    $sheet->setCellValue('E' . $row, count($lots) . ' รายการ');
    $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
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
    $sheet->getStyle('A' . $dataStartRow . ':E' . $row)->applyFromArray($styleArray);

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(25);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(30);
    $sheet->getColumnDimension('E')->setWidth(20);

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="SupplierLotReport_' . htmlspecialchars($supplier['supplier_name']) . '_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    ob_end_clean();
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
    // Create error spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()));
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="ErrorReport_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    ob_end_clean();
    $writer->save('php://output');
    exit;
}