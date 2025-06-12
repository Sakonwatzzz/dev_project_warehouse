<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';
session_start();
date_default_timezone_set('Asia/Bangkok');

// Function to convert text to TIS-620 or keep UTF-8 with fallback
function thai($text)
{
    if (!is_string($text) || empty($text)) {
        return '';
    }
    $converted = iconv('UTF-8', 'TIS-620//IGNORE', $text);
    if ($converted === false) {
        error_log("iconv failed for text: $text");
        return $text; // Fallback to original UTF-8 if conversion fails
    }
    error_log("Thai conversion - Input: $text, Output: $converted");
    return $converted;

}

class THPDF extends FPDF
{
    public $stock_data = [];

    function Header()
    {
        $this->AddFont('THSarabunNew', '', 'THSarabunNew.php');
        $this->AddFont('THSarabunNew', 'B', 'THSarabunNew_b.php');
        $this->SetFont('THSarabunNew', 'B', 12); // Reduced font size from 14 to 12

        // Title
        $warehouse_title = '';
        if (isset($this->stock_data[0]['warehouse_name'])) {
            $warehouse_title = ': ' . $this->stock_data[0]['warehouse_name'];
        }
        $this->Cell(0, 8, thai('รายงานการเช็คสต๊อกสินค้า' . $warehouse_title), 0, 1, 'C');

        // Report date
        $this->SetFont('THSarabunNew', '', 10); // Reduced font size from 11 to 10
        $this->Cell(0, 5, thai('วันที่และเวลา: ') . date('d/m/Y H:i:s'), 0, 1, 'L'); // Shortened text


        // Stock details (if available)
        if (isset($this->stock_data) && count($this->stock_data) > 0) {
            $stock = $this->stock_data[0];
            // $this->Cell(50, 6, thai('รหัสการตรวจ: ' . $stock['stock_check_id']), 0, 0, 'L');
            // $this->Cell(50, 6, thai('รหัสสต๊อก: ' . $stock['stock_id']), 0, 0, 'L');
            // $this->Cell(50, 6, thai('วันที่ตรวจ: ' . date('d/m/Y', strtotime($stock['stock_date']))), 0, 0, 'L');
            // $this->Cell(40, 6, thai('คลัง: ' . $stock['warehouse_name']), 0, 1, 'L');

            if (!empty($stock['personnel_name'])) {

                $this->Cell(100, 5, thai('ผู้ตรวจสอบ: ' . $stock['personnel_name']), 0, 0, 'L');

            }
        }
        $this->Ln(5); // Reduced from Ln(7) to Ln(5)

        // Line separator
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2); // Reduced from Ln(3) to Ln(2)

        // Table headers
        $this->SetFont('THSarabunNew', 'B', 11);
        $this->SetFillColor(200, 216, 255);
        $this->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'L', true);
        $this->Cell(70, 8, thai('ชื่อสินค้า'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('จำนวนจากระบบ'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('จำนวนที่นับได้'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('ส่วนต่าง'), 1, 0, 'C', true);
        $this->Cell(25, 8, thai('หมายเหตุ'), 1, 1, 'C', true);

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
    error_log("Search parameter: $search"); // Debug log

    // รับ selected_date จาก URL หรือใช้วันที่ปัจจุบันเป็นค่าเริ่มต้น
    $selected_date = isset($_GET['selected_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['selected_date'])
        ? $_GET['selected_date']
        : date("Y-m-d");
    error_log("Selected date: $selected_date"); // Debug log

    $params = [':selected_date' => $selected_date];
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
            sc.stock_check_remark_id,
            scr.stock_check_remark_name,
            sc.stock_check_comment
        FROM stock_check sc
        LEFT JOIN product p ON sc.product_id = p.product_id
        LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
        LEFT JOIN stock s ON sc.stock_id = s.stock_id
        LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
        LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
        LEFT JOIN stock_check_remark scr ON sc.stock_check_remark_id = scr.stock_check_remark_id
        WHERE s.check_status_id = 2 
        AND DATE(s.stock_date) = :selected_date
    ";

    if (!empty($search)) {
        $query .= " AND (sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    $query .= " ORDER BY s.stock_date DESC";
    error_log("Query: $query");
    error_log("Params: " . json_encode($params));


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
                $pdf->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'L');
                $pdf->Cell(70, 8, thai('ชื่อสินค้า'), 1, 0, 'C');
                $pdf->Cell(20, 8, thai('จำนวนจากระบบ'), 1, 0, 'C');
                $pdf->Cell(20, 8, thai('จำนวนที่นับได้'), 1, 0, 'C');
                $pdf->Cell(20, 8, thai('ส่วนต่าง'), 1, 0, 'C');
                $pdf->Cell(25, 8, thai('หมายเหตุ'), 1, 1, 'C');
                $pdf->SetFont('THSarabunNew', '', 10);
            }

            $system_qty = (int) ($stock['stock_check_system'] ?? 0);
            $actual_qty = (int) ($stock['stock_check_actual'] ?? 0);

            $difference = $actual_qty - $system_qty;

            $total_system += $system_qty;
            $total_actual += $actual_qty;


            $product_name = $stock['product_name'] ?? '';
            if (mb_strlen($product_name, 'UTF-8') > 255) {
                $product_name = mb_substr($product_name, 0, 252, 'UTF-8') . '...';
            }

            $pdf->Cell(25, 7, thai($stock['product_id'] ?? ''), 1, 0, 'L');
            $pdf->Cell(70, 7, thai($product_name), 1, 0, 'L');

            $pdf->Cell(20, 7, thai(number_format($system_qty)), 1, 0, 'C');
            $pdf->Cell(20, 7, thai(number_format($actual_qty)), 1, 0, 'C');

            $diff_text = ($difference > 0 ? '+' : '') . number_format($difference);

            if ($difference < 0) {
                $pdf->SetTextColor(139, 0, 0);       // สีแดงเข้ม
                $pdf->SetFont('THSarabunNew', 'B', 11);  // ตัวหนา
                $diff_text = thai($diff_text); // สมมติแปลงข้อความก่อนใช้งาน
            }

            $pdf->Cell(20, 7, thai($diff_text), 1, 0, 'C');

            // เปลี่ยนสีตัวหนังสือกลับเป็นสีดำ
            if ($difference < 0) {
                $pdf->SetTextColor(0, 0, 0); // สีดำ
                $pdf->SetFont('THSarabunNew', '', 11);  // ตัวหนา
            }

            $remark = !empty($stock['stock_check_remark_name']) ? mb_substr($stock['stock_check_remark_name'], 0, 20, 'UTF-8') : '';
            $pdf->Cell(25, 7, thai($remark), 1, 1, 'L');

        }

        // Summary row
        $pdf->Ln(2);
        $pdf->SetFont('THSarabunNew', 'B', 11);
        $total_difference = $total_actual - $total_system;


        $pdf->Cell(95, 8, thai('รวมทั้งหมด'), 1, 0, 'R');

        $pdf->Cell(20, 8, thai(number_format($total_system)), 1, 0, 'C');
        $pdf->Cell(20, 8, thai(number_format($total_actual)), 1, 0, 'C');
        $diff_total = ($total_difference > 0 ? '+' : '') . number_format($total_difference);
        $pdf->Cell(20, 8, thai($diff_total), 1, 0, 'C');
        $pdf->Cell(25, 8, '', 1, 1, 'C');

    } else {
        $pdf->Cell(190, 8, thai('ไม่พบข้อมูลการเช็คสต๊อก'), 1, 1, 'C');
    }

    // Summary section และ ช่องลงชื่อแบบ 2 คอลัมน์
    $pdf->Ln(5);

    // เก็บตำแหน่ง Y ปัจจุบัน
    $currentY = $pdf->GetY();

    // คอลัมน์ซ้าย - สรุปรายงาน
    $pdf->SetXY(10, $currentY);
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(95, 8, thai('สรุปรายงาน'), 0, 1, 'L');
    $pdf->SetFont('THSarabunNew', '', 11);
    $pdf->SetX(10);
    $pdf->Cell(95, 6, thai('จำนวนรายการสินค้าตรวจสอบทั้งหมด:') . count($stock_checks) . thai(' รายการ'), 0, 1, 'L');

    if (count($stock_checks) > 0) {
        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('จำนวนสินค้าคงเหลือจากระบบ:') . number_format($total_system), 0, 1, 'L');

        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('จำนวนที่นับได้จริง: ') . number_format($total_actual), 0, 1, 'L');


        // คำนวณยอดรวมติดลบ (รายการที่มีส่วนต่างติดลบ)
        $negative_total = 0;
        $positive_total = 0;
        foreach ($stock_checks as $stock) {
            $system_qty = (int) ($stock['stock_check_system'] ?? 0);
            $actual_qty = (int) ($stock['stock_check_actual'] ?? 0);

            $difference = $actual_qty - $system_qty;

            if ($difference < 0) {
                $negative_total += $difference;
            } else if ($difference > 0) {
                $positive_total += $difference;
            }
        }

        if ($positive_total > 0) {
            $pdf->SetX(10);
            $pdf->Cell(95, 6, thai('สินค้าที่เกิน: +') . number_format($positive_total), 0, 1, 'L');
        }
        if ($negative_total < 0) {
            $pdf->SetX(10);
            $pdf->Cell(95, 6, thai('สินค้าที่ขาด: ') . number_format(abs($negative_total)), 0, 1, 'L');
        }
    }

    // คอลัมน์ขวา - ช่องลงชื่อ
    $pdf->SetXY(110, $currentY + 10); // เริ่มจากตำแหน่งเดียวกันกับสรุปรายงาน + ระยะห่าง
    $pdf->SetFont('THSarabunNew', '', 11);
    $pdf->Cell(80, 8, thai('ลงชื่อ........................................................'), 0, 1, 'C');
    $pdf->SetX(120);
    $pdf->Cell(60, 6, thai('ผู้ตรวจสอบสินค้า'), 0, 1, 'C');
    $pdf->SetX(110);
    $pdf->Cell(80, 8, thai('(........................................................)'), 0, 1, 'C');
    $pdf->SetX(110);
    $pdf->Cell(80, 8, thai('วันที่ (................./.................../..................)'), 0, 1, 'C');

    // แสดงผล PDF
    $filename = 'StockCheckReport_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('D', $filename); // Change to 'D' for download
} catch (PDOException $e) {
    $pdf = new THPDF('P');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . thai(htmlspecialchars($e->getMessage())), 0, 1, 'C');
    ob_end_clean();
    $pdf->Output('I', 'Error_Report.pdf'); // Output error as PDF
}