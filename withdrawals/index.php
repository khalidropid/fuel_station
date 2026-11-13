<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'مسحوبات الموظفين';

$error = '';

$withdrawals = [];

$totalWithdrawals = 0.00;
$todayWithdrawals = 0.00;
$withdrawalsCount = 0;

$typeLabels = [
    'personal'      => 'شخصية',
    'advance_salary' => 'سلفة راتب',
    'expense'       => 'مصروف',
    'other'         => 'أخرى'
];

try {

    /*
    |--------------------------------------------------------------------------
    | إجمالي المسحوبات
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(amount), 0)
        FROM employee_withdrawals
    ");

    $totalWithdrawals = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | مسحوبات اليوم
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(amount), 0)
        FROM employee_withdrawals
        WHERE DATE(withdrawal_date) = CURDATE()
    ");

    $todayWithdrawals = (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | عدد المسحوبات
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COUNT(*)
        FROM employee_withdrawals
    ");

    $withdrawalsCount = (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | قائمة المسحوبات
    |--------------------------------------------------------------------------
    |
    | مهم:
    | استخدمنا LEFT JOIN مع shifts لأن shift_id يسمح بـ NULL.
    |
    */

    $stmt = $pdo->query("
        SELECT
            w.id,
            w.employee_id,
            w.shift_id,
            w.amount,
            w.withdrawal_date,
            w.withdrawal_type,
            w.description,
            w.created_by,
            w.created_at,

            e.employee_no,
            e.full_name AS employee_name,

            s.shift_no,
            s.status AS shift_status,
            s.start_datetime,
            s.end_datetime

        FROM employee_withdrawals w

        INNER JOIN employees e
            ON e.id = w.employee_id

        LEFT JOIN shifts s
            ON s.id = w.shift_id

        ORDER BY
            w.withdrawal_date DESC,
            w.id DESC
    ");

    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

    $error = 'تعذر تحميل بيانات المسحوبات.';
}


require_once __DIR__ . '/../includes/header.php';

?>

<!-- القسم العلوي المعدل لإضافة مربع البحث -->
<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 20px;">

    <!-- العنوان ومربع البحث -->
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="margin: 0; margin-bottom: 5px;">مسحوبات الموظفين</h2>
            <p style="margin: 0; color: #666;">
                إدارة ومتابعة المبالغ المسحوبة من الموظفين.
            </p>
        </div>

        <!-- حقل البحث الجديد -->
        <div>
            <input
                type="text"
                id="withdrawalSearch"
                placeholder="🔍 بحث باسم الموظف، رقمه، أو الوصف..."
                style="width: 320px; max-width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;">
        </div>
    </div>

    <!-- الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            + تسجيل مسحوبية
        </a>
    </div>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<!--
|--------------------------------------------------------------------------
| الإحصائيات
|--------------------------------------------------------------------------
-->

<div class="cards">


    <!-- إجمالي المسحوبات -->

    <div class="card stat-card">

        <div class="stat-icon">
            💸
        </div>

        <div class="stat-info">

            <span>
                إجمالي المسحوبات
            </span>

            <strong>
                <?= number_format(
                    $totalWithdrawals,
                    2
                ) ?>
            </strong>

        </div>

    </div>


    <!-- مسحوبات اليوم -->

    <div class="card stat-card">

        <div class="stat-icon">
            📅
        </div>

        <div class="stat-info">

            <span>
                مسحوبات اليوم
            </span>

            <strong>
                <?= number_format(
                    $todayWithdrawals,
                    2
                ) ?>
            </strong>

        </div>

    </div>


    <!-- عدد العمليات -->

    <div class="card stat-card">

        <div class="stat-icon">
            📋
        </div>

        <div class="stat-info">

            <span>
                عدد العمليات
            </span>

            <strong>
                <?= number_format(
                    $withdrawalsCount
                ) ?>
            </strong>

        </div>

    </div>

</div>


<!--
|--------------------------------------------------------------------------
| جدول المسحوبات
|--------------------------------------------------------------------------
-->

<!-- التعديل 1: إضافة overflow:hidden هنا لمنع الكارد من التمدد خارج الشاشة -->
<div class="card" style="overflow: hidden;">


    <div class="card-header">

        <div>

            <h3>
                سجل المسحوبات
            </h3>

            <p>
                جميع عمليات السحب المسجلة في النظام.
            </p>

        </div>

    </div>


    <!-- التعديل 2: إضافة overflow-x: auto لعمل السكرول -->
    <div class="table-responsive" style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">

        <!-- التعديل 3: إجبار الجدول على عرض لا يقل عن 1100 بكسل، ومنع تكسير النصوص -->
        <table id="withdrawalsTable" class="table" style="min-width: 1100px; white-space: nowrap;">

            <thead>

                <tr>

                    <th>
                        الموظف
                    </th>

                    <th>
                        الوردية
                    </th>

                    <th>
                        نوع المسحوبة
                    </th>

                    <th>
                        المبلغ
                    </th>

                    <th>
                        تاريخ المسحوبة
                    </th>

                    <th>
                        حالة الوردية
                    </th>

                    <th>
                        الوصف
                    </th>

                    <th>
                        الإجراءات
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php if (!$withdrawals): ?>


                    <tr>

                        <td
                            colspan="9"
                            style="text-align:center; padding:30px;">

                            <div style="font-size:32px;">
                                💸
                            </div>

                            <strong>
                                لا توجد مسحوبات مسجلة
                            </strong>

                            <p>
                                لم يتم تسجيل أي مسحوبات للموظفين حتى الآن.
                            </p>

                            <a
                                href="create.php"
                                class="btn btn-primary">
                                + تسجيل أول مسحوبة
                            </a>

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($withdrawals as $withdrawal): ?>

                        <!-- إضافة data-search لكي يقرأها كود البحث -->
                        <tr data-search="<?= htmlspecialchars(strtolower($withdrawal['employee_name'] . ' ' . $withdrawal['employee_no'] . ' ' . ($withdrawal['description'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">


                            <!-- الرقم -->

                            <td>

                                <strong>
                                    #<?= (int)$withdrawal['id'] ?>
                                </strong>

                            </td>


                            <!-- الموظف -->

                            <td>

                                <div>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $withdrawal['employee_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>

                                </div>

                                <small>

                                    رقم الموظف:

                                    <?= htmlspecialchars(
                                        $withdrawal['employee_no'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </small>

                            </td>


                            <!-- الوردية -->

                            <td>

                                <?php if (
                                    !empty($withdrawal['shift_id'])
                                    &&
                                    !empty($withdrawal['shift_no'])
                                ): ?>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $withdrawal['shift_no'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>

                                <?php else: ?>

                                    <span class="badge">
                                        بدون وردية
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- نوع المسحوبة -->

                            <td>

                                <?php

                                $type =
                                    $withdrawal['withdrawal_type']
                                    ?? 'other';

                                ?>

                                <span class="badge">

                                    <?= htmlspecialchars(
                                        $typeLabels[$type]
                                            ?? $type,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </td>


                            <!-- المبلغ -->

                            <td>

                                <strong
                                    style="font-size:16px; color: #dc2626;">

                                    <?= number_format(
                                        (float)$withdrawal['amount'],
                                        2
                                    ) ?>

                                </strong>

                            </td>


                            <!-- تاريخ المسحوبة -->

                            <td>

                                <?= htmlspecialchars(
                                    $withdrawal['withdrawal_date'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <!-- حالة الوردية -->

                            <td>

                                <?php if (
                                    empty($withdrawal['shift_id'])
                                ): ?>

                                    <span class="badge">
                                        بدون وردية
                                    </span>


                                <?php elseif (
                                    $withdrawal['shift_status']
                                    === 'open'
                                ): ?>

                                    <span class="badge badge-success">
                                        مفتوحة
                                    </span>


                                <?php elseif (
                                    $withdrawal['shift_status']
                                    === 'closed'
                                ): ?>

                                    <span class="badge badge-danger">
                                        مغلقة
                                    </span>


                                <?php else: ?>

                                    <span class="badge">
                                        <?= htmlspecialchars(
                                            $withdrawal['shift_status']
                                                ?? 'غير معروف',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- الوصف -->

                            <td>

                                <?php if (
                                    !empty($withdrawal['description'])
                                ): ?>

                                    <span
                                        title="<?= htmlspecialchars(
                                                    $withdrawal['description'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>">

                                        <?= htmlspecialchars(
                                            mb_strimwidth(
                                                $withdrawal['description'],
                                                0,
                                                35,
                                                '...',
                                                'UTF-8'
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span>
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- الإجراءات -->

                            <td>

                                <div
                                    style="
                                    display:flex;
                                    gap:6px;
                                    flex-wrap:wrap;
                                ">


                                    <a
                                        href="view.php?id=<?= (int)$withdrawal['id'] ?>"
                                        class="btn btn-sm">
                                        عرض
                                    </a>


                                    <?php
                                    /*
                                |--------------------------------------------------------------------------
                                | السماح بالتعديل والحذف
                                |--------------------------------------------------------------------------
                                |
                                | إذا كانت المسحوبة مرتبطة بورديه مغلقة
                                | لا نسمح بالتعديل أو الحذف.
                                |
                                | أما المسحوبة بدون وردية
                                | فيمكن تعديلها أو حذفها.
                                |
                                */

                                    $canEdit =

                                        empty($withdrawal['shift_id'])

                                        ||

                                        (
                                            isset(
                                                $withdrawal['shift_status']
                                            )

                                            &&

                                            $withdrawal['shift_status']
                                            ===
                                            'open'
                                        );

                                    ?>


                                    <?php if ($canEdit): ?>


                                        <a
                                            href="edit.php?id=<?= (int)$withdrawal['id'] ?>"
                                            class="btn btn-sm btn-primary">
                                            تعديل
                                        </a>


                                        <a
                                            href="delete.php?id=<?= (int)$withdrawal['id'] ?>"
                                            class="btn btn-sm btn-danger"
                                            onclick="
                                            return confirm(
                                                'هل أنت متأكد من حذف هذه المسحوبة؟'
                                            );
                                        ">
                                            حذف
                                        </a>


                                    <?php else: ?>


                                        <span
                                            class="badge"
                                            title="لا يمكن تعديل مسحوبة مرتبطة بورديه مغلقة">
                                            مقفلة
                                        </span>


                                    <?php endif; ?>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


            </tbody>


            <?php if ($withdrawals): ?>

                <tfoot>

                    <tr>

                        <th
                            colspan="4"
                            style="text-align:right;">
                            الإجمالي
                        </th>

                        <th>

                            <strong style="color: #dc2626;">

                                <?= number_format(
                                    $totalWithdrawals,
                                    2
                                ) ?>

                            </strong>

                        </th>

                        <th colspan="4"></th>

                    </tr>

                </tfoot>

            <?php endif; ?>


        </table>

    </div>

</div>

<!-- سكريبت البحث الفوري -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('withdrawalSearch');
        const rows = document.querySelectorAll('#withdrawalsTable tbody tr[data-search]');

        if (!searchInput) return;

        searchInput.addEventListener('input', function() {
            const value = this.value.trim().toLowerCase();

            rows.forEach(row => {
                const text = row.getAttribute('data-search') || '';
                // إظهار الصف إذا كان يطابق البحث، وإخفاؤه إذا لم يطابق
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    });
</script>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>