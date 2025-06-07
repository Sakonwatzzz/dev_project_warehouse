<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../vendor/autoload.php';

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
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: ../../../../../login.php?timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// CSRF token validation
if (!isset($_GET['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Invalid CSRF token';
    exit;
}

try {
    // Handle search parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $selected_date = isset($_GET['selected_date']) && !empty($_GET['selected_date']) ? trim($_GET['selected_date']) : date('Y-m-d');

    // Build the main query
    $query = "
    SELECT 
        sc.stock_check_id,
        p.product_id,
        p.product_name,
        sc.stock_check_system,
        sc.stock_check_actual,
        scr.stock_check_remark_name AS stock_check_remark,
        w.warehouse_name,
        s.stock_date,
        per.personnel_name
    FROM stock_check sc
    LEFT JOIN product p ON sc.product_id = p.product_id
    LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
    LEFT JOIN stock s ON sc.stock_id = s.stock_id
    LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
    LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
    LEFT JOIN stock_check_remark scr ON sc.stock_check_remark_id = scr.stock_check_remark_id
    WHERE s.check_status_id = 2
    AND DATE(s.stock_date) = :selected_date
    ";
    $params = [':selected_date' => $selected_date];
    if ($search) {
        $query .= " AND (sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    $query .= " ORDER BY s.stock_date DESC";

    // Execute query
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    $sheet->mergeCells('A1:D1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setName('TH SarabunPSK');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4F81BD');
    $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FFFFFFFF');
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Report date
    $sheet->setCellValue('A2', 'วันที่ออกรายงาน ณ เวลา: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:D2');
    $sheet->getStyle('A2')->getFont()->setSize(12)->setName('TH SarabunPSK');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // Personnel name
    if (!empty($stock_checks) && isset($stock_checks[0]['personnel_name'])) {
        $sheet->setCellValue('A3', 'ผู้ตรวจสอบ: ' . $stock_checks[0]['personnel_name']);
        $sheet->mergeCells('A3:D3');
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
        'จำนวนที่นับได้',
        'ส่วนต่าง',
        'หมายเหตุ'
    ];

    $sheet->fromArray($headers, null, 'A5');
    $sheet->getStyle('A5:E5')->getFont()->setBold(true)->setSize(12)->setName('TH SarabunPSK');
    $sheet->getStyle('A5:E5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A5:E5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD3D3D3');
    $sheet->getRowDimension(5)->setRowHeight(25);

    // Data Population
    $row = 6;
    $totals = [
        // 'system' => 0,
        'actual' => 0,
        'negative' => 0,
        'positive' => 0
    ];

    foreach ($stock_checks as $index => $stock) {
        $system_qty = (int) $stock['stock_check_system'];
        $actual_qty = (int) $stock['stock_check_actual'];
        $difference = $actual_qty - $system_qty;

        // Update totals
        $totals['system'] += $system_qty;
        $totals['actual'] += $actual_qty;
        if ($difference < 0) {
            $totals['negative'] += $difference;
        } elseif ($difference > 0) {
            $totals['positive'] += $difference;
        }

        // Handle long product names
        $product_name = $stock['product_name'];
        if (strlen($product_name) > 255) {
            $product_name = substr($product_name, 0, 252) . '...';
        }

        // Format remark
        $remark = !empty($stock['stock_check_remark'])
            ? substr($stock['stock_check_remark'], 0, 255)
            : '-';

        // Format difference with sign
        $diff_text = ($difference > 0 ? '+' : '') . number_format($difference);

        // Set cell values
        $sheet->setCellValueExplicit('A' . $row, $stock['product_id'], DataType::TYPE_STRING);
        $sheet->setCellValue('B' . $row, $product_name);
        // $sheet->setCellValue('C' . $row, number_format($system_qty));
        $sheet->setCellValue('C' . $row, number_format($actual_qty));
        $sheet->setCellValue('D' . $row, $diff_text);
        $sheet->setCellValue('E' . $row, $remark);

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
        // $sheet->setCellValue('C' . $row, number_format($totals['system']));
        $sheet->setCellValue('C' . $row, number_format($totals['actual']));
        $diff_total = ($totals['positive'] > 0 ? '+' : '') . number_format($totals['positive']);
        $sheet->setCellValue('D' . $row, $diff_total);
        $sheet->setCellValue('E' . $row, '');

        $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true)->setSize(12)->setName('TH SarabunPSK');
        $sheet->getStyle('C' . $row . ':D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row . ':E' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6E6FA');
        $sheet->getRowDimension($row)->setRowHeight(25);

        // Conditional formatting for total positive difference
        if ($totals['positive'] > 0) {
            $sheet->getStyle('D' . $row)->getFont()->getColor()->setARGB('FF228B22');
        } elseif ($totals['positive'] == 0) {
            $sheet->getStyle('D' . $row)->getFont()->getColor()->setARGB('FF000000');
        }

        $row++;
        $row++;

        // Summary header
        $sheet->setCellValue('A' . $row, 'สรุปรายงาน');
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14)->setName('TH SarabunPSK');
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4F81BD');
        $sheet->getStyle('A' . $row)->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension($row)->setRowHeight(25);

        $row++;

        // Summary details
        $summary_items = [
            'จำนวนรายการทั้งหมด: ' . count($stock_checks) . ' รายการ',
            // 'จำนวนจากระบบ: ' . number_format($totals['system']),
            'จำนวนนับจริง: ' . number_format($totals['actual']),
            'ส่วนเกินทั้งหมด: ' . ($totals['positive'] > 0 ? '' : '') . number_format($totals['positive']),
            'ส่วนขาดทั้งหมด: ' . ($totals['negative'] > 0 ? '' : '') . number_format($totals['negative'])
        ];

        foreach ($summary_items as $index => $item) {
            $sheet->setCellValue('A' . $row, $item);
            $sheet->mergeCells('A' . $row . ':F' . $row);
            $sheet->getStyle('A' . $row)->getFont()->setSize(12)->setName('TH SarabunPSK');
            $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF5F6FA');
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        // // Show shortage if exists
        // if ($totals['negative'] < 0) {
        //     $sheet->setCellValue('A' . $row, 'ส่วนขาดทั้งหมด: ' . number_format($totals['negative']));
        //     $sheet->mergeCells('A' . $row . ':F' . $row);
        //     $sheet->getStyle('A' . $row)->getFont()->setSize(12)->setName('TH SarabunPSK')->setBold(true);
        //     $sheet->getStyle('A' . $row)->getFont()->getColor()->setARGB('FFDC143C');
        //     $sheet->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF0F0');
        //     $sheet->getRowDimension($row)->setRowHeight(20);
        // }
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
        // 'C' => 15, // stock_check_system
        'C' => 15, // difference
        'D' => 15, // stock_check_actual
        'E' => 25  // stock_check_remark
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
    echo 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage());
    exit;
}
