<?php
require_once __DIR__ . '../../../../config/database_server.php';



?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ตัวกรองการค้นหา</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 font-sans">
  <div class="container mx-auto p-4">
    <div class="bg-white shadow-md rounded-lg p-4 mb-4">
      <div class="flex justify-start items-center gap-4">
        <!-- ช่องค้นหาข้อความ -->
        <div class="flex items-center">
          <input type="text" placeholder="ค้นหา..." class="border border-gray-300 rounded-sm px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 w-96">
        </div>
        <!-- <button class="bg-gray-200 text-gray-600 px-4 py-2 rounded-r-md hover:bg-gray-300">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </button> -->

        <!-- วันที่เริ่มต้น - สิ้นสุด -->
        <div class="flex items-center space-x-2">
          <input type="date" class="border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500" value="2025-04-28">
          <span class="text-gray-600">ถึง</span>
          <input type="date" class="border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500" value="2025-04-28">
        </div>

        <!-- ค้นหาตามสถานะ -->
        <div class="flex items-center">
          <select class="border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">สถานะ: ทั้งหมด</option>
            <option value="completed">สำเร็จ</option>
            <option value="pending">รอดำเนินการ</option>
            <option value="cancelled">ยกเลิก</option>
          </select>
        </div>


      </div>
    </div>
    <!-- ปุ่มค้นหา -->
    <!-- <div class="flex items-center space-x-2">
          <button class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">ค้นหา</button>
          <button class="bg-gray-200 text-gray-600 px-4 py-2 rounded-md hover:bg-gray-300">ล้าง</button>
        </div> -->
    <!-- ตาราง (จากโค้ดก่อนหน้า) -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
      <table class="min-w-full border-collapse">
        <thead>
          <tr class="bg-gray-200 text-gray-700">
            <th class="py-3 px-4 text-left border-b">รหัสการขาย</th>
            <th class="py-3 px-4 text-left border-b">วันที่ขาย</th>
            <th class="py-3 px-4 text-left border-b">รหัสสถานะการขาย</th>
            <th class="py-3 px-4 text-left border-b">รหัสสมาชิก</th>
            <th class="py-3 px-4 text-left border-b">พนักงาน</th>
            <th class="py-3 px-4 text-left border-b">ยอดรวม</th>
            <th class="py-3 px-4 text-left border-b">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <tr class="hover:bg-gray-50">
            <td class="py-3 px-4 border-b text-gray-600">#0010</td>
            <td class="py-3 px-4 border-b text-gray-600">-</td>
            <td class="py-3 px-4 border-b text-gray-600">-</td>
            <td class="py-3 px-4 border-b text-gray-600">นายสมชาย ใจดี</td>
            <td class="py-3 px-4 border-b text-gray-600">28/04/2025 13:45</td>
            <td class="py-3 px-4 border-b text-gray-600">-</td>
            <td class="py-3 px-4 border-b text-gray-600">฿213.00</td>
            <td class="py-3 px-4 border-b">
              <a href="http://localhost:8000/?page=sale_detail" class="text-blue-500 hover:text-blue-700">ดูรายละเอียด</a>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="bg-gray-200 text-gray-700">
            <td colspan="6" class="py-3 px-4 text-right border-t font-semibold">รวม</td>
            <td class="py-3 px-4 border-t font-semibold">฿213.00</td>
            <td class="py-3 px-4 border-t"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</body>

</html>