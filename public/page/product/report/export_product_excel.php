<?php
require_once __DIR__ . '../../../../../config/database_server.php';

// กำหนด header สำหรับไฟล์ Excel
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header("Content-Disposition: attachment; filename=product_report_" . date("Y-m-d") . ".xls");
header('Pragma: no-cache');
header('Expires: 0');

// เพิ่ม BOM เพื่อรองรับภาษาไทย
echo "\xEF\xBB\xBF";

// ดึงข้อมูลสินค้า
try {
    $query = "SELECT 
        product.product_id, 
        product.product_name, 
        product.product_price1, 
        product.product_price2, 
        product.product_price3, 
        product.product_price4, 
        product.product_capital, 
        product.product_type_id, 
        product.product_brand_id,
        COALESCE(product_brand.product_brand_name, 'ไม่มีข้อมูล') AS product_brand_name, 
        COALESCE(product_type.product_type_name, 'ไม่มีข้อมูล') AS product_type_name
    FROM product
    LEFT JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id
    LEFT JOIN product_type ON product.product_type_id = product_type.product_type_id";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo '<html>';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
    echo '<style>';
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
    echo '<div class="title">รายงานรายการสินค้า</div>';
    echo '<div class="subtitle">วันที่ออกรายงาน: ' . date('d/m/Y H:i:s') . '</div>';

    // ตารางข้อมูลหลัก
    echo '<table>';
    echo '<tr>';
    echo '<th>รหัสสินค้า</th>';
    echo '<th>ชื่อสินค้า</th>';
    echo '<th>ราคาขาย 1</th>';
    echo '<th>ราคาทุน</th>';
    echo '<th>ประเภทสินค้า</th>';
    echo '<th>ยี่ห้อ</th>';
    echo '</tr>';

    $totalProducts = 0;
    $rowCount = 0;

    foreach ($products as $product) {
        $rowClass = ($rowCount % 2 == 0) ? 'even-row' : '';
        echo '<tr class="' . $rowClass . '">';
        echo '<td class="left">' . htmlspecialchars($product['product_id']) . '</td>';
        echo '<td class="left">' . htmlspecialchars($product['product_name']) . '</td>';
        echo '<td class="right">' . number_format($product['product_price1'], 2) . '</td>';
        echo '<td class="right">' . number_format($product['product_capital'], 2) . '</td>';
        echo '<td class="left">' . htmlspecialchars($product['product_type_name']) . '</td>';
        echo '<td class="left">' . htmlspecialchars($product['product_brand_name']) . '</td>';
        echo '</tr>';
        $totalProducts++;
        $rowCount++;
    }

    // แถวสรุปจำนวนสินค้า
    echo '<tr class="summary">';
    echo '<td colspan="5" class="right">รวมจำนวนสินค้า</td>';
    echo '<td class="center"><span class="number">' . number_format($totalProducts) . '</span> รายการ</td>';
    echo '</tr>';
    echo '</table>';

    // สรุปย่อ
    echo '<br>';
    echo '<table class="summary-table">';
    echo '<tr><th colspan="2">สรุปรายงาน</th></tr>';
    echo '<tr>';
    echo '<td class="left" style="width: 70%;">จำนวนสินค้าทั้งหมด</td>';
    echo '<td class="center"><span class="number">' . number_format($totalProducts) . '</span> รายการ</td>';
    echo '</tr>';
    echo '</table>';

    echo '</body>';
    echo '</html>';

} catch (PDOException $e) {
    echo '<div style="color: #e74c3c; font-weight: bold; text-align: center; margin: 20px; padding: 15px; background-color: #ffebee;">';
    echo 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}
