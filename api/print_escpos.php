<?php
require_once '../includes/api_auth.php';
require_role(['admin', 'cashier']);

$ids = $_GET['id'] ?? $_POST['id'] ?? '';
$receipt_id = $_GET['receipt_id'] ?? $_POST['receipt_id'] ?? '';

if ($receipt_id) {
    $stmt = $pdo->prepare("
        SELECT a.*, p.id as patient_id, p.full_name as patient, p.phone as patient_phone, p.dob as patient_dob, d.full_name as doctor, d.specialty_id, sp.name as doctor_specialty, s.name as service, s.price, sd.name as direction
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN specialties sp ON d.specialty_id = sp.id
        JOIN services s ON a.service_id = s.id
        LEFT JOIN service_directions sd ON s.direction_id = sd.id
        WHERE a.receipt_id = ?
    ");
    $stmt->execute([$receipt_id]);
} elseif ($ids) {
    $id_array = array_map('intval', explode(',', $ids));
    $id_array = array_filter($id_array);
    if (empty($id_array)) die(json_encode(['success' => false, 'error' => 'Invalid IDs']));
    $placeholders = implode(',', array_fill(0, count($id_array), '?'));
    $stmt = $pdo->prepare("
        SELECT a.*, p.id as patient_id, p.full_name as patient, p.phone as patient_phone, p.dob as patient_dob, d.full_name as doctor, d.specialty_id, sp.name as doctor_specialty, s.name as service, s.price, sd.name as direction
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN specialties sp ON d.specialty_id = sp.id
        JOIN services s ON a.service_id = s.id
        LEFT JOIN service_directions sd ON s.direction_id = sd.id
        WHERE a.id IN ($placeholders)
    ");
    $stmt->execute($id_array);
} else {
    die(json_encode(['success' => false, 'error' => 'No ID provided']));
}

$items = $stmt->fetchAll();
if (empty($items)) die(json_encode(['success' => false, 'error' => 'Appointments not found']));

// Get Printer IP from settings
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'printer_ip'");
$stmt->execute();
$printer_ip = $stmt->fetchColumn();

if (!$printer_ip) {
    die(json_encode(['success' => false, 'error' => 'Printer IP not configured in Settings']));
}

$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$clinic_name = $settings['clinic_name'] ?? 'LifeMed Clinic';
$clinic_phone = $settings['clinic_phone'] ?? '';
$clinic_address = $settings['clinic_address'] ?? '';
$social = json_decode($settings['social_links'] ?? '{}', true);

// 3. Format ESC/POS Commands
// Basic ESC/POS codes
$ESC = "\x1b";
$GS  = "\x1d";
$LF  = "\x0a";

$data = "";
$data .= $ESC . "a" . "\x01"; // Align center
// Logo disabled by user request
$data .= $ESC . "!" . "\x38"; // Double height/width
$data .= $clinic_name . $LF;
$data .= $ESC . "!" . "\x00"; // Normal font
if ($clinic_address) $data .= $clinic_address . $LF;
if ($clinic_phone) $data .= $clinic_phone . $LF;
$data .= "KVITANCIYA #" . ($items[0]['receipt_id'] ?: str_pad($items[0]['id'], 6, '0', STR_PAD_LEFT)) . $LF;
$data .= date('d.m.Y H:i') . $LF;
$data .= str_repeat("-", 48) . $LF;

$data .= $ESC . "a" . "\x00"; // Align left
$data .= str_pad("ID Pacienta:", 12) . str_pad($items[0]['patient_id'], 5, '0', STR_PAD_LEFT) . $LF;
$data .= str_pad("Pacient:", 12) . $items[0]['patient'] . $LF;
$data .= str_pad("Data rozhd:", 12) . ($items[0]['patient_dob'] ? date('d.m.Y', strtotime($items[0]['patient_dob'])) : '-') . $LF;
$data .= str_pad("Telefon:", 12) . $items[0]['patient_phone'] . $LF;
$data .= str_pad("Vrach:", 12) . ($items[0]['doctor'] ?: 'Obshee priyem') . $LF;

// Show specimen code for lab services
$has_lab = false;
$lab_specimen = null;
foreach ($items as $item) {
    if (!empty($item['specimen_code'])) {
        $has_lab = true;
        if (empty($lab_specimen)) {
            $lab_specimen = $item['specimen_code'];
        }
    }
}
if ($has_lab && !empty($lab_specimen)) {
    $data .= str_pad("Obrazets:", 12) . $lab_specimen . $LF;
}

if (!empty($items[0]['referring_doctor_name'])) {
    $data .= str_pad("Napravil:", 12) . $items[0]['referring_doctor_name'] . $LF;
}

// Add Queue Numbers
$queues = [];
foreach ($items as $item) {
    if ($item['direction'] && $item['queue_number'] > 0) {
        $queues[] = $item['direction'] . ": #" . $item['queue_number'];
    }
}
if (!empty($queues)) {
    $data .= str_repeat("-", 48) . $LF;
    $data .= $ESC . "!" . "\x18"; // Double height
    foreach ($queues as $q) {
        $data .= "OCHERED: " . $q . $LF;
    }
    $data .= $ESC . "!" . "\x00"; // Normal font
}

$data .= str_repeat("-", 48) . $LF;
$data .= "Uslugi:" . $LF;

$total = 0;
foreach ($items as $item) {
    $qty = $item['quantity'] ?? 1;
    $line_total = $item['price'] * $qty;
    $data .= "- " . $item['service'] . ($qty > 1 ? " (x{$qty})" : "") . $LF;
    $data .= str_pad(number_format($line_total, 0, '.', ' '), 48, " ", STR_PAD_LEFT) . $LF;
    $total += $line_total;
}

$data .= str_repeat("-", 48) . $LF;
$data .= $ESC . "!" . "\x10"; // Bold
$data .= "ITOGO K OPLATE:" . str_pad(number_format($total, 0, '.', ' '), 32, " ", STR_PAD_LEFT) . $LF;
$data .= $ESC . "!" . "\x00";
$data .= str_repeat("-", 48) . $LF . $LF;

$data .= $ESC . "a" . "\x01"; // Center
$data .= "Zdem Vas snova! Budte zdorovi!" . $LF;
if (!empty($social)) {
    $social_str = "";
    if (!empty($social['telegram'])) $social_str .= "TG: " . $social['telegram'];
    if (!empty($social['instagram'])) $social_str .= ($social_str ? " | " : "") . "IG: " . $social['instagram'];
    if ($social_str) $data .= $social_str . $LF;
}

// 4. QR Code (Standard ESC/POS GS ( k)
$qr_content = $clinic_name . " #" . $items[0]['id'];
$store_len = strlen($qr_content) + 3;
$pL = $store_len % 256;
$pH = intval($store_len / 256);

$data .= $GS . "(k" . "\x03\x00\x31\x43\x03"; // Set size
$data .= $GS . "(k" . "\x03\x00\x31\x45\x30"; // Error correction
$data .= $GS . "(k" . chr($pL) . chr($pH) . "\x31\x50\x30" . $qr_content; // Store data
$data .= $GS . "(k" . "\x03\x00\x31\x51\x30"; // Print QR

$data .= $LF . $LF . $LF . $LF . $LF;
$data .= "\x1d\x56\x42\x00"; // Full cut

// 5. Cyrillic Fix: Set Code Page 17 (CP866) and encode
$setup = $ESC . "t" . "\x11"; // Select CP866
$encoded_data = $setup . iconv('UTF-8', 'CP866//IGNORE', $data);

// 6. Send to Printer
try {
    $fp = @fsockopen($printer_ip, 9100, $errno, $errstr, 3);
    if (!$fp) {
        throw new Exception("Could not connect to printer: $errstr");
    }
    fwrite($fp, $encoded_data);
    fclose($fp);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
