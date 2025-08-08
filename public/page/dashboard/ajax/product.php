
<?php
//page/dashboard/ajax/product.php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json');

$pdo = Database::getInstance()->getConnection();

try {
    // นับสินค้าทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product");
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();

    // นับล็อตทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lot");
    $stmt->execute();
    $total_records_lot = (int)$stmt->fetchColumn();

    // นับสมาชิกทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM member");
    $stmt->execute();
    $total_records_member = (int)$stmt->fetchColumn();

    // นับยอดขายทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale");
    $stmt->execute();
    $total_records_sale = (int)$stmt->fetchColumn();

    // นับยอดขายวันนี้ (ยอดรวมจาก sale_total_amou ของวันนี้)
    $stmt = $pdo->prepare("SELECT SUM(sale_total_amount) FROM sale WHERE DATE(sale_date) = CURDATE()");
    $stmt->execute();
    $total_sale_today = (float)$stmt->fetchColumn();

    // สินค้าใกล้หมด
    $query = "
    SELECT 
        p.product_id,
        p.product_name,
        SUM(lp.lot_product_remain) as total_qty,
        w.warehouse_name
    FROM 
        lot_product lp
        JOIN lot ON lp.lot_id = lot.lot_id
        JOIN product p ON lp.product_id = p.product_id
        JOIN warehouse w ON lot.warehouse_id = w.warehouse_id
    WHERE 
        lot.lot_status_id = 2
    GROUP BY 
        p.product_id, p.product_name, w.warehouse_name
    HAVING 
        total_qty <= 10
    ORDER BY 
        total_qty ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ส่งข้อมูลทั้งสองใน JSON เดียว
    echo json_encode([
        'total_count' => $total_records,
        'total_count_lot' => $total_records_lot,
        'total_count_member' => $total_records_member,
        'total_count_sale' => $total_records_sale,
        'total_sale_today' => $total_sale_today,
        'low_stock_products' => $low_stock_products,
    ]);
    exit;
} catch (PDOException $e) {
    error_log("Count error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'เกิดข้อผิดพลาดในการนับข้อมูล']);
    exit;
}
