<?php
require_once __DIR__ . '../../../../../config/database_server.php';
require_once __DIR__ . '../../../../fpdf186/fpdf.php';
$pdo = Database::getInstance()->getConnection();

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

        $this->SetFont('THSarabunNew', 'B', 20);
        $this->Cell(0, 10, thai('รายงานร้านค้า'), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 8, thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'C');

        $this->SetFont('THSarabunNew', '', 14);
        $start_date = $_GET['start_date'] ?? '';
        $end_date   = $_GET['end_date'] ?? '';
        $date_range = '';

        if ($start_date && $end_date) {
            $date_range = thai("ช่วงวันที่: $start_date ถึง $end_date");
        } elseif ($start_date) {
            $date_range = thai("ตั้งแต่วันที่: $start_date");
        } elseif ($end_date) {
            $date_range = thai("ถึงวันที่: $end_date");
        }

        $this->Cell(0, 10, $date_range ?: thai('วันที่ออกรายงาน: ') . date('d/m/Y H:i:s'), 0, 1, 'L');
        $this->Ln(3);
        $this->SetDrawColor(150, 150, 150);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);

        $this->SetFont('THSarabunNew', 'B', 14);
        $this->SetFillColor(200, 216, 237);

        // Header Table
        $this->Cell(30, 8, thai('รหัสร้านค้า'), 1, 0, 'C', true);
        $this->Cell(100, 8, thai('ชื่อร้านค้า'), 1, 0, 'C', true);
        $this->Cell(60, 8, thai('วันที่สร้าง'), 1, 1, 'C', true);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('THSarabunNew', '', 12);
        $this->Cell(0, 10, thai('หน้า ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Calculate number of lines for MultiCell
    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ')
                $sep = $i;
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j)
                        $i++;
                } else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

try {
    $start_date = trim($_GET['start_date'] ?? '');
    $end_date   = trim($_GET['end_date'] ?? '');

    $query = "
        SELECT 
            st.store_id,
            st.store_name,
            st.store_create_date
        FROM store st";

    $params = [];
    if ($start_date || $end_date) {
        $query .= " WHERE 1=1";
        if ($start_date) {
            $query .= " AND st.store_create_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND st.store_create_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
    }

    $query .= " ORDER BY st.store_id";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pdf = new THPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('THSarabunNew', '', 13);

    foreach ($stores as $store) {
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }

        // Calculate row height based on store name
        $lineHeight = 7;
        $lines = $pdf->NbLines(100, thai($store['store_name']));
        $rowHeight = $lineHeight * $lines;

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Draw cells
        $pdf->MultiCell(30, $rowHeight, thai($store['store_id']), 1, 'C');
        $pdf->SetXY($x + 30, $y);
        $pdf->MultiCell(100, $lineHeight, thai($store['store_name']), 1, 'L');
        $pdf->SetXY($x + 30 + 100, $y);
        $pdf->MultiCell(60, $rowHeight, thai((new DateTime($store['store_create_date']))->format('d/m/Y')), 1, 'C');
    }

    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, thai('รวมทั้งหมด: ') . count($stores) . thai(' รายการ'), 0, 1, 'R');

    $pdf->Ln(5);
    $pdf->Cell(0, 10, thai('สรุปรายงาน'), 0, 1);
    $pdf->SetFont('THSarabunNew', '', 14);
    $pdf->Cell(0, 10, thai('จำนวนร้านค้าทั้งหมด: ') . count($stores) . thai(' รายการ'), 0, 1);

    $filename = 'StoreReport_' . date('Ymd_His') . '.pdf';
    if (ob_get_contents()) ob_end_clean();
    $pdf->Output('I', $filename);
} catch (PDOException $e) {
    if (!isset($pdf)) {
        $pdf = new THPDF('P');
        $pdf->AliasNbPages();
        $pdf->AddPage();
    }
    $pdf->SetFont('THSarabunNew', 'B', 14);
    $pdf->Cell(0, 10, thai('เกิดข้อผิดพลาดในการดึงข้อมูล: ') . thai($e->getMessage()), 0, 1, 'C');
    $pdf->Output();
}
