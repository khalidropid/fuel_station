<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'تفاصيل الوردية';

$shiftId = (int)($_GET['id'] ?? 0);

if ($shiftId <= 0) {
    header('Location: index.php');
    exit;
}

try {

    // 1. بيانات الوردية والموظف
    $stmt = $pdo->prepare("
        SELECT s.*, e.employee_no, e.full_name AS employee_name, e.job_title
        FROM shifts s
        INNER JOIN employees e ON e.id = s.employee_id
        WHERE s.id = ? LIMIT 1
    ");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        throw new Exception('الوردية غير موجودة.');
    }

    // 2. الطرمبات المرتبطة بالوردية
    $pumpStmt = $pdo->prepare("
        SELECT sp.*, p.pump_no, p.name AS pump_name, t.tank_no, t.name AS tank_name, ft.name AS fuel_name
        FROM shift_pumps sp
        INNER JOIN pumps p ON p.id = sp.pump_id
        INNER JOIN tanks t ON t.id = p.tank_id
        INNER JOIN fuel_types ft ON ft.id = t.fuel_type_id
        WHERE sp.shift_id = ? ORDER BY sp.id ASC
    ");
    $pumpStmt->execute([$shiftId]);
    $shiftPumps = $pumpStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. جلب الديون الآجلة المسجلة للوردية (shift_credits)
    $creditsStmt = $pdo->prepare("
        SELECT sc.*, c.full_name AS customer_name, p.name AS pump_name 
        FROM shift_credits sc
        INNER JOIN customers c ON sc.customer_id = c.id
        INNER JOIN pumps p ON sc.pump_id = p.id
        WHERE sc.shift_id = ?
    ");
    $creditsStmt->execute([$shiftId]);
    $shiftCreditsList = $creditsStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalShiftCredits = array_sum(array_column($shiftCreditsList, 'amount'));

    // 4. جلب المسحوبات المسجلة
    $withStmt = $pdo->prepare("SELECT * FROM employee_withdrawals WHERE shift_id = ?");
    $withStmt->execute([$shiftId]);
    $shiftWithdrawalsList = $withStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalWithdrawals = (float)$shift['total_withdrawals']; 

    // === الحسابات النقدية والنهائية (مأخوذة من جدول الوردية مباشرة) ===
    $totalSalesFromMeters = (float)$shift['total_sales'];
    $cardTotal = (float)$shift['card_total'];
    
    // المبيعات النقدية = (إجمالي العدادات - الشبكة - الآجل)
    $cashSales = $totalSalesFromMeters - $cardTotal - $totalShiftCredits;

} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2>تفاصيل الوردية</h2>
        <p><?= isset($shift) ? htmlspecialchars($shift['shift_no']) : '' ?></p>
    </div>
    <div>
        <a href="index.php" class="btn">العودة للورديات</a>
        <?php if (isset($shift) && $shift['status'] === 'open'): ?>
            <a href="close.php?id=<?= (int)$shift['id'] ?>" class="btn btn-warning">إغلاق الوردية</a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php else: ?>

<!-- الإحصائيات -->
<div class="cards">
    <div class="card stat-card">
        <div class="stat-icon">👨‍🔧</div>
        <div class="stat-info">
            <span>الموظف</span>
            <strong><?= htmlspecialchars($shift['employee_name']) ?></strong>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
            <span>المبيعات النقدية الكاش</span>
            <strong><?= number_format($cashSales, 2) ?></strong>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon">💳</div>
        <div class="stat-info">
            <span>مبيعات الشبكة/الآجل</span>
            <strong><?= number_format($cardTotal + $totalShiftCredits, 2) ?></strong>
        </div>
    </div>
    <div class="card stat-card">
        <div class="stat-icon">💸</div>
        <div class="stat-info">
            <span>إجمالي المسحوبات</span>
            <strong><?= number_format($totalWithdrawals, 2) ?></strong>
        </div>
    </div>
</div>

<!-- الطرمبات وقراءات العداد -->
<div class="card">
    <h2>طرمبات الوردية والمبلغ المستحق من العداد</h2>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr style="background-color: #f4f4f4;">
                    <th>الطرمبة</th>
                    <th>الوقود</th>
                    <th style="color: blue;">قراءة البداية</th>
                    <th style="color: red;">قراءة النهاية</th>
                    <th>اللترات المنصرفة</th>
                    <th style="color: #d9534f;">المبلغ المستحق (العداد)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shiftPumps)): ?>
                    <tr><td colspan="6" style="text-align:center;">لا توجد طرمبات مرتبطة.</td></tr>
                <?php else: ?>
                    <?php foreach ($shiftPumps as $pump): ?>
                        <tr>
                            <td><?= htmlspecialchars($pump['pump_name']) ?> (<?= htmlspecialchars($pump['pump_no']) ?>)</td>
                            <td><?= htmlspecialchars($pump['fuel_name']) ?></td>
                            <td style="font-weight: bold; color: blue;"><?= number_format((float)$pump['opening_reading'], 2) ?></td>
                            <td style="font-weight: bold; color: red;">
                                <?= $pump['closing_reading'] !== null ? number_format((float)$pump['closing_reading'], 2) : '---' ?>
                            </td>
                            <td style="font-weight: bold;"><?= number_format((float)$pump['total_liters'], 2) ?></td>
                            <td style="font-weight: bold; color: #d9534f;"><?= number_format((float)$pump['total_sales'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #fff3cd;">
                    <th colspan="5" style="text-align: left;">إجمالي مطالبة العدادات الكلية:</th>
                    <th style="font-size: 1.2em; color: #d9534f;"><?= number_format($totalSalesFromMeters, 2) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- تفاصيل المبيعات الآجلة (الديون) -->
<?php if (!empty($shiftCreditsList)): ?>
<div class="card">
    <h2>تفاصيل المبيعات الآجلة (الديون)</h2>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead style="background-color: #f8f9fa;">
                <tr>
                    <th>العميل</th>
                    <th>الطرمبة</th>
                    <th>وقت التعبئة</th>
                    <th>المبلغ المسجل (دين)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shiftCreditsList as $credit): ?>
                <tr>
                    <td><?= htmlspecialchars($credit['customer_name']) ?></td>
                    <td><?= htmlspecialchars($credit['pump_name']) ?></td>
                    <td><?= htmlspecialchars($credit['credit_time']) ?></td>
                    <td style="color: #d9534f; font-weight: bold;"><?= number_format((float)$credit['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- تفاصيل المسحوبات -->
<?php if (!empty($shiftWithdrawalsList)): ?>
<div class="card">
    <h2>مسحوبات الموظف أثناء الوردية</h2>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead style="background-color: #fdf3f4;">
                <tr>
                    <th>المبلغ</th>
                    <th>السبب / البيان</th>
                    <th>التاريخ والوقت</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shiftWithdrawalsList as $with): ?>
                <tr>
                    <td style="color: red; font-weight: bold;"><?= number_format((float)$with['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($with['description'] ?? 'غير محدد') ?></td>
                    <td><?= htmlspecialchars($with['withdrawal_date']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ملخص الحساب والتفاصيل المالية -->
<div class="card">
    <h2>ملخص الوردية النهائي (المالية والجرد)</h2>
    <div class="table-responsive">
        <table class="table">
            
            <tr style="background-color: #f8f9fa;">
                <th colspan="2" style="text-align: center; color: #555;">تحليل المبيعات حسب طرق الدفع</th>
            </tr>
            <tr>
                <th>إجمالي مطالبة العدادات ⛽</th>
                <td style="font-weight: bold; font-size: 1.1em; color: #333;"><?= number_format($totalSalesFromMeters, 2) ?></td>
            </tr>
            <tr>
                <th>يُخصم: إجمالي مبيعات الشبكة / البطاقة 💳</th>
                <td style="color: blue;">- <?= number_format($cardTotal, 2) ?></td>
            </tr>
            <tr>
                <th>يُخصم: إجمالي المبيعات الآجلة (الديون) 📒</th>
                <td style="color: #d9534f;">- <?= number_format($totalShiftCredits, 2) ?></td>
            </tr>
            <tr style="border-top: 2px solid #ccc; background-color: #e6fced;">
                <th>الصافي من المبيعات النقدية (الكاش) 💵</th>
                <td style="color: green; font-weight: bold; font-size: 1.1em;"><?= number_format($cashSales, 2) ?></td>
            </tr>

            <tr style="background-color: #f8f9fa;">
                <th colspan="2" style="text-align: center; color: #555;">الجرد وحركة الصندوق (الكاش الفعلي)</th>
            </tr>
            <tr>
                <th>النقدية الافتتاحية (بداية الوردية)</th>
                <td>+ <?= number_format((float)$shift['opening_cash'], 2) ?></td>
            </tr>
            <tr>
                <th>إجمالي المسحوبات أثناء الوردية</th>
                <td style="color: red;">- <?= number_format($totalWithdrawals, 2) ?></td>
            </tr>
            <tr style="background-color: #e9ecef;">
                <th>النقدية المتوقعة للتوريد (حساب النظام) 💰</th>
                <td style="font-weight: bold; font-size: 1.2em; color: #333;"><?= number_format((float)$shift['expected_cash'], 2) ?></td>
            </tr>

            <!-- التسوية الختامية -->
            <?php if ($shift['status'] === 'closed'): ?>
            <tr>
                <th>النقد المُسلّم فعلياً من الموظف عند الإغلاق</th>
                <td style="font-weight: bold; font-size: 1.2em; color: #000;">
                    <?= number_format((float)$shift['closing_cash'], 2) ?>
                </td>
            </tr>
            
            <?php 
                $difference = (float)$shift['difference'];
                $diffText = $difference > 0 ? "زيادة (لصالح المحطة 🟢)" : ($difference < 0 ? "عجز 🔴" : "الصندوق مطابق 🔵");
                $diffColor = $difference > 0 ? "green" : ($difference < 0 ? "red" : "blue");
            ?>
            <tr style="background-color: #fdfdfd; border-top: 2px solid #333;">
                <th style="font-size: 1.1em;">الفارق (العجز أو الزيادة)</th>
                <td style="color: <?= $diffColor ?>; font-weight: bold; font-size: 1.3em;">
                    <?= number_format($difference, 2) ?> - <?= $diffText ?>
                </td>
            </tr>

            <!-- إظهار حقل العجز الصريح المسجل في قاعدة البيانات -->
            <?php if (isset($shift['shortage']) && (float)$shift['shortage'] > 0): ?>
            <tr>
                <th style="color: red;">مبلغ العجز المُسجل على الموظف</th>
                <td style="color: red; font-weight: bold; font-size: 1.2em;">
                    <?= number_format((float)$shift['shortage'], 2) ?>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <th style="color: green;">مبلغ العجز المُسجل على الموظف</th>
                <td style="color: green; font-weight: bold; font-size: 1.2em;">
                    0.00
                </td>
            </tr>
            <?php endif; ?>

            <?php endif; ?>

        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>