<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die('رقم نوع الوقود غير صحيح.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM fuel_types
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$fuel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fuel) {
    die('نوع الوقود غير موجود.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $unit = trim($_POST['unit'] ?? 'liter');

    if ($name === '') {

        $error = 'اسم الوقود مطلوب.';

    } elseif ($code === '') {

        $error = 'كود الوقود مطلوب.';

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE fuel_types
                SET
                    code = :code,
                    name = :name,
                    unit = :unit
                WHERE id = :id
            ");

            $stmt->execute([
                ':code' => $code,
                ':name' => $name,
                ':unit' => $unit,
                ':id' => $id
            ]);

            header('Location: index.php?success=updated');
            exit;

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {
                $error = 'كود الوقود مستخدم بالفعل.';
            } else {
                $error = 'خطأ قاعدة البيانات: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="form-card">

    <h2>تعديل نوع الوقود</h2>

    <?php if ($error): ?>

        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="form-group">

            <label for="code">
                كود الوقود
            </label>

            <input
                type="text"
                id="code"
                name="code"
                class="form-control"
                value="<?= htmlspecialchars($fuel['code']) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="name">
                اسم الوقود
            </label>

            <input
                type="text"
                id="name"
                name="name"
                class="form-control"
                value="<?= htmlspecialchars($fuel['name']) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="unit">
                وحدة القياس
            </label>

            <select
                id="unit"
                name="unit"
                class="form-control"
            >

                <option
                    value="liter"
                    <?= $fuel['unit'] === 'liter' ? 'selected' : '' ?>
                >
                    لتر
                </option>

                <option
                    value="gallon"
                    <?= $fuel['unit'] === 'gallon' ? 'selected' : '' ?>
                >
                    جالون
                </option>

            </select>

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
