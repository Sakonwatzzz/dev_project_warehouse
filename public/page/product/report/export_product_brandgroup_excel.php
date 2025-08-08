<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../vendor/autoload.php';
session_start();
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection(); 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'product_brandgroup', 'id' => $_GET['id'] ?? ''] + ($_GET ?? []));
    header("Location: /page/login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

try {
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }

    $productBrandId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    if (empty($productBrandId)) {
        throw new Exception('กรุณาระบุแบรนด์สินค้า');
    }

    // Fetch brand name
    $stmtBrand = $pdo->prepare("SELECT product_brand_name FROM product_brand WHERE product_brand_id = :product_brand_id");
    $stmtBrand->bindParam(':product_brand_id', $productBrandId, PDO::PARAM_INT);
    $stmtBrand->execute();
    $brandResult = $stmtBrand->fetch(PDO::FETCH_ASSOC);
    $productBrandName = $brandResult['product_brand_name'] ?? 'ไม่ทราบแบรนด์';

    // Build query
    $query = "
        SELECT 
            p.product_id,
            p.product_name,
            p.product_price1,
            p.product_price2,
            p.product_price3,
            p.product_price4,
            p.product_capital,
            pb.product_brand_name
        FROM product p
        INNER JOIN product_brand pb ON p.product_brand_id = pb.product_brand_id
        WHERE p.product_brand_id = :product_brand_id";
    $params = [':product_brand_id' => $productBrandId];

    if (!empty($search)) {
        $query .= " AND (p.product_id LIKE :search OR p.product_name LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $query .= " ORDER BY p.product_name";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->getStyle('A')->getNumberFormat()->setFormatCode('@');

    $sheet->setCellValue('A1', 'รายงานสินค้า: ' . htmlspecialchars($productBrandName));
    $sheet->mergeCells('A1:G1');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true);
    $sheet->getRowDimension(1)->setRowHeight(25);

    $sheet->setCellValue('A2', 'วันที่ออกรายงาน: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:G2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(2)->setRowHeight(20);

    $filter_text = $search ? "ค้นหา: $search" : '';
    $sheet->setCellValue('A3', $filter_text);
    $sheet->mergeCells('A3:G3');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension(3)->setRowHeight(20);

    $row = 4;
    $sheet->getRowDimension($row)->setRowHeight(15);

    $row++;
    $sheet->setCellValueExplicit('A' . $row, 'รหัสสินค้า', DataType::TYPE_STRING);
    $sheet->setCellValue('B' . $row, 'ชื่อสินค้า');
    $sheet->setCellValue('C' . $row, 'ราคา 1');
    $sheet->setCellValue('D' . $row, 'ราคา 2');
    $sheet->setCellValue('E' . $row, 'ราคา 3');
    $sheet->setCellValue('F' . $row, 'ราคา 4');
    $sheet->setCellValue('G' . $row, 'ต้นทุน');

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

    $dataStartRow = $row + 1;

    if (count($products) > 0) {
        foreach ($products as $product) {
            $row++;
            $sheet->setCellValueExplicit('A' . $row, $product['product_id'], DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $row, $product['product_name']);
            $sheet->setCellValue('C' . $row, number_format($product['product_price1'], 2));
            $sheet->setCellValue('D' . $row, number_format($product['product_price2'], 2));
            $sheet->setCellValue('E' . $row, number_format($product['product_price3'], 2));
            $sheet->setCellValue('F' . $row, number_format($product['product_price4'], 2));
            $sheet->setCellValue('G' . $row, number_format($product['product_capital'], 2));
            $sheet->getRowDimension($row)->setRowHeight(20);
        }
    } else {
        $row++;
        $sheet->setCellValue('A' . $row, 'ไม่พบข้อมูลสินค้า');
        $sheet->mergeCells('A' . $row . ':G' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(20);
    }

    $row++;
    $sheet->setCellValue('A' . $row, 'รวมทั้งหมด');
    $sheet->mergeCells('A' . $row . ':F' . $row);
    $sheet->setCellValue('G' . $row, count($products) . ' รายการ');
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
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(15);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="ProductReport_' . htmlspecialchars($productBrandName) . '_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} catch (Exception $e) {
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