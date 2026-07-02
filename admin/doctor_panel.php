<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['doctor']);

$doctor_id = $_SESSION['doctor_id'];
$today = date('Y-m-d');

// Stats for today
$stmt = $pdo->prepare("SELECT COUNT(*) as total, 
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as done,
                       SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as waiting
                       FROM appointments WHERE doctor_id = ? AND appointment_date = ?");
$stmt->execute([$doctor_id, $today]);
$stats = $stmt->fetch();

// Upcoming appointments grouped by patient/receipt
$stmt = $pdo->prepare("
    SELECT 
        MIN(a.id) as id,
        a.patient_id, 
        MAX(a.receipt_id) as receipt_id,
        MIN(a.appointment_time) as appointment_time,
        MAX(p.full_name) as patient_name,
        GROUP_CONCAT(s.name SEPARATOR ' • ') as services,
        MAX(a.status) as status
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN services s ON a.service_id = s.id
    WHERE a.doctor_id = ? AND a.appointment_date = ?
    GROUP BY a.patient_id, a.appointment_date, COALESCE(a.receipt_id, a.id)
    ORDER BY MIN(a.appointment_time) ASC
");
$stmt->execute([$doctor_id, $today]);
$appointments = $stmt->fetchAll();

$page_title = 'Рабочий стол врача';
include '../includes/header.php';
?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-4 text-center" style="border-radius: 1.5rem; background: linear-gradient(135deg, #4e54c8 0%, #8f94fb 100%); color: white;">
            <div class="h6 text-white text-opacity-75 text-uppercase small fw-bold">Всего сегодня</div>
            <div class="h1 fw-bold mb-0"><?= $stats['total'] ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-4 text-center" style="border-radius: 1.5rem; background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
            <div class="h6 text-white text-opacity-75 text-uppercase small fw-bold">Принято</div>
            <div class="h1 fw-bold mb-0"><?= $stats['done'] ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-4 text-center" style="border-radius: 1.5rem; background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%); color: white;">
            <div class="h6 text-white text-opacity-75 text-uppercase small fw-bold">Ожидают</div>
            <div class="h1 fw-bold mb-0"><?= $stats['waiting'] ?></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius: 1.5rem;">
    <div class="card-header bg-white py-4 px-4 border-0">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Моё расписание на сегодня</h5>
            <span class="badge bg-light text-dark shadow-sm px-3 py-2 border rounded-pill">
                <i class="far fa-calendar-alt me-2 text-primary"></i> <?= date('d.m.Y') ?>
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary small text-uppercase">
                    <tr>
                        <th class="ps-4">Время</th>
                        <th>Пациент</th>
                        <th>Услуга</th>
                        <th>Статус</th>
                        <th class="text-end pe-4">Медицинская карта</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $app): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-primary"><?= substr($app['appointment_time'], 0, 5) ?></td>
                        <td>
                            <div class="fw-bold text-dark"><?= $app['patient_name'] ?></div>
                        </td>
                        <td>
                            <div class="small text-truncate" style="max-width: 300px;" title="<?= $app['services'] ?>">
                                <?= $app['services'] ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge rounded-pill px-3 py-2 bg-<?= $app['status'] == 'completed' ? 'success' : ($app['status'] == 'cancelled' ? 'danger' : 'warning') ?> bg-opacity-10 text-<?= $app['status'] == 'completed' ? 'success' : ($app['status'] == 'cancelled' ? 'danger' : 'warning') ?>">
                                <?= $app['status'] == 'completed' ? 'Принят' : ($app['status'] == 'cancelled' ? 'Отменен' : 'Ожидает') ?>
                            </span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group">
                                <a href="patient_history.php?id=<?= $app['patient_id'] ?>" class="btn btn-outline-info rounded-pill btn-sm px-3 me-2">
                                    <i class="fas fa-history me-1"></i> История
                                </a>
                                <a href="lab_forms.php?patient_id=<?= $app['patient_id'] ?>&appointment_date=<?= $today ?>" class="btn btn-primary rounded-pill btn-sm px-3">
                                    <i class="fas fa-file-medical me-1"></i> Осмотр
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <div class="text-secondary opacity-50">
                                <i class="fas fa-calendar-day fa-3x mb-3"></i><br>
                                На сегодня записей пока нет
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>