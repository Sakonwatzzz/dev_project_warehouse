<?php
require_once __DIR__ . '../../../../config/database_server.php';
$pdo = Database::getInstance()->getConnection(); 

$query = "SELECT personnel_type_id, personnel_type_name FROM personnel_type";
$results = $pdo->query($query);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $typename = $_POST['typename'];

    $stmt = $pdo->prepare("
        UPDATE personnel_type
        SET personnel_type_name = ?
        WHERE personnel_type_id = ?
    ");
    $stmt->execute([$typename, $id]);

    header("Location: http://localhost:8000/home.php?page=personnel_type");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM personnel_type WHERE personnel_type_id = ?");
$stmt->execute([$id]);
$personnel_type_update = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/member_edit.css">
    <title>Document</title>
</head>

<body>
    <div class="main-body">
        <h2>แก้ไขประเภทบุคลากร</h2>
        <form class="form-container" action="type_edit.php" method="POST">
            <input type="hidden" name="id" value="<?php echo($personnel_type_update['personnel_type_id']) ?>">

            <label for="typename">ชื่อประเภทบุคลากร:</label>
            <input type="text" name="typename" value="<?php echo($personnel_type_update['personnel_type_name']) ?>">

            <input class="submit-button" type="submit" value="Update">
        </form>
    </div>
</body>

</html>