<?php
require_once __DIR__ . '../../../../config/database_server.php';

// เพิ่มตัวแปรสำหรับการค้นหา
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Function to fetch data from database
function fetchData($pdo, $query, $params = [])
{
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        throw new Exception("Database error: " . $e->getMessage());
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['brandname']) && !empty(trim($_POST['brandname']))) {
            $brandname = trim($_POST['brandname']);
            $stmt = $pdo->prepare('INSERT INTO product_brand (product_brand_name) VALUES (?)');
            $stmt->execute([$brandname]);
            header('Location: ?page=product_brand&add_success=1');
            exit;
        } elseif (isset($_POST['update_brandname']) && isset($_POST['id'])) {
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $newBrandName = trim($_POST['update_brandname']);
            if ($id && !empty($newBrandName)) {
                $stmt = $pdo->prepare("UPDATE product_brand SET product_brand_name = ? WHERE product_brand_id = ?");
                $stmt->execute([$newBrandName, $id]);
                header('Location: ?page=product_brand&edit_success=1');
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
            $stmt = $pdo->prepare("DELETE FROM product_brand WHERE product_brand_id = ?");
            $stmt->execute([$id]);
            header("Location: ?page=product_brand&delete_success=1");
            exit;
        }
    } catch (PDOException $e) {
        echo "Delete error: " . htmlspecialchars($e->getMessage());
        exit;
    }
}

// Total count
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_count FROM product_brand");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
} catch (PDOException $e) {
    $count = 0;
    echo "Count error: " . htmlspecialchars($e->getMessage());
}

// Pagination และ การค้นหา
$records_per_page = 5;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    // สร้าง SQL query สำหรับนับรายการทั้งหมด ตามเงื่อนไขการค้นหา
    $count_query = "SELECT COUNT(*) FROM product_brand";
    $params = [];

    if (!empty($search)) {
        $count_query .= " WHERE product_brand_name LIKE :search OR product_brand_id LIKE :search OR product_brand_date LIKE :search";
        $params[':search'] = "%$search%";
    }

    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $total_pages = 1;
    echo "Pagination error: " . htmlspecialchars($e->getMessage());
}


try {
    // Fetch personnel data
    $query = "SELECT personnel_id, personnel_name FROM personnel";
    $personnels = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT 
        pb.product_brand_id,
        pb.product_brand_name,
        DATE_FORMAT(pb.product_brand_date, '%Y-%m-%d %H:%i:%s') as product_brand_date,
        per.personnel_id,
        per.personnel_name,
        COALESCE(COUNT(p.product_id), 0) as product_count
        FROM product_brand pb
        LEFT JOIN product p ON pb.product_brand_id = p.product_brand_id
        LEFT JOIN personnel per ON pb.personnel_id = per.personnel_id";

    $params = [];
    if (!empty($search)) {
        $query .= " WHERE pb.product_brand_name LIKE :search OR pb.product_brand_id LIKE :search";
        $params[':search'] = "%$search%";
    }

    $query .= " GROUP BY pb.product_brand_id, pb.product_brand_name 
        LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $productbrand = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $productbrand = [];
    echo "Fetch error: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>จัดการแบรนด์สินค้า</title>
    <style>
        @media (max-width: 768px) {
            .table-responsive {
                display: none;
            }

            .card-responsive {
                display: block;
            }
        }

        @media (min-width: 769px) {
            .table-responsive {
                display: block;
            }

            .card-responsive {
                display: none;
            }
        }
    </style>
</head>

<body class="font-sans bg-gray-100">
    <div class=" mx-auto px-4 py-8 md:px-8 lg:px-16">

        <!-- ปุ่มดาวน์โหลดรายงาน -->
        <div class="mb-4 flex space-x-2">
            <a href="/page/product/report/export_product_brand_pdf.php" target="_blank">
                <button class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex items-center text-sm md:text-base">
                    <i class="fas fa-file-pdf mr-2"></i> PDF
                </button>
            </a>
            <a href="/page/product/report/export_product_brand_excel.php" target="_blank">
                <button class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition flex items-center text-sm md:text-base">
                    <i class="fas fa-file-excel mr-2"></i> Excel
                </button>
            </a>
        </div>

        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-list mr-2"></i>รายการแบรนด์สินค้า
                        </h2>
                        <span class="px-3 py-1 bg-white font-bold border border-gray-500 rounded-md text-sm md:text-base"><?php echo $count; ?> รายการ</span>
                    </div>

                    <!-- แก้ไขส่วน input search ให้ส่งค่าไปยัง GET -->
                    <div class="w-full md:w-1/2">
                        <form action="" method="GET" class="w-full" id="searchForm">
                            <input type="hidden" name="page" value="product_brand">
                            <div class="relative">
                                <input type="text" name="search" id="searchInput"
                                    placeholder="ค้นหาแบรนด์สินค้า..." value="<?php echo htmlspecialchars($search); ?>"
                                    class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base"
                                    oninput="checkEmptySearch(this)">
                                <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="p-4 md:p-6">
                <!-- Table View (Desktop/iPad) -->
                <div class="table-responsive overflow-x-auto">
                    <table class="w-full border-collapse" id="dataTable">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">รหัสแบรนด์</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">ชื่อแบรนด์สินค้า</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">พนักงาน</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">วันที่</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">จำนวนสินค้า</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Add Form Row -->
                            <tr>
                                <form action="" method="POST">
                                    <td class="px-4 py-2 border bg-white text-center text-gray-600 text-sm md:text-base">อัตโนมัติ</td>
                                    <td class="px-4 py-2 border bg-white">
                                        <input type="text" id="brandname" name="brandname"
                                            placeholder="ชื่อแบรนด์"
                                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base"
                                            required />
                                    </td>
                                    <td class="px-4 py-2 border bg-white">
                                        <select name="personnel_id" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base" required>
                                            <option value="">เลือกพนักงาน</option>
                                            <?php foreach ($personnels as $personnel): ?>
                                                <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                                                    <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2 border bg-white text-center text-sm md:text-base">อัตโนมัติ</td>
                                    <td class="px-4 py-2 border bg-white text-center text-sm md:text-base">-</td>
                                    <td class="w-[120px] px-4 py-2 border bg-white text-center">
                                        <button type="submit"
                                            class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex justify-center items-center text-sm md:text-base">
                                            <i class="fas fa-plus mr-1"></i>เพิ่ม
                                        </button>
                                    </td>

                                </form>
                            </tr>
                            <!-- Data Rows -->
                            <?php if (count($productbrand) > 0): ?>
                                <?php foreach ($productbrand as $brand): ?>
                                    <tr class="hover:bg-gray-100" data-id="<?php echo htmlspecialchars($brand['product_brand_id']); ?>">
                                        <td class="px-4 py-1 border text-sm md:text-base"><?php echo htmlspecialchars($brand['product_brand_id']); ?></td>
                                        <td class="px-4 py-1 border brand-name text-sm md:text-base"><?php echo htmlspecialchars($brand['product_brand_name']); ?></td>
                                        <td class="px-4 py-1 border brand-name text-sm md:text-base"><?php echo htmlspecialchars($brand['personnel_name']); ?></td>
                                        <td class="px-4 py-1 border brand-name text-sm md:text-base"><?php echo htmlspecialchars($brand['product_brand_date']); ?></td>
                                        <td class="px-4 py-1 border text-center cursor-pointer">
                                            <a href="?page=product_brandgroup&tabel=product_brand&id=<?php echo $brand['product_brand_id']; ?>&product_brand_name=<?php echo urlencode($brand['product_brand_name']); ?>"
                                                class="text-orange-600 text-sm md:text-base">
                                                <?php echo number_format($brand['product_count']); ?> รายการ
                                            </a>
                                        </td>
                                        <td class="px-4 py-1 border text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button onclick="enableEdit(this)"
                                                    class="edit-btn bg-yellow-500 text-white px-4 py-1 rounded-lg hover:bg-yellow-600 transition flex items-center text-sm md:text-base">
                                                    <i class="fas fa-edit mr-1"></i>แก้ไข
                                                </button>
                                                <a href="?page=product_brand&id=<?php echo htmlspecialchars($brand['product_brand_id']); ?>"
                                                    onclick="return confirm('ยืนยันการลบข้อมูล?')"
                                                    class="delete-btn bg-red-500 text-white px-4 py-1 rounded-lg hover:bg-red-600 transition flex items-center text-sm md:text-base">
                                                    <i class="fas fa-trash-alt mr-1"></i>ลบ
                                                </a>
                                                <button class="save-btn hidden bg-green-500 text-white px-4 py-1 rounded-lg hover:bg-green-600 transition items-center text-sm md:text-base"
                                                    onclick="saveEdit(this)">
                                                    <i class="fas fa-save mr-1"></i>บันทึก
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-gray-600 text-sm md:text-base">ไม่มีข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Card View (Mobile) -->
                <div class="card-responsive">
                    <!-- Add Form Card -->
                    <div class="bg-white p-4 mb-4 rounded-lg shadow">
                        <form action="" method="POST">
                            <div class="mb-4">
                                <label class="block text-gray-700 font-semibold mb-2 text-sm">ชื่อแบรนด์</label>
                                <input type="text" id="brandname_mobile" name="brandname"
                                    placeholder="ชื่อแบรนด์"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    required />
                            </div>
                            <button type="submit"
                                class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex items-center justify-center text-sm">
                                <i class="fas fa-plus mr-1"></i>เพิ่ม
                            </button>
                        </form>
                    </div>
                    <!-- Data Cards -->
                    <?php if (count($productbrand) > 0): ?>
                        <?php foreach ($productbrand as $brand): ?>
                            <div class="bg-white p-4 mb-4 rounded-lg shadow" data-id="<?php echo htmlspecialchars($brand['product_brand_id']); ?>">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">รหัสแบรนด์:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($brand['product_brand_id']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm brand-name">ชื่อแบรนด์:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($brand['product_brand_name']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">จำนวนสินค้า:</span>
                                    <a href="?page=product_brandgroup&tabel=product_brand&id=<?php echo $brand['product_brand_id']; ?>&product_brand_name=<?php echo urlencode($brand['product_brand_name']); ?>"
                                        class="text-orange-600 hover:underline text-sm">
                                        <?php echo number_format($brand['product_count']); ?> รายการ
                                    </a>
                                </div>
                                <div class="flex justify-center space-x-2 mt-4">
                                    <button onclick="enableEditMobile(this)"
                                        class="edit-btn bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition flex items-center text-sm">
                                        <i class="fas fa-edit mr-1"></i>แก้ไข
                                    </button>
                                    <a href="?page=product_brand&id=<?php echo htmlspecialchars($brand['product_brand_id']); ?>"
                                        onclick="return confirm('ยืนยันการลบข้อมูล?')"
                                        class="delete-btn bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 transition flex items-center text-sm">
                                        <i class="fas fa-trash-alt mr-1"></i>ลบ
                                    </a>
                                    <button class="save-btn hidden bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition items-center text-sm"
                                        onclick="saveEditMobile(this)">
                                        <i class="fas fa-save mr-1"></i>บันทึก
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-600 text-sm">ไม่มีข้อมูล</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="pagination mt-5 flex flex-wrap justify-center items-center gap-2">
            <?php if ($total_pages > 1): ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <a href="?page=product_brand&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=product_brand&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=product_brand&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 rounded-md transition-colors text-sm md:text-base <?= $i == $page ? 'bg-gray-800 text-white' : 'bg-blue-100 text-black hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                    <a href="?page=product_brand&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>

                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=product_brand&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        ถัดไป »
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification -->
    <div id="notification" class="hidden fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white border-2 border-gray-400 rounded-md shadow-lg w-80 md:w-96 h-32 z-50">
        <div id="notification-header" class="p-2 rounded-t-md font-bold text-sm md:text-base"></div>
        <div id="notification-body" class="p-5 text-center text-base md:text-lg"></div>
    </div>

    <script>
        // ฟังก์ชันตรวจสอบเมื่อลบข้อความค้นหาจนหมด
        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                // ถ้าช่องค้นหาว่างเปล่า ให้เปลี่ยนเส้นทางไปยังหน้าแสดงข้อมูลทั้งหมด
                window.location.href = '?page=product_brand';
            }
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

            // ถ้ามีการค้นหา ให้ focus ที่ช่องค้นหา
            if (urlParams.has('search')) {
                document.getElementById('searchInput').focus();
            }

            // เพิ่ม event listener สำหรับการกด Escape เพื่อยกเลิกการค้นหา
            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.location.href = '?page=product_brand';
                }
            });
        });

        // ก่อน reload ให้เก็บไว้
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                localStorage.setItem('searchPreserve', this.value);
            }
        });

        // หลังโหลดหน้า
        document.addEventListener('DOMContentLoaded', () => {
            const preserved = localStorage.getItem('searchPreserve');
            if (preserved) {
                const input = document.getElementById('searchInput');
                input.value = preserved;
                input.focus();
                // เคลื่อนเคอร์เซอร์ไปหลังข้อความ
                input.selectionStart = input.selectionEnd = preserved.length;
                localStorage.removeItem('searchPreserve');
            }
        });

        function enableEdit(button) {
            const row = button.closest('tr');
            const brandNameCell = row.querySelector('.brand-name');
            const originalName = brandNameCell.textContent.trim();

            brandNameCell.innerHTML = `<input type="text" class="edit-input w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base" value="${originalName}" />`;
            button.classList.add('hidden');
            row.querySelector('.delete-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
        }

        function enableEditMobile(button) {
            const card = button.closest('div[data-id]');
            const brandNameSpan = card.querySelector('.brand-name + span');
            const originalName = brandNameSpan.textContent.trim();

            brandNameSpan.innerHTML = `<input type="text" class="edit-input w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" value="${originalName}" />`;
            button.classList.add('hidden');
            card.querySelector('.delete-btn').classList.add('hidden');
            card.querySelector('.save-btn').classList.remove('hidden');
        }

        function saveEdit(button) {
            const row = button.closest('tr');
            const brandId = row.getAttribute('data-id');
            const newName = row.querySelector('.edit-input').value.trim();
            const brandNameCell = row.querySelector('.brand-name');

            if (!newName) {
                alert('กรุณากรอกชื่อแบรนด์');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${brandId}&update_brandname=${encodeURIComponent(newName)}`
                })
                .then(response => {
                    if (response.ok) {
                        brandNameCell.textContent = newName;
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

        function saveEditMobile(button) {
            const card = button.closest('div[data-id]');
            const brandId = card.getAttribute('data-id');
            const newName = card.querySelector('.edit-input').value.trim();
            const brandNameSpan = card.querySelector('.brand-name + span');

            if (!newName) {
                alert('กรุณากรอกชื่อแบรนด์');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${brandId}&update_brandname=${encodeURIComponent(newName)}`
                })
                .then(response => {
                    if (response.ok) {
                        brandNameSpan.textContent = newName;
                        const editBtn = card.querySelector('.edit-btn');
                        const deleteBtn = card.querySelector('.delete-btn');
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

        function showNotification(message, headerClass) {
            const notification = document.getElementById('notification');
            const header = document.getElementById('notification-header');
            const body = document.getElementById('notification-body');

            header.className = `p-2 rounded-t-md font-bold ${headerClass} text-sm md:text-base`;
            header.textContent = 'แจ้งเตือน';
            body.textContent = message;
            notification.classList.remove('hidden');

            setTimeout(() => {
                notification.classList.add('hidden');
            }, 1000);
        }
    </script>
</body>

</html>