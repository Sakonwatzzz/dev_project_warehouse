<?php
require_once __DIR__ . '../../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

// รับและตรวจสอบ input
$product_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';
$lot_id = isset($_GET['lot_id']) ? $_GET['lot_id'] : null;
$lot_product_id = isset($_GET['lot_product_id']) ? $_GET['lot_product_id'] : null;
$warehouse_id = isset($_GET['warehouse_id']) ? $_GET['warehouse_id'] : null;



if (!$product_id || !$lot_id || !$lot_product_id) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

if ($product_id && $lot_id && $lot_product_id) {
    $query = "
        SELECT p.product_id, p.product_name, lp.lot_id, lp.lot_product_id, lp.lot_product_remain, 
               l.lot_name, w.warehouse_id, w.warehouse_name
        FROM product p
        JOIN lot_product lp ON p.product_id = lp.product_id
        JOIN lot l ON lp.lot_id = l.lot_id
        JOIN warehouse w ON l.warehouse_id = w.warehouse_id
        WHERE p.product_id = ? AND lp.lot_id = ? AND lp.lot_product_id = ? AND lp.lot_product_status = 1 AND l.lot_status_id = 2
    ";
    $params = [$product_id, $lot_id, $lot_product_id];

    if ($warehouse_id) {
        $query .= " AND w.warehouse_id = ?";
        $params[] = $warehouse_id;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $product_query = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($product_query ?? []);
?>