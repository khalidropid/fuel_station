[<?php

    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../config/database.php';

    $pageTitle = 'تعديل موظف';

    $error = '';

    $id = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );


    /*
|--------------------------------------------------------------------------
| التحقق من ID
|--------------------------------------------------------------------------
*/

    if (!$id || $id <= 0) {

        header('Location: index.php');
        exit;
    }


    /*
|--------------------------------------------------------------------------
| أنواع الأجور
|--------------------------------------------------------------------------
*/

    $salaryTypes = [
        'monthly' => 'شهري',
        'daily'   => 'يومي',
        'hourly'  => 'بالساعة',
        'shift'   => 'بالوردية'
    ];


    /*
|--------------------------------------------------------------------------
| حالات الموظف
|--------------------------------------------------------------------------
*/

    $statuses = [
        'active'   => 'نشط',
        'inactive' => 'غير نشط',
        'vacation' => 'إجازة'
    ];


    /*
|--------------------------------------------------------------------------
| جلب الموظف
|--------------------------------------------------------------------------
*/

    try {

        $stmt = $pdo->prepare("
        SELECT
            id,
            employee_no,
            full_name,
            phone,
            address,
            job_title,
            salary_type,
            salary,
            hire_date,
            status,
            notes,
            created_at,
            updated_at
        FROM employees
        WHERE id = ?
        LIMIT 1
    ");

        $stmt->execute([
            $id
        ]);

        $employee = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$employee) {

            header('Location: index.php');
            exit;
        }
    } catch (Throwable $e) {

        die('خطأ في جلب بيانات الموظف: ' .
            htmlspecialchars(
                $e->getMessage(),
                ENT_QUOTES,
                'UTF-8'
            ));
    }


    /*
|--------------------------------------------------------------------------
| جلب الحساب المالي للموظف
|--------------------------------------------------------------------------
|
| مهم:
|
| نعتمد على employee_id فقط.
|
| لأن employee_id عليه UNIQUE في جدول accounts.
|
*/

    function getEmployeeAccount(
        PDO $pdo,
        int $employeeId
    ): ?array {

        $stmt = $pdo->prepare("
        SELECT
            id,
            account_no,
            account_name,
            account_type,
            employee_id,
            opening_balance,
            status,
            notes
        FROM accounts
        WHERE employee_id = ?
        LIMIT 1
    ");

        $stmt->execute([
            $employeeId
        ]);

        $account =
            $stmt->fetch(PDO::FETCH_ASSOC);

        return $account ?: null;
    }


    try {

        $account =
            getEmployeeAccount(
                $pdo,
                $id
            );
    } catch (Throwable $e) {

        $account = null;

        $error =
            'تعذر قراءة الحساب المالي: ' .
            $e->getMessage();
    }


    /*
|--------------------------------------------------------------------------
| حفظ التعديل
|--------------------------------------------------------------------------
*/

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        /*
    |--------------------------------------------------------------------------
    | قراءة البيانات
    |--------------------------------------------------------------------------
    */

        $employeeNo = trim(
            $_POST['employee_no'] ?? ''
        );

        $fullName = trim(
            $_POST['full_name'] ?? ''
        );

        $phone = trim(
            $_POST['phone'] ?? ''
        );

        $address = trim(
            $_POST['address'] ?? ''
        );

        $jobTitle = trim(
            $_POST['job_title'] ?? ''
        );

        $salaryType =
            $_POST['salary_type'] ?? 'monthly';

        $salary =
            trim(
                $_POST['salary'] ?? '0'
            );

        $hireDate =
            trim(
                $_POST['hire_date'] ?? ''
            );

        $status =
            $_POST['status'] ?? 'active';

        $notes =
            trim(
                $_POST['notes'] ?? ''
            );


        /*
    |--------------------------------------------------------------------------
    | التحقق من البيانات
    |--------------------------------------------------------------------------
    */

        if ($employeeNo === '') {

            $error =
                'رقم الموظف مطلوب.';
        } elseif ($fullName === '') {

            $error =
                'اسم الموظف مطلوب.';
        } elseif (
            !array_key_exists(
                $salaryType,
                $salaryTypes
            )
        ) {

            $error =
                'نوع الأجر غير صحيح.';
        } elseif (
            !is_numeric($salary)
            ||
            (float)$salary < 0
        ) {

            $error =
                'قيمة الأجر غير صحيحة.';
        } elseif (
            !array_key_exists(
                $status,
                $statuses
            )
        ) {

            $error =
                'حالة الموظف غير صحيحة.';
        }


        /*
    |--------------------------------------------------------------------------
    | الحفظ
    |--------------------------------------------------------------------------
    */

        if ($error === '') {

            try {

                $pdo->beginTransaction();


                /*
            |--------------------------------------------------------------------------
            | قفل الموظف
            |--------------------------------------------------------------------------
            */

                $lockEmployee = $pdo->prepare("
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

                $lockEmployee->execute([
                    $id
                ]);

                $currentEmployee =
                    $lockEmployee->fetch(PDO::FETCH_ASSOC);


                if (!$currentEmployee) {

                    throw new RuntimeException(
                        'الموظف غير موجود.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | التأكد من رقم الموظف
            |--------------------------------------------------------------------------
            */

                $check = $pdo->prepare("
                SELECT id
                FROM employees
                WHERE employee_no = ?
                  AND id <> ?
                LIMIT 1
            ");

                $check->execute([

                    $employeeNo,

                    $id

                ]);


                if ($check->fetch()) {

                    throw new RuntimeException(
                        'رقم الموظف مستخدم بالفعل.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | تحديث الموظف
            |--------------------------------------------------------------------------
            */

                $updateEmployee = $pdo->prepare("
                UPDATE employees
                SET
                    employee_no = ?,
                    full_name = ?,
                    phone = ?,
                    address = ?,
                    job_title = ?,
                    salary_type = ?,
                    salary = ?,
                    hire_date = ?,
                    status = ?,
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

                $updateEmployee->execute([

                    $employeeNo,

                    $fullName,

                    $phone !== ''
                        ? $phone
                        : null,

                    $address !== ''
                        ? $address
                        : null,

                    $jobTitle !== ''
                        ? $jobTitle
                        : null,

                    $salaryType,

                    (float)$salary,

                    $hireDate !== ''
                        ? $hireDate
                        : null,

                    $status,

                    $notes !== ''
                        ? $notes
                        : null,

                    $id

                ]);


                /*
            |--------------------------------------------------------------------------
            | جلب الحساب المالي بعد قفل الموظف
            |--------------------------------------------------------------------------
            */

                $accountStmt = $pdo->prepare("
                SELECT
                    id,
                    account_no,
                    account_name,
                    account_type,
                    employee_id,
                    opening_balance,
                    status,
                    notes
                FROM accounts
                WHERE employee_id = ?
                LIMIT 1
                FOR UPDATE
            ");

                $accountStmt->execute([
                    $id
                ]);

                $account =
                    $accountStmt->fetch(PDO::FETCH_ASSOC);


                /*
            |--------------------------------------------------------------------------
            | رقم الحساب المالي
            |--------------------------------------------------------------------------
            |
            | النظام المحاسبي يستخدم:
            |
            | EMP-0001
            | EMP-0002
            | EMP-0005
            |
            */

                $accountNo =
                    'EMP-' .
                    str_pad(
                        (string)$id,
                        4,
                        '0',
                        STR_PAD_LEFT
                    );


                /*
            |--------------------------------------------------------------------------
            | اسم الحساب
            |--------------------------------------------------------------------------
            */

                $accountName =
                    'حساب الموظف - ' .
                    $fullName;


                /*
            |--------------------------------------------------------------------------
            | حالة الحساب
            |--------------------------------------------------------------------------
            */

                $accountStatus =
                    $status === 'active'
                    ? 'active'
                    : 'inactive';


                /*
            |--------------------------------------------------------------------------
            | الحساب موجود
            |--------------------------------------------------------------------------
            */

                if ($account) {

                    /*
                |--------------------------------------------------------------------------
                | التأكد أن الحساب حساب موظف
                |--------------------------------------------------------------------------
                */

                    if (
                        $account['account_type']
                        !==
                        'employee'
                    ) {

                        throw new RuntimeException(

                            'يوجد حساب مرتبط بالموظف رقم ' .
                                $id .
                                ' ولكنه ليس من نوع employee.'

                        );
                    }


                    /*
                |--------------------------------------------------------------------------
                | تحديث الحساب
                |--------------------------------------------------------------------------
                */

                    $updateAccount = $pdo->prepare("
                    UPDATE accounts
                    SET
                        account_no = ?,
                        account_name = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                      AND employee_id = ?
                ");

                    $updateAccount->execute([

                        $accountNo,

                        $accountName,

                        $accountStatus,

                        $account['id'],

                        $id

                    ]);
                } else {

                    /*
                |--------------------------------------------------------------------------
                | لا يوجد حساب
                |--------------------------------------------------------------------------
                |
                | قبل الإنشاء نتأكد أن رقم الحساب غير مستخدم.
                |
                */

                    $numberCheck = $pdo->prepare("
                    SELECT
                        id,
                        employee_id,
                        account_type
                    FROM accounts
                    WHERE account_no = ?
                    LIMIT 1
                    FOR UPDATE
                ");

                    $numberCheck->execute([
                        $accountNo
                    ]);

                    $existingNumber =
                        $numberCheck->fetch(PDO::FETCH_ASSOC);


                    if ($existingNumber) {

                        throw new RuntimeException(

                            'رقم الحساب ' .
                                $accountNo .
                                ' مستخدم بالفعل.'

                        );
                    }


                    /*
                |--------------------------------------------------------------------------
                | إنشاء الحساب
                |--------------------------------------------------------------------------
                */

                    $createAccount = $pdo->prepare("
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
                        'employee',
                        NULL,
                        ?,
                        NULL,
                        0.00,
                        ?,
                        ?,
                        NOW(),
                        NOW()
                    )
                ");

                    $createAccount->execute([

                        $accountNo,

                        $accountName,

                        $id,

                        $accountStatus,

                        'الحساب المالي للموظف.'

                    ]);
                }


                /*
            |--------------------------------------------------------------------------
            | تأكيد المعاملة
            |--------------------------------------------------------------------------
            */

                $pdo->commit();


                /*
            |--------------------------------------------------------------------------
            | تحديث البيانات المحلية
            |--------------------------------------------------------------------------
            */

                $employee['employee_no'] =
                    $employeeNo;

                $employee['full_name'] =
                    $fullName;

                $employee['phone'] =
                    $phone;

                $employee['address'] =
                    $address;

                $employee['job_title'] =
                    $jobTitle;

                $employee['salary_type'] =
                    $salaryType;

                $employee['salary'] =
                    $salary;

                $employee['hire_date'] =
                    $hireDate;

                $employee['status'] =
                    $status;

                $employee['notes'] =
                    $notes;


                /*
            |--------------------------------------------------------------------------
            | إعادة جلب الحساب
            |--------------------------------------------------------------------------
            */

                $account =
                    getEmployeeAccount(
                        $pdo,
                        $id
                    );


                /*
            |--------------------------------------------------------------------------
            | الانتقال
            |--------------------------------------------------------------------------
            */

                header(
                    'Location: index.php?success=updated'
                );
                exit;

                exit;
            } catch (Throwable $e) {

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

                $error =
                    $e->getMessage();


                /*
            |--------------------------------------------------------------------------
            | الاحتفاظ بالبيانات المدخلة
            |--------------------------------------------------------------------------
            */

                $employee['employee_no'] =
                    $employeeNo;

                $employee['full_name'] =
                    $fullName;

                $employee['phone'] =
                    $phone;

                $employee['address'] =
                    $address;

                $employee['job_title'] =
                    $jobTitle;

                $employee['salary_type'] =
                    $salaryType;

                $employee['salary'] =
                    $salary;

                $employee['hire_date'] =
                    $hireDate;

                $employee['status'] =
                    $status;

                $employee['notes'] =
                    $notes;


                /*
            |--------------------------------------------------------------------------
            | إعادة جلب الحساب
            |--------------------------------------------------------------------------
            */

                try {

                    $account =
                        getEmployeeAccount(
                            $pdo,
                            $id
                        );
                } catch (Throwable $ignore) {

                    $account = null;
                }
            }
        }
    }


    /*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

    require_once __DIR__ . '/../includes/header.php';

    ?>


<div class="page-header">

    <div>

        <h2>
            تعديل الموظف
        </h2>

        <p>

            <?= htmlspecialchars(
                $employee['full_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </p>

    </div>


    <div>

        <a
            href="index.php"
            class="btn">
            ← الموظفين
        </a>


        <a
            href="view.php?id=<?= (int)$id ?>"
            class="btn">
            عرض الموظف
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

    <h2>
        بيانات الموظف
    </h2>


    <form
        method="POST"
        action="edit.php?id=<?= (int)$id ?>">

        <input
            type="hidden"
            name="id"
            value="<?= (int)$id ?>">


        <div class="form-grid">


            <!-- رقم الموظف -->

            <div class="form-group">

                <label>
                    رقم الموظف
                </label>

                <input
                    type="text"
                    name="employee_no"
                    class="form-control"
                    value="<?= htmlspecialchars(
                                $employee['employee_no'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

            </div>


            <!-- الاسم -->

            <div class="form-group">

                <label>
                    اسم الموظف
                </label>

                <input
                    type="text"
                    name="full_name"
                    class="form-control"
                    value="<?= htmlspecialchars(
                                $employee['full_name'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

            </div>


            <!-- الهاتف -->

            <div class="form-group">

                <label>
                    الهاتف
                </label>

                <input
                    type="text"
                    name="phone"
                    class="form-control"
                    value="<?= htmlspecialchars(
                                $employee['phone'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

            </div>


            <!-- العنوان -->

            <div class="form-group">

                <label>
                    العنوان
                </label>

                <input
                    type="text"
                    name="address"
                    class="form-control"
                    value="<?= htmlspecialchars(
                                $employee['address'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

            </div>


            <!-- الوظيفة -->

            <div class="form-group">

                <label>
                    الوظيفة
                </label>

                <input
                    type="text"
                    name="job_title"
                    class="form-control"
                    value="<?= htmlspecialchars(
                                $employee['job_title'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

            </div>


            <!-- نوع الأجر -->

            <div class="form-group">

                <label>
                    نوع الأجر
                </label>

                <select
                    name="salary_type"
                    class="form-control"
                    required>

                    <?php foreach (
                        $salaryTypes
                        as $value => $label
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                        $value,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                            <?= (
                                $employee['salary_type']
                                ===
                                $value
                            )
                                ? 'selected'
                                : '' ?>>

                            <?= htmlspecialchars(
                                $label,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- الراتب -->

            <div class="form-group">

                <label>
                    قيمة الأجر
                </label>

                <input
                    type="number"
                    name="salary"
                    class="form-control"
                    step="0.01"
                    min="0"
                    value="<?= htmlspecialchars(
                                $employee['salary'] ?? '0',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    required>

            </div>


            <!-- تاريخ التوظيف -->

            <div class="form-group">

                <label>
                    تاريخ التوظيف
                </label>

                <input
                    type="date"
                    name="hire_date"
                    class="form-control"
                    value="<?= htmlspecialchars(
                                $employee['hire_date'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">

            </div>


            <!-- الحالة -->

            <div class="form-group">

                <label>
                    الحالة
                </label>

                <select
                    name="status"
                    class="form-control"
                    required>

                    <?php foreach (
                        $statuses
                        as $value => $label
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                        $value,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                            <?= (
                                $employee['status']
                                ===
                                $value
                            )
                                ? 'selected'
                                : '' ?>>

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


        <!-- الملاحظات -->

        <div class="form-group">

            <label>
                ملاحظات
            </label>

            <textarea
                name="notes"
                class="form-control"
                rows="4"><?= htmlspecialchars(
                                $employee['notes'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></textarea>

        </div>


        <!-- الحساب المالي -->

        <?php if ($account): ?>

            <div class="alert">

                <strong>
                    الحساب المالي المرتبط
                </strong>

                <br><br>

                رقم الحساب:

                <strong>

                    <?= htmlspecialchars(
                        $account['account_no'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </strong>

                <br>

                اسم الحساب:

                <strong>

                    <?= htmlspecialchars(
                        $account['account_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </strong>

                <br>

                نوع الحساب:

                <strong>

                    <?= htmlspecialchars(
                        $account['account_type'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </strong>

            </div>

        <?php else: ?>

            <div class="alert alert-warning">

                لا يوجد حساب مالي مرتبط بهذا الموظف.

                <br>

                سيتم إنشاء الحساب تلقائيًا عند حفظ التعديل.

                <br><br>

                رقم الحساب المتوقع:

                <strong>
                    EMP-<?= str_pad(
                            (string)$id,
                            4,
                            '0',
                            STR_PAD_LEFT
                        ) ?>
                </strong>

            </div>

        <?php endif; ?>


        <!-- الأزرار -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary">
                💾 حفظ التعديلات
            </button>


            <a
                href="index.php"
                class="btn">
                إلغاء
            </a>

        </div>

    </form>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>]
