<?php
require_once __DIR__ . '../../../../config/database_server.php';

// รับค่าจาก URL เพื่อหา type ของตำแหน่ง
$type = isset($_GET['type']) ? $_GET['type'] : '';

// คำสั่ง SQL เพื่อดึงข้อมูลสมาชิกตามประเภท
$query = "
    SELECT    
        member.member_id,
        member.member_name, 
        member.member_phone, 
        member.member_type_id, 
        member_type.member_type_name
    FROM
        member
    INNER JOIN
        member_type ON member.member_type_id = member_type.member_type_id
    WHERE
        member_type.member_type_name = :type
    ORDER BY
        member.member_name;
";

try {
    // ใช้ prepared statement เพื่อป้องกัน SQL Injection
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->execute();

    // ดึงข้อมูลสมาชิก
    $memberData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
    exit();
}

// คำสั่ง SQL เพื่อคำนวณจำนวนข้อมูลทั้งหมดในประเภทที่กำหนด
$query = "
    SELECT COUNT(*) as total_count
    FROM member
    INNER JOIN member_type ON member.member_type_id = member_type.member_type_id
    WHERE member_type.member_type_name = :type
";

try {
    // ใช้ prepared statement เพื่อป้องกัน SQL Injection
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->execute();

    // ดึงจำนวนข้อมูลทั้งหมด
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $row['total_count'];
} catch (Exception $e) {
    echo "error: " . $e->getMessage();
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../style/personnel_list.css">
    <title>กลุ่มสมาชิก</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

        .title {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .count {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .listcount {
            font-size: 16px;
            text-align: right;
            color: #000;
            padding: 4px 10px 4px 10px;
            border: 1px solid gray;
            border-radius: 5px;
            background-color: #fff;
            display: inline-block;
            margin-top: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            width: 70px;
        }

        .detail-action {
            margin-top: 25px;
            width: auto;
            height: 40px;
        }

        .detail-action a {
            border-radius: 6px;
            width: 50px;
            height: 40px;
            padding: 10px;
            background-color: #333;
            text-decoration: none;
            color: #fff;
        }

        .detail-action a:hover {
            background-color: #000000;
        }
    </style>
</head>

<body>
    <div class="personnel-list">
        <div class="title">
            <div class="count">
                <h2>รายชื่อสมาชิกตามประเภท: <?php echo htmlspecialchars($type); ?></h2>
                <div class="listcount">
                    <?php echo $count; ?>
                </div>
            </div>
            <div class="search">
                <input type="text" id="searchInput" onkeyup="searchTable()" tabindex="1" placeholder="ค้นหาสมาชิก...">
            </div>
        </div>

        <?php if ($memberData): ?>
            <table class="data-table" id="dataTable">
                <thead>
                    <tr>
                        <th>ชื่อ</th>
                        <th>ประเภท</th>
                        <th>เบอร์โทร</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>

                <!--ลูปข้อมูลตาม type ของประเภท-->
                <tbody>
                    <?php foreach ($memberData as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['member_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['member_type_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['member_phone']); ?></td>
                            <td>
                                <a href="?page=member_update&id=<?php echo $member['member_id']; ?>">แก้ไข</a>
                                <a href="?page=bar_member&id=<?php echo $member['member_id']; ?>" onclick="return confirm('ลบหรือไม่ลบ สรุป?')">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>ไม่มีสมาชิกในประเภทนี้</p>
        <?php endif; ?>

        <!--ย้อนกลับไปหน้าเดิม-->
        <div class="detail-action">
            <a href="/?page=bar_member">ย้อนกลับ</a>
        </div>
    </div>
</body>

</html>
