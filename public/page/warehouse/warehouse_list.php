<?php
require_once __DIR__ . '../../../../config/database_server.php';

// ตัวแปร $id จะรับค่าจากพารามิเตอร์ใน URL ที่มีชื่อว่า id ถ้าพารามิเตอร์นี้มีอยู่ใน URL
// การใช้ isset() ทำให้ตรวจสอบได้ว่า $_GET['id'] มีการส่งค่าเข้ามาหรือไม่ /ถ้ามีค่า $_GET['id'], ก็จะใช้ค่าในนั้น; ถ้าไม่มี, ก็จะกำหนดให้เป็น null
// $id = isset($_GET['id']) ? $_GET['id'] : null;
// $is_update = !is_null($id);

// //ถ้าตรวจพบว่า $is_update เป็น true (หมายความว่าเรากำลังอัปเดตข้อมูล), 
// //โค้ดจะทำการดึงข้อมูลจากฐานข้อมูลของ warehouse โดยใช้ id ที่ได้รับจากพารามิเตอร์ URL เพื่อค้นหาข้อมูลที่ต้องการอัปเดต.
// if ($is_update) {
//     // ดึงข้อมูลของ ID ที่ต้องการแก้ไข
//     $query = "SELECT * FROM warehouse WHERE warehouse_id = ?";
//     $stmt = $pdo->prepare($query);
//     $stmt->execute([$id]);
//     $warehouse = $stmt->fetch();
// } else {
//     $warehouse = ['warehouse_name' => '', 'warehouse_status' => '']; // ค่าเปล่าสำหรับการเพิ่มข้อมูลใหม่
// }

// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $warehouse_id = $_POST['warehouse_id'];
//     $warehouse_name = $_POST['warehouse_name'];
//     $warehouse_status = $_POST['warehouse_status'];

//     /* ตัวแปร $is_update จะถูกกำหนดไว้ในโค้ดก่อนหน้านี้ว่าเป็น true ถ้ามีการส่งค่า id (หมายความว่าเราต้องการอัปเดตข้อมูลที่มีอยู่ในฐานข้อมูล)
//      และจะเป็น false ถ้าไม่มีการส่ง id (หมายความว่าเราต้องการเพิ่มข้อมูลใหม่) */

//     /* ถ้า $is_update เป็น true (หมายถึงการอัปเดตข้อมูล), จะทำการเตรียมคำสั่ง SQL UPDATE 
//         เพื่ออัปเดตข้อมูลในตาราง warehouse ตาม warehouse_id ที่กำหนด */
//     if ($is_update) {
//         $stmt = $pdo->prepare("
//     UPDATE warehouse
//     SET warehouse_name = ?, warehouse_status = ?
//     WHERE warehouse_id = ?
//     ");
//         $stmt->execute([$warehouse_name, $warehouse_status, $warehouse_id]);
//     } else {

//         $stmt = $pdo->prepare('INSERT INTO warehouse (warehouse_name, warehouse_status) VALUES (?,?)');
//         $stmt->execute([$warehouse_name, $warehouse_status]);
//     }
//     // Redirect กลับไปที่หน้า warehouse_list
//     header('Location: ?page=warehouse_list');
//     exit;
// }
/* ------------------------------------------------------------ 
การใช้ if ($is_update) สองครั้งมีจุดประสงค์ที่แตกต่างกัน
ครั้งแรกเพื่อเช็คว่าเรากำลังดึงข้อมูลเพื่ออัปเดต (หรือแสดงฟอร์มเปล่าสำหรับเพิ่มข้อมูลใหม่)
ครั้งที่สองเพื่อเช็คว่าเรากำลังดำเนินการอัปเดตหรือเพิ่มข้อมูลหลังจากที่ฟอร์มถูกส่ง
ในทั้งสองกรณี, $is_update ถูกใช้เพื่อทำให้โค้ดสามารถแยกการทำงานระหว่างการ อัปเดต และ เพิ่ม ข้อมูลได้อย่างถูกต้อง. 
*/


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warehouse_name = $_POST['warehouse_name'];
    $warehouse_status = $_POST['warehouse_status'];

    if (isset($warehouse_name) && isset($warehouse_status)) {
        try {
            // ถ้าไม่มีก็ insert ใหม่
            $stmt = $pdo->prepare("INSERT INTO warehouse (warehouse_name, warehouse_status) VALUES (?, ?)");
            $stmt->execute([$warehouse_name, $warehouse_status]);

            // Redirect กลับไปยังหน้า warehouse_list
            header('Location: ?page=warehouse_list');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = $_POST['warehouse_id'];
    $newWarehouseName = $_POST['warehouse_name'];
    $newWarehouseStatus = $_POST['warehouse_status'];

    try {
        // Update the warehouse details in the database
        $stmt = $pdo->prepare("UPDATE warehouse SET warehouse_name = ?, warehouse_status = ? WHERE warehouse_id = ?");
        $stmt->execute([$newWarehouseName, $newWarehouseStatus, $id]);

        // Redirect back to the warehouse list after update
        header('Location: ?page=warehouse_list');
        exit;
    } catch (PDOException $e) {
        echo 'Database error: ' . $e->getMessage();
    }
}



// ตรวจสอบว่ากำลังลบหรือแก้ไข
$is_del = isset($_GET['is_del']) ? (bool) $_GET['is_del'] : false;

// ถ้าเป็นการลบ
if ($is_del && isset($_GET['id'])) {
    $id = $_GET['id'];
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
$records_per_page = 3;
$page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
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

/// ดึงข้อมูลตามการแบ่งหน้า
$query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse LIMIT :offset, :records_per_page";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
$stmt->execute();
$warehouseResults = $stmt;

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
            <tbody class="tableBody">

                <tr>
                    <!-- ฟอร์มเพิ่มข้อมูลจะไม่แสดงผลเมื่ออยู่ในโหมดการแก้ไข ($is_update == true). -->
                    <form action="" method="POST">
                        <td>
                            <input type="hidden" id="warehouse_id" name="warehouse_id">
                        </td>
                        <td>
                            <input type="text" id="warehouse_name" name="warehouse_name"
                                placeholder="ชื่อ"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />

                        </td>
                        <td>
                            <input type=" text" id="warehouse_status" name="warehouse_status" placeholder="สถานะ"
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
            <tbody class="tableBody">
                <?php while ($warehouse = $warehouseResults->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr data-id="<?php echo $warehouse['warehouse_id']; ?>">
                        <td><?php echo $warehouse['warehouse_id']; ?></td>
                        <td class="warehouse-name">
                            <?php echo $warehouse['warehouse_name']; ?>
                        </td>
                        <td class="warehouse-status">
                            <?php echo $warehouse['warehouse_status']; ?>
                        </td>
                        <td>
                            <button onclick="enableEdit(this)" class="text-blue-500 hover:text-blue-700">แก้ไข</button>
                            <a href="?page=warehouse_list&id=<?php echo $warehouse['warehouse_id']; ?>&is_del=1"
                                onclick="return confirm('ยืนยันการลบข้อมูล?')" class="text-red-500 hover:text-red-700">ลบ</a>
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



        // ฟังก์ชันแสดงการแจ้งเตือน
        function showNotification(message) {
            const notification = document.getElementById('notification');
            const notificationBody = notification.querySelector('.notification-body');
            notificationBody.textContent = message;
            notification.style.display = 'block';
            setTimeout(() => {
                notification.style.display = 'none';
            }, 2000);
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

        function enableEdit(button) {
            const row = button.closest('tr');
            const warehouseNameCell = row.querySelector('.warehouse-name');
            const warehouseStatusCell = row.querySelector('.warehouse-status');
            const warehouseId = row.getAttribute('data-id');

            const originalWarehouseName = warehouseNameCell.innerText;
            const originalWarehouseStatus = warehouseStatusCell.innerText;

            if (button.innerText === 'แก้ไข') {
                // เปลี่ยนเป็นโหมดแก้ไข
                warehouseNameCell.innerHTML = `<input type="text" class="edit-input" style="border: 1px solid #333;" value="${originalWarehouseName}" />`;
                warehouseStatusCell.innerHTML = `<input type="text" class="edit-input" style="border: 1px solid #333;" value="${originalWarehouseStatus}" />`;
                button.innerText = 'บันทึก';
            } else {
                // บันทึกการเปลี่ยนแปลง
                const newWarehouseName = warehouseNameCell.querySelector('.edit-input').value;
                const newWarehouseStatus = warehouseStatusCell.querySelector('.edit-input').value;

                if (newWarehouseName && newWarehouseStatus !== originalWarehouseName) {
                    // ส่งการอัปเดตไปที่เซิร์ฟเวอร์
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `id=${warehouseId}&update_warehouse_name=${encodeURIComponent(newWarehouseName)}&update_warehouse_status=${encodeURIComponent(newWarehouseStatus)}`
                    }).then(() => {
                        // อัปเดตเซลล์ให้แสดงผลการเปลี่ยนแปลง
                        warehouseNameCell.innerHTML = newWarehouseName;
                        warehouseStatusCell.innerHTML = newWarehouseStatus;
                        button.innerText = 'แก้ไข';
                    }).catch(error => {
                        console.error('Error:', error);
                        alert('ไม่สามารถบันทึกการเปลี่ยนแปลงได้');
                    });
                } else {
                    // รีเซ็ตค่าเป็นค่าดั้งเดิมหากไม่มีการเปลี่ยนแปลง
                    warehouseNameCell.innerHTML = originalWarehouseName;
                    warehouseStatusCell.innerHTML = originalWarehouseStatus;
                    button.innerText = 'แก้ไข';
                }
            }
        }
    </script>
</body>

</html>