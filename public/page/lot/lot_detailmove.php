<?php
// เรียกไฟล์ที่ใช้ในการเชื่อมต่อฐานข้อมูล
require_once __DIR__ . "../../../../config/database_server.php";
$pdo = Database::getInstance()->getConnection(); 
date_default_timezone_set('Asia/Bangkok');

$accessToken = getToken('access_token');
if (!$accessToken || !validateToken($accessToken)) {
    $refreshToken = getToken('refresh_token');
    if ($refreshToken && validateToken($refreshToken)) {
        $accessToken = refreshToken($refreshToken);
    } else {
        header('Location: login.php');
        exit();
    }
}

// Session Management
if (!isset($_SESSION)) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'lot_detail', 'tabel' => 'lot', 'id' => $_GET['id'] ?? ''] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Validate required GET parameters
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    header("Location: /?page=lot_list&error=" . urlencode("ระบุประเภทหรือ ID ไม่ถูกต้อง"));
    exit;
}

// Function to validate form data
function validateLotData($lot_detail, $lot_date, $lot_status_id, $warehouse_id, $personnel_id)
{
    $errors = [];
    if (strlen($lot_detail) > 1000) {
        $errors[] = 'รายละเอียดล็อตต้องมีความยาวไม่เกิน 1000 ตัวอักษร';
    }
    if (empty($lot_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lot_date)) {
        $errors[] = 'วันที่ล็อตต้องอยู่ในรูปแบบที่ถูกต้อง';
    }
    if (empty($lot_status_id) || !is_numeric($lot_status_id)) {
        $errors[] = 'ต้องเลือกสถานะล็อต';
    }
    if (empty($warehouse_id) || !is_numeric($warehouse_id)) {
        $errors[] = 'ต้องเลือกคลังปลายทาง';
    }
    if (empty($personnel_id) || !is_numeric($personnel_id)) {
        $errors[] = 'ต้องเลือกพนักงาน';
    }
    return $errors;
}

// Handle POST request for updating lot
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: /?page=lot_detail&tabel=lot&id={$_GET['id']}&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $lot_id = filter_var($_POST['lot_id'], FILTER_VALIDATE_INT);
    $lot_detail = trim($_POST['lot_detail']);
    $lot_date = trim($_POST['lot_date']);
    $lot_status_id = filter_var($_POST['lot_status_id'], FILTER_VALIDATE_INT);
    $warehouse_id = filter_var($_POST['warehouse_id'], FILTER_VALIDATE_INT);
    $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);

    $errors = validateLotData($lot_detail, $lot_date, $lot_status_id, $warehouse_id, $personnel_id);
    if (!empty($errors)) {
        header("Location: /?page=lot_detail&tabel=lot&id={$_GET['id']}&is_update=1&error=" . urlencode(implode(', ', $errors)));
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
            header("Location: /?page=lot_detail&tabel=lot&id={$_GET['id']}&error=" . urlencode("ไม่พบข้อมูลล็อตที่ต้องการแก้ไข"));
            exit;
        }
        $pdo->commit();
        header("Location: /?page=lot_detail&tabel=lot&id={$_GET['id']}&success=" . urlencode("แก้ไขล็อตสำเร็จ"));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update lot error: " . $e->getMessage());
        header("Location: /?page=lot_detail&tabel=lot&id={$_GET['id']}&error=" . urlencode("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Retrieve GET parameters
$tabel = $_GET['tabel'];
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$is_update = isset($_GET['is_update']) ? (bool)$_GET['is_update'] : false;

if (!$id) {
    header("Location: /?page=lot_list&error=" . urlencode("ID ไม่ถูกต้อง"));
    exit;
}

// Prepare SQL query based on table type
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

try {
    $stmt->execute([$id]);
    $lot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lot) {
        header("Location: /?page=lot_list&error=" . urlencode("ไม่พบข้อมูลล็อต"));
        exit;
    }
} catch (PDOException $e) {
    error_log("Fetch lot error: " . $e->getMessage());
    header("Location: /?page=lot_list&error=" . urlencode("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage())));
    exit;
}

// Fetch dropdown data
try {
    $querylot = "SELECT lot_status_id, lot_status_name FROM lot_status ORDER BY lot_status_name";
    $status_lot = $pdo->query($querylot);

    $querywarehouse = "SELECT warehouse_id, warehouse_name FROM warehouse ORDER BY warehouse_name";
    $warehouse_lot = $pdo->query($querywarehouse);

    $querypersonnel = "SELECT personnel_id, personnel_name FROM personnel ORDER BY personnel_name";
    $personnel_lot = $pdo->query($querypersonnel);
} catch (PDOException $e) {
    error_log("Fetch dropdown data error: " . $e->getMessage());
    header("Location: /?page=lot_detail&tabel=lot&id={$_GET['id']}&error=" . urlencode("เกิดข้อผิดพลาดในการดึงข้อมูลตัวเลือก"));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_update ? 'แก้ไขล็อตสินค้า' : 'รายละเอียดล็อตสินค้า'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../style/page_list.css">
    <style>
        .form-field {
            transition: border-color 0.2s ease-in-out;
        }

        .form-field:focus {
            border-color: #2563eb;
            outline: none;
            right: 2px;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto px-4 py-8 xl:max-w-4xl">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between mb-6">
            <h2 class="text-2xl font-bold text-blue-800 mb-4 sm:mb-0">
                <i class="fas fa-box mr-2"></i>
                <?php echo $is_update ? 'แก้ไขล็อตสินค้า' : 'รายละเอียดล็อตสินค้า'; ?>
            </h2>
            <!-- <a href="/?page=lot_list" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-xl shadow-md hover:from-blue-600 hover:to-blue-800 transition duration-300 ease-in-out">
                <i class="fas fa-arrow-left"></i>
                <span>ย้อนกลับ</span>
            </a> -->
        </div>

        <!-- Notification -->
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

        <!-- Lot Details Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 border-b-2 border-gray-200 pb-2">
                ข้อมูลล็อตสินค้า
            </h3>
            <form id="lotForm" action="" method="POST" class="space-y-4">
                <input type="hidden" name="lot_id" value="<?php echo htmlspecialchars($lot['lot_id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <?php
                $fields = [
                    ['label' => 'รหัสล็อต', 'name' => 'lot_id', 'value' => $lot['lot_id'], 'readonly' => true, 'id' => 'lot-id', 'type' => 'text'],
                    ['label' => 'รายละเอียดล็อต', 'name' => 'lot_detail', 'value' => $lot['lot_detail'], 'id' => 'lot-detail', 'errorId' => 'detailError', 'type' => 'textarea'],
                    ['label' => 'วันที่ล็อต', 'name' => 'lot_date', 'value' => date('Y-m-d', strtotime($lot['lot_date'])), 'id' => 'lot-date', 'errorId' => 'dateError', 'type' => 'date'],
                ];
                foreach ($fields as $field): ?>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                        <div class="w-full sm:w-8/12">
                            <?php if ($is_update && !($field['readonly'] ?? false)): ?>
                                <?php if ($field['type'] === 'textarea'): ?>
                                    <textarea id="<?php echo $field['id']; ?>"
                                        name="<?php echo $field['name']; ?>"
                                        class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                        aria-describedby="<?php echo $field['errorId'] ?? ''; ?>"><?php echo htmlspecialchars($field['value']); ?></textarea>
                                <?php else: ?>
                                    <input type="<?php echo $field['type'] ?? 'text'; ?>"
                                        id="<?php echo $field['id']; ?>"
                                        name="<?php echo $field['name']; ?>"
                                        value="<?php echo htmlspecialchars($field['value']); ?>"
                                        class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                        aria-describedby="<?php echo $field['errorId'] ?? ''; ?>">
                                <?php endif; ?>
                                <span id="<?php echo $field['errorId'] ?? ''; ?>" class="text-red-500 text-xs mt-1 hidden"></span>
                            <?php elseif ($is_update && ($field['readonly'] ?? false)): ?>
                                <input type="text"
                                    id="<?php echo $field['id']; ?>"
                                    name="<?php echo $field['name']; ?>"
                                    value="<?php echo htmlspecialchars($field['value']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"
                                    readonly>
                            <?php else: ?>
                                <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($field['type'] === 'date' ? date('d/m/Y', strtotime($field['value'])) : $field['value']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Lot Status -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700">สถานะล็อต:</label>
                    <div class="w-full sm:w-8/12">
                        <?php if ($is_update): ?>
                            <select id="lot-status" name="lot_status_id" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="statusError">
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
                            <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($lot['lot_status_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Warehouse -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700">คลังสินค้า:</label>
                    <div class="w-full sm:w-8/12">
                        <?php if ($is_update): ?>
                            <select id="warehouse-id" name="warehouse_id" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="warehouseError">
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
                            <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($lot['warehouse_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Personnel -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700">พนักงาน:</label>
                    <div class="w-full sm:w-8/12">
                        <?php if ($is_update): ?>
                            <select id="personnel-id" name="personnel_id" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="personnelError">
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
                            <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($lot['personnel_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-4 mt-6">
                    <?php if ($is_update): ?>
                        <a href="/?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                            <i class="fas fa-times mr-2"></i> ยกเลิก
                        </a>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition">
                            <i class="fas fa-save mr-2"></i> บันทึก
                        </button>
                    <?php else: ?>
                        <?php if ($lot['lot_status_id'] != 2): ?>
                            <a href="/?page=lot_detail&tabel=lot&id=<?php echo htmlspecialchars($lot['lot_id']); ?>&is_update=1"
                                class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition">
                                <i class="fas fa-edit mr-2"></i> แก้ไขข้อมูล
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script>
        const showNotification = (message, headerClass) => {
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
        };

        const hideNotification = () => {
            document.getElementById('notification').classList.add('translate-x-full');
        };

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                showNotification(decodeURIComponent(urlParams.get('success')), 'bg-green-500');
            } else if (urlParams.has('error')) {
                showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-600');
            }

            <?php if ($is_update): ?>
                document.getElementById('lot-detail').focus();
            <?php endif; ?>

            const validateForm = () => {
                let hasError = false;
                const fields = [{
                        id: 'lot-detail',
                        errorId: 'detailError',
                        label: 'รายละเอียดล็อต',
                        maxLength: 1000,
                        required: false
                    },
                    {
                        id: 'lot-date',
                        errorId: 'dateError',
                        label: 'วันที่ล็อต',
                        required: true,
                        validate: (value) => /^\d{4}-\d{2}-\d{2}$/.test(value),
                        errorMessage: 'วันที่ต้องอยู่ในรูปแบบที่ถูกต้อง'
                    },
                    {
                        id: 'lot-status',
                        errorId: 'statusError',
                        label: 'สถานะล็อต',
                        required: true
                    },
                    {
                        id: 'warehouse-id',
                        errorId: 'warehouseError',
                        label: 'คลังปลายทาง',
                        required: true
                    },
                    {
                        id: 'personnel-id',
                        errorId: 'personnelError',
                        label: 'พนักงาน',
                        required: true
                    }
                ];

                fields.forEach(({
                    id,
                    errorId,
                    label,
                    maxLength,
                    required,
                    validate,
                    errorMessage
                }) => {
                    const input = document.getElementById(id);
                    const error = document.getElementById(errorId);
                    if (required && !input.value.trim()) {
                        error.textContent = `กรุณากรอก${label}`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (maxLength && input.value.length > maxLength) {
                        error.textContent = `${label}ยาวเกิน ${maxLength} ตัวอักษร`;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else if (validate && !validate(input.value)) {
                        error.textContent = errorMessage;
                        error.classList.remove('hidden');
                        hasError = true;
                    } else {
                        error.classList.add('hidden');
                    }
                });

                return !hasError;
            };

            document.getElementById('lotForm').addEventListener('submit', (e) => {
                if (!validateForm()) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>

</html>