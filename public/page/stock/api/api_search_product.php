<?php
require_once __DIR__ . '../../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$warehouse_id = isset($_GET['warehouse_id']) ? trim($_GET['warehouse_id']) : '';
$products = [];

if (strlen($query) >= 2) {
    $sql = "
        SELECT p.product_id, p.product_name, lp.lot_id, lp.lot_product_id, lp.lot_product_remain, l.lot_name, w.warehouse_id, w.warehouse_name
        FROM product p
        LEFT JOIN lot_product lp ON p.product_id = lp.product_id
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN warehouse w ON l.warehouse_id = w.warehouse_id
        WHERE lp.lot_product_status = 1 AND l.lot_status_id = 2
        AND (p.product_id LIKE ? OR p.product_name LIKE ?)
    ";
    $params = ["%$query%", "%$query%"];

    if ($warehouse_id && is_numeric($warehouse_id)) {
        $sql .= " AND w.warehouse_id = ?";
        $params[] = $warehouse_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($products);
?>