<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'الطرمبات';

$stmt = $pdo->query("
    SELECT
        p.id,
        p.pump_no,
        p.name,
        p.status,
        t.tank_no,
        t.name AS tank_name,
        ft.name AS fuel_name,
        ft.code AS fuel_code
    FROM pumps p
    INNER JOIN tanks t
        ON t.id = p.tank_id
    INNER JOIN fuel_types ft
        ON ft.id = t.fuel_type_id
    ORDER BY p.id DESC
");

$pumps = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

?>

<!-- تم تعديل هذا القسم لجعل الزر في اليمين -->
<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 30px;">

    <!-- 1. العنوان -->
    <div>
        <h2 style="margin: 0;">الطرمبات</h2>
    </div>

    <!-- 2. الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            + إضافة طرمبة جديدة
        </a>
    </div>

</div>


<?php if (isset($_GET['success'])): ?>

    <div class="alert alert-success">

        <?php if ($_GET['success'] === 'created'): ?>

            تم إضافة الطرمبة بنجاح.

        <?php elseif ($_GET['success'] === 'updated'): ?>

            تم تعديل الطرمبة بنجاح.

        <?php elseif ($_GET['success'] === 'status'): ?>

            تم تغيير حالة الطرمبة بنجاح.

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- التعديل 1: إضافة overflow: hidden لمنع الواجهة من التكسر -->
<div class="card" style="overflow: hidden;">

    <!-- التعديل 2: تفعيل السكرول الأفقي بشكل سلس -->
    <div class="table-responsive" style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">

        <!-- التعديل 3: إجبار الجدول على عدم تقلص الأعمدة وتكسير النصوص -->
        <table class="table" style="min-width: 900px; white-space: nowrap;">

            <thead>

                <tr>

                    <th>رقم الطرمبة</th>
                    <th>اسم الطرمبة</th>
                    <th>الخزان</th>
                    <th>نوع الوقود</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>

                </tr>

            </thead>

            <tbody>

                <?php if (!$pumps): ?>

                    <tr>

                        <td colspan="6" style="text-align: center; padding: 20px;">
                            لا توجد طرمبات مسجلة.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($pumps as $pump): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars($pump['id']) ?>
                            </td>

                            <td>
                                <strong><?= htmlspecialchars($pump['pump_no']) ?></strong>
                            </td>

                            <td>
                                <strong><?= htmlspecialchars($pump['name']) ?></strong>
                            </td>

                            <td>

                                <?= htmlspecialchars($pump['tank_name']) ?>

                                <small>
                                    (<?= htmlspecialchars($pump['tank_no']) ?>)
                                </small>

                            </td>

                            <td>

                                <?= htmlspecialchars($pump['fuel_name']) ?>

                                <small>
                                    (<?= htmlspecialchars($pump['fuel_code']) ?>)
                                </small>

                            </td>

                            <td>

                                <?php if ($pump['status'] === 'active'): ?>

                                    <span class="badge badge-success">
                                        نشطة
                                    </span>

                                <?php elseif ($pump['status'] === 'maintenance'): ?>

                                    <span class="badge badge-warning">
                                        صيانة
                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-danger">
                                        غير نشطة
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td>

                                <div style="display: flex; gap: 5px;">
                                    <a
                                        href="edit.php?id=<?= $pump['id'] ?>"
                                        class="btn btn-sm btn-primary">
                                        تعديل
                                    </a>

                                    <a
                                        href="toggle.php?id=<?= $pump['id'] ?>"
                                        class="btn btn-sm btn-secondary"
                                        onclick="return confirm('هل تريد تغيير حالة الطرمبة؟')">
                                        تغيير الحالة
                                    </a>
                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>
</div>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>