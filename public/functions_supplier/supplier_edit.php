<?php
require_once __DIR__ . '/../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $detail = $_POST['detail'];

    $stmt = $pdo->prepare("
        UPDATE supplier 
        SET supplier_name = ?,  supplier_address = ?, supplier_phone = ?, supplier_detail
        WHERE supplier_id = ?
    ");
    $stmt->execute([$name, $address, $phone, $detail, $id]);

    header("Location: supplier.php");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM supplier WHERE supplier_id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch();
?>

<h2>Edit supplier</h2>
<form action="edit.php" method="POST">
    <input type="hidden" name="id" value="<?= htmlspecialchars($supplier['supplier_id']) ?>">

    <label for="name">Name:</label>
    <input type="text" name="name" value="<?= htmlspecialchars($supplier['supplier_name']) ?>" required>

    <label for="address">Address:</label>
    <input type="text" name="address" value="<?= htmlspecialchars($supplier['supplier_address']) ?>" required>

    <label for="address">Phone:</label>
    <input type="text" name="phone" value="<?= htmlspecialchars($supplier['supplier_phone']) ?>" required>

    <label for="address">Password:</label>
    <input type="text" name="detail" value="<?= htmlspecialchars($supplier['supplier_detail']) ?>" required>
    <input type="submit" value="Update">
</form>