<?php
require_once __DIR__ . '../../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

header('Content-Type: application/json');

$pdo = Database::getInstance()->getConnection();

try {
    // รับพารามิเตอร์ timeframe (ค่าเริ่มต้นคือ 'monthly')
    $timeframe = isset($_GET['timeframe']) ? $_GET['timeframe'] : 'monthly';
    $valid_timeframes = ['daily', 'weekly', 'monthly', 'yearly'];
    if (!in_array($timeframe, $valid_timeframes)) {
        $timeframe = 'monthly';
    }

    // นับสินค้าทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product");
    $stmt->execute();
    $total_records = (int)$stmt->fetchColumn();

    // นับล็อตทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lot");
    $stmt->execute();
    $total_records_lot = (int)$stmt->fetchColumn();

    // นับสมาชิกทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM member");
    $stmt->execute();
    $total_records_member = (int)$stmt->fetchColumn();

    // นับยอดขายทั้งหมด
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale");
    $stmt->execute();
    $total_records_sale = (int)$stmt->fetchColumn();

    // นับยอดขายวันนี้
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(sale_total_amount), 0) FROM sale WHERE DATE(sale_date) = ? AND sale_status_id = 1");
    $stmt->execute([$today]);
    $total_sale_today = (float)$stmt->fetchColumn();
    
    // สินค้าใกล้หมด
    $query = "
        SELECT 
            p.product_id,
            p.product_name,
            SUM(lp.lot_product_remain) as total_qty,
            w.warehouse_name
        FROM 
            lot_product lp
            JOIN lot ON lp.lot_id = lot.lot_id
            JOIN product p ON lp.product_id = p.product_id
            JOIN warehouse w ON lot.warehouse_id = w.warehouse_id
        WHERE 
            lot.lot_status_id = 2
        GROUP BY 
            p.product_id, p.product_name, w.warehouse_name
        HAVING 
            total_qty <= 10
        ORDER BY 
            total_qty ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $low_stock_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // การขายล่าสุด
    $query = "
        SELECT 
            s.sale_id,
            s.sale_date,
            m.member_name,
            s.sale_total_amount,
            s.sale_status_id
        FROM 
            sale s
            JOIN member m ON s.member_id = m.member_id
        ORDER BY 
            s.sale_date DESC
        LIMIT 5";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $latest_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ข้อมูลยอดขายตาม timeframe
    $sales_data = [];
    if ($timeframe === 'daily') {
        // 7 วันล่าสุด
        $query = "
            SELECT 
                DATE(s.sale_date) AS date,
                SUM(s.sale_total_amount) AS total_sales
            FROM 
                sale s
            WHERE 
                s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND s.sale_status_id = 1
            GROUP BY 
                DATE(s.sale_date)
            ORDER BY 
                DATE(s.sale_date)";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($timeframe === 'weekly') {
        // 4 สัปดาห์ล่าสุด
        $query = "
        SELECT 
            YEARWEEK(s.sale_date, 1) AS yearweek,
            DATE_FORMAT(MIN(s.sale_date), '%Y-%m-%d') AS week_start,
            DATE_FORMAT(MAX(s.sale_date), '%Y-%m-%d') AS week_end,
            SUM(s.sale_total_amount) AS total_sales
        FROM 
            sale s
        WHERE 
            s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)
            AND s.sale_status_id = 1
        GROUP BY 
            YEARWEEK(s.sale_date, 1)
        ORDER BY 
            YEARWEEK(s.sale_date, 1)";

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($timeframe === 'monthly') {
        // ปีปัจจุบัน รายเดือน
        $query = "
            SELECT 
                MONTH(s.sale_date) AS month,
                SUM(s.sale_total_amount) AS total_sales
            FROM 
                sale s
            WHERE 
                YEAR(s.sale_date) = YEAR(CURDATE())
                AND s.sale_status_id = 1
            GROUP BY 
                MONTH(s.sale_date)
            ORDER BY 
                MONTH(s.sale_date)";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($timeframe === 'yearly') {
        // 5 ปีล่าสุด
        $query = "
            SELECT 
                YEAR(s.sale_date) AS year,
                SUM(s.sale_total_amount) AS total_sales
            FROM 
                sale s
            WHERE 
                s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
                AND s.sale_status_id = 1
            GROUP BY 
                YEAR(s.sale_date)
            ORDER BY 
                YEAR(s.sale_date)";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Top 5 สินค้าขายดี
    $whereDateTopProducts = '';

    if ($timeframe === 'daily') {
        $whereDateTopProducts = "AND DATE(s.sale_date) = CURDATE()";
    } elseif ($timeframe === 'weekly') {
        $whereDateTopProducts = "AND YEARWEEK(s.sale_date, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($timeframe === 'monthly') {
        $whereDateTopProducts = "AND YEAR(s.sale_date) = YEAR(CURDATE()) AND MONTH(s.sale_date) = MONTH(CURDATE())";
    } elseif ($timeframe === 'yearly') {
        $whereDateTopProducts = "AND YEAR(s.sale_date) = YEAR(CURDATE())";
    }

    $query = "
    SELECT 
        p.product_id,
        p.product_name,
        SUM(sp.sale_product_qty) AS total_qty,
        SUM(sp.sale_product_qty * sp.sale_product_price) AS total_amount
    FROM 
        sale_product sp
        JOIN sale s ON sp.sale_id = s.sale_id
        JOIN product p ON sp.product_id = p.product_id
    WHERE 
        s.sale_status_id = 1
        $whereDateTopProducts
    GROUP BY 
        p.product_id, p.product_name
    ORDER BY 
        total_qty DESC
    LIMIT 5";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // ส่งข้อมูล JSON
    echo json_encode([
        'total_count' => $total_records,
        'total_count_lot' => $total_records_lot,
        'total_count_member' => $total_records_member,
        'total_count_sale' => $total_records_sale,
        'total_sale_today' => $total_sale_today,
        'low_stock_products' => $low_stock_products,
        'latest_sales' => $latest_sales,
        'sales_data' => $sales_data,
        'top_products' => $top_products,
        'timeframe' => $timeframe
    ]);
    exit;
} catch (PDOException $e) {
    error_log("ข้อผิดพลาด: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'เกิดข้อผิดพลาดในการนับข้อมูล']);
    exit;
}
