<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'تفاصيل المسحوبة';

$error = '';
$withdrawal = null;

$id = (int)($_GET['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| أنواع المسحوبات
|--------------------------------------------------------------------------
*/

$typeLabels = [
    'personal'       => 'شخصية',
    'advance_salary' => 'سلفة راتب',
    'expense'        => 'مصروف',
    'other'          => 'أخرى'
];


try {

    /*
    |--------------------------------------------------------------------------
    | التحقق من الرقم
    |--------------------------------------------------------------------------
    */

    if ($id <= 0) {

        throw new Exception(
            'رقم المسحوبة غير صحيح.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | جلب بيانات المسحوبة
    |--------------------------------------------------------------------------
    |
    | نستخدم LEFT JOIN مع shifts لأن shift_id يسمح بـ NULL.
    |
    */

    $stmt = $pdo->prepare("
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
            e.phone AS employee_phone,
            e.job_title,

            s.shift_no,
            s.status AS shift_status,
            s.start_datetime,
            s.end_datetime

        FROM employee_withdrawals w

        INNER JOIN employees e
            ON e.id = w.employee_id

        LEFT JOIN shifts s
            ON s.id = w.shift_id

        WHERE w.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $id
    ]);

    $withdrawal =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$withdrawal) {

        throw new Exception(
            'المسحوبة غير موجودة.'
        );
    }


} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


require_once __DIR__ . '/../includes/header.php';

?>


<div class="page-header">

    <div>

        <h2>
            تفاصيل المسحوبة
        </h2>

        <p>
            عرض جميع تفاصيل عملية السحب المسجلة.
        </p>

    </div>

    <div>

        <a
            href="index.php"
            class="btn"
        >
            ← العودة للمسحوبات
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


<?php elseif ($withdrawal): ?>


<?php

/*
|--------------------------------------------------------------------------
| بيانات مساعدة
|--------------------------------------------------------------------------
*/

$type =
    $withdrawal['withdrawal_type']
    ?? 'other';

$typeLabel =
    $typeLabels[$type]
    ?? $type;


$hasShift =
    !empty($withdrawal['shift_id'])
    &&
    !empty($withdrawal['shift_no']);


$shiftOpen =
    $hasShift
    &&
    ($withdrawal['shift_status'] === 'open');


$canEdit =
    !$hasShift
    ||
    $shiftOpen;


?>


<div class="card">


    <!--
    |--------------------------------------------------------------------------
    | رأس العملية
    |--------------------------------------------------------------------------
    -->

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:20px;
            flex-wrap:wrap;
            margin-bottom:25px;
        "
    >

        <div>

            <div
                style="
                    color:#6b7280;
                    font-size:14px;
                    margin-bottom:6px;
                "
            >
                رقم العملية
            </div>

            <h2
                style="
                    margin:0;
                    font-size:24px;
                "
            >
                #<?= (int)$withdrawal['id'] ?>
            </h2>

        </div>


        <div>

            <span class="badge">

                <?= htmlspecialchars(
                    $typeLabel,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>

    </div>


    <hr>


    <!--
    |--------------------------------------------------------------------------
    | المبلغ
    |--------------------------------------------------------------------------
    -->

    <div
        style="
            text-align:center;
            padding:25px 15px;
            margin-bottom:20px;
        "
    >

        <div
            style="
                color:#6b7280;
                font-size:15px;
                margin-bottom:8px;
            "
        >
            مبلغ المسحوبة
        </div>

        <div
            style="
                font-size:32px;
                font-weight:700;
            "
        >

            <?= number_format(
                (float)$withdrawal['amount'],
                2
            ) ?>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | بيانات العملية
    |--------------------------------------------------------------------------
    -->

    <div class="details-grid">


        <!-- الموظف -->

        <div>

            <strong>
                الموظف
            </strong>

            <span>

                <?= htmlspecialchars(
                    $withdrawal['employee_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <!-- رقم الموظف -->

        <div>

            <strong>
                رقم الموظف
            </strong>

            <span>

                <?= htmlspecialchars(
                    $withdrawal['employee_no'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <!-- الوظيفة -->

        <div>

            <strong>
                الوظيفة
            </strong>

            <span>

                <?= !empty(
                    $withdrawal['job_title']
                )
                    ? htmlspecialchars(
                        $withdrawal['job_title'],
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    : '—'
                ?>

            </span>

        </div>


        <!-- الهاتف -->

        <div>

            <strong>
                الهاتف
            </strong>

            <span>

                <?= !empty(
                    $withdrawal['employee_phone']
                )
                    ? htmlspecialchars(
                        $withdrawal['employee_phone'],
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    : '—'
                ?>

            </span>

        </div>


        <!-- الوردية -->

        <div>

            <strong>
                الوردية
            </strong>

            <span>

                <?php if ($hasShift): ?>

                    <?= htmlspecialchars(
                        $withdrawal['shift_no'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                <?php else: ?>

                    <span class="badge">
                        بدون وردية
                    </span>

                <?php endif; ?>

            </span>

        </div>


        <!-- حالة الوردية -->

        <div>

            <strong>
                حالة الوردية
            </strong>

            <span>

                <?php if (!$hasShift): ?>

                    <span class="badge">
                        بدون وردية
                    </span>

                <?php elseif ($shiftOpen): ?>

                    <span class="badge badge-success">
                        مفتوحة
                    </span>

                <?php else: ?>

                    <span class="badge badge-danger">
                        مغلقة
                    </span>

                <?php endif; ?>

            </span>

        </div>


        <!-- نوع المسحوبة -->

        <div>

            <strong>
                نوع المسحوبة
            </strong>

            <span>

                <?= htmlspecialchars(
                    $typeLabel,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <!-- تاريخ المسحوبة -->

        <div>

            <strong>
                تاريخ المسحوبة
            </strong>

            <span>

                <?= htmlspecialchars(
                    $withdrawal['withdrawal_date'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <!-- تاريخ الإنشاء -->

        <div>

            <strong>
                تاريخ تسجيل السجل
            </strong>

            <span>

                <?= htmlspecialchars(
                    $withdrawal['created_at'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


    </div>


    <hr>


    <!--
    |--------------------------------------------------------------------------
    | الوصف
    |--------------------------------------------------------------------------
    -->

    <div
        style="
            margin-top:20px;
        "
    >

        <strong>
            الوصف / الملاحظات
        </strong>


        <div
            style="
                margin-top:10px;
                padding:15px;
                background:#f9fafb;
                border-radius:8px;
                line-height:1.8;
            "
        >

            <?php if (
                !empty(
                    $withdrawal['description']
                )
            ): ?>

                <?= nl2br(
                    htmlspecialchars(
                        $withdrawal['description'],
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) ?>

            <?php else: ?>

                <span style="color:#6b7280;">
                    لا توجد ملاحظات.
                </span>

            <?php endif; ?>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | الإجراءات
    |--------------------------------------------------------------------------
    -->

    <div class="form-actions">


        <?php if ($canEdit): ?>


            <a
                href="edit.php?id=<?= (int)$withdrawal['id'] ?>"
                class="btn btn-primary"
            >
                ✏️ تعديل
            </a>


            <a
                href="delete.php?id=<?= (int)$withdrawal['id'] ?>"
                class="btn btn-danger"
                onclick="
                    return confirm(
                        'هل أنت متأكد من حذف هذه المسحوبة؟'
                    );
                "
            >
                🗑 حذف
            </a>


        <?php else: ?>


            <span
                class="badge"
                title="لا يمكن تعديل أو حذف مسحوبة مرتبطة بورديّة مغلقة"
            >
                🔒 العملية مقفلة
            </span>


        <?php endif; ?>


        <a
            href="index.php"
            class="btn"
        >
            ← العودة
        </a>


    </div>


</div>


<?php endif; ?>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>