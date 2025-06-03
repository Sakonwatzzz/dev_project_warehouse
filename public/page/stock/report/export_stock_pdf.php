<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';
session_start();
date_default_timezone_set('Asia/Bangkok');

// Function to convert text to TIS-620 or keep UTF-8
function thai($text)
{
    if (!is_string($text)) {
        return '';
    }
    $converted = iconv('UTF-8', 'TIS-620//IGNORE', $text);
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
        $this->SetFont('THSarabunNew', 'B', 14);

        // Title
        $warehouse_title = '';
        if (isset($this->stock_data[0]['warehouse_name'])) {
            $warehouse_title = ': ' . $this->stock_data[0]['warehouse_name'];
        }
        $this->Cell(0, 10, thai('รายงานการเช็คสต๊อกสินค้า' . $warehouse_title), 0, 1, 'C');

        // Report date
        $this->SetFont('THSarabunNew', '', 11);
        $this->Cell(0, 6, thai('วันที่ออกรายงานประจำวันและเวลาที่: ') . date('d/m/Y H:i:s'), 0, 1, 'L');

        // Stock details (if available)
        if (isset($this->stock_data) && count($this->stock_data) > 0) {
            $stock = $this->stock_data[0];
            if (!empty($stock['personnel_name'])) {
                $this->Cell(100, 6, thai('ผู้ตรวจสอบ: ' . $stock['personnel_name']), 0, 0, 'L');
            }
        }
        $this->Ln(7);

        // Line separator
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);

        // Table headers
        $this->SetFont('THSarabunNew', 'B', 11);
        $this->SetFillColor(200, 216, 537);
        $this->Cell(30, 8, thai('รหัสสินค้า'), 1, 0, 'L', true);
        $this->Cell(80, 8, thai('ชื่อสินค้า'), 1, 0, 'C', true);
        $this->Cell(25, 8, thai('นับได้'), 1, 0, 'C', true);
        $this->Cell(25, 8, thai('ส่วนต่าง'), 1, 0, 'C', true);
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
            sc.product_id,
            p.product_name,
            sc.lot_id,
            sc.warehouse_id,
            w.warehouse_name,
            sc.stock_check_comment,
            per.personnel_name,
            sc.lot_product_id,
            lp.lot_product_remain,
            s.stock_id,
            s.stock_date,
            sc.stock_check_system,
            sc.stock_check_actual,  
            s.check_status_id,
            scs.check_status_name,
            scr.stock_check_remark_id,
            scr.stock_check_remark_name
        FROM stock_check AS sc
        LEFT JOIN product p ON sc.product_id = p.product_id
        LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
        LEFT JOIN lot l ON sc.lot_id = l.lot_id
        LEFT JOIN lot_product lp ON sc.lot_product_id = lp.lot_product_id
        LEFT JOIN stock s ON sc.stock_id = s.stock_id
        LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
        LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
        LEFT JOIN stock_check_remark scr ON sc.stock_check_remark_id = scr.stock_check_remark_id
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

    error_log("Query: $query");
    error_log("Params: " . json_encode($params));

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_checks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Fetched " . count($stock_checks) . " rows");

    $pdf = new THPDF('P');
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
                $pdf->SetFont('THSarabunNew', 'B', 11);
                $pdf->Cell(30, 8, thai('รหัสสินค้า'), 1, 0, 'L');
                $pdf->Cell(80, 8, thai('ชื่อสินค้า'), 1, 0, 'C');
                $pdf->Cell(25, 8, thai('นับได้'), 1, 0, 'C');
                $pdf->Cell(25, 8, thai('ส่วนต่าง'), 1, 0, 'C');
                $pdf->Cell(30, 8, thai('หมายเหตุ'), 1, 1, 'C');
                $pdf->SetFont('THSarabunNew', '', 10);
            }

            $system_qty = (int)$stock['stock_check_system'];
            $actual_qty = (int)$stock['stock_check_actual'];
            $difference = $actual_qty - $system_qty;

            $total_system += $system_qty;
            $total_actual += $actual_qty;

            $product_name = $stock['product_name'];
            if (strlen($product_name) > 255) {
                $product_name = mb_substr($product_name, 0, 252, 'UTF-8') . '...';
            }

            $pdf->Cell(30, 7, thai($stock['product_id']), 1, 0, 'L');
            $pdf->Cell(80, 7, thai($product_name), 1, 0, 'L');
            $pdf->Cell(25, 7, thai(number_format($actual_qty)), 1, 0, 'C');

            $diff_text = ($difference > 0 ? '+' : '') . number_format($difference);

            if ($difference < 0) {
                $pdf->SetTextColor(139, 0, 0);
                $pdf->SetFont('THSarabunNew', 'B', 11);
            }

            $pdf->Cell(25, 7, thai($diff_text), 1, 0, 'C');

            if ($difference < 0) {
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('THSarabunNew', '', 11);
            }

            $remark = !empty($stock['stock_check_remark_name']) ? mb_substr($stock['stock_check_remark_name'], 0, 13, 'UTF-8') : 'ไม่มีหมายเหตุ';
            error_log("stock_check_id: {$stock['stock_check_id']}, raw_remark: " . ($stock['stock_check_remark_name'] ?? 'NULL') . ", converted_remark: " . thai($stock['stock_check_remark_name'] ?? 'NULL') . ", final_remark: $remark");
            $pdf->Cell(30, 7, thai($remark), 1, 1, 'L');
        }

        $pdf->Ln(2);
        $pdf->SetFont('THSarabunNew', 'B', 11);
        $total_difference = $total_actual - $total_system;

        $pdf->Cell(110, 8, thai('รวมทั้งหมด'), 1, 0, 'R');
        $pdf->Cell(25, 8, thai(number_format($total_actual)), 1, 0, 'C');
        $diff_total = ($total_difference > 0 ? '+' : '') . number_format($total_difference);
        $pdf->Cell(25, 8, thai($diff_total), 1, 0, 'C');
        $pdf->Cell(30, 8, '', 1, 1, 'C');
    } else {
        $pdf->Cell(190, 8, thai('ไม่พบข้อมูลการเช็คสต๊อก'), 1, 1, 'C');
    }

    $pdf->Ln(5);
    $currentY = $pdf->GetY();

    $pdf->SetXY(10, $currentY);
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(95, 8, thai('สรุปรายงาน'), 0, 1, 'L');
    $pdf->SetFont('THSarabunNew', '', 11);
    $pdf->SetX(10);
    $pdf->Cell(95, 6, thai('จำนวนรายการทั้งหมด: ') . count($stock_checks) . thai(' รายการ'), 0, 1, 'L');

    if (count($stock_checks) > 0) {
        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('จำนวนนับจริง: ') . number_format($total_actual), 0, 1, 'L');

        $negative_total = 0;
        $positive_total = 0;
        foreach ($stock_checks as $stock) {
            $system_qty = (int)$stock['stock_check_system'];
            $actual_qty = (int)$stock['stock_check_actual'];
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
            $pdf->Cell(95, 6, thai('สินค้าที่ขาด: ') . number_format($negative_total), 0, 1, 'L');
        }
    }

    $pdf->SetXY(110, $currentY + 10);
    $pdf->SetFont('THSarabunNew', '', 11);
    $pdf->Cell(80, 8, thai('ลงชื่อ........................................................'), 0, 1, 'C');
    $pdf->SetX(120);
    $pdf->Cell(60, 6, thai('ผู้ตรวจสอบ'), 0, 1, 'C');
    $pdf->SetX(110);
    $pdf->Cell(80, 8, thai('(........................................................)'), 0, 1, 'C');
    $pdf->SetX(110);
    $pdf->Cell(80, 8, thai('วันที่ (................./.................../..................)'), 0, 1, 'C');

    $filename = 'StockCheckReport_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);
} catch (PDOException $e) {
    error_log("PDOException in export_stock_pdf.php: " . $e->getMessage());
    $pdf = new THPDF('P');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . htmlspecialchars($e->getMessage()), 0, 1, 'C');
    ob_end_clean();
    $pdf->Output();
}
?>