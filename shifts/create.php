<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'فتح وردية جديدة';

$error = '';

try {

    // جلب الموظفين النشطين
    $employeesStmt = $pdo->query("
        SELECT id, employee_no, full_name
        FROM employees
        WHERE status = 'active'
        ORDER BY full_name ASC
    ");
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);


    // جلب الطرمبات النشطة مع آخر قراءة لكل طرمبة تلقائياً
    $pumpsStmt = $pdo->query("
        SELECT
            p.id,
            p.pump_no,
            p.name,
            p.tank_id,
            t.name AS tank_name,
            ft.name AS fuel_name,
            (
                SELECT reading
                FROM pump_readings pr
                WHERE pr.pump_id = p.id
                ORDER BY pr.reading_date DESC, pr.id DESC
                LIMIT 1
            ) AS last_reading
        FROM pumps p
        INNER JOIN tanks t
            ON t.id = p.tank_id
        INNER JOIN fuel_types ft
            ON ft.id = t.fuel_type_id
        WHERE p.status = 'active'
        ORDER BY p.pump_no ASC
    ");
    $pumps = $pumpsStmt->fetchAll(PDO::FETCH_ASSOC);


    // إنشاء الوردية
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $startDatetime = trim($_POST['start_datetime'] ?? '');
        $openingCash = (float)($_POST['opening_cash'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        // مصفوفة الطرمبات المحددة وقراءاتها
        $pumpIds = $_POST['pump_ids'] ?? [];
        $openingReadings = $_POST['opening_readings'] ?? [];

        // التحقق الأساسي
        if ($employeeId <= 0) {
            throw new Exception('يرجى اختيار الموظف.');
        }
        if (empty($pumpIds)) {
            throw new Exception('يرجى اختيار طرمبة واحدة على الأقل.');
        }
        if ($startDatetime === '') {
            throw new Exception('يرجى تحديد وقت بداية الوردية.');
        }

        // التأكد أن الموظف موجود ونشط
        $employeeCheck = $pdo->prepare("SELECT id FROM employees WHERE id = ? AND status = 'active' LIMIT 1");
        $employeeCheck->execute([$employeeId]);
        if (!$employeeCheck->fetch()) {
            throw new Exception('الموظف غير موجود أو غير نشط.');
        }

        // التأكد من عدم وجود وردية مفتوحة لنفس الموظف
        $openEmployeeShift = $pdo->prepare("SELECT id FROM shifts WHERE employee_id = ? AND status = 'open' LIMIT 1");
        $openEmployeeShift->execute([$employeeId]);
        if ($openEmployeeShift->fetch()) {
            throw new Exception('يوجد بالفعل وردية مفتوحة لهذا الموظف.');
        }

        // التحقق من حالة الطرمبات المحددة (يجب ألا تكون مفتوحة في وردية أخرى)
        $openPumpShift = $pdo->prepare("
            SELECT sp.id, p.name 
            FROM shift_pumps sp
            INNER JOIN shifts s ON s.id = sp.shift_id
            INNER JOIN pumps p ON p.id = sp.pump_id
            WHERE sp.pump_id = ? AND s.status = 'open'
            LIMIT 1
        ");

        foreach ($pumpIds as $pId) {
            $openPumpShift->execute([(int)$pId]);
            if ($row = $openPumpShift->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("الطرمبة ({$row['name']}) تعمل حالياً في وردية أخرى مفتوحة.");
            }
        }

        // إنشاء رقم الوردية
        $shiftNo = 'SH-' . date('YmdHis');

        $pdo->beginTransaction();

        // 1. إنشاء الوردية الرئيسية
        $insertShift = $pdo->prepare("
            INSERT INTO shifts
            (shift_no, employee_id, start_datetime, status, opening_cash, closing_cash, total_sales, total_withdrawals, expected_cash, difference, notes)
            VALUES (?, ?, ?, 'open', ?, 0, 0, 0, 0, 0, ?)
        ");
        $insertShift->execute([$shiftNo, $employeeId, $startDatetime, $openingCash, $notes]);
        
        $shiftId = $pdo->lastInsertId();

        // تجهيز استعلامات الإدراج للطرمبات والقراءات
        $insertShiftPump = $pdo->prepare("
            INSERT INTO shift_pumps (shift_id, pump_id, opening_reading, closing_reading, total_liters, total_sales)
            VALUES (?, ?, ?, NULL, 0, 0)
        ");

        $insertReading = $pdo->prepare("
            INSERT INTO pump_readings (pump_id, reading, reading_date, reading_type, employee_id, notes)
            VALUES (?, ?, ?, 'opening', ?, ?)
        ");

        // 2. ربط الطرمبات وتسجيل قراءاتها
        foreach ($pumpIds as $pId) {
            $pId = (int)$pId;
            $openingReading = (float)($openingReadings[$pId] ?? 0);

            // ربط الطرمبة بالوردية
            $insertShiftPump->execute([$shiftId, $pId, $openingReading]);

            // حفظ القراءة في سجل العدادات
            $insertReading->execute([$pId, $openingReading, $startDatetime, $employeeId, 'قراءة بداية الوردية ' . $shiftNo]);
        }

        $pdo->commit();
        header('Location: view.php?id=' . $shiftId);
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
        <h2>فتح وردية جديدة</h2>
        <p>تحديد الموظف، الطرمبات العاملة، وإدخال/تأكيد قراءات العدادات.</p>
    </div>
    <div>
        <a href="index.php" class="btn">العودة للورديات</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST">

        <div class="form-group">
            <label>الموظف</label>
            <select name="employee_id" class="form-control" required>
                <option value="">اختر الموظف</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?= (int)$employee['id'] ?>" <?= (isset($_POST['employee_id']) && $_POST['employee_id'] == $employee['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($employee['employee_no'] . ' - ' . $employee['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>تاريخ ووقت بداية الوردية</label>
            <input type="datetime-local" name="start_datetime" class="form-control" value="<?= htmlspecialchars($_POST['start_datetime'] ?? date('Y-m-d\TH:i')) ?>" required>
        </div>

        <div class="form-group">
            <label>النقدية الافتتاحية (المبلغ الموجود في الدرج)</label>
            <input type="number" name="opening_cash" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($_POST['opening_cash'] ?? '0') ?>">
        </div>

        <!-- قسم اختيار الطرمبات وإدخال القراءات -->
        <div class="form-group">
            <label style="font-weight: bold; color: #333; margin-top: 15px;">
                الطرمبات (حدد الطرمبات التي سيعمل عليها الموظف وقم بتأكيد قراءة البداية)
            </label>
            <div class="table-responsive" style="border: 1px solid #ddd; border-radius: 5px; max-height: 350px; overflow-y: auto;">
                <table class="table">
                    <thead style="background-color: #f4f4f4;">
                        <tr>
                            <th style="width: 50px;">تحديد</th>
                            <th>الطرمبة</th>
                            <th>نوع الوقود</th>
                            <th style="width: 250px;">قراءة العداد الحالية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pumps as $pump): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="pump_ids[]" value="<?= $pump['id'] ?>" id="pump_<?= $pump['id'] ?>" style="transform: scale(1.5);">
                                </td>
                                <td>
                                    <label for="pump_<?= $pump['id'] ?>" style="cursor: pointer; margin: 0;">
                                        <?= htmlspecialchars($pump['name']) ?> 
                                        <small>(<?= htmlspecialchars($pump['pump_no']) ?>)</small>
                                    </label>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($pump['fuel_name']) ?></span>
                                </td>
                                <td>
                                    <input type="number" name="opening_readings[<?= $pump['id'] ?>]" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($pump['last_reading'] ?? 0) ?>" placeholder="أدخل القراءة">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-group">
            <label>ملاحظات</label>
            <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">فتح الوردية</button>
            <a href="index.php" class="btn btn-secondary">إلغاء</a>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>