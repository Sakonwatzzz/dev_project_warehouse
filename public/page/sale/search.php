<?php
ob_start();
session_start();
require_once __DIR__ . '/../../../config/database_server.php';
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../../logs/php_errors.log');

// Handle product search for suggestions first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    error_log('Starting search action');
    try {
        $query = trim($_POST['query'] ?? '');
        error_log('Search Request: Query=' . $query);
        if (empty($query)) {
            echo json_encode(['success' => false, 'error' => 'กรุณาป้อนคำค้นหา']);
            exit();
        }
        error_log('Preparing SQL query');
        $stmt = $pdo->prepare("SELECT product_id, product_name 
                              FROM product 
                              WHERE product_id LIKE ? OR product_name LIKE ? 
                              LIMIT 10");
        error_log('Executing query');
        $stmt->execute(["%$query%", "%$query%"]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log('Search Products: ' . print_r($products, true));
        echo json_encode(['success' => true, 'data' => $products]);
    } catch (Exception $e) {
        error_log('Search Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
?>