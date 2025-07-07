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

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'member_group', 'type' => $_GET['type'] ?? ''] + ($_GET ?? []));
    header("Location: /page/login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

try {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }

    $type = isset($_GET['type']) ? trim($_GET['type']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    if (empty($type)) {
        throw new Exception('กรุณาระบุประเภทสมาชิก');
    }

    $query = "
        SELECT 
            m.member_id,
            m.member_name,
            m.member_phone,
            m.member_register_date,
            mt.member_type_name
        FROM member m
        INNER JOIN member_type mt ON m.member_type_id = mt.member_type_id
        WHERE mt.member_type_name = :type";

    $params = [':type' => $type];
    $conditions = [];
    if ($start_date) {
        $conditions[] = "m.member_register_date >= :start_date";
        $params[':start_date'] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $conditions[] = "m.member_register_date <= :end_date";
        $params[':end_date'] = $end_date . ' 23:59:59';
    }
    if ($conditions) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY m.member_id";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->getStyle('A')->getNumberFormat()->setFormatCode('@');

    $sheet->setCellValue('A1', 'รายงานสมาชิก: ' . htmlspecialchars($type));
    $sheet->mergeCells('A1:D1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:D2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

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

    $row = 4;
    $sheet->getRowDimension($row)->setRowHeight(15);
    $row++;

    $sheet->setCellValueExplicit('A' . $row, 'รหัสสมาชิก', DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, 'ชื่อสมาชิก');
    $sheet->setCellValue('C' . $row, 'เบอร์โทร');
    $sheet->setCellValue('D' . $row, 'วันที่ลงทะเบียน');

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
    $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray($headerStyle);
    $sheet->getRowDimension($row)->setRowHeight(20);

    $dataStartRow = $row + 1;

    if (count($members) > 0) {
        foreach ($members as $member) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, $member['member_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $member['member_name']);
            $sheet->setCellValue('C' . $row, $member['member_phone']);
            $sheet->setCellValue('D' . $row, $member['member_register_date']);
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    } else {
        $row++;
        $sheet->setCellValue('A' . $row, 'ไม่พบข้อมูลสมาชิก');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    $row++;
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->mergeCells('A' . $row . ':C' . $row);
    $sheet->setCellValue('D' . $row, count($members) . ' รายการ');
    $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
    $sheet->getRowDimension($row)->setRowHeight(20);

    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
    $sheet->getStyle('A' . $dataStartRow . ':D' . $row)->applyFromArray($styleArray);

    $sheet->getColumnDimension('A')->setWidth(15);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(20);
    $sheet->getColumnDimension('D')->setWidth(25);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="MemberReport_' . htmlspecialchars($type) . '_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()));
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
