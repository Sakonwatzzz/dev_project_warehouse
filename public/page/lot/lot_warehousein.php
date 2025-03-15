<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body>
    <h1 class="flex justify-center text-3xl pb-6 pt-6">Hello warehouse In</h1>
    <div class="flex justify-center items-center">
        <form method="POST" action="" class="w-[60%]">
            <div class="">
                <?php
                date_default_timezone_set('Asia/Bangkok'); // ตั้งโซนเวลาเป็นไทย
                $currentDateTime = date("Y-m-d H:i:s"); // วันที่และเวลาปัจจุบันในรูปแบบฐานข้อมูล (YYYY-MM-DD HH:MM:SS)
                $displayDateTime = date("d/m/Y H:i:s"); // แสดงวันที่สวยงาม
                ?>
                <!-- วันที่แสดงทางขวาบน -->
                <span class="absolute top-2 right-3 text-gray-900 text-xl">
                    <?= $displayDateTime ?>
                </span>
            </div>
            <div class="w-full flex justify-center items-center">

                <div
                    class="w-full flex flex-wrap space-y-3 bg-gradient-to-b  from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg p-4 border border-[#8f959b] shadow rounded-lg">
                    <!-- รหัส Lot สินค้า -->
                    <div class="w-full flex pt-10">
                        <label for="lot_id" class="w-[15%] mb-2">รหัส Lot สินค้า:</label>
                        <input type="text" name="lot_id" id="lot_id" required class="w-[85%] border rounded px-2 py-1"
                            placeholder="กรอกเลขที่ lot">
                    </div>
                    <!-- คลังสินค้า -->
                    <div class="w-full flex">
                        <label for="warehouse_id" class="w-[15%] mb-2">นำมาจากคลัง:</label>
                        <select name="warehouse_id" id="warehouse_id" required class="w-[85%] border rounded px-2 py-1">
                            <?php foreach ($warehouse_lot as $warehouse): ?>
                                <option value="<?= htmlspecialchars($warehouse['warehouse_id']) ?>">
                                    <?= htmlspecialchars($warehouse['warehouse_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- ร้าน -->
                    <div class="w-full flex">
                        <label for="store_name" class="w-[15%] mb-2">รับไปที่คลัง:</label>
                        <select name="store_id" id="store_name" required class="w-[85%] border rounded px-2 py-1">
                            <?php foreach ($store_lot as $store): ?>
                                <option value="<?= htmlspecialchars($store['store_id']) ?>">
                                    <?= htmlspecialchars($store['store_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- รายละเอียด -->
                    <div class="w-full flex">
                        <label for="lot_detail" class="w-[15%] mb-2">รายละเอียด:</label>
                        <input type="text" name="lot_detail" id="lot_detail" class="w-[85%] border rounded px-2 py-1"
                            placeholder="รายละเอียด">
                    </div>


                    <!-- ผู้ส่งมอบ -->
                    <div class="w-full flex">
                        <label for="supplier_id" class="w-[15%] mb-2">รับมาจากผู้ส่งมอบ:</label>
                        <select name="supplier_id" id="supplier_id" class="w-[85%] border rounded px-2 py-1">
                            <?php foreach ($supplier_lot as $supplier): ?>
                                <option value="<?= htmlspecialchars($supplier['supplier_id']) ?>">
                                    <?= htmlspecialchars($supplier['supplier_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>


                    <!-- สถาณะ -->
                    <div class="w-full flex">
                        <label for="lot_status_name" class="w-[15%] mb-2">สถานะ:</label>
                        <select name="lot_status_id" id="lot_status_name" required
                            class="w-[85%] border rounded px-2 py-1">
                            <?php foreach ($status_lot as $status): ?>
                                <option value="<?= htmlspecialchars($status['lot_status_id']) ?>">
                                    <?= htmlspecialchars($status['lot_status_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>


                    <!-- วันที่รับสินค้า -->
                    <div class="w-full flex">
                        <label for="lot_date" class="w-[15%] mb-2"></label>
                        <input type="hidden" name="lot_date" value="<?= $currentDateTime ?>">

                    </div>

                    <!-- ปุ่มบันทึก -->
                    <div class="w-full flex justify-end mt-5">
                        <button type="submit"
                            class="bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg px-4 py-1 rounded shadow-md">
                            บันทึก
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</body>

</html>