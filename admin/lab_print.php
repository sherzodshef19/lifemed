<?php
require_once '../config/db.php';
require_once '../includes/auth_functions.php';
check_role(['admin', 'doctor', 'cashier']);

$patient_id = $_GET['patient_id'] ?? die('Patient required');
$template_id = $_GET['template_id'] ?? die('Template required');

$patient = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$patient->execute([$patient_id]);
$p = $patient->fetch();

$template = $pdo->prepare("SELECT * FROM lab_templates WHERE id = ?");
$template->execute([$template_id]);
$t = $template->fetch();

$content = $t['content'];
$print_date = date('d.m.Y');
$doctor_name = null;
if (isset($_GET['result_id'])) {
    $res_stmt = $pdo->prepare("SELECT r.*, d.full_name as doctor_name FROM lab_results r LEFT JOIN doctors d ON r.doctor_id = d.id WHERE r.id = ?");
    $res_stmt->execute([$_GET['result_id']]);
    $res_data = $res_stmt->fetch();
    if ($res_data) {
        $content = $res_data['result_data'];
        $print_date = date('d.m.Y', strtotime($res_data['created_at']));
        $doctor_name = $res_data['doctor_name'];
    }
}

$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$clinic_name = $settings['clinic_name'] ?? 'LifeMed Clinic';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= h($t['title']) ?> - <?= h($p['full_name']) ?></title>
    <link rel="stylesheet" href="../assets/vendor/css/fontawesome.min.css">
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: 'Arial', sans-serif; background: #eee; margin: 0; padding: 0; -webkit-print-color-adjust: exact; }
        
        .no-print-bar { background: #222; color: white; padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; font-size: 13px; }
        .btn-print { background: #27ae60; color: white; border: none; padding: 5px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-back { background: transparent; color: #bbb; border: 1px solid #444; padding: 4px 15px; border-radius: 4px; cursor: pointer; }
        .btn-back:hover { color: white; border-color: #666; }
        .ms-3 { margin-left: 1rem; }

        .a4-page { 
            width: 210mm; 
            min-height: 297mm; 
            padding: 8mm 12mm; 
            margin: 0 auto; 
            background: white; 
            position: relative;
            box-sizing: border-box;
        }

        .logo-box { display: flex; align-items: center; }
        .logo-img { max-height: 90px; width: auto; margin-right: 15px; object-fit: contain; }
        .clinic-name { font-size: 18px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        .clinic-contacts { text-align: right; font-size: 10px; color: #475569; line-height: 1.2; }

        .analysis-title { text-align: center; font-size: 15px; font-weight: 800; color: #000; margin: 5px 0; text-transform: uppercase; border-bottom: 1px solid #eee; padding-bottom: 3px; }

        .patient-card { border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; margin-bottom: 10px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; font-size: 11px; background: #fafafa; }
        .info-label { color: #64748b; font-weight: 500; margin-right: 5px; }
        .info-value { color: #000; font-weight: 700; }

        .results-container { min-height: 150mm; }
        .results-container table { width: 100% !important; border-collapse: collapse !important; margin: 0 !important; }
        .results-container td, .results-container th { border: 1px solid #000 !important; padding: 1px 6px !important; font-size: 16.5px !important; line-height: 1.1 !important; color: #000 !important; }
        .results-container th { background: #eee !important; font-weight: 800 !important; text-transform: uppercase; font-size: 12px !important; }

        .signature-footer { margin-top: 15px; display: flex; justify-content: space-between; align-items: flex-end; font-size: 14px; }
        .sig-block { border-bottom: 1px solid #000; width: 150px; text-align: right; padding-bottom: 1px; }

        @media print {
            body { background: white; }
            .no-print-bar { display: none; }
            .a4-page { margin: 0; box-shadow: none; width: 100%; height: auto; }
        }
    </style>
</head>
<body>
    <div class="no-print-bar">
        <div>
            <button class="btn-back" onclick="window.location.href='index.php'"><i class="fas fa-arrow-left me-2"></i> НАЗАД</button>
            <span class="ms-3"><i class="fas fa-file-medical me-2 text-primary"></i>Бланк: <?= h($t['title']) ?> | Пациент: <?= h($p['full_name']) ?></span>
        </div>
        <button class="btn-print" onclick="window.print()"><i class="fas fa-print me-2"></i> ПЕЧАТЬ</button>
    </div>

    <div class="a4-page">
        <div class="clinic-header">
            <div class="logo-box">
                <?php if (!empty($settings['clinic_logo'])): ?>
                    <img src="../assets/img/<?= htmlspecialchars($settings['clinic_logo']) ?>" alt="Logo" class="logo-img">
                <?php else: ?>
                    <div style="width: 32px; height: 32px; background: #2563eb; border-radius: 6px; margin-right: 10px;"></div>
                <?php endif; ?>
                <div class="clinic-name"><?= h($clinic_name) ?></div>
            </div>
            <div class="clinic-contacts">
                <?= h($settings['clinic_address'] ?? 'Ташкент') ?> | Тел: <?= h($settings['clinic_phone'] ?? '') ?><br>
                Дата: <b><?= $print_date ?></b>
            </div>
        </div>

        <div class="analysis-title"><?= h($t['title']) ?></div>

        <div class="patient-card">
            <div><span class="info-label">Пациент:</span> <span class="info-value"><?= h($p['full_name']) ?></span></div>
            <div><span class="info-label">ID:</span> <span class="info-value"><?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?></span></div>
            <div><span class="info-label">Дата рожд:</span> <span class="info-value"><?= date('d.m.Y', strtotime($p['dob'])) ?></span></div>
            <div><span class="info-label">Пол/Возраст:</span> <span class="info-value"><?= (!empty($p['gender']) ? ($p['gender'] == 'male' ? 'М' : 'Ж') : '—') ?> / <?= date_diff(date_create($p['dob']), date_create('today'))->y ?> л.</span></div>
        </div>

        <div class="results-container">
            <?php
            // Content is from trusted lab templates. Sanitize at save-time in API.
            // Only allow safe tags: tables, formatting, basic structure
            $allowed = '<table><thead><tbody><tr><th><td><p><br><b><i><u><strong><em><span><div><h1><h2><h3><h4><h5><h6><ul><ol><li><img><style>';
            echo strip_tags($content, $allowed);
            ?>
        </div>

        <div class="signature-footer">
            <div>
                <b>Врач:</b> <?= !empty($doctor_name) ? htmlspecialchars($doctor_name) : ($_SESSION['role'] == 'doctor' ? htmlspecialchars($_SESSION['full_name']) : '________________') ?>
                <div class="sig-block"></div>
            </div>
            <div style="text-align: right; color: #666; font-size: 9px;">
                Подпись и печать клиники
            </div>
        </div>
    </div>

    <script>
        // Automatic print focus if needed
    </script>
</body>
</html>
