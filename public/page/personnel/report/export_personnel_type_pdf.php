<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';
$pdo = Database::getInstance()->getConnection(); 

// Function to convert text to TIS-620
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
        $this->Cell(0, 10, thai('รายงานประเภทบุคลากร'), 0, 1, 'C');

        // Report date
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        // Date range
        $this->SetFont('THSarabunNew', '', 14);
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
        $date_range = '';
        if ($start_date && $end_date) {
            $date_range = thai("ช่วงวันที่: $start_date ถึง $end_date");
        } elseif ($start_date) {
            $date_range = thai("ตั้งแต่วันที่: $start_date");
        } elseif ($end_date) {
            $date_range = thai("ถึงวันที่: $end_date");
        }
        $this->Cell(0, 10, $date_range ? $date_range : thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'L');

        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        $this->SetFont('THSarabunNew', 'B', 16);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(30, 10, thai('รหัส'), 1, 0, 'C', true);
        $this->Cell(80, 10, thai('ชื่อประเภท'), 1, 0, 'C', true);
        $this->Cell(80, 10, thai('วันที่และเวลา'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    $query = "SELECT 
        personnel_type_id,
        personnel_type_name,
        personnel_type_date
        FROM personnel_type";

    $params = [];
    if ($start_date || $end_date) {
        $query .= " WHERE 1=1";
        if ($start_date) {
            $query .= " AND personnel_type_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND personnel_type_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }

    $query .= " ORDER BY personnel_type_id";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $personnel_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new THPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->SetFont('THSarabunNew', '', 14);

    foreach ($personnel_types as $type) {
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }

        $pdf->Cell(30, 10, $type['personnel_type_id'], 1, 0, 'C');
        $pdf->Cell(80, 10, thai($type['personnel_type_name']), 1, 0, 'L');
        $pdf->Cell(80, 10, thai($type['personnel_type_date']), 1, 1, 'C');
    }

    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(190, 10, thai('รวมทั้งหมด: ') . count($personnel_types) . thai(' รายการ'), 1, 1, 'R');

    $pdf->Ln(10);
    $pdf->Cell(0, 10, thai('สรุปรายงาน'), 0, 1);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->Cell(0, 10, thai('จำนวนประเภทบุคลากรทั้งหมด: ') . count($personnel_types) . thai(' รายการ'), 0, 1);

    $filename = 'PersonnelTypeReport_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);
} catch (PDOException $e) {
    $pdf = new THPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . $e->getMessage(), 0, 1, 'C');
    $pdf->Output();
}
?>