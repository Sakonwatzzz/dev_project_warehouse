<!-- PHP Handler for switch_sale (ต้องอยู่ใน sale_add.php) -->
<?php
// ต้องเรียก ob_start() ที่ต้นไฟล์
ob_start();
session_start();

// ตั้งค่า error reporting เพื่อป้องกัน output ไม่พึงประสงค์
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Handler สำหรับ switch_sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'switch_sale') {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean(); // ล้าง output buffer
    error_log("Switch sale request: " . json_encode($_POST)); // Log request
    $new_sale_id = $_POST['sale_id'] ?? null;
    if ($new_sale_id && in_array($new_sale_id, $_SESSION['active_sales'] ?? [])) {
        $_SESSION['current_sale_id'] = $new_sale_id;
        $response = ['status' => 'success', 'sale_id' => $new_sale_id, 'message' => 'เปลี่ยนไปใช้บิล #' . $new_sale_id];
        error_log("Switch sale success: " . json_encode($response)); // Log success
        echo json_encode($response);
    } else {
        $response = ['status' => 'error', 'message' => 'บิลไม่ถูกต้องหรือไม่พบ'];
        error_log("Switch sale error: " . json_encode($response)); // Log error
        echo json_encode($response);
    }
    exit(); // หยุดการประมวลผลทันที
}
?>

<!-- HTML for billTabs -->
<div class="flex items-center space-x-2 mb-6">
    <button id="new_sale" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        เปิดบิลใหม่
    </button>
    <div id="billTabs" class="flex space-x-2 overflow-x-auto flex-nowrap max-w-full">
        <?php foreach ($valid_active_sales as $active_sale_id): ?>
            <div id="tab_<?php echo $active_sale_id; ?>" class="px-4 py-2 rounded <?php echo $active_sale_id == $sale_id ? 'tab-active' : 'tab-inactive'; ?> cursor-pointer whitespace-nowrap" onclick="switchBill(<?php echo $active_sale_id; ?>)">
                บิล #<?php echo $active_sale_id; ?> <span onclick="cancelSale(<?php echo $active_sale_id; ?>)">×</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- CSS for billTabs -->
<style>
    .tab-active { background-color: #3b82f6; color: white; }
    .tab-inactive { background-color: #e5e7eb; color: black; }
    #billTabs::-webkit-scrollbar {
        height: 8px;
    }
    #billTabs::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    #billTabs::-webkit-scrollbar-thumb {
        background: #3b82f6;
        border-radius: 4px;
    }
    #billTabs::-webkit-scrollbar-thumb:hover {
        background: #2563eb;
    }
</style>

<!-- JavaScript/Ajax for billTabs -->
<script>
    // Array to track bills in client-side
    let bills = <?php echo json_encode($valid_active_sales); ?>;
    let currentSaleId = <?php echo json_encode($sale_id); ?>;

    // Function to create a new sale
    function createNewSale() {
        $.ajax({
            url: window.location.pathname,
            type: 'POST',
            data: {
                action: 'new_sale'
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    bills.push(response.sale_id);
                    currentSaleId = response.sale_id;
                    addBillTab(response.sale_id);
                    switchBill(response.sale_id);
                    Swal.fire({
                        toast: true,
                        position: 'center',
                        icon: 'success',
                        title: response.message,
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true
                    });
                    window.location.href = response.redirect;
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: response.message,
                        confirmButtonText: 'ตกลง'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('New sale error:', xhr.responseText); // Log raw response
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'เกิดข้อผิดพลาดในการสร้างบิลใหม่: ' + error,
                    confirmButtonText: 'ตกลง'
                });
            }
        });
    }

    // Function to add a bill tab to the UI
    function addBillTab(saleId) {
        const tab = `<div id="tab_${saleId}" class="px-4 py-2 rounded tab-active cursor-pointer whitespace-nowrap" onclick="switchBill(${saleId})">
            บิล #${saleId} <span onclick="cancelSale(${saleId})">×</span>
        </div>`;
        $('#billTabs').append(tab);
        updateTabStyles();
    }

    // Function to switch to a different bill
    function switchBill(saleId) {
        if (saleId === currentSaleId) return;
        $.ajax({
            url: window.location.pathname,
            type: 'POST',
            data: {
                action: 'switch_sale',
                sale_id: saleId
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    currentSaleId = saleId;
                    updateTabStyles();
                    window.location.href = `?page=sale_add&sale_id=${saleId}`;
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: response.message,
                        confirmButtonText: 'ตกลง'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Switch bill error:', xhr.responseText); // Log raw response
                let errorMessage = 'เกิดข้อผิดพลาดในการสลับบิล: ' + error;
                try {
                    // พยายาม parse JSON ถ้าเป็นไปได้
                    const parsed = JSON.parse(xhr.responseText);
                    errorMessage = parsed.message || errorMessage;
                } catch (e) {
                    // ถ้า parse ไม่ได้ แสดงว่าเป็น HTML หรือข้อผิดพลาดอื่น
                    errorMessage = 'เซิร์ฟเวอร์ส่งข้อมูลไม่ถูกต้อง (อาจเป็น HTML)';
                }
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: errorMessage,
                    confirmButtonText: 'ตกลง'
                });
            }
        });
    }

    // Function to cancel a sale
    function cancelSale(saleId) {
        event.stopPropagation(); // Prevent triggering switchBill
        Swal.fire({
            title: 'ยืนยันการยกเลิก?',
            text: `คุณต้องการยกเลิกบิล #${saleId} หรือไม่?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: window.location.pathname,
                    type: 'POST',
                    data: {
                        action: 'cancel_sale',
                        sale_id: saleId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            bills = bills.filter(id => id != saleId);
                            $(`#tab_${saleId}`).remove();
                            if (currentSaleId == saleId) {
                                currentSaleId = bills.length > 0 ? bills[0] : null;
                            }
                            updateTabStyles();
                            Swal.fire({
                                toast: true,
                                position: 'center',
                                icon: 'success',
                                title: response.message,
                                showConfirmButton: false,
                                timer: 1500,
                                timerProgressBar: true
                            });
                            if (response.redirect) {
                                window.location.href = response.redirect;
                            }
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'ข้อผิดพลาด',
                                text: response.message,
                                confirmButtonText: 'ตกลง'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Cancel sale error:', xhr.responseText); // Log raw response
                        Swal.fire({
                            icon: 'error',
                            title: 'ข้อผิดพลาด',
                            text: 'เกิดข้อผิดพลาดในการยกเลิกบิล: ' + error,
                            confirmButtonText: 'ตกลง'
                        });
                    }
                });
            }
        });
    }

    // Function to update tab styles based on currentSaleId
    function updateTabStyles() {
        $('#billTabs > div').each(function() {
            const tabId = $(this).attr('id').replace('tab_', '');
            if (tabId == currentSaleId) {
                $(this).removeClass('tab-inactive').addClass('tab-active');
            } else {
                $(this).removeClass('tab-active').addClass('tab-inactive');
            }
        });
    }

    // Event listener for new sale button
    $('#new_sale').on('click', createNewSale);
</script>