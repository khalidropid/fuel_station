<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php'; 

$pageTitle = 'إغلاق الوردية وجرد الصندوق';

$shiftId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$shiftId) {
    header('Location: index.php');
    exit;
}

$error = '';

try {
    // 1. جلب بيانات الوردية
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ? AND status = 'open' LIMIT 1");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        throw new Exception('الوردية غير موجودة أو مغلقة مسبقاً.');
    }

    // 2. جلب الطرمبات المرتبطة (مع إضافة p.tank_id لخصم الكمية لاحقاً)
    $pumpStmt = $pdo->prepare("
        SELECT 
            sp.id AS shift_pump_id, 
            sp.pump_id, 
            p.tank_id, 
            p.name AS pump_name, 
            p.pump_no,
            ft.name AS fuel_name,
            sp.opening_reading,
            sp.total_liters AS recorded_liters,
            COALESCE((
                SELECT fp.price 
                FROM fuel_prices fp 
                WHERE fp.fuel_type_id = t.fuel_type_id 
                  AND fp.effective_from <= ?
                ORDER BY fp.effective_from DESC LIMIT 1
            ), 0) AS liter_price
        FROM shift_pumps sp
        INNER JOIN pumps p ON p.id = sp.pump_id
        INNER JOIN tanks t ON t.id = p.tank_id
        INNER JOIN fuel_types ft ON ft.id = t.fuel_type_id
        WHERE sp.shift_id = ?
    ");
    $pumpStmt->execute([$shift['start_datetime'], $shiftId]);
    $shiftPumps = $pumpStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. جلب العملاء
    $customersStmt = $pdo->query("SELECT id, full_name, customer_no FROM customers WHERE status = 'active'");
    $customers = $customersStmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. جلب المسحوبات السابقة
    $withStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM employee_withdrawals WHERE shift_id = ?");
    $withStmt->execute([$shiftId]);
    $existingWithdrawals = (float)$withStmt->fetchColumn();

    // === معالجة الإغلاق (POST) ===
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $closingReadings = $_POST['closing_readings'] ?? [];
        $cardTotal = (float)($_POST['card_total'] ?? 0);
        $closingCash = (float)($_POST['closing_cash'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $endDatetime = date('Y-m-d H:i:s');
        $userId = $_SESSION['user_id'] ?? 1;

        $creditCustomerIds = $_POST['credit_customer_id'] ?? [];
        $creditPumpIds = $_POST['credit_pump_id'] ?? [];
        $creditTimes = $_POST['credit_time'] ?? [];
        $creditAmounts = $_POST['credit_amount'] ?? [];

        $withAmounts = $_POST['withdrawal_amount'] ?? [];
        $withNotes = $_POST['withdrawal_notes'] ?? [];

        $pdo->beginTransaction();

        // Serialize closing against sales being entered for this shift.
        $lockShift = $pdo->prepare("SELECT * FROM shifts WHERE id = ? AND status = 'open' LIMIT 1 FOR UPDATE");
        $lockShift->execute([$shiftId]);
        $lockedShift = $lockShift->fetch(PDO::FETCH_ASSOC);
        if (!$lockedShift) {
            throw new Exception('الوردية أُغلقت أو تغيرت أثناء المعالجة. أعد تحميل الصفحة.');
        }
        $shift = $lockedShift;

        $postedSalesStmt = $pdo->prepare("
            SELECT COUNT(*) AS sale_count,
                   COALESCE(SUM(total), 0) AS total_sales,
                   COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total ELSE 0 END), 0) AS cash_sales,
                   COALESCE(SUM(CASE WHEN payment_method = 'card' THEN total ELSE 0 END), 0) AS card_sales,
                   COALESCE(SUM(CASE WHEN payment_method = 'credit' THEN total ELSE 0 END), 0) AS credit_sales
            FROM sales WHERE shift_id = ?
        ");
        $postedSalesStmt->execute([$shiftId]);
        $postedSales = $postedSalesStmt->fetch(PDO::FETCH_ASSOC);
        $hasPostedSales = (int)$postedSales['sale_count'] > 0;

        $totalMeterSales = 0;
        
        // تجهيز أوامر تحديث العدادات والخزانات
        $updateShiftPump = $pdo->prepare("UPDATE shift_pumps SET closing_reading = ?, total_liters = ?, total_sales = ? WHERE id = ?");
        $insertReading = $pdo->prepare("INSERT INTO pump_readings (pump_id, reading, reading_date, reading_type, employee_id, notes) VALUES (?, ?, ?, 'closing', ?, ?)");
        
        // 🌟 تجهيز أوامر المخزون
        $updateTankVolume = $pdo->prepare("UPDATE tanks SET current_volume = current_volume - ? WHERE id = ?");
        $insertTankMovement = $pdo->prepare("
            INSERT INTO tank_movements (tank_id, movement_type, quantity, reference_id, movement_date, notes, created_by)
            VALUES (?, 'sale', ?, ?, ?, ?, ?)
        ");

        // أ. معالجة قراءات العدادات وخصم المخزون
        foreach ($shiftPumps as $pump) {
            $cReading = (float)($closingReadings[$pump['shift_pump_id']] ?? 0);
            $oReading = (float)$pump['opening_reading'];
            
            if ($cReading < $oReading) {
                throw new Exception("قراءة النهاية للطرمبة ({$pump['pump_name']}) غير صحيحة.");
            }
            
            $liters = $cReading - $oReading;
            $salesValue = $liters * (float)$pump['liter_price'];
            $totalMeterSales += $salesValue;

            if ($hasPostedSales && abs($liters - (float)$pump['recorded_liters']) > 0.02) {
                throw new Exception("قراءة العداد للطرمبة ({$pump['pump_name']}) لا تطابق الكمية المسجلة في شاشة المبيعات. راجع الفواتير أو سجّل المبيعات الناقصة قبل الإغلاق.");
            }
            
            // 1. تحديث الوردية والعدادات
            $updateShiftPump->execute([$cReading, $liters, $salesValue, $pump['shift_pump_id']]);
            $insertReading->execute([$pump['pump_id'], $cReading, $endDatetime, $shift['employee_id'], "إغلاق وردية " . $shift['shift_no']]);

            // 2. 🌟 خصم المخزون من الخزان المرتبط بالطرمبة وتسجيل الحركة
            if ($liters > 0 && !$hasPostedSales) {
                $updateTankVolume->execute([$liters, $pump['tank_id']]);
                
                $movementNotes = "سحب مبيعات من الطرمبة ({$pump['pump_name']}) في الوردية رقم: " . $shift['shift_no'];
                // تُسجل الكمية بالسالب لأنها عملية خروج وقود (sale)
                $insertTankMovement->execute([$pump['tank_id'], -$liters, $shiftId, $endDatetime, $movementNotes, $userId]);
            }
        }

        if ($hasPostedSales) {
            // Sales entry already reduced tank stock and posted its journal.
            // Do not post those movements again during shift close.
            $totalMeterSales = (float)$postedSales['total_sales'];
            $cardTotal = (float)$postedSales['card_sales'];
        }

        // ب. تسجيل الديون
        $totalCreditSales = 0;
        $insertCredit = $pdo->prepare("INSERT INTO shift_credits (shift_id, customer_id, pump_id, amount, credit_time) VALUES (?, ?, ?, ?, ?)");
        $insertCustomerTx = $pdo->prepare("INSERT INTO customer_transactions (customer_id, transaction_type, reference_id, amount, transaction_date, description, created_by) VALUES (?, 'sale', ?, ?, ?, ?, ?)");
        $updateCustomerBalance = $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?");
        
        $customerEntries = []; 

        $hasManualCreditInput = false;
        foreach ($creditAmounts as $creditAmountInput) {
            if ((float)$creditAmountInput > 0) {
                $hasManualCreditInput = true;
                break;
            }
        }
        if ($hasPostedSales && $hasManualCreditInput) {
            throw new Exception('تم تسجيل مبيعات هذه الوردية من شاشة المبيعات؛ لا تدخل ديونًا إضافية في شاشة الإغلاق.');
        }

        for ($i = 0; !$hasPostedSales && $i < count($creditCustomerIds); $i++) {
            $cId = (int)$creditCustomerIds[$i];
            $pId = (int)$creditPumpIds[$i];
            $cTime = $creditTimes[$i] ?: date('H:i');
            $cAmount = (float)$creditAmounts[$i];

            if ($cId > 0 && $cAmount > 0) {
                $insertCredit->execute([$shiftId, $cId, $pId, $cAmount, $cTime]);
                $desc = "تعبئة آجل - وردية: " . $shift['shift_no'];
                $insertCustomerTx->execute([$cId, $shiftId, $cAmount, $endDatetime, $desc, $userId]);
                $updateCustomerBalance->execute([$cAmount, $cId]);
                $totalCreditSales += $cAmount;

                $custAccount = accountingEnsureCustomerAccount($pdo, $cId, true);
                $customerEntries[] = [
                    'account_id' => (int)$custAccount['id'],
                    'debit' => $cAmount,
                    'credit' => 0,
                    'description' => $desc
                ];
            }
        }

        if ($hasPostedSales) {
            $totalCreditSales = (float)$postedSales['credit_sales'];
        }

        // ج. مسحوبات الموظف
        $totalNewWithdrawals = 0;
        $insertWithdrawal = $pdo->prepare("INSERT INTO employee_withdrawals (employee_id, shift_id, amount, withdrawal_date, withdrawal_type, description) VALUES (?, ?, ?, ?, 'personal', ?)");
        for ($i = 0; $i < count($withAmounts); $i++) {
            $wAmt = (float)$withAmounts[$i];
            $wNote = trim($withNotes[$i] ?? '');
            if ($wAmt > 0) {
                $insertWithdrawal->execute([$shift['employee_id'], $shiftId, $wAmt, $endDatetime, $wNote]);
                $totalNewWithdrawals += $wAmt;
            }
        }
        $finalWithdrawalsTotal = $existingWithdrawals + $totalNewWithdrawals;

        // د. حساب الصندوق النهائي
        $expectedCash = ($totalMeterSales + $shift['opening_cash']) - $finalWithdrawalsTotal - $cardTotal - $totalCreditSales;
        $difference = $closingCash - $expectedCash;
        $shortage = ($difference < 0) ? abs($difference) : 0.00;

        if ($shortage > 0) {
            $shortageDesc = "عجز جرد في الوردية: " . $shift['shift_no'];
            $insertWithdrawal->execute([$shift['employee_id'], $shiftId, $shortage, $endDatetime, $shortageDesc]);
            $finalWithdrawalsTotal += $shortage;
        }

        // هـ. إغلاق الوردية
        $updateShift = $pdo->prepare("
            UPDATE shifts SET status = 'closed', end_datetime = ?, closing_cash = ?, total_sales = ?, card_total = ?, total_withdrawals = ?, expected_cash = ?, difference = ?, shortage = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?
        ");
        $updateShift->execute([$endDatetime, $closingCash, $totalMeterSales, $cardTotal, $finalWithdrawalsTotal, $expectedCash, $difference, $shortage, $notes, $shiftId]);

        // =====================================================================
        // القيود المحاسبية التلقائية 
        // =====================================================================
        $accountingEntries = [];
        $salesAccount = accountingEnsureAccount($pdo, 'REV-SALES', 'إيرادات المبيعات', 'other', null, null, null, 'حساب مبيعات الوقود');
        $cashAccount = accountingEnsureCashAccount($pdo);
        $empAccount = accountingEnsureEmployeeAccount($pdo, $shift['employee_id'], true);

        // 1. المبيعات النقدية
        $cashSales = $hasPostedSales
            ? (float)$postedSales['cash_sales']
            : $totalMeterSales - $cardTotal - $totalCreditSales;
        if ($cashSales > 0) {
            $accountingEntries[] = ['account_id' => (int)$cashAccount['id'], 'debit' => $cashSales, 'credit' => 0, 'description' => 'مبيعات نقدية للوردية ' . $shift['shift_no']];
            $accountingEntries[] = ['account_id' => (int)$salesAccount['id'], 'debit' => 0, 'credit' => $cashSales, 'description' => 'إيراد مبيعات نقدية'];
        }

        // 2. المبيعات الآجلة
        foreach ($customerEntries as $ce) {
            $accountingEntries[] = $ce; 
            $accountingEntries[] = ['account_id' => (int)$salesAccount['id'], 'debit' => 0, 'credit' => $ce['debit'], 'description' => $ce['description']];
        }

        // 3. المسحوبات النقدية
        if ($totalNewWithdrawals > 0) {
            $accountingEntries[] = ['account_id' => (int)$empAccount['id'], 'debit' => $totalNewWithdrawals, 'credit' => 0, 'description' => 'مسحوبات نقدية أثناء الوردية ' . $shift['shift_no']];
            $accountingEntries[] = ['account_id' => (int)$cashAccount['id'], 'debit' => 0, 'credit' => $totalNewWithdrawals, 'description' => 'صرف مسحوبات للموظف'];
        }

        // 4. العجز
        if ($shortage > 0) {
            $accountingEntries[] = ['account_id' => (int)$empAccount['id'], 'debit' => $shortage, 'credit' => 0, 'description' => 'عجز جرد للوردية ' . $shift['shift_no']];
            $accountingEntries[] = ['account_id' => (int)$cashAccount['id'], 'debit' => 0, 'credit' => $shortage, 'description' => 'تسوية عجز صندوق الوردية'];
        }

        if (!empty($accountingEntries)) {
            $transactionNo = accountingGenerateNo('SHF');
            accountingPostBalancedTransaction($pdo, 'shift_close', 'employee', $shift['employee_id'], $accountingEntries, $endDatetime, 'قيد إغلاق الوردية ' . $shift['shift_no'], $userId, $transactionNo);
        }

        $pdo->commit();
        header('Location: view.php?id=' . $shiftId . '&success=closed');
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>جرد وإغلاق الوردية: <?= htmlspecialchars($shift['shift_no'] ?? '') ?></h2>
        <p>أدخل بيانات الإغلاق: العدادات، الشبكة، ديون العملاء، ومسحوبات الموظف.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="closeShiftForm">
    
    <!-- 1. قراءات الطرمبات -->
    <div class="card mb-4">
        <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">1. قراءات نهاية الوردية (العدادات)</h3>
        <div class="table-responsive">
            <table class="table">
                <thead style="background-color: #f8f9fa;">
                    <tr>
                        <th>الطرمبة</th>
                        <th>الوقود</th>
                        <th>قراءة البداية</th>
                        <th>قراءة النهاية</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shiftPumps as $pump): ?>
                        <tr>
                            <td><?= htmlspecialchars($pump['pump_name']) ?> <small>(<?= htmlspecialchars($pump['pump_no']) ?>)</small></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($pump['fuel_name']) ?></span></td>
                            <td style="color: blue; font-weight: bold; font-size: 1.1em;">
                                <?= number_format((float)$pump['opening_reading'], 2) ?>
                            </td>
                            <td>
                                <input type="number" name="closing_readings[<?= $pump['shift_pump_id'] ?>]" class="form-control" step="0.01" min="<?= (float)$pump['opening_reading'] ?>" required placeholder="أدخل القراءة الجديدة">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 2. مبيعات الشبكة / البطاقة -->
    <div class="card mb-4">
        <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">2. إجمالي مبيعات الشبكة (البطاقة)</h3>
        <div class="form-group row">
            <div class="col-md-4">
                <label>إجمالي مبلغ الشبكة المسحوب 💳</label>
                <input type="number" name="card_total" class="form-control" step="0.01" min="0" value="0" required>
            </div>
        </div>
    </div>

    <!-- 3. المبيعات الآجلة (الديون) -->
    <div class="card mb-4">
        <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">3. المبيعات الآجلة (ديون العملاء)</h3>
        <div class="table-responsive">
            <table class="table" id="creditTable">
                <thead style="background-color: #f8f9fa;">
                    <tr>
                        <th>العميل</th>
                        <th>من أي طرمبة؟</th>
                        <th>وقت التعبئة</th>
                        <th>المبلغ</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody id="creditBody">
                    <tr>
                        <td>
                            <select name="credit_customer_id[]" class="form-control">
                                <option value="">-- اختر العميل --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="credit_pump_id[]" class="form-control">
                                <?php foreach ($shiftPumps as $p): ?>
                                    <option value="<?= $p['pump_id'] ?>"><?= htmlspecialchars($p['pump_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="time" name="credit_time[]" class="form-control" value="<?= date('H:i') ?>">
                        </td>
                        <td>
                            <input type="number" name="credit_amount[]" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger remove-row">X</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-secondary" id="addRowBtn">+ إضافة دين آخر</button>
        </div>
    </div>

    <!-- 4. المسحوبات الشخصية -->
    <div class="card mb-4">
        <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">4. مسحوبات الموظف (النقدية التي سحبها أثناء الوردية)</h3>
        <div class="table-responsive">
            <table class="table" id="withdrawTable">
                <thead style="background-color: #fdf3f4;">
                    <tr>
                        <th>المبلغ المسحوب</th>
                        <th>السبب / البيان</th>
                        <th>إجراء</th>
                    </tr>
                </thead>
                <tbody id="withdrawBody">
                    <tr>
                        <td>
                            <input type="number" name="withdrawal_amount[]" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </td>
                        <td>
                            <input type="text" name="withdrawal_notes[]" class="form-control" placeholder="مثال: غداء، سلفة، شراء أدوات">
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger remove-row">X</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-secondary" id="addWithdrawBtn">+ إضافة مسحوب آخر</button>
        </div>
    </div>

    <!-- 5. الكاش النهائي والتسليم -->
    <div class="card mb-4">
        <h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">5. تسليم العهدة (الكاش)</h3>
        
        <div class="form-group row">
            <div class="col-md-6">
                <label style="font-weight: bold; color: green; font-size: 1.1em;">النقد المُسلّم بيده (الكاش الفعلي) 💵</label>
                <input type="number" name="closing_cash" class="form-control input-lg" style="font-size: 1.5em; font-weight: bold; color: green;" step="0.01" min="0" required placeholder="المبلغ الذي سيسلمه الكاشير">
                <small style="color:red; font-weight:bold; display:block; margin-top:5px;">ملاحظة: سيتم ترحيل هذه البيانات فوراً للحسابات وخصم المخزون من الخزانات.</small>
            </div>
        </div>

        <div class="form-group">
            <label>ملاحظات الجرد (اختياري)</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
        </div>

        <div class="form-actions mt-4">
            <button type="submit" class="btn btn-warning btn-lg" onclick="return confirm('تأكيد إغلاق الوردية؟ سيتم خصم الوقود المباع من الخزانات وترحيل الماليات بشكل نهائي.')">إغلاق الوردية وترحيل الحسابات</button>
            <a href="view.php?id=<?= $shiftId ?>" class="btn btn-secondary btn-lg">تراجع</a>
        </div>
    </div>
</form>

<script>
document.getElementById('addRowBtn').addEventListener('click', function() {
    var tbody = document.getElementById('creditBody');
    var newRow = tbody.querySelector('tr').cloneNode(true);
    newRow.querySelector('select[name="credit_customer_id[]"]').value = "";
    newRow.querySelector('input[name="credit_amount[]"]').value = "";
    tbody.appendChild(newRow);
});

document.getElementById('addWithdrawBtn').addEventListener('click', function() {
    var tbody = document.getElementById('withdrawBody');
    var newRow = tbody.querySelector('tr').cloneNode(true);
    newRow.querySelector('input[name="withdrawal_amount[]"]').value = "";
    newRow.querySelector('input[name="withdrawal_notes[]"]').value = "";
    tbody.appendChild(newRow);
});

document.addEventListener('click', function(e) {
    if(e.target && e.target.classList.contains('remove-row')) {
        var tbody = e.target.closest('tbody');
        if(tbody.getElementsByTagName('tr').length > 1) {
            e.target.closest('tr').remove();
        } else {
            alert('يجب ترك سطر واحد على الأقل.');
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>7
