<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'تعديل الوردية';

$shiftId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$shiftId) {
    header('Location: index.php');
    exit;
}

$error = '';

try {
    // 1. جلب بيانات الوردية والتأكد من أنها مفتوحة
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ? LIMIT 1");
    $stmt->execute([$shiftId]);
    $shift = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$shift) {
        throw new Exception('الوردية غير موجودة.');
    }

    if ($shift['status'] !== 'open') {
        throw new Exception('لا يمكن تعديل الوردية لأنها مغلقة أو ملغاة. يمكنك فقط عرضها.');
    }

    // 2. جلب الموظفين النشطين
    $employeesStmt = $pdo->query("SELECT id, employee_no, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC");
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. جلب الطرمبات المرتبطة بهذه الوردية لتعديل قراءة البداية
    $pumpStmt = $pdo->prepare("
        SELECT 
            sp.id AS shift_pump_id, 
            p.name AS pump_name, 
            p.pump_no,
            ft.name AS fuel_name,
            sp.opening_reading
        FROM shift_pumps sp
        INNER JOIN pumps p ON p.id = sp.pump_id
        INNER JOIN tanks t ON t.id = p.tank_id
        INNER JOIN fuel_types ft ON ft.id = t.fuel_type_id
        WHERE sp.shift_id = ?
    ");
    $pumpStmt->execute([$shiftId]);
    $shiftPumps = $pumpStmt->fetchAll(PDO::FETCH_ASSOC);

    // معالجة التعديل (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $startDatetime = trim($_POST['start_datetime'] ?? '');
        $openingCash = (float)($_POST['opening_cash'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        $openingReadings = $_POST['opening_readings'] ?? [];

        if ($employeeId <= 0 || $startDatetime === '') {
            throw new Exception('الرجاء تعبئة جميع الحقول المطلوبة.');
        }

        $pdo->beginTransaction();

        // أ. تحديث بيانات الوردية الأساسية
        $updateShift = $pdo->prepare("
            UPDATE shifts 
            SET employee_id = ?, start_datetime = ?, opening_cash = ?, notes = ?
            WHERE id = ?
        ");
        $updateShift->execute([$employeeId, $startDatetime, $openingCash, $notes, $shiftId]);

        // ب. تحديث قراءات البداية للطرمبات (في حال تم الإدخال بشكل خاطئ)
        $updateReading = $pdo->prepare("UPDATE shift_pumps SET opening_reading = ? WHERE id = ? AND shift_id = ?");
        
        foreach ($shiftPumps as $pump) {
            $shiftPumpId = $pump['shift_pump_id'];
            if (isset($openingReadings[$shiftPumpId])) {
                $newReading = (float)$openingReadings[$shiftPumpId];
                $updateReading->execute([$newReading, $shiftPumpId, $shiftId]);
            }
        }

        $pdo->commit();
        header('Location: index.php?success=1');
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
        <h2>تعديل الوردية: <?= htmlspecialchars($shift['shift_no'] ?? '') ?></h2>
        <p>يمكنك تعديل بيانات الوردية وقراءات البداية طالما أن الوردية لا تزال مفتوحة.</p>
    </div>
    <div>
        <a href="index.php" class="btn">العودة للورديات</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- إخفاء النموذج إذا كان هناك خطأ يمنع التعديل (مثل الوردية مغلقة) -->
<?php if (isset($shift) && $shift['status'] === 'open'): ?>
<div class="card">
    <form method="POST">

        <div class="form-group">
            <label>الموظف</label>
            <select name="employee_id" class="form-control" required>
                <option value="">اختر الموظف</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?= (int)$employee['id'] ?>" <?= ($shift['employee_id'] == $employee['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($employee['employee_no'] . ' - ' . $employee['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>تاريخ ووقت بداية الوردية</label>
            <input type="datetime-local" name="start_datetime" class="form-control" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($shift['start_datetime']))) ?>" required>
        </div>

        <div class="form-group">
            <label>النقدية الافتتاحية (في الدرج)</label>
            <input type="number" name="opening_cash" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($shift['opening_cash']) ?>">
        </div>

        <div class="form-group">
            <label style="font-weight: bold; color: #333; margin-top: 15px;">
                تصحيح قراءات بداية الوردية للعدادات
            </label>
            <small style="display: block; margin-bottom: 10px; color: #666;">* لتغيير الطرمبات (إضافة/حذف طرمبة)، يجب إلغاء هذه الوردية وفتح وردية جديدة للحفاظ على سلامة الحسابات.</small>
            
            <div class="table-responsive" style="border: 1px solid #ddd; border-radius: 5px;">
                <table class="table mb-0">
                    <thead style="background-color: #f4f4f4;">
                        <tr>
                            <th>الطرمبة</th>
                            <th>نوع الوقود</th>
                            <th style="width: 300px;">قراءة العداد (البداية)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shiftPumps as $pump): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($pump['pump_name']) ?> 
                                    <small>(<?= htmlspecialchars($pump['pump_no']) ?>)</small>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($pump['fuel_name']) ?></span>
                                </td>
                                <td>
                                    <input type="number" name="opening_readings[<?= $pump['shift_pump_id'] ?>]" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($pump['opening_reading']) ?>" required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-group mt-3">
            <label>ملاحظات</label>
            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($shift['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions mt-4">
            <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
            <a href="index.php" class="btn btn-secondary">إلغاء</a>
        </div>

    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>