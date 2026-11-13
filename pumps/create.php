<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'إضافة طرمبة';

$error = '';

$stmt = $pdo->query("
    SELECT
        t.id,
        t.tank_no,
        t.name,
        ft.name AS fuel_name,
        ft.code AS fuel_code
    FROM tanks t
    INNER JOIN fuel_types ft
        ON ft.id = t.fuel_type_id
    WHERE t.status = 'active'
    ORDER BY t.tank_no
");

$tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pumpNo = trim($_POST['pump_no'] ?? '');

    $name = trim($_POST['name'] ?? '');

    $tankId = filter_input(
        INPUT_POST,
        'tank_id',
        FILTER_VALIDATE_INT
    );

    $status = $_POST['status'] ?? 'active';


    if ($pumpNo === '') {

        $error = 'رقم الطرمبة مطلوب.';

    } elseif ($name === '') {

        $error = 'اسم الطرمبة مطلوب.';

    } elseif (!$tankId) {

        $error = 'اختر الخزان.';

    } elseif (
        !in_array(
            $status,
            ['active', 'inactive', 'maintenance'],
            true
        )
    ) {

        $error = 'حالة الطرمبة غير صحيحة.';

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO pumps
                (
                    pump_no,
                    name,
                    tank_id,
                    status
                )
                VALUES
                (
                    :pump_no,
                    :name,
                    :tank_id,
                    :status
                )
            ");

            $stmt->execute([
                ':pump_no' => $pumpNo,
                ':name' => $name,
                ':tank_id' => $tankId,
                ':status' => $status
            ]);

            header(
                'Location: index.php?success=created'
            );

            exit;

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                $error = 'رقم الطرمبة مستخدم بالفعل.';

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

    <h2>إضافة طرمبة</h2>


    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST">


        <div class="form-group">

            <label for="pump_no">
                رقم الطرمبة
            </label>

            <input
                type="text"
                id="pump_no"
                name="pump_no"
                class="form-control"
                value="<?= htmlspecialchars($_POST['pump_no'] ?? '') ?>"
                placeholder="مثال: PUMP-01"
                required
            >

        </div>


        <div class="form-group">

            <label for="name">
                اسم الطرمبة
            </label>

            <input
                type="text"
                id="name"
                name="name"
                class="form-control"
                value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                placeholder="مثال: الطرمبة رقم 1"
                required
            >

        </div>


        <div class="form-group">

            <label for="tank_id">
                الخزان
            </label>

            <select
                id="tank_id"
                name="tank_id"
                class="form-control"
                required
            >

                <option value="">
                    -- اختر الخزان --
                </option>

                <?php foreach ($tanks as $tank): ?>

                    <option
                        value="<?= $tank['id'] ?>"
                        <?= (
                            isset($_POST['tank_id'])
                            &&
                            $_POST['tank_id'] == $tank['id']
                        )
                            ? 'selected'
                            : '' ?>
                    >

                        <?= htmlspecialchars($tank['tank_no']) ?>

                        -

                        <?= htmlspecialchars($tank['name']) ?>

                        -

                        <?= htmlspecialchars($tank['fuel_name']) ?>

                    </option>

                <?php endforeach; ?>

            </select>

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
                    نشطة
                </option>

                <option value="inactive">
                    غير نشطة
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
                حفظ الطرمبة
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
