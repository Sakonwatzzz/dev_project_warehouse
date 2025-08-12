<?php
$accessToken = getToken('access_token');
if (!$accessToken || !validateToken($accessToken)) {
    $refreshToken = getToken('refresh_token');
    if ($refreshToken && validateToken($refreshToken)) {
        $accessToken = refreshToken($refreshToken);
    } else {
        header('Location: login.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดผู้ดูแล</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style/dashboard.css">
</head>

<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">แดชบอร์ด</h1>
            <div class="date-time" id="currentDateTime"></div>
        </div>

        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">สินค้าทั้งหมด</div>
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalCountDisplay">-</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">จำนวนลอต</div>
                    <div class="stat-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalCountLotDisplay">-</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">สมาชิกทั้งหมด</div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalCountMemberDisplay">-</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">ยอดขายวันนี้</div>
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalCountSaleTodayDisplay">-</div>
                <div class="stat-description">จากการขาย <span id="totalCountSaleDisplay">-</span> รายการ</div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">ยอดขาย</div>
                    <div class="chart-actions">
                        <button class="chart-button" data-timeframe="daily">รายวัน</button>
                        <button class="chart-button" data-timeframe="weekly">รายสัปดาห์</button>
                        <button class="chart-button active" data-timeframe="monthly">รายเดือน</button>
                        <button class="chart-button" data-timeframe="yearly">รายปี</button>
                    </div>
                </div>
                <div class="chart-content">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">สินค้าขายดี</div>
                    <div class="chart-actions">
                        <button class="chart-button top-products-btn" data-timeframe="weekly">สัปดาห์นี้</button>
                        <button class="chart-button top-products-btn active" data-timeframe="monthly">เดือนนี้</button>
                        <button class="chart-button top-products-btn" data-timeframe="yearly">ปีนี้</button>
                    </div>
                </div>
                <div class="chart-content">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>

        </div>

        <div class="recent-section">
            <div class="recent-card">
                <div class="recent-header">
                    <div class="recent-title">การขายล่าสุด</div>
                    <a href="?page=sale_list" class="see-all">ดูทั้งหมด</a>
                </div>
                <div class="recent-content">
                    <table class="table-responsive">
                        <thead>
                            <tr>
                                <th>เลขที่</th>
                                <th>ลูกค้า</th>
                                <th>วันที่</th>
                                <th>ยอดรวม</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody id="LatestSalesTable"></tbody>
                    </table>
                </div>
            </div>

            <div class="recent-card">
                <div class="recent-header">
                    <div class="recent-title">สินค้าใกล้หมด</div>
                    <a href="?page=product_list" class="see-all">ดูทั้งหมด</a>
                </div>
                <div class="recent-content">
                    <table class="table-responsive">
                        <thead>
                            <tr>
                                <th>รหัสสินค้า</th>
                                <th>ชื่อสินค้า</th>
                                <th>คงเหลือ</th>
                                <th>คลัง</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody id="lowStockProductsTable"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <script src="/page/dashboard/function/product.js"></script>
    <script>
        // เพิ่มส่วน JavaScript สำหรับจัดการปุ่มสินค้าขายดี
        document.querySelectorAll('.top-products-btn').forEach(button => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.top-products-btn').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                const timeframe = button.getAttribute('data-timeframe');
                loadCounts(timeframe);
            });
        });
    </script>

</body>

</html>