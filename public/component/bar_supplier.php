<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        /* พื้นหลังแบบ Windows XP */
        body {
            font-family: Tahoma, sans-serif;
            background-color: #e3f2fd; /* สีฟ้าอ่อน */
            color: #333;
        }

        /* สไตล์ปุ่ม Windows XP */
        .button-bb {
            padding: 5px;
            display: flex;
            gap: 5px;
            align-items: center;
            background-color: #bbdefb; /* พื้นหลังปุ่มโทนฟ้า */
            border: 1px solid #90caf9; /* สีขอบปุ่ม */
            border-radius: 4px;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
        }

        .button-bb a {
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .button-bb button {
            display: flex;
            align-items: center;
            background-color: #e1f5fe; /* สีฟ้าอ่อนปุ่ม */
            border: 1px solid #81d4fa; /* สีขอบปุ่ม */
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
            box-shadow: 1px 1px 3px #888;
        }

        .button-bb button:hover {
            background-color: #b3e5fc; /* เปลี่ยนสีเมื่อ hover */
            border-color: #39a9db; /* เปลี่ยนสีขอบเมื่อ hover */
        }

        /* ขนาดภาพ */
        .button-bb img {
            width: 32px;
            height: auto;
            margin-right: 8px;
        }

        /* สไตล์ข้อความ */
        .button-bb p {
            margin: 0;
            font-size: 12px;
            color: #333;
        }

        .button-bar-1 {
            display: flex;
            gap: 5px;
        }

        .button-bar-2 {
            display: flex;
            gap: 5px;
        }
    </style>
</head>

<body>
    <div class="button-bb">
        <div class="button-bar-1">
            <a href="?page=bar_supplier" class="button-link">
                <button>
                    <img src="../image/box.png" alt="">
                    <p>รายชื่อผู้ส่งมอบ</p>
                </button>
            </a>
            <a href="?page=supplier_add" class="button-link">
                <button>
                    <img src="../image/add-product.png" alt="">
                    <p>เพิ่มรายชื่อผู้ส่งมอบ</p>
                </button>
            </a>
        </div>
    </div>
</body>

</html>
