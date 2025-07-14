<?php
require_once __DIR__ . '../../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 
header('Content-Type: application/json');
try {
    $product_id = isset($_GET['product_id']) ? trim($_GET['product_id']) : '';
    $warehouse_id = isset($_GET['warehouse_id']) ? trim($_GET['warehouse_id']) : '';
    if (!$product_id) {
        error_log("get_product_location.php: Missing product_id");
        echo json_encode([]);
        exit;
    }

    $query = "
        SELECT p.product_id, p.product_name, lp.lot_id, lp.lot_product_id, 
               COALESCE(lp.lot_product_remain, 0) as lot_product_remain, 
               l.lot_name, w.warehouse_id, w.warehouse_name, 
               lp.lot_product_status, l.lot_status_id
        FROM product p
        LEFT JOIN lot_product lp ON p.product_id = lp.product_id
        LEFT JOIN lot l ON lp.lot_id = l.lot_id
        LEFT JOIN warehouse w ON l.warehouse_id = w.warehouse_id
        WHERE p.product_id = ?
    ";
    $params = [$product_id];

    if ($warehouse_id && is_numeric($warehouse_id)) {
        $query .= " AND w.warehouse_id = ?";
        $params[] = $warehouse_id;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        error_log("get_product_location.php: No results for product_id: $product_id");
        echo json_encode([]);
        exit;
    }

    // กรองเฉพาะล็อตที่ใช้งานได้
    $filtered_products = array_filter($products, function ($product) {
        return $product['lot_product_status'] == 1 && $product['lot_status_id'] == 2;
    });

    if (empty($filtered_products)) {
        error_log("get_product_location.php: No active lots for product_id: $product_id");
        echo json_encode([]);
        exit;
    }

    // เลือกล็อตแรก
    $filtered_products = array_values($filtered_products);
    echo json_encode($filtered_products);
} catch (Exception $e) {
    error_log("get_product_location.php error: " . $e->getMessage());
    echo json_encode([]);
}
?>