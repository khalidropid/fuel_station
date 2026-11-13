<?php

require_once __DIR__ . '/../config/database.php';

$invoiceId = (int)($_GET['id'] ?? 0);

if ($invoiceId <= 0) {
    die('رقم الفاتورة غير صحيح.');
}


$stmt = $pdo->prepare("
    SELECT
        s.*,

        p.pump_no,
        p.name AS pump_name,

        e.full_name AS employee_name

    FROM sales s

    INNER JOIN pumps p
        ON p.id = s.pump_id

    LEFT JOIN employees e
        ON e.id = s.employee_id

    WHERE s.id = ?

    LIMIT 1
");

$stmt->execute([$invoiceId]);

$invoice = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$invoice) {
    die('الفاتورة غير موجودة.');
}


$itemsStmt = $pdo->prepare("
    SELECT
        si.quantity,
        si.unit_price,
        si.total,

        ft.name AS fuel_name,
        ft.unit

    FROM sale_items si

    INNER JOIN fuel_types ft
        ON ft.id = si.fuel_type_id

    WHERE si.sale_id = ?

    ORDER BY si.id ASC
");

$itemsStmt->execute([$invoiceId]);

$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <title>
        فاتورة <?= htmlspecialchars($invoice['invoice_no']) ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            font-family: Arial, sans-serif;

            margin: 0;

            padding: 20px;

            background: #fff;

            color: #000;

        }

        .invoice {

            width: 80mm;

            max-width: 100%;

            margin: auto;

        }

        .center {

            text-align: center;

        }

        h2 {

            margin: 5px 0;

        }

        h3 {

            margin: 5px 0 15px;

        }

        .line {

            border-top: 1px dashed #000;

            margin: 10px 0;

        }

        table {

            width: 100%;

            border-collapse: collapse;

            font-size: 12px;

        }

        th,
        td {

            padding: 5px 2px;

            text-align: right;

        }

        th {

            border-bottom: 1px solid #000;

        }

        .total {

            font-size: 16px;

            font-weight: bold;

        }

        .footer {

            text-align: center;

            margin-top: 20px;

            font-size: 12px;

        }

        .print-button {

            margin-top: 20px;

            text-align: center;

        }

        button {

            padding: 10px 20px;

            cursor: pointer;

        }

        @media print {

            body {

                padding: 0;

            }

            .print-button {

                display: none;

            }

            .invoice {

                width: 80mm;

            }

        }

    </style>

</head>

<body>


<div class="invoice">


    <div class="center">

        <h2>
            محطة الوقود
        </h2>

        <div>
            فاتورة مبيعات
        </div>

    </div>


    <div class="line"></div>


    <div>

        <strong>
            رقم الفاتورة:
        </strong>

        <?= htmlspecialchars(
            $invoice['invoice_no']
        ) ?>

    </div>


    <div>

        <strong>
            التاريخ:
        </strong>

        <?= htmlspecialchars(
            $invoice['sale_date']
        ) ?>

    </div>


    <div>

        <strong>
            الطرمبة:
        </strong>

        <?= htmlspecialchars(
            $invoice['pump_no']
        ) ?>

    </div>


    <?php if ($invoice['employee_name']): ?>

        <div>

            <strong>
                الموظف:
            </strong>

            <?= htmlspecialchars(
                $invoice['employee_name']
            ) ?>

        </div>

    <?php endif; ?>


    <div class="line"></div>


    <table>

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

                <td>
                    <?= htmlspecialchars(
                        $item['fuel_name']
                    ) ?>
                </td>

                <td>
                    <?= number_format(
                        (float)$item['quantity'],
                        2
                    ) ?>
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


    <div class="line"></div>


    <table>

        <tr>

            <td>
                المجموع
            </td>

            <td>
                <?= number_format(
                    (float)$invoice['subtotal'],
                    2
                ) ?>
            </td>

        </tr>


        <tr>

            <td>
                الخصم
            </td>

            <td>
                <?= number_format(
                    (float)$invoice['discount'],
                    2
                ) ?>
            </td>

        </tr>


        <tr class="total">

            <td>
                الإجمالي
            </td>

            <td>

                <?= number_format(
                    (float)$invoice['total'],
                    2
                ) ?>

            </td>

        </tr>

    </table>


    <div class="line"></div>


    <div>

        <strong>
            طريقة الدفع:
        </strong>

        <?php

        if ($invoice['payment_method'] === 'cash') {

            echo 'نقدي';

        } elseif (
            $invoice['payment_method'] === 'card'
        ) {

            echo 'بطاقة';

        } else {

            echo 'آجل';

        }

        ?>

    </div>


    <div class="footer">

        شكراً لزيارتكم

        <br>

        نتمنى لكم رحلة سعيدة

    </div>


    <div class="print-button">

        <button onclick="window.print()">
            🖨️ طباعة
        </button>

    </div>


</div>


</body>

</html>
