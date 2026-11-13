<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'صرف رواتب الموظفين';
$error = ''; $success = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? 'create';
        $userId = accountingUserId();

        if ($action === 'create') {
            $employeeId = (int)($_POST['employee_id'] ?? 0);
            $month = trim($_POST['salary_month'] ?? date('Y-m-01'));
            
            // الراتب والبدلات والخصومات
            $basic = (float)($_POST['basic_salary'] ?? 0);
            $allow = (float)($_POST['allowances'] ?? 0);
            $ded = (float)($_POST['deductions'] ?? 0);
            
            // المبلغ الذي سيتم تسليمه كاش للموظف الآن
            $paidCash = (float)($_POST['paid_cash'] ?? 0);
            
            $notes = trim($_POST['notes'] ?? '');

            if ($employeeId <= 0) throw new Exception('اختر الموظف.');
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) throw new Exception('الشهر غير صحيح.');
            $salaryMonth = $month . '-01';
            
            if ($basic < 0 || $allow < 0 || $ded < 0 || $paidCash < 0) {
                throw new Exception('القيم لا يمكن أن تكون سالبة.');
            }

            $pdo->beginTransaction();
            
            // 🌟 التعديل هنا: جلب حساب الموظف المالي مباشرة بالـ ID لتجنب خطأ التكرار
            $stmtAcc = $pdo->prepare("SELECT id FROM accounts WHERE employee_id = ? AND account_type = 'employee' LIMIT 1");
            $stmtAcc->execute([$employeeId]);
            $employeeAccount = $stmtAcc->fetch(PDO::FETCH_ASSOC);
            
            if (!$employeeAccount) {
                throw new Exception('لم يتم العثور على حساب مالي لهذا الموظف. يرجى مراجعة دليل الحسابات.');
            }

            $cashAccount = accountingEnsureCashAccount($pdo);
            $expenseAccount = accountingEnsureAccount($pdo, 'EXP-SALARY', 'مصروف الرواتب', 'expense', null, null, null, 'حساب مصروف الرواتب.');

            // حساب الاستحقاق (الراتب الأساسي + البدلات - خصومات الغياب)
            $netAccrued = round($basic + $allow - $ded, 2);
            if ($netAccrued < 0) throw new Exception('صافي استحقاق الراتب لا يمكن أن يكون سالبًا.');

            // التأكد من عدم صرف الراتب مرتين لنفس الشهر
            $q = $pdo->prepare("SELECT id FROM salaries WHERE employee_id=? AND salary_month=? AND status<>'cancelled' LIMIT 1 FOR UPDATE");
            $q->execute([$employeeId, $salaryMonth]);
            if ($q->fetch()) throw new Exception('تم إثبات وصرف راتب لهذا الموظف في هذا الشهر مسبقاً.');

            // 1. إدراج سجل الراتب في قاعدة البيانات
            $status = ($paidCash > 0) ? 'paid' : 'pending';
            $stmt = $pdo->prepare("
                INSERT INTO salaries (employee_id, salary_month, basic_salary, allowances, deductions, withdrawals, net_salary, status, paid_date, notes)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $employeeId, $salaryMonth, $basic, $allow, $ded, $netAccrued,
                $status, $status === 'paid' ? date('Y-m-d H:i:s') : null, $notes !== '' ? $notes : null
            ]);
            $salaryId = (int)$pdo->lastInsertId();

            $entries = [];

            // 2. قيد استحقاق الراتب (إثبات الراتب للموظف)
            if ($netAccrued > 0) {
                $entries[] = ['account_id' => (int)$expenseAccount['id'], 'debit' => $netAccrued, 'credit' => 0, 'description' => 'إثبات مصروف راتب شهر - ' . $month];
                $entries[] = ['account_id' => (int)$employeeAccount['id'], 'debit' => 0, 'credit' => $netAccrued, 'description' => 'استحقاق راتب شهر - ' . $month];
            }

            // 3. قيد الدفع النقدي (إذا تم تسليم كاش للموظف)
            if ($paidCash > 0) {
                $entries[] = ['account_id' => (int)$employeeAccount['id'], 'debit' => $paidCash, 'credit' => 0, 'description' => 'صرف نقدية من الراتب - ' . $month];
                $entries[] = ['account_id' => (int)$cashAccount['id'], 'debit' => 0, 'credit' => $paidCash, 'description' => 'صرف راتب الموظف نقدًا'];
            }

            // ترحيل القيود
            if (!empty($entries)) {
                $transactionNo = accountingGenerateNo('SAL');
                accountingPostBalancedTransaction(
                    $pdo, 'salary', 'employee', $employeeId,
                    $entries, date('Y-m-d H:i:s'), 'إثبات وصرف راتب - ' . $month, $userId, $transactionNo
                );
            }

            $pdo->commit();
            $success = 'تم إثبات الراتب وصرف المبلغ وتحديث كشف حساب الموظف بنجاح.';
        }
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $error = $e->getMessage();
}

// جلب الموظفين مع رواتبهم الأساسية وحساب أرصدتهم الحالية
$employeesQuery = $pdo->query("SELECT id, employee_no, full_name, salary FROM employees WHERE status='active' AND is_deleted=0 ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

// 🌟 التعديل هنا أيضاً: استخدام استعلام مباشر لجلب حساب الموظف للواجهة
$employees = [];
$stmtAccSearch = $pdo->prepare("SELECT id FROM accounts WHERE employee_id = ? AND account_type = 'employee' LIMIT 1");

foreach ($employeesQuery as $emp) {
    $stmtAccSearch->execute([$emp['id']]);
    $empAccount = $stmtAccSearch->fetch(PDO::FETCH_ASSOC);
    
    if ($empAccount) {
        $balanceData = accountingGetBalance($pdo, $empAccount['id']);
        $emp['current_balance'] = $balanceData['balance']; 
    } else {
        $emp['current_balance'] = 0;
    }
    
    $employees[] = $emp;
}

$rows = $pdo->query("
    SELECT s.*, e.employee_no, e.full_name
    FROM salaries s 
    JOIN employees e ON e.id = s.employee_id
    ORDER BY s.salary_month DESC, s.id DESC LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>صرف رواتب الموظفين</h2>
        <p>إثبات استحقاق الراتب نهاية الشهر، وتسوية المسحوبات والعجز تلقائياً من كشف الحساب، ثم صرف المتبقي.</p>
    </div>
    <a href="../accounts/index.php" class="btn btn-secondary">دليل الحسابات</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="row" style="display: flex; gap: 20px; flex-wrap: wrap;">
    
    <!-- فورم الصرف -->
    <div class="card" style="flex: 2; min-width: 300px;">
        <h3>إثبات وصرف راتب</h3>
        <form method="post">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>الموظف</label>
                <select name="employee_id" id="employee_id" class="form-control" required>
                    <option value="">-- اختر الموظف --</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>" 
                                data-salary="<?= $e['salary'] ?>" 
                                data-balance="<?= $e['current_balance'] ?>">
                            <?= htmlspecialchars($e['employee_no'] . ' - ' . $e['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>شهر الاستحقاق</label>
                <input type="month" name="salary_month" class="form-control" value="<?= htmlspecialchars(date('Y-m')) ?>" required>
            </div>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e5e7eb;">
                <div class="form-group">
                    <label>الراتب الأساسي (تم جلبه من ملف الموظف)</label>
                    <input type="number" step="0.01" min="0" name="basic_salary" id="basic_salary" class="form-control" required readonly style="background: #e9ecef;">
                </div>
                
                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label>إضافات / مكافآت (+)</label>
                        <input type="number" step="0.01" min="0" name="allowances" id="allowances" class="form-control" value="0">
                    </div>
                    
                    <div class="form-group" style="flex: 1;">
                        <label>خصومات غياب/إدارية (-)</label>
                        <input type="number" step="0.01" min="0" name="deductions" id="deductions" class="form-control" value="0">
                    </div>
                </div>
            </div>

            <!-- عرض الرصيد المحاسبي المتبقي -->
            <div id="balance_info_box" style="display: none; background: #e6fced; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #16a34a;">
                <h4 style="margin-top: 0; color: #15803d; font-size: 16px;">الخلاصة المحاسبية للموظف</h4>
                <p style="margin: 5px 0; font-size: 14px;">استحقاق هذا الشهر: <strong id="calc_accrued">0</strong></p>
                <p style="margin: 5px 0; font-size: 14px;">رصيد الموظف القديم (مسحوبات أو سلف): <strong id="calc_old_balance">0</strong></p>
                <hr style="border-color: #16a34a; opacity: 0.3;">
                <p style="margin: 5px 0; font-size: 16px; font-weight: bold;">المبلغ الفعلي المسموح بصرفه الآن: <span id="calc_final_payable" style="color: blue;">0</span></p>
            </div>

            <div class="form-group">
                <label style="color: green; font-weight: bold;">المبلغ المراد تسليمه كاش للموظف الآن 💵</label>
                <input type="number" step="0.01" min="0" name="paid_cash" id="paid_cash" class="form-control" style="border: 2px solid green; font-size: 1.2em;" value="0">
            </div>

            <div class="form-group">
                <label>ملاحظات السند</label>
                <textarea name="notes" class="form-control"></textarea>
            </div>
            
            <button class="btn btn-primary btn-lg" style="width: 100%;">اعتماد الراتب وصرف النقدية</button>
        </form>
    </div>

</div>

<!-- جدول الرواتب السابقة -->
<div class="card mt-4">
    <h3>سجل الرواتب المُثبتة</h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>الشهر</th>
                    <th>الموظف</th>
                    <th>الأساسي</th>
                    <th>الصافي المُستحق</th>
                    <th>تاريخ الإثبات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars(substr($r['salary_month'], 0, 7)) ?></td>
                        <td><?= htmlspecialchars($r['employee_no'] . ' - ' . $r['full_name']) ?></td>
                        <td><?= number_format($r['basic_salary'], 2) ?></td>
                        <td style="font-weight: bold;"><?= number_format($r['net_salary'], 2) ?></td>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const empSelect = document.getElementById('employee_id');
    const basicInput = document.getElementById('basic_salary');
    const allowInput = document.getElementById('allowances');
    const dedInput = document.getElementById('deductions');
    const cashInput = document.getElementById('paid_cash');
    
    const balanceBox = document.getElementById('balance_info_box');
    const calcAccrued = document.getElementById('calc_accrued');
    const calcOldBalance = document.getElementById('calc_old_balance');
    const calcFinalPayable = document.getElementById('calc_final_payable');

    function calculatePayroll() {
        if (empSelect.selectedIndex <= 0) {
            balanceBox.style.display = 'none';
            return;
        }

        const option = empSelect.options[empSelect.selectedIndex];
        const basic = parseFloat(basicInput.value) || 0;
        const allow = parseFloat(allowInput.value) || 0;
        const ded = parseFloat(dedInput.value) || 0;
        
        // الرصيد المحاسبي: إذا كان سالب يعني له (دائن)، وإذا موجب يعني عليه (مدين/مسحوبات)
        const currentBalance = parseFloat(option.dataset.balance) || 0; 

        // استحقاق الشهر
        const accruedThisMonth = basic + allow - ded;
        
        // المستحق النهائي = استحقاق الشهر - (الرصيد المدين عليه) أو + (الرصيد الدائن له)
        const finalPayable = accruedThisMonth - currentBalance;

        calcAccrued.textContent = accruedThisMonth.toFixed(2);
        
        if (currentBalance > 0) {
            calcOldBalance.textContent = currentBalance.toFixed(2) + ' (عليه مسحوبات/عجز)';
            calcOldBalance.style.color = 'red';
        } else if (currentBalance < 0) {
            calcOldBalance.textContent = Math.abs(currentBalance).toFixed(2) + ' (له مستحقات سابقة)';
            calcOldBalance.style.color = 'green';
        } else {
            calcOldBalance.textContent = '0.00 (متزن)';
            calcOldBalance.style.color = 'black';
        }

        calcFinalPayable.textContent = finalPayable > 0 ? finalPayable.toFixed(2) : '0.00 (لا يوجد مبلغ مستحق للصرف)';
        
        // اقتراح مبلغ الدفع تلقائياً
        cashInput.value = finalPayable > 0 ? finalPayable.toFixed(2) : 0;
        
        balanceBox.style.display = 'block';
    }

    empSelect.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        basicInput.value = option.dataset.salary || 0;
        calculatePayroll();
    });

    allowInput.addEventListener('input', calculatePayroll);
    dedInput.addEventListener('input', calculatePayroll);
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>