<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'تقرير الحسابات';

$error = '';

$accounts = [];

$totals = [
    'debit'    => 0,
    'credit'   => 0,
    'balance'  => 0,
    'count'    => 0,
    'positive' => 0,
    'negative' => 0,
    'zero'     => 0
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
| نوع الحساب المحدد
|--------------------------------------------------------------------------
*/

$type = trim(
    $_GET['type'] ?? ''
);


/*
|--------------------------------------------------------------------------
| التحقق من نوع الحساب
|--------------------------------------------------------------------------
*/

if (
    $type !== ''
    &&
    !array_key_exists(
        $type,
        $accountTypes
    )
) {

    $type = '';
}


/*
|--------------------------------------------------------------------------
| جلب الحسابات
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            id,
            account_no,
            account_name,
            account_type,
            opening_balance,
            status,
            notes,
            created_at
        FROM accounts
        WHERE status = 'active'
    ";

    $params = [];


    if ($type !== '') {

        $sql .= "
            AND account_type = ?
        ";

        $params[] = $type;
    }


    $sql .= "
        ORDER BY
            CASE account_type

                WHEN 'cash' THEN 1
                WHEN 'bank' THEN 2
                WHEN 'owner' THEN 3
                WHEN 'employee' THEN 4
                WHEN 'customer' THEN 5
                WHEN 'supplier' THEN 6
                WHEN 'expense' THEN 7
                ELSE 8

            END,

            account_name ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $accounts =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | حساب أرصدة الحسابات
    |--------------------------------------------------------------------------
    */

    foreach ($accounts as &$account) {

        $balanceData =
            accountingGetBalance(
                $pdo,
                (int)$account['id']
            );


        $debit =
            round(
                (float)($balanceData['debit'] ?? 0),
                2
            );


        $credit =
            round(
                (float)($balanceData['credit'] ?? 0),
                2
            );


        $balance =
            round(
                (float)($balanceData['balance'] ?? 0),
                2
            );


        $account['debit'] =
            $debit;

        $account['credit'] =
            $credit;

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
                'report-badge-credit';

            $account['direction_icon'] =
                '↑';

            $account['direction_amount'] =
                $balance;

            $totals['positive']++;
        } elseif ($balance < -0.009) {

            $account['direction'] = 'عليه';

            $account['direction_class'] =
                'report-badge-debit';

            $account['direction_icon'] =
                '↓';

            $account['direction_amount'] =
                abs($balance);

            $totals['negative']++;
        } else {

            $account['direction'] = 'متزن';

            $account['direction_class'] =
                'report-badge-zero';

            $account['direction_icon'] =
                '=';

            $account['direction_amount'] =
                0;

            $totals['zero']++;
        }


        /*
        |--------------------------------------------------------------------------
        | الإجماليات
        |--------------------------------------------------------------------------
        */

        $totals['debit'] +=
            $debit;

        $totals['credit'] +=
            $credit;

        $totals['balance'] +=
            $balance;
    }

    unset($account);


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


    $totals['count'] =
        count($accounts);
} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


require_once __DIR__ . '/../includes/header.php';

?>


<style>
    /*
|--------------------------------------------------------------------------
| Reports Accounts
|--------------------------------------------------------------------------
*/

    .accounts-report-page {
        direction: rtl;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }


    /*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

    .accounts-report-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
    }

    .accounts-report-title h2 {
        margin: 0 0 7px;
        font-size: 24px;
    }

    .accounts-report-title p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
    }


    /*
|--------------------------------------------------------------------------
| Header Actions
|--------------------------------------------------------------------------
*/

    .accounts-report-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }


    /*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

    .accounts-report-stats {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));

        gap: 16px;
    }


    .accounts-report-stat {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 15px;
        padding: 18px;

        display: flex;
        align-items: center;
        gap: 14px;

        box-shadow:
            0 2px 8px rgba(0, 0, 0, .04);
    }


    .accounts-report-stat-icon {
        width: 48px;
        height: 48px;
        min-width: 48px;

        border-radius: 12px;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 21px;

        background: #f3f4f6;
    }


    .accounts-report-stat-content {
        min-width: 0;

        display: flex;
        flex-direction: column;
        gap: 5px;
    }


    .accounts-report-stat-content span {
        color: #6b7280;
        font-size: 12px;
    }


    .accounts-report-stat-content strong {
        color: #111827;
        font-size: 20px;
    }


    /*
|--------------------------------------------------------------------------
| Filter
|--------------------------------------------------------------------------
*/

    .accounts-report-filter {
        background: #ffffff;

        border: 1px solid #e5e7eb;
        border-radius: 15px;

        padding: 18px;

        box-shadow:
            0 2px 8px rgba(0, 0, 0, .04);
    }


    .accounts-report-filter-form {
        display: grid;

        grid-template-columns:
            1fr 280px auto;

        align-items: end;

        gap: 14px;
    }


    .accounts-report-field {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }


    .accounts-report-field label {
        color: #374151;
        font-size: 13px;
        font-weight: 600;
    }


    .accounts-report-field input,
    .accounts-report-field select {
        width: 100%;
        height: 44px;

        padding: 0 13px;

        border:
            1px solid #d1d5db;

        border-radius: 10px;

        background: #ffffff;

        font-family: inherit;

        outline: none;

        transition:
            border-color .15s ease,
            box-shadow .15s ease;
    }


    .accounts-report-field input:focus,
    .accounts-report-field select:focus {

        border-color: #2563eb;

        box-shadow:
            0 0 0 3px rgba(37, 99, 235, .10);

    }


    /*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

    .accounts-report-search {
        position: relative;
    }


    .accounts-report-search input {
        padding-right: 42px;
    }


    .accounts-report-search-icon {
        position: absolute;

        right: 13px;
        top: 50%;

        transform:
            translateY(-50%);

        color: #6b7280;

        pointer-events: none;
    }


    /*
|--------------------------------------------------------------------------
| Table Card
|--------------------------------------------------------------------------
*/

    .accounts-report-card {
        background: #ffffff;

        border:
            1px solid #e5e7eb;

        border-radius: 15px;

        overflow: hidden;

        box-shadow:
            0 2px 8px rgba(0, 0, 0, .04);
    }


    /*
|--------------------------------------------------------------------------
| Table Header
|--------------------------------------------------------------------------
*/

    .accounts-report-card-header {
        padding: 18px 20px;

        border-bottom:
            1px solid #e5e7eb;

        display: flex;
        justify-content: space-between;
        align-items: center;

        gap: 15px;
    }


    .accounts-report-card-header h3 {
        margin: 0;

        color: #111827;

        font-size: 17px;
    }


    .accounts-report-count {
        color: #6b7280;

        font-size: 13px;
    }


    /*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

    .accounts-report-table-wrapper {
        overflow-x: auto;
    }


    .accounts-report-table {
        width: 100%;

        min-width: 1100px;

        border-collapse: collapse;
    }


    .accounts-report-table th {

        padding: 14px 16px;

        background: #f9fafb;

        color: #4b5563;

        font-size: 12px;

        font-weight: 700;

        text-align: right;

        white-space: nowrap;

        border-bottom:
            1px solid #e5e7eb;
    }


    .accounts-report-table td {

        padding: 15px 16px;

        color: #374151;

        border-bottom:
            1px solid #f1f5f9;

        vertical-align: middle;
    }


    .accounts-report-table tbody tr {

        transition:
            background .15s ease;
    }


    .accounts-report-table tbody tr:hover {

        background: #f8fafc;
    }


    .accounts-report-table tbody tr:last-child td {

        border-bottom: none;

    }


    /*
|--------------------------------------------------------------------------
| Account Number
|--------------------------------------------------------------------------
*/

    .report-account-number {

        display: inline-flex;

        padding: 5px 9px;

        background: #f3f4f6;

        border-radius: 7px;

        font-family: monospace;

        direction: ltr;

        color: #374151;

        font-size: 12px;

    }


    /*
|--------------------------------------------------------------------------
| Account Name
|--------------------------------------------------------------------------
*/

    .report-account-name {

        display: flex;

        flex-direction: column;

        gap: 4px;
    }


    .report-account-name strong {

        color: #111827;

        font-size: 14px;

    }


    .report-account-name span {

        color: #9ca3af;

        font-size: 11px;

    }


    /*
|--------------------------------------------------------------------------
| Type Badge
|--------------------------------------------------------------------------
*/

    .report-type-badge {

        display: inline-flex;

        align-items: center;

        padding: 5px 9px;

        border-radius: 20px;

        background: #f3f4f6;

        color: #4b5563;

        font-size: 11px;

        font-weight: 600;

    }


    /*
|--------------------------------------------------------------------------
| Money
|--------------------------------------------------------------------------
*/

    .report-money {

        font-variant-numeric:
            tabular-nums;

        white-space: nowrap;

    }


    .report-money-debit {

        color: #b91c1c;

    }


    .report-money-credit {

        color: #047857;

    }


    .report-money-balance {

        font-weight: 700;

        color: #111827;

    }


    /*
|--------------------------------------------------------------------------
| Direction Badge
|--------------------------------------------------------------------------
*/

    .report-direction {

        display: inline-flex;

        align-items: center;

        gap: 5px;

        padding: 5px 9px;

        border-radius: 20px;

        font-size: 11px;

        font-weight: 700;

    }


    .report-badge-credit {

        background: #ecfdf5;

        color: #047857;

    }


    .report-badge-debit {

        background: #fef2f2;

        color: #b91c1c;

    }


    .report-badge-zero {

        background: #f3f4f6;

        color: #6b7280;

    }


    /*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

    .report-status {

        display: inline-flex;

        align-items: center;

        gap: 5px;

        padding: 5px 9px;

        border-radius: 20px;

        background: #ecfdf5;

        color: #047857;

        font-size: 11px;

        font-weight: 600;

    }


    .report-status-dot {

        width: 6px;
        height: 6px;

        border-radius: 50%;

        background: currentColor;

    }


    /*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

    .report-actions {

        display: flex;

        align-items: center;

        gap: 6px;

        flex-wrap: wrap;

        white-space: nowrap;

    }


    .report-action {

        display: inline-flex;

        align-items: center;

        justify-content: center;

        min-height: 34px;

        padding: 0 10px;

        border-radius: 8px;

        text-decoration: none;

        font-size: 11px;

        font-weight: 600;

        border: 1px solid transparent;

        transition: .15s ease;

    }


    .report-action-statement {

        color: #374151;

        background: #f3f4f6;

        border-color: #e5e7eb;

    }


    .report-action-statement:hover {

        background: #e5e7eb;

    }


    .report-action-transaction {

        color: #2563eb;

        background: #eff6ff;

        border-color: #dbeafe;

    }


    .report-action-transaction:hover {

        background: #dbeafe;

    }


    /*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/

    .accounts-report-table tfoot td {

        padding: 15px 16px;

        background: #f9fafb;

        border-top:
            2px solid #e5e7eb;

        font-weight: 700;

    }


    .report-total-debit {

        color: #b91c1c;

    }


    .report-total-credit {

        color: #047857;

    }


    .report-total-balance {

        color: #111827;

    }


    /*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

    .report-empty {

        text-align: center;

        padding: 55px 20px !important;

    }


    .report-empty-icon {

        font-size: 38px;

        margin-bottom: 10px;

    }


    .report-empty strong {

        display: block;

        margin-bottom: 6px;

        color: #374151;

    }


    .report-empty span {

        color: #9ca3af;

        font-size: 13px;

    }


    /*
|--------------------------------------------------------------------------
| No Search Results
|--------------------------------------------------------------------------
*/

    .report-no-results {

        display: none;

        padding: 40px 20px;

        text-align: center;

        color: #6b7280;

    }


    /*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

    @media (max-width: 1050px) {

        .accounts-report-stats {

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

        }


        .accounts-report-filter-form {

            grid-template-columns:
                1fr 1fr;

        }


        .accounts-report-filter-form .report-filter-button {

            grid-column:
                span 2;

        }

    }


    @media (max-width: 650px) {

        .accounts-report-stats {

            grid-template-columns: 1fr;

        }


        .accounts-report-header {

            align-items: stretch;

        }


        .accounts-report-actions {

            width: 100%;

        }


        .accounts-report-actions .btn {

            flex: 1;

            text-align: center;

        }


        .accounts-report-filter-form {

            grid-template-columns: 1fr;

        }


        .accounts-report-filter-form .report-filter-button {

            grid-column: auto;

        }


        .report-filter-button {

            width: 100%;

        }

    }
</style>


<div class="accounts-report-page">


    <!-- =====================================================
         Header
    ====================================================== -->

    <div class="accounts-report-header">

        <div class="accounts-report-title">

            <h2>
                تقرير الحسابات
            </h2>

            <p>
                متابعة أرصدة الحسابات والحركات المالية
                في النظام المحاسبي.
            </p>

        </div>


        <div class="accounts-report-actions">

            <a
                href="../accounts/index.php"
                class="btn">
                📚 دليل الحسابات
            </a>


            <a
                href="../accounts/transaction.php"
                class="btn btn-primary">
                + حركة مالية
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

    <?php endif; ?>


    <!-- =====================================================
         Statistics
    ====================================================== -->

    <div class="accounts-report-stats">


        <div class="accounts-report-stat">

            <div class="accounts-report-stat-icon">
                📚
            </div>

            <div class="accounts-report-stat-content">

                <span>
                    الحسابات النشطة
                </span>

                <strong>
                    <?= number_format(
                        $totals['count']
                    ) ?>
                </strong>

            </div>

        </div>


        <div class="accounts-report-stat">

            <div class="accounts-report-stat-icon">
                📥
            </div>

            <div class="accounts-report-stat-content">

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


        <div class="accounts-report-stat">

            <div class="accounts-report-stat-icon">
                📤
            </div>

            <div class="accounts-report-stat-content">

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


        <div class="accounts-report-stat">

            <div class="accounts-report-stat-icon">
                ⚖️
            </div>

            <div class="accounts-report-stat-content">

                <span>
                    صافي الأرصدة
                </span>

                <strong>

                    <?= number_format(
                        abs($totals['balance']),
                        2
                    ) ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- =====================================================
         Filter
    ====================================================== -->

    <div class="accounts-report-filter">

        <form
            method="get"
            class="accounts-report-filter-form">


            <div class="
                accounts-report-field
                accounts-report-search
            ">

                <label>
                    البحث في الحسابات
                </label>

                <span
                    class="accounts-report-search-icon">
                    🔍
                </span>

                <input
                    type="text"
                    id="accountSearch"
                    placeholder="رقم الحساب أو الاسم..."
                    autocomplete="off">

            </div>


            <div class="accounts-report-field">

                <label>
                    نوع الحساب
                </label>

                <select
                    name="type">

                    <option value="">
                        جميع الحسابات
                    </option>


                    <?php foreach (
                        $accountTypes
                        as $key => $label
                    ): ?>

                        <option
                            value="<?= e($key) ?>"
                            <?= $type === $key
                                ? 'selected'
                                : '' ?>>
                            <?= e($label) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="report-filter-button">

                <button
                    type="submit"
                    class="btn btn-primary">
                    🔎 تطبيق التصفية
                </button>

            </div>


        </form>

    </div>


    <!-- =====================================================
         Accounts Table
    ====================================================== -->

    <div class="accounts-report-card">


        <div class="accounts-report-card-header">

            <div>

                <h3>
                    كشف الحسابات
                </h3>

            </div>


            <span
                class="accounts-report-count"
                id="accountsCount">

                <?= number_format(
                    count($accounts)
                ) ?>

                حساب

            </span>

        </div>


        <div class="accounts-report-table-wrapper">

            <table
                class="accounts-report-table"
                id="accountsTable">

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


                    <?php if (
                        empty($accounts)
                    ): ?>

                        <tr>

                            <td
                                colspan="9"
                                class="report-empty">

                                <div
                                    class="report-empty-icon">
                                    📚
                                </div>

                                <strong>
                                    لا توجد حسابات
                                </strong>

                                <span>
                                    لا توجد حسابات نشطة
                                    مطابقة للفلتر الحالي.
                                </span>

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach (
                            $accounts
                            as $index => $account
                        ): ?>


                            <tr
                                class="account-report-row"
                                data-search="<?= e(
                                                    $account['account_no']
                                                        . ' '
                                                        . $account['account_name']
                                                        . ' '
                                                        . (
                                                            $accountTypes[$account['account_type']]
                                                            ??
                                                            $account['account_type']
                                                        )
                                                ) ?>">


                                <!-- Number -->

                                <td>
                                    <?= $index + 1 ?>
                                </td>


                                <!-- Account Number -->

                                <td>

                                    <span
                                        class="report-account-number">
                                        <?= e(
                                            $account['account_no']
                                        ) ?>
                                    </span>

                                </td>


                                <!-- Account Name -->

                                <td>

                                    <div
                                        class="report-account-name">

                                        <strong>

                                            <?= e(
                                                $account['account_name']
                                            ) ?>

                                        </strong>


                                        <?php if (
                                            !empty($account['notes'])
                                        ): ?>

                                            <span>

                                                <?= e(
                                                    $account['notes']
                                                ) ?>

                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>


                                <!-- Type -->

                                <td>

                                    <span
                                        class="report-type-badge">

                                        <?= e(
                                            $accountTypes[$account['account_type']]
                                                ??
                                                $account['account_type']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Debit -->

                                <td>

                                    <span
                                        class="
                                            report-money
                                            report-money-debit
                                        ">

                                        <?= number_format(
                                            $account['debit'],
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Credit -->

                                <td>

                                    <span
                                        class="
                                            report-money
                                            report-money-credit
                                        ">

                                        <?= number_format(
                                            $account['credit'],
                                            2
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Balance -->

                                <td>

                                    <div
                                        style="
                                            display:flex;
                                            flex-direction:column;
                                            gap:5px;
                                        ">

                                        <span
                                            class="
                                                report-money
                                                report-money-balance
                                            ">

                                            <?= number_format(
                                                abs(
                                                    $account['balance']
                                                ),
                                                2
                                            ) ?>

                                        </span>


                                        <span
                                            class="
                                                report-direction
                                                <?= e(
                                                    $account['direction_class']
                                                ) ?>">

                                            <span>
                                                <?= e(
                                                    $account['direction_icon']
                                                ) ?>
                                            </span>

                                            <?= e(
                                                $account['direction']
                                            ) ?>

                                        </span>

                                    </div>

                                </td>


                                <!-- Status -->

                                <td>

                                    <span
                                        class="report-status">

                                        <span
                                            class="report-status-dot"></span>

                                        نشط

                                    </span>

                                </td>


                                <!-- Actions -->

                                <td>

                                    <div
                                        class="report-actions">


                                        <a
                                            href="../accounts/statement.php?id=<?= (int)$account['id'] ?>"
                                            class="
                                                report-action
                                                report-action-statement
                                            ">
                                            📋 كشف
                                        </a>


                                        <a
                                            href="../accounts/transaction.php?account_id=<?= (int)$account['id'] ?>"
                                            class="
                                                report-action
                                                report-action-transaction
                                            ">
                                            + حركة
                                        </a>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </tbody>


                <?php if (
                    !empty($accounts)
                ): ?>

                    <tfoot>

                        <tr>

                            <td
                                colspan="4">
                                الإجمالي
                            </td>


                            <td>

                                <span
                                    class="
                                        report-money
                                        report-total-debit
                                    ">

                                    <?= number_format(
                                        $totals['debit'],
                                        2
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="
                                        report-money
                                        report-total-credit
                                    ">

                                    <?= number_format(
                                        $totals['credit'],
                                        2
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <span
                                    class="
                                        report-money
                                        report-total-balance
                                    ">

                                    <?= number_format(
                                        abs(
                                            $totals['balance']
                                        ),
                                        2
                                    ) ?>

                                </span>

                            </td>


                            <td colspan="2">

                                <span
                                    style="
                                        color:#6b7280;
                                        font-size:12px;
                                    ">

                                    متزن:
                                    <?= number_format(
                                        $totals['zero']
                                    ) ?>

                                    |
                                    له:
                                    <?= number_format(
                                        $totals['positive']
                                    ) ?>

                                    |
                                    عليه:
                                    <?= number_format(
                                        $totals['negative']
                                    ) ?>

                                </span>

                            </td>

                        </tr>

                    </tfoot>

                <?php endif; ?>


            </table>


            <div
                id="accountNoResults"
                class="report-no-results">

                🔍 لا توجد نتائج مطابقة للبحث.

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
                        '.account-report-row'
                    )
                );


            const count =
                document.getElementById(
                    'accountsCount'
                );


            const noResults =
                document.getElementById(
                    'accountNoResults'
                );


            if (!search) {
                return;
            }


            function filterAccounts() {

                const value =
                    search.value
                    .trim()
                    .toLowerCase();


                let visible =
                    0;


                rows.forEach(
                    function(row) {

                        const text =
                            (
                                row.dataset.search ||
                                ''
                            )
                            .toLowerCase();


                        const matches = !value ||
                            text.includes(value);


                        row.style.display =
                            matches ?
                            '' :
                            'none';


                        if (matches) {

                            visible++;

                        }

                    }
                );


                if (count) {

                    count.textContent =
                        visible + ' حساب';

                }


                if (noResults) {

                    noResults.style.display =
                        (
                            rows.length > 0 &&
                            visible === 0
                        ) ?
                        'block' :
                        'none';

                }

            }


            search.addEventListener(
                'input',
                filterAccounts
            );

        }
    );
</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
