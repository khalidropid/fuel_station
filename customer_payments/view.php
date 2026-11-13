<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'عرض سند التحصيل';

$payment = null;
$error = '';

try {

    $paymentId = (int)($_GET['id'] ?? 0);

    if ($paymentId <= 0) {
        throw new Exception('رقم سند التحصيل غير صحيح.');
    }

    /*
    |--------------------------------------------------------------------------
    | بيانات سند التحصيل
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            cp.id,
            cp.payment_no,
            cp.customer_id,
            cp.amount,
            cp.payment_method,
            cp.payment_date,
            cp.notes,
            cp.created_at,

            c.customer_no,
            c.full_name AS customer_name,
            c.phone,
            c.address,

            u.full_name AS created_by_name

        FROM customer_payments cp

        INNER JOIN customers c
            ON c.id = cp.customer_id

        LEFT JOIN users u
            ON u.id = cp.created_by

        WHERE cp.id = ?

        LIMIT 1
    ");

    $stmt->execute([$paymentId]);

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        throw new Exception('سند التحصيل غير موجود.');
    }

    /*
    |--------------------------------------------------------------------------
    | الحساب المالي للعميل
    |--------------------------------------------------------------------------
    */

    $customerAccount = accountingEnsureCustomerAccount(
        $pdo,
        (int)$payment['customer_id'],
        false
    );

    /*
    |--------------------------------------------------------------------------
    | رصيد العميل
    |--------------------------------------------------------------------------
    */

    $balanceData = accountingGetBalance(
        $pdo,
        (int)$customerAccount['id']
    );

    /*
    |--------------------------------------------------------------------------
    | المديونية الحالية
    |--------------------------------------------------------------------------
    |
    | حساب العميل:
    |
    | العميل إذا كان عليه مبلغ يظهر الرصيد بقيمة سالبة
    | في محرك المحاسبة الحالي.
    |
    */

    $customerBalance = (float)$balanceData['balance'];

    $currentDebt = max(
        0,
        round(-$customerBalance, 2)
    );

    /*
    |--------------------------------------------------------------------------
    | إجمالي المدفوع
    |--------------------------------------------------------------------------
    |
    | نستخدم سندات التحصيل الفعلية.
    |
    */

    $paidStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM customer_payments
        WHERE customer_id = ?
    ");

    $paidStmt->execute([
        $payment['customer_id']
    ]);

    $totalPaid = round(
        (float)$paidStmt->fetchColumn(),
        2
    );

    /*
    |--------------------------------------------------------------------------
    | كشف حساب العميل
    |--------------------------------------------------------------------------
    */

    $ledger = accountingGetLedger(
        $pdo,
        (int)$customerAccount['id']
    );

    /*
    |--------------------------------------------------------------------------
    | رصيد قبل السند
    |--------------------------------------------------------------------------
    */

    $balanceBeforePayment = 0;

    foreach ($ledger['rows'] as $row) {

        if (
            strtotime($row['transaction_date'])
            <=
            strtotime($payment['payment_date'])
        ) {

            $balanceBeforePayment =
                (float)$row['running_balance'];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | تنظيف الملاحظات
    |--------------------------------------------------------------------------
    */

    $notes = trim(
        (string)($payment['notes'] ?? '')
    );

} catch (Throwable $e) {

    $error = $e->getMessage();

}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>

        <h2>سند التحصيل</h2>

        <p>
            عرض تفاصيل سند تحصيل العميل.
        </p>

    </div>

    <div style="display:flex;gap:8px;flex-wrap:wrap;">

        <?php if ($payment): ?>

            <a
                href="print.php?id=<?= (int)$payment['id'] ?>"
                target="_blank"
                class="btn btn-primary"
            >
                🖨️ طباعة السند
            </a>

        <?php endif; ?>

        <a
            href="index.php"
            class="btn"
        >
            العودة للتحصيلات
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

<?php elseif ($payment): ?>


<!-- =========================================================
     بيانات السند
========================================================== -->

<div class="card">

    <div style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:20px;
        flex-wrap:wrap;
        margin-bottom:20px;
    ">

        <div>

            <span style="
                color:#6b7280;
                font-size:14px;
            ">
                رقم السند
            </span>

            <h2 style="margin:5px 0 0;">

                <?= htmlspecialchars(
                    $payment['payment_no'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h2>

        </div>

        <div style="
            text-align:left;
        ">

            <span style="
                color:#6b7280;
                font-size:14px;
            ">
                تاريخ السداد
            </span>

            <strong style="
                display:block;
                margin-top:5px;
            ">

                <?= htmlspecialchars(
                    $payment['payment_date'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>

        </div>

    </div>


    <div style="
        display:grid;
        grid-template-columns:
            repeat(auto-fit,minmax(220px,1fr));
        gap:16px;
    ">

        <div>

            <small style="color:#6b7280;">
                العميل
            </small>

            <strong style="
                display:block;
                margin-top:5px;
            ">

                <?= htmlspecialchars(
                    $payment['customer_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>

        </div>


        <div>

            <small style="color:#6b7280;">
                رقم العميل
            </small>

            <strong style="
                display:block;
                margin-top:5px;
            ">

                <?= htmlspecialchars(
                    $payment['customer_no'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>

        </div>


        <div>

            <small style="color:#6b7280;">
                الهاتف
            </small>

            <strong style="
                display:block;
                margin-top:5px;
            ">

                <?= htmlspecialchars(
                    $payment['phone'] ?: '-',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>

        </div>


        <div>

            <small style="color:#6b7280;">
                طريقة الدفع
            </small>

            <strong style="
                display:block;
                margin-top:5px;
            ">

                <?php

                echo match (
                    $payment['payment_method']
                ) {

                    'cash' => 'نقدي',

                    'card' => 'بطاقة',

                    default => 'أخرى'

                };

                ?>

            </strong>

        </div>

    </div>

</div>


<!-- =========================================================
     المبلغ
========================================================== -->

<div class="cards">

    <div class="card stat-card">

        <div class="stat-icon">
            💵
        </div>

        <div class="stat-info">

            <span>
                مبلغ التحصيل
            </span>

            <strong>

                <?= number_format(
                    (float)$payment['amount'],
                    2
                ) ?>

            </strong>

        </div>

    </div>


    <div class="card stat-card">

        <div class="stat-icon">
            📋
        </div>

        <div class="stat-info">

            <span>
                إجمالي المديونية الحالية
            </span>

            <strong>

                <?= number_format(
                    $currentDebt,
                    2
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
                إجمالي المدفوع
            </span>

            <strong>

                <?= number_format(
                    $totalPaid,
                    2
                ) ?>

            </strong>

        </div>

    </div>


    <div class="card stat-card">

        <div class="stat-icon">
            ⚖️
        </div>

        <div class="stat-info">

            <span>
                المتبقي على العميل
            </span>

            <strong>

                <?= number_format(
                    $currentDebt,
                    2
                ) ?>

            </strong>

        </div>

    </div>

</div>


<!-- =========================================================
     الملاحظات
========================================================== -->

<?php if ($notes !== ''): ?>

<div class="card">

    <h3>
        ملاحظات
    </h3>

    <p style="
        margin:10px 0 0;
        line-height:1.8;
    ">

        <?= nl2br(
            htmlspecialchars(
                $notes,
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>

    </p>

</div>

<?php endif; ?>


<!-- =========================================================
     الإجراءات
========================================================== -->

<div class="card">

    <div style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:10px;
        flex-wrap:wrap;
    ">

        <div>

            <h3 style="margin:0;">
                إجراءات السند
            </h3>

            <p style="
                margin:5px 0 0;
                color:#6b7280;
            ">
                يمكنك طباعة أو حذف السند.
            </p>

        </div>


        <div style="
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        ">

            <a
                href="print.php?id=<?= (int)$payment['id'] ?>"
                target="_blank"
                class="btn btn-primary"
            >
                🖨️ طباعة
            </a>

            <a
                href="delete.php?id=<?= (int)$payment['id'] ?>"
                class="btn btn-danger"
                onclick="return confirm('هل أنت متأكد من حذف سند التحصيل؟');"
            >
                🗑️ حذف السند
            </a>

        </div>

    </div>

</div>


<?php endif; ?>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>