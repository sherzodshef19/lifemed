<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'cashier']);

$patient_id = $_GET['id'] ?? die('Patient ID required');

// Fetch Patient Info
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) die('Patient not found');

// Fetch Visits History
$stmt = $pdo->prepare("
    SELECT a.*, d.full_name as doctor_name, s.name as service_name, s.price
    FROM appointments a
    LEFT JOIN doctors d ON a.doctor_id = d.id
    JOIN services s ON a.service_id = s.id
    WHERE a.patient_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$stmt->execute([$patient_id]);
$visits = $stmt->fetchAll();

// Group visits by receipt_id (or individual if receipt_id is null)
$grouped_visits = [];
foreach ($visits as $v) {
    $group_key = $v['receipt_id'] ? 'r_' . $v['receipt_id'] : 'a_' . $v['id'];
    if (!isset($grouped_visits[$group_key])) {
        $grouped_visits[$group_key] = [
            'id' => $v['id'],
            'receipt_id' => $v['receipt_id'],
            'appointment_date' => $v['appointment_date'],
            'appointment_time' => $v['appointment_time'],
            'doctor_name' => $v['doctor_name'],
            'payment_status' => $v['payment_status'],
            'services' => [],
            'total_price' => 0
        ];
    }
    $grouped_visits[$group_key]['services'][] = [
        'id' => $v['id'],
        'name' => $v['service_name'],
        'price' => $v['price']
    ];
    $grouped_visits[$group_key]['total_price'] += $v['price'];
}

$page_title = 'История пациента: ' . $patient['full_name'];
include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><?= htmlspecialchars($patient['full_name']) ?></h4>
        <p class="text-secondary mb-0">
            <i class="fas fa-birthday-cake me-1"></i> <?= $patient['dob'] ?> | 
            <i class="fas fa-phone me-1"></i> <?= $patient['phone'] ?>
        </p>
    </div>
    <a href="patients.php" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="fas fa-arrow-left me-2"></i> К списку пациентов
    </a>
</div>

<div class="row g-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-4 text-center" style="border-radius: 1rem;">
            <div class="h6 text-secondary text-uppercase small fw-bold">Всего визитов</div>
            <div class="h2 fw-bold mb-0 text-primary"><?= count($visits) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm p-4 text-center" style="border-radius: 1rem;">
            <div class="h6 text-secondary text-uppercase small fw-bold">Общая сумма</div>
            <div class="h2 fw-bold mb-0 text-success">
                <?= number_format(array_sum(array_column($visits, 'price')), 0, '.', ' ') ?>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-pills mb-4 bg-white p-2 rounded-pill shadow-sm d-inline-flex" id="pills-tab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active rounded-pill px-4" id="visits-tab" data-bs-toggle="pill" data-bs-target="#visits" type="button">
            <i class="fas fa-calendar-check me-2"></i> Визиты
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link rounded-pill px-4" id="lab-tab" data-bs-toggle="pill" data-bs-target="#lab" type="button">
            <i class="fas fa-microscope me-2"></i> Анализы
        </button>
    </li>
</ul>

<div class="tab-content" id="pills-tabContent">
    <!-- VISITS TAB -->
    <div class="tab-pane fade show active" id="visits" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius: 1rem; overflow: hidden;">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Журнал посещений</h6>
                <button type="button" id="printGeneralBtn" class="btn btn-primary rounded-pill btn-sm px-3 shadow-sm" style="display: none;">
                    <i class="fas fa-receipt me-2"></i> Общий чек (<span id="selectedCount">0</span>)
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4" style="width: 40px;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAllVisits">
                                </div>
                            </th>
                            <th>Дата / Время</th>
                            <th>Услуга</th>
                            <th>Врач</th>
                            <th>Сумма</th>
                            <th class="text-end pe-4">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grouped_visits as $group): ?>
                        <tr class="<?= $group['receipt_id'] ? 'table-light' : '' ?>">
                            <td class="ps-4">
                                <div class="form-check">
                                    <input class="form-check-input visit-checkbox" type="checkbox" value="<?= $group['receipt_id'] ? 'group_' . $group['receipt_id'] : $group['id'] ?>" data-ids="<?= $group['receipt_id'] ? implode(',', array_column($group['services'], 'id')) : $group['id'] ?>">
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= date('d.m.Y', strtotime($group['appointment_date'])) ?></div>
                                <small class="text-secondary"><?= substr($group['appointment_time'], 0, 5) ?></small>
                            </td>
                            <td>
                                <?php foreach ($group['services'] as $idx => $s): ?>
                                    <div class="<?= $idx > 0 ? 'mt-1 pt-1 border-top' : '' ?>">
                                        <div class="fw-bold text-dark"><?= $s['name'] ?></div>
                                        <?php if (count($group['services']) > 1): ?>
                                            <small class="text-secondary"><?= number_format($s['price'], 0, '.', ' ') ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <span class="badge rounded-pill bg-<?= $group['payment_status'] == 'paid' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $group['payment_status'] == 'paid' ? 'success' : 'danger' ?> px-2 small mt-1">
                                    <?= $group['payment_status'] == 'paid' ? 'Оплачено' : 'Долг' ?>
                                </span>
                            </td>
                            <td><?= $group['doctor_name'] ?: '<span class="text-secondary small italic">Общий приём</span>' ?></td>
                            <td class="fw-bold"><?= number_format($group['total_price'], 0, '.', ' ') ?></td>
                            <td class="text-end pe-4">
                                <?php if ($group['receipt_id']): ?>
                                    <a href="receipt.php?receipt_id=<?= $group['receipt_id'] ?>" target="_blank" class="btn btn-sm btn-primary" title="Печать группового чека">
                                        <i class="fas fa-print me-1"></i> Чек
                                    </a>
                                <?php else: ?>
                                    <a href="receipt.php?id=<?= $group['id'] ?>" target="_blank" class="btn btn-sm btn-light text-primary" title="Печать чека">
                                        <i class="fas fa-print"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- LAB RESULTS TAB -->
    <div class="tab-pane fade" id="lab" role="tabpanel">
        <div class="card border-0 shadow-sm" style="border-radius: 1rem; overflow: hidden;">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Результаты анализов</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4">Дата</th>
                            <th>Название анализа</th>
                            <th class="text-end pe-4">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stmt = $pdo->prepare("SELECT r.*, t.title FROM lab_results r JOIN lab_templates t ON r.template_id = t.id WHERE r.patient_id = ? ORDER BY r.created_at DESC");
                        $stmt->execute([$patient_id]);
                        $results = $stmt->fetchAll();
                        foreach ($results as $res): 
                        ?>
                        <tr>
                            <td class="ps-4"><?= date('d.m.Y H:i', strtotime($res['created_at'])) ?></td>
                            <td><div class="fw-bold text-dark"><?= $res['title'] ?></div></td>
                            <td class="text-end pe-4">
                                <a href="lab_print.php?result_id=<?= $res['id'] ?>&patient_id=<?= $patient_id ?>&template_id=<?= $res['template_id'] ?>" target="_blank" class="btn btn-sm btn-light text-success" title="Просмотр и печать">
                                    <i class="fas fa-file-medical me-1"></i> Открыть
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($results)): ?>
                        <tr><td colspan="3" class="text-center py-5 text-secondary">Анализы не найдены</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllVisits');
    const checkboxes = document.querySelectorAll('.visit-checkbox');
    const printBtn = document.getElementById('printGeneralBtn');
    const countSpan = document.getElementById('selectedCount');

    function updatePrintButton() {
        const checkedCount = document.querySelectorAll('.visit-checkbox:checked').length;
        if (checkedCount > 0) {
            printBtn.style.display = 'inline-block';
            countSpan.textContent = checkedCount;
        } else {
            printBtn.style.display = 'none';
        }
    }

    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = selectAll.checked);
        updatePrintButton();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updatePrintButton);
    });

    printBtn.addEventListener('click', function() {
        const selectedElements = Array.from(document.querySelectorAll('.visit-checkbox:checked'));
        let allIds = [];
        let hasReceiptGroups = false;
        let receiptIds = [];

        selectedElements.forEach(cb => {
            const val = cb.value;
            if (val.startsWith('group_')) {
                receiptIds.push(val.replace('group_', ''));
                hasReceiptGroups = true;
            } else {
                allIds.push(val);
            }
        });
        
        // If we have mixed or just specific IDs, let's open them.
        // Actually, for simplicity, if multi-selecting, we can just fetch all individual IDs from the groups
        // But receipt.php?receipt_id is cleaner for full groups.
        
        if (receiptIds.length === 1 && allIds.length === 0) {
            window.open(`receipt.php?receipt_id=${receiptIds[0]}`, '_blank');
        } else if (allIds.length > 0 || receiptIds.length > 0) {
            // Collect all IDs from data-ids attribute to be safe
            let combinedIds = [];
            selectedElements.forEach(cb => {
                const dataIds = cb.getAttribute('data-ids');
                if (dataIds) combinedIds.push(...dataIds.split(','));
            });
            window.open(`receipt.php?id=${combinedIds.join(',')}`, '_blank');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
