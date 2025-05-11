<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';

// ฟังก์ชันแปลงข้อความเป็น TIS-620
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

        $this->SetFont('THSarabunNew', 'B', 20);
        $this->Cell(0, 10, thai('รายงานแบรนด์สินค้า'), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 14);
        $this->Cell(0, 10, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        $this->SetFont('THSarabunNew', 'B', 16);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(30, 10, thai('รหัสแบรนด์'), 1, 0, 'C', true);
        $this->Cell(100, 10, thai('ชื่อแบรนด์สินค้า'), 1, 0, 'C', true);
        $this->Cell(60, 10, thai('จำนวนสินค้า'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new THPDF();
$pdf->AliasNbPages();
$pdf->AddPage();

try {
    $query = "SELECT 
        b.product_brand_id,
        b.product_brand_name,
        COALESCE(COUNT(p.product_id), 0) as product_count
        FROM product_brand b
        LEFT JOIN product p ON b.product_brand_id = p.product_brand_id
        GROUP BY b.product_brand_id, b.product_brand_name
        ORDER BY b.product_brand_id";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $productbrands = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf->SetFont('THSarabunNew', '', 14);

    $totalProducts = 0;
    $rowCount = 0;

    foreach ($productbrands as $brand) {
        // ตรวจสอบพื้นที่ในหน้านี้พอแสดงอีกแถวหรือไม่
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }

        $pdf->Cell(30, 10, $brand['product_brand_id'], 1, 0, 'C');
        $pdf->Cell(100, 10, thai($brand['product_brand_name']), 1, 0, 'L');
        $pdf->Cell(60, 10, number_format($brand['product_count']) . thai(' รายการ'), 1, 1, 'C');

        $totalProducts += $brand['product_count'];
    }


    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(130, 10, thai('รวมทั้งหมด'), 1, 0, 'R');
    $pdf->Cell(60, 10, number_format($totalProducts) . thai(' รายการ'), 1, 1, 'C');

    $pdf->Ln(10);
    $pdf->Cell(0, 10, thai('สรุปรายงาน'), 0, 1);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->Cell(0, 10, thai('จำนวนแบรนด์สินค้าทั้งหมด: ') . count($productbrands) . thai(' แบรนด์'), 0, 1);
    $pdf->Cell(0, 10, thai('จำนวนสินค้าทั้งหมด: ') . number_format($totalProducts) . thai(' รายการ'), 0, 1);
} catch (PDOException $e) {
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . $e->getMessage(), 0, 1, 'C');
}

$filename = 'ProductbrandReport_' . date('Ymd_His') . '.pdf';
ob_end_clean(); // เคลียร์ output buffer ก่อนแสดง PDF
$pdf->Output('I', $filename);
