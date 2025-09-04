<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../vendor/autoload.php';
$pdo = Database::getInstance()->getConnection();
session_start();
date_default_timezone_set('Asia/Bangkok');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// Session timeout check
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {
    session_unset();
    session_destroy();
    header("Location: ../../../../../login.php?timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Verify CSRF token
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || !isset($data['csrf_token']) || !verifyCsrfToken($data['csrf_token'])) {
    error_log("CSRF Validation Failed: Session Token: " . ($_SESSION['csrf_token'] ?? 'unset') . ", POST Token: " . ($data['csrf_token'] ?? 'unset'));
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Handle search parameters
$search = isset($data['search']) ? trim($data['search']) : '';
$start_date = isset($data['start_date']) ? htmlspecialchars($data['start_date']) : '';
$end_date = isset($data['end_date']) ? htmlspecialchars($data['end_date']) : '';
$date_range = isset($data['date_range']) ? htmlspecialchars($data['date_range']) : 'this_year';

if ($date_range === 'custom' && empty($start_date)) {
    $start_date = date('Y-m-d');
}
if ($date_range === 'custom' && empty($end_date)) {
    $end_date = date('Y-m-d');
}

// Validate date format
if ($date_range === 'custom' && $start_date && $end_date) {
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        if ($start > $end) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'วันที่เริ่มต้นต้องไม่มากกว่าวันที่สิ้นสุด']);
            exit;
        }
    } catch (Exception $e) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'รูปแบบวันที่ไม่ถูกต้อง']);
        exit;
    }
}

// Build WHERE conditions and parameters
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search OR per.personnel_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($date_range === 'today') {
    $where_conditions[] = "DATE(s.stock_date) = CURDATE()";
} elseif ($date_range === 'this_month') {
    $where_conditions[] = "YEAR(s.stock_date) = YEAR(CURDATE()) AND MONTH(s.stock_date) = MONTH(CURDATE())";
} elseif ($date_range === 'this_year') {
    $where_conditions[] = "YEAR(s.stock_date) = YEAR(CURDATE())";
} elseif ($date_range === 'custom' && $start_date && $end_date) {
    $where_conditions[] = "DATE(s.stock_date) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}

$where_clause = !empty($where_conditions) ? 'WHERE s.check_status_id = 2 AND ' . implode(' AND ', $where_conditions) : 'WHERE s.check_status_id = 2';

try {
    // Build the main query
    $query = "
        SELECT 
            sc.stock_check_id,
            sc.product_id AS stock_product_id,
            p.product_id AS product_table_id,
            COALESCE(p.product_name, 'ไม่ระบุ') AS product_name,
            sc.stock_check_system,
            sc.stock_check_actual,
            sc.stock_check_remark_id,
            scr.stock_check_remark_name,
            w.warehouse_name,
            s.stock_date,
            per.personnel_name
        FROM stock_check sc
        INNER JOIN product p ON sc.product_id = p.product_id
        LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
        LEFT JOIN stock s ON sc.stock_id = s.stock_id
        LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
        LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
        LEFT JOIN stock_check_remark scr ON sc.stock_check_remark_id = scr.stock_check_remark_id
        $where_clause
        ORDER BY s.stock_date DESC";

    // Execute query
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log query and results for debugging
    error_log("Excel Export Query: $query with params: " . json_encode($params));
    error_log("Excel Export Results: " . json_encode(array_map(function ($row) {
        return [
            'stock_check_id' => $row['stock_check_id'],
            'stock_product_id' => $row['stock_product_id'],
            'product_table_id' => $row['product_table_id'],
            'product_name' => $row['product_name']
        ];
    }, $stock_checks)));

    // Check if query returned results
    if (!$stock_checks) {
        $stock_checks = [];
        error_log("No data returned from stock check query. Parameters: " . json_encode($params));
    }

    // Excel Spreadsheet Creation
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Stock Check Report');

    // Freeze the header row
    $sheet->freezePane('A6');

    // Report Header Section
    $warehouse_title = !empty($stock_checks) && isset($stock_checks[0]['warehouse_name'])
        ? ': ' . $stock_checks[0]['warehouse_name']
        : '';

    $sheet->setCellValue('A1', 'รายงานการเช็คสต๊อกสินค้า' . $warehouse_title);
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setName('TH SarabunPSK');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4F81BD');
    $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Report date
    $sheet->setCellValue('A2', 'วันที่ออกรายงาน ณ เวลา: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getFont()->setSize(12)->setName('TH SarabunPSK');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // Personnel name
    if (!empty($stock_checks) && isset($stock_checks[0]['personnel_name'])) {
        $sheet->setCellValue('A3', 'ผู้ตรวจสอบ: ' . $stock_checks[0]['personnel_name']);
        $sheet->mergeCells('A3:F3');
        $sheet->getStyle('A3')->getFont()->setSize(12)->setName('TH SarabunPSK');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(3)->setRowHeight(20);
    }

    // Add empty row
    $sheet->getRowDimension(4)->setRowHeight(10);

    // Table Headers
    $headers = [
        'รหัสสินค้า',
        'ชื่อสินค้า',
        'จำนวนจากระบบ',
        'จำนวนที่นับได้',
        'ส่วนต่าง',
        'หมายเหตุ'
    ];

    $sheet->fromArray($headers, null, 'A5');
    $sheet->getStyle('A5:F5')->getFont()->setBold(true)->setSize(12)->setName('TH SarabunPSK');
    $sheet->getStyle('A5:F5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A5:F5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD3D3D3');
    $sheet->getRowDimension(5)->setRowHeight(25);

    // Data Population
    $row = 6;
    $totals = [
        'system' => 0,
        'actual' => 0,
        'negative' => 0,
        'positive' => 0
    ];

    foreach ($stock_checks as $index => $stock) {
        $system_qty = (int) ($stock['stock_check_system'] ?? 0);
        $actual_qty = (int) ($stock['stock_check_actual'] ?? 0);
        $difference = $actual_qty - $system_qty;

        // Update totals
        $totals['system'] += $system_qty;
        $totals['actual'] += $actual_qty;
        if ($difference < 0) {
            $totals['negative'] += $difference;
        } elseif ($difference > 0) {
            $totals['positive'] += $difference;
        }

        // Use stock_product_id from stock_check
        $product_id = $stock['stock_product_id'] ?? 'N/A';
        $product_name = $stock['product_name'];

        // Format remark
        $remark = !empty($stock['stock_check_remark_name'])
            ? mb_substr($stock['stock_check_remark_name'], 0, 50, 'UTF-8')
            : '-';

        // Format difference with sign
        $diff_text = ($difference > 0 ? '+' : '') . number_format($difference);

        // Set cell values
        $sheet->setCellValueExplicit('A' . $row, $product_id, DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $row, $product_name);
        $sheet->setCellValue('C' . $row, number_format($system_qty));
        $sheet->setCellValue('D' . $row, number_format($actual_qty));
        $sheet->setCellValue('E' . $row, $diff_text);
        $sheet->setCellValue('F' . $row, $remark);

        // Apply word wrap
        $sheet->getStyle('B' . $row)->getAlignment()->setWrapText(true);
        $sheet->getStyle('F' . $row)->getAlignment()->setWrapText(true);

        // Center-align numeric columns
        $sheet->getStyle('C' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Conditional formatting for differences
        if ($difference < 0) {
            $sheet->getStyle('E' . $row)
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setARGB('FFDC143C');
        } elseif ($difference > 0) {
            $sheet->getStyle('E' . $row)
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setARGB('FF228B22');
        }

        // Alternating row colors
        $rowColor = ($index % 2 == 0) ? 'FFF5F6FA' : 'FFFFFFFF';
        $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($rowColor);

        $sheet->getRowDimension($row)->setRowHeight(25);
        $row++;
    }

    // Summary Section
    if (!empty($stock_checks)) {
        // Add spacing
        $sheet->getRowDimension($row)->setRowHeight(10);
        $row++;

        // Total row
        $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $sheet->setCellValue('C' . $row, number_format($totals['system']));
        $sheet->setCellValue('D' . $row, number_format($totals['actual']));
        $diff_total = ($totals['positive'] > 0 ? '+' : '') . number_format($totals['positive']);
        $sheet->setCellValue('E' . $row, $diff_total);
        $sheet->setCellValue('F' . $row, '');

        $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true)->setSize(12)->setName('TH SarabunPSK');
        $sheet->getStyle('C' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row . ':F' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6E6FA');
        $sheet->getRowDimension($row)->setRowHeight(25);

        // Conditional formatting for total positive difference
        if ($totals['positive'] > 0) {
            $sheet->getStyle('E' . $row)->getFont()->getColor()->setARGB('FF228B22');
        } elseif ($totals['positive'] < 0) {
            $sheet->getStyle('E' . $row)->getFont()->getColor()->setARGB('FFDC143C');
        }

        $row++;
        $row++;

        // Summary header
        $sheet->setCellValue('A' . $row, 'สรุปรายงาน');
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14)->setName('TH SarabunPSK');
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4F81BD');
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension($row)->setRowHeight(25);

        $row++;

        // Summary details
        $summary_items = [
            'จำนวนรายการสินค้าตรวจสอบทั้งหมด: ' . count($stock_checks) . ' รายการ',
            'จำนวนสินค้าคงเหลือจากรายการที่ตรวจสอบจากระบบ: ' . number_format($totals['system']) . ' ชิ้น',
            'จำนวนสินค้าที่นับได้จริงรวมทั้งหมด: ' . number_format($totals['actual']) . ' ชิ้น',
            'จำนวนสินค้าที่เกินรวมทั้งหมด: ' . ($totals['positive'] > 0 ? '+' : '') . number_format($totals['positive']) . ' ชิ้น',
            'จำนวนสินค้าที่ขาดรวมทั้งหมด: ' . number_format(abs($totals['negative'])) . ' ชิ้น'
        ];

        foreach ($summary_items as $item) {
            $sheet->setCellValue('A' . $row, $item);
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setSize(12)->setName('TH SarabunPSK');
            $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF5F6FA');
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }
    } else {
        // No data message
        $sheet->setCellValue('A' . $row, 'ไม่พบข้อมูลการเช็คสต๊อก');
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12)->setName('TH SarabunPSK');
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setARGB('FF8B0000');
        $row++;
    }

    // Styling and Formatting
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
    $sheet->getStyle('A5:F' . ($row - 1))->applyFromArray($styleArray);

    // Set column widths
    $column_widths = [
        'A' => 15, // product_id
        'B' => 40, // product_name
        'C' => 15, // stock_check_system
        'D' => 15, // stock_check_actual
        'E' => 15, // difference
        'F' => 25  // stock_check_remark
    ];

    foreach ($column_widths as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }

    // Add footer
    $row += 2;
    $sheet->setCellValue('A' . $row, 'สร้างโดยระบบจัดการสต๊อก');
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->getStyle('A' . $row)->getFont()->setSize(10)->setName('TH SarabunPSK')->setItalic(true);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // File Output
    $filename = 'StockCheckReport_' . date('Ymd_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    ob_end_clean();
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
    error_log("Error in export_stock_excel.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage())]);
    exit;
}
