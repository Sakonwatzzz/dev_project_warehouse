<?php
require_once __DIR__ . '../../../../config/database_server.php';

// จัดการการเพิ่มข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehousename = trim($_POST['warehousename'] ?? '');
    $warehouse_status = trim($_POST['warehouse_status'] ?? '');

    if (!empty($warehousename) && !empty($warehouse_status)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO warehouse (warehouse_name, warehouse_status) VALUES (?, ?)');
            $stmt->execute([$warehousename, $warehouse_status]);
            header("Location: ?page=warehouse_list&success");
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        echo 'Error: ชื่อหรือสถานะของประเภทสินค้าต้องไม่ว่าง';
    }
}

if (isset($_GET['warehouse_id'])) {
    $id = $_GET['warehouse_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM warehouse WHERE warehouse_id = ?");
        $stmt->execute([$id]);
        header("Location: ?page=warehouse_list");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}


// จำนวนข้อมูลทั้งหมดในตาราง
$query = "SELECT COUNT(*) as total_count FROM warehouse";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];


// การแบ่งหน้า
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// นับจำนวนรายการทั้งหมด
try {
    $total_query = "SELECT COUNT(*) FROM warehouse";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// ดึงข้อมูลตามการแบ่งหน้า
try {
    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $warehouseResults = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <style>
    .title {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        align-items: center;
        justify-content: center;
        gap: 5px;

    }

    .count {
        display: flex;
        gap: 1.5rem;
        align-items: center;

    }

    .listcount {
        font-size: 16px;
        text-align: right;
        color: #000;
        padding: 4px 10px 4px 10px;
        border: 1px solid gray;
        border-radius: 5px;
        background-color: #fff;
        display: inline-block;
        margin-top: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        width: 70px;
    }

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
        background-color: cyan;
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

    .pagination {
        margin-top: 20px;
        text-align: center;
    }

    .pagination a {
        display: inline-block;
        padding: 8px 16px;
        text-decoration: none;
        background-color: #f1f1f1;
        color: black;
        border-radius: 5px;
        margin: 0 4px;
    }

    .pagination a.active {
        background-color: #5b97b2;
        ;
        color: white;
    }

    .pagination a:hover:not(.active) {
        background-color: #ddd;
    }
    </style>
    <title>ข้อมูลคลังสินค้า</title>
</head>

<body>
    <div class="personnel-list">
        <div class="title">
            <div class="count">
                <h2>ข้อมูลคลัง</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>

            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาแบรนด์...">
            </div>
        </div>

        <div id="notification" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">เพิ่มสำเร็จ!</div>
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
            <tbody id="tableBody">
                <tr>
                    <form action="" method="POST">
                        <td></td>
                        <td>
                            <input type="text" id="warehousename" name="warehousename" placeholder="ชื่อ" required
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />
                        </td>
                        <td>
                            <input type="text" id="warehouse_status" name="warehouse_status" placeholder="สถานะ"
                                required
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />
                        </td>
                        <td>
                            <button type="submit"
                                class="px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                                เพิ่ม
                            </button>
                        </td>
                    </form>
                </tr>

                <?php while ($warehouse_list = $warehouseResults->fetch(PDO::FETCH_ASSOC)): ?>
                <tr
                    onclick="window.location.href='page/warehouse/warehouse_detail.php?tabel=warehouse&id=<?php echo htmlspecialchars($warehouse_list['warehouse_id']) ?>'">
                    <td><?php echo ($warehouse_list['warehouse_id']) ?></td>
                    <td><?php echo ($warehouse_list['warehouse_name']) ?></td>
                    <td><?php echo ($warehouse_list['warehouse_status']) ?></td>
                    <td>
                        <a href="page/warehouse/warehouse_update.php?id=<?php echo ($warehouse_list['warehouse_id']) ?>"
                            class="text-blue-500 hover:text-blue-700">แก้ไข</a>
                        <a href="?warehouse_id=<?php echo ($warehouse_list['warehouse_id']) ?>"
                            onclick="return confirm('ยืนยันการลบข้อมูล?')"
                            class="text-red-500 hover:text-red-700 ml-2">ลบ</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!--โค้ดนี้ใช้ในการแบ่งหน้าในตาราง-->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
            <?php if ($page > 1): ?>
            <a href="?page=warehouse_list&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
            <a href="#" class="active"><?php echo $i; ?></a>
            <?php else: ?>
            <a href="?page=warehouse_list&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=warehouse_list&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
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