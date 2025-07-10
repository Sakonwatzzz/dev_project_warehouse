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

        // Title
        $this->SetFont('THSarabunNew', 'B', 22);
        $lot_name = isset($_GET['lot_name']) ? htmlspecialchars($_GET['lot_name']) : 'ทั้งหมด';
        $this->Cell(0, 12, thai('รายงานรายการย้ายสินค้า' . ($lot_name !== 'ทั้งหมด' ? ' - Lot: ' . $lot_name : '')), 0, 1, 'C');

        // Report date
        $this->SetFont('THSarabunNew', '', 14);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'L');

        // // Status
        // $status_text = isset($_GET['status_text']) ? htmlspecialchars($_GET['status_text']) : 'N/A';
        // $this->Cell(0, 8, thai('สถานะ: ' . $status_text), 0, 1, 'L');

        // Personnel
        $personnel_text = isset($_GET['personnel_text']) ? htmlspecialchars($_GET['personnel_text']) : 'N/A';
        $this->Cell(0, 8, thai('พนักงาน: ' . $personnel_text), 0, 1, 'L');

        // // Receive date
        // $date_text = isset($_GET['date_text']) ? htmlspecialchars($_GET['date_text']) : 'N/A';
        // $this->Cell(0, 8, thai('วันที่รับสินค้า: ' . $date_text), 0, 1, 'L');

        // Filters
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

        // Supplier information (if available)
        if (isset($this->lot_products) && count($this->lot_products) > 0) {
            $lot_product = $this->lot_products[0];
            if (!empty($lot_product['supplier_name'])) {
                $this->Cell(0, 8, thai('ผู้ส่งมอบ: ' . $lot_product['supplier_name']), 0, 1, 'L');
            }
        }

        $this->Ln(2);
        // Table headers
        $this->SetFont('THSarabunNew', 'B', 16);
        $this->SetFillColor(200, 216, 237);
        $this->Cell(25, 10, thai('รหัสสินค้า'), 1, 0, 'C', true);
        $this->Cell(35, 10, thai('ชื่อล็อตสินค้า'), 1, 0, 'C', true);
        $this->Cell(45, 10, thai('ชื่อสินค้า'), 1, 0, 'C', true);
        $this->Cell(25, 10, thai('ราคาต้นทุน'), 1, 0, 'C', true);
        $this->Cell(30, 10, thai('จำนวนเข้า'), 1, 0, 'C', true);
        $this->Cell(30, 10, thai('คงเหลือ'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 12);
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
        throw new Exception('Invalid CSRF token');
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
            s.supplier_name,
            pn.personnel_name
        FROM lot_product lp
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN product p ON lp.product_id = p.product_id
        LEFT JOIN supplier s ON l.supplier_id = s.supplier_id
        LEFT JOIN personnel pn ON l.personnel_id = pn.personnel_id";
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

    // Determine header information
    $status_text = count($lot_products) > 0 ? array_unique(array_column($lot_products, 'lot_product_status')) : ['N/A'];
    $status_text = count($status_text) === 1 ? $status_text[0] : 'หลากหลาย';
    $personnel_text = count($lot_products) > 0 ? array_unique(array_column($lot_products, 'personnel_name')) : ['N/A'];
    $personnel_text = count($personnel_text) === 1 ? $personnel_text[0] : 'หลากหลาย';
    $date_text = count($lot_products) > 0 ? array_unique(array_column($lot_products, 'lot_product_date')) : ['N/A'];
    $date_text = count($date_text) === 1 ? $date_text[0] : ($start_date && $end_date ? "$start_date ถึง $end_date" : 'หลากหลาย');
    $_GET['status_text'] = $status_text;
    $_GET['personnel_text'] = $personnel_text;
    $_GET['date_text'] = $date_text;

    // Initialize PDF
    $pdf = new THPDF('P');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AliasNbPages();
    $pdf->SetLotProducts($lot_products);
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 14);

    // Initialize totals
    $total_qty = 0;
    $total_remain = 0;
    $total_cost = 0;

    if (count($lot_products) > 0) {
        foreach ($lot_products as $lot_product) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
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

            $pdf->Cell(25, 10, thai($lot_product['product_id']), 1, 0, 'C');
            $pdf->Cell(35, 10, thai($lot_product['lot_name']), 1, 0, 'L');
            $pdf->Cell(45, 10, thai($product_name), 1, 0, 'L');
            $pdf->Cell(25, 10, thai(number_format($capital, 2)), 1, 0, 'R');
            $pdf->Cell(30, 10, thai(number_format($qty)), 1, 0, 'R');
            $pdf->Cell(30, 10, thai(number_format($remain)), 1, 1, 'R');
        }

        // Summary row
        $pdf->SetFont('THSarabunNew', 'B', 14);
        $pdf->Cell(105, 10, thai('รวมทั้งหมด'), 1, 0, 'R');
        $pdf->Cell(25, 10, thai(number_format($total_cost, 2)), 1, 0, 'R');
        $pdf->Cell(30, 10, thai(number_format($total_qty)), 1, 0, 'R');
        $pdf->Cell(30, 10, thai(number_format($total_remain)), 1, 1, 'R');

        // Summary section
        $pdf->Ln(8);
        $pdf->SetFont('THSarabunNew', 'B', 14);
        $pdf->Cell(0, 8, thai('สรุปรายงาน'), 0, 1, 'L');
        $pdf->SetFont('THSarabunNew', '', 14);
        $pdf->Cell(0, 8, thai('จำนวนรายการทั้งหมด: ') . count($lot_products) . thai(' รายการ'), 0, 1, 'L');
        $pdf->Cell(0, 8, thai('ราคาต้นทุนรวม: ') . number_format($total_cost, 2), 0, 1, 'L');
        $pdf->Cell(0, 8, thai('จำนวนเข้า: ') . number_format($total_qty), 0, 1, 'L');
        $pdf->Cell(0, 8, thai('จำนวนคงเหลือ: ') . number_format($total_remain), 0, 1, 'L');

        // // Note
        // $pdf->Ln(8);
        // $pdf->SetFont('THSarabunNew', '', 14);
        // $pdf->MultiCell(0, 8, thai("หมายเหตุ: รายงานนี้จัดทำขึ้นโดยระบบอัตโนมัติเพื่อใช้สำหรับอ้างอิงข้อมูลรายการรับสินค้าเท่านั้น"), 0, 'L');
    } else {
        $pdf->Cell(190, 10, thai('ไม่พบข้อมูลรายการรับสินค้า'), 1, 1, 'C');
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
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาด: ') . thai(htmlspecialchars($e->getMessage())), 0, 1, 'C');
    ob_end_clean();
    $pdf->Output();
}
