<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'اسعار الوقود';


// تمت إضافة fp.is_active لجلب حالة السعر
$stmt = $pdo->query("
    SELECT
        fp.id,
        fp.price,
        fp.effective_from,
        fp.is_active,
        ft.code,
        ft.name AS fuel_name,
        ft.unit
    FROM fuel_prices fp
    INNER JOIN fuel_types ft
        ON ft.id = fp.fuel_type_id
    ORDER BY fp.effective_from DESC, fp.id DESC 
");

$prices = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

?>

<!-- تم تعديل هذا القسم لجعل الزر في اليمين -->
<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 30px;">

    <!-- 1. العنوان -->
    <div>
        <h2 style="margin: 0;">اسعار الوقود</h2>
    </div>

    <!-- 2. الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            + إضافة اسعار الوقود
        </a>
    </div>

</div>

<?php if (isset($_GET['success'])): ?>

    <div class="alert alert-success">

        <?php if ($_GET['success'] === 'updated'): ?>
            تم تعديل سعر الوقود بنجاح.
        <?php elseif ($_GET['success'] === '1'): ?>
            تم حفظ سعر الوقود بنجاح.
        <?php else: ?>
            تمت العملية بنجاح.
        <?php endif; ?>

    </div>

<?php endif; ?>

<div class="table-container">

    <table>

        <thead>

            <tr>
                <th>نوع الوقود</th>
                <th>الكود</th>
                <th>السعر</th>
                <th>الوحدة</th>
                <th>يبدأ من</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>

        </thead>

        <tbody>

        <?php if (!$prices): ?>

            <tr>

                <td colspan="8">
                    لا توجد أسعار مسجلة.
                </td>

            </tr>

        <?php else: ?>

            <?php foreach ($prices as $price): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($price['id']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($price['fuel_name']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($price['code']) ?>
                    </td>

                    <td>
                        <?= number_format($price['price'], 2) ?>
                    </td>

                    <td>
                        <?= $price['unit'] === 'liter'
                            ? 'لتر'
                            : htmlspecialchars($price['unit']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($price['effective_from']) ?>
                    </td>

                    <td>
                        <?php if (isset($price['is_active']) && $price['is_active']): ?>
                            <span class="badge badge-success">نشط</span>
                        <?php else: ?>
                            <span class="badge badge-danger">غير نشط</span>
                        <?php endif; ?>
                    </td>

                    <td>

                        <a
                            href="edit.php?id=<?= $price['id'] ?>"
                            class="btn btn-sm btn-primary"
                        >
                            تعديل
                        </a>

                        <a
                            href="toggle.php?id=<?= $price['id'] ?>"
                            class="btn btn-sm btn-secondary"
                            onclick="return confirm('هل تريد تغيير حالة السعر؟')"
                        >
                            <?= (isset($price['is_active']) && $price['is_active']) ? 'تعطيل' : 'تفعيل' ?>
                        </a>

                    </td>

                </tr>

            <?php endforeach; ?>

        <?php endif; ?>

        </tbody>

    </table>

</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>