<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id) {

    header('Location: index.php');
    exit;

}


/*
 * جلب الحالة الحالية
 */

$stmt = $pdo->prepare("
    SELECT status
    FROM employees
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $id
]);

$employee = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$employee) {

    header('Location: index.php');
    exit;

}


/*
 * لا نقوم بتغيير الموظف الموجود في إجازة
 * إلى غير نشط بشكل تلقائي.
 *
 * الحالات:
 *
 * active -> inactive
 * inactive -> active
 */

if ($employee['status'] === 'active') {

    $newStatus = 'inactive';

} else {

    $newStatus = 'active';

}


$stmt = $pdo->prepare("
    UPDATE employees
    SET status = :status
    WHERE id = :id
");

$stmt->execute([

    ':status' => $newStatus,

    ':id' => $id

]);


header(
    'Location: index.php?success=status'
);

exit;
