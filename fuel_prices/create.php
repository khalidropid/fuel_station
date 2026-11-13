<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$error = '';

$stmt = $pdo->query("
    SELECT id, code, name, unit
    FROM fuel_types
    WHERE is_active = 1
    ORDER BY name
");

$fuelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fuelTypeId = filter_input(
        INPUT_POST,
        'fuel_type_id',
        FILTER_VALIDATE_INT
    );

    $price = trim($_POST['price'] ?? '');

    $effectiveFrom = trim(
        $_POST['effective_from'] ?? ''
    );

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

            $stmt = $pdo->prepare("
                INSERT INTO fuel_prices
                (
                    fuel_type_id,
                    price,
                    effective_from
                )
                VALUES
                (
                    :fuel_type_id,
                    :price,
                    :effective_from
                )
            ");

            $stmt->execute([
                ':fuel_type_id' => $fuelTypeId,
                ':price' => $price,
                ':effective_from' => $effectiveFrom
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

    <h2>إضافة سعر وقود</h2>

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
                value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
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
                value="<?= htmlspecialchars(
                    $_POST['effective_from']
                    ?? date('Y-m-d\TH:i')
                ) ?>"
                required
            >

        </div>


        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                حفظ السعر
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
