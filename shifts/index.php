<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'الورديات';

require_once __DIR__ . '/../includes/header.php';

try {

    $stmt = $pdo->query("
        SELECT
            s.id,
            s.shift_no,
            s.start_datetime,
            s.end_datetime,
            s.status,
            s.opening_cash,
            s.closing_cash,
            s.total_sales,
            s.total_withdrawals,
            s.expected_cash,
            s.difference,
            e.employee_no,
            e.full_name AS employee_name
        FROM shifts s
        INNER JOIN employees e
            ON e.id = s.employee_id
        ORDER BY s.id DESC
    ");

    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

    $shifts = [];
    $error = $e->getMessage();
}

?>

<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 20px;">

    <!-- القسم العلوي: العنوان + حقل البحث -->
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="margin-bottom: 5px;">الورديات</h2>
            <p style="margin: 0; color: #666;">
                إدارة ورديات الموظفين ومتابعة القراءات والمبيعات.
            </p>
        </div>

        <!-- حقل البحث الجديد -->
        <div>
            <input
                type="text"
                id="shiftSearch"
                placeholder="🔍 بحث برقم الوردية أو اسم الموظف..."
                style="width: 320px; max-width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;">
        </div>
    </div>

    <!-- القسم السفلي: الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            + فتح وردية جديدة
        </a>
    </div>

</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        تم حفظ التعديلات بنجاح.
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        خطأ قاعدة البيانات: <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- تم إضافة overflow:hidden هنا لمنع الكارد من التمدد خارج الشاشة -->
<div class="card" style="overflow: hidden;">

    <!-- التعديل هنا: إضافة overflow-x: auto لعمل السكرول -->
    <div class="table-responsive" style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">

        <!-- التعديل هنا: إجبار الجدول على عرض لا يقل عن 1100 بكسل، ومنع تكسير النصوص -->
        <table class="table" id="shiftsTable" style="min-width: 1100px; white-space: nowrap;">
            <thead>
                <tr>
                    <th>رقم الوردية</th>
                    <th>الموظف</th>
                    <th>البداية</th>
                    <th>النهاية</th>
                    <th>المبيعات</th>
                    <th>المسحوبات</th>
                    <th>المبلغ المتوقع</th>
                    <th>الفرق</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shifts)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center; padding: 30px;">
                            لا توجد ورديات حتى الآن.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($shifts as $shift): ?>
                        <!-- إضافة data-search لكي يقرأها كود البحث -->
                        <tr data-search="<?= htmlspecialchars(strtolower($shift['shift_no'] . ' ' . $shift['employee_name'])) ?>">
                            <td><?= (int)$shift['id'] ?></td>
                            <td><strong><?= htmlspecialchars($shift['shift_no']) ?></strong></td>
                            <td><strong><?= htmlspecialchars($shift['employee_name']) ?></strong></td>
                            <td><?= htmlspecialchars($shift['start_datetime']) ?></td>
                            <td><?= $shift['end_datetime'] ? htmlspecialchars($shift['end_datetime']) : '-' ?></td>
                            <td><?= number_format((float)$shift['total_sales'], 2) ?></td>
                            <td><?= number_format((float)$shift['total_withdrawals'], 2) ?></td>
                            <td><?= number_format((float)$shift['expected_cash'], 2) ?></td>
                            <td>
                                <?php
                                $diff = (float)$shift['difference'];
                                $color = $diff < 0 ? 'red' : ($diff > 0 ? 'green' : 'inherit');
                                ?>
                                <span style="color: <?= $color ?>; font-weight: bold;">
                                    <?= number_format($diff, 2) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($shift['status'] === 'open'): ?>
                                    <span class="badge badge-success">مفتوحة</span>
                                <?php elseif ($shift['status'] === 'closed'): ?>
                                    <span class="badge badge-secondary">مغلقة</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">ملغاة</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="view.php?id=<?= (int)$shift['id'] ?>" class="btn btn-sm btn-info">عرض</a>

                                    <?php if ($shift['status'] === 'open'): ?>
                                        <a href="edit.php?id=<?= (int)$shift['id'] ?>" class="btn btn-sm btn-primary">تعديل</a>
                                        <a href="close.php?id=<?= (int)$shift['id'] ?>" class="btn btn-sm btn-warning">إغلاق</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- سكريبت البحث الفوري -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('shiftSearch');
        const rows = document.querySelectorAll('#shiftsTable tbody tr[data-search]');

        if (!searchInput) return;

        searchInput.addEventListener('input', function() {
            const value = this.value.trim().toLowerCase();

            rows.forEach(row => {
                const text = row.getAttribute('data-search') || '';
                // إظهار الصف إذا كان يطابق البحث، وإخفاؤه إذا لم يطابق
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>