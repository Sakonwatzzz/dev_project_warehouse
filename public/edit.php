<?php
require_once __DIR__ . '/../config/database_server.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);  // Hash password

    $stmt = $pdo->prepare("
        UPDATE personnel 
        SET personnel_name = ?, personnel_surname = ?, personnel_address = ?, personnel_phone = ?, personnel_username = ?, personnel_password = ? 
        WHERE personnel_id = ?
    ");
    $stmt->execute([$name, $surname, $address, $phone, $username, $password, $id]);

    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM personnel WHERE personnel_id = ?");
$stmt->execute([$id]);
$personnel = $stmt->fetch();
?>

<h2>Edit Personnel</h2>
<form action="edit.php" method="POST">
    <input type="hidden" name="id" value="<?= htmlspecialchars($personnel['personnel_id']) ?>">

    <label for="name">Name:</label>
    <input type="text" name="name" value="<?= htmlspecialchars($personnel['personnel_name']) ?>" required>

    <label for="surname">Surname Name:</label>
    <input type="text" name="surname" value="<?= htmlspecialchars($personnel['personnel_surname']) ?>" required>

    <label for="address">Address:</label>
    <input type="text" name="address" value="<?= htmlspecialchars($personnel['personnel_address']) ?>" required>

    <label for="address">Phone:</label>
    <input type="text" name="phone" value="<?= htmlspecialchars($personnel['personnel_phone']) ?>" required>

    <label for="address">Username:</label>
    <input type="text" name="username" value="<?= htmlspecialchars($personnel['personnel_username']) ?>" required>

    <label for="address">Password:</label>
    <input type="text" name="password" value="<?= htmlspecialchars($personnel['personnel_password']) ?>" required>
    <input type="submit" value="Update">
</form>