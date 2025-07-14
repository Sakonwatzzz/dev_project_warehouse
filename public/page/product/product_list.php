<?php
require_once __DIR__ . '../../../../config/database_server.php';
date_default_timezone_set('Asia/Bangkok');

$pdo = Database::getInstance()->getConnection();

$incomplete_mode = isset($_GET['mode']) && $_GET['mode'] === 'incomplete';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$selected_types = isset($_GET['product_type']) ? array_filter(array_map('intval', explode(',', $_GET['product_type']))) : [];
$selected_brands = isset($_GET['product_brand']) ? array_filter(array_map('intval', explode(',', $_GET['product_brand']))) : [];
$page = isset($_GET['page_num']) ? max(1, (int)$_GET['page_num']) : 1;

try {
    $typeLimit = 5;
    $brandLimit = 5;
    $totalTypeCount = $pdo->query("SELECT COUNT(*) FROM product_type")->fetchColumn();
    $totalBrandCount = $pdo->query("SELECT COUNT(*) FROM product_brand")->fetchColumn();

    $type_stmt = $pdo->prepare("SELECT product_type_id, product_type_name FROM product_type LIMIT ?");
    $type_stmt->bindValue(1, $typeLimit, PDO::PARAM_INT);
    $type_stmt->execute();
    $typeResults = $type_stmt->fetchAll(PDO::FETCH_ASSOC);

    $brand_stmt = $pdo->prepare("SELECT product_brand_id, product_brand_name FROM product_brand LIMIT ?");
    $brand_stmt->bindValue(1, $brandLimit, PDO::PARAM_INT);
    $brand_stmt->execute();
    $brandResults = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "ข้อผิดพลาดฐานข้อมูล: " . htmlspecialchars($e->getMessage());
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการสินค้า</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../style/product_list.css" rel="stylesheet">
</head>

<body class="bg-gradient-to-br from-blue-50 to-white min-h-screen font-sans text-base">
    <div class="mx-auto px-4 py-6">
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Filters Sidebar -->
            <div class="w-full lg:w-1/4">
                <div class="glass-effect rounded-xl p-5 card-shadow filter-card">
                    <button id="toggleFilters" class="lg:hidden w-full bg-blue-50 text-blue-700 py-2 px-4 rounded-lg mb-3 flex items-center justify-between hover:bg-blue-100 transition">
                        <span class="font-medium">ตัวกรอง</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="filters" class="hidden lg:block">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2 mb-4">
                            <i class="fas fa-filter text-blue-500"></i>ค้นหาแบบละเอียด
                        </h2>
                        <div id="typesBrandsContainer">
                            <div class="mb-6">
                                <h3 class="text-base font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                    <i class="fas fa-tags text-blue-500"></i>หมวดหมู่สินค้า
                                </h3>
                                <div class="space-y-2">
                                    <?php foreach ($typeResults as $typeRow): ?>
                                        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-blue-50 transition cursor-pointer">
                                            <input type="checkbox" name="product_type[]" value="<?php echo $typeRow['product_type_id']; ?>"
                                                class="w-4 h-4 text-blue-600 border-blue-300 rounded focus:ring-blue-500"
                                                <?php echo in_array($typeRow['product_type_id'], $selected_types) ? 'checked' : ''; ?>
                                                onchange="updateFilters()">
                                            <span class="text-gray-700"><?php echo htmlspecialchars($typeRow['product_type_name']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                    <div class="flex gap-3 mt-3">
                                        <?php if ($typeLimit < $totalTypeCount): ?>
                                            <button onclick="showMoreTypes(<?php echo $typeLimit + 5; ?>, <?php echo $brandLimit; ?>)" class="w-1/2 bg-blue-50 text-blue-600 py-2 rounded-lg hover:bg-blue-100 standard-button-sm">แสดงเพิ่ม</button>
                                        <?php endif; ?>
                                        <?php if ($typeLimit > 5): ?>
                                            <button onclick="showMoreTypes(5, <?php echo $brandLimit; ?>)" class="w-1/2 bg-blue-50 text-blue-600 py-2 rounded-lg hover:bg-blue-100 standard-button-sm">แสดงน้อย</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-6">
                                <h3 class="text-base font-semibold text-gray-700 mb-3 flex items-center gap-2">
                                    <i class="fas fa-star text-blue-500"></i>แบรนด์สินค้า
                                </h3>
                                <div class="space-y-2">
                                    <?php foreach ($brandResults as $brandRow): ?>
                                        <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-blue-50 transition cursor-pointer">
                                            <input type="checkbox" name="product_brand[]" value="<?php echo $brandRow['product_brand_id']; ?>"
                                                class="w-4 h-4 text-blue-600 border-blue-300 rounded focus:ring-blue-500"
                                                <?php echo in_array($brandRow['product_brand_id'], $selected_brands) ? 'checked' : ''; ?>
                                                onchange="updateFilters()">
                                            <span class="text-gray-700"><?php echo htmlspecialchars($brandRow['product_brand_name']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                    <div class="flex gap-3 mt-3">
                                        <?php if ($brandLimit < $totalBrandCount): ?>
                                            <button onclick="showMoreBrands(<?php echo $typeLimit; ?>, <?php echo $brandLimit + 5; ?>)" class="w-1/2 bg-blue-50 text-blue-600 py-2 rounded-lg hover:bg-blue-100 standard-button-sm">แสดงเพิ่ม</button>
                                        <?php endif; ?>
                                        <?php if ($brandLimit > 5): ?>
                                            <button onclick="showMoreBrands(<?php echo $typeLimit; ?>, 5)" class="w-1/2 bg-blue-50 text-blue-600 py-2 rounded-lg hover:bg-blue-100 standard-button-sm">แสดงน้อย</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button onclick="clearFilters()" class="w-full bg-gray-100 text-gray-700 py-2 rounded-lg hover:bg-gray-200 transition standard-button">
                            <i class="fas fa-times mr-1"></i>ล้างตัวกรอง
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="w-full lg:w-3/4">
                <div class="glass-effect rounded-xl p-6 card-shadow animate-fade-in">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-3">
                        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-boxes text-blue-500"></i>รายการสินค้า
                            <?php if ($incomplete_mode): ?>
                                <span class="bg-red-100 text-red-700 px-2 py-1 rounded-full text-sm font-medium">ข้อมูลไม่ครบ</span>
                            <?php endif; ?>
                        </h1>
                        <a href="/?page=product_insert" class="btn-primary text-white py-2 px-4 rounded-lg font-medium hover-lift">
                            <i class="fas fa-plus mr-1"></i>เพิ่มสินค้า
                        </a>
                    </div>
                    <!-- Search Section -->
                    <form id="searchForm" class="mb-6 w-full max-w-2xl ">
                        <input type="hidden" name="page" value="product_list">
                        <input type="hidden" name="page_num" value="1">

                        <div class="relative">
                            <!-- Input Field -->
                            <input
                                type="text"
                                id="searchInput"
                                name="search"
                                class="w-full border border-gray-300 rounded-lg pl-12 pr-12 py-3 text-sm md:text-base bg-white/70 backdrop-blur-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-400 transition-all duration-200"
                                placeholder="ค้นหาสินค้าหรือรหัสสินค้า..."
                                value="<?php echo htmlspecialchars($search); ?>">

                            <!-- Search Icon -->
                            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-base md:text-lg"></i>

                            <!-- Clear Button -->
                            <button
                                type="button"
                                id="clearSearch"
                                class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-red-500 transition <?php echo $search ? '' : 'hidden'; ?>">
                                <i class="fas fa-times-circle text-base md:text-lg"></i>
                            </button>
                        </div>
                    </form>

                    <!-- Controls Section -->
                    <div class="glass-effect rounded-xl p-4 mb-6 card-shadow">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                                <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                    <i class="fas fa-calendar-alt text-blue-500"></i>กรองตามวันที่
                                </h3>
                                <div class="flex flex-col xs:flex-row gap-2">
                                    <input type="date" id="start_date"
                                        class="border border-blue-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white/80 standard-input-mobile"
                                        value="<?php echo htmlspecialchars($start_date); ?>">
                                    <input type="date" id="end_date"
                                        class="border border-blue-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white/80 standard-input-mobile"
                                        value="<?php echo htmlspecialchars($end_date); ?>">
                                </div>
                            </div>
                            <div class="flex flex-col xs:flex-row gap-2">
                                <!-- Mode Toggle Buttons -->
                                <button id="showAllBtn" class="bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600 transition hover-lift flex items-center gap-2 standard-button-mobile <?php echo !$incomplete_mode ? 'bg-blue-700' : ''; ?>">
                                    <i class="fas fa-list"></i><span>แสดงทั้งหมด</span>
                                </button>
                                <button id="showIncompleteBtn" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition hover-lift flex items-center gap-2 standard-button-mobile <?php echo $incomplete_mode ? 'bg-red-700' : ''; ?>">
                                    <i class="fas fa-exclamation-triangle"></i><span>แสดงสินค้าไม่สมบูรณ์</span>
                                </button>
                                <button onclick="exportPDF()" class="bg-red-500 text-white py-2 px-4 rounded-lg hover:bg-red-600 transition hover-lift flex items-center gap-2 standard-button-mobile">
                                    <i class="fas fa-file-pdf"></i><span>PDF</span>
                                </button>
                                <button onclick="exportExcel()" class="bg-emerald-500 text-white py-2 px-4 rounded-lg hover:bg-emerald-600 transition hover-lift flex items-center gap-2 standard-button-mobile">
                                    <i class="fas fa-file-excel"></i><span>Excel</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Table -->
                    <div id="productTable" class="animate-fade-in">
                        <!-- ตารางจะถูกโหลดผ่าน AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Track current mode
        let currentMode = '<?php echo $incomplete_mode ? 'incomplete' : ''; ?>';

        document.addEventListener('DOMContentLoaded', () => {
            const searchForm = document.getElementById('searchForm');
            const searchInput = document.getElementById('searchInput');
            const clearSearch = document.getElementById('clearSearch');
            const toggleFilters = document.getElementById('toggleFilters');
            const filters = document.getElementById('filters');
            const showAllBtn = document.getElementById('showAllBtn');
            const showIncompleteBtn = document.getElementById('showIncompleteBtn');

            // ป้องกันการ submit ฟอร์ม
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                updateTable();
            });

            // การจัดการช่องค้นหา
            searchInput.addEventListener('input', () => {
                clearSearch.style.display = searchInput.value.length > 0 ? 'block' : 'none';
                debounceUpdateTable();
            });

            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    searchInput.value = '';
                    clearSearch.style.display = 'none';
                    updateTable();
                }
            });

            clearSearch.addEventListener('click', () => {
                searchInput.value = '';
                clearSearch.style.display = 'none';
                updateTable();
            });

            // การสลับแสดง/ซ่อนตัวกรอง
            toggleFilters.addEventListener('click', () => {
                filters.classList.toggle('hidden');
                const chevron = toggleFilters.querySelector('i');
                chevron.classList.toggle('fa-chevron-down');
                chevron.classList.toggle('fa-chevron-up');
            });

            // การอัปเดตเมื่อเปลี่ยนตัวกรอง
            document.querySelectorAll('input[type="checkbox"], input[type="date"]').forEach(input => {
                input.addEventListener('change', updateFilters);
            });

            // Handle mode toggle buttons
            showAllBtn.addEventListener('click', () => {
                currentMode = '';
                showAllBtn.classList.add('bg-blue-700');
                showAllBtn.classList.remove('bg-blue-500');
                showIncompleteBtn.classList.add('bg-red-500');
                showIncompleteBtn.classList.remove('bg-red-700');
                updateTable();
            });

            showIncompleteBtn.addEventListener('click', () => {
                currentMode = 'incomplete';
                showIncompleteBtn.classList.add('bg-red-700');
                showIncompleteBtn.classList.remove('bg-red-500');
                showAllBtn.classList.add('bg-blue-500');
                showAllBtn.classList.remove('bg-blue-700');
                updateTable();
            });

            // อัปเดตตารางเมื่อโหลดหน้า
            updateTable();

            // การแจ้งเตือนเมื่อโหลดหน้า
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('edit_success')) {
                showNotification('แก้ไขสินค้าสำเร็จ!', 'success');
            } else if (urlParams.has('delete_success')) {
                showNotification('ลบสินค้าสำเร็จ!', 'success');
            }
        });

        // Debounce สำหรับลดการเรียก updateTable
        let debounceTimer;

        function debounceUpdateTable() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(updateTable, 300);
        }

        function updateFilters() {
            updateTable();
        }

        function updateTable() {
            const urlParams = new URLSearchParams();
            urlParams.set('page', 'product_list');
            urlParams.set('page_num', '1');
            if (currentMode) {
                urlParams.set('mode', currentMode);
            }
            const productTypes = Array.from(document.querySelectorAll('input[name="product_type[]"]:checked')).map(cb => cb.value);
            if (productTypes.length > 0) {
                urlParams.set('product_type', productTypes.join(','));
            }
            const productBrands = Array.from(document.querySelectorAll('input[name="product_brand[]"]:checked')).map(cb => cb.value);
            if (productBrands.length > 0) {
                urlParams.set('product_brand', productBrands.join(','));
            }
            const searchInput = document.getElementById('searchInput').value.trim();
            if (searchInput) {
                urlParams.set('search', searchInput);
            }
            const startDate = document.getElementById('start_date').value;
            if (startDate) {
                urlParams.set('start_date', startDate);
            }
            const endDate = document.getElementById('end_date').value;
            if (endDate) {
                urlParams.set('end_date', endDate);
            }

            fetch(`/page/product/ajax/product_table.php?${urlParams.toString()}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.text();
                })
                .then(data => {
                    try {
                        const json = JSON.parse(data);
                        showNotification(json.error, 'error');
                        document.getElementById('productTable').innerHTML = '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
                    } catch (e) {
                        document.getElementById('productTable').innerHTML = data;
                        history.pushState({}, '', `?${urlParams.toString()}`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('เกิดข้อผิดพลาดขณะโหลดตาราง', 'error');
                    document.getElementById('productTable').innerHTML = '<div class="text-center py-4 text-gray-600">เกิดข้อผิดพลาด</div>';
                });
        }

        function goToPage(pageNum) {
            const urlParams = new URLSearchParams();
            urlParams.set('page', 'product_list');
            urlParams.set('page_num', pageNum);
            if (currentMode) {
                urlParams.set('mode', currentMode);
            }
            const productTypes = Array.from(document.querySelectorAll('input[name="product_type[]"]:checked')).map(cb => cb.value);
            if (productTypes.length > 0) {
                urlParams.set('product_type', productTypes.join(','));
            }
            const productBrands = Array.from(document.querySelectorAll('input[name="product_brand[]"]:checked')).map(cb => cb.value);
            if (productBrands.length > 0) {
                urlParams.set('product_brand', productBrands.join(','));
            }
            const searchInput = document.getElementById('searchInput').value.trim();
            if (searchInput) {
                urlParams.set('search', searchInput);
            }
            const startDate = document.getElementById('start_date').value;
            if (startDate) {
                urlParams.set('start_date', startDate);
            }
            const endDate = document.getElementById('end_date').value;
            if (endDate) {
                urlParams.set('end_date', endDate);
            }

            fetch(`/page/product/ajax/product_table.php?${urlParams.toString()}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.text();
                })
                .then(data => {
                    try {
                        const json = JSON.parse(data);
                        showNotification(json.error, 'error');
                        document.getElementById('productTable').innerHTML = '<div class="text-center py-4 text-gray-600">ไม่สามารถโหลดข้อมูลได้</div>';
                    } catch (e) {
                        document.getElementById('productTable').innerHTML = data;
                        history.pushState({}, '', `?${urlParams.toString()}`);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('เกิดข้อผิดพลาดขณะโหลดตาราง', 'error');
                    document.getElementById('productTable').innerHTML = '<div class="text-center py-4 text-gray-600">เกิดข้อผิดพลาด</div>';
                });
        }

        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            document.querySelectorAll('input[name="product_type[]"], input[name="product_brand[]"]').forEach(cb => cb.checked = false);
            document.getElementById('clearSearch').style.display = 'none';
            updateTable();
            loadTypesBrands(5, 5); // รีเซ็ตหมวดหมู่/แบรนด์
        }

        function exportPDFLibs() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('export', 'pdf');
            window.open(`/page/product/report/export_product_pdf.php?${urlParams.toString()}`);
        }

        function exportExcel() {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('export', 'excel');
            window.open(`/page/product/report/export_product_excel.php?${urlParams.toString()}`);
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} animate-fade-in`;
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        function deleteProduct(id) {
            if (!confirm('ยืนยันการลบ?')) return;

            fetch('/page/product/ajax/delete_product.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `id=${encodeURIComponent(id)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        updateTable();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('เกิดข้อผิดพลาดขณะลบสินค้า', 'error');
                });
        }

        function showMoreTypes(typeLimit, brandLimit) {
            loadTypesBrands(typeLimit, brandLimit);
        }

        function showMoreBrands(typeLimit, brandLimit) {
            loadTypesBrands(typeLimit, brandLimit);
        }

        function loadTypesBrands(typeLimit, brandLimit) {
            const productTypes = Array.from(document.queryelectorAll('input[name="product_type[]"]:checked')).map(cb => cb.value);
            const productBrands = Array.from(document.querySelectorAll('input[name="product_brand[]"]:checked')).map(cb => cb.value);

            fetch('/page/product/ajax/ajax_types_brands.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `type_limit=${encodeURIComponent(typeLimit)}&brand_limit=${encodeURIComponent(brandLimit)}&product_type=${encodeURIComponent(productTypes.join(','))}&product_brand=${encodeURIComponent(productBrands.join(','))}`
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.text();
                })
                .then(data => {
                    try {
                        const json = JSON.parse(data);
                        showNotification(json.error, 'error');
                    } catch (e) {
                        document.getElementById('typesBrandsContainer').innerHTML = data;
                        document.querySelectorAll('input[name="product_type[]"], input[name="product_brand[]"]').forEach(input => {
                            input.addEventListener('change', updateFilters);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('เกิดข้อผิดพลาดขณะโหลดตัวกรอง', 'error');
                });
        }
    </script>
</body>

</html>