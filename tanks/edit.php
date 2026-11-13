<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'تعديل الخزان';

// التحقق من رقم الخزان الممرر في الرابط
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('رقم الخزان غير صحيح.');
}

// جلب بيانات الخزان الحالي
$stmtTank = $pdo->prepare("
    SELECT *
    FROM tanks
    WHERE id = :id
    LIMIT 1
");
$stmtTank->execute([':id' => $id]);
$tank = $stmtTank->fetch(PDO::FETCH_ASSOC);

if (!$tank) {
    die('الخزان غير موجود.');
}

// جلب أنواع الوقود النشطة للقائمة المنسدلة
$stmtTypes = $pdo->query("
    SELECT id, code, name
    FROM fuel_types
    WHERE is_active = 1
    ORDER BY name
");
$fuelTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tankNo = trim($_POST['tank_no'] ?? '');
    $name = trim($_POST['name'] ?? '');

    $fuelTypeId = filter_input(
        INPUT_POST,
        'fuel_type_id',
        FILTER_VALIDATE_INT
    );

    $capacity = trim($_POST['capacity'] ?? '');
    $currentVolume = trim($_POST['current_volume'] ?? '');
    $minimumLevel = trim($_POST['minimum_level'] ?? '');

    $status = $_POST['status'] ?? 'active';

    if ($tankNo === '') {
        $error = 'رقم الخزان مطلوب.';
    } elseif ($name === '') {
        $error = 'اسم الخزان مطلوب.';
    } elseif (!$fuelTypeId) {
        $error = 'اختر نوع الوقود.';
    } elseif ($capacity === '' || !is_numeric($capacity)) {
        $error = 'أدخل سعة صحيحة.';
    } elseif ((float)$capacity < 0) {
        $error = 'السعة لا يمكن أن تكون سالبة.';
    } elseif ($currentVolume === '' || !is_numeric($currentVolume)) {
        $error = 'أدخل الرصيد الحالي بشكل صحيح.';
    } elseif ((float)$currentVolume < 0) {
        $error = 'الرصيد الحالي لا يمكن أن يكون سالبًا.';
    } elseif ($minimumLevel === '' || !is_numeric($minimumLevel)) {
        $error = 'أدخل الحد الأدنى بشكل صحيح.';
    } elseif ((float)$minimumLevel < 0) {
        $error = 'الحد الأدنى لا يمكن أن يكون سالبًا.';
    } elseif (!in_array($status, ['active', 'inactive', 'maintenance'], true)) {
        $error = 'حالة الخزان غير صحيحة.';
    } elseif ((float)$currentVolume > (float)$capacity) {
        $error = 'الرصيد الحالي لا يمكن أن يكون أكبر من سعة الخزان.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE tanks
                SET
                    tank_no = :tank_no,
                    name = :name,
                    fuel_type_id = :fuel_type_id,
                    capacity = :capacity,
                    current_volume = :current_volume,
                    minimum_level = :minimum_level,
                    status = :status
                WHERE id = :id
            ");

            $stmt->execute([
                ':tank_no' => $tankNo,
                ':name' => $name,
                ':fuel_type_id' => $fuelTypeId,
                ':capacity' => $capacity,
                ':current_volume' => $currentVolume,
                ':minimum_level' => $minimumLevel,
                ':status' => $status,
                ':id' => $id
            ]);

            header('Location: index.php?success=updated');
            exit;

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'رقم الخزان مستخدم بالفعل.';
            } else {
                $error = 'خطأ قاعدة البيانات: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="form-card">

    <h2>تعديل الخزان</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="form-group">
            <label for="tank_no">رقم الخزان</label>
            <input
                type="text"
                id="tank_no"
                name="tank_no"
                class="form-control"
                value="<?= htmlspecialchars($_POST['tank_no'] ?? $tank['tank_no']) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="name">اسم الخزان</label>
            <input
                type="text"
                id="name"
                name="name"
                class="form-control"
                value="<?= htmlspecialchars($_POST['name'] ?? $tank['name']) ?>"
                placeholder="مثال: الخزان الرئيسي"
                required
            >
        </div>

        <div class="form-group">
            <label for="fuel_type_id">نوع الوقود</label>
            <select
                id="fuel_type_id"
                name="fuel_type_id"
                class="form-control"
                required
            >
                <option value="">-- اختر نوع الوقود --</option>
                <?php foreach ($fuelTypes as $fuel): ?>
                    <option
                        value="<?= $fuel['id'] ?>"
                        <?= (($fuelTypeId ?? $tank['fuel_type_id']) == $fuel['id']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($fuel['name']) ?> - <?= htmlspecialchars($fuel['code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="capacity">سعة الخزان</label>
            <input
                type="number"
                id="capacity"
                name="capacity"
                class="form-control"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars($_POST['capacity'] ?? $tank['capacity']) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="current_volume">الرصيد الحالي</label>
            <input
                type="number"
                id="current_volume"
                name="current_volume"
                class="form-control"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars($_POST['current_volume'] ?? $tank['current_volume']) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="minimum_level">الحد الأدنى للمخزون</label>
            <input
                type="number"
                id="minimum_level"
                name="minimum_level"
                class="form-control"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars($_POST['minimum_level'] ?? $tank['minimum_level']) ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="status">الحالة</label>
            <?php $currentStatus = $_POST['status'] ?? $tank['status']; ?>
            <select
                id="status"
                name="status"
                class="form-control"
            >
                <option value="active" <?= $currentStatus === 'active' ? 'selected' : '' ?>>نشط</option>
                <option value="inactive" <?= $currentStatus === 'inactive' ? 'selected' : '' ?>>غير نشط</option>
                <option value="maintenance" <?= $currentStatus === 'maintenance' ? 'selected' : '' ?>>صيانة</option>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                حفظ التعديلات
            </button>
            <a href="index.php" class="btn btn-secondary">
                إلغاء
            </a>
        </div>

    </form>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>