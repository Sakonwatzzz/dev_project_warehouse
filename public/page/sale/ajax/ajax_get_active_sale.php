<?php
session_start();
require_once __DIR__ . '../../../../../config/database_server.php';
$html = '';
$valid_sales = [];
if (!empty($_SESSION['active_sales'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['active_sales']), '?'));
    $stmt = $pdo->prepare("SELECT sale_id FROM sale WHERE sale_id IN ($placeholders) AND sale_status_id IN (1, 2)");
    $stmt->execute(array_values($_SESSION['active_sales']));
    $valid_sales = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $_SESSION['active_sales'] = $valid_sales;
}
foreach ($_SESSION['active_sales'] as $sid) {
    $active = ($sid == $_SESSION['current_sale_id']) ? 'tab-active' : 'tab-inactive';
    $html .= "<div id='tab_$sid' class='px-4 py-1 rounded-md hover:bg-gray-300 transition-colors shadow-lg cursor-pointer $active' onclick='switchBill($sid)'>บิล #$sid</div>";
    $html .= "<button id='close_tab_$sid' type='button' class='text-red-500 text-xl font-bold pl-3'>×</button>";
}
echo json_encode(['status' => 'success', 'html' => $html]);