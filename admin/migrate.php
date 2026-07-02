<?php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
require_once '../includes/helpers.php';
check_role(['admin']);

$results = [];

function run_migration($pdo, $name, $sql) {
    global $results;
    try {
        $pdo->exec($sql);
        $results[] = ['name' => $name, 'status' => 'ok', 'message' => 'Выполнено'];
    } catch (PDOException $e) {
        if ($e->getCode() == '42S01' || strpos($e->getMessage(), 'already exists') !== false) {
            $results[] = ['name' => $name, 'status' => 'skip', 'message' => 'Уже существует'];
        } else {
            $results[] = ['name' => $name, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
}

function migrate_table_exists($pdo, $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    return $stmt->fetch() !== false;
}

// === 1. Audit Log Table ===
if (!migrate_table_exists($pdo, 'audit_log')) {
    run_migration($pdo, 'Create audit_log table', "
        CREATE TABLE audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            role VARCHAR(20) DEFAULT 'system',
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50) NULL,
            entity_id INT NULL,
            details JSON NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} else {
    $results[] = ['name' => 'Create audit_log table', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 2. Working Hours Table ===
if (!migrate_table_exists($pdo, 'working_hours')) {
    run_migration($pdo, 'Create working_hours table', "
        CREATE TABLE working_hours (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doctor_id INT NOT NULL,
            day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
            UNIQUE KEY uk_doctor_day (doctor_id, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} else {
    $results[] = ['name' => 'Create working_hours table', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 3. Add queue_number to appointments ===
if (!column_exists($pdo, 'appointments', 'queue_number')) {
    run_migration($pdo, 'Add queue_number to appointments', "
        ALTER TABLE appointments ADD COLUMN queue_number INT DEFAULT 0
    ");
} else {
    $results[] = ['name' => 'Add queue_number to appointments', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 4. Add deleted_at to patients ===
if (!column_exists($pdo, 'patients', 'deleted_at')) {
    run_migration($pdo, 'Add deleted_at to patients', "
        ALTER TABLE patients ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
    ");
} else {
    $results[] = ['name' => 'Add deleted_at to patients', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 5. Add deleted_at to appointments ===
if (!column_exists($pdo, 'appointments', 'deleted_at')) {
    run_migration($pdo, 'Add deleted_at to appointments', "
        ALTER TABLE appointments ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL
    ");
} else {
    $results[] = ['name' => 'Add deleted_at to appointments', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 6. Add receipt_number to appointments ===
if (!column_exists($pdo, 'appointments', 'receipt_number')) {
    run_migration($pdo, 'Add receipt_number to appointments', "
        ALTER TABLE appointments ADD COLUMN receipt_number INT NULL
    ");
} else {
    $results[] = ['name' => 'Add receipt_number to appointments', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 7. Add category to lab_templates if missing ===
if (!column_exists($pdo, 'lab_templates', 'category')) {
    run_migration($pdo, 'Add category to lab_templates', "
        ALTER TABLE lab_templates ADD COLUMN category VARCHAR(50) DEFAULT 'laboratory'
    ");
} else {
    $results[] = ['name' => 'Add category to lab_templates', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 8. Add gender to patients if missing ===
if (!column_exists($pdo, 'patients', 'gender')) {
    run_migration($pdo, 'Add gender to patients', "
        ALTER TABLE patients ADD COLUMN gender ENUM('male', 'female') NULL DEFAULT NULL
    ");
} else {
    $results[] = ['name' => 'Add gender to patients', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 9. Replace referring_doctor_id with referring_doctor_name in appointments ===
if (column_exists($pdo, 'appointments', 'referring_doctor_id')) {
    run_migration($pdo, 'Drop referring_doctor_id from appointments', "
        ALTER TABLE appointments DROP COLUMN referring_doctor_id
    ");
} else {
    $results[] = ['name' => 'Drop referring_doctor_id from appointments', 'status' => 'skip', 'message' => 'Уже существует'];
}
if (!column_exists($pdo, 'appointments', 'referring_doctor_name')) {
    run_migration($pdo, 'Add referring_doctor_name to appointments', "
        ALTER TABLE appointments ADD COLUMN referring_doctor_name VARCHAR(255) NULL DEFAULT NULL AFTER doctor_id
    ");
} else {
    $results[] = ['name' => 'Add referring_doctor_name to appointments', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 10. Add specimen_code to appointments ===
if (!column_exists($pdo, 'appointments', 'specimen_code')) {
    run_migration($pdo, 'Add specimen_code to appointments', "
        ALTER TABLE appointments ADD COLUMN specimen_code VARCHAR(30) NULL DEFAULT NULL AFTER receipt_number
    ");
} else {
    $results[] = ['name' => 'Add specimen_code to appointments', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 11. Add commission_pct to services ===
if (!column_exists($pdo, 'services', 'commission_pct')) {
    run_migration($pdo, 'Add commission_pct to services', "
        ALTER TABLE services ADD COLUMN commission_pct DECIMAL(5,2) DEFAULT 0 AFTER price
    ");
} else {
    $results[] = ['name' => 'Add commission_pct to services', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 12. Add telegram_id to users ===
if (!column_exists($pdo, 'users', 'telegram_id')) {
    run_migration($pdo, 'Add telegram_id to users', "
        ALTER TABLE users ADD COLUMN telegram_id VARCHAR(20) NULL DEFAULT NULL AFTER phone
    ");
} else {
    $results[] = ['name' => 'Add telegram_id to users', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 13. Add telegram_id to doctors ===
if (!column_exists($pdo, 'doctors', 'telegram_id')) {
    run_migration($pdo, 'Add telegram_id to doctors', "
        ALTER TABLE doctors ADD COLUMN telegram_id VARCHAR(20) NULL DEFAULT NULL AFTER phone
    ");
} else {
    $results[] = ['name' => 'Add telegram_id to doctors', 'status' => 'skip', 'message' => 'Уже существует'];
}

// === 14. Add telegram_id to patients ===
if (!column_exists($pdo, 'patients', 'telegram_id')) {
    run_migration($pdo, 'Add telegram_id to patients', "
        ALTER TABLE patients ADD COLUMN telegram_id VARCHAR(20) NULL DEFAULT NULL AFTER phone
    ");
} else {
    $results[] = ['name' => 'Add telegram_id to patients', 'status' => 'skip', 'message' => 'Уже существует'];
}

// Log migration run
audit_log($pdo, 'run_migration', 'system', null, ['results' => $results]);

$page_title = 'Миграция базы данных';
include '../includes/header.php';

$okCount = count(array_filter($results, function($r) { return $r['status'] === 'ok'; }));
$skipCount = count(array_filter($results, function($r) { return $r['status'] === 'skip'; }));
$errorCount = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fas fa-database text-primary me-2"></i>Миграция базы данных</h6>
                <a href="settings.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Назад</a>
            </div>
            <div class="card-body p-4">
                <!-- Summary -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="p-3 bg-success bg-opacity-10 rounded-3 text-center">
                            <h3 class="fw-bold text-success mb-0"><?= $okCount ?></h3>
                            <small class="text-secondary">Выполнено</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-warning bg-opacity-10 rounded-3 text-center">
                            <h3 class="fw-bold text-warning mb-0"><?= $skipCount ?></h3>
                            <small class="text-secondary">Пропущено (уже есть)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-danger bg-opacity-10 rounded-3 text-center">
                            <h3 class="fw-bold text-danger mb-0"><?= $errorCount ?></h3>
                            <small class="text-secondary">Ошибки</small>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light text-secondary small text-uppercase">
                            <tr>
                                <th>Миграция</th>
                                <th class="text-center" style="width: 120px;">Статус</th>
                                <th>Сообщение</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $r): ?>
                            <tr>
                                <td class="fw-medium"><?= h($r['name']) ?></td>
                                <td class="text-center">
                                    <?php if ($r['status'] === 'ok'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2">
                                            <i class="fas fa-check me-1"></i> OK
                                        </span>
                                    <?php elseif ($r['status'] === 'skip'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2">
                                            <i class="fas fa-minus-circle me-1"></i> Пропущено
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2">
                                            <i class="fas fa-times me-1"></i> Ошибка
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-secondary small"><?= h($r['message']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($errorCount === 0): ?>
                <div class="alert alert-success mt-3 mb-0">
                    <i class="fas fa-check-circle me-2"></i> Все миграции выполнены успешно!
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
