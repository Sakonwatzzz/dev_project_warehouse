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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
                <!-- <div class="stat-description">เพิ่มขึ้น 15% จากเดือนที่แล้ว</div> -->
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">จำนวนลอต</div>
                    <div class="stat-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalCountLotDisplay">-</div>
                <!-- <div class="stat-description">มี 12 ลอตที่กำลังจะหมดอายุ</div> -->
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">สมาชิกทั้งหมด</div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalCountMemberDisplay">-</div>
                <!-- <div class="stat-description">เพิ่มขึ้น 5% จากเดือนที่แล้ว</div> -->
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">ยอดขายวันนี้</div>
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalCountSaleTodayDisplay">-</div>
                <div class="stat-description">จากการขาย <span id="totalCountSaleDisplay"></span> รายการ</div>
            </div>
        </div>

        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">ยอดขายประจำเดือน</div>
                    <div class="chart-actions">
                        <button class="chart-button">รายวัน</button>
                        <button class="chart-button">รายสัปดาห์</button>
                        <button class="chart-button">รายเดือน</button>
                    </div>
                </div>
                <div class="chart-content">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- หมวดหมู่สินค้าที่ขายดี -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">หมวดหมู่สินค้าที่ขายดี</div>
                    <div class="chart-actions">
                        <button class="chart-button">สัปดาห์นี้</button>
                        <button class="chart-button">เดือนนี้</button>
                        <button class="chart-button">ปีนี้</button>
                    </div>
                </div>
                <div class="chart-content">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>

        <div class="recent-section">
            <div class="recent-card">
                <div class="recent-header">
                    <div class="recent-title">การขายล่าสุด</div>
                    <a href="?page=navigationbar_sale" class="see-all">ดูทั้งหมด</a>
                </div>
                <div class="recent-content">
                    <table>
                        <thead>
                            <tr>
                                <th>เลขที่</th>
                                <th>ลูกค้า</th>
                                <th>วันที่</th>
                                <th>ยอดรวม</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>INV-2025-0325</td>
                                <td>สมชาย ใจดี</td>
                                <td>20/03/2025</td>
                                <td>฿3,250</td>
                                <td><span class="status completed">เสร็จสิ้น</span></td>
                            </tr>
                            <tr>
                                <td>INV-2025-0324</td>
                                <td>วิภา สุขสม</td>
                                <td>20/03/2025</td>
                                <td>฿1,450</td>
                                <td><span class="status completed">เสร็จสิ้น</span></td>
                            </tr>
                            <tr>
                                <td>INV-2025-0323</td>
                                <td>มานพ เกษมสันต์</td>
                                <td>19/03/2025</td>
                                <td>฿5,780</td>
                                <td><span class="status pending">รอชำระเงิน</span></td>
                            </tr>
                            <tr>
                                <td>INV-2025-0322</td>
                                <td>สุดา รักดี</td>
                                <td>19/03/2025</td>
                                <td>฿2,800</td>
                                <td><span class="status cancelled">ยกเลิก</span></td>
                            </tr>
                            <tr>
                                <td>INV-2025-0321</td>
                                <td>วิชัย มานะ</td>
                                <td>18/03/2025</td>
                                <td>฿4,650</td>
                                <td><span class="status completed">เสร็จสิ้น</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="recent-card">
                <div class="recent-header">
                    <div class="recent-title">สินค้าใกล้หมด</div>
                    <a href="?page=product_list" class="see-all">ดูทั้งหมด</a>
                </div>
                <div class="recent-content">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัสสินค้า</th>
                                <th>ชื่อสินค้า</th>
                                <th>คงเหลือ</th>
                                <th>คลัง</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody id="lowStockProductsTable">
                            <!-- ข้อมูลจะถูกเพิ่มโดย JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <script>
        // Current date time
        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('th-TH', options);
        }

        updateDateTime();
        setInterval(updateDateTime, 60000);

        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
                datasets: [{
                    label: 'ยอดขาย (พันบาท)',
                    data: [250, 320, 280, 360, 400, 450, 420, 480, 520, 540, 580, 620],
                    backgroundColor: 'rgba(24, 144, 255, 0.2)',
                    borderColor: 'rgba(24, 144, 255, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: ['อาหารและเครื่องดื่ม', 'อิเล็กทรอนิกส์', 'เครื่องแต่งกาย', 'เครื่องสำอาง', 'อื่นๆ'],
                datasets: [{
                    data: [35, 25, 20, 15, 5],
                    backgroundColor: [
                        'rgba(24, 144, 255, 0.7)',
                        'rgba(47, 194, 91, 0.7)',
                        'rgba(250, 140, 22, 0.7)',
                        'rgba(245, 34, 45, 0.7)',
                        'rgba(114, 46, 209, 0.7)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    </script>

    <script src="/page/dashboard/function/product.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', loadCounts);
    </script>

</body>

</html>