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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
        }

        .dashboard-container {
            padding: 20px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .dashboard-title {
            font-size: 24px;
            color: #333;
            font-weight: bold;
        }

        .date-time {
            font-size: 14px;
            color: #666;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-title {
            font-size: 16px;
            color: #666;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background-color: #e6f7ff;
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #1890ff;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-description {
            font-size: 14px;
            color: #888;
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .chart-title {
            font-size: 18px;
            color: #333;
            font-weight: bold;
        }

        .chart-actions {
            display: flex;
            gap: 10px;
        }

        .chart-button {
            background-color: #f0f0f0;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            font-size: 12px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .chart-button:hover {
            background-color: #e0e0e0;
        }

        .chart-content {
            height: 250px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .recent-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
        }

        .recent-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .recent-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .recent-title {
            font-size: 18px;
            color: #333;
            font-weight: bold;
        }

        .see-all {
            font-size: 14px;
            color: #1890ff;
            text-decoration: none;
        }

        .recent-content table {
            width: 100%;
            border-collapse: collapse;
        }

        .recent-content th {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            color: #666;
            font-weight: normal;
        }

        .recent-content td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .status.completed {
            background-color: #e6f7ff;
            color: #1890ff;
        }

        .status.pending {
            background-color: #fff7e6;
            color: #fa8c16;
        }

        .status.cancelled {
            background-color: #fff1f0;
            color: #f5222d;
        }

        .chart-content canvas {
            max-width: 100%;
        }

        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }

            .recent-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                <div class="stat-description">เพิ่มขึ้น 15% จากเดือนที่แล้ว</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">จำนวนลอต</div>
                    <div class="stat-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalCountLotDisplay">-</div>
                <div class="stat-description">มี 12 ลอตที่กำลังจะหมดอายุ</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">สมาชิกทั้งหมด</div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-value" id="totalCountMemberDisplay">-</div>
                <div class="stat-description">เพิ่มขึ้น 5% จากเดือนที่แล้ว</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">ยอดขายวันนี้</div>
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
                <div class="stat-value">฿15,230</div>
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
                        <tbody>
                            <tr>
                                <td>P-00125</td>
                                <td>เครื่องดื่มผสมวิตามิน</td>
                                <td>5</td>
                                <td>คลังหลัก</td>
                                <td><span class="status pending">ต่ำ</span></td>
                            </tr>
                            <tr>
                                <td>P-00238</td>
                                <td>กระเป๋าเดินทาง</td>
                                <td>2</td>
                                <td>คลังหลัก</td>
                                <td><span class="status cancelled">วิกฤต</span></td>
                            </tr>
                            <tr>
                                <td>P-00341</td>
                                <td>แผ่นกรองอากาศ</td>
                                <td>8</td>
                                <td>คลังสำรอง</td>
                                <td><span class="status pending">ต่ำ</span></td>
                            </tr>
                            <tr>
                                <td>P-00189</td>
                                <td>หูฟังไร้สาย</td>
                                <td>3</td>
                                <td>คลังสำรอง</td>
                                <td><span class="status cancelled">วิกฤต</span></td>
                            </tr>
                            <tr>
                                <td>P-00412</td>
                                <td>ผลิตภัณฑ์ทำความสะอาด</td>
                                <td>10</td>
                                <td>คลังหลัก</td>
                                <td><span class="status pending">ต่ำ</span></td>
                            </tr>
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