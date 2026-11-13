<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'دليل الحسابات';

$error = '';

$accounts = [];

$totals = [
    'debit'  => 0,
    'credit' => 0,
    'balance' => 0
];


/*
|--------------------------------------------------------------------------
| حماية النصوص
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
| قراءة الحسابات
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT

            a.id,
            a.account_no,
            a.account_name,
            a.account_type,
            a.owner_id,
            a.employee_id,
            a.customer_id,
            a.opening_balance,
            a.status,
            a.notes,
            a.created_at,

            COALESCE(
                SUM(at.debit),
                0
            ) AS total_debit,

            COALESCE(
                SUM(at.credit),
                0
            ) AS total_credit

        FROM accounts a

        LEFT JOIN account_transactions at
            ON at.account_id = a.id

        GROUP BY

            a.id,
            a.account_no,
            a.account_name,
            a.account_type,
            a.owner_id,
            a.employee_id,
            a.customer_id,
            a.opening_balance,
            a.status,
            a.notes,
            a.created_at

        ORDER BY

            CASE a.account_type

                WHEN 'cash' THEN 1
                WHEN 'bank' THEN 2
                WHEN 'owner' THEN 3
                WHEN 'employee' THEN 4
                WHEN 'customer' THEN 5
                WHEN 'supplier' THEN 6
                WHEN 'expense' THEN 7
                ELSE 8

            END,

            a.account_no ASC
    ");

    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | حساب الأرصدة
    |--------------------------------------------------------------------------
    */

    foreach ($accounts as &$account) {

        $openingDebit =
            accountingAccountOpeningDebit(
                $account
            );

        $openingCredit =
            accountingAccountOpeningCredit(
                $account
            );


        $transactionDebit =
            (float)$account['total_debit'];

        $transactionCredit =
            (float)$account['total_credit'];


        /*
        |--------------------------------------------------------------------------
        | إجمالي المدين والدائن
        |--------------------------------------------------------------------------
        */

        $totalDebit =
            round(
                $openingDebit +
                    $transactionDebit,
                2
            );


        $totalCredit =
            round(
                $openingCredit +
                    $transactionCredit,
                2
            );


        /*
        |--------------------------------------------------------------------------
        | الرصيد
        |--------------------------------------------------------------------------
        |
        | موجب = له
        | سالب = عليه
        |
        */

        $balance =
            round(
                $totalCredit -
                    $totalDebit,
                2
            );


        $account['total_debit'] =
            $totalDebit;

        $account['total_credit'] =
            $totalCredit;

        $account['balance'] =
            $balance;


        /*
        |--------------------------------------------------------------------------
        | اتجاه الرصيد
        |--------------------------------------------------------------------------
        */

        if ($balance > 0.009) {

            $account['direction'] = 'له';

            $account['direction_class'] =
                'badge-success';

            $account['direction_amount'] =
                $balance;
        } elseif ($balance < -0.009) {

            $account['direction'] = 'عليه';

            $account['direction_class'] =
                'badge-danger';

            $account['direction_amount'] =
                abs($balance);
        } else {

            $account['direction'] = 'متزن';

            $account['direction_class'] =
                'badge-secondary';

            $account['direction_amount'] =
                0;
        }


        /*
        |--------------------------------------------------------------------------
        | الإجماليات
        |--------------------------------------------------------------------------
        */

        $totals['debit'] +=
            $totalDebit;

        $totals['credit'] +=
            $totalCredit;

        $totals['balance'] +=
            $balance;
    }

    unset($account);


    /*
    |--------------------------------------------------------------------------
    | تقريب الإجماليات
    |--------------------------------------------------------------------------
    */

    $totals['debit'] =
        round(
            $totals['debit'],
            2
        );

    $totals['credit'] =
        round(
            $totals['credit'],
            2
        );

    $totals['balance'] =
        round(
            $totals['balance'],
            2
        );
} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';

?>


<style>
    /*
|--------------------------------------------------------------------------
| Accounts Page
|--------------------------------------------------------------------------
*/

    .accounts-page {

        direction: rtl;

        display: flex;

        flex-direction: column;

        gap: 20px;

    }


    /*
|--------------------------------------------------------------------------
| Page Header
|--------------------------------------------------------------------------
*/

    .accounts-header {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 15px;

        flex-wrap: wrap;

    }


    .accounts-header-info h2 {

        margin: 0 0 6px;

    }


    .accounts-header-info p {

        margin: 0;

        color: #6b7280;

    }


    /*
|--------------------------------------------------------------------------
| Summary Cards
|--------------------------------------------------------------------------
*/

    .accounts-summary {

        display: grid;

        grid-template-columns:
            repeat(4, minmax(0, 1fr));

        gap: 16px;

    }


    .account-stat {

        background: #fff;

        border: 1px solid #e5e7eb;

        border-radius: 14px;

        padding: 20px;

        display: flex;

        align-items: center;

        gap: 15px;

        box-shadow:
            0 2px 8px rgba(0, 0, 0, .04);

    }


    .account-stat-icon {

        width: 50px;

        height: 50px;

        min-width: 50px;

        border-radius: 12px;

        display: flex;

        align-items: center;

        justify-content: center;

        font-size: 22px;

        background: #f3f4f6;

    }


    .account-stat-content {

        display: flex;

        flex-direction: column;

        gap: 5px;

    }


    .account-stat-content span {

        color: #6b7280;

        font-size: 13px;

    }


    .account-stat-content strong {

        color: #111827;

        font-size: 21px;

    }


    .account-stat-content small {

        font-size: 12px;

        color: #6b7280;

    }


    /*
|--------------------------------------------------------------------------
| Toolbar
|--------------------------------------------------------------------------
*/

    .accounts-toolbar {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 15px;

        flex-wrap: wrap;

        padding-bottom: 18px;

        margin-bottom: 5px;

        border-bottom: 1px solid #e5e7eb;

    }


    .accounts-toolbar-title h3 {

        margin: 0;

        font-size: 18px;

    }


    .accounts-toolbar-title p {

        margin: 5px 0 0;

        color: #6b7280;

        font-size: 13px;

    }


    /*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

    .account-search-wrapper {

        position: relative;

        width: 300px;

        max-width: 100%;

    }


    .account-search {

        width: 100%;

        height: 44px;

        padding: 0 42px 0 14px;

        border: 1px solid #d1d5db;

        border-radius: 10px;

        outline: none;

        font-family: inherit;

        background: #fff;

    }


    .account-search:focus {

        border-color: #2563eb;

        box-shadow:
            0 0 0 3px rgba(37, 99, 235, .10);

    }


    .account-search-icon {

        position: absolute;

        right: 14px;

        top: 50%;

        transform:
            translateY(-50%);

        color: #6b7280;

        pointer-events: none;

    }


    /*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

    .accounts-table-wrapper {

        overflow-x: auto;

    }


    .accounts-table {

        width: 100%;

        min-width: 1100px;

        border-collapse: collapse;

    }


    .accounts-table th {

        background: #f9fafb;

        color: #4b5563;

        font-size: 13px;

        font-weight: 600;

        padding: 14px 15px;

        text-align: right;

        white-space: nowrap;

        border-bottom:
            1px solid #e5e7eb;

    }


    .accounts-table td {

        padding: 15px;

        border-bottom:
            1px solid #f1f5f9;

        color: #374151;

        vertical-align: middle;

    }


    .accounts-table tbody tr {

        transition:
            background .15s ease;

    }


    .accounts-table tbody tr:hover {

        background: #f8fafc;

    }


    .accounts-table tbody tr:last-child td {

        border-bottom: none;

    }


    /*
|--------------------------------------------------------------------------
| Account Number
|--------------------------------------------------------------------------
*/

    .account-number {

        font-family: monospace;

        direction: ltr;

        display: inline-block;

        background: #f3f4f6;

        padding: 5px 8px;

        border-radius: 6px;

        font-size: 13px;

    }


    /*
|--------------------------------------------------------------------------
| Account Name
|--------------------------------------------------------------------------
*/

    .account-name {

        font-weight: 700;

        color: #111827;

    }


    .account-name-wrapper {

        display: flex;

        flex-direction: column;

        gap: 4px;

    }


    .account-notes {

        color: #9ca3af;

        font-size: 11px;

    }


    /*
|--------------------------------------------------------------------------
| Amounts
|--------------------------------------------------------------------------
*/

    .amount {

        font-weight: 600;

        white-space: nowrap;

    }


    .amount-debit {

        color: #b91c1c;

    }


    .amount-credit {

        color: #047857;

    }


    /*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

    .account-actions {

        display: flex;

        align-items: center;

        gap: 6px;

        flex-wrap: wrap;

    }


    .account-actions .btn {

        white-space: nowrap;

    }


    /*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

    .accounts-empty {

        text-align: center;

        padding: 55px 20px !important;

    }


    .accounts-empty-icon {

        font-size: 40px;

        margin-bottom: 10px;

    }


    .accounts-empty strong {

        display: block;

        margin-bottom: 6px;

    }


    .accounts-empty span {

        color: #9ca3af;

        font-size: 13px;

    }


    /*
|--------------------------------------------------------------------------
| Search Empty
|--------------------------------------------------------------------------
*/

    .accounts-no-results {

        display: none;

        text-align: center;

        padding: 40px;

        color: #6b7280;

    }


    /*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

    @media (max-width: 1100px) {

        .accounts-summary {

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

        }

    }


    @media (max-width: 650px) {

        .accounts-summary {

            grid-template-columns: 1fr;

        }


        .accounts-header {

            align-items: stretch;

        }


        .accounts-header .btn {

            width: 100%;

            text-align: center;

        }


        .accounts-toolbar {

            align-items: stretch;

            flex-direction: column;

        }


        .account-search-wrapper {

            width: 100%;

        }

    }
</style>


<div class="accounts-page">


    <!-- =====================================================
         Header
    ====================================================== -->

    <div class="accounts-header">

        <div class="accounts-header-info">

            <h2>
                دليل الحسابات
            </h2>

            <p>
                جميع الحسابات والأرصدة المالية في المحطة.
            </p>

        </div>


        <div>

            <a
                href="transaction.php"
                class="btn btn-primary">
                أضافة معاملة مالية
            </a>

        </div>

    </div>


    <!-- =====================================================
         Messages
    ====================================================== -->

    <?php if (
        isset($_GET['success'])
        &&
        $_GET['success'] === 'transaction'
    ): ?>

        <div class="alert alert-success">

            تم تسجيل الحركة المالية بنجاح.

        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['success'])
        &&
        $_GET['success'] === 'deleted'
    ): ?>

        <div class="alert alert-success">

            تم حذف الحركة المالية بنجاح.

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert alert-danger">

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         Summary
    ====================================================== -->

    <div class="accounts-summary">


        <!-- Accounts -->

        <div class="account-stat">

            <div class="account-stat-icon">
                📚
            </div>

            <div class="account-stat-content">

                <span>
                    عدد الحسابات
                </span>

                <strong>
                    <?= number_format(
                        count($accounts)
                    ) ?>
                </strong>

            </div>

        </div>


        <!-- Debit -->

        <div class="account-stat">

            <div class="account-stat-icon">
                📥
            </div>

            <div class="account-stat-content">

                <span>
                    إجمالي المدين
                </span>

                <strong>
                    <?= number_format(
                        $totals['debit'],
                        2
                    ) ?>
                </strong>

            </div>

        </div>


        <!-- Credit -->

        <div class="account-stat">

            <div class="account-stat-icon">
                📤
            </div>

            <div class="account-stat-content">

                <span>
                    إجمالي الدائن
                </span>

                <strong>
                    <?= number_format(
                        $totals['credit'],
                        2
                    ) ?>
                </strong>

            </div>

        </div>


        <!-- Balance -->

        <div class="account-stat">

            <div class="account-stat-icon">
                ⚖️
            </div>

            <div class="account-stat-content">

                <span>
                    صافي الأرصدة
                </span>

                <strong>

                    <?= number_format(
                        abs($totals['balance']),
                        2
                    ) ?>

                </strong>

                <small>

                    <?php if (
                        $totals['balance'] > 0.009
                    ): ?>

                        له

                    <?php elseif (
                        $totals['balance'] < -0.009
                    ): ?>

                        عليه

                    <?php else: ?>

                        متزن

                    <?php endif; ?>

                </small>

            </div>

        </div>


    </div>


    <!-- =====================================================
         Accounts Card
    ====================================================== -->

    <!-- التعديل 1: إضافة overflow: hidden لمنع البطاقة من التمدد خارج الشاشة -->
    <div class="card" style="overflow: hidden;">


        <!-- Toolbar -->

        <div class="accounts-toolbar">

            <div class="accounts-toolbar-title">

                <h3>
                    الحسابات
                </h3>

                <p>
                    يمكنك البحث عن الحساب أو فتح كشفه أو تسجيل حركة مالية.
                </p>

            </div>


            <div class="account-search-wrapper">

                <span class="account-search-icon">
                    🔍
                </span>

                <input
                    type="text"
                    id="accountSearch"
                    class="account-search"
                    placeholder="بحث عن رقم أو اسم الحساب..."
                    autocomplete="off">

            </div>

        </div>


        <!-- Table -->

        <!-- التعديل 2: تفعيل السكرول الأفقي بشكل سلس -->
        <div class="accounts-table-wrapper" style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">

            <!-- التعديل 3: إجبار الجدول على عدم تقلص الأعمدة وتكسير النصوص -->
            <table
                class="accounts-table"
                id="accountsTable" style="min-width: 1100px; white-space: nowrap;">

                <thead>

                    <tr>

                        <th>
                            #
                        </th>

                        <th>
                            رقم الحساب
                        </th>

                        <th>
                            اسم الحساب
                        </th>

                        <th>
                            النوع
                        </th>

                        <th>
                            المدين
                        </th>

                        <th>
                            الدائن
                        </th>

                        <th>
                            الرصيد
                        </th>

                        <th>
                            الحالة
                        </th>

                        <th>
                            الإجراءات
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php if (!$accounts): ?>

                        <tr>

                            <td
                                colspan="9"
                                class="accounts-empty">

                                <div class="accounts-empty-icon">
                                    📚
                                </div>

                                <strong>
                                    لا توجد حسابات
                                </strong>

                                <span>
                                    لا توجد حسابات مالية مسجلة في النظام حتى الآن.
                                </span>

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach (
                            $accounts
                            as $index => $account
                        ): ?>


                            <?php

                            $typeText =
                                $accountTypes[$account['account_type']]
                                ??
                                $account['account_type'];

                            ?>


                            <tr
                                class="account-row"
                                data-search="<?= e(
                                                    $account['account_no']
                                                        . ' '
                                                        . $account['account_name']
                                                        . ' '
                                                        . $typeText
                                                ) ?>">


                                <!-- Number -->

                                <td>

                                    <?= $index + 1 ?>

                                </td>


                                <!-- Account Number -->

                                <td>

                                    <span class="account-number">

                                        <?= e(
                                            $account['account_no']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Account Name -->

                                <td>

                                    <div class="account-name-wrapper">

                                        <span class="account-name">

                                            <?= e(
                                                $account['account_name']
                                            ) ?>

                                        </span>


                                        <?php if (
                                            !empty($account['notes'])
                                        ): ?>

                                            <span class="account-notes">

                                                <?= e(
                                                    $account['notes']
                                                ) ?>

                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>


                                <!-- Type -->

                                <td>

                                    <span class="badge">

                                        <?= e(
                                            $typeText
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Debit -->

                                <td>

                                    <span class="amount amount-debit">

                                        <?= number_format(
                                            $account['total_debit'],
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Credit -->

                                <td>

                                    <span class="amount amount-credit">

                                        <?= number_format(
                                            $account['total_credit'],
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Balance -->

                                <td>

                                    <?php if (
                                        $account['balance'] > 0.009
                                    ): ?>

                                        <span
                                            class="
                                            badge
                                            badge-success
                                        ">

                                            له

                                            <?= number_format(
                                                $account['balance'],
                                                2
                                            ) ?>

                                        </span>


                                    <?php elseif (
                                        $account['balance'] < -0.009
                                    ): ?>

                                        <span
                                            class="
                                            badge
                                            badge-danger
                                        ">

                                            عليه

                                            <?= number_format(
                                                abs(
                                                    $account['balance']
                                                ),
                                                2
                                            ) ?>

                                        </span>


                                    <?php else: ?>

                                        <span
                                            class="
                                            badge
                                            badge-secondary
                                        ">

                                            متزن

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Status -->

                                <td>

                                    <?php if (
                                        $account['status']
                                        ===
                                        'active'
                                    ): ?>

                                        <span
                                            class="
                                            badge
                                            badge-success
                                        ">
                                            نشط
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                            badge
                                            badge-danger
                                        ">
                                            غير نشط
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Actions -->

                                <td>

                                    <div class="account-actions">


                                        <!-- Statement -->

                                        <a
                                            href="statement.php?id=<?= (int)$account['id'] ?>"
                                            class="btn btn-primary">
                                            كشف الحساب
                                        </a>


                                        <!-- Transaction -->

                                        <a
                                            href="transaction.php?account_id=<?= (int)$account['id'] ?>"
                                            class="btn btn-sm btn-primary">
                                            أضافة معاملة مالية جديدة
                                        </a>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </tbody>

            </table>


            <div
                id="accountNoResults"
                class="accounts-no-results">

                لا توجد نتائج مطابقة للبحث.

            </div>

        </div>

    </div>

</div>


<script>
    document.addEventListener(
        'DOMContentLoaded',
        function() {


            const search =
                document.getElementById(
                    'accountSearch'
                );


            const rows =
                Array.from(
                    document.querySelectorAll(
                        '#accountsTable tbody tr.account-row'
                    )
                );


            const noResults =
                document.getElementById(
                    'accountNoResults'
                );


            if (!search) {
                return;
            }


            /*
            |--------------------------------------------------------------------------
            | البحث
            |--------------------------------------------------------------------------
            */

            search.addEventListener(
                'input',
                function() {


                    const value =
                        this.value
                        .trim()
                        .toLowerCase();


                    let visibleCount = 0;


                    rows.forEach(
                        function(row) {


                            const text =
                                (
                                    row.dataset.search ||
                                    ''
                                )
                                .toLowerCase();


                            const visible = !value ||
                                text.includes(value);


                            row.style.display =
                                visible ?
                                '' :
                                'none';


                            if (visible) {

                                visibleCount++;

                            }

                        }
                    );


                    if (noResults) {

                        noResults.style.display =
                            (
                                rows.length > 0 &&
                                visibleCount === 0
                            ) ?
                            'block' :
                            'none';

                    }

                }
            );

        }
    );
</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>