<?php
require_once __DIR__ . '../../../../../config/database_server.php';
header('Content-Type: text/html; charset=UTF-8');

try {
    $typeLimit = isset($_POST['type_limit']) ? max(5, (int)$_POST['type_limit']) : 5;
    $brandLimit = isset($_POST['brand_limit']) ? max(5, (int)$_POST['brand_limit']) : 5;
    $selected_types = isset($_POST['product_type']) ? array_filter(array_map('intval', explode(',', $_POST['product_type']))) : [];
    $selected_brands = isset($_POST['product_brand']) ? array_filter(array_map('intval', explode(',', $_POST['product_brand']))) : [];

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

    ob_start();
?>
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
<?php
    echo ob_get_clean();
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ข้อผิดพลาดฐานข้อมูล: ' . htmlspecialchars($e->getMessage())]);
    exit;
}
?>