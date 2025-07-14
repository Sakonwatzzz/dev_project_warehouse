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
    // Header ของ PDF
    function Header()
    {
        // เพิ่มฟอนต์ที่ต้องการใช้งาน
        $this->AddFont('THSarabunNew', '', 'THSarabunNew.php');
        $this->AddFont('THSarabunNew', 'B', 'THSarabunNew_b.php');

        // หัวข้อรายงาน
        $this->SetFont('THSarabunNew', 'B', 20);
        $this->Cell(0, 10, thai('รายงานสมาชิก'), 0, 1, 'C');

        // วันที่ออกรายงาน
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        // แสดงช่วงวันที่ (หากมีส่งมาแบบ GET)
        $this->SetFont('THSarabunNew', '', 14);
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
        $end_date   = isset($_GET['end_date']) ? $_GET['end_date'] : '';
        $date_range = '';
        if ($start_date && $end_date) {
            $date_range = thai("ช่วงวันที่: $start_date ถึง $end_date");
        } elseif ($start_date) {
            $date_range = thai("ตั้งแต่วันที่: $start_date");
        } elseif ($end_date) {
            $date_range = thai("ถึงวันที่: $end_date");
        }
        $this->Cell(0, 10, $date_range ? $date_range : thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'L');

        // เส้นแบ่ง
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        // ส่วนหัวของตาราง (ปรับคอลัมน์ให้เหมาะกับแนวตั้ง)
        $this->SetFont('THSarabunNew', 'B', 14);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(25, 10, thai('รหัสประเภท'), 1, 0, 'C', true);
        $this->Cell(65, 10, thai('ชื่อ-สกุล'), 1, 0, 'C', true);
        $this->Cell(50, 10, thai('วันที่สมัคร'), 1, 0, 'C', true);
        $this->Cell(50, 10, thai('พนักงาน'), 1, 1, 'C', true);
    }

    // Footer ของ PDF
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date   = isset($_GET['end_date'])   ? trim($_GET['end_date']) : '';

    $query = "
        SELECT 
            mt.member_type_id,
            mt.member_type_name,
            mt.member_type_date,
            mt.personnel_id,
            pn.personnel_name
        FROM member_type mt
        INNER JOIN personnel pn ON mt.personnel_id = pn.personnel_id";

    $params = [];
    if ($start_date || $end_date) {
        $query .= " WHERE 1=1";
        if ($start_date) {
            $query .= " AND mt.member_type_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND mt.member_type_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }

    $query .= " ORDER BY mt.member_type_id";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // สร้าง PDF แบบแนวตั้ง (Portrait)
    $pdf = new THPDF('P');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 12);

    // วนลูปแสดงข้อมูลสมาชิก
    foreach ($members as $member) {
        // ตรวจสอบความสูงหน้ากระดาษ เพื่อตัดหน้ากระดาษใหม่ (A4 แนวตั้งสูงประมาณ 297 มม.)
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }

        $pdf->Cell(25, 10, thai($member['member_type_id']), 1, 0, 'C');
        $pdf->Cell(65, 10, thai($member['member_type_name']), 1, 0, 'L');
        $pdf->Cell(50, 10, thai((new DateTime($member['member_type_date']))->format('d/m/Y')), 1, 0, 'L');
        $pdf->Cell(50, 10, thai($member['personnel_name']), 1, 1, 'L');
    }

    // สรุปข้อมูลท้ายตาราง
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(190, 10, thai('รวมทั้งหมด: ') . count($members) . thai(' รายการ'), 1, 1, 'R');

    $pdf->Ln(10);
    $pdf->Cell(0, 10, thai('สรุปรายงาน'), 0, 1);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->Cell(0, 10, thai('จำนวนสมาชิกทั้งหมด: ') . count($members) . thai(' รายการ'), 0, 1);

    $filename = 'MemberReport_' . date('Ymd_His') . '.pdf';

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
