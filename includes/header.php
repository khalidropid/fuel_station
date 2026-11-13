<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentUri  = $_SERVER['REQUEST_URI'] ?? '';

?>

<!DOCTYPE html>

<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta name="theme-color"
        content="#111827">

    <title>
        <?= htmlspecialchars(
            $pageTitle ?? 'محطة الوقود',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

    <link rel="stylesheet"
        href="/assets/css/style.css">

</head>

<body>

    <div class="app">

        <!-- =================================================
         Mobile Overlay
    ================================================== -->

        <div
            class="sidebar-overlay"
            id="sidebarOverlay"
            onclick="closeSidebar()"></div>


        <!-- =================================================
         Sidebar
    ================================================== -->

        <aside
            class="sidebar"
            id="sidebar">

            <!-- Logo -->

            <div class="sidebar-logo">

                <div class="logo-icon">
                    ⛽
                </div>

                <div class="sidebar-logo-text">

                    <h2>
                        محطة الريان
                    </h2>

                    <span>
                        نظام الإدارة
                    </span>

                </div>

                <!-- Mobile Close -->

                <button
                    type="button"
                    class="sidebar-close"
                    onclick="closeSidebar()"
                    aria-label="إغلاق القائمة">
                    ×
                </button>

            </div>


            <!-- =================================================
             Navigation
        ================================================== -->

            <nav class="sidebar-menu">

                <!-- لوحة التحكم -->

                <a
                    href="/dashboard/"
                    class="<?= (
                                $currentPage === 'index.php'
                                &&
                                str_contains(
                                    $currentUri,
                                    '/dashboard/'
                                )
                            ) ? 'active' : '' ?>">

                    <span>📊</span>

                    <span class="menu-text">
                        لوحة التحكم
                    </span>

                </a>


                <!-- =================================================
                 إدارة المحطة
            ================================================== -->

                <div class="menu-title">
                    إدارة المحطة
                </div>


                <a
                    href="/fuel_types/"
                    class="<?= str_contains(
                                $currentUri,
                                '/fuel_types/'
                            ) ? 'active' : '' ?>">

                    <span>⛽</span>

                    <span class="menu-text">
                        أنواع الوقود
                    </span>

                </a>


                <a
                    href="/fuel_prices/"
                    class="<?= str_contains(
                                $currentUri,
                                '/fuel_prices/'
                            ) ? 'active' : '' ?>">

                    <span>💰</span>

                    <span class="menu-text">
                        أسعار الوقود
                    </span>

                </a>


                <a
                    href="/tanks/"
                    class="<?= str_contains(
                                $currentUri,
                                '/tanks/'
                            ) ? 'active' : '' ?>">

                    <span>🛢️</span>

                    <span class="menu-text">
                        الخزانات
                    </span>

                </a>


                <a
                    href="/tank_status/"
                    class="<?= str_contains(
                                $currentUri,
                                '/tank_status/'
                            ) ? 'active' : '' ?>">

                    <span>🔋</span>

                    <span class="menu-text">
                        حالة الخزانات
                    </span>

                </a>


                <a
                    href="/pumps/"
                    class="<?= str_contains(
                                $currentUri,
                                '/pumps/'
                            ) ? 'active' : '' ?>">

                    <span>🔧</span>

                    <span class="menu-text">
                        الطرمبات
                    </span>

                </a>


                <a
                    href="/pump_readings/"
                    class="<?= str_contains(
                                $currentUri,
                                '/pump_readings/'
                            ) ? 'active' : '' ?>">

                    <span>📊</span>

                    <span class="menu-text">
                        قراءات الطرمبات
                    </span>

                </a>


                <!-- =================================================
                 العمليات
            ================================================== -->

                <div class="menu-title">
                    العمليات
                </div>


                <a
                    href="/shifts/index.php"
                    class="<?= str_contains(
                                $currentUri,
                                '/shifts/'
                            ) ? 'active' : '' ?>">

                    <span>🔄</span>

                    <span class="menu-text">
                        الورديات
                    </span>

                </a>


                <!-- <a
                href="/sales/index.php"
                class="<?= str_contains(
                            $currentUri,
                            '/sales/'
                        ) ? 'active' : '' ?>"
            >

                <span>💰</span>

                <span class="menu-text">
                    المبيعات
                </span>

            </a> -->


                <!-- <a
                href="/invoices/index.php"
                class="<?= str_contains(
                            $currentUri,
                            '/invoices/'
                        ) ? 'active' : '' ?>"
            >

                <span>🧾</span>

                <span class="menu-text">
                    الفواتير
                </span>

            </a> -->


                <a
                    href="/customers/index.php"
                    class="<?= str_contains(
                                $currentUri,
                                '/customers/'
                            ) ? 'active' : '' ?>">

                    <span>👨‍🔧</span>

                    <span class="menu-text">
                        العملاء
                    </span>

                </a>


                <a
                    href="/customer_payments/index.php"
                    class="<?= str_contains(
                                $currentUri,
                                '/customer_payments/'
                            ) ? 'active' : '' ?>">

                    <span>💵</span>

                    <span class="menu-text">
                        تحصيلات العملاء
                    </span>

                </a>


                <a
                    href="/employees/"
                    class="<?= str_contains(
                                $currentUri,
                                '/employees/'
                            ) ? 'active' : '' ?>">

                    <span>👨‍🔧</span>

                    <span class="menu-text">
                        الموظفون
                    </span>

                </a>


                <a
                    href="/withdrawals/"
                    class="<?= str_contains(
                                $currentUri,
                                '/withdrawals/'
                            ) ? 'active' : '' ?>">

                    <span>💸</span>

                    <span class="menu-text">
                        المسحوبات
                    </span>

                </a>


                <!-- =================================================
                 المحاسبة والحسابات
            ================================================== -->

                <div class="menu-title">
                    المحاسبة والحسابات
                </div>


                <a
                    href="/accounts/"
                    class="<?= str_contains(
                                $currentUri,
                                '/accounts/'
                            ) ? 'active' : '' ?>">

                    <span>📚</span>

                    <span class="menu-text">
                        دليل الحسابات
                    </span>

                </a>


                <a
                    href="/accounts/transaction.php"
                    class="<?= (
                                $currentPage === 'transaction.php'
                                &&
                                str_contains(
                                    $currentUri,
                                    '/accounts/'
                                )
                            ) ? 'active' : '' ?>">

                    <span>🧾</span>

                    <span class="menu-text">
                        معاملة مالية
                    </span>

                </a>


                <a
                    href="/employees/salary.php"
                    class="<?= (
                                $currentPage === 'salary.php'
                                &&
                                str_contains(
                                    $currentUri,
                                    '/employees/'
                                )
                            ) ? 'active' : '' ?>">

                    <span>💵</span>

                    <span class="menu-text">
                        رواتب الموظفين
                    </span>

                </a>


                <a
                    href="/owners/view.php"
                    class="<?= str_contains(
                                $currentUri,
                                '/owners/'
                            ) ? 'active' : '' ?>">

                    <span>👤</span>

                    <span class="menu-text">
                        حساب المالك
                    </span>

                </a>


                <a
                    href="/expenses/create.php"
                    class="<?= str_contains(
                                $currentUri,
                                '/expenses/'
                            ) ? 'active' : '' ?>">

                    <span>💸</span>

                    <span class="menu-text">
                        المصروفات
                    </span>

                </a>


                <a
                    href="/suppliers/view.php"
                    class="<?= str_contains(
                                $currentUri,
                                '/suppliers/'
                            ) ? 'active' : '' ?>">

                    <span>🏢</span>

                    <span class="menu-text">
                        حسابات الموردين
                    </span>

                </a>


                <!-- =================================================
                 التقارير
            ================================================== -->

                <div class="menu-title">
                    التقارير
                </div>


                <a
                    href="/reports/index.php"
                    class="<?= str_contains(
                                $currentUri,
                                '/reports/'
                            ) ? 'active' : '' ?>">

                    <span>📈</span>

                    <span class="menu-text">
                        لوحة التقارير
                    </span>

                </a>


                <a
                    href="/reports/sales.php"
                    class="<?= (
                                $currentPage === 'sales.php'
                                &&
                                str_contains(
                                    $currentUri,
                                    '/reports/'
                                )
                            ) ? 'active' : '' ?>">

                    <span>💰</span>

                    <span class="menu-text">
                        تقرير المبيعات
                    </span>

                </a>


                <a
                    href="/reports/accounts.php"
                    class="<?= (
                                $currentPage === 'accounts.php'
                                &&
                                str_contains(
                                    $currentUri,
                                    '/reports/'
                                )
                            ) ? 'active' : '' ?>">

                    <span>📒</span>

                    <span class="menu-text">
                        تقرير الحسابات
                    </span>

                </a>


                <!-- =================================================
                 النظام
            ================================================== -->

                <div class="menu-title">
                    النظام
                </div>


                <a
                    href="/settings/"
                    class="<?= str_contains(
                                $currentUri,
                                '/settings/'
                            ) ? 'active' : '' ?>">

                    <span>⚙️</span>

                    <span class="menu-text">
                        الإعدادات
                    </span>

                </a>

                <a href="/auth/logout.php">

                    <span>
                        🚪
                    </span>

                    <span class="menu-text">
                        تسجيل الخروج
                    </span>

                </a>

            </nav>


            <!-- =================================================
             Sidebar Footer
        ================================================== -->



        </aside>


        <!-- =================================================
         Main
    ================================================== -->

        <main class="main">


            <!-- =================================================
             Topbar
        ================================================== -->

            <header class="topbar">


                <div class="topbar-right">

                    <button
                        class="menu-toggle"
                        id="menuToggle"
                        onclick="toggleSidebar()"
                        type="button"
                        aria-label="فتح القائمة"
                        aria-expanded="false">
                        ☰
                    </button>


                    <div class="page-heading">

                        <h1>
                            <?= htmlspecialchars(
                                $pageTitle ?? 'لوحة التحكم',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h1>

                        <span>
                            <?= date('Y-m-d') ?>
                        </span>

                    </div>

                </div>


                <!-- =================================================
                 User
            ================================================== -->

                <div class="user-area">

                    <div class="user-avatar">

                        <?= htmlspecialchars(
                            mb_substr(
                                $_SESSION['full_name'] ?? 'م',
                                0,
                                1
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>


                    <div class="user-info">

                        <strong>

                            <?= htmlspecialchars(
                                $_SESSION['full_name']
                                    ??
                                    'المستخدم',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>


                        <small>

                            <?= htmlspecialchars(
                                $_SESSION['role']
                                    ??
                                    '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </small>

                    </div>

                </div>

            </header>


            <!-- =================================================
             Content
        ================================================== -->

            <section class="content">

                <script>
                    function openSidebar() {

                        const sidebar =
                            document.getElementById('sidebar');

                        const overlay =
                            document.getElementById('sidebarOverlay');

                        const menuToggle =
                            document.getElementById('menuToggle');


                        if (!sidebar || !overlay) {
                            return;
                        }


                        sidebar.classList.add('open');

                        overlay.classList.add('active');

                        document.body.classList.add('sidebar-open');


                        if (menuToggle) {

                            menuToggle.setAttribute(
                                'aria-expanded',
                                'true'
                            );
                        }
                    }


                    function closeSidebar() {

                        const sidebar =
                            document.getElementById('sidebar');

                        const overlay =
                            document.getElementById('sidebarOverlay');

                        const menuToggle =
                            document.getElementById('menuToggle');


                        if (!sidebar || !overlay) {
                            return;
                        }


                        sidebar.classList.remove('open');

                        overlay.classList.remove('active');

                        document.body.classList.remove('sidebar-open');


                        if (menuToggle) {

                            menuToggle.setAttribute(
                                'aria-expanded',
                                'false'
                            );
                        }
                    }


                    function toggleSidebar() {

                        const sidebar =
                            document.getElementById('sidebar');


                        if (!sidebar) {
                            return;
                        }


                        if (
                            sidebar.classList.contains('open')
                        ) {

                            closeSidebar();

                        } else {

                            openSidebar();
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | إغلاق القائمة بزر Escape
                    |--------------------------------------------------------------------------
                    */

                    document.addEventListener(
                        'keydown',
                        function(event) {

                            if (
                                event.key === 'Escape'
                            ) {

                                closeSidebar();
                            }
                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | إغلاق القائمة عند الضغط على رابط في الهاتف
                    |--------------------------------------------------------------------------
                    */

                    document.addEventListener(
                        'DOMContentLoaded',
                        function() {

                            const sidebar =
                                document.getElementById('sidebar');


                            if (!sidebar) {
                                return;
                            }


                            const links =
                                sidebar.querySelectorAll(
                                    'a'
                                );


                            links.forEach(
                                function(link) {

                                    link.addEventListener(
                                        'click',
                                        function() {

                                            if (
                                                window.innerWidth <= 768
                                            ) {

                                                closeSidebar();
                                            }

                                        }
                                    );

                                }
                            );

                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | إعادة ضبط القائمة عند الانتقال من الهاتف للكمبيوتر
                    |--------------------------------------------------------------------------
                    */

                    window.addEventListener(
                        'resize',
                        function() {

                            if (
                                window.innerWidth > 768
                            ) {

                                closeSidebar();
                            }

                        }
                    );
                </script>