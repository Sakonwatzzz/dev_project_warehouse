<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../vendor/autoload.php';
$pdo = Database::getInstance()->getConnection();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

try {
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    $query = "
        SELECT 
            st.store_id,
            st.store_name,
            st.store_create_date
        FROM store st";

    $params = [];
    if ($start_date || $end_date) {
        $query .= " WHERE 1=1";
        if ($start_date) {
            $query .= " AND st.store_create_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND st.store_create_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }

    $query .= " ORDER BY st.store_id";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set report title
    $sheet->setCellValue('A1', 'รายงานข้อมูลร้านค้า (Stores Report)');
    $sheet->mergeCells('A1:C1');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => ['bold' => true, 'size' => 16],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);

    // Report generation date
    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:C2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Date range display
    $date_range = '';
    if ($start_date && $end_date) {
        $date_range = "ช่วงวันที่: $start_date ถึง $end_date";
    } elseif ($start_date) {
        $date_range = "ตั้งแต่วันที่: $start_date";
    } elseif ($end_date) {
        $date_range = "ถึงวันที่: $end_date";
    }
    $sheet->setCellValue('A3', $date_range);
    $sheet->mergeCells('A3:C3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Header row
    $row = 5;
    $headers = ['รหัสร้านค้า', 'ชื่อร้านค้า', 'วันที่สร้าง'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $col++;
    }

    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FFCCE5FF'],
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
    ]);
    $sheet->getRowDimension($row)->setRowHeight(25);

    // Data rows
    $row++;
    $start_data_row = $row;
    if (count($stores) > 0) {
        foreach ($stores as $store) {
            $sheet->setCellValueExplicit('A' . $row, $store['store_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $store['store_name']);
            $sheet->setCellValue('C' . $row, (new DateTime($store['store_create_date']))->format('d/m/Y H:i:s'));
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }
    } else {
        $sheet->setCellValue('A' . $row, 'ไม่พบข้อมูลร้านค้า');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Summary row
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->mergeCells('A' . $row . ':B' . $row);
    $sheet->setCellValue('C' . $row, count($stores) . ' รายการ');
    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);

    // Apply borders
    $sheet->getStyle("A{$start_data_row}:C{$row}")->applyFromArray([
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN],
        ],
    ]);

    // Column widths
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(40);
    $sheet->getColumnDimension('C')->setWidth(25);

    // Output Excel file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="StoresReport_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (PDOException $e) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    $sheet->mergeCells('A1:C1');
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="ErrorReport_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
