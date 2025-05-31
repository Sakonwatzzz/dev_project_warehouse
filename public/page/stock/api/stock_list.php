<?php
require_once __DIR__ . '../../../../../config/database_server.php';
header('Content-Type: application/json');

try {
    $status = $_GET['status'] ?? null;
    $stock_id = $_GET['stock_id'] ?? null;
    $stock_ids = $_GET['stock_ids'] ?? null;

    $query = "SELECT
        stock_check.product_id,
        product.product_name,
        stock_check.stock_check_id,
        stock_check.lot_id,
        warehouse.warehouse_id,
        warehouse.warehouse_name,
        stock_check.stock_check_comment,
        stock_check.stock_check_remark_id,
        stock_check_remarks.stock_check_remark_name, -- แก้ไขที่นี่
        personnel.personnel_name,
        stock_check.lot_product_id,
        lot_product.lot_product_remain,
        stock.stock_id,
        stock.stock_date,
        stock_check.stock_check_system,
        stock_check.stock_check_actual,
        stock.check_status_id,
        stock_checks.check_status_name
    FROM stock_check
    LEFT JOIN product ON stock_check.product_id = product.product_id
    LEFT JOIN warehouse ON stock_check.warehouse_id = warehouse.warehouse_id
    LEFT JOIN lot ON stock_check.lot_id = lot.lot_id
    LEFT JOIN lot_product ON stock_check.lot_product_id = lot_product.lot_product_id
    LEFT JOIN stock ON stock_check.stock_id = stock.stock_id
    LEFT JOIN personnel ON stock.personnel_id = personnel.personnel_id
    LEFT JOIN stock_check_status stock_checks ON stock.check_status_id = stock_checks.check_status_id
    LEFT JOIN stock_check_remark stock_check_remarks ON stock_check.stock_check_remark_id = stock_check_remarks.stock_check_remark_id";
    $conditions = [];
    $params = [];

    if ($stock_ids) {
        $ids = explode(',', $stock_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $conditions[] = "stock.stock_id IN ($placeholders)";
        foreach ($ids as $index => $id) {
            $params[$index] = $id;
        }
    } elseif ($stock_id) {
        $conditions[] = "stock.stock_id = ?";
        $params[] = $stock_id;
    }
    if ($status) {
        $conditions[] = "stock.check_status_id = ?";
        $params[] = $status;
    }

    if ($conditions) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($data);
} catch (Exception $e) {
    error_log("stock_list.php error: " . $e->getMessage(), 3, "/var/log/php_errors.log");
    echo json_encode([]);
}
?>