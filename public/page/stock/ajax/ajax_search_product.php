<?php
require_once __DIR__ . '../../../../../config/database_server.php';
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$products = [];
if (strlen($query) >= 2) {
    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, SUM(lp.lot_product_remain) as total_stock
        FROM product p
        LEFT JOIN lot_product lp ON p.product_id = lp.product_id
        WHERE p.product_id LIKE ? OR p.product_name LIKE ?
        GROUP BY p.product_id, p.product_name
        LIMIT 10
    ");
    $stmt->execute(["%$query%", "%$query%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
header('Content-Type: application/json');
echo json_encode($products);
?>