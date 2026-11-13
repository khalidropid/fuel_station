<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'إضافة خزان';

$error = '';

$stmt = $pdo->query("
    SELECT id, code, name, unit
    FROM fuel_types
    WHERE is_active = 1
    ORDER BY name
");

$fuelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);


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

    } elseif (
        !in_array(
            $status,
            ['active', 'inactive', 'maintenance'],
            true
        )
    ) {

        $error = 'حالة الخزان غير صحيحة.';

    } elseif ((float)$currentVolume > (float)$capacity) {

        $error = 'الرصيد الحالي لا يمكن أن يكون أكبر من سعة الخزان.';

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO tanks
                (
                    tank_no,
                    name,
                    fuel_type_id,
                    capacity,
                    current_volume,
                    minimum_level,
                    status
                )
                VALUES
                (
                    :tank_no,
                    :name,
                    :fuel_type_id,
                    :capacity,
                    :current_volume,
                    :minimum_level,
                    :status
                )
            ");

            $stmt->execute([
                ':tank_no' => $tankNo,
                ':name' => $name,
                ':fuel_type_id' => $fuelTypeId,
                ':capacity' => $capacity,
                ':current_volume' => $currentVolume,
                ':minimum_level' => $minimumLevel,
                ':status' => $status
            ]);

            header(
                'Location: index.php?success=created'
            );

            exit;

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                $error = 'رقم الخزان مستخدم بالفعل.';

            } else {

                $error =
                    'خطأ قاعدة البيانات: ' .
                    $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="form-card">

    <h2>إضافة خزان</h2>


    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST">


        <div class="form-group">

            <label for="tank_no">
                رقم الخزان
            </label>

            <input
                type="text"
                id="tank_no"
                name="tank_no"
                class="form-control"
                value="<?= htmlspecialchars($_POST['tank_no'] ?? '') ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="name">
                اسم الخزان
            </label>

            <input
                type="text"
                id="name"
                name="name"
                class="form-control"
                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                placeholder="مثال: الخزان الرئيسي"
                required
            >

        </div>


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
                        <?= (
                            isset($_POST['fuel_type_id'])
                            &&
                            $_POST['fuel_type_id'] == $fuel['id']
                        )
                            ? 'selected'
                            : '' ?>
                    >

                        <?= htmlspecialchars($fuel['name']) ?>

                        -
                        <?= htmlspecialchars($fuel['code']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-group">

            <label for="capacity">
                سعة الخزان
            </label>

            <input
                type="number"
                id="capacity"
                name="capacity"
                class="form-control"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars($_POST['capacity'] ?? '') ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="current_volume">
                الرصيد الحالي
            </label>

            <input
                type="number"
                id="current_volume"
                name="current_volume"
                class="form-control"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars($_POST['current_volume'] ?? '0') ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="minimum_level">
                الحد الأدنى للمخزون
            </label>

            <input
                type="number"
                id="minimum_level"
                name="minimum_level"
                class="form-control"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars($_POST['minimum_level'] ?? '0') ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="status">
                الحالة
            </label>

            <select
                id="status"
                name="status"
                class="form-control"
            >

                <option value="active">
                    نشط
                </option>

                <option value="inactive">
                    غير نشط
                </option>

                <option value="maintenance">
                    صيانة
                </option>

            </select>

        </div>


        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                حفظ الخزان
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
