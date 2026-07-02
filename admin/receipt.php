<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'cashier']);

$ids = $_GET['id'] ?? null;
$receipt_id = $_GET['receipt_id'] ?? null;

if ($receipt_id) {
    $stmt = $pdo->prepare("
        SELECT a.*, p.id as patient_id, p.full_name as patient, p.phone as patient_phone, p.dob, d.full_name as doctor, d.specialty_id, sp.name as doctor_specialty, s.name as service, s.price, sd.name as direction, sg.name as group_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN specialties sp ON d.specialty_id = sp.id
        JOIN services s ON a.service_id = s.id
        LEFT JOIN service_directions sd ON s.direction_id = sd.id
        LEFT JOIN service_groups sg ON sd.group_id = sg.id
        WHERE a.receipt_id = ?
    ");
    $stmt->execute([$receipt_id]);
} elseif ($ids) {
    $id_array = array_map('intval', explode(',', $ids));
    $id_array = array_filter($id_array);
    if (empty($id_array)) die('Invalid IDs');
    $placeholders = implode(',', array_fill(0, count($id_array), '?'));
    $stmt = $pdo->prepare("
        SELECT a.*, p.id as patient_id, p.full_name as patient, p.phone as patient_phone, p.dob, d.full_name as doctor, d.specialty_id, sp.name as doctor_specialty, s.name as service, s.price, sd.name as direction, sg.name as group_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN specialties sp ON d.specialty_id = sp.id
        JOIN services s ON a.service_id = s.id
        LEFT JOIN service_directions sd ON s.direction_id = sd.id
        LEFT JOIN service_groups sg ON sd.group_id = sg.id
        WHERE a.id IN ($placeholders)
    ");
    $stmt->execute($id_array);
} else {
    die('ID or Receipt ID required');
}

$items = $stmt->fetchAll();
if (empty($items))
    die('Appointments not found');

$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$social = json_decode($settings['social_links'] ?? '{}', true);
$clinic_name = $settings['clinic_name'] ?? 'LifeMed CRM';

$total_price = 0;
foreach ($items as $item) {
    $qty = $item['quantity'] ?? 1;
    $total_price += ($item['price'] * $qty);
}
$first_item = $items[0];
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>Чек #<?= $first_item['receipt_id'] ?: $first_item['id'] ?></title>
    <style>
        @import url('../assets/vendor/css/fonts-google.css');

        body {
            font-family: 'Courier Prime', 'Courier New', Courier, monospace;
            width: 80mm;
            margin: 0 auto;
            color: #1a1a1a;
            background-color: #f8f9fa;
        }

        .receipt {
            background-color: #fff;
            padding: 5mm;
            box-shadow: 0 0 10mm rgba(0,0,0,0.05);
            margin: 10px auto;
            position: relative;
        }

        .header {
            text-align: center;
            margin-bottom: 5mm;
        }

        .logo-container {
            margin-bottom: 4mm;
            display: flex;
            justify-content: center;
        }

        .logo-img {
            max-height: 25mm;
            max-width: 60mm;
            object-fit: contain;
            filter: grayscale(0.2); /* Slight aesthetic touch */
        }

        .clinic-name {
            font-family: 'Inter', sans-serif;
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 1.5mm;
            letter-spacing: -0.5px;
            color: #000;
            text-transform: uppercase;
        }

        .details-box {
            font-size: 0.85rem;
            line-height: 1.4;
            color: #444;
        }

        .receipt-info {
            margin-top: 3mm;
            font-weight: bold;
            font-size: 1rem;
            color: #000;
        }

        .divider {
            border-bottom: 1px dashed #000;
            margin: 3mm 0;
            opacity: 0.3;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            margin: 1.5mm 0;
            font-size: 0.9rem;
        }

        .service-list {
            margin-top: 2mm;
        }

        .service-item {
            margin-bottom: 2mm;
        }

        .total-section {
            margin: 4mm 0;
            padding: 3mm 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.2rem;
            font-family: 'Inter', sans-serif;
        }

        .footer {
            text-align: center;
            margin-top: 5mm;
            font-size: 0.8rem;
        }

        .qr {
            margin: 4mm 0;
            display: flex;
            justify-content: center;
        }

        @media print {
            body {
                background-color: #fff;
                width: 100%;
                margin: 0;
            }

            .receipt {
                box-shadow: none;
                margin: 0;
                border: none;
                padding: 3mm;
            }

            .no-print {
                display: none;
            }
        }
    </style>
    <script src="../assets/vendor/js/qrcode.min.js"></script>
</head>

<body>
    <div class="no-print" style="text-align: center; margin: 10px;">
        <button onclick="if(window.opener) { window.close(); } else { window.location.href='appointments.php'; }"
            style="padding: 10px 20px; cursor: pointer; border-radius: 5px; background: #6c757d; color: white; border: none; margin-right: 10px;">
            ← Назад / Закрыть
        </button>
        <button onclick="window.print()"
            style="padding: 10px 20px; cursor: pointer; border-radius: 5px; border: 1px solid #ccc;">Обычная печать
            (Браузер)</button>
        <button onclick="printViaIP('<?= $_SERVER['QUERY_STRING'] ?>')" id="ipPrintBtn"
            style="padding: 10px 20px; cursor: pointer; border-radius: 5px; background: #007bff; color: white; border: none; margin-left: 10px;">
            Печать по IP (Сетевой)
        </button>
        <div id="status" style="margin-top: 10px; font-size: 0.8rem;"></div>
    </div>

    <div class="receipt">
        <div class="header">
            <?php if (!empty($settings['clinic_logo'])): ?>
                <div class="logo-container">
                    <img src="../assets/img/<?= htmlspecialchars($settings['clinic_logo']) ?>" alt="Logo" class="logo-img">
                </div>
            <?php endif; ?>
            <div class="clinic-name"><?= htmlspecialchars($clinic_name) ?></div>
            <div class="details-box">
                <?php if (!empty($settings['clinic_address'])): ?>
                    <div><?= htmlspecialchars($settings['clinic_address']) ?></div>
                <?php endif; ?>
                <?php if (!empty($settings['clinic_phone'])): ?>
                    <div>Тел: <?= htmlspecialchars($settings['clinic_phone']) ?></div>
                <?php endif; ?>
            </div>
            <div class="receipt-info">КВИТАНЦИЯ #<?= $first_item['receipt_id'] ?: str_pad($first_item['id'], 6, '0', STR_PAD_LEFT) ?></div>
            <div style="font-size: 0.75rem; color: #666;"><?= date('d.m.Y H:i') ?></div>
        </div>

        <div class="divider"></div>

        <div class="item-row"><span>ID пациента:</span> <span><?= str_pad($first_item['patient_id'], 5, '0', STR_PAD_LEFT) ?></span></div>
        <div class="item-row"><span>Пациент:</span> <span><?= h($first_item['patient']) ?></span></div>
        <div class="item-row"><span>Дата рождения:</span> <span><?= $first_item['dob'] ? date('d.m.Y', strtotime($first_item['dob'])) : '-' ?></span></div>
        <div class="item-row"><span>Телефон:</span> <span><?= h($first_item['patient_phone']) ?></span></div>
        <div class="item-row"><span>Врач:</span> <span><?= h($first_item['doctor'] ?? 'Общий приём') ?></span></div>
        <?php if (!empty($first_item['referring_doctor_name'])): ?>
            <div class="item-row"><span>Направил:</span> <span><?= h($first_item['referring_doctor_name']) ?></span></div>
        <?php endif; ?>
        <?php
        $specimen_codes = [];
        foreach ($items as $item) {
            if (!empty($item['specimen_code']) && !in_array($item['specimen_code'], $specimen_codes)) {
                $specimen_codes[] = $item['specimen_code'];
            }
        }
        ?>
        <?php if (!empty($specimen_codes)): ?>
            <div class="item-row"><span>Образец:</span> <span style="font-weight: bold;"><?= h(implode(', ', $specimen_codes)) ?></span></div>
        <?php endif; ?>

        <?php 
        $queues = [];
        foreach ($items as $item) {
            if (!empty($item['direction']) && $item['queue_number'] > 0) {
                $queues[] = ['dir' => $item['direction'], 'num' => $item['queue_number']];
            }
        }
        if (!empty($queues)): ?>
            <div class="divider"></div>
            <div style="text-align: center; background: #f0f0f0; padding: 10px; border-radius: 5px;">
                <div style="font-size: 0.8rem; text-transform: uppercase; color: #666;">Очередь</div>
                <?php foreach ($queues as $q): ?>
                    <div style="font-size: 1.4rem; font-weight: bold;"><?= htmlspecialchars($q['dir']) ?>: #<?= $q['num'] ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="divider"></div>
        <div style="font-weight: bold; margin-bottom: 1mm;">Услуги:</div>
        <?php foreach ($items as $item):
            $qty = $item['quantity'] ?? 1;
            $line_total = $item['price'] * $qty;
            ?>
            <div class="service-item">
                <div class="item-row">
                    <span>
                        <?= h($item['service']) ?>     <?= $qty > 1 ? "(x{$qty})" : "" ?>
                    </span>
                    <span><?= number_format($line_total, 0, '.', ' ') ?></span>
                </div>
                <?php if (!empty($item['direction'])): ?>
                    <div style="font-size: 0.7rem; color: #666; margin-left: 2mm;"><?= h($item['direction']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="divider"></div>

        <div class="total-section">
            <div class="total-row">
                <span>ИТОГО К ОПЛАТЕ:</span>
                <span><?= number_format($total_price, 0, '.', ' ') ?></span>
            </div>
        </div>

        <div class="divider"></div>

        <div class="footer">
            <p>Ждем Вас снова! Будьте здоровы!</p>
            <?php if (!empty($social)): ?>
                <div style="font-size: 0.7rem;">
                    <?php if (!empty($social['telegram']))
                        echo "TG: " . $social['telegram']; ?>
                    <?php if (!empty($social['instagram']))
                        echo " | IG: " . $social['instagram']; ?>
                </div>
            <?php endif; ?>
            <div class="qr">
                <?php
                $qr_template = $settings['qr_content'] ?? '{clinic} Visit #{id}';
                $qr_text = str_replace(
                    ['{clinic}', '{id}', '{patient}'],
                    [$clinic_name, $first_item['id'], $first_item['patient']],
                    $qr_template
                );
                ?>
                <div id="qrcode" style="display: flex; justify-content: center;"></div>
                <script>
                    new QRCode(document.getElementById("qrcode"), {
                        text: <?= json_encode($qr_text) ?>,
                        width: 80,
                        height: 80,
                        colorDark : "#000000",
                        colorLight : "#ffffff",
                        correctLevel : QRCode.CorrectLevel.H
                    });
                </script>
            </div>
        </div>
    </div>

    <script>
        window.onload = function () {
            // window.print();
        }

        async function printViaIP(queryString) {
            const btn = document.getElementById('ipPrintBtn');
            const status = document.getElementById('status');

            btn.disabled = true;
            status.innerHTML = '<span style="color: blue">Отправка на принтер...</span>';

            try {
                const res = await fetch(`../api/print_escpos.php?${queryString}`);
                const data = await res.json();
                if (data.success) {
                    status.innerHTML = '<span style="color: green">Чек успешно напечатан!</span>';
                } else {
                    status.innerHTML = '<span style="color: red">Ошибка: ' + data.error + '</span>';
                }
            } catch (e) {
                console.error(e);
                status.innerHTML = '<span style="color: red">Ошибка связи с сервером</span>';
            } finally {
                btn.disabled = false;
                setTimeout(() => { status.innerHTML = ''; }, 3000);
            }
        }
    </script>
</body>

</html>