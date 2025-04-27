<?php
require_once __DIR__ . '../../../../config/database_server.php';
$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
$warehouse_id = isset($_GET['warehouse_id']) ? $_GET['warehouse_id'] : null;
$product = null;
if ($barcode) {
    $query = "
        SELECT p.product_name, p.product_price4, SUM(lp.lot_product_remain) as total_stock
        FROM product p
        LEFT JOIN lot_product lp ON p.product_id = lp.product_id
        WHERE p.product_id = ?
    ";
    $params = [$barcode];
    if ($warehouse_id) {
        $query .= " AND lp.warehouse_id = ?";
        $params[] = $warehouse_id;
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
}
header('Content-Type: application/json');
echo json_encode($product);
?>