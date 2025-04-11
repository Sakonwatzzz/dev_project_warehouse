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
    exit(); // หยุดการทำงานทันทีหลังส่ง JSON
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
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        .bill-tab {
            @apply bg-gray-200 p-2 rounded-t-lg mr-1 cursor-pointer hover:bg-gray-300;
        }

        .bill-tab.active {
            @apply bg-white font-bold;
        }

        .bill-content {
            @apply hidden;
        }

        .bill-content.active {
            @apply block;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans">
    <div class="flex h-screen">
        <!-- ส่วนบิลทั้งหมด -->
        <div class="w-3/4 p-4">
            <h1 class="text-2xl font-bold mb-4">หน้าการขายสินค้า</h1>
            <!-- Input สำหรับสแกน -->
            <div class="flex justify-center items-center gap-10 pb-6">
                <div class=" items-center ">
                    <h1>พนักงานขาย</h1>
                    <input type="text" id="scanInput" class="border p-2 w-64" placeholder="" value="Personnel_001"
                        readonly>
                </div>
                <div class=" items-center">
                    <h1 value="member_id">สมาชิก</h1>
                    <input type="text" id="scanInput" class="border p-2 w-64" placeholder="กรอกรหัสสมาชิก">
                    <button type="submit" id="" class="bg-blue-500 text-white p-2 rounded">ค้นหา</button>
                </div>
                <div class=" items-center">
                    <h1 value="product_id">สินค้า</h1>
                    <input type="text" id="scanInput" class="border p-2 w-64" placeholder="กรอกรหัสรหัสสินค้า">
                    <button type="submit" id="" class="bg-blue-500 text-white p-2 rounded">ค้นหาสินค้า</button>
                </div>
            </div>
            <div class="pb-5">
                <button id="newBillBtn" class="bg-blue-500 text-white p-2 rounded ">เปิดบิลใหม่</button>
            </div>

            <!-- แท็บด้านบน -->
            <div id="bill-tabs" class="flex gap-2 mb-4 border-b overflow-x-auto">
                <?php foreach ($_SESSION['bills'] as $billId => $billData): ?>
                    <div class="bill-tab rounded-md bg-gray-300 hover:bg-gray-400 cursor-pointer py-2 p-2<?php echo $billId === $activeBillId ? 'active' : ''; ?>"
                        data-bill-id="<?php echo $billId; ?>">
                        <span class=" rounded-md cursor-pointer py-2 ">bill#<?php echo substr($billId, 5); ?></span>
                        <button class="close-tab text-gary-500 ml-2" data-bill-id="<?php echo $billId; ?>">x</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- เนื้อหาบิล -->
            <div id="bill-contents">
                <?php foreach ($_SESSION['bills'] as $billId => $billData): ?>
                    <div id="bill-<?php echo $billId; ?>"
                        class="bill-content <?php echo $billId === $activeBillId ? 'active' : ''; ?>">
                        <div class="bg-white p-4 rounded shadow">
                            <!-- ข้อมูลพนักงาน -->
                            <div class="mb-4">
                                <h3 class="font-semibold">พนักงานขาย:</h3>
                                <div id="personnel-info-<?php echo $billId; ?>">
                                    <?php if (isset($billData['personnel']) && $billData['personnel']): ?>
                                        <p>Personnel_001</p>
                                    <?php else: ?>
                                        <p>ยังไม่ระบุพนักงาน</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ข้อมูลสมาชิก -->
                            <div class="mb-4">
                                <h3 class="font-semibold">สมาชิก:</h3>
                                <div id="member-info-<?php echo $billId; ?>">
                                    <?php if (isset($billData['member']) && $billData['member']): ?>
                                        <p>ชื่อ: <?php echo htmlspecialchars($billData['member']['member_name'] ?? ''); ?></p>
                                        <p>เบอร์: <?php echo htmlspecialchars($billData['member']['member_phone'] ?? ''); ?></p>
                                        <p>วันที่สมัคร:
                                            <?php echo htmlspecialchars($billData['member']['member_register_date'] ?? ''); ?>
                                        </p>
                                    <?php else: ?>
                                        <p>ยังไม่ระบุสมาชิก</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ตารางสินค้า -->
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-gray-200">
                                        <th class="p-2 border">รหัสสินค้า</th>
                                        <th class="p-2 border">ชื่อสินค้า</th>
                                        <th class="p-2 border">ราคาขายชิ้นละ</th>
                                        <th class="p-2 border">ราคาทุน</th>
                                        <th class="p-2 border">จำนวน</th>
                                        <th class="p-2 border">รวมเป็นเงิน</th>

                                    </tr>
                                </thead>
                                <tbody id="product-table-<?php echo $billId; ?>">
                                    <?php if (isset($billData['products']) && is_array($billData['products'])): ?>
                                        <?php foreach ($billData['products'] as $productId => $data): ?>
                                            <tr>
                                                <td class="p-2 border">
                                                    <?php echo htmlspecialchars($data['details']['product_id'] ?? ''); ?>
                                                </td>
                                                <td class="p-2 border">
                                                    <?php echo htmlspecialchars($data['details']['product_name'] ?? ''); ?>
                                                </td>
                                                <td class="p-2 border">
                                                    <?php echo number_format($data['details']['product_price1'] ?? 0, 2); ?>
                                                </td>
                                                <td class="p-2 border">
                                                    <?php echo number_format($data['details']['product_price2'] ?? 0, 2); ?>
                                                </td>
                                                <td class="p-2 border">
                                                    <?php echo number_format($data['details']['product_price3'] ?? 0, 2); ?>
                                                </td>
                                                <td class="p-2 border">
                                                    <?php echo number_format($data['details']['product_price4'] ?? 0, 2); ?>
                                                </td>
                                                <td class="p-2 border">
                                                    <?php echo number_format($data['details']['product_capital'] ?? 0, 2); ?>
                                                </td>
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
        </div>
        <div class="w-1/4 bg-gray-100 p-4 overflow-y-auto">
            <h2 class="text-lg font-semibold mb-4">Order's summary</h2>
            <div class="pb-6">
                <p class="flex justify-center">ใบเสร็จรับเงิน/ใบกำกับภาษีอย่างย่อ</p>
                <p class="flex justify-center">สำเพ็ง บุรีรัมย์
                    (145 ตำบล ชุมเห็ด อำเภอเมืองบุรีรัมย์ บุรีรัมย์ 31000)</p>
                <p class="flex justify-center">TAX ID:</p>
                <p class="flex justify-center">POS ID:</p>
            </div>
            <div class="pb-6">
                <p class="">Date :11/04/2568 14:10</p>
                <p class="">Personnel ID :</p>
                <p class="">Member ID :</p>
            </div>
            <table class="mb-8 w-full border-2 border-gray-300 shadow-lg pb-6">
                <tr class="bg-gray-200">
                    <th class="py-2" hidden>รหัสสินค้า</th>
                    <th class="py-2">ชื่อสินค้า</th>
                    <th class="py-2">จำนวนที่ซื้อ</th>
                    <th class="py-2">ราคาขาย</th>
                </tr>
                <tbody>
                    <tr>
                        <td hidden><?php echo ($product_query['product_id']) ?></td>
                        <td><?php echo ($product_query['product_name']) ?></td>
                        <td><?php echo ($product_query['sale_product_qty']) ?></td>
                        <td><?php echo ($product_query['sale_product_price']) ?></td>
                    </tr>
                </tbody>
            </table>
            <div class="px-4 py-2 border-y border-gray-600 ">
                <table class="w-full">
                    <tr>
                        <th class="text-left">Total bath</th>
                        <td class="text-right">1450.90</td>
                    </tr>
                </table>
            </div>
            <div class="px-4 py-2 border-y border-gray-600">
                <table class="w-full">
                    <tr>
                        <th class="text-left">Status Pay</th>
                        <td class="text-right">QRPayment</td>
                    </tr>
                </table>
                <div>
                    <p>PROMPTPAY\EDC1234567890</p>
                </div>
            </div>
            <div class="px-4 py-2 border-y border-gray-600">
                <table class="w-full">
                    <tr>
                        <th class="text-left">VATable:</th>
                        <td class="text-right">145.00</td>
                    </tr>
                    <tr>
                        <th class="text-left">VAT:7.00%:</th>
                        <td class="text-right">9.49</td>
                    </tr>
                </table>
            </div>
            <div class=" px-4 py-2 border-y border-gray-600">
                <p class="flex justify-center">ราคารวมภาษีมูลค่าเพิ่มแล้ว</p>
                <p class="flex justify-center">ขอบคุณที่ใช้บริการ</p>
            </div>

            <div class="flex justify-center items-center pt-6">
                <button type="submit"
                    class="p-3 text-white rounded-lg bg-blue-600 hover:bg-blue-800 cursor-pointer transition delay-100 duration-2s00 ease-in-out">
                    Pay Now</button>
            </div>
        </div>
    </div>


</body>

</html>


<script>
    $(document).ready(function () {
        const scanInput = $('#scanInput');
        const scanTypeSelect = $('#scanType');

        // เปิดบิลใหม่
        $('#newBillBtn').on('click', function () {
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: { action: 'new_bill' },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        const billId = response.bill_id;
                        // เพิ่มแท็บใหม่ใน UI
                        $('#bill-tabs').append(`
                                <div class="bill-tab" data-bill-id="${billId}">
                                    <span>บิล #${billId.substring(5)}</span>
                                    <button class="close-tab text-red-500 ml-2" data-bill-id="${billId}">x</button>
                                </div>
                            `);
                        $('#bill-contents').append(`
                                <div id="bill-${billId}" class="bill-content">
                                    <div class="bg-white p-4 rounded shadow">
                                        <div class="mb-4">
                                            <h3 class="font-semibold">พนักงานขาย:</h3>
                                            <div id="personnel-info-${billId}">ยังไม่ระบุพนักงาน</div>
                                        </div>
                                        <div class="mb-4">
                                            <h3 class="font-semibold">สมาชิก:</h3>
                                            <div id="member-info-${billId}">ยังไม่ระบุสมาชิก</div>
                                        </div>
                                        <table class="w-full border-collapse">
                                            <thead>
                                                <tr class="bg-gray-200">
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
                        // เพิ่มแท็บทางขวา
                        $('.flex.flex-col.space-y-2').append(`
                                <div class="flex justify-between items-center bg-white p-2 rounded shadow">
                                    <a href="?bill_id=${billId}" class="bill-tab flex-1 " data-bill-id="${billId}">
                                        บิล #${billId.substring(5)}
                                    </a>
                                    <button class="delete-bill text-red-500 hover:text-red-700 ml-2" data-bill-id="${billId}">🗑️</button>
                                </div>
                            `);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: 'ไม่สามารถเปิดบิลใหม่ได้'
                        });
                    }
                },
                error: function (xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: 'การเชื่อมต่อล้มเหลว: ' + xhr.responseText
                    });
                }
            });
        });

        // สลับแท็บเมื่อคลิก
        $(document).on('click', '.bill-tab', function (e) {
            e.preventDefault();
            const billId = $(this).data('bill-id');
            $('.bill-tab').removeClass('active');
            $('.bill-content').removeClass('active');
            $(`.bill-tab[data-bill-id="${billId}"]`).addClass('active');
            $(`#bill-${billId}`).addClass('active');
            window.history.pushState({}, '', `?bill_id=${billId}`);
        });

        // ปิดแท็บ
        $(document).on('click', '.close-tab', function (e) {
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
                        success: function (response) {
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
                        error: function (xhr) {
                            Swal.fire('เกิดข้อผิดพลาด', xhr.responseText, 'error');
                        }
                    });
                }
            });
        });

        // ลบแท็บทางขวา
        $(document).on('click', '.delete-bill', function () {
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
                        success: function (response) {
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
                        error: function (xhr) {
                            Swal.fire('เกิดข้อผิดพลาด', xhr.responseText, 'error');
                        }
                    });
                }
            });
        });

        // สแกนข้อมูล
        scanInput.on('keypress', function (e) {
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
            }
        });
    });
</script>
?>