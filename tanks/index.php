<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'الخزانات';

$stmt = $pdo->query("
    SELECT
        t.id,
        t.tank_no,
        t.name,
        t.capacity,
        t.current_volume,
        t.minimum_level,
        t.status,
        ft.name AS fuel_name,
        ft.code AS fuel_code,
        ft.unit
    FROM tanks t
    INNER JOIN fuel_types ft
        ON ft.id = t.fuel_type_id
    ORDER BY t.id DESC
");

$tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

?>

<!-- تم تعديل هذا القسم لجعل الزر في اليمين -->
<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 30px;">

    <!-- 1. العنوان -->
    <div>
        <h2 style="margin: 0;">الخزانات</h2>
    </div>

    <!-- 2. الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            +  إضافة خزان جدبد
        </a>
    </div>

</div>


<?php if (isset($_GET['success'])): ?>

    <div class="alert alert-success">

        <?php if ($_GET['success'] === 'created'): ?>

            تم إضافة الخزان بنجاح.

        <?php elseif ($_GET['success'] === 'updated'): ?>

            تم تعديل الخزان بنجاح.

        <?php elseif ($_GET['success'] === 'status'): ?>

            تم تغيير حالة الخزان بنجاح.

        <?php endif; ?>

    </div>

<?php endif; ?>


<div class="table-container">

    <table>

        <thead>

            <tr>

                <th>رقم الخزان</th>
                <th>اسم الخزان</th>
                <th>نوع الوقود</th>
                <th>السعة</th>
                <th>الرصيد الحالي</th>
                <th>الحد الأدنى</th>
                <th>الحالة</th>
                <th>الإجراءات</th>

            </tr>

        </thead>

        <tbody>

        <?php if (!$tanks): ?>

            <tr>

                <td colspan="9">
                    لا توجد خزانات مسجلة.
                </td>

            </tr>

        <?php else: ?>

            <?php foreach ($tanks as $tank): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($tank['id']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($tank['tank_no']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($tank['name']) ?>
                    </td>

                    <td>

                        <?= htmlspecialchars($tank['fuel_name']) ?>

                        <small>
                            (<?= htmlspecialchars($tank['fuel_code']) ?>)
                        </small>

                    </td>

                    <td>
                        <?= number_format((float)$tank['capacity'], 2) ?>
                    </td>

                    <td>
                        <?= number_format((float)$tank['current_volume'], 2) ?>
                    </td>

                    <td>
                        <?= number_format((float)$tank['minimum_level'], 2) ?>
                    </td>

                    <td>

                        <?php if ($tank['status'] === 'active'): ?>

                            <span class="badge badge-success">
                                نشط
                            </span>

                        <?php elseif ($tank['status'] === 'maintenance'): ?>

                            <span class="badge badge-warning">
                                صيانة
                            </span>

                        <?php else: ?>

                            <span class="badge badge-danger">
                                غير نشط
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>

                        <a
                            href="edit.php?id=<?= $tank['id'] ?>"
                            class="btn btn-sm btn-primary"
                        >
                            تعديل
                        </a>

                        <a
                            href="toggle.php?id=<?= $tank['id'] ?>"
                            class="btn btn-sm btn-secondary"
                            onclick="return confirm('هل تريد تغيير حالة الخزان؟')"
                        >
                            تغيير الحالة
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
