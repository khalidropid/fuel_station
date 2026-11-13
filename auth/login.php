<?php

session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور.';

    } else {

        $stmt = $pdo->prepare("
            SELECT id, username, password, full_name, role, is_active
            FROM users
            WHERE username = :username
            LIMIT 1
        ");

        $stmt->execute([
            ':username' => $username
        ]);

        $user = $stmt->fetch();

        if (
            $user &&
            $user['is_active'] == 1 &&
            password_verify($password, $user['password'])
        ) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];

            header('Location: ../dashboard/');
            exit;

        } else {

            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>تسجيل الدخول - محطة الريان</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f2f4f7;

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-box {
            width: 380px;
            max-width: 90%;

            background: white;

            padding: 35px;

            border-radius: 12px;

            box-shadow:
                0 5px 25px rgba(0,0,0,0.10);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo h1 {
            margin: 0;
            color: #1f2937;
        }

        .logo p {
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 7px;

            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            width: 100%;

            padding: 12px;

            border: none;

            border-radius: 7px;

            background: #2563eb;

            color: white;

            font-size: 16px;

            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .error {
            background: #fee2e2;

            color: #991b1b;

            padding: 10px;

            border-radius: 7px;

            margin-bottom: 15px;

            text-align: center;
        }

    </style>

</head>

<body>

<div class="login-box">

    <div class="logo">

        <h1>محطة الريان</h1>

        <p>نظام إدارة المحطة</p>

    </div>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST">

        <div class="form-group">

            <label for="username">
                اسم المستخدم
            </label>

            <input
                type="text"
                id="username"
                name="username"
                autocomplete="username"
                required
            >

        </div>

        <div class="form-group">

            <label for="password">
                كلمة المرور
            </label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

        </div>

        <button type="submit">
            تسجيل الدخول
        </button>

    </form>

</div>

</body>

</html>
