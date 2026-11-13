<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('رقم الفاتورة غير صحيح.');
}

$sale = null;
$items = [];
$settings = [];
$error = '';

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

try {

    /*
    |--------------------------------------------------------------------------
    | بيانات الفاتورة
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

            p.pump_no,
            p.name AS pump_name,

            e.employee_no,
            e.full_name AS employee_name,

            sh.shift_no,
            sh.status AS shift_status,

            c.customer_no,
            c.full_name AS customer_name,
            c.phone AS customer_phone

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
        $id
    ]);

    $sale =
        $saleStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$sale) {

        throw new Exception(
            'الفاتورة غير موجودة.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | تفاصيل الفاتورة
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
        $id
    ]);

    $items =
        $itemsStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | التأكد من وجود تفاصيل
    |--------------------------------------------------------------------------
    */

    if (empty($items)) {

        throw new Exception(
            'لا توجد تفاصيل مرتبطة بهذه الفاتورة.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | إعدادات المحطة
    |--------------------------------------------------------------------------
    */

    $settingsStmt = $pdo->query("
        SELECT
            setting_key,
            setting_value
        FROM settings
    ");

    $settingsRows =
        $settingsStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach ($settingsRows as $row) {

        $settings[
            $row['setting_key']
        ] =
            $row['setting_value'];
    }


} catch (Throwable $e) {

    error_log(
        'Sales view error: '
        .
        $e->getMessage()
    );

    $error =
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| بيانات المحطة
|--------------------------------------------------------------------------
*/

$stationName =
    $settings['station_name']
    ??
    'محطة الوقود';

$stationPhone =
    $settings['station_phone']
    ??
    '';

$stationAddress =
    $settings['station_address']
    ??
    '';

$currency =
    $settings['currency']
    ??
    'ريال';


/*
|--------------------------------------------------------------------------
| طريقة الدفع
|--------------------------------------------------------------------------
*/

$paymentMethodText =
    match (
        $sale['payment_method'] ?? ''
    ) {

        'cash' =>
            'نقدي',

        'card' =>
            'بطاقة',

        'credit' =>
            'آجل',

        default =>
            '-'
    };


/*
|--------------------------------------------------------------------------
| التاريخ
|--------------------------------------------------------------------------
*/

$saleDate = '';

if (!empty($sale['sale_date'])) {

    $timestamp =
        strtotime(
            $sale['sale_date']
        );

    if ($timestamp !== false) {

        $saleDate =
            date(
                'Y-m-d H:i',
                $timestamp
            );

    } else {

        $saleDate =
            $sale['sale_date'];
    }
}

?>

<!DOCTYPE html>

<html
    lang="ar"
    dir="rtl"
>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>

        فاتورة
        <?= e($sale['invoice_no'] ?? '') ?>

    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        html,
        body {

            margin: 0;
            padding: 0;

            background: #fff;

            font-family:
                Tahoma,
                Arial,
                sans-serif;

            color: #000;

        }


        body {

            direction: rtl;

        }


        /*
        =========================================================
        الفاتورة
        =========================================================
        */

        .receipt {

            width: 80mm;

            max-width: 80mm;

            margin: 0 auto;

            padding: 5mm 3mm;

            font-size: 12px;

            line-height: 1.5;

        }


        /*
        =========================================================
        رأس الفاتورة
        =========================================================
        */

        .header {

            text-align: center;

            margin-bottom: 8px;

        }


        .station-name {

            font-size: 19px;

            font-weight: bold;

            margin-bottom: 3px;

        }


        .station-info {

            font-size: 11px;

            line-height: 1.5;

        }


        /*
        =========================================================
        الفواصل
        =========================================================
        */

        .separator {

            border-top:
                1px dashed #000;

            margin:
                7px 0;

        }


        /*
        =========================================================
        عنوان الفاتورة
        =========================================================
        */

        .invoice-title {

            text-align: center;

            font-size: 16px;

            font-weight: bold;

            margin: 5px 0;

        }


        /*
        =========================================================
        معلومات الفاتورة
        =========================================================
        */

        .info-row {

            display: flex;

            justify-content: space-between;

            gap: 5px;

            margin: 2px 0;

        }


        .info-label {

            font-weight: bold;

            white-space: nowrap;

        }


        .info-value {

            text-align: left;

            word-break: break-word;

        }


        /*
        =========================================================
        العميل
        =========================================================
        */

        .customer-box {

            margin-top: 5px;

            padding: 4px 0;

        }


        /*
        =========================================================
        جدول الوقود
        =========================================================
        */

        .items {

            width: 100%;

            border-collapse: collapse;

            margin-top: 7px;

            font-size: 11px;

        }


        .items th {

            border-bottom:
                1px solid #000;

            padding:
                4px 1px;

            text-align: center;

            font-weight: bold;

        }


        .items td {

            padding:
                4px 1px;

            text-align: center;

            vertical-align: middle;

        }


        .items .fuel {

            text-align: right;

        }


        /*
        =========================================================
        الإجماليات
        =========================================================
        */

        .totals {

            margin-top: 7px;

            border-top:
                1px dashed #000;

            padding-top: 5px;

        }


        .total-row {

            display: flex;

            justify-content: space-between;

            margin: 3px 0;

        }


        .grand-total {

            font-size: 17px;

            font-weight: bold;

            border-top:
                1px solid #000;

            border-bottom:
                1px solid #000;

            padding:
                6px 0;

            margin-top: 5px;

        }


        /*
        =========================================================
        الملاحظات
        =========================================================
        */

        .notes {

            margin-top: 8px;

            font-size: 10px;

        }


        /*
        =========================================================
        التذييل
        =========================================================
        */

        .footer {

            text-align: center;

            margin-top: 12px;

            font-size: 11px;

        }


        .thank-you {

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 3px;

        }


        /*
        =========================================================
        أزرار التحكم
        =========================================================
        */

        .print-actions {

            width: 80mm;

            max-width: 80mm;

            margin: 15px auto;

            text-align: center;

        }


        .print-actions button,
        .print-actions a {

            display: inline-block;

            padding:
                9px 15px;

            margin: 3px;

            border: none;

            border-radius: 5px;

            text-decoration: none;

            cursor: pointer;

            font-family: inherit;

            font-size: 13px;

        }


        .btn-print {

            background: #198754;

            color: #fff;

        }


        .btn-back {

            background: #6c757d;

            color: #fff;

        }


        .btn-delete {

            background: #dc3545;

            color: #fff;

        }


        /*
        =========================================================
        الطباعة
        =========================================================
        */

        @media print {

            @page {

                size: 80mm auto;

                margin: 0;

            }


            html,
            body {

                width: 80mm;

                margin: 0;

                padding: 0;

            }


            .receipt {

                width: 80mm;

                max-width: 80mm;

                margin: 0;

                padding:
                    3mm 3mm;

            }


            .print-actions {

                display: none !important;

            }


            body {

                -webkit-print-color-adjust:
                    exact;

                print-color-adjust:
                    exact;

            }

        }

    </style>

</head>


<body>


<?php if ($error): ?>


    <div
        style="
            width:80mm;
            margin:20px auto;
            text-align:center;
            color:red;
        "
    >

        <?= e($error) ?>

    </div>


<?php else: ?>


    <!-- =====================================================
         الفاتورة
    ====================================================== -->

    <div class="receipt">


        <!-- =================================================
             رأس المحطة
        ================================================== -->

        <div class="header">

            <div class="station-name">

                <?= e(
                    $stationName
                ) ?>

            </div>


            <?php if ($stationPhone): ?>

                <div class="station-info">

                    هاتف:
                    <?= e(
                        $stationPhone
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if ($stationAddress): ?>

                <div class="station-info">

                    <?= e(
                        $stationAddress
                    ) ?>

                </div>

            <?php endif; ?>

        </div>


        <div class="separator"></div>


        <!-- =================================================
             عنوان الفاتورة
        ================================================== -->

        <div class="invoice-title">

            فاتورة بيع وقود

        </div>


        <!-- =================================================
             رقم الفاتورة
        ================================================== -->

        <div class="info-row">

            <span class="info-label">
                رقم الفاتورة:
            </span>

            <span class="info-value">

                <?= e(
                    $sale['invoice_no']
                ) ?>

            </span>

        </div>


        <!-- =================================================
             التاريخ
        ================================================== -->

        <div class="info-row">

            <span class="info-label">
                التاريخ:
            </span>

            <span class="info-value">

                <?= e(
                    $saleDate
                ) ?>

            </span>

        </div>


        <!-- =================================================
             الوردية
        ================================================== -->

        <div class="info-row">

            <span class="info-label">
                الوردية:
            </span>

            <span class="info-value">

                <?= !empty(
                    $sale['shift_no']
                )
                    ? e(
                        $sale['shift_no']
                    )
                    : '-'
                ?>

            </span>

        </div>


        <!-- =================================================
             الطرمبة
        ================================================== -->

        <div class="info-row">

            <span class="info-label">
                الطرمبة:
            </span>

            <span class="info-value">

                <?= e(
                    'طرمبة '
                    .
                    ($sale['pump_no'] ?? '')
                    .
                    (
                        !empty(
                            $sale['pump_name']
                        )
                            ? ' - '
                            .
                            $sale['pump_name']
                            : ''
                    )
                ) ?>

            </span>

        </div>


        <!-- =================================================
             الموظف
        ================================================== -->

        <div class="info-row">

            <span class="info-label">
                الموظف:
            </span>

            <span class="info-value">

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

            </span>

        </div>


        <!-- =================================================
             العميل
        ================================================== -->

        <?php if (
            ($sale['payment_method'] ?? '')
            ===
            'credit'
        ): ?>


            <div class="customer-box">

                <div class="info-row">

                    <span class="info-label">
                        العميل:
                    </span>

                    <span class="info-value">

                        <?= !empty(
                            $sale['customer_name']
                        )
                            ? e(
                                $sale['customer_name']
                            )
                            : '-'
                        ?>

                    </span>

                </div>


                <?php if (
                    !empty(
                        $sale['customer_no']
                    )
                ): ?>

                    <div class="info-row">

                        <span class="info-label">
                            رقم العميل:
                        </span>

                        <span class="info-value">

                            <?= e(
                                $sale['customer_no']
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>


                <?php if (
                    !empty(
                        $sale['customer_phone']
                    )
                ): ?>

                    <div class="info-row">

                        <span class="info-label">
                            الهاتف:
                        </span>

                        <span class="info-value">

                            <?= e(
                                $sale['customer_phone']
                            ) ?>

                        </span>

                    </div>

                <?php endif; ?>

            </div>


        <?php endif; ?>


        <div class="separator"></div>


        <!-- =================================================
             تفاصيل الوقود
        ================================================== -->

        <table class="items">

            <thead>

                <tr>

                    <th>
                        الوقود
                    </th>

                    <th>
                        الكمية
                    </th>

                    <th>
                        السعر
                    </th>

                    <th>
                        الإجمالي
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php foreach ($items as $item): ?>

                <tr>

                    <td class="fuel">

                        <?= e(
                            $item['fuel_name']
                        ) ?>

                    </td>


                    <td>

                        <?= number_format(
                            (float)$item['quantity'],
                            2
                        ) ?>

                        <?php if (
                            !empty(
                                $item['unit']
                            )
                        ): ?>

                            <?= e(
                                $item['unit']
                            ) ?>

                        <?php endif; ?>

                    </td>


                    <td>

                        <?= number_format(
                            (float)$item['unit_price'],
                            2
                        ) ?>

                    </td>


                    <td>

                        <?= number_format(
                            (float)$item['total'],
                            2
                        ) ?>

                    </td>

                </tr>

            <?php endforeach; ?>


            </tbody>

        </table>


        <!-- =================================================
             الإجماليات
        ================================================== -->

        <div class="totals">


            <div class="total-row">

                <span>
                    المجموع:
                </span>

                <span>

                    <?= number_format(
                        (float)(
                            $sale['subtotal']
                            ??
                            0
                        ),
                        2
                    ) ?>

                    <?= e(
                        $currency
                    ) ?>

                </span>

            </div>


            <div class="total-row">

                <span>
                    الخصم:
                </span>

                <span>

                    <?= number_format(
                        (float)(
                            $sale['discount']
                            ??
                            0
                        ),
                        2
                    ) ?>

                    <?= e(
                        $currency
                    ) ?>

                </span>

            </div>


            <div class="total-row grand-total">

                <span>
                    الإجمالي:
                </span>

                <span>

                    <?= number_format(
                        (float)(
                            $sale['total']
                            ??
                            0
                        ),
                        2
                    ) ?>

                    <?= e(
                        $currency
                    ) ?>

                </span>

            </div>


            <!-- =================================================
                 طريقة الدفع
            ================================================== -->

            <div class="total-row">

                <span>
                    طريقة الدفع:
                </span>

                <span>

                    <?= e(
                        $paymentMethodText
                    ) ?>

                </span>

            </div>


        </div>


        <!-- =================================================
             الملاحظات
        ================================================== -->

        <?php if (
            !empty(
                $sale['notes']
            )
        ): ?>

            <div class="separator"></div>

            <div class="notes">

                <strong>
                    ملاحظات:
                </strong>

                <br>

                <?= nl2br(
                    e(
                        $sale['notes']
                    )
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             التذييل
        ================================================== -->

        <div class="footer">

            <div class="thank-you">

                شكراً لتعاملكم معنا

            </div>

            <div>

                نتمنى لكم رحلة سعيدة

            </div>

        </div>


    </div>


    <!-- =====================================================
         أزرار التحكم
    ====================================================== -->

    <div class="print-actions">


        <button
            type="button"
            class="btn-print"
            onclick="window.print()"
        >

            🖨️ طباعة الفاتورة

        </button>


        <a
            href="index.php"
            class="btn-back"
        >

            ↩ العودة للمبيعات

        </a>


        <a
            href="delete.php?id=<?= (int)$sale['id'] ?>"
            class="btn-delete"
            onclick="
                return confirm(
                    'هل أنت متأكد من حذف هذه الفاتورة؟ سيتم عكس المخزون والوردية وحساب العميل إذا كانت آجلة.'
                );
            "
        >

            🗑️ حذف

        </a>


    </div>


<?php endif; ?>


<script>

/*
|--------------------------------------------------------------------------
| الطباعة التلقائية
|--------------------------------------------------------------------------
|
| يتم فتح نافذة الطباعة بعد تحميل الفاتورة.
|
*/

window.addEventListener(
    'load',
    function () {

        setTimeout(
            function () {

                window.print();

            },
            500
        );

    }
);

</script>


</body>

</html>