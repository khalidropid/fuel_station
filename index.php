<?php

// بدء الجلسة للتحقق من حالة المستخدم
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق مما إذا كان المستخدم مسجلاً للدخول بالفعل
if (isset($_SESSION['user_id'])) {
    
    // إذا كان مسجلاً للدخول، يتم توجيهه مباشرة إلى لوحة التحكم
    header('Location: dashboard/index.php');
    exit;
    
} else {
    
    // إذا لم يكن مسجلاً للدخول، يتم توجيهه إلى صفحة تسجيل الدخول
    header('Location: auth/login.php');
    exit;
    
}