<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'عرض الفاتورة';

$invoiceId = (int)($_GET['id'] ?? 0);

$invoice = null;
$items = [];
$error = '';

try {

    if ($invoiceId <= 0) {
        throw new Exception('رقم الفاتورة غير صحيح.');
    }


    $stmt = $pdo->prepare("
        SELECT
            s.*,

            p.pump_no,
            p.name AS pump_name,

            e.employee_no,
            e.full_name AS employee_name,

            sh.shift_no

        FROM sales s

        INNER JOIN pumps p
            ON p.id = s.pump_id

        LEFT JOIN employees e
            ON e.id = s.employee_id

        LEFT JOIN shifts sh
            ON sh.id = s.shift_id

        WHERE s.id = ?

        LIMIT 1
    ");

    $stmt->execute([$invoiceId]);

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$invoice) {
        throw new Exception('الفاتورة غير موجودة.');
    }


    $itemsStmt = $pdo->prepare("
        SELECT
            si.id,
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

    $itemsStmt->execute([$invoiceId]);

    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $error = $e->getMessage();

}

require_once __DIR__ . '/../includes/header.php';

?>


<div class="page-header">

    <div>

        <h2>
            عرض الفاتورة
        </h2>

        <p>
            تفاصيل عملية البيع.
        </p>

    </div>

    <div>

        <a
            href="index.php"
            class="btn"
        >
            العودة للفواتير
        </a>

        <?php if ($invoice): ?>

            <a
                href="print.php?id=<?= (int)$invoice['id'] ?>"
                target="_blank"
                class="btn btn-primary"
            >
                🖨️ طباعة الفاتورة
            </a>

        <?php endif; ?>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars($error) ?>

    </div>

<?php else: ?>


<div class="card">

    <div style="text-align:center;">

        <h2>
            محطة الوقود
        </h2>

        <h3>

            فاتورة رقم:

            <?= htmlspecialchars(
                $invoice['invoice_no']
            ) ?>

        </h3>

    </div>


    <hr>


    <div class="cards">

        <div class="card">

            <strong>
                التاريخ
            </strong>

            <br>

            <?= htmlspecialchars(
                $invoice['sale_date']
            ) ?>

        </div>


        <div class="card">

            <strong>
                الطرمبة
            </strong>

            <br>

            <?= htmlspecialchars(
                'طرمبة ' .
                $invoice['pump_no'] .
                ' - ' .
                $invoice['pump_name']
            ) ?>

        </div>


        <div class="card">

            <strong>
                الموظف
            </strong>

            <br>

            <?= $invoice['employee_name']
                ? htmlspecialchars(
                    $invoice['employee_name']
                )
                : '-'
            ?>

        </div>


        <div class="card">

            <strong>
                طريقة الدفع
            </strong>

            <br>

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

    </div>


    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>

                    <th>#</th>

                    <th>نوع الوقود</th>

                    <th>الكمية</th>

                    <th>سعر الوحدة</th>

                    <th>الإجمالي</th>

                </tr>

            </thead>

            <tbody>

            <?php if (empty($items)): ?>

                <tr>

                    <td
                        colspan="5"
                        style="text-align:center;"
                    >

                        لا توجد تفاصيل لهذه الفاتورة.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($items as $index => $item): ?>

                    <tr>

                        <td>
                            <?= $index + 1 ?>
                        </td>

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

                            <?= htmlspecialchars(
                                $item['unit']
                            ) ?>

                        </td>

                        <td>

                            <?= number_format(
                                (float)$item['unit_price'],
                                2
                            ) ?>

                        </td>

                        <td>

                            <strong>

                                <?= number_format(
                                    (float)$item['total'],
                                    2
                                ) ?>

                            </strong>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


    <div
        style="
            max-width:400px;
            margin-right:auto;
            margin-top:25px;
        "
    >

        <table class="table">

            <tr>

                <th>
                    المجموع الفرعي
                </th>

                <td>

                    <?= number_format(
                        (float)$invoice['subtotal'],
                        2
                    ) ?>

                </td>

            </tr>


            <tr>

                <th>
                    الخصم
                </th>

                <td>

                    <?= number_format(
                        (float)$invoice['discount'],
                        2
                    ) ?>

                </td>

            </tr>


            <tr>

                <th>
                    الإجمالي
                </th>

                <td>

                    <strong>

                        <?= number_format(
                            (float)$invoice['total'],
                            2
                        ) ?>

                    </strong>

                </td>

            </tr>

        </table>

    </div>


    <?php if (!empty($invoice['notes'])): ?>

        <div class="alert">

            <strong>
                ملاحظات:
            </strong>

            <?= htmlspecialchars(
                $invoice['notes']
            ) ?>

        </div>

    <?php endif; ?>


</div>


<?php endif; ?>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
