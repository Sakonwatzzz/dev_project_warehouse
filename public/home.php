<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <style>
    </style>

</head>

<body>
    <?php include 'component/navbar.php'; ?>

    <div class="content-item">
        <?php
        if (isset($_GET['page'])) {
            $page = $_GET['page'];

            if ($page === 'personnel_list') {
                include './component/bar_personnel.php';
                include './page/personnels/personnel_list.php';
            } elseif ($page === 'personnel_add') {
                include './component/bar_personnel.php';
                include './page/personnels/personnel_add.php';
            } elseif ($page === 'personnel_type') {
                include './component/bar_personnel.php';
                include './page/personnels/personnel_type.php';
            } elseif ($page === 'personnel_status') {
                include './component/bar_personnel.php';
                include './page/personnels/personnel_status.php';
            } 
        } else {
            echo "<p>กรุณาเลือกเมนูเพื่อแสดงข้อมูล</p>";
        }
        ?>

    </div>



</body>

</html>