<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'التقارير';

$error = '';

/*
|--------------------------------------------------------------------------
| الفترة
|--------------------------------------------------------------------------
*/

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');


/*
|--------------------------------------------------------------------------
| التحقق من التاريخ
|--------------------------------------------------------------------------
*/

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-m-d');
}


if ($from > $to) {

    $temp = $from;
    $from = $to;
    $to   = $temp;
}


/*
|--------------------------------------------------------------------------
| تاريخ النهاية
|--------------------------------------------------------------------------
*/

$toExclusive = date(
    'Y-m-d',
    strtotime($to . ' +1 day')
);


/*
|--------------------------------------------------------------------------
| الملخص
|--------------------------------------------------------------------------
*/

$summary = [

    'accounts'  => 0,

    'debit'     => 0,

    'credit'    => 0,

    'sales'     => 0,

    'expenses'  => 0

];


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
| قراءة البيانات
|--------------------------------------------------------------------------
*/

try {


    /*
    |--------------------------------------------------------------------------
    | الحسابات النشطة
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM accounts
        WHERE status = 'active'
    ");

    $summary['accounts'] =
        (int)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | المدين والدائن
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT

            COALESCE(
                SUM(debit),
                0
            ) AS debit,

            COALESCE(
                SUM(credit),
                0
            ) AS credit

        FROM account_transactions

        WHERE transaction_date >= ?

        AND transaction_date < ?
    ");

    $stmt->execute([

        $from . ' 00:00:00',

        $toExclusive . ' 00:00:00'

    ]);


    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);


    $summary['debit'] =
        (float)($row['debit'] ?? 0);


    $summary['credit'] =
        (float)($row['credit'] ?? 0);


    /*
    |--------------------------------------------------------------------------
    | المبيعات
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT

            COALESCE(
                SUM(total),
                0
            )

        FROM sales

        WHERE sale_date >= ?

        AND sale_date < ?
    ");

    $stmt->execute([

        $from . ' 00:00:00',

        $toExclusive . ' 00:00:00'

    ]);


    $summary['sales'] =
        (float)$stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | المصروفات
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT

            COALESCE(
                SUM(amount),
                0
            )

        FROM expenses

        WHERE expense_date >= ?

        AND expense_date < ?
    ");

    $stmt->execute([

        $from . ' 00:00:00',

        $toExclusive . ' 00:00:00'

    ]);


    $summary['expenses'] =
        (float)$stmt->fetchColumn();
} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| الحسابات المشتقة
|--------------------------------------------------------------------------
*/

$netMovement =
    $summary['credit']
    -
    $summary['debit'];


$profitEstimate =
    $summary['sales']
    -
    $summary['expenses'];


$balanceDifference =
    $summary['debit']
    -
    $summary['credit'];


require_once __DIR__ . '/../includes/header.php';

?>


<style>
    /*
|--------------------------------------------------------------------------
| Reports Page
|--------------------------------------------------------------------------
*/

    .reports-page {

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

    .reports-header {

        display: flex;

        justify-content: space-between;

        align-items: center;

        gap: 20px;

        flex-wrap: wrap;

    }


    .reports-header-info h2 {

        margin: 0 0 7px;

        font-size: 24px;

    }


    .reports-header-info p {

        margin: 0;

        color: #6b7280;

        font-size: 14px;

    }


    .reports-header-actions {

        display: flex;

        gap: 8px;

        flex-wrap: wrap;

    }


    /*
|--------------------------------------------------------------------------
| Date Filter
|--------------------------------------------------------------------------
*/

    .reports-filter {

        background: #fff;

        border: 1px solid #e5e7eb;

        border-radius: 16px;

        padding: 20px;

        box-shadow:
            0 2px 8px rgba(0, 0, 0, .04);

    }


    .reports-filter-header {

        display: flex;

        align-items: center;

        gap: 10px;

        margin-bottom: 18px;

    }


    .reports-filter-icon {

        width: 42px;

        height: 42px;

        border-radius: 10px;

        display: flex;

        align-items: center;

        justify-content: center;

        background: #eff6ff;

        color: #2563eb;

        font-size: 20px;

    }


    .reports-filter-title h3 {

        margin: 0 0 3px;

        font-size: 16px;

    }


    .reports-filter-title span {

        color: #9ca3af;

        font-size: 12px;

    }


    .reports-filter-form {

        display: grid;

        grid-template-columns:
            minmax(180px, 1fr) minmax(180px, 1fr) auto auto;

        gap: 14px;

        align-items: end;

    }


    .report-date-group {

        display: flex;

        flex-direction: column;

        gap: 7px;

    }


    .report-date-group label {

        font-size: 13px;

        font-weight: 600;

        color: #374151;

    }


    .report-date-group input {

        width: 100%;

        height: 44px;

        padding: 0 12px;

        border: 1px solid #d1d5db;

        border-radius: 10px;

        background: #fff;

        outline: none;

        font-family: inherit;

    }


    .report-date-group input:focus {

        border-color: #2563eb;

        box-shadow:
            0 0 0 3px rgba(37, 99, 235, .10);

    }


    .report-filter-button {

        height: 44px;

    }


    .report-reset-button {

        height: 44px;

    }


    /*
|--------------------------------------------------------------------------
| Current Period
|--------------------------------------------------------------------------
*/

    .reports-period {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 15px;

        background: #f8fafc;

        border: 1px solid #e5e7eb;

        border-radius: 12px;

        padding: 13px 16px;

    }


    .reports-period-info {

        display: flex;

        align-items: center;

        gap: 9px;

        color: #374151;

        font-size: 13px;

    }


    .reports-period-info strong {

        color: #111827;

    }


    .reports-period-icon {

        font-size: 17px;

    }


    /*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

    .report-stats {

        display: grid;

        grid-template-columns:
            repeat(5, minmax(0, 1fr));

        gap: 15px;

    }


    .report-stat {

        background: #fff;

        border: 1px solid #e5e7eb;

        border-radius: 15px;

        padding: 18px;

        display: flex;

        align-items: center;

        gap: 13px;

        box-shadow:
            0 2px 8px rgba(0, 0, 0, .04);

        min-width: 0;

    }


    .report-stat-icon {

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


    .report-stat-info {

        min-width: 0;

        display: flex;

        flex-direction: column;

        gap: 5px;

    }


    .report-stat-info span {

        color: #6b7280;

        font-size: 12px;

        white-space: nowrap;

    }


    .report-stat-info strong {

        color: #111827;

        font-size: 19px;

        white-space: nowrap;

    }


    .report-stat-info small {

        color: #9ca3af;

        font-size: 11px;

    }


    /*
|--------------------------------------------------------------------------
| Main Financial Summary
|--------------------------------------------------------------------------
*/

    .financial-summary {

        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        gap: 16px;

    }


    .financial-card {

        background: #fff;

        border: 1px solid #e5e7eb;

        border-radius: 15px;

        padding: 20px;

        box-shadow:
            0 2px 8px rgba(0, 0, 0, .04);

    }


    .financial-card-header {

        display: flex;

        align-items: center;

        justify-content: space-between;

        margin-bottom: 15px;

    }


    .financial-card-title {

        display: flex;

        align-items: center;

        gap: 9px;

    }


    .financial-card-title-icon {

        width: 36px;

        height: 36px;

        border-radius: 9px;

        display: flex;

        align-items: center;

        justify-content: center;

        background: #f3f4f6;

    }


    .financial-card-title span {

        font-weight: 700;

        color: #374151;

    }


    .financial-value {

        font-size: 25px;

        font-weight: 800;

        color: #111827;

        margin-bottom: 7px;

    }


    .financial-description {

        color: #9ca3af;

        font-size: 12px;

    }


    /*
|--------------------------------------------------------------------------
| Reports Navigation
|--------------------------------------------------------------------------
*/

    .reports-section {

        background: #fff;

        border: 1px solid #e5e7eb;

        border-radius: 16px;

        padding: 20px;

        box-shadow:
            0 2px 8px rgba(0, 0, 0, .04);

    }


    .reports-section-header {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 15px;

        margin-bottom: 18px;

    }


    .reports-section-header h3 {

        margin: 0 0 5px;

        font-size: 18px;

    }


    .reports-section-header p {

        margin: 0;

        color: #9ca3af;

        font-size: 12px;

    }


    .reports-grid {

        display: grid;

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 15px;

    }


    .report-link-card {

        display: flex;

        align-items: center;

        justify-content: space-between;

        gap: 15px;

        padding: 17px;

        border: 1px solid #e5e7eb;

        border-radius: 12px;

        text-decoration: none;

        color: inherit;

        transition:
            .15s ease;

        background: #fff;

    }


    .report-link-card:hover {

        border-color: #bfdbfe;

        background: #f8fbff;

        transform:
            translateY(-1px);

    }


    .report-link-info {

        display: flex;

        align-items: center;

        gap: 12px;

    }


    .report-link-icon {

        width: 44px;

        height: 44px;

        min-width: 44px;

        border-radius: 11px;

        display: flex;

        align-items: center;

        justify-content: center;

        background: #eff6ff;

        font-size: 20px;

    }


    .report-link-text {

        display: flex;

        flex-direction: column;

        gap: 4px;

    }


    .report-link-text strong {

        color: #111827;

        font-size: 14px;

    }


    .report-link-text span {

        color: #9ca3af;

        font-size: 11px;

    }


    .report-link-arrow {

        color: #9ca3af;

        font-size: 18px;

    }


    /*
|--------------------------------------------------------------------------
| Error
|--------------------------------------------------------------------------
*/

    .report-error {

        margin-bottom: 0;

    }


    /*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

    @media (max-width: 1200px) {

        .report-stats {

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

        }

    }


    @media (max-width: 900px) {

        .reports-filter-form {

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

        }


        .financial-summary {

            grid-template-columns:
                1fr;

        }


        .report-stats {

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

        }

    }


    @media (max-width: 600px) {

        .reports-header {

            align-items: stretch;

        }


        .reports-header-actions {

            width: 100%;

        }


        .reports-header-actions .btn {

            flex: 1;

            text-align: center;

        }


        .reports-filter-form {

            grid-template-columns: 1fr;

        }


        .report-filter-button,
        .report-reset-button {

            width: 100%;

        }


        .report-stats {

            grid-template-columns: 1fr;

        }


        .reports-period {

            align-items: flex-start;

            flex-direction: column;

        }


        .reports-grid {

            grid-template-columns: 1fr;

        }

    }
</style>


<div class="reports-page">


    <!-- =====================================================
         Header
    ====================================================== -->

    <div class="reports-header">

        <div class="reports-header-info">

            <h2>
                التقارير المحاسبية
            </h2>

            <p>
                نظرة شاملة على الحركة المالية والمبيعات والمصروفات خلال الفترة المحددة.
            </p>

        </div>


        <div class="reports-header-actions">

            <a
                href="../accounts/index.php"
                class="btn">
                📚 دليل الحسابات
            </a>

            <a
                href="sales.php?from=<?= e($from) ?>&to=<?= e($to) ?>"
                class="btn btn-primary">
                📊 تقرير المبيعات
            </a>

        </div>

    </div>


    <!-- =====================================================
         Error
    ====================================================== -->

    <?php if ($error): ?>

        <div class="alert alert-danger report-error">

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         Date Filter
    ====================================================== -->

    <div class="reports-filter">

        <div class="reports-filter-header">

            <div class="reports-filter-icon">
                📅
            </div>

            <div class="reports-filter-title">

                <h3>
                    تحديد فترة التقرير
                </h3>

                <span>
                    اختر تاريخ البداية والنهاية لعرض البيانات.
                </span>

            </div>

        </div>


        <form
            method="get"
            class="reports-filter-form">

            <div class="report-date-group">

                <label>
                    من تاريخ
                </label>

                <input
                    type="date"
                    name="from"
                    value="<?= e($from) ?>"
                    required>

            </div>


            <div class="report-date-group">

                <label>
                    إلى تاريخ
                </label>

                <input
                    type="date"
                    name="to"
                    value="<?= e($to) ?>"
                    required>

            </div>


            <button
                type="submit"
                class="btn btn-primary report-filter-button">
                🔎 تطبيق
            </button>


            <a
                href="index.php"
                class="btn report-reset-button">
                ↻ إعادة ضبط
            </a>

        </form>

    </div>


    <!-- =====================================================
         Period
    ====================================================== -->

    <div class="reports-period">

        <div class="reports-period-info">

            <span class="reports-period-icon">
                🗓️
            </span>

            <span>
                الفترة الحالية:
            </span>

            <strong>
                <?= e($from) ?>
            </strong>

            <span>
                إلى
            </span>

            <strong>
                <?= e($to) ?>
            </strong>

        </div>


        <span class="badge">

            <?= number_format(
                $summary['accounts']
            ) ?>

            حساب نشط

        </span>

    </div>


    <!-- =====================================================
         Statistics
    ====================================================== -->

    <div class="report-stats">


        <!-- Accounts -->

        <div class="report-stat">

            <div class="report-stat-icon">
                📚
            </div>

            <div class="report-stat-info">

                <span>
                    الحسابات النشطة
                </span>

                <strong>
                    <?= number_format(
                        $summary['accounts']
                    ) ?>
                </strong>

                <small>
                    حساب
                </small>

            </div>

        </div>


        <!-- Sales -->

        <div class="report-stat">

            <div class="report-stat-icon">
                🧾
            </div>

            <div class="report-stat-info">

                <span>
                    إجمالي المبيعات
                </span>

                <strong>
                    <?= number_format(
                        $summary['sales'],
                        2
                    ) ?>
                </strong>

                <small>
                    خلال الفترة
                </small>

            </div>

        </div>


        <!-- Expenses -->

        <div class="report-stat">

            <div class="report-stat-icon">
                💸
            </div>

            <div class="report-stat-info">

                <span>
                    إجمالي المصروفات
                </span>

                <strong>
                    <?= number_format(
                        $summary['expenses'],
                        2
                    ) ?>
                </strong>

                <small>
                    خلال الفترة
                </small>

            </div>

        </div>


        <!-- Debit -->

        <div class="report-stat">

            <div class="report-stat-icon">
                📥
            </div>

            <div class="report-stat-info">

                <span>
                    إجمالي المدين
                </span>

                <strong>
                    <?= number_format(
                        $summary['debit'],
                        2
                    ) ?>
                </strong>

                <small>
                    الحركات المحاسبية
                </small>

            </div>

        </div>


        <!-- Credit -->

        <div class="report-stat">

            <div class="report-stat-icon">
                📤
            </div>

            <div class="report-stat-info">

                <span>
                    إجمالي الدائن
                </span>

                <strong>
                    <?= number_format(
                        $summary['credit'],
                        2
                    ) ?>
                </strong>

                <small>
                    الحركات المحاسبية
                </small>

            </div>

        </div>


    </div>


    <!-- =====================================================
         Financial Summary
    ====================================================== -->

    <div class="financial-summary">


        <!-- Net Movement -->

        <div class="financial-card">

            <div class="financial-card-header">

                <div class="financial-card-title">

                    <div class="financial-card-title-icon">
                        ⚖️
                    </div>

                    <span>
                        صافي الحركة
                    </span>

                </div>

            </div>


            <div class="financial-value">

                <?= number_format(
                    abs($netMovement),
                    2
                ) ?>

            </div>


            <div class="financial-description">

                <?php if ($netMovement > 0.009): ?>

                    صافي دائن خلال الفترة.

                <?php elseif ($netMovement < -0.009): ?>

                    صافي مدين خلال الفترة.

                <?php else: ?>

                    الحركة متزنة.

                <?php endif; ?>

            </div>

        </div>


        <!-- Estimated Profit -->

        <div class="financial-card">

            <div class="financial-card-header">

                <div class="financial-card-title">

                    <div class="financial-card-title-icon">
                        📈
                    </div>

                    <span>
                        صافي المبيعات بعد المصروفات
                    </span>

                </div>

            </div>


            <div class="financial-value">

                <?= number_format(
                    $profitEstimate,
                    2
                ) ?>

            </div>


            <div class="financial-description">

                المبيعات ناقص المصروفات خلال الفترة المحددة.

            </div>

        </div>


        <!-- Difference -->

        <div class="financial-card">

            <div class="financial-card-header">

                <div class="financial-card-title">

                    <div class="financial-card-title-icon">
                        🧮
                    </div>

                    <span>
                        فرق المدين والدائن
                    </span>

                </div>

            </div>


            <div class="financial-value">

                <?= number_format(
                    abs($balanceDifference),
                    2
                ) ?>

            </div>


            <div class="financial-description">

                <?php if (
                    abs($balanceDifference) < 0.01
                ): ?>

                    المدين والدائن متساويان.

                <?php elseif (
                    $balanceDifference > 0
                ): ?>

                    المدين أكبر من الدائن.

                <?php else: ?>

                    الدائن أكبر من المدين.

                <?php endif; ?>

            </div>

        </div>


    </div>


    <!-- =====================================================
         Reports
    ====================================================== -->

    <div class="reports-section">


        <div class="reports-section-header">

            <div>

                <h3>
                    التقارير المتاحة
                </h3>

                <p>
                    اختر التقرير الذي تريد استعراض تفاصيله.
                </p>

            </div>

        </div>


        <div class="reports-grid">


            <!-- Sales -->

            <a
                href="sales.php?from=<?= e($from) ?>&to=<?= e($to) ?>"
                class="report-link-card">

                <div class="report-link-info">

                    <div class="report-link-icon">
                        🧾
                    </div>

                    <div class="report-link-text">

                        <strong>
                            تقرير المبيعات
                        </strong>

                        <span>
                            تفاصيل المبيعات خلال الفترة المحددة.
                        </span>

                    </div>

                </div>


                <div class="report-link-arrow">
                    ←
                </div>

            </a>


            <!-- Accounts -->

            <a
                href="accounts.php?from=<?= e($from) ?>&to=<?= e($to) ?>"
                class="report-link-card">

                <div class="report-link-info">

                    <div class="report-link-icon">
                        📚
                    </div>

                    <div class="report-link-text">

                        <strong>
                            تقرير الحسابات
                        </strong>

                        <span>
                            حركة وأرصدة الحسابات المحاسبية.
                        </span>

                    </div>

                </div>


                <div class="report-link-arrow">
                    ←
                </div>

            </a>


        </div>

    </div>


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
