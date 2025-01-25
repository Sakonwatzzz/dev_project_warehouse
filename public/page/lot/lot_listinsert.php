<?php
require_once __DIR__ . '../../../../config/database_server.php';
ob_start();

try {
    // ดึงชื่อคลังมาแสดงผล
    $query = "SELECT lot_status_id, lot_status_name FROM lot_status";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $status_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // ดึงชื่อคลังมาแสดงผล
    $query = "SELECT warehouse_id, warehouse_name, warehouse_status FROM warehouse";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $warehouse_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // ดึงชื่อผู่ส่งมอบมาแสดงผล
    $query = "SELECT supplier_id, supplier_name FROM supplier";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $supplier_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $query = "SELECT store_id, store_name FROM store";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $store_lot = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "error" . $e->getMessage();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lot_detail = $_POST['lot_detail'];
    $lot_number = $_POST['lot_number'];
    $lot_date = $_POST['lot_date'];
    $lot_status_id = $_POST['lot_status_id'];
    $warehouse_id = $_POST['warehouse_id'];
    $store_id = $_POST['store_id'];
    $supplier_id = $_POST['supplier_id'];

    $stmt = $pdo->prepare('INSERT INTO lot (lot_number, lot_date, lot_status_id, warehouse_id, store_id, supplier_id) 
    VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$lot_detail, $lot_number, $lot_date, $lot_status_id, $warehouse_id, $supplier_id]);

    $lot_id = $pdo->lastInsertId();
    header("Location: index.php?page=lot_detail&tabel=lot&id=$lot_id");
    exit;
}

// $query = "SELECT
// 	lot_status.lot_status_id, 
// 	lot_status.lot_status_name, 
// 	supplier.supplier_id, 
// 	supplier.supplier_name, 
// 	warehouse.warehouse_name,
// 	warehouse.warehouse_status,
// 	warehouse.warehouse_id, 
// 	store.store_id, 
// 	store.store_name, 
// 	lot.lot_id, 
// 	lot.lot_detail, 
// 	lot.lot_date, 
// 	lot.lot_status_id, 
// 	lot.warehouse_id, 
// 	lot.store_id
// FROM
// 	lot
// 	LEFT JOIN lot_status ON lot.lot_status_id = lot_status.lot_status_id
//     LEFT JOIN supplier ON lot.supplier_id = supplier.supplier_id
//     LEFT JOIN warehouse ON lot.warehouse_id = warehouse.warehouse_id
//     LEFT JOIN store ON lot.store_id = store.store_id";


ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lot_list_insert</title>
</head>

<body>
    <h1 class="mx-11 my-8 text-4xl font-bold">รอบของสินค้า</h1>
    <div class="flex">
        <div class="relative w-full h-[500px] mx-11 flex">
            <!-- ส่วนแรก (ด้านซ้าย) -->
            <div class="w-[30%] border border-[#7a7a7a] rounded-lg bg-[#cbdbe4]">
                <h2 class="text-2xl px-4 py-2">คลังสินค้า</h2>
                <table class="w-full">
                    <thead class="">
                        <tr>
                            <th
                                class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                ชื่อคลังสินค้า</th>
                            <th
                                class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                สถานะ</th>
                            <th
                                class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                                การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warehouse_lot as $warehouse_lot_list): ?>
                            <tr class="hover:bg-gray-100 transition">
                                <!-- ชื่อคลังสินค้า -->
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300">
                                    <?php echo $warehouse_lot_list['warehouse_name']; ?>
                                </td>
                                <!-- สถานะคลัง -->
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300">
                                    <?php echo $warehouse_lot_list['warehouse_status']; ?>
                                </td>
                                <!-- การจัดการ -->
                                <td class="bg-white text-gray-800 px-3 py-2 border border-gray-300 text-center">
                                    <!-- ปุ่มแก้ไข -->
                                    <button onclick="enableEdit(this)" class="edit-button 
                            bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 
                            text-white 
                            text-lg 
                            border border-yellow-700 
                            border-t-white 
                            border-l-white 
                            px-2 
                            py-1 
                            rounded 
                            shadow-md 
                            hover:bg-[#FFF9C4] 
                            active:border-[#FFF176] 
                            active:border-b-white 
                            active:border-r-white 
                            active:shadow-inner
                            ">
                                        แก้ไข
                                    </button>
                                    <!-- ปุ่มลบ -->
                                    <button
                                        class="delete-button bg-red-500 text-white px-4 py-2 rounded-md shadow hover:bg-red-600 transition">
                                        <a href="?page=warehouse_list&id=<?php echo $warehouse_lot_list['warehouse_id']; ?>"
                                            onclick="return confirm('ยืนยันการลบข้อมูล?')">
                                            ลบ
                                        </a>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>
            </div>

            <!-- ส่วนที่สอง (ด้านขวา) -->
            <form method="POST" action="">

            <div class="w-[70%] flex justify-center items-center">
                <div
                    class="flex flex-wrap space-y-3 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg p-- border border-[#8f959b] shadow rounded-lg">
                    <!-- <div class="w-full md:w-1/2 px-2">
                        <label class="w-[110px] text-base mr-4" for="lot_id">รหัสล็อตสินค้า:</label>
                        <p  class="border px-2 py-1 w-full rounded" type="text"
                            id="lot_detail" type="id" id="lot_id" name="lot_id" value="<?php echo $lot['lot_id']; ?>"
                            >
                    </div> -->
                    <!-- Input สำหรับเลขที่ Lot -->
                    <div>
            <label for="lot_number">เลขที่ Lot สินค้า:</label>
            <input type="text" name="lot_number" id="lot_number" placeholder="กรอกเลขที่ lot" required>
        </div>
                    <!-- Dropdown สำหรับคลังสินค้า -->
                    <div>
            <label for="warehouse_id">คลังสินค้า:</label>
            <select name="warehouse_id" id="warehouse_id" required>
                <?php foreach ($warehouse_lot as $warehouse): ?>
                    <option value="<?= $warehouse['warehouse_id'] ?>"><?= $warehouse['warehouse_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="supplier_id">ผู้ส่งมอบ:</label>
            <select name="supplier_id" id="supplier_id" required>
                <?php foreach ($supplier_lot as $supplier): ?>
                    <option value="<?= $supplier['supplier_id'] ?>"><?= $supplier['supplier_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

                    <!-- วันที่รับสินค้า -->
                    <div class="w-full px-2">
                        <label for="lot_product_date" class="text-[20px] pb-2 block">วันที่รับสินค้า:</label>
                        <input type="date" id="lot_product_date" name="lot_product_date" value="<?= date('Y-m-d') ?>"
                            class="border px-2 py-1 w-full rounded" readonly>
                    </div>
                    <div class="flex justify-end mt-5 gap-2">
                        <button class="
                            bg-gradient-to-b from-green-300 via-green-500 to-green-700 text-white text-lg border border-green-700 
                            border-t-white 
                            border-l-white 
                            px-4 
                            py-1 
                            rounded 
                            shadow-md 
                            hover:bg-[#E8F5E9] 
                            active:border-[#A5D6A7] 
                            active:border-b-white 
                            active:border-r-white 
                                active:shadow-inner
                            " type="submit" value="เพิ่ม">บันทึก</button>
                        <!--  -->
                    </div>
                </div>
            </div>
            </form>

        </div>

    </div>
    <!-- <div class="grid grid-cols-5 gap-2 py-4 ">
                    <div class="p-2 bg-[#7cfe7f] border border-green-600 rounded-lg">
                        <div class="flex space-x-2 my-2 px-1">
                            <p class="flex items-center justify-center">2024/01/22</p>
                            <div class="flex items-center w-[70px] px-2 py-1 border border-[#426e42] bg-[#a5e5a5] rounded-sm">
                                <img src="../image/warehouse.png" alt="warehouse" class="w-6 h-6">
                                <p class="ml-2">
                                    1
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2 items-center justify-center">
                            <p class="bg-pink-400 px-5">1</p>
                            <p class="bg-pink-400 px-5">2</p>
                            <p class="bg-pink-400 px-5">3</p>
                        </div>
                    </div>
                    <div class="p-2 bg-[#7cfe7f] border border-green-600 rounded-lg">
                        <div class="flex space-x-2 my-2 px-1 ">
                            <p class="flex items-center justify-center">2024/01/22</p>
                            <div class="flex items-center w-[70px] px-2 py-1 border border-[#426e42] bg-[#a5e5a5] rounded-sm">
                                <img src="../image/warehouse.png" alt="warehouse" class="w-6 h-6">
                                <p class="ml-2">
                                    1
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2 items-center justify-center">
                            <p class="bg-pink-400 px-5">1</p>
                            <p class="bg-pink-400 px-5">2</p>
                            <p class="bg-pink-400 px-5">3</p>
                        </div>
                    </div>
                    <div class="p-2 bg-[#fb7d7e] border border-[#bd5959] rounded-lg">
                        <div class="flex space-x-2 my-2 px-1 ">
                            <p class="flex items-center justify-center">2024/01/22</p>
                            <div class="flex items-center w-[70px] px-2 py-1 border border-[#bd5959] bg-[#fcafaf] rounded-sm">
                                <img src="../image/warehouse.png" alt="warehouse" class="w-6 h-6">
                                <p class="ml-2">
                                    2
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2 items-center justify-center">
                            <p class="bg-green-300 px-5">1</p>
                            <p class="bg-green-300 px-5">2</p>
                            <p class="bg-green-300 px-5">3</p>
                        </div>
                    </div>
                    <div class="p-2 bg-[#fb7d7e] border border-[#bd5959] rounded-lg">
                        <div class="flex space-x-2 my-2 px-1 ">
                            <p class="flex items-center justify-center">2024/01/22</p>
                            <div class="flex items-center w-[70px] px-2 py-1 border border-[#bd5959] bg-[#fcafaf] rounded-sm">
                                <img src="../image/warehouse.png" alt="warehouse" class="w-6 h-6">
                                <p class="ml-2">
                                    2
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2 items-center justify-center">
                            <p class="bg-green-300 px-5">1</p>
                            <p class="bg-green-300 px-5">2</p>
                            <p class="bg-green-300 px-5">3</p>
                        </div> -->

</body>

</html>