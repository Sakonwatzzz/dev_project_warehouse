<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าจอชำระเงิน POS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <div class="container mx-auto p-6 bg-white shadow-lg rounded-lg">
        <!-- ส่วนหัว -->
        <div class="flex justify-between items-center bg-red-400 text-white p-4 rounded-t-lg">
            <div>
                <h1 class="text-xl font-bold">RangyPOS</h1>
                <p class="text-sm">เลขที่: SE2022-07211</p>
                <p class="text-sm">วันที่: 27/09/2022 10:01</p>
            </div>
            <div class="flex space-x-2">
                <button class="p-2 bg-red-500 rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h18M12 3v18"></path>
                    </svg>
                </button>
                <button class="p-2 bg-red-500 rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- ยอดรวม -->
        <div class="text-center my-6">
            <h2 class="text-4xl font-bold text-gray-800">250.00 บาท</h2>
        </div>

        <!-- รายการสินค้า -->
        <div class="flex">
            <!-- รายการ -->
            <div class="w-3/4 bg-gray-200 p-4 rounded-lg">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-300">
                            <th class="p-2">#</th>
                            <th class="p-2">รายการ</th>
                            <th class="p-2">จำนวน</th>
                            <th class="p-2">ราคา</th>
                            <th class="p-2">รวม</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="p-2">1</td>
                            <td class="p-2">ลาเต้ร้อนหวานน้อย Rotting Tikky</td>
                            <td class="p-2">1</td>
                            <td class="p-2">105.00</td>
                            <td class="p-2">105.00</td>
                            <td class="p-2">
                                <button class="text-red-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <!-- เพิ่มรายการอื่นๆ ได้ตามต้องการ -->
                    </tbody>
                </table>
            </div>

            <!-- วิธีชำระเงิน -->
            <div class="w-1/4 ml-4 p-4 bg-gray-100 rounded-lg">
                <h3 class="font-bold mb-4">วิธีชำระเงิน</h3>
                <div class="space-y-2">
                    <button class="w-full bg-blue-500 text-white py-2 rounded-lg">เงินสด</button>
                    <button class="w-full bg-blue-500 text-white py-2 rounded-lg">QR / โอน</button>
                    <button class="w-full bg-blue-500 text-white py-2 rounded-lg">Alipay</button>
                    <button class="w-full bg-red-500 text-white py-2 rounded-lg">True Money</button>
                </div>
            </div>
        </div>

        <!-- ปุ่มด้านล่าง -->
        <div class="flex justify-between mt-6">
            <div class="space-x-2">
                <button class="bg-yellow-400 text-black py-2 px-4 rounded-lg">เพิ่มส่วนลด</button>
                <button class="bg-orange-400 text-black py-2 px-4 rounded-lg">หมายเหตุ</button>
            </div>
            <div class="space-x-2">
                <button class="bg-red-400 text-white py-2 px-4 rounded-lg">ยกเลิก</button>
                <button class="bg-green-500 text-white py-2 px-4 rounded-lg">ชำระเงิน</button>
            </div>
        </div>
    </div>
</body>
</html>