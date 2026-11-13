<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'إضافة مسحوبة موظف';

$error = '';

/*
|--------------------------------------------------------------------------
| القيم الافتراضية
|--------------------------------------------------------------------------
*/

$employeeId = 0;
$shiftId = '';
$amount = '';
$withdrawalDate = date('Y-m-d\TH:i');
$withdrawalType = 'personal';
$description = '';

/*
|--------------------------------------------------------------------------
| أنواع المسحوبات
|--------------------------------------------------------------------------
*/

$withdrawalTypes = [
    'personal'       => 'سحب شخصي',
    'advance_salary' => 'سلفة راتب',
    'expense'        => 'مصروف',
    'other'          => 'أخرى'
];


/*
|--------------------------------------------------------------------------
| جلب الموظفين
|--------------------------------------------------------------------------
*/

$employeesStmt = $pdo->query("
    SELECT
        id,
        employee_no,
        full_name,
        job_title,
        status
    FROM employees
    WHERE status IN ('active', 'vacation')
    ORDER BY full_name ASC
");

$employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| جلب الورديات المفتوحة
|--------------------------------------------------------------------------
*/

$shifts = [];

try {

    $shiftsStmt = $pdo->query("
        SELECT
            id,
            shift_no,
            shift_date,
            status
        FROM shifts
        WHERE status = 'open'
        ORDER BY id DESC
    ");

    $shifts = $shiftsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $shifts = [];
}


/*
|--------------------------------------------------------------------------
| معالجة POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employeeId =
        (int)($_POST['employee_id'] ?? 0);

    $shiftId =
        trim($_POST['shift_id'] ?? '');

    $amount =
        trim($_POST['amount'] ?? '');

    $withdrawalDate =
        trim($_POST['withdrawal_date'] ?? '');

    $withdrawalType =
        $_POST['withdrawal_type'] ?? 'personal';

    $description =
        trim($_POST['description'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | التحقق من البيانات
    |--------------------------------------------------------------------------
    */

    if ($employeeId <= 0) {

        $error = 'يرجى اختيار الموظف.';

    } elseif (
        $amount === ''
        || !is_numeric($amount)
        || (float)$amount <= 0
    ) {

        $error = 'يجب إدخال مبلغ صحيح أكبر من صفر.';

    } elseif (
        !array_key_exists(
            $withdrawalType,
            $withdrawalTypes
        )
    ) {

        $error = 'نوع المسحوبة غير صحيح.';

    } elseif ($withdrawalDate === '') {

        $error = 'تاريخ المسحوبة مطلوب.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | تحويل المبلغ
            |--------------------------------------------------------------------------
            */

            $amountValue =
                round(
                    (float)$amount,
                    2
                );


            if ($amountValue <= 0) {

                throw new RuntimeException(
                    'مبلغ المسحوبة يجب أن يكون أكبر من صفر.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | التحقق من التاريخ
            |--------------------------------------------------------------------------
            */

            $dateObject =
                DateTime::createFromFormat(
                    'Y-m-d\TH:i',
                    $withdrawalDate
                );

            if (!$dateObject) {

                $dateObject =
                    DateTime::createFromFormat(
                        'Y-m-d H:i',
                        $withdrawalDate
                    );
            }

            if (!$dateObject) {

                throw new RuntimeException(
                    'تاريخ المسحوبة غير صحيح.'
                );
            }


            $transactionDate =
                $dateObject->format(
                    'Y-m-d H:i:s'
                );


            /*
            |--------------------------------------------------------------------------
            | بدء Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | قفل الموظف والتحقق منه
            |--------------------------------------------------------------------------
            */

            $employeeStmt = $pdo->prepare("
                SELECT
                    id,
                    employee_no,
                    full_name,
                    status
                FROM employees
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $employeeStmt->execute([
                $employeeId
            ]);

            $employee =
                $employeeStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$employee) {

                throw new RuntimeException(
                    'الموظف غير موجود.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | التحقق من حالة الموظف
            |--------------------------------------------------------------------------
            */

            if (
                !in_array(
                    $employee['status'],
                    ['active', 'vacation'],
                    true
                )
            ) {

                throw new RuntimeException(
                    'لا يمكن تسجيل مسحوبة لموظف غير نشط.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | معالجة رقم الوردية
            |--------------------------------------------------------------------------
            */

            $realShiftId = null;

            if ($shiftId !== '') {

                if (!ctype_digit($shiftId)) {

                    throw new RuntimeException(
                        'رقم الوردية غير صحيح.'
                    );
                }

                $realShiftId =
                    (int)$shiftId;


                if ($realShiftId <= 0) {

                    $realShiftId = null;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | التحقق من الوردية
            |--------------------------------------------------------------------------
            */

            if ($realShiftId !== null) {

                $shiftStmt = $pdo->prepare("
                    SELECT
                        id,
                        shift_no,
                        shift_date,
                        status
                    FROM shifts
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $shiftStmt->execute([
                    $realShiftId
                ]);

                $shift =
                    $shiftStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$shift) {

                    throw new RuntimeException(
                        'الوردية المحددة غير موجودة.'
                    );
                }


                if (
                    isset($shift['status'])
                    &&
                    $shift['status'] !== 'open'
                ) {

                    throw new RuntimeException(
                        'لا يمكن تسجيل المسحوبة على وردية مغلقة.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | الحصول على حساب الموظف الموجود
            |--------------------------------------------------------------------------
            |
            | مهم:
            | لا نقوم بإنشاء حساب جديد هنا.
            |
            | لكل موظف حساب واحد فقط بسبب:
            |
            | uq_accounts_employee_id
            |
            |--------------------------------------------------------------------------
            */

            $employeeAccountStmt = $pdo->prepare("
                SELECT
                    id,
                    employee_id
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
                    'لا يوجد حساب مالي للموظف "' .
                    $employee['full_name'] .
                    '". يجب إنشاء الحساب المالي للموظف أولًا.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | الحصول على حساب الخزينة
            |--------------------------------------------------------------------------
            */

            $cashAccount =
                accountingEnsureCashAccount(
                    $pdo
                );


            if (
                !$cashAccount
                || empty($cashAccount['id'])
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

            $transactionDescription =
                'مسحوبة موظف: ' .
                $employee['full_name'] .
                ' - ' .
                $withdrawalTypes[$withdrawalType];


            if ($description !== '') {

                $transactionDescription .=
                    ' - ' .
                    $description;
            }


            /*
            |--------------------------------------------------------------------------
            | إنشاء سجل المسحوبة
            |--------------------------------------------------------------------------
            */

            $insertStmt = $pdo->prepare("
                INSERT INTO employee_withdrawals
                (
                    employee_id,
                    shift_id,
                    amount,
                    withdrawal_date,
                    withdrawal_type,
                    description,
                    created_by,
                    created_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW()
                )
            ");


            $insertStmt->execute([

                $employeeId,

                $realShiftId,

                $amountValue,

                $transactionDate,

                $withdrawalType,

                $description !== ''
                    ? $description
                    : null,

                accountingUserId()

            ]);


            $withdrawalId =
                (int)$pdo->lastInsertId();


            if ($withdrawalId <= 0) {

                throw new RuntimeException(
                    'تعذر إنشاء سجل المسحوبة.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | إنشاء القيد المحاسبي
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

                    $withdrawalId,

                    [

                        [
                            'account_id' =>
                                (int)$employeeAccount['id'],

                            'debit' =>
                                $amountValue,

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
                                $amountValue,

                            'description' =>
                                $transactionDescription
                        ]

                    ],

                    $transactionDate,

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
            | إتمام العملية
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | الانتقال إلى صفحة العرض
            |--------------------------------------------------------------------------
            */

            header(
                'Location: view.php?id=' .
                $withdrawalId .
                '&success=created'
            );

            exit;


        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Rollback
            |--------------------------------------------------------------------------
            */

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | رسالة الخطأ
            |--------------------------------------------------------------------------
            */

            $error =
                $e->getMessage();
        }
    }
}


require_once __DIR__ . '/../includes/header.php';

?>


<div class="page-header">

    <div>

        <h2>
            إضافة مسحوبة موظف
        </h2>

        <p>
            تسجيل مسحوبة مالية وربطها تلقائيًا بالحساب المالي للموظف.
        </p>

    </div>


    <div>

        <a
            href="index.php"
            class="btn"
        >
            ← المسحوبات
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


<div class="card">

    <div class="card-header">

        <div>

            <h2>
                بيانات المسحوبة
            </h2>

            <p>
                أدخل بيانات العملية ثم اضغط حفظ.
            </p>

        </div>

    </div>


    <form
        method="POST"
        action="create.php"
    >

        <div class="form-grid">


            <!-- الموظف -->

            <div class="form-group">

                <label for="employee_id">
                    الموظف
                    <span style="color:#dc2626">*</span>
                </label>

                <select
                    name="employee_id"
                    id="employee_id"
                    class="form-control"
                    required
                >

                    <option value="">
                        اختر الموظف
                    </option>

                    <?php foreach ($employees as $employee): ?>

                        <option
                            value="<?= (int)$employee['id'] ?>"
                            <?= $employeeId === (int)$employee['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $employee['employee_no'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            -

                            <?= htmlspecialchars(
                                $employee['full_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <?php if (
                                !empty($employee['job_title'])
                            ): ?>

                                -

                                <?= htmlspecialchars(
                                    $employee['job_title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- الوردية -->

            <div class="form-group">

                <label for="shift_id">
                    الوردية
                </label>

                <select
                    name="shift_id"
                    id="shift_id"
                    class="form-control"
                >

                    <option value="">
                        بدون تحديد وردية
                    </option>

                    <?php foreach ($shifts as $shift): ?>

                        <option
                            value="<?= (int)$shift['id'] ?>"
                            <?= $shiftId === (string)$shift['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?php if (
                                !empty($shift['shift_no'])
                            ): ?>

                                <?= htmlspecialchars(
                                    $shift['shift_no'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                -

                            <?php endif; ?>

                            <?= htmlspecialchars(
                                $shift['shift_date'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if (!$shifts): ?>

                    <small style="color:#6b7280;">
                        لا توجد ورديات مفتوحة حاليًا.
                    </small>

                <?php endif; ?>

            </div>


            <!-- المبلغ -->

            <div class="form-group">

                <label for="amount">
                    مبلغ المسحوبة
                    <span style="color:#dc2626">*</span>
                </label>

                <input
                    type="number"
                    name="amount"
                    id="amount"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $amount,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    min="0.01"
                    step="0.01"
                    placeholder="0.00"
                    required
                >

            </div>


            <!-- التاريخ -->

            <div class="form-group">

                <label for="withdrawal_date">
                    تاريخ ووقت المسحوبة
                    <span style="color:#dc2626">*</span>
                </label>

                <input
                    type="datetime-local"
                    name="withdrawal_date"
                    id="withdrawal_date"
                    class="form-control"
                    value="<?= htmlspecialchars(
                        $withdrawalDate,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >

            </div>


            <!-- النوع -->

            <div class="form-group">

                <label for="withdrawal_type">
                    نوع المسحوبة
                    <span style="color:#dc2626">*</span>
                </label>

                <select
                    name="withdrawal_type"
                    id="withdrawal_type"
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
                            <?= $withdrawalType === $value
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


        </div>


        <!-- الوصف -->

        <div class="form-group">

            <label for="description">
                الوصف / ملاحظات
            </label>

            <textarea
                name="description"
                id="description"
                class="form-control"
                rows="4"
                maxlength="500"
                placeholder="اكتب سبب المسحوبة أو أي ملاحظات إضافية..."
            ><?= htmlspecialchars(
                $description,
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

            سيتم تسجيل المسحوبة على حساب الموظف المالي الموجود،
            وسيتم خصم المبلغ من حساب الخزينة.

            <br><br>

            <strong>
                ملاحظة:
            </strong>

            يجب أن يكون للموظف حساب مالي موجود مسبقًا.
            لا يتم إنشاء حساب جديد أثناء تسجيل المسحوبة.

        </div>


        <!-- الإجراءات -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                💾 حفظ المسحوبة
            </button>


            <a
                href="index.php"
                class="btn"
            >
                إلغاء
            </a>

        </div>

    </form>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
