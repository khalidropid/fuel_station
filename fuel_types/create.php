<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'إضافة نوع وقود';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $unit = trim($_POST['unit'] ?? 'لتر');

    if ($name === '') {

        $error = 'اسم الوقود مطلوب.';

    } else {

        try {

            // تم إزالة حقل description لأنه غير موجود في قاعدة البيانات
            $stmt = $pdo->prepare("
                INSERT INTO fuel_types
                (name, code, unit)
                VALUES
                (:name, :code, :unit)
            ");

            // تم تصحيح طريقة تمرير المتغيرات لتجنب خطأ الـ NULL
            $stmt->execute([
                ':name' => $name,
                ':code' => $code, // إرسال النص كما هو حتى لو كان فارغاً
                ':unit' => $unit
            ]);

            header('Location: index.php?success=created');
            exit;

        } catch (PDOException $e) {

            if ($e->getCode() == 23000) {

                $error = 'الكود أو نوع الوقود موجود مسبقاً.';

            } else {

                $error = 'حدث خطأ أثناء حفظ البيانات: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">
    <div>
        <h2 style="margin: 0;">إضافة نوع وقود</h2>
    </div>
</div>

<div class="form-card">

    <?php if ($error): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <form method="POST">

        <div class="form-group">

            <label>
                اسم الوقود *
            </label>

            <input
                type="text"
                name="name"
                class="form-control"
                placeholder="مثال: بنزين"
                required
            >

        </div>


        <div class="form-group">

            <label>
                الكود *
            </label>

            <input
                type="text"
                name="code"
                class="form-control"
                placeholder="مثال: PETROL"
                required
            >
            <small style="color: #666;">يجب أن يكون الكود باللغة الإنجليزية (مثال: PETROL أو DIESEL).</small>

        </div>


        <div class="form-group">

            <label>
                وحدة القياس
            </label>

            <input
                type="text"
                name="unit"
                class="form-control"
                value="لتر"
                required
            >

        </div>

        <div class="form-actions mt-4">

            <button
                type="submit"
                class="btn btn-primary"
            >
                حفظ
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