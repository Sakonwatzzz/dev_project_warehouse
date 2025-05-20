$(document).ready(function () {
    // ... (โค้ดเดิมจาก stock_insert_list.js)

    // จัดการ submit ฟอร์ม
    $('#form_insert_stock').on('submit', function (event) {
        event.preventDefault(); // ป้องกันการ submit แบบปกติ

        const Toast = Swal.mixin({
            toast: true,
            position: 'top',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
                didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
            },
        });

        // รวบรวมข้อมูลจากฟอร์ม
        let formData = {
            action: 'insert_check_product',
            product_id: $('#product_id_input').val().trim(),
            warehouse_id: $('#warehouse_id').val(),
            stock_check_date: $('#stock-check-date').val() || luxon.DateTime.now().toISO(), // ใช้วันที่ปัจจุบันหากไม่มี
            stock_check_remark: $('#stock-check-remark').val() || '',
            personnel_id: '1', // สมมติว่า personnel_id มาจาก session หรือกำหนดค่าเริ่มต้น
            stock_check_actual: $('#stock_check_actual').val() || '0', // เพิ่ม input ในฟอร์ม
        };

        // ตรวจสอบข้อมูลพื้นฐาน
        if (!formData.product_id || !formData.warehouse_id) {
            Toast.fire({
                icon: 'error',
                title: 'กรุณากรอก รหัสสินค้า และ คลังสินค้า',
            });
            return;
        }

        // ดึงข้อมูลสินค้าจาก get_product.php
        $.ajax({
            url: 'page/stock/api/get_product.php',
            type: 'GET',
            data: {
                product_id: formData.product_id,
                warehouse_id: formData.warehouse_id,
                lot_id: null, // สามารถปรับตามความต้องการ
                lot_product_id: null,
            },
            dataType: 'json',
            success: function (productData) {
                if (!productData || Array.isArray(productData) && productData.length === 0) {
                    Toast.fire({
                        icon: 'error',
                        title: 'ไม่พบข้อมูลสินค้า',
                    });
                    return;
                }

                // เพิ่มข้อมูลจาก get_product.php
                formData.lot_id = productData.lot_id || '';
                formData.lot_product_id = productData.lot_product_id || '';
                formData.stock_check_system = productData.lot_product_remain || '0';

                // ส่งข้อมูลไปยัง insert_stock.php
                $.ajax({
                    url: 'page/stock/api/insert_stock.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'success') {
                            Toast.fire({
                                icon: 'success',
                                title: response.message,
                            });
                            // รีเซ็ตฟอร์ม
                            $('#form_insert_stock')[0].reset();
                            $('#product_id_input').focus();
                            // รีเฟรชตาราง
                            loadStockCheck();
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message,
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        Toast.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาดในการบันทึก: ' + error,
                        });
                    }
                });
            },
            error: function () {
                Toast.fire({
                    icon: 'error',
                    title: 'ไม่สามารถดึงข้อมูลสินค้าได้',
                });
            }
        });
    });

    // เพิ่ม input สำหรับ stock_check_actual หากยังไม่มี
    $('#product_id_input').on('input', function () {
        let productId = $(this).val().trim();
        if (productId.includes('*')) {
            let [id, quantity] = productId.split('*');
            $('#product_id_input').val(id);
            $('#stock_check_actual').val(quantity || '0'); // อัปเดต stock_check_actual
        }
    });
});


$(document).ready(function () {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });

    // เริ่มต้นโดย disable ปุ่ม submit
    $('#btn-submit').prop('disabled', true);

    // ตัวแปรสำหรับตรวจสอบสถานะการประมวลผล
    let isProcessing = false;

    // ฟังก์ชันเปลี่ยนสีพื้นหลัง input
    function changeInputBackground(event) {
        const product_id_input = $('#product_id_input');
        if (event.keyCode === 46) { // Delete
            product_id_input.removeClass('bg-white bg-red-500 bg-green-600');
        }
        if (event.keyCode === 45) { // - (ลบ)
            product_id_input.removeClass('bg-white bg-green-600').addClass('bg-red-500');
        }
        if (event.keyCode === 43) { // + (เพิ่ม)
            product_id_input.removeClass('bg-white bg-red-500').addClass('bg-green-600');
        }
    }

    // ฟังก์ชันรีเซ็ตสีพื้นหลัง
    function resetInputBackground() {
        $('#product_id_input').removeClass('bg-red-500 bg-green-600').addClass('bg-white');
    }

    // จัดการ keydown และ focus สำหรับ product_id_input
    $('#product_id_input').on('keydown', changeInputBackground).on('focus', resetInputBackground);

    // จัดการรูปแบบ รหัส*จำนวน
    $('#product_id_input').on('input', function () {
        let input = $(this).val().trim();
        if (input.includes('*')) {
            let [id, quantity] = input.split('*');
            $(this).val(id.trim());
            $('#stock_check_actual').val(quantity.trim() || '0');
        }
    });

    // ตรวจสอบข้อมูลฟอร์มเพื่อ enable/disable ปุ่ม submit
    function validateForm() {
        const product_id = $('#product_id_input').val().trim();
        const warehouse_id = $('#warehouse_id').val().trim();
        const stock_check_actual = $('#stock_check_actual').val().trim();
        const isValid = product_id && warehouse_id && stock_check_actual && /^\d+$/.test(product_id) && /^\d+$/.test(stock_check_actual);
        $('#btn-submit').prop('disabled', !isValid);
    }

    // ตรวจสอบฟอร์มเมื่อมีการเปลี่ยนแปลง input
    $('#product_id_input, #warehouse_id, #stock_check_actual').on('input change', validateForm);

    // จัดการ submit ฟอร์ม
    $('#form_insert_stock').on('submit', function (event) {
        event.preventDefault();
        if (isProcessing) return;
        isProcessing = true;

        // แสดง spinner
        $('#btn-submit').find('.spinner').removeClass('hidden').end().find('.text').addClass('hidden');

        // รวบรวมข้อมูลจากฟอร์ม
        let formData = {
            action: 'insert_check_product',
            product_id: $('#product_id_input').val().trim(),
            warehouse_id: $('#warehouse_id').val().trim(),
            stock_check_actual: $('#stock_check_actual').val().trim(),
            stock_check_date: $('#stock-check-date').val() || luxon.DateTime.now().toFormat('yyyy-MM-dd HH:mm:ss'),
            stock_check_remark: $('#stock-check-remark').val().trim(),
            personnel_id: $('#personnel_id').val() || '1',
        };

        // ตรวจสอบข้อมูลพื้นฐาน
        if (!formData.product_id || !formData.warehouse_id || !formData.stock_check_actual) {
            Toast.fire({
                icon: 'warning',
                title: 'กรุณากรอก รหัสสินค้า, คลังสินค้า, และจำนวนที่นับได้จริง',
            });
            $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
            isProcessing = false;
            return;
        }

        if (!/^\d+$/.test(formData.product_id) || !/^\d+$/.test(formData.stock_check_actual)) {
            Toast.fire({
                icon: 'error',
                title: 'รหัสสินค้าและจำนวนต้องเป็นตัวเลข',
            });
            $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
            isProcessing = false;
            return;
        }

        // ดึงข้อมูลสินค้าจาก get_product.php
        $.ajax({
            url: 'page/stock/api/get_product.php',
            type: 'GET',
            data: {
                product_id: formData.product_id,
                warehouse_id: formData.warehouse_id,
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            success: function (productData) {
                if (!productData || (Array.isArray(productData) && productData.length === 0)) {
                    Toast.fire({
                        icon: 'error',
                        title: 'ไม่พบข้อมูลสินค้าในคลังนี้',
                    });
                    $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
                    isProcessing = false;
                    return;
                }

                // ใช้ record แรก
                let product = productData[0];
                formData.lot_id = product.lot_id || '';
                formData.lot_product_id = product.lot_product_id || '';
                formData.stock_check_system = product.lot_product_remain || '0';

                // ส่งข้อมูลไป insert_stock.php
                $.ajax({
                    url: 'page/stock/api/insert_stock.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    success: function (response) {
                        if (response.status === 'success') {
                            Toast.fire({
                                icon: 'success',
                                title: response.message,
                            });
                            $('#form_insert_stock')[0].reset();
                            $('#product_id_input').focus();
                            $('#product_info').addClass('hidden').empty();
                            loadStockCheck();
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message,
                            });
                        }
                        $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
                        isProcessing = false;
                    },
                    error: function (xhr, status, error) {
                        Toast.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาดในการบันทึก: ' + error,
                        });
                        $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
                        isProcessing = false;
                    },
                });
            },
            error: function (xhr, status, error) {
                Toast.fire({
                    icon: 'error',
                    title: 'ไม่สามารถดึงข้อมูลสินค้าได้: ' + error,
                });
                $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
                isProcessing = false;
            },
        });
    });

    // ฟังก์ชันโหลดข้อมูลตาราง
    function loadStockCheck() {
        $.ajax({
            url: 'page/stock/api/get_product.php',
            type: 'GET',
            data: { warehouse_id: $('#warehouse_id').val().trim() },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            success: function (data) {
                let tbody = $('#stock_check_table');
                if (tbody.length === 0) {
                    console.error('Element #stock_check_table not found in DOM');
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: 'ไม่พบตาราง #stock_check_table ในหน้าเว็บ',
                    });
                    return;
                }
                tbody.empty();
                if (!data || data.length === 0) {
                    console.warn('No data returned from get_product.php');
                    tbody.append(
                        '<tr><td colspan="12" class="text-center py-4 text-gray-500">ไม่พบรายการตรวจสอบสต๊อกสินค้า</td></tr>'
                    );
                    return;
                }
                $.each(data, function (index, item) {
                    let row = `
                        <tr>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                <input type="checkbox" class="stock-list-select rounded text-blue-500 focus:ring-blue-400"
                                    data-product-id="${DOMPurify.sanitize(item.product_id)}"
                                    data-lot-id="${DOMPurify.sanitize(item.lot_id || '')}"
                                    data-lot-product-id="${DOMPurify.sanitize(item.lot_product_id || '')}"
                                    data-system-quantity="${item.lot_product_remain || 0}">
                            </td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.stock_check_id || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.stock_check_date || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.warehouse_name || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.product_id || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.product_name || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.lot_product_remain || '0'}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.stock_check_actual || '0'}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.personnel_name || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.check_status_name || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.stock_check_remark || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm text-blue-600">
                                <a href="edit_stock_check.php?id=${DOMPurify.sanitize(item.stock_check_id)}">แก้ไข</a>
                            </td>
                        </tr>`;
                    tbody.append(row);
                });
            },
            error: function (xhr, status, error) {
                console.error('Ajax error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'ไม่สามารถดึงข้อมูลจาก get_product.php: ' + error,
                });
            },
        });
    }

    // เรียก loadStockCheck ครั้งแรกและทุก 30 วินาที
    loadStockCheck();
    setInterval(loadStockCheck, 30000);
});





$(document).ready(function () {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });

    // ค้นหาสินค้าเมื่อพิมพ์ใน #product_id_input
    $('#product_id_input').on('input', function () {
        const query = $(this).val().trim();
        const warehouse_id = $('#warehouse_id').val().trim();
        if (query.length < 2) {
            $('#barcode_suggestions').addClass('hidden').empty();
            $('#product_info').addClass('hidden').empty();
            return;
        }
        $.ajax({
            url: 'page/stock/api/api_search_product.php',
            method: 'GET',
            data: { query: query, warehouse_id: warehouse_id },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            success: function (response) {
                const products = response;
                const suggestions = $('#barcode_suggestions').removeClass('hidden').empty();
                if (!products || products.length === 0) {
                    suggestions.append('<div class="p-2 text-gray-500">ไม่พบสินค้า</div>');
                    return;
                }
                products.forEach((product) => {
                    suggestions.append(`
                        <div class="p-2 hover:bg-gray-100 cursor-pointer" 
                             data-product-id="${DOMPurify.sanitize(product.product_id)}" 
                             data-lot-id="${DOMPurify.sanitize(product.lot_id)}" 
                             data-lot-product-id="${DOMPurify.sanitize(product.lot_product_id)}">
                            ${DOMPurify.sanitize(product.product_id)} - ${DOMPurify.sanitize(product.product_name)} 
                            (ล็อต: ${DOMPurify.sanitize(product.lot_name || 'N/A')}, สต็อก: ${product.lot_product_remain})
                        </div>
                    `);
                });
                suggestions.find('div').on('click', function () {
                    const product_id = $(this).data('product-id');
                    const lot_id = $(this).data('lot-id');
                    const lot_product_id = $(this).data('lot-product-id');
                    $('#product_id_input').val(product_id);
                    suggestions.addClass('hidden').empty();
                    fetchProductInfo(product_id, lot_id, lot_product_id);
                });
            },
            error: function () {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'ไม่สามารถค้นหาสินค้าได้',
                });
            },
        });
    });

    // แสดงข้อมูลสินค้าใน #product_info
    function fetchProductInfo(product_id, lot_id, lot_product_id) {
        $.ajax({
            url: 'page/stock/api/get_product.php',
            method: 'GET',
            data: { product_id, lot_id, lot_product_id },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            success: function (product) {
                if (product && product.length > 0) {
                    const item = product[0];
                    $('#product_info').removeClass('hidden').html(`
                        <div class="bg-white rounded-lg p-4 shadow">
                            <p><strong>สินค้า:</strong> ${DOMPurify.sanitize(item.product_name)}</p>
                            <p><strong>ล็อต:</strong> ${DOMPurify.sanitize(item.lot_name || 'N/A')}</p>
                            <p><strong>จำนวนสินค้าในระบบ:</strong> ${item.lot_product_remain}</p>
                        </div>
                    `);
                    // อัปเดต input ในฟอร์มหลัก
                    $('#stock_check_actual').val(item.lot_product_remain);
                } else {
                    $('#product_info').addClass('hidden').empty();
                    Swal.fire({
                        icon: 'warning',
                        title: 'ข้อผิดพลาด',
                        text: 'ไม่พบสินค้า',
                    });
                }
            },
            error: function () {
                $('#product_info').addClass('hidden').empty();
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'เกิดข้อผิดพลาดในการดึงข้อมูลสินค้า',
                });
            },
        });
    }
});



$(document).ready(function () {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top',
        showConfirmButton: false,
        timer: 1500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        },
    });

    // เริ่มต้นโดย disable ปุ่ม submit
    $('#btn-submit').prop('disabled', true);

    // ตัวแปรสำหรับตรวจสอบสถานะการประมวลผล
    let isProcessing = false;

    // ฟังก์ชันเปลี่ยนสีพื้นหลัง input
    function changeInputBackground(event) {
        const product_id_input = $('#product_id_input');
        if (event.keyCode === 46) { // Delete
            product_id_input.removeClass('bg-white bg-red-500 bg-green-600');
        }
        if (event.keyCode === 45) { // - (ลบ)
            product_id_input.removeClass('bg-white bg-green-600').addClass('bg-red-500');
        }
        if (event.keyCode === 43) { // + (เพิ่ม)
            product_id_input.removeClass('bg-white bg-red-500').addClass('bg-green-600');
        }
    }

    // ฟังก์ชันรีเซ็ตสีพื้นหลัง
    function resetInputBackground() {
        $('#product_id_input').removeClass('bg-red-500 bg-green-600').addClass('bg-white');
    }

    // จัดการ keydown และ focus สำหรับ product_id_input
    $('#product_id_input').on('keydown', changeInputBackground).on('focus', resetInputBackground);

    // จัดการรูปแบบ รหัส*จำนวน
    $('#product_id_input').on('input', function () {
        let input = $(this).val().trim();
        if (input.includes('*')) {
            let [id, quantity] = input.split('*');
            $(this).val(id.trim());
            $('#stock_check_actual').val(quantity.trim() || '0');
        }
    });

    $("#product_id_input")
        .on("keydown", changeInputBackgroud)
        .on("focus", resetInputBackgroud)
        .on("keypress", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                if (!$("#btn-submit").prop("disabled")) {
                    $("#form_insert_stock").submit();
                }
            }
        })
        .on("input", function () {
            let input = $(this).val().trim();
            if (input.includes("*")) {
                let [stock_check_id, stock_check_actual] = input.split("*");
                let isDelete = stock_check_id.startsWith("-");
                stock_check_id = stock_check_id.replace(/^-/, "").trim();
                $(this).val(stock_check_id);
                stock_check_actual = stock_check_actual.trim() || "0";
                if (isDelete) {
                    stock_check_actual = `-${stock_check_actual}`;
                }
                $("#stock_check_actual").val(stock_check_actual);
                const product_id_input = document.getElementById("product_id_input");
                product_id_input.classList.remove("bg-white", "bg-red-500", "bg-green-600");
                product_id_input.classList.add(isDelete ? "bg-red-500" : "bg-green-600");
            }
        });
    // ตรวจสอบข้อมูลฟอร์มเพื่อ enable/disable ปุ่ม submit
 function validateForm() {
        const product_id = $('#product_id_input').val().trim();
        const warehouse_id = $('#warehouse_id').val().trim();
        const stock_check_actual = $('#stock_check_actual').val().trim();
        const stock_check_date = $('#stock-check-date').val().trim();
        const personnel_id = $('#personnel_id').val().trim() || '1'; // Default to '1' if no session

        // ตรวจสอบว่าไม่ว่างและเป็นตัวเลขที่ถูกต้อง
        const isValid =
            product_id &&
            warehouse_id &&
            stock_check_actual &
            stock_check_date &&
            personnel_id &&
            isNumeric(product_id) &&
            isNumeric(stock_check_actual) &&
            parseFloat(stock_check_actual) >= 0;

        $('#btn-submit').prop('disabled', !isValid);
    }

    // ตรวจสอบฟอร์มเมื่อมีการเปลี่ยนแปลง input
    $('#product_id_input, #warehouse_id, #stock_check_actual').on('input change', validateForm);

    // จัดการ submit ฟอร์ม
    $('#form_insert_stock').on('submit', function (event) {
        event.preventDefault();
        if (isProcessing) return;
        isProcessing = true;

        // แสดง spinner
        $('#btn-submit').find('.spinner').removeClass('hidden').end().find('.text').addClass('hidden');

        // รวบรวมข้อมูลจากฟอร์ม
        let formData = {
            action: 'insert_check_product',
            product_id: $('#product_id_input').val().trim(),
            warehouse_id: $('#warehouse_id').val().trim(),
            stock_check_actual: $('#stock_check_actual').val().trim(),
            stock_check_date: $('#stock-check-date').val() || luxon.DateTime.now().toFormat('yyyy-MM-dd HH:mm:ss'),
            stock_check_remark: $('#stock-check-remark').val().trim(),
            personnel_id: $('#personnel_id').val() || '1',
        };

        // ตรวจสอบข้อมูลพื้นฐาน
        if (!formData.product_id || !formData.warehouse_id || !formData.stock_check_actual) {
            Toast.fire({
                icon: 'warning',
                title: 'กรุณากรอก รหัสสินค้า, คลังสินค้า, และจำนวนที่นับได้จริง',
            });
            $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
            isProcessing = false;
            return;
        }

        if (!/^\d+$/.test(formData.product_id) || !/^\d+$/.test(formData.stock_check_actual)) {
            Toast.fire({
                icon: 'error',
                title: 'รหัสสินค้าและจำนวนต้องเป็นตัวเลข',
            });
            $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
            isProcessing = false;
            return;
        }

        // ดึงข้อมูลสินค้าจาก get_product.php
        $.ajax({
            url: 'page/stock/api/get_product.php',
            type: 'GET',
            data: {
                product_id: formData.product_id,
                warehouse_id: formData.warehouse_id,
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            success: function (productData) {
                if (!productData || (Array.isArray(productData) && productData.length === 0)) {
                    Toast.fire({
                        icon: 'error',
                        title: 'ไม่พบข้อมูลสินค้าในคลังนี้',
                    });
                    $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
                    isProcessing = false;
                    return;
                }

                // ใช้ record แรก
                let product = productData[0];
                formData.lot_id = product.lot_id || '';
                formData.lot_product_id = product.lot_product_id || '';
                formData.stock_check_system = product.lot_product_remain || '0';

                // ส่งข้อมูลไป insert_stock.php
                $.ajax({
                    url: 'page/stock/api/insert_stock.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    success: function (response) {
                        if (response.status === 'success') {
                            Toast.fire({
                                icon: 'success',
                                title: response.message,
                            });
                            $('#form_insert_stock')[0].reset();
                            $('#product_id_input').focus();
                            $('#product_info').addClass('hidden').empty();
                            loadStockCheck();
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: response.message,
                            });
                        }
                        $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
                        isProcessing = false;
                    },
                    error: function (xhr, status, error) {
                        Toast.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาดในการบันทึก: ' + error,
                        });
                        $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
                        isProcessing = false;
                    },
                });
            },
            error: function (xhr, status, error) {
                Toast.fire({
                    icon: 'error',
                    title: 'ไม่สามารถดึงข้อมูลสินค้าได้: ' + error,
                });
                $('#btn-submit').find('.spinner').addClass('hidden').end().find('.text').removeClass('hidden');
                isProcessing = false;
            },
        });
    });

    // ฟังก์ชันโหลดข้อมูลตาราง
    function loadStockCheck() {
        $.ajax({
            url: 'page/stock/api/get_product.php',
            type: 'GET',
            data: { warehouse_id: $('#warehouse_id').val().trim() },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            success: function (data) {
                let tbody = $('#stock_check_table');
                if (tbody.length === 0) {
                    console.error('Element #stock_check_table not found in DOM');
                    Swal.fire({
                        icon: 'error',
                        title: 'ข้อผิดพลาด',
                        text: 'ไม่พบตาราง #stock_check_table ในหน้าเว็บ',
                    });
                    return;
                }
                tbody.empty();
                if (!data || data.length === 0) {
                    console.warn('No data returned from get_product.php');
                    tbody.append(
                        '<tr><td colspan="12" class="text-center py-4 text-gray-500">ไม่พบรายการตรวจสอบสต๊อกสินค้า</td></tr>'
                    );
                    return;
                }
                $.each(data, function (index, item) {
                    let row = `
                        <tr>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">
                                <input type="checkbox" class="stock-list-select rounded text-blue-500 focus:ring-blue-400"
                                    data-product-id="${DOMPurify.sanitize(item.product_id)}"
                                    data-lot-id="${DOMPurify.sanitize(item.lot_id || '')}"
                                    data-lot-product-id="${DOMPurify.sanitize(item.lot_product_id || '')}"
                                    data-system-quantity="${item.lot_product_remain || 0}">
                            </td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.stock_check_id || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.stock_check_date || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.warehouse_name || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.product_id || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.product_name || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.lot_product_remain || '0'}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${item.stock_check_actual || '0'}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.personnel_name || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.check_status_name || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm">${DOMPurify.sanitize(item.stock_check_remark || '-')}</td>
                            <td class="px-3 sm:px-6 py-2 border text-left text-xs sm:text-sm text-blue-600">
                                <a href="edit_stock_check.php?id=${DOMPurify.sanitize(item.stock_check_id)}">แก้ไข</a>
                            </td>
                        </tr>`;
                    tbody.append(row);
                });
            },
            error: function (xhr, status, error) {
                console.error('Ajax error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'ไม่สามารถดึงข้อมูลจาก get_product.php: ' + error,
                });
            },
        });
    }

    // เรียก loadStockCheck ครั้งแรกและทุก 30 วินาที
    loadStockCheck();
    // setInterval(loadStockCheck, 30000); // คอมเมนต์ไว้ตามโค้ดต้นฉบับ
});