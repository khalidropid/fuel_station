<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'إضافة عميل';

$error = '';

$customerNo = '';
$fullName = '';
$phone = '';
$address = '';
$creditLimit = '0';
$status = 'active';
$notes = '';


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
| إنشاء رقم العميل
|--------------------------------------------------------------------------
*/

function generateCustomerNo(
    PDO $pdo
): string {

    $stmt = $pdo->query("
        SELECT customer_no
        FROM customers
        ORDER BY id DESC
        LIMIT 1
    ");


    $lastCustomer =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (
        !$lastCustomer
        ||
        empty($lastCustomer['customer_no'])
    ) {

        return 'CUS-0001';
    }


    $lastNo =
        $lastCustomer['customer_no'];


    if (
        preg_match(
            '/(\d+)$/',
            $lastNo,
            $matches
        )
    ) {

        $number =
            (int)$matches[1];

        $number++;


        return 'CUS-' .
            str_pad(
                (string)$number,
                4,
                '0',
                STR_PAD_LEFT
            );
    }


    return 'CUS-0001';
}


/*
|--------------------------------------------------------------------------
| إنشاء رقم العميل عند فتح الصفحة
|--------------------------------------------------------------------------
*/

try {

    $customerNo =
        generateCustomerNo($pdo);

} catch (PDOException $e) {

    $error =
        'تعذر إنشاء رقم العميل تلقائيًا.';
}


/*
|--------------------------------------------------------------------------
| معالجة النموذج
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    ===
    'POST'
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

    if (
        $customerNo === ''
    ) {

        $error =
            'رقم العميل مطلوب.';

    } elseif (
        $fullName === ''
    ) {

        $error =
            'اسم العميل مطلوب.';

    } elseif (
        !is_numeric($creditLimit)
    ) {

        $error =
            'حد الائتمان يجب أن يكون رقمًا صحيحًا.';

    } elseif (
        (float)$creditLimit < 0
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

    if (
        $error === ''
    ) {

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
                    LIMIT 1
                ");


            $checkStmt->execute([
                $customerNo
            ]);


            if (
                $checkStmt->fetch()
            ) {

                $error =
                    'رقم العميل مستخدم بالفعل.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | بدء Transaction
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | إنشاء العميل
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $pdo->prepare("
                        INSERT INTO customers
                        (
                            customer_no,
                            full_name,
                            phone,
                            address,
                            credit_limit,
                            balance,
                            status,
                            notes,
                            created_at
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            0,
                            ?,
                            ?,
                            NOW()
                        )
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

                    (float)$creditLimit,

                    $status,

                    $notes !== ''
                        ? $notes
                        : null

                ]);


                /*
                |--------------------------------------------------------------------------
                | الحصول على ID العميل
                |--------------------------------------------------------------------------
                */

                $customerId =
                    (int)$pdo->lastInsertId();


                if (
                    $customerId <= 0
                ) {

                    throw new Exception(
                        'تعذر الحصول على رقم العميل.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | إنشاء الحساب المالي تلقائيًا
                |--------------------------------------------------------------------------
                |
                | الدالة:
                |
                | 1. تبحث عن حساب مرتبط بالعميل.
                | 2. إذا وجدته تستخدمه.
                | 3. إذا لم تجده تنشئ حسابًا جديدًا.
                |
                */

                $customerAccount =
                    accountingEnsureCustomerAccount(
                        $pdo,
                        $customerId,
                        true
                    );


                /*
                |--------------------------------------------------------------------------
                | التأكد من إنشاء الحساب
                |--------------------------------------------------------------------------
                */

                $accountId =
                    (int)$customerAccount['id'];


                if (
                    $accountId <= 0
                ) {

                    throw new Exception(
                        'تعذر إنشاء الحساب المالي للعميل.'
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
                | الانتقال إلى صفحة العميل
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: view.php?id=' .
                    $customerId
                );

                exit;
            }

        } catch (
            Throwable $e
        ) {

            /*
            |--------------------------------------------------------------------------
            | Rollback
            |--------------------------------------------------------------------------
            */

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | رسالة الخطأ
            |--------------------------------------------------------------------------
            */

            if (
                $e instanceof PDOException
                &&
                $e->getCode() === '23000'
            ) {

                $error =
                    'تعذر حفظ العميل بسبب تعارض في البيانات.';

            } else {

                $error =
                    $e->getMessage();
            }
        }
    }
}


require_once __DIR__ .
    '/../includes/header.php';

?>


<!-- =====================================================
     رأس الصفحة
====================================================== -->

<div class="page-header">

    <div>

        <h2>
            إضافة عميل جديد
        </h2>

        <p>
            تسجيل عميل جديد وإنشاء حسابه المالي تلقائيًا
        </p>

    </div>


    <div>

        <a
            href="index.php"
            class="btn"
        >
            ← العودة إلى العملاء
        </a>

    </div>

</div>


<!-- =====================================================
     رسالة الخطأ
====================================================== -->

<?php if ($error): ?>

    <div class="alert alert-danger">

        <?=e($error)?>

    </div>

<?php endif; ?>


<!-- =====================================================
     نموذج إضافة العميل
====================================================== -->

<div class="card">

    <h2>
        بيانات العميل
    </h2>


    <form
        method="POST"
        action="create.php"
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
                    value="<?=e($customerNo)?>"
                    required
                >

                <small>
                    يتم إنشاء الرقم تلقائيًا ويمكن تعديله.
                </small>

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
                    value="<?=e($fullName)?>"
                    placeholder="أدخل اسم العميل"
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
                    value="<?=e($phone)?>"
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
                    value="<?=e($creditLimit)?>"
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
                        <?=$status === 'active'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        نشط
                    </option>


                    <option
                        value="inactive"
                        <?=$status === 'inactive'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        غير نشط
                    </option>


                    <option
                        value="blocked"
                        <?=$status === 'blocked'
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
                    value="<?=e($address)?>"
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
            ><?=e($notes)?></textarea>

        </div>


        <!-- =================================================
             الحساب المالي
        ================================================== -->

        <div class="alert">

            <strong>
                الحساب المالي:
            </strong>

            سيتم إنشاء حساب مالي تلقائيًا لهذا العميل عند الحفظ.

            <br>

            <strong>
                الرصيد الافتتاحي:
            </strong>

            0.00

            <br>

            سيتم استخدام الحساب في المبيعات الآجلة،
            والتحصيلات، وكشف الحساب، وحساب له / عليه.

        </div>


        <!-- الأزرار -->

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
                💾 حفظ العميل وإنشاء الحساب
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

require_once __DIR__ .
    '/../includes/footer.php';

?>