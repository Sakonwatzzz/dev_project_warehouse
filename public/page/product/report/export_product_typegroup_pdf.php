<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';
session_start();
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection(); 

// Session timeout handling
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query([
        'timeout' => 1,
        'page' => 'product_typegroup',
        'id' => $_GET['id'] ?? '',
        'search' => $_GET['search'] ?? '',
        'product_type_name' => $_GET['product_type_name'] ?? ''
    ]);
    header("Location: /page/login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// CSRF token verification
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
    die('Invalid CSRF token');
}

function thai($text)
{
    return iconv('UTF-8', 'TIS-620//IGNORE', $text);
}

class THPDF extends FPDF
{
    function Header()
    {
        $this->AddFont('THSarabunNew', '', 'THSarabunNew.php');
        $this->AddFont('THSarabunNew', 'B', 'THSarabunNew_b.php');

        // Title
        $this->SetFont('THSarabunNew', 'B', 22);
        $this->Cell(0, 12, thai('รายงานสินค้า'), 0, 1, 'C');

        // Product type name
        $this->SetFont('THSarabunNew', 'B', 18);
        $productTypeName = $_GET['product_type_name'] ?? 'ไม่ทราบประเภท';
        $this->Cell(0, 10, thai('ประเภท: ') . thai($productTypeName), 0, 1, 'C');

        // Date
        $this->SetFont('THSarabunNew', '', 14);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        

        $this->Ln(2);

        // Table header
        $this->SetFont('THSarabunNew', 'B', 16);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(30, 10, thai('รหัส'), 1, 0, 'C', true);
        $this->Cell(65, 10, thai('ชื่อสินค้า'), 1, 0, 'C', true);
        $this->Cell(19, 10, thai('ราคา 1'), 1, 0, 'C', true);
        $this->Cell(19, 10, thai('ราคา 2'), 1, 0, 'C', true);
        $this->Cell(19, 10, thai('ราคา 3'), 1, 0, 'C', true);
        $this->Cell(19, 10, thai('ราคา 4'), 1, 0, 'C', true);
        $this->Cell(19, 10, thai('ต้นทุน'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    $productTypeId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    if (empty($productTypeId)) {
        throw new Exception('กรุณาระบุประเภทสินค้า');
    }

    // Get product type name
    $stmtType = $pdo->prepare("SELECT product_type_name FROM product_type WHERE product_type_id = :product_type_id");
    $stmtType->bindParam(':product_type_id', $productTypeId, PDO::PARAM_INT);
    $stmtType->execute();
    $typeResult = $stmtType->fetch(PDO::FETCH_ASSOC);
    $productTypeName = $typeResult['product_type_name'] ?? 'ไม่ทราบประเภท';
    $_GET['product_type_name'] = $productTypeName;

    // Query products
    $query = "
        SELECT 
            p.product_id,
            p.product_name,
            pt.product_type_name,
            p.product_price1,
            p.product_price2,
            p.product_price3,
            p.product_price4,
            p.product_capital
        FROM product p
        INNER JOIN product_type pt ON p.product_type_id = pt.product_type_id
        WHERE p.product_type_id = :product_type_id";
    $params = [':product_type_id' => $productTypeId];

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

    if (empty($products)) {
        throw new Exception('ไม่มีข้อมูลสินค้าสำหรับประเภทนี้');
    }

    $pdf = new THPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 14);

    foreach ($products as $product) {
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }
        $pdf->Cell(30, 10, thai($product['product_id']), 1, 0, 'C');
        $pdf->Cell(65, 10, thai($product['product_name']), 1, 0, 'L');
        $pdf->Cell(19, 10, number_format($product['product_price1'], 2), 1, 0, 'R');
        $pdf->Cell(19, 10, number_format($product['product_price2'], 2), 1, 0, 'R');
        $pdf->Cell(19, 10, number_format($product['product_price3'], 2), 1, 0, 'R');
        $pdf->Cell(19, 10, number_format($product['product_price4'], 2), 1, 0, 'R');
        $pdf->Cell(19, 10, number_format($product['product_capital'], 2), 1, 1, 'R');
    }

    // Summary
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(190, 10, thai('รวมทั้งหมด: ') . count($products) . thai(' รายการ'), 1, 1, 'R');

    $pdf->Ln(8);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->MultiCell(0, 8, thai("หมายเหตุ: รายงานนี้จัดทำขึ้นโดยระบบอัตโนมัติเพื่อใช้สำหรับอ้างอิงข้อมูลสินค้าเท่านั้น"), 0, 'L');

    $filename = 'ProductReport_' . htmlspecialchars($productTypeName) . '_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);
} catch (Exception $e) {
    $pdf = new THPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาด: ') . thai(htmlspecialchars($e->getMessage())), 0, 1, 'C');
    $pdf->Output();
}
