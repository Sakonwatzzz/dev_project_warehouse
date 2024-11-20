<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Retrieve the product_type_id from URL (if present)
$id = isset($_GET['id']) ? $_GET['id'] : null;
$is_update = !is_null($id);

// Retrieve the product type details if updating
if ($is_update) {
    $query = "SELECT * FROM product_type WHERE product_type_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id]);
    $product_type = $stmt->fetch();
} else {
    $product_type = ['product_type_name' => '']; // Empty for creating new
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_type_id = $_POST['product_type_id'];
    $product_type_name = $_POST['product_type_name'];

    if ($is_update) {
        // Update the product type in database
        $stmt = $pdo->prepare("UPDATE product_type SET product_type_name = ? WHERE product_type_id = ?");
        $stmt->execute([$product_type_name, $product_type_id]);
    } else {
        // Insert a new product type
        $stmt = $pdo->prepare("INSERT INTO product_type (product_type_name) VALUES (?)");
        $stmt->execute([$product_type_name]);
    }

    // Redirect to the product type page
    header('Location: ?page=product_type');
    exit;
}

// Handle deletion
$is_del = isset($_GET['is_del']) ? (bool) $_GET['is_del'] : false;
if ($is_del && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM product_type WHERE product_type_id = ?");
        $stmt->execute([$id]);
        header("Location: ?page=product_type");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Pagination logic
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $total_query = "SELECT COUNT(*) FROM product_type";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$query = "SELECT product_type_id, product_type_name FROM product_type LIMIT :offset, :records_per_page";
$stmt = $pdo->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
$stmt->execute();
$producttypeResults = $stmt;
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
    <title>ข้อมูลประเภทสินค้า</title>
</head>

<body>
    <div class="personnel-list">
        <div class="title">
            <div class="count">
                <h2>ข้อมูลประเภทสินค้า</h2>
                <div class="listcount">
                    <?php echo $total_records; ?>
                </div>
            </div>

            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหา...">
            </div>
        </div>

        <table class="data-table" id="dataTable">
            <thead>
                <tr>
                    <th>รหัส</th>
                    <th>ชื่อ</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody class="tableBody">
                <tr>
                    <!-- ฟอร์มเพิ่มข้อมูลจะไม่แสดงผลเมื่ออยู่ในโหมดการแก้ไข ($is_update == true). -->
                    <?php if (!$is_update): ?>

                        <form action="" method="POST">
                            <td>
                                <input type="hidden" id="product_type_id" name="product_type_id">
                            </td>
                            <td>
                                <input type="text" id="product_type_name" name="product_type_name"
                                    placeholder="ชื่อ"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />
                            </td>
                            <td>
                                <button type="submit"
                                    class="px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                                    เพิ่ม
                                </button>
                            </td>
                        </form>
                    <?php endif; ?>
                </tr>
                <form action="" method="POST">
                    <?php while ($product_type = $producttypeResults->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?php echo $product_type['product_type_id']; ?></td>
                            <td>
                                <?php if ($is_update && $product_type['product_type_id'] == $id): ?>
                                    <input type="hidden" name="product_type_id" value="<?php echo $product_type['product_type_id']; ?>">
                                    <input type="text" name="product_type_name" value="<?php echo $product_type['product_type_name']; ?>" class="w-full p-3 border border-gray-300 rounded-lg">
                                <?php else: ?>
                                    <?php echo $product_type['product_type_name']; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$is_update): ?>
                                    <!-- ปุ่มแก้ไขและลบเมื่อไม่ได้อยู่ในโหมดการแก้ไข -->
                                    <a href="?page=product_type&tabel=product_type&id=<?php echo $product_type['product_type_id']; ?>&is_update=1">แก้ไข</a>
                                    <a href="?page=product_type&id=<?php echo $product_type['product_type_id']; ?>&is_del=1"
                                        onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                                <?php elseif ($is_update && $product_type['product_type_id'] == $id): ?>
                                    <!-- เมื่ออยู่ในโหมดการแก้ไข แสดงปุ่มบันทึก -->
                                    <button class="submit-button" type="submit" style="border-radius: 6px; width: 100px; height: 30px;">
                                        บันทึก
                                    </button>
                                <?php endif; ?>
                            </td>

                        </tr>
                    <?php endwhile; ?>
                </form>
            </tbody>
        </table>

        <!-- Pagination Links -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=product_type&page_num=<?php echo $page - 1; ?>">&laquo; ก่อนหน้า</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=product_type&page_num=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=product_type&page_num=<?php echo $page + 1; ?>">ถัดไป &raquo;</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            let foundAny = false; // ตัวแปรเพื่อเก็บสถานะการค้นหา

            for (let i = 1; i < tr.length; i++) { // เริ่มจาก 1 เพื่อข้าม header
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
                tr[i].style.display = found ? "" : "none"; // แสดงหรือซ่อนแถว
                if (found) {
                    foundAny = true; // ถ้าพบข้อมูลอย่างน้อย 1 แถว
                }
            }

            // แสดงข้อความเมื่อไม่พบข้อมูล
            const noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    const message = document.createElement("p");
                    message.id = "noDataMessage";
                    message.textContent = "ไม่พบข้อมูล";
                    message.style.color = "red"; // ปรับสีข้อความ
                    message.style.fontWeight = "bold"; // ปรับความหนาของข้อความ
                    message.style.marginTop = "10px"; // เพิ่มระยะห่างด้านบน
                    table.parentNode.insertBefore(message, table); // เพิ่มข้อความก่อนตาราง
                }
            } else {
                if (noDataMessage) {
                    noDataMessage.remove(); // ลบข้อความเมื่อพบข้อมูล
                }
            }
        }
    </script>


    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                const notification = document.getElementById('notification');
                notification.style.display = 'block'; // แสดงการแจ้งเตือน

                // ลบการแจ้งเตือนหลัง 2 วินาที
                setTimeout(() => {
                    notification.style.display = 'none'; // ซ่อนการแจ้งเตือน
                }, 2000);
            }
        });
    </script>

    <script>
        // เมื่อคลิกปุ่มแก้ไข ให้ซ่อนปุ่มแก้ไขและลบ และแสดงปุ่มบันทึก
        document.querySelectorAll('.edit-button').forEach(button => {
            button.addEventListener('click', function() {
                // หยุดการกระทำของลิงก์
                event.preventDefault();
                // ซ่อนปุ่มแก้ไขและลบ
                this.parentElement.querySelector('.edit-delete-buttons').style.display = 'none';
                // แสดงปุ่มบันทึก
                this.parentElement.querySelector('.save-button').style.display = 'block';
            });
        });
    </script>

</body>

</html>