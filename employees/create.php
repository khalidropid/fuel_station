<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'إضافة موظف';

$error = '';

$employeeNo = '';
$fullName = '';
$phone = '';
$address = '';
$jobTitle = '';
$salaryType = 'monthly';
$salary = '0';
$hireDate = '';
$status = 'active';
$notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employeeNo = trim($_POST['employee_no'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $jobTitle = trim($_POST['job_title'] ?? '');
    $salaryType = $_POST['salary_type'] ?? 'monthly';
    $salary = trim($_POST['salary'] ?? '0');
    $hireDate = trim($_POST['hire_date'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $notes = trim($_POST['notes'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | التحقق
    |--------------------------------------------------------------------------
    */

    if ($employeeNo === '') {
        $error = 'رقم الموظف مطلوب.';
    } elseif ($fullName === '') {
        $error = 'اسم الموظف مطلوب.';
    } elseif (!in_array($salaryType, ['monthly', 'daily', 'hourly', 'shift'], true)) {
        $error = 'نوع الأجر غير صحيح.';
    } elseif (!is_numeric($salary) || (float)$salary < 0) {
        $error = 'قيمة الأجر غير صحيحة.';
    } elseif (!in_array($status, ['active', 'inactive', 'vacation'], true)) {
        $error = 'حالة الموظف غير صحيحة.';
    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Transaction
            |--------------------------------------------------------------------------
            */
            $pdo->beginTransaction();

            /*
            |--------------------------------------------------------------------------
            | التأكد من عدم تكرار رقم الموظف
            |--------------------------------------------------------------------------
            */
            $check = $pdo->prepare("SELECT id FROM employees WHERE employee_no = ? AND is_deleted = 0 LIMIT 1");
            $check->execute([$employeeNo]);

            if ($check->fetch()) {
                throw new Exception('رقم الموظف مستخدم بالفعل لموظف نشط.');
            }

            /*
            |--------------------------------------------------------------------------
            | إنشاء الموظف
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                INSERT INTO employees
                (employee_no, full_name, phone, address, job_title, salary_type, salary, hire_date, status, notes)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $employeeNo,
                $fullName,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $jobTitle !== '' ? $jobTitle : null,
                $salaryType,
                (float)$salary,
                $hireDate !== '' ? $hireDate : null,
                $status,
                $notes !== '' ? $notes : null
            ]);

            $employeeId = (int)$pdo->lastInsertId();

            /*
            |--------------------------------------------------------------------------
            | إنشاء أو استعادة الحساب المالي
            |--------------------------------------------------------------------------
            |
            | نبحث أولاً هل هناك حساب مالي يحمل نفس "رقم الموظف" كان تابعاً لموظف محذوف.
            | إذا وجدناه، نحدثه ونربطه بالموظف الجديد.
            | إذا لم نجده، ننشئ حساباً جديداً.
            |
            */
            
            // البحث عن حساب قديم يحمل نفس رقم الموظف أو نفس employee_id (إن وُجد)
            $accountCheck = $pdo->prepare("
                SELECT id 
                FROM accounts 
                WHERE account_no = ? OR employee_id = ? 
                LIMIT 1 FOR UPDATE
            ");
            $accountCheck->execute([$employeeNo, $employeeId]);
            $existingAccount = $accountCheck->fetch(PDO::FETCH_ASSOC);

            if ($existingAccount) {
                // إذا وجدنا حساباً قديماً، نقوم بتحديث بياناته ليرتبط بالموظف الجديد بدلاً من إظهار خطأ
                $updateAccount = $pdo->prepare("
                    UPDATE accounts 
                    SET 
                        employee_id = ?, 
                        account_name = ?, 
                        status = 'active', 
                        notes = 'تم إعادة ربط الحساب بموظف جديد.'
                    WHERE id = ?
                ");
                $updateAccount->execute([
                    $employeeId,
                    'حساب الموظف: ' . $fullName,
                    $existingAccount['id']
                ]);

            } else {
                // إذا لم نجد حساباً قديماً، ننشئ حساباً جديداً تماماً
                $accountStmt = $pdo->prepare("
                    INSERT INTO accounts
                    (account_no, account_name, account_type, owner_id, employee_id, customer_id, opening_balance, status, notes)
                    VALUES
                    (?, ?, 'employee', NULL, ?, NULL, 0.00, 'active', ?)
                ");

                $accountStmt->execute([
                    $employeeNo,
                    'حساب الموظف: ' . $fullName,
                    $employeeId,
                    'تم إنشاء الحساب تلقائيًا مع إنشاء الموظف.'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | تأكيد العملية
            |--------------------------------------------------------------------------
            */
            $pdo->commit();

            /*
            |--------------------------------------------------------------------------
            | الانتقال إلى الموظف
            |--------------------------------------------------------------------------
            */
            header('Location: view.php?id=' . $employeeId . '&success=created');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>إضافة موظف</h2>
        <p>إضافة الموظف وإنشاء حسابه المالي تلقائيًا</p>
    </div>
    <div>
        <a href="index.php" class="btn">← الموظفين</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="card">
    <h2>بيانات الموظف</h2>

    <form method="POST" action="create.php">

        <div class="form-grid">
            <div class="form-group">
                <label for="employee_no">رقم الموظف</label>
                <input type="text" id="employee_no" name="employee_no" class="form-control" value="<?= htmlspecialchars($employeeNo, ENT_QUOTES, 'UTF-8') ?>" placeholder="EMP-0001" required>
            </div>

            <div class="form-group">
                <label for="full_name">اسم الموظف</label>
                <input type="text" id="full_name" name="full_name" class="form-control" value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">رقم الهاتف</label>
                <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label for="address">العنوان</label>
                <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label for="job_title">الوظيفة</label>
                <input type="text" id="job_title" name="job_title" class="form-control" value="<?= htmlspecialchars($jobTitle, ENT_QUOTES, 'UTF-8') ?>" placeholder="عامل طرمبة">
            </div>

            <div class="form-group">
                <label for="salary_type">نوع الأجر</label>
                <select id="salary_type" name="salary_type" class="form-control">
                    <option value="monthly" <?= $salaryType === 'monthly' ? 'selected' : '' ?>>شهري</option>
                    <option value="daily" <?= $salaryType === 'daily' ? 'selected' : '' ?>>يومي</option>
                    <option value="hourly" <?= $salaryType === 'hourly' ? 'selected' : '' ?>>بالساعة</option>
                    <option value="shift" <?= $salaryType === 'shift' ? 'selected' : '' ?>>بالوردية</option>
                </select>
            </div>

            <div class="form-group">
                <label for="salary">قيمة الأجر</label>
                <input type="number" id="salary" name="salary" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($salary, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label for="hire_date">تاريخ التوظيف</label>
                <input type="date" id="hire_date" name="hire_date" class="form-control" value="<?= htmlspecialchars($hireDate, ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
                <label for="status">الحالة</label>
                <select id="status" name="status" class="form-control">
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>نشط</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>غير نشط</option>
                    <option value="vacation" <?= $status === 'vacation' ? 'selected' : '' ?>>إجازة</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="notes">ملاحظات</label>
            <textarea id="notes" name="notes" class="form-control" rows="4"><?= htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="alert">
            <strong>الحساب المالي:</strong>
            سيتم إنشاء حساب مالي تلقائيًا للموظف برقم الحساب:
            <strong><?= htmlspecialchars($employeeNo ?: 'EMP-0001', ENT_QUOTES, 'UTF-8') ?></strong>
            وبرصيد افتتاحي 0.00.
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                💾 حفظ الموظف وإنشاء الحساب
            </button>
            <a href="index.php" class="btn">
                إلغاء
            </a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>