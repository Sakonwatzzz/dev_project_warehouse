<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';
$pdo = Database::getInstance()->getConnection(); 

// ฟังก์ชันแปลงข้อความเป็น TIS-620
function thai($text)
{
    return iconv('UTF-8', 'TIS-620//IGNORE', $text);
}

class THPDF extends FPDF
{
    protected $start_date;
    protected $end_date;
    protected $mode;

    // Constructor รับพารามิเตอร์วันที่และโหมด
    function __construct($start_date = null, $end_date = null, $mode = 'complete')
    {
        parent::__construct('P');
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->mode = $mode;
    }

    function Header()
    {
        $this->AddFont('THSarabunNew', '', 'THSarabunNew.php');
        $this->AddFont('THSarabunNew', 'B', 'THSarabunNew_b.php');

        // หัวข้อตามโหมด
        $this->SetFont('THSarabunNew', 'B', 18);
        $title = $this->mode === 'incomplete' ? 'รายงานสินค้าที่ยังไม่ระบุ' : 'รายงานสินค้า';
        $this->Cell(0, 10, thai($title), 0, 1, 'C');

        // วันที่ออกรายงานและช่วงวันที่
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        // แสดงช่วงวันที่
        if ($this->start_date && $this->end_date) {
            $this->Cell(0, 8, thai('ช่วงวันที่: ') . date('d/m/Y', strtotime($this->start_date)) . thai(' ถึง ') . date('d/m/Y', strtotime($this->end_date)), 0, 1, 'C');
        } elseif ($this->start_date) {
            $this->Cell(0, 8, thai('ตั้งแต่วันที่: ') . date('d/m/Y', strtotime($this->start_date)), 0, 1, 'C');
        } elseif ($this->end_date) {
            $this->Cell(0, 8, thai('จนถึงวันที่: ') . date('d/m/Y', strtotime($this->end_date)), 0, 1, 'C');
        } else {
            $this->Cell(0, 8, thai('ข้อมูลทั้งหมด'), 0, 1, 'C');
        }

        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        // หัวตาราง
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

// รับพารามิเตอร์จาก GET
$start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : null;
$mode = isset($_GET['mode']) ? htmlspecialchars($_GET['mode']) : 'complete';

// สร้าง PDF พร้อมส่งวันที่และโหมด
$pdf = new THPDF($start_date, $end_date, $mode);
$pdf->SetMargins(10, 10);
$pdf->AliasNbPages();
$pdf->AddPage();

try {
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
    LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id
    LEFT JOIN lot_product ON product.product_id = lot_product.product_id
    LEFT JOIN lot ON lot.lot_id = lot_product.lot_id
    LEFT JOIN personnel ON lot.personnel_id = personnel.personnel_id";

    // ตัวแปรสำหรับ bind parameters
    $bind_params = [];

    // เพิ่มเงื่อนไข WHERE
    $where_conditions = [];

    // เงื่อนไขตามโหมด
    if ($mode === 'incomplete') {
        $where_conditions[] = "(
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
        $where_conditions[] = "
            product.product_name IS NOT NULL AND TRIM(product.product_name) != '' AND 
            product.product_price1 IS NOT NULL AND product.product_price1 != '' AND product.product_price1 != 0 AND 
            product.product_price2 IS NOT NULL AND product.product_price2 != '' AND product.product_price2 != 0 AND 
            product.product_price3 IS NOT NULL AND product.product_price3 != '' AND product.product_price3 != 0 AND 
            product.product_price4 IS NOT NULL AND product.product_price4 != '' AND product.product_price4 != 0 AND 
            product.product_capital IS NOT NULL AND product.product_capital != '' AND product.product_capital != 0 AND 
            product.product_type_id IS NOT NULL AND product.product_type_id != 0 AND 
            product.product_brand_id IS NOT NULL AND product.product_brand_id != 0";
    }

    // เพิ่มเงื่อนไขวันที่
    if ($start_date) {
        $where_conditions[] = "lot_product.lot_product_date >= ?";
        $bind_params[] = $start_date;
    }
    if ($end_date) {
        $where_conditions[] = "lot_product.lot_product_date <= ?";
        $bind_params[] = $end_date;
    }

    // รวมเงื่อนไข WHERE
    if (!empty($where_conditions)) {
        $query .= " WHERE " . implode(" AND ", $where_conditions);
    }

    $stmt = $pdo->prepare($query);

    // Bind ค่าพารามิเตอร์
    foreach ($bind_params as $index => $param) {
        $stmt->bindValue($index + 1, $param, PDO::PARAM_STR);
    }

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
            $row_height = 8 * $max_nb;

            // ตรวจสอบพื้นที่ในหน้านี้
            if ($pdf->GetY() + $row_height > 270) {
                $pdf->AddPage();
            }

            // เก็บตำแหน่ง Y ก่อนเริ่มแถว
            $y_start = $pdf->GetY();

            // วาดคอลัมน์ทั้งหมดในแถว
            $pdf->Cell(20, $row_height, $product['product_id'], 1, 0, 'C');
            $x = $pdf->GetX();
            $pdf->MultiCell(50, 8, thai($name_text), 1, 'L');
            $pdf->SetXY($x + 50, $y_start);
            $pdf->Cell(20, $row_height, number_format($product['product_price4'] ?: 0, 2), 1, 0, 'C');
            $pdf->Cell(20, $row_height, number_format($product['product_capital'] ?: 0, 2), 1, 0, 'C');
            $x = $pdf->GetX();
            $pdf->MultiCell(35, 8, thai($type_text), 1, 'L');
            $pdf->SetXY($x + 35, $y_start);
            $x = $pdf->GetX();
            $pdf->MultiCell(35, 8, thai($brand_text), 1, 'L');
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
ob_end_clean();
$pdf->Output('I', $filename);
