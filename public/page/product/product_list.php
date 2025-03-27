<?php
require_once __DIR__ . '../../../../config/database_server.php';

// จำนวนข้อมูลทั้งหมดในตาราง
$query = "SELECT COUNT(*) as total_count FROM product";
$stmt = $pdo->query($query);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$count = $row['total_count'];

// ตรวจสอบโหมดการแสดงผล
$incomplete_mode = isset($_GET['mode']) && $_GET['mode'] === 'incomplete';


// Get selected filters from GET request
$selected_types = isset($_GET['product_type']) ? explode(',', $_GET['product_type']) : [];
$selected_brands = isset($_GET['product_brand']) ? explode(',', $_GET['product_brand']) : [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['product_id'];
    $name = $_POST['name'];
    $price1 = $_POST['price_1'];
    $price2 = $_POST['price_2'];
    $price3 = $_POST['price_3'];
    $price4 = $_POST['price_4'];
    $capital = $_POST['capital'];
    $type = $_POST['typename'];
    $brand = $_POST['typebrandname'];

    $stmt = $pdo->prepare("
        UPDATE product 
        SET product_name = ?, product_price1 = ?, product_price2 = ?, 
            product_price3 = ?, product_price4 = ?, product_capital = ?, product_type_id = ?, 
            product_brand_id = ? 
        WHERE product_id = ?
    ");
    $stmt->execute([$name, $price1, $price2, $price3, $price4, $capital, $type, $brand, $id]);

    // รีไดเรกต์ไปที่หน้ารายการหลังอัปเดตสำเร็จ
    header("Location: /?page=product_detail&tabel=product&id=$id");
    exit;
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM product WHERE product_id = ?");
    $stmt->execute([$id]);

    // ตรวจสอบว่ามีข้อมูลเหลือในตารางหรือไม่
    $stmt = $pdo->query("SELECT COUNT(*) FROM lot");
    $count = $stmt->fetchColumn();

    // ถ้าตารางว่างให้รีเซ็ต AUTO_INCREMENT
    if ($count == 0) {
        $pdo->query("ALTER TABLE lot AUTO_INCREMENT = 1");
    }

    header("Location: /?page=product_list&delete_success");
    exit;
}

// การแบ่งหน้า
$records_per_page = 10;
$page = isset($_GET['page_num']) ? (int) $_GET['page_num'] : 1;
$offset = ($page - 1) * $records_per_page;

// คำสั่ง SQL สำหรับโหมดแสดงข้อมูลไม่ครบ
$where_condition = "WHERE 1=1"; // เริ่มต้นเงื่อนไขให้ปลอดภัย

if ($incomplete_mode) {
    $where_condition .= " AND (
        product.product_name = '' OR product.product_name IS NULL OR 
        product.product_price1 = '' OR product.product_price1 IS NULL OR 
        product.product_price2 = '' OR product.product_price2 IS NULL OR 
        product.product_price3 = '' OR product.product_price3 IS NULL OR 
        product.product_price4 = '' OR product.product_price4 IS NULL OR 
        product.product_capital = '' OR product.product_capital IS NULL OR 
        product.product_type_id = '' OR product.product_type_id IS NULL OR 
        product.product_brand_id = '' OR product.product_brand_id IS NULL
    )";
}

// กรองประเภทสินค้า
if (!empty($selected_types)) {
    $type_placeholders = implode(' AND product.product_type_id = ', array_map(function ($i) { return ":type$i"; }, range(1, count($selected_types))));
    $where_condition .= " AND product.product_type_id = $type_placeholders";
}

// กรองแบรนด์สินค้า
if (!empty($selected_brands)) {
    $brand_placeholders = implode(' AND product.product_brand_id = ', array_map(function ($i) { return ":brand$i"; }, range(1, count($selected_brands))));
    $where_condition .= " AND product.product_brand_id = $brand_placeholders";
}

// การเลือกข้อมูลในตาราง product
$query = "SELECT 
    product.product_id, 
    product.product_name, 
    product.product_price1, 
    product.product_price2, 
    product.product_price3, 
    product.product_price4, 
    product.product_capital, 
    product.product_type_id, 
    product.product_brand_id,
    product_brand.product_brand_name, 
    product_type.product_type_name
FROM product
INNER JOIN product_brand ON product.product_brand_id = product_brand.product_brand_id
INNER JOIN product_type ON product.product_type_id = product_type.product_type_id
$where_condition
LIMIT :offset, :records_per_page"; // ใช้ named parameters :offset และ :records_per_page


$stmt = $pdo->prepare($query);

// Bind Parameters สำหรับ selected types
$paramIndex = 1;
if (!empty($selected_types)) {
    foreach ($selected_types as $type) {
        $stmt->bindValue(':type' . $paramIndex, (int) $type, PDO::PARAM_INT);
        $paramIndex++;
    }
}

// Bind Parameters สำหรับ selected brands
if (!empty($selected_brands)) {
    foreach ($selected_brands as $brand) {
        $stmt->bindValue(':brand' . $paramIndex, (int) $brand, PDO::PARAM_INT);
        $paramIndex++;
    }
}

// Bind for offset and records_per_page
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':records_per_page', $records_per_page, PDO::PARAM_INT);

// Execute query
if ($stmt->execute()) {
    $resultsproduct = $stmt;
} else {
    echo "Error executing query.";
}


// นับจำนวนรายการทั้งหมด
try {
    $total_query = "SELECT COUNT(*) FROM product $where_condition";
    $total_result = $pdo->query($total_query);
    $total_records = $total_result->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
$typeResults = $pdo->query("SELECT product_type_id, product_type_name FROM product_type");
$brandResults = $pdo->query("SELECT product_brand_id, product_brand_name FROM product_brand");

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="../../output.css" rel="stylesheet">
    <style>
        .title {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .count {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .listcount {
            font-size: 16px;
            text-align: right;
            color: #000;
            padding: 4px 10px 4px 10px;
            border: 1px solid gray;
            border-radius: 5px;
            background-color: #fff;
            display: inline-block;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            width: 70px;
        }

        .incompletecount {
            font-size: 16px;
            text-align: right;
            color: #fff;
            padding: 4px 10px 4px 10px;
            border: 1px solid #c53030;
            border-radius: 5px;
            background-color: #f56565;
            display: inline-block;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            width: 70px;
        }

        .notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #fff;
            color: #000;
            border: 2px solid #A0A0A0;
            border-radius: 5px;
            font-family: "Tahoma", "Verdana", sans-serif;
            width: 400px;
            height: 120px;
            box-shadow: 5px 5px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
        }

        .notification-header {
            background-color: #ffdfa0;
            color: #000;
            padding: 10px;
            font-weight: bold;
            border-top-left-radius: 5px;
            border-top-right-radius: 5px;
        }

        .notification-body {
            padding: 20px;
            font-size: 16px;
            text-align: center;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a {
            display: inline-block;
            padding: 8px 16px;
            text-decoration: none;
            background-color: #aec5e366;
            color: black;
            border-radius: 5px;
            margin: 0 4px;
        }

        .pagination a.active {
            background-color: #333;
            color: white;
        }

        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }

        .mode-toggle {
            display: flex;
            justify-content: center;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .mode-button {
            padding: 8px 16px;
            margin: 0 5px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .normal-mode {
            background-color: #e2e8f0;
            color: #2d3748;
            border: 1px solid #cbd5e0;
        }

        .normal-mode.active {
            background-color: #2d3748;
            color: #fff;
        }

        .incomplete-mode {
            background-color: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .incomplete-mode.active {
            background-color: #c53030;
            color: #fff;
        }

        .incomplete-data {
            background-color: #fff5f5 !important;
        }

        .missing-field {
            color: #c53030;
            font-weight: bold;
        }
    </style>
    <title>Product list</title>
</head>

<body>
    <div class="font-[Tahoma] mx-[23rem] mt-11 w-auto">
        <!-- Keep title, count, search, mode-toggle and notifications unchanged -->
        <div class="title">
            <div class="count">
                <?php if ($incomplete_mode): ?>
                    <h2 class="text-2xl font-bold">จำนวนรายการสินค้าที่กรอกข้อมูลไม่ครบ</h2>
                    <div class="incompletecount">
                        <?php echo isset($incomplete_count) ? $incomplete_count : '0'; ?>
                    </div>
                <?php else: ?>
                    <h2 class="text-2xl font-bold">จำนวนรายการสินค้าทั้งหมด</h2>

                    <div class="listcount">
                        <?php echo $count; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาสินค้า...">
            </div>
        </div>

        <div class="mode-toggle">
            <a href="?page=product_list"
                class="mode-button normal-mode <?php echo !$incomplete_mode ? 'active' : ''; ?>">
                แสดงทั้งหมด
            </a>
            <a href="?page=product_list&mode=incomplete"
                class="mode-button incomplete-mode <?php echo $incomplete_mode ? 'active' : ''; ?>">
                แสดงข้อมูลไม่ครบ
            </a>
        </div>

        <div id="notification-edit" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">แก้ไขบุคลากรสำเร็จ!</div>
        </div>
        <div id="notification-delete" class="notification">
            <div class="notification-header">การแจ้งเตือน</div>
            <div class="notification-body">ลบสำเร็จ!</div>
        </div>

        <div class="flex flex-col md:flex-row gap-4 ">
            <div class="dropdown relative">
                <div class="dropdown-button text-white bg-blue-700 w-auto hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center inline-flex items-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" id="dropdownButton">
                    เลือกประเภท
                </div>
                <ul class="dropdown-list absolute mt-1 w-auto px-5 bg-white border border-gray-300 rounded shadow-lg hidden z-10 max-h-64 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100" id="dropdownList">
                    <li class="px-4 py-2 cursor-pointer">
                        <div>
                            <?php foreach ($typeResults as $typeRow): ?>
                                <label>
                                    <input type="checkbox" class="filter-checkbox" name="product_type[]" value="<?php echo $typeRow['product_type_id']; ?>" <?php echo in_array($typeRow['product_type_id'], $selected_types) ? 'checked' : ''; ?>>
                                    <?php echo $typeRow['product_type_name']; ?>
                                </label><br>
                            <?php endforeach; ?>
                        </div>
                    </li>
                    <li class="px-4 py-2 cursor-pointer">
                        <div>
                            <?php foreach ($brandResults as $brandRow): ?>
                                <label>
                                    <input type="checkbox" class="filter-checkbox" name="product_brand[]" value="<?php echo $brandRow['product_brand_id']; ?>" <?php echo in_array($brandRow['product_brand_id'], $selected_brands) ? 'checked' : ''; ?>>
                                    <?php echo $brandRow['product_brand_name']; ?>
                                </label><br>
                            <?php endforeach; ?>
                        </div>
                    </li>
                </ul>
            </div>
            <div id="product-list-container"></div>
            <table class="mt-5 mb-8 w-[100%] border-2 border-[#b7c4d5] shadow-lg" id="dataTable">
                <thead>
                    <tr>
                        <th
                            class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            รหัสสินค้า</th>
                        <th
                            class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            ชื่อสินค้า</th>
                        <th
                            class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            ราคาทุน</th>
                        <th
                            class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            ราคาขาย</th>
                        <th
                            class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            ประเภทสินค้า</th>
                        <th
                            class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            แบรนด์สินค้า</th>
                        <th
                            class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            สถานะข้อมูล</th>
                        <th
                            class="w-[170px] bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                            การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultsproduct as $product_list): ?>
                        <?php
                        $is_incomplete = empty($product_list['product_name']) ||
                            empty($product_list['product_price1']) ||
                            empty($product_list['product_price2']) ||
                            empty($product_list['product_price3']) ||
                            empty($product_list['product_price4']) ||
                            empty($product_list['product_capital']) ||
                            empty($product_list['product_type_id']) ||
                            empty($product_list['product_brand_id']);
                        ?>
                        <tr class="<?php echo $is_incomplete ? 'incomplete-data' : 'bg-white'; ?>">
                            <td class="text-start bg-amber-200"><?php echo ($product_list['product_id']); ?></td>
                            <td class="text-start hover:bg-[#aec5e366] cursor-pointer<?php echo empty($product_list['product_name']) ? 'missing-field' : ''; ?>"
                                onclick="window.location.href='?page=product_detail&tabel=product&id=<?php echo ($product_list['product_id']) ?>'">
                                <?php echo empty($product_list['product_name']) ? 'ไม่มีข้อมูล' : $product_list['product_name']; ?>
                            </td>
                            <td class="text-start <?php echo empty($product_list['product_capital']) ? 'missing-field' : ''; ?>">
                                <?php echo empty($product_list['product_capital']) ? 'ไม่มีข้อมูล' : $product_list['product_capital']; ?>
                            </td>
                            <td class="text-start <?php echo empty($product_list['product_price4']) ? 'missing-field' : ''; ?>">
                                <?php echo empty($product_list['product_price4']) ? 'ไม่มีข้อมูล' : $product_list['product_price4']; ?>
                            </td>
                            <td class="text-start <?php echo empty($product_list['product_type_id']) ? 'missing-field' : ''; ?>">
                                <?php echo empty($product_list['product_type_name']) ? 'ไม่มีข้อมูล' : $product_list['product_type_name']; ?>
                            </td>
                            <td class="text-start <?php echo empty($product_list['product_brand_id']) ? 'missing-field' : ''; ?>">
                                <?php echo empty($product_list['product_brand_name']) ? 'ไม่มีข้อมูล' : $product_list['product_brand_name']; ?>
                            </td>
                            <td class="text-start">
                                <?php if ($is_incomplete): ?>
                                    <span class="text-red-600 font-bold">ข้อมูลไม่ครบ</span>
                                <?php else: ?>
                                    <span class="text-green-600 font-bold">ข้อมูลครบถ้วน</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=product_update&id=<?php echo ($product_list['product_id']) ?>" class="bg-gradient-to-b from-yellow-300 via-yellow-500 to-yellow-700 text-white text-lg border border-yellow-700 border-t-white border-l-white px-2 py-1 rounded shadow-md hover:bg-[#FFF9C4] active:border-[#FFF176] active:border-b-white active:border-r-white active:shadow-inner">
                                    แก้ไข
                                </a>

                                <a href="?page=product_list&id=<?php echo $product_list['product_id']; ?><?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>"
                                    onclick="return confirm('ลบหรือไม่ลบ สรุป?')" class="bg-gradient-to-b from-red-300 via-red-500 to-red-700 text-white text-lg border border-red-700 border-t-white border-l-white px-4 py-1 rounded shadow-md hover:bg-[#FFEBEE] active:border-[#EF9A9A] active:border-b-white active:border-r-white active:shadow-inner">
                                    ลบ
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ถ้าไม่มีข้อมูลให้แสดงข้อความ -->
        <?php if ($total_records == 0): ?>
            <div class="text-center py-4 text-red-600 font-bold">
                <?php if ($incomplete_mode): ?>
                    ไม่พบสินค้าที่มีข้อมูลไม่ครบ
                <?php else: ?>
                    ไม่พบข้อมูลสินค้า
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Keep the pagination and add product button unchanged -->
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($page > 1): ?>
                    <a
                        href="?page=product_list<?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>&page_num=<?php echo ($page - 1); ?>">&laquo;
                        ก่อนหน้า</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                        <a href="#" class="active"><?php echo $i; ?></a>
                    <?php else: ?>
                        <a
                            href="?page=product_list<?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>&page_num=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a
                        href="?page=product_list<?php echo $incomplete_mode ? '&mode=incomplete' : ''; ?>&page_num=<?php echo ($page + 1); ?>">ถัดไป
                        &raquo;</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- ปุ่มเพิ่มสินค้า -->
        <div class="text-end mt-4 mb-8">
            <a href="?page=product_insert" class="
                bg-gradient-to-b from-blue-300 via-blue-500 to-blue-700 
                text-white 
                text-lg 
                border border-blue-700 
                border-t-white 
                border-l-white 
                px-4 
                py-2 
                rounded 
                shadow-md 
                hover:bg-[#E3F2FD] 
                active:border-[#90CAF9] 
                active:border-b-white 
                active:border-r-white 
                active:shadow-inner
            ">
                เพิ่มสินค้าใหม่
            </a>
        </div>
    </div>

    <!-- Keep the scripts unchanged -->
    <script>
        // Get all checkboxes
        const checkboxes = document.querySelectorAll('.filter-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                let selectedTypes = [];
                let selectedBrands = [];

                // Collect selected types and brands
                document.querySelectorAll('input[name="product_type[]"]:checked').forEach(typeCheckbox => {
                    selectedTypes.push(typeCheckbox.value);
                });
                document.querySelectorAll('input[name="product_brand[]"]:checked').forEach(brandCheckbox => {
                    selectedBrands.push(brandCheckbox.value);
                });

                // Update the URL with selected filters
                let url = new URL(window.location.href);
                url.searchParams.set('product_type', selectedTypes.join(','));
                url.searchParams.set('product_brand', selectedBrands.join(','));

                // Reload the page with updated filters
                window.location.href = url.toString();
            });
        });

        // Toggle visibility of the dropdown list when the button is clicked
        const dropdownButton = document.getElementById('dropdownButton');
        const dropdownList = document.getElementById('dropdownList');

        dropdownButton.addEventListener('click', function() {
            dropdownList.classList.toggle('hidden');
        });

        // Close the dropdown if clicked outside
        document.addEventListener('click', function(event) {
            if (!dropdownButton.contains(event.target) && !dropdownList.contains(event.target)) {
                dropdownList.classList.add('hidden');
            }
        });

        function searchTable() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toLowerCase();
            const table = document.getElementById("dataTable");
            const tr = table.getElementsByTagName("tr");
            const thead = table.querySelector("thead");
            let foundAny = false;

            // Always keep the header visible
            thead.style.display = "";

            // Always keep the input row visible
            tr[0].style.display = "";

            // Search through data rows (start from index 2 to skip header and input row)
            for (let i = 1; i < tr.length; i++) {
                const td = tr[i].getElementsByTagName("td");
                let rowMatchesSearch = false;

                // Check every cell in the row
                for (let j = 0; j < td.length; j++) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        rowMatchesSearch = true;
                        break;
                    }
                }

                tr[i].style.display = rowMatchesSearch ? "" : "none";
                if (rowMatchesSearch) {
                    foundAny = true;
                }
            }

            // Handle "No data" message
            let noDataMessage = document.getElementById("noDataMessage");
            if (!foundAny) {
                if (!noDataMessage) {
                    noDataMessage = document.createElement("p");
                    noDataMessage.id = "noDataMessage";
                    noDataMessage.textContent = "ไม่พบข้อมูล";
                    noDataMessage.style.color = "red";
                    noDataMessage.style.fontWeight = "bold";
                    noDataMessage.style.textAlign = "center";
                    noDataMessage.style.marginTop = "10px";
                    table.parentNode.insertBefore(noDataMessage, table.nextSibling);
                }
            } else {
                if (noDataMessage) {
                    noDataMessage.remove();
                }
            }
        }
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const searchInput = document.getElementById("searchInput");
            searchInput.focus();
            if (urlParams.has('edit_success')) {
                const notificationEdit = document.getElementById('notification-edit');
                notificationEdit.style.display = 'block';
                setTimeout(() => {
                    notificationEdit.style.display = 'none';
                }, 2000);
            } else if (urlParams.has('delete_success')) {
                const notificationDelete = document.getElementById('notification-delete');
                notificationDelete.style.display = 'block';
                setTimeout(() => {
                    notificationDelete.style.display = 'none';
                }, 2000);
            }
        });
    </script>
</body>

</html>