<?php
require_once __DIR__ . '../../../../config/database_server.php';
require_once __DIR__ . '../../../fpdf186/fpdf.php';

// เพิ่มตัวแปรสำหรับการค้นหาและกรองวันที่
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

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
        if (isset($_POST['typename']) && !empty(trim($_POST['typename'])) && isset($_POST['personnel_id'])) {
            $typename = trim($_POST['typename']);
            $personnelId = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);
            $date = date('Y-m-d H:i:s');
            if ($personnelId) {
                $stmt = $pdo->prepare('INSERT INTO product_type (product_type_name, product_type_date, personnel_id) VALUES (?, ?, ?)');
                $stmt->execute([$typename, $date, $personnelId]);
                header('Location: ?page=product_type&add_success=1');
                exit;
            } else {
                throw new Exception("Invalid personnel ID");
            }
        } elseif (isset($_POST['update_typename']) && isset($_POST['id']) && isset($_POST['personnel_id'])) {
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $newTypeName = trim($_POST['update_typename']);
            $personnelId = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);
            $date = date('Y-m-d H:i:s');
            if ($id && !empty($newTypeName) && $personnelId) {
                $stmt = $pdo->prepare("UPDATE product_type SET product_type_name = ?, product_type_date = ?, personnel_id = ? WHERE product_type_id = ?");
                $stmt->execute([$newTypeName, $date, $personnelId, $id]);
                header('Location: ?page=product_type&edit_success=1');
                exit;
            } else {
                throw new Exception("Invalid input data");
            }
        }
    } catch (Exception $e) {
        echo 'Error: ' . htmlspecialchars($e->getMessage());
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
    $count_query = "SELECT COUNT(*) as total_count FROM product_type";
    $params = [];
    if ($start_date || $end_date || !empty($search)) {
        $count_query .= " WHERE 1=1";
        if ($start_date) {
            $count_query .= " AND product_type_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $count_query .= " AND product_type_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        if (!empty($search)) {
            $count_query .= " AND (product_type_name LIKE :search OR product_type_id LIKE :search)";
            $params[':search'] = "%$search%";
        }
    }
    $stmt = $pdo->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['total_count'];
} catch (PDOException $e) {
    $count = 0;
    echo "Count error: " . htmlspecialchars($e->getMessage());
}

// Pagination และ การค้นหา
$records_per_page = 6;
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;
$offset = ($page - 1) * $records_per_page;

try {
    $count_query = "SELECT COUNT(*) FROM product_type";
    $params = [];
    if ($start_date || $end_date || !empty($search)) {
        $count_query .= " WHERE 1=1";
        if ($start_date) {
            $count_query .= " AND product_type_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $count_query .= " AND product_type_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        if (!empty($search)) {
            $count_query .= " AND (product_type_name LIKE :search OR product_type_id LIKE :search)";
            $params[':search'] = "%$search%";
        }
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

// ดึงข้อมูลตามเงื่อนไขการค้นหาและวันที่
try {
    // Fetch personnel data
    $query = "SELECT personnel_id, personnel_name FROM personnel";
    $personnels = fetchData($pdo, $query)->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT 
        pt.product_type_id,
        pt.product_type_name,
        DATE_FORMAT(pt.product_type_date, '%Y-%m-%d %H:%i:%s') as product_type_date,
        per.personnel_id,
        per.personnel_name,
        COALESCE(COUNT(p.product_id), 0) as product_count
        FROM product_type pt
        LEFT JOIN product p ON pt.product_type_id = p.product_type_id
        LEFT JOIN personnel per ON pt.personnel_id = per.personnel_id";

    $params = [];
    if ($start_date || $end_date || !empty($search)) {
        $query .= " WHERE 1=1";
        if ($start_date) {
            $query .= " AND pt.product_type_date >= :start_date";
            $params[':start_date'] = $start_date . ' 00:00:00';
        }
        if ($end_date) {
            $query .= " AND pt.product_type_date <= :end_date";
            $params[':end_date'] = $end_date . ' 23:59:59';
        }
        if (!empty($search)) {
            $query .= " AND (pt.product_type_name LIKE :search OR pt.product_type_id LIKE :search)";
            $params[':search'] = "%$search%";
        }
    }

    $query .= " GROUP BY pt.product_type_id, pt.product_type_name 
        ORDER BY pt.product_type_id
        LIMIT :offset, :records_per_page";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link href="../../output.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title>จัดการประเภทสินค้า</title>
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
        <div class="flex flex-col md:flex-row md:space-x-4 mb-4">
            <!-- เลือกช่วงวันที่ -->
            <div class="flex items-center space-x-2 mb-2 md:mb-0">
                <label for="start_date" class="text-sm font-medium">วันที่เริ่มต้น</label>
                <input type="date" id="start_date" name="start_date" class="border rounded px-2 py-1"
                    value="<?php echo htmlspecialchars($start_date); ?>">
                <label for="end_date" class="text-sm font-medium">ถึง</label>
                <input type="date" id="end_date" name="end_date" class="border rounded px-2 py-1"
                    value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <!-- ปุ่มดาวน์โหลด -->
            <div class="flex space-x-2">
                <button onclick="exportPDF()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex items-center text-sm md:text-base">
                    <i class="fas fa-file-pdf mr-2"></i> PDF
                </button>
                <button onclick="exportExcel()" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition flex items-center text-sm md:text-base">
                    <i class="fas fa-file-excel mr-2"></i> Excel
                </button>
            </div>
        </div>

        <!-- Table Section -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#c8d8ed] to-[#c8d8ed] p-4 border-b border-[#8f959b]">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-list mr-2"></i>รายการประเภทสินค้า
                        </h2>
                        <span class="px-3 py-1 bg-white font-bold border border-gray-500 rounded-md text-sm md:text-base"><?php echo $count; ?> รายการ</span>
                    </div>
                    <div class="w-full md:w-1/2">
                        <form action="" method="GET" class="w-full" id="searchForm">
                            <input type="hidden" name="page" value="product_type">
                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            <div class="relative">
                                <input type="text" name="search" id="searchInput"
                                    placeholder="ค้นหาประเภทสินค้า..." value="<?php echo htmlspecialchars($search); ?>"
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
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">รหัสประเภท</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">ชื่อประเภทสินค้า</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">พนักงาน</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">วันที่</th>
                                <th class="px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">จำนวนสินค้า</th>
                                <th class="w-[80px] px-4 py-3 border text-center text-gray-700 font-semibold text-sm md:text-base">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Add Form Row -->
                            <tr>
                                <form action="" method="POST">
                                    <td class="px-4 py-2 border bg-white text-center text-gray-600 text-sm md:text-base">อัตโนมัติ</td>
                                    <td class="px-4 py-2 border bg-white">
                                        <input type="text" name="typename" placeholder="ชื่อประเภทสินค้า"
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
                                    <td class="px-4 py-2 border bg-white text-center">
                                        <button type="submit"
                                            class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex justify-center items-center text-sm md:text-base">
                                            <i class="fas fa-plus mr-1"></i>เพิ่ม
                                        </button>
                                    </td>
                                </form>
                            </tr>
                            <!-- Data Rows -->
                            <?php if (count($producttypes) > 0): ?>
                                <?php foreach ($producttypes as $type): ?>
                                    <tr class="hover:bg-gray-100" data-id="<?php echo htmlspecialchars($type['product_type_id']); ?>">
                                        <td class="px-4 py-1 border text-sm md:text-base"><?php echo htmlspecialchars($type['product_type_id']); ?></td>
                                        <td class="type-name px-4 py-1 border text-sm md:text-base"><?php echo htmlspecialchars($type['product_type_name']); ?></td>
                                        <td class="type-personnel px-4 py-1 border text-sm md:text-base" data-personnel-id="<?php echo htmlspecialchars($type['personnel_id']); ?>">
                                            <?php echo htmlspecialchars($type['personnel_name']); ?>
                                        </td>
                                        <td class="type-date px-4 py-1 border text-center text-sm md:text-base" data-date="<?php echo date('Y-m-d', strtotime($type['product_type_date'])); ?>">
                                            <?php echo htmlspecialchars($type['product_type_date']); ?>
                                        </td>
                                        <td class="px-4 py-1 border text-center cursor-pointer">
                                            <a href="?page=product_typegroup&tabel=product_type&id=<?php echo $type['product_type_id']; ?>&product_type_name=<?php echo urlencode($type['product_type_name']); ?>"
                                                class="text-orange-600 text-sm md:text-base">
                                                <?php echo number_format($type['product_count']); ?> รายการ
                                            </a>
                                        </td>
                                        <td class="px-4 py-1 border text-center">
                                            <div class="flex justify-center space-x-2">
                                                <button onclick="enableEdit(this)"
                                                    class="edit-btn bg-yellow-500 text-white px-4 py-1 rounded-lg hover:bg-yellow-600 transition flex items-center text-sm md:text-base">
                                                    <i class="fas fa-edit mr-1"></i>แก้ไข
                                                </button>
                                                <a href="?page=product_type&id=<?php echo htmlspecialchars($type['product_type_id']); ?>"
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
                                    <td colspan="6" class="text-center py-4 text-gray-600 text-sm md:text-base">ไม่มีข้อมูล</td>
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
                                <label class="block text-gray-700 font-semibold mb-2 text-sm">ชื่อประเภทสินค้า</label>
                                <input type="text" name="typename" placeholder="ชื่อประเภทสินค้า"
                                    class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    required />
                            </div>
                            <div class="mb-4">
                                <label class="block text-gray-700 font-semibold mb-2 text-sm">พนักงาน</label>
                                <select name="personnel_id" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" required>
                                    <option value="">เลือกพนักงาน</option>
                                    <?php foreach ($personnels as $personnel): ?>
                                        <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                                            <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit"
                                class="w-full px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition flex items-center justify-center text-sm">
                                <i class="fas fa-plus mr-1"></i>เพิ่ม
                            </button>
                        </form>
                    </div>
                    <!-- Data Cards -->
                    <?php if (count($producttypes) > 0): ?>
                        <?php foreach ($producttypes as $type): ?>
                            <div class="bg-white p-4 mb-4 rounded-lg shadow" data-id="<?php echo htmlspecialchars($type['product_type_id']); ?>">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">รหัสประเภท:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($type['product_type_id']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm type-name">ชื่อประเภท:</span>
                                    <span class="text-sm"><?php echo htmlspecialchars($type['product_type_name']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm type-personnel" data-personnel-id="<?php echo htmlspecialchars($type['personnel_id']); ?>">
                                        พนักงาน:
                                    </span>
                                    <span class="text-sm"><?php echo htmlspecialchars($type['personnel_name']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm type-date" data-date="<?php echo date('Y-m-d', strtotime($type['product_type_date'])); ?>">
                                        วันที่:
                                    </span>
                                    <span class="text-sm"><?php echo htmlspecialchars($type['product_type_date']); ?></span>
                                </div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold text-sm">จำนวนสินค้า:</span>
                                    <a href="?page=product_typegroup&tabel=product_type&id=<?php echo $type['product_type_id']; ?>&product_type_name=<?php echo urlencode($type['product_type_name']); ?>"
                                        class="text-orange-600 hover:underline text-sm">
                                        <?php echo number_format($type['product_count']); ?> รายการ
                                    </a>
                                </div>
                                <div class="flex justify-center space-x-2 mt-4">
                                    <button onclick="enableEditMobile(this)"
                                        class="edit-btn bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition flex items-center text-sm">
                                        <i class="fas fa-edit mr-1"></i>แก้ไข
                                    </button>
                                    <a href="?page=product_type&id=<?php echo htmlspecialchars($type['product_type_id']); ?>"
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
                    <a href="?page=product_type&page_num=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        « ก่อนหน้า
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="?page=product_type&page_num=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        1
                    </a>
                    <?php if ($start_page > 2): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=product_type&page_num=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-3 py-2 rounded-md transition-colors text-sm md:text-base <?= $i == $page ? 'bg-gray-800 text-white' : 'bg-blue-100 text-black hover:bg-gray-300' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-3 py-2 text-gray-500 text-sm md:text-base">...</span>
                    <?php endif; ?>
                    <a href="?page=product_type&page_num=<?= $total_pages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
                        class="px-3 py-2 bg-blue-100 text-black rounded-md hover:bg-gray-300 transition-colors text-sm md:text-base">
                        <?= $total_pages ?>
                    </a>
                <?php endif; ?>

                <!-- Next Button -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=product_type&page_num=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($start_date) ? '&start_date=' . urlencode($start_date) : '' ?><?= !empty($end_date) ? '&end_date=' . urlencode($end_date) : '' ?>"
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
        function checkEmptySearch(input) {
            if (input.value.trim() === '') {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('page', 'product_type');
                urlParams.delete('search');
                window.location.search = urlParams.toString();
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

            if (urlParams.has('search')) {
                document.getElementById('searchInput').focus();
            }

            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const urlParams = new URLSearchParams(window.location.search);
                    urlParams.set('page', 'product_type');
                    urlParams.delete('search');
                    window.location.search = urlParams.toString();
                }
            });

            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    localStorage.setItem('searchPreserve', this.value);
                }
            });

            const preserved = localStorage.getItem('searchPreserve');
            if (preserved) {
                const input = document.getElementById('searchInput');
                input.value = preserved;
                input.focus();
                input.selectionStart = input.selectionEnd = preserved.length;
                localStorage.removeItem('searchPreserve');
            }
        });

        function enableEdit(button) {
            const row = button.closest('tr');
            const typeNameCell = row.querySelector('.type-name');
            const typePersonnelCell = row.querySelector('.type-personnel');
            const originalName = typeNameCell.textContent.trim();
            const originalPersonnelId = typePersonnelCell.dataset.personnelId;

            typeNameCell.innerHTML = `<input type="text" class="edit-name w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base" value="${originalName}" />`;
            typePersonnelCell.innerHTML = `
                <select class="edit-personnel w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm md:text-base">
                    <option value="">เลือกพนักงาน</option>
                    <?php foreach ($personnels as $personnel): ?>
                        <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>" 
                            ${originalPersonnelId === '<?php echo $personnel['personnel_id']; ?>' ? 'selected' : ''}>
                            <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>`;
            button.classList.add('hidden');
            row.querySelector('.delete-btn').classList.add('hidden');
            row.querySelector('.save-btn').classList.remove('hidden');
        }

        function enableEditMobile(button) {
            const card = button.closest('div[data-id]');
            const typeNameSpan = card.querySelector('.type-name + span');
            const typePersonnelSpan = card.querySelector('.type-personnel + span');
            const originalName = typeNameSpan.textContent.trim();
            const originalPersonnelId = typePersonnelSpan.dataset.personnelId;

            typeNameSpan.innerHTML = `<input type="text" class="edit-name w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" value="${originalName}" />`;
            typePersonnelSpan.innerHTML = `
                <select class="edit-personnel w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value="">เลือกพนักงาน</option>
                    <?php foreach ($personnels as $personnel): ?>
                        <option value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>" 
                            ${originalPersonnelId === '<?php echo $personnel['personnel_id']; ?>' ? 'selected' : ''}>
                            <?php echo htmlspecialchars($personnel['personnel_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>`;
            button.classList.add('hidden');
            card.querySelector('.delete-btn').classList.add('hidden');
            card.querySelector('.save-btn').classList.remove('hidden');
        }

        function saveEdit(button) {
            const row = button.closest('tr');
            const typeId = row.getAttribute('data-id');
            const newName = row.querySelector('.edit-name').value.trim();
            const newPersonnelId = row.querySelector('.edit-personnel').value;
            const typeNameCell = row.querySelector('.type-name');
            const typePersonnelCell = row.querySelector('.type-personnel');

            if (!newName) {
                alert('กรุณากรอกชื่อประเภทสินค้า');
                return;
            }
            if (!newPersonnelId) {
                alert('กรุณาเลือกพนักงาน');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${typeId}&update_typename=${encodeURIComponent(newName)}&personnel_id=${newPersonnelId}`
                })
                .then(response => {
                    if (response.ok) {
                        typeNameCell.textContent = newName;
                        typePersonnelCell.textContent = row.querySelector('.edit-personnel option:checked').text;
                        typePersonnelCell.dataset.personnelId = newPersonnelId;
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
            const typeId = card.getAttribute('data-id');
            const newName = card.querySelector('.edit-name').value.trim();
            const newPersonnelId = card.querySelector('.edit-personnel').value;
            const typeNameSpan = card.querySelector('.type-name + span');
            const typePersonnelSpan = card.querySelector('.type-personnel + span');

            if (!newName) {
                alert('กรุณากรอกชื่อประเภทสินค้า');
                return;
            }
            if (!newPersonnelId) {
                alert('กรุณาเลือกพนักงาน');
                return;
            }

            fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${typeId}&update_typename=${encodeURIComponent(newName)}&personnel_id=${newPersonnelId}`
                })
                .then(response => {
                    if (response.ok) {
                        typeNameSpan.textContent = newName;
                        typePersonnelSpan.textContent = card.querySelector('.edit-personnel option:checked').text;
                        typePersonnelSpan.dataset.personnelId = newPersonnelId;
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

        function exportPDF() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/product/report/export_product_type_pdf.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        function exportExcel() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            let url = '/page/product/report/export_product_type_excel.php';
            if (startDate) url += `?start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `${startDate ? '&' : '?'}end_date=${encodeURIComponent(endDate)}`;
            window.open(url, '_blank');
        }

        document.getElementById('start_date').addEventListener('change', updateDateFilter);
        document.getElementById('end_date').addEventListener('change', updateDateFilter);

        function updateDateFilter() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', 'product_type');

            if (startDate) {
                urlParams.set('start_date', startDate);
            } else {
                urlParams.delete('start_date');
            }

            if (endDate) {
                urlParams.set('end_date', endDate);
            } else {
                urlParams.delete('end_date');
            }

            window.location.search = urlParams.toString();
        }
    </script>
</body>

</html>