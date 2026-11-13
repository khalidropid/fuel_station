<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'الفواتير';

$invoices = [];
$error = '';

try {

    $stmt = $pdo->query("
        SELECT
            s.id,
            s.invoice_no,
            s.sale_date,
            s.payment_method,
            s.subtotal,
            s.discount,
            s.total,

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

        ORDER BY s.id DESC
    ");

    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error = $e->getMessage();

}

require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>

        <h2>
            الفواتير
        </h2>

        <p>
            عرض ومتابعة وطباعة فواتير مبيعات الوقود.
        </p>

    </div>

    <div>

        <a
            href="../sales/create.php"
            class="btn btn-primary"
        >
            + تسجيل عملية بيع
        </a>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">

        خطأ قاعدة البيانات:

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<div class="cards">

    <div class="card stat-card">

        <div class="stat-icon">
            🧾
        </div>

        <div class="stat-info">

            <span>
                عدد الفواتير
            </span>

            <strong>
                <?= count($invoices) ?>
            </strong>

        </div>

    </div>


    <div class="card stat-card">

        <div class="stat-icon">
            💰
        </div>

        <div class="stat-info">

            <span>
                إجمالي الفواتير
            </span>

            <strong>

                <?= number_format(
                    array_sum(
                        array_column($invoices, 'total')
                    ),
                    2
                ) ?>

            </strong>

        </div>

    </div>


    <div class="card stat-card">

        <div class="stat-icon">
            💵
        </div>

        <div class="stat-info">

            <span>
                الفواتير النقدية
            </span>

            <strong>

                <?php

                $cashTotal = 0;

                foreach ($invoices as $invoice) {

                    if ($invoice['payment_method'] === 'cash') {

                        $cashTotal += (float)$invoice['total'];

                    }

                }

                echo number_format($cashTotal, 2);

                ?>

            </strong>

        </div>

    </div>


    <div class="card stat-card">

        <div class="stat-icon">
            💳
        </div>

        <div class="stat-info">

            <span>
                فواتير البطاقات
            </span>

            <strong>

                <?php

                $cardTotal = 0;

                foreach ($invoices as $invoice) {

                    if ($invoice['payment_method'] === 'card') {

                        $cardTotal += (float)$invoice['total'];

                    }

                }

                echo number_format($cardTotal, 2);

                ?>

            </strong>

        </div>

    </div>

</div>


<div class="card">

    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>

                    <th>#</th>

                    <th>رقم الفاتورة</th>

                    <th>التاريخ</th>

                    <th>الوردية</th>

                    <th>الطرمبة</th>

                    <th>الموظف</th>

                    <th>طريقة الدفع</th>

                    <th>الخصم</th>

                    <th>الإجمالي</th>

                    <th>الإجراءات</th>

                </tr>

            </thead>

            <tbody>

            <?php if (empty($invoices)): ?>

                <tr>

                    <td
                        colspan="10"
                        style="text-align:center;"
                    >

                        لا توجد فواتير حتى الآن.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($invoices as $invoice): ?>

                    <tr>

                        <td>
                            <?= (int)$invoice['id'] ?>
                        </td>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $invoice['invoice_no']
                                ) ?>
                            </strong>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $invoice['sale_date']
                            ) ?>
                        </td>

                        <td>

                            <?= $invoice['shift_no']
                                ? htmlspecialchars(
                                    $invoice['shift_no']
                                )
                                : '-'
                            ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                'طرمبة ' .
                                $invoice['pump_no'] .
                                ' - ' .
                                $invoice['pump_name']
                            ) ?>

                        </td>

                        <td>

                            <?= $invoice['employee_name']
                                ? htmlspecialchars(
                                    $invoice['employee_name']
                                )
                                : '-'
                            ?>

                        </td>

                        <td>

                            <?php if (
                                $invoice['payment_method'] === 'cash'
                            ): ?>

                                <span class="badge badge-success">
                                    نقدي
                                </span>

                            <?php elseif (
                                $invoice['payment_method'] === 'card'
                            ): ?>

                                <span class="badge">
                                    بطاقة
                                </span>

                            <?php else: ?>

                                <span class="badge badge-warning">
                                    آجل
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?= number_format(
                                (float)$invoice['discount'],
                                2
                            ) ?>

                        </td>

                        <td>

                            <strong>

                                <?= number_format(
                                    (float)$invoice['total'],
                                    2
                                ) ?>

                            </strong>

                        </td>

                        <td>

                            <a
                                href="view.php?id=<?= (int)$invoice['id'] ?>"
                                class="btn btn-sm"
                            >
                                عرض
                            </a>

                            <a
                                href="print.php?id=<?= (int)$invoice['id'] ?>"
                                class="btn btn-sm btn-primary"
                                target="_blank"
                            >
                                طباعة
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
