<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('رقم تسعيرة الوقود غير صحيح.');
}

// جلب تفاصيل التسعيرة المراد تعديلها
$stmtPrice = $pdo->prepare("
    SELECT *
    FROM fuel_prices
    WHERE id = :id
    LIMIT 1
");
$stmtPrice->execute([':id' => $id]);
$fuelPrice = $stmtPrice->fetch(PDO::FETCH_ASSOC);

if (!$fuelPrice) {
    die('تسعيرة الوقود غير موجودة.');
}

// جلب قائمة أنواع الوقود النشطة لعرضها في القائمة المنسدلة
$stmtTypes = $pdo->query("
    SELECT id, code, name
    FROM fuel_types
    WHERE is_active = 1
    ORDER BY name
");
$fuelTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fuelTypeId = filter_input(INPUT_POST, 'fuel_type_id', FILTER_VALIDATE_INT);
    $price = trim($_POST['price'] ?? '');
    $effectiveFrom = trim($_POST['effective_from'] ?? '');

    if (!$fuelTypeId) {
        $error = 'اختر نوع الوقود.';
    } elseif ($price === '' || !is_numeric($price)) {
        $error = 'أدخل سعرًا صحيحًا.';
    } elseif ((float)$price < 0) {
        $error = 'السعر لا يمكن أن يكون سالبًا.';
    } elseif ($effectiveFrom === '') {
        $error = 'حدد تاريخ ووقت بداية السعر.';
    } else {

        try {

            $stmtUpdate = $pdo->prepare("
                UPDATE fuel_prices
                SET
                    fuel_type_id = :fuel_type_id,
                    price = :price,
                    effective_from = :effective_from
                WHERE id = :id
            ");

            $stmtUpdate->execute([
                ':fuel_type_id' => $fuelTypeId,
                ':price' => $price,
                ':effective_from' => $effectiveFrom,
                ':id' => $id
            ]);

            header('Location: index.php?success=updated');
            exit;

        } catch (PDOException $e) {
            $error = 'خطأ قاعدة البيانات: ' . $e->getMessage();
        }

    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="form-card">

    <h2>تعديل سعر الوقود</h2>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="form-group">

            <label for="fuel_type_id">
                نوع الوقود
            </label>

            <select
                id="fuel_type_id"
                name="fuel_type_id"
                class="form-control"
                required
            >
                <option value="">
                    -- اختر نوع الوقود --
                </option>

                <?php foreach ($fuelTypes as $fuel): ?>

                    <option
                        value="<?= $fuel['id'] ?>"
                        <?= ($fuelPrice['fuel_type_id'] == $fuel['id']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($fuel['name']) ?> - <?= htmlspecialchars($fuel['code']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

        <div class="form-group">

            <label for="price">
                سعر الوحدة
            </label>

            <input
                type="number"
                id="price"
                name="price"
                class="form-control"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars($_POST['price'] ?? $fuelPrice['price']) ?>"
                required
            >

        </div>

        <div class="form-group">

            <label for="effective_from">
                يبدأ تطبيق السعر من
            </label>

            <input
                type="datetime-local"
                id="effective_from"
                name="effective_from"
                class="form-control"
                value="<?= htmlspecialchars($_POST['effective_from'] ?? date('Y-m-d\TH:i', strtotime($fuelPrice['effective_from']))) ?>"
                required
            >

        </div>

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                حفظ التعديلات
            </button>

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                إلغاء
            </a>

        </div>

    </form>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>