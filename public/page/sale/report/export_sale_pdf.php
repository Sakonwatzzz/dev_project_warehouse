<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';

$pdo = Database::getInstance()->getConnection();

function thai($text)
{
    return iconv('UTF-8', 'TIS-620//IGNORE', $text);
}

class THPDF extends FPDF
{
    protected $start_date;
    protected $end_date;
    protected $status;
    protected $personnel_name;

    function __construct($start_date = null, $end_date = null, $status = 1, $personnel_name = 'ไม่มีข้อมูล')
    {
        parent::__construct('P');
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->status = $status;
        $this->personnel_name = $personnel_name;
    }

    function Header()
    {
        $this->AddFont('THSarabunNew', '', 'THSarabunNew.php');
        $this->AddFont('THSarabunNew', 'B', 'THSarabunNew_b.php');

        $this->SetFont('THSarabunNew', 'B', 18);
        $this->Cell(0, 10, thai('รายงานการขาย'), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

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

        $this->SetFont('THSarabunNew', 'B', 11);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(25, 8, thai('รหัสการขาย'), 1, 0, 'C', true);
        $this->Cell(30, 8, thai('วันที่ขาย'), 1, 0, 'C', true);
        $this->Cell(40, 8, thai('ชื่อสมาชิก'), 1, 0, 'C', true);
        $this->Cell(35, 8, thai('เบอร์โทร'), 1, 0, 'C', true);
        $this->Cell(35, 8, thai('ชื่อพนักงาน'), 1, 0, 'C', true); // New column for personnel name
        $this->Cell(25, 8, thai('ยอดรวม'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 11);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

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

$search = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : null;
$status = isset($_GET['status']) ? (int)$_GET['status'] : 1;
$date_range = isset($_GET['date_range']) ? htmlspecialchars($_GET['date_range']) : 'this_year';

try {
    $where_conditions = [];
    $params = [];

    if ($search) {
        $where_conditions[] = "(sale.sale_id LIKE ? OR member.member_name LIKE ? OR personnel.personnel_name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if ($date_range === 'today') {
        $where_conditions[] = "DATE(sale.sale_date) = CURDATE()";
    } elseif ($date_range === 'this_month') {
        $where_conditions[] = "YEAR(sale.sale_date) = YEAR(CURDATE()) AND MONTH(sale.sale_date) = MONTH(CURDATE())";
    } elseif ($date_range === 'this_year') {
        $where_conditions[] = "YEAR(sale.sale_date) = YEAR(CURDATE())";
    } elseif ($date_range === 'custom' && $start_date) {
        $where_conditions[] = "DATE(sale.sale_date) >= ?";
        $params[] = $start_date;
    }
    if ($date_range === 'custom' && $end_date) {
        $where_conditions[] = "DATE(sale.sale_date) <= ?";
        $params[] = $end_date;
    }

    if ($status) {
        $where_conditions[] = "sale.sale_status_id = ?";
        $params[] = $status;
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    $query = "SELECT 
                sale.sale_id, 
                sale.sale_date, 
                COALESCE(member.member_name, 'ไม่มีข้อมูล') AS member_name, 
                COALESCE(member.member_phone, 'ไม่มีข้อมูล') AS member_phone, 
                COALESCE(personnel.personnel_name, 'ไม่มีข้อมูล') AS personnel_name, 
                SUM(sale_product.sale_product_qty * sale_product.sale_product_price) AS total_price
            FROM sale
            LEFT JOIN sale_product ON sale.sale_id = sale_product.sale_id
            LEFT JOIN member ON sale.member_id = member.member_id
            LEFT JOIN personnel ON sale.personnel_id = personnel.personnel_id
            $where_clause
            GROUP BY sale.sale_id
            ORDER BY sale.sale_id ASC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $personnel_name = !empty($sales) ? $sales[0]['personnel_name'] : 'ไม่มีข้อมูล';
    $pdf = new THPDF($start_date, $end_date, $status, $personnel_name);
    $pdf->SetMargins(10, 10);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->SetFont('THSarabunNew', '', 11);
    $total_sales = count($sales);
    $total_amount = 0;

    if ($total_sales > 0) {
        foreach ($sales as $sale) {
            $total_amount += $sale['total_price'];
            $nb_sale_id = $pdf->NbLines(25, thai($sale['sale_id']));
            $nb_date = $pdf->NbLines(30, thai($sale['sale_date']));
            $nb_member = $pdf->NbLines(40, thai($sale['member_name']));
            $nb_phone = $pdf->NbLines(35, thai($sale['member_phone']));
            $nb_personnel = $pdf->NbLines(35, thai($sale['personnel_name'])); // New calculation for personnel name
            $max_nb = max($nb_sale_id, $nb_date, $nb_member, $nb_phone, $nb_personnel);
            $row_height = 8 * $max_nb;

            if ($pdf->GetY() + $row_height > 270) {
                $pdf->AddPage();
            }

            $y_start = $pdf->GetY();
            $pdf->Cell(25, $row_height, thai($sale['sale_id']), 1, 0, 'C');
            $x = $pdf->GetX();
            $pdf->MultiCell(30, 8, thai($sale['sale_date']), 1, 'L');
            $pdf->SetXY($x + 30, $y_start);
            $x = $pdf->GetX();
            $pdf->MultiCell(40, 8, thai($sale['member_name']), 1, 'L');
            $pdf->SetXY($x + 40, $y_start);
            $x = $pdf->GetX();
            $pdf->MultiCell(35, 8, thai($sale['member_phone']), 1, 'L');
            $pdf->SetXY($x + 35, $y_start);
            $x = $pdf->GetX();
            $pdf->MultiCell(35, 8, thai($sale['personnel_name']), 1, 'L'); // New column for personnel name
            $pdf->SetXY($x + 35, $y_start);
            $pdf->Cell(25, $row_height, number_format($sale['total_price'], 2), 1, 1, 'C');
        }
    } else {
        $pdf->SetFont('THSarabunNew', 'B', 12);
        $pdf->Cell(0, 10, thai('ไม่พบรายการขาย'), 0, 1, 'C');
    }

    $pdf->SetFont('THSarabunNew', 'B', 11);
    $pdf->Cell(165, 8, thai('รวมทั้งหมด'), 1, 0, 'R'); // Adjusted width to accommodate new column
    $pdf->Cell(25, 8, number_format($total_sales) . thai(' รายการ'), 1, 1, 'C');

    $pdf->Ln(10);
    $pdf->Cell(0, 8, thai('สรุปรายงาน'), 0, 1);
    $pdf->SetFont('THSarabunNew', '', 11);
    $pdf->Cell(0, 8, thai('จำนวนรายการขายทั้งหมด: ') . number_format($total_sales) . thai(' รายการ'), 0, 1);
    $pdf->Cell(0, 8, thai('ยอดรวมทั้งหมด: ฿') . number_format($total_amount, 2), 0, 1);
} catch (PDOException $e) {
    $pdf = new THPDF($start_date, $end_date, $status, 'ไม่มีข้อมูล');
    $pdf->SetMargins(10, 10);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . $e->getMessage(), 0, 1, 'C');
}

$filename = 'SaleReport_' . date('Ymd_His') . '.pdf';
ob_end_clean();
$pdf->Output('I', $filename);