<?php
require_once '../config/config.php';
require_once '../includes/auth_functions.php';
require_once '../includes/helpers.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid form submission';
    } else {
        $username = sanitize_string($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (login($username, $password)) {
            header("Location: index.php");
            exit();
        } else {
            $error = 'Неверный логин или пароль или аккаунт заблокирован';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - LifeMed CRM</title>
<!--  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> 
   <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">  -->

 <link rel="stylesheet" href="/assets/vendor/css/fontawesome.min.css">
 <link href="/assets/vendor/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1.5rem;
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #3b82f6;
            color: white;
            box-shadow: none;
        }
        .btn-login {
            background: #3b82f6;
            border: none;
            padding: 0.75rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-login:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-5">
            <h2 class="fw-bold">LiFe <span class="text-info">Med</span></h2>
            <p class="text-secondary">Clinic Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-25 text-white mb-4">
                <?= h($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-4">
                <label class="form-label small text-secondary">Логин</label>
                <input type="text" name="username" class="form-control" required placeholder="admin" autocomplete="username">
            </div>
            <div class="mb-4">
                <label class="form-label small text-secondary">Пароль</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••••" autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-login w-100 text-white">Войти в систему</button>
        </form>
    </div>
</body>
</html>
