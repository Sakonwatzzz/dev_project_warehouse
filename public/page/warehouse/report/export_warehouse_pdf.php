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
    function Header()
    {
        $this->AddFont('THSarabunNew', '', 'THSarabunNew.php');
        $this->AddFont('THSarabunNew', 'B', 'THSarabunNew_b.php');

        $this->SetFont('THSarabunNew', 'B', 20);
        $this->Cell(0, 10, thai('รายงานคลังสินค้า'), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 14);
        $start_date = $_GET['start_date'] ?? '';
        $end_date   = $_GET['end_date'] ?? '';
        $date_range = '';

        if ($start_date && $end_date) {
            $date_range = thai("ช่วงวันที่: $start_date ถึง $end_date");
        } elseif ($start_date) {
            $date_range = thai("ตั้งแต่วันที่: $start_date");
        } elseif ($end_date) {
            $date_range = thai("ถึงวันที่: $end_date");
        }

        if ($date_range) {
            $this->Cell(0, 10, $date_range, 0, 1, 'L');
        }

        $this->Ln(3);
        $this->SetDrawColor(150, 150, 150);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        $this->SetFont('THSarabunNew', 'B', 14);
        $this->SetFillColor(200, 216, 237);

        // Header Table
        $this->Cell(30, 8, thai('รหัสคลังสินค้า'), 1, 0, 'C', true);
        $this->Cell(120, 8, thai('ชื่อคลังสินค้า'), 1, 0, 'C', true);
        $this->Cell(40, 8, thai('วันที่และเวลา'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    $start_date = trim($_GET['start_date'] ?? '');
    $end_date   = trim($_GET['end_date'] ?? '');

    $query = "
        SELECT 
            w.warehouse_id,
            w.warehouse_name,
            w.warehouse_create_date
        FROM warehouse w";

    $params = [];

    if ($start_date || $end_date) {
        $query .= " WHERE 1=1";
        if ($start_date) {
            $query .= " AND w.warehouse_create_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND w.warehouse_create_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }

    $query .= " ORDER BY w.warehouse_id";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new THPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 14);
    $rowHeight = 8;

    foreach ($warehouses as $warehouse) {
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }

        $pdf->Cell(30, $rowHeight, thai($warehouse['warehouse_id']), 1, 0, 'C');
        $pdf->Cell(120, $rowHeight, thai($warehouse['warehouse_name']), 1, 0, 'L');
        $pdf->Cell(40, $rowHeight, thai((new DateTime($warehouse['warehouse_create_date']))->format('d/m/Y H:i:s')), 1, 1, 'C');
    }

    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, thai('รวมทั้งหมด: ') . count($warehouses) . thai(' รายการ'), 0, 1, 'R');

    $pdf->Ln(5);
    $pdf->Cell(0, 10, thai('สรุปรายงาน'), 0, 1);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->Cell(0, 10, thai('จำนวนคลังทั้งหมด: ') . count($warehouses) . thai(' รายการ'), 0, 1);

    $filename = 'WarehouseReport_' . date('Ymd_His') . '.pdf';
    if (ob_get_contents()) ob_end_clean();
    $pdf->Output('I', $filename);
} catch (PDOException $e) {
    if (!isset($pdf)) {
        $pdf = new THPDF('P');
        $pdf->AliasNbPages();
        $pdf->AddPage();
    }
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . thai($e->getMessage()), 0, 1, 'C');
    $pdf->Output();
}
