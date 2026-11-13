<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

// 1. جلب الحالة الحالية للخزان
$stmt = $pdo->prepare("
    SELECT status 
    FROM tanks 
    WHERE id = :id 
    LIMIT 1
");

$stmt->execute([':id' => $id]);
$tank = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tank) {
    header('Location: index.php');
    exit;
}

// 2. تحديد الحالة الجديدة
// إذا كان نشط نجعله غير نشط، وإذا كان غير نشط أو في وضع الصيانة نجعله نشطاً
$newStatus = ($tank['status'] === 'active') ? 'inactive' : 'active';

// 3. تحديث الحالة في قاعدة البيانات
$updateStmt = $pdo->prepare("
    UPDATE tanks
    SET status = :status
    WHERE id = :id
");

$updateStmt->execute([
    ':status' => $newStatus,
    ':id' => $id
]);

// 4. إعادة التوجيه مع رسالة النجاح (success=status كما هو موجود في ملف index)
header('Location: index.php?success=status');
exit;