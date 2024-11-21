<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['product_type'])) {
        $product_type = $_POST['product_type'] ?? '';
        try {
            $stmt = $pdo->prepare('INSERT INTO product_type (product_type_name) VALUES (?)');
            $stmt->execute([$product_type]);
            header('Location: /index.php?page=product_type');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_product_type'])) {
        $id = $_POST['id'];
        $newproduct_type = $_POST['update_product_type'];
        try {
            $stmt = $pdo->prepare("UPDATE product_type SET product_type_name = ? WHERE product_type_id = ?");
            $stmt->execute([$newproduct_type, $id]);
            header('Location: /index.php?page=product_type');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    }
}

// เงื่อนไขการลบข้อมูล (เช็คเฉพาะเมื่อมี 'id' ใน $_GET)
if (isset($_GET['id'])) {
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

// จำนวนข้อมูลทั้งหมดในตาราง
$query = "SELECT COUNT(*) as total_count FROM product_type";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// การแบ่งหน้า
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// นับจำนวนรายการทั้งหมด
try {
    $total_query = "SELECT COUNT(*) FROM product_type"; // เปลี่ยนจาก warehouse เป็น product_type
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// ดึงข้อมูลตามการแบ่งหน้า
try {
    $query = "SELECT product_type_id, product_type_name FROM product_type LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $productbrands = $stmt;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
    <title>Document</title>
</head>

<body>
    <div class="personnel-list" ">
        <div class=" title">
        <div class="count">
            <h2 style="font-weight: bold;">ข้อมูลแบรนด์สินค้า</h2>
            <div class="listcount">
                <?php echo $count; ?>
            </div>
        </div>
        <div class="search">
            <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาประเภทสินค้า...">
        </div>

    </div>


    <table class="data-table" id="dataTable">
        <tr>
            <form action="" method="POST">
                <td></td>
                <td>
                    <input type="text" id="product_type_name" name="product_type_name" placeholder="ชื่อแบรนด์" required
                        class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 placeholder-gray-400" />
                </td>
                <td>
                    <button type="submit"
                        class="save-button px-6 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                        เพิ่ม
                    </button>
                </td>
            </form>
        </tr>
        <thead>
            <tr>
                <th>รหัสแบรนด์สินค้า</th>
                <th>ชื่อแบรนด์สินค้า</th>
                <th>การจัดการ</th>
            </tr>
        </thead>
        <tbody id="tableBody">
            <?php foreach ($productbrands as $brand): ?>
                <tr data-id="<?php echo $brand['product_type_id']; ?>">
                    <td><?php echo $brand['product_type_id']; ?></td>
                    <td class="type-name"><?php echo ($brand['product_type_name']); ?></td>
                    <td>
                        <button onclick="enableEdit(this)" class="edit-button text-blue-500 hover:text-blue-700">แก้ไข</button>
                        <a href="?page=product_type&id=<?php echo $brand['product_type_id']; ?>" onclick="return confirm('ยืนยันการลบข้อมูล?')" class="delete-button  text-red-500 hover:text-red-700">ลบ</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!--โค้ดนี้ใช้ในการแบ่งหน้าในตาราง-->
    <div class="pagination">
        <?php if ($total_pages > 1): ?>
            <?php if ($page > 1): ?>
                <a href="?page=product_type&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <a href="#" class="active"><?php echo $i; ?></a>
                <?php else: ?>
                    <a href="?page=product_type&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=product_type&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
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

        function enableEdit(button) {
            const row = button.closest('tr');
            const typeNameCell = row.querySelector('.type-name');
            const typeId = row.getAttribute('data-id');
            const originaltypeName = typeNameCell.innerText;

            if (button.innerText === 'แก้ไข') {
                // เปลี่ยนเป็นโหมดแก้ไข
                typeNameCell.innerHTML = `<input type="text" class="edit-input" style="border: 1px solid #333;" value="${originaltypeName}" />`;

                button.innerText = 'บันทึก';
                const deleteButton = row.querySelector('.delete-button');
                const editButton = row.querySelector('.edit-button');
                deleteButton.style.display = 'none';
                editButton.style.display = 'none';

                const saveButton = document.createElement('button');
                saveButton.innerText = 'บันทึก';
                saveButton.classList.add('save-button', 'text-green-500', 'hover:text-green-700');
                saveButton.onclick = function() {
                    // Save changes
                    const newtypeName = typeNameCell.querySelector('.edit-input').value;

                    // Update the warehouse in the database
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `id=${typeId}&update_product_type=${encodeURIComponent(newtypeName)}`
                    }).then(() => {
                        // Update the table with new values after saving
                        typeNameCell.innerHTML = newtypeName;
                        // Reset to edit mode
                        button.innerText = 'แก้ไข';
                        deleteButton.style.display = 'inline-block';
                        editButton.style.display = 'inline-block';
                        saveButton.remove(); // Remove the save button
                    }).catch(error => {
                        console.error('Error:', error);
                        alert('ไม่สามารถบันทึกการเปลี่ยนแปลงได้');
                    });
                };
                row.querySelector('td:last-child').appendChild(saveButton);
            }

        }
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