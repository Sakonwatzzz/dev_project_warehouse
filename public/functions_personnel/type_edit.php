<?php
require_once __DIR__ . '../../../config/database_server.php';


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
$personneltype = $stmt->fetch();
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
    <h2>แก้ไขประเภทบุคลากร</h2>
    <form action="type_edit.php" method="POST">
        <input type="hidden" name="id" value="<?= htmlspecialchars($personneltype['personnel_type_id']) ?>">

        <label for="typename">ชื่อประเภทบุคลากร:</label>
        <input type="text" name="typename" value="<?= htmlspecialchars($personneltype['personnel_type_name']) ?>">

        <input type="submit" value="Update">
    </form>
</body>

</html>