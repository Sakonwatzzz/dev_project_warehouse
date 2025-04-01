<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['personnel_type'])) {
        $personnel_type = $_POST['personnel_type'] ?? '';
        try {
            $stmt = $pdo->prepare('INSERT INTO personnel_type (personnel_type_name) VALUES (?)');
            $stmt->execute([$personnel_type]);
            header('Location: /index.php?page=personnel_type');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_personnel_type'])) {
        $id = $_POST['id'];
        $newpersonnel_type = $_POST['update_personnel_type'];
        try {
            $stmt = $pdo->prepare("UPDATE personnel_type SET personnel_type_name = ? WHERE personnel_type_id = ?");
            $stmt->execute([$newpersonnel_type, $id]);
            header('Location: /index.php?page=personnel_type');
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
        $stmt = $pdo->prepare("DELETE FROM personnel_type WHERE personnel_type_id = ?");
        $stmt->execute([$id]);
        header("Location: ?page=personnel_type");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// จำนวนข้อมูลทั้งหมดในตาราง
$query = "SELECT COUNT(*) as total_count FROM personnel_type";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// การแบ่งหน้า
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// นับจำนวนรายการทั้งหมด
try {
    $total_query = "SELECT COUNT(*) FROM personnel_type"; // เปลี่ยนจาก warehouse เป็น personnel_type
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// ดึงข้อมูลตามการแบ่งหน้า
try {
    $query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $personnel_types = $stmt;
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
    <title>Personnel Type</title>
</head>

<body>
    <div class="font-[Tahoma] mx-[35rem] mt-11">
        <div class=" flex ">
            <div class="flex justify-center items-center">
                <h2 class="w-[190px] text-2xl font-bold">ประเภทบุคลากร</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="w-full flex justify-end ">
                <input type="text" id="searchInput" class="pl-2 w-[250px] h-[40px] outline-none border border-blue-300  rounded-lg text-base text-blue-500 placeholder:text-blue-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-blue-500 sm:text-sm/6 " onkeyup="searchTable()" tabindex="1" placeholder="ค้นหา...">
            </div>

        </div>


        <table class=" mt-5 mb-8 w-[100%]   shadow-lg" id="dataTable">
            <tr>
                <form action="" method="POST">

                    <td class="p-2 bg-white">
                        <input type="text" id="personnel_type" name="personnel_type" placeholder="ชื่อบุคลากร" required
                            class=" w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </td>
                    <td class="p-2 bg-white border border-gray-300 text-center">
                        <button type="submit"
                            class=" w-32 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-400">
                            เพิ่ม
                        </button>
                    </td>
                </form>
            </tr>
            <thead>
                <tr>

                    <th class="w-[300px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow  font-bold p-5">ชื่อประเภท</th>
                    <th class="w-[120px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow  font-bold p-5">การจัดการ</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach ($personnel_types as $personneltype): ?>
                    <tr data-id="<?php echo $personneltype['personnel_type_id']; ?>" class="bg-white hover:bg-gray-100 cursor-pointer">
                        <td class="type-name py-2 px-4 border border-gray-300"><?php echo ($personneltype['personnel_type_name']); ?></td>
                        <td class="text-center py-2 px-4 border border-gray-300">
                            <button onclick="enableEdit(this)" class="edit-button bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 
                            text-white 
                            text-lg 
                            border border-yellow-700 
                            border-t-white 
                            border-l-white 
                            px-2 
                            py-1 
                            rounded 
                            shadow-md 
                            hover:bg-[#FFF9C4] 
                            active:border-[#FFF176] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner
                            ">แก้ไข</button>
                            <button class="bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 
                            border-t-white 
                            border-l-white 
                            px-4 
                            py-1 
                            rounded 
                                            shadow-md 
                            hover:bg-[#FFEBEE] 
                            active:border-[#EF9A9A] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner">
                                <a href="?page=personnel_type&id=<?php echo $personneltype['personnel_type_id']; ?>" onclick="return confirm('ยืนยันการลบข้อมูล?')" class="delete-button">ลบ</a>
                            </button>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!--โค้ดนี้ใช้ในการแบ่งหน้าในตาราง-->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a href="?page=personnel_type&page_num=<?php echo ($page - 1); ?>">&laquo; ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a href="?page=personnel_type&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=personnel_type&page_num=<?php echo ($page + 1); ?>">ถัดไป &raquo;</a>
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
            const thead = table.querySelector("thead");
            let foundAny = false;

            // Always keep the header visible
            thead.style.display = "";

            // Always keep the input row visible 
            tr[0].style.display = "";

            // Search through data rows (start from index 2 to skip header and input row)
            for (let i = 2; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName("td");
                let rowMatchesSearch = false;

                // Check every cell in the row
                for (let j = 0; j < td.length; j++) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        rowMatchesSearch = true;
                        break;
                    }
                }

                tr[i].style.display = rowMatchesSearch ? "" : "none";
                if (rowMatchesSearch) {
                    foundAny = true;
                }
            }

            // Handle "No data" message
            let noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    noDataMessage = document.createElement("p");
                    noDataMessage.id = "noDataMessage";
                    noDataMessage.textContent = "ไม่พบข้อมูล";
                    noDataMessage.style.color = "red";
                    noDataMessage.style.fontWeight = "bold";
                    noDataMessage.style.textAlign = "center";
                    noDataMessage.style.marginTop = "10px";
                    table.parentNode.insertBefore(noDataMessage, table.nextSibling);
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
                saveButton.classList.add('save-button',
                    'bg-gradient-to-b',
                    'from-green-300',
                    'via-green-500',
                    'to-green-700',
                    'text-white',
                    'text-lg',
                    'border',
                    'border-green-700',
                    'border-t-white',
                    'border-l-white',
                    'px-2',
                    'py-1',
                    'rounded',
                    'shadow-md',
                    'hover:bg-[#E8F5E9]',
                    'active:border-[#A5D6A7]',
                    'active:border-b-white',
                    'active:border-r-white',
                    'active:shadow-inner'
                );
                saveButton.onclick = function() {
                    // Save changes
                    const newtypeName = typeNameCell.querySelector('.edit-input').value;

                    // Update the warehouse in the database
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `id=${typeId}&update_personnel_type=${encodeURIComponent(newtypeName)}`
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

    <script>
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