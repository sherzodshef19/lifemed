<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!csrf_verify()) {
        $error = 'CSRF token mismatch. Попробуйте ещё раз.';
    } else if (isset($_POST['update_settings'])) {
        $clinic_name = $_POST['clinic_name'];
        $clinic_phone = $_POST['clinic_phone'];
        $telegram = $_POST['telegram'] ?? '';
        $instagram = $_POST['instagram'] ?? '';
        $social_links = json_encode(['telegram' => $telegram, 'instagram' => $instagram]);

        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('clinic_name', ?)")->execute([$clinic_name]);
        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('clinic_phone', ?)")->execute([$clinic_phone]);
        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('printer_ip', ?)")->execute([$_POST['printer_ip'] ?? '']);
        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('clinic_address', ?)")->execute([$_POST['clinic_address'] ?? '']);
        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('qr_content', ?)")->execute([$_POST['qr_content'] ?? '']);
        $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('social_links', ?)")->execute([$social_links]);

        // Remove logo
        if (($_POST['remove_logo'] ?? '0') === '1' && !empty($settings['clinic_logo'])) {
            $logo_path = '../assets/img/' . $settings['clinic_logo'];
            if (file_exists($logo_path)) unlink($logo_path);
            $pdo->prepare("DELETE FROM settings WHERE setting_key = 'clinic_logo'")->execute();
            unset($settings['clinic_logo']);
        }

        if (isset($_FILES['clinic_logo']) && $_FILES['clinic_logo']['error'] == 0) {
            require_once __DIR__ . '/../includes/helpers.php';
            $validation = validate_upload($_FILES['clinic_logo'], ['jpg', 'jpeg', 'png', 'svg', 'webp'], 2097152);
            if ($validation['valid']) {
                $logo_name = 'logo_' . time() . '.' . $validation['ext'];
                $upload_path = '../assets/img/' . $logo_name;
                if (move_uploaded_file($_FILES['clinic_logo']['tmp_name'], $upload_path)) {
                    $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('clinic_logo', ?)")->execute([$logo_name]);
                    $settings['clinic_logo'] = $logo_name;
                }
            } else {
                $error = $validation['error'];
            }
        }
        $success = 'Настройки сохранены';
    }
}

// Fetch current settings
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$social = json_decode($settings['social_links'] ?? '{}', true);

$page_title = 'Настройки клиники';
include '../includes/header.php';
?>

<div class="row">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Общие настройки</h6>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= h($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label class="form-label text-secondary small">Логотип клиники</label>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <?php if (!empty($settings['clinic_logo'])): ?>
                                <div class="position-relative">
                                    <img src="../assets/img/<?= htmlspecialchars($settings['clinic_logo']) ?>" alt="Logo" class="img-thumbnail" style="max-height: 80px;">
                                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 20px; height: 20px; font-size: 10px; transform: translate(30%, -30%);" onclick="removeLogo()" title="Удалить логотип">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center border rounded" style="width: 80px; height: 80px;">
                                    <i class="fas fa-image text-secondary"></i>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="clinic_logo" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small">Название клиники</label>
                        <input type="text" name="clinic_name" class="form-control" value="<?= htmlspecialchars($settings['clinic_name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small">Телефон клиники (для чека)</label>
                        <input type="text" name="clinic_phone" class="form-control" placeholder="+998 90 123-45-67" value="<?= htmlspecialchars($settings['clinic_phone'] ?? '') ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small">Адрес клиники (для чека)</label>
                        <input type="text" name="clinic_address" class="form-control" placeholder="г. Ташкент, ул. Навои..." value="<?= htmlspecialchars($settings['clinic_address'] ?? '') ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small">IP адрес принтера (Ethernet/Wi-Fi)</label>
                        <div class="input-group">
                            <input type="text" name="printer_ip" id="printer_ip" class="form-control" placeholder="192.168.1.100" value="<?= htmlspecialchars($settings['printer_ip'] ?? '') ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="checkPrinter()">Проверить связь</button>
                        </div>
                        <div id="printer_status" class="small mt-1"></div>
                    </div>
                    
                    <h6 class="fw-bold mb-3">Социальные сети (для чека)</h6>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small">Telegram</label>
                            <input type="text" name="telegram" class="form-control" placeholder="@username" value="<?= htmlspecialchars($social['telegram'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small">Instagram</label>
                            <input type="text" name="instagram" class="form-control" placeholder="@username" value="<?= htmlspecialchars($social['instagram'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small">Содержимое QR-кода</label>
                        <textarea name="qr_content" class="form-control" rows="2" placeholder="Например: https://t.me/myclinic?id={id}"><?= htmlspecialchars($settings['qr_content'] ?? '{clinic} Чек #{id}') ?></textarea>
                        <div class="form-text small">Доступные теги: {clinic}, {id}, {patient}</div>
                    </div>

                    <div class="d-grid">
                        <input type="hidden" name="remove_logo" id="remove_logo" value="0">
                        <button type="submit" name="update_settings" class="btn btn-primary py-2 shadow-sm">Сохранить изменения</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="fab fa-telegram text-info me-2"></i>Telegram Bot</h6>
            </div>
            <div class="card-body text-center py-4">
                <i class="fab fa-telegram fa-3x text-info mb-3"></i>
                <h6 class="fw-bold">Настройки Telegram перенесены</h6>
                <p class="text-secondary small">Все настройки бота, webhook и привязка аккаунтов теперь на отдельной странице.</p>
                <a href="telegram_settings.php" class="btn btn-info">
                    <i class="fas fa-arrow-right me-1"></i> Открыть настройки Telegram
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">База данных</h6>
            </div>
            <div class="card-body">
                <p class="text-secondary small">Управление резервными копиями данных клиники.</p>
                <div class="d-grid gap-2">
                    <a href="backup_db.php" class="btn btn-light border text-start py-3 px-4 rounded-3 text-decoration-none">
                        <i class="fas fa-download text-success me-2"></i> Экспорт всей базы (SQL)
                    </a>
                    <a href="import_db.php" class="btn btn-light border text-start py-3 px-4 rounded-3 text-decoration-none">
                        <i class="fas fa-upload text-warning me-2"></i> Импорт базы (SQL)
                    </a>
                    <a href="migrate.php" class="btn btn-light border text-start py-3 px-4 rounded-3 text-decoration-none">
                        <i class="fas fa-sync-alt text-info me-2"></i> Миграция базы данных
                    </a>
                    <button class="btn btn-light border text-start py-3 px-4 rounded-3" onclick="testPrint()">
                        <i class="fas fa-print text-primary me-2"></i> Тестовая печать на чековом принтере
                    </button>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Очистка базы данных</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-danger small mb-3">
                    <strong>Внимание!</strong> Все действия необратимы. Сделайте бэкап перед очисткой.
                </div>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-danger text-start py-3 px-4 rounded-3" onclick="clearData('patients')">
                        <i class="fas fa-users me-2"></i> Пациенты <span class="text-muted small">— вся история, анализы, приёмы</span>
                    </button>
                    <button class="btn btn-outline-danger text-start py-3 px-4 rounded-3" onclick="clearData('doctors')">
                        <i class="fas fa-user-md me-2"></i> Врачи <span class="text-muted small">— полное удаление</span>
                    </button>
                    <button class="btn btn-outline-warning text-start py-3 px-4 rounded-3" onclick="clearData('lab_results')">
                        <i class="fas fa-microscope me-2"></i> Результаты анализов
                    </button>
                    <button class="btn btn-outline-warning text-start py-3 px-4 rounded-3" onclick="clearData('lab_templates')">
                        <i class="fas fa-file-medical me-2"></i> Шаблоны анализов <span class="text-muted small">— + все результаты</span>
                    </button>
                    <button class="btn btn-outline-warning text-start py-3 px-4 rounded-3" onclick="clearData('services')">
                        <i class="fas fa-stethoscope me-2"></i> Услуги <span class="text-muted small">— + направления и группы</span>
                    </button>
                    <button class="btn btn-outline-warning text-start py-3 px-4 rounded-3" onclick="clearData('appointments')">
                        <i class="fas fa-calendar-check me-2"></i> Приёмы <span class="text-muted small">— все записи</span>
                    </button>
                    <button class="btn btn-outline-secondary text-start py-3 px-4 rounded-3" onclick="clearData('audit_log')">
                        <i class="fas fa-history me-2"></i> Журнал аудита
                    </button>
                </div>
                <div id="clear_status" class="mt-3"></div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Предпросмотр чека</h6>
            </div>
            <div class="card-body">
                <div class="p-3 border rounded bg-white text-dark shadow-sm" style="font-family: 'Courier New', monospace; font-size: 0.85rem; max-width: 300px; margin: 0 auto;">
                    <div class="text-center mb-2">
                        <?php if (!empty($settings['clinic_logo'])): ?>
                            <img src="../assets/img/<?= htmlspecialchars($settings['clinic_logo']) ?>" alt="Logo" style="max-height: 50px; margin-bottom: 5px;">
                        <?php endif; ?>
                        <div class="fw-bold" style="font-size: 1.1rem;"><?= htmlspecialchars($settings['clinic_name'] ?? 'Clinic') ?></div>
                        <div style="font-size: 0.8rem;"><?= htmlspecialchars($settings['clinic_address'] ?? '') ?></div>
                        <div style="font-size: 0.8rem;"><?= htmlspecialchars($settings['clinic_phone'] ?? '') ?></div>
                        <div class="mt-1">КВИТАНЦИЯ #000001</div>
                        <div style="font-size: 0.75rem;"><?= date('d.m.Y H:i') ?></div>
                    </div>
                    <hr style="border-top: 1px dashed #000; margin: 10px 0;">
                    <div class="d-flex justify-content-between"><span>Услуга:</span> <span>Консультация</span></div>
                    <div class="d-flex justify-content-between"><span>Врач:</span> <span>Доктор Иванов</span></div>
                    <hr style="border-top: 1px dashed #000; margin: 10px 0;">
                    <div class="d-flex justify-content-between fw-bold"><span>ИТОГО:</span> <span>50 000 СУМ</span></div>
                    <hr style="border-top: 1px dashed #000; margin: 10px 0;">
                    <div class="text-center">
                        <small>TG: <?= htmlspecialchars($social['telegram'] ?? '') ?></small><br>
                        <strong>Будьте здоровы!</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    async function checkPrinter() {
        const ip = document.getElementById('printer_ip').value;
        const statusDiv = document.getElementById('printer_status');
        
        statusDiv.innerHTML = '<span class="text-secondary">Проверка...</span>';
        
        try {
            const res = await fetch(`../api/check_printer.php?ip=${ip}`);
            const data = await res.json();
            
            if (data.success) {
                statusDiv.innerHTML = `<span class="text-success fw-bold"><i class="fas fa-check-circle"></i> ${data.message}</span>`;
            } else {
                statusDiv.innerHTML = `<span class="text-danger fw-bold"><i class="fas fa-exclamation-circle"></i> ${data.message}</span>`;
            }
        } catch (e) {
            statusDiv.innerHTML = '<span class="text-danger">Ошибка сервера при проверке</span>';
        }
    }

    function testPrint() {
        const ip = document.getElementById('printer_ip').value;
        if (!ip) { showToast('Укажите IP принтера в настройках', 'warning'); return; }
        showToast('Отправка тестового чека...', 'info');
        fetch(`../api/check_printer.php?ip=${ip}`)
            .then(r => r.json())
            .then(d => { showToast(d.message, d.success ? 'success' : 'error'); })
            .catch(() => showToast('Ошибка связи с сервером', 'danger'));
    }

    function removeLogo() {
        if (!confirm('Удалить логотип?')) return;
        document.getElementById('remove_logo').value = '1';
        const thumb = document.querySelector('.position-relative');
        if (thumb) {
            thumb.outerHTML = '<div class="bg-light d-flex align-items-center justify-content-center border rounded" style="width: 80px; height: 80px;"><i class="fas fa-image text-secondary"></i></div>';
        }
        showToast('Логотип будет удалён после сохранения', 'info');
    }

    const clearLabels = {
        patients: 'ВСЕХ ПАЦИЕНТОВ с историей, анализами и приёмами',
        doctors: 'ВСЕХ ВРАЧЕЙ',
        lab_results: 'ВСЕ РЕЗУЛЬТАТЫ АНАЛИЗОВ',
        lab_templates: 'ВСЕ ШАБЛОНЫ и результаты анализов',
        services: 'ВСЕ УСЛУГИ, направления и группы',
        appointments: 'ВСЕ ЗАПИСИ НА ПРИЁМ',
        audit_log: 'ВЕСЬ ЖУРНАЛ АУДИТА'
    };

    async function clearData(type) {
        const label = clearLabels[type] || type;
        const confirmed = confirm(`Вы уверены что хотите удалить ${label}?`);
        if (!confirmed) return;

        const second = confirm(`ВНИМАНИЕ! Действие необратимо.\n\nУдалить ${label}?`);
        if (!second) return;

        const statusDiv = document.getElementById('clear_status');
        statusDiv.innerHTML = '<div class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Выполнение...</div>';

        try {
            const res = await fetch('../api/clear_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, confirm: 'DELETE' })
            });
            const data = await res.json();

            if (data.success) {
                statusDiv.innerHTML = `<div class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> ${data.data.message}</div>`;
                showToast(data.data.message, 'success');
            } else {
                statusDiv.innerHTML = `<div class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> ${data.error}</div>`;
                showToast(data.error, 'danger');
            }
        } catch (e) {
            statusDiv.innerHTML = '<div class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Ошибка сервера</div>';
            showToast('Ошибка связи с сервером', 'danger');
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
