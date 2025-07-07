<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';
session_start();
date_default_timezone_set('Asia/Bangkok');

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: /?page=login&timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
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

        $this->SetFont('THSarabunNew', 'B', 22);
        $this->Cell(0, 12, thai('รายงานสมาชิก'), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 14);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        $type = $_GET['type'] ?? '';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';
        $search = $_GET['search'] ?? '';

        $filters = [];
        if ($type) $filters[] = thai("ประเภท: $type");
        if ($start_date && $end_date) {
            $filters[] = thai("ช่วงวันที่: $start_date ถึง $end_date");
        } elseif ($start_date) {
            $filters[] = thai("ตั้งแต่วันที่: $start_date");
        } elseif ($end_date) {
            $filters[] = thai("ถึงวันที่: $end_date");
        }
        if ($search) $filters[] = thai("ค้นหา: $search");

        $filter_text = !empty($filters) ? implode(', ', $filters) : thai('ทั้งหมด');
        $this->MultiCell(0, 8, $filter_text, 0, 'L');

        $this->Ln(2);
        $this->SetFont('THSarabunNew', 'B', 16);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(35, 10, thai('รหัส'), 1, 0, 'C', true);
        $this->Cell(65, 10, thai('ชื่อ-นามสกุล'), 1, 0, 'C', true);
        $this->Cell(40, 10, thai('เบอร์โทรศัพท์'), 1, 0, 'C', true);
        $this->Cell(50, 10, thai('วันที่สมัคร'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    $type = $_GET['type'] ?? '';
    $search = $_GET['search'] ?? '';
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';

    $query = "
        SELECT 
            m.member_id,
            m.member_name,
            m.member_phone,
            m.member_register_date
        FROM member m
        INNER JOIN member_type mt ON m.member_type_id = mt.member_type_id
        WHERE mt.member_type_name = :type";
    $params = [':type' => $type];

    if (!empty($search) || $start_date || $end_date) {
        $query .= " AND 1=1";
        if (!empty($search)) {
            $query .= " AND (
                m.member_id LIKE :search OR 
                m.member_name LIKE :search OR 
                m.member_phone LIKE :search
            )";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $query .= " AND m.member_register_date >= :start_date";
            $params[':start_date'] = "$start_date 00:00:00";
        }
        if ($end_date) {
            $query .= " AND m.member_register_date <= :end_date";
            $params[':end_date'] = "$end_date 23:59:59";
        }
    }

    $query .= " ORDER BY m.member_id";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new THPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 14);

    foreach ($members as $member) {
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }
        $pdf->Cell(35, 10, $member['member_id'], 1, 0, 'C');
        $pdf->Cell(65, 10, thai($member['member_name']), 1, 0, 'L');
        $pdf->Cell(40, 10, thai($member['member_phone']), 1, 0, 'L');
        $pdf->Cell(50, 10, thai($member['member_register_date']), 1, 1, 'L');
    }

    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(190, 10, thai('รวมทั้งหมด: ') . count($members) . thai(' รายการ'), 1, 1, 'R');

    $pdf->Ln(8);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->MultiCell(0, 8, thai("หมายเหตุ: รายงานนี้จัดทำขึ้นโดยระบบอัตโนมัติเพื่อใช้สำหรับอ้างอิงข้อมูลสมาชิกเท่านั้น"), 0, 'L');

    $filename = 'MemberReport_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);
} catch (PDOException $e) {
    $pdf = new THPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาด: ') . $e->getMessage(), 0, 1, 'C');
    $pdf->Output();
}
