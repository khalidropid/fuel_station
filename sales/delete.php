<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'حذف عملية بيع';

$error = '';

/*
|--------------------------------------------------------------------------
| الحصول على رقم البيع
|--------------------------------------------------------------------------
*/

$saleId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($saleId <= 0) {

    header('Location: index.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| معالجة الحذف
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /*
        |--------------------------------------------------------------------------
        | بدء Transaction
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | جلب البيع مع قفل السجل
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.invoice_no,
                s.shift_id,
                s.pump_id,
                s.employee_id,
                s.customer_id,
                s.sale_date,
                s.payment_method,
                s.subtotal,
                s.discount,
                s.total,
                s.notes,

                sh.shift_no,
                sh.status AS shift_status,
                sh.employee_id AS shift_employee_id,
                sh.opening_cash,

                p.pump_no,
                p.name AS pump_name,
                p.tank_id

            FROM sales s

            INNER JOIN shifts sh
                ON sh.id = s.shift_id

            INNER JOIN pumps p
                ON p.id = s.pump_id

            WHERE s.id = ?

            LIMIT 1

            FOR UPDATE
        ");

        $stmt->execute([
            $saleId
        ]);

        $sale = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$sale) {

            throw new Exception(
                'عملية البيع غير موجودة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | التحقق من الوردية
        |--------------------------------------------------------------------------
        */

        if (
            $sale['shift_status']
            !==
            'open'
        ) {

            throw new Exception(
                'لا يمكن حذف عملية بيع من وردية مغلقة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | قفل الوردية
        |--------------------------------------------------------------------------
        |
        | نعيد القفل صراحة للتأكد من أن حسابات الوردية
        | لن تتغير أثناء عملية الحذف.
        |
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                shift_no,
                status,
                employee_id,
                opening_cash,
                total_sales,
                total_withdrawals,
                expected_cash
            FROM shifts
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $sale['shift_id']
        ]);

        $shift =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$shift) {

            throw new Exception(
                'الوردية المرتبطة بالبيع غير موجودة.'
            );
        }


        if (
            $shift['status']
            !==
            'open'
        ) {

            throw new Exception(
                'الوردية مغلقة، لا يمكن حذف البيع.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | جلب تفاصيل البيع
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                sale_id,
                fuel_type_id,
                quantity,
                unit_price,
                total
            FROM sale_items
            WHERE sale_id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $saleId
        ]);

        $saleItems =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        if (!$saleItems) {

            throw new Exception(
                'تفاصيل البيع غير موجودة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | حساب كمية الوقود
        |--------------------------------------------------------------------------
        |
        | البيع الحالي في add.php ينشئ عنصرًا واحدًا.
        | لكن نحسب مجموع العناصر حتى يكون الحذف أكثر أمانًا.
        |
        */

        $totalQuantity = 0;

        foreach ($saleItems as $item) {

            $totalQuantity +=
                (float)$item['quantity'];
        }


        $totalQuantity =
            round(
                $totalQuantity,
                2
            );


        if ($totalQuantity <= 0) {

            throw new Exception(
                'كمية الوقود في عملية البيع غير صحيحة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | قفل الطرمبة
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                pump_no,
                name,
                tank_id,
                status
            FROM pumps
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $sale['pump_id']
        ]);

        $pump =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$pump) {

            throw new Exception(
                'الطرمبة المرتبطة بالبيع غير موجودة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | التأكد من الخزان
        |--------------------------------------------------------------------------
        */

        if (
            empty($pump['tank_id'])
        ) {

            throw new Exception(
                'الطرمبة غير مرتبطة بخزان.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | قفل الخزان
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                capacity,
                current_volume,
                fuel_type_id
            FROM tanks
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $pump['tank_id']
        ]);

        $tank =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$tank) {

            throw new Exception(
                'الخزان المرتبط بالطرمبة غير موجود.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | التأكد من أن إضافة الكمية لن تتجاوز سعة الخزان
        |--------------------------------------------------------------------------
        */

        $currentVolume =
            (float)$tank['current_volume'];

        $capacity =
            (float)$tank['capacity'];


        $newVolume =
            round(
                $currentVolume
                +
                $totalQuantity,
                2
            );


        if (
            $capacity > 0
            &&
            $newVolume > $capacity
        ) {

            throw new Exception(
                'لا يمكن حذف البيع لأن إعادة كمية الوقود '
                .
                'ستتجاوز سعة الخزان. '
                .
                'السعة: '
                .
                number_format(
                    $capacity,
                    2
                )
                .
                ' لتر، '
                .
                'المخزون الحالي: '
                .
                number_format(
                    $currentVolume,
                    2
                )
                .
                ' لتر، '
                .
                'الكمية المعادة: '
                .
                number_format(
                    $totalQuantity,
                    2
                )
                .
                ' لتر.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | التحقق من حركة الخزان الأصلية
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                tank_id,
                movement_type,
                quantity,
                reference_id
            FROM tank_movements
            WHERE reference_id = ?
            AND tank_id = ?
            AND movement_type = 'sale'
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $saleId,
            $tank['id']
        ]);

        $tankMovement =
            $stmt->fetch(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | إذا كانت هناك حركة مخزون
        |--------------------------------------------------------------------------
        */

        if ($tankMovement) {

            $movementQuantity =
                abs(
                    (float)$tankMovement['quantity']
                );


            /*
            | يجب أن تطابق كمية حركة المخزون
            | كمية البيع.
            */

            if (
                round(
                    $movementQuantity,
                    2
                )
                !==
                round(
                    $totalQuantity,
                    2
                )
            ) {

                throw new Exception(
                    'كمية حركة الخزان لا تتطابق مع كمية البيع.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | قفل shift_pumps
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                shift_id,
                pump_id,
                opening_reading,
                closing_reading,
                total_liters,
                total_sales
            FROM shift_pumps
            WHERE shift_id = ?
            AND pump_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            $sale['shift_id'],
            $sale['pump_id']
        ]);

        $shiftPump =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$shiftPump) {

            throw new Exception(
                'الطرمبة غير مرتبطة بالوردية.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | التأكد من عدم جعل إجمالي الطرمبة سالبًا
        |--------------------------------------------------------------------------
        */

        $currentPumpLiters =
            (float)$shiftPump['total_liters'];

        $currentPumpSales =
            (float)$shiftPump['total_sales'];


        $newPumpLiters =
            round(
                $currentPumpLiters
                -
                $totalQuantity,
                2
            );


        $newPumpSales =
            round(
                $currentPumpSales
                -
                (float)$sale['total'],
                2
            );


        if ($newPumpLiters < 0) {

            throw new Exception(
                'لا يمكن حذف البيع لأن إجمالي لترات الطرمبة سيصبح سالبًا.'
            );
        }


        if ($newPumpSales < 0) {

            throw new Exception(
                'لا يمكن حذف البيع لأن إجمالي مبيعات الطرمبة سيصبح سالبًا.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | التعامل مع العميل في حالة البيع الآجل
        |--------------------------------------------------------------------------
        */

        $customer = null;

        $customerBalanceBeforeDelete = 0;

        $customerBalanceAfterDelete = 0;


        if (
            $sale['payment_method']
            ===
            'credit'
        ) {

            if (
                empty($sale['customer_id'])
            ) {

                throw new Exception(
                    'عملية البيع الآجل لا تحتوي على عميل.'
                );
            }


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
                    credit_limit,
                    balance,
                    status
                FROM customers
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                $sale['customer_id']
            ]);

            $customer =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$customer) {

                throw new Exception(
                    'العميل المرتبط بالبيع غير موجود.'
                );
            }


            $customerBalanceBeforeDelete =
                (float)$customer['balance'];


            /*
            |--------------------------------------------------------------------------
            | التأكد من وجود حركة العميل
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    customer_id,
                    transaction_type,
                    amount,
                    balance_before,
                    balance_after,
                    reference_id,
                    transaction_date
                FROM customer_transactions
                WHERE customer_id = ?
                AND reference_id = ?
                AND transaction_type = 'sale'
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                $sale['customer_id'],
                $saleId
            ]);

            $customerTransaction =
                $stmt->fetch(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | التحقق من حركة العميل
            |--------------------------------------------------------------------------
            */

            if ($customerTransaction) {

                $transactionAmount =
                    (float)$customerTransaction['amount'];


                if (
                    round(
                        $transactionAmount,
                        2
                    )
                    !==
                    round(
                        (float)$sale['total'],
                        2
                    )
                ) {

                    throw new Exception(
                        'قيمة حركة العميل لا تتطابق مع قيمة البيع.'
                    );
                }


                /*
                | الرصيد بعد الحذف
                */

                $customerBalanceAfterDelete =
                    round(
                        $customerBalanceBeforeDelete
                        -
                        (float)$sale['total'],
                        2
                    );


            } else {

                /*
                | إذا لم توجد الحركة، لا نحذف البيع
                | حتى لا نقوم بتعديل الرصيد بشكل غير آمن.
                */

                throw new Exception(
                    'لم يتم العثور على حركة العميل المرتبطة بهذه الفاتورة.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | منع الرصيد السالب
            |--------------------------------------------------------------------------
            */

            if (
                $customerBalanceAfterDelete
                <
                0
            ) {

                throw new Exception(
                    'لا يمكن حذف البيع لأن رصيد العميل سيصبح سالبًا.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | حذف حركة العميل
        |--------------------------------------------------------------------------
        */

        // Remove both sides of the sale journal in the same transaction.
        $deleteSaleJournal = $pdo->prepare(
            "DELETE FROM account_transactions
             WHERE reference_type = 'sale' AND reference_id = ?"
        );
        $deleteSaleJournal->execute([$saleId]);

        if (
            $sale['payment_method']
            ===
            'credit'
        ) {

            $stmt = $pdo->prepare("
                DELETE FROM customer_transactions
                WHERE customer_id = ?
                AND reference_id = ?
                AND transaction_type = 'sale'
            ");

            $stmt->execute([
                $sale['customer_id'],
                $saleId
            ]);


            /*
            |--------------------------------------------------------------------------
            | تحديث رصيد العميل
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE customers
                SET balance = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $customerBalanceAfterDelete,
                $sale['customer_id']
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | إعادة كمية الوقود إلى الخزان
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE tanks
            SET current_volume = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $newVolume,
            $tank['id']
        ]);


        /*
        |--------------------------------------------------------------------------
        | حركة عكسية للخزان
        |--------------------------------------------------------------------------
        |
        | لا نحذف حركة البيع الأصلية.
        | نسجل حركة reverse_sale للمحافظة على سجل المخزون.
        |
        */

        $stmt = $pdo->prepare("
            INSERT INTO tank_movements
            (
                tank_id,
                movement_type,
                quantity,
                reference_id,
                movement_date,
                notes
            )
            VALUES
            (
                ?,
                'reverse_sale',
                ?,
                ?,
                NOW(),
                ?
            )
        ");

        $stmt->execute([
            $tank['id'],
            $totalQuantity,
            $saleId,
            'عكس حذف بيع - فاتورة '
            .
            $sale['invoice_no']
        ]);


        /*
        |--------------------------------------------------------------------------
        | تحديث shift_pumps
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE shift_pumps
            SET
                total_liters = ?,
                total_sales = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $newPumpLiters,
            $newPumpSales,
            $shiftPump['id']
        ]);


        /*
        |--------------------------------------------------------------------------
        | تحديث إجمالي مبيعات الوردية
        |--------------------------------------------------------------------------
        */

        /*
        | بعد حذف البيع نعيد حساب إجمالي المبيعات
        | من جدول sales بدل الاعتماد على عملية طرح واحدة.
        |
        | هذا أكثر أمانًا إذا كانت هناك عمليات أخرى.
        */

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(
                    SUM(total),
                    0
                ) AS total_sales
            FROM sales
            WHERE shift_id = ?
            AND id <> ?
        ");

        $stmt->execute([
            $sale['shift_id'],
            $saleId
        ]);

        $shiftSalesRow =
            $stmt->fetch(PDO::FETCH_ASSOC);


        $newShiftSales =
            round(
                (float)(
                    $shiftSalesRow['total_sales']
                    ?? 0
                ),
                2
            );


        /*
        |--------------------------------------------------------------------------
        | حساب سحوبات الموظفين
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(
                    SUM(amount),
                    0
                ) AS total_withdrawals
            FROM employee_withdrawals
            WHERE shift_id = ?
        ");

        $stmt->execute([
            $sale['shift_id']
        ]);

        $withdrawalRow =
            $stmt->fetch(PDO::FETCH_ASSOC);


        $totalWithdrawals =
            round(
                (float)(
                    $withdrawalRow['total_withdrawals']
                    ?? 0
                ),
                2
            );


        /*
        |--------------------------------------------------------------------------
        | حساب المبيعات النقدية بعد الحذف
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
                ) AS cash_sales
            FROM sales
            WHERE shift_id = ?
            AND id <> ?
        ");

        $stmt->execute([
            $sale['shift_id'],
            $saleId
        ]);

        $cashSalesRow =
            $stmt->fetch(PDO::FETCH_ASSOC);


        $cashSales =
            round(
                (float)(
                    $cashSalesRow['cash_sales']
                    ?? 0
                ),
                2
            );


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
        | حذف تفاصيل البيع
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            DELETE FROM sale_items
            WHERE sale_id = ?
        ");

        $stmt->execute([
            $saleId
        ]);


        /*
        |--------------------------------------------------------------------------
        | حذف البيع
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            DELETE FROM sales
            WHERE id = ?
            AND shift_id = ?
        ");

        $stmt->execute([
            $saleId,
            $sale['shift_id']
        ]);


        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                'تعذر حذف عملية البيع.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | تحديث الوردية
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE shifts
            SET
                total_sales = ?,
                total_withdrawals = ?,
                expected_cash = ?
            WHERE id = ?
            AND status = 'open'
        ");

        $stmt->execute([
            $newShiftSales,
            $totalWithdrawals,
            $expectedCash,
            $sale['shift_id']
        ]);


        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                'تعذر تحديث إجماليات الوردية.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        /*
        |--------------------------------------------------------------------------
        | العودة للمبيعات
        |--------------------------------------------------------------------------
        */

        header(
            'Location: index.php?deleted=1'
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
    }
}


/*
|--------------------------------------------------------------------------
| عرض معلومات البيع قبل الحذف
|--------------------------------------------------------------------------
*/

$salePreview = null;

try {

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.invoice_no,
            s.sale_date,
            s.payment_method,
            s.subtotal,
            s.discount,
            s.total,
            s.customer_id,

            p.pump_no,
            p.name AS pump_name,

            e.employee_no,
            e.full_name AS employee_name,

            sh.shift_no

        FROM sales s

        INNER JOIN pumps p
            ON p.id = s.pump_id

        INNER JOIN employees e
            ON e.id = s.employee_id

        INNER JOIN shifts sh
            ON sh.id = s.shift_id

        WHERE s.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $saleId
    ]);

    $salePreview =
        $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


require_once __DIR__ . '/../includes/header.php';

?>


<div class="page-header">

    <div>

        <h2>
            حذف عملية بيع
        </h2>

        <p>
            حذف العملية وعكس تأثيرها على المخزون والوردية والعميل.
        </p>

    </div>

    <div>

        <a
            href="index.php"
            class="btn"
        >
            العودة للمبيعات
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


<?php if ($salePreview): ?>

    <div class="card">

        <h3>
            تأكيد حذف عملية البيع
        </h3>


        <div class="sale-delete-info">

            <p>
                <strong>
                    رقم الفاتورة:
                </strong>

                <?= htmlspecialchars(
                    $salePreview['invoice_no'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>


            <p>
                <strong>
                    الوردية:
                </strong>

                <?= htmlspecialchars(
                    $salePreview['shift_no'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>


            <p>
                <strong>
                    الطرمبة:
                </strong>

                <?= htmlspecialchars(
                    $salePreview['pump_no']
                    . ' - '
                    . $salePreview['pump_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>


            <p>
                <strong>
                    الموظف:
                </strong>

                <?= htmlspecialchars(
                    $salePreview['employee_no']
                    . ' - '
                    . $salePreview['employee_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>


            <p>
                <strong>
                    طريقة الدفع:
                </strong>

                <?php

                $paymentLabels = [
                    'cash'   => 'نقدي',
                    'card'   => 'بطاقة',
                    'credit' => 'آجل'
                ];

                echo htmlspecialchars(
                    $paymentLabels[
                        $salePreview['payment_method']
                    ]
                    ??
                    $salePreview['payment_method'],
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>

            </p>


            <p>
                <strong>
                    المجموع:
                </strong>

                <?= number_format(
                    (float)$salePreview['subtotal'],
                    2
                ) ?>
            </p>


            <p>
                <strong>
                    الخصم:
                </strong>

                <?= number_format(
                    (float)$salePreview['discount'],
                    2
                ) ?>
            </p>


            <p>
                <strong>
                    الإجمالي:
                </strong>

                <?= number_format(
                    (float)$salePreview['total'],
                    2
                ) ?>
            </p>


            <div class="alert alert-danger">

                <strong>
                    تحذير:
                </strong>

                سيتم عكس تأثير العملية على:

                <ul>

                    <li>
                        مخزون الخزان
                    </li>

                    <li>
                        حركة المخزون
                    </li>

                    <li>
                        إجمالي الطرمبة
                    </li>

                    <li>
                        إجمالي الوردية
                    </li>

                    <?php if (
                        $salePreview['payment_method']
                        ===
                        'credit'
                    ): ?>

                        <li>
                            رصيد العميل
                        </li>

                        <li>
                            حركة العميل
                        </li>

                    <?php endif; ?>

                </ul>

                ولا يمكن التراجع عن الحذف من خلال هذه الصفحة.

            </div>


            <form
                method="POST"
                onsubmit="return confirmDelete();"
            >

                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$saleId ?>"
                >


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-danger"
                    >
                        نعم، حذف العملية
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

    </div>

<?php else: ?>

    <div class="card">

        <div class="alert alert-danger">

            عملية البيع غير موجودة.

        </div>


        <a
            href="index.php"
            class="btn"
        >
            العودة للمبيعات
        </a>

    </div>

<?php endif; ?>


<script>

function confirmDelete() {

    return confirm(
        'هل أنت متأكد من حذف عملية البيع؟\n\n'
        +
        'سيتم عكس المخزون وإجماليات الوردية '
        +
        'ورصيد العميل إن كانت العملية آجلة.'
    );
}

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
