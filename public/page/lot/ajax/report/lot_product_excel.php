<?php
require_once __DIR__ . '../../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../../vendor/autoload.php';
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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'lot_productlist', 'lot_id' => $_GET['lot_id'] ?? 0]);
    header("Location: /page/login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Verify CSRF token
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

try {
    // Validate CSRF token
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }

    // Get lot ID and validate
    $lot_id = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : 0;
    $lot_name = "";
    $supplier_name = "";
    if ($lot_id) {
        $stmt = $pdo->prepare("SELECT lot_name, supplier_name FROM lot l LEFT JOIN supplier s ON l.supplier_id = s.supplier_id WHERE l.lot_id = ?");
        $stmt->execute([$lot_id]);
        $lot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lot) {
            throw new Exception('ไม่พบข้อมูลล็อต');
        }
        $lot_name = $lot['lot_name'];
        $supplier_name = $lot['supplier_name'] ?: 'ไม่ระบุผู่ส่งมอบ';
    } else {
        $supplier_name = 'ทุกผู้ส่งมอบ';
    }

    // Handle search and date parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    // Build query
    $query = "
        SELECT
            lp.lot_product_id,
            l.lot_name,
            p.product_name,
            lp.lot_product_qty,
            lp.lot_product_remain,
            lp.lot_product_capital,
            lp.lot_product_date,
            lp.lot_product_status,
            s.supplier_name
        FROM lot_product lp
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN product p ON lp.product_id = p.product_id
        LEFT JOIN supplier s ON l.supplier_id = s.supplier_id";
    $params = [];
    if ($lot_id || !empty($search) || $start_date || $end_date) {
        $query .= " WHERE";
        $conditions = [];
        if ($lot_id) {
            $conditions[] = " lp.lot_id = :lot_id";
            $params[':lot_id'] = $lot_id;
        }
        if (!empty($search)) {
            $conditions[] = " (lp.lot_product_id LIKE :search OR p.product_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $conditions[] = " lp.lot_product_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $conditions[] = " lp.lot_product_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        $query .= " " . implode(" AND ", $conditions);
    }
    $query .= " ORDER BY lp.lot_product_date DESC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $lot_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column A as text
    $sheet->getStyle('A')->getNumberFormat()->setFormatCode('@');

    // Report title
    $sheet->setCellValue('A1', 'รายงานรายการรับสินค้า' . ($lot_id ? ' - Lot: ' . htmlspecialchars($lot_name) : ''));
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // Report date
    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    $sheet->setCellValue('A3', 'ผู้ส่งมอบ: ' . htmlspecialchars($supplier_name));
    $sheet->mergeCells('A3:G3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(20);

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
    $filter_text = !empty($filters) ? implode(', ', $filters) : '';
    $sheet->setCellValue('A4', $filter_text);
    $sheet->mergeCells('A4:G4');
    $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(20);

    // Empty row
    $row = 4;
    $sheet->getRowDimension($row)->setRowHeight(15);

    // Table headers
    $row++;
    $sheet->setCellValueExplicit('A' . $row, 'รหัสสินค้า', DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, 'ชื่อล็อตสินค้า');
    $sheet->setCellValue('C' . $row, 'ชื่อสินค้า');
    $sheet->setCellValue('D' . $row, 'ราคาต้นทุน');
    $sheet->setCellValue('E' . $row, 'จำนวนสินค้าเข้า');
    $sheet->setCellValue('F' . $row, 'จำนวนสินค้าคงเหลือ');
    $sheet->setCellValue('G' . $row, 'วันที่รับสินค้า');

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
    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // Data rows
    $dataStartRow = $row + 1;

    if (count($lot_products) > 0) {
        foreach ($lot_products as $lot_product) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, $lot_product['lot_product_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $lot_product['lot_name']);
            $sheet->setCellValue('C' . $row, $lot_product['product_name']);
            $sheet->setCellValue('D' . $row, $lot_product['lot_product_capital']);
            $sheet->setCellValue('E' . $row, $lot_product['lot_product_qty']);
            $sheet->setCellValue('F' . $row, $lot_product['lot_product_remain']);
            $sheet->setCellValue('G' . $row, $lot_product['lot_product_date']);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    } else {
        $row++;
        $sheet->setCellValue('A' . $row, 'ไม่พบข้อมูลรายการรับสินค้า');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    // Calculate totals
    $total_qty = 0;
    $total_remain = 0;
    $total_capital = 0;
    $total_cost = 0;

    if (count($lot_products) > 0) {
        foreach ($lot_products as $lot_product) {
            $total_qty += (int)$lot_product['lot_product_qty'];
            $total_remain += (int)$lot_product['lot_product_remain'];
            $total_capital += (float)$lot_product['lot_product_capital'];
            $total_cost += (float)($lot_product['lot_product_qty'] * $lot_product['lot_product_capital']);
        }
    }

    // Summary row
    $row++;
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->setCellValue('D' . $row, number_format($total_cost, 2)); // Total cost (qty * capital)
    $sheet->setCellValue('E' . $row, $total_qty); // Total quantity
    $sheet->setCellValue('F' . $row, $total_remain); // Total remaining
    $sheet->setCellValue('G' . $row, count($lot_products) . ' รายการ'); // Total items
    $sheet->mergeCells('A' . $row . ':C' . $row); // Merge A to C
    $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
    $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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
    $sheet->getStyle('A' . $dataStartRow . ':G' . $row)->applyFromArray($styleArray);

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(25);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(25);
    $sheet->getColumnDimension('F')->setWidth(25);
    $sheet->getColumnDimension('G')->setWidth(20);

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="LotProductReport_' . htmlspecialchars($lot_name ?: 'All') . '_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
    // Create error spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()));
    $sheet->mergeCells('A1:G1');
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
