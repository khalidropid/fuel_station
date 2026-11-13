<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'إضافة قراءة طرمبة';

$error = '';


// جلب الطرمبات النشطة
$stmt = $pdo->query("
    SELECT
        p.id,
        p.pump_no,
        p.name AS pump_name,
        t.name AS tank_name,
        ft.name AS fuel_name

    FROM pumps p

    INNER JOIN tanks t
        ON t.id = p.tank_id

    INNER JOIN fuel_types ft
        ON ft.id = t.fuel_type_id

    WHERE p.status = 'active'

    ORDER BY p.pump_no
");

$pumps = $stmt->fetchAll(PDO::FETCH_ASSOC);


// جلب الموظفين النشطين
$stmt = $pdo->query("
    SELECT
        id,
        employee_no,
        full_name

    FROM employees

    WHERE status = 'active'

    ORDER BY full_name
");

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pumpId = filter_input(
        INPUT_POST,
        'pump_id',
        FILTER_VALIDATE_INT
    );

    $reading = trim($_POST['reading'] ?? '');

    $readingDate = trim(
        $_POST['reading_date'] ?? ''
    );

    $readingType = $_POST['reading_type'] ?? 'manual';

    $employeeId = filter_input(
        INPUT_POST,
        'employee_id',
        FILTER_VALIDATE_INT
    );

    $notes = trim($_POST['notes'] ?? '');


    if (!$pumpId) {

        $error = 'يجب اختيار الطرمبة.';

    } elseif (
        $reading === '' ||
        !is_numeric($reading) ||
        (float)$reading < 0
    ) {

        $error = 'أدخل قراءة صحيحة للعداد.';

    } elseif ($readingDate === '') {

        $error = 'تاريخ القراءة مطلوب.';

    } elseif (
        !in_array(
            $readingType,
            ['opening', 'closing', 'manual'],
            true
        )
    ) {

        $error = 'نوع القراءة غير صحيح.';

    } else {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO pump_readings
                (
                    pump_id,
                    reading,
                    reading_date,
                    reading_type,
                    employee_id,
                    notes
                )
                VALUES
                (
                    :pump_id,
                    :reading,
                    :reading_date,
                    :reading_type,
                    :employee_id,
                    :notes
                )
            ");

            $stmt->execute([

                ':pump_id' => $pumpId,

                ':reading' => $reading,

                ':reading_date' => $readingDate,

                ':reading_type' => $readingType,

                ':employee_id' =>
                    $employeeId ?: null,

                ':notes' =>
                    $notes !== ''
                        ? $notes
                        : null

            ]);


            header(
                'Location: index.php?success=1'
            );

            exit;


        } catch (PDOException $e) {

            $error =
                'خطأ قاعدة البيانات: ' .
                $e->getMessage();

        }

    }

}


require_once __DIR__ . '/../includes/header.php';

?>

<div class="form-card">

    <h2>إضافة قراءة طرمبة</h2>


    <?php if ($error): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <form method="POST">


        <div class="form-group">

            <label for="pump_id">
                الطرمبة
            </label>

            <select
                id="pump_id"
                name="pump_id"
                class="form-control"
                required
            >

                <option value="">
                    -- اختر الطرمبة --
                </option>

                <?php foreach ($pumps as $pump): ?>

                    <option
                        value="<?= $pump['id'] ?>"
                        <?= (
                            isset($_POST['pump_id'])
                            &&
                            $_POST['pump_id'] == $pump['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $pump['pump_no']
                        ) ?>

                        -

                        <?= htmlspecialchars(
                            $pump['pump_name']
                        ) ?>

                        |

                        <?= htmlspecialchars(
                            $pump['fuel_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-group">

            <label for="reading">
                قراءة العداد
            </label>

            <input
                type="number"
                id="reading"
                name="reading"
                class="form-control"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars(
                    $_POST['reading'] ?? ''
                ) ?>"
                placeholder="مثال: 12500.00"
                required
            >

        </div>


        <div class="form-group">

            <label for="reading_type">
                نوع القراءة
            </label>

            <select
                id="reading_type"
                name="reading_type"
                class="form-control"
                required
            >

                <option value="opening">
                    افتتاحية
                </option>

                <option value="closing">
                    ختامية
                </option>

                <option value="manual" selected>
                    يدوية
                </option>

            </select>

        </div>


        <div class="form-group">

            <label for="reading_date">
                تاريخ ووقت القراءة
            </label>

            <input
                type="datetime-local"
                id="reading_date"
                name="reading_date"
                class="form-control"
                value="<?= htmlspecialchars(
                    $_POST['reading_date']
                    ?? date('Y-m-d\TH:i')
                ) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="employee_id">
                الموظف
            </label>

            <select
                id="employee_id"
                name="employee_id"
                class="form-control"
            >

                <option value="">
                    -- بدون موظف --
                </option>

                <?php foreach ($employees as $employee): ?>

                    <option
                        value="<?= $employee['id'] ?>"
                        <?= (
                            isset($_POST['employee_id'])
                            &&
                            $_POST['employee_id']
                                == $employee['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $employee['full_name']
                        ) ?>

                        -

                        <?= htmlspecialchars(
                            $employee['employee_no']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-group">

            <label for="notes">
                ملاحظات
            </label>

            <textarea
                id="notes"
                name="notes"
                class="form-control"
                rows="3"
                placeholder="ملاحظات اختيارية"
            ><?= htmlspecialchars(
                $_POST['notes'] ?? ''
            ) ?></textarea>

        </div>


        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                حفظ القراءة
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

