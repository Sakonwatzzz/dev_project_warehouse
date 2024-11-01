<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - Warehouse Management POS v1.0</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Tahoma", "MS Sans Serif", sans-serif;
            background-color: #e9f5e9; /* สีพื้นหลัง */
            color: #000;
            height: 100vh;
            overflow: hidden;
        }

        .container {
            width: 100%;
            height: 100%;
            border: 2px solid #005700; /* สีขอบ */
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
        }

        .title-bar {
            background: linear-gradient(to right, #006400, #a0d6a0); /* แถบชื่อ */
            padding: 3px 5px;
            color: white;
            font-weight: bold;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .window-controls {
            display: flex;
            gap: 2px;
        }

        .window-button {
            width: 16px;
            height: 14px;
            border: 1px solid #fff;
            background: #d4d0c8;
            font-size: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .menu-bar {
            background: #d4d0c8; /* สีแถบเมนู */
            border-bottom: 1px solid #808080;
            padding: 2px 0;
        }

        .menu-item {
            display: inline-block;
            padding: 2px 8px;
            font-size: 11px;
            cursor: pointer;
        }

        .menu-item:hover {
            background: #006400; /* สีเมื่อชี้เมาส์ */
            color: white;
        }

        .main-content {
            display: flex;
            flex: 1;
        }

        .sidebar {
            width: 250px;
            background: #d4d0c8; /* สีของ Sidebar */
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .dashboard {
            flex: 1;
            padding: 10px;
            background: white;
        }

        .stats-cards {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .card {
            background: #d4d0c8; /* สีการ์ด */
            border: 1px solid #808080;
            padding: 10px;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .card h3 {
            font-size: 14px;
            margin-bottom: 5px;
        }

        .chart-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .chart-area {
            background: #e9f5e9; /* สีกราฟ */
            border: 1px solid #808080;
            padding: 10px;
            margin-top: 10px;
            flex: 1;
            width: 45%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .chart-area canvas {
            max-width: 100%;
        }

        .recent-activity {
            background: #e9f5e9; /* สีกิจกรรมล่าสุด */
            border: 1px solid #808080;
            padding: 10px;
            margin-top: 10px;
            flex: 1;
        }

        .status-bar {
            background: #d4d0c8; /* สี Status Bar */
            border-top: 1px solid #808080;
            padding: 2px 5px;
            font-size: 11px;
            display: flex;
            justify-content: space-between;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <div class="main-content">
            <div class="dashboard">
                <div class="stats-cards">
                    <div class="card">
                        <h3>Total Sales</h3>
                        <span>$1,200</span>
                    </div>
                    <div class="card">
                        <h3>Users</h3>
                        <span>150</span>
                    </div>
                    <div class="card">
                        <h3>Products</h3>
                        <span>300</span>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-area">
                        <h3>Sales Line Chart</h3>
                        <canvas id="salesLineChart"></canvas>
                    </div>
                    <div class="chart-area">
                        <h3>Sales Bar Chart</h3>
                        <canvas id="salesBarChart"></canvas>
                    </div>
                    <div class="chart-area">
                        <h3>Product Category Pie Chart</h3>
                        <canvas id="productPieChart"></canvas>
                    </div>
                </div>
                
               
            </div>
        </div>

        <div class="status-bar">
            <span>Status: Ready</span>
            <span>Time: <span id="current-time"></span></span>
        </div>
    </div>

    <script>
        // Sales Line Chart
        new Chart(document.getElementById("salesLineChart"), {
            type: "line",
            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul"],
                datasets: [{
                    label: "Monthly Sales ($)",
                    data: [1200, 1500, 1000, 1700, 1300, 1800, 1600],
                    backgroundColor: "rgba(0, 100, 0, 0.2)", /* สีพื้นหลังกราฟ */
                    borderColor: "#006400", /* สีขอบกราฟ */
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true
            }
        });

        // Sales Bar Chart
        new Chart(document.getElementById("salesBarChart"), {
            type: "bar",
            data: {
                labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul"],
                datasets: [{
                    label: "Monthly Sales ($)",
                    data: [1200, 1500, 1000, 1700, 1300, 1800, 1600],
                    backgroundColor: "rgba(0, 100, 0, 0.8)", /* สีพื้นหลังกราฟ */
                    borderColor: "#006400", /* สีขอบกราฟ */
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true
            }
        });

        // Product Category Pie Chart
        new Chart(document.getElementById("productPieChart"), {
            type: "pie",
            data: {
                labels: ["Electronics", "Furniture", "Clothing", "Accessories"],
                datasets: [{
                    data: [30, 20, 25, 25],
                    backgroundColor: ["#006400", "#a0d6a0", "#c3b091", "#808080"], /* สีสำหรับแต่ละประเภทสินค้า */
                    borderColor: "#808080",
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true
            }
        });

        // Update current time every second
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            document.getElementById('current-time').innerText = timeString;
        }
        setInterval(updateTime, 1000);
    </script>
</body> 
</html>
