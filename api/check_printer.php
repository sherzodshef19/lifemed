<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin']);

$ip = $_GET['ip'] ?? '';

if (filter_var($ip, FILTER_VALIDATE_IP)) {
    // Attempt to open a socket connection to port 9100 (standard for RAW printing)
    $connection = @fsockopen($ip, 9100, $errno, $errstr, 2);
    if ($connection) {
        fclose($connection);
        echo json_encode(['success' => true, 'message' => 'Принтер в сети и готов к работе']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Не удалось соединиться с принтером (check IP/Power)']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Некорректный IP адрес']);
}
