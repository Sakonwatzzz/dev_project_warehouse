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

                    <li><a href="#">ขาย</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="button-bar">
        <?php

        if (isset($_GET['page'])) {
            $page = $_GET['page'];

            // แปลงพารามิเตอร์ให้เป็นเส้นทางไฟล์ (แทนที่ _ ด้วย /)
            $file_path = __DIR__ . "" . str_replace("_", "/", $page) . ".php";

            // ตรวจสอบว่าไฟล์นั้นมีอยู่หรือไม่
            if (file_exists($file_path)) {
                // หากมีคำว่า "member" ในชื่อพารามิเตอร์ ให้รวม bar_member.php
                if (strpos($page, 'member') !== false) {
                    include __DIR__ . "/bar_member.php"; // รวม bar_member.php
                }
                // รวมไฟล์ที่ตรงกับพารามิเตอร์
                include $file_path;
            } else {
                echo "<h2>ไม่พบหน้าที่ต้องการ: " . htmlspecialchars($page) . "</h2>";
            }
        } else {
            // ถ้าไม่มีพารามิเตอร์ จะแสดงหน้าเริ่มต้น
            include __DIR__ . "/bar_index.php"; 
        }

        ?>
    </div>

</body>

</html>