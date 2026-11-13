<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| رقم المسحوبة
|--------------------------------------------------------------------------
*/

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);


if ($id <= 0) {

    header('Location: index.php');

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | بدء Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | جلب المسحوبة
    |--------------------------------------------------------------------------
    |
    | مهم:
    |
    | استخدمنا LEFT JOIN بدل INNER JOIN
    | لأن shift_id يمكن أن يكون NULL.
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

            s.id AS real_shift_id,
            s.shift_no,
            s.status AS shift_status,
            s.opening_cash

        FROM employee_withdrawals w

        INNER JOIN employees e
            ON e.id = w.employee_id

        LEFT JOIN shifts s
            ON s.id = w.shift_id

        WHERE w.id = ?

        LIMIT 1

        FOR UPDATE
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
    | التحقق من الوردية
    |--------------------------------------------------------------------------
    |
    | إذا كانت المسحوبة مرتبطة بورديّة:
    | يجب أن تكون الوردية مفتوحة.
    |
    | إذا لم تكن مرتبطة بورديّة:
    | يسمح بالحذف.
    |
    |--------------------------------------------------------------------------
    */

    $shiftId =
        !empty($withdrawal['shift_id'])
            ? (int)$withdrawal['shift_id']
            : null;


    if ($shiftId !== null) {

        if (empty($withdrawal['real_shift_id'])) {

            throw new RuntimeException(
                'الوردية المرتبطة بالمسحوبة غير موجودة.'
            );
        }


        if (
            $withdrawal['shift_status']
            !== 'open'
        ) {

            throw new RuntimeException(
                'لا يمكن حذف مسحوبة مرتبطة بوردية مغلقة.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | حذف القيد المحاسبي
    |--------------------------------------------------------------------------
    |
    | القيد الخاص بالمسحوبة يكون:
    |
    | reference_type = employee_withdrawal
    | reference_id   = رقم المسحوبة
    |
    |--------------------------------------------------------------------------
    */

    $accountTransactionStmt = $pdo->prepare("
        DELETE FROM account_transactions

        WHERE reference_type = ?

        AND reference_id = ?
    ");

    $accountTransactionStmt->execute([

        'employee_withdrawal',

        $id

    ]);


    /*
    |--------------------------------------------------------------------------
    | حذف المسحوبة
    |--------------------------------------------------------------------------
    */

    $deleteStmt = $pdo->prepare("
        DELETE FROM employee_withdrawals

        WHERE id = ?
    ");

    $deleteStmt->execute([
        $id
    ]);


    if ($deleteStmt->rowCount() !== 1) {

        throw new RuntimeException(
            'تعذر حذف المسحوبة.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | إذا لم تكن المسحوبة مرتبطة بورديّة
    |--------------------------------------------------------------------------
    |
    | لا يوجد شيء نعيد حسابه في shifts.
    |
    |--------------------------------------------------------------------------
    */

    if ($shiftId === null) {

        $pdo->commit();


        header(
            'Location: index.php?success=deleted'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | إعادة حساب إجمالي مسحوبات الوردية
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
    | حساب المبيعات النقدية
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
    | الحصول على الرصيد الافتتاحي للوردية
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            opening_cash,
            status
        FROM shifts
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        $shiftId
    ]);


    $shift =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$shift) {

        throw new RuntimeException(
            'الوردية غير موجودة.'
        );
    }


    if ($shift['status'] !== 'open') {

        throw new RuntimeException(
            'الوردية أصبحت مغلقة ولا يمكن تحديث إجمالياتها.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | حساب النقد المتوقع
    |--------------------------------------------------------------------------
    */

    $openingCash =
        (float)$shift['opening_cash'];


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

    $updateShiftStmt = $pdo->prepare("
        UPDATE shifts

        SET

            total_withdrawals = ?,

            expected_cash = ?

        WHERE id = ?

        AND status = 'open'
    ");

    $updateShiftStmt->execute([

        $totalWithdrawals,

        $expectedCash,

        $shiftId

    ]);


    /*
    |--------------------------------------------------------------------------
    | لا نعتمد على rowCount()
    |--------------------------------------------------------------------------
    |
    | MySQL قد يعيد 0 إذا كانت القيم الجديدة
    | مساوية للقيم القديمة، وهذا ليس خطأ.
    |
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | إتمام العملية
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | العودة إلى قائمة المسحوبات
    |--------------------------------------------------------------------------
    */

    header(
        'Location: index.php?success=deleted'
    );

    exit;


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


    /*
    |--------------------------------------------------------------------------
    | عرض الخطأ
    |--------------------------------------------------------------------------
    */

    require_once __DIR__ . '/../includes/header.php';

    ?>


    <div class="page-header">

        <div>

            <h2>
                تعذر حذف المسحوبة
            </h2>

            <p>
                حدث خطأ أثناء محاولة حذف عملية السحب.
            </p>

        </div>

    </div>


    <div class="alert alert-danger">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>


    <div class="form-actions">

        <a
            href="view.php?id=<?= $id ?>"
            class="btn"
        >
            العودة للمسحوبة
        </a>


        <a
            href="index.php"
            class="btn"
        >
            المسحوبات
        </a>

    </div>


    <?php

    require_once __DIR__ . '/../includes/footer.php';

}
