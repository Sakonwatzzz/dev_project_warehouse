<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lot_insert_combined</title>
</head>

<body>
    <div class="">
        <h1 class="mx-11 my-8 text-4xl font-bold">รอบของสินค้า</h1>
        <div class="relative w-full h-[500px] mx-11 ">
            <!-- ส่วนแรก (ด้านซ้าย) -->
            <div class="absolute left-0 top-0 w-[30%] border border-[#7a7a7a] rounded-lg bg-[#cbdbe4]">
                <h2 class="text-2xl px-4 py-2">คลังสินค้า</h2>
                <table class="w-full">
                    <thead>
                        <tr>
                            <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800  px-3 py-2 border border-[#8f959b] shadow">รหัส</th>
                            <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800  px-3 py-2 border border-[#8f959b] shadow">ชื่อคลัง</th>
                            <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800  px-3 py-2 border border-[#8f959b] shadow">nor</th>
                            <th class="bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800  px-3 py-2 border border-[#8f959b] shadow">แยก</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="bg-[#fff] text-gray-600"></td>
                            <td class="bg-[#fff] text-gray-600"></td>
                            <td class="bg-[#fff] text-gray-600"></td>
                            <td class="bg-[#fff] text-gray-600"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ส่วนที่สอง (ด้านขวา) -->
            <div class="absolute right-[22rem] top-0 w-[50%] ">
                <div class="flex space-x-2 bg-gradient-to-b from-[#c8d8ed] via-[#c8d8ed] to-[#c8d8ed] text-gray-800 text-lg px-3 py-2 border border-[#8f959b] shadow">
                    <div class="">
                        <p class="text-[20px]  pb-2">เลขที่ lot สินค้า</p>
                        <input type="text" placeholder="กรอกเลขที่ lot" class="border px-2 py-1 rounded">
                    </div>
                    <div>
                        <p class="text-[20px]  pb-2">คำอธิบาย</p>
                        <input type="text" placeholder="กรอกคำอธิบาย" class="border px-2 py-1 rounded">

                    </div>
                    <div>
                        <p class="text-[20px]  pb-2">คลัง</p>
                        <div class=" space-x-1">
                            <select class="w-[200px] h-[36px]" name="" id=""></select>
                            <select class="w-[200px] h-[36px]" name="" id=""></select>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-5 gap-2 py-4 ">
                    <div class="p-2 bg-[#7cfe7f] border border-green-600 rounded-lg">
                        <div class="flex space-x-2 my-2 px-1">
                            <p class="flex items-center justify-center">2024/01/22</p>
                            <div class="flex items-center w-[70px] px-2 py-1 border border-[#426e42] bg-[#a5e5a5] rounded-sm">
                                <img src="../image/warehouse.png" alt="warehouse" class="w-6 h-6">
                                <p class="ml-2">
                                    1
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2 items-center justify-center">
                            <p class="bg-pink-400 px-5">1</p>
                            <p class="bg-pink-400 px-5">2</p>
                            <p class="bg-pink-400 px-5">3</p>
                        </div>
                    </div>
                    <div class="p-2 bg-[#7cfe7f] border border-green-600 rounded-lg">
                        <div class="flex space-x-2 my-2 px-1 ">
                            <p class="flex items-center justify-center">2024/01/22</p>
                            <div class="flex items-center w-[70px] px-2 py-1 border border-[#426e42] bg-[#a5e5a5] rounded-sm">
                                <img src="../image/warehouse.png" alt="warehouse" class="w-6 h-6">
                                <p class="ml-2">
                                    1
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2 items-center justify-center">
                            <p class="bg-pink-400 px-5">1</p>
                            <p class="bg-pink-400 px-5">2</p>
                            <p class="bg-pink-400 px-5">3</p>
                        </div>
                    </div>
                    <div class="p-2 bg-[#fb7d7e] border border-[#bd5959] rounded-lg">
                        <div class="flex space-x-2 my-2 px-1 ">
                            <p class="flex items-center justify-center">2024/01/22</p>
                            <div class="flex items-center w-[70px] px-2 py-1 border border-[#bd5959] bg-[#fcafaf] rounded-sm">
                                <img src="../image/warehouse.png" alt="warehouse" class="w-6 h-6">
                                <p class="ml-2">
                                    2
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2 items-center justify-center">
                            <p class="bg-green-300 px-5">1</p>
                            <p class="bg-green-300 px-5">2</p>
                            <p class="bg-green-300 px-5">3</p>
                        </div>
                    </div>
                    <div class="p-2 bg-[#fb7d7e] border border-[#bd5959] rounded-lg">
                        <div class="flex space-x-2 my-2 px-1 ">
                            <p class="flex items-center justify-center">2024/01/22</p>
                            <div class="flex items-center w-[70px] px-2 py-1 border border-[#bd5959] bg-[#fcafaf] rounded-sm">
                                <img src="../image/warehouse.png" alt="warehouse" class="w-6 h-6">
                                <p class="ml-2">
                                    2
                                </p>
                            </div>
                        </div>
                        <div class="flex space-x-2 items-center justify-center">
                            <p class="bg-green-300 px-5">1</p>
                            <p class="bg-green-300 px-5">2</p>
                            <p class="bg-green-300 px-5">3</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>

</html>