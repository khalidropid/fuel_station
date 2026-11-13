<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'تعديل المسحوبة';

$error = '';

$withdrawal = null;

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| أنواع المسحوبات
|--------------------------------------------------------------------------
*/

$withdrawalTypes = [

    'personal' => 'شخصي',

    'advance_salary' => 'سلفة راتب',

    'expense' => 'مصروف',

    'other' => 'أخرى'

];


/*
|--------------------------------------------------------------------------
| التحقق من رقم المسحوبة
|--------------------------------------------------------------------------
*/

if ($id <= 0) {

    $error = 'رقم المسحوبة غير صحيح.';

} else {

    try {

        /*
        |--------------------------------------------------------------------------
        | جلب بيانات المسحوبة
        |--------------------------------------------------------------------------
        |
        | نستخدم LEFT JOIN مع shifts لأن shift_id
        | يمكن أن يكون NULL.
        |
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT

                w.id,
                w.employee_id,
                w.shift_id,
                w.amount,
                w.withdrawal_date,
                w.withdrawal_type,
                w.description,
                w.created_by,
                w.created_at,

                e.employee_no,
                e.full_name AS employee_name,
                e.job_title,

                s.shift_no,
                s.status AS shift_status,
                s.opening_cash,
                s.start_datetime,
                s.end_datetime

            FROM employee_withdrawals w

            INNER JOIN employees e
                ON e.id = w.employee_id

            LEFT JOIN shifts s
                ON s.id = w.shift_id

            WHERE w.id = ?

            LIMIT 1
        ");

        $stmt->execute([
            $id
        ]);

        $withdrawal =
            $stmt->fetch(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | التحقق من وجود المسحوبة
        |--------------------------------------------------------------------------
        */

        if (!$withdrawal) {

            throw new RuntimeException(
                'المسحوبة غير موجودة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | إذا كانت المسحوبة مرتبطة بورديّة
        |--------------------------------------------------------------------------
        */

        if (
            $withdrawal['shift_id'] !== null
            &&
            $withdrawal['shift_id'] !== ''
        ) {

            if (
                $withdrawal['shift_status'] !== 'open'
            ) {

                throw new RuntimeException(
                    'لا يمكن تعديل مسحوبة مرتبطة بوردية مغلقة.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | معالجة POST
        |--------------------------------------------------------------------------
        */

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            /*
            |--------------------------------------------------------------------------
            | قراءة البيانات
            |--------------------------------------------------------------------------
            */

            $amount =
                trim(
                    $_POST['amount'] ?? ''
                );

            $withdrawalType =
                trim(
                    $_POST['withdrawal_type'] ?? ''
                );

            $description =
                trim(
                    $_POST['description'] ?? ''
                );


            /*
            |--------------------------------------------------------------------------
            | التحقق من المبلغ
            |--------------------------------------------------------------------------
            */

            if (
                $amount === ''
                ||
                !is_numeric($amount)
            ) {

                throw new RuntimeException(
                    'يجب إدخال مبلغ صحيح.'
                );
            }


            $newAmount =
                round(
                    (float)$amount,
                    2
                );


            if ($newAmount <= 0) {

                throw new RuntimeException(
                    'يجب أن يكون مبلغ المسحوبة أكبر من صفر.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | التحقق من نوع المسحوبة
            |--------------------------------------------------------------------------
            */

            if (
                !array_key_exists(
                    $withdrawalType,
                    $withdrawalTypes
                )
            ) {

                throw new RuntimeException(
                    'نوع المسحوبة غير صحيح.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | بدء Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | قفل المسحوبة
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT

                    w.id,
                    w.employee_id,
                    w.shift_id,
                    w.amount,
                    w.withdrawal_date,
                    w.withdrawal_type,
                    w.description,

                    s.status AS shift_status,
                    s.opening_cash

                FROM employee_withdrawals w

                LEFT JOIN shifts s
                    ON s.id = w.shift_id

                WHERE w.id = ?

                LIMIT 1

                FOR UPDATE
            ");

            $stmt->execute([
                $id
            ]);

            $current =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$current) {

                throw new RuntimeException(
                    'المسحوبة غير موجودة.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | التحقق من الوردية
            |--------------------------------------------------------------------------
            */

            $shiftId = null;

            if (
                $current['shift_id'] !== null
                &&
                $current['shift_id'] !== ''
            ) {

                $shiftId =
                    (int)$current['shift_id'];


                if ($shiftId <= 0) {

                    $shiftId = null;
                }
            }


            if (
                $shiftId !== null
                &&
                $current['shift_status'] !== 'open'
            ) {

                throw new RuntimeException(
                    'لا يمكن تعديل مسحوبة مرتبطة بوردية مغلقة.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | الموظف
            |--------------------------------------------------------------------------
            */

            $employeeId =
                (int)$current['employee_id'];


            if ($employeeId <= 0) {

                throw new RuntimeException(
                    'الموظف المرتبط بالمسحوبة غير صحيح.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | قفل حساب الموظف
            |--------------------------------------------------------------------------
            |
            | لا نقوم بإنشاء حساب جديد.
            |
            |--------------------------------------------------------------------------
            */

            $employeeAccountStmt = $pdo->prepare("
                SELECT

                    id,
                    account_no,
                    account_name,
                    account_type,
                    employee_id,
                    status

                FROM accounts

                WHERE employee_id = ?

                LIMIT 1

                FOR UPDATE
            ");

            $employeeAccountStmt->execute([
                $employeeId
            ]);

            $employeeAccount =
                $employeeAccountStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$employeeAccount) {

                throw new RuntimeException(
                    'لا يوجد حساب مالي للموظف. يجب إنشاء حساب الموظف أولًا.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | التحقق من نوع حساب الموظف
            |--------------------------------------------------------------------------
            */

            if (
                $employeeAccount['account_type']
                !==
                'employee'
            ) {

                throw new RuntimeException(
                    'الحساب المرتبط بالموظف ليس حساب موظف.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | التحقق من حالة الحساب
            |--------------------------------------------------------------------------
            */

            if (
                $employeeAccount['status']
                !==
                'active'
            ) {

                throw new RuntimeException(
                    'الحساب المالي للموظف غير نشط.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | حساب الخزينة
            |--------------------------------------------------------------------------
            */

            $cashAccount =
                accountingEnsureCashAccount(
                    $pdo
                );


            if (
                !$cashAccount
                ||
                empty($cashAccount['id'])
            ) {

                throw new RuntimeException(
                    'تعذر العثور على حساب الخزينة.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | وصف العملية
            |--------------------------------------------------------------------------
            */

            $employeeName =
                $withdrawal['employee_name']
                ??
                'الموظف';


            $transactionDescription =
                'مسحوبة موظف: ' .
                $employeeName .
                ' - ' .
                $withdrawalTypes[$withdrawalType];


            if ($description !== '') {

                $transactionDescription .=
                    ' - ' .
                    $description;
            }


            /*
            |--------------------------------------------------------------------------
            | تحديث سجل المسحوبة
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE employee_withdrawals

                SET

                    amount = ?,

                    withdrawal_type = ?,

                    description = ?

                WHERE id = ?
            ");

            $stmt->execute([

                $newAmount,

                $withdrawalType,

                $description !== ''
                    ? $description
                    : null,

                $id

            ]);


            /*
            |--------------------------------------------------------------------------
            | إعادة حساب بيانات الوردية
            |--------------------------------------------------------------------------
            */

            if ($shiftId !== null) {

                /*
                |--------------------------------------------------------------------------
                | إجمالي المسحوبات
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT
                        COALESCE(
                            SUM(amount),
                            0
                        )

                    FROM employee_withdrawals

                    WHERE shift_id = ?
                ");

                $stmt->execute([
                    $shiftId
                ]);

                $totalWithdrawals =
                    round(
                        (float)$stmt->fetchColumn(),
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | المبيعات النقدية
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT

                        COALESCE(
                            SUM(
                                CASE
                                    WHEN payment_method = 'cash'
                                    THEN total
                                    ELSE 0
                                END
                            ),
                            0
                        )

                    FROM sales

                    WHERE shift_id = ?
                ");

                $stmt->execute([
                    $shiftId
                ]);

                $cashSales =
                    round(
                        (float)$stmt->fetchColumn(),
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | النقد الافتتاحي
                |--------------------------------------------------------------------------
                */

                $openingCash =
                    round(
                        (float)$current['opening_cash'],
                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | النقد المتوقع
                |--------------------------------------------------------------------------
                */

                $expectedCash =
                    round(

                        $openingCash

                        +

                        $cashSales

                        -

                        $totalWithdrawals,

                        2
                    );


                /*
                |--------------------------------------------------------------------------
                | تحديث الوردية
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE shifts

                    SET

                        total_withdrawals = ?,

                        expected_cash = ?

                    WHERE id = ?

                    AND status = 'open'
                ");

                $stmt->execute([

                    $totalWithdrawals,

                    $expectedCash,

                    $shiftId

                ]);


                if ($stmt->rowCount() !== 1) {

                    throw new RuntimeException(
                        'تعذر تحديث بيانات الوردية.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | حذف القيد المحاسبي القديم
            |--------------------------------------------------------------------------
            |
            | accountingPostBalancedTransaction()
            | يخزن القيد في account_transactions.
            |
            | كل طرف من القيد يكون سجلًا مستقلًا،
            | لكن reference_type + reference_id
            | يربطان السجلات بالمسحوبة.
            |
            |--------------------------------------------------------------------------
            */

            $deleteTransactionStmt = $pdo->prepare("
                DELETE FROM account_transactions

                WHERE reference_type = ?

                AND reference_id = ?
            ");

            $deleteTransactionStmt->execute([

                'employee_withdrawal',

                $id

            ]);


            /*
            |--------------------------------------------------------------------------
            | إنشاء القيد الجديد
            |--------------------------------------------------------------------------
            |
            | الموظف = مدين
            | الخزينة = دائن
            |
            |--------------------------------------------------------------------------
            */

            $transactionNo =
                accountingPostBalancedTransaction(

                    $pdo,

                    'employee_withdrawal',

                    'employee_withdrawal',

                    $id,

                    [

                        [

                            'account_id' =>
                                (int)$employeeAccount['id'],

                            'debit' =>
                                $newAmount,

                            'credit' =>
                                0.00,

                            'description' =>
                                $transactionDescription

                        ],

                        [

                            'account_id' =>
                                (int)$cashAccount['id'],

                            'debit' =>
                                0.00,

                            'credit' =>
                                $newAmount,

                            'description' =>
                                $transactionDescription

                        ]

                    ],

                    $current['withdrawal_date'],

                    $transactionDescription,

                    accountingUserId()

                );


            /*
            |--------------------------------------------------------------------------
            | التأكد من إنشاء القيد
            |--------------------------------------------------------------------------
            */

            if (!$transactionNo) {

                throw new RuntimeException(
                    'تعذر إنشاء القيد المحاسبي.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | إتمام Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | إعادة التوجيه
            |--------------------------------------------------------------------------
            */

            header(
                'Location: view.php?id=' .
                $id .
                '&success=updated'
            );

            exit;
        }


    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Rollback
        |--------------------------------------------------------------------------
        */

        if (
            isset($pdo)
            &&
            $pdo instanceof PDO
            &&
            $pdo->inTransaction()
        ) {

            $pdo->rollBack();
        }


        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';

?>


<div class="page-header">

    <div>

        <h2>
            تعديل المسحوبة
        </h2>

        <p>
            تعديل بيانات المسحوبة وإعادة تحديث القيد المحاسبي والوردية.
        </p>

    </div>


    <div>

        <a
            href="view.php?id=<?= $id ?>"
            class="btn"
        >
            ← العودة
        </a>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<?php if ($withdrawal): ?>


<div class="card">

    <div class="card-header">

        <div>

            <h3>
                بيانات المسحوبة
            </h3>

            <p>

                رقم العملية:

                <strong>
                    #<?= (int)$withdrawal['id'] ?>
                </strong>

            </p>

        </div>

    </div>


    <div class="details-grid">


        <!-- الموظف -->

        <div>

            <strong>
                الموظف
            </strong>

            <span>

                <?= htmlspecialchars(
                    $withdrawal['employee_no'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

                -

                <?= htmlspecialchars(
                    $withdrawal['employee_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <!-- الوظيفة -->

        <div>

            <strong>
                الوظيفة
            </strong>

            <span>

                <?= htmlspecialchars(
                    $withdrawal['job_title']
                    ?: '—',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <!-- الوردية -->

        <div>

            <strong>
                الوردية
            </strong>

            <span>

                <?php if (
                    !empty($withdrawal['shift_id'])
                ): ?>

                    <?= htmlspecialchars(
                        $withdrawal['shift_no']
                        ?: '—',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                <?php else: ?>

                    بدون وردية

                <?php endif; ?>

            </span>

        </div>


        <!-- حالة الوردية -->

        <div>

            <strong>
                حالة الوردية
            </strong>

            <span>

                <?php if (
                    empty($withdrawal['shift_id'])
                ): ?>

                    <span class="badge">
                        بدون وردية
                    </span>

                <?php elseif (
                    $withdrawal['shift_status'] === 'open'
                ): ?>

                    <span class="badge badge-success">
                        مفتوحة
                    </span>

                <?php else: ?>

                    <span class="badge badge-danger">
                        مغلقة
                    </span>

                <?php endif; ?>

            </span>

        </div>


        <!-- تاريخ التسجيل -->

        <div>

            <strong>
                تاريخ التسجيل
            </strong>

            <span>

                <?= htmlspecialchars(
                    $withdrawal['created_at'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <!-- تاريخ المسحوبة -->

        <div>

            <strong>
                تاريخ المسحوبة
            </strong>

            <span>

                <?= htmlspecialchars(
                    $withdrawal['withdrawal_date'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        </div>


        <!-- المبلغ -->

        <div>

            <strong>
                المبلغ الحالي
            </strong>

            <span>

                <?= number_format(
                    (float)$withdrawal['amount'],
                    2
                ) ?>

            </span>

        </div>


    </div>


    <hr>


    <?php

    $selectedType =
        $_POST['withdrawal_type']
        ??
        $withdrawal['withdrawal_type'];

    $selectedAmount =
        $_POST['amount']
        ??
        $withdrawal['amount'];

    $selectedDescription =
        $_POST['description']
        ??
        $withdrawal['description']
        ??
        '';

    ?>


    <form
        method="POST"
        action="edit.php?id=<?= $id ?>"
    >

        <input
            type="hidden"
            name="id"
            value="<?= $id ?>"
        >


        <!-- نوع المسحوبة -->

        <div class="form-group">

            <label for="withdrawal_type">

                نوع المسحوبة

                <span style="color:#dc2626;">
                    *
                </span>

            </label>


            <select
                id="withdrawal_type"
                name="withdrawal_type"
                class="form-control"
                required
            >

                <?php foreach (
                    $withdrawalTypes
                    as $value => $label
                ): ?>

                    <option
                        value="<?= htmlspecialchars(
                            $value,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        <?= $selectedType === $value
                            ? 'selected'
                            : '' ?>
                    >

                        <?= htmlspecialchars(
                            $label,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- المبلغ -->

        <div class="form-group">

            <label for="amount">

                المبلغ

                <span style="color:#dc2626;">
                    *
                </span>

            </label>


            <input
                type="number"
                id="amount"
                name="amount"
                class="form-control"
                step="0.01"
                min="0.01"
                value="<?= htmlspecialchars(
                    (string)$selectedAmount,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >

        </div>


        <!-- الوصف -->

        <div class="form-group">

            <label for="description">

                الوصف / الملاحظات

            </label>


            <textarea
                id="description"
                name="description"
                class="form-control"
                rows="4"
                maxlength="500"
                placeholder="اكتب وصف المسحوبة إن وجد..."
            ><?= htmlspecialchars(
                (string)$selectedDescription,
                ENT_QUOTES,
                'UTF-8'
            ) ?></textarea>

        </div>


        <!-- التنبيه المحاسبي -->

        <div
            class="alert"
            style="
                background:#eff6ff;
                border:1px solid #bfdbfe;
                color:#1e40af;
                margin-top:20px;
            "
        >

            <strong>
                المعالجة المحاسبية:
            </strong>

            <br>

            عند تغيير مبلغ المسحوبة سيتم حذف القيد المحاسبي
            القديم المرتبط بهذه المسحوبة وإنشاء قيد جديد بالمبلغ الجديد.

            <br><br>

            <strong>
                الموظف:
            </strong>

            سيتم استخدام الحساب المالي الموجود للموظف،
            ولن يتم إنشاء حساب جديد.

            <?php if (
                !empty($withdrawal['shift_id'])
            ): ?>

                <br><br>

                <strong>
                    الوردية:
                </strong>

                سيتم إعادة حساب إجمالي المسحوبات
                والنقد المتوقع للوردية.

            <?php endif; ?>

        </div>


        <!-- الأزرار -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                💾 حفظ التعديلات
            </button>


            <a
                href="view.php?id=<?= $id ?>"
                class="btn"
            >
                إلغاء
            </a>

        </div>

    </form>

</div>


<?php endif; ?>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>

