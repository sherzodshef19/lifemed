<?php
/**
 * Telegram Bot для LifeMed CRM
 * 
 * Роли:
 * - Админ/начальник: ежедневный отчёт, бэкап, уведомление о каждой услуге
 * - Пациент: ЧЕК ID {receipt_id} → получает PDF чек
 * - Врач: уведомление о приёме + результат анализа PDF
 *
 * Webhook URL: https://ваш-домен/api/telegram_bot.php
 */

require_once '../config/db.php';

// ==================== Logging ====================

function tgLog($pdo, $direction, $chat_id, $message_type = 'text', $message_text = null, $response_status = null, $error_message = null) {
    try {
        $pdo->prepare("INSERT INTO telegram_logs (direction, chat_id, message_type, message_text, response_status, error_message) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$direction, (string)$chat_id, $message_type, $message_text ? mb_substr($message_text, 0, 4000) : null, $response_status, $error_message]);
    } catch (Exception $e) {
        // Silently fail — logs table may not exist yet
    }
}

function isBlacklisted($pdo, $chat_id) {
    static $cache = [];
    $key = (string)$chat_id;
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = $pdo->prepare("SELECT id FROM telegram_blacklist WHERE chat_id = ?");
        $stmt->execute([$key]);
        $cache[$key] = (bool)$stmt->fetch();
    } catch (Exception $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

// Telegram API helpers
function tgApi($method, $params = []) {
    global $pdo;
    $token = function_exists('getBotToken') ? getBotToken($pdo) : TG_BOT_TOKEN;
    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function tgSend($chat_id, $text, $parse_mode = 'HTML', $reply_markup = null) {
    global $pdo;
    $params = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => $parse_mode];
    if ($reply_markup) $params['reply_markup'] = $reply_markup;
    $result = tgApi('sendMessage', $params);
    $status = !empty($result['ok']) ? 'ok' : ($result['description'] ?? 'error');
    tgLog($pdo, 'outgoing', $chat_id, 'text', $text, $status, $status !== 'ok' ? $status : null);
    return $result;
}

function tgSendPhoto($chat_id, $photo_path, $caption = '') {
    if (!file_exists($photo_path)) return false;
    global $pdo;
    $token = function_exists('getBotToken') ? getBotToken($pdo) : TG_BOT_TOKEN;
    $url = 'https://api.telegram.org/bot' . $token . '/sendPhoto';
    $ch = curl_init($url);
    $post = [
        'chat_id' => ['@' => $chat_id],
        'caption' => $caption,
        'photo' => new CURLFile($photo_path),
    ];
    // Fix: chat_id as string
    $post = [
        'chat_id' => $chat_id,
        'caption' => $caption,
        'photo' => new CURLFile($photo_path),
    ];
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function tgSendDocument($chat_id, $file_path, $caption = '') {
    if (!file_exists($file_path)) return false;
    global $pdo;
    $token = function_exists('getBotToken') ? getBotToken($pdo) : TG_BOT_TOKEN;
    $url = 'https://api.telegram.org/bot' . $token . '/sendDocument';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $chat_id,
            'caption' => $caption,
            'document' => new CURLFile($file_path),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Get admin chat ID from DB settings, fallback to config constant
function getAdminChatId($pdo) {
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'TG_ADMIN_CHAT_ID'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        $cached = !empty($val) ? $val : TG_ADMIN_CHAT_ID;
    } catch (Exception $e) {
        $cached = TG_ADMIN_CHAT_ID;
    }
    return $cached;
}

function getBotToken($pdo) {
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'TG_BOT_TOKEN'");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        $cached = !empty($val) ? $val : TG_BOT_TOKEN;
    } catch (Exception $e) {
        $cached = TG_BOT_TOKEN;
    }
    return $cached;
}

// ==================== PDF Generation ====================

function generateReceiptPdf($receipt_id, $pdo) {
    require_once __DIR__ . '/../../assets/vendor/simple_pdf.php';

    $stmt = $pdo->prepare("
        SELECT a.*, p.full_name as patient, p.phone as patient_phone, p.dob,
               d.full_name as doctor, s.name as service, s.price,
               sd.name as direction, sg.name as group_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        JOIN services s ON a.service_id = s.id
        LEFT JOIN service_directions sd ON s.direction_id = sd.id
        LEFT JOIN service_groups sg ON sd.group_id = sg.id
        WHERE a.receipt_id = ?
    ");
    $stmt->execute([$receipt_id]);
    $items = $stmt->fetchAll();
    if (empty($items)) return null;

    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $settings_stmt->fetch()) $settings[$row['setting_key']] = $row['setting_value'];
    $clinic_name = $settings['clinic_name'] ?? 'LifeMed';

    $total = 0;
    foreach ($items as $item) {
        $qty = $item['quantity'] ?? 1;
        $total += $item['price'] * $qty;
    }

    $first = $items[0];
    $pdf = new SimplePdf();
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->cell(0, 12, $clinic_name, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->cell(0, 6, $settings['clinic_address'] ?? '', 0, 1, 'C');
    $pdf->cell(0, 6, $settings['clinic_phone'] ?? '', 0, 1, 'C');
    $pdf->ln(4);
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->cell(0, 10, 'КВИТАНЦИЯ #' . $receipt_id, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->cell(0, 6, date('d.m.Y H:i'), 0, 1, 'C');
    $pdf->ln(4);
    $pdf->cell(0, 6, 'Пациент: ' . $first['patient'], 0, 1);
    $pdf->cell(0, 6, 'Тел: ' . ($first['patient_phone'] ?? ''), 0, 1);
    $pdf->cell(0, 6, 'Врач: ' . ($first['doctor'] ?: 'Общий приём'), 0, 1);
    $pdf->ln(4);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->cell(0, 8, 'Услуги:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    foreach ($items as $item) {
        $qty = $item['quantity'] ?? 1;
        $line = $item['service'] . ($qty > 1 ? " x{$qty}" : '');
        $line_total = $item['price'] * $qty;
        $pdf->cell(140, 6, $line, 0, 0);
        $pdf->cell(50, 6, number_format($line_total, 0, '.', ' ') . ' сум', 0, 1, 'R');
    }

    $pdf->ln(2);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->cell(0, 8, 'ИТОГО: ' . number_format($total, 0, '.', ' ') . ' сум', 0, 1, 'C');

    $path = sys_get_temp_dir() . '/receipt_' . $receipt_id . '.pdf';
    $pdf->Output('F', $path);
    return $path;
}

// ==================== Role Detection ====================

function detectUserRole($chat_id, $pdo) {
    static $cache = [];
    $key = (string)$chat_id;
    if (isset($cache[$key])) return $cache[$key];

    // Single UNION query instead of 3 separate queries
    $stmt = $pdo->prepare("
        SELECT 'admin' as role, id, full_name FROM users WHERE telegram_id = ? AND role = 'admin'
        UNION ALL
        SELECT 'doctor' as role, id, full_name FROM doctors WHERE telegram_id = ?
        UNION ALL
        SELECT 'patient' as role, id, full_name FROM patients WHERE telegram_id = ? AND (deleted_at IS NULL)
        LIMIT 1
    ");
    $stmt->execute([$chat_id, $chat_id, $chat_id]);
    $row = $stmt->fetch();

    if ($row) {
        $cache[$key] = ['role' => $row['role'], 'data' => ['id' => $row['id'], 'full_name' => $row['full_name']]];
    } else {
        $cache[$key] = null;
    }
    return $cache[$key];
}

// ==================== Link Commands ====================

function generateLinkCode($chat_id, $pdo) {
    $code = strtoupper(substr(md5($chat_id . time()), 0, 8));
    // Store temporarily (valid 10 min)
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['tg_link_' . $code, json_encode(['chat_id' => $chat_id, 'expires' => time() + 600])]);
    return $code;
}

function processLinkCode($code, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute(['tg_link_' . $code]);
    $row = $stmt->fetch();
    if (!$row) return false;

    $data = json_decode($row['setting_value'], true);
    if (!$data || $data['expires'] < time()) return false;

    // Clean up
    $pdo->prepare("DELETE FROM settings WHERE setting_key = ?")->execute(['tg_link_' . $code]);
    return $data['chat_id'];
}

// ==================== PUBLIC API FUNCTIONS ====================
// Called from PHP (appointments.php, lab_results.php, etc.)

function notifyAdminNewService($appointment, $pdo) {
    $chat_id = getAdminChatId($pdo);
    if (empty($chat_id)) return;

    $text = "🏥 <b>Новая услуга оформлена</b>\n\n"
        . "📋 " . $appointment['service'] . "\n"
        . "👤 " . $appointment['patient'] . "\n"
        . "🩺 " . ($appointment['doctor'] ?: 'Общий приём') . "\n"
        . "💰 " . number_format($appointment['price'] * ($appointment['quantity'] ?? 1), 0, '.', ' ') . " сум\n"
        . "📄 Чек: " . $appointment['receipt_id'];

    tgSend($chat_id, $text);
}

function notifyDoctor($doctor_id, $message, $pdo) {
    $stmt = $pdo->prepare("SELECT telegram_id, full_name FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doc = $stmt->fetch();
    if ($doc && $doc['telegram_id']) {
        tgSend($doc['telegram_id'], "🏥 <b>" . $doc['full_name'] . "</b>\n\n" . $message);
    }
}

function notifyPatientLabResult($patient_id, $result_id, $pdo) {
    $stmt = $pdo->prepare("SELECT telegram_id, full_name FROM patients WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    if (!$patient || empty($patient['telegram_id'])) return;

    $stmt = $pdo->prepare("SELECT lt.title FROM lab_results lr JOIN lab_templates lt ON lr.template_id = lt.id WHERE lr.id = ?");
    $stmt->execute([$result_id]);
    $lab = $stmt->fetch();

    $title = $lab['title'] ?? 'Анализ';
    $keyboard = json_encode([
        'inline_keyboard' => [[
            ['text' => '📊 Посмотреть результат', 'callback_data' => 'labresult:' . $result_id]
        ]]
    ]);

    tgSend($patient['telegram_id'],
        "📊 <b>Готов результат анализа!</b>\n\n"
        . "Тип: " . $title . "\n"
        . "Дата: " . date('d.m.Y H:i') . "\n\n"
        . "Нажмите кнопку ниже для просмотра:",
        'HTML',
        $keyboard
    );
}

function notifyPatientAppointment($patient_id, $appointment, $pdo) {
    $stmt = $pdo->prepare("SELECT telegram_id, full_name FROM patients WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    if (!$patient || empty($patient['telegram_id'])) return;

    $doctor = $appointment['doctor'] ?? 'Не указан';
    $service = $appointment['service'] ?? '';
    $date = $appointment['date'] ?? date('d.m.Y');
    $time = $appointment['time'] ?? '';

    tgSend($patient['telegram_id'],
        "📅 <b>Новая запись на приём</b>\n\n"
        . "🩺 Врач: " . $doctor . "\n"
        . "📋 Услуга: " . $service . "\n"
        . "📆 Дата: " . $date . "\n"
        . "🕐 Время: " . $time . "\n\n"
        . "Ждём вас в клинике!",
        'HTML'
    );
}

function sendDailyReport($pdo) {
    $chat_id = getAdminChatId($pdo);
    if (empty($chat_id)) return;

    $today = date('Y-m-d');

    // Income
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt, COALESCE(SUM(s.price * COALESCE(a.quantity, 1)), 0) as total
        FROM appointments a JOIN services s ON a.service_id = s.id
        WHERE a.appointment_date = ?
    ");
    $stmt->execute([$today]);
    $stats = $stmt->fetch();

    // By doctor
    $stmt = $pdo->prepare("
        SELECT d.full_name as doctor, COUNT(*) as cnt, SUM(s.price * COALESCE(a.quantity, 1)) as total
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        WHERE a.appointment_date = ?
        GROUP BY a.doctor_id
        ORDER BY total DESC
    ");
    $stmt->execute([$today]);
    $by_doctor = $stmt->fetchAll();

    $text = "📊 <b>Отчёт за " . date('d.m.Y') . "</b>\n\n"
        . "💰 Выручка: <b>" . number_format($stats['total'], 0, '.', ' ') . " сум</b>\n"
        . "📋 Приёмов: <b>" . $stats['cnt'] . "</b>\n\n";

    if (!empty($by_doctor)) {
        $text .= "<b>По врачам:</b>\n";
        foreach ($by_doctor as $d) {
            $text .= "• " . ($d['doctor'] ?: 'Общий') . ": " . $d['cnt'] . " приёмов — " . number_format($d['total'], 0, '.', ' ') . " сум\n";
        }
    }

    tgSend($chat_id, $text);
}

function backupDatabase($pdo) {
    $chat_id = getAdminChatId($pdo);
    if (empty($chat_id)) return;

    $db_name = 'lifemed';
    $path = sys_get_temp_dir() . '/backup_' . date('Y-m-d_His') . '.sql';

    $cmd = "C:\\OSPanel\\modules\\database\\MySQL-8.0-x64\\bin\\mysqldump.exe -u root $db_name > \"$path\" 2>&1";
    exec($cmd);

    if (file_exists($path) && filesize($path) > 0) {
        tgSendDocument($chat_id, $path, "💾 Бэкап БД за " . date('d.m.Y H:i'));
        unlink($path);
    }
}

// ==================== MAIN WEBHOOK ====================

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Verify webhook secret token
$webhook_secret = '';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'TG_WEBHOOK_SECRET'");
    $stmt->execute();
    $webhook_secret = $stmt->fetchColumn();
} catch (Exception $e) { /* table may not exist yet */ }

if (!empty($webhook_secret)) {
    $provided_secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals($webhook_secret, $provided_secret)) {
        http_response_code(403);
        exit('Forbidden: invalid secret token');
    }
}

if (empty(getBotToken($pdo))) {
    exit('Bot token not configured');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) exit('Invalid input');

// Log incoming & check blacklist
$incoming_chat_id = $input['callback_query']['from']['id'] ?? $input['message']['chat']['id'] ?? null;
$incoming_text = $input['message']['text'] ?? null;
if ($incoming_chat_id) {
    tgLog($pdo, 'incoming', $incoming_chat_id, $incoming_text ? 'text' : 'other', $incoming_text);
    if (isBlacklisted($pdo, $incoming_chat_id)) {
        // Silently ignore blacklisted users
        tgApi('sendMessage', [
            'chat_id' => $incoming_chat_id,
            'text' => '❌ Ваш аккаунт заблокирован.',
        ]);
        exit;
    }
}

// Handle callback query
if (!empty($input['callback_query'])) {
    $cb = $input['callback_query'];
    $chat_id = $cb['from']['id'];
    $data = $cb['data'];

    tgApi('answerCallbackQuery', ['callback_query_id' => $cb['id']]);

    if (strpos($data, 'receipt:') === 0) {
        $receipt_id = str_replace('receipt:', '', $data);
        $user = detectUserRole($chat_id, $pdo);

        if ($user) {
            $pdf_path = generateReceiptPdf($receipt_id, $pdo);
            if ($pdf_path) {
                tgSendDocument($chat_id, $pdf_path, "📄 Чек #$receipt_id");
                unlink($pdf_path);
            } else {
                tgSend($chat_id, "❌ Чек не найден");
            }
        }
    }

    // Lab result callback
    if (strpos($data, 'labresult:') === 0) {
        $result_id = (int) str_replace('labresult:', '', $data);
        $user = detectUserRole($chat_id, $pdo);

        if ($user && $user['role'] === 'patient') {
            $stmt = $pdo->prepare("
                SELECT lr.result_data, lr.created_at, lt.title, lt.content
                FROM lab_results lr
                JOIN lab_templates lt ON lr.template_id = lt.id
                WHERE lr.id = ? AND lr.patient_id = ?
            ");
            $stmt->execute([$result_id, $user['data']['id']]);
            $result = $stmt->fetch();

            if ($result) {
                $date = date('d.m.Y H:i', strtotime($result['created_at']));
                $text = "📊 <b>" . $result['title'] . "</b>\n"
                    . "📅 " . $date . "\n\n";

                // Parse result_data (JSON or plain text)
                $data = json_decode($result['result_data'], true);
                if ($data && is_array($data)) {
                    foreach ($data as $key => $val) {
                        $text .= "• <b>" . h($key) . ":</b> " . h($val) . "\n";
                    }
                } else {
                    $text .= h($result['result_data'] ?? 'Нет данных');
                }

                tgSend($chat_id, $text, 'HTML');
            } else {
                tgSend($chat_id, "❌ Результат не найден.");
            }
        }
    }

    exit;
}

// Handle text messages
if (empty($input['message']['text'])) exit;
if (empty($input['message']['chat'])) exit;

$chat_id = $input['message']['chat']['id'];
$text = trim($input['message']['text']);
$first_name = $input['message']['from']['first_name'] ?? '';

// ==================== COMMANDS ====================

// /start
if ($text === '/start') {
    $user = detectUserRole($chat_id, $pdo);

    if ($user) {
        if ($user['role'] === 'admin') {
            $keyboard = [
                ['📊 Отчёт за сегодня', '💾 Бэкап БД'],
            ];
            $markup = json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false]);
            tgSend($chat_id, "👋 Здравствуйте, {$user['data']['full_name']}!\n\n"
                . "Вы вошли как <b>Администратор</b>.\n\n"
                . "📋 Доступные команды:\n"
                . "📊 Отчёт за сегодня — ежедневный отчёт\n"
                . "💾 Бэкап БД — резервная копия\n\n"
                . "Пациенты могут отправить: ЧЕК ID {номер} — получить PDF чек",
                'HTML', $markup);
        } elseif ($user['role'] === 'doctor') {
            $keyboard = [
                ['📋 Мои сегодняшние приёмы'],
            ];
            $markup = json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false]);
            tgSend($chat_id, "👋 Здравствуйте, доктор {$user['data']['full_name']}!\n\n"
                . "Вы будете получать уведомления о новых записях.",
                'HTML', $markup);
        } elseif ($user['role'] === 'patient') {
            $keyboard = [
                ['📋 Мои чеки', '📊 Мои анализы'],
                ['ℹ️ Помощь'],
            ];
            $markup = json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false]);
            tgSend($chat_id, "👋 Здравствуйте, <b>{$user['data']['full_name']}</b>!\n\n"
                . "Выберите действие:", 'HTML', $markup);
        }
    } else {
        // Not linked — ask for phone number
        $link_code = generateLinkCode($chat_id, $pdo);
        tgSend($chat_id, "👋 Добро пожаловать в <b>LifeMed Bot</b>!\n\n"
            . "Для привязки аккаунта отправьте свой <b>номер телефона</b>\n"
            . "(тот же, что указывали при регистрации в клинике)\n\n"
            . "Например: <code>+998901234567</code>\n\n"
            . "Или введите код вручную в CRM → Telegram Bot",
            'HTML');
    }
    exit;
}

// /help
if ($text === '/help') {
    tgSend($chat_id, "📋 <b>Команды бота:</b>\n\n"
        . "ЧЕК ID {номер} — получить PDF чек\n"
        . "/start — главное меню\n"
        . "/help — эта справка\n\n"
        . "Для привязки аккаунта обратитесь в клинику.",
        'HTML');
    exit;
}

// ==================== ROLE-BASED HANDLING ====================

$user = detectUserRole($chat_id, $pdo);

if (!$user) {
    // Try auto-link by phone number
    $phone = preg_replace('/[^0-9+]/', '', $text);
    if (strlen($phone) >= 9) {
        // Normalize: remove +, leading 8 → 998
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone_clean, 0, 1) === '8') {
            $phone_clean = '998' . substr($phone_clean, 1);
        }
        if (substr($phone_clean, 0, 2) === '99' && strlen($phone_clean) === 12) {
            $phone_fmt = '+' . $phone_clean;
        } elseif (strlen($phone_clean) === 9) {
            $phone_fmt = '+998' . $phone_clean;
        } else {
            $phone_fmt = '+' . $phone_clean;
        }

        // Search patient by phone (try multiple formats)
        $stmt = $pdo->prepare("SELECT id, full_name, phone FROM patients WHERE phone = ? AND deleted_at IS NULL");
        $stmt->execute([$phone_fmt]);
        $patient = $stmt->fetch();

        if (!$patient) {
            // Try without +
            $stmt->execute([ltrim($phone_fmt, '+')]);
            $patient = $stmt->fetch();
        }
        if (!$patient) {
            // Try with 8 instead of 998
            $alt = '8' . substr($phone_fmt, 4);
            $stmt->execute([$alt]);
            $patient = $stmt->fetch();
        }

        if ($patient) {
            // Check if already linked to another chat
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE telegram_id = ? AND id != ?");
            $stmt->execute([$chat_id, $patient['id']]);
            if ($stmt->fetch()) {
                tgSend($chat_id, "❌ Этот номер уже привязан к другому аккаунту Telegram.\n"
                    . "Обратитесь в клинику для перепривязки.");
                exit;
            }

            // Link!
            $pdo->prepare("UPDATE patients SET telegram_id = ? WHERE id = ?")->execute([$chat_id, $patient['id']]);

            $keyboard = [
                ['📋 Мои чеки', '📊 Мои анализы'],
                ['ℹ️ Помощь'],
            ];
            $markup = json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false]);
            tgSend($chat_id, "✅ Аккаунт привязан!\n\n"
                . "👋 Здравствуйте, <b>{$patient['full_name']}</b>!\n\n"
                . "Выберите действие:", 'HTML', $markup);
            exit;
        } else {
            tgSend($chat_id, "❌ Пациент с номером <code>" . h($phone_fmt) . "</code> не найден.\n\n"
                . "Проверьте номер или обратитесь в клинику.", 'HTML');
            exit;
        }
    }

    // Not a phone — show link instructions
    $link_code = generateLinkCode($chat_id, $pdo);
    tgSend($chat_id, "❌ Аккаунт не привязан.\n\n"
        . "Отправьте свой <b>номер телефона</b> для привязки:\n"
        . "(тот же, что указывали при регистрации)\n\n"
        . "Например: <code>+998901234567</code>\n\n"
        . "Или введите код в CRM → Telegram Bot: <code>{$link_code}</code>",
        'HTML');
    exit;
}

// === ADMIN ===
if ($user['role'] === 'admin') {
    if ($text === '📊 Отчёт за сегодня' || $text === '/report') {
        sendDailyReport($pdo);
        exit;
    }
    if ($text === '💾 Бэкап БД' || $text === '/backup') {
        tgSend($chat_id, "⏳ Создаю бэкап...");
        backupDatabase($pdo);
        exit;
    }
    tgSend($chat_id, "Неизвестная команда. Нажмите кнопку меню или отправьте /help");
    exit;
}

// === DOCTOR ===
if ($user['role'] === 'doctor') {
    if ($text === '📋 Мои сегодняшние приёмы' || $text === '/today') {
        $stmt = $pdo->prepare("
            SELECT a.appointment_time, p.full_name as patient, s.name as service, a.status
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN services s ON a.service_id = s.id
            WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
            ORDER BY a.appointment_time ASC
        ");
        $stmt->execute([$user['data']['id']]);
        $appointments = $stmt->fetchAll();

        if (empty($appointments)) {
            tgSend($chat_id, "📋 Сегодня приёмов нет.");
        } else {
            $text_msg = "📋 <b>Приёмы на сегодня:</b>\n\n";
            foreach ($appointments as $a) {
                $status_icon = $a['status'] === 'completed' ? '✅' : ($a['status'] === 'cancelled' ? '❌' : '🕐');
                $text_msg .= $status_icon . " " . substr($a['appointment_time'], 0, 5) . " — " . $a['patient'] . "\n";
                $text_msg .= "   📋 " . $a['service'] . "\n\n";
            }
            tgSend($chat_id, $text_msg);
        }
        exit;
    }
    tgSend($chat_id, "Отправьте /today для просмотра приёмов на сегодня");
    exit;
}

// ==================== PATIENT ===
if ($user['role'] === 'patient') {
    // ЧЕК ID command
    if (preg_match('/^(?:ЧЕК\s+ID|receipt)\s+(.+)$/iu', $text, $m)) {
        $receipt_id = trim($m[1]);
        $pdf_path = generateReceiptPdf($receipt_id, $pdo);
        if ($pdf_path) {
            tgSendDocument($chat_id, $pdf_path, "📄 Чек #$receipt_id");
            unlink($pdf_path);
        } else {
            tgSend($chat_id, "❌ Чек #$receipt_id не найден. Проверьте номер.");
        }
        exit;
    }

    // List patient's receipts
    if ($text === '📋 Мои чеки' || $text === '/receipts') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT a.receipt_id, a.appointment_date, 
                   SUM(s.price * COALESCE(a.quantity, 1)) as total
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            WHERE a.patient_id = ? AND a.receipt_id IS NOT NULL
            GROUP BY a.receipt_id
            ORDER BY a.appointment_date DESC
            LIMIT 10
        ");
        $stmt->execute([$user['data']['id']]);
        $receipts = $stmt->fetchAll();

        if (empty($receipts)) {
            tgSend($chat_id, "📋 У вас пока нет чеков.");
        } else {
            $keyboard = [];
            foreach ($receipts as $r) {
                $keyboard[] = [['text' => '📄 ' . $r['receipt_id'] . ' — ' . number_format($r['total'], 0, '.', ' ') . ' сум', 'callback_data' => 'receipt:' . $r['receipt_id']]];
            }
            $markup = json_encode(['inline_keyboard' => $keyboard]);
            tgSend($chat_id, "📋 <b>Ваши последние чеки:</b>\n\nНажмите на чек для скачивания PDF:", 'HTML', $markup);
        }
        exit;
    }

    // List patient's lab results
    if ($text === '📊 Мои анализы' || $text === '/results') {
        $stmt = $pdo->prepare("
            SELECT lr.id, lr.created_at, lt.title
            FROM lab_results lr
            JOIN lab_templates lt ON lr.template_id = lt.id
            WHERE lr.patient_id = ?
            ORDER BY lr.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user['data']['id']]);
        $results = $stmt->fetchAll();

        if (empty($results)) {
            tgSend($chat_id, "📊 У вас пока нет результатов анализов.");
        } else {
            $keyboard = [];
            foreach ($results as $r) {
                $date = date('d.m.Y', strtotime($r['created_at']));
                $keyboard[] = [['text' => '📊 ' . $r['title'] . ' (' . $date . ')', 'callback_data' => 'labresult:' . $r['id']]];
            }
            $markup = json_encode(['inline_keyboard' => $keyboard]);
            tgSend($chat_id, "📊 <b>Ваши результаты анализов:</b>\n\nНажмите для просмотра:", 'HTML', $markup);
        }
        exit;
    }

    // Default: show patient menu
    $keyboard = [
        ['📋 Мои чеки', '📊 Мои анализы'],
        ['ℹ️ Помощь'],
    ];
    $markup = json_encode(['keyboard' => $keyboard, 'resize_keyboard' => true, 'one_time_keyboard' => false]);
    tgSend($chat_id, "👋 Здравствуйте, <b>{$user['data']['full_name']}</b>!\n\n"
        . "Выберите действие:", 'HTML', $markup);
    exit;
}
