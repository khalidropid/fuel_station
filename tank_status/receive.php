<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'توريد وقود للخزان';
$error = '';

// جلب الخزانات النشطة للقائمة المنسدلة
$stmtTanks = $pdo->query("
    SELECT t.id, t.name, t.current_volume, t.capacity, ft.name AS fuel_name 
    FROM tanks t 
    INNER JOIN fuel_types ft ON ft.id = t.fuel_type_id 
    WHERE t.status = 'active'
");
$tanks = $stmtTanks->fetchAll(PDO::FETCH_ASSOC);

// تحديد الخزان المختار مسبقاً (إن وجد من الرابط)
$selectedTankId = (int)($_GET['tank_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tankId = (int)$_POST['tank_id'];
    $quantity = (float)$_POST['quantity'];
    $supplier = trim($_POST['supplier'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $receiptDate = date('Y-m-d H:i:s');
    $userId = $_SESSION['user_id'] ?? 1;

    if ($tankId <= 0) {
        $error = 'يرجى اختيار الخزان.';
    } elseif ($quantity <= 0) {
        $error = 'الكمية الموردة يجب أن تكون أكبر من صفر.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. التحقق من سعة الخزان ومساحته المتبقية
            $checkStmt = $pdo->prepare("SELECT name, current_volume, capacity FROM tanks WHERE id = ? FOR UPDATE");
            $checkStmt->execute([$tankId]);
            $tankData = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$tankData) {
                throw new Exception("الخزان غير موجود.");
            }

            $availableSpace = (float)$tankData['capacity'] - (float)$tankData['current_volume'];
            if ($quantity > $availableSpace) {
                throw new Exception("الكمية الموردة ({$quantity} لتر) أكبر من المساحة الفارغة في الخزان ({$availableSpace} لتر).");
            }

            // 2. تحديث رصيد الخزان
            $updateTank = $pdo->prepare("UPDATE tanks SET current_volume = current_volume + ? WHERE id = ?");
            $updateTank->execute([$quantity, $tankId]);

            // 3. إنشاء رقم إيصال فريد وتسجيل التوريد في (fuel_receipts)
            $receiptNo = 'REC-' . date('YmdHis') . '-' . rand(100, 999);
            $insertReceipt = $pdo->prepare("
                INSERT INTO fuel_receipts (receipt_no, tank_id, quantity, receipt_date, supplier, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insertReceipt->execute([$receiptNo, $tankId, $quantity, $receiptDate, $supplier, $notes, $userId]);
            $receiptId = $pdo->lastInsertId();

            // 4. إثبات الحركة في سجل حركة الخزانات (tank_movements)
            $insertMovement = $pdo->prepare("
                INSERT INTO tank_movements (tank_id, movement_type, quantity, reference_id, movement_date, notes, created_by)
                VALUES (?, 'receipt', ?, ?, ?, ?, ?)
            ");
            $insertMovement->execute([$tankId, $quantity, $receiptId, $receiptDate, "توريد وقود بإيصال رقم: " . $receiptNo, $userId]);

            $pdo->commit();
            header('Location: index.php?success=1');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 20px;">
    <div>
        <h2 style="margin: 0; margin-bottom: 5px;">توريد وقود للخزان</h2>
        <p style="margin: 0; color: #666;">إضافة كميات الوقود الجديدة الواردة من الموردين (القواطر) إلى الخزانات.</p>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary" style="padding: 10px 20px;">
            ← العودة لمراقبة الخزانات
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST">
        
        <div class="form-group">
            <label for="tank_id">اختر الخزان المراد تعبئته</label>
            <select name="tank_id" id="tank_id" class="form-control" required>
                <option value="">-- اختر الخزان --</option>
                <?php foreach ($tanks as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $selectedTankId === $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['fuel_name']) ?>) - متاح: <?= number_format($t['capacity'] - $t['current_volume'], 2) ?> لتر
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="quantity">الكمية الموردة (باللتر)</label>
            <input type="number" step="0.01" min="0.01" name="quantity" id="quantity" class="form-control" placeholder="أدخل عدد اللترات" required>
        </div>

        <div class="form-group">
            <label for="supplier">اسم المورد / السائق (اختياري)</label>
            <input type="text" name="supplier" id="supplier" class="form-control" placeholder="مثال: شركة النفط / القاطرة رقم 123">
        </div>

        <div class="form-group">
            <label for="notes">ملاحظات (اختياري)</label>
            <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
        </div>

        <div class="form-actions mt-4">
            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;" onclick="return confirm('هل أنت متأكد من الكمية المدخلة؟')">
                تأكيد التوريد
            </button>
        </div>

    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>