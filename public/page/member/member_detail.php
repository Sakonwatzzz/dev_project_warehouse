<?php
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');
$pdo = Database::getInstance()->getConnection(); 

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
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 86400)) {
    session_unset();
    session_destroy();
    $query_params = http_build_query(['timeout' => 1, 'page' => 'member_detail', 'tabel' => $_GET['tabel'] ?? '', 'id' => $_GET['id'] ?? ''] + ($_GET ?? []));
    header("Location: login.php?$query_params");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// Validate required GET parameters
if (!isset($_GET['tabel']) || !isset($_GET['id'])) {
    header("Location: /?page=member_list&error=" . urlencode("ระบุประเภทหรือ ID ไม่ถูกต้อง"));
    exit;
}

// Function to validate form data
function validateMemberData($name, $address, $phone, $registerdate, $typename, $personnelname)
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
    if (empty($registerdate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $registerdate)) {
        $errors[] = 'วันที่สมัครต้องอยู่ในรูปแบบ YYYY-MM-DD';
    }
    if (empty($typename) || !is_numeric($typename)) {
        $errors[] = 'ต้องเลือกประเภทสมาชิก';
    }
    if (empty($personnelname) || !is_numeric($personnelname)) {
        $errors[] = 'ต้องเลือกพนักงาน';
    }
    return $errors;
}

// Handle POST request for updating member
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header("Location: /?page=member_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&error=" . urlencode("Invalid CSRF token"));
        exit;
    }

    $member_id = filter_var($_POST['member_id'], FILTER_VALIDATE_INT);
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $registerdate = trim($_POST['registerdate']);
    $typename = filter_var($_POST['typename'], FILTER_VALIDATE_INT);
    $personnelname = filter_var($_POST['personnelname'], FILTER_VALIDATE_INT);

    $errors = validateMemberData($name, $address, $phone, $registerdate, $typename, $personnelname);
    if (!empty($errors)) {
        header("Location: /?page=member_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&is_update=1&error=" . urlencode(implode(', ', $errors)));
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE member 
            SET member_name = ?, member_address = ?, member_phone = ?, member_register_date = ?, member_type_id = ?, personnel_id = ?
            WHERE member_id = ?
        ");
        $stmt->execute([$name, $address, $phone, $registerdate, $typename, $personnelname, $member_id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            header("Location: /?page=member_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&error=" . urlencode("ไม่พบข้อมูลสมาชิกที่ต้องการแก้ไข"));
            exit;
        }
        $pdo->commit();
        header("Location: /?page=member_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&success=" . urlencode("แก้ไขข้อมูลสำเร็จ"));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Update member error: " . $e->getMessage());
        header("Location: /?page=member_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&error=" . urlencode("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage())));
        exit;
    }
}

// Retrieve GET parameters
$tabel = $_GET['tabel'];
$id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$is_update = isset($_GET['is_update']) ? (bool)$_GET['is_update'] : false;

if (!$id) {
    header("Location: /?page=member_list&error=" . urlencode("ID ไม่ถูกต้อง"));
    exit;
}

// Prepare SQL query based on table type
switch ($tabel) {
    case 'member':
        $stmt = $pdo->prepare("
            SELECT member.*, member_type.member_type_name, personnel.personnel_name
            FROM member
            LEFT JOIN member_type ON member.member_type_id = member_type.member_type_id
            LEFT JOIN personnel ON member.personnel_id = personnel.personnel_id
            WHERE member.member_id = ?
        ");
        break;
    default:
        header("Location: /?page=member_list&error=" . urlencode("ระบุประเภทไม่ถูกต้อง"));
        exit;
}

try {
    $stmt->execute([$id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$member) {
        header("Location: /?page=member_list&error=" . urlencode("ไม่พบข้อมูลสมาชิก"));
        exit;
    }
} catch (PDOException $e) {
    error_log("Fetch member error: " . $e->getMessage());
    header("Location: /?page=member_list&error=" . urlencode("เกิดข้อผิดพลาดในฐานข้อมูล: " . htmlspecialchars($e->getMessage())));
    exit;
}

// Fetch member types for dropdown
try {
    $queryTypes = "SELECT member_type_id, member_type_name FROM member_type ORDER BY member_type_name";
    $member_type_results = $pdo->query($queryTypes);
} catch (PDOException $e) {
    error_log("Fetch member types error: " . $e->getMessage());
    header("Location: /?page=member_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&error=" . urlencode("เกิดข้อผิดพลาดในการดึงข้อมูลประเภทสมาชิก"));
    exit;
}

// Fetch personnel for dropdown
try {
    $queryPersonnels = "SELECT personnel_id, personnel_name FROM personnel ORDER BY personnel_name";
    $personnelResults = $pdo->query($queryPersonnels);
} catch (PDOException $e) {
    error_log("Fetch personnel error: " . $e->getMessage());
    header("Location: /?page=member_detail&tabel={$_GET['tabel']}&id={$_GET['id']}&error=" . urlencode("เกิดข้อผิดพลาดในการดึงข้อมูลพนักงาน"));
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_update ? 'แก้ไขข้อมูลสมาชิก' : 'รายละเอียดสมาชิก'; ?></title>
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
        <div class="flex flex-col sm:flex-row justify-between mb-6">
            <h2 class="text-2xl font-bold text-blue-800 mb-4 sm:mb-0">
                <i class="fas fa-user mr-2"></i>
                <?php echo $is_update ? 'แก้ไขข้อมูลสมาชิก' : 'รายละเอียดสมาชิก'; ?>
            </h2>
            <!-- <a href="/?page=member_list" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-xl shadow-md hover:from-blue-600 hover:to-blue-800 transition duration-300 ease-in-out">
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

        <!-- Member Details Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 border-b-2 border-gray-200 pb-2">
                ข้อมูลสมาชิก
            </h3>
            <form id="memberForm" action="" method="POST" class="space-y-4">
                <input type="hidden" name="member_id" value="<?php echo htmlspecialchars($member['member_id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <?php
                $fields = [
                    ['label' => 'รหัสสมาชิก', 'name' => 'member_id', 'value' => $member['member_id'], 'readonly' => true, 'id' => 'member-id'],
                    ['label' => 'ชื่อ-สกุล', 'name' => 'name', 'value' => $member['member_name'], 'id' => 'member-name', 'required' => true, 'errorId' => 'nameError'],
                    ['label' => 'ที่อยู่', 'name' => 'address', 'value' => $member['member_address'], 'id' => 'member-address', 'errorId' => 'addressError'],
                    ['label' => 'เบอร์โทร', 'name' => 'phone', 'value' => $member['member_phone'], 'id' => 'member-phone', 'errorId' => 'phoneError'],
                    ['label' => 'วันที่สมัคร', 'name' => 'registerdate', 'value' => date('Y-m-d', strtotime($member['member_register_date'])), 'id' => 'member-registerdate', 'type' => 'date', 'errorId' => 'registerdateError'],
                ];
                foreach ($fields as $field): ?>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                        <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700"><?php echo $field['label']; ?>:</label>
                        <div class="w-full sm:w-8/12">
                            <?php if ($is_update && !($field['readonly'] ?? false)): ?>
                                <input type="<?php echo $field['type'] ?? 'text'; ?>"
                                    id="<?php echo $field['id']; ?>"
                                    name="<?php echo $field['name']; ?>"
                                    value="<?php echo htmlspecialchars($field['value']); ?>"
                                    class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                    <?php echo ($field['required'] ?? false) ? 'required' : ''; ?>
                                    aria-describedby="<?php echo $field['errorId'] ?? ''; ?>">
                                <span id="<?php echo $field['errorId'] ?? ''; ?>" class="text-red-500 text-xs mt-1 hidden"></span>
                            <?php elseif ($is_update && ($field['readonly'] ?? false)): ?>
                                <input type="<?php echo $field['type'] ?? 'text'; ?>"
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

                <!-- Member Type -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700">
                        <a href="?page=member_type" class="text-blue-600 hover:underline">ประเภทสมาชิก</a>:
                    </label>
                    <div class="w-full sm:w-8/12">
                        <?php if ($is_update): ?>
                            <select id="member-type" name="typename" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="typeError">
                                <option value="">เลือกประเภทสมาชิก</option>
                                <?php while ($typeRow = $member_type_results->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo htmlspecialchars($typeRow['member_type_id']); ?>"
                                        <?php echo $typeRow['member_type_id'] == $member['member_type_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($typeRow['member_type_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <span id="typeError" class="text-red-500 text-xs mt-1 hidden"></span>
                        <?php else: ?>
                            <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($member['member_type_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Personnel -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    <label class="w-full sm:w-4/12 text-sm font-medium text-gray-700">
                        <a href="?page=personnel_insert" class="text-blue-600 hover:underline">พนักงาน</a>:
                    </label>
                    <div class="w-full sm:w-8/12">
                        <?php if ($is_update): ?>
                            <select id="personnel-name" name="personnelname" class="form-field w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required aria-describedby="personnelError">
                                <option value="">เลือกพนักงาน</option>
                                <?php while ($personnelRow = $personnelResults->fetch(PDO::FETCH_ASSOC)): ?>
                                    <option value="<?php echo htmlspecialchars($personnelRow['personnel_id']); ?>"
                                        <?php echo $personnelRow['personnel_id'] == $member['personnel_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($personnelRow['personnel_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <span id="personnelError" class="text-red-500 text-xs mt-1 hidden"></span>
                        <?php else: ?>
                            <span class="block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-600"><?php echo htmlspecialchars($member['personnel_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end gap-4 mt-6">
                    <?php if ($is_update): ?>
                        <a href="/?page=member_detail&tabel=member&id=<?php echo htmlspecialchars($member['member_id']); ?>"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                            <i class="fas fa-times mr-2"></i> ยกเลิก
                        </a>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition">
                            <i class="fas fa-save mr-2"></i> บันทึก
                        </button>
                    <?php else: ?>
                        <a href="/?page=member_detail&tabel=member&id=<?php echo htmlspecialchars($member['member_id']); ?>&is_update=1"
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
                const successMessage = urlParams.get('success') === '1' ? 'เพิ่มสำเร็จ' : decodeURIComponent(urlParams.get('success'));
                showNotification(successMessage, 'bg-green-500');
            } else if (urlParams.has('error')) {
                showNotification(decodeURIComponent(urlParams.get('error')), 'bg-red-600');
            }

            <?php if ($is_update): ?>
                document.getElementById('member-name').focus();
            <?php endif; ?>

            const validateForm = () => {
                let hasError = false;
                const fields = [{
                        id: 'member-name',
                        errorId: 'nameError',
                        label: 'ชื่อ-สกุล',
                        maxLength: 255,
                        required: true
                    },
                    {
                        id: 'member-address',
                        errorId: 'addressError',
                        label: 'ที่อยู่',
                        maxLength: 500
                    },
                    {
                        id: 'member-phone',
                        errorId: 'phoneError',
                        label: 'เบอร์โทร',
                        validate: (value) => !value || /^\d{10}$/.test(value),
                        errorMessage: 'ต้องเป็นตัวเลข 10 หลัก'
                    },
                    {
                        id: 'member-registerdate',
                        errorId: 'registerdateError',
                        label: 'วันที่สมัคร',
                        validate: (value) => /^\d{4}-\d{2}-\d{2}$/.test(value),
                        errorMessage: 'ต้องอยู่ในรูปแบบ YYYY-MM-DD',
                        required: true
                    },
                    {
                        id: 'member-type',
                        errorId: 'typeError',
                        label: 'ประเภทสมาชิก',
                        required: true
                    },
                    {
                        id: 'personnel-name',
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

            document.getElementById('memberForm').addEventListener('submit', (e) => {
                if (!validateForm()) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>

</html>