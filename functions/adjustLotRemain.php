<?php

require_once __DIR__ . '../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

function adjustLotRemain(PDO $pdo, string $productId, int $qty, bool $addMode = false)
{
    $remainingToProcess = $qty;
    $lot_ids = [];
    try {
        if ($addMode) {
            // คืนสต็อก: เลือกล็อตที่มีอยู่หรือสร้างใหม่
            $query = "
                SELECT lot_product_id, lot_product_remain 
                FROM lot_product 
                WHERE product_id = ?
                ORDER BY lot_product_date ASC, lot_product_id ASC LIMIT 1
            ";
            $stmtLot = $pdo->prepare($query);
            $stmtLot->execute([$productId]);
            $lot = $stmtLot->fetch(PDO::FETCH_ASSOC);

            if (!$lot) {
                // ถ้าไม่มีล็อต, สร้างล็อตใหม่
                $insertLot = $pdo->prepare("
                    INSERT INTO lot_product (product_id, lot_product_remain, lot_product_date)
                    VALUES (?, ?, NOW())
                ");
                $insertLot->execute([$productId, $remainingToProcess]);
                $lot_ids[] = ['lot_product_id' => $pdo->lastInsertId(), 'qty' => $remainingToProcess];
                $remainingToProcess = 0;
            } else {
                // เพิ่มสต็อกในล็อตที่มีอยู่
                $newRemain = $lot['lot_product_remain'] + $remainingToProcess;
                $updateLot = $pdo->prepare("
                    UPDATE lot_product 
                    SET lot_product_remain = ? 
                    WHERE lot_product_id = ?
                ");
                $updateLot->execute([$newRemain, $lot['lot_product_id']]);
                $lot_ids[] = ['lot_product_id' => $lot['lot_product_id'], 'qty' => $remainingToProcess];
                $remainingToProcess = 0;
            }
        } else
            while ($remainingToProcess > 0) {
                $query = "
                SELECT lot_product_id, lot_product_remain 
                FROM lot_product 
                WHERE product_id = ? AND lot_product_remain > 0
                ORDER BY lot_product_date ASC, lot_product_id ASC LIMIT 1
            ";
                $stmtLot = $pdo->prepare($query);
                $stmtLot->execute([$productId]);
                $lot = $stmtLot->fetch(PDO::FETCH_ASSOC);

                if (!$lot) {
                    throw new Exception("สต็อกล็อตไม่เพียงพอสำหรับสินค้า $productId");
                }

                $current = (int) $lot['lot_product_remain'];
                if ($addMode) {
                    $newRemain = $current + $remainingToProcess;
                    $remainingToProcess = 0;
                } else {
                    if ($current >= $remainingToProcess) {
                        $newRemain = $current - $remainingToProcess;
                        $lot_ids[] = ['lot_product_id' => $lot['lot_product_id'], 'qty' => $remainingToProcess];
                        $remainingToProcess = 0;
                    } else {
                        $newRemain = 0;
                        $lot_ids[] = ['lot_product_id' => $lot['lot_product_id'], 'qty' => $current];
                        $remainingToProcess -= $current;
                    }
                }

                $updateLot = $pdo->prepare("
                UPDATE lot_product 
                SET lot_product_remain = ? 
                WHERE lot_product_id = ?
            ");
                $updateLot->execute([$newRemain, $lot['lot_product_id']]);
            }
    } catch (Exception $e) {
        error_log("Error in adjustLotRemain for product $productId: " . $e->getMessage());
        throw $e;
    }
    return $lot_ids;
}
?>