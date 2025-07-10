<?php
require_once __DIR__ . '../../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

try {
    // Query to fetch product type data
    $query = "SELECT 
        pt.product_type_id,
        pt.product_type_name,
        COALESCE(COUNT(p.product_id), 0) as product_count
        FROM product_type pt
        LEFT JOIN product p ON pt.product_type_id = p.product_type_id
        GROUP BY pt.product_type_id, pt.product_type_name
        ORDER BY pt.product_type_id";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $producttypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column A as text
    $sheet->getStyle('A')->getNumberFormat()->setFormatCode('@');

    // Report title
    $sheet->setCellValue('A1', 'รายงานประเภทสินค้า');
    $sheet->mergeCells('A1:C1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // Report date
    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:C2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // Empty row
    $row = 3;
    $sheet->getRowDimension($row)->setRowHeight(15);

    // Table headers
    $row++;
    $sheet->setCellValueExplicit('A' . $row, 'รหัสประเภท', DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, 'ชื่อประเภทสินค้า');
    $sheet->setCellValue('C' . $row, 'จำนวนสินค้า');

    // Header styling (simple, bold, with borders)
    $headerStyle = [
        'font' => [
            'bold' => true,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'], // Black border
            ],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ];
    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // Data rows
    $totalProducts = 0;
    $dataStartRow = $row + 1;

    if (count($producttypes) > 0) {
        foreach ($producttypes as $type) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, $type['product_type_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $type['product_type_name']);
            $sheet->setCellValue('C' . $row, number_format($type['product_count']) . ' รายการ');
            $sheet->getRowDimension($row)->setRowHeight(20);
            $totalProducts += $type['product_count'];
        }
    } else {
        $row++;
        $sheet->setCellValue('A' . $row, 'ไม่พบข้อมูลประเภทสินค้า');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    // Summary row
    $row++;
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->setCellValue('C' . $row, number_format($totalProducts) . ' รายการ');
    $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // Apply borders to data and summary
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'], // Black border
            ],
        ],
    ];
    $sheet->getStyle('A' . $dataStartRow . ':C' . $row)->applyFromArray($styleArray);

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15); // รหัสประเภท
    $sheet->getColumnDimension('B')->setWidth(40); // ชื่อประเภทสินค้า
    $sheet->getColumnDimension('C')->setWidth(20); // จำนวนสินค้า

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="ProductTypeReport_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    // Create error spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage());
    $sheet->mergeCells('A1:C1');
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