<?php

//ไฟนี้เป็นส่วนของการจัดการ เพิ่ม ลบ แก้ไข ดึงข็อมูล

class Model
{
    protected $db; // เก็บ instance ของการเชื่อมต่อฐานข้อมูล
    protected $table; // เก็บชื่อของตารางที่เกี่ยวข้องกับ model นี้

    // Constructor: กำหนดค่าเริ่มต้นของโมเดลด้วยชื่อของตารางและเชื่อมต่อฐานข้อมูล
    public function __construct($table)
    {
        // เชื่อมต่อฐานข้อมูลโดยใช้ PDO
        require_once __DIR__ . '/../config/database_server.php';
        $this->db = $pdo; // เก็บ instance ของ PDO
        $this->table = $table; // กำหนดชื่อตาราง

        // ตรวจสอบการเชื่อมต่อฐานข้อมูล
        if (!$this->db) {
            $this->logError("Database connection not established.");
            throw new Exception("Database connection not established.");
        }
    }

    // sanitizeInput: กำจัดข้อมูลไม่ปลอดภัยจาก input โดยการแปลงเป็น string ที่ปลอดภัย
    private function sanitizeInput($data)
    {
        return htmlspecialchars(strip_tags($data));
    }

    // logError: บันทึก error ลงในไฟล์ 'error_log.txt' เพื่อใช้ในการ debug
    private function logError($message)
    {
        $logFile = 'error_log.txt';

        // ตรวจสอบว่าไฟล์ log มีอยู่หรือไม่ หากไม่ให้สร้างขึ้น
        if (!file_exists($logFile)) {
            file_put_contents($logFile, ""); // สร้างไฟล์ว่าง
        }
        error_log($message . " | " . date('Y-m-d H:i:s') . PHP_EOL, 3, 'error_log.txt');
    }

    // add: เพิ่มข้อมูลใหม่ลงในตาราง
    public function add(array $data): bool
    {
        // ขั้นตอนที่ 1: ทำความสะอาดข้อมูลที่ได้รับ
        foreach ($data as $key => $value) {
            // เรียกใช้ฟังก์ชัน sanitizeInput เพื่อทำความสะอาดข้อมูลแต่ละค่าที่ส่งเข้ามา
            $data[$key] = $this->sanitizeInput($value);
        }

        // ขั้นตอนที่ 2: ตรวจสอบว่ามีข้อมูลที่ต้องเพิ่มหรือไม่
        if (empty($data)) {
            // ถ้าไม่มีข้อมูล จะบันทึกข้อความผิดพลาดใน log
            $this->logError("Add operation failed: No data provided");
            return false; // คืนค่าผลลัพธ์เป็น false เพื่อบ่งชี้ว่าการเพิ่มข้อมูลล้มเหลว
        }

        try {
            // ขั้นตอนที่ 3: เตรียมค่าสำหรับการสร้างคำสั่ง SQL
            $fields = implode(", ", array_keys($data)); // ดึงชื่อฟิลด์จาก array $data และแปลงเป็น string
            $placeholders = implode(", ", array_fill(0, count($data), "?")); // สร้าง placeholder สำหรับค่าที่จะถูกเพิ่ม

            // ขั้นตอนที่ 4: เตรียม statement สำหรับการเพิ่มข้อมูล
            $stmt = $this->db->prepare("INSERT INTO $this->table ($fields) VALUES ($placeholders)");

            // ขั้นตอนที่ 5: ดำเนินการคำสั่ง SQL
            if (!$stmt->execute(array_values($data))) {
                // ถ้าการดำเนินการไม่สำเร็จ ให้โยนข้อผิดพลาด
                throw new Exception("Execute failed: " . implode(", ", $stmt->errorInfo()));
            }

            return true; // คืนค่า true เมื่อการเพิ่มข้อมูลสำเร็จ
        } catch (Exception $e) {
            // ขั้นตอนที่ 6: จัดการข้อผิดพลาด
            $this->logError("Error adding record: " . $e->getMessage()); // บันทึกข้อความผิดพลาดใน log
            return false; // คืนค่าผลลัพธ์เป็น false เพื่อบ่งชี้ว่าการเพิ่มข้อมูลล้มเหลว
        }
    }


    public function delete($id, $idColumn): bool // รับพารามิเตอร์ ID และชื่อคอลัมน์
    {
        if (empty($id)) {
            $this->logError("Delete operation failed: Invalid ID");
            return false;
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM $this->table WHERE $idColumn = ?"); // ใช้ชื่อคอลัมน์ที่ส่งเข้ามา
            if (!$stmt->execute([$id])) {
                throw new Exception("Execute failed: " . implode(", ", $stmt->errorInfo()));
            }

            return true;
        } catch (Exception $e) {
            $this->logError("Error deleting record: " . $e->getMessage());
            return false;
        }
    }


    public function update($id, $idColumn, array $data): bool
    {
        // ขั้นตอนที่ 1: ทำความสะอาดข้อมูลที่ได้รับ
        foreach ($data as $key => $value) {
            $data[$key] = $this->sanitizeInput($value);
        }

        // ขั้นตอนที่ 2: ตรวจสอบข้อมูลที่ได้รับ
        if (empty($data) || empty($id)) {
            $this->logError("Update operation failed: Invalid data or ID");
            return false;
        }

        try {
            // ขั้นตอนที่ 3: เตรียมค่าสำหรับการสร้างคำสั่ง SQL
            $fields = implode(" = ?, ", array_keys($data)) . " = ?"; // สร้าง string สำหรับการอัปเดตฟิลด์

            // ขั้นตอนที่ 4: เตรียม statement สำหรับการอัปเดตข้อมูล
            $stmt = $this->db->prepare("UPDATE $this->table SET $fields WHERE $idColumn = ?");

            // เพิ่ม ID ลงในข้อมูลที่จะ execute
            $data[] = $id; // เพิ่ม ID เป็นค่าที่สุดท้าย

            // ขั้นตอนที่ 5: ดำเนินการคำสั่ง SQL
            if (!$stmt->execute(array_values($data))) { // ใช้ array_values() เพื่อส่งค่าในรูปแบบ array
                throw new Exception("Execute failed: " . implode(", ", $stmt->errorInfo()));
            }

            return true; // คืนค่า true เมื่อการอัปเดตข้อมูลสำเร็จ
        } catch (Exception $e) {
            $this->logError("Error updating record: " . $e->getMessage());
            return false;
        }
    }

    // getAll: ดึงข้อมูลทั้งหมดจากตาราง สามารถใช้เงื่อนไขต่างๆ เช่น การเรียงลำดับและการจำกัดข้อมูล
    public function getAll(array $conditions = [], string $orderBy = "", int $limit = 10, int $offset = 0): array
    {
        try {
            // ขั้นตอนที่ 1: เริ่มต้นสร้างคำสั่ง SQL
            $query = "SELECT * FROM $this->table";
            $params = []; // อาร์เรย์สำหรับเก็บพารามิเตอร์ที่ใช้ในคำสั่ง SQL

            // ขั้นตอนที่ 2: กำหนดเงื่อนไขการค้นหา
            if (!empty($conditions)) {
                $clauses = []; // อาร์เรย์สำหรับเก็บเงื่อนไขการค้นหา
                foreach ($conditions as $field => $value) {
                    // เพิ่มเงื่อนไขในรูปแบบ "field LIKE ?"
                    $clauses[] = "$field LIKE ?";
                    // ทำความสะอาดข้อมูลและเพิ่มค่าในรูปแบบ wildcard
                    $params[] = '%' . $this->sanitizeInput($value) . '%';
                }
                // เพิ่มส่วน WHERE ในคำสั่ง SQL
                $query .= " WHERE " . implode(" AND ", $clauses);
            }

            // ขั้นตอนที่ 3: เพิ่มการเรียงลำดับ
            if ($orderBy) {
                $query .= " ORDER BY $orderBy"; // เพิ่ม ORDER BY
            }

            // ขั้นตอนที่ 4: เพิ่ม LIMIT และ OFFSET
            $query .= " LIMIT $limit OFFSET $offset"; // ใช้ค่าตรงๆ สำหรับ LIMIT และ OFFSET

            // ขั้นตอนที่ 5: เตรียม statement
            $stmt = $this->db->prepare($query); // เตรียมคำสั่ง SQL
            $stmt->execute($params); // ดำเนินการคำสั่ง SQL โดยส่งพารามิเตอร์
            return $stmt->fetchAll(PDO::FETCH_ASSOC); // คืนค่าผลลัพธ์เป็นอาร์เรย์ของแถวข้อมูลในรูปแบบ associative array
        } catch (Exception $e) {
            // ขั้นตอนที่ 6: จัดการข้อผิดพลาด
            $this->logError("Error fetching records: " . $e->getMessage()); // บันทึกข้อความผิดพลาดใน log
            return []; // คืนค่าอาร์เรย์ว่างในกรณีเกิดข้อผิดพลาด
        }
    }

    
    
    
    //ฟังชั่นนี้ยังไม่สมบูรณ์ 
    // getById: ดึงข้อมูลจากตารางโดยใช้ id
    public function getById($id): ?array
    {
        // ขั้นตอนที่ 1: ตรวจสอบว่ามี ID หรือไม่
        if (empty($id)) {
            $this->logError("Get by ID operation failed: Invalid ID"); // บันทึกข้อความผิดพลาดใน log
            return null; // คืนค่า null หาก ID ไม่ถูกต้อง
        }

        try {
            // ขั้นตอนที่ 2: เตรียมคำสั่ง SQL เพื่อดึงข้อมูล
            $stmt = $this->db->prepare("SELECT * FROM $this->table WHERE id = ?");
            // ขั้นตอนที่ 3: ดำเนินการคำสั่ง SQL โดยส่ง ID
            $stmt->execute([$id]);
            // ขั้นตอนที่ 4: คืนค่าผลลัพธ์เป็นแถวข้อมูลเดียวในรูปแบบ associative array
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // ขั้นตอนที่ 5: จัดการข้อผิดพลาด
            $this->logError("Error fetching record by ID: " . $e->getMessage()); // บันทึกข้อความผิดพลาดใน log
            return null; // คืนค่า null ในกรณีเกิดข้อผิดพลาด
        }
    }
}
