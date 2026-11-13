<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'تحصيلات العملاء';

$payments = [];
$error = '';

try {

    $stmt = $pdo->query("
        SELECT
            cp.id,
            cp.payment_no,
            cp.customer_id,
            cp.amount,
            cp.payment_method,
            cp.payment_date,
            cp.notes,

            c.customer_no,
            c.full_name AS customer_name,

            u.full_name AS created_by_name

        FROM customer_payments cp

        INNER JOIN customers c
            ON c.id = cp.customer_id

        LEFT JOIN users u
            ON u.id = cp.created_by

        ORDER BY cp.id DESC
    ");

    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $error = $e->getMessage();

}

require_once __DIR__ . '/../includes/header.php';

?>

<!-- تم تعديل هذا القسم لجعل الزر في اليمين -->
<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 30px;">

    <!-- 1. العنوان -->
    <div>
        <h2 style="margin: 0;">تحصيلات العملاء</h2>
        <p>
            تسجيل ومتابعة المبالغ التي قام العملاء بسدادها.
        </p>
    </div>

    <!-- 2. الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            + تسجيل تحصيل لعميل
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
                عدد السندات
            </span>

            <strong>
                <?= count($payments) ?>
            </strong>

        </div>

    </div>


    <div class="card stat-card">

        <div class="stat-icon">
            💰
        </div>

        <div class="stat-info">

            <span>
                إجمالي التحصيلات
            </span>

            <strong>

                <?= number_format(
                    array_sum(
                        array_map(
                            fn($payment) =>
                                (float)$payment['amount'],
                            $payments
                        )
                    ),
                    2
                ) ?>

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

                    <th>رقم السند</th>

                    <th>العميل</th>

                    <th>المبلغ</th>

                    <th>طريقة الدفع</th>

                    <th>تاريخ السداد</th>

                    <th>المستخدم</th>

                    <th>الإجراءات</th>

                </tr>

            </thead>

            <tbody>

            <?php if (empty($payments)): ?>

                <tr>

                    <td
                        colspan="8"
                        style="text-align:center;"
                    >
                        لا توجد تحصيلات حتى الآن.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($payments as $payment): ?>

                    <tr>

                        <td>
                            <?= (int)$payment['id'] ?>
                        </td>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $payment['payment_no']
                                ) ?>
                            </strong>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $payment['customer_no']
                                . ' - '
                                . $payment['customer_name']
                            ) ?>

                        </td>

                        <td>

                            <strong>

                                <?= number_format(
                                    (float)$payment['amount'],
                                    2
                                ) ?>

                            </strong>

                        </td>

                        <td>

                            <?php if (
                                $payment['payment_method']
                                === 'cash'
                            ): ?>

                                <span class="badge badge-success">
                                    نقدي
                                </span>

                            <?php elseif (
                                $payment['payment_method']
                                === 'card'
                            ): ?>

                                <span class="badge">
                                    بطاقة
                                </span>

                            <?php else: ?>

                                <span class="badge badge-warning">
                                    أخرى
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $payment['payment_date']
                            ) ?>
                        </td>

                        <td>

                            <?= $payment['created_by_name']
                                ? htmlspecialchars(
                                    $payment['created_by_name']
                                )
                                : '-'
                            ?>

                        </td>

                        <td>

                            <a
                                href="view.php?id=<?= (int)$payment['id'] ?>"
                                class="btn btn-sm"
                            >
                                عرض
                            </a>

                            <a
                                href="delete.php?id=<?= (int)$payment['id'] ?>"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('هل أنت متأكد من حذف سند التحصيل؟');"
                            >
                                حذف
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