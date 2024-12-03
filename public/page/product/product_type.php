<?php
require_once __DIR__ . '../../../../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['typename'])) {
        $typename = $_POST['typename'] ?? '';
        try {
            $stmt = $pdo->prepare('INSERT INTO product_type (product_type_name) VALUES (?)');
            $stmt->execute([$typename]);
            header('Location: /index.php?page=product_type');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    } elseif (isset($_POST['update_typename'])) {
        $id = $_POST['id'];
        $newTypeName = $_POST['update_typename'];
        try {
            $stmt = $pdo->prepare("UPDATE product_type SET product_type_name = ? WHERE product_type_id = ?");
            $stmt->execute([$newTypeName, $id]);
            header('Location: /index.php?page=product_type');
            exit;
        } catch (PDOException $e) {
            echo 'Database error: ' . $e->getMessage();
        }
    }
}

// Check for delete action
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

// Count total records in the table
$query = "SELECT COUNT(*) as total_count FROM product_type";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// Pagination settings
$records_per_page = 5;
$page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// Calculate total pages
try {
    $total_query = "SELECT COUNT(*) FROM product_type";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Fetch data for the current page
try {
    $query = "SELECT product_type_id, product_type_name FROM product_type LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $producttypes = $stmt;
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
    <link href="../../output.css" rel="stylesheet">
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
            background-color: #aec5e366;
            color: black;
            border-radius: 5px;
            margin: 0 4px;
        }

        .pagination a.active {
            background-color: #333;
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
    </style>
    <title>Document</title>
</head>

<body>
    <div class="font-[Tahoma] mx-[15rem] mt-11">
        <div class="title">
            <div class="count">
                <h2 class="text-2xl font-bold">ข้อมูลประเภทสินค้า</h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาประเภทสินค้า...">
            </div>
        </div>

        <table class=" mt-5 mb-8 w-[100%]  border-2 border-[#A0A0A0] shadow-lg" id="dataTable">
            <tr>
                <form action="" method="POST">
                    <td class="bg-white">
                        <input type="text" id="typename" name="typename" placeholder="ชื่อประเภทสินค้า" required
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none hover:border-gray-600" />
                    </td>
                    <td class="bg-white">
                        <button type="submit"
                            class="px-11 py-2 bg-blue-500 text-white font-semibold rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 transition ease-in-out duration-200">
                            เพิ่ม
                        </button>
                    </td>
                </form>
            </tr>
            <thead>
                <tr>
                    <th class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-2 border border-blue-700 shadow">ชื่อประเภทสินค้า</th>
                    <th class="bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 text-white text-lg px-3 py-2 border border-blue-700 shadow">การจัดการ</th>
                </tr>
            </thead>
            <tbody id="tableBody">
                <?php foreach ($producttypes as $type): ?>
                    <tr class="bg-white hover:bg-[#b7babe66] cursor-pointer" data-id="<?php echo $type['product_type_id']; ?>">
                        <td class="type-name"><?php echo ($type['product_type_name']); ?></td>
                        <td>
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
                            ">
                                แก้ไข
                            </button>

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
                                <a href="?page=product_type&id=<?php echo $type['product_type_id']; ?>" onclick="return confirm('ยืนยันการลบข้อมูล?')" class="delete-button  ">
                                    ลบ</a>
                            </button>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
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
            const originalTypeName = typeNameCell.innerText;

            if (button.innerText === 'แก้ไข') {
                // Change to edit mode
                typeNameCell.innerHTML = `<input type="text" class="edit-input" style="border: 1px solid #333;" value="${originalTypeName}" />`;

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
                    const newTypeName = typeNameCell.querySelector('.edit-input').value;

                    // Update the product type in the database using a POST request
                    fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `id=${typeId}&update_typename=${encodeURIComponent(newTypeName)}`
                        }).then(response => response.text())
                        .then(() => {
                            // Update the table with the new value after saving
                            typeNameCell.innerHTML = newTypeName;
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

                // Append save button after the actions column
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