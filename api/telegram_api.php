<?php
require_once '../includes/api_auth.php';
require_once '../config/config.php';
require_admin();

// CSRF verification for mutating requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !csrf_verify()) {
    api_error('CSRF token mismatch', 403);
}

header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Helper: get token from DB or config constant
function getBotToken($pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'TG_BOT_TOKEN'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    return !empty($val) ? $val : TG_BOT_TOKEN;
}

function getAdminChat($pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'TG_ADMIN_CHAT_ID'");
    $stmt->execute();
    $val = $stmt->fetchColumn();
    return !empty($val) ? $val : TG_ADMIN_CHAT_ID;
}

// Telegram API call
function tgApiCall($token, $method, $params = []) {
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) {
        return ['ok' => false, 'error' => 'cURL error: ' . $err];
    }
    return json_decode($result, true);
}

switch ($action) {

    // === SAVE BOT TOKEN + ADMIN CHAT ===
    case 'save_config':
        $token = trim($input['token'] ?? '');
        $adminChat = trim($input['admin_chat_id'] ?? '');

        if (empty($token)) {
            echo json_encode(['success' => false, 'error' => 'Токен не может быть пустым']);
            exit;
        }

        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('TG_BOT_TOKEN', ?)")->execute([$token]);
        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('TG_ADMIN_CHAT_ID', ?)")->execute([$adminChat]);

        // Update config constant for this request
        define('TG_BOT_TOKEN', $token);
        define('TG_ADMIN_CHAT_ID', $adminChat);

        echo json_encode(['success' => true]);
        exit;

    // === TEST MESSAGE ===
    case 'test_message':
        $token = getBotToken($pdo);
        $chatId = getAdminChat($pdo);

        if (empty($token)) {
            echo json_encode(['success' => false, 'error' => 'Bot token не задан']);
            exit;
        }
        if (empty($chatId)) {
            echo json_encode(['success' => false, 'error' => 'Chat ID начальника не задан']);
            exit;
        }

        $result = tgApiCall($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => "✅ <b>LifeMed Bot</b> работает!\n\nТестовое сообщение из панели управления.\nВремя: " . date('d.m.Y H:i:s'),
            'parse_mode' => 'HTML',
        ]);

        if (!empty($result['ok'])) {
            echo json_encode(['success' => true]);
        } else {
            $desc = $result['description'] ?? $result['error'] ?? 'Неизвестная ошибка';
            echo json_encode(['success' => false, 'error' => $desc]);
        }
        exit;

    // === SET WEBHOOK ===
    case 'set_webhook':
        $token = getBotToken($pdo);
        if (empty($token)) {
            echo json_encode(['success' => false, 'error' => 'Bot token не задан. Сохраните его сначала.']);
            exit;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $webhookUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/api/telegram_bot.php';

        // Generate or reuse secret token for webhook verification
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'TG_WEBHOOK_SECRET'");
        $stmt->execute();
        $secret = $stmt->fetchColumn();
        if (empty($secret)) {
            $secret = bin2hex(random_bytes(32));
            $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('TG_WEBHOOK_SECRET', ?)")->execute([$secret]);
        }

        $result = tgApiCall($token, 'setWebhook', [
            'url' => $webhookUrl,
            'max_connections' => 40,
            'allowed_updates' => ['message', 'callback_query'],
            'secret_token' => $secret,
        ]);

        if (!empty($result['ok'])) {
            $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('TG_WEBHOOK_URL', ?)")->execute([$webhookUrl]);
            echo json_encode(['success' => true, 'url' => $webhookUrl, 'details' => $result['result'] ?? null]);
        } else {
            $desc = $result['description'] ?? $result['error'] ?? 'Неизвестная ошибка';
            echo json_encode(['success' => false, 'error' => $desc, 'details' => $result]);
        }
        exit;

    // === DELETE WEBHOOK ===
    case 'delete_webhook':
        $token = getBotToken($pdo);
        if (empty($token)) {
            echo json_encode(['success' => false, 'error' => 'Bot token не задан']);
            exit;
        }

        $result = tgApiCall($token, 'deleteWebhook');

        if (!empty($result['ok'])) {
            $pdo->prepare("DELETE FROM settings WHERE setting_key = 'TG_WEBHOOK_URL'")->execute();
            echo json_encode(['success' => true]);
        } else {
            $desc = $result['description'] ?? $result['error'] ?? 'Неизвестная ошибка';
            echo json_encode(['success' => false, 'error' => $desc]);
        }
        exit;

    // === GET WEBHOOK INFO ===
    case 'get_webhook_info':
        $token = getBotToken($pdo);
        if (empty($token)) {
            echo json_encode(['success' => false, 'error' => 'Bot token не задан']);
            exit;
        }

        $result = tgApiCall($token, 'getWebhookInfo');

        if (!empty($result['ok'])) {
            echo json_encode(['success' => true, 'info' => $result['result']]);
        } else {
            $desc = $result['description'] ?? $result['error'] ?? 'Неизвестная ошибка';
            echo json_encode(['success' => false, 'error' => $desc]);
        }
        exit;

    // === LINK TELEGRAM ===
    case 'link_telegram':
        $type = $input['entity_type'] ?? '';
        $entityId = (int)($input['entity_id'] ?? 0);
        $chatId = trim($input['chat_id'] ?? '');

        if (!$entityId || !$chatId) {
            echo json_encode(['success' => false, 'error' => 'Заполните все поля']);
            exit;
        }

        $tables = [
            'user'    => ['table' => 'users',    'col' => 'id', 'tg_col' => 'telegram_id'],
            'doctor'  => ['table' => 'doctors',  'col' => 'id', 'tg_col' => 'telegram_id'],
            'patient' => ['table' => 'patients', 'col' => 'id', 'tg_col' => 'telegram_id'],
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

    // === UNLINK TELEGRAM ===
    case 'unlink_telegram':
        $type = $input['entity_type'] ?? '';
        $entityId = (int)($input['entity_id'] ?? 0);

        if (!$entityId) {
            echo json_encode(['success' => false, 'error' => 'Missing ID']);
            exit;
        }

        $tables = [
            'user'    => ['table' => 'users',    'col' => 'id', 'tg_col' => 'telegram_id'],
            'doctor'  => ['table' => 'doctors',  'col' => 'id', 'tg_col' => 'telegram_id'],
            'patient' => ['table' => 'patients', 'col' => 'id', 'tg_col' => 'telegram_id'],
        ];

        if (!isset($tables[$type])) {
            echo json_encode(['success' => false, 'error' => 'Неизвестный тип']);
            exit;
        }

        $t = $tables[$type];
        $pdo->prepare("UPDATE {$t['table']} SET {$t['tg_col']} = NULL WHERE {$t['col']} = ?")->execute([$entityId]);
        echo json_encode(['success' => true]);
        exit;

    // === SAVE NOTIFICATION SETTINGS (extended) ===
    case 'save_notifications':
        $keys = [
            'TG_NOTIFY_NEW_SERVICE'    => $input['notify_new_service'] ?? '1',
            'TG_NOTIFY_DOCTOR'         => $input['notify_doctor'] ?? '1',
            'TG_DAILY_REPORT'          => $input['daily_report'] ?? '1',
            'TG_NOTIFY_NEW_PATIENT'    => $input['notify_new_patient'] ?? '0',
            'TG_NOTIFY_PAYMENT'        => $input['notify_payment'] ?? '0',
            'TG_NOTIFY_CANCEL'         => $input['notify_cancel'] ?? '0',
            'TG_NOTIFY_SCHEDULE'       => $input['notify_schedule'] ?? '0',
        ];
        foreach ($keys as $key => $val) {
            $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
        }
        echo json_encode(['success' => true]);
        exit;

    // === SAVE MESSAGE TEMPLATES ===
    case 'save_templates':
        $templates = $input['templates'] ?? [];
        $allowed = [
            'TG_TPL_NEW_SERVICE', 'TG_TPL_DOCTOR_NOTIFY', 'TG_TPL_DAILY_REPORT',
            'TG_TPL_NEW_PATIENT', 'TG_TPL_PAYMENT', 'TG_TPL_CANCEL', 'TG_TPL_SCHEDULE',
        ];
        foreach ($templates as $key => $val) {
            if (in_array($key, $allowed)) {
                $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
            }
        }
        echo json_encode(['success' => true]);
        exit;

    // === GET TEMPLATES ===
    case 'get_templates':
        $tpl_keys = [
            'TG_TPL_NEW_SERVICE' => "🏥 <b>Новая услуга</b>\n\n📋 {service}\n👤 {patient}\n🩺 {doctor}\n💰 {price} сум\n📄 Чек: {receipt_id}",
            'TG_TPL_DOCTOR_NOTIFY' => "🏥 <b>Новая запись</b>\n\n👤 Пациент: {patient}\n📋 Услуга: {service}\n📆 {date} 🕐 {time}",
            'TG_TPL_DAILY_REPORT' => "📊 <b>Отчёт за {date}</b>\n\n💰 Выручка: {total} сум\n📋 Приёмов: {count}",
            'TG_TPL_NEW_PATIENT' => "👤 <b>Новый пациент</b>\n\nИмя: {name}\nТел: {phone}\nДата: {date}",
            'TG_TPL_PAYMENT' => "💰 <b>Оплата получена</b>\n\nПациент: {patient}\nСумма: {amount} сум\nЧек: {receipt_id}",
            'TG_TPL_CANCEL' => "❌ <b>Запись отменена</b>\n\nПациент: {patient}\nВрач: {doctor}\nДата: {date} {time}",
            'TG_TPL_SCHEDULE' => "📅 <b>Изменение расписания</b>\n\nВрач: {doctor}\nДата: {date}\n{details}",
        ];
        // Single query instead of 7
        $keys = array_keys($tpl_keys);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$placeholders})");
        $stmt->execute($keys);
        $db_settings = [];
        while ($row = $stmt->fetch()) { $db_settings[$row['setting_key']] = $row['setting_value']; }
        $result = [];
        foreach ($tpl_keys as $key => $default) {
            $result[$key] = $db_settings[$key] ?? $default;
        }
        echo json_encode(['success' => true, 'templates' => $result]);
        exit;

    // === GET TELEGRAM LOGS ===
    case 'get_logs':
        $limit = min((int)($input['limit'] ?? 50), 200);
        $offset = (int)($input['offset'] ?? 0);
        $direction = $input['direction'] ?? '';

        $where = '';
        $params = [];
        if ($direction && in_array($direction, ['incoming', 'outgoing'])) {
            $where = 'WHERE direction = ?';
            $params[] = $direction;
        }

        $stmt = $pdo->prepare("SELECT * FROM telegram_logs {$where} ORDER BY id DESC LIMIT ? OFFSET ?");
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        $countParams = $params ? array_slice($params, 0, -2) : [];
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM telegram_logs" . ($where ? " $where" : ''));
        $countStmt->execute($countParams);
        $total = $countStmt->fetchColumn();

        echo json_encode(['success' => true, 'logs' => $logs, 'total' => $total]);
        exit;

    // === CLEAR TELEGRAM LOGS ===
    case 'clear_logs':
        $before = $input['before'] ?? '';
        if ($before) {
            $pdo->prepare("DELETE FROM telegram_logs WHERE created_at < ?")->execute([$before]);
        } else {
            $pdo->exec("TRUNCATE TABLE telegram_logs");
        }
        echo json_encode(['success' => true]);
        exit;

    // === BLACKLIST: ADD ===
    case 'blacklist_add':
        $chatId = trim($input['chat_id'] ?? '');
        $reason = trim($input['reason'] ?? '');
        if (!$chatId) {
            echo json_encode(['success' => false, 'error' => 'Укажите Chat ID']);
            exit;
        }
        try {
            $pdo->prepare("INSERT IGNORE INTO telegram_blacklist (chat_id, reason, blocked_by) VALUES (?, ?, ?)")
                ->execute([$chatId, $reason, $_SESSION['user_id'] ?? null]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;

    // === BLACKLIST: REMOVE ===
    case 'blacklist_remove':
        $chatId = trim($input['chat_id'] ?? '');
        if (!$chatId) {
            echo json_encode(['success' => false, 'error' => 'Укажите Chat ID']);
            exit;
        }
        $pdo->prepare("DELETE FROM telegram_blacklist WHERE chat_id = ?")->execute([$chatId]);
        echo json_encode(['success' => true]);
        exit;

    // === BLACKLIST: LIST ===
    case 'blacklist_list':
        $stmt = $pdo->query("SELECT * FROM telegram_blacklist ORDER BY created_at DESC");
        $list = $stmt->fetchAll();
        echo json_encode(['success' => true, 'list' => $list]);
        exit;

    // === CHECK IF BLACKLISTED ===
    case 'check_blacklist':
        $chatId = trim($input['chat_id'] ?? '');
        $stmt = $pdo->prepare("SELECT id FROM telegram_blacklist WHERE chat_id = ?");
        $stmt->execute([$chatId]);
        echo json_encode(['success' => true, 'blocked' => (bool)$stmt->fetch()]);
        exit;

    // === CSV IMPORT (bulk link) ===
    case 'csv_import':
        $csvData = $input['csv_data'] ?? '';
        $type = $input['entity_type'] ?? 'patient';

        if (!$csvData) {
            echo json_encode(['success' => false, 'error' => 'Нет данных CSV']);
            exit;
        }

        $tables = [
            'user'    => ['table' => 'users',    'col' => 'id', 'tg_col' => 'telegram_id'],
            'doctor'  => ['table' => 'doctors',  'col' => 'id', 'tg_col' => 'telegram_id'],
            'patient' => ['table' => 'patients', 'col' => 'id', 'tg_col' => 'telegram_id'],
        ];
        if (!isset($tables[$type])) {
            echo json_encode(['success' => false, 'error' => 'Неизвестный тип']);
            exit;
        }
        $t = $tables[$type];

        $lines = array_filter(array_map('trim', explode("\n", $csvData)));
        $linked = 0;
        $errors = [];
        $lineNum = 0;

        foreach ($lines as $line) {
            $lineNum++;
            if ($lineNum === 1 && stripos($line, 'id') === 0 && stripos($line, 'chat') !== false) continue; // skip header
            $parts = str_getcsv($line);
            if (count($parts) < 2) {
                $errors[] = "Строка {$lineNum}: мало данных";
                continue;
            }
            $entityId = (int)trim($parts[0]);
            $chatId = trim($parts[1]);
            if (!$entityId || !$chatId) {
                $errors[] = "Строка {$lineNum}: пустые данные";
                continue;
            }

            $check = $pdo->prepare("SELECT id FROM {$t['table']} WHERE {$t['col']} = ?");
            $check->execute([$entityId]);
            if (!$check->fetch()) {
                $errors[] = "Строка {$lineNum}: ID #{$entityId} не найден";
                continue;
            }

            $pdo->prepare("UPDATE {$t['table']} SET {$t['tg_col']} = ? WHERE {$t['col']} = ?")->execute([$chatId, $entityId]);
            $linked++;
        }

        echo json_encode(['success' => true, 'linked' => $linked, 'errors' => $errors]);
        exit;

    // === TEST NOTIFICATION BY TYPE ===
    case 'test_notification':
        $type = $input['notif_type'] ?? '';
        $chatId = trim($input['chat_id'] ?? '');

        if (empty($chatId)) {
            echo json_encode(['success' => false, 'error' => 'Укажите Chat ID для теста']);
            exit;
        }

        $testMessages = [
            'new_service'  => "🏥 <b>[ТЕСТ] Новая услуга</b>\n\n📋 УЗИ сердца\n👤 Иванов Иван\n🩺 Доктор Петров\n💰 150 000 сум\n📄 Чек: #1234",
            'doctor'       => "🏥 <b>[ТЕСТ] Новая запись</b>\n\n👤 Пациент: Сидоров\n📋 Услуга: Консультация\n📆 " . date('d.m.Y') . " 🕐 14:00",
            'daily_report' => "📊 <b>[ТЕСТ] Отчёт за " . date('d.m.Y') . "</b>\n\n💰 Выручка: 2 500 000 сум\n📋 Приёмов: 18",
            'new_patient'  => "👤 <b>[ТЕСТ] Новый пациент</b>\n\nИмя: Тест Тестов\nТел: +998901234567\nДата: " . date('d.m.Y'),
            'payment'      => "💰 <b>[ТЕСТ] Оплата получена</b>\n\nПациент: Иванов\nСумма: 200 000 сум\nЧек: #5678",
            'cancel'       => "❌ <b>[ТЕСТ] Запись отменена</b>\n\nПациент: Иванов\nВрач: Доктор\nДата: " . date('d.m.Y') . " 10:00",
            'schedule'     => "📅 <b>[ТЕСТ] Изменение расписания</b>\n\nВрач: Доктор Петров\nДата: " . date('d.m.Y') . "\nДобавлен приём в 15:00",
        ];

        if (!isset($testMessages[$type])) {
            echo json_encode(['success' => false, 'error' => 'Неизвестный тип: ' . $type]);
            exit;
        }

        $token = getBotToken($pdo);
        $result = tgApiCall($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $testMessages[$type],
            'parse_mode' => 'HTML',
        ]);

        if (!empty($result['ok'])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['description'] ?? 'Ошибка отправки']);
        }
        exit;

    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие: ' . $action]);
        exit;
}
