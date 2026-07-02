<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'lifemed');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
date_default_timezone_set('Asia/Tashkent');

// Error reporting - set APP_DEBUG to true for debugging
define('APP_DEBUG', false);
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
// Always log errors to file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

// Session configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Telegram Bot
define('TG_BOT_TOKEN', '7714801274:AAFysDwMT5RUAT4e5QDm-fERdR_yvRZ_Qsc'); // BotFather token
define('TG_ADMIN_CHAT_ID', '1783400289'); // Chat ID начальника
