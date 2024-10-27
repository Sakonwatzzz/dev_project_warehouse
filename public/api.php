<?php

//ไฟนี้เป็นเพียงการทดสอบ ฟังชั่นเฉยๆ ไม่เกี่ยวข้องกับส่วนอื่นๆ


require_once __DIR__ . '/ModelController.php'; // เปลี่ยนเส้นทางให้ถูกต้อง
require_once 'PersernelController.php';

header('Content-Type: application/json'); // กำหนดให้เป็น JSON

$action = $_SERVER['REQUEST_METHOD']; // รับ Method (GET, POST, PUT, DELETE)

switch ($action) {
    case 'POST':
        // เพิ่มข้อมูล
        $data = json_decode(file_get_contents('php://input'), true); // รับข้อมูล JSON
        $persernel = new Persernel();
        if ($persernel->add($data)) {
            echo json_encode(["message" => "เพิ่มข้อมูลสำเร็จ!"]);
        } else {
            echo json_encode(["message" => "ขออภัยเกิดข้อผิดพลาดในการเพิ่มข้อมูล!"], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'DELETE':
        // ลบข้อมูล
        $id = $_GET['id'] ?? null; // ตรวจสอบว่า id ถูกส่งมา
        if (!$id) {
            echo json_encode(["message" => "ID ไม่ถูกต้อง!"], JSON_UNESCAPED_UNICODE);
            break;
        }
        $table = $_GET['table'] ?? 'personnel'; // กำหนดตารางที่ต้องการลบข้อมูล
        $idColumn = ($table === 'personnel') ? 'personnel_id' : 'supplie_id'; // กำหนดชื่อคอลัมน์ ID
        $persernel = new Persernel();
        if ($persernel->delete($id, $idColumn)) {
            echo json_encode(["message" => "ลบข้อมูลสำเร็จ!"]);
        } else {
            echo json_encode(["message" => "เกิดข้อผิดพลาดในการลบข้อมูล!"]);
        }
        break;


    case 'PUT':
        // แก้ไขข้อมูล
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['personnel_id'] ?? null; // รับ ID จากข้อมูล JSON
        $idColumn = 'personnel_id'; // กำหนดชื่อคอลัมน์ที่ใช้สำหรับอัปเดต

        // ตรวจสอบว่าข้อมูลที่ได้รับมีข้อมูลที่ต้องอัปเดต
        if (empty($data) || empty($id)) {
            echo json_encode(["message" => "เกิดข้อผิดพลาด: ข้อมูลไม่ถูกต้องหรือ ID ไม่ได้ถูกส่งมา!"]);
            break;
        }

        $persernel = new Persernel();
        // เรียกใช้ฟังก์ชัน update โดยส่ง ID, ชื่อคอลัมน์, และข้อมูลที่ต้องการอัปเดต
        if ($persernel->update($id, $idColumn, $data)) {
            echo json_encode(["message" => "อัปเดตข้อมูลสำเร็จ!"]);
        } else {
            echo json_encode(["message" => "เกิดข้อผิดพลาดในการอัปเดตข้อมูล!"]);
        }
        break;


    case 'GET':
        // ตรวจสอบการเรียกดูข้อมูล
        if (isset($_GET['personnel_id'])) {
            // ดึงข้อมูลตาม ID
            $persernel = new Persernel();
            $record = $persernel->getById($_GET['personnel_id']);
            echo json_encode($record ? $record : ["message" => "ไม่พบข้อมูล!"]);
        } else {
            // ดึงข้อมูลทั้งหมด
            $persernel = new Persernel();
            $results = $persernel->getAll();
            echo json_encode($results);
        }
        break;

    default:
        echo json_encode(["message" => "ไม่มี action ที่ถูกต้อง!"]);
        break;
}
