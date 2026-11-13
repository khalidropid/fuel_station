<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'كشف الحساب';

$accountId =
    (int)($_GET['id'] ?? 0);

if ($accountId <= 0) {

    header('Location: index.php');

    exit;
}


try {

    $account =
        accountingGetAccountById(
            $pdo,
            $accountId
        );


    if (!$account) {

        throw new Exception(
            'الحساب غير موجود.'
        );
    }


    $balance =
        accountingGetBalance(
            $pdo,
            $accountId
        );


    $stmt = $pdo->prepare("
        SELECT
            at.*
        FROM account_transactions at
        WHERE at.account_id = ?
        ORDER BY
            at.transaction_date ASC,
            at.id ASC
    ");

    $stmt->execute([
        $accountId
    ]);


    $transactions =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    $runningBalance =
        (float)$account['opening_balance'];

} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>

        <h2>
            كشف حساب
        </h2>

        <p>

            <?= htmlspecialchars(
                $account['account_name'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            -

            <?= htmlspecialchars(
                $account['account_no'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </p>

    </div>

    <div>

        <a
            href="transaction.php?account_id=<?= $accountId ?>"
            class="btn btn-primary"
        >
            إضافة حركة
        </a>

        <a
            href="index.php"
            class="btn"
        >
            الحسابات
        </a>

    </div>

</div>


<?php if (!empty($error)): ?>

<div class="alert alert-danger">

    <?= htmlspecialchars(
        $error,
        ENT_QUOTES,
        'UTF-8'
    ) ?>

</div>

<?php else: ?>


<div class="card">

    <div>

        <strong>
            الحساب:
        </strong>

        <?= htmlspecialchars(
            $account['account_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

    <div>

        <strong>
            رقم الحساب:
        </strong>

        <?= htmlspecialchars(
            $account['account_no'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

    <hr>

    <div>

        <strong>
            إجمالي المدين:
        </strong>

        <?= number_format(
            $balance['debit'],
            2
        ) ?>

    </div>

    <div>

        <strong>
            إجمالي الدائن:
        </strong>

        <?= number_format(
            $balance['credit'],
            2
        ) ?>

    </div>

    <div>

        <strong>
            النتيجة:
        </strong>

        <?php if ($balance['balance'] > 0): ?>

            عليه
            <?= number_format(
                $balance['amount'],
                2
            ) ?>

        <?php elseif ($balance['balance'] < 0): ?>

            له
            <?= number_format(
                $balance['amount'],
                2
            ) ?>

        <?php else: ?>

            متزن

        <?php endif; ?>

    </div>

</div>


<div class="card">

    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>

                    <th>التاريخ</th>

                    <th>رقم الحركة</th>

                    <th>البيان</th>

                    <th>مدين</th>

                    <th>دائن</th>

                    <th>الرصيد</th>

                    <th>الحالة</th>

                </tr>

            </thead>

            <tbody>

                <tr>

                    <td colspan="5">
                        الرصيد الافتتاحي
                    </td>

                    <td>
                        <?= number_format(
                            abs($runningBalance),
                            2
                        ) ?>
                    </td>

                    <td>

                        <?= $runningBalance > 0
                            ? 'عليه'
                            : (
                                $runningBalance < 0
                                    ? 'له'
                                    : 'متزن'
                            ) ?>

                    </td>

                </tr>


                <?php foreach ($transactions as $transaction): ?>

                    <?php

                    $runningBalance =
                        round(
                            $runningBalance
                            +
                            (float)$transaction['debit']
                            -
                            (float)$transaction['credit'],
                            2
                        );

                    ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $transaction['transaction_date'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $transaction['transaction_no'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $transaction['description'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (float)$transaction['debit'],
                                2
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (float)$transaction['credit'],
                                2
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                abs($runningBalance),
                                2
                            ) ?>
                        </td>

                        <td>

                            <?php if ($runningBalance > 0): ?>

                                عليه

                            <?php elseif ($runningBalance < 0): ?>

                                له

                            <?php else: ?>

                                متزن

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>