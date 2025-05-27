<?php
require_once __DIR__ . '../../../../../../config/database_server.php';
require_once __DIR__ . '../../../../../fpdf186/fpdf.php';
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
        $lot_name = isset($_GET['lot_name']) ? htmlspecialchars($_GET['lot_name']) : 'ทั้งหมด';
        $this->Cell(0, 12, thai('รายงานรายการรับสินค้า' . ($lot_name !== 'ทั้งหมด' ? ' - Lot: ' . $lot_name : '')), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 14);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        $status_text = isset($_GET['status_text']) ? htmlspecialchars($_GET['status_text']) : 'N/A';
        $this->Cell(0, 8, thai('สถานะ: ' . $status_text), 0, 1, 'C');

        $supplier_text = isset($_GET['supplier_text']) ? htmlspecialchars($_GET['supplier_text']) : 'N/A';
        $this->Cell(0, 8, thai('ผู้ส่งมอบ: ' . $supplier_text), 0, 1, 'C');

        $date_text = isset($_GET['date_text']) ? htmlspecialchars($_GET['date_text']) : 'N/A';
        $this->Cell(0, 8, thai('วันที่รับสินค้า: ' . $date_text), 0, 1, 'C');

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

        // ขนาดรวม = 190 mm พอดีกับ A4 แนวตั้ง
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
}


try {
    // Validate CSRF token
    if (!isset($_GET['csrf_token']) || !verifyCsrfToken($_GET['csrf_token'])) {
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

    // Determine header information (status, supplier, date)
    $status_text = count($lot_products) > 0 ? array_unique(array_column($lot_products, 'lot_product_status')) : ['N/A'];
    $status_text = count($status_text) === 1 ? $status_text[0] : 'หลากหลาย';
    $supplier_text = count($lot_products) > 0 ? array_unique(array_column($lot_products, 'supplier_name')) : ['N/A'];
    $supplier_text = count($supplier_text) === 1 ? $supplier_text[0] : 'หลากหลาย';
    $date_text = count($lot_products) > 0 ? array_unique(array_column($lot_products, 'lot_product_date')) : ['N/A'];
    $date_text = count($date_text) === 1 ? $date_text[0] : ($start_date && $end_date ? "$start_date ถึง $end_date" : 'หลากหลาย');
    $_GET['status_text'] = $status_text;
    $_GET['supplier_text'] = $supplier_text;
    $_GET['date_text'] = $date_text;

    $pdf = new THPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 14);

    foreach ($lot_products as $lot_product) {
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
        }
        $pdf->Cell(25, 10, thai($lot_product['lot_product_id']), 1, 0, 'C');
        $pdf->Cell(35, 10, thai($lot_product['lot_name']), 1, 0, 'L');
        $pdf->Cell(45, 10, thai($lot_product['product_name']), 1, 0, 'L');
        $pdf->Cell(25, 10, thai($lot_product['lot_product_capital']), 1, 0, 'R');
        $pdf->Cell(30, 10, thai($lot_product['lot_product_qty']), 1, 0, 'R');
        $pdf->Cell(30, 10, thai($lot_product['lot_product_remain']), 1, 1, 'R');
    }


    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(190, 10, thai('รวมทั้งหมด: ') . count($lot_products) . thai(' รายการ'), 1, 1, 'R');

    $pdf->Ln(8);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->MultiCell(0, 8, thai("หมายเหตุ: รายงานนี้จัดทำขึ้นโดยระบบอัตโนมัติเพื่อใช้สำหรับอ้างอิงข้อมูลรายการรับสินค้าเท่านั้น"), 0, 'L');

    $filename = 'LotProductReport_' . htmlspecialchars($lot_name ?: 'All') . '_' . date('Ymd_His') . '.pdf';
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
