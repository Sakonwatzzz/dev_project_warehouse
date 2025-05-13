<?php
require_once __DIR__ . '../../../../../config/database_server.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// รับพารามิเตอร์จาก GET
$start_date = isset($_GET['start_date']) && strtotime($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) && strtotime($_GET['end_date']) ? $_GET['end_date'] : null;
$mode = isset($_GET['mode']) ? htmlspecialchars($_GET['mode']) : 'complete';

try {
    // สร้าง query
    $query = "SELECT 
        product.product_id, 
        product.product_name, 
        product.product_price4, 
        product.product_capital, 
        COALESCE(product_type.product_type_name, 'ไม่มีข้อมูล') AS product_type_name, 
        COALESCE(product_brand.product_brand_name, 'ไม่มีข้อมูล') AS product_brand_name
    FROM product
    LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id
    LEFT JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id
    LEFT JOIN lot_product ON product.product_id = lot_product.product_id
    LEFT JOIN lot ON lot.lot_id = lot_product.lot_id";

    $bind_params = [];
    $where_conditions = [];

    // เงื่อนไขตามโหมด
    if ($mode === 'incomplete') {
        $where_conditions[] = "(
            product.product_name IS NULL OR TRIM(product.product_name) = '' OR 
            product.product_price4 IS NULL OR product.product_price4 = '' OR product.product_price4 = 0 OR 
            product.product_capital IS NULL OR product.product_capital = '' OR product.product_capital = 0 OR 
            product.product_type_id IS NULL OR product.product_type_id = 0 OR 
            product.product_brand_id IS NULL OR product.product_brand_id = 0
        )";
    } else {
        $where_conditions[] = "
            product.product_name IS NOT NULL AND TRIM(product.product_name) != '' AND 
            product.product_price4 IS NOT NULL AND product.product_price4 != '' AND product.product_price4 != 0 AND 
            product.product_capital IS NOT NULL AND product.product_capital != '' AND product.product_capital != 0 AND 
            product.product_type_id IS NOT NULL AND product.product_type_id != 0 AND 
            product.product_brand_id IS NOT NULL AND product.product_brand_id != 0";
    }

    // เงื่อนไขวันที่
    if ($start_date) {
        $where_conditions[] = "lot_product.lot_product_date >= ?";
        $bind_params[] = $start_date;
    }
    if ($end_date) {
        $where_conditions[] = "lot_product.lot_product_date <= ?";
        $bind_params[] = $end_date;
    }

    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(" AND ", $where_conditions);
    }

    $stmt = $pdo->prepare($query);
    foreach ($bind_params as $index => $param) {
        $stmt->bindValue($index + 1, $param, PDO::PARAM_STR);
    }
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // สร้าง Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // ตั้งคอลัมน์ A เป็น Text
    $sheet->getStyle('A')->getNumberFormat()->setFormatCode('@');

    // หัวข้อรายงาน
    $report_title = $mode === 'incomplete' ? 'รายงานสินค้าที่ยังไม่ระบุ' : 'รายงานสินค้า';
    $sheet->setCellValue('A1', $report_title);
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // วันที่ออกรายงาน
    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // แถวว่าง
    $row = 3;
    $sheet->getRowDimension($row)->setRowHeight(15);

    // หัวตาราง
    $row++;
    $sheet->setCellValueExplicit('A' . $row, 'รหัสสินค้า', DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, 'ชื่อสินค้า');
    $sheet->setCellValue('C' . $row, 'ราคาขาย');
    $sheet->setCellValue('D' . $row, 'ราคาทุน');
    $sheet->setCellValue('E' . $row, 'ประเภทสินค้า');
    $sheet->setCellValue('F' . $row, 'ยี่ห้อ');

    // สไตล์หัวตาราง (คัดลอกจาก product_type)
    $headerStyle = [
        'font' => [
            'bold' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'], // เส้นขอบสีดำ
            ],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];
    $sheet->getStyle('A' . $row . ':F' . $row)->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // ข้อมูล
    $totalProducts = 0;
    $dataStartRow = $row + 1;

    if (count($products) > 0) {
        foreach ($products as $product) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, $product['product_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $product['product_name'] ?: 'ไม่มีข้อมูล');
            $sheet->setCellValue('C' . $row, number_format($product['product_price4'] ?: 0, 2));
            $sheet->setCellValue('D' . $row, number_format($product['product_capital'] ?: 0, 2));
            $sheet->setCellValue('E' . $row, $product['product_type_name']);
            $sheet->setCellValue('F' . $row, $product['product_brand_name']);
            $sheet->getRowDimension($row)->setRowHeight(20);
            $totalProducts++;
        }
    } else {
        $row++;
        $message = $mode === 'incomplete' ? 'ไม่พบสินค้าที่ยังไม่ระบุ' : 'ไม่พบสินค้าที่ระบุแล้ว';
        $sheet->setCellValue('A' . $row, $message);
        $sheet->mergeCells('A' . $row . ':F' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    // แถวสรุป (คัดลอกสไตล์จาก product_type)
    $row++;
    $sheet->setCellValue('A' . $row, 'รวมจำนวนสินค้า');
    $sheet->mergeCells('A' . $row . ':E' . $row);
    $sheet->setCellValue('F' . $row, number_format($totalProducts) . ' รายการ');
    $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // ใช้เส้นขอบทุกช่องในตารางข้อมูลและสรุป
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
    $sheet->getStyle('A' . $dataStartRow . ':F' . $row)->applyFromArray($styleArray);

    // กำหนดความกว้างคอลัมน์ (ปรับให้ใกล้เคียง product_type)
    $sheet->getColumnDimension('A')->setWidth(15); // รหัสสินค้า
    $sheet->getColumnDimension('B')->setWidth(40); // ชื่อสินค้า
    $sheet->getColumnDimension('C')->setWidth(20); // ราคาขาย
    $sheet->getColumnDimension('D')->setWidth(20); // ราคาทุน
    $sheet->getColumnDimension('E')->setWidth(25); // ประเภทสินค้า
    $sheet->getColumnDimension('F')->setWidth(25); // ยี่ห้อ

    // ตั้งค่า header สำหรับดาวน์โหลด
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $filename = $mode === 'incomplete' ? 'IncompleteProductReport_' . date('Ymd_His') . '.xlsx' : 'CompleteProductReport_' . date('Ymd_His') . '.xlsx';
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    // สร้าง Spreadsheet สำหรับแสดงข้อผิดพลาด
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage());
    $sheet->mergeCells('A1:F1');
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