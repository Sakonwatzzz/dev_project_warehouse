<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบตรวจสอบสต๊อกสินค้า</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.0/cdn.min.js" defer></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <style>

    </style>
</head>

<body class="bg-gray-50">
    <div x-data="stockCheckApp()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-blue-600 shadow-md">
            <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-white">ระบบตรวจสอบสต๊อกสินค้า</h1>
                <div class="flex items-center space-x-4">
                    <span class="text-white" x-text="currentDateTime"></span>
                    <div class="bg-white rounded-full p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 mb-6">
                <nav class="-mb-px flex space-x-8">
                    <button @click="activeTab = 'new'"
                        :class="activeTab === 'new' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-lg">
                        สร้างรายการตรวจสอบใหม่
                    </button>
                    <button @click="activeTab = 'history'"
                        :class="activeTab === 'history' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="py-4 px-1 border-b-2 font-medium text-lg">
                        ประวัติการตรวจสอบ
                    </button>
                </nav>
            </div>

            <!-- New Stock Check Form -->
            <div x-show="activeTab === 'new'" x-cloak>
                <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold mb-4">ข้อมูลการตรวจสอบสต๊อก</h2>

                    <!-- Warehouse Selection -->
                    <div class="mb-4">
                        <label for="warehouse" class="block text-gray-700 font-medium mb-2">คลังสินค้า</label>
                        <select id="warehouse" x-model="formData.warehouseId"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled selected>เลือกคลังสินค้า</option>
                            <template x-for="warehouse in warehouses" :key="warehouse.id">
                                <option :value="warehouse.id" x-text="warehouse.name"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Product Scanning -->
                    <div class="mb-4">
                        <label for="product-scan"
                            class="block text-gray-700 font-medium mb-2">สแกนบาร์โค้ดสินค้า</label>
                        <div class="flex space-x-2">
                            <input type="text" id="product-scan" x-model="scanInput" @keydown.enter="scanProduct"
                                placeholder="สแกนหรือป้อนรหัสสินค้า"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <button @click="scanProduct"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                สแกน
                            </button>
                        </div>
                    </div>

                    <!-- Lot Selection (shows after product scan) -->
                    <div x-show="currentProduct" class="mb-4">
                        <label for="lot" class="block text-gray-700 font-medium mb-2">เลือกล็อตสินค้า</label>
                        <select id="lot" x-model="formData.lotId" @change="selectLot"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled selected>เลือกล็อตสินค้า</option>
                            <template x-for="lot in availableLots" :key="lot.lotId">
                                <option :value="lot.lotId"
                                    x-text="`ล็อต ${lot.lotId} - ${lot.lotCode} (จำนวนคงเหลือ: ${lot.quantity})`">
                                </option>
                            </template>
                        </select>
                    </div>

                    <!-- Product Details -->
                    <div x-show="currentProduct && selectedLot" class="mb-6 bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-medium text-lg mb-2">ข้อมูลสินค้า</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-gray-600">รหัสสินค้า:</p>
                                <p class="font-medium" x-text="currentProduct.productId"></p>
                            </div>
                            <div>
                                <p class="text-gray-600">ชื่อสินค้า:</p>
                                <p class="font-medium" x-text="currentProduct.name"></p>
                            </div>
                            <div>
                                <p class="text-gray-600">จำนวนในระบบ:</p>
                                <p class="font-medium" x-text="selectedLot ? selectedLot.quantity : '-'"></p>
                            </div>
                            <div>
                                <p class="text-gray-600">หน่วย:</p>
                                <p class="font-medium" x-text="currentProduct.unit"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Actual Quantity Input -->
                    <div x-show="currentProduct && selectedLot" class="mb-4">
                        <label for="actual-quantity"
                            class="block text-gray-700 font-medium mb-2">จำนวนจริงที่นับได้</label>
                        <input type="number" id="actual-quantity" x-model="formData.actualQuantity" min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Difference Calculation -->
                    <div x-show="currentProduct && selectedLot && formData.actualQuantity !== ''"
                        class="mb-4 p-3 rounded-lg" :class="getDifferenceClass()">
                        <div class="flex justify-between items-center">
                            <span class="font-medium">ผลต่าง:</span>
                            <span class="font-bold text-lg" x-text="calculateDifference()"></span>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div x-show="currentProduct && selectedLot" class="mb-6">
                        <label for="remarks" class="block text-gray-700 font-medium mb-2">หมายเหตุ (ถ้ามี)</label>
                        <textarea id="remarks" x-model="formData.remarks" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            placeholder="ระบุสาเหตุของความคลาดเคลื่อน หรือข้อมูลเพิ่มเติม"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end">
                        <button @click="submitStockCheck" :disabled="!isFormValid()"
                            :class="isFormValid() ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 cursor-not-allowed'"
                            class="px-6 py-3 text-white rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            บันทึกการตรวจสอบ
                        </button>
                    </div>
                </div>

                <!-- Stock Check Queue -->
                <div x-show="stockCheckQueue.length > 0" class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">รายการตรวจสอบ</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        รหัสสินค้า</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ชื่อสินค้า</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ล็อต</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        จำนวนในระบบ</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        จำนวนจริง</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ผลต่าง</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        หมายเหตุ</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(check, index) in stockCheckQueue">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                           ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button
                                                class="text-red-600 hover:text-red-900">ลบ</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button @click="submitAllChecks"
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            บันทึกทั้งหมด
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stock Check History -->
            <div>
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-xl font-semibold mb-4">ประวัติการตรวจสอบสต๊อก</h2>

                    <!-- Filter Controls -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="date-filter" class="block text-gray-700 text-sm font-medium mb-2">วันที่</label>
                            <input type="date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label for="warehouse-filter"
                                class="block text-gray-700 text-sm font-medium mb-2">คลังสินค้า</label>
                            <select
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">ทั้งหมด</option>
                                <template x-for="warehouse in warehouses" :key="warehouse.id">
                                    <option :value="warehouse.id" x-text="warehouse.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label for="status-filter"
                                class="block text-gray-700 text-sm font-medium mb-2">สถานะ</label>
                            <select id="status-filter" x-model="filters.status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">ทั้งหมด</option>
                                <option value="pending">รอดำเนินการ</option>
                                <option value="approved">อนุมัติแล้ว</option>
                                <option value="rejected">ปฏิเสธ</option>
                            </select>
                        </div>
                    </div>

                    <!-- History Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        รหัสตรวจสอบ</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        วันที่ตรวจสอบ</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        คลังสินค้า</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        รหัสสินค้า</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ชื่อสินค้า</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ล็อต</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        จำนวนในระบบ</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        จำนวนจริง</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ผลต่าง</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        สถานะ</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(check, index) in filteredHistory" :key="index">
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"
                                           ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                           ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                                            ></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                >
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button @click="viewCheckDetails(check)"
                                                class="text-blue-600 hover:text-blue-900">รายละเอียด</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- No Results Message -->
                    <div x-show="filteredHistory.length === 0" class="py-8 text-center text-gray-500">
                        ไม่พบข้อมูลการตรวจสอบสต๊อก
                    </div>
                </div>
            </div>
        </main>

        <!-- Stock Check Details Modal -->
        <div x-show="showDetailModal" x-cloak class="fixed inset-0 overflow-y-auto" aria-labelledby="modal-title"
            role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                <!-- Modal panel -->
                <div
                    class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    รายละเอียดการตรวจสอบสต๊อก
                                </h3>
                                <div class="mt-4 space-y-3">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-500">รหัสตรวจสอบ:</p>
                                            <p class="font-medium"></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">วันที่ตรวจสอบ:</p>
                                            <p class="font-medium"></p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">คลังสินค้า:</p>
                                            <p class="font-medium">
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-sm text-gray-500">ผู้ตรวจสอบ:</p>
                                            <p class="font-medium"></p>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 pt-3">
                                        <h4 class="font-medium">ข้อมูลสินค้า</h4>
                                        <div class="grid grid-cols-2 gap-4 mt-2">
                                            <div>
                                                <p class="text-sm text-gray-500">รหัสสินค้า:</p>
                                                <p class="font-medium"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">ชื่อสินค้า:</p>
                                                <p class="font-medium"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">ล็อต:</p>
                                                <p class="font-medium"></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 pt-3">
                                        <h4 class="font-medium">ข้อมูลการตรวจสอบ</h4>
                                        <div class="grid grid-cols-3 gap-4 mt-2">
                                            <div>
                                                <p class="text-sm text-gray-500">จำนวนในระบบ:</p>
                                                <p class="font-medium"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">จำนวนจริง:</p>
                                                <p class="font-medium"></p>
                                            </div>
                                            <div>
                                                <p class="text-sm text-gray-500">ผลต่าง:</p>
                                                <p class="font-medium"></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 pt-3">
                                        <h4 class="font-medium">สถานะ</h4>
                                        <div class="mt-2">
                                            <span
                                                class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full">
                                            </span>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 pt-3">
                                        <h4 class="font-medium">หมายเหตุ</h4>
                                        <p class="mt-1 text-gray-700"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm"
                            @click="approveCheck">
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>