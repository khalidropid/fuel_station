<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/accounting.php';

$pageTitle = 'تعديل عملية بيع';

$error = '';
$sale = null;
$customers = [];
$shifts = [];
$pumps = [];
$fuelTypes = [];

$saleId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($saleId <= 0) {
    die('رقم عملية البيع غير صحيح.');
}

/*
|--------------------------------------------------------------------------
| دوال مساعدة
|--------------------------------------------------------------------------
*/

function moneyValue($value): float
{
    return round((float)$value, 2);
}

function getSale(PDO $pdo, int $saleId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.invoice_no,
            s.shift_id,
            s.pump_id,
            s.employee_id,
            s.customer_id,
            s.sale_date,
            s.payment_method,
            s.subtotal,
            s.discount,
            s.total,
            s.notes,

            si.id AS item_id,
            si.fuel_type_id,
            si.quantity,
            si.unit_price,
            si.total AS item_total,

            p.pump_no,
            p.name AS pump_name,
            p.tank_id,

            t.name AS tank_name,
            t.current_volume,
            t.fuel_type_id AS tank_fuel_type_id,

            ft.name AS fuel_name

        FROM sales s

        INNER JOIN sale_items si
            ON si.sale_id = s.id

        INNER JOIN pumps p
            ON p.id = s.pump_id

        INNER JOIN tanks t
            ON t.id = p.tank_id

        INNER JOIN fuel_types ft
            ON ft.id = si.fuel_type_id

        WHERE s.id = ?

        ORDER BY si.id ASC

        LIMIT 1
    ");

    $stmt->execute([$saleId]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ?: null;
}

/*
|--------------------------------------------------------------------------
| معالجة الطلب
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | جلب بيانات الفاتورة
    |--------------------------------------------------------------------------
    */

    $sale = getSale($pdo, $saleId);

    if (!$sale) {
        throw new Exception('عملية البيع غير موجودة.');
    }


    /*
    |--------------------------------------------------------------------------
    | معالجة POST
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $pumpId = (int)($_POST['pump_id'] ?? 0);
        $fuelTypeId = (int)($_POST['fuel_type_id'] ?? 0);
        $customerId = (int)($_POST['customer_id'] ?? 0);

        $quantity = moneyValue($_POST['quantity'] ?? 0);
        $unitPrice = moneyValue($_POST['unit_price'] ?? 0);
        $discount = moneyValue($_POST['discount'] ?? 0);

        $paymentMethod = trim(
            $_POST['payment_method'] ?? 'cash'
        );

        $saleDate = trim(
            $_POST['sale_date'] ?? ''
        );

        $notes = trim(
            $_POST['notes'] ?? ''
        );


        /*
        |--------------------------------------------------------------------------
        | التحقق الأساسي
        |--------------------------------------------------------------------------
        */

        if ($shiftId <= 0) {
            throw new Exception('يرجى اختيار الوردية.');
        }

        if ($pumpId <= 0) {
            throw new Exception('يرجى اختيار الطرمبة.');
        }

        if ($fuelTypeId <= 0) {
            throw new Exception('يرجى اختيار نوع الوقود.');
        }

        if ($quantity <= 0) {
            throw new Exception('يجب أن تكون الكمية أكبر من صفر.');
        }

        if ($unitPrice <= 0) {
            throw new Exception('سعر اللتر يجب أن يكون أكبر من صفر.');
        }

        if ($discount < 0) {
            throw new Exception('الخصم لا يمكن أن يكون سالبًا.');
        }

        if (!in_array(
            $paymentMethod,
            ['cash', 'card', 'credit'],
            true
        )) {
            throw new Exception('طريقة الدفع غير صحيحة.');
        }

        if ($saleDate === '') {
            throw new Exception('يرجى تحديد تاريخ البيع.');
        }


        /*
        |--------------------------------------------------------------------------
        | العميل في حالة البيع الآجل
        |--------------------------------------------------------------------------
        */

        if (
            $paymentMethod === 'credit'
            &&
            $customerId <= 0
        ) {
            throw new Exception(
                'يجب اختيار العميل عند البيع الآجل.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | الحسابات
        |--------------------------------------------------------------------------
        */

        $subtotal = moneyValue(
            $quantity * $unitPrice
        );

        $total = moneyValue(
            $subtotal - $discount
        );

        if ($total < 0) {
            throw new Exception(
                'الخصم لا يمكن أن يكون أكبر من قيمة البيع.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | التاريخ
        |--------------------------------------------------------------------------
        */

        $saleDateDb = str_replace(
            'T',
            ' ',
            $saleDate
        );

        /*
        |--------------------------------------------------------------------------
        | بدء Transaction
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | قفل الفاتورة
        |--------------------------------------------------------------------------
        */

        $lockSaleStmt = $pdo->prepare("
            SELECT
                id,
                invoice_no,
                shift_id,
                pump_id,
                employee_id,
                customer_id,
                payment_method,
                total,
                sale_date

            FROM sales

            WHERE id = ?

            LIMIT 1

            FOR UPDATE
        ");

        $lockSaleStmt->execute([
            $saleId
        ]);

        $oldSale = $lockSaleStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$oldSale) {
            throw new Exception(
                'تعذر العثور على الفاتورة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | قفل تفاصيل الفاتورة
        |--------------------------------------------------------------------------
        */

        $oldItemStmt = $pdo->prepare("
            SELECT
                id,
                sale_id,
                fuel_type_id,
                quantity,
                unit_price,
                total

            FROM sale_items

            WHERE sale_id = ?

            ORDER BY id ASC

            LIMIT 1

            FOR UPDATE
        ");

        $oldItemStmt->execute([
            $saleId
        ]);

        $oldItem = $oldItemStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$oldItem) {
            throw new Exception(
                'لا توجد تفاصيل مرتبطة بهذه الفاتورة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | القيم القديمة
        |--------------------------------------------------------------------------
        */

        $oldQuantity = moneyValue(
            $oldItem['quantity']
        );

        $oldTotal = moneyValue(
            $oldSale['total']
        );

        $oldPumpId = (int)$oldSale['pump_id'];
        $oldShiftId = (int)$oldSale['shift_id'];
        $oldFuelTypeId = (int)$oldItem['fuel_type_id'];

        $oldCustomerId = (int)(
            $oldSale['customer_id'] ?? 0
        );

        $oldPaymentMethod =
            $oldSale['payment_method'];


        /*
        |--------------------------------------------------------------------------
        | قفل الوردية القديمة والجديدة
        |--------------------------------------------------------------------------
        */

        $shiftIdsToLock = array_values(
            array_unique([
                $oldShiftId,
                $shiftId
            ])
        );

        sort($shiftIdsToLock);

        $lockedShifts = [];

        foreach ($shiftIdsToLock as $lockShiftId) {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    shift_no,
                    employee_id,
                    status,
                    opening_cash,
                    closing_cash,
                    total_sales,
                    total_withdrawals,
                    expected_cash,
                    difference

                FROM shifts

                WHERE id = ?

                LIMIT 1

                FOR UPDATE
            ");

            $stmt->execute([
                $lockShiftId
            ]);

            $lockedShift = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$lockedShift) {
                throw new Exception(
                    'الوردية رقم ' .
                    $lockShiftId .
                    ' غير موجودة.'
                );
            }

            $lockedShifts[$lockShiftId] =
                $lockedShift;
        }


        /*
        |--------------------------------------------------------------------------
        | لا نسمح بنقل الفاتورة إلى وردية مغلقة
        |--------------------------------------------------------------------------
        */

        if (
            $lockedShifts[$shiftId]['status']
            !== 'open'
        ) {
            throw new Exception(
                'لا يمكن تعديل البيع إلى وردية مغلقة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | حماية الوردية القديمة
        |--------------------------------------------------------------------------
        |
        | إذا كانت الفاتورة في وردية مغلقة، لا نسمح بتغييرها.
        | هذا يمنع العبث بنتائج وردية تم إغلاقها.
        |
        */

        if (
            $lockedShifts[$oldShiftId]['status']
            !== 'open'
        ) {
            throw new Exception(
                'لا يمكن تعديل فاتورة تابعة لوردية مغلقة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | الموظف الجديد
        |--------------------------------------------------------------------------
        */

        $newEmployeeId = (int)
            $lockedShifts[$shiftId]['employee_id'];


        /*
        |--------------------------------------------------------------------------
        | قفل الطرمبات القديمة والجديدة
        |--------------------------------------------------------------------------
        */

        $pumpIdsToLock = array_values(
            array_unique([
                $oldPumpId,
                $pumpId
            ])
        );

        sort($pumpIdsToLock);

        $lockedPumps = [];

        foreach ($pumpIdsToLock as $lockPumpId) {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    pump_no,
                    name,
                    tank_id,
                    status

                FROM pumps

                WHERE id = ?

                LIMIT 1

                FOR UPDATE
            ");

            $stmt->execute([
                $lockPumpId
            ]);

            $lockedPump = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$lockedPump) {
                throw new Exception(
                    'الطرمبة رقم ' .
                    $lockPumpId .
                    ' غير موجودة.'
                );
            }

            $lockedPumps[$lockPumpId] =
                $lockedPump;
        }


        /*
        |--------------------------------------------------------------------------
        | الطرمبة الجديدة يجب أن تكون نشطة
        |--------------------------------------------------------------------------
        */

        if (
            $lockedPumps[$pumpId]['status']
            !== 'active'
        ) {
            throw new Exception(
                'لا يمكن استخدام طرمبة غير نشطة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | قفل الخزانات القديمة والجديدة
        |--------------------------------------------------------------------------
        */

        $oldTankId = (int)
            $lockedPumps[$oldPumpId]['tank_id'];

        $newTankId = (int)
            $lockedPumps[$pumpId]['tank_id'];

        $tankIdsToLock = array_values(
            array_unique([
                $oldTankId,
                $newTankId
            ])
        );

        sort($tankIdsToLock);

        $lockedTanks = [];

        foreach ($tankIdsToLock as $lockTankId) {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    tank_no,
                    name,
                    fuel_type_id,
                    capacity,
                    current_volume,
                    minimum_level,
                    status

                FROM tanks

                WHERE id = ?

                LIMIT 1

                FOR UPDATE
            ");

            $stmt->execute([
                $lockTankId
            ]);

            $lockedTank = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$lockedTank) {
                throw new Exception(
                    'الخزان رقم ' .
                    $lockTankId .
                    ' غير موجود.'
                );
            }

            $lockedTanks[$lockTankId] =
                $lockedTank;
        }


        /*
        |--------------------------------------------------------------------------
        | التحقق من نوع الوقود
        |--------------------------------------------------------------------------
        */

        $newTankFuelTypeId = (int)
            $lockedTanks[$newTankId]['fuel_type_id'];

        if (
            $newTankFuelTypeId
            !== $fuelTypeId
        ) {
            throw new Exception(
                'نوع الوقود لا يتطابق مع وقود خزان الطرمبة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | التحقق من نوع الوقود القديم
        |--------------------------------------------------------------------------
        |
        | للتأكد من سلامة البيانات القديمة.
        |
        */

        $oldTankFuelTypeId = (int)
            $lockedTanks[$oldTankId]['fuel_type_id'];

        if (
            $oldTankFuelTypeId
            !== $oldFuelTypeId
        ) {
            throw new Exception(
                'بيانات الفاتورة القديمة غير متطابقة مع نوع وقود الخزان.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | قفل العملاء المتأثرين
        |--------------------------------------------------------------------------
        */

        $customerIdsToLock = [];

        if ($oldPaymentMethod === 'credit') {
            if ($oldCustomerId <= 0) {
                throw new Exception(
                    'الفاتورة القديمة آجل ولكن لا يوجد لها عميل.'
                );
            }

            $customerIdsToLock[] =
                $oldCustomerId;
        }

        if ($paymentMethod === 'credit') {
            $customerIdsToLock[] =
                $customerId;
        }

        $customerIdsToLock = array_values(
            array_unique($customerIdsToLock)
        );

        sort($customerIdsToLock);

        $lockedCustomers = [];

        foreach ($customerIdsToLock as $lockCustomerId) {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    customer_no,
                    full_name,
                    credit_limit,
                    balance,
                    status

                FROM customers

                WHERE id = ?

                LIMIT 1

                FOR UPDATE
            ");

            $stmt->execute([
                $lockCustomerId
            ]);

            $lockedCustomer = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$lockedCustomer) {
                throw new Exception(
                    'العميل رقم ' .
                    $lockCustomerId .
                    ' غير موجود.'
                );
            }

            $lockedCustomers[$lockCustomerId] =
                $lockedCustomer;
        }


        /*
        |--------------------------------------------------------------------------
        | التحقق من العميل الجديد
        |--------------------------------------------------------------------------
        */

        if ($paymentMethod === 'credit') {

            $newCustomer =
                $lockedCustomers[$customerId];

            if (
                $newCustomer['status']
                !== 'active'
            ) {
                throw new Exception(
                    'لا يمكن إجراء بيع آجل لهذا العميل لأنه غير نشط.'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | حساب رصيد العميل قبل إضافة الفاتورة الجديدة
        |--------------------------------------------------------------------------
        |
        | نزيل فقط تأثير الفاتورة القديمة إذا كانت آجلة.
        |
        */

        $customerBalancesAfterOldSale = [];

        foreach ($lockedCustomers as $id => $customer) {

            $balance = moneyValue(
                $customer['balance']
            );

            if (
                $oldPaymentMethod === 'credit'
                &&
                $oldCustomerId === (int)$id
            ) {
                $balance = moneyValue(
                    $balance - $oldTotal
                );

                if ($balance < 0) {
                    throw new Exception(
                        'رصيد العميل القديم لا يسمح بعكس الفاتورة الآجلة. '
                        .
                        'الرصيد الحالي: ' .
                        number_format(
                            (float)$customer['balance'],
                            2
                        ) .
                        ' وقيمة الفاتورة: ' .
                        number_format(
                            $oldTotal,
                            2
                        )
                    );
                }
            }

            $customerBalancesAfterOldSale[$id] =
                $balance;
        }


        /*
        |--------------------------------------------------------------------------
        | التحقق من الحد الائتماني
        |--------------------------------------------------------------------------
        */

        if ($paymentMethod === 'credit') {

            $baseBalance =
                $customerBalancesAfterOldSale[
                    $customerId
                ];

            $creditLimit = moneyValue(
                $newCustomer['credit_limit']
            );

            if (
                $creditLimit > 0
                &&
                (
                    $baseBalance + $total
                ) > $creditLimit
            ) {
                throw new Exception(
                    'البيع يتجاوز الحد الائتماني للعميل. '
                    .
                    'الحد الائتماني: ' .
                    number_format(
                        $creditLimit,
                        2
                    )
                    .
                    ' - الرصيد بعد التعديل: ' .
                    number_format(
                        $baseBalance + $total,
                        2
                    )
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | التحقق من shift_pumps
        |--------------------------------------------------------------------------
        |
        | الطرمبة يجب أن تكون مرتبطة بالوردية الجديدة.
        |
        */

        $shiftPumpIdsToLock = [];

        $pairs = [
            [$oldShiftId, $oldPumpId],
            [$shiftId, $pumpId]
        ];

        foreach ($pairs as $pair) {

            $pairKey =
                $pair[0] . ':' . $pair[1];

            $shiftPumpIdsToLock[$pairKey] =
                [
                    'shift_id' => $pair[0],
                    'pump_id' => $pair[1]
                ];
        }

        /*
        | ترتيب ثابت للقفل
        */

        uasort(
            $shiftPumpIdsToLock,
            function ($a, $b) {

                if (
                    $a['shift_id']
                    ===
                    $b['shift_id']
                ) {
                    return
                        $a['pump_id']
                        <=>
                        $b['pump_id'];
                }

                return
                    $a['shift_id']
                    <=>
                    $b['shift_id'];
            }
        );

        $lockedShiftPumps = [];

        foreach ($shiftPumpIdsToLock as $pair) {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    shift_id,
                    pump_id,
                    opening_reading,
                    closing_reading,
                    total_liters,
                    total_sales

                FROM shift_pumps

                WHERE shift_id = ?
                AND pump_id = ?

                LIMIT 1

                FOR UPDATE
            ");

            $stmt->execute([
                $pair['shift_id'],
                $pair['pump_id']
            ]);

            $row = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if ($row) {

                $key =
                    $pair['shift_id']
                    . ':'
                    .
                    $pair['pump_id'];

                $lockedShiftPumps[$key] =
                    $row;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | يجب أن تكون الطرمبة الجديدة مرتبطة بالوردية
        |--------------------------------------------------------------------------
        */

        $newShiftPumpKey =
            $shiftId . ':' . $pumpId;

        if (
            !isset(
                $lockedShiftPumps[
                    $newShiftPumpKey
                ]
            )
        ) {
            throw new Exception(
                'الطرمبة المحددة غير مرتبطة بالوردية الجديدة.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | تصحيح مخزون الخزان
        |--------------------------------------------------------------------------
        */

        if ($oldTankId === $newTankId) {

            /*
            | نفس الخزان:
            |
            | الحجم الحالي
            | + الكمية القديمة
            | - الكمية الجديدة
            */

            $currentVolume =
                moneyValue(
                    $lockedTanks[
                        $oldTankId
                    ]['current_volume']
                );

            $volumeAfterReturn =
                moneyValue(
                    $currentVolume
                    +
                    $oldQuantity
                );

            if ($quantity > $volumeAfterReturn) {

                throw new Exception(
                    'الكمية الجديدة أكبر من المخزون المتاح في الخزان بعد إعادة كمية الفاتورة القديمة. '
                    .
                    'المتاح: '
                    .
                    number_format(
                        $volumeAfterReturn,
                        2
                    )
                    .
                    ' لتر.'
                );
            }

            $newVolume =
                moneyValue(
                    $volumeAfterReturn
                    -
                    $quantity
                );

            $updateTank = $pdo->prepare("
                UPDATE tanks

                SET current_volume = ?

                WHERE id = ?
            ");

            $updateTank->execute([
                $newVolume,
                $oldTankId
            ]);


            /*
            | تسجيل حركة التصحيح فقط إذا تغيرت الكمية
            */

            $movementQuantity =
                moneyValue(
                    $oldQuantity
                    -
                    $quantity
                );

            if (
                abs($movementQuantity)
                > 0.000001
            ) {

                $insertMovement = $pdo->prepare("
                    INSERT INTO tank_movements
                    (
                        tank_id,
                        movement_type,
                        quantity,
                        reference_id,
                        movement_date,
                        notes,
                        created_by
                    )

                    VALUES
                    (
                        ?,
                        'adjustment',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $insertMovement->execute([
                    $oldTankId,
                    $movementQuantity,
                    $saleId,
                    $saleDateDb,
                    'تصحيح كمية الفاتورة ' .
                    $oldSale['invoice_no'],
                    $_SESSION['user_id'] ?? null
                ]);
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | خزّان مختلف
            |--------------------------------------------------------------------------
            */

            /*
            | إعادة الكمية القديمة إلى الخزان القديم
            */

            $oldCurrentVolume =
                moneyValue(
                    $lockedTanks[
                        $oldTankId
                    ]['current_volume']
                );

            $restoredOldVolume =
                moneyValue(
                    $oldCurrentVolume
                    +
                    $oldQuantity
                );

            $oldCapacity =
                moneyValue(
                    $lockedTanks[
                        $oldTankId
                    ]['capacity']
                );

            if (
                $oldCapacity > 0
                &&
                $restoredOldVolume > $oldCapacity
            ) {
                throw new Exception(
                    'إعادة الكمية القديمة ستجعل مخزون الخزان القديم يتجاوز سعته.'
                );
            }


            /*
            | التحقق من مخزون الخزان الجديد
            */

            $newCurrentVolume =
                moneyValue(
                    $lockedTanks[
                        $newTankId
                    ]['current_volume']
                );

            if ($quantity > $newCurrentVolume) {

                throw new Exception(
                    'الكمية المطلوبة أكبر من مخزون الخزان الجديد. '
                    .
                    'المتاح: '
                    .
                    number_format(
                        $newCurrentVolume,
                        2
                    )
                    .
                    ' لتر.'
                );
            }

            $newRemainingVolume =
                moneyValue(
                    $newCurrentVolume
                    -
                    $quantity
                );


            /*
            | تحديث الخزان القديم
            */

            $updateOldTank = $pdo->prepare("
                UPDATE tanks

                SET current_volume = ?

                WHERE id = ?
            ");

            $updateOldTank->execute([
                $restoredOldVolume,
                $oldTankId
            ]);


            /*
            | تحديث الخزان الجديد
            */

            $updateNewTank = $pdo->prepare("
                UPDATE tanks

                SET current_volume = ?

                WHERE id = ?
            ");

            $updateNewTank->execute([
                $newRemainingVolume,
                $newTankId
            ]);


            /*
            | حركة إعادة الكمية للخزان القديم
            */

            $insertOldMovement = $pdo->prepare("
                INSERT INTO tank_movements
                (
                    tank_id,
                    movement_type,
                    quantity,
                    reference_id,
                    movement_date,
                    notes,
                    created_by
                )

                VALUES
                (
                    ?,
                    'adjustment',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $insertOldMovement->execute([
                $oldTankId,
                $oldQuantity,
                $saleId,
                $saleDateDb,
                'إعادة كمية بسبب تعديل الفاتورة ' .
                $oldSale['invoice_no'],
                $_SESSION['user_id'] ?? null
            ]);


            /*
            | حركة خصم من الخزان الجديد
            */

            $insertNewMovement = $pdo->prepare("
                INSERT INTO tank_movements
                (
                    tank_id,
                    movement_type,
                    quantity,
                    reference_id,
                    movement_date,
                    notes,
                    created_by
                )

                VALUES
                (
                    ?,
                    'adjustment',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $insertNewMovement->execute([
                $newTankId,
                -$quantity,
                $saleId,
                $saleDateDb,
                'خصم كمية بسبب نقل الفاتورة ' .
                $oldSale['invoice_no'],
                $_SESSION['user_id'] ?? null
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | تحديث أرصدة العملاء
        |--------------------------------------------------------------------------
        */

        /*
        | أولًا: تطبيق الرصيد الناتج بعد عكس الفاتورة القديمة
        */

        foreach (
            $customerBalancesAfterOldSale
            as $affectedCustomerId =>
            $balanceAfterOldSale
        ) {

            /*
            | إذا كان هذا العميل هو العميل الجديد
            | والفاتورة الجديدة آجل، نضيف القيمة الجديدة.
            |
            | إذا لم يعد العميل مرتبطًا بالفاتورة،
            | يبقى الرصيد بعد عكس الفاتورة القديمة.
            */

            if (
                $paymentMethod === 'credit'
                &&
                $affectedCustomerId
                === $customerId
            ) {

                $finalBalance =
                    moneyValue(
                        $balanceAfterOldSale
                        +
                        $total
                    );

            } else {

                $finalBalance =
                    moneyValue(
                        $balanceAfterOldSale
                    );
            }


            $updateCustomer = $pdo->prepare("
                UPDATE customers

                SET balance = ?

                WHERE id = ?
            ");

            $updateCustomer->execute([
                $finalBalance,
                $affectedCustomerId
            ]);
        }


        /*
        | حالة العميل الجديد آجل ولكنه لم يكن ضمن
        | العملاء المتأثرين سابقًا.
        */

        if (
            $paymentMethod === 'credit'
            &&
            !isset(
                $customerBalancesAfterOldSale[
                    $customerId
                ]
            )
        ) {

            $baseBalance =
                moneyValue(
                    $newCustomer['balance']
                );

            $finalBalance =
                moneyValue(
                    $baseBalance
                    +
                    $total
                );

            $updateNewCustomer = $pdo->prepare("
                UPDATE customers

                SET balance = ?

                WHERE id = ?
            ");

            $updateNewCustomer->execute([
                $finalBalance,
                $customerId
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | تحديث customer_transactions
        |--------------------------------------------------------------------------
        |
        | transaction_type = sale
        | reference_id = sales.id
        |
        */

        $oldTransactionStmt = $pdo->prepare("
            SELECT
                id,
                customer_id,
                amount,
                transaction_date

            FROM customer_transactions

            WHERE transaction_type = 'sale'
            AND reference_id = ?

            ORDER BY id ASC

            LIMIT 1

            FOR UPDATE
        ");

        $oldTransactionStmt->execute([
            $saleId
        ]);

        $saleTransaction =
            $oldTransactionStmt->fetch(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | فاتورة آجل
        |--------------------------------------------------------------------------
        */

        if ($paymentMethod === 'credit') {

            if ($saleTransaction) {

                /*
                | تحديث الحركة الموجودة
                */

                $updateTransaction = $pdo->prepare("
                    UPDATE customer_transactions

                    SET
                        customer_id = ?,
                        amount = ?,
                        transaction_date = ?,
                        description = ?,
                        created_by = ?

                    WHERE id = ?
                ");

                $updateTransaction->execute([
                    $customerId,
                    $total,
                    $saleDateDb,
                    'بيع آجل - الفاتورة ' .
                    $oldSale['invoice_no'],
                    $_SESSION['user_id'] ?? null,
                    $saleTransaction['id']
                ]);

            } else {

                /*
                | إذا لم تكن هناك حركة قديمة،
                | ننشئ حركة البيع الآجل.
                */

                $insertTransaction = $pdo->prepare("
                    INSERT INTO customer_transactions
                    (
                        customer_id,
                        transaction_type,
                        reference_id,
                        amount,
                        transaction_date,
                        description,
                        created_by
                    )

                    VALUES
                    (
                        ?,
                        'sale',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $insertTransaction->execute([
                    $customerId,
                    $saleId,
                    $total,
                    $saleDateDb,
                    'بيع آجل - الفاتورة ' .
                    $oldSale['invoice_no'],
                    $_SESSION['user_id'] ?? null
                ]);
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | لم تعد الفاتورة آجلة
            |--------------------------------------------------------------------------
            |
            | الحركة المرتبطة بالفاتورة لم تعد تمثل مديونية.
            | نحذف فقط سجل sale المرتبط بهذه الفاتورة.
            |
            */

            if ($saleTransaction) {

                $deleteTransaction = $pdo->prepare("
                    DELETE FROM customer_transactions

                    WHERE id = ?
                ");

                $deleteTransaction->execute([
                    $saleTransaction['id']
                ]);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | تحديث sales
        |--------------------------------------------------------------------------
        */

        $updateSale = $pdo->prepare("
            UPDATE sales

            SET
                shift_id = ?,
                pump_id = ?,
                employee_id = ?,
                customer_id = ?,
                sale_date = ?,
                payment_method = ?,
                subtotal = ?,
                discount = ?,
                total = ?,
                notes = ?

            WHERE id = ?
        ");

        $updateSale->execute([
            $shiftId,
            $pumpId,
            $newEmployeeId,
            $paymentMethod === 'credit'
                ? $customerId
                : (
                    $customerId > 0
                        ? $customerId
                        : null
                ),
            $saleDateDb,
            $paymentMethod,
            $subtotal,
            $discount,
            $total,
            $notes !== ''
                ? $notes
                : null,
            $saleId
        ]);


        /*
        |--------------------------------------------------------------------------
        | تحديث sale_items
        |--------------------------------------------------------------------------
        |
        | total هنا = كمية × سعر اللتر
        | والخصم محفوظ في sales.discount.
        |
        */

        $itemTotal = moneyValue(
            $quantity * $unitPrice
        );

        $updateItem = $pdo->prepare("
            UPDATE sale_items

            SET
                fuel_type_id = ?,
                quantity = ?,
                unit_price = ?,
                total = ?

            WHERE id = ?
        ");

        $updateItem->execute([
            $fuelTypeId,
            $quantity,
            $unitPrice,
            $itemTotal,
            (int)$oldItem['id']
        ]);

        // Replace the original sale journal atomically with the edited values.
        $deleteSaleJournal = $pdo->prepare(
            "DELETE FROM account_transactions
             WHERE reference_type = 'sale' AND reference_id = ?"
        );
        $deleteSaleJournal->execute([$saleId]);

        $debitAccount = match ($paymentMethod) {
            'cash' => accountingEnsureCashAccount($pdo),
            'card' => accountingEnsureCardAccount($pdo),
            'credit' => accountingEnsureCustomerAccount($pdo, $customerId, true),
            default => throw new RuntimeException('طريقة الدفع غير صحيحة.'),
        };
        $ownerAccount = accountingEnsureOwnerAccount($pdo);
        accountingPostBalancedTransaction(
            $pdo,
            'sale',
            'sale',
            $saleId,
            [
                [
                    'account_id' => (int)$debitAccount['id'],
                    'debit' => $total,
                    'credit' => 0,
                    'description' => 'بيع وقود - فاتورة ' . $oldSale['invoice_no'],
                ],
                [
                    'account_id' => (int)$ownerAccount['id'],
                    'debit' => 0,
                    'credit' => $total,
                    'description' => 'إيراد بيع وقود - فاتورة ' . $oldSale['invoice_no'],
                ],
            ],
            $saleDateDb,
            'بيع وقود - فاتورة ' . $oldSale['invoice_no'],
            accountingUserId(),
            $oldSale['invoice_no']
        );


        /*
        |--------------------------------------------------------------------------
        | إعادة حساب shift_pumps
        |--------------------------------------------------------------------------
        */

        $affectedShiftPumpPairs = [];

        $affectedShiftPumpPairs[] = [
            'shift_id' => $oldShiftId,
            'pump_id' => $oldPumpId
        ];

        $affectedShiftPumpPairs[] = [
            'shift_id' => $shiftId,
            'pump_id' => $pumpId
        ];


        /*
        | إزالة التكرار
        */

        $uniquePairs = [];

        foreach ($affectedShiftPumpPairs as $pair) {

            $key =
                $pair['shift_id']
                .
                ':'
                .
                $pair['pump_id'];

            $uniquePairs[$key] = $pair;
        }


        foreach ($uniquePairs as $pair) {

            $totalStmt = $pdo->prepare("
                SELECT
                    COALESCE(
                        SUM(si.quantity),
                        0
                    ) AS total_liters,

                    COALESCE(
                        SUM(s.total),
                        0
                    ) AS total_sales

                FROM sales s

                INNER JOIN sale_items si
                    ON si.sale_id = s.id

                WHERE s.shift_id = ?
                AND s.pump_id = ?
            ");

            $totalStmt->execute([
                $pair['shift_id'],
                $pair['pump_id']
            ]);

            $pumpTotals =
                $totalStmt->fetch(
                    PDO::FETCH_ASSOC
                );

            $totalLiters =
                moneyValue(
                    $pumpTotals['total_liters']
                );

            $totalSales =
                moneyValue(
                    $pumpTotals['total_sales']
                );


            $updateShiftPump = $pdo->prepare("
                UPDATE shift_pumps

                SET
                    total_liters = ?,
                    total_sales = ?

                WHERE shift_id = ?
                AND pump_id = ?
            ");

            $updateShiftPump->execute([
                $totalLiters,
                $totalSales,
                $pair['shift_id'],
                $pair['pump_id']
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | إعادة حساب الوردية
        |--------------------------------------------------------------------------
        |
        | نستخدم employee_withdrawals بدل withdrawals.
        |
        */

        foreach ($shiftIdsToLock as $affectedShiftId) {

            $salesTotalStmt = $pdo->prepare("
                SELECT
                    COALESCE(
                        SUM(total),
                        0
                    )

                FROM sales

                WHERE shift_id = ?
            ");

            $salesTotalStmt->execute([
                $affectedShiftId
            ]);

            $salesTotal =
                moneyValue(
                    $salesTotalStmt->fetchColumn()
                );


            $withdrawalsStmt = $pdo->prepare("
                SELECT
                    COALESCE(
                        SUM(amount),
                        0
                    )

                FROM employee_withdrawals

                WHERE shift_id = ?
            ");

            $withdrawalsStmt->execute([
                $affectedShiftId
            ]);

            $withdrawals =
                moneyValue(
                    $withdrawalsStmt->fetchColumn()
                );


            $openingCash =
                moneyValue(
                    $lockedShifts[
                        $affectedShiftId
                    ]['opening_cash']
                );


            $expectedCash =
                moneyValue(
                    $openingCash
                    +
                    $salesTotal
                    -
                    $withdrawals
                );


            /*
            | لا نغير closing_cash هنا.
            | لأنه يمثل النقد الفعلي الذي تم تسجيله عند الإغلاق.
            */

            $updateShift = $pdo->prepare("
                UPDATE shifts

                SET
                    total_sales = ?,
                    total_withdrawals = ?,
                    expected_cash = ?

                WHERE id = ?
            ");

            $updateShift->execute([
                $salesTotal,
                $withdrawals,
                $expectedCash,
                $affectedShiftId
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Commit
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        /*
        |--------------------------------------------------------------------------
        | إعادة التوجيه
        |--------------------------------------------------------------------------
        */

        header(
            'Location: view.php?id=' .
            $saleId
        );

        exit;
    }


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = $e->getMessage();


    /*
    |--------------------------------------------------------------------------
    | إعادة جلب الفاتورة بعد الخطأ
    |--------------------------------------------------------------------------
    */

    try {

        $sale = getSale(
            $pdo,
            $saleId
        );

    } catch (Throwable $ignored) {
        // لا شيء
    }
}


/*
|--------------------------------------------------------------------------
| القوائم
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | العملاء
    |--------------------------------------------------------------------------
    */

    $currentCustomerId =
        (int)($sale['customer_id'] ?? 0);

    $customerStmt = $pdo->prepare("
        SELECT
            id,
            customer_no,
            full_name,
            phone,
            credit_limit,
            balance,
            status

        FROM customers

        WHERE status = 'active'
           OR id = ?

        ORDER BY full_name ASC
    ");

    $customerStmt->execute([
        $currentCustomerId
    ]);

    $customers =
        $customerStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | الورديات المفتوحة + الوردية الحالية
    |--------------------------------------------------------------------------
    */

    $shiftStmt = $pdo->prepare("
        SELECT
            s.id,
            s.shift_no,
            s.employee_id,
            s.status,

            e.employee_no,
            e.full_name AS employee_name

        FROM shifts s

        INNER JOIN employees e
            ON e.id = s.employee_id

        WHERE s.status = 'open'
           OR s.id = ?

        ORDER BY s.id DESC
    ");

    $shiftStmt->execute([
        (int)$sale['shift_id']
    ]);

    $shifts =
        $shiftStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | الطرمبات
    |--------------------------------------------------------------------------
    */

    $pumpStmt = $pdo->prepare("
        SELECT
            p.id,
            p.pump_no,
            p.name,
            p.tank_id,
            p.status,

            t.name AS tank_name,
            t.current_volume,
            t.fuel_type_id,

            ft.name AS fuel_name

        FROM pumps p

        INNER JOIN tanks t
            ON t.id = p.tank_id

        INNER JOIN fuel_types ft
            ON ft.id = t.fuel_type_id

        WHERE p.status = 'active'
           OR p.id = ?

        ORDER BY p.pump_no ASC
    ");

    $pumpStmt->execute([
        (int)$sale['pump_id']
    ]);

    $pumps =
        $pumpStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | أنواع الوقود
    |--------------------------------------------------------------------------
    */

    $fuelStmt = $pdo->prepare("
        SELECT
            id,
            code,
            name,
            unit,
            is_active

        FROM fuel_types

        WHERE is_active = 1
           OR id = ?

        ORDER BY name ASC
    ");

    $fuelStmt->execute([
        (int)$sale['fuel_type_id']
    ]);

    $fuelTypes =
        $fuelStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (Throwable $e) {

    if ($error === '') {
        $error = $e->getMessage();
    }
}


require_once __DIR__ . '/../includes/header.php';

?>


<div class="page-header">

    <div>

        <h2>
            تعديل عملية البيع
        </h2>

        <p>
            تعديل بيانات الفاتورة والكمية والعميل وطريقة الدفع.
        </p>

    </div>

    <div>

        <a
            href="view.php?id=<?= $saleId ?>"
            class="btn"
        >
            العودة للفاتورة
        </a>

    </div>

</div>


<?php if ($error): ?>

    <div class="alert alert-danger">

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<?php if ($sale): ?>

<div class="card">

    <form method="POST">

        <input
            type="hidden"
            name="id"
            value="<?= $saleId ?>"
        >


        <!-- رقم الفاتورة -->

        <div class="form-group">

            <label>
                رقم الفاتورة
            </label>

            <input
                type="text"
                value="<?= htmlspecialchars(
                    $sale['invoice_no']
                ) ?>"
                readonly
            >

        </div>


        <!-- الوردية -->

        <div class="form-group">

            <label>
                الوردية
            </label>

            <select
                name="shift_id"
                required
            >

                <option value="">
                    اختر الوردية
                </option>

                <?php foreach ($shifts as $shift): ?>

                    <option
                        value="<?= (int)$shift['id'] ?>"
                        <?= (
                            (
                                $_POST['shift_id']
                                ??
                                $sale['shift_id']
                            )
                            ==
                            $shift['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $shift['shift_no']
                            .
                            ' - '
                            .
                            $shift['employee_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- الطرمبة -->

        <div class="form-group">

            <label>
                الطرمبة
            </label>

            <select
                name="pump_id"
                required
            >

                <option value="">
                    اختر الطرمبة
                </option>

                <?php foreach ($pumps as $pump): ?>

                    <option
                        value="<?= (int)$pump['id'] ?>"
                        <?= (
                            (
                                $_POST['pump_id']
                                ??
                                $sale['pump_id']
                            )
                            ==
                            $pump['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            'طرمبة '
                            .
                            $pump['pump_no']
                            .
                            ' - '
                            .
                            $pump['name']
                            .
                            ' - '
                            .
                            $pump['fuel_name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- العميل -->

        <div class="form-group">

            <label>
                العميل
            </label>

            <select
                name="customer_id"
                id="customer_id"
            >

                <option value="0">
                    بدون عميل
                </option>

                <?php foreach ($customers as $customer): ?>

                    <option
                        value="<?= (int)$customer['id'] ?>"
                        data-balance="<?= htmlspecialchars(
                            $customer['balance']
                        ) ?>"
                        data-limit="<?= htmlspecialchars(
                            $customer['credit_limit']
                        ) ?>"
                        <?= (
                            (
                                $_POST['customer_id']
                                ??
                                $sale['customer_id']
                                ??
                                0
                            )
                            ==
                            $customer['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $customer['customer_no']
                            .
                            ' - '
                            .
                            $customer['full_name']
                            .
                            ' - الرصيد: '
                            .
                            number_format(
                                (float)$customer['balance'],
                                2
                            )
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <small id="customerInfo"></small>

        </div>


        <!-- نوع الوقود -->

        <div class="form-group">

            <label>
                نوع الوقود
            </label>

            <select
                name="fuel_type_id"
                id="fuel_type_id"
                required
            >

                <option value="">
                    اختر نوع الوقود
                </option>

                <?php foreach ($fuelTypes as $fuel): ?>

                    <option
                        value="<?= (int)$fuel['id'] ?>"
                        <?= (
                            (
                                $_POST['fuel_type_id']
                                ??
                                $sale['fuel_type_id']
                            )
                            ==
                            $fuel['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $fuel['name']
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- الكمية -->

        <div class="form-group">

            <label>
                الكمية / لتر
            </label>

            <input
                type="number"
                name="quantity"
                id="quantity"
                step="0.01"
                min="0.01"
                value="<?= htmlspecialchars(
                    $_POST['quantity']
                    ??
                    $sale['quantity']
                ) ?>"
                required
            >

        </div>


        <!-- سعر اللتر -->

        <div class="form-group">

            <label>
                سعر اللتر
            </label>

            <input
                type="number"
                name="unit_price"
                id="unit_price"
                step="0.01"
                min="0.01"
                value="<?= htmlspecialchars(
                    $_POST['unit_price']
                    ??
                    $sale['unit_price']
                ) ?>"
                required
            >

        </div>


        <!-- الخصم -->

        <div class="form-group">

            <label>
                الخصم
            </label>

            <input
                type="number"
                name="discount"
                id="discount"
                step="0.01"
                min="0"
                value="<?= htmlspecialchars(
                    $_POST['discount']
                    ??
                    $sale['discount']
                ) ?>"
            >

        </div>


        <!-- الإجمالي -->

        <div class="form-group">

            <label>
                الإجمالي
            </label>

            <input
                type="text"
                id="total_display"
                readonly
                value="<?= number_format(
                    (float)$sale['total'],
                    2,
                    '.',
                    ''
               ) ?>"
            >

        </div>


        <!-- طريقة الدفع -->

        <div class="form-group">

            <label>
                طريقة الدفع
            </label>

            <select
                name="payment_method"
                id="payment_method"
                required
            >

                <option
                    value="cash"
                    <?= (
                        (
                            $_POST['payment_method']
                            ??
                            $sale['payment_method']
                        )
                        === 'cash'
                    )
                        ? 'selected'
                        : ''
                    ?>
                >
                    نقدي
                </option>

                <option
                    value="card"
                    <?= (
                        (
                            $_POST['payment_method']
                            ??
                            $sale['payment_method']
                        )
                        === 'card'
                    )
                        ? 'selected'
                        : ''
                    ?>
                >
                    بطاقة
                </option>

                <option
                    value="credit"
                    <?= (
                        (
                            $_POST['payment_method']
                            ??
                            $sale['payment_method']
                        )
                        === 'credit'
                    )
                        ? 'selected'
                        : ''
                    ?>
                >
                    آجل
                </option>

            </select>

        </div>


        <!-- التاريخ -->

        <div class="form-group">

            <label>
                تاريخ ووقت البيع
            </label>

            <input
                type="datetime-local"
                name="sale_date"
                value="<?= htmlspecialchars(
                    $_POST['sale_date']
                    ??
                    date(
                        'Y-m-d\TH:i',
                        strtotime(
                            $sale['sale_date']
                        )
                    )
                ) ?>"
                required
            >

        </div>


        <!-- الملاحظات -->

        <div class="form-group">

            <label>
                ملاحظات
            </label>

            <textarea
                name="notes"
                rows="4"
            ><?= htmlspecialchars(
                $_POST['notes']
                ??
                $sale['notes']
                ??
                ''
            ) ?></textarea>

        </div>


        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                حفظ التعديلات
            </button>

            <a
                href="view.php?id=<?= $saleId ?>"
                class="btn"
            >
                إلغاء
            </a>

        </div>

    </form>

</div>

<?php endif; ?>


<script>

const quantityInput =
    document.getElementById('quantity');

const priceInput =
    document.getElementById('unit_price');

const discountInput =
    document.getElementById('discount');

const totalDisplay =
    document.getElementById('total_display');

const paymentMethod =
    document.getElementById('payment_method');

const customerSelect =
    document.getElementById('customer_id');

const customerInfo =
    document.getElementById('customerInfo');


function calculateTotal() {

    if (
        !quantityInput ||
        !priceInput ||
        !discountInput ||
        !totalDisplay
    ) {
        return;
    }

    const quantity =
        parseFloat(quantityInput.value) || 0;

    const price =
        parseFloat(priceInput.value) || 0;

    const discount =
        parseFloat(discountInput.value) || 0;

    let subtotal =
        quantity * price;

    let total =
        subtotal - discount;

    if (total < 0) {
        total = 0;
    }

    totalDisplay.value =
        total.toFixed(2);
}


function updateCustomerInfo() {

    if (
        !customerSelect ||
        !customerInfo
    ) {
        return;
    }

    const option =
        customerSelect.options[
            customerSelect.selectedIndex
        ];

    if (
        !option ||
        !option.value ||
        option.value === '0'
    ) {

        customerInfo.textContent = '';

        return;
    }

    const balance =
        parseFloat(
            option.dataset.balance
        ) || 0;

    const limit =
        parseFloat(
            option.dataset.limit
        ) || 0;

    customerInfo.textContent =
        'الرصيد الحالي: '
        +
        balance.toFixed(2)
        +
        ' | الحد الائتماني: '
        +
        (
            limit > 0
                ? limit.toFixed(2)
                : 'غير محدد'
        );
}


function updateCustomerRequirement() {

    if (
        !paymentMethod ||
        !customerSelect
    ) {
        return;
    }

    customerSelect.required =
        paymentMethod.value === 'credit';
}


if (quantityInput) {

    quantityInput.addEventListener(
        'input',
        calculateTotal
    );
}

if (priceInput) {

    priceInput.addEventListener(
        'input',
        calculateTotal
    );
}

if (discountInput) {

    discountInput.addEventListener(
        'input',
        calculateTotal
    );
}

if (customerSelect) {

    customerSelect.addEventListener(
        'change',
        updateCustomerInfo
    );
}

if (paymentMethod) {

    paymentMethod.addEventListener(
        'change',
        updateCustomerRequirement
    );
}


calculateTotal();
updateCustomerInfo();
updateCustomerRequirement();

</script>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
