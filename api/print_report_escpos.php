<?php
require_once '../includes/api_auth.php';
require_admin();

$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// 1. Get Printer IP
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'printer_ip'");
$stmt->execute();
$printer_ip = $stmt->fetchColumn();

if (!$printer_ip) {
    die(json_encode(['success' => false, 'error' => 'Printer IP not configured']));
}

// 2. Fetch Data
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

$doc_stats = [];
foreach ($data as $row) {
    $name = $row['doctor'] ?: 'Obshee';
    if (!isset($doc_stats[$name])) $doc_stats[$name] = 0;
    $qty = $row['quantity'] ?? 1;
    $doc_stats[$name] += ($row['price'] * $qty);
}
arsort($doc_stats);

$clinic_name_res = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'clinic_name'");
$clinic_name = $clinic_name_res->fetchColumn() ?: 'LifeMed Clinic';

// 3. Format ESC/POS
$ESC = "\x1b";
$LF = "\x0a";

$out = "";
$out .= $ESC . "a" . "\x01"; // Center
$out .= $ESC . "!" . "\x38"; // Double height/width
$out .= $clinic_name . $LF;
$out .= $ESC . "!" . "\x00"; // Normal
$out .= "FINANSOVIY OTCHET" . $LF;
$out .= $start_date . " - " . $end_date . $LF;
$out .= str_repeat("-", 48) . $LF;

$out .= $ESC . "a" . "\x00"; // Left
$out .= "Vsego priyemov: " . count($data) . $LF;
$out .= $ESC . "!" . "\x10"; // Bold
$out .= "ITOGO: " . str_pad(number_format($total_income, 0, '.', ' ') . " SUM", 41, " ", STR_PAD_LEFT) . $LF;
$out .= $ESC . "!" . "\x00";
$out .= str_repeat("-", 48) . $LF;

$out .= "PO VRACHAM:" . $LF;
foreach ($doc_stats as $name => $sum) {
    $out .= $name . $LF;
    $out .= str_pad("", 10) . str_pad(number_format($sum, 0, '.', ' ') . " SUM", 38, " ", STR_PAD_LEFT) . $LF;
}

$out .= str_repeat("-", 48) . $LF;
$out .= $ESC . "a" . "\x01";
$out .= "Sformirovan: " . date('d.m.Y H:i') . $LF;
$out .= $LF . $LF . $LF . $LF . $LF;
$out .= "\x1d\x56\x42\x00"; // Cut

// 5. Cyrillic Fix: Select Code Page 17 (CP866)
$setup = $ESC . "t" . "\x11"; 
$encoded_out = $setup . iconv('UTF-8', 'CP866//IGNORE', $out);

// 6. Send to Printer
try {
    $fp = @fsockopen($printer_ip, 9100, $errno, $errstr, 3);
    if (!$fp) throw new Exception("Error: $errstr");
    fwrite($fp, $encoded_out);
    fclose($fp);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
