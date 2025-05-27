<?php
// เรียกไฟล์ที่ใช้ในการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . "../../../../config/database_server.php";
session_start();
date_default_timezone_set('Asia/Bangkok');

// Create CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check session timeout
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'lot_detail', 'tabel' => 'lot', 'id' => $_GET['id']] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// ตรวจสอบว่ามีการระบุประเภท (tabel) และ ID (id) มาหรือไม่ ถ้าไม่เจอให้หยุดการทำงาน
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    header("Location: /?page=lot_list&error=" . urlencode("ระบุประเภทหรือ ID ไม่ถูกต้อง"));
    exit;
}

// ดึงค่าตัวแปรจาก URL เพื่อระบุประเภทและ ID
$tabel = $_GET['tabel'];
$id = $_GET['id'];
$is_update = isset($_GET['is_update']) ? $_GET['is_update'] : false;

// ตรวจสอบประเภทที่กำหนดมาและทำการ query เพื่อดึงข้อมูลสินค้า
switch ($tabel) {
    case 'lot':
        $stmt = $pdo->prepare("
            SELECT
                lot_status.lot_status_id, 
                lot_status.lot_status_name, 
                personnel.personnel_id, 
                personnel.personnel_name, 
                warehouse.warehouse_name, 
                warehouse.warehouse_id,
                lot.lot_id, 
                lot.lot_detail, 
                lot.lot_date, 
                lot.lot_status_id, 
                lot.warehouse_id,
                lot.personnel_id
            FROM
                lot
                LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
                LEFT JOIN personnel ON lot.personnel_id = personnel.personnel_id
                LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
            WHERE lot.lot_id = ?
        ");
        break;
    default:
        header("Location: /?page=lot_list&error=" . urlencode("ระบุประเภทไม่ถูกต้อง"));
        exit;
}

// ดำเนินการ query ด้วย lot_id ที่ระบุ
$stmt->execute([$id]);
$lot = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lot) {
    header("Location: /?page=lot_list&error=" . urlencode("ไม่พบข้อมูล"));
    exit;
}

// ดึงข้อมูลสำหรับ dropdown
try {
    $querylot = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $status_lot = $pdo->query($querylot);

    $querywarehouse = "SELECT warehouse_id, warehouse_name FROM warehouse";
    $warehouse_lot = $pdo->query($querywarehouse);

    $querypersonnel = "SELECT personnel_id, personnel_name FROM personnel";
    $personnel_lot = $pdo->query($querypersonnel);
} catch (PDOException $e) {
    error_log("Fetch dropdown data error: " . $e->getMessage());
    header("Location: /?page=lot_list&error=" . urlencode("Database error: " . htmlspecialchars($e->getMessage())));
    exit;
}

// Handle POST request for updating lot
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: /?page=lot_detail&tabel=lot&id=$id&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $lot_id = filter_var($_POST['lot_id'], FILTER_VALIDATE_INT);
    $lot_detail = trim($_POST['lot_detail']);
    $lot_date = trim($_POST['lot_date']);
    $lot_status_id = filter_var($_POST['lot_status_id'], FILTER_VALIDATE_INT);
    $warehouse_id = filter_var($_POST['warehouse_id'], FILTER_VALIDATE_INT);
    $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);

    // Validate input
    $errors = [];
    if (strlen($lot_detail) > 1000) {
        $errors[] = 'รายละเอียดล็อตต้องมีความยาวไม่เกิน 1000 ตัวอักษร';
    }
    if (!$lot_status_id) {
        $errors[] = 'กรุณาเลือกสถานะล็อต';
    }
    if (!$warehouse_id) {
        $errors[] = 'กรุณาเลือกคลังปลายทาง';
    }
    if (!$personnel_id) {
        $errors[] = 'กรุณาเลือกพนักงาน';
    }
    if (!$lot_id) {
        $errors[] = 'รหัสล็อตไม่ถูกต้อง';
    }

    if (!empty($errors)) {
        header("Location: /?page=lot_detail&tabel=lot&id=$id&error=" . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE lot 
            SET lot_detail = ?, lot_date = ?, lot_status_id = ?, warehouse_id = ?, personnel_id = ?
            WHERE lot_id = ?
        ");
        $stmt->execute([$lot_detail, $lot_date, $lot_status_id, $warehouse_id, $personnel_id, $lot_id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header("Location: /?page=lot_detailmove&tabel=lot&id=$id&error=" . urlencode("ไม่พบข้อมูลล็อตที่ต้องการแก้ไข"));
            exit;
        }
        $pdo->commit();
        header("Location: /?page=lot_detailmove&tabel=lot&id=$lot_id&success=" . urlencode("แก้ไขล็อตสำเร็จ"));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update lot error: " . $e->getMessage());
        header("Location: /?page=lot_detailmove&tabel=lot&id=$id&error=" . urlencode("Database error: " . htmlspecialchars($e->getMessage())));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดล็อตสินค้า</title>
    <link rel="stylesheet" href="../../output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body class="bg-gray-50">
    <div class="container mx-auto xl:max-w-4xl p-4">
        <!-- Notification -->
        <div id="notification"
            class="fixed top-5 right-5 w-80 bg-white border border-gray-200 rounded-md shadow-lg z-50 overflow-hidden hidden">
            <div id="notification-header" class="px-4 py-2 font-bold text-white"></div>
            <div class="px-4 py-3" id="notification-message"></div>
        </div>

        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="bg-gradient-to-b from-[#d7e1ec] to-[#e6effb] p-4 border-b border-[#cfd5dc]">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-box mr-2"></i>
                    <?php echo $is_update ? 'แก้ไขล็อตสินค้า' : 'รายละเอียดย้ายสินค้า'; ?>
                </h2>
            </div>

            <!-- Form -->
            <div class="p-6">
                <form id="lotForm" class="space-y-4" action="" method="POST">
                    <input type="hidden" name="lot_id" value="<?php echo htmlspecialchars($lot['lot_id']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">รหัสล็อต</label>
                            <input type="text" name="lot_id" value="<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-100"
                                readonly>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">รายละเอียดล็อต</label>
                            <?php if ($is_update): ?>
                                <textarea id="lotDetail" name="lot_detail"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($lot['lot_detail']); ?></textarea>
                                <span id="detailError" class="text-red-500 text-xs mt-1 hidden"></span>
                            <?php else: ?>
                                <p class="px-3 py-2 border border-gray-300 rounded-md bg-gray-100"><?php echo htmlspecialchars($lot['lot_detail']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">วันที่ล็อต</label>
                            <?php if ($is_update): ?>
                                <input type="date" id="lotDate" name="lot_date"
                                    value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($lot['lot_date']))); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <span id="dateError" class="text-red-500 text-xs mt-1 hidden"></span>
                            <?php else: ?>
                                <p class="px-3 py-2 border border-gray-300 rounded-md bg-gray-100"><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($lot['lot_date']))); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">สถานะล็อต</label>
                            <?php if ($is_update): ?>
                                <select id="lotStatusId" name="lot_status_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required>
                                    <option value="">เลือกสถานะล็อต</option>
                                    <?php while ($lot_status_result = $status_lot->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($lot_status_result['lot_status_id']); ?>"
                                            <?php echo $lot_status_result['lot_status_id'] == $lot['lot_status_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lot_status_result['lot_status_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <span id="statusError" class="text-red-500 text-xs mt-1 hidden"></span>
                            <?php else: ?>
                                <p class="px-3 py-2 border border-gray-300 rounded-md bg-gray-100"><?php echo htmlspecialchars($lot['lot_status_name']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">คลังสินค้า</label>
                            <?php if ($is_update): ?>
                                <select id="warehouseId" name="warehouse_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required>
                                    <option value="">เลือกคลัง</option>
                                    <?php while ($warehouse_result = $warehouse_lot->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($warehouse_result['warehouse_id']); ?>"
                                            <?php echo $warehouse_result['warehouse_id'] == $lot['warehouse_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($warehouse_result['warehouse_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <span id="warehouseError" class="text-red-500 text-xs mt-1 hidden"></span>
                            <?php else: ?>
                                <p class="px-3 py-2 border border-gray-300 rounded-md bg-gray-100"><?php echo htmlspecialchars($lot['warehouse_name']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">พนักงาน</label>
                            <?php if ($is_update): ?>
                                <select id="personnelId" name="personnel_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    required>
                                    <option value="">เลือกพนักงาน</option>
                                    <?php while ($personnel_result = $personnel_lot->fetch(PDO::FETCH_ASSOC)): ?>
                                        <option value="<?php echo htmlspecialchars($personnel_result['personnel_id']); ?>"
                                            <?php echo $personnel_result['personnel_id'] == $lot['personnel_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($personnel_result['personnel_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <span id="personnelError" class="text-red-500 text-xs mt-1 hidden"></span>
                            <?php else: ?>
                                <p class="px-3 py-2 border border-gray-300 rounded-md bg-gray-100"><?php echo htmlspecialchars($lot['personnel_name']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Action Buttons -->
                    <div class="flex justify-end gap-2 mt-6">
                        <?php if ($is_update): ?>
                            <a href="/?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
                                <i class="fas fa-times mr-1"></i> ยกเลิก
                            </a>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">
                                <i class="fas fa-save mr-1"></i> บันทึก
                            </button>
                        <?php else: ?>
                            <a href="/?page=lot_warehouseinout"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">
                                <i class="fas fa-arrow-left mr-1"></i> ย้อนกลับ
                            </a>
                            <?php if ($lot['lot_status_id'] != 2): ?>
                                <a href="/?page=lot_detailmove&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>&is_update=1"
                                    class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition">
                                    <i class="fas fa-edit mr-1"></i> แก้ไข
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);

            // Show notification if success or error is present
            if (urlParams.has('success')) {
                showNotification(urlParams.get('success'), 'bg-green-500');
            } else if (urlParams.has('error')) {
                showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
            }

            // Focus on first input when in update mode
            <?php if ($is_update): ?>
                document.getElementById('lotDetail').focus();
            <?php endif; ?>

            // Notification function
            function showNotification(message, className) {
                const notification = document.getElementById('notification');
                const header = document.getElementById('notification-header');
                const messageDiv = document.getElementById('notification-message');
                header.className = `px-4 py-2 font-bold text-white ${className}`;
                header.textContent = 'การแจ้งเตือน';
                messageDiv.textContent = message;
                notification.classList.remove('hidden');
                setTimeout(() => {
                    notification.classList.add('hidden');
                }, 5000);
            }

            // Form validation
            document.getElementById('lotForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const fields = [
                    {
                        id: 'lotDetail',
                        errorId: 'detailError',
                        label: 'รายละเอียดล็อต',
                        maxLength: 1000,
                        required: false
                    },
                    {
                        id: 'lotDate',
                        errorId: 'dateError',
                        label: 'วันที่ล็อต',
                        maxLength: Infinity,
                        required: true
                    },
                    {
                        id: 'lotStatusId',
                        errorId: 'statusError',
                        label: 'สถานะล็อต',
                        maxLength: Infinity,
                        required: true
                    },
                    {
                        id: 'warehouseId',
                        errorId: 'warehouseError',
                        label: 'คลังปลายทาง',
                        maxLength: Infinity,
                        required: true
                    },
                    {
                        id: 'personnelId',
                        errorId: 'personnelError',
                        label: 'พนักงาน',
                        maxLength: Infinity,
                        required: true
                    }
                ];

                let hasError = false;
                fields.forEach(field => {
                    const input = document.getElementById(field.id);
                    const error = document.getElementById(field.errorId);
                    if (!input.value.trim() && field.required) {
                        error.textContent = `กรุณากรอก${field.label}`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (input.value.length > field.maxLength) {
                        error.textContent = `${field.label}ยาวเกิน ${field.maxLength} ตัวอักษร`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else {
                        error.classList.add('hidden');
                    }
                });

                if (!hasError) {
                    this.submit();
                }
            });
        });
    </script>
</body>

</html>