<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';
session_start();
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection();

// Function to convert text to TIS-620
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
        $this->SetFont('THSarabunNew', 'B', 14);

        // Title
        $warehouse_title = isset($this->stock_data[0]['warehouse_name']) ? ': ' . $this->stock_data[0]['warehouse_name'] : '';
        $this->Cell(0, 10, thai('รายงานการเช็คสต๊อกสินค้า' . $warehouse_title), 0, 1, 'C');

        // Report date
        $this->SetFont('THSarabunNew', '', 11);
        $this->Cell(0, 6, thai('วันที่ออกรายงาน ณ เวลา: ') . date('d/m/Y H:i:s'), 0, 1, 'L');

        // Personnel name
        if (!empty($this->stock_data) && isset($this->stock_data[0]['personnel_name'])) {
            $this->Cell(100, 5, thai('ผู้ตรวจสอบ: ' . $this->stock_data[0]['personnel_name']), 0, 0, 'L');
        }
        $this->Ln(7);

        // Line separator
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);

        // Table headers
        $this->SetFont('THSarabunNew', 'B', 11);
        $this->SetFillColor(200, 216, 255);
        $this->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'L', true);
        $this->Cell(75, 8, thai('ชื่อสินค้า'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('จากระบบ'), 1, 0, 'C', true);
        $this->Cell(20, 8, thai('นับได้'), 1, 0, 'C', true);
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
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {

    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'stock_list'] + ($_GET ?? []));
    header("Location: /page/stock/login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Verify CSRF token
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data || !isset($data['csrf_token']) || !verifyCsrfToken($data['csrf_token'])) {
    error_log("CSRF Validation Failed: Session Token: " . ($_SESSION['csrf_token'] ?? 'unset') . ", POST Token: " . ($data['csrf_token'] ?? 'unset'));
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Handle search parameters
$search = isset($data['search']) ? trim($data['search']) : '';
$start_date = isset($data['start_date']) ? htmlspecialchars($data['start_date']) : '';
$end_date = isset($data['end_date']) ? htmlspecialchars($data['end_date']) : '';
$date_range = isset($data['date_range']) ? htmlspecialchars($data['date_range']) : 'this_year';

if ($date_range === 'custom' && empty($start_date)) {
    $start_date = date('Y-m-d');
}
if ($date_range === 'custom' && empty($end_date)) {
    $end_date = date('Y-m-d');
}

// Validate date format
if ($date_range === 'custom' && $start_date && $end_date) {
    try {
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        if ($start > $end) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'วันที่เริ่มต้นต้องไม่มากกว่าวันที่สิ้นสุด']);
            exit;
        }
    } catch (Exception $e) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'รูปแบบวันที่ไม่ถูกต้อง']);
        exit;
    }
}

// Build WHERE conditions and parameters
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(sc.stock_check_id LIKE :search OR p.product_name LIKE :search OR w.warehouse_name LIKE :search OR per.personnel_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($date_range === 'today') {
    $where_conditions[] = "DATE(s.stock_date) = CURDATE()";
} elseif ($date_range === 'this_month') {
    $where_conditions[] = "YEAR(s.stock_date) = YEAR(CURDATE()) AND MONTH(s.stock_date) = MONTH(CURDATE())";
} elseif ($date_range === 'this_year') {
    $where_conditions[] = "YEAR(s.stock_date) = YEAR(CURDATE())";
} elseif ($date_range === 'custom' && $start_date && $end_date) {
    $where_conditions[] = "DATE(s.stock_date) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}

$where_clause = !empty($where_conditions) ? 'WHERE s.check_status_id = 2 AND ' . implode(' AND ', $where_conditions) : 'WHERE s.check_status_id = 2';

try {
    $query = "
        SELECT 
            sc.stock_check_id,
            s.stock_id,
            s.stock_date,
            w.warehouse_name,
            sc.product_id AS stock_product_id,
            p.product_id AS product_table_id,
            COALESCE(p.product_name, 'ไม่ระบุ') AS product_name,
            sc.stock_check_system,
            sc.stock_check_actual,
            per.personnel_name,
            scs.check_status_name,
            sc.stock_check_remark_id,
            scr.stock_check_remark_name
        FROM stock_check sc
        INNER JOIN product p ON sc.product_id = p.product_id
        LEFT JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
        LEFT JOIN stock s ON sc.stock_id = s.stock_id
        LEFT JOIN personnel per ON s.personnel_id = per.personnel_id
        LEFT JOIN stock_check_status scs ON s.check_status_id = scs.check_status_id
        LEFT JOIN stock_check_remark scr ON sc.stock_check_remark_id = scr.stock_check_remark_id
        $where_clause
        ORDER BY s.stock_date DESC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stock_checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log query and results for debugging
    error_log("PDF Export Query: $query with params: " . json_encode($params));
    error_log("PDF Export Results: " . json_encode(array_map(function ($row) {
        return [
            'stock_check_id' => $row['stock_check_id'],
            'stock_product_id' => $row['stock_product_id'],
            'product_table_id' => $row['product_table_id'],
            'product_name' => $row['product_name']
        ];
    }, $stock_checks)));

    $pdf = new THPDF('P');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->SetStockData($stock_checks);
    $pdf->AddPage();

    $pdf->SetFont('THSarabunNew', '', 10);

    if (count($stock_checks) > 0) {
        $total_system = 0;
        $total_actual = 0;
        $negative_total = 0;
        $positive_total = 0;

        foreach ($stock_checks as $stock) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                $pdf->SetFont('THSarabunNew', 'B', 11);
                $pdf->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'L');
                $pdf->Cell(75, 8, thai('ชื่อสินค้า'), 1, 0, 'C');
                $pdf->Cell(20, 8, thai('จากระบบ'), 1, 0, 'C');
                $pdf->Cell(20, 8, thai('นับได้'), 1, 0, 'C');
                $pdf->Cell(20, 8, thai('ส่วนต่าง'), 1, 0, 'C');
                $pdf->Cell(25, 8, thai('หมายเหตุ'), 1, 1, 'C');
                $pdf->SetFont('THSarabunNew', '', 10);
            }

            $system_qty = (int) ($stock['stock_check_system'] ?? 0);
            $actual_qty = (int) ($stock['stock_check_actual'] ?? 0);
            $difference = $actual_qty - $system_qty;

            $total_system += $system_qty;
            $total_actual += $actual_qty;
            if ($difference < 0) {
                $negative_total += $difference;
            } elseif ($difference > 0) {
                $positive_total += $difference;
            }

            // Use stock_product_id from stock_check
            $product_id = $stock['stock_product_id'] ?? 'N/A';
            $product_name = $stock['product_name'];

            $pdf->Cell(25, 7, thai($product_id), 1, 0, 'L');
            $pdf->Cell(75, 7, thai($product_name), 1, 0, 'L');
            $pdf->Cell(20, 7, thai(number_format($system_qty)), 1, 0, 'C');
            $pdf->Cell(20, 7, thai(number_format($actual_qty)), 1, 0, 'C');

            $diff_text = ($difference > 0 ? '+' : '') . number_format($difference);
            if ($difference < 0) {
                $pdf->SetTextColor(139, 0, 0);
                $pdf->SetFont('THSarabunNew', 'B', 11);
            }
            $pdf->Cell(20, 7, thai($diff_text), 1, 0, 'C');
            if ($difference < 0) {
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('THSarabunNew', '', 10);
            }

            $remark = !empty($stock['stock_check_remark_name']) ? mb_substr($stock['stock_check_remark_name'], 0, 20, 'UTF-8') : '-';
            if ($stock['stock_check_remark_id'] == 2 && $stock['stock_check_remark_name'] === 'ขาดหาย') {
                $pdf->SetTextColor(139, 0, 0);
                $pdf->SetFont('THSarabunNew', 'B', 11);
            }
            $pdf->Cell(25, 7, thai($remark), 1, 1, 'L');
            if ($stock['stock_check_remark_id'] == 2 && $stock['stock_check_remark_name'] === 'ขาดหาย') {
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('THSarabunNew', '', 10);
            }
        }

        // Summary row
        $pdf->Ln(2);
        $pdf->SetFont('THSarabunNew', 'B', 11);
        $total_difference = $total_actual - $total_system;

        $pdf->Cell(100, 8, thai('รวมทั้งหมด'), 1, 0, 'R');
        $pdf->Cell(20, 8, thai(number_format($total_system)), 1, 0, 'C');
        $pdf->Cell(20, 8, thai(number_format($total_actual)), 1, 0, 'C');
        $diff_total = ($total_difference > 0 ? '+' : '') . number_format($total_difference);
        if ($total_difference < 0) {
            $pdf->SetTextColor(139, 0, 0);
            $pdf->SetFont('THSarabunNew', 'B', 11);
        }
        $pdf->Cell(20, 8, thai($diff_total), 1, 0, 'C');
        if ($total_difference < 0) {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('THSarabunNew', '', 10);
        }
        $pdf->Cell(25, 8, '', 1, 1, 'C');

        // Summary section
        $pdf->Ln(5);
        $currentY = $pdf->GetY();

        // Left column - Summary
        $pdf->SetXY(10, $currentY);
        $pdf->SetFont('THSarabunNew', 'B', 12);
        $pdf->Cell(95, 8, thai('สรุปรายงาน'), 0, 1, 'L');
        $pdf->SetFont('THSarabunNew', '', 11);
        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('จำนวนรายการสินค้าตรวจสอบทั้งหมด: ') . count($stock_checks) . thai(' รายการ'), 0, 1, 'L');
        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('จำนวนสินค้าคงเหลือจากรายการที่ตรวจสอบจากระบบ: ') . number_format($total_system) . thai(' ชิ้น'), 0, 1, 'L');
        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('จำนวนสินค้าที่นับได้จริงรวมทั้งหมด: ') . number_format($total_actual) . thai(' ชิ้น'), 0, 1, 'L');
        if ($positive_total > 0) {
            $pdf->SetX(10);
            $pdf->Cell(95, 6, thai('จำนวนสินค้าที่เกินรวมทั้งหมด: +') . number_format($positive_total) . thai(' ชิ้น'), 0, 1, 'L');
        }
        if ($negative_total < 0) {
            $pdf->SetX(10);
            $pdf->Cell(95, 6, thai('จำนวนสินค้าที่ขาดรวมทั้งหมด: ') . number_format(abs($negative_total)) . thai(' ชิ้น'), 0, 1, 'L');
        }

        // Right column - Signature
        $pdf->SetXY(110, $currentY + 10);
        $pdf->SetFont('THSarabunNew', '', 11);
        $pdf->Cell(80, 8, thai('ลงชื่อ........................................................'), 0, 1, 'C');
        $pdf->SetX(120);
        $pdf->Cell(60, 6, thai('ผู้ตรวจสอบสินค้า'), 0, 1, 'C');
        $pdf->SetX(110);
        $pdf->Cell(80, 8, thai('(........................................................)'), 0, 1, 'C');
        $pdf->SetX(110);
        $pdf->Cell(80, 8, thai('วันที่ (................./.................../..................)'), 0, 1, 'C');
    } else {
        $pdf->Cell(190, 8, thai('ไม่พบข้อมูลการเช็คสต๊อก'), 1, 1, 'C');
    }

    // Output PDF
    $filename = 'StockCheckReport_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);
} catch (PDOException $e) {
    error_log("Error in export_stock_pdf.php: " . $e->getMessage());
    $pdf = new THPDF('P');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . thai(htmlspecialchars($e->getMessage())), 0, 1, 'C');
    ob_end_clean();
    $pdf->Output();
}
