<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'لوحة التحكم';

$error = '';

/*
|--------------------------------------------------------------------------
| القيم الافتراضية
|--------------------------------------------------------------------------
*/

$fuelTypesCount      = 0;
$fuelPricesCount     = 0;
$pumpsCount          = 0;
$employeesCount      = 0;
$tanksCount          = 0;
$openShiftsCount     = 0;

$todaySales          = 0.00;
$todayCashSales      = 0.00;
$todayCardSales      = 0.00;
$todayCreditSales    = 0.00;

$todaySalesCount     = 0;
$totalStock          = 0.00;

$recentSales         = [];
$lowStockTanks       = [];


/*
|--------------------------------------------------------------------------
| تنفيذ استعلامات لوحة التحكم
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | عدد أنواع الوقود النشطة
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM fuel_types
        WHERE is_active = 1
    ");

    $fuelTypesCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | عدد أسعار الوقود
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM fuel_prices
    ");

    $fuelPricesCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | عدد الطرمبات النشطة
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM pumps
        WHERE status = 'active'
    ");

    $pumpsCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | عدد الموظفين النشطين
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM employees
        WHERE status = 'active'
    ");

    $employeesCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | عدد الخزانات
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM tanks
    ");

    $tanksCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | عدد الورديات المفتوحة
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM shifts
        WHERE status = 'open'
    ");

    $openShiftsCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | إجمالي مخزون الخزانات
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            COALESCE(
                SUM(current_volume),
                0
            )
        FROM tanks
    ");

    $totalStock = (float) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | مبيعات اليوم
    |--------------------------------------------------------------------------
    |
    | يتم فصل طرق الدفع حتى تكون أرقام لوحة التحكم واضحة.
    |
    */

    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS sales_count,

            COALESCE(
                SUM(total),
                0
            ) AS total_sales,

            COALESCE(
                SUM(
                    CASE
                        WHEN payment_method = 'cash'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS cash_sales,

            COALESCE(
                SUM(
                    CASE
                        WHEN payment_method = 'card'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS card_sales,

            COALESCE(
                SUM(
                    CASE
                        WHEN payment_method = 'credit'
                        THEN total
                        ELSE 0
                    END
                ),
                0
            ) AS credit_sales

        FROM sales

        WHERE sale_date >= CURDATE()
          AND sale_date < DATE_ADD(
              CURDATE(),
              INTERVAL 1 DAY
          )
    ");

    $salesToday = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($salesToday) {

        $todaySalesCount =
            (int) (
                $salesToday['sales_count']
                ?? 0
            );

        $todaySales =
            (float) (
                $salesToday['total_sales']
                ?? 0
            );

        $todayCashSales =
            (float) (
                $salesToday['cash_sales']
                ?? 0
            );

        $todayCardSales =
            (float) (
                $salesToday['card_sales']
                ?? 0
            );

        $todayCreditSales =
            (float) (
                $salesToday['credit_sales']
                ?? 0
            );
    }


    /*
    |--------------------------------------------------------------------------
    | آخر عمليات البيع
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            s.id,
            s.invoice_no,
            s.sale_date,
            s.payment_method,
            s.total,

            e.full_name AS employee_name,

            p.pump_no,
            p.name AS pump_name,

            c.full_name AS customer_name

        FROM sales s

        LEFT JOIN employees e
            ON e.id = s.employee_id

        LEFT JOIN pumps p
            ON p.id = s.pump_id

        LEFT JOIN customers c
            ON c.id = s.customer_id

        ORDER BY
            s.id DESC

        LIMIT 8
    ");

    $recentSales =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | الخزانات منخفضة المخزون
    |--------------------------------------------------------------------------
    |
    | يتم اعتبار الخزان منخفضًا إذا وصل مخزونه
    | إلى 20% أو أقل من سعته.
    |
    */

    $stmt = $pdo->query("
        SELECT
            t.id,
            t.name,
            t.capacity,
            t.current_volume,

            ft.name AS fuel_name

        FROM tanks t

        LEFT JOIN fuel_types ft
            ON ft.id = t.fuel_type_id

        WHERE
            t.capacity > 0
            AND (
                t.current_volume / t.capacity
            ) <= 0.20

        ORDER BY
            (
                t.current_volume / t.capacity
            ) ASC

        LIMIT 6
    ");

    $lowStockTanks =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | لا نعرض تفاصيل قاعدة البيانات للمستخدم النهائي
    |--------------------------------------------------------------------------
    */

    $error =
        'تعذر تحميل بعض بيانات لوحة التحكم.';

    /*
    | يمكن تسجيل الخطأ في ملف السجل
    | بدل عرضه للمستخدم.
    */

    error_log(
        'Dashboard Error: '
        . $e->getMessage()
    );
}


require_once __DIR__ . '/../includes/header.php';

?>


<!-- =========================================================
     رسالة الخطأ
========================================================= -->

<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars(
            $error,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     عنوان الصفحة
========================================================= -->

<div class="page-header">

    <div>

        <h2>
            لوحة التحكم
        </h2>

        <p>
            نظرة عامة على حالة محطة الريان والعمليات اليومية.
        </p>

    </div>

</div>


<!-- =========================================================
     الإحصائيات الرئيسية
========================================================= -->

<div class="cards">


    <!-- أنواع الوقود -->

    <div class="card stat-card">

        <div class="stat-icon">
            ⛽
        </div>

        <div class="stat-info">

            <span>
                أنواع الوقود
            </span>

            <strong>
                <?= number_format(
                    $fuelTypesCount
                ) ?>
            </strong>

        </div>

    </div>


    <!-- الطرمبات -->

    <div class="card stat-card">

        <div class="stat-icon">
            🔧
        </div>

        <div class="stat-info">

            <span>
                الطرمبات النشطة
            </span>

            <strong>
                <?= number_format(
                    $pumpsCount
                ) ?>
            </strong>

        </div>

    </div>


    <!-- الموظفون -->

    <div class="card stat-card">

        <div class="stat-icon">
            👨‍🔧
        </div>

        <div class="stat-info">

            <span>
                الموظفون
            </span>

            <strong>
                <?= number_format(
                    $employeesCount
                ) ?>
            </strong>

        </div>

    </div>


    <!-- الورديات -->

    <div class="card stat-card">

        <div class="stat-icon">
            🔄
        </div>

        <div class="stat-info">

            <span>
                الورديات المفتوحة
            </span>

            <strong>
                <?= number_format(
                    $openShiftsCount
                ) ?>
            </strong>

        </div>

    </div>


    <!-- مبيعات اليوم -->

    <div class="card stat-card">

        <div class="stat-icon">
            💵
        </div>

        <div class="stat-info">

            <span>
                مبيعات اليوم
            </span>

            <strong>
                <?= number_format(
                    $todaySales,
                    2
                ) ?>
            </strong>

        </div>

    </div>


    <!-- المبيعات النقدية -->

    <div class="card stat-card">

        <div class="stat-icon">
            💰
        </div>

        <div class="stat-info">

            <span>
                المبيعات النقدية
            </span>

            <strong>
                <?= number_format(
                    $todayCashSales,
                    2
                ) ?>
            </strong>

        </div>

    </div>


    <!-- المبيعات بالبطاقة -->

    <div class="card stat-card">

        <div class="stat-icon">
            💳
        </div>

        <div class="stat-info">

            <span>
                مبيعات البطاقة
            </span>

            <strong>
                <?= number_format(
                    $todayCardSales,
                    2
                ) ?>
            </strong>

        </div>

    </div>


    <!-- المبيعات الآجلة -->

    <div class="card stat-card">

        <div class="stat-icon">
            🧾
        </div>

        <div class="stat-info">

            <span>
                المبيعات الآجلة
            </span>

            <strong>
                <?= number_format(
                    $todayCreditSales,
                    2
                ) ?>
            </strong>

        </div>

    </div>

</div>


<!-- =========================================================
     ملخص إضافي
========================================================= -->

<div class="cards">


    <!-- عدد العمليات -->

    <div class="card stat-card">

        <div class="stat-icon">
            📋
        </div>

        <div class="stat-info">

            <span>
                عمليات البيع اليوم
            </span>

            <strong>
                <?= number_format(
                    $todaySalesCount
                ) ?>
            </strong>

        </div>

    </div>


    <!-- الخزانات -->

    <div class="card stat-card">

        <div class="stat-icon">
            🛢️
        </div>

        <div class="stat-info">

            <span>
                الخزانات
            </span>

            <strong>
                <?= number_format(
                    $tanksCount
                ) ?>
            </strong>

        </div>

    </div>


    <!-- المخزون -->

    <div class="card stat-card">

        <div class="stat-icon">
            📦
        </div>

        <div class="stat-info">

            <span>
                إجمالي المخزون
            </span>

            <strong>
                <?= number_format(
                    $totalStock,
                    2
                ) ?>
                <small style="font-size:11px;">
                    لتر
                </small>
            </strong>

        </div>

    </div>


    <!-- أسعار الوقود -->

    <div class="card stat-card">

        <div class="stat-icon">
            💲
        </div>

        <div class="stat-info">

            <span>
                أسعار الوقود
            </span>

            <strong>
                <?= number_format(
                    $fuelPricesCount
                ) ?>
            </strong>

        </div>

    </div>

</div>


<!-- =========================================================
     آخر عمليات البيع
========================================================= -->

<div class="table-card">

    <div
        style="
            padding:18px 20px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:15px;
            border-bottom:1px solid var(--border);
        "
    >

        <div>

            <h2
                style="
                    margin:0;
                    font-size:16px;
                "
            >
                آخر عمليات البيع
            </h2>

            <p
                style="
                    margin:3px 0 0;
                    color:#64748b;
                    font-size:11px;
                "
            >
                أحدث العمليات المسجلة في النظام
            </p>

        </div>

        <a
            href="/sales/"
            class="btn btn-secondary"
        >
            عرض جميع المبيعات
        </a>

    </div>


    <div class="table-responsive">

        <table class="table">

            <thead>

                <tr>

                    <th>
                        الفاتورة
                    </th>

                    <th>
                        التاريخ
                    </th>

                    <th>
                        الطرمبة
                    </th>

                    <th>
                        الموظف
                    </th>

                    <th>
                        العميل
                    </th>

                    <th>
                        الدفع
                    </th>

                    <th>
                        الإجمالي
                    </th>

                    <th>
                        الإجراء
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (!$recentSales): ?>

                <tr>

                    <td
                        colspan="8"
                        style="
                            text-align:center;
                            padding:30px;
                            color:#64748b;
                        "
                    >
                        لا توجد عمليات بيع مسجلة.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($recentSales as $sale): ?>

                    <tr>

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $sale['invoice_no'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </strong>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                date(
                                    'Y-m-d H:i',
                                    strtotime(
                                        $sale['sale_date']
                                    )
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <?=
                                'طرمبة '
                                .
                                htmlspecialchars(
                                    $sale['pump_no']
                                    ?? '-',
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                            ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $sale['employee_name']
                                ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $sale['customer_name']
                                ?? 'عميل نقدي',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <?php

                            $paymentLabels = [

                                'cash' =>
                                    [
                                        'text' =>
                                            'نقدي',
                                        'class' =>
                                            'badge-success'
                                    ],

                                'card' =>
                                    [
                                        'text' =>
                                            'بطاقة',
                                        'class' =>
                                            'badge'
                                    ],

                                'credit' =>
                                    [
                                        'text' =>
                                            'آجل',
                                        'class' =>
                                            'badge-warning'
                                    ]

                            ];

                            $payment =
                                $paymentLabels[
                                    $sale['payment_method']
                                ]
                                ??
                                [
                                    'text' =>
                                        $sale['payment_method'],
                                    'class' =>
                                        'badge'
                                ];

                            ?>

                            <span
                                class="badge <?= $payment['class'] ?>"
                            >

                                <?= htmlspecialchars(
                                    $payment['text'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <strong>

                                <?= number_format(
                                    (float)$sale['total'],
                                    2
                                ) ?>

                            </strong>

                        </td>


                        <td>

                            <a
                                href="/sales/view.php?id=<?= (int)$sale['id'] ?>"
                                class="btn btn-secondary"
                                style="
                                    min-height:32px;
                                    padding:6px 10px;
                                "
                            >
                                عرض
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- =========================================================
     الخزانات منخفضة المخزون
========================================================= -->

<?php if ($lowStockTanks): ?>

    <div
        class="table-card"
        style="margin-top:22px;"
    >

        <div
            style="
                padding:18px 20px;
                border-bottom:1px solid var(--border);
            "
        >

            <h2
                style="
                    margin:0;
                    font-size:16px;
                "
            >
                ⚠️ تنبيه المخزون
            </h2>

            <p
                style="
                    margin:3px 0 0;
                    color:#64748b;
                    font-size:11px;
                "
            >
                خزانات وصلت إلى 20% أو أقل من سعتها.
            </p>

        </div>


        <div class="table-responsive">

            <table class="table">

                <thead>

                    <tr>

                        <th>
                            الخزان
                        </th>

                        <th>
                            نوع الوقود
                        </th>

                        <th>
                            السعة
                        </th>

                        <th>
                            المخزون الحالي
                        </th>

                        <th>
                            نسبة الامتلاء
                        </th>

                        <th>
                            الحالة
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($lowStockTanks as $tank): ?>

                    <?php

                    $capacity =
                        (float)$tank['capacity'];

                    $currentVolume =
                        (float)$tank['current_volume'];

                    $percentage =
                        $capacity > 0
                            ? (
                                $currentVolume
                                /
                                $capacity
                                *
                                100
                            )
                            : 0;

                    ?>

                    <tr>

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $tank['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </strong>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $tank['fuel_name']
                                ?? '-',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <td>

                            <?= number_format(
                                $capacity,
                                2
                            ) ?>

                            لتر

                        </td>


                        <td>

                            <strong>

                                <?= number_format(
                                    $currentVolume,
                                    2
                                ) ?>

                            </strong>

                            لتر

                        </td>


                        <td>

                            <?= number_format(
                                $percentage,
                                1
                            ) ?>%

                        </td>


                        <td>

                            <span
                                class="badge badge-danger"
                            >
                                مخزون منخفض
                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

<?php endif; ?>


<!-- =========================================================
     رسالة ترحيب
========================================================= -->

<div
    class="card"
    style="margin-top:22px;"
>

    <h2>
        مرحباً بك في نظام إدارة محطة الريان ⛽
    </h2>

    <p>
        للتواصل مع شركة ATTAK_7.OCT على الارقام التالية
        773330258  . . . 772452744  . . . . 777590913

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>