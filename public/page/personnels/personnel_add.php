<?php
require_once __DIR__ . '../../../../config/database_server.php';
$result = fetchAll($pdo, 'personnel');

$query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$results = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_add.css">

    <title>Document</title>

</head>

<body>

    <div class="add-box">
        <h2 class="sub-title">เพิ่มสมาชิก</h2>
        <form class="form-container" action="fuctions_personnel/create.php" method="POST">
            <div class="box">
                <label for="name">ชื่อ นามสกุลบุคลากร:</label>
                <input type="text" id="name" name="name" required>
            </div>


            <!-- loop -->
            <?php
            if ($results->num_rows > 0) {
            ?>
                <div class="box">
                    <label for="typename">ประเภทบุคคลากร:</label>
                    <select name="typename" id="typename" required>
                        <?php while ($row = $results->fetch_assoc()) { ?>
                            <option value="<?php echo htmlspecialchars($row['personnel_type_id']); ?>">
                                <?php echo htmlspecialchars($row['personnel_type_name']); ?>
                            </option>
                        <?php } ?>
                    </select><br>
                </div>

            <?php
            } else {
                echo "No personnel types available.";
            }
            ?>
            <!--  -->
            <div class="box">
                <label for="address">ที่อยู่บุคลากร:</label>
                <input type="text" id="address" name="address" required>
            </div>
            <div class="box">
                <label for="phone">เบอร์โทรศัพท์:</label>
                <input type="text" id="phone" name="phone" required>
            </div>

            <div class="box">
                <label for="username">ชื่อผู้ใช้:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="box">
                <label for="password">รหัสผ่าน:</label>
                <input type="password" id="password" name="password" required>
            </div>


            <!--<input class="submit-button" type="submit" value="เพิ่ม">-->
            <div class="box">
                <label for=""></label>
                <button lass="submit-button" type="submit" value="เพิ่ม">บันทึก</button>
            </div>

        </form>

    </div>
</body>

</html>