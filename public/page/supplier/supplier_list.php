<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Correct the query fetching logic
$query = "SELECT
    supplier.supplier_id, 
    supplier.supplier_name, 
    supplier.supplier_address, 
    supplier.supplier_phone, 
    supplier.supplier_detail
FROM supplier";

try {
    // Execute the query and fetch the results
    $supplierResults = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Handle deletion based on ID
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // Delete the supplier record
        $stmt = $pdo->prepare("DELETE FROM supplier WHERE supplier_id = ?");
        $stmt->execute([$id]);

        // Redirect after deletion
        header("Location: /?page=bar_supplier_list");
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
            <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="ค้นหาสมาชิก...">
        </div>

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขผู้ส่งมอบสำเร็จ!</div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
                <tr>
                    <!-- <th>รหัส</th> -->
                    <th>ชื่อ</th>
                    <th>ที่อยู่</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>รายละเอียด</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($supplierResults as $supplier_list): ?>
                    <tr
                        onclick="window.location.href='page/supplier/supplier_detail.php?tabel=supplier&id=<?php echo ($supplier_list['supplier_id']) ?>'">
                        <td><?php echo ($supplier_list['supplier_name']) ?></td>
                        <td><?php echo ($supplier_list['supplier_address']) ?></td>
                        <td><?php echo ($supplier_list['supplier_phone']) ?></td>
                        <td><?php echo ($supplier_list['supplier_detail']) ?></td>
                        <td>
                            <a href="page/supplier/supplier_update.php?id=<?php echo ($supplier_list['supplier_id']) ?>">แก้ไข</a>
                            <a href="?id=<?php echo ($supplier_list['supplier_id']) ?>" onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
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