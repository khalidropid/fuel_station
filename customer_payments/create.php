<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'تسجيل سداد عميل';

$error = '';

$customerId =
    (int)(
        $_GET['customer_id']
        ??
        $_POST['customer_id']
        ??
        0
    );

$customers = [];


function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function money($value): string
{
    return number_format(
        (float)$value,
        2
    );
}


try {

    /*
    |--------------------------------------------------------------------------
    | العملاء
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            customer_no,
            full_name,
            balance,
            credit_limit,
            status
        FROM customers
        WHERE status = 'active'
        ORDER BY full_name ASC
    ");

    $customers =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    */

    if (
        $_SERVER['REQUEST_METHOD']
        ===
        'POST'
    ) {

        $customerId =
            (int)(
                $_POST['customer_id']
                ??
                0
            );

        $paymentMethod =
            trim(
                $_POST['payment_method']
                ??
                'cash'
            );

        $amount =
            (float)(
                $_POST['amount']
                ??
                0
            );

        $paymentDate =
            trim(
                $_POST['payment_date']
                ??
                date('Y-m-d\TH:i')
            );

        $notes =
            trim(
                $_POST['notes']
                ??
                ''
            );


        if (
            $customerId <= 0
        ) {

            throw new Exception(
                'يرجى اختيار العميل.'
            );
        }


        if (
            $amount <= 0
        ) {

            throw new Exception(
                'مبلغ السداد يجب أن يكون أكبر من صفر.'
            );
        }


        if (
            !in_array(
                $paymentMethod,
                [
                    'cash',
                    'card'
                ],
                true
            )
        ) {

            throw new Exception(
                'طريقة الدفع غير صحيحة.'
            );
        }


        $paymentDateDb =
            str_replace(
                'T',
                ' ',
                $paymentDate
            );


        if (
            strtotime(
                $paymentDateDb
            )
            ===
            false
        ) {

            throw new Exception(
                'تاريخ السداد غير صحيح.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Transaction
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | قفل العميل
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                customer_no,
                full_name,
                balance,
                credit_limit,
                status
            FROM customers
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $customerId
        ]);

        $customer =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$customer) {

            throw new Exception(
                'العميل غير موجود.'
            );
        }


        if (
            $customer['status']
            !==
            'active'
        ) {

            throw new Exception(
                'العميل غير نشط.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | حساب العميل
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                account_no,
                account_name,
                status
            FROM accounts
            WHERE customer_id = ?
            AND account_type = 'customer'
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $customerId
        ]);

        $customerAccount =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$customerAccount) {

            throw new Exception(
                'حساب العميل غير موجود.'
            );
        }


        if (
            $customerAccount['status']
            !==
            'active'
        ) {

            throw new Exception(
                'حساب العميل غير نشط.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | منع السداد الزائد
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                opening_balance,

                COALESCE(
                    (
                        SELECT SUM(debit)
                        FROM account_transactions
                        WHERE account_id = a.id
                    ),
                    0
                ) AS total_debit,

                COALESCE(
                    (
                        SELECT SUM(credit)
                        FROM account_transactions
                        WHERE account_id = a.id
                    ),
                    0
                ) AS total_credit

            FROM accounts a

            WHERE a.id = ?

            LIMIT 1
        ");

        $stmt->execute([
            $customerAccount['id']
        ]);

        $accountBalanceRow =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        $currentBalance =
            round(
                (float)$accountBalanceRow['opening_balance']
                +
                (float)$accountBalanceRow['total_debit']
                -
                (float)$accountBalanceRow['total_credit'],
                2
            );


        if (
            $currentBalance <= 0
        ) {

            throw new Exception(
                'لا يوجد مبلغ مستحق على العميل.'
            );
        }


        if (
            $amount > $currentBalance
        ) {

            throw new Exception(
                'مبلغ السداد أكبر من الرصيد المستحق. '
                .
                'المستحق: '
                .
                money($currentBalance)
            );
        }


        /*
        |--------------------------------------------------------------------------
        | حساب الخزينة
        |--------------------------------------------------------------------------
        */

        $cashAccountNo =
            $paymentMethod === 'cash'
                ? 'CASH-001'
                : 'CARD-001';


        $stmt = $pdo->prepare("
            SELECT
                id,
                account_no,
                account_name,
                account_type,
                status
            FROM accounts
            WHERE account_no = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $cashAccountNo
        ]);

        $paymentAccount =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$paymentAccount) {

            throw new Exception(
                'حساب الدفع غير موجود: '
                .
                $cashAccountNo
            );
        }


        if (
            $paymentAccount['status']
            !==
            'active'
        ) {

            throw new Exception(
                'حساب الدفع غير نشط.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | رقم السداد
        |--------------------------------------------------------------------------
        */

        $paymentNo =
            'PAY-'
            .
            date('YmdHis')
            .
            '-'
            .
            random_int(
                100,
                999
            );


        /*
        |--------------------------------------------------------------------------
        | إنشاء customer_payments
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO customer_payments
            (
                payment_no,
                customer_id,
                amount,
                payment_date,
                payment_method,
                notes,
                created_by
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $paymentNo,
            $customerId,
            $amount,
            $paymentDateDb,
            $paymentMethod,
            $notes !== ''
                ? $notes
                : null,
            $_SESSION['user_id']
                ??
                null
        ]);


        $paymentId =
            (int)$pdo->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | رقم الحركة المحاسبية
        |--------------------------------------------------------------------------
        */

        $transactionNo =
            'TRX-'
            .
            date('YmdHis')
            .
            '-'
            .
            random_int(
                100,
                999
            );


        $description =
            'سداد عميل - '
            .
            $customer['full_name']
            .
            ' - '
            .
            $paymentNo;


        $createdBy =
            $_SESSION['user_id']
            ??
            null;


        /*
        |--------------------------------------------------------------------------
        | مدين: الخزينة / البطاقة
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO account_transactions
            (
                transaction_no,
                account_id,
                transaction_type,
                reference_type,
                reference_id,
                debit,
                credit,
                transaction_date,
                description,
                created_by
            )
            VALUES
            (
                ?,
                ?,
                'payment',
                'customer_payment',
                ?,
                ?,
                0,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $transactionNo,
            $paymentAccount['id'],
            $paymentId,
            $amount,
            $paymentDateDb,
            $description,
            $createdBy
        ]);


        /*
        |--------------------------------------------------------------------------
        | دائن: حساب العميل
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO account_transactions
            (
                transaction_no,
                account_id,
                transaction_type,
                reference_type,
                reference_id,
                debit,
                credit,
                transaction_date,
                description,
                created_by
            )
            VALUES
            (
                ?,
                ?,
                'payment',
                'customer_payment',
                ?,
                0,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $transactionNo,
            $customerAccount['id'],
            $paymentId,
            $amount,
            $paymentDateDb,
            $description,
            $createdBy
        ]);


        /*
        |--------------------------------------------------------------------------
        | الرصيد الجديد
        |--------------------------------------------------------------------------
        */

        $newBalance =
            round(
                $currentBalance
                -
                $amount,
                2
            );


        /*
        |--------------------------------------------------------------------------
        | تحديث customers.balance
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE customers
            SET balance = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $newBalance,
            $customerId
        ]);


        /*
        |--------------------------------------------------------------------------
        | customer_transactions
        |--------------------------------------------------------------------------
        |
        | لا نعتمد عليه في كشف الحساب الجديد.
        |
        | لكن نترك الحركة القديمة متوافقة
        | مع النظام الحالي حتى لا تتعطل الصفحات
        | القديمة التي قد تعتمد عليه.
        |
        */

        $stmt = $pdo->prepare("
            INSERT INTO customer_transactions
            (
                customer_id,
                transaction_type,
                reference_id,
                amount,
                transaction_date,
                description,
                created_by
            )
            VALUES
            (
                ?,
                'payment',
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $customerId,
            $paymentId,
            $amount,
            $paymentDateDb,
            $description,
            $createdBy
        ]);


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        header(
            'Location: ../customers/view.php?id='
            .
            $customerId
        );

        exit;
    }

} catch (Throwable $e) {

    if (
        $pdo instanceof PDO
        &&
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    $error =
        $e->getMessage();
}


require_once __DIR__ . '/../includes/header.php';

?>


<div class="page-header">

    <div>

        <h2>
            تسجيل سداد عميل
        </h2>

        <p>
            تسجيل السداد وترحيله إلى الحساب المالي تلقائيًا.
        </p>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= e($error) ?>

    </div>

<?php endif; ?>


<div class="card">

    <form
        method="POST"
    >


        <div class="form-group">

            <label>
                العميل
            </label>

            <select
                name="customer_id"
                required
                id="customer_id"
            >

                <option value="">
                    اختر العميل
                </option>


                <?php foreach (
                    $customers
                    as $customer
                ): ?>

                    <option
                        value="<?= (int)$customer['id'] ?>"
                        data-balance="<?= e(
                            $customer['balance']
                        ) ?>"
                        <?= (
                            $customerId
                            ===
                            (int)$customer['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= e(
                            $customer['customer_no']
                            .
                            ' - '
                            .
                            $customer['full_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <small
                id="customer_balance"
            ></small>

        </div>


        <div class="form-group">

            <label>
                طريقة الدفع
            </label>

            <select
                name="payment_method"
                required
            >

                <option value="cash">
                    نقدي
                </option>

                <option value="card">
                    بطاقة
                </option>

            </select>

        </div>


        <div class="form-group">

            <label>
                مبلغ السداد
            </label>

            <input
                type="number"
                name="amount"
                step="0.01"
                min="0.01"
                value="<?= e(
                    $_POST['amount'] ?? ''
                ) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label>
                تاريخ السداد
            </label>

            <input
                type="datetime-local"
                name="payment_date"
                value="<?= e(
                    $_POST['payment_date']
                    ??
                    date('Y-m-d\TH:i')
                ) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label>
                ملاحظات
            </label>

            <textarea
                name="notes"
                rows="4"
            ><?= e(
                $_POST['notes'] ?? ''
            ) ?></textarea>

        </div>


        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                تسجيل السداد
            </button>


            <a
                href="../customers/view.php?id=<?= (int)$customerId ?>"
                class="btn"
            >
                إلغاء
            </a>

        </div>


    </form>

</div>


<script>

const customerSelect =
    document.getElementById(
        'customer_id'
    );

const customerBalance =
    document.getElementById(
        'customer_balance'
    );


function updateCustomerBalance()
{
    const option =
        customerSelect.options[
            customerSelect.selectedIndex
        ];


    if (
        !option
        ||
        !option.value
    ) {

        customerBalance.textContent =
            '';

        return;
    }


    const balance =
        parseFloat(
            option.dataset.balance
        )
        ||
        0;


    customerBalance.textContent =
        'الرصيد الحالي: '
        +
        balance.toFixed(2);
}


customerSelect.addEventListener(
    'change',
    updateCustomerBalance
);


document.addEventListener(
    'DOMContentLoaded',
    updateCustomerBalance
);

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>