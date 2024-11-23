<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../style/navbar.css">
    <title>Admin System</title>
</head>

<body>
    <div class="header">
        <p>Admin Management system</p>
        <div class="navbar">
            <div class="link">
                <ul>
                    <li><a href="?page=bar_index"
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_index') ? 'active' : ''; ?>">หน้าแรก</a>
                    </li>
                    <li><a href="?page=bar_product"
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_product') ? 'active' : ''; ?>">สินค้า</a>
                    </li>
                    <li><a href="?page=bar_personnel"
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_personnel') ? 'active' : ''; ?>">บุคลากร</a>
                    </li>
                    <li><a href="?page=bar_member"
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_member') ? 'active' : ''; ?>">สมาชิก</a>
                    </li>
                    <li><a href="?page=bar_supplier"
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'bar_supplier') ? 'active' : ''; ?>">ผู้ส่งมอบ</a>
                    </li>

                    <li><a href="?page=navigationbar_sale"
                            class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'navigationbar_sale') ? 'active' : ''; ?>">ขาย</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="button-bar">
        <?php
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
            // var_dump($page); // ตรวจสอบค่า $page
            $parts = explode("_", $page);
            // var_dump($parts); // ตรวจสอบการแยก $parts


            // ตรวจสอบว่า $parts มีจำนวนส่วนมากกว่า 2
            if (count($parts) >= 2) {
                $module = $parts[0];
                $action = $parts[1];
                $subaction = $parts[2] ?? null; // รองรับ subaction เช่น lot_product_insert

                switch ($page) {
                    case "bar_index":
                        include __DIR__ . "/bar_index.php";
                        break;
                    case "bar_product":
                        include __DIR__ . "/bar_product.php";
                        include __DIR__ . "/../page/product/product_list.php";
                        break;
                    case "bar_personnel":
                        include __DIR__ . "/bar_personnel.php";
                        include __DIR__ . "/../page/personnel/personnel_list.php";
                        break;
                    case "bar_member":
                        include __DIR__ . "/bar_member.php";
                        include __DIR__ . "/../page/member/member_list.php";
                        break;
                    case "bar_supplier":
                        include __DIR__ . "/bar_supplier.php";
                        include __DIR__ . "/../page/supplier/supplier_list.php";
                        break;
                    case "navigationbar_sale":
                        include __DIR__ . "/navigationbar_sale.php";
                        include __DIR__ . "/../page/sale/sale_bill.php";
                        break;
                    case "lot_product_list":
                        include __DIR__ . "/bar_product.php";
                        include __DIR__ . "/../page/lot/lot_product_list.php";
                        break;
                    default:
                        // ตรวจสอบพารามิเตอร์ที่รองรับใน array
                        if (in_array($page, ['personnel_list', 'personnel_insert', 'personnel_type', 'personnel_detail', 'personnel_update', 'personnel_group'])) {
                            include __DIR__ . "/bar_personnel.php";
                        } elseif (in_array($page, ['member_list', 'member_insert', 'member_update', 'member_type', 'member_detail','member_group'])) {
                            include __DIR__ . "/bar_member.php";
                        } elseif (in_array($page, [
                            'product_list',
                            'product_insert',
                            'product_type',
                            'product_brand',
                            'product_brandupdate',
                            'product_detail',
                            'product_update',
                            'warehouse_list',
                            'warehouse_update',
                            'lot_insert',
                            'lot_list',
                            'lot_update',
                            'lot_product_insert',
                            'lot_productlist',
                            'lot_productupdate',
                            'lot_detail',
                            'lot_product_detail',
                        ])) {
                            include __DIR__ . "/bar_product.php";
                        } elseif (in_array($page, ['supplier_list', 'supplier_insert', 'supplier_update', 'supplier_detail',])) {
                            include __DIR__ . "/bar_supplier.php";
                        } elseif (in_array($page, ['sale_list', 'sale_bill', 'sale_status'])) {
                            include __DIR__ . "/navigationbar_sale.php";
                        } else {
                            echo "<h2>ไม่พบไฟล์ที่รองรับพารามิเตอร์: " . htmlspecialchars($page) . "</h2>";
                        }
                        break;
                }
            } else {
                echo "<h2>รูปแบบของพารามิเตอร์ไม่ถูกต้อง: " . htmlspecialchars($page) . "</h2>";
            }
        } else {
            include __DIR__ . "/bar_index.php";
        }
        ?>



    </div>

</body>

</html>