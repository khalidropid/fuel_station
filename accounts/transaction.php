<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'معاملة مالية';

$error = '';

/*
|--------------------------------------------------------------------------
| القيم الافتراضية
|--------------------------------------------------------------------------
*/

$partyType = $_GET['party_type'] ?? 'customer';

$partyId = (int)(
    $_GET['party_id'] ?? 0
);

$accountIdFromUrl = (int)(
    $_GET['account_id'] ?? 0
);


/*
|--------------------------------------------------------------------------
| إذا تم فتح الحركة من دليل الحسابات
|--------------------------------------------------------------------------
|
| مثال:
| transaction.php?account_id=5
|
*/

if ($accountIdFromUrl > 0) {

    try {

        $urlAccount =
            accountingGetAccountById(
                $pdo,
                $accountIdFromUrl
            );


        if ($urlAccount) {

            switch ($urlAccount['account_type']) {

                case 'employee':

                    $partyType = 'employee';

                    $partyId =
                        (int)$urlAccount['employee_id'];

                    break;


                case 'customer':

                    $partyType = 'customer';

                    $partyId =
                        (int)$urlAccount['customer_id'];

                    break;


                case 'owner':

                    $partyType = 'owner';

                    $partyId = 0;

                    break;


                case 'supplier':

                    $partyType = 'supplier';

                    $partyId =
                        $accountIdFromUrl;

                    break;
            }
        }
    } catch (Throwable $e) {

        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| حفظ الحركة
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $partyType =
        $_POST['party_type'] ?? 'customer';

    $partyId =
        (int)(
            $_POST['party_id'] ?? 0
        );

    $operation =
        $_POST['operation'] ?? '';

    $amount =
        (float)(
            $_POST['amount'] ?? 0
        );

    $date =
        str_replace(
            'T',
            ' ',
            trim(
                $_POST['transaction_date']
                    ?? date('Y-m-d\TH:i')
            )
        );

    $description =
        trim(
            $_POST['description'] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | التحقق الأساسي
    |--------------------------------------------------------------------------
    */

    if ($amount <= 0) {

        $error =
            'المبلغ يجب أن يكون أكبر من صفر.';
    } elseif (strtotime($date) === false) {

        $error =
            'التاريخ غير صحيح.';
    }


    /*
    |--------------------------------------------------------------------------
    | تنفيذ الحركة
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        try {

            /*
            |--------------------------------------------------------------------------
            | بدء المعاملة
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            $party = null;

            $counter = null;

            $partyAccount = null;

            $entries = [];


            /*
            |--------------------------------------------------------------------------
            | العميل
            |--------------------------------------------------------------------------
            */

            if ($partyType === 'customer') {

                if ($partyId <= 0) {

                    throw new Exception(
                        'اختر العميل.'
                    );
                }


                $partyAccount =
                    accountingEnsureCustomerAccount(
                        $pdo,
                        $partyId,
                        true
                    );


                $counter =
                    accountingEnsureCashAccount(
                        $pdo
                    );


                /*
                | إضافة مديونية
                */

                if ($operation === 'charge') {

                    $party =
                        $partyAccount;


                    $entries = [

                        [
                            'account_id' =>
                            (int)$party['id'],

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,

                            'description' =>
                            $description
                        ],

                        [
                            'account_id' =>
                            (int)$counter['id'],

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,

                            'description' =>
                            $description
                        ]

                    ];


                    /*
                | سداد العميل
                */
                } elseif ($operation === 'payment') {

                    $party =
                        $partyAccount;


                    $entries = [

                        [
                            'account_id' =>
                            (int)$counter['id'],

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,

                            'description' =>
                            $description
                        ],

                        [
                            'account_id' =>
                            (int)$party['id'],

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,

                            'description' =>
                            $description
                        ]

                    ];
                } else {

                    throw new Exception(
                        'نوع حركة العميل غير صحيح.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | الموظف
            |--------------------------------------------------------------------------
            */ elseif ($partyType === 'employee') {

                if ($partyId <= 0) {

                    throw new Exception(
                        'اختر الموظف.'
                    );
                }


                $partyAccount =
                    accountingEnsureEmployeeAccount(
                        $pdo,
                        $partyId,
                        true
                    );


                $counter =
                    accountingEnsureCashAccount(
                        $pdo
                    );


                /*
                | استحقاق للموظف
                */

                if ($operation === 'due') {

                    $expense =
                        accountingEnsureAccount(
                            $pdo,
                            'EXP-SALARY',
                            'مصروف الرواتب',
                            'expense',
                            null,
                            null,
                            null,
                            'حساب الرواتب.'
                        );


                    $entries = [

                        [
                            'account_id' =>
                            (int)$expense['id'],

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,

                            'description' =>
                            $description
                        ],

                        [
                            'account_id' =>
                            (int)$partyAccount['id'],

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,

                            'description' =>
                            $description
                        ]

                    ];


                    /*
                | دفع أو سلفة للموظف
                */
                } elseif (
                    $operation === 'payment'
                    ||
                    $operation === 'advance'
                ) {

                    $entries = [

                        [
                            'account_id' =>
                            (int)$partyAccount['id'],

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,

                            'description' =>
                            $description
                        ],

                        [
                            'account_id' =>
                            (int)$counter['id'],

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,

                            'description' =>
                            $description
                        ]

                    ];
                } else {

                    throw new Exception(
                        'نوع حركة الموظف غير صحيح.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | المالك
            |--------------------------------------------------------------------------
            */ elseif ($partyType === 'owner') {

                $partyAccount =
                    accountingEnsureOwnerAccount(
                        $pdo
                    );


                $counter =
                    accountingEnsureCashAccount(
                        $pdo
                    );


                /*
                | مساهمة المالك
                */

                if (
                    $operation ===
                    'contribution'
                ) {

                    $entries = [

                        [
                            'account_id' =>
                            (int)$counter['id'],

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,

                            'description' =>
                            $description
                        ],

                        [
                            'account_id' =>
                            (int)$partyAccount['id'],

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,

                            'description' =>
                            $description
                        ]

                    ];


                    /*
                | سحب المالك
                */
                } elseif (
                    $operation ===
                    'withdrawal'
                ) {

                    $entries = [

                        [
                            'account_id' =>
                            (int)$partyAccount['id'],

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,

                            'description' =>
                            $description
                        ],

                        [
                            'account_id' =>
                            (int)$counter['id'],

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,

                            'description' =>
                            $description
                        ]

                    ];
                } else {

                    throw new Exception(
                        'نوع حركة المالك غير صحيح.'
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | المورد
            |--------------------------------------------------------------------------
            */ elseif ($partyType === 'supplier') {

                $accountId =
                    (int)(
                        $_POST['account_id']
                        ?? 0
                    );


                /*
                | إذا كان المورد قادمًا من الحساب
                */

                if ($accountId <= 0) {

                    $accountId =
                        $partyId;
                }


                if ($accountId <= 0) {

                    throw new Exception(
                        'اختر حساب المورد.'
                    );
                }


                $partyAccount =
                    accountingGetAccountById(
                        $pdo,
                        $accountId,
                        true
                    );


                if (
                    !$partyAccount
                    ||
                    $partyAccount['account_type']
                    !== 'supplier'
                ) {

                    throw new Exception(
                        'حساب المورد غير صحيح.'
                    );
                }


                $counter =
                    accountingEnsureCashAccount(
                        $pdo
                    );


                /*
                | شراء / استحقاق للمورد
                */

                if (
                    $operation ===
                    'purchase'
                ) {

                    $expense =
                        accountingEnsureAccount(
                            $pdo,
                            'EXP-GENERAL',
                            'مشتريات / مصروفات الموردين',
                            'expense',
                            null,
                            null,
                            null,
                            'حساب عام لمعاملات الموردين.'
                        );


                    $entries = [

                        [
                            'account_id' =>
                            (int)$expense['id'],

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,

                            'description' =>
                            $description
                        ],

                        [
                            'account_id' =>
                            (int)$partyAccount['id'],

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,

                            'description' =>
                            $description
                        ]

                    ];


                    /*
                | سداد المورد
                */
                } elseif (
                    $operation ===
                    'payment'
                ) {

                    $entries = [

                        [
                            'account_id' =>
                            (int)$partyAccount['id'],

                            'debit' =>
                            $amount,

                            'credit' =>
                            0,

                            'description' =>
                            $description
                        ],

                        [
                            'account_id' =>
                            (int)$counter['id'],

                            'debit' =>
                            0,

                            'credit' =>
                            $amount,

                            'description' =>
                            $description
                        ]

                    ];
                } else {

                    throw new Exception(
                        'نوع حركة المورد غير صحيح.'
                    );
                }
            } else {

                throw new Exception(
                    'نوع الطرف غير صحيح.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | التأكد من وجود قيود
            |--------------------------------------------------------------------------
            */

            if (empty($entries)) {

                throw new Exception(
                    'لم يتم إنشاء القيود المحاسبية.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | إنشاء رقم الحركة
            |--------------------------------------------------------------------------
            */

            $no =
                accountingGenerateNo(
                    'MAN'
                );


            /*
            |--------------------------------------------------------------------------
            | تسجيل الحركة
            |--------------------------------------------------------------------------
            */

            accountingPostBalancedTransaction(

                $pdo,

                'manual',

                $partyType,

                $partyId,

                $entries,

                $date,

                $description !== ''
                    ? $description
                    : 'حركة مالية',

                accountingUserId(),

                $no

            );


            /*
            |--------------------------------------------------------------------------
            | تحديث رصيد العميل القديم
            |--------------------------------------------------------------------------
            */

            if (
                $partyType ===
                'customer'
            ) {

                accountingRecalculateCustomerLegacyBalance(
                    $pdo,
                    $partyId
                );
            }


            /*
            |--------------------------------------------------------------------------
            | تأكيد العملية
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | العودة إلى دليل الحسابات
            |--------------------------------------------------------------------------
            */

            header(
                'Location: index.php?success=transaction&no=' .
                    urlencode($no)
            );

            exit;
        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | التراجع عن العملية
            |--------------------------------------------------------------------------
            */

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
    }
}


/*
|--------------------------------------------------------------------------
| جلب الموظفين
|--------------------------------------------------------------------------
*/

try {

    $employeesStmt =
        $pdo->query("
            SELECT
                id,
                employee_no,
                full_name
            FROM employees
            WHERE status = 'active'
            ORDER BY full_name
        ");

    $employees =
        $employeesStmt->fetchAll(
            PDO::FETCH_ASSOC
        );
} catch (Throwable $e) {

    $employees = [];

    if ($error === '') {

        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| جلب العملاء
|--------------------------------------------------------------------------
*/

try {

    $customersStmt =
        $pdo->query("
            SELECT
                id,
                customer_no,
                full_name
            FROM customers
            WHERE status = 'active'
            ORDER BY full_name
        ");

    $customers =
        $customersStmt->fetchAll(
            PDO::FETCH_ASSOC
        );
} catch (Throwable $e) {

    $customers = [];

    if ($error === '') {

        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| جلب الموردين
|--------------------------------------------------------------------------
*/

try {

    $suppliersStmt =
        $pdo->query("
            SELECT
                id,
                account_no,
                account_name
            FROM accounts
            WHERE account_type = 'supplier'
              AND status = 'active'
            ORDER BY account_name
        ");

    $suppliers =
        $suppliersStmt->fetchAll(
            PDO::FETCH_ASSOC
        );
} catch (Throwable $e) {

    $suppliers = [];

    if ($error === '') {

        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| التاريخ الافتراضي
|--------------------------------------------------------------------------
*/

$transactionDate =
    $_POST['transaction_date']
    ??
    date('Y-m-d\TH:i');


require_once __DIR__ . '/../includes/header.php';

?>


<style>
    .transaction-page {
        max-width: 900px;
        margin: 0 auto;
    }

    .transaction-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, .04);
    }

    .transaction-header {
        margin-bottom: 25px;
    }

    .transaction-header h3 {
        margin: 0 0 7px;
    }

    .transaction-header p {
        margin: 0;
        color: #6b7280;
    }

    .transaction-grid {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .transaction-group {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .transaction-group.full {
        grid-column: 1 / -1;
    }

    .transaction-group label {
        font-weight: 600;
        color: #374151;
    }

    .transaction-group select,
    .transaction-group input,
    .transaction-group textarea {
        width: 100%;
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 9px;
        font-family: inherit;
        font-size: 14px;
        background: #fff;
        outline: none;
    }

    .transaction-group textarea {
        min-height: 110px;
        resize: vertical;
    }

    .transaction-group select:focus,
    .transaction-group input:focus,
    .transaction-group textarea:focus {
        border-color: #2563eb;
        box-shadow:
            0 0 0 3px rgba(37, 99, 235, .1);
    }

    .transaction-info {
        background: #eff6ff;
        border: 1px solid #dbeafe;
        color: #1e40af;
        border-radius: 10px;
        padding: 13px 15px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .transaction-actions {
        display: flex;
        justify-content: flex-start;
        align-items: center;
        gap: 10px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #e5e7eb;
    }

    .transaction-currency {
        color: #6b7280;
        font-size: 12px;
    }

    @media (max-width: 700px) {

        .transaction-grid {
            grid-template-columns: 1fr;
        }

        .transaction-group.full {
            grid-column: auto;
        }

        .transaction-card {
            padding: 18px;
        }

        .transaction-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .transaction-actions .btn {
            width: 100%;
            text-align: center;
        }
    }
</style>


<div class="transaction-page">


    <!-- =====================================================
         Page Header
    ====================================================== -->

    <div class="page-header">

        <div>

            <h2>
                حركة مالية
            </h2>

            <p>
                تسجيل حركة مالية جديدة في النظام المحاسبي.
            </p>

        </div>


        <div>

            <a
                href="index.php"
                class="btn">
                ← دليل الحسابات
            </a>

        </div>

    </div>


    <!-- =====================================================
         Error
    ====================================================== -->

    <?php if ($error): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         Transaction Card
    ====================================================== -->

    <div class="transaction-card">


        <div class="transaction-header">

            <h3>
                تسجيل حركة جديدة
            </h3>

            <p>
                اختر الطرف ونوع العملية وسيقوم النظام بإنشاء القيد المحاسبي تلقائيًا.
            </p>

        </div>


        <div class="transaction-info">

            <strong>
                تنبيه:
            </strong>

            بعد حفظ الحركة سيتم إعادتك تلقائيًا إلى
            <strong>
                دليل الحسابات
            </strong>.

        </div>


        <form
            method="post"
            id="transactionForm">


            <div class="transaction-grid">


                <!-- =================================================
                     Party Type
                ================================================== -->

                <div class="transaction-group">

                    <label for="party_type">
                        الطرف
                    </label>

                    <select
                        name="party_type"
                        id="party_type"
                        required>

                        <option
                            value="customer"
                            <?= $partyType === 'customer'
                                ? 'selected'
                                : '' ?>>
                            عميل
                        </option>

                        <option
                            value="employee"
                            <?= $partyType === 'employee'
                                ? 'selected'
                                : '' ?>>
                            موظف
                        </option>

                        <option
                            value="owner"
                            <?= $partyType === 'owner'
                                ? 'selected'
                                : '' ?>>
                            مالك المحطة
                        </option>

                        <option
                            value="supplier"
                            <?= $partyType === 'supplier'
                                ? 'selected'
                                : '' ?>>
                            مورد
                        </option>

                    </select>

                </div>


                <!-- =================================================
                     Party
                ================================================== -->

                <div
                    class="transaction-group"
                    id="partySelectGroup">

                    <label for="party_id">
                        الحساب / الطرف
                    </label>

                    <select
                        name="party_id"
                        id="party_id"></select>

                </div>


                <!-- =================================================
                     Operation
                ================================================== -->

                <div class="transaction-group">

                    <label for="operation">
                        نوع العملية
                    </label>

                    <select
                        name="operation"
                        id="operation"
                        required></select>

                </div>


                <!-- =================================================
                     Amount
                ================================================== -->

                <div class="transaction-group">

                    <label for="amount">
                        المبلغ
                    </label>

                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        step="0.01"
                        min="0.01"
                        value="<?= htmlspecialchars(
                                    $_POST['amount'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                        required>

                    <span class="transaction-currency">
                        أدخل المبلغ بالعملة المعتمدة للنظام.
                    </span>

                </div>


                <!-- =================================================
                     Date
                ================================================== -->

                <div class="transaction-group">

                    <label for="transaction_date">
                        التاريخ
                    </label>

                    <input
                        type="datetime-local"
                        name="transaction_date"
                        id="transaction_date"
                        value="<?= htmlspecialchars(
                                    $transactionDate,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                        required>

                </div>


                <!-- =================================================
                     Description
                ================================================== -->

                <div class="transaction-group full">

                    <label for="description">
                        البيان
                    </label>

                    <textarea
                        name="description"
                        id="description"
                        placeholder="اكتب وصف الحركة..."
                        required><?= htmlspecialchars(
                                        $_POST['description'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?></textarea>

                </div>


            </div>


            <!-- =================================================
                 Actions
            ================================================== -->

            <div class="transaction-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="saveTransactionBtn">
                    💾 حفظ الحركة
                </button>


                <a
                    href="index.php"
                    class="btn">
                    إلغاء
                </a>

            </div>


        </form>

    </div>

</div>


<script>
    /*
|--------------------------------------------------------------------------
| بيانات الأطراف
|--------------------------------------------------------------------------
*/

    const transactionData = {

        customer: <?= json_encode(
                        $customers,
                        JSON_UNESCAPED_UNICODE
                    ) ?>,

        employee: <?= json_encode(
                        $employees,
                        JSON_UNESCAPED_UNICODE
                    ) ?>,

        supplier: <?= json_encode(
                        $suppliers,
                        JSON_UNESCAPED_UNICODE
                    ) ?>

    };


    /*
    |--------------------------------------------------------------------------
    | العناصر
    |--------------------------------------------------------------------------
    */

    const partyType =
        document.getElementById(
            'party_type'
        );

    const party =
        document.getElementById(
            'party_id'
        );

    const partyGroup =
        document.getElementById(
            'partySelectGroup'
        );

    const operation =
        document.getElementById(
            'operation'
        );


    /*
    |--------------------------------------------------------------------------
    | العمليات
    |--------------------------------------------------------------------------
    */

    const operations = {

        customer: [

            [
                'charge',
                'إضافة مديونية / بيع آجل'
            ],

            [
                'payment',
                'سداد العميل'
            ]

        ],

        employee: [

            [
                'due',
                'استحقاق للموظف'
            ],

            [
                'payment',
                'دفع للموظف'
            ],

            [
                'advance',
                'سلفة للموظف'
            ]

        ],

        owner: [

            [
                'contribution',
                'إضافة أموال للمالك / مساهمة'
            ],

            [
                'withdrawal',
                'سحب المالك'
            ]

        ],

        supplier: [

            [
                'purchase',
                'شراء / استحقاق للمورد'
            ],

            [
                'payment',
                'سداد المورد'
            ]

        ]

    };


    /*
    |--------------------------------------------------------------------------
    | القيمة الحالية للطرف
    |--------------------------------------------------------------------------
    */

    const initialPartyId =
        <?= (int)$partyId ?>;


    /*
    |--------------------------------------------------------------------------
    | إنشاء القائمة
    |--------------------------------------------------------------------------
    */

    function renderTransactionForm() {

        const type =
            partyType.value;


        /*
        |--------------------------------------------------------------------------
        | قائمة الطرف
        |--------------------------------------------------------------------------
        */

        party.innerHTML = '';


        if (type === 'owner') {

            partyGroup.style.display =
                'none';

            party.required =
                false;

            party.value =
                '';

        } else {

            partyGroup.style.display =
                'flex';

            party.required =
                true;


            const list =
                transactionData[type] || [];


            const firstOption =
                document.createElement(
                    'option'
                );

            firstOption.value = '';

            firstOption.textContent =
                'اختر الطرف';


            party.appendChild(
                firstOption
            );


            list.forEach(
                function(item) {

                    const option =
                        document.createElement(
                            'option'
                        );


                    option.value =
                        item.id;


                    if (type === 'supplier') {

                        option.textContent =
                            (
                                item.account_no ||
                                ''
                            ) +
                            ' - ' +
                            (
                                item.account_name ||
                                ''
                            );

                    } else {

                        option.textContent =
                            (
                                item.employee_no ||
                                item.customer_no ||
                                ''
                            ) +
                            ' - ' +
                            (
                                item.full_name ||
                                ''
                            );
                    }


                    if (
                        parseInt(item.id) ===
                        parseInt(initialPartyId)
                    ) {

                        option.selected =
                            true;
                    }


                    party.appendChild(
                        option
                    );

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | قائمة العمليات
        |--------------------------------------------------------------------------
        */

        operation.innerHTML = '';


        const operationList =
            operations[type] || [];


        operationList.forEach(
            function(item) {

                const option =
                    document.createElement(
                        'option'
                    );


                option.value =
                    item[0];


                option.textContent =
                    item[1];


                operation.appendChild(
                    option
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | استعادة العملية بعد خطأ
        |--------------------------------------------------------------------------
        */

        const previousOperation =
            <?= json_encode(
                $_POST['operation'] ?? '',
                JSON_UNESCAPED_UNICODE
            ) ?>;


        if (
            previousOperation &&
            operation.querySelector(
                `option[value="${previousOperation}"]`
            )
        ) {

            operation.value =
                previousOperation;
        }

    }


    /*
    |--------------------------------------------------------------------------
    | تغيير الطرف
    |--------------------------------------------------------------------------
    */

    partyType.addEventListener(
        'change',
        function() {

            renderTransactionForm();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | منع الضغط المتكرر
    |--------------------------------------------------------------------------
    */

    document
        .getElementById('transactionForm')
        .addEventListener(
            'submit',
            function() {

                const button =
                    document.getElementById(
                        'saveTransactionBtn'
                    );


                button.disabled =
                    true;


                button.textContent =
                    '⏳ جاري حفظ الحركة...';

            }
        );


    /*
    |--------------------------------------------------------------------------
    | التشغيل
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'DOMContentLoaded',
        function() {

            renderTransactionForm();

        }
    );
</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>