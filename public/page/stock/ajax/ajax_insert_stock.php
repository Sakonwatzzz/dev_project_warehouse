<?php

require_once __DIR__ . '../../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'insert_check_product') {
    header('Content-Type: application/json');
    ob_clean();
    try {
        $lot_id = $_POST['lot_id'];
        $lot_product_id = $_POST['lot_product_id'];
        $product_id = $_POST['product_id'];
        $warehouse_id = $_POST['warehouse_id'];
        $system_quantity = $_POST['system_quantity'];
        $stock_check_date = $_POST['stock_check_date'];
        $stock_check_remark = $_POST['stock_check_remark'];
        $personnel_id = $_POST['personnel_id'];
        $check_status_id = 1;

        $stmt = $pdo->prepare("INSERT INTO stock_check (lot_id, lot_product_id, product_id, warehouse_id, system_quantity, stock_check_date, stock_check_remark, personnel_id, check_status_id)
        VALUE (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$lot_id, $lot_product_id, $product_id, $warehouse_id, $system_quantity, $stock_check_date, $stock_check_remark, $personnel_id, $check_status_id]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' . $e->getMessage()]);
    }
}
?>