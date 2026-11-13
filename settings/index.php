<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'إعدادات النظام';

$error = '';
$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| معالجة طلب النسخ الاحتياطي (Backup)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_backup'])) {

    // 1. إعدادات قاعدة البيانات (يجب أن تتطابق مع ملف config.php)
    $dbHost = '127.0.0.1';
    $dbUser = 'root'; // اسم مستخدم قاعدة البيانات في XAMPP
    $dbPass = '';     // الباسورد (اتركه فارغاً إذا كان XAMPP الافتراضي)
    $dbName = 'fuel_station'; // اسم قاعدة البيانات الخاصة بالمحطة

    // 2. مسار الحفظ في قرص D 
    $backupFolder = 'F:\\Station_Backups\\';

    // إنشاء المجلد تلقائياً إذا لم يكن موجوداً
    if (!is_dir($backupFolder)) {
        if (!@mkdir($backupFolder, 0777, true)) {
            $message = "خطأ: لم نتمكن من إنشاء مجلد النسخ الاحتياطي في المسار D:\Station_Backups. تأكد من وجود قرص D في الجهاز.";
            $messageType = 'danger';
        }
    }

    if (is_dir($backupFolder)) {
        // 3. اسم ملف النسخة الاحتياطية
        $fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $fullPath = $backupFolder . $fileName;

        // 4. مسار أداة mysqldump 
        // مسار XAMPP الافتراضي في نظام الويندوز
        $mysqldumpPath = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

        if (!file_exists($mysqldumpPath)) {
            $message = "خطأ: أداة mysqldump غير موجودة في المسار الافتراضي. يرجى التأكد من تثبيت XAMPP.";
            $messageType = 'danger';
        } else {
            // 5. تجهيز أمر استخراج قاعدة البيانات
            if (empty($dbPass)) {
                $command = "\"$mysqldumpPath\" -h $dbHost -u $dbUser $dbName > \"$fullPath\"";
            } else {
                $command = "\"$mysqldumpPath\" -h $dbHost -u $dbUser -p\"$dbPass\" $dbName > \"$fullPath\"";
            }

            // 6. تنفيذ الأمر
            exec($command . ' 2>&1', $output, $result);

            if ($result === 0) {
                $message = "✅ تم أخذ النسخة الاحتياطية بنجاح! تم الحفظ في المسار: " . $fullPath;
                $messageType = 'success';
            } else {
                $message = "حدث خطأ أثناء النسخ الاحتياطي: " . implode(" ", $output);
                $messageType = 'danger';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

?>

<!-- =========================================================
     Page Header
========================================================== -->

<div class="page-header">

    <div>

        <h2>
            إعدادات النظام
        </h2>

        <p>
            إدارة إعدادات ووظائف نظام محطة الوقود.
        </p>

    </div>

</div>

<!-- رسائل التنبيه الخاصة بالنسخ الاحتياطي -->
<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>" style="font-size: 16px; font-weight: bold;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>


<!-- =========================================================
     النسخ الاحتياطي (Backup) - القسم الجديد
========================================================== -->
<div class="card" style="border-right: 5px solid #2563eb; background-color: #f8fafc;">

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">

        <div>
            <h2 style="color: #1e3a8a; margin-top: 0; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 30px;">💾</span> النسخ الاحتياطي للبيانات (Backup)
            </h2>
            <p style="font-size: 15px; color: #475569; margin: 0;">
                لحماية بيانات المحطة من الضياع، يُنصح بأخذ نسخة احتياطية يومياً. <br>
                سيتم حفظ النسخة تلقائياً في المسار الآمن: <strong style="color: #2563eb; direction: ltr; display: inline-block;">D:\Station_Backups\</strong>
            </p>
        </div>

        <div>
            <form method="POST">
                <button type="submit" name="do_backup" class="btn btn-primary" style="padding: 12px 25px; font-size: 16px; font-weight: bold; background-color: #1d4ed8;" onclick="return confirm('هل أنت متأكد من بدء عملية النسخ الاحتياطي الآن؟')">
                    بدء النسخ الاحتياطي
                </button>
            </form>
        </div>

    </div>

</div>


<!-- =========================================================
     Information
========================================================== -->

<div class="card">

    <h2>
        إعدادات المحطة
    </h2>

    <p>

        إعدادات النظام الأساسية يمكن إضافتها هنا
        دون التأثير على البيانات الحالية.

    </p>

</div>


<!-- =========================================================
     Management Links
========================================================== -->

<div class="cards">


    <!-- =====================================================
         الحسابات
    ====================================================== -->

    <div class="card">

        <div class="stat-icon">
            📚
        </div>

        <h3>
            الحسابات
        </h3>

        <p>
            إدارة دليل الحسابات والحركات المالية.
        </p>

        <a
            href="/accounts/"
            class="btn btn-primary">

            فتح دليل الحسابات

        </a>

    </div>


    <!-- =====================================================
         المبيعات
    ====================================================== -->

    <div class="card">

        <div class="stat-icon">
            💰
        </div>

        <h3>
            المبيعات
        </h3>

        <p>
            إدارة عمليات البيع والفواتير.
        </p>

        <a
            href="/sales/"
            class="btn">

            فتح المبيعات

        </a>

    </div>


    <!-- =====================================================
         الموظفون
    ====================================================== -->

    <div class="card">

        <div class="stat-icon">
            👨‍💼
        </div>

        <h3>
            الموظفون
        </h3>

        <p>
            إدارة الموظفين والرواتب وحساباتهم.
        </p>

        <a
            href="/employees/"
            class="btn">

            فتح الموظفين

        </a>

    </div>


    <!-- =====================================================
         العملاء
    ====================================================== -->

    <div class="card">

        <div class="stat-icon">
            👤
        </div>

        <h3>
            العملاء
        </h3>

        <p>
            إدارة العملاء وحساباتهم والمديونيات.
        </p>

        <a
            href="/customers/"
            class="btn">

            فتح العملاء

        </a>

    </div>


    <!-- =====================================================
         الموردون
    ====================================================== -->

    <div class="card">

        <div class="stat-icon">
            🏢
        </div>

        <h3>
            الموردون
        </h3>

        <p>
            إدارة حسابات الموردين والمستحقات.
        </p>

        <a
            href="/suppliers/view.php"
            class="btn">

            حسابات الموردين

        </a>

    </div>


    <!-- =====================================================
         المصروفات
    ====================================================== -->

    <div class="card">

        <div class="stat-icon">
            💸
        </div>

        <h3>
            المصروفات
        </h3>

        <p>
            تسجيل المصروفات وربطها بالخزينة والحسابات.
        </p>

        <a
            href="/expenses/create.php"
            class="btn">

            تسجيل مصروف

        </a>

    </div>


    <!-- =====================================================
         التقارير
    ====================================================== -->

    <div class="card">

        <div class="stat-icon">
            📊
        </div>

        <h3>
            التقارير
        </h3>

        <p>
            تقارير المبيعات والحسابات والحركات المالية.
        </p>

        <a
            href="/reports/"
            class="btn">

            فتح التقارير

        </a>

    </div>


    <!-- =====================================================
         قاعدة البيانات
    ====================================================== -->

    <div class="card">

        <div class="stat-icon">
            🗄️
        </div>

        <h3>
            قاعدة البيانات
        </h3>

        <p>
            فحص الحسابات والحركات المالية والتأكد من التوازن.
        </p>

        <a
            href="/reports/accounts.php"
            class="btn">

            فحص الحسابات

        </a>

    </div>


</div>


<!-- =========================================================
     النظام المحاسبي
========================================================== -->

<div class="card">

    <div class="page-header">

        <div>

            <h2>
                النظام المحاسبي
            </h2>

            <p>
                جميع العمليات المحاسبية يجب أن تمر من خلال
                الحسابات والحركات المالية.
            </p>

        </div>

    </div>


    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>

                    <th>
                        العملية
                    </th>

                    <th>
                        الحساب المدين
                    </th>

                    <th>
                        الحساب الدائن
                    </th>

                    <th>
                        الحالة
                    </th>

                </tr>

            </thead>


            <tbody>


                <tr>

                    <td>
                        بيع نقدي
                    </td>

                    <td>
                        الخزينة
                    </td>

                    <td>
                        المالك
                    </td>

                    <td>

                        <span class="badge badge-success">
                            محاسبيًا مزدوج
                        </span>

                    </td>

                </tr>


                <tr>

                    <td>
                        بيع آجل
                    </td>

                    <td>
                        العميل
                    </td>

                    <td>
                        المالك
                    </td>

                    <td>

                        <span class="badge badge-success">
                            محاسبيًا مزدوج
                        </span>

                    </td>

                </tr>


                <tr>

                    <td>
                        راتب الموظف
                    </td>

                    <td>
                        المصروف / الرواتب
                    </td>

                    <td>
                        حساب الموظف أو الخزينة حسب الحالة
                    </td>

                    <td>

                        <span class="badge">
                            حسب العملية
                        </span>

                    </td>

                </tr>


                <tr>

                    <td>
                        مصروف نقدي
                    </td>

                    <td>
                        المصروف
                    </td>

                    <td>
                        الخزينة
                    </td>

                    <td>

                        <span class="badge badge-success">
                            محاسبيًا مزدوج
                        </span>

                    </td>

                </tr>


                <tr>

                    <td>
                        تحصيل عميل
                    </td>

                    <td>
                        الخزينة
                    </td>

                    <td>
                        العميل
                    </td>

                    <td>

                        <span class="badge badge-success">
                            محاسبيًا مزدوج
                        </span>

                    </td>

                </tr>


            </tbody>

        </table>

    </div>

</div>


<!-- =========================================================
     ملاحظة
========================================================== -->

<div class="card">

    <h3>
        ملاحظة مهمة
    </h3>

    <p>

        هذه الصفحة لا تنشئ جدولًا جديدًا ولا تعدل قاعدة البيانات.
        تم تصميمها بحيث تعمل مع قاعدة البيانات الحالية بأمان.

    </p>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>