<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {

    $customerId = (int)(
        $_GET['customer_id'] ?? 0
    );


    if ($customerId <= 0) {

        throw new Exception(
            'رقم العميل غير صحيح.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | إجمالي المبيعات الآجلة
    |--------------------------------------------------------------------------
    */

    $salesStmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(total),
                0
            )

        FROM sales

        WHERE customer_id = ?

        AND payment_method = 'credit'
    ");

    $salesStmt->execute([
        $customerId
    ]);

    $totalDebt =
        (float)$salesStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | إجمالي المدفوعات
    |--------------------------------------------------------------------------
    */

    $paymentsStmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(amount),
                0
            )

        FROM customer_payments

        WHERE customer_id = ?
    ");

    $paymentsStmt->execute([
        $customerId
    ]);

    $totalPaid =
        (float)$paymentsStmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | الرصيد
    |--------------------------------------------------------------------------
    */

    $balance =
        $totalDebt -
        $totalPaid;


    if ($balance < 0) {

        $balance = 0;

    }


    echo json_encode([
        'success' => true,
        'total_debt' => $totalDebt,
        'total_paid' => $totalPaid,
        'balance' => $balance
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}