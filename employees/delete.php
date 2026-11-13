<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'حذف موظف';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$error = '';
$employee = null;

try {

    /*
    |--------------------------------------------------------------------------
    | جلب الموظف
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            employee_no,
            full_name,
            phone,
            job_title,
            salary,
            salary_type,
            status
        FROM employees
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        throw new Exception('الموظف غير موجود.');
    }


    /*
    |--------------------------------------------------------------------------
    | تنفيذ الحذف
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | قفل الموظف
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
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

        $stmt->execute([$id]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            throw new Exception('الموظف غير موجود.');
        }


        /*
        |--------------------------------------------------------------------------
        | التحقق من وجود حساب مالي
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                account_no,
                account_name
            FROM accounts
            WHERE employee_id = ?
            AND account_type = 'employee'
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([$id]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | التحقق من وجود مسحوبات
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*)
            FROM employee_withdrawals
            WHERE employee_id = ?
        ");

        $stmt->execute([$id]);

        $withdrawalsCount = (int)$stmt->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | التحقق من وجود قيود محاسبية
        |--------------------------------------------------------------------------
        */

        $transactionsCount = 0;

        if ($account) {

            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*)
                FROM account_transactions
                WHERE account_id = ?
            ");

            $stmt->execute([
                (int)$account['id']
            ]);

            $transactionsCount =
                (int)$stmt->fetchColumn();
        }


        /*
        |--------------------------------------------------------------------------
        | منع الحذف إذا كان الموظف مرتبطًا بحركات مالية
        |--------------------------------------------------------------------------
        */

        if ($withdrawalsCount > 0) {

            throw new Exception(
                'لا يمكن حذف الموظف لأن لديه '
                . $withdrawalsCount
                . ' عملية مسحوبة مرتبطة به. '
                . 'قم بتعطيل الموظف بدلًا من حذفه.'
            );
        }


        if ($transactionsCount > 0) {

            throw new Exception(
                'لا يمكن حذف الموظف لأن حسابه المالي يحتوي على '
                . $transactionsCount
                . ' قيد محاسبي. '
                . 'قم بتعطيل الموظف بدلًا من حذفه.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | حذف الحساب المالي
        |--------------------------------------------------------------------------
        */

        if ($account) {

            $stmt = $pdo->prepare("
                DELETE FROM accounts
                WHERE id = ?
                AND employee_id = ?
                AND account_type = 'employee'
            ");

            $stmt->execute([
                (int)$account['id'],
                $id
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | حذف الموظف
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            DELETE FROM employees
            WHERE id = ?
        ");

        $stmt->execute([$id]);


        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                'تعذر حذف الموظف.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | تأكيد العملية
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        header(
            'Location: index.php?success=deleted'
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


require_once __DIR__ . '/../includes/header.php';

?>

<div class="page-header">

    <div>

        <h2>
            حذف موظف
        </h2>

        <p>
            تأكيد حذف الموظف من النظام.
        </p>

    </div>

    <div>

        <a
            href="index.php"
            class="btn"
        >
            ← الموظفين
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


<?php if ($employee): ?>

    <div class="card">

        <h2>
            تأكيد الحذف
        </h2>

        <p>
            هل أنت متأكد أنك تريد حذف الموظف التالي؟
        </p>


        <div
            style="
                margin:20px 0;
                padding:18px;
                background:#f9fafb;
                border:1px solid #e5e7eb;
                border-radius:10px;
            "
        >

            <strong>
                <?= htmlspecialchars(
                    $employee['full_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <br>

            رقم الموظف:

            <?= htmlspecialchars(
                $employee['employee_no'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            <?php if (!empty($employee['job_title'])): ?>

                <br>

                الوظيفة:

                <?= htmlspecialchars(
                    $employee['job_title'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            <?php endif; ?>

        </div>


        <div class="alert alert-warning">

            ⚠️ حذف الموظف عملية نهائية ولا يمكن التراجع عنها.

            <br>

            إذا كان الموظف لديه مسحوبات أو قيود محاسبية،
            فسيتم منع الحذف للحفاظ على سلامة البيانات المالية.

        </div>


        <form
            method="POST"
            action="delete.php?id=<?= (int)$id ?>"
        >

            <input
                type="hidden"
                name="id"
                value="<?= (int)$id ?>"
            >


            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-danger"
                    onclick="return confirm('هل أنت متأكد تمامًا من حذف هذا الموظف؟');"
                >
                    🗑 حذف الموظف
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

<?php endif; ?>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>