<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'cashier', 'doctor']);

if ($_SESSION['role'] === 'doctor') {
    header("Location: doctor_panel.php");
    exit();
}

$page_title = 'Главная панель';
include '../includes/header.php';

$soft_delete_sql = appointments_soft_delete_where($pdo);
$soft_delete_sql_p = appointments_soft_delete_where_simple($pdo);

// Fetch stats
$patients_count = $pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$doctors_count = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$appointments_today = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURRENT_DATE" . $soft_delete_sql_p)->fetchColumn();
$income_today = $pdo->query("SELECT SUM(s.price * COALESCE(a.quantity, 1)) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.payment_status = 'paid' AND a.appointment_date = CURRENT_DATE" . $soft_delete_sql)->fetchColumn() ?? 0;
$total_income = $pdo->query("SELECT SUM(s.price * COALESCE(a.quantity, 1)) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.payment_status = 'paid'" . $soft_delete_sql)->fetchColumn() ?? 0;
$unpaid_count = $pdo->query("SELECT COUNT(*) FROM appointments WHERE payment_status = 'unpaid'" . $soft_delete_sql_p)->fetchColumn();
?>

<style>
    .stat-card {
        border: none;
        border-radius: 1.25rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        position: relative;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px -5px rgba(0,0,0,0.1);
    }
    .stat-card .icon-box {
        width: 56px;
        height: 56px;
        border-radius: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
    }
    .bg-gradient-primary { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); }
    .bg-gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .bg-gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
    .bg-gradient-info { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
    
    .quick-link {
        transition: all 0.2s;
        border: 1px solid #f1f5f9 !important;
    }
    .quick-link:hover {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
        transform: translateX(5px);
    }
    .list-item-hover {
        transition: background-color 0.2s;
    }
    .list-item-hover:hover {
        background-color: #f8fafc !important;
    }
</style>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="icon-box bg-primary bg-opacity-10 text-primary">
                    <i class="fas fa-users fa-lg"></i>
                </div>
                <h6 class="text-secondary fw-medium mb-1">Пациенты</h6>
                <div class="d-flex align-items-end gap-2">
                    <h3 class="fw-bold mb-0"><?= number_format($patients_count) ?></h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="icon-box bg-success bg-opacity-10 text-success">
                    <i class="fas fa-calendar-check fa-lg"></i>
                </div>
                <h6 class="text-secondary fw-medium mb-1">Приёмы (Сегодня)</h6>
                <div class="d-flex align-items-end gap-2">
                    <h3 class="fw-bold mb-0"><?= $appointments_today ?></h3>
                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill mb-1">Актуально</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="icon-box bg-info bg-opacity-10 text-info">
                    <i class="fas fa-coins fa-lg"></i>
                </div>
                <h6 class="text-secondary fw-medium mb-1">Доход (Сегодня)</h6>
                <h3 class="fw-bold mb-0"><?= number_format($income_today, 0, '.', ' ') ?> <small class="fs-6 fw-normal">сум</small></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="icon-box bg-warning bg-opacity-10 text-warning">
                    <i class="fas fa-file-invoice fa-lg"></i>
                </div>
                <h6 class="text-secondary fw-medium mb-1">Ожидают оплаты</h6>
                <div class="d-flex align-items-end gap-2">
                    <h3 class="fw-bold mb-0"><?= $unpaid_count ?></h3>
                    <span class="text-<?= $unpaid_count > 0 ? 'danger' : 'secondary' ?> small mb-1">записей</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Последние записи на приём</h6>
                <a href="appointments.php" class="btn btn-sm btn-outline-primary">Все записи</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-secondary small text-uppercase">
                            <tr>
                                <th class="ps-4">Пациент</th>
                                <th>Врач</th>
                                <th>Услуга</th>
                                <th>Статус</th>
                                <th class="text-end pe-4">Время</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_stmt = $pdo->query("
                                SELECT 
                                    MAX(p.full_name) as patient, 
                                    MAX(d.full_name) as doctor, 
                                    GROUP_CONCAT(s.name SEPARATOR ' • ') as services, 
                                    MIN(a.appointment_time) as appointment_time, 
                                    MAX(a.status) as status, 
                                    MAX(a.payment_status) as payment_status,
                                    COUNT(*) as service_count
                                FROM appointments a
                                JOIN patients p ON a.patient_id = p.id
                                LEFT JOIN doctors d ON a.doctor_id = d.id
                                JOIN services s ON a.service_id = s.id
                                GROUP BY a.patient_id, a.appointment_date, COALESCE(a.receipt_id, a.id)
                                ORDER BY MAX(a.created_at) DESC
                                LIMIT 7
                            ");
                            while ($row = $recent_stmt->fetch()):
                                $status_color = ($row['status'] == 'completed' ? 'success' : ($row['status'] == 'cancelled' ? 'danger' : 'primary'));
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= $row['patient'] ?></div>
                                    <div class="text-<?= $row['payment_status'] == 'paid' ? 'success' : 'danger' ?> ultra-small" style="font-size: 0.7rem;">
                                        <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>
                                        <?= $row['payment_status'] == 'paid' ? 'Оплачено' : 'Ожидает оплаты' ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px;">
                                            <i class="fas fa-user-md text-secondary" style="font-size: 0.8rem;"></i>
                                        </div>
                                        <span class="small"><?= $row['doctor'] ?: 'Общий приём' ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-truncate d-inline-block small" style="max-width: 200px;" title="<?= $row['services'] ?>">
                                        <?= $row['services'] ?>
                                        <?php if ($row['service_count'] > 1): ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1">x<?= $row['service_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $status_color ?> bg-opacity-10 text-<?= $status_color ?> rounded-pill px-3 py-2 small fw-medium">
                                        <?= $row['status'] == 'completed' ? 'Завершен' : ($row['status'] == 'cancelled' ? 'Отменен' : 'Ожидает') ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="fw-bold text-primary small"><?= substr($row['appointment_time'], 0, 5) ?></div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div id="quick-actions" class="card">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0">Быстрые действия</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-3">
                    <a href="registration.php" class="btn btn-white text-start shadow-sm py-3 px-4 rounded-3 quick-link">
                        <i class="fas fa-user-plus text-primary me-2"></i> Регистрация пациента
                    </a>
                    <a href="appointments.php?action=new" class="btn btn-white text-start shadow-sm py-3 px-4 rounded-3 quick-link">
                        <i class="fas fa-calendar-plus text-success me-2"></i> Новая запись
                    </a>
                    <a href="reports.php" class="btn btn-white text-start shadow-sm py-3 px-4 rounded-3 quick-link">
                        <i class="fas fa-file-invoice-dollar text-warning me-2"></i> Отчёт по кассе
                    </a>
                </div>
            </div>
        </div>

        <!-- Latest Examinations Section -->
        <div class="card mt-4 border-0 shadow-sm" style="border-radius: 1.25rem;">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Новые обследования</h6>
                <a href="lab_forms.php" class="btn btn-sm btn-link text-primary text-decoration-none">Все</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php
                    $lab_stmt = $pdo->query("
                        SELECT r.*, p.full_name as patient, t.title as template 
                        FROM lab_results r 
                        JOIN patients p ON r.patient_id = p.id 
                        JOIN lab_templates t ON r.template_id = t.id 
                        ORDER BY r.created_at DESC 
                        LIMIT 5
                    ");
                    $lab_results = $lab_stmt->fetchAll();
                    foreach ($lab_results as $lab):
                    ?>
                    <div class="list-group-item border-0 py-3 px-4 list-item-hover">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold small text-dark mb-1"><?= $lab['patient'] ?></div>
                                <div class="text-secondary" style="font-size: 0.75rem;"><?= $lab['template'] ?></div>
                            </div>
                            <div class="text-end">
                                <a href="lab_print.php?patient_id=<?= $lab['patient_id'] ?>&template_id=<?= $lab['template_id'] ?>&result_id=<?= $lab['id'] ?>" 
                                   target="_blank" class="btn btn-sm btn-light border rounded-pill">
                                    <i class="fas fa-eye text-primary"></i>
                                </a>
                                <div class="text-muted mt-1" style="font-size: 0.65rem;"><?= date('H:i', strtotime($lab['created_at'])) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($lab_results)): ?>
                    <div class="p-4 text-center text-secondary small">
                        Результатов пока нет
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
