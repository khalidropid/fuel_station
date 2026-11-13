<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'تعديل حساب';

$error = '';

$owners = [];
$employees = [];
$customers = [];


/*
|--------------------------------------------------------------------------
| حماية النصوص
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| أنواع الحسابات
|--------------------------------------------------------------------------
*/

$accountTypes = [

    'cash' => 'خزينة',

    'owner' => 'مالك',

    'employee' => 'موظف',

    'customer' => 'عميل',

    'expense' => 'مصروف',

    'bank' => 'بنك',

    'other' => 'أخرى'

];


/*
|--------------------------------------------------------------------------
| قراءة ID
|--------------------------------------------------------------------------
*/

$accountId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


if (!$accountId) {

    header('Location: index.php');
    exit;

}


/*
|--------------------------------------------------------------------------
| جلب الحساب
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            id,
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
        FROM accounts
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $accountId
    ]);

    $account =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$account) {

        header('Location: index.php');
        exit;

    }


    /*
    |--------------------------------------------------------------------------
    | جلب المستخدمين
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            username,
            full_name,
            role
        FROM users
        WHERE is_active = 1
        ORDER BY full_name ASC
    ");

    $owners =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | جلب الموظفين
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            employee_no,
            full_name,
            status
        FROM employees
        ORDER BY full_name ASC
    ");

    $employees =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | جلب العملاء
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            id,
            customer_no,
            name
        FROM customers
        ORDER BY name ASC
    ");

    $customers =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    $error =
        'تعذر تحميل بيانات الحساب: '
        .
        $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| معالجة التعديل
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    !$error
) {

    $accountNo = trim(
        $_POST['account_no'] ?? ''
    );

    $accountName = trim(
        $_POST['account_name'] ?? ''
    );

    $accountType = trim(
        $_POST['account_type'] ?? ''
    );

    $ownerId = (int)(
        $_POST['owner_id'] ?? 0
    );

    $employeeId = (int)(
        $_POST['employee_id'] ?? 0
    );

    $customerId = (int)(
        $_POST['customer_id'] ?? 0
    );

    $openingBalance = trim(
        $_POST['opening_balance'] ?? '0'
    );

    $status = trim(
        $_POST['status'] ?? 'active'
    );

    $notes = trim(
        $_POST['notes'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | التحقق الأساسي
    |--------------------------------------------------------------------------
    */

    if ($accountNo === '') {

        $error =
            'رقم الحساب مطلوب.';

    } elseif ($accountName === '') {

        $error =
            'اسم الحساب مطلوب.';

    } elseif (
        !array_key_exists(
            $accountType,
            $accountTypes
        )
    ) {

        $error =
            'نوع الحساب غير صحيح.';

    } elseif (
        !is_numeric($openingBalance)
        ||
        (float)$openingBalance < 0
    ) {

        $error =
            'الرصيد الافتتاحي غير صحيح.';

    } elseif (
        !in_array(
            $status,
            ['active', 'inactive'],
            true
        )
    ) {

        $error =
            'حالة الحساب غير صحيحة.';

    }


    /*
    |--------------------------------------------------------------------------
    | التحقق من نوع الربط
    |--------------------------------------------------------------------------
    */

    if (!$error) {

        if ($accountType === 'owner') {

            if ($ownerId <= 0) {

                $error =
                    'يجب اختيار المالك.';

            }

            $employeeId = 0;
            $customerId = 0;

        } elseif ($accountType === 'employee') {

            if ($employeeId <= 0) {

                $error =
                    'يجب اختيار الموظف.';

            }

            $ownerId = 0;
            $customerId = 0;

        } elseif ($accountType === 'customer') {

            if ($customerId <= 0) {

                $error =
                    'يجب اختيار العميل.';

            }

            $ownerId = 0;
            $employeeId = 0;

        } else {

            $ownerId = 0;
            $employeeId = 0;
            $customerId = 0;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | تحديث البيانات
    |--------------------------------------------------------------------------
    */

    if (!$error) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | قفل الحساب الحالي
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    account_no,
                    account_type,
                    owner_id,
                    employee_id,
                    customer_id
                FROM accounts
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                $accountId
            ]);

            $currentAccount =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$currentAccount) {

                throw new Exception(
                    'الحساب غير موجود.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | التأكد من رقم الحساب
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM accounts
                WHERE account_no = ?
                AND id <> ?
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                $accountNo,
                $accountId
            ]);


            if ($stmt->fetch()) {

                throw new Exception(
                    'رقم الحساب مستخدم بالفعل من حساب آخر.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | حساب المالك
            |--------------------------------------------------------------------------
            */

            if ($accountType === 'owner') {

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        username,
                        full_name,
                        is_active
                    FROM users
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmt->execute([
                    $ownerId
                ]);

                $owner =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$owner) {

                    throw new Exception(
                        'المالك المحدد غير موجود.'
                    );

                }


                if (
                    (int)$owner['is_active']
                    !==
                    1
                ) {

                    throw new Exception(
                        'لا يمكن ربط الحساب بمالك غير نشط.'
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | منع تكرار حساب المالك
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM accounts
                    WHERE account_type = 'owner'
                    AND owner_id = ?
                    AND status = 'active'
                    AND id <> ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmt->execute([
                    $ownerId,
                    $accountId
                ]);


                if ($stmt->fetch()) {

                    throw new Exception(
                        'هذا المالك مرتبط بحساب نشط آخر.'
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | حساب الموظف
            |--------------------------------------------------------------------------
            */

            if ($accountType === 'employee') {

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

                $stmt->execute([
                    $employeeId
                ]);

                $employee =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$employee) {

                    throw new Exception(
                        'الموظف المحدد غير موجود.'
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | منع تكرار حساب الموظف
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM accounts
                    WHERE account_type = 'employee'
                    AND employee_id = ?
                    AND status = 'active'
                    AND id <> ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmt->execute([
                    $employeeId,
                    $accountId
                ]);


                if ($stmt->fetch()) {

                    throw new Exception(
                        'هذا الموظف مرتبط بحساب نشط آخر.'
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | حساب العميل
            |--------------------------------------------------------------------------
            */

            if ($accountType === 'customer') {

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        customer_no,
                        name
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
                        'العميل المحدد غير موجود.'
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | منع تكرار حساب العميل
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM accounts
                    WHERE account_type = 'customer'
                    AND customer_id = ?
                    AND status = 'active'
                    AND id <> ?
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmt->execute([
                    $customerId,
                    $accountId
                ]);


                if ($stmt->fetch()) {

                    throw new Exception(
                        'هذا العميل مرتبط بحساب نشط آخر.'
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | تحديث الحساب
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE accounts

                SET

                    account_no =
                        :account_no,

                    account_name =
                        :account_name,

                    account_type =
                        :account_type,

                    owner_id =
                        :owner_id,

                    employee_id =
                        :employee_id,

                    customer_id =
                        :customer_id,

                    opening_balance =
                        :opening_balance,

                    status =
                        :status,

                    notes =
                        :notes

                WHERE id = :id
            ");


            $stmt->execute([

                ':account_no' =>
                    $accountNo,

                ':account_name' =>
                    $accountName,

                ':account_type' =>
                    $accountType,

                ':owner_id' =>
                    $ownerId > 0
                        ? $ownerId
                        : null,

                ':employee_id' =>
                    $employeeId > 0
                        ? $employeeId
                        : null,

                ':customer_id' =>
                    $customerId > 0
                        ? $customerId
                        : null,

                ':opening_balance' =>
                    round(
                        (float)$openingBalance,
                        2
                    ),

                ':status' =>
                    $status,

                ':notes' =>
                    $notes !== ''
                        ? $notes
                        : null,

                ':id' =>
                    $accountId

            ]);


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | العودة إلى كشف الحساب
            |--------------------------------------------------------------------------
            */

            header(
                'Location: view.php?id='
                .
                $accountId
                .
                '&success=updated'
            );

            exit;


        } catch (Throwable $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();

            }


            $error =
                $e->getMessage();


            /*
            |--------------------------------------------------------------------------
            | عرض البيانات التي أدخلها المستخدم
            |--------------------------------------------------------------------------
            */

            $account['account_no'] =
                $accountNo;

            $account['account_name'] =
                $accountName;

            $account['account_type'] =
                $accountType;

            $account['owner_id'] =
                $ownerId;

            $account['employee_id'] =
                $employeeId;

            $account['customer_id'] =
                $customerId;

            $account['opening_balance'] =
                $openingBalance;

            $account['status'] =
                $status;

            $account['notes'] =
                $notes;

        }

    }

}


require_once __DIR__ . '/../includes/header.php';

?>


<div class="page-header">

    <div>

        <h2>
            تعديل الحساب
        </h2>

        <p>
            تعديل بيانات الحساب وربطه بالمالك أو الموظف أو العميل.
        </p>

    </div>


    <div>

        <a
            href="view.php?id=<?= (int)$accountId ?>"
            class="btn btn-secondary"
        >
            كشف الحساب
        </a>


        <a
            href="index.php"
            class="btn btn-secondary"
        >
            الحسابات
        </a>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= e($error) ?>

    </div>

<?php endif; ?>


<div class="form-card">

    <form method="POST" id="accountForm">


        <!-- =================================================
             رقم الحساب
        ================================================== -->

        <div class="form-group">

            <label for="account_no">
                رقم الحساب
            </label>

            <input
                type="text"
                id="account_no"
                name="account_no"
                class="form-control"
                value="<?= e(
                    $account['account_no']
                ) ?>"
                required
            >

        </div>


        <!-- =================================================
             اسم الحساب
        ================================================== -->

        <div class="form-group">

            <label for="account_name">
                اسم الحساب
            </label>

            <input
                type="text"
                id="account_name"
                name="account_name"
                class="form-control"
                value="<?= e(
                    $account['account_name']
                ) ?>"
                required
            >

        </div>


        <!-- =================================================
             نوع الحساب
        ================================================== -->

        <div class="form-group">

            <label for="account_type">
                نوع الحساب
            </label>

            <select
                name="account_type"
                id="account_type"
                class="form-control"
                required
            >

                <?php foreach (
                    $accountTypes
                    as $type => $name
                ): ?>

                    <option
                        value="<?= e($type) ?>"
                        <?= (
                            $account['account_type']
                            ===
                            $type
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= e($name) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- =================================================
             المالك
        ================================================== -->

        <div
            class="form-group"
            id="ownerField"
            style="display:none;"
        >

            <label for="owner_id">
                المالك
            </label>

            <select
                name="owner_id"
                id="owner_id"
                class="form-control"
            >

                <option value="">
                    اختر المالك
                </option>


                <?php foreach ($owners as $owner): ?>

                    <option
                        value="<?= (int)$owner['id'] ?>"
                        <?= (
                            (int)(
                                $account['owner_id']
                                ?? 0
                            )
                            ===
                            (int)$owner['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= e(
                            $owner['full_name']
                            .
                            ' - '
                            .
                            $owner['username']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- =================================================
             الموظف
        ================================================== -->

        <div
            class="form-group"
            id="employeeField"
            style="display:none;"
        >

            <label for="employee_id">
                الموظف
            </label>

            <select
                name="employee_id"
                id="employee_id"
                class="form-control"
            >

                <option value="">
                    اختر الموظف
                </option>


                <?php foreach ($employees as $employee): ?>

                    <option
                        value="<?= (int)$employee['id'] ?>"
                        <?= (
                            (int)(
                                $account['employee_id']
                                ?? 0
                            )
                            ===
                            (int)$employee['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= e(
                            $employee['employee_no']
                            .
                            ' - '
                            .
                            $employee['full_name']
                            .
                            (
                                $employee['status'] !== 'active'
                                    ? ' - غير نشط'
                                    : ''
                            )
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- =================================================
             العميل
        ================================================== -->

        <div
            class="form-group"
            id="customerField"
            style="display:none;"
        >

            <label for="customer_id">
                العميل
            </label>

            <select
                name="customer_id"
                id="customer_id"
                class="form-control"
            >

                <option value="">
                    اختر العميل
                </option>


                <?php foreach ($customers as $customer): ?>

                    <option
                        value="<?= (int)$customer['id'] ?>"
                        <?= (
                            (int)(
                                $account['customer_id']
                                ?? 0
                            )
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
                            $customer['name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- =================================================
             الرصيد الافتتاحي
        ================================================== -->

        <div class="form-group">

            <label for="opening_balance">
                الرصيد الافتتاحي
            </label>

            <input
                type="number"
                id="opening_balance"
                name="opening_balance"
                class="form-control"
                step="0.01"
                min="0"
                value="<?= e(
                    $account['opening_balance']
                ) ?>"
            >

        </div>


        <!-- =================================================
             الحالة
        ================================================== -->

        <div class="form-group">

            <label for="status">
                الحالة
            </label>

            <select
                name="status"
                id="status"
                class="form-control"
            >

                <option
                    value="active"
                    <?= (
                        $account['status']
                        ===
                        'active'
                    )
                        ? 'selected'
                        : ''
                    ?>
                >
                    نشط
                </option>


                <option
                    value="inactive"
                    <?= (
                        $account['status']
                        ===
                        'inactive'
                    )
                        ? 'selected'
                        : ''
                    ?>
                >
                    غير نشط
                </option>

            </select>

        </div>


        <!-- =================================================
             الملاحظات
        ================================================== -->

        <div class="form-group">

            <label for="notes">
                ملاحظات
            </label>

            <textarea
                name="notes"
                id="notes"
                class="form-control"
                rows="4"
            ><?= e(
                $account['notes'] ?? ''
            ) ?></textarea>

        </div>


        <!-- =================================================
             معلومات الحساب
        ================================================== -->

        <div class="card">

            <h3>
                معلومات الحساب
            </h3>

            <div style="margin-top:10px;">

                <strong>
                    رقم الحساب:
                </strong>

                <?= e(
                    $account['account_no']
                ) ?>

            </div>


            <div style="margin-top:10px;">

                <strong>
                    تاريخ الإنشاء:
                </strong>

                <?= e(
                    $account['created_at']
                    ?? '-'
                ) ?>

            </div>


            <?php if (
                !empty(
                    $account['updated_at']
                )
            ): ?>

                <div style="margin-top:10px;">

                    <strong>
                        آخر تحديث:
                    </strong>

                    <?= e(
                        $account['updated_at']
                    ) ?>

                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             الأزرار
        ================================================== -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                حفظ التعديلات
            </button>


            <a
                href="view.php?id=<?= (int)$accountId ?>"
                class="btn btn-secondary"
            >
                إلغاء
            </a>

        </div>

    </form>

</div>


<script>

/*
|--------------------------------------------------------------------------
| العناصر
|--------------------------------------------------------------------------
*/

const accountType =
    document.getElementById(
        'account_type'
    );

const ownerField =
    document.getElementById(
        'ownerField'
    );

const employeeField =
    document.getElementById(
        'employeeField'
    );

const customerField =
    document.getElementById(
        'customerField'
    );

const ownerSelect =
    document.getElementById(
        'owner_id'
    );

const employeeSelect =
    document.getElementById(
        'employee_id'
    );

const customerSelect =
    document.getElementById(
        'customer_id'
    );


/*
|--------------------------------------------------------------------------
| تحديث حقول الربط
|--------------------------------------------------------------------------
*/

function updateRelationFields()
{
    const type =
        accountType.value;


    ownerField.style.display =
        'none';

    employeeField.style.display =
        'none';

    customerField.style.display =
        'none';


    ownerSelect.required =
        false;

    employeeSelect.required =
        false;

    customerSelect.required =
        false;


    if (type === 'owner') {

        ownerField.style.display =
            'block';

        ownerSelect.required =
            true;

    }


    if (type === 'employee') {

        employeeField.style.display =
            'block';

        employeeSelect.required =
            true;

    }


    if (type === 'customer') {

        customerField.style.display =
            'block';

        customerSelect.required =
            true;

    }
}


/*
|--------------------------------------------------------------------------
| تغيير نوع الحساب
|--------------------------------------------------------------------------
*/

accountType.addEventListener(
    'change',
    updateRelationFields
);


/*
|--------------------------------------------------------------------------
| تهيئة الصفحة
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        updateRelationFields();

    }
);

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>