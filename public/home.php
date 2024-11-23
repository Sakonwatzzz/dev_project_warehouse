<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>

    <style>
        .button-bar {
            display: block;
        }
    </style>
</head>

<body>
    <?php include 'component/navigationbar.php'; ?>

    <div class="content-item">
        <?php
        if (isset($_GET['page'])) {
            $page = $_GET['page'];
            $parts = explode("_", $page);

            if (count($parts) >= 2) {
                // จัดการ module, action, และ subaction (ถ้ามี)
                $module = $parts[0];
                $action = $parts[1];
                $subaction = $parts[2] ?? null; // กรณีมี subaction
        
                // กำหนดเส้นทางไฟล์ตามกรณี
                if ($subaction) {
                    $filePath = __DIR__ . "/page/{$module}/{$module}_{$action}_{$subaction}.php";
                } else {
                    $filePath = __DIR__ . "/page/{$module}/{$module}_{$action}.php";
                }

                // ตรวจสอบไฟล์
                if (file_exists($filePath)) {
                    include $filePath;
                } else {
                    echo "<h2>ไม่พบไฟล์ที่คุณต้องการ</h2>";
                }
            } else {
                echo "<h2>รูปแบบของพารามิเตอร์ไม่ถูกต้อง</h2>";
            }
        } else {
            echo "<h2>ยินดีต้อนรับ</h2>";
            // include __DIR__ . "/component/dashboard.php";
        }
        ?>
    </div>
</body>

</html>