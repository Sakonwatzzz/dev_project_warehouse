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

        $this->SetFont('THSarabunNew', 'B', 18);
        // กำหนดหัวข้อตามโหมด
        $title = isset($_GET['mode']) && $_GET['mode'] === 'incomplete'
            ? 'รายงานสินค้าที่ยังไม่ระบุ'
            : 'รายงานสินค้า';
        $this->Cell(0, 10, thai($title), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        $this->SetFont('THSarabunNew', 'B', 11);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(20, 8, thai('รหัส'), 1, 0, 'C', true);
        $this->Cell(50, 8, thai('ชื่อสินค้า'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('ราคาขาย'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('ราคาทุน'), 1, 0, 'C', true);
        $this->Cell(35, 8, thai('ประเภท'), 1, 0, 'C', true);
        $this->Cell(35, 8, thai('ยี่ห้อ'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 11);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // ฟังก์ชันช่วยคำนวณจำนวนบรรทัดสำหรับ MultiCell
    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += isset($cw[$c]) ? $cw[$c] : 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

$pdf = new THPDF('P'); // ใช้โหมดแนวตั้ง (Portrait)
$pdf->SetMargins(10, 10); // ตั้งระยะขอบ
$pdf->AliasNbPages();
$pdf->AddPage();

try {
    // ตรวจสอบโหมด
    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'complete'; // ค่าเริ่มต้นเป็น complete

    // สร้างคำสั่ง SQL
    $query = "SELECT 
        product.product_id, 
        product.product_name, 
        product.product_price1, 
        product.product_price2, 
        product.product_price3, 
        product.product_price4, 
        product.product_capital, 
        product.product_type_id, 
        product.product_brand_id,
        COALESCE(product_brand.product_brand_name, 'ไม่มีข้อมูล') AS product_brand_name, 
        COALESCE(product_type.product_type_name, 'ไม่มีข้อมูล') AS product_type_name
    FROM product
    LEFT JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id
    LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id";

    // เพิ่มเงื่อนไขตามโหมด
    if ($mode === 'incomplete') {
        // สินค้าที่ยังไม่ระบุ (ข้อมูลไม่ครบถ้วน)
        $query .= " WHERE (
            product.product_name IS NULL OR TRIM(product.product_name) = '' OR 
            product.product_price1 IS NULL OR product.product_price1 = '' OR product.product_price1 = 0 OR 
            product.product_price2 IS NULL OR product.product_price2 = '' OR product.product_price2 = 0 OR 
            product.product_price3 IS NULL OR product.product_price3 = '' OR product.product_price3 = 0 OR 
            product.product_price4 IS NULL OR product.product_price4 = '' OR product.product_price4 = 0 OR 
            product.product_capital IS NULL OR product.product_capital = '' OR product.product_capital = 0 OR 
            product.product_type_id IS NULL OR product.product_type_id = 0 OR 
            product.product_brand_id IS NULL OR product.product_brand_id = 0
        )";
    } else {
        // สินค้าที่ระบุแล้ว (ข้อมูลครบถ้วน)
        $query .= " WHERE 
            product.product_name IS NOT NULL AND TRIM(product.product_name) != '' AND 
            product.product_price1 IS NOT NULL AND product.product_price1 != '' AND product.product_price1 != 0 AND 
            product.product_price2 IS NOT NULL AND product.product_price2 != '' AND product.product_price2 != 0 AND 
            product.product_price3 IS NOT NULL AND product.product_price3 != '' AND product.product_price3 != 0 AND 
            product.product_price4 IS NOT NULL AND product.product_price4 != '' AND product.product_price4 != 0 AND 
            product.product_capital IS NOT NULL AND product.product_capital != '' AND product.product_capital != 0 AND 
            product.product_type_id IS NOT NULL AND product.product_type_id != 0 AND 
            product.product_brand_id IS NOT NULL AND product.product_brand_id != 0";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf->SetFont('THSarabunNew', '', 11);

    $totalProducts = count($products);

    if ($totalProducts > 0) {
        foreach ($products as $product) {
            // คำนวณจำนวนบรรทัดสำหรับคอลัมน์ที่ใช้ MultiCell
            $name_text = $product['product_name'] ?: 'ไม่มีข้อมูล';
            $type_text = $product['product_type_name'];
            $brand_text = $product['product_brand_name'];

            $nb_name = $pdf->NbLines(50, thai($name_text));
            $nb_type = $pdf->NbLines(35, thai($type_text));
            $nb_brand = $pdf->NbLines(35, thai($brand_text));

            // หาจำนวนบรรทัดสูงสุดเพื่อกำหนดความสูงของแถว
            $max_nb = max($nb_name, $nb_type, $nb_brand);
            $row_height = 8 * $max_nb; // ความสูงต่อบรรทัด = 8 มม.

            // ตรวจสอบพื้นที่ในหน้านี้
            if ($pdf->GetY() + $row_height > 270) { // ปรับสำหรับ A4 แนวตั้ง
                $pdf->AddPage();
            }

            // เก็บตำแหน่ง Y ก่อนเริ่มแถว
            $y_start = $pdf->GetY();

            // วาดคอลัมน์ทั้งหมดในแถว
            // คอลัมน์รหัส
            $pdf->Cell(20, $row_height, $product['product_id'], 1, 0, 'C');

            // คอลัมน์ชื่อสินค้า
            $x = $pdf->GetX();
            $pdf->MultiCell(50, 8, thai($name_text), 1, 'L');
            $pdf->SetXY($x + 50, $y_start);

            // คอลัมน์ราคาขาย
            $pdf->Cell(20, $row_height, number_format($product['product_price4'] ?: 0, 2), 1, 0, 'C');

            // คอลัมน์ราคาทุน
            $pdf->Cell(20, $row_height, number_format($product['product_capital'] ?: 0, 2), 1, 0, 'C');

            // คอลัมน์ประเภท
            $x = $pdf->GetX();
            $pdf->MultiCell(35, 8, thai($type_text), 1, 'L');
            $pdf->SetXY($x + 35, $y_start);

            // คอลัมน์ยี่ห้อ
            $x = $pdf->GetX();
            $pdf->MultiCell(35, 8, thai($brand_text), 1, 'L');

            // ย้ายตำแหน่ง Y ไปที่ด้านล่างของแถว
            $pdf->SetY($y_start + $row_height);
        }
    } else {
        $pdf->SetFont('THSarabunNew', 'B', 12);
        $message = $mode === 'incomplete' ? 'ไม่พบสินค้าที่ยังไม่ระบุ' : 'ไม่พบสินค้าที่ระบุแล้ว';
        $pdf->Cell(0, 10, thai($message), 0, 1, 'C');
    }

    // เพิ่มส่วนสรุป
    $pdf->SetFont('THSarabunNew', 'B', 11);
    $pdf->Cell(145, 8, thai('รวมทั้งหมด'), 1, 0, 'R');
    $pdf->Cell(35, 8, number_format($totalProducts) . thai(' รายการ'), 1, 1, 'C');

    $pdf->Ln(10);
    $pdf->Cell(0, 8, thai('สรุปรายงาน'), 0, 1);
    $pdf->SetFont('THSarabunNew', '', 11);
    $pdf->Cell(0, 8, thai('จำนวนสินค้าทั้งหมด: ') . number_format($totalProducts) . thai(' รายการ'), 0, 1);
} catch (PDOException $e) {
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . $e->getMessage(), 0, 1, 'C');
}

// ตั้งชื่อไฟล์ตามโหมด
$filename = $mode === 'incomplete' ? 'IncompleteProductReport_' . date('Ymd_His') . '.pdf' : 'CompleteProductReport_' . date('Ymd_His') . '.pdf';
ob_end_clean(); // เคลียร์ output buffer
$pdf->Output('I', $filename);
