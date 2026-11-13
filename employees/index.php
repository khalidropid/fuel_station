<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$pageTitle = 'الموظفون';

$error = '';

$employees = [];

$stats = [
    'total'    => 0,
    'active'   => 0,
    'vacation' => 0,
    'inactive' => 0
];


/*
|--------------------------------------------------------------------------
| دالة حماية النصوص
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| أنواع الأجور
|--------------------------------------------------------------------------
*/

$salaryTypes = [

    'monthly' => 'شهري',

    'daily' => 'يومي',

    'hourly' => 'بالساعة',

    'shift' => 'بالوردية'

];


/*
|--------------------------------------------------------------------------
| قراءة الموظفين
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            id,
            employee_no,
            full_name,
            phone,
            address,
            job_title,
            salary_type,
            salary,
            hire_date,
            status,
            notes
        FROM employees
        ORDER BY id DESC
    ");

    $employees =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | الإحصائيات
    |--------------------------------------------------------------------------
    */

    $stats['total'] =
        count($employees);


    foreach ($employees as $employee) {

        if ($employee['status'] === 'active') {

            $stats['active']++;

        } elseif ($employee['status'] === 'vacation') {

            $stats['vacation']++;

        } else {

            $stats['inactive']++;
        }
    }


} catch (Throwable $e) {

    $error =
        $e->getMessage();
}


require_once __DIR__ . '/../includes/header.php';

?>


<style>

/*
|--------------------------------------------------------------------------
| Employees Page
|--------------------------------------------------------------------------
*/

.employees-page {
    display: flex;
    flex-direction: column;
    gap: 20px;
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

.employees-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.employees-header-info h2 {
    margin: 0 0 6px;
}

.employees-header-info p {
    margin: 0;
    color: #6b7280;
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

.employee-stats {
    display: grid;
    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 16px;
}

.employee-stat {
    background: #fff;
    border-radius: 14px;
    padding: 20px;

    display: flex;
    align-items: center;

    gap: 15px;

    border: 1px solid #e5e7eb;

    box-shadow:
        0 2px 8px rgba(0,0,0,.04);
}

.employee-stat-icon {
    width: 50px;
    height: 50px;

    border-radius: 12px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 22px;

    background: #f3f4f6;
}

.employee-stat-content {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.employee-stat-content span {
    color: #6b7280;
    font-size: 13px;
}

.employee-stat-content strong {
    font-size: 23px;
    color: #111827;
}


/*
|--------------------------------------------------------------------------
| Toolbar
|--------------------------------------------------------------------------
*/

.employees-toolbar {
    background: #fff;

    border: 1px solid #e5e7eb;

    border-radius: 14px;

    padding: 16px;

    display: flex;

    align-items: center;

    gap: 12px;

    flex-wrap: wrap;
}

.employee-search {
    position: relative;

    flex: 1;

    min-width: 250px;
}

.employee-search input {
    width: 100%;

    height: 44px;

    padding:
        0 42px 0 14px;

    border:
        1px solid #d1d5db;

    border-radius: 10px;

    outline: none;

    font-family: inherit;
}

.employee-search input:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px rgba(37,99,235,.1);
}

.employee-search-icon {

    position: absolute;

    right: 14px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #6b7280;
}

.employee-filter {

    height: 44px;

    min-width: 150px;

    padding:
        0 12px;

    border:
        1px solid #d1d5db;

    border-radius: 10px;

    background: #fff;

    font-family: inherit;

    outline: none;
}


/*
|--------------------------------------------------------------------------
| Table Card
|--------------------------------------------------------------------------
*/

.employees-card {

    background: #fff;

    border:
        1px solid #e5e7eb;

    border-radius: 14px;

    overflow: hidden;

    box-shadow:
        0 2px 8px rgba(0,0,0,.04);
}

.employees-card-header {

    padding:
        18px 20px;

    border-bottom:
        1px solid #e5e7eb;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}

.employees-card-header h3 {

    margin: 0;

    font-size: 17px;
}

.employees-count {

    color: #6b7280;

    font-size: 13px;
}


/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.employees-table-wrapper {

    overflow-x: auto;
}

.employees-table {

    width: 100%;

    border-collapse: collapse;

    min-width: 1150px;
}

.employees-table th {

    background: #f9fafb;

    color: #4b5563;

    font-size: 13px;

    font-weight: 600;

    padding:
        14px 16px;

    text-align: right;

    white-space: nowrap;

    border-bottom:
        1px solid #e5e7eb;
}

.employees-table td {

    padding:
        15px 16px;

    border-bottom:
        1px solid #f1f5f9;

    vertical-align: middle;

    color: #374151;
}

.employees-table tbody tr {

    transition:
        background .15s ease;
}

.employees-table tbody tr:hover {

    background:
        #f8fafc;
}

.employees-table tbody tr:last-child td {

    border-bottom: none;
}


/*
|--------------------------------------------------------------------------
| Employee Identity
|--------------------------------------------------------------------------
*/

.employee-identity {

    display: flex;

    align-items: center;

    gap: 11px;

    min-width: 190px;
}

.employee-avatar {

    width: 42px;

    height: 42px;

    min-width: 42px;

    border-radius: 50%;

    background:
        #eff6ff;

    color:
        #2563eb;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 700;

    font-size: 16px;
}

.employee-name {

    display: flex;

    flex-direction: column;

    gap: 3px;
}

.employee-name strong {

    color:
        #111827;
}

.employee-number {

    color:
        #9ca3af;

    font-size:
        12px;
}


/*
|--------------------------------------------------------------------------
| Job
|--------------------------------------------------------------------------
*/

.employee-job {

    display: flex;

    flex-direction: column;

    gap: 3px;
}

.employee-job strong {

    color:
        #374151;
}

.employee-job span {

    color:
        #9ca3af;

    font-size:
        12px;
}


/*
|--------------------------------------------------------------------------
| Salary
|--------------------------------------------------------------------------
*/

.employee-salary {

    display: flex;

    flex-direction: column;

    gap: 3px;
}

.employee-salary strong {

    color:
        #111827;
}

.employee-salary span {

    color:
        #6b7280;

    font-size:
        12px;
}


/*
|--------------------------------------------------------------------------
| Badges
|--------------------------------------------------------------------------
*/

.employee-status {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding:
        5px 10px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 600;
}

.employee-status-dot {

    width: 6px;

    height: 6px;

    border-radius: 50%;

    background:
        currentColor;
}

.employee-status-active {

    background:
        #ecfdf5;

    color:
        #047857;
}

.employee-status-vacation {

    background:
        #fffbeb;

    color:
        #b45309;
}

.employee-status-inactive {

    background:
        #fef2f2;

    color:
        #b91c1c;
}


/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

.employee-actions {

    display: flex;

    align-items: center;

    gap: 6px;

    white-space: nowrap;
}

.employee-action {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 34px;

    padding:
        0 10px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

    border:
        1px solid transparent;

    transition:
        .15s ease;
}


/*
|--------------------------------------------------------------------------
| Edit
|--------------------------------------------------------------------------
*/

.employee-action-edit {

    color:
        #2563eb;

    background:
        #eff6ff;

    border-color:
        #dbeafe;
}

.employee-action-edit:hover {

    background:
        #dbeafe;
}


/*
|--------------------------------------------------------------------------
| Toggle
|--------------------------------------------------------------------------
*/

.employee-action-toggle {

    color:
        #4b5563;

    background:
        #f3f4f6;

    border-color:
        #e5e7eb;
}

.employee-action-toggle:hover {

    background:
        #e5e7eb;
}


/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

.employee-action-delete {

    color:
        #b91c1c;

    background:
        #fef2f2;

    border-color:
        #fecaca;
}

.employee-action-delete:hover {

    background:
        #fee2e2;
}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.employee-empty {

    padding:
        55px 20px !important;

    text-align:
        center;
}

.employee-empty-icon {

    font-size:
        38px;

    margin-bottom:
        10px;
}

.employee-empty strong {

    display:
        block;

    margin-bottom:
        6px;
}

.employee-empty span {

    color:
        #9ca3af;

    font-size:
        13px;
}


/*
|--------------------------------------------------------------------------
| No Search Results
|--------------------------------------------------------------------------
*/

.employee-no-results {

    display:
        none;

    padding:
        40px;

    text-align:
        center;

    color:
        #6b7280;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 1000px) {

    .employee-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }

}


@media (max-width: 600px) {

    .employee-stats {

        grid-template-columns:
            1fr;
    }


    .employees-header {

        align-items:
            stretch;
    }


    .employees-header .btn {

        width:
            100%;

        text-align:
            center;
    }


    .employee-search {

        min-width:
            100%;
    }


    .employee-filter {

        width:
            100%;
    }

}

</style>


<div class="employees-page">


    <!-- =====================================================
         Header
    ====================================================== -->

<!-- تم تعديل هذا القسم لجعل الزر في اليمين -->
<div class="page-header" style="display: flex; flex-direction: column; align-items: flex-start; gap: 30px;">

    <!-- 1. العنوان -->
    <div>
        <h2 style="margin: 0;">الموظفون</h2>
        <p>
                إدارة بيانات الموظفين والأجور والحالة الوظيفية.
            </p>
    </div>

    <!-- 2. الزر -->
    <div>
        <a href="create.php" class="btn btn-primary" style="padding: 10px 20px; font-weight: bold;">
            + إضافة موظف
        </a>
    </div>

</div>


    <!-- =====================================================
         Success Messages
    ====================================================== -->

    <?php if (isset($_GET['success'])): ?>

        <div class="alert alert-success">

            <?php if ($_GET['success'] === 'created'): ?>

                تم إضافة الموظف بنجاح.

            <?php elseif ($_GET['success'] === 'updated'): ?>

                تم تعديل بيانات الموظف بنجاح.

            <?php elseif ($_GET['success'] === 'status'): ?>

                تم تغيير حالة الموظف بنجاح.

            <?php elseif ($_GET['success'] === 'deleted'): ?>

                تم حذف الموظف بنجاح.

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         Error
    ====================================================== -->

    <?php if ($error): ?>

        <div class="alert alert-danger">

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         Statistics
    ====================================================== -->

    <div class="employee-stats">


        <div class="employee-stat">

            <div class="employee-stat-icon">
                👥
            </div>

            <div class="employee-stat-content">

                <span>
                    إجمالي الموظفين
                </span>

                <strong>
                    <?= number_format($stats['total']) ?>
                </strong>

            </div>

        </div>


        <div class="employee-stat">

            <div class="employee-stat-icon">
                ✅
            </div>

            <div class="employee-stat-content">

                <span>
                    الموظفون النشطون
                </span>

                <strong>
                    <?= number_format($stats['active']) ?>
                </strong>

            </div>

        </div>


        <div class="employee-stat">

            <div class="employee-stat-icon">
                🌴
            </div>

            <div class="employee-stat-content">

                <span>
                    في إجازة
                </span>

                <strong>
                    <?= number_format($stats['vacation']) ?>
                </strong>

            </div>

        </div>


        <div class="employee-stat">

            <div class="employee-stat-icon">
                ⛔
            </div>

            <div class="employee-stat-content">

                <span>
                    غير نشط
                </span>

                <strong>
                    <?= number_format($stats['inactive']) ?>
                </strong>

            </div>

        </div>

    </div>


    <!-- =====================================================
         Toolbar
    ====================================================== -->

    <div class="employees-toolbar">

        <div class="employee-search">

            <span class="employee-search-icon">
                🔍
            </span>

            <input
                type="text"
                id="employeeSearch"
                placeholder="ابحث باسم الموظف أو الرقم أو الهاتف أو الوظيفة..."
                autocomplete="off"
            >

        </div>


        <select
            id="employeeStatusFilter"
            class="employee-filter"
        >

            <option value="">
                كل الحالات
            </option>

            <option value="active">
                نشط
            </option>

            <option value="vacation">
                إجازة
            </option>

            <option value="inactive">
                غير نشط
            </option>

        </select>

    </div>


    <!-- =====================================================
         Employees Table
    ====================================================== -->

    <div class="employees-card">


        <div class="employees-card-header">

            <h3>
                قائمة الموظفين
            </h3>

            <span
                class="employees-count"
                id="employeesCount"
            >
                <?= number_format(count($employees)) ?>
                موظف
            </span>

        </div>


        <div class="employees-table-wrapper">

            <table
                class="employees-table"
                id="employeesTable"
            >

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            الموظف
                        </th>

                        <th>
                            الهاتف
                        </th>

                        <th>
                            الوظيفة
                        </th>

                        <th>
                            الأجر
                        </th>

                        <th>
                            تاريخ التوظيف
                        </th>

                        <th>
                            الحالة
                        </th>

                        <th>
                            الإجراءات
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (!$employees): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="employee-empty"
                        >

                            <div class="employee-empty-icon">
                                👥
                            </div>

                            <strong>
                                لا يوجد موظفون
                            </strong>

                            <span>
                                قم بإضافة أول موظف إلى النظام.
                            </span>

                        </td>

                    </tr>

                <?php else: ?>


                    <?php foreach (
                        $employees
                        as $index => $employee
                    ): ?>


                        <?php

                        $status =
                            $employee['status'];

                        $statusText =
                            'غير نشط';

                        $statusClass =
                            'employee-status-inactive';


                        if ($status === 'active') {

                            $statusText =
                                'نشط';

                            $statusClass =
                                'employee-status-active';

                        } elseif (
                            $status === 'vacation'
                        ) {

                            $statusText =
                                'إجازة';

                            $statusClass =
                                'employee-status-vacation';
                        }


                        $salaryType =
                            $salaryTypes[
                                $employee['salary_type']
                            ]
                            ??
                            $employee['salary_type'];


                        $initial =
                            mb_substr(
                                trim(
                                    $employee['full_name']
                                ),
                                0,
                                1,
                                'UTF-8'
                            );

                        ?>


                        <tr
                            class="employee-row"
                            data-status="<?= e($status) ?>"
                            data-search="<?= e(
                                $employee['employee_no']
                                . ' '
                                . $employee['full_name']
                                . ' '
                                . ($employee['phone'] ?? '')
                                . ' '
                                . ($employee['job_title'] ?? '')
                            ) ?>"
                        >


                            <!-- Number -->

                            <td>

                                <?= $index + 1 ?>

                            </td>


                            <!-- Employee -->

                            <td>

                                <div class="employee-identity">

                                    <div class="employee-avatar">

                                        <?= e($initial) ?>

                                    </div>


                                    <div class="employee-name">

                                        <strong>

                                            <?= e(
                                                $employee['full_name']
                                            ) ?>

                                        </strong>

                                        <span class="employee-number">

                                            رقم الموظف:

                                            <?= e(
                                                $employee['employee_no']
                                            ) ?>

                                        </span>

                                    </div>

                                </div>

                            </td>


                            <!-- Phone -->

                            <td>

                                <?php if (
                                    !empty(
                                        $employee['phone']
                                    )
                                ): ?>

                                    <?= e(
                                        $employee['phone']
                                    ) ?>

                                <?php else: ?>

                                    <span
                                        style="color:#9ca3af;"
                                    >
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Job -->

                            <td>

                                <div class="employee-job">

                                    <strong>

                                        <?= !empty(
                                            $employee['job_title']
                                        )
                                            ? e(
                                                $employee['job_title']
                                            )
                                            : 'بدون تحديد'
                                        ?>

                                    </strong>


                                    <?php if (
                                        !empty(
                                            $employee['address']
                                        )
                                    ): ?>

                                        <span>

                                            <?= e(
                                                $employee['address']
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </div>

                            </td>


                            <!-- Salary -->

                            <td>

                                <div class="employee-salary">

                                    <strong>

                                        <?= number_format(
                                            (float)$employee['salary'],
                                            2
                                        ) ?>

                                    </strong>

                                    <span>

                                        <?= e(
                                            $salaryType
                                        ) ?>

                                    </span>

                                </div>

                            </td>


                            <!-- Hire Date -->

                            <td>

                                <?= !empty(
                                    $employee['hire_date']
                                )
                                    ? e(
                                        $employee['hire_date']
                                    )
                                    : '—'
                                ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <span
                                    class="employee-status <?= e(
                                        $statusClass
                                    ) ?>"
                                >

                                    <span
                                        class="employee-status-dot"
                                    ></span>

                                    <?= e(
                                        $statusText
                                    ) ?>

                                </span>

                            </td>


                            <!-- Actions -->

                            <td>

                                <div class="employee-actions">


                                    <!-- Edit -->

                                    <a
                                        href="edit.php?id=<?= (int)$employee['id'] ?>"
                                        class="employee-action employee-action-edit"
                                    >
                                        تعديل
                                    </a>


                                    <!-- Toggle Status -->

                                    <a
                                        href="toggle.php?id=<?= (int)$employee['id'] ?>"
                                        class="employee-action employee-action-toggle"
                                        onclick="return confirm('هل تريد تغيير حالة الموظف؟');"
                                    >
                                        تغيير الحالة
                                    </a>


                                    <!-- Delete -->

                                    <a
                                        href="delete.php?id=<?= (int)$employee['id'] ?>"
                                        class="employee-action employee-action-delete"
                                        onclick="return confirm('⚠️ هل أنت متأكد من حذف هذا الموظف؟\\n\\nسيتم حذف الموظف فقط إذا لم تكن لديه عمليات مالية أو مسحوبات مرتبطة به.');"
                                    >
                                        حذف
                                    </a>


                                </div>

                            </td>

                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>


            <div
                id="employeeNoResults"
                class="employee-no-results"
            >

                لا توجد نتائج مطابقة للبحث.

            </div>

        </div>

    </div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        const search =
            document.getElementById(
                'employeeSearch'
            );


        const filter =
            document.getElementById(
                'employeeStatusFilter'
            );


        const rows =
            Array.from(
                document.querySelectorAll(
                    '.employee-row'
                )
            );


        const count =
            document.getElementById(
                'employeesCount'
            );


        const noResults =
            document.getElementById(
                'employeeNoResults'
            );


        /*
        |--------------------------------------------------------------------------
        | فلترة الموظفين
        |--------------------------------------------------------------------------
        */

        function filterEmployees() {


            const searchValue =
                (
                    search?.value
                    || ''
                )
                .trim()
                .toLowerCase();


            const statusValue =
                filter?.value
                || '';


            let visibleCount = 0;


            rows.forEach(
                function (row) {


                    const text =
                        (
                            row.dataset.search
                            || ''
                        )
                        .toLowerCase();


                    const rowStatus =
                        row.dataset.status
                        || '';


                    const matchesSearch =
                        !searchValue
                        ||
                        text.includes(
                            searchValue
                        );


                    const matchesStatus =
                        !statusValue
                        ||
                        rowStatus === statusValue;


                    const visible =
                        matchesSearch
                        &&
                        matchesStatus;


                    row.style.display =
                        visible
                            ? ''
                            : 'none';


                    if (visible) {

                        visibleCount++;

                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | تحديث العدد
            |--------------------------------------------------------------------------
            */

            if (count) {

                count.textContent =
                    visibleCount + ' موظف';

            }


            /*
            |--------------------------------------------------------------------------
            | لا توجد نتائج
            |--------------------------------------------------------------------------
            */

            if (noResults) {

                noResults.style.display =
                    (
                        rows.length > 0
                        &&
                        visibleCount === 0
                    )
                        ? 'block'
                        : 'none';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | البحث
        |--------------------------------------------------------------------------
        */

        if (search) {

            search.addEventListener(
                'input',
                filterEmployees
            );

        }


        /*
        |--------------------------------------------------------------------------
        | فلتر الحالة
        |--------------------------------------------------------------------------
        */

        if (filter) {

            filter.addEventListener(
                'change',
                filterEmployees
            );

        }

    }
);

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>