<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../vendor/autoload.php';

$pdo = Database::getInstance()->getConnection();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
$start_date = isset($_GET['start_date']) && strtotime($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) && strtotime($_GET['end_date']) ? $_GET['end_date'] : null;
$status = isset($_GET['status']) ? (int)$_GET['status'] : 1;
$date_range = isset($_GET['date_range']) ? htmlspecialchars($_GET['date_range']) : 'this_year';

try {
    $where_conditions = [];
    $params = [];

    if ($search) {
        $where_conditions[] = "(sale.sale_id LIKE ? OR member.member_name LIKE ? OR personnel.personnel_name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if ($date_range === 'today') {
        $where_conditions[] = "DATE(sale.sale_date) = CURDATE()";
    } elseif ($date_range === 'this_month') {
        $where_conditions[] = "YEAR(sale.sale_date) = YEAR(CURDATE()) AND MONTH(sale.sale_date) = MONTH(CURDATE())";
    } elseif ($date_range === 'this_year') {
        $where_conditions[] = "YEAR(sale.sale_date) = YEAR(CURDATE())";
    } elseif ($date_range === 'custom' && $start_date) {
        $where_conditions[] = "DATE(sale.sale_date) >= ?";
        $params[] = $start_date;
    }
    if ($date_range === 'custom' && $end_date) {
        $where_conditions[] = "DATE(sale.sale_date) <= ?";
        $params[] = $end_date;
    }

    if ($status) {
        $where_conditions[] = "sale.sale_status_id = ?";
        $params[] = $status;
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    $query = "SELECT 
                sale.sale_id, 
                sale.sale_date, 
                sale_status.sale_status_name, 
                COALESCE(member.member_name, 'ไม่มีข้อมูล') AS member_name, 
                COALESCE(member.member_phone, 'ไม่มีข้อมูล') AS member_phone, 
                COALESCE(personnel.personnel_name, 'ไม่มีข้อมูล') AS personnel_name, 
                SUM(sale_product.sale_product_qty * sale_product.sale_product_price) AS total_price
            FROM sale
            LEFT JOIN sale_product ON sale.sale_id = sale_product.sale_id
            LEFT JOIN member ON sale.member_id = member.member_id
            LEFT JOIN personnel ON sale.personnel_id = personnel.personnel_id
            LEFT JOIN sale_status ON sale.sale_status_id = sale_status.sale_status_id
            $where_clause
            GROUP BY sale.sale_id
            ORDER BY sale.sale_id ASC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->getStyle('A')->getNumberFormat()->setFormatCode('@');

    $sheet->setCellValue('A1', 'รายงานการขาย');
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    if ($start_date && $end_date) {
        $sheet->setCellValue('A3', 'ช่วงวันที่: ' . date('d/m/Y', strtotime($start_date)) . ' ถึง ' . date('d/m/Y', strtotime($end_date)));
    } elseif ($start_date) {
        $sheet->setCellValue('A3', 'ตั้งแต่วันที่: ' . date('d/m/Y', strtotime($start_date)));
    } elseif ($end_date) {
        $sheet->setCellValue('A3', 'จนถึงวันที่: ' . date('d/m/Y', strtotime($end_date)));
    } else {
        $sheet->setCellValue('A3', 'ข้อมูลทั้งหมด');
    }
    $sheet->mergeCells('A3:G3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(20);

    $row = 4;
    $sheet->setCellValueExplicit('A' . $row, 'รหัสการขาย', DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, 'วันที่ขาย');
    $sheet->setCellValue('C' . $row, 'สถานะ');
    $sheet->setCellValue('D' . $row, 'ชื่อสมาชิก');
    $sheet->setCellValue('E' . $row, 'เบอร์โทร');
    $sheet->setCellValue('F' . $row, 'พนักงาน');
    $sheet->setCellValue('G' . $row, 'ยอดรวม');

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

    $total_sales = 0;
    $total_amount = 0;
    $dataStartRow = $row + 1;

    if (count($sales) > 0) {
        foreach ($sales as $sale) {
            $row++;
            $total_sales++;
            $total_amount += $sale['total_price'];
            $sheet->setCellValueExplicit('A' . $row, $sale['sale_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $sale['sale_date']);
            $sheet->setCellValue('C' . $row, $sale['sale_status_name']);
            $sheet->setCellValue('D' . $row, $sale['member_name']);
            $sheet->setCellValue('E' . $row, $sale['member_phone']);
            $sheet->setCellValue('F' . $row, $sale['personnel_name']);
            $sheet->setCellValue('G' . $row, number_format($sale['total_price'], 2));
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    } else {
        $row++;
        $sheet->setCellValue('A' . $row, 'ไม่พบรายการขาย');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    $row++;
    $sheet->setCellValue('A' . $row, 'รวมจำนวนการขาย');
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->setCellValue('G' . $row, number_format($total_sales) . ' รายการ');
    $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
    $sheet->getRowDimension($row)->setRowHeight(20);

    $row++;
    $sheet->setCellValue('A' . $row, 'รวมยอดขายทั้งหมด');
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->setCellValue('G' . $row, number_format($total_amount, 2) . ' บาท');
    $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
    $sheet->getRowDimension($row)->setRowHeight(20);

    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
    $sheet->getStyle('A' . $dataStartRow . ':G' . $row)->applyFromArray($styleArray);

    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(30);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(20);
    $sheet->getColumnDimension('G')->setWidth(15);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $filename = 'SaleReport_' . date('Ymd_His') . '.xlsx';
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage());
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
?>