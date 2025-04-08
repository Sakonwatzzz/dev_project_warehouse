<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบสั่งเครื่องดื่ม</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen">
        <!-- Sidebar: รายการคำสั่งซื้อ -->
        <div class="w-1/3 bg-white shadow-lg p-4 overflow-y-auto">
            <h2 class="text-lg font-bold mb-4">ยอดรวม 180.20</h2>
            <ul>
                <!-- รายการคำสั่งซื้อ -->
                <li class="flex justify-between items-center py-2 border-b">
                    <div class="flex items-center">
                        <img src="https://via.placeholder.com/40" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                        <div>
                            <p class="font-semibold">คุณ B</p>
                            <p class="text-sm text-gray-500">9:00</p>
                        </div>
                    </div>
                    <span class="text-green-600 font-semibold">9.00</span>
                </li>
                <li class="flex justify-between items-center py-2 border-b">
                    <div class="flex items-center">
                        <img src="https://via.placeholder.com/40" alt="Profile" class="w-10 h-10 rounded-full mr-3">
                        <div>
                            <p class="font-semibold">คุณน้องน้ำผลไม้</p>
                            <p class="text-sm text-gray-500">10:30</p>
                        </div>
                    </div>
                    <span class="text-green-600 font-semibold">8.50</span>
                </li>
                <!-- เพิ่มรายการอื่นๆ ได้ตามต้องการ -->
            </ul>
        </div>

        <!-- Main Content: เมนูเครื่องดื่ม -->
        <div class="w-2/3 p-6">
            <!-- หัวข้อและช่องค้นหา -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">เครื่องดื่ม</h1>
                <div class="relative">
                    <input type="text" placeholder="ค้นหา" class="border rounded-lg py-2 px-4 pl-10">
                    <svg class="w-5 h-5 absolute left-3 top-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

            <!-- รายการเมนู -->
            <div class="grid grid-cols-3 gap-6">
                <!-- เมนูเครื่องดื่ม -->
                <div class="bg-white rounded-lg shadow-md p-4 text-center">
                    <img src="https://via.placeholder.com/150" alt="Drink" class="w-full h-32 object-cover rounded-lg mb-3">
                    <h3 class="font-semibold">ลาเต้ร้อน</h3>
                    <p class="text-gray-500">ลาเต้ร้อนหวานน้อย x 2</p>
                    <p class="text-green-600 font-bold">170.00</p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-4 text-center">
                    <img src="https://via.placeholder.com/150" alt="Drink" class="w-full h-32 object-cover rounded-lg mb-3">
                    <h3 class="font-semibold">Ginger Beer</h3>
                    <p class="text-gray-500">ขิง 50%</p>
                    <p class="text-green-600 font-bold">50.00</p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-4 text-center">
                    <img src="https://via.placeholder.com/150" alt="Drink" class="w-full h-32 object-cover rounded-lg mb-3">
                    <h3 class="font-semibold">โซดา</h3>
                    <p class="text-gray-500">โซดาเย็น</p>
                    <p class="text-green-600 font-bold">14.00</p>
                </div>
                <!-- เพิ่มเมนูอื่นๆ ได้ตามต้องการ -->
            </div>

            <!-- ปุ่มด้านล่าง -->
            <div class="flex justify-end mt-6 space-x-4">
                <button class="bg-gray-300 text-gray-700 py-2 px-6 rounded-lg">ยกเลิก</button>
                <button class="bg-green-500 text-white py-2 px-6 rounded-lg">ชำระเงิน</button>
            </div>
        </div>
    </div>
</body>
</html>