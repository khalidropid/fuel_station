<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'قراءات الطرمبات';

$sql = "
    SELECT
        pr.id,
        pr.reading,
        pr.reading_date,
        pr.reading_type,
        pr.notes,

        p.pump_no,
        p.name AS pump_name,

        e.employee_no,
        e.full_name AS employee_name

    FROM pump_readings pr

    INNER JOIN pumps p
        ON p.id = pr.pump_id

    LEFT JOIN employees e
        ON e.id = pr.employee_id

    ORDER BY pr.reading_date DESC, pr.id DESC
";

$stmt = $pdo->query($sql);

$readings = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';

?>

<!-- تم تعديل هذا القسم لجعل الزر في اليمين -->
<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 30px;">

    <!-- 1. العنوان -->
    <div>
        <h2 style="margin: 0;">قراءات الطرمبة</h2>
    </div>

    <!-- 2. الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            + إضافة قراءات
        </a>
    </div>

</div>


<?php if (isset($_GET['success'])): ?>

    <div class="alert alert-success">

        تم تسجيل قراءة العداد بنجاح.

    </div>

<?php endif; ?>


<div class="table-container">

    <table>

        <thead>

            <tr>

                <th>الطرمبة</th>
                <th>القراءة</th>
                <th>نوع القراءة</th>
                <th>التاريخ</th>
                <th>الموظف</th>
                <th>الملاحظات</th>

            </tr>

        </thead>

        <tbody>

        <?php if (!$readings): ?>

            <tr>

                <td colspan="7">
                    لا توجد قراءات مسجلة.
                </td>

            </tr>

        <?php else: ?>

            <?php foreach ($readings as $reading): ?>

                <tr>

                    <td>
                        <?= htmlspecialchars($reading['id']) ?>
                    </td>

                    <td>

                        <?= htmlspecialchars($reading['pump_no']) ?>

                        -

                        <?= htmlspecialchars($reading['pump_name']) ?>

                    </td>

                    <td>

                        <strong>
                            <?= number_format(
                                (float)$reading['reading'],
                                2
                            ) ?>
                        </strong>

                    </td>

                    <td>

                        <?php if ($reading['reading_type'] === 'opening'): ?>

                            <span class="badge badge-success">
                                افتتاحية
                            </span>

                        <?php elseif ($reading['reading_type'] === 'closing'): ?>

                            <span class="badge badge-warning">
                                ختامية
                            </span>

                        <?php else: ?>

                            <span class="badge badge-secondary">
                                يدوية
                            </span>

                        <?php endif; ?>

                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $reading['reading_date']
                        ) ?>
                    </td>

                    <td>

                        <?php if ($reading['employee_name']): ?>

                            <?= htmlspecialchars(
                                $reading['employee_name']
                            ) ?>

                            <?php if ($reading['employee_no']): ?>

                                <small>
                                    (<?= htmlspecialchars(
                                        $reading['employee_no']
                                    ) ?>)
                                </small>

                            <?php endif; ?>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </td>

                    <td>

                        <?= $reading['notes']
                            ? htmlspecialchars($reading['notes'])
                            : '—'
                        ?>

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
