<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'انواع الوقود';


$stmt = $pdo->query("
    SELECT *
    FROM fuel_types
    ORDER BY id DESC
");

$fuelTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

?>

<!-- تم تعديل هذا القسم لجعل الزر في اليمين -->
<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 30px;">

    <!-- 1. العنوان -->
    <div>
        <h2 style="margin: 0;">أنواع الوقود</h2>
    </div>

    <!-- 2. الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            + إضافة نوع وقود
        </a>
    </div>

</div>


<?php if (isset($_GET['success'])): ?>

    <div class="alert alert-success">

        <?php if ($_GET['success'] === 'updated'): ?>

            تم تعديل نوع الوقود بنجاح.

        <?php elseif ($_GET['success'] === 'created'): ?>

            تم إضافة نوع الوقود بنجاح.

        <?php else: ?>

            تمت العملية بنجاح.

        <?php endif; ?>

    </div>

<?php endif; ?>


<div class="table-container">

    <table>

        <thead>

            <tr>

                <th>الكود</th>
                <th>اسم الوقود</th>
                <th>الوحدة</th>
                <th>الحالة</th>
                <th>الإجراءات</th>

            </tr>

        </thead>

        <tbody>

        <?php if (!$fuelTypes): ?>

            <tr>

                <td colspan="6">
                    لا توجد أنواع وقود.
                </td>

            </tr>

        <?php else: ?>

            <?php foreach ($fuelTypes as $fuel): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($fuel['id']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($fuel['code']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($fuel['name']) ?>
                    </td>

                    <td>
                        <?= $fuel['unit'] === 'liter' ? 'لتر' : htmlspecialchars($fuel['unit']) ?>
                    </td>

                    <td>

                        <?php if ($fuel['is_active']): ?>

                            <span class="badge badge-success">
                                نشط
                            </span>

                        <?php else: ?>

                            <span class="badge badge-danger">
                                غير نشط
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <a
                            href="edit.php?id=<?= $fuel['id'] ?>"
                            class="btn btn-sm btn-primary"
                        >
                            تعديل
                        </a>

                        <a
                            href="toggle.php?id=<?= $fuel['id'] ?>"
                            class="btn btn-sm btn-secondary"
                            onclick="return confirm('هل تريد تغيير حالة نوع الوقود؟')"
                        >
                            <?= $fuel['is_active'] ? 'تعطيل' : 'تفعيل' ?>
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