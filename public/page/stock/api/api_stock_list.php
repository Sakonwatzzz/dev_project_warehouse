<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '../../../../../config/database_server.php';

if (!$pdo) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$product_id = $_GET['product_id'] ?? '';
$warehouse_id = $_GET['warehouse_id'] ?? '';
$status_id = $_GET['status'] ?? '';
$date_range = [];
if (!empty($_GET['date_range']) && strpos($_GET['date_range'], ' to ') !== false) {
    $date_range = array_map('trim', explode(' to ', $_GET['date_range']));
}
$tab = $_GET['tab'] ?? 'active';

$sql = "
    SELECT sc.stock_check_id, sc.stock_check_date, w.warehouse_name, 
       COUNT(DISTINCT sc.lot_product_id) as item_count, p.personnel_name, 
       s.check_status_name, sc.stock_check_remark, sc.product_id, 
       sc.stock_check_system, sc.stock_check_actual
FROM stock_check sc
JOIN warehouse w ON sc.warehouse_id = w.warehouse_id
JOIN personnel p ON sc.personnel_id = p.personnel_id
JOIN stock_check_status s ON sc.check_status_id = s.check_status_id
JOIN product pr ON sc.product_id = pr.product_id
WHERE 1=1
";
$params = [];

if ($product_id) {
    $sql .= " AND sc.product_id = ?";
    $params[] = $product_id;
}
if ($warehouse_id && is_numeric($warehouse_id)) {
    $sql .= " AND sc.warehouse_id = ?";
    $params[] = $warehouse_id;
}
if ($status_id && is_numeric($status_id)) {
    $sql .= " AND sc.check_status_id = ?";
    $params[] = $status_id;
}
if (count($date_range) === 2 && DateTime::createFromFormat('d/m/Y', $date_range[0]) && DateTime::createFromFormat('d/m/Y', $date_range[1])) {
    $sql .= " AND DATE(sc.stock_check_date) BETWEEN ? AND ?";
    $params[] = DateTime::createFromFormat('d/m/Y', $date_range[0])->format('Y-m-d');
    $params[] = DateTime::createFromFormat('d/m/Y', $date_range[1])->format('Y-m-d');
}

if ($tab === 'active') {
    $sql .= " AND sc.check_status_id = 1";
} else {
    $sql .= " AND sc.check_status_id != 1";
}

$sql .= " GROUP BY sc.stock_check_id";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data ?: []);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>