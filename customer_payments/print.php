<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$payment = null;
$error = '';

try {

    $paymentId = (int)($_GET['id'] ?? 0);

    if ($paymentId <= 0) {
        throw new Exception(
            'رقم سند التحصيل غير صحيح.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | بيانات السند
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

    $stmt->execute([
        $paymentId
    ]);

    $payment =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$payment) {

        throw new Exception(
            'سند التحصيل غير موجود.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | حساب العميل
    |--------------------------------------------------------------------------
    */

    $customerAccount =
        accountingEnsureCustomerAccount(
            $pdo,
            (int)$payment['customer_id'],
            false
        );


    /*
    |--------------------------------------------------------------------------
    | رصيد العميل
    |--------------------------------------------------------------------------
    */

    $balanceData =
        accountingGetBalance(
            $pdo,
            (int)$customerAccount['id']
        );


    /*
    |--------------------------------------------------------------------------
    | المديونية الحالية
    |--------------------------------------------------------------------------
    */

    $balance =
        (float)$balanceData['balance'];


    $currentDebt =
        max(
            0,
            round(
                -$balance,
                2
            )
        );


    /*
    |--------------------------------------------------------------------------
    | إجمالي المدفوع
    |--------------------------------------------------------------------------
    */

    $paidStmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(amount),
                0
            )
        FROM customer_payments
        WHERE customer_id = ?
    ");

    $paidStmt->execute([
        $payment['customer_id']
    ]);

    $totalPaid =
        round(
            (float)$paidStmt->fetchColumn(),
            2
        );


} catch (Throwable $e) {

    $error =
        $e->getMessage();

}

?>
<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        سند تحصيل
        <?= $payment
            ? htmlspecialchars(
                $payment['payment_no'],
                ENT_QUOTES,
                'UTF-8'
            )
            : ''
        ?>
    </title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            background: #fff;
            color: #111827;
            font-family:
                Tahoma,
                Arial,
                sans-serif;
            direction: rtl;
        }

        .print-container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }

        .print-header {
            text-align: center;
            padding-bottom: 18px;
            margin-bottom: 20px;
            border-bottom: 2px solid #111827;
        }

        .print-header h1 {
            margin: 0 0 8px;
            font-size: 25px;
        }

        .print-header p {
            margin: 0;
            color: #4b5563;
            font-size: 14px;
        }

        .info-table,
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .info-table td,
        .payment-table th,
        .payment-table td {
            border: 1px solid #d1d5db;
            padding: 10px;
        }

        .info-table td.label {
            width: 18%;
            background: #f3f4f6;
            font-weight: bold;
        }

        .info-table td.value {
            width: 32%;
        }

        .payment-table th {
            background: #f3f4f6;
            font-weight: bold;
            text-align: center;
        }

        .payment-table td {
            text-align: center;
        }

        .amount {
            font-size: 20px;
            font-weight: bold;
        }

        .summary {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0;
        }

        .summary-box {
            border: 1px solid #d1d5db;
            padding: 14px;
            text-align: center;
        }

        .summary-box .title {
            display: block;
            color: #4b5563;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .summary-box .value {
            display: block;
            font-size: 19px;
            font-weight: bold;
        }

        .notes {
            border: 1px solid #d1d5db;
            padding: 12px;
            margin-top: 15px;
            min-height: 60px;
        }

        .notes-title {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-top: 70px;
            text-align: center;
        }

        .signature {
            padding-top: 10px;
            border-top: 1px solid #111827;
        }

        .print-actions {
            text-align: center;
            margin-bottom: 20px;
        }

        .print-actions button {
            border: 0;
            background: #2563eb;
            color: white;
            padding: 10px 22px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .error {
            max-width: 700px;
            margin: 40px auto;
            padding: 15px;
            border: 1px solid #ef4444;
            background: #fee2e2;
            color: #991b1b;
            text-align: center;
        }

        @media print {

            body {
                padding: 0;
                margin: 0;
            }

            .print-container {
                max-width: none;
                width: 100%;
            }

            .print-actions {
                display: none !important;
            }

            @page {
                size: A4;
                margin: 12mm;
            }

            .payment-table {
                page-break-inside: avoid;
            }

            .summary {
                page-break-inside: avoid;
            }

            .signatures {
                page-break-inside: avoid;
            }

        }

        @media screen and (max-width: 700px) {

            body {
                padding: 10px;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .info-table {
                font-size: 13px;
            }

            .signatures {
                grid-template-columns: 1fr;
                gap: 60px;
            }

        }

    </style>

</head>


<body>


<?php if ($error): ?>

    <div class="error">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php elseif ($payment): ?>


<div class="print-container">


    <!-- =====================================================
         زر الطباعة - لا يظهر في الورق
    ====================================================== -->

    <div class="print-actions">

        <button
            type="button"
            onclick="window.print()"
        >
            🖨️ طباعة السند
        </button>

    </div>


    <!-- =====================================================
         رأس السند
    ====================================================== -->

    <div class="print-header">

        <h1>
            سند تحصيل
        </h1>

        <p>
            إيصال سداد مالي
        </p>

    </div>


    <!-- =====================================================
         بيانات العميل والسند
    ====================================================== -->

    <table class="info-table">

        <tbody>

            <tr>

                <td class="label">
                    رقم السند
                </td>

                <td class="value">

                    <?= htmlspecialchars(
                        $payment['payment_no'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <td class="label">
                    التاريخ
                </td>

                <td class="value">

                    <?= htmlspecialchars(
                        $payment['payment_date'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

            </tr>


            <tr>

                <td class="label">
                    رقم العميل
                </td>

                <td class="value">

                    <?= htmlspecialchars(
                        $payment['customer_no'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <td class="label">
                    العميل
                </td>

                <td class="value">

                    <?= htmlspecialchars(
                        $payment['customer_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

            </tr>


            <tr>

                <td class="label">
                    الهاتف
                </td>

                <td class="value">

                    <?= htmlspecialchars(
                        $payment['phone'] ?: '-',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>

                <td class="label">
                    طريقة الدفع
                </td>

                <td class="value">

                    <?php

                    echo match (
                        $payment['payment_method']
                    ) {

                        'cash' => 'نقدي',

                        'card' => 'بطاقة',

                        default => 'أخرى'

                    };

                    ?>

                </td>

            </tr>

        </tbody>

    </table>


    <!-- =====================================================
         جدول مبلغ التحصيل
    ====================================================== -->

    <table class="payment-table">

        <thead>

            <tr>

                <th>
                    البيان
                </th>

                <th>
                    المبلغ
                </th>

            </tr>

        </thead>

        <tbody>

            <tr>

                <td>
                    مبلغ التحصيل
                </td>

                <td class="amount">

                    <?= number_format(
                        (float)$payment['amount'],
                        2
                    ) ?>

                </td>

            </tr>

        </tbody>

    </table>


    <!-- =====================================================
         ملخص الحساب
         
         لا يوجد إجمالي مبيعات آجلة
    ====================================================== -->

    <div class="summary">

        <div class="summary-box">

            <span class="title">
                المديونية الحالية
            </span>

            <span class="value">

                <?= number_format(
                    $currentDebt,
                    2
                ) ?>

            </span>

        </div>


        <div class="summary-box">

            <span class="title">
                إجمالي المدفوع
            </span>

            <span class="value">

                <?= number_format(
                    $totalPaid,
                    2
                ) ?>

            </span>

        </div>


        <div class="summary-box">

            <span class="title">
                المتبقي
            </span>

            <span class="value">

                <?= number_format(
                    $currentDebt,
                    2
                ) ?>

            </span>

        </div>

    </div>


    <!-- =====================================================
         الملاحظات
    ====================================================== -->

    <?php if (
        trim(
            (string)$payment['notes']
        ) !== ''
    ): ?>

        <div class="notes">

            <div class="notes-title">
                ملاحظات
            </div>

            <?= nl2br(
                htmlspecialchars(
                    $payment['notes'],
                    ENT_QUOTES,
                    'UTF-8'
                )
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         التوقيعات
    ====================================================== -->

    <div class="signatures">

        <div class="signature">

            مستلم المبلغ

        </div>

        <div class="signature">

            أمين الصندوق

        </div>

    </div>


</div>


<script>

window.addEventListener(
    'load',
    function () {

        setTimeout(
            function () {

                window.print();

            },
            300
        );

    }
);

</script>


<?php endif; ?>


</body>

</html>