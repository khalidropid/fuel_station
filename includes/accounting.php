<?php

/**
 * =========================================================
 * محرك المحاسبة المزدوجة
 * =========================================================
 *
 * جميع القيود والحسابات المالية في النظام تمر من هنا.
 */

require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| رقم المستخدم الحالي
|--------------------------------------------------------------------------
*/

if (!function_exists('accountingUserId')) {

    function accountingUserId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;

        return ($id !== null && (int)$id > 0)
            ? (int)$id
            : null;
    }
}


/*
|--------------------------------------------------------------------------
| إنشاء رقم حركة
|--------------------------------------------------------------------------
*/

function accountingGenerateNo(
    string $prefix = 'TRX'
): string {

    return $prefix .
        '-' .
        date('YmdHis') .
        '-' .
        random_int(100, 999);
}


/*
|--------------------------------------------------------------------------
| جلب حساب بواسطة رقم الحساب
|--------------------------------------------------------------------------
*/

function accountingGetAccountByNo(
    PDO $pdo,
    string $accountNo,
    bool $forUpdate = false
): ?array {

    $sql = "
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
            notes
        FROM accounts
        WHERE account_no = ?
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $accountNo
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


/*
|--------------------------------------------------------------------------
| جلب حساب بواسطة ID
|--------------------------------------------------------------------------
*/

function accountingGetAccountById(
    PDO $pdo,
    int $id,
    bool $forUpdate = false
): ?array {

    $sql = "
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
            notes
        FROM accounts
        WHERE id = ?
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $id
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}


/*
|--------------------------------------------------------------------------
| إنشاء أو جلب حساب عام
|--------------------------------------------------------------------------
*/

function accountingEnsureAccount(
    PDO $pdo,
    string $accountNo,
    string $name,
    string $type,
    ?int $employeeId = null,
    ?int $customerId = null,
    ?int $ownerId = null,
    string $notes = ''
): array {

    /*
    | البحث بواسطة رقم الحساب
    */

    $account = accountingGetAccountByNo(
        $pdo,
        $accountNo,
        true
    );

    if ($account) {

        if ($account['status'] !== 'active') {

            throw new RuntimeException(
                "الحساب {$accountNo} غير نشط."
            );
        }

        return $account;
    }


    /*
    | أنواع الحسابات المسموحة
    */

    $allowed = [
        'cash',
        'bank',
        'owner',
        'employee',
        'customer',
        'supplier',
        'expense',
        'other'
    ];

    if (!in_array($type, $allowed, true)) {

        throw new RuntimeException(
            'نوع الحساب غير صحيح.'
        );
    }


    /*
    | إنشاء الحساب
    */

    $stmt = $pdo->prepare("
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
            ?,
            ?,
            ?,
            ?,
            0.00,
            'active',
            ?,
            NOW(),
            NOW()
        )
    ");

    $stmt->execute([

        $accountNo,

        $name,

        $type,

        $ownerId,

        $employeeId,

        $customerId,

        $notes !== ''
            ? $notes
            : null

    ]);


    $id = (int)$pdo->lastInsertId();


    if ($id <= 0) {

        throw new RuntimeException(
            'تعذر إنشاء الحساب.'
        );
    }


    $account = accountingGetAccountById(
        $pdo,
        $id,
        true
    );


    if (!$account) {

        throw new RuntimeException(
            'تعذر قراءة الحساب بعد إنشائه.'
        );
    }


    return $account;
}


/*
|--------------------------------------------------------------------------
| حساب المالك
|--------------------------------------------------------------------------
*/

function accountingEnsureOwnerAccount(
    PDO $pdo
): array {

    return accountingEnsureAccount(
        $pdo,
        'OWNER-001',
        'مالك المحطة',
        'owner',
        null,
        null,
        null,
        'الحساب الرئيسي لحقوق مالك المحطة.'
    );
}


/*
|--------------------------------------------------------------------------
| حساب الخزينة
|--------------------------------------------------------------------------
*/

function accountingEnsureCashAccount(
    PDO $pdo
): array {

    return accountingEnsureAccount(
        $pdo,
        'CASH-001',
        'خزينة المحطة',
        'cash',
        null,
        null,
        null,
        'الحساب النقدي الرئيسي للمحطة.'
    );
}


/*
|--------------------------------------------------------------------------
| حساب البطاقات / البنك
|--------------------------------------------------------------------------
*/

function accountingEnsureCardAccount(
    PDO $pdo
): array {

    return accountingEnsureAccount(
        $pdo,
        'CARD-001',
        'مبيعات البطاقات / البنك',
        'bank',
        null,
        null,
        null,
        'حساب وسيط لمبيعات البطاقات.'
    );
}


/*
|--------------------------------------------------------------------------
| حساب الموظف
|--------------------------------------------------------------------------
*/

function accountingEnsureEmployeeAccount(
    PDO $pdo,
    int $employeeId,
    bool $forUpdate = true
): array {

    $sql = "
        SELECT
            id,
            employee_no,
            full_name,
            status
        FROM employees
        WHERE id = ?
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $employeeId
    ]);

    $employee = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$employee) {

        throw new RuntimeException(
            'الموظف غير موجود.'
        );
    }


    $accountNo =
        'EMP-' .
        str_pad(
            (string)$employeeId,
            4,
            '0',
            STR_PAD_LEFT
        );


    return accountingEnsureAccount(

        $pdo,

        $accountNo,

        'حساب الموظف - ' .
        $employee['full_name'],

        'employee',

        $employeeId,

        null,

        null,

        'حساب الموظف.'

    );
}


/*
|--------------------------------------------------------------------------
| حساب العميل
|--------------------------------------------------------------------------
|
| مهم:
|
| لكل عميل حساب مالي واحد فقط.
|
| نبحث أولًا باستخدام customer_id.
|
| إذا وجدناه:
| نستخدمه.
|
| إذا لم نجده:
| ننشئ حسابًا جديدًا.
|
|--------------------------------------------------------------------------
*/

function accountingEnsureCustomerAccount(
    PDO $pdo,
    int $customerId,
    bool $forUpdate = true
): array {

    /*
    |--------------------------------------------------------------------------
    | التحقق من العميل
    |--------------------------------------------------------------------------
    */

    $customerSql = "
        SELECT
            id,
            customer_no,
            full_name,
            status
        FROM customers
        WHERE id = ?
        LIMIT 1
    ";

    if ($forUpdate) {
        $customerSql .= " FOR UPDATE";
    }

    $stmt = $pdo->prepare(
        $customerSql
    );

    $stmt->execute([
        $customerId
    ]);

    $customer =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$customer) {

        throw new RuntimeException(
            'العميل غير موجود.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | البحث عن حساب العميل بواسطة customer_id
    |--------------------------------------------------------------------------
    */

    $accountSql = "
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
            notes
        FROM accounts
        WHERE customer_id = ?
        LIMIT 1
    ";

    if ($forUpdate) {
        $accountSql .= " FOR UPDATE";
    }

    $accountStmt = $pdo->prepare(
        $accountSql
    );

    $accountStmt->execute([
        $customerId
    ]);

    $account =
        $accountStmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | الحساب موجود
    |--------------------------------------------------------------------------
    */

    if ($account) {

        if (
            $account['account_type']
            !==
            'customer'
        ) {

            throw new RuntimeException(
                'الحساب المرتبط بالعميل ليس حساب عميل.'
            );
        }


        if (
            $account['status']
            !==
            'active'
        ) {

            throw new RuntimeException(
                'الحساب المالي للعميل غير نشط.'
            );
        }


        return $account;
    }


    /*
    |--------------------------------------------------------------------------
    | إنشاء رقم الحساب
    |--------------------------------------------------------------------------
    */

    $accountNo =
        'CUS-' .
        str_pad(
            (string)$customerId,
            4,
            '0',
            STR_PAD_LEFT
        );


    /*
    |--------------------------------------------------------------------------
    | التأكد من أن رقم الحساب غير مستخدم
    |--------------------------------------------------------------------------
    */

    $numberStmt = $pdo->prepare("
        SELECT
            id,
            customer_id
        FROM accounts
        WHERE account_no = ?
        LIMIT 1
    ");

    $numberStmt->execute([
        $accountNo
    ]);

    $numberAccount =
        $numberStmt->fetch(PDO::FETCH_ASSOC);


    if ($numberAccount) {

        throw new RuntimeException(
            'رقم الحساب ' .
            $accountNo .
            ' مستخدم بالفعل.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | إنشاء الحساب المالي للعميل
    |--------------------------------------------------------------------------
    */

    $insertStmt = $pdo->prepare("
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
            'active',
            ?,
            NOW(),
            NOW()
        )
    ");

    try {

        $insertStmt->execute([

            $accountNo,

            'حساب العميل - ' .
            $customer['full_name'],

            $customerId,

            'حساب العميل.'

        ]);

    } catch (PDOException $e) {

        /*
        |--------------------------------------------------------------------------
        | في حالة وجود الحساب بسبب عملية متزامنة
        |--------------------------------------------------------------------------
        */

        if (
            $e->getCode() === '23000'
        ) {

            $existingStmt = $pdo->prepare("
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
                    notes
                FROM accounts
                WHERE customer_id = ?
                LIMIT 1
            ");

            $existingStmt->execute([
                $customerId
            ]);

            $existing =
                $existingStmt->fetch(PDO::FETCH_ASSOC);


            if ($existing) {

                if (
                    $existing['account_type']
                    !==
                    'customer'
                ) {

                    throw new RuntimeException(
                        'الحساب المرتبط بالعميل غير صحيح.'
                    );
                }


                if (
                    $existing['status']
                    !==
                    'active'
                ) {

                    throw new RuntimeException(
                        'الحساب المالي للعميل غير نشط.'
                    );
                }


                return $existing;
            }
        }


        throw $e;
    }


    /*
    |--------------------------------------------------------------------------
    | قراءة الحساب بعد الإنشاء
    |--------------------------------------------------------------------------
    */

    $accountId =
        (int)$pdo->lastInsertId();


    if ($accountId <= 0) {

        throw new RuntimeException(
            'تعذر إنشاء الحساب المالي للعميل.'
        );
    }


    $account =
        accountingGetAccountById(
            $pdo,
            $accountId,
            $forUpdate
        );


    if (!$account) {

        throw new RuntimeException(
            'تم إنشاء الحساب ولكن تعذر قراءته.'
        );
    }


    return $account;
}


/*
|--------------------------------------------------------------------------
| حساب المورد
|--------------------------------------------------------------------------
*/

function accountingEnsureSupplierAccount(
    PDO $pdo,
    string $accountNo,
    string $name
): array {

    if (
        strpos($accountNo, 'SUP-') !== 0
    ) {

        throw new RuntimeException(
            'رقم حساب المورد يجب أن يبدأ بـ SUP-.'
        );
    }


    return accountingEnsureAccount(

        $pdo,

        $accountNo,

        $name,

        'supplier',

        null,

        null,

        null,

        'حساب مورد.'

    );
}


/*
|--------------------------------------------------------------------------
| إنشاء قيد مزدوج متوازن
|--------------------------------------------------------------------------
*/

function accountingPostBalancedTransaction(
    PDO $pdo,
    string $transactionType,
    string $referenceType,
    ?int $referenceId,
    array $entries,
    string $transactionDate,
    string $description,
    ?int $createdBy = null,
    ?string $transactionNo = null
): string {

    if (
        !$entries ||
        count($entries) < 2
    ) {

        throw new RuntimeException(
            'القيد يجب أن يحتوي على طرفين على الأقل.'
        );
    }


    $totalDebit = 0.0;
    $totalCredit = 0.0;

    $normalized = [];


    foreach ($entries as $entry) {

        $accountId =
            (int)($entry['account_id'] ?? 0);

        $debit =
            round(
                (float)($entry['debit'] ?? 0),
                2
            );

        $credit =
            round(
                (float)($entry['credit'] ?? 0),
                2
            );


        if ($accountId <= 0) {

            throw new RuntimeException(
                'حساب غير صالح في القيد.'
            );
        }


        if (
            $debit < 0 ||
            $credit < 0
        ) {

            throw new RuntimeException(
                'المدين والدائن لا يمكن أن يكونا سالبين.'
            );
        }


        if (
            ($debit > 0 && $credit > 0)
            ||
            ($debit == 0 && $credit == 0)
        ) {

            throw new RuntimeException(
                'كل سطر يجب أن يكون مدينًا أو دائنًا فقط.'
            );
        }


        $totalDebit += $debit;

        $totalCredit += $credit;


        $normalized[] = [

            'account_id' =>
                $accountId,

            'debit' =>
                $debit,

            'credit' =>
                $credit,

            'description' =>
                $entry['description']
                ??
                $description

        ];
    }


    $totalDebit =
        round($totalDebit, 2);

    $totalCredit =
        round($totalCredit, 2);


    if (
        abs(
            $totalDebit -
            $totalCredit
        ) > 0.009
    ) {

        throw new RuntimeException(

            'القيد غير متوازن: المدين ' .
            number_format(
                $totalDebit,
                2
            ) .
            ' والدائن ' .
            number_format(
                $totalCredit,
                2
            )

        );
    }


    $transactionNo =
        $transactionNo
        ?:
        accountingGenerateNo('TRX');


    $check = $pdo->prepare("
        SELECT id
        FROM account_transactions
        WHERE transaction_no = ?
        LIMIT 1
        FOR UPDATE
    ");

    $check->execute([
        $transactionNo
    ]);


    if ($check->fetch()) {

        throw new RuntimeException(
            'رقم الحركة مستخدم مسبقًا: ' .
            $transactionNo
        );
    }


    /*
    |--------------------------------------------------------------------------
    | قفل الحسابات
    |--------------------------------------------------------------------------
    */

    $ids =
        array_values(
            array_unique(
                array_map(
                    fn($e) =>
                        (int)$e['account_id'],
                    $normalized
                )
            )
        );


    sort(
        $ids,
        SORT_NUMERIC
    );


    foreach ($ids as $id) {

        $account =
            accountingGetAccountById(
                $pdo,
                $id,
                true
            );


        if (!$account) {

            throw new RuntimeException(
                'أحد الحسابات في القيد غير موجود.'
            );
        }


        if (
            $account['status']
            !==
            'active'
        ) {

            throw new RuntimeException(
                'أحد الحسابات في القيد غير نشط.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | إدخال القيود
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO account_transactions
        (
            transaction_no,
            account_id,
            transaction_type,
            reference_type,
            reference_id,
            debit,
            credit,
            transaction_date,
            description,
            created_by
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
            ?,
            ?,
            ?
        )
    ");


    foreach ($normalized as $entry) {

        $stmt->execute([

            $transactionNo,

            $entry['account_id'],

            $transactionType,

            $referenceType,

            $referenceId,

            $entry['debit'],

            $entry['credit'],

            $transactionDate,

            $entry['description']
                ?:
                $description,

            $createdBy

        ]);
    }


    return $transactionNo;
}


/*
|--------------------------------------------------------------------------
| الرصيد الافتتاحي المدين
|--------------------------------------------------------------------------
*/

function accountingAccountOpeningDebit(
    array $account
): float {

    $type =
        $account['account_type'];


    return in_array(
        $type,
        [
            'cash',
            'bank',
            'customer',
            'expense',
            'other'
        ],
        true
    )
        ? (float)$account['opening_balance']
        : 0.0;
}


/*
|--------------------------------------------------------------------------
| الرصيد الافتتاحي الدائن
|--------------------------------------------------------------------------
*/

function accountingAccountOpeningCredit(
    array $account
): float {

    $type =
        $account['account_type'];


    return in_array(
        $type,
        [
            'owner',
            'employee',
            'supplier'
        ],
        true
    )
        ? (float)$account['opening_balance']
        : 0.0;
}


/*
|--------------------------------------------------------------------------
| الحصول على رصيد الحساب
|--------------------------------------------------------------------------
*/

function accountingGetBalance(
    PDO $pdo,
    int $accountId,
    bool $forUpdate = false
): array {

    $account =
        accountingGetAccountById(
            $pdo,
            $accountId,
            $forUpdate
        );


    if (!$account) {

        throw new RuntimeException(
            'الحساب غير موجود.'
        );
    }


    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(debit), 0) AS debit,
            COALESCE(SUM(credit), 0) AS credit
        FROM account_transactions
        WHERE account_id = ?
    ");

    $stmt->execute([
        $accountId
    ]);


    $r =
        $stmt->fetch(PDO::FETCH_ASSOC);


    $debit =
        round(
            accountingAccountOpeningDebit($account)
            +
            (float)$r['debit'],
            2
        );


    $credit =
        round(
            accountingAccountOpeningCredit($account)
            +
            (float)$r['credit'],
            2
        );


    $net =
        round(
            $credit - $debit,
            2
        );


    return [

        'account' =>
            $account,

        'debit' =>
            $debit,

        'credit' =>
            $credit,

        'balance' =>
            $net,

        'status' =>
            $net > 0.009
                ? 'له'
                : (
                    $net < -0.009
                        ? 'عليه'
                        : 'متوازن'
                )

    ];
}


/*
|--------------------------------------------------------------------------
| كشف حساب
|--------------------------------------------------------------------------
*/

function accountingGetLedger(
    PDO $pdo,
    int $accountId
): array {

    $account =
        accountingGetAccountById(
            $pdo,
            $accountId
        );


    if (!$account) {

        throw new RuntimeException(
            'الحساب غير موجود.'
        );
    }


    $openingDebit =
        accountingAccountOpeningDebit(
            $account
        );


    $openingCredit =
        accountingAccountOpeningCredit(
            $account
        );


    $stmt = $pdo->prepare("
        SELECT
            id,
            transaction_no,
            transaction_type,
            reference_type,
            reference_id,
            debit,
            credit,
            transaction_date,
            description,
            created_by
        FROM account_transactions
        WHERE account_id = ?
        ORDER BY
            transaction_date ASC,
            id ASC
    ");

    $stmt->execute([
        $accountId
    ]);


    $rows = [];


    $running =
        round(
            $openingCredit -
            $openingDebit,
            2
        );


    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {

        $running =
            round(
                $running
                +
                (float)$row['credit']
                -
                (float)$row['debit'],
                2
            );


        $row['running_balance'] =
            $running;


        $row['balance_status'] =
            $running > 0.009
                ? 'له'
                : (
                    $running < -0.009
                        ? 'عليه'
                        : 'متوازن'
                );


        $rows[] =
            $row;
    }


    return [

        'account' =>
            $account,

        'rows' =>
            $rows,

        'opening_debit' =>
            $openingDebit,

        'opening_credit' =>
            $openingCredit

    ];
}


/*
|--------------------------------------------------------------------------
| تحديث رصيد العميل القديم
|--------------------------------------------------------------------------
*/

function accountingRecalculateCustomerLegacyBalance(
    PDO $pdo,
    int $customerId
): void {

    $account =
        accountingEnsureCustomerAccount(
            $pdo,
            $customerId,
            true
        );


    $data =
        accountingGetBalance(
            $pdo,
            (int)$account['id']
        );


    /*
    | customers.balance يمثل المبلغ المستحق
    | على العميل.
    */

    $amountOwed =
        max(
            0,
            round(
                -$data['balance'],
                2
            )
        );


    $stmt = $pdo->prepare("
        UPDATE customers
        SET balance = ?
        WHERE id = ?
    ");


    $stmt->execute([

        $amountOwed,

        $customerId

    ]);
}