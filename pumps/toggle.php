<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

// 1. جلب الحالة الحالية للطرمبة
$stmt = $pdo->prepare("
    SELECT status 
    FROM pumps 
    WHERE id = :id 
    LIMIT 1
");

$stmt->execute([':id' => $id]);
$pump = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pump) {
    header('Location: index.php');
    exit;
}

// 2. تحديد الحالة الجديدة
// إذا كانت نشطة نجعلها غير نشطة، وإذا كانت غير نشطة أو في صيانة نجعلها نشطة
$newStatus = ($pump['status'] === 'active') ? 'inactive' : 'active';

// 3. تحديث الحالة في قاعدة البيانات
$updateStmt = $pdo->prepare("
    UPDATE pumps
    SET status = :status
    WHERE id = :id
");

$updateStmt->execute([
    ':status' => $newStatus,
    ':id' => $id
]);

// 4. إعادة التوجيه لصفحة العرض مع إشعار النجاح
header('Location: index.php?success=status');
exit;