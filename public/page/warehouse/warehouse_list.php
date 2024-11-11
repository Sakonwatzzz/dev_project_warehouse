<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Correct the query fetching logic
$query = "SELECT
    warehouse.warehouse_id, 
    warehouse.warehouse_name, 
    warehouse.warehouse_status, 

FROM warehouse";

try {
    // Execute the query and fetch the results
    $warehouseResults = $pdo->query($query);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Handle deletion based on ID
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // Delete the warehouse record
        $stmt = $pdo->prepare("DELETE FROM warehouse WHERE warehouse_id = ?");
        $stmt->execute([$id]);

        // Redirect after deletion
        header("Location: /?page=bar_warehouse_list");
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <style>
        /* CSS สำหรับการแจ้งเตือน */
        .notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            color: #000;
            border: 2px solid #A0A0A0;
            border-radius: 5px;
            font-family: "Tahoma", "Verdana", sans-serif;
            width: 400px;
            height: 120px;
            box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
        }

        .notification-header {
            background-color: #ffdfa0;
            color: #000;
            padding: 10px;
            font-weight: bold;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }

        .notification-body {
            padding: 20px;
            font-size: 16px;
            text-align: center;
        }
    </style>
    <title>หน้าผู้ส่งมอบ</title>
</head>

<body>

    <div class="personnel-list">
        <h2 class="title">ข้อมูลผู้ส่งมอบ</h2>

        <div class="search">
            <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาสมาชิก...">
        </div>

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขผู้ส่งมอบสำเร็จ!</div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ชื่อ</th>
                    <th>สถานะ</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($warehouse_list = $warehouseResults->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr
                        onclick="window.location.href='page/warehouse/warehouse_detail.php?tabel=warehouse&id=<?php echo ($warehouse_list['warehouse_id']) ?>'">
                        <td><?php echo ($warehouse_list['warehouse_id']) ?></td>
                        <td><?php echo ($warehouse_list['warehouse_name']) ?></td>
                        <td><?php echo ($warehouse_list['warehouse_address']) ?></td>

                        <td>
                            <a href="page/warehouse/warehouse_update.php?id=<?php echo ($warehouse_list['warehouse_id']) ?>">แก้ไข</a>
                            <a href="?id=<?php echo ($warehouse_list['warehouse_id']) ?>" onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            let foundAny = false;

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName("td");
                for (let j = 0; j < td.length; j++) {
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none";
                if (found) {
                    foundAny = true;
                }
            }

            const noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    const message = document.createElement("p");
                    message.id = "noDataMessage";
                    message.textContent = "ไม่พบข้อมูล";
                    message.style.color = "red";
                    message.style.fontWeight = "bold";
                    message.style.marginTop = "10px";
                    table.parentNode.insertBefore(message, table);
                }
            } else {
                if (noDataMessage) {
                    noDataMessage.remove();
                }
            }
        }

        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = d
            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.style.display = 'block';

                setTimeout(() => {
                    notification.style.display = 'none';
                }, 2000);
            }
        });
    </script>
</body>

</html>