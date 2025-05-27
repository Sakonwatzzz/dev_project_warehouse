<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';
session_start();
date_default_timezone_set('Asia/Bangkok');

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

        // Title
        $this->SetFont('THSarabunNew', 'B', 18);
        $this->Cell(0, 10, thai('รายงานการเช็คสต๊อกสินค้า'), 0, 1, 'C');
        $this->Ln(2);

        // Report date
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        // Date range
        $this->SetFont('THSarabunNew', '', 12);
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
        $this->Cell(0, 8, $date_range ? $date_range : '', 0, 1, 'C');
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 10);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: /login.php?timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Verify CSRF token
if (!isset($_SESSION['csrf_token']) || !isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    error_log("CSRF Validation Failed: Session Token: " . ($_SESSION['csrf_token'] ?? 'unset') . ", GET Token: " . ($_GET['csrf_token'] ?? 'unset'));
    header('HTTP/1.1 403 Forbidden');
    exit('Invalid CSRF token');
}

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    $query = "
        SELECT 
            sc.stock_check_id,
            s.stock_id,
            s.stock_date,
            w.warehouse_name,
            p.product_id,
            p.product_name,
            sc.stock_check_system,
            sc.stock_check_actual,
            per.personnel_name,
            scs.check_status_name,
            sc.stock_check_remark
        FROM stock_check sc
        LEFT JOIN product p ON sc.product_id = p.product_id
        LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
        LEFT JOIN stock s ON sc.stock_id = s.stock_id
        LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
        LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
        WHERE s.check_status_id = 2";

    $params = [];
    if ($search || $start_date || $end_date) {
        $query .= " AND 1=1";
        if ($search) {
            $query .= " AND (sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $query .= " AND s.stock_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND s.stock_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }
    $query .= " ORDER BY s.stock_date DESC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new THPDF('P'); // Portrait orientation
    $pdf->SetMargins(10, 10, 10); // 10mm margins
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Additional header information (stock_check_id, personnel_name, check_status_name, stock_check_remark)
    $pdf->SetFont('THSarabunNew', '', 11);
    if (count($stock_checks) > 0) {
        // Use the first record for header information
        $stock = $stock_checks[0];
        $pdf->Cell(47.5, 8, thai('รหัส: ' . $stock['stock_check_id']), 0, 0, 'L');
        $pdf->Cell(47.5, 8, thai('พนักงาน: ' . $stock['personnel_name']), 0, 0, 'L');
        $pdf->Cell(47.5, 8, thai('สถานะ: ' . $stock['check_status_name']), 0, 0, 'L');
        $pdf->Cell(47.5, 8, thai('หมายเหตุ: ' . $stock['stock_check_remark']), 0, 1, 'L');
    }
    $pdf->Ln(2);

    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(5);

    // Table headers
    $pdf->SetFont('THSarabunNew', 'B', 11);
    $pdf->SetFillColor(200, 216, 237);
    $pdf->Cell(20, 8, thai('รหัสสต๊อก'), 1, 0, 'C', true);
    $pdf->Cell(30, 8, thai('วันที่'), 1, 0, 'C', true);
    $pdf->Cell(35, 8, thai('คลังสินค้า'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'C', true);
    $pdf->Cell(50, 8, thai('ชื่อสินค้า'), 1, 0, 'C', true);
    $pdf->Cell(15, 8, thai('จากระบบ'), 1, 0, 'C', true);
    $pdf->Cell(15, 8, thai('นับได้'), 1, 1, 'C', true);

    $pdf->SetFont('THSarabunNew', '', 11);

    if (count($stock_checks) > 0) {
        foreach ($stock_checks as $stock) {
            if ($pdf->GetY() > 270) { // Adjusted for A4 portrait height
                $pdf->AddPage();
                // Repeat table headers on new page
                $pdf->SetFont('THSarabunNew', 'B', 11);
                $pdf->SetFillColor(200, 216, 237);
                $pdf->Cell(20, 8, thai('รหัสสต๊อก'), 1, 0, 'C', true);
                $pdf->Cell(30, 8, thai('วันที่'), 1, 0, 'C', true);
                $pdf->Cell(35, 8, thai('คลังสินค้า'), 1, 0, 'C', true);
                $pdf->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'C', true);
                $pdf->Cell(50, 8, thai('ชื่อสินค้า'), 1, 0, 'C', true);
                $pdf->Cell(15, 8, thai('จากระบบ'), 1, 0, 'C', true);
                $pdf->Cell(15, 8, thai('นับได้'), 1, 1, 'C', true);
                $pdf->SetFont('THSarabunNew', '', 11);
            }

            $pdf->Cell(20, 8, thai($stock['stock_id']), 1, 0, 'C');
            $pdf->Cell(30, 8, thai($stock['stock_date']), 1, 0, 'C');
            $pdf->Cell(35, 8, thai($stock['warehouse_name']), 1, 0, 'L');
            $pdf->Cell(25, 8, thai($stock['product_id']), 1, 0, 'C');
            $pdf->Cell(50, 8, thai($stock['product_name']), 1, 0, 'L');
            $pdf->Cell(15, 8, thai($stock['stock_check_system']), 1, 0, 'C');
            $pdf->Cell(15, 8, thai($stock['stock_check_actual']), 1, 1, 'C');
        }
    } else {
        $pdf->Cell(190, 8, thai('ไม่พบข้อมูลการเช็คสต๊อก'), 1, 1, 'C');
    }

    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(190, 8, thai('รวมทั้งหมด: ') . count($stock_checks) . thai(' รายการ'), 1, 1, 'R');

    $pdf->Ln(10);
    $pdf->Cell(0, 8, thai('สรุปรายงาน'), 0, 1);
    $pdf->SetFont('THSarabunNew', '', 12);
    $pdf->Cell(0, 8, thai('จำนวนการเช็คสต๊อกทั้งหมด: ') . count($stock_checks) . thai(' รายการ'), 0, 1);

    $filename = 'StockCheckReport_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);
} catch (PDOException $e) {
    $pdf = new THPDF('P');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . thai($e->getMessage()), 0, 1, 'C');
    ob_end_clean();
    $pdf->Output();
}
