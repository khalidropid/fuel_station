<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'تعديل العميل';

$error = '';

$customerId =
    (int) (
        $_GET['id']
        ??
        $_POST['id']
        ??
        0
    );

$customer = null;

$account = null;


/*
|--------------------------------------------------------------------------
| حماية النصوص
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| التحقق من رقم العميل
|--------------------------------------------------------------------------
*/

if ($customerId <= 0) {

    $error =
        'رقم العميل غير صحيح أو لم يتم تحديد العميل.';
}


/*
|--------------------------------------------------------------------------
| تحميل بيانات العميل
|--------------------------------------------------------------------------
*/

if ($error === '') {

    try {

        $stmt =
            $pdo->prepare("
                SELECT
                    id,
                    customer_no,
                    full_name,
                    phone,
                    address,
                    credit_limit,
                    balance,
                    status,
                    notes,
                    created_at
                FROM customers
                WHERE id = ?
                LIMIT 1
            ");


        $stmt->execute([
            $customerId
        ]);


        $customer =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$customer) {

            $error =
                'العميل غير موجود.';
        }


    } catch (PDOException $e) {

        $error =
            'حدث خطأ في قاعدة البيانات أثناء تحميل بيانات العميل.';
    }
}


/*
|--------------------------------------------------------------------------
| تحميل الحساب المالي
|--------------------------------------------------------------------------
*/

if (
    $error === ''
    &&
    $customer
) {

    try {

        $accountStmt =
            $pdo->prepare("
                SELECT
                    id,
                    account_no,
                    account_name,
                    account_type,
                    customer_id,
                    opening_balance,
                    status,
                    notes
                FROM accounts
                WHERE customer_id = ?
                LIMIT 1
            ");


        $accountStmt->execute([
            $customerId
        ]);


        $account =
            $accountStmt->fetch(
                PDO::FETCH_ASSOC
            );


    } catch (PDOException $e) {

        $error =
            'تعذر تحميل الحساب المالي للعميل.';
    }
}


/*
|--------------------------------------------------------------------------
| القيم الافتراضية للنموذج
|--------------------------------------------------------------------------
*/

$customerNo =
    $customer['customer_no'] ?? '';

$fullName =
    $customer['full_name'] ?? '';

$phone =
    $customer['phone'] ?? '';

$address =
    $customer['address'] ?? '';

$creditLimit =
    $customer['credit_limit'] ?? '0';

$balance =
    $customer['balance'] ?? '0';

$status =
    $customer['status'] ?? 'active';

$notes =
    $customer['notes'] ?? '';


/*
|--------------------------------------------------------------------------
| معالجة التعديل
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    $customer
) {

    $customerNo =
        trim(
            $_POST['customer_no'] ?? ''
        );


    $fullName =
        trim(
            $_POST['full_name'] ?? ''
        );


    $phone =
        trim(
            $_POST['phone'] ?? ''
        );


    $address =
        trim(
            $_POST['address'] ?? ''
        );


    $creditLimit =
        trim(
            $_POST['credit_limit'] ?? '0'
        );


    $status =
        trim(
            $_POST['status'] ?? 'active'
        );


    $notes =
        trim(
            $_POST['notes'] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | التحقق
    |--------------------------------------------------------------------------
    */

    if ($customerNo === '') {

        $error =
            'رقم العميل مطلوب.';

    } elseif ($fullName === '') {

        $error =
            'اسم العميل مطلوب.';

    } elseif (
        !is_numeric($creditLimit)
    ) {

        $error =
            'حد الائتمان يجب أن يكون رقمًا صحيحًا.';

    } elseif (
        (float) $creditLimit < 0
    ) {

        $error =
            'حد الائتمان لا يمكن أن يكون سالبًا.';

    } elseif (
        !in_array(
            $status,
            [
                'active',
                'inactive',
                'blocked'
            ],
            true
        )
    ) {

        $error =
            'حالة العميل غير صحيحة.';
    }


    /*
    |--------------------------------------------------------------------------
    | الحفظ
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            /*
            |--------------------------------------------------------------------------
            | التأكد من عدم تكرار رقم العميل
            |--------------------------------------------------------------------------
            */

            $checkStmt =
                $pdo->prepare("
                    SELECT id
                    FROM customers
                    WHERE customer_no = ?
                      AND id <> ?
                    LIMIT 1
                ");


            $checkStmt->execute([

                $customerNo,

                $customerId

            ]);


            if ($checkStmt->fetch()) {

                $error =
                    'رقم العميل مستخدم من قبل عميل آخر.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | بدء Transaction
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | قفل العميل
                |--------------------------------------------------------------------------
                */

                $lockCustomerStmt =
                    $pdo->prepare("
                        SELECT
                            id,
                            customer_no,
                            full_name,
                            balance,
                            status
                        FROM customers
                        WHERE id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");


                $lockCustomerStmt->execute([
                    $customerId
                ]);


                $lockedCustomer =
                    $lockCustomerStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$lockedCustomer) {

                    throw new Exception(
                        'العميل غير موجود.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | تحديث العميل
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $pdo->prepare("
                        UPDATE customers
                        SET
                            customer_no = ?,
                            full_name = ?,
                            phone = ?,
                            address = ?,
                            credit_limit = ?,
                            status = ?,
                            notes = ?
                        WHERE id = ?
                        LIMIT 1
                    ");


                $stmt->execute([

                    $customerNo,

                    $fullName,

                    $phone !== ''
                        ? $phone
                        : null,

                    $address !== ''
                        ? $address
                        : null,

                    (float) $creditLimit,

                    $status,

                    $notes !== ''
                        ? $notes
                        : null,

                    $customerId

                ]);


                /*
                |--------------------------------------------------------------------------
                | البحث عن الحساب المالي مع القفل
                |--------------------------------------------------------------------------
                */

                $accountStmt =
                    $pdo->prepare("
                        SELECT
                            id,
                            account_no,
                            account_name,
                            account_type,
                            customer_id,
                            opening_balance,
                            status,
                            notes
                        FROM accounts
                        WHERE customer_id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");


                $accountStmt->execute([
                    $customerId
                ]);


                $account =
                    $accountStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                /*
                |--------------------------------------------------------------------------
                | حالة الحساب المالي
                |--------------------------------------------------------------------------
                */

                $accountStatus =
                    $status === 'active'
                        ? 'active'
                        : 'inactive';


                /*
                |--------------------------------------------------------------------------
                | إذا كان الحساب موجودًا
                |--------------------------------------------------------------------------
                */

                if ($account) {

                    /*
                    |--------------------------------------------------------------------------
                    | التأكد أنه حساب عميل
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $account['account_type']
                        !==
                        'customer'
                    ) {

                        throw new Exception(
                            'الحساب المرتبط بالعميل ليس من نوع حساب عميل.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | تحديث الحساب
                    |--------------------------------------------------------------------------
                    */

                    $updateAccountStmt =
                        $pdo->prepare("
                            UPDATE accounts
                            SET
                                account_name = ?,
                                status = ?,
                                updated_at = NOW()
                            WHERE id = ?
                            LIMIT 1
                        ");


                    $updateAccountStmt->execute([

                        $fullName,

                        $accountStatus,

                        $account['id']

                    ]);


                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | الحساب غير موجود
                    |--------------------------------------------------------------------------
                    |
                    | إنشاء الحساب تلقائيًا للحالات القديمة
                    | التي تم إنشاؤها قبل تفعيل الربط.
                    |
                    */

                    $baseAccountNo =
                        'CUS-' .
                        str_pad(
                            (string) $customerId,
                            6,
                            '0',
                            STR_PAD_LEFT
                        );


                    $newAccountNo =
                        $baseAccountNo;


                    $counter = 1;


                    while (true) {

                        $checkAccountNo =
                            $pdo->prepare("
                                SELECT id
                                FROM accounts
                                WHERE account_no = ?
                                LIMIT 1
                            ");


                        $checkAccountNo->execute([
                            $newAccountNo
                        ]);


                        if (
                            !$checkAccountNo->fetch()
                        ) {

                            break;
                        }


                        $newAccountNo =
                            $baseAccountNo .
                            '-' .
                            $counter;

                        $counter++;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | إنشاء الحساب
                    |--------------------------------------------------------------------------
                    */

                    $insertAccountStmt =
                        $pdo->prepare("
                            INSERT INTO accounts
                            (
                                account_no,
                                account_name,
                                account_type,
                                owner_id,
                                employee_id,
                                customer_id,
                                opening_balance,
                                status,
                                notes,
                                created_at,
                                updated_at
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                'customer',
                                NULL,
                                NULL,
                                ?,
                                0.00,
                                ?,
                                ?,
                                NOW(),
                                NOW()
                            )
                        ");


                    $insertAccountStmt->execute([

                        $newAccountNo,

                        $fullName,

                        $customerId,

                        $accountStatus,

                        'حساب مالي تلقائي للعميل: ' .
                        $customerNo

                    ]);


                    $newAccountId =
                        (int) $pdo->lastInsertId();


                    if (
                        $newAccountId <= 0
                    ) {

                        throw new Exception(
                            'تعذر إنشاء الحساب المالي للعميل.'
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | التأكد من وجود حساب واحد فقط
                |--------------------------------------------------------------------------
                */

                $verifyStmt =
                    $pdo->prepare("
                        SELECT
                            COUNT(*) AS total
                        FROM accounts
                        WHERE customer_id = ?
                    ");


                $verifyStmt->execute([
                    $customerId
                ]);


                $accountCount =
                    (int) (
                        $verifyStmt->fetchColumn()
                    );


                if (
                    $accountCount !== 1
                ) {

                    throw new Exception(
                        'يجب أن يكون للعميل حساب مالي واحد فقط.'
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
                | العودة إلى صفحة العميل
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: view.php?id=' .
                    $customerId
                );

                exit;
            }


        } catch (Throwable $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            if (
                $e instanceof PDOException
                &&
                $e->getCode() === '23000'
            ) {

                $error =
                    'تعذر حفظ البيانات بسبب وجود حساب أو رقم مستخدم مسبقًا.';
            } else {

                $error =
                    'حدث خطأ أثناء تحديث بيانات العميل والحساب المالي.';

                /*
                 * للتطوير فقط:
                 *
                 * $error = $e->getMessage();
                 */
            }
        }
    }
}


require_once __DIR__ . '/../includes/header.php';

?>


<!-- =====================================================
     رأس الصفحة
====================================================== -->

<div class="page-header">

    <div>

        <h2>
            تعديل العميل
        </h2>


        <?php if ($customer): ?>

            <p>

                <?= e(
                    $customer['full_name']
                ) ?>

            </p>

        <?php endif; ?>

    </div>


    <div>

        <a
            href="index.php"
            class="btn"
        >
            ← العملاء
        </a>


        <?php if ($customer): ?>

            <a
                href="view.php?id=<?= (int) $customerId ?>"
                class="btn"
            >
                عرض العميل
            </a>

        <?php endif; ?>

    </div>

</div>


<!-- =====================================================
     رسالة الخطأ
====================================================== -->

<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= e($error) ?>

    </div>

<?php endif; ?>


<?php if ($customer): ?>


<!-- =====================================================
     معلومات الحساب الحالية
====================================================== -->

<div class="cards">


    <!-- الرصيد -->

    <div class="card stat-card">

        <div class="stat-icon">
            💰
        </div>

        <div class="stat-info">

            <span>
                الرصيد المستحق
            </span>

            <strong>

                <?= number_format(
                    (float) $balance,
                    2
                ) ?>

            </strong>

        </div>

    </div>


    <!-- حد الائتمان -->

    <div class="card stat-card">

        <div class="stat-icon">
            📊
        </div>

        <div class="stat-info">

            <span>
                حد الائتمان
            </span>

            <strong>

                <?= number_format(
                    (float) $creditLimit,
                    2
                ) ?>

            </strong>

        </div>

    </div>


    <!-- الحساب المالي -->

    <div class="card stat-card">

        <div class="stat-icon">
            🧾
        </div>

        <div class="stat-info">

            <span>
                الحساب المالي
            </span>

            <strong>

                <?php if ($account): ?>

                    <?= e(
                        $account['account_no']
                    ) ?>

                <?php else: ?>

                    غير موجود

                <?php endif; ?>

            </strong>

        </div>

    </div>


</div>


<!-- =====================================================
     نموذج التعديل
====================================================== -->

<div class="card">

    <h2>
        بيانات العميل
    </h2>


    <form
        method="POST"
        action="edit.php?id=<?= (int) $customerId ?>"
    >


        <input
            type="hidden"
            name="id"
            value="<?= (int) $customerId ?>"
        >


        <div class="form-grid">


            <!-- رقم العميل -->

            <div class="form-group">

                <label for="customer_no">
                    رقم العميل
                </label>

                <input
                    type="text"
                    id="customer_no"
                    name="customer_no"
                    value="<?= e($customerNo) ?>"
                    required
                >

            </div>


            <!-- اسم العميل -->

            <div class="form-group">

                <label for="full_name">
                    اسم العميل
                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= e($fullName) ?>"
                    required
                >

            </div>


            <!-- الهاتف -->

            <div class="form-group">

                <label for="phone">
                    رقم الهاتف
                </label>

                <input
                    type="text"
                    id="phone"
                    name="phone"
                    value="<?= e($phone) ?>"
                    placeholder="رقم الهاتف"
                >

            </div>


            <!-- حد الائتمان -->

            <div class="form-group">

                <label for="credit_limit">
                    حد الائتمان
                </label>

                <input
                    type="number"
                    id="credit_limit"
                    name="credit_limit"
                    value="<?= e($creditLimit) ?>"
                    min="0"
                    step="0.01"
                >

            </div>


            <!-- الحالة -->

            <div class="form-group">

                <label for="status">
                    الحالة
                </label>

                <select
                    id="status"
                    name="status"
                >

                    <option
                        value="active"
                        <?= $status === 'active'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        نشط
                    </option>


                    <option
                        value="inactive"
                        <?= $status === 'inactive'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        غير نشط
                    </option>


                    <option
                        value="blocked"
                        <?= $status === 'blocked'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        محظور
                    </option>

                </select>

            </div>


            <!-- العنوان -->

            <div class="form-group">

                <label for="address">
                    العنوان
                </label>

                <input
                    type="text"
                    id="address"
                    name="address"
                    value="<?= e($address) ?>"
                    placeholder="عنوان العميل"
                >

            </div>


        </div>


        <!-- الملاحظات -->

        <div class="form-group">

            <label for="notes">
                ملاحظات
            </label>

            <textarea
                id="notes"
                name="notes"
                rows="4"
                placeholder="ملاحظات إضافية عن العميل"
            ><?= e($notes) ?></textarea>

        </div>


        <!-- =================================================
             معلومات الحساب المالي
        ================================================== -->

        <div class="alert">

            <strong>
                الحساب المالي:
            </strong>

            <?php if ($account): ?>

                الحساب المرتبط:

                <strong>
                    <?= e(
                        $account['account_no']
                    ) ?>
                </strong>

                -
                
                <?= e(
                    $account['account_name']
                ) ?>

                <br>

                نوع الحساب:

                <strong>
                    حساب عميل
                </strong>

                <br>

                سيتم تحديث اسم الحساب تلقائيًا إذا تم تغيير اسم العميل.

            <?php else: ?>

                <strong>
                    تنبيه:
                </strong>

                لا يوجد حساب مالي مرتبط بهذا العميل حاليًا.

                سيتم إنشاء الحساب المالي تلقائيًا عند حفظ التعديلات.

            <?php endif; ?>

        </div>


        <!-- =================================================
             تنبيه الرصيد
        ================================================== -->

        <div class="alert">

            <strong>
                تنبيه:
            </strong>

            الرصيد الحالي للعميل هو:

            <strong>
                <?= number_format(
                    (float) $balance,
                    2
                ) ?>
            </strong>

            وسيتم الحفاظ عليه كما هو.

            <br>

            تعديل بيانات العميل هنا لا يغير:

            <br>

            • المبيعات السابقة

            <br>

            • الدفعات السابقة

            <br>

            • الحركات المالية

            <br>

            • الرصيد الافتتاحي للحساب

        </div>


        <!-- =================================================
             الأزرار
        ================================================== -->

        <div
            style="
                display:flex;
                gap:10px;
                margin-top:20px;
            "
        >

            <button
                type="submit"
                class="btn btn-primary"
            >
                💾 حفظ التعديلات
            </button>


            <a
                href="view.php?id=<?= (int) $customerId ?>"
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