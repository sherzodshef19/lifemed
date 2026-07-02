<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
require_once '../includes/helpers.php';
check_role(['admin']);

$page_title = 'Импорт базы данных';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ошибка загрузки файла';
    } elseif ($file['size'] > 52428800) { // 50MB max
        $error = 'Файл слишком большой (макс. 50MB)';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            $error = 'Допустимый формат: .sql';
        } else {
            $content = file_get_contents($file['tmp_name']);
            if ($content === false) {
                $error = 'Не удалось прочитать файл';
            } else {
                try {
                    // Split by semicolons (simplified - works for most SQL dumps)
                    $statements = array_filter(array_map('trim', explode(';', $content)));
                    $executed = 0;
                    foreach ($statements as $sql) {
                        if (!empty($sql) && $sql !== '--') {
                            $pdo->exec($sql);
                            $executed++;
                        }
                    }
                    $success = "Импорт завершён. Выполнено запросов: $executed";
                    audit_log($pdo, 'import_database', 'system', null, ['statements' => $executed]);
                } catch (PDOException $e) {
                    $error = 'Ошибка SQL: ' . $e->getMessage();
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0"><i class="fas fa-upload text-primary me-2"></i>Импорт базы данных</h6>
            </div>
            <div class="card-body p-4">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= h($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php endif; ?>

                <div class="alert alert-warning small">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Внимание!</strong> Импорт перезапишет существующие данные. Убедитесь, что вы делаете резервную копию перед импортом.
                </div>

                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="mb-4">
                        <label class="form-label text-secondary small">SQL файл</label>
                        <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4" id="importBtn" onclick="return confirm('Вы уверены? Это перезапишет данные!')">
                            <i class="fas fa-upload me-2"></i>Импортировать
                        </button>
                        <a href="backup_db.php" class="btn btn-outline-secondary px-4">
                            <i class="fas fa-download me-2"></i>Сначала сделать бэкап
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
