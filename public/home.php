
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./style/navbar.css"/>
    <title>Home</title>
    <style>
        .welcome-message {
            display: flex;
            justify-content: center;
            /* จัดให้อยู่กลางในแนวนอน */
            align-items: center;
            /* จัดให้อยู่กลางในแนวตั้ง */
            height: 100vh;
            /* ให้สูงเต็มหน้าจอ */
            text-align: center;
            /* จัดข้อความให้กึ่งกลาง */
            font-size: 2em;
            /* ขนาดตัวอักษร */
            color: #333;
            /* สีตัวอักษร */
            background-color: #e6f3ff;
            /* สีพื้นหลัง */
        }
    </style>

</head>

<body>
    <?php include 'component/navbar.php'; ?>

    <div class="content">
        <?php
        
        if (isset($_GET['page'])) {
            $page = $_GET['page'];

            
            if ($page === 'personnel') {
                include 'component/personnel.php'; 
            }
            
        } else {
            echo '<div class="welcome-message"><h1>ยังไม่รู้จะเอาอะไรเป็นหน้าแรก คิดอยู่</h1></div>'; 
        }
        ?>
    </div>
</body>

</html>

    