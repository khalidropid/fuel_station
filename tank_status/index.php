<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'مراقبة حالة الخزانات';

// 1. جلب الخزانات النشطة مع أنواع الوقود
$stmt = $pdo->query("
    SELECT 
        t.id, t.tank_no, t.name, t.capacity, t.current_volume, t.minimum_level,
        ft.name AS fuel_name, ft.code AS fuel_code
    FROM tanks t
    INNER JOIN fuel_types ft ON ft.id = t.fuel_type_id
    WHERE t.status = 'active'
    ORDER BY t.id ASC
");
$tanks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. فحص الخزانات التي وصلت للحد الأدنى لإنشاء التنبيهات
$alerts = [];
foreach ($tanks as $tank) {
    if ((float)$tank['current_volume'] <= (float)$tank['minimum_level']) {
        $alerts[] = "⚠️ تحذير: {$tank['name']} ({$tank['fuel_name']}) وصل إلى الحد الأدنى! الرصيد الحالي: " . number_format($tank['current_volume'], 2) . " لتر.";
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 20px;">
        <div>
            <h2 style="margin: 0; margin-bottom: 5px;">مراقبة حالة الخزانات</h2>
            <p style="margin: 0; color: #666;">عرض مباشر لمنسوب الوقود (بنزين / ديزل) في الخزانات النشطة.</p>
        </div>
        <div>
            <a href="receive.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
                + توريد وقود لخزان (تعبئة)
            </a>
        </div>
</div>

<!-- التنبيهات الذكية عند نقص المنسوب -->
<?php if (!empty($alerts)): ?>
    <div class="alert alert-danger" style="border-right: 5px solid #dc2626;">
        <h4 style="margin-top: 0;">تنبيهات انخفاض المخزون:</h4>
        <ul style="margin-bottom: 0; padding-right: 20px;">
            <?php foreach ($alerts as $alert): ?>
                <li><strong><?= htmlspecialchars($alert) ?></strong></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        تم توريد الوقود وتحديث منسوب الخزان بنجاح!
    </div>
<?php endif; ?>

<div class="row" style="display: flex; flex-wrap: wrap; gap: 20px;">
    <?php if (empty($tanks)): ?>
        <div class="card" style="width: 100%; text-align: center; padding: 40px;">
            <h3>لا توجد خزانات نشطة حالياً.</h3>
            <p>يرجى إضافة خزانات من قسم "إعدادات الخزانات".</p>
        </div>
    <?php else: ?>
        <?php foreach ($tanks as $tank): 
            $capacity = (float)$tank['capacity'];
            $volume = (float)$tank['current_volume'];
            $minLevel = (float)$tank['minimum_level'];
            
            // حساب النسبة المئوية للامتلاء
            $percent = $capacity > 0 ? ($volume / $capacity) * 100 : 0;
            $percent = min(100, max(0, $percent)); // التأكد أنها بين 0 و 100
            
            // تحديد لون شريط التقدم بناءً على المنسوب
            $barColor = '#16a34a'; // أخضر للطبيعي
            if ($volume <= $minLevel) {
                $barColor = '#dc2626'; // أحمر للخطر
            } elseif ($percent <= 30) {
                $barColor = '#f59e0b'; // برتقالي للمتوسط
            }
        ?>
            <div class="card" style="flex: 1; min-width: 300px; border-top: 4px solid <?= $barColor ?>;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
                    <h3 style="margin: 0;"><?= htmlspecialchars($tank['name']) ?></h3>
                    <span class="badge" style="background-color: #e5e7eb; color: #374151; font-size: 14px;">
                        <?= htmlspecialchars($tank['fuel_name']) ?>
                    </span>
                </div>
                
                <!-- شريط التقدم -->
                <div style="background-color: #e5e7eb; border-radius: 999px; height: 24px; overflow: hidden; margin-bottom: 15px; position: relative;">
                    <div style="background-color: <?= $barColor ?>; height: 100%; width: <?= $percent ?>%; transition: width 0.5s ease;"></div>
                    <span style="position: absolute; top: 0; left: 0; right: 0; text-align: center; color: #000; font-size: 14px; font-weight: bold; line-height: 24px; text-shadow: 1px 1px 2px rgba(255,255,255,0.7);">
                        <?= number_format($percent, 1) ?>%
                    </span>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 14px; color: #4b5563; margin-bottom: 15px;">
                    <div>الرصيد الحالي: <strong style="color: #000; font-size: 16px;"><?= number_format($volume, 2) ?></strong> لتر</div>
                    <div>سعة الخزان: <strong><?= number_format($capacity, 2) ?></strong> لتر</div>
                </div>

                <hr style="border: 0; border-top: 1px solid #eee; margin-bottom: 15px;">

                <div style="text-align: left;">
                    <a href="receive.php?tank_id=<?= $tank['id'] ?>" class="btn btn-sm" style="background-color: #2563eb; color: #fff;">
                        ⬇️ توريد وقود
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>