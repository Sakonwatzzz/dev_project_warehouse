<?php
require_once __DIR__ . '../../../../config/database_server.php';


$query = "SELECT
    supplier.supplier_id, 
    supplier.supplier_name, 
    supplier.supplier_address, 
    supplier.supplier_phone, 
    supplier.supplier_detail
FROM supplier";
try {
    $supplierResults = $pdo->query($query);
} catch (Exception $e) {
    echo "error" . $e->getMessage();
    exit();
}

// จำนวนข้อมูลทั้งหมดในตาราง
$query = "SELECT COUNT(*) as total_count FROM supplier";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM supplier WHERE supplier_id = ?");
    $stmt->execute([$id]);
    header("Location: index.php?page=supplier_list&delete_success");
    exit;
}

// การแบ่งหน้า
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;
$total_pages = ceil($count / $records_per_page);

// นับจำนวนรายการทั้งหมด
try {
    $total_query = "SELECT COUNT(*) FROM supplier";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

try {
    $query = "SELECT supplier_id, supplier_name, supplier_address, supplier_phone, supplier_detail 
              FROM supplier 
              LIMIT ?, ?";

    $stmt = $pdo->prepare($query);
    $stmt->bindValue(1, $offset, PDO::PARAM_INT);
    $stmt->bindValue(2, $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $supplierResults = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
    </style>
    <title>หน้าผู้ส่งมอบ</title>
</head>

<body>

    <div class="personnel-list">

        <div class="title">
            <div class="count">
                <h2>ข้อมูลผู้ส่งมอบ</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาสมาชิก...">
            </div>
        </div>

        <div id="notification-edit" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขผู้ส่งมอบสำเร็จ!</div>
        </div>
        <div id="notification-delete" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">ลบสำเร็จ!</div>
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
                <?php while ($supplier_list = $supplierResults->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><?php echo ($supplier_list['supplier_name']) ?></td>
                        <td><?php echo ($supplier_list['supplier_address']) ?></td>
                        <td><?php echo ($supplier_list['supplier_phone']) ?></td>
                        <td><?php echo ($supplier_list['supplier_detail']) ?></td>
                        <td>
                            <a href="?page=supplier_update&id=<?php echo ($supplier_list['supplier_id']) ?>">แก้ไข</a>
                            <a href="?page=supplier_list&id=<?php echo ($supplier_list['supplier_id']) ?>" onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!--โค้ดนี้ใช้ในการแบ่งหน้าในตาราง-->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=supplier_list&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=supplier_list&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=supplier_list&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
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
            const searchInput = document.getElementById("searchInput");
            searchInput.focus();
            if (urlParams.has('edit_success')) {
                const notificationEdit = document.getElementById('notification-edit');
                notificationEdit.style.display = 'block';
                setTimeout(() => {
                    notificationEdit.style.display = 'none';
                }, 2000);
            } else if (urlParams.has('delete_success')) {
                const notificationDelete = document.getElementById('notification-delete');
                notificationDelete.style.display = 'block';
                setTimeout(() => {
                    notificationDelete.style.display = 'none';
                }, 2000);
            }
        });
    </script>
</body>

</html>