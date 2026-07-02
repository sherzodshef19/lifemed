<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
require_once '../config/config.php';
check_role(['admin']);

header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);

// Save TG_ADMIN_CHAT_ID
if (isset($input['setting_key'])) {
    $key = $input['setting_key'];
    $val = $input['setting_value'] ?? '';
    if (!in_array($key, ['TG_ADMIN_CHAT_ID'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid setting']);
        exit;
    }
    $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
    echo json_encode(['success' => true]);
    exit;
}

// Link Telegram account
if (($input['action'] ?? '') === 'link_telegram') {
    $type = $input['entity_type'] ?? '';
    $entityId = (int)($input['entity_id'] ?? 0);
    $chatId = trim($input['chat_id'] ?? '');

    if (!$entityId || !$chatId) {
        echo json_encode(['success' => false, 'error' => 'Заполните все поля']);
        exit;
    }

    $tables = [
        'user'   => ['table' => 'users',   'col' => 'id', 'tg_col' => 'telegram_id'],
        'doctor' => ['table' => 'doctors',  'col' => 'id', 'tg_col' => 'telegram_id'],
        'patient'=> ['table' => 'patients', 'col' => 'id', 'tg_col' => 'telegram_id'],
    ];

    if (!isset($tables[$type])) {
        echo json_encode(['success' => false, 'error' => 'Неизвестный тип: ' . $type]);
        exit;
    }

    $t = $tables[$type];
    $check = $pdo->prepare("SELECT id FROM {$t['table']} WHERE {$t['col']} = ?");
    $check->execute([$entityId]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Запись #' . $entityId . ' не найдена']);
        exit;
    }

    $pdo->prepare("UPDATE {$t['table']} SET {$t['tg_col']} = ? WHERE {$t['col']} = ?")->execute([$chatId, $entityId]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
