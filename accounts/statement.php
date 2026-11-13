<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'كشف الحساب';

$error = '';

$account = null;
$balance = null;
$ledger = null;
$transactions = [];


/*
|--------------------------------------------------------------------------
| دالة حماية
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
| رقم الحساب
|--------------------------------------------------------------------------
*/

$accountId = (int)($_GET['id'] ?? 0);

if ($accountId <= 0) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| قراءة كشف الحساب
|--------------------------------------------------------------------------
*/

try {

    $account = accountingGetAccountById(
        $pdo,
        $accountId
    );

    if (!$account) {

        throw new RuntimeException(
            'الحساب غير موجود.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | الرصيد
    |--------------------------------------------------------------------------
    */

    $balance = accountingGetBalance(
        $pdo,
        $accountId
    );


    /*
    |--------------------------------------------------------------------------
    | كشف الحساب
    |--------------------------------------------------------------------------
    */

    $ledger = accountingGetLedger(
        $pdo,
        $accountId
    );


    $transactions = $ledger['rows'];


} catch (Throwable $e) {

    $error = $e->getMessage();
}


require_once __DIR__ . '/../includes/header.php';

?>


<style>

/*
|--------------------------------------------------------------------------
| شاشة كشف الحساب
|--------------------------------------------------------------------------
*/

.statement-page {
    direction: rtl;
}


/*
|--------------------------------------------------------------------------
| رأس الحساب
|--------------------------------------------------------------------------
*/

.statement-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
}

.statement-title h2 {
    margin: 0 0 8px;
}

.statement-title p {
    margin: 0;
    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| أزرار الإجراءات
|--------------------------------------------------------------------------
*/

.statement-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}


/*
|--------------------------------------------------------------------------
| بطاقة معلومات الحساب
|--------------------------------------------------------------------------
*/

.account-info-card {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 15px;
    margin-bottom: 20px;
}

.account-info-item {
    padding: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
}

.account-info-item span {
    display: block;
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 6px;
}

.account-info-item strong {
    display: block;
    font-size: 18px;
}


/*
|--------------------------------------------------------------------------
| بطاقات الرصيد
|--------------------------------------------------------------------------
*/

.statement-summary {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 15px;
    margin-bottom: 20px;
}

.summary-box {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 18px;
}

.summary-box .label {
    display: block;
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 8px;
}

.summary-box .value {
    font-size: 21px;
    font-weight: 700;
}


/*
|--------------------------------------------------------------------------
| ألوان الحالة
|--------------------------------------------------------------------------
*/

.text-danger {
    color: #dc2626;
}

.text-success {
    color: #16a34a;
}

.text-muted {
    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| جدول كشف الحساب
|--------------------------------------------------------------------------
*/

.statement-table-wrapper {
    overflow-x: auto;
}

.statement-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.statement-table th,
.statement-table td {
    border: 1px solid #d1d5db;
    padding: 11px 10px;
    text-align: center;
    vertical-align: middle;
}

.statement-table thead th {
    background: #f3f4f6;
    font-weight: 700;
}

.statement-table tbody tr:nth-child(even) {
    background: #fafafa;
}

.statement-table .text-right {
    text-align: right;
}

.statement-table .amount {
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| صف الرصيد الافتتاحي
|--------------------------------------------------------------------------
*/

.opening-row {
    background: #eff6ff !important;
    font-weight: 700;
}


/*
|--------------------------------------------------------------------------
| صف الرصيد النهائي
|--------------------------------------------------------------------------
*/

.closing-row {
    background: #f3f4f6 !important;
    font-weight: 700;
}


/*
|--------------------------------------------------------------------------
| الحالة
|--------------------------------------------------------------------------
*/

.status-badge {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.status-due {
    background: #fee2e2;
    color: #b91c1c;
}

.status-credit {
    background: #dcfce7;
    color: #15803d;
}

.status-balanced {
    background: #e5e7eb;
    color: #374151;
}


/*
|--------------------------------------------------------------------------
| لا توجد حركات
|--------------------------------------------------------------------------
*/

.empty-statement {
    text-align: center;
    padding: 35px !important;
    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| الاستجابة للشاشات الصغيرة
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    .account-info-card,
    .statement-summary {
        grid-template-columns: repeat(2, 1fr);
    }

}


@media (max-width: 600px) {

    .statement-header {
        flex-direction: column;
        align-items: stretch;
    }

    .account-info-card,
    .statement-summary {
        grid-template-columns: 1fr;
    }

}


/*
|--------------------------------------------------------------------------
| الطباعة
|--------------------------------------------------------------------------
|
| نطبع كشف الحساب فقط.
|
*/

@media print {

    @page {
        size: A4 landscape;
        margin: 10mm;
    }


    /*
    | إخفاء كل الصفحة
    */

    body * {
        visibility: hidden !important;
    }


    /*
    | إظهار منطقة الطباعة فقط
    */

    #printStatement,
    #printStatement * {
        visibility: visible !important;
    }


    /*
    | وضع منطقة الطباعة أعلى الصفحة
    */

    #printStatement {
        position: absolute !important;
        top: 0 !important;
        right: 0 !important;
        left: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }


    /*
    | إخفاء عناصر الشاشة
    */

    .no-print {
        display: none !important;
    }


    /*
    | معلومات الطباعة
    */

    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 15px;
    }


    .print-header h1 {
        margin: 0 0 5px;
        font-size: 20px;
    }


    .print-header p {
        margin: 3px 0;
        font-size: 12px;
    }


    /*
    | الجدول
    */

    .statement-table-wrapper {
        overflow: visible !important;
    }


    .statement-table {
        display: table !important;
        width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        table-layout: fixed !important;
    }


    .statement-table thead {
        display: table-header-group !important;
    }


    .statement-table tbody {
        display: table-row-group !important;
    }


    .statement-table tr {
        display: table-row !important;
        page-break-inside: avoid !important;
    }


    .statement-table th,
    .statement-table td {
        display: table-cell !important;
        border: 1px solid #000 !important;
        padding: 6px !important;
        color: #000 !important;
        background: #fff !important;
        font-size: 10px !important;
    }


    .statement-table thead th {
        background: #e5e7eb !important;
        font-weight: bold !important;
    }


    .opening-row td {
        background: #f3f4f6 !important;
    }


    .closing-row td {
        background: #f3f4f6 !important;
    }


    /*
    | منع ظهور الروابط والأزرار
    */

    a {
        text-decoration: none !important;
        color: #000 !important;
    }


    /*
    | إظهار حالة الرصيد بدون شكل البادج
    */

    .status-badge {
        background: transparent !important;
        color: #000 !important;
        padding: 0 !important;
    }

}

</style>


<div class="statement-page">


<?php if ($error): ?>


    <div class="alert alert-danger no-print">

        <?= e($error) ?>

    </div>


<?php else: ?>


    <!-- =====================================================
         رأس الصفحة
    ====================================================== -->

    <div class="statement-header no-print">

        <div class="statement-title">

            <h2>
                كشف الحساب
            </h2>

            <p>
                <?= e($account['account_name']) ?>
                -
                <?= e($account['account_no']) ?>
            </p>

        </div>


        <div class="statement-actions">

            <button
                type="button"
                class="btn btn-primary"
                onclick="printStatement()"
            >
                🖨️ طباعة الكشف
            </button>


            <a
                href="transaction.php?account_id=<?= (int)$accountId ?>"
                class="btn btn-success"
            >
                + إضافة حركة
            </a>


            <a
                href="index.php"
                class="btn"
            >
                دليل الحسابات
            </a>

        </div>

    </div>


    <!-- =====================================================
         منطقة الطباعة
    ====================================================== -->

    <div id="printStatement">


        <!-- =================================================
             عنوان خاص بالطباعة
        ================================================== -->

        <div class="print-header">

            <h1>
                كشف حساب
            </h1>

            <p>
                <?= e($account['account_name']) ?>
            </p>

            <p>
                رقم الحساب:
                <?= e($account['account_no']) ?>
            </p>

            <p>
                تاريخ الطباعة:
                <?= date('Y-m-d H:i') ?>
            </p>

        </div>


        <!-- =================================================
             معلومات الحساب
        ================================================== -->

        <div class="account-info-card no-print">


            <div class="account-info-item">

                <span>
                    رقم الحساب
                </span>

                <strong>
                    <?= e($account['account_no']) ?>
                </strong>

            </div>


            <div class="account-info-item">

                <span>
                    اسم الحساب
                </span>

                <strong>
                    <?= e($account['account_name']) ?>
                </strong>

            </div>


            <div class="account-info-item">

                <span>
                    نوع الحساب
                </span>

                <strong>

                    <?php

                    $types = [

                        'cash'     => 'خزينة',
                        'bank'     => 'بنك',
                        'owner'    => 'مالك',
                        'employee' => 'موظف',
                        'customer' => 'عميل',
                        'supplier' => 'مورد',
                        'expense'  => 'مصروف',
                        'other'    => 'أخرى'

                    ];

                    echo e(
                        $types[
                            $account['account_type']
                        ]
                        ??
                        $account['account_type']
                    );

                    ?>

                </strong>

            </div>


            <div class="account-info-item">

                <span>
                    الحالة
                </span>

                <strong>

                    <?= $account['status'] === 'active'
                        ? 'نشط'
                        : 'غير نشط'
                    ?>

                </strong>

            </div>

        </div>


        <!-- =================================================
             ملخص الحساب
        ================================================== -->

        <div class="statement-summary no-print">


            <div class="summary-box">

                <span class="label">
                    إجمالي المدين
                </span>

                <span class="value">

                    <?= number_format(
                        $balance['debit'],
                        2
                    ) ?>

                </span>

            </div>


            <div class="summary-box">

                <span class="label">
                    إجمالي الدائن
                </span>

                <span class="value">

                    <?= number_format(
                        $balance['credit'],
                        2
                    ) ?>

                </span>

            </div>


            <div class="summary-box">

                <span class="label">
                    الرصيد الحالي
                </span>

                <span class="value">

                    <?= number_format(
                        abs($balance['balance']),
                        2
                    ) ?>

                </span>

            </div>


            <div class="summary-box">

                <span class="label">
                    حالة الرصيد
                </span>

                <span class="value">

                    <?php if ($balance['balance'] < -0.009): ?>

                        <span class="text-success">
                            له
                        </span>

                    <?php elseif ($balance['balance'] > 0.009): ?>

                        <span class="text-danger">
                            عليه
                        </span>

                    <?php else: ?>

                        <span class="text-muted">
                            متزن
                        </span>

                    <?php endif; ?>

                </span>

            </div>

        </div>


        <!-- =================================================
             جدول كشف الحساب
        ================================================== -->

        <div class="card">


            <div class="statement-table-wrapper">


                <table class="statement-table">


                    <thead>

                        <tr>

                            <th style="width: 12%;">
                                التاريخ
                            </th>

                            <th style="width: 14%;">
                                رقم الحركة
                            </th>

                            <th style="width: 30%;">
                                البيان
                            </th>

                            <th style="width: 12%;">
                                مدين
                            </th>

                            <th style="width: 12%;">
                                دائن
                            </th>

                            <th style="width: 10%;">
                                الرصيد
                            </th>

                            <th style="width: 10%;">
                                الحالة
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <!-- =================================
                             الرصيد الافتتاحي
                        ================================== -->

                        <?php

                        $runningBalance =
                            round(
                                $ledger['opening_credit']
                                -
                                $ledger['opening_debit'],
                                2
                            );

                        ?>


                        <tr class="opening-row">

                            <td>
                                -
                            </td>

                            <td>
                                -
                            </td>

                            <td class="text-right">
                                الرصيد الافتتاحي
                            </td>

                            <td class="amount">

                                <?= number_format(
                                    $ledger['opening_debit'],
                                    2
                                ) ?>

                            </td>

                            <td class="amount">

                                <?= number_format(
                                    $ledger['opening_credit'],
                                    2
                                ) ?>

                            </td>

                            <td class="amount">

                                <?= number_format(
                                    abs($runningBalance),
                                    2
                                ) ?>

                            </td>

                            <td>

                                <?php if ($runningBalance > 0.009): ?>

                                    <span class="status-badge status-credit">
                                        له
                                    </span>

                                <?php elseif ($runningBalance < -0.009): ?>

                                    <span class="status-badge status-due">
                                        عليه
                                    </span>

                                <?php else: ?>

                                    <span class="status-badge status-balanced">
                                        متزن
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>


                        <!-- =================================
                             الحركات
                        ================================== -->

                        <?php if (empty($transactions)): ?>


                            <tr>

                                <td
                                    colspan="7"
                                    class="empty-statement"
                                >

                                    لا توجد حركات مالية لهذا الحساب.

                                </td>

                            </tr>


                        <?php else: ?>


                            <?php foreach (
                                $transactions
                                as $transaction
                            ): ?>


                                <?php

                                $runningBalance =
                                    round(
                                        $runningBalance
                                        +
                                        (float)$transaction['credit']
                                        -
                                        (float)$transaction['debit'],
                                        2
                                    );

                                ?>


                                <tr>


                                    <td>

                                        <?= e(
                                            $transaction[
                                                'transaction_date'
                                            ]
                                        ) ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= e(
                                                $transaction[
                                                    'transaction_no'
                                                ]
                                            ) ?>

                                        </strong>

                                    </td>


                                    <td class="text-right">

                                        <?= e(
                                            $transaction[
                                                'description'
                                            ]
                                        ) ?>

                                    </td>


                                    <td class="amount">

                                        <?php if (
                                            (float)$transaction['debit']
                                            > 0
                                        ): ?>

                                            <?= number_format(
                                                (float)$transaction['debit'],
                                                2
                                            ) ?>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>


                                    <td class="amount">

                                        <?php if (
                                            (float)$transaction['credit']
                                            > 0
                                        ): ?>

                                            <?= number_format(
                                                (float)$transaction['credit'],
                                                2
                                            ) ?>

                                        <?php else: ?>

                                            -

                                        <?php endif; ?>

                                    </td>


                                    <td class="amount">

                                        <?= number_format(
                                            abs($runningBalance),
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            $runningBalance > 0.009
                                        ): ?>

                                            <span
                                                class="status-badge status-credit"
                                            >
                                                له
                                            </span>

                                        <?php elseif (
                                            $runningBalance < -0.009
                                        ): ?>

                                            <span
                                                class="status-badge status-due"
                                            >
                                                عليه
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="status-badge status-balanced"
                                            >
                                                متزن
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php endif; ?>


                        <!-- =================================
                             الرصيد النهائي
                        ================================== -->

                        <tr class="closing-row">

                            <td>
                                -
                            </td>

                            <td>
                                -
                            </td>

                            <td class="text-right">

                                الرصيد النهائي

                            </td>

                            <td>

                                <?= number_format(
                                    $balance['debit'],
                                    2
                                ) ?>

                            </td>

                            <td>

                                <?= number_format(
                                    $balance['credit'],
                                    2
                                ) ?>

                            </td>

                            <td>

                                <?= number_format(
                                    abs($balance['balance']),
                                    2
                                ) ?>

                            </td>

                            <td>

                                <?php if (
                                    $balance['balance'] > 0.009
                                ): ?>

                                    عليه

                                <?php elseif (
                                    $balance['balance'] < -0.009
                                ): ?>

                                    له

                                <?php else: ?>

                                    متزن

                                <?php endif; ?>

                            </td>

                        </tr>


                    </tbody>

                </table>

            </div>

        </div>


    </div>

</div>


<script>

function printStatement()
{
    window.print();
}

</script>


<?php endif; ?>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>