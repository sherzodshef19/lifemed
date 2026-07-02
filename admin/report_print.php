<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin']);

$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT a.*, d.full_name as doctor, s.name as service, s.price
    FROM appointments a
    LEFT JOIN doctors d ON a.doctor_id = d.id
    JOIN services s ON a.service_id = s.id
    WHERE a.appointment_date BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date]);
$data = $stmt->fetchAll();

$total_income = 0;
foreach ($data as $row) {
    $qty = $row['quantity'] ?? 1;
    $total_income += ($row['price'] * $qty);
}

// Stats by doctor
$doc_stats = [];
foreach ($data as $row) {
    $name = $row['doctor'] ?: 'Общие';
    if (!isset($doc_stats[$name])) $doc_stats[$name] = 0;
    $qty = $row['quantity'] ?? 1;
    $doc_stats[$name] += ($row['price'] * $qty);
}
arsort($doc_stats);

$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$clinic_name = $settings['clinic_name'] ?? 'LifeMed CRM';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Финансовый отчёт</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 80mm; margin: 0 auto; color: #000; font-size: 13px; line-height: 1.4; }
        .receipt { padding: 5mm; border: 1px dashed #ccc; background: #fff; }
        .header { text-align: center; margin-bottom: 5mm; }
        .divider { border-bottom: 1px dashed #000; margin: 3mm 0; }
        .row { display: flex; justify-content: space-between; margin: 1mm 0; }
        .bold { font-weight: bold; }
        .no-print { text-align: center; margin: 15px 0; }
        .btn { padding: 8px 15px; cursor: pointer; border-radius: 5px; border: 1px solid #ccc; font-size: 13px; }
        .btn-blue { background: #007bff; color: white; border: none; }
        .btn-gray { background: #6c757d; color: white; border: none; }
        @media print {
            .no-print { display: none; }
            body { width: 100%; border: none; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-gray" onclick="if(window.opener) window.close(); else history.back();">← Назад</button>
        <button class="btn" onclick="window.print()">Обычная печать</button>
        <button class="btn btn-blue" onclick="printViaIP()">Печать по IP (Сетевой)</button>
        <div id="status" style="margin-top: 10px; font-size: 12px;"></div>
    </div>

    <div class="receipt">
        <div class="header">
            <div style="font-size: 1.1rem; font-weight: bold;"><?= h($clinic_name) ?></div>
            <?php if(!empty($settings['clinic_phone'])): ?>
                <div><?= h($settings['clinic_phone']) ?></div>
            <?php endif; ?>
            <div style="margin-top: 2mm;">ФИНАНСОВЫЙ ОТЧЕТ</div>
            <div style="font-size: 0.8rem;"><?= date('d.m.Y', strtotime($start_date)) ?> - <?= date('d.m.Y', strtotime($end_date)) ?></div>
        </div>

        <div class="divider"></div>

        <div class="row"><span>Всего приёмов:</span> <span><?= count($data) ?></span></div>
        <div class="row bold" style="font-size: 1.1rem; margin-top: 2mm;">
            <span>ИТОГО ВЫРУЧКА:</span> 
            <span><?= number_format($total_income, 0, '.', ' ') ?></span>
        </div>

        <div class="divider"></div>
        <div class="bold" style="text-decoration: underline; margin-bottom: 2mm;">ПО ВРАЧАМ:</div>
        <?php foreach ($doc_stats as $name => $sum): ?>
            <div class="row">
                <span><?= h($name) ?></span>
                <span><?= number_format($sum, 0, '.', ' ') ?></span>
            </div>
        <?php endforeach; ?>

        <div class="divider"></div>
        <div class="header" style="margin-top: 5mm; font-size: 0.75rem; opacity: 0.8;">
            <div>Отчёт сформирован:</div>
            <div><?= date('d.m.Y H:i') ?></div>
        </div>
    </div>

    <script>
        async function printViaIP() {
            const status = document.getElementById('status');
            status.innerHTML = '<span style="color: blue">Отправка на принтер...</span>';
            
            const start = '<?= $start_date ?>';
            const end = '<?= $end_date ?>';
            
            try {
                const res = await fetch(`../api/print_report_escpos.php?start_date=${start}&end_date=${end}`);
                const data = await res.json();
                if (data.success) {
                    status.innerHTML = '<span style="color: green">Отчёт напечатан!</span>';
                } else {
                    status.innerHTML = '<span style="color: red">Ошибка: ' + data.error + '</span>';
                }
            } catch (e) {
                status.innerHTML = '<span style="color: red">Ошибка связи с сервером</span>';
            }
        }
    </script>
</body>
</html>
