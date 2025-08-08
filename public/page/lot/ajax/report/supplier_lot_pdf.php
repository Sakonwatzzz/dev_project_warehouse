<?php
require_once __DIR__ . '/../../../../../config/database_server.php';
require_once __DIR__ . '/../../../../fpdf186/fpdf.php';
ob_start();
session_start();
date_default_timezone_set('Asia/Bangkok');

$pdo = Database::getInstance()->getConnection(); 

// Create CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'supplier_lot', 'supplier_id' => $_GET['supplier_id'] ?? 0]);
    header("Location: /page/login.php?$query_params");
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

function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database query error: " . $e->getMessage());
        throw new Exception("Database error: " . htmlspecialchars($e->getMessage()));
    }
}

class THPDF extends FPDF
{
    function Header()
    {
        $this->AddFont('THSarabunNew', '', 'THSarabunNew.php');
        $this->AddFont('THSarabunNew', 'B', 'THSarabunNew_b.php');

        $this->SetFont('THSarabunNew', 'B', 22);
        $this->Cell(0, 12, thai('รายงานล็อตสินค้าจาก: ' . htmlspecialchars($_GET['supplier_name'] ?? 'ซัพพลายเออร์')), 0, 1, 'C');

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
        throw new Exception('Invalid CSRF token');
    }

    // Get supplier ID and validate
    $supplier_id = isset($_GET['supplier_id']) ? filter_var($_GET['supplier_id'], FILTER_VALIDATE_INT) : 0;
    if (!$supplier_id) {
        throw new Exception('กรุณาระบุรหัสซัพพลายเออร์');
    }

    // Fetch supplier information
    $stmt = fetchData($pdo, "SELECT supplier_name FROM supplier WHERE supplier_id = :supplier_id", [':supplier_id' => $supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$supplier) {
        throw new Exception('ไม่พบข้อมูลซัพพลายเออร์');
    }
    $_GET['supplier_name'] = $supplier['supplier_name']; // For header

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
        WHERE l.supplier_id = :supplier_id";
    $params = [':supplier_id' => $supplier_id];

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

    $stmt = fetchData($pdo, $query, $params);
    $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new THPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 14);

    if (count($lots) === 0) {
        $pdf->Cell(0, 10, thai('ไม่พบข้อมูลล็อต'), 1, 1, 'C');
    } else {
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
    }

    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(190, 10, thai('รวมทั้งหมด: ') . count($lots) . thai(' รายการ'), 1, 1, 'R');

    $pdf->Ln(8);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->MultiCell(0, 8, thai("หมายเหตุ: รายงานนี้จัดทำขึ้นโดยระบบอัตโนมัติเพื่อใช้สำหรับอ้างอิงข้อมูลล็อตสินค้าเท่านั้น"), 0, 'L');

    $filename = 'SupplierLotReport_' . htmlspecialchars($supplier['supplier_name']) . '_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);

} catch (Exception $e) {
    $pdf = new THPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาด: ') . thai(htmlspecialchars($e->getMessage())), 0, 1, 'C');
    ob_end_clean();
    $pdf->Output();
}