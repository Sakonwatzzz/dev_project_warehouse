<?php
require_once __DIR__ . '../../../../config/database_server.php'; 

session_start();
if (!isset($_SESSION['bills'])) {
    $_SESSION['bills'] = [];
}

$activeBillId = $_GET['bill_id'] ?? (isset($_SESSION['bills']) && count($_SESSION['bills']) > 0 ? array_keys($_SESSION['bills'])[0] : null);

// ตรวจสอบว่าเป็น Ajax request หรือไม่
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// เพิ่มบิลใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_bill') {
    header('Content-Type: application/json');
    $newBillId = uniqid('bill_');
    $_SESSION['bills'][$newBillId] = [
        'products' => [],
        'member' => null,
        'personnel' => null
    ];
    echo json_encode(['success' => true, 'bill_id' => $newBillId]);
    exit();
}

// ลบบิล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_bill' && isset($_POST['bill_id'])) {
    header('Content-Type: application/json');
    $billId = $_POST['bill_id'];
    unset($_SESSION['bills'][$billId]);
    echo json_encode(['success' => true]);
    exit();
}

// สแกน product_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id']) && $activeBillId) {
    header('Content-Type: application/json');
    try {
        $product_id = trim($_POST['product_id']);
        $stmt = $pdo->prepare("SELECT product_id, product_name, product_price1, product_price2, 
                              product_price3, product_price4, product_capital, product_type_id, 
                              product_brand_id FROM product WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            if (!isset($_SESSION['bills'][$activeBillId]['products'][$product_id])) {
                $_SESSION['bills'][$activeBillId]['products'][$product_id] = [
                    'details' => $product,
                    'quantity' => 1
                ];
            } else {
                $_SESSION['bills'][$activeBillId]['products'][$product_id]['quantity'] += 1;
            }
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่พบสินค้า']);
        }
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// สแกน member_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_id']) && $activeBillId) {
    header('Content-Type: application/json');
    try {
        $member_id = trim($_POST['member_id']);
        $stmt = $pdo->prepare("SELECT member_id, member_name, member_phone, member_register_date 
                              FROM member WHERE member_id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            $_SESSION['bills'][$activeBillId]['member'] = $member;
            echo json_encode(['success' => true, 'member' => $member]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่พบสมาชิก']);
        }
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// สแกน personnel_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['personnel_id']) && $activeBillId) {
    header('Content-Type: application/json');
    try {
        $personnel_id = trim($_POST['personnel_id']);
        $stmt = $pdo->prepare("SELECT personnel_id, personnel_name, personnel_phone, personnel_username 
                              FROM personnel WHERE personnel_id = ?");
        $stmt->execute([$personnel_id]);
        $personnel = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($personnel) {
            $_SESSION['bills'][$activeBillId]['personnel'] = $personnel;
            echo json_encode(['success' => true, 'personnel' => $personnel]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ไม่พบพนักงาน']);
        }
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// ถ้าไม่ใช่ Ajax request ให้แสดง HTML
if (!$isAjax) {

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าการขายสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .bill-tab { 
            @apply bg-blue-400 text-white p-2 rounded-t-lg mr-1 cursor-pointer hover:bg-blue-500;
        }
        .bill-tab.active { 
            @apply bg-white text-blue-800 font-bold border-t-2 border-l-2 border-r-2 border-blue-500;
        }
        .bill-content { 
            @apply hidden;
        }
        .bill-content.active { 
            @apply block;
        }
    </style>
</head>
<body class="bg-gray-200 font-sans">
    <div class="container mx-auto p-4 bg-white shadow-lg rounded-lg">
        <!-- ส่วนหัว -->
        <div class="flex justify-between items-center bg-blue-600 text-white p-4 rounded-t-lg">
            <div class="flex space-x-2">
                <button class="p-2 bg-blue-700 rounded">รับชำระ</button>
                <button class="p-2 bg-blue-700 rounded">ออกใบกำกับ</button>
                <button class="p-2 bg-blue-700 rounded">เปลี่ยนผู้ใช้</button>
            </div>
            <h1 class="text-xl font-bold">หน้าการขายสินค้า</h1>
            <button class="p-2 bg-blue-700 rounded">ออกจากระบบ ▼</button>
        </div>

        <!-- Input สำหรับสแกน -->
        <div class="p-4 flex items-center space-x-4">
            <div class="flex-1">
                <label for="scanInput" class="block mb-2">สแกนรหัส</label>
                <div class="flex items-center space-x-2">
                    <input type="text" id="scanInput" class="border p-2 rounded-lg w-full" placeholder="กรอกรหัส">
                    <select id="scanType" class="border p-2 rounded-lg">
                        <option value="product_id">สินค้า</option>
                        <option value="member_id">สมาชิก</option>
                        <option value="personnel_id">พนักงาน</option>
                    </select>
                </div>
            </div>
            <button id="newBillBtn" class="bg-blue-500 text-white p-2 rounded-lg h-10 mt-8">เปิดบิลใหม่</button>
        </div>

        <!-- แท็บด้านบน -->
        <div id="bill-tabs" class="flex gap-2 mb-4 border-b overflow-x-auto">
            <?php foreach ($_SESSION['bills'] as $billId => $billData): ?>
                <div class="bill-tab <?php echo $billId === $activeBillId ? 'active' : ''; ?>" data-bill-id="<?php echo $billId; ?>">
                    <span>บิล #<?php echo substr($billId, 5); ?></span>
                    <button class="close-tab text-blue-500 ml-2" data-bill-id="<?php echo $billId; ?>">x</button>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- เนื้อหาบิล -->
        <div id="bill-contents">
            <?php foreach ($_SESSION['bills'] as $billId => $billData): ?>
                <div id="bill-<?php echo $billId; ?>" class="bill-content <?php echo $billId === $activeBillId ? 'active' : ''; ?>">
                    <div class="p-4">
                        <!-- ข้อมูลพนักงานและสมาชิก -->
                        <div class="flex justify-between mb-4">
                            <div>
                                <h3 class="font-semibold">พนักงานขาย:</h3>
                                <div id="personnel-info-<?php echo $billId; ?>">
                                    <?php if (isset($billData['personnel']) && $billData['personnel']): ?>
                                        <p>ชื่อ: <?php echo htmlspecialchars($billData['personnel']['personnel_name'] ?? ''); ?></p>
                                        <p>เบอร์: <?php echo htmlspecialchars($billData['personnel']['personnel_phone'] ?? ''); ?></p>
                                        <p>Username: <?php echo htmlspecialchars($billData['personnel']['personnel_username'] ?? ''); ?></p>
                                    <?php else: ?>
                                        <p>ยังไม่ระบุพนักงาน</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <h3 class="font-semibold">สมาชิก:</h3>
                                <div id="member-info-<?php echo $billId; ?>">
                                    <?php if (isset($billData['member']) && $billData['member']): ?>
                                        <p>ชื่อ: <?php echo htmlspecialchars($billData['member']['member_name'] ?? ''); ?></p>
                                        <p>เบอร์: <?php echo htmlspecialchars($billData['member']['member_phone'] ?? ''); ?></p>
                                        <p>วันที่สมัคร: <?php echo htmlspecialchars($billData['member']['member_register_date'] ?? ''); ?></p>
                                    <?php else: ?>
                                        <p>ยังไม่ระบุสมาชิก</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- ตารางสินค้า -->
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-blue-500 text-white">
                                    <th class="p-2 border">Product ID</th>
                                    <th class="p-2 border">ชื่อสินค้า</th>
                                    <th class="p-2 border">ราคา 1</th>
                                    <th class="p-2 border">ราคา 2</th>
                                    <th class="p-2 border">ราคา 3</th>
                                    <th class="p-2 border">ราคา 4</th>
                                    <th class="p-2 border">ราคาทุน</th>
                                    <th class="p-2 border">จำนวน</th>
                                </tr>
                            </thead>
                            <tbody id="product-table-<?php echo $billId; ?>">
                                <?php if (isset($billData['products']) && is_array($billData['products'])): ?>
                                    <?php foreach ($billData['products'] as $productId => $data): ?>
                                        <tr class="border-b">
                                            <td class="p-2 border"><?php echo htmlspecialchars($data['details']['product_id'] ?? ''); ?></td>
                                            <td class="p-2 border"><?php echo htmlspecialchars($data['details']['product_name'] ?? ''); ?></td>
                                            <td class="p-2 border"><?php echo number_format($data['details']['product_price1'] ?? 0, 2); ?></td>
                                            <td class="p-2 border"><?php echo number_format($data['details']['product_price2'] ?? 0, 2); ?></td>
                                            <td class="p-2 border"><?php echo number_format($data['details']['product_price3'] ?? 0, 2); ?></td>
                                            <td class="p-2 border"><?php echo number_format($data['details']['product_price4'] ?? 0, 2); ?></td>
                                            <td class="p-2 border"><?php echo number_format($data['details']['product_capital'] ?? 0, 2); ?></td>
                                            <td class="p-2 border"><?php echo htmlspecialchars($data['quantity'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ส่วนแท็บบิลทางขวา -->
        <div class="fixed right-4 top-1/4 space-y-2">
            <button class="w-16 h-16 bg-blue-500 text-white rounded-lg flex items-center justify-center">F1<br>รับชำระ</button>
            <button class="w-16 h-16 bg-blue-500 text-white rounded-lg flex items-center justify-center">F9<br>ออกใบกำกับ</button>
            <button class="w-16 h-16 bg-red-500 text-white rounded-lg flex items-center justify-center">Log Out</button>
        </div>

        <!-- ส่วนล่าง -->
        <div class="flex justify-between items-center bg-blue-600 text-white p-4 rounded-b-lg">
            <p>วัน 14 ต.ค. 2022 10:50</p>
            <p>POS SME BY HEGCOMP LIMITED PARTNERSHIP</p>
            <p>Administrator</p>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const scanInput = $('#scanInput');
            const scanTypeSelect = $('#scanType');

            // เปิดบิลใหม่
            $('#newBillBtn').on('click', function() {
                $.ajax({
                    url: window.location.href,
                    method: 'POST',
                    data: { action: 'new_bill' },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const billId = response.bill_id;
                            // เพิ่มแท็บใหม่ใน UI
                            $('#bill-tabs').append(`
                                <div class="bill-tab" data-bill-id="${billId}">
                                    <span>บิล #${billId.substring(5)}</span>
                                    <button class="close-tab text-blue-500 ml-2" data-bill-id="${billId}">x</button>
                                </div>
                            `);
                            $('#bill-contents').append(`
                                <div id="bill-${billId}" class="bill-content">
                                    <div class="p-4">
                                        <div class="flex justify-between mb-4">
                                            <div>
                                                <h3 class="font-semibold">พนักงานขาย:</h3>
                                                <div id="personnel-info-${billId}">ยังไม่ระบุพนักงาน</div>
                                            </div>
                                            <div>
                                                <h3 class="font-semibold">สมาชิก:</h3>
                                                <div id="member-info-${billId}">ยังไม่ระบุสมาชิก</div>
                                            </div>
                                        </div>
                                        <table class="w-full border-collapse">
                                            <thead>
                                                <tr class="bg-blue-500 text-white">
                                                    <th class="p-2 border">Product ID</th>
                                                    <th class="p-2 border">ชื่อสินค้า</th>
                                                    <th class="p-2 border">ราคา 1</th>
                                                    <th class="p-2 border">ราคา 2</th>
                                                    <th class="p-2 border">ราคา 3</th>
                                                    <th class="p-2 border">ราคา 4</th>
                                                    <th class="p-2 border">ราคาทุน</th>
                                                    <th class="p-2 border">จำนวน</th>
                                                </tr>
                                            </thead>
                                            <tbody id="product-table-${billId}"></tbody>
                                        </table>
                                    </div>
                                </div>
                            `);
                            $('.bill-tab').removeClass('active');
                            $('.bill-content').removeClass('active');
                            $(`.bill-tab[data-bill-id="${billId}"]`).addClass('active');
                            $(`#bill-${billId}`).addClass('active');
                            window.history.pushState({}, '', `?bill_id=${billId}`);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: 'ไม่สามารถเปิดบิลใหม่ได้'
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'การเชื่อมต่อล้มเหลว: ' + xhr.responseText
                        });
                    }
                });
            });

            // สลับแท็บเมื่อคลิก
            $(document).on('click', '.bill-tab', function(e) {
                e.preventDefault();
                const billId = $(this).data('bill-id');
                $('.bill-tab').removeClass('active');
                $('.bill-content').removeClass('active');
                $(`.bill-tab[data-bill-id="${billId}"]`).addClass('active');
                $(`#bill-${billId}`).addClass('active');
                window.history.pushState({}, '', `?bill_id=${billId}`);
            });

            // ปิดแท็บ
            $(document).on('click', '.close-tab', function(e) {
                e.stopPropagation();
                const billId = $(this).data('bill-id');
                Swal.fire({
                    title: 'คุณแน่ใจหรือไม่?',
                    text: "บิลนี้จะถูกลบถาวร",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ลบ',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: window.location.href,
                            method: 'POST',
                            data: { action: 'delete_bill', bill_id: billId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    $(`#bill-${billId}`).remove();
                                    $(`.bill-tab[data-bill-id="${billId}"]`).remove();
                                    const remainingTabs = $('.bill-tab');
                                    if (remainingTabs.length > 0) {
                                        const newActiveBillId = remainingTabs.first().data('bill-id');
                                        $('.bill-tab').removeClass('active');
                                        $('.bill-content').removeClass('active');
                                        $(`.bill-tab[data-bill-id="${newActiveBillId}"]`).addClass('active');
                                        $(`#bill-${newActiveBillId}`).addClass('active');
                                        window.history.pushState({}, '', `?bill_id=${newActiveBillId}`);
                                    } else {
                                        window.history.pushState({}, '', '?');
                                    }
                                }
                            },
                            error: function(xhr) {
                                Swal.fire('เกิดข้อผิดพลาด', xhr.responseText, 'error');
                            }
                        });
                    }
                });
            });

            // สแกนข้อมูล
            scanInput.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    const value = scanInput.val().trim();
                    const scanType = scanTypeSelect.val();
                    const activeBillId = $('.bill-tab.active').data('bill-id');

                    if (!value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'กรุณากรอกรหัส',
                            text: 'กรุณากรอกรหัสก่อนสแกน'
                        });
                        return;
                    }

                    if (!activeBillId && $('.bill-tab').length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'กรุณาเปิดบิลก่อน',
                            text: 'โปรดเปิดบิลใหม่ก่อนสแกน'
                        });
                        return;
                    }

                    const currentActiveBillId = activeBillId || '<?php echo $activeBillId; ?>';

                    let requestData = {};
                    requestData[scanType] = value;

                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: requestData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                if (scanType === 'product_id') {
                                    const product = response.product;
                                    const tbody = $(`#product-table-${currentActiveBillId}`);
                                    const existingRow = tbody.find(`tr:contains(${product.product_id})`);
                                    if (existingRow.length) {
                                        const qtyCell = existingRow.find('td:last');
                                        qtyCell.text(parseInt(qtyCell.text()) + 1);
                                    } else {
                                        tbody.append(`
                                            <tr class="border-b">
                                                <td class="p-2 border">${product.product_id}</td>
                                                <td class="p-2 border">${product.product_name}</td>
                                                <td class="p-2 border">${parseFloat(product.product_price1).toFixed(2)}</td>
                                                <td class="p-2 border">${parseFloat(product.product_price2).toFixed(2)}</td>
                                                <td class="p-2 border">${parseFloat(product.product_price3).toFixed(2)}</td>
                                                <td class="p-2 border">${parseFloat(product.product_price4).toFixed(2)}</td>
                                                <td class="p-2 border">${parseFloat(product.product_capital).toFixed(2)}</td>
                                                <td class="p-2 border">1</td>
                                            </tr>
                                        `);
                                    }
                                } else if (scanType === 'member_id') {
                                    const member = response.member;
                                    $(`#member-info-${currentActiveBillId}`).html(`
                                        <p>ชื่อ: ${member.member_name}</p>
                                        <p>เบอร์: ${member.member_phone}</p>
                                        <p>วันที่สมัคร: ${member.member_register_date}</p>
                                    `);
                                } else if (scanType === 'personnel_id') {
                                    const personnel = response.personnel;
                                    $(`#personnel-info-${currentActiveBillId}`).html(`
                                        <p>ชื่อ: ${personnel.personnel_name}</p>
                                        <p>เบอร์: ${personnel.personnel_phone}</p>
                                        <p>Username: ${personnel.personnel_username}</p>
                                    `);
                                }
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'ไม่พบข้อมูล',
                                    text: response.error || 'รหัสไม่ตรงกับข้อมูลที่เลือก'
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'เกิดข้อผิดพลาด',
                                text: 'การเชื่อมต่อล้มเหลว: ' + xhr.responseText
                            });
                        }
                    });

                    scanInput.val('');
                }
            });
        });
    </script>
</body>
</html>

<?php
}
?>