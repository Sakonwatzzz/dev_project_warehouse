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
    public $stock_data = [];

    function Header()
    {
        $this->AddFont('THSarabunNew', '', 'THSarabunNew.php');
        $this->AddFont('THSarabunNew', 'B', 'THSarabunNew_b.php');

        // Title
        $this->SetFont('THSarabunNew', 'B', 16);
        $this->Cell(0, 10, thai('รายงานการเช็คสต๊อกสินค้า'), 0, 1, 'C');
        $this->Ln(3);

        // Report date
        $this->SetFont('THSarabunNew', '', 11);
        $this->Cell(0, 6, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'L');

        // Date range (today)
        $date_range = thai("วันที่: " . date('d/m/Y'));
        $this->Cell(0, 6, $date_range, 0, 1, 'L');

        // Stock details (if available)
        if (isset($this->stock_data) && count($this->stock_data) > 0) {
            $stock = $this->stock_data[0];
            $this->Cell(50, 6, thai('รหัสการตรวจ: ' . $stock['stock_check_id']), 0, 0, 'L');
            $this->Cell(50, 6, thai('รหัสสต๊อก: ' . $stock['stock_id']), 0, 0, 'L');
            $this->Cell(50, 6, thai('วันที่ตรวจ: ' . date('d/m/Y', strtotime($stock['stock_date']))), 0, 0, 'L');
            $this->Cell(40, 6, thai('คลัง: ' . $stock['warehouse_name']), 0, 1, 'L');

            if (!empty($stock['personnel_name'])) {
                $this->Cell(100, 6, thai('ผู้ตรวจสอบ: ' . $stock['personnel_name']), 0, 0, 'L');
                $this->Cell(90, 6, thai('สถานะ: ' . $stock['check_status_name']), 0, 1, 'L');
            }
        }
        $this->Ln(5);

        // Line separator
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);

        // Table headers
        $this->SetFont('THSarabunNew', 'B', 11);
        $this->SetFillColor(200, 216, 537);
        $this->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'C', true);
        $this->Cell(75, 8, thai('ชื่อสินค้า'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('จากระบบ'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('นับได้'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('ส่วนต่าง'), 1, 0, 'C', true);
        $this->Cell(30, 8, thai('หมายเหตุ'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 9);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . ' / {nb}', 0, 0, 'C');
    }

    function SetStockData($data)
    {
        $this->stock_data = $data;
    }
}

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'stock_list'] + ($_GET ?? []));
    header("Location: /page/stock/login.php?$query_params");
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
        WHERE s.check_status_id = 2
        AND s.stock_date >= :today_start 
        AND s.stock_date <= :today_end";

    $params = [
        ':today_start' => date('Y-m-d 00:00:00'),
        ':today_end' => date('Y-m-d 23:59:59')
    ];

    if ($search) {
        $query .= " AND (sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    $query .= " ORDER BY s.stock_date DESC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new THPDF('P'); // Portrait orientation
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->SetStockData($stock_checks);
    $pdf->AddPage();

    $pdf->SetFont('THSarabunNew', '', 10);

    if (count($stock_checks) > 0) {
        $total_system = 0;
        $total_actual = 0;

        foreach ($stock_checks as $stock) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Repeat table headers on new page
                $pdf->SetFont('THSarabunNew', 'B', 11);
                $pdf->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'C');
                $pdf->Cell(75, 8, thai('ชื่อสินค้า'), 1, 0, 'C');
                $pdf->Cell(20, 8, thai('จากระบบ'), 1, 0, 'C');
                $pdf->Cell(20, 8, thai('นับได้'), 1, 0, 'C');
                $pdf->Cell(20, 8, thai('ส่วนต่าง'), 1, 0, 'C');
                $pdf->Cell(30, 8, thai('หมายเหตุ'), 1, 1, 'C');
                $pdf->SetFont('THSarabunNew', '', 10);
            }

            $system_qty = (int)$stock['stock_check_system'];
            $actual_qty = (int)$stock['stock_check_actual'];
            $difference = $actual_qty - $system_qty;

            $total_system += $system_qty;
            $total_actual += $actual_qty;

            // Handle long product names
            $product_name = $stock['product_name'];
            if (strlen($product_name) > 35) {
                $product_name = substr($product_name, 0, 32) . '...';
            }

            $pdf->Cell(25, 7, thai($stock['product_id']), 1, 0, 'C');
            $pdf->Cell(75, 7, thai($product_name), 1, 0, 'L');
            $pdf->Cell(20, 7, thai(number_format($system_qty)), 1, 0, 'C');
            $pdf->Cell(20, 7, thai(number_format($actual_qty)), 1, 0, 'C');

            $diff_text = ($difference > 0 ? '+' : '') . number_format($difference);
            $pdf->Cell(20, 7, thai($diff_text), 1, 0, 'C');

            $remark = !empty($stock['stock_check_remark']) ? substr($stock['stock_check_remark'], 0, 15) : '-';
            $pdf->Cell(30, 7, thai($remark), 1, 1, 'L');
        }

        // Summary row
        $pdf->Ln(2);
        $pdf->SetFont('THSarabunNew', 'B', 11);
        $total_difference = $total_actual - $total_system;

        $pdf->Cell(100, 8, thai('รวมทั้งหมด'), 1, 0, 'R');
        $pdf->Cell(20, 8, thai(number_format($total_system)), 1, 0, 'C');
        $pdf->Cell(20, 8, thai(number_format($total_actual)), 1, 0, 'C');
        $diff_total = ($total_difference > 0 ? '+' : '') . number_format($total_difference);
        $pdf->Cell(20, 8, thai($diff_total), 1, 0, 'C');
        $pdf->Cell(30, 8, '', 1, 1, 'C');
    } else {
        $pdf->Cell(190, 8, thai('ไม่พบข้อมูลการเช็คสต๊อก'), 1, 1, 'C');
    }

    // Summary section
    $pdf->Ln(5);
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(0, 8, thai('สรุปรายงาน'), 0, 1, 'L');
    $pdf->SetFont('THSarabunNew', '', 11);
    $pdf->Cell(0, 6, thai('จำนวนรายการทั้งหมด: ') . count($stock_checks) . thai(' รายการ'), 0, 1, 'L');

    if (count($stock_checks) > 0) {
        $pdf->Cell(0, 6, thai('จำนวนจากระบบ: ') . number_format($total_system), 0, 1, 'L');
        $pdf->Cell(0, 6, thai('จำนวนนับจริง: ') . number_format($total_actual), 0, 1, 'L');
        $pdf->Cell(0, 6, thai('ส่วนต่าง: ') . ($total_difference > 0 ? '+' : '') . number_format($total_difference), 0, 1, 'L');
    }

    $filename = 'StockCheckReport_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);
} catch (PDOException $e) {
    $pdf = new THPDF('P');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . thai(htmlspecialchars($e->getMessage())), 0, 1, 'C');
    ob_end_clean();
    $pdf->Output();
}
