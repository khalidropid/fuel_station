<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

try {

    $paymentId = (int)(
        $_GET['id'] ?? 0
    );


    if ($paymentId <= 0) {

        throw new Exception(
            'رقم سند التحصيل غير صحيح.'
        );

    }


    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | جلب السند
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
            payment_no,
            customer_id,
            amount,
            payment_date

        FROM customer_payments

        WHERE id = ?

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        $paymentId
    ]);

    $payment =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$payment) {

        throw new Exception(
            'سند التحصيل غير موجود.'
        );

    }

    // Lock the customer's ledger account before removing the receipt so the
    // legacy balance can be rebuilt from the remaining double-entry ledger.
    $customerAccount = accountingEnsureCustomerAccount(
        $pdo,
        (int)$payment['customer_id'],
        true
    );

    $deleteLedger = $pdo->prepare(
        "DELETE FROM account_transactions
         WHERE reference_type = 'customer_payment' AND reference_id = ?"
    );
    $deleteLedger->execute([$paymentId]);

    $deleteCustomerTransaction = $pdo->prepare(
        "DELETE FROM customer_transactions
         WHERE transaction_type = 'payment' AND reference_id = ? AND customer_id = ?"
    );
    $deleteCustomerTransaction->execute([
        $paymentId,
        (int)$payment['customer_id'],
    ]);


    /*
    |--------------------------------------------------------------------------
    | حذف السند
    |--------------------------------------------------------------------------
    */

    $delete = $pdo->prepare("
        DELETE FROM customer_payments

        WHERE id = ?
    ");

    $delete->execute([
        $paymentId
    ]);


    if ($delete->rowCount() !== 1) {

        throw new Exception(
            'لم يتم حذف سند التحصيل.'
        );

    }

    accountingRecalculateCustomerLegacyBalance(
        $pdo,
        (int)$payment['customer_id']
    );


    $pdo->commit();


    header(
        'Location: index.php'
    );

    exit;

} catch (Exception $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    die(
        'خطأ: '
        .
        htmlspecialchars(
            $e->getMessage()
        )
    );
}
