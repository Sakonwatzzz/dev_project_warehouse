<?php
session_start();
require_once __DIR__ . '../../../../../config/database_server.php';
header('Content-Type: application/json');

try {
    $sale_id = $_GET['sale_id'] ?? null;
    if (!$sale_id) {
        echo json_encode(['status' => 'error', 'message' => 'ไม่มี sale_id']);
        exit;
    }

    error_log("ajax_get_sale_product: sale_id=$sale_id");

    $status_query = "SELECT ss.sale_status_name
        FROM sale s
        JOIN sale_status ss ON s.sale_status_id = ss.sale_status_id
        WHERE s.sale_id = ?";
    $status_stmt = $pdo->prepare($status_query);
    $status_stmt->execute([$sale_id]);
    $sale_status = $status_stmt->fetch(PDO::FETCH_ASSOC);
    $sale_status_name = $sale_status ? $sale_status['sale_status_name'] : 'ไม่ทราบสถานะ';

    $product_query = "SELECT
            sp.sale_product_id, sp.sale_id, sp.product_id,
            COALESCE(pr.product_name, 'ไม่มีสินค้าในระบบ') as product_name,
            sp.sale_product_qty, sp.sale_product_price, sp.sale_product_capital
        FROM sale_product sp
        LEFT JOIN product pr ON pr.product_id = sp.product_id
        WHERE sp.sale_id = ?";
    $stmt = $pdo->prepare($product_query);
    $stmt->execute([$sale_id]);
    $sale_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("ajax_get_sale_product: found " . count($sale_products) . " products");

    $sale_net_amount = 0;
    $total_items = 0;
    $total_qty = 0;
    foreach ($sale_products as $product) {
        $sale_net_amount += $product['sale_product_qty'] * $product['sale_product_price'];
        $total_items++;
        $total_qty += $product['sale_product_qty'];
    }

    $vat_rate = 7.00;
    $sale_vat_amount = $sale_net_amount * ($vat_rate / 100);
    $sale_total_amount = $sale_net_amount + $sale_vat_amount;

    $html = '';
    if (empty($sale_products)) {
        $html = '<tr><td colspan="6" class="text-center py-8 text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    <p class="mt-2">ไม่พบสินค้าในการขายนี้</p>
                    <p class="text-sm">กรุณาเพิ่มสินค้า</p>
                </td></tr>';
    } else {
        foreach ($sale_products as $product) {
            $html .= '<tr class="border-t border-gray-200 hover:bg-gray-50">';
            $html .= '<td class="py-3 px-4 text-sm">' . htmlspecialchars($product['product_id']) . '</td>';
            $html .= '<td class="py-3 px-4 text-sm">' . htmlspecialchars($product['product_name']) . '</td>';
            $html .= '<td class="py-3 px-4 text-center text-sm">' . htmlspecialchars($product['sale_product_qty']) . '</td>';
            $html .= '<td class="py-3 px-4 text-right text-sm">' . number_format($product['sale_product_price'], 2) . '</td>';
            $html .= '<td class="py-3 px-4 text-right font-medium text-sm">' . number_format($product['sale_product_qty'] * $product['sale_product_price'], 2) . '</td>';
            $html .= '<td class="py-3 px-4 text-center">
                        <div class="flex justify-center space-x-2">
                            <button class="edit-product p-1 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors"
                                data-id="' . htmlspecialchars($product['sale_product_id']) . '"
                                data-barcode="' . htmlspecialchars($product['product_id']) . '"
                                data-name="' . htmlspecialchars($product['product_name']) . '"
                                data-qty="' . htmlspecialchars($product['sale_product_qty']) . '"
                                data-price="' . htmlspecialchars($product['sale_product_price']) . '"
                                data-capital="' . htmlspecialchars($product['sale_product_capital']) . '">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                            <button type="button" class="delete-product p-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors"
                                data-barcode="' . htmlspecialchars($product['product_id']) . '"
                                data-qty="1"
                                data-sale-id="' . htmlspecialchars($sale_id) . '">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </td>';
            $html .= '</tr>';
        }
    }

    echo json_encode([
        'status' => 'success',
        'html' => $html,
        'sale_net_amount' => $sale_net_amount, // ส่งเป็น float
        'sale_vat_amount' => $sale_vat_amount, // ส่งเป็น float
        'sale_total_amount' => $sale_total_amount, // ส่งเป็น float
        'total_items' => $total_items,
        'total_qty' => $total_qty,
        'sale_status_name' => $sale_status_name
    ]);
} catch (Exception $e) {
    error_log("Error in ajax_get_sale_product: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit();
