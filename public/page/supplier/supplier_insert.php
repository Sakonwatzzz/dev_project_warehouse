<?php
ob_start();
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection(); 

// สร้าง CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ดึงรายชื่อพนักงาน
try {
    $queryPersonnels = "SELECT personnel_id, personnel_name FROM personnel";
    $personnelResults = $pdo->query($queryPersonnels);
} catch (PDOException $e) {
    die("Personnel fetch error: " . htmlspecialchars($e->getMessage()));
}

// Handle POST request for inserting a new supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('Location: ?page=supplier_add&error=' . urlencode('Invalid CSRF token'));
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $detail = trim($_POST['detail'] ?? '');
    $createdate = date('Y-m-d H:i:s');
    $personnel = filter_var($_POST['personnel'] ?? 0, FILTER_VALIDATE_INT);

    // ตรวจสอบข้อมูล
    $errors = [];
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อผู้ส่งมอบต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (empty($address) || strlen($address) > 500) {
        $errors[] = 'ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร';
    }
    if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว';
    }
    if (!$personnel) {
        $errors[] = 'กรุณาเลือกพนักงาน';
    }

    if (!empty($errors)) {
        header('Location: ?page=supplier_add&error=' . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO supplier (supplier_name, supplier_address, supplier_phone, supplier_detail, supplier_create_date, personnel_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $address, $phone, $detail, $createdate, $personnel]);

        $supplier_id = $pdo->lastInsertId();
        header("Location: /?page=supplier_detail&tabel=supplier&id=$supplier_id&success=1");
        exit;
    } catch (PDOException $e) {
        header('Location: ?page=supplier_add&error=' . urlencode('Database error: ' . htmlspecialchars($e->getMessage())));
        exit;
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../output.css" rel="stylesheet">
    <link rel="stylesheet" href="../../style/page_insert.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>เพิ่มผู้ส่งมอบ | ระบบจัดการข้อมูล</title>
</head>

<body class="font-sans bg-gray-50 text-gray-800">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-indigo-900">เพิ่มผู้ส่งมอบ</h1>
            <p class="text-gray-600">กรอกข้อมูลเพื่อเพิ่มผู้ส่งมอบใหม่ลงในระบบ</p>
        </div>

        <!-- Form Section -->
        <div class="bg-white rounded-lg shadow-lg border border-gray-100 p-6">
            <form id="supplierForm" class="space-y-4" action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <!-- ชื่อผู้ส่งมอบ -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="supplier-name" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ชื่อผู้ส่งมอบ:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="name" id="supplier-name" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="nameError" class="text-rose-500 text-xs mt-1 hidden">ชื่อผู้ส่งมอบต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร</span>
                    </div>
                </div>

                <!-- ที่อยู่ -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="supplier-address" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">ที่อยู่:</label>
                    <div class="w-full sm:w-3/4">
                        <textarea name="address" id="supplier-address" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none" rows="3"></textarea>
                        <span id="addressError" class="text-rose-500 text-xs mt-1 hidden">ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร</span>
                    </div>
                </div>

                <!-- เบอร์โทร -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="supplier-phone" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">เบอร์โทร:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="phone" id="supplier-phone" maxlength="10" pattern="[0-9]{10}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <span id="phoneError" class="text-rose-500 text-xs mt-1 hidden">เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว</span>
                    </div>
                </div>

                <!-- รายละเอียด -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="supplier-detail" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">รายละเอียด:</label>
                    <div class="w-full sm:w-3/4">
                        <textarea name="detail" id="supplier-detail"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none" rows="3"></textarea>
                        <span id="detailError" class="text-rose-500 text-xs mt-1 hidden">รายละเอียดต้องมีความยาวไม่เกิน 500 ตัวอักษร</span>
                    </div>
                </div>

                <!-- วันที่และเวลา -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="supplier-createdate" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">วันที่และเวลา:</label>
                    <div class="w-full sm:w-3/4">
                        <input type="text" name="createdate" id="supplier-createdate" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly
                            class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-200 text-gray-600 cursor-not-allowed">
                    </div>
                </div>

                <!-- พนักงาน -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label for="supplier-personnel" class="w-full sm:w-1/4 text-sm font-medium text-gray-700">
                        <a href="?page=personnel_list" class="text-indigo-600 hover:underline">พนักงาน:</a>
                    </label>
                    <div class="w-full sm:w-3/4">
                        <?php if ($personnelResults->rowCount() > 0): ?>
                            <select name="personnel" id="supplier-personnel" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-indigo-500" required>
                                <option value="" disabled selected>เลือกพนักงาน</option>
                                <?php while ($person = $personnelResults->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo htmlspecialchars($person['personnel_id']); ?>">
                                        <?php echo htmlspecialchars($person['personnel_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <span id="personnelError" class="text-rose-500 text-xs mt-1 hidden">กรุณาเลือกพนักงาน</span>
                        <?php else: ?>
                            <span class="text-gray-600">ไม่พบข้อมูลพนักงาน</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ปุ่ม -->
                <div class="flex justify-end gap-4 mt-6">
                    <button type="button" onclick="showCancelModal()"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        ยกเลิก
                    </button>
                    <button type="submit"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>

        <!-- Cancel Confirmation Modal -->
        <div id="cancelModal"
            class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 opacity-0 invisible transition-all duration-300 backdrop-blur-sm">
            <div class="bg-white rounded-lg w-full max-w-md p-6 relative transform scale-95 transition-all duration-300">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">ยืนยันการยกเลิก</h3>
                    <p class="text-sm text-gray-500 mb-6">คุณแน่ใจหรือไม่ว่าต้องการยกเลิก? ข้อมูลที่กรอกจะไม่ถูกบันทึก</p>
                    <div class="flex justify-center gap-3">
                        <button type="button" onclick="closeCancelModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            อยู่ต่อ
                        </button>
                        <a href="/?page=supplier_list"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                            ยกเลิก
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Notification -->
        <div id="notification"
            class="fixed top-4 right-4 max-w-sm w-full bg-white rounded-lg shadow-lg border-l-4 transform translate-x-full transition-transform duration-300 z-50 flex items-center">
            <div id="notification-header" class="flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg">
                <i id="notificationIcon" class="fas fa-check-circle text-2xl"></i>
            </div>
            <div class="flex-1 ml-4 pr-4 py-4">
                <p id="notificationTitle" class="text-sm font-medium text-gray-900"></p>
                <p id="notificationMessage" class="mt-1 text-sm text-gray-500"></p>
            </div>
            <button onclick="hideNotification()" class="mr-4 text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <script>
        function showCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.classList.remove('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
            modal.querySelector('.transform').classList.add('scale-100');
        }

        function closeCancelModal() {
            const modal = document.getElementById('cancelModal');
            modal.classList.add('invisible', 'opacity-0');
            modal.querySelector('.transform').classList.remove('scale-100');
            modal.querySelector('.transform').classList.add('scale-95');
        }

        function showNotification(message, headerClass) {
            const notification = document.getElementById('notification');
            const header = document.getElementById('notification-header');
            const title = document.getElementById('notificationTitle');
            const body = document.getElementById('notificationMessage');
            const icon = document.getElementById('notificationIcon');

            header.className = `flex-shrink-0 flex items-center justify-center w-16 h-16 text-white rounded-l-lg ${headerClass}`;
            title.textContent = 'แจ้งเตือน';
            body.textContent = message;
            icon.className = `fas ${headerClass.includes('bg-green') ? 'fa-check-circle' : 'fa-exclamation-circle'} text-2xl`;
            notification.classList.remove('translate-x-full');

            setTimeout(() => {
                hideNotification();
            }, 5000);
        }

        function hideNotification() {
            const notification = document.getElementById('notification');
            notification.classList.add('translate-x-full');
        }

        function updateDateTime() {
            const dateInput = document.getElementById('supplier-createdate');
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            dateInput.value = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }

        document.getElementById('supplierForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('supplier-name').value.trim();
            const address = document.getElementById('supplier-address').value.trim();
            const phone = document.getElementById('supplier-phone').value.trim();
            const detail = document.getElementById('supplier-detail').value.trim();
            const personnel = document.getElementById('supplier-personnel').value;
            const nameError = document.getElementById('nameError');
            const addressError = document.getElementById('addressError');
            const phoneError = document.getElementById('phoneError');
            const detailError = document.getElementById('detailError');
            const personnelError = document.getElementById('personnelError');

            let hasError = false;

            if (!name || name.length > 255) {
                nameError.textContent = 'ชื่อผู้ส่งมอบต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
                nameError.classList.remove('hidden');
                hasError = true;
            } else {
                nameError.classList.add('hidden');
            }

            if (!address || address.length > 500) {
                addressError.textContent = 'ที่อยู่ต้องไม่ว่างและมีความยาวไม่เกิน 500 ตัวอักษร';
                addressError.classList.remove('hidden');
                hasError = true;
            } else {
                addressError.classList.add('hidden');
            }

            if (phone && !/^[0-9]{10}$/.test(phone)) {
                phoneError.textContent = 'เบอร์โทรศัพท์ต้องเป็นตัวเลข 10 ตัว';
                phoneError.classList.remove('hidden');
                hasError = true;
            } else {
                phoneError.classList.add('hidden');
            }

            if (detail && detail.length > 500) {
                detailError.textContent = 'รายละเอียดต้องมีความยาวไม่เกิน 500 ตัวอักษร';
                detailError.classList.remove('hidden');
                hasError = true;
            } else {
                detailError.classList.add('hidden');
            }

            if (!personnel) {
                personnelError.textContent = 'กรุณาเลือกพนักงาน';
                personnelError.classList.remove('hidden');
                hasError = true;
            } else {
                personnelError.classList.add('hidden');
            }

            if (!hasError) {
                this.submit();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            document.getElementById('supplier-name').focus();
            updateDateTime();
            setInterval(updateDateTime, 1000);

            if (urlParams.has('error')) {
                showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-500');
            } else if (urlParams.has('success') && urlParams.get('success') === '1') {
                showNotification('เพิ่มผู้ส่งมอบสำเร็จ', 'bg-green-500');
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                showCancelModal();
            }
        });

        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCancelModal();
            }
        });
    </script>
</body>

</html>