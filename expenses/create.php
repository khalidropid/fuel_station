<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'تسجيل مصروف';
$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| رسالة النجاح بعد إعادة تحميل الصفحة
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['success'])
    && $_GET['success'] === 'created'
) {
    $success = 'تم تسجيل المصروف والقيد المحاسبي المتوازن.';
}

/*
|--------------------------------------------------------------------------
| معالجة النموذج
|--------------------------------------------------------------------------
*/

try {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $date = trim(
            $_POST['expense_date'] ?? date('Y-m-d\TH:i')
        );

        $category = trim(
            $_POST['category'] ?? ''
        );

        $amount = (float)($_POST['amount'] ?? 0);

        $description = trim(
            $_POST['description'] ?? ''
        );

        $employeeId = (int)($_POST['employee_id'] ?? 0);


        /*
        |--------------------------------------------------------------------------
        | التحقق من البيانات
        |--------------------------------------------------------------------------
        */

        if ($category === '') {
            throw new Exception('أدخل تصنيف المصروف.');
        }

        if ($amount <= 0) {
            throw new Exception(
                'قيمة المصروف يجب أن تكون أكبر من صفر.'
            );
        }

        $dateDb = str_replace('T', ' ', $date);

        if (strtotime($dateDb) === false) {
            throw new Exception('تاريخ المصروف غير صحيح.');
        }


        /*
        |--------------------------------------------------------------------------
        | بدء العملية
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | إنشاء رقم المصروف
        |--------------------------------------------------------------------------
        */

        $expenseNo = null;

        for ($i = 0; $i < 10; $i++) {

            $candidate =
                'EXP-' .
                date('YmdHis') .
                '-' .
                random_int(100, 999);

            $checkStmt = $pdo->prepare("
                SELECT id
                FROM expenses
                WHERE expense_no = ?
                LIMIT 1
            ");

            $checkStmt->execute([$candidate]);

            if (!$checkStmt->fetch()) {
                $expenseNo = $candidate;
                break;
            }
        }

        if (!$expenseNo) {
            throw new Exception('تعذر إنشاء رقم المصروف.');
        }


        /*
        |--------------------------------------------------------------------------
        | تسجيل المصروف
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO expenses
            (
                expense_no,
                expense_date,
                category,
                amount,
                description,
                employee_id,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $expenseNo,
            $dateDb,
            $category,
            $amount,
            $description !== '' ? $description : null,
            $employeeId > 0 ? $employeeId : null,
            accountingUserId()
        ]);

        $expenseId = (int)$pdo->lastInsertId();


        /*
        |--------------------------------------------------------------------------
        | الحصول على حساب المصروف وحساب الخزينة
        |--------------------------------------------------------------------------
        */

        $code = 'EXP-' . strtoupper(
            substr(
                hash(
                    'sha256',
                    mb_strtolower($category, 'UTF-8')
                ),
                0,
                10
            )
        );

        $expenseAccount = accountingEnsureAccount(
            $pdo,
            $code,
            'مصروف - ' . $category,
            'expense',
            null,
            null,
            null,
            'حساب مصروف تلقائي: ' . $category
        );

        $cash = accountingEnsureCashAccount($pdo);


        /*
        |--------------------------------------------------------------------------
        | إنشاء القيد المحاسبي
        |--------------------------------------------------------------------------
        |
        | حساب المصروف = مدين
        | حساب الخزينة = دائن
        |--------------------------------------------------------------------------
        */

        accountingPostBalancedTransaction(
            $pdo,
            'expense',
            'expense',
            $expenseId,
            [
                [
                    'account_id' => (int)$expenseAccount['id'],
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'مصروف ' . $category . ' - ' . $expenseNo
                ],
                [
                    'account_id' => (int)$cash['id'],
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'دفع مصروف من الخزينة - ' . $expenseNo
                ]
            ],
            $dateDb,
            'مصروف ' . $category . ' - ' . $expenseNo,
            accountingUserId(),
            $expenseNo
        );


        /*
        |--------------------------------------------------------------------------
        | تأكيد العملية وإعادة تحميل الصفحة
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        header(
            'Location: ' .
            $_SERVER['PHP_SELF'] .
            '?success=created'
        );

        exit;
    }

} catch (Throwable $e) {

    if (
        $pdo instanceof PDO
        && $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    $error = $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| جلب الموظفين
|--------------------------------------------------------------------------
*/

$employees = $pdo->query("
    SELECT
        id,
        employee_no,
        full_name
    FROM employees
    WHERE status = 'active'
    ORDER BY full_name
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| جلب آخر المصروفات
|--------------------------------------------------------------------------
*/

$expenses = $pdo->query("
    SELECT
        x.*,
        e.full_name AS employee_name
    FROM expenses x
    LEFT JOIN employees e ON e.id = x.employee_id
    ORDER BY x.expense_date DESC, x.id DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>
        <h2>تسجيل مصروف</h2>
        <p>
            المصروف مدين والخزينة دائنة في قيد واحد متوازن.
        </p>
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


<?php if ($success): ?>

    <div class="alert alert-success">
        <?= htmlspecialchars(
            $success,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </div>

<?php endif; ?>


<div class="card">

    <form method="post" action="<?= htmlspecialchars(
        $_SERVER['PHP_SELF'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>">

        <div class="form-group">

            <label for="expense_date">
                التاريخ
            </label>

            <input
                type="datetime-local"
                id="expense_date"
                name="expense_date"
                value="<?= htmlspecialchars(
                    $_POST['expense_date'] ?? date('Y-m-d\TH:i'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="category">
                تصنيف المصروف
            </label>

            <input
                type="text"
                id="category"
                name="category"
                value="<?= htmlspecialchars(
                    $_POST['category'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                placeholder="كهرباء، صيانة، نقل..."
                required
            >

        </div>


        <div class="form-group">

            <label for="amount">
                المبلغ
            </label>

            <input
                type="number"
                id="amount"
                name="amount"
                step="0.01"
                min="0.01"
                value="<?= htmlspecialchars(
                    $_POST['amount'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="employee_id">
                الموظف المرتبط (اختياري)
            </label>

            <select
                id="employee_id"
                name="employee_id"
            >

                <option value="0">
                    بدون موظف
                </option>

                <?php foreach ($employees as $employee): ?>

                    <option
                        value="<?= (int)$employee['id'] ?>"
                        <?= (int)($_POST['employee_id'] ?? 0) === (int)$employee['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= htmlspecialchars(
                            $employee['employee_no'] .
                            ' - ' .
                            $employee['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div class="form-group">

            <label for="description">
                الوصف
            </label>

            <textarea
                id="description"
                name="description"
            ><?= htmlspecialchars(
                $_POST['description'] ?? '',
                ENT_QUOTES,
                'UTF-8'
            ) ?></textarea>

        </div>


        <button
            type="submit"
            class="btn btn-primary"
        >
            حفظ المصروف
        </button>

    </form>

</div>


<div class="card">

    <h3>آخر المصروفات</h3>

    <table class="table">

        <thead>
            <tr>
                <th>الرقم</th>
                <th>التاريخ</th>
                <th>التصنيف</th>
                <th>المبلغ</th>
                <th>الموظف</th>
            </tr>
        </thead>

        <tbody>

            <?php if (!$expenses): ?>

                <tr>
                    <td colspan="5">
                        لا توجد مصروفات مسجلة.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($expenses as $expense): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars(
                                $expense['expense_no'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $expense['expense_date'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $expense['category'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>
                            <?= number_format(
                                (float)$expense['amount'],
                                2
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $expense['employee_name'] ?? '—',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
