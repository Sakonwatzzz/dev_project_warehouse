<?php
require_once __DIR__ . '../../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../fpdf186/fpdf.php';
session_start();
date_default_timezone_set('Asia/Bangkok');

$pdo = Database::getInstance()->getConnection(); 

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {
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
        $this->Cell(0, 12, thai('รายงานล็อตสินค้าจาก: ' . htmlspecialchars($_GET['personnel_name'] ?? 'บุคลากร')), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 14);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        $search = $_GET['search'] ?? '';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';

        $filters = [];
        if ($search) $filters[] = thai("ค้นหา: $search");
        if ($start_date && $end_date) {
            $filters[] = thai("ช่วงวันที่: $start_date ถึง $end_date");
        } elseif ($start_date) {
            $filters[] = thai("ตั้งแต่วันที่: $start_date");
        } elseif ($end_date) {
            $filters[] = thai("ถึงวันที่: $end_date");
        }

        $filter_text = !empty($filters) ? implode(', ', $filters) : thai('ทั้งหมด');
        $this->MultiCell(0, 8, $filter_text, 0, 'L');

        $this->Ln(2);
        $this->SetFont('THSarabunNew', 'B', 16);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(30, 10, thai('รหัสล็อต'), 1, 0, 'C', true);
        $this->Cell(40, 10, thai('วันที่และเวลา'), 1, 0, 'C', true);
        $this->Cell(40, 10, thai('คลังสินค้า'), 1, 0, 'C', true);
        $this->Cell(50, 10, thai('รายละเอียด'), 1, 0, 'C', true);
        $this->Cell(30, 10, thai('สถานะ'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    // Validate CSRF token
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        die('Invalid CSRF token');
    }

    // Get personnel ID and validate
    $personnel_id = isset($_GET['personnel_id']) ? (int)$_GET['personnel_id'] : 0;
    if (!$personnel_id) {
        throw new Exception('กรุณาระบุรหัสบุคลากร');
    }

    // Fetch personnel information
    $stmt = $pdo->prepare("SELECT personnel_name FROM personnel WHERE personnel_id = ?");
    $stmt->execute([$personnel_id]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$personnel) {
        throw new Exception('ไม่พบข้อมูลบุคลากร');
    }
    $_GET['personnel_name'] = $personnel['personnel_name']; // For header

    // Handle search and date parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    // Build query
    $query = "
        SELECT
            l.lot_id,
            l.lot_detail,
            l.lot_date,
            ls.lot_status_name,
            w.warehouse_name
        FROM lot l
        LEFT JOIN lot_status ls ON l.lot_status_id = ls.lot_status_id
        LEFT JOIN warehouse w ON l.warehouse_id = w.warehouse_id
        WHERE l.personnel_id = :personnel_id";
    $params = [':personnel_id' => $personnel_id];

    if (!empty($search)) {
        $query .= " AND (l.lot_id LIKE :search OR l.lot_detail LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($start_date) {
        $query .= " AND l.lot_date >= :start_date";
        $params[':start_date'] = "$start_date 00:00:00";
    }
    if ($end_date) {
        $query .= " AND l.lot_date <= :end_date";
        $params[':end_date'] = "$end_date 23:59:59";
    }

    $query .= " ORDER BY l.lot_date DESC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new THPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 14);

    foreach ($lots as $lot) {
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }
        $pdf->Cell(30, 10, thai($lot['lot_id']), 1, 0, 'C');
        $pdf->Cell(40, 10, thai($lot['lot_date']), 1, 0, 'L');
        $pdf->Cell(40, 10, thai($lot['warehouse_name']), 1, 0, 'L');
        $pdf->Cell(50, 10, thai($lot['lot_detail']), 1, 0, 'L');
        $pdf->Cell(30, 10, thai($lot['lot_status_name']), 1, 1, 'L');
    }

    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(190, 10, thai('รวมทั้งหมด: ') . count($lots) . thai(' รายการ'), 1, 1, 'R');

    $pdf->Ln(8);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->MultiCell(0, 8, thai("หมายเหตุ: รายงานนี้จัดทำขึ้นโดยระบบอัตโนมัติเพื่อใช้สำหรับอ้างอิงข้อมูลล็อตสินค้าเท่านั้น"), 0, 'L');

    $filename = 'PersonnelLotReport_' . htmlspecialchars($personnel['personnel_name']) . '_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);

} catch (Exception $e) {
    $pdf = new THPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาด: ') . thai(htmlspecialchars($e->getMessage())), 0, 1, 'C');
    $pdf->Output();
}