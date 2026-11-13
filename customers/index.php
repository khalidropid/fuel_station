<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'العملاء';

$error = '';

$customers = [];

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');


/*
|--------------------------------------------------------------------------
| جلب العملاء
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            id,
            customer_no,
            full_name,
            phone,
            address,
            credit_limit,
            balance,
            status,
            notes,
            created_at
        FROM customers
        WHERE 1 = 1
    ";

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | البحث
    |--------------------------------------------------------------------------
    */

    if ($search !== '') {

        $sql .= "
            AND (
                customer_no LIKE ?
                OR full_name LIKE ?
                OR phone LIKE ?
            )
        ";

        $searchValue = '%' . $search . '%';

        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }


    /*
    |--------------------------------------------------------------------------
    | فلترة الحالة
    |--------------------------------------------------------------------------
    */

    if (
        $status !== ''
        && in_array(
            $status,
            ['active', 'inactive', 'blocked'],
            true
        )
    ) {

        $sql .= "
            AND status = ?
        ";

        $params[] = $status;
    }


    /*
    |--------------------------------------------------------------------------
    | ترتيب النتائج
    |--------------------------------------------------------------------------
    */

    $sql .= "
        ORDER BY id DESC
    ";


    /*
    |--------------------------------------------------------------------------
    | تنفيذ الاستعلام
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {

    $error = 'حدث خطأ في قاعدة البيانات أثناء تحميل العملاء.';

    /*
    |--------------------------------------------------------------------------
    | للتطوير فقط
    |--------------------------------------------------------------------------
    |
    | يمكنك مؤقتًا استخدام:
    |
    | $error = $e->getMessage();
    |
    */
}


/*
|--------------------------------------------------------------------------
| إحصائيات
|--------------------------------------------------------------------------
*/

$totalCustomers = count($customers);

$activeCustomers = 0;
$blockedCustomers = 0;
$totalBalance = 0;


foreach ($customers as $customer) {

    if ($customer['status'] === 'active') {

        $activeCustomers++;
    }

    if ($customer['status'] === 'blocked') {

        $blockedCustomers++;
    }

    $totalBalance += (float) ($customer['balance'] ?? 0);
}


require_once __DIR__ . '/../includes/header.php';

?>


<!-- =====================================================
     رأس الصفحة
====================================================== -->

<!-- تم تعديل هذا القسم لجعل الزر في اليمين -->
<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 30px;">

    <!-- 1. العنوان -->
    <div>
        <h2 style="margin: 0;">العملاء</h2>
        <p>
            إدارة العملاء والحسابات والديون
        </p>
    </div>

    <!-- 2. الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            + إضافة عميل
        </a>
    </div>

</div>


<!-- =====================================================
     رسالة الخطأ
====================================================== -->

<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<!-- =====================================================
     البطاقات الإحصائية
====================================================== -->

<div class="cards">


    <!-- إجمالي العملاء -->

    <div class="card stat-card">

        <div class="stat-icon">
            👥
        </div>

        <div class="stat-info">

            <span>
                إجمالي العملاء
            </span>

            <strong>
                <?= $totalCustomers ?>
            </strong>

        </div>

    </div>


    <!-- العملاء النشطون -->

    <div class="card stat-card">

        <div class="stat-icon">
            ✅
        </div>

        <div class="stat-info">

            <span>
                العملاء النشطون
            </span>

            <strong>
                <?= $activeCustomers ?>
            </strong>

        </div>

    </div>


    <!-- العملاء المحظورون -->

    <div class="card stat-card">

        <div class="stat-icon">
            🚫
        </div>

        <div class="stat-info">

            <span>
                العملاء المحظورون
            </span>

            <strong>
                <?= $blockedCustomers ?>
            </strong>

        </div>

    </div>


    <!-- إجمالي المديونية -->

    <div class="card stat-card">

        <div class="stat-icon">
            💰
        </div>

        <div class="stat-info">

            <span>
                إجمالي المديونية
            </span>

            <strong>
                <?= number_format(
                    $totalBalance,
                    2
                ) ?>
            </strong>

        </div>

    </div>


</div>


<!-- =====================================================
     البحث والفلاتر
====================================================== -->

<div class="card">

    <h2>
        البحث عن عميل
    </h2>


    <form
        method="GET"
        action="index.php">

        <div class="form-grid">


            <!-- البحث -->

            <div class="form-group">

                <label for="search">
                    البحث
                </label>

                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?= htmlspecialchars(
                                $search,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    placeholder="رقم العميل أو الاسم أو الهاتف">

            </div>


            <!-- الحالة -->

            <div class="form-group">

                <label for="status">
                    الحالة
                </label>

                <select
                    id="status"
                    name="status">

                    <option value="">
                        جميع الحالات
                    </option>

                    <option
                        value="active"
                        <?= $status === 'active'
                            ? 'selected'
                            : '' ?>>
                        نشط
                    </option>

                    <option
                        value="inactive"
                        <?= $status === 'inactive'
                            ? 'selected'
                            : '' ?>>
                        غير نشط
                    </option>

                    <option
                        value="blocked"
                        <?= $status === 'blocked'
                            ? 'selected'
                            : '' ?>>
                        محظور
                    </option>

                </select>

            </div>


            <!-- الأزرار -->

            <div
                class="form-group"
                style="display:flex;align-items:end;gap:10px;">

                <button
                    type="submit"
                    class="btn btn-primary">
                    🔍 بحث
                </button>


                <a
                    href="index.php"
                    class="btn">
                    إعادة تعيين
                </a>

            </div>


        </div>

    </form>

</div>


<!-- =====================================================
     قائمة العملاء
====================================================== -->

<!-- التعديل 1: إضافة overflow: hidden لمنع البطاقة من التمدد خارج الشاشة -->
<div class="card" style="overflow: hidden;">


    <div class="page-header">

        <div>

            <h2>
                قائمة العملاء
            </h2>

            <p>
                عدد النتائج:
                <strong>
                    <?= $totalCustomers ?>
                </strong>
            </p>

        </div>


        <div>

            <a
                href="create.php"
                class="btn btn-primary">
                + إضافة عميل
            </a>

        </div>

    </div>


    <!-- التعديل 2: تفعيل السكرول الأفقي بشكل سلس -->
    <div class="table-responsive" style="overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch;">

        <!-- التعديل 3: إجبار الجدول على عدم تقلص الأعمدة وتكسير النصوص -->
        <table class="table" style="min-width: 1100px; white-space: nowrap;">


            <thead>

                <tr>

                    <th>
                        #
                    </th>

                    <th>
                        رقم العميل
                    </th>

                    <th>
                        اسم العميل
                    </th>

                    <th>
                        الهاتف
                    </th>

                    <th>
                        حد الائتمان
                    </th>

                    <th>
                        الرصيد المستحق
                    </th>

                    <th>
                        الحالة
                    </th>

                    <th>
                        تاريخ التسجيل
                    </th>

                    <th>
                        الإجراءات
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php if (empty($customers)): ?>

                    <tr>

                        <td
                            colspan="9"
                            style="text-align:center;">

                            <?php if ($search !== '' || $status !== ''): ?>

                                لا توجد نتائج مطابقة للبحث.

                            <?php else: ?>

                                لا يوجد عملاء حتى الآن.

                            <?php endif; ?>

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($customers as $customer): ?>

                        <tr>


                            <!-- ID -->

                            <td>

                                <?= (int) $customer['id'] ?>

                            </td>


                            <!-- رقم العميل -->

                            <td>

                                <?= htmlspecialchars(
                                    $customer['customer_no'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <!-- الاسم -->

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $customer['full_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </td>


                            <!-- الهاتف -->

                            <td>

                                <?php if (
                                    !empty($customer['phone'])
                                ): ?>

                                    <?= htmlspecialchars(
                                        $customer['phone'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <!-- حد الائتمان -->

                            <td>

                                <?= number_format(
                                    (float)
                                    $customer['credit_limit'],
                                    2
                                ) ?>

                            </td>


                            <!-- الرصيد -->

                            <td>

                                <?php

                                $balance =
                                    (float)
                                    ($customer['balance'] ?? 0);

                                ?>


                                <?php if ($balance > 0): ?>

                                    <strong
                                        style="color:#dc3545;">

                                        <?= number_format(
                                            $balance,
                                            2
                                        ) ?>

                                    </strong>

                                <?php elseif ($balance < 0): ?>

                                    <strong
                                        style="color:#198754;">

                                        <?= number_format(
                                            abs($balance),
                                            2
                                        ) ?>

                                    </strong>

                                <?php else: ?>

                                    <span>
                                        0.00
                                    </span>

                                <?php endif; ?>


                            </td>


                            <!-- الحالة -->

                            <td>


                                <?php if (
                                    $customer['status']
                                    === 'active'
                                ): ?>

                                    <span
                                        class="badge badge-success">
                                        نشط
                                    </span>


                                <?php elseif (
                                    $customer['status']
                                    === 'blocked'
                                ): ?>

                                    <span
                                        class="badge badge-danger">
                                        محظور
                                    </span>


                                <?php else: ?>

                                    <span class="badge">
                                        غير نشط
                                    </span>

                                <?php endif; ?>


                            </td>


                            <!-- تاريخ التسجيل -->

                            <td>

                                <?= htmlspecialchars(
                                    $customer['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <!-- الإجراءات -->

                            <td>

                                <div
                                    style="
                                    display:flex;
                                    gap:6px;
                                    flex-wrap:wrap;
                                ">


                                    <!-- عرض العميل -->

                                    <a
                                        href="view.php?id=<?= (int) $customer['id'] ?>"
                                        class="btn btn-sm">
                                        👁 عرض
                                    </a>


                                    <!-- تعديل العميل -->

                                    <a
                                        href="edit.php?id=<?= (int) $customer['id'] ?>"
                                        class="btn btn-sm btn-primary">
                                        ✏️ تعديل
                                    </a>


                                </div>

                            </td>


                        </tr>

                    <?php endforeach; ?>


                <?php endif; ?>


            </tbody>

        </table>

    </div>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>