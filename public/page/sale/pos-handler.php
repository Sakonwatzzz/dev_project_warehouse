<?php
require_once '../../../config/database_server.php';
header('Content-Type: application/json');

if ($_GET['action'] === 'scan') {
    $code = $_GET['code'];
    $stmt = $pdo->prepare("SELECT product_id, name, price FROM product WHERE product_id = ?");
    $stmt->execute([$code]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสสินค้านี้']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $products = $data['products']; // cart
    $pdo->beginTransaction();
    try {
        // Insert sale
        $stmt = $pdo->prepare("INSERT INTO sale (sale_date, sale_status_id, member_id, personnel_id, store_id)
                               VALUES (NOW(), ?, ?, ?, ?)");
        $stmt->execute([
            $data['sale_status_id'],
            $data['member_id'],
            $data['personnel_id'],
            $data['store_id']
        ]);
        $sale_id = $pdo->lastInsertId();

        // Insert each product
        $stmt = $pdo->prepare("INSERT INTO sale_product (
            sale_id, product_id, sale_product_qty, sale_product_price, sale_product_capital,
            sale_product_booking, sale_product_status, warehouse_id, sale_deposit, sale_reference
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($products as $p) {
            $stmt->execute([
                $sale_id,
                $p['product_id'],
                $p['sale_product_qty'],
                $p['sale_product_price'],
                $p['sale_product_capital'],
                $p['sale_product_booking'],
                $p['sale_product_status'],
                $p['warehouse_id'],
                $p['sale_deposit'],
                $p['sale_reference']
            ]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'sale_id' => $sale_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
