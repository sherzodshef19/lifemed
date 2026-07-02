<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
require_once '../includes/helpers.php';
check_role(['admin']);

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 30;

$has_commission = column_exists($pdo, 'services', 'commission_pct');
$has_specimen = column_exists($pdo, 'appointments', 'specimen_code');
$has_referring = column_exists($pdo, 'appointments', 'referring_doctor_name');

// Build dynamic WHERE
$where = "a.appointment_date BETWEEN ? AND ?";
$params = [$start_date, $end_date];

if ($search !== '') {
    $where .= " AND (p.full_name LIKE ? OR s.name LIKE ? OR d.full_name LIKE ? OR a.referring_doctor_name LIKE ?)";
    $q = "%{$search}%";
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
}

// Count total rows
$count_params = $params;
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN services s ON a.service_id = s.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    WHERE {$where}
");
$count_stmt->execute($count_params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$extra_cols = '';
if ($has_commission) $extra_cols .= ', s.commission_pct';
if ($has_specimen) $extra_cols .= ', a.specimen_code';
if ($has_referring) $extra_cols .= ', a.referring_doctor_name';

$query_params = array_merge($params, [$per_page, $offset]);
$stmt = $pdo->prepare("
    SELECT a.*, p.full_name as patient, p.phone as patient_phone, d.full_name as doctor, s.name as service, s.price{$extra_cols}
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    JOIN services s ON a.service_id = s.id
    WHERE {$where}
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT ? OFFSET ?
");
$stmt->execute($query_params);
$report_data = $stmt->fetchAll();

$stats_extra = '';
if ($has_commission) $stats_extra .= ', s.commission_pct';

$stats_params = $params;
$stats_stmt = $pdo->prepare("
    SELECT a.*, s.price, d.full_name as doctor{$stats_extra}
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    LEFT JOIN doctors d ON a.doctor_id = d.id
    JOIN patients p ON a.patient_id = p.id
    WHERE {$where}
");
$stats_stmt->execute($stats_params);
$all_data = $stats_stmt->fetchAll();

$total_income = 0;
foreach ($all_data as $row) {
    $qty = $row['quantity'] ?? 1;
    $total_income += ($row['price'] * $qty);
}

$doctor_stats = [];
foreach ($all_data as $row) {
    $doctorName = $row['doctor'] ?: 'Общий приём';
    if (!isset($doctor_stats[$doctorName])) $doctor_stats[$doctorName] = ['count' => 0, 'sum' => 0];
    $doctor_stats[$doctorName]['count']++;
    $qty = $row['quantity'] ?? 1;
    $doctor_stats[$doctorName]['sum'] += ($row['price'] * $qty);
}
uasort($doctor_stats, function($a, $b) { return $b['sum'] <=> $a['sum']; });

$referring_stats = [];
if ($has_commission && $has_referring) {
    $share_params = $params;
    $share_stmt = $pdo->prepare("
        SELECT a.referring_doctor_name, COUNT(*) as referral_count, SUM(s.price * a.quantity) as total_services,
               ROUND(AVG(s.commission_pct), 1) as avg_pct,
               SUM(s.price * a.quantity * s.commission_pct / 100) as total_share
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        WHERE {$where} AND s.commission_pct > 0 AND a.referring_doctor_name IS NOT NULL AND a.referring_doctor_name != ''
        GROUP BY a.referring_doctor_name
        ORDER BY total_share DESC
    ");
    $share_stmt->execute($share_params);
    $referring_stats = $share_stmt->fetchAll();
}

if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report_'.$start_date.'_to_'.$end_date.'.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($output, ['Дата', 'Время', 'Пациент', 'Телефон', 'Врач', 'Направил', 'Образец', 'Услуга', 'Сумма', 'Комиссия %', 'Доля врача', 'Оплата']);
    foreach ($report_data as $row) {
        $qty = $row['quantity'] ?? 1;
        $line_total = $row['price'] * $qty;
        $share = $line_total * ($row['commission_pct'] ?? 0) / 100;
        fputcsv($output, [
            $row['appointment_date'],
            $row['appointment_time'],
            $row['patient'],
            $row['patient_phone'],
            $row['doctor'] ?: 'Общий приём',
            $row['referring_doctor_name'] ?: '-',
            $row['specimen_code'] ?: '-',
            $row['service'],
            $line_total,
            $row['commission_pct'] ?? 0,
            $share > 0 ? $share : '-',
            $row['payment_status'] == 'paid' ? 'Оплачено' : 'Долг'
        ]);
    }
    fputcsv($output, ['', '', '', '', '', '', '', 'ИТОГО:', $total_income]);
    fclose($output);
    exit;
}

$page_title = 'Финансовые отчёты';
include '../includes/header.php';
?>

<div class="row g-4 mb-4">
    <!-- Filters Area -->
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
            <div class="card-body p-4">
                <form method="GET" class="row align-items-end g-3">
                    <div class="col-md-2">
                        <label class="form-label small text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Начало периода</label>
                        <input type="date" name="start_date" class="form-control border-light shadow-none bg-light" value="<?= $start_date ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Конец периода</label>
                        <input type="date" name="end_date" class="form-control border-light shadow-none bg-light" value="<?= $end_date ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-secondary fw-bold text-uppercase" style="font-size: 0.7rem;">Поиск</label>
                        <input type="text" name="search" class="form-control border-light shadow-none bg-light" placeholder="Пациент, услуга, врач, направитель..." value="<?= h($search) ?>">
                    </div>
                    <div class="col-md-4 d-flex justify-content-end align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-light border" onclick="setPeriod('today')">Сегодня</button>
                        <button type="button" class="btn btn-sm btn-light border" onclick="setPeriod('month')">Месяц</button>
                    </div>
                    <div class="col-12 d-flex justify-content-end align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-dark px-4 py-2 rounded-pill" onclick="printReport('browser')">
                            <i class="fas fa-print me-2"></i> Печать
                        </button>
                        <button type="button" class="btn btn-outline-primary px-4 py-2 rounded-pill" onclick="printReport('ip')">
                            <i class="fas fa-network-wired me-2"></i> IP Печать
                        </button>
                        <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm rounded-pill">
                            <i class="fas fa-sync-alt me-2"></i> Обновить
                        </button>
                        <button type="submit" name="export" value="1" class="btn btn-outline-success px-4 py-2 rounded-pill">
                            <i class="fas fa-file-excel me-2"></i> Экспорт
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Stats -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 1rem; background: linear-gradient(135deg, #4481eb 0%, #04befe 100%);">
            <div class="card-body p-4 text-white">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="bg-white bg-opacity-25 rounded-3 p-3">
                        <i class="fas fa-wallet fa-2x"></i>
                    </div>
                </div>
                <h6 class="opacity-75 mb-1">Выручка за период</h6>
                <h2 class="fw-bold mb-0"><?= number_format($total_income, 0, '.', ' ') ?> <small style="font-size: 1rem;">сум</small></h2>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 1rem;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="bg-info bg-opacity-10 text-info rounded-3 p-3">
                        <i class="fas fa-user-md fa-2x"></i>
                    </div>
                </div>
                <h6 class="text-secondary mb-1">Всего приёмов</h6>
                <h2 class="fw-bold mb-0"><?= $total_rows ?> <small class="text-secondary" style="font-size: 1rem;">визитов</small></h2>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 1rem;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                </div>
                <h6 class="text-secondary mb-1">Средний чек</h6>
                <h2 class="fw-bold mb-0">
                    <?= $total_rows > 0 ? number_format($total_income / $total_rows, 0, '.', ' ') : 0 ?>
                    <small class="text-secondary" style="font-size: 1rem;">сум</small>
                </h2>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Detailed Table -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius: 1rem; overflow: hidden;">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">Детализация платежей</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4">Дата / Пациент</th>
                            <th>Услуга / Врач</th>
                            <th>Образец</th>
                            <th>Сумма</th>
                            <th class="text-end pe-4">Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?= date('d.m.Y', strtotime($row['appointment_date'])) ?></div>
                                <div class="text-secondary" style="font-size: 0.8rem;"><?= h($row['patient']) ?></div>
                            </td>
                            <td>
                                <div class="fw-medium text-dark"><?= h($row['service']) ?></div>
                                <div class="text-secondary small"><?= h($row['doctor'] ?: 'Общий приём') ?></div>
                                <?php if (!empty($row['referring_doctor_name'])): ?>
                                    <div class="text-success small"><i class="fas fa-share me-1"></i><?= h($row['referring_doctor_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['specimen_code'])): ?>
                                    <span class="badge bg-light text-dark border"><?= h($row['specimen_code']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-bold text-primary"><?= number_format($row['price'] * ($row['quantity'] ?? 1), 0, '.', ' ') ?></div>
                                <?php if (!empty($row['commission_pct']) && $row['commission_pct'] > 0): ?>
                                    <div class="text-success small" style="font-size: 0.7rem;">+<?= number_format($row['price'] * ($row['quantity'] ?? 1) * $row['commission_pct'] / 100, 0, '.', ' ') ?> доля</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <span class="badge rounded-pill bg-<?= $row['payment_status'] == 'paid' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $row['payment_status'] == 'paid' ? 'success' : 'danger' ?> px-3">
                                    <?= $row['payment_status'] == 'paid' ? 'Оплачено' : 'Долг' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($report_data)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-secondary">
                                <i class="fas fa-folder-open fa-3x opacity-25 mb-3 d-block"></i>
                                За указанный период данных не найдено
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <?php $filter_qs = http_build_query(array_filter(['start_date' => $start_date, 'end_date' => $end_date, 'search' => $search])); ?>
        <div class="d-flex justify-content-center mt-3">
            <nav>
                <ul class="pagination pagination-sm shadow-sm">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $filter_qs ?>&page=1"><i class="fas fa-angle-double-left"></i></a>
                    </li>
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $filter_qs ?>&page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php
                    $start_p = max(1, $page - 2);
                    $end_p = min($total_pages, $page + 2);
                    if ($end_p - $start_p < 4) {
                        if ($start_p === 1) $end_p = min($total_pages, $start_p + 4);
                        else $start_p = max(1, $end_p - 4);
                    }
                    for ($i = $start_p; $i <= $end_p; $i++):
                    ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link px-3" href="?<?= $filter_qs ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $filter_qs ?>&page=<?= $page + 1 ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= $filter_qs ?>&page=<?= $total_pages ?>"><i class="fas fa-angle-double-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <!-- Doctor Performance -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold mb-0">По врачам</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($doctor_stats as $name => $stats): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                        <div>
                            <div class="fw-bold text-dark"><?= h($name) ?></div>
                            <small class="text-secondary"><?= $stats['count'] ?> приёмов</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-dark"><?= number_format($stats['sum'], 0, '.', ' ') ?></div>
                            <small class="text-primary fw-bold" style="font-size: 0.7rem;"><?= round(($stats['sum'] / $total_income) * 100, 1) ?>%</small>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Referring Doctor Shares -->
<?php if (!empty($referring_stats)): ?>
<div class="row g-4 mt-1">
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="fas fa-user-md text-success me-2"></i>Доли направивших врачей</h6>
                <small class="text-secondary">На основе процента комиссии по услугам</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-4">Врач</th>
                            <th>Кол-во направлений</th>
                            <th>Сумма услуг</th>
                            <th>Средний %</th>
                            <th class="text-end pe-4">Доля врача (сум)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $total_shares = 0; ?>
                        <?php foreach ($referring_stats as $ref): ?>
                        <?php $total_shares += $ref['total_share']; ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark"><?= h($ref['referring_doctor_name']) ?></td>
                            <td><?= $ref['referral_count'] ?></td>
                            <td><?= number_format($ref['total_services'], 0, '.', ' ') ?></td>
                            <td><span class="badge bg-info bg-opacity-10 text-info"><?= $ref['avg_pct'] ?>%</span></td>
                            <td class="text-end pe-4 fw-bold text-success"><?= number_format($ref['total_share'], 0, '.', ' ') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-light">
                            <td class="ps-4 fw-bold" colspan="4">ИТОГО доли врачей</td>
                            <td class="text-end pe-4 fw-bold text-success" style="font-size: 1.1rem;"><?= number_format($total_shares, 0, '.', ' ') ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function setPeriod(type) {
    const start = document.querySelector('input[name="start_date"]');
    const end = document.querySelector('input[name="end_date"]');
    const today = new Date().toISOString().split('T')[0];
    
    if (type === 'today') {
        start.value = today;
        end.value = today;
    } else if (type === 'yesterday') {
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        const yStr = yesterday.toISOString().split('T')[0];
        start.value = yStr;
        end.value = yStr;
    } else if (type === 'month') {
        const date = new Date();
        const firstDay = new Date(date.getFullYear(), date.getMonth(), 1).toISOString().split('T')[0];
        const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0).toISOString().split('T')[0];
        start.value = firstDay;
        end.value = lastDay;
    }
    start.form.submit();
}
function printReport(type) {
    const start = document.querySelector('input[name="start_date"]').value;
    const end = document.querySelector('input[name="end_date"]').value;
    
    if (type === 'browser') {
        window.open(`report_print.php?start_date=${start}&end_date=${end}`, '_blank');
    } else if (type === 'ip') {
        const btn = event.currentTarget;
        const oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Обработка...';

        (async () => {
            try {
                let logoHex = '';
                const logoImg = document.querySelector('.sidebar img'); // Try to find logo in sidebar
                if (logoImg && logoImg.complete && logoImg.naturalWidth !== 0) {
                    logoHex = await getImageHex(logoImg);
                }

                const formData = new FormData();
                formData.append('start_date', start);
                formData.append('end_date', end);
                if (logoHex) formData.append('logo_hex', logoHex);

                const res = await fetch(`../api/print_report_escpos.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) showToast('Отчёт успешно отправлен на принтер', 'success');
                else showToast('Ошибка печати: ' + data.error, 'danger');
            } catch (e) {
                console.error(e);
                showToast('Ошибка связи с принтером', 'danger');
            } finally {
                btn.innerHTML = oldHtml;
                btn.disabled = false;
            }
        })();
    }
}

async function getImageHex(img) {
    return new Promise((resolve) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const targetWidth = 384; 
        const scale = targetWidth / img.naturalWidth;
        const targetHeight = Math.round(img.naturalHeight * scale);
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        ctx.fillStyle = "white";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, targetWidth, targetHeight);
        const imageData = ctx.getImageData(0, 0, targetWidth, targetHeight);
        const pixels = imageData.data;
        const threshold = 128;
        const bitData = [];
        for (let y = 0; y < targetHeight; y++) {
            for (let x = 0; x < targetWidth; x += 8) {
                let byte = 0;
                for (let bit = 0; bit < 8; bit++) {
                    const idx = ((y * targetWidth) + (x + bit)) * 4;
                    const r = pixels[idx];
                    const g = pixels[idx + 1];
                    const b = pixels[idx + 2];
                    const avg = (r + g + b) / 3;
                    if (avg < threshold) byte |= (1 << (7 - bit));
                }
                bitData.push(byte);
            }
        }
        const xL = (targetWidth / 8) % 256;
        const xH = Math.floor((targetWidth / 8) / 256);
        const yL = targetHeight % 256;
        const yH = Math.floor(targetHeight / 256);
        const command = [0x1D, 0x76, 0x30, 0x00, xL, xH, yL, yH, ...bitData];
        const hex = command.map(b => b.toString(16).padStart(2, '0')).join('');
        resolve(hex);
    });
}
</script>

<?php include '../includes/footer.php'; ?>
