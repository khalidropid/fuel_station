<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header(
    'Content-Type: application/json; charset=utf-8'
);


/*
|--------------------------------------------------------------------------
| دالة JSON
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message = '',
    array $data = [],
    int $statusCode = 200
): void {

    http_response_code($statusCode);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


try {

    /*
    |--------------------------------------------------------------------------
    | رقم الوردية
    |--------------------------------------------------------------------------
    */

    $shiftId =
        (int)(
            $_GET['shift_id']
            ?? 0
        );


    if ($shiftId <= 0) {

        jsonResponse(
            false,
            'رقم الوردية غير صحيح.',
            [],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | التحقق من الوردية
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
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

        WHERE s.id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $shiftId
    ]);


    $shift =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$shift) {

        jsonResponse(
            false,
            'الوردية غير موجودة.',
            [],
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | منع التعامل مع وردية مغلقة
    |--------------------------------------------------------------------------
    */

    if (
        $shift['status']
        !==
        'open'
    ) {

        jsonResponse(
            false,
            'الوردية مغلقة ولا يمكن تسجيل بيع فيها.',
            [],
            409
        );
    }


    /*
    |--------------------------------------------------------------------------
    | جلب الطرمبات المرتبطة بالوردية
    |--------------------------------------------------------------------------
    |
    | shift_pumps
    |      ↓
    | pumps
    |      ↓
    | tanks
    |      ↓
    | fuel_types
    |
    */

    $stmt = $pdo->prepare("
        SELECT

            sp.id AS shift_pump_id,

            sp.shift_id,

            sp.pump_id AS id,

            sp.opening_reading,

            sp.closing_reading,

            sp.total_liters,

            sp.total_sales,


            p.pump_no,

            p.name AS name,

            p.status AS pump_status,

            p.tank_id,


            t.name AS tank_name,

            t.capacity,

            t.current_volume,

            t.fuel_type_id,


            ft.code AS fuel_code,

            ft.name AS fuel_name,

            ft.unit AS fuel_unit

        FROM shift_pumps sp


        INNER JOIN pumps p
            ON p.id = sp.pump_id


        INNER JOIN tanks t
            ON t.id = p.tank_id


        INNER JOIN fuel_types ft
            ON ft.id = t.fuel_type_id


        WHERE sp.shift_id = ?

        AND p.status = 'active'

        AND t.fuel_type_id IS NOT NULL

        AND ft.is_active = 1


        ORDER BY
            p.pump_no ASC
    ");

    $stmt->execute([
        $shiftId
    ]);


    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /*
    |--------------------------------------------------------------------------
    | تجهيز بيانات الطرمبات
    |--------------------------------------------------------------------------
    */

    $pumps = [];


    foreach ($rows as $row) {

        $currentVolume =
            (float)$row['current_volume'];


        $capacity =
            (float)$row['capacity'];


        /*
        |--------------------------------------------------------------------------
        | حماية من القيم غير الصحيحة
        |--------------------------------------------------------------------------
        */

        if ($currentVolume < 0) {
            $currentVolume = 0;
        }


        if (
            $capacity > 0
            &&
            $currentVolume > $capacity
        ) {

            $currentVolume =
                $capacity;
        }


        $pumps[] = [

            'id' =>
                (int)$row['id'],


            'shift_pump_id' =>
                (int)$row['shift_pump_id'],


            'shift_id' =>
                (int)$row['shift_id'],


            'pump_no' =>
                $row['pump_no'],


            'name' =>
                $row['name'],


            'tank_id' =>
                (int)$row['tank_id'],


            'tank_name' =>
                $row['tank_name'],


            'fuel_type_id' =>
                (int)$row['fuel_type_id'],


            'fuel_code' =>
                $row['fuel_code'],


            'fuel_name' =>
                $row['fuel_name'],


            'fuel_unit' =>
                $row['fuel_unit'],


            'current_volume' =>
                number_format(
                    $currentVolume,
                    2,
                    '.',
                    ''
                ),


            'capacity' =>
                number_format(
                    $capacity,
                    2,
                    '.',
                    ''
                ),


            'opening_reading' =>
                number_format(
                    (float)$row['opening_reading'],
                    2,
                    '.',
                    ''
                ),


            'closing_reading' =>
                $row['closing_reading'] !== null
                    ? number_format(
                        (float)$row['closing_reading'],
                        2,
                        '.',
                        ''
                    )
                    : null,


            'total_liters' =>
                number_format(
                    (float)$row['total_liters'],
                    2,
                    '.',
                    ''
                ),


            'total_sales' =>
                number_format(
                    (float)$row['total_sales'],
                    2,
                    '.',
                    ''
                )

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | النتيجة
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'تم تحميل الطرمبات بنجاح.',
        [
            'shift' => [

                'id' =>
                    (int)$shift['id'],

                'shift_no' =>
                    $shift['shift_no'],

                'employee_id' =>
                    (int)$shift['employee_id'],

                'employee_no' =>
                    $shift['employee_no'],

                'employee_name' =>
                    $shift['employee_name'],

                'status' =>
                    $shift['status']
            ],

            'pumps' =>
                $pumps,

            'count' =>
                count($pumps)
        ]
    );


} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | خطأ قاعدة البيانات
    |--------------------------------------------------------------------------
    */

    error_log(
        'get_pumps.php PDO Error: '
        .
        $e->getMessage()
    );


    jsonResponse(
        false,
        'حدث خطأ أثناء تحميل الطرمبات.',
        [],
        500
    );


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | أخطاء عامة
    |--------------------------------------------------------------------------
    */

    error_log(
        'get_pumps.php Error: '
        .
        $e->getMessage()
    );


    jsonResponse(
        false,
        'حدث خطأ غير متوقع.',
        [],
        500
    );
}