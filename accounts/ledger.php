<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'كشف الحساب';

$error = '';

$accountId = (int)($_GET['id'] ?? 0);

if ($accountId <= 0) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| البيانات
|--------------------------------------------------------------------------
*/

$ledger = null;
$account = null;
$balance = null;

try {

    /*
    |--------------------------------------------------------------------------
    | جلب الحساب
    |--------------------------------------------------------------------------
    */

    $account = accountingGetAccountById(
        $pdo,
        $accountId
    );

    if (!$account) {

        throw new RuntimeException(
            'الحساب المطلوب غير موجود.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | جلب الرصيد
    |--------------------------------------------------------------------------
    */

    $balance = accountingGetBalance(
        $pdo,
        $accountId
    );


    /*
    |--------------------------------------------------------------------------
    | جلب كشف الحساب
    |--------------------------------------------------------------------------
    */

    $ledger = accountingGetLedger(
        $pdo,
        $accountId
    );


} catch (Throwable $e) {

    $error = $e->getMessage();
}


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
| أنواع الحسابات
|--------------------------------------------------------------------------
*/

$accountTypes = [

    'cash'     => 'خزينة',
    'bank'     => 'بنك',
    'owner'    => 'المالك',
    'employee' => 'موظف',
    'customer' => 'عميل',
    'supplier' => 'مورد',
    'expense'  => 'مصروف',
    'other'    => 'أخرى'

];


/*
|--------------------------------------------------------------------------
| نوع الحساب
|--------------------------------------------------------------------------
*/

$accountType =
    $accountTypes[
        $account['account_type'] ?? ''
    ]
    ??
    ($account['account_type'] ?? '');


/*
|--------------------------------------------------------------------------
| البيانات المحاسبية
|--------------------------------------------------------------------------
*/

$openingDebit =
    $ledger['opening_debit'] ?? 0;

$openingCredit =
    $ledger['opening_credit'] ?? 0;

$transactions =
    $ledger['rows'] ?? [];

$totalDebit =
    $balance['debit'] ?? 0;

$totalCredit =
    $balance['credit'] ?? 0;

$netBalance =
    $balance['balance'] ?? 0;

$status =
    $balance['status'] ?? 'متوازن';


/*
|--------------------------------------------------------------------------
| قيمة العرض
|--------------------------------------------------------------------------
*/

$displayBalance =
    abs((float)$netBalance);


/*
|--------------------------------------------------------------------------
| كلاس الحالة
|--------------------------------------------------------------------------
*/

$statusClass = 'secondary';

if ($status === 'عليه') {

    $statusClass = 'danger';

} elseif ($status === 'له') {

    $statusClass = 'success';
}


/*
|--------------------------------------------------------------------------
| الرصيد الافتتاحي
|--------------------------------------------------------------------------
*/

$openingNet =
    round(
        $openingCredit - $openingDebit,
        2
    );


require_once __DIR__ . '/../includes/header.php';

?>


<style>

/* =========================================================
   كشف الحساب
========================================================= */

.ledger-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    margin-bottom: 25px;

    flex-wrap: wrap;

}


.ledger-account {

    display: flex;

    align-items: center;

    gap: 15px;

}


.ledger-account-icon {

    width: 60px;

    height: 60px;

    border-radius: 14px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #eff6ff;

    color: #2563eb;

    font-size: 28px;

}


.ledger-account-info h2 {

    margin: 0 0 5px;

}


.ledger-account-info p {

    margin: 0;

    color: #6b7280;

}


/* =========================================================
   بطاقات الملخص
========================================================= */

.ledger-summary {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(180px, 1fr));

    gap: 18px;

    margin-bottom: 25px;

}


.ledger-stat {

    background: #fff;

    border-radius: 14px;

    padding: 20px;

    border: 1px solid #e5e7eb;

    box-shadow:
        0 2px 8px rgba(0,0,0,.04);

}


.ledger-stat-label {

    display: block;

    color: #6b7280;

    font-size: 14px;

    margin-bottom: 10px;

}


.ledger-stat-value {

    font-size: 24px;

    font-weight: 700;

}


.ledger-stat-debit
.ledger-stat-value {

    color: #dc2626;

}


.ledger-stat-credit
.ledger-stat-value {

    color: #16a34a;

}


/* =========================================================
   معلومات الحساب
========================================================= */

.account-info-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(180px, 1fr));

    gap: 15px;

}


.account-info-item {

    background: #f9fafb;

    border: 1px solid #e5e7eb;

    border-radius: 10px;

    padding: 15px;

}


.account-info-item span {

    display: block;

    font-size: 13px;

    color: #6b7280;

    margin-bottom: 5px;

}


.account-info-item strong {

    font-size: 15px;

}


/* =========================================================
   جدول الكشف
========================================================= */

.ledger-table {

    width: 100%;

    border-collapse: collapse;

}


.ledger-table th {

    background: #f8fafc;

    color: #374151;

    font-weight: 700;

    white-space: nowrap;

    padding: 14px;

    border-bottom: 2px solid #e5e7eb;

}


.ledger-table td {

    padding: 13px 14px;

    border-bottom: 1px solid #f1f5f9;

    vertical-align: middle;

}


.ledger-table tbody tr:hover {

    background: #f8fafc;

}


/* =========================================================
   الرصيد
========================================================= */

.balance-positive {

    color: #dc2626;

    font-weight: 700;

}


.balance-negative {

    color: #16a34a;

    font-weight: 700;

}


.balance-zero {

    color: #6b7280;

    font-weight: 700;

}


/* =========================================================
   الشارات
========================================================= */

.ledger-badge {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 5px 10px;

    border-radius: 999px;

    font-size: 12px;

    font-weight: 600;

}


.ledger-badge-danger {

    background: #fee2e2;

    color: #b91c1c;

}


.ledger-badge-success {

    background: #dcfce7;

    color: #15803d;

}


.ledger-badge-secondary {

    background: #f3f4f6;

    color: #4b5563;

}


.opening-row {

    background: #eff6ff;

    font-weight: 600;

}


.total-row {

    background: #f8fafc;

    font-weight: 700;

}


@media (max-width: 1000px) {

    .ledger-summary {

        grid-template-columns:
            repeat(2, 1fr);

    }

    .account-info-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media (max-width: 600px) {

    .ledger-summary {

        grid-template-columns: 1fr;

    }

    .account-info-grid {

        grid-template-columns: 1fr;

    }

    .ledger-table {

        min-width: 900px;

    }

}

</style>


<!-- =========================================================
     رأس كشف الحساب
========================================================== -->

<div class="ledger-header">

    <div class="ledger-account">

        <div class="ledger-account-icon">
            📒
        </div>

        <div class="ledger-account-info">

            <h2>
                كشف حساب
            </h2>

            <p>

                <?= e($account['account_name']) ?>

                -

                <?= e($account['account_no']) ?>

            </p>

        </div>

    </div>


    <div>

        <a
            href="transaction.php?account_id=<?= $accountId ?>"
            class="btn btn-primary"
        >
            + إضافة حركة
        </a>


        <a
            href="index.php"
            class="btn"
        >
            ← دليل الحسابات
        </a>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= e($error) ?>

    </div>

<?php else: ?>


<!-- =========================================================
     ملخص الحساب
========================================================== -->

<div class="ledger-summary">


    <!-- المدين -->

    <div class="ledger-stat">

        <span class="ledger-stat-label">
            إجمالي المدين
        </span>

        <div
            class="ledger-stat-value"
            style="color:#dc2626;"
        >

            <?= number_format(
                $totalDebit,
                2
            ) ?>

        </div>

    </div>


    <!-- الدائن -->

    <div class="ledger-stat">

        <span class="ledger-stat-label">
            إجمالي الدائن
        </span>

        <div
            class="ledger-stat-value"
            style="color:#16a34a;"
        >

            <?= number_format(
                $totalCredit,
                2
            ) ?>

        </div>

    </div>


    <!-- الرصيد -->

    <div class="ledger-stat">

        <span class="ledger-stat-label">
            الرصيد الحالي
        </span>

        <div class="ledger-stat-value">

            <?= number_format(
                $displayBalance,
                2
            ) ?>

        </div>

    </div>


    <!-- الحالة -->

    <div class="ledger-stat">

        <span class="ledger-stat-label">
            حالة الحساب
        </span>

        <div>

            <span
                class="ledger-badge ledger-badge-<?= e($statusClass) ?>"
            >

                <?= e($status) ?>

            </span>

        </div>

    </div>

</div>


<!-- =========================================================
     بيانات الحساب
========================================================== -->

<div class="card">

    <h3>
        معلومات الحساب
    </h3>


    <div class="account-info-grid">

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
                <?= e($accountType) ?>
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


        <div class="account-info-item">

            <span>
                الرصيد الافتتاحي المدين
            </span>

            <strong>

                <?= number_format(
                    $openingDebit,
                    2
                ) ?>

            </strong>

        </div>


        <div class="account-info-item">

            <span>
                الرصيد الافتتاحي الدائن
            </span>

            <strong>

                <?= number_format(
                    $openingCredit,
                    2
                ) ?>

            </strong>

        </div>


        <div class="account-info-item">

            <span>
                صافي الرصيد الافتتاحي
            </span>

            <strong>

                <?= number_format(
                    abs($openingNet),
                    2
                ) ?>

                <?php if ($openingNet > 0): ?>

                    <small>
                        له
                    </small>

                <?php elseif ($openingNet < 0): ?>

                    <small>
                        عليه
                    </small>

                <?php else: ?>

                    <small>
                        متزن
                    </small>

                <?php endif; ?>

            </strong>

        </div>


        <div class="account-info-item">

            <span>
                عدد الحركات
            </span>

            <strong>
                <?= count($transactions) ?>
            </strong>

        </div>

    </div>

</div>


<!-- =========================================================
     كشف الحركات
========================================================== -->

<div class="card">

    <div class="page-header">

        <div>

            <h2>
                الحركات المالية
            </h2>

            <p>
                جميع الحركات المسجلة على هذا الحساب.
            </p>

        </div>

        <div>

            <input
                type="text"
                id="ledgerSearch"
                placeholder="بحث في الحركات..."
                style="max-width:240px;"
            >

        </div>

    </div>


    <div class="table-responsive">

        <table
            class="ledger-table"
            id="ledgerTable"
        >

            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        التاريخ
                    </th>

                    <th>
                        رقم الحركة
                    </th>

                    <th>
                        البيان
                    </th>

                    <th>
                        مدين
                    </th>

                    <th>
                        دائن
                    </th>

                    <th>
                        الرصيد
                    </th>

                    <th>
                        الحالة
                    </th>

                </tr>

            </thead>


            <tbody>


                <!-- الرصيد الافتتاحي -->

                <tr class="opening-row">

                    <td>
                        -
                    </td>

                    <td>
                        -
                    </td>

                    <td>
                        -
                    </td>

                    <td>
                        الرصيد الافتتاحي
                    </td>

                    <td>

                        <?= number_format(
                            $openingDebit,
                            2
                        ) ?>

                    </td>

                    <td>

                        <?= number_format(
                            $openingCredit,
                            2
                        ) ?>

                    </td>

                    <td>

                        <?= number_format(
                            abs($openingNet),
                            2
                        ) ?>

                    </td>

                    <td>

                        <?php if ($openingNet > 0): ?>

                            <span class="ledger-badge ledger-badge-success">
                                له
                            </span>

                        <?php elseif ($openingNet < 0): ?>

                            <span class="ledger-badge ledger-badge-danger">
                                عليه
                            </span>

                        <?php else: ?>

                            <span class="ledger-badge ledger-badge-secondary">
                                متزن
                            </span>

                        <?php endif; ?>

                    </td>

                </tr>


                <?php if (empty($transactions)): ?>

                    <tr>

                        <td
                            colspan="8"
                            style="
                                text-align:center;
                                padding:40px;
                                color:#6b7280;
                            "
                        >

                            لا توجد حركات مالية لهذا الحساب.

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach (
                        $transactions
                        as $index => $transaction
                    ): ?>


                        <?php

                        $running =
                            (float)$transaction[
                                'running_balance'
                            ];

                        $transactionStatus =
                            $transaction[
                                'balance_status'
                            ];

                        ?>


                        <tr
                            data-search="<?= e(
                                ($transaction['transaction_no'] ?? '')
                                . ' '
                                .
                                ($transaction['description'] ?? '')
                                . ' '
                                .
                                ($transaction['transaction_date'] ?? '')
                            ) ?>"
                        >

                            <td>

                                <?= $index + 1 ?>

                            </td>


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
                                        ] ?? ''
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    $transaction[
                                        'description'
                                    ] ?? ''
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    (float)$transaction['debit'] > 0
                                ): ?>

                                    <span
                                        style="
                                            color:#dc2626;
                                            font-weight:600;
                                        "
                                    >

                                        <?= number_format(
                                            (float)$transaction['debit'],
                                            2
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (
                                    (float)$transaction['credit'] > 0
                                ): ?>

                                    <span
                                        style="
                                            color:#16a34a;
                                            font-weight:600;
                                        "
                                    >

                                        <?= number_format(
                                            (float)$transaction['credit'],
                                            2
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>

                                <strong>

                                    <?= number_format(
                                        abs($running),
                                        2
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?php if (
                                    $transactionStatus === 'له'
                                ): ?>

                                    <span
                                        class="ledger-badge ledger-badge-success"
                                    >
                                        له
                                    </span>

                                <?php elseif (
                                    $transactionStatus === 'عليه'
                                ): ?>

                                    <span
                                        class="ledger-badge ledger-badge-danger"
                                    >
                                        عليه
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="ledger-badge ledger-badge-secondary"
                                    >
                                        متزن
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                <!-- الإجمالي -->

                <tr class="total-row">

                    <td colspan="4">

                        الإجمالي

                    </td>

                    <td>

                        <?= number_format(
                            $totalDebit,
                            2
                        ) ?>

                    </td>

                    <td>

                        <?= number_format(
                            $totalCredit,
                            2
                        ) ?>

                    </td>

                    <td>

                        <?= number_format(
                            $displayBalance,
                            2
                        ) ?>

                    </td>

                    <td>

                        <span
                            class="ledger-badge ledger-badge-<?= e($statusClass) ?>"
                        >

                            <?= e($status) ?>

                        </span>

                    </td>

                </tr>


            </tbody>

        </table>

    </div>

</div>


<?php endif; ?>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const search =
            document.getElementById(
                'ledgerSearch'
            );


        const rows =
            document.querySelectorAll(
                '#ledgerTable tbody tr[data-search]'
            );


        if (!search) {
            return;
        }


        search.addEventListener(
            'input',
            function () {

                const value =
                    this.value
                        .trim()
                        .toLowerCase();


                rows.forEach(
                    function (row) {

                        const text =
                            (
                                row.dataset.search
                                || ''
                            )
                            .toLowerCase();


                        row.style.display =
                            text.includes(value)
                                ? ''
                                : 'none';

                    }
                );

            }
        );

    }
);

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>