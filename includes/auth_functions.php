<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

function login($username, $password) {
    global $pdo;
    
    // Rate limiting on login
    if (!rate_limit('login_' . $username, 5, 300)) {
        return false;
    }

    // Try Users Table (Admin/Cashier)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        audit_log($pdo, 'login', 'user', $user['id']);
        return true;
    }

    // Try Doctors Table
    $stmt = $pdo->prepare("SELECT * FROM doctors WHERE username = ?");
    $stmt->execute([$username]);
    $doctor = $stmt->fetch();

    if ($doctor && password_verify($password, $doctor['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $doctor['id'];
        $_SESSION['doctor_id'] = $doctor['id'];
        $_SESSION['full_name'] = $doctor['full_name'];
        $_SESSION['role'] = 'doctor';
        audit_log($pdo, 'login', 'doctor', $doctor['id']);
        return true;
    }

    return false;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function check_role($roles = []) {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit();
    }
    if (!empty($roles) && !in_array($_SESSION['role'], $roles)) {
        http_response_code(403);
        die("Access denied.");
    }
}

function logout() {
    session_destroy();
    header("Location: login.php");
    exit();
}
