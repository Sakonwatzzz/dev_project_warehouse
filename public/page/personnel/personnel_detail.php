<?php
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection(); 

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
    $query_params = http_build_query(['timeout' => 1, 'page' => 'personnel_detail', 'tabel' => $_GET['tabel'] ?? '', 'id' => $_GET['id'] ?? ''] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Validate required GET parameters
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    header("Location: /?page=personnel_list&error=" . urlencode("ระบุประเภทหรือ ID ไม่ถูกต้อง"));
    exit;
}

// Function to validate form data
function validatePersonnelData($name, $address, $phone, $username, $password, $typename)
{
    $errors = [];
    if (empty($name) || strlen($name) > 255) {
        $errors[] = 'ชื่อ-สกุลต้องไม่ว่างและมีความยาวไม่เกิน 255 ตัวอักษร';
    }
    if (strlen($address) > 500) {
        $errors[] = 'ที่อยู่มีความยาวเกิน 500 ตัวอักษร';
    }
    if (!empty($phone) && !preg_match('/^\d{10}$/', $phone)) {
        $errors[] = 'เบอร์โทรต้องเป็นตัวเลข 10 หลัก';
    }
    if (strlen($username) > 50) {
        $errors[] = 'ชื่อผู้ใช้มีความยาวเกิน 50 ตัวอักษร';
    }
    if (strlen($password) > 255) {
        $errors[] = 'รหัสผ่านมีความยาวเกิน 255 ตัวอักษร';
    }
    if (empty($typename) || !is_numeric($typename)) {
        $errors[] = 'ต้องเลือกประเภทบุคลากร';
    }
    return $errors;
}

// Handle POST request for updating personnel
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: /?page=personnel_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $personnel_id = filter_var($_POST['personnel_id'], FILTER_VALIDATE_INT);
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $typename = filter_var($_POST['typename'], FILTER_VALIDATE_INT);

    $errors = validatePersonnelData($name, $address, $phone, $username, $password, $typename);
    if (!empty($errors)) {
        header("Location: /?page=personnel_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&is_update=1&error=" . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE personnel 
            SET personnel_name = ?, personnel_address = ?, personnel_phone = ?, 
                personnel_username = ?, personnel_password = ?, personnel_type_id = ?
            WHERE personnel_id = ?
        ");
        $stmt->execute([$name, $address, $phone, $username, $password, $typename, $personnel_id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header("Location: /?page=personnel_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&error=" . urlencode("ไม่พบข้อมูลบุคลากรที่ต้องการแก้ไข"));
            exit;
        }
        $pdo->commit();
        header("Location: /?page=personnel_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&success=" . urlencode("แก้ไขข้อมูลสำเร็จ"));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update personnel error: " . $e->getMessage());
        header("Location: /?page=personnel_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&error=" . urlencode("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Retrieve GET parameters
$tabel = $_GET['tabel'];
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$is_update = isset($_GET['is_update']) ? (bool)$_GET['is_update'] : false;

if (!$id) {
    header("Location: /?page=personnel_list&error=" . urlencode("ID ไม่ถูกต้อง"));
    exit;
}

// Prepare SQL query based on table type
switch ($tabel) {
    case 'personnel':
        $stmt = $pdo->prepare("
            SELECT personnel.*, personnel_type.personnel_type_name
            FROM personnel
            INNER JOIN personnel_type ON personnel.personnel_type_id = personnel_type.personnel_type_id
            WHERE personnel.personnel_id = ?
        ");
        break;
    default:
        header("Location: /?page=personnel_list&error=" . urlencode("ระบุประเภทไม่ถูกต้อง"));
        exit;
}

try {
    $stmt->execute([$id]);
    $personnel = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$personnel) {
        header("Location: /?page=personnel_list&error=" . urlencode("ไม่พบข้อมูลบุคลากร"));
        exit;
    }
} catch (PDOException $e) {
    error_log("Fetch personnel error: " . $e->getMessage());
    header("Location: /?page=personnel_list&error=" . urlencode("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage())));
    exit;
}

// Fetch personnel types for dropdown
try {
    $queryTypes = "SELECT personnel_type_id, personnel_type_name FROM personnel_type ORDER BY personnel_type_name";
    $personnel_type_results = $pdo->query($queryTypes);
} catch (PDOException $e) {
    error_log("Fetch personnel types error: " . $e->getMessage());
    header("Location: /?page=personnel_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&error=" . urlencode("เกิดข้อผิดพลาดในการดึงข้อมูลประเภทบุคลากร"));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_update ? 'แก้ไขข้อมูลบุคลากร' : 'รายละเอียดบุคลากร'; ?></title>
    <link href="../../output.css" rel="stylesheet">
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
        <div class="flex flex-col sm:flex-row justify-between  mb-6">
            <h2 class="text-2xl font-bold text-blue-800 mb-4 sm:mb-0">
                <i class="fas fa-user mr-2"></i>
                <?php echo $is_update ? 'แก้ไขข้อมูลบุคลากร' : 'รายละเอียดบุคลากร'; ?>
            </h2>
            <!-- <a href="/?page=personnel_list" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-xl shadow-md hover:from-blue-600 hover:to-blue-800 transition duration-300 ease-in-out">
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

        <!-- Personnel Details Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 border-b-2 border-gray-200 pb-2">
                ข้อมูลบุคลากร
            </h3>
            <form id="personnelForm" action="" method="POST" class="space-y-4">
                <input type="hidden" name="personnel_id" value="<?php echo htmlspecialchars($personnel['personnel_id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <?php
                $fields = [
                    ['label' => 'รหัสบุคลากร', 'name' => 'personnel_id', 'value' => $personnel['personnel_id'], 'readonly' => true, 'id' => 'personnel-id'],
                    ['label' => 'ชื่อ-สกุล', 'name' => 'name', 'value' => $personnel['personnel_name'], 'id' => 'personnel-name', 'required' => true, 'errorId' => 'nameError'],
                    ['label' => 'ที่อยู่', 'name' => 'address', 'value' => $personnel['personnel_address'], 'id' => 'personnel-address', 'errorId' => 'addressError'],
                    ['label' => 'เบอร์โทร', 'name' => 'phone', 'value' => $personnel['personnel_phone'], 'id' => 'personnel-phone', 'errorId' => 'phoneError'],
                    ['label' => 'ชื่อผู้ใช้', 'name' => 'username', 'value' => $personnel['personnel_username'], 'id' => 'personnel-username', 'errorId' => 'usernameError'],
                    ['label' => 'รหัสผ่าน', 'name' => 'password', 'value' => $personnel['personnel_password'], 'id' => 'personnel-password', 'errorId' => 'passwordError'],
                ];
                foreach ($fields as $field): ?>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                        <div class="w-full sm:w-8/12">
                            <?php if ($is_update && !($field['readonly'] ?? false)): ?>
                                <input type="<?php echo $field['name'] === 'password' ? 'password' : 'text'; ?>"
                                    id="<?php echo $field['id']; ?>"
                                    name="<?php echo $field['name']; ?>"
                                    value="<?php echo htmlspecialchars($field['value']); ?>"
                                    class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                    <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>
                                    aria-describedby="<?php echo $field['errorId'] ?? ''; ?>">
                                <span id="<?php echo $field['errorId'] ?? ''; ?>" class="text-red-500 text-xs mt-1 hidden"></span>
                            <?php elseif ($is_update && ($field['readonly'] ?? false)): ?>
                                <input type="text"
                                    id="<?php echo $field['id']; ?>"
                                    name="<?php echo $field['name']; ?>"
                                    value="<?php echo htmlspecialchars($field['value']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"
                                    readonly>
                            <?php else: ?>
                                <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($field['value']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Personnel Type -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700">
                        <a href="?page=personnel_type" class="text-blue-600 hover:underline">ประเภทบุคลากร</a>:
                    </label>
                    <div class="w-full sm:w-8/12">
                        <?php if ($is_update): ?>
                            <select id="personnel-type" name="typename" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="typeError">
                                <option value="">เลือกประเภทบุคลากร</option>
                                <?php while ($typeRow = $personnel_type_results->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo htmlspecialchars($typeRow['personnel_type_id']); ?>"
                                        <?php echo $typeRow['personnel_type_id'] == $personnel['personnel_type_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($typeRow['personnel_type_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <span id="typeError" class="text-red-500 text-xs mt-1 hidden"></span>
                        <?php else: ?>
                            <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($personnel['personnel_type_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-4 mt-6">
                    <?php if ($is_update): ?>
                        <a href="/?page=personnel_detail&tabel=personnel&id=<?php echo htmlspecialchars($personnel['personnel_id']); ?>"
                            class="inline-flex items-center px-4 py-2  border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                            <i class="fas fa-times mr-2"></i> ยกเลิก
                        </a>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition">
                            <i class="fas fa-save mr-2"></i> บันทึก
                        </button>
                    <?php else: ?>
                        <a href="/?page=personnel_detail&tabel=personnel&id=<?php echo htmlspecialchars($personnel['personnel_id']); ?>&is_update=1"
                            class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition">
                            <i class="fas fa-edit mr-2"></i> แก้ไขข้อมูล
                        </a>
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
                document.getElementById('personnel-name').focus();
            <?php endif; ?>

            const validateForm = () => {
                let hasError = false;
                const fields = [{
                        id: 'personnel-name',
                        errorId: 'nameError',
                        label: 'ชื่อ-สกุล',
                        maxLength: 255,
                        required: true
                    },
                    {
                        id: 'personnel-address',
                        errorId: 'addressError',
                        label: 'ที่อยู่',
                        maxLength: 500
                    },
                    {
                        id: 'personnel-phone',
                        errorId: 'phoneError',
                        label: 'เบอร์โทร',
                        validate: (value) => !value || /^\d{10}$/.test(value),
                        errorMessage: 'ต้องเป็นตัวเลข 10 หลัก'
                    },
                    {
                        id: 'personnel-username',
                        errorId: 'usernameError',
                        label: 'ชื่อผู้ใช้',
                        maxLength: 50
                    },
                    {
                        id: 'personnel-password',
                        errorId: 'passwordError',
                        label: 'รหัสผ่าน',
                        maxLength: 255
                    },
                    {
                        id: 'personnel-type',
                        errorId: 'typeError',
                        label: 'ประเภทบุคลากร',
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

            document.getElementById('personnelForm').addEventListener('submit', (e) => {
                if (!validateForm()) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>

</html>