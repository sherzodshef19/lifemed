<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
require_once '../config/config.php';
check_role(['admin']);

$error = '';
$success = '';

// Fetch all settings
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Current bot token & admin chat from config constants (fallback)
$bot_token = $settings['TG_BOT_TOKEN'] ?? TG_BOT_TOKEN;
$admin_chat = $settings['TG_ADMIN_CHAT_ID'] ?? TG_ADMIN_CHAT_ID;

// Auto-detect webhook URL (works behind Cloudflare/reverse proxy)
$scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false)
    ? 'https' : 'https'; // Default to HTTPS for production
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'your-domain.com';
$webhook_url = $scheme . '://' . $host . '/api/telegram_bot.php';

// Fetch linked accounts
$linked = [];
foreach (['users' => 'Админ', 'doctors' => 'Врач', 'patients' => 'Пациент'] as $table => $label) {
    if ($table === 'users') {
        $stmt = $pdo->prepare("SELECT id, full_name, telegram_id, role FROM $table WHERE telegram_id IS NOT NULL AND telegram_id != ''");
        $stmt->execute();
    } elseif ($table === 'doctors') {
        $stmt = $pdo->prepare("SELECT id, full_name, telegram_id FROM $table WHERE telegram_id IS NOT NULL AND telegram_id != ''");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, telegram_id FROM $table WHERE telegram_id IS NOT NULL AND telegram_id != ''");
        $stmt->execute();
    }
    foreach ($stmt->fetchAll() as $row) {
        $row['type_label'] = $label;
        $row['type_key'] = $table;
        $linked[] = $row;
    }
}

// Count logs & blacklist
$logCount = 0;
$blacklistCount = 0;
try {
    $logCount = (int)$pdo->query("SELECT COUNT(*) FROM telegram_logs")->fetchColumn();
} catch (Exception $e) { /* table may not exist */ }
try {
    $blacklistCount = (int)$pdo->query("SELECT COUNT(*) FROM telegram_blacklist")->fetchColumn();
} catch (Exception $e) { /* table may not exist */ }

$page_title = 'Настройки Telegram';
include '../includes/header.php';
?>

<style>
    .nav-pills .nav-link.active { background-color: #0d6efd; }
    .log-entry { font-size: 0.8rem; border-left: 3px solid #dee2e6; padding-left: 8px; margin-bottom: 6px; }
    .log-entry.incoming { border-left-color: #198754; }
    .log-entry.outgoing { border-left-color: #0d6efd; }
    .template-textarea { font-family: monospace; font-size: 0.85rem; min-height: 80px; }
    .badge-count { font-size: 0.7rem; }
</style>

<div class="row g-4">
    <!-- LEFT COLUMN -->
    <div class="col-lg-7">
        <!-- Bot Token & Admin -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center">
                <i class="fab fa-telegram text-info me-2 fs-5"></i>
                <h6 class="fw-bold mb-0">Конфигурация бота</h6>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show"><?= h($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show"><?= h($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label fw-medium">Bot Token</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-key text-secondary"></i></span>
                        <input type="password" id="tg_token" class="form-control font-monospace"
                               value="<?= htmlspecialchars($bot_token) ?>"
                               placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz">
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleToken()">
                            <i class="fas fa-eye" id="tokenEye"></i>
                        </button>
                    </div>
                    <div class="form-text">Получите у <a href="https://t.me/BotFather" target="_blank">@BotFather</a></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">Chat ID начальника</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-user-shield text-secondary"></i></span>
                        <input type="text" id="tg_admin_chat" class="form-control"
                               value="<?= htmlspecialchars($admin_chat) ?>"
                               placeholder="Например: 1783400289">
                    </div>
                    <div class="form-text">Узнать: откройте @userinfobot в Telegram → <code>/start</code></div>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="saveBotConfig()">
                        <i class="fas fa-save me-1"></i> Сохранить
                    </button>
                    <button class="btn btn-outline-info" onclick="testMessage()">
                        <i class="fas fa-paper-plane me-1"></i> Тестовое сообщение
                    </button>
                </div>
                <div id="bot_config_status" class="mt-2"></div>
            </div>
        </div>

        <!-- Webhook -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center">
                <i class="fas fa-link text-primary me-2 fs-5"></i>
                <h6 class="fw-bold mb-0">Webhook</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-medium">URL вебхука</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fas fa-globe text-secondary"></i></span>
                        <input type="text" id="tg_webhook_url" class="form-control font-monospace"
                               value="<?= htmlspecialchars($webhook_url) ?>" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyWebhook()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap mb-3">
                    <button class="btn btn-success btn-sm" onclick="setWebhook()">
                        <i class="fas fa-plug me-1"></i> Установить
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteWebhook()">
                        <i class="fas fa-unlink me-1"></i> Удалить
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="getWebhookInfo()">
                        <i class="fas fa-info-circle me-1"></i> Статус
                    </button>
                </div>

                <div id="webhook_status" class="p-3 rounded bg-light small" style="display:none;"></div>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center">
                <i class="fas fa-bell text-warning me-2 fs-5"></i>
                <h6 class="fw-bold mb-0">Уведомления</h6>
            </div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notify_new_service"
                           <?= ($settings['TG_NOTIFY_NEW_SERVICE'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="notify_new_service">
                        Новая услуга → начальнику
                    </label>
                    <div class="form-text">При оформлении приёма/услуги</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notify_doctor_appt"
                           <?= ($settings['TG_NOTIFY_DOCTOR'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="notify_doctor_appt">
                        Новая запись → врачу
                    </label>
                    <div class="form-text">Если у врача привязан Telegram</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="daily_report"
                           <?= ($settings['TG_DAILY_REPORT'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="daily_report">
                        Ежедневный отчёт → начальнику
                    </label>
                    <div class="form-text">Автоматически в конце дня</div>
                </div>
                <hr>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notify_new_patient"
                           <?= ($settings['TG_NOTIFY_NEW_PATIENT'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="notify_new_patient">
                        Новый пациент → начальнику
                    </label>
                    <div class="form-text">При регистрации нового пациента</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notify_payment"
                           <?= ($settings['TG_NOTIFY_PAYMENT'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="notify_payment">
                        Оплата → начальнику
                    </label>
                    <div class="form-text">При получении оплаты</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notify_cancel"
                           <?= ($settings['TG_NOTIFY_CANCEL'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="notify_cancel">
                        Отмена записи → начальнику
                    </label>
                    <div class="form-text">При отмене приёма</div>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="notify_schedule"
                           <?= ($settings['TG_NOTIFY_SCHEDULE'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="notify_schedule">
                        Изменение расписания → начальнику
                    </label>
                    <div class="form-text">При изменении рабочих часов врача</div>
                </div>
                <button class="btn btn-primary btn-sm" onclick="saveNotifications()">
                    <i class="fas fa-save me-1"></i> Сохранить уведомления
                </button>
                <div id="notif_status" class="mt-2"></div>
            </div>
        </div>

        <!-- Message Templates -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between" role="button" data-bs-toggle="collapse" data-bs-target="#templatesCollapse">
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-alt text-secondary me-2 fs-5"></i>
                    <h6 class="fw-bold mb-0">Шаблоны сообщений</h6>
                </div>
                <i class="fas fa-chevron-down text-secondary"></i>
            </div>
            <div class="collapse" id="templatesCollapse">
                <div class="card-body">
                    <div class="alert alert-info small mb-3">
                        Доступные переменные: <code>{service}</code> <code>{patient}</code> <code>{doctor}</code> <code>{price}</code> <code>{receipt_id}</code> <code>{date}</code> <code>{time}</code> <code>{total}</code> <code>{count}</code> <code>{name}</code> <code>{phone}</code> <code>{amount}</code> <code>{details}</code>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Новая услуга (начальнику)</label>
                        <textarea id="tpl_new_service" class="form-control template-textarea"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Запись врачу</label>
                        <textarea id="tpl_doctor_notify" class="form-control template-textarea"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Ежедневный отчёт</label>
                        <textarea id="tpl_daily_report" class="form-control template-textarea"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Новый пациент</label>
                        <textarea id="tpl_new_patient" class="form-control template-textarea"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Оплата</label>
                        <textarea id="tpl_payment" class="form-control template-textarea"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Отмена записи</label>
                        <textarea id="tpl_cancel" class="form-control template-textarea"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Изменение расписания</label>
                        <textarea id="tpl_schedule" class="form-control template-textarea"></textarea>
                    </div>
                    <button class="btn btn-primary btn-sm" onclick="saveTemplates()">
                        <i class="fas fa-save me-1"></i> Сохранить шаблоны
                    </button>
                    <div id="template_status" class="mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Test Notifications by Type -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center">
                <i class="fas fa-flask text-success me-2 fs-5"></i>
                <h6 class="fw-bold mb-0">Тест уведомлений</h6>
            </div>
            <div class="card-body">
                <p class="text-secondary small mb-3">Отправьте тестовое уведомление конкретного типа на указанный Chat ID.</p>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Chat ID получателя</label>
                        <input type="text" id="test_chat_id" class="form-control" placeholder="1783400289" value="<?= htmlspecialchars($admin_chat) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-medium">Тип уведомления</label>
                        <select id="test_notif_type" class="form-select">
                            <option value="new_service">Новая услуга</option>
                            <option value="doctor">Запись врачу</option>
                            <option value="daily_report">Ежедневный отчёт</option>
                            <option value="new_patient">Новый пациент</option>
                            <option value="payment">Оплата</option>
                            <option value="cancel">Отмена записи</option>
                            <option value="schedule">Изменение расписания</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-success btn-sm" onclick="testNotification()">
                    <i class="fas fa-paper-plane me-1"></i> Отправить тест
                </button>
                <div id="test_notif_status" class="mt-2"></div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="col-lg-5">
        <!-- Link Account -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center">
                <i class="fas fa-user-plus text-success me-2 fs-5"></i>
                <h6 class="fw-bold mb-0">Привязка аккаунта</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small fw-medium text-secondary">Тип аккаунта</label>
                    <select id="tg_link_type" class="form-select">
                        <option value="user">Администратор</option>
                        <option value="doctor">Врач</option>
                        <option value="patient">Пациент</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium text-secondary">ID записи в БД</label>
                    <input type="number" id="tg_link_entity_id" class="form-control" placeholder="Например: 1">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium text-secondary">Telegram Chat ID</label>
                    <input type="text" id="tg_link_chat_id" class="form-control" placeholder="Например: 1783400289">
                </div>
                <button class="btn btn-success w-100" onclick="linkTelegram()">
                    <i class="fas fa-link me-1"></i> Привязать
                </button>
                <div id="tg_link_status" class="mt-2"></div>
            </div>
        </div>

        <!-- CSV Import -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between" role="button" data-bs-toggle="collapse" data-bs-target="#csvCollapse">
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-csv text-info me-2 fs-5"></i>
                    <h6 class="fw-bold mb-0">Массовая привязка (CSV)</h6>
                </div>
                <i class="fas fa-chevron-down text-secondary"></i>
            </div>
            <div class="collapse" id="csvCollapse">
                <div class="card-body">
                    <p class="text-secondary small mb-2">Формат: <code>entity_id,chat_id</code> (каждая строка — одна привязка)</p>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Тип</label>
                        <select id="csv_link_type" class="form-select form-select-sm">
                            <option value="patient">Пациент</option>
                            <option value="doctor">Врач</option>
                            <option value="user">Администратор</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <textarea id="csv_data" class="form-control font-monospace" rows="5" placeholder="1,1783400289&#10;2,1234567890&#10;3,9876543210"></textarea>
                    </div>
                    <button class="btn btn-info btn-sm" onclick="csvImport()">
                        <i class="fas fa-upload me-1"></i> Импортировать
                    </button>
                    <div id="csv_status" class="mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Linked Accounts List -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="fas fa-list text-secondary me-2 fs-5"></i>
                    <h6 class="fw-bold mb-0">Привязанные аккаунты</h6>
                </div>
                <span class="badge bg-info rounded-pill"><?= count($linked) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($linked)): ?>
                    <div class="p-4 text-center text-secondary">
                        <i class="fas fa-user-slash fa-2x mb-2 d-block"></i>
                        <small>Нет привязанных аккаунтов</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height:300px;overflow-y:auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-secondary small" style="position:sticky;top:0;">
                                <tr>
                                    <th>Тип</th>
                                    <th>Имя</th>
                                    <th>Chat ID</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($linked as $acc): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark"><?= h($acc['type_label']) ?></span></td>
                                    <td class="small"><?= h($acc['full_name']) ?> <span class="text-muted">(ID:<?= $acc['id'] ?>)</span></td>
                                    <td class="font-monospace small"><?= h($acc['telegram_id']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger" onclick="unlinkAccount('<?= $acc['type_key'] ?>', <?= $acc['id'] ?>)" title="Отвязать">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Blacklist -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between" role="button" data-bs-toggle="collapse" data-bs-target="#blacklistCollapse">
                <div class="d-flex align-items-center">
                    <i class="fas fa-ban text-danger me-2 fs-5"></i>
                    <h6 class="fw-bold mb-0">Чёрный список</h6>
                </div>
                <span class="badge bg-danger rounded-pill badge-count"><?= $blacklistCount ?></span>
            </div>
            <div class="collapse" id="blacklistCollapse">
                <div class="card-body">
                    <div class="input-group input-group-sm mb-3">
                        <input type="text" id="bl_chat_id" class="form-control" placeholder="Chat ID">
                        <input type="text" id="bl_reason" class="form-control" placeholder="Причина (необязательно)">
                        <button class="btn btn-danger" onclick="blacklistAdd()">
                            <i class="fas fa-ban me-1"></i> Заблокировать
                        </button>
                    </div>
                    <div id="bl_list" class="small" style="max-height:200px;overflow-y:auto;">
                        <div class="text-center text-secondary py-2"><i class="fas fa-spinner fa-spin"></i></div>
                    </div>
                    <div id="bl_status" class="mt-2"></div>
                </div>
            </div>
        </div>

        <!-- Bot Logs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center justify-content-between" role="button" data-bs-toggle="collapse" data-bs-target="#logsCollapse">
                <div class="d-flex align-items-center">
                    <i class="fas fa-history text-secondary me-2 fs-5"></i>
                    <h6 class="fw-bold mb-0">Логи бота</h6>
                </div>
                <span class="badge bg-secondary rounded-pill badge-count"><?= $logCount ?></span>
            </div>
            <div class="collapse" id="logsCollapse">
                <div class="card-body">
                    <div class="d-flex gap-2 mb-3">
                        <select id="log_filter" class="form-select form-select-sm" style="width:auto;">
                            <option value="">Все</option>
                            <option value="incoming">Входящие</option>
                            <option value="outgoing">Исходящие</option>
                        </select>
                        <button class="btn btn-sm btn-outline-secondary" onclick="loadLogs()">
                            <i class="fas fa-sync"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="clearLogs()">
                            <i class="fas fa-trash"></i> Очистить
                        </button>
                    </div>
                    <div id="logs_container" class="small" style="max-height:350px;overflow-y:auto;">
                        <div class="text-center text-secondary py-2"><i class="fas fa-spinner fa-spin"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bot Status Card -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0 d-flex align-items-center">
                <i class="fas fa-heartbeat text-danger me-2 fs-5"></i>
                <h6 class="fw-bold mb-0">Статус бота</h6>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <span class="me-2" id="bot_status_dot" style="width:12px;height:12px;border-radius:50%;background:#ccc;"></span>
                    <span id="bot_status_text" class="small text-secondary">Проверка...</span>
                </div>
                <button class="btn btn-sm btn-outline-secondary" onclick="checkBotStatus()">
                    <i class="fas fa-sync me-1"></i> Обновить статус
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const API_URL = '../api/telegram_api.php';
const CSRF_TOKEN = '<?= csrf_token() ?>';

async function apiPost(data) {
    data._csrf_token = CSRF_TOKEN;
    const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify(data)
    });
    return res.json();
}

// Toggle token visibility
function toggleToken() {
    const inp = document.getElementById('tg_token');
    const eye = document.getElementById('tokenEye');
    if (inp.type === 'password') {
        inp.type = 'text';
        eye.className = 'fas fa-eye-slash';
    } else {
        inp.type = 'password';
        eye.className = 'fas fa-eye';
    }
}

// Save bot token + admin chat
async function saveBotConfig() {
    const token = document.getElementById('tg_token').value.trim();
    const adminChat = document.getElementById('tg_admin_chat').value.trim();
    const status = document.getElementById('bot_config_status');
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Сохранение...</span>';
    const data = await apiPost({ action: 'save_config', token, admin_chat_id: adminChat });
    if (data.success) {
        status.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> Сохранено</span>';
        showToast('Настройки бота сохранены', 'success');
    } else {
        status.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
    }
}

// Test message
async function testMessage() {
    const status = document.getElementById('bot_config_status');
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Отправка...</span>';
    const data = await apiPost({ action: 'test_message' });
    if (data.success) {
        status.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> Тестовое сообщение отправлено!</span>';
    } else {
        status.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
    }
}

// Webhook
async function setWebhook() {
    const status = document.getElementById('webhook_status');
    status.style.display = 'block';
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Установка...</span>';
    const data = await apiPost({ action: 'set_webhook' });
    if (data.success) {
        status.innerHTML = '<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> Webhook установлен!</span>';
        if (data.details) status.innerHTML += '<pre class="mt-2 mb-0 small">' + JSON.stringify(data.details, null, 2) + '</pre>';
    } else {
        status.innerHTML = '<span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
    }
}

async function deleteWebhook() {
    if (!confirm('Удалить webhook?')) return;
    const status = document.getElementById('webhook_status');
    status.style.display = 'block';
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Удаление...</span>';
    const data = await apiPost({ action: 'delete_webhook' });
    status.innerHTML = data.success
        ? '<span class="text-warning fw-bold"><i class="fas fa-check-circle me-1"></i> Webhook удалён</span>'
        : '<span class="text-danger fw-bold"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
}

async function getWebhookInfo() {
    const status = document.getElementById('webhook_status');
    status.style.display = 'block';
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Проверка...</span>';
    const data = await apiPost({ action: 'get_webhook_info' });
    if (data.success && data.info) {
        const i = data.info;
        let html = '<div class="small">';
        html += '<div><strong>URL:</strong> ' + (i.url || 'Не установлен') + '</div>';
        html += '<div><strong>Pending updates:</strong> ' + (i.pending_update_count || 0) + '</div>';
        if (i.last_error_date) html += '<div class="text-danger"><strong>Error:</strong> ' + i.last_error_message + '</div>';
        html += '</div>';
        status.innerHTML = html;
    } else {
        status.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
    }
}

function copyWebhook() {
    navigator.clipboard.writeText(document.getElementById('tg_webhook_url').value);
    showToast('URL скопирован', 'info');
}

// Save notification toggles
async function saveNotifications() {
    const status = document.getElementById('notif_status');
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Сохранение...</span>';
    const data = await apiPost({
        action: 'save_notifications',
        notify_new_service: document.getElementById('notify_new_service').checked ? '1' : '0',
        notify_doctor: document.getElementById('notify_doctor_appt').checked ? '1' : '0',
        daily_report: document.getElementById('daily_report').checked ? '1' : '0',
        notify_new_patient: document.getElementById('notify_new_patient').checked ? '1' : '0',
        notify_payment: document.getElementById('notify_payment').checked ? '1' : '0',
        notify_cancel: document.getElementById('notify_cancel').checked ? '1' : '0',
        notify_schedule: document.getElementById('notify_schedule').checked ? '1' : '0',
    });
    status.innerHTML = data.success
        ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i> Сохранено</span>'
        : '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
}

// Link Telegram
async function linkTelegram() {
    const type = document.getElementById('tg_link_type').value;
    const entityId = document.getElementById('tg_link_entity_id').value.trim();
    const chatId = document.getElementById('tg_link_chat_id').value.trim();
    const status = document.getElementById('tg_link_status');
    if (!entityId || !chatId) { showToast('Заполните все поля', 'warning'); return; }
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Привязка...</span>';
    const data = await apiPost({ action: 'link_telegram', entity_type: type, entity_id: entityId, chat_id: chatId });
    if (data.success) {
        status.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> Привязано!</span>';
        setTimeout(() => location.reload(), 1000);
    } else {
        status.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
    }
}

// Unlink
async function unlinkAccount(type, entityId) {
    if (!confirm('Отвязать аккаунт?')) return;
    const data = await apiPost({ action: 'unlink_telegram', entity_type: type, entity_id: entityId });
    if (data.success) { showToast('Аккаунт отвязан', 'success'); setTimeout(() => location.reload(), 800); }
    else { showToast(data.error || 'Ошибка', 'error'); }
}

// Bot status
async function checkBotStatus() {
    const dot = document.getElementById('bot_status_dot');
    const text = document.getElementById('bot_status_text');
    dot.style.background = '#ffc107';
    text.textContent = 'Проверка...';
    const data = await apiPost({ action: 'get_webhook_info' });
    if (data.success && data.info && data.info.url) {
        dot.style.background = '#10b981';
        text.textContent = 'Активен: ' + data.info.url;
    } else if (data.success) {
        dot.style.background = '#f59e0b';
        text.textContent = 'Webhook не установлен';
    } else {
        dot.style.background = '#ef4444';
        text.textContent = 'Ошибка: ' + (data.error || 'Не удалось');
    }
}

// ============ TEMPLATES ============
async function loadTemplates() {
    const data = await apiPost({ action: 'get_templates' });
    if (data.success && data.templates) {
        const map = {
            'TG_TPL_NEW_SERVICE': 'tpl_new_service',
            'TG_TPL_DOCTOR_NOTIFY': 'tpl_doctor_notify',
            'TG_TPL_DAILY_REPORT': 'tpl_daily_report',
            'TG_TPL_NEW_PATIENT': 'tpl_new_patient',
            'TG_TPL_PAYMENT': 'tpl_payment',
            'TG_TPL_CANCEL': 'tpl_cancel',
            'TG_TPL_SCHEDULE': 'tpl_schedule',
        };
        for (const [key, id] of Object.entries(map)) {
            const el = document.getElementById(id);
            if (el && data.templates[key]) el.value = data.templates[key];
        }
    }
}

async function saveTemplates() {
    const status = document.getElementById('template_status');
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Сохранение...</span>';
    const templates = {
        TG_TPL_NEW_SERVICE: document.getElementById('tpl_new_service').value,
        TG_TPL_DOCTOR_NOTIFY: document.getElementById('tpl_doctor_notify').value,
        TG_TPL_DAILY_REPORT: document.getElementById('tpl_daily_report').value,
        TG_TPL_NEW_PATIENT: document.getElementById('tpl_new_patient').value,
        TG_TPL_PAYMENT: document.getElementById('tpl_payment').value,
        TG_TPL_CANCEL: document.getElementById('tpl_cancel').value,
        TG_TPL_SCHEDULE: document.getElementById('tpl_schedule').value,
    };
    const data = await apiPost({ action: 'save_templates', templates });
    status.innerHTML = data.success
        ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i> Сохранено</span>'
        : '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
}

// ============ TEST NOTIFICATION ============
async function testNotification() {
    const status = document.getElementById('test_notif_status');
    const chatId = document.getElementById('test_chat_id').value.trim();
    const type = document.getElementById('test_notif_type').value;
    if (!chatId) { showToast('Укажите Chat ID', 'warning'); return; }
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Отправка...</span>';
    const data = await apiPost({ action: 'test_notification', chat_id: chatId, notif_type: type });
    status.innerHTML = data.success
        ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i> Тест отправлен!</span>'
        : '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
}

// ============ CSV IMPORT ============
async function csvImport() {
    const status = document.getElementById('csv_status');
    const csvData = document.getElementById('csv_data').value.trim();
    const type = document.getElementById('csv_link_type').value;
    if (!csvData) { showToast('Вставьте данные CSV', 'warning'); return; }
    status.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Импорт...</span>';
    const data = await apiPost({ action: 'csv_import', csv_data: csvData, entity_type: type });
    if (data.success) {
        let msg = 'Привязано: ' + data.linked;
        if (data.errors && data.errors.length) msg += '. Ошибки: ' + data.errors.join('; ');
        status.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> ' + msg + '</span>';
        if (data.linked > 0) setTimeout(() => location.reload(), 1500);
    } else {
        status.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
    }
}

// ============ BLACKLIST ============
async function loadBlacklist() {
    const data = await apiPost({ action: 'blacklist_list' });
    const container = document.getElementById('bl_list');
    if (!data.success || !data.list || data.list.length === 0) {
        container.innerHTML = '<div class="text-center text-secondary py-2">Нет заблокированных</div>';
        return;
    }
    let html = '<table class="table table-sm table-hover mb-0"><thead><tr><th>Chat ID</th><th>Причина</th><th></th></tr></thead><tbody>';
    for (const item of data.list) {
        html += '<tr><td class="font-monospace">' + item.chat_id + '</td><td>' + (item.reason || '—') + '</td>';
        html += '<td><button class="btn btn-sm btn-outline-success py-0" onclick="blacklistRemove(\'' + item.chat_id + '\')" title="Разблокировать"><i class="fas fa-check"></i></button></td></tr>';
    }
    html += '</tbody></table>';
    container.innerHTML = html;
}

async function blacklistAdd() {
    const chatId = document.getElementById('bl_chat_id').value.trim();
    const reason = document.getElementById('bl_reason').value.trim();
    const status = document.getElementById('bl_status');
    if (!chatId) { showToast('Укажите Chat ID', 'warning'); return; }
    const data = await apiPost({ action: 'blacklist_add', chat_id: chatId, reason: reason });
    if (data.success) {
        status.innerHTML = '<span class="text-success small"><i class="fas fa-check me-1"></i> Заблокирован</span>';
        document.getElementById('bl_chat_id').value = '';
        document.getElementById('bl_reason').value = '';
        loadBlacklist();
    } else {
        status.innerHTML = '<span class="text-danger small"><i class="fas fa-exclamation-circle me-1"></i> ' + (data.error || 'Ошибка') + '</span>';
    }
}

async function blacklistRemove(chatId) {
    const data = await apiPost({ action: 'blacklist_remove', chat_id: chatId });
    if (data.success) { loadBlacklist(); showToast('Разблокирован', 'success'); }
}

// ============ LOGS ============
async function loadLogs() {
    const filter = document.getElementById('log_filter').value;
    const container = document.getElementById('logs_container');
    container.innerHTML = '<div class="text-center text-secondary py-2"><i class="fas fa-spinner fa-spin"></i></div>';
    const data = await apiPost({ action: 'get_logs', limit: 50, direction: filter });
    if (!data.success || !data.logs || data.logs.length === 0) {
        container.innerHTML = '<div class="text-center text-secondary py-2">Нет логов</div>';
        return;
    }
    let html = '';
    for (const log of data.logs) {
        const cls = log.direction === 'incoming' ? 'incoming' : 'outgoing';
        const icon = log.direction === 'incoming' ? 'fa-arrow-down text-success' : 'fa-arrow-up text-primary';
        const time = log.created_at ? log.created_at.substring(11, 19) : '';
        const text = (log.message_text || '').substring(0, 80);
        const status = log.response_status || '';
        const errClass = status === 'ok' ? 'text-success' : (status ? 'text-danger' : '');
        html += '<div class="log-entry ' + cls + '">';
        html += '<i class="fas ' + icon + ' me-1"></i> ';
        html += '<strong>' + log.chat_id + '</strong> ';
        html += '<span class="text-muted">' + time + '</span> ';
        html += '<span class="' + errClass + '">' + status + '</span>';
        if (text) html += '<br><span class="text-muted">' + text.replace(/</g, '&lt;') + '</span>';
        html += '</div>';
    }
    container.innerHTML = html;
}

async function clearLogs() {
    if (!confirm('Очистить все логи?')) return;
    const data = await apiPost({ action: 'clear_logs' });
    if (data.success) { loadLogs(); showToast('Логи очищены', 'success'); }
}

// Auto-load on collapse show
document.addEventListener('DOMContentLoaded', () => {
    checkBotStatus();
    loadTemplates();

    document.getElementById('blacklistCollapse').addEventListener('show.bs.collapse', loadBlacklist);
    document.getElementById('logsCollapse').addEventListener('show.bs.collapse', loadLogs);
});
</script>

<?php include '../includes/footer.php'; ?>
