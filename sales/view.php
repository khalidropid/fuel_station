<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'عرض الفاتورة';

$sale = null;
$items = [];
$error = '';

$saleId = (int)($_GET['id'] ?? 0);

if ($saleId <= 0) {

    $error = 'رقم عملية البيع غير صحيح.';

} else {

    try {

        /*
        |--------------------------------------------------------------------------
        | جلب بيانات الفاتورة
        |--------------------------------------------------------------------------
        */

        $saleStmt = $pdo->prepare("
            SELECT
                s.id,
                s.invoice_no,

                s.shift_id,
                s.pump_id,
                s.employee_id,
                s.customer_id,

                s.sale_date,
                s.payment_method,

                s.subtotal,
                s.discount,
                s.total,
                s.notes,
                s.created_at,

                /*
                |--------------------------------------------------------------------------
                | الطرمبة
                |--------------------------------------------------------------------------
                */

                p.pump_no,
                p.name AS pump_name,
                p.tank_id,

                /*
                |--------------------------------------------------------------------------
                | الموظف
                |--------------------------------------------------------------------------
                */

                e.employee_no,
                e.full_name AS employee_name,

                /*
                |--------------------------------------------------------------------------
                | الوردية
                |--------------------------------------------------------------------------
                */

                sh.shift_no,
                sh.status AS shift_status,

                /*
                |--------------------------------------------------------------------------
                | العميل
                |--------------------------------------------------------------------------
                */

                c.customer_no,
                c.full_name AS customer_name,
                c.phone AS customer_phone,
                c.address AS customer_address,
                c.credit_limit,
                c.balance AS customer_balance,
                c.status AS customer_status

            FROM sales s

            INNER JOIN pumps p
                ON p.id = s.pump_id

            LEFT JOIN employees e
                ON e.id = s.employee_id

            LEFT JOIN shifts sh
                ON sh.id = s.shift_id

            LEFT JOIN customers c
                ON c.id = s.customer_id

            WHERE s.id = ?

            LIMIT 1
        ");

        $saleStmt->execute([
            $saleId
        ]);

        $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);


        if (!$sale) {

            throw new Exception(
                'الفاتورة غير موجودة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | جلب تفاصيل البيع
        |--------------------------------------------------------------------------
        */

        $itemsStmt = $pdo->prepare("
            SELECT
                si.id,
                si.sale_id,
                si.fuel_type_id,

                si.quantity,
                si.unit_price,
                si.total,

                ft.code,
                ft.name AS fuel_name,
                ft.unit

            FROM sale_items si

            INNER JOIN fuel_types ft
                ON ft.id = si.fuel_type_id

            WHERE si.sale_id = ?

            ORDER BY si.id ASC
        ");

        $itemsStmt->execute([
            $saleId
        ]);

        $items = $itemsStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


        /*
        |--------------------------------------------------------------------------
        | التحقق من وجود تفاصيل
        |--------------------------------------------------------------------------
        */

        if (empty($items)) {

            throw new Exception(
                'لا توجد تفاصيل مرتبطة بهذه الفاتورة.'
            );
        }


    } catch (Exception $e) {

        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| دوال مساعدة
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


function money($value): string
{
    return number_format(
        (float)$value,
        2
    );
}


/*
|--------------------------------------------------------------------------
| طريقة الدفع
|--------------------------------------------------------------------------
*/

$paymentMethodText = '-';

if ($sale) {

    switch ($sale['payment_method']) {

        case 'cash':
            $paymentMethodText = 'نقدي';
            break;

        case 'card':
            $paymentMethodText = 'بطاقة';
            break;

        case 'credit':
            $paymentMethodText = 'آجل';
            break;
    }
}


/*
|--------------------------------------------------------------------------
| التاريخ
|--------------------------------------------------------------------------
*/

$saleDate = '';

if (
    $sale
    &&
    !empty($sale['sale_date'])
) {

    $timestamp = strtotime(
        $sale['sale_date']
    );

    if ($timestamp !== false) {

        $saleDate = date(
            'Y-m-d H:i',
            $timestamp
        );

    } else {

        $saleDate = $sale['sale_date'];
    }
}


require_once __DIR__ . '/../includes/header.php';

?>


<!-- =====================================================
     Header
====================================================== -->

<div class="page-header">

    <div>

        <h2>
            عرض الفاتورة
        </h2>

        <p>
            تفاصيل عملية بيع الوقود والعميل.
        </p>

    </div>


    <div>

        <?php if (!$error && $sale): ?>

            <a
                href="print.php?id=<?= (int)$saleId ?>"
                target="_blank"
                class="btn btn-primary"
            >
                🖨 طباعة الفاتورة
            </a>

        <?php endif; ?>


        <a
            href="index.php"
            class="btn"
        >
            العودة للمبيعات
        </a>

    </div>

</div>


<!-- =====================================================
     Error
====================================================== -->

<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= e($error) ?>

    </div>

<?php else: ?>


<!-- =====================================================
     معلومات الفاتورة
====================================================== -->

<div class="card">

    <div class="page-header">

        <div>

            <h2>

                فاتورة رقم:

                <?= e(
                    $sale['invoice_no']
                ) ?>

            </h2>

        </div>


        <div>

            <?php if (
                $sale['payment_method'] === 'cash'
            ): ?>

                <span class="badge badge-success">
                    نقدي
                </span>

            <?php elseif (
                $sale['payment_method'] === 'card'
            ): ?>

                <span class="badge">
                    بطاقة
                </span>

            <?php elseif (
                $sale['payment_method'] === 'credit'
            ): ?>

                <span class="badge badge-warning">
                    آجل
                </span>

            <?php endif; ?>

        </div>

    </div>


    <div class="table-responsive">

        <table class="table">

            <tbody>

                <tr>

                    <th>
                        رقم الفاتورة
                    </th>

                    <td>
                        <?= e(
                            $sale['invoice_no']
                        ) ?>
                    </td>


                    <th>
                        التاريخ
                    </th>

                    <td>
                        <?= e($saleDate) ?>
                    </td>

                </tr>


                <tr>

                    <th>
                        الوردية
                    </th>

                    <td>

                        <?= !empty($sale['shift_no'])
                            ? e($sale['shift_no'])
                            : '-'
                        ?>

                    </td>


                    <th>
                        حالة الوردية
                    </th>

                    <td>

                        <?php if (
                            $sale['shift_status']
                            === 'open'
                        ): ?>

                            <span class="badge badge-success">
                                مفتوحة
                            </span>

                        <?php elseif (
                            $sale['shift_status']
                            === 'closed'
                        ): ?>

                            <span class="badge">
                                مغلقة
                            </span>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </td>

                </tr>


                <tr>

                    <th>
                        الموظف
                    </th>

                    <td>

                        <?php if (
                            !empty(
                                $sale['employee_name']
                            )
                        ): ?>

                            <?= e(
                                $sale['employee_no']
                                . ' - '
                                . $sale['employee_name']
                            ) ?>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </td>


                    <th>
                        الطرمبة
                    </th>

                    <td>

                        <?= e(
                            'طرمبة '
                            . $sale['pump_no']
                            . ' - '
                            . $sale['pump_name']
                        ) ?>

                    </td>

                </tr>


                <tr>

                    <th>
                        طريقة الدفع
                    </th>

                    <td>

                        <?php if (
                            $sale['payment_method']
                            === 'cash'
                        ): ?>

                            <span class="badge badge-success">
                                نقدي
                            </span>

                        <?php elseif (
                            $sale['payment_method']
                            === 'card'
                        ): ?>

                            <span class="badge">
                                بطاقة
                            </span>

                        <?php elseif (
                            $sale['payment_method']
                            === 'credit'
                        ): ?>

                            <span class="badge badge-warning">
                                آجل
                            </span>

                        <?php endif; ?>

                    </td>


                    <th>
                        رقم العملية
                    </th>

                    <td>
                        <?= (int)$sale['id'] ?>
                    </td>

                </tr>

            </tbody>

        </table>

    </div>

</div>


<!-- =====================================================
     بيانات العميل
====================================================== -->

<?php if (
    $sale['payment_method'] === 'credit'
    ||
    !empty($sale['customer_id'])
): ?>

    <div class="card">

        <div class="page-header">

            <div>

                <h2>
                    👤 بيانات العميل
                </h2>

                <p>
                    بيانات العميل المرتبط بعملية البيع.
                </p>

            </div>

        </div>


        <?php if (
            !empty($sale['customer_id'])
            &&
            !empty($sale['customer_name'])
        ): ?>

            <div class="table-responsive">

                <table class="table">

                    <tbody>

                        <tr>

                            <th>
                                رقم العميل
                            </th>

                            <td>

                                <?= e(
                                    $sale['customer_no']
                                ) ?>

                            </td>


                            <th>
                                اسم العميل
                            </th>

                            <td>

                                <strong>

                                    <?= e(
                                        $sale['customer_name']
                                    ) ?>

                                </strong>

                            </td>

                        </tr>


                        <tr>

                            <th>
                                الهاتف
                            </th>

                            <td>

                                <?= !empty(
                                    $sale['customer_phone']
                                )
                                    ? e(
                                        $sale['customer_phone']
                                    )
                                    : '-'
                                ?>

                            </td>


                            <th>
                                العنوان
                            </th>

                            <td>

                                <?= !empty(
                                    $sale['customer_address']
                                )
                                    ? e(
                                        $sale['customer_address']
                                    )
                                    : '-'
                                ?>

                            </td>

                        </tr>


                        <?php if (
                            $sale['payment_method']
                            === 'credit'
                        ): ?>

                            <tr>

                                <th>
                                    حد الائتمان
                                </th>

                                <td>

                                    <?= money(
                                        $sale['credit_limit']
                                    ) ?>

                                </td>


                                <th>
                                    الرصيد الحالي
                                </th>

                                <td>

                                    <strong>

                                        <?= money(
                                            $sale['customer_balance']
                                        ) ?>

                                    </strong>

                                </td>

                            </tr>


                            <tr>

                                <th>
                                    حالة العميل
                                </th>

                                <td colspan="3">

                                    <?php

                                    $customerStatus =
                                        $sale['customer_status']
                                        ?? '';

                                    ?>


                                    <?php if (
                                        $customerStatus
                                        === 'active'
                                    ): ?>

                                        <span
                                            class="badge badge-success"
                                        >
                                            نشط
                                        </span>

                                    <?php elseif (
                                        $customerStatus
                                        === 'blocked'
                                    ): ?>

                                        <span
                                            class="badge badge-danger"
                                        >
                                            محظور
                                        </span>

                                    <?php else: ?>

                                        <span class="badge">
                                            غير نشط
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <?php if (
                $sale['payment_method']
                === 'credit'
            ): ?>

                <div class="alert alert-warning">

                    هذه الفاتورة مسجلة على حساب العميل:

                    <strong>
                        <?= e(
                            $sale['customer_name']
                        ) ?>
                    </strong>

                    <br>

                    قيمة الفاتورة:

                    <strong>
                        <?= money(
                            $sale['total']
                        ) ?>
                    </strong>

                </div>

            <?php endif; ?>


        <?php else: ?>

            <div class="alert alert-danger">

                هذه العملية مسجلة كبيع آجل ولكن لا يوجد
                عميل مرتبط بها.

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     تفاصيل الوقود
====================================================== -->

<div class="card">

    <div class="page-header">

        <div>

            <h2>
                تفاصيل البيع
            </h2>

            <p>
                الوقود والكمية وسعر الوحدة.
            </p>

        </div>

    </div>


    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        نوع الوقود
                    </th>

                    <th>
                        الكمية
                    </th>

                    <th>
                        سعر الوحدة
                    </th>

                    <th>
                        الإجمالي
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php foreach (
                $items as $index => $item
            ): ?>

                <tr>

                    <td>
                        <?= $index + 1 ?>
                    </td>


                    <td>

                        <strong>

                            <?= e(
                                $item['fuel_name']
                            ) ?>

                        </strong>


                        <?php if (
                            !empty($item['code'])
                        ): ?>

                            <small>

                                (
                                <?= e(
                                    $item['code']
                                ) ?>
                                )

                            </small>

                        <?php endif; ?>

                    </td>


                    <td>

                        <?= money(
                            $item['quantity']
                        ) ?>

                        <?= e(
                            $item['unit']
                            ?? 'لتر'
                        ) ?>

                    </td>


                    <td>

                        <?= money(
                            $item['unit_price']
                        ) ?>

                    </td>


                    <td>

                        <strong>

                            <?= money(
                                $item['total']
                            ) ?>

                        </strong>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =====================================================
     ملخص الفاتورة
====================================================== -->

<div class="card">

    <h2>
        ملخص الفاتورة
    </h2>


    <div class="table-responsive">

        <table class="table">

            <tbody>

                <tr>

                    <th>
                        المجموع الفرعي
                    </th>

                    <td>

                        <?= money(
                            $sale['subtotal']
                        ) ?>

                    </td>

                </tr>


                <tr>

                    <th>
                        الخصم
                    </th>

                    <td>

                        <?= money(
                            $sale['discount']
                        ) ?>

                    </td>

                </tr>


                <tr>

                    <th>
                        الإجمالي
                    </th>

                    <td>

                        <strong
                            style="font-size:20px;"
                        >

                            <?= money(
                                $sale['total']
                            ) ?>

                        </strong>

                    </td>

                </tr>


                <tr>

                    <th>
                        طريقة الدفع
                    </th>

                    <td>

                        <?= e(
                            $paymentMethodText
                        ) ?>

                    </td>

                </tr>

            </tbody>

        </table>

    </div>

</div>


<!-- =====================================================
     ملخص المديونية
====================================================== -->

<?php if (
    $sale['payment_method'] === 'credit'
    &&
    !empty($sale['customer_id'])
): ?>

    <div class="card">

        <h2>
            💳 معلومات المديونية
        </h2>


        <div class="cards">


            <div class="card stat-card">

                <div class="stat-icon">
                    🧾
                </div>

                <div class="stat-info">

                    <span>
                        قيمة الفاتورة الآجلة
                    </span>

                    <strong>

                        <?= money(
                            $sale['total']
                        ) ?>

                    </strong>

                </div>

            </div>


            <div class="card stat-card">

                <div class="stat-icon">
                    💰
                </div>

                <div class="stat-info">

                    <span>
                        رصيد العميل الحالي
                    </span>

                    <strong>

                        <?= money(
                            $sale['customer_balance']
                        ) ?>

                    </strong>

                </div>

            </div>


            <div class="card stat-card">

                <div class="stat-icon">
                    📊
                </div>

                <div class="stat-info">

                    <span>
                        حد الائتمان
                    </span>

                    <strong>

                        <?= money(
                            $sale['credit_limit']
                        ) ?>

                    </strong>

                </div>

            </div>

        </div>


        <?php

        $creditLimit =
            (float)$sale['credit_limit'];

        $customerBalance =
            (float)$sale['customer_balance'];

        $remainingCredit = 0;

        if ($creditLimit > 0) {

            $remainingCredit =
                max(
                    0,
                    $creditLimit - $customerBalance
                );
        }

        ?>


        <?php if (
            $creditLimit > 0
        ): ?>

            <div class="alert alert-warning">

                المتبقي من حد الائتمان:

                <strong>

                    <?= money(
                        $remainingCredit
                    ) ?>

                </strong>

            </div>

        <?php else: ?>

            <div class="alert alert-warning">

                حد الائتمان غير محدد.

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     الملاحظات
====================================================== -->

<?php if (
    !empty($sale['notes'])
): ?>

    <div class="card">

        <h2>
            📝 ملاحظات
        </h2>

        <p>

            <?= nl2br(
                e($sale['notes'])
            ) ?>

        </p>

    </div>

<?php endif; ?>


<!-- =====================================================
     الإجراءات
====================================================== -->

<div class="form-actions">

    <a
        href="print.php?id=<?= (int)$saleId ?>"
        target="_blank"
        class="btn btn-primary"
    >
        🖨 طباعة الفاتورة
    </a>


    <a
        href="index.php"
        class="btn"
    >
        العودة للمبيعات
    </a>


    <a
        href="delete.php?id=<?= (int)$saleId ?>"
        class="btn btn-danger"
        onclick="return confirm('تحذير: سيتم حذف عملية البيع وإرجاع كمية الوقود وتعديل إجماليات الوردية وحذف حركة العميل الآجل إن وجدت. هل أنت متأكد؟');"
    >
        🗑 حذف البيع
    </a>

</div>


<?php endif; ?>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>