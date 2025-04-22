<?php

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการขายสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">จัดการการขายสินค้า</h1>

        <!-- ส่วนค้นหาและกรอง -->
        <div class="mb-4 flex space-x-4">
            <input type="text" id="search" placeholder="ค้นหาด้วยรหัสการขายหรือชื่อสมาชิก"
                class="border p-2 rounded w-1/3">
            <input type="date" id="dateFilter" class="border p-2 rounded">
            <select id="statusFilter" class="border p-2 rounded">
                <option value="">ทุกสถานะ</option>
            </select>
            <button onclick="loadSales()" class="bg-blue-500 text-white px-4 py-2 rounded">ค้นหา</button>
        </div>

        <!-- ปุ่มเพิ่มการขาย -->
        <button onclick="showAddForm()" class="bg-green-500 text-white px-4 py-2 rounded mb-4">เพิ่มการขาย</button>

        <!-- ฟอร์มเพิ่ม/แก้ไขการขาย (ซ่อนเริ่มต้น) -->
        <div id="saleForm" class="hidden bg-white p-4 rounded shadow mb-4">
            <h2 class="text-xl font-bold mb-2">เพิ่ม/แก้ไขการขาย</h2>
            <form id="formSale">
                <input type="hidden" name="sale_id" id="sale_id">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label>วันที่ขาย</label>
                        <input type="date" name="sale_date" id="sale_date" class="border p-2 w-full" required>
                    </div>
                    <div>
                        <label>สมาชิก</label>
                        <select name="member_id" id="member_id" class="border p-2 w-full" required>
                            <option value="">เลือกสมาชิก</option>
                        </select>
                    </div>
                    <div>
                        <label>พนักงาน</label>
                        <select name="personnel_id" id="personnel_id" class="border p-2 w-full" required>
                            <option value="">เลือกพนักงาน</option>
                        </select>
                    </div>
                    <div>
                        <label>สถานะ</label>
                        <select name="sale_status_id" id="sale_status_id" class="border p-2 w-full" required>
                            <option value="">เลือกสถานะ</option>
                        </select>
                    </div>
                </div>
                <!-- รายการสินค้า -->
                <div id="productList" class="mt-4">
                    <h3 class="font-bold">รายการสินค้า</h3>
                    <div class="product-row flex space-x-2 mb-2">
                        <select name="product_id[]" class="border p-2">
                            <option value="">เลือกสินค้า</option>
                        </select>
                        <input type="number" name="sale_product_qty[]" placeholder="จำนวน" class="border p-2 w-24">
                        <input type="number" name="sale_product_price[]" placeholder="ราคา" class="border p-2 w-24">
                        <input type="number" name="sale_product_capital[]" placeholder="ต้นทุน" class="border p-2 w-24">
                        <select name="warehouse_id[]" class="border p-2">
                            <option value="">เลือกคลัง</option>
                        </select>
                        <button type="button" onclick="addProductRow()"
                            class="bg-blue-500 text-white px-2 py-1 rounded">+</button>
                    </div>
                </div>
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded mt-4">บันทึก</button>
                <button type="button" onclick="hideAddForm()"
                    class="bg-gray-500 text-white px-4 py-2 rounded mt-4">ยกเลิก</button>
            </form>
        </div>

        <!-- ตารางแสดงรายการการขาย -->
        <div class="bg-white p-4 rounded shadow">
            <table class="w-full border">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border p-2">รหัสการขาย</th>
                        <th class="border p-2">วันที่</th>
                        <th class="border p-2">สมาชิก</th>
                        <th class="border p-2">พนักงาน</th>
                        <th class="border p-2">สถานะ</th>
                        <th class="border p-2">ยอดรวม</th>
                        <th class="border p-2">การจัดการ</th>
                    </tr>
                </thead>
                <tbody id="salesTable"></tbody>
            </table>
        </div>
    </div>

</body>
<script>
    function loadDropdown(type, selector) {
        $.ajax({
            url: 'sale_list.php',
            type: 'POST',
            data: { action: 'getDropdown', type: type },
            success: function (data) {
                let options = '<option value="">เลือก</option>';
                JSON.parse(data).forEach(item => {
                    options += `<option value="${item[Object.keys(item)[0]]}">${item[Object.keys(item)[1]]}</option>`;
                });
                $(selector).html(options);
            }
        });
    }

    function showAddForm() {
        $('#saleForm').removeClass('hidden');
        $('#formSale')[0].reset();
        $('#sale_id').val('');
        $('#productList').html(getProductRow());
        loadDropdown('product', '#productList select[name="product_id[]"]');
        loadDropdown('warehouse', '#productList select[name="warehouse_id[]"]');
    }

    function hideAddForm() {
        $('#saleForm').addClass('hidden');
    }

    function getProductRow() {
        return `
                <div class="product-row flex space-x-2 mb-2">
                    <select name="product_id[]" class="border p-2">
                        <option value="">เลือกสินค้า</option>
                    </select>
                    <input type="number" name="sale_product_qty[]" placeholder="จำนวน" class="border p-2 w-24">
                    <input type="number" name="sale_product_price[]" placeholder="ราคา" class="border p-2 w-24">
                    <input type="number" name="sale_product_capital[]" placeholder="ต้นทุน" class="border p-2 w-24">
                    <select name="warehouse_id[]" class="border p-2">
                        <option value="">เลือกคลัง</option>
                    </select>
                    <button type="button" onclick="addProductRow()" class="bg-blue-500 text-white px-2 py-1 rounded">+</button>
                </div>`;
    }

    function addProductRow() {
        let row = $(getProductRow());
        $('#productList').append(row);
        loadDropdown('product', row.find('select[name="product_id[]"]'));
        loadDropdown('warehouse', row.find('select[name="warehouse_id[]"]'));
    }

    function loadSales() {
        let search = $('#search').val();
        let date = $('#dateFilter').val();
        let status = $('#statusFilter').val();

        $.ajax({
            url: 'sale_list.php',
            type: 'POST',
            data: { action: 'loadSales', search: search, date: date, status: status },
            success: function (data) {
                $('#salesTable').html(data);
            }
        });
    }

    $('#formSale').submit(function (e) {
        e.preventDefault();
        $.ajax({
            url: 'sale_list.php',
            type: 'POST',
            data: $(this).serialize() + '&action=saveSale',
            success: function (response) {
                alert('บันทึกสำเร็จ');
                hideAddForm();
                loadSales();
            }
        });
    });

    function editSale(sale_id) {
        $.ajax({
            url: 'sale_list.php',
            type: 'POST',
            data: { action: 'getSale', sale_id: sale_id },
            success: function (data) {
                let sale = JSON.parse(data);
                $('#sale_id').val(sale.sale_id);
                $('#sale_date').val(sale.sale_date.split(' ')[0]); // แยกวันที่
                $('#member_id').val(sale.member_id);
                $('#personnel_id').val(sale.personnel_id);
                $('#sale_status_id').val(sale.sale_status_id);
                $('#saleForm').removeClass('hidden');
                // หมายเหตุ: ต้องเพิ่ม logic เพื่อโหลดรายการสินค้าใน sale_product
            }
        });
    }

    // โหลด dropdown เมื่อหน้าโหลด
    loadDropdown('status', '#statusFilter');
    loadDropdown('member', '#member_id');
    loadDropdown('personnel', '#personnel_id');
    loadDropdown('status', '#sale_status_id');

    // โหลดข้อมูลการขายเมื่อหน้าโหลด
    loadSales();
</script>

</html>