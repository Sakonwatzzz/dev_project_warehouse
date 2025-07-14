<?php
require_once __DIR__ . '../../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../fpdf186/fpdf.php';
session_start();
date_default_timezone_set('Asia/Bangkok');

$pdo = Database::getInstance()->getConnection(); 

// Session timeout check
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: /?page=login&timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// CSRF token verification
function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Convert text to TIS-620 for Thai characters
function thai($text)
{
    return iconv('UTF-8', 'TIS-620//IGNORE', $text);
}

class THPDF extends FPDF
{
    public $lot_products = [];

    function Header()
    {
        $this->AddFont('THSarabunNew', '', 'THSarabunNew.php');
        $this->AddFont('THSarabunNew', 'B', 'THSarabunNew_b.php');

        $this->SetFont('THSarabunNew', 'B', 16);
        $lot_name = isset($_GET['lot_name']) ? htmlspecialchars($_GET['lot_name']) : 'ทั้งหมด';
        $this->Cell(0, 10, thai('รายงานรายการรับสินค้า' . ($lot_name !== 'ทั้งหมด' ? ': ' . $lot_name : '')), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 11);
        $this->Cell(0, 6, thai('วันที่ออกรายงาน ณ เวลา: ') . date('d/m/Y H:i:s'), 0, 1, 'L');

        // Display filters
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

        // Stock details (if available)
        if (isset($this->lot_products) && count($this->lot_products) > 0) {
            $lot_product = $this->lot_products[0];
            if (!empty($lot_product['supplier_name'])) {
                $this->Cell(100, 6, thai('ผู้ส่งมอบ: ' . $lot_product['supplier_name']), 0, 0, 'L');
            }
        }
        $this->Ln(7);

        // Line separator
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);

        // Table headers (ตัดคอลัมน์ส่วนต่างออก)
        $this->SetFont('THSarabunNew', 'B', 11);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'L', true);
        $this->Cell(40, 8, thai('ชื่อล็อตสินค้า'), 1, 0, 'C', true);
        $this->Cell(55, 8, thai('ชื่อสินค้า'), 1, 0, 'C', true);
        $this->Cell(25, 8, thai('ราคาต้นทุน'), 1, 0, 'C', true);
        $this->Cell(25, 8, thai('จำนวนเข้า'), 1, 0, 'C', true);
        $this->Cell(25, 8, thai('คงเหลือ'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 9);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function SetLotProducts($data)
    {
        $this->lot_products = $data;
    }
}

try {
    // Validate CSRF token
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
        error_log("CSRF Validation Failed: Session Token: " . ($_SESSION['csrf_token'] ?? 'unset') . ", GET Token: " . ($_GET['csrf_token'] ?? 'unset'));
        header('HTTP/1.1 403 Forbidden');
        exit('Invalid CSRF token');
    }

    // Get lot ID and validate
    $lot_id = isset($_GET['lot_id']) ? (int)$_GET['lot_id'] : 0;
    $lot_name = "";
    if ($lot_id) {
        $stmt = $pdo->prepare("SELECT lot_name FROM lot WHERE lot_id = ?");
        $stmt->execute([$lot_id]);
        $lot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lot) {
            throw new Exception('ไม่พบข้อมูลล็อต');
        }
        $lot_name = $lot['lot_name'];
    }
    $_GET['lot_name'] = $lot_name;

    // Handle search and date parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    // Build query
    $query = "
        SELECT
            lp.lot_product_id,
            l.lot_name,
            p.product_id,
            p.product_name,
            lp.lot_product_qty,
            lp.lot_product_remain,
            lp.lot_product_capital,
            lp.lot_product_date,
            lp.lot_product_status,
            s.supplier_name
        FROM lot_product lp
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN product p ON lp.product_id = p.product_id
        LEFT JOIN supplier s ON l.supplier_id = s.supplier_id";
    $params = [];
    if ($lot_id || !empty($search) || $start_date || $end_date) {
        $query .= " WHERE";
        $conditions = [];
        if ($lot_id) {
            $conditions[] = " lp.lot_id = :lot_id";
            $params[':lot_id'] = $lot_id;
        }
        if (!empty($search)) {
            $conditions[] = " (lp.lot_product_id LIKE :search OR p.product_name LIKE :search)";
            $params[':search'] = "%$search%";
        }
        if ($start_date) {
            $conditions[] = " lp.lot_product_date >= :start_date";
            $params[':start_date'] = "$start_date 00:00:00";
        }
        if ($end_date) {
            $conditions[] = " lp.lot_product_date <= :end_date";
            $params[':end_date'] = "$end_date 23:59:59";
        }
        $query .= " " . implode(" AND ", $conditions);
    }
    $query .= " ORDER BY lp.lot_product_date DESC";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $lot_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize PDF
    $pdf = new THPDF('P');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->SetLotProducts($lot_products);
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 10);

    // Initialize totals
    $total_qty = 0;
    $total_remain = 0;
    $total_cost = 0;

    if (count($lot_products) > 0) {
        foreach ($lot_products as $lot_product) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Repeat table headers
                $pdf->SetFont('THSarabunNew', 'B', 11);
                $pdf->SetFillColor(200, 216, 237);
                $pdf->Cell(25, 8, thai('รหัสสินค้า'), 1, 0, 'L', true);
                $pdf->Cell(40, 8, thai('ชื่อล็อตสินค้า'), 1, 0, 'C', true);
                $pdf->Cell(55, 8, thai('ชื่อสินค้า'), 1, 0, 'C', true);
                $pdf->Cell(25, 8, thai('ราคาต้นทุน'), 1, 0, 'C', true);
                $pdf->Cell(25, 8, thai('จำนวนเข้า'), 1, 0, 'C', true);
                $pdf->Cell(25, 8, thai('คงเหลือ'), 1, 1, 'C', true);
                $pdf->SetFont('THSarabunNew', '', 10);
            }

            $qty = (int)$lot_product['lot_product_qty'];
            $remain = (int)$lot_product['lot_product_remain'];
            $capital = (float)$lot_product['lot_product_capital'];

            $total_qty += $qty;
            $total_remain += $remain;
            $total_cost += $capital * $qty;

            // Handle long product names
            $product_name = $lot_product['product_name'];
            if (strlen($product_name) > 255) {
                $product_name = substr($product_name, 0, 252) . '...';
            }

            $pdf->Cell(25, 7, thai($lot_product['product_id']), 1, 0, 'L');
            $pdf->Cell(40, 7, thai($lot_product['lot_name']), 1, 0, 'L');
            $pdf->Cell(55, 7, thai($product_name), 1, 0, 'L');
            $pdf->Cell(25, 7, thai(number_format($capital, 2)), 1, 0, 'R');
            $pdf->Cell(25, 7, thai(number_format($qty)), 1, 0, 'C');
            $pdf->Cell(25, 7, thai(number_format($remain)), 1, 1, 'C');
        }

        // Summary row
        $pdf->Ln(2);
        $pdf->SetFont('THSarabunNew', 'B', 11);
        $pdf->Cell(120, 8, thai('รวมทั้งหมด'), 1, 0, 'R');
        $pdf->Cell(25, 8, thai(number_format($total_cost, 2)), 1, 0, 'R');
        $pdf->Cell(25, 8, thai(number_format($total_qty)), 1, 0, 'C');
        $pdf->Cell(25, 8, thai(number_format($total_remain)), 1, 1, 'C');

        // Summary section and signature in two-column layout
        $pdf->Ln(5);
        $currentY = $pdf->GetY();

        // Left column - Summary
        $pdf->SetXY(10, $currentY);
        $pdf->SetFont('THSarabunNew', 'B', 12);
        $pdf->Cell(95, 8, thai('สรุปรายงาน'), 0, 1, 'L');
        $pdf->SetFont('THSarabunNew', '', 11);
        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('จำนวนรายการทั้งหมด: ') . count($lot_products) . thai(' รายการ'), 0, 1, 'L');
        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('ราคาต้นทุนรวม: ') . number_format($total_cost, 2), 0, 1, 'L');
        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('จำนวนเข้า: ') . number_format($total_qty), 0, 1, 'L');
        $pdf->SetX(10);
        $pdf->Cell(95, 6, thai('จำนวนคงเหลือ: ') . number_format($total_remain), 0, 1, 'L');

    } else {
        $pdf->Cell(195, 8, thai('ไม่พบข้อมูลรายการรับสินค้า'), 1, 1, 'C');
    }

    // Output PDF
    $filename = 'LotProductReport_' . htmlspecialchars($lot_name ?: 'All') . '_' . date('Ymd_His') . '.pdf';
    ob_end_clean();
    $pdf->Output('I', $filename);
} catch (Exception $e) {
    $pdf = new THPDF('P');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', 'B', 12);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาด: ') . thai(htmlspecialchars($e->getMessage())), 0, 1, 'C');
    ob_end_clean();
    $pdf->Output();
}
