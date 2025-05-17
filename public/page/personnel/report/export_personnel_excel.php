<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

try {
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    $query = "SELECT 
        p.personnel_id,
        p.personnel_name,
        p.personnel_username,
        pt.personnel_type_name
        FROM personnel p
        INNER JOIN personnel_type pt ON p.personnel_type_id = pt.personnel_type_id";

    $params = [];
    if ($start_date || $end_date) {
        $query .= " WHERE 1=1";
        if ($start_date) {
            $query .= " AND p.personnel_create_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND p.personnel_create_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }

    $query .= " ORDER BY p.personnel_id";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column A as text
    $sheet->getStyle('A')->getNumberFormat()->setFormatCode('@');

    // Report title
    $sheet->setCellValue('A1', 'รายงานบุคลากร');
    $sheet->mergeCells('A1:D1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    // Report date
    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:D2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    // Date range
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    $date_range = '';
    if ($start_date && $end_date) {
        $date_range = "ช่วงวันที่: $start_date ถึง $end_date";
    } elseif ($start_date) {
        $date_range = "ตั้งแต่วันที่: $start_date";
    } elseif ($end_date) {
        $date_range = "ถึงวันที่: $end_date";
    }
    $sheet->setCellValue('A3', $date_range ? $date_range : '');
    $sheet->mergeCells('A3:D3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(20);

    // Empty row
    $row = 4;
    $sheet->getRowDimension($row)->setRowHeight(15);

    // Table headers
    $row++;
    $sheet->setCellValueExplicit('A' . $row, 'รหัสบุคลากร', DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, 'ชื่อ-นามสกุล');
    $sheet->setCellValue('C' . $row, 'ชื่อผู้ใช้');
    $sheet->setCellValue('D' . $row, 'ประเภทบุคลากร');

    // Header styling
    $headerStyle = [
        'font' => [
            'bold' => true,
        ],
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
    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setRowHeight(20);

    // Data rows
    $dataStartRow = $row + 1;

    if (count($personnel) > 0) {
        foreach ($personnel as $person) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, $person['personnel_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $person['personnel_name']);
            $sheet->setCellValue('C' . $row, $person['personnel_username']);
            $sheet->setCellValue('D' . $row, $person['personnel_type_name']);
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    } else {
        $row++;
        $sheet->setCellValue('A' . $row, 'ไม่พบข้อมูลบุคลากร');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    // Summary row
    $row++;
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->setCellValue('D' . $row, count($personnel) . ' รายการ');
    $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
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
    $sheet->getStyle('A' . $dataStartRow . ':D' . $row)->applyFromArray($styleArray);

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(25);

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="PersonnelReport_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (PDOException $e) {
    // Create error spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage());
    $sheet->mergeCells('A1:D1');
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