<?php
require_once __DIR__ . '../../../../config/database_server.php';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['typename']) && !empty(trim($_POST['typename']))) {
            $typename = trim($_POST['typename']);
            $stmt = $pdo->prepare('INSERT INTO product_type (product_type_name) VALUES (?)');
            $stmt->execute([$typename]);
            header('Location: ?page=product_type&add_success=1');
            exit;
        } elseif (isset($_POST['update_typename']) && isset($_POST['id'])) {
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $newTypeName = trim($_POST['update_typename']);
            if ($id && !empty($newTypeName)) {
                $stmt = $pdo->prepare("UPDATE product_type SET product_type_name = ? WHERE product_type_id = ?");
                $stmt->execute([$newTypeName, $id]);
                header('Location: ?page=product_type&edit_success=1');
                exit;
            }
        }
    } catch (PDOException $e) {
        echo 'Database error: ' . htmlspecialchars($e->getMessage());
        exit;
    }
}

// Handle DELETE request
if (isset($_GET['id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM product_type WHERE product_type_id = ?");
            $stmt->execute([$id]);
            header("Location: ?page=product_type&delete_success=1");
            exit;
        }
    } catch (PDOException $e) {
        echo "Delete error: " . htmlspecialchars($e->getMessage());
        exit;
    }
}

// Total count
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_count FROM product_type");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
} catch (PDOException $e) {
    $count = 0;
    echo "Count error: " . htmlspecialchars($e->getMessage());
}

// Pagination
$records_per_page = 10;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $total_records = $pdo->query("SELECT COUNT(*) FROM product_type")->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}

// Fetch data
try {
    $query = "SELECT 
        pt.product_type_id,
        pt.product_type_name,
        COALESCE(COUNT(p.product_id), 0) as product_count
        FROM product_type pt
        LEFT JOIN product p ON pt.product_type_id = p.product_type_id
        GROUP BY pt.product_type_id, pt.product_type_name 
        LIMIT :offset, :records_per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $producttypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $producttypes = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <title>จัดการประเภทสินค้า</title>
</head>

<body class="bg-gray-100">
    <div class="font-sans mx-96 mt-11">
        <!-- Title and Search -->
        <div class="grid grid-cols-2 gap-2 mb-5">
            <div class="flex items-center space-x-6">
                <h2 class="text-2xl font-bold text-gray-800">ข้อมูลประเภทสินค้า</h2>
                <span class="text-base text-center text-gray-800 px-3 py-1 border border-gray-400 rounded-md bg-white shadow-md w-20"><?php echo $count; ?></span>
            </div>
            <div class="mt-4 md:mt-0">
                <input type="text" id="searchInput" onkeyup="searchTable()"
                    placeholder="ค้นหาประเภทสินค้า..."
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <!-- Table -->
        <table class="w-full mt-5 mb-8 border-2 border-gray-400 shadow-lg" id="dataTable">
            <!-- Add Form -->
            <tr>
                <form action="" method="POST">
                    <td class="bg-white p-2">
                        <input type="text" id="typename" name="typename"
                            placeholder="ชื่อประเภทสินค้า"
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required />
                    </td>
                    <td class="p-2 bg-white border border-gray-300 text-center text-lg italic text-gray-600">อัตโนมัติ</td>
                    <td class="bg-white p-2 text-center">
                        <button type="submit"
                            class="w-32 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white font-semibold rounded-lg transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-400">
                            เพิ่ม
                        </button>
                    </td>
                </form>
            </tr>

            <!-- Header -->
            <thead>
                <tr class="bg-gradient-to-b from-blue-200 to-blue-100 text-gray-800 text-lg">
                    <th class="w-2/5 px-3 py-2 border border-gray-500">ชื่อประเภทสินค้า</th>
                    <th class="w-2/5 px-3 py-2 border border-gray-500">จำนวนสินค้า</th>
                    <th class="w-1/5 px-3 py-2 border border-gray-500">การจัดการ</th>
                </tr>
            </thead>

            <!-- Body -->
            <tbody id="tableBody">
                <?php foreach ($producttypes as $type): ?>
                    <tr class="bg-white hover:bg-gray-100 cursor-pointer" data-id="<?php echo $type['product_type_id']; ?>">
                        <td class="type-name py-2 px-4 border border-gray-300"><?php echo htmlspecialchars($type['product_type_name']); ?></td>
                        <td class="py-2 px-4 border border-gray-300 text-center hover:bg-gray-200 cursor-pointer"
                            onclick="window.location.href='?page=product_typegroup&tabel=product_type&id=<?php echo $type['product_type_id']; ?>&product_type_name=<?php echo urlencode($type['product_type_name']); ?>'">
                            <?php echo number_format($type['product_count']); ?> รายการ
                        </td>
                        <td class="py-2 px-4 border border-gray-300 text-center">
                            <div class="space-x-2">
                                <button onclick="enableEdit(this)"
                                    class="edit-btn bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 text-white text-lg px-4 py-1 rounded shadow-md hover:from-yellow-200 hover:via-yellow-400 hover:to-yellow-600 transition duration-200">
                                    แก้ไข
                                </button>
                                <a href="?page=product_type&id=<?php echo $type['product_type_id']; ?>"
                                    onclick="return confirm('ยืนยันการลบข้อมูล?')"
                                    class="delete-btn inline-block bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg px-4 py-1 rounded shadow-md hover:from-red-200 hover:via-red-400 hover:to-red-600 transition duration-200">
                                    ลบ
                                </a>
                                <button class="save-btn hidden bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg px-4 py-1 rounded shadow-md hover:from-green-200 hover:via-green-400 hover:to-green-600 transition duration-200"
                                    onclick="saveEdit(this)">
                                    บันทึก
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="text-center space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?page=product_type&page_num=<?php echo ($page - 1); ?>"
                        class="inline-block px-4 py-2 bg-blue-100 hover:bg-blue-200 text-black rounded-md transition duration-200">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=product_type&page_num=<?php echo $i; ?>"
                        class="inline-block px-4 py-2 rounded-md transition duration-200 <?php echo $i === $page ? 'bg-gray-800 text-white' : 'bg-blue-100 hover:bg-blue-200 text-black'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=product_type&page_num=<?php echo ($page + 1); ?>"
                        class="inline-block px-4 py-2 bg-blue-100 hover:bg-blue-200 text-black rounded-md transition duration-200">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Notification -->
    <div id="notification" class="hidden fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white border-2 border-gray-400 rounded-md shadow-lg w-96 h-32 z-50">
        <div id="notification-header" class="p-2 rounded-t-md font-bold"></div>
        <div id="notification-body" class="p-5 text-center text-lg"></div>
    </div>

    <script>
        function enableEdit(button) {
            const row = button.closest('tr');
            const typeNameCell = row.querySelector('.type-name');
            const originalName = typeNameCell.textContent.trim();

            typeNameCell.innerHTML = `<input type="text" class="edit-input w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" value="${originalName}" />`;
            button.classList.add('hidden');
            row.querySelector('.delete-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
        }

        function saveEdit(button) {
            const row = button.closest('tr');
            const typeId = row.getAttribute('data-id');
            const newName = row.querySelector('.edit-input').value.trim();
            const typeNameCell = row.querySelector('.type-name');

            if (!newName) {
                alert('กรุณากรอกชื่อประเภทสินค้า');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${typeId}&update_typename=${encodeURIComponent(newName)}`
                })
                .then(response => {
                    if (response.ok) {
                        typeNameCell.textContent = newName;
                        const editBtn = row.querySelector('.edit-btn');
                        const deleteBtn = row.querySelector('.delete-btn');
                        editBtn.classList.remove('hidden');
                        deleteBtn.classList.remove('hidden');
                        button.classList.add('hidden');
                        showNotification('แก้ไขสำเร็จ', 'bg-cyan-200');
                    } else {
                        throw new Error('Failed to save');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('เกิดข้อผิดพลาดในการบันทึก', 'bg-red-200');
                });
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('dataTable');
            const tr = table.getElementsByTagName('tr');
            let foundAny = false;

            tr[0].style.display = ''; // Keep input row visible
            table.querySelector('thead').style.display = ''; // Keep header visible

            for (let i = 2; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName('td');
                let rowMatches = false;
                for (let j = 0; j < td.length; j++) {
                    const text = td[j].textContent.toLowerCase();
                    if (text.includes(filter)) {
                        rowMatches = true;
                        break;
                    }
                }
                tr[i].style.display = rowMatches ? '' : 'none';
                if (rowMatches) foundAny = true;
            }

            const noData = document.getElementById('noDataMessage');
            if (!foundAny && !noData) {
                const msg = document.createElement('p');
                msg.id = 'noDataMessage';
                msg.textContent = 'ไม่พบข้อมูล';
                msg.className = 'text-red-500 font-bold text-center mt-2';
                table.parentNode.insertBefore(msg, table.nextSibling);
            } else if (foundAny && noData) {
                noData.remove();
            }
        }

        function showNotification(message, headerClass) {
            const notification = document.getElementById('notification');
            const header = document.getElementById('notification-header');
            const body = document.getElementById('notification-body');

            header.className = `p-2 rounded-t-md font-bold ${headerClass}`;
            header.textContent = 'แจ้งเตือน';
            body.textContent = message;
            notification.classList.remove('hidden');

            setTimeout(() => {
                notification.classList.add('hidden');
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('add_success')) {
                showNotification('เพิ่มข้อมูลสำเร็จ', 'bg-cyan-200');
            } else if (urlParams.has('edit_success')) {
                showNotification('แก้ไขข้อมูลสำเร็จ', 'bg-cyan-200');
            } else if (urlParams.has('delete_success')) {
                showNotification('ลบข้อมูลสำเร็จ', 'bg-cyan-200');
            }
            document.getElementById('searchInput').focus();
        });
    </script>
</body>

</html>