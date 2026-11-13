<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'المبيعات';

$sales = [];

$error = '';

$success = '';

/*
|--------------------------------------------------------------------------
| دالة حماية النصوص
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| رسالة نجاح الحذف
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['deleted'])
    &&
    $_GET['deleted'] === '1'
) {

    $success =
        'تم حذف عملية البيع وعكس جميع تأثيراتها بنجاح.';
}


/*
|--------------------------------------------------------------------------
| جلب المبيعات
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT

            s.id,

            s.invoice_no,

            s.sale_date,

            s.payment_method,

            s.subtotal,

            s.discount,

            s.total,

            s.customer_id,


            p.pump_no,

            p.name AS pump_name,


            e.employee_no,

            e.full_name AS employee_name,


            sh.shift_no,

            sh.status AS shift_status,


            c.customer_no,

            c.full_name AS customer_name


        FROM sales s


        INNER JOIN pumps p
            ON p.id = s.pump_id


        LEFT JOIN employees e
            ON e.id = s.employee_id


        LEFT JOIN shifts sh
            ON sh.id = s.shift_id


        LEFT JOIN customers c
            ON c.id = s.customer_id


        ORDER BY
            s.id DESC
    ");


    $sales =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | تسجيل الخطأ في Log
    |--------------------------------------------------------------------------
    */

    error_log(
        'Sales index database error: '
        .
        $e->getMessage()
    );


    /*
    |--------------------------------------------------------------------------
    | رسالة للمستخدم
    |--------------------------------------------------------------------------
    */

    $error =
        'تعذر تحميل بيانات المبيعات. حاول مرة أخرى.';
}


/*
|--------------------------------------------------------------------------
| الإحصائيات
|--------------------------------------------------------------------------
*/

$salesCount = count($sales);

$grandTotal = 0;

$cashTotal = 0;

$cardTotal = 0;

$creditTotal = 0;

$discountTotal = 0;


foreach ($sales as $sale) {

    $total =
        (float)($sale['total'] ?? 0);

    $discount =
        (float)($sale['discount'] ?? 0);


    $grandTotal +=
        $total;

    $discountTotal +=
        $discount;


    switch (
        $sale['payment_method']
        ??
        ''
    ) {

        case 'cash':

            $cashTotal +=
                $total;

            break;


        case 'card':

            $cardTotal +=
                $total;

            break;


        case 'credit':

            $creditTotal +=
                $total;

            break;
    }
}


/*
|--------------------------------------------------------------------------
| تجهيز القيم
|--------------------------------------------------------------------------
*/

$grandTotal =
    round(
        $grandTotal,
        2
    );

$cashTotal =
    round(
        $cashTotal,
        2
    );

$cardTotal =
    round(
        $cardTotal,
        2
    );

$creditTotal =
    round(
        $creditTotal,
        2
    );

$discountTotal =
    round(
        $discountTotal,
        2
    );


require_once __DIR__ . '/../includes/header.php';

?>


<!-- =====================================================
     Page Header
====================================================== -->

<div class="page-header">

    <div>

        <h2>
            المبيعات
        </h2>

        <p>
            إدارة عمليات بيع الوقود ومتابعة الفواتير.
        </p>

    </div>


    <div>

        <a
            href="create.php"
            class="btn btn-primary"
        >
            + تسجيل عملية بيع
        </a>

    </div>

</div>


<!-- =====================================================
     Success
====================================================== -->

<?php if ($success): ?>

    <div class="alert alert-success">

        <?= e($success) ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     Error
====================================================== -->

<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= e($error) ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     Statistics
====================================================== -->

<div class="cards">


    <!-- عدد المبيعات -->

    <div class="card stat-card">

        <div class="stat-icon">
            🧾
        </div>

        <div class="stat-info">

            <span>
                عدد المبيعات
            </span>

            <strong>
                <?= number_format(
                    $salesCount
                ) ?>
            </strong>

        </div>

    </div>


    <!-- إجمالي المبيعات -->

    <div class="card stat-card">

        <div class="stat-icon">
            💰
        </div>

        <div class="stat-info">

            <span>
                إجمالي المبيعات
            </span>

            <strong>

                <?= number_format(
                    $grandTotal,
                    2
                ) ?>

            </strong>

        </div>

    </div>


    <!-- النقد -->

    <div class="card stat-card">

        <div class="stat-icon">
            💵
        </div>

        <div class="stat-info">

            <span>
                مبيعات نقدية
            </span>

            <strong>

                <?= number_format(
                    $cashTotal,
                    2
                ) ?>

            </strong>

        </div>

    </div>


    <!-- البطاقة -->

    <div class="card stat-card">

        <div class="stat-icon">
            💳
        </div>

        <div class="stat-info">

            <span>
                مبيعات البطاقات
            </span>

            <strong>

                <?= number_format(
                    $cardTotal,
                    2
                ) ?>

            </strong>

        </div>

    </div>


    <!-- الآجل -->

    <div class="card stat-card">

        <div class="stat-icon">
            📒
        </div>

        <div class="stat-info">

            <span>
                المبيعات الآجلة
            </span>

            <strong>

                <?= number_format(
                    $creditTotal,
                    2
                ) ?>

            </strong>

        </div>

    </div>


    <!-- الخصومات -->

    <div class="card stat-card">

        <div class="stat-icon">
            🏷️
        </div>

        <div class="stat-info">

            <span>
                إجمالي الخصومات
            </span>

            <strong>

                <?= number_format(
                    $discountTotal,
                    2
                ) ?>

            </strong>

        </div>

    </div>

</div>


<!-- =====================================================
     Sales Table
====================================================== -->

<div class="card">

    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        رقم الفاتورة
                    </th>

                    <th>
                        التاريخ
                    </th>

                    <th>
                        الوردية
                    </th>

                    <th>
                        الطرمبة
                    </th>

                    <th>
                        الموظف
                    </th>

                    <th>
                        العميل
                    </th>

                    <th>
                        طريقة الدفع
                    </th>

                    <th>
                        المجموع
                    </th>

                    <th>
                        الخصم
                    </th>

                    <th>
                        الإجمالي
                    </th>

                    <th>
                        الإجراءات
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (empty($sales)): ?>

                <tr>

                    <td
                        colspan="12"
                        style="text-align:center;"
                    >

                        لا توجد عمليات بيع حتى الآن.

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach ($sales as $sale): ?>

                    <tr>


                        <!-- ID -->

                        <td>

                            <?= (int)$sale['id'] ?>

                        </td>


                        <!-- Invoice -->

                        <td>

                            <strong>

                                <?= e(
                                    $sale['invoice_no']
                                ) ?>

                            </strong>

                        </td>


                        <!-- Date -->

                        <td>

                            <?= e(
                                $sale['sale_date']
                            ) ?>

                        </td>


                        <!-- Shift -->

                        <td>

                            <?php if (
                                !empty(
                                    $sale['shift_no']
                                )
                            ): ?>

                                <?= e(
                                    $sale['shift_no']
                                ) ?>


                                <?php if (
                                    ($sale['shift_status'] ?? '')
                                    ===
                                    'open'
                                ): ?>

                                    <span class="badge badge-success">
                                        مفتوحة
                                    </span>

                                <?php else: ?>

                                    <span class="badge">
                                        مغلقة
                                    </span>

                                <?php endif; ?>


                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <!-- Pump -->

                        <td>

                            <?= e(
                                'طرمبة '
                                .
                                ($sale['pump_no'] ?? '')
                                .
                                ' - '
                                .
                                ($sale['pump_name'] ?? '')
                            ) ?>

                        </td>


                        <!-- Employee -->

                        <td>

                            <?php if (
                                !empty(
                                    $sale['employee_name']
                                )
                            ): ?>

                                <?= e(
                                    $sale['employee_no']
                                    .
                                    ' - '
                                    .
                                    $sale['employee_name']
                                ) ?>

                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <!-- Customer -->

                        <td>

                            <?php if (
                                ($sale['payment_method'] ?? '')
                                ===
                                'credit'
                            ): ?>


                                <?php if (
                                    !empty(
                                        $sale['customer_name']
                                    )
                                ): ?>

                                    <?= e(
                                        $sale['customer_no']
                                        .
                                        ' - '
                                        .
                                        $sale['customer_name']
                                    ) ?>

                                <?php else: ?>

                                    <span
                                        class="badge badge-danger"
                                    >
                                        عميل غير موجود
                                    </span>

                                <?php endif; ?>


                            <?php else: ?>

                                -

                            <?php endif; ?>

                        </td>


                        <!-- Payment -->

                        <td>

                            <?php

                            $paymentMethod =
                                $sale['payment_method']
                                ??
                                '';

                            ?>


                            <?php if (
                                $paymentMethod
                                ===
                                'cash'
                            ): ?>

                                <span
                                    class="badge badge-success"
                                >
                                    نقدي
                                </span>


                            <?php elseif (
                                $paymentMethod
                                ===
                                'card'
                            ): ?>

                                <span class="badge">

                                    بطاقة

                                </span>


                            <?php elseif (
                                $paymentMethod
                                ===
                                'credit'
                            ): ?>

                                <span
                                    class="badge badge-warning"
                                >

                                    آجل

                                </span>


                            <?php else: ?>

                                <span
                                    class="badge badge-danger"
                                >

                                    غير معروف

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Subtotal -->

                        <td>

                            <?= number_format(
                                (float)(
                                    $sale['subtotal']
                                    ??
                                    0
                                ),
                                2
                            ) ?>

                        </td>


                        <!-- Discount -->

                        <td>

                            <?= number_format(
                                (float)(
                                    $sale['discount']
                                    ??
                                    0
                                ),
                                2
                            ) ?>

                        </td>


                        <!-- Total -->

                        <td>

                            <strong>

                                <?= number_format(
                                    (float)(
                                        $sale['total']
                                        ??
                                        0
                                    ),
                                    2
                                ) ?>

                            </strong>

                        </td>


                        <!-- Actions -->

                        <td>

                            <div
                                style="
                                    display:flex;
                                    gap:5px;
                                    flex-wrap:wrap;
                                "
                            >

                                <!-- View -->

                                <a
                                    href="view.php?id=<?= (int)$sale['id'] ?>"
                                    class="btn btn-sm"
                                >

                                    عرض

                                </a>


                        <a
                            href="edit.php?id=<?= $sale['id'] ?>"
                            class="btn btn-sm btn-primary"
                        >
                            تعديل
                        </a>


                                <!-- Delete -->

                                <a
                                    href="delete.php?id=<?= (int)$sale['id'] ?>"
                                    class="btn btn-sm btn-danger"
                                >

                                    حذف

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