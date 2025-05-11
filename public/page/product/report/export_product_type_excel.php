<?php
require_once __DIR__ . '../../../../../config/database_server.php';

// กำหนด header สำหรับไฟล์ Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="รายงานประเภทสินค้า_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// เพิ่ม BOM เพื่อรองรับภาษาไทย
echo "\xEF\xBB\xBF";

// ดึงข้อมูลประเภทสินค้า
try {
    $query = "SELECT 
        pt.product_type_id,
        pt.product_type_name,
        COALESCE(COUNT(p.product_id), 0) as product_count
        FROM product_type pt
        LEFT JOIN product p ON pt.product_type_id = p.product_type_id
        GROUP BY pt.product_type_id, pt.product_type_name
        ORDER BY pt.product_type_id";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $producttypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // เริ่มสร้างไฟล์ Excel ด้วย HTML Table
    echo '<html>';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '<style>';
    // สไตล์ที่ปรับปรุงใหม่
    echo 'body { font-family: Arial, Tahoma, sans-serif; font-size: 14px; }';
    echo 'table { border-collapse: collapse; width: 100%; margin: 10px 0; }';
    echo 'th, td { border: 1px solid #2c3e50; padding: 10px; }';
    echo 'th { background-color: #3498db; color: white; font-weight: bold; text-align: center; }';
    echo 'td { background-color: #ffffff; }';
    echo '.title { font-size: 20px; color: #2c3e50; text-align: center; margin: 20px 0; font-weight: bold; }';
    echo '.subtitle { color: #34495e; text-align: center; margin-bottom: 20px; }';
    echo '.right { text-align: right; }';
    echo '.left { text-align: left; }';
    echo '.center { text-align: center; }';
    echo '.summary { background-color: #ecf0f1; font-weight: bold; }';
    echo '.summary-table { width: 50%; margin: 20px auto; background-color: #f8f9fa; }';
    echo '.summary-table th { background-color: #2ecc71; }';
    echo '.even-row { background-color: #f5f6fa; }';
    echo '.number { color: #e74c3c; font-weight: bold; }';
    echo '@media print { .no-print { display: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';

    // หัวข้อรายงาน
    echo '<div class="title">รายงานประเภทสินค้า</div>';
    echo '<div class="subtitle">วันที่ออกรายงาน: ' . date('d/m/Y H:i:s') . '</div>';

    // ตารางข้อมูลหลัก
    echo '<table>';
    echo '<tr>';
    echo '<th style="width: 15%;">รหัสประเภท</th>';
    echo '<th style="width: 50%;">ชื่อประเภทสินค้า</th>';
    echo '<th style="width: 35%;">จำนวนสินค้า</th>';
    echo '</tr>';

    // วนลูปแสดงข้อมูล
    $totalProducts = 0;
    $rowCount = 0;

    foreach ($producttypes as $type) {
        $rowClass = ($rowCount % 2 == 0) ? 'even-row' : '';
        echo '<tr class="' . $rowClass . '">';
        echo '<td class="center">' . $type['product_type_id'] . '</td>';
        echo '<td class="left">' . htmlspecialchars($type['product_type_name']) . '</td>';
        echo '<td class="center"><span class="number">' . number_format($type['product_count']) . '</span> รายการ</td>';
        echo '</tr>';

        $totalProducts += $type['product_count'];
        $rowCount++;
    }

    // แถวสรุปผลรวม
    echo '<tr class="summary">';
    echo '<td colspan="2" class="right">รวมทั้งหมด</td>';
    echo '<td class="center"><span class="number">' . number_format($totalProducts) . '</span> รายการ</td>';
    echo '</tr>';
    echo '</table>';

    // ตารางสรุป
    echo '<br>';
    echo '<table class="summary-table">';
    echo '<tr><th colspan="2">สรุปรายงาน</th></tr>';
    echo '<tr>';
    echo '<td class="left" style="width: 70%;">จำนวนประเภทสินค้าทั้งหมด</td>';
    echo '<td class="center"><span class="number">' . count($producttypes) . '</span> ประเภท</td>';
    echo '</tr>';
    echo '<tr>';
    echo '<td class="left">จำนวนสินค้าทั้งหมด</td>';
    echo '<td class="center"><span class="number">' . number_format($totalProducts) . '</span> รายการ</td>';
    echo '</tr>';
    echo '</table>';

    echo '</body>';
    echo '</html>';

} catch (PDOException $e) {
    // กรณีเกิดข้อผิดพลาด
    echo '<div style="color: #e74c3c; font-weight: bold; text-align: center; margin: 20px; padding: 15px; background-color: #ffebee;">';
    echo 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}